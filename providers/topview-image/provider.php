<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-topview-image-provider.php';

return [
    'id'     => 'topview',
    'name'   => 'Topview Image',
    'types'  => ['image', 'commercial'],
    'status' => YooY_Secrets::has_api_key('yoy_topview_api_key') ? 'active' : 'pending',
    'mock'   => !YooY_Secrets::has_api_key('yoy_topview_api_key'),
    'models' => ['topview-product', 'topview-banner'],
];
