<?php
/**
 * Setup & Health-Check Login with Google — Versi cPanel (tanpa SSH/CLI)
 * File: cpanel_deploy_check.php
 *
 * Buka lewat browser:
 *   https://domain-anda/dashboard/cpanel_deploy_check.php?key=GANTI_KEY_ANDA
 *
 * Fungsi (semua via browser, tidak butuh terminal):
 *   1. Menulis/memperbarui file .env langsung dari form
 *   2. Verifikasi kredensial Google, redirect URI, konektivitas, database, data users
 *   3. Menjalankan migrasi SQL (index email + tabel login_logs) lewat tombol
 *   4. Menampilkan URL consent Google untuk uji alur login nyata
 *
 * PENTING: setelah setup selesai, HAPUS file ini dari server (cPanel > File Manager)!
 */

// ---------- Proteksi akses ----------
$ACCESS_KEY = 'GANTI_KEY_ANDA';      // <-- GANTI nilainya sebelum upload ke server
$key = $_GET['key'] ?? $_POST['key'] ?? '';
if (!is_string($key) || $key === '' || !hash_equals($ACCESS_KEY, $key)) {
    http_response_code(403);
    exit('Akses ditolak. Gunakan ?key=<ACCESS_KEY> yang benar.');
}

require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/google.php';

@header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

$msgs  = [];
$flash = '';

// ---------- Aksi: simpan .env ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_env') {
    $fields = [
        'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GOOGLE_REDIRECT_URI',
        'APP_URL_PORTAL', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS',
    ];
    $existing = [];
    // Root proyek = folder tempat file ini berada (dashboard/), bukan parent-nya,
    // agar aman walau config/env.php dipanggil dengan path relatif.
    $envPath  = __DIR__ . '/.env';
    if (is_file($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
            $ln = trim($ln);
            if ($ln === '' || $ln[0] === '#') continue;
            $p = strpos($ln, '=');
            if ($p === false) continue;
            $existing[trim(substr($ln, 0, $p))] = substr($ln, $p + 1);
        }
    }
    foreach ($fields as $f) {
        $v = trim((string)($_POST[$f] ?? ''));
        if ($v !== '') $existing[$f] = $v;
    }
    $out = "# Dihasilkan oleh cpanel_deploy_check.php pada " . date('Y-m-d H:i:s') . "\n";
    foreach ($existing as $k => $v) $out .= "$k=$v\n";
    if (@file_put_contents($envPath, $out) !== false) {
        $flash = '<div class="flash ok">File <code>.env</code> berhasil ditulis ke <code>' . htmlspecialchars($envPath) . '</code>. Checklist di bawah sudah memakai nilai terbaru.</div>';
    } else {
        $flash = '<div class="flash bad">GAGAL menulis <code>.env</code> ke <code>' . htmlspecialchars($envPath) . '</code>. Buat/edit file <code>.env</code> manual via cPanel &gt; File Manager (aktifkan "Show Hidden Files").</div>';
    }
}

