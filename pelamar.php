<?php include '_layout_top.php';
$role = currentAdminRole();
$message = '';

function roleCanVerifyDocumentsLocal($role) { return in_array($role, ['admin','sdi'], true); }
function roleCanSeeSection($role, $section) {
    if ($role === 'admin') return true;
    $map = [
        'administrasi' => ['sdi'],
        'tes_prodi' => ['prodi'],
        'tes_psikolog' => ['psikolog'],
        'tes_toefl' => ['toefl'],
        'rekom_dekan' => ['dekan'],
        'final' => ['rektor'],
        'catatan' => ['sdi','prodi','dekan','rektor','psikolog','toefl'],
    ];
    return in_array($role, $map[$section] ?? [], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_workflow';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'verify_files_all') {
        if (!roleCanVerifyDocumentsLocal($role)) {
            $message = '<div class="alert alert-error">Hanya admin/SDI yang boleh memverifikasi kesesuaian dokumen.</div>';
        } else {
            $fieldNames = $_POST['field_name'] ?? [];
            $statuses = $_POST['status_file'] ?? [];
            $notes = $_POST['catatan_file'] ?? [];
            foreach ($fieldNames as $field) {
                $field = (string)$field;
                upsertDocumentStatus($pdo, $id, $field, $statuses[$field] ?? 'Belum Dicek', $notes[$field] ?? null);
            }
            auditLog($pdo, 'VERIFIKASI_DOKUMEN_MASSAL', 'pelamar', $id, 'Validasi semua dokumen disimpan oleh '.$role);
            $message = '<div class="alert alert-success">Validasi dokumen berhasil disimpan.</div>';
        }

    } elseif ($action === 'send_email_notification') {
        if (!in_array($role, ['admin','sdi'], true)) {
            $message = '<div class="alert alert-error">Hanya admin/SDI yang boleh mengirim atau mencatat email panggilan.</div>';
        } else {
            $jenisEmail = $_POST['jenis_email'] ?? 'Panggilan Seleksi';
            [$sent, $info] = sendRecruitmentEmail($pdo, $id, $jenisEmail);
            auditLog($pdo, $sent ? 'KIRIM_EMAIL_PANGGILAN' : 'CATAT_EMAIL_PANGGILAN', 'pelamar', $id, 'Jenis email: '.$jenisEmail);
            $message = '<div class="alert alert-success">'.e($info).'</div>';
        }
    } elseif ($action === 'verify_ijazah') {
        if (!in_array($role, ['admin','sdi'], true)) {
            $message = '<div class="alert alert-error">Hanya admin/SDI yang boleh menyimpan verifikasi ijazah.</div>';
        } else {
            $stmtIj = $pdo->prepare("SELECT * FROM pelamar WHERE id=? LIMIT 1");
            $stmtIj->execute([$id]);
            $ijData = $stmtIj->fetch();
            if (!$ijData) {
                $message = '<div class="alert alert-error">Data pelamar tidak ditemukan.</div>';
            } else {
                $folder = 'pelamar_' . $id . '_' . safeFolderName($ijData['email'] ?? 'verifikasi');
                $bukti = null;

if (
    isset($_FILES['bukti_verifikasi_ijazah']) &&
    $_FILES['bukti_verifikasi_ijazah']['error'] === UPLOAD_ERR_OK &&
    !empty($_FILES['bukti_verifikasi_ijazah']['name'])
) {
    $bukti = uploadFile('bukti_verifikasi_ijazah', false, 'pelamar_'.$id.'_');
}

$finalBukti = $bukti ?: ($ijData['bukti_verifikasi_ijazah'] ?? null);
                $statusIj = $_POST['status_verifikasi_ijazah'] ?? 'Belum Dicek';
                if (!in_array($statusIj, ['Belum Dicek','Valid','Tidak Valid','Perlu Konfirmasi'], true)) $statusIj = 'Belum Dicek';
                $catatanIj = trim($_POST['catatan_verifikasi_ijazah'] ?? '');
                $pdo->prepare("UPDATE pelamar SET status_verifikasi_ijazah=?, catatan_verifikasi_ijazah=?, bukti_verifikasi_ijazah=?, verified_ijazah_by=?, verified_ijazah_at=NOW() WHERE id=?")
                    ->execute([$statusIj, $catatanIj, $finalBukti, $_SESSION['admin_id'] ?? null, $id]);
                auditLog($pdo, 'VERIFIKASI_IJAZAH', 'pelamar', $id, 'Status ijazah: '.$statusIj.'; catatan: '.$catatanIj);
                $message = '<div class="alert alert-success">Status verifikasi ijazah berhasil disimpan.</div>';
            }
        }

    } elseif ($action === 'update_workflow') {
        $stmtOld = $pdo->prepare("SELECT * FROM pelamar WHERE id=? LIMIT 1");
        $stmtOld->execute([$id]);
        $old = $stmtOld->fetch();
        if (!$old) {
            $message = '<div class="alert alert-error">Data lamaran tidak ditemukan.</div>';
        } else {
            $stage = $_POST['tahap_lamaran'] ?? $old['tahap_lamaran'];
            if (!roleCanMoveToStage($role, $stage)) {
                $message = '<div class="alert alert-error">Role '.e($role).' tidak berwenang memindahkan lamaran ke tahap ini.</div>';
            } else {
                $data = $old;

                // SDI/BAUK: administrasi, sortir, pengumuman, pembekalan, rapat, SK, penempatan.
                if (in_array($role, ['admin','sdi'], true)) {
                    foreach (['status_berkas','jabatan_dilamar','prodi_tujuan','fakultas_tujuan','keputusan_rapat_sdi','status_email_panggilan','status_pembekalan','hasil_validasi_pedoman','alasan_keputusan','catatan_admin','nomor_sk','penempatan_unit'] as $f) $data[$f] = $_POST[$f] ?? $data[$f];
                    foreach (['tanggal_pengumuman','tanggal_pembekalan','tanggal_sk'] as $f) $data[$f] = ($_POST[$f] ?? '') !== '' ? $_POST[$f] : null;
                }

                // Prodi/unit: TPA, keterampilan, wawancara, microteaching, dan rekomendasi prodi/unit.
                if (in_array($role, ['admin','prodi'], true)) {
                    foreach (['nilai_tes_akademik','nilai_keterampilan','nilai_tes','nilai_wawancara','nilai_microteaching'] as $f) $data[$f] = ($_POST[$f] ?? '') !== '' ? $_POST[$f] : null;
                    $data['rekomendasi_prodi'] = $_POST['rekomendasi_prodi'] ?? $data['rekomendasi_prodi'];
                    $data['catatan_admin'] = $_POST['catatan_admin'] ?? $data['catatan_admin'];
                }

                // Psikolog: nilai psikotes dan sertifikat/hasil psikotes.
                if (in_array($role, ['admin','psikolog'], true)) {
                    $data['nilai_psikotes'] = ($_POST['nilai_psikotes'] ?? '') !== '' ? $_POST['nilai_psikotes'] : null;
                    $data['nilai_tes_psikologi'] = ($_POST['nilai_tes_psikologi'] ?? '') !== '' ? $_POST['nilai_tes_psikologi'] : null;
                    $data['nilai_tes_kepribadian'] = ($_POST['nilai_tes_kepribadian'] ?? '') !== '' ? $_POST['nilai_tes_kepribadian'] : null;
                    $data['catatan_psikotes'] = $_POST['catatan_psikotes'] ?? $data['catatan_psikotes'];
                    $folder = 'pelamar_' . $id . '_' . safeFolderName($old['email'] ?? 'tes');
                    $cert = uploadFile('file_sertifikat_psikotes', false, $folder);
                    if ($cert) { $data['file_sertifikat_psikotes'] = $cert; $data['psikotes_by'] = $_SESSION['admin_id'] ?? null; $data['psikotes_at'] = date('Y-m-d H:i:s'); }
                }

                // Penilai TOEFL/Bahasa Inggris: nilai dan sertifikat TOEFL.
                if (in_array($role, ['admin','toefl'], true)) {
                    $data['nilai_toefl'] = ($_POST['nilai_toefl'] ?? '') !== '' ? $_POST['nilai_toefl'] : null;
                    $data['catatan_toefl'] = $_POST['catatan_toefl'] ?? $data['catatan_toefl'];
                    $folder = 'pelamar_' . $id . '_' . safeFolderName($old['email'] ?? 'tes');
                    $cert = uploadFile('file_sertifikat_toefl', false, $folder);
                    if ($cert) { $data['file_sertifikat_toefl'] = $cert; $data['toefl_by'] = $_SESSION['admin_id'] ?? null; $data['toefl_at'] = date('Y-m-d H:i:s'); }
                }

                // Dekan: rekomendasi dekan/fakultas.
                if (in_array($role, ['admin','dekan'], true)) {
                    $data['rekomendasi_dekan'] = $_POST['rekomendasi_dekan'] ?? $data['rekomendasi_dekan'];
                    $data['alasan_keputusan'] = $_POST['alasan_keputusan'] ?? $data['alasan_keputusan'];
                    $data['catatan_admin'] = $_POST['catatan_admin'] ?? $data['catatan_admin'];
                }

                // Rektor: pengesahan akhir, SK/penempatan, diterima/ditolak.
                if (in_array($role, ['admin','rektor'], true)) {
                    foreach (['keputusan_rapat_sdi','nomor_sk','alasan_keputusan','catatan_admin'] as $f) $data[$f] = $_POST[$f] ?? $data[$f];
                    foreach (['tanggal_sk'] as $f) $data[$f] = ($_POST[$f] ?? '') !== '' ? $_POST[$f] : null;
                }

                $data['tahap_lamaran'] = $stage;
                $data['penerbit_sk'] = $_POST['penerbit_sk_auto'] ?? $data['penerbit_sk'];

                $stmt = $pdo->prepare("UPDATE pelamar SET
                    tahap_lamaran=?, status_berkas=?, jabatan_dilamar=?, prodi_tujuan=?, fakultas_tujuan=?,
                    nilai_tes_akademik=?, nilai_psikotes=?, catatan_psikotes=?, nilai_tes_psikologi=?, nilai_tes_kepribadian=?, nilai_toefl=?, catatan_toefl=?, nilai_keterampilan=?, nilai_tes=?, nilai_wawancara=?, nilai_microteaching=?,
                    file_sertifikat_psikotes=?, psikotes_by=?, psikotes_at=?, file_sertifikat_toefl=?, toefl_by=?, toefl_at=?,
                    rekomendasi_prodi=?, rekomendasi_dekan=?, keputusan_rapat_sdi=?, nomor_sk=?, tanggal_sk=?, penerbit_sk=?, penempatan_unit=?,
                    status_email_panggilan=?, tanggal_pengumuman=?, status_pembekalan=?, tanggal_pembekalan=?, hasil_validasi_pedoman=?, alasan_keputusan=?, catatan_admin=?
                    WHERE id=?");
                $stmt->execute([
                    $data['tahap_lamaran'], $data['status_berkas'], $data['jabatan_dilamar'], $data['prodi_tujuan'], $data['fakultas_tujuan'],
                    $data['nilai_tes_akademik'], $data['nilai_psikotes'], $data['catatan_psikotes'], $data['nilai_tes_psikologi'], $data['nilai_tes_kepribadian'], $data['nilai_toefl'], $data['catatan_toefl'], $data['nilai_keterampilan'], $data['nilai_tes'], $data['nilai_wawancara'], $data['nilai_microteaching'],
                    $data['file_sertifikat_psikotes'], $data['psikotes_by'], $data['psikotes_at'], $data['file_sertifikat_toefl'], $data['toefl_by'], $data['toefl_at'],
                    $data['rekomendasi_prodi'], $data['rekomendasi_dekan'], $data['keputusan_rapat_sdi'], $data['nomor_sk'], $data['tanggal_sk'], $data['penerbit_sk'], $data['penempatan_unit'],
                    $data['status_email_panggilan'], $data['tanggal_pengumuman'], $data['status_pembekalan'], $data['tanggal_pembekalan'], $data['hasil_validasi_pedoman'], $data['alasan_keputusan'], $data['catatan_admin'],
                    $id
                ]);
                auditLog($pdo, 'UPDATE_TAHAP_LAMARAN', 'pelamar', $id, 'Role '.$role.' memperbarui lamaran; tahap: '.$stage);
                $message = '<div class="alert alert-success">Lamaran berhasil diperbarui sesuai hak akses role.</div>';
            }
        }
    }
}

$detailId = (int)($_GET['id'] ?? 0);
$tahapList = tahapOptions();
if ($detailId > 0) {
    $stmt = $pdo->prepare("SELECT p.*, l.nama_posisi, l.kategori, l.unit_kerja FROM pelamar p JOIN lowongan l ON p.lowongan_id=l.id WHERE p.id=? LIMIT 1");
    $stmt->execute([$detailId]);
    $p = $stmt->fetch();
    if (!$p) { echo '<div class="alert alert-error">Data pelamar tidak ditemukan.</div>'; include '_layout_bottom.php'; exit; }
    if (!in_array($role, ['admin','sdi'], true) && !in_array($p['tahap_lamaran'], roleVisibleStages($role), true)) {
        echo '<div class="alert alert-error"><b>Akses detail dibatasi.</b><br>Lamaran ini belum berada pada tahap kewenangan role '.e(adminRoleLabel($role)).'.</div>';
        echo '<a class="btn btn-light" href="pelamar.php">Kembali</a>';
        include '_layout_bottom.php'; exit;
    }
    $isDosen = isDosenKategori($p);
    $autoSk = $isDosen ? 'BPH' : 'Rektor';
    $docFields = documentFieldsForPelamar($p);
    $v = kmeansVectorFromPelamar($p);
    ?>
    <h1>Detail Lamaran</h1>
    <?= $message ?>
    <a class="btn btn-light" href="pelamar.php">← Kembali ke daftar</a><br><br>
    <div class="grid-2">
      <div class="card">
        <h3><?=e($p['nama_lengkap'])?></h3>
        <p><?=e($p['email'])?> · <?=e($p['no_hp'])?><br><b>Tracking:</b> <?=e($p['tracking_code'] ?: '-')?><br><b>Jenis:</b> <?=e($isDosen ? 'Dosen' : 'Tenaga Kependidikan')?><br><b>Status:</b> <span class="pill <?=e(tahapBadgeClass($p['tahap_lamaran']))?>"><?=e($p['tahap_lamaran'])?></span></p>
      </div>
      <div class="card">
        <h3>Ringkasan Internal</h3>
        <p>Usia <?=$v[0]?> · Pendidikan <?=$v[1]?> · IPK <?=$v[2]?> · Dokumen <?=$v[3]?> · Pengalaman <?=$v[4]?> · Sertifikat <?=$v[5]?><br>Kategori rekomendasi internal: <b><?=e($p['cluster_label'] ?: '-')?></b></p>
      </div>
    </div><br>

    <div class="card"><h3>Verifikasi Dokumen</h3><p class="hint">File disimpan per folder pelamar di <code>uploads/pelamar_ID_email/</code>. Hanya PDF, JPG, JPEG, dan PNG yang diterima. Validasi dokumen disimpan sekaligus dengan satu tombol.</p>
    <?php if(roleCanVerifyDocumentsLocal($role)): ?><form method="POST"><?php endif; ?>
    <?php if(roleCanVerifyDocumentsLocal($role)): ?><input type="hidden" name="action" value="verify_files_all"><input type="hidden" name="id" value="<?=e($p['id'])?>"><?php endif; ?>
    <div class="document-grid">
    <?php foreach($docFields as $field=>$label): $path=$p[$field] ?? ''; $st=documentStatus($pdo,$p['id'],$field); ?>
      <div class="doc-card">
        <b><?=e($label)?></b><br>
        <?php if($path): ?>
          <?php if(isImageUpload($path)): ?><a href="<?=e(fileUrl($path))?>" target="_blank"><img class="doc-thumb" src="<?=e(fileUrl($path))?>" alt="<?=e($label)?>"></a><?php else: ?><a class="btn btn-light" target="_blank" href="<?=e(fileUrl($path))?>">Lihat PDF</a><?php endif; ?>
        <?php else: ?><span class="pill pill-no">Belum Upload</span><?php endif; ?>
        <?php if(roleCanVerifyDocumentsLocal($role)): ?>
          <input type="hidden" name="field_name[]" value="<?=e($field)?>">
          <select name="status_file[<?=e($field)?>]"><option <?= $st['status']==='Belum Dicek'?'selected':'' ?>>Belum Dicek</option><option <?= $st['status']==='Sesuai'?'selected':'' ?>>Sesuai</option><option <?= $st['status']==='Tidak Sesuai'?'selected':'' ?>>Tidak Sesuai</option></select>
          <input name="catatan_file[<?=e($field)?>]" value="<?=e($st['catatan'] ?? '')?>" placeholder="Catatan singkat">
        <?php else: ?>
          <p><span class="pill <?= $st['status']==='Sesuai'?'pill-ok':($st['status']==='Tidak Sesuai'?'pill-no':'pill-neutral') ?>"><?=e($st['status'])?></span><br><span class="hint"><?=e($st['catatan'] ?? '')?></span></p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    </div>
    <?php if(roleCanVerifyDocumentsLocal($role)): ?><br><button class="btn btn-primary" type="submit">Simpan Validasi Dokumen</button></form><?php else: ?><p class="hint">Role <?=e(strtoupper($role))?> hanya dapat melihat dokumen, tidak dapat memverifikasi kesesuaian berkas.</p><?php endif; ?>
    </div><br>

    <div class="card"><h3>Verifikasi Ijazah via Portal Resmi</h3><p class="hint">Sistem tidak melakukan bypass captcha. SDI membuka portal resmi PISN/SIVIL, melakukan pengecekan manual, lalu menyimpan status dan bukti verifikasi di sini.</p>
      <div class="grid-2"><div><b>Data ijazah yang dicatat</b><p><?php if($isDosen): ?>
        <b>S1:</b> <?=e($p['no_ijazah_s1'] ?: '-')?> · <?=e($p['pt_ijazah_s1'] ?: '-')?> · <?=e($p['prodi_ijazah_s1'] ?: '-')?> · <?=e($p['tahun_lulus_s1'] ?: '-')?><br>
        <b>S2:</b> <?=e($p['no_ijazah_s2'] ?: '-')?> · <?=e($p['pt_ijazah_s2'] ?: '-')?> · <?=e($p['prodi_ijazah_s2'] ?: '-')?> · <?=e($p['tahun_lulus_s2'] ?: '-')?><br>
        <b>S3:</b> <?=e($p['no_ijazah_s3'] ?: '-')?> · <?=e($p['pt_ijazah_s3'] ?: '-')?> · <?=e($p['prodi_ijazah_s3'] ?: '-')?> · <?=e($p['tahun_lulus_s3'] ?: '-')?>
      <?php else: ?>
        <b>Terakhir:</b> <?=e($p['no_ijazah_terakhir'] ?: '-')?> · <?=e($p['instansi_ijazah_terakhir'] ?: '-')?> · <?=e($p['jurusan_ijazah_terakhir'] ?: '-')?> · <?=e($p['tahun_lulus_terakhir'] ?: '-')?>
      <?php endif; ?></p></div><div><b>Status saat ini</b><p><span class="pill <?= $p['status_verifikasi_ijazah']==='Valid'?'pill-ok':($p['status_verifikasi_ijazah']==='Tidak Valid'?'pill-no':'pill-wait') ?>"><?=e($p['status_verifikasi_ijazah'] ?? 'Belum Dicek')?></span><br><?=nl2br(e($p['catatan_verifikasi_ijazah'] ?? ''))?></p><?php if(!empty($p['bukti_verifikasi_ijazah'])): ?><a class="btn btn-light" target="_blank" href="<?=e(fileUrl($p['bukti_verifikasi_ijazah']))?>">Lihat Bukti Verifikasi</a><?php endif; ?></div></div>
      <?php if(in_array($role, ['admin','sdi'], true)): ?><form method="POST" enctype="multipart/form-data" class="form"><input type="hidden" name="action" value="verify_ijazah"><input type="hidden" name="id" value="<?=e($p['id'])?>"><div class="form-grid"><div class="field"><label>Status Verifikasi Ijazah</label><select name="status_verifikasi_ijazah"><option <?= ($p['status_verifikasi_ijazah']??'')==='Belum Dicek'?'selected':'' ?>>Belum Dicek</option><option <?= ($p['status_verifikasi_ijazah']??'')==='Valid'?'selected':'' ?>>Valid</option><option <?= ($p['status_verifikasi_ijazah']??'')==='Tidak Valid'?'selected':'' ?>>Tidak Valid</option><option <?= ($p['status_verifikasi_ijazah']??'')==='Perlu Konfirmasi'?'selected':'' ?>>Perlu Konfirmasi</option></select></div><div class="field"><label>Upload Bukti Verifikasi (PDF/JPG/PNG)</label><input type="file" name="bukti_verifikasi_ijazah" accept="application/pdf,.pdf"></div><div class="field full"><label>Catatan Verifikasi</label><textarea name="catatan_verifikasi_ijazah" placeholder="Contoh: Nomor ijazah sesuai data PISN/SIVIL."><?=e($p['catatan_verifikasi_ijazah'] ?? '')?></textarea></div></div><br><a class="btn btn-light" href="https://pisn.kemdiktisaintek.go.id/" target="_blank">Buka Portal PISN/SIVIL</a> <button class="btn btn-primary" type="submit">Simpan Verifikasi Ijazah</button></form><?php else: ?><p class="hint">Role ini hanya dapat melihat status verifikasi ijazah.</p><?php endif; ?>
    </div><br>

    <?php if(in_array($role, ['admin','sdi'], true)): ?>
    <div class="card"><h3>Email Pemanggilan / Pengumuman</h3><p class="hint">Gunakan bagian ini untuk mencatat dan mengirim email panggilan sesuai alur: panggilan TPA, psikotes, TOEFL/keterampilan, wawancara, pengumuman kelulusan, dan panggilan kerja. Jika mail() hosting belum aktif, isi email tetap tersimpan di log sebagai template.</p>
      <form method="POST" class="form"><input type="hidden" name="action" value="send_email_notification"><input type="hidden" name="id" value="<?=e($p['id'])?>">
        <div class="form-grid"><div class="field"><label>Jenis Email</label><select name="jenis_email"><option>Panggilan Tes Potensi Akademik</option><option>Panggilan Tes Psikotes</option><option>Panggilan Tes Bahasa Inggris/TOEFL</option><option>Panggilan Tes Keterampilan/Keahlian</option><option>Panggilan Wawancara</option><option>Pengumuman Kelulusan</option><option>Panggilan Kerja/Penerimaan</option></select></div><div class="field"><label>Email Tujuan</label><input value="<?=e($p['email'])?>" readonly></div></div><br><button class="btn btn-primary" type="submit">Kirim / Catat Email Panggilan</button>
      </form>
    </div><br>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="form"><input type="hidden" name="action" value="update_workflow"><input type="hidden" name="id" value="<?=e($p['id'])?>">
    <div class="card"><h3>Edit Sesuai Hak Akses: <?=e(strtoupper($role))?></h3><div class="form-grid">
      <div class="field"><label>Tahap</label><select name="tahap_lamaran"><?php foreach($tahapList as $t): if(roleCanMoveToStage($role,$t) || $p['tahap_lamaran']===$t): ?><option value="<?=e($t)?>" <?= $p['tahap_lamaran']===$t?'selected':'' ?>><?=e($t)?></option><?php endif; endforeach; ?></select></div>
      <div class="field"><label>Penerbit SK Otomatis</label><input value="<?=e($autoSk)?>" readonly><input type="hidden" name="penerbit_sk_auto" value="<?=e($autoSk)?>"></div>
    </div></div><br>

    <?php if(roleCanSeeSection($role,'administrasi')): ?>
    <div class="card"><h3>Bagian SDI/BAUK</h3><div class="form-grid">
      <div class="field"><label>Status Berkas</label><select name="status_berkas"><option <?= $p['status_berkas']=='Menunggu'?'selected':'' ?>>Menunggu</option><option <?= $p['status_berkas']=='Memenuhi'?'selected':'' ?>>Memenuhi</option><option <?= $p['status_berkas']=='Tidak Memenuhi'?'selected':'' ?>>Tidak Memenuhi</option></select></div>
      <div class="field"><label>Jabatan</label><input name="jabatan_dilamar" value="<?=e($p['jabatan_dilamar'])?>"></div>
      <div class="field"><label>Prodi/Unit</label><input name="prodi_tujuan" value="<?=e($p['prodi_tujuan'])?>"></div>
      <div class="field"><label>Fakultas/Biro</label><input name="fakultas_tujuan" value="<?=e($p['fakultas_tujuan'])?>"></div>
      <div class="field"><label>Rapat SDI</label><select name="keputusan_rapat_sdi"><option>Belum</option><option <?= $p['keputusan_rapat_sdi']=='Diterima'?'selected':'' ?>>Diterima</option><option <?= $p['keputusan_rapat_sdi']=='Ditolak'?'selected':'' ?>>Ditolak</option><option <?= $p['keputusan_rapat_sdi']=='Dipertimbangkan'?'selected':'' ?>>Dipertimbangkan</option></select></div>
      <div class="field"><label>Status Email Panggilan</label><select name="status_email_panggilan"><option>Belum Dikirim</option><option <?= $p['status_email_panggilan']=='Sudah Dikirim'?'selected':'' ?>>Sudah Dikirim</option></select><span class="hint">Panggilan tes, wawancara, dan penerimaan dikirim melalui email pelamar.</span></div>
      <div class="field"><label>Tanggal Pengumuman</label><input type="date" name="tanggal_pengumuman" value="<?=e($p['tanggal_pengumuman'])?>"></div>
      <div class="field"><label>Status Pembekalan</label><select name="status_pembekalan"><option>Belum</option><option <?= $p['status_pembekalan']=='Sudah'?'selected':'' ?>>Sudah</option><option <?= $p['status_pembekalan']=='Tidak Hadir'?'selected':'' ?>>Tidak Hadir</option></select></div>
      <div class="field"><label>Tanggal Pembekalan</label><input type="date" name="tanggal_pembekalan" value="<?=e($p['tanggal_pembekalan'])?>"></div>
      <div class="field"><label>Nomor SK</label><input name="nomor_sk" value="<?=e($p['nomor_sk'])?>"></div>
      <div class="field"><label>Tanggal SK</label><input type="date" name="tanggal_sk" value="<?=e($p['tanggal_sk'])?>"></div>
      <div class="field"><label>Penempatan Kerja</label><input name="penempatan_unit" value="<?=e($p['penempatan_unit'])?>"></div>
      <div class="field full"><label>Validasi Pedoman</label><textarea name="hasil_validasi_pedoman"><?=e($p['hasil_validasi_pedoman'])?></textarea></div>
      <div class="field full"><label>Alasan/Keputusan</label><textarea name="alasan_keputusan"><?=e($p['alasan_keputusan'])?></textarea></div>
      <div class="field full"><label>Catatan SDI</label><textarea name="catatan_admin"><?=e($p['catatan_admin'])?></textarea></div>
    </div></div><br>
    <?php endif; ?>

    <?php if(roleCanSeeSection($role,'tes_prodi')): ?>
    <div class="card"><h3>Bagian Prodi/Unit: Tes dan Wawancara</h3><div class="form-grid">
      <div class="field"><label>TPA/Akademik</label><input type="number" min="0" max="100" name="nilai_tes_akademik" value="<?=e($p['nilai_tes_akademik'])?>"></div>
      <div class="field"><label>Keterampilan Tendik</label><input type="number" min="0" max="100" name="nilai_keterampilan" value="<?=e($p['nilai_keterampilan'])?>"></div>
      <div class="field"><label>Tes Umum</label><input type="number" min="0" max="100" name="nilai_tes" value="<?=e($p['nilai_tes'])?>"></div>
      <div class="field"><label>Wawancara</label><input type="number" min="0" max="100" name="nilai_wawancara" value="<?=e($p['nilai_wawancara'])?>"></div>
      <div class="field"><label>Microteaching</label><input type="number" min="0" max="100" name="nilai_microteaching" value="<?=e($p['nilai_microteaching'])?>"></div>
      <div class="field"><label>Rekom Prodi/Unit</label><select name="rekomendasi_prodi"><option>Belum</option><option <?= $p['rekomendasi_prodi']=='Direkomendasikan'?'selected':'' ?>>Direkomendasikan</option><option <?= $p['rekomendasi_prodi']=='Tidak Direkomendasikan'?'selected':'' ?>>Tidak Direkomendasikan</option></select></div>
      <div class="field full"><label>Catatan Prodi/Unit</label><textarea name="catatan_admin"><?=e($p['catatan_admin'])?></textarea></div>
    </div></div><br>
    <?php endif; ?>

    <?php if(roleCanSeeSection($role,'tes_psikolog')): ?>
    <div class="card"><h3>Bagian Psikolog: Tes Psikotes</h3><p class="hint">Role psikolog hanya mengisi penilaian psikotes dan mengupload file sertifikat/hasil psikotes. File yang diterima: PDF/JPG/PNG.</p><div class="form-grid">
      <div class="field"><label>Nilai Psikotes</label><input type="number" min="0" max="100" name="nilai_psikotes" value="<?=e($p['nilai_psikotes'])?>"></div>
      <div class="field"><label>Nilai Psikologi</label><input type="number" min="0" max="100" name="nilai_tes_psikologi" value="<?=e($p['nilai_tes_psikologi'])?>"></div>
      <div class="field"><label>Nilai Kepribadian</label><input type="number" min="0" max="100" name="nilai_tes_kepribadian" value="<?=e($p['nilai_tes_kepribadian'])?>"></div>
      <div class="field"><label>Sertifikat/Hasil Psikotes</label><input type="file" name="file_sertifikat_psikotes" accept=".pdf,.jpg,.jpeg,.png"><?php if(!empty($p['file_sertifikat_psikotes'])): ?><br><a class="btn btn-light" target="_blank" href="<?=e(fileUrl($p['file_sertifikat_psikotes']))?>">Lihat File Psikotes</a><?php endif; ?></div>
      <div class="field full"><label>Catatan Psikotes</label><textarea name="catatan_psikotes"><?=e($p['catatan_psikotes'])?></textarea></div>
    </div></div><br>
    <?php endif; ?>

    <?php if(roleCanSeeSection($role,'tes_toefl')): ?>
    <div class="card"><h3>Bagian Penilai TOEFL/Bahasa Inggris</h3><p class="hint">Role TOEFL hanya mengisi nilai TOEFL/Bahasa Inggris dan mengupload file sertifikat/hasil tes. Untuk sementara pengecekan ijazah hanya berupa tautan ke portal resmi.</p><div class="form-grid">
      <div class="field"><label>Nilai TOEFL/Bahasa Inggris</label><input type="number" min="0" max="100" name="nilai_toefl" value="<?=e($p['nilai_toefl'])?>"></div>
      <div class="field"><label>Sertifikat/Hasil TOEFL</label><input type="file" name="file_sertifikat_toefl" accept=".pdf,.jpg,.jpeg,.png"><?php if(!empty($p['file_sertifikat_toefl'])): ?><br><a class="btn btn-light" target="_blank" href="<?=e(fileUrl($p['file_sertifikat_toefl']))?>">Lihat File TOEFL</a><?php endif; ?></div>
      <div class="field full"><label>Catatan TOEFL</label><textarea name="catatan_toefl"><?=e($p['catatan_toefl'] ?? '')?></textarea></div>
    </div></div><br>
    <?php endif; ?>

    <?php if(roleCanSeeSection($role,'rekom_dekan')): ?>
    <div class="card"><h3>Bagian Dekan/Fakultas</h3><div class="form-grid">
      <div class="field"><label>Rekom Dekan</label><select name="rekomendasi_dekan"><option>Belum</option><option <?= $p['rekomendasi_dekan']=='Direkomendasikan'?'selected':'' ?>>Direkomendasikan</option><option <?= $p['rekomendasi_dekan']=='Tidak Direkomendasikan'?'selected':'' ?>>Tidak Direkomendasikan</option></select></div>
      <div class="field full"><label>Alasan/Rekomendasi Dekan</label><textarea name="alasan_keputusan"><?=e($p['alasan_keputusan'])?></textarea></div>
      <div class="field full"><label>Catatan Dekan</label><textarea name="catatan_admin"><?=e($p['catatan_admin'])?></textarea></div>
    </div></div><br>
    <?php endif; ?>

    <?php if(roleCanSeeSection($role,'final')): ?>
    <div class="card"><h3>Keputusan Akhir / Rektor</h3><div class="form-grid">
      <div class="field"><label>Keputusan Rapat</label><select name="keputusan_rapat_sdi"><option>Belum</option><option <?= $p['keputusan_rapat_sdi']=='Diterima'?'selected':'' ?>>Diterima</option><option <?= $p['keputusan_rapat_sdi']=='Ditolak'?'selected':'' ?>>Ditolak</option><option <?= $p['keputusan_rapat_sdi']=='Dipertimbangkan'?'selected':'' ?>>Dipertimbangkan</option></select></div>
      <div class="field"><label>Nomor SK</label><input name="nomor_sk" value="<?=e($p['nomor_sk'])?>"></div>
      <div class="field"><label>Tanggal SK</label><input type="date" name="tanggal_sk" value="<?=e($p['tanggal_sk'])?>"></div>
      <div class="field full"><label>Alasan/Keputusan Akhir</label><textarea name="alasan_keputusan"><?=e($p['alasan_keputusan'])?></textarea></div>
      <div class="field full"><label>Catatan Akhir</label><textarea name="catatan_admin"><?=e($p['catatan_admin'])?></textarea></div>
    </div></div><br>
    <?php endif; ?>

    <button class="btn btn-primary" type="submit">Simpan Perubahan Lamaran</button>
    </form>
    <?php include '_layout_bottom.php'; exit;
}

$q = trim($_GET['q'] ?? '');
$tahap = trim($_GET['tahap'] ?? '');
$where = [];$params=[];
list($roleStageWhere, $roleStageParams) = roleStageWhereClause($role, 'p');
if ($roleStageWhere !== '') { $where[] = $roleStageWhere; $params = array_merge($params, $roleStageParams); }
if ($q !== '') { $where[]="(p.nama_lengkap LIKE ? OR p.email LIKE ? OR p.prodi_tujuan LIKE ? OR p.jabatan_dilamar LIKE ?)"; $like="%$q%"; $params=array_merge($params,[$like,$like,$like,$like]); }
if ($tahap !== '') { $where[]="p.tahap_lamaran=?"; $params[]=$tahap; }
$sqlWhere = $where ? 'WHERE '.implode(' AND ',$where) : '';
$countStmt=$pdo->prepare("SELECT COUNT(*) FROM pelamar p $sqlWhere");$countStmt->execute($params);$totalFiltered=(int)$countStmt->fetchColumn();
$stmt=$pdo->prepare("SELECT p.*, l.kategori, l.unit_kerja FROM pelamar p JOIN lowongan l ON p.lowongan_id=l.id $sqlWhere ORDER BY p.id DESC LIMIT 300");$stmt->execute($params);$data=$stmt->fetchAll();
$totalPelamar=$pdo->query("SELECT COUNT(*) FROM pelamar")->fetchColumn();
?>
<h1>Workflow Lamaran</h1>
<?= $message ?>
<div class="metric-grid"><div class="metric">Total Pelamar<b><?=e($totalPelamar)?></b></div><div class="metric">Hasil Filter<b><?=e($totalFiltered)?></b></div><div class="metric">Role Aktif<b style="font-size:18px"><?=e(strtoupper($role))?></b></div></div>
<div class="card"><h3>Daftar Lamaran Ringkas</h3><p class="hint">Daftar dibuat ringkas: nama dan status. Klik detail untuk melihat data, dokumen, dan fitur sesuai hak akses role.</p></div><br>
<form class="form" method="GET"><div class="form-grid"><div class="field"><label>Cari nama/email/prodi/jabatan</label><input name="q" value="<?=e($q)?>"></div><div class="field"><label>Filter Tahap</label><select name="tahap"><option value="">Semua Tahap</option><?php foreach(roleVisibleStages($role) as $t): ?><option value="<?=e($t)?>" <?= $tahap===$t?'selected':'' ?>><?=e($t)?></option><?php endforeach; ?></select></div></div><br><button class="btn btn-primary" type="submit">Filter</button><a class="btn btn-light" href="pelamar.php">Reset</a></form><br>
<div class="table-wrap"><table><tr><th>Nama Pelamar</th><th>Jenis/Unit</th><th>Status Lamaran</th><th>Dokumen</th><th>Aksi</th></tr>
<?php foreach($data as $p): $issues=pedomanIssues($p); $docFields=documentFieldsForPelamar($p); $totalDocs=count($docFields); $ok=0; foreach($docFields as $field=>$label){ $st=documentStatus($pdo,$p['id'],$field); if($st['status']==='Sesuai') $ok++; } ?>
<tr>
<td><b><?=e($p['nama_lengkap'])?></b><br><span class="hint"><?=e($p['email'])?> · <?=e($p['tracking_code'] ?: '-')?></span></td>
<td><?=e(isDosenKategori($p)?'Dosen':'Tenaga Kependidikan')?><br><span class="hint"><?=e($p['prodi_tujuan'] ?: $p['unit_kerja'] ?: '-')?></span></td>
<td><span class="pill <?=e(tahapBadgeClass($p['tahap_lamaran']))?>"><?=e($p['tahap_lamaran'])?></span><br><span class="hint">Berkas: <?=e($p['status_berkas'])?></span><?php if($issues): ?><br><span class="pill pill-no"><?=count($issues)?> catatan</span><?php endif; ?></td>
<td><?=e($ok)?> / <?=e($totalDocs)?> sesuai</td>
<td><a class="btn btn-primary" href="pelamar.php?id=<?=e($p['id'])?>">Detail/Edit</a></td>
</tr>
<?php endforeach; ?>
</table></div>
<?php include '_layout_bottom.php'; ?>
