/**
 * Bilingual speech (English + 华文) via Web Speech API.
 */
window.ScannerVoice = (function () {
  var synth = window.speechSynthesis || null;
  var speaking = false;

  function pickVoice(lang) {
    if (!synth) return null;
    var voices = synth.getVoices() || [];
    var want = (lang || "en").toLowerCase();
    if (want.indexOf("zh") === 0 || want === "zh" || want === "cn") {
      return (
        voices.find(function (v) {
          return /zh-CN|zh_CN/i.test(v.lang);
        }) ||
        voices.find(function (v) {
          return /zh|Chinese|华文|中文/i.test(v.lang + v.name);
        }) ||
        null
      );
    }
    return (
      voices.find(function (v) {
        return /^en(-|_)/i.test(v.lang);
      }) ||
      voices.find(function (v) {
        return /en/i.test(v.lang);
      }) ||
      null
    );
  }

  if (synth) {
    synth.onvoiceschanged = function () {
      pickVoice("zh-CN");
      pickVoice("en-US");
    };
  }

  function stop() {
    if (!synth) return;
    synth.cancel();
    speaking = false;
  }

  function speak(text, lang) {
    return new Promise(function (resolve) {
      if (!synth || !text) {
        resolve(false);
        return;
      }
      stop();
      var u = new SpeechSynthesisUtterance(String(text));
      var useLang = lang || "en-US";
      u.lang = useLang;
      u.rate = useLang.indexOf("zh") === 0 ? 0.95 : 0.98;
      u.pitch = 1;
      var voice = pickVoice(useLang);
      if (voice) u.voice = voice;
      u.onend = function () {
        speaking = false;
        resolve(true);
      };
      u.onerror = function () {
        speaking = false;
        resolve(false);
      };
      speaking = true;
      setTimeout(function () {
        synth.speak(u);
      }, 60);
    });
  }

  /** Speak English then Chinese intro. */
  async function speakBilingual(enText, zhText) {
    if (enText) await speak(enText, "en-US");
    if (zhText) await speak(zhText, "zh-CN");
  }

  return {
    speak: speak,
    speakBilingual: speakBilingual,
    stop: stop,
    get speaking() {
      return speaking;
    },
    supported: !!synth,
  };
})();
