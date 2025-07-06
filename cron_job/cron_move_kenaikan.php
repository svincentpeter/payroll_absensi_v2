<?php
// cron_job/cron_move_kenaikan.php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../koneksi.php';

$conn->begin_transaction();

try {
    // Ambil semua kenaikan yang sudah selesai periode tapi belum difinalisasi
    $sql = "
        SELECT id, id_anggota, jumlah
        FROM kenaikan_gaji_tahunan
        WHERE status = 'aktif'
          AND tanggal_berakhir <= CURDATE()
    ";
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Query gagal: " . $conn->error);
    }

    $stmtUpdateKG = $conn->prepare("
        UPDATE kenaikan_gaji_tahunan
        SET status = 'selesai'
        WHERE id = ?
    ");

    $stmtUpdateGaji = $conn->prepare("
        UPDATE anggota_sekolah
        SET gaji_pokok = gaji_pokok + ?
        WHERE id = ?
    ");



    $stmtInsertHistory = $conn->prepare("
  INSERT INTO salary_history
    (id_anggota, jenis, amount, effective_date, created_by)
  VALUES (?, 'increment', ?, CURDATE(), 'cronjob')
");

    $stmtDelPayhead = $conn->prepare("
        DELETE FROM employee_payheads
        WHERE id_anggota = ? AND id_payhead = 100
    ");

    while ($row = $result->fetch_assoc()) {
        $idKG = $row['id'];
        $idAnggota = $row['id_anggota'];
        $jumlah = $row['jumlah'];

        /* 1. tandai selesai */
        $stmtUpdateKG->bind_param("i", $idKG);
        $stmtUpdateKG->execute();

        /* 2. naikkan gaji pokok */
        $stmtUpdateGaji->bind_param("di", $jumlah, $idAnggota);
        $stmtUpdateGaji->execute();

        /* 3. hapus payhead 100 draft/aktif */
        $stmtDelPayhead->bind_param("i", $idAnggota);
        $stmtDelPayhead->execute();

        /* 4. catat history */
        $stmtInsertHistory->bind_param("id", $idAnggota, $jumlah);
        $stmtInsertHistory->execute();
    }

    while ($row = $result->fetch_assoc()) {
        $idKG      = $row['id'];
        $idAnggota = $row['id_anggota'];
        $jumlah    = $row['jumlah'];

        // Tandai selesai
        $stmtUpdateKG->bind_param("i", $idKG);
        if (!$stmtUpdateKG->execute()) {
            throw new Exception("Gagal update status kenaikan ID $idKG: " . $stmtUpdateKG->error);
        }

        // Tambah ke gaji pokok
        $stmtUpdateGaji->bind_param("di", $jumlah, $idAnggota);
        if (!$stmtUpdateGaji->execute()) {
            throw new Exception("Gagal update gaji pokok anggota ID $idAnggota: " . $stmtUpdateGaji->error);
        }

        // Catat ke history
        $stmtInsertHistory->bind_param("id", $idAnggota, $jumlah);
        if (!$stmtInsertHistory->execute()) {
            throw new Exception("Gagal insert salary_history ID anggota $idAnggota: " . $stmtInsertHistory->error);
        }

        // Audit opsional
        add_audit_log(
            $conn,
            "system",
            "KenaikanGajiSelesai",
            "ID $idKG - Anggota $idAnggota naik Rp " . number_format($jumlah, 0, ',', '.')
        );
    }

    // Cleanup
    $stmtUpdateKG->close();
    $stmtUpdateGaji->close();
    $stmtInsertHistory->close();

    $conn->commit();
    echo "✅ Cron selesai. Semua kenaikan gaji telah difinalisasi.\n";
} catch (Exception $e) {
    $conn->rollback();
    error_log("[CRON ERROR] " . $e->getMessage());
    echo "❌ Cron gagal: " . $e->getMessage() . "\n";
}
