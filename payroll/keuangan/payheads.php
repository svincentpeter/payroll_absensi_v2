<?php
// File: /payroll_absensi_v2/payroll/keuangan/payheads.php (fix)

// =========================
// 1. Pengaturan Keamanan
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

// Proteksi CSRF
generate_csrf_token(); // Generate CSRF token jika belum ada

// Role Checking
function authorize($allowed_roles = ['keuangan', 'superadmin']) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        send_response(403, 'Akses ditolak.');
    }
}

// Koneksi ke database
require_once __DIR__ . '/../../koneksi.php';

// Nonaktifkan output buffering (jika ada)
if (ob_get_length()) ob_end_clean();

// Implementasi CSP dengan nonce
header("Content-Security-Policy: default-src 'self'; 
    script-src 'self' https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.datatables.net https://cdn.jsdelivr.net 'nonce-$nonce'; 
    style-src 'self' https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com https://cdn.datatables.net https://cdn.jsdelivr.net 'nonce-$nonce'; 
    img-src 'self'; 
    font-src 'self' https://cdnjs.cloudflare.com; 
    connect-src 'self'");

// =========================
// 2. Sanitasi Input (tersedia di helpers.php)
// =========================


// =========================
// 3. Menangani Permintaan AJAX
// =========================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verifikasi CSRF
        $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        verify_csrf_token($csrf_token);

        // Role Check
        authorize();

        // Ambil case
        $case = isset($_POST['case']) ? bersihkan_input($_POST['case']) : '';

        switch ($case) {
            case 'LoadingPayheads':
                LoadingPayheads($conn);
                break;
            case 'AddPayhead':
                AddPayhead($conn);
                break;
            case 'GetPayheadDetail':
                GetPayheadDetail($conn);
                break;
            case 'UpdatePayhead':
                UpdatePayhead($conn);
                break;
            case 'DeletePayhead':
                DeletePayhead($conn);
                break;
            case 'AddAuditLog':
                // Menerima data aksi dan detail dari AJAX
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
// 4. Fungsi CRUD dengan Audit Logs
// =========================

function LoadingPayheads($conn) {
    // DataTables parameters
    $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = isset($_POST['search']['value']) ? bersihkan_input($_POST['search']['value']) : '';

    // Filter Jenis Payhead
    $filterJenis = isset($_POST['jenis_payhead']) ? bersihkan_input($_POST['jenis_payhead']) : '';

    // Total records
    $sqlTotal = "SELECT COUNT(*) as total FROM payheads";
    $resultTotal = mysqli_query($conn, $sqlTotal);
    if (!$resultTotal) {
        send_response(1, 'Query Error: ' . mysqli_error($conn));
    }
    $rowTotal = mysqli_fetch_assoc($resultTotal);
    $recordsTotal = $rowTotal['total'];

    // Membangun query filter + ORDER
    $sqlFilter = "SELECT * FROM payheads WHERE 1=1";
    $sqlFilterCount = "SELECT COUNT(*) as total FROM payheads WHERE 1=1";
    $paramsFilterCount = [];
    $typesFilterCount = "";
    $paramsFilter = [];
    $typesFilter = "";

    // Jika ada pencarian
    if (!empty($search)) {
        $sqlFilter      .= " AND (nama_payhead LIKE ? OR jenis LIKE ? OR deskripsi LIKE ?)";
        $sqlFilterCount .= " AND (nama_payhead LIKE ? OR jenis LIKE ? OR deskripsi LIKE ?)";
        $searchParam = "%" . $search . "%";
        $paramsFilterCount = [$searchParam, $searchParam, $searchParam];
        $typesFilterCount = "sss";
        $paramsFilter = [$searchParam, $searchParam, $searchParam];
        $typesFilter = "sss";
    }

    // Jika filter jenis dipilih
    if (!empty($filterJenis)) {
        $sqlFilter      .= " AND jenis = ?";
        $sqlFilterCount .= " AND jenis = ?";
        $paramsFilterCount[] = $filterJenis;
        $typesFilterCount .= "s";
        $paramsFilter[] = $filterJenis;
        $typesFilter .= "s";
    }

    // Hitung recordsFiltered
    $stmtFiltered = $conn->prepare($sqlFilterCount);
    if ($stmtFiltered === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    if (!empty($paramsFilterCount)) {
        $stmtFiltered->bind_param($typesFilterCount, ...$paramsFilterCount);
    }
    $stmtFiltered->execute();
    $resultFiltered = $stmtFiltered->get_result();
    if (!$resultFiltered) {
        send_response(1, 'Query Error: ' . $stmtFiltered->error);
    }
    $rowFiltered = $resultFiltered->fetch_assoc();
    $recordsFiltered = isset($rowFiltered['total']) ? $rowFiltered['total'] : 0;
    $stmtFiltered->close();

    // Order by
    $orderBy = " ORDER BY id DESC";
    if (isset($_POST['order'], $_POST['columns'])) {
        $columnIndex = intval($_POST['order'][0]['column']);
        $allowedColumns = ['nama_payhead', 'jenis', 'deskripsi'];
        if (isset($_POST['columns'][$columnIndex]['data']) && in_array($_POST['columns'][$columnIndex]['data'], $allowedColumns)) {
            $colName = $_POST['columns'][$columnIndex]['data'];
            $colSortOrder = ($_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
            $orderBy = " ORDER BY $colName $colSortOrder";
        }
    }

    // Limit
    $limit = " LIMIT ?, ?";
    $paramsFilter[] = $start;
    $paramsFilter[] = $length;
    $typesFilter .= "ii";
    $sqlFilter .= $orderBy . $limit;

    // Eksekusi query data
    $stmtData = $conn->prepare($sqlFilter);
    if ($stmtData === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    if (!empty($paramsFilter)) {
        $stmtData->bind_param($typesFilter, ...$paramsFilter);
    }
    $stmtData->execute();
    $dataQuery = $stmtData->get_result();
    if (!$dataQuery) {
        send_response(1, 'Query Error: ' . $stmtData->error);
    }

    $data = [];
    $no = $start + 1;

    while ($row = $dataQuery->fetch_assoc()) {
        $jenis = ($row['jenis'] == 'earnings') 
                    ? '<span class="badge badge-success">Pendapatan</span>' 
                    : '<span class="badge badge-danger">Potongan</span>';
        // Tombol Aksi (dropdown dengan ikon tiga titik vertikal)
        $aksi = '
<div class="dropdown">
  <button class="btn" type="button" id="dropdownMenuButton_' . htmlspecialchars($row['id']) . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <i class="bi bi-three-dots-vertical"></i>
  </button>
  <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_' . htmlspecialchars($row['id']) . '">
    <a class="dropdown-item btn-edit" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '" title="Edit">
        <i class="fas fa-pencil-alt"></i> Edit
    </a>
    <a class="dropdown-item btn-delete" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '" title="Hapus">
        <i class="fas fa-trash-alt"></i> Hapus
    </a>
  </div>
</div>';
        $data[] = [
            "no" => $no++,
            "nama_payhead" => bersihkan_input($row['nama_payhead']),
            "jenis" => $jenis,
            "deskripsi" => bersihkan_input($row['deskripsi']),
            "aksi" => $aksi
        ];
    }
    $stmtData->close();

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $recordsTotal,
        "recordsFiltered" => $recordsFiltered,
        "data" => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

function AddPayhead($conn) {
    $nama_payhead = isset($_POST['nama_payhead']) ? bersihkan_input($_POST['nama_payhead']) : '';
    $jenis = isset($_POST['jenis_payhead']) ? bersihkan_input($_POST['jenis_payhead']) : '';
    $deskripsi = isset($_POST['deskripsi']) ? bersihkan_input($_POST['deskripsi']) : '';

    if (empty($nama_payhead) || empty($jenis)) {
        send_response(2, 'Semua field wajib diisi.');
    }
    if (!in_array($jenis, ['earnings', 'deductions'])) {
        send_response(3, 'Jenis Payhead tidak valid.');
    }

    // cek duplikasi
    $stmt = $conn->prepare("SELECT id FROM payheads WHERE nama_payhead = ? AND jenis = ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("ss", $nama_payhead, $jenis);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        send_response(4, 'Payhead sudah ada.');
    }
    $stmt->close();

    // insert
    $stmt = $conn->prepare("INSERT INTO payheads (nama_payhead, jenis, deskripsi) VALUES (?, ?, ?)");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("sss", $nama_payhead, $jenis, $deskripsi);
    if ($stmt->execute()) {
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $details_log = "Menambahkan Payhead: Nama='$nama_payhead', Jenis='$jenis', Deskripsi='$deskripsi'.";
        if (!add_audit_log($conn, $user_id, 'AddPayhead', $details_log)) {
            log_error("Gagal mencatat audit log untuk AddPayhead ID " . $stmt->insert_id . ".");
        }
        send_response(0, 'Payhead berhasil ditambahkan.');
    } else {
        send_response(1, 'Gagal menambah payhead: ' . $stmt->error);
    }
    $stmt->close();
    exit();
}

function GetPayheadDetail($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        send_response(1, 'ID Payhead tidak valid.');
    }

    $stmt = $conn->prepare("SELECT * FROM payheads WHERE id = ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows == 1) {
        $payhead = $result->fetch_assoc();
        $stmt->close();

        // Tambahkan Audit Log untuk Viewing Detail Payhead
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $details_log = "Melihat detail Payhead ID $id: Nama='{$payhead['nama_payhead']}', Jenis='{$payhead['jenis']}', Deskripsi='{$payhead['deskripsi']}'.";
        if (!add_audit_log($conn, $user_id, 'ViewPayheadDetail', $details_log)) {
            log_error("Gagal mencatat audit log untuk ViewPayheadDetail ID $id.");
        }

        send_response(0, [
            'id' => $payhead['id'],
            'nama_payhead' => $payhead['nama_payhead'],
            'jenis' => $payhead['jenis'],
            'deskripsi' => $payhead['deskripsi']
        ]);
    } else {
        send_response(2, 'Payhead tidak ditemukan.');
    }
    $stmt->close();
    exit();
}

function UpdatePayhead($conn) {
    $id = isset($_POST['edit_payhead_id']) ? intval($_POST['edit_payhead_id']) : 0;
    $nama_payhead = isset($_POST['edit_nama_payhead']) ? bersihkan_input($_POST['edit_nama_payhead']) : '';
    $jenis = isset($_POST['edit_jenis_payhead']) ? bersihkan_input($_POST['edit_jenis_payhead']) : '';
    $deskripsi = isset($_POST['edit_deskripsi']) ? bersihkan_input($_POST['edit_deskripsi']) : '';

    if ($id <= 0 || empty($nama_payhead) || empty($jenis)) {
        send_response(3, 'Field wajib diisi dan ID Payhead harus valid.');
    }
    if (!in_array($jenis, ['earnings', 'deductions'])) {
        send_response(4, 'Jenis Payhead tidak valid.');
    }

    // cek duplikasi
    $stmt = $conn->prepare("SELECT id FROM payheads WHERE nama_payhead = ? AND jenis = ? AND id != ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("ssi", $nama_payhead, $jenis, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        send_response(5, 'Payhead sudah ada.');
    }
    $stmt->close();

    // update
    $stmt = $conn->prepare("UPDATE payheads SET nama_payhead = ?, jenis = ?, deskripsi = ? WHERE id = ?");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("sssi", $nama_payhead, $jenis, $deskripsi, $id);
    if ($stmt->execute()) {
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $details_log = "Mengupdate Payhead ID $id: Nama='$nama_payhead', Jenis='$jenis', Deskripsi='$deskripsi'.";
        if (!add_audit_log($conn, $user_id, 'UpdatePayhead', $details_log)) {
            log_error("Gagal mencatat audit log untuk UpdatePayhead ID $id.");
        }
        send_response(0, 'Payhead berhasil diupdate.');
    } else {
        send_response(1, 'Gagal mengupdate payhead: ' . $stmt->error);
    }
    $stmt->close();
    exit();
}

function DeletePayhead($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        send_response(3, 'ID Payhead tidak valid.');
    }

    // Cek apakah payhead sedang digunakan di tabel payroll
    $stmt = $conn->prepare("SELECT id FROM payroll_details WHERE id_payhead = ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        send_response(1, 'Eksekusi Query Error: ' . $stmt->error);
    }
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        send_response(4, 'Payhead tidak bisa dihapus karena sedang dipakai.');
    }
    $stmt->close();

    // Hapus payhead
    $stmt = $conn->prepare("DELETE FROM payheads WHERE id = ?");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        send_response(1, 'Gagal menghapus payhead: ' . $stmt->error);
    }

    // Tambahkan Audit Log
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $details_log = "Menghapus Payhead ID $id.";
    if (!add_audit_log($conn, $user_id, 'DeletePayhead', $details_log)) {
        log_error("Gagal mencatat audit log untuk DeletePayhead ID $id.");
    }

    send_response(0, 'Payhead berhasil dihapus.');
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Payheads - Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 4 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" nonce="<?php echo $nonce; ?>">
    <!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/css/sb-admin-2.min.css" nonce="<?php echo $nonce; ?>">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.6.2/css/buttons.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" nonce="<?php echo $nonce; ?>">
    <!-- Bootstrap Notify CSS (jika perlu) -->
    <link rel="stylesheet" href="/payroll_absensi_v2/plugins/bootstrap-notify/bootstrap-notify.min.css" nonce="<?php echo $nonce; ?>">
    <style nonce="<?php echo $nonce; ?>">
        .btn {
            transition: background-color 0.3s, transform 0.2s;
        }
        .btn:hover {
            transform: scale(1.05);
        }
        .aksi-column .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        /* Custom Styles untuk Kartu */
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }
        /* Menambahkan efek hover */
        .table-hover tbody tr:hover {
            background-color: #e2e6ea;
        }
        #payheadsTable.table-sm tbody tr:nth-of-type(odd) {
            background-color: #f9f9f9;
        }
        #payheadsTable.table-sm tbody tr:nth-of-type(even) {
            background-color: #ffffff;
        }
        #payheadsTable.table-sm tbody tr:hover {
            background-color: #e2e6ea;
        }
        #payheadsTable.table-sm th, #payheadsTable.table-sm td {
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
        @media (max-width: 768px) {
            .form-inline .form-group {
                width: 100%;
                margin-right: 0 !important;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body id="page-top" class="sidebar-mini">
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include(__DIR__ . '/../../sidebar.php'); ?>
        <!-- End of Sidebar -->

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

                <!-- Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">
                        <i class="fas fa-money-check-alt"></i> Manajemen Payheads
                    </h1>

                    <!-- Filter -->
                    <div class="card mb-4" style="background-color: #f8f9fa; border-radius: 0.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <div class="card-header" style="background-color: #ffffff;">
                            <strong>Filter Payheads</strong>
                        </div>
                        <div class="card-body">
                            <form id="filterForm" class="form-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <div class="form-group mb-2 mr-3">
                                    <label for="filterJenisPayhead" class="mr-2"><strong>Jenis Payhead:</strong></label>
                                    <select class="form-control" id="filterJenisPayhead" name="jenis_payhead" style="width:200px">
                                        <option value="">Semua Jenis</option>
                                        <option value="earnings">Earnings (Pendapatan)</option>
                                        <option value="deductions">Deductions (Potongan)</option>
                                    </select>
                                </div>
                                <div class="form-group mb-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-primary mr-2" id="btnApplyFilter">
                                        <i class="fas fa-filter"></i> Filter
                                    </button>
                                    <button type="button" class="btn btn-secondary mr-2" id="btnResetFilter">
                                        <i class="fas fa-undo"></i> Reset
                                    </button>
                                    <button type="button" class="btn btn-success" id="btnExportData">
                                        <i class="fas fa-file-export"></i> Export
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div id="alert-placeholder"></div>

                    <!-- Tabel Rekap Payheads -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-clipboard-list"></i> Daftar Payheads</h6>
                            <button type="button" class="btn btn-primary btn-success" data-toggle="modal" data-target="#addPayheadModal">
                                <i class="fas fa-plus"></i> Tambah Payhead
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <!-- Tambahkan class .table-sm -->
                                <table id="payheadsTable" class="table table-sm table-bordered table-striped display nowrap" style="width:100%">
                                    <thead class="thead">
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Payhead</th>
                                            <th>Jenis</th>
                                            <th>Deskripsi</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end container-fluid -->
            </div>
            <!-- end #content -->

            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?php echo date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Modal Tambah -->
    <div class="modal fade" id="addPayheadModal" tabindex="-1" aria-labelledby="addPayheadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="add-payhead-form" class="needs-validation" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="addPayheadModalLabel">Tambah Payhead</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="case" value="AddPayhead">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label for="nama_payhead">Nama Payhead <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama_payhead" name="nama_payhead" required>
                            <div class="invalid-feedback">Nama payhead belum diisi.</div>
                        </div>
                        <div class="form-group">
                            <label for="jenis_payhead">Jenis <span class="text-danger">*</span></label>
                            <select class="form-control" id="jenis_payhead" name="jenis_payhead" required>
                                <option value="">---Pilih Jenis---</option>
                                <option value="earnings">Earnings (Pendapatan)</option>
                                <option value="deductions">Deductions (Potongan)</option>
                            </select>
                            <div class="invalid-feedback">Pilih jenis payhead.</div>
                        </div>
                        <div class="form-group">
                            <label for="deskripsi">Deskripsi <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" required></textarea>
                            <div class="invalid-feedback">Masukkan deskripsi payhead.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit -->
    <div class="modal fade" id="editPayheadModal" tabindex="-1" aria-labelledby="editPayheadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="edit-payhead-form" class="needs-validation" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="editPayheadModalLabel">Edit Payhead</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="case" value="UpdatePayhead">
                        <input type="hidden" id="edit_payhead_id" name="edit_payhead_id">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label for="edit_nama_payhead">Nama Payhead <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_nama_payhead" name="edit_nama_payhead" required>
                            <div class="invalid-feedback">Nama payhead belum diisi.</div>
                        </div>
                        <div class="form-group">
                            <label for="edit_jenis_payhead">Jenis <span class="text-danger">*</span></label>
                            <select class="form-control" id="edit_jenis_payhead" name="edit_jenis_payhead" required>
                                <option value="">---Pilih Jenis---</option>
                                <option value="earnings">Earnings (Pendapatan)</option>
                                <option value="deductions">Deductions (Potongan)</option>
                            </select>
                            <div class="invalid-feedback">Pilih jenis payhead.</div>
                        </div>
                        <div class="form-group">
                            <label for="edit_deskripsi">Deskripsi <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="edit_deskripsi" name="edit_deskripsi" rows="3" required></textarea>
                            <div class="invalid-feedback">Masukkan deskripsi payhead.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Delete -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form id="deleteForm">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Hapus Payhead</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span>&times;</span>
              </button>
            </div>
              <div class="modal-body">
                  <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                  <input type="hidden" id="delete_id" name="id">
                  <p>Yakin ingin menghapus item ini?</p>
              </div>
              <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Tidak</button>
                  <button type="submit" class="btn btn-danger">Ya, Hapus</button>
              </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Loading Spinner -->
    <div id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>

    <!-- JS Dependencies -->
    <!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js" nonce="<?php echo $nonce; ?>"></script>
<!-- Bootstrap Bundle (termasuk Popper) -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.2/js/dataTables.buttons.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.2/js/buttons.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.2/js/buttons.html5.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.2/js/buttons.print.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="/payroll_absensi_v2/plugins/bootstrap-notify/bootstrap-notify.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/js/sb-admin-2.min.js" nonce="<?php echo $nonce; ?>"></script>
    <!-- Pastikan menyertakan SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" nonce="<?php echo $nonce; ?>"></script>
    <script nonce="<?php echo $nonce; ?>">
    $(document).ready(function() {
        $('[data-toggle="tooltip"]').tooltip();

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

        // Tabel Data Payheads
        var payheadsTable = $('#payheadsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "payheads.php?ajax=1",
                type: "POST",
                data: function (d) {
                    d.case = 'LoadingPayheads';
                    d.csrf_token = csrfToken;
                    d.jenis_payhead = $('#filterJenisPayhead').val();
                },
                beforeSend: function(){
                    $('#loadingSpinner').show();
                },
                complete: function(){
                    $('#loadingSpinner').hide();
                },
                error: function(){
                    showToast('Terjadi kesalahan saat memuat data payheads.', 'error');
                }
            },
            columns: [
                { data: "no", orderable: false },
                { data: "nama_payhead" },
                { data: "jenis" },
                { data: "deskripsi" },
                { data: "aksi", orderable: false }
            ],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
            },
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Export Excel',
                    className: 'btn btn-success btn-sm',
                    exportOptions: { columns: [0,1,2,3] }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> Export PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: { columns: [0,1,2,3] },
                    customize: function (doc) {
                        doc.styles.tableHeader.fillColor = '#343a40';
                        doc.styles.tableHeader.color = 'white';
                        doc.defaultStyle.fontSize = 10;
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print"></i> Print',
                    className: 'btn btn-info btn-sm',
                    exportOptions: { columns: [0,1,2,3] }
                }
            ],
            responsive: true,
            autoWidth: false
        });

        // Apply Filter
        $('#btnApplyFilter').on('click', function(){
            $.ajax({
                url: 'payheads.php?ajax=1',
                type: 'POST',
                data: {
                    case: 'AddAuditLog',
                    csrf_token: csrfToken,
                    action: 'ApplyFilter',
                    details: `Pengguna menerapkan filter Jenis Payhead: ${$('#filterJenisPayhead').val() || 'Semua'}.`
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
            payheadsTable.ajax.reload();
        });

        // Reset Filter
        $('#btnResetFilter').on('click', function(){
            $.ajax({
                url: 'payheads.php?ajax=1',
                type: 'POST',
                data: {
                    case: 'AddAuditLog',
                    csrf_token: csrfToken,
                    action: 'ResetFilter',
                    details: 'Pengguna mereset semua filter Payhead.'
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
            payheadsTable.ajax.reload();
        });

        // Export Excel
        $('#btnExportData').on('click', function(){
            payheadsTable.button('.buttons-excel').trigger();
            $.ajax({
                url: 'payheads.php?ajax=1',
                type: 'POST',
                data: {
                    case: 'AddAuditLog',
                    csrf_token: csrfToken,
                    action: 'ExportData',
                    details: 'Pengguna mengekspor data payheads ke Excel.'
                },
                success: function(response){
                    if(response.code === 0){
                        showToast('Data berhasil diekspor ke Excel.', 'success');
                    }
                },
                error: function(){
                    showToast('Terjadi kesalahan saat mencatat audit log.', 'warning');
                }
            });
        });

        // Validasi Bootstrap
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

        // Tambah Payhead
        $('#add-payhead-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            var formData = form.serialize();
            $.ajax({
                url: "payheads.php?ajax=1",
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
                        $('#addPayheadModal').modal('hide');
                        payheadsTable.ajax.reload(null, false);
                        form[0].reset();
                        form.removeClass('was-validated');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat menambah payhead.', 'error');
                }
            });
        });

        // Buka modal edit
        $(document).on('click', '.btn-edit', function() {
            var id = $(this).data('id');
            var modal = $('#editPayheadModal');
            var form = $('#edit-payhead-form');
            form[0].reset();
            form.removeClass('was-validated');
            $.ajax({
                url: "payheads.php?ajax=1",
                type: "POST",
                data: { id: id, case: 'GetPayheadDetail', csrf_token: csrfToken },
                dataType: "json",
                success: function(response){
                    if (response.code == 0) {
                        $('#edit_payhead_id').val(response.result.id);
                        $('#edit_nama_payhead').val(response.result.nama_payhead);
                        $('#edit_jenis_payhead').val(response.result.jenis);
                        $('#edit_deskripsi').val(response.result.deskripsi);
                        modal.modal('show');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    showToast('Terjadi kesalahan mengambil detail payhead.', 'error');
                }
            });
        });

        // Update Payhead
        $('#edit-payhead-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            var formData = form.serialize();
            $.ajax({
                url: "payheads.php?ajax=1",
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
                        $('#editPayheadModal').modal('hide');
                        payheadsTable.ajax.reload(null, false);
                        form[0].reset();
                        form.removeClass('was-validated');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat update payhead.', 'error');
                }
            });
        });

        // Hapus Payhead
        $(document).on('click', '.btn-delete', function() {
            var id = $(this).data('id');
            $('#delete_id').val(id);
            $('#deleteModal').modal('show');
        });

        // Konfirmasi Delete
        $('#deleteForm').on('submit', function(e){
            e.preventDefault();
            var id = $('#delete_id').val();
            if (!id) {
                showToast('ID Payhead tidak ditemukan.', 'error');
                return;
            }
            $.ajax({
                url: "payheads.php?ajax=1",
                type: "POST",
                data: {
                    id: id,
                    case: 'DeletePayhead',
                    csrf_token: csrfToken
                },
                dataType: "json",
                beforeSend: function(){
                    $('#deleteForm').find('button[type="submit"]').prop('disabled', true);
                    $('#deleteForm').find('.spinner-border').removeClass('d-none');
                },
                success: function(response){
                    $('#deleteForm').find('button[type="submit"]').prop('disabled', false);
                    $('#deleteForm').find('.spinner-border').addClass('d-none');
                    if (response.code == 0) {
                        showToast(response.result, 'success');
                        $('#deleteModal').modal('hide');
                        payheadsTable.ajax.reload(null, false);
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(xhr, status, error){
                    $('#deleteForm').find('button[type="submit"]').prop('disabled', false);
                    $('#deleteForm').find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat menghapus payhead: ' + error, 'error');
                }
            });
        });

        // Optional: tangani event enter-key pada filter form
        $('#filterForm').on('keypress', function(e){
            if(e.which === 13) {
                $('#btnApplyFilter').click();
            }
        });
    });
    </script>
</body>
</html>
