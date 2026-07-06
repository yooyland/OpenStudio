<?php
if (!defined('ABSPATH')) exit;

final class YooY_Video_API_Router {

    /** @var array<string, YooY_Video_Provider_Interface> */
    private array $providers = [];

    public function __construct() {
        $this->boot_providers();
    }

    public function providers(): array {
        $list = [];
        foreach ($this->providers as $provider) {
            $list[] = [
                'id'     => $provider->id(),
                'name'   => $provider->name(),
                'models' => $provider->models(),
            ];
        }
        return $list;
    }

    public function route(array $params): array {
        $provider_id = sanitize_text_field($params['provider'] ?? 'mock');
        $provider    = $this->providers[$provider_id] ?? $this->providers['mock'] ?? null;

        if (!$provider) {
            throw new Exception('Video provider not available.');
        }

        $params['job_id'] = $params['job_id'] ?? ('vid_' . wp_generate_uuid4());

        return apply_filters('yoy_video_studio_generate', $provider->generate($params), $params, $provider_id);
    }

    public function status(string $provider_id, string $job_id): array {
        $provider = $this->providers[$provider_id] ?? $this->providers['mock'] ?? null;
        if (!$provider) {
            return ['job_id' => $job_id, 'status' => 'error', 'error' => 'Provider not found'];
        }
        return $provider->status($job_id);
    }

    private function boot_providers(): void {
        $map = [
            'mock'    => YOY_AI_STUDIO_PROVIDERS_DIR . 'mock-video/class-mock-video-provider.php',
            'runway'  => YOY_AI_STUDIO_PROVIDERS_DIR . 'runway/class-runway-provider.php',
            'topview' => YOY_AI_STUDIO_PROVIDERS_DIR . 'topview/class-topview-provider.php',
        ];

        foreach ($map as $id => $file) {
            if (!file_exists($file)) continue;
            require_once $file;
            $class = match ($id) {
                'mock'    => 'YooY_Mock_Video_Provider',
                'runway'  => 'YooY_Runway_Provider',
                'topview' => 'YooY_Topview_Provider',
                default   => null,
            };
            if ($class && class_exists($class)) {
                $this->providers[$id] = new $class();
            }
        }
    }
}
