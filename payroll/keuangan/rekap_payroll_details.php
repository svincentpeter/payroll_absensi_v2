<?php
// File: /payroll_absensi_v2/payroll/keuangan/rekap_payroll_details.php (fix)

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

// Sertakan koneksi dan helpers
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../helpers.php';

// Mulai session, error handling, dan generate CSRF token
start_session_safe();
init_error_handling();
generate_csrf_token();

// Buat nonce untuk Content Security Policy dan simpan di session
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

// Terapkan Content Security Policy (sesuaikan sumber jika diperlukan)
header("Content-Security-Policy: default-src 'self'; 
    script-src 'self' https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.datatables.net https://cdn.jsdelivr.net 'nonce-$nonce'; 
    style-src 'self' https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com https://cdn.datatables.net 'nonce-$nonce'; 
    img-src 'self'; 
    font-src 'self' https://cdnjs.cloudflare.com; 
    connect-src 'self'");

// Pengecekan role: hanya user dengan peran 'keuangan', 'superadmin', atau 'sdm' yang boleh mengakses
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['keuangan', 'superadmin', 'sdm'])) {
    header("Location: /payroll_absensi_v2/login.php");
    exit();
}

// Inisialisasi token CSRF jika belum ada
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ambil parameter 'jenjang' dari URL dan validasi
$jenjang = isset($_GET['jenjang']) ? bersihkan_input($_GET['jenjang']) : '';
if (empty($jenjang)) {
    echo "Jenjang tidak valid.";
    exit();
}

// Tambahkan Audit Log saat pengguna mengakses halaman ini
add_audit_log(
    $conn,
    $_SESSION['user_id'],
    'AccessPage',
    "Pengguna dengan ID {$_SESSION['user_id']} dan peran '{$_SESSION['role']}' mengakses halaman Rekap Payroll Details untuk jenjang '{$jenjang}'."
);

// =========================
// 2. Fungsi Pendukung
// =========================
if (!function_exists('bulanIntToName')) {
    function bulanIntToName($bulan) {
        $namaBulan = [
            '1'  => 'Januari',
            '2'  => 'Februari',
            '3'  => 'Maret',
            '4'  => 'April',
            '5'  => 'Mei',
            '6'  => 'Juni',
            '7'  => 'Juli',
            '8'  => 'Agustus',
            '9'  => 'September',
            '10' => 'Oktober',
            '11' => 'November',
            '12' => 'Desember'
        ];
        return isset($namaBulan[$bulan]) ? $namaBulan[$bulan] : 'Invalid';
    }
}

