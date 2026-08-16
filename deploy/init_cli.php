<?php
// ============================================================
// SIPJU - INIT CLI (dijalankan Docker saat container web start)
//   php deploy/init_cli.php
// Membuat skema + seed bila belum ada. Aman dijalankan berulang.
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/init_db.php';

try {
    $pdo = db();
    init_database($pdo);
    echo "SIPJU database ready (DB=" . DB_NAME . ")\n";
} catch (Throwable $e) {
    fwrite(STDERR, "SIPJU init failed: " . $e->getMessage() . "\n");
    exit(1);
}
