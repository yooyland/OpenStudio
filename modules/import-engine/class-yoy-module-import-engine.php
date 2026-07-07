<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/class-import-engine.php';

final class YooY_Module_Import_Engine extends YooY_Module_Base {

    private YooY_Import_Engine $engine;

    public function id(): string { return 'import-engine'; }
    public function name(): string { return 'Import Engine'; }
    public function description(): string { return 'Unified import pipeline for external assets into YooY Gallery Store.'; }
    public function version(): string { return '1.0.0'; }

    public function init(YooY_Core_Engine $core): void {
        parent::init($core);
        $this->engine = new YooY_Import_Engine();
    }

    public function register_rest_routes(): void {
        $auth = 'is_user_logged_in';

        $this->register_route('/schema', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'schema'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/queue', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'queue_list'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/history', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'history'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/upload', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'upload'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/folder', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'folder'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/process', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'process'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/stats', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'stats'], 'permission_callback' => [$this, 'require_admin'],
        ]);
    }

    public function schema(): WP_REST_Response {
        return $this->success($this->engine->schema());
    }

    public function queue_list(WP_REST_Request $request): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) {
            return $user;
        }
        $status = sanitize_text_field($request->get_param('status') ?? '');
        return $this->success([
            'queue' => $this->engine->queue()->list($user, $status !== '' ? $status : null),
        ]);
    }

    public function history(WP_REST_Request $request): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) {
            return $user;
        }
        return $this->success([
            'history' => $this->engine->history($user, (int) ($request->get_param('limit') ?? 50)),
        ]);
    }

    public function upload(WP_REST_Request $request): WP_REST_Response {
        try {
            $user = $this->require_user();
            if ($user instanceof WP_REST_Response) {
                return $user;
            }

            $options = $this->parse_options($request);
            $files   = $this->collect_upload_files($request);

            if (empty($files)) {
                return $this->error('No files provided.');
            }

            $queued  = $this->engine->enqueue_files($user, $files, $options);
            $results = $this->engine->process_queue($user, count($queued));

            return $this->success([
                'queued'  => $queued,
                'results' => $results,
            ], 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function folder(WP_REST_Request $request): WP_REST_Response {
        try {
            $user = $this->require_user();
            if ($user instanceof WP_REST_Response) {
                return $user;
            }

            $body    = $request->get_json_params() ?: [];
            $options = $this->parse_options($request, $body);
            $options['source'] = 'folder';
            $options['origin'] = sanitize_text_field($body['origin'] ?? 'Folder');

            $files = [];
            foreach (($body['files'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $name = sanitize_text_field($row['filename'] ?? $row['name'] ?? 'import.bin');
                $b64  = (string) ($row['data'] ?? $row['base64'] ?? '');
                if ($b64 === '') {
                    continue;
                }
                if (strpos($b64, 'base64,') !== false) {
                    $parts = explode('base64,', $b64, 2);
                    $b64   = $parts[1];
                }
                $binary = base64_decode($b64, true);
                if ($binary === false || $binary === '') {
                    continue;
                }
                $files[] = [
                    'filename' => $name,
                    'binary'   => $binary,
                    'size'     => strlen($binary),
                    'mime'     => sanitize_mime_type($row['mime'] ?? ''),
                ];
            }

            if (empty($files)) {
                return $this->error('No folder files provided.');
            }

            $queued  = $this->engine->enqueue_files($user, $files, $options);
            $results = $this->engine->process_queue($user, count($files));

            return $this->success([
                'queued'  => $queued,
                'results' => $results,
            ], 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function process(WP_REST_Request $request): WP_REST_Response {
        try {
            $user = $this->require_user();
            if ($user instanceof WP_REST_Response) {
                return $user;
            }

            $body = $request->get_json_params() ?: [];
            $id   = sanitize_text_field($body['queue_id'] ?? '');
            $limit = max(1, min(20, (int) ($body['limit'] ?? 10)));

            if ($id !== '') {
                $result = $this->engine->process_item($user, $id);
                return $this->success(['results' => [$result]]);
            }

            return $this->success([
                'results' => $this->engine->process_queue($user, $limit),
            ]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function stats(): WP_REST_Response {
        return $this->success($this->engine->queue()->stats());
    }

    public function require_admin(): bool {
        return current_user_can('manage_options');
    }

    private function parse_options(WP_REST_Request $request, ?array $body = null): array {
        $body = $body ?? ($request->get_json_params() ?: []);
        $params = $request->get_params();
        return [
            'source'             => sanitize_text_field($body['source'] ?? $params['source'] ?? $request->get_param('source') ?? 'upload'),
            'origin'             => sanitize_text_field($body['origin'] ?? $params['origin'] ?? $request->get_param('origin') ?? 'Imported'),
            'type_hint'          => sanitize_text_field($body['type_hint'] ?? $params['type_hint'] ?? ''),
            'project_id'         => sanitize_text_field($body['project_id'] ?? $params['project_id'] ?? ''),
            'new_project_title'  => sanitize_text_field($body['new_project_title'] ?? $params['new_project_title'] ?? ''),
            'skip_ai'            => !empty($body['skip_ai']) || !empty($params['skip_ai']),
        ];
    }

    private function collect_upload_files(WP_REST_Request $request): array {
        $files = [];
        $params = $request->get_file_params();

        if (!empty($params['files'])) {
            $files = array_merge($files, $this->normalize_file_param($params['files']));
        }
        if (!empty($params['file'])) {
            $files = array_merge($files, $this->normalize_file_param($params['file']));
        }

        if (!empty($files)) {
            return $files;
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            return [];
        }

        foreach (($body['files'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $b64 = (string) ($row['data'] ?? $row['base64'] ?? '');
            if ($b64 === '') {
                continue;
            }
            if (strpos($b64, 'base64,') !== false) {
                $parts = explode('base64,', $b64, 2);
                $b64   = $parts[1];
            }
            $binary = base64_decode($b64, true);
            if ($binary === false) {
                continue;
            }
            $files[] = [
                'filename' => sanitize_text_field($row['filename'] ?? 'import.bin'),
                'binary'   => $binary,
                'size'     => strlen($binary),
                'mime'     => sanitize_mime_type($row['mime'] ?? ''),
            ];
        }

        return $files;
    }

    private function normalize_file_param(array $file): array {
        $out = [];
        if (isset($file['tmp_name']) && !is_array($file['tmp_name'])) {
            $file = [$file];
        } elseif (isset($file['tmp_name']) && is_array($file['tmp_name'])) {
            $batch = [];
            foreach ($file['tmp_name'] as $i => $tmp) {
                $batch[] = [
                    'tmp_name' => $tmp,
                    'name'     => $file['name'][$i] ?? 'import.bin',
                    'size'     => $file['size'][$i] ?? 0,
                    'type'     => $file['type'][$i] ?? '',
                    'error'    => $file['error'][$i] ?? 0,
                ];
            }
            $file = $batch;
        }

        foreach ($file as $row) {
            if (!is_array($row) || empty($row['tmp_name']) || !empty($row['error'])) {
                continue;
            }
            $binary = @file_get_contents($row['tmp_name']);
            if ($binary === false || $binary === '') {
                continue;
            }
            $out[] = [
                'filename' => $row['name'] ?? 'import.bin',
                'binary'   => $binary,
                'size'     => (int) ($row['size'] ?? strlen($binary)),
                'mime'     => sanitize_mime_type($row['type'] ?? ''),
            ];
        }

        return $out;
    }
}
