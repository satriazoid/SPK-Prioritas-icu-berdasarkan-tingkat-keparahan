<?php
require_once __DIR__ . '/core/ahp_loader.php';
$ahp = new AHPLoader();
$d   = $ahp->getFullDetail();
$labels = $d['criteria_labels'];
$n = count($d['criteria']);

// Skala Saaty referensi
$saatyScale = [
    1=>'Sama penting',2=>'Di antara 1 dan 3',3=>'Sedikit lebih penting',
    4=>'Di antara 3 dan 5',5=>'Lebih penting',6=>'Di antara 5 dan 7',
    7=>'Sangat lebih penting',8=>'Di antara 7 dan 9',9=>'Mutlak lebih penting',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail AHP – ICU Priority</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Crimson+Pro:ital,wght@0,300;0,600;1,300&display=swap');

  :root {
    --bg:#0a0e1a;--surface:#111827;--surface2:#162032;
    --border:#1e2d45;--accent:#e8303a;--blue:#3b82f6;
    --green:#22c55e;--orange:#f97316;--yellow:#eab308;
    --text:#e2e8f0;--muted:#64748b;
  }
  *{margin:0;padding:0;box-sizing:border-box;}
  body{font-family:'Space Grotesk',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
  body::before{
    content:'';position:fixed;inset:0;
    background-image:linear-gradient(rgba(59,130,246,.03)1px,transparent 1px),
                     linear-gradient(90deg,rgba(59,130,246,.03)1px,transparent 1px);
    background-size:40px 40px;pointer-events:none;z-index:0;
  }

  nav{
    display:flex;align-items:center;justify-content:space-between;
    padding:1rem 2rem;border-bottom:1px solid var(--border);
    background:rgba(10,14,26,.9);backdrop-filter:blur(12px);
    position:sticky;top:0;z-index:100;
  }
  .nav-logo{display:flex;align-items:center;gap:.7rem;}
  .dot{width:8px;height:8px;background:var(--accent);border-radius:50%;animation:pulse 1.8s infinite;}
  @keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(232,48,58,.6)}50%{box-shadow:0 0 0 7px rgba(232,48,58,0)}}
  .nav-logo span{font-size:.8rem;letter-spacing:.12em;color:var(--muted);text-transform:uppercase;}
  .nav-links{display:flex;gap:1.5rem;}
  .nav-links a{color:var(--muted);text-decoration:none;font-size:.85rem;transition:color .2s;}
  .nav-links a:hover,.nav-links a.active{color:var(--text);}

  .page{position:relative;z-index:1;padding:2rem;max-width:1100px;margin:0 auto;}
  .page-title{font-family:'Crimson Pro',serif;font-size:2.2rem;font-weight:300;margin-bottom:.25rem;}
  .page-sub{color:var(--muted);font-size:.9rem;margin-bottom:2rem;}

  /* SECTION */
  .section{margin-bottom:2.5rem;}
  .section-label{
    display:flex;align-items:center;gap:.75rem;
    font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;
    color:var(--muted);margin-bottom:1rem;
  }
  .section-label::after{content:'';flex:1;height:1px;background:var(--border);}
  .step-badge{
    display:inline-flex;align-items:center;justify-content:center;
    width:24px;height:24px;border-radius:50%;background:var(--blue);
    color:#fff;font-size:.7rem;font-weight:700;flex-shrink:0;
  }

  /* CARD */
  .card{background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:1.5rem;}
  .card-hd{
    display:flex;align-items:center;justify-content:space-between;
    padding:.9rem 1.4rem;border-bottom:1px solid var(--border);
    background:var(--surface2);
  }
  .card-hd h3{font-size:.9rem;font-weight:600;}
  .card-hd .note{font-size:.75rem;color:var(--muted);}

  /* TABLE SHARED */
  .tbl-wrap{overflow-x:auto;}
  table{width:100%;border-collapse:collapse;font-size:.82rem;}
  th{
    text-align:center;padding:.7rem .9rem;
    background:var(--surface2);color:var(--muted);
    font-size:.68rem;letter-spacing:.1em;text-transform:uppercase;
    border-bottom:1px solid var(--border);
  }
  th.left{text-align:left;}
  td{padding:.65rem .9rem;border-bottom:1px solid var(--border);text-align:center;}
  td.left{text-align:left;}
  tr:last-child td{border-bottom:none;}
  tr:hover td{background:rgba(59,130,246,.04);}

  .diag{background:rgba(59,130,246,.08);color:var(--blue);font-weight:600;}
  .hi  {color:#ef4444;}
  .lo  {color:#22c55e;}

  /* WEIGHT BAR */
  .w-bar{display:flex;align-items:center;gap:.75rem;}
  .bar-track{flex:1;height:6px;background:var(--border);border-radius:3px;}
  .bar-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,var(--blue),var(--green));transition:width .8s ease;}
  .w-val{min-width:45px;text-align:right;font-weight:600;font-size:.85rem;}

  /* CONSISTENCY BOX */
  .consist-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;padding:1.5rem;}
  .c-item{text-align:center;}
  .c-val{font-size:1.8rem;font-family:'Crimson Pro',serif;font-weight:600;margin-bottom:.2rem;}
  .c-lbl{font-size:.72rem;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;}
  .ok  {color:var(--green);}
  .bad {color:var(--accent);}

  /* FORMULA */
  .formula{
    background:var(--surface2);border:1px solid var(--border);
    border-radius:8px;padding:1rem 1.4rem;
    font-family:monospace;font-size:.85rem;color:var(--blue);
    margin:1rem 0;
  }

  /* SAATY TABLE */
  .saaty-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:.75rem;padding:1.2rem;}
  .saaty-item{
    display:flex;align-items:center;gap:.75rem;
    background:var(--surface2);border:1px solid var(--border);
    border-radius:8px;padding:.75rem 1rem;
  }
  .saaty-num{
    min-width:28px;height:28px;display:flex;align-items:center;justify-content:center;
    background:var(--border);border-radius:50%;font-size:.78rem;font-weight:700;
  }
  .saaty-desc{font-size:.8rem;color:var(--muted);}

  @media(max-width:700px){
    .consist-grid{grid-template-columns:1fr;padding:1rem;}
    nav .nav-links{display:none;}
    .page{padding:1rem;}
  }
