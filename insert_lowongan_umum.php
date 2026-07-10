<?php
require_once 'includes/config.php';

echo "<h1>Cek Data Sebelum Insert Lamaran</h1>";

// 1. Cek data pelamar
echo "<h2>Data Pelamar</h2>";
$pelamar = $pdo->query("
    SELECT p.id_pelamar, u.nama_lengkap, u.email 
    FROM pelamar p
    JOIN users u ON p.id_user = u.id_user
")->fetchAll();

if (empty($pelamar)) {
    echo "<p style='color:red;'>❌ TIDAK ADA DATA PELAMAR! Silakan tambahkan pelamar terlebih dahulu.</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID Pelamar</th><th>Nama</th><th>Email</th></tr>";
    foreach ($pelamar as $p) {
        echo "<tr><td>{$p['id_pelamar']}</td><td>{$p['nama_lengkap']}</td><td>{$p['email']}</td></tr>";
    }
    echo "</table>";
}

echo "<br>";

// 2. Cek data lowongan
echo "<h2>Data Lowongan</h2>";
$lowongan = $pdo->query("
    SELECT id_lowongan, nama_lowongan, status 
    FROM lowongan
")->fetchAll();

if (empty($lowongan)) {
    echo "<p style='color:red;'>❌ TIDAK ADA DATA LOWONGAN! Silakan tambahkan lowongan terlebih dahulu.</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID Lowongan</th><th>Nama Lowongan</th><th>Status</th></tr>";
    foreach ($lowongan as $l) {
        echo "<tr><td>{$l['id_lowongan']}</td><td>{$l['nama_lowongan']}</td><td>{$l['status']}</td></tr>";
    }
    echo "</table>";
}

echo "<br>";

// 3. Cek data master_status_lamaran
echo "<h2>Data Master Status Lamaran</h2>";
$status = $pdo->query("
    SELECT id_master_status_lamaran, nama_status 
    FROM master_status_lamaran
")->fetchAll();

if (empty($status)) {
    echo "<p style='color:red;'>❌ TIDAK ADA DATA STATUS LAMARAN!</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID Status</th><th>Nama Status</th></tr>";
    foreach ($status as $s) {
        echo "<tr><td>{$s['id_master_status_lamaran']}</td><td>{$s['nama_status']}</td></tr>";
    }
    echo "</table>";
}

echo "<br>";

// 4. Rekomendasi
echo "<h2>Rekomendasi Insert Lamaran</h2>";

if (!empty($pelamar) && !empty($lowongan) && !empty($status)) {
    echo "<p style='color:green;'>✅ Semua data tersedia. Silakan insert lamaran dengan ID yang valid.</p>";
    echo "<p>Gunakan ID berikut untuk insert:</p>";
    echo "<ul>";
    echo "<li><strong>ID Pelamar:</strong> " . implode(', ', array_column($pelamar, 'id_pelamar')) . "</li>";
    echo "<li><strong>ID Lowongan:</strong> " . implode(', ', array_column($lowongan, 'id_lowongan')) . "</li>";
    echo "<li><strong>ID Status:</strong> " . implode(', ', array_column($status, 'id_master_status_lamaran')) . "</li>";
    echo "</ul>";
} else {
    echo "<p style='color:red;'>❌ Ada data yang kurang. Lengkapi data terlebih dahulu.</p>";
}
?>