<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: login.php");
  exit;
}

$pdo = null;
try {
  $pdo = new PDO("mysql:host=localhost;dbname=icu_priority;charset=utf8", "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
  die("Database connection failed: " . $e->getMessage());
}

$msg = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['action'])) {
    $action = $_POST['action'];

    $patient_id = $_POST['patient_id'] ?? '';
    $age = $_POST['age'] ?? 0;
    $bmi = $_POST['bmi'] ?? 0;
    $spo2 = $_POST['spo2'] ?? 0;
    $hr = $_POST['hr'] ?? 0;

    if ($action === 'create') {
      try {
        $stmt = $pdo->prepare("INSERT INTO dataset_akumulasi (patient_id, age, bmi, d1_spo2_min, d1_heartrate_min) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$patient_id, $age, $bmi, $spo2, $hr]);
        $msg = "Data pasien berhasil ditambahkan.";
      } catch (Exception $e) {
        $msg = "Gagal menambah: " . $e->getMessage();
      }
    } elseif ($action === 'update') {
      $orig_id = $_POST['orig_id'] ?? '';
      try {
        $stmt = $pdo->prepare("UPDATE dataset_akumulasi SET patient_id=?, age=?, bmi=?, d1_spo2_min=?, d1_heartrate_min=? WHERE patient_id=?");
        $stmt->execute([$patient_id, $age, $bmi, $spo2, $hr, $orig_id]);
        $msg = "Data pasien berhasil diperbarui.";
      } catch (Exception $e) {
        $msg = "Gagal memperbarui: " . $e->getMessage();
      }
    } elseif ($action === 'delete') {
      $orig_id = $_POST['orig_id'] ?? '';
      try {
        $stmt = $pdo->prepare("DELETE FROM dataset_akumulasi WHERE patient_id=?");
        $stmt->execute([$orig_id]);
        $msg = "Data pasien berhasil dihapus.";
      } catch (Exception $e) {
        $msg = "Gagal menghapus: " . $e->getMessage();
      }
    }
  }
}

