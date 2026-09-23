<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/ScannerUrl.php';

$url = isset($_GET['u']) ? (string) $_GET['u'] : '';
if ($url === '' || !preg_match('#^https?://#i', $url)) {
    $url = ScannerUrl::forPhone();
}
// hard length guard
$url = substr($url, 0, 512);

$remote = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&ecc=M&margin=8&data='
    . rawurlencode($url);

$png = false;
if (function_exists('curl_init')) {
    $ch = curl_init($remote);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'AI-AR-Scanner/1.0',
    ]);
    $png = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300 || $png === false || $png === '') {
        $png = false;
    }
}

if ($png !== false) {
    header('Content-Type: image/png');
    header('Cache-Control: no-store');
    echo $png;
    exit;
}

// Offline fallback: SVG placeholder with URL text (still shows something)
require_once dirname(__DIR__) . '/includes/SimpleQr.php';
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: no-store');
echo SimpleQr::svg($url, 280, 2);
