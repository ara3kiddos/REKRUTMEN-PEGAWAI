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
// AMBIL DATA PELAMAR
// ============================================================
$pelamar_id = $_GET['id'] ?? 0;

if (!$pelamar_id) {
    header("Location: data_pelamar.php");
    exit;
}

$stmt = $pdo->prepare("
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
        lm.catatan,
        ms.nama_status,
        l.nama_lowongan,
        l.deskripsi as deskripsi_lowongan
    FROM pelamar p
    JOIN users u ON p.id_user = u.id_user
    LEFT JOIN master_agama a ON p.id_agama = a.id_agama
    LEFT JOIN master_pendidikan mp ON p.pendidikan_terakhir = mp.id_pendidikan
    LEFT JOIN lamaran lm ON p.id_pelamar = lm.id_pelamar
    LEFT JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
    LEFT JOIN master_status_lamaran ms ON lm.id_status_lamaran = ms.id_master_status_lamaran
    WHERE p.id_pelamar = ?
");
$stmt->execute([$pelamar_id]);
$pelamar = $stmt->fetch();

if (!$pelamar) {
    header("Location: data_pelamar.php");
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
$stmt->execute([$pelamar_id]);
$dokumenList = $stmt->fetchAll();

include '_layout_top.php';
?>

<!-- ============================================================ -->
<!-- HEADER -->
<!-- ============================================================ -->
<div class="topline">
    <div>
        <h1>👤 Detail Pelamar</h1>
        <p class="hint">Informasi lengkap data pelamar.</p>
    </div>
    <div>
        <a href="data_pelamar.php" class="btn btn-outline">⬅️ Kembali</a>
        <?php if(!empty($pelamar['id_lamaran'])): ?>
            <a href="verifikasi_detail.php?id=<?= $pelamar['id_lamaran'] ?>" class="btn btn-primary">📄 Verifikasi Lamaran</a>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- DATA PRIBADI -->
<!-- ============================================================ -->
<div class="card">
    <h3>📋 Data Pribadi</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
        <div>
            <p><strong>Nama Lengkap:</strong> <?= htmlspecialchars($pelamar['nama_lengkap']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($pelamar['email']) ?></p>
            <p><strong>NIK:</strong> <?= htmlspecialchars($pelamar['nik']) ?></p>
            <p><strong>No. HP:</strong> <?= htmlspecialchars($pelamar['no_hp']) ?></p>
            <p><strong>Alamat:</strong> <?= htmlspecialchars($pelamar['alamat']) ?></p>
        </div>
        <div>
            <p><strong>Tempat Lahir:</strong> <?= htmlspecialchars($pelamar['tempat_lahir']) ?></p>
            <p><strong>Tanggal Lahir:</strong> <?= date('d-m-Y', strtotime($pelamar['tanggal_lahir'])) ?></p>
            <p><strong>Umur:</strong> <?= $pelamar['umur'] ?> tahun</p>
            <p><strong>Jenis Kelamin:</strong> <?= $pelamar['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan' ?></p>
            <p><strong>Agama:</strong> <?= htmlspecialchars($pelamar['nama_agama'] ?? '-') ?></p>
            <p><strong>Pendidikan:</strong> <?= htmlspecialchars($pelamar['pendidikan_nama'] ?? '-') ?></p>
            <p><strong>Status Akun:</strong> 
                <span class="badge <?= $pelamar['status_aktif'] ? 'badge-success' : 'badge-danger' ?>">
                    <?= $pelamar['status_aktif'] ? 'Aktif' : 'Non-Aktif' ?>
                </span>
            </p>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- DATA LAMARAN -->
<!-- ============================================================ -->
<div class="card">
    <h3>📄 Data Lamaran</h3>
    
    <?php if(empty($pelamar['id_lamaran'])): ?>
        <p style="color:#999;text-align:center;padding:20px;">Belum ada lamaran.</p>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
            <div>
                <p><strong>Lowongan:</strong> <?= htmlspecialchars($pelamar['nama_lowongan'] ?? '-') ?></p>
                <p><strong>Tanggal Lamaran:</strong> <?= date('d-m-Y', strtotime($pelamar['tanggal_lamaran'])) ?></p>
            </div>
            <div>
                <p><strong>Status:</strong> 
                    <span class="badge 
                        <?= in_array($pelamar['id_status_lamaran'], [1, 2]) ? 'badge-warning' : 
                            (in_array($pelamar['id_status_lamaran'], [14, 15, 16]) ? 'badge-success' : 
                            (in_array($pelamar['id_status_lamaran'], [17]) ? 'badge-danger' : 'badge-info')) ?>">
                        <?= htmlspecialchars($pelamar['nama_status'] ?? 'Belum Ada') ?>
                    </span>
                </p>
                <p><strong>Catatan:</strong> <?= htmlspecialchars($pelamar['catatan'] ?? '-') ?></p>
            </div>
        </div>
        
        <?php if(!empty($pelamar['deskripsi_lowongan'])): ?>
            <div style="margin-top:10px;padding:12px;background:#f8fafc;border-radius:6px;">
                <p><strong>Deskripsi Lowongan:</strong></p>
                <p style="color:#475569;font-size:14px;"><?= nl2br(htmlspecialchars($pelamar['deskripsi_lowongan'])) ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- DOKUMEN PELAMAR -->
<!-- ============================================================ -->
<div class="card">
    <h3>📎 Dokumen Pelamar</h3>
    
    <?php if(empty($dokumenList)): ?>
        <p style="color:#999;text-align:center;padding:20px;">Belum ada dokumen yang diupload.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Dokumen</th>
                        <th>Kategori</th>
                        <th>Status Verifikasi</th>
                        <th>File</th>
                        <th>Catatan</th>
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
                            <td>
                                <?php if(!empty($d['lokasi_file'])): ?>
                                    <a href="../<?= $d['lokasi_file'] ?>" target="_blank" class="btn btn-sm btn-primary">👁️ Lihat</a>
                                <?php else: ?>
                                    <span style="color:#999;font-size:12px;">Tidak ada</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($d['catatan'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- AKSI -->
<!-- ============================================================ -->
<div class="card">
    <h3>⚡ Aksi</h3>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php if(!empty($pelamar['id_lamaran'])): ?>
            <a href="verifikasi_detail.php?id=<?= $pelamar['id_lamaran'] ?>" class="btn btn-primary">📄 Verifikasi Lamaran</a>
        <?php endif; ?>
        <a href="data_pelamar.php" class="btn btn-outline">⬅️ Kembali ke Daftar</a>
    </div>
</div>

<?php include '_layout_bottom.php'; ?>