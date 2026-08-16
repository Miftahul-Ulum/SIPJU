<?php
// ============================================================
// PJU MONITORING - DASHBOARD UTAMA
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_login();
$user = current_user();
$jsConfig = json_encode([
    'broker' => [
        'host'   => MQTT_HOST,
        'port'   => MQTT_WS_PORT,
        'wss'    => MQTT_USE_WSS,
        'path'   => MQTT_PATH,
        'user'   => MQTT_USER,
        'pass'   => MQTT_PASS,
        'client' => MQTT_CLIENT_ID,
    ],
    'topics' => [
        'data' => MQTT_TOPIC_DATA,
        'cmd'  => MQTT_TOPIC_CMD,
    ],
    'wa' => setting_get('wa_number', WA_DEFAULT_NUMBER),
]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/mqtt@5.3.5/dist/mqtt.min.js"></script>
    <style>
        #map { height: 360px; border-radius: 1rem; z-index: 0; }
        .leaflet-container { background: #e2e8f0; }
        .mqtt-offline .node-live { opacity: .55; }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">

    <nav class="sticky top-0 z-50 border-b border-slate-200 bg-white/90 shadow-sm backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-3">
            <a href="index.php" class="flex items-center gap-2 text-sm font-black tracking-tight text-slate-900">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-100 text-lg">🛣️</span>
                <span><?= APP_NAME ?> <span class="text-sky-600">Monitoring</span></span>
            </a>
            <div class="flex items-center gap-2 text-[11px] font-bold">
                <span id="mqttBadge" class="rounded-full border border-slate-200 bg-gray-100 px-3 py-1.5 text-slate-500">MQTT: …</span>
                <span id="modeBadge" class="rounded-full border border-slate-200 bg-gray-100 px-3 py-1.5 text-slate-500">Mode: …</span>
                <a href="nodes.php" class="rounded-full border border-slate-200 px-3 py-1.5 text-slate-600 hover:bg-slate-100">Tiang</a>
                <a href="schedules.php" class="rounded-full border border-slate-200 px-3 py-1.5 text-slate-600 hover:bg-slate-100">Jadwal</a>
                <a href="logout.php" class="rounded-full bg-slate-800 px-3 py-1.5 text-white hover:bg-slate-700">Keluar</a>
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-7xl px-5 py-6 space-y-6">

        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900">Dashboard <?= APP_NAME ?></h1>
                <p class="text-sm text-slate-500"><?= APP_FULL ?> — <?= date('l, d F Y') ?></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button id="btnModeAuto" onclick="setMode('auto')" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-black uppercase tracking-wider transition hover:bg-slate-100">🤖 Otomatis</button>
                <button id="btnModeManual" onclick="setMode('manual')" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-black uppercase tracking-wider transition hover:bg-slate-100">🖐️ Manual</button>
                <button onclick="testWA()" class="rounded-full border border-green-300 px-4 py-2 text-xs font-black text-green-700 transition hover:bg-green-50">💬 Test WhatsApp</button>
            </div>
        </div>

        <!-- Statistik -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">Tiang PJU</p>
                <p id="statTotal" class="mt-2 text-3xl font-black text-slate-900">–</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">Lampu Menyala</p>
                <p id="statOn" class="mt-2 text-3xl font-black text-green-600">–</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">Daya Total</p>
                <p id="statWatt" class="mt-2 text-3xl font-black text-sky-600">– <span class="text-base text-slate-400">W</span></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">Tegangan Rata²</p>
                <p id="statVolt" class="mt-2 text-3xl font-black text-amber-600">– <span class="text-base text-slate-400">V</span></p>
            </div>
        </div>

        <!-- Live Nodes -->
        <section>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-black text-slate-900">Kondisi Tiang Secara Langsung</h2>
                <span id="lastUpdate" class="text-[11px] text-slate-400">belum ada data</span>
            </div>
            <div id="nodeGrid" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"></div>
        </section>

        <!-- Grafik Riwayat -->
        <section>
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <h2 class="text-lg font-black text-slate-900">Grafik Riwayat Sensor</h2>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <select id="chartNode" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-700 outline-none focus:border-sky-500"></select>
                    <select id="chartField" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-700 outline-none focus:border-sky-500">
                        <option value="light_level">Intensitas Cahaya</option>
                        <option value="temperature">Suhu (°C)</option>
                        <option value="humidity">Kelembapan (%)</option>
                        <option value="voltage">Tegangan (V)</option>
                        <option value="power_watt">Daya (W)</option>
                    </select>
                    <select id="chartHours" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-700 outline-none focus:border-sky-500">
                        <option value="1">1 jam</option>
                        <option value="6">6 jam</option>
                        <option value="24" selected>24 jam</option>
                        <option value="48">48 jam</option>
                        <option value="168">7 hari</option>
                    </select>
                    <button onclick="loadChart()" class="rounded-lg bg-sky-500 px-4 py-2 font-black text-white hover:bg-sky-400">Muat</button>
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <canvas id="historyChart"></canvas>
            </div>
        </section>

        <!-- Peta & Notifikasi -->
        <section class="grid gap-6 lg:grid-cols-2">
            <div>
                <h2 class="mb-3 text-lg font-black text-slate-900">Peta Lokasi Tiang PJU</h2>
                <div id="map" class="border border-slate-200 shadow-sm"></div>
            </div>
            <div>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-black text-slate-900">Notifikasi</h2>
                    <button onclick="markRead()" class="rounded-full border border-slate-300 px-3 py-1.5 text-[11px] font-bold text-slate-600 hover:bg-slate-100">Tandai dibaca</button>
                </div>
                <div id="notifBox" class="max-h-[360px] space-y-2 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-3 shadow-sm"></div>
            </div>
        </section>

        <footer class="pb-6 text-center text-[11px] text-slate-400">
            <?= APP_NAME ?> — <?= APP_FULL ?> · Sistem Monitoring PJU berbasis ESP-Now &amp; Internet of Things · Skripsi
        </footer>
    </main>

<script>
const CFG = <?= $jsConfig ?>;
const USER = <?= json_encode($user['name'] ?: $user['username']) ?>;

// ---------- Elemen ----------
const $ = id => document.getElementById(id);

// ---------- MQTT ----------
let mqttClient = null;
let connected = false;
let liveData = {};      // node_id -> data terakhir
let nodeList = [];

function mqttUrl() {
    if (!CFG.broker.host) return null;
    const proto = CFG.broker.wss ? 'wss' : 'ws';
    return `${proto}://${CFG.broker.host}:${CFG.broker.port}${CFG.broker.path}`;
}

function connectMQTT() {
    const url = mqttUrl();
    if (!url) { setMQTTBadge(false, 'broker belum diatur'); return; }
    const opts = { clientId: CFG.broker.client + '_' + Math.random().toString(16).slice(2, 8) };
    if (CFG.broker.user) { opts.username = CFG.broker.user; opts.password = CFG.broker.pass; }

    try {
        mqttClient = mqtt.connect(url, opts);
    } catch (e) { setMQTTBadge(false, 'gagal koneksi'); return; }

    mqttClient.on('connect', () => {
        connected = true;
        setMQTTBadge(true);
        mqttClient.subscribe(CFG.topics.data, { qos: 0 }, err => {
            if (err) console.error('subscribe gagal', err);
        });
    });
    mqttClient.on('message', (topic, payload) => {
        try {
            const msg = JSON.parse(payload.toString());
            let nodeId = msg.node_id || msg.id || (topic.split('/').filter(Boolean)[1] || '');
            if (!nodeId) return;
            msg.node_id = nodeId;
            handleSensor(nodeId, msg);
        } catch (e) { console.warn('pesan tidak valid', e); }
    });
    mqttClient.on('close', () => { connected = false; setMQTTBadge(false, 'terputus'); });
    mqttClient.on('error', e => { connected = false; setMQTTBadge(false, 'error'); console.warn(e); });
}

function publishCmd(nodeId, cmd, value) {
    if (!connected || !mqttClient) return false;
    // CFG.topics.cmd = "pju/cmd" -> base "pju"
    const base = CFG.topics.cmd.replace(/\/cmd$/, '');
    // Kontrol lampu  : pju/<node_id>/cmd
    // Kontrol mode   : pju/cmd
    const topic = nodeId === '*' ? base + '/cmd' : base + '/' + nodeId + '/cmd';
    const msg = JSON.stringify(value !== undefined ? { cmd, value } : { cmd });
    mqttClient.publish(topic, msg);
    return true;
}

function setMQTTBadge(ok, label) {
    const el = $('mqttBadge');
    el.textContent = 'MQTT: ' + (label || (ok ? 'terhubung' : 'tidak terhubung'));
    el.className = 'rounded-full px-3 py-1.5 text-[11px] font-bold border ' +
        (ok ? 'border-green-200 bg-green-100 text-green-700'
            : 'border-red-200 bg-red-100 text-red-700');
}

// ---------- Penyimpanan ke DB ----------
function handleSensor(nodeId, msg) {
    liveData[nodeId] = { ...msg, lastSeen: Date.now() };

    // Simpan ke database
    fetch('api/store.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(msg),
    }).catch(() => {});

    renderNodes();
    updateStats();
    updateMap();

    const t = new Date();
    $('lastUpdate').textContent = 'terakhir update: ' + t.toLocaleTimeString('id-ID');
}

// ---------- Render kartu node ----------
function renderNodes() {
    const grid = $('nodeGrid');
    grid.innerHTML = nodeList.map(n => {
        const live = liveData[n.node_id];
        const base = n.live || {};
        const data = live || base;
        const offline = live ? false : (n.offline || !base);

        const lampOn = data.lamp_status == 1;
        const light = (data.light_level != null) ? Number(data.light_level).toFixed(1) : '–';
        const temp  = (data.temperature != null) ? Number(data.temperature).toFixed(1) : '–';
        const hum   = (data.humidity != null) ? Number(data.humidity).toFixed(1) : '–';
        const volt  = (data.voltage != null) ? (Number(data.voltage) / 1).toFixed(1) : '–';
        const watt  = (data.power_watt != null) ? Number(data.power_watt).toFixed(1) : '–';
        const motion = data.motion == 1;

        const lampBadge = offline ? 'bg-slate-100 text-slate-500' : (lampOn ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700');
        const cardBorder = offline ? 'border-slate-200' : (lampOn ? 'border-green-300' : 'border-red-300');

        return `
        <div class="rounded-2xl border ${cardBorder} bg-white p-5 shadow-sm transition">
          <div class="flex items-start justify-between gap-2">
            <div>
              <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">${n.node_id}</p>
              <h3 class="text-base font-black text-slate-900">${esc(n.name)}</h3>
              <p class="mt-0.5 text-[11px] text-slate-500">${esc(n.location || 'Lokasi belum diatur')}</p>
            </div>
            <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase ${lampBadge}">${offline ? 'Offline' : (lampOn ? 'Menyala' : 'Mati')}</span>
          </div>
          <div class="mt-4 grid grid-cols-2 gap-2 text-[12px]">
            <div class="rounded-xl bg-slate-100 p-2.5"><p class="text-slate-500">💡 Cahaya</p><p class="font-black text-slate-900">${light}%</p></div>
            <div class="rounded-xl bg-slate-100 p-2.5"><p class="text-slate-500">🌡️ Suhu</p><p class="font-black text-slate-900">${temp}°C</p></div>
            <div class="rounded-xl bg-slate-100 p-2.5"><p class="text-slate-500">💧 Kelembapan</p><p class="font-black text-slate-900">${hum}%</p></div>
            <div class="rounded-xl bg-slate-100 p-2.5"><p class="text-slate-500">⚡ Tegangan</p><p class="font-black text-slate-900">${volt}V</p></div>
            <div class="rounded-xl bg-slate-100 p-2.5"><p class="text-slate-500">🔌 Daya</p><p class="font-black text-slate-900">${watt}W</p></div>
            <div class="rounded-xl bg-slate-100 p-2.5"><p class="text-slate-500">👤 Gerakan</p><p class="font-black text-slate-900">${motion ? 'Terdeteksi' : 'Tidak ada'}</p></div>
          </div>
          <div class="mt-4 flex gap-2">
            <button onclick="setLamp('${n.node_id}',1)" class="flex-1 rounded-full bg-green-500 py-2 text-[11px] font-black text-white transition hover:bg-green-400 active:scale-95">Nyalakan</button>
            <button onclick="setLamp('${n.node_id}',0)" class="flex-1 rounded-full bg-red-500 py-2 text-[11px] font-black text-white transition hover:bg-red-400 active:scale-95">Matikan</button>
          </div>
        </div>`;
    }).join('');
}

function esc(s) { return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])); }

