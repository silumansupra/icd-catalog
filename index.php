<?php
// ============================================================
//  Katalog Pencarian ICD-10 / ICD-9-CM  (single file PHP + JS)
//  KISS: PDO + endpoint JSON di file yang sama
// ============================================================

// ---------- KONFIG DB ----------
// Kredensial dibaca dari config.php (salin dari config.example.php).
// config.php tidak di-commit ke repo.
$cfgFile = __DIR__ . '/config.php';
if (!file_exists($cfgFile)) {
    http_response_code(500);
    exit('config.php belum ada. Salin config.example.php menjadi config.php lalu isi kredensial DB.');
}
$CFG = require $cfgFile;

function db()
{
    global $CFG;
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host={$CFG['host']};dbname={$CFG['name']};charset=utf8mb4",
            $CFG['user'],
            $CFG['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        // tabel disimpan latin1, paksa koneksi baca sebagai utf8mb4
        $pdo->exec("SET NAMES utf8mb4");
    }
    return $pdo;
}

function json_out($data)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- API ENDPOINT ----------
if (isset($_GET['api'])) {
    $action  = $_GET['api'];
    $q       = trim($_GET['q'] ?? '');
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 25;
    $offset  = ($page - 1) * $perPage;

    try {
        if ($action === 'search') {
            $t = $_GET['type'] ?? 'icd10';
            $type = in_array($t, ['icd9', 'index'], true) ? $t : 'icd10';
            $like = '%' . $q . '%';

            if ($type === 'index') {
                // Alphabetical Index (ICD-10 Volume 3) — cari lead term -> kode
                $where = "WHERE vol_code IS NOT NULL AND vol_code <> ''";
                $params = [];
                if ($q !== '') {
                    $where .= " AND (vol_name LIKE :q1 OR vol_code LIKE :q2)";
                    $params[':q1'] = $like;
                    $params[':q2'] = $like;
                }
                $total = db()->prepare("SELECT COUNT(*) FROM icd10_volume3 $where");
                $total->execute($params);
                $totalRows = (int)$total->fetchColumn();

                $sql = "SELECT vol_code AS code, vol_name AS name,
                               vol_code2, vol_index
                        FROM icd10_volume3 $where
                        ORDER BY vol_name
                        LIMIT $perPage OFFSET $offset";
                $st = db()->prepare($sql);
                $st->execute($params);
                $rows = $st->fetchAll();
            } elseif ($type === 'icd10') {
                $where = "WHERE icd10_deprecated = '0'";
                $params = [];
                if ($q !== '') {
                    $where .= " AND (icd10_code LIKE :q1 OR icd10_name LIKE :q2)";
                    $params[':q1'] = $like;
                    $params[':q2'] = $like;
                }
                $total = db()->prepare("SELECT COUNT(*) FROM icd10_volume1 $where");
                $total->execute($params);
                $totalRows = (int)$total->fetchColumn();

                $sql = "SELECT icd10_code AS code, icd10_name AS name,
                               icd10_description AS descr, chapter_code, block_code
                        FROM icd10_volume1 $where
                        ORDER BY icd10_code
                        LIMIT $perPage OFFSET $offset";
                $st = db()->prepare($sql);
                $st->execute($params);
                $rows = $st->fetchAll();
            } else {
                $where = "WHERE 1=1";
                $params = [];
                if ($q !== '') {
                    $where .= " AND (icd9cm_code LIKE :q1 OR icd9cm_name LIKE :q2)";
                    $params[':q1'] = $like;
                    $params[':q2'] = $like;
                }
                $total = db()->prepare("SELECT COUNT(*) FROM icd9cm_codes $where");
                $total->execute($params);
                $totalRows = (int)$total->fetchColumn();

                $sql = "SELECT icd9cm_code AS code, icd9cm_name AS name,
                               icd9cm_description AS descr, root_code, child1_code
                        FROM icd9cm_codes $where
                        ORDER BY icd9cm_code
                        LIMIT $perPage OFFSET $offset";
                $st = db()->prepare($sql);
                $st->execute($params);
                $rows = $st->fetchAll();
            }

            json_out([
                'ok'       => true,
                'type'     => $type,
                'page'     => $page,
                'perPage'  => $perPage,
                'total'    => $totalRows,
                'lastPage' => (int)ceil($totalRows / $perPage),
                'rows'     => $rows,
            ]);
        }

        if ($action === 'detail') {
            // ambil 1 kode ICD-10 by code (untuk lompat dari Alphabetical Index)
            $code = $_GET['code'] ?? '';
            $st = db()->prepare(
                "SELECT icd10_code AS code, icd10_name AS name,
                        icd10_description AS descr, chapter_code, block_code
                 FROM icd10_volume1 WHERE icd10_code = :c LIMIT 1"
            );
            $st->execute([':c' => $code]);
            $row = $st->fetch();
            json_out(['ok' => (bool)$row, 'row' => $row ?: null]);
        }
        json_out(['ok' => false, 'msg' => 'unknown action']);
    } catch (Throwable $e) {
        http_response_code(500);
        json_out(['ok' => false, 'msg' => $e->getMessage()]);
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Katalog ICD-10 / ICD-9-CM</title>
    <style>
        :root {
            --bg: #0f172a;
            --card: #1e293b;
            --line: #334155;
            --txt: #e2e8f0;
            --muted: #94a3b8;
            --accent: #38bdf8;
            --accent2: #22c55e;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: system-ui, Segoe UI, Roboto, sans-serif;
            background: var(--bg);
            color: var(--txt);
        }

        header {
            padding: 20px 16px;
            border-bottom: 1px solid var(--line);
            background: #111827;
        }

        header h1 {
            margin: 0;
            font-size: 20px;
        }

        header p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .wrap {
            max-width: 920px;
            margin: 0 auto;
            padding: 16px;
        }

        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
        }

        .tab {
            padding: 8px 16px;
            border: 1px solid var(--line);
            border-radius: 8px;
            cursor: pointer;
            background: var(--card);
            color: var(--muted);
            font-size: 14px;
        }

        .tab.active {
            border-color: var(--accent);
            color: var(--accent);
        }

        .searchbar {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
        }

        .searchbar input {
            flex: 1;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: var(--card);
            color: var(--txt);
            font-size: 15px;
            outline: none;
        }

        .searchbar input:focus {
            border-color: var(--accent);
        }

        .meta {
            color: var(--muted);
            font-size: 13px;
            margin: 8px 2px;
        }

        .row {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 8px;
            background: var(--card);
            cursor: pointer;
            transition: .12s;
        }

        .row:hover {
            border-color: var(--accent);
        }

        .code {
            font-weight: 700;
            color: var(--accent2);
            font-family: ui-monospace, Consolas, monospace;
        }

        .name {
            margin-top: 2px;
        }

        .descr {
            display: none;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed var(--line);
            color: var(--muted);
            font-size: 13.5px;
            white-space: pre-wrap;
            line-height: 1.5;
        }

        .row.open .descr {
            display: block;
        }

        .pager {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: center;
            margin: 16px 0 30px;
        }

        .pager button {
            padding: 8px 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--card);
            color: var(--txt);
            cursor: pointer;
        }

        .pager button:disabled {
            opacity: .4;
            cursor: default;
        }

        .empty {
            text-align: center;
            color: var(--muted);
            padding: 40px 0;
        }

        .badge {
            font-size: 11px;
            color: var(--muted);
            margin-left: 6px;
        }
    </style>
