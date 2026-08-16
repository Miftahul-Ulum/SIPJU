# SIPJU — Sistem Informasi Penerangan Jalan Umum

> Monitoring & kontrol lampu PJU berbasis **ESP32 + ESP-NOW (ESP-Now)** dan **Internet of Things**, dengan backend tunggal PHP/MySQL.

## Deskripsi

SIPJU adalah sistem backend sekaligus dashboard web untuk lampu penerangan jalan umum (PJU) yang dikendalikan lewat jaringan **ESP-NOW**. Satu ESP32 bertindak sebagai **gateway** (Wi-Fi + HTTP) yang berkomunikasi dengan beberapa **slave** (ESP-01/ESP8266) melalui ESP-NOW dan mengendalikan relay + monitoring lampu.

Backend ini **menggantikan server Node.js** (LPJU-IOT) sebelumnya: semua data device, telemetry, perintah (command), dan notifikasi disimpan di **MySQL**. Perintah dapat dikirim dari **dashboard web** maupun **bot WhatsApp** (bot Node tetap dipakai, tetapi menulis ke tabel `commands` SIPJU melalui endpoint bridge).

## Fitur

- 🖥️ Dashboard web responsif (statistik, kartu node + slave, riwayat telemetri).
- 🔌 Kontrol relay dari web: ON/OFF, set mode (MANUAL/SCHEDULE), set jadwal, restart.
- 📡 Komunikasi ESP32 Gateway via `POST /api/device/{node_id}` (format sama dengan server Node lama, jadi firmware tidak berubah selain URL).
- 💾 Antrean perintah tunggal di tabel `commands` (command lama ditimpa = superseded, format persis Redis server lama).
- ⚠️ Deteksi mismatch relay gateway vs slave (notifikasi DEVICE ERROR / RECOVERY).
- 📲 Bot WhatsApp tetap berjalan via **endpoint bridge** `api/wa.php` (auth API key, tanpa session).
- 🐳 Siap deploy dengan Docker (Portainer) + opsi **Cloudflare Tunnel** (akses publik tanpa buka port).
- 🔐 Login admin + API key untuk komunikasi device.

## Arsitektur

```
                        ┌──────────────────────────────────────┐
                        │           SIPJU (backend)             │
  ESP32 Gateway ───────▶│  api/device.php  ──▶ MySQL ──┐        │
  (Wi-Fi, HTTP POST)    │  api/wa.php (bridge WA)      │        │
                        │  Dashboard (index.php)       ▼        │
  Bot WhatsApp ────────▶│  api/action.php / api/data   commands │
                        └──────────────────────────────────────┘
                                   ▲
                    ESP-NOW (relay + lampu) / ESP32 ⇄ slave
```

### Jalur perintah

- **Web** : Dashboard → `api/action.php?action=send_command` → tabel `commands` → gateway mengambil saat polling → slave.
- **WhatsApp** : Bot Node → `POST api/wa.php?action=command` → tabel `commands` → gateway mengambil saat polling → slave.

## Struktur Folder

```
├── api/
│   ├── device.php     # Endpoint ESP32 Gateway (telemetry + ambil command)
│   ├── action.php     # Aksi dashboard (login wajib)
│   ├── data.php       # Data dashboard (login wajib)
│   └── wa.php         # Bridge bot WhatsApp (auth X-Api-Key)
├── deploy/
│   ├── init_db.php    # Skema DB tunggal (idempotent)
│   └── init_cli.php   # Init DB otomatis saat container start
├── index.php          # Dashboard
├── login.php / logout.php
├── nodes.php          # Manajemen node & slave
├── install.php        # Installer web (buat DB + skema + admin)
├── config.php         # Konfigurasi utama (env-aware)
├── db.php             # Helper PDO + session + json_out
├── Dockerfile
├── docker-compose.yml
├── docker-compose.tunnel.yml
└── schedules.php
```

## Instalasi Lokal (XAMPP)

