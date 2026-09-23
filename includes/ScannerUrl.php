<?php

declare(strict_types=1);

/**
 * Phone QR / scanner URL builder.
 * On cPanel HTTPS: always use the live domain (any network works).
 */
final class ScannerUrl
{
    public static function publicUrlFile(): string
    {
        return dirname(__DIR__) . '/config/public-url.txt';
    }

    public static function savedPublicBase(): string
    {
        $file = self::publicUrlFile();
        if (!is_file($file)) {
            return '';
        }
        $url = trim((string) file_get_contents($file));
        // Strip UTF-8 BOM if present
        $url = preg_replace('/^\xEF\xBB\xBF/', '', $url) ?? $url;
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return '';
        }
        return rtrim($url, '/') . '/';
    }

    public static function projectBasePath(): string
    {
        $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        if (str_ends_with($script, '/api')) {
            $script = dirname($script);
        }
        if ($script === '/' || $script === '\\' || $script === '.') {
            return '/';
        }
        return rtrim($script, '/') . '/';
    }

    public static function detectRequestBase(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443)
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host . self::projectBasePath();
    }

    public static function isLocalHost(?string $host = null): bool
    {
        $host = strtolower((string) ($host ?? ($_SERVER['HTTP_HOST'] ?? '')));
        $host = preg_replace('/:\d+$/', '', $host) ?: '';
        return $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || str_starts_with($host, '192.168.')
            || str_starts_with($host, '10.');
    }

    /**
     * Best URL for phone QR codes.
     */
    public static function forPhone(): string
    {
        $request = self::detectRequestBase();
        $https = str_starts_with(strtolower($request), 'https://');

        // Production / cPanel HTTPS: trust current domain (works on any WiFi)
        if ($https && !self::isLocalHost()) {
            return rtrim($request, '/') . '/index.php';
        }

        // Local XAMPP: prefer saved Cloudflare tunnel URL if present
        $saved = self::savedPublicBase();
        if ($saved !== '') {
            return rtrim($saved, '/') . '/index.php';
        }

        // Fallback: current host (may need same WiFi)
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $hostname = preg_replace('/:\d+$/', '', $host) ?: 'localhost';
        $port = '';
        if (preg_match('/:(\d+)$/', $host, $m)) {
            $port = ':' . $m[1];
        }
        $scheme = str_starts_with(strtolower($request), 'https://') ? 'https' : 'http';

        if ($hostname === 'localhost' || $hostname === '127.0.0.1' || $hostname === '::1') {
            $lan = self::lanIp();
            if ($lan !== '') {
                $hostname = $lan;
            }
        }

        return $scheme . '://' . $hostname . $port . self::projectBasePath() . 'index.php';
    }

    private static function lanIp(): string
    {
        $candidates = [];
        $hostname = @gethostname();
        if (is_string($hostname) && $hostname !== '') {
            $resolved = @gethostbyname($hostname);
            if (is_string($resolved) && self::isPrivateIp($resolved)) {
                $candidates[] = $resolved;
            }
        }
        if (function_exists('net_get_interfaces')) {
            $ifs = @net_get_interfaces();
            if (is_array($ifs)) {
                foreach ($ifs as $info) {
                    foreach (($info['unicast'] ?? []) as $addr) {
                        $ip = $addr['address'] ?? '';
                        if (is_string($ip) && self::isPrivateIp($ip)) {
                            $candidates[] = $ip;
                        }
                    }
                }
            }
        }
        foreach ($candidates as $ip) {
            if (str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
                return $ip;
            }
        }
        return $candidates[0] ?? '';
    }

    private static function isPrivateIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        if ($ip === '127.0.0.1') {
            return false;
        }
        return (bool) preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[0-1])\.)/', $ip);
    }
}
