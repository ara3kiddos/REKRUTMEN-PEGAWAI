<?php include '_layout_top.php';
$catalog = adminPageCatalog();
$roles = ['admin','sdi','prodi','psikolog','toefl','dekan','rektor'];
?>
<h1>Hak Akses Pengguna Internal</h1>
<div class="card">
  <p>Halaman ini menjadi acuan hak akses. Sidebar sekarang mengikuti role login, dan akses langsung ke URL yang tidak sesuai role akan ditolak.</p>
</div><br>

<div class="card">
  <h3>Role Aktif</h3>
  <p><b><?=e(adminRoleLabel(currentAdminRole()))?></b></p>
  <ul>
    <?php foreach(adminRoleCapabilities(currentAdminRole()) as $cap): ?>
      <li><?=e($cap)?></li>
    <?php endforeach; ?>
  </ul>
</div><br>

<div class="table-wrap"><table>
<tr><th>Role</th><th>Kewenangan Utama</th><th>Menu yang Tampil</th></tr>
<?php foreach($roles as $r): $items = adminMenuItems($r); ?>
<tr>
  <td><b><?=e(adminRoleLabel($r))?></b><br><span class="hint">username: <?=e($r)?></span></td>
  <td><ul><?php foreach(adminRoleCapabilities($r) as $cap): ?><li><?=e($cap)?></li><?php endforeach; ?></ul></td>
  <td><?php foreach($items as $file=>$meta): ?><span class="pill pill-blue"><?=e($meta[0])?></span> <?php endforeach; ?></td>
</tr>
<?php endforeach; ?>
</table></div><br>

<div class="card">
  <h3>Catatan Pembatasan</h3>
  <ul>
    <li><b>Prodi/Unit</b> fokus TPA/akademik, keterampilan tendik, wawancara, microteaching, dan rekomendasi prodi/unit.</li>
    <li><b>Psikolog</b> hanya fokus input nilai psikotes, catatan, dan upload sertifikat/hasil psikotes.</li>
    <li><b>Penilai TOEFL</b> hanya fokus input nilai TOEFL/Bahasa Inggris, catatan, dan upload sertifikat/hasil TOEFL.</li>
    <li><b>Dekan</b> hanya fokus rekomendasi fakultas/dekan.</li>
    <li><b>Rektor</b> fokus pengesahan akhir/SK, bukan verifikasi dokumen atau input nilai teknis.</li>
    <li><b>SDI/BAUK</b> fokus administrasi, dokumen, perencanaan, pengumuman, pembekalan, rapat, dan penempatan.</li>
  </ul>
</div>
<?php include '_layout_bottom.php'; ?>
