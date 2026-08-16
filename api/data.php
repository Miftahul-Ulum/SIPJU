<?php
// ============================================================
// SIPJU - API DATA DASHBOARD (harus login)
//   GET api/data.php?act=nodes          -> node + state terakhir + slaves
//   GET api/data.php?act=history&node=..&field=..&hours=..
//   GET api/data.php?act=stats
//   GET api/data.php?act=schedules      -> jadwal per device
//   GET api/data.php?act=devices        -> daftar node utk dropdown
//   GET api/data.php?act=commands&node=..
//   GET api/data.php?act=notifications
//   GET api/data.php?act=settings
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
session_start();

if (current_user() === null) {
    json_out(['status' => 'error', 'message' => 'Silakan login'], 401);
}

$act = $_GET['act'] ?? 'nodes';

try {
    $pdo = db();

    switch ($act) {

        case 'nodes': {
            $nodes = $pdo->query('SELECT * FROM nodes WHERE enabled = 1 ORDER BY node_id')->fetchAll();

            $states = [];
            $st = $pdo->query('SELECT * FROM device_state');
            foreach ($st->fetchAll() as $r) {
                $states[$r['node_id']] = $r;
            }

            $slaves = [];
            $st = $pdo->query('SELECT * FROM slaves ORDER BY node_id, slave_id');
            foreach ($st->fetchAll() as $r) {
                $slaves[$r['node_id']][] = [
                    'slave_id'    => (int) $r['slave_id'],
                    'state'       => (int) $r['state'],
                    'lamp_ok'     => (int) $r['lamp_ok'],
                    'last_update' => $r['last_update'],
                ];
            }

            $offlineSec = NODE_OFFLINE_SECONDS;
            foreach ($nodes as &$n) {
                $s = $states[$n['node_id']] ?? null;
                $n['state']  = $s;
                $n['slaves'] = $slaves[$n['node_id']] ?? [];
                $n['online'] = false;
                if ($s && $s['last_seen']) {
                    $n['online'] = (time() - strtotime($s['last_seen'])) <= $offlineSec;
                }
            }
            unset($n);

            json_out(['status' => 'success', 'nodes' => $nodes]);
        }

        case 'history': {
            $node  = $_GET['node'] ?? '';
            $field = $_GET['field'] ?? 'voltage';
            $hours = max(1, min(168, (int) ($_GET['hours'] ?? 24)));

            $allowed = ['voltage', 'current_amp', 'power_watt', 'energy', 'wifi_rssi'];
            if (!in_array($field, $allowed, true)) {
                json_out(['status' => 'error', 'message' => 'field tidak valid'], 400);
            }

            $st = $pdo->prepare("SELECT created_at, `$field` AS val FROM telemetry
                WHERE node_id = ? AND `$field` IS NOT NULL AND created_at >= NOW() - INTERVAL ? HOUR
                ORDER BY created_at ASC");
            $st->execute([$node, $hours]);

            $points = [];
            foreach ($st->fetchAll() as $r) {
                $points[] = [date('Y-m-d H:i:s', strtotime($r['created_at'])), (float) $r['val']];
            }
            json_out(['status' => 'success', 'field' => $field, 'points' => $points]);
        }

        case 'stats': {
            $states = $pdo->query('SELECT * FROM device_state')->fetchAll();

            $totalNodes = (int) $pdo->query('SELECT COUNT(*) FROM nodes WHERE enabled = 1')->fetchColumn();
            $offlineSec = NODE_OFFLINE_SECONDS;

            $online   = 0;
            $lightsOn = 0;
            $totalWatt = 0.0;
            $totalEnergy = 0.0;
            $voltSum  = 0.0;
            $voltN    = 0;

            foreach ($states as $s) {
                $isOnline = $s['last_seen'] && (time() - strtotime($s['last_seen'])) <= $offlineSec;
                if (!$isOnline) {
                    continue;
                }
                $online++;
                if ((int) $s['gateway_state'] === 1) {
                    $lightsOn++;
                    $totalWatt += (float) ($s['power_watt'] ?? 0);
                }
                $totalEnergy += (float) ($s['energy'] ?? 0);
                if ($s['voltage'] !== null) {
                    $voltSum += (float) $s['voltage'];
                    $voltN++;
                }
            }

            json_out([
                'status'        => 'success',
                'total_nodes'   => $totalNodes,
                'online'        => $online,
                'lights_on'     => $lightsOn,
                'lights_off'    => $online - $lightsOn,
                'total_watt'    => round($totalWatt, 1),
                'total_energy'  => round($totalEnergy, 2),
                'avg_voltage'   => $voltN ? round($voltSum / $voltN, 1) : null,
            ]);
        }

        case 'schedules': {
            $list = $pdo->query('SELECT node_id, gateway_state, control_mode, on_schedule, off_schedule, last_seen FROM device_state ORDER BY node_id')->fetchAll();
            json_out(['status' => 'success', 'schedules' => $list]);
        }

        case 'devices': {
            $list = $pdo->query('SELECT node_id, name FROM nodes WHERE enabled = 1 ORDER BY node_id')->fetchAll();
            json_out(['status' => 'success', 'devices' => $list]);
        }

        case 'commands': {
            $node = $_GET['node'] ?? '';
            if ($node !== '') {
                $st = $pdo->prepare('SELECT * FROM commands WHERE node_id = ? ORDER BY id DESC LIMIT 20');
                $st->execute([$node]);
                $list = $st->fetchAll();
            } else {
                $list = $pdo->query('SELECT * FROM commands ORDER BY id DESC LIMIT 30')->fetchAll();
            }
            json_out(['status' => 'success', 'commands' => $list]);
        }

        case 'notifications': {
            $list = $pdo->query('SELECT * FROM notifications ORDER BY id DESC LIMIT 30')->fetchAll();
            json_out(['status' => 'success', 'notifications' => $list]);
        }

        case 'settings': {
            json_out([
                'status'   => 'success',
                'settings' => [
                    'wa_number' => setting_get('wa_number', WA_DEFAULT_NUMBER),
                    'wa_notify' => setting_get('wa_notify', '1'),
                    'api_key'   => API_KEY,
                    'endpoint'  => API_ENDPOINT_BASE . '&lt;node_id&gt;',
                    'devices'   => DEVICES,
                ],
            ]);
        }

        default:
            json_out(['status' => 'error', 'message' => 'act tidak dikenal'], 400);
    }
} catch (Throwable $e) {
    json_out(['status' => 'error', 'message' => $e->getMessage()], 500);
}
