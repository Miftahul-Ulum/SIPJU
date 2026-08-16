<?php
// ============================================================
// API: AMBIL DATA UNTUK DASHBOARD
//   GET api/data.php?act=nodes          -> daftar node + pembacaan terakhir
//   GET api/data.php?act=history&node=..&field=..&hours=..
//   GET api/data.php?act=stats
//   GET api/data.php?act=schedules
//   GET api/data.php?act=notifications
//   GET api/data.php?act=settings
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
session_start();

$act = $_GET['act'] ?? 'nodes';

try {
    $pdo = db();

    switch ($act) {

        case 'nodes': {
            $nodes = $pdo->query('SELECT * FROM nodes WHERE enabled = 1 ORDER BY node_id')->fetchAll();

            // Pembacaan sensor terakhir per node
            $latest = [];
            $st = $pdo->prepare('SELECT sd.* FROM sensor_data sd
                JOIN (SELECT node_id, MAX(id) AS maxid FROM sensor_data GROUP BY node_id) m
                ON sd.node_id = m.node_id AND sd.id = m.maxid');
            $st->execute();
            foreach ($st->fetchAll() as $r) {
                $latest[$r['node_id']] = $r;
            }

            $offlineSec = NODE_OFFLINE_SECONDS;
            foreach ($nodes as &$n) {
                $cur = $latest[$n['node_id']] ?? null;
                $n['live'] = $cur;
                if ($cur) {
                    $last = strtotime($cur['created_at']);
                    $n['offline'] = (time() - $last) > $offlineSec;
                } else {
                    $n['offline'] = true;
                }
            }
            unset($n);
            json_out(['status' => 'success', 'nodes' => $nodes]);
        }

        case 'history': {
            $node   = $_GET['node'] ?? '';
            $field  = $_GET['field'] ?? 'light_level';
            $hours  = max(1, min(168, (int) ($_GET['hours'] ?? 24)));

            $allowed = ['light_level','temperature','humidity','voltage','current_amp','power_watt'];
            if (!in_array($field, $allowed, true)) {
                json_out(['status' => 'error', 'message' => 'field tidak valid'], 400);
            }

            $st = $pdo->prepare("SELECT created_at, `$field` AS val FROM sensor_data
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
            // Mode saat ini
            $mode = setting_get('mode', 'auto');

            // Jumlah node
            $totalNodes = (int) $pdo->query('SELECT COUNT(*) FROM nodes WHERE enabled = 1')->fetchColumn();

            // Lampu menyala dari data terakhir
            $st = $pdo->query('SELECT sd.node_id, sd.lamp_status, sd.power_watt, sd.created_at FROM sensor_data sd
                JOIN (SELECT node_id, MAX(id) AS maxid FROM sensor_data GROUP BY node_id) m
                ON sd.node_id = m.node_id AND sd.id = m.maxid');
            $lightsOn = 0;
            $totalWatt = 0.0;
            $activeRows = 0;
            foreach ($st->fetchAll() as $r) {
                if ((time() - strtotime($r['created_at'])) <= NODE_OFFLINE_SECONDS) {
                    $activeRows++;
                    if ((int) $r['lamp_status'] === 1) {
                        $lightsOn++;
                        $totalWatt += (float) ($r['power_watt'] ?? 0);
                    }
                }
            }

            // Rata-rata tegangan (24 jam terakhir)
            $avgVolt = $pdo->query('SELECT ROUND(AVG(voltage),1) FROM sensor_data WHERE voltage IS NOT NULL AND created_at >= NOW() - INTERVAL 24 HOUR')->fetchColumn();

            // Total data tersimpan
            $totalRows = (int) $pdo->query('SELECT COUNT(*) FROM sensor_data')->fetchColumn();

            json_out([
                'status'    => 'success',
                'mode'      => $mode,
                'total_nodes' => $totalNodes,
                'lights_on'   => $lightsOn,
                'lights_off'  => $activeRows - $lightsOn,
                'total_watt'  => round($totalWatt, 1),
                'avg_voltage' => $avgVolt === null ? null : (float) $avgVolt,
                'total_rows'  => $totalRows,
            ]);
        }

        case 'schedules': {
            $list = $pdo->query('SELECT * FROM schedules ORDER BY id DESC')->fetchAll();
            json_out(['status' => 'success', 'schedules' => $list]);
        }

        case 'notifications': {
            $list = $pdo->query('SELECT * FROM notifications ORDER BY id DESC LIMIT 30')->fetchAll();
            json_out(['status' => 'success', 'notifications' => $list]);
        }

        case 'settings': {
            json_out([
                'status'    => 'success',
                'settings'  => [
                    'mode'       => setting_get('mode', 'auto'),
                    'wa_number'  => setting_get('wa_number', WA_DEFAULT_NUMBER),
                    'wa_notify'  => setting_get('wa_notify', '1'),
                ],
            ]);
        }

        default:
            json_out(['status' => 'error', 'message' => 'act tidak dikenal'], 400);
    }
} catch (Throwable $e) {
    json_out(['status' => 'error', 'message' => $e->getMessage()], 500);
}
