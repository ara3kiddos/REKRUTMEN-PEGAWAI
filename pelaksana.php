<?php include '_layout_top.php';
$message='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $stmt=$pdo->prepare("INSERT INTO pelaksana_penerimaan (tahun, nomor_surat, ketua, anggota, tugas, status) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$_POST['tahun'], trim($_POST['nomor_surat'] ?? ''), trim($_POST['ketua']), trim($_POST['anggota'] ?? ''), trim($_POST['tugas'] ?? ''), $_POST['status'] ?? 'Aktif']);
    $message='<div class="alert alert-success">Data pelaksana penerimaan berhasil disimpan.</div>';
}
$list=$pdo->query("SELECT * FROM pelaksana_penerimaan ORDER BY tahun DESC, id DESC")->fetchAll();
?>
<h1>Pelaksana Penerimaan</h1>
<?= $message ?>
<div class="card"><h3>Dasar Pedoman</h3><p>Pedoman mensyaratkan adanya penunjukan pelaksana penerimaan dosen dan tenaga kependidikan oleh Rektor, diketuai oleh Kepala BAUK/SDI. Menu ini dipakai untuk mencatat tim/panitia penerimaan agar proses seleksi lebih akuntabel.</p></div><br>
<form class="form" method="POST"><div class="form-grid">
<div class="field"><label>Tahun</label><input type="number" name="tahun" value="<?=date('Y')?>" required></div>
<div class="field"><label>Nomor Surat/Keputusan</label><input name="nomor_surat" placeholder="Contoh: .../UM-BJM/..." ></div>
<div class="field"><label>Ketua Pelaksana</label><input name="ketua" placeholder="Contoh: Kepala BAUK/SDI" required></div>
<div class="field"><label>Status</label><select name="status"><option>Aktif</option><option>Selesai</option><option>Dibatalkan</option></select></div>
<div class="field full"><label>Anggota</label><textarea name="anggota" placeholder="Nama anggota/tim seleksi"></textarea></div>
<div class="field full"><label>Tugas Utama</label><textarea name="tugas" placeholder="Melakukan seleksi calon dosen dan tenaga kependidikan dari pelamar umum/honorer"></textarea></div>
</div><br><button class="btn btn-primary" type="submit">Simpan Pelaksana</button></form><br>
<div class="table-wrap"><table><tr><th>Tahun</th><th>Nomor Surat</th><th>Ketua</th><th>Anggota</th><th>Tugas</th><th>Status</th></tr><?php foreach($list as $r): ?><tr><td><?=e($r['tahun'])?></td><td><?=e($r['nomor_surat'] ?: '-')?></td><td><?=e($r['ketua'])?></td><td><?=nl2br(e($r['anggota'] ?: '-'))?></td><td><?=nl2br(e($r['tugas'] ?: '-'))?></td><td><span class="pill pill-blue"><?=e($r['status'])?></span></td></tr><?php endforeach; ?></table></div>
<?php include '_layout_bottom.php'; ?>
