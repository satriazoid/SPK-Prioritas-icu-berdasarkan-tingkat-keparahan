<?php
session_start();
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ICU Priority System</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Crimson+Pro:ital,wght@0,300;0,600;1,300&display=swap');

    :root {
      --bg: #0a0e1a;
      --surface: #111827;
      --border: #1e2d45;
      --accent: #e8303a;
      --accent2: #f97316;
      --blue: #3b82f6;
      --text: #e2e8f0;
      --muted: #64748b;
      --kritis: #ef4444;
      --tinggi: #f97316;
      --sedang: #eab308;
      --rendah: #22c55e;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Space Grotesk', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* Background grid */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background-image:
        linear-gradient(rgba(59, 130, 246, 0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(59, 130, 246, 0.03) 1px, transparent 1px);
      background-size: 40px 40px;
      pointer-events: none;
      z-index: 0;
    }

    .wrapper {
      position: relative;
      z-index: 1;
    }

    /* ── NAV ── */
    nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.2rem 3rem;
      border-bottom: 1px solid var(--border);
      background: rgba(10, 14, 26, 0.8);
      backdrop-filter: blur(12px);
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .nav-logo {
      display: flex;
      align-items: center;
      gap: .8rem;
    }

    .nav-logo .pulse {
      width: 10px;
      height: 10px;
      background: var(--accent);
      border-radius: 50%;
      animation: pulse 1.8s ease-in-out infinite;
    }

    @keyframes pulse {

      0%,
      100% {
        box-shadow: 0 0 0 0 rgba(232, 48, 58, .6);
      }

      50% {
        box-shadow: 0 0 0 8px rgba(232, 48, 58, 0);
      }
    }

    .nav-logo span {
      font-size: .8rem;
      letter-spacing: .15em;
      color: var(--muted);
      text-transform: uppercase;
    }

    .nav-links {
      display: flex;
      gap: 2rem;
    }

    .nav-links a {
      color: var(--muted);
      text-decoration: none;
      font-size: .9rem;
      letter-spacing: .05em;
      transition: color .2s;
    }

    .nav-links a:hover,
    .nav-links a.active {
      color: var(--text);
    }

    /* ── HERO ── */
    .hero {
      min-height: 85vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 4rem 2rem;
    }

    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      background: rgba(232, 48, 58, .1);
      border: 1px solid rgba(232, 48, 58, .3);
      border-radius: 100px;
      padding: .4rem 1.2rem;
      font-size: .75rem;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 2.5rem;
    }

    .hero h1 {
      font-family: 'Crimson Pro', serif;
      font-size: clamp(3rem, 8vw, 6.5rem);
      font-weight: 300;
      line-height: 1.05;
      letter-spacing: -.02em;
      margin-bottom: 1.5rem;
    }

    .hero h1 em {
      font-style: italic;
      color: var(--accent);
    }

    .hero p {
      max-width: 580px;
      color: var(--muted);
      font-size: 1.05rem;
      line-height: 1.7;
      margin-bottom: 3rem;
    }

    .hero-cta {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      justify-content: center;
    }

    .btn {
      padding: .85rem 2.2rem;
      border-radius: 8px;
      font-family: 'Space Grotesk', sans-serif;
      font-size: .9rem;
      font-weight: 600;
      letter-spacing: .04em;
      cursor: pointer;
      text-decoration: none;
      transition: all .25s;
      display: inline-flex;
      align-items: center;
      gap: .5rem;
    }

    .btn-primary {
      background: var(--accent);
      color: #fff;
      border: none;
    }

    .btn-primary:hover {
      background: #c42030;
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(232, 48, 58, .35);
    }

    .btn-outline {
      background: transparent;
      color: var(--text);
      border: 1px solid var(--border);
    }

    .btn-outline:hover {
      border-color: var(--blue);
      color: var(--blue);
    }

    /* ── STATS BAR ── */
    .stats-bar {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1px;
      background: var(--border);
      border-top: 1px solid var(--border);
      border-bottom: 1px solid var(--border);
    }

    .stat-item {
      background: var(--surface);
      padding: 2rem;
      text-align: center;
    }

    .stat-item .val {
      font-size: 2.2rem;
      font-weight: 700;
      font-family: 'Crimson Pro', serif;
    }

    .stat-item .lbl {
      font-size: .78rem;
      color: var(--muted);
      letter-spacing: .08em;
      margin-top: .25rem;
      text-transform: uppercase;
    }

    .val-red {
      color: var(--kritis);
    }

    .val-orange {
      color: var(--tinggi);
    }

    .val-blue {
      color: var(--blue);
    }

    .val-green {
      color: var(--rendah);
    }

    /* ── METHOD CARDS ── */
    .section {
      padding: 5rem 3rem;
      max-width: 1200px;
      margin: 0 auto;
    }

    .section-title {
      font-family: 'Crimson Pro', serif;
      font-size: 2.2rem;
      font-weight: 300;
      margin-bottom: .5rem;
    }

    .section-sub {
      color: var(--muted);
      font-size: .9rem;
      margin-bottom: 3rem;
    }

    .method-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
    }

    .method-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 2.5rem;
      transition: border-color .25s, transform .25s;
      text-decoration: none;
      color: inherit;
      display: block;
    }

    .method-card:hover {
      border-color: var(--blue);
      transform: translateY(-4px);
    }

    .method-tag {
      display: inline-block;
      font-size: .7rem;
      letter-spacing: .12em;
      text-transform: uppercase;
      padding: .3rem .8rem;
      border-radius: 4px;
      margin-bottom: 1.2rem;
      font-weight: 600;
    }

    .tag-red {
      background: rgba(239, 68, 68, .15);
      color: var(--kritis);
    }

    .tag-blue {
      background: rgba(59, 130, 246, .15);
      color: var(--blue);
    }

    .method-card h3 {
      font-size: 1.4rem;
      margin-bottom: .8rem;
    }

    .method-card p {
      color: var(--muted);
      font-size: .9rem;
      line-height: 1.6;
    }

    .method-steps {
      margin-top: 1.5rem;
      display: flex;
      flex-direction: column;
      gap: .5rem;
    }

    .method-step {
      display: flex;
      align-items: center;
      gap: .75rem;
      font-size: .82rem;
      color: var(--muted);
    }

    .step-num {
      width: 22px;
      height: 22px;
      border-radius: 50%;
      background: var(--border);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .7rem;
      font-weight: 700;
      flex-shrink: 0;
      color: var(--text);
    }

    /* ── CRITERIA TABLE ── */
    .criteria-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1rem;
      margin-top: 2rem;
    }

    .crit-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 1.5rem;
    }

    .crit-card .icon {
      font-size: 1.8rem;
      margin-bottom: .75rem;
    }

    .crit-card h4 {
      font-size: .95rem;
      margin-bottom: .3rem;
    }

    .crit-card .type-badge {
      font-size: .7rem;
      padding: .2rem .6rem;
      border-radius: 4px;
      background: rgba(249, 115, 22, .15);
      color: var(--tinggi);
      font-weight: 600;
    }

    .crit-card p {
      font-size: .8rem;
      color: var(--muted);
      margin-top: .5rem;
      line-height: 1.5;
    }

    /* ── FOOTER ── */
    footer {
      border-top: 1px solid var(--border);
      padding: 2rem 3rem;
      text-align: center;
      color: var(--muted);
      font-size: .82rem;
    }

    @media (max-width: 768px) {
      nav {
        padding: 1rem 1.5rem;
      }

      .nav-links {
        display: none;
      }

      .stats-bar {
        grid-template-columns: 1fr 1fr;
      }

      .method-grid {
        grid-template-columns: 1fr;
      }

      .criteria-grid {
        grid-template-columns: 1fr 1fr;
      }

      .section {
        padding: 3rem 1.5rem;
      }
    }
  </style>
