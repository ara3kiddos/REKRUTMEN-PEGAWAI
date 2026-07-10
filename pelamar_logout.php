<?php require __DIR__.'/includes/config.php';
unset($_SESSION['pelamar_user_id'], $_SESSION['pelamar_user_name'], $_SESSION['pelamar_id']);
header('Location: login_pelamar.php');
exit;
