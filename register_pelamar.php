<?php 
require __DIR__.'/includes/config.php'; 
$error = $_GET['error'] ?? ''; 
$success = $_GET['success'] ?? ''; 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Pelamar</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* ============================================================
           ANIMASI TEKS SAJA
           ============================================================ */
        
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
        
        .auth-panel .field {
            opacity: 0;
            animation: fadeInUp 0.5s ease-out forwards;
        }
        
        .auth-panel .field:nth-child(1) { animation-delay: 0.5s; }
        .auth-panel .field:nth-child(2) { animation-delay: 0.6s; }
        .auth-panel .field:nth-child(3) { animation-delay: 0.7s; }
        .auth-panel .field:nth-child(4) { animation-delay: 0.8s; }
        .auth-panel .field:nth-child(5) { animation-delay: 0.9s; }
        .auth-panel .field:nth-child(6) { animation-delay: 1.0s; }
        .auth-panel .field:nth-child(7) { animation-delay: 1.1s; }
        
        .auth-panel .btn-group {
            opacity: 0;
            animation: fadeInUp 0.6s ease-out 1.2s forwards;
        }
        
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
            <span class="eyebrow">Portal Rekrutmen SDI</span>
            <h1>Buat akun untuk mengajukan <br><span>lamaran secara resmi</span></h1>
            <p>Gunakan email aktif karena surat panggilan tes, wawancara, dan informasi penerimaan akan dikirim melalui email pelamar.</p>
        </div>
    </section>

    <!-- ===== PANEL SIDE ===== -->
    <main class="auth-panel">
        <div class="auth-card">
            <a class="brand" href="index.php">
                <div class="brand-logo">📚</div>
                <div class="brand-title">
                    <strong>Daftar Pelamar</strong>
                    <span>Universitas Muhammadiyah Banjarmasin</span>
                </div>
            </a>
            <h1>Daftar Akun</h1>
            <p class="hint">Isi data akun terlebih dahulu. Setelah login, pelamar dapat mengirim lamaran dan mengunggah dokumen.</p>

            <?php if($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form class="form" action="proses_register_pelamar.php" method="POST">
                <div class="form-grid">
                    <div class="field full">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" required>
                    </div>
                    <div class="field full">
                        <label>Email Aktif</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="field">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="field">
                        <label>Konfirmasi Password</label>
                        <input type="password" name="password_confirm" required>
                    </div>
                    <div class="field">
                        <label>NIK</label>
                        <input type="text" name="nik">
                    </div>
                    <div class="field">
                        <label>No. HP/WhatsApp</label>
                        <input type="text" name="no_hp">
                    </div>
                    <div class="field full">
                        <label>Alamat</label>
                        <textarea name="alamat"></textarea>
                    </div>
                </div>

                <div class="btn-group" style="display:flex;gap:12px;margin-top:8px;flex-wrap:wrap;">
                    <button class="btn btn-primary" type="submit" style="flex:1;min-width:120px;">Buat Akun</button>
                    <a class="btn btn-light" href="login.php" style="flex:1;min-width:120px;">Sudah punya akun</a>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>