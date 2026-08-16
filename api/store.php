<?php
// ============================================================
// API: SIMPAN DATA SENSOR DARI MQTT/BRIDGE
// Dipanggil oleh dashboard (JS) atau bridge MQTT:
//   POST api/store.php  body: {"node_id":"pju01","light":45.2,"temp":28.1,...}
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST; // fallback form-urlencoded
}

$nodeId = trim($data['node_id'] ?? $data['id'] ?? '');
if ($nodeId === '') {
    json_out(['status' => 'error', 'message' => 'node_id wajib diisi'], 400);
}

$map = $GLOBALS['FIELD_MAP'] ?? [];

$row = [
    'node_id'     => $nodeId,
    'light_level' => null,
    'temperature' => null,
    'humidity'    => null,
    'motion'      => null,
    'voltage'     => null,
    'current_amp' => null,
    'power_watt'  => null,
    'lamp_status' => null,
    'mode'        => null,
];

foreach ($data as $key => $val) {
    if (isset($map[$key]) && array_key_exists($map[$key], $row)) {
        $col = $map[$key];
        if ($col === 'motion' || $col === 'lamp_status') {
            $row[$col] = ((int) $val) > 0 ? 1 : 0;
        } else {
            $row[$col] = is_numeric($val) ? (float) $val : null;
        }
    }
}

try {
    $pdo = db();

    // Auto-daftarkan node baru jika belum ada
    $st = $pdo->prepare('SELECT id FROM nodes WHERE node_id = ?');
    $st->execute([$nodeId]);
    if (!$st->fetch()) {
        $pdo->prepare('INSERT INTO nodes (node_id, name) VALUES (?,?)')
            ->execute([$nodeId, 'Node ' . strtoupper($nodeId)]);
    }

    $pdo->prepare('INSERT INTO sensor_data
        (node_id, light_level, temperature, humidity, motion, voltage, current_amp, power_watt, lamp_status, mode, payload)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            $nodeId,
            $row['light_level'],
            $row['temperature'],
            $row['humidity'],
            $row['motion'],
            $row['voltage'],
            $row['current_amp'],
            $row['power_watt'],
            $row['lamp_status'],
            $row['mode'],
            json_encode($data),
        ]);

    json_out(['status' => 'success', 'node_id' => $nodeId]);
} catch (Throwable $e) {
    json_out(['status' => 'error', 'message' => $e->getMessage()], 500);
}
