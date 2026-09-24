async function loadSettings() {
  const res = await fetch("api/settings.php", { cache: "no-store" });
  const json = await res.json();
  if (!json.ok) throw new Error(json.error || "Failed to load settings");
  return json.data;
}

function fillForm(data) {
  const f = document.getElementById("settingsForm");
  f.ai_provider.value = data.ai_provider || "auto";
  f.gemini_model.value = data.gemini_model || "";
  f.gemini_fallback_models.value = data.gemini_fallback_models || "";
  f.agnes_api_base.value = data.agnes_api_base || "";
  f.agnes_model.value = data.agnes_model || "";
  f.agnes_fallback_models.value = data.agnes_fallback_models || "";
  f.request_timeout.value = data.request_timeout || 45;
  f.db_enabled.checked = !!data.db_enabled;
  f.web_lookup_enabled.checked = !!data.web_lookup_enabled;
  document.getElementById("geminiKeyStatus").textContent = data.gemini_key_status || "Not configured";
  document.getElementById("agnesKeyStatus").textContent = data.agnes_key_status || "Not configured";
  f.gemini_api_key.value = "";
  f.agnes_api_key.value = "";
}

function bindShowHide(inputId, btnId) {
  const input = document.getElementById(inputId);
  const btn = document.getElementById(btnId);
  btn.addEventListener("click", () => {
    const show = input.type === "password";
    input.type = show ? "text" : "password";
    btn.textContent = show ? "Hide" : "Show";
  });
}

function phoneUrl() {
  const el = document.getElementById("phoneQrUrl");
  return (el?.dataset?.url || el?.textContent || "").trim();
}

function renderQr() {
  const url = phoneUrl();
  const canvas = document.getElementById("phoneQrCanvas");
  const img = document.getElementById("phoneQrImg");
  if (!url) return;

  if (window.QRCode && canvas) {
    QRCode.toCanvas(
      canvas,
      url,
      { width: 280, margin: 2, errorCorrectionLevel: "M", color: { dark: "#111111", light: "#ffffff" } },
      (err) => {
        if (err) {
          fallbackQrImage(url, canvas, img);
        }
      }
    );
    return;
  }
  fallbackQrImage(url, canvas, img);
}

function fallbackQrImage(url, canvas, img) {
  if (canvas) canvas.hidden = true;
  if (!img) return;
  img.hidden = false;
  img.src = "api/qr.php?u=" + encodeURIComponent(url) + "&t=" + Date.now();
  img.onerror = () => {
    img.src =
      "https://api.qrserver.com/v1/create-qr-code/?size=280x280&ecc=M&margin=8&data=" +
      encodeURIComponent(url);
  };
}

document.getElementById("settingsForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  const f = e.target;
  const payload = {
    action: "save",
    ai_provider: f.ai_provider.value,
    gemini_api_key: f.gemini_api_key.value,
    gemini_model: f.gemini_model.value,
    gemini_fallback_models: f.gemini_fallback_models.value,
    agnes_api_key: f.agnes_api_key.value,
    agnes_api_base: f.agnes_api_base.value,
    agnes_model: f.agnes_model.value,
    agnes_fallback_models: f.agnes_fallback_models.value,
    request_timeout: Number(f.request_timeout.value || 45),
    db_enabled: f.db_enabled.checked,
    web_lookup_enabled: f.web_lookup_enabled.checked,
  };

  const btn = document.getElementById("saveBtn");
  btn.disabled = true;
  btn.textContent = "Saving…";
  try {
    const res = await fetch("api/settings.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || "Save failed");
    fillForm(json.data);
    document.getElementById("saveMsg").textContent = "Settings saved.";
  } catch (err) {
    document.getElementById("saveMsg").textContent =
      err.message + "（若在 cPanel：请确认 config 文件夹可写，并已有 config.php）";
  } finally {
    btn.disabled = false;
    btn.textContent = "Save settings";
  }
});

document.getElementById("testBtn").addEventListener("click", async () => {
  const box = document.getElementById("testResult");
  box.textContent = "Testing…";
  try {
    const res = await fetch("api/settings.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "test" }),
    });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || "Test failed");
    const g = json.data.gemini;
    const a = json.data.agnes;
    box.innerHTML = `
      <ul class="test-list">
        <li><span>Gemini</span><strong>${g.ok ? "✓" : "✗"} ${g.message}</strong></li>
        <li><span>Agnes</span><strong>${a.ok ? "✓" : "✗"} ${a.message}</strong></li>
      </ul>`;
  } catch (err) {
    box.textContent = err.message;
  }
});

const copyBtn = document.getElementById("copyUrlBtn");
if (copyBtn) {
  copyBtn.addEventListener("click", async () => {
    const url = phoneUrl();
    try {
      await navigator.clipboard.writeText(url);
      copyBtn.textContent = "已复制";
      setTimeout(() => (copyBtn.textContent = "复制链接"), 1200);
    } catch (_) {
      prompt("复制链接：", url);
    }
  });
}

const openHttpsBtn = document.getElementById("openHttpsBtn");
if (openHttpsBtn) {
  openHttpsBtn.href = phoneUrl() || "index.php";
}

bindShowHide("gemini_api_key", "toggleGeminiKey");
bindShowHide("agnes_api_key", "toggleAgnesKey");

renderQr();

(async function init() {
  try {
    const data = await loadSettings();
    fillForm(data);
  } catch (err) {
    document.getElementById("saveMsg").textContent =
      err.message + "（检查 PHP 是否 8+，以及 api/settings.php 能否打开）";
  }
})();
