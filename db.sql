-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 26, 2026 at 06:23 PM
-- Server version: 8.0.30
-- PHP Version: 8.4.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sdi`
--

-- --------------------------------------------------------

--
-- Table structure for table `clustering`
--

CREATE TABLE `clustering` (
  `id_cluster` int NOT NULL,
  `id_lamaran` int NOT NULL,
  `nilai_admin` decimal(5,2) NOT NULL,
  `nilai_tpa` decimal(5,2) NOT NULL,
  `nilai_psikotes` decimal(5,2) NOT NULL,
  `nilai_toefl` decimal(5,2) NOT NULL,
  `nilai_keterampilan` decimal(5,2) NOT NULL,
  `nilai_wawancancara` decimal(5,2) NOT NULL,
  `cluster` tinyint NOT NULL,
  `rekomendasi` enum('Direkomendasikan','Tidak Direkomendasikan') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dokumen_master`
--

CREATE TABLE `dokumen_master` (
  `id_dokumen` int NOT NULL,
  `nama_dokumen` varchar(150) NOT NULL,
  `wajib` tinyint(1) NOT NULL DEFAULT '1',
  `kategori` enum('Dosen','Tendik') NOT NULL,
  `urutan` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jenis_tes`
--

CREATE TABLE `jenis_tes` (
  `id_tes` int NOT NULL,
  `nama_tes` varchar(150) NOT NULL,
  `deskripsi` text,
  `urutan` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lamaran`
--

CREATE TABLE `lamaran` (
  `id_lamaran` int NOT NULL,
  `id_pelamar` int NOT NULL,
  `id_lowongan` int NOT NULL,
  `tanggal_lamaran` date NOT NULL,
  `id_status_lamaran` int NOT NULL,
  `catatan` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `log_aktivitas`
--

CREATE TABLE `log_aktivitas` (
  `id_log` int NOT NULL,
  `id_user` int NOT NULL,
  `aktivitas` varchar(150) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `waktu` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lowongan`
--

CREATE TABLE `lowongan` (
  `id_lowongan` int NOT NULL,
  `nama_lowongan` varchar(50) NOT NULL,
  `minimal_pendidikan` int NOT NULL,
  `deskripsi` text NOT NULL,
  `status` enum('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `master_agama`
--

CREATE TABLE `master_agama` (
  `id_agama` int NOT NULL,
  `nama_agama` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `master_pendidikan`
--

CREATE TABLE `master_pendidikan` (
  `id_pendidikan` int NOT NULL,
  `jenjang` varchar(50) NOT NULL,
  `keterangan` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `master_status_lamaran`
--

CREATE TABLE `master_status_lamaran` (
  `id_master_status_lamaran` int NOT NULL,
  `nama_status` varchar(100) NOT NULL,
  `urutan` int NOT NULL,
  `keterangan` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nilai_tes`
--

CREATE TABLE `nilai_tes` (
  `id_nilai` int NOT NULL,
  `id_lamaran` int NOT NULL,
  `id_tes` int NOT NULL,
  `id_penilai` int NOT NULL,
  `nilai` decimal(5,2) NOT NULL,
  `catatan` text,
  `tanggal_input` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifikasi`
--

CREATE TABLE `notifikasi` (
  `id_notifikasi` int NOT NULL,
  `id_user` int NOT NULL,
  `judul` varchar(150) NOT NULL,
  `pesan` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `panggilan_email`
--

CREATE TABLE `panggilan_email` (
  `id_email` int NOT NULL,
  `id_lamaran` int NOT NULL,
  `email_tujuan` varchar(100) NOT NULL,
  `subjek` varchar(100) NOT NULL,
  `isi_email` text NOT NULL,
  `tanggal_kirim` datetime NOT NULL,
  `status` enum('Terkirim','Gagal') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `panggilan_wa`
--

CREATE TABLE `panggilan_wa` (
  `id_panggilan` int NOT NULL,
  `id_lamaran` int NOT NULL,
  `tanggal_kirim` datetime NOT NULL,
  `nomor_tujuan` varchar(20) NOT NULL,
  `pesan` text NOT NULL,
  `status` enum('Terkirim','Gagal') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pelamar`
--

CREATE TABLE `pelamar` (
  `id_pelamar` int NOT NULL,
  `id_user` int NOT NULL,
  `tempat_lahir` varchar(100) NOT NULL,
  `tanggal_lahir` date NOT NULL,
  `jenis_kelamin` enum('L','P') NOT NULL,
  `id_agama` int NOT NULL,
  `umur` int NOT NULL,
  `pendidikan_terakhir` int NOT NULL,
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pelamar_dokumen`
--

CREATE TABLE `pelamar_dokumen` (
  `id_pelamar_dokumen` int NOT NULL,
  `id_pelamar` int NOT NULL,
  `id_dokumen` int NOT NULL,
  `nama_file` varchar(150) NOT NULL,
  `lokasi_file` varchar(255) NOT NULL,
  `status_verifikasi` enum('Belum','Ya','Tidak') NOT NULL DEFAULT 'Belum',
  `catatan` text,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `penilai`
--

CREATE TABLE `penilai` (
  `id_penilai` int NOT NULL,
  `id_user` int NOT NULL,
  `id_tes` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id_role` int NOT NULL,
  `nama_role` varchar(50) NOT NULL,
  `keterangan` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sk_pengangkatan`
--

CREATE TABLE `sk_pengangkatan` (
  `id_sk` int NOT NULL,
  `id_lamaran` int NOT NULL,
  `nomor_sk` varchar(100) NOT NULL,
  `tanggal_sk` date NOT NULL,
  `jenis_sk` enum('Dosen','Tendik') NOT NULL,
  `file_sk` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id_user` int NOT NULL,
  `id_role` int NOT NULL,
  `nik` varchar(20) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `no_hp` varchar(100) NOT NULL,
  `alamat` text NOT NULL,
  `status_aktif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `verifikasi_administrasi`
--

CREATE TABLE `verifikasi_administrasi` (
  `id_verifikasi` int NOT NULL,
  `id_lamaran` int NOT NULL,
  `id_user` int NOT NULL,
  `tanggal_verifikasi` date NOT NULL,
  `hasil` enum('Diterima','Ditolak') NOT NULL,
  `catatan` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `verifikasi_ijazah`
--

CREATE TABLE `verifikasi_ijazah` (
  `id_verifikasi_ijazah` int NOT NULL,
  `id_lamaran` int NOT NULL,
  `nomor_ijazah` varchar(100) NOT NULL,
  `status_valid` enum('Valid','Tidak Valid') NOT NULL,
  `link_cek` varchar(255) DEFAULT NULL,
  `catatan` text,
  `tanggal_cek` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `clustering`
--
ALTER TABLE `clustering`
  ADD PRIMARY KEY (`id_cluster`),
  ADD UNIQUE KEY `uq_clustering_lamaran` (`id_lamaran`);

--
-- Indexes for table `dokumen_master`
--
ALTER TABLE `dokumen_master`
  ADD PRIMARY KEY (`id_dokumen`);

--
-- Indexes for table `jenis_tes`
--
ALTER TABLE `jenis_tes`
  ADD PRIMARY KEY (`id_tes`);

--
-- Indexes for table `lamaran`
--
ALTER TABLE `lamaran`
  ADD PRIMARY KEY (`id_lamaran`),
  ADD KEY `fk_lamaran_pelamar` (`id_pelamar`),
  ADD KEY `fk_lamaran_lowongan` (`id_lowongan`),
  ADD KEY `fk_lamaran_status` (`id_status_lamaran`);

--
-- Indexes for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `fk_log_users` (`id_user`);

--
-- Indexes for table `lowongan`
--
ALTER TABLE `lowongan`
  ADD PRIMARY KEY (`id_lowongan`);

--
-- Indexes for table `master_agama`
--
ALTER TABLE `master_agama`
  ADD PRIMARY KEY (`id_agama`);

--
-- Indexes for table `master_pendidikan`
--
ALTER TABLE `master_pendidikan`
  ADD PRIMARY KEY (`id_pendidikan`);

--
-- Indexes for table `master_status_lamaran`
--
ALTER TABLE `master_status_lamaran`
  ADD PRIMARY KEY (`id_master_status_lamaran`);

--
-- Indexes for table `nilai_tes`
--
ALTER TABLE `nilai_tes`
  ADD PRIMARY KEY (`id_nilai`),
  ADD KEY `fk_nilai_lamaran` (`id_lamaran`),
  ADD KEY `fk_nilai_tes` (`id_tes`),
  ADD KEY `fk_nilai_penilai` (`id_penilai`);

--
-- Indexes for table `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id_notifikasi`),
  ADD KEY `fk_notifikasi_users` (`id_user`);

--
-- Indexes for table `panggilan_email`
--
ALTER TABLE `panggilan_email`
  ADD PRIMARY KEY (`id_email`),
  ADD KEY `fk_email_lamaran` (`id_lamaran`);

--
-- Indexes for table `panggilan_wa`
--
ALTER TABLE `panggilan_wa`
  ADD PRIMARY KEY (`id_panggilan`),
  ADD KEY `fk_wa_lamaran` (`id_lamaran`);

--
-- Indexes for table `pelamar`
--
ALTER TABLE `pelamar`
  ADD PRIMARY KEY (`id_pelamar`),
  ADD UNIQUE KEY `uq_pelamar_user` (`id_user`),
  ADD KEY `fk_pelamar_agama` (`id_agama`);

--
-- Indexes for table `pelamar_dokumen`
--
ALTER TABLE `pelamar_dokumen`
  ADD PRIMARY KEY (`id_pelamar_dokumen`),
  ADD KEY `fk_pel_dok_pelamar` (`id_pelamar`),
  ADD KEY `fk_pel_dok_master` (`id_dokumen`);

--
-- Indexes for table `penilai`
--
ALTER TABLE `penilai`
  ADD PRIMARY KEY (`id_penilai`),
  ADD KEY `fk_penilai_users` (`id_user`),
  ADD KEY `fk_penilai_tes` (`id_tes`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_role`);

--
-- Indexes for table `sk_pengangkatan`
--
ALTER TABLE `sk_pengangkatan`
  ADD PRIMARY KEY (`id_sk`),
  ADD UNIQUE KEY `uq_sk_lamaran` (`id_lamaran`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `uq_users_nik` (`nik`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `fk_users_roles` (`id_role`);

--
-- Indexes for table `verifikasi_administrasi`
--
ALTER TABLE `verifikasi_administrasi`
  ADD PRIMARY KEY (`id_verifikasi`),
  ADD KEY `fk_verif_admin_lamaran` (`id_lamaran`),
  ADD KEY `fk_verif_admin_user` (`id_user`);

--
-- Indexes for table `verifikasi_ijazah`
--
ALTER TABLE `verifikasi_ijazah`
  ADD PRIMARY KEY (`id_verifikasi_ijazah`),
  ADD KEY `fk_verif_ijazah_lamaran` (`id_lamaran`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `clustering`
--
ALTER TABLE `clustering`
  MODIFY `id_cluster` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dokumen_master`
--
ALTER TABLE `dokumen_master`
  MODIFY `id_dokumen` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jenis_tes`
--
ALTER TABLE `jenis_tes`
  MODIFY `id_tes` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lamaran`
--
ALTER TABLE `lamaran`
  MODIFY `id_lamaran` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  MODIFY `id_log` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lowongan`
--
ALTER TABLE `lowongan`
  MODIFY `id_lowongan` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `master_agama`
--
ALTER TABLE `master_agama`
  MODIFY `id_agama` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `master_pendidikan`
--
ALTER TABLE `master_pendidikan`
  MODIFY `id_pendidikan` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `master_status_lamaran`
--
ALTER TABLE `master_status_lamaran`
  MODIFY `id_master_status_lamaran` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `nilai_tes`
--
ALTER TABLE `nilai_tes`
  MODIFY `id_nilai` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id_notifikasi` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `panggilan_email`
--
ALTER TABLE `panggilan_email`
  MODIFY `id_email` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `panggilan_wa`
--
ALTER TABLE `panggilan_wa`
  MODIFY `id_panggilan` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pelamar`
--
ALTER TABLE `pelamar`
  MODIFY `id_pelamar` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pelamar_dokumen`
--
ALTER TABLE `pelamar_dokumen`
  MODIFY `id_pelamar_dokumen` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `penilai`
--
ALTER TABLE `penilai`
  MODIFY `id_penilai` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id_role` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sk_pengangkatan`
--
ALTER TABLE `sk_pengangkatan`
  MODIFY `id_sk` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `verifikasi_administrasi`
--
ALTER TABLE `verifikasi_administrasi`
  MODIFY `id_verifikasi` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `verifikasi_ijazah`
--
ALTER TABLE `verifikasi_ijazah`
  MODIFY `id_verifikasi_ijazah` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `clustering`
--
ALTER TABLE `clustering`
  ADD CONSTRAINT `fk_clustering_lamaran` FOREIGN KEY (`id_lamaran`) REFERENCES `lamaran` (`id_lamaran`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `lamaran`
--
ALTER TABLE `lamaran`
  ADD CONSTRAINT `fk_lamaran_lowongan` FOREIGN KEY (`id_lowongan`) REFERENCES `lowongan` (`id_lowongan`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lamaran_pelamar` FOREIGN KEY (`id_pelamar`) REFERENCES `pelamar` (`id_pelamar`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lamaran_status` FOREIGN KEY (`id_status_lamaran`) REFERENCES `master_status_lamaran` (`id_master_status_lamaran`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD CONSTRAINT `fk_log_users` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `nilai_tes`
--
ALTER TABLE `nilai_tes`
  ADD CONSTRAINT `fk_nilai_lamaran` FOREIGN KEY (`id_lamaran`) REFERENCES `lamaran` (`id_lamaran`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_nilai_penilai` FOREIGN KEY (`id_penilai`) REFERENCES `penilai` (`id_penilai`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_nilai_tes` FOREIGN KEY (`id_tes`) REFERENCES `jenis_tes` (`id_tes`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD CONSTRAINT `fk_notifikasi_users` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `panggilan_email`
--
ALTER TABLE `panggilan_email`
  ADD CONSTRAINT `fk_email_lamaran` FOREIGN KEY (`id_lamaran`) REFERENCES `lamaran` (`id_lamaran`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `panggilan_wa`
--
ALTER TABLE `panggilan_wa`
  ADD CONSTRAINT `fk_wa_lamaran` FOREIGN KEY (`id_lamaran`) REFERENCES `lamaran` (`id_lamaran`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pelamar`
--
ALTER TABLE `pelamar`
  ADD CONSTRAINT `fk_pelamar_agama` FOREIGN KEY (`id_agama`) REFERENCES `master_agama` (`id_agama`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pelamar_users` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pelamar_dokumen`
--
ALTER TABLE `pelamar_dokumen`
  ADD CONSTRAINT `fk_pel_dok_master` FOREIGN KEY (`id_dokumen`) REFERENCES `dokumen_master` (`id_dokumen`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pel_dok_pelamar` FOREIGN KEY (`id_pelamar`) REFERENCES `pelamar` (`id_pelamar`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `penilai`
--
ALTER TABLE `penilai`
  ADD CONSTRAINT `fk_penilai_tes` FOREIGN KEY (`id_tes`) REFERENCES `jenis_tes` (`id_tes`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_penilai_users` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sk_pengangkatan`
--
ALTER TABLE `sk_pengangkatan`
  ADD CONSTRAINT `fk_sk_lamaran` FOREIGN KEY (`id_lamaran`) REFERENCES `lamaran` (`id_lamaran`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_roles` FOREIGN KEY (`id_role`) REFERENCES `roles` (`id_role`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `verifikasi_administrasi`
--
ALTER TABLE `verifikasi_administrasi`
  ADD CONSTRAINT `fk_verif_admin_lamaran` FOREIGN KEY (`id_lamaran`) REFERENCES `lamaran` (`id_lamaran`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_verif_admin_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `verifikasi_ijazah`
--
ALTER TABLE `verifikasi_ijazah`
  ADD CONSTRAINT `fk_verif_ijazah_lamaran` FOREIGN KEY (`id_lamaran`) REFERENCES `lamaran` (`id_lamaran`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;