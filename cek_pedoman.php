<?php include '_layout_top.php';
$counts = [
 'perencanaan' => (int)$pdo->query("SELECT COUNT(*) FROM perencanaan_kebutuhan")->fetchColumn(),
 'pelaksana' => (int)$pdo->query("SELECT COUNT(*) FROM pelaksana_penerimaan WHERE status='Aktif'")->fetchColumn(),
 'lowongan' => (int)$pdo->query("SELECT COUNT(*) FROM lowongan WHERE status='Aktif'")->fetchColumn(),
 'pelamar' => (int)$pdo->query("SELECT COUNT(*) FROM pelamar")->fetchColumn(),
 'berkas' => (int)$pdo->query("SELECT COUNT(*) FROM pelamar WHERE status_berkas<>'Menunggu'")->fetchColumn(),
 'tes' => (int)$pdo->query("SELECT COUNT(*) FROM pelamar WHERE nilai_tes_akademik IS NOT NULL OR nilai_toefl IS NOT NULL OR nilai_keterampilan IS NOT NULL OR nilai_wawancara IS NOT NULL")->fetchColumn(),
 'pengumuman' => (int)$pdo->query("SELECT COUNT(*) FROM pelamar WHERE status_email_panggilan='Sudah Dikirim' OR tanggal_pengumuman IS NOT NULL")->fetchColumn(),
 'pembekalan' => (int)$pdo->query("SELECT COUNT(*) FROM pelamar WHERE status_pembekalan<>'Belum' OR tanggal_pembekalan IS NOT NULL")->fetchColumn(),
 'sk' => (int)$pdo->query("SELECT COUNT(*) FROM pelamar WHERE nomor_sk IS NOT NULL AND nomor_sk<>''")->fetchColumn(),
 'audit' => (int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn(),
];
$items = [
 ['Perencanaan kebutuhan dosen/tendik', 'perencanaan', 'Dosen berdasarkan kebutuhan prodi; tendik berdasarkan analisis jabatan dan beban kerja.'],
 ['Penunjukan pelaksana penerimaan', 'pelaksana', 'Rektor menunjuk pelaksana; ketua Kepala BAUK/SDI.'],
 ['Lowongan/rekrutmen terbuka', 'lowongan', 'Lowongan aktif tampil untuk pelamar.'],
 ['Akun pelamar dan pengiriman lamaran', 'pelamar', 'Pelamar daftar akun lalu mengirim lamaran.'],
 ['Seleksi administrasi SDI', 'berkas', 'Status berkas diverifikasi: menunggu/memenuhi/tidak memenuhi.'],
 ['Tahapan tes dan wawancara', 'tes', 'TPA, TOEFL untuk dosen, tes keterampilan untuk tendik, wawancara.'],
 ['Pengumuman/panggilan email', 'pengumuman', 'Status/tanggal pengumuman dicatat.'],
 ['Pembekalan BPH/Pimpinan', 'pembekalan', 'Status dan tanggal pembekalan dicatat.'],
 ['SK dan penempatan', 'sk', 'Penerbit SK otomatis: dosen BPH, tendik Rektor; penempatan unit dicatat.'],
 ['Audit trail akuntabilitas', 'audit', 'Aktivitas penting tercatat untuk bukti proses.'],
];
?>
<h1>Cek Kesesuaian Pedoman</h1>
<div class="card"><h3>Matriks Kepatuhan Sistem</h3><p>Checklist ini digunakan untuk memastikan aplikasi menjalankan alur rekrutmen secara tertib, tetapi juga mengikuti alur pedoman SDI: perencanaan, pelaksana penerimaan, seleksi, pengumuman, pembekalan, SK, dan penempatan.</p></div><br>
<div class="table-wrap"><table><tr><th>Aspek Pedoman</th><th>Status Data</th><th>Keterangan</th></tr>
<?php foreach($items as $it): $ok = $counts[$it[1]] > 0; ?>
<tr><td><b><?=e($it[0])?></b></td><td><span class="pill <?=$ok?'pill-ok':'pill-wait'?>"><?=$ok?'Ada':'Belum Ada'?> (<?=e($counts[$it[1]])?>)</span></td><td><?=e($it[2])?></td></tr>
<?php endforeach; ?>
</table></div>
<?php include '_layout_bottom.php'; ?>
