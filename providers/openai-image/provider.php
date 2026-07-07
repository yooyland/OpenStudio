<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-openai-image-provider.php';

return [
    'id'     => 'openai',
    'name'   => 'GPT Image',
    'types'  => ['image'],
    'status' => YooY_Secrets::has_api_key('yoy_openai_api_key') ? 'active' : 'pending',
    'mock'   => !YooY_Secrets::has_api_key('yoy_openai_api_key'),
    'models' => ['dall-e-3', 'gpt-image-1'],
];
