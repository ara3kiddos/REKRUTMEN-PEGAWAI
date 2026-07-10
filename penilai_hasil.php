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

// ============================================================
// AMBIL DATA NILAI
// ============================================================
$filter_tes = $_GET['tes'] ?? 'all';

$sql = "
    SELECT 
        nt.*,
        jt.nama_tes,
        u.nama_lengkap as pelamar_nama,
        u.email,
        l.nama_lowongan,
        ms.nama_status
    FROM nilai_tes nt
    JOIN lamaran lm ON nt.id_lamaran = lm.id_lamaran
    JOIN pelamar p ON lm.id_pelamar = p.id_pelamar
    JOIN users u ON p.id_user = u.id_user
    JOIN jenis_tes jt ON nt.id_tes = jt.id_tes
    LEFT JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
    LEFT JOIN master_status_lamaran ms ON lm.id_status_lamaran = ms.id_master_status_lamaran
    WHERE 1=1
";

if ($filter_tes != 'all') {
    $sql .= " AND nt.id_tes = " . intval($filter_tes);
}

$sql .= " ORDER BY nt.tanggal_input DESC";

$nilai_list = $pdo->query($sql)->fetchAll();

// Ambil daftar jenis tes untuk filter
$jenis_tes_list = $pdo->query("SELECT * FROM jenis_tes ORDER BY urutan")->fetchAll();

include '_layout_top.php';
?>

<!-- ============================================================ -->
<!-- HEADER -->
<!-- ============================================================ -->
<div class="topline">
    <div>
        <h1>📊 Hasil Penilaian</h1>
        <p class="hint">Daftar semua nilai tes yang sudah diinput.</p>
    </div>
    <div>
        <a href="penilai_dashboard.php" class="btn btn-outline">⬅ Kembali</a>
    </div>
</div>

<!-- ============================================================ -->
<!-- FILTER -->
<!-- ============================================================ -->
<div class="card">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <label>Filter Tes:</label>
        <select name="tes" onchange="this.form.submit()" style="padding:8px 12px;border-radius:6px;border:1px solid #d1d5db;">
            <option value="all" <?= $filter_tes == 'all' ? 'selected' : '' ?>>Semua Tes</option>
            <?php foreach($jenis_tes_list as $jt): ?>
                <option value="<?= $jt['id_tes'] ?>" <?= $filter_tes == $jt['id_tes'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($jt['nama_tes']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="badge badge-secondary">Total: <?= count($nilai_list) ?></span>
    </form>
</div>

<!-- ============================================================ -->
<!-- TABEL NILAI -->
<!-- ============================================================ -->
<div class="card">
    <h3>📋 Daftar Nilai</h3>
    
    <?php if(empty($nilai_list)): ?>
        <p style="color:#999;padding:20px 0;text-align:center;">Belum ada nilai yang diinput.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Pelamar</th>
                        <th>Lowongan</th>
                        <th>Jenis Tes</th>
                        <th>Nilai</th>
                        <th>Catatan</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($nilai_list as $n): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($n['pelamar_nama']) ?></strong>
                                <br><span class="text-muted"><?= htmlspecialchars($n['email']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($n['nama_lowongan'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($n['nama_tes']) ?></td>
                            <td>
                                <span class="badge 
                                    <?= $n['nilai'] >= 80 ? 'badge-success' : 
                                        ($n['nilai'] >= 60 ? 'badge-warning' : 'badge-danger') ?>">
                                    <?= number_format($n['nilai'], 2) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($n['catatan'] ?? '-') ?></td>
                            <td><?= date('d-m-Y H:i', strtotime($n['tanggal_input'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '_layout_bottom.php'; ?>