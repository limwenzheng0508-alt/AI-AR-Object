<!DOCTYPE html>
<html lang="en">
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

    <section class="panel qr-panel">
      <h1 class="qr-title">Open phone scan</h1>
      <p class="qr-sub">用手机扫描下方二维码，打开相机识别功能</p>
      <p class="r-warn" style="text-align:left;max-width:36rem;margin:0 auto 1rem">
        <strong>放到 cPanel 后：</strong>用 <code>https://你的域名/...</code> 打开本页，二维码会自动变成公网 HTTPS，手机任意网络都能扫（不需要隧道）。<br><br>
        <strong>本机测试跨 WiFi：</strong>双击 <code>开启跨网络HTTPS.bat</code> 后刷新本页。<br><br>
        <strong>智能识别：</strong>下方填写 Gemini API Key → Save settings。详见 <code>CPANEL安装说明.md</code>。
      </p>
      <div class="qr-box">
        <?php
          require_once __DIR__ . '/includes/ScannerUrl.php';
          $phoneUrl = ScannerUrl::forPhone();
          $qrSrc = 'api/qr.php?u=' . rawurlencode($phoneUrl) . '&t=' . time();
          $isHttps = str_starts_with(strtolower($phoneUrl), 'https://');
        ?>
        <img
          id="phoneQrImg"
          class="qr-image"
          src="<?= htmlspecialchars($qrSrc, ENT_QUOTES, 'UTF-8') ?>"
          width="280"
          height="280"
          alt="Phone scan QR code"
        />
        <p class="qr-url" id="phoneQrUrl"><?= htmlspecialchars($phoneUrl, ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($isHttps): ?>
          <p class="qr-sub" style="color:#9fe0c8">✓ HTTPS 已就绪，手机可开相机</p>
        <?php endif; ?>
        <p class="qr-help">已启用 HTTPS 公网链接时，手机任意网络都能扫并开相机。隧道窗口勿关。</p>
        <button type="button" class="ghost-btn" id="copyUrlBtn">复制链接</button>
        <a class="ghost-btn" id="openHttpsBtn" href="#" target="_blank" rel="noopener">用 HTTPS 打开扫描页</a>
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

  <script src="assets/js/settings.js"></script>
</body>
</html>
