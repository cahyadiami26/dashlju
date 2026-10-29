<?php
/**
 * Controller Autentikasi - Menyesuaikan skema tabel users DMS
 * File: controllers/AuthController.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

class AuthController
{
    /** @var \PDO */
    private $db;
    
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_DURATION   = 900; // 15 menit
    
    public function __construct()
    {
        $this->db = getDBConnection();
    }
    
    /**
     * Proses login user
     * 
     * @param string $identifier Username atau NIK atau Email
     * @param string $password
     * @return array
     */
    public function login(string $identifier, string $password): array
    {
        $identifier = trim($identifier);
        
        if (empty($identifier) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Username dan password harus diisi.'
            ];
        }
        
        // Cek brute force
        if ($this->isLockedOut($identifier)) {
            $remaining = $this->getLockoutRemaining($identifier);
            return [
                'success' => false,
                'message' => "Akun dikunci sementara. Coba lagi dalam {$remaining} menit."
            ];
        }
        
        try {
            // Query sesuai skema tabel users DMS
            // Support login pakai username, NIK, atau email
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    nik,
                    name,
                    email,
                    position,
                    division,
                    department,
                    username,
                    password,
                    role,
                    avatar,
                    photo,
                    signature,
                    rfid,
                    is_active,
                    webmail_user,
                    webmail_pass
                FROM users
                WHERE (username = :ident OR nik = :ident OR email = :ident)
                LIMIT 1
            ");
            
            $stmt->execute(['ident' => $identifier]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->recordFailedAttempt($identifier);
                return [
                    'success' => false,
                    'message' => 'Username atau password salah.'
                ];
            }
            
            // Cek status aktif
            if ((int) $user['is_active'] !== 1) {
                return [
                    'success' => false,
                    'message' => 'Akun Anda telah dinonaktifkan. Hubungi administrator.'
                ];
            }
            
            // Verifikasi password
            if (!$this->verifyPassword($password, $user['password'])) {
                $this->recordFailedAttempt($identifier);
                $attemptsLeft = $this->getAttemptsLeft($identifier);
                
                $msg = 'Username atau password salah.';
                if ($attemptsLeft <= 2 && $attemptsLeft > 0) {
                    $msg .= " ({$attemptsLeft} percobaan tersisa)";
                }
                
                return [
                    'success' => false,
                    'message' => $msg
                ];
            }
            
            // === LOGIN BERHASIL ===
            
            // Auto-rehash password jika masih pakai algoritma lama
            $this->rehashPasswordIfNeeded($user['id'], $password, $user['password']);
            
            $this->clearFailedAttempts($identifier);
            session_regenerate_id(true);
            
            // Set session sesuai skema DMS
            setSessionUser([
                'id'         => (int) $user['id'],
                'nik'        => $user['nik'],
                'username'   => $user['username'],
                'name'       => $user['name'],
                'email'      => $user['email'],
                'role'       => $user['role'],
                'position'   => $user['position'],
                'division'   => $user['division'],
                'department' => $user['department'],
                'avatar'     => $user['avatar'],
                'photo'      => $user['photo'],
                'signature'  => $user['signature'],
                'rfid'       => $user['rfid'],
                'webmail_user' => $user['webmail_user'],
                'webmail_pass' => $user['webmail_pass']
            ]);
            
            $this->logLoginActivity((int) $user['id'], 'login_success');
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            return [
                'success' => true,
                'message' => 'Login berhasil! Mengalihkan...',
                'user'    => [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'webmail_user' => $user['webmail_user'],
                    'webmail_pass' => $user['webmail_pass']
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Login Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.'
            ];
        }
    }
    
    /**
     * Verifikasi password dengan multi-support:
     * - password_hash() modern (bcrypt, argon2)
     * - MD5 legacy (jika DMS masih pakai MD5)
     * - Plain (fallback - TIDAK RECOMMENDED)
     */
    private function verifyPassword(string $input, string $stored): bool
    {
        // Cek apakah pakai password_hash() modern
        // Bcrypt: $2y$, Argon2: $argon2
        if (preg_match('/^\$(2y|argon2)/', $stored)) {
            return password_verify($input, $stored);
        }
        
        // Legacy MD5 (32 karakter hex)
        if (strlen($stored) === 32 && ctype_xdigit($stored)) {
            return hash_equals($stored, md5($input));
        }
        
        // Legacy SHA1 (40 karakter hex)
        if (strlen($stored) === 40 && ctype_xdigit($stored)) {
            return hash_equals($stored, sha1($input));
        }
        
        // Fallback plain comparison (tidak aman - hanya untuk migrasi)
        return hash_equals($stored, $input);
    }
    
    /**
     * Auto-upgrade password lama ke bcrypt saat user login berhasil
     */
    private function rehashPasswordIfNeeded(int $userId, string $plainPassword, string $storedHash): void
    {
        $needsRehash = false;
        
        // Cek jika masih MD5/SHA1/plain
        if (!preg_match('/^\$(2y|argon2)/', $storedHash)) {
            $needsRehash = true;
        } elseif (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
            // Atau butuh upgrade cost/algoritma
            $needsRehash = true;
        }
        
        if ($needsRehash) {
            try {
                $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
                $stmt = $this->db->prepare("UPDATE users SET password = :pwd WHERE id = :id");
                $stmt->execute([
                    'pwd' => $newHash,
                    'id'  => $userId
                ]);
            } catch (PDOException $e) {
                error_log('Password rehash failed: ' . $e->getMessage());
            }
        }
    }
    
    // ========================================
    // Brute Force Protection
    // ========================================
    
    private function recordFailedAttempt(string $identifier): void
    {
        $key = 'login_attempts_' . md5($identifier . ($_SERVER['REMOTE_ADDR'] ?? ''));
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count'      => 0,
                'first_time' => time()
            ];
        }
        
        $_SESSION[$key]['count']++;
        $_SESSION[$key]['last_time'] = time();
        
        if ($_SESSION[$key]['count'] >= self::MAX_LOGIN_ATTEMPTS) {
            $_SESSION[$key]['locked_until'] = time() + self::LOCKOUT_DURATION;
        }
    }
    
    private function isLockedOut(string $identifier): bool
    {
        $key = 'login_attempts_' . md5($identifier . ($_SERVER['REMOTE_ADDR'] ?? ''));
        
        if (isset($_SESSION[$key]['locked_until'])) {
            if (time() < $_SESSION[$key]['locked_until']) {
                return true;
            }
            unset($_SESSION[$key]);
        }
        
        return false;
    }
    
    private function getLockoutRemaining(string $identifier): int
    {
        $key = 'login_attempts_' . md5($identifier . ($_SERVER['REMOTE_ADDR'] ?? ''));
        $remaining = ($_SESSION[$key]['locked_until'] ?? time()) - time();
        return max(1, (int) ceil($remaining / 60));
    }
    
    private function getAttemptsLeft(string $identifier): int
    {
        $key = 'login_attempts_' . md5($identifier . ($_SERVER['REMOTE_ADDR'] ?? ''));
        $used = $_SESSION[$key]['count'] ?? 0;
        return max(0, self::MAX_LOGIN_ATTEMPTS - $used);
    }
    
    private function clearFailedAttempts(string $identifier): void
    {
        $key = 'login_attempts_' . md5($identifier . ($_SERVER['REMOTE_ADDR'] ?? ''));
        unset($_SESSION[$key]);
    }
    
    /**
     * Log aktivitas login (opsional - abaikan jika tabel belum ada)
     */
    private function logLoginActivity(int $userId, string $activity): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO login_logs (user_id, activity, ip_address, user_agent, created_at)
                VALUES (:user_id, :activity, :ip, :ua, NOW())
            ");
            $stmt->execute([
                'user_id'  => $userId,
                'activity' => $activity,
                'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
                'ua'       => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (PDOException $e) {
            // Silent fail - jangan hentikan proses login
            error_log('Login Log Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Proses login user via NFC UID
     * 
     * @param string $rfidUid
     * @return array
     */
    public function loginByNfc(string $rfidUid): array
    {
        $rfidUid = trim($rfidUid);
        
        if (empty($rfidUid)) {
            return [
                'success' => false,
                'message' => 'UID NFC tidak valid.'
            ];
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id, nik, name, email, position, division, department,
                    username, role, avatar, photo, signature, rfid,
                    is_active, webmail_user, webmail_pass
                FROM users
                WHERE rfid = :rfid
                LIMIT 1
            ");
            
            $stmt->execute(['rfid' => $rfidUid]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Kartu NFC tidak terdaftar di sistem.'
                ];
            }
            
            if ((int) $user['is_active'] !== 1) {
                return [
                    'success' => false,
                    'message' => 'Akun Anda telah dinonaktifkan. Hubungi administrator.'
                ];
            }
            
            // === LOGIN BERHASIL VIA NFC ===
            session_regenerate_id(true);
            
            setSessionUser([
                'id'         => (int) $user['id'],
                'nik'        => $user['nik'],
                'username'   => $user['username'],
                'name'       => $user['name'],
                'email'      => $user['email'],
                'role'       => $user['role'],
                'position'   => $user['position'],
                'division'   => $user['division'],
                'department' => $user['department'],
                'avatar'     => $user['avatar'],
                'photo'      => $user['photo'],
                'signature'  => $user['signature'],
                'rfid'       => $user['rfid'],
                'webmail_user' => $user['webmail_user'],
                'webmail_pass' => $user['webmail_pass']
            ]);
            
            $this->logLoginActivity((int) $user['id'], 'login_nfc_success');
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            
            return [
                'success' => true,
                'message' => 'Login NFC berhasil! Mengalihkan...',
                'user'    => [
                    'name' => $user['name'],
                    'role' => $user['role']
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('NFC Login Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem database.'
            ];
        }
    }
}