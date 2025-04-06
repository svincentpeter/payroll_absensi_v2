<?php
// File: /payroll_absensi_v2/sdm/employees.php

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();

generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

require_once __DIR__ . '/../koneksi.php';
authorize(['M:SDM', 'M:Superadmin'], '/payroll_absensi_v2/login.php');
$jenjangList = getOrderedJenjang();

/*
 |---------------------------------------------------------------------
 | Bagian: Parameter Bulan/Tahun (untuk payroll)
 |---------------------------------------------------------------------
*/
$selectedMonth = isset($_GET['filterMonth']) ? intval($_GET['filterMonth']) : date('n');
$selectedYear  = isset($_GET['filterYear'])  ? intval($_GET['filterYear'])  : date('Y');

/* =========================
   FUNGSI-FUNGSI SERVER SIDE
   ========================= */

/**
 * Proses payroll untuk anggota tertentu.
 */
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

    // 4) Hitung gaji pokok total + gaji bersih (sementara)
    $gajiPokok  = $gajiPokokEmployee + $salaryIndexBase;
    $gajiBersih = $gajiPokok + $totalEarnings - $totalDeductions;

    // 5) Insert payroll => status draft
    $tglPayroll    = date('Y-m-d H:i:s');
    $catatan       = '';
    $statusPayroll = 'draft';

    $stmtPayroll = $conn->prepare("
        INSERT INTO payroll
            (id_anggota, bulan, tahun, tgl_payroll,
             gaji_pokok, total_pendapatan, total_potongan,
             gaji_bersih, no_rekening, catatan, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtPayroll->bind_param(
        "iiisddddsss",
        $id_anggota,
        $bulan,
        $tahun,
        $tglPayroll,
        $gajiPokok,
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
    //    (kecuali is_rapel = 1, karena rapel belum dihitung di sini)
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
    //    (opsional, sesuai kebutuhan)
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

/**
 * Memeriksa apakah semua anggota sudah final payroll untuk bulan/tahun tertentu.
 */
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

/**
 * Mengambil rekap absensi untuk anggota pada periode tertentu.
 */
function ViewRekapAbsensi($conn)
{
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id_anggota = intval($_POST['id'] ?? 0);
    $bulan      = intval($_POST['selectedMonth'] ?? 0);
    $tahun      = intval($_POST['selectedYear']  ?? 0);

    $stmt = $conn->prepare("SELECT * FROM rekap_absensi
                            WHERE id_anggota = ? AND bulan = ? AND tahun = ?
                            LIMIT 1");
    $stmt->bind_param("iii", $id_anggota, $bulan, $tahun);
    if (!$stmt->execute()) {
        send_response(1, 'Gagal mengambil rekap absensi.');
    }
    $res = $stmt->get_result();
    // [Audit log baru]
    $user_nip = $_SESSION['nip'] ?? '';
    add_audit_log($conn, $user_nip, 'ViewRekapAbsensi', "id_anggota=$id_anggota, bulan=$bulan, tahun=$tahun");

    if ($res->num_rows > 0) {
        $rekap = $res->fetch_assoc();
        send_response(0, $rekap);
    } else {
        send_response(0, [
            'id'                     => 0,
            'id_anggota'            => $id_anggota,
            'bulan'                 => $bulan,
            'tahun'                 => $tahun,
            'total_hadir'           => 0,
            'total_izin'            => 0,
            'total_cuti'            => 0,
            'total_tanpa_keterangan' => 0,
            'total_sakit'           => 0
        ]);
    }
}

/**
 * Mengedit atau menyimpan rekap absensi untuk anggota.
 */
function EditRekapAbsensi($conn)
{
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id_anggota              = intval($_POST['id_anggota'] ?? 0);
    $bulan                   = intval($_POST['bulan'] ?? 0);
    $tahun                   = intval($_POST['tahun'] ?? 0);
    $total_hadir             = intval($_POST['total_hadir'] ?? 0);
    $total_izin              = intval($_POST['total_izin']  ?? 0);
    $total_cuti              = intval($_POST['total_cuti']  ?? 0);
    $total_tanpa_keterangan  = intval($_POST['total_tanpa_keterangan'] ?? 0);
    $total_sakit             = intval($_POST['total_sakit'] ?? 0);

    $stmtCek = $conn->prepare("SELECT id FROM rekap_absensi
                               WHERE id_anggota = ? AND bulan = ? AND tahun = ?
                               LIMIT 1");
    $stmtCek->bind_param("iii", $id_anggota, $bulan, $tahun);
    if (!$stmtCek->execute()) {
        send_response(1, 'Gagal mengecek rekap absensi.');
    }
    $resCek = $stmtCek->get_result();
    // [Audit log baru]
    $user_nip = $_SESSION['nip'] ?? '';
    $detail   = "EditRekapAbsensi for ID=$id_anggota, bulan=$bulan, tahun=$tahun";
    add_audit_log($conn, $user_nip, 'EditRekapAbsensi', $detail);

    if ($resCek->num_rows > 0) {
        $row = $resCek->fetch_assoc();
        $idRekap = $row['id'];
        $stmtU = $conn->prepare("UPDATE rekap_absensi
                                 SET total_hadir = ?, total_izin = ?,
                                     total_cuti = ?, total_tanpa_keterangan = ?,
                                     total_sakit = ?
                                 WHERE id = ?");
        $stmtU->bind_param(
            "iiiiii",
            $total_hadir,
            $total_izin,
            $total_cuti,
            $total_tanpa_keterangan,
            $total_sakit,
            $idRekap
        );
        if ($stmtU->execute()) {
            send_response(0, 'Rekap absensi berhasil diperbarui.');
        } else {
            send_response(1, 'Gagal memperbarui rekap absensi.');
        }
        $stmtU->close();
    } else {
        $stmtI = $conn->prepare("INSERT INTO rekap_absensi
            (id_anggota, bulan, tahun, total_hadir, total_izin,
             total_cuti, total_tanpa_keterangan, total_sakit)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtI->bind_param(
            "iiiiiiii",
            $id_anggota,
            $bulan,
            $tahun,
            $total_hadir,
            $total_izin,
            $total_cuti,
            $total_tanpa_keterangan,
            $total_sakit
        );
        if ($stmtI->execute()) {
            send_response(0, 'Rekap absensi berhasil disimpan.');
        } else {
            send_response(1, 'Gagal menyimpan rekap absensi.');
        }
        $stmtI->close();
    }
}

/**
 * Mengambil semua data payheads.
 */
function GetAllPayheads($conn)
{
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $sql = "SELECT id, nama_payhead, jenis, nominal
            FROM payheads
            ORDER BY nama_payhead ASC";
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

/**
 * Menyimpan/memperbarui penetapan payheads ke employee (dipanggil dari payroll_page).
 */
function AssignPayheadsToEmployee($conn)
{
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $empcode     = intval($_POST['empcode'] ?? 0);
    $payheads    = isset($_POST['payheads']) ? json_decode($_POST['payheads'], true) : [];
    $pay_amounts = isset($_POST['pay_amounts']) ? json_decode($_POST['pay_amounts'], true) : [];
    $remarksArr  = $_POST['remarks'] ?? [];
    $rapels      = isset($_POST['rapels']) ? json_decode($_POST['rapels'], true) : [];

    // Audit log
    $user_nip   = $_SESSION['nip'] ?? '';
    $detailsLog = "AssignPayheadsToEmployee: empcode=$empcode, total payheads=" . count($payheads);
    add_audit_log($conn, $user_nip, 'AssignPayheadsToEmployee', $detailsLog);

    // Ambil nilai bulan & tahun (untuk data rapel)
    $selectedMonth = isset($_POST['selectedMonth']) ? intval($_POST['selectedMonth']) : date('n');
    $selectedYear  = isset($_POST['selectedYear'])  ? intval($_POST['selectedYear'])  : date('Y');

    if ($empcode <= 0) {
        send_response(1, 'ID anggota tidak valid.');
    }
    if (empty($payheads)) {
        send_response(1, 'Tidak ada payheads yang dipilih.');
    }

    // Ambil data payheads lama
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

    // Ambil NIP (untuk penamaan file)
    $stmtNip = $conn->prepare("SELECT nip FROM anggota_sekolah WHERE id=? LIMIT 1");
    $stmtNip->bind_param("i", $empcode);
    $stmtNip->execute();
    $resNip = $stmtNip->get_result();
    if ($resNip->num_rows === 0) {
        send_response(1, "Anggota dengan ID $empcode tidak ditemukan.");
    }
    $rowNip = $resNip->fetch_assoc();
    $nip    = $rowNip['nip'];
    $stmtNip->close();

    // Ambil nilai bulan untuk nama file (opsional)
    $bulanVal = isset($_POST['bulanVal']) ? intval($_POST['bulanVal']) : date('n');

    // Siapkan statement untuk cek master payheads
    $stmtGet = $conn->prepare("SELECT jenis, nama_payhead, nominal FROM payheads WHERE id = ? LIMIT 1");

    // Siapkan statement untuk INSERT dan UPDATE (hanya sekali)
    $sqlInsert = "INSERT INTO employee_payheads
        (id_anggota, id_payhead, jenis, amount, status, remarks, support_doc_path, upload_file_blob, is_rapel)
        VALUES (?, ?, ?, ?, 'draft', ?, ?, ?, ?)";
    $stmtIns = $conn->prepare($sqlInsert);

    $sqlUpdate = "UPDATE employee_payheads
        SET amount = ?, remarks = ?, support_doc_path = ?, upload_file_blob = ?, is_rapel = ?
        WHERE id_anggota = ? AND id_payhead = ?";
    $stmtUpd = $conn->prepare($sqlUpdate);

    $conn->begin_transaction();
    try {
        // 1. Hapus payhead lama yang tidak lagi dipilih
        foreach ($oldPayheads as $oldPid => $oldRow) {
            if (!in_array($oldPid, $payheads)) {
                $stmtDel = $conn->prepare("DELETE FROM employee_payheads WHERE id_anggota = ? AND id_payhead = ?");
                $stmtDel->bind_param("ii", $empcode, $oldPid);
                $stmtDel->execute();
                $stmtDel->close();
            }
        }

        // 2. Loop setiap payhead yang dipilih user
        foreach ($payheads as $pid) {
            $pidInt = intval($pid);
            $rawAmount = $pay_amounts[$pidInt] ?? '0';
            $numericAmount = floatval(str_replace(['.', ','], ['', '.'], $rawAmount));
            $remarkForThis = $remarksArr[$pidInt] ?? '';
            $isRapel = isset($rapels[$pidInt]) ? intval($rapels[$pidInt]) : 0;

            // Data rapel tidak tersimpan di tabel, sehingga tidak digunakan pada query INSERT/UPDATE.
            // Namun, variabel-variabel ini dapat digunakan untuk keperluan lain (misalnya file upload) jika diperlukan.
            if ($isRapel) {
                $rapelStartMonth = $selectedMonth;
                $rapelStartYear  = $selectedYear;
                $rapelMonthlyAmt = $numericAmount;
            } else {
                $rapelStartMonth = null;
                $rapelStartYear  = null;
                $rapelMonthlyAmt = 0.00;
            }

            $path_file = '';
            $fileBlob  = null;
            $nullBlob  = null; // untuk parameter BLOB

            // Cek data master payheads
            $stmtGet->bind_param("i", $pidInt);
            $stmtGet->execute();
            $resG = $stmtGet->get_result();
            if ($resG->num_rows === 0) {
                throw new Exception("Payhead ID $pidInt tidak ditemukan di master payheads.");
            }
            $rowG = $resG->fetch_assoc();
            $jenisPay = $rowG['jenis'];

            // Jika payhead sudah pernah ada, ambil data lama
            $alreadyExists = isset($oldPayheads[$pidInt]);
            if ($alreadyExists) {
                $path_file = $oldPayheads[$pidInt]['support_doc_path'];
                $fileBlob  = $oldPayheads[$pidInt]['upload_file_blob'];
            }

            // Proses file upload (jika ada)
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
                    $folderUpload = __DIR__ . '/../uploads/payhead_support/';
                    if (!is_dir($folderUpload)) {
                        mkdir($folderUpload, 0755, true);
                    }
                    $namaBersih = preg_replace('/[^A-Za-z0-9_\- ]+/', '', $rowG['nama_payhead']);
                    $namaBersih = str_replace(' ', '_', trim($namaBersih));
                    $newName = $nip . '_' . $bulanVal . '_' . $namaBersih . '.' . $ext;
                    $destPath = $folderUpload . $newName;
                    if (!move_uploaded_file($tmpName, $destPath)) {
                        throw new Exception("Gagal upload file payhead $pidInt.");
                    }
                    $path_file = '/payroll_absensi_v2/uploads/payhead_support/' . $newName;
                    $fileBlob = file_get_contents($destPath);
                }
            }

            // 3. Jika payhead sudah ada, UPDATE; jika belum, INSERT
            if ($alreadyExists) {
                // Bind parameter dan eksekusi UPDATE
                $stmtUpd->bind_param("dssbiii", $numericAmount, $remarkForThis, $path_file, $nullBlob, $isRapel, $empcode, $pidInt);
                if ($fileBlob !== null) {
                    $stmtUpd->send_long_data(3, $fileBlob);
                }
                $stmtUpd->execute();
            } else {
                // Bind parameter dan eksekusi INSERT
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
        send_response(0, 'Payheads berhasil disimpan/diupdate tanpa menghapus file lama yang masih dipakai.');
    } catch (Exception $ex) {
        $conn->rollback();
        send_response(1, 'Gagal menugaskan payheads: ' . $ex->getMessage());
    }
}


/**
 * Memuat data employees (grid card) + status payroll
 */
function LoadingEmployees($conn)
{
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = sanitize_input($_POST['search'] ?? '');
    $jenjang = sanitize_input($_POST['jenjang'] ?? '');
    $role   = sanitize_input($_POST['role'] ?? '');
    $selectedMonth = intval($_POST['selectedMonth'] ?? date('n'));
    $selectedYear  = intval($_POST['selectedYear']  ?? date('Y'));

    // Subquery payroll status
    $subqueryPayrollStatus = "(
        SELECT p.status
          FROM payroll p
         WHERE p.id_anggota = a.id
           AND p.bulan = $selectedMonth
           AND p.tahun = $selectedYear
      ORDER BY FIELD(p.status, 'final','revisi','draft')
         LIMIT 1
     ) AS payroll_status";

    // Subquery rapel
    $subqueryRapel = "(SELECT COUNT(*)
                         FROM employee_payheads ep
                        WHERE ep.id_anggota = a.id
                          AND ep.is_rapel = 1
                      ) AS has_rapel";

    // Hitung total data
    $sqlTotal = "SELECT COUNT(*) AS total FROM anggota_sekolah";
    $resTotal = $conn->query($sqlTotal);
    $rowTotal = $resTotal->fetch_assoc();
    $recordsTotal = intval($rowTotal['total']);

    // Query utama
    $sql = "SELECT a.id, a.uid, a.nip, a.nama, a.jenjang, a.role,
                   a.job_title, a.status_kerja, a.masa_kerja_tahun,
                   a.masa_kerja_bulan, a.gaji_pokok, a.no_rekening,
                   a.email, si.level AS salary_index_level,
                   si.base_salary AS salary_index_base,
                   $subqueryPayrollStatus,
                   $subqueryRapel
              FROM anggota_sekolah a
         LEFT JOIN salary_indices si ON a.salary_index_id = si.id
             WHERE 1=1";

    // Filter jenjang
    if (!empty($jenjang)) {
        $sql .= " AND a.jenjang = '" . $conn->real_escape_string($jenjang) . "'";
    }
    // Filter role
    if (!empty($role)) {
        $sql .= " AND a.role = '" . $conn->real_escape_string($role) . "'";
    }
    // Filter search
    if (!empty($search)) {
        $s = $conn->real_escape_string($search);
        $sql .= " AND (
            a.id LIKE '%$s%' OR a.uid LIKE '%$s%' OR a.nip LIKE '%$s%' OR a.nama LIKE '%$s%'
            OR a.jenjang LIKE '%$s%' OR a.role LIKE '%$s%' OR a.job_title LIKE '%$s%'
            OR a.status_kerja LIKE '%$s%' OR a.no_rekening LIKE '%$s%' OR a.email LIKE '%$s%'
        )";
    }

    $sql .= " ORDER BY a.id DESC LIMIT $start, $length";

    $res = $conn->query($sql);
    if (!$res) {
        send_response(1, 'Gagal query data employees: ' . $conn->error);
    }

    $data = [];
    while ($row = $res->fetch_assoc()) {
        $hasRapel  = (intval($row['has_rapel']) > 0);
        $masaKerja = ($row['masa_kerja_tahun'] > 0 ? $row['masa_kerja_tahun'] . ' Thn ' : '')
            . ($row['masa_kerja_bulan'] > 0 ? $row['masa_kerja_bulan'] . ' Bln' : '');
        $masaKerja = trim($masaKerja) ?: '-';
        $gajiPokokFmt = number_format($row['gaji_pokok'], 0, ',', '.');

        $statusPayroll = 'Belum Diproses';
        if (!empty($row['payroll_status'])) {
            if ($row['payroll_status'] === 'final') {
                $statusPayroll = 'Final';
            } elseif ($row['payroll_status'] === 'revisi') {
                $statusPayroll = 'Revisi';
            } elseif ($row['payroll_status'] === 'draft') {
                $statusPayroll = 'Draft';
            }
        }

        $data[] = [
            'id'          => $row['id'],
            'uid'         => $row['uid'],
            'nip'         => $row['nip'],
            'nama'        => $row['nama'],
            'jenjang'     => $row['jenjang'],
            'role'        => $row['role'],
            'job_title'   => $row['job_title'],
            'status_kerja' => $row['status_kerja'],
            'masa_kerja'  => $masaKerja,
            'gaji_pokok'  => $gajiPokokFmt,
            'no_rekening' => $row['no_rekening'],
            'email'       => $row['email'],
            'salary_index_level' => $row['salary_index_level'] ?: '-',
            'salary_index_base'  => floatval($row['salary_index_base'] ?: 0),
            'payroll_status'     => $statusPayroll,
            'has_rapel'          => $hasRapel,
            'foto_profil'        => getProfilePhotoUrl($row['nama'], $row['jenjang'], $row['role'], $row['id'])
        ];
    }

    // [Audit log baru] — mencatat kapan user memuat data employees
    $user_nip = $_SESSION['nip'] ?? '';
    add_audit_log($conn, $user_nip, 'LoadingEmployees', "start=$start, length=$length, filter role=$role, jenjang=$jenjang, search=$search");

    echo json_encode([
        'recordsTotal' => $recordsTotal,
        'data'         => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Mengedit data anggota (khusus update no_rekening).
 */
function EditEmployee($conn)
{
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id          = intval($_POST['id'] ?? 0);
    $no_rekening = trim(sanitize_input($_POST['no_rekening'] ?? ''));
    if ($id <= 0 || empty($no_rekening)) {
        send_response(1, 'ID & No Rekening wajib diisi.');
    }
    $stmtU = $conn->prepare("UPDATE anggota_sekolah SET no_rekening = ? WHERE id = ?");
    if (!$stmtU) {
        send_response(1, 'Prepare failed EditEmployee: ' . $conn->error);
    }
    $stmtU->bind_param("si", $no_rekening, $id);
    if ($stmtU->execute()) {
        $stmtU->close();
        updateSalaryIndexForUser($conn, $id);

        // [Audit log baru]
        $user_nip = $_SESSION['nip'] ?? '';
        $detail   = "EditEmployee: ID=$id, Update no_rekening=$no_rekening";
        add_audit_log($conn, $user_nip, 'EditEmployee', $detail);

        send_response(0, 'No Rekening anggota berhasil diperbarui.');
    } else {
        send_response(1, 'Gagal memperbarui No Rekening: ' . $stmtU->error);
    }
}

/**
 * Mengambil detail lengkap data anggota, termasuk payheads dan perhitungan payroll.
 */
function ViewEmployeeDetail($conn)
{
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        send_response(1, 'ID anggota tidak valid.');
    }
    $selectedMonth = isset($_POST['selectedMonth']) ? intval($_POST['selectedMonth']) : date('n');
    $selectedYear  = isset($_POST['selectedYear']) ? intval($_POST['selectedYear']) : date('Y');
    
    // Ambil parameter tambahan untuk include rapel (default 0 = tidak include)
    $includeRapel = isset($_POST['includeRapel']) ? intval($_POST['includeRapel']) : 0;

    $stmt = $conn->prepare("
        SELECT a.*, si.level AS salary_index_level, si.base_salary AS salary_index_base,
        (SELECT p.status FROM payroll p WHERE p.id_anggota = a.id AND p.bulan = ? AND p.tahun = ? ORDER BY p.tgl_payroll DESC LIMIT 1) AS payroll_status
        FROM anggota_sekolah a
        LEFT JOIN salary_indices si ON a.salary_index_id = si.id
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("iii", $selectedMonth, $selectedYear, $id);
    if (!$stmt->execute()) {
        send_response(1, 'Execute failed (ViewEmployeeDetail): ' . $stmt->error);
    }
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $emp = $res->fetch_assoc();
        $stmt->close();

        $masaKerja = ($emp['masa_kerja_tahun'] > 0 ? $emp['masa_kerja_tahun'] . ' Tahun ' : '')
            . ($emp['masa_kerja_bulan'] > 0 ? $emp['masa_kerja_bulan'] . ' Bulan' : '');
        $masaKerja = trim($masaKerja) ?: '-';

        $gajiPokokVal  = floatval($emp['gaji_pokok']);
        $levelIndexVal = floatval($emp['salary_index_base']);
        updateSalaryIndexForUser($conn, $id);

        // Ambil data payheads
        $stmtPH = $conn->prepare("
            SELECT ep.id_payhead, ph.nama_payhead, ph.jenis AS jenis_payhead,
                   ep.amount, ep.support_doc_path, ep.is_rapel, ep.remarks
              FROM employee_payheads ep
              JOIN payheads ph ON ep.id_payhead = ph.id
             WHERE ep.id_anggota = ?
        ");
        if (!$stmtPH) {
            send_response(1, 'Prepare failed (payheads): ' . $conn->error);
        }
        $stmtPH->bind_param("i", $id);
        if (!$stmtPH->execute()) {
            send_response(1, 'Execute failed (payheads): ' . $stmtPH->error);
        }
        $resPH = $stmtPH->get_result();
        $assigned = [];
        $totalPendapatan = 0;
        $totalPotongan   = 0;
        while ($rw = $resPH->fetch_assoc()) {
            // Jika includeRapel = 0 (default untuk view detail), lewati data rapel
            if (!$includeRapel && intval($rw['is_rapel']) === 1) {
                continue;
            }
            $assigned[] = [
                'id_payhead'        => $rw['id_payhead'],
                'nama_payhead'      => $rw['nama_payhead'],
                'jenis_payhead'     => $rw['jenis_payhead'],
                'jenis_payhead_idn' => translateJenis($rw['jenis_payhead']),
                'amount'            => $rw['amount'],
                'support_doc_path'  => $rw['support_doc_path'],
                'is_rapel'          => $rw['is_rapel']
            ];
            if ($rw['jenis_payhead'] === 'earnings') {
                $totalPendapatan += floatval($rw['amount']);
            } else {
                $totalPotongan += floatval($rw['amount']);
            }
        }
        $stmtPH->close();
        $gajiBersihVal = $gajiPokokVal + $levelIndexVal + $totalPendapatan - $totalPotongan;

        // Audit log
        $user_nip   = $_SESSION['nip'] ?? '';
        $detailsLog = "Melihat detail anggota ID $id (oleh $user_nip).";
        add_audit_log($conn, $user_nip, 'ViewEmployeeDetail', $detailsLog);

        send_response(0, [
            'id'                 => $emp['id'],
            'uid'                => $emp['uid'],
            'nip'                => $emp['nip'],
            'nama'               => $emp['nama'],
            'jenjang'            => $emp['jenjang'],
            'job_title'          => $emp['job_title'],
            'role'               => $emp['role'],
            'status_kerja'       => $emp['status_kerja'],
            'masa_kerja'         => $masaKerja,
            'gaji_pokok_val'     => $gajiPokokVal,
            'gaji_pokok'         => 'Rp ' . number_format($gajiPokokVal, 0, ',', '.'),
            'no_rekening'        => $emp['no_rekening'],
            'email'              => $emp['email'],
            'jenis_kelamin'      => $emp['jenis_kelamin'],
            'agama'              => $emp['agama'],
            'masa_kerja_tahun'   => $emp['masa_kerja_tahun'],
            'masa_kerja_bulan'   => $emp['masa_kerja_bulan'],
            'payheads'           => $assigned, // hanya payheads non-rapel jika includeRapel=0
            'total_pendapatan'   => $totalPendapatan,
            'total_potongan'     => $totalPotongan,
            'salary_index_level' => $emp['salary_index_level'] ?: '-',
            'salary_index_base'  => $levelIndexVal,
            'gaji_bersih'        => $gajiBersihVal,
            'payroll_status'     => $emp['payroll_status']
        ]);
    } else {
        $stmt->close();
        send_response(1, 'Anggota tidak ditemukan.');
    }
}

/**
 * Mengambil data payhead berdasarkan ID.
 */
function GetPayheadById($conn)
{
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        send_response(1, 'ID payhead tidak valid.');
    }
    $stmt = $conn->prepare("SELECT id, nama_payhead, jenis AS jenis_payhead
                            FROM payheads
                            WHERE id = ? LIMIT 1");
    if (!$stmt) {
        send_response(1, 'Prepare failed GetPayheadById: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        send_response(1, 'Execute failed GetPayheadById: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $payhead = $res->fetch_assoc();
        $payhead['jenis_payhead_idn'] = translateJenis($payhead['jenis_payhead']);
        // [Audit log baru]
        $user_nip = $_SESSION['nip'] ?? '';
        add_audit_log($conn, $user_nip, 'GetPayheadById', "Payhead ID=$id");
        send_response(0, $payhead);
    } else {
        send_response(1, 'Payhead tidak ditemukan.');
    }
}

/* =========================
   AJAX HANDLER
   ========================= */
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $case = trim($_POST['case'] ?? '');
        switch ($case) {
            case 'LoadingEmployees':
                LoadingEmployees($conn);
                break;
            case 'EditEmployee':
                EditEmployee($conn);
                break;
            case 'ViewEmployeeDetail':
                ViewEmployeeDetail($conn);
                break;
            case 'GetPayheadById':
                GetPayheadById($conn);
                break;
            case 'GetAllPayheads':
                GetAllPayheads($conn);
                break;
            case 'AssignPayheadsToEmployee':
                AssignPayheadsToEmployee($conn);
                break;
            case 'ProcessPayroll':
                ProcessPayroll($conn);
                break;
            case 'CheckPayrollCompletion':
                CheckPayrollCompletion($conn);
                break;
            case 'ViewRekapAbsensi':
                ViewRekapAbsensi($conn);
                break;
            case 'EditRekapAbsensi':
                EditRekapAbsensi($conn);
                break;
            default:
                send_response(1, 'Kasus tidak valid.');
        }
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan (harus POST).');
    }
    exit();
}

// Ambil data "bulan yg sudah final" (dipakai di modal)
$processedMonths = [];
$resTotal = $conn->query("SELECT COUNT(*) as total FROM anggota_sekolah");
$totalAnggota = $resTotal ? intval($resTotal->fetch_assoc()['total']) : 0;
$sqlMon = "
  SELECT `bulan`,
         `tahun`,
         COUNT(DISTINCT `id_anggota`) AS completed
    FROM `payroll`
   WHERE `status` = 'final'
GROUP BY `bulan`, `tahun`
";

$resMon = $conn->query($sqlMon);
if ($resMon) {
    while ($rm = $resMon->fetch_assoc()) {
        if (intval($rm['completed']) === $totalAnggota) {
            $processedMonths[] = [
                'bulan' => intval($rm['bulan']),
                'tahun' => intval($rm['tahun'])
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Manajemen Anggota - Payroll System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body,
        .text-gray-800 {
            color: #000 !important;
        }

        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }

        #employeeCards {
            margin-top: 20px;
        }

        #employeeCards .col {
            display: flex;
        }

        #employeeCards .card {
            flex: 1;
        }

        .employee-photo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 10px;
        }

        .badge-status {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .badge-status.final {
            background-color: #28a745;
            color: #fff;
        }

        .badge-status.draft {
            background-color: #17a2b8;
            color: #fff;
        }

        .badge-status.revisi {
            background-color: #ffc107;
            color: #000;
        }

        .badge-status.default {
            background-color: #6c757d;
            color: #fff;
        }

        .spinner-border {
            display: none;
            margin-left: 5px;
        }

        #loadingSpinner {
            display: none;
            position: fixed;
            z-index: 9999;
            height: 100px;
            width: 100px;
            margin: auto;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
        }

        .modal-dialog-scrollable {
            max-height: 90vh;
        }

        .processed-month {
            background: #343a40 !important;
            color: #fff !important;
            pointer-events: none;
            border: 1px solid #343a40;
        }
    </style>
    <script>
        const CSRF_TOKEN = '<?= htmlspecialchars($csrf_token); ?>';
    </script>
</head>

<body id="page-top">
    <div id="wrapper">
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include __DIR__ . '/../navbar.php'; ?>
                <?php include __DIR__ . '/../breadcrumb.php'; ?>
                <div class="container-fluid">
                    <div id="selectedMonthDisplay" class="mb-3">
                        <div class="card mb-3">
                            <div class="card-body d-flex align-items-center">
                                <i class="bi bi-calendar3 me-2"></i>
                                <span class="fw-bold">
                                    Payroll Bulan: <?= date('F', mktime(0, 0, 0, $selectedMonth, 1)) . ' ' . $selectedYear; ?>
                                </span>
                                <button id="btnChangeCalendar" class="btn btn-link ms-auto">
                                    <i class="bi bi-pencil-square"></i> Ganti Kalender
                                </button>
                            </div>
                        </div>
                    </div>
                    <h1 class="h3 mb-4 text-gray-800"><i class="bi bi-people-fill"></i> Payroll Anggota</h1>
                    <div id="alert-placeholder"></div>
                    <!-- Filter Anggota -->
                    <div class="card mb-4 shadow">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-white">
                                <i class="bi bi-filter-square-fill"></i> Filter Anggota
                            </h6>
                        </div>
                        <div class="card-body" style="background-color: #f8f9fa;">
                            <form id="filterForm" method="GET" class="row gy-2 gx-3 align-items-center">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                <!-- Jenjang Pendidikan -->
                                <div class="col-auto">
                                    <label for="filterJenjang" class="form-label mb-0"><strong>Jenjang Pendidikan:</strong></label>
                                    <select class="form-control" id="filterJenjang" name="jenjang">
                                        <option value="">Semua Jenjang</option>
                                        <?php
                                        // Ambil daftar jenjang yang telah didefinisikan di helper
                                        $jenjangList = getOrderedJenjang();
                                        foreach ($jenjangList as $jenjang) {
                                            echo '<option value="' . htmlspecialchars($jenjang) . '">' . htmlspecialchars($jenjang) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <!-- Role -->
                                <div class="col-auto">
                                    <label for="filterRole" class="form-label mb-0"><strong>Role:</strong></label>
                                    <select class="form-control" id="filterRole" name="role">
                                        <option value="">Semua Role</option>
                                        <?php
                                        $stmtRole = $conn->prepare("
                        SELECT DISTINCT role
                        FROM anggota_sekolah
                        WHERE role IS NOT NULL AND role != ''
                        ORDER BY role ASC
                    ");
                                        if ($stmtRole) {
                                            $stmtRole->execute();
                                            $resRole = $stmtRole->get_result();
                                            while ($row = $resRole->fetch_assoc()) {
                                                echo '<option value="' . htmlspecialchars($row['role']) . '">'
                                                    . htmlspecialchars($row['role'])
                                                    . '</option>';
                                            }
                                            $stmtRole->close();
                                        } else {
                                            echo '<option value="">Tidak ada data</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <!-- Pencarian -->
                                <div class="col-auto">
                                    <label for="filterSearch" class="form-label mb-0"><strong>Pencarian:</strong></label>
                                    <input type="text" class="form-control" id="filterSearch" name="search" placeholder="Cari nama / nip...">
                                </div>
                                <!-- Tombol -->
                                <div class="col-auto d-flex align-items-end">
                                    <button type="button" id="btnApplyFilter" class="btn btn-primary me-2">
                                        <i class="fas fa-filter"></i> Terapkan Filter
                                    </button>
                                    <button type="button" id="btnResetFilter" class="btn btn-secondary">
                                        <i class="fas fa-undo"></i> Reset Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- End Filter Anggota -->

                    <!-- Grid Card Container -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex align-items-center">
                            <h6 class="m-0 fw-bold text-white"><i class="fas fa-clipboard-list"></i> Daftar Anggota Sekolah</h6>
                            <div class="spinner-border text-light ms-auto" role="status" id="loadingSpinner">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="employeeCards" class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 g-3"></div>
                            <!-- Pagination manual -->
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center" id="paginationContainer"></ul>
                            </nav>
                        </div>
                    </div>
                </div><!-- /.container-fluid -->
            </div><!-- /#content -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?= date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div><!-- /#content-wrapper -->
    </div><!-- /#wrapper -->

    <!-- MODAL: Edit anggota -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel" aria-hidden="true">
        <!-- Gunakan modal-xl untuk ukuran ekstra besar, plus modal-dialog-centered agar modal tampil di tengah layar -->
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <form id="editEmployeeForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editEmployeeModalLabel">
                            <i class="bi bi-pencil-square"></i> Update No Rekening Anggota
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Hidden inputs -->
                        <input type="hidden" name="case" value="EditEmployee">
                        <input type="hidden" name="id" id="editEmployeeId">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">

                        <div class="container-fluid">
                            <!-- Row 1 -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="editNip" class="form-label">NIP</label>
                                    <input type="text" name="nip" id="editNip" class="form-control" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label for="editNama" class="form-label">Nama</label>
                                    <input type="text" name="nama" id="editNama" class="form-control" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label for="editJenjang" class="form-label">Jenjang Pendidikan</label>
                                    <input type="text" name="jenjang" id="editJenjang" class="form-control" readonly>
                                </div>
                            </div>
                            <!-- Row 2 -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="editJobTitle" class="form-label">Job Title</label>
                                    <input type="text" name="job_title" id="editJobTitle" class="form-control" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label for="editStatusKerja" class="form-label">Status Kerja</label>
                                    <select name="status_kerja" id="editStatusKerja" class="form-control" disabled>
                                        <option value="">---Pilih Status---</option>
                                        <option value="Tetap">Tetap</option>
                                        <option value="Kontrak">Kontrak</option>
                                        <option value="Paruh Waktu">Paruh Waktu</option>
                                        <option value="Magang">Magang</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="editNoRekening" class="form-label">
                                        No Rekening <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="no_rekening" id="editNoRekening" class="form-control" required>
                                </div>
                            </div>
                            <!-- Row 3 -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="editEmail" class="form-label">Email</label>
                                    <input type="email" name="email" id="editEmail" class="form-control" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label for="editJenisKelamin" class="form-label">Jenis Kelamin</label>
                                    <select name="jenis_kelamin" id="editJenisKelamin" class="form-control" disabled>
                                        <option value="">---</option>
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="editAgama" class="form-label">Agama</label>
                                    <input type="text" name="agama" id="editAgama" class="form-control" readonly>
                                </div>
                            </div>
                        </div> <!-- /.container-fluid -->
                    </div> <!-- /.modal-body -->

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: View Detail anggota -->
    <div class="modal fade" id="viewDetailModal" tabindex="-1" aria-labelledby="viewDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewDetailModalLabel"><i class="bi bi-eye-fill"></i> Detail anggota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered">
                        <tr>
                            <th>ID</th>
                            <td id="detailId"></td>
                        </tr>
                        <tr>
                            <th>UID</th>
                            <td id="detailUid"></td>
                        </tr>
                        <tr>
                            <th>NIP</th>
                            <td id="detailNip"></td>
                        </tr>
                        <tr>
                            <th>Nama</th>
                            <td id="detailNama"></td>
                        </tr>
                        <tr>
                            <th>Jenjang Pendidikan</th>
                            <td id="detailJenjang"></td>
                        </tr>
                        <tr>
                            <th>Role</th>
                            <td id="detailRole"></td>
                        </tr>
                        <tr>
                            <th>Job Title</th>
                            <td id="detailJobTitle"></td>
                        </tr>
                        <tr>
                            <th>Status Kerja</th>
                            <td id="detailStatusKerja"></td>
                        </tr>
                        <tr>
                            <th>Masa Kerja</th>
                            <td id="detailMasaKerja"></td>
                        </tr>
                        <tr>
                            <th>No Rekening</th>
                            <td id="detailNoRekening"></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td id="detailEmail"></td>
                        </tr>
                        <tr>
                            <th>Jenis Kelamin</th>
                            <td id="detailJenisKelamin"></td>
                        </tr>
                        <tr>
                            <th>Agama</th>
                            <td id="detailAgama"></td>
                        </tr>
                        <tr>
                            <th>Gaji Pokok</th>
                            <td id="detailGajiPokok"></td>
                        </tr>
                        <tr>
                            <th>Nominal Level Indeks</th>
                            <td id="detailSalaryIndexNominal"></td>
                        </tr>
                        <tr>
                            <th>Payheads</th>
                            <td id="detailPayheads"></td>
                        </tr>
                        <tr>
                            <th>Total Pendapatan</th>
                            <td id="detailTotalPendapatan"></td>
                        </tr>
                        <tr>
                            <th>Total Potongan</th>
                            <td id="detailTotalPotongan"></td>
                        </tr>
                        <tr>
                            <th>Gaji Bersih</th>
                            <td id="detailGajiBersih"></td>
                        </tr>
                    </table>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: Review Rekap Absensi -->
    <div class="modal fade" id="rekapReviewModal" tabindex="-1" aria-labelledby="rekapReviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rekapReviewModalLabel"><i class="bi bi-eye"></i> Review Rekap Absensi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="data-display">
                        <p><strong>ID Anggota:</strong> <span id="review_id_anggota"></span></p>
                        <p><strong>Bulan:</strong> <span id="review_bulan"></span></p>
                        <p><strong>Tahun:</strong> <span id="review_tahun"></span></p>
                        <p><strong>Total Hadir:</strong> <span id="review_total_hadir"></span></p>
                        <p><strong>Total Izin:</strong> <span id="review_total_izin"></span></p>
                        <p><strong>Total Cuti:</strong> <span id="review_total_cuti"></span></p>
                        <p><strong>Total Tanpa Keterangan:</strong> <span id="review_total_tanpa_keterangan"></span></p>
                        <p><strong>Total Sakit:</strong> <span id="review_total_sakit"></span></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" id="btnOpenEditRekap">Edit</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: Edit Rekap Absensi -->
    <div class="modal fade" id="rekapAbsensiModal" tabindex="-1" aria-labelledby="rekapAbsensiModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="rekapAbsensiForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="rekapAbsensiModalLabel"><i class="bi bi-calendar-check"></i> Edit Rekap Absensi</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="case" value="EditRekapAbsensi">
                        <input type="hidden" name="id_anggota" id="rekap_id_anggota_edit">
                        <input type="hidden" name="bulan" id="rekap_bulan_edit">
                        <input type="hidden" name="tahun" id="rekap_tahun_edit">
                        <div class="mb-3">
                            <label for="total_hadir_edit" class="form-label">Total Hadir</label>
                            <input type="number" class="form-control" name="total_hadir" id="total_hadir_edit" required>
                        </div>
                        <div class="mb-3">
                            <label for="total_izin_edit" class="form-label">Total Izin</label>
                            <input type="number" class="form-control" name="total_izin" id="total_izin_edit" required>
                        </div>
                        <div class="mb-3">
                            <label for="total_cuti_edit" class="form-label">Total Cuti</label>
                            <input type="number" class="form-control" name="total_cuti" id="total_cuti_edit" required>
                        </div>
                        <div class="mb-3">
                            <label for="total_tanpa_keterangan_edit" class="form-label">Total Tanpa Keterangan</label>
                            <input type="number" class="form-control" name="total_tanpa_keterangan" id="total_tanpa_keterangan_edit" required>
                        </div>
                        <div class="mb-3">
                            <label for="total_sakit_edit" class="form-label">Total Sakit</label>
                            <input type="number" class="form-control" name="total_sakit" id="total_sakit_edit" required>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan Perubahan
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: Select Month (Payroll) -->
    <div class="modal fade" id="SalaryMonthModal" tabindex="-1" aria-labelledby="salaryMonthModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md" style="max-width: 600px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="salaryMonthModalLabel"><i class="fa fa-calendar"></i> Pilih Bulan untuk Payroll</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row text-center">
                        <?php
                        $currentYear  = date('Y');
                        $currentMonth = date('n');
                        $startMonth   = $currentMonth - 2;
                        $startYear    = $currentYear;
                        for ($i = 0; $i < 16; $i++) {
                            $month = $startMonth + $i;
                            $year  = $startYear;
                            if ($month <= 0) {
                                $month += 12;
                                $year  -= 1;
                            } elseif ($month > 12) {
                                $month -= 12;
                                $year  += 1;
                            }
                            $highlightClass = 'bg-light';
                            foreach ($processedMonths as $pm) {
                                if ($pm['bulan'] == $month && $pm['tahun'] == $year) {
                                    $highlightClass = 'processed-month';
                                    break;
                                }
                            }
                            if ($month == $selectedMonth && $year == $selectedYear) {
                                $highlightClass = 'bg-warning text-dark fw-bold';
                            }
                            echo '<div class="col-3 mb-3">';
                            echo '  <div class="p-2 ' . $highlightClass . '" style="border: 1px solid #ddd; border-radius: 5px;">';
                            echo '    <a href="#" class="month-link" data-month-number="' . $month . '" data-month="' . date("F", mktime(0, 0, 0, $month, 1)) . '" data-year="' . $year . '" style="color: inherit; text-decoration: none;">';
                            echo '      ' . strtoupper(date("F", mktime(0, 0, 0, $month, 1))) . '<br>' . $year;
                            echo '    </a>';
                            echo '  </div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/autonumeric@4.8.0/dist/autoNumeric.min.js"></script>

    <script>
        $(document).ready(function() {

            // === SweetAlert2 Toast ===
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });

            function showToast(message, icon = 'success') {
                Toast.fire({
                    icon: icon,
                    title: message
                });
            }

            // === Variabel Pagination Manual ===
            let currentPage = 1;
            let pageSize = 10;

            function loadEmployees(page) {
                currentPage = page;
                let start = (currentPage - 1) * pageSize;
                let length = pageSize;
                $.ajax({
                    url: "employees.php?ajax=1",
                    type: "POST",
                    data: {
                        case: "LoadingEmployees",
                        start: start,
                        length: length,
                        jenjang: $("#filterJenjang").val(),
                        role: $("#filterRole").val(),
                        search: $("#filterSearch").val(),
                        selectedMonth: localStorage.getItem("selectedMonthNumber") || <?= $selectedMonth ?>,
                        selectedYear: localStorage.getItem("selectedYearPayroll") || <?= $selectedYear ?>,
                        csrf_token: "<?= htmlspecialchars($csrf_token); ?>"
                    },
                    dataType: "json",
                    beforeSend: function() {
                        $("#loadingSpinner").show();
                    },
                    success: function(res) {
                        $("#loadingSpinner").hide();
                        if (res.data) {
                            generateCards(res.data);
                            generatePagination(res.recordsTotal);
                        } else {
                            $("#employeeCards").empty();
                            $("#paginationContainer").empty();
                            showToast("Data kosong atau gagal di-load", "warning");
                        }
                    },
                    error: function(xhr, status, error) {
                        $("#loadingSpinner").hide();
                        showToast("Terjadi kesalahan memuat data: " + error, "error");
                    }
                });
            }

            // Dapatkan baseUrl dari PHP
            let baseUrl = "<?= getBaseUrl(); ?>";

            function generateCards(data) {
                let container = $("#employeeCards");
                container.empty();
                data.forEach(function(item) {
                    // tentukan warna & teks rapel
                    let rapelBadge = '';
                    if (item.has_rapel) {
                        rapelBadge = `<span class="badge" style="background-color: red;">Ada</span>`;
                    } else {
                        rapelBadge = `<span class="badge bg-secondary">-</span>`;
                    }

                    let badgeClass = 'default';
                    if (item.payroll_status.toLowerCase() === 'final') badgeClass = 'final';
                    else if (item.payroll_status.toLowerCase() === 'draft') badgeClass = 'draft';
                    else if (item.payroll_status.toLowerCase() === 'revisi') badgeClass = 'revisi';

                    let photoUrl = item.foto_profil && item.foto_profil !== '' ?
                        item.foto_profil :
                        baseUrl + "/assets/img/undraw_profile.svg";

                    let cardHtml = `
          <div class="col">
            <div class="card shadow-sm p-3 h-100 text-center" data-payroll_status="${item.payroll_status}">
              <img src="${photoUrl}" alt="Foto" class="employee-photo mb-2">
              <h6 class="mb-0">${item.nama}</h6>
              <small class="text-muted">NIP: ${item.nip}</small>
              <p class="mt-2 mb-1" style="font-size:0.85rem;">
                Role: ${item.role} | <strong>${item.jenjang}</strong>
              </p>
              <p style="font-size:0.85rem;">
                Status: <span class="badge-status ${badgeClass}">${item.payroll_status}</span>
              </p>
              <p style="font-size:0.85rem;">
                Rapel: ${rapelBadge}
              </p>
              <div class="d-grid gap-2">
                <button class="btn btn-sm btn-primary btnViewDetail" data-id="${item.id}">
                  <i class="bi bi-eye-fill"></i> Detail Data
                </button>
                <button class="btn btn-sm btn-warning btnEdit" data-id="${item.id}">
                  <i class="bi bi-pencil-square"></i> Edit Data
                </button>
                <button class="btn btn-sm btn-info btnAssignPayheads" data-id="${item.id}">
                  <i class="bi bi-cash-stack"></i> Payroll
                </button>
                <button class="btn btn-sm btn-secondary btnRekapAbsensi"
                        data-id="${item.id}"
                        data-role="${item.role}">
                  <i class="bi bi-calendar-check"></i> Rekap Absensi
                </button>
              </div>
            </div>
          </div>
        `;
                    container.append(cardHtml);
                });
            }


            function generatePagination(totalRecords) {
                let totalPages = Math.ceil(totalRecords / pageSize);
                let pagination = $("#paginationContainer");
                pagination.empty();
                for (let i = 1; i <= totalPages; i++) {
                    let li = $("<li>").addClass("page-item").append(
                        $("<a>").addClass("page-link").text(i).attr("href", "#").on("click", function(e) {
                            e.preventDefault();
                            loadEmployees(i);
                        })
                    );
                    if (i === currentPage) li.addClass("active");
                    pagination.append(li);
                }
            }

            loadEmployees(1);

            $("#btnApplyFilter").on("click", function() {
                loadEmployees(1);
            });
            $("#btnResetFilter").on("click", function() {
                $("#filterForm")[0].reset();
                $("#filterSearch").val("");
                loadEmployees(1);
            });

            $(document).on("click", ".btnAssignPayheads", function() {
                let cardStatus = $(this).parents('.card').attr('data-payroll_status');
                if (typeof cardStatus !== 'undefined' && cardStatus.toLowerCase() === 'final') {
                    showToast("Payroll sudah final, tidak dapat mengubah payheads.", "warning");
                    return;
                }
                let id = $(this).data("id");
                let filterMonth = localStorage.getItem("selectedMonthNumber") || <?= $selectedMonth ?>;
                let filterYear = localStorage.getItem("selectedYearPayroll") || <?= $selectedYear ?>;
                window.location.href = 'payroll_page.php?empcode=' + id + '&filterMonth=' + filterMonth + '&filterYear=' + filterYear;
            });

            function calcPotonganAbsensi(role, totalIzin, totalCuti, totalTK, totalSakit) {
                let totalTidakHadir = totalIzin + totalCuti + totalTK + totalSakit;
                let potongan = 0;
                if (role === 'P' || role === 'TK') {
                    const biayaPerHari = 75000;
                    potongan = Math.min(totalTidakHadir, 2) * biayaPerHari;
                } else if (role === 'M') {
                    const biayaPerHariManajerial = 50000;
                    potongan = totalTidakHadir * biayaPerHariManajerial;
                }
                return potongan;
            }

            // Event handler untuk Edit Data
            $(document).on("click", ".btnEdit", function() {
                let id = $(this).data("id");
                $.ajax({
                    url: 'employees.php?ajax=1',
                    type: 'POST',
                    data: {
                        case: 'ViewEmployeeDetail',
                        id: id,
                        csrf_token: '<?= htmlspecialchars($csrf_token); ?>',
                        selectedMonth: localStorage.getItem("selectedMonthNumber"),
                        selectedYear: localStorage.getItem("selectedYearPayroll")
                    },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.code === 0) {
                            var e = resp.result;
                            $('#editEmployeeId').val(e.id);
                            $('#editNip').val(e.nip);
                            $('#editNama').val(e.nama);
                            $('#editJenjang').val(e.jenjang);
                            $('#editJobTitle').val(e.job_title);
                            $('#editStatusKerja').val(e.status_kerja);
                            $('#editNoRekening').val(e.no_rekening);
                            $('#editEmail').val(e.email);
                            $('#editJenisKelamin').val(e.jenis_kelamin);
                            $('#editAgama').val(e.agama);
                            $('#editEmployeeModal').modal('show');
                        } else {
                            showToast(resp.result, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        showToast('Terjadi kesalahan saat memuat data anggota: ' + error, 'error');
                    }
                });
            });

            $('#editEmployeeForm').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                var dataToSend = {
                    case: 'EditEmployee',
                    id: $('#editEmployeeId').val(),
                    no_rekening: $('#editNoRekening').val(),
                    csrf_token: '<?= htmlspecialchars($csrf_token); ?>'
                };
                $.ajax({
                    url: 'employees.php?ajax=1',
                    type: 'POST',
                    data: dataToSend,
                    dataType: 'json',
                    beforeSend: function() {
                        form.find('button[type="submit"]').prop('disabled', true);
                        form.find('.spinner-border').removeClass('d-none');
                    },
                    success: function(resp) {
                        form.find('button[type="submit"]').prop('disabled', false);
                        form.find('.spinner-border').addClass('d-none');
                        if (resp.code === 0) {
                            showToast(resp.result, 'success');
                            loadEmployees(currentPage);
                            $('#editEmployeeModal').modal('hide');
                        } else {
                            showToast(resp.result, 'error');
                        }
                    },
                    error: function() {
                        form.find('button[type="submit"]').prop('disabled', false);
                        form.find('.spinner-border').addClass('d-none');
                        showToast('Terjadi kesalahan saat memperbarui data anggota.', 'error');
                    }
                });
            });

            // Event handler untuk View Detail
            $(document).on("click", ".btnViewDetail", function() {
                let id = $(this).data("id");
                $.ajax({
                    url: 'employees.php?ajax=1',
                    type: 'POST',
                    data: {
                        case: 'ViewEmployeeDetail',
                        id: id,
                        csrf_token: '<?= htmlspecialchars($csrf_token); ?>',
                        selectedMonth: localStorage.getItem("selectedMonthNumber"),
                        selectedYear: localStorage.getItem("selectedYearPayroll")
                    },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.code === 0) {
                            var e = resp.result;
                            $('#detailId').text(e.id);
                            $('#detailUid').text(e.uid);
                            $('#detailNip').text(e.nip);
                            $('#detailNama').text(e.nama);
                            $('#detailJenjang').text(e.jenjang);
                            $('#detailRole').text(e.role);
                            $('#detailJobTitle').text(e.job_title);
                            $('#detailStatusKerja').text(e.status_kerja);
                            $('#detailMasaKerja').text(e.masa_kerja);
                            $('#detailNoRekening').text(e.no_rekening);
                            $('#detailEmail').text(e.email);
                            $('#detailJenisKelamin').text(e.jenis_kelamin === 'L' ? 'Laki-laki' : e.jenis_kelamin === 'P' ? 'Perempuan' : '-');
                            $('#detailAgama').text(e.agama);
                            $('#detailGajiPokok').text(e.gaji_pokok);
                            let baseSalary = parseFloat(e.salary_index_base) || 0;
                            $('#detailSalaryIndexNominal').text('Rp ' + baseSalary.toLocaleString('id-ID', {
                                minimumFractionDigits: 2
                            }));
                            if (e.payheads && e.payheads.length > 0) {
                                var s = '<ul>';
                                e.payheads.forEach(function(ph) {
                                    var nominal = parseFloat(ph.amount).toLocaleString('id-ID', {
                                        minimumFractionDigits: 2
                                    });
                                    var jns = (ph.jenis_payhead === 'earnings') ? 'Pendapatan' : 'Potongan';
                                    var clr = (ph.jenis_payhead === 'earnings') ? 'green' : 'red';
                                    s += `<li style="color:${clr}">${ph.nama_payhead} (${jns}): Rp ${nominal}`;
                                    if (ph.support_doc_path && ph.support_doc_path.trim() !== "") {
                                        s += ` - <a href="download_payhead.php?file=${encodeURIComponent(ph.support_doc_path)}&name=${encodeURIComponent(ph.nama_payhead)}" download="${ph.nama_payhead}" target="_blank">Lihat Dokumen</a>`;
                                    }
                                    s += `</li>`;
                                });
                                s += '</ul>';
                                $('#detailPayheads').html(s);
                            } else {
                                $('#detailPayheads').html('<i>Tidak ada payheads</i>');
                            }
                            $('#detailTotalPendapatan').text('Rp ' + parseFloat(e.total_pendapatan).toLocaleString('id-ID', {
                                minimumFractionDigits: 2
                            }));
                            $('#detailTotalPotongan').text('Rp ' + parseFloat(e.total_potongan).toLocaleString('id-ID', {
                                minimumFractionDigits: 2
                            }));
                            $('#detailGajiBersih').text('Rp ' + parseFloat(e.gaji_bersih).toLocaleString('id-ID', {
                                minimumFractionDigits: 2
                            }));
                            $('#viewDetailModal').modal('show');
                        } else {
                            showToast(resp.result, 'error');
                        }
                    },
                    error: function() {
                        showToast('Terjadi kesalahan saat mengambil detail anggota.', 'error');
                    }
                });
            });

            // Handler untuk rekapan absensi
            $(document).on('click', '.btnRekapAbsensi', function() {
                var id = $(this).data('id');
                var role = $(this).data('role');
                var selectedMonth = localStorage.getItem('selectedMonthNumber') || <?= $selectedMonth ?>;
                var selectedYear = localStorage.getItem('selectedYearPayroll') || <?= $selectedYear ?>;
                $.ajax({
                    url: 'employees.php?ajax=1',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        case: 'ViewRekapAbsensi',
                        id: id,
                        selectedMonth: selectedMonth,
                        selectedYear: selectedYear,
                        csrf_token: CSRF_TOKEN
                    },
                    success: function(resp) {
                        if (resp.code === 0) {
                            var data = resp.result;
                            $("#review_id_anggota").text(data.id_anggota);
                            $("#review_bulan").text(data.bulan);
                            $("#review_tahun").text(data.tahun);
                            $("#review_total_hadir").text(data.total_hadir);
                            $("#review_total_izin").text(data.total_izin);
                            $("#review_total_cuti").text(data.total_cuti);
                            $("#review_total_tanpa_keterangan").text(data.total_tanpa_keterangan);
                            $("#review_total_sakit").text(data.total_sakit);
                            $('#rekap_id_anggota_edit').val(data.id_anggota);
                            $('#rekap_bulan_edit').val(data.bulan);
                            $('#rekap_tahun_edit').val(data.tahun);
                            $('#total_hadir_edit').val(data.total_hadir);
                            $('#total_izin_edit').val(data.total_izin);
                            $('#total_cuti_edit').val(data.total_cuti);
                            $('#total_tanpa_keterangan_edit').val(data.total_tanpa_keterangan);
                            $('#total_sakit_edit').val(data.total_sakit);
                            let potonganAbsensi = calcPotonganAbsensi(role, data.total_izin, data.total_cuti, data.total_tanpa_keterangan, data.total_sakit);
                            window.potonganAbsensiGlobal = potonganAbsensi;
                            $('#rekapReviewModal').modal('show');
                        } else {
                            $("#review_id_anggota, #review_bulan, #review_tahun").text('');
                            $("#review_total_hadir, #review_total_izin, #review_total_cuti, #review_total_tanpa_keterangan, #review_total_sakit").text('0');
                            window.potonganAbsensiGlobal = 0;
                        }
                    },
                    error: function() {
                        $("#review_total_hadir, #review_total_izin, #review_total_cuti, #review_total_tanpa_keterangan, #review_total_sakit").text("0");
                        window.potonganAbsensiGlobal = 0;
                    }
                });
            });

            $('#btnOpenEditRekap').on('click', function() {
                $('#rekapReviewModal').modal('hide');
                $('#rekapAbsensiModal').modal('show');
            });

            $('#rekapAbsensiForm').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                $.ajax({
                    url: 'employees.php?ajax=1',
                    type: 'POST',
                    dataType: 'json',
                    data: form.serialize(),
                    beforeSend: function() {
                        form.find('button[type="submit"]').prop('disabled', true);
                        form.find('.spinner-border').removeClass('d-none');
                    },
                    success: function(resp) {
                        form.find('button[type="submit"]').prop('disabled', false);
                        form.find('.spinner-border').addClass('d-none');
                        if (resp.code === 0) {
                            showToast(resp.result, 'success');
                            $('#rekapAbsensiModal').modal('hide');
                        } else {
                            showToast(resp.result, 'error');
                        }
                    },
                    error: function() {
                        form.find('button[type="submit"]').prop('disabled', false);
                        form.find('.spinner-border').addClass('d-none');
                        showToast('Terjadi kesalahan saat menyimpan rekap absensi.', 'error');
                    }
                });
            });

            function initSelectedMonthDisplay() {
                if (localStorage.getItem('selectedMonthPayroll') && localStorage.getItem('selectedYearPayroll')) {
                    var selMonth = localStorage.getItem('selectedMonthPayroll');
                    var selYear = localStorage.getItem('selectedYearPayroll');
                    $('#selectedMonthDisplay').html(
                        '<div class="card mb-3">' +
                        '<div class="card-body d-flex align-items-center">' +
                        '<i class="bi bi-calendar3 me-2"></i>' +
                        '<span class="fw-bold">Payroll Bulan: ' + selMonth + ' ' + selYear + '</span>' +
                        '<button id="btnChangeCalendar" class="btn btn-link ms-auto">' +
                        '<i class="bi bi-pencil-square"></i> Ganti Kalender' +
                        '</button>' +
                        '</div>' +
                        '</div>'
                    );
                } else {
                    $('#selectedMonthDisplay').html('<h4>Klik di sini untuk memilih bulan payroll</h4>');
                    $('#SalaryMonthModal').modal('show');
                }
            }
            initSelectedMonthDisplay();
            loadEmployees(1);

            $("#btnChangeCalendar").on("click", function() {
                $("#SalaryMonthModal").modal("show");
            });

            $(document).on("click", ".month-link", function(e) {
                e.preventDefault();
                var monthNumber = $(this).data('month-number');
                var monthName = $(this).data('month');
                var year = $(this).data('year');
                if (window.currentEmpId) {
                    var employeeId = window.currentEmpId;
                    var targetUrl = "/payroll_absensi_v2/payroll/keuangan/manage-salary.php";
                    targetUrl += "?id_anggota=" + employeeId;
                    targetUrl += "&bulan=" + encodeURIComponent(monthName);
                    targetUrl += "&tahun=" + encodeURIComponent(year);
                    window.location.href = targetUrl;
                } else {
                    $.ajax({
                        url: 'employees.php?ajax=1',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            case: 'CheckPayrollCompletion',
                            selectedMonth: monthNumber,
                            selectedYear: year,
                            csrf_token: '<?= htmlspecialchars($csrf_token); ?>'
                        },
                        success: function(resp) {
                            if (resp.code === 0) {
                                if (resp.result.complete === false) {
                                    var messages = resp.result.messages;
                                    var messageText = messages.join("<br>");
                                    Swal.fire({
                                        title: 'Perhatian!',
                                        html: messageText + "<br><br>Apakah Anda tetap ingin memilih bulan ini?",
                                        icon: 'warning',
                                        showCancelButton: true,
                                        confirmButtonText: 'Ya, pilih bulan ini',
                                        cancelButtonText: 'Batal'
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            simpanPilihanBulan(monthNumber, monthName, year);
                                        }
                                    });
                                } else {
                                    simpanPilihanBulan(monthNumber, monthName, year);
                                }
                            } else {
                                showToast(resp.result, 'error');
                            }
                        },
                        error: function(xhr, status, error) {
                            showToast('Terjadi kesalahan saat mengecek payroll: ' + error, 'error');
                        }
                    });
                }
            });

            function simpanPilihanBulan(monthNumber, monthName, year) {
                localStorage.setItem('selectedMonthPayroll', monthName);
                localStorage.setItem('selectedMonthNumber', monthNumber);
                localStorage.setItem('selectedYearPayroll', year);
                $('#selectedMonthDisplay').html(
                    '<div class="card mb-3">' +
                    '<div class="card-body d-flex align-items-center">' +
                    '<i class="bi bi-calendar3 me-2"></i>' +
                    '<span class="fw-bold">Payroll Bulan: ' + monthName + ' ' + year + '</span>' +
                    '<button id="btnChangeCalendar" class="btn btn-link ms-auto">' +
                    '<i class="bi bi-pencil-square"></i> Ganti Kalender' +
                    '</button>' +
                    '</div>' +
                    '</div>'
                );
                $('#SalaryMonthModal').modal('hide');
                var newUrl = "employees.php?filterMonth=" + monthNumber + "&filterYear=" + year;
                window.location.href = newUrl;
            }
        });
    </script>
</body>

</html>