<?php
// File: /payroll_absensi_v2/payroll/keuangan/payroll_history.php

// =========================
// 1. Pengaturan Keamanan
// =========================

// Atur session cookie parameters sebelum session_start()
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => true,      // Hanya lewat HTTPS
    'httponly' => true,      // Tidak dapat diakses melalui JavaScript
    'samesite' => 'Strict'
]);

// Mulai session
session_start();

// Jika CSRF token belum ada, buat token baru
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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

// Fungsi sanitasi input
function bersihkan_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Fungsi pengiriman response JSON
function send_response($code, $result) {
    if ($code !== 0) {
        error_log("Response Code $code: " . json_encode($result));
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => $code, 'result' => $result]);
    exit();
}

// Fungsi untuk pengecekan role
function authorize($allowed_roles = ['keuangan', 'superadmin']) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        send_response(403, 'Akses ditolak.');
    }
}

// Koneksi ke database
require_once __DIR__ . '/../../koneksi.php';

// Nonaktifkan output buffering jika ada
if (ob_get_length()) ob_end_clean();


// =========================
// 2. Menangani Permintaan AJAX
// =========================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verifikasi CSRF token
        $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
            send_response(403, 'Token CSRF tidak valid.');
        }
        // Periksa role
        authorize();
        
        // Ambil case dari parameter POST
        $case = isset($_POST['case']) ? bersihkan_input($_POST['case']) : '';
        
        switch ($case) {
            case 'LoadingPayrollHistory':
                LoadingPayrollHistory($conn);
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
// 3. Fungsi CRUD dan Helper untuk Payroll History
// =========================

/**
 * Fungsi konversi integer bulan ke nama bulan (English)
 */
function bulanIntToName($bulan) {
    $map = [
        1 => 'January', 2 => 'February', 3 => 'March',
        4 => 'April',   5 => 'May',       6 => 'June',
        7 => 'July',    8 => 'August',    9 => 'September',
        10 => 'October',11 => 'November',12 => 'December'
    ];
    return isset($map[$bulan]) ? $map[$bulan] : 'Unknown';
}

/**
 * Fungsi LoadingPayrollHistory: memuat data payroll history untuk DataTables
 */
function LoadingPayrollHistory($conn) {
    // Parameter DataTables
    $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = isset($_POST['search']['value']) ? bersihkan_input($_POST['search']['value']) : '';

    // Filter Parameters
    $jenjang = isset($_POST['jenjang']) ? bersihkan_input($_POST['jenjang']) : '';
    $bulan   = isset($_POST['bulan']) ? intval($_POST['bulan']) : 0;
    $tahun   = isset($_POST['tahun']) ? intval($_POST['tahun']) : 0;

    // Query dasar: join payroll, anggota_sekolah, dan salary_indices (untuk level indeks)
    $sqlBase = "
        FROM payroll p
        JOIN anggota_sekolah a ON p.id_anggota = a.id
        LEFT JOIN salary_indices si ON a.salary_index_id = si.id
        WHERE 1=1
    ";

    $params = [];
    $types  = "";

    if (!empty($jenjang)) {
        $sqlBase .= " AND a.jenjang = ?";
        $params[] = $jenjang;
        $types   .= "s";
    }
    if ($bulan > 0) {
        $sqlBase .= " AND p.bulan = ?";
        $params[] = $bulan;
        $types   .= "i";
    }
    if ($tahun > 0) {
        $sqlBase .= " AND p.tahun = ?";
        $params[] = $tahun;
        $types   .= "i";
    }
    if (!empty($search)) {
        $sqlBase .= " AND (
                        p.id LIKE ? OR
                        a.id LIKE ? OR
                        a.uid LIKE ? OR
                        a.nama LIKE ? OR
                        a.jenjang LIKE ? OR
                        a.role LIKE ? OR
                        a.job_title LIKE ? OR
                        si.level LIKE ? OR
                        si.base_salary LIKE ? OR
                        p.bulan LIKE ? OR
                        p.tahun LIKE ? OR
                        p.gaji_pokok LIKE ? OR
                        p.total_pendapatan LIKE ? OR
                        p.total_potongan LIKE ? OR
                        p.gaji_bersih LIKE ? OR
                        a.no_rekening LIKE ? OR
                        a.email LIKE ?
                      )";
        $searchParam = "%" . $search . "%";
        // 17 kolom untuk kondisi search
        for ($i = 0; $i < 17; $i++) {
            $params[] = $searchParam;
            $types   .= "s";
        }
    }

    // Hitung total records terfilter
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

    // Hitung total records (tanpa filter)
    $sqlTotal = "SELECT COUNT(*) AS total FROM payroll";
    $resTotal = $conn->query($sqlTotal);
    if (!$resTotal) {
        send_response(1, 'Query gagal: ' . $conn->error);
    }
    $rowTotal = $resTotal->fetch_assoc();
    $recordsTotal = $rowTotal['total'];

    // Query untuk mengambil data dengan kolom lengkap
    $sqlData = "
        SELECT p.id, a.uid, a.nama, a.jenjang, a.role, a.job_title, si.level AS salary_index_level, 
               si.base_salary AS salary_index_base, p.bulan, p.tahun, p.gaji_pokok, p.total_pendapatan, 
               p.total_potongan, p.gaji_bersih, a.no_rekening, a.email
        " . $sqlBase;

    // Ordering: izinkan pengurutan pada kolom-kolom yang ditampilkan
    $orderBy = " ORDER BY p.id DESC";
    if (isset($_POST['order'][0]['column']) && isset($_POST['columns'])) {
        $columnIndex = intval($_POST['order'][0]['column']);
        // Daftar kolom yang diizinkan
        $allowedColumns = [
            'id', 'uid', 'nama', 'jenjang', 'role', 'job_title', 'salary_index_level', 'salary_index_base',
            'bulan', 'tahun', 'gaji_pokok', 'total_pendapatan', 'total_potongan', 'gaji_bersih', 'no_rekening', 'email'
        ];
        if (isset($_POST['columns'][$columnIndex]['data']) &&
            in_array($_POST['columns'][$columnIndex]['data'], $allowedColumns)) {
            $colName = $_POST['columns'][$columnIndex]['data'];
            $colSortOrder = ($_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
            $mapCol = [
                'id' => 'p.id',
                'uid' => 'a.uid',
                'nama' => 'a.nama',
                'jenjang' => 'a.jenjang',
                'role' => 'a.role',
                'job_title' => 'a.job_title',
                'salary_index_level' => 'si.level',
                'salary_index_base' => 'si.base_salary',
                'bulan' => 'p.bulan',
                'tahun' => 'p.tahun',
                'gaji_pokok' => 'p.gaji_pokok',
                'total_pendapatan' => 'p.total_pendapatan',
                'total_potongan' => 'p.total_potongan',
                'gaji_bersih' => 'p.gaji_bersih',
                'no_rekening' => 'a.no_rekening',
                'email' => 'a.email'
            ];
            if (isset($mapCol[$colName])) {
                $orderBy = " ORDER BY " . $mapCol[$colName] . " " . $colSortOrder;
            }
        }
    }

    // LIMIT untuk paging
    $limit = " LIMIT ?, ?";
    $paramsData = $params;
    $typesData = $types;
    $paramsData[] = $start;
    $paramsData[] = $length;
    $typesData .= "ii";

    $sqlData .= $orderBy . $limit;
    $stmtData = $conn->prepare($sqlData);
    if ($stmtData === false) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    if (!empty($paramsData)) {
        $stmtData->bind_param($typesData, ...$paramsData);
    }
    if (!$stmtData->execute()) {
        send_response(1, 'Execute failed: ' . $stmtData->error);
    }
    $resData = $stmtData->get_result();

    $data = [];
    while ($row = $resData->fetch_assoc()) {
        // Format angka ke format rupiah untuk kolom-kolom numerik
        $formattedGajiPokok = 'Rp ' . number_format($row['gaji_pokok'], 2, ',', '.');
        $formattedSalaryIndex = 'Rp ' . number_format($row['salary_index_base'], 2, ',', '.');
        $formattedPendapatan = 'Rp ' . number_format($row['total_pendapatan'], 2, ',', '.');
        $formattedPotongan = 'Rp ' . number_format($row['total_potongan'], 2, ',', '.');
        $formattedGajiBersih = 'Rp ' . number_format($row['gaji_bersih'], 2, ',', '.');

        // Tombol aksi untuk melihat detail payroll
        $aksi = '
<div class="dropdown">
  <button class="btn btn-sm" type="button" id="dropdownMenuButton_' . htmlspecialchars($row['id']) . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <i class="fas fa-ellipsis-v"></i>
  </button>
  <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_' . htmlspecialchars($row['id']) . '">
    <a class="dropdown-item btn-view-detail" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '">
      <i class="fas fa-eye"></i> Lihat Detail
    </a>
  </div>
</div>';

        $data[] = [
            "id"                 => htmlspecialchars($row['id']),
            "uid"                => htmlspecialchars($row['uid']),
            "nama"               => bersihkan_input($row['nama']),
            "jenjang"            => bersihkan_input($row['jenjang']),
            "role"               => htmlspecialchars($row['role']),
            "job_title"          => htmlspecialchars($row['job_title']),
            "salary_index_level" => htmlspecialchars($row['salary_index_level'] ?? '-'),
            "salary_index_base"  => $formattedSalaryIndex,
            "bulan"              => bulanIntToName($row['bulan']),
            "tahun"              => $row['tahun'],
            "gaji_pokok"         => $formattedGajiPokok,
            "total_pendapatan"   => $formattedPendapatan,
            "total_potongan"     => $formattedPotongan,
            "gaji_bersih"        => $formattedGajiBersih,
            "no_rekening"        => htmlspecialchars($row['no_rekening']),
            "email"              => htmlspecialchars($row['email']),
            "aksi"               => $aksi
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>History Payroll - Payroll Management System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- CSS Bootstrap & SB Admin 2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/css/sb-admin-2.min.css" nonce="<?php echo $nonce; ?>">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.1.1/css/buttons.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css" nonce="<?php echo $nonce; ?>">
    <!-- Bootstrap Notify CSS -->
    <link rel="stylesheet" href="/payroll_absensi_v2/plugins/bootstrap-notify/bootstrap-notify.min.css" nonce="<?php echo $nonce; ?>">
    <style nonce="<?php echo $nonce; ?>">
        /* Custom Styles */
        .btn {
            transition: background-color 0.3s, transform 0.2s;
        }
        .btn:hover { transform: scale(1.05); }
        .card-header { background: linear-gradient(45deg, #0d47a1, #42a5f5); color: white; }
        .table-hover tbody tr:hover { background-color: #e2e6ea; }
        thead th {
            background-color: #343a40;
            color: white;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
        }
        tbody tr:nth-of-type(odd) { background-color: #f9f9f9; }
        tbody tr:nth-of-type(even) { background-color: #ffffff; }
        #payrollTable th, #payrollTable td { font-size: 14px; vertical-align: middle; white-space: nowrap; }
        .table-responsive { overflow-x: auto; }
        .spinner-border { margin-left: 5px; }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include(__DIR__ . '/../../sidebar.php'); ?>
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Navbar -->
                <?php include(__DIR__ . '/../../navbar.php'); ?>
                <!-- Breadcrumb -->
                <div class="container-fluid">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="/payroll_absensi_v2/absensi/sdm/dashboard_sdm.php">Home</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Manajemen Karyawan</li>
                        </ol>
                    </nav>
                </div>
                <!-- Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">
                        <i class="bi bi-people-fill"></i> History Payroll
                    </h1>

                    <!-- Filter Section -->
                    <div class="card mb-4">
                        <div class="card-header font-weight-bold">
                            <i class="bi bi-filter-square-fill"></i> Filter Payroll History
                        </div>
                        <div class="card-body">
                            <form id="filterPayrollForm" class="form-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <div class="form-group mb-2 me-3">
                                    <label for="filterJenjang" class="me-2"><strong>Jenjang:</strong></label>
                                    <select class="form-control" id="filterJenjang" name="jenjang">
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
                                            } else {
                                                echo '<option value="">Tidak ada data</option>';
                                            }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group mb-2 me-3">
                                    <label for="filterBulan" class="me-2"><strong>Bulan:</strong></label>
                                    <select class="form-control" id="filterBulan" name="bulan">
                                        <option value="">Semua Bulan</option>
                                        <?php
                                            for ($m=1; $m<=12; $m++) {
                                                echo "<option value=\"$m\">" . bulanIntToName($m) . "</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group mb-2 me-3">
                                    <label for="filterTahun" class="me-2"><strong>Tahun:</strong></label>
                                    <select class="form-control" id="filterTahun" name="tahun">
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
                                <div class="form-group mb-2">
                                    <button type="button" class="btn btn-primary me-2" id="btnApplyFilterPayroll">
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

                    <div id="alert-placeholder"></div>

                    <!-- Tabel History Payroll -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-white">
                                <i class="fas fa-clipboard-list"></i> Daftar History Payroll
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="payrollTable" class="table table-sm table-bordered table-striped display nowrap" style="width:100%">
                                    <thead class="thead">
                                        <tr>
                                            <th>ID Payroll</th>
                                            <th>UID</th>
                                            <th>Nama Karyawan</th>
                                            <th>Jenjang</th>
                                            <th>Role</th>
                                            <th>Job Title</th>
                                            <th>Level Indeks</th>
                                            <th>Salary Index</th>
                                            <th>Bulan</th>
                                            <th>Tahun</th>
                                            <th>Gaji Pokok</th>
                                            <th>Total Pendapatan</th>
                                            <th>Total Potongan</th>
                                            <th>Gaji Bersih</th>
                                            <th>No. Rekening</th>
                                            <th>Email</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Container -->
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
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Payroll</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Konten Detail Payroll dimuat melalui AJAX -->
                    <div id="detailPayrollContent">
                        <p>Memuat...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
    <!-- End Modal: Detail Payroll -->

    <!-- Loading Spinner -->
    <div id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>

    <!-- JS Dependencies dengan nonce -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/2.11.8/umd/popper.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.5.3/js/bootstrap.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
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
    <script src="/payroll_absensi_v2/plugins/bootstrap-notify/bootstrap-notify.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/js/sb-admin-2.min.js" nonce="<?php echo $nonce; ?>"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" nonce="<?php echo $nonce; ?>"></script>
    <script nonce="<?php echo $nonce; ?>">
    $(document).ready(function() {
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
            Toast.fire({ icon: icon, title: message });
        }

        var csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

        // Inisialisasi DataTable untuk History Payroll
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
                    showToast('Terjadi kesalahan saat memuat data payroll.', 'error');
                }
            },
            columns: [
                { data:'id', name:'id' },
                { data:'uid', name:'uid' },
                { data:'nama', name:'nama' },
                { data:'jenjang', name:'jenjang' },
                { data:'role', name:'role' },
                { data:'job_title', name:'job_title' },
                { data:'salary_index_level', name:'salary_index_level' },
                { data:'salary_index_base', name:'salary_index_base' },
                { data:'bulan', name:'bulan' },
                { data:'tahun', name:'tahun' },
                { data:'gaji_pokok', name:'gaji_pokok' },
                { data:'total_pendapatan', name:'total_pendapatan' },
                { data:'total_potongan', name:'total_potongan' },
                { data:'gaji_bersih', name:'gaji_bersih' },
                { data:'no_rekening', name:'no_rekening' },
                { data:'email', name:'email' },
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
                    exportOptions: { columns: [0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15] }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> Export PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: { columns: [0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15] },
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
                    exportOptions: { columns: [0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15] }
                }
            ]
        });

        // EVENT: Terapkan Filter
        $('#btnApplyFilterPayroll').on('click', function(){
            payrollTable.ajax.reload();
        });

        // EVENT: Reset Filter
        $('#btnResetFilterPayroll').on('click', function(){
            $('#filterPayrollForm')[0].reset();
            payrollTable.ajax.reload();
        });

        // EVENT: Tampilkan Detail Payroll
        $(document).on('click', '.btn-view-detail', function() {
            var idPayroll = $(this).data('id');
            if (idPayroll) {
                $.ajax({
                    url: "payroll_details.php",
                    type: "GET",
                    data: { id_payroll: idPayroll },
                    beforeSend: function(){
                        $('#detailPayrollContent').html('<p>Memuat...</p>');
                        $('#detailPayrollModal').modal('show');
                    },
                    success: function(response){
                        $('#detailPayrollContent').html(response);
                    },
                    error: function(){
                        $('#detailPayrollContent').html('<p>Terjadi kesalahan saat memuat detail payroll.</p>');
                    }
                });
            }
        });
    });
    </script>
</body>
</html>
