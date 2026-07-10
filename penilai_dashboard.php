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

$role_name = $user['nama_role'];

// ============================================================
// CEK STRUKTUR TABEL NILAI_TES
// ============================================================
// Cek apakah tabel nilai_tes ada
$table_check = $pdo->query("SHOW TABLES LIKE 'nilai_tes'");
$table_exists = $table_check->rowCount() > 0;

if ($table_exists) {
    // Cek apakah kolom id_lamaran ada
    $col_check = $pdo->query("SHOW COLUMNS FROM nilai_tes LIKE 'id_lamaran'");
    $has_id_lamaran = $col_check->rowCount() > 0;
    
    if (!$has_id_lamaran) {
        // Tambahkan kolom id_lamaran
        $pdo->exec("ALTER TABLE nilai_tes ADD COLUMN id_lamaran INT NOT NULL AFTER id_nilai");
    }
}

// ============================================================
// STATISTIK PENILAI
// ============================================================

// Total lamaran yang perlu dinilai (status 4-8)
$perlu_dinilai = $pdo->query("
    SELECT COUNT(*) FROM lamaran WHERE id_status_lamaran IN (4, 5, 6, 7, 8)
")->fetchColumn();

// Total lamaran yang sudah dinilai (ada di nilai_tes)
$sudah_dinilai = 0;
if ($table_exists) {
    $sudah_dinilai = $pdo->query("
        SELECT COUNT(DISTINCT id_lamaran) FROM nilai_tes
    ")->fetchColumn();
}

// Total nilai tes yang sudah diinput
$total_nilai = 0;
if ($table_exists) {
    $total_nilai = $pdo->query("
        SELECT COUNT(*) FROM nilai_tes
    ")->fetchColumn();
}

// ============================================================
// DATA LAMARAN PER STATUS TES
// ============================================================

// 1. Tes Potensi Akademik (status 4) - id_tes = 1
$tpa = $pdo->query("
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
    WHERE lm.id_status_lamaran = 4
    ORDER BY lm.tanggal_lamaran ASC
    LIMIT 20
")->fetchAll();

// 2. Tes Psikotes (status 5) - id_tes = 2
$psikotes = $pdo->query("
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
    WHERE lm.id_status_lamaran = 5
    ORDER BY lm.tanggal_lamaran ASC
    LIMIT 20
")->fetchAll();

// 3. Tes Bahasa Inggris/TOEFL (status 6) - id_tes = 3
$toefl = $pdo->query("
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
    WHERE lm.id_status_lamaran = 6
    ORDER BY lm.tanggal_lamaran ASC
    LIMIT 20
")->fetchAll();

// 4. Tes Keterampilan/Keahlian (status 7) - id_tes = 4
$keterampilan = $pdo->query("
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
    WHERE lm.id_status_lamaran = 7
    ORDER BY lm.tanggal_lamaran ASC
    LIMIT 20
")->fetchAll();

// 5. Wawancara (status 8) - id_tes = 5
$wawancara = $pdo->query("
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
    WHERE lm.id_status_lamaran = 8
    ORDER BY lm.tanggal_lamaran ASC
    LIMIT 20
")->fetchAll();

// ============================================================
// DATA NILAI TERBARU
// ============================================================
$nilai_terbaru = [];
if ($table_exists) {
    $nilai_terbaru = $pdo->query("
        SELECT 
            nt.*,
            jt.nama_tes,
            u.nama_lengkap as pelamar_nama,
            l.nama_lowongan
        FROM nilai_tes nt
        JOIN lamaran lm ON nt.id_lamaran = lm.id_lamaran
        JOIN pelamar p ON lm.id_pelamar = p.id_pelamar
        JOIN users u ON p.id_user = u.id_user
        JOIN jenis_tes jt ON nt.id_tes = jt.id_tes
        LEFT JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
        ORDER BY nt.tanggal_input DESC
        LIMIT 10
    ")->fetchAll();
}

include '_layout_top.php';
?>

<!-- ============================================================ -->
<!-- HEADER -->
<!-- ============================================================ -->
<div class="topline">
    <div>
        <h1>📝 Dashboard <span>Penilai</span></h1>
        <p class="hint">
            Selamat datang, <strong><?= htmlspecialchars($user['nama_lengkap']) ?></strong> 
            (<?= htmlspecialchars($role_name) ?>)
        </p>
    </div>
    <div class="topline-right">
        <span class="badge badge-primary">Role: Penilai</span>
        <span class="badge badge-success">Status: Aktif</span>
        <span class="badge badge-info">Perlu Dinilai: <?= number_format($perlu_dinilai) ?></span>
        <a href="../logout.php" class="btn btn-danger btn-sm">🚪 Logout</a>
    </div>
</div>

<!-- ============================================================ -->
<!-- STATISTIK -->
<!-- ============================================================ -->
<div class="metric-grid">
    <div class="metric-card metric-warning">
        <div class="metric-icon">📝</div>
        <div class="metric-content">
            <span class="metric-label">Perlu Dinilai</span>
            <span class="metric-value"><?= number_format($perlu_dinilai) ?></span>
            <span class="metric-change">⏳ Menunggu</span>
        </div>
    </div>
    <div class="metric-card metric-success">
        <div class="metric-icon">✅</div>
        <div class="metric-content">
            <span class="metric-label">Sudah Dinilai</span>
            <span class="metric-value"><?= number_format($sudah_dinilai) ?></span>
            <span class="metric-change">✔️ Selesai</span>
        </div>
    </div>
    <div class="metric-card metric-info">
        <div class="metric-icon">📊</div>
        <div class="metric-content">
            <span class="metric-label">Total Nilai</span>
            <span class="metric-value"><?= number_format($total_nilai) ?></span>
            <span class="metric-change">📋 Tersimpan</span>
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
        <a href="penilai_dashboard.php" class="btn btn-primary">📊 Dashboard</a>
        <a href="penilai_input_nilai.php" class="btn btn-success">📝 Input Nilai</a>
        <a href="penilai_hasil.php" class="btn btn-outline">📊 Hasil Penilaian</a>
        <a href="../logout.php" class="btn btn-danger">🚪 Logout</a>
    </div>
</div>

<!-- ============================================================ -->
<!-- GRID 2 KOLOM -->
<!-- ============================================================ -->
<div class="grid-2">

    <!-- KOLOM KIRI: TES POTENSI AKADEMIK -->
    <div class="card">
        <h3>📚 Tes Potensi Akademik</h3>
        <p class="hint">Pelamar yang perlu dinilai TPA.</p>
        
        <?php if(empty($tpa)): ?>
            <p style="color:green;padding:20px 0;text-align:center;">✅ Tidak ada pelamar perlu dinilai TPA.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Lowongan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($tpa as $l): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($l['nama_lengkap']) ?></strong>
                                    <br><span class="text-muted"><?= htmlspecialchars($l['email']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($l['nama_lowongan'] ?? '-') ?></td>
                                <td>
                                    <a href="penilai_input_nilai.php?id=<?= $l['id_lamaran'] ?>&tes=1" class="btn btn-primary btn-sm">📝 Input</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- KOLOM KANAN: TES PSIKOTES -->
    <div class="card">
        <h3>🧠 Tes Psikotes</h3>
        <p class="hint">Pelamar yang perlu dinilai Psikotes.</p>
        
        <?php if(empty($psikotes)): ?>
            <p style="color:green;padding:20px 0;text-align:center;">✅ Tidak ada pelamar perlu dinilai Psikotes.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Lowongan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($psikotes as $l): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($l['nama_lengkap']) ?></strong>
                                    <br><span class="text-muted"><?= htmlspecialchars($l['email']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($l['nama_lowongan'] ?? '-') ?></td>
                                <td>
                                    <a href="penilai_input_nilai.php?id=<?= $l['id_lamaran'] ?>&tes=2" class="btn btn-primary btn-sm">📝 Input</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- KOLOM KIRI: TES BAHASA INGGRIS/TOEFL -->
    <div class="card">
        <h3>🌍 Tes Bahasa Inggris/TOEFL</h3>
        <p class="hint">Pelamar yang perlu dinilai TOEFL (khusus Dosen).</p>
        
        <?php if(empty($toefl)): ?>
            <p style="color:green;padding:20px 0;text-align:center;">✅ Tidak ada pelamar perlu dinilai TOEFL.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Lowongan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($toefl as $l): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($l['nama_lengkap']) ?></strong>
                                    <br><span class="text-muted"><?= htmlspecialchars($l['email']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($l['nama_lowongan'] ?? '-') ?></td>
                                <td>
                                    <a href="penilai_input_nilai.php?id=<?= $l['id_lamaran'] ?>&tes=3" class="btn btn-primary btn-sm">📝 Input</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- KOLOM KANAN: TES KETERAMPILAN/KEAHLIAN -->
    <div class="card">
        <h3>🔧 Tes Keterampilan/Keahlian</h3>
        <p class="hint">Pelamar yang perlu dinilai keterampilan (khusus Tendik).</p>
        
        <?php if(empty($keterampilan)): ?>
            <p style="color:green;padding:20px 0;text-align:center;">✅ Tidak ada pelamar perlu dinilai keterampilan.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Lowongan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($keterampilan as $l): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($l['nama_lengkap']) ?></strong>
                                    <br><span class="text-muted"><?= htmlspecialchars($l['email']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($l['nama_lowongan'] ?? '-') ?></td>
                                <td>
                                    <a href="penilai_input_nilai.php?id=<?= $l['id_lamaran'] ?>&tes=4" class="btn btn-primary btn-sm">📝 Input</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- WAWANCARA -->
<!-- ============================================================ -->
<div class="card">
    <h3>💬 Wawancara</h3>
    <p class="hint">Pelamar yang perlu diwawancarai.</p>
    
    <?php if(empty($wawancara)): ?>
        <p style="color:green;padding:20px 0;text-align:center;">✅ Tidak ada pelamar perlu diwawancarai.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Lowongan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($wawancara as $l): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($l['nama_lengkap']) ?></strong>
                                <br><span class="text-muted"><?= htmlspecialchars($l['email']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($l['nama_lowongan'] ?? '-') ?></td>
                            <td>
                                <a href="penilai_input_nilai.php?id=<?= $l['id_lamaran'] ?>&tes=5" class="btn btn-primary btn-sm">📝 Input</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- NILAI TERBARU -->
<!-- ============================================================ -->
<div class="card">
    <div class="card-header">
        <h3>📋 Nilai Terbaru</h3>
        <span class="badge badge-secondary">10 Terakhir</span>
    </div>
    <p class="hint">10 nilai tes terakhir yang diinput.</p>
    
    <?php if(empty($nilai_terbaru)): ?>
        <p style="color:#999;padding:20px 0;text-align:center;">Belum ada nilai yang diinput.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Pelamar</th>
                        <th>Jenis Tes</th>
                        <th>Nilai</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($nilai_terbaru as $n): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($n['pelamar_nama']) ?></strong>
                                <br><span class="text-muted"><?= htmlspecialchars($n['nama_lowongan'] ?? '-') ?></span>
                            </td>
                            <td><?= htmlspecialchars($n['nama_tes']) ?></td>
                            <td>
                                <span class="badge 
                                    <?= $n['nilai'] >= 80 ? 'badge-success' : 
                                        ($n['nilai'] >= 60 ? 'badge-warning' : 'badge-danger') ?>">
                                    <?= number_format($n['nilai'], 2) ?>
                                </span>
                            </td>
                            <td><?= date('d-m-Y H:i', strtotime($n['tanggal_input'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '_layout_bottom.php'; ?>