<?php
// File: /payroll_absensi_v2/payroll/keuangan/holidays.php

// =========================
// 1. Pengaturan Keamanan & Session
// =========================

// Atur session cookie parameters sebelum session_start()
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => true,      // Hanya lewat HTTPS
    'httponly' => true,      // Tidak dapat diakses via JavaScript
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

// Implementasi CSP dengan nonce
header("Content-Security-Policy: default-src 'self'; 
    script-src 'self' https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.datatables.net https://cdn.jsdelivr.net 'nonce-$nonce'; 
    style-src 'self' https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com https://cdn.datatables.net https://cdn.jsdelivr.net 'nonce-$nonce'; 
    img-src 'self'; 
    font-src 'self' https://cdnjs.cloudflare.com; 
    connect-src 'self'");

// =========================
// 2. Role Checking (Hanya untuk sdm dan superadmin)
// =========================
function authorize($allowed_roles = ['sdm', 'superadmin']) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        send_response(403, 'Akses ditolak.');
    }
}
authorize();

// =========================
// 3. Koneksi ke Database
// =========================
require_once __DIR__ . '/../../koneksi.php';

// Nonaktifkan output buffering (jika ada)
if (ob_get_length()) ob_end_clean();

// =========================
// 4. Tangani Permintaan AJAX (Server-side processing)
// =========================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Verifikasi CSRF
        $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        verify_csrf_token($csrf_token);

        // Ambil parameter 'case' untuk menentukan aksi
        $case = isset($_POST['case']) ? bersihkan_input($_POST['case']) : '';

        switch ($case) {
            case 'LoadingHolidays':
                LoadingHolidays($conn);
                break;
            case 'AddHoliday':
                AddHoliday($conn);
                break;
            case 'GetHolidayDetail':
                GetHolidayDetail($conn);
                break;
            case 'UpdateHoliday':
                UpdateHoliday($conn);
                break;
            case 'DeleteHoliday':
                DeleteHoliday($conn);
                break;
            case 'AddAuditLog':
                // Contoh pencatatan audit log (jika diperlukan)
                $action = isset($_POST['action']) ? bersihkan_input($_POST['action']) : '';
                $details = isset($_POST['details']) ? bersihkan_input($_POST['details']) : '';
                if (!empty($action) && !empty($details)) {
                    $logged = add_audit_log(
                        $conn,
                        $_SESSION['user_id'],
                        $action,
                        $details
                    );
                    if ($logged) {
                        send_response(0, 'Audit log berhasil dicatat.');
                    } else {
                        send_response(1, 'Gagal mencatat audit log.');
                    }
                } else {
                    send_response(1, 'Data audit log tidak lengkap.');
                }
                break;
            default:
                send_response(404, 'Kasus tidak ditemukan.');
        }
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    exit();
}

// =========================
// 5. Fungsi CRUD untuk Holidays
// =========================

