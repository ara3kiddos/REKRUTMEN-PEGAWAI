<?php
session_start();

// ============================================================
// CEK LOGIN
// ============================================================
if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit;
}

// Cek role (hanya Rektor - role 3)
if ($_SESSION['id_role'] != 3) {
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

if (!$lamaran_id) {
    header("Location: rektor_dashboard.php");
    exit;
}

// ============================================================
// AMBIL DATA LAMARAN
// ============================================================
$stmt = $pdo->prepare("
    SELECT 
        lm.*,
        p.id_pelamar,
        p.tempat_lahir,
        p.tanggal_lahir,
        p.jenis_kelamin,
        p.umur,
        u.nama_lengkap,
        u.email,
        u.no_hp,
        u.nik,
        u.alamat,
        l.nama_lowongan,
        ms.nama_status,
        ms.id_master_status_lamaran as status_id
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
    header("Location: rektor_dashboard.php");
    exit;
}

// ============================================================
// PROSES APPROVE/TOLAK
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve'])) {
        $status_baru = $_POST['status_baru'] ?? 11;
        
        try {
            $stmt = $pdo->prepare("
                UPDATE lamaran 
                SET id_status_lamaran = ?, catatan = CONCAT(catatan, ' | Rekomendasi Rektor: Disetujui') 
                WHERE id_lamaran = ?
            ");
            $stmt->execute([$status_baru, $lamaran_id]);
            $success = 'Rekomendasi berhasil disetujui!';
            auditLog($pdo, "Rektor approve lamaran ID: $lamaran_id");
            header("Location: rektor_dashboard.php?success=1");
            exit;
        } catch (PDOException $e) {
            $error = 'Gagal approve: ' . $e->getMessage();
        }
    }
    
    if (isset($_POST['reject'])) {
        try {
            $stmt = $pdo->prepare("
                UPDATE lamaran 
                SET id_status_lamaran = 17, catatan = CONCAT(catatan, ' | Rekomendasi Rektor: Ditolak') 
                WHERE id_lamaran = ?
            ");
            $stmt->execute([$lamaran_id]);
            $success = 'Rekomendasi berhasil ditolak!';
            auditLog($pdo, "Rektor reject lamaran ID: $lamaran_id");
            header("Location: rektor_dashboard.php?success=1");
            exit;
        } catch (PDOException $e) {
            $error = 'Gagal tolak: ' . $e->getMessage();
        }
    }
}

// Ambil status untuk dropdown
$statusList = $pdo->query("
    SELECT * FROM master_status_lamaran 
    WHERE id_master_status_lamaran IN (11, 12, 13, 14, 15, 16)
    ORDER BY urutan
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Rekomendasi - Rektor</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f1f5f9; 
            color: #1a1a2e; 
            padding: 20px;
        }
        .container { max-width: 900px; margin: 0 auto; }
        
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
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-outline { 
            background: transparent; 
            color: #475569; 
            border: 1px solid #d1d5db; 
        }
        .btn-outline:hover { background: #f1f5f9; }
        .btn-lg { padding: 12px 32px; font-size: 16px; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { 
            display: block; 
            font-weight: 600; 
            font-size: 14px; 
            margin-bottom: 5px; 
            color: #475569; 
        }
        .form-group select, .form-group textarea {
            width: 100%; 
            padding: 10px 14px; 
            border: 1px solid #d1d5db; 
            border-radius: 8px; 
            font-size: 14px; 
        }
        .form-group textarea { min-height: 80px; }
        
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
        .badge-success { background: #10b981; color: white; }
        .badge-danger { background: #ef4444; color: white; }
        
        .alert { 
            padding: 12px 16px; 
            border-radius: 8px; 
            margin-bottom: 15px; 
            font-size: 14px; 
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        
        .action-buttons { 
            display: flex; 
            gap: 15px; 
            margin-top: 20px; 
            flex-wrap: wrap; 
        }
        
        @media (max-width: 600px) {
            .info-row { flex-direction: column; gap: 5px; }
            .action-buttons { flex-direction: column; }
            .action-buttons .btn { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>
<div class="container">

    <div class="topline">
        <div>
            <h1>📋 Approve Rekomendasi</h1>
            <p class="hint" style="margin:0;color:#6b7280;">Review dan approve rekomendasi lamaran.</p>
        </div>
        <div>
            <a href="rektor_dashboard.php" class="btn btn-outline">⬅ Kembali</a>
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
            <span class="label">NIK</span>
            <span class="value"><?= htmlspecialchars($lamaran['nik']) ?></span>
        </div>
        <div class="info-row">
            <span class="label">No. HP</span>
            <span class="value"><?= htmlspecialchars($lamaran['no_hp']) ?></span>
        </div>
        <div class="info-row">
            <span class="label">Lowongan</span>
            <span class="value"><?= htmlspecialchars($lamaran['nama_lowongan'] ?? '-') ?></span>
        </div>
        <div class="info-row">
            <span class="label">Status Saat Ini</span>
            <span class="value">
                <span class="badge badge-warning"><?= htmlspecialchars($lamaran['nama_status']) ?></span>
            </span>
        </div>
        <div class="info-row">
            <span class="label">Catatan</span>
            <span class="value"><?= htmlspecialchars($lamaran['catatan'] ?? '-') ?></span>
        </div>
    </div>

    <!-- FORM APPROVE -->
    <div class="card">
        <h2>✏️ Approve Rekomendasi</h2>
        <p class="hint">Pilih status selanjutnya untuk lamaran ini.</p>
        
        <form method="POST">
            <div class="form-group">
                <label>Status Selanjutnya</label>
                <select name="status_baru" required>
                    <option value="11">Rapat SDI/Universitas</option>
                    <option value="12">Pengumuman Kelulusan</option>
                    <option value="13">Pembekalan BPH/Pimpinan</option>
                    <option value="14">Pengangkatan SK</option>
                    <option value="15">Penempatan Kerja</option>
                    <option value="16">Diterima</option>
                </select>
            </div>
            
            <div class="action-buttons">
                <button type="submit" name="approve" class="btn btn-success btn-lg">✅ Setujui</button>
                <button type="submit" name="reject" class="btn btn-danger btn-lg" onclick="return confirm('Yakin ingin menolak rekomendasi ini?')">❌ Tolak</button>
                <a href="rektor_dashboard.php" class="btn btn-outline btn-lg">⬅ Batal</a>
            </div>
        </form>
    </div>

    <div style="text-align:center;padding:20px 0;color:#6b7280;font-size:13px;border-top:1px solid #e2e8f0;margin-top:20px;">
        &copy; <?= date('Y') ?> SDI System - Universitas Muhammadiyah Banjarmasin
    </div>
</div>
</body>
</html>