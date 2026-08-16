<?php
// ============================================================
// SIPJU - JADWAL OTOMATIS PER DEVICE
// Jadwal ON/OFF harian disimpan di NVS firmware (via SET_SCHEDULE).
// Berfungsi saat device dalam mode SCHEDULE.
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
    <title>Jadwal — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">

    <nav class="sticky top-0 z-50 border-b border-slate-200 bg-white/90 shadow-sm backdrop-blur">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-5 py-3">
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
                Jadwal ON/OFF harian disimpan di memori ESP32 gateway dan dijalankan oleh RTC DS3231.
                Hanya berfungsi saat mode <b class="text-sky-600">SCHEDULE</b>.
            </p>
        </div>

        <!-- Form atur jadwal -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-black text-slate-900">Atur Jadwal Perangkat</h2>
            <form id="formSet" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1 block text-[11px] font-bold text-slate-500">Gateway</label>
                    <select name="node_id" id="nodeSelect" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm outline-none focus:border-sky-500 focus:bg-white"></select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold text-slate-500">Jam Menyala (ON)</label>
                    <input name="on_time" type="time" step="1" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm outline-none focus:border-sky-500 focus:bg-white">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold text-slate-500">Jam Mati (OFF)</label>
                    <input name="off_time" type="time" step="1" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm outline-none focus:border-sky-500 focus:bg-white">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full rounded-full bg-sky-500 px-6 py-2.5 text-sm font-black text-white shadow-lg shadow-sky-500/30 transition hover:bg-sky-400 active:scale-95">Kirim Jadwal</button>
                </div>
            </form>
            <p id="hint" class="mt-3 hidden rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-700"></p>
        </section>

        <!-- Daftar jadwal -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-black text-slate-900">Jadwal Saat Ini</h2>
            <div id="list" class="space-y-3"></div>
        </section>
    </main>

    <div id="toast" class="fixed bottom-5 right-5 z-50 hidden max-w-sm rounded-xl border px-4 py-3 text-sm font-bold shadow-xl"></div>

<script>
const $ = id => document.getElementById(id);
const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));

function toast(msg, ok) {
    const t = $('toast');
    t.innerHTML = (ok ? '✅ ' : '⚠️ ') + esc(msg);
    t.className = 'fixed bottom-5 right-5 z-50 max-w-sm rounded-xl border px-4 py-3 text-sm font-bold shadow-xl ' +
        (ok ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700');
    t.classList.remove('hidden');
    clearTimeout(t._h);
    t._h = setTimeout(() => t.classList.add('hidden'), 4000);
}

async function loadSchedules() {
    const d = await fetch('api/data.php?act=schedules').then(r => r.json()).catch(() => null);
    if (!d || d.status !== 'success') return;

    const sel = $('nodeSelect');
    sel.innerHTML = d.schedules.map(s => `<option value="${esc(s.node_id)}">${esc(s.node_id)} (${esc(s.control_mode)})</option>`).join('');

    const list = d.schedules;
    $('list').innerHTML = list.length ? list.map(s => {
        const on = String(s.on_schedule || '').slice(0, 8) || '–';
        const off = String(s.off_schedule || '').slice(0, 8) || '–';
        const manual = s.control_mode === 'MANUAL';
        return `
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div>
                <div class="flex items-center gap-2">
                    <p class="font-black text-slate-900">${esc(s.node_id)}</p>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-black ${manual ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700'}">${manual ? 'MANUAL' : 'SCHEDULE'}</span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-black ${s.gateway_state == 1 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}">Lampu ${s.gateway_state == 1 ? 'ON' : 'OFF'}</span>
                </div>
                <p class="mt-1 text-[11px] text-slate-500">ON <b>${on}</b> · OFF <b>${off}</b> · update terakhir ${esc(s.last_seen || '–')}</p>
            </div>
            <div class="flex gap-2 text-[11px] font-bold">
                ${manual ? `<button onclick="setMode('${s.node_id}')" class="rounded-full border border-sky-500/60 px-3 py-1.5 text-sky-700 hover:bg-sky-50">Ubah ke SCHEDULE</button>`
                          : `<button onclick="fill('${s.node_id}','${on}','${off}')" class="rounded-full border border-slate-300 px-3 py-1.5 text-slate-600 hover:bg-slate-100">Ubah Jadwal</button>`}
            </div>
        </div>`;
    }).join('') : '<p class="p-3 text-sm text-slate-400">Belum ada device terdaftar.</p>';
}

function setMode(nodeId) {
    const body = new URLSearchParams({ action: 'send_command', node_id: nodeId, type: 'SET_MODE', control_mode: 'SCHEDULE' });
    fetch('api/action.php?action=send_command', { method: 'POST', body })
        .then(r => r.json()).then(d => { toast(d.message || 'OK', d.status === 'success'); loadSchedules(); });
}

function fill(nodeId, on, off) {
    $('nodeSelect').value = nodeId;
    document.querySelector('[name=on_time]').value = on.slice(0, 5);
    document.querySelector('[name=off_time]').value = off.slice(0, 5);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

$('formSet').addEventListener('submit', async ev => {
    ev.preventDefault();
    const f = ev.target;
    const body = new URLSearchParams(new FormData(f));
    body.set('action', 'send_command');
    body.set('type', 'SET_SCHEDULE');
    const d = await fetch('api/action.php?action=send_command', { method: 'POST', body }).then(r => r.json());
    const hint = $('hint');
    if (d.status === 'success') {
        toast(d.message, true);
        hint.classList.add('hidden');
    } else {
        toast(d.message, false);
        hint.textContent = d.message || 'Gagal';
        hint.classList.remove('hidden');
    }
    loadSchedules();
});

loadSchedules();
setInterval(loadSchedules, 10000);
</script>
</body>
</html>
