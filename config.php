<?php
session_start();

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'sdi';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Koneksi database gagal: ' . htmlspecialchars($e->getMessage()));
}

// ============================================================
// KONFIGURASI KONTAK SDI
// ============================================================
define('WA_SDI', '6281234567890');        // Ganti dengan nomor WhatsApp SDI
define('EMAIL_SDI', 'sdi@um-bjm.ac.id');  // Ganti dengan email SDI
define('TELP_SDI', '0851234567890');      // Ganti dengan telepon SDI

// ============================================================
// FUNGSI REDIRECT BERDASARKAN ROLE
// ============================================================

if (!function_exists('redirectByRole')) {
    function redirectByRole($role_id) {
        switch ($role_id) {
            case 1: // Superuser
                header("Location: admin/dashboard.php");
                break;
            case 2: // SDI
                header("Location: admin/dashboard.php");
                break;
            case 3: // Rektor
                header("Location: admin/rektor_dashboard.php");
                break;
            case 4: // Penilai
                header("Location: admin/penilai_dashboard.php");
                break;
            case 5: // Pelamar
                header("Location: dashboard.php");
                break;
            default:
                header("Location: index.php");
                break;
        }
        exit;
    }
}

// ============================================================
// FUNGSI WHATSAPP & EMAIL
// ============================================================

if (!function_exists('waLink')) {
    function waLink($no_hp, $pesan = '') {
        // Bersihkan nomor HP (hapus spasi, tanda hubung, dll)
        $no_hp = preg_replace('/[^0-9]/', '', $no_hp);
        
        // Jika nomor kosong
        if (empty($no_hp)) return '#';
        
        // Jika nomor dimulai dengan 0, ganti dengan 62 (Indonesia)
        if (substr($no_hp, 0, 1) === '0') {
            $no_hp = '62' . substr($no_hp, 1);
        }
        
        // Jika nomor dimulai dengan 8, tambahkan 62
        if (substr($no_hp, 0, 1) === '8' && strlen($no_hp) < 13) {
            $no_hp = '62' . $no_hp;
        }
        
        // Encode pesan
        $pesan = urlencode($pesan);
        
        return 'https://wa.me/' . $no_hp . '?text=' . $pesan;
    }
}

if (!function_exists('mailLink')) {
    function mailLink($email, $subject = '', $body = '') {
        if (empty($email)) return '#';
        $subject = urlencode($subject);
        $body = urlencode($body);
        return 'mailto:' . $email . '?subject=' . $subject . '&body=' . $body;
    }
}

// ============================================================
// FUNGSI SCHEMA (Dengan pengecekan keberadaan fungsi)
// ============================================================

if (!function_exists('_schemaTableExists')) {
    function _schemaTableExists(PDO $pdo, $table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('_schemaColumnExists')) {
    function _schemaColumnExists(PDO $pdo, $table, $column) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('_schemaAddColumn')) {
    function _schemaAddColumn(PDO $pdo, $table, $column, $definition) {
        try {
            if (_schemaTableExists($pdo, $table) && !_schemaColumnExists($pdo, $table, $column)) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            }
        } catch (Throwable $e) {
            // Jangan bikin 500 kalau user database hosting tidak mengizinkan ALTER otomatis.
        }
    }
}

