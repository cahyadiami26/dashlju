<?php
/**
 * Konfigurasi Database - Menggunakan database DMS
 * File: config/database.php
 */

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'ljucoid_dms');
define('DB_USER', 'ljucoid_dms');
define('DB_PASS', 'icikiwir2026');
define('DB_CHARSET', 'utf8mb4');

define('BIDANG', [
    'Direksi','Keuangan', 'Pengadaan', 'Keamanan', 'Infokom', 'SDM', 
    'IT', 'Legal', 'Kesekretariatan', 'Jasa Konstruksi', 
    'Pengangkutan & Pergudangan', 'Perdagangan', 'Energi & Migas'
]);

define('DIVISI', [
    'Keuangan & Umum', 'Bisnis', 'Sekretariat', 'Pengawas Internal', 'Direksi'
]);

define('POSITIONS', [
    'Direktur Utama', 'Direktur Operasional', 'Kepala Bidang', 
    'Kepala Divisi', 'Manager', 'Sekretaris', 'Staff', 'System Administrator',
    'Corporate Secretary','Sekretaris Direksi','Sekretaris Komisaris'
]);

function getDBConnection(): PDO
{
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database Connection Error: ' . $e->getMessage());
            die('Koneksi database gagal. Silakan hubungi administrator.');
        }
    }
    
    return $pdo;
}

function getActiveUserCount(): int {
    global $pdo;
    if (!$pdo) { $pdo = getDBConnection(); }

    $sql = "
        WITH latest_activity AS (
            SELECT user_id, activity, created_at,
                   ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY created_at DESC) as rn
            FROM login_logs WHERE user_id != 1
        )
        SELECT COUNT(*) AS jumlah_user_login
        FROM latest_activity
        WHERE rn = 1 
          AND activity = 'login_success' 
          AND DATE(created_at) = CURDATE();
    ";

    try {
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['jumlah_user_login'] : 0;
    } catch (PDOException $e) {
        error_log("Gagal menghitung user login: " . $e->getMessage());
        return 0;
    }
}

/**
 * Mengambil daftar user yang sedang aktif online beserta detail profilnya
 */
function getOnlineUsersList(): array {
    global $pdo;
    if (!$pdo) { $pdo = getDBConnection(); }

    $sql = "
        WITH latest_activity AS (
            SELECT user_id, activity, created_at, ip_address,
                   ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY created_at DESC) as rn
            FROM login_logs
        )
        SELECT u.id, u.username, u.name, u.email, u.position, u.division, u.department, u.avatar, la.created_at as last_login
        FROM latest_activity la
        JOIN users u ON la.user_id = u.id
        WHERE la.rn = 1  
          AND la.activity = 'login_success' 
          AND DATE(la.created_at) = CURDATE()
          AND u.id != 1
        ORDER BY la.created_at DESC;
    ";

    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("Gagal mengambil daftar user online: " . $e->getMessage());
        return [];
    }
}