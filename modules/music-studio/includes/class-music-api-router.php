<?php
if (!defined('ABSPATH')) exit;

final class YooY_Music_API_Router {

    /** @var array<string, YooY_Music_Provider_Interface> */
    private array $providers = [];

    public function __construct() {
        $map = [
            'mock' => ['file' => 'mock-music/class-mock-music-provider.php', 'class' => 'YooY_Mock_Music_Provider'],
            'suno' => ['file' => 'suno/class-suno-provider.php', 'class' => 'YooY_Suno_Provider'],
        ];
        foreach ($map as $id => $info) {
            $file = YOY_AI_STUDIO_PROVIDERS_DIR . $info['file'];
            if (!file_exists($file)) continue;
            require_once $file;
            if (class_exists($info['class'])) {
                $this->providers[$id] = new $info['class']();
            }
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
        if (!$provider) throw new Exception('Music provider not available.');
        $params['job_id'] = $params['job_id'] ?? ('mus_' . wp_generate_uuid4());
        $raw = $provider->generate($params);
        return apply_filters('yoy_music_studio_generate', YooY_Job_Normalizer::normalize($raw, 'music'), $params);
    }

    public function status(string $provider_id, string $job_id): array {
        $provider = $this->providers[$provider_id] ?? $this->providers['mock'];
        return YooY_Job_Normalizer::normalize($provider->status($job_id), 'music');
    }
}
