<?php
// ============================================================
// SIPJU - INSTALLER (jalankan SEKALI di browser)
// http://localhost/PJU/install.php
// Membuat database, tabel, akun admin default, dan node contoh.
//
// CATATAN: tabel data lama (sensor_data & schedules versi MQTT)
// akan di-drop dan diganti schema kontrak firmware ESP-Now.
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

        // 2. Jalankan skema + seed (lihat deploy/init_db.php)
        require_once __DIR__ . '/deploy/init_db.php';
        init_database($pdo);

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
            <p class="mt-2 text-sm text-slate-500">Membuat database <b class="text-sky-600"><?= htmlspecialchars(DB_NAME) ?></b> beserta tabel, akun admin, dan node dari daftar <b class="text-sky-600"><?= htmlspecialchars(DEVICES) ?></b>.</p>
        </div>

        <?php if ($message): ?>
            <div class="mt-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">✅ <?= htmlspecialchars($message) ?>
                <ul class="mt-2 list-disc pl-5 text-xs text-green-600">
                    <li>Login: <b>admin</b> / <b>admin123</b></li>
                    <li>Buka <a class="underline" href="index.php">index.php</a></li>
                    <li>Endpoint device: <b><?= htmlspecialchars(API_ENDPOINT_BASE) ?>&lt;node_id&gt;</b> (header <b>x-api-key</b>)</li>
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
