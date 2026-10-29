<?php
/**
 * Handle Edit Profile User
 * File: dashboard/update_profile.php
 *
 * Dipanggil oleh form edit profil pada views/dashboard.php
 * Upload:
 *   /home/ljucoid/dms.lju.co.id/uploads/avatars
 *   /home/ljucoid/dms.lju.co.id/uploads/photos
 *   /home/ljucoid/dms.lju.co.id/uploads/signatures
 */

declare(strict_types=1);

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';

/* =========================================================
 * KONFIGURASI
 * ========================================================= */
const UPLOAD_BASE = '/home/ljucoid/dms.lju.co.id/uploads';

const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5 MB
$USER_ID_COLUMN = 'id';
const ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];

/*
 * Sesuaikan hanya jika struktur tabel user Anda berbeda.
 * Sesuai config/session.php, user diidentifikasi dengan kolom users.id
 * dan dikembalikan oleh getSessionUser() sebagai key 'id'.
 */
const USER_TABLE = 'users';
const USER_ID_COLUMN = 'id';


/* =========================================================
 * HELPER
 * ========================================================= */

function redirectBack(string $type, string $message): never
{
    // Kembali ke halaman sebelumnya atau dashboard.
    $back = $_SERVER['HTTP_REFERER'] ?? '/dashboard/';

    // Jangan redirect ke URL eksternal yang tidak terkait domain aplikasi.
    $parsed = parse_url($back);
    if (
        isset($parsed['host']) &&
        isset($_SERVER['HTTP_HOST']) &&
        strcasecmp($parsed['host'], $_SERVER['HTTP_HOST']) !== 0
    ) {
        $back = '/dashboard/';
    }

    // Simpan pesan flash jika fungsi session aplikasi Anda menyediakannya.
    // Fallback menggunakan session biasa.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    $_SESSION['profile_message'] = [
        'type' => $type,
        'message' => $message,
    ];

    header('Location: ' . $back);
    exit;
}

function getPdo(): PDO
{
    if (!function_exists('getDBConnection')) {
        throw new RuntimeException(
            'Fungsi getDBConnection() tidak ditemukan pada config/database.php.'
        );
    }

    $connection = getDBConnection();

    if (!$connection instanceof PDO) {
        throw new RuntimeException(
            'getDBConnection() tidak mengembalikan koneksi PDO yang valid.'
        );
    }

    return $connection;
}

function getLoggedInUser(): array
{
    if (function_exists('getSessionUser')) {
        $user = getSessionUser();

        if (is_array($user) && !empty($user['id'])) {
            return $user;
        }
    }

    // Fallback menggunakan session user_id. Nilainya adalah users.id.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    $userId = $_SESSION['user_id'] ?? null;

    if ($userId !== null && $userId !== '') {
        return [
            'id' => $userId,
        ];
    }

    throw new RuntimeException('Sesi pengguna tidak valid atau sudah berakhir.');
}

function cleanText(?string $value): string
{
    return trim((string)$value);
}

function validateImageUpload(array $file, string $label): ?array
{
    // UPLOAD_ERR_NO_FILE = tidak ada file baru, bukan error.
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            "Upload {$label} gagal. Kode upload: " . (int)$file['error']
        );
    }

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException("File {$label} tidak valid.");
    }

    if ((int)$file['size'] > MAX_IMAGE_SIZE) {
        throw new RuntimeException(
            "Ukuran {$label} terlalu besar. Maksimal 5 MB."
        );
    }

    // Deteksi MIME dari isi file, bukan dari ekstensi/nama file.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!isset(ALLOWED_MIME[$mime])) {
        throw new RuntimeException(
            "Format {$label} tidak diperbolehkan. Gunakan JPG, PNG, WEBP, atau GIF."
        );
    }

    // Pastikan benar-benar gambar.
    if (@getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException("File {$label} bukan gambar yang valid.");
    }

    return [
        'tmp_name' => $file['tmp_name'],
        'size'     => (int)$file['size'],
        'mime'     => $mime,
        'extension'=> ALLOWED_MIME[$mime],
    ];
}

function ensureUploadDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException(
                "Folder upload tidak dapat dibuat: {$directory}"
            );
        }
    }

    if (!is_writable($directory)) {
        throw new RuntimeException(
            "Folder upload tidak memiliki permission write: {$directory}"
        );
    }
}

function generateFileName(int|string $userId, string $type, string $extension): string
{
    // Nama tidak memakai nama asli user sehingga lebih aman dan konsisten.
    return sprintf(
        '%s_%s_%s.%s',
        $type,
        preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$userId),
        bin2hex(random_bytes(8)),
        $extension
    );
}

