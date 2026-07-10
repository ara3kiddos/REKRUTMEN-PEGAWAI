<?php
require_once '../includes/config.php';

echo "<h1>Insert Data Lamaran</h1>";

// ============================================================
// AMBIL DATA PELAMAR DAN LOWONGAN
// ============================================================

// Ambil semua pelamar
$pelamarList = $pdo->query("
    SELECT p.id_pelamar, u.nama_lengkap, u.email 
    FROM pelamar p
    JOIN users u ON p.id_user = u.id_user
    ORDER BY p.id_pelamar
")->fetchAll();

// Ambil semua lowongan aktif
$lowonganList = $pdo->query("
    SELECT id_lowongan, nama_lowongan 
    FROM lowongan 
    WHERE status = 'Aktif'
    ORDER BY id_lowongan
")->fetchAll();

// ============================================================
// TAMPILKAN DATA YANG TERSEDIA
// ============================================================

echo "<h2>Data Pelamar Tersedia</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
echo "<tr><th>ID</th><th>Nama</th><th>Email</th></tr>";
foreach ($pelamarList as $p) {
    echo "<tr>";
    echo "<td>{$p['id_pelamar']}</td>";
    echo "<td>{$p['nama_lengkap']}</td>";
    echo "<td>{$p['email']}</td>";
    echo "</tr>";
}
echo "</table>";
echo "<br>";

echo "<h2>Data Lowongan Aktif</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
echo "<tr><th>ID</th><th>Nama Lowongan</th></tr>";
foreach ($lowonganList as $l) {
    echo "<tr>";
    echo "<td>{$l['id_lowongan']}</td>";
    echo "<td>{$l['nama_lowongan']}</td>";
    echo "</tr>";
}
echo "</table>";
echo "<br>";

// ============================================================
// CEK APAKAH SUDAH ADA DATA LAMARAN
// ============================================================

$stmt = $pdo->query("SELECT COUNT(*) FROM lamaran");
$totalLamaran = (int)$stmt->fetchColumn();

if ($totalLamaran > 0) {
    echo "<p style='color:orange;'>⚠️ Sudah ada $totalLamaran data lamaran. Skip insert.</p>";
    
    // Tampilkan data lamaran yang sudah ada
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
    
    echo "<h2>Data Lamaran Yang Sudah Ada</h2>";
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Pelamar</th><th>Lowongan</th><th>Tanggal</th><th>Status</th><th>Catatan</th></tr>";
    foreach ($lamaranList as $lm) {
        echo "<tr>";
        echo "<td>{$lm['id_lamaran']}</td>";
        echo "<td>{$lm['nama_lengkap']}</td>";
        echo "<td>{$lm['nama_lowongan']}</td>";
        echo "<td>{$lm['tanggal_lamaran']}</td>";
        echo "<td>{$lm['nama_status']}</td>";
        echo "<td>" . htmlspecialchars($lm['catatan'] ?? '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} else {
    // ============================================================
    // INSERT DATA LAMARAN CONTOH
    // ============================================================
    
    // Pastikan ada data pelamar dan lowongan
    if (empty($pelamarList)) {
        echo "<p style='color:red;'>❌ Tidak ada data pelamar. Silakan tambahkan pelamar terlebih dahulu.</p>";
        exit;
    }
    
    if (empty($lowonganList)) {
        echo "<p style='color:red;'>❌ Tidak ada data lowongan aktif. Silakan tambahkan lowongan terlebih dahulu.</p>";
        exit;
    }
    
    // Mapping lowongan berdasarkan nama
    $lowonganMap = [];
    foreach ($lowonganList as $l) {
        $lowonganMap[$l['nama_lowongan']] = $l['id_lowongan'];
    }
    
    // Mapping pelamar berdasarkan nama
    $pelamarMap = [];
    foreach ($pelamarList as $p) {
        $pelamarMap[$p['nama_lengkap']] = $p['id_pelamar'];
    }
    
    // Data lamaran yang akan diinsert
    $lamaranData = [
        [
            'pelamar' => 'Pelamar 1',  // Ganti dengan nama pelamar yang ada
            'lowongan' => 'Dosen Tetap Informatika',
            'status' => 1,  // Lamaran Dikirim
            'catatan' => 'Lamaran baru untuk posisi Dosen Informatika'
        ],
        [
            'pelamar' => 'Pelamar 2',  // Ganti dengan nama pelamar yang ada
            'lowongan' => 'Dosen Tetap Manajemen',
            'status' => 1,
            'catatan' => 'Lamaran baru untuk posisi Dosen Manajemen'
        ],
        [
            'pelamar' => 'Pelamar 3',  // Ganti dengan nama pelamar yang ada
            'lowongan' => 'Staff Administrasi Akademik',
            'status' => 1,
            'catatan' => 'Lamaran baru untuk posisi Staff Administrasi Akademik'
        ]
    ];
    
    $success = 0;
    $failed = 0;
    
    foreach ($lamaranData as $data) {
        $pelamarId = $pelamarMap[$data['pelamar']] ?? null;
        $lowonganId = $lowonganMap[$data['lowongan']] ?? null;
        
        if (!$pelamarId) {
            echo "<p style='color:red;'>❌ Pelamar '{$data['pelamar']}' tidak ditemukan.</p>";
            $failed++;
            continue;
        }
        
        if (!$lowonganId) {
            echo "<p style='color:red;'>❌ Lowongan '{$data['lowongan']}' tidak ditemukan.</p>";
            $failed++;
            continue;
        }
        
        // Cek apakah pelamar sudah punya lamaran
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lamaran WHERE id_pelamar = ?");
        $stmt->execute([$pelamarId]);
        if ((int)$stmt->fetchColumn() > 0) {
            echo "<p style='color:orange;'>⏭ Pelamar '{$data['pelamar']}' sudah memiliki lamaran. Skip.</p>";
            $failed++;
            continue;
        }
        
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
            
            $stmt->execute([
                $pelamarId,
                $lowonganId,
                $data['status'],
                $data['catatan']
            ]);
            
            echo "<p style='color:green;'>✅ Lamaran untuk <strong>{$data['pelamar']}</strong> - {$data['lowongan']} berhasil dibuat.</p>";
            $success++;
        } catch (PDOException $e) {
            echo "<p style='color:red;'>❌ Gagal insert lamaran: " . $e->getMessage() . "</p>";
            $failed++;
        }
    }
    
    echo "<hr>";
    echo "<h2>Ringkasan</h2>";
    echo "<p>✅ Berhasil: $success</p>";
    echo "<p>❌ Gagal/Skip: $failed</p>";
    echo "<p>📊 Total Lamaran: " . $pdo->query("SELECT COUNT(*) FROM lamaran")->fetchColumn() . "</p>";
}

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