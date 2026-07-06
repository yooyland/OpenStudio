<?php
if (!defined('ABSPATH')) exit;

final class YooY_Image_API_Router {

    /** @var array<string, YooY_Image_Provider_Interface> */
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
                'types'  => method_exists($provider, 'types') ? $provider->types() : ['image'],
            ];
        }
        return $list;
    }

    public function generate(array $params): array {
        $provider = $this->resolve($params['provider'] ?? 'mock');
        $params['job_id'] = $params['job_id'] ?? ('img_' . wp_generate_uuid4());
        $raw = $provider->generate($params);
        $normalized = YooY_Job_Normalizer::normalize($raw, 'image');
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
        if (!isset($this->providers[$id])) {
            if (!isset($this->providers['mock'])) {
                throw new Exception('Image provider not available.');
            }
            return $this->providers['mock'];
        }
        return $this->providers[$id];
    }

    private function boot_providers(): void {
        $map = [
            'mock'      => ['file' => 'mock-image/class-mock-image-provider.php', 'class' => 'YooY_Mock_Image_Provider'],
            'openai'    => ['file' => 'openai-image/class-openai-image-provider.php', 'class' => 'YooY_OpenAI_Image_Provider'],
            'replicate' => ['file' => 'replicate-image/class-replicate-image-provider.php', 'class' => 'YooY_Replicate_Image_Provider'],
            'topview'   => ['file' => 'topview-image/class-topview-image-provider.php', 'class' => 'YooY_Topview_Image_Provider'],
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
}
