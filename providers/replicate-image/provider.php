<?php
if (!defined('ABSPATH')) exit;

return [
    'id'     => 'replicate',
    'name'   => 'Replicate',
    'types'  => ['image'],
    'status' => 'active',
    'mock'   => false,
    'models' => ['flux-schnell', 'flux-dev', 'sdxl'],
];
