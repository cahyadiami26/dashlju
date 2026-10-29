<?php
/**
 * Logout - Menghancurkan session di semua aplikasi
 * File: logout.php
 */

require_once __DIR__ . '/config/session.php';

// Log logout activity (mendukung logout manual atau timeout)
if (isLoggedIn()) {
    try {
        require_once __DIR__ . '/config/database.php';
        $db = getDBConnection();
        
        // Tentukan jenis aktivitas berdasarkan parameter URL 'reason'
        $reason = $_GET['reason'] ?? 'logout';
        $activity = ($reason === 'timeout') ? 'timeout' : 'logout';
        
        $stmt = $db->prepare("
            INSERT INTO login_logs (user_id, activity, ip_address, user_agent, created_at)
            VALUES (:uid, :activity, :ip, :ua, NOW())
        ");
        $stmt->execute([
            'uid'      => $_SESSION['user_id'],
            'activity' => $activity,
            'ip'       => $_SERVER['REMOTE_ADDR'],
            'ua'       => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        // Lanjutkan logout meskipun logging gagal
        error_log('Logout Log Error: ' . $e->getMessage());
    }
}

// Hancurkan session menggunakan helper
destroySession();

// Redirect ke halaman login dengan membawa status (opsional untuk menampilkan pesan)
$redirectReason = $_GET['reason'] ?? 'manual';
header('Location: /dashboard/?status=logged_out&reason=' . $redirectReason);
exit;