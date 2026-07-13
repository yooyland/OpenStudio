<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/class-project-store.php';
require_once __DIR__ . '/includes/class-projects-rest.php';
require_once __DIR__ . '/class-yoy-module-projects.php';

return new YooY_Module_Projects();
