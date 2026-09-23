/**
 * Local COCO-SSD — center-weighted, product-first (fallback only).
 */
window.LocalVision = (function () {
  var modelPromise = null;

  var PRODUCT_BONUS = {
    bottle: 1.35,
    "cell phone": 1.4,
    laptop: 1.35,
    keyboard: 1.3,
    mouse: 1.3,
    book: 1.25,
    cup: 1.2,
    "wine glass": 1.15,
    remote: 1.25,
    clock: 1.1,
    scissors: 1.15,
    toothbrush: 1.1,
    backpack: 1.05,
    handbag: 1.05,
    umbrella: 1.05,
    "sports ball": 1.1,
    banana: 1.1,
    apple: 1.1,
    orange: 1.1,
    tv: 1.15,
    person: 0.15,
    chair: 0.55,
    couch: 0.4,
    bed: 0.35,
    diningtable: 0.35,
  };

  var INFOS = {
    bottle: {
      en: "Bottle",
      zh: "瓶子",
      specEn: "Liquid container — aim at label for brand",
      specZh: "液体容器 — 请对准标签可读品牌",
      descEn: "A bottle is centered in the frame. Point the label toward the camera for brand-specific details.",
      descZh: "取景框中央是瓶子。请把标签对准镜头，才能识别具体品牌与容量。",
    },
    "cell phone": {
      en: "Mobile phone",
      zh: "手机",
      specEn: "Smartphone — aim at back logo / model text",
      specZh: "智能手机 — 请对准背面 Logo 或型号",
      descEn: "A smartphone is in the viewfinder. For exact model, show the rear logo clearly.",
      descZh: "取景框内是手机。若要准确型号，请清晰拍摄背面品牌标识。",
    },
    laptop: {
      en: "Laptop",
      zh: "笔记本电脑",
      specEn: "Portable computer — aim at lid logo",
      specZh: "笔记本电脑 — 请对准机盖品牌",
      descEn: "A laptop is in the viewfinder. Show the brand logo on the lid for a more precise name.",
      descZh: "取景框内是笔记本电脑。请对准机盖品牌 Logo 以获得更准确名称。",
    },
    keyboard: {
      en: "Keyboard",
      zh: "键盘",
      specEn: "Computer keyboard",
      specZh: "电脑键盘",
      descEn: "A keyboard fills the viewfinder.",
      descZh: "取景框内是键盘。",
    },
    mouse: {
      en: "Computer mouse",
      zh: "滑鼠",
      specEn: "Pointing device",
      specZh: "电脑指向设备",
      descEn: "A computer mouse is in the viewfinder.",
      descZh: "取景框内是电脑滑鼠。",
    },
    book: {
      en: "Book",
      zh: "书本",
      specEn: "Printed book — aim at cover title",
      specZh: "书本 — 请对准封面书名",
      descEn: "A book is in the viewfinder. Show the cover title for a specific name.",
      descZh: "取景框内是书本。请对准封面书名以确认书名。",
    },
    cup: {
      en: "Cup",
      zh: "杯子",
      specEn: "Drinking cup",
      specZh: "杯子",
      descEn: "A cup is in the viewfinder.",
      descZh: "取景框内是杯子。",
    },
    "wine glass": {
      en: "Wine glass",
      zh: "酒杯",
      specEn: "Glassware",
      specZh: "玻璃酒杯",
      descEn: "A wine glass is in the viewfinder.",
      descZh: "取景框内是酒杯。",
    },
    chair: {
      en: "Chair",
      zh: "椅子",
      specEn: "Seat",
      specZh: "座椅",
      descEn: "A chair is detected. If you meant another product, fill the frame with that item.",
      descZh: "检测到椅子。若你要扫的是别的物品，请把该物品填满取景框。",
    },
    tv: {
      en: "Screen / Monitor",
      zh: "屏幕 / 显示器",
      specEn: "Display",
      specZh: "显示设备",
      descEn: "A screen or monitor is in the viewfinder.",
      descZh: "取景框内是屏幕或显示器。",
    },
    remote: {
      en: "Remote control",
      zh: "遥控器",
      specEn: "Remote",
      specZh: "遥控器",
      descEn: "A remote control is in the viewfinder.",
      descZh: "取景框内是遥控器。",
    },
    clock: {
      en: "Clock",
      zh: "时钟",
      specEn: "Clock",
      specZh: "时钟",
      descEn: "A clock is in the viewfinder.",
      descZh: "取景框内是时钟。",
    },
    scissors: {
      en: "Scissors",
      zh: "剪刀",
      specEn: "Cutting tool",
      specZh: "剪刀",
      descEn: "Scissors are in the viewfinder.",
      descZh: "取景框内是剪刀。",
    },
    toothbrush: {
      en: "Toothbrush",
      zh: "牙刷",
      specEn: "Oral care",
      specZh: "牙刷",
      descEn: "A toothbrush is in the viewfinder.",
      descZh: "取景框内是牙刷。",
    },
    backpack: {
      en: "Backpack",
      zh: "背包",
      specEn: "Bag",
      specZh: "背包",
      descEn: "A backpack is in the viewfinder.",
      descZh: "取景框内是背包。",
    },
    handbag: {
      en: "Handbag",
      zh: "手提包",
      specEn: "Bag",
      specZh: "手提包",
      descEn: "A handbag is in the viewfinder.",
      descZh: "取景框内是手提包。",
    },
    umbrella: {
      en: "Umbrella",
      zh: "雨伞",
      specEn: "Umbrella",
      specZh: "雨伞",
      descEn: "An umbrella is in the viewfinder.",
      descZh: "取景框内是雨伞。",
    },
    "sports ball": {
      en: "Ball",
      zh: "球",
      specEn: "Sports ball",
      specZh: "球",
      descEn: "A ball is in the viewfinder.",
      descZh: "取景框内是球。",
    },
    banana: {
      en: "Banana",
      zh: "香蕉",
      specEn: "Fruit",
      specZh: "水果",
      descEn: "A banana is in the viewfinder.",
      descZh: "取景框内是香蕉。",
    },
    apple: {
      en: "Apple (fruit)",
      zh: "苹果（水果）",
      specEn: "Fruit",
      specZh: "水果",
      descEn: "An apple fruit is in the viewfinder (not an electronic Apple device).",
      descZh: "取景框内是水果苹果（不是电子产品）。",
    },
    orange: {
      en: "Orange",
      zh: "橙子",
      specEn: "Fruit",
      specZh: "水果",
      descEn: "An orange is in the viewfinder.",
      descZh: "取景框内是橙子。",
    },
    person: {
      en: "Person",
      zh: "人物",
      specEn: "Human",
      specZh: "人",
      descEn: "A person fills the frame. Move closer to the product you want to scan.",
      descZh: "取景框主要是人。请把要识别的产品放进取景框中央。",
    },
  };

  function loadScripts() {
    if (window.cocoSsd) return Promise.resolve();
    function add(src) {
      return new Promise(function (resolve, reject) {
        var s = document.createElement("script");
        s.src = src;
        s.async = true;
        s.onload = resolve;
        s.onerror = reject;
        document.head.appendChild(s);
      });
    }
    return add("https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.22.0/dist/tf.min.js").then(function () {
      return add("https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd@2.2.3/dist/coco-ssd.min.js");
    });
  }

  function ensureModel() {
    if (!modelPromise) {
      modelPromise = loadScripts().then(function () {
        return window.cocoSsd.load({ base: "lite_mobilenet_v2" });
      });
    }
    return modelPromise;
  }

  function boxFromCoco(bbox, imgW, imgH) {
    var x = bbox[0];
    var y = bbox[1];
    var w = bbox[2];
    var h = bbox[3];
    return {
      xmin: Math.round((x / imgW) * 1000),
      ymin: Math.round((y / imgH) * 1000),
      xmax: Math.round(((x + w) / imgW) * 1000),
      ymax: Math.round(((y + h) / imgH) * 1000),
    };
  }

  function scorePrediction(pred, W, H) {
    var key = String(pred.class || "").toLowerCase();
    var cx = pred.bbox[0] + pred.bbox[2] / 2;
    var cy = pred.bbox[1] + pred.bbox[3] / 2;
    var dx = (cx - W / 2) / (W / 2);
    var dy = (cy - H / 2) / (H / 2);
    var dist = Math.sqrt(dx * dx + dy * dy);
    var area = (pred.bbox[2] * pred.bbox[3]) / (W * H);
    var centerBoost = 1.35 - Math.min(1.1, dist);
    var areaBoost = 0.55 + Math.min(0.7, area * 2.8);
    var classBoost = PRODUCT_BONUS[key] != null ? PRODUCT_BONUS[key] : 1;
    return pred.score * centerBoost * areaBoost * classBoost;
  }

  async function recognizeFromCanvas(canvasEl) {
    var model = await ensureModel();
    var W = canvasEl.width;
    var H = canvasEl.height;
    var preds = await model.detect(canvasEl, 12, 0.35);
    if (!preds || !preds.length) {
      throw new Error("取景框内未检测到物品，请把产品放框中央再扫");
    }

    var ranked = preds
      .map(function (p) {
        return { pred: p, score: scorePrediction(p, W, H) };
      })
      .sort(function (a, b) {
        return b.score - a.score;
      });

    // Prefer non-person if any decent candidate
    var best = ranked[0].pred;
    for (var i = 0; i < ranked.length; i++) {
      if (ranked[i].pred.class !== "person" && ranked[i].score > 0.12) {
        best = ranked[i].pred;
        break;
      }
    }

    var key = String(best.class || "").toLowerCase();
    var info = INFOS[key] || {
      en: best.class,
      zh: best.class,
      specEn: "Detected object in viewfinder",
      specZh: "取景框内检测到的物体",
      descEn: "Detected: " + best.class + ". For brand/model details, configure Gemini API Key.",
      descZh: "检测到：" + best.class + "。若要准确品牌型号介绍，请在 Settings 填写 Gemini API Key。",
    };

    return {
      objectLabel: key,
      objectLabelZh: info.zh,
      productName: info.en,
      productNameZh: info.zh,
      manufacturer: "",
      specification: info.specEn,
      specificationZh: info.specZh,
      description: info.descEn,
      descriptionZh: info.descZh,
      boundingBox: boxFromCoco(best.bbox, W, H),
      confidence: Math.max(0, Math.min(1, best.score || 0)),
      provider: "local-vision",
    };
  }

  return { recognizeFromCanvas: recognizeFromCanvas, ensureModel: ensureModel };
})();
