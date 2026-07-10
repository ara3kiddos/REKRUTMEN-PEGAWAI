<?php
require __DIR__.'/includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CEK LOGIN
if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

$file = $_GET['file'] ?? '';

if (empty($file)) {
    die('File tidak ditemukan');
}

// Hapus leading slash
$file = ltrim($file, '/');

// Pastikan file di dalam folder uploads
if (strpos($file, 'uploads/') !== 0) {
    die('Akses tidak diizinkan');
}

// Path lengkap
$full_path = __DIR__ . '/' . $file;

if (!file_exists($full_path)) {
    die('File tidak ditemukan: ' . $file);
}

// Tampilkan file PDF
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($file) . '"');
header('Content-Length: ' . filesize($full_path));
readfile($full_path);
exit;
?>