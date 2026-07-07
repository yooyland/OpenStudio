<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-topview-provider.php';

return [
    'id'     => 'topview',
    'name'   => 'Topview',
    'types'  => ['video', 'commercial'],
    'status' => YooY_Secrets::has_api_key('yoy_topview_api_key') ? 'active' : 'pending',
    'mock'   => !YooY_Secrets::has_api_key('yoy_topview_api_key'),
    'models' => ['topview-v1', 'topview-ads'],
];
