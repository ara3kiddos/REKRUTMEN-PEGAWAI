<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/includes/config.php';
echo '<h2>Runtime OK</h2>';
echo '<p>Koneksi database dan include berhasil.</p>';
foreach (['admins','lowongan','pelamar','pelamar_users','file_verifikasi','perencanaan_kebutuhan','email_logs'] as $t) {
    try { $n = $pdo->query('SELECT COUNT(*) FROM `'.$t.'`')->fetchColumn(); echo htmlspecialchars($t).': '.htmlspecialchars((string)$n).'<br>'; }
    catch (Throwable $e) { echo '<b>'.htmlspecialchars($t).'</b>: ERROR '.htmlspecialchars($e->getMessage()).'<br>'; }
}