// ---------- Statistik ----------
function updateStats() {
    fetch('api/data.php?act=stats').then(r => r.json()).then(d => {
        if (!d || d.status !== 'success') return;
        $('statTotal').textContent = d.total_nodes ?? '–';
        $('statOn').textContent = (d.lights_on ?? '–');
        $('statWatt').innerHTML = (d.total_watt ?? '–') + ' <span class="text-base text-slate-400">W</span>';
        $('statVolt').innerHTML = (d.avg_voltage != null ? d.avg_voltage : '–') + ' <span class="text-base text-slate-400">V</span>';
    }).catch(() => {});
}

function updateMode(mode) {
    $('modeBadge').textContent = 'Mode: ' + (mode === 'manual' ? 'Manual 🖐️' : 'Otomatis 🤖');
    $('modeBadge').className = 'rounded-full px-3 py-1.5 text-[11px] font-bold border ' +
        (mode === 'manual' ? 'border-amber-200 bg-amber-100 text-amber-700' : 'border-sky-200 bg-sky-100 text-sky-700');
    $('btnModeAuto').className = 'rounded-full px-4 py-2 text-xs font-black uppercase tracking-wider transition border ' +
        (mode === 'auto' ? 'bg-sky-500 border-sky-500 text-white' : 'border-slate-300 text-slate-600 hover:bg-slate-100');
    $('btnModeManual').className = 'rounded-full px-4 py-2 text-xs font-black uppercase tracking-wider transition border ' +
        (mode === 'manual' ? 'bg-amber-500 border-amber-500 text-white' : 'border-slate-300 text-slate-600 hover:bg-slate-100');
}

