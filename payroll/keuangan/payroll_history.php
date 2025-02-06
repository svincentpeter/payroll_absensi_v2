<?php
// File: /payroll_absensi_v2/payroll/keuangan/payroll_history.php (fix)

// =========================
// 1. Pengaturan Keamanan
// =========================
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => true,      // Hanya lewat HTTPS
    'httponly' => true,      // Tidak dapat diakses via JS
    'samesite' => 'Strict'
]);
session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$nonce = base64_encode(random_bytes(16));
$_SESSION['csp_nonce'] = $nonce;
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirect);
    exit();
}
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("Content-Security-Policy: default-src 'self'; 
    script-src 'self' https://code.jquery.com https://cdn.jsdelivr.net https://cdn.datatables.net https://cdnjs.cloudflare.com 'nonce-$nonce'; 
    style-src 'self' https://cdn.jsdelivr.net https://cdn.datatables.net https://cdnjs.cloudflare.com 'nonce-$nonce'; 
    img-src 'self'; 
    font-src 'self' https://cdnjs.cloudflare.com; 
    connect-src 'self'");

// Fungsi untuk mengirim response JSON
function send_response($code, $result) {
    if ($code !== 0) {
        error_log("Response Code $code: " . json_encode($result));
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => $code, 'result' => $result]);
    exit();
}

// Role Checking: Hanya untuk role 'sdm' dan 'superadmin'
function authorize($allowed_roles = ['sdm', 'superadmin']) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        send_response(403, 'Akses ditolak.');
    }
}

require_once __DIR__ . '/../../koneksi.php';
if (ob_get_length()) ob_end_clean();


// =========================
// 2. Sanitasi Input & Helper
// =========================
function bersihkan_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
function bulanIntToName($bulan) {
    $map = [
        1 => 'January', 2 => 'February', 3 => 'March',
        4 => 'April',   5 => 'May',       6 => 'June',
        7 => 'July',    8 => 'August',    9 => 'September',
        10 => 'October',11 => 'November',12 => 'December'
    ];
    return isset($map[$bulan]) ? $map[$bulan] : 'Unknown';
}


