<?php
// ============================================================
// SIPJU - ENDPOINT KOMUNIKASI ESP32 GATEWAY
//   POST api/device/{node_id}
//   Header : x-api-key: {API_KEY}
//   Body   : telemetry JSON (format sketch_gateway.ino)
//
// Flow (sama dengan server Node LPJU-IOT asli):
//   - ESP POST latest state
//   - Server simpan ke MySQL
//   - Server return pending command (jika ada)
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// ---------- Ambil node_id (rewrite / path_info fallback) ----------
$nodeId = trim($_GET['node'] ?? '');
if ($nodeId === '') {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $parts = explode('/', rtrim(parse_url($uri, PHP_URL_PATH) ?? '', '/'));
    $last = end($parts);
    if (is_string($last) && $last !== 'device.php' && $last !== 'device') {
        $nodeId = trim($last);
    }
}
$nodeId = strtoupper($nodeId);

// ---------- Method ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['success' => false, 'message' => 'Method not allowed'], 405);
}

// ---------- API Key ----------
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey === '') {
    json_out(['success' => false, 'message' => 'API key is required'], 401);
}
if (!hash_equals(API_KEY, $apiKey)) {
    json_out(['success' => false, 'message' => 'Invalid API key'], 401);
}

if ($nodeId === '') {
    json_out(['success' => false, 'message' => 'node_id is required'], 400);
}

// ---------- Parse body ----------
$raw = file_get_contents('php://input');
$d = json_decode($raw, true);
if (!is_array($d)) {
    json_out(['success' => false, 'message' => 'Invalid JSON body'], 400);
}

