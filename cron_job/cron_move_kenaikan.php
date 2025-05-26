<?php
// cron_job/cron_move_kenaikan.php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../koneksi.php';

// Mulai transaksi
$conn->begin_transaction();

try {
    // 1. Ambil semua kenaikan gaji yang masih aktif dan sudah melewati tanggal berakhir
    $sql = "SELECT * FROM kenaikan_gaji_tahunan 
            WHERE status = 'aktif' 
              AND tanggal_berakhir < CURDATE() 
              AND pindah_ke_lain_lain = 0";
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Query gagal: " . $conn->error);
    }

    // 2. Untuk setiap record, update status dan pindah ke lain-lain
    while ($row = $result->fetch_assoc()) {
        $idKG = $row['id'];
        $idAnggota = $row['id_anggota'];
        $nominalKenaikan = $row['jumlah'];

        // Update kenaikan_gaji_tahunan menjadi selesai
        $sqlUpdateKG = "UPDATE kenaikan_gaji_tahunan 
                        SET status = 'selesai', pindah_ke_lain_lain = 1 
                        WHERE id = ?";
        $stmtUpdateKG = $conn->prepare($sqlUpdateKG);
        $stmtUpdateKG->bind_param("i", $idKG);
        if (!$stmtUpdateKG->execute()) {
            throw new Exception("Gagal update kenaikan_gaji_tahunan: " . $stmtUpdateKG->error);
        }
        $stmtUpdateKG->close();

        // 3. Perbarui atau insert ke employee_payheads untuk payhead 'Lain-lain'
        // Misal, ID payhead untuk "Lain-lain" di master payheads adalah 99.
        $idPayheadLainLain = 100;
        $sqlCheck = "SELECT * FROM employee_payheads WHERE id_anggota = ? AND id_payhead = ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bind_param("ii", $idAnggota, $idPayheadLainLain);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        if ($resCheck->num_rows > 0) {
            // Jika sudah ada, update dengan menambahkan nominal kenaikan
            $rowEP = $resCheck->fetch_assoc();
            $newAmount = floatval($rowEP['amount']) + $nominalKenaikan;
            $sqlUpdEP = "UPDATE employee_payheads SET amount = ? 
                         WHERE id_anggota = ? AND id_payhead = ?";
            $stmtUpdEP = $conn->prepare($sqlUpdEP);
            $stmtUpdEP->bind_param("dii", $newAmount, $idAnggota, $idPayheadLainLain);
            if (!$stmtUpdEP->execute()) {
                throw new Exception("Gagal update employee_payheads: " . $stmtUpdEP->error);
            }
            $stmtUpdEP->close();
        } else {
            // Jika belum ada, insert record baru
            $sqlInsEP = "INSERT INTO employee_payheads (id_anggota, id_payhead, jenis, amount, status, remarks, support_doc_path, upload_file_blob, is_rapel)
                         VALUES (?, ?, 'earnings', ?, 'draft', 'Kenaikan gaji otomatis pindah ke Lain-lain', '', NULL, 0)";
            $stmtInsEP = $conn->prepare($sqlInsEP);
            $stmtInsEP->bind_param("iid", $idAnggota, $idPayheadLainLain, $nominalKenaikan);
            if (!$stmtInsEP->execute()) {
                throw new Exception("Gagal insert employee_payheads: " . $stmtInsEP->error);
            }
            $stmtInsEP->close();
        }
        $stmtCheck->close();

        // (Opsional) Catat audit log per kenaikan
        add_audit_log($conn, "system", "CronMoveKenaikan", "Kenaikan gaji untuk anggota ID $idAnggota dengan kenaikan nominal Rp " . number_format($nominalKenaikan,0,',','.') . " telah dipindahkan ke Lain-lain.");
    }

    $conn->commit();
    echo "Cron job berhasil dijalankan.\n";
} catch (Exception $ex) {
    $conn->rollback();
    echo "Cron job gagal: " . $ex->getMessage() . "\n";
}
