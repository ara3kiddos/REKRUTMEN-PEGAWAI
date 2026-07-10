<?php
require __DIR__.'/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Ambil data dari form dan bersihkan spasi cadangan
    $nama_lengkap     = trim($_POST['nama_lengkap']);
    $email            = trim($_POST['email']);
    $password         = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $nik              = trim($_POST['nik']) ?: null; 
    $no_hp            = trim($_POST['no_hp']) ?: null;
    $alamat           = trim($_POST['alamat']) ?: null;

    // 2. Validasi: Pastikan Password dan Konfirmasi Password cocok
    if ($password !== $password_confirm) {
        header("Location: register_pelamar.php?error=" . urlencode("Konfirmasi password tidak cocok!"));
        exit;
    }

    try {
        // 3. Validasi: Cek apakah email sudah terdaftar sebelumnya
        $stmt_cek = $pdo->prepare("SELECT id_user FROM users WHERE email = ? LIMIT 1");
        $stmt_cek->execute([$email]);
        if ($stmt_cek->fetch()) {
            header("Location: register_pelamar.php?error=" . urlencode("Email sudah terdaftar, silakan gunakan email lain atau langsung login."));
            exit;
        }

        // 4. Hash password demi keamanan
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);

        // 5. Set default untuk Pelamar
        $id_role = 5;       // Otomatis 5 (Pelamar)
        $status_aktif = 1;  // Langsung aktif agar bisa login

        // 6. Jalankan query insert ke tabel users
        $sql = "INSERT INTO users (nama_lengkap, email, password, id_role, status_aktif, nik, no_hp, alamat) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nama_lengkap,
            $email,
            $password_hashed,
            $id_role,
            $status_aktif,
            $nik,
            $no_hp,
            $alamat
        ]);

        // 7. Jika berhasil, lempar kembali ke halaman login.php dengan pesan sukses
        header("Location: login.php?registered=1");
        exit;

    } catch (PDOException $e) {
        header("Location: register_pelamar.php?error=" . urlencode("Gagal mendaftar: " . $e->getMessage()));
        exit;
    }
} else {
    header("Location: register_pelamar.php");
    exit;
}