try {
    $pdo = db();

    // ---------- Auto-register node ----------
    $st = $pdo->prepare('SELECT id FROM nodes WHERE node_id = ?');
    $st->execute([$nodeId]);
    if (!$st->fetch()) {
        $pdo->prepare('INSERT INTO nodes (node_id, name) VALUES (?,?)')
            ->execute([$nodeId, 'PJU ' . $nodeId]);
    }

    $gatewayState = isset($d['gateway_state']) ? ((int) $d['gateway_state'] > 0 ? 1 : 0) : 0;
    $fwVersion    = isset($d['firmware_version']) ? substr(trim((string) $d['firmware_version']), 0, 20) : '';
    $mode         = (isset($d['control_mode']) && strtoupper(trim($d['control_mode'])) === 'MANUAL') ? 'MANUAL' : 'SCHEDULE';

    $f = function ($k, $fallback = null) use ($d) {
        return isset($d[$k]) ? $d[$k] : $fallback;
    };

    // ---------- Ambil state lama (untuk pertahankan schedule saat mode MANUAL) ----------
    $old = null;
    $st = $pdo->prepare('SELECT * FROM device_state WHERE node_id = ?');
    $st->execute([$nodeId]);
    $old = $st->fetch() ?: null;

    $onTime  = $f('on_schedule', $old['on_schedule'] ?? null);
    $offTime = $f('off_schedule', $old['off_schedule'] ?? null);
    if ($onTime === null || $onTime === '') { $onTime = null; }
    if ($offTime === null || $offTime === '') { $offTime = null; }

    $voltage = is_numeric($f('voltage')) ? (float) $f('voltage') : null;
    $current = is_numeric($f('current')) ? (float) $f('current') : null;
    $power   = is_numeric($f('power')) ? (float) $f('power') : null;
    $energy  = is_numeric($f('energy')) ? (float) $f('energy') : null;
    $rssi    = is_numeric($f('wifi_rssi')) ? (int) $f('wifi_rssi') : null;
    $uptime  = is_numeric($f('uptime')) ? (int) $f('uptime') : null;
    $heap    = is_numeric($f('free_heap')) ? (int) $f('free_heap') : null;
    $rtc     = $f('rtc_time', null);
    $sat     = is_numeric($f('gps_satellites')) ? (int) $f('gps_satellites') : null;
    $hdop    = is_numeric($f('gps_hdop')) ? (float) $f('gps_hdop') : null;
    $lat     = $f('latitude', null);
    $lng     = $f('longitude', null);
    if ($lat === '-' || $lat === null || $lat === '') { $lat = null; } else { $lat = substr((string) $lat, 0, 20); }
    if ($lng === '-' || $lng === null || $lng === '') { $lng = null; } else { $lng = substr((string) $lng, 0, 20); }
    $serverTs = (int) ($f('server_timestamp', 0) ?: time() * 1000);
    $now      = date('Y-m-d H:i:s');

    // ---------- Upsert device_state ----------
    $pdo->prepare('INSERT INTO device_state
        (node_id, gateway_state, firmware_version, control_mode, on_schedule, off_schedule,
         wifi_rssi, uptime, free_heap, voltage, current_amp, power_watt, energy,
         rtc_time, gps_satellites, gps_hdop, latitude, longitude, server_timestamp, last_seen)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
         gateway_state=VALUES(gateway_state), firmware_version=VALUES(firmware_version),
         control_mode=VALUES(control_mode),
         on_schedule=IF(VALUES(on_schedule) IS NULL, on_schedule, VALUES(on_schedule)),
         off_schedule=IF(VALUES(off_schedule) IS NULL, off_schedule, VALUES(off_schedule)),
         wifi_rssi=VALUES(wifi_rssi), uptime=VALUES(uptime), free_heap=VALUES(free_heap),
         voltage=VALUES(voltage), current_amp=VALUES(current_amp), power_watt=VALUES(power_watt),
         energy=VALUES(energy), rtc_time=VALUES(rtc_time), gps_satellites=VALUES(gps_satellites),
         gps_hdop=VALUES(gps_hdop), latitude=VALUES(latitude), longitude=VALUES(longitude),
         server_timestamp=VALUES(server_timestamp), last_seen=VALUES(last_seen)')
        ->execute([
            $nodeId, $gatewayState, $fwVersion, $mode, $onTime, $offTime,
            $rssi, $uptime, $heap, $voltage, $current, $power, $energy,
            $rtc, $sat, $hdop, $lat, $lng, $serverTs, $now,
        ]);

    // ---------- Insert telemetry (riwayat) ----------
    $pdo->prepare('INSERT INTO telemetry
        (node_id, gateway_state, control_mode, voltage, current_amp, power_watt, energy,
         wifi_rssi, uptime, free_heap, rtc_time, gps_satellites, gps_hdop,
         latitude, longitude, on_schedule, off_schedule, firmware_version, payload)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            $nodeId, $gatewayState, $mode, $voltage, $current, $power, $energy,
            $rssi, $uptime, $heap, $rtc, $sat, $hdop, $lat, $lng,
            $onTime, $offTime, $fwVersion, json_encode($d),
        ]);

    // ---------- Slaves + deteksi mismatch ----------
    if (isset($d['slaves']) && is_array($d['slaves'])) {
        foreach ($d['slaves'] as $slave) {
            if (!isset($slave['slave_id'])) {
                continue;
            }
            $sid     = (int) $slave['slave_id'];
            $sState  = isset($slave['state']) ? ((int) $slave['state'] > 0 ? 1 : 0) : 0;
            $lampOk  = isset($slave['lamp_ok']) ? ((int) $slave['lamp_ok'] > 0 ? 1 : 0) : 1;

            $pdo->prepare('INSERT INTO slaves (node_id, slave_id, state, lamp_ok, last_update) VALUES (?,?,?,?,?)
                ON DUPLICATE KEY UPDATE state=VALUES(state), lamp_ok=VALUES(lamp_ok), last_update=VALUES(last_update)')
                ->execute([$nodeId, $sid, $sState, $lampOk, $now]);

            $mismatch = ($gatewayState !== $sState);

            $counterKey   = 'fault:' . $nodeId . ':slave:' . $sid;
            $notifiedKey  = 'fault_notified:' . $nodeId . ':slave:' . $sid;

            if ($mismatch) {
                $count = ((int) setting_get($counterKey, '0')) + 1;
                setting_set($counterKey, (string) $count);
                if ($count >= FAULT_ALERT_THRESHOLD && setting_get($notifiedKey, '0') !== '1') {
                    setting_set($notifiedKey, '1');
                    $msg = '⚠️ DEVICE ERROR: relay gateway ' . $nodeId . ' (' . ($gatewayState ? 'ON' : 'OFF') . ') tidak sinkron dengan slave ' . $sid . ' (' . ($sState ? 'ON' : 'OFF') . ').';
                    $pdo->prepare('INSERT INTO notifications (type, node_id, message) VALUES (?,?,?)')
                        ->execute(['error', $nodeId, $msg]);
                    if (setting_get('wa_notify', '1') === '1') {
                        $pdo->prepare('INSERT INTO notifications (type, node_id, message) VALUES (?,?,?)')
                            ->execute(['wa', $nodeId, $msg]);
                    }
                }
            } else {
                setting_set($counterKey, '0');
                if (setting_get($notifiedKey, '0') === '1') {
                    setting_set($notifiedKey, '0');
                    $msg = '✅ DEVICE RECOVERY: slave ' . $sid . ' pada ' . $nodeId . ' kembali sinkron.';
                    $pdo->prepare('INSERT INTO notifications (type, node_id, message) VALUES (?,?,?)')
                        ->execute(['info', $nodeId, $msg]);
                }
            }
        }
    }

    // ---------- Ambil pending command (paling lama, sekali pakai) ----------
    $command = null;
    $st = $pdo->prepare('SELECT * FROM commands WHERE node_id = ? AND status = "pending" ORDER BY id ASC LIMIT 1');
    $st->execute([$nodeId]);
    $cmdRow = $st->fetch();
    if ($cmdRow) {
        $pdo->prepare('UPDATE commands SET status = "sent", delivered_at = ? WHERE id = ?')
            ->execute([$now, $cmdRow['id']]);

        $command = [
            'type'         => $cmdRow['type'],
            'command_id'   => 'CMD-' . $cmdRow['id'],
            'created_at'   => $cmdRow['created_at'],
            'requested_by' => $cmdRow['requested_by'],
        ];
        if ($cmdRow['control_mode'] !== null) {
            $command['control_mode'] = $cmdRow['control_mode'];
        }
        if ($cmdRow['on_time'] !== null) {
            $command['on_time'] = $cmdRow['on_time'];
        }
        if ($cmdRow['off_time'] !== null) {
            $command['off_time'] = $cmdRow['off_time'];
        }
    }

    // ---------- Response (format persis server Node) ----------
    json_out([
        'success' => true,
        'message' => 'Success',
        'data'    => [
            'success' => true,
            'command' => $command,
        ],
    ]);
} catch (Throwable $e) {
    json_out(['success' => false, 'message' => 'Internal server error: ' . $e->getMessage()], 500);
}
