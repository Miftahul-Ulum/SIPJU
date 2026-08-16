<?php
require_once __DIR__ . '/db.php';
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        $st = db()->prepare('SELECT * FROM users WHERE username = ?');
        $st->execute([$username]);
        $user = $st->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id'       => (int) $user['id'],
                'username' => $user['username'],
                'name'     => $user['name'],
                'role'     => $user['role'],
            ];
            header('Location: index.php');
            exit;
        }
        $error = 'Username atau password salah.';
    } catch (Throwable $e) {
        $error = 'Database belum terpasang. Jalankan <a class="underline text-sky-600" href="install.php">install.php</a> dulu.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-sky-50 via-slate-50 to-indigo-50 flex items-center justify-center p-6">
    <div class="w-full max-w-sm rounded-3xl border border-slate-200 bg-white p-8 shadow-xl shadow-slate-200/60">
        <div class="text-center">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-sky-100 text-3xl">🛣️</div>
            <h1 class="text-xl font-black tracking-tight text-slate-900"><?= APP_NAME ?></h1>
            <p class="mt-1 text-sm text-slate-500"><?= APP_FULL ?></p>
        </div>

        <?php if ($error): ?>
            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700"><?= $error ?></div>
        <?php endif; ?>

        <form method="post" class="mt-6 space-y-4">
            <div>
                <label class="mb-1 block text-xs font-bold text-slate-600">Username</label>
                <input type="text" name="username" required autofocus
                    class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-sky-500 focus:bg-white focus:ring-2 focus:ring-sky-100">
            </div>
            <div>
                <label class="mb-1 block text-xs font-bold text-slate-600">Password</label>
                <input type="password" name="password" required
                    class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-sky-500 focus:bg-white focus:ring-2 focus:ring-sky-100">
            </div>
            <button type="submit"
                class="w-full rounded-full bg-sky-500 py-3 text-sm font-black text-white transition hover:bg-sky-400 active:scale-95 shadow-lg shadow-sky-500/30">
                Masuk
            </button>
        </form>
    </div>
</body>
</html>
