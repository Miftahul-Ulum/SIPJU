<?php
// ============================================================
// SIPJU - DASHBOARD UTAMA
// Komunikasi ke device via REST API (POST telemetry / polling 5s).
// Kontrol via perintah yang dikirim ke tabel commands.
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_login();
$user = current_user();
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
    <style>
        #map { height: 360px; border-radius: 1rem; z-index: 0; }
        .leaflet-container { background: #e2e8f0; }
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
                <span id="onlineBadge" class="rounded-full border border-slate-200 bg-gray-100 px-3 py-1.5 text-slate-500">Online: …</span>
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
            <div class="flex items-center gap-2">
                <span id="lastUpdate" class="text-[11px] text-slate-400">belum ada data</span>
                <button onclick="testWA()" class="rounded-full border border-green-300 px-4 py-2 text-xs font-black text-green-700 transition hover:bg-green-50">💬 Test WhatsApp</button>
            </div>
        </div>

        <!-- Statistik -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">Tiang PJU</p>
                <p id="statTotal" class="mt-2 text-3xl font-black text-slate-900">–</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">Online</p>
                <p id="statOnline" class="mt-2 text-3xl font-black text-sky-600">–</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">Lampu Menyala</p>
                <p id="statOn" class="mt-2 text-3xl font-black text-green-600">–</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">Daya Total</p>
                <p id="statWatt" class="mt-2 text-3xl font-black text-amber-600">– <span class="text-base text-slate-400">W</span></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">Tegangan Rata²</p>
                <p id="statVolt" class="mt-2 text-3xl font-black text-indigo-600">– <span class="text-base text-slate-400">V</span></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">Energi Total</p>
                <p id="statEnergy" class="mt-2 text-3xl font-black text-emerald-600">– <span class="text-base text-slate-400">Wh</span></p>
            </div>
        </div>

        <!-- Live Devices -->
        <section>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-black text-slate-900">Kondisi Gateway Secara Langsung</h2>
            </div>
            <div id="nodeGrid" class="space-y-6"></div>
        </section>

        <!-- Grafik Riwayat -->
        <section>
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <h2 class="text-lg font-black text-slate-900">Grafik Riwayat Telemetri</h2>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <select id="chartNode" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-700 outline-none focus:border-sky-500"></select>
                    <select id="chartField" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-700 outline-none focus:border-sky-500">
                        <option value="voltage">Tegangan (V)</option>
                        <option value="current_amp">Arus (A)</option>
                        <option value="power_watt">Daya (W)</option>
                        <option value="energy">Energi (Wh)</option>
                        <option value="wifi_rssi">RSSI WiFi (dBm)</option>
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
                <h2 class="mb-3 text-lg font-black text-slate-900">Peta Lokasi Gateway</h2>
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
            <?= APP_NAME ?> — <?= APP_FULL ?> · ESP-Now &amp; Internet of Things · Endpoint: <span class="text-sky-600"><?= htmlspecialchars(API_ENDPOINT_BASE) ?>&lt;node_id&gt;</span>
        </footer>
    </main>

    <!-- Toast -->
    <div id="toast" class="fixed bottom-5 right-5 z-50 hidden max-w-sm rounded-xl border px-4 py-3 text-sm font-bold shadow-xl"></div>

<script>
const USER = <?= json_encode($user['name'] ?: $user['username']) ?>;
const $ = id => document.getElementById(id);

let nodes = [];
let map = null;
let mapMarkers = [];
let historyChart = null;

function esc(s) { return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])); }

function toast(msg, ok) {
    const t = $('toast');
    t.innerHTML = (ok ? '✅ ' : '⚠️ ') + esc(msg);
    t.className = 'fixed bottom-5 right-5 z-50 max-w-sm rounded-xl border px-4 py-3 text-sm font-bold shadow-xl ' +
        (ok ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700');
    t.classList.remove('hidden');
    clearTimeout(t._h);
    t._h = setTimeout(() => t.classList.add('hidden'), 4000);
}

function fmtUptime(s) {
    s = Number(s) || 0;
    const d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60);
    return d ? d + 'h ' + h + 'j ' + m + 'm' : (h ? h + 'j ' + m + 'm' : m + 'm');
}

