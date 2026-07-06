<?php
if (!defined('ABSPATH')) exit;

final class YooY_Voice_Clone {

    private YooY_Voice_API_Router $router;
    private YooY_Voice_Catalog $catalog;

    public function __construct(YooY_Voice_API_Router $router, YooY_Voice_Catalog $catalog) {
        $this->router  = $router;
        $this->catalog = $catalog;
    }

    public function clone(int $user_id, array $params): array {
        $name = sanitize_text_field($params['clone_name'] ?? '');
        if ($name === '') throw new Exception('Clone name is required.');

        if (empty($params['sample_base64']) && empty($params['sample_url'])) {
            throw new Exception('Voice sample is required for cloning.');
        }

        $result = $this->router->clone_voice([
            'provider'          => sanitize_text_field($params['provider'] ?? 'mock'),
            'clone_name'        => $name,
            'clone_description' => sanitize_textarea_field($params['clone_description'] ?? ''),
            'sample_base64'     => $params['sample_base64'] ?? '',
            'sample_url'        => esc_url_raw($params['sample_url'] ?? ''),
        ]);

        $this->catalog->add_cloned_voice($user_id, [
            'id'      => $result['voice_id'],
            'name'    => $name,
            'language'=> $params['language'] ?? 'ko',
            'gender'  => $params['gender'] ?? 'unknown',
            'preview' => 'Cloned voice',
        ]);

        return $result;
    }
}
