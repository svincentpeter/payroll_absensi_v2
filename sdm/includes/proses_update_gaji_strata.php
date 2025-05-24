<?php
file_put_contents(__DIR__ . "/log_test.txt", date('Y-m-d H:i:s')." test\n", FILE_APPEND);

require_once '../../koneksi.php';
require_once '../../helpers.php';
require_once 'mgk_salary_handler.php';

// Hanya izinkan method POST & role admin/SDM
start_session_safe();
if (!in_array($_SESSION['role'] ?? '', ['M', 'M:SDM', 'M:Superadmin'])) {
    send_response(1, "Tidak diizinkan.");
}

$ok = updateSalaryIndexForAll($conn);
if ($ok) {
    add_audit_log($conn, $_SESSION['nip'] ?? '', 'Sinkron Gaji Pokok', 'Update massal semua gaji pokok via tombol Sinkron');
    send_response(0, "Berhasil update semua gaji pokok anggota.");
} else {
    send_response(1, "Sebagian data gagal diupdate. Periksa log error.");
}
