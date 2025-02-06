<?php
// File: /payroll_absensi_v2/payroll/keuangan/employees.php

// 1. Pengaturan keamanan: session cookie, forced HTTPS, HSTS, dll.
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => true,      // Hanya lewat HTTPS
    'httponly' => true,      // Tidak dapat diakses via JS
    'samesite' => 'Strict'
]);

require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
generate_csrf_token();

// Buat nonce untuk CSP dan simpan di session
$nonce = base64_encode(random_bytes(16));
$_SESSION['csp_nonce'] = $nonce;

// Paksa HTTPS jika belum menggunakan HTTPS
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirect);
    exit();
}

// HSTS header
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

// Atur Content-Security-Policy dengan nonce
header("Content-Security-Policy: default-src 'self'; 
    script-src 'self' https://code.jquery.com https://cdnjs.cloudflare.com https://cdn.datatables.net https://cdn.jsdelivr.net 'nonce-$nonce'; 
    style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'nonce-$nonce'; 
    img-src 'self'; 
    font-src 'self'; 
    connect-src 'self'");

// Koneksi ke database
require_once __DIR__ . '/../../koneksi.php';

// Fungsi pengecekan role (keuangan atau superadmin)
function authorize($conn) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['keuangan', 'superadmin'])) {
        send_response(403, 'Akses ditolak.');
    }
}

// === HANDLE AJAX REQUESTS ===
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    // Pastikan metode POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verifikasi token CSRF
        $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        verify_csrf_token($csrf_token);

        $case = isset($_POST['case']) ? bersihkan_input($_POST['case']) : '';
        switch ($case) {
            case 'LoadingEmployees':
                authorize($conn);
                LoadingEmployees($conn);
                break;
            case 'EditEmployee':
                authorize($conn);
                EditEmployee($conn);
                break;
            case 'AssignPayheadsToEmployee':
                authorize($conn);
                AssignPayheadsToEmployee($conn);
                break;
            case 'ViewEmployeeDetail':
                authorize($conn);
                ViewEmployeeDetail($conn);
                break;
            case 'GetPayheadById':
                authorize($conn);
                GetPayheadById($conn);
                break;
            case 'GetAllPayheads':
                authorize($conn);
                GetAllPayheads($conn);
                break;
            default:
                send_response(1, 'Kasus tidak valid.');
        }
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    $conn->close();
    exit();
}

/**
 * Fungsi untuk mengambil semua payheads dari database
 */
function GetAllPayheads($conn) {
    $sql = "SELECT id, nama_payhead, jenis FROM payheads ORDER BY nama_payhead ASC";
    $result = $conn->query($sql);
    if (!$result) {
        send_response(1, 'Query gagal: ' . $conn->error);
    }
    $payheads = [];
    while ($row = $result->fetch_assoc()) {
        $jenis_idn = translateJenis($row['jenis']);
        $payheads[] = [
            'id' => $row['id'],
            'nama_payhead' => htmlspecialchars($row['nama_payhead']),
            'jenis_payhead' => htmlspecialchars($row['jenis']),
            'jenis_payhead_idn' => htmlspecialchars($jenis_idn ?? 'Tidak Diketahui')
        ];
    }
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    add_audit_log($conn, $user_id, 'GetAllPayheads', 'Mengakses semua payheads.');
    send_response(0, $payheads);
}

/**
 * Fungsi untuk memuat data karyawan (server-side untuk DataTables)
 */
