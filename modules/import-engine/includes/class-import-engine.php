<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-import-validator.php';
require_once __DIR__ . '/class-import-queue.php';
require_once __DIR__ . '/class-import-metadata-extractor.php';
require_once __DIR__ . '/class-import-thumbnail-generator.php';
require_once __DIR__ . '/class-import-ai-metadata.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-asset-generator.php';

final class YooY_Import_Engine {

    private YooY_Import_Queue $queue;
    private YooY_Gallery_Store $gallery;
    private ?YooY_Gallery_Actions $actions;

    public function __construct(?YooY_Gallery_Store $gallery = null, ?YooY_Gallery_Actions $actions = null) {
        $this->queue   = new YooY_Import_Queue();
        $this->gallery = $gallery ?? new YooY_Gallery_Store();
        if ($actions !== null) {
            $this->actions = $actions;
        } else {
            require_once YOY_AI_STUDIO_MODULES_DIR . 'gallery/includes/class-gallery-actions.php';
            $this->actions = new YooY_Gallery_Actions($this->gallery);
        }
    }

    public function queue(): YooY_Import_Queue {
        return $this->queue;
    }

    public function schema(): array {
        return [
            'types'    => YooY_Import_Validator::supported_types(),
            'origins'  => YooY_Import_Validator::origins(),
            'sources'  => YooY_Import_Validator::sources(),
            'pipeline' => ['import', 'queue', 'metadata', 'thumbnail', 'register', 'store', 'gallery'],
            'future'   => ['folder_watch', 'google_drive', 'dropbox', 'onedrive', 'sharepoint', 'nas'],
        ];
    }

    public function enqueue_files(int $user_id, array $files, array $options = []): array {
        $source = sanitize_text_field($options['source'] ?? 'upload');
        $origin = sanitize_text_field($options['origin'] ?? 'Imported');
        $queued = [];

        foreach ($files as $file) {
            $filename = sanitize_text_field($file['filename'] ?? 'import.bin');
            $binary   = (string) ($file['binary'] ?? '');
            $size     = (int) ($file['size'] ?? strlen($binary));
            $mime     = sanitize_mime_type($file['mime'] ?? '');
            $type_hint = sanitize_text_field($options['type_hint'] ?? ($file['type_hint'] ?? ''));

            try {
                $validated = YooY_Import_Validator::validate_file($filename, $size, $mime, $type_hint ?: null);
            } catch (Exception $e) {
                $queued[] = [
                    'status' => 'failed',
                    'error'  => $e->getMessage(),
                    'filename' => $filename,
                ];
                continue;
            }

            $entry = $this->queue->enqueue($user_id, [
                'filename' => $validated['filename'],
                'type'     => $validated['type'],
                'source'   => $source,
                'origin'   => $origin,
                'options'  => $options,
            ]);

            $path = $this->queue->store_binary($user_id, $entry['id'], $binary);
            if ($path !== '') {
                $this->queue->update($user_id, $entry['id'], ['binary_ref' => $path]);
                $entry['binary_ref'] = $path;
            } else {
                $this->queue->update($user_id, $entry['id'], [
                    'status' => 'failed',
                    'error'  => 'Failed to stage file for import.',
                ]);
                $entry['status'] = 'failed';
            }

            $queued[] = $entry;
        }

        return $queued;
    }

    public function process_queue(int $user_id, int $limit = 10): array {
        $pending = $this->queue->list($user_id, 'queued');
        $results = [];
        $count   = 0;

        foreach ($pending as $item) {
            if ($count >= $limit) {
                break;
            }
            $results[] = $this->process_item($user_id, $item['id']);
            $count++;
        }

        return $results;
    }

