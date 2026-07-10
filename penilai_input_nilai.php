<?php
session_start();

// ============================================================
// CEK LOGIN
// ============================================================
if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit;
}

// Cek role (hanya Penilai - role 4)
if ($_SESSION['id_role'] != 4) {
    header("Location: ../dashboard.php");
    exit;
}

require_once __DIR__ . '/../includes/config.php';

// Ambil data user
$stmt = $pdo->prepare("
    SELECT u.*, r.nama_role 
    FROM users u 
    JOIN roles r ON u.id_role = r.id_role 
    WHERE u.id_user = ? AND u.status_aktif = 1
");
$stmt->execute([$_SESSION['id_user']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$lamaran_id = $_GET['id'] ?? 0;
$tes_id = $_GET['tes'] ?? 0;

if (!$lamaran_id || !$tes_id) {
    header("Location: penilai_dashboard.php");
    exit;
}

// ============================================================
// AMBIL DATA LAMARAN
// ============================================================
$stmt = $pdo->prepare("
    SELECT 
        lm.*,
        p.id_pelamar,
        u.nama_lengkap,
        u.email,
        l.nama_lowongan,
        ms.nama_status
    FROM lamaran lm
    JOIN pelamar p ON lm.id_pelamar = p.id_pelamar
    JOIN users u ON p.id_user = u.id_user
    LEFT JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
    LEFT JOIN master_status_lamaran ms ON lm.id_status_lamaran = ms.id_master_status_lamaran
    WHERE lm.id_lamaran = ?
");
$stmt->execute([$lamaran_id]);
$lamaran = $stmt->fetch();

if (!$lamaran) {
    header("Location: penilai_dashboard.php");
    exit;
}

// ============================================================
// AMBIL JENIS TES
// ============================================================
$stmt = $pdo->prepare("SELECT * FROM jenis_tes WHERE id_tes = ?");
$stmt->execute([$tes_id]);
$jenis_tes = $stmt->fetch();

if (!$jenis_tes) {
    header("Location: penilai_dashboard.php");
    exit;
}

// ============================================================
// CEK APAKAH SUDAH ADA NILAI
// ============================================================
$stmt = $pdo->prepare("
    SELECT * FROM nilai_tes 
    WHERE id_lamaran = ? AND id_tes = ?
");
$stmt->execute([$lamaran_id, $tes_id]);
$nilai_existing = $stmt->fetch();

// ============================================================
// PROSES INPUT NILAI
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_nilai'])) {
    $nilai = $_POST['nilai'] ?? 0;
    $catatan = $_POST['catatan'] ?? '';
    
    // VALIDASI: Nilai harus diisi
    if ($nilai === '' || $nilai === null) {
        $error = 'Nilai wajib diisi!';
    } else {
        try {
            // Cari atau buat id_penilai
            $stmt = $pdo->prepare("SELECT id_penilai FROM penilai WHERE id_user = ?");
            $stmt->execute([$_SESSION['id_user']]);
            $penilai = $stmt->fetch();
            
            if (!$penilai) {
                // Buat data penilai jika belum ada
                $stmt = $pdo->prepare("INSERT INTO penilai (id_user) VALUES (?)");
                $stmt->execute([$_SESSION['id_user']]);
                $id_penilai = $pdo->lastInsertId();
            } else {
                $id_penilai = $penilai['id_penilai'];
            }
            
            // ============================================================
            // SIMPAN NILAI KE TABEL NILAI_TES
            // ============================================================
            // Cek apakah sudah ada data
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM nilai_tes 
                WHERE id_lamaran = ? AND id_tes = ?
            ");
            $stmt->execute([$lamaran_id, $tes_id]);
            $exists = $stmt->fetchColumn() > 0;
            
            if ($exists) {
                // Update data yang sudah ada
                $stmt = $pdo->prepare("
                    UPDATE nilai_tes 
                    SET nilai = ?, catatan = ?, tanggal_input = NOW(), id_penilai = ?
                    WHERE id_lamaran = ? AND id_tes = ?
                ");
                $stmt->execute([$nilai, $catatan, $id_penilai, $lamaran_id, $tes_id]);
            } else {
                // Insert data baru
                $stmt = $pdo->prepare("
                    INSERT INTO nilai_tes (id_lamaran, id_tes, id_penilai, nilai, catatan, tanggal_input) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$lamaran_id, $tes_id, $id_penilai, $nilai, $catatan]);
            }
            
            // Update status lamaran ke tes berikutnya atau lanjut
            $status_selanjutnya = $lamaran['id_status_lamaran'] + 1;
            if ($status_selanjutnya > 8) {
                $status_selanjutnya = 9; // Rekomendasi Prodi/Unit
            }
            
            $stmt = $pdo->prepare("
                UPDATE lamaran 
                SET id_status_lamaran = ? 
                WHERE id_lamaran = ?
            ");
            $stmt->execute([$status_selanjutnya, $lamaran_id]);
            
            $success = 'Nilai berhasil disimpan! Status lamaran diperbarui.';
            
            // Audit log
            auditLog($pdo, "Penilai input nilai lamaran ID: $lamaran_id - Tes: " . $jenis_tes['nama_tes']);
            
            header("Location: penilai_dashboard.php?success=1");
            exit;
        } catch (PDOException $e) {
            $error = 'Gagal simpan nilai: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Nilai - Penilai</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f1f5f9; 
            color: #1a1a2e; 
            padding: 20px;
        }
        .container { max-width: 800px; margin: 0 auto; }
        
        .card { 
            background: white; 
            border-radius: 12px; 
            padding: 25px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.08); 
            margin-bottom: 20px;
        }
        .card h2 { font-size: 22px; margin-bottom: 10px; }
        .card .hint { font-size: 14px; color: #6b7280; margin-bottom: 20px; }
        
        .topline { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap; 
            gap: 15px; 
            margin-bottom: 25px; 
            background: white;
            padding: 20px 25px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .topline h1 { font-size: 24px; color: #1a1a2e; }
        
        .btn { 
            display: inline-block; 
            padding: 10px 24px; 
            border-radius: 8px; 
            text-decoration: none; 
            font-size: 14px; 
            font-weight: 500; 
            border: none; 
            cursor: pointer; 
            transition: all 0.2s; 
        }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-outline { 
            background: transparent; 
            color: #475569; 
            border: 1px solid #d1d5db; 
        }
        .btn-outline:hover { background: #f1f5f9; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { 
            display: block; 
            font-weight: 600; 
            font-size: 14px; 
            margin-bottom: 5px; 
            color: #475569; 
        }
        .form-group input, .form-group textarea {
            width: 100%; 
            padding: 10px 14px; 
            border: 1px solid #d1d5db; 
            border-radius: 8px; 
            font-size: 14px; 
        }
        .form-group textarea { min-height: 80px; }
        .form-group input:focus, .form-group textarea:focus {
            outline: none; 
            border-color: #2563eb; 
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        
        .info-row { 
            display: flex; 
            justify-content: space-between; 
            padding: 8px 0; 
            border-bottom: 1px solid #f1f3f5; 
        }
        .info-row .label { color: #6b7280; font-weight: 500; }
        .info-row .value { font-weight: 600; }
        
        .badge { 
            display: inline-block; 
            padding: 4px 14px; 
            border-radius: 20px; 
            font-size: 12px; 
            font-weight: 600; 
        }
        .badge-warning { background: #f59e0b; color: white; }
        
        .alert { 
            padding: 12px 16px; 
            border-radius: 8px; 
            margin-bottom: 15px; 
            font-size: 14px; 
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        
        @media (max-width: 600px) {
            .info-row { flex-direction: column; gap: 5px; }
        }
    </style>
</head>
<body>
<div class="container">

    <div class="topline">
        <div>
            <h1>📝 Input Nilai Tes</h1>
            <p class="hint" style="margin:0;color:#6b7280;">Input nilai untuk pelamar.</p>
        </div>
        <div>
            <a href="penilai_dashboard.php" class="btn btn-outline">⬅ Kembali</a>
        </div>
    </div>

    <?php if($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- INFORMASI LAMARAN -->
    <div class="card">
        <h2>👤 Informasi Pelamar</h2>
        
        <div class="info-row">
            <span class="label">Nama Lengkap</span>
            <span class="value"><?= htmlspecialchars($lamaran['nama_lengkap']) ?></span>
        </div>
        <div class="info-row">
            <span class="label">Email</span>
            <span class="value"><?= htmlspecialchars($lamaran['email']) ?></span>
        </div>
        <div class="info-row">
            <span class="label">Lowongan</span>
            <span class="value"><?= htmlspecialchars($lamaran['nama_lowongan'] ?? '-') ?></span>
        </div>
        <div class="info-row">
            <span class="label">Jenis Tes</span>
            <span class="value">
                <span class="badge badge-warning"><?= htmlspecialchars($jenis_tes['nama_tes']) ?></span>
            </span>
        </div>
        <div class="info-row">
            <span class="label">Deskripsi</span>
            <span class="value"><?= htmlspecialchars($jenis_tes['deskripsi'] ?? '-') ?></span>
        </div>
        <div class="info-row">
            <span class="label">Status Saat Ini</span>
            <span class="value"><?= htmlspecialchars($lamaran['nama_status']) ?></span>
        </div>
    </div>

    <!-- FORM INPUT NILAI -->
    <div class="card">
        <h2>✏️ Input Nilai</h2>
        <p class="hint">Masukkan nilai tes untuk pelamar.</p>
        
        <form method="POST">
            <div class="form-group">
                <label>Nilai (0-100) <span style="color:red;">*</span></label>
                <input type="number" name="nilai" min="0" max="100" step="0.01" 
                       value="<?= htmlspecialchars($nilai_existing['nilai'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Catatan <span style="color:#6b7280;font-weight:400;">(opsional)</span></label>
                <textarea name="catatan" placeholder="Catatan untuk nilai ini..."><?= htmlspecialchars($nilai_existing['catatan'] ?? '') ?></textarea>
            </div>
            
            <?php if($nilai_existing): ?>
                <div style="padding:10px;background:#fef3c7;border-radius:8px;margin-bottom:15px;">
                    <span style="color:#92400e;">⚠️ Nilai sudah ada. Simpan akan memperbarui nilai yang ada.</span>
                </div>
            <?php endif; ?>
            
            <div style="display:flex;gap:10px;margin-top:10px;">
                <button type="submit" name="simpan_nilai" class="btn btn-success">💾 Simpan Nilai</button>
                <a href="penilai_dashboard.php" class="btn btn-outline">⬅ Batal</a>
            </div>
        </form>
    </div>

    <div style="text-align:center;padding:20px 0;color:#6b7280;font-size:13px;border-top:1px solid #e2e8f0;margin-top:20px;">
        &copy; <?= date('Y') ?> SDI System - Universitas Muhammadiyah Banjarmasin
    </div>
</div>
</body>
</html>