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
// PROSES CRUD PENGUMUMAN
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pengumuman'])) {
    $judul = trim($_POST['judul'] ?? '');
    $isi = trim($_POST['isi'] ?? '');
    $kategori = $_POST['kategori'] ?? 'Umum';
    
    if (empty($judul) || empty($isi)) {
        $error = 'Judul dan isi pengumuman wajib diisi!';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO notifikasi (id_user, judul, pesan) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$_SESSION['id_user'], $judul, $isi]);
            $success = 'Pengumuman berhasil ditambahkan!';
        } catch (PDOException $e) {
            $error = 'Gagal tambah pengumuman: ' . $e->getMessage();
        }
    }
}

// Ambil pengumuman
$pengumuman = $pdo->query("
    SELECT n.*, u.nama_lengkap 
    FROM notifikasi n
    JOIN users u ON n.id_user = u.id_user
    ORDER BY n.id_notifikasi DESC
    LIMIT 20
")->fetchAll();

include '_layout_top.php';
?>

<!-- ============================================================ -->
<!-- HEADER -->
<!-- ============================================================ -->
<div class="topline">
    <div>
        <h1>📢 Pengumuman</h1>
        <p class="hint">Kelola pengumuman untuk pelamar.</p>
    </div>
    <div>
        <span class="badge badge-primary">Total: <?= count($pengumuman) ?></span>
    </div>
</div>

<!-- ============================================================ -->
<!-- FORM TAMBAH -->
<!-- ============================================================ -->
<div class="card">
    <h3>📝 Tambah Pengumuman</h3>
    <form method="POST">
        <div class="form-group">
            <label>Judul <span class="required">*</span></label>
            <input type="text" name="judul" placeholder="Contoh: Pengumuman Hasil Seleksi Administrasi" required>
        </div>
        <div class="form-group">
            <label>Isi Pengumuman <span class="required">*</span></label>
            <textarea name="isi" rows="5" placeholder="Tulis isi pengumuman..." required></textarea>
        </div>
        <div class="form-group">
            <label>Kategori</label>
            <select name="kategori">
                <option value="Umum">Umum</option>
                <option value="Seleksi">Seleksi</option>
                <option value="Pengumuman">Pengumuman</option>
                <option value="Penting">Penting</option>
            </select>
        </div>
        <button type="submit" name="add_pengumuman" class="btn btn-primary">📤 Publikasikan</button>
    </form>
</div>

<!-- ============================================================ -->
<!-- DAFTAR PENGUMUMAN -->
<!-- ============================================================ -->
<div class="card">
    <h3>📋 Daftar Pengumuman</h3>
    
    <?php if(empty($pengumuman)): ?>
        <p style="color:#999;padding:20px;text-align:center;">Belum ada pengumuman.</p>
    <?php else: ?>
        <?php foreach($pengumuman as $p): ?>
            <div style="border:1px solid #e5e7eb;border-radius:8px;padding:15px;margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:8px;">
                    <h4 style="margin:0;"><?= htmlspecialchars($p['judul']) ?></h4>
                    <span style="font-size:12px;color:#6b7280;">
                        <?= date('d-m-Y H:i', strtotime($p['waktu'])) ?> oleh <?= htmlspecialchars($p['nama_lengkap']) ?>
                    </span>
                </div>
                <p style="margin:8px 0 0;color:#475569;"><?= nl2br(htmlspecialchars($p['pesan'])) ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include '_layout_bottom.php'; ?>