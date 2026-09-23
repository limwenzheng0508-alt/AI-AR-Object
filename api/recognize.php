<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/ApiResponse.php';
require_once dirname(__DIR__) . '/includes/ImageValidator.php';
require_once dirname(__DIR__) . '/includes/ObjectRecognizer.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ApiResponse::fail('Method not allowed.', 405);
}

$body = ApiResponse::readJsonBody();
$image = (string) ($body['image'] ?? '');

$knownBrands = [];
if (isset($body['knownBrands']) && is_array($body['knownBrands'])) {
    foreach ($body['knownBrands'] as $b) {
        if (is_string($b) && trim($b) !== '') {
            $knownBrands[] = mb_substr(trim($b), 0, 80);
        }
    }
    $knownBrands = array_values(array_unique($knownBrands));
    $knownBrands = array_slice($knownBrands, 0, 20);
}

$knownProducts = [];
if (isset($body['knownProducts']) && is_array($body['knownProducts'])) {
    foreach (array_slice($body['knownProducts'], 0, 15) as $p) {
        if (!is_array($p)) {
            continue;
        }
        $knownProducts[] = [
            'brand' => mb_substr(trim((string) ($p['brand'] ?? '')), 0, 80),
            'name' => mb_substr(trim((string) ($p['name'] ?? '')), 0, 120),
            'nameZh' => mb_substr(trim((string) ($p['nameZh'] ?? '')), 0, 120),
        ];
    }
}

$validated = ImageValidator::fromPayload($image);
if (!$validated['ok']) {
    ApiResponse::fail((string) ($validated['error'] ?? 'Invalid image.'), 422);
}

try {
    $recognizer = new ObjectRecognizer();
    $result = $recognizer->recognize(
        (string) $validated['binary'],
        (string) $validated['mime'],
        $knownBrands,
        $knownProducts
    );

    if (!$result['ok']) {
        ApiResponse::fail((string) ($result['error'] ?? 'Unable to identify the object.'), 422);
    }

    ApiResponse::ok($result['data']);
} catch (Throwable $e) {
    error_log('recognize.php: ' . $e->getMessage());
    ApiResponse::fail('Server error while identifying the object.', 500);
}
