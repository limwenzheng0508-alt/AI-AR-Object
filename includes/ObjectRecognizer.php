<?php

declare(strict_types=1);

require_once __DIR__ . '/AppConfig.php';
require_once __DIR__ . '/GeminiVision.php';
require_once __DIR__ . '/AgnesVision.php';
require_once __DIR__ . '/ProductResult.php';
require_once __DIR__ . '/ScanHistory.php';

/**
 * Orchestrates provider selection, fallback, optional web lookup, history.
 */
final class ObjectRecognizer
{
    /**
     * @param list<string> $knownBrands
     * @param list<array<string,string>> $knownProducts
     * @return array{ok:bool,data?:array,error?:string,stage?:string}
     */
    public function recognize(string $imageBinary, string $mime, array $knownBrands = [], array $knownProducts = []): array
    {
        $timeout = (int) AppConfig::get('request_timeout', 45);
        $timeout = max(10, min(120, $timeout));
        $mode = strtolower((string) AppConfig::get('ai_provider', 'auto'));

        $gemini = new GeminiVision();
        $agnes = new AgnesVision();

        $errors = [];
        $result = null;

        if ($mode === 'gemini') {
            try {
                $result = $gemini->recognize($imageBinary, $mime, $timeout, $knownBrands, $knownProducts);
            } catch (Throwable $e) {
                return ['ok' => false, 'error' => $e->getMessage(), 'stage' => 'ai'];
            }
        } elseif ($mode === 'agnes') {
            try {
                $result = $agnes->recognize($imageBinary, $mime, $timeout, $knownBrands, $knownProducts);
            } catch (Throwable $e) {
                return ['ok' => false, 'error' => $e->getMessage(), 'stage' => 'ai'];
            }
        } else {
            try {
                $result = $gemini->recognize($imageBinary, $mime, $timeout, $knownBrands, $knownProducts);
            } catch (Throwable $e) {
                $errors[] = 'Gemini: ' . $e->getMessage();
            }
            if ($result === null || !$result->isComplete()) {
                try {
                    $result = $agnes->recognize($imageBinary, $mime, $timeout, $knownBrands, $knownProducts);
                } catch (Throwable $e) {
                    $errors[] = 'Agnes: ' . $e->getMessage();
                }
            }
        }

        if ($result === null || !$result->isComplete()) {
            $msg = 'Unable to identify the object.';
            if ($errors !== []) {
                $msg .= ' ' . implode(' | ', array_map(static fn ($s) => mb_substr($s, 0, 120), $errors));
            }
            if (trim((string) AppConfig::get('gemini_api_key', '')) === ''
                && trim((string) AppConfig::get('agnes_api_key', '')) === '') {
                $msg = 'AI provider is not configured.';
            }
            return ['ok' => false, 'error' => $msg, 'stage' => 'ai'];
        }

        // Optional conservative enrichment
        if ((bool) AppConfig::get('web_lookup_enabled', false)) {
            $result = $this->maybeEnrich($result);
        }

        try {
            ScanHistory::insert($result);
        } catch (Throwable $e) {
            // History is optional — ignore DB issues
        }

        return ['ok' => true, 'data' => $result->toArray()];
    }

    /**
     * Conservative enrichment: only accept more specific name if it contains
     * the same manufacturer / core tokens from the AI result.
     */
    private function maybeEnrich(ProductResult $result): ProductResult
    {
        $query = trim($result->manufacturer . ' ' . $result->productName);
        if ($query === '' || strlen($query) < 3) {
            return $result;
        }

        // Lightweight DuckDuckGo-style instant answer is unreliable for products;
        // keep a stub that never invents models — only trims/normalizes.
        // Real enrichment can be plugged here later without changing API shape.
        return $result;
    }
}
