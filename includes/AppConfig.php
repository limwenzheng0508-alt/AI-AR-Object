<?php

declare(strict_types=1);

/**
 * Loads and persists application configuration safely.
 */
final class AppConfig
{
    private static ?array $cache = null;

    public static function path(): string
    {
        return dirname(__DIR__) . '/config/config.php';
    }

    public static function examplePath(): string
    {
        return dirname(__DIR__) . '/config/config.example.php';
    }

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = self::path();
        if (!is_file($path)) {
            $example = self::examplePath();
            self::$cache = is_file($example) ? (require $example) : [];
            return self::$cache;
        }

        $data = require $path;
        self::$cache = is_array($data) ? $data : [];
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * Public-safe settings (no secrets).
     */
    public static function publicView(): array
    {
        $all = self::all();
        return [
            'ai_provider' => (string) ($all['ai_provider'] ?? 'auto'),
            'gemini_model' => (string) ($all['gemini_model'] ?? ''),
            'gemini_fallback_models' => (string) ($all['gemini_fallback_models'] ?? ''),
            'agnes_model' => (string) ($all['agnes_model'] ?? ''),
            'agnes_fallback_models' => (string) ($all['agnes_fallback_models'] ?? ''),
            'agnes_api_base' => (string) ($all['agnes_api_base'] ?? ''),
            'request_timeout' => (int) ($all['request_timeout'] ?? 45),
            'db_enabled' => (bool) ($all['db_enabled'] ?? false),
            'web_lookup_enabled' => (bool) ($all['web_lookup_enabled'] ?? false),
            'gemini_key_status' => self::keyStatus((string) ($all['gemini_api_key'] ?? '')),
            'agnes_key_status' => self::keyStatus((string) ($all['agnes_api_key'] ?? '')),
        ];
    }

    public static function keyStatus(string $key): string
    {
        return trim($key) !== '' ? 'Key saved' : 'Not configured';
    }

    /**
     * Merge and save settings. Empty password fields keep existing keys.
     *
     * @param array<string,mixed> $input
     */
    public static function save(array $input): bool
    {
        $current = self::all();
        $allowed = [
            'ai_provider',
            'gemini_api_key',
            'gemini_model',
            'gemini_fallback_models',
            'agnes_api_key',
            'agnes_api_base',
            'agnes_model',
            'agnes_fallback_models',
            'request_timeout',
            'db_enabled',
            'db_host',
            'db_name',
            'db_user',
            'db_pass',
            'web_lookup_enabled',
        ];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            // Keep existing API keys when blank submitted
            if (($key === 'gemini_api_key' || $key === 'agnes_api_key' || $key === 'db_pass')
                && trim((string) $input[$key]) === '') {
                continue;
            }
            $current[$key] = $input[$key];
        }

        $provider = strtolower((string) ($current['ai_provider'] ?? 'auto'));
        if (!in_array($provider, ['auto', 'gemini', 'agnes'], true)) {
            $provider = 'auto';
        }
        $current['ai_provider'] = $provider;

        $timeout = (int) ($current['request_timeout'] ?? 45);
        $current['request_timeout'] = max(10, min(120, $timeout));

        $export = var_export($current, true);
        $php = "<?php\n\ndeclare(strict_types=1);\n\nreturn {$export};\n";
        $ok = (bool) file_put_contents(self::path(), $php, LOCK_EX);
        if ($ok) {
            self::$cache = $current;
        }
        return $ok;
    }

    public static function parseModelList(string $raw): array
    {
        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return array_values(array_unique($out));
    }
}
