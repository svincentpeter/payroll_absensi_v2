<?php
// File: /payroll_absensi_v2/absensi/sdm/employees.php

// =========================
// 1. Pengaturan Awal
// =========================
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();

// Hasilkan CSRF token dan simpan ke variabel
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

// Sertakan koneksi ke database
require_once __DIR__ . '/../../koneksi.php';

// Otorisasi pengguna (hanya role sdm dan superadmin)
authorize(['sdm', 'superadmin'], '/payroll_absensi_v2/login.php');

/**
 * Fungsi ProcessPayroll untuk memproses data payroll dan mengirimkannya ke list payroll.
 */
function ProcessPayroll($conn) {
    // Verifikasi token CSRF
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    // Ambil parameter dari POST
    $id_anggota = isset($_POST['id_anggota']) ? intval($_POST['id_anggota']) : 0;
    $bulan = isset($_POST['selectedMonth']) ? intval($_POST['selectedMonth']) : 0;
    $tahun = isset($_POST['selectedYear']) ? intval($_POST['selectedYear']) : 0;
    if($id_anggota <= 0 || $bulan <= 0 || $tahun <= 0) {
        send_response(1, 'Parameter tidak valid untuk proses payroll.');
    }
    // Update employee_payheads menjadi 'final'
    $stmtFinal = $conn->prepare("UPDATE employee_payheads SET status = 'final' WHERE id_anggota = ? AND status IN ('draft','revisi')");
    $stmtFinal->bind_param("i", $id_anggota);
    if(!$stmtFinal->execute()){
        $error = $stmtFinal->error;
        $stmtFinal->close();
        send_response(1, 'Gagal update status payheads: ' . $error);
    }
    $stmtFinal->close();

    // Hitung total pendapatan (earnings) dan potongan (deductions)
    $sqlSum = "SELECT jenis, SUM(amount) as total FROM employee_payheads WHERE id_anggota = ? AND status = 'final' GROUP BY jenis";
    $stmtSum = $conn->prepare($sqlSum);
    $stmtSum->bind_param("i", $id_anggota);
    if(!$stmtSum->execute()){
        $error = $stmtSum->error;
        $stmtSum->close();
        send_response(1, 'Gagal menghitung total payheads: ' . $error);
    }
    $resultSum = $stmtSum->get_result();
    $totalEarnings = 0;
    $totalDeductions = 0;
    while($row = $resultSum->fetch_assoc()){
        if($row['jenis'] === 'earnings'){
            $totalEarnings = floatval($row['total']);
        } else {
            $totalDeductions = floatval($row['total']);
        }
    }
    $stmtSum->close();

    // Ambil data anggota untuk gaji pokok dan no_rekening
    $stmtEmp = $conn->prepare("SELECT gaji_pokok, salary_index_id, no_rekening FROM anggota_sekolah WHERE id = ? LIMIT 1");
    $stmtEmp->bind_param("i", $id_anggota);
    if(!$stmtEmp->execute()){
        $error = $stmtEmp->error;
        $stmtEmp->close();
        send_response(1, 'Gagal mengambil data anggota: ' . $error);
    }
    $resultEmp = $stmtEmp->get_result();
    if($resultEmp->num_rows == 0){
        $stmtEmp->close();
        send_response(1, 'Anggota tidak ditemukan.');
    }
    $empData = $resultEmp->fetch_assoc();
    $stmtEmp->close();
    $gajiPokokEmployee = floatval($empData['gaji_pokok']);
    $no_rekening = $empData['no_rekening'];
    $salaryIndexBase = 0;
    if(!empty($empData['salary_index_id'])){
        $stmtIndex = $conn->prepare("SELECT base_salary FROM salary_indices WHERE id = ? LIMIT 1");
        $stmtIndex->bind_param("i", $empData['salary_index_id']);
        if($stmtIndex->execute()){
            $resIndex = $stmtIndex->get_result();
            if($resIndex->num_rows > 0){
                $rowIndex = $resIndex->fetch_assoc();
                $salaryIndexBase = floatval($rowIndex['base_salary']);
            }
        }
        $stmtIndex->close();
    }
    $gajiPokok = $gajiPokokEmployee + $salaryIndexBase;
    $gajiBersih = $gajiPokok + $totalEarnings - $totalDeductions;
    $tglPayroll = date('Y-m-d H:i:s');
    $catatan = '';

    // Masukkan data payroll baru ke tabel payroll dengan status draft
    $statusPayroll = 'draft';
    $stmtPayroll = $conn->prepare("INSERT INTO payroll (id_anggota, bulan, tahun, tgl_payroll, gaji_pokok, total_pendapatan, total_potongan, gaji_bersih, no_rekening, catatan, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtPayroll->bind_param("iiisddddsss", $id_anggota, $bulan, $tahun, $tglPayroll, $gajiPokok, $totalEarnings, $totalDeductions, $gajiBersih, $no_rekening, $catatan, $statusPayroll);
    if(!$stmtPayroll->execute()){
        $error = $stmtPayroll->error;
        $stmtPayroll->close();
        send_response(1, 'Gagal insert payroll: ' . $error);
    }
    $stmtPayroll->close();

    // Catat audit log
    $user_nip = $_SESSION['nip'] ?? '';
    $details_log = "Memproses payroll untuk anggota dengan NIP $user_nip, ID anggota: $id_anggota.";
    add_audit_log($conn, $user_nip, 'ProcessPayroll', $details_log);

    send_response(0, 'Payroll berhasil diproses dan masuk ke list payroll dengan status draft.');
}

/**
 * Fungsi CheckPayrollCompletion: Mengecek jumlah anggota yang belum memiliki payroll final.
 */
function CheckPayrollCompletion($conn) {
    $bulan = isset($_POST['selectedMonth']) ? intval($_POST['selectedMonth']) : 0;
    $tahun = isset($_POST['selectedYear']) ? intval($_POST['selectedYear']) : 0;
    if ($bulan <= 0 || $tahun <= 0) {
        send_response(1, 'Parameter bulan/tahun tidak valid.');
    }
    $query = "SELECT jenjang, COUNT(*) as pending FROM anggota_sekolah 
            WHERE id NOT IN (
                SELECT id_anggota FROM payroll WHERE bulan = ? AND tahun = ? AND status = 'final'
            ) GROUP BY jenjang";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $bulan, $tahun);
    if(!$stmt->execute()){
        send_response(1, 'Gagal menghitung anggota yang belum final: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    $messages = [];
    while($row = $result->fetch_assoc()){
        if (intval($row['pending']) > 0) {
            $messages[] = "Terdapat " . $row['pending'] . " anggota dengan jenjang '" . $row['jenjang'] . "' yang belum selesai payroll.";
        }
    }
    $stmt->close();
    if(empty($messages)){
        send_response(0, ['complete' => true, 'messages' => []]);
    } else {
        send_response(0, ['complete' => false, 'messages' => $messages]);
    }
}

/**
 * === HANDLER UNTUK REQUEST AJAX ===
 */
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    error_log("DEBUG: AJAX request received in employees.php");
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verifikasi CSRF token
        verify_csrf_token($_POST['csrf_token'] ?? '');
        
        $case = isset($_POST['case']) ? trim($_POST['case']) : '';
        error_log("DEBUG: Case received: " . $case);
        switch ($case) {
            case 'LoadingEmployees':
                LoadingEmployees($conn);
                break;
            case 'EditEmployee':
                EditEmployee($conn);
                break;
            case 'AssignPayheadsToEmployee':
                AssignPayheadsToEmployee($conn);
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
            case 'ProcessPayroll':
                ProcessPayroll($conn);
                break;
            case 'CheckPayrollCompletion':
                CheckPayrollCompletion($conn);
                break;
            default:
                error_log("DEBUG: Invalid case: " . $case);
                send_response(1, 'Kasus tidak valid.');
        }
    } else {
        error_log("DEBUG: Request method is not POST");
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    $conn->close();
    exit();
}

/**
 * Fungsi GetAllPayheads: Mengambil seluruh data payheads.
 */
function GetAllPayheads($conn) {
    error_log("DEBUG: Entering GetAllPayheads");
    $sql = "SELECT id, nama_payhead, jenis, nominal FROM payheads ORDER BY nama_payhead ASC";
    $result = $conn->query($sql);
    if (!$result) {
        error_log("DEBUG: Query failed in GetAllPayheads: " . $conn->error);
        send_response(1, 'Query gagal: ' . $conn->error);
    }
    $payheads = [];
    while ($row = $result->fetch_assoc()){
        $payheads[] = [
            'id'                => $row['id'],
            'nama_payhead'      => $row['nama_payhead'],
            'jenis_payhead'     => $row['jenis'],
            'jenis_payhead_idn' => translateJenis($row['jenis']),
            'nominal'           => $row['nominal']
        ];
    }
    error_log("DEBUG: GetAllPayheads returning " . count($payheads) . " records");
    send_response(0, $payheads);
}

/**
 * Fungsi LoadingEmployees: Mengambil data anggota untuk DataTables.
 */
function LoadingEmployees($conn) {
    error_log("DEBUG: Entering LoadingEmployees");
    $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = isset($_POST['search']['value']) ? sanitize_input($_POST['search']['value']) : '';
    $jenjangFilter = isset($_POST['jenjang']) ? sanitize_input($_POST['jenjang']) : '';
    $roleFilter = isset($_POST['role']) ? sanitize_input($_POST['role']) : '';
    $selectedMonth = isset($_POST['selectedMonth']) ? intval($_POST['selectedMonth']) : date('n');
    $selectedYear = isset($_POST['selectedYear']) ? intval($_POST['selectedYear']) : date('Y');
    error_log("DEBUG: LoadingEmployees parameters: draw=$draw, start=$start, length=$length, search=$search, jenjangFilter=$jenjangFilter, roleFilter=$roleFilter, selectedMonth=$selectedMonth, selectedYear=$selectedYear");

    $subqueryPayrollStatus = "(SELECT p.status FROM payroll p WHERE p.id_anggota = a.id AND p.bulan = $selectedMonth AND p.tahun = $selectedYear ORDER BY p.tgl_payroll DESC LIMIT 1) AS payroll_status";

    $sql = "SELECT SQL_CALC_FOUND_ROWS
                a.id, a.uid, a.nip, a.nama, a.jenjang, a.role,
                a.job_title, a.status_kerja, a.masa_kerja_tahun, a.masa_kerja_bulan,
                a.gaji_pokok, a.no_rekening, a.email,
                si.level AS salary_index_level, si.base_salary AS salary_index_base,
                $subqueryPayrollStatus
            FROM anggota_sekolah a
            LEFT JOIN salary_indices si ON a.salary_index_id = si.id
            WHERE 1=1";
    $params = [];
    $types  = "";
    if (!empty($jenjangFilter)) {
        $sql .= " AND a.jenjang = ?";
        $params[] = $jenjangFilter;
        $types .= "s";
    }
    if (!empty($roleFilter)) {
        $sql .= " AND a.role = ?";
        $params[] = $roleFilter;
        $types .= "s";
    }
    if (!empty($search)) {
        $sql .= " AND (a.id LIKE ? OR a.uid LIKE ? OR a.nip LIKE ? OR a.nama LIKE ? OR
                    a.jenjang LIKE ? OR a.role LIKE ? OR a.job_title LIKE ? OR
                    a.status_kerja LIKE ? OR a.no_rekening LIKE ? OR a.email LIKE ?)";
        $searchParam = "%" . $search . "%";
        for ($i = 0; $i < 10; $i++) {
            $params[] = $searchParam;
            $types .= "s";
        }
    }
    $orderBy = " ORDER BY a.id DESC";
    if (isset($_POST['order'][0]['column'], $_POST['columns'])) {
        $columnIndex = intval($_POST['order'][0]['column']);
        $allowedColumns = ['id','uid','nip','nama','jenjang','role','job_title','masa_kerja','salary_index_level','gaji_pokok','no_rekening','email'];
        if (isset($_POST['columns'][$columnIndex]['data']) &&
            in_array($_POST['columns'][$columnIndex]['data'], $allowedColumns)) {
            $colName = $_POST['columns'][$columnIndex]['data'];
            $colSortOrder = ($_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
            if ($colName !== 'masa_kerja') {
                $orderBy = " ORDER BY a.$colName $colSortOrder";
            }
        }
    }
    $sql .= $orderBy . " LIMIT ?, ?";
    $params[] = $start;
    $params[] = $length;
    $types .= "ii";

    error_log("DEBUG: Final SQL in LoadingEmployees: " . $sql);
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("DEBUG: Prepare failed in LoadingEmployees: " . $conn->error);
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        error_log("DEBUG: Execute failed in LoadingEmployees: " . $stmt->error);
        send_response(1, 'Execute failed: ' . $stmt->error);
    }
    $resData = $stmt->get_result();
    $resultFiltered = $conn->query("SELECT FOUND_ROWS() AS total");
    $totalFiltered = ($resultFiltered) ? $resultFiltered->fetch_assoc()['total'] : 0;
    $resultTotal = $conn->query("SELECT COUNT(*) AS total FROM anggota_sekolah");
    $totalData = ($resultTotal) ? $resultTotal->fetch_assoc()['total'] : 0;

    $data = [];
    while ($row = $resData->fetch_assoc()){
        $masaKerja = ($row['masa_kerja_tahun'] > 0 ? $row['masa_kerja_tahun'] . ' Thn ' : '') .
                     ($row['masa_kerja_bulan'] > 0 ? $row['masa_kerja_bulan'] . ' Bln' : '');
        $masaKerja = trim($masaKerja) ?: '-';
        $gajiPokok = number_format($row['gaji_pokok'], 2, ',', '.');
        
        if (!empty($row['payroll_status'])) {
            if ($row['payroll_status'] === 'final') {
                $statusPayroll = '<span class="badge bg-success text-white">Final</span>';
            } else if ($row['payroll_status'] === 'revisi') {
                $statusPayroll = '<span class="badge bg-warning text-dark">Revisi</span>';
            } else if ($row['payroll_status'] === 'draft') {
                $statusPayroll = '<span class="badge bg-info text-white">Draft</span>';
            } else {
                $statusPayroll = '<span class="badge bg-secondary text-white">Belum Diproses</span>';
            }
        } else {
            $statusPayroll = '<span class="badge bg-secondary text-white">Belum Diproses</span>';
        }
        
        if ($row['payroll_status'] === 'final') {
            $assignButton = '<span class="dropdown-item disabled" title="Payroll Final">Assign Payheads</span>';
        } else {
            $assignButton = '<a class="dropdown-item btnAssignPayheads" href="javascript:void(0)" data-id="' . $row['id'] . '">
    <i class="bi bi-cash-stack"></i> Assign Payheads
    </a>';
        }
        $aksi = '
    <div class="dropdown">
      <button class="btn" type="button" id="dropdownMenuButton_' . $row['id'] . '" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="bi bi-three-dots-vertical"></i>
      </button>
      <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_' . $row['id'] . '">
          <a class="dropdown-item btnEdit" href="javascript:void(0)" data-id="' . $row['id'] . '">
          <i class="bi bi-pencil-square"></i> Edit
          </a>
          ' . $assignButton . '
          <a class="dropdown-item btnViewDetail" href="javascript:void(0)" data-id="' . $row['id'] . '">
          <i class="bi bi-eye-fill"></i> View Detail
          </a>
      </div>
    </div>';
        $data[] = [
            "id"            => $row['id'],
            "uid"           => $row['uid'],
            "nip"           => $row['nip'],
            "nama"          => $row['nama'],
            "jenjang"       => $row['jenjang'],
            "role"          => $row['role'],
            "job_title"     => $row['job_title'],
            "masa_kerja"    => $masaKerja,
            "level_indeks"  => $row['salary_index_level'] ?: '-',
            "gaji_pokok"    => $gajiPokok,
            "no_rekening"   => $row['no_rekening'],
            "email"         => $row['email'],
            "payroll_status"=> $statusPayroll,
            "aksi"          => $aksi
        ];
    }
    error_log("DEBUG: Loaded " . count($data) . " employees");
    $stmt->close();
    echo json_encode([
        "draw"            => $draw,
        "recordsTotal"    => $totalData,
        "recordsFiltered" => $totalFiltered,
        "data"            => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Fungsi EditEmployee: Memperbarui data no rekening anggota.
 */
function EditEmployee($conn) {
    error_log("DEBUG: Entering EditEmployee");
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $no_rekening = isset($_POST['no_rekening']) ? sanitize_input($_POST['no_rekening']) : '';
    if ($id <= 0 || empty($no_rekening)) {
        error_log("DEBUG: EditEmployee: Invalid parameters id=$id, no_rekening=$no_rekening");
        send_response(1, 'ID dan No Rekening wajib diisi.');
    }
    $stmtBefore = $conn->prepare("SELECT no_rekening FROM anggota_sekolah WHERE id = ? LIMIT 1");
    if ($stmtBefore) {
        $stmtBefore->bind_param("i", $id);
        $stmtBefore->execute();
        $stmtBefore->get_result();
        $stmtBefore->close();
    } else {
        error_log("DEBUG: EditEmployee: Prepare failed: " . $conn->error);
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmtUpdate = $conn->prepare("UPDATE anggota_sekolah SET no_rekening = ? WHERE id = ?");
    if (!$stmtUpdate) {
        error_log("DEBUG: EditEmployee: Prepare failed (update): " . $conn->error);
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmtUpdate->bind_param("si", $no_rekening, $id);
    if ($stmtUpdate->execute()){
        error_log("DEBUG: EditEmployee: Update successful for ID $id");
        send_response(0, 'No Rekening anggota berhasil diperbarui.');
    } else {
        error_log("DEBUG: EditEmployee: Update failed: " . $stmtUpdate->error);
        send_response(1, 'Gagal memperbarui No Rekening: ' . $stmtUpdate->error);
    }
    $stmtUpdate->close();
}

/**
 * Fungsi AssignPayheadsToEmployee: Menetapkan payheads kepada anggota.
 */
function AssignPayheadsToEmployee($conn) {
    error_log("DEBUG: Entering AssignPayheadsToEmployee");
    $empcode = isset($_POST['empcode']) ? intval($_POST['empcode']) : 0;
    $payheads    = isset($_POST['payheads']) ? json_decode($_POST['payheads'], true) : [];
    $pay_amounts = isset($_POST['pay_amounts']) ? json_decode($_POST['pay_amounts'], true) : [];
    $remarks     = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    if ($empcode <= 0) {
        send_response(1, 'ID anggota tidak valid.');
    }
    if (empty($payheads)) {
        send_response(1, 'Tidak ada payheads yang dipilih.');
    }
    $supportDocPath = '';
    if (isset($_FILES['support_doc']) && $_FILES['support_doc']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../uploads/payhead_support/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileTmpPath = $_FILES['support_doc']['tmp_name'];
        $fileName    = basename($_FILES['support_doc']['name']);
        $fileExt     = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = uniqid('doc_', true) . '.' . $fileExt;
        $destPath    = $uploadDir . $newFileName;
        if (!move_uploaded_file($fileTmpPath, $destPath)) {
            send_response(1, 'Gagal mengunggah dokumen pendukung.');
        }
        $supportDocPath = '/payroll_absensi_v2/uploads/payhead_support/' . $newFileName;
    }
    $conn->begin_transaction();
    try {
        $stmtDelete = $conn->prepare("DELETE FROM employee_payheads WHERE id_anggota = ?");
        $stmtDelete->bind_param("i", $empcode);
        $stmtDelete->execute();
        $stmtDelete->close();
        $stmtGetJenis = $conn->prepare("SELECT jenis FROM payheads WHERE id = ?");
        $stmtInsert = $conn->prepare("INSERT INTO employee_payheads (id_anggota, id_payhead, jenis, amount, status, remarks, support_doc_path) VALUES (?, ?, ?, ?, 'draft', ?, ?)");
        foreach ($payheads as $payhead_id) {
            $payhead_id = intval($payhead_id);
            $nilai = isset($pay_amounts[$payhead_id])
                ? floatval(str_replace(['.', ','], ['', '.'], $pay_amounts[$payhead_id]))
                : 0.0;
            $stmtGetJenis->bind_param("i", $payhead_id);
            $stmtGetJenis->execute();
            $resJenis = $stmtGetJenis->get_result();
            if ($resJenis->num_rows === 0) {
                throw new Exception("Payhead ID $payhead_id tidak ditemukan.");
            }
            $rowJenis = $resJenis->fetch_assoc();
            $jenis = $rowJenis['jenis'];
            $stmtInsert->bind_param("iisdss", $empcode, $payhead_id, $jenis, $nilai, $remarks, $supportDocPath);
            $stmtInsert->execute();
        }
        $stmtGetJenis->close();
        $stmtInsert->close();
        $conn->commit();
        error_log("Assigned payheads to $empcode in draft status. Remarks: $remarks. Doc: $supportDocPath");
        send_response(0, 'Payheads berhasil disimpan dalam status draft.');
    } catch (Exception $e) {
        $conn->rollback();
        send_response(1, 'Gagal menugaskan payheads: ' . $e->getMessage());
    }
}

/**
 * Fungsi ViewEmployeeDetail: Menampilkan detail anggota beserta payheads dan perhitungan gaji bersih.
 */
function ViewEmployeeDetail($conn) {
    error_log("DEBUG: Entering ViewEmployeeDetail");
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        error_log("DEBUG: ViewEmployeeDetail: Invalid employee ID: $id");
        send_response(1, 'ID anggota tidak valid.');
    }
    $stmt = $conn->prepare("SELECT a.*, si.level AS salary_index_level, si.base_salary AS salary_index_base FROM anggota_sekolah a LEFT JOIN salary_indices si ON a.salary_index_id = si.id WHERE a.id = ? LIMIT 1");
    if (!$stmt) {
        error_log("DEBUG: ViewEmployeeDetail: Prepare failed: " . $conn->error);
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        error_log("DEBUG: ViewEmployeeDetail: Execute failed: " . $stmt->error);
        send_response(1, 'Execute failed: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $emp = $res->fetch_assoc();
        $stmt->close();
        $masaKerja = ($emp['masa_kerja_tahun'] > 0 ? $emp['masa_kerja_tahun'] . ' Tahun ' : '') .
                     ($emp['masa_kerja_bulan'] > 0 ? $emp['masa_kerja_bulan'] . ' Bulan' : '');
        $masaKerja = trim($masaKerja) ?: '-';
        $gajiPokokVal = floatval($emp['gaji_pokok']);
        $levelIndexVal = floatval($emp['salary_index_base']);

        $stmtPH = $conn->prepare("SELECT ep.id_payhead, ph.nama_payhead, ph.jenis AS jenis_payhead, ep.amount FROM employee_payheads ep JOIN payheads ph ON ep.id_payhead = ph.id WHERE ep.id_anggota = ?");
        if (!$stmtPH) {
            error_log("DEBUG: ViewEmployeeDetail: Prepare failed (payheads): " . $conn->error);
            send_response(1, 'Prepare failed: ' . $conn->error);
        }
        $stmtPH->bind_param("i", $id);
        if (!$stmtPH->execute()) {
            error_log("DEBUG: ViewEmployeeDetail: Execute failed (payheads): " . $stmtPH->error);
            send_response(1, 'Execute failed: ' . $stmtPH->error);
        }
        $resPH = $stmtPH->get_result();
        $assigned = [];
        $totalPendapatan = 0;
        $totalPotongan = 0;
        while ($rw = $resPH->fetch_assoc()){
            $assigned[] = [
                'id_payhead'       => $rw['id_payhead'],
                'nama_payhead'     => $rw['nama_payhead'],
                'jenis_payhead'    => $rw['jenis_payhead'],
                'jenis_payhead_idn'=> translateJenis($rw['jenis_payhead']),
                'amount'           => $rw['amount']
            ];
            if ($rw['jenis_payhead'] === 'earnings') {
                $totalPendapatan += floatval($rw['amount']);
            } else {
                $totalPotongan += floatval($rw['amount']);
            }
        }
        $stmtPH->close();
        $gajiBersihVal = $gajiPokokVal + $levelIndexVal + $totalPendapatan - $totalPotongan;
        error_log("DEBUG: ViewEmployeeDetail: Employee $id: gajiBersih = $gajiBersihVal");
        // Catat audit log
        $user_nip = $_SESSION['nip'] ?? '';
        $details_log = "Melihat detail anggota dengan NIP $user_nip, ID: $id.";
        add_audit_log($conn, $user_nip, 'ViewEmployeeDetail', $details_log);
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
            'gaji_pokok'         => 'Rp ' . number_format($gajiPokokVal, 2, ',', '.'),
            'no_rekening'        => $emp['no_rekening'],
            'email'              => $emp['email'],
            'jenis_kelamin'      => $emp['jenis_kelamin'],
            'agama'              => $emp['agama'],
            'masa_kerja_tahun'   => $emp['masa_kerja_tahun'],
            'masa_kerja_bulan'   => $emp['masa_kerja_bulan'],
            'payheads'           => $assigned,
            'total_pendapatan'   => $totalPendapatan,
            'total_potongan'     => $totalPotongan,
            'salary_index_level' => $emp['salary_index_level'] ?: '-',
            'salary_index_base'  => $levelIndexVal,
            'gaji_bersih'        => $gajiBersihVal
        ]);
    } else {
        $stmt->close();
        error_log("DEBUG: ViewEmployeeDetail: Employee not found for ID $id");
        send_response(1, 'Anggota tidak ditemukan.');
    }
}

/**
 * Fungsi GetPayheadById: Mengambil detail payhead berdasarkan ID.
 */
function GetPayheadById($conn) {
    error_log("DEBUG: Entering GetPayheadById");
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        error_log("DEBUG: GetPayheadById: Invalid ID: $id");
        send_response(1, 'ID payhead tidak valid.');
    }
    $stmt = $conn->prepare("SELECT id, nama_payhead, jenis AS jenis_payhead FROM payheads WHERE id = ? LIMIT 1");
    if (!$stmt) {
        error_log("DEBUG: GetPayheadById: Prepare failed: " . $conn->error);
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        error_log("DEBUG: GetPayheadById: Execute failed: " . $stmt->error);
        send_response(1, 'Execute failed: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $payhead = $res->fetch_assoc();
        $payhead['jenis_payhead_idn'] = translateJenis($payhead['jenis_payhead']);
        error_log("DEBUG: GetPayheadById: Found payhead: " . print_r($payhead, true));
        send_response(0, $payhead);
    } else {
        error_log("DEBUG: GetPayheadById: Payhead not found for ID $id");
        send_response(1, 'Payhead tidak ditemukan.');
    }
    $stmt->close();
}

// --- Modal Select Month (global payroll) ---
// Ambil data processedMonths
$processedMonths = [];
$resultTotal = $conn->query("SELECT COUNT(*) as total FROM anggota_sekolah");
$totalAnggota = ($resultTotal) ? intval($resultTotal->fetch_assoc()['total']) : 0;
$sql = "SELECT bulan, tahun, COUNT(DISTINCT id_anggota) as completed FROM payroll WHERE status = 'final' GROUP BY bulan, tahun";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()){
        if (intval($row['completed']) === $totalAnggota) {
            $processedMonths[] = ['bulan' => intval($row['bulan']), 'tahun' => intval($row['tahun'])];
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
    <!-- CSS Bootstrap 5 & SB Admin 2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/css/sb-admin-2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.1.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    <style>
        body { color: #000 !important; }
        .text-gray-800 { color: #000 !important; }
        .btnEdit, .btnAssignPayheads, .btnViewDetail {
            transition: background-color 0.3s, transform 0.2s;
        }
        .btnEdit:hover, .btnAssignPayheads:hover, .btnViewDetail:hover {
            transform: scale(1.05);
        }
        .card-header {
            background: #4e73df;
            color: white;
        }
        .table-hover tbody tr:hover { background-color: #e2e6ea; }
        .aksi-column .btn { margin-right: 5px; margin-bottom: 5px; }
        #employees th, #employees td {
            font-size: 14px; text-align: left; vertical-align: middle; white-space: nowrap; color: #000 !important;
        }
        .spinner-border { margin-left: 5px; }
        .modal-body { display: flex; gap: 25px; color: #000 !important; }
        .modal-body > div {
            flex: 1; border: 1px solid #ccc; border-radius: 5px; background-color: #f9f9f9; padding: 10px;
        }
        /* Modal Assign Payheads */
        #ManageModal .modal-dialog {
            max-width: 1500px;
            margin: auto;
            margin-top: 50px;
        }
        #all_payheads, #selected_payheads {
            white-space: normal !important;
            height: auto !important;
            min-height: 250px;
            overflow-y: auto;
        }
        .processed-month {
            background: #343a40 !important;
            color: #fff !important;
            pointer-events: none;
            border: 1px solid #343a40;
        }
        .mr-3 { margin-right: 1rem !important; }
        .mr-2 { margin-right: 0.5rem !important; }
        .ml-auto { margin-left: auto !important; }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include __DIR__ . '/../../navbar.php'; ?>
                <div class="container-fluid">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/payroll_absensi_v2/payroll/keuangan/dashboard_keuangan.php">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Payroll Anggota</li>
                        </ol>
                    </nav>
                    <!-- Header untuk bulan yang dipilih -->
                    <div id="selectedMonthDisplay" class="mb-3" style="cursor: pointer;"></div>
                    <h1 class="h3 mb-4 text-gray-800"><i class="bi bi-people-fill"></i> Payroll Anggota</h1>
                    <div id="alert-placeholder"></div>
                    <!-- Filter Anggota -->
                    <div class="card mb-4">
                        <div class="card-header fw-bold"><i class="bi bi-filter-square-fill"></i> Filter Anggota</div>
                        <div class="card-body">
                            <form id="filterForm" class="row align-items-center">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                <div class="form-group mb-2 col-auto">
                                    <label for="filterJenjang" class="me-2">Jenjang Pendidikan:</label>
                                    <select class="form-control" id="filterJenjang" name="jenjang">
                                        <option value="">Semua Jenjang</option>
                                        <?php
                                        $stmtJenjang = $conn->prepare("SELECT DISTINCT jenjang FROM anggota_sekolah WHERE jenjang IS NOT NULL AND jenjang != '' ORDER BY jenjang ASC");
                                        if ($stmtJenjang) {
                                            $stmtJenjang->execute();
                                            $resJenjang = $stmtJenjang->get_result();
                                            while ($row = $resJenjang->fetch_assoc()){
                                                echo '<option value="' . htmlspecialchars($row['jenjang']) . '">' . htmlspecialchars($row['jenjang']) . '</option>';
                                            }
                                            $stmtJenjang->close();
                                        } else {
                                            echo '<option value="">Tidak ada data</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group mb-2 col-auto">
                                    <label for="filterRole" class="me-2">Role:</label>
                                    <select class="form-control" id="filterRole" name="role">
                                        <option value="">Semua Role</option>
                                        <?php
                                        $stmtRole = $conn->prepare("SELECT DISTINCT role FROM anggota_sekolah WHERE role IS NOT NULL AND role != '' ORDER BY role ASC");
                                        if ($stmtRole) {
                                            $stmtRole->execute();
                                            $resRole = $stmtRole->get_result();
                                            while ($row = $resRole->fetch_assoc()){
                                                echo '<option value="' . htmlspecialchars($row['role']) . '">' . htmlspecialchars($row['role']) . '</option>';
                                            }
                                            $stmtRole->close();
                                        } else {
                                            echo '<option value="">Tidak ada data</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group mb-2 col-auto">
                                    <button type="button" class="btn btn-primary mb-2 me-2" id="btnApplyFilter">
                                        <i class="fas fa-filter"></i> Terapkan Filter
                                    </button>
                                    <button type="button" class="btn btn-secondary mb-2" id="btnResetFilter">
                                        <i class="fas fa-undo"></i> Reset Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- Container DataTable -->
                    <div id="employeeContainer" style="display: none;">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 fw-bold text-white"><i class="fas fa-clipboard-list"></i> Daftar Anggota Sekolah</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="employees" class="table table-sm table-bordered table-striped display nowrap text-dark" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>UID</th>
                                                <th>NIP</th>
                                                <th>Nama</th>
                                                <th>Jenjang</th>
                                                <th>Role</th>
                                                <th>Job Title</th>
                                                <th>Masa Kerja</th>
                                                <th>Level Indeks</th>
                                                <th>Gaji Pokok</th>
                                                <th>No Rekening</th>
                                                <th>Email</th>
                                                <th>Status Payroll</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody><!-- Data akan dimuat oleh DataTables --></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div> <!-- End employeeContainer -->
                </div> <!-- /.container-fluid -->
            </div> <!-- End #content -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?php echo date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div> <!-- End content-wrapper -->
    </div> <!-- End wrapper -->

    <!-- MODAL: Edit anggota -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="editEmployeeForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editEmployeeModalLabel"><i class="bi bi-pencil-square"></i> Update No Rekening anggota</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="case" value="EditEmployee">
                        <input type="hidden" name="id" id="editEmployeeId">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                        <div class="container-fluid">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="editNip">NIP</label>
                                        <input type="text" name="nip" id="editNip" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="editNama">Nama</label>
                                        <input type="text" name="nama" id="editNama" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="editJenjang">Jenjang Pendidikan</label>
                                        <input type="text" name="jenjang" id="editJenjang" class="form-control" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="editJobTitle">Job Title</label>
                                        <input type="text" name="job_title" id="editJobTitle" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="editStatusKerja">Status Kerja</label>
                                        <select name="status_kerja" id="editStatusKerja" class="form-control" disabled>
                                            <option value="">---Pilih Status---</option>
                                            <option value="Tetap">Tetap</option>
                                            <option value="Kontrak">Kontrak</option>
                                            <option value="Paruh Waktu">Paruh Waktu</option>
                                            <option value="Magang">Magang</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="editNoRekening">No Rekening <span class="text-danger">*</span></label>
                                        <input type="text" name="no_rekening" id="editNoRekening" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="editEmail">Email</label>
                                        <input type="email" name="email" id="editEmail" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="editJenisKelamin">Jenis Kelamin</label>
                                        <select name="jenis_kelamin" id="editJenisKelamin" class="form-control" disabled>
                                            <option value="">---</option>
                                            <option value="L">Laki-laki</option>
                                            <option value="P">Perempuan</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="editAgama">Agama</label>
                                        <input type="text" name="agama" id="editAgama" class="form-control" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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

    <!-- MODAL: Assign Payheads -->
    <div class="modal fade" id="ManageModal" tabindex="-1" aria-labelledby="manageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form id="assign-payhead-form" enctype="multipart/form-data">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="manageModalLabel">
                            <i class="bi bi-cash-stack"></i> Tetapkan / Perbarui Payheads ke anggota
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="container-fluid">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="remarks"><strong>Keterangan Perubahan:</strong></label>
                                        <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="Masukkan keterangan perubahan..."></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="support_doc"><strong>Unggah Dokumen Pendukung:</strong></label>
                                        <input type="file" class="form-control" id="support_doc" name="support_doc">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header bg-secondary text-white">Payheads Tersedia</div>
                                        <div class="card-body">
                                            <div class="form-group mb-2">
                                                <input type="text" id="searchAllPayheads" class="form-control" placeholder="Cari payheads...">
                                            </div>
                                            <div class="form-group mb-2 text-end">
                                                <button type="button" id="selectHeads" class="btn btn-success btn-sm">
                                                    <i class="fa fa-arrow-circle-right"></i> Tambah ke Pilihan
                                                </button>
                                            </div>
                                            <select id="all_payheads" class="form-control" multiple style="height: 250px;"></select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header bg-secondary text-white">Payheads Terpilih</div>
                                        <div class="card-body">
                                            <div class="form-group mb-2">
                                                <input type="text" id="searchSelectedPayheads" class="form-control" placeholder="Cari payheads...">
                                            </div>
                                            <div class="form-group mb-2 text-end">
                                                <button type="button" id="removeHeads" class="btn btn-danger btn-sm">
                                                    <i class="fa fa-arrow-circle-left"></i> Hapus dari Pilihan
                                                </button>
                                            </div>
                                            <select id="selected_payheads" class="form-control" multiple style="height: 250px;"></select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header bg-secondary text-white">Tetapkan Jumlah</div>
                                        <div class="card-body" style="max-height: 250px; overflow-y: auto;">
                                            <table class="table table-bordered mb-0" id="selected_payamount_table">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 5%;">No.</th>
                                                        <th style="width: 55%;">Nama Payhead</th>
                                                        <th style="width: 40%;">Nominal</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="case" value="AssignPayheadsToEmployee">
                        <input type="hidden" name="empcode" id="empcode">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-success" id="btnProcessPayroll">
                            <i class="bi bi-check-circle"></i> Proses Payroll
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check-circle"></i> Simpan Payheads
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times-circle"></i> Batal
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
                        <tr><th>ID</th><td id="detailId"></td></tr>
                        <tr><th>UID</th><td id="detailUid"></td></tr>
                        <tr><th>NIP</th><td id="detailNip"></td></tr>
                        <tr><th>Nama</th><td id="detailNama"></td></tr>
                        <tr><th>Jenjang Pendidikan</th><td id="detailJenjang"></td></tr>
                        <tr><th>Role</th><td id="detailRole"></td></tr>
                        <tr><th>Job Title</th><td id="detailJobTitle"></td></tr>
                        <tr><th>Status Kerja</th><td id="detailStatusKerja"></td></tr>
                        <tr><th>Masa Kerja</th><td id="detailMasaKerja"></td></tr>
                        <tr><th>No Rekening</th><td id="detailNoRekening"></td></tr>
                        <tr><th>Email</th><td id="detailEmail"></td></tr>
                        <tr><th>Jenis Kelamin</th><td id="detailJenisKelamin"></td></tr>
                        <tr><th>Agama</th><td id="detailAgama"></td></tr>
                        <tr><th>Gaji Pokok</th><td id="detailGajiPokok"></td></tr>
                        <tr><th>Nominal Level Indeks</th><td id="detailSalaryIndexNominal"></td></tr>
                        <tr><th>Payheads</th><td id="detailPayheads"></td></tr>
                        <tr><th>Total Pendapatan</th><td id="detailTotalPendapatan"></td></tr>
                        <tr><th>Total Potongan</th><td id="detailTotalPotongan"></td></tr>
                        <tr><th>Gaji Bersih</th><td id="detailGajiBersih"></td></tr>
                    </table>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: Select Month (global payroll) -->
    <div class="modal fade" id="SalaryMonthModal" tabindex="-1" aria-labelledby="salaryMonthModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md" style="max-width: 600px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="salaryMonthModalLabel"><i class="fa fa-calendar"></i> Pilih Bulan untuk Gaji</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row text-center">
                        <?php
                        $currentYear  = date('Y');
                        $currentMonth = date('n');
                        $before2Month = $currentMonth - 2;
                        $before2Year  = $currentYear;
                        for ($i = 0; $i < 16; $i++) {
                            $month = $before2Month + $i;
                            $year  = $before2Year;
                            if ($month <= 0) {
                                $month += 12;
                                $year  -= 1;
                            } elseif ($month > 12) {
                                $month -= 12;
                                $year  += 1;
                            }
                            $processed = false;
                            foreach ($processedMonths as $pm) {
                                if ($pm['bulan'] == $month && $pm['tahun'] == $year) {
                                    $processed = true;
                                    break;
                                }
                            }
                            $highlightClass = ($month == $currentMonth && $year == $currentYear) ? 'bg-warning fw-bold' : '';
                            if ($processed) {
                                $highlightClass .= ' processed-month';
                            }
                            echo '<div class="col-sm-3 mb-3">';
                            echo '  <div class="' . trim($highlightClass) . '" style="padding:10px; border:1px solid #ddd; border-radius:5px;">';
                            echo '    <a href="#" class="month-link" data-month-number="' . $month . '" data-month="' . htmlspecialchars(getIndonesianMonthName($month)) . '" data-year="' . htmlspecialchars($year) . '" style="color:#333; text-decoration:none;">';
                            echo '      ' . strtoupper(htmlspecialchars(getIndonesianMonthName($month))) . '<br>' . htmlspecialchars($year);
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
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.colVis.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/js/sb-admin-2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/autonumeric@4.6.0/dist/autoNumeric.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        $('[data-bs-toggle="tooltip"]').tooltip();

        // Inisialisasi DataTable untuk employeeContainer
        function initEmployeesTable() {
            $('#employeeContainer').show();
            if (!$.fn.DataTable.isDataTable('#employees')) {
                $('#employees').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: 'employees.php?ajax=1',
                        type: 'POST',
                        data: function(d) {
                            d.case = 'LoadingEmployees';
                            d.jenjang = $('#filterJenjang').val();
                            d.role = $('#filterRole').val();
                            d.selectedMonth = localStorage.getItem('selectedMonthNumber');
                            d.selectedYear = localStorage.getItem('selectedYearPayroll');
                            d.csrf_token = '<?= htmlspecialchars($csrf_token); ?>';
                        },
                        error: function(xhr, error, thrown) {
                            showToast('Terjadi kesalahan saat memuat data anggota: ' + error, 'error');
                        }
                    },
                    columns: [
                        { data: 'id' },
                        { data: 'uid' },
                        { data: 'nip' },
                        { data: 'nama' },
                        { data: 'jenjang' },
                        { data: 'role' },
                        { data: 'job_title' },
                        { data: 'masa_kerja' },
                        { data: 'level_indeks' },
                        { data: 'gaji_pokok' },
                        { data: 'no_rekening' },
                        { data: 'email' },
                        { data: 'payroll_status', orderable: false, searchable: false },
                        { data: 'aksi', orderable: false, searchable: false }
                    ],
                    order: [[0, 'desc']],
                    dom: 'Bfrtip',
                    buttons: [
                        {
                            extend: 'colvis',
                            text: '<i class="fas fa-columns"></i> Kolom',
                            className: 'btn btn-warning btn-sm'
                        }
                    ],
                    responsive: true,
                    pageLength: 10,
                    autoWidth: false
                });
            }
        }

        function initAutoNumeric() {
            AutoNumeric.multiple('.currency-input', {
                digitGroupSeparator: '.',
                decimalCharacter: ',',
                decimalPlaces: 2,
                unformatOnSubmit: true
            });
        }
        initAutoNumeric();

        function updateRowNumbers() {
            $("#selected_payamount_table tbody tr").each(function(index) {
                $(this).find("td:first").text(index + 1);
            });
        }

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
            Toast.fire({ icon: icon, title: message });
        }

        // Inisialisasi header selectedMonthDisplay
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
        initEmployeesTable();

        // Event handler untuk klik header selectedMonthDisplay atau tombol Ganti Kalender
        $('#selectedMonthDisplay').on('click', '#btnChangeCalendar, h4', function(){
            $('#SalaryMonthModal').modal('show');
        });

        $('#btnApplyFilter').on('click', function(){
            $('#employees').DataTable().ajax.reload();
        });
        $('#btnResetFilter').on('click', function(){
            $('#filterForm')[0].reset();
            $('#employees').DataTable().ajax.reload();
        });

        $('#employees tbody').on('click', '.btnEdit', function() {
            var id = $(this).data('id');
            $.ajax({
                url: 'employees.php?ajax=1',
                type: 'POST',
                data: { case: 'ViewEmployeeDetail', id: id, csrf_token: '<?= htmlspecialchars($csrf_token); ?>' },
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
                beforeSend: function(){
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success: function(resp){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if(resp.code === 0){
                        showToast(resp.result, 'success');
                        $('#employees').DataTable().ajax.reload(null, false);
                        $('#editEmployeeModal').modal('hide');
                    } else {
                        showToast(resp.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat memperbarui data anggota.', 'error');
                }
            });
        });
        $('#employees tbody').on('click', '.btnViewDetail', function(){
            var id = $(this).data('id');
            $.ajax({
                url:'employees.php?ajax=1',
                type:'POST',
                data:{ case: 'ViewEmployeeDetail', id: id, csrf_token: '<?= htmlspecialchars($csrf_token); ?>' },
                dataType:'json',
                success:function(resp){
                    if(resp.code === 0){
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
                        $('#detailJenisKelamin').text(
                            e.jenis_kelamin === 'L' ? 'Laki-laki' :
                            e.jenis_kelamin === 'P' ? 'Perempuan'  : '-'
                        );
                        $('#detailAgama').text(e.agama);
                        $('#detailGajiPokok').text(e.gaji_pokok);
                        let baseSalary = parseFloat(e.salary_index_base) || 0;
                        $('#detailSalaryIndexNominal').text('Rp ' + baseSalary.toLocaleString('id-ID', {minimumFractionDigits:2}));
                        if(e.payheads && e.payheads.length > 0){
                            var s = '<ul>';
                            e.payheads.forEach(function(ph){
                                var nominal = parseFloat(ph.amount).toLocaleString('id-ID',{minimumFractionDigits:2});
                                var jns = (ph.jenis_payhead === 'earnings') ? 'Pendapatan' : 'Potongan';
                                var clr = (ph.jenis_payhead==='earnings') ? 'green' : 'red';
                                s += `<li style="color:${clr}">${ph.nama_payhead} (${jns}): Rp ${nominal}</li>`;
                            });
                            s += '</ul>';
                            $('#detailPayheads').html(s);
                        } else {
                            $('#detailPayheads').html('<i>Tidak ada payheads</i>');
                        }
                        $('#detailTotalPendapatan').text('Rp ' + parseFloat(e.total_pendapatan).toLocaleString('id-ID',{minimumFractionDigits:2}));
                        $('#detailTotalPotongan').text('Rp ' + parseFloat(e.total_potongan).toLocaleString('id-ID',{minimumFractionDigits:2}));
                        $('#detailGajiBersih').text('Rp ' + parseFloat(e.gaji_bersih).toLocaleString('id-ID',{minimumFractionDigits:2}));
                        $('#viewDetailModal').modal('show');
                    } else {
                        showToast(resp.result, 'error');
                    }
                },
                error:function(){
                    showToast('Terjadi kesalahan saat mengambil detail anggota.', 'error');
                }
            });
        });
        $('#employees tbody').on('click', '.btnAssignPayheads', function(){
            var id = $(this).data('id');
            $('#empcode').val(id);
            $('#all_payheads').empty();
            $('#selected_payheads').empty();
            $("#selected_payamount_table tbody").empty();
            $.ajax({
                type: "POST",
                dataType: "json",
                url: 'employees.php?ajax=1',
                data: { case: 'ViewEmployeeDetail', id: id, csrf_token: '<?= htmlspecialchars($csrf_token); ?>' },
                success: function(result) {
                    if(result.code === 0) {
                        var assignedPayheads = result.result.payheads; 
                        $.ajax({
                            type: "POST",
                            dataType: "json",
                            url: 'employees.php?ajax=1',
                            data: { case: 'GetAllPayheads', csrf_token: '<?= htmlspecialchars($csrf_token); ?>' },
                            success: function(allPayheadsResult) {
                                if(allPayheadsResult.code === 0) {
                                    var allPayheadsList = allPayheadsResult.result;
                                    var assignedIds = assignedPayheads.map(function(ph){
                                        return parseInt(ph.id_payhead, 10);
                                    });
                                    var availablePayheads = allPayheadsList.filter(function(ph) {
                                        return !assignedIds.includes(parseInt(ph.id, 10));
                                    });
                                    availablePayheads.forEach(function(ph){
                                        var optionText = ph.nama_payhead + ' (' + ph.jenis_payhead_idn + ')';
                                        var option = $("<option></option>")
                                            .attr("value", ph.id)
                                            .attr("data-nominal", ph.nominal)
                                            .text(optionText)
                                            .addClass(ph.jenis_payhead === 'earnings' ? 'text-success' : 'text-danger');
                                        $('#all_payheads').append(option);
                                    });
                                    assignedPayheads.forEach(function(ap){
                                        var payheadId = ap.id_payhead;
                                        var payheadName = ap.nama_payhead + ' (' + ap.jenis_payhead_idn + ')';
                                        var option = $("<option></option>")
                                            .attr("value", payheadId)
                                            .text(payheadName)
                                            .addClass(ap.jenis_payhead === 'earnings' ? 'text-success' : 'text-danger');
                                        $('#selected_payheads').append(option);
                                        var row = `
                                            <tr id="payhead-row-${payheadId}">
                                            <td></td>
                                            <td>${payheadName}</td>
                                            <td>
                                                <input type="text" name="pay_amounts[${payheadId}]" 
                                                    class="form-control currency-input" 
                                                    value="${ap.amount || 0}" required>
                                            </td>
                                            </tr>
                                        `;
                                        $("#selected_payamount_table tbody").append(row);
                                    });
                                    updateRowNumbers();
                                    initAutoNumeric();
                                    $('#ManageModal').modal('show');
                                } else {
                                    showToast(allPayheadsResult.result, 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                showToast('Terjadi kesalahan saat memuat semua payheads: ' + error, 'error');
                            }
                        });
                    } else {
                        showToast(result.result, 'error');
                    }
                },
                error: function(){
                    showToast('Terjadi kesalahan saat load payheads.', 'error');
                }
            });
        });
        $('#assign-payhead-form').on('submit', function(e){
            e.preventDefault();
            var form = $(this);
            var empId = $('#empcode').val();
            var payHeads = [];
            $('#selected_payheads option').each(function() {
                payHeads.push($(this).val());
            });
            var payAmounts = {};
            payHeads.forEach(function(payheadId){
                var amount = $('input[name="pay_amounts[' + payheadId + ']"]').val();
                payAmounts[payheadId] = amount;
            });
            var isValid = true;
            payHeads.forEach(function(payheadId){
                var amount = payAmounts[payheadId];
                var numericAmount = parseFloat(amount.replace(/\./g, '').replace(',', '.'));
                if(amount === '' || isNaN(numericAmount) || numericAmount <= 0){
                    $('input[name="pay_amounts[' + payheadId + ']"]').addClass('is-invalid');
                    isValid = false;
                } else {
                    $('input[name="pay_amounts[' + payheadId + ']"]').removeClass('is-invalid');
                }
            });
            if(!isValid){
                showToast('Pastikan semua jumlah payhead valid (angka dan > 0)!', 'error');
                return;
            }
            var formData = new FormData(form[0]);
            formData.append('payheads', JSON.stringify(payHeads));
            formData.append('pay_amounts', JSON.stringify(payAmounts));
            $.ajax({
                url:'employees.php?ajax=1',
                type:'POST',
                dataType:'json',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend:function(){
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success:function(resp){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if(resp.code === 0){
                        showToast(resp.result, 'success');
                        $('#employees').DataTable().ajax.reload(null, false);
                        setTimeout(function(){
                            $('#ManageModal').modal('hide');
                            form[0].reset();
                            $('#all_payheads').empty();
                            $('#selected_payheads').empty();
                            $("#selected_payamount_table tbody").empty();
                        }, 200);
                    } else {
                        showToast(resp.result, 'error');
                    }
                },
                error:function(xhr, status, error){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat menetapkan payheads: ' + error, 'error');
                }
            });
        });
        $('#ManageModal').on('click', '#removeHeads', function(){
            var selectedOptions = $('#selected_payheads option:selected');
            if(selectedOptions.length === 0){
                showToast('Pilih setidaknya satu payhead untuk dihapus.', 'info');
                return;
            }
            selectedOptions.each(function(){
                var payheadId = $(this).val();
                var payheadName = $(this).text();
                $(this).remove();
                $("#payhead-row-" + payheadId).remove();
                updateRowNumbers();
                var jenisPayhead = (payheadName.includes('(Pendapatan)')) ? 'earnings' : 'deductions';
                var option = $("<option></option>")
                    .attr("value", payheadId)
                    .text(payheadName)
                    .addClass(jenisPayhead === 'earnings' ? 'text-success' : 'text-danger');
                $('#all_payheads').append(option);
            });
        });
        $('#ManageModal').on('click', '#selectHeads', function(){
            var selectedOptions = $('#all_payheads option:selected');
            if(selectedOptions.length === 0){
                showToast('Pilih setidaknya satu payhead untuk ditetapkan.', 'info');
                return;
            }
            selectedOptions.each(function(){
                var payheadId = $(this).val();
                var payheadName = $(this).text();
                var jenisPayhead = $(this).hasClass('text-success') ? 'earnings' : 'deductions';
                var defaultNominal = $(this).attr("data-nominal") || "0";
                if($('#selected_payheads option[value="' + payheadId + '"]').length === 0){
                    var option = $("<option></option>")
                        .attr("value", payheadId)
                        .text(payheadName)
                        .addClass(jenisPayhead === 'earnings' ? 'text-success' : 'text-danger');
                    $('#selected_payheads').append(option);
                    var row = `
                        <tr id="payhead-row-${payheadId}">
                        <td></td>
                        <td>${payheadName}</td>
                        <td>
                            <input type="text" name="pay_amounts[${payheadId}]" 
                                class="form-control currency-input" 
                                value="${parseFloat(defaultNominal).toLocaleString('id-ID', { minimumFractionDigits:2 })}" required>
                        </td>
                        </tr>
                    `;
                    $("#selected_payamount_table tbody").append(row);
                    updateRowNumbers();
                    $(this).remove();
                }
            });
            initAutoNumeric();
        });
        // Button Proses Payroll pada modal Assign Payheads
        function savePayheads(callback) {
            var form = $('#assign-payhead-form');
            var payHeads = [];
            $('#selected_payheads option').each(function() {
                payHeads.push($(this).val());
            });
            var payAmounts = {};
            payHeads.forEach(function(payheadId) {
                var amount = $('input[name="pay_amounts[' + payheadId + ']"]').val();
                payAmounts[payheadId] = amount;
            });
            var isValid = true;
            payHeads.forEach(function(payheadId) {
                var amount = payAmounts[payheadId];
                var numericAmount = parseFloat(amount.replace(/\./g, '').replace(',', '.'));
                if(amount === '' || isNaN(numericAmount) || numericAmount <= 0){
                    $('input[name="pay_amounts[' + payheadId + ']"]').addClass('is-invalid');
                    isValid = false;
                } else {
                    $('input[name="pay_amounts[' + payheadId + ']"]').removeClass('is-invalid');
                }
            });
            if(!isValid){
                showToast('Pastikan semua jumlah payhead valid (angka dan > 0)!', 'error');
                return;
            }
            var formData = new FormData(form[0]);
            formData.append('payheads', JSON.stringify(payHeads));
            formData.append('pay_amounts', JSON.stringify(payAmounts));
            $.ajax({
                url: 'employees.php?ajax=1',
                type: 'POST',
                dataType: 'json',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function(){
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success: function(resp) {
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if(resp.code === 0){
                        showToast(resp.result, 'success');
                        $('#employees').DataTable().ajax.reload(null, false);
                        setTimeout(function(){
                            $('#ManageModal').modal('hide');
                            form[0].reset();
                            $('#all_payheads').empty();
                            $('#selected_payheads').empty();
                            $("#selected_payamount_table tbody").empty();
                        }, 200);
                        if (typeof callback === 'function') {
                            callback();
                        }
                    } else {
                        showToast(resp.result, 'error');
                    }
                },
                error: function(xhr, status, error){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat menetapkan payheads: ' + error, 'error');
                }
            });
        }
        function callProcessPayroll(empcode, selectedMonth, selectedYear) {
            $.ajax({
                url: 'employees.php?ajax=1',
                type: 'POST',
                dataType: 'json',
                data: {
                    case: 'ProcessPayroll',
                    id_anggota: empcode,
                    selectedMonth: selectedMonth,
                    selectedYear: selectedYear,
                    csrf_token: '<?= htmlspecialchars($csrf_token); ?>'
                },
                success: function(resp) {
                    if(resp.code === 0) {
                        Swal.fire('Berhasil', resp.result, 'success').then(() => {
                            $('#employees').DataTable().ajax.reload(null, false);
                            $('#ManageModal').modal('hide');
                        });
                    } else {
                        Swal.fire('Gagal', resp.result, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire('Error', 'Terjadi kesalahan saat memproses payroll: ' + error, 'error');
                }
            });
        }
        $('#btnProcessPayroll').on('click', function(){
            var selectedMonth = localStorage.getItem('selectedMonthNumber') || 0;
            var selectedYear = localStorage.getItem('selectedYearPayroll') || 0;
            var empcode = $('#empcode').val();
            if (!empcode || selectedMonth == 0 || selectedYear == 0) {
                Swal.fire('Error', 'Pastikan ID anggota dan bulan payroll valid!', 'error');
                return;
            }
            Swal.fire({
                title: 'Proses Payroll',
                text: "Apakah Anda yakin data payroll sudah benar dan ingin diproses?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, proses sekarang',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    if ($('#selected_payheads option').length > 0) {
                        savePayheads(function(){
                            callProcessPayroll(empcode, selectedMonth, selectedYear);
                        });
                    } else {
                        callProcessPayroll(empcode, selectedMonth, selectedYear);
                    }
                }
            });
        });
        $(document).on('click', '.month-link', function(e) {
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
            $('#selectedMonthDisplay').html('<h4>Payroll Bulan: ' + monthName + ' ' + year + ' <button id="btnChangeCalendar" class="btn btn-link">Ganti Kalender</button></h4>');
            $('#SalaryMonthModal').modal('hide');
            initEmployeesTable();
        }
    });
    </script>
</body>
</html>
