<?php
require_once 'includes/config.php';

echo "<h1>📥 Import Data Lamaran</h1>";

// ============================================================
// CEK FILE CSV
// ============================================================

$csvFile = 'datanew.csv'; // Simpan file CSV di root folder

if (!file_exists($csvFile)) {
    echo "<p style='color:red;'>❌ File <strong>datanew.csv</strong> tidak ditemukan!</p>";
    echo "<p>Silakan konversi file Excel ke CSV dan simpan sebagai <strong>datanew.csv</strong> di root folder.</p>";
    echo "<p><strong>Langkah:</strong></p>";
    echo "<ol>
            <li>Buka <strong>datanew.xlsx</strong> di Excel</li>
            <li>File → Save As → Pilih <strong>CSV (Comma delimited) (*.csv)</strong></li>
            <li>Simpan dengan nama <strong>datanew.csv</strong> di folder project</li>
          </ol>";
    exit;
}

// ============================================================
// BACA CSV & INSERT
// ============================================================

echo "<h2>📋 Proses Import Data</h2>";

$handle = fopen($csvFile, 'r');
if ($handle === false) {
    die("❌ Gagal membuka file CSV.");
}

// Deteksi delimiter
$firstLine = fgets($handle);
rewind($handle);
$delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';

// Baca header
$headers = fgetcsv($handle, 0, $delimiter);
$headers = array_map('trim', $headers);

// Mapping header
$map = [];
foreach ($headers as $idx => $col) {
    $colLower = strtolower($col);
    if (strpos($colLower, 'nama') !== false && strpos($colLower, 'pelamar') !== false) {
        $map['nama'] = $idx;
    } elseif (strpos($colLower, 'tempat') !== false || strpos($colLower, 'ttl') !== false) {
        $map['ttl'] = $idx;
    } elseif (strpos($colLower, 'formasi') !== false || strpos($colLower, 'dosen') !== false) {
        $map['formasi'] = $idx;
    } elseif (strpos($colLower, 'pendidikan') !== false) {
        $map['pendidikan'] = $idx;
    } elseif (strpos($colLower, 'telp') !== false || strpos($colLower, 'hp') !== false) {
        $map['hp'] = $idx;
    } elseif (strpos($colLower, 'nik') !== false) {
        $map['nik'] = $idx;
    } elseif (strpos($colLower, 'alamat') !== false) {
        $map['alamat'] = $idx;
    }
}

// Ambil data master
$pendidikan = $pdo->query("SELECT id_pendidikan, jenjang FROM master_pendidikan")->fetchAll(PDO::FETCH_KEY_PAIR);
$agama = $pdo->query("SELECT id_agama, nama_agama FROM master_agama")->fetchAll(PDO::FETCH_KEY_PAIR);
$status = $pdo->query("SELECT id_master_status_lamaran, nama_status FROM master_status_lamaran")->fetchAll(PDO::FETCH_KEY_PAIR);

// Pastikan lowongan ada
$lowongan = [];
$stmt = $pdo->query("SELECT id_lowongan, nama_lowongan FROM lowongan");
while ($row = $stmt->fetch()) {
    $lowongan[strtoupper(trim($row['nama_lowongan']))] = $row['id_lowongan'];
}

if (!isset($lowongan['DOSEN'])) {
    $pdo->exec("INSERT INTO lowongan (nama_lowongan, minimal_pendidikan, deskripsi, status) VALUES ('Dosen', 7, 'Lowongan Dosen', 'Aktif')");
    $lowongan['DOSEN'] = $pdo->lastInsertId();
}
if (!isset($lowongan['TENAGA KEPENDIDIKAN (TENDIK)'])) {
    $pdo->exec("INSERT INTO lowongan (nama_lowongan, minimal_pendidikan, deskripsi, status) VALUES ('Tenaga Kependidikan (Tendik)', 1, 'Lowongan Tenaga Kependidikan', 'Aktif')");
    $lowongan['TENAGA KEPENDIDIKAN (TENDIK)'] = $pdo->lastInsertId();
}

// Ambil data existing untuk cek duplikat
$existingEmail = $pdo->query("SELECT email FROM users")->fetchAll(PDO::FETCH_COLUMN);
$existingNik = $pdo->query("SELECT nik FROM users")->fetchAll(PDO::FETCH_COLUMN);

// ============================================================
// MULAI IMPORT
// ============================================================

$pdo->beginTransaction();

$success = 0;
$failed = 0;
$skipped = 0;
$errors = [];

$rowNum = 0;

