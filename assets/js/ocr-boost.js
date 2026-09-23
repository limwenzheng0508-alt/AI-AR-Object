/**
 * On-device OCR — read brand/model text from the capture (no invented names).
 */
window.OcrBoost = (function () {
  var ready = null;

  function loadTesseract() {
    if (window.Tesseract) return Promise.resolve();
    return new Promise(function (resolve, reject) {
      var s = document.createElement("script");
      s.src = "https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js";
      s.async = true;
      s.onload = resolve;
      s.onerror = reject;
      document.head.appendChild(s);
    });
  }

  function ensure() {
    if (!ready) {
      ready = loadTesseract().catch(function () {
        ready = null;
        throw new Error("OCR unavailable");
      });
    }
    return ready;
  }

  function cleanText(raw) {
    return String(raw || "")
      .replace(/[^\w\u4e00-\u9fff\s\-+./&%]/gi, " ")
      .replace(/\s+/g, " ")
      .trim();
  }

  function extractTokens(text) {
    var t = cleanText(text);
    if (!t) return [];
    var parts = t.split(" ").filter(function (w) {
      return w.length >= 2 && !/^(the|and|for|with|from|this|that|size|ml|oz)$/i.test(w);
    });
    // Prefer ALLCAPS / mixed brand-like tokens
    parts.sort(function (a, b) {
      var score = function (w) {
        var s = 0;
        if (/[A-Z]{2,}/.test(w)) s += 3;
        if (/[A-Za-z]/.test(w) && /\d/.test(w)) s += 2;
        if (/[\u4e00-\u9fff]/.test(w)) s += 2;
        s += Math.min(3, w.length / 4);
        return s;
      };
      return score(b) - score(a);
    });
    return parts.slice(0, 8);
  }

  async function readCanvas(canvasEl) {
    await ensure();
    var result = await window.Tesseract.recognize(canvasEl, "eng", {
      logger: function () {},
    });
    var text = cleanText(result && result.data && result.data.text);
    var tokens = extractTokens(text);
    return {
      text: text,
      tokens: tokens,
      conf: result && result.data ? Number(result.data.confidence) || 0 : 0,
    };
  }

  return { readCanvas: readCanvas, ensure: ensure };
})();
