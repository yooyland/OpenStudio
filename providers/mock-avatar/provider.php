<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-mock-avatar-provider.php';

return [
    'id'     => 'mock',
    'name'   => 'Mock Avatar',
    'types'  => ['avatar'],
    'status' => 'active',
    'mock'   => true,
    'models' => ['mock-avatar-v1', 'mock-avatar-v2'],
];
