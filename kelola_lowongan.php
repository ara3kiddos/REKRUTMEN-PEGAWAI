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
// PROSES CRUD LOWONGAN
// ============================================================
$error = '';
$success = '';

// Tambah Lowongan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lowongan'])) {
    $nama_lowongan = trim($_POST['nama_lowongan'] ?? '');
    $minimal_pendidikan = $_POST['minimal_pendidikan'] ?? 0;
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $status = $_POST['status'] ?? 'Aktif';
    
    if (empty($nama_lowongan) || empty($minimal_pendidikan) || empty($deskripsi)) {
        $error = 'Semua field wajib diisi!';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO lowongan (nama_lowongan, minimal_pendidikan, deskripsi, status) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$nama_lowongan, $minimal_pendidikan, $deskripsi, $status]);
            $success = 'Lowongan berhasil ditambahkan!';
            auditLog($pdo, "Tambah lowongan: $nama_lowongan");
        } catch (PDOException $e) {
            $error = 'Gagal tambah lowongan: ' . $e->getMessage();
        }
    }
}

// Edit Lowongan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_lowongan'])) {
    $id_lowongan = $_POST['id_lowongan'] ?? 0;
    $nama_lowongan = trim($_POST['nama_lowongan'] ?? '');
    $minimal_pendidikan = $_POST['minimal_pendidikan'] ?? 0;
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $status = $_POST['status'] ?? 'Aktif';
    
    if (empty($nama_lowongan) || empty($minimal_pendidikan) || empty($deskripsi)) {
        $error = 'Semua field wajib diisi!';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE lowongan 
                SET nama_lowongan = ?, minimal_pendidikan = ?, deskripsi = ?, status = ? 
                WHERE id_lowongan = ?
            ");
            $stmt->execute([$nama_lowongan, $minimal_pendidikan, $deskripsi, $status, $id_lowongan]);
            $success = 'Lowongan berhasil diupdate!';
            auditLog($pdo, "Edit lowongan ID: $id_lowongan");
        } catch (PDOException $e) {
            $error = 'Gagal update lowongan: ' . $e->getMessage();
        }
    }
}

// Hapus Lowongan
if (isset($_GET['delete'])) {
    $id_lowongan = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM lowongan WHERE id_lowongan = ?");
        $stmt->execute([$id_lowongan]);
        $success = 'Lowongan berhasil dihapus!';
        auditLog($pdo, "Hapus lowongan ID: $id_lowongan");
    } catch (PDOException $e) {
        $error = 'Gagal hapus lowongan: ' . $e->getMessage();
    }
}

