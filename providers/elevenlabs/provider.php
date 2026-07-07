<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-elevenlabs-provider.php';

return [
    'id'     => 'elevenlabs',
    'name'   => 'ElevenLabs',
    'types'  => ['voice', 'tts'],
    'status' => YooY_Secrets::has_api_key('yoy_elevenlabs_api_key') ? 'active' : 'pending',
    'mock'   => !YooY_Secrets::has_api_key('yoy_elevenlabs_api_key'),
    'models' => ['eleven_multilingual_v2', 'eleven_turbo_v2_5', 'eleven_flash_v2_5'],
];
