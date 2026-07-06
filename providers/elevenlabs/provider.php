<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-elevenlabs-provider.php';

return [
    'id'     => 'elevenlabs',
    'name'   => 'ElevenLabs',
    'types'  => ['voice', 'tts'],
    'status' => get_option('yoy_elevenlabs_api_key') ? 'active' : 'pending',
    'mock'   => !get_option('yoy_elevenlabs_api_key'),
    'models' => ['eleven_multilingual_v2', 'eleven_turbo_v2_5', 'eleven_flash_v2_5'],
];
