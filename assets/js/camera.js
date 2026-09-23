/**
 * Camera — continuous autofocus/exposure; silent center capture (no zoom UI).
 */
window.ScannerCamera = (function () {
  function isLocalHost(hostname) {
    return hostname === "localhost" || hostname === "127.0.0.1" || hostname === "[::1]" || hostname === "::1";
  }

  function canUseCamera() {
    if (window.isSecureContext) return true;
    if (isLocalHost(location.hostname)) return true;
    return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
  }

  function getMedia(constraints) {
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
      return navigator.mediaDevices.getUserMedia(constraints);
    }
    var legacy = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia;
    if (!legacy) return Promise.reject(new Error("Camera API missing"));
    return new Promise(function (resolve, reject) {
      legacy.call(navigator, constraints, resolve, reject);
    });
  }

  async function applyAutoAdjust(track) {
    if (!track || !track.getCapabilities) return;
    try {
      var caps = track.getCapabilities() || {};
      var advanced = [];
      if (caps.focusMode && caps.focusMode.indexOf("continuous") !== -1) {
        advanced.push({ focusMode: "continuous" });
      }
      if (caps.exposureMode && caps.exposureMode.indexOf("continuous") !== -1) {
        advanced.push({ exposureMode: "continuous" });
      }
      if (caps.whiteBalanceMode && caps.whiteBalanceMode.indexOf("continuous") !== -1) {
        advanced.push({ whiteBalanceMode: "continuous" });
      }
      var constr = {};
      if (advanced.length) constr.advanced = advanced;
      // Some browsers use top-level
      if (caps.focusMode && caps.focusMode.indexOf("continuous") !== -1) constr.focusMode = "continuous";
      if (Object.keys(constr).length) {
        await track.applyConstraints(constr);
      }
    } catch (_) {
      /* not supported on all phones */
    }
  }

  async function startCamera(videoEl) {
    if (!canUseCamera()) {
      throw new Error("需要 HTTPS 才能开相机。请用 Settings 的 HTTPS 链接。");
    }
    var attempts = [
      {
        audio: false,
        video: {
          facingMode: { ideal: "environment" },
          width: { ideal: 1280 },
          height: { ideal: 720 },
          frameRate: { ideal: 24, max: 30 },
        },
      },
      { audio: false, video: { facingMode: { ideal: "environment" } } },
      { audio: false, video: true },
    ];
    var stream = null;
    var lastErr = null;
    for (var i = 0; i < attempts.length; i++) {
      try {
        stream = await getMedia(attempts[i]);
        break;
      } catch (err) {
        lastErr = err;
      }
    }
    if (!stream) {
      var n = lastErr && lastErr.name;
      if (n === "NotAllowedError" || n === "PermissionDeniedError") throw new Error("请允许相机权限");
      throw new Error((lastErr && lastErr.message) || "无法开启相机");
    }

    var track = stream.getVideoTracks()[0];
    await applyAutoAdjust(track);

    videoEl.setAttribute("playsinline", "true");
    videoEl.setAttribute("webkit-playsinline", "true");
    videoEl.playsInline = true;
    videoEl.muted = true;
    videoEl.autoplay = true;
    videoEl.srcObject = stream;
    try {
      await videoEl.play();
    } catch (_) {
      await new Promise(function (r) {
        videoEl.onloadedmetadata = function () {
          videoEl.play().then(r).catch(r);
        };
        setTimeout(r, 600);
      });
    }
    return stream;
  }

  function stopCamera(stream) {
    if (!stream) return;
    stream.getTracks().forEach(function (t) {
      t.stop();
    });
  }

  /**
   * Capture for AI only — does NOT change on-screen preview (no zoom effect).
   * Uses a mild center region so framing stays natural.
   */
  function captureViewfinder(videoEl, canvasEl, _vf, maxSide, quality) {
    maxSide = maxSide || 960;
    if (quality == null) quality = 0.72;
    var vw = videoEl.videoWidth;
    var vh = videoEl.videoHeight;
    if (!vw || !vh) throw new Error("画面未就绪，请再点一次 SCAN");

    var side = Math.min(vw, vh) * 0.9;
    var sx = (vw - side) / 2;
    var sy = (vh - side) / 2;

    var out = Math.min(maxSide, Math.round(side));
    out = Math.max(480, Math.min(1100, out));
    canvasEl.width = out;
    canvasEl.height = out;
    var ctx = canvasEl.getContext("2d", { alpha: false, willReadFrequently: false });
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = "medium";
    ctx.drawImage(videoEl, sx, sy, side, side, 0, 0, out, out);
    return canvasEl.toDataURL("image/jpeg", quality);
  }

  function captureFrame(videoEl, canvasEl, maxSide, quality) {
    return captureViewfinder(videoEl, canvasEl, null, maxSide, quality);
  }

  /** Re-apply autofocus before each scan */
  async function nudgeAutoFocus(stream) {
    if (!stream) return;
    var track = stream.getVideoTracks()[0];
    await applyAutoAdjust(track);
    // brief settle time for focus motors
    await new Promise(function (r) {
      setTimeout(r, 80);
    });
  }

  return {
    startCamera: startCamera,
    stopCamera: stopCamera,
    captureFrame: captureFrame,
    captureViewfinder: captureViewfinder,
    nudgeAutoFocus: nudgeAutoFocus,
  };
})();