// =========================
// 3. Menangani Permintaan AJAX
// =========================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
            send_response(403, 'Token CSRF tidak valid.');
        }
        authorize();
        $case = isset($_POST['case']) ? bersihkan_input($_POST['case']) : '';
        switch ($case) {
            case 'LoadingPayrollHistory':
                LoadingPayrollHistory($conn);
                break;
            case 'ViewPayrollDetail':
                ViewPayrollDetail($conn);
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
// 4. Fungsi CRUD dan Helper
// =========================

function LoadingPayrollHistory($conn) {
    $draw    = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start   = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length  = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search  = isset($_POST['search']['value']) ? bersihkan_input($_POST['search']['value']) : '';
    $jenjang = isset($_POST['jenjang']) ? bersihkan_input($_POST['jenjang']) : '';
    $bulan   = isset($_POST['bulan']) ? intval($_POST['bulan']) : 0;
    $tahun   = isset($_POST['tahun']) ? intval($_POST['tahun']) : 0;

    $sqlBase = "
        FROM payroll p
        JOIN anggota_sekolah a ON p.id_anggota = a.id
        WHERE 1=1
    ";
    $params = [];
    $types  = "";
    if (!empty($jenjang)) {
        $sqlBase .= " AND a.jenjang = ?";
        $params[] = $jenjang;
        $types  .= "s";
    }
    if ($bulan > 0) {
        $sqlBase .= " AND p.bulan = ?";
        $params[] = $bulan;
        $types  .= "i";
    }
    if ($tahun > 0) {
        $sqlBase .= " AND p.tahun = ?";
        $params[] = $tahun;
        $types  .= "i";
    }
    if (!empty($search)) {
        $sqlBase .= " AND (
                        p.id LIKE ? 
                        OR a.id LIKE ?
                        OR a.nama LIKE ?
                        OR p.bulan LIKE ?
                        OR p.tahun LIKE ?
                        OR p.gaji_pokok LIKE ?
                        OR p.total_pendapatan LIKE ?
                        OR p.total_potongan LIKE ?
                        OR p.gaji_bersih LIKE ?
                      )";
        $searchParam = "%" . $search . "%";
        for ($i = 0; $i < 9; $i++) {
            $params[] = $searchParam;
            $types  .= "s";
        }
    }
    $sqlFilteredCount = "SELECT COUNT(*) AS total " . $sqlBase;
    $stmtFiltered = $conn->prepare($sqlFilteredCount);
    if ($stmtFiltered === false) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    if (!empty($params)) {
        $stmtFiltered->bind_param($types, ...$params);
    }
    $stmtFiltered->execute();
    $resFiltered = $stmtFiltered->get_result();
    $rowFiltered = $resFiltered->fetch_assoc();
    $totalFiltered = $rowFiltered['total'];
    $stmtFiltered->close();

    $sqlTotal = "SELECT COUNT(*) AS total FROM payroll";
    $resTotal = $conn->query($sqlTotal);
    if (!$resTotal) {
        send_response(1, 'Query gagal: ' . $conn->error);
    }
    $rowTotal = $resTotal->fetch_assoc();
    $recordsTotal = $rowTotal['total'];

    $sqlData = "
        SELECT p.id, a.nama, a.jenjang, p.bulan, p.tahun, p.gaji_pokok, p.total_pendapatan, p.total_potongan, p.gaji_bersih
        " . $sqlBase;
    $orderBy = " ORDER BY p.id DESC";
    if (isset($_POST['order'], $_POST['columns'])) {
        $columnIndex = intval($_POST['order'][0]['column']);
        $allowedColumns = ['id', 'nama', 'jenjang', 'bulan', 'tahun', 'gaji_pokok', 'total_pendapatan', 'total_potongan', 'gaji_bersih'];
        if (isset($_POST['columns'][$columnIndex]['data']) && in_array($_POST['columns'][$columnIndex]['data'], $allowedColumns)) {
            $colName = $_POST['columns'][$columnIndex]['data'];
            $colSortOrder = ($_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
            $mapCol = [
                'id' => 'p.id',
                'nama' => 'a.nama',
                'jenjang' => 'a.jenjang',
                'bulan' => 'p.bulan',
                'tahun' => 'p.tahun',
                'gaji_pokok' => 'p.gaji_pokok',
                'total_pendapatan' => 'p.total_pendapatan',
                'total_potongan' => 'p.total_potongan',
                'gaji_bersih' => 'p.gaji_bersih'
            ];
            if (isset($mapCol[$colName])) {
                $orderBy = " ORDER BY " . $mapCol[$colName] . " " . $colSortOrder;
            }
        }
    }
    $limit = " LIMIT ?, ?";
    $paramsData = $params;
    $typesData  = $types;
    $paramsData[] = $start;
    $paramsData[] = $length;
    $typesData   .= "ii";
    $sqlData .= $orderBy . $limit;
    $stmtData = $conn->prepare($sqlData);
    if ($stmtData === false) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    if (!empty($paramsData)) {
        $stmtData->bind_param($typesData, ...$paramsData);
    }
    $stmtData->execute();
    $resData = $stmtData->get_result();
    $data = [];
    while ($row = $resData->fetch_assoc()) {
        // Gunakan dropdown dengan ikon tiga titik vertikal untuk kolom aksi
        $aksi = '
<div class="dropdown">
  <button class="btn" type="button" id="dropdownMenuButton_' . htmlspecialchars($row['id']) . '" 
          data-bs-toggle="dropdown" aria-expanded="false">
    <i class="bi bi-three-dots-vertical"></i>
  </button>
  <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton_' . htmlspecialchars($row['id']) . '">
    <li><a class="dropdown-item" href="payroll-details.php?id_payroll=' . $row['id'] . '">
      <i class="fas fa-file-invoice"></i> Lihat Payroll
    </a></li>
    <li><a class="dropdown-item btn-view-full-detail" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '">
      <i class="fas fa-eye"></i> View Detail
    </a></li>
  </ul>
</div>';
        $data[] = [
            "id"             => htmlspecialchars($row['id']),
            "nama"           => bersihkan_input($row['nama']),
            "jenjang"        => bersihkan_input($row['jenjang']),
            "bulan"          => bulanIntToName($row['bulan']),
            "tahun"          => $row['tahun'],
            "gaji_pokok"     => 'Rp ' . number_format($row['gaji_pokok'], 2, ',', '.'),
            "total_pendapatan" => 'Rp ' . number_format($row['total_pendapatan'], 2, ',', '.'),
            "total_potongan"   => 'Rp ' . number_format($row['total_potongan'], 2, ',', '.'),
            "gaji_bersih"    => 'Rp ' . number_format($row['gaji_bersih'], 2, ',', '.'),
            "aksi"           => $aksi
        ];
    }
    $stmtData->close();
    $json_data = [
        "draw"            => $draw,
        "recordsTotal"    => $recordsTotal,
        "recordsFiltered" => $totalFiltered,
        "data"            => $data
    ];
    echo json_encode($json_data, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Fungsi untuk menampilkan detail lengkap payroll beserta rincian komponen pendapatan dan potongan.
 */
function ViewPayrollDetail($conn) {
    $id_payroll = isset($_POST['id_payroll']) ? intval($_POST['id_payroll']) : 0;
    if ($id_payroll <= 0) {
        send_response(1, 'ID Payroll tidak valid.');
    }
    // Ambil data payroll beserta data karyawan dan salary_indices
    $stmt = $conn->prepare("
        SELECT p.*, 
               a.uid, a.nip, a.nama, a.jenjang, a.role, a.job_title, a.status_kerja, 
               a.masa_kerja_tahun, a.masa_kerja_bulan, a.no_rekening, a.email, a.jenis_kelamin, a.agama,
               si.level AS salary_index_level, si.base_salary AS salary_index_base
        FROM payroll p 
        JOIN anggota_sekolah a ON p.id_anggota = a.id 
        LEFT JOIN salary_indices si ON a.salary_index_id = si.id
        WHERE p.id = ? LIMIT 1
    ");
    if ($stmt === false) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("i", $id_payroll);
    if (!$stmt->execute()) {
        send_response(1, 'Execute failed: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        send_response(1, 'Payroll tidak ditemukan.');
    }
    $row = $result->fetch_assoc();
    $stmt->close();

    // Format angka dan bulan
    $row['gaji_pokok']      = 'Rp ' . number_format($row['gaji_pokok'], 2, ',', '.');
    $row['total_pendapatan'] = 'Rp ' . number_format($row['total_pendapatan'], 2, ',', '.');
    $row['total_potongan']   = 'Rp ' . number_format($row['total_potongan'], 2, ',', '.');
    $row['gaji_bersih']      = 'Rp ' . number_format($row['gaji_bersih'], 2, ',', '.');
    $row['bulan']            = bulanIntToName($row['bulan']);
    $masaKerja = '';
    if ($row['masa_kerja_tahun'] > 0) {
        $masaKerja .= $row['masa_kerja_tahun'] . ' Thn ';
    }
    if ($row['masa_kerja_bulan'] > 0) {
        $masaKerja .= $row['masa_kerja_bulan'] . ' Bln';
    }
    $row['masa_kerja'] = trim($masaKerja) ?: '-';

    // Ambil detail payroll dari tabel payroll_detail dengan nama payhead
    $stmtPD = $conn->prepare("
        SELECT pd.*, ph.nama_payhead 
        FROM payroll_detail pd 
        JOIN payheads ph ON pd.id_payhead = ph.id
        WHERE pd.id_payroll = ?
    ");
    if ($stmtPD === false) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmtPD->bind_param("i", $id_payroll);
    if (!$stmtPD->execute()) {
        send_response(1, 'Execute failed: ' . $stmtPD->error);
    }
    $resultPD = $stmtPD->get_result();
    $payheads_detail = [];
    while ($rowPD = $resultPD->fetch_assoc()) {
        $payheads_detail[] = [
            'id_payhead'   => $rowPD['id_payhead'],
            'nama_payhead' => $rowPD['nama_payhead'],
            'jenis'        => $rowPD['jenis'],
            'amount'       => $rowPD['amount']
        ];
    }
    $stmtPD->close();
    $row['payheads_detail'] = $payheads_detail;

    send_response(0, $row);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>History Payroll - Payroll Management System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" nonce="<?php echo $nonce; ?>">
    <!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="/payroll_absensi_v2/assets/css/sb-admin-2.min.css" nonce="<?php echo $nonce; ?>">
    <!-- DataTables CSS for Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.1.1/css/buttons.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" nonce="<?php echo $nonce; ?>">
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
        thead th {
            background-color: #343a40;
            color: white;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
        }
        tbody tr:nth-of-type(odd) {
            background-color: #f9f9f9;
        }
        tbody tr:nth-of-type(even) {
            background-color: #ffffff;
        }
        #payrollTable th, #payrollTable td {
            font-size: 14px;
            vertical-align: middle;
            white-space: nowrap;
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
            .row .col-auto {
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body id="page-top" class="sb-nav-fixed">
    <!-- Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include(__DIR__ . '/../../sidebar.php'); ?>
        <!-- End Sidebar -->
        <!-- CONTENT WRAPPER -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- MAIN CONTENT -->
            <div id="content">
                <!-- Navbar -->
                <?php include __DIR__ . '/../../navbar.php'; ?>
                
                <!-- Breadcrumb -->
                <div class="container-fluid">
                    <nav aria-label="breadcrumb">
                      <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="/payroll_absensi_v2/payroll/keuangan/dashboard_keuangan.php">Home</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">History Payroll</li>
                      </ol>
                    </nav>
                </div>
                <!-- End Topbar -->
                <!-- Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">
                        <i class="fas fa-history"></i> History Payroll
                    </h1>
                    <!-- Filter Section -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-white">
                            <strong>Filter Payroll History</strong>
                        </div>
                        <div class="card-body">
                            <form id="filterPayrollForm" class="row gy-2 gx-3 align-items-center">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <div class="col-auto">
                                    <label for="filterJenjang" class="form-label"><strong>Jenjang:</strong></label>
                                    <select class="form-select" id="filterJenjang" name="jenjang">
                                        <option value="">Semua Jenjang</option>
                                        <?php
                                            $stmtJenjang = $conn->prepare("SELECT DISTINCT jenjang FROM anggota_sekolah ORDER BY jenjang ASC");
                                            if ($stmtJenjang) {
                                                $stmtJenjang->execute();
                                                $resJenjang = $stmtJenjang->get_result();
                                                while ($row = $resJenjang->fetch_assoc()) {
                                                    echo '<option value="' . htmlspecialchars($row['jenjang']) . '">' . htmlspecialchars($row['jenjang']) . '</option>';
                                                }
                                                $stmtJenjang->close();
                                            }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <label for="filterBulan" class="form-label"><strong>Bulan:</strong></label>
                                    <select class="form-select" id="filterBulan" name="bulan">
                                        <option value="">Semua Bulan</option>
                                        <?php
                                            for ($m=1; $m<=12; $m++) {
                                                echo "<option value=\"$m\">" . bulanIntToName($m) . "</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <label for="filterTahun" class="form-label"><strong>Tahun:</strong></label>
                                    <select class="form-select" id="filterTahun" name="tahun">
                                        <option value="">Semua Tahun</option>
                                        <?php
                                            $stmtTahun = $conn->prepare("SELECT DISTINCT tahun FROM payroll ORDER BY tahun DESC");
                                            if ($stmtTahun) {
                                                $stmtTahun->execute();
                                                $resTahun = $stmtTahun->get_result();
                                                while ($row = $resTahun->fetch_assoc()) {
                                                    echo '<option value="' . htmlspecialchars($row['tahun']) . '">' . htmlspecialchars($row['tahun']) . '</option>';
                                                }
                                                $stmtTahun->close();
                                            }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-primary" id="btnApplyFilterPayroll">
                                        <i class="fas fa-filter"></i> Terapkan Filter
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="btnResetFilterPayroll">
                                        <i class="fas fa-undo"></i> Reset Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- End Filter Section -->
                    <!-- Tabel History Payroll -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 bg-primary">
                            <h6 class="m-0 text-white">
                                <i class="fas fa-clipboard-list"></i> Daftar History Payroll
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="payrollTable" class="table table-sm table-bordered table-striped display nowrap" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>ID Payroll</th>
                                            <th>Nama Karyawan</th>
                                            <th>Jenjang</th>
                                            <th>Bulan</th>
                                            <th>Tahun</th>
                                            <th>Gaji Pokok</th>
                                            <th>Total Pendapatan</th>
                                            <th>Total Potongan</th>
                                            <th>Gaji Bersih</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Container Fluid -->
            </div>
            <!-- End Content -->
            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?php echo date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div>
        <!-- End Content Wrapper -->
    </div>
    <!-- End Wrapper -->

    <!-- Modal: Detail Payroll -->
    <div class="modal fade" id="detailPayrollModal" tabindex="-1" aria-labelledby="detailPayrollModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailPayrollModalLabel">Detail Payroll</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body" id="detailPayrollContent">
                    <p>Memuat detail payroll...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times-circle"></i> Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- JS Dependencies -->
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
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/js/sb-admin-2.min.js" nonce="<?php echo $nonce; ?>"></script>
    <!-- Sertakan plugin AutoNumeric -->
    <script src="https://cdn.jsdelivr.net/npm/autonumeric@4.6.0/dist/autoNumeric.min.js" nonce="<?php echo $nonce; ?>"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" nonce="<?php echo $nonce; ?>"></script>
    <script nonce="<?php echo $nonce; ?>">
    $(document).ready(function() {
        // Inisialisasi tooltip Bootstrap 5
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
        var csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

        // Inisialisasi DataTable untuk Payroll History
        var payrollTable = $('#payrollTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "payroll_history.php?ajax=1",
                type: "POST",
                data: function(d) {
                    d.case = 'LoadingPayrollHistory';
                    d.csrf_token = csrfToken;
                    d.jenjang = $('#filterJenjang').val();
                    d.bulan   = $('#filterBulan').val();
                    d.tahun   = $('#filterTahun').val();
                },
                beforeSend: function() {
                    $('#loadingSpinner').show();
                },
                complete: function() {
                    $('#loadingSpinner').hide();
                },
                error: function() {
                    Swal.fire({ icon: 'error', title: 'Terjadi kesalahan saat memuat data payroll.' });
                }
            },
            columns: [
                { data:'id', name:'id' },
                { data:'nama', name:'nama' },
                { data:'jenjang', name:'jenjang' },
                { data:'bulan', name:'bulan' },
                { data:'tahun', name:'tahun' },
                { data:'gaji_pokok', name:'gaji_pokok' },
                { data:'total_pendapatan', name:'total_pendapatan' },
                { data:'total_potongan', name:'total_potongan' },
                { data:'gaji_bersih', name:'gaji_bersih' },
                { data:'aksi', orderable:false, searchable:false }
            ],
            order: [[0, 'desc']],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
            },
            responsive: true,
            autoWidth: false,
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Export Excel',
                    className: 'btn btn-success btn-sm',
                    exportOptions: { columns: [0,1,2,3,4,5,6,7,8] }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> Export PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: { columns: [0,1,2,3,4,5,6,7,8] },
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
                    exportOptions: { columns: [0,1,2,3,4,5,6,7,8] }
                }
            ]
        });

        // Filter events
        $('#btnApplyFilterPayroll').on('click', function(){
            payrollTable.ajax.reload();
        });
        $('#btnResetFilterPayroll').on('click', function(){
            $('#filterPayrollForm')[0].reset();
            payrollTable.ajax.reload();
        });

        // Event: Tampilkan Detail Payroll Lengkap (modal dengan breakdown grid)
        $(document).on('click', '.btn-view-full-detail', function() {
            var idPayroll = $(this).data('id');
            if (idPayroll) {
                $.ajax({
                    url: "payroll_history.php?ajax=1",
                    type: "POST",
                    data: {
                        case: 'ViewPayrollDetail',
                        id_payroll: idPayroll,
                        csrf_token: csrfToken
                    },
                    beforeSend: function() {
                        $('#detailPayrollContent').html('<p>Memuat detail payroll...</p>');
                        var detailModal = new bootstrap.Modal(document.getElementById('detailPayrollModal'));
                        detailModal.show();
                    },
                    success: function(response) {
                        if (response.code === 0) {
                            var d = response.result;
                            var html = '<table class="table table-bordered">';
                            html += '<tr><th>ID Payroll</th><td>' + d.id + '</td></tr>';
                            html += '<tr><th>UID</th><td>' + (d.uid || '-') + '</td></tr>';
                            html += '<tr><th>NIP</th><td>' + (d.nip || '-') + '</td></tr>';
                            html += '<tr><th>Nama</th><td>' + d.nama + '</td></tr>';
                            html += '<tr><th>Jenjang Pendidikan</th><td>' + d.jenjang + '</td></tr>';
                            html += '<tr><th>Role</th><td>' + (d.role ? d.role : '-') + '</td></tr>';
                            html += '<tr><th>Job Title</th><td>' + (d.job_title ? d.job_title : '-') + '</td></tr>';
                            html += '<tr><th>Status Kerja</th><td>' + (d.status_kerja ? d.status_kerja : '-') + '</td></tr>';
                            html += '<tr><th>Masa Kerja</th><td>' + d.masa_kerja + '</td></tr>';
                            html += '<tr><th>No Rekening</th><td>' + (d.no_rekening ? d.no_rekening : '-') + '</td></tr>';
                            html += '<tr><th>Email</th><td>' + (d.email ? d.email : '-') + '</td></tr>';
                            html += '<tr><th>Jenis Kelamin</th><td>' + (d.jenis_kelamin ? d.jenis_kelamin : '-') + '</td></tr>';
                            html += '<tr><th>Agama</th><td>' + (d.agama ? d.agama : '-') + '</td></tr>';
                            html += '<tr><th>Gaji Pokok & Salary Indeks</th><td>' + d.gaji_pokok + '</td></tr>';
                            html += '<tr><th>Total Pendapatan</th><td>' + d.total_pendapatan;
                            var earnings = [];
                            if(d.payheads_detail && d.payheads_detail.length > 0){
                                d.payheads_detail.forEach(function(ph){
                                    if(ph.jenis === 'earnings'){
                                        earnings.push(ph);
                                    }
                                });
                            }
                            if(earnings.length > 0){
                                html += '<div class="row mt-2">';
                                earnings.forEach(function(ph){
                                    var nominal = parseFloat(ph.amount).toLocaleString('id-ID', {minimumFractionDigits:2});
                                    html += '<div class="col-12 mb-1"><span class="badge bg-success me-2">' + ph.nama_payhead + '</span> <span class="text-success">Rp ' + nominal + '</span></div>';
                                });
                                html += '</div>';
                            }
                            html += '</td></tr>';
                            html += '<tr><th>Total Potongan</th><td>' + d.total_potongan;
                            var deductions = [];
                            if(d.payheads_detail && d.payheads_detail.length > 0){
                                d.payheads_detail.forEach(function(ph){
                                    if(ph.jenis === 'deductions'){
                                        deductions.push(ph);
                                    }
                                });
                            }
                            if(deductions.length > 0){
                                html += '<div class="row mt-2">';
                                deductions.forEach(function(ph){
                                    var nominal = parseFloat(ph.amount).toLocaleString('id-ID', {minimumFractionDigits:2});
                                    html += '<div class="col-12 mb-1"><span class="badge bg-danger me-2">' + ph.nama_payhead + '</span> <span class="text-danger">Rp ' + nominal + '</span></div>';
                                });
                                html += '</div>';
                            }
                            html += '</td></tr>';
                            html += '<tr><th>Gaji Bersih</th><td>' + d.gaji_bersih + '</td></tr>';
                            html += '<tr><th>Bulan</th><td>' + d.bulan + '</td></tr>';
                            html += '<tr><th>Tahun</th><td>' + d.tahun + '</td></tr>';
                            html += '</table>';
                            $('#detailPayrollContent').html(html);
                        } else {
                            $('#detailPayrollContent').html('<p>' + response.result + '</p>');
                        }
                    },
                    error: function() {
                        $('#detailPayrollContent').html('<p>Terjadi kesalahan saat memuat detail payroll.</p>');
                    }
                });
            }
        });
    });
    </script>
</body>
</html>