// =========================
// 3. Handle Permintaan AJAX
// =========================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verifikasi CSRF token
        $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        verify_csrf_token($csrf_token);

        $case = isset($_POST['case']) ? bersihkan_input($_POST['case']) : '';
        switch ($case) {
            case 'LoadingRekapPayrollDetails':
                // Catat audit log untuk aksi loading detail
                add_audit_log(
                    $conn,
                    $_SESSION['user_id'],
                    'LoadingRekapPayrollDetails',
                    "Pengguna dengan ID {$_SESSION['user_id']} memuat detail rekap payroll untuk jenjang '{$jenjang}'."
                );
                LoadingRekapPayrollDetails($conn, $jenjang);
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

// =========================
// 4. Fungsi AJAX (Server-Side DataTables)
// =========================

function LoadingRekapPayrollDetails($conn, $jenjang) {
    // Parameter DataTables
    $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = isset($_POST['search']['value']) ? bersihkan_input($_POST['search']['value']) : '';

    // Filter tambahan: Bulan dan Tahun (jika ada)
    $bulan = isset($_POST['bulan']) ? intval($_POST['bulan']) : 0;
    $tahun = isset($_POST['tahun']) ? intval($_POST['tahun']) : 0;

    // Query dasar: join payroll dan anggota_sekolah untuk mendapatkan nama karyawan
    $sqlBase = "
        FROM payroll p
        JOIN anggota_sekolah a ON p.id_anggota = a.id
        WHERE a.jenjang = ?
    ";
    $params = [$jenjang];
    $types  = "s";

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
        // Misalnya, filter berdasarkan nama karyawan atau ID payroll
        $sqlBase .= " AND (a.nama LIKE ? OR p.id LIKE ?)";
        $searchParam = "%" . $search . "%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types  .= "ss";
    }

    // Hitung recordsFiltered
    $sqlCountFiltered = "SELECT COUNT(*) AS total " . $sqlBase;
    $stmtFiltered = $conn->prepare($sqlCountFiltered);
    if ($stmtFiltered === false) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmtFiltered->bind_param($types, ...$params);
    $stmtFiltered->execute();
    $resFiltered = $stmtFiltered->get_result();
    $rowFiltered = $resFiltered->fetch_assoc();
    $totalFiltered = isset($rowFiltered['total']) ? $rowFiltered['total'] : 0;
    $stmtFiltered->close();

    // Hitung recordsTotal (tanpa filter pencarian)
    $sqlCountTotal = "SELECT COUNT(*) AS total FROM payroll p JOIN anggota_sekolah a ON p.id_anggota = a.id WHERE a.jenjang = ?";
    $stmtTotal = $conn->prepare($sqlCountTotal);
    if ($stmtTotal === false) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmtTotal->bind_param("s", $jenjang);
    $stmtTotal->execute();
    $resTotal = $stmtTotal->get_result();
    $rowTotal = $resTotal->fetch_assoc();
    $recordsTotal = isset($rowTotal['total']) ? $rowTotal['total'] : 0;
    $stmtTotal->close();

    // Query data: ambil kolom-kolom yang diperlukan
    $sqlData = "
        SELECT p.id AS id_payroll,
               a.nama AS nama_karyawan,
               p.bulan,
               p.tahun,
               p.gaji_pokok,
               p.total_pendapatan,
               p.total_potongan,
               p.gaji_bersih
        " . $sqlBase . "
    ";

    // ORDER BY: default p.id DESC
    $orderBy = " ORDER BY p.id DESC";
    if (isset($_POST['order'][0]['column']) && isset($_POST['columns'])) {
        $colIndex = intval($_POST['order'][0]['column']);
        $allowedCols = ['id_payroll', 'nama_karyawan', 'bulan', 'tahun', 'gaji_pokok', 'total_pendapatan', 'total_potongan', 'gaji_bersih'];
        if (isset($_POST['columns'][$colIndex]['data']) && in_array($_POST['columns'][$colIndex]['data'], $allowedCols)) {
            $colName = $_POST['columns'][$colIndex]['data'];
            $colSortOrder = ($_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
            $mapCol = [
                'id_payroll'       => 'p.id',
                'nama_karyawan'    => 'a.nama',
                'bulan'            => 'p.bulan',
                'tahun'            => 'p.tahun',
                'gaji_pokok'       => 'p.gaji_pokok',
                'total_pendapatan' => 'p.total_pendapatan',
                'total_potongan'   => 'p.total_potongan',
                'gaji_bersih'      => 'p.gaji_bersih'
            ];
            if (isset($mapCol[$colName])) {
                $orderBy = " ORDER BY " . $mapCol[$colName] . " " . $colSortOrder;
            }
        }
    }

    // LIMIT untuk paging
    $limit = " LIMIT ?, ?";
    $paramsData = $params;
    $typesData  = $types;
    $paramsData[] = $start;
    $paramsData[] = $length;
    $typesData .= "ii";

    $sqlData .= $orderBy . $limit;

    $stmtData = $conn->prepare($sqlData);
    if ($stmtData === false) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmtData->bind_param($typesData, ...$paramsData);
    $stmtData->execute();
    $resData = $stmtData->get_result();

    $data = [];
    while ($row = $resData->fetch_assoc()) {
        $data[] = [
            "id_payroll" => $row['id_payroll'],
            "nama_karyawan" => htmlspecialchars($row['nama_karyawan']),
            "bulan" => bulanIntToName($row['bulan']),
            "tahun" => $row['tahun'],
            "gaji_pokok" => 'Rp ' . number_format($row['gaji_pokok'], 2, ',', '.'),
            "total_pendapatan" => 'Rp ' . number_format($row['total_pendapatan'], 2, ',', '.'),
            "total_potongan" => 'Rp ' . number_format($row['total_potongan'], 2, ',', '.'),
            "gaji_bersih" => 'Rp ' . number_format($row['gaji_bersih'], 2, ',', '.'),
            "aksi" => '
                <a href="payroll-details.php?id_payroll=' . urlencode($row['id_payroll']) . '" class="btn btn-info btn-sm" data-toggle="tooltip" title="Lihat Detail">
                    <i class="fas fa-eye"></i>
                </a>
            '
        ];
    }
    $stmtData->close();

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $recordsTotal,
        "recordsFiltered" => $totalFiltered,
        "data" => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Rekap Payroll - <?php echo htmlspecialchars($jenjang); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 4 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/css/sb-admin-2.min.css">
    <!-- DataTables CSS (Bootstrap 4) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <style>
        /* Header tabel dengan font kecil (sesuai payheads.php) */
        thead th {
            background-color: #343a40;
            color: white;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
            font-size: 13px;
        }
        tbody td {
            font-size: 13px;
            vertical-align: middle;
            white-space: nowrap;
        }
        tbody tr:nth-of-type(odd) {
            background-color: #f9f9f9;
        }
        tbody tr:nth-of-type(even) {
            background-color: #ffffff;
        }
        tbody tr:hover {
            background-color: #e2e6ea;
        }
        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
        }
        /* Ukuran tabel lebih kecil seperti di payheads.php */
        #rekapPayrollDetailsTable.table-sm th,
        #rekapPayrollDetailsTable.table-sm td {
            font-size: 13px;
            padding: 5px 10px;
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
        /* Styling card header agar sama dengan payheads.php */
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
            padding: 0.75rem 1.25rem;
            border-bottom: none;
        }
        .card-body {
            padding: 1rem 1.25rem;
        }
    </style>