// ============================================================
// AMBIL DATA (polling)
// ============================================================
async function loadData() {
    const d = await fetch('api/data.php?act=nodes').then(r => r.json()).catch(() => null);
    if (!d || d.status !== 'success') return;
    nodes = d.nodes;
    renderNodes();
    updateStats();
    updateMap();
    $('lastUpdate').textContent = 'terakhir refresh: ' + new Date().toLocaleTimeString('id-ID');
}

// ============================================================
// KIRIM PERINTAH
// ============================================================
async function sendCommand(nodeId, type, extra = {}) {
    const body = new URLSearchParams({ action: 'send_command', node_id: nodeId, type });
    for (const [k, v] of Object.entries(extra)) if (v !== undefined && v !== '') body.set(k, v);
    const d = await fetch('api/action.php?action=send_command', { method: 'POST', body }).then(r => r.json());
    toast(d.message || 'Perintah diproses', d.status === 'success');
    loadData();
}

function setMode(nodeId, mode) { sendCommand(nodeId, 'SET_MODE', { control_mode: mode }); }
function setLamp(nodeId, on) { sendCommand(nodeId, on ? 'STATE_ON' : 'STATE_OFF'); }
function restart(nodeId) { if (confirm('Restart device ' + nodeId + '?')) sendCommand(nodeId, 'RESTART_DEVICE'); }

