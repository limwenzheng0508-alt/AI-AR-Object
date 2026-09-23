<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <meta name="theme-color" content="#070b12" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <title>AI + AR Object Scanner</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Noto+Sans+SC:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <link rel="stylesheet" href="assets/css/app.css" />
</head>
<body>
  <div class="scan-app" id="app">
    <video id="cameraVideo" playsinline webkit-playsinline muted autoplay></video>
    <img id="freezeFrame" class="freeze-frame hidden" alt="" />
    <canvas id="captureCanvas"></canvas>
    <div id="arLayer" aria-live="polite"></div>

    <div class="boot-gate" id="bootGate">
      <div>
        <h1>AI + AR Scanner</h1>
        <p id="bootMsg">要识别准确：请先到 <a href="settings.php" style="color:#9fd4ff">Settings</a> 填写 Gemini API Key，再开相机扫描。</p>
        <button type="button" class="scan-btn" id="startCamBtn">Allow camera / 允许相机</button>
        <p class="boot-note" id="httpsNote"></p>
        <p style="margin-top:1rem"><a class="ghost-btn" href="settings.php"><i class="fa-solid fa-qrcode"></i> QR / Settings</a></p>
      </div>
    </div>

    <div class="scan-hud">
      <header class="top-bar">
        <div class="brand"><i class="fa-solid fa-cube" aria-hidden="true"></i> AI + AR SCANNER</div>
        <a class="icon-btn" href="settings.php" aria-label="Settings"><i class="fa-solid fa-gear"></i></a>
      </header>
      <div id="aiBanner" class="ai-banner warn">检查 AI 配置中…</div>
      <div id="brandBar" class="brand-bar"></div>

      <div class="viewfinder-wrap">
        <div class="viewfinder" id="viewfinder" aria-hidden="true">
          <span class="c3"></span><span class="c4"></span>
        </div>
      </div>

      <div class="bottom-dock">
        <div class="status-line" id="statusLine" role="status" aria-live="polite">对准物体 / Point at object</div>
        <button type="button" class="scan-btn" id="scanBtn" disabled>SCAN / 扫描</button>
        <section id="resultPanel" class="result-panel hidden" aria-live="polite"></section>
      </div>
    </div>

    <div class="progress-sheet hidden" id="progressSheet" role="status" aria-live="polite">
      <h2 id="progressTitle">识别中 / Identifying</h2>
      <div class="stage-bar"><span id="progressBar"></span></div>
      <p id="progressMsg" class="progress-msg">请稍候，不要离开…</p>
    </div>
  </div>

  <script src="assets/js/camera.js"></script>
  <script src="assets/js/voice.js"></script>
  <script src="assets/js/brand-memory.js"></script>
  <script src="assets/js/ocr-boost.js"></script>
  <script src="assets/js/smart-result.js"></script>
  <script src="assets/js/local-vision.js"></script>
  <script src="assets/js/ar-overlay.js"></script>
  <script src="assets/js/scanner.js"></script>
  <script>
    (function () {
      var note = document.getElementById("httpsNote");
      if (!note) return;
      var ok = window.isSecureContext || location.hostname === "localhost" || location.hostname === "127.0.0.1";
      if (!ok) {
        note.innerHTML = "⚠️ 当前是 HTTP。若开不了相机，请用 HTTPS 链接打开。";
        note.style.color = "#f4c15d";
      }
    })();
  </script>
</body>
</html>