// Ambil data lowongan
$lowonganList = $pdo->query("
    SELECT 
        l.*,
        mp.jenjang as minimal_pendidikan_nama,
        (SELECT COUNT(*) FROM lamaran WHERE id_lowongan = l.id_lowongan) as total_pelamar
    FROM lowongan l
    LEFT JOIN master_pendidikan mp ON l.minimal_pendidikan = mp.id_pendidikan
    ORDER BY l.id_lowongan DESC
")->fetchAll();

// Ambil data pendidikan
$pendidikanList = $pdo->query("SELECT * FROM master_pendidikan ORDER BY jenjang")->fetchAll();

include '_layout_top.php';
?>

<!-- ============================================================ -->
<!-- HEADER -->
<!-- ============================================================ -->
<div class="topline">
    <div>
        <h1>💼 Kelola Lowongan</h1>
        <p class="hint">Tambah, edit, atau hapus lowongan yang tersedia.</p>
    </div>
    <div>
        <span class="badge badge-primary">Total: <?= count($lowonganList) ?></span>
    </div>
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
<!-- FORM TAMBAH LOWONGAN -->
<!-- ============================================================ -->
<div class="card">
    <h3>📝 Tambah Lowongan Baru</h3>
    <form method="POST">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
            <div class="form-group">
                <label>Nama Lowongan <span class="required">*</span></label>
                <input type="text" name="nama_lowongan" placeholder="Contoh: Dosen Tetap Informatika" required>
            </div>
            <div class="form-group">
                <label>Minimal Pendidikan <span class="required">*</span></label>
                <select name="minimal_pendidikan" required>
                    <option value="">-- Pilih --</option>
                    <?php foreach($pendidikanList as $p): ?>
                        <option value="<?= $p['id_pendidikan'] ?>"><?= htmlspecialchars($p['jenjang']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="grid-column:1/-1;">
                <label>Deskripsi <span class="required">*</span></label>
                <textarea name="deskripsi" rows="3" placeholder="Deskripsi lengkap lowongan..." required></textarea>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="Aktif">Aktif</option>
                    <option value="Tidak Aktif">Tidak Aktif</option>
                </select>
            </div>
            <div style="display:flex;align-items:end;">
                <button type="submit" name="add_lowongan" class="btn btn-primary">➕ Tambah Lowongan</button>
            </div>
        </div>
    </form>
</div>

<!-- ============================================================ -->
<!-- DAFTAR LOWONGAN -->
<!-- ============================================================ -->
<div class="card">
    <h3>📋 Daftar Lowongan</h3>
    
    <?php if(empty($lowonganList)): ?>
        <p style="color:#999;padding:20px;text-align:center;">Belum ada lowongan.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Lowongan</th>
                        <th>Min Pendidikan</th>
                        <th>Status</th>
                        <th>Pelamar</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($lowonganList as $l): ?>
                        <tr>
                            <td><?= $l['id_lowongan'] ?></td>
                            <td><strong><?= htmlspecialchars($l['nama_lowongan']) ?></strong></td>
                            <td><?= htmlspecialchars($l['minimal_pendidikan_nama'] ?? '-') ?></td>
                            <td>
                                <span class="badge <?= $l['status'] == 'Aktif' ? 'badge-success' : 'badge-danger' ?>">
                                    <?= htmlspecialchars($l['status']) ?>
                                </span>
                            </td>
                            <td><?= number_format($l['total_pelamar']) ?></td>
                            <td>
                                <button onclick="editLowongan(<?= $l['id_lowongan'] ?>, '<?= addslashes($l['nama_lowongan']) ?>', <?= $l['minimal_pendidikan'] ?>, '<?= addslashes($l['deskripsi']) ?>', '<?= $l['status'] ?>')" class="btn btn-sm btn-primary">✏️</button>
                                <a href="?delete=<?= $l['id_lowongan'] ?>" onclick="return confirm('Yakin hapus lowongan ini?')" class="btn btn-sm btn-danger">🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- MODAL EDIT LOWONGAN -->
<!-- ============================================================ -->
<div id="editModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:999;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:12px;padding:30px;max-width:600px;width:90%;">
        <h3 style="margin-top:0;">✏️ Edit Lowongan</h3>
        <form method="POST">
            <input type="hidden" name="id_lowongan" id="edit_id">
            <div class="form-group">
                <label>Nama Lowongan <span class="required">*</span></label>
                <input type="text" name="nama_lowongan" id="edit_nama" required>
            </div>
            <div class="form-group">
                <label>Minimal Pendidikan <span class="required">*</span></label>
                <select name="minimal_pendidikan" id="edit_pendidikan" required>
                    <?php foreach($pendidikanList as $p): ?>
                        <option value="<?= $p['id_pendidikan'] ?>"><?= htmlspecialchars($p['jenjang']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Deskripsi <span class="required">*</span></label>
                <textarea name="deskripsi" id="edit_deskripsi" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="edit_status">
                    <option value="Aktif">Aktif</option>
                    <option value="Tidak Aktif">Tidak Aktif</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;margin-top:15px;">
                <button type="submit" name="edit_lowongan" class="btn btn-primary">💾 Update</button>
                <button type="button" onclick="closeEditModal()" class="btn btn-outline">❌ Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function editLowongan(id, nama, pendidikan, deskripsi, status) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_pendidikan').value = pendidikan;
    document.getElementById('edit_deskripsi').value = deskripsi;
    document.getElementById('edit_status').value = status;
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>

<?php include '_layout_bottom.php'; ?>