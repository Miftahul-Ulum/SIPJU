<?php
// ============================================================
// SIPJU - API AKSI DASHBOARD (harus login)
//   POST api/action.php  action=...
//   action = send_command, add_node, update_node, delete_node,
//            mark_notif_read, add_notification, update_wa, test_wa
//
// send_command menulis ke tabel commands; perintah dikirim
// ke ESP32 saat device melakukan POST telemetry berikutnya.
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
session_start();

if (current_user() === null) {
    json_out(['status' => 'error', 'message' => 'Silakan login'], 401);
}

$user    = current_user();
$action  = $_POST['action'] ?? $_GET['action'] ?? '';

function getDeviceState(PDO $pdo, string $nodeId): ?array
{
    $st = $pdo->prepare('SELECT * FROM device_state WHERE node_id = ?');
    $st->execute([$nodeId]);
    $row = $st->fetch();
    return $row ?: null;
}

function nodeIsOnline(array $state): bool
{
    return !empty($state['last_seen']) && (time() - strtotime($state['last_seen'])) <= NODE_OFFLINE_SECONDS;
}

function pushCommand(PDO $pdo, string $nodeId, array $payload, string $requester): void
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

    switch ($action) {

        // ============================================
        // KIRIM PERINTAH KE DEVICE
        // ============================================
        case 'send_command': {
            $nodeId = strtoupper(trim($_POST['node_id'] ?? ''));
            $type   = strtoupper(trim($_POST['type'] ?? ''));

            if ($nodeId === '' || $type === '') {
                json_out(['status' => 'error', 'message' => 'node_id dan type wajib diisi'], 400);
            }

            $st = $pdo->prepare('SELECT COUNT(*) FROM nodes WHERE node_id = ?');
            $st->execute([$nodeId]);
            if ((int) $st->fetchColumn() === 0) {
                json_out(['status' => 'error', 'message' => 'Node ' . $nodeId . ' tidak terdaftar'], 400);
            }

            $state = getDeviceState($pdo, $nodeId);

            switch ($type) {
                case 'STATE_ON':
                case 'STATE_OFF': {
                    if (!$state || !nodeIsOnline($state)) {
                        json_out(['status' => 'error', 'message' => $nodeId . ' sedang offline, perintah tidak dikirim'], 400);
                    }
                    if (($state['control_mode'] ?? 'SCHEDULE') !== 'MANUAL') {
                        json_out(['status' => 'error', 'message' => 'Device dalam mode SCHEDULE. Ubah ke MANUAL dulu untuk kontrol manual.'], 400);
                    }
                    pushCommand($pdo, $nodeId, ['type' => $type], $user['username']);
                    $pdo->prepare('INSERT INTO control_log (node_id, action, source) VALUES (?,?,?)')
                        ->execute([$nodeId, $type, 'web']);
                    json_out(['status' => 'success', 'message' => 'Perintah ' . $type . ' masuk antrean untuk ' . $nodeId]);
                }

                case 'SET_MODE': {
                    $mode = strtoupper(trim($_POST['control_mode'] ?? ''));
                    if ($mode !== 'SCHEDULE' && $mode !== 'MANUAL') {
                        json_out(['status' => 'error', 'message' => 'control_mode harus SCHEDULE atau MANUAL'], 400);
                    }
                    pushCommand($pdo, $nodeId, ['type' => $type, 'control_mode' => $mode], $user['username']);
                    $pdo->prepare('INSERT INTO control_log (node_id, action, source) VALUES (?,?,?)')
                        ->execute([$nodeId, 'SET_MODE_' . $mode, 'web']);
                    json_out(['status' => 'success', 'message' => 'Perintah ganti mode ke ' . $mode . ' masuk antrean']);
                }

                case 'SET_SCHEDULE': {
                    $on  = trim($_POST['on_time'] ?? '');
                    $off = trim($_POST['off_time'] ?? '');
                    $timeRegex = '/^([01]\d|2[0-3]):([0-5]\d)(:([0-5]\d))?$/';
                    if (!preg_match($timeRegex, $on) || !preg_match($timeRegex, $off)) {
                        json_out(['status' => 'error', 'message' => 'Format waktu salah. Gunakan HH:MM atau HH:MM:SS'], 400);
                    }
                    if (!$state || !nodeIsOnline($state)) {
                        json_out(['status' => 'error', 'message' => $nodeId . ' sedang offline, jadwal tidak dikirim'], 400);
                    }
                    if (($state['control_mode'] ?? 'SCHEDULE') !== 'SCHEDULE') {
                        json_out(['status' => 'error', 'message' => 'Device dalam mode MANUAL. Ubah ke SCHEDULE dulu untuk jadwal otomatis.'], 400);
                    }
                    // Normalisasi ke HH:MM:SS (format yang dipahami firmware)
                    $on  = (strlen($on) === 5) ? $on . ':00' : $on;
                    $off = (strlen($off) === 5) ? $off . ':00' : $off;
                    pushCommand($pdo, $nodeId, ['type' => $type, 'on_time' => $on, 'off_time' => $off], $user['username']);
                    $pdo->prepare('INSERT INTO control_log (node_id, action, source) VALUES (?,?,?)')
                        ->execute([$nodeId, 'SET_SCHEDULE', 'web']);
                    json_out(['status' => 'success', 'message' => 'Jadwal ON ' . $on . ' / OFF ' . $off . ' masuk antrean']);
                }

                case 'RESTART_DEVICE': {
                    if (!$state || !nodeIsOnline($state)) {
                        json_out(['status' => 'error', 'message' => $nodeId . ' sedang offline, perintah tidak dikirim'], 400);
                    }
                    pushCommand($pdo, $nodeId, ['type' => $type], $user['username']);
                    $pdo->prepare('INSERT INTO control_log (node_id, action, source) VALUES (?,?,?)')
                        ->execute([$nodeId, 'RESTART_DEVICE', 'web']);
                    json_out(['status' => 'success', 'message' => 'Perintah restart masuk antrean']);
                }

                default:
                    json_out(['status' => 'error', 'message' => 'type perintah tidak dikenal'], 400);
            }
        }

        // ============================================
        // MANAJEMEN NODE
        // ============================================
        case 'add_node': {
            $nodeId = strtoupper(trim($_POST['node_id'] ?? ''));
            $name   = trim($_POST['name'] ?? '');
            $loc    = trim($_POST['location'] ?? '');
            $lat    = $_POST['lat'] === '' ? null : (float) $_POST['lat'];
            $lng    = $_POST['lng'] === '' ? null : (float) $_POST['lng'];
            $slaveCount = max(0, (int) ($_POST['slave_count'] ?? 1));
            if ($nodeId === '' || $name === '') {
                json_out(['status' => 'error', 'message' => 'node_id dan nama wajib diisi'], 400);
            }
            $pdo->prepare('INSERT INTO nodes (node_id, name, location, lat, lng, slave_count) VALUES (?,?,?,?,?,?)')
                ->execute([$nodeId, $name, $loc, $lat, $lng, $slaveCount]);
            $pdo->prepare('INSERT IGNORE INTO device_state (node_id) VALUES (?)')->execute([$nodeId]);
            json_out(['status' => 'success']);
        }

        case 'update_node': {
            $id      = (int) ($_POST['id'] ?? 0);
            $name    = trim($_POST['name'] ?? '');
            $loc     = trim($_POST['location'] ?? '');
            $lat     = $_POST['lat'] === '' ? null : (float) $_POST['lat'];
            $lng     = $_POST['lng'] === '' ? null : (float) $_POST['lng'];
            $enabled = isset($_POST['enabled']) ? (int) $_POST['enabled'] : 1;
            $slaveCount = max(0, (int) ($_POST['slave_count'] ?? 1));
            if (!$id) {
                json_out(['status' => 'error', 'message' => 'id wajib diisi'], 400);
            }
            $pdo->prepare('UPDATE nodes SET name=?, location=?, lat=?, lng=?, slave_count=?, enabled=? WHERE id=?')
                ->execute([$name, $loc, $lat, $lng, $slaveCount, $enabled, $id]);
            json_out(['status' => 'success']);
        }

        case 'delete_node': {
            $id = (int) ($_POST['id'] ?? 0);
            if (!$id) {
                json_out(['status' => 'error', 'message' => 'id wajib diisi'], 400);
            }
            $pdo->prepare('DELETE FROM nodes WHERE id=?')->execute([$id]);
            json_out(['status' => 'success']);
        }

        // ============================================
        // NOTIFIKASI & WHATSAPP
        // ============================================
        case 'mark_notif_read': {
            $pdo->exec('UPDATE notifications SET is_read = 1');
            json_out(['status' => 'success']);
        }

        case 'add_notification': {
            $type    = trim($_POST['type'] ?? 'info');
            $nodeId  = trim($_POST['node_id'] ?? '');
            $message = trim($_POST['message'] ?? '');
            if ($message === '') {
                json_out(['status' => 'error', 'message' => 'pesan wajib diisi'], 400);
            }
            $pdo->prepare('INSERT INTO notifications (type, node_id, message) VALUES (?,?,?)')
                ->execute([$type, $nodeId, $message]);
            json_out(['status' => 'success']);
        }

        case 'update_wa': {
            $number = preg_replace('/[^0-9]/', '', $_POST['wa_number'] ?? '');
            $notify = isset($_POST['wa_notify']) ? '1' : '0';
            if ($number === '') {
                json_out(['status' => 'error', 'message' => 'nomor WhatsApp wajib diisi'], 400);
            }
            setting_set('wa_number', $number);
            setting_set('wa_notify', $notify);
            json_out(['status' => 'success', 'wa_number' => $number]);
        }

        case 'test_wa': {
            $number = setting_get('wa_number', WA_DEFAULT_NUMBER);
            $msg = APP_NAME . ': Test notifikasi. Pesan ini dikirim dari dashboard.';
            json_out([
                'status'       => 'success',
                'whatsapp_url' => 'https://wa.me/' . $number . '?text=' . urlencode($msg),
            ]);
        }

        default:
            json_out(['status' => 'error', 'message' => 'action tidak dikenal'], 400);
    }
} catch (Throwable $e) {
    json_out(['status' => 'error', 'message' => $e->getMessage()], 500);
}