</head>
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include(__DIR__ . '/../../sidebar.php'); ?>
        <!-- End Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
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
                <!-- End Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <!-- Page Heading -->
                    <h1 class="h3 mb-4 text-gray-800">
                        <i class="fas fa-chart-bar"></i> Detail Rekap Payroll - <?php echo htmlspecialchars($jenjang); ?>
                    </h1>

                    <!-- Filter Section -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <strong>Filter Detail Rekap Payroll</strong>
                        </div>
                        <div class="card-body">
                            <form id="filterForm" class="form-inline">
                                <!-- Sertakan CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <!-- Filter Bulan -->
                                <div class="form-group mb-2 mr-3">
                                    <label for="filterBulan" class="mr-2">Bulan:</label>
                                    <select class="form-control" id="filterBulan" name="bulan" style="width:120px">
                                        <option value="">Semua Bulan</option>
                                        <?php
                                            for ($m = 1; $m <= 12; $m++) {
                                                echo "<option value=\"$m\">" . bulanIntToName($m) . "</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                                <!-- Filter Tahun -->
                                <div class="form-group mb-2 mr-3">
                                    <label for="filterTahun" class="mr-2">Tahun:</label>
                                    <select class="form-control" id="filterTahun" name="tahun" style="width:120px">
                                        <option value="">Semua Tahun</option>
                                        <?php
                                            // Ambil daftar tahun dari tabel payroll berdasarkan jenjang
                                            $stmtTahun = $conn->prepare("SELECT DISTINCT tahun FROM payroll WHERE id_anggota IN (SELECT id FROM anggota_sekolah WHERE jenjang = ?) ORDER BY tahun DESC");
                                            if ($stmtTahun) {
                                                $stmtTahun->bind_param("s", $jenjang);
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
                                <button type="button" class="btn btn-primary mb-2 mr-2" id="btnApplyFilter">
                                    <i class="fas fa-filter"></i> Terapkan Filter
                                </button>
                                <button type="button" class="btn btn-secondary mb-2" id="btnResetFilter">
                                    <i class="fas fa-undo"></i> Reset Filter
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Tabel Detail Rekap Payroll -->
                    <div class="card shadow mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title m-0">Daftar Detail Payroll - <?php echo htmlspecialchars($jenjang); ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="rekapPayrollDetailsTable" class="table table-sm table-bordered table-striped display nowrap" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>ID Payroll</th>
                                            <th>Nama Karyawan</th>
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
                <!-- /.container-fluid -->
            </div>
            <!-- /.content -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?php echo date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div>
        <!-- /.content-wrapper -->
    </div>
    <!-- ./wrapper -->

    <!-- Loading Spinner -->
    <div id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/js/sb-admin-2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/js/all.min.js"></script>
    <script src="/payroll_absensi_v2/plugins/bootstrap-notify/bootstrap-notify.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    $(document).ready(function(){
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

        // Inisialisasi DataTables untuk Detail Rekap Payroll
        var rekapPayrollDetailsTable = $('#rekapPayrollDetailsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: 'rekap_payroll_details.php?ajax=1&jenjang=<?php echo urlencode($jenjang); ?>',
                type: 'POST',
                data: function(d){
                    d.case = 'LoadingRekapPayrollDetails';
                    d.csrf_token = csrfToken;
                    d.bulan = $('#filterBulan').val();
                    d.tahun = $('#filterTahun').val();
                },
                beforeSend: function(){
                    $('#loadingSpinner').show();
                },
                complete: function(){
                    $('#loadingSpinner').hide();
                },
                error: function(xhr, error, thrown){
                    showToast('Terjadi kesalahan load data detail rekap payroll.', 'error');
                }
            },
            columns: [
                { data:'id_payroll', name:'id_payroll' },
                { data:'nama_karyawan', name:'nama_karyawan' },
                { data:'bulan', name:'bulan' },
                { data:'tahun', name:'tahun' },
                { data:'gaji_pokok', name:'gaji_pokok' },
                { data:'total_pendapatan', name:'total_pendapatan' },
                { data:'total_potongan', name:'total_potongan' },
                { data:'gaji_bersih', name:'gaji_bersih' },
                { data:'aksi', orderable:false, searchable:false }
            ],
            order: [[0,'desc']],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
            },
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Export Excel',
                    className: 'btn btn-success btn-sm',
                    exportOptions: { columns: [0,1,2,3,4,5,6,7] }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> Export PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: { columns: [0,1,2,3,4,5,6,7] },
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
                    exportOptions: { columns: [0,1,2,3,4,5,6,7] }
                }
            ],
            responsive: true,
            autoWidth: false,
            columnDefs: [
                {
                    targets: 8,
                    orderable: false,
                    responsivePriority: 1
                }
            ]
        });

        // EVENT: Apply Filter
        $('#btnApplyFilter').on('click', function(){
            // Jika diperlukan, catat audit log via AJAX
            $.ajax({
                url: 'rekap_payroll_details.php?ajax=1&jenjang=<?php echo urlencode($jenjang); ?>',
                type: 'POST',
                data: {
                    case: 'AddAuditLog',
                    csrf_token: csrfToken,
                    action: 'ApplyFilter',
                    details: `Pengguna menerapkan filter Bulan: ${$('#filterBulan').val() || 'Semua'}, Tahun: ${$('#filterTahun').val() || 'Semua'}.`
                },
                success: function(response){
                    // Jika audit log berhasil, tidak perlu ditampilkan
                },
                error: function(){
                    // Jika gagal mencatat audit log, tetap lanjutkan
                }
            });
            rekapPayrollDetailsTable.ajax.reload();
        });

        // EVENT: Reset Filter
        $('#btnResetFilter').on('click', function(){
            $.ajax({
                url: 'rekap_payroll_details.php?ajax=1&jenjang=<?php echo urlencode($jenjang); ?>',
                type: 'POST',
                data: {
                    case: 'ResetFilter',
                    csrf_token: csrfToken
                },
                success: function(response){
                    // Jika perlu, catat reset filter
                },
                error: function(){
                    // Gagal mencatat audit log, tetapi lanjutkan
                }
            });
            $('#filterForm')[0].reset();
            rekapPayrollDetailsTable.ajax.reload();
        });

        // Optional: Trigger filter pada enter key
        $('#filterForm').on('keypress', function(e){
            if(e.which === 13){
                $('#btnApplyFilter').click();
            }
        });
    });
    </script>
</body>
</html>
