<?php
// ============================================================
// SIPJU - KONFIGURASI UTAMA
// Sistem Informasi Penerangan Jalan Umum (Skripsi)
// Monitoring PJU berbasis ESP-Now & Internet of Things
//
// Dashboard ini SEKALIGUS menjadi backend:
// ESP32 Gateway -> POST /api/device/{node_id} -> MySQL -> command
// ============================================================

// ===== Database (XAMPP / MySQL) =====
define('DB_HOST', 'localhost');
define('DB_NAME', 'pju_monitoring');
define('DB_USER', 'root');
define('DB_PASS', '');

// ===== Nama Aplikasi =====
define('APP_NAME', 'SIPJU');
define('APP_FULL', 'Sistem Informasi Penerangan Jalan Umum');

// ============================================================
// KONFIGURASI FIRMWARE / API
// ============================================================

// API Key untuk autentikasi request dari ESP32 Gateway.
// WAJIB sama dengan API_KEY di sketch_gateway.ino
define('API_KEY', 'LPJU_IOT_2026');

// Daftar node (gateway) yang terdaftar, dipisah koma.
// Nama node harus sama dengan NODE_ID di firmware, contoh: LPJU01.
define('DEVICES', 'LPJU01');

// Alamat endpoint yang dipakai ESP32 (SERVER_BASE_URL di firmware).
// Endpoint akan menjadi: {API_ENDPOINT_BASE}{node_id}
// Contoh akses via LAN: http://192.168.1.100/PJU/api/device/LPJU01
define('API_ENDPOINT_BASE', 'http://localhost/PJU/api/device/');

// Jeda antar kirim telemetry firmware (ms) - untuk hitung online
define('TELEMETRY_INTERVAL_MS', 5000);

// Detik tanpa update terakhir sebelum node dianggap offline
define('NODE_OFFLINE_SECONDS', 60);

// Ambang jumlah mismatch (gateway_state vs slave.state)
// sebelum memicu notifikasi "DEVICE ERROR"
define('FAULT_ALERT_THRESHOLD', 3);

// ===== WhatsApp (notifikasi) =====
define('WA_DEFAULT_NUMBER', '6289524500594');
