<?php
/**
 * Loader .env sederhana (tanpa library eksternal)
 * File: config/env.php
 *
 * Cara pakai:
 * 1. Salin .env.example menjadi .env di root proyek, lalu isi kredensial.
 * 2. Loader ini otomatis dipanggil oleh config/google.php dan config/database.php
 *    (atau panggil manual: require_once __DIR__ . '/env.php'; )
 *
 * Format .env yang didukung:
 *   KEY=value
 *   KEY="value with spaces"
 *   KEY='value'
 *   # komentar
 *   baris kosong diabaikan
 *
 * Catatan prioritas nilai:
 *   getenv() / $_ENV / $_SERVER yang sudah diset dari luar (mis. Apache SetEnv,
 *   nginx fastcgi_param, docker env) LEBIH DIPRIORITASKAN daripada nilai dalam .env.
 */

if (!function_exists('loadEnv')) {
    /**
     * Muat file .env ke environment proses.
     *
     * @param string|null $path Path file .env. Default: <root proyek>/.env
     * @return bool true jika file ditemukan & dimuat
     */
    function loadEnv(?string $path = null): bool
    {
        static $loaded = false;

        if ($loaded) {
            return true;
        }

        if ($path === null) {
            // Root proyek = satu level di atas folder config/
            $path = dirname(__DIR__) . '/.env';
        }

        if (!is_file($path) || !is_readable($path)) {
            // Tidak ada file .env — bukan error, aplikasi tetap jalan
            // dengan environment variable dari server atau nilai default.
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // Lewati komentar & baris kosong
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Harus berbentuk KEY=VALUE
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key   = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            if ($key === '') {
                continue;
            }

            // Strip quote tunggal/ganda di awal-akhir nilai
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last  = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            // Ekspansi escape umum di dalam nilai ber-quote ganda
            $value = str_replace(['\n', '\r', '\t', '\"'], ["\n", "\r", "\t", '"'], $value);

            // Jangan menimpa variabel yang sudah diset dari luar
            if (getenv($key) !== false || isset($_ENV[$key]) || isset($_SERVER[$key])) {
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }

        $loaded = true;
        return true;
    }
}

if (!function_exists('env')) {
    /**
     * Ambil nilai environment variable dengan fallback.
     *
     * @param string $key     Nama variabel, mis. 'GOOGLE_CLIENT_ID'
     * @param mixed  $default Nilai default jika tidak ditemukan / kosong
     * @return mixed
     */
    function env(string $key, $default = null)
    {
        // Pastikan .env sudah dimuat
        loadEnv();

        $value = getenv($key);
        if ($value === false || $value === '') {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        // Konversi literal boolean/null umum
        switch (strtolower((string)$value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
        }

        return $value;
    }
}

// Muat otomatis saat file ini di-require
loadEnv();