function loadSettings() {
    fetch('api/data.php?act=settings').then(r => r.json()).then(d => {
        if (d && d.status === 'success') updateMode(d.settings.mode);
    }).catch(() => {});
}

function setMode(mode) {
    fetch('api/action.php?action=save_mode', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'mode=' + mode,
    }).then(r => r.json()).then(d => {
        if (d.status === 'success') {
            updateMode(mode);
            publishCmd('*', 'mode', mode);
            logControl('*', 'MODE_' + mode.toUpperCase(), 'web');
        }
    });
}

function setLamp(nodeId, on) {
    publishCmd(nodeId, on ? 'on' : 'off');
    fetch('api/action.php?action=log_control', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'node_id=' + encodeURIComponent(nodeId) + '&control_action=' + (on ? 'ON' : 'OFF') + '&source=web',
    });
    addNotif('control', nodeId, 'Lampu ' + nodeId + ' dinyalakan/dimatikan dari dashboard');
}

function logControl(nodeId, act, src) {
    fetch('api/action.php?action=log_control', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'node_id=' + encodeURIComponent(nodeId) + '&control_action=' + encodeURIComponent(act) + '&source=' + src,
    }).catch(() => {});
}

// ---------- Grafik riwayat ----------
let historyChart = null;
function loadChart() {
    const node = $('chartNode').value;
    const field = $('chartField').value;
    const hours = $('chartHours').value;
    fetch(`api/data.php?act=history&node=${encodeURIComponent(node)}&field=${field}&hours=${hours}`)
        .then(r => r.json()).then(d => {
            if (!d || d.status !== 'success') return;
            const values = d.points.map(p => p[1]);
            const labelsChart = d.points.map(p => p[0].slice(11));
            if (historyChart) historyChart.destroy();
            const titleMap = { light_level: 'Intensitas Cahaya (%)', temperature: 'Suhu (°C)', humidity: 'Kelembapan (%)', voltage: 'Tegangan (V)', power_watt: 'Daya (W)' };
            historyChart = new Chart($('historyChart'), {
                type: 'line',
                data: {
                    labels: labelsChart,
                    datasets: [{
                        label: titleMap[field] || field,
                        data: values,
                        borderColor: '#0284c7',
                        backgroundColor: 'rgba(2,132,199,.10)',
                        fill: true,
                        tension: .35,
                        pointRadius: 0,
                        borderWidth: 2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#64748b' } } },
                    scales: {
                        x: { ticks: { color: '#94a3b8', maxTicksLimit: 8 }, grid: { color: '#e2e8f0' } },
                        y: { beginAtZero: true, ticks: { color: '#64748b' }, grid: { color: '#e2e8f0' } },
                    },
                },
            });
            $('historyChart').parentElement.style.height = '320px';
        }).catch(() => {});
}

// ---------- Peta ----------
let map = null;
let mapMarkers = [];
function initMap() {
    if (map) return;
    map = L.map('map').setView([-6.59, 110.67], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap',
    }).addTo(map);
}

function updateMap() {
    if (!map) return;
    const items = nodeList.map(n => {
        const data = liveData[n.node_id] || n.live || {};
        const offline = !liveData[n.node_id] && n.offline;
        const lampOn = data.lamp_status == 1;
        return { n, data, offline, lampOn };
    }).filter(x => x.n.lat != null && x.n.lng != null);

    mapMarkers.forEach(m => map.removeLayer(m));
    mapMarkers = [];

    items.forEach(x => {
        const color = x.offline ? '#94a3b8' : (x.lampOn ? '#22c55e' : '#ef4444');
        const icon = L.divIcon({
            className: '',
            html: `<div style="width:18px;height:18px;border-radius:50%;background:${color};border:3px solid #fff;box-shadow:0 0 8px ${color}66"></div>`,
            iconSize: [18, 18],
        });
        const m = L.marker([x.n.lat, x.n.lng], { icon }).addTo(map)
            .bindPopup(`<b>${esc(x.n.name)}</b><br>${esc(x.n.node_id)}<br>Status: ${x.offline ? 'Offline' : (x.lampOn ? 'Menyala' : 'Mati')}`);
        mapMarkers.push(m);
    });

    if (items.length) {
        const bounds = L.latLngBounds(items.map(x => [x.n.lat, x.n.lng]));
        map.fitBounds(bounds, { padding: [40, 40] });
    }
}

// ---------- Notifikasi ----------
function loadNotifs() {
    fetch('api/data.php?act=notifications').then(r => r.json()).then(d => {
        if (!d || d.status !== 'success') return;
        const box = $('notifBox');
        if (!d.notifications.length) { box.innerHTML = '<p class="p-3 text-sm text-slate-400">Belum ada notifikasi.</p>'; return; }
        box.innerHTML = d.notifications.map(n => {
            const icon = n.type === 'error' ? '🚨' : (n.type === 'wa' ? '💬' : (n.type === 'control' ? '🎛️' : 'ℹ️'));
            const color = n.type === 'error' ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-slate-50';
            return `<div class="rounded-xl border p-3 text-[12px] ${color} ${n.is_read ? 'opacity-60' : ''}">
                <p class="font-bold text-slate-800">${icon} ${esc(n.message)}</p>
                <p class="mt-1 text-[10px] text-slate-400">${esc(n.created_at)} · ${esc(n.type || 'info')}</p>
            </div>`;
        }).join('');
    }).catch(() => {});
}

function addNotif(type, nodeId, message) {
    fetch('api/action.php?action=add_notification', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'type=' + encodeURIComponent(type) + '&node_id=' + encodeURIComponent(nodeId || '') + '&message=' + encodeURIComponent(message),
    }).then(() => loadNotifs());
}

