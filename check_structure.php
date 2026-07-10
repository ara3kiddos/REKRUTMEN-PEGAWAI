<?php
require_once 'includes/config.php';

echo "<h2>Struktur Tabel Pelamar</h2>";
$stmt = $pdo->query("DESCRIBE pelamar");
echo "<pre>";
print_r($stmt->fetchAll());
echo "</pre>";

echo "<h2>Struktur Tabel Lamaran</h2>";
$stmt = $pdo->query("DESCRIBE lamaran");
echo "<pre>";
print_r($stmt->fetchAll());
echo "</pre>";

echo "<h2>Struktur Tabel Lowongan</h2>";
$stmt = $pdo->query("DESCRIBE lowongan");
echo "<pre>";
print_r($stmt->fetchAll());
echo "</pre>";

echo "<h2>Data Pelamar dengan Lamaran</h2>";
$pelamarList = getAllPelamarWithDetails($pdo);
echo "<pre>";
print_r($pelamarList);
echo "</pre>";
?>