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
// AMBIL DATA LAMARAN
// ============================================================
$lamaran_id = $_GET['id'] ?? 0;

if (!$lamaran_id) {
    header("Location: verifikasi_lamaran.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        lm.*,
        p.id_pelamar,
        p.tempat_lahir,
        p.tanggal_lahir,
        p.jenis_kelamin,
        p.umur,
        u.nama_lengkap,
        u.email,
        u.no_hp,
        u.nik,
        u.alamat,
        l.nama_lowongan,
        ms.nama_status
    FROM lamaran lm
    JOIN pelamar p ON lm.id_pelamar = p.id_pelamar
    JOIN users u ON p.id_user = u.id_user
    LEFT JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
    LEFT JOIN master_status_lamaran ms ON lm.id_status_lamaran = ms.id_master_status_lamaran
    WHERE lm.id_lamaran = ?
");
$stmt->execute([$lamaran_id]);
$lamaran = $stmt->fetch();

if (!$lamaran) {
    header("Location: verifikasi_lamaran.php");
    exit;
}

// ============================================================
// AMBIL DOKUMEN PELAMAR
// ============================================================
$stmt = $pdo->prepare("
    SELECT pd.*, dm.nama_dokumen, dm.kategori, dm.wajib
    FROM pelamar_dokumen pd
    JOIN dokumen_master dm ON pd.id_dokumen = dm.id_dokumen
    WHERE pd.id_pelamar = ?
    ORDER BY dm.urutan
");
$stmt->execute([$lamaran['id_pelamar']]);
$dokumenList = $stmt->fetchAll();

// ============================================================
// PROSES UPDATE DOKUMEN
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_document'])) {
        $dokumen_id = $_POST['dokumen_id'] ?? 0;
        $status = $_POST['status'] ?? 'Belum';
        $catatan = $_POST['catatan'] ?? '';
        
        $stmt = $pdo->prepare("
            UPDATE pelamar_dokumen 
            SET status_verifikasi = ?, catatan = ? 
            WHERE id_pelamar_dokumen = ?
        ");
        $stmt->execute([$status, $catatan, $dokumen_id]);
        $success = 'Status dokumen berhasil diupdate!';
        
        // Refresh data
        $stmt = $pdo->prepare("
            SELECT pd.*, dm.nama_dokumen, dm.kategori, dm.wajib
            FROM pelamar_dokumen pd
            JOIN dokumen_master dm ON pd.id_dokumen = dm.id_dokumen
            WHERE pd.id_pelamar = ?
            ORDER BY dm.urutan
        ");
        $stmt->execute([$lamaran['id_pelamar']]);
        $dokumenList = $stmt->fetchAll();
    }
    
    if (isset($_POST['update_status'])) {
        $status_id = $_POST['status_id'] ?? 0;
        $catatan = $_POST['catatan'] ?? '';
        
        $stmt = $pdo->prepare("
            UPDATE lamaran 
            SET id_status_lamaran = ?, catatan = ? 
            WHERE id_lamaran = ?
        ");
        $stmt->execute([$status_id, $catatan, $lamaran_id]);
        $success = 'Status lamaran berhasil diupdate!';
        
        // Refresh data
        $stmt = $pdo->prepare("
            SELECT ms.nama_status
            FROM lamaran lm
            JOIN master_status_lamaran ms ON lm.id_status_lamaran = ms.id_master_status_lamaran
            WHERE lm.id_lamaran = ?
        ");
        $stmt->execute([$lamaran_id]);
        $status = $stmt->fetch();
        $lamaran['nama_status'] = $status['nama_status'] ?? 'Unknown';
    }
}

// Ambil semua status untuk dropdown
$statusList = $pdo->query("SELECT DISTINCT nama_status, id_master_status_lamaran, urutan FROM master_status_lamaran ORDER BY urutan")->fetchAll();

include '_layout_top.php';
?>

<!-- ============================================================ -->
<!-- HEADER -->
<!-- ============================================================ -->
<div class="topline">
    <div>
        <h1>🔍 Detail Verifikasi Lamaran</h1>
        <p class="hint">Detail lengkap lamaran dan verifikasi dokumen.</p>
    </div>
    <div>
        <a href="verifikasi_lamaran.php" class="btn btn-outline">⬅️ Kembali</a>
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
<!-- INFORMASI PELAMAR + KONTAK -->
<!-- ============================================================ -->
<div class="card">
    <h3>👤 Informasi Pelamar</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
        <div>
            <p><strong>Nama Lengkap:</strong> <?= htmlspecialchars($lamaran['nama_lengkap']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($lamaran['email']) ?></p>
            <p><strong>NIK:</strong> <?= htmlspecialchars($lamaran['nik']) ?></p>
            <p><strong>No. HP:</strong> <?= htmlspecialchars($lamaran['no_hp']) ?></p>
        </div>
        <div>
            <p><strong>Lowongan:</strong> <?= htmlspecialchars($lamaran['nama_lowongan'] ?? '-') ?></p>
            <p><strong>Status:</strong> <span class="badge badge-info"><?= htmlspecialchars($lamaran['nama_status'] ?? 'Unknown') ?></span></p>
            <p><strong>Tanggal Lamaran:</strong> <?= date('d-m-Y', strtotime($lamaran['tanggal_lamaran'])) ?></p>
            <p><strong>Catatan:</strong> <?= htmlspecialchars($lamaran['catatan'] ?? '-') ?></p>
        </div>
    </div>
    
    <!-- ============================================================ -->
    <!-- TOMBOL KONTAK - WHATSAPP & EMAIL -->
    <!-- ============================================================ -->
    <div style="display:flex;gap:10px;margin-top:15px;flex-wrap:wrap;padding-top:15px;border-top:1px solid #e2e8f0;">
        <?php if(!empty($lamaran['no_hp'])): 
            $wa_pesan = "Halo " . $lamaran['nama_lengkap'] . ",\n\nKami dari tim SDI Universitas Muhammadiyah Banjarmasin.\n";
            $wa_pesan .= "Terkait lamaran Anda untuk posisi " . ($lamaran['nama_lowongan'] ?? '') . ".\n\n";
            $wa_pesan .= "Status saat ini: " . ($lamaran['nama_status'] ?? 'Lamaran Dikirim') . "\n";
            $wa_pesan .= "Silakan cek dashboard Anda untuk informasi lebih lanjut.\n\n";
            $wa_pesan .= "Terima kasih.";
        ?>
            <a href="<?= waLink($lamaran['no_hp'], $wa_pesan) ?>" target="_blank" class="btn btn-success" style="display:inline-flex;align-items:center;gap:8px;">
                💬 WhatsApp
            </a>
        <?php endif; ?>
        
        <?php if(!empty($lamaran['email'])): 
            $email_subject = "Informasi Lamaran - " . ($lamaran['nama_lowongan'] ?? 'Rekrutmen SDI');
            $email_body = "Halo " . $lamaran['nama_lengkap'] . ",\n\n";
            $email_body .= "Kami dari tim SDI Universitas Muhammadiyah Banjarmasin.\n";
            $email_body .= "Terkait lamaran Anda untuk posisi " . ($lamaran['nama_lowongan'] ?? '') . ".\n\n";
            $email_body .= "Status saat ini: " . ($lamaran['nama_status'] ?? 'Lamaran Dikirim') . "\n";
            $email_body .= "Silakan cek dashboard Anda untuk informasi lebih lanjut.\n\n";
            $email_body .= "Terima kasih.";
        ?>
            <a href="<?= mailLink($lamaran['email'], $email_subject, $email_body) ?>" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:8px;">
                📧 Email
            </a>
        <?php endif; ?>
        
        <?php if(!empty($lamaran['no_hp'])): ?>
            <a href="tel:<?= preg_replace('/[^0-9]/', '', $lamaran['no_hp']) ?>" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:8px;">
                📞 Telepon
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- TIMELINE TAHAPAN -->
<!-- ============================================================ -->
<div class="card">
    <h3>⏳ Timeline Tahapan</h3>
    <div class="timeline" style="display:flex;flex-direction:column;gap:8px;padding:10px 0;">
        <?php 
        $current_status_id = $lamaran['id_status_lamaran'];
        foreach($statusList as $status):
            $done = $status['id_master_status_lamaran'] <= $current_status_id;
            $is_current = $status['id_master_status_lamaran'] == $current_status_id;
        ?>
            <div class="timeline-item <?= $done ? 'active' : '' ?> <?= $is_current ? 'current' : '' ?>" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:6px;<?= $done ? 'background:#e0f2fe;' : 'background:#f3f4f6;' ?>">
                <span style="font-weight:bold;color:<?= $done ? '#0369a1' : '#9ca3af' ?>;"><?= $status['urutan'] ?></span>
                <span style="flex:1;font-weight:<?= $is_current ? 'bold' : 'normal'; ?>"><?= htmlspecialchars($status['nama_status']) ?></span>
                <?php if($is_current): ?>
                    <span class="pill pill-blue" style="font-size:11px;">📍 Saat Ini</span>
                <?php endif; ?>
                <?php if(!$done): ?>
                    <span style="font-size:11px;color:#9ca3af;">(belum)</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- UPDATE STATUS LAMARAN -->
<!-- ============================================================ -->
<div class="card">
    <h3>📝 Update Status Lamaran</h3>
    <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
        <div class="form-group" style="flex:1;">
            <label>Status Baru</label>
            <select name="status_id" required style="padding:8px 12px;border-radius:6px;border:1px solid #d1d5db;width:100%;">
                <?php foreach($statusList as $s): ?>
                    <option value="<?= $s['id_master_status_lamaran'] ?>" <?= $s['id_master_status_lamaran'] == $lamaran['id_status_lamaran'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['nama_status']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex:2;">
            <label>Catatan</label>
            <input type="text" name="catatan" placeholder="Catatan (opsional)" style="padding:8px 12px;border-radius:6px;border:1px solid #d1d5db;width:100%;">
        </div>
        <div>
            <button type="submit" name="update_status" class="btn btn-primary">📤 Update Status</button>
        </div>
    </form>
</div>

<!-- ============================================================ -->
<!-- DOKUMEN PELAMAR -->
<!-- ============================================================ -->
<div class="card">
    <h3>📎 Dokumen Pelamar</h3>
    <p class="hint">Verifikasi kelengkapan dan keabsahan dokumen.</p>
    
    <?php if(empty($dokumenList)): ?>
        <p style="color:#999;padding:20px 0;text-align:center;">Belum ada dokumen yang diupload.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Dokumen</th>
                        <th>Kategori</th>
                        <th>Status</th>
                        <th>Catatan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach($dokumenList as $d): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($d['nama_dokumen']) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($d['kategori']) ?></span></td>
                            <td>
                                <span class="badge 
                                    <?= $d['status_verifikasi'] == 'Ya' ? 'badge-success' : 
                                        ($d['status_verifikasi'] == 'Tidak' ? 'badge-danger' : 'badge-warning') ?>">
                                    <?= htmlspecialchars($d['status_verifikasi'] ?? 'Belum') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($d['catatan'] ?? '-') ?></td>
                            <td>
                                <button onclick="openModal(<?= $d['id_pelamar_dokumen'] ?>, '<?= htmlspecialchars($d['nama_dokumen']) ?>', '<?= htmlspecialchars($d['status_verifikasi']) ?>')" class="btn btn-sm btn-primary">📄 Verifikasi</button>
                                <?php if(!empty($d['lokasi_file'])): 
                                    $full_path = __DIR__ . '/../' . $d['lokasi_file'];
                                    if(file_exists($full_path)): ?>
                                        <a href="../<?= $d['lokasi_file'] ?>" target="_blank" class="btn btn-sm btn-outline">👁️ Lihat</a>
                                    <?php else: ?>
                                        <span style="color:red;font-size:12px;">File tidak ditemukan</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- MODAL VERIFIKASI DOKUMEN -->
<!-- ============================================================ -->
<div id="verifyModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:999;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:12px;padding:30px;max-width:500px;width:90%;">
        <h3 style="margin-top:0;">📄 Verifikasi Dokumen</h3>
        <form method="POST">
            <input type="hidden" name="dokumen_id" id="dokumen_id">
            <p><strong>Dokumen:</strong> <span id="dokumen_nama"></span></p>
            <div class="form-group">
                <label>Status Verifikasi</label>
                <select name="status" id="dokumen_status" style="width:100%;padding:8px 12px;border-radius:6px;border:1px solid #d1d5db;">
                    <option value="Belum">Belum Dicek</option>
                    <option value="Ya">Sesuai</option>
                    <option value="Tidak">Tidak Sesuai</option>
                </select>
            </div>
            <div class="form-group">
                <label>Catatan</label>
                <textarea name="catatan" id="dokumen_catatan" style="width:100%;padding:8px 12px;border-radius:6px;border:1px solid #d1d5db;min-height:60px;" placeholder="Catatan verifikasi..."></textarea>
            </div>
            <div style="display:flex;gap:10px;margin-top:15px;">
                <button type="submit" name="verify_document" class="btn btn-primary">✅ Simpan</button>
                <button type="button" onclick="closeModal()" class="btn btn-outline">❌ Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id, nama, status) {
    document.getElementById('dokumen_id').value = id;
    document.getElementById('dokumen_nama').textContent = nama;
    document.getElementById('dokumen_status').value = status || 'Belum';
    document.getElementById('verifyModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('verifyModal').style.display = 'none';
}

// Tutup modal saat klik di luar
document.getElementById('verifyModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php include '_layout_bottom.php'; ?>