<?php
// File: /payroll_absensi_v2/pegawai/manage_salary_indices.php

// =========================
// 1. Pengaturan Keamanan
// =========================

// Atur parameter cookie session sebelum session_start()
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

// Pastikan CSRF token tersedia
generate_csrf_token();

// Role Checking: hanya role sdm dan superadmin yang boleh mengakses
function authorize($allowed_roles = ['sdm', 'superadmin']) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        send_response(403, 'Akses ditolak.');
    }
}

// Koneksi ke database
require_once __DIR__ . '/../../koneksi.php';

// Nonaktifkan output buffering jika ada
if (ob_get_length()) ob_end_clean();

// Implementasi CSP dengan nonce
header("Content-Security-Policy: default-src 'self'; 
    script-src 'self' https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.datatables.net https://cdn.jsdelivr.net 'nonce-$nonce'; 
    style-src 'self' https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com https://cdn.datatables.net https://cdn.jsdelivr.net 'nonce-$nonce'; 
    img-src 'self'; 
    font-src 'self' https://cdnjs.cloudflare.com; 
    connect-src 'self'");

// =========================
// 2. Menangani Permintaan AJAX
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
            case 'LoadingSalaryIndices':
                LoadingSalaryIndices($conn);
                break;
            case 'AddSalaryIndex':
                AddSalaryIndex($conn);
                break;
            case 'GetSalaryIndexDetail':
                GetSalaryIndexDetail($conn);
                break;
            case 'UpdateSalaryIndex':
                UpdateSalaryIndex($conn);
                break;
            case 'DeleteSalaryIndex':
                DeleteSalaryIndex($conn);
                break;
            case 'AddAuditLog':
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
// 3. Fungsi CRUD untuk Salary Indices dengan Audit Log
// =========================

function LoadingSalaryIndices($conn) {
    // Parameter DataTables
    $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = isset($_POST['search']['value']) ? bersihkan_input($_POST['search']['value']) : '';

    // Total records
    $sqlTotal = "SELECT COUNT(*) as total FROM salary_indices";
    $resultTotal = mysqli_query($conn, $sqlTotal);
    if (!$resultTotal) {
        send_response(1, 'Query Error: ' . mysqli_error($conn));
    }
    $rowTotal = mysqli_fetch_assoc($resultTotal);
    $recordsTotal = $rowTotal['total'];

    // Bangun query filter
    $sqlFilter = "SELECT * FROM salary_indices WHERE 1=1";
    $sqlFilterCount = "SELECT COUNT(*) as total FROM salary_indices WHERE 1=1";
    $paramsFilter = [];
    $typesFilter = "";

    if (!empty($search)) {
        $sqlFilter      .= " AND (level LIKE ? OR description LIKE ?)";
        $sqlFilterCount .= " AND (level LIKE ? OR description LIKE ?)";
        $searchParam = "%" . $search . "%";
        $paramsFilter = [$searchParam, $searchParam];
        $typesFilter = "ss";
    }

    // Hitung recordsFiltered
    $stmtFiltered = $conn->prepare($sqlFilterCount);
    if ($stmtFiltered === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    if (!empty($paramsFilter)) {
        $stmtFiltered->bind_param($typesFilter, ...$paramsFilter);
    }
    $stmtFiltered->execute();
    $resultFiltered = $stmtFiltered->get_result();
    if (!$resultFiltered) {
        send_response(1, 'Query Error: ' . $stmtFiltered->error);
    }
    $rowFiltered = $resultFiltered->fetch_assoc();
    $recordsFiltered = isset($rowFiltered['total']) ? $rowFiltered['total'] : 0;
    $stmtFiltered->close();

    // Order by (default: id DESC)
    $orderBy = " ORDER BY id DESC";
    if (isset($_POST['order'], $_POST['columns'])) {
        $columnIndex = intval($_POST['order'][0]['column']);
        // Pastikan hanya kolom yang diizinkan
        $allowedColumns = ['level', 'min_years', 'max_years', 'base_salary', 'description'];
        if (isset($_POST['columns'][$columnIndex]['data']) && in_array($_POST['columns'][$columnIndex]['data'], $allowedColumns)) {
            $colName = $_POST['columns'][$columnIndex]['data'];
            $colSortOrder = ($_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
            $orderBy = " ORDER BY $colName $colSortOrder";
        }
    }

    // Limit
    $limit = " LIMIT ?, ?";
    if (!empty($paramsFilter)) {
        $paramsFilter[] = $start;
        $paramsFilter[] = $length;
        $typesFilter .= "ii";
    } else {
        $paramsFilter = [$start, $length];
        $typesFilter = "ii";
    }
    $sqlFilter .= $orderBy . $limit;

    // Eksekusi query data
    $stmtData = $conn->prepare($sqlFilter);
    if ($stmtData === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmtData->bind_param($typesFilter, ...$paramsFilter);
    $stmtData->execute();
    $dataQuery = $stmtData->get_result();
    if (!$dataQuery) {
        send_response(1, 'Query Error: ' . $stmtData->error);
    }

    $data = [];
    $no = $start + 1;

    while ($row = $dataQuery->fetch_assoc()) {
        // Format gaji pokok dengan format rupiah (misalnya)
        $base_salary = number_format($row['base_salary'], 2, ',', '.');
        // Jika max_years null, tampilkan tanda "-"
        $max_years = ($row['max_years'] === null) ? '-' : (int)$row['max_years'];

        // Tombol Aksi dengan dropdown tiga titik vertikal
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
            "no"          => $no++,
            "level"       => htmlspecialchars($row['level']),
            "min_years"   => (int)$row['min_years'],
            "max_years"   => $max_years,
            "base_salary" => $base_salary,
            "description" => htmlspecialchars($row['description']),
            "aksi"        => $aksi
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

function AddSalaryIndex($conn) {
    $level       = isset($_POST['level']) ? trim($_POST['level']) : '';
    $min_years   = isset($_POST['min_years']) ? intval($_POST['min_years']) : 0;
    $max_years   = (isset($_POST['max_years']) && $_POST['max_years'] !== '') ? intval($_POST['max_years']) : null;
    $base_salary = isset($_POST['base_salary']) ? floatval($_POST['base_salary']) : 0;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    if (empty($level)) {
        send_response(2, 'Level wajib diisi.');
    }
    // Cek duplikasi level
    $stmt = $conn->prepare("SELECT id FROM salary_indices WHERE level = ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("s", $level);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        send_response(4, 'Level indeks sudah ada.');
    }
    $stmt->close();

    // Insert data
    $stmt = $conn->prepare("INSERT INTO salary_indices (level, min_years, max_years, base_salary, description) VALUES (?, ?, ?, ?, ?)");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("siids", $level, $min_years, $max_years, $base_salary, $description);
    if ($stmt->execute()) {
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $details_log = "Menambahkan Indeks Gaji: Level='$level', Min Tahun='$min_years', Max Tahun='" . ($max_years ?? 'NULL') . "', Gaji Pokok='$base_salary', Keterangan='$description'.";
        if (!add_audit_log($conn, $user_id, 'AddSalaryIndex', $details_log)) {
            log_error("Gagal mencatat audit log untuk AddSalaryIndex ID " . $stmt->insert_id . ".");
        }
        send_response(0, 'Indeks gaji berhasil ditambahkan.');
    } else {
        send_response(1, 'Gagal menambah indeks gaji: ' . $stmt->error);
    }
    $stmt->close();
    exit();
}

function GetSalaryIndexDetail($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        send_response(1, 'ID tidak valid.');
    }

    $stmt = $conn->prepare("SELECT * FROM salary_indices WHERE id = ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $stmt->close();

        // Catat audit log untuk melihat detail
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $details_log = "Melihat detail Indeks Gaji ID $id: Level='{$row['level']}', Min Tahun='{$row['min_years']}', Max Tahun='" . ($row['max_years'] ?? 'NULL') . "', Gaji Pokok='{$row['base_salary']}', Keterangan='{$row['description']}'.";
        if (!add_audit_log($conn, $user_id, 'ViewSalaryIndexDetail', $details_log)) {
            log_error("Gagal mencatat audit log untuk ViewSalaryIndexDetail ID $id.");
        }

        send_response(0, [
            'id' => $row['id'],
            'level' => $row['level'],
            'min_years' => $row['min_years'],
            'max_years' => $row['max_years'],
            'base_salary' => $row['base_salary'],
            'description' => $row['description']
        ]);
    } else {
        send_response(2, 'Data tidak ditemukan.');
    }
    $stmt->close();
    exit();
}

function UpdateSalaryIndex($conn) {
    $id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
    $level = isset($_POST['edit_level']) ? trim($_POST['edit_level']) : '';
    $min_years = isset($_POST['edit_min_years']) ? intval($_POST['edit_min_years']) : 0;
    $max_years = (isset($_POST['edit_max_years']) && $_POST['edit_max_years'] !== '') ? intval($_POST['edit_max_years']) : null;
    $base_salary = isset($_POST['edit_base_salary']) ? floatval($_POST['edit_base_salary']) : 0;
    $description = isset($_POST['edit_description']) ? trim($_POST['edit_description']) : '';

    if ($id <= 0 || empty($level)) {
        send_response(3, 'Field wajib diisi dan ID harus valid.');
    }

    // Cek duplikasi level untuk indeks lain
    $stmt = $conn->prepare("SELECT id FROM salary_indices WHERE level = ? AND id != ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("si", $level, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        send_response(5, 'Level indeks sudah dipakai.');
    }
    $stmt->close();

    // Update data
    $stmt = $conn->prepare("UPDATE salary_indices SET level = ?, min_years = ?, max_years = ?, base_salary = ?, description = ? WHERE id = ?");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("siidsi", $level, $min_years, $max_years, $base_salary, $description, $id);
    if ($stmt->execute()) {
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $details_log = "Mengupdate Indeks Gaji ID $id: Level='$level', Min Tahun='$min_years', Max Tahun='" . ($max_years ?? 'NULL') . "', Gaji Pokok='$base_salary', Keterangan='$description'.";
        if (!add_audit_log($conn, $user_id, 'UpdateSalaryIndex', $details_log)) {
            log_error("Gagal mencatat audit log untuk UpdateSalaryIndex ID $id.");
        }
        send_response(0, 'Indeks gaji berhasil diupdate.');
    } else {
        send_response(1, 'Gagal mengupdate data: ' . $stmt->error);
    }
    $stmt->close();
    exit();
}

function DeleteSalaryIndex($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        send_response(3, 'ID tidak valid.');
    }

    // Hapus data
    $stmt = $conn->prepare("DELETE FROM salary_indices WHERE id = ?");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        send_response(1, 'Gagal menghapus data: ' . $stmt->error);
    }

    // Audit Log
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $details_log = "Menghapus Indeks Gaji ID $id.";
    if (!add_audit_log($conn, $user_id, 'DeleteSalaryIndex', $details_log)) {
        log_error("Gagal mencatat audit log untuk DeleteSalaryIndex ID $id.");
    }

    send_response(0, 'Indeks gaji berhasil dihapus.');
    $stmt->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Indeks/Kenaikan Gaji Pokok - Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 4 CSS -->
    <link rel="stylesheet" href="/payroll_absensi_v2/dist/css/bootstrap.min.css" nonce="<?php echo $nonce; ?>">
    <!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/css/sb-admin-2.min.css" nonce="<?php echo $nonce; ?>">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.6.2/css/buttons.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" nonce="<?php echo $nonce; ?>">
    <!-- Bootstrap Notify CSS (opsional) -->
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
        .table-hover tbody tr:hover {
            background-color: #e2e6ea;
        }
        #salaryIndicesTable.table-sm tbody tr:nth-of-type(odd) {
            background-color: #f9f9f9;
        }
        #salaryIndicesTable.table-sm tbody tr:nth-of-type(even) {
            background-color: #ffffff;
        }
        #salaryIndicesTable.table-sm tbody tr:hover {
            background-color: #e2e6ea;
        }
        #salaryIndicesTable.table-sm th, #salaryIndicesTable.table-sm td {
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

                <!-- Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">
                        <i class="fas fa-chart-line"></i> Manajemen Indeks/Kenaikan Gaji Pokok
                    </h1>

                    <!-- Tombol Tambah -->
                    <div class="d-flex justify-content-end mb-3">
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addSalaryIndexModal">
                            <i class="fas fa-plus"></i> Tambah Indeks Gaji
                        </button>
                    </div>

                    <!-- Tabel Indeks Gaji -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-clipboard-list"></i> Daftar Indeks Gaji</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="salaryIndicesTable" class="table table-sm table-bordered table-striped display nowrap" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Level</th>
                                            <th>Min. Masa Kerja (Thn)</th>
                                            <th>Max. Masa Kerja (Thn)</th>
                                            <th>Gaji Pokok (Rp)</th>
                                            <th>Keterangan</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Loading Spinner -->
                    <div id="loadingSpinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
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

    <!-- Modal Tambah Indeks Gaji -->
    <div class="modal fade" id="addSalaryIndexModal" tabindex="-1" aria-labelledby="addSalaryIndexModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="add-salary-index-form" class="needs-validation" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="addSalaryIndexModalLabel"><i class="fas fa-plus"></i> Tambah Indeks Gaji</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="case" value="AddSalaryIndex">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label for="level">Level <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="level" name="level" required>
                            <div class="invalid-feedback">Level belum diisi.</div>
                        </div>
                        <div class="form-group">
                            <label for="min_years">Min. Masa Kerja (Tahun) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="min_years" name="min_years" min="0" required>
                            <div class="invalid-feedback">Minimal masa kerja belum diisi.</div>
                        </div>
                        <div class="form-group">
                            <label for="max_years">Max. Masa Kerja (Tahun)</label>
                            <input type="number" class="form-control" id="max_years" name="max_years" min="0">
                        </div>
                        <div class="form-group">
                            <label for="base_salary">Gaji Pokok (Rp) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="base_salary" name="base_salary" min="0" step="0.01" required>
                            <div class="invalid-feedback">Gaji pokok belum diisi.</div>
                        </div>
                        <div class="form-group">
                            <label for="description">Keterangan</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
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

    <!-- Modal Edit Indeks Gaji -->
    <div class="modal fade" id="editSalaryIndexModal" tabindex="-1" aria-labelledby="editSalaryIndexModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="edit-salary-index-form" class="needs-validation" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="editSalaryIndexModalLabel"><i class="fas fa-edit"></i> Edit Indeks Gaji</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="case" value="UpdateSalaryIndex">
                        <input type="hidden" id="edit_id" name="edit_id">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label for="edit_level">Level <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_level" name="edit_level" required>
                            <div class="invalid-feedback">Level belum diisi.</div>
                        </div>
                        <div class="form-group">
                            <label for="edit_min_years">Min. Masa Kerja (Tahun) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit_min_years" name="edit_min_years" min="0" required>
                            <div class="invalid-feedback">Minimal masa kerja belum diisi.</div>
                        </div>
                        <div class="form-group">
                            <label for="edit_max_years">Max. Masa Kerja (Tahun)</label>
                            <input type="number" class="form-control" id="edit_max_years" name="edit_max_years" min="0">
                        </div>
                        <div class="form-group">
                            <label for="edit_base_salary">Gaji Pokok (Rp) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit_base_salary" name="edit_base_salary" min="0" step="0.01" required>
                            <div class="invalid-feedback">Gaji pokok belum diisi.</div>
                        </div>
                        <div class="form-group">
                            <label for="edit_description">Keterangan</label>
                            <textarea class="form-control" id="edit_description" name="edit_description" rows="3"></textarea>
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

    <!-- Modal Hapus Indeks Gaji -->
    <div class="modal fade" id="deleteSalaryIndexModal" tabindex="-1" aria-labelledby="deleteSalaryIndexModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form id="delete-salary-index-form">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title"><i class="fas fa-trash-alt"></i> Hapus Indeks Gaji</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                <span>&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
              <input type="hidden" id="delete_id" name="id">
              <p>Yakin ingin menghapus Indeks Gaji <strong id="delete_level"></strong>?</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Tidak</button>
              <button type="submit" class="btn btn-danger">
                <i class="fas fa-trash"></i> Ya, Hapus
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- JS Dependencies -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" nonce="<?php echo $nonce; ?>"></script>
    <!-- Bootstrap Bundle (termasuk Popper) -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>
    <!-- DataTables -->
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
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" nonce="<?php echo $nonce; ?>"></script>
    <!-- Bootstrap Notify (jika diperlukan) -->
    <script src="/payroll_absensi_v2/plugins/bootstrap-notify/bootstrap-notify.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script nonce="<?php echo $nonce; ?>">
    $(document).ready(function() {
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

        // Inisialisasi DataTable untuk Salary Indices
        var salaryIndicesTable = $('#salaryIndicesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "manage_salary_indices.php?ajax=1",
                type: "POST",
                data: function(d) {
                    d.case = 'LoadingSalaryIndices';
                    d.csrf_token = csrfToken;
                },
                beforeSend: function(){
                    $('#loadingSpinner').show();
                },
                complete: function(){
                    $('#loadingSpinner').hide();
                },
                error: function(){
                    showToast('Terjadi kesalahan saat memuat data.', 'error');
                }
            },
            columns: [
                { data: "no", orderable: false },
                { data: "level" },
                { data: "min_years" },
                { data: "max_years" },
                { data: "base_salary" },
                { data: "description" },
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
                    exportOptions: { columns: [0,1,2,3,4,5] }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> Export PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: { columns: [0,1,2,3,4,5] },
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
                    exportOptions: { columns: [0,1,2,3,4,5] }
                }
            ],
            responsive: true,
            autoWidth: false
        });

        // Tambah Indeks Gaji via Ajax
        $('#add-salary-index-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            var formData = form.serialize();
            $.ajax({
                url: "manage_salary_indices.php?ajax=1",
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
                        $('#addSalaryIndexModal').modal('hide');
                        salaryIndicesTable.ajax.reload(null, false);
                        form[0].reset();
                        form.removeClass('was-validated');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat menambah data.', 'error');
                }
            });
        });

        // Buka modal edit dan ambil detail data
        $(document).on('click', '.btn-edit', function() {
            var id = $(this).data('id');
            var modal = $('#editSalaryIndexModal');
            var form = $('#edit-salary-index-form');
            form[0].reset();
            form.removeClass('was-validated');
            $.ajax({
                url: "manage_salary_indices.php?ajax=1",
                type: "POST",
                data: { id: id, case: 'GetSalaryIndexDetail', csrf_token: csrfToken },
                dataType: "json",
                success: function(response){
                    if (response.code == 0) {
                        $('#edit_id').val(response.result.id);
                        $('#edit_level').val(response.result.level);
                        $('#edit_min_years').val(response.result.min_years);
                        $('#edit_max_years').val(response.result.max_years);
                        $('#edit_base_salary').val(response.result.base_salary);
                        $('#edit_description').val(response.result.description);
                        modal.modal('show');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    showToast('Terjadi kesalahan mengambil detail data.', 'error');
                }
            });
        });

        // Update Indeks Gaji via Ajax
        $('#edit-salary-index-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            var formData = form.serialize();
            $.ajax({
                url: "manage_salary_indices.php?ajax=1",
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
                        $('#editSalaryIndexModal').modal('hide');
                        salaryIndicesTable.ajax.reload(null, false);
                        form[0].reset();
                        form.removeClass('was-validated');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat update data.', 'error');
                }
            });
        });

        // Buka modal hapus
        $(document).on('click', '.btn-delete', function() {
            var id = $(this).data('id');
            $('#delete_id').val(id);
            var level = $(this).closest('tr').find('td:eq(1)').text();
            $('#delete_level').text(level);
            $('#deleteSalaryIndexModal').modal('show');
        });

        // Hapus data via Ajax
        $('#delete-salary-index-form').on('submit', function(e) {
            e.preventDefault();
            var id = $('#delete_id').val();
            if (!id) {
                showToast('ID tidak ditemukan.', 'error');
                return;
            }
            $.ajax({
                url: "manage_salary_indices.php?ajax=1",
                type: "POST",
                data: { id: id, case: 'DeleteSalaryIndex', csrf_token: csrfToken },
                dataType: "json",
                beforeSend: function(){
                    $('#delete-salary-index-form').find('button[type="submit"]').prop('disabled', true);
                    $('#delete-salary-index-form').find('.spinner-border').removeClass('d-none');
                },
                success: function(response){
                    $('#delete-salary-index-form').find('button[type="submit"]').prop('disabled', false);
                    $('#delete-salary-index-form').find('.spinner-border').addClass('d-none');
                    if (response.code == 0) {
                        showToast(response.result, 'success');
                        $('#deleteSalaryIndexModal').modal('hide');
                        salaryIndicesTable.ajax.reload(null, false);
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    $('#delete-salary-index-form').find('button[type="submit"]').prop('disabled', false);
                    $('#delete-salary-index-form').find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat menghapus data.', 'error');
                }
            });
        });

    });
    </script>
</body>
</html>
