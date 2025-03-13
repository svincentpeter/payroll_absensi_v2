<?php
// logout.php

// Mulai session secara aman
require_once __DIR__ . '/helpers.php';
start_session_safe();

// Sertakan koneksi database
require_once __DIR__ . '/koneksi.php';

// Mendapatkan informasi pengguna sebelum logout
$user_id = NULL;
$username = 'Unknown';
$role = 'Unknown';

if (isset($_SESSION['role'])) {
    if (isset($_SESSION['user_id'])) {
        // Pengguna dari tabel users
        $user_id = intval($_SESSION['user_id']);
        $username = $_SESSION['username'] ?? 'Unknown';
        $role = $_SESSION['role'];
    } elseif (isset($_SESSION['nip'])) {
        // Pengguna dari tabel anggota_sekolah
        $role = $_SESSION['role'];
        $username = $_SESSION['nip'] ?? 'Unknown';
        // Karena anggota_sekolah tidak terhubung dengan users.id_user, set user_id = NULL
        $user_id = NULL;
    }
}

// Catat Audit Log untuk Logout jika role tidak kosong
if (!empty($role)) {
    add_audit_log($conn, $user_id, 'Logout', "Pengguna '{$username}' dengan role '{$role}' berhasil logout.");
}

// Hancurkan session
$_SESSION = [];
session_unset();
session_destroy();

// Redirect ke halaman login menggunakan getBaseUrl()
header("Location: " . getBaseUrl() . "/login.php");
exit();
?>
