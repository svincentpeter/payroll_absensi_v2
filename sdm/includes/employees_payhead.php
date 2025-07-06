<?php
// File: /payroll_absensi_v2/sdm/includes/employees_payhead.php

// di bagian atas AssignPayheadsToEmployee(), setelah validasi $empcode:
$selectedMonth    = isset($_POST['selectedMonth'])     ? intval($_POST['selectedMonth'])      : date('n');
$selectedYear     = isset($_POST['selectedYear'])      ? intval($_POST['selectedYear'])       : date('Y');
$potonganAbsensi  = isset($_POST['potongan_absensi'])  ? floatval($_POST['potongan_absensi']) : 0;

// *** PERBAIKAN ***: ambil ranking_id dari form
$ranking_id       = isset($_POST['ranking_id'])        ? intval($_POST['ranking_id'])         : 0;

function cekRankingValid(mysqli $c, int $id): bool {
    $st = $c->prepare("SELECT 1 FROM ranking_kenaikan WHERE id=? AND is_aktif=1");
    $st->bind_param("i", $id);
    $st->execute();
    $st->store_result();
    return $st->num_rows > 0;
}

// Semua fungsi terkait payhead karyawan
if (!function_exists('GetAllPayheads')) {
    function GetAllPayheads($conn)
    {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $sql = "SELECT id, nama_payhead, jenis, nominal FROM payheads ORDER BY nama_payhead ASC";
        $res = $conn->query($sql);
        if (!$res) {
            send_response(1, 'Query gagal GetAllPayheads.');
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
        // [Audit log baru]
        $user_nip = $_SESSION['nip'] ?? '';
        add_audit_log($conn, $user_nip, 'GetAllPayheads', "Mengambil semua payheads");

        send_response(0, $payheads);
    }
}
if (!function_exists('AssignPayheadsToEmployee')) {
    function AssignPayheadsToEmployee($conn)
    {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $empcode = intval($_POST['empcode'] ?? 0);
        $payheads = isset($_POST['payheads']) ? json_decode($_POST['payheads'], true) : [];
        $pay_amounts = isset($_POST['pay_amounts']) ? json_decode($_POST['pay_amounts'], true) : [];
        $remarksArr = $_POST['remarks'] ?? [];
        $rapels = isset($_POST['rapels']) ? json_decode($_POST['rapels'], true) : [];
        $user_nip = $_SESSION['nip'] ?? '';
        $detailsLog = "AssignPayheadsToEmployee: empcode=$empcode, total payheads=" . count($payheads);
        add_audit_log($conn, $user_nip, 'AssignPayheadsToEmployee', $detailsLog);

        // *** PERBAIKAN ***: ambil kembali ranking_id di dalam fungsi
        $selectedMonth    = isset($_POST['selectedMonth'])     ? intval($_POST['selectedMonth'])      : date('n');
        $selectedYear     = isset($_POST['selectedYear'])      ? intval($_POST['selectedYear'])       : date('Y');
        $potonganAbsensi  = isset($_POST['potongan_absensi'])  ? floatval($_POST['potongan_absensi']) : 0;
        $ranking_id       = isset($_POST['ranking_id'])        ? intval($_POST['ranking_id'])         : 0;

        if ($empcode <= 0) {
            send_response(1, 'ID anggota tidak valid.');
        }
        if (empty($payheads)) {
            send_response(1, 'Tidak ada payheads yang dipilih.');
        }

        // Ambil payheads existing untuk anggota ini
        $sqlOld = "SELECT * FROM employee_payheads WHERE id_anggota = ?";
        $stmtOld = $conn->prepare($sqlOld);
        $stmtOld->bind_param("i", $empcode);
        $stmtOld->execute();
        $resOld = $stmtOld->get_result();
        $oldPayheads = [];
        while ($op = $resOld->fetch_assoc()) {
            $oldPayheads[$op['id_payhead']] = $op;
        }
        $stmtOld->close();

        // Get nip anggota
        $stmtNip = $conn->prepare("SELECT nip FROM anggota_sekolah WHERE id=? LIMIT 1");
        $stmtNip->bind_param("i", $empcode);
        $stmtNip->execute();
        $resNip = $stmtNip->get_result();
        if ($resNip->num_rows === 0) {
            send_response(1, "Anggota dengan ID $empcode tidak ditemukan.");
        }
        $rowNip = $resNip->fetch_assoc();
        $nip = $rowNip['nip'];
        $stmtNip->close();

        $stmtGet = $conn->prepare("SELECT jenis, nama_payhead, nominal FROM payheads WHERE id = ? LIMIT 1");
        $sqlInsert = "INSERT INTO employee_payheads (id_anggota, id_payhead, jenis, amount, status, remarks, support_doc_path, upload_file_blob, is_rapel) VALUES (?, ?, ?, ?, 'draft', ?, ?, ?, ?)";
        $stmtIns = $conn->prepare($sqlInsert);
        $sqlUpdate = "UPDATE employee_payheads SET amount = ?, remarks = ?, support_doc_path = ?, upload_file_blob = ?, is_rapel = ? WHERE id_anggota = ? AND id_payhead = ?";
        $stmtUpd = $conn->prepare($sqlUpdate);

        $conn->begin_transaction();
        try {
            if (!empty($_POST['chkKenaikanGajiTahunan'])) {

    $namaKenaikan    = trim($_POST['nama_kenaikan'] ?? '');
    $nominalKenaikan = floatval(str_replace(['.', ','], ['', '.'], $_POST['nominal_kenaikan'] ?? '0'));

    /*  ❱❱ Ganti penentuan periode: pakai bulan & tahun payroll  ❰❰ */
    // $tglPayroll sudah tidak dipakai lagi
    $startDate = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);        // 2025-07-01
    $endDate   = date('Y-m-d', strtotime('+1 year -1 day', strtotime($startDate))); // 2026-06-30

    // --- validasi ----------------------------------------------------------
    if ($nominalKenaikan <= 0) {
        throw new Exception('Nominal kenaikan harus lebih besar dari 0');
    }
    if (!cekRankingValid($conn, $ranking_id)) {
        throw new Exception('Ranking tidak valid');
    }

                // pastikan hanya satu aktif per periode
                $stmtCek = $conn->prepare("
                    SELECT id FROM kenaikan_gaji_tahunan 
                     WHERE id_anggota=? AND status='aktif' 
                       AND tanggal_mulai<=? AND tanggal_berakhir>=?
                ");
                $stmtCek->bind_param("iss", $empcode, $startDate, $startDate);
                $stmtCek->execute();
                $stmtCek->store_result();
                if ($stmtCek->num_rows === 0) {
    // INSERT baru (tetap seperti sekarang)
    $stmtKG = $conn->prepare("
        INSERT INTO kenaikan_gaji_tahunan
          (id_anggota,nama_kenaikan,jumlah,tanggal_mulai,tanggal_berakhir,
           status,ranking_id,dibuat_pada)
        VALUES (?,?,?,?,?,'aktif',?,NOW())");
    $stmtKG->bind_param("isdssi",
        $empcode,$namaKenaikan,$nominalKenaikan,$startDate,$endDate,$ranking_id);
    $stmtKG->execute();
    $stmtKG->close();
} else {
    // 🔴 **tambahkan blok UPDATE**  ➜ sinkronkan nilai lama
    $stmtKG = $conn->prepare("
        UPDATE kenaikan_gaji_tahunan
           SET nama_kenaikan = ?, jumlah = ?, ranking_id = ?
         WHERE id_anggota = ? AND status='aktif'
           AND tanggal_mulai <= ? AND tanggal_berakhir >= ?");
    $stmtKG->bind_param("sdiiss",
        $namaKenaikan,$nominalKenaikan,$ranking_id,
        $empcode,$startDate,$startDate);
    $stmtKG->execute();
    $stmtKG->close();
}
$stmtCek->close();

                // tambahkan juga sebagai payhead khusus ID=100
                $idPayheadKenaikan = 100;
                $earningStr        = 'earnings';
                $support           = '';
                $blobNull          = null;
                $isRapel           = 0;

                if (!isset($oldPayheads[$idPayheadKenaikan])) {
                    // INSERT baru
                    $stmtIns->bind_param(
                        "iisdssbi", 
                        $empcode,
                        $idPayheadKenaikan,
                        $earningStr,
                        $nominalKenaikan,
                        $namaKenaikan,
                        $support,
                        $blobNull,
                        $isRapel
                    );
                    $stmtIns->execute();
                } else {
                    // UPDATE existing
                    $stmtUpd->bind_param(
                        "dssbiii",
                        $nominalKenaikan,
                        $namaKenaikan,
                        $support,
                        $blobNull,
                        $isRapel,
                        $empcode,
                        $idPayheadKenaikan
                    );
                    $stmtUpd->execute();
                }
            }

            // Hapus payhead yang tidak dipilih lagi
            foreach ($oldPayheads as $oldPid => $_) {
                if (!in_array($oldPid, $payheads, true)) {
                    $del = $conn->prepare(
                        "DELETE FROM employee_payheads WHERE id_anggota=? AND id_payhead=?"
                    );
                    $del->bind_param("ii", $empcode, $oldPid);
                    $del->execute();
                    $del->close();
                }
            }
            

            // Simpan/Update semua payhead yang dipilih
            foreach ($payheads as $pid) {
                $pidInt = intval($pid);
                $rawAmount = $pay_amounts[$pidInt] ?? '0';
                $numericAmount = floatval(str_replace(['.', ','], ['', '.'], $rawAmount));
                $remarkForThis = $remarksArr[$pidInt] ?? '';
                $isRapel = isset($rapels[$pidInt]) ? intval($rapels[$pidInt]) : 0;

                // --- Get master payhead data
                $stmtGet->bind_param("i", $pidInt);
                $stmtGet->execute();
                $resG = $stmtGet->get_result();
                if ($resG->num_rows === 0) {
                    throw new Exception("Payhead ID $pidInt tidak ditemukan di master payheads.");
                }
                $rowG = $resG->fetch_assoc();
                $jenisPay = $rowG['jenis'];

                // Default nilai
                $alreadyExists = isset($oldPayheads[$pidInt]);
                $path_file = $alreadyExists ? $oldPayheads[$pidInt]['support_doc_path'] : '';
                $fileBlob = null;
                $nullBlob = null;

                // --- Handle File Upload
                $fArr = $_FILES['upload_file'] ?? null;
                if ($fArr && isset($fArr['name'][$pidInt]) && !empty($fArr['name'][$pidInt])) {
                    $errCode = $fArr['error'][$pidInt];
                    $tmpName = $fArr['tmp_name'][$pidInt];
                    $fileName = $fArr['name'][$pidInt];
                    $fileSize = $fArr['size'][$pidInt];
                    if ($errCode === UPLOAD_ERR_OK) {
                        if ($fileSize > 2097152) {
                            throw new Exception("File payhead $pidInt: ukuran file > 2MB.");
                        }
                        $allowedExt = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        if (!in_array($ext, $allowedExt)) {
                            throw new Exception("File payhead $pidInt: ekstensi $ext tidak diizinkan.");
                        }
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = finfo_file($finfo, $tmpName);
                        finfo_close($finfo);
                        $allowedMimes = [
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'image/jpeg',
                            'image/png'
                        ];
                        if (!in_array($mimeType, $allowedMimes)) {
                            throw new Exception("File payhead $pidInt: MIME $mimeType tidak valid.");
                        }
                        // Hapus file lama
                        if ($alreadyExists && !empty($oldPayheads[$pidInt]['support_doc_path'])) {
                            $oldPath = __DIR__ . '/../' . ltrim($oldPayheads[$pidInt]['support_doc_path'], '/');
                            if (is_file($oldPath)) unlink($oldPath);
                        }
                        $payrollDir = __DIR__ . '/../../uploads/payroll_docs/' . $empcode . '/' . $selectedYear . '_' . $selectedMonth . '/';
                        if (!is_dir($payrollDir)) {
                            mkdir($payrollDir, 0775, true);
                        }
                        $namaBersih = preg_replace('/[^A-Za-z0-9_\- ]+/', '', $rowG['nama_payhead']);
                        $namaBersih = str_replace(' ', '_', trim($namaBersih));
                        $newName = "payhead{$pidInt}_{$nip}_{$selectedYear}{$selectedMonth}_" . time() . '.' . $ext;
                        $destPath = $payrollDir . $newName;
                        if (!move_uploaded_file($tmpName, $destPath)) {
                            throw new Exception("Gagal upload file payhead $pidInt.");
                        }
                        $path_file = '/payroll_absensi_v2/uploads/payroll_docs/' . $empcode . '/' . $selectedYear . '_' . $selectedMonth . '/' . $newName;
                        $fileBlob = null;
                    }
                }


                // --- Update payroll draft dengan potongan absensi (TAMBAHKAN BAGIAN INI sebelum commit) ---
                $sqlUpdatePayroll = "UPDATE payroll 
                SET potongan_absensi = ? 
                WHERE id_anggota = ? AND bulan = ? AND tahun = ? AND status = 'draft'";
                // update draft payroll supaya potongan_absensi selalu sinkron
                $stmtUpdatePayroll = $conn->prepare("
    UPDATE payroll
       SET potongan_absensi = ?
     WHERE id_anggota = ? AND bulan = ? AND tahun = ? AND status = 'draft'
");
                $stmtUpdatePayroll->bind_param(
                    "diii",
                    $potonganAbsensi,
                    $empcode,
                    $selectedMonth,
                    $selectedYear
                );
                $stmtUpdatePayroll->execute();
                $stmtUpdatePayroll->close();


                // --- Insert / Update DB
                if ($alreadyExists) {
                    $stmtUpd->bind_param("dssbiii", $numericAmount, $remarkForThis, $path_file, $nullBlob, $isRapel, $empcode, $pidInt);
                    if ($fileBlob !== null) {
                        $stmtUpd->send_long_data(3, $fileBlob);
                    }
                    $stmtUpd->execute();
                } else {
                    $stmtIns->bind_param("iisdssbi", $empcode, $pidInt, $jenisPay, $numericAmount, $remarkForThis, $path_file, $nullBlob, $isRapel);
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
        if ($id <= 0) send_response(1, 'ID payhead tidak valid.');
        $stmt = $conn->prepare("SELECT id, nama_payhead, jenis FROM payheads WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows) {
            $ph = $res->fetch_assoc();
            $ph['jenis_payhead_idn'] = translateJenis($ph['jenis']);
            add_audit_log($conn, $_SESSION['nip'] ?? '', 'GetPayheadById', "Payhead ID=$id");
            send_response(0, $ph);
        }
        send_response(1, 'Payhead tidak ditemukan.');
    }
}