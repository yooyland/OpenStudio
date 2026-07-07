<?php
if (!defined('ABSPATH')) exit;

final class YooY_Voice_API_Router {

    /** @var array<string, YooY_Voice_Provider_Interface> */
    private array $providers = [];

    public function __construct() {
        $this->boot_providers();
    }

    public function providers(): array {
        if (class_exists('YooY_Provider_Catalog')) {
            return YooY_Provider_Catalog::list_for_studio('voice', $this->providers);
        }

        $list = [];
        foreach ($this->providers as $p) {
            $list[] = ['id' => $p->id(), 'name' => $p->name(), 'models' => $p->models()];
        }
        return $list;
    }

    public function speak(array $params): array {
        $provider = $this->resolve($params['provider'] ?? 'mock');
        if (!$provider) {
            throw new Exception('Voice provider not available.');
        }
        $params['job_id'] = $params['job_id'] ?? ('vce_' . wp_generate_uuid4());
        return apply_filters('yoy_voice_studio_speak', $provider->speak($params), $params);
    }

    public function clone_voice(array $params): array {
        $provider = $this->resolve($params['provider'] ?? 'mock');
        if (!$provider) {
            throw new Exception('Voice provider not available.');
        }
        return apply_filters('yoy_voice_studio_clone', $provider->clone_voice($params), $params);
    }

    public function status(string $provider_id, string $job_id): array {
        $provider = $this->resolve($provider_id);
        if (!$provider) {
            throw new Exception('Voice provider not available.');
        }
        return $provider->status($job_id);
    }

    private function resolve(string $id): ?YooY_Voice_Provider_Interface {
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
            YooY_Provider_Bootstrap::register_voice($this->providers);
        }
    }
}