    public function process_item(int $user_id, string $queue_id): array {
        $item = $this->queue->get($user_id, $queue_id);
        if (!$item) {
            throw new Exception('Import queue item not found.');
        }

        $this->queue->update($user_id, $queue_id, ['status' => 'processing', 'progress' => 10]);

        try {
            $binary = $this->queue->load_binary((string) ($item['binary_ref'] ?? ''));
            if ($binary === '') {
                throw new Exception('Import binary missing.');
            }

            $options  = is_array($item['options'] ?? null) ? $item['options'] : [];
            $filename = (string) ($item['filename'] ?? 'import.bin');
            $type     = (string) ($item['type'] ?? 'image');
            $source   = (string) ($item['source'] ?? 'upload');
            $origin   = (string) ($item['origin'] ?? 'Imported');

            $validated = YooY_Import_Validator::validate_file($filename, strlen($binary), '', $type);
            $this->queue->update($user_id, $queue_id, ['progress' => 25]);

            $mime  = $validated['mime'] !== '' ? $validated['mime'] : self::mime_for_ext($validated['extension']);
            $media = YooY_Asset_Generator::import_from_binary($binary, $filename, $mime, $user_id);
            if (empty($media['url'])) {
                throw new Exception('Failed to store imported file.');
            }
            $media['user_id'] = $user_id;

            $this->queue->update($user_id, $queue_id, ['progress' => 45]);
            $extracted = YooY_Import_Metadata_Extractor::extract($type, (string) ($item['binary_ref'] ?? ''), $binary, $validated);

            $this->queue->update($user_id, $queue_id, ['progress' => 60]);
            $ai_meta = YooY_Import_AI_Metadata::generate($filename, $type, $extracted, $options);

            $thumb = YooY_Import_Thumbnail_Generator::generate($type, $media, $extracted, $ai_meta['title']);
            $this->queue->update($user_id, $queue_id, ['progress' => 80]);

            $gallery_item = $this->register_asset($user_id, [
                'type'          => $type,
                'origin'        => $origin,
                'source'        => $source,
                'provider'      => YooY_Import_Validator::provider_for_source($source),
                'title'         => $ai_meta['title'],
                'description'   => $ai_meta['description'],
                'attachment_id' => (int) ($media['attachment_id'] ?? 0),
                'asset_url'     => $media['full_url'] ?? $media['url'],
                'image_url'     => $media['full_url'] ?? $media['url'],
                'original_url'  => $media['original_url'] ?? $media['url'],
                'full_url'      => $media['full_url'] ?? $media['url'],
                'large_url'     => $media['large_url'] ?? $media['url'],
                'medium_large_url' => $media['medium_large_url'] ?? $media['url'],
                'images'        => $media['images'] ?? [],
                'thumbnail_url' => $thumb['thumbnail_url'] ?? $media['thumbnail_url'] ?? $media['thumbnail'] ?? $media['url'],
                'status'        => 'completed',
                'meta'          => [
                    'origin'      => $origin,
                    'import_id'   => $queue_id,
                    'source'      => $source,
                    'description' => $ai_meta['description'],
                    'status'      => 'completed',
                    'extracted'   => $extracted,
                    'ai'          => $ai_meta,
                    'keywords'    => $ai_meta['keywords'],
                    'tags'        => $ai_meta['tags'],
                    'category'    => $ai_meta['category'],
                ],
            ]);

            $project_id = sanitize_text_field($options['project_id'] ?? '');
            $new_project_title = sanitize_text_field($options['new_project_title'] ?? '');
            if ($new_project_title !== '') {
                require_once YOY_AI_STUDIO_MODULES_DIR . 'projects/includes/class-project-store.php';
                $project = (new YooY_Project_Store())->create($user_id, [
                    'title' => $new_project_title,
                    'type'  => $type === 'writing' ? 'writing' : ($type === 'video' ? 'video' : 'mixed'),
                ]);
                $project_id = $project['id'] ?? '';
            }
            if ($project_id !== '') {
                $this->actions->save_to_project($user_id, $gallery_item['id'], $project_id);
                $gallery_item['meta']['project_id'] = $project_id;
            }

            $this->register_job($user_id, $gallery_item, $queue_id);
            $this->queue->remove_binary((string) ($item['binary_ref'] ?? ''));

            $this->queue->update($user_id, $queue_id, [
                'status'     => 'completed',
                'progress'   => 100,
                'gallery_id' => $gallery_item['id'],
                'error'      => '',
            ]);

            $this->queue->log_event([
                'user_id'    => $user_id,
                'queue_id'   => $queue_id,
                'gallery_id' => $gallery_item['id'],
                'filename'   => $filename,
                'type'       => $type,
                'status'     => 'completed',
                'source'     => $source,
            ]);

            return [
                'queue_id' => $queue_id,
                'status'   => 'completed',
                'item'     => $gallery_item,
            ];
        } catch (Exception $e) {
            $this->queue->update($user_id, $queue_id, [
                'status'   => 'failed',
                'progress' => 100,
                'error'    => $e->getMessage(),
            ]);
            $this->queue->log_event([
                'user_id'  => $user_id,
                'queue_id' => $queue_id,
                'filename' => $item['filename'] ?? '',
                'type'     => $item['type'] ?? '',
                'status'   => 'failed',
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function import_direct(int $user_id, string $binary, string $filename, array $options = []): array {
        $queued = $this->enqueue_files($user_id, [[
            'filename' => $filename,
            'binary'   => $binary,
            'size'     => strlen($binary),
            'mime'     => $options['mime'] ?? '',
        ]], $options);

        if (empty($queued[0]['id'])) {
            throw new Exception($queued[0]['error'] ?? 'Import failed.');
        }
        if (($queued[0]['status'] ?? '') === 'failed') {
            throw new Exception($queued[0]['error'] ?? 'Import failed.');
        }

        return $this->process_item($user_id, $queued[0]['id']);
    }

    public function history(int $user_id, int $limit = 50): array {
        $items = $this->queue->list($user_id);
        return array_slice($items, 0, $limit);
    }

    private function register_asset(int $user_id, array $payload): array {
        $payload['id']      = 'gal_' . wp_generate_uuid4();
        $payload['user_id'] = $user_id;
        $payload['job_id']  = 'impjob_' . wp_generate_uuid4();
        $payload['studio']  = sanitize_text_field($payload['studio'] ?? (($payload['type'] ?? 'image') . '-studio'));
        $payload['prompt']  = $payload['prompt'] ?? '';

        return $this->gallery->save($user_id, $payload);
    }

    private function register_job(int $user_id, array $gallery_item, string $queue_id): void {
        if (!class_exists('YooY_Job_Store')) {
            return;
        }

        $store = new YooY_Job_Store();
        $store->save($user_id, [
            'job_id'       => $gallery_item['job_id'] ?? ('impjob_' . wp_generate_uuid4()),
            'status'       => YooY_Job_Status::COMPLETED,
            'type'         => $gallery_item['type'] ?? 'image',
            'provider'     => $gallery_item['provider'] ?? 'Imported',
            'prompt'       => $gallery_item['prompt'] ?? '',
            'output'       => [
                'primary'   => $gallery_item['image_url'] ?? '',
                'thumbnail' => $gallery_item['thumbnail_url'] ?? '',
            ],
            'attachment_id'=> (int) ($gallery_item['attachment_id'] ?? 0),
            'credits_used' => 0,
            'meta'         => [
                'origin'    => $gallery_item['meta']['origin'] ?? 'Imported',
                'import_id' => $queue_id,
                'gallery_id'=> $gallery_item['id'] ?? '',
            ],
        ], 'import-engine');
    }

    private static function mime_for_ext(string $ext): string {
        $map = [
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp', 'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'avi' => 'video/x-msvideo',
            'mkv' => 'video/x-matroska', 'webm' => 'video/webm',
            'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'flac' => 'audio/flac', 'aac' => 'audio/aac',
            'pdf' => 'application/pdf', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }
}
