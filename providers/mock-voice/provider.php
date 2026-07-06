<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-mock-voice-provider.php';

return [
    'id'     => 'mock',
    'name'   => 'Mock Voice',
    'types'  => ['voice', 'tts'],
    'status' => 'active',
    'mock'   => true,
    'models' => ['mock-tts-v1', 'mock-tts-v2'],
];
