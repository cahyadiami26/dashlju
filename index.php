<?php
/**
 * Entry Point: Login & Selector Dashboard
 * URL: https://lju.co.id/dashboard/
 * File: index.php
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/apps.php';
require_once __DIR__ . '/config/google.php';

$pdo = getDBConnection();
$error = '';
$apps = getAppsConfig();

// Tampilkan pesan error dari proses login Google (jika ada)
if (isset($_SESSION['google_login_error'])) {
    $error = $_SESSION['google_login_error'];
    unset($_SESSION['google_login_error']);
}

// Fallback jika fungsi belum terdefinisi
if (function_exists('getAllowedRedirectUrls')) {
    $allowed_urls = getAllowedRedirectUrls();
} else {
    $allowed_urls = array_values(array_column($apps, 'url'));
}

// Tangkap parameter redirect (kompatibel dengan sistem lama)
$target_system        = trim($_GET['target_system'] ?? $_POST['target_system'] ?? '');
$redirect_after_login = trim($_GET['redirect_after_login'] ?? $_POST['redirect_after_login'] ?? '');

$has_valid_target = !empty($target_system) && in_array($target_system, $allowed_urls, true);

// ====================================================================
// 1. JIKA USER SUDAH LOGIN SEBELUMNYA (Seamless SSO)
// ====================================================================
if (isLoggedIn()) {
    // Jika ada target_system valid, langsung redirect ke aplikasi tersebut
    if ($has_valid_target) {
        if (!empty($redirect_after_login) && $target_system === APP_URL_PM) {
            header('Location: ' . APP_URL_PM . $redirect_after_login);
            exit;
        }
        if ($target_system === APP_URL_PM) {
            header('Location: ' . APP_URL_PM . '/?page=dashboard');
            exit;
        }
        header('Location: ' . $target_system);
        exit;
    }

    // Jika tidak ada target, buka Dashboard Selector
    $user = getSessionUser();
    uasort($apps, fn($a, $b) => $a['order'] <=> $b['order']);
    require_once __DIR__ . '/views/dashboard.php';
    exit;
}

// ====================================================================
// 2. PROSES FORM LOGIN
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $target_url = $has_valid_target ? $target_system : null;

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Setup Session
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['department'] = $user['department'];
            $_SESSION['division']   = $user['division'];
            $_SESSION['position']   = $user['position'];
            $_SESSION['avatar']     = $user['avatar'];
            
            // --- TAMBAHKAN PENCATATAN LOG LOGIN BERHASIL ---
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO login_logs (user_id, activity, ip_address, user_agent, created_at)
                    VALUES (:user_id, 'login_success', :ip, :ua, NOW())
                ");
                $logStmt->execute([
                    'user_id' => $user['id'],
                    'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
                    'ua'      => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            } catch (PDOException $e) {
                error_log('Login Log Error (index.php): ' . $e->getMessage());
            }
            // ----------------------------------------------

            if (function_exists('logActivity')) {
                logActivity($user['id'], 'login', 'User logged in via Dashboard Portal');
            }
            
            session_regenerate_id(true);
            
            // Redirect Logic
            if (!empty($redirect_after_login) && $target_url === APP_URL_PM) {
                header('Location: ' . APP_URL_PM . $redirect_after_login);
                exit;
            }
            
            if ($target_url === APP_URL_PM) {
                header('Location: ' . APP_URL_PM . '/?page=dashboard');
                exit;
            } else if ($target_url) {
                header('Location: ' . $target_url);
                exit;
            } else {
                header('Location: https://lju.co.id/dashboard');
                exit;
            }
            
        } else {
            // --- TAMBAHKAN PENCATATAN LOG LOGIN GAGAL ---
            try {
                // Ambil user_id jika usernamenya ada di database, jika tidak biarkan NULL
                $userId = $user ? $user['id'] : null;
                
                $logStmt = $pdo->prepare("
                    INSERT INTO login_logs (user_id, activity, ip_address, user_agent, created_at)
                    VALUES (:user_id, 'login_failed', :ip, :ua, NOW())
                ");
                $logStmt->execute([
                    'user_id' => $userId,
                    'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
                    'ua'      => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            } catch (PDOException $e) {
                error_log('Login Failed Log Error (index.php): ' . $e->getMessage());
            }
            // --------------------------------------------

            $error = 'Username atau password salah';
        }
    } else {
        $error = 'Mohon isi username dan password';
    }
}

// ====================================================================
// 3. JIKA BELUM LOGIN -> TAMPILKAN FORM LOGIN
// ====================================================================
require_once __DIR__ . '/views/login.php';