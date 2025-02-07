<?php
// File: /payroll_absensi_v2/payroll/keuangan/rekap_payroll.php

session_start();

// Inklusi koneksi dan helpers
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../helpers.php'; // Pastikan path ini benar

// Periksa apakah pengguna sudah login dan memiliki peran yang sesuai
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['keuangan', 'superadmin', 'sdm'])) {
    header("Location: /payroll_absensi_v2/login.php");
    exit();
}

// Tambahkan Audit Log saat pengguna mengakses halaman ini
add_audit_log(
    $conn,
    $_SESSION['user_id'],
    'AccessPage',
    "Pengguna dengan ID {$_SESSION['user_id']} dan peran '{$_SESSION['role']}' mengakses halaman Rekap Payroll."
);

/**
 * Fungsi untuk mengonversi angka bulan ke nama bulan dalam Bahasa Indonesia
 */
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

// Nonaktifkan output buffering agar JSON tidak terganggu
if (ob_get_length()) ob_end_clean();

// Nonaktifkan display_errors & aktifkan logging error
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
error_reporting(E_ALL);

// Inisialisasi Token CSRF jika belum ada (untuk proteksi)
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- HANDLE AJAX REQUEST ---
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    // Pastikan method POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Verifikasi Token CSRF
        $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
            send_response(403, 'Token CSRF tidak valid.');
        }

        $case = isset($_POST['case']) ? bersihkan_input($_POST['case']) : '';

        switch ($case) {
            case 'LoadingRekapPayroll':
                // Audit log: LoadingRekapPayroll
                add_audit_log(
                    $conn,
                    $_SESSION['user_id'],
                    'LoadingRekapPayroll',
                    "Pengguna dengan ID {$_SESSION['user_id']} dan peran '{$_SESSION['role']}' memuat data rekap payroll."
                );
                LoadingRekapPayroll($conn);
                break;

            case 'AddAuditLog':
                // Catat aksi audit log
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

            case 'ViewRekapPayrollDetail':
                ViewRekapPayrollDetail($conn);
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
 * Fungsi memuat data rekap payroll per jenjang (server-side DataTables)
 */
function LoadingRekapPayroll($conn) {
    // Parameter DataTables
    $draw    = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start   = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length  = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search  = isset($_POST['search']['value']) ? bersihkan_input($_POST['search']['value']) : '';

    // Filter tambahan
    $jenjang = isset($_POST['jenjang']) ? bersihkan_input($_POST['jenjang']) : '';
    $bulan   = isset($_POST['bulan']) ? intval($_POST['bulan']) : 0;
    $tahun   = isset($_POST['tahun']) ? intval($_POST['tahun']) : 0;

    // Query dasar
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
        $sqlBase .= " AND (a.jenjang LIKE ?)";
        $searchParam = "%" . $search . "%";
        $params[] = $searchParam;
        $types  .= "s";
    }

    // Total records terfilter
    $sqlFilteredCount = "SELECT COUNT(DISTINCT a.jenjang) AS total " . $sqlBase;
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
    $totalFiltered = isset($rowFiltered['total']) ? $rowFiltered['total'] : 0;
    $stmtFiltered->close();

    // Total records (tanpa filter)
    $sqlTotal = "
        SELECT COUNT(DISTINCT a.jenjang) AS total
        FROM payroll p
        JOIN anggota_sekolah a ON p.id_anggota = a.id
    ";
    $resTotal = $conn->query($sqlTotal);
    if (!$resTotal) {
        send_response(1, 'Query gagal: ' . $conn->error);
    }
    $rowTotal = $resTotal->fetch_assoc();
    $recordsTotal = isset($rowTotal['total']) ? $rowTotal['total'] : 0;

    // Query data dengan grouping per jenjang
    $sqlData = "
        SELECT a.jenjang,
               SUM(p.gaji_pokok) AS total_gaji_pokok,
               SUM(p.total_pendapatan) AS total_pendapatan,
               SUM(p.total_potongan) AS total_potongan,
               SUM(p.gaji_bersih) AS total_gaji_bersih
        " . $sqlBase . "
        GROUP BY a.jenjang
    ";

    // Pengurutan
    $orderBy = " ORDER BY a.jenjang ASC";
    if (isset($_POST['order']) && isset($_POST['columns'])) {
        $columnIndex = intval($_POST['order'][0]['column']);
        $allowedColumns = [
            'jenjang',
            'total_gaji_pokok',
            'total_pendapatan',
            'total_potongan',
            'total_gaji_bersih'
        ];
        if (isset($_POST['columns'][$columnIndex]['data']) && in_array($_POST['columns'][$columnIndex]['data'], $allowedColumns)) {
            $colName = $_POST['columns'][$columnIndex]['data'];
            $colSortOrder = ($_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
            $mapCol = [
                'jenjang'            => 'a.jenjang',
                'total_gaji_pokok'   => 'total_gaji_pokok',
                'total_pendapatan'   => 'total_pendapatan',
                'total_potongan'     => 'total_potongan',
                'total_gaji_bersih'  => 'total_gaji_bersih'
            ];
            if (isset($mapCol[$colName])) {
                $orderBy = " ORDER BY " . $mapCol[$colName] . " " . $colSortOrder;
            }
        }
    }

    // Limit untuk paging
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
        $data[] = [
            "jenjang"           => htmlspecialchars($row['jenjang']),
            "total_gaji_pokok"  => 'Rp ' . number_format($row['total_gaji_pokok'], 2, ',', '.'),
            "total_pendapatan"  => 'Rp ' . number_format($row['total_pendapatan'], 2, ',', '.'),
            "total_potongan"    => 'Rp ' . number_format($row['total_potongan'], 2, ',', '.'),
            "total_gaji_bersih" => 'Rp ' . number_format($row['total_gaji_bersih'], 2, ',', '.'),
            "aksi" => '
                <a href="rekap_payroll_details.php?jenjang=' . urlencode($row['jenjang']) . '" class="btn btn-info btn-sm me-1" data-bs-toggle="tooltip" title="Lihat Payroll Detail">
                    <i class="fas fa-file-invoice"></i>
                </a>
                <button class="btn btn-secondary btn-sm btn-view-rekap-detail" data-jenjang="' . htmlspecialchars($row['jenjang']) . '" data-bs-toggle="tooltip" title="View Detail">
                    <i class="fas fa-eye"></i>
                </button>
            '
        ];
    }
    $stmtData->close();

    // Output JSON untuk DataTables
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
 * Fungsi untuk menampilkan detail rekap payroll beserta rincian komponen
 * pengeluaran (earnings & deductions) dengan tampilan berwarna.
 */
/**
 * Fungsi untuk menampilkan detail rekap payroll beserta rincian komponen
 * pengeluaran (earnings & deductions) secara kompak dengan grid dua kolom.
 */
function ViewRekapPayrollDetail($conn) {
    $jenjang = isset($_POST['jenjang']) ? bersihkan_input($_POST['jenjang']) : '';
    if (empty($jenjang)) {
        send_response(1, 'Jenjang tidak valid.');
    }

    // Ambil data rincian payhead secara agregat untuk jenjang tersebut
    $stmt = $conn->prepare("
        SELECT ph.nama_payhead, ph.jenis, SUM(pd.amount) as total_amount
        FROM payroll_detail pd
        JOIN payheads ph ON pd.id_payhead = ph.id
        JOIN payroll p ON pd.id_payroll = p.id
        JOIN anggota_sekolah a ON p.id_anggota = a.id
        WHERE a.jenjang = ?
        GROUP BY ph.id, ph.nama_payhead, ph.jenis
    ");
    if ($stmt === false) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("s", $jenjang);
    if (!$stmt->execute()) {
        send_response(1, 'Execute failed: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    $details = [];
    while ($row = $result->fetch_assoc()) {
        $details[] = $row;
    }
    $stmt->close();

    // Ambil data summary payroll untuk jenjang tersebut
    $stmt2 = $conn->prepare("
        SELECT SUM(p.gaji_pokok) as total_gaji_pokok,
               SUM(p.total_pendapatan) as total_pendapatan,
               SUM(p.total_potongan) as total_potongan,
               SUM(p.gaji_bersih) as total_gaji_bersih
        FROM payroll p
        JOIN anggota_sekolah a ON p.id_anggota = a.id
        WHERE a.jenjang = ?
    ");
    if ($stmt2 === false) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmt2->bind_param("s", $jenjang);
    if (!$stmt2->execute()) {
        send_response(1, 'Execute failed: ' . $stmt2->error);
    }
    $result2 = $stmt2->get_result();
    $summary = $result2->fetch_assoc();
    if ($summary) {
        $summary['total_gaji_pokok'] = 'Rp ' . number_format($summary['total_gaji_pokok'], 2, ',', '.');
        $summary['total_pendapatan'] = 'Rp ' . number_format($summary['total_pendapatan'], 2, ',', '.');
        $summary['total_potongan']   = 'Rp ' . number_format($summary['total_potongan'], 2, ',', '.');
        $summary['total_gaji_bersih'] = 'Rp ' . number_format($summary['total_gaji_bersih'], 2, ',', '.');
    } else {
        $summary = [];
    }
    $stmt2->close();

    // Pisahkan data berdasarkan jenis: earnings dan deductions
    $earnings = [];
    $deductions = [];
    foreach ($details as $d) {
        if ($d['jenis'] === 'earnings') {
            $earnings[] = $d;
        } elseif ($d['jenis'] === 'deductions') {
            $deductions[] = $d;
        }
    }

    // Buat HTML summary (dengan font kecil agar kompak)
    $html  = '<h5 class="mb-3" style="font-size:14px;">Rekap Detail untuk Jenjang: ' . htmlspecialchars($jenjang) . '</h5>';
    $html .= '<table class="table table-sm table-bordered table-striped mb-3" style="font-size:12px;">';
    $html .= '<thead class="bg-primary text-white"><tr>';
    $html .= '<th colspan="2" class="text-center">Summary Payroll</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';
    $html .= '<tr><th style="width:50%;">Total Gaji Pokok</th><td>' . $summary['total_gaji_pokok'] . '</td></tr>';
    $html .= '<tr><th>Total Pendapatan</th><td>' . $summary['total_pendapatan'] . '</td></tr>';
    $html .= '<tr><th>Total Potongan</th><td>' . $summary['total_potongan'] . '</td></tr>';
    $html .= '<tr><th>Total Gaji Bersih</th><td>' . $summary['total_gaji_bersih'] . '</td></tr>';
    $html .= '</tbody></table>';

    // Buat tampilan grid dua kolom untuk rincian pendapatan dan potongan
    $html .= '<div class="row" style="font-size:12px;">';

    // Kolom kiri: Rincian Pendapatan (Earnings)
    $html .= '<div class="col-md-6 mb-2">';
    $html .= '<div class="card">';
    $html .= '<div class="card-header bg-success text-white p-1">Pendapatan</div>';
    $html .= '<div class="card-body p-1">';
    $html .= '<table class="table table-sm table-bordered table-striped mb-0">';
    $html .= '<thead class="bg-light"><tr>';
    $html .= '<th style="width:5%;">#</th><th>Nama Payhead</th><th style="width:35%;">Jumlah</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';
    if (!empty($earnings)) {
        $no = 1;
        foreach ($earnings as $e) {
            $nominal = 'Rp ' . number_format($e['total_amount'], 2, ',', '.');
            $html .= '<tr>';
            $html .= '<td>' . $no++ . '</td>';
            $html .= '<td>' . htmlspecialchars($e['nama_payhead']) . '</td>';
            $html .= '<td class="text-success">' . $nominal . '</td>';
            $html .= '</tr>';
        }
    } else {
        $html .= '<tr><td colspan="3" class="text-center">Tidak ada data</td></tr>';
    }
    $html .= '</tbody></table>';
    $html .= '</div></div>';
    $html .= '</div>'; // End kolom kiri

    // Kolom kanan: Rincian Potongan (Deductions)
    $html .= '<div class="col-md-6 mb-2">';
    $html .= '<div class="card">';
    $html .= '<div class="card-header bg-danger text-white p-1">Potongan</div>';
    $html .= '<div class="card-body p-1">';
    $html .= '<table class="table table-sm table-bordered table-striped mb-0">';
    $html .= '<thead class="bg-light"><tr>';
    $html .= '<th style="width:5%;">#</th><th>Nama Payhead</th><th style="width:35%;">Jumlah</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';
    if (!empty($deductions)) {
        $no = 1;
        foreach ($deductions as $d) {
            $nominal = 'Rp ' . number_format($d['total_amount'], 2, ',', '.');
            $html .= '<tr>';
            $html .= '<td>' . $no++ . '</td>';
            $html .= '<td>' . htmlspecialchars($d['nama_payhead']) . '</td>';
            $html .= '<td class="text-danger">' . $nominal . '</td>';
            $html .= '</tr>';
        }
    } else {
        $html .= '<tr><td colspan="3" class="text-center">Tidak ada data</td></tr>';
    }
    $html .= '</tbody></table>';
    $html .= '</div></div>';
    $html .= '</div>'; // End kolom kanan

    $html .= '</div>'; // End row

    send_response(0, $html);
}


?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/css/sb-admin-2.min.css">
    <!-- DataTables CSS (Bootstrap 4) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.6.2/css/buttons.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <!-- (Opsional) Select2 & SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <style>
        /* Custom Styles untuk Kartu */
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }
        .table-hover tbody tr:hover {
            background-color: #e2e6ea;
        }
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
        .aksi-column .btn:last-child {
            margin-right: 0;
        }
        .select2-container--bootstrap4 .select2-selection--single {
            background-color: transparent !important;
            border: 1px solid #ccc !important;
            color: #000;
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
        tbody tr:hover {
            background-color: #e2e6ea;
        }
        #rekapPayrollTable th, #rekapPayrollTable td {
            font-size: 14px;
            text-align: left;
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
            overflow: visible;
            margin: auto;
            top: 0; left: 0; bottom: 0; right: 0;
        }
        @media (max-width: 768px) {
            .form-inline .form-group {
                width: 100%;
                margin-right: 0 !important;
                margin-bottom: 10px;
            }
            #btnApplyFilter, #btnResetFilter, #btnExportData {
                margin-top: 5px;
            }
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
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">
                        <i class="fas fa-chart-bar"></i> Rekap Payroll
                    </h1>
                    <!-- Modified Filter Section -->
                    <div class="card mb-4" style="background-color: #f8f9fa; border-radius: 0.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <div class="card-header" style="background-color: #ffffff; border-top-left-radius: 0.5rem; border-top-right-radius: 0.5rem;">
                            <strong>Filter Rekap Payroll</strong>
                        </div>
                        <div class="card-body">
                            <form id="filterForm" class="form-inline">
                                <!-- Sertakan CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <!-- Jenjang -->
                                <div class="form-group mb-2 mr-3">
                                    <label for="filterJenjang" class="mr-2"><strong>Jenjang:</strong></label>
                                    <select class="form-control" id="filterJenjang" name="jenjang" style="width:150px">
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
                                <!-- Bulan -->
                                <div class="form-group mb-2 mr-3">
                                    <label for="filterBulan" class="mr-2"><strong>Bulan:</strong></label>
                                    <select class="form-control" id="filterBulan" name="bulan" style="width:120px">
                                        <option value="">Semua Bulan</option>
                                        <?php
                                            for ($m=1; $m<=12; $m++) {
                                                echo "<option value=\"$m\">" . bulanIntToName($m) . "</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                                <!-- Tahun -->
                                <div class="form-group mb-2 mr-3">
                                    <label for="filterTahun" class="mr-2"><strong>Tahun:</strong></label>
                                    <select class="form-control" id="filterTahun" name="tahun" style="width:120px">
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
                                <!-- Tombol Filter, Reset, & Ekspor -->
                                <div class="form-group mb-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-primary mr-2" id="btnApplyFilter">
                                        <i class="fas fa-filter"></i> Terapkan Filter
                                    </button>
                                    <button type="button" class="btn btn-secondary mr-2" id="btnResetFilter">
                                        <i class="fas fa-undo"></i> Reset Filter
                                    </button>
                                    <button type="button" class="btn btn-success" id="btnExportData">
                                        <i class="fas fa-file-export"></i> Export Data
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- End of Modified Filter Section -->
                    <!-- Tabel Rekap Payroll -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-clipboard-list"></i> Daftar Rekap Payroll</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="rekapPayrollTable" class="table table-sm table-bordered table-striped display nowrap" style="width:100%">
                                    <thead class="thead">
                                        <tr>
                                            <th>Jenjang</th>
                                            <th>Total Gaji Pokok</th>
                                            <th>Total Pendapatan</th>
                                            <th>Total Potongan</th>
                                            <th>Total Gaji Bersih</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
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
        </div>
    </div>

    <!-- Modal: Detail Rekap Payroll -->
    <div class="modal fade" id="detailRekapModal" tabindex="-1" aria-labelledby="detailRekapModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailRekapModalLabel">Detail Rekap Payroll</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body" id="detailRekapContent">
                    <p>Memuat detail rekap payroll...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times-circle"></i> Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/js/sb-admin-2.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.2/js/buttons.bootstrap4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    $(document).ready(function(){
        // Inisialisasi Tooltip
        $('[data-bs-toggle="tooltip"]').tooltip();

        // Inisialisasi SweetAlert2 Toast
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        // Fungsi notifikasi toast
        function showToast(message, icon = 'success') {
            Toast.fire({
                icon: icon,
                title: message
            });
        }

        // CSRF Token
        var csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

        // Inisialisasi DataTables (server-side)
        var rekapPayrollTable = $('#rekapPayrollTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: 'rekap_payroll.php?ajax=1',
                type: 'POST',
                data: function(d) {
                    d.case = 'LoadingRekapPayroll';
                    d.csrf_token = csrfToken;
                    d.jenjang = $('#filterJenjang').val();
                    d.bulan   = $('#filterBulan').val();
                    d.tahun   = $('#filterTahun').val();
                },
                beforeSend: function(){
                    $('#loadingSpinner').show();
                },
                complete: function(){
                    $('#loadingSpinner').hide();
                },
                error: function(xhr, error, thrown){
                    showToast('Terjadi kesalahan load data rekap payroll.', 'error');
                }
            },
            columns: [
                { data: 'jenjang', name: 'jenjang' },
                { data: 'total_gaji_pokok', name: 'total_gaji_pokok' },
                { data: 'total_pendapatan', name: 'total_pendapatan' },
                { data: 'total_potongan', name: 'total_potongan' },
                { data: 'total_gaji_bersih', name: 'total_gaji_bersih' },
                { data: 'aksi', orderable: false, searchable: false }
            ],
            order: [[0, 'asc']],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
            },
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Export Excel',
                    className: 'btn btn-success btn-sm',
                    exportOptions: {
                        columns: [0,1,2,3,4]
                    }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> Export PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: {
                        columns: [0,1,2,3,4]
                    },
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
                    exportOptions: {
                        columns: [0,1,2,3,4]
                    }
                }
            ],
            responsive: true,
            autoWidth: false,
            columnDefs: [
                {
                    targets: 5,
                    orderable: false,
                    responsivePriority: 1
                }
            ]
        });

        // EVENT: Apply Filter
        $('#btnApplyFilter').on('click', function(){
            $.ajax({
                url: 'rekap_payroll.php?ajax=1',
                type: 'POST',
                data: {
                    case: 'AddAuditLog',
                    csrf_token: csrfToken,
                    action: 'ApplyFilter',
                    details: `Pengguna menerapkan filter Jenjang: ${$('#filterJenjang').val() || 'Semua'}, Bulan: ${$('#filterBulan').val() || 'Semua'}, Tahun: ${$('#filterTahun').val() || 'Semua'}.`
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
            rekapPayrollTable.ajax.reload();
        });

        // EVENT: Reset Filter
        $('#btnResetFilter').on('click', function(){
            $.ajax({
                url: 'rekap_payroll.php?ajax=1',
                type: 'POST',
                data: {
                    case: 'AddAuditLog',
                    csrf_token: csrfToken,
                    action: 'ResetFilter',
                    details: 'Pengguna mereset semua filter.'
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
            rekapPayrollTable.ajax.reload();
        });

        // EVENT: Export Data ke Excel
        $('#btnExportData').on('click', function(){
            rekapPayrollTable.button('.buttons-excel').trigger();
            $.ajax({
                url: 'rekap_payroll.php?ajax=1',
                type: 'POST',
                data: {
                    case: 'AddAuditLog',
                    csrf_token: csrfToken,
                    action: 'ExportData',
                    details: 'Pengguna mengekspor data rekap payroll ke Excel.'
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

        // EVENT: Tampilkan Detail Rekap Payroll (modal)
        $(document).on('click', '.btn-view-rekap-detail', function(){
    var jenjang = $(this).data('jenjang');
    if (jenjang) {
        $.ajax({
            url: 'rekap_payroll.php?ajax=1',
            type: 'POST',
            data: {
                case: 'ViewRekapPayrollDetail',
                jenjang: jenjang,
                csrf_token: csrfToken
            },
            beforeSend: function(){
                $('#detailRekapContent').html('<p>Memuat detail rekap payroll...</p>');
                var detailModal = new bootstrap.Modal(document.getElementById('detailRekapModal'));
                detailModal.show();
            },
            success: function(response){
                if (response.code === 0) {
                    $('#detailRekapContent').html(response.result);
                } else {
                    $('#detailRekapContent').html('<p>' + response.result + '</p>');
                }
            },
            error: function(){
                $('#detailRekapContent').html('<p>Terjadi kesalahan saat memuat detail rekap payroll.</p>');
            }
        });
    }
});

        // Optional: Jalankan filter saat tekan tombol Enter
        $('#filterForm').on('keypress', function(e){
            if(e.which === 13) {
                $('#btnApplyFilter').click();
            }
        });
    });
    </script>
</body>
</html>
