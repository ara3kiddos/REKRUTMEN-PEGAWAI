<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Lamaran - Rekrutmen UMB</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body {
            background: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        .navbar {
            background: #1a1a2e;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .navbar .brand {
            color: white;
            font-weight: 700;
            font-size: 18px;
            text-decoration: none;
        }
        .navbar .brand span {
            color: #93c5fd;
            font-weight: 400;
            font-size: 13px;
            margin-left: 10px;
        }
        .navbar .nav-links {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .navbar .nav-links a {
            color: #cbd5e1;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.2s;
        }
        .navbar .nav-links a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .navbar .nav-links .btn-logout {
            color: #f87171;
        }
        .navbar .nav-links .btn-logout:hover {
            background: rgba(248, 113, 113, 0.1);
        }
        .user-badge {
            color: white;
            padding: 6px 12px;
            background: rgba(255,255,255,0.1);
            border-radius: 6px;
            font-size: 13px;
        }
    </style>
</head>
<body>
<nav class="navbar">
    <a href="dashboard.php" class="brand">
        🏛️ Rekrutmen UMB
        <span>Pelamar</span>
    </a>
    <div class="nav-links">
        <span class="user-badge">👤 <?= htmlspecialchars($user['nama_lengkap'] ?? '') ?></span>
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="lamaran_baru.php" class="btn-logout">📝 Lamaran Baru</a>
        <a href="logout.php" class="btn-logout">🚪 Logout</a>
    </div>
</nav>
<div class="container"></div>