if (!function_exists('ensureSchemaCompatibility')) {
    function ensureSchemaCompatibility(PDO $pdo) {
        // ============================================================
        // TIDAK ADA PENAMBAHAN FIELD - MENGIKUTI STRUKTUR ERD
        // HANYA INSERT DATA MASTER SAJA
        // ============================================================

        // 1. Pastikan data master status lamaran ada
        if (_schemaTableExists($pdo, 'master_status_lamaran')) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM master_status_lamaran");
            if ((int)$stmt->fetchColumn() == 0) {
                try {
                    $pdo->exec("INSERT INTO master_status_lamaran (nama_status, urutan, keterangan) VALUES 
                        ('Lamaran Dikirim', 1, 'Pelamar telah mengirim lamaran'),
                        ('Seleksi Administrasi SDI', 2, 'Sedang dalam proses seleksi administrasi oleh SDI'),
                        ('Disortir ke Prodi/Unit', 3, 'Lamaran disortir ke prodi/unit terkait'),
                        ('Tes Potensi Akademik', 4, 'Menjalani tes potensi akademik'),
                        ('Tes Psikotes', 5, 'Menjalani tes psikotes'),
                        ('Tes Bahasa Inggris/TOEFL', 6, 'Menjalani tes bahasa Inggris/TOEFL'),
                        ('Tes Keterampilan/Keahlian', 7, 'Menjalani tes keterampilan/keahlian'),
                        ('Wawancara', 8, 'Menjalani wawancara'),
                        ('Rekomendasi Prodi/Unit', 9, 'Menunggu rekomendasi dari prodi/unit'),
                        ('Rekomendasi Dekan', 10, 'Menunggu rekomendasi dari dekan'),
                        ('Rapat SDI/Universitas', 11, 'Menunggu keputusan rapat SDI/universitas'),
                        ('Pengumuman Kelulusan', 12, 'Pengumuman kelulusan'),
                        ('Pembekalan BPH/Pimpinan', 13, 'Menjalani pembekalan BPH/pimpinan'),
                        ('Pengangkatan SK', 14, 'Proses pengangkatan SK'),
                        ('Penempatan Kerja', 15, 'Penempatan kerja'),
                        ('Diterima', 16, 'Diterima'),
                        ('Ditolak', 17, 'Ditolak')");
                } catch (Throwable $e) {}
            }
        }

        // 2. Pastikan data roles ada
        if (_schemaTableExists($pdo, 'roles')) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM roles");
            if ((int)$stmt->fetchColumn() < 5) {
                try {
                    $pdo->exec("INSERT IGNORE INTO roles (nama_role, keterangan) VALUES 
                        ('Superuser', 'Administrator Sistem dengan akses penuh'),
                        ('SDI', 'SDI / BAUK - Mengelola seleksi administrasi'),
                        ('Rektor', 'Rektor - Menyetujui keputusan akhir'),
                        ('Penilai', 'Penilai - Mengelola tes dan penilaian'),
                        ('Pelamar', 'Pelamar - Pendaftar lowongan')");
                } catch (Throwable $e) {}
            }
        }

        // 3. Pastikan data agama ada
        if (_schemaTableExists($pdo, 'master_agama')) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM master_agama");
            if ((int)$stmt->fetchColumn() == 0) {
                try {
                    $pdo->exec("INSERT INTO master_agama (nama_agama) VALUES 
                        ('Islam'), ('Kristen'), ('Katholik'), ('Hindu'), ('Buddha'), ('Konghucu')");
                } catch (Throwable $e) {}
            }
        }

        // 4. Pastikan data pendidikan ada
        if (_schemaTableExists($pdo, 'master_pendidikan')) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM master_pendidikan");
            if ((int)$stmt->fetchColumn() == 0) {
                try {
                    $pdo->exec("INSERT INTO master_pendidikan (jenjang, keterangan) VALUES 
                        ('SMA/SMK', 'Sekolah Menengah Atas'),
                        ('D1', 'Diploma 1'),
                        ('D2', 'Diploma 2'),
                        ('D3', 'Diploma 3'),
                        ('D4', 'Diploma 4'),
                        ('S1', 'Strata 1'),
                        ('S2', 'Strata 2'),
                        ('S3', 'Strata 3')");
                } catch (Throwable $e) {}
            }
        }

        // 5. Pastikan data dokumen master ada
        if (_schemaTableExists($pdo, 'dokumen_master')) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM dokumen_master");
            if ((int)$stmt->fetchColumn() == 0) {
                try {
                    $pdo->exec("INSERT INTO dokumen_master (nama_dokumen, wajib, kategori, urutan) VALUES 
                        ('Surat Lamaran', 1, 'Dosen', 1),
                        ('CV/Biodata', 1, 'Dosen', 2),
                        ('Fotokopi KTP', 1, 'Dosen', 3),
                        ('Pas Foto', 1, 'Dosen', 4),
                        ('Fotokopi Kartu Keluarga', 1, 'Dosen', 5),
                        ('Surat Keterangan Sehat', 1, 'Dosen', 6),
                        ('Fotokopi Ijazah S1', 1, 'Dosen', 7),
                        ('Transkrip S1', 1, 'Dosen', 8),
                        ('Fotokopi Ijazah S2', 1, 'Dosen', 9),
                        ('Transkrip S2', 1, 'Dosen', 10),
                        ('Fotokopi Ijazah S3', 0, 'Dosen', 11),
                        ('Transkrip S3', 0, 'Dosen', 12),
                        ('SK Penyetaraan Ijazah LN', 0, 'Dosen', 13),
                        ('Sertifikat Pendukung', 0, 'Dosen', 14),
                        ('Dokumen Pendukung Lain', 0, 'Dosen', 15),
                        ('Surat Lamaran', 1, 'Tendik', 1),
                        ('CV/Biodata', 1, 'Tendik', 2),
                        ('Fotokopi KTP', 1, 'Tendik', 3),
                        ('Pas Foto', 1, 'Tendik', 4),
                        ('Fotokopi Kartu Keluarga', 1, 'Tendik', 5),
                        ('Surat Keterangan Sehat', 1, 'Tendik', 6),
                        ('Fotokopi Ijazah Terakhir', 1, 'Tendik', 7),
                        ('Transkrip Nilai Terakhir', 1, 'Tendik', 8),
                        ('Sertifikat Pendukung', 0, 'Tendik', 9),
                        ('Dokumen Pendukung Lain', 0, 'Tendik', 10)");
                } catch (Throwable $e) {}
            }
        }

        // 6. Pastikan data jenis tes ada
        if (_schemaTableExists($pdo, 'jenis_tes')) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM jenis_tes");
            if ((int)$stmt->fetchColumn() == 0) {
                try {
                    $pdo->exec("INSERT INTO jenis_tes (nama_tes, deskripsi, urutan) VALUES 
                        ('Tes Potensi Akademik', 'Tes kemampuan akademik dasar', 1),
                        ('Tes Psikotes', 'Tes psikologi dan kepribadian', 2),
                        ('Tes Bahasa Inggris/TOEFL', 'Tes kemampuan bahasa Inggris', 3),
                        ('Tes Keterampilan/Keahlian', 'Tes keterampilan teknis', 4),
                        ('Wawancara', 'Wawancara dengan tim seleksi', 5)");
                } catch (Throwable $e) {}
            }
        }
    }
}