**Kebutuhan:** PHP 8+, MySQL/MariaDB, Apache (mod_rewrite aktif).

1. Salin folder ini ke `C:\xampp\htdocs\PJU` (atau `htdocs` lainnya).
2. Pastikan MySQL (XAMPP) berjalan.
3. Buka `http://localhost/PJU/install.php` → klik tombol **Install**.
   - Membuat database `pju_monitoring` + seluruh tabel + user admin.
   - Aman dijalankan berulang (semua `CREATE ... IF NOT EXISTS`).
4. Login di `http://localhost/PJU/` dengan:
   - Username : `admin`
   - Password : `admin123`
5. Buka menu **Nodes** untuk mendaftarkan node (mis. `LPJU01`) dan jumlah slave.

## Konfigurasi

`config.php` otomatis membaca variabel lingkungan (Docker). Nilai default untuk XAMPP lokal:

| Variabel      | Default        | Keterangan                                  |
|---------------|----------------|---------------------------------------------|
| `DB_HOST`     | `localhost`    | Host MySQL                                   |
| `DB_NAME`     | `pju_monitoring` | Nama database                              |
| `DB_USER`     | `root`         | User MySQL                                   |
| `DB_PASS`     | *(kosong)*     | Password MySQL                               |
| `API_KEY`     | `LPJU_IOT_2026`| API key ESP32 & bot WhatsApp (WAJIB sama dgn firmware) |
| `DEVICES`     | `LPJU01`       | Daftar node (koma)                           |
| `API_ENDPOINT_BASE` | `http://localhost/PJU/api/device/` | Endpoint yang dipakai firmware |

> ⚠️ **Ubah `API_KEY` dan password admin** sebelum dipakai produksi.

## Firmware (ESP32 / ESP-Now)

- **Gateway (`sketch_gateway.ino`)** : hanya ubah `SERVER_BASE_URL` (baris ~76) menjadi alamat SIPJU, contoh:
  - Lokal : `http://192.168.1.100/PJU/api/device/`
  - Produksi (tunnel) : `https://sipju.pju.biz.id/api/device/`
  - Pastikan `API_KEY` di firmware sama dengan di `config.php`.
  - Logika kontrol (STATE_ON/OFF, SET_MODE, SET_SCHEDULE, RESTART) **tidak berubah**.
- **Master (`sketch_master.ino`)** : daftar MAC slave (SLAVE 1/2/3), `TOTAL_SLAVES` otomatis.
- **Slave 1/2/3** : tidak perlu diubah.

> Hanya gateway yang wajib reflash saat pindah server. Master & slave aman.

## API

### 1. Endpoint ESP32 Gateway

```
POST /api/device/{node_id}
Header : x-api-key: {API_KEY}
Body   : JSON telemetry (format sketch_gateway.ino)
```

Contoh balasan (format persis server Node lama, sehingga firmware tidak berubah):

```json
{ "success": true, "data": { "success": true, "command": { "type": "STATE_ON", "command_id": "CMD-3", ... } } }
```

- Auto-register node jika belum ada.
- Menyimpan `device_state`, `telemetry`, `slaves`.
- Mengembalikan 1 pending command (paling lama) lalu menandainya `sent`.

### 2. Bridge WhatsApp

```
GET  /api/wa.php?action=status&node_id=LPJU01
POST /api/wa.php?action=command     # body: node_id, type, [control_mode|on_time|off_time], requested_by?
Header : X-Api-Key: {API_KEY}
```

Validasi dilakukan server: cek node terdaftar, online (`last_seen` ≤ 60 detik), dan mode (MANUAL untuk ON/OFF, SCHEDULE untuk jadwal).

### 3. API Dashboard (wajib login session)

