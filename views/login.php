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

        

        <!-- Footer -->
        <div class="footer-text">
            &copy; <?= date('Y') ?> PT. Lampung Jasa Utama<br>
            <span class="version">LJU SSO Portal</span>
        </div>
    </div>

</div>

</body>
</html>