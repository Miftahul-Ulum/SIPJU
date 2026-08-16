<?php
// ============================================================
// PJU MONITORING - JADWAL OTOMATIS (TIMER)
// ============================================================
require_once __DIR__ . '/db.php';
require_login();

$mode = setting_get('mode', 'auto');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal — <?= APP_NAME ?></title>
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
                <a href="nodes.php" class="rounded-full border border-slate-200 px-3 py-1.5 text-slate-600 hover:bg-slate-100">Tiang</a>
                <a href="logout.php" class="rounded-full bg-slate-800 px-3 py-1.5 text-white hover:bg-slate-700">Keluar</a>
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-5xl px-5 py-6 space-y-6">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900">Jadwal Otomatis (Timer)</h1>
            <p class="text-sm text-slate-500">
                Berjalan saat mode <b class="text-sky-600">Otomatis</b> — lampu menyala antara jam mulai–selesai.
                Mode saat ini: <b id="modeText" class="<?= $mode === 'manual' ? 'text-amber-600' : 'text-sky-600' ?>"><?= $mode === 'manual' ? 'MANUAL' : 'OTOMATIS' ?></b>
            </p>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-black text-slate-900">Tambah Jadwal</h2>
            <form id="formAdd" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1 block text-[11px] font-bold text-slate-500">Tiang</label>
                    <select name="node_id" id="nodeSelect" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm outline-none focus:border-sky-500 focus:bg-white"></select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold text-slate-500">Hari</label>
                    <select name="day_of_week" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm outline-none focus:border-sky-500 focus:bg-white">
                        <option value="all">Setiap hari</option>
                        <option value="mon,tue,wed,thu,fri">Senin–Jumat</option>
                        <option value="sat,sun">Sabtu–Minggu</option>
                        <option value="mon">Senin</option>
                        <option value="tue">Selasa</option>
                        <option value="wed">Rabu</option>
                        <option value="thu">Kamis</option>
                        <option value="fri">Jumat</option>
                        <option value="sat">Sabtu</option>
                        <option value="sun">Minggu</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold text-slate-500">Mulai</label>
                    <input name="start_time" type="time" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm outline-none focus:border-sky-500 focus:bg-white">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold text-slate-500">Selesai</label>
                    <input name="end_time" type="time" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm outline-none focus:border-sky-500 focus:bg-white">
                </div>
                <div class="sm:col-span-2 lg:col-span-4">
                    <button type="submit" class="rounded-full bg-sky-500 px-6 py-2.5 text-sm font-black text-white shadow-lg shadow-sky-500/30 transition hover:bg-sky-400 active:scale-95">+ Tambah Jadwal</button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-black text-slate-900">Daftar Jadwal</h2>
            <div id="list" class="space-y-3"></div>
        </section>
    </main>

<script>
const $ = id => document.getElementById(id);
const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
const DAYS = { all: 'Setiap hari', mon: 'Senin', tue: 'Selasa', wed: 'Rabu', thu: 'Kamis', fri: 'Jumat', sat: 'Sabtu', sun: 'Minggu' };

async function loadNodes() {
    const d = await fetch('api/data.php?act=nodes').then(r => r.json()).catch(() => null);
    const nodes = (d && d.nodes) || [];
    const sel = $('nodeSelect');
    sel.innerHTML = '<option value="*">Semua tiang</option>' + nodes.map(n =>
        `<option value="${esc(n.node_id)}">${esc(n.name)} (${esc(n.node_id)})</option>`).join('');
}

async function loadList() {
    const d = await fetch('api/data.php?act=schedules').then(r => r.json()).catch(() => null);
    const list = (d && d.schedules) || [];
    $('list').innerHTML = list.length ? list.map(s => `
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div>
                <p class="font-black text-slate-900">${s.node_id === '*' ? 'Semua tiang' : esc(s.node_id)}</p>
                <p class="text-[11px] text-slate-500">${esc(DAYS[s.day_of_week] || s.day_of_week)} · ${String(s.start_time).slice(0,5)} – ${String(s.end_time).slice(0,5)} · ${s.enabled ? 'Aktif' : 'Nonaktif'}</p>
            </div>
            <button onclick="del(${s.id})" class="rounded-full border border-red-500/60 px-3 py-1.5 text-[11px] font-bold text-red-700 hover:bg-red-50">Hapus</button>
        </div>`).join('')
        : '<p class="p-3 text-sm text-slate-400">Belum ada jadwal.</p>';
}

async function add(ev) {
    ev.preventDefault();
    const body = new URLSearchParams(new FormData(ev.target));
    body.set('action', 'add_schedule');
    const d = await fetch('api/action.php?action=add_schedule', { method: 'POST', body }).then(r => r.json());
    if (d.status === 'success') { ev.target.reset(); loadList(); } else alert(d.message || 'Gagal');
}

async function del(id) {
    if (!confirm('Hapus jadwal ini?')) return;
    const d = await fetch('api/action.php?action=delete_schedule', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_schedule&id=' + id,
    }).then(r => r.json());
    if (d.status === 'success') loadList();
}

$('formAdd').addEventListener('submit', add);
loadNodes();
loadList();
</script>
</body>
</html>
