<?php
session_start();
require_once __DIR__ . '/core/ahp_loader.php';
require_once __DIR__ . '/core/saw_engine.php';
require_once __DIR__ . '/core/wp_engine.php';

// ── Database connection ──────────────────────────────────────────────────────
$pdo = null;
$dbError = null;
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=icu_priority;charset=utf8",
        "root", "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    $dbError = $e->getMessage();
}

// ── Pagination & filter ──────────────────────────────────────────────────────
$method   = $_GET['method'] ?? 'both';   // saw | wp | both | compare
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = (int)($_GET['per_page'] ?? 10);
$search   = trim($_GET['search'] ?? '');

// ── Load patients ────────────────────────────────────────────────────────────
$patients = [];
if ($pdo) {
    try {
        $where = $search ? "WHERE patient_id LIKE :s OR age LIKE :s2" : "";
        $sql   = "SELECT patient_id, age, bmi, d1_spo2_min, d1_heartrate_min
                  FROM dataset_akumulasi
                  $where
                  ORDER BY patient_id
                  LIMIT 200";
        $stmt = $pdo->prepare($sql);
        if ($search) {
            $stmt->bindValue(':s',  "%$search%");
            $stmt->bindValue(':s2', "%$search%");
        }
        $stmt->execute();
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $dbError = $e->getMessage();
    }
}

// ── Demo data jika DB kosong ─────────────────────────────────────────────────
if (empty($patients)) {
    $patients = [
        ['patient_id'=>'P001','age'=>72,'bmi'=>18.5,'d1_spo2_min'=>82,'d1_heartrate_min'=>45],
        ['patient_id'=>'P002','age'=>55,'bmi'=>29.8,'d1_spo2_min'=>91,'d1_heartrate_min'=>58],
        ['patient_id'=>'P003','age'=>43,'bmi'=>22.1,'d1_spo2_min'=>94,'d1_heartrate_min'=>62],
        ['patient_id'=>'P004','age'=>68,'bmi'=>35.2,'d1_spo2_min'=>87,'d1_heartrate_min'=>50],
        ['patient_id'=>'P005','age'=>31,'bmi'=>24.5,'d1_spo2_min'=>97,'d1_heartrate_min'=>70],
        ['patient_id'=>'P006','age'=>80,'bmi'=>17.2,'d1_spo2_min'=>79,'d1_heartrate_min'=>38],
        ['patient_id'=>'P007','age'=>48,'bmi'=>31.0,'d1_spo2_min'=>88,'d1_heartrate_min'=>55],
        ['patient_id'=>'P008','age'=>60,'bmi'=>26.3,'d1_spo2_min'=>92,'d1_heartrate_min'=>60],
    ];
}

// ── Calculate ────────────────────────────────────────────────────────────────
$sawEngine = new SAWEngine($patients);
$sawResults = $sawEngine->calculate();
$sawSummary = $sawEngine->getSummary();

$wpEngine  = new WPEngine($patients);
$wpResults = $wpEngine->calculate();
$wpSummary = $wpEngine->getSummary();
$comparison = $wpEngine->compareWithSAW($sawResults);

$ahp = $sawEngine->getAHP();
$ahpDetail = $ahp->getFullDetail();

