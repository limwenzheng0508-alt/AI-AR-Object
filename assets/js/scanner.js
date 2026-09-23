/**
 * Scanner — smart cloud AI when keyed; otherwise category + OCR (honest, no fake intros).
 */
(function () {
  var Cam = window.ScannerCamera;
  var Mem = window.BrandMemory;
  var Smart = window.SmartResult;
  var resultPanel = document.getElementById("resultPanel");
  var freezeEl = document.getElementById("freezeFrame");

  var els = {
    boot: document.getElementById("bootGate"),
    startBtn: document.getElementById("startCamBtn"),
    bootMsg: document.getElementById("bootMsg"),
    video: document.getElementById("cameraVideo"),
    canvas: document.getElementById("captureCanvas"),
    status: document.getElementById("statusLine"),
    scanBtn: document.getElementById("scanBtn"),
    progress: document.getElementById("progressSheet"),
    progressTitle: document.getElementById("progressTitle"),
    progressBar: document.getElementById("progressBar"),
    progressMsg: document.getElementById("progressMsg"),
    aiBanner: document.getElementById("aiBanner"),
    brandBar: document.getElementById("brandBar"),
  };

  var stream = null;
  var busy = false;
  var aiReady = false;

  function cancelResult() {
    if (window.ScannerVoice) window.ScannerVoice.stop();
    overlay.clear();
    hideFreeze();
    hideProgress();
    busy = false;
    els.scanBtn.disabled = false;
    els.scanBtn.textContent = "SCAN / 扫描";
    setStatus("已取消 · 对准新产品后点 SCAN");
  }

  function rescanNew() {
    cancelResult();
    setStatus("重新扫描新产品…");
    setTimeout(function () {
      if (!busy) runScan();
    }, 250);
  }

  var overlay = window.ScannerOverlay.createArOverlay(
    document.getElementById("arLayer"),
    resultPanel,
    { onCancel: cancelResult, onRescan: rescanNew }
  );

  function setStatus(t) {
    if (els.status) els.status.textContent = t;
  }

  function showProgress(pct, msg) {
    if (!els.progress.classList.contains("hidden") && els._lastPct === pct && els._lastMsg === msg) return;
    els._lastPct = pct;
    els._lastMsg = msg;
    els.progress.classList.remove("hidden");
    els.progressTitle.textContent = "识别中";
    els.progressBar.style.width = pct + "%";
    if (els.progressMsg) els.progressMsg.textContent = msg || "";
  }

  function hideProgress() {
    els.progress.classList.add("hidden");
    els.progressBar.style.width = "0%";
    els._lastPct = -1;
    els._lastMsg = "";
  }

  function hideFreeze() {
    if (!freezeEl) return;
    freezeEl.classList.add("hidden");
    freezeEl.removeAttribute("src");
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function renderBrandBar() {
    if (!els.brandBar || !Mem) return;
    var brands = Mem.knownBrands();
    if (!brands.length) {
      els.brandBar.innerHTML = '<span class="brand-empty">智能记忆：识别成功的品牌会自动记住</span>';
      return;
    }
    els.brandBar.innerHTML =
      '<span class="brand-label">已记品牌</span>' +
      brands
        .slice(0, 8)
        .map(function (b) {
          return '<span class="brand-chip">' + escapeHtml(b) + "</span>";
        })
        .join("") +
      '<button type="button" class="brand-clear" id="clearBrandsBtn">清除</button>';
    var btn = document.getElementById("clearBrandsBtn");
    if (btn) {
      btn.onclick = function () {
        Mem.clear();
        renderBrandBar();
      };
    }
  }

  async function refreshAiStatus() {
    try {
      var res = await fetch("api/settings.php", { cache: "no-store" });
      var json = await res.json();
      aiReady = !!(json.ok && json.data && json.data.gemini_key_status === "Key saved");
      if (!aiReady && json.ok && json.data && json.data.agnes_key_status === "Key saved") aiReady = true;
      if (els.aiBanner) {
        if (aiReady) {
          els.aiBanner.textContent = "✓ 智能 AI 已开启（云端识别产品）";
          els.aiBanner.className = "ai-banner ok";
        } else {
          els.aiBanner.innerHTML =
            '⚠️ 未填 Gemini Key：只能读类别+标签文字，无法精准讲产品。<a href="settings.php">立即配置</a>';
          els.aiBanner.className = "ai-banner warn";
        }
      }
    } catch (_) {
      aiReady = false;
    }
  }

  async function bootCamera() {
    if (!els.startBtn || busy) return;
    els.startBtn.disabled = true;
    els.startBtn.textContent = "启动中…";
    try {
      await refreshAiStatus();
      stream = await Cam.startCamera(els.video);
      hideFreeze();
      els.boot.classList.add("hidden");
      renderBrandBar();
      setStatus(aiReady ? "智能模式 · 对准产品点 SCAN" : "基础模式 · 建议先去 Settings 填 Gemini Key");
      els.scanBtn.disabled = false;
      els.scanBtn.textContent = "SCAN / 扫描";
    } catch (err) {
      els.bootMsg.textContent = (err && err.message) || "无法开相机";
      els.startBtn.disabled = false;
      els.startBtn.textContent = "Allow camera / 允许相机";
    }
  }

  async function recognizeCloud(dataUrl) {
    var controller = new AbortController();
    var timer = setTimeout(function () {
      controller.abort();
    }, 55000);
    try {
      var res = await fetch("api/recognize.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          image: dataUrl,
          knownBrands: Mem ? Mem.knownBrands() : [],
          knownProducts: Mem ? Mem.knownProducts() : [],
        }),
        signal: controller.signal,
      });
      var payload = await res.json();
      if (payload && payload.ok && payload.data) {
        return Smart ? Smart.sanitizeCloud(payload.data) : payload.data;
      }
      throw new Error((payload && payload.error) || "AI 识别失败");
    } finally {
      clearTimeout(timer);
    }
  }

  async function recognizeLocalSmart() {
    if (!window.LocalVision) throw new Error("本地识别不可用");
    var coco = await window.LocalVision.recognizeFromCanvas(els.canvas);
    var ocr = { text: "", tokens: [], conf: 0 };
    if (window.OcrBoost) {
      try {
        ocr = await window.OcrBoost.readCanvas(els.canvas);
      } catch (_) {}
    }
    return Smart.fromLocalAndOcr(coco, ocr);
  }

  function pickBetter(a, b) {
    if (!a) return b;
    if (!b) return a;
    var score = function (r) {
      var c = Number(r.confidence) || 0;
      var bonus = 0;
      if (r.manufacturer) bonus += 0.1;
      if (r.productNameZh) bonus += 0.05;
      if (r.descriptionZh) bonus += 0.05;
      if (r.provider !== "local-vision" && r.provider !== "local+ocr") bonus += 0.15;
      return c + bonus;
    };
    return score(a) >= score(b) ? a : b;
  }

  async function runScan() {
    if (busy) return;
    busy = true;
    if (window.ScannerVoice) window.ScannerVoice.stop();
    overlay.clear();
    hideFreeze();
    els.scanBtn.disabled = true;
    els.scanBtn.textContent = "SCANNING…";

    try {
      showProgress(15, "自动对焦…");
      if (Cam.nudgeAutoFocus) await Cam.nudgeAutoFocus(stream);

      showProgress(30, "截取产品画面…");
      var dataUrl = Cam.captureViewfinder(els.video, els.canvas, null, 960, 0.72);

      var result = null;
      var errMsg = "";

      if (aiReady) {
        // Cloud-only smart path when key exists — do NOT fall back to nonsense intros
        showProgress(55, "云端智能识别产品…");
        try {
          result = await recognizeCloud(dataUrl);
        } catch (err) {
          errMsg = (err && err.message) || "";
        }
        if (result && Number(result.confidence) < 0.62) {
          showProgress(75, "二次智能确认…");
          if (Cam.nudgeAutoFocus) await Cam.nudgeAutoFocus(stream);
          await new Promise(function (r) {
            setTimeout(r, 100);
          });
          var dataUrl2 = Cam.captureViewfinder(els.video, els.canvas, null, 1100, 0.78);
          try {
            result = pickBetter(result, await recognizeCloud(dataUrl2));
          } catch (_) {}
        }
        if (!result) {
          // Assist with OCR text note but keep honest
          showProgress(85, "辅助读取标签文字…");
          try {
            var assist = await recognizeLocalSmart();
            if (assist && assist.manufacturer) {
              // Keep assist only if cloud totally failed
              result = assist;
              errMsg = "";
            }
          } catch (_) {}
        }
        if (!result) {
          throw new Error(
            errMsg ||
              "AI 无法确认此产品。请把品牌/型号字对准取景框中央再试。"
          );
        }
      } else {
        // No API key: category + OCR only (honest)
        showProgress(50, "识别类别…");
        showProgress(70, "读取标签文字…");
        result = await recognizeLocalSmart();
      }

      if (!result) throw new Error("识别失败，请再试");

      if (Mem && result.provider !== "local-vision" && result.provider !== "local+ocr") {
        Mem.remember(result);
        renderBrandBar();
      } else if (Mem && result.manufacturer) {
        Mem.remember(result);
        renderBrandBar();
      }

      showProgress(100, "完成");
      overlay.show(result);
      setStatus(
        (result.provider === "local+ocr" || result.provider === "local-vision"
          ? "基础识别： "
          : "智能识别： ") +
          (result.productNameZh || result.productName || "")
      );
      hideProgress();
    } catch (err) {
      hideProgress();
      setStatus((err && err.message) || "识别失败");
    } finally {
      busy = false;
      els.scanBtn.disabled = false;
      els.scanBtn.textContent = "SCAN / 扫描";
    }
  }

  if (els.startBtn) {
    els.startBtn.onclick = function (e) {
      e.preventDefault();
      bootCamera();
    };
  }
  if (els.scanBtn) {
    els.scanBtn.onclick = function (e) {
      e.preventDefault();
      runScan();
    };
  }

  refreshAiStatus();
  renderBrandBar();
  window.addEventListener("pagehide", function () {
    Cam.stopCamera(stream);
    if (window.ScannerVoice) window.ScannerVoice.stop();
  });
})();
