<?php
// ============================================================
// API: AKSI DASHBOARD (harus login)
//   POST api/action.php  action=...
//   action = save_mode, set_lamp, log_control, add_node,
//            update_node, delete_node, add_schedule,
//            delete_schedule, mark_notif_read, update_wa, test_wa
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
session_start();

if (current_user() === null) {
    json_out(['status' => 'error', 'message' => 'Silakan login'], 401);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $pdo = db();

    switch ($action) {

        case 'save_mode': {
            $mode = ($_POST['mode'] ?? '') === 'manual' ? 'manual' : 'auto';
            setting_set('mode', $mode);
            json_out(['status' => 'success', 'mode' => $mode]);
        }

        case 'set_lamp': {
            $nodeId = trim($_POST['node_id'] ?? '');
            $on     = isset($_POST['on']) ? ((int) $_POST['on'] === 1 ? 1 : 0) : null;
            if ($nodeId === '' || $on === null) {
                json_out(['status' => 'error', 'message' => 'node_id dan on wajib diisi'], 400);
            }
            $pdo->prepare('INSERT INTO control_log (node_id, action, source) VALUES (?,?,?)')
                ->execute([$nodeId, $on ? 'ON' : 'OFF', 'web']);
            json_out(['status' => 'success']);
        }

        case 'log_control': {
            $nodeId = trim($_POST['node_id'] ?? '');
            $act    = trim($_POST['control_action'] ?? '');
            $src    = trim($_POST['source'] ?? 'web');
            if ($nodeId === '' || $act === '') {
                json_out(['status' => 'error', 'message' => 'parameter kurang'], 400);
            }
            $pdo->prepare('INSERT INTO control_log (node_id, action, source) VALUES (?,?,?)')
                ->execute([$nodeId, $act, $src]);
            json_out(['status' => 'success']);
        }

        case 'add_node': {
            $nodeId = trim($_POST['node_id'] ?? '');
            $name   = trim($_POST['name'] ?? '');
            $loc    = trim($_POST['location'] ?? '');
            $lat    = $_POST['lat'] === '' ? null : (float) $_POST['lat'];
            $lng    = $_POST['lng'] === '' ? null : (float) $_POST['lng'];
            if ($nodeId === '' || $name === '') {
                json_out(['status' => 'error', 'message' => 'node_id dan nama wajib diisi'], 400);
            }
            $pdo->prepare('INSERT INTO nodes (node_id, name, location, lat, lng) VALUES (?,?,?,?,?)')
                ->execute([$nodeId, $name, $loc, $lat, $lng]);
            json_out(['status' => 'success']);
        }

        case 'update_node': {
            $id     = (int) ($_POST['id'] ?? 0);
            $name   = trim($_POST['name'] ?? '');
            $loc    = trim($_POST['location'] ?? '');
            $lat    = $_POST['lat'] === '' ? null : (float) $_POST['lat'];
            $lng    = $_POST['lng'] === '' ? null : (float) $_POST['lng'];
            $enabled = isset($_POST['enabled']) ? (int) $_POST['enabled'] : 1;
            if (!$id) {
                json_out(['status' => 'error', 'message' => 'id wajib diisi'], 400);
            }
            $pdo->prepare('UPDATE nodes SET name=?, location=?, lat=?, lng=?, enabled=? WHERE id=?')
                ->execute([$name, $loc, $lat, $lng, $enabled, $id]);
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

        case 'add_schedule': {
            $nodeId    = trim($_POST['node_id'] ?? '*');
            $day       = trim($_POST['day_of_week'] ?? 'all');
            $startTime = trim($_POST['start_time'] ?? '');
            $endTime   = trim($_POST['end_time'] ?? '');
            if ($startTime === '' || $endTime === '') {
                json_out(['status' => 'error', 'message' => 'waktu mulai/selesai wajib diisi'], 400);
            }
            $pdo->prepare('INSERT INTO schedules (node_id, day_of_week, start_time, end_time) VALUES (?,?,?,?)')
                ->execute([$nodeId, $day, $startTime, $endTime]);
            json_out(['status' => 'success']);
        }

        case 'delete_schedule': {
            $id = (int) ($_POST['id'] ?? 0);
            if (!$id) {
                json_out(['status' => 'error', 'message' => 'id wajib diisi'], 400);
            }
            $pdo->prepare('DELETE FROM schedules WHERE id=?')->execute([$id]);
            json_out(['status' => 'success']);
        }

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
            $msg = 'PJU Monitoring: Test notifikasi. Pesan ini dikirim dari dashboard.';
            json_out([
                'status' => 'success',
                'whatsapp_url' => 'https://wa.me/' . $number . '?text=' . urlencode($msg),
            ]);
        }

        default:
            json_out(['status' => 'error', 'message' => 'action tidak dikenal'], 400);
    }
} catch (Throwable $e) {
    json_out(['status' => 'error', 'message' => $e->getMessage()], 500);
}
