<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta name="theme-color" content="#070b12" />
  <title>Open phone scan · Settings</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Noto+Sans+SC:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/app.css" />
</head>
<body class="page-shell">
  <div class="page-wrap">
    <nav class="page-nav">
      <a class="ghost-btn" href="index.php"><i class="fa-solid fa-camera"></i> Scanner</a>
      <a class="ghost-btn" href="history.php"><i class="fa-solid fa-clock-rotate-left"></i> History</a>
    </nav>

    <?php
      require_once __DIR__ . '/includes/ScannerUrl.php';
      require_once __DIR__ . '/includes/AppConfig.php';
      AppConfig::ensureFile();
      $phoneUrl = ScannerUrl::forPhone();
      $isHttps = str_starts_with(strtolower($phoneUrl), 'https://');
      $phpOk = PHP_VERSION_ID >= 80000;
      $cfgWritable = AppConfig::isWritable();
      $curlOk = function_exists('curl_init');
    ?>

    <?php if (!$phpOk || !$cfgWritable || !$isHttps): ?>
    <section class="panel" style="border-color:#c9a227;margin-bottom:1rem">
      <h2 style="font-size:1.1rem">cPanel 检查</h2>
      <ul style="margin:0;padding-left:1.2rem;line-height:1.7;color:var(--muted,#9aa4b2)">
        <li>PHP 版本：<?= htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') ?> <?= $phpOk ? '✓' : '✗ 请在 MultiPHP 选 8.2/8.3' ?></li>
        <li>config 可写：<?= $cfgWritable ? '✓' : '✗ 请把 config 文件夹权限设为 755，config.php 设为 644 并可写' ?></li>
        <li>HTTPS：<?= $isHttps ? '✓' : '✗ 请用 https://你的域名/... 打开（并开启 SSL），否则手机无法开相机' ?></li>
        <li>curl：<?= $curlOk ? '✓' : '✗ 请在 Select PHP Extensions 开启 curl' ?></li>
      </ul>
    </section>
    <?php endif; ?>

    <section class="panel qr-panel">
      <h1 class="qr-title">Open phone scan</h1>
      <p class="qr-sub">用手机扫描下方二维码，打开相机识别功能</p>
      <p class="r-warn" style="text-align:left;max-width:36rem;margin:0 auto 1rem">
        <strong>放到 cPanel 后：</strong>必须用 <code>https://你的域名/...</code> 打开本页（不要用 http）。二维码会指向公网地址，手机任意网络都能扫。<br><br>
        <strong>智能识别：</strong>下方填写 Gemini API Key → Save settings。
      </p>
      <div class="qr-box">
        <canvas id="phoneQrCanvas" width="280" height="280" class="qr-image" aria-label="Phone scan QR code"></canvas>
        <img id="phoneQrImg" class="qr-image" width="280" height="280" alt="" hidden />
        <p class="qr-url" id="phoneQrUrl" data-url="<?= htmlspecialchars($phoneUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($phoneUrl, ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($isHttps): ?>
          <p class="qr-sub" style="color:#9fe0c8">✓ HTTPS 已就绪，手机可开相机</p>
        <?php else: ?>
          <p class="qr-sub" style="color:#f0b4b4">⚠ 当前不是 HTTPS。请到 cPanel 为域名开启 SSL，再用 https 打开本页。</p>
        <?php endif; ?>
        <p class="qr-help">若二维码空白：检查本页是否用 https 打开，或点下方「复制链接」手动发给手机。</p>
        <button type="button" class="ghost-btn" id="copyUrlBtn">复制链接</button>
        <a class="ghost-btn" id="openHttpsBtn" href="<?= htmlspecialchars($phoneUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">打开扫描页</a>
      </div>
    </section>

    <section class="panel">
      <h2>AI Configuration</h2>
      <form id="settingsForm" class="form-grid" autocomplete="off">
        <label>
          AI Provider
          <select name="ai_provider" id="ai_provider">
            <option value="auto">Auto (Gemini → Agnes)</option>
            <option value="gemini">Gemini only</option>
            <option value="agnes">Agnes only</option>
          </select>
        </label>

        <label>
          Gemini API Key
          <span class="key-status" id="geminiKeyStatus">Not configured</span>
          <div class="input-group">
            <input class="form-control" type="password" name="gemini_api_key" id="gemini_api_key" placeholder="Leave blank to keep saved key" />
            <button class="btn btn-outline-light" type="button" id="toggleGeminiKey">Show</button>
          </div>
        </label>

        <label>
          Gemini Model
          <input type="text" name="gemini_model" id="gemini_model" placeholder="gemini-2.0-flash" />
        </label>

        <label>
          Gemini Fallback Models
          <textarea name="gemini_fallback_models" id="gemini_fallback_models" rows="3" placeholder="One model per line"></textarea>
        </label>

        <label>
          Agnes API Base URL
          <input type="url" name="agnes_api_base" id="agnes_api_base" placeholder="https://api.example.com/v1" />
        </label>

        <label>
          Agnes API Key
          <span class="key-status" id="agnesKeyStatus">Not configured</span>
          <div class="input-group">
            <input class="form-control" type="password" name="agnes_api_key" id="agnes_api_key" placeholder="Leave blank to keep saved key" />
            <button class="btn btn-outline-light" type="button" id="toggleAgnesKey">Show</button>
          </div>
        </label>

        <label>
          Agnes Model
          <input type="text" name="agnes_model" id="agnes_model" />
        </label>

        <label>
          Agnes Fallback Models
          <textarea name="agnes_fallback_models" id="agnes_fallback_models" rows="3"></textarea>
        </label>

        <label>
          Request Timeout (10–120 seconds)
          <input type="number" name="request_timeout" id="request_timeout" min="10" max="120" />
        </label>

        <label style="display:flex;align-items:center;gap:.5rem;color:var(--text)">
          <input type="checkbox" name="db_enabled" id="db_enabled" /> Enable MySQL scan history
        </label>

        <label style="display:flex;align-items:center;gap:.5rem;color:var(--text)">
          <input type="checkbox" name="web_lookup_enabled" id="web_lookup_enabled" /> Enable optional web product lookup
        </label>

        <div class="form-actions">
          <button class="primary-btn" type="submit" id="saveBtn">Save settings</button>
          <button class="ghost-btn" type="button" id="testBtn">Test Connection</button>
        </div>
        <p id="saveMsg" class="key-status" role="status" aria-live="polite"></p>
        <div id="testResult"></div>
      </form>
    </section>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js"></script>
  <script src="assets/js/settings.js"></script>
</body>
</html>
