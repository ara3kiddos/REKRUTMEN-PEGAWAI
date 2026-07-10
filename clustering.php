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
// FUNGSI K-MEANS CLUSTERING
// ============================================================

function euclideanDistance($a, $b) {
    $sum = 0;
    for ($i = 0; $i < count($a); $i++) {
        $sum += pow($a[$i] - $b[$i], 2);
    }
    return sqrt($sum);
}

function kMeansClustering($data, $k = 3, $maxIterations = 100) {
    if (empty($data)) return [];
    
    $n = count($data);
    $d = count($data[0]['vector']);
    
    // Inisialisasi centroid secara acak
    $centroids = [];
    $indices = array_rand($data, min($k, $n));
    if (!is_array($indices)) $indices = [$indices];
    
    foreach ($indices as $idx) {
        $centroids[] = $data[$idx]['vector'];
    }
    
    if (count($centroids) < $k) {
        $k = count($centroids);
    }
    
    $clusters = [];
    $iteration = 0;
    $changed = true;
    
    while ($changed && $iteration < $maxIterations) {
        $changed = false;
        $newClusters = array_fill(0, $k, []);
        
        foreach ($data as $idx => $item) {
            $minDist = INF;
            $clusterIdx = 0;
            
            for ($c = 0; $c < $k; $c++) {
                $dist = euclideanDistance($item['vector'], $centroids[$c]);
                if ($dist < $minDist) {
                    $minDist = $dist;
                    $clusterIdx = $c;
                }
            }
            
            $newClusters[$clusterIdx][] = $idx;
        }
        
        for ($c = 0; $c < $k; $c++) {
            if (!isset($clusters[$c]) || $clusters[$c] != $newClusters[$c]) {
                $changed = true;
                break;
            }
        }
        
        $clusters = $newClusters;
        
        for ($c = 0; $c < $k; $c++) {
            if (empty($clusters[$c])) continue;
            
            $newCentroid = array_fill(0, $d, 0);
            foreach ($clusters[$c] as $idx) {
                for ($i = 0; $i < $d; $i++) {
                    $newCentroid[$i] += $data[$idx]['vector'][$i];
                }
            }
            for ($i = 0; $i < $d; $i++) {
                $newCentroid[$i] /= count($clusters[$c]);
            }
            $centroids[$c] = $newCentroid;
        }
        
        $iteration++;
    }
    
    $result = [];
    foreach ($clusters as $c => $indices) {
        $result[$c] = [
            'centroid' => $centroids[$c],
            'members' => $indices,
            'count' => count($indices),
            'data' => []
        ];
        foreach ($indices as $idx) {
            $result[$c]['data'][] = $data[$idx];
        }
    }
    
    return $result;
}

// ============================================================
// AMBIL DATA PELAMAR UNTUK CLUSTERING
// ============================================================

$k = isset($_POST['k']) ? (int)$_POST['k'] : 3;
$clusteringResult = null;

