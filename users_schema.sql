CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nik` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `position` enum('Direktur Utama','Direktur Operasional','Kepala Bidang','Kepala Divisi','Manager','Sekretaris','Staff','System Administrator','Corporate Secretary','Sekretaris Direksi','Sekretaris Komisaris') DEFAULT NULL,
  `division` enum('Keuangan','Pengadaan','Keamanan','Infokom','SDM','Direksi','IT','Legal','Kesekretariatan','Jasa Konstruksi','Pengangkutan & Pergudangan','Perdagangan','Energi & Migas','Direksi') DEFAULT NULL,
  `department` enum('Direksi','Keuangan & Umum','Bisnis','Sekretariat','Pengawas Internal') DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user','op_arsip') DEFAULT 'user',
  `avatar` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `signature` varchar(255) DEFAULT NULL,
  `rfid` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `webmail_user` varchar(200) NOT NULL,
  `webmail_pass` varchar(16) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- ============================================
-- Dukungan Login with Google (OAuth 2.0)
-- ============================================

-- Index pada kolom email agar pencarian user via email Google cepat
ALTER TABLE `users` ADD INDEX `idx_users_email` (`email`);

-- Tabel log login (dipakai oleh index.php, google_callback.php, AuthController)
CREATE TABLE IF NOT EXISTS `login_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `activity` enum('login_success','login_failed','login_nfc_success','login_google_success','logout','timeout') DEFAULT 'login_success',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_activity` (`user_id`,`activity`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

