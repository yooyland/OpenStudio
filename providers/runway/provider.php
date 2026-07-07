<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-runway-provider.php';

return [
    'id'     => 'runway',
    'name'   => 'Runway',
    'types'  => ['video'],
    'status' => YooY_Secrets::has_api_key('yoy_runway_api_key') ? 'active' : 'pending',
    'mock'   => !YooY_Secrets::has_api_key('yoy_runway_api_key'),
    'models' => ['gen-3-alpha', 'gen-4-turbo'],
];