while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
    $rowNum++;
    
    // Skip jika jumlah kolom tidak sesuai
    if (count($row) != count($headers)) continue;
    
    // Ambil nama
    $nama = trim($row[$map['nama'] ?? 0] ?? '');
    if (empty($nama) || strlen($nama) < 3) continue;
    
    // Skip header/baris tidak valid
    $skipWords = ['REKAP', 'UNIVERSITAS', 'Keperawatan', 'Kebidanan', 'Farmasi', 'Perpustakaan', 
                  'Psikologi', 'Pendidikan', 'Teknik', 'Promosi', 'Rekap', 'PERIODE', 'No'];
    $skip = false;
    foreach ($skipWords as $word) {
        if (stripos($nama, $word) !== false) { $skip = true; break; }
    }
    if ($skip) continue;
    
    try {
        // ============================================================
        // PARSE DATA
        // ============================================================
        
        // Email
        $email = '';
        if (isset($map['hp']) && isset($row[$map['hp']])) {
            $hp = trim($row[$map['hp']]);
            if (filter_var($hp, FILTER_VALIDATE_EMAIL)) $email = $hp;
        }
        if (empty($email)) {
            $email = strtolower(str_replace([' ', ',', '.'], '', $nama)) . '@example.com';
        }
        if (in_array($email, $existingEmail)) {
            $email = $email . '.' . $rowNum;
        }
        
        // NIK
        $nik = isset($map['nik']) ? trim($row[$map['nik']]) : '';
        if (empty($nik)) $nik = 'NIK-' . date('Ymd') . '-' . $rowNum;
        if (in_array($nik, $existingNik)) $nik = $nik . '-' . $rowNum;
        
        // No HP
        $no_hp = isset($map['hp']) ? trim($row[$map['hp']]) : '';
        if (strpos($no_hp, '@') !== false) $no_hp = ''; // jika email
        if (!preg_match('/^[0-9+\-() ]+$/', $no_hp)) $no_hp = '';
        
        // TTL
        $ttl = isset($map['ttl']) ? trim($row[$map['ttl']]) : '';
        $tempat_lahir = '';
        $tanggal_lahir = '2000-01-01';
        if (!empty($ttl)) {
            $parts = explode(',', $ttl);
            $tempat_lahir = trim($parts[0] ?? '');
            if (isset($parts[1])) {
                $tgl = trim($parts[1]);
                // Parse tanggal
                $tgl = str_replace(['Mei', 'Juni', 'Juli', 'Agus', 'Sept', 'Okt', 'Nop', 'Des'], 
                                  ['May', 'June', 'July', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], $tgl);
                $tgl = str_replace(['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'], 
                                  ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'], $tgl);
                $date = date_create_from_format('d M Y', $tgl);
                if ($date) $tanggal_lahir = $date->format('Y-m-d');
            }
        }
        
        // Pendidikan
        $pendidikan_raw = isset($map['pendidikan']) ? trim($row[$map['pendidikan']]) : '';
        $pendidikan_terakhir = 1;
        if (!empty($pendidikan_raw)) {
            $raw = strtoupper($pendidikan_raw);
            if (strpos($raw, 'S3') !== false) $pendidikan_terakhir = $pendidikan['S3'] ?? 8;
            elseif (strpos($raw, 'S2') !== false) $pendidikan_terakhir = $pendidikan['S2'] ?? 7;
            elseif (strpos($raw, 'S1') !== false) $pendidikan_terakhir = $pendidikan['S1'] ?? 6;
            elseif (strpos($raw, 'D4') !== false) $pendidikan_terakhir = $pendidikan['D4'] ?? 5;
            elseif (strpos($raw, 'D3') !== false) $pendidikan_terakhir = $pendidikan['D3'] ?? 4;
            elseif (strpos($raw, 'SMA') !== false || strpos($raw, 'SMK') !== false) {
                $pendidikan_terakhir = $pendidikan['SMA/SMK'] ?? 1;
            }
        }
        
        // Jenis kelamin
        $jenis_kelamin = 'L';
        $female = ['Ibu', 'Ny', 'Siti', 'Dewi', 'Pratiwi', 'Rahayu', 'Wulandari', 'Kusuma', 'Ayu', 'Sri', 'Rina', 'Tuti', 'Nur', 'Sari', 'Lestari', 'Fitri', 'Rini', 'Yuli', 'Ani', 'Nia'];
        foreach ($female as $ind) {
            if (stripos($nama, $ind) !== false) { $jenis_kelamin = 'P'; break; }
        }
        
        // Umur
        $umur = 0;
        if ($tanggal_lahir && $tanggal_lahir !== '2000-01-01') {
            $birth = new DateTime($tanggal_lahir);
            $today = new DateTime();
            $umur = $birth->diff($today)->y;
        }
        
        // Formasi (Dosen/Tendik)
        $is_dosen = false;
        $formasi = isset($map['formasi']) ? trim($row[$map['formasi']]) : '';
        if (strpos($formasi, '√') !== false || strpos(strtoupper($formasi), 'DOSEN') !== false) {
            $is_dosen = true;
        }
        // Jika tidak terdeteksi, cek dari pendidikan
        if (!$is_dosen && $pendidikan_terakhir >= 7) $is_dosen = true;
        
        $lowongan_nama = $is_dosen ? 'Dosen' : 'Tenaga Kependidikan (Tendik)';
        $id_lowongan = $lowongan[strtoupper($lowongan_nama)];
        
        // Alamat
        $alamat = isset($map['alamat']) ? trim($row[$map['alamat']]) : '';
        
        // Agama (default Islam)
        $id_agama = 1;
        foreach ($agama as $id => $nama_agama) {
            if (strtoupper($nama_agama) == 'ISLAM') { $id_agama = $id; break; }
        }
        
        // ============================================================
        // INSERT KE DATABASE
        // ============================================================
        
        $password = password_hash('password123', PASSWORD_DEFAULT);
        
        // INSERT users
        $stmt = $pdo->prepare("
            INSERT INTO users (id_role, nik, nama_lengkap, email, password, no_hp, alamat, status_aktif) 
            VALUES (5, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$nik, $nama, $email, $password, $no_hp, $alamat]);
        $id_user = $pdo->lastInsertId();
        
        // INSERT pelamar
        $stmt = $pdo->prepare("
            INSERT INTO pelamar (id_user, tempat_lahir, tanggal_lahir, jenis_kelamin, id_agama, umur, pendidikan_terakhir) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$id_user, $tempat_lahir, $tanggal_lahir, $jenis_kelamin, $id_agama, $umur, $pendidikan_terakhir]);
        $id_pelamar = $pdo->lastInsertId();
        
        // INSERT lamaran
        $id_status = 1; // Lamaran Dikirim
        $catatan = "Import CSV - " . ($is_dosen ? 'Dosen' : 'Tendik');
        
        $stmt = $pdo->prepare("
            INSERT INTO lamaran (id_pelamar, id_lowongan, tanggal_lamaran, id_status_lamaran, catatan) 
            VALUES (?, ?, NOW(), ?, ?)
        ");
        $stmt->execute([$id_pelamar, $id_lowongan, $id_status, $catatan]);
        
        $success++;
        $existingEmail[] = $email;
        $existingNik[] = $nik;
        
        echo "<p style='color:green;'>✅ [$success] $nama - $lowongan_nama</p>";
        
    } catch (Exception $e) {
        $failed++;
        $errors[] = "Baris $rowNum: " . $e->getMessage();
        echo "<p style='color:red;'>❌ Baris $rowNum: Gagal import $nama - " . $e->getMessage() . "</p>";
    }
}

