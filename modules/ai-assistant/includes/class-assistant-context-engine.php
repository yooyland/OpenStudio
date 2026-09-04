<?php
if (!defined('ABSPATH')) exit;

/**
 * Context Engine — Active Project + Gallery recent only.
 * No new DB / Store. Reuses YooY_Project_Store + YooY_Gallery_Store.
 */
final class YooY_Assistant_Context_Engine {

    /** @var YooY_Project_Store|null */
    private $projects;

    /** @var YooY_Gallery_Store|null */
    private $gallery;

    public function __construct() {
        if (class_exists('YooY_Project_Store')) {
            $this->projects = new YooY_Project_Store();
        } elseif (is_readable(YOY_AI_STUDIO_MODULES_DIR . 'projects/includes/class-project-store.php')) {
            require_once YOY_AI_STUDIO_MODULES_DIR . 'projects/includes/class-project-store.php';
            if (class_exists('YooY_Project_Store')) {
                $this->projects = new YooY_Project_Store();
            }
        }

        if (class_exists('YooY_Gallery_Store')) {
            $this->gallery = new YooY_Gallery_Store();
        } elseif (is_readable(YOY_AI_STUDIO_MODULES_DIR . 'gallery/includes/class-gallery-store.php')) {
            require_once YOY_AI_STUDIO_MODULES_DIR . 'gallery/includes/class-gallery-store.php';
            if (class_exists('YooY_Gallery_Store')) {
                $this->gallery = new YooY_Gallery_Store();
            }
        }
    }

    /**
     * @param int                  $user_id
     * @param string|null          $project_id
     * @param string|null          $current_studio
     * @param array<string, mixed> $client_context Selected/last asset from UI (session).
     * @return array<string, mixed>
     */
    public function build(int $user_id, ?string $project_id = null, ?string $current_studio = null, array $client_context = []): array {
        $project_id = $project_id ? sanitize_text_field($project_id) : '';
        $studio     = $current_studio ? sanitize_text_field($current_studio) : '';

        $mode    = 'general';
        $project = null;

        if ($user_id > 0 && $project_id !== '' && $this->projects) {
            $row = $this->projects->get($user_id, $project_id);
            if (is_array($row)) {
                $mode    = 'project';
                $project = [
                    'id'          => (string) ($row['id'] ?? $project_id),
                    'title'       => (string) ($row['title'] ?? 'Project'),
                    'type'        => (string) ($row['type'] ?? 'mixed'),
                    'description' => (string) ($row['description'] ?? ''),
                    'asset_count' => is_array($row['assets'] ?? null) ? count($row['assets']) : 0,
                ];
            }
        }

        $recent = [];
        if ($user_id > 0 && $this->gallery) {
            $filters = [];
            if ($mode === 'project' && $project_id !== '') {
                $filters['project_id'] = $project_id;
            }
            $items = $this->gallery->list($user_id, $filters);
            if (is_array($items)) {
                foreach (array_slice($items, 0, 8) as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $recent[] = $this->normalize_asset_ref($item);
                }
            }
        }

        $selected = $this->normalize_asset_ref(isset($client_context['selected_asset']) ? $client_context['selected_asset'] : null);
        $last     = $this->normalize_asset_ref(isset($client_context['last_asset']) ? $client_context['last_asset'] : null);

        return [
            'mode'            => $mode,
            'project'         => $project,
            'current_studio'  => $studio,
            'selected_asset'  => $selected,
            'last_asset'      => $last,
            'recent_assets'   => $recent,
            'source_authority'=> $this->source_authority_hint(),
            'credits_note'    => '대화·추천·프롬프트 보완은 Credits를 차감하지 않습니다. Studio 실행 시에만 기존 Credits가 적용됩니다.',
            'gallery_policy'  => '대화는 Gallery에 저장되지 않습니다. 생성된 Asset만 Gallery에 저장됩니다.',
        ];
    }

    /**
     * Public-safe asset reference only (no private prompt / provider).
     *
     * @param mixed $raw
     * @return array<string, mixed>|null
     */
    private function normalize_asset_ref($raw) {
        if (!is_array($raw)) {
            return null;
        }
        $id = sanitize_text_field((string) ($raw['gallery_id'] ?? $raw['id'] ?? ''));
        if ($id === '') {
            return null;
        }
        $thumb = '';
        if (!empty($raw['thumbnail'])) {
            $thumb = esc_url_raw((string) $raw['thumbnail']);
        } elseif (!empty($raw['url'])) {
            $thumb = esc_url_raw((string) $raw['url']);
        } elseif (!empty($raw['preview'])) {
            $thumb = esc_url_raw((string) $raw['preview']);
        }
        $out = [
            'gallery_id' => $id,
            'type'       => sanitize_text_field((string) ($raw['type'] ?? 'image')),
            'title'      => sanitize_text_field((string) ($raw['title'] ?? '')),
            'studio'     => sanitize_text_field((string) ($raw['studio'] ?? '')),
            'created_at' => sanitize_text_field((string) ($raw['created_at'] ?? '')),
        ];
        if ($thumb !== '') {
            $out['thumbnail'] = $thumb;
            $out['url']       = $thumb;
        }
        if (!empty($raw['public_safe']) || !empty($raw['is_public'])) {
            $out['public_safe'] = true;
        }
        return $out;
    }

    /**
     * Internal Source Authority summary — never exposed as a user settings UI.
     *
     * @return array<string, mixed>
     */
    private function source_authority_hint(): array {
        $priority = [
            '대한민국 대통령실',
            '정부부처',
            '공공기관',
            '국가기관',
            '그 외',
        ];

        $hints = [];
        if (class_exists('YooY_Source_Authority')) {
            $hints = YooY_Source_Authority::domain_authority_hints();
        }

        return [
            'priority' => $priority,
            'hints'    => $hints,
            'user_ui'  => false,
        ];
    }
}
