# dashlju — Portal SSO Login

Portal login terpusat (username/password, NFC, dan **Login with Google**).

## Login with Google — Panduan Implementasi

### 1. Persiapan kredensial (Google Cloud Console)
1. Buka <https://console.cloud.google.com/apis/credentials>.
2. Buat **OAuth client ID** tipe **Web application**.
3. Tambahkan **Authorized redirect URI** yang **sama persis** dengan URL callback portal, contoh:
   `https://lju.co.id/dashboard/google_callback.php`
4. Salin **Client ID** dan **Client Secret**.

### 2. Konfigurasi `.env` (tanpa hardcode kredensial)
```bash
cp .env.example .env      # lalu isi nilainya
```
| Variabel | Keterangan |
|---|---|
| `GOOGLE_CLIENT_ID` | Client ID dari Cloud Console |
| `GOOGLE_CLIENT_SECRET` | Client Secret dari Cloud Console |
| `GOOGLE_REDIRECT_URI` | Opsional. Default: `APP_URL_PORTAL/google_callback.php`. **Wajib sama persis** dengan Authorized redirect URI di Cloud Console |
| `APP_URL_PORTAL` | URL dasar portal, mis. `https://lju.co.id/dashboard` |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Kredensial MySQL |

> Environment variable dari server (Apache `SetEnv`, nginx `fastcgi_param`) lebih diprioritaskan daripada isi `.env`. File `.env` sudah masuk `.gitignore` — jangan pernah di-commit.

### 3. Migrasi database
Jalankan pada database portal (mis. `ljucoid_dms`). Jika tabel `users` sudah ada, cukup jalankan bagian migrasinya saja:
```sql
ALTER TABLE `users` ADD INDEX `idx_users_email` (`email`);

CREATE TABLE IF NOT EXISTS `login_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `activity` enum('login_success','login_failed','login_nfc_success','login_google_success','logout','timeout') DEFAULT 'login_success',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_activity` (`user_id`,`activity`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```
Pastikan kolom `users.email` **terisi dan unik** — pencocokan akun Google memakai email ini. User tanpa email tidak akan bisa login Google.

### 4. Prasyarat server
- PHP ≥ 7.4 dengan ekstensi **curl** dan **pdo_mysql** aktif.
- Akses outbound ke `accounts.google.com`, `oauth2.googleapis.com`, `openidconnect.googleapis.com` (port 443).
- Portal diakses via **HTTPS** (wajib untuk cookie session & OAuth redirect).

### 5. Verifikasi otomatis
Jalankan health-check untuk memastikan semua langkah di atas benar:
```bash
php google_setup_check.php          # dari SSH/cPanel terminal
# atau buka https://domain-anda/google_setup_check.php dari browser di server itu sendiri (localhost)
```
Hasilnya berupa daftar `[ OK ] / [WARN] / [FAIL]` per aspek: `.env`, redirect URI, konektivitas & validitas kredensial ke Google, koneksi DB, struktur migrasi, dan kesiapan data email user. **Hapus file ini setelah setup selesai.**

### 6. Uji coba manual
1. Buka form login → tombol **"Masuk dengan Google"** harus muncul (hanya tampil jika kredensial terisi).
2. Klik → pilih akun Google → consent → kembali ke portal dalam keadaan login.
3. Email Google **tidak terdaftar** → kembali ke login dengan pesan error yang jelas.
4. Cek `login_logs`: aktivitas tercatat sebagai `login_google_success`.

## Alur File
| File | Peran |
|---|---|
| `google_auth.php` | Inisiator OAuth (buat state anti-CSRF, redirect ke Google) |
| `google_callback.php` | Callback: validasi state → tukar code→token → ambil profil → cocokkan `users.email` → buat session |
| `config/google.php` | Konfigurasi & helper OAuth (dibaca dari `.env`) |
| `config/env.php` | Loader `.env` murni PHP tanpa library |
| `google_setup_check.php` | Health-check implementasi (hapus setelah dipakai) |