fclose($handle);

// ============================================================
// COMMIT / ROLLBACK
// ============================================================

if ($failed > 0) {
    $pdo->rollBack();
    echo "<h2 style='color:red;'>❌ Import GAGAL!</h2>";
    echo "<p>Terjadi $failed error. Semua data di-rollback.</p>";
    if (!empty($errors)) {
        echo "<h4>Error Details:</h4><ul>";
        foreach ($errors as $err) echo "<li>$err</li>";
        echo "</ul>";
    }
} else {
    $pdo->commit();
    echo "<h2 style='color:green;'>✅ Import BERHASIL!</h2>";
    echo "<p>Berhasil mengimport <strong>$success</strong> data lamaran.</p>";
}

// Tampilkan statistik
echo "<hr>";
echo "<h3>📊 Statistik Data</h3>";
$total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE id_role = 5")->fetchColumn();
$total_pelamar = $pdo->query("SELECT COUNT(*) FROM pelamar")->fetchColumn();
$total_lamaran = $pdo->query("SELECT COUNT(*) FROM lamaran")->fetchColumn();
echo "<ul>
        <li>Total Pelamar: <strong>$total_users</strong></li>
        <li>Data Pelamar: <strong>$total_pelamar</strong></li>
        <li>Lamaran: <strong>$total_lamaran</strong></li>
      </ul>";
?>