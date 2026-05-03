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

// ── Pengaturan Tab (Method) ──────────────────────────────────────────────────
$method   = $_GET['method'] ?? 'both';   // saw | wp | both | compare

// ── Load patients ────────────────────────────────────────────────────────────
$patients = [];
if ($pdo) {
    try {
        $sql   = "SELECT patient_id, age, bmi, d1_spo2_min, d1_heartrate_min
                  FROM dataset_akumulasi
                  ORDER BY patient_id
                  LIMIT 200"; 
        $stmt = $pdo->prepare($sql);
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

<!-- Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Crimson+Pro:ital,wght@0,300;0,600;1,300&display=swap" rel="stylesheet">

<!-- jQuery & DataTables CSS/JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
  :root {
    --bg:      #f8fafc;
    --surface: #ffffff;
    --surface2:#f1f5f9;
    --border:  #e2e8f0;
    --accent:  #ef4444;
    --blue:    #2563eb;
    --green:   #16a34a;
    --orange:  #ea580c;
    --yellow:  #ca8a04;
    --text:    #0f172a;
    --muted:   #64748b;
  }
  
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'Space Grotesk',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }
  
  /* NAV */
  nav {
    display:flex; align-items:center; justify-content:space-between;
    padding:1rem 2rem; border-bottom:1px solid var(--border);
    background:rgba(255,255,255,0.95); backdrop-filter:blur(12px);
    position:sticky; top:0; z-index:100; box-shadow:0 1px 3px rgba(0,0,0,0.05);
  }
  .nav-logo { display:flex; align-items:center; gap:.7rem; font-weight:700; color:var(--text); text-transform:uppercase; letter-spacing:1px;}
  .nav-logo .dot { width:8px; height:8px; background:var(--accent); border-radius:50%; }
  .nav-links { display:flex; gap:1.5rem; }
  .nav-links a { color:var(--muted); text-decoration:none; font-size:.85rem; font-weight:500; transition:color .2s; }
  .nav-links a:hover, .nav-links a.active { color:var(--blue); }

  /* LAYOUT */
  .page { padding:2rem; max-width:1400px; margin:0 auto; }
  .page-header { margin-bottom:2rem; }
  .page-header h1 { font-family:'Crimson Pro',serif; font-size:2.4rem; font-weight:600; margin-bottom:.25rem; color:var(--text); }
  .page-header p { color:var(--muted); font-size:.9rem; }

  /* STATS CARDS */
  .stats-row { display:grid; grid-template-columns:repeat(5,1fr); gap:1rem; margin-bottom:1.5rem; }
  .stat-card {
    background:var(--surface); border:1px solid var(--border);
    border-radius:10px; padding:1.2rem 1.5rem; box-shadow:0 2px 4px rgba(0,0,0,0.02);
  }
  .stat-card .sv { font-size:1.8rem; font-weight:700; font-family:'Crimson Pro',serif; }
  .stat-card .sl { font-size:.72rem; color:var(--muted); letter-spacing:.08em; text-transform:uppercase; margin-top:.2rem; font-weight: 600;}
  .sv-red { color:var(--accent); } .sv-orange { color:var(--orange); } .sv-blue { color:var(--blue); }

  /* CHART GRID */
  .chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
  .chart-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 12px; padding: 1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.03);
  }
  .chart-card h3 { font-size: .95rem; font-weight: 600; margin-bottom: 1rem; color: var(--text); border-bottom: 1px solid var(--border); padding-bottom: .5rem;}

  /* SPEARMAN BOX */
  .spearman-box {
    background:var(--surface); border:1px solid var(--border);
    border-radius:10px; padding:1.5rem 2rem; margin-bottom:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.03);
  }
  .spearman-val { font-size:2.5rem; font-family:'Crimson Pro',serif; font-weight:600; color:var(--blue); }
  .corr-bar { height:8px; background:var(--border); border-radius:4px; margin-top:.5rem; overflow:hidden;}
  .corr-fill { height:100%; background:linear-gradient(90deg, var(--blue), var(--green)); border-radius:4px; }

  /* TABLE & TABS */
  .card { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.03); padding: 1.5rem;}
  .card-header { margin-bottom:1rem; border-bottom:1px solid var(--border); padding-bottom:.5rem; }
  .card-header h3 { font-size:1.1rem; font-weight:600; color:var(--text);}
  .card-header .meta { font-size:.85rem; color:var(--muted); }

  .method-toggle { display:flex; gap:.5rem; margin-bottom: 1.5rem; }
  .toggle-btn {
    padding:.5rem 1.2rem; border:1px solid var(--border); border-radius:6px; background:var(--surface);
    color:var(--text); font-size:.85rem; cursor:pointer; text-decoration:none; transition:all .2s; font-weight: 500;
  }
  .toggle-btn:hover, .toggle-btn.act { background:var(--blue); color:#fff; border-color:var(--blue); }

  /* BADGES DI SESUAIKAN DENGAN VARIABLE WARNA */
  .badge { display:inline-block; padding:.3rem .6rem; border-radius:4px; font-size:.7rem; font-weight:700; letter-spacing:.05em; }
  .b-kritis { background:rgba(239, 68, 68, 0.15); color:var(--accent); }
  .b-tinggi { background:rgba(234, 88, 12, 0.15); color:var(--orange); }
  .b-sedang { background:rgba(202, 138, 4, 0.15); color:var(--yellow); }
  .b-rendah { background:rgba(22, 163, 74, 0.15); color:var(--green); }
  
  .rank-num {
    display:inline-flex; align-items:center; justify-content:center;
    width:28px; height:28px; border-radius:50%; background:var(--surface2);
    font-size:.78rem; font-weight:700; border: 1px solid var(--border); color:var(--text);
  }
  .rank-1 { background:rgba(202, 138, 4, 0.15); border-color:var(--yellow); color:var(--yellow); }
  .rank-2 { background:var(--surface2); border-color:var(--muted); color:var(--muted); }
  .rank-3 { background:rgba(234, 88, 12, 0.15); border-color:var(--orange); color:var(--orange); }
  
  .diff-same { color:var(--green); font-weight:600; }
  .diff-1    { color:var(--yellow); font-weight:600;}
  .diff-big  { color:var(--accent); font-weight:600;}

  /* DATATABLES CUSTOMIZATION */
  .dataTables_wrapper .dataTables_filter input { border: 1px solid var(--border); border-radius: 6px; padding: 4px 8px; outline:none; color:var(--text); background:var(--surface);}
  .dataTables_wrapper .dataTables_length select { border: 1px solid var(--border); border-radius: 4px; padding: 2px; color:var(--text); background:var(--surface);}
  .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { color: var(--muted) !important; font-size: .85rem;}
  table.dataTable thead th { border-bottom: 2px solid var(--border); color: var(--muted); font-size: .75rem; text-transform: uppercase; letter-spacing:.05em; }
  table.dataTable tbody td { border-bottom: 1px solid var(--border); font-size: .85rem; vertical-align: middle; color:var(--text);}
</style>
</head>
<body>

<nav>
  <div class="nav-logo"><div class="dot"></div><span>ICU Priority DSS</span></div>
  <div class="nav-links">
    <a href="index.php">Beranda</a>
    <a href="dashboard.php" class="active">Dashboard</a>
    <a href="detail_ahp.php">Detail AHP</a>
  </div>
</nav>

<div class="page">
  <div class="page-header">
    <h1>Dashboard Analitik Prioritas ICU</h1>
    <p>Visualisasi Perbandingan AHP+SAW vs AHP+WP &nbsp;·&nbsp; <?= count($patients) ?> pasien dimuat &nbsp;·&nbsp; <?= $pdo ? '<span style="color:var(--green)">Database Terhubung ✓</span>' : '<span style="color:var(--orange)">Mode Demo</span>' ?></p>
    <?php if ($dbError): ?>
      <p style="color:var(--accent);font-size:.8rem;margin-top:.3rem">⚠ <?= htmlspecialchars($dbError) ?></p>
    <?php endif; ?>
  </div>

  <!-- STATS -->
  <div class="stats-row">
    <div class="stat-card"><div class="sv sv-red"><?= $sawSummary['by_priority']['KRITIS'] ?? 0 ?></div><div class="sl">Kritis (SAW)</div></div>
    <div class="stat-card"><div class="sv sv-orange"><?= $sawSummary['by_priority']['TINGGI'] ?? 0 ?></div><div class="sl">Tinggi (SAW)</div></div>
    <div class="stat-card"><div class="sv sv-red"><?= $wpSummary['by_priority']['KRITIS'] ?? 0 ?></div><div class="sl">Kritis (WP)</div></div>
    <div class="stat-card"><div class="sv sv-orange"><?= $wpSummary['by_priority']['TINGGI'] ?? 0 ?></div><div class="sl">Tinggi (WP)</div></div>
    <div class="stat-card"><div class="sv sv-blue"><?= count($patients) ?></div><div class="sl">Total Pasien</div></div>
  </div>

  <!-- SPEARMAN CORRELATION & AHP SUMMARY -->
  <div class="spearman-box">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:2rem;flex-wrap:wrap">
      <div>
        <div style="font-size:.75rem;color:var(--muted);letter-spacing:.1em;text-transform:uppercase;margin-bottom:.3rem">Korelasi Spearman (SAW vs WP)</div>
        <div class="spearman-val"><?= $spearman ?></div>
        <div class="corr-bar" style="width:200px">
          <div class="corr-fill" style="width:<?= abs($spearman)*100 ?>%"></div>
        </div>
        <div style="font-size:.85rem;color:var(--text);margin-top:.4rem;font-weight:500;">
          <?php if ($spearman >= 0.9): ?>
            Kedua metode menghasilkan ranking yang sangat konsisten
          <?php elseif ($spearman >= 0.7): ?>
            ✔ Korelasi kuat — hasil ranking cukup serupa
          <?php else: ?>
            ⚠ Perbedaan signifikan antara SAW dan WP
          <?php endif; ?>
        </div>
      </div>
      <div style="display:flex;gap:3rem;flex-wrap:wrap">
        <div>
          <div style="font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.3rem;">Bobot AHP</div>
          <?php foreach ($ahpDetail['criteria'] as $i => $c): ?>
            <div style="margin-top:.2rem;font-size:.85rem">
              <span style="color:var(--muted);display:inline-block;width:70px;"><?= $ahpDetail['criteria_labels'][$i] ?></span>
              <strong><?= round($ahpDetail['weights'][$c]*100, 1) ?>%</strong>
            </div>
          <?php endforeach; ?>
        </div>
        <div>
          <div style="font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.3rem;">Konsistensi AHP</div>
          <div style="font-size:.85rem">CR = <strong style="color:<?= $ahpDetail['is_consistent']?'var(--green)':'var(--accent)' ?>"><?= round($ahpDetail['consistency_ratio'], 4) ?></strong></div>
          <div style="font-size:.8rem;color:var(--muted);margin-top:.2rem;"><?= $ahpDetail['is_consistent'] ? 'Konsisten (CR < 0.1)' : '⚠ Tidak konsisten' ?></div>
        </div>
      </div>
    </div>
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

  <!-- TABS METHOD -->
  <div class="method-toggle">
    <a href="?method=both" class="toggle-btn <?= $method==='both'?'act':'' ?>">Keduanya</a>
    <a href="?method=saw"  class="toggle-btn <?= $method==='saw'?'act':'' ?>">Tabel SAW</a>
    <a href="?method=wp"   class="toggle-btn <?= $method==='wp'?'act':'' ?>">Tabel WP</a>
    <a href="?method=compare" class="toggle-btn <?= $method==='compare'?'act':'' ?>">Perbandingan Detail</a>
  </div>

  <!-- TABLE CONTENT -->
  <?php if ($method === 'both' || $method === 'saw'): ?>
  <div class="card" style="margin-bottom:1.5rem">
    <div class="card-header">
      <h3>Ranking AHP + SAW</h3>
      <span class="meta">Skor tertinggi = prioritas pertama</span>
    </div>
    <table class="datatable-init display" style="width:100%">
      <thead>
        <tr>
          <th>Rank</th><th>Patient ID</th><th>Usia</th><th>BMI</th>
          <th>SpO2 Min</th><th>HR Min</th><th>Skor SAW</th><th>Prioritas</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($sawResults as $r): ?>
        <tr>
          <td><span class="rank-num <?= $r['rank']<=3 ? 'rank-'.$r['rank'] : '' ?>"><?= $r['rank'] ?></span></td>
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
  <?php endif; ?>

  <?php if ($method === 'both' || $method === 'wp'): ?>
  <div class="card" style="margin-bottom:1.5rem">
    <div class="card-header">
      <h3>Ranking AHP + WP</h3>
      <span class="meta">V(i) = S(i) / Σ S(k)</span>
    </div>
    <table class="datatable-init display" style="width:100%">
      <thead>
        <tr>
          <th>Rank</th><th>Patient ID</th><th>Usia</th><th>BMI</th>
          <th>SpO2 Min</th><th>HR Min</th><th>S(i)</th><th>Skor WP V(i)</th><th>Prioritas</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($wpResults as $r): ?>
        <tr>
          <td><span class="rank-num <?= $r['rank']<=3 ? 'rank-'.$r['rank'] : '' ?>"><?= $r['rank'] ?></span></td>
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
  <?php endif; ?>

  <?php if ($method === 'compare'): ?>
  <div class="card">
    <div class="card-header">
      <h3>Perbandingan Ranking SAW vs WP</h3>
      <span class="meta">Menampilkan gap / selisih peringkat</span>
    </div>
    <table class="datatable-init display" style="width:100%">
      <thead>
        <tr>
          <th>Patient ID</th>
          <th>Rank SAW</th>
          <th>Rank WP</th>
          <th>Selisih Peringkat</th>
          <th>Status Konsistensi</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($comparison as $c): ?>
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
              <span class="badge b-rendah">✓ SAMA</span>
            <?php else: ?>
              <span class="badge b-tinggi">BERBEDA</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>

<!-- SCRIPT INISIALISASI -->
<script>
// ── 1. INISIALISASI DATATABLES ───────────────────────────────────────────────
$(document).ready(function() {
    $('.datatable-init').DataTable({
        "pageLength": 10,
        "lengthMenu": [5, 10, 25, 50, 100],
        "language": {
            "search": "Cari Data:",
            "lengthMenu": "Tampilkan _MENU_ baris",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ pasien",
            "infoEmpty": "Tidak ada data tersedia",
            "zeroRecords": "Data tidak ditemukan",
            "paginate": {
                "first": "Awal",
                "last": "Akhir",
                "next": "Lanjut →",
                "previous": "← Kembali"
            }
        }
    });
});

// ── 2. INISIALISASI CHART.JS DENGAN SINKRONISASI CSS VARIABLE ─────────────────
// Trik agar JS membaca warna langsung dari file CSS kamu (sangat dinamis)
const style = getComputedStyle(document.body);
const cBlue   = style.getPropertyValue('--blue').trim();
const cAccent = style.getPropertyValue('--accent').trim();
const cGreen  = style.getPropertyValue('--green').trim();
const cOrange = style.getPropertyValue('--orange').trim();
const cYellow = style.getPropertyValue('--yellow').trim();
const cMuted  = style.getPropertyValue('--muted').trim();

Chart.defaults.font.family = "'Space Grotesk', sans-serif";
Chart.defaults.color = cMuted;

// Ambil data JSON dari PHP
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
            data: ahpWeightsData.map(v => v * 100), // Konversi ke %
            backgroundColor: 'rgba(37, 99, 235, 0.15)', // Light transparan blue
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

// C. Bar Chart (Top 10)
new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: {
        labels: labelsTop10,
        datasets: [
            { label: 'Skor SAW', data: sawTop10, backgroundColor: cBlue, borderRadius: 4 },
            { label: 'Skor WP', data: wpTop10, backgroundColor: cMuted, borderRadius: 4 }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        scales: { y: { beginAtZero: false, min: 0.5 } },
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
            backgroundColor: 'rgba(239, 68, 68, 0.6)', // Accent transparent
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