// ============================================================
// RENDER KARTU DEVICE
// ============================================================
function renderNodes() {
    const grid = $('nodeGrid');
    if (!nodes.length) { grid.innerHTML = '<p class="text-sm text-slate-400">Belum ada node terdaftar.</p>'; return; }

    grid.innerHTML = nodes.map(n => {
        const s = n.state || {};
        const online = n.online;
        const lampOn = s.gateway_state == 1;
        const mode = s.control_mode || 'SCHEDULE';

        const badge = !online ? ['Offline', 'bg-slate-100 text-slate-500 border-slate-200']
            : lampOn ? ['Menyala', 'bg-green-100 text-green-700 border-green-200']
            : ['Mati', 'bg-red-100 text-red-700 border-red-200'];
        const border = !online ? 'border-slate-200' : (lampOn ? 'border-green-300' : 'border-red-300');

        const v = (x, d = '–', dec = 1) => (x !== null && x !== undefined) ? Number(x).toFixed(dec) : d;
        const gps = (s.latitude && s.longitude) ? `${Number(s.latitude).toFixed(5)}, ${Number(s.longitude).toFixed(5)}` : '–';

        const statCell = (label, value, sub, color) => `
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">${label}</p>
                <p class="mt-1 text-2xl font-black ${color}">${value}${sub ? ` <span class="text-sm font-bold text-slate-400">${sub}</span>` : ''}</p>
            </div>`;

        const slaves = n.slaves || [];

        const slaveCount = Math.max(1, Number(n.slave_count) || slaves.length || 1);
        const slaveCard = sl => {
            const slOn = sl.state == 1, slOk = sl.lamp_ok == 1;
            return `
            <div class="rounded-2xl border p-4 ${!slOn ? 'border-red-200 bg-red-50/50' : (slOk ? 'border-green-200 bg-green-50/50' : 'border-amber-200 bg-amber-50/50')}">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Slave #${sl.slave_id}</p>
                        <h4 class="font-black text-slate-900">Tiang ${sl.slave_id}</h4>
                    </div>
                    <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase ${slOn ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}">${slOn ? 'Menyala' : 'Mati'}</span>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2 text-[12px]">
                    <div class="rounded-xl border border-slate-100 bg-white p-2.5"><p class="text-[10px] text-slate-500">Relay</p><p class="font-black ${slOn ? 'text-green-700' : 'text-red-600'}">${slOn ? 'ON' : 'OFF'}</p></div>
                    <div class="rounded-xl border border-slate-100 bg-white p-2.5"><p class="text-[10px] text-slate-500">Lampu</p><p class="font-black ${slOk ? 'text-green-700' : 'text-red-600'}">${slOk ? 'OK' : 'ERROR'}</p></div>
                </div>
                <p class="mt-2 text-[10px] text-slate-400">update terakhir ${esc(sl.last_update || '–')}</p>
            </div>`;
        };
        const emptySlaveCard = id => `
            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Slave #${id}</p>
                        <h4 class="font-black text-slate-400">Tiang ${id}</h4>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase text-slate-400">Belum ada data</span>
                </div>
                <p class="mt-3 text-[11px] text-slate-400">Menunggu data dari slave #${id} ...</p>
            </div>`;
        const slaveCards = Array.from({ length: slaveCount }, (_, i) => {
            const sl = slaves.find(x => x.slave_id === i + 1);
            return sl ? slaveCard(sl) : emptySlaveCard(i + 1);
        });

        return `
        <div class="rounded-2xl border ${border} bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">${esc(n.node_id)}</p>
                    <h3 class="text-lg font-black text-slate-900">${esc(n.name)}</h3>
                    <p class="mt-0.5 text-[11px] text-slate-500">${esc(n.location || 'Lokasi belum diatur')}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-[11px] font-bold">
                    <span class="rounded-full border px-3 py-1 text-[10px] font-black uppercase ${badge[1]}">${badge[0]}</span>
                    <span class="rounded-full px-3 py-1 font-black ${mode === 'MANUAL' ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700'}">${mode}</span>
                    <span class="rounded-full px-3 py-1 font-black bg-slate-100 text-slate-600">FW ${esc(s.firmware_version || '–')}</span>
                    <span class="rounded-full px-3 py-1 font-black bg-slate-100 text-slate-600">⏰ ${esc(s.rtc_time || '–')}</span>
                </div>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
                ${statCell('Tiang PJU', esc(n.node_id), '', 'text-slate-900')}
                ${statCell('Online', online ? 'Ya' : 'Tidak', '', online ? 'text-sky-600' : 'text-slate-400')}
                ${statCell('Lampu Menyala', !online ? '–' : (lampOn ? 'ON' : 'OFF'), '', lampOn ? 'text-green-600' : 'text-red-500')}
                ${statCell('Daya Total', v(s.power_watt), 'W', 'text-amber-600')}
                ${statCell('Tegangan Rata²', v(s.voltage), 'V', 'text-indigo-600')}
                ${statCell('Energi Total', v(s.energy), 'Wh', 'text-emerald-600')}
            </div>

            <div class="mt-4 grid grid-cols-3 gap-2 text-[12px] sm:grid-cols-6">
                <div class="rounded-xl bg-slate-100 p-2.5"><p class="text-[10px] text-slate-500">🔌 Arus</p><p class="font-black text-slate-900">${v(s.current_amp)}<span class="text-[10px] text-slate-400">A</span></p></div>
                <div class="rounded-xl bg-slate-100 p-2.5"><p class="text-[10px] text-slate-500">📶 WiFi</p><p class="font-black text-slate-900">${v(s.wifi_rssi, '–', 0)}<span class="text-[10px] text-slate-400">dBm</span></p></div>
                <div class="rounded-xl bg-slate-100 p-2.5"><p class="text-[10px] text-slate-500">🕐 Uptime</p><p class="font-black text-slate-900">${fmtUptime(s.uptime)}</p></div>
                <div class="rounded-xl bg-slate-100 p-2.5"><p class="text-[10px] text-slate-500">🧠 RAM Bebas</p><p class="font-black text-slate-900">${v(s.free_heap, '–', 0)}<span class="text-[10px] text-slate-400">B</span></p></div>
                <div class="rounded-xl bg-slate-100 p-2.5"><p class="text-[10px] text-slate-500">🛰️ GPS</p><p class="font-black text-slate-900 text-[11px]">${esc(gps)}</p></div>
                <div class="rounded-xl bg-slate-100 p-2.5"><p class="text-[10px] text-slate-500">📡 Satelit</p><p class="font-black text-slate-900">${v(s.gps_satellites, '–', 0)}<span class="text-[10px] text-slate-400">HDOP ${v(s.gps_hdop)}</span></p></div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button onclick="setMode('${n.node_id}','${mode === 'MANUAL' ? 'SCHEDULE' : 'MANUAL'}')"
                    class="flex-1 min-w-[140px] rounded-full py-2 text-[11px] font-black text-white transition active:scale-95 ${mode === 'MANUAL' ? 'bg-sky-500 hover:bg-sky-400' : 'bg-amber-500 hover:bg-amber-400'}">
                    ${mode === 'MANUAL' ? 'Mode SCHEDULE' : 'Mode MANUAL'}
                </button>
                <button onclick="setLamp('${n.node_id}',1)" ${mode !== 'MANUAL' || !online ? 'disabled' : ''}
                    class="flex-1 min-w-[70px] rounded-full py-2 text-[11px] font-black text-white transition active:scale-95 ${mode === 'MANUAL' && online ? 'bg-green-500 hover:bg-green-400' : 'bg-slate-200 text-slate-400 cursor-not-allowed'}">ON</button>
                <button onclick="setLamp('${n.node_id}',0)" ${mode !== 'MANUAL' || !online ? 'disabled' : ''}
                    class="flex-1 min-w-[70px] rounded-full py-2 text-[11px] font-black text-white transition active:scale-95 ${mode === 'MANUAL' && online ? 'bg-red-500 hover:bg-red-400' : 'bg-slate-200 text-slate-400 cursor-not-allowed'}">OFF</button>
                <button onclick="restart('${n.node_id}')" ${!online ? 'disabled' : ''}
                    class="rounded-full px-4 py-2 text-[11px] font-black transition active:scale-95 ${online ? 'bg-slate-800 text-white hover:bg-slate-700' : 'bg-slate-200 text-slate-400 cursor-not-allowed'}">↻ Restart</button>
            </div>
            ${mode !== 'MANUAL' ? '<p class="mt-2 text-center text-[10px] text-slate-400">Kontrol ON/OFF manual tersedia dalam mode MANUAL.</p>' : ''}
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-black text-slate-900">Status Slave ESP-NOW — ${esc(n.node_id)}</h3>
                <span class="rounded-full px-3 py-1 text-[10px] font-black ${slaves.length === slaveCount ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600'}">${slaves.length}/${slaveCount} slave terhubung</span>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                ${slaveCards.join('')}
            </div>
        </div>`;
    }).join('');
}

