<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>Scan History · AI + AR Scanner</title>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <link rel="stylesheet" href="assets/css/app.css" />
</head>
<body class="page-shell">
  <div class="page-wrap">
    <nav class="page-nav">
      <a class="ghost-btn" href="index.php"><i class="fa-solid fa-camera"></i> Scanner</a>
      <a class="ghost-btn" href="settings.php"><i class="fa-solid fa-gear"></i> Settings</a>
    </nav>

    <section class="panel">
      <h1>Scan History</h1>
      <div class="form-actions" style="margin-bottom:1rem">
        <input type="search" id="searchQ" placeholder="Search product / brand" style="flex:1;min-width:160px;border-radius:999px;border:1px solid var(--line);background:#0b111a;color:#fff;padding:.55rem .9rem" />
        <button class="ghost-btn" type="button" id="searchBtn">Search</button>
        <button class="ghost-btn" type="button" id="clearBtn">Clear history</button>
      </div>
      <p id="histMsg" class="key-status" role="status" aria-live="polite">Loading…</p>
      <div class="table-wrap">
        <table class="history">
          <thead>
            <tr>
              <th>Product</th>
              <th>Provider</th>
              <th>Confidence</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody id="histBody"></tbody>
        </table>
      </div>
      <div class="form-actions" style="margin-top:1rem">
        <button class="ghost-btn" type="button" id="prevPage">Prev</button>
        <span id="pageInfo" class="key-status"></span>
        <button class="ghost-btn" type="button" id="nextPage">Next</button>
      </div>
    </section>
  </div>

  <script>
    let page = 1;
    let total = 0;
    const perPage = 20;

    async function load() {
      const q = document.getElementById("searchQ").value.trim();
      const url = `api/history.php?page=${page}&q=${encodeURIComponent(q)}`;
      const res = await fetch(url, { cache: "no-store" });
      const json = await res.json();
      const msg = document.getElementById("histMsg");
      const body = document.getElementById("histBody");
      body.innerHTML = "";

      if (!json.ok) {
        msg.textContent = json.error || "Failed to load history";
        return;
      }

      if (!json.data.enabled) {
        msg.textContent = "History is disabled. Enable MySQL in Settings and import database/database.sql.";
        return;
      }

      total = json.data.total;
      msg.textContent = total ? `${total} record(s)` : "No scans yet.";
      document.getElementById("pageInfo").textContent = `Page ${json.data.page}`;

      for (const row of json.data.items) {
        const tr = document.createElement("tr");
        const conf = Math.round((Number(row.confidence) || 0) * 100);
        tr.innerHTML = `
          <td>${escapeHtml(row.product_name || row.object_label)}</td>
          <td>${escapeHtml(row.provider || "")}</td>
          <td>${conf}%</td>
          <td>${escapeHtml(row.created_at || "")}</td>`;
        body.appendChild(tr);
      }
    }

    function escapeHtml(s) {
      return String(s)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;");
    }

    document.getElementById("searchBtn").addEventListener("click", () => {
      page = 1;
      load();
    });
    document.getElementById("prevPage").addEventListener("click", () => {
      if (page > 1) {
        page -= 1;
        load();
      }
    });
    document.getElementById("nextPage").addEventListener("click", () => {
      if (page * perPage < total) {
        page += 1;
        load();
      }
    });
    document.getElementById("clearBtn").addEventListener("click", async () => {
      if (!confirm("Clear all scan history?")) return;
      const res = await fetch("api/history.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "clear" }),
      });
      const json = await res.json();
      if (!json.ok) alert(json.error || "Clear failed");
      page = 1;
      load();
    });

    load();
  </script>
</body>
</html>
