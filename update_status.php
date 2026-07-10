<?php
require_once '../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CEK LOGIN ADMIN
if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit;
}

// Ambil data user
$stmt = $pdo->prepare("SELECT u.*, r.nama_role FROM users u JOIN roles r ON u.id_role = r.id_role WHERE u.id_user = ?");
$stmt->execute([$_SESSION['id_user']]);
$user = $stmt->fetch();

if (!$user || $user['id_role'] == 5) {
    header("Location: ../dashboard.php");
    exit;
}

$error = '';
$success = '';

// Proses update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $lamaran_id = $_POST['lamaran_id'] ?? 0;
    $status_id = $_POST['status_id'] ?? 0;
    $catatan = $_POST['catatan'] ?? '';
    
    if ($lamaran_id && $status_id) {
        try {
            $stmt = $pdo->prepare("
                UPDATE lamaran 
                SET id_status_lamaran = ?, catatan = ? 
                WHERE id_lamaran = ?
            ");
            $stmt->execute([$status_id, $catatan, $lamaran_id]);
            $success = 'Status lamaran berhasil diupdate!';
        } catch (PDOException $e) {
            $error = 'Gagal update status: ' . $e->getMessage();
        }
    }
}

// Ambil semua lamaran dengan detail
$lamaranList = $pdo->query("
    SELECT 
        lm.id_lamaran,
        u.nama_lengkap,
        u.email,
        l.nama_lowongan,
        lm.tanggal_lamaran,
        lm.id_status_lamaran,
        ms.nama_status as status_lamaran,
        lm.catatan
    FROM lamaran lm
    JOIN pelamar p ON lm.id_pelamar = p.id_pelamar
    JOIN users u ON p.id_user = u.id_user
    JOIN lowongan l ON lm.id_lowongan = l.id_lowongan
    JOIN master_status_lamaran ms ON lm.id_status_lamaran = ms.id_master_status_lamaran
    ORDER BY lm.id_lamaran DESC
")->fetchAll();

// Ambil semua status
$statusList = $pdo->query("SELECT * FROM master_status_lamaran ORDER BY urutan")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Status Lamaran</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="admin-shell">
    <h1>Update Status Lamaran</h1>
    
    <?php if($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Pelamar</th>
                    <th>Lowongan</th>
                    <th>Status Saat Ini</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($lamaranList as $l): ?>
                    <tr>
                        <td><?= $l['id_lamaran'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($l['nama_lengkap']) ?></strong>
                            <br><span style="font-size:12px;color:#999;"><?= htmlspecialchars($l['email']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($l['nama_lowongan']) ?></td>
                        <td>
                            <span class="pill pill-blue"><?= htmlspecialchars($l['status_lamaran']) ?></span>
                        </td>
                        <td>
                            <form method="POST" style="display:flex;gap:5px;flex-wrap:wrap;align-items:center;">
                                <input type="hidden" name="lamaran_id" value="<?= $l['id_lamaran'] ?>">
                                <select name="status_id" required>
                                    <?php foreach($statusList as $s): ?>
                                        <option value="<?= $s['id_master_status_lamaran'] ?>" 
                                            <?= $s['id_master_status_lamaran'] == $l['id_status_lamaran'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['nama_status']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="catatan" placeholder="Catatan (opsional)" style="flex:1;min-width:150px;padding:5px;">
                                <button type="submit" name="update_status" class="btn btn-primary btn-sm">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>