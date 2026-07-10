<?php
// Tampilkan semua error untuk debugging
session_start();

// ============================================================
// CEK LOGIN
// ============================================================
if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit;
}

// Cek role (hanya admin)
if ($_SESSION['id_role'] == 5) {
    header("Location: ../dashboard.php");
    exit;
}

// Koneksi database
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

$role_id = $user['id_role'];
$role_name = $user['nama_role'];

// ============================================================
// STATISTIK UTAMA
// ============================================================

$total_pelamar = $pdo->query("SELECT COUNT(*) FROM pelamar")->fetchColumn();
$total_lamaran = $pdo->query("SELECT COUNT(*) FROM lamaran")->fetchColumn();
$total_lowongan = $pdo->query("SELECT COUNT(*) FROM lowongan WHERE status = 'Aktif'")->fetchColumn();
$total_dokumen = $pdo->query("SELECT COUNT(*) FROM pelamar_dokumen")->fetchColumn();

// ============================================================
// STATISTIK STATUS LAMARAN
// ============================================================

$status_menunggu = $pdo->query("
    SELECT COUNT(*) FROM lamaran WHERE id_status_lamaran IN (1, 2)
")->fetchColumn();

$status_tes = $pdo->query("
    SELECT COUNT(*) FROM lamaran WHERE id_status_lamaran IN (4, 5, 6, 7, 8)
")->fetchColumn();

$status_rekomendasi = $pdo->query("
    SELECT COUNT(*) FROM lamaran WHERE id_status_lamaran IN (9, 10, 11)
")->fetchColumn();

$status_diterima = $pdo->query("
    SELECT COUNT(*) FROM lamaran WHERE id_status_lamaran IN (14, 15, 16)
")->fetchColumn();

$status_ditolak = $pdo->query("
    SELECT COUNT(*) FROM lamaran WHERE id_status_lamaran = 17
")->fetchColumn();

// ============================================================
// STATISTIK VERIFIKASI DOKUMEN
// ============================================================

$dokumen_belum = $pdo->query("
    SELECT COUNT(*) FROM pelamar_dokumen WHERE status_verifikasi = 'Belum'
")->fetchColumn();

$dokumen_sesuai = $pdo->query("
    SELECT COUNT(*) FROM pelamar_dokumen WHERE status_verifikasi = 'Ya'
")->fetchColumn();

$dokumen_tidak = $pdo->query("
    SELECT COUNT(*) FROM pelamar_dokumen WHERE status_verifikasi = 'Tidak'
")->fetchColumn();

// ============================================================
// DATA KHUSUS SDI (ROLE 2)
// ============================================================

// 1. Lamaran perlu verifikasi (status 1 - Lamaran Dikirim)
$need_verification = $pdo->query("
    SELECT 
        lm.id_lamaran,
        lm.tanggal_lamaran,
        lm.catatan,
        p.id_pelamar,
        u.nama_lengkap,
        u.email,
        u.no_hp,
        u.nik,
        l.nama_lowongan,
        ms.nama_status
    FROM lamaran lm
    JOIN pelamar p ON lm.id_pelamar = p.id_pelamar
    JOIN users u ON p.id_user = u.id_user
    LEFT JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
    LEFT JOIN master_status_lamaran ms ON lm.id_status_lamaran = ms.id_master_status_lamaran
    WHERE lm.id_status_lamaran = 1
    ORDER BY lm.tanggal_lamaran ASC
    LIMIT 10
")->fetchAll();

// 2. Lamaran proses verifikasi (status 2 - Seleksi Administrasi SDI)
$in_verification = $pdo->query("
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
    WHERE lm.id_status_lamaran = 2
    ORDER BY lm.tanggal_lamaran ASC
    LIMIT 10
")->fetchAll();

// 3. Data pelamar dengan dokumen belum lengkap
$dokumen_belum_lengkap = $pdo->query("
    SELECT 
        p.id_pelamar,
        u.nama_lengkap,
        u.email,
        COUNT(pd.id_pelamar_dokumen) as total_dokumen,
        SUM(CASE WHEN pd.status_verifikasi = 'Belum' THEN 1 ELSE 0 END) as belum_diverifikasi
    FROM pelamar p
    JOIN users u ON p.id_user = u.id_user
    LEFT JOIN pelamar_dokumen pd ON p.id_pelamar = pd.id_pelamar
    GROUP BY p.id_pelamar, u.nama_lengkap, u.email
    HAVING belum_diverifikasi > 0
    ORDER BY belum_diverifikasi DESC
    LIMIT 10
")->fetchAll();

// 4. Lowongan dengan pelamar terbanyak
$top_lowongan = $pdo->query("
    SELECT 
        l.id_lowongan,
        l.nama_lowongan,
        COUNT(lm.id_lamaran) as total_pelamar
    FROM lowongan l
    LEFT JOIN lamaran lm ON l.id_lowongan = lm.id_lowongan
    WHERE l.status = 'Aktif'
    GROUP BY l.id_lowongan, l.nama_lowongan
    ORDER BY total_pelamar DESC
    LIMIT 5
")->fetchAll();

// 5. Statistik per hari (7 hari terakhir)
$stats_per_day = $pdo->query("
    SELECT 
        DATE(tanggal_lamaran) as tanggal,
        COUNT(*) as total
    FROM lamaran
    WHERE tanggal_lamaran >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(tanggal_lamaran)
    ORDER BY tanggal ASC
")->fetchAll();

// 6. Lamaran terbaru
$recent_lamaran = $pdo->query("
    SELECT 
        lm.id_lamaran,
        lm.tanggal_lamaran,
        lm.catatan,
        p.id_pelamar,
        u.nama_lengkap,
        u.email,
        l.nama_lowongan,
        ms.nama_status,
        lm.id_status_lamaran
    FROM lamaran lm
    JOIN pelamar p ON lm.id_pelamar = p.id_pelamar
    JOIN users u ON p.id_user = u.id_user
    LEFT JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
    LEFT JOIN master_status_lamaran ms ON lm.id_status_lamaran = ms.id_master_status_lamaran
    ORDER BY lm.tanggal_lamaran DESC
    LIMIT 10
")->fetchAll();

// 7. Clustering
$cluster_count = $pdo->query("
    SELECT COUNT(DISTINCT p.id_pelamar) 
    FROM pelamar p
    JOIN lamaran lm ON p.id_pelamar = lm.id_pelamar
")->fetchColumn();


?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SDI System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f1f5f9; 
            color: #1a1a2e; 
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        /* Header */
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
        
        /* Badge */
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
        
        /* Metric Cards */
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
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid #2563eb;
        }
        .metric-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.12); 
        }
        .metric-card.metric-success { border-left-color: #10b981; }
        .metric-card.metric-warning { border-left-color: #f59e0b; }
        .metric-card.metric-danger { border-left-color: #ef4444; }
        .metric-card.metric-info { border-left-color: #06b6d4; }
        .metric-card.metric-primary { border-left-color: #2563eb; }
        
        .metric-icon { font-size: 32px; width: 50px; text-align: center; }
        .metric-content { flex: 1; }
        .metric-label { display: block; font-size: 13px; color: #6b7280; font-weight: 500; }
        .metric-value { display: block; font-size: 24px; font-weight: 700; color: #1a1a2e; }
        .metric-change { font-size: 12px; color: #6b7280; }
        
        /* Cards */
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
        
        /* Tables */
        .table-wrap { overflow-x: auto; }
        .table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 14px; 
        }
        .table th { 
            text-align: left; 
            padding: 10px 12px; 
            background: #f8fafc; 
            border-bottom: 2px solid #e2e8f0; 
            font-weight: 600; 
            color: #475569; 
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table td { padding: 10px 12px; border-bottom: 1px solid #f1f3f5; }
        .table tr:hover { background: #f8fafc; }
        .table .text-muted { color: #6b7280; font-size: 12px; }
        
        /* Buttons */
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
        
        /* Grid 2 columns */
        .grid-2 { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; 
        }
        
        /* Quick Menu */
        .quick-menu { 
            display: flex; 
            gap: 10px; 
            flex-wrap: wrap; 
            margin-top: 10px; 
        }
        .quick-menu .btn { 
            padding: 10px 20px; 
            font-size: 14px; 
        }
        
        /* Chart bars */
        .chart-bar { 
            display: flex; 
            align-items: flex-end; 
            height: 130px; 
            gap: 10px; 
            padding: 10px 0; 
        }
        .chart-bar-item { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
        }
        .chart-bar-item .bar { 
            width: 30px; 
            background: #2563eb; 
            border-radius: 4px 4px 0 0; 
            min-height: 5px; 
            transition: height 0.5s;
        }
        .chart-bar-item .bar-label { 
            font-size: 11px; 
            color: #6b7280; 
            margin-top: 5px; 
        }
        .chart-bar-item .bar-value { 
            font-size: 10px; 
            font-weight: 600; 
            color: #1a1a2e; 
        }
        
        /* Stat box */
        .stat-box { 
            background: #f8fafc; 
            padding: 15px; 
            border-radius: 8px; 
            text-align: center; 
        }
        .stat-box .number { 
            font-size: 28px; 
            font-weight: 700; 
            color: #2563eb; 
        }
        .stat-box .label { 
            font-size: 13px; 
            color: #6b7280; 
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
            .metric-grid { grid-template-columns: 1fr 1fr; }
            .topline { flex-direction: column; align-items: flex-start; }
            .topline-right { width: 100%; }
        }
        @media (max-width: 480px) {
            .metric-grid { grid-template-columns: 1fr; }
        }
        
        .mt-10 { margin-top: 10px; }
        .mt-15 { margin-top: 15px; }
        .mt-20 { margin-top: 20px; }
        .text-center { text-align: center; }
        .text-muted { color: #6b7280; }
        .text-success { color: #10b981; }
        .text-danger { color: #ef4444; }
        .text-warning { color: #f59e0b; }
    </style>
</head>
<body>
<div class="container">

    <!-- ============================================================ -->
    <!-- HEADER -->
    <!-- ============================================================ -->
    <div class="topline">
        <div>
            <h1>📊 Dashboard <span><?= htmlspecialchars($role_name) ?></span></h1>
            <p class="hint">
                Selamat datang, <strong><?= htmlspecialchars($user['nama_lengkap']) ?></strong> 
                (<?= htmlspecialchars($role_name) ?>)
            </p>
        </div>
        <div class="topline-right">
            <span class="badge badge-primary">Role: <?= htmlspecialchars($role_name) ?></span>
            <span class="badge badge-success">Status: Aktif</span>
            <span class="badge badge-info">Total Pelamar: <?= number_format($total_pelamar) ?></span>
            <a href="../logout.php" class="btn btn-danger btn-sm">🚪 Logout</a>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- STATISTIK UTAMA -->
    <!-- ============================================================ -->
    <div class="metric-grid">
        <div class="metric-card">
            <div class="metric-icon">👤</div>
            <div class="metric-content">
                <span class="metric-label">Total Pelamar</span>
                <span class="metric-value"><?= number_format($total_pelamar) ?></span>
                <span class="metric-change">📋 Terdaftar</span>
            </div>
        </div>
        
        <div class="metric-card metric-warning">
            <div class="metric-icon">📄</div>
            <div class="metric-content">
                <span class="metric-label">Total Lamaran</span>
                <span class="metric-value"><?= number_format($total_lamaran) ?></span>
                <span class="metric-change">📤 Masuk</span>
            </div>
        </div>
        
        <div class="metric-card metric-success">
            <div class="metric-icon">💼</div>
            <div class="metric-content">
                <span class="metric-label">Lowongan Aktif</span>
                <span class="metric-value"><?= number_format($total_lowongan) ?></span>
                <span class="metric-change">✅ Dibuka</span>
            </div>
        </div>
        
        <div class="metric-card metric-info">
            <div class="metric-icon">📎</div>
            <div class="metric-content">
                <span class="metric-label">Total Dokumen</span>
                <span class="metric-value"><?= number_format($total_dokumen) ?></span>
                <span class="metric-change">📁 Terupload</span>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- STATISTIK STATUS LAMARAN -->
    <!-- ============================================================ -->
    <div class="metric-grid">
        <div class="metric-card metric-warning">
            <div class="metric-icon">⏳</div>
            <div class="metric-content">
                <span class="metric-label">Menunggu Verifikasi</span>
                <span class="metric-value"><?= number_format($status_menunggu) ?></span>
                <span class="metric-change">🔄 Proses</span>
            </div>
        </div>
        
        <div class="metric-card metric-info">
            <div class="metric-icon">📝</div>
            <div class="metric-content">
                <span class="metric-label">Proses Tes</span>
                <span class="metric-value"><?= number_format($status_tes) ?></span>
                <span class="metric-change">📊 Berjalan</span>
            </div>
        </div>
        
        <div class="metric-card metric-primary">
            <div class="metric-icon">🤝</div>
            <div class="metric-content">
                <span class="metric-label">Rekomendasi</span>
                <span class="metric-value"><?= number_format($status_rekomendasi) ?></span>
                <span class="metric-change">📋 Menunggu</span>
            </div>
        </div>
        
        <div class="metric-card metric-success">
            <div class="metric-icon">🎉</div>
            <div class="metric-content">
                <span class="metric-label">Diterima</span>
                <span class="metric-value"><?= number_format($status_diterima) ?></span>
                <span class="metric-change">✅ Lolos</span>
            </div>
        </div>
        
        <div class="metric-card metric-danger">
            <div class="metric-icon">❌</div>
            <div class="metric-content">
                <span class="metric-label">Ditolak</span>
                <span class="metric-value"><?= number_format($status_ditolak) ?></span>
                <span class="metric-change">🚫 Tidak Lolos</span>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- STATISTIK VERIFIKASI DOKUMEN -->
    <!-- ============================================================ -->
    <div class="metric-grid">
        <div class="metric-card metric-warning">
            <div class="metric-icon">📄</div>
            <div class="metric-content">
                <span class="metric-label">Dokumen Belum Dicek</span>
                <span class="metric-value"><?= number_format($dokumen_belum) ?></span>
                <span class="metric-change">⏳ Perlu Verifikasi</span>
            </div>
        </div>
        
        <div class="metric-card metric-success">
            <div class="metric-icon">✅</div>
            <div class="metric-content">
                <span class="metric-label">Dokumen Sesuai</span>
                <span class="metric-value"><?= number_format($dokumen_sesuai) ?></span>
                <span class="metric-change">✔️ Valid</span>
            </div>
        </div>
        
        <div class="metric-card metric-danger">
            <div class="metric-icon">❌</div>
            <div class="metric-content">
                <span class="metric-label">Dokumen Tidak Sesuai</span>
                <span class="metric-value"><?= number_format($dokumen_tidak) ?></span>
                <span class="metric-change">⚠️ Perbaikan</span>
            </div>
        </div>
    </div>


    <!-- ============================================================ -->
    <!-- QUICK MENU -->
    <!-- ============================================================ -->
    <div class="card">
        <div class="card-header">
            <h3>⚡ Menu Cepat</h3>
        </div>
        <div class="quick-menu">
            <a href="verifikasi_lamaran.php" class="btn btn-primary">📄 Verifikasi Lamaran</a>
            <a href="data_pelamar.php" class="btn btn-outline">👤 Data Pelamar</a>
            <a href="kelola_lowongan.php" class="btn btn-outline">💼 Kelola Lowongan</a>
            <a href="clustering.php" class="btn btn-success">📊 K-Means Clustering</a>
<a href="https://pisn.kemdiktisaintek.go.id/" target="_blank" class="btn btn-outline">🎓 Cek Ijazah</a>           
<a href="laporan.php" class="btn btn-outline">📊 Laporan</a>
            <a href="../logout.php" class="btn btn-danger">🚪 Logout</a>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- GRID 2 KOLOM -->
    <!-- ============================================================ -->
    <div class="grid-2">

        <!-- KOLOM KIRI: LAMARAN PERLU VERIFIKASI -->
        <div class="card">
            <h3>📄 Lamaran Perlu Verifikasi</h3>
            <p class="hint">Lamaran baru yang menunggu verifikasi administrasi.</p>
            
            <?php if(empty($need_verification)): ?>
                <p style="color:green;padding:20px 0;text-align:center;">✅ Semua lamaran sudah diverifikasi.</p>
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
                            <?php foreach($need_verification as $l): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($l['nama_lengkap']) ?></strong>
                                        <br><span class="text-muted"><?= htmlspecialchars($l['email']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($l['nama_lowongan'] ?? '-') ?></td>
                                    <td><?= date('d-m-Y', strtotime($l['tanggal_lamaran'])) ?></td>
                                    <td>
                                        <a href="verifikasi_detail.php?id=<?= $l['id_lamaran'] ?>" class="btn btn-primary btn-sm">🔍 Verifikasi</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if(count($need_verification) >= 10): ?>
                    <p class="text-center mt-10">
                        <a href="verifikasi_lamaran.php" class="btn btn-outline btn-sm">Lihat Semua</a>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- KOLOM KANAN: LAMARAN PROSES VERIFIKASI -->
        <div class="card">
            <h3>🔄 Lamaran Proses Verifikasi</h3>
            <p class="hint">Lamaran yang sedang dalam proses verifikasi administrasi.</p>
            
            <?php if(empty($in_verification)): ?>
                <p style="color:#999;padding:20px 0;text-align:center;">Tidak ada lamaran dalam proses verifikasi.</p>
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
                            <?php foreach($in_verification as $l): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($l['nama_lengkap']) ?></strong>
                                        <br><span class="text-muted"><?= htmlspecialchars($l['email']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($l['nama_lowongan'] ?? '-') ?></td>
                                    <td><?= date('d-m-Y', strtotime($l['tanggal_lamaran'])) ?></td>
                                    <td>
                                        <a href="verifikasi_detail.php?id=<?= $l['id_lamaran'] ?>" class="btn btn-primary btn-sm">🔍 Lanjutkan</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- KOLOM KIRI: DOKUMEN BELUM LENGKAP -->
        <div class="card">
            <h3>📎 Dokumen Belum Lengkap</h3>
            <p class="hint">Pelamar dengan dokumen yang belum diverifikasi.</p>
            
            <?php if(empty($dokumen_belum_lengkap)): ?>
                <p style="color:green;padding:20px 0;text-align:center;">✅ Semua dokumen sudah lengkap.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Total</th>
                                <th>Belum</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($dokumen_belum_lengkap as $d): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($d['nama_lengkap']) ?></strong>
                                        <br><span class="text-muted"><?= htmlspecialchars($d['email']) ?></span>
                                    </td>
                                    <td><?= $d['total_dokumen'] ?></td>
                                    <td><span class="badge badge-warning"><?= $d['belum_diverifikasi'] ?></span></td>
                                    <td>
                                        <a href="verifikasi_dokumen.php?id=<?= $d['id_pelamar'] ?>" class="btn btn-primary btn-sm">📄 Cek</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- KOLOM KANAN: LOWONGAN TERPOPULER -->
        <div class="card">
            <h3>🔥 Lowongan Terpopuler</h3>
            <p class="hint">Lowongan dengan jumlah pelamar terbanyak.</p>
            
            <?php if(empty($top_lowongan)): ?>
                <p style="color:#999;padding:20px 0;text-align:center;">Belum ada data lowongan.</p>
            <?php else: ?>
                <?php foreach($top_lowongan as $l): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f1f3f5;">
                        <div>
                            <strong><?= htmlspecialchars($l['nama_lowongan']) ?></strong>
                            <br><span class="text-muted">Total Pelamar</span>
                        </div>
                        <span class="badge badge-primary" style="font-size:16px;padding:6px 14px;">
                            <?= number_format($l['total_pelamar']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- KOLOM KIRI: STATISTIK PER HARI -->
        <div class="card">
            <h3>📈 Statistik Lamaran 7 Hari Terakhir</h3>
            <p class="hint">Jumlah lamaran masuk per hari.</p>
            
            <?php if(empty($stats_per_day)): ?>
                <p style="color:#999;padding:20px 0;text-align:center;">Belum ada data lamaran.</p>
            <?php else: ?>
                <?php 
                $max = max(array_column($stats_per_day, 'total')) ?: 1;
                ?>
                <div class="chart-bar">
                    <?php foreach($stats_per_day as $s): 
                        $height = ($s['total'] / $max) * 100;
                        $day = date('d/m', strtotime($s['tanggal']));
                    ?>
                        <div class="chart-bar-item">
                            <div class="bar-value"><?= $s['total'] ?></div>
                            <div class="bar" style="height:<?= max(5, $height) ?>px;"></div>
                            <div class="bar-label"><?= $day ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- KOLOM KANAN: CLUSTERING & IJAZAH -->
        <div class="card">
            <h3>📊 Ringkasan Clustering & Ijazah</h3>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-top:10px;">
                <div class="stat-box">
                    <div class="number"><?= number_format($cluster_count) ?></div>
                    <div class="label">Total Pelamar</div>
                    <a href="clustering.php" class="btn btn-primary btn-sm" style="margin-top:8px;">📊 Clustering</a>
                </div>
                
                <div class="stat-box">
                    <div class="number" style="color:#10b981;"><?= number_format($ijazah_total) ?></div>
                    <div class="label">Total Cek Ijazah</div>
<a href="https://pisn.kemdiktisaintek.go.id/" target="_blank" class="btn btn-success btn-sm" style="margin-top:8px;">🎓 Cek Ijazah</a>                </div>
            </div>
            
            <div style="margin-top:15px;padding:12px;background:#f0f4ff;border-radius:8px;">
                <div style="display:flex;justify-content:space-between;font-size:13px;">
                    <span>Valid: <strong style="color:#10b981;"><?= number_format($ijazah_valid) ?></strong></span>
                    <span>|</span>
                    <span>Tidak Valid: <strong style="color:#ef4444;"><?= number_format($ijazah_invalid) ?></strong></span>
                    <span>|</span>
                    <span>Total: <strong><?= number_format($ijazah_total) ?></strong></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- LAMARAN TERBARU -->
    <!-- ============================================================ -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Lamaran Terbaru</h3>
            <span class="badge badge-secondary">10 Terakhir</span>
        </div>
        <p class="hint">10 lamaran terakhir yang masuk ke sistem.</p>
        
        <?php if(empty($recent_lamaran)): ?>
            <p style="color:#999;padding:20px 0;text-align:center;">Belum ada lamaran masuk.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama</th>
                            <th>Lowongan</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach($recent_lamaran as $l): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($l['nama_lengkap']) ?></strong>
                                    <br><span class="text-muted"><?= htmlspecialchars($l['email']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($l['nama_lowongan'] ?? '-') ?></td>
                                <td>
                                    <span class="badge 
                                        <?= in_array($l['id_status_lamaran'], [1, 2]) ? 'badge-warning' : 
                                            (in_array($l['id_status_lamaran'], [14, 15, 16]) ? 'badge-success' : 
                                            (in_array($l['id_status_lamaran'], [17]) ? 'badge-danger' : 'badge-info')) ?>">
                                        <?= htmlspecialchars($l['nama_status'] ?? 'Lamaran Dikirim') ?>
                                    </span>
                                </td>
                                <td><?= date('d-m-Y', strtotime($l['tanggal_lamaran'])) ?></td>
                                <td>
                                    <a href="detail_lamaran.php?id=<?= $l['id_lamaran'] ?>" class="btn btn-outline btn-sm">Detail</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<!-- ============================================================ -->
<!-- WIDGET KONTAK CEPAT -->
<!-- ============================================================ -->
<div class="card">
    <div class="card-header">
        <h3>📞 Kontak Cepat</h3>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="https://wa.me/<?= WA_SDI ?>" target="_blank" class="btn btn-success" style="display:inline-flex;align-items:center;gap:8px;">
            💬 WhatsApp SDI
        </a>
        <a href="mailto:<?= EMAIL_SDI ?>" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:8px;">
            📧 Email SDI
        </a>
        <a href="tel:<?= TELP_SDI ?>" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:8px;">
            📞 Telepon SDI
        </a>
    </div>
</div>
    <!-- ============================================================ -->
    <!-- FOOTER -->
    <!-- ============================================================ -->
    <div style="text-align:center;padding:20px 0;color:#6b7280;font-size:13px;border-top:1px solid #e2e8f0;margin-top:20px;">
        &copy; <?= date('Y') ?> SDI System - Universitas Muhammadiyah Banjarmasin
        <br>
        <span style="font-size:11px;">Dashboard Admin - <?= htmlspecialchars($role_name) ?></span>
    </div>

</div>
</body>
</html>