// ---------- Aksi: jalankan migrasi ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run_migrate') {
    require_once __DIR__ . '/config/database.php';
    try {
        $pdo  = getDBConnection();
        $sqls = [
            'Kolom users.email' => "ALTER TABLE `users` ADD COLUMN `email` VARCHAR(100) NULL DEFAULT NULL",
            'Index idx_users_email' => "ALTER TABLE `users` ADD INDEX `idx_users_email` (`email`)",
            'Tabel login_logs' => "CREATE TABLE IF NOT EXISTS `login_logs` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) DEFAULT NULL,
                `activity` enum('login_success','login_failed','login_nfc_success','login_google_success','logout','timeout') DEFAULT 'login_success',
                `ip_address` varchar(45) DEFAULT NULL,
                `user_agent` varchar(255) DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `idx_user_activity` (`user_id`,`activity`,`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        ];
        foreach ($sqls as $label => $sql) {
            try {
                $pdo->exec($sql);
                $msgs[] = "<li class='ok'>[OK] $label — berhasil dijalankan.</li>";
            } catch (PDOException $e) {
                $em = $e->getMessage();
                if (stripos($em, 'Duplicate') !== false || stripos($em, 'already exists') !== false) {
                    $msgs[] = "<li class='warn'>[LEWAT] $label — sudah ada, tidak masalah.</li>";
                } else {
                    $msgs[] = "<li class='bad'>[GAGAL] $label — " . htmlspecialchars($em) . "</li>";
                }
            }
        }
    } catch (Throwable $e) {
        $msgs[] = "<li class='bad'>[GAGAL] Koneksi DB untuk migrasi: " . htmlspecialchars($e->getMessage()) . "</li>";
    }
}

// ---------- Helper tampilan ----------
$pass = 0; $warn = 0; $fail = 0;
function ok($m)   { global $pass; $pass++; echo "<div class='row ok'>[ OK ] $m</div>"; }
function warn($m) { global $warn; $warn++; echo "<div class='row warn'>[WARN] $m</div>"; }
function bad($m)  { global $fail; $fail++; echo "<div class='row bad'>[FAIL] $m</div>"; }
function info($m) { echo "<div class='row info'>&nbsp;&nbsp;&nbsp;&nbsp;$m</div>"; }
function h($s)    { return htmlspecialchars((string)$s, ENT_QUOTES); }

// Deteksi URL aplikasi saat ini (untuk saran redirect URI)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$appUrl = $scheme . '://' . $host . $dir;
$suggestedRedirect = $appUrl . '/google_callback.php';
$keyH = h($_GET['key'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex,nofollow">
<title>Setup Check — Login with Google (cPanel)</title>
<style>
 body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:24px;background:#f6f7f9;color:#222}
 h1{font-size:20px} h2{font-size:16px;margin-top:26px;border-bottom:2px solid #ddd;padding-bottom:4px}
 .row{padding:2px 0;font-size:14px} code{background:#eee;padding:1px 5px;border-radius:3px;font-size:13px}
 .ok{color:#0a7a2f}.warn{color:#a06a00}.bad{color:#c02020}.info{color:#555}
 .box{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px;margin:14px 0}
 .grid{display:grid;grid-template-columns:220px 1fr;gap:6px 12px;align-items:center}
 input[type=text],input[type=password]{width:100%;box-sizing:border-box;padding:7px;border:1px solid #bbb;border-radius:5px;font-size:13px}
 button{background:#0b66c3;color:#fff;border:0;padding:9px 18px;border-radius:6px;font-size:14px;cursor:pointer;margin-top:10px}
 button:hover{background:#0952a0}
 .flash{padding:10px 14px;border-radius:6px;margin:10px 0;font-size:14px}
 .flash.ok{background:#e5f6ea;border:1px solid #9ddeb2}.flash.bad{background:#fdeaea;border:1px solid #f2b4b4}
 ul{margin:6px 0}
</style>
</head>
<body>
<h1>&#128295; Setup &amp; Health-Check Login with Google — khusus cPanel (tanpa SSH/CLI)</h1>
<p style="font-size:14px">Halaman ini membaca konfigurasi persis seperti yang dipakai aplikasi (loader <code>config/env.php</code>). Perbaiki semua baris <span class="bad">[FAIL]</span> sampai checklist hijau, lalu uji login dan <strong>hapus file ini</strong>.</p>
<?= $flash ?>

<!-- ==================== 1. FORM .env ==================== -->
<div class="box">
<h2 style="margin-top:0">1) Isi kredensial (otomatis ditulis ke file <code>.env</code>)</h2>
<p style="font-size:13px">Kosongkan kolom jika tidak ingin mengubah nilai yang sudah ada. Target file: <code><?= h(dirname(__DIR__)) ?>/.env</code> (dilindungi <code>.htaccess</code>).</p>
<form method="post">
  <input type="hidden" name="action" value="save_env">
  <input type="hidden" name="key" value="<?= $keyH ?>">
  <div class="grid">
    <label>GOOGLE_CLIENT_ID</label><input type="text" name="GOOGLE_CLIENT_ID" placeholder="<?= h(GOOGLE_CLIENT_ID ?: 'xxxxxxxx.apps.googleusercontent.com') ?>" autocomplete="off">
    <label>GOOGLE_CLIENT_SECRET</label><input type="password" name="GOOGLE_CLIENT_SECRET" placeholder="<?= GOOGLE_CLIENT_SECRET ? '(tersimpan — kosongkan = tidak berubah)' : 'GOCSPX-...' ?>" autocomplete="new-password">
    <label>GOOGLE_REDIRECT_URI</label><input type="text" name="GOOGLE_REDIRECT_URI" placeholder="<?= h($suggestedRedirect) ?>">
    <label>APP_URL_PORTAL</label><input type="text" name="APP_URL_PORTAL" placeholder="<?= h($appUrl) ?>">
    <label>DB_HOST</label><input type="text" name="DB_HOST" placeholder="<?= h(defined('DB_HOST') ? DB_HOST : 'localhost') ?>">
    <label>DB_PORT</label><input type="text" name="DB_PORT" placeholder="<?= h(defined('DB_PORT') ? DB_PORT : '3306') ?>">
    <label>DB_NAME</label><input type="text" name="DB_NAME" placeholder="<?= h(defined('DB_NAME') ? DB_NAME : '') ?>">
    <label>DB_USER</label><input type="text" name="DB_USER" placeholder="<?= h(defined('DB_USER') ? DB_USER : '') ?>">
    <label>DB_PASS</label><input type="password" name="DB_PASS" placeholder="<?= (defined('DB_PASS') && DB_PASS !== '') ? '(tersimpan — kosongkan = tidak berubah)' : 'password DB' ?>" autocomplete="new-password">
  </div>
  <button type="submit">Simpan ke .env &amp; periksa ulang</button>
</form>
</div>

<!-- ==================== 2. CHECKLIST ==================== -->
<h2>2) Checklist otomatis</h2>
<div class="box">

<h2 style="margin-top:0;border:0;padding:0">A. Konfigurasi (.env)</h2>
<?php
if (loadEnv()) {
    ok('File <code>.env</code> ditemukan &amp; dimuat dari root proyek.');
} else {
    warn('File <code>.env</code> belum ada — aplikasi masih memakai default/environment server. Gunakan form bagian 1 untuk membuatnya.');
}
if (isGoogleLoginEnabled()) {
    ok('GOOGLE_CLIENT_ID &amp; GOOGLE_CLIENT_SECRET terisi → tombol "Masuk dengan Google" AKTIF.');
    info('Client ID: <code>' . h(substr(GOOGLE_CLIENT_ID, 0, 28)) . '…</code>');
} else {
    bad('GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET masih kosong. Isi form bagian 1.');
}
?>

<h2>B. Redirect URI (harus SAMA PERSIS dengan Cloud Console)</h2>
<?php
info('Nilai yang akan dikirim ke Google: <code><strong>' . h(GOOGLE_REDIRECT_URI) . '</strong></code>');
info('URL aplikasi ini terdeteksi:       <code>' . h($appUrl) . '</code>');
if (stripos(GOOGLE_REDIRECT_URI, 'localhost') !== false || stripos(GOOGLE_REDIRECT_URI, '127.0.0.1') !== false) {
    bad('GOOGLE_REDIRECT_URI masih mengarah ke localhost. Simpan nilai: <code>' . h($suggestedRedirect) . '</code>');
} elseif (rtrim(GOOGLE_REDIRECT_URI, '/') !== rtrim($suggestedRedirect, '/')) {
    warn('Redirect URI berbeda dari URL aplikasi saat ini. Bukan error selama Cloud Console mendaftarkan nilai berikut persis:<br>&nbsp;&nbsp;<code>' . h(GOOGLE_REDIRECT_URI) . '</code><br>Namun jika ragu, samakan dengan: <code>' . h($suggestedRedirect) . '</code>');
} else {
    ok('Redirect URI cocok dengan lokasi file <code>google_callback.php</code> di server ini.');
}
?>

<h2>C. Konektivitas &amp; validitas kredensial ke Google</h2>
<?php
if (!function_exists('curl_init')) {
    bad('Ekstensi cURL tidak aktif. cPanel → <em>Select PHP Version</em> → Extensions → centang <code>curl</code>.');
} else {
    ok('Ekstensi cURL tersedia.');
    $ch = curl_init(GOOGLE_TOKEN_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'code'          => 'setup-check-invalid',
            'client_id'     => GOOGLE_CLIENT_ID ?: 'check@example.com',
            'client_secret' => GOOGLE_CLIENT_SECRET ?: 'check-secret',
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        bad('Gagal menghubungi googleapis.com: ' . h($err));
        info('Kemungkinan hosting memblokir koneksi keluar port 443 — tanyakan ke support hosting.');
    } else {
        $j    = json_decode((string)$resp, true);
        $gErr = is_array($j) ? ($j['error'] ?? '') : '';
        ok('Server Google dapat dijangkau (HTTP ' . (int)$http . '), SSL verify berfungsi.');
        switch ($gErr) {
            case 'invalid_client':
                bad('Google menolak Client ID/Secret Anda (<code>invalid_client</code>). Salin ulang dari Cloud Console ke form bagian 1.');
                break;
            case 'unauthorized_client':
                bad('OAuth client bukan tipe "Web application" atau authorization code flow tidak diizinkan.');
                break;
            case 'invalid_grant':
                warn('Google merespon <code>invalid_grant</code> — umumnya redirect URI tidak sama persis dengan yang terdaftar. Cek bagian B.');
                break;
            default:
                ok('Kredensial diterima Google (probe hanya ditolak karena kode uji tidak valid — normal).');
        }
    }
}
?>

<h2>D. Database</h2>
<?php
$pdo = null;
if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
    bad('Driver pdo_mysql tidak aktif. cPanel → Select PHP Version → Options → centang <code>pdo_mysql</code> &amp; <code>mysqli</code>.');
} else {
    ok('Driver pdo_mysql tersedia.');
    require_once __DIR__ . '/config/database.php';
    try {
        $pdo = getDBConnection();
        ok('Koneksi MySQL berhasil: <code>' . h(DB_USER) . '@' . h(DB_HOST) . ':' . h(DB_PORT) . '/' . h(DB_NAME) . '</code>');
    } catch (Throwable $e) {
        bad('Koneksi database GAGAL: ' . h($e->getMessage()));
        info('Cek DB_HOST/DB_NAME/DB_USER/DB_PASS di form bagian 1 (lihat cPanel → MySQL Databases).');
    }
}
if ($pdo instanceof PDO) {
    $colEmail = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'email'")->fetch();
    $colEmail ? ok('Kolom <code>users.email</code> tersedia (' . h($colEmail['Type']) . ').')
              : bad('Kolom <code>users.email</code> TIDAK ada — klik tombol "Jalankan migrasi" di bawah.');
    $hasIdx = !empty($pdo->query("SHOW INDEX FROM `users` WHERE Key_name='idx_users_email'")->fetchAll());
    $hasIdx ? ok('Index <code>idx_users_email</code> terpasang.')
            : warn('Index <code>idx_users_email</code> belum ada — klik tombol "Jalankan migrasi".');
    $hasLog = $pdo->query("SHOW TABLES LIKE 'login_logs'")->fetch();
    $hasLog ? ok('Tabel <code>login_logs</code> tersedia (pencatatan aktivitas login).')
            : warn('Tabel <code>login_logs</code> belum ada — klik tombol "Jalankan migrasi".');
}
?>
<?php if ($msgs): ?>
<h2>Hasil migrasi terakhir</h2>
<ul><?= implode('', $msgs) ?></ul>
<?php endif; ?>

<h2>E. Jalankan migrasi database (otomatis)</h2>
<p style="font-size:13px">Menambahkan kolom/index <code>email</code> dan tabel <code>login_logs</code>. Aman dijalankan berulang kali (yang sudah ada akan dilewati).</p>
<form method="post">
  <input type="hidden" name="action" value="run_migrate">
  <input type="hidden" name="key" value="<?= $keyH ?>">
  <button type="submit">&#9654; Jalankan migrasi database</button>
</form>

<h2>F. Kesiapan data users</h2>
<?php
if ($pdo instanceof PDO) {
    try {
        $total    = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $withMail = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE email IS NOT NULL AND TRIM(email)<>''")->fetchColumn();
        $active   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1 AND email IS NOT NULL AND TRIM(email)<>''")->fetchColumn();
        info("Total user: <strong>$total</strong> · punya email: <strong>$withMail</strong> · aktif + ber-email (bisa login Google): <strong>$active</strong>");
        if ($withMail === 0) {
            bad('Belum ada user ber-email. Login Google tidak akan bisa mencocokkan akun.');
            info('Isi kolom email via cPanel → phpMyAdmin — harus SAMA dengan Gmail/Workspace user.');
        } else {
            ok('Ada user ber-email yang bisa dicocokkan.');
        }
        $dups = $pdo->query("SELECT email, COUNT(*) c FROM users WHERE email IS NOT NULL AND TRIM(email)<>'' GROUP BY LOWER(TRIM(email)) HAVING c>1 LIMIT 5")->fetchAll();
        if ($dups) {
            warn('Ditemukan email duplikat (pencocokan memakai baris pertama):');
            foreach ($dups as $d) info('· ' . h($d['email']) . ' (' . (int)$d['c'] . 'x)');
        } else {
            ok('Tidak ada email duplikat.');
        }
        $noAt = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE email IS NOT NULL AND TRIM(email)<>'' AND email NOT LIKE '%@%'")->fetchColumn();
        $noAt === 0 ? ok('Format semua email valid (mengandung "@").')
                    : warn("$noAt email tidak mengandung '@' — tidak akan pernah cocok.");
    } catch (Throwable $e) {
        bad('Gagal memeriksa data users: ' . h($e->getMessage()));
    }
} else {
    warn('Lewati pemeriksaan data users (DB belum terhubung).');
}
?>

<h2>G. Uji alur OAuth (menuju halaman Google)</h2>
<?php
if (isGoogleLoginEnabled()) {
    $state = bin2hex(random_bytes(16));
    $url   = buildGoogleAuthUrl($state);
    ok('State token anti-CSRF berhasil dibuat: <code>' . h($state) . '</code>');
    info('<a href="' . h($url) . '" target="_blank" rel="noopener"><strong>▶ Klik di sini untuk mencoba login Google sekarang</strong></a>');
    info('Setelah memilih akun, browser kembali ke <code>' . h(GOOGLE_REDIRECT_URI) . '</code> — jika sukses Anda masuk dashboard; jika gagal, pesan error tampil di halaman login.');
} else {
    warn('Lewati — kredensial Google belum lengkap.');
}
?>
</div>

<!-- ==================== 3. RINGKASAN ==================== -->
<div class="box">
<h2 style="margin-top:0;border:0;padding:0">Ringkasan</h2>
<p><span class='ok'><?= $pass ?> OK</span> · <span class='warn'><?= $warn ?> WARN</span> · <span class='bad'><?= $fail ?> FAIL</span></p>
<?php if ($fail === 0): ?>
<p class="ok"><strong>Siap digunakan!</strong> Langkah penutup:</p>
<ul>
<li>Buka form login portal → tombol <em>"Masuk dengan Google"</em> muncul → uji satu akun pegawai.</li>
<li>Cek cPanel → phpMyAdmin → tabel <code>login_logs</code> berisi <code>login_google_success</code>.</li>
<li><strong>Hapus file <code>cpanel_deploy_check.php</code></strong> (dan <code>google_setup_check.php</code> jika ada) dari server via cPanel → File Manager.</li>
</ul>
<?php else: ?>
<p class="bad">Masih ada <strong><?= $fail ?> masalah (FAIL)</strong> — ikuti petunjuk pada tiap baris merah di atas.</p>
<?php endif; ?>
</div>

<p style="font-size:12px;color:#777">Keamanan: file ini melindungi diri dengan access key dan menampilkan informasi konfigurasi terbatas. Setelah seluruh checklist hijau dan login terbukti berjalan, segera hapus file ini dari server.</p>
</body>
</html>
