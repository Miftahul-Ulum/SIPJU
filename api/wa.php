<?php
// ============================================================
// SIPJU - API BRIDGE WHATSAPP BOT (dipanggil server-to-server)
//   Auth : header "X-Api-Key: <API_KEY>" (sama dengan device)
//   GET  : ?action=status&node_id=LPJU01
//   POST : ?action=command
//         body: node_id, type, control_mode?, on_time?, off_time?,
//               requested_by? (default "wa")
//
// Perintah yang masuk ditulis ke tabel commands; ESP32 gateway
// mengambilnya saat polling telemetry berikutnya. Bot WhatsApp
// cukup menelepon endpoint ini - tidak perlu sentuh Redis lagi.
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

// Dukung body form-encoded maupun JSON (dikirim bot WhatsApp)
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $_POST = array_merge($_POST, $json);
    }
}

$key = $_SERVER['HTTP_X_API_KEY'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if ($key === '') {
    json_out(['status' => 'error', 'message' => 'API key wajib diisi'], 401);
}
if (!hash_equals(API_KEY, $key)) {
    json_out(['status' => 'error', 'message' => 'API key salah'], 401);
}

function waNodeIsOnline(array $state): bool
{
    return !empty($state['last_seen']) && (time() - strtotime($state['last_seen'])) <= NODE_OFFLINE_SECONDS;
}

function waPushCommand(PDO $pdo, string $nodeId, array $payload, string $requester): void
{
    // Single pending command (command lama ditimpa, sama seperti server Node)
    $pdo->prepare("UPDATE commands SET status = 'superseded' WHERE node_id = ? AND status = 'pending'")
        ->execute([$nodeId]);

    $pdo->prepare('INSERT INTO commands (node_id, type, control_mode, on_time, off_time, requested_by) VALUES (?,?,?,?,?,?)')
        ->execute([
            $nodeId,
            $payload['type'],
            $payload['control_mode'] ?? null,
            $payload['on_time'] ?? null,
            $payload['off_time'] ?? null,
            $requester,
        ]);
}

try {
    $pdo = db();
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    switch ($action) {

        // ============================================
        // STATUS - data untuk balasan /status di WhatsApp
        // ============================================
        case 'status': {
            $nodeId = strtoupper(trim($_GET['node_id'] ?? ''));
            if ($nodeId === '') {
                json_out(['status' => 'error', 'message' => 'node_id wajib diisi'], 400);
            }

            $st = $pdo->prepare('SELECT * FROM device_state WHERE node_id = ?');
            $st->execute([$nodeId]);
            $state = $st->fetch();
            if (!$state) {
                json_out(['status' => 'error', 'message' => 'Node ' . $nodeId . ' tidak ditemukan'], 404);
            }

            $ss = $pdo->prepare('SELECT slave_id, state, lamp_ok FROM slaves WHERE node_id = ? ORDER BY slave_id');
            $ss->execute([$nodeId]);

            json_out([
                'status' => 'success',
                'data'   => [
                    'node_id'        => $nodeId,
                    'gateway_state'  => (int) $state['gateway_state'],
                    'control_mode'   => $state['control_mode'],
                    'on_schedule'    => $state['on_schedule'],
                    'off_schedule'   => $state['off_schedule'],
                    'wifi_rssi'      => $state['wifi_rssi'],
                    'uptime'         => (int) $state['uptime'],
                    'free_heap'      => $state['free_heap'],
                    'voltage'        => $state['voltage'],
                    'current'        => $state['current_amp'],
                    'power'          => $state['power_watt'],
                    'energy'         => $state['energy'],
                    'rtc_time'       => $state['rtc_time'],
                    'gps_satellites' => $state['gps_satellites'],
                    'gps_hdop'       => $state['gps_hdop'],
                    'latitude'       => $state['latitude'],
                    'longitude'      => $state['longitude'],
                    'online'         => waNodeIsOnline($state),
                    'slaves'         => $ss->fetchAll(),
                ],
            ]);
        }

        // ============================================
        // COMMAND - insert perintah dari bot WhatsApp
        // ============================================
        case 'command': {
            $nodeId = strtoupper(trim($_POST['node_id'] ?? ''));
            $type   = strtoupper(trim($_POST['type'] ?? ''));
            $requester = trim($_POST['requested_by'] ?? '');
            if ($requester === '') {
                $requester = 'wa';
            }

            if ($nodeId === '' || $type === '') {
                json_out(['status' => 'error', 'message' => 'node_id dan type wajib diisi'], 400);
            }

            $st = $pdo->prepare('SELECT COUNT(*) FROM nodes WHERE node_id = ?');
            $st->execute([$nodeId]);
            if ((int) $st->fetchColumn() === 0) {
                json_out(['status' => 'error', 'message' => 'Node ' . $nodeId . ' tidak terdaftar'], 400);
            }

            $state = null;
            $st = $pdo->prepare('SELECT * FROM device_state WHERE node_id = ?');
            $st->execute([$nodeId]);
            $state = $st->fetch() ?: null;

            switch ($type) {
                case 'STATE_ON':
                case 'STATE_OFF': {
                    if (!$state || !waNodeIsOnline($state)) {
                        json_out(['status' => 'error', 'message' => $nodeId . ' sedang offline, perintah tidak dikirim'], 400);
                    }
                    if (($state['control_mode'] ?? 'SCHEDULE') !== 'MANUAL') {
                        json_out(['status' => 'error', 'message' => 'Device dalam mode SCHEDULE. Ubah ke MANUAL dulu untuk kontrol manual.'], 400);
                    }
                    waPushCommand($pdo, $nodeId, ['type' => $type], $requester);
                    $pdo->prepare('INSERT INTO control_log (node_id, action, source) VALUES (?,?,?)')
                        ->execute([$nodeId, $type, 'wa']);
                    json_out(['status' => 'success', 'message' => 'Perintah ' . $type . ' berhasil dikirim ke ' . $nodeId]);
                }

                case 'SET_MODE': {
                    $mode = strtoupper(trim($_POST['control_mode'] ?? ''));
                    if ($mode !== 'SCHEDULE' && $mode !== 'MANUAL') {
                        json_out(['status' => 'error', 'message' => 'control_mode harus SCHEDULE atau MANUAL'], 400);
                    }
                    waPushCommand($pdo, $nodeId, ['type' => $type, 'control_mode' => $mode], $requester);
                    $pdo->prepare('INSERT INTO control_log (node_id, action, source) VALUES (?,?,?)')
                        ->execute([$nodeId, 'SET_MODE_' . $mode, 'wa']);
                    json_out(['status' => 'success', 'message' => 'Mode ' . $nodeId . ' berhasil diubah ke ' . $mode]);
                }

                case 'SET_SCHEDULE': {
                    $on  = trim($_POST['on_time'] ?? '');
                    $off = trim($_POST['off_time'] ?? '');
                    $timeRegex = '/^([01]\d|2[0-3]):([0-5]\d)(:([0-5]\d))?$/';
                    if (!preg_match($timeRegex, $on) || !preg_match($timeRegex, $off)) {
                        json_out(['status' => 'error', 'message' => 'Format waktu salah. Gunakan HH:MM atau HH:MM:SS'], 400);
                    }
                    if (!$state || !waNodeIsOnline($state)) {
                        json_out(['status' => 'error', 'message' => $nodeId . ' sedang offline, jadwal tidak dikirim'], 400);
                    }
                    if (($state['control_mode'] ?? 'SCHEDULE') !== 'SCHEDULE') {
                        json_out(['status' => 'error', 'message' => 'Device dalam mode MANUAL. Ubah ke SCHEDULE dulu untuk jadwal otomatis.'], 400);
                    }
                    $on  = (strlen($on) === 5) ? $on . ':00' : $on;
                    $off = (strlen($off) === 5) ? $off . ':00' : $off;
                    waPushCommand($pdo, $nodeId, ['type' => $type, 'on_time' => $on, 'off_time' => $off], $requester);
                    $pdo->prepare('INSERT INTO control_log (node_id, action, source) VALUES (?,?,?)')
                        ->execute([$nodeId, 'SET_SCHEDULE', 'wa']);
                    json_out(['status' => 'success', 'message' => 'Schedule ' . $nodeId . ' berhasil diperbarui']);
                }

                case 'RESTART_DEVICE': {
                    if (!$state || !waNodeIsOnline($state)) {
                        json_out(['status' => 'error', 'message' => $nodeId . ' sedang offline, perintah tidak dikirim'], 400);
                    }
                    waPushCommand($pdo, $nodeId, ['type' => $type], $requester);
                    $pdo->prepare('INSERT INTO control_log (node_id, action, source) VALUES (?,?,?)')
                        ->execute([$nodeId, 'RESTART_DEVICE', 'wa']);
                    json_out(['status' => 'success', 'message' => 'Perintah restart ' . $nodeId . ' berhasil dikirim']);
                }

                default:
                    json_out(['status' => 'error', 'message' => 'type perintah tidak dikenal'], 400);
            }
        }

        default:
            json_out(['status' => 'error', 'message' => 'action tidak dikenal'], 400);
    }
} catch (Throwable $e) {
    json_out(['status' => 'error', 'message' => $e->getMessage()], 500);
}
