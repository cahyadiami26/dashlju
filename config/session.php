<?php
/**
 * Konfigurasi Session Bersama & Sinkronisasi Database
 * File: config/session.php
 */

if (headers_sent($file, $line)) {
    die("Headers sudah terkirim di $file baris $line. Session tidak bisa dimulai.");
}

session_name('LJU_APPS');
ini_set('session.cookie_domain', '.lju.co.id');

session_set_cookie_params([
    'lifetime' => 86400,
    'path'     => '/',
    'domain'   => '.lju.co.id',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly'  => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Helper: Ambil data user terbaru dengan query langsung ke tabel users di MySQL
 */
function getSessionUser(): array
{
    // Jika belum login, kembalikan data kosong dari session
    if (empty($_SESSION['user_id'])) {
        return [
            'id'           => '',
            'nik'          => '',
            'username'     => '',
            'name'         => '',
            'email'        => '',
            'role'         => '',
            'position'     => '',
            'division'     => '',
            'department'   => '',
            'avatar'       => '',
            'photo'        => '',
            'signature'    => '',
            'rfid'         => '',
            'webmail_user' => '',
            'webmail_pass' => '',
        ];
    }

    // Lakukan query ke database untuk mendapatkan data terkini
    try {
        // Pastikan file koneksi database sudah di-require sebelumnya atau panggil di sini
        require_once __DIR__ . '/database.php';
        $db = getDBConnection();

        $stmt = $db->prepare("
            SELECT id, nik, username, name, email, role, position, division, department, avatar, photo, signature, rfid, webmail_user, webmail_pass 
            FROM users 
            WHERE id = :id 
            LIMIT 1
        ");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $userRow = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($userRow) {
            // Sinkronkan kembali ke variabel session agar data selalu up-to-date
            $_SESSION['user_id']    = $userRow['id'];
            $_SESSION['user_nik']   = $userRow['nik'];
            $_SESSION['username']   = $userRow['username'];
            $_SESSION['name']   = $userRow['username'];
            $_SESSION['user_name']  = $userRow['name'];
            $_SESSION['user_email'] = $userRow['email'];
            $_SESSION['role']       = $userRow['role'];
            $_SESSION['position']   = $userRow['position'];
            $_SESSION['division']   = $userRow['division'];
            $_SESSION['department'] = $userRow['department'];
            $_SESSION['avatar']     = $userRow['avatar'];
            $_SESSION['photo']      = $userRow['photo'];
            $_SESSION['signature']  = $userRow['signature'];
            $_SESSION['rfid']       = $userRow['rfid'];
            $_SESSION['webmail_user'] = $userRow['webmail_user'];
            $_SESSION['webmail_pass'] = $userRow['webmail_pass'];
        }
    } catch (\Throwable $e) {
        // Fallback jika terjadi kendala koneksi database, gunakan data yang ada di session saat ini
        error_log('Gagal sinkronisasi session user dari database: ' . $e->getMessage());
    }

    // Mengembalikan array data user sesuai struktur yang diminta
    return [
        'id'           => $_SESSION['user_id']       ?? '',
        'nik'          => $_SESSION['user_nik']      ?? '',
        'username'     => $_SESSION['username']      ?? '',
        'name'         => $_SESSION['user_name']     ?? '',
        'email'        => $_SESSION['user_email']    ?? '',
        'role'         => $_SESSION['role']          ?? '',
        'position'     => $_SESSION['position']      ?? '',
        'division'     => $_SESSION['division']      ?? '',
        'department'   => $_SESSION['department']    ?? '',
        'avatar'       => $_SESSION['avatar']        ?? '',
        'photo'        => $_SESSION['photo']         ?? '',
        'signature'    => $_SESSION['signature']     ?? '',
        'rfid'         => $_SESSION['rfid']          ?? '',
        'webmail_user' => $_SESSION['webmail_user']  ?? '',
        'webmail_pass' => $_SESSION['webmail_pass']  ?? '',
    ];
    checkSessionTimeout();
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']) && !empty($_SESSION['username']);
}

function destroySession(): void
{
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Helper: Catat log aktivitas logout/timeout ke database
 */
function logSessionActivity(int $userId, string $activity): void
{
    try {
        require_once __DIR__ . '/config/database.php';
        $db = getDBConnection();
        
        $stmt = $db->prepare("
            INSERT INTO login_logs (user_id, activity, ip_address, user_agent, created_at)
            VALUES (:user_id, :activity, :ip, :ua, NOW())
        ");
        $stmt->execute([
            'user_id'  => $userId,
            'activity' => $activity,
            'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
            'ua'       => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (\Throwable $e) {
        error_log('Session Log Error: ' . $e->getMessage());
    }
}

/**
 * Helper: Cek apakah session sudah timeout karena idle (30 menit)
 */
function checkSessionTimeout(): void
{
    $timeoutDuration = 1800; // 30 menit dalam detik (30 * 60)

    if (isset($_SESSION['last_activity'])) {
        $elapsedTime = time() - $_SESSION['last_activity'];
        
        if ($elapsedTime > $timeoutDuration) {
            // Catat log jika user_id tersedia sebelum session dihapus
            if (!empty($_SESSION['user_id'])) {
                logSessionActivity((int) $_SESSION['user_id'], 'timeout');
            }
            
            destroySession();
            header('Location: /login.php?timeout=1');
            exit;
        }
    }
    
    // Perbarui waktu aktivitas terakhir dengan waktu saat ini
    $_SESSION['last_activity'] = time();
}