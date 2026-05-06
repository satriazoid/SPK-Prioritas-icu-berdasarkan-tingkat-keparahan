<?php
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=icu_priority;charset=utf8",
        "root", "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

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

    // Insert dummy users
    // password: 'password123' -> hashed
    $admin_pw = password_hash('admin123', PASSWORD_DEFAULT);
    $user_pw = password_hash('user123', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, role) VALUES ('admin', :apw, 'admin'), ('user', :upw, 'user')");
    $stmt->execute(['apw' => $admin_pw, 'upw' => $user_pw]);

    echo "Database setup completed successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
