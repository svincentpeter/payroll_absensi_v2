<?php
// update_absensi.php

// Inisiasi session secara aman dan buat CSRF token
require_once __DIR__ . '/../helpers.php';
start_session_safe();
generate_csrf_token();

// Otorisasi: Hanya izinkan pengguna dengan role Pendidik (P) dan Tenaga Kependidikan (TK)
authorize(['P', 'TK']);

// Koneksi database
require_once __DIR__ . '/../koneksi.php';

// Proses update status absensi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifikasi CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $id_jadwal = intval($_POST['id_jadwal'] ?? 0);
    if ($id_jadwal > 0) {
        // Tentukan status baru berdasarkan tombol yang ditekan
        if (isset($_POST['confirm'])) {
            $new_status = 'hadir';
        } elseif (isset($_POST['tidak_hadir'])) {
            $new_status = 'tidak hadir';
        } else {
            $_SESSION['absensi_error'] = "Tidak ada aksi yang dipilih.";
            header("Location: dashboard_jadwal.php");
            exit();
        }

        $sql = "UPDATE jadwal_piket SET status = ? WHERE id_jadwal = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_status, $id_jadwal);
        if ($stmt->execute()) {
            $_SESSION['absensi_success'] = "Status kehadiran telah diperbarui menjadi '$new_status'.";
        } else {
            $_SESSION['absensi_error'] = "Gagal mengupdate status kehadiran.";
        }
        $stmt->close();
    } else {
        $_SESSION['absensi_error'] = "ID jadwal tidak valid.";
    }
}
header("Location: dashboard_jadwal.php");
exit();
?>
<?php
// Tutup koneksi database menggunakan fungsi dari helpers.php
close_db_connection();
?>