</head>

<body>
    <header>
        <div class="wrap" style="padding:0;">
            <h1>Katalog Pencarian ICD-10 / ICD-9-CM</h1>
            <p>Cari berdasarkan kode atau deskripsi diagnosa (ICD-10) & tindakan (ICD-9-CM).</p>
        </div>
    </header>

    <div class="wrap">
        <div class="tabs">
            <div class="tab active" data-type="icd10">ICD-10 Diagnosa</div>
            <div class="tab" data-type="icd9">ICD-9-CM Tindakan</div>
            <div class="tab" data-type="index">Alphabetical Index</div>
        </div>

        <div class="searchbar">
            <input id="q" type="text" placeholder="Ketik kode (mis. A09) atau nama (mis. diabetes)…" autocomplete="off">
        </div>

        <div class="meta" id="meta">Memuat…</div>
        <div id="results"></div>
        <div class="pager" id="pager"></div>
    </div>

    <script>
        const state = {
            type: 'icd10',
            q: '',
            page: 1,
            lastPage: 1,
            timer: null
        };
        const $ = s => document.querySelector(s);

        function esc(s) {
            return (s ?? '').replace(/[&<>]/g, c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;'
            } [c]));
        }

        async function load() {
            const url = `?api=search&type=${state.type}&q=${encodeURIComponent(state.q)}&page=${state.page}`;
            $('#meta').textContent = 'Mencari…';
            try {
                const r = await fetch(url);
                const d = await r.json();
                if (!d.ok) {
                    $('#meta').textContent = 'Error: ' + d.msg;
                    return;
                }
                state.lastPage = d.lastPage;
                render(d);
            } catch (e) {
                $('#meta').textContent = 'Gagal memuat: ' + e.message;
            }
        }

        function render(d) {
            $('#meta').textContent =
                `${d.total.toLocaleString('id-ID')} hasil` +
                (state.q ? ` untuk "${state.q}"` : '') +
                ` — halaman ${d.page}/${Math.max(d.lastPage,1)}`;

            const res = $('#results');
            if (!d.rows.length) {
                res.innerHTML = '<div class="empty">Tidak ada hasil.</div>';
                $('#pager').innerHTML = '';
                return;
            }

            if (state.type === 'index') {
                res.innerHTML = d.rows.map(x => `
      <div class="row" onclick="jumpTo('${esc(x.code)}')" title="Klik untuk lihat detail kode di ICD-10">
        <div class="name">${esc(x.name)}</div>
        <div><span class="code">${esc(x.code)}</span>
          ${x.vol_code2 ? '<span class="code"> / '+esc(x.vol_code2)+'</span>' : ''}
          <span class="badge">indeks ${esc(x.vol_index)} · buka detail ›</span>
        </div>
      </div>`).join('');
            } else {
                res.innerHTML = d.rows.map(x => `
      <div class="row" onclick="this.classList.toggle('open')">
        <div><span class="code">${esc(x.code)}</span>
          <span class="badge">${state.type==='icd10' ? 'Bab '+esc(x.chapter_code)+' · '+esc(x.block_code) : 'Root '+esc(x.root_code)}</span>
        </div>
        <div class="name">${esc(x.name)}</div>
        <div class="descr">${x.descr ? esc(x.descr) : '<i>Tidak ada deskripsi tambahan.</i>'}</div>
      </div>`).join('');
            }

            $('#pager').innerHTML = `
    <button ${d.page<=1?'disabled':''} onclick="goto(${d.page-1})">‹ Sebelumnya</button>
    <span class="meta">${d.page} / ${Math.max(d.lastPage,1)}</span>
    <button ${d.page>=d.lastPage?'disabled':''} onclick="goto(${d.page+1})">Berikutnya ›</button>`;
        }

        // Lompat dari Alphabetical Index ke detail kode di tab ICD-10
        async function jumpTo(code) {
            try {
                const r = await fetch(`?api=detail&code=${encodeURIComponent(code)}`);
                const d = await r.json();
                // pindah ke tab ICD-10 dengan kode sebagai query
                document.querySelectorAll('.tab').forEach(x => x.classList.toggle('active', x.dataset.type === 'icd10'));
                state.type = 'icd10';
                state.q = code;
                state.page = 1;
                $('#q').value = code;
                await load();
                // buka baris pertama (kode persisnya) otomatis
                const first = document.querySelector('.row');
                if (first) first.classList.add('open');
                if (!d.ok) {
                    $('#meta').textContent += ' — kode tidak ditemukan di Volume 1 (mungkin kategori induk).';
                }
            } catch (e) {
                alert('Gagal memuat detail: ' + e.message);
            }
        }

        function goto(p) {
            state.page = p;
            load();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        $('#q').addEventListener('input', e => {
            state.q = e.target.value.trim();
            state.page = 1;
            clearTimeout(state.timer);
            state.timer = setTimeout(load, 300); // debounce
        });

        document.querySelectorAll('.tab').forEach(t => t.addEventListener('click', () => {
            document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
            t.classList.add('active');
            state.type = t.dataset.type;
            state.page = 1;
            const ph = {
                icd10: 'Ketik kode (mis. A09) atau nama (mis. diabetes)…',
                icd9: 'Ketik kode tindakan (mis. 00.01) atau nama…',
                index: 'Ketik istilah (mis. fracture, vertigo, abscess)…'
            };
            $('#q').placeholder = ph[state.type];
            load();
        }));

        load();
    </script>
</body>

</html>