// Jalankan schema compatibility
try { 
    ensureSchemaCompatibility($pdo); 
} catch (Throwable $e) { 
    // Schema compatibility tidak boleh menyebabkan 500
}

// ============================================================
// FUNGSI-FUNGSI HELPERS (Semua dibungkus dengan function_exists)
// ============================================================

if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

// ============================================================
// FUNGSI UNTUK AKSES DATA
// ============================================================

if (!function_exists('getPelamarWithDetails')) {
    function getPelamarWithDetails(PDO $pdo, $pelamarId) {
        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                u.nama_lengkap,
                u.email,
                u.no_hp,
                u.alamat,
                u.nik,
                u.id_role,
                r.nama_role as role_name,
                a.nama_agama as agama,
                mp.jenjang as pendidikan_terakhir_nama,
                l.id_lowongan,
                l.nama_lowongan,
                lm.id_lamaran,
                lm.tanggal_lamaran,
                lm.id_status_lamaran,
                ms.nama_status as status_lamaran,
                lm.catatan
            FROM pelamar p
            LEFT JOIN users u ON p.id_user = u.id_user
            LEFT JOIN roles r ON u.id_role = r.id_role
            LEFT JOIN master_agama a ON p.id_agama = a.id_agama
            LEFT JOIN master_pendidikan mp ON p.pendidikan_terakhir = mp.id_pendidikan
            LEFT JOIN lamaran lm ON p.id_pelamar = lm.id_pelamar
            LEFT JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
            LEFT JOIN master_status_lamaran ms ON lm.id_status_lamaran = ms.id_master_status_lamaran
            WHERE p.id_pelamar = ?
        ");
        $stmt->execute([$pelamarId]);
        return $stmt->fetch();
    }
}

