<?php
require __DIR__.'/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// CEK LOGIN & ROLE (REKTOR atau SUPERUSER)
// ============================================================
if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT u.*, r.nama_role FROM users u JOIN roles r ON u.id_role = r.id_role WHERE u.id_user = ? AND u.status_aktif = 1");
$stmt->execute([$_SESSION['id_user']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

// Yang bisa akses: Rektor (3) atau Superuser (1)
if (!in_array($user['id_role'], [1, 3])) {
    header("Location: dashboard.php");
    exit;
}

$role_id = $user['id_role'];
$role_name = $user['nama_role'];

// ============================================================
// PROSES TAMBAH / EDIT SK
// ============================================================
$error = '';
$success = '';
$edit_sk = null;

// Ambil data SK untuk diedit
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM sk_pengangkatan WHERE id_sk = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_sk = $stmt->fetch();
}

// Proses simpan SK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id_lamaran = $_POST['id_lamaran'] ?? 0;
    $nomor_sk = trim($_POST['nomor_sk'] ?? '');
    $tanggal_sk = $_POST['tanggal_sk'] ?? date('Y-m-d');
    $jenis_sk = $_POST['jenis_sk'] ?? 'Tendik';
    $id_sk = $_POST['id_sk'] ?? 0;
    
    // Validasi
    if (empty($id_lamaran) || empty($nomor_sk)) {
        $error = 'Lamaran dan Nomor SK wajib diisi!';
    } else {
        try {
            // Cek apakah lamaran sudah punya SK
            if (empty($id_sk)) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM sk_pengangkatan WHERE id_lamaran = ?");
                $stmt->execute([$id_lamaran]);
                if ((int)$stmt->fetchColumn() > 0) {
                    $error = 'Lamaran ini sudah memiliki SK!';
                }
            }
            
            if (empty($error)) {
                // Upload file SK
                $file_sk = '';
                if (isset($_FILES['file_sk']) && $_FILES['file_sk']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/../uploads/sk/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    
                    $ext = strtolower(pathinfo($_FILES['file_sk']['name'], PATHINFO_EXTENSION));
                    $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
                    
                    if (!in_array($ext, $allowed)) {
                        $error = 'Format file SK harus PDF, JPG, atau PNG.';
                    } else {
                        $filename = 'SK_' . date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                        $target = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['file_sk']['tmp_name'], $target)) {
                            $file_sk = 'uploads/sk/' . $filename;
                        } else {
                            $error = 'Gagal upload file SK.';
                        }
                    }
                } elseif ($edit_sk && empty($_FILES['file_sk']['error'])) {
                    // Jika edit dan tidak upload file baru, gunakan file lama
                    $file_sk = $edit_sk['file_sk'];
                }
                
                if (empty($error)) {
                    if ($id_sk > 0) {
                        // UPDATE SK
                        $sql = "UPDATE sk_pengangkatan SET 
                                    nomor_sk = ?, 
                                    tanggal_sk = ?, 
                                    jenis_sk = ?";
                        $params = [$nomor_sk, $tanggal_sk, $jenis_sk];
                        
                        if (!empty($file_sk)) {
                            $sql .= ", file_sk = ?";
                            $params[] = $file_sk;
                        }
                        
                        $sql .= " WHERE id_sk = ?";
                        $params[] = $id_sk;
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $success = 'SK berhasil diperbarui!';
                        
                    } else {
                        // INSERT SK BARU
                        $stmt = $pdo->prepare("
                            INSERT INTO sk_pengangkatan (id_lamaran, nomor_sk, tanggal_sk, jenis_sk, file_sk) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$id_lamaran, $nomor_sk, $tanggal_sk, $jenis_sk, $file_sk]);
                        $success = 'SK berhasil dibuat!';
                        
                        // Update status lamaran menjadi "Pengangkatan SK"
                        $stmt = $pdo->prepare("
                            UPDATE lamaran 
                            SET id_status_lamaran = 14, tahap_lamaran = 'Pengangkatan SK' 
                            WHERE id_lamaran = ?
                        ");
                        $stmt->execute([$id_lamaran]);
                    }
                    
                    // Redirect untuk refresh
                    header("Location: sk_pengangkatan.php?success=" . urlencode($success));
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = 'Gagal menyimpan SK: ' . $e->getMessage();
        }
    }
}