| Endpoint | Fungsi |
|----------|--------|
| `api/data.php?act=nodes` | Node + state + slaves + status online |
| `api/data.php?act=history&node=&field=&hours=` | Riwayat telemetri (voltage/current_amp/power_watt/energy/wifi_rssi) |
| `api/data.php?act=stats` | Statistik ringkas |
| `api/data.php?act=schedules` | Jadwal per device |
| `api/data.php?act=commands&node=` | Riwayat command |
| `api/data.php?act=notifications` | Notifikasi |
| `api/action.php?action=send_command` | Kirim perintah (web) |
| `api/action.php?action=add_node` / `update_node` / `delete_node` | Manajemen node |

## Bot WhatsApp (bridge)

Container Node `lpju-iot-app` tetap dijalankan untuk koneksi WhatsApp (baileys). Setelah cutover:

1. Tambahkan env di stack server Node:
   - `SIPJU_API_URL` = `https://sipju.pju.biz.id` (atau `http://IP:8080`)
   - `SIPJU_API_KEY` = sama dengan `API_KEY`
2. Ganti file `src/config/env.js`, `src/services/command.service.js`, `src/controllers/whatsapp.controller.js` dengan versi yang memanggil SIPJU (lihat folder server proyek firmware).
3. Rebuild container.

Alur: `/status`, `/on`, `/off`, `/set_mode`, `/set_schedule`, `/restart` → **SIPJU** (MySQL), bukan Redis. Redis hanya fallback untuk jalur polling ESP→Node lama.

## Deploy Docker (Portainer)

1. Pastikan repo ini **public** (agar Portainer bisa clone tanpa kredensial).
2. Portainer → **Stacks → Add stack** → nama `pju` → mode **Repository** → URL `https://github.com/{user}/SIPJU`.
3. Isi environment (jangan pakai default untuk produksi):
   - `DB_ROOT_PASSWORD`, `DB_PASSWORD`
   - `API_KEY`
   - `WEB_PORT` (contoh `8080`)
4. **Deploy the stack**. Portainer membangun image otomatis (stack berisi `build:`).
   - Kontainer `pju-db` (MariaDB, data di volume `pju_db_data`) + `pju-web` (PHP 8.2 + Apache).
   - Skema DB dibuat otomatis saat `pju-web` start (idempotent) — tidak perlu buka `install.php`.
5. Akses `http://IP_VPS:{WEB_PORT}/` → login `admin/admin123`.

## Cloudflare Tunnel (akses publik tanpa buka port)

1. Dashboard Cloudflare → **Zero Trust → Networks → Tunnels → Create a tunnel** → tipe **Cloudflared** → salin token.
2. Tambah **Public Hostname**:
   - Subdomain : `sipju.pju.biz.id`
   - Service   : `http://pju-web:80`
3. Deploy `docker-compose.tunnel.yml` (stack terpisah di Portainer) dengan env `CLOUDFLARE_TUNNEL_TOKEN`.
4. Setelah aktif, gateway diarahkan ke `https://sipju.pju.biz.id/api/device/`.

## Keamanan

- Ganti password admin default (`admin123`) segera setelah deploy.
- Ganti `API_KEY` default (`LPJU_IOT_2026`) dan sesuaikan di firmware + bot WhatsApp.
- Gunakan HTTPS (Cloudflare Tunnel / reverse proxy) di produksi.

## Troubleshooting

| Gejala | Solusi |
|--------|--------|
| `API key wajib diisi` / `Invalid API key` | Pastikan `x-api-key` sama dengan `API_KEY` di `config.php` |
| Node tampil offline di dashboard | Cek `last_seen`; pastikan `SERVER_BASE_URL` firmware benar & jarak polling ≤ 60 detik |
| Perintah tidak jalan dari WhatsApp | Pastikan `SIPJU_API_URL`/`SIPJU_API_KEY` di bot Node benar; cek mode device (ON/OFF butuh MANUAL) |
| Halaman API balas 401 | Endpoint `api/*` (kecuali `device.php`/`wa.php`) butuh login session |
| Container `pju-web` restart loop | Cek log; pastikan `pju-db` sehat dan env `DB_*` benar |
