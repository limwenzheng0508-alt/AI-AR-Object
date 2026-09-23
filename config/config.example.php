<?php

declare(strict_types=1);

/**
 * Copy to config.php on the server if missing, then fill keys in Settings page.
 */
return [
    'ai_provider' => 'auto',

    'gemini_api_key' => '',
    'gemini_model' => 'gemini-1.5-flash',
    'gemini_fallback_models' => "gemini-2.0-flash\ngemini-1.5-pro\ngemini-1.5-flash-8b",

    'agnes_api_key' => '',
    'agnes_api_base' => 'https://api.agnes-ai.com/v1',
    'agnes_model' => 'agnes-vision',
    'agnes_fallback_models' => '',

    'request_timeout' => 60,

    'db_enabled' => false,
    'db_host' => '127.0.0.1',
    'db_name' => 'ai_ar_scanner',
    'db_user' => 'root',
    'db_pass' => '',
    'db_charset' => 'utf8mb4',

    'web_lookup_enabled' => false,
];
