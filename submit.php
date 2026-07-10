<?php require __DIR__.'/includes/config.php'; requirePelamar();
try {
    $user = currentPelamarUser($pdo);
    if (!$user) throw new Exception('Sesi pelamar tidak valid. Silakan login ulang.');

    $lowonganId = (int)($_POST['lowongan_id'] ?? 0);
    $stmtLow = $pdo->prepare("SELECT * FROM lowongan WHERE id=? AND status='Aktif' LIMIT 1");
    $stmtLow->execute([$lowonganId]);
    $low = $stmtLow->fetch();
    if (!$low) throw new Exception('Lowongan tidak ditemukan atau tidak aktif.');
    $isDosen = isDosenKategori($low) || stripos((string)($_POST['jabatan_dilamar'] ?? ''), 'dosen') !== false;

    $pendScore = pendidikanScore($_POST['pendidikan_terakhir'] ?? '');
    $age = ageFromBirthdate($_POST['tanggal_lahir'] ?? null);
    if ($isDosen && $pendScore < 90) throw new Exception('Untuk melamar sebagai dosen, pendidikan minimal harus S2.');
    if ($isDosen && $age !== null && $age > 58) throw new Exception('Untuk dosen, usia maksimal 58 tahun sesuai pedoman.');
    if (($_POST['agama'] ?? '') !== 'Islam') throw new Exception('Persyaratan umum pedoman mewajibkan pelamar beragama Islam.');
    if (($_POST['pernyataan_integritas'] ?? '') !== 'Ya') throw new Exception('Pernyataan integritas wajib disetujui.');
    if (($_POST['bebas_narkotika'] ?? '') !== 'Ya') throw new Exception('Pernyataan bebas narkotika wajib disetujui.');

    $folder = 'pelamar_' . $user['id'] . '_' . safeFolderName($user['email']);

    $suratLamaran = uploadFile('file_surat_lamaran', true, $folder);
    $cv = uploadFile('file_cv', true, $folder);
    $ktp = uploadFile('file_ktp', true, $folder);
    $pasfoto = uploadFile('file_pasfoto', true, $folder);
    $kk = uploadFile('file_kk', true, $folder);
    $suratSehat = uploadFile('file_surat_sehat', true, $folder);

    $ijazah = uploadFile('file_ijazah', !$isDosen, $folder);
    $transkrip = uploadFile('file_transkrip', !$isDosen, $folder);
    $ijazahS1 = uploadFile('file_ijazah_s1', $isDosen, $folder);
    $transkripS1 = uploadFile('file_transkrip_s1', $isDosen, $folder);
    $ijazahS2 = uploadFile('file_ijazah_s2', $isDosen, $folder);
    $transkripS2 = uploadFile('file_transkrip_s2', $isDosen, $folder);
    $ijazahS3 = uploadFile('file_ijazah_s3', false, $folder);
    $transkripS3 = uploadFile('file_transkrip_s3', false, $folder);

    $sertifikat = uploadFile('file_sertifikat', false, $folder);
    $skPenyetaraan = uploadFile('file_sk_penyetaraan', false, $folder);
    $pendukung = uploadFile('file_pendukung', false, $folder);
    $tracking = nextTrackingCode($pdo);
    $penerbitSk = $isDosen ? 'BPH' : 'Rektor';

    $stmt = $pdo->prepare("INSERT INTO pelamar (
        user_id,lowongan_id,nama_lengkap,nik,email,no_hp,alamat,agama,pernyataan_integritas,bebas_narkotika,
        jenis_kelamin,tanggal_lahir,pendidikan_terakhir,jurusan,nama_kampus,ipk,pengalaman_tahun,kemampuan_komputer,kemampuan_komunikasi,punya_pimnas,punya_toefl,punya_sertifikat_profesi,
        file_surat_lamaran,file_cv,file_ktp,file_pasfoto,file_kk,file_surat_sehat,file_ijazah,file_transkrip,file_ijazah_s1,file_transkrip_s1,file_ijazah_s2,file_transkrip_s2,file_ijazah_s3,file_transkrip_s3,file_sertifikat,file_sk_penyetaraan,file_pendukung,
        no_ijazah_s1,no_ijazah_s2,no_ijazah_s3,pt_ijazah_s1,pt_ijazah_s2,pt_ijazah_s3,prodi_ijazah_s1,prodi_ijazah_s2,prodi_ijazah_s3,tahun_lulus_s1,tahun_lulus_s2,tahun_lulus_s3,no_ijazah_terakhir,instansi_ijazah_terakhir,jurusan_ijazah_terakhir,tahun_lulus_terakhir,
        jabatan_dilamar,prodi_tujuan,fakultas_tujuan,tracking_code,tahap_lamaran,status_berkas,penerbit_sk,sumber_data,catatan_admin
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $user['id'], $lowonganId, $user['nama_lengkap'], trim($_POST['nik'] ?? $user['nik']), $user['email'], trim($_POST['no_hp'] ?? $user['no_hp']), trim($_POST['alamat'] ?? $user['alamat']), $_POST['agama'], $_POST['pernyataan_integritas'], $_POST['bebas_narkotika'],
        $_POST['jenis_kelamin'], $_POST['tanggal_lahir'], $_POST['pendidikan_terakhir'], $_POST['jurusan'], $_POST['nama_kampus'], $_POST['ipk'], $_POST['pengalaman_tahun'], ($_POST['kemampuan_komputer'] ?? 0) ?: 0, ($_POST['kemampuan_komunikasi'] ?? 0) ?: 0, $_POST['punya_pimnas'] ?? 'Tidak', $_POST['punya_toefl'] ?? 'Tidak', $_POST['punya_sertifikat_profesi'] ?? 'Tidak',
        $suratLamaran, $cv, $ktp, $pasfoto, $kk, $suratSehat, $ijazah, $transkrip, $ijazahS1, $transkripS1, $ijazahS2, $transkripS2, $ijazahS3, $transkripS3, $sertifikat, $skPenyetaraan, $pendukung,
        trim($_POST['no_ijazah_s1'] ?? ''), trim($_POST['no_ijazah_s2'] ?? ''), trim($_POST['no_ijazah_s3'] ?? ''), trim($_POST['pt_ijazah_s1'] ?? ''), trim($_POST['pt_ijazah_s2'] ?? ''), trim($_POST['pt_ijazah_s3'] ?? ''), trim($_POST['prodi_ijazah_s1'] ?? ''), trim($_POST['prodi_ijazah_s2'] ?? ''), trim($_POST['prodi_ijazah_s3'] ?? ''), ($_POST['tahun_lulus_s1'] ?? '') ?: null, ($_POST['tahun_lulus_s2'] ?? '') ?: null, ($_POST['tahun_lulus_s3'] ?? '') ?: null, trim($_POST['no_ijazah_terakhir'] ?? ''), trim($_POST['instansi_ijazah_terakhir'] ?? ''), trim($_POST['jurusan_ijazah_terakhir'] ?? ''), ($_POST['tahun_lulus_terakhir'] ?? '') ?: null,
        $_POST['jabatan_dilamar'], $_POST['prodi_tujuan'], $_POST['fakultas_tujuan'], $tracking, 'Lamaran Dikirim', 'Menunggu', $penerbitSk, 'Form Web', 'Lamaran baru dikirim oleh pelamar melalui akun.'
    ]);
    $newPelamarId = (int)$pdo->lastInsertId();
    foreach (['file_surat_lamaran','file_cv','file_ktp','file_pasfoto','file_kk','file_surat_sehat','file_ijazah','file_transkrip','file_ijazah_s1','file_transkrip_s1','file_ijazah_s2','file_transkrip_s2','file_ijazah_s3','file_transkrip_s3','file_sertifikat','file_sk_penyetaraan','file_pendukung'] as $fieldName) {
        upsertDocumentStatus($pdo, $newPelamarId, $fieldName, 'Belum Dicek', null);
    }
    auditLog($pdo, 'KIRIM_LAMARAN', 'pelamar', $newPelamarId, 'Pelamar mengirim lamaran baru; tracking: '.$tracking);
    header('Location: pelamar_dashboard.php?success=1');
} catch (Exception $e) {
    header('Location: lamaran_baru.php?error=' . urlencode($e->getMessage()));
}
exit;
