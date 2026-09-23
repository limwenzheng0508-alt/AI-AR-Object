<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/ApiResponse.php';
require_once dirname(__DIR__) . '/includes/AppConfig.php';
require_once dirname(__DIR__) . '/includes/GeminiVision.php';
require_once dirname(__DIR__) . '/includes/AgnesVision.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    ApiResponse::ok(AppConfig::publicView());
}

if ($method === 'POST') {
    $body = ApiResponse::readJsonBody();
    $action = (string) ($body['action'] ?? 'save');

    if ($action === 'test') {
        $timeout = (int) AppConfig::get('request_timeout', 45);
        $timeout = max(10, min(30, $timeout));
        $gemini = new GeminiVision();
        $agnes = new AgnesVision();
        ApiResponse::ok([
            'gemini' => $gemini->testConnection($timeout),
            'agnes' => $agnes->testConnection($timeout),
        ]);
    }

    if ($action === 'save') {
        $ok = AppConfig::save([
            'ai_provider' => $body['ai_provider'] ?? 'auto',
            'gemini_api_key' => $body['gemini_api_key'] ?? '',
            'gemini_model' => $body['gemini_model'] ?? '',
            'gemini_fallback_models' => $body['gemini_fallback_models'] ?? '',
            'agnes_api_key' => $body['agnes_api_key'] ?? '',
            'agnes_api_base' => $body['agnes_api_base'] ?? '',
            'agnes_model' => $body['agnes_model'] ?? '',
            'agnes_fallback_models' => $body['agnes_fallback_models'] ?? '',
            'request_timeout' => $body['request_timeout'] ?? 45,
            'db_enabled' => !empty($body['db_enabled']),
            'web_lookup_enabled' => !empty($body['web_lookup_enabled']),
        ]);

        if (!$ok) {
            ApiResponse::fail('Unable to save settings.', 500);
        }
        ApiResponse::ok(AppConfig::publicView());
    }

    ApiResponse::fail('Unknown action.', 400);
}

ApiResponse::fail('Method not allowed.', 405);
