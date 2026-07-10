<?php include '_layout_top.php';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $kategori = $_POST['kategori'] === 'Dosen' ? 'Dosen' : 'Tenaga Kependidikan';
    $pendMin = $kategori === 'Dosen' ? 'S2' : 'SMA/SMK';
    $stmt=$pdo->prepare("INSERT INTO lowongan (
        perencanaan_id,nama_posisi,kategori,unit_kerja,pendidikan_min,pengalaman_min,sertifikat_wajib,
        spesifikasi_keahlian,uraian_jabatan,beban_kerja,kompetensi_wajib,status
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $_POST['perencanaan_id'] ?: null,
        $kategori,
        $kategori,
        $_POST['unit_kerja'],
        $pendMin,
        (int)($_POST['pengalaman_min'] ?? 0),
        $_POST['sertifikat_wajib'],
        $_POST['spesifikasi_keahlian'],
        $_POST['uraian_jabatan'],
        $_POST['beban_kerja'],
        $_POST['kompetensi_wajib'],
        $_POST['status']
    ]);
    echo '<div class="alert alert-success">Lowongan berhasil ditambahkan sesuai dua kategori pedoman: Dosen atau Tenaga Kependidikan.</div>';
}
$list=$pdo->query("SELECT l.*, pk.unit_pengusul, pk.fakultas_biro, pk.jumlah_kebutuhan FROM lowongan l LEFT JOIN perencanaan_kebutuhan pk ON l.perencanaan_id=pk.id ORDER BY l.id DESC")->fetchAll();
$perencanaan=$pdo->query("SELECT * FROM perencanaan_kebutuhan WHERE status='Disetujui' ORDER BY tahun DESC, id DESC")->fetchAll();
?>
<h1>Kelola Lowongan</h1>
<div class="grid-2">
  <div class="card">
    <h3>Dosen</h3>
    <p><b>Persyaratan umum:</b> beragama Islam, minimal S2, berakhlak mulia dan memiliki integritas tinggi, usia maksimal 58 tahun saat diangkat menjadi dosen, sehat jasmani-rohani, dan tidak menggunakan narkotika.</p>
    <p><b>Administrasi:</b> surat lamaran, CV/biodata riwayat pengalaman kerja, akun email, fotokopi KTP, pas foto, fotokopi KK, surat keterangan sehat, fotokopi ijazah S1 dan S2 beserta transkrip, ijazah/transkrip S3 opsional, serta SK penyetaraan ijazah opsional bagi lulusan luar negeri.</p>
  </div>
  <div class="card">
    <h3>Tenaga Kependidikan</h3>
    <p><b>Persyaratan umum:</b> beragama Islam, pendidikan terakhir minimal SMA/sederajat, sehat jasmani-rohani, dan sesuai spesifikasi keahlian yang ditentukan.</p>
    <p><b>Administrasi:</b> surat lamaran, CV/biodata riwayat pengalaman kerja, akun email, fotokopi KTP, pas foto, fotokopi KK, surat keterangan sehat, fotokopi ijazah terakhir beserta transkrip nilai.</p>
  </div>
</div><br>
<form class="form" method="POST"><div class="form-grid">
<div class="field full"><label>Hubungkan dengan Perencanaan Kebutuhan</label><select name="perencanaan_id"><option value="">Tidak dihubungkan</option><?php foreach($perencanaan as $r): ?><option value="<?=e($r['id'])?>"><?=e($r['tahun'])?> - <?=e($r['jenis_kebutuhan'])?> - <?=e($r['posisi'])?> (<?=e($r['unit_pengusul'])?>)</option><?php endforeach; ?></select></div>
<div class="field"><label>Kategori Lowongan</label><select name="kategori"><option>Dosen</option><option>Tenaga Kependidikan</option></select><span class="hint">Pedoman hanya membedakan dosen dan tenaga kependidikan.</span></div>
<div class="field"><label>Unit Kerja/Prodi</label><input name="unit_kerja" placeholder="Contoh: Prodi Informatika / BAUK" required></div>
<div class="field"><label>Pengalaman Minimal</label><input type="number" name="pengalaman_min" value="0" min="0"></div>
<div class="field"><label>Sertifikat Wajib/Opsional</label><input name="sertifikat_wajib" placeholder="Contoh: TOEFL, sertifikat kompetensi, dll."></div>
<div class="field"><label>Status</label><select name="status"><option>Aktif</option><option>Nonaktif</option></select></div>
<div class="field full"><label>Spesifikasi Keahlian</label><textarea name="spesifikasi_keahlian" placeholder="Keahlian khusus sesuai jabatan/unit kerja"></textarea></div>
<div class="field full"><label>Uraian Jabatan</label><textarea name="uraian_jabatan" placeholder="Tugas pokok dan tanggung jawab"></textarea></div>
<div class="field full"><label>Beban Kerja</label><textarea name="beban_kerja" placeholder="Gambaran beban kerja/layanan"></textarea></div>
<div class="field full"><label>Kompetensi Wajib</label><textarea name="kompetensi_wajib" placeholder="Kompetensi minimal yang harus dipenuhi"></textarea></div>
</div><br><button class="btn btn-primary">Tambah Lowongan</button></form><br>
<div class="table-wrap"><table><tr><th>Lowongan</th><th>Perencanaan</th><th>Persyaratan Sistem</th><th>Keahlian/Jabatan</th><th>Status</th></tr><?php foreach($list as $l): ?><tr><td><b><?=e($l['kategori'])?></b><br><?=e($l['unit_kerja'] ?: '-')?></td><td><?= $l['perencanaan_id'] ? e($l['unit_pengusul'].' / '.$l['fakultas_biro'].' · '.$l['jumlah_kebutuhan'].' org') : '-' ?></td><td>Pendidikan minimal: <?=e($l['pendidikan_min'])?><br>Pengalaman: <?=e($l['pengalaman_min'])?> tahun<br>Sertifikat: <?=e($l['sertifikat_wajib'] ?: '-')?></td><td><?=nl2br(e(mb_substr((string)$l['spesifikasi_keahlian'],0,120)))?><br><span class="hint"><?=nl2br(e(mb_substr((string)$l['uraian_jabatan'],0,120)))?></span></td><td><?=e($l['status'])?></td></tr><?php endforeach; ?></table></div><?php include '_layout_bottom.php'; ?>
