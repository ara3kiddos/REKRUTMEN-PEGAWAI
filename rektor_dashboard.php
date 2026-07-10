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

$role_name = $user['nama_role'];

// ============================================================
// STATISTIK
// ============================================================

// Total lamaran yang masuk ke rekomendasi (status 9,10,11)
$total_rekomendasi = $pdo->query("
    SELECT COUNT(*) FROM lamaran WHERE id_status_lamaran IN (9, 10, 11)
")->fetchColumn();

// Total lamaran yang sudah direkomendasikan (status 12,13,14,15,16)
$total_direkomendasikan = $pdo->query("
    SELECT COUNT(*) FROM lamaran WHERE id_status_lamaran IN (12, 13, 14, 15, 16)
")->fetchColumn();

// Total lamaran ditolak
$total_ditolak = $pdo->query("
    SELECT COUNT(*) FROM lamaran WHERE id_status_lamaran = 17
")->fetchColumn();

// ============================================================
// DATA REKOMENDASI
// ============================================================

// Lamaran menunggu rekomendasi (status 9 - Rekomendasi Prodi/Unit)
$menunggu_rekomendasi = $pdo->query("
    SELECT 
        lm.id_lamaran,
        lm.tanggal_lamaran,
        lm.catatan,
        p.id_pelamar,
        u.nama_lengkap,
        u.email,
        u.nik,
        l.nama_lowongan,
        ms.nama_status
    FROM lamaran lm
    JOIN pelamar p ON lm.id_pelamar = p.id_pelamar
    JOIN users u ON p.id_user = u.id_user
    LEFT JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
    LEFT JOIN master_status_lamaran ms ON lm.id_status_lamaran = ms.id_master_status_lamaran
    WHERE lm.id_status_lamaran = 9
    ORDER BY lm.tanggal_lamaran ASC
    LIMIT 10
")->fetchAll();

// Lamaran rekomendasi dekan (status 10)
$rekomendasi_dekan = $pdo->query("
    SELECT 
        lm.id_lamaran,
        lm.tanggal_lamaran,
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
    WHERE lm.id_status_lamaran = 10
    ORDER BY lm.tanggal_lamaran ASC
    LIMIT 10
")->fetchAll();

// Lamaran menunggu rapat (status 11)
$menunggu_rapat = $pdo->query("
    SELECT 
        lm.id_lamaran,
        lm.tanggal_lamaran,
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
    WHERE lm.id_status_lamaran = 11
    ORDER BY lm.tanggal_lamaran ASC
    LIMIT 10
")->fetchAll();

// Lamaran pengumuman kelulusan (status 12)
$pengumuman = $pdo->query("
    SELECT 
        lm.id_lamaran,
        lm.tanggal_lamaran,
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
    WHERE lm.id_status_lamaran = 12
    ORDER BY lm.tanggal_lamaran DESC
    LIMIT 10
")->fetchAll();

// ============================================================
// PROSES APPROVE/TOLAK
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve'])) {
        $lamaran_id = $_POST['lamaran_id'] ?? 0;
        $status_baru = $_POST['status_baru'] ?? 11; // default ke Rapat SDI
        
        try {
            $stmt = $pdo->prepare("
                UPDATE lamaran 
                SET id_status_lamaran = ?, catatan = CONCAT(catatan, ' | Rekomendasi Rektor: Disetujui') 
                WHERE id_lamaran = ?
            ");
            $stmt->execute([$status_baru, $lamaran_id]);
            $success = 'Rekomendasi berhasil disetujui!';
            
            // Audit log
            auditLog($pdo, "Rektor approve lamaran ID: $lamaran_id ke status: $status_baru");
            
            header("Location: rektor_dashboard.php?success=1");
            exit;
        } catch (PDOException $e) {
            $error = 'Gagal approve: ' . $e->getMessage();
        }
    }
    
    if (isset($_POST['reject'])) {
        $lamaran_id = $_POST['lamaran_id'] ?? 0;
        
        try {
            $stmt = $pdo->prepare("
                UPDATE lamaran 
                SET id_status_lamaran = 17, catatan = CONCAT(catatan, ' | Rekomendasi Rektor: Ditolak') 
                WHERE id_lamaran = ?
            ");
            $stmt->execute([$lamaran_id]);
            $success = 'Rekomendasi berhasil ditolak!';
            
            // Audit log
            auditLog($pdo, "Rektor reject lamaran ID: $lamaran_id");
            
            header("Location: rektor_dashboard.php?success=1");
            exit;
        } catch (PDOException $e) {
            $error = 'Gagal tolak: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Rektor - SDI System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f1f5f9; 
            color: #1a1a2e; 
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
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
        .topline h1 { font-size: 26px; color: #1a1a2e; }
        .topline h1 span { color: #667eea; }
        .topline .hint { color: #6b7280; font-size: 14px; margin-top: 4px; }
        .topline-right { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        
        .badge { 
            display: inline-block; 
            padding: 4px 14px; 
            border-radius: 20px; 
            font-size: 12px; 
            font-weight: 600; 
        }
        .badge-primary { background: #2563eb; color: white; }
        .badge-success { background: #10b981; color: white; }
        .badge-warning { background: #f59e0b; color: white; }
        .badge-danger { background: #ef4444; color: white; }
        .badge-info { background: #06b6d4; color: white; }
        .badge-secondary { background: #6b7280; color: white; }
        
        .metric-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 15px; 
            margin-bottom: 15px;
        }
        .metric-card { 
            background: white; 
            border-radius: 12px; 
            padding: 20px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.08); 
            display: flex; 
            align-items: center; 
            gap: 15px; 
            border-left: 4px solid #2563eb;
        }
        .metric-card.metric-success { border-left-color: #10b981; }
        .metric-card.metric-warning { border-left-color: #f59e0b; }
        .metric-card.metric-danger { border-left-color: #ef4444; }
        .metric-card.metric-info { border-left-color: #06b6d4; }
        
        .metric-icon { font-size: 32px; width: 50px; text-align: center; }
        .metric-content { flex: 1; }
        .metric-label { display: block; font-size: 13px; color: #6b7280; font-weight: 500; }
        .metric-value { display: block; font-size: 24px; font-weight: 700; color: #1a1a2e; }
        
        .card { 
            background: white; 
            border-radius: 12px; 
            padding: 20px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.08); 
            margin-bottom: 20px;
        }
        .card h3 { font-size: 17px; margin-bottom: 6px; color: #1a1a2e; }
        .card .hint { font-size: 13px; color: #6b7280; margin-bottom: 15px; }
        .card-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .table-wrap { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .table th { 
            text-align: left; 
            padding: 10px 12px; 
            background: #f8fafc; 
            border-bottom: 2px solid #e2e8f0; 
            font-weight: 600; 
            color: #475569; 
            font-size: 12px;
            text-transform: uppercase;
        }
        .table td { padding: 10px 12px; border-bottom: 1px solid #f1f3f5; }
        .table tr:hover { background: #f8fafc; }
        .text-muted { color: #6b7280; font-size: 12px; }
        
        .btn { 
            display: inline-block; 
            padding: 8px 18px; 
            border-radius: 8px; 
            text-decoration: none; 
            font-size: 13px; 
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
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; }
        .btn-outline { 
            background: transparent; 
            color: #475569; 
            border: 1px solid #e2e8f0; 
        }
        .btn-outline:hover { background: #f1f5f9; }
        .btn-sm { padding: 4px 12px; font-size: 12px; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .mt-10 { margin-top: 10px; }
        .text-center { text-align: center; }
        
        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
            .metric-grid { grid-template-columns: 1fr 1fr; }
            .topline { flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 480px) {
            .metric-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">

    <!-- HEADER -->
    <div class="topline">
        <div>
            <h1>🏛️ Dashboard <span>Rektor</span></h1>
            <p class="hint">
                Selamat datang, <strong><?= htmlspecialchars($user['nama_lengkap']) ?></strong> 
                (<?= htmlspecialchars($role_name) ?>)
            </p>
        </div>
        <div class="topline-right">
            <span class="badge badge-primary">Role: Rektor</span>
            <span class="badge badge-success">Status: Aktif</span>
            <a href="../logout.php" class="btn btn-danger btn-sm">🚪 Logout</a>
        </div>
    </div>

    <!-- STATISTIK -->
    <div class="metric-grid">
        <div class="metric-card metric-warning">
            <div class="metric-icon">⏳</div>
            <div class="metric-content">
                <span class="metric-label">Menunggu Rekomendasi</span>
                <span class="metric-value"><?= number_format($total_rekomendasi) ?></span>
            </div>
        </div>
        <div class="metric-card metric-success">
            <div class="metric-icon">✅</div>
            <div class="metric-content">
                <span class="metric-label">Direkomendasikan</span>
                <span class="metric-value"><?= number_format($total_direkomendasikan) ?></span>
            </div>
        </div>
        <div class="metric-card metric-danger">
            <div class="metric-icon">❌</div>
            <div class="metric-content">
                <span class="metric-label">Ditolak</span>
                <span class="metric-value"><?= number_format($total_ditolak) ?></span>
            </div>
        </div>
    </div>

    <!-- MENU CEPAT -->
    <div class="card">
        <div class="card-header">
            <h3>⚡ Menu Cepat</h3>
        </div>
        <div class="quick-menu" style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="rektor_dashboard.php" class="btn btn-primary">📊 Dashboard</a>
            <a href="rektor_approve.php" class="btn btn-warning">📋 Approve Rekomendasi</a>
            <a href="rektor_laporan.php" class="btn btn-outline">📊 Laporan</a>
            <a href="../logout.php" class="btn btn-danger">🚪 Logout</a>
        </div>
    </div>

    <!-- GRID 2 KOLOM -->
    <div class="grid-2">
        <!-- KOLOM KIRI: MENUNGGU REKOMENDASI -->
        <div class="card">
            <h3>⏳ Menunggu Rekomendasi</h3>
            <p class="hint">Lamaran yang sudah direkomendasikan Prodi/Unit, menunggu persetujuan Rektor.</p>
            
            <?php if(empty($menunggu_rekomendasi)): ?>
                <p style="color:green;padding:20px 0;text-align:center;">✅ Tidak ada lamaran menunggu rekomendasi.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Lowongan</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($menunggu_rekomendasi as $l): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($l['nama_lengkap']) ?></strong>
                                        <br><span class="text-muted"><?= htmlspecialchars($l['email']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($l['nama_lowongan'] ?? '-') ?></td>
                                    <td><?= date('d-m-Y', strtotime($l['tanggal_lamaran'])) ?></td>
                                    <td>
                                        <a href="rektor_approve.php?id=<?= $l['id_lamaran'] ?>" class="btn btn-primary btn-sm">📋 Review</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- KOLOM KANAN: REKOMENDASI DEKAN -->
        <div class="card">
            <h3>📋 Rekomendasi Dekan</h3>
            <p class="hint">Lamaran yang sudah mendapatkan rekomendasi dari Dekan.</p>
            
            <?php if(empty($rekomendasi_dekan)): ?>
                <p style="color:#999;padding:20px 0;text-align:center;">Belum ada rekomendasi dekan.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Lowongan</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($rekomendasi_dekan as $l): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($l['nama_lengkap']) ?></strong>
                                        <br><span class="text-muted"><?= htmlspecialchars($l['email']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($l['nama_lowongan'] ?? '-') ?></td>
                                    <td><?= date('d-m-Y', strtotime($l['tanggal_lamaran'])) ?></td>
                                    <td>
                                        <a href="rektor_approve.php?id=<?= $l['id_lamaran'] ?>" class="btn btn-primary btn-sm">📋 Review</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- KOLOM KIRI: MENUNGGU RAPAT -->
        <div class="card">
            <h3>🤝 Menunggu Rapat SDI</h3>
            <p class="hint">Lamaran yang sudah disetujui Rektor, menunggu rapat SDI/Universitas.</p>
            
            <?php if(empty($menunggu_rapat)): ?>
                <p style="color:#999;padding:20px 0;text-align:center;">Tidak ada lamaran menunggu rapat.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Lowongan</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($menunggu_rapat as $l): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($l['nama_lengkap']) ?></strong>
                                        <br><span class="text-muted"><?= htmlspecialchars($l['email']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($l['nama_lowongan'] ?? '-') ?></td>
                                    <td><?= date('d-m-Y', strtotime($l['tanggal_lamaran'])) ?></td>
                                    <td>
                                        <span class="badge badge-warning">Menunggu Rapat</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- KOLOM KANAN: PENGUMUMAN -->
        <div class="card">
            <h3>📢 Pengumuman Kelulusan</h3>
            <p class="hint">Lamaran yang sudah diumumkan kelulusannya.</p>
            
            <?php if(empty($pengumuman)): ?>
                <p style="color:#999;padding:20px 0;text-align:center;">Belum ada pengumuman kelulusan.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Lowongan</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($pengumuman as $l): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($l['nama_lengkap']) ?></strong>
                                        <br><span class="text-muted"><?= htmlspecialchars($l['email']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($l['nama_lowongan'] ?? '-') ?></td>
                                    <td><?= date('d-m-Y', strtotime($l['tanggal_lamaran'])) ?></td>
                                    <td>
                                        <span class="badge badge-success">✅ Lulus</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- FOOTER -->
    <div style="text-align:center;padding:20px 0;color:#6b7280;font-size:13px;border-top:1px solid #e2e8f0;margin-top:20px;">
        &copy; <?= date('Y') ?> SDI System - Universitas Muhammadiyah Banjarmasin
        <br><span style="font-size:11px;">Dashboard Rektor</span>
    </div>

</div>
</body>
</html>