// Fetch edit data if requested
$editData = null;
if (isset($_GET['edit'])) {
  $stmt = $pdo->prepare("SELECT * FROM dataset_akumulasi WHERE patient_id=?");
  $stmt->execute([$_GET['edit']]);
  $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch all patients
$searchQuery = trim($_GET['search'] ?? '');
if ($searchQuery) {
    $stmt = $pdo->prepare("SELECT * FROM dataset_akumulasi WHERE patient_id LIKE ? ORDER BY patient_id DESC LIMIT 100");
    $stmt->execute(['%' . $searchQuery . '%']);
} else {
    $stmt = $pdo->query("SELECT * FROM dataset_akumulasi ORDER BY patient_id DESC LIMIT 100");
}
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch stats for complex dashboard
$stmt = $pdo->query("SELECT COUNT(*) as total, AVG(age) as avg_age, AVG(bmi) as avg_bmi, MIN(d1_spo2_min) as min_spo2, MIN(d1_heartrate_min) as min_hr FROM dataset_akumulasi");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - ICU Priority</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap');

    :root {
      --bg: #0a0e1a;
      --surface: #111827;
      --surface2: #162032;
      --border: #1e2d45;
      --accent: #e8303a;
      --blue: #3b82f6;
      --green: #22c55e;
      --text: #e2e8f0;
      --muted: #64748b;
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
    }

    nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1rem 2rem;
      border-bottom: 1px solid var(--border);
      background: rgba(10, 14, 26, .9);
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .nav-logo {
      font-weight: 700;
      color: var(--text);
      letter-spacing: 1px;
    }

    .nav-links a {
      color: var(--muted);
      text-decoration: none;
      margin-left: 1.5rem;
      font-size: .9rem;
    }

    .nav-links a:hover {
      color: var(--text);
    }

    .container {
      max-width: 1200px;
      margin: 2rem auto;
      padding: 0 2rem;
    }

    .page-header {
      margin-bottom: 2rem;
    }

    .page-header h1 {
      font-size: 2rem;
      font-weight: 600;
      margin-bottom: .5rem;
    }

    .page-header p {
      color: var(--muted);
    }

    /* Stats row */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-bottom: 2rem;
    }

    .stat-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 1.5rem;
    }

    .stat-card .val {
      font-size: 1.8rem;
      font-weight: 700;
      color: var(--blue);
    }

    .stat-card .lbl {
      font-size: .8rem;
      color: var(--muted);
      text-transform: uppercase;
      margin-top: .3rem;
    }

    /* Dashboard Grid */
    .dashboard-grid {
      display: grid;
      grid-template-columns: 350px 1fr;
      gap: 2rem;
      align-items: start;
    }

    @media (max-width:900px) {
      .dashboard-grid {
        grid-template-columns: 1fr;
      }
    }

    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 1.5rem;
    }

    .card h3 {
      font-size: 1.1rem;
      margin-bottom: 1.5rem;
      border-bottom: 1px solid var(--border);
      padding-bottom: .5rem;
    }

    .form-group {
      margin-bottom: 1rem;
    }

    .form-group label {
      display: block;
      font-size: .8rem;
      color: var(--muted);
      margin-bottom: .3rem;
      text-transform: uppercase;
    }

    .form-group input {
      width: 100%;
      padding: .6rem .8rem;
      border-radius: 6px;
      border: 1px solid var(--border);
      background: var(--surface2);
      color: var(--text);
      font-family: inherit;
    }

    .form-group input:focus {
      outline: none;
      border-color: var(--blue);
    }

    .btn {
      padding: .6rem 1.2rem;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-family: inherit;
      font-weight: 600;
      font-size: .9rem;
      transition: all .2s;
    }

    .btn-primary {
      background: var(--blue);
      color: #fff;
      width: 100%;
    }

    .btn-primary:hover {
      background: #2563eb;
    }

    .btn-danger {
      background: rgba(232, 48, 58, .1);
      color: var(--accent);
      border: 1px solid rgba(232, 48, 58, .3);
      padding: .3rem .6rem;
      font-size: .8rem;
    }

    .btn-danger:hover {
      background: var(--accent);
      color: #fff;
    }

    .btn-edit {
      background: rgba(59, 130, 246, .1);
      color: var(--blue);
      border: 1px solid rgba(59, 130, 246, .3);
      padding: .3rem .6rem;
      font-size: .8rem;
      text-decoration: none;
    }

    .btn-edit:hover {
      background: var(--blue);
      color: #fff;
    }

    .alert {
      padding: 1rem;
      background: rgba(34, 197, 94, .1);
      border: 1px solid var(--green);
      color: var(--green);
      border-radius: 8px;
      margin-bottom: 1.5rem;
    }

    /* Table */
    .table-responsive {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: .9rem;
    }

    th {
      text-align: left;
      padding: 1rem;
      border-bottom: 1px solid var(--border);
      color: var(--muted);
      text-transform: uppercase;
      font-size: .75rem;
      letter-spacing: .05em;
    }

    td {
      padding: 1rem;
      border-bottom: 1px solid var(--border);
    }

    tr:last-child td {
      border-bottom: none;
    }

    tr:hover td {
      background: var(--surface2);
    }
  </style>
</head>

