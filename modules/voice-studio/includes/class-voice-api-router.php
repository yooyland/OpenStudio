<?php
if (!defined('ABSPATH')) exit;

final class YooY_Voice_API_Router {

    /** @var array<string, YooY_Voice_Provider_Interface> */
    private array $providers = [];

    public function __construct() {
        $map = [
            'mock'        => ['file' => 'mock-voice/class-mock-voice-provider.php', 'class' => 'YooY_Mock_Voice_Provider'],
            'elevenlabs'  => ['file' => 'elevenlabs/class-elevenlabs-provider.php', 'class' => 'YooY_ElevenLabs_Provider'],
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

    public function speak(array $params): array {
        $provider = $this->providers[$params['provider'] ?? 'mock'] ?? $this->providers['mock'];
        $params['job_id'] = $params['job_id'] ?? ('vce_' . wp_generate_uuid4());
        return apply_filters('yoy_voice_studio_speak', $provider->speak($params), $params);
    }

    public function clone_voice(array $params): array {
        $provider = $this->providers[$params['provider'] ?? 'mock'] ?? $this->providers['mock'];
        return apply_filters('yoy_voice_studio_clone', $provider->clone_voice($params), $params);
    }

    public function status(string $provider_id, string $job_id): array {
        $provider = $this->providers[$provider_id] ?? $this->providers['mock'];
        return $provider->status($job_id);
    }
}
