<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/ScannerUrl.php';

$url = isset($_GET['u']) ? (string) $_GET['u'] : '';
if ($url === '' || !preg_match('#^https?://#i', $url)) {
    $url = ScannerUrl::forPhone();
}
$url = substr($url, 0, 512);

// Prefer local GD QR (no external network) — works on cPanel
$lib = dirname(__DIR__) . '/includes/vendor/php-qrcode.php';
if (is_file($lib) && function_exists('imagecreatetruecolor') && function_exists('imagepng')) {
    require_once $lib;
    try {
        header('Cache-Control: no-store, no-cache, must-revalidate');
        $generator = new QRCode($url, [
            's' => 'qrl',
            'w' => 280,
            'h' => 280,
            'sf' => 6,
            'wq' => 1,
            'wm' => 1,
            'bc' => 'FFFFFF',
            'fc' => '111111',
        ]);
        $generator->output_image();
        exit;
    } catch (Throwable $e) {
        // fall through
    }
}

// Last resort: SVG placeholder (still a scannable-looking block + clear URL)
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: no-store');
$safe = htmlspecialchars($url, ENT_QUOTES | ENT_XML1, 'UTF-8');
echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="280" height="280" viewBox="0 0 280 280">
  <rect width="280" height="280" fill="#fff"/>
  <rect x="20" y="20" width="60" height="60" fill="#111"/>
  <rect x="30" y="30" width="40" height="40" fill="#fff"/>
  <rect x="40" y="40" width="20" height="20" fill="#111"/>
  <rect x="200" y="20" width="60" height="60" fill="#111"/>
  <rect x="210" y="30" width="40" height="40" fill="#fff"/>
  <rect x="220" y="40" width="20" height="20" fill="#111"/>
  <rect x="20" y="200" width="60" height="60" fill="#111"/>
  <rect x="30" y="210" width="40" height="40" fill="#fff"/>
  <rect x="40" y="220" width="20" height="20" fill="#111"/>
  <text x="140" y="150" text-anchor="middle" font-size="12" fill="#333">QR unavailable</text>
  <text x="140" y="170" text-anchor="middle" font-size="9" fill="#666">Enable PHP GD</text>
</svg>
SVG;
