<?php
require_once __DIR__.'/includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// CEK LOGIN
// ============================================================
if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

// Ambil data user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id_user = ? AND status_aktif = 1");
$stmt->execute([$_SESSION['id_user']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Cek role pelamar (role 5)
if ($user['id_role'] != 5) {
    header("Location: dashboard.php");
    exit;
}

// ============================================================
// CEK DAN BUAT DATA PELAMAR OTOMATIS
// ============================================================
$stmt = $pdo->prepare("SELECT * FROM pelamar WHERE id_user = ?");
$stmt->execute([$_SESSION['id_user']]);
$pelamar = $stmt->fetch();

if (!$pelamar) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO pelamar (id_user, tempat_lahir, tanggal_lahir, jenis_kelamin, id_agama, umur, pendidikan_terakhir) 
            VALUES (?, '', '2000-01-01', 'L', 1, 0, 1)
        ");
        $stmt->execute([$_SESSION['id_user']]);
        
        $stmt = $pdo->prepare("
            SELECT p.*, u.nama_lengkap, u.email, u.nik, u.no_hp, u.alamat 
            FROM pelamar p
            JOIN users u ON p.id_user = u.id_user
            WHERE p.id_user = ?
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['id_user']]);
        $pelamar = $stmt->fetch();
    } catch (PDOException $e) {
        $error = 'Gagal membuat data pelamar. Silakan hubungi admin.';
    }
}

