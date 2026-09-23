/**
 * Build honest bilingual product cards from AI / local+OCR — never invent models.
 */
window.SmartResult = (function () {
  var ZH_CAT = {
    bottle: "瓶子",
    "cell phone": "手机",
    phone: "手机",
    laptop: "笔记本电脑",
    keyboard: "键盘",
    mouse: "滑鼠",
    book: "书本",
    cup: "杯子",
    "wine glass": "酒杯",
    chair: "椅子",
    tv: "屏幕/显示器",
    remote: "遥控器",
    clock: "时钟",
    backpack: "背包",
    handbag: "手提包",
    umbrella: "雨伞",
    "sports ball": "球",
    banana: "香蕉",
    apple: "苹果（水果）",
    orange: "橙子",
    person: "人物",
    scissors: "剪刀",
    toothbrush: "牙刷",
    headphones: "耳机",
    camera: "相机",
  };

  function fromLocalAndOcr(coco, ocr) {
    var label = String((coco && coco.objectLabel) || "object").toLowerCase();
    var zhCat = ZH_CAT[label] || label;
    var tokens = (ocr && ocr.tokens) || [];
    var raw = (ocr && ocr.text) || "";
    var brandGuess = tokens.length ? tokens[0] : "";
    var visible = tokens.slice(0, 4).join(" ");

    var nameEn, nameZh, descEn, descZh, specEn, specZh, mfr;

    if (visible && ocr.conf >= 35) {
      mfr = /^[A-Za-z]/.test(brandGuess) ? brandGuess : "";
      nameEn = (mfr ? mfr + " " : "") + (coco.productName || label);
      nameZh = (mfr ? mfr + " " : "") + zhCat;
      specEn = "Visible text: " + visible;
      specZh = "画面可见文字：" + visible;
      descEn =
        "Detected a " +
        (coco.productName || label) +
        ". Visible label/text on the item: \"" +
        visible +
        "\". Exact model is only confirmed when printed clearly.";
      descZh =
        "检测到" +
        zhCat +
        "。物品上可读到的文字：" +
        visible +
        "。若要精确型号，请把品牌与型号字对准镜头，并配置 Gemini AI。";
    } else {
      mfr = "";
      nameEn = coco.productName || label;
      nameZh = zhCat;
      specEn = "Category detection only";
      specZh = "仅类别识别";
      descEn =
        "This looks like a " +
        nameEn +
        ". No clear brand text was readable. Point the label toward the camera, or configure Gemini API Key for smart product intro.";
      descZh =
        "看起来是" +
        zhCat +
        "。未能清晰读到品牌文字。请把标签对准镜头，或在 Settings 填写 Gemini API Key 以获得智能产品介绍。";
    }

    return {
      objectLabel: label,
      objectLabelZh: zhCat,
      productName: nameEn,
      productNameZh: nameZh,
      manufacturer: mfr,
      specification: specEn,
      specificationZh: specZh,
      description: descEn,
      descriptionZh: descZh,
      boundingBox: coco.boundingBox || { ymin: 120, xmin: 120, ymax: 880, xmax: 880 },
      confidence: Math.max(Number(coco.confidence) || 0, (ocr && ocr.conf ? ocr.conf / 100 : 0) * 0.5),
      provider: "local+ocr",
    };
  }

  /** Sanitize cloud AI result — drop empty junk, keep real fields */
  function sanitizeCloud(data) {
    if (!data) return null;
    var r = Object.assign({}, data);
    r.provider = r.provider || "gemini";
    if (!r.productNameZh && r.objectLabelZh) r.productNameZh = r.productName ? r.productName : r.objectLabelZh;
    if (!r.descriptionZh && r.specificationZh) r.descriptionZh = r.specificationZh;
    if (!r.description && r.specification) r.description = r.specification;
    // Reject garbage: empty names
    if (!r.productName && !r.objectLabel) return null;
    return r;
  }

  return { fromLocalAndOcr: fromLocalAndOcr, sanitizeCloud: sanitizeCloud };
})();
