# 📚 Sistem Absensi Guru

Aplikasi web sederhana untuk mencatat kehadiran guru menggunakan PHP, MySQL, HTML, CSS, dan JavaScript.

---

## 📁 Struktur Folder

```
absensi-guru/
├── index.php                  ← Halaman Login (Guru & Admin)
├── logout.php                 ← Proses Logout
├── database.sql               ← Skema & Data Awal Database
│
├── css/
│   └── style.css              ← Stylesheet Utama
│
├── includes/
│   ├── config.php             ← Konfigurasi Database & Fungsi Helper
│   ├── auth_guru.php          ← Middleware Auth untuk Guru
│   └── auth_admin.php         ← Middleware Auth untuk Admin
│
├── guru/
│   ├── dashboard.php          ← Dashboard Guru
│   ├── absensi.php            ← Halaman Absen Masuk/Pulang
│   └── riwayat.php            ← Riwayat Absensi Pribadi
│
└── admin/
    ├── dashboard.php          ← Dashboard Admin
    ├── guru.php               ← Manajemen Data Guru (CRUD)
    └── absensi.php            ← Laporan & Data Absensi Semua Guru
```

---

## 🚀 Cara Menjalankan di Localhost (XAMPP)

### Langkah 1 — Install & Jalankan XAMPP
1. Download XAMPP dari https://www.apachefriends.org
2. Install dan buka **XAMPP Control Panel**
3. Klik **Start** pada **Apache** dan **MySQL**

### Langkah 2 — Salin File Project
1. Buka folder `C:\xampp\htdocs\` (Windows) atau `/Applications/XAMPP/htdocs/` (Mac)
2. Salin seluruh folder `absensi-guru` ke dalam `htdocs`

### Langkah 3 — Buat Database
1. Buka browser, akses: **http://localhost/phpmyadmin**
2. Klik tab **"SQL"** di bagian atas
3. Salin seluruh isi file `database.sql`
4. Tempel ke kolom SQL dan klik **"Go"**

   *Atau alternatif:*
   - Klik **"Import"** → pilih file `database.sql` → klik **"Go"**

### Langkah 4 — Konfigurasi Database
Buka file `includes/config.php` dan sesuaikan:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');   // Username MySQL Anda
define('DB_PASS', '');       // Password MySQL (default XAMPP: kosong)
define('DB_NAME', 'absensi_guru');
```

### Langkah 5 — Akses Aplikasi
Buka browser dan akses:
```
http://localhost/absensi-guru/
```

---

## 🔑 Akun Default

### Admin
| Username | Password |
|----------|----------|
| admin    | password |

### Guru (Contoh)
| Nama | NIP | Password |
|------|-----|----------|
| Budi Santoso, S.Pd | 198501012010011001 | password |
| Siti Aminah, S.Pd  | 198703152012012002 | password |
| Ahmad Fauzi, M.Pd  | 199001202015011003 | password |

> ⚠️ **Penting:** Segera ubah password setelah pertama kali login!

---

## 🗄️ Struktur Database

### Tabel `guru`
| Kolom      | Tipe         | Keterangan             |
|------------|--------------|------------------------|
| id         | INT (PK, AI) | ID unik guru           |
| nama       | VARCHAR(100) | Nama lengkap           |
| nip        | VARCHAR(30)  | NIP (unik)             |
| sk         | VARCHAR(50)  | Nomor SK (cth: 620 Tahun 2025)                    |
| spmt       | VARCHAR(100) | Nomor SPMT (cth: 800.1.13.2/20810-Dindikbud/2025) |
| password   | VARCHAR(255) | Password (di-hash)     |
| created_at | TIMESTAMP    | Waktu dibuat           |

### Tabel `absensi`
| Kolom      | Tipe        | Keterangan               |
|------------|-------------|--------------------------|
| id         | INT (PK, AI)| ID unik absensi          |
| guru_id    | INT (FK)    | Relasi ke tabel guru     |
| tanggal    | DATE        | Tanggal absen            |
| jam_masuk  | TIME        | Jam absen masuk          |
| jam_pulang | TIME        | Jam absen pulang         |
| created_at | TIMESTAMP   | Waktu dibuat             |

### Tabel `admin`
| Kolom      | Tipe         | Keterangan         |
|------------|--------------|--------------------|
| id         | INT (PK, AI) | ID unik admin      |
| username   | VARCHAR(50)  | Username (unik)    |
| password   | VARCHAR(255) | Password (di-hash) |
| nama       | VARCHAR(100) | Nama admin         |
| created_at | TIMESTAMP    | Waktu dibuat       |

---

## ✨ Fitur Lengkap

### 👨‍🏫 Portal Guru
- ✅ Login menggunakan NIP dan Password
- ✅ Dashboard dengan jam real-time
- ✅ Absen masuk (1 kali per hari)
- ✅ Absen pulang (setelah absen masuk)
- ✅ Riwayat absensi pribadi dengan filter bulan
- ✅ Statistik kehadiran

### 🛡️ Portal Admin
- ✅ Login dengan username & password
- ✅ Dashboard ringkasan kehadiran hari ini
- ✅ Progress bar tingkat kehadiran
- ✅ Tambah, edit, dan hapus akun guru
- ✅ Lihat semua data absensi dengan filter
- ✅ Filter absensi per bulan, tanggal, dan guru

---

## 🛠️ Teknologi yang Digunakan
- **PHP 7.4+** — Backend & Server-side Logic
- **MySQL** — Database
- **HTML5 & CSS3** — Tampilan
- **JavaScript** — Interaktivitas (jam real-time, dll)
- **Font Awesome 6** — Icon
- **Google Fonts** — Tipografi (Plus Jakarta Sans)

---

## ❗ Troubleshooting

**Halaman putih / error:**
- Pastikan XAMPP Apache & MySQL sudah Start
- Pastikan database sudah diimport
- Periksa konfigurasi di `includes/config.php`

**Cannot connect to database:**
- Cek password MySQL di `config.php` (default XAMPP: kosong)
- Pastikan nama database `absensi_guru` sudah dibuat

**Halaman login tidak bisa diakses:**
- Pastikan folder berada di `htdocs/absensi-guru/`
- Akses via `http://localhost/absensi-guru/`
