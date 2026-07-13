<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/class-assistant-context-engine.php';
require_once __DIR__ . '/includes/class-assistant-recommendation-engine.php';
require_once __DIR__ . '/includes/class-assistant-prompt-composer.php';
require_once __DIR__ . '/includes/class-assistant-conversation-engine.php';
require_once __DIR__ . '/includes/class-assistant-service.php';
require_once __DIR__ . '/class-yoy-module-ai-assistant.php';

return new YooY_Module_AI_Assistant();