function markRead() {
    fetch('api/action.php?action=mark_notif_read', { method: 'POST' }).then(() => loadNotifs());
}

// ---------- WhatsApp ----------
function testWA() {
    fetch('api/action.php?action=test_wa').then(r => r.json()).then(d => {
        if (d.status === 'success') window.open(d.whatsapp_url, '_blank');
    });
}

// ---------- Scheduler (mode otomatis) ----------
let schedules = [];
function loadSchedules() {
    return fetch('api/data.php?act=schedules').then(r => r.json()).then(d => {
        schedules = (d && d.schedules) ? d.schedules : [];
    }).catch(() => { schedules = []; });
}

function schedulerTick() {
    // Hanya aktif saat mode auto
    fetch('api/data.php?act=settings').then(r => r.json()).then(d => {
        if (!d || d.status !== 'success' || d.settings.mode !== 'auto') return;
        if (!connected || !mqttClient) return;
        const now = new Date();
        const hm = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
        const days = ['sun','mon','tue','wed','thu','fri','sat'];
        const today = days[now.getDay()];

        schedules.forEach(s => {
            if (!s.enabled) return;
            const dayOk = s.day_of_week === 'all' || String(s.day_of_week).split(',').includes(today);
            if (!dayOk) return;
            const start = String(s.start_time).slice(0,5);
            const end = String(s.end_time).slice(0,5);
            const inRange = (hm >= start && hm <= end);
            const targets = s.node_id === '*' ? nodeList.map(n => n.node_id) : [s.node_id];
            targets.forEach(nid => {
                const shouldOn = inRange ? 1 : 0;
                // Publish hanya jika berbeda dengan status saat ini
                const cur = (liveData[nid] && liveData[nid].lamp_status != null) ? liveData[nid].lamp_status : null;
                if (cur === null || cur != shouldOn) {
                    publishCmd(nid, shouldOn ? 'on' : 'off');
                    logControl(nid, shouldOn ? 'ON' : 'OFF', 'schedule');
                    addNotif('info', nid, 'Jadwal otomatis: lampu ' + nid + ' ' + (shouldOn ? 'dinyalakan' : 'dimatikan'));
                }
            });
        });
    }).catch(() => {});
}

// ---------- Inisialisasi ----------
async function init() {
    initMap();
    loadSettings();

    const nodesRes = await fetch('api/data.php?act=nodes').then(r => r.json()).catch(() => null);
    nodeList = (nodesRes && nodesRes.nodes) || [];

    // Isi selector node
    const sel = $('chartNode');
    nodeList.forEach(n => {
        const o = document.createElement('option');
        o.value = n.node_id;
        o.textContent = n.name + ' (' + n.node_id + ')';
        sel.appendChild(o);
    });

    renderNodes();
    updateStats();
    loadChart();
    loadNotifs();
    loadSchedules().then(() => schedulerTick());
    updateMap();

    // MQTT
    connectMQTT();

    // Scheduler tiap 30 detik
    setInterval(() => { schedulerTick(); }, 30000);
    // Refresh statistik & status offline tiap 30 detik
    setInterval(() => { updateStats(); renderNodes(); }, 30000);
    // Rekontek MQTT tiap 60 detik jika terputus
    setInterval(() => { if (!connected && mqttUrl()) connectMQTT(); }, 60000);
}

window.addEventListener('load', init);
</script>
</body>
</html>
