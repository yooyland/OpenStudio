<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-heygen-provider.php';

return [
    'id'     => 'heygen',
    'name'   => 'HeyGen',
    'types'  => ['avatar'],
    'status' => YooY_Secrets::has_api_key('yoy_heygen_api_key') ? 'active' : 'pending',
    'mock'   => !YooY_Secrets::has_api_key('yoy_heygen_api_key'),
    'models' => ['heygen-v2', 'heygen-studio'],
];