function deleteOldFile(?string $oldFile, string $directory): void
{
    if (empty($oldFile)) {
        return;
    }

    /*
     * Hanya nama file yang boleh tersimpan di database.
     * basename() mencegah path traversal.
     */
    $oldFile = basename($oldFile);
    $path = $directory . DIRECTORY_SEPARATOR . $oldFile;

    if (is_file($path)) {
        @unlink($path);
    }
}

function saveUploadedImage(
    array $upload,
    string $type,
    int|string $userId,
    string $directory
): string {
    ensureUploadDirectory($directory);

    $fileName = generateFileName($userId, $type, $upload['extension']);
    $destination = $directory . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($upload['tmp_name'], $destination)) {
        throw new RuntimeException(
            "File {$type} gagal dipindahkan ke folder upload."
        );
    }

    // Permission file agar dapat dibaca web server.
    @chmod($destination, 0644);

    return $fileName;
}

function isSafeIdentifier(string $value): bool
{
    return (bool)preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value);
}


/* =========================================================
 * PROSES UTAMA
 * ========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('error', 'Metode request tidak diperbolehkan.');
}

try {
    $currentUser = getLoggedInUser();
    $userId = $currentUser['id'];

    $pdo = getPdo();

    if (!isSafeIdentifier(USER_TABLE) || !isSafeIdentifier(USER_ID_COLUMN)) {
        throw new RuntimeException('Konfigurasi nama tabel/kolom tidak valid.');
    }

    /*
     * Field yang memang boleh diedit oleh user.
     *
     * Role, department, division, position, status, dll. sengaja
     * tidak diproses agar user tidak dapat menaikkan hak aksesnya
     * melalui form edit profil.
     */
    $name     = cleanText($_POST['name'] ?? '');
    $username = cleanText($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $nik = cleanText($_POST['nik'] ?? '');
    // Ubah string kosong menjadi null agar tidak terkena duplicate entry UNIQUE constraint
    $nik = ($nik !== '') ? $nik : null;
    $email    = cleanText($_POST['email'] ?? '');

    if ($name === '') {
        throw new RuntimeException('Nama lengkap wajib diisi.');
    }

    if ($username === '') {
        throw new RuntimeException('Username wajib diisi.');
    }

    if (mb_strlen($username) > 100) {
        throw new RuntimeException('Username terlalu panjang.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Format email tidak valid.');
    }

    /*
     * Cek username/email yang digunakan user lain.
     *
     * Bagian email hanya dijalankan jika kolom email memang tersedia.
     * Jika database Anda tidak memiliki kolom email, hapus blok email
     * ini atau sesuaikan dengan nama kolom yang digunakan.
     */
    $stmt = $pdo->prepare(
        "SELECT {$USER_ID_COLUMN}
         FROM " . USER_TABLE . "
         WHERE username = :username
           AND {$USER_ID_COLUMN} <> :user_id
         LIMIT 1"
    );
    $stmt->execute([
        ':username' => $username,
        ':user_id'  => $userId,
    ]);

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException('Username tersebut sudah digunakan.');
    }

    if ($email !== '') {
        $stmt = $pdo->prepare(
            "SELECT {$USER_ID_COLUMN}
             FROM " . USER_TABLE . "
             WHERE email = :email
               AND {$USER_ID_COLUMN} <> :user_id
             LIMIT 1"
        );
        $stmt->execute([
            ':email'   => $email,
            ':user_id' => $userId,
        ]);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Email tersebut sudah digunakan.');
        }
    }

    /*
     * Validasi semua file terlebih dahulu sebelum database diubah.
     */
    $avatarUpload = validateImageUpload($_FILES['avatar'] ?? [], 'avatar');
    $photoUpload = validateImageUpload($_FILES['photo'] ?? [], 'foto');
    $signatureUpload = validateImageUpload($_FILES['signature'] ?? [], 'tanda tangan');

    /*
     * Ambil nama file lama untuk kebutuhan cleanup.
     */
    $stmt = $pdo->prepare(
        "SELECT avatar, photo, signature
         FROM " . USER_TABLE . "
         WHERE " . USER_ID_COLUMN . " = :user_id
         LIMIT 1"
    );
    $stmt->execute([':user_id' => $userId]);

    $oldUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$oldUser) {
        throw new RuntimeException('Data user tidak ditemukan.');
    }

    /*
     * Upload file baru.
     * File lama belum dihapus sampai database berhasil diperbarui.
     */
    $newAvatar = null;
    $newPhoto = null;
    $newSignature = null;

    $movedFiles = [];

    if ($avatarUpload !== null) {
        $newAvatar = saveUploadedImage(
            $avatarUpload,
            'avatar',
            $userId,
            UPLOAD_BASE . '/avatars'
        );
        $movedFiles[] = UPLOAD_BASE . '/avatars/' . $newAvatar;
    }

    if ($photoUpload !== null) {
        $newPhoto = saveUploadedImage(
            $photoUpload,
            'photo',
            $userId,
            UPLOAD_BASE . '/photos'
        );
        $movedFiles[] = UPLOAD_BASE . '/photos/' . $newPhoto;
    }

    if ($signatureUpload !== null) {
        $newSignature = saveUploadedImage(
            $signatureUpload,
            'signature',
            $userId,
            UPLOAD_BASE . '/signatures'
        );
        $movedFiles[] = UPLOAD_BASE . '/signatures/' . $newSignature;
    }

    /*
     * Susun UPDATE secara dinamis.
     * File hanya diubah jika user memilih file baru.
     */
    $set = [
        'name = :name',
        'username = :username',
        'nik = :nik',
        'email = :email',
    ];

    $params = [
        ':name'     => $name,
        ':username' => $username,
        ':nik'      => $nik,
        ':email'    => $email !== '' ? $email : null,
        ':user_id'  => $userId,
    ];

    if ($password !== '') {
        if (mb_strlen($password) < 8) {
            throw new RuntimeException(
                'Password baru minimal 8 karakter.'
            );
        }

        $set[] = 'password = :password';
        $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    if ($newAvatar !== null) {
        $set[] = 'avatar = :avatar';
        $params[':avatar'] = $newAvatar;
    }

    if ($newPhoto !== null) {
        $set[] = 'photo = :photo';
        $params[':photo'] = $newPhoto;
    }

    if ($newSignature !== null) {
        $set[] = 'signature = :signature';
        $params[':signature'] = $newSignature;
    }

    /*
     * Transaksi database.
     */
    $pdo->beginTransaction();

    $sql = "UPDATE " . USER_TABLE . "
            SET " . implode(', ', $set) . "
            WHERE " . USER_ID_COLUMN . " = :user_id
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() < 1 && empty($movedFiles)) {
        /*
         * rowCount() dapat 0 jika nilai tidak berubah, jadi bukan error.
         */
    }

    $pdo->commit();

    /*
     * Sinkronkan nilai session yang dikelola config/session.php.
     * getSessionUser() tetap akan membaca ulang database pada request berikutnya.
     */
    $_SESSION['user_id']    = $userId;
    $_SESSION['user_nik']   = $nik;
    $_SESSION['username']   = $username;
    $_SESSION['user_name']  = $name;
    $_SESSION['user_email'] = $email;

    if ($newAvatar !== null) {
        $_SESSION['avatar'] = $newAvatar;
    }
    if ($newPhoto !== null) {
        $_SESSION['photo'] = $newPhoto;
    }
    if ($newSignature !== null) {
        $_SESSION['signature'] = $newSignature;
    }

    /*
     * Setelah database berhasil, hapus file lama yang digantikan.
     */
    if ($newAvatar !== null) {
        deleteOldFile(
            $oldUser['avatar'] ?? null,
            UPLOAD_BASE . '/avatars'
        );
    }

    if ($newPhoto !== null) {
        deleteOldFile(
            $oldUser['photo'] ?? null,
            UPLOAD_BASE . '/photos'
        );
    }

    if ($newSignature !== null) {
        deleteOldFile(
            $oldUser['signature'] ?? null,
            UPLOAD_BASE . '/signatures'
        );
    }

    redirectBack(
        'success',
        'Profil berhasil diperbarui.'
    );

} catch (Throwable $e) {

    /*
     * Rollback bila transaksi masih aktif.
     */
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    /*
     * Jika database gagal setelah file baru terlanjur dipindahkan,
     * hapus file baru tersebut agar tidak menjadi orphan file.
     */
    if (!empty($movedFiles) && is_array($movedFiles)) {
        foreach ($movedFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    // Jangan tampilkan detail database/path server kepada user.
    error_log(
        'update_profile.php: ' . $e->getMessage() .
        ' | user_id=' . ($_SESSION['user_id'] ?? 'unknown')
    );

    redirectBack(
        'error',
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Gagal memperbarui profil. Silakan coba lagi.'
    );
}
