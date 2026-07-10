<?php
require __DIR__.'/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CEK LOGIN
if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT u.*, r.nama_role FROM users u JOIN roles r ON u.id_role = r.id_role WHERE u.id_user = ? AND u.status_aktif = 1");
$stmt->execute([$_SESSION['id_user']]);
$user = $stmt->fetch();

if (!$user || !in_array($user['id_role'], [1, 2])) {
    header("Location: dashboard.php");
    exit;
}

$role_id = $user['id_role'];
$role_name = $user['nama_role'];

// ============================================================
// FILTER & PENCARIAN
// ============================================================
$search = $_GET['search'] ?? '';
$filter_agama = $_GET['agama'] ?? '';
$filter_pendidikan = $_GET['pendidikan'] ?? '';

$sql = "
    SELECT 
        p.*,
        u.nama_lengkap,
        u.email,
        u.no_hp,
        u.nik,
        u.alamat,
        u.status_aktif,
        a.nama_agama,
        mp.jenjang as pendidikan_nama,
        lm.id_lamaran,
        lm.tanggal_lamaran,
        lm.id_status_lamaran,
        ms.nama_status,
        l.nama_lowongan
    FROM pelamar p
    JOIN users u ON p.id_user = u.id_user
    LEFT JOIN master_agama a ON p.id_agama = a.id_agama
    LEFT JOIN master_pendidikan mp ON p.pendidikan_terakhir = mp.id_pendidikan
    LEFT JOIN lamaran lm ON p.id_pelamar = lm.id_pelamar
    LEFT JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
    LEFT JOIN master_status_lamaran ms ON lm.id_status_lamaran = ms.id_master_status_lamaran
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (u.nama_lengkap LIKE ? OR u.email LIKE ? OR u.nik LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($filter_agama)) {
    $sql .= " AND p.id_agama = ?";
    $params[] = $filter_agama;
}

if (!empty($filter_pendidikan)) {
    $sql .= " AND p.pendidikan_terakhir = ?";
    $params[] = $filter_pendidikan;
}

$sql .= " ORDER BY p.id_pelamar DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pelamarList = $stmt->fetchAll();

// Ambil data agama untuk filter
$agamaList = $pdo->query("SELECT * FROM master_agama ORDER BY nama_agama")->fetchAll();

// Ambil data pendidikan untuk filter
$pendidikanList = $pdo->query("SELECT * FROM master_pendidikan ORDER BY jenjang")->fetchAll();

include '_layout_top.php';
?>

<!-- ============================================================ -->
<!-- HEADER -->
<!-- ============================================================ -->
<div class="topline">
    <div>
        <h1>👤 Data Pelamar</h1>
        <p class="hint">Kelola dan lihat data semua pelamar.</p>
    </div>
    <div>
        <span class="badge badge-primary">Total: <?= count($pelamarList) ?></span>
    </div>
</div>

<!-- ============================================================ -->
<!-- FILTER & SEARCH -->
<!-- ============================================================ -->
<div class="card">
    <form method="GET" style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:10px;align-items:end;">
        <div class="form-group">
            <label>Cari</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama, email, NIK..." style="width:100%;padding:8px 12px;border-radius:6px;border:1px solid #d1d5db;">
        </div>
        <div class="form-group">
            <label>Agama</label>
            <select name="agama" style="width:100%;padding:8px 12px;border-radius:6px;border:1px solid #d1d5db;">
                <option value="">Semua Agama</option>
                <?php foreach($agamaList as $a): ?>
                    <option value="<?= $a['id_agama'] ?>" <?= $filter_agama == $a['id_agama'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['nama_agama']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Pendidikan</label>
            <select name="pendidikan" style="width:100%;padding:8px 12px;border-radius:6px;border:1px solid #d1d5db;">
                <option value="">Semua Pendidikan</option>
                <?php foreach($pendidikanList as $p): ?>
                    <option value="<?= $p['id_pendidikan'] ?>" <?= $filter_pendidikan == $p['id_pendidikan'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['jenjang']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;gap:8px;">
            <button type="submit" class="btn btn-primary">🔍 Cari</button>
            <a href="data_pelamar.php" class="btn btn-outline">↻ Reset</a>
        </div>
    </form>
</div>

<!-- ============================================================ -->
<!-- DAFTAR PELAMAR -->
<!-- ============================================================ -->
<div class="card">
    <h3>📋 Daftar Pelamar</h3>
    
    <?php if(empty($pelamarList)): ?>
        <p style="color:#999;padding:20px;text-align:center;">Tidak ada pelamar ditemukan.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>NIK</th>
                        <th>Agama</th>
                        <th>Pendidikan</th>
                        <th>Lowongan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach($pelamarList as $p): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($p['nama_lengkap']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($p['email']) ?></td>
                            <td><?= htmlspecialchars($p['nik']) ?></td>
                            <td><?= htmlspecialchars($p['nama_agama'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($p['pendidikan_nama'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($p['nama_lowongan'] ?? '-') ?></td>
                            <td>
                                <span class="badge 
                                    <?= in_array($p['id_status_lamaran'], [1, 2]) ? 'badge-warning' : 
                                        (in_array($p['id_status_lamaran'], [14, 15, 16]) ? 'badge-success' : 
                                        (in_array($p['id_status_lamaran'], [17]) ? 'badge-danger' : 'badge-info')) ?>">
                                    <?= htmlspecialchars($p['nama_status'] ?? 'Belum Ada') ?>
                                </span>
                            </td>
                            <td>
                                <a href="detail_pelamar.php?id=<?= $p['id_pelamar'] ?>" class="btn btn-sm btn-primary">👁️ Detail</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '_layout_bottom.php'; ?>