<body>

  <nav>
    <div class="nav-logo">ICU Admin panel</div>
    <div class="nav-links">
    <a href="dashboard.php">Lihat Web</a>
    <a href="logout.php">Logout (<?= htmlspecialchars($_SESSION['username']) ?>)</a>
  </div>
  </nav>

  <div class="container">
    <div class="page-header">
      <h1>Data Pasien (CRUD)</h1>
      <p>Manajemen dataset ICU Priority</p>
    </div>

    <?php if ($msg): ?>
      <div class="alert"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="stats-row">
      <div class="stat-card">
        <div class="val"><?= round($stats['total'] ?? 0) ?></div>
        <div class="lbl">Total Pasien</div>
      </div>
      <div class="stat-card">
        <div class="val"><?= round($stats['avg_age'] ?? 0, 1) ?></div>
        <div class="lbl">Rata-rata Usia</div>
      </div>
      <div class="stat-card">
        <div class="val"><?= round($stats['avg_bmi'] ?? 0, 1) ?></div>
        <div class="lbl">Rata-rata BMI</div>
      </div>
      <div class="stat-card">
        <div class="val" style="color:var(--accent)"><?= round($stats['min_spo2'] ?? 0, 1) ?>%</div>
        <div class="lbl">SpO2 Terendah</div>
      </div>
    </div>

    <div class="dashboard-grid">
      <!-- Form Tambah/Edit -->
      <div class="card">
        <h3><?= $editData ? 'Edit Data Pasien' : 'Tambah Pasien Baru' ?></h3>
        <form method="POST" action="admin_dashboard.php">
          <input type="hidden" name="action" value="<?= $editData ? 'update' : 'create' ?>">
          <?php if ($editData): ?>
            <input type="hidden" name="orig_id" value="<?= htmlspecialchars($editData['patient_id']) ?>">
          <?php endif; ?>

          <div class="form-group">
            <label>Patient ID</label>
            <input type="text" name="patient_id" required
              value="<?= htmlspecialchars($editData['patient_id'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Usia</label>
            <input type="number" step="0.1" name="age" required value="<?= htmlspecialchars($editData['age'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>BMI</label>
            <input type="number" step="0.1" name="bmi" required value="<?= htmlspecialchars($editData['bmi'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>SpO2 Min (%)</label>
            <input type="number" step="0.1" name="spo2" required
              value="<?= htmlspecialchars($editData['d1_spo2_min'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Heart Rate Min</label>
            <input type="number" step="0.1" name="hr" required
              value="<?= htmlspecialchars($editData['d1_heartrate_min'] ?? '') ?>">
          </div>

          <button type="submit" class="btn btn-primary"><?= $editData ? 'Simpan Perubahan' : 'Tambah Pasien' ?></button>
          <?php if ($editData): ?>
            <a href="admin_dashboard.php"
              style="display:block; text-align:center; margin-top:1rem; color:var(--muted); font-size:.8rem;">Batal
              Edit</a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Tabel Data -->
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; border-bottom:1px solid var(--border); padding-bottom:.5rem;">
        <h3 style="border:none; margin:0; padding:0;">Daftar Pasien</h3>
        <form method="GET" action="admin_dashboard.php" style="display:flex; gap:.5rem;">
          <input type="text" name="search" placeholder="Cari ID Pasien..." value="<?= htmlspecialchars($searchQuery) ?>" style="padding:.4rem .8rem; border-radius:6px; border:1px solid var(--border); background:var(--surface2); color:var(--text); font-family:inherit;">
          <button type="submit" class="btn btn-primary" style="width:auto; padding:.4rem 1rem;">Cari</button>
          <?php if($searchQuery): ?>
            <a href="admin_dashboard.php" class="btn" style="background:var(--surface2); color:var(--text); border:1px solid var(--border); padding:.4rem 1rem; text-decoration:none;">Reset</a>
          <?php endif; ?>
        </form>
      </div>
      <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>ID Pasien</th>
                <th>Usia</th>
                <th>BMI</th>
                <th>SpO2</th>
                <th>Heart Rate</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($patients as $p): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($p['patient_id']) ?></strong></td>
                  <td><?= $p['age'] ?></td>
                  <td><?= $p['bmi'] ?></td>
                  <td><?= $p['d1_spo2_min'] ?></td>
                  <td><?= $p['d1_heartrate_min'] ?></td>
                  <td style="display:flex; gap:.5rem;">
                    <a href="?edit=<?= urlencode($p['patient_id']) ?>" class="btn-edit">Edit</a>
                    <form method="POST" action="admin_dashboard.php"
                      onsubmit="return confirm('Yakin ingin menghapus pasien ini?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="orig_id" value="<?= htmlspecialchars($p['patient_id']) ?>">
                      <button type="submit" class="btn-danger">Hapus</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($patients)): ?>
                <tr>
                  <td colspan="6" style="text-align:center; color:var(--muted)">Belum ada data</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>

</body>

</html>