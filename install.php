<?php
// ============================================================
// PJU MONITORING - INSTALLER (jalankan SEKALI di browser)
// http://localhost/PJU/install.php
// Membuat database, tabel, akun admin default, dan data contoh.
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        // 1. Buat database jika belum ada
        $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        $pdo->exec('USE `' . DB_NAME . '`');

        // 2. Tabel users
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100) NOT NULL DEFAULT '',
            role ENUM('admin','operator') NOT NULL DEFAULT 'operator',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        // 3. Tabel nodes (tiang PJU)
        $pdo->exec("CREATE TABLE IF NOT EXISTS nodes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            node_id VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            location VARCHAR(255) NOT NULL DEFAULT '',
            lat DOUBLE NULL,
            lng DOUBLE NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        // 4. Tabel sensor_data (riwayat)
        $pdo->exec("CREATE TABLE IF NOT EXISTS sensor_data (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            node_id VARCHAR(50) NOT NULL,
            light_level FLOAT NULL,
            temperature FLOAT NULL,
            humidity FLOAT NULL,
            motion TINYINT(1) NULL,
            voltage FLOAT NULL,
            current_amp FLOAT NULL,
            power_watt FLOAT NULL,
            lamp_status TINYINT(1) NULL,
            mode VARCHAR(10) NULL,
            payload JSON NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_node_time (node_id, created_at)
        ) ENGINE=InnoDB");

        // 5. Tabel schedules (jadwal timer)
        $pdo->exec("CREATE TABLE IF NOT EXISTS schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            node_id VARCHAR(50) NOT NULL DEFAULT '*',
            day_of_week VARCHAR(40) NOT NULL DEFAULT 'all',
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        // 6. Tabel settings
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            skey VARCHAR(50) PRIMARY KEY,
            svalue VARCHAR(255) NOT NULL DEFAULT ''
        ) ENGINE=InnoDB");

        // 7. Tabel notifications
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(30) NOT NULL DEFAULT 'info',
            node_id VARCHAR(50) NULL,
            message TEXT,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        // 8. Tabel control_log
        $pdo->exec("CREATE TABLE IF NOT EXISTS control_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            node_id VARCHAR(50) NULL,
            action VARCHAR(30) NOT NULL,
            source VARCHAR(20) NOT NULL DEFAULT 'web',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        // 9. Admin default
        $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
        $st->execute(['admin']);
        if ((int) $st->fetchColumn() === 0) {
            $pdo->prepare('INSERT INTO users (username, password, name, role) VALUES (?,?,?,?)')
                ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Administrator', 'admin']);
        }

        // 10. Node contoh
        $st = $pdo->prepare('SELECT COUNT(*) FROM nodes');
        $st->execute();
        if ((int) $st->fetchColumn() === 0) {
            $pdo->exec("INSERT INTO nodes (node_id, name, location, lat, lng) VALUES
                ('pju01','PJU Jalan Ahmad Yani 1','Jl. Ahmad Yani, Jepara',-6.5915,110.6717),
                ('pju02','PJU Jalan Ahmad Yani 2','Jl. Ahmad Yani, Jepara',-6.5910,110.6722),
                ('pju03','PJU Alun-Alun Jepara','Jl. Raya Alun-Alun, Jepara',-6.5920,110.6710)");
        }

        // 11. Pengaturan default
        setting_set('mode', 'auto');           // auto | manual
        setting_set('wa_number', WA_DEFAULT_NUMBER);
        setting_set('wa_notify', '1');
        setting_set('installed', '1');

        $message = 'Instalasi berhasil!';
    } catch (Throwable $e) {
        $error = 'Instalasi gagal: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalasi — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-sky-50 via-slate-50 to-indigo-50 flex items-center justify-center p-6">
    <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-8 shadow-xl shadow-slate-200/60">
        <div class="text-center">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-sky-100 text-3xl">🛣️</div>
            <h1 class="text-xl font-black tracking-tight text-slate-900">Instalasi Database <?= APP_NAME ?></h1>
            <p class="mt-2 text-sm text-slate-500">Membuat database <b class="text-sky-600"><?= htmlspecialchars(DB_NAME) ?></b> beserta tabel, akun admin, dan data contoh.</p>
        </div>

        <?php if ($message): ?>
            <div class="mt-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">✅ <?= htmlspecialchars($message) ?>
                <ul class="mt-2 list-disc pl-5 text-xs text-green-600">
                    <li>Login: <b>admin</b> / <b>admin123</b></li>
                    <li>Buka <a class="underline" href="index.php">index.php</a></li>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" class="mt-6">
            <button type="submit" name="install" value="1"
                class="w-full rounded-full bg-sky-500 py-3 text-sm font-black text-white shadow-lg shadow-sky-500/30 transition hover:bg-sky-400 active:scale-95">
                Jalankan Instalasi
            </button>
        </form>
        <p class="mt-4 text-center text-[11px] text-slate-400">Jalankan sekali saja. Hapus file ini setelah berhasil.</p>
    </div>
</body>
</html>