function LoadingHolidays($conn) {
    // DataTables parameters
    $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = isset($_POST['search']['value']) ? bersihkan_input($_POST['search']['value']) : '';

    // Hitung total records
    $sqlTotal = "SELECT COUNT(*) as total FROM holidays";
    $resultTotal = mysqli_query($conn, $sqlTotal);
    if (!$resultTotal) {
        send_response(1, 'Query Error: ' . mysqli_error($conn));
    }
    $rowTotal = mysqli_fetch_assoc($resultTotal);
    $recordsTotal = $rowTotal['total'];

    // Membangun query filter
    $sqlFilter = "SELECT * FROM holidays WHERE 1=1";
    $sqlFilterCount = "SELECT COUNT(*) as total FROM holidays WHERE 1=1";
    $params = [];
    $types = "";
    if (!empty($search)) {
        $sqlFilter      .= " AND (holiday_title LIKE ? OR holiday_desc LIKE ? OR holiday_date LIKE ? OR holiday_type LIKE ?)";
        $sqlFilterCount .= " AND (holiday_title LIKE ? OR holiday_desc LIKE ? OR holiday_date LIKE ? OR holiday_type LIKE ?)";
        $searchParam = "%" . $search . "%";
        $params = array_fill(0, 4, $searchParam);
        $types = "ssss";
    }

    // Hitung recordsFiltered
    $stmtFiltered = $conn->prepare($sqlFilterCount);
    if ($stmtFiltered === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    if (!empty($params)) {
        $stmtFiltered->bind_param($types, ...$params);
    }
    $stmtFiltered->execute();
    $resultFiltered = $stmtFiltered->get_result();
    $rowFiltered = $resultFiltered->fetch_assoc();
    $recordsFiltered = isset($rowFiltered['total']) ? $rowFiltered['total'] : 0;
    $stmtFiltered->close();

    // Order by (default: holiday_id DESC)
    $orderBy = " ORDER BY holiday_id DESC";
    if (isset($_POST['order'], $_POST['columns'])) {
        $columnIndex = intval($_POST['order'][0]['column']);
        $allowedColumns = ['holiday_title', 'holiday_desc', 'holiday_date', 'holiday_type'];
        if (isset($_POST['columns'][$columnIndex]['data']) && in_array($_POST['columns'][$columnIndex]['data'], $allowedColumns)) {
            $colName = $_POST['columns'][$columnIndex]['data'];
            $colSortOrder = ($_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
            $orderBy = " ORDER BY $colName $colSortOrder";
        }
    }

    // Limit
    $limit = " LIMIT ?, ?";
    $params[] = $start;
    $params[] = $length;
    $types .= "ii";
    $sqlFilter .= $orderBy . $limit;

    $stmtData = $conn->prepare($sqlFilter);
    if ($stmtData === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    if (!empty($params)) {
        $stmtData->bind_param($types, ...$params);
    }
    $stmtData->execute();
    $dataQuery = $stmtData->get_result();

    $data = [];
    $no = $start + 1;

    while ($row = $dataQuery->fetch_assoc()) {
        // Tampilkan jenis hari libur dengan badge dan ikon
        $jenis = ($row['holiday_type'] == 'wajib') 
                    ? '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Wajib</span>' 
                    : '<span class="badge badge-info"><i class="fas fa-info-circle"></i> Opsional</span>';
        // Tombol aksi dengan ikon (Edit dan Hapus)
        $aksi = '
            <button class="btn btn-sm btn-warning btn-edit" data-id="' . htmlspecialchars($row['holiday_id']) . '" title="Edit"><i class="fas fa-edit"></i></button>
            <button class="btn btn-sm btn-danger btn-delete" data-id="' . htmlspecialchars($row['holiday_id']) . '" title="Hapus"><i class="fas fa-trash-alt"></i></button>
        ';
        $data[] = [
            "no"        => $no++,
            "nama"      => htmlspecialchars($row['holiday_title']),
            "deskripsi" => htmlspecialchars($row['holiday_desc']),
            "tanggal"   => date('d-m-Y', strtotime($row['holiday_date'])),
            "jenis"     => $jenis,
            "aksi"      => $aksi
        ];
    }
    $stmtData->close();

    echo json_encode([
        "draw"            => $draw,
        "recordsTotal"    => $recordsTotal,
        "recordsFiltered" => $recordsFiltered,
        "data"            => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

function AddHoliday($conn) {
    $nama      = isset($_POST['nama']) ? bersihkan_input($_POST['nama']) : '';
    $deskripsi = isset($_POST['deskripsi']) ? bersihkan_input($_POST['deskripsi']) : '';
    $tanggal   = isset($_POST['tanggal']) ? bersihkan_input($_POST['tanggal']) : '';
    $jenis     = isset($_POST['jenis']) ? bersihkan_input($_POST['jenis']) : '';

    if (empty($nama) || empty($deskripsi) || empty($tanggal) || empty($jenis)) {
        send_response(2, 'Semua field wajib diisi.');
    }
    $allowedJenis = ['wajib', 'opsional'];
    if (!in_array($jenis, $allowedJenis)) {
        send_response(3, 'Jenis hari libur tidak valid.');
    }

    // Cek duplikasi
    $stmt = $conn->prepare("SELECT holiday_id FROM holidays WHERE holiday_title = ? AND holiday_date = ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("ss", $nama, $tanggal);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        send_response(4, 'Hari libur sudah ada.');
    }
    $stmt->close();

    // Insert data
    $stmt = $conn->prepare("INSERT INTO holidays (holiday_title, holiday_desc, holiday_date, holiday_type) VALUES (?, ?, ?, ?)");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("ssss", $nama, $deskripsi, $tanggal, $jenis);
    if ($stmt->execute()) {
        // Tambahkan audit log jika diinginkan
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $details_log = "Menambahkan Hari Libur: Judul='$nama', Tanggal='$tanggal', Jenis='$jenis'.";
        if (!add_audit_log($conn, $user_id, 'AddHoliday', $details_log)) {
            log_error("Gagal mencatat audit log untuk AddHoliday ID " . $stmt->insert_id . ".");
        }
        send_response(0, 'Hari libur berhasil ditambahkan.');
    } else {
        send_response(1, 'Gagal menambahkan hari libur: ' . $stmt->error);
    }
    $stmt->close();
    exit();
}

function GetHolidayDetail($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        send_response(1, 'ID Hari Libur tidak valid.');
    }
    $stmt = $conn->prepare("SELECT * FROM holidays WHERE holiday_id = ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows == 1) {
        $holiday = $result->fetch_assoc();
        $stmt->close();
        send_response(0, [
            'id'        => $holiday['holiday_id'],
            'nama'      => $holiday['holiday_title'],
            'deskripsi' => $holiday['holiday_desc'],
            'tanggal'   => $holiday['holiday_date'],
            'jenis'     => $holiday['holiday_type']
        ]);
    } else {
        send_response(2, 'Hari libur tidak ditemukan.');
    }
    $stmt->close();
    exit();
}

function UpdateHoliday($conn) {
    $id        = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
    $nama      = isset($_POST['edit_nama']) ? bersihkan_input($_POST['edit_nama']) : '';
    $deskripsi = isset($_POST['edit_deskripsi']) ? bersihkan_input($_POST['edit_deskripsi']) : '';
    $tanggal   = isset($_POST['edit_tanggal']) ? bersihkan_input($_POST['edit_tanggal']) : '';
    $jenis     = isset($_POST['edit_jenis']) ? bersihkan_input($_POST['edit_jenis']) : '';

    if ($id <= 0 || empty($nama) || empty($deskripsi) || empty($tanggal) || empty($jenis)) {
        send_response(3, 'Semua field wajib diisi dan ID Hari Libur harus valid.');
    }
    $allowedJenis = ['wajib', 'opsional'];
    if (!in_array($jenis, $allowedJenis)) {
        send_response(4, 'Jenis hari libur tidak valid.');
    }

    // Cek duplikasi selain record yang sedang diedit
    $stmt = $conn->prepare("SELECT holiday_id FROM holidays WHERE holiday_title = ? AND holiday_date = ? AND holiday_id != ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("ssi", $nama, $tanggal, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        send_response(5, 'Hari libur sudah ada.');
    }
    $stmt->close();

    // Update data
    $stmt = $conn->prepare("UPDATE holidays SET holiday_title = ?, holiday_desc = ?, holiday_date = ?, holiday_type = ? WHERE holiday_id = ?");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("sssii", $nama, $deskripsi, $tanggal, $jenis, $id);
    // Jika tipe data holiday_type adalah string, gunakan "ssssi" sesuai kebutuhan
    if ($stmt->execute()) {
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $details_log = "Mengupdate Hari Libur ID $id: Judul='$nama', Tanggal='$tanggal', Jenis='$jenis'.";
        if (!add_audit_log($conn, $user_id, 'UpdateHoliday', $details_log)) {
            log_error("Gagal mencatat audit log untuk UpdateHoliday ID $id.");
        }
        send_response(0, 'Hari libur berhasil diupdate.');
    } else {
        send_response(1, 'Gagal mengupdate hari libur: ' . $stmt->error);
    }
    $stmt->close();
    exit();
}

function DeleteHoliday($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        send_response(3, 'ID Hari Libur tidak valid.');
    }

    // Contoh: Cek jika holiday digunakan di tabel lain (misalnya payroll)
    $stmt = $conn->prepare("SELECT id FROM payroll_payheads WHERE id_payhead = ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        send_response(4, 'Hari libur tidak dapat dihapus karena sedang digunakan.');
    }
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM holidays WHERE holiday_id = ?");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $details_log = "Menghapus Hari Libur ID $id.";
        if (!add_audit_log($conn, $user_id, 'DeleteHoliday', $details_log)) {
            log_error("Gagal mencatat audit log untuk DeleteHoliday ID $id.");
        }
        send_response(0, 'Hari libur berhasil dihapus.');
    } else {
        send_response(1, 'Gagal menghapus hari libur: ' . $stmt->error);
    }
    $stmt->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Hari Libur - Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" nonce="<?php echo $nonce; ?>">
<!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/css/sb-admin-2.min.css" nonce="<?php echo $nonce; ?>">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" nonce="<?php echo $nonce; ?>">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" nonce="<?php echo $nonce; ?>">
    <!-- Datepicker CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css" nonce="<?php echo $nonce; ?>">
    <style nonce="<?php echo $nonce; ?>">
        .btn {
            transition: background-color 0.3s, transform 0.2s;
        }
        .btn:hover {
            transform: scale(1.05);
        }
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }
        .table-hover tbody tr:hover {
            background-color: #e2e6ea;
        }
        #holidaysTable tbody tr:nth-of-type(odd) {
            background-color: #f9f9f9;
        }
        #holidaysTable tbody tr:nth-of-type(even) {
            background-color: #ffffff;
        }
        #holidaysTable tbody tr:hover {
            background-color: #e2e6ea;
        }
        .table-sm th, .table-sm td {
            font-size: 13px;
            vertical-align: middle;
            white-space: nowrap;
        }
        thead th {
            background-color: #343a40;
            color: white;
            text-align: left;
        }
        .table-responsive {
            overflow-x: auto;
        }
        #loadingSpinner {
            display: none;
            position: fixed;
            z-index: 9999;
            height: 100px;
            width: 100px;
            margin: auto;
            top: 0; left: 0; bottom: 0; right: 0;
        }
    </style>
