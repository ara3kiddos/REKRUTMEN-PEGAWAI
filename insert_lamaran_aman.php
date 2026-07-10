<?php
require_once 'includes/config.php';

echo "<h1>Insert Lamaran (Dengan Validasi)</h1>";

// ============================================================
// AMBIL DATA YANG TERSEDIA
// ============================================================

// Ambil ID pelamar yang tersedia
$pelamarIds = $pdo->query("SELECT id_pelamar FROM pelamar")->fetchAll(PDO::FETCH_COLUMN);

// Ambil ID lowongan yang tersedia
$lowonganIds = $pdo->query("SELECT id_lowongan FROM lowongan WHERE status = 'Aktif'")->fetchAll(PDO::FETCH_COLUMN);

// Ambil ID status yang tersedia
$statusIds = $pdo->query("SELECT id_master_status_lamaran FROM master_status_lamaran")->fetchAll(PDO::FETCH_COLUMN);

// ============================================================
// CEK KETERSEDIAAN DATA
// ============================================================

echo "<h2>Data Tersedia</h2>";
echo "<p>ID Pelamar: " . (empty($pelamarIds) ? '<span style="color:red;">Tidak ada</span>' : implode(', ', $pelamarIds)) . "</p>";
echo "<p>ID Lowongan: " . (empty($lowonganIds) ? '<span style="color:red;">Tidak ada</span>' : implode(', ', $lowonganIds)) . "</p>";
echo "<p>ID Status: " . (empty($statusIds) ? '<span style="color:red;">Tidak ada</span>' : implode(', ', $statusIds)) . "</p>";
echo "<br>";

// ============================================================
// CEK APAKAH SUDAH ADA LAMARAN
// ============================================================

$stmt = $pdo->query("SELECT COUNT(*) FROM lamaran");
$totalLamaran = (int)$stmt->fetchColumn();

if ($totalLamaran > 0) {
    echo "<p style='color:orange;'>⚠️ Sudah ada $totalLamaran data lamaran.</p>";
}

// ============================================================
// INSERT LAMARAN (JIKA DATA TERSEDIA)
// ============================================================

if (empty($pelamarIds) || empty($lowonganIds) || empty($statusIds)) {
    echo "<p style='color:red;'>❌ Tidak bisa insert karena ada data yang kosong.</p>";
    echo "<p>Silakan lengkapi data terlebih dahulu:</p>";
    echo "<ul>";
    if (empty($pelamarIds)) echo "<li>Tambahkan data ke tabel <strong>pelamar</strong></li>";
    if (empty($lowonganIds)) echo "<li>Tambahkan data ke tabel <strong>lowongan</strong></li>";
    if (empty($statusIds)) echo "<li>Tambahkan data ke tabel <strong>master_status_lamaran</strong></li>";
    echo "</ul>";
    exit;
}

// ============================================================
// INSERT LAMARAN
// ============================================================

$success = 0;
$failed = 0;
$skipped = 0;

// Ambil detail pelamar dan lowongan
$pelamarData = $pdo->query("
    SELECT p.id_pelamar, u.nama_lengkap 
    FROM pelamar p
    JOIN users u ON p.id_user = u.id_user
")->fetchAll();

$lowonganData = $pdo->query("
    SELECT id_lowongan, nama_lowongan 
    FROM lowongan 
    WHERE status = 'Aktif'
")->fetchAll();

foreach ($pelamarData as $p) {
    // Cek apakah pelamar sudah punya lamaran
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lamaran WHERE id_pelamar = ?");
    $stmt->execute([$p['id_pelamar']]);
    if ((int)$stmt->fetchColumn() > 0) {
        echo "<p style='color:orange;'>⏭ Pelamar <strong>{$p['nama_lengkap']}</strong> sudah memiliki lamaran. Skip.</p>";
        $skipped++;
        continue;
    }
    
    // Pilih lowongan acak (dosen atau tendik)
    $lowongan = $lowonganData[array_rand($lowonganData)];
    $statusId = $statusIds[0]; // Gunakan status pertama (Lamaran Dikirim)
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO lamaran (
                id_pelamar,
                id_lowongan,
                tanggal_lamaran,
                id_status_lamaran,
                catatan
            ) VALUES (?, ?, NOW(), ?, ?)
        ");
        
        $catatan = "Lamaran untuk posisi: " . $lowongan['nama_lowongan'];
        $stmt->execute([
            $p['id_pelamar'],
            $lowongan['id_lowongan'],
            $statusId,
            $catatan
        ]);
        
        echo "<p style='color:green;'>✅ Lamaran untuk <strong>{$p['nama_lengkap']}</strong> - {$lowongan['nama_lowongan']} berhasil dibuat.</p>";
        $success++;
    } catch (PDOException $e) {
        echo "<p style='color:red;'>❌ Gagal insert lamaran untuk {$p['nama_lengkap']}: " . $e->getMessage() . "</p>";
        $failed++;
    }
}

// ============================================================
// RINGKASAN
// ============================================================

echo "<hr>";
echo "<h2>Ringkasan</h2>";
echo "<p>✅ Berhasil: $success</p>";
echo "<p>⏭ Skip (sudah ada lamaran): $skipped</p>";
echo "<p>❌ Gagal: $failed</p>";
echo "<p>📊 Total Lamaran: " . $pdo->query("SELECT COUNT(*) FROM lamaran")->fetchColumn() . "</p>";

// ============================================================
// TAMPILKAN SEMUA LAMARAN
// ============================================================

echo "<h2>Daftar Semua Lamaran</h2>";
$lamaranList = $pdo->query("
    SELECT 
        lm.id_lamaran,
        u.nama_lengkap,
        l.nama_lowongan,
        lm.tanggal_lamaran,
        ms.nama_status,
        lm.catatan
    FROM lamaran lm
    JOIN pelamar p ON lm.id_pelamar = p.id_pelamar
    JOIN users u ON p.id_user = u.id_user
    JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
    JOIN master_status_lamaran ms ON lm.id_status_lamaran = ms.id_master_status_lamaran
    ORDER BY lm.id_lamaran
")->fetchAll();

if (empty($lamaranList)) {
    echo "<p style='color:orange;'>Belum ada data lamaran.</p>";
} else {
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
    echo "<tr style='background:#f0f0f0;'>";
    echo "<th>ID</th><th>Pelamar</th><th>Lowongan</th><th>Tanggal</th><th>Status</th><th>Catatan</th>";
    echo "</tr>";
    
    foreach ($lamaranList as $lm) {
        echo "<tr>";
        echo "<td>{$lm['id_lamaran']}</td>";
        echo "<td><strong>{$lm['nama_lengkap']}</strong></td>";
        echo "<td>{$lm['nama_lowongan']}</td>";
        echo "<td>" . date('d-m-Y', strtotime($lm['tanggal_lamaran'])) . "</td>";
        echo "<td><span style='background:#dbeafe;color:#1d4ed8;padding:2px 10px;border-radius:12px;font-size:12px;'>{$lm['nama_status']}</span></td>";
        echo "<td>" . htmlspecialchars($lm['catatan'] ?? '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>