<?php
/**
 * View Login Portal - Clean & Elegant Theme
 * File: views/login.php
 * Logo di bagian kanan (atas header text)
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LJU Apps Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">


    <style>
        :root {
            --primary-blue: #1e40af;
            --primary-teal: #14b8a6;
            --text-dark: #0f172a;
            --text-gray: #64748b;
            --bg-input: #f8fafc;
            --border-input: #e2e8f0;
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f0fdfa;
            background-image: 
                radial-gradient(at 0% 0%, hsla(186,100%,86%,1) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(199,89%,48%,1) 0, transparent 50%), 
                radial-gradient(at 100% 100%, hsla(173,80%,40%,1) 0, transparent 50%),
                radial-gradient(at 0% 100%, hsla(217,100%,45%,0.8) 0, transparent 50%);
            padding: 20px;
        }

        /* Container Card Utama */
        .login-container {
            width: 100%;
            max-width: 1000px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            display: flex;
            overflow: hidden;
            min-height: 580px;
        }

        /* BAGIAN KIRI: Ilustrasi saja (tanpa logo) */
        .login-left {
            flex: 1.2;
            background-color: #f8fafc;
            padding: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            border-right: 1px solid #f1f5f9;
        }

        .illustration {
            width: 100%;
            max-width: 380px;
        }

        .illustration img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Fallback ilustrasi jika image tidak ada */
        .illustration-fallback {
            width: 100%;
            max-width: 320px;
            opacity: 0.15;
            filter: grayscale(100%);
        }

        /* BAGIAN KANAN: Logo + Form Login */
        .login-right {
            flex: 1;
            padding: 48px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
        }

        /* ===== LOGO di atas header (bagian kanan) ===== */
        .logo-top {
            display: flex;
            justify-content: center;
            margin-bottom: 28px;
        }

        .logo-top img {
            height: 56px;          /* Ukuran logo terkontrol */
            width: auto;
            max-width: 200px;
            object-fit: contain;
            display: block;
        }

        /* Fallback jika logo image gagal load */
        .logo-fallback {
            display: none;
            align-items: center;
            gap: 10px;
            font-size: 28px;
            font-weight: 800;
            color: var(--primary-blue);
            letter-spacing: -0.5px;
        }

        .logo-fallback span {
            color: var(--primary-teal);
        }

        /* Header Text */
        .header-text {
            text-align: center;
            margin-bottom: 36px;
        }

        .header-text h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 6px;
            letter-spacing: -0.3px;
        }

        .header-text p {
            font-size: 13px;
            color: var(--text-gray);
            font-weight: 500;
            line-height: 1.5;
        }

        .header-text .company {
            display: block;
            margin-top: 2px;
            font-size: 12px;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Alert Error */
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #ef4444;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Form */
        .form-group {
            margin-bottom: 18px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            width: 20px;
            height: 20px;
            color: #94a3b8;
            pointer-events: none;
        }

        .input-wrapper input {
            width: 100%;
            padding: 15px 16px 15px 46px;
            background: var(--bg-input);
            border: 1.5px solid var(--border-input);
            border-radius: 12px;
            font-size: 14px;
            color: var(--text-dark);
            font-family: inherit;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .input-wrapper input::placeholder {
            color: #94a3b8;
            font-weight: 400;
        }

        .input-wrapper input:focus {
            outline: none;
            border-color: var(--primary-teal);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.1);
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(90deg, var(--primary-blue) 0%, var(--primary-teal) 100%);
            border: none;
            border-radius: 12px;
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px rgba(20, 184, 166, 0.2);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px rgba(20, 184, 166, 0.3);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit svg {
            width: 18px;
            height: 18px;
        }

        /* ===== Login with Google ===== */
        .divider-or {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 22px 0 16px;
            color: var(--text-gray);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
        }

        .divider-or::before,
        .divider-or::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--border-input);
        }

        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 13px 24px;
            border: 1px solid var(--border-input);
            border-radius: 12px;
            background: #ffffff;
            color: var(--text-dark);
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06);
        }

        .btn-google:hover {
            background: #f8fafc;
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.12);
            border-color: #cbd5e1;
        }

        .btn-google:active {
            transform: translateY(0);
        }

        .btn-google svg {
            flex-shrink: 0;
        }

        /* Footer */
        .footer-text {
            text-align: center;
            margin-top: 36px;
            padding-top: 24px;
            border-top: 1px solid #f1f5f9;
            font-size: 12px;
            color: var(--text-gray);
            font-weight: 500;
            line-height: 1.6;
        }

        .footer-text .version {
            color: #94a3b8;
            font-size: 11px;
        }

        /* Responsive */
        @media (max-width: 860px) {
            .login-container {
                flex-direction: column;
                max-width: 440px;
                min-height: auto;
            }
            .login-left {
                display: none;
            }
            .login-right {
                padding: 40px 28px;
            }
            .logo-top img {
                height: 48px;
                max-width: 160px;
            }
            .header-text h1 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>

<div class="login-container">

    <!-- ============================== -->
    <!-- KIRI: Ilustrasi (tanpa logo)   -->
    <!-- ============================== -->
    <div class="login-left">
        <div class="illustration">
            <img 
                src="https://lju.co.id/dashboard/loginbg.png" 
                alt="LJU Document System"
                class="illustration-img"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
            >
            <svg class="illustration-fallback" style="display:none;" viewBox="0 0 400 340" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="60" y="40" width="120" height="160" rx="12" fill="#e2e8f0"/>
                <rect x="80" y="60" width="80" height="8" rx="4" fill="#cbd5e1"/>
                <rect x="80" y="80" width="60" height="8" rx="4" fill="#cbd5e1"/>
                <rect x="80" y="100" width="70" height="8" rx="4" fill="#cbd5e1"/>
                <circle cx="100" cy="140" r="18" fill="#94a3b8"/>
                <rect x="160" y="70" width="140" height="180" rx="12" fill="#e2e8f0"/>
                <rect x="180" y="95" width="100" height="8" rx="4" fill="#cbd5e1"/>
                <rect x="180" y="115" width="80" height="8" rx="4" fill="#cbd5e1"/>
                <rect x="180" y="135" width="90" height="8" rx="4" fill="#cbd5e1"/>
                <circle cx="210" cy="185" r="20" fill="#94a3b8"/>
                <circle cx="280" cy="160" r="50" stroke="#14b8a6" stroke-width="8" fill="none" opacity="0.5"/>
                <line x1="315" y1="195" x2="350" y2="230" stroke="#14b8a6" stroke-width="8" stroke-linecap="round" opacity="0.5"/>
            </svg>
        </div>
    </div>

    <!-- ============================== -->
    <!-- KANAN: Logo + Form             -->
    <!-- ============================== -->
    <div class="login-right">

        <!-- LOGO di atas header text -->
        <div class="logo-top">
            <img 
                src="https://dms.lju.co.id/assets/img/lju_logo.png" 
                alt="LJU Logo"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
            >
            <div class="logo-fallback">
                LJU <span>Portal</span>
            </div>
        </div>

        <!-- Header Text -->
        <div class="header-text">
            <h1>LJU-SSO Portal</h1>
            <p>
                Single Sign-On System
                <span class="company">PT. Lampung Jasa Utama</span>
            </p>
        </div>

        <!-- Error Alert -->
        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Form Login -->
        <form method="POST" action="">
            <input type="hidden" name="target_system" value="<?= htmlspecialchars($target_system ?? '') ?>">
            <input type="hidden" name="redirect_after_login" value="<?= htmlspecialchars($redirect_after_login ?? '') ?>">

            <div class="form-group">
                <div class="input-wrapper">
                    <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                        placeholder="Masukkan Username Anda" 
                        required 
                        autofocus
                        autocomplete="username"
                    >
                </div>
            </div>

            <div class="form-group">
                <div class="input-wrapper">
                    <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Masukkan Password Anda" 
                        required
                        autocomplete="current-password"
                    >
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                </svg>
                Masuk ke Sistem
            </button>
        </form>

        <?php if (function_exists('isGoogleLoginEnabled') && isGoogleLoginEnabled()): ?>
        <!-- Pembatas ATAU -->
        <div class="divider-or">
            <span>ATAU</span>
        </div>

        <!-- Tombol Login with Google -->
        <a class="btn-google"
           href="google_auth.php?target_system=<?= htmlspecialchars(urlencode($target_system ?? '')) ?>&redirect_after_login=<?= htmlspecialchars(urlencode($redirect_after_login ?? '')) ?>">
            <svg viewBox="0 0 24 24" width="18" height="18" xmlns="http://www.w3.org/2000/svg">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
            Masuk dengan Google
        </a>
        <?php endif; ?>


        <!-- Footer -->
        <div class="footer-text">
            &copy; <?= date('Y') ?> PT. Lampung Jasa Utama<br>
            <span class="version">LJU SSO Portal</span>
        </div>
    </div>

</div>

</body>
</html>