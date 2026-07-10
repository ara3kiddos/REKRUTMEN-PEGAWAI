<?php include '_layout_top.php';
$message = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $stmt=$pdo->prepare("INSERT INTO perencanaan_kebutuhan (
        tahun, unit_pengusul, fakultas_biro, jenis_kebutuhan, posisi, jumlah_kebutuhan,
        rasio_dosen_mahasiswa, beban_mengajar, sks_kelulusan, analisis_jabatan,
        analisis_beban_kerja, spesifikasi_keahlian, alasan, status
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $_POST['tahun'], $_POST['unit_pengusul'], $_POST['fakultas_biro'], $_POST['jenis_kebutuhan'], $_POST['posisi'], $_POST['jumlah_kebutuhan'],
        $_POST['rasio_dosen_mahasiswa'] ?: null, $_POST['beban_mengajar'] ?: null, $_POST['sks_kelulusan'] ?: null, $_POST['analisis_jabatan'],
        $_POST['analisis_beban_kerja'], $_POST['spesifikasi_keahlian'], $_POST['alasan'], $_POST['status']
    ]);
    $message = '<div class="alert alert-success">Usulan perencanaan kebutuhan berhasil disimpan.</div>';
}
$list=$pdo->query("SELECT * FROM perencanaan_kebutuhan ORDER BY tahun DESC, id DESC")->fetchAll();
?>
<h1>Perencanaan Kebutuhan SDI</h1>
<?= $message ?>
<div class="card">
  <h3>Fungsi Modul</h3>
  <p>Modul ini dibuat agar rekrutmen tidak langsung membuka lowongan, tetapi diawali usulan kebutuhan dari prodi/unit. Untuk dosen dicatat rasio dosen-mahasiswa, beban mengajar, dan SKS kelulusan. Untuk tenaga kependidikan dicatat analisis jabatan dan beban kerja.</p>
</div><br>
<form class="form" method="POST">
  <div class="form-grid">
    <div class="field"><label>Tahun Perencanaan</label><input type="number" name="tahun" value="<?=date('Y')?>" required></div>
    <div class="field"><label>Jenis Kebutuhan</label><select name="jenis_kebutuhan"><option>Dosen</option><option>Tenaga Kependidikan</option></select></div>
    <div class="field"><label>Unit Pengusul/Prodi</label><input name="unit_pengusul" placeholder="Contoh: Prodi Informatika" required></div>
    <div class="field"><label>Fakultas/Biro</label><input name="fakultas_biro" placeholder="Contoh: Fakultas Teknik / BAUK" required></div>
    <div class="field"><label>Posisi/Jabatan Dibutuhkan</label><input name="posisi" placeholder="Contoh: Dosen Informatika / Laboran" required></div>
    <div class="field"><label>Jumlah Kebutuhan</label><input type="number" name="jumlah_kebutuhan" min="1" value="1" required></div>
    <div class="field"><label>Rasio Dosen-Mahasiswa <span class="hint">khusus dosen</span></label><input name="rasio_dosen_mahasiswa" placeholder="Contoh: 1:30"></div>
    <div class="field"><label>Beban Mengajar <span class="hint">khusus dosen</span></label><input name="beban_mengajar" placeholder="Contoh: 12 SKS/dosen"></div>
    <div class="field"><label>Jumlah SKS Kelulusan <span class="hint">khusus dosen</span></label><input name="sks_kelulusan" placeholder="Contoh: 144 SKS"></div>
    <div class="field"><label>Status Usulan</label><select name="status"><option>Diusulkan</option><option>Disetujui</option><option>Ditolak</option></select></div>
    <div class="field full"><label>Analisis Jabatan <span class="hint">khusus tendik / opsional dosen</span></label><textarea name="analisis_jabatan" placeholder="Uraian fungsi jabatan, tugas pokok, tanggung jawab"></textarea></div>
    <div class="field full"><label>Analisis Beban Kerja</label><textarea name="analisis_beban_kerja" placeholder="Uraikan volume kerja, kebutuhan pegawai, atau beban layanan"></textarea></div>
    <div class="field full"><label>Spesifikasi Keahlian</label><textarea name="spesifikasi_keahlian" placeholder="Contoh: mampu mengelola jaringan, laboratorium, arsip, atau bidang akademik tertentu"></textarea></div>
    <div class="field full"><label>Alasan Kebutuhan</label><textarea name="alasan" placeholder="Alasan pembukaan kebutuhan/formasi" required></textarea></div>
  </div><br>
  <button class="btn btn-primary">Simpan Perencanaan</button>
</form><br>
<div class="table-wrap"><table>
<tr><th>Tahun</th><th>Unit/Fakultas</th><th>Jenis & Posisi</th><th>Dasar Analisis</th><th>Jumlah</th><th>Status</th></tr>
<?php foreach($list as $r): ?>
<tr>
<td><?=e($r['tahun'])?></td>
<td><b><?=e($r['unit_pengusul'])?></b><br><?=e($r['fakultas_biro'] ?: '-')?></td>
<td><?=e($r['jenis_kebutuhan'])?><br><b><?=e($r['posisi'])?></b></td>
<td>Rasio: <?=e($r['rasio_dosen_mahasiswa'] ?: '-')?><br>Beban: <?=e($r['beban_mengajar'] ?: '-')?><br>SKS: <?=e($r['sks_kelulusan'] ?: '-')?><br><span class="hint"><?=e(mb_substr((string)$r['analisis_jabatan'],0,90))?></span></td>
<td><?=e($r['jumlah_kebutuhan'])?></td>
<td><span class="pill pill-blue"><?=e($r['status'])?></span></td>
</tr>
<?php endforeach; ?>
</table></div>
<?php include '_layout_bottom.php'; ?>