// ============================================================
// AMBIL DATA LOWONGAN AKTIF
// ============================================================
$lowongan = $pdo->query("
    SELECT * FROM lowongan WHERE status = 'Aktif' ORDER BY nama_lowongan
")->fetchAll();

// ============================================================
// PROSES SUBMIT FORM
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_lamaran'])) {
    // Validasi dasar
    $lowongan_id = $_POST['lowongan_id'] ?? 0;
    $jenis_lamaran = $_POST['jenis_lamaran'] ?? '';
    $tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
    $tempat_lahir = $_POST['tempat_lahir'] ?? '';
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
    $pendidikan_terakhir = $_POST['pendidikan_terakhir'] ?? '';
    $agama = $_POST['agama'] ?? '';
    $tujuan_spesifik = $_POST['tujuan_spesifik'] ?? '';
    $jurusan = $_POST['jurusan'] ?? '';
    $ipk = $_POST['ipk'] ?? 0;
    $pengalaman_kerja = $_POST['pengalaman_kerja'] ?? 0;
    
    // Hitung umur
    $umur = 0;
    if ($tanggal_lahir) {
        $birth = new DateTime($tanggal_lahir);
        $today = new DateTime();
        $umur = $birth->diff($today)->y;
    }
    
    // Validasi
    if ($umur > 58) {
        $error = 'Maaf, usia Anda ' . $umur . ' tahun. Maksimal usia pendaftar adalah 58 tahun.';
    } elseif ($jenis_lamaran == 'dosen' && !in_array($pendidikan_terakhir, ['S2', 'S3'])) {
        $error = 'Untuk melamar sebagai Dosen, pendidikan minimal S2.';
    } elseif ($jenis_lamaran == 'tendik' && !in_array($pendidikan_terakhir, ['SMA/SMK', 'D1', 'D2', 'D3', 'D4', 'S1'])) {
        $error = 'Untuk Tenaga Kependidikan, pendidikan maksimal S1.';
    } elseif (strtolower($agama) != 'islam') {
        $error = 'Persyaratan: Beragama Islam.';
    } elseif ($_POST['pernyataan_integritas'] != 'Ya') {
        $error = 'Anda harus menyetujui pernyataan integritas dan berakhlak mulia.';
    } elseif ($_POST['bebas_narkotika'] != 'Ya') {
        $error = 'Anda harus menyetujui pernyataan bebas narkotika.';
    } elseif ($_POST['sehat_jasmani'] != 'Ya') {
        $error = 'Anda harus menyetujui pernyataan sehat jasmani dan rohani.';
    } else {
        try {
            // Cek apakah sudah punya lamaran
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM lamaran WHERE id_pelamar = ?");
            $stmt->execute([$pelamar['id_pelamar']]);
            if ((int)$stmt->fetchColumn() > 0) {
                $error = 'Anda sudah memiliki lamaran aktif. Tidak dapat membuat lamaran baru.';
            } else {
                // Mulai transaksi
                $pdo->beginTransaction();
                
                // ============================================================
                // CARI ID AGAMA
                // ============================================================
                $id_agama = null;
                $stmt = $pdo->prepare("SELECT id_agama FROM master_agama WHERE LOWER(nama_agama) = LOWER(?) LIMIT 1");
                $stmt->execute([$agama]);
                $id_agama = $stmt->fetchColumn();
                
                if (!$id_agama) {
                    $stmt = $pdo->prepare("SELECT id_agama FROM master_agama WHERE LOWER(nama_agama) LIKE LOWER(?) LIMIT 1");
                    $stmt->execute(['%' . $agama . '%']);
                    $id_agama = $stmt->fetchColumn();
                }
                
                if (!$id_agama) {
                    $id_agama = 1;
                }
                
                // ============================================================
                // CARI ID PENDIDIKAN
                // ============================================================
                $id_pendidikan = null;
                $stmt = $pdo->prepare("SELECT id_pendidikan FROM master_pendidikan WHERE jenjang = ? LIMIT 1");
                $stmt->execute([$pendidikan_terakhir]);
                $id_pendidikan = $stmt->fetchColumn();
                
                if (!$id_pendidikan) {
                    $id_pendidikan = 6;
                }
                
                // ============================================================
                // UPDATE DATA PELAMAR
                // ============================================================
                $stmt = $pdo->prepare("
                    UPDATE pelamar 
                    SET 
                        tempat_lahir = ?,
                        tanggal_lahir = ?,
                        jenis_kelamin = ?,
                        id_agama = ?,
                        umur = ?,
                        pendidikan_terakhir = ?
                    WHERE id_pelamar = ?
                ");
                
                $stmt->execute([
                    $tempat_lahir,
                    $tanggal_lahir,
                    $jenis_kelamin,
                    $id_agama,
                    $umur,
                    $id_pendidikan,
                    $pelamar['id_pelamar']
                ]);
                
                // ============================================================
                // BUAT CATATAN LENGKAP
                // ============================================================
                $catatan = "Jenis: " . ucfirst($jenis_lamaran);
                $catatan .= " | Tujuan: " . $tujuan_spesifik;
                if (!empty($jurusan)) {
                    $catatan .= " | Jurusan: " . $jurusan;
                }
                if (!empty($ipk) && $ipk > 0) {
                    $catatan .= " | IPK: " . number_format($ipk, 2);
                }
                if (!empty($pengalaman_kerja) && $pengalaman_kerja > 0) {
                    $catatan .= " | Pengalaman: " . $pengalaman_kerja . " tahun";
                }
                
                // ============================================================
                // INSERT LAMARAN
                // ============================================================
                $stmt = $pdo->prepare("
                    INSERT INTO lamaran (
                        id_pelamar, 
                        id_lowongan, 
                        tanggal_lamaran, 
                        id_status_lamaran, 
                        catatan
                    ) VALUES (?, ?, NOW(), 1, ?)
                ");
                
                $stmt->execute([$pelamar['id_pelamar'], $lowongan_id, $catatan]);
                $lamaran_id = $pdo->lastInsertId();
                
                // ============================================================
                // PROSES UPLOAD DOKUMEN
                // ============================================================
                // Pastikan folder upload ada
                $upload_base_dir = __DIR__ . '/uploads/dokumen/';
                if (!is_dir($upload_base_dir)) {
                    mkdir($upload_base_dir, 0777, true);
                }
                
                // Buat folder per pelamar
                $upload_dir = $upload_base_dir . $pelamar['id_pelamar'] . '/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Daftar dokumen yang diupload
                $dokumen_list = [
                    'file_surat_lamaran' => 'Surat Lamaran',
                    'file_cv' => 'CV/Biodata',
                    'file_ktp' => 'Fotokopi KTP',
                    'file_pasfoto' => 'Pas Foto',
                    'file_kk' => 'Fotokopi Kartu Keluarga',
                    'file_surat_sehat' => 'Surat Keterangan Sehat'
                ];
                
                // Tambahan untuk Dosen
                if ($jenis_lamaran == 'dosen') {
                    $dokumen_list['file_ijazah_s1'] = 'Fotokopi Ijazah S1';
                    $dokumen_list['file_transkrip_s1'] = 'Transkrip S1';
                    $dokumen_list['file_ijazah_s2'] = 'Fotokopi Ijazah S2';
                    $dokumen_list['file_transkrip_s2'] = 'Transkrip S2';
                    $dokumen_list['file_ijazah_s3'] = 'Fotokopi Ijazah S3';
                    $dokumen_list['file_transkrip_s3'] = 'Transkrip S3';
                    $dokumen_list['file_sk_penyetaraan'] = 'SK Penyetaraan Ijazah LN';
                }
                
                // Tambahan untuk Tendik
                if ($jenis_lamaran == 'tendik') {
                    $dokumen_list['file_ijazah'] = 'Fotokopi Ijazah Terakhir';
                    $dokumen_list['file_transkrip'] = 'Transkrip Nilai Terakhir';
                }
                
                // Sertifikat pendukung
                $dokumen_list['file_sertifikat1'] = 'Sertifikat Pendukung';
                $dokumen_list['file_sertifikat2'] = 'Sertifikat Pendukung';
                $dokumen_list['file_sertifikat3'] = 'Sertifikat Pendukung';
                
                // Proses upload setiap file
                foreach ($dokumen_list as $field_name => $doc_name) {
                    if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
                        $file = $_FILES[$field_name];
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $doc_name) . '.' . $ext;
                        $file_path = $upload_dir . $file_name;
                        $relative_path = 'uploads/dokumen/' . $pelamar['id_pelamar'] . '/' . $file_name;
                        
                        // Pindahkan file
                        if (move_uploaded_file($file['tmp_name'], $file_path)) {
                            // Cari id_dokumen
                            $stmt = $pdo->prepare("
                                SELECT id_dokumen FROM dokumen_master 
                                WHERE nama_dokumen LIKE ? 
                                LIMIT 1
                            ");
                            $stmt->execute(['%' . $doc_name . '%']);
                            $id_dokumen = $stmt->fetchColumn();
                            
                            if ($id_dokumen) {
                                // Simpan ke database dokumen
                                $stmt = $pdo->prepare("
                                    INSERT INTO pelamar_dokumen (
                                        id_pelamar, 
                                        id_dokumen, 
                                        nama_file, 
                                        lokasi_file, 
                                        status_verifikasi
                                    ) VALUES (?, ?, ?, ?, 'Belum')
                                ");
                                $stmt->execute([
                                    $pelamar['id_pelamar'],
                                    $id_dokumen,
                                    $file_name,
                                    $relative_path
                                ]);
                            }
                        }
                    }
                }
                
                // Commit transaksi
                $pdo->commit();
                
                $success = 'Lamaran berhasil dikirim!';
                header("Location: dashboard.php?success=" . urlencode($success));
                exit;
                
            }
        } catch (PDOException $e) {
            // Rollback jika ada error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Lamaran - Rekrutmen UMB</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .form-lamaran { max-width: 900px; margin: 0 auto; padding: 20px 0; }
        .form-section { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow-sm); }
        .form-section h2 { font-size: 18px; margin: 0 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid var(--accent-soft); color: var(--primary); }
        .section-desc { color: var(--muted); font-size: 14px; margin-bottom: 15px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 8px; }
        .form-group { margin-bottom: 10px; }
        .form-group label { display: block; font-weight: 700; font-size: 14px; margin-bottom: 5px; color: #334155; }
        .form-group .required { color: var(--danger); }
        .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="number"], .form-group input[type="date"], .form-group select, .form-group textarea {
            width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 12px; font-size: 14px; transition: border-color 0.2s; box-sizing: border-box; font-family: inherit; background: #fff;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(15, 39, 71, 0.08);
        }
        .form-group input.readonly, .form-group textarea.readonly {
            background: var(--surface-2); color: var(--muted); cursor: not-allowed;
        }
        .form-group input[type="file"] { padding: 10px; border: 1px dashed var(--border); border-radius: 12px; width: 100%; background: var(--surface-2); font-size: 13px; cursor: pointer; }
        .form-group input[type="file"]:hover { border-color: var(--primary); background: var(--accent-soft); }
        .help-text { display: block; font-size: 12px; color: var(--muted); margin-top: 4px; }
        .checkbox-group { padding: 6px 0; }
        .checkbox-group label { display: flex; align-items: center; gap: 10px; font-weight: 500; cursor: pointer; font-size: 14px; color: var(--text); }
        .checkbox-group input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer; flex-shrink: 0; }
        .form-actions { display: flex; gap: 15px; margin-top: 30px; padding-top: 20px; border-top: 2px solid var(--border); }
        .page-header { max-width: 900px; margin: 0 auto 25px; padding: 0 20px; }
        .page-header h1 { font-size: 32px; margin: 0; color: var(--primary); }
        .page-header .subtitle { color: var(--muted); font-size: 16px; margin: 5px 0 0; }
        .nav-lamaran .brand-title strong { font-size: 16px; }
        .nav-lamaran .brand-title span { font-size: 12px; }
        .jenis-info {
            display: none;
            padding: 12px 16px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 14px;
            font-weight: 500;
        }
        .jenis-info.dosen {
            display: block;
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }
        .jenis-info.tendik {
            display: block;
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        .custom-alert-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .custom-alert-box {
            background: white;
            padding: 30px;
            border-radius: 16px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            animation: slideDown 0.3s ease;
        }
        .custom-alert-box .icon { font-size: 48px; margin-bottom: 10px; }
        .custom-alert-box h3 { margin: 0 0 10px 0; color: #1e293b; }
        .custom-alert-box p { color: #475569; margin-bottom: 20px; line-height: 1.6; }
        .custom-alert-box .btn-close-alert {
            background: #e2e8f0;
            border: none;
            padding: 10px 30px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            color: #1e293b;
            transition: all 0.2s;
        }
        .custom-alert-box .btn-close-alert:hover { background: #cbd5e1; }
        .custom-alert-box .btn-close-alert.primary {
            background: #0f2747;
            color: white;
        }
        .custom-alert-box .btn-close-alert.primary:hover { background: #1a3a5c; }
        @keyframes slideDown {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @media (max-width: 768px) { 
            .form-row { grid-template-columns: 1fr; } 
            .form-lamaran { padding: 10px; } 
            .form-section { padding: 16px; } 
            .form-actions { flex-direction: column; } 
            .form-actions .btn { text-align: center; width: 100%; justify-content: center; } 
            .page-header h1 { font-size: 26px; } 
        }
        @media (max-width: 480px) { 
            .form-section h2 { font-size: 16px; } 
            .form-group label { font-size: 13px; } 
            .form-group input, .form-group select, .form-group textarea { font-size: 13px; padding: 8px 10px; } 
        }
    </style>
</head>
<body>
<div class="page-shell">
    <nav class="public-nav nav-lamaran">
        <div class="nav-inner">
            <a class="brand" href="dashboard.php">
                <div class="brand-logo">🏛️</div>
                <div class="brand-title">
                    <strong>Form Lamaran</strong>
                    <span><?= htmlspecialchars($user['nama_lengkap'] ?? '') ?></span>
                </div>
            </a>
            <div class="nav-links">
                <a href="dashboard.php">📊 Dashboard</a>
                <a href="logout.php" class="btn btn-danger">🚪 Logout</a>
            </div>
        </div>
    </nav>

    <main class="section">
        <div class="container">
            <div class="page-header">
                <h1>📝 Form Lamaran</h1>
                <p class="subtitle">Silakan isi form di bawah ini untuk mengirim lamaran Anda.</p>
            </div>

            <?php if($error): ?>
                <div class="alert alert-error" style="max-width:900px;margin:0 auto 20px;"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="alert alert-success" style="max-width:900px;margin:0 auto 20px;"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="form-lamaran" id="lamaranForm">
                <input type="hidden" name="submit_lamaran" value="1">

                <!-- ============================================================ -->
                <!-- BAGIAN 1: JENIS LAMARAN & LOWONGAN -->
                <!-- ============================================================ -->
                <div class="form-section">
                    <h2>1. Pilih Jenis Lamaran & Lowongan</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Jenis Lamaran <span class="required">*</span></label>
                            <select name="jenis_lamaran" id="jenisLamaran" required>
                                <option value="">-- Pilih Jenis Lamaran --</option>
                                <option value="dosen">Dosen</option>
                                <option value="tendik">Tenaga Kependidikan (Tendik)</option>
                            </select>
                            <small class="help-text">Pilih jenis lamaran yang sesuai.</small>
                        </div>
                        <div class="form-group">
                            <label>Pilih Lowongan <span class="required">*</span></label>
                            <select name="lowongan_id" id="lowongan_id" required>
                                <option value="">-- Pilih Lowongan --</option>
                                <?php foreach($lowongan as $l): ?>
                                    <option value="<?= $l['id_lowongan'] ?>">
                                        <?= htmlspecialchars($l['nama_lowongan']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="help-text">Pilih lowongan yang tersedia.</small>
                        </div>
                    </div>
                    
                    <div id="jenisInfo" class="jenis-info">
                        <span id="jenisInfoText"></span>
                    </div>

                    <div class="form-group">
                        <label>Tujuan Spesifik (Program Studi / Unit Kerja) <span class="required">*</span></label>
                        <input type="text" name="tujuan_spesifik" id="tujuanSpesifik" placeholder="Contoh: Informatika / Administrasi Akademik" required>
                        <small class="help-text" id="tujuanHelp">Isi dengan program studi (untuk Dosen) atau unit kerja (untuk Tendik).</small>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- BAGIAN 2: DATA DIRI (READ ONLY) -->
                <!-- ============================================================ -->
                <div class="form-section">
                    <h2>2. Data Diri</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Lengkap</label>
                            <input type="text" value="<?= htmlspecialchars($user['nama_lengkap'] ?? '') ?>" readonly class="readonly">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly class="readonly">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>NIK</label>
                            <input type="text" value="<?= htmlspecialchars($user['nik'] ?? '') ?>" readonly class="readonly">
                        </div>
                        <div class="form-group">
                            <label>No. HP/WhatsApp</label>
                            <input type="text" value="<?= htmlspecialchars($user['no_hp'] ?? '') ?>" readonly class="readonly">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Alamat Lengkap</label>
                        <textarea readonly class="readonly" rows="2"><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- BAGIAN 3: DATA PELAMAR -->
                <!-- ============================================================ -->
                <div class="form-section">
                    <h2>3. Data Pelamar</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Agama <span class="required">*</span></label>
                            <select name="agama" id="agama" required>
                                <option value="">-- Pilih Agama --</option>
                                <option value="Islam">Islam</option>
                                <option value="Kristen">Kristen</option>
                                <option value="Katholik">Katholik</option>
                                <option value="Hindu">Hindu</option>
                                <option value="Buddha">Buddha</option>
                                <option value="Konghucu">Konghucu</option>
                            </select>
                            <small class="help-text">Persyaratan: Beragama Islam</small>
                        </div>
                        <div class="form-group">
                            <label>Tempat Lahir <span class="required">*</span></label>
                            <input type="text" name="tempat_lahir" value="<?= htmlspecialchars($pelamar['tempat_lahir'] ?? '') ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tanggal Lahir <span class="required">*</span></label>
                            <input type="date" name="tanggal_lahir" id="tanggalLahir" value="<?= htmlspecialchars($pelamar['tanggal_lahir'] ?? '') ?>" required>
                            <small class="help-text">Maksimal usia 58 tahun saat mendaftar.</small>
                        </div>
                        <div class="form-group">
                            <label>Jenis Kelamin <span class="required">*</span></label>
                            <select name="jenis_kelamin" required>
                                <option value="">-- Pilih --</option>
                                <option value="L" <?= ($pelamar['jenis_kelamin'] ?? '') == 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= ($pelamar['jenis_kelamin'] ?? '') == 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Pendidikan Terakhir <span class="required">*</span></label>
                            <select name="pendidikan_terakhir" id="pendidikanTerakhir" required>
                                <option value="">-- Pilih Pendidikan --</option>
                            </select>
                            <small class="help-text" id="pendidikanHelp">Pilih pendidikan terakhir Anda.</small>
                        </div>
                        <div class="form-group">
                            <label>Jurusan/Program Studi <span style="color: #6b7280; font-weight: 400;">(opsional)</span></label>
                            <input type="text" name="jurusan" placeholder="Contoh: Informatika / Manajemen">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>IPK/Nilai Akhir <span style="color: #6b7280; font-weight: 400;">(opsional)</span></label>
                            <input type="number" step="0.01" min="0" max="4" name="ipk" id="ipk" placeholder="Contoh: 3.50">
                            <small class="help-text">Akan tersimpan di catatan lamaran.</small>
                        </div>
                        <div class="form-group">
                            <label>Pengalaman Kerja (Tahun) <span style="color: #6b7280; font-weight: 400;">(opsional)</span></label>
                            <input type="number" name="pengalaman_kerja" id="pengalamanKerja" min="0" max="50" placeholder="Contoh: 5">
                            <small class="help-text">Akan tersimpan di catatan lamaran.</small>
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- BAGIAN 4: PERNYATAAN -->
                <!-- ============================================================ -->
                <div class="form-section">
                    <h2>4. Pernyataan</h2>
                    
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="pernyataan_integritas" value="Ya" required>
                            Saya menyatakan berakhlak mulia dan memiliki integritas yang tinggi.
                        </label>
                    </div>
                    
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="bebas_narkotika" value="Ya" required>
                            Saya menyatakan tidak menggunakan narkotika dan obat-obatan terlarang.
                        </label>
                    </div>
                    
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="sehat_jasmani" value="Ya" required>
                            Saya menyatakan sehat jasmani dan rohani.
                        </label>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- BAGIAN 5: DOKUMEN ADMINISTRASI UMUM -->
                <!-- ============================================================ -->
                <div class="form-section">
                    <h2>5. Dokumen Administrasi Umum</h2>
                    <p class="section-desc">Semua file harus dalam format PDF, maksimal 5MB.</p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Surat Lamaran <span class="required">*</span></label>
                            <input type="file" name="file_surat_lamaran" accept=".pdf" required>
                        </div>
                        <div class="form-group">
                            <label>CV / Biodata <span class="required">*</span></label>
                            <input type="file" name="file_cv" accept=".pdf" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Fotokopi KTP <span class="required">*</span></label>
                            <input type="file" name="file_ktp" accept=".pdf" required>
                        </div>
                        <div class="form-group">
                            <label>Pas Foto 3x4 & 4x6 <span class="required">*</span></label>
                            <input type="file" name="file_pasfoto" accept=".pdf" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Fotokopi Kartu Keluarga <span class="required">*</span></label>
                            <input type="file" name="file_kk" accept=".pdf" required>
                        </div>
                        <div class="form-group">
                            <label>Surat Keterangan Sehat <span class="required">*</span></label>
                            <input type="file" name="file_surat_sehat" accept=".pdf" required>
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- BAGIAN 6: DOKUMEN DOSEN (DINAMIS) -->
                <!-- ============================================================ -->
                <div class="form-section" id="dokumenDosen" style="display:none;">
                    <h2>6. Dokumen Pendidikan Dosen</h2>
                    <p class="section-desc">Wajib untuk lamaran Dosen. File PDF maksimal 5MB.</p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Ijazah S1 <span class="required">*</span></label>
                            <input type="file" name="file_ijazah_s1" accept=".pdf">
                        </div>
                        <div class="form-group">
                            <label>Transkrip S1 <span class="required">*</span></label>
                            <input type="file" name="file_transkrip_s1" accept=".pdf">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Ijazah S2 <span class="required">*</span></label>
                            <input type="file" name="file_ijazah_s2" accept=".pdf">
                        </div>
                        <div class="form-group">
                            <label>Transkrip S2 <span class="required">*</span></label>
                            <input type="file" name="file_transkrip_s2" accept=".pdf">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Ijazah S3 (Opsional)</label>
                            <input type="file" name="file_ijazah_s3" accept=".pdf">
                        </div>
                        <div class="form-group">
                            <label>Transkrip S3 (Opsional)</label>
                            <input type="file" name="file_transkrip_s3" accept=".pdf">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>SK Penyetaraan Ijazah (Opsional - bagi lulusan luar negeri)</label>
                        <input type="file" name="file_sk_penyetaraan" accept=".pdf">
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- BAGIAN 7: DOKUMEN TENDIK (DINAMIS) -->
                <!-- ============================================================ -->
                <div class="form-section" id="dokumenTendik" style="display:none;">
                    <h2>6. Dokumen Pendidikan Tenaga Kependidikan</h2>
                    <p class="section-desc">Wajib untuk lamaran Tendik. File PDF maksimal 5MB.</p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Ijazah Terakhir <span class="required">*</span></label>
                            <input type="file" name="file_ijazah" accept=".pdf">
                        </div>
                        <div class="form-group">
                            <label>Transkrip Nilai Terakhir <span class="required">*</span></label>
                            <input type="file" name="file_transkrip" accept=".pdf">
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- BAGIAN 8: SERTIFIKAT PENDUKUNG (OPSIONAL) -->
                <!-- ============================================================ -->
                <div class="form-section">
                    <h2>7. Sertifikat Pendukung (Opsional)</h2>
                    <p class="section-desc">Sertifikat kompetensi, pelatihan, atau keahlian. File PDF maksimal 5MB.</p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Sertifikat Pendukung 1</label>
                            <input type="file" name="file_sertifikat1" accept=".pdf">
                        </div>
                        <div class="form-group">
                            <label>Sertifikat Pendukung 2</label>
                            <input type="file" name="file_sertifikat2" accept=".pdf">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Sertifikat Pendukung 3</label>
                        <input type="file" name="file_sertifikat3" accept=".pdf">
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- TOMBOL SUBMIT -->
                <!-- ============================================================ -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">📤 Kirim Lamaran</button>
                    <a href="dashboard.php" class="btn btn-light">⬅️ Batal</a>
                </div>
            </form>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <div>
                <b>Portal Rekrutmen SDI</b>
                <br><span style="font-size:13px;color:#94A3B8;">Form Lamaran Pelamar</span>
            </div>
            <div style="font-size:13px;">© <?= date('Y') ?> Sistem Rekrutmen</div>
        </div>
    </footer>
</div>

<!-- ============================================================ -->
<!-- CUSTOM POPUP ALERT -->
<!-- ============================================================ -->
<div class="custom-alert-overlay" id="agamaAlert">
    <div class="custom-alert-box">
        <div class="icon">🕌</div>
        <h3>Persyaratan Agama</h3>
        <p>
            <strong>Persyaratan:</strong> Beragama Islam.<br><br>
            Saat ini pendaftaran hanya dibuka untuk yang beragama Islam.<br>
            Jika Anda beragama selain Islam, mohon menunggu informasi selanjutnya.
        </p>
        <button class="btn-close-alert primary" onclick="closeAgamaAlert()">Saya Mengerti</button>
    </div>
</div>

<script>
// ============================================================
// FUNGSI UNTUK POPUP AGAMA
// ============================================================
function showAgamaAlert() {
    document.getElementById('agamaAlert').style.display = 'flex';
}

function closeAgamaAlert() {
    document.getElementById('agamaAlert').style.display = 'none';
    document.getElementById('agama').value = '';
}

// ============================================================
// FORM DINAMIS
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var jenisLamaran = document.getElementById('jenisLamaran');
    var pendidikanSelect = document.getElementById('pendidikanTerakhir');
    var pendidikanHelp = document.getElementById('pendidikanHelp');
    var tujuanHelp = document.getElementById('tujuanHelp');
    var dokumenDosen = document.getElementById('dokumenDosen');
    var dokumenTendik = document.getElementById('dokumenTendik');
    var jenisInfo = document.getElementById('jenisInfo');
    var jenisInfoText = document.getElementById('jenisInfoText');
    var agamaSelect = document.getElementById('agama');

    // ============================================================
    // VALIDASI AGAMA - TAMPILKAN POPUP
    // ============================================================
    if (agamaSelect) {
        agamaSelect.addEventListener('change', function() {
            var agama = this.value.toLowerCase();
            if (agama !== '' && agama !== 'islam') {
                showAgamaAlert();
                this.value = '';
            }
        });
    }

    function updateForm() {
        var jenis = jenisLamaran.value;
        
        // Sembunyikan semua
        dokumenDosen.style.display = 'none';
        dokumenTendik.style.display = 'none';
        jenisInfo.className = 'jenis-info';
        jenisInfo.style.display = 'none';
        
        // Reset required
        document.querySelectorAll('#dokumenDosen input[type="file"]').forEach(function(el) {
            el.required = false;
        });
        document.querySelectorAll('#dokumenTendik input[type="file"]').forEach(function(el) {
            el.required = false;
        });
        
        // Kosongkan select pendidikan
        pendidikanSelect.innerHTML = '<option value="">-- Pilih Pendidikan --</option>';
        
        if (jenis === 'dosen') {
            // Info
            jenisInfo.style.display = 'block';
            jenisInfo.className = 'jenis-info dosen';
            jenisInfoText.textContent = '📚 Anda memilih Dosen. Persyaratan: Pendidikan minimal S2, melampirkan ijazah S1, S2, dan S3 (opsional).';
            
            // Dokumen Dosen
            dokumenDosen.style.display = 'block';
            document.querySelectorAll('#dokumenDosen input[type="file"]').forEach(function(el) {
                if (el.name.includes('ijazah_s3') || el.name.includes('transkrip_s3') || el.name.includes('sk_penyetaraan')) {
                    el.required = false;
                } else {
                    el.required = true;
                }
            });
            
            // Pendidikan: S2 dan S3
            ['S2', 'S3'].forEach(function(opt) {
                var option = document.createElement('option');
                option.value = opt;
                option.textContent = opt;
                pendidikanSelect.appendChild(option);
            });
            
            pendidikanHelp.textContent = 'Untuk Dosen minimal pendidikan S2.';
            tujuanHelp.textContent = 'Isi dengan program studi tujuan (Contoh: Informatika, Manajemen, Hukum, dll)';
            
        } else if (jenis === 'tendik') {
            // Info
            jenisInfo.style.display = 'block';
            jenisInfo.className = 'jenis-info tendik';
            jenisInfoText.textContent = '📋 Anda memilih Tenaga Kependidikan. Persyaratan: Pendidikan maksimal S1, melampirkan ijazah terakhir.';
            
            // Dokumen Tendik
            dokumenTendik.style.display = 'block';
            document.querySelectorAll('#dokumenTendik input[type="file"]').forEach(function(el) {
                el.required = true;
            });
            
            // Pendidikan: SMA/SMK sampai S1
            ['SMA/SMK', 'D1', 'D2', 'D3', 'D4', 'S1'].forEach(function(opt) {
                var option = document.createElement('option');
                option.value = opt;
                option.textContent = opt;
                pendidikanSelect.appendChild(option);
            });
            
            pendidikanHelp.textContent = 'Untuk Tendik maksimal pendidikan S1.';
            tujuanHelp.textContent = 'Isi dengan unit kerja tujuan (Contoh: Administrasi Akademik, Keuangan, HRD, dll)';
            
        } else {
            // Default semua opsi
            ['SMA/SMK', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'].forEach(function(opt) {
                var option = document.createElement('option');
                option.value = opt;
                option.textContent = opt;
                pendidikanSelect.appendChild(option);
            });
            pendidikanHelp.textContent = 'Pilih pendidikan terakhir Anda.';
            tujuanHelp.textContent = 'Isi dengan program studi atau unit kerja tujuan.';
        }
    }

    if (jenisLamaran) {
        jenisLamaran.addEventListener('change', updateForm);
        updateForm(); // Jalankan pertama kali
    }

    // Validasi usia
    var tanggalLahir = document.getElementById('tanggalLahir');
    if (tanggalLahir) {
        tanggalLahir.addEventListener('change', function() {
            if (this.value) {
                var birth = new Date(this.value);
                var today = new Date();
                var age = today.getFullYear() - birth.getFullYear();
                var m = today.getMonth() - birth.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
                    age--;
                }
                if (age > 58) {
                    alert('Maaf, usia Anda ' + age + ' tahun. Maksimal usia pendaftar adalah 58 tahun.');
                    this.value = '';
                    this.focus();
                }
            }
        });
    }

    // Validasi file PDF
    document.querySelectorAll('input[type="file"]').forEach(function(input) {
        input.addEventListener('change', function() {
            if (this.files.length > 0) {
                var file = this.files[0];
                var ext = file.name.split('.').pop().toLowerCase();
                if (ext !== 'pdf') {
                    alert('File harus berformat PDF!');
                    this.value = '';
                }
                if (file.size > 5 * 1024 * 1024) {
                    alert('Ukuran file maksimal 5MB!');
                    this.value = '';
                }
            }
        });
    });

    // Validasi submit
    var form = document.getElementById('lamaranForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            var jenis = document.getElementById('jenisLamaran').value;
            if (!jenis) {
                e.preventDefault();
                alert('Silakan pilih jenis lamaran terlebih dahulu.');
                return false;
            }
            
            var tujuan = document.getElementById('tujuanSpesifik').value.trim();
            if (!tujuan) {
                e.preventDefault();
                alert('Silakan isi tujuan spesifik (Program Studi / Unit Kerja).');
                return false;
            }
            
            var agama = document.getElementById('agama').value;
            if (agama.toLowerCase() !== 'islam') {
                e.preventDefault();
                showAgamaAlert();
                return false;
            }
            
            return true;
        });
    }
});
</script>
</body>
</html>