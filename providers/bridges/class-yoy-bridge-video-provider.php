<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-video-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'mock-video/class-mock-video-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-provider-guard.php';

final class YooY_Bridge_Video_Provider implements YooY_Video_Provider_Interface {

    private string $catalog_id;
    private string $label;
    private string $api_key;
    private string $default_model;

    public function __construct(string $catalog_id, array $def) {
        $this->catalog_id    = $catalog_id;
        $this->label         = (string) ($def['name'] ?? $catalog_id);
        $option              = (string) ($def['option'] ?? '');
        $this->api_key       = $option !== '' ? YooY_Secrets::get_api_key($option) : '';
        $this->default_model = $catalog_id . '-v1';
    }

    public function id(): string { return $this->catalog_id; }
    public function name(): string { return $this->label; }

    public function models(): array {
        return [
            ['id' => $this->default_model, 'name' => $this->label, 'max_duration' => 10, 'resolutions' => ['720p', '1080p']],
        ];
    }

    public function generate(array $params): array {
        YooY_Provider_Guard::require_key($this->label, $this->api_key, $params);
        return (new YooY_Mock_Video_Provider())->generate(array_merge($params, [
            'provider' => $this->catalog_id,
            'model'    => $params['model'] ?? $this->default_model,
        ]));
    }

    public function status(string $job_id): array {
        return (new YooY_Mock_Video_Provider())->status($job_id);
    }
}
