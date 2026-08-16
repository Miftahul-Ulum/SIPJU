<?php
// ============================================================
// SIPJU - MANAJEMEN TIANG (NODE / GATEWAY)
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
    <title>Tiang PJU — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">

    <nav class="sticky top-0 z-50 border-b border-slate-200 bg-white/90 shadow-sm backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-3">
            <a href="index.php" class="flex items-center gap-2 text-sm font-black tracking-tight text-slate-900">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-100 text-lg">🛣️</span>
                <span><?= APP_NAME ?> <span class="text-sky-600">Monitoring</span></span>
            </a>
            <div class="flex items-center gap-2 text-[11px] font-bold">
                <a href="index.php" class="rounded-full border border-slate-200 px-3 py-1.5 text-slate-600 hover:bg-slate-100">Dashboard</a>
                <a href="schedules.php" class="rounded-full border border-slate-200 px-3 py-1.5 text-slate-600 hover:bg-slate-100">Jadwal</a>
                <a href="logout.php" class="rounded-full bg-slate-800 px-3 py-1.5 text-white hover:bg-slate-700">Keluar</a>
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-7xl px-5 py-6 space-y-6">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900">Manajemen Tiang PJU</h1>
            <p class="text-sm text-slate-500">Daftar gateway + metadata lokasi. Node baru bisa didaftarkan manual atau otomatis saat ESP32 pertama kali POST telemetry.</p>
        </div>

        <!-- Form tambah -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-black text-slate-900">Tambah Tiang Baru</h2>
            <form id="formAdd" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div><label class="mb-1 block text-[11px] font-bold text-slate-500">ID Node *</label>
                    <input name="node_id" required placeholder="LPJU02" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm outline-none focus:border-sky-500 focus:bg-white"></div>
                <div><label class="mb-1 block text-[11px] font-bold text-slate-500">Nama Tiang *</label>
                    <input name="name" required placeholder="PJU Jalan Kartini" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm outline-none focus:border-sky-500 focus:bg-white"></div>
                <div><label class="mb-1 block text-[11px] font-bold text-slate-500">Lokasi</label>
                    <input name="location" placeholder="Jl. Kartini, Jepara" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm outline-none focus:border-sky-500 focus:bg-white"></div>
                <div><label class="mb-1 block text-[11px] font-bold text-slate-500">Latitude</label>
                    <input name="lat" type="number" step="any" placeholder="-6.5900" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm outline-none focus:border-sky-500 focus:bg-white"></div>
                <div><label class="mb-1 block text-[11px] font-bold text-slate-500">Longitude</label>
                    <input name="lng" type="number" step="any" placeholder="110.6700" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm outline-none focus:border-sky-500 focus:bg-white"></div>
                <div class="sm:col-span-2 lg:col-span-5">
                    <button type="submit" class="rounded-full bg-sky-500 px-6 py-2.5 text-sm font-black text-white shadow-lg shadow-sky-500/30 transition hover:bg-sky-400 active:scale-95">+ Tambah Tiang</button>
                </div>
            </form>
        </section>

        <!-- Daftar -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-black text-slate-900">Daftar Tiang</h2>
            <div id="list" class="space-y-3"></div>
        </section>
    </main>

<script>
const $ = id => document.getElementById(id);
const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));

async function loadList() {
    const d = await fetch('api/data.php?act=nodes').then(r => r.json()).catch(() => null);
    const nodes = (d && d.nodes) || [];
    $('list').innerHTML = nodes.map(n => {
        const s = n.state || {};
        const online = n.online;
        const lampOn = s.gateway_state == 1;
        const statusBadge = !online ? '<span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black text-slate-500">Offline</span>'
            : `<span class="rounded-full px-2 py-0.5 text-[10px] font-black ${lampOn ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}">${lampOn ? 'Menyala' : 'Mati'}</span>`;
        const slaves = (n.slaves || []).map(sl => `S${sl.slave_id}:${sl.state ? 'ON' : 'OFF'}`).join(', ') || '–';
        return `
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <div class="flex items-center gap-2">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">${esc(n.node_id)}</p>
                        ${statusBadge}
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-black ${s.control_mode === 'MANUAL' ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700'}">${esc(s.control_mode || '–')}</span>
                    </div>
                    <h3 class="font-black text-slate-900">${esc(n.name)}</h3>
                    <p class="text-[11px] text-slate-500">${esc(n.location || '—')}${n.lat != null ? ` · ${n.lat}, ${n.lng}` : ''}</p>
                    <p class="mt-1 text-[11px] text-slate-500">Slave: <b>${esc(slaves)}</b> · Volt <b>${s.voltage != null ? Number(s.voltage).toFixed(1) + ' V' : '–'}</b> · Daya <b>${s.power_watt != null ? Number(s.power_watt).toFixed(1) + ' W' : '–'}</b> · update ${esc(s.last_seen || '–')}</p>
                </div>
                <div class="flex gap-2 text-[11px] font-bold">
                    <button onclick="editNode(${n.id},'${esc(n.name)}','${esc(n.location)}','${n.lat ?? ''}','${n.lng ?? ''}',${n.enabled})" class="rounded-full border border-sky-500/60 px-3 py-1.5 text-sky-700 hover:bg-sky-50">Ubah</button>
                    <button onclick="delNode(${n.id})" class="rounded-full border border-red-500/60 px-3 py-1.5 text-red-700 hover:bg-red-50">Hapus</button>
                </div>
            </div>
        </div>`;
    }).join('') || '<p class="p-3 text-sm text-slate-400">Belum ada tiang terdaftar.</p>';
}

async function addNode(ev) {
    ev.preventDefault();
    const f = ev.target;
    const body = new URLSearchParams(new FormData(f));
    body.set('action', 'add_node');
    const d = await fetch('api/action.php?action=add_node', { method: 'POST', body }).then(r => r.json());
    if (d.status === 'success') { f.reset(); loadList(); } else alert(d.message || 'Gagal');
}

async function delNode(id) {
    if (!confirm('Hapus tiang ini?')) return;
    const d = await fetch('api/action.php?action=delete_node', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_node&id=' + id,
    }).then(r => r.json());
    if (d.status === 'success') loadList();
}

async function editNode(id, name, location, lat, lng, enabled) {
    const newName = prompt('Nama tiang:', name);
    if (newName === null) return;
    const newLoc = prompt('Lokasi:', location) ?? '';
    const newLat = prompt('Latitude:', lat) ?? '';
    const newLng = prompt('Longitude:', lng) ?? '';
    const keep = confirm('Aktifkan tiang ini? Klik OK untuk aktif, Batal untuk nonaktif.');
    const d = await fetch('api/action.php?action=update_node', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=update_node&id=' + id + '&name=' + encodeURIComponent(newName) +
              '&location=' + encodeURIComponent(newLoc) + '&lat=' + encodeURIComponent(newLat) +
              '&lng=' + encodeURIComponent(newLng) + '&enabled=' + (keep ? 1 : 0),
    }).then(r => r.json());
    if (d.status === 'success') loadList(); else alert(d.message || 'Gagal');
}

$('formAdd').addEventListener('submit', addNode);
loadList();
setInterval(loadList, 10000);
</script>
</body>
</html>
