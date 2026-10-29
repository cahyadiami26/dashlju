<?php
/**
 * Skrip Penghubung SSO cPanel Webmail
 * File: open_webmail.php
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$email    = $_SESSION['webmail_user'] ?? '';
$password = $_SESSION['webmail_pass'] ?? '';

if (empty($email) || empty($password)) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT webmail_user, webmail_pass, email FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $userData = $stmt->fetch();

        if ($userData) {
            $email    = !empty($userData['webmail_user']) ? $userData['webmail_user'] : $userData['email'];
            $password = $userData['webmail_pass'] ?? '';
            
            $_SESSION['webmail_user'] = $email;
            $_SESSION['webmail_pass'] = $password;
        }
    } catch (Exception $e) {
        // Abaikan
    }
}

if (empty($email) || empty($password)) {
    die("<div style='font-family:sans-serif; padding:20px; text-align:center;'>
            <h3>Akses Gagal</h3>
            <p>Kredensial webmail belum diatur untuk akun Anda.</p>
            <a href='https://lju.co.id/dashboard'>Kembali ke Dashboard</a>
         </div>");
}

// Konfigurasi cPanel Host
$cpanel_host = "mail.lju.co.id";
$cpanel_port = "2096";

// Solusi Alternatif yang Andal: 
// Mengarahkan ke halaman webmail dengan menyertakan parameter user, 
// atau menggunakan halaman auto-login berbasis form HTML POST otomatis (Invisible Auto-Submit)
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Mengalihkan ke Webmail...</title>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .loader-box { text-align: center; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .spinner { width: 40px; height: 40px; border: 4px solid #e2e8f0; border-top: 4px solid #0284c7; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="loader-box">
        <div class="spinner"></div>
        <h3>Menghubungkan ke Webmail...</h3>
        <p style="color: #64748b; font-size: 14px;">Mohon tunggu sebentar, Anda sedang dialihkan secara otomatis.</p>
    </div>

    <!-- Form tersembunyi untuk melakukan Auto-POST ke webmail jika didukung, 
         atau lempar ke halaman login dengan prefill via Javascript/URL -->
    <form id="autoLoginForm" action="https://<?php echo $cpanel_host; ?>:<?php echo $cpanel_port; ?>/login/" method="POST" style="display:none;">
        <input type="hidden" name="user" value="<?php echo htmlspecialchars($email); ?>">
        <input type="hidden" name="pass" value="<?php echo htmlspecialchars($password); ?>">
    </form>

    <script>
        // Coba lakukan otomatisasi pengisian atau POST langsung ke endpoint login webmail port 2096
        setTimeout(function() {
            document.getElementById('autoLoginForm').submit();
        }, 500);
    </script>
</body>
</html>