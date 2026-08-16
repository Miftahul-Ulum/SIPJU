<?php
// ============================================================
// PJU MONITORING - KONFIGURASI UTAMA
// Sistem Monitoring Penerangan Jalan Umum (Skripsi)
// ============================================================

// ===== Database (XAMPP / MySQL) =====
define('DB_HOST', 'localhost');
define('DB_NAME', 'pju_monitoring');
define('DB_USER', 'root');
define('DB_PASS', '');

// ===== Nama Aplikasi =====
define('APP_NAME', 'SIPJU');
define('APP_FULL', 'Sistem Informasi Penerangan Jalan Umum');

// ===== Broker MQTT (broker sendiri) =====
// Contoh broker: EMQX / Mosquitto yang dijalankan di jaringan Anda.
// Dashboard terhubung ke broker lewat WebSocket.
define('MQTT_HOST', 'localhost');      // IP / hostname broker (mis. 192.168.1.100)
define('MQTT_WS_PORT', 8083);          // Port WebSocket MQTT (EMQX: 8083 = ws, 8084 = wss)
define('MQTT_USE_WSS', false);         // true jika pakai wss (HTTPS), false jika ws (HTTP)
define('MQTT_PATH', '/mqtt');          // Path WebSocket broker (EMQX default: /mqtt)
define('MQTT_USER', '');               // Username broker (kosongkan jika anonim)
define('MQTT_PASS', '');               // Password broker
define('MQTT_CLIENT_ID', 'pju-dashboard');

// ===== Topik MQTT =====
// Data sensor dari firmware: pju/<node_id>/sensor
define('MQTT_TOPIC_DATA', 'pju/+/sensor');
// Perintah kontrol: dashboard publish ke pju/<node_id>/cmd
define('MQTT_TOPIC_CMD', 'pju/cmd');

// ===== WhatsApp (untuk notifikasi) =====
define('WA_DEFAULT_NUMBER', '6289524500594');

// ===== Ambang batas (offline / error) =====
define('NODE_OFFLINE_SECONDS', 300);   // 300 detik = dianggap offline
define('MOTION_ON_THRESHOLD', 30);     // persen cahaya saat gerakan menyala otomatis

// ============================================================
// PETA FIELD JSON FIRMWARE -> KOLOM DATABASE
// Kunci kiri = nama field di JSON yang dikirim firmware,
// Nilai kanan = nama kolom di tabel sensor_data.
// Tambahkan mapping sesuai field tambahan firmware Anda.
// ============================================================
$FIELD_MAP = [
    'light'        => 'light_level',
    'luminosity'   => 'light_level',
    'ldr'          => 'light_level',
    'lux'          => 'light_level',
    'temp'         => 'temperature',
    'temperature'  => 'temperature',
    'hum'          => 'humidity',
    'humidity'     => 'humidity',
    'motion'       => 'motion',
    'pir'          => 'motion',
    'voltage'      => 'voltage',
    'volt'         => 'voltage',
    'v'            => 'voltage',
    'current'      => 'current_amp',
    'arus'         => 'current_amp',
    'ampere'       => 'current_amp',
    'power'        => 'power_watt',
    'watt'         => 'power_watt',
    'p'            => 'power_watt',
    'lamp'         => 'lamp_status',
    'lamp_status'  => 'lamp_status',
    'status'       => 'lamp_status',
    'mode'         => 'mode',
];
