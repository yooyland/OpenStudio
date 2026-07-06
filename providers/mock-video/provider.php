<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-mock-video-provider.php';

return [
    'id'     => 'mock',
    'name'   => 'Mock Video',
    'types'  => ['video'],
    'status' => 'active',
    'mock'   => true,
    'models' => ['mock-v1'],
];