// Pagination
$totalRows  = count($sawResults);
$totalPages = max(1, ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;
$sawPaged   = array_slice($sawResults, $offset, $perPage);
$wpPaged    = array_slice($wpResults,  $offset, $perPage);
$compPaged  = array_slice($comparison, $offset, $perPage);

// ── Spearman correlation ─────────────────────────────────────────────────────
function spearman(array $comparison): float {
    $n = count($comparison);
    if ($n < 2) return 1.0;
    $d2 = 0;
    foreach ($comparison as $c) {
        if ($c['saw_rank'] === '-') continue;
        $d2 += pow((int)$c['wp_rank'] - (int)$c['saw_rank'], 2);
    }
    return 1 - (6 * $d2) / ($n * ($n * $n - 1));
}
$spearman = round(spearman($comparison), 4);

// ── Persiapan Data Untuk Grafik (Chart.js) ───────────────────────────────────
$chartAHPWeights = array_values($ahpDetail['weights']);

$countKritis = $sawSummary['by_priority']['KRITIS'] ?? 0;
$countTinggi = $sawSummary['by_priority']['TINGGI'] ?? 0;
$countSedang = $sawSummary['by_priority']['SEDANG'] ?? 0;
$countRendah = $sawSummary['by_priority']['RENDAH'] ?? 0;
$chartStatus = [$countKritis, $countTinggi, $countSedang, $countRendah];

$chartLabelsTop10 = [];
$chartSAWTop10 = [];
$chartWPTop10 = [];
$chartScatterData = [];

$loopCount = 0;
foreach($sawResults as $sr) {
    $wpScore = 0;
    foreach($wpResults as $wr) {
        if($wr['patient_id'] == $sr['patient_id']) { 
            $wpScore = $wr['wp_score']; 
            break; 
        }
    }
    
    if($loopCount < 10) {
        $chartLabelsTop10[] = $sr['patient_id'];
        $chartSAWTop10[] = $sr['saw_score'];
        $chartWPTop10[] = $wpScore;
    }
    
    $chartScatterData[] = [
        'x' => (float)$sr['saw_score'], 
        'y' => (float)$wpScore
    ];
    $loopCount++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard – ICU Priority</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Crimson+Pro:ital,wght@0,300;0,600;1,300&display=swap');

  :root {
    --bg:      #0a0e1a;
    --surface: #111827;
    --surface2:#162032;
    --border:  #1e2d45;
    --accent:  #e8303a;
    --blue:    #3b82f6;
    --green:   #22c55e;
    --orange:  #f97316;
    --yellow:  #eab308;
    --text:    #e2e8f0;
    --muted:   #64748b;
  }
  * { margin:0;padding:0;box-sizing:border-box; }
  body { font-family:'Space Grotesk',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }
  body::before {
    content:''; position:fixed; inset:0;
    background-image: linear-gradient(rgba(59,130,246,.03) 1px,transparent 1px),
                      linear-gradient(90deg,rgba(59,130,246,.03) 1px,transparent 1px);
    background-size:40px 40px; pointer-events:none; z-index:0;
  }

  /* NAV */
  nav {
    display:flex;align-items:center;justify-content:space-between;
    padding:1rem 2rem; border-bottom:1px solid var(--border);
    background:rgba(10,14,26,.9); backdrop-filter:blur(12px);
    position:sticky;top:0;z-index:100;
  }
  .nav-logo { display:flex;align-items:center;gap:.7rem; }
  .nav-logo .dot { width:8px;height:8px;background:var(--accent);border-radius:50%;animation:pulse 1.8s infinite; }
  @keyframes pulse { 0%,100%{box-shadow:0 0 0 0 rgba(232,48,58,.6)}50%{box-shadow:0 0 0 7px rgba(232,48,58,0)} }
  .nav-logo span { font-size:.8rem;letter-spacing:.12em;color:var(--muted);text-transform:uppercase; }
  .nav-links { display:flex;gap:1.5rem; }
  .nav-links a { color:var(--muted);text-decoration:none;font-size:.85rem;letter-spacing:.04em;transition:color .2s; }
  .nav-links a:hover,.nav-links a.active { color:var(--text); }

  /* LAYOUT */
  .page { position:relative;z-index:1;padding:2rem; max-width:1400px;margin:0 auto; }
  .page-header { margin-bottom:2rem; }
  .page-header h1 { font-family:'Crimson Pro',serif;font-size:2.4rem;font-weight:300;margin-bottom:.25rem; }
  .page-header p { color:var(--muted);font-size:.9rem; }

  /* STATS CARDS */
  .stats-row { display:grid;grid-template-columns:repeat(5,1fr);gap:1rem;margin-bottom:2rem; }
  .stat-card {
    background:var(--surface);border:1px solid var(--border);
    border-radius:10px;padding:1.2rem 1.5rem;
  }
  .stat-card .sv { font-size:1.8rem;font-weight:700;font-family:'Crimson Pro',serif; }
  .stat-card .sl { font-size:.72rem;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin-top:.2rem; }
  .sv-red    { color:#ef4444; }
  .sv-orange { color:#f97316; }
  .sv-yellow { color:#eab308; }
  .sv-green  { color:#22c55e; }
  .sv-blue   { color:#3b82f6; }

  /* CHART GRID */
  .chart-grid { display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:2rem; }
  .chart-card {
    background:var(--surface);border:1px solid var(--border);
    border-radius:12px;padding:1.5rem;
  }
  .chart-card h3 { font-size:.95rem;font-weight:600;margin-bottom:1rem;color:var(--text);border-bottom:1px solid var(--border);padding-bottom:.5rem; }

  /* SPEARMAN BOX */
  .spearman-box {
    background:var(--surface);border:1px solid var(--border);
    border-radius:10px;padding:1.5rem 2rem;margin-bottom:1.5rem;
  }
  .spearman-val { font-size:2.5rem;font-family:'Crimson Pro',serif;font-weight:600;color:var(--blue); }
  .corr-bar { height:8px;background:var(--border);border-radius:4px;margin-top:.5rem; }
  .corr-fill { height:100%;background:linear-gradient(90deg,var(--blue),var(--green));border-radius:4px; }

  /* TABS */
  .tabs { display:flex;gap:.5rem;margin-bottom:1.5rem;border-bottom:1px solid var(--border);padding-bottom:0; }
  .tab {
    padding:.6rem 1.4rem;font-size:.85rem;letter-spacing:.04em;cursor:pointer;
    border-bottom:2px solid transparent;color:var(--muted);background:none;border-top:none;border-left:none;border-right:none;
    font-family:inherit;transition:all .2s;
  }
  .tab:hover { color:var(--text); }
  .tab.active { color:var(--text);border-bottom-color:var(--blue); }

  /* TABLE */
  .card {
    background:var(--surface);border:1px solid var(--border);
    border-radius:12px;overflow:hidden;
  }
  .card-header {
    display:flex;align-items:center;justify-content:space-between;
    padding:1rem 1.5rem;border-bottom:1px solid var(--border);
  }
  .card-header h3 { font-size:.95rem;font-weight:600; }
  .card-header .meta { font-size:.78rem;color:var(--muted); }

  .tbl-wrap { overflow-x:auto; }
  table { width:100%;border-collapse:collapse;font-size:.82rem; }
  th {
    text-align:left;padding:.75rem 1rem;
    background:var(--surface2);
    color:var(--muted);font-size:.7rem;letter-spacing:.1em;text-transform:uppercase;
    border-bottom:1px solid var(--border);
    white-space:nowrap;
  }
  td {
    padding:.7rem 1rem;border-bottom:1px solid var(--border);
    white-space:nowrap;
  }
  tr:last-child td { border-bottom:none; }
  tr:hover td { background:rgba(59,130,246,.04); }

  .badge {
    display:inline-block;padding:.2rem .65rem;border-radius:4px;
    font-size:.68rem;font-weight:700;letter-spacing:.08em;
  }
  .b-kritis { background:rgba(239,68,68,.15);color:#ef4444; }
  .b-tinggi { background:rgba(249,115,22,.15);color:#f97316; }
  .b-sedang { background:rgba(234,179,8,.15);color:#eab308; }
  .b-rendah { background:rgba(34,197,94,.15);color:#22c55e; }

  .rank-num {
    display:inline-flex;align-items:center;justify-content:center;
    width:28px;height:28px;border-radius:50%;
    background:var(--border);font-size:.78rem;font-weight:700;
  }
  .rank-1 { background:rgba(255,215,0,.2);color:gold; }
  .rank-2 { background:rgba(192,192,192,.2);color:silver; }
  .rank-3 { background:rgba(205,127,50,.2);color:#cd7f32; }

  .diff-same { color:var(--green); font-weight:600; }
  .diff-1    { color:var(--yellow); }
  .diff-big  { color:var(--accent); }

  /* PAGINATION */
  .pagination {
    display:flex;align-items:center;justify-content:center;
    gap:.5rem;padding:1rem;
  }
  .pag-btn {
    padding:.4rem .85rem;border:1px solid var(--border);border-radius:6px;
    background:none;color:var(--muted);font-size:.8rem;cursor:pointer;
    font-family:inherit;transition:all .2s;text-decoration:none;
    display:inline-block;
  }
  .pag-btn:hover,.pag-btn.active { background:var(--blue);color:#fff;border-color:var(--blue); }
  .pag-btn.disabled { opacity:.3;pointer-events:none; }

  /* SEARCH */
  .toolbar {
    display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;
  }
  .search-box {
    display:flex;align-items:center;gap:.5rem;
    background:var(--surface);border:1px solid var(--border);
    border-radius:8px;padding:.5rem 1rem;flex:1;max-width:320px;
  }
  .search-box input {
    background:none;border:none;outline:none;color:var(--text);
    font-family:inherit;font-size:.85rem;width:100%;
  }
  .search-box input::placeholder { color:var(--muted); }

  .method-toggle { display:flex;gap:.5rem; }
  .toggle-btn {
    padding:.45rem 1.1rem;border:1px solid var(--border);border-radius:6px;
    background:none;color:var(--muted);font-size:.8rem;cursor:pointer;
    font-family:inherit;text-decoration:none;transition:all .2s;
  }
  .toggle-btn:hover,.toggle-btn.act { background:var(--blue);color:#fff;border-color:var(--blue); }

  @media(max-width:900px){
    .stats-row{grid-template-columns:repeat(2,1fr);}
    .chart-grid{grid-template-columns:1fr;}
    nav .nav-links{display:none;}
    .page{padding:1rem;}
  }
</style>

<!-- jQuery & DataTables CSS/JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<nav>
  <div class="nav-logo"><div class="dot"></div><span>ICU Priority</span></div>
  <div class="nav-links">
    <a href="index.php">Beranda</a>
    <a href="dashboard.php" class="active">Dashboard</a>
    <a href="detail_ahp.php">Detail AHP</a>
  </div>
</nav>

<div class="page">
  <div class="page-header">
    <h1>Dashboard Prioritas ICU</h1>
    <p>Perbandingan ranking AHP+SAW vs AHP+WP &nbsp;·&nbsp; <?= count($patients) ?> pasien dimuat &nbsp;·&nbsp; <?= $pdo ? 'Database: Terhubung' : 'Mode Demo (DB tidak terhubung)' ?></p>
    <?php if ($dbError): ?>
      <p style="color:var(--orange);font-size:.8rem;margin-top:.3rem">⚠ <?= htmlspecialchars($dbError) ?></p>
    <?php endif; ?>
  </div>

  <!-- SPEARMAN CORRELATION -->
  <div class="spearman-box">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:2rem;flex-wrap:wrap">
      <div>
        <div style="font-size:.75rem;color:var(--muted);letter-spacing:.1em;text-transform:uppercase;margin-bottom:.3rem">Korelasi Spearman (SAW vs WP)</div>
        <div class="spearman-val"><?= $spearman ?></div>
        <div class="corr-bar" style="width:200px">
          <div class="corr-fill" style="width:<?= abs($spearman)*100 ?>%"></div>
        </div>
        <div style="font-size:.8rem;color:var(--muted);margin-top:.4rem">
          <?php if ($spearman >= 0.9): ?>
            Kedua metode menghasilkan ranking yang sangat konsisten
          <?php elseif ($spearman >= 0.7): ?>
            ✔ Korelasi kuat — hasil ranking cukup serupa
          <?php else: ?>
            ⚠ Perbedaan signifikan antara SAW dan WP
          <?php endif; ?>
        </div>
      </div>
      <div style="display:flex;gap:2rem;flex-wrap:wrap">
        <div>
          <div style="font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.08em">Bobot AHP</div>
          <?php foreach ($ahpDetail['criteria'] as $i => $c): ?>
            <div style="margin-top:.3rem;font-size:.83rem">
              <span style="color:var(--muted)"><?= $ahpDetail['criteria_labels'][$i] ?>:</span>
              <strong><?= round($ahpDetail['weights'][$c]*100, 1) ?>%</strong>
            </div>
          <?php endforeach; ?>
        </div>
        <div>
          <div style="font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.08em">Konsistensi AHP</div>
          <div style="margin-top:.3rem;font-size:.83rem">CR = <strong style="color:<?= $ahpDetail['is_consistent']?'var(--green)':'var(--accent)' ?>"><?= round($ahpDetail['consistency_ratio'], 4) ?></strong></div>
          <div style="font-size:.78rem;color:var(--muted)"><?= $ahpDetail['is_consistent'] ? 'Konsisten (CR < 0.1)' : '⚠ Tidak konsisten' ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- STATS -->
  <div class="stats-row">
    <div class="stat-card"><div class="sv sv-red"><?= $sawSummary['by_priority']['KRITIS'] ?? 0 ?></div><div class="sl">Kritis (SAW)</div></div>
    <div class="stat-card"><div class="sv sv-orange"><?= $sawSummary['by_priority']['TINGGI'] ?? 0 ?></div><div class="sl">Tinggi (SAW)</div></div>
    <div class="stat-card"><div class="sv sv-red"><?= $wpSummary['by_priority']['KRITIS'] ?? 0 ?></div><div class="sl">Kritis (WP)</div></div>
    <div class="stat-card"><div class="sv sv-orange"><?= $wpSummary['by_priority']['TINGGI'] ?? 0 ?></div><div class="sl">Tinggi (WP)</div></div>
    <div class="stat-card"><div class="sv sv-blue"><?= count($patients) ?></div><div class="sl">Total Pasien</div></div>
  </div>

  <!-- CHARTS SECTION -->
  <div class="chart-grid">
    <!-- Radar Chart AHP -->
    <div class="chart-card">
      <h3>Visualisasi Bobot Kriteria (AHP)</h3>
      <div style="height: 250px; display: flex; justify-content: center;">
        <canvas id="radarChart"></canvas>
      </div>
    </div>
    
    <!-- Donut Chart Status -->
    <div class="chart-card">
      <h3>Distribusi Status Pasien (Berdasarkan SAW)</h3>
      <div style="height: 250px; display: flex; justify-content: center;">
        <canvas id="donutChart"></canvas>
      </div>
    </div>

    <!-- Bar Chart Top 10 -->
    <div class="chart-card">
      <h3>Top 10 Pasien Prioritas (Skor SAW vs WP)</h3>
      <div style="height: 250px;">
        <canvas id="barChart"></canvas>
      </div>
    </div>

    <!-- Scatter Plot Korelasi -->
    <div class="chart-card">
      <h3>Peta Korelasi Skor (SAW vs WP)</h3>
      <div style="height: 250px;">
        <canvas id="scatterChart"></canvas>
      </div>
    </div>
  </div>

  <!-- TOOLBAR -->
  <div class="toolbar">
    <form method="GET" style="display:contents">
      <div class="search-box">
        <span style="color:var(--muted)">🔍</span>
        <input type="text" name="search" placeholder="Cari patient ID..." value="<?= htmlspecialchars($search) ?>" onchange="this.form.submit()">
        <input type="hidden" name="method" value="<?= $method ?>">
        <input type="hidden" name="page" value="1">
      </div>
    </form>
    <div class="method-toggle">
      <a href="?method=both&page=1" class="toggle-btn <?= $method==='both'?'act':'' ?>">Keduanya</a>
      <a href="?method=saw&page=1"  class="toggle-btn <?= $method==='saw'?'act':'' ?>">SAW</a>
      <a href="?method=wp&page=1"   class="toggle-btn <?= $method==='wp'?'act':'' ?>">WP</a>
      <a href="?method=compare&page=1" class="toggle-btn <?= $method==='compare'?'act':'' ?>">Perbandingan</a>
    </div>
    <div style="margin-left:auto;font-size:.8rem;color:var(--muted)">
      Hal <?= $page ?>/<?= $totalPages ?> &nbsp;(<?= $totalRows ?> hasil)
    </div>
  </div>

  <!-- TABLE CONTENT -->
  <?php if ($method === 'both' || $method === 'saw'): ?>
  <div class="card" style="margin-bottom:1.5rem">
    <div class="card-header">
      <h3>Ranking AHP + SAW</h3>
      <span class="meta">Skor tertinggi = prioritas pertama</span>
    </div>
    <div class="tbl-wrap">
      <table class="datatable-init" style="width:100%">
        <thead>
          <tr>
            <th>Rank</th><th>Patient ID</th><th>Usia</th><th>BMI</th>
            <th>SpO2 Min</th><th>HR Min</th><th>SAW Score</th><th>Prioritas</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($sawPaged as $r): ?>
          <tr>
            <td>
              <span class="rank-num <?= $r['rank']<=3 ? 'rank-'.$r['rank'] : '' ?>"><?= $r['rank'] ?></span>
            </td>
            <td><code><?= htmlspecialchars($r['patient_id']) ?></code></td>
            <td><?= $r['age'] ?></td>
            <td><?= $r['bmi'] ?></td>
            <td><?= $r['d1_spo2_min'] ?></td>
            <td><?= $r['d1_heartrate_min'] ?></td>
            <td><strong><?= $r['saw_score'] ?></strong></td>
            <td>
              <?php $p = strtolower($r['priority']); ?>
              <span class="badge b-<?= $p ?>"><?= $r['priority'] ?></span>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= paginationHTML($page, $totalPages, $method) ?>
  </div>
  <?php endif; ?>

  <?php if ($method === 'both' || $method === 'wp'): ?>
  <div class="card" style="margin-bottom:1.5rem">
    <div class="card-header">
      <h3>Ranking AHP + WP</h3>
      <span class="meta">V(i) = S(i) / Σ S(k)</span>
    </div>
    <div class="tbl-wrap">
      <table class="datatable-init" style="width:100%">
        <thead>
          <tr>
            <th>Rank</th><th>Patient ID</th><th>Usia</th><th>BMI</th>
            <th>SpO2 Min</th><th>HR Min</th><th>S(i)</th><th>WP Score V(i)</th><th>Prioritas</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($wpPaged as $r): ?>
          <tr>
            <td>
              <span class="rank-num <?= $r['rank']<=3 ? 'rank-'.$r['rank'] : '' ?>"><?= $r['rank'] ?></span>
            </td>
            <td><code><?= htmlspecialchars($r['patient_id']) ?></code></td>
            <td><?= $r['age'] ?></td>
            <td><?= $r['bmi'] ?></td>
            <td><?= $r['d1_spo2_min'] ?></td>
            <td><?= $r['d1_heartrate_min'] ?></td>
            <td style="color:var(--muted);font-size:.78rem"><?= $r['s_value'] ?></td>
            <td><strong><?= $r['wp_score'] ?></strong></td>
            <td>
              <?php $p = strtolower($r['priority']); ?>
              <span class="badge b-<?= $p ?>"><?= $r['priority'] ?></span>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= paginationHTML($page, $totalPages, $method) ?>
  </div>
  <?php endif; ?>

  <?php if ($method === 'compare'): ?>
  <div class="card">
    <div class="card-header">
      <h3>Perbandingan Ranking SAW vs WP</h3>
      <span class="meta">Spearman ρ = <?= $spearman ?></span>
    </div>
    <div class="tbl-wrap">
      <table class="datatable-init" style="width:100%">
        <thead>
          <tr>
            <th>Patient ID</th>
            <th>Rank SAW</th>
            <th>Rank WP</th>
            <th>Selisih</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($compPaged as $c): ?>
          <tr>
            <td><code><?= htmlspecialchars($c['patient_id']) ?></code></td>
            <td><?= $c['saw_rank'] ?></td>
            <td><?= $c['wp_rank'] ?></td>
            <td>
              <?php $d = $c['rank_diff']; ?>
              <span class="<?= $d===0?'diff-same':($d<=2?'diff-1':'diff-big') ?>">
                <?= $d === 0 ? '=' : ($d==='-'?'-':'±'.$d) ?>
              </span>
            </td>
            <td>
              <?php if ($c['same_rank']): ?>
                <span style="color:var(--green);font-size:.8rem">✓ Sama</span>
              <?php else: ?>
                <span style="color:var(--muted);font-size:.8rem">Berbeda</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= paginationHTML($page, $totalPages, $method) ?>
  </div>
  <?php endif; ?>

</div>

<?php
function paginationHTML(int $page, int $total, string $method): string {
    if ($total <= 1) return '';
    $html = '<div class="pagination">';
    $html .= '<a class="pag-btn '.($page<=1?'disabled':'').'" href="?method='.$method.'&page='.($page-1).'">← Prev</a>';
    for ($i = max(1,$page-2); $i <= min($total,$page+2); $i++) {
        $html .= '<a class="pag-btn '.($i==$page?'active':'').'" href="?method='.$method.'&page='.$i.'">'.$i.'</a>';
    }
    $html .= '<a class="pag-btn '.($page>=$total?'disabled':'').'" href="?method='.$method.'&page='.($page+1).'">Next →</a>';
    $html .= '</div>';
    return $html;
}
?>

<!-- SCRIPT INISIALISASI -->
<script>
// ── 1. INISIALISASI DATATABLES ───────────────────────────────────────────────
$(document).ready(function() {
    $('.datatable-init').DataTable({
        "pageLength": <?= $perPage ?>,
        "lengthMenu": [5, 10, 25, 50, 100],
        "paging": false,
        "searching": false,
        "info": false,
        "language": {
            "paginate": {
                "next": "Lanjut →",
                "previous": "← Kembali"
            }
        }
    });
});

// ── 2. INISIALISASI CHART.JS ─────────────────────────────────────────────────
const style = getComputedStyle(document.body);
const cBlue   = style.getPropertyValue('--blue').trim();
const cAccent = style.getPropertyValue('--accent').trim();
const cGreen  = style.getPropertyValue('--green').trim();
const cOrange = style.getPropertyValue('--orange').trim();
const cYellow = style.getPropertyValue('--yellow').trim();
const cMuted  = style.getPropertyValue('--muted').trim();

Chart.defaults.font.family = "'Space Grotesk', sans-serif";
Chart.defaults.color = cMuted;

const ahpWeightsData = <?= json_encode($chartAHPWeights) ?>;
const statusData = <?= json_encode($chartStatus) ?>;
const labelsTop10 = <?= json_encode($chartLabelsTop10) ?>;
const sawTop10 = <?= json_encode($chartSAWTop10) ?>;
const wpTop10 = <?= json_encode($chartWPTop10) ?>;
const scatterData = <?= json_encode($chartScatterData) ?>;

// A. Radar Chart (AHP)
new Chart(document.getElementById('radarChart'), {
    type: 'radar',
    data: {
        labels: ['Usia', 'BMI', 'SpO2 Min', 'HR Min'],
        datasets: [{
            label: 'Bobot AHP',
            data: ahpWeightsData.map(v => v * 100),
            backgroundColor: 'rgba(201, 206, 216, 0.15)',
            borderColor: cBlue,
            pointBackgroundColor: cBlue,
            borderWidth: 2
        }]
    },
    options: {
        scales: { r: { beginAtZero: true, max: 60, ticks: { display: false } } },
        plugins: { legend: { display: false } }
    }
});

// B. Donut Chart (Status)
new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    data: {
        labels: ['Kritis', 'Tinggi', 'Sedang', 'Rendah'],
        datasets: [{
            data: statusData,
            backgroundColor: [cAccent, cOrange, cYellow, cGreen],
            borderWidth: 0,
            hoverOffset: 4
        }]
    },
    options: {
        cutout: '70%',
        plugins: { legend: { position: 'bottom' } }
    }
});

// C. Bar Chart (Top 10) - Dual Y-Axis
new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: {
        labels: labelsTop10,
        datasets: [
            { 
                label: 'Skor SAW', 
                data: sawTop10, 
                backgroundColor: cBlue, 
                borderRadius: 4,
                yAxisID: 'y'
            },
            { 
                label: 'Skor WP', 
                data: wpTop10, 
                backgroundColor: cMuted, 
                borderRadius: 4,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true, 
        maintainAspectRatio: false,
        scales: { 
            y: { 
                type: 'linear',
                display: true,
                position: 'left',
                beginAtZero: false,
                title: { display: true, text: 'Nilai SAW', font: {size: 11} }
            },
            y1: { 
                type: 'linear',
                display: true,
                position: 'right',
                beginAtZero: true,
                title: { display: true, text: 'Nilai WP', font: {size: 11} },
                grid: { drawOnChartArea: false }
            }
        },
        plugins: { legend: { position: 'top' } }
    }
});

// D. Scatter Plot (Korelasi)
new Chart(document.getElementById('scatterChart'), {
    type: 'scatter',
    data: {
        datasets: [{
            label: 'Sebaran Pasien',
            data: scatterData,
            backgroundColor: 'rgba(239, 68, 68, 0.6)',
            borderColor: cAccent,
            pointRadius: 4,
            pointHoverRadius: 7
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        scales: {
            x: { title: { display: true, text: 'Skor SAW' } },
            y: { title: { display: true, text: 'Skor WP' } }
        },
        plugins: { legend: { display: false }, tooltip: {
            callbacks: {
                label: function(ctx) { return 'SAW: ' + ctx.parsed.x + ', WP: ' + ctx.parsed.y; }
            }
        }}
    }
});
</script>
</body>
</html>