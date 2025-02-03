<?php
session_start();
require_once __DIR__ . '/../../koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_jadwal = intval($_POST['id_jadwal'] ?? 0);
    if ($id_jadwal > 0) {
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
