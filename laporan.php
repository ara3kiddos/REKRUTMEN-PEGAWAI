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
// STATISTIK LAPORAN
// ============================================================

// Total semua
$total_pelamar = $pdo->query("SELECT COUNT(*) FROM pelamar")->fetchColumn();
$total_lamaran = $pdo->query("SELECT COUNT(*) FROM lamaran")->fetchColumn();
$total_dokumen = $pdo->query("SELECT COUNT(*) FROM pelamar_dokumen")->fetchColumn();

// Status lamaran
$statusData = $pdo->query("
    SELECT ms.nama_status, COUNT(lm.id_lamaran) as total
    FROM master_status_lamaran ms
    LEFT JOIN lamaran lm ON ms.id_master_status_lamaran = lm.id_status_lamaran
    GROUP BY ms.id_master_status_lamaran, ms.nama_status
    ORDER BY ms.urutan
")->fetchAll();

// Lamaran per bulan
$monthlyData = $pdo->query("
    SELECT 
        DATE_FORMAT(tanggal_lamaran, '%Y-%m') as bulan,
        COUNT(*) as total
    FROM lamaran
    GROUP BY DATE_FORMAT(tanggal_lamaran, '%Y-%m')
    ORDER BY bulan DESC
    LIMIT 12
")->fetchAll();

// Lowongan dengan pelamar
$lowonganData = $pdo->query("
    SELECT 
        l.nama_lowongan,
        COUNT(lm.id_lamaran) as total
    FROM lowongan l
    LEFT JOIN lamaran lm ON l.id_lowongan = lm.id_lowongan
    WHERE l.status = 'Aktif'
    GROUP BY l.id_lowongan, l.nama_lowongan
    ORDER BY total DESC
")->fetchAll();

// Data pelamar per pendidikan
$pendidikanData = $pdo->query("
    SELECT 
        mp.jenjang,
        COUNT(p.id_pelamar) as total
    FROM master_pendidikan mp
    LEFT JOIN pelamar p ON mp.id_pendidikan = p.pendidikan_terakhir
    GROUP BY mp.id_pendidikan, mp.jenjang
    ORDER BY mp.id_pendidikan
")->fetchAll();

// Data pelamar per agama
$agamaData = $pdo->query("
    SELECT 
        ma.nama_agama,
        COUNT(p.id_pelamar) as total
    FROM master_agama ma
    LEFT JOIN pelamar p ON ma.id_agama = p.id_agama
    GROUP BY ma.id_agama, ma.nama_agama
    ORDER BY ma.id_agama
")->fetchAll();

include '_layout_top.php';
?>

<!-- ============================================================ -->
<!-- HEADER -->
<!-- ============================================================ -->
<div class="topline">
    <div>
        <h1>📊 Laporan</h1>
        <p class="hint">Statistik dan laporan rekrutmen.</p>
    </div>
</div>

<!-- ============================================================ -->
<!-- STATISTIK UTAMA -->
<!-- ============================================================ -->
<div class="metric-grid">
    <div class="metric-card">
        <div class="metric-icon">👤</div>
        <div class="metric-content">
            <span class="metric-label">Total Pelamar</span>
            <span class="metric-value"><?= number_format($total_pelamar) ?></span>
        </div>
    </div>
    <div class="metric-card metric-warning">
        <div class="metric-icon">📄</div>
        <div class="metric-content">
            <span class="metric-label">Total Lamaran</span>
            <span class="metric-value"><?= number_format($total_lamaran) ?></span>
        </div>
    </div>
    <div class="metric-card metric-info">
        <div class="metric-icon">📎</div>
        <div class="metric-content">
            <span class="metric-label">Total Dokumen</span>
            <span class="metric-value"><?= number_format($total_dokumen) ?></span>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- STATUS LAMARAN -->
<!-- ============================================================ -->
<div class="card">
    <h3>📊 Status Lamaran</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;">
        <?php foreach($statusData as $s): ?>
            <div style="background:#f8fafc;padding:12px;border-radius:8px;text-align:center;">
                <div style="font-size:11px;color:#6b7280;"><?= htmlspecialchars($s['nama_status']) ?></div>
                <div style="font-size:22px;font-weight:700;color:#1a1a2e;"><?= number_format($s['total']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- LAMARAN PER BULAN -->
<!-- ============================================================ -->
<div class="card">
    <h3>📈 Lamaran Per Bulan (12 Bulan Terakhir)</h3>
    <?php if(empty($monthlyData)): ?>
        <p style="color:#999;text-align:center;padding:20px;">Belum ada data.</p>
    <?php else: ?>
        <div style="display:flex;align-items:flex-end;height:150px;gap:8px;padding:10px 0;">
            <?php 
            $max = max(array_column($monthlyData, 'total')) ?: 1;
            $monthlyData = array_reverse($monthlyData);
            foreach($monthlyData as $m): 
                $height = ($m['total'] / $max) * 120;
                $label = date('M Y', strtotime($m['bulan'] . '-01'));
            ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;">
                    <div style="width:100%;display:flex;justify-content:center;">
                        <div style="width:30px;background:#2563eb;border-radius:4px 4px 0 0;height:<?= max(5, $height) ?>px;min-height:5px;"></div>
                    </div>
                    <div style="font-size:10px;color:#6b7280;margin-top:5px;text-align:center;"><?= $label ?></div>
                    <div style="font-size:10px;font-weight:600;color:#1a1a2e;"><?= $m['total'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- LOWONGAN TERPOPULER -->
<!-- ============================================================ -->
<div class="card">
    <h3>🔥 Lowongan Terpopuler</h3>
    <?php if(empty($lowonganData)): ?>
        <p style="color:#999;text-align:center;padding:20px;">Belum ada data.</p>
    <?php else: ?>
        <?php foreach($lowonganData as $l): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f1f3f5;">
                <span><?= htmlspecialchars($l['nama_lowongan']) ?></span>
                <span class="badge badge-primary"><?= number_format($l['total']) ?> pelamar</span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- PENDIDIKAN PELAMAR -->
<!-- ============================================================ -->
<div class="card">
    <h3>🎓 Pendidikan Pelamar</h3>
    <?php if(empty($pendidikanData)): ?>
        <p style="color:#999;text-align:center;padding:20px;">Belum ada data.</p>
    <?php else: ?>
        <?php foreach($pendidikanData as $p): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f1f3f5;">
                <span><?= htmlspecialchars($p['jenjang']) ?></span>
                <span class="badge badge-info"><?= number_format($p['total']) ?> pelamar</span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- AGAMA PELAMAR -->
<!-- ============================================================ -->
<div class="card">
    <h3>🕌 Agama Pelamar</h3>
    <?php if(empty($agamaData)): ?>
        <p style="color:#999;text-align:center;padding:20px;">Belum ada data.</p>
    <?php else: ?>
        <?php foreach($agamaData as $a): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f1f3f5;">
                <span><?= htmlspecialchars($a['nama_agama']) ?></span>
                <span class="badge badge-info"><?= number_format($a['total']) ?> pelamar</span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include '_layout_bottom.php'; ?>