<?php
// Semua proses payroll dan pengecekan completion
if (!function_exists('ProcessPayroll')) {
    function ProcessPayroll($conn)
{
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id_anggota = intval($_POST['id_anggota'] ?? 0);
    $bulan      = intval($_POST['selectedMonth'] ?? 0);
    $tahun      = intval($_POST['selectedYear'] ?? 0);

    if ($id_anggota <= 0 || $bulan <= 0 || $tahun <= 0) {
        send_response(1, 'Parameter tidak valid untuk proses payroll.');
    }

    // 1) Hitung total earnings & deductions (status draft/revisi), kecuali rapel
    $sqlSum = "
        SELECT jenis, SUM(amount) AS total
          FROM employee_payheads
         WHERE id_anggota = ?
           AND status IN ('draft','revisi')
           AND IFNULL(is_rapel, 0) = 0
        GROUP BY jenis
    ";
    $stmtSum = $conn->prepare($sqlSum);
    $stmtSum->bind_param("i", $id_anggota);
    if (!$stmtSum->execute()) {
        $stmtSum->close();
        send_response(1, 'Gagal menghitung total payheads (draft/revisi).');
    }
    $resSum = $stmtSum->get_result();

    $totalEarnings   = 0;
    $totalDeductions = 0;
    while ($row = $resSum->fetch_assoc()) {
        if ($row['jenis'] === 'earnings') {
            $totalEarnings = floatval($row['total']);
        } else {
            $totalDeductions = floatval($row['total']);
        }
    }
    $stmtSum->close();

    $salaryIndexAmount = 0;
    // 2) Update salary index
    updateSalaryIndexForUser($conn, $id_anggota);

    // 3) Ambil data anggota
    $stmtEmp = $conn->prepare("
        SELECT gaji_pokok, salary_index_id, no_rekening
          FROM anggota_sekolah
         WHERE id = ?
         LIMIT 1
    ");
    $stmtEmp->bind_param("i", $id_anggota);
    if (!$stmtEmp->execute()) {
        $stmtEmp->close();
        send_response(1, 'Gagal mengambil data anggota.');
    }
    $resEmp = $stmtEmp->get_result();
    if ($resEmp->num_rows == 0) {
        $stmtEmp->close();
        send_response(1, 'Anggota tidak ditemukan.');
    }
    $empData = $resEmp->fetch_assoc();
    $stmtEmp->close();

    $gajiPokokEmployee = floatval($empData['gaji_pokok']);
    $no_rekening       = $empData['no_rekening'];
    $salaryIndexBase   = 0;
    $salaryIndexAmount = $salaryIndexBase;

    // Ambil base_salary dari salary_index (jika ada)
    if (!empty($empData['salary_index_id'])) {
        $stmtIndex = $conn->prepare("
            SELECT base_salary
              FROM salary_indices
             WHERE id = ?
             LIMIT 1
        ");
        $stmtIndex->bind_param("i", $empData['salary_index_id']);
        if ($stmtIndex->execute()) {
            $resIndex = $stmtIndex->get_result();
            if ($resIndex->num_rows > 0) {
                $rowIndex = $resIndex->fetch_assoc();
                $salaryIndexBase = floatval($rowIndex['base_salary']);
            }
        }
        $stmtIndex->close();
    }

    // 4) Simpan komponen terpisah: basic salary & salary index
    $basicSalary        = $gajiPokokEmployee;
    $salaryIndexAmount  = $salaryIndexBase;
    $gajiBersih         = $basicSalary
        + $salaryIndexAmount
        + $totalEarnings
        - $totalDeductions;

    // 5) Insert payroll => status draft
    $tglPayroll    = date('Y-m-d H:i:s');
    $catatan       = '';
    $statusPayroll = 'draft';

    $stmtPayroll = $conn->prepare("
        INSERT INTO payroll
            (id_anggota, bulan, tahun, tgl_payroll,
             gaji_pokok, salary_index_amount,
             total_pendapatan, total_potongan,
             gaji_bersih, no_rekening,
             catatan, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtPayroll->bind_param(
        "iiisddddddss",
        $id_anggota,
        $bulan,
        $tahun,
        $tglPayroll,
        $basicSalary,
        $salaryIndexAmount,
        $totalEarnings,
        $totalDeductions,
        $gajiBersih,
        $no_rekening,
        $catatan,
        $statusPayroll
    );
    if (!$stmtPayroll->execute()) {
        $stmtPayroll->close();
        send_response(1, 'Gagal insert payroll (draft): ' . $stmtPayroll->error);
    }
    $newPayrollId = $stmtPayroll->insert_id;
    $stmtPayroll->close();

    // 6) Insert detail payhead ke payroll_detail => status draft
    $sqlPH = "
       SELECT id_payhead, jenis, amount
         FROM employee_payheads
        WHERE id_anggota = ?
          AND status IN ('draft','revisi')
          AND IFNULL(is_rapel, 0) = 0
    ";
    $stmtPH = $conn->prepare($sqlPH);
    $stmtPH->bind_param("i", $id_anggota);
    if (!$stmtPH->execute()) {
        $stmtPH->close();
        send_response(1, 'Gagal menyalin payheads ke payroll_detail: ' . $stmtPH->error);
    }
    $resPH = $stmtPH->get_result();
    $stmtPH->close();

    $sqlInsDetail = "
   INSERT INTO payroll_detail (id_payroll, id_anggota, id_payhead, jenis, amount)
   VALUES (?, ?, ?, ?, ?)
";
    $stmtDet = $conn->prepare($sqlInsDetail);

    while ($ph = $resPH->fetch_assoc()) {
        $pid   = intval($ph['id_payhead']);
        $jenis = $ph['jenis'];
        $amt   = floatval($ph['amount']);

        // Perhatikan: sekarang bind 5 parameter (iiisd)
        $stmtDet->bind_param("iiisd", $newPayrollId, $id_anggota, $pid, $jenis, $amt);
        $stmtDet->execute();
    }

    $stmtDet->close();

    // 7) Audit log
    $user_nip   = $_SESSION['nip'] ?? '';
    $detailsLog = "SDM memproses payroll => draft, anggota ID = $id_anggota, oleh $user_nip. (payroll_id=$newPayrollId)";
    add_audit_log($conn, $user_nip, 'ProcessPayroll', $detailsLog);

    // 8) Notifikasi ke keuangan & superadmin jika diproses oleh SDM
    if ($_SESSION['role'] === 'sdm') {
        $sqlNotif = "SELECT id, nip FROM anggota_sekolah WHERE role IN ('keuangan','superadmin')";
        $resNotif = $conn->query($sqlNotif);
        if ($resNotif) {
            while ($u = $resNotif->fetch_assoc()) {
                $msg    = "Payroll (draft) untuk anggota ID $id_anggota telah diproses oleh SDM.";
                $target = 'keuangan';
                $stmtN  = $conn->prepare("
                    INSERT INTO notifications (user_id, message, is_read, role_target)
                    VALUES (?, ?, 0, ?)
                ");
                if ($stmtN) {
                    $stmtN->bind_param("iss", $u['id'], $msg, $target);
                    $stmtN->execute();
                    $stmtN->close();
                }
            }
        }
    }

    // 9) Kembalikan respon
    send_response(0, 'Payroll berhasil diproses (status draft).');
}
}
if (!function_exists('CheckPayrollCompletion')) {
    function CheckPayrollCompletion($conn)
{
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $bulan = intval($_POST['selectedMonth'] ?? 0);
    $tahun = intval($_POST['selectedYear']  ?? 0);
    if ($bulan <= 0 || $tahun <= 0) {
        send_response(1, 'Parameter bulan/tahun tidak valid.');
    }
    $sql = "SELECT jenjang, COUNT(*) as pending
            FROM anggota_sekolah
            WHERE id NOT IN (
                SELECT id_anggota FROM payroll
                WHERE bulan = ? AND tahun = ? AND status = 'final'
            )
            GROUP BY jenjang";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $bulan, $tahun);
    if (!$stmt->execute()) {
        send_response(1, 'Gagal menghitung anggota yang belum final.');
    }
    $res = $stmt->get_result();
    $messages = [];
    while ($r = $res->fetch_assoc()) {
        if (intval($r['pending']) > 0) {
            $messages[] = "Terdapat {$r['pending']} anggota jenjang '{$r['jenjang']}' yang belum selesai payroll.";
        }
    }
    // [Audit log baru]
    $user_nip   = $_SESSION['nip'] ?? '';
    add_audit_log($conn, $user_nip, 'CheckPayrollCompletion', "Memeriksa completion payroll bulan=$bulan, tahun=$tahun");

    if (empty($messages)) {
        send_response(0, ['complete' => true, 'messages' => []]);
    } else {
        send_response(0, ['complete' => false, 'messages' => $messages]);
    }
}
}

if (!function_exists('RemovePayrollFile')) {
    function RemovePayrollFile($conn) {
        // Ambil data request
        $empcode = intval($_POST['empcode'] ?? 0);
        $payheadId = intval($_POST['payhead_id'] ?? 0);
        $csrf = $_POST['csrf_token'] ?? '';
        verify_csrf_token($csrf);

        // Validasi input
        if ($empcode <= 0 || $payheadId <= 0) {
            send_response(1, 'Data tidak valid.');
        }

        // Cari file path
        $q = $conn->prepare("SELECT support_doc_path FROM employee_payheads WHERE id_anggota = ? AND id_payhead = ? AND status = 'draft' LIMIT 1");
        $q->bind_param('ii', $empcode, $payheadId);
        $q->execute();
        $r = $q->get_result()->fetch_assoc();
        $q->close();

        if ($r && !empty($r['support_doc_path'])) {
            $filePath = $r['support_doc_path'];
            // Hapus file di server
            if (file_exists($filePath)) @unlink($filePath);

            // Update DB kosongkan path
            $up = $conn->prepare("UPDATE employee_payheads SET support_doc_path = NULL WHERE id_anggota = ? AND id_payhead = ? AND status = 'draft'");
            $up->bind_param('ii', $empcode, $payheadId);
            $up->execute();
            $up->close();

            send_response(0, 'File berhasil dihapus');
        }
        send_response(1, 'File tidak ditemukan');
    }
}

?>
