<?php include '_layout_top.php';
$logs = $pdo->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 300")->fetchAll();
?>
<h1>Audit Trail</h1>
<div class="card"><h3>Akuntabilitas Proses Rekrutmen</h3><p>Halaman ini mencatat aktivitas penting seperti pelamar mengirim lamaran dan admin mengubah tahapan seleksi. Audit trail membantu membuktikan proses berjalan transparan dan akuntabel.</p></div><br>
<div class="table-wrap"><table><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Data</th><th>Detail</th></tr>
<?php foreach($logs as $l): ?>
<tr><td><?=e($l['created_at'])?></td><td><?=e($l['user_type'])?> #<?=e($l['user_id'] ?: '-')?></td><td><b><?=e($l['aksi'])?></b></td><td><?=e($l['tabel'] ?: '-')?> #<?=e($l['record_id'] ?: '-')?></td><td><?=e($l['detail'] ?: '-')?></td></tr>
<?php endforeach; ?>
</table></div>
<?php include '_layout_bottom.php'; ?>
