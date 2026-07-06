<?php
if (!defined('ABSPATH')) exit;

final class YooY_Avatar_API_Router {

    /** @var array<string, YooY_Avatar_Provider_Interface> */
    private array $providers = [];

    public function __construct() {
        $map = [
            'mock'   => ['file' => 'mock-avatar/class-mock-avatar-provider.php', 'class' => 'YooY_Mock_Avatar_Provider'],
            'heygen' => ['file' => 'heygen/class-heygen-provider.php', 'class' => 'YooY_HeyGen_Provider'],
            'vidu'   => ['file' => 'vidu/class-vidu-provider.php', 'class' => 'YooY_Vidu_Provider'],
        ];
        foreach ($map as $id => $info) {
            $file = YOY_AI_STUDIO_PROVIDERS_DIR . $info['file'];
            if (!file_exists($file)) continue;
            require_once $file;
            if (class_exists($info['class'])) $this->providers[$id] = new $info['class']();
        }
    }

    public function providers(): array {
        $list = [];
        foreach ($this->providers as $p) {
            $list[] = ['id' => $p->id(), 'name' => $p->name(), 'models' => $p->models()];
        }
        return $list;
    }

    public function generate(array $params): array {
        $id = $params['provider'] ?? 'mock';
        $provider = $this->providers[$id] ?? $this->providers['mock'] ?? null;
        if (!$provider) throw new Exception('Avatar provider not available.');
        $params['job_id'] = $params['job_id'] ?? ('avt_' . wp_generate_uuid4());
        return apply_filters('yoy_avatar_studio_generate', $provider->generate($params), $params);
    }

    public function status(string $provider_id, string $job_id): array {
        $provider = $this->providers[$provider_id] ?? $this->providers['mock'];
        return $provider->status($job_id);
    }
}