// ============================================================
// STATISTIK
// ============================================================
async function updateStats() {
    const d = await fetch('api/data.php?act=stats').then(r => r.json()).catch(() => null);
    if (!d || d.status !== 'success') return;
    $('statTotal').textContent = d.total_nodes ?? '–';
    $('statOnline').textContent = d.online ?? '–';
    $('statOn').textContent = d.lights_on ?? '–';
    $('statWatt').innerHTML = (d.total_watt ?? '–') + ' <span class="text-base text-slate-400">W</span>';
    $('statVolt').innerHTML = (d.avg_voltage != null ? d.avg_voltage : '–') + ' <span class="text-base text-slate-400">V</span>';
    $('statEnergy').innerHTML = (d.total_energy ?? '–') + ' <span class="text-base text-slate-400">Wh</span>';

    const online = d.online ?? 0;
    const total = d.total_nodes ?? 0;
    $('onlineBadge').textContent = 'Online: ' + online + '/' + total;
    $('onlineBadge').className = 'rounded-full px-3 py-1.5 text-[11px] font-bold border ' +
        (online === total && total > 0 ? 'border-green-200 bg-green-100 text-green-700' : 'border-slate-200 bg-slate-100 text-slate-600');
}

// ============================================================
// GRAFIK
// ============================================================
async function loadChart() {
    const node = $('chartNode').value, field = $('chartField').value, hours = $('chartHours').value;
    const d = await fetch(`api/data.php?act=history&node=${encodeURIComponent(node)}&field=${field}&hours=${hours}`).then(r => r.json()).catch(() => null);
    if (!d || d.status !== 'success') return;

    const titleMap = { voltage: 'Tegangan (V)', current_amp: 'Arus (A)', power_watt: 'Daya (W)', energy: 'Energi (Wh)', wifi_rssi: 'RSSI WiFi (dBm)' };
    if (historyChart) historyChart.destroy();
    historyChart = new Chart($('historyChart'), {
        type: 'line',
        data: {
            labels: d.points.map(p => p[0]),
            datasets: [{
                label: titleMap[field] || field,
                data: d.points.map(p => p[1]),
                borderColor: '#0284c7',
                backgroundColor: 'rgba(2,132,199,.10)',
                fill: true, tension: .35, pointRadius: 0, borderWidth: 2,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#64748b' } } },
            scales: {
                x: { ticks: { color: '#94a3b8', maxTicksLimit: 8 }, grid: { color: '#e2e8f0' } },
                y: { beginAtZero: true, ticks: { color: '#64748b' }, grid: { color: '#e2e8f0' } },
            },
        },
    });
    $('historyChart').parentElement.style.height = '320px';
}

// ============================================================
// PETA
// ============================================================
function initMap() {
    if (map) return;
    map = L.map('map').setView([-6.5915, 110.6717], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
}

function updateMap() {
    if (!map) return;
    const items = nodes.filter(n => {
        const s = n.state || {};
        return (s.latitude && s.longitude) || (n.lat != null && n.lng != null);
    }).map(n => {
        const s = n.state || {};
        const lat = s.latitude && s.latitude !== '-' ? Number(s.latitude) : n.lat;
        const lng = s.longitude && s.longitude !== '-' ? Number(s.longitude) : n.lng;
        const lampOn = s.gateway_state == 1;
        const color = !n.online ? '#94a3b8' : (lampOn ? '#22c55e' : '#ef4444');
        return { n, lat, lng, color, lampOn };
    });

    mapMarkers.forEach(m => map.removeLayer(m));
    mapMarkers = [];

    items.forEach(x => {
        const icon = L.divIcon({
            className: '',
            html: `<div style="width:18px;height:18px;border-radius:50%;background:${x.color};border:3px solid #fff;box-shadow:0 0 8px ${x.color}66"></div>`,
            iconSize: [18, 18],
        });
        const m = L.marker([x.lat, x.lng], { icon }).addTo(map)
            .bindPopup(`<b>${esc(x.n.name)}</b><br>${esc(x.n.node_id)}<br>Status: ${x.n.online ? (x.lampOn ? 'Menyala' : 'Mati') : 'Offline'}`);
        mapMarkers.push(m);
    });

    if (items.length) map.fitBounds(L.latLngBounds(items.map(x => [x.lat, x.lng])), { padding: [40, 40] });
}

// ============================================================
// NOTIFIKASI & WA
// ============================================================
async function loadNotifs() {
    const d = await fetch('api/data.php?act=notifications').then(r => r.json()).catch(() => null);
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
}

function markRead() { fetch('api/action.php?action=mark_notif_read', { method: 'POST' }).then(() => loadNotifs()); }

async function testWA() {
    const d = await fetch('api/action.php?action=test_wa').then(r => r.json()).catch(() => null);
    if (d && d.status === 'success') window.open(d.whatsapp_url, '_blank');
}

// ============================================================
// INISIALISASI
// ============================================================
async function init() {
    initMap();
    loadNotifs();
    await loadData();

    const sel = $('chartNode');
    nodes.forEach(n => {
        const o = document.createElement('option');
        o.value = n.node_id;
        o.textContent = n.name + ' (' + n.node_id + ')';
        sel.appendChild(o);
    });
    loadChart();

    // Polling data setiap 5 detik (interval telemetry firmware)
    setInterval(loadData, 5000);
    setInterval(loadNotifs, 15000);
    setInterval(loadChart, 60000);
}

window.addEventListener('load', init);
</script>
</body>
</html>