</head>
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <?php include __DIR__ . '/../../navbar.php'; ?>
                <!-- End of Topbar -->
<!-- Breadcrumb -->
<?php include __DIR__ . '/../../breadcrumb.php'; ?>

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-calendar-alt"></i> Manajemen Hari Libur</h1>

                    <!-- Filter (opsional) -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <strong><i class="fas fa-filter"></i> Filter Hari Libur</strong>
                        </div>
                        <div class="card-body">
                            <form id="filterForm" class="form-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <button type="button" class="btn btn-primary mr-2" id="btnApplyFilter" title="Terapkan Filter">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <button type="button" class="btn btn-secondary" id="btnResetFilter" title="Reset Filter">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Tombol Tambah Hari Libur -->
                    <div class="mb-3">
                        
                    </div>

                    <!-- Alert Container -->
                    <div id="alert-container"></div>

                    <!-- Tabel Data Hari Libur -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-list"></i> Daftar Hari Libur</h6>
                            <button type="button" class="btn btn-primary btn-success" data-toggle="modal" data-target="#addHolidayModal" title="Tambah Hari Libur">
                            <i class="fas fa-plus-circle"></i> Tambah Hari Libur
                        </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="holidaysTable" class="table table-sm table-bordered table-striped display nowrap" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th><i class="fas fa-hashtag"></i> No</th>
                                            <th><i class="fas fa-heading"></i> Judul Hari Libur</th>
                                            <th><i class="fas fa-align-left"></i> Deskripsi</th>
                                            <th><i class="fas fa-calendar"></i> Tanggal</th>
                                            <th><i class="fas fa-tags"></i> Jenis</th>
                                            <th><i class="fas fa-cogs"></i> Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Page Content -->
            </div>
            <!-- End Content -->

            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>
                            &copy; <?php echo date("Y"); ?> Payroll Management System 
                            | Developed By <i class="fas fa-user-cog"></i> [Nama Anda]
                        </span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>

    <!-- Modal Tambah Hari Libur -->
    <div class="modal fade" id="addHolidayModal" tabindex="-1" aria-labelledby="addHolidayModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="add-holiday-form" class="needs-validation" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="addHolidayModalLabel"><i class="fas fa-plus-circle"></i> Tambah Hari Libur</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup" title="Tutup Modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="case" value="AddHoliday">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label for="nama"><i class="fas fa-heading"></i> Judul Hari Libur <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama" name="nama" required>
                            <div class="invalid-feedback">Judul hari libur belum diisi.</div>
                        </div>
                        <div class="form-group">
                            <label for="deskripsi"><i class="fas fa-align-left"></i> Deskripsi <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" required></textarea>
                            <div class="invalid-feedback">Deskripsi belum diisi.</div>
                        </div>
                        <div class="form-group">
                            <label for="tanggal"><i class="fas fa-calendar-alt"></i> Tanggal Hari Libur <span class="text-danger">*</span></label>
                            <input type="text" class="form-control datepicker" id="tanggal" name="tanggal" placeholder="yyyy-mm-dd" required>
                            <div class="invalid-feedback">Tanggal belum dipilih.</div>
                        </div>
                        <div class="form-group">
                            <label for="jenis"><i class="fas fa-tags"></i> Jenis Hari Libur <span class="text-danger">*</span></label>
                            <select class="form-control" id="jenis" name="jenis" required>
                                <option value="">---Pilih Jenis---</option>
                                <option value="wajib">Wajib</option>
                                <option value="opsional">Opsional</option>
                            </select>
                            <div class="invalid-feedback">Pilih jenis hari libur.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" title="Tutup Modal">
                            <i class="fas fa-times"></i> Tutup
                        </button>
                        <button type="submit" class="btn btn-primary" title="Simpan Data">
                            <i class="fas fa-save"></i> Simpan
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Hari Libur -->
    <div class="modal fade" id="editHolidayModal" tabindex="-1" aria-labelledby="editHolidayModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="edit-holiday-form" class="needs-validation" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="editHolidayModalLabel"><i class="fas fa-edit"></i> Edit Hari Libur</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup" title="Tutup Modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="case" value="UpdateHoliday">
                        <input type="hidden" id="edit_id" name="edit_id">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label for="edit_nama"><i class="fas fa-heading"></i> Judul Hari Libur <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_nama" name="edit_nama" required>
                            <div class="invalid-feedback">Judul hari libur belum diisi.</div>
                        </div>
                        <div class="form-group">
                            <label for="edit_deskripsi"><i class="fas fa-align-left"></i> Deskripsi <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="edit_deskripsi" name="edit_deskripsi" rows="3" required></textarea>
                            <div class="invalid-feedback">Deskripsi belum diisi.</div>
                        </div>
                        <div class="form-group">
                            <label for="edit_tanggal"><i class="fas fa-calendar-alt"></i> Tanggal Hari Libur <span class="text-danger">*</span></label>
                            <input type="text" class="form-control datepicker" id="edit_tanggal" name="edit_tanggal" placeholder="yyyy-mm-dd" required>
                            <div class="invalid-feedback">Tanggal belum dipilih.</div>
                        </div>
                        <div class="form-group">
                            <label for="edit_jenis"><i class="fas fa-tags"></i> Jenis Hari Libur <span class="text-danger">*</span></label>
                            <select class="form-control" id="edit_jenis" name="edit_jenis" required>
                                <option value="">---Pilih Jenis---</option>
                                <option value="wajib">Wajib</option>
                                <option value="opsional">Opsional</option>
                            </select>
                            <div class="invalid-feedback">Pilih jenis hari libur.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" title="Tutup Modal">
                            <i class="fas fa-times"></i> Tutup
                        </button>
                        <button type="submit" class="btn btn-primary" title="Update Data">
                            <i class="fas fa-save"></i> Update
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JS Dependencies -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" nonce="<?php echo $nonce; ?>"></script>
    <!-- Bootstrap Bundle (termasuk Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>
<!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" nonce="<?php echo $nonce; ?>"></script>
    <!-- Bootstrap Datepicker JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js" nonce="<?php echo $nonce; ?>"></script>
    <!-- SB Admin 2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/js/sb-admin-2.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script nonce="<?php echo $nonce; ?>">
    $(document).ready(function() {
        // Inisialisasi tooltip
        $('[data-toggle="tooltip"]').tooltip();

        // Inisialisasi SweetAlert2 Toast
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

        // Inisialisasi DataTable untuk Holidays
        var holidaysTable = $('#holidaysTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "holidays.php?ajax=1",
                type: "POST",
                data: function(d) {
                    d.case = 'LoadingHolidays';
                    d.csrf_token = csrfToken;
                },
                beforeSend: function(){
                    $('#loadingSpinner').show();
                },
                complete: function(){
                    $('#loadingSpinner').hide();
                },
                error: function(){
                    showToast('Terjadi kesalahan saat memuat data hari libur.', 'error');
                }
            },
            columns: [
                { data: "no", orderable: false },
                { data: "nama" },
                { data: "deskripsi" },
                { data: "tanggal" },
                { data: "jenis" },
                { data: "aksi", orderable: false }
            ],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
            },
            responsive: true,
            autoWidth: false
        });

        // Filter (contoh, jika diperlukan)
        $('#btnApplyFilter').on('click', function(){
            $.ajax({
                url: 'holidays.php?ajax=1',
                type: 'POST',
                data: {
                    case: 'AddAuditLog',
                    csrf_token: csrfToken,
                    action: 'ApplyFilter',
                    details: 'Pengguna menerapkan filter pada Hari Libur.'
                },
                success: function(response){
                    if(response.code === 0){
                        showToast('Filter berhasil diterapkan.');
                    }
                },
                error: function(){
                    showToast('Terjadi kesalahan saat mencatat audit log.', 'warning');
                }
            });
            holidaysTable.ajax.reload();
        });

        $('#btnResetFilter').on('click', function(){
            $.ajax({
                url: 'holidays.php?ajax=1',
                type: 'POST',
                data: {
                    case: 'AddAuditLog',
                    csrf_token: csrfToken,
                    action: 'ResetFilter',
                    details: 'Pengguna mereset filter hari libur.'
                },
                success: function(response){
                    if(response.code === 0){
                        showToast('Filter berhasil direset.');
                    }
                },
                error: function(){
                    showToast('Terjadi kesalahan saat mencatat audit log.', 'warning');
                }
            });
            $('#filterForm')[0].reset();
            holidaysTable.ajax.reload();
        });

        // Validasi form Bootstrap
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Tambah Hari Libur
        $('#add-holiday-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            var formData = form.serialize();
            $.ajax({
                url: "holidays.php?ajax=1",
                type: "POST",
                data: formData,
                dataType: "json",
                beforeSend: function(){
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success: function(response){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if (response.code == 0) {
                        showToast(response.result, 'success');
                        $('#addHolidayModal').modal('hide');
                        holidaysTable.ajax.reload(null, false);
                        form[0].reset();
                        form.removeClass('was-validated');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat menambah hari libur.', 'error');
                }
            });
        });

        // Buka modal edit
        $(document).on('click', '.btn-edit', function() {
            var id = $(this).data('id');
            var modal = $('#editHolidayModal');
            var form = $('#edit-holiday-form');
            form[0].reset();
            form.removeClass('was-validated');
            $.ajax({
                url: "holidays.php?ajax=1",
                type: "POST",
                data: { id: id, case: 'GetHolidayDetail', csrf_token: csrfToken },
                dataType: "json",
                success: function(response){
                    if (response.code == 0) {
                        $('#edit_id').val(response.result.id);
                        $('#edit_nama').val(response.result.nama);
                        $('#edit_deskripsi').val(response.result.deskripsi);
                        $('#edit_tanggal').val(response.result.tanggal);
                        $('#edit_jenis').val(response.result.jenis);
                        modal.modal('show');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    showToast('Terjadi kesalahan mengambil detail hari libur.', 'error');
                }
            });
        });

        // Update Hari Libur
        $('#edit-holiday-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            var formData = form.serialize();
            $.ajax({
                url: "holidays.php?ajax=1",
                type: "POST",
                data: formData,
                dataType: "json",
                beforeSend: function(){
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success: function(response){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if (response.code == 0) {
                        showToast(response.result, 'success');
                        $('#editHolidayModal').modal('hide');
                        holidaysTable.ajax.reload(null, false);
                        form[0].reset();
                        form.removeClass('was-validated');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat update hari libur.', 'error');
                }
            });
        });

        // Hapus Hari Libur
        $(document).on('click', '.btn-delete', function() {
            var id = $(this).data('id');
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Hari libur akan dihapus!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check"></i> Ya, hapus!',
                cancelButtonText: '<i class="fas fa-times"></i> Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "holidays.php?ajax=1",
                        type: "POST",
                        data: { id: id, case: 'DeleteHoliday', csrf_token: csrfToken },
                        dataType: "json",
                        beforeSend: function(){
                            $('#loadingSpinner').show();
                        },
                        success: function(response){
                            $('#loadingSpinner').hide();
                            if (response.code == 0) {
                                showToast(response.result, 'success');
                                holidaysTable.ajax.reload(null, false);
                            } else {
                                showToast(response.result, 'error');
                            }
                        },
                        error: function(){
                            $('#loadingSpinner').hide();
                            showToast('Terjadi kesalahan saat menghapus hari libur.', 'error');
                        }
                    });
                }
            });
        });

        // Inisialisasi Datepicker
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        });

        // Reset form saat modal ditutup
        $('#addHolidayModal, #editHolidayModal').on('hidden.bs.modal', function () {
            $(this).find('form')[0].reset();
            $(this).find('form').removeClass('was-validated');
        });
    });
    </script>
</body>
</html>
