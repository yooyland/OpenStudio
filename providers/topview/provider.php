<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-topview-provider.php';

return [
    'id'     => 'topview',
    'name'   => 'Topview',
    'types'  => ['video', 'commercial'],
    'status' => get_option('yoy_topview_api_key') ? 'active' : 'pending',
    'mock'   => !get_option('yoy_topview_api_key'),
    'models' => ['topview-v1', 'topview-ads'],
];
