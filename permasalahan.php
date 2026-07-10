<?php include '_layout_top.php';
$total = (int)$pdo->query("SELECT COUNT(*) FROM pelamar")->fetchColumn();
$belumVerifikasi = (int)$pdo->query("SELECT COUNT(*) FROM pelamar WHERE status_berkas='Menunggu'")->fetchColumn();
$perencanaan = (int)$pdo->query("SELECT COUNT(*) FROM perencanaan_kebutuhan")->fetchColumn();
$lowonganTanpaRencana = (int)$pdo->query("SELECT COUNT(*) FROM lowongan WHERE perencanaan_id IS NULL")->fetchColumn();
$dosenNonS2 = (int)$pdo->query("SELECT COUNT(*) FROM pelamar p JOIN lowongan l ON p.lowongan_id=l.id WHERE (l.kategori='Dosen' OR l.nama_posisi LIKE '%Dosen%' OR p.jabatan_dilamar LIKE '%Dosen%') AND COALESCE(p.pendidikan_skor_import, CASE p.pendidikan_terakhir WHEN 'S2' THEN 90 WHEN 'S3' THEN 100 ELSE 0 END) < 90")->fetchColumn();
$dosenNoToefl = (int)$pdo->query("SELECT COUNT(*) FROM pelamar p JOIN lowongan l ON p.lowongan_id=l.id WHERE (l.kategori='Dosen' OR l.nama_posisi LIKE '%Dosen%' OR p.jabatan_dilamar LIKE '%Dosen%') AND p.punya_toefl='Tidak' AND (p.nilai_toefl IS NULL OR p.nilai_toefl=0)")->fetchColumn();
$tendikNoSkill = (int)$pdo->query("SELECT COUNT(*) FROM pelamar p JOIN lowongan l ON p.lowongan_id=l.id WHERE NOT (l.kategori='Dosen' OR l.nama_posisi LIKE '%Dosen%' OR p.jabatan_dilamar LIKE '%Dosen%') AND (p.nilai_keterampilan IS NULL OR p.nilai_keterampilan=0)")->fetchColumn();
$belumSk = (int)$pdo->query("SELECT COUNT(*) FROM pelamar WHERE tahap_lamaran IN ('Pengangkatan SK','Diterima') AND (nomor_sk IS NULL OR nomor_sk='')")->fetchColumn();
$clusterBelum = (int)$pdo->query("SELECT COUNT(*) FROM pelamar WHERE status_berkas='Memenuhi' AND cluster_label IS NULL")->fetchColumn();
$syaratUmum = (int)$pdo->query("SELECT COUNT(*) FROM pelamar WHERE agama<>'Islam' OR pernyataan_integritas<>'Ya' OR bebas_narkotika<>'Ya'")->fetchColumn();
?>
<h1>Titik Permasalahan Rekrutmen</h1>
<div class="metric-grid">
  <div class="metric">Total Pelamar<b><?=e($total)?></b></div>
  <div class="metric">Berkas Belum Diverifikasi<b><?=e($belumVerifikasi)?></b></div>
  <div class="metric">Lowongan Tanpa Rencana<b><?=e($lowonganTanpaRencana)?></b></div>
  <div class="metric">Siap Rekomendasi Belum Diproses<b><?=e($clusterBelum)?></b></div>
</div>
<div class="card"><h3>Masalah Utama dari Bidang Rekrutmen</h3>
<ol class="list">
<li><b>Perencanaan kebutuhan belum selalu menjadi dasar lowongan.</b> Pedoman mengawali rekrutmen dari kebutuhan prodi/unit, sehingga sistem perlu modul usulan kebutuhan sebelum lowongan dibuka.</li>
<li><b>Persyaratan dosen dan tenaga kependidikan berbeda.</b> Dosen minimal S2, TOEFL/sejenisnya, dan usia maksimal 58 tahun; tendik minimal SMA/sederajat serta sesuai spesifikasi keahlian.</li>
<li><b>Dokumen administrasi cukup banyak.</b> Surat lamaran, CV, KTP, pas foto, KK, surat sehat, ijazah, transkrip, serta SK penyetaraan perlu checklist agar tidak tercecer.</li>
<li><b>Alur seleksi panjang dan melibatkan banyak pihak.</b> SDI/BAUK, prodi/unit, dekan, BPH/pimpinan, dan rektor perlu status tracking yang jelas.</li>
<li><b>Hasil tes harus dipisah per jenis seleksi.</b> TPA, TOEFL, keterampilan, wawancara, psikologi/kepribadian perlu kolom berbeda agar audit seleksi jelas.</li>
<li><b>Rekomendasi Pelamar tidak boleh jadi keputusan akhir.</b> Rekomendasi Pelamar hanya pengelompokan pelamar, sedangkan keputusan tetap lewat rekomendasi, rapat, pembekalan, dan SK.</li>
</ol></div><br>
<div class="table-wrap"><table><tr><th>Indikator Masalah</th><th>Jumlah</th><th>Solusi di Sistem</th></tr>
<tr><td>Perencanaan kebutuhan tersimpan</td><td><?=e($perencanaan)?></td><td>Menu Perencanaan Kebutuhan SDI.</td></tr>
<tr><td>Lowongan belum terhubung ke perencanaan</td><td><?=e($lowonganTanpaRencana)?></td><td>Lowongan dapat dihubungkan dengan usulan kebutuhan yang disetujui.</td></tr>
<tr><td>Syarat umum belum sesuai/tercatat</td><td><?=e($syaratUmum)?></td><td>Form agama, integritas, dan bebas narkotika.</td></tr>
<tr><td>Dosen belum memenuhi minimal S2</td><td><?=e($dosenNonS2)?></td><td>Validasi otomatis saat submit lamaran dosen.</td></tr>
<tr><td>Dosen belum memiliki TOEFL/sejenisnya</td><td><?=e($dosenNoToefl)?></td><td>Kolom nilai TOEFL dan catatan tes Bahasa Inggris.</td></tr>
<tr><td>Tendik belum memiliki nilai keterampilan</td><td><?=e($tendikNoSkill)?></td><td>Kolom tes keterampilan/keahlian sesuai jabatan.</td></tr>
<tr><td>Data diterima/SK belum lengkap</td><td><?=e($belumSk)?></td><td>Nomor SK, tanggal SK, penerbit SK otomatis, dan penempatan kerja.</td></tr>
</table></div>
<?php include '_layout_bottom.php'; ?>
