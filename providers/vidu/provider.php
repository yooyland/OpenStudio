<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-vidu-provider.php';

return [
    'id'     => 'vidu',
    'name'   => 'Vidu',
    'types'  => ['avatar', 'scene'],
    'status' => YooY_Secrets::has_api_key('yoy_vidu_api_key') ? 'active' : 'pending',
    'mock'   => !YooY_Secrets::has_api_key('yoy_vidu_api_key'),
    'models' => ['vidu-avatar', 'vidu-scene'],
];
