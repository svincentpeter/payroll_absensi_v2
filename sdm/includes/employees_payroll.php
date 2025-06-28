<?php
// File: /payroll_absensi_v2/sdm/includes/employees_payroll.php

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

        // 1) Hitung total earnings & deductions
        $stmtSum = $conn->prepare("
            SELECT jenis, SUM(amount) AS total
              FROM employee_payheads
             WHERE id_anggota = ?
               AND status IN ('draft','revisi')
               AND IFNULL(is_rapel,0) = 0
               AND id_payhead <> 7
             GROUP BY jenis
        ");
        $stmtSum->bind_param("i", $id_anggota);
        $stmtSum->execute();
        $resSum = $stmtSum->get_result();
        $totalEarnings = $totalDeductions = 0;
        while ($row = $resSum->fetch_assoc()) {
            if ($row['jenis'] === 'earnings') {
                $totalEarnings = floatval($row['total']);
            } else {
                $totalDeductions = floatval($row['total']);
            }
        }
        $stmtSum->close();

        // 2) Ambil honor_jam_lebih
        $stmtHon = $conn->prepare("
            SELECT SUM(total_honor) AS honor_jam_lebih
              FROM kelebihan_jam_mengajar
             WHERE id_anggota = ? AND bulan = ? AND tahun = ? AND is_final = 1
        ");
        $stmtHon->bind_param("iii", $id_anggota, $bulan, $tahun);
        $stmtHon->execute();
        $rowHon = $stmtHon->get_result()->fetch_assoc();
        $honorJamLebih = floatval($rowHon['honor_jam_lebih'] ?? 0);
        $stmtHon->close();

        $totalEarnings += $honorJamLebih;

        // 3) Potongan absensi
        if (isset($_POST['potongan_absensi'])) {
            $potonganAbsensi = floatval($_POST['potongan_absensi']);
        } else {
            $stmtPot = $conn->prepare("
                SELECT potongan_absensi
                  FROM payroll
                 WHERE id_anggota = ? AND bulan = ? AND tahun = ? AND status = 'draft'
                 LIMIT 1
            ");
            $stmtPot->bind_param("iii", $id_anggota, $bulan, $tahun);
            $stmtPot->execute();
            $r = $stmtPot->get_result()->fetch_assoc();
            $potonganAbsensi = floatval($r['potongan_absensi'] ?? 0);
            $stmtPot->close();
        }
        $totalDeductions += $potonganAbsensi;

        // 4) Ambil data anggota
        $stmtEmp = $conn->prepare("
            SELECT gaji_pokok, salary_index_id, no_rekening
              FROM anggota_sekolah
             WHERE id = ? LIMIT 1
        ");
        $stmtEmp->bind_param("i", $id_anggota);
        $stmtEmp->execute();
        $empData = $stmtEmp->get_result()->fetch_assoc();
        $stmtEmp->close();

        if (!$empData) {
            send_response(1, 'Anggota tidak ditemukan.');
        }
        $basicSalary = floatval($empData['gaji_pokok']);
        $no_rekening = $empData['no_rekening'];

        // 5) Ambil base_salary jika ada
        $salaryIndexAmount = 0;
        if (!empty($empData['salary_index_id'])) {
            $stmtIdx = $conn->prepare("
                SELECT base_salary
                  FROM salary_indices
                 WHERE id = ? LIMIT 1
            ");
            $stmtIdx->bind_param("i", $empData['salary_index_id']);
            $stmtIdx->execute();
            $rowIdx = $stmtIdx->get_result()->fetch_assoc();
            $salaryIndexAmount = floatval($rowIdx['base_salary'] ?? 0);
            $stmtIdx->close();
        }

        // 6) Hitung gaji bersih
        $gajiBersih = $basicSalary
            + $salaryIndexAmount
            + $totalEarnings
            - $totalDeductions;

        // 7) Format tanggal payroll
        $tgl = trim($_POST['tgl_payroll'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $tgl)) {
            $tgl = str_replace('T',' ',$tgl).':00';
        } else {
            $tgl = date('Y-m-d H:i:s');
        }

        // 8) Reset dan hapus draft lama
        $stmtUpd = $conn->prepare("
            UPDATE payroll
               SET status = 'draft'
             WHERE id_anggota = ? AND bulan = ? AND tahun = ? AND status = 'revisi'
        ");
        $stmtUpd->bind_param("iii", $id_anggota,$bulan,$tahun);
        $stmtUpd->execute();
        $stmtUpd->close();

        $stmtDel = $conn->prepare("
            DELETE FROM payroll
             WHERE id_anggota = ? AND bulan = ? AND tahun = ? AND status = 'draft'
        ");
        $stmtDel->bind_param("iii", $id_anggota,$bulan,$tahun);
        $stmtDel->execute();
        $stmtDel->close();

        // === 9) INSERT PAYROLL via direct query ===
        $tglEsc   = $conn->real_escape_string($tgl);
        $noRekEsc = $conn->real_escape_string($no_rekening);
        $catEsc   = '';  // jika ada catatan, isi variabel ini

        $sqlInsert = "
            INSERT INTO payroll
              (id_anggota, bulan, tahun, tgl_payroll,
               gaji_pokok, salary_index_amount,
               total_pendapatan, total_potongan,
               honor_jam_lebih, potongan_absensi,
               gaji_bersih, no_rekening, catatan, status)
            VALUES (
              ".(int)$id_anggota.", ".(int)$bulan.", ".(int)$tahun.",
              '{$tglEsc}', ".(float)$basicSalary.",
              ".(float)$salaryIndexAmount.", ".(float)$totalEarnings.",
              ".(float)$totalDeductions.", ".(float)$honorJamLebih.",
              ".(float)$potonganAbsensi.", ".(float)$gajiBersih.",
              '{$noRekEsc}', '{$catEsc}', 'draft'
            )
        ";
        if (!$conn->query($sqlInsert)) {
            send_response(1, 'Gagal insert payroll: '.$conn->error);
        }
        $newPayrollId = $conn->insert_id;

        // 10) Copy detail payhead ke payroll_detail
        // 10) Copy detail payhead ke payroll_detail
$stmtPH = $conn->prepare("
    SELECT id_payhead, jenis, amount
      FROM employee_payheads
     WHERE id_anggota = ? 
       AND status IN ('draft','revisi') 
       AND IFNULL(is_rapel,0)=0 
       AND id_payhead<>7
");
$stmtPH->bind_param("i", $id_anggota);
$stmtPH->execute();
$resPH = $stmtPH->get_result();
$stmtPH->close();

$stmtDet = $conn->prepare("
    INSERT INTO payroll_detail 
      (id_payroll, id_anggota, id_payhead, jenis, amount)
    VALUES (?,?,?,?,?)
");

while ($ph = $resPH->fetch_assoc()) {
    // Siapkan variabel ATAU passed-by-reference
    $bind_id_payhead = (int)   $ph['id_payhead'];
    $bind_jenis      = (string)$ph['jenis'];
    $bind_amount     = (float) $ph['amount'];

    // Sekarang bind_param hanya menerima variabel
    $stmtDet->bind_param(
        "iiisd",
        $newPayrollId,
        $id_anggota,
        $bind_id_payhead,
        $bind_jenis,
        $bind_amount
    );
    $stmtDet->execute();
}
$stmtDet->close();


        // 11) Audit log & notifikasi…
        $user_nip = $_SESSION['nip'] ?? '';
        add_audit_log($conn,$user_nip,'ProcessPayroll',"Payroll draft anggota=$id_anggota");

        if ($_SESSION['role']==='sdm') {
            $resNotif = $conn->query("SELECT id,nip FROM anggota_sekolah WHERE role IN ('keuangan','superadmin')");
            while ($u = $resNotif->fetch_assoc()) {
                $msg = "Payroll draft untuk anggota $id_anggota diproses SDM.";
                $stmtN = $conn->prepare("
                    INSERT INTO notifications (user_id,message,is_read,role_target)
                    VALUES (?,?,0,?)
                ");
                $roleT = 'keuangan';
                $stmtN->bind_param("iss",$u['id'],$msg,$roleT);
                $stmtN->execute();
                $stmtN->close();
            }
        }

        // 12) Respon sukses
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
    function RemovePayrollFile($conn)
    {
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