</head>

<body>
  <div class="wrapper">

    <!-- NAV -->
    <nav>
      <div class="nav-logo">
        <div class="pulse"></div>
        <span>ICU Priority System</span>
      </div>
      <div class="nav-links">
        <a href="index.php" class="active">Beranda</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="detail_ahp.php">Detail AHP</a>
        <?php if (isset($_SESSION['user_id'])): ?>
          <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="admin_dashboard.php" style="color:var(--accent)">Admin Panel</a>
          <?php endif; ?>
          <a href="logout.php">Logout (<?= htmlspecialchars($_SESSION['username']) ?>)</a>
        <?php else: ?>
          <a href="login.php" style="color:var(--blue)">Login</a>
        <?php endif; ?>
      </div>
    </nav>

    <!-- HERO -->
    <section class="hero">
      <div class="hero-badge">
        <span>●</span> Sistem Pendukung Keputusan ICU
      </div>
      <h1>Prioritas Kritis<br>dengan <em>Presisi Data.</em></h1>
      <p>
        Membandingkan metode <strong>AHP+SAW</strong> dan <strong>AHP+WP</strong> untuk menentukan urutan prioritas
        pasien ICU berdasarkan kondisi klinis: usia, BMI, saturasi oksigen, dan detak jantung.
      </p>
      <div class="hero-cta">
        <a href="dashboard.php" class="btn btn-primary">
          ▶ Lihat Dashboard
        </a>
        <a href="detail_ahp.php" class="btn btn-outline">
          Detail Perhitungan AHP →
        </a>
      </div>
    </section>

    <!-- STATS BAR -->
    <div class="stats-bar">
      <?php
      // Koneksi DB untuk ambil total pasien
      $totalPatients = 0;
      $kritis = $tinggi = $sedang = 0;
      try {
        $pdo = new PDO("mysql:host=localhost;dbname=icu_priority;charset=utf8", "root", "");
        $stmt = $pdo->query("SELECT COUNT(*) FROM dataset_akumulasi");
        $totalPatients = $stmt ? (int) $stmt->fetchColumn() : 0;
      } catch (Exception $e) { /* silent */
      }
      ?>
      <div class="stat-item">
        <div class="val val-blue"><?= number_format($totalPatients) ?></div>
        <div class="lbl">Total Pasien</div>
      </div>
      <div class="stat-item">
        <div class="val val-blue">4</div>
        <div class="lbl">Kriteria Evaluasi</div>
      </div>
      <div class="stat-item">
        <div class="val val-blue">2</div>
        <div class="lbl">Metode MCDM</div>
      </div>
      <div class="stat-item">
        <div class="val val-blue">&lt;0.1</div>
        <div class="lbl">Consistency Ratio AHP</div>
      </div>
    </div>

    <!-- METHODS -->
    <div class="section">
      <div class="section-title">Metode yang Digunakan</div>
      <div class="section-sub">Dua pendekatan MCDM dibandingkan dalam sistem ini</div>
      <div class="method-grid">
        <!-- AHP+SAW -->
        <a href="dashboard.php?method=saw" class="method-card">
          <span class="method-tag tag-blue">AHP + SAW</span>
          <h3>Simple Additive Weighting</h3>
          <p>Menormalisasi setiap kriteria lalu mengalikannya dengan bobot AHP. Skor akhir adalah penjumlahan tertimbang
            — metode yang sederhana, transparan, dan mudah diinterpretasikan.</p>
          <div class="method-steps">
            <div class="method-step">
              <div class="step-num">1</div>Bangun matriks perbandingan AHP 4×4
            </div>
            <div class="method-step">
              <div class="step-num">2</div>Hitung bobot kriteria & uji konsistensi
            </div>
            <div class="method-step">
              <div class="step-num">3</div>Normalisasi data pasien (cost/benefit)
            </div>
            <div class="method-step">
              <div class="step-num">4</div>Hitung skor SAW = Σ (Wj × Rij)
            </div>
          </div>
        </a>
        <!-- AHP+WP -->
        <a href="dashboard.php?method=wp" class="method-card">
          <span class="method-tag tag-blue">AHP + WP</span>
          <h3>Weighted Product</h3>
          <p>Mengalikan nilai kriteria yang dipangkatkan dengan bobot AHP. Metode ini lebih sensitif terhadap perbedaan
            proporsional antar kriteria dan memberikan penalti lebih besar pada nilai ekstrem.</p>
          <div class="method-steps">
            <div class="method-step">
              <div class="step-num">1</div>Gunakan bobot AHP yang sama
            </div>
            <div class="method-step">
              <div class="step-num">2</div>Hitung S(i) = Π Xij^(±Wj)
            </div>
            <div class="method-step">
              <div class="step-num">3</div>Normalisasi: V(i) = S(i) / Σ S(k)
            </div>
            <div class="method-step">
              <div class="step-num">4</div>Ranking berdasarkan V(i) tertinggi
            </div>
          </div>
        </a>
      </div>
    </div>

    <!-- CRITERIA -->
    <div class="section" style="padding-top:0">
      <div class="section-title">4 Kriteria Evaluasi</div>
      <div class="section-sub">Semua menggunakan tipe <em>cost</em> - nilai lebih rendah = kondisi lebih kritis =
        prioritas lebih tinggi</div>
      <div class="criteria-grid">
        <div class="crit-card">
          <h4>Usia (Age)</h4>
          <span class="type-badge">COST</span>
          <p>Pasien lansia memiliki risiko komplikasi lebih tinggi dan memerlukan penanganan prioritas.</p>
        </div>
        <div class="crit-card">
          <h4>BMI</h4>
          <span class="type-badge">COST</span>
          <p>BMI ekstrem (sangat rendah/tinggi) meningkatkan risiko komplikasi selama perawatan ICU.</p>
        </div>
        <div class="crit-card">
          <h4>SpO2 Minimum</h4>
          <span class="type-badge">COST</span>
          <p>Saturasi oksigen rendah mengindikasikan hipoksia - kondisi kritis yang memerlukan intervensi segera.</p>
        </div>
        <div class="crit-card">
          <h4>Heart Rate Min</h4>
          <span class="type-badge">COST</span>
          <p>Detak jantung minimum rendah (bradikardia) dapat mengancam jiwa dan memerlukan monitoring ketat.</p>
        </div>
      </div>
    </div>

    <footer>
      ICU Priority System &nbsp;·&nbsp; Dataset: WiDS Datathon 2020
    </footer>

  </div>
</body>

</html>