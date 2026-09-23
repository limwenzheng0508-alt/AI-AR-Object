<?php

declare(strict_types=1);

require_once __DIR__ . '/AIProvider.php';
require_once __DIR__ . '/AppConfig.php';
require_once __DIR__ . '/ProductResult.php';
require_once __DIR__ . '/VisionPrompt.php';

/**
 * Agnes Vision via OpenAI-compatible Chat Completions API.
 * Configure base URL + key in settings.
 */
final class AgnesVision implements AIProvider
{
    public function name(): string
    {
        return 'agnes';
    }

    public function recognize(
        string $imageBinary,
        string $mime,
        int $timeoutSeconds,
        array $knownBrands = [],
        array $knownProducts = []
    ): ProductResult {
        $key = trim((string) AppConfig::get('agnes_api_key', ''));
        $base = rtrim((string) AppConfig::get('agnes_api_base', ''), '/');
        if ($key === '' || $base === '') {
            throw new RuntimeException('Agnes API is not configured.');
        }

        $models = array_values(array_unique(array_filter(array_merge(
            [(string) AppConfig::get('agnes_model', 'agnes-vision')],
            AppConfig::parseModelList((string) AppConfig::get('agnes_fallback_models', ''))
        ))));

        $lastError = 'Agnes request failed.';
        foreach ($models as $model) {
            try {
                $text = $this->callModel($base, $key, $model, $imageBinary, $mime, $timeoutSeconds, $knownBrands, $knownProducts);
                $result = $this->parseModelJson($text, 'agnes');
                if ($result->isComplete()) {
                    return $result;
                }
                $lastError = 'Agnes returned incomplete result.';
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        throw new RuntimeException($lastError);
    }

    public function testConnection(int $timeoutSeconds): array
    {
        $key = trim((string) AppConfig::get('agnes_api_key', ''));
        $base = rtrim((string) AppConfig::get('agnes_api_base', ''), '/');
        if ($key === '' || $base === '') {
            return ['ok' => false, 'message' => 'Not configured'];
        }

        $url = $base . '/models';
        $resp = $this->httpJson('GET', $url, null, $key, $timeoutSeconds);
        if (($resp['http'] ?? 0) >= 200 && ($resp['http'] ?? 0) < 300) {
            return ['ok' => true, 'message' => 'Connected'];
        }

        // Some hosts may not expose /models — try a tiny chat ping without image
        return ['ok' => false, 'message' => $this->safeError($resp)];
    }

    private function callModel(
        string $base,
        string $key,
        string $model,
        string $imageBinary,
        string $mime,
        int $timeoutSeconds,
        array $knownBrands = [],
        array $knownProducts = []
    ): string {
        $url = $base . '/chat/completions';
        $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($imageBinary);

        $payload = [
            'model' => $model,
            'temperature' => 0.1,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => VisionPrompt::systemInstruction(),
                ],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => VisionPrompt::userText($knownBrands, $knownProducts)],
                        [
                            'type' => 'image_url',
                            'image_url' => ['url' => $dataUrl],
                        ],
                    ],
                ],
            ],
            'response_format' => ['type' => 'json_object'],
        ];

        $resp = $this->httpJson('POST', $url, $payload, $key, $timeoutSeconds);
        if (($resp['http'] ?? 0) < 200 || ($resp['http'] ?? 0) >= 300) {
            throw new RuntimeException($this->safeError($resp));
        }

        $text = $resp['json']['choices'][0]['message']['content'] ?? '';
        if (!is_string($text) || trim($text) === '') {
            throw new RuntimeException('Empty Agnes response.');
        }
        return $text;
    }

    private function parseModelJson(string $text, string $provider): ProductResult
    {
        $clean = trim($text);
        if (preg_match('/\{.*\}/s', $clean, $m)) {
            $clean = $m[0];
        }
        $data = json_decode($clean, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON from Agnes.');
        }
        return ProductResult::fromArray($data, $provider);
    }

    /**
     * @param array<string,mixed>|null $payload
     * @return array{http:int,json:?array,raw:string}
     */
    private function httpJson(string $method, string $url, ?array $payload, string $apiKey, int $timeout): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to start HTTP client.');
        }

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Network error contacting Agnes.');
        }

        $json = json_decode($raw, true);
        return [
            'http' => $http,
            'json' => is_array($json) ? $json : null,
            'raw' => $raw,
        ];
    }

    /** @param array<string,mixed> $resp */
    private function safeError(array $resp): string
    {
        $msg = $resp['json']['error']['message'] ?? ($resp['json']['message'] ?? null);
        if (is_string($msg) && $msg !== '') {
            return mb_substr($msg, 0, 180);
        }
        $http = (int) ($resp['http'] ?? 0);
        return $http > 0 ? "HTTP {$http}" : 'Connection failed';
    }
}
