<?php

declare(strict_types=1);

require_once __DIR__ . '/AIProvider.php';
require_once __DIR__ . '/AppConfig.php';
require_once __DIR__ . '/ProductResult.php';
require_once __DIR__ . '/VisionPrompt.php';

/**
 * Google Gemini Vision (Generative Language API).
 */
final class GeminiVision implements AIProvider
{
    public function name(): string
    {
        return 'gemini';
    }

    public function recognize(
        string $imageBinary,
        string $mime,
        int $timeoutSeconds,
        array $knownBrands = [],
        array $knownProducts = []
    ): ProductResult {
        $key = trim((string) AppConfig::get('gemini_api_key', ''));
        if ($key === '') {
            throw new RuntimeException('Gemini API key is not configured.');
        }

        $models = array_values(array_unique(array_filter(array_merge(
            [(string) AppConfig::get('gemini_model', 'gemini-1.5-flash')],
            AppConfig::parseModelList((string) AppConfig::get('gemini_fallback_models', ''))
        ))));

        $lastError = 'Gemini request failed.';
        foreach ($models as $model) {
            try {
                $json = $this->callModel($model, $key, $imageBinary, $mime, $timeoutSeconds, $knownBrands, $knownProducts);
                $result = $this->parseModelJson($json, 'gemini');
                if ($result->isComplete()) {
                    return $result;
                }
                $lastError = 'Gemini returned incomplete result.';
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        throw new RuntimeException($lastError);
    }

    public function testConnection(int $timeoutSeconds): array
    {
        $key = trim((string) AppConfig::get('gemini_api_key', ''));
        if ($key === '') {
            return ['ok' => false, 'message' => 'Not configured'];
        }

        $model = (string) AppConfig::get('gemini_model', 'gemini-2.0-flash');
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($model)
            . '?key=' . rawurlencode($key);

        $resp = $this->httpJson('GET', $url, null, $timeoutSeconds);
        if (($resp['http'] ?? 0) >= 200 && ($resp['http'] ?? 0) < 300) {
            return ['ok' => true, 'message' => 'Connected'];
        }

        return ['ok' => false, 'message' => $this->safeError($resp)];
    }

    private function callModel(
        string $model,
        string $key,
        string $imageBinary,
        string $mime,
        int $timeoutSeconds,
        array $knownBrands = [],
        array $knownProducts = []
    ): string {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($model)
            . ':generateContent?key=' . rawurlencode($key);

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => VisionPrompt::systemInstruction()]],
            ],
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    ['text' => VisionPrompt::userText($knownBrands, $knownProducts)],
                    [
                        'inline_data' => [
                            'mime_type' => $mime,
                            'data' => base64_encode($imageBinary),
                        ],
                    ],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
                'topP' => 0.8,
                'responseMimeType' => 'application/json',
            ],
        ];

        // Prefer camelCase for newer Gemini APIs; keep snake_case fallback fields too
        $payload['contents'][0]['parts'][1] = [
            'inline_data' => [
                'mime_type' => $mime,
                'data' => base64_encode($imageBinary),
            ],
        ];

        $resp = $this->httpJson('POST', $url, $payload, $timeoutSeconds);
        if (($resp['http'] ?? 0) < 200 || ($resp['http'] ?? 0) >= 300) {
            // Retry with camelCase inlineData
            $payload['contents'][0]['parts'][1] = [
                'inlineData' => [
                    'mimeType' => $mime,
                    'data' => base64_encode($imageBinary),
                ],
            ];
            $resp = $this->httpJson('POST', $url, $payload, $timeoutSeconds);
        }
        if (($resp['http'] ?? 0) < 200 || ($resp['http'] ?? 0) >= 300) {
            throw new RuntimeException($this->safeError($resp));
        }

        $body = $resp['json'] ?? [];
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if (!is_string($text) || trim($text) === '') {
            throw new RuntimeException('Empty Gemini response.');
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
            throw new RuntimeException('Invalid JSON from Gemini.');
        }
        return ProductResult::fromArray($data, $provider);
    }

    /**
     * @param array<string,mixed>|null $payload
     * @return array{http:int,json:?array,raw:string}
     */
    private function httpJson(string $method, string $url, ?array $payload, int $timeout): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to start HTTP client.');
        }

        $headers = ['Accept: application/json'];
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
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Network error contacting Gemini.');
        }

        $json = json_decode($raw, true);
        return [
            'http' => $http,
            'json' => is_array($json) ? $json : null,
            'raw' => $raw,
            'curl_error' => $err,
        ];
    }

    /** @param array<string,mixed> $resp */
    private function safeError(array $resp): string
    {
        $msg = $resp['json']['error']['message'] ?? null;
        if (is_string($msg) && $msg !== '') {
            // Strip any accidental key fragments
            $msg = preg_replace('/key=[A-Za-z0-9_-]+/i', 'key=***', $msg) ?? $msg;
            return mb_substr($msg, 0, 180);
        }
        $http = (int) ($resp['http'] ?? 0);
        return $http > 0 ? "HTTP {$http}" : 'Connection failed';
    }
}