if (!function_exists('getAllPelamarWithDetails')) {
    function getAllPelamarWithDetails(PDO $pdo, $filters = []) {
        $sql = "
            SELECT 
                p.*,
                u.nama_lengkap,
                u.email,
                u.no_hp,
                u.alamat,
                u.nik,
                u.id_role,
                r.nama_role as role_name,
                a.nama_agama as agama,
                mp.jenjang as pendidikan_terakhir_nama,
                l.id_lowongan,
                l.nama_lowongan,
                lm.id_lamaran,
                lm.tanggal_lamaran,
                lm.id_status_lamaran,
                ms.nama_status as status_lamaran,
                lm.catatan
            FROM pelamar p
            LEFT JOIN users u ON p.id_user = u.id_user
            LEFT JOIN roles r ON u.id_role = r.id_role
            LEFT JOIN master_agama a ON p.id_agama = a.id_agama
            LEFT JOIN master_pendidikan mp ON p.pendidikan_terakhir = mp.id_pendidikan
            LEFT JOIN lamaran lm ON p.id_pelamar = lm.id_pelamar
            LEFT JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
            LEFT JOIN master_status_lamaran ms ON lm.id_status_lamaran = ms.id_master_status_lamaran
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($filters['status_lamaran'])) {
            $sql .= " AND lm.id_status_lamaran = ?";
            $params[] = $filters['status_lamaran'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (u.nama_lengkap LIKE ? OR u.email LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY lm.tanggal_lamaran DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

if (!function_exists('getLowongan')) {
    function getLowongan(PDO $pdo, $lowonganId) {
        $stmt = $pdo->prepare("SELECT * FROM lowongan WHERE id_lowongan = ?");
        $stmt->execute([$lowonganId]);
        return $stmt->fetch();
    }
}

if (!function_exists('getLowonganAktif')) {
    function getLowonganAktif(PDO $pdo) {
        $stmt = $pdo->query("SELECT * FROM lowongan WHERE status = 'Aktif' ORDER BY nama_lowongan");
        return $stmt->fetchAll();
    }
}

if (!function_exists('getAllLowongan')) {
    function getAllLowongan(PDO $pdo) {
        $stmt = $pdo->query("SELECT * FROM lowongan ORDER BY nama_lowongan");
        return $stmt->fetchAll();
    }
}

if (!function_exists('updateLamaranStatus')) {
    function updateLamaranStatus(PDO $pdo, $lamaranId, $statusId, $catatan = null) {
        $stmt = $pdo->prepare("
            UPDATE lamaran 
            SET id_status_lamaran = ?, catatan = ? 
            WHERE id_lamaran = ?
        ");
        return $stmt->execute([$statusId, $catatan, $lamaranId]);
    }
}

if (!function_exists('getStatusLamaran')) {
    function getStatusLamaran(PDO $pdo, $statusId) {
        $stmt = $pdo->prepare("SELECT * FROM master_status_lamaran WHERE id_master_status_lamaran = ?");
        $stmt->execute([$statusId]);
        return $stmt->fetch();
    }
}

if (!function_exists('getAllStatusLamaran')) {
    function getAllStatusLamaran(PDO $pdo) {
        $stmt = $pdo->query("SELECT * FROM master_status_lamaran ORDER BY urutan");
        return $stmt->fetchAll();
    }
}

if (!function_exists('isPelamarSudahLamaran')) {
    function isPelamarSudahLamaran(PDO $pdo, $pelamarId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lamaran WHERE id_pelamar = ?");
        $stmt->execute([$pelamarId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('getUserRole')) {
    function getUserRole(PDO $pdo, $userId) {
        $stmt = $pdo->prepare("
            SELECT r.nama_role 
            FROM users u 
            JOIN roles r ON u.id_role = r.id_role 
            WHERE u.id_user = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result ? $result['nama_role'] : null;
    }
}

if (!function_exists('hasRole')) {
    function hasRole(PDO $pdo, $userId, $roleName) {
        $userRole = getUserRole($pdo, $userId);
        return strtolower($userRole) === strtolower($roleName);
    }
}

if (!function_exists('getLamaranByPelamarId')) {
    function getLamaranByPelamarId(PDO $pdo, $pelamarId) {
        $stmt = $pdo->prepare("
            SELECT lm.*, l.nama_lowongan, ms.nama_status
            FROM lamaran lm
            LEFT JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
            LEFT JOIN master_status_lamaran ms ON lm.id_status_lamaran = ms.id_master_status_lamaran
            WHERE lm.id_pelamar = ?
        ");
        $stmt->execute([$pelamarId]);
        return $stmt->fetch();
    }
}

if (!function_exists('createLamaran')) {
    function createLamaran(PDO $pdo, $pelamarId, $lowonganId, $statusId = 1) {
        $stmt = $pdo->prepare("SELECT id_lamaran FROM lamaran WHERE id_pelamar = ?");
        $stmt->execute([$pelamarId]);
        if ($stmt->fetch()) {
            return [false, 'Pelamar sudah memiliki lamaran'];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO lamaran (id_pelamar, id_lowongan, tanggal_lamaran, id_status_lamaran, catatan) 
            VALUES (?, ?, NOW(), ?, 'Lamaran baru dikirim')
        ");
        $result = $stmt->execute([$pelamarId, $lowonganId, $statusId]);
        
        if ($result) {
            return [true, 'Lamaran berhasil dibuat'];
        }
        return [false, 'Gagal membuat lamaran'];
    }
}

if (!function_exists('getNilaiTes')) {
    function getNilaiTes(PDO $pdo, $lamaranId) {
        $stmt = $pdo->prepare("
            SELECT nt.*, jt.nama_tes, u.nama_lengkap as penilai_nama
            FROM nilai_tes nt
            JOIN jenis_tes jt ON nt.id_tes = jt.id_tes
            LEFT JOIN penilai p ON nt.id_penilai = p.id_penilai
            LEFT JOIN users u ON p.id_user = u.id_user
            WHERE nt.id_lamaran = ?
            ORDER BY jt.urutan
        ");
        $stmt->execute([$lamaranId]);
        return $stmt->fetchAll();
    }
}

if (!function_exists('saveNilaiTes')) {
    function saveNilaiTes(PDO $pdo, $lamaranId, $tesId, $penilaiId, $nilai, $catatan = null) {
        $stmt = $pdo->prepare("
            INSERT INTO nilai_tes (id_lamaran, id_tes, id_penilai, nilai, catatan, tanggal_input) 
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                nilai = VALUES(nilai),
                catatan = VALUES(catatan),
                tanggal_input = NOW()
        ");
        return $stmt->execute([$lamaranId, $tesId, $penilaiId, $nilai, $catatan]);
    }
}

if (!function_exists('auditLog')) {
    function auditLog(PDO $pdo, $aksi) {
        try {
            $userId = !empty($_SESSION['id_user']) ? (int)$_SESSION['id_user'] : null;
            
            if ($userId) {
                $stmt = $pdo->prepare("
                    INSERT INTO log_aktivitas (id_user, aktivitas, ip_address, waktu) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$userId, $aksi, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
            }
        } catch (Throwable $e) {
            // Audit tidak boleh menghentikan proses utama.
        }
    }
}

// ============================================================
// FUNGSI UNTUK SK PENGANGKATAN
// ============================================================

if (!function_exists('getSkByLamaran')) {
    function getSkByLamaran(PDO $pdo, $lamaranId) {
        $stmt = $pdo->prepare("SELECT * FROM sk_pengangkatan WHERE id_lamaran = ?");
        $stmt->execute([$lamaranId]);
        return $stmt->fetch();
    }
}

if (!function_exists('getAllSk')) {
    function getAllSk(PDO $pdo) {
        $stmt = $pdo->query("
            SELECT 
                sk.*,
                lm.id_lamaran,
                u.nama_lengkap,
                u.email,
                l.nama_lowongan
            FROM sk_pengangkatan sk
            JOIN lamaran lm ON sk.id_lamaran = lm.id_lamaran
            JOIN pelamar p ON lm.id_pelamar = p.id_pelamar
            JOIN users u ON p.id_user = u.id_user
            LEFT JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
            ORDER BY sk.tanggal_sk DESC
        ");
        return $stmt->fetchAll();
    }
}

if (!function_exists('getSkByPelamarId')) {
    function getSkByPelamarId(PDO $pdo, $pelamarId) {
        $stmt = $pdo->prepare("
            SELECT sk.*, l.nama_lowongan
            FROM sk_pengangkatan sk
            JOIN lamaran lm ON sk.id_lamaran = lm.id_lamaran
            LEFT JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
            WHERE lm.id_pelamar = ?
        ");
        $stmt->execute([$pelamarId]);
        return $stmt->fetch();
    }
}

if (!function_exists('createSk')) {
    function createSk(PDO $pdo, $id_lamaran, $nomor_sk, $tanggal_sk, $jenis_sk, $file_sk) {
        $stmt = $pdo->prepare("
            INSERT INTO sk_pengangkatan (id_lamaran, nomor_sk, tanggal_sk, jenis_sk, file_sk) 
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$id_lamaran, $nomor_sk, $tanggal_sk, $jenis_sk, $file_sk]);
    }
}

if (!function_exists('updateSk')) {
    function updateSk(PDO $pdo, $id_sk, $nomor_sk, $tanggal_sk, $jenis_sk, $file_sk = null) {
        if ($file_sk) {
            $stmt = $pdo->prepare("
                UPDATE sk_pengangkatan 
                SET nomor_sk = ?, tanggal_sk = ?, jenis_sk = ?, file_sk = ? 
                WHERE id_sk = ?
            ");
            return $stmt->execute([$nomor_sk, $tanggal_sk, $jenis_sk, $file_sk, $id_sk]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE sk_pengangkatan 
                SET nomor_sk = ?, tanggal_sk = ?, jenis_sk = ? 
                WHERE id_sk = ?
            ");
            return $stmt->execute([$nomor_sk, $tanggal_sk, $jenis_sk, $id_sk]);
        }
    }
}

if (!function_exists('deleteSk')) {
    function deleteSk(PDO $pdo, $id_sk) {
        $stmt = $pdo->prepare("DELETE FROM sk_pengangkatan WHERE id_sk = ?");
        return $stmt->execute([$id_sk]);
    }
}

// ============================================================
// FUNGSI UNTUK DOKUMEN
// ============================================================

if (!function_exists('getPelamarDocuments')) {
    function getPelamarDocuments(PDO $pdo, $pelamarId) {
        $stmt = $pdo->prepare("
            SELECT pd.*, dm.nama_dokumen, dm.kategori, dm.wajib
            FROM pelamar_dokumen pd
            JOIN dokumen_master dm ON pd.id_dokumen = dm.id_dokumen
            WHERE pd.id_pelamar = ?
            ORDER BY dm.urutan
        ");
        $stmt->execute([$pelamarId]);
        return $stmt->fetchAll();
    }
}

if (!function_exists('savePelamarDocument')) {
    function savePelamarDocument(PDO $pdo, $pelamarId, $dokumenId, $fileName, $filePath) {
        $stmt = $pdo->prepare("
            INSERT INTO pelamar_dokumen (id_pelamar, id_dokumen, nama_file, lokasi_file, status_verifikasi) 
            VALUES (?, ?, ?, ?, 'Belum')
            ON DUPLICATE KEY UPDATE 
                nama_file = VALUES(nama_file),
                lokasi_file = VALUES(lokasi_file)
        ");
        return $stmt->execute([$pelamarId, $dokumenId, $fileName, $filePath]);
    }
}

if (!function_exists('verifyDocument')) {
    function verifyDocument(PDO $pdo, $pelamarDokumenId, $status, $catatan = null) {
        $allowed = ['Belum', 'Ya', 'Tidak'];
        if (!in_array($status, $allowed, true)) $status = 'Belum';
        
        $stmt = $pdo->prepare("
            UPDATE pelamar_dokumen 
            SET status_verifikasi = ?, catatan = ? 
            WHERE id_pelamar_dokumen = ?
        ");
        return $stmt->execute([$status, $catatan, $pelamarDokumenId]);
    }
}

if (!function_exists('documentStatus')) {
    function documentStatus(PDO $pdo, $pelamarId, $dokumenId) {
        try {
            $stmt = $pdo->prepare("
                SELECT status_verifikasi as status, catatan 
                FROM pelamar_dokumen 
                WHERE id_pelamar = ? AND id_dokumen = ?
            ");
            $stmt->execute([(int)$pelamarId, (int)$dokumenId]);
            $result = $stmt->fetch();
            if ($result) {
                return ['status' => $result['status'], 'catatan' => $result['catatan']];
            }
            return ['status' => 'Belum', 'catatan' => null];
        } catch (Throwable $e) {
            return ['status' => 'Belum', 'catatan' => null];
        }
    }
}

if (!function_exists('fileUrl')) {
    function fileUrl($path) {
        $path = ltrim((string)$path, '/');
        // Jika path sudah mengandung uploads, langsung gunakan
        if (strpos($path, 'uploads/') === 0) {
            return '../' . $path;
        }
        return '../uploads/' . str_replace('%2F', '/', rawurlencode($path));
    }
}

if (!function_exists('isImageUpload')) {
    function isImageUpload($path) {
        return (bool)preg_match('/\.(jpg|jpeg|png)$/i', (string)$path);
    }
}

// ============================================================
// FUNGSI UNTUK FILE
// ============================================================

if (!function_exists('fileExists')) {
    function fileExists($path) {
        $full_path = __DIR__ . '/../' . ltrim($path, '/');
        return file_exists($full_path);
    }
}

if (!function_exists('getFileUrl')) {
    function getFileUrl($path) {
        $path = ltrim((string)$path, '/');
        if (strpos($path, 'uploads/') === 0) {
            return '/' . $path;
        }
        return '/' . $path;
    }
}

if (!function_exists('getFileSize')) {
    function getFileSize($path) {
        $full_path = __DIR__ . '/../' . ltrim($path, '/');
        if (file_exists($full_path)) {
            $size = filesize($full_path);
            if ($size >= 1048576) {
                return number_format($size / 1048576, 2) . ' MB';
            } elseif ($size >= 1024) {
                return number_format($size / 1024, 2) . ' KB';
            }
            return $size . ' bytes';
        }
        return '-';
    }
}

// ============================================================
// FUNGSI UNTUK CEK ROLE SAAT LOGIN
// ============================================================

if (!function_exists('checkUserRole')) {
    function checkUserRole($pdo, $userId) {
        $stmt = $pdo->prepare("SELECT id_role FROM users WHERE id_user = ? AND status_aktif = 1");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result ? (int)$result['id_role'] : 0;
    }
}

?>