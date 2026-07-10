<?php
require __DIR__.'/includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// FUNGSI REDIRECT BERDASARKAN ROLE
// ============================================================
function redirectByRole($role_id) {
    switch ($role_id) {
        case 1: // Superuser
            header("Location: admin/dashboard.php");
            break;
        case 2: // SDI
            header("Location: admin/dashboard.php");
            break;
        case 3: // Rektor
            header("Location: admin/rektor_dashboard.php");
            break;
        case 4: // Penilai
            header("Location: admin/penilai_dashboard.php");
            break;
        case 5: // Pelamar
            header("Location: dashboard.php");
            break;
        default:
            header("Location: index.php");
            break;
    }
    exit;
}

// ============================================================
// CEK SESSION - Jika sudah login, redirect berdasarkan role
// ============================================================
if (isset($_SESSION['id_user']) && isset($_SESSION['id_role'])) {
    redirectByRole($_SESSION['id_role']);
}

$error = '';
$success = '';

// ============================================================
// PROSES LOGIN
// ============================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email   = trim($_POST['email']);
    $password = $_POST['password'];
    $selected_role = isset($_POST['role']) ? (int)$_POST['role'] : 0;

    // Validasi role harus dipilih
    if ($selected_role == 0) {
        $error = "Silakan pilih role terlebih dahulu!";
    } else {
        // Cek user berdasarkan email dan role
        $stmt = $pdo->prepare("
            SELECT u.*, r.nama_role 
            FROM users u 
            JOIN roles r ON u.id_role = r.id_role 
            WHERE u.email = ? AND u.id_role = ?
        ");
        $stmt->execute([$email, $selected_role]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($user['status_aktif'] != 1) {
                $error = "Akun Anda tidak aktif. Silakan hubungi administrator.";
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['id_user']      = $user['id_user'];
                $_SESSION['id_role']      = $user['id_role'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['role_name']    = $user['nama_role'];

                // Redirect berdasarkan role
                redirectByRole($user['id_role']);
            } else {
                $error = "Password salah!";
            }
        } else {
            $error = "Email atau Role tidak ditemukan!";
        }
    }
}

if (isset($_GET['registered'])) {
    $success = "Akun berhasil dibuat. Silakan login.";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SDI System</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* ============================================================
           ANIMASI TEKS SAJA - TIDAK MEMPENGARUHI SIDEBAR
           ============================================================ */
        
        /* Animasi fade-in untuk teks di visual side */
        .auth-visual .auth-copy {
            animation: fadeInUp 0.8s ease-out both;
        }
        
        .auth-visual .eyebrow {
            animation: fadeInUp 0.6s ease-out 0.2s both;
        }
        
        .auth-visual h1 {
            animation: fadeInUp 0.8s ease-out 0.3s both;
        }
        
        .auth-visual h1 span {
            display: inline-block;
            animation: shimmerText 3s ease-in-out infinite;
            background: linear-gradient(135deg, #d4af37, #f7edcf, #d4af37);
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .auth-visual p {
            animation: fadeInUp 0.8s ease-out 0.5s both;
        }
        
        /* Animasi untuk elemen form di panel kanan */
        .auth-panel .brand {
            animation: fadeInUp 0.6s ease-out both;
        }
        
        .auth-panel h1 {
            animation: fadeInUp 0.6s ease-out 0.1s both;
        }
        
        .auth-panel .hint {
            animation: fadeInUp 0.6s ease-out 0.2s both;
        }
        
        .auth-panel .alert {
            animation: fadeInUp 0.5s ease-out 0.3s both;
        }
        
        .auth-panel form {
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }
        
        .auth-panel .register-link {
            animation: fadeInUp 0.6s ease-out 0.6s both;
        }
        
        .auth-panel .demo-info {
            animation: fadeInUp 0.6s ease-out 0.7s both;
        }
        
        /* Animasi untuk field input satu per satu */
        .auth-panel .field {
            opacity: 0;
            animation: fadeInUp 0.5s ease-out forwards;
        }
        
        .auth-panel .field:nth-child(1) { animation-delay: 0.5s; }
        .auth-panel .field:nth-child(2) { animation-delay: 0.6s; }
        .auth-panel .field:nth-child(3) { animation-delay: 0.7s; }
        
        /* Animasi tombol */
        .auth-panel .btn-group {
            opacity: 0;
            animation: fadeInUp 0.6s ease-out 0.8s forwards;
        }
        
        /* Keyframes */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes shimmerText {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }
        
        /* Efek hover pada tombol - hanya tombol yang bergerak */
        .auth-panel .btn-primary {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .auth-panel .btn-primary:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.35);
        }
        
        .auth-panel .btn-light {
            transition: transform 0.3s ease, background 0.3s ease;
        }
        
        .auth-panel .btn-light:hover {
            transform: translateY(-2px);
        }
        
        /* Efek typing pada placeholder (opsional) */
        .auth-panel .field input::placeholder {
            transition: opacity 0.3s ease;
        }
        
        .auth-panel .field input:focus::placeholder {
            opacity: 0.5;
        }
        
        /* Animasi subtle pada brand logo */
        .auth-panel .brand .brand-logo {
            transition: transform 0.5s ease, rotate 0.5s ease;
        }
        
        .auth-panel .brand:hover .brand-logo {
            transform: scale(1.1) rotate(-5deg);
        }
    </style>
</head>
<body>
<div class="auth-page">
    <!-- ===== VISUAL SIDE ===== -->
    <section class="auth-visual">
        <div class="auth-copy">
            <span class="eyebrow">🔐 Portal Rekrutmen SDI</span>
            <h1>Selamat datang kembali di <span>Sistem SDI</span></h1>
            <p>Masuk untuk mengelola lamaran, verifikasi dokumen, dan proses rekrutmen.</p>
        </div>
    </section>

    <!-- ===== PANEL SIDE ===== -->
    <main class="auth-panel">
        <div class="auth-card">
            <a class="brand" href="index.php">
                <div class="brand-logo">📚</div>
                <div class="brand-title">
                    <strong>Login Sistem</strong>
                    <span>Universitas Muhammadiyah Banjarmasin</span>
                </div>
            </a>
            <h1>Masuk ke Akun</h1>
            <p class="hint">Pilih role dan masukkan email serta password Anda.</p>

            <?php if($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form class="form" method="POST" action="">
                <div class="form-grid">
                    <!-- Role -->
                    <div class="field full">
                        <label for="role">Pilih Role</label>
                        <select name="role" id="role" required>
                            <option value="">-- Pilih Role --</option>
                            <option value="1">👑 Super Admin</option>
                            <option value="2">🏢 SDI / BAUK</option>
                            <option value="3">🎓 Rektor</option>
                            <option value="4">📋 Penilai</option>
                            <option value="5">👤 Pelamar</option>
                        </select>
                        <div class="hint" style="font-size:12px;color:#999;margin-top:4px;">Pilih role sesuai dengan akses Anda</div>
                    </div>

                    <!-- Email -->
                    <div class="field full">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" placeholder="Masukkan email Anda" required>
                    </div>

                    <!-- Password -->
                    <div class="field full">
                        <label for="password">Password</label>
                        <input type="password" name="password" id="password" placeholder="Masukkan password" required>
                    </div>
                </div>

                <div class="btn-group" style="display:flex;gap:12px;margin-top:8px;flex-wrap:wrap;">
                    <button class="btn btn-primary" type="submit" style="flex:1;min-width:120px;">🔑 Masuk</button>
                    <a class="btn btn-light" href="register_pelamar.php" style="flex:1;min-width:120px;">📝 Daftar Pelamar</a>
                </div>
            </form>

            <div class="register-link" style="text-align:center;margin-top:20px;font-size:14px;color:#6b7280;">
                Belum punya akun? <a href="register_pelamar.php" style="color:#667eea;text-decoration:none;font-weight:600;">Daftar sebagai Pelamar</a>
            </div>

            <div class="demo-info" style="text-align:center;margin-top:12px;padding:10px;background:#f0f4ff;border-radius:8px;font-size:12px;color:#6b7280;">
                <strong>🧪 Demo:</strong> superadmin@um-bjm.ac.id / 123456
            </div>
        </div>
    </main>
</div>
</body>
</html>