<?php
// ============================================================
// _layout_top.php - Header Admin untuk Semua Role
// ============================================================

// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan user sudah login
if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit;
}

// Ambil data user jika belum ada
if (!isset($user) || empty($user)) {
    require_once __DIR__ . '/../includes/config.php';
    
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
}

$role_id = $user['id_role'] ?? 0;
$role_name = $user['nama_role'] ?? 'Unknown';

// ============================================================
// NAVIGASI BERDASARKAN ROLE (PAKAI PATH ABSOLUT)
// ============================================================

$base_url = '/reknew/admin/';

$nav_links = [];

switch ($role_id) {
    case 1: // Superuser
        $nav_links = [
            ['url' => $base_url . 'dashboard.php', 'label' => '📊 Dashboard'],
            ['url' => $base_url . 'verifikasi_lamaran.php', 'label' => '📄 Verifikasi'],
            ['url' => $base_url . 'kelola_lowongan.php', 'label' => '💼 Lowongan'],
            ['url' => $base_url . 'data_pelamar.php', 'label' => '👤 Pelamar'],
            ['url' => $base_url . 'clustering.php', 'label' => '📊 Clustering'],
            ['url' => $base_url . 'laporan.php', 'label' => '📊 Laporan'],
            ['url' => $base_url . 'rektor_dashboard.php', 'label' => '🏛️ Rektor'],
            ['url' => $base_url . 'penilai_dashboard.php', 'label' => '📝 Penilai'],
        ];
        break;
        
    case 2: // SDI
        $nav_links = [
            ['url' => $base_url . 'dashboard.php', 'label' => '📊 Dashboard'],
            ['url' => $base_url . 'verifikasi_lamaran.php', 'label' => '📄 Verifikasi'],
            ['url' => $base_url . 'kelola_lowongan.php', 'label' => '💼 Lowongan'],
            ['url' => $base_url . 'data_pelamar.php', 'label' => '👤 Pelamar'],
            ['url' => $base_url . 'laporan.php', 'label' => '📊 Laporan'],
        ];
        break;
        
    case 3: // Rektor
        $nav_links = [
            ['url' => $base_url . 'rektor_dashboard.php', 'label' => '📊 Dashboard'],
            ['url' => $base_url . 'laporan.php', 'label' => '📊 Laporan'],
        ];
        break;
        
    case 4: // Penilai
        $nav_links = [
            ['url' => $base_url . 'penilai_dashboard.php', 'label' => '📊 Dashboard'],
            ['url' => $base_url . 'penilai_input_nilai.php', 'label' => '📝 Input Nilai'],
            ['url' => $base_url . 'penilai_hasil.php', 'label' => '📊 Hasil Penilaian'],
        ];
        break;
        
    default:
        $nav_links = [
            ['url' => $base_url . 'dashboard.php', 'label' => '📊 Dashboard'],
        ];
        break;
}

// ============================================================
// DETEKSI HALAMAN AKTIF
// ============================================================
$current_page = basename($_SERVER['PHP_SELF']);

