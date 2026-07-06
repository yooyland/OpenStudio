<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-mock-image-provider.php';

return [
    'id'     => 'mock',
    'name'   => 'Mock Image',
    'types'  => ['image'],
    'status' => 'active',
    'mock'   => true,
    'models' => ['mock-image-v1'],
];