function LoadingEmployees($conn) {
    // Parameter DataTables
    $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = isset($_POST['search']['value']) ? bersihkan_input($_POST['search']['value']) : '';
    $jenjangFilter = isset($_POST['jenjang']) ? bersihkan_input($_POST['jenjang']) : '';

    // Buat string kondisi filter dan parameter untuk binding
    $where = " WHERE 1=1";
    $params = [];
    $types = "";
    if (!empty($jenjangFilter)) {
        $where .= " AND a.jenjang = ?";
        $params[] = $jenjangFilter;
        $types .= "s";
    }
    if (!empty($search)) {
        $where .= " AND (a.id LIKE ? OR a.uid LIKE ? OR a.nip LIKE ? OR a.nama LIKE ? OR a.jenjang LIKE ? OR a.job_title LIKE ? OR a.status_kerja LIKE ? OR a.no_rekening LIKE ? OR a.email LIKE ?)";
        $searchParam = "%" . $search . "%";
        for ($i = 0; $i < 9; $i++) {
            $params[] = $searchParam;
            $types .= "s";
        }
    }

    // Data query tanpa SQL_CALC_FOUND_ROWS
    $sql = "SELECT 
                a.id, a.uid, a.nip, a.nama, a.jenjang, a.job_title, a.status_kerja, a.role,
                a.masa_kerja_tahun, a.masa_kerja_bulan, a.gaji_pokok, a.no_rekening, a.email,
                si.level AS salary_index_level
            FROM anggota_sekolah a
            LEFT JOIN salary_indices si ON a.salary_index_id = si.id" . $where;
    // Tambahkan ordering dan limit
    $orderBy = " ORDER BY a.id DESC";
    if (isset($_POST['order'][0]['column']) && isset($_POST['columns'])) {
        $columnIndex = intval($_POST['order'][0]['column']);
        $allowedColumns = ['id', 'uid', 'nip', 'nama', 'jenjang', 'job_title', 'role', 'status_kerja', 'no_rekening', 'email'];
        if (isset($_POST['columns'][$columnIndex]['data']) && in_array($_POST['columns'][$columnIndex]['data'], $allowedColumns)) {
            $colName = $_POST['columns'][$columnIndex]['data'];
            $colSortOrder = ($_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
            $orderBy = " ORDER BY $colName $colSortOrder";
        }
    }
    $sqlData = $sql . $orderBy . " LIMIT ?, ?";
    $paramsData = $params;
    $typesData = $types . "ii";
    $paramsData[] = $start;
    $paramsData[] = $length;

    $stmt = $conn->prepare($sqlData);
    if ($stmt === false) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    if (!empty($paramsData)) {
        $stmt->bind_param($typesData, ...$paramsData);
    }
    if (!$stmt->execute()) {
        send_response(1, 'Execute failed: ' . $stmt->error);
    }
    $resData = $stmt->get_result();

    // Hitung total data setelah filter
    $sqlCount = "SELECT COUNT(*) as totalFiltered FROM anggota_sekolah a LEFT JOIN salary_indices si ON a.salary_index_id = si.id" . $where;
    $stmtCount = $conn->prepare($sqlCount);
    if ($stmtCount === false) {
        send_response(1, 'Prepare count failed: ' . $conn->error);
    }
    if (!empty($params)) {
        $stmtCount->bind_param($types, ...$params);
    }
    if (!$stmtCount->execute()) {
        send_response(1, 'Execute count failed: ' . $stmtCount->error);
    }
    $resultCount = $stmtCount->get_result();
    $rowCount = $resultCount->fetch_assoc();
    $totalFiltered = isset($rowCount['totalFiltered']) ? $rowCount['totalFiltered'] : 0;
    $stmtCount->close();

    // Total data tanpa filter (untuk DataTables recordsTotal)
    $resultTotal = $conn->query("SELECT COUNT(*) AS total FROM anggota_sekolah");
    $totalData = $resultTotal ? $resultTotal->fetch_assoc()['total'] : 0;

    $data = [];
    while ($row = $resData->fetch_assoc()) {
        // Format masa kerja
        $masaKerja = '';
        if ($row['masa_kerja_tahun'] > 0) {
            $masaKerja .= $row['masa_kerja_tahun'] . ' Tahun ';
        }
        if ($row['masa_kerja_bulan'] > 0) {
            $masaKerja .= $row['masa_kerja_bulan'] . ' Bulan';
        }
        $masaKerja = trim($masaKerja) ?: '-';
        // Format gaji pokok
        $gajiPokok = number_format($row['gaji_pokok'], 2, ',', '.');

        // Untuk tampilan tabel, hanya tampilkan level indeks saja (nominal di view detail)
        $levelIndeks = htmlspecialchars($row['salary_index_level'] ?? '-');

        // Kolom aksi: tombol dropdown dengan ikon tiga titik vertikal
        $aksi = '
<div class="dropdown">
  <button class="btn" type="button" id="dropdownMenuButton_' . htmlspecialchars($row['id']) . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <i class="bi bi-three-dots-vertical"></i>
  </button>
  <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_' . htmlspecialchars($row['id']) . '">
    <a class="dropdown-item btnEdit" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '">
      <i class="bi bi-pencil-square"></i> Edit
    </a>
    <a class="dropdown-item btnAssignPayheads" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '">
      <i class="bi bi-cash-stack"></i> Assign Payheads
    </a>
    <a class="dropdown-item btnViewDetail" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '">
      <i class="bi bi-eye-fill"></i> View Detail
    </a>
    <a class="dropdown-item btnSelectMonth" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '">
      <i class="fa fa-calendar"></i> Select Month
    </a>
  </div>
</div>';

        $data[] = [
            "id"           => htmlspecialchars($row['id']),
            "uid"          => htmlspecialchars($row['uid']),
            "nip"          => htmlspecialchars($row['nip']),
            "nama"         => htmlspecialchars($row['nama']),
            "jenjang"      => htmlspecialchars($row['jenjang']),
            "job_title"    => htmlspecialchars($row['job_title']),
            "role"         => htmlspecialchars($row['role']),
            "masa_kerja"   => $masaKerja,
            "gaji_pokok"   => $gajiPokok,
            "salary_index" => $levelIndeks,
            "no_rekening"  => htmlspecialchars($row['no_rekening']),
            "email"        => htmlspecialchars($row['email']),
            "aksi"         => $aksi
        ];
    }
    $stmt->close();
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $totalData,
        "recordsFiltered" => $totalFiltered,
        "data" => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Fungsi EditEmployee: hanya memperbarui no rekening karyawan
 */
function EditEmployee($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $no_rekening = isset($_POST['no_rekening']) ? bersihkan_input($_POST['no_rekening']) : '';
    if ($id <= 0 || empty($no_rekening)) {
        send_response(1, 'ID dan No Rekening wajib diisi.');
    }
    $stmtBefore = $conn->prepare("SELECT no_rekening FROM anggota_sekolah WHERE id = ? LIMIT 1");
    if ($stmtBefore) {
        $stmtBefore->bind_param("i", $id);
        $stmtBefore->execute();
        $resultBefore = $stmtBefore->get_result();
        $before = $resultBefore->fetch_assoc();
        $stmtBefore->close();
    } else {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmtUpdate = $conn->prepare("UPDATE anggota_sekolah SET no_rekening = ? WHERE id = ?");
    if ($stmtUpdate === false) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmtUpdate->bind_param("si", $no_rekening, $id);
    if ($stmtUpdate->execute()) {
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $details = "Memperbarui No Rekening karyawan ID $id dari '{$before['no_rekening']}' menjadi '$no_rekening'.";
        add_audit_log($conn, $user_id, 'EditEmployee', $details);
        send_response(0, 'No Rekening karyawan berhasil diperbarui.');
    } else {
        send_response(1, 'Gagal memperbarui No Rekening: ' . $stmtUpdate->error);
    }
    $stmtUpdate->close();
}

/**
 * Fungsi AssignPayheadsToEmployee: menetapkan payheads ke karyawan
 */
function AssignPayheadsToEmployee($conn) {
    $empcode = isset($_POST['empcode']) ? intval($_POST['empcode']) : 0;
    $payheads = isset($_POST['payheads']) ? $_POST['payheads'] : [];
    $pay_amounts = isset($_POST['pay_amounts']) ? $_POST['pay_amounts'] : [];
    if ($empcode <= 0) {
        send_response(1, 'ID karyawan tidak valid.');
    }
    if (empty($payheads)) {
        send_response(1, 'Tidak ada payheads yang dipilih.');
    }
    $conn->begin_transaction();
    try {
        $stmtDelete = $conn->prepare("DELETE FROM employee_payheads WHERE id_anggota = ?");
        if ($stmtDelete === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmtDelete->bind_param("i", $empcode);
        if (!$stmtDelete->execute()) {
            throw new Exception("Execute failed: " . $stmtDelete->error);
        }
        $stmtDelete->close();

        $stmtGetJenis = $conn->prepare("SELECT jenis FROM payheads WHERE id = ?");
        if ($stmtGetJenis === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmtInsert = $conn->prepare("INSERT INTO employee_payheads (id_anggota, id_payhead, jenis, amount) VALUES (?, ?, ?, ?)");
        if ($stmtInsert === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        foreach ($payheads as $payhead_id) {
            $payhead_id = intval($payhead_id);
            $nilai = isset($pay_amounts[$payhead_id]) ? floatval(str_replace(['.', ','], ['', '.'], $pay_amounts[$payhead_id])) : 0.0;
            if ($nilai < 0) {
                throw new Exception("Nilai payhead tidak boleh negatif.");
            }
            $stmtGetJenis->bind_param("i", $payhead_id);
            if (!$stmtGetJenis->execute()) {
                throw new Exception("Execute failed: " . $stmtGetJenis->error);
            }
            $resultJenis = $stmtGetJenis->get_result();
            if ($resultJenis->num_rows === 0) {
                throw new Exception("Payhead dengan ID $payhead_id tidak ditemukan.");
            }
            $rowJenis = $resultJenis->fetch_assoc();
            $jenis = $rowJenis['jenis'];
            $stmtInsert->bind_param("iisd", $empcode, $payhead_id, $jenis, $nilai);
            if (!$stmtInsert->execute()) {
                throw new Exception("Insert failed: " . $stmtInsert->error);
            }
        }
        $stmtGetJenis->close();
        $stmtInsert->close();
        $conn->commit();
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $details = "Menetapkan payheads ke karyawan ID $empcode. Payheads: " . implode(', ', $payheads) . ".";
        add_audit_log($conn, $user_id, 'AssignPayheadsToEmployee', $details);
        send_response(0, 'Payheads berhasil ditugaskan / diperbarui.');
    } catch (Exception $e) {
        $conn->rollback();
        send_response(1, 'Gagal menugaskan payheads: ' . $e->getMessage());
    }
}

/**
 * Fungsi ViewEmployeeDetail: menampilkan detail karyawan beserta payheads yang ditugaskan
 * Di modal view detail, selain data karyawan dan payheads, ditampilkan juga level indeks beserta nominalnya
 */
function ViewEmployeeDetail($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        send_response(1, 'ID karyawan tidak valid.');
    }
    $stmt = $conn->prepare("SELECT * FROM anggota_sekolah WHERE id = ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        send_response(1, 'Execute failed: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $emp = $res->fetch_assoc();
        $masaKerja = '';
        if ($emp['masa_kerja_tahun'] > 0) {
            $masaKerja .= $emp['masa_kerja_tahun'] . ' Tahun ';
        }
        if ($emp['masa_kerja_bulan'] > 0) {
            $masaKerja .= $emp['masa_kerja_bulan'] . ' Bulan';
        }
        $masaKerja = trim($masaKerja) ?: '-';
        $gajiPokokVal = floatval($emp['gaji_pokok']);

        $stmtPH = $conn->prepare("
            SELECT ep.id_payhead, ph.nama_payhead, ph.jenis AS jenis_payhead, ep.amount
            FROM employee_payheads ep
            JOIN payheads ph ON ep.id_payhead = ph.id
            WHERE ep.id_anggota = ?
        ");
        if ($stmtPH === false) {
            send_response(1, 'Prepare failed: ' . $conn->error);
        }
        $stmtPH->bind_param("i", $id);
        if (!$stmtPH->execute()) {
            send_response(1, 'Execute failed: ' . $stmtPH->error);
        }
        $resPH = $stmtPH->get_result();
        $assigned = [];
        $totalPendapatan = 0;
        $totalPotongan = 0;
        while ($rw = $resPH->fetch_assoc()) {
            $jenis_idn = translateJenis($rw['jenis_payhead']);
            $assigned[] = [
                'id_payhead' => $rw['id_payhead'],
                'nama_payhead' => $rw['nama_payhead'],
                'jenis_payhead' => $rw['jenis_payhead'],
                'jenis_payhead_idn' => htmlspecialchars($jenis_idn),
                'amount' => $rw['amount']
            ];
            if ($rw['jenis_payhead'] === 'earnings') {
                $totalPendapatan += $rw['amount'];
            } else {
                $totalPotongan += $rw['amount'];
            }
        }
        $stmtPH->close();

        // Ambil data level indeks dan nominalnya dari tabel salary_indices bila ada
        $levelIndeks = '-';
        $nominalLevel = '-';
        if (!empty($emp['salary_index_id'])) {
            $stmtLevel = $conn->prepare("SELECT level, base_salary FROM salary_indices WHERE id = ? LIMIT 1");
            if ($stmtLevel) {
                $stmtLevel->bind_param("i", $emp['salary_index_id']);
                $stmtLevel->execute();
                $resultLevel = $stmtLevel->get_result();
                if ($resultLevel->num_rows > 0) {
                    $rowLevel = $resultLevel->fetch_assoc();
                    $levelIndeks = htmlspecialchars($rowLevel['level']);
                    $nominalLevel = 'Rp ' . number_format($rowLevel['base_salary'], 2, ',', '.');
                }
                $stmtLevel->close();
            }
        }

        $emp['payheads'] = $assigned;
        $gajiBersihVal = $gajiPokokVal + $totalPendapatan - $totalPotongan;
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        add_audit_log($conn, $user_id, 'ViewEmployeeDetail', "Mengakses detail karyawan ID $id.");
        send_response(0, [
            'id' => htmlspecialchars($emp['id']),
            'uid' => htmlspecialchars($emp['uid']),
            'nip' => htmlspecialchars($emp['nip']),
            'nama' => htmlspecialchars($emp['nama']),
            'jenjang' => htmlspecialchars($emp['jenjang']),
            'job_title' => htmlspecialchars($emp['job_title']),
            'role' => htmlspecialchars($emp['role']),
            'status_kerja' => htmlspecialchars($emp['status_kerja']),
            'masa_kerja' => $masaKerja,
            'gaji_pokok_val' => $gajiPokokVal,
            'gaji_pokok' => 'Rp ' . number_format($gajiPokokVal, 2, ',', '.'),
            'no_rekening' => htmlspecialchars($emp['no_rekening']),
            'email' => htmlspecialchars($emp['email']),
            'jenis_kelamin' => htmlspecialchars($emp['jenis_kelamin']),
            'agama' => htmlspecialchars($emp['agama']),
            'masa_kerja_tahun' => $emp['masa_kerja_tahun'],
            'masa_kerja_bulan' => $emp['masa_kerja_bulan'],
            'payheads' => $assigned,
            'total_pendapatan' => $totalPendapatan,
            'total_potongan' => $totalPotongan,
            'gaji_bersih' => $gajiBersihVal,
            'level_indeks' => $levelIndeks,
            'nominal_level' => $nominalLevel
        ]);
    } else {
        send_response(1, 'Karyawan tidak ditemukan.');
    }
    $stmt->close();
}

/**
 * Fungsi GetPayheadById: mengambil detail payhead berdasarkan ID
 */
function GetPayheadById($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        send_response(1, 'ID payhead tidak valid.');
    }
    $stmt = $conn->prepare("SELECT id, nama_payhead, jenis AS jenis_payhead FROM payheads WHERE id = ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        send_response(1, 'Execute failed: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $payhead = $res->fetch_assoc();
        $payhead['jenis_payhead_idn'] = htmlspecialchars(translateJenis($payhead['jenis_payhead']));
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        add_audit_log($conn, $user_id, 'GetPayheadById', "Mengakses payhead ID $id.");
        send_response(0, $payhead);
    } else {
        send_response(1, 'Payhead tidak ditemukan.');
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Karyawan - Payroll System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- CSS Bootstrap dan SB Admin 2 dengan nonce -->
    <link rel="stylesheet" href="/payroll_absensi_v2/assets/css/bootstrap.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="/payroll_absensi_v2/assets/css/sb-admin-2.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.1.1/css/buttons.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <!-- Bootstrap Notify sudah dihapus karena memakai SweetAlert2 -->
    <style nonce="<?php echo $nonce; ?>">
        .btnEdit, .btnAssignPayheads, .btnViewDetail, .btnSelectMonth {
            transition: background-color 0.3s, transform 0.2s;
        }
        .btnEdit:hover, .btnAssignPayheads:hover, .btnViewDetail:hover, .btnSelectMonth:hover {
            transform: scale(1.05);
        }
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }
        .table-hover tbody tr:hover {
            background-color: #e2e6ea;
        }
        .aksi-column .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .aksi-column .btn:last-child {
            margin-right: 0;
        }
        #employees th, #employees td {
            font-size: 14px;
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }
        .spinner-border {
            margin-left: 5px;
        }
        .modal-body {
            display: flex;
            gap: 25px;
        }
        .modal-body > div {
            flex: 1;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #f9f9f9;
            padding: 10px;
        }
        #ManageModal .modal-dialog {
            max-width: 1000px;
            margin: auto;
            padding-top: 70px;
        }
    </style>
</head>
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fas fa-bars"></i>
                    </button>
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a href="/payroll_absensi_v2/logout.php" class="btn btn-danger btn-sm" title="Logout">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </nav>
                <!-- End of Topbar -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800 "><i class="bi bi-people-fill"></i> Manajemen Karyawan</h1>
                    <div id="alert-placeholder"></div>
                    <!-- Filter Section -->
                    <div class="card mb-4">
                        <div class="m-0 card-header font-weight-bold"> <i class="bi bi-filter-square-fill"></i> Filter Karyawan</div>
                        <div class="card-body">
                            <form id="filterForm" class="form-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <div class="form-group mb-2 mr-3">
                                    <label for="filterJenjang" class="mr-2">Jenjang Pendidikan:</label>
                                    <select class="form-control" id="filterJenjang" name="jenjang">
                                        <option value="">Semua Jenjang</option>
                                        <?php
                                            $stmtJenjang = $conn->prepare("SELECT DISTINCT jenjang FROM anggota_sekolah WHERE jenjang IS NOT NULL AND jenjang != '' ORDER BY jenjang ASC");
                                            if ($stmtJenjang) {
                                                $stmtJenjang->execute();
                                                $resJenjang = $stmtJenjang->get_result();
                                                while ($row = $resJenjang->fetch_assoc()) {
                                                    echo '<option value="' . htmlspecialchars($row['jenjang']) . '">' . htmlspecialchars($row['jenjang']) . '</option>';
                                                }
                                                $stmtJenjang->close();
                                            } else {
                                                echo '<option value="">Tidak ada data</option>';
                                            }
                                        ?>
                                    </select>
                                </div>
                                <button type="button" class="btn btn-primary mb-2 mr-2" id="btnApplyFilter">
                                    <i class="fas fa-filter"></i> Terapkan Filter
                                </button>
                                <button type="button" class="btn btn-secondary mb-2" id="btnResetFilter">
                                    <i class="fas fa-undo"></i> Reset Filter
                                </button>
                            </form>
                        </div>
                    </div>
                    <!-- Tabel Data Karyawan -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-clipboard-list"></i> Daftar Anggota Sekolah</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="employees" class="table table-sm table-bordered table-striped display nowrap" style="width:100%">
                                    <thead class="thead">
                                        <tr>
                                            <th>ID</th>
                                            <th>UID</th>
                                            <th>NIP</th>
                                            <th>Nama</th>
                                            <th>Jenjang</th>
                                            <th>Job Title</th>
                                            <th>Role</th>
                                            <th>Masa Kerja</th>
                                            <th>Gaji Pokok</th>
                                            <th>Level Indeks</th>
                                            <th>No Rekening</th>
                                            <th>Email</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody><!-- Data diisi oleh DataTables --></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?php echo date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    <!-- MODAL: Edit Karyawan -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="editEmployeeForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editEmployeeModalLabel"><i class="bi bi-pencil-square"></i> Update No Rekening Karyawan</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="case" value="EditEmployee">
                        <input type="hidden" name="id" id="editEmployeeId">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="container-fluid">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="editNip">NIP <span class="text-danger">*</span></label>
                                        <input type="text" name="nip" id="editNip" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="editNama">Nama <span class="text-danger">*</span></label>
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
                                            <option value="">---Pilih Jenis Kelamin---</option>
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
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- MODAL: Assign Payheads -->
    <div class="modal fade" id="ManageModal" tabindex="-1" aria-labelledby="manageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title text-center" id="manageModalLabel">Tetapkan Payheads ke Karyawan</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <form id="assign-payhead-form">
                    <div class="modal-body">
                        <input type="hidden" name="case" value="AssignPayheadsToEmployee">
                        <input type="hidden" name="empcode" id="empcode" />
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="select-payheads">
                            <label><strong>Payheads Tersedia:</strong></label>
                            <input type="text" id="searchAllPayheads" class="form-control mb-2" placeholder="Cari Payheads Tersedia...">
                            <button type="button" id="selectHeads" class="btn btn-success btn-sm mb-2">
                                <i class="fa fa-arrow-circle-right"></i> Tetapkan
                            </button>
                            <select id="all_payheads" class="form-control" multiple size="10"></select>
                        </div>
                        <div class="selected-payheads">
                            <label><strong>Payheads Terpilih:</strong></label>
                            <input type="text" id="searchSelectedPayheads" class="form-control mb-2" placeholder="Cari Payheads Terpilih...">
                            <button type="button" id="removeHeads" class="btn btn-danger btn-sm mb-2">
                                <i class="fa fa-arrow-circle-left"></i> Hapus
                            </button>
                            <select id="selected_payheads" class="form-control" multiple size="10"></select>
                        </div>
                        <div class="assign-amounts">
                            <label><strong>Tetapkan Jumlah:</strong></label>
                            <div id="selected_payamount"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check-circle"></i> Tetapkan Payheads
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times-circle"></i> Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- MODAL: View Detail Karyawan -->
    <div class="modal fade" id="viewDetailModal" tabindex="-1" aria-labelledby="viewDetailModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="viewDetailModalLabel"><i class="bi bi-eye-fill"></i> Detail Karyawan</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span>&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <table class="table table-bordered">
              <tr><th>ID</th><td id="detailId"></td></tr>
              <tr><th>UID</th><td id="detailUid"></td></tr>
              <tr><th>NIP</th><td id="detailNip"></td></tr>
              <tr><th>Nama</th><td id="detailNama"></td></tr>
              <tr><th>Jenjang Pendidikan</th><td id="detailJenjang"></td></tr>
              <tr><th>Job Title</th><td id="detailJobTitle"></td></tr>
              <tr><th>Role</th><td id="detailRole"></td></tr>
              <tr><th>Status Kerja</th><td id="detailStatusKerja"></td></tr>
              <tr><th>Masa Kerja</th><td id="detailMasaKerja"></td></tr>
              <tr><th>No Rekening</th><td id="detailNoRekening"></td></tr>
              <tr><th>Email</th><td id="detailEmail"></td></tr>
              <!-- Di modal view detail, tampilkan juga level indeks beserta nominalnya -->
              <tr><th>Level Indeks</th><td id="detailLevelIndeks"></td></tr>
              <tr><th>Nominal Level</th><td id="detailNominalLevel"></td></tr>
              <tr><th>Payheads</th><td id="detailPayheads"></td></tr>
              <tr><th>Gaji Pokok</th><td id="detailGajiPokok"></td></tr>
              <tr><th>Total Pendapatan</th><td id="detailTotalPendapatan"></td></tr>
              <tr><th>Total Potongan</th><td id="detailTotalPotongan"></td></tr>
              <tr><th>Gaji Bersih</th><td id="detailGajiBersih"></td></tr>
            </table>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-dismiss="modal">
              <i class="bi bi-x-circle"></i> Tutup
            </button>
          </div>
        </div>
      </div>
    </div>
    <!-- MODAL: Select Month -->
    <div class="modal fade" id="SalaryMonthModal" tabindex="-1" aria-labelledby="salaryMonthModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-md" style="max-width: 600px;">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="salaryMonthModalLabel">
              <i class="fa fa-calendar"></i> Pilih Bulan untuk Gaji
            </h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span>&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="row text-center">
              <?php
              $months = [];
              $years  = [];
              $currentYear = date('Y');
              $currentMonth = date('n');
              $before2Month = $currentMonth - 2;
              $before2Year = $currentYear;
              for ($i = 0; $i < 16; $i++) {
                  $month = $before2Month + $i;
                  $year = $before2Year;
                  if ($month <= 0) {
                      $month += 12;
                      $year -= 1;
                  } elseif ($month > 12) {
                      $month -= 12;
                      $year += 1;
                  }
                  $m = date('F', mktime(0, 0, 0, $month, 1));
                  $y = $year;
                  $months[] = $m;
                  $years[] = $y;
              }
              for ($i = 0; $i < 16; $i++) {
                  $monthName = $months[$i];
                  $yearName = $years[$i];
                  $highlightClass = ($monthName == date('F') && $yearName == date('Y')) ? 'bg-warning font-weight-bold' : '';
                  echo '<div class="col-sm-3 mb-3">';
                  echo '  <div class="' . $highlightClass . '" style="padding:10px; border:1px solid #ddd; border-radius:5px;">';
                  echo '    <a href="#" class="month-link" data-month="' . htmlspecialchars($monthName) . '" data-year="' . htmlspecialchars($yearName) . '" style="color:#333; text-decoration:none;">';
                  echo '      ' . strtoupper(htmlspecialchars($monthName)) . '<br>' . htmlspecialchars($yearName);
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
    <!-- JS Dependencies dengan nonce -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/dataTables.buttons.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.html5.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.print.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.colVis.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
    <!-- Bootstrap Notify dihapus -->
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/js/sb-admin-2.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/autonumeric@4.6.0/dist/autoNumeric.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" nonce="<?php echo $nonce; ?>"></script>
    <script nonce="<?php echo $nonce; ?>">
    $(document).ready(function() {
        // Inisialisasi AutoNumeric untuk input dengan class .currency-input
        function initAutoNumeric() {
            AutoNumeric.multiple('.currency-input', {
                digitGroupSeparator: '.',
                decimalCharacter: ',',
                decimalPlaces: 2,
                unformatOnSubmit: true
            });
        }
        initAutoNumeric();

        // SweetAlert2 Toast Mixin
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

        var csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

        var empTable = $('#employees').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: 'employees.php?ajax=1',
                type: 'POST',
                data: function(d) {
                    d.case = 'LoadingEmployees';
                    d.jenjang = $('#filterJenjang').val();
                    d.csrf_token = csrfToken;
                },
                error: function(xhr, error, thrown) {
                    showToast('Terjadi kesalahan saat memuat data karyawan.', 'error');
                }
            },
            columns: [
                { data: 'id' },
                { data: 'uid' },
                { data: 'nip' },
                { data: 'nama' },
                { data: 'jenjang' },
                { data: 'job_title' },
                { data: 'role' },
                { data: 'masa_kerja' },
                { data: 'gaji_pokok' },
                { data: 'salary_index' },
                { data: 'no_rekening' },
                { data: 'email' },
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
            autoWidth: false,
            columnDefs: [{ orderable: false, targets: -1 }]
        });

        $('#btnApplyFilter').on('click', function(){
            $.ajax({
                url: 'employees.php?ajax=1',
                type: 'POST',
                data: {
                    case: 'AddAuditLog',
                    csrf_token: csrfToken,
                    action: 'ApplyFilter',
                    details: `Pengguna menerapkan filter Jenjang: ${$('#filterJenjang').val() || 'Semua'}.`
                },
                success: function(response){
                    if(response.code === 0){
                        showToast('Filter berhasil diterapkan.', 'success');
                    }
                },
                error: function(){
                    showToast('Terjadi kesalahan saat mencatat audit log.', 'warning');
                }
            });
            empTable.ajax.reload();
        });

        $('#btnResetFilter').on('click', function(){
            $.ajax({
                url: 'employees.php?ajax=1',
                type: 'POST',
                data: {
                    case: 'AddAuditLog',
                    csrf_token: csrfToken,
                    action: 'ResetFilter',
                    details: 'Pengguna mereset filter karyawan.'
                },
                success: function(response){
                    if(response.code === 0){
                        showToast('Filter berhasil direset.', 'success');
                    }
                },
                error: function(){
                    showToast('Terjadi kesalahan saat mencatat audit log.', 'warning');
                }
            });
            $('#filterForm')[0].reset();
            empTable.ajax.reload();
        });

        $('#employees tbody').on('click', '.btnEdit', function() {
            var id = $(this).data('id');
            $.ajax({
                url: 'employees.php?ajax=1',
                type: 'POST',
                data: { 
                    case: 'ViewEmployeeDetail', 
                    id: id, 
                    csrf_token: csrfToken 
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
                    showToast('Terjadi kesalahan saat memuat data karyawan.', 'error');
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
                csrf_token: csrfToken
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
                        empTable.ajax.reload(null, false);
                        $('#editEmployeeModal').modal('hide');
                    } else {
                        showToast(resp.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat memperbarui data karyawan.', 'error');
                }
            });
        });

        $('#employees tbody').on('click', '.btnViewDetail', function(){
            var id = $(this).data('id');
            $.ajax({
                url:'employees.php?ajax=1',
                type:'POST',
                data:{ 
                    case: 'ViewEmployeeDetail', 
                    id: id,
                    csrf_token: csrfToken
                },
                dataType:'json',
                success:function(resp){
                    if(resp.code === 0){
                        var e = resp.result;
                        $('#detailId').text(e.id);
                        $('#detailUid').text(e.uid);
                        $('#detailNip').text(e.nip);
                        $('#detailNama').text(e.nama);
                        $('#detailJenjang').text(e.jenjang);
                        $('#detailJobTitle').text(e.job_title);
                        $('#detailRole').text(e.role);
                        $('#detailStatusKerja').text(e.status_kerja);
                        $('#detailMasaKerja').text(e.masa_kerja);
                        $('#detailNoRekening').text(e.no_rekening);
                        $('#detailEmail').text(e.email);
                        // Di modal view detail, tampilkan juga level indeks dan nominalnya
                        $('#detailLevelIndeks').text(e.level_indeks);
                        $('#detailNominalLevel').text(e.nominal_level);
                        if(e.payheads && e.payheads.length > 0){
                            var s = '<ul>';
                            e.payheads.forEach(function(ph){
                                var nominal = parseFloat(ph.amount).toLocaleString('id-ID',{minimumFractionDigits:2});
                                var jns = (ph.jenis_payhead === 'earnings') ? 'Pendapatan' : 'Potongan';
                                var clr = (ph.jenis_payhead==='earnings') ? 'green' : 'red';
                                s += '<li style="color:'+clr+'">'+ ph.nama_payhead +' ('+jns+'): Rp '+nominal+'</li>';
                            });
                            s += '</ul>';
                            $('#detailPayheads').html(s);
                        } else {
                            $('#detailPayheads').html('<i>Tidak ada payheads</i>');
                        }
                        $('#detailGajiPokok').text(e.gaji_pokok);
                        $('#detailTotalPendapatan').text('Rp ' + parseFloat(e.total_pendapatan).toLocaleString('id-ID',{minimumFractionDigits:2}));
                        $('#detailTotalPotongan').text('Rp ' + parseFloat(e.total_potongan).toLocaleString('id-ID',{minimumFractionDigits:2}));
                        $('#detailGajiBersih').text('Rp ' + parseFloat(e.gaji_bersih).toLocaleString('id-ID',{minimumFractionDigits:2}));
                        $('#viewDetailModal').modal('show');
                    } else {
                        showToast(resp.result, 'error');
                    }
                },
                error:function(){
                    showToast('Terjadi kesalahan saat mengambil detail karyawan.', 'error');
                }
            });
        });

        $('#employees tbody').on('click', '.btnAssignPayheads', function(){
            var id = $(this).data('id');
            $('#empcode').val(id);
            $('#all_payheads').empty();
            $('#selected_payheads').empty();
            $('#selected_payamount').empty();
            $.ajax({
                type: "POST",
                dataType: "json",
                url: 'employees.php?ajax=1',
                data: { 
                    case: 'ViewEmployeeDetail', 
                    id: id,
                    csrf_token: csrfToken
                },
                success: function(result) {
                    if(result.code === 0) {
                        var assignedPayheads = result.result.payheads; 
                        $.ajax({
                            type: "POST",
                            dataType: "json",
                            url: 'employees.php?ajax=1',
                            data: { 
                                case: 'GetAllPayheads',
                                csrf_token: csrfToken
                            },
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
                                            .text(optionText)
                                            .addClass(ph.jenis_payhead === 'earnings' ? 'text-success' : 'text-danger');
                                        $('#all_payheads').append(option);
                                    });
                                    assignedPayheads.forEach(function(ap){
                                        var payheadId = ap.id_payhead;
                                        var payheadNameTranslated = ap.nama_payhead + ' (' + ap.jenis_payhead_idn + ')';
                                        var option = $("<option></option>")
                                            .attr("value", payheadId)
                                            .text(payheadNameTranslated)
                                            .addClass(ap.jenis_payhead === 'earnings' ? 'text-success' : 'text-danger');
                                        $('#selected_payheads').append(option);
                                        var payheadAmount = `
                                            <div class="payhead-item">
                                                <label>${payheadNameTranslated}</label>
                                                <input type="text" name="pay_amounts[${payheadId}]" class="form-control currency-input" value="${ap.amount}" required>
                                            </div>
                                        `;
                                        $('#selected_payamount').append(payheadAmount);
                                    });
                                    initAutoNumeric();
                                    $('#ManageModal').modal('show');
                                } else {
                                    showToast(allPayheadsResult.result, 'error');
                                }
                            },
                            error: function(){
                                showToast('Terjadi kesalahan saat memuat semua payheads.', 'error');
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
                if(amount === '' || parseFloat(amount) < 0){
                    $('input[name="pay_amounts[' + payheadId + ']"]').addClass('is-invalid');
                    isValid = false;
                } else {
                    $('input[name="pay_amounts[' + payheadId + ']"]').removeClass('is-invalid');
                }
            });
            if(!isValid){
                showToast('Pastikan semua jumlah payhead valid (>=0)!', 'error');
                return;
            }
            var dataToSend = {
                case: 'AssignPayheadsToEmployee',
                empcode: empId,
                payheads: payHeads,
                pay_amounts: payAmounts,
                csrf_token: csrfToken
            };
            $.ajax({
                url:'employees.php?ajax=1',
                type:'POST',
                data: dataToSend,
                dataType:'json',
                beforeSend:function(){
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success:function(resp){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if(resp.code === 0){
                        showToast(resp.result, 'success');
                        empTable.ajax.reload(null, false);
                        setTimeout(function(){
                            $('#ManageModal').modal('hide');
                            form[0].reset();
                            $('#all_payheads').empty();
                            $('#selected_payheads').empty();
                            $('#selected_payamount').empty();
                        }, 200);
                    } else {
                        showToast(resp.result, 'error');
                    }
                },
                error:function(jqXHR, textStatus, errorThrown){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat menetapkan payheads.', 'error');
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
                $('input[name="pay_amounts[' + payheadId + ']"]').closest('.payhead-item').remove();
                var jenisPayhead = payheadName.includes('(Pendapatan)') ? 'earnings' : 'deductions';
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
                if($('#selected_payheads option[value="' + payheadId + '"]').length === 0){
                    var option = $("<option></option>")
                        .attr("value", payheadId)
                        .text(payheadName)
                        .addClass(jenisPayhead === 'earnings' ? 'text-success' : 'text-danger');
                    $('#selected_payheads').append(option);
                    var payheadAmount = `
                        <div class="payhead-item">
                            <label>${payheadName}</label>
                            <input type="text" name="pay_amounts[${payheadId}]" class="form-control currency-input" value="0" required>
                        </div>
                    `;
                    $('#selected_payamount').append(payheadAmount);
                    $(this).remove();
                }
            });
            initAutoNumeric();
        });

        $('#employees tbody').on('click', '.btnSelectMonth', function() {
            var employeeId = $(this).data('id'); 
            window.currentEmpId = employeeId;
            $('#SalaryMonthModal').modal('show');
        });
        $(document).on('click', '.month-link', function(e) {
            e.preventDefault();
            var monthName = $(this).data('month'); 
            var yearName  = $(this).data('year');  
            var employeeId = window.currentEmpId || 0;
            if (employeeId === 0) {
                showToast('ID Karyawan tidak valid!', 'error');
                return;
            }
            var targetUrl = "/payroll_absensi_v2/payroll/keuangan/manage-salary.php";
            targetUrl += "?id_anggota=" + employeeId;
            targetUrl += "&bulan=" + encodeURIComponent(monthName);
            targetUrl += "&tahun=" + encodeURIComponent(yearName);
            window.location.href = targetUrl;
        });

        $('#filterForm').on('keypress', function(e){
            if(e.which === 13) {
                $('#btnApplyFilter').click();
            }
        });
    });
    </script>
</body>
</html>
