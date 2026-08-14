<div align="center">

# ⚡ Antrian PLN

**Sistem Manajemen Antrian Digital untuk Kantor PLN**

Dibangun dengan **Laravel 8** + **Node.js WebSocket** untuk layanan antrian yang cepat, transparan, dan real-time.

</div>

---

## 📌 Status Proyek

> 🛠️ **Sedang dalam pengerjaan** — Proyek ini masih tahap pengembangan.  
> Mohon tunggu kabar selanjutnya ya! Kami sedang berusaha memberikan fitur terbaik untuk kenyamanan pengguna.

---

## ✨ Fitur Utama

| Fitur | Deskripsi |
|-------|-----------|
| 🎟️ **Pengambilan Tiket** | Pelanggan mengambil nomor antrian sesuai jenis layanan |
| 📢 **Panggilan Real-time** | Nomor antrian dipanggil dan ditampilkan langsung di layar monitor via WebSocket |
| 🛎️ **Manajemen Antrian** | Panggil, layani, *skip*, selesaikan, hingga *restore* antrian yang terlewat |
| 🗑️ **Tempat Sampah** | Antrian yang dihapus bisa dipulihkan atau dihapus permanen |
| 👤 **Autentikasi & Peran** | Login/logout dengan Sanctum, role admin & petugas loket, nomor loket per pengguna |
| 📊 **Dashboard Analitik** | Statistik antrian harian/mingguan lengkap dengan fitur *export* |
| 🎬 **Konten Layar TV** | Unggah video & atur volume untuk tampilan layar tunggu |
| 🖥️ **WebSocket Broadcast** | Server Node.js terpisah untuk broadcast event ke semua display |

---

## 🧱 Tech Stack

<p align="center">
  <img src="https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/Laravel_8-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 8">
  <img src="https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/Node.js-339933?style=for-the-badge&logo=nodedotjs&logoColor=white" alt="Node.js">
  <img src="https://img.shields.io/badge/WebSocket-010101?style=for-the-badge&logo=socketdotio&logoColor=white" alt="WebSocket">
</p>

### Backend
- **Laravel 8** — Framework PHP utama
- **Laravel Sanctum** — Autentikasi API token
- **MySQL** — Basis data utama

### Frontend
- **Laravel Mix / Webpack** — Build asset
- **Axios** — HTTP client

### Real-time
- **Node.js + `ws`** — WebSocket server untuk broadcast antrian

---

## 🏗️ Arsitektur

```
┌─────────────┐    REST API    ┌──────────────────┐
│   Petugas   │ ─────────────▶ │                  │
│  (loket)    │                │   Laravel API    │
└─────────────┘                │  (Sanctum auth)  │
                               └────────┬─────────┘
┌─────────────┐                         │
│ Pelanggan   │ ─── ambil tiket ────────┤
│ (mesin/kiosk│                         │
└─────────────┘                         ▼
┌─────────────┐              ┌──────────────────┐
│   Display   │ ◀─────────── │  WS Server       │ ◀── HTTP broadcast
│  (layar TV) │   WebSocket  │  (Node.js:3001)  │    (Node.js:3002)
└─────────────┘              └──────────────────┘
```

---

## 🚀 Instalasi

> Prasyarat: **PHP ^7.3|^8.0**, **Composer**, **Node.js**, **MySQL**, **Laragon/XAMPP** (opsional)

### 1. Clone & Install Dependency

```bash
git clone <repository-url> antrian-pln
cd antrian-pln

composer install
npm install
```

### 2. Konfigurasi Environment

```bash
cp .env.example .env
php artisan key:generate
```

Sesuaikan `.env` dengan konfigurasi aplikasi:

```env
APP_NAME=Antrian PLN
DB_DATABASE=antrian_pln
DB_USERNAME=root
DB_PASSWORD=your_password

# Origin aplikasi frontend (tanpa spasi, pisahkan dengan koma)
CORS_ALLOWED_ORIGINS=http://localhost,http://localhost:3000
```

### 3. Migrasi Database

```bash
php artisan migrate
```

### 4. Jalankan Backend

```bash
php artisan serve
```

### 5. Jalankan WebSocket Server

```bash
cd websocket-server
npm install
npm start
```

- 🖥️ WebSocket: `ws://localhost:3001`
- 📡 HTTP relay (broadcast): `http://localhost:3002/broadcast`

> 📝 Untuk development dengan auto-reload: `npm run dev`

### 6. Build Asset Frontend

```bash
npm run dev     # development
npm run prod    # production (minified)
```

---

## 🔌 API Reference

### 🔓 Publik (tanpa autentikasi)

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| `POST` | `/api/auth/login` | Login pengguna |
| `GET` | `/api/queue` | Daftar antrian |
| `POST` | `/api/queue/take` | Ambil tiket antrian |
| `GET` | `/api/queue/stats` | Statistik antrian |
| `GET` | `/api/queue/weekly` | Statistik mingguan |
| `GET` | `/api/queue/active` | Antrian yang sedang dipanggil |
| `GET` | `/api/queue/last-called/{loket}` | Antrian terakhir dipanggil per loket |
| `GET` | `/api/queue/{id}` | Detail antrian |

### 🔒 Terproteksi (token Sanctum)

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| `POST` | `/api/auth/logout` | Logout |
| `GET` | `/api/auth/profile` | Profil pengguna |
| `PUT` | `/api/queue/{id}/call` | Panggil antrian |
| `PUT` | `/api/queue/{id}/serve` | Layani antrian |
| `PUT` | `/api/queue/{id}/skip` | Skip antrian |
| `PUT` | `/api/queue/{id}/complete` | Selesaikan antrian |
| `PUT` | `/api/queue/{id}/restore` | Pulihkan antrian |
| `GET` | `/api/queue/trash` | Daftar antrian terhapus |
| `DELETE` | `/api/queue/trash` | Kosongkan tempat sampah |
| `POST` | `/api/queue/clear-history` | Bersihkan riwayat |
| `GET` | `/api/dashboard/analitik` | Data analitik dashboard |
| `GET` | `/api/dashboard/export` | Export data dashboard |

### 🎬 Pengaturan Layar TV

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| `GET` | `/api/settings/video-volume` | Ambil volume video |
| `POST` | `/api/settings/video-volume` | Atur volume video |
| `GET` | `/api/settings/videos` | Daftar video |
| `POST` | `/api/settings/videos` | Unggah video |
| `DELETE` | `/api/settings/videos/{filename}` | Hapus video |

---

## 📁 Struktur Proyek

```
antrian-pln/
├── app/                    # Kode aplikasi Laravel
│   └── Http/Controllers/   # AuthController, QueueController, dll.
├── config/                 # Konfigurasi aplikasi
├── database/
│   ├── migrations/         # Skema tabel (users, antrians, dll.)
│   └── seeders/
├── routes/
│   ├── api.php             # Endpoint REST API
│   └── web.php             # Route web
├── websocket-server/       # Server WebSocket Node.js
│   └── server.js           # WS server + HTTP relay broadcast
└── resources/              # View & asset frontend
```

---

## 🤝 Kontribusi

Kontribusi sangat kami nantikan! Silakan buat *pull request* atau laporkan kendala melalui *issues*.

---

<div align="center">

**Dibuat dengan 💙 untuk pelayanan publik yang lebih baik**

</div>