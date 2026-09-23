/**
 * Result panel — bilingual; voice on press; cancel / rescan actions.
 */
window.ScannerOverlay = (function () {
  function createArOverlay(layerEl, panelEl, hooks) {
    hooks = hooks || {};
    var speakingBtn = null;

    function clear() {
      if (window.ScannerVoice) window.ScannerVoice.stop();
      speakingBtn = null;
      layerEl.classList.remove("active");
      layerEl.innerHTML = "";
      if (panelEl) {
        panelEl.classList.add("hidden");
        panelEl.innerHTML = "";
      }
    }

    function toCssBox(box) {
      box = box || {};
      var ymin = Number(box.ymin) / 10;
      var xmin = Number(box.xmin) / 10;
      var ymax = Number(box.ymax) / 10;
      var xmax = Number(box.xmax) / 10;
      return {
        top: Math.max(2, ymin) + "%",
        left: Math.max(2, xmin) + "%",
        height: Math.max(8, ymax - ymin) + "%",
        width: Math.max(8, xmax - xmin) + "%",
      };
    }

    function clean(v, fallback) {
      v = (v == null ? "" : String(v)).trim();
      if (!v || v === "—") return fallback || "";
      return v;
    }

    function buildSpeech(r, lang) {
      if (lang === "zh") {
        return (
          [
            "产品：" + (r.productNameZh || r.productName),
            r.manufacturer ? "厂商：" + r.manufacturer : "",
            r.specificationZh || r.specification || "",
            r.descriptionZh || r.description || "",
          ]
            .filter(Boolean)
            .join("。") + "。"
        );
      }
      return (
        [
          "Product: " + (r.productName || ""),
          r.manufacturer ? "Brand: " + r.manufacturer : "",
          r.specification ? "Spec: " + r.specification : "",
          r.description || "",
        ]
          .filter(Boolean)
          .join(". ") + "."
      );
    }

    function resetVoiceButtons() {
      if (!panelEl) return;
      panelEl.querySelectorAll(".voice-btn").forEach(function (b) {
        b.classList.remove("speaking");
        if (b.getAttribute("data-act") === "en") b.textContent = "🔊 讲英文";
        if (b.getAttribute("data-act") === "zh") b.textContent = "🔊 讲华文";
        if (b.getAttribute("data-act") === "stop") b.classList.add("hidden");
      });
    }

    function show(result) {
      clear();
      result = result || {};
      layerEl.classList.add("active");

      var boxEl = document.createElement("div");
      boxEl.className = "bbox";
      Object.assign(boxEl.style, toCssBox(result.boundingBox));
      layerEl.appendChild(boxEl);

      var nameEn = clean(result.productName, clean(result.objectLabel, "Object"));
      var nameZh = clean(result.productNameZh, clean(result.objectLabelZh, nameEn));
      var descEn = clean(result.description, clean(result.specification, ""));
      var descZh = clean(result.descriptionZh, clean(result.specificationZh, ""));
      var specEn = clean(result.specification, "");
      var specZh = clean(result.specificationZh, "");
      var mfr = clean(result.manufacturer, "—");
      var conf = Math.round((Number(result.confidence) || 0) * 100);
      var provider = clean(result.provider, "—");
      var isLocal = provider === "local-vision" || provider === "local+ocr";
      var lowConf = conf > 0 && conf < 70;

      if (!descZh)
        descZh = isLocal
          ? "未能生成详细华文介绍。请配置 Gemini，或把标签文字对准镜头。"
          : "（暂无华文介绍）";
      if (!descEn)
        descEn = isLocal
          ? "No detailed intro. Configure Gemini or aim at the product label."
          : "(No English description)";

      var speechData = {
        productName: nameEn,
        productNameZh: nameZh,
        manufacturer: mfr === "—" ? "" : mfr,
        specification: specEn,
        specificationZh: specZh,
        description: descEn,
        descriptionZh: descZh,
      };

      if (!panelEl) return;
      panelEl.classList.remove("hidden");
      panelEl.innerHTML =
        (isLocal
          ? '<p class="r-warn">⚠️ 基础模式（类别 + 标签文字）。要精准讲出产品，请到 Settings 填写 Gemini API Key。</p>'
          : '<p class="r-ok">✓ 智能 AI 产品识别</p>') +
        (lowConf
          ? '<p class="r-warn">置信度偏低（' +
            conf +
            '%）。可点「取消」换角度，或「重新扫描」再试一次。</p>'
          : "") +
        '<div class="lang-block">' +
        '<p class="r-label">English</p>' +
        '<h2 class="r-en"></h2>' +
        '<p class="r-desc-en"></p>' +
        "</div>" +
        '<div class="lang-block">' +
        '<p class="r-label">华文</p>' +
        '<h2 class="r-zh-title"></h2>' +
        '<p class="r-desc-zh"></p>' +
        "</div>" +
        '<div class="r-meta">' +
        '<span class="r-conf"></span>' +
        '<span data-m="mfr"></span>' +
        '<span data-m="prov"></span>' +
        "</div>" +
        '<p class="r-voice-hint">语音需手动按：</p>' +
        '<div class="voice-row">' +
        '<button type="button" class="voice-btn" data-act="en">🔊 讲英文</button>' +
        '<button type="button" class="voice-btn" data-act="zh">🔊 讲华文</button>' +
        '<button type="button" class="voice-btn hidden" data-act="stop">⏹ 停止</button>' +
        "</div>" +
        '<div class="action-row">' +
        '<button type="button" class="act-btn cancel" data-act="cancel">取消结果</button>' +
        '<button type="button" class="act-btn rescan" data-act="rescan">重新扫描新产品</button>' +
        "</div>";

      panelEl.querySelector(".r-en").textContent = nameEn;
      panelEl.querySelector(".r-zh-title").textContent = nameZh;
      panelEl.querySelector(".r-desc-en").textContent = descEn;
      panelEl.querySelector(".r-desc-zh").textContent = descZh;
      panelEl.querySelector(".r-conf").textContent = conf + "%";
      panelEl.querySelector('[data-m="mfr"]').textContent = "Brand: " + mfr;
      panelEl.querySelector('[data-m="prov"]').textContent = "AI: " + provider;

      panelEl.querySelectorAll(".voice-btn").forEach(function (btn) {
        btn.addEventListener("click", function (e) {
          e.preventDefault();
          e.stopPropagation();
          var act = btn.getAttribute("data-act");
          var voice = window.ScannerVoice;
          if (!voice) return;
          if (act === "stop" || (voice.speaking && speakingBtn === act)) {
            voice.stop();
            resetVoiceButtons();
            speakingBtn = null;
            return;
          }
          voice.stop();
          speakingBtn = act;
          resetVoiceButtons();
          btn.classList.add("speaking");
          var stop = panelEl.querySelector('[data-act="stop"]');
          if (stop) stop.classList.remove("hidden");
          var text = buildSpeech(speechData, act === "zh" ? "zh" : "en");
          voice.speak(text, act === "zh" ? "zh-CN" : "en-US").then(function () {
            resetVoiceButtons();
            speakingBtn = null;
          });
        });
      });

      panelEl.querySelectorAll(".act-btn").forEach(function (btn) {
        btn.addEventListener("click", function (e) {
          e.preventDefault();
          e.stopPropagation();
          var act = btn.getAttribute("data-act");
          if (act === "cancel" && typeof hooks.onCancel === "function") hooks.onCancel();
          if (act === "rescan" && typeof hooks.onRescan === "function") hooks.onRescan();
        });
      });
    }

    return { show: show, clear: clear };
  }

  return { createArOverlay: createArOverlay };
})();
