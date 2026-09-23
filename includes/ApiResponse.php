<?php

declare(strict_types=1);

/**
 * Shared JSON helpers for API endpoints.
 */
final class ApiResponse
{
    public static function send(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function ok(mixed $data = null, int $status = 200): void
    {
        $out = ['ok' => true];
        if ($data !== null) {
            $out['data'] = $data;
        }
        self::send($out, $status);
    }

    public static function fail(string $error, int $status = 400): void
    {
        self::send(['ok' => false, 'error' => $error], $status);
    }

    public static function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
