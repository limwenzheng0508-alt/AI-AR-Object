<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/ApiResponse.php';
require_once dirname(__DIR__) . '/includes/ScanHistory.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        if (!ScanHistory::enabled()) {
            ApiResponse::ok([
                'enabled' => false,
                'items' => [],
                'total' => 0,
                'page' => 1,
                'perPage' => 20,
            ]);
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id > 0) {
            $row = ScanHistory::find($id);
            if (!$row) {
                ApiResponse::fail('Record not found.', 404);
            }
            ApiResponse::ok($row);
        }

        $search = trim((string) ($_GET['q'] ?? ''));
        $page = (int) ($_GET['page'] ?? 1);
        $data = ScanHistory::list($search, $page, 20);
        $data['enabled'] = true;
        ApiResponse::ok($data);
    }

    if ($method === 'POST') {
        $body = ApiResponse::readJsonBody();
        if (($body['action'] ?? '') === 'clear') {
            if (!ScanHistory::enabled()) {
                ApiResponse::fail('Database history is disabled.', 400);
            }
            ScanHistory::clear();
            ApiResponse::ok(['cleared' => true]);
        }
        ApiResponse::fail('Unknown action.', 400);
    }
} catch (Throwable $e) {
    error_log('history.php: ' . $e->getMessage());
    ApiResponse::fail('Database error. Check MySQL settings.', 500);
}

ApiResponse::fail('Method not allowed.', 405);
