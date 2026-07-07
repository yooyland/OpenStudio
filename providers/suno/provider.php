<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-suno-provider.php';

return [
    'id'     => 'suno',
    'name'   => 'Suno',
    'types'  => ['music'],
    'status' => YooY_Secrets::has_api_key('yoy_suno_api_key') ? 'active' : 'pending',
    'mock'   => !YooY_Secrets::has_api_key('yoy_suno_api_key'),
    'models' => ['chirp-v3-5', 'chirp-v4', 'chirp-v4-5'],
];