</style>
</head>
<body>
<nav>
  <div class="nav-logo"><div class="dot"></div><span>ICU Priority</span></div>
  <div class="nav-links">
    <a href="index.php">Beranda</a>
    <a href="dashboard.php">Dashboard</a>
    <a href="detail_ahp.php" class="active">Detail AHP</a>
  </div>
</nav>

<div class="page">
  <div class="page-title">Detail Perhitungan AHP</div>
  <div class="page-sub">Analytical Hierarchy Process — Matriks 4×4 &nbsp;|&nbsp; Kriteria: <?= implode(', ', $labels) ?></div>

  <!-- STEP 1: PAIRWISE MATRIX -->
  <div class="section">
    <div class="section-label"><span class="step-badge">1</span> Matriks Perbandingan Berpasangan</div>
    <div class="card">
      <div class="card-hd">
        <h3>Pairwise Comparison Matrix (4×4)</h3>
        <span class="note">Skala Saaty 1–9</span>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th class="left">Kriteria</th>
              <?php foreach($labels as $l): ?><th><?= $l ?></th><?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
          <?php foreach($d['criteria'] as $i => $ci): ?>
            <tr>
              <td class="left"><strong><?= $labels[$i] ?></strong></td>
              <?php foreach($d['criteria'] as $j => $cj): ?>
                <td class="<?= $i===$j?'diag':'' ?>">
                  <?= round($d['pairwise_matrix'][$i][$j], 3) ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
          <tr style="background:var(--surface2)">
            <td class="left" style="color:var(--muted);font-size:.75rem">Jumlah Kolom</td>
            <?php foreach($d['column_sums'] as $s): ?>
              <td style="color:var(--muted);font-size:.78rem"><?= round($s,3) ?></td>
            <?php endforeach; ?>
          </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- STEP 2: NORMALIZED MATRIX -->
  <div class="section">
    <div class="section-label"><span class="step-badge">2</span> Matriks Ternormalisasi</div>
    <div class="card">
      <div class="card-hd">
        <h3>Normalisasi Kolom</h3>
        <span class="note">Rij = Xij / ΣXij (per kolom)</span>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th class="left">Kriteria</th>
              <?php foreach($labels as $l): ?><th><?= $l ?></th><?php endforeach; ?>
              <th>Rata-rata (Bobot)</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($d['criteria'] as $i => $ci): ?>
            <tr>
              <td class="left"><strong><?= $labels[$i] ?></strong></td>
              <?php foreach($d['criteria'] as $j => $cj): ?>
                <td><?= round($d['normalized_matrix'][$i][$j],4) ?></td>
              <?php endforeach; ?>
              <td><strong style="color:var(--blue)"><?= round($d['weights'][$ci],4) ?></strong></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- STEP 3: WEIGHTS -->
  <div class="section">
    <div class="section-label"><span class="step-badge">3</span> Bobot Prioritas Kriteria</div>
    <div class="card">
      <div class="card-hd">
        <h3>Priority Vector (Eigen Vector Aproksimasi)</h3>
        <span class="note">Bobot = rata-rata baris matriks ternormalisasi</span>
      </div>
      <div style="padding:1.5rem;display:flex;flex-direction:column;gap:1rem;">
        <?php foreach($d['criteria'] as $i => $c):
          $w = $d['weights'][$c]; ?>
          <div>
            <div style="display:flex;justify-content:space-between;margin-bottom:.4rem;font-size:.85rem">
              <span><?= $labels[$i] ?></span>
              <span style="color:var(--muted);font-size:.75rem"><?= round($w*100,2) ?>%</span>
            </div>
            <div class="w-bar">
              <div class="bar-track"><div class="bar-fill" style="width:<?= $w*100/.4 ?>%"></div></div>
              <span class="w-val"><?= round($w,4) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- STEP 4: CONSISTENCY -->
  <div class="section">
    <div class="section-label"><span class="step-badge">4</span> Uji Konsistensi</div>

    <div class="formula">
      λmax = <?= round($d['lambda_max'],4) ?> &nbsp;&nbsp;|&nbsp;&nbsp;
      CI = (λmax − n) / (n − 1) = <?= round($d['consistency_index'],4) ?> &nbsp;&nbsp;|&nbsp;&nbsp;
      CR = CI / RI[n=4] = CI / 0.90 = <?= round($d['consistency_ratio'],4) ?>
    </div>

    <div class="card">
      <div class="card-hd">
        <h3>Hasil Uji Konsistensi</h3>
        <span class="note <?= $d['is_consistent']?'ok':'bad' ?>">
          <?= $d['is_consistent'] ? '✅ CR < 0.1 — KONSISTEN' : '⚠ CR ≥ 0.1 — TIDAK KONSISTEN' ?>
        </span>
      </div>
      <div class="consist-grid">
        <div class="c-item">
          <div class="c-val" style="color:var(--blue)"><?= round($d['lambda_max'],4) ?></div>
          <div class="c-lbl">λ Max</div>
          <div style="font-size:.75rem;color:var(--muted);margin-top:.3rem">n = <?= $n ?></div>
        </div>
        <div class="c-item">
          <div class="c-val" style="color:var(--orange)"><?= round($d['consistency_index'],4) ?></div>
          <div class="c-lbl">Consistency Index (CI)</div>
          <div style="font-size:.75rem;color:var(--muted);margin-top:.3rem">RI[4] = 0.90</div>
        </div>
        <div class="c-item">
          <div class="c-val <?= $d['is_consistent']?'ok':'bad' ?>"><?= round($d['consistency_ratio'],4) ?></div>
          <div class="c-lbl">Consistency Ratio (CR)</div>
          <div style="font-size:.75rem;margin-top:.3rem;color:<?= $d['is_consistent']?'var(--green)':'var(--accent)' ?>">
            <?= $d['is_consistent'] ? 'CR < 0.1 ✓' : 'CR ≥ 0.1 ✗' ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- SAATY SCALE REFERENCE -->
  <div class="section">
    <div class="section-label">Referensi Skala Saaty</div>
    <div class="card">
      <div class="card-hd"><h3>Tabel Skala 1–9 Saaty</h3></div>
      <div class="saaty-grid">
        <?php foreach($saatyScale as $k=>$v): ?>
          <div class="saaty-item">
            <div class="saaty-num"><?= $k ?></div>
            <div class="saaty-desc"><?= $v ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- INTERPRETASI -->
  <div class="section">
    <div class="section-label">Interpretasi Bobot</div>
    <div class="card">
      <div class="card-hd"><h3>Urutan Kepentingan Kriteria</h3></div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>Urutan</th><th class="left">Kriteria</th>
              <th>Bobot</th><th>Persentase</th><th>Tipe</th><th>Interpretasi</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $sorted = $d['weights'];
          arsort($sorted);
          $types = $ahp->getCriteriaTypes();
          $labels_map = array_combine($d['criteria'], $d['criteria_labels']);
          $interp = [
            'age'=>'Usia lebih tua → risiko lebih tinggi',
            'bmi'=>'BMI ekstrem → komplikasi lebih besar',
            'd1_spo2_min'=>'SpO2 rendah → hipoksia kritis',
            'd1_heartrate_min'=>'HR rendah → bradikardia berbahaya',
          ];
          $rank=1;
          foreach($sorted as $c=>$w): ?>
            <tr>
              <td><?= $rank++ ?></td>
              <td class="left"><strong><?= $labels_map[$c] ?></strong><br>
                <span style="font-size:.72rem;color:var(--muted)"><?= $c ?></span>
              </td>
              <td><strong><?= round($w,4) ?></strong></td>
              <td>
                <div class="bar-track" style="width:80px;display:inline-block">
                  <div class="bar-fill" style="width:<?= $w*100/.4 ?>%"></div>
                </div>
                <?= round($w*100,1) ?>%
              </td>
              <td><span style="font-size:.75rem;background:rgba(249,115,22,.15);color:var(--orange);padding:.2rem .6rem;border-radius:4px">
                <?= strtoupper($types[$c]) ?>
              </span></td>
              <td style="color:var(--muted);font-size:.8rem"><?= $interp[$c] ?? '-' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div style="text-align:center;margin:2rem 0">
    <a href="dashboard.php" style="display:inline-flex;align-items:center;gap:.5rem;padding:.85rem 2rem;background:var(--blue);color:#fff;border-radius:8px;text-decoration:none;font-size:.9rem;font-weight:600">
      ← Kembali ke Dashboard
    </a>
  </div>

</div>
</body>
</html>