function isActive($url, $current_page) {
    return basename($url) == $current_page ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SDI System - <?= htmlspecialchars($role_name) ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* ============================================================ */
        /* ADMIN LAYOUT STYLE */
        /* ============================================================ */
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f1f5f9; 
            color: #1a1a2e; 
        }
        
        /* Container */
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        /* ============================================================ */
        /* NAVIGASI ADMIN */
        /* ============================================================ */
        .admin-nav {
            background: #0f2747;
            color: white;
            padding: 0 20px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .admin-nav .nav-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding: 12px 0;
        }
        .admin-nav .nav-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 18px;
            font-weight: 700;
            color: white;
        }
        .admin-nav .nav-brand span {
            font-size: 13px;
            font-weight: 400;
            color: #94a3b8;
        }
        .admin-nav .nav-brand .badge-role {
            background: #667eea;
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .admin-nav .nav-links {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .admin-nav .nav-links a {
            color: #cbd5e1;
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            font-weight: 500;
        }
        .admin-nav .nav-links a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .admin-nav .nav-links a.active {
            background: #667eea;
            color: white;
        }
        .admin-nav .nav-links .btn-logout {
            background: #ef4444;
            color: white;
            padding: 6px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .admin-nav .nav-links .btn-logout:hover {
            background: #dc2626;
        }
        
        /* ============================================================ */
        /* TOPLINE / HEADER HALAMAN */
        /* ============================================================ */
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
        .topline h1 span { color: #667eea; }
        .topline .hint { color: #6b7280; font-size: 14px; margin-top: 4px; }
        .topline-right { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        
        /* ============================================================ */
        /* BADGE */
        /* ============================================================ */
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
        
        /* ============================================================ */
        /* METRIC CARDS */
        /* ============================================================ */
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
        
        /* ============================================================ */
        /* CARDS */
        /* ============================================================ */
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
        
        /* ============================================================ */
        /* TABLES */
        /* ============================================================ */
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
        
        /* ============================================================ */
        /* BUTTONS */
        /* ============================================================ */
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
        .btn-lg { padding: 10px 24px; font-size: 14px; }
        
        /* ============================================================ */
        /* QUICK MENU */
        /* ============================================================ */
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
        
        /* ============================================================ */
        /* GRID */
        /* ============================================================ */
        .grid-2 { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; 
        }
        
        /* ============================================================ */
        /* ALERT */
        /* ============================================================ */
        .alert { 
            padding: 12px 16px; 
            border-radius: 8px; 
            margin-bottom: 15px; 
            font-size: 14px; 
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        
        /* ============================================================ */
        /* MISC */
        /* ============================================================ */
        .mt-10 { margin-top: 10px; }
        .mt-15 { margin-top: 15px; }
        .mt-20 { margin-top: 20px; }
        .text-center { text-align: center; }
        .text-muted { color: #6b7280; }
        .text-success { color: #10b981; }
        .text-danger { color: #ef4444; }
        .text-warning { color: #f59e0b; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { 
            display: block; 
            font-weight: 600; 
            font-size: 14px; 
            margin-bottom: 5px; 
            color: #475569; 
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; 
            padding: 10px 14px; 
            border: 1px solid #d1d5db; 
            border-radius: 8px; 
            font-size: 14px; 
            font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none; 
            border-color: #2563eb; 
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        
        /* ============================================================ */
        /* RESPONSIVE */
        /* ============================================================ */
        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
            .metric-grid { grid-template-columns: 1fr 1fr; }
            .topline { flex-direction: column; align-items: flex-start; }
            .topline-right { width: 100%; }
            .admin-nav .nav-inner { flex-direction: column; align-items: flex-start; }
            .admin-nav .nav-links { width: 100%; }
        }
        @media (max-width: 480px) {
            .metric-grid { grid-template-columns: 1fr; }
        }
        
        /* ============================================================ */
        /* TIMELINE */
        /* ============================================================ */
        .timeline {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 10px 0;
        }
        .timeline-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 6px;
            background: #f3f4f6;
        }
        .timeline-item.active {
            background: #e0f2fe;
        }
        .timeline-item.current {
            background: #dbeafe;
            border: 2px solid #2563eb;
        }
        .timeline-item .timeline-number {
            font-weight: bold;
            color: #9ca3af;
        }
        .timeline-item.active .timeline-number {
            color: #0369a1;
        }
    </style>
</head>
<body>

<!-- ============================================================ -->
<!-- NAVIGASI ADMIN -->
<!-- ============================================================ -->
<nav class="admin-nav">
    <div class="nav-inner">
        <div class="nav-brand">
            🏛️ SDI System
            <span><?= htmlspecialchars($user['nama_lengkap'] ?? '') ?></span>
            <span class="badge-role"><?= htmlspecialchars($role_name) ?></span>
        </div>
        <div class="nav-links">
            <?php foreach($nav_links as $link): ?>
                <a href="<?= $link['url'] ?>" class="<?= isActive($link['url'], $current_page) ?>">
                    <?= $link['label'] ?>
                </a>
            <?php endforeach; ?>
            <a href="/reknew/logout.php" class="btn-logout">🚪 Logout</a>
        </div>
    </div>
</nav>

<!-- ============================================================ -->
<!-- MAIN CONTENT -->
<!-- ============================================================ -->
<div class="container">