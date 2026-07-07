<?php
if (!defined('ABSPATH')) exit;

final class YooY_Image_API_Router {

    /** @var array<string, YooY_Image_Provider_Interface> */
    private array $providers = [];

    public function __construct() {
        $this->boot_providers();
    }

    public function providers(): array {
        if (class_exists('YooY_Provider_Catalog')) {
            return YooY_Provider_Catalog::list_for_studio('image', $this->providers);
        }

        $list = [];
        foreach ($this->providers as $provider) {
            $list[] = [
                'id'     => $provider->id(),
                'name'   => $provider->name(),
                'models' => $provider->models(),
                'types'  => method_exists($provider, 'types') ? $provider->types() : ['image'],
            ];
        }
        return $list;
    }

    public function generate(array $params): array {
        $provider = $this->resolve($params['provider'] ?? 'mock');
        $params['job_id'] = $params['job_id'] ?? ('img_' . wp_generate_uuid4());
        $raw = $provider->generate($params);
        if (isset($raw['type'], $raw['job_id'])) {
            $normalized = $raw;
        } else {
            $normalized = YooY_Job_Normalizer::normalize($raw, 'image');
        }
        return apply_filters('yoy_image_studio_generate', $normalized, $params);
    }

    public function edit(array $params): array {
        $provider = $this->resolve($params['provider'] ?? 'mock');
        $params['job_id'] = $params['job_id'] ?? ('imgedit_' . wp_generate_uuid4());
        $raw = $provider->edit($params);
        return YooY_Job_Normalizer::normalize($raw, 'image');
    }

    public function status(string $provider_id, string $job_id): array {
        $provider = $this->resolve($provider_id);
        $raw = $provider->status($job_id);
        return YooY_Job_Normalizer::normalize($raw, 'image');
    }

    private function resolve(string $id): YooY_Image_Provider_Interface {
        $route_id = class_exists('YooY_Provider_Catalog')
            ? YooY_Provider_Catalog::route_id($id)
            : $id;
        if ($route_id === 'flux') {
            $route_id = 'replicate';
        }

        if (isset($this->providers[$id])) {
            return $this->providers[$id];
        }
        if (isset($this->providers[$route_id])) {
            return $this->providers[$route_id];
        }
        if (!isset($this->providers['mock'])) {
            throw new Exception('Image provider not available.');
        }
        return $this->providers['mock'];
    }

    private function boot_providers(): void {
        require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-provider-bootstrap.php';
        if (class_exists('YooY_Provider_Bootstrap')) {
            YooY_Provider_Bootstrap::register_image($this->providers);
        }
    }
}
