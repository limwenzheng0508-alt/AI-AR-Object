/**
 * Remember brands / products for smarter later scans.
 */
window.BrandMemory = (function () {
  var KEY = "ai_ar_brand_memory_v1";
  var MAX = 40;

  function load() {
    try {
      var raw = localStorage.getItem(KEY);
      var arr = raw ? JSON.parse(raw) : [];
      return Array.isArray(arr) ? arr : [];
    } catch (_) {
      return [];
    }
  }

  function save(list) {
    try {
      localStorage.setItem(KEY, JSON.stringify(list.slice(0, MAX)));
    } catch (_) {}
  }

  function normalize(s) {
    return String(s || "")
      .trim()
      .replace(/\s+/g, " ");
  }

  function remember(result) {
    if (!result || result.provider === "local-vision") return;
    var brand = normalize(result.manufacturer);
    var name = normalize(result.productName);
    var nameZh = normalize(result.productNameZh);
    if (!brand && !name) return;

    var list = load().filter(function (item) {
      return !(
        (brand && item.brand === brand && item.name === name) ||
        (name && item.name === name && item.brand === brand)
      );
    });

    list.unshift({
      brand: brand,
      name: name,
      nameZh: nameZh,
      label: normalize(result.objectLabel),
      at: Date.now(),
    });
    save(list);
  }

  function knownBrands() {
    var seen = {};
    var out = [];
    load().forEach(function (item) {
      if (item.brand && !seen[item.brand.toLowerCase()]) {
        seen[item.brand.toLowerCase()] = true;
        out.push(item.brand);
      }
    });
    return out.slice(0, 20);
  }

  function knownProducts() {
    return load()
      .slice(0, 15)
      .map(function (item) {
        return {
          brand: item.brand,
          name: item.name,
          nameZh: item.nameZh,
        };
      });
  }

  function clear() {
    save([]);
  }

  return {
    remember: remember,
    knownBrands: knownBrands,
    knownProducts: knownProducts,
    load: load,
    clear: clear,
  };
})();
