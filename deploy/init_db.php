<?php
// ============================================================
// SIPJU - SKEMA DATABASE (idempotent)
// Dipakai oleh install.php (web) dan deploy/init_cli.php (Docker).
// Aman dijalankan berulang kali: semua CREATE pakai IF NOT EXISTS.
// ============================================================

function init_database(PDO $pdo): void
{
    // ---- Tabel lama versi MQTT (sudah tidak dipakai) ----
    $pdo->exec('DROP TABLE IF EXISTS sensor_data');
    $pdo->exec('DROP TABLE IF EXISTS schedules');

    // ---- users ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(100) NOT NULL DEFAULT '',
        role ENUM('admin','operator') NOT NULL DEFAULT 'operator',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // ---- nodes (metadata tiang PJU) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS nodes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        node_id VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(100) NOT NULL,
        location VARCHAR(255) NOT NULL DEFAULT '',
        lat DOUBLE NULL,
        lng DOUBLE NULL,
        slave_count INT NOT NULL DEFAULT 1,
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // ---- device_state (state terakhir per gateway) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS device_state (
        node_id VARCHAR(50) PRIMARY KEY,
        gateway_state TINYINT(1) NOT NULL DEFAULT 0,
        firmware_version VARCHAR(20) NOT NULL DEFAULT '',
        control_mode ENUM('SCHEDULE','MANUAL') NOT NULL DEFAULT 'SCHEDULE',
        on_schedule TIME NULL,
        off_schedule TIME NULL,
        wifi_rssi INT NULL,
        uptime BIGINT NULL,
        free_heap INT NULL,
        voltage FLOAT NULL,
        current_amp FLOAT NULL,
        power_watt FLOAT NULL,
        energy FLOAT NULL,
        rtc_time VARCHAR(8) NULL,
        gps_satellites INT NULL,
        gps_hdop FLOAT NULL,
        latitude VARCHAR(20) NULL,
        longitude VARCHAR(20) NULL,
        server_timestamp BIGINT NULL,
        last_seen DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // ---- slaves (status relay + lampu per slave) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS slaves (
        id INT AUTO_INCREMENT PRIMARY KEY,
        node_id VARCHAR(50) NOT NULL,
        slave_id INT NOT NULL,
        state TINYINT(1) NOT NULL DEFAULT 0,
        lamp_ok TINYINT(1) NOT NULL DEFAULT 1,
        last_update DATETIME NULL,
        UNIQUE KEY uq_node_slave (node_id, slave_id)
    ) ENGINE=InnoDB");

    // ---- telemetry (riwayat) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS telemetry (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        node_id VARCHAR(50) NOT NULL,
        gateway_state TINYINT(1) NULL,
        control_mode VARCHAR(10) NULL,
        voltage FLOAT NULL,
        current_amp FLOAT NULL,
        power_watt FLOAT NULL,
        energy FLOAT NULL,
        wifi_rssi INT NULL,
        uptime BIGINT NULL,
        free_heap INT NULL,
        rtc_time VARCHAR(8) NULL,
        gps_satellites INT NULL,
        gps_hdop FLOAT NULL,
        latitude VARCHAR(20) NULL,
        longitude VARCHAR(20) NULL,
        on_schedule VARCHAR(8) NULL,
        off_schedule VARCHAR(8) NULL,
        firmware_version VARCHAR(20) NULL,
        payload JSON NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_node_time (node_id, created_at)
    ) ENGINE=InnoDB");

    // ---- commands (antrean perintah ke device) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS commands (
        id INT AUTO_INCREMENT PRIMARY KEY,
        node_id VARCHAR(50) NOT NULL,
        type VARCHAR(30) NOT NULL,
        control_mode VARCHAR(10) NULL,
        on_time VARCHAR(8) NULL,
        off_time VARCHAR(8) NULL,
        requested_by VARCHAR(100) NOT NULL DEFAULT 'web',
        status ENUM('pending','sent','superseded') NOT NULL DEFAULT 'pending',
        delivered_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_node_status (node_id, status)
    ) ENGINE=InnoDB");

    // ---- settings ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        skey VARCHAR(50) PRIMARY KEY,
        svalue VARCHAR(255) NOT NULL DEFAULT ''
    ) ENGINE=InnoDB");

    // ---- notifications ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(30) NOT NULL DEFAULT 'info',
        node_id VARCHAR(50) NULL,
        message TEXT,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // ---- control_log ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS control_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        node_id VARCHAR(50) NULL,
        action VARCHAR(30) NOT NULL,
        source VARCHAR(20) NOT NULL DEFAULT 'web',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // ---- Admin default ----
    $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    $st->execute(['admin']);
    if ((int) $st->fetchColumn() === 0) {
        $pdo->prepare('INSERT INTO users (username, password, name, role) VALUES (?,?,?,?)')
            ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Administrator', 'admin']);
    }

    // ---- Node sesuai daftar DEVICES di config ----
    foreach (explode(',', DEVICES) as $nodeId) {
        $nodeId = trim($nodeId);
        if ($nodeId === '') {
            continue;
        }
        $st = $pdo->prepare('SELECT COUNT(*) FROM nodes WHERE node_id = ?');
        $st->execute([$nodeId]);
        if ((int) $st->fetchColumn() === 0) {
            $pdo->prepare('INSERT INTO nodes (node_id, name, location, lat, lng) VALUES (?,?,?,?,?)')
                ->execute([$nodeId, 'PJU ' . $nodeId, 'Lokasi belum diatur', null, null]);
        }
        $pdo->prepare('INSERT IGNORE INTO device_state (node_id) VALUES (?)')->execute([$nodeId]);
    }

    // ---- Pengaturan default ----
    $st = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
    $st->execute(['wa_number', WA_DEFAULT_NUMBER]);
    $st->execute(['wa_notify', '1']);
    $st->execute(['installed', '1']);
}
