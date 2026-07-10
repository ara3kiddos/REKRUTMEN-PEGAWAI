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
// PROSES UPDATE STATUS VERIFIKASI
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $lamaran_id = $_POST['lamaran_id'] ?? 0;
    $status_id = $_POST['status_id'] ?? 0;
    $catatan = $_POST['catatan'] ?? '';
    
    if ($lamaran_id && $status_id) {
        try {
            $stmt = $pdo->prepare("
                UPDATE lamaran 
                SET id_status_lamaran = ?, catatan = ? 
                WHERE id_lamaran = ?
            ");
            $stmt->execute([$status_id, $catatan, $lamaran_id]);
            $success = 'Status lamaran berhasil diupdate!';
            
            // Audit log
            auditLog($pdo, "Update status lamaran ID: $lamaran_id ke status ID: $status_id");
        } catch (PDOException $e) {
            $error = 'Gagal update status: ' . $e->getMessage();
        }
    }
}

// ============================================================
// AMBIL DATA LAMARAN
// ============================================================

// Filter
$filter_status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

$sql = "
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
        ms.id_master_status_lamaran as status_id,
        ms.nama_status,
        ms.urutan
    FROM lamaran lm
    JOIN pelamar p ON lm.id_pelamar = p.id_pelamar
    JOIN users u ON p.id_user = u.id_user
    LEFT JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
    LEFT JOIN master_status_lamaran ms ON lm.id_status_lamaran = ms.id_master_status_lamaran
    WHERE 1=1
";

$params = [];

if ($filter_status !== 'all') {
    $sql .= " AND lm.id_status_lamaran = ?";
    $params[] = (int)$filter_status;
}

if (!empty($search)) {
    $sql .= " AND (u.nama_lengkap LIKE ? OR u.email LIKE ? OR u.nik LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY lm.tanggal_lamaran DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lamaranList = $stmt->fetchAll();

// Ambil semua status untuk filter
$statusList = $pdo->query("SELECT * FROM master_status_lamaran ORDER BY urutan")->fetchAll();

include '_layout_top.php';
?>

<!-- ============================================================ -->
<!-- HEADER -->
<!-- ============================================================ -->
<div class="topline">
    <div>
        <h1>📄 Verifikasi Lamaran</h1>
        <p class="hint">Kelola dan verifikasi lamaran yang masuk.</p>
    </div>
    <div>
        <span class="badge badge-primary">Total: <?= count($lamaranList) ?></span>
    </div>
</div>

<!-- ============================================================ -->
<!-- FILTER & SEARCH -->
<!-- ============================================================ -->
<div class="card">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <div>
            <label>Status:</label>
            <select name="status" onchange="this.form.submit()" style="padding:8px 12px;border-radius:6px;border:1px solid #d1d5db;">
                <option value="all" <?= $filter_status == 'all' ? 'selected' : '' ?>>Semua Status</option>
                <?php foreach($statusList as $s): ?>
                    <option value="<?= $s['id_master_status_lamaran'] ?>" <?= $filter_status == $s['id_master_status_lamaran'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['nama_status']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1;min-width:200px;">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama, email, NIK..." style="width:100%;padding:8px 12px;border-radius:6px;border:1px solid #d1d5db;">
        </div>
        <button type="submit" class="btn btn-primary">🔍 Cari</button>
        <a href="verifikasi_lamaran.php" class="btn btn-outline">↻ Reset</a>
    </form>
</div>

<!-- ============================================================ -->
<!-- PESAN -->
<!-- ============================================================ -->
<?php if($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- DAFTAR LAMARAN -->
<!-- ============================================================ -->
<div class="card">
    <h3>📋 Daftar Lamaran</h3>
    
    <?php if(empty($lamaranList)): ?>
        <p style="color:#999;padding:20px;text-align:center;">Tidak ada lamaran ditemukan.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Pelamar</th>
                        <th>Lowongan</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Kontak</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach($lamaranList as $l): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($l['nama_lengkap']) ?></strong>
                                <br><span class="hint"><?= htmlspecialchars($l['email']) ?></span>
                                <br><span class="hint">NIK: <?= htmlspecialchars($l['nik']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($l['nama_lowongan'] ?? '-') ?></td>
                            <td>
                                <span class="badge 
                                    <?= in_array($l['status_id'], [1, 2]) ? 'badge-warning' : 
                                        (in_array($l['status_id'], [14, 15, 16]) ? 'badge-success' : 
                                        (in_array($l['status_id'], [17]) ? 'badge-danger' : 'badge-info')) ?>">
                                    <?= htmlspecialchars($l['nama_status'] ?? 'Unknown') ?>
                                </span>
                            </td>
                            <td><?= date('d-m-Y', strtotime($l['tanggal_lamaran'])) ?></td>
                            <td>
                                <?php if(!empty($l['no_hp'])): ?>
                                    <a href="<?= waLink($l['no_hp'], 'Halo ' . $l['nama_lengkap'] . ', terkait lamaran Anda di SDI UMB.') ?>" target="_blank" class="btn btn-sm btn-success" title="WhatsApp">💬</a>
                                <?php endif; ?>
                                <?php if(!empty($l['email'])): ?>
                                    <a href="<?= mailLink($l['email'], 'Informasi Lamaran SDI UMB', 'Halo ' . $l['nama_lengkap'] . ',\n\nTerkait lamaran Anda di SDI UMB.') ?>" class="btn btn-sm btn-primary" title="Email">📧</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="verifikasi_detail.php?id=<?= $l['id_lamaran'] ?>" class="btn btn-sm btn-primary">🔍 Detail</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- UPDATE STATUS CEPAT -->
<!-- ============================================================ -->
<div class="card">
    <h3>⚡ Update Status Cepat</h3>
    <p class="hint">Pilih lamaran dan update status secara langsung.</p>
    
    <form method="POST">
        <div style="display:grid;grid-template-columns:1fr 1fr 2fr auto;gap:10px;align-items:end;">
            <div class="form-group">
                <label>Pilih Lamaran</label>
                <select name="lamaran_id" required>
                    <option value="">-- Pilih --</option>
                    <?php foreach($lamaranList as $l): ?>
                        <option value="<?= $l['id_lamaran'] ?>">
                            <?= htmlspecialchars($l['nama_lengkap']) ?> - <?= htmlspecialchars($l['nama_lowongan'] ?? '-') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Status Baru</label>
                <select name="status_id" required>
                    <?php foreach($statusList as $s): ?>
                        <option value="<?= $s['id_master_status_lamaran'] ?>">
                            <?= htmlspecialchars($s['nama_status']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Catatan</label>
                <input type="text" name="catatan" placeholder="Catatan (opsional)">
            </div>
            <div>
                <button type="submit" name="update_status" class="btn btn-primary">📤 Update</button>
            </div>
        </div>
    </form>
</div>

<?php include '_layout_bottom.php'; ?>