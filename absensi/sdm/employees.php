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
 * Di sini kita baca filterMonth & filterYear dari GET
 * Jika tidak ada, gunakan default: bulan & tahun sekarang
 */
$selectedMonth = isset($_GET['filterMonth']) ? intval($_GET['filterMonth']) : date('n');
$selectedYear  = isset($_GET['filterYear'])  ? intval($_GET['filterYear'])  : date('Y');


/**
 * Fungsi ProcessPayroll untuk memproses data payroll dan mengirimkannya ke list payroll.
 * MODIFIKASI: Pastikan perhitungan gaji pokok dan gaji bersih konsisten.
 */
function ProcessPayroll($conn) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $id_anggota = isset($_POST['id_anggota']) ? intval($_POST['id_anggota']) : 0;
    $bulan = isset($_POST['selectedMonth']) ? intval($_POST['selectedMonth']) : 0;
    $tahun = isset($_POST['selectedYear']) ? intval($_POST['selectedYear']) : 0;
    if($id_anggota <= 0 || $bulan <= 0 || $tahun <= 0) {
        send_response(1, 'Parameter tidak valid untuk proses payroll.');
    }
    
    // Update status payheads menjadi final (jika sebelumnya draft atau revisi)
    $stmtFinal = $conn->prepare("UPDATE employee_payheads SET status = 'final' WHERE id_anggota = ? AND status IN ('draft','revisi')");
    $stmtFinal->bind_param("i", $id_anggota);
    if(!$stmtFinal->execute()){
        $error = $stmtFinal->error;
        $stmtFinal->close();
        send_response(1, 'Gagal update status payheads.');
    }
    $stmtFinal->close();

    // Hitung total earnings dan deductions dari employee_payheads
    $sqlSum = "SELECT jenis, SUM(amount) as total FROM employee_payheads WHERE id_anggota = ? AND status = 'final' GROUP BY jenis";
    $stmtSum = $conn->prepare($sqlSum);
    $stmtSum->bind_param("i", $id_anggota);
    if(!$stmtSum->execute()){
        $stmtSum->close();
        send_response(1, 'Gagal menghitung total payheads.');
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

    // Ambil data anggota
    $stmtEmp = $conn->prepare("SELECT gaji_pokok, salary_index_id, no_rekening FROM anggota_sekolah WHERE id = ? LIMIT 1");
    $stmtEmp->bind_param("i", $id_anggota);
    if(!$stmtEmp->execute()){
        $stmtEmp->close();
        send_response(1, 'Gagal mengambil data anggota.');
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
    // Konsisten: gaji pokok adalah penjumlahan dari gaji pokok anggota + salary index
    $gajiPokok = $gajiPokokEmployee + $salaryIndexBase;
    $gajiBersih = $gajiPokok + $totalEarnings - $totalDeductions;
    $tglPayroll = date('Y-m-d H:i:s');
    $catatan = '';

    $statusPayroll = 'draft';
    $stmtPayroll = $conn->prepare("INSERT INTO payroll (id_anggota, bulan, tahun, tgl_payroll, gaji_pokok, total_pendapatan, total_potongan, gaji_bersih, no_rekening, catatan, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtPayroll->bind_param("iiisddddsss", $id_anggota, $bulan, $tahun, $tglPayroll, $gajiPokok, $totalEarnings, $totalDeductions, $gajiBersih, $no_rekening, $catatan, $statusPayroll);
    if(!$stmtPayroll->execute()){
        $stmtPayroll->close();
        send_response(1, 'Gagal insert payroll.');
    }
    $stmtPayroll->close();

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
        send_response(1, 'Gagal menghitung anggota yang belum final.');
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
 * Fungsi ViewRekapAbsensi: Mengambil data rekap absensi untuk anggota, bulan, dan tahun tertentu.
 */
function ViewRekapAbsensi($conn) {
    $id_anggota = intval($_POST['id']);
    $bulan = intval($_POST['selectedMonth']);
    $tahun = intval($_POST['selectedYear']);
    
    $stmt = $conn->prepare("SELECT * FROM rekap_absensi WHERE id_anggota = ? AND bulan = ? AND tahun = ? LIMIT 1");
    $stmt->bind_param("iii", $id_anggota, $bulan, $tahun);
    if(!$stmt->execute()){
        send_response(1, 'Gagal mengambil rekap absensi.');
    }
    $result = $stmt->get_result();
    if($result->num_rows > 0){
        $rekap = $result->fetch_assoc();
        send_response(0, $rekap);
    } else {
        send_response(0, [
            'id' => 0,
            'id_anggota' => $id_anggota,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'total_hadir' => 0,
            'total_izin' => 0,
            'total_cuti' => 0,
            'total_tanpa_keterangan' => 0,
            'total_sakit' => 0
        ]);
    }
    $stmt->close();
}


/**
 * Fungsi EditRekapAbsensi: Menyimpan (update atau insert) data rekap absensi untuk anggota.
 */
function EditRekapAbsensi($conn) {
    $id_anggota = intval($_POST['id_anggota']);
    $bulan = intval($_POST['bulan']);
    $tahun = intval($_POST['tahun']);
    $total_hadir = intval($_POST['total_hadir']);
    $total_izin = intval($_POST['total_izin']);
    $total_cuti = intval($_POST['total_cuti']);
    $total_tanpa_keterangan = intval($_POST['total_tanpa_keterangan']);
    $total_sakit = intval($_POST['total_sakit']);
    
    $stmtCheck = $conn->prepare("SELECT id FROM rekap_absensi WHERE id_anggota = ? AND bulan = ? AND tahun = ? LIMIT 1");
    $stmtCheck->bind_param("iii", $id_anggota, $bulan, $tahun);
    if(!$stmtCheck->execute()){
        send_response(1, 'Gagal mengecek rekap absensi.');
    }
    $result = $stmtCheck->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $id = $row['id'];
        $stmtUpdate = $conn->prepare("UPDATE rekap_absensi SET total_hadir = ?, total_izin = ?, total_cuti = ?, total_tanpa_keterangan = ?, total_sakit = ? WHERE id = ?");
        $stmtUpdate->bind_param("iiiiii", $total_hadir, $total_izin, $total_cuti, $total_tanpa_keterangan, $total_sakit, $id);
        if ($stmtUpdate->execute()){
            send_response(0, 'Rekap absensi berhasil diperbarui.');
        } else {
            send_response(1, 'Gagal memperbarui rekap absensi.');
        }
        $stmtUpdate->close();
    } else {
        $stmtInsert = $conn->prepare("INSERT INTO rekap_absensi (id_anggota, bulan, tahun, total_hadir, total_izin, total_cuti, total_tanpa_keterangan, total_sakit) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtInsert->bind_param("iiiiiiii", $id_anggota, $bulan, $tahun, $total_hadir, $total_izin, $total_cuti, $total_tanpa_keterangan, $total_sakit);
        if ($stmtInsert->execute()){
            send_response(0, 'Rekap absensi berhasil disimpan.');
        } else {
            send_response(1, 'Gagal menyimpan rekap absensi.');
        }
        $stmtInsert->close();
    }
    $stmtCheck->close();
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
        send_response(1, 'Query gagal.');
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
 * MODIFIKASI: Tambahan komentar untuk peringatan performa subquery bila data sudah besar.
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

    // Catatan: Subquery di sini bisa menimbulkan masalah performa bila data sangat besar.
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
        send_response(1, 'Prepare failed.');
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        error_log("DEBUG: Execute failed in LoadingEmployees: " . $stmt->error);
        send_response(1, 'Execute failed.');
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
          <a class="dropdown-item btnRekapAbsensi" href="javascript:void(0)" data-id="' . $row['id'] . '">
              <i class="bi bi-calendar-check"></i> Rekap Absensi
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
    $no_rekening = isset($_POST['no_rekening']) ? trim(sanitize_input($_POST['no_rekening'])) : '';
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
        send_response(1, 'Prepare failed.');
    }
    $stmtUpdate = $conn->prepare("UPDATE anggota_sekolah SET no_rekening = ? WHERE id = ?");
    if (!$stmtUpdate) {
        error_log("DEBUG: EditEmployee: Prepare failed (update): " . $conn->error);
        send_response(1, 'Prepare failed.');
    }
    $stmtUpdate->bind_param("si", $no_rekening, $id);
    if ($stmtUpdate->execute()){
        error_log("DEBUG: EditEmployee: Update successful for ID $id");
        send_response(0, 'No Rekening anggota berhasil diperbarui.');
    } else {
        error_log("DEBUG: EditEmployee: Update failed: " . $stmtUpdate->error);
        send_response(1, 'Gagal memperbarui No Rekening.');
    }
    $stmtUpdate->close();
}


/**
 * Fungsi AssignPayheadsToEmployee: Menetapkan payheads kepada anggota.
 * MODIFIKASI: Tambahan validasi file upload berdasarkan ekstensi dan MIME type.
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
        // Daftar ekstensi yang diizinkan
        $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $uploadDir = __DIR__ . '/../../uploads/payhead_support/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileTmpPath = $_FILES['support_doc']['tmp_name'];
        $fileName    = basename($_FILES['support_doc']['name']);
        $fileExt     = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        // Validasi ekstensi file
        if (!in_array($fileExt, $allowedExtensions)) {
            send_response(1, 'Tipe file tidak diizinkan.');
        }
        // Validasi MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileTmpPath);
        finfo_close($finfo);
        $allowedMimes = [
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png'
        ];
        if (!in_array($mimeType, $allowedMimes)) {
            send_response(1, 'MIME type file tidak diizinkan.');
        }
        $newFileName = uniqid('doc_', true) . '.' . $fileExt;
        $destPath    = $uploadDir . $newFileName;
        if (!move_uploaded_file($fileTmpPath, $destPath)) {
            send_response(1, 'Gagal mengunggah dokumen pendukung.');
        }
        $supportDocPath = '/payroll_absensi_v2/uploads/payhead_support/' . $newFileName;
    }
    $conn->begin_transaction();
    try {
        // Catatan: Penghapusan seluruh record payheads lama dilakukan di sini; pastikan sesuai dengan logika bisnis
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
        send_response(1, 'Prepare failed.');
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        error_log("DEBUG: ViewEmployeeDetail: Execute failed: " . $stmt->error);
        send_response(1, 'Execute failed.');
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
            send_response(1, 'Prepare failed.');
        }
        $stmtPH->bind_param("i", $id);
        if (!$stmtPH->execute()) {
            error_log("DEBUG: ViewEmployeeDetail: Execute failed (payheads): " . $stmtPH->error);
            send_response(1, 'Execute failed.');
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
        // Konsisten: gaji bersih dihitung dengan cara yang sama (gaji pokok + salary index + earnings - deductions)
        $gajiBersihVal = $gajiPokokVal + $levelIndexVal + $totalPendapatan - $totalPotongan;
        error_log("DEBUG: ViewEmployeeDetail: Employee $id: gajiBersih = $gajiBersihVal");
        
        $user_nip = $_SESSION['nip'] ?? '';
        $details_log = "Melihat detail anggota dengan NIP $user_nip, ID: $id.";
        try {
            add_audit_log($conn, $user_nip, 'ViewEmployeeDetail', $details_log);
        } catch (Exception $e) {
            error_log("DEBUG: Audit log error: " . $e->getMessage());
        }
        
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
        send_response(1, 'Prepare failed.');
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        error_log("DEBUG: GetPayheadById: Execute failed: " . $stmt->error);
        send_response(1, 'Execute failed.');
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

// --- Handler untuk Request AJAX ---
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    error_log("DEBUG: AJAX request received in employees.php");
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            case 'ViewRekapAbsensi':
                ViewRekapAbsensi($conn);
                break;
            case 'EditRekapAbsensi':
                EditRekapAbsensi($conn);
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

// --- Modal Select Month (global payroll) ---
// Ambil data processedMonths (hanya untuk menandai mana yang sudah final semua)
$processedMonths = [];
$resultTotal = $conn->query("SELECT COUNT(*) as total FROM anggota_sekolah");
$totalAnggota = ($resultTotal) ? intval($resultTotal->fetch_assoc()['total']) : 0;
$sql = "SELECT bulan, tahun, COUNT(DISTINCT id_anggota) as completed 
        FROM payroll 
        WHERE status = 'final' 
        GROUP BY bulan, tahun";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()){
        if (intval($row['completed']) === $totalAnggota) {
            $processedMonths[] = [
                'bulan' => intval($row['bulan']), 
                'tahun' => intval($row['tahun'])
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
        .btnEdit, .btnAssignPayheads, .btnViewDetail, .btnRekapAbsensi {
            transition: background-color 0.3s, transform 0.2s;
        }
        .btnEdit:hover, .btnAssignPayheads:hover, .btnViewDetail:hover, .btnRekapAbsensi:hover {
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
        .modal-content { overflow-x: auto; }
        .modal-body input,
        .modal-body select,
        .modal-body textarea { width: 100%; }
        #rekapAbsensiModal .modal-dialog { max-width: 700px; }
        #rekapReviewModal .modal-dialog { max-width: 700px; }
        #rekapReviewModal .modal-header { background-color: #0d6efd; color: #fff; padding: 15px; }
        #rekapReviewModal .modal-body { padding: 20px; font-size: 14px; color: #333; }
        #rekapReviewModal .data-display p { margin: 5px 0; }
        #rekapReviewModal .data-display strong { display: inline-block; width: 180px; }
        #rekapReviewModal .modal-footer { padding: 15px; border-top: 1px solid #dee2e6; }
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
                    <!-- Header untuk bulan yang dipilih (versi Query String) -->
                    <div id="selectedMonthDisplay" class="mb-3">
                        <div class="card mb-3">
                            <div class="card-body d-flex align-items-center">
                                <i class="bi bi-calendar3 me-2"></i>
                                <span class="fw-bold">
                                    Payroll Bulan: 
                                    <?= date('F', mktime(0, 0, 0, $selectedMonth, 1)) . ' ' . $selectedYear; ?>
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

    <!-- MODAL: Assign Payheads (Enhanced) -->
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
                <!-- Row 1: Informasi Payroll -->
                <div class="row mb-4">
                  <!-- Kolom Kiri: General Info -->
                  <div class="col-md-6">
                    <div class="card">
                      <div class="card-header bg-primary text-white">
                        Informasi Karyawan & Payroll
                      </div>
                      <div class="card-body">
                        <div class="mb-3">
                          <label>Nama Karyawan</label>
                          <input type="text" class="form-control" id="fieldNamaKaryawan" readonly>
                        </div>
                        <div class="mb-3">
                          <label>Periode</label>
                          <input type="text" class="form-control" id="fieldPeriode" readonly>
                        </div>
                        <div class="mb-3">
                          <label>Masa Kerja</label>
                          <input type="text" class="form-control" id="fieldMasaKerja" readonly>
                        </div>
                        <div class="mb-3">
                          <label>No. Rekening</label>
                          <input type="text" class="form-control" id="inputNoRek" value="">
                        </div>
                        <div class="mb-3">
                          <label>Tanggal Payroll</label>
                          <input type="datetime-local" class="form-control" id="inputTanggalPayroll" required>
                        </div>
                        <div class="mb-3">
                          <label>Catatan Payroll</label>
                          <textarea class="form-control" id="inputDescription" rows="3" placeholder="Tambahkan catatan..."></textarea>
                        </div>
                      </div>
                    </div>
                  </div>
                   <!-- Kolom Kanan: Perhitungan Payroll -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header bg-secondary text-white">
        Perhitungan Payroll
      </div>
      <div class="card-body">
        <!-- Kolom Level Indeks, Nominal Indeks, Gaji Pokok, dll -->
        <div class="mb-3">
          <label>Level Indeks</label>
          <input type="text" class="form-control" id="inputIndexLevel" readonly>
        </div>
        <div class="mb-3">
          <label>Nominal Indeks</label>
          <input type="text" class="form-control currency-input" id="inputIndexNominal" readonly>
        </div>
        <div class="mb-3">
          <label>Gaji Pokok</label>
          <input type="text" class="form-control currency-input" id="inputGajiPokok" readonly>
        </div>
        <div class="mb-3">
          <label>Total Pendapatan (Payheads)</label>
          <input type="text" class="form-control" id="inputTotalEarnings" readonly>
        </div>
        <div class="mb-3">
          <label>Total Potongan</label>
          <input type="text" class="form-control" id="inputTotalDeductions" readonly>
        </div>
        <div class="mb-3">
          <label>Estimasi Gaji Bersih</label>
          <input type="text" class="form-control" id="inputNetSalary" readonly>
        </div>
      </div>
    </div>
  </div>
</div> <!-- Pastikan row ini tertutup dengan benar -->

<!-- Row 2: Form Perubahan (Remarks & Dokumen Pendukung) -->
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
                <!-- Row 3: Pemilihan Payheads -->
                <div class="row">
  <!-- Kolom Kiri: Payheads Tersedia -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header bg-secondary text-white">Payheads Tersedia</div>
      <div class="card-body">
        <div class="form-group mb-2">
          <input type="text" id="searchAllPayheads" class="form-control" placeholder="Cari payheads...">
        </div>
        <!-- Ganti select multiple dengan div -->
        <div id="all_payheads_list" style="max-height: 250px; overflow-y: auto;"></div>
      </div>
    </div>
  </div>

  <!-- Kolom Tengah: Payheads Terpilih -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header bg-secondary text-white">Payheads Terpilih</div>
      <div class="card-body">
        <div class="form-group mb-2">
          <input type="text" id="searchSelectedPayheads" class="form-control" placeholder="Cari payheads...">
        </div>
        <!-- Ganti select multiple dengan div -->
        <div id="selected_payheads_list" style="max-height: 250px; overflow-y: auto;"></div>
      </div>
    </div>
  </div>

  <!-- Kolom Kanan: Table Nominal -->
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

    <!-- MODAL: Review Rekap Absensi (tidak dapat diedit) -->
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
                        <p><strong>Total Tanpa Keterangan:</strong> <span id="review_total_tk"></span></p>
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
                        // Buat grid 16 bulan (2 bulan ke belakang + 13 bulan ke depan)
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
                            // Cek apakah bulan ini sudah complete (semua final)
                            $highlightClass = 'bg-light';
                            foreach ($processedMonths as $pm) {
                                if ($pm['bulan'] == $month && $pm['tahun'] == $year) {
                                    $highlightClass = 'processed-month';
                                    break;
                                }
                            }
                            // Tambah highlight jika sama dengan $selectedMonth/$selectedYear
                            if ($month == $selectedMonth && $year == $selectedYear) {
                                $highlightClass = 'bg-warning text-dark fw-bold';
                            }
                            echo '<div class="col-3 mb-3">';
                            echo '  <div class="p-2 ' . $highlightClass . '" style="border: 1px solid #ddd; border-radius: 5px;">';
                            echo '    <a href="#" class="month-link" data-month-number="' . $month . '" 
                                                  data-month="' . date("F", mktime(0, 0, 0, $month, 1)) . '" 
                                                  data-year="' . $year . '" 
                                                  style="color: inherit; text-decoration: none;">';
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
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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

    // ================
    // 1. DataTable
    // ================
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

    // ==================================================
    // 2. Toast / Notifikasi
    // ==================================================
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

    // ==================================================
    // 3. AutoNumeric untuk format uang
    // ==================================================
    function initAutoNumeric() {
        AutoNumeric.multiple('.currency-input', {
            digitGroupSeparator: '.',
            decimalCharacter: ',',
            decimalPlaces: 2,
            unformatOnSubmit: true
        });
    }
    initAutoNumeric();

    // ==================================================
    // 4. Bagian Pemilihan Payheads (Tersedia & Terpilih)
    //    Menggunakan Tombol + / - di SEBELAH KIRI item
    // ==================================================

    // a) Fungsi menampilkan payheads BELUM dipilih
    function loadAvailablePayheads(availablePayheads) {
        $('#all_payheads_list').empty();

        availablePayheads.forEach(function(ph) {
            let labelText = ph.nama_payhead + ' (' + ph.jenis_payhead_idn + ')';
            // Buat item payhead (dengan tombol + di kiri)
            let item = $(`
                <div class="payhead-item d-flex align-items-center mb-1"
                     data-id="${ph.id}"
                     data-nominal="${ph.nominal}"
                     data-type="${ph.jenis_payhead}">
                  <button type="button" class="btn btn-sm btn-primary btn-success btnAddPayhead me-2 rounded-circle">
                    <i class="bi bi-plus"></i>
                  </button>
                  <span class="payhead-name ${ph.jenis_payhead === 'earnings' ? 'text-success' : 'text-danger'}">
                    ${labelText}
                  </span>
                </div>
            `);
            $('#all_payheads_list').append(item);
        });
    }

    // b) Fungsi menampilkan payheads SUDAH dipilih
    function loadSelectedPayheads(assignedPayheads) {
        $('#selected_payheads_list').empty();

        assignedPayheads.forEach(function(ap) {
            let labelText = ap.nama_payhead + ' (' + ap.jenis_payhead_idn + ')';
            let item = $(`
                <div class="payhead-item d-flex align-items-center mb-1"
                     data-id="${ap.id_payhead}"
                     data-nominal="${ap.amount}"
                     data-type="${ap.jenis_payhead}">
                  <button type="button" class="btn btn-sm btn-danger btnRemovePayhead me-2 rounded-circle">
                    <i class="bi bi-dash"></i>
                  </button>
                  <span class="payhead-name ${ap.jenis_payhead === 'earnings' ? 'text-success' : 'text-danger'}">
                    ${labelText}
                  </span>
                </div>
            `);
            $('#selected_payheads_list').append(item);
        });

        // Setelah memuat payheads terpilih, perbarui tabel nominal
        updateSelectedPayamountTable();
    }

    // c) Event delegasi untuk tombol + (Add)
    $(document).on('click', '.btnAddPayhead', function() {
        let item = $(this).closest('.payhead-item');
        // Pindahkan item ke #selected_payheads_list
        $(this)
            .removeClass('btnAddPayhead btn-primary')
            .addClass('btnRemovePayhead btn-danger')
            .html('<i class="bi bi-dash"></i>');

        item.remove();
        $('#selected_payheads_list').append(item);
        updateSelectedPayamountTable();
    });

    // d) Event delegasi untuk tombol - (Remove)
    $(document).on('click', '.btnRemovePayhead', function() {
        let item = $(this).closest('.payhead-item');
        // Pindahkan item ke #all_payheads_list
        $(this)
            .removeClass('btnRemovePayhead btn-danger')
            .addClass('btnAddPayhead btn-primary')
            .html('<i class="bi bi-plus"></i>');

        item.remove();
        $('#all_payheads_list').append(item);
        updateSelectedPayamountTable();
    });

    // e) Update tabel nominal (bagian kanan) berdasarkan #selected_payheads_list
    function updateSelectedPayamountTable() {
        let tbody = $("#selected_payamount_table tbody");
        tbody.empty();

        $("#selected_payheads_list .payhead-item").each(function(index) {
            let payheadId   = $(this).data('id');
            let payheadName = $(this).find('.payhead-name').text();
            let defaultAmt  = $(this).data('nominal') || "0";
            let payType     = $(this).data('type') || "";

            let row = `
                <tr id="payhead-row-${payheadId}" data-type="${payType}">
                  <td>${index + 1}</td>
                  <td>${payheadName}</td>
                  <td>
                    <input type="text" name="pay_amounts[${payheadId}]"
                           class="form-control currency-input"
                           value="${defaultAmt}" required>
                  </td>
                </tr>
            `;
            tbody.append(row);
        });

        // Re-init AutoNumeric
        AutoNumeric.multiple('.currency-input', {
            digitGroupSeparator: '.',
            decimalCharacter: ',',
            decimalPlaces: 2,
            unformatOnSubmit: true
        });

        // Kalkulasi total
        recalcPayheadsTotals();
    }

    // f) Fungsi menghitung total pendapatan, potongan, dsb
    function recalcPayheadsTotals() {
        let totalEarnings = 0;
        let totalDeductions = 0;

        $("#selected_payamount_table tbody tr").each(function() {
            let type = ($(this).data("type") || "").toLowerCase();
            let val  = $(this).find("input.currency-input").val();
            let amount = parseFloat(val.replace(/\./g, '').replace(',', '.')) || 0;

            if (type === "earnings") {
                totalEarnings += amount;
            } else if (type === "deduction" || type === "deductions" || type === "potongan") {
                totalDeductions += amount;
            }
        });

        function formatNumber(num) {
            return num.toLocaleString('id-ID', { minimumFractionDigits: 2 });
        }

        $("#inputTotalEarnings").val("Rp " + formatNumber(totalEarnings));
        $("#inputTotalDeductions").val("Rp " + formatNumber(totalDeductions));

        // Ambil gaji pokok
        let gajiPokokText = $("#inputGajiPokok").val().replace(/[Rp\s.]/g, '').replace(',', '.');
        let gajiPokok = parseFloat(gajiPokokText) || 0;

        // Ambil nominal indeks
        let indexNominalText = $("#inputIndexNominal").val().replace(/[Rp\s.]/g, '').replace(',', '.');
        let indexNominal = parseFloat(indexNominalText) || 0;

        // Gaji bersih
        let netSalary = gajiPokok + indexNominal + totalEarnings - totalDeductions;
        $("#inputNetSalary").val("Rp " + formatNumber(netSalary));
    }

    // Pastikan jika user input nominal, total di-recalc
    $("#selected_payamount_table").on("input", "input.currency-input", function() {
        recalcPayheadsTotals();
    });


    // ==================================================
    // 5. Inisialisasi tampilan bulan payroll (header)
    // ==================================================
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

    // Klik "Ganti Kalender"
    $('#selectedMonthDisplay').on('click', '#btnChangeCalendar, h4', function(){
        $('#SalaryMonthModal').modal('show');
    });

    // Filter
    $('#btnApplyFilter').on('click', function(){
        $('#employees').DataTable().ajax.reload();
    });
    $('#btnResetFilter').on('click', function(){
        $('#filterForm')[0].reset();
        $('#employees').DataTable().ajax.reload();
    });

    // ==================================================
    // 6. Edit Employee (No Rekening)
    // ==================================================
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

    // ==================================================
    // 7. View Detail Anggota
    // ==================================================
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

                    $('#detailTotalPendapatan').text(
                        'Rp ' + parseFloat(e.total_pendapatan).toLocaleString('id-ID',{minimumFractionDigits:2})
                    );
                    $('#detailTotalPotongan').text(
                        'Rp ' + parseFloat(e.total_potongan).toLocaleString('id-ID',{minimumFractionDigits:2})
                    );
                    $('#detailGajiBersih').text(
                        'Rp ' + parseFloat(e.gaji_bersih).toLocaleString('id-ID',{minimumFractionDigits:2})
                    );

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

    // ==================================================
    // 8. Rekap Absensi
    // ==================================================
    $('#employees tbody').on('click', '.btnRekapAbsensi', function(){
        var id = $(this).data('id');
        var selectedMonth = localStorage.getItem('selectedMonthNumber');
        var selectedYear = localStorage.getItem('selectedYearPayroll');
        $.ajax({
            url: 'employees.php?ajax=1',
            type: 'POST',
            dataType: 'json',
            data: { 
                case: 'ViewRekapAbsensi', 
                id: id, 
                selectedMonth: selectedMonth, 
                selectedYear: selectedYear, 
                csrf_token: '<?= htmlspecialchars($csrf_token); ?>' 
            },
            success: function(resp) {
                if(resp.code === 0) {
                    var data = resp.result;
                    $('#review_id_anggota').text(data.id_anggota);
                    var bulanNames = {
                        1:'Januari',2:'Februari',3:'Maret',4:'April',5:'Mei',
                        6:'Juni',7:'Juli',8:'Agustus',9:'September',10:'Oktober',
                        11:'November',12:'Desember'
                    };
                    $('#review_bulan').text(bulanNames[data.bulan] || data.bulan);
                    $('#review_tahun').text(data.tahun);
                    $('#review_total_hadir').text(data.total_hadir);
                    $('#review_total_izin').text(data.total_izin);
                    $('#review_total_cuti').text(data.total_cuti);
                    $('#review_total_tk').text(data.total_tanpa_keterangan);
                    $('#review_total_sakit').text(data.total_sakit);

                    // Isi form edit rekap
                    $('#rekap_id_anggota_edit').val(data.id_anggota);
                    $('#rekap_bulan_edit').val(data.bulan);
                    $('#rekap_tahun_edit').val(data.tahun);
                    $('#total_hadir_edit').val(data.total_hadir);
                    $('#total_izin_edit').val(data.total_izin);
                    $('#total_cuti_edit').val(data.total_cuti);
                    $('#total_tanpa_keterangan_edit').val(data.total_tanpa_keterangan);
                    $('#total_sakit_edit').val(data.total_sakit);

                    $('#rekapReviewModal').modal('show');
                } else {
                    showToast(resp.result, 'error');
                }
            },
            error: function() {
                showToast('Terjadi kesalahan saat memuat data rekap absensi.', 'error');
            }
        });
    });
    $('#btnOpenEditRekap').on('click', function(){
        $('#rekapReviewModal').modal('hide');
        $('#rekapAbsensiModal').modal('show');
    });
    $('#rekapAbsensiForm').on('submit', function(e){
        e.preventDefault();
        var form = $(this);
        $.ajax({
            url: 'employees.php?ajax=1',
            type: 'POST',
            dataType: 'json',
            data: form.serialize(),
            beforeSend: function(){
                form.find('button[type="submit"]').prop('disabled', true);
                form.find('.spinner-border').removeClass('d-none');
            },
            success: function(resp){
                form.find('button[type="submit"]').prop('disabled', false);
                form.find('.spinner-border').addClass('d-none');
                if(resp.code === 0){
                    showToast(resp.result, 'success');
                    $('#rekapAbsensiModal').modal('hide');
                } else {
                    showToast(resp.result, 'error');
                }
            },
            error: function(){
                form.find('button[type="submit"]').prop('disabled', false);
                form.find('.spinner-border').addClass('d-none');
                showToast('Terjadi kesalahan saat menyimpan rekap absensi.', 'error');
            }
        });
    });

    // ==================================================
    // 9. Assign Payheads -> Modal
    // ==================================================
    $('#employees tbody').on('click', '.btnAssignPayheads', function(){
        var id = $(this).data('id');
        $('#empcode').val(id);

        // Kosongkan div list
        $('#all_payheads_list').empty();
        $('#selected_payheads_list').empty();
        $("#selected_payamount_table tbody").empty();

        // Ambil data detail karyawan (ViewEmployeeDetail)
        $.ajax({
            type: "POST",
            dataType: "json",
            url: 'employees.php?ajax=1',
            data: { case: 'ViewEmployeeDetail', id: id, csrf_token: '<?= htmlspecialchars($csrf_token); ?>' },
            success: function(result) {
                if(result.code === 0) {
                    var e = result.result;
                    // Isi field di modal
                    $('#fieldNamaKaryawan').val(e.nama);
                    var selMonth = localStorage.getItem('selectedMonthPayroll') || '';
                    var selYear = localStorage.getItem('selectedYearPayroll') || '';
                    $('#fieldPeriode').val(selMonth + " " + selYear);
                    $('#fieldMasaKerja').val(e.masa_kerja);
                    $('#inputIndexLevel').val(e.salary_index_level);
                    $('#inputNoRek').val(e.no_rekening);

                    var now = new Date();
                    $('#inputTanggalPayroll').val(now.toISOString().slice(0,16));
                    $('#inputDescription').val('');
                    $('#inputGajiPokok').val(e.gaji_pokok_val);

                    let indexBaseFormatted = e.salary_index_base.toLocaleString('id-ID', { minimumFractionDigits: 2 });
                    $('#inputIndexNominal').val(indexBaseFormatted);

                    $('#inputTotalEarnings').val('Rp 0,00');
                    $('#inputTotalDeductions').val('Rp 0,00');
                    $('#inputNetSalary').val('Rp ' + parseFloat(e.gaji_pokok_val).toLocaleString('id-ID',{minimumFractionDigits:2}));

                    // Ambil data semua payheads
                    $.ajax({
                        type: "POST",
                        dataType: "json",
                        url: 'employees.php?ajax=1',
                        data: { case: 'GetAllPayheads', csrf_token: '<?= htmlspecialchars($csrf_token); ?>' },
                        success: function(allPayheadsResult) {
                            if(allPayheadsResult.code === 0) {
                                var allPayheadsList = allPayheadsResult.result;
                                var assignedPayheads = e.payheads || [];
                                var assignedIds = assignedPayheads.map(function(ph){
                                    return parseInt(ph.id_payhead, 10);
                                });

                                // Filter payheads: mana yang belum dipilih
                                var availablePayheads = allPayheadsList.filter(function(ph) {
                                    return !assignedIds.includes(parseInt(ph.id, 10));
                                });

                                // Tampilkan "available" + "selected"
                                loadAvailablePayheads(availablePayheads);
                                loadSelectedPayheads(assignedPayheads);

                                // Tampilkan modal
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

    // Submit form assign payheads (hanya simpan draft)
    $('#assign-payhead-form').on('submit', function(e){
        e.preventDefault();
        var form = $(this);

        // Kumpulkan ID payhead terpilih
        var payHeads = [];
        $('#selected_payheads_list .payhead-item').each(function() {
            payHeads.push($(this).data('id'));
        });

        // Kumpulkan nominal dari table
        var payAmounts = {};
        payHeads.forEach(function(payheadId) {
            var inputSel = `input[name="pay_amounts[${payheadId}]"]`;
            var amount = $(inputSel).val();
            payAmounts[payheadId] = amount;
        });

        // Validasi nominal > 0
        var isValid = true;
        payHeads.forEach(function(payheadId){
            var amount = payAmounts[payheadId];
            var numericAmount = parseFloat(amount.replace(/\./g, '').replace(',', '.'));
            if(!amount || isNaN(numericAmount) || numericAmount <= 0){
                $(`input[name="pay_amounts[${payheadId}]"]`).addClass('is-invalid');
                isValid = false;
            } else {
                $(`input[name="pay_amounts[${payheadId}]"]`).removeClass('is-invalid');
            }
        });
        if(!isValid){
            showToast('Pastikan semua jumlah payhead valid (angka & > 0)!', 'error');
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
                        $('#all_payheads_list').empty();
                        $('#selected_payheads_list').empty();
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

    // Fungsi penyimpanan payheads ke draft + callback
    function savePayheads(callback) {
        var form = $('#assign-payhead-form');

        var payHeads = [];
        $('#selected_payheads_list .payhead-item').each(function() {
            payHeads.push($(this).data('id'));
        });

        var payAmounts = {};
        payHeads.forEach(function(payheadId) {
            var inputSel = `input[name="pay_amounts[${payheadId}]"]`;
            var amount = $(inputSel).val();
            payAmounts[payheadId] = amount;
        });

        // Validasi
        var isValid = true;
        payHeads.forEach(function(payheadId) {
            var amount = payAmounts[payheadId];
            var numericAmount = parseFloat(amount.replace(/\./g, '').replace(',', '.'));
            if(!amount || isNaN(numericAmount) || numericAmount <= 0){
                $(`input[name="pay_amounts[${payheadId}]"]`).addClass('is-invalid');
                isValid = false;
            } else {
                $(`input[name="pay_amounts[${payheadId}]"]`).removeClass('is-invalid');
            }
        });
        if(!isValid){
            showToast('Pastikan semua jumlah payhead valid (angka & > 0)!', 'error');
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
                        $('#all_payheads_list').empty();
                        $('#selected_payheads_list').empty();
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

    // ==================================================
    // 10. Tombol Proses Payroll (Final)
    // ==================================================
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
                // Jika payheads ada (selected), simpan dulu (draft), lalu final
                if ($('#selected_payheads_list .payhead-item').length > 0) {
                    savePayheads(function(){
                        callProcessPayroll(empcode, selectedMonth, selectedYear);
                    });
                } else {
                    // Tidak ada payheads terpilih? Tetap proses
                    callProcessPayroll(empcode, selectedMonth, selectedYear);
                }
            }
        });
    });

    // ==================================================
    // 11. Pemilihan Bulan (Calendar)
    // ==================================================
    $(document).on('click', '.month-link', function(e) {
        e.preventDefault();
        var monthNumber = $(this).data('month-number');
        var monthName   = $(this).data('month');
        var year        = $(this).data('year');

        // Jika punya logika khusus "currentEmpId"
        if (window.currentEmpId) {
            var employeeId = window.currentEmpId;
            var targetUrl = "/payroll_absensi_v2/payroll/keuangan/manage-salary.php";
            targetUrl += "?id_anggota=" + employeeId;
            targetUrl += "&bulan=" + encodeURIComponent(monthName);
            targetUrl += "&tahun=" + encodeURIComponent(year);
            window.location.href = targetUrl;
        } 
        else {
            // Cek apakah payroll sudah complete
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
                        } 
                        else {
                            // Sudah complete, langsung simpan
                            simpanPilihanBulan(monthNumber, monthName, year);
                        }
                    } 
                    else {
                        showToast(resp.result, 'error');
                    }
                },
                error: function(xhr, status, error){
                    showToast('Terjadi kesalahan saat mengecek payroll: ' + error, 'error');
                }
            });
        }
    });

    // Simpan pilihan bulan ke localStorage & reload
    function simpanPilihanBulan(monthNumber, monthName, year) {
        localStorage.setItem('selectedMonthPayroll', monthName);
        localStorage.setItem('selectedMonthNumber', monthNumber);
        localStorage.setItem('selectedYearPayroll', year);

        // Update tampilan header
        $('#selectedMonthDisplay').html(
            '<h4>Payroll Bulan: ' + monthName + ' ' + year +
            ' <button id="btnChangeCalendar" class="btn btn-link">Ganti Kalender</button></h4>'
        );
        $('#SalaryMonthModal').modal('hide');

        // Reload page agar param GET di URL ikut berubah (opsional)
        var newUrl = "employees.php?filterMonth=" + monthNumber + "&filterYear=" + year;
        window.location.href = newUrl;
    }
});
</script>

</body>
</html>