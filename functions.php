<?php
// ============================================================
// FILE: includes/functions.php
// FUNGSI-FUNGSI HELPER UNTUK CEK AKSES
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Cek apakah user sudah login
 */
function isLoggedIn() {
    return isset($_SESSION['id_user']);
}

/**
 * Cek apakah user memiliki role tertentu
 */
function hasRole($roleId) {
    if (!isLoggedIn()) return false;
    return $_SESSION['id_role'] == $roleId;
}

/**
 * Cek apakah user memiliki salah satu role yang diizinkan
 */
function hasAccess($allowedRoles = []) {
    if (!isLoggedIn()) return false;
    if (empty($allowedRoles)) return true;
    return in_array($_SESSION['id_role'], $allowedRoles);
}

/**
 * Cek akses dan redirect jika tidak diizinkan
 */
function checkAccess($allowedRoles = []) {
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit;
    }
    if (!empty($allowedRoles) && !hasAccess($allowedRoles)) {
        header("Location: dashboard.php");
        exit;
    }
}

/**
 * Ambil role user saat ini
 */
function getCurrentRole() {
    return $_SESSION['id_role'] ?? 0;
}

/**
 * Ambil nama role user saat ini
 */
function getCurrentRoleName() {
    return $_SESSION['role_name'] ?? 'Guest';
}

/**
 * Ambil nama lengkap user saat ini
 */
function getCurrentUserName() {
    return $_SESSION['nama_lengkap'] ?? 'User';
}

/**
 * Cek apakah user adalah Super Admin (role 1)
 */
function isSuperAdmin() {
    return hasRole(1);
}

/**
 * Cek apakah user adalah SDI (role 2)
 */
function isSDI() {
    return hasRole(2);
}

/**
 * Cek apakah user adalah Rektor (role 3)
 */
function isRektor() {
    return hasRole(3);
}

/**
 * Cek apakah user adalah Penilai (role 4)
 */
function isPenilai() {
    return hasRole(4);
}

/**
 * Cek apakah user adalah Pelamar (role 5)
 */
function isPelamar() {
    return hasRole(5);
}

/**
 * Cek apakah user adalah Admin (role 1,2,3,4)
 */
function isAdmin() {
    return isLoggedIn() && in_array(getCurrentRole(), [1, 2, 3, 4]);
}
?>