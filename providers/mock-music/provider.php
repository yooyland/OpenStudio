<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-mock-music-provider.php';

return [
    'id'     => 'mock',
    'name'   => 'Mock Music',
    'types'  => ['music'],
    'status' => 'active',
    'mock'   => true,
    'models' => ['mock-music-v1', 'mock-music-v2'],
];