// ============================================================
// PROSES HAPUS SK
// ============================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id_sk = $_GET['delete'];
    
    try {
        // Ambil file_sk untuk dihapus
        $stmt = $pdo->prepare("SELECT file_sk FROM sk_pengangkatan WHERE id_sk = ?");
        $stmt->execute([$id_sk]);
        $sk = $stmt->fetch();
        
        if ($sk && !empty($sk['file_sk'])) {
            $file_path = __DIR__ . '/../' . $sk['file_sk'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM sk_pengangkatan WHERE id_sk = ?");
        $stmt->execute([$id_sk]);
        
        $success = 'SK berhasil dihapus!';
        header("Location: sk_pengangkatan.php?success=" . urlencode($success));
        exit;
    } catch (PDOException $e) {
        $error = 'Gagal hapus SK: ' . $e->getMessage();
    }
}

// ============================================================
// AMBIL DATA
// ============================================================

// Ambil daftar pelamar yang sudah diterima / layak SK
$pelamar_lulus = $pdo->query("
    SELECT 
        lm.id_lamaran,
        lm.tahap_lamaran,
        lm.tanggal_lamaran,
        p.id_pelamar,
        u.nama_lengkap,
        u.email,
        u.nik,
        l.nama_lowongan,
        l.kategori,
        l.nama_posisi,
        l.unit_kerja
    FROM lamaran lm
    JOIN pelamar p ON lm.id_pelamar = p.id_pelamar
    JOIN users u ON p.id_user = u.id_user
    LEFT JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
    WHERE lm.tahap_lamaran IN ('Diterima', 'Rapat SDI/Universitas', 'Pengangkatan SK')
    ORDER BY lm.tanggal_lamaran DESC
")->fetchAll();

// Ambil daftar SK yang sudah dikeluarkan
$sk_list = $pdo->query("
    SELECT 
        sk.*,
        lm.id_lamaran,
        p.id_pelamar,
        u.nama_lengkap,
        u.email,
        u.nik,
        l.nama_lowongan,
        l.kategori,
        l.nama_posisi,
        lm.tahap_lamaran
    FROM sk_pengangkatan sk
    JOIN lamaran lm ON sk.id_lamaran = lm.id_lamaran
    JOIN pelamar p ON lm.id_pelamar = p.id_pelamar
    JOIN users u ON p.id_user = u.id_user
    LEFT JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
    ORDER BY sk.tanggal_sk DESC
")->fetchAll();

include '_layout_top.php';
?>

<!-- ============================================================ -->
<!-- HEADER -->
<!-- ============================================================ -->
<div class="topline">
    <div>
        <h1>📄 SK Pengangkatan</h1>
        <p class="hint">Kelola Surat Keputusan pengangkatan pegawai</p>
    </div>
    <div>
        <span class="badge badge-primary">Total SK: <?= count($sk_list) ?></span>
        <span class="badge badge-success">Lulus: <?= count($pelamar_lulus) ?></span>
    </div>
</div>

<!-- ============================================================ -->
<!-- PESAN ERROR / SUCCESS -->
<!-- ============================================================ -->
<?php if(isset($_GET['success'])): ?>
    <div class="alert alert-success" style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:6px;margin-bottom:15px;">
        ✅ <?= htmlspecialchars($_GET['success']) ?>
    </div>
<?php endif; ?>

<?php if($error): ?>
    <div class="alert alert-error" style="background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:6px;margin-bottom:15px;">
        ❌ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- FORM TAMBAH / EDIT SK -->
<!-- ============================================================ -->
<div class="card">
    <h3><?= $edit_sk ? '✏️ Edit SK' : '📝 Buat SK Baru' ?></h3>
    
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save">
        <?php if($edit_sk): ?>
            <input type="hidden" name="id_sk" value="<?= $edit_sk['id_sk'] ?>">
        <?php endif; ?>
        
        <div class="form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
            <div class="field">
                <label>Pilih Pelamar <span style="color:red;">*</span></label>
                <select name="id_lamaran" id="id_lamaran" required <?= $edit_sk ? 'disabled' : '' ?>>
                    <option value="">-- Pilih Pelamar --</option>
                    <?php foreach($pelamar_lulus as $p): ?>
                        <?php 
                        // Cek apakah sudah punya SK
                        $has_sk = false;
                        foreach($sk_list as $sk) {
                            if ($sk['id_lamaran'] == $p['id_lamaran']) {
                                $has_sk = true;
                                break;
                            }
                        }
                        ?>
                        <option value="<?= $p['id_lamaran'] ?>" 
                            <?= ($edit_sk && $edit_sk['id_lamaran'] == $p['id_lamaran']) ? 'selected' : '' ?>
                            <?= $has_sk && !$edit_sk ? 'disabled style="color:#999;"' : '' ?>>
                            <?= htmlspecialchars($p['nama_lengkap']) ?> 
                            - <?= htmlspecialchars($p['nama_lowongan'] ?? $p['nama_posisi'] ?? '-') ?>
                            <?= $has_sk && !$edit_sk ? '(Sudah ada SK)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if($edit_sk): ?>
                    <input type="hidden" name="id_lamaran" value="<?= $edit_sk['id_lamaran'] ?>">
                    <p class="hint"><?= htmlspecialchars($edit_sk['nama_lengkap'] ?? '') ?></p>
                <?php endif; ?>
            </div>
            
            <div class="field">
                <label>Nomor SK <span style="color:red;">*</span></label>
                <input type="text" name="nomor_sk" value="<?= $edit_sk['nomor_sk'] ?? '' ?>" placeholder="Contoh: 123/UMB/SK/2024" required>
            </div>
            
            <div class="field">
                <label>Tanggal SK</label>
                <input type="date" name="tanggal_sk" value="<?= $edit_sk['tanggal_sk'] ?? date('Y-m-d') ?>" required>
            </div>
            
            <div class="field">
                <label>Jenis SK</label>
                <select name="jenis_sk">
                    <option value="Dosen" <?= ($edit_sk && $edit_sk['jenis_sk'] == 'Dosen') ? 'selected' : '' ?>>Dosen</option>
                    <option value="Tendik" <?= ($edit_sk && $edit_sk['jenis_sk'] == 'Tendik') ? 'selected' : '' ?>>Tenaga Kependidikan</option>
                </select>
            </div>
            
            <div class="field full" style="grid-column: 1 / -1;">
                <label>File SK (PDF/PNG/JPG) <?= $edit_sk ? '<span class="hint">(Kosongkan jika tidak diubah)</span>' : '<span style="color:red;">*</span>' ?></label>
                <input type="file" name="file_sk" accept=".pdf,.jpg,.jpeg,.png" <?= $edit_sk ? '' : 'required' ?>>
                <?php if($edit_sk && !empty($edit_sk['file_sk'])): ?>
                    <p class="hint">File saat ini: <a href="../<?= $edit_sk['file_sk'] ?>" target="_blank">Lihat File</a></p>
                <?php endif; ?>
            </div>
        </div>
        
        <br>
        <button type="submit" class="btn btn-primary"><?= $edit_sk ? 'Update SK' : 'Buat SK' ?></button>
        <?php if($edit_sk): ?>
            <a href="sk_pengangkatan.php" class="btn btn-outline">Batal</a>
        <?php endif; ?>
    </form>
</div>

<!-- ============================================================ -->
<!-- DAFTAR SK -->
<!-- ============================================================ -->
<div class="card">
    <h3>📋 Daftar SK Pengangkatan</h3>
    
    <?php if(empty($sk_list)): ?>
        <p style="color:#999;padding:20px;text-align:center;">Belum ada SK yang dikeluarkan.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama</th>
                    <th>Nomor SK</th>
                    <th>Posisi</th>
                    <th>Jenis</th>
                    <th>Tanggal</th>
                    <th>File</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($sk_list as $i => $sk): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <strong><?= htmlspecialchars($sk['nama_lengkap']) ?></strong>
                            <br><span class="hint"><?= htmlspecialchars($sk['email']) ?></span>
                        </td>
                        <td><strong><?= htmlspecialchars($sk['nomor_sk']) ?></strong></td>
                        <td><?= htmlspecialchars($sk['nama_lowongan'] ?? $sk['nama_posisi'] ?? '-') ?></td>
                        <td>
                            <span class="badge <?= $sk['jenis_sk'] == 'Dosen' ? 'badge-primary' : 'badge-warning' ?>">
                                <?= htmlspecialchars($sk['jenis_sk']) ?>
                            </span>
                        </td>
                        <td><?= date('d-m-Y', strtotime($sk['tanggal_sk'])) ?></td>
                        <td>
                            <?php if(!empty($sk['file_sk'])): ?>
                                <a href="../<?= $sk['file_sk'] ?>" target="_blank" class="btn btn-sm btn-primary">📄 Lihat</a>
                            <?php else: ?>
                                <span class="hint">Tidak ada</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="sk_pengangkatan.php?edit=<?= $sk['id_sk'] ?>" class="btn btn-sm btn-outline">✏️</a>
                            <a href="sk_pengangkatan.php?delete=<?= $sk['id_sk'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus SK ini?')">🗑️</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- PELAMAR LULUS TANPA SK -->
<!-- ============================================================ -->
<div class="card">
    <h3>🎓 Pelamar Lulus Tanpa SK</h3>
    
    <?php 
    // Filter pelamar lulus yang belum punya SK
    $sk_lamaran_ids = array_column($sk_list, 'id_lamaran');
    $belum_sk = array_filter($pelamar_lulus, function($p) use ($sk_lamaran_ids) {
        return !in_array($p['id_lamaran'], $sk_lamaran_ids);
    });
    ?>
    
    <?php if(empty($belum_sk)): ?>
        <p style="color:green;padding:20px;">✅ Semua pelamar lulus sudah memiliki SK.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Lowongan</th>
                    <th>Kategori</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($belum_sk as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['nama_lengkap']) ?></td>
                        <td><?= htmlspecialchars($p['nama_lowongan'] ?? $p['nama_posisi'] ?? '-') ?></td>
                        <td><span class="badge <?= ($p['kategori'] ?? 'Tendik') == 'Dosen' ? 'badge-primary' : 'badge-warning' ?>"><?= htmlspecialchars($p['kategori'] ?? 'Tendik') ?></span></td>
                        <td><?= date('d-m-Y', strtotime($p['tanggal_lamaran'])) ?></td>
                        <td>
                            <a href="sk_pengangkatan.php?buat=<?= $p['id_lamaran'] ?>" class="btn btn-sm btn-success">📄 Buat SK</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include '_layout_bottom.php'; ?>