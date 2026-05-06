<?php
session_start();

// Database setup inline for ease of use
$pdo = null;
$dbError = null;
try {
  $pdo = new PDO(
    "mysql:host=localhost;dbname=icu_priority;charset=utf8",
    "root",
    "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );

  // Ensure users table exists
  $sql = "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin','user') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ";
  $pdo->exec($sql);

  // Insert dummy users if not exist
  $admin_pw = password_hash('admin', PASSWORD_DEFAULT);
  $user_pw = password_hash('user', PASSWORD_DEFAULT);
  $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, role) VALUES ('admin', :apw, 'admin'), ('user', :upw, 'user')");
  $stmt->execute(['apw' => $admin_pw, 'upw' => $user_pw]);

} catch (Exception $e) {
  $dbError = $e->getMessage();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($pdo && $username && $password) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u");
    $stmt->execute(['u' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
      $_SESSION['user_id'] = $user['id'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['role'] = $user['role'];

      if ($user['role'] === 'admin') {
        header("Location: admin_dashboard.php");
      } else {
        header("Location: dashboard.php");
      }
      exit;
    } else {
      $error = "Username atau password salah.";
    }
  } else {
    $error = "Mohon isi semua bidang.";
  }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - ICU Priority System</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap');

    :root {
      --bg: #0a0e1a;
      --surface: #111827;
      --border: #1e2d45;
      --accent: #e8303a;
      --blue: #3b82f6;
      --text: #e2e8f0;
      --muted: #64748b;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Space Grotesk', sans-serif;
      background: var(--bg);
      color: var(--text);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
    }

    .login-container {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 3rem;
      width: 100%;
      max-width: 400px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }

    .logo {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: .8rem;
      margin-bottom: 2rem;
    }

    .logo .pulse {
      width: 12px;
      height: 12px;
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

    .logo span {
      font-size: 1.1rem;
      letter-spacing: .1em;
      color: var(--text);
      text-transform: uppercase;
      font-weight: 600;
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    .form-group label {
      display: block;
      margin-bottom: .5rem;
      font-size: .85rem;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: .05em;
    }

    .form-group input {
      width: 100%;
      padding: .8rem 1rem;
      border-radius: 8px;
      border: 1px solid var(--border);
      background: var(--bg);
      color: var(--text);
      font-family: inherit;
      font-size: 1rem;
      transition: border-color .2s;
    }

    .form-group input:focus {
      outline: none;
      border-color: var(--blue);
    }

    .btn {
      width: 100%;
      padding: 1rem;
      border-radius: 8px;
      border: none;
      background: var(--blue);
      color: #fff;
      font-family: inherit;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all .2s;
      margin-top: 1rem;
    }

    .btn:hover {
      background: #2563eb;
      transform: translateY(-2px);
    }

    .error {
      color: var(--accent);
      font-size: .85rem;
      margin-bottom: 1rem;
      text-align: center;
    }

    .demo-creds {
      margin-top: 2rem;
      padding-top: 1.5rem;
      border-top: 1px solid var(--border);
      font-size: .8rem;
      color: var(--muted);
      text-align: center;
      line-height: 1.6;
    }
  </style>
</head>

<body>

  <div class="login-container">
    <div class="logo">
      <div class="pulse"></div>
      <span>ICU Priority</span>
    </div>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($dbError): ?>
      <div class="error" style="color:#f97316">DB Error: <?= htmlspecialchars($dbError) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autocomplete="off">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button type="submit" class="btn">Masuk</button>
    </form>

    <!-- <div class="demo-creds">
    <strong>Demo Akun:</strong><br>
    Admin: admin / admin<br>
    User: user / user
  </div> -->
  </div>

</body>

</html>