// Ambil data pelamar yang sudah punya status lamaran
// Gunakan data dari tabel lamaran dan pelamar
$pelamarData = $pdo->query("
    SELECT 
        p.id_pelamar,
        u.nama_lengkap,
        u.email,
        l.nama_lowongan,
        lm.id_lamaran,
        lm.tanggal_lamaran,
        lm.id_status_lamaran,
        ms.nama_status
    FROM pelamar p
    JOIN users u ON p.id_user = u.id_user
    LEFT JOIN lamaran lm ON p.id_pelamar = lm.id_pelamar
    LEFT JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
    LEFT JOIN master_status_lamaran ms ON lm.id_status_lamaran = ms.id_master_status_lamaran
    ORDER BY p.id_pelamar
")->fetchAll();

// ============================================================
// GENERATE DATA NILAI SIMULASI UNTUK CLUSTERING
// ============================================================

$data = [];
foreach ($pelamarData as $p) {
    // Generate nilai simulasi berdasarkan status
    // Ini hanya untuk demo clustering
    $statusId = (int)($p['id_status_lamaran'] ?? 1);
    
    // Nilai simulasi berdasarkan status
    switch ($statusId) {
        case 1: // Lamaran Dikirim
            $nilai_akademik = rand(40, 65);
            $nilai_psikotes = rand(40, 60);
            $nilai_toefl = rand(35, 55);
            $nilai_keterampilan = rand(40, 60);
            break;
        case 2: // Seleksi Administrasi
            $nilai_akademik = rand(50, 70);
            $nilai_psikotes = rand(45, 65);
            $nilai_toefl = rand(40, 60);
            $nilai_keterampilan = rand(45, 65);
            break;
        case 4: // Tes Akademik
        case 5: // Tes Psikotes
        case 6: // Tes TOEFL
        case 7: // Tes Keterampilan
        case 8: // Wawancara
            $nilai_akademik = rand(60, 85);
            $nilai_psikotes = rand(55, 80);
            $nilai_toefl = rand(50, 75);
            $nilai_keterampilan = rand(55, 80);
            break;
        case 14: // Pengangkatan SK
        case 15: // Penempatan Kerja
        case 16: // Diterima
            $nilai_akademik = rand(75, 95);
            $nilai_psikotes = rand(70, 90);
            $nilai_toefl = rand(65, 85);
            $nilai_keterampilan = rand(70, 90);
            break;
        default:
            $nilai_akademik = rand(50, 70);
            $nilai_psikotes = rand(50, 70);
            $nilai_toefl = rand(45, 65);
            $nilai_keterampilan = rand(50, 70);
    }
    
    $data[] = [
        'id_pelamar' => $p['id_pelamar'],
        'nama' => $p['nama_lengkap'],
        'email' => $p['email'],
        'lowongan' => $p['nama_lowongan'] ?? '-',
        'status' => $p['nama_status'] ?? 'Lamaran Dikirim',
        'nilai' => [
            'akademik' => $nilai_akademik,
            'psikotes' => $nilai_psikotes,
            'toefl' => $nilai_toefl,
            'keterampilan' => $nilai_keterampilan
        ],
        'vector' => [
            $nilai_akademik,
            $nilai_psikotes,
            $nilai_toefl,
            $nilai_keterampilan
        ]
    ];
}

// Jalankan clustering jika ada data
if (!empty($data) && $k > 1 && isset($_POST['run_clustering'])) {
    $clusteringResult = kMeansClustering($data, $k);
}

include '_layout_top.php';
?>

<!-- ============================================================ -->
<!-- HEADER -->
<!-- ============================================================ -->
<div class="topline">
    <div>
        <h1>📊 K-Means Clustering</h1>
        <p class="hint">Clustering pelamar berdasarkan nilai simulasi untuk membantu pengambilan keputusan.</p>
    </div>
    <div>
        <span class="badge badge-primary">Data Pelamar: <?= count($data) ?></span>
        <span class="badge badge-info">Cluster: <?= $k ?></span>
    </div>
</div>

<!-- ============================================================ -->
<!-- FORM CLUSTERING -->
<!-- ============================================================ -->
<div class="card">
    <h3>⚙️ Parameter Clustering</h3>
    <form method="POST" style="display:flex;gap:15px;align-items:center;flex-wrap:wrap;">
        <div>
            <label>Jumlah Cluster (K):</label>
            <select name="k" style="padding:8px 12px;border-radius:6px;border:1px solid #d1d5db;">
                <option value="2" <?= $k == 2 ? 'selected' : '' ?>>2</option>
                <option value="3" <?= $k == 3 ? 'selected' : '' ?>>3</option>
                <option value="4" <?= $k == 4 ? 'selected' : '' ?>>4</option>
                <option value="5" <?= $k == 5 ? 'selected' : '' ?>>5</option>
            </select>
        </div>
        <button type="submit" name="run_clustering" class="btn btn-primary">▶️ Jalankan Clustering</button>
        <span style="font-size:12px;color:#6b7280;margin-left:10px;">
            ⚡ Nilai adalah simulasi untuk demo clustering
        </span>
    </form>
</div>

<!-- ============================================================ -->
<!-- HASIL CLUSTERING -->
<!-- ============================================================ -->

<?php if ($clusteringResult !== null && !empty($clusteringResult)): ?>
    <div class="card">
        <h3>📊 Hasil Clustering</h3>
        
        <!-- Summary -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:20px;">
            <?php foreach ($clusteringResult as $id => $cluster): ?>
                <div style="background:#f8fafc;padding:12px;border-radius:8px;text-align:center;border-left:4px solid <?= ['#2563eb','#10b981','#f59e0b','#ef4444','#8b5cf6'][$id] ?? '#6b7280' ?>">
                    <div style="font-size:12px;color:#6b7280;">Cluster <?= $id + 1 ?></div>
                    <div style="font-size:24px;font-weight:700;color:#1a1a2e;"><?= $cluster['count'] ?></div>
                    <div style="font-size:12px;color:#6b7280;">Pelamar</div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Detail per cluster -->
        <?php foreach ($clusteringResult as $id => $cluster): 
            $colors = ['#dbeafe','#d1fae5','#fef3c7','#fee2e2','#ede9fe'];
            $color = $colors[$id] ?? '#f3f4f6';
        ?>
            <div style="background:<?= $color ?>;padding:15px;border-radius:8px;margin-bottom:15px;">
                <h4 style="margin-top:0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                    <span>Cluster <?= $id + 1 ?> (<?= $cluster['count'] ?> pelamar)</span>
                    <span style="font-size:12px;font-weight:400;color:#6b7280;">
                        Centroid: [<?= implode(', ', array_map(function($v){ return round($v,1); }, $cluster['centroid'])) ?>]
                    </span>
                </h4>
                
                <?php if (!empty($cluster['data'])): ?>
                    <div class="table-wrap">
                        <table class="table" style="background:white;border-radius:6px;overflow:hidden;font-size:13px;">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama</th>
                                    <th>Lowongan</th>
                                    <th>Status</th>
                                    <th>Akademik</th>
                                    <th>Psikotes</th>
                                    <th>TOEFL</th>
                                    <th>Keterampilan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($cluster['data'] as $member): ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><strong><?= htmlspecialchars($member['nama']) ?></strong></td>
                                        <td><?= htmlspecialchars($member['lowongan']) ?></td>
                                        <td><span class="badge badge-info"><?= htmlspecialchars($member['status']) ?></span></td>
                                        <td><?= $member['nilai']['akademik'] ?: '-' ?></td>
                                        <td><?= $member['nilai']['psikotes'] ?: '-' ?></td>
                                        <td><?= $member['nilai']['toefl'] ?: '-' ?></td>
                                        <td><?= $member['nilai']['keterampilan'] ?: '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <!-- Rekomendasi -->
        <?php if (!empty($clusteringResult)): 
            $bestCluster = null;
            $bestScore = 0;
            foreach ($clusteringResult as $id => $cluster) {
                $avgScore = array_sum($cluster['centroid']) / count($cluster['centroid']);
                if ($avgScore > $bestScore && $cluster['count'] > 0) {
                    $bestScore = $avgScore;
                    $bestCluster = $id;
                }
            }
        ?>
            <div style="background:#f0fdf4;padding:15px;border-radius:8px;border-left:4px solid #10b981;margin-top:15px;">
                <h4 style="margin-top:0;color:#065f46;">💡 Rekomendasi</h4>
                <?php if ($bestCluster !== null): ?>
                    <p><strong>Cluster <?= $bestCluster + 1 ?></strong> memiliki rata-rata nilai tertinggi (<?= round($bestScore, 1) ?>). 
                    Pelamar di cluster ini direkomendasikan untuk diprioritaskan.</p>
                <?php endif; ?>
                <p style="font-size:13px;color:#6b7280;margin:5px 0 0;">
                    * Rekomendasi berdasarkan hasil clustering menggunakan algoritma K-Means.<br>
                    * Nilai yang digunakan adalah data simulasi untuk demo.
                </p>
            </div>
        <?php endif; ?>
    </div>
<?php elseif (isset($_POST['run_clustering'])): ?>
    <div class="card" style="text-align:center;padding:40px;">
        <h3>⚠️ Tidak Cukup Data</h3>
        <p>Data yang tersedia untuk clustering kurang dari <?= $k ?> pelamar.</p>
        <p style="color:#6b7280;font-size:13px;">Minimal <?= $k ?> pelamar diperlukan untuk clustering.</p>
    </div>
<?php elseif (!empty($data)): ?>
    <div class="card" style="text-align:center;padding:40px;">
        <h3>📊 Siap Clustering</h3>
        <p>Data <?= count($data) ?> pelamar siap untuk di-cluster.</p>
        <p style="color:#6b7280;font-size:13px;">Klik tombol "Jalankan Clustering" untuk memproses.</p>
    </div>
<?php else: ?>
    <div class="card" style="text-align:center;padding:40px;">
        <h3>📊 Belum Ada Data Pelamar</h3>
        <p>Belum ada data pelamar untuk di-cluster.</p>
        <p style="color:#6b7280;font-size:13px;">Silakan tambahkan pelamar terlebih dahulu.</p>
    </div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- INFORMASI -->
<!-- ============================================================ -->
<div class="card" style="background:#f0f4ff;border-color:#93c5fd;">
    <h4>ℹ️ Tentang K-Means Clustering</h4>
    <ul style="margin:10px 0 0;padding-left:20px;color:#475569;font-size:14px;">
        <li><strong>K-Means</strong> adalah algoritma clustering yang mengelompokkan data berdasarkan kemiripan.</li>
        <li>Data yang digunakan adalah <strong>nilai simulasi</strong> (Akademik, Psikotes, TOEFL, Keterampilan).</li>
        <li>Nilai <strong>K</strong> menentukan jumlah cluster yang akan dibentuk.</li>
        <li>Semakin tinggi nilai centroid, semakin baik performa pelamar di cluster tersebut.</li>
    </ul>
</div>

<?php include '_layout_bottom.php'; ?>