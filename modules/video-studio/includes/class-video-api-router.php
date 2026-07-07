<?php
if (!defined('ABSPATH')) exit;

final class YooY_Video_API_Router {

    /** @var array<string, YooY_Video_Provider_Interface> */
    private array $providers = [];

    public function __construct() {
        $this->boot_providers();
    }

    public function providers(): array {
        if (class_exists('YooY_Provider_Catalog')) {
            return YooY_Provider_Catalog::list_for_studio('video', $this->providers);
        }

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
        $provider    = $this->resolve($provider_id);
        if (!$provider) {
            throw new Exception('Video provider not available.');
        }

        $params['job_id'] = $params['job_id'] ?? ('vid_' . wp_generate_uuid4());
        $raw = $provider->generate($params);
        $normalized = YooY_Job_Normalizer::normalize($raw, 'video');
        return apply_filters('yoy_video_studio_generate', $normalized, $params, $provider_id);
    }

    public function status(string $provider_id, string $job_id): array {
        $provider = $this->resolve($provider_id);
        if (!$provider) {
            return YooY_Job_Normalizer::normalize([
                'job_id' => $job_id,
                'status' => YooY_Job_Status::FAILED,
                'error'  => 'Provider not found',
            ], 'video');
        }
        return YooY_Job_Normalizer::normalize($provider->status($job_id), 'video');
    }

    private function resolve(string $id): ?YooY_Video_Provider_Interface {
        $route_id = class_exists('YooY_Provider_Catalog')
            ? YooY_Provider_Catalog::route_id($id)
            : $id;

        if (isset($this->providers[$id])) {
            return $this->providers[$id];
        }
        if (isset($this->providers[$route_id])) {
            return $this->providers[$route_id];
        }
        return $this->providers['mock'] ?? null;
    }

    private function boot_providers(): void {
        require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-provider-bootstrap.php';
        if (class_exists('YooY_Provider_Bootstrap')) {
            YooY_Provider_Bootstrap::register_video($this->providers);
        }
    }
}
