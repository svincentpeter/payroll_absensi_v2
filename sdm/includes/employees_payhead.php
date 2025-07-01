<?php
// File: /payroll_absensi_v2/sdm/includes/employees_payhead.php

// Semua fungsi terkait payhead karyawan
if (!function_exists('GetAllPayheads')) {
    function GetAllPayheads($conn)
    {
        verify_csrf_token($_POST['csrf_token'] ?? '');

        // Hanya ambil payheads selain Kenaikan Gaji Tahunan
        $sql = "
          SELECT id, nama_payhead, jenis, nominal
            FROM payheads
           WHERE code IS NULL
              OR code <> 'ANNUAL_INC'
           ORDER BY nama_payhead ASC
        ";
        $res = $conn->query($sql);
        if (!$res) {
            send_response(1, 'Query gagal GetAllPayheads: ' . $conn->error);
        }

        $payheads = [];
        while ($row = $res->fetch_assoc()) {
            $payheads[] = [
                'id'                => $row['id'],
                'nama_payhead'      => $row['nama_payhead'],
                'jenis_payhead'     => $row['jenis'],
                'jenis_payhead_idn' => translateJenis($row['jenis']),
                'nominal'           => $row['nominal']
            ];
        }

        // Audit log
        $user_nip = $_SESSION['nip'] ?? '';
        add_audit_log($conn, $user_nip, 'GetAllPayheads', "Mengambil semua payheads");

        send_response(0, $payheads);
    }
}

if (!function_exists('AssignPayheadsToEmployee')) {
    function AssignPayheadsToEmployee($conn)
    {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $empcode       = intval($_POST['empcode'] ?? 0);
        $payheads      = isset($_POST['payheads']) ? json_decode($_POST['payheads'], true) : [];
        $pay_amounts   = isset($_POST['pay_amounts']) ? json_decode($_POST['pay_amounts'], true) : [];
        $remarksArr    = $_POST['remarks'] ?? [];
        $rapels        = isset($_POST['rapels']) ? json_decode($_POST['rapels'], true) : [];
        $user_nip      = $_SESSION['nip'] ?? '';
        $selectedMonth = isset($_POST['selectedMonth']) ? intval($_POST['selectedMonth']) : date('n');
        $selectedYear  = isset($_POST['selectedYear'])  ? intval($_POST['selectedYear'])  : date('Y');
        $potonganAbsensi = isset($_POST['potongan_absensi']) ? intval($_POST['potongan_absensi']) : 0;

        add_audit_log(
            $conn,
            $user_nip,
            'AssignPayheadsToEmployee',
            "empcode={$empcode}, total payheads=" . count($payheads)
        );

        if ($empcode <= 0) {
            send_response(1, 'ID anggota tidak valid.');
        }
        if (empty($payheads)) {
            send_response(1, 'Tidak ada payheads yang dipilih.');
        }

        // Ambil payheads existing untuk anggota ini
        $stmtOld = $conn->prepare("SELECT * FROM employee_payheads WHERE id_anggota = ?");
        $stmtOld->bind_param("i", $empcode);
        $stmtOld->execute();
        $resOld = $stmtOld->get_result();
        $oldPayheads = [];
        while ($op = $resOld->fetch_assoc()) {
            $oldPayheads[$op['id_payhead']] = $op;
        }
        $stmtOld->close();

        // Get nip anggota
        $stmtNip = $conn->prepare("SELECT nip FROM anggota_sekolah WHERE id = ? LIMIT 1");
        $stmtNip->bind_param("i", $empcode);
        $stmtNip->execute();
        $resNip = $stmtNip->get_result();
        if ($resNip->num_rows === 0) {
            send_response(1, "Anggota dengan ID $empcode tidak ditemukan.");
        }
        $nip = $resNip->fetch_assoc()['nip'];
        $stmtNip->close();

        // Persiapan statement
        $stmtGet = $conn->prepare("SELECT jenis, nama_payhead, nominal FROM payheads WHERE id = ? LIMIT 1");
        $stmtIns = $conn->prepare("
            INSERT INTO employee_payheads
              (id_anggota, id_payhead, jenis, amount, status, remarks, support_doc_path, upload_file_blob, is_rapel)
            VALUES (?, ?, ?, ?, 'draft', ?, ?, ?, ?)
        ");
        $stmtUpd = $conn->prepare("
            UPDATE employee_payheads
               SET amount = ?, remarks = ?, support_doc_path = ?, upload_file_blob = ?, is_rapel = ?
             WHERE id_anggota = ? AND id_payhead = ?
        ");

        $conn->begin_transaction();
        try {
            // Hapus payhead yang tidak dipilih lagi
            foreach ($oldPayheads as $oldPid => $oldRow) {
                if (!in_array($oldPid, $payheads, true)) {
                    $stmtDel = $conn->prepare("
                        DELETE FROM employee_payheads
                         WHERE id_anggota = ? AND id_payhead = ?
                    ");
                    $stmtDel->bind_param("ii", $empcode, $oldPid);
                    $stmtDel->execute();
                    $stmtDel->close();
                }
            }

            // Update potongan_absensi di header payroll (draft)
            $stmtUpdatePayroll = $conn->prepare("
                UPDATE payroll
                   SET potongan_absensi = ?
                 WHERE id_anggota = ? AND bulan = ? AND tahun = ? AND status = 'draft'
            ");
            $stmtUpdatePayroll->bind_param("iiii",
                $potonganAbsensi,
                $empcode,
                $selectedMonth,
                $selectedYear
            );
            $stmtUpdatePayroll->execute();
            $stmtUpdatePayroll->close();

            // Simpan/Update semua payhead yang dipilih
            foreach ($payheads as $pid) {
                $pidInt         = intval($pid);
                $rawAmount      = $pay_amounts[$pidInt] ?? '0';
                $numericAmount  = floatval(str_replace(['.', ','], ['', '.'], $rawAmount));
                $remarkForThis  = $remarksArr[$pidInt] ?? '';
                $isRapel        = isset($rapels[$pidInt]) ? intval($rapels[$pidInt]) : 0;

                // Ambil data master payhead
                $stmtGet->bind_param("i", $pidInt);
                $stmtGet->execute();
                $resG = $stmtGet->get_result();
                if ($resG->num_rows === 0) {
                    throw new Exception("Payhead ID $pidInt tidak ditemukan di master payheads.");
                }
                $rowG     = $resG->fetch_assoc();
                $jenisPay = $rowG['jenis'];

                // Cek eksistensi
                $alreadyExists = isset($oldPayheads[$pidInt]);
                $path_file     = $alreadyExists ? $oldPayheads[$pidInt]['support_doc_path'] : '';
                $fileBlob      = null;
                $nullBlob      = null;

                // Handle upload file (sama seperti sebelumya)...
                if (!empty($_FILES['upload_file']['name'][$pidInt])) {
                    // ...upload validations & move_uploaded_file...
                    // atur $path_file sesuai upload baru
                }

                if ($alreadyExists) {
                    $stmtUpd->bind_param(
                        "dssbiii",
                        $numericAmount,
                        $remarkForThis,
                        $path_file,
                        $nullBlob,
                        $isRapel,
                        $empcode,
                        $pidInt
                    );
                    if ($fileBlob !== null) {
                        $stmtUpd->send_long_data(3, $fileBlob);
                    }
                    $stmtUpd->execute();
                } else {
                    $stmtIns->bind_param(
                        "iisdssbi",
                        $empcode,
                        $pidInt,
                        $jenisPay,
                        $numericAmount,
                        $remarkForThis,
                        $path_file,
                        $nullBlob,
                        $isRapel
                    );
                    if ($fileBlob !== null) {
                        $stmtIns->send_long_data(6, $fileBlob);
                    }
                    $stmtIns->execute();
                }
            }

            $stmtGet->close();
            $stmtIns->close();
            $stmtUpd->close();

            $conn->commit();
            send_response(0, 'Payheads berhasil disimpan/diupdate!');
        } catch (Exception $ex) {
            $conn->rollback();
            send_response(1, 'Gagal menugaskan payheads: ' . $ex->getMessage());
        }
    }
}

if (!function_exists('GetPayheadById')) {
    function GetPayheadById($conn)
    {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            send_response(1, 'ID payhead tidak valid.');
        }
        $stmt = $conn->prepare("
            SELECT id, nama_payhead, jenis AS jenis_payhead
              FROM payheads
             WHERE id = ?
             LIMIT 1
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $payhead = $res->fetch_assoc();
            $payhead['jenis_payhead_idn'] = translateJenis($payhead['jenis_payhead']);
            add_audit_log($conn, $_SESSION['nip'] ?? '', 'GetPayheadById', "Payhead ID=$id");
            send_response(0, $payhead);
        } else {
            send_response(1, 'Payhead tidak ditemukan.');
        }
    }
}
