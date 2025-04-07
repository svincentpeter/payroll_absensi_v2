<?php
// File: /payroll_absensi_v2/keuangan/rekap_payroll_details.php

// 1. Inisialisasi Session & Pengaturan Awal
require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:Keuangan', 'M:Superadmin', 'sdm']);
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];
require_once __DIR__ . '/../koneksi.php';
if (ob_get_length()) {
    ob_end_clean();
}

// 2. Ambil Parameter & Catat Audit Log
$jenjang = isset($_GET['jenjang']) ? sanitize_input($_GET['jenjang']) : '';
if (empty($jenjang)) {
    echo "Jenjang tidak valid.";
    exit();
}

add_audit_log(
    $conn,
    $_SESSION['nip'],
    'AccessPage',
    "Pengguna dengan NIP {$_SESSION['nip']} dan peran '{$_SESSION['role']}' mengakses halaman Rekap Payroll Details untuk jenjang '{$jenjang}'."
);

// 3. Siapkan header kolom dinamis (untuk DataTables dan header HTML)
// Ambil daftar payhead earnings
$earningHeaderPayheads = [];
$resultEarnings = $conn->query("SELECT DISTINCT pdf.nama_payhead 
    FROM payroll_detail_final pdf 
    JOIN payroll_final p ON pdf.id_payroll_final = p.id 
    JOIN anggota_sekolah a ON p.id_anggota = a.id 
    WHERE a.jenjang = '$jenjang' AND pdf.jenis='earnings' 
    ORDER BY pdf.nama_payhead ASC");
if ($resultEarnings) {
    while ($row = $resultEarnings->fetch_assoc()) {
        $earningHeaderPayheads[] = $row['nama_payhead'];
    }
}
// Ambil daftar payhead deductions
$deductionHeaderPayheads = [];
$resultDeductions = $conn->query("SELECT DISTINCT pdf.nama_payhead 
    FROM payroll_detail_final pdf 
    JOIN payroll_final p ON pdf.id_payroll_final = p.id 
    JOIN anggota_sekolah a ON p.id_anggota = a.id 
    WHERE a.jenjang = '$jenjang' AND pdf.jenis='deductions' 
    ORDER BY pdf.nama_payhead ASC");
if ($resultDeductions) {
    while ($row = $resultDeductions->fetch_assoc()) {
        $deductionHeaderPayheads[] = $row['nama_payhead'];
    }
}
$headerPayheads = array_merge($earningHeaderPayheads, $deductionHeaderPayheads);

// 4. Handle Permintaan AJAX (Server-Side DataTables)
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $case = isset($_POST['case']) ? sanitize_input($_POST['case']) : '';
        switch ($case) {
            case 'LoadingRekapPayrollDetails':
                add_audit_log(
                    $conn,
                    $_SESSION['nip'],
                    'LoadingRekapPayrollDetails',
                    "Pengguna dengan NIP {$_SESSION['nip']} memuat detail rekap payroll untuk jenjang '{$jenjang}'."
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

function LoadingRekapPayrollDetails($conn, $jenjang)
{
    // Parameter DataTables
    $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = isset($_POST['search']['value']) ? sanitize_input($_POST['search']['value']) : '';

    // Filter tambahan: Bulan dan Tahun (jika di-set)
    $bulan = isset($_POST['bulan']) ? intval($_POST['bulan']) : 0;
    $tahun = isset($_POST['tahun']) ? intval($_POST['tahun']) : 0;

    // Kondisi WHERE dasar
    $sqlWhere = " WHERE a.jenjang = ? ";
$params = [$jenjang];
$types  = "s";
    if ($bulan > 0) {
        $sqlWhere .= " AND p.bulan = ? ";
        $params[] = $bulan;
        $types  .= "i";
    }
    if ($tahun > 0) {
        $sqlWhere .= " AND p.tahun = ? ";
        $params[] = $tahun;
        $types  .= "i";
    }
    if (!empty($search)) {
        $sqlWhere .= " AND (a.nama LIKE ? OR p.id LIKE ?) ";
        $searchParam = "%" . $search . "%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types  .= "ss";
    }

    // Hitung recordsFiltered
    $sqlCountFiltered = "SELECT COUNT(*) AS total 
        FROM payroll_final p 
        JOIN anggota_sekolah a ON p.id_anggota = a.id " . $sqlWhere;
    $stmtFiltered = $conn->prepare($sqlCountFiltered);
    if ($stmtFiltered === false) {
        send_response(1, 'Prepare failed (filtered): ' . $conn->error);
    }
    $stmtFiltered->bind_param($types, ...$params);
    $stmtFiltered->execute();
    $resFiltered = $stmtFiltered->get_result();
    $rowFiltered = $resFiltered->fetch_assoc();
    $totalFiltered = isset($rowFiltered['total']) ? $rowFiltered['total'] : 0;
    $stmtFiltered->close();

    // Hitung recordsTotal (tanpa filter pencarian)
    $sqlCountTotal = "SELECT COUNT(*) AS total 
        FROM payroll_final p 
        JOIN anggota_sekolah a ON p.id_anggota = a.id 
        WHERE a.jenjang = ?";
    $stmtTotal = $conn->prepare($sqlCountTotal);
    $stmtTotal->bind_param("s", $jenjang);
    $stmtTotal->execute();
    $resTotal = $stmtTotal->get_result();
    $rowTotal = $resTotal->fetch_assoc();
    $recordsTotal = isset($rowTotal['total']) ? $rowTotal['total'] : 0;
    $stmtTotal->close();

    // Siapkan subquery dinamis untuk aggregasi detail payroll berdasarkan header payheads
    $dynamicCases = [];
    foreach ($GLOBALS['headerPayheads'] as $ph) {
        $escaped = $conn->real_escape_string($ph);
        $alias = "payhead_" . md5($ph);
        $dynamicCases[] = "SUM(CASE WHEN pdf.nama_payhead = '$escaped' THEN pdf.amount ELSE 0 END) AS `$alias`";
    }
    $dynamicSelectSubquery = empty($dynamicCases) ? "0 AS dummy" : implode(", ", $dynamicCases);

    // Bangun outer dynamic select
    $outerDynamicSelect = "";
    foreach ($GLOBALS['headerPayheads'] as $ph) {
        $alias = "payhead_" . md5($ph);
        $outerDynamicSelect .= ", IFNULL(agg_detail.`$alias`, 0) AS `$alias` ";
    }

    // Query utama (termasuk LEFT JOIN ke kenaikan_gaji_tahunan)
    $sqlData = "
        SELECT
            p.id AS id_payroll,
            a.nama AS nama_karyawan,
            p.bulan,
            p.tahun,
            p.gaji_pokok AS total_gaji_pokok
            $outerDynamicSelect,
            IFNULL(kg.total_lain_lain, 0) AS total_lain_lain,
            p.gaji_bersih AS total_gaji_bersih
        FROM payroll_final p
        JOIN anggota_sekolah a ON p.id_anggota = a.id
        LEFT JOIN (
            SELECT id_payroll_final, $dynamicSelectSubquery
            FROM payroll_detail_final pdf
            GROUP BY id_payroll_final
        ) agg_detail ON p.id = agg_detail.id_payroll_final
        LEFT JOIN (
            SELECT id_anggota, SUM(jumlah) AS total_lain_lain
            FROM kenaikan_gaji_tahunan
            WHERE pindah_ke_lain_lain = 1
            GROUP BY id_anggota
        ) kg ON a.id = kg.id_anggota
        " . $sqlWhere . "
        GROUP BY p.id
    ";

    // Sorting default
    $orderBy = " ORDER BY p.id DESC";
    $allowedCols = ['id_payroll', 'nama_karyawan', 'bulan', 'tahun', 'total_gaji_pokok'];
    foreach ($GLOBALS['headerPayheads'] as $ph) {
        $alias = "payhead_" . md5($ph);
        $allowedCols[] = $alias;
    }
    $allowedCols[] = 'total_lain_lain';
    $allowedCols[] = 'total_gaji_bersih';
    if (isset($_POST['order'][0]['column']) && isset($_POST['columns'])) {
        $colIndex = intval($_POST['order'][0]['column']);
        if (isset($_POST['columns'][$colIndex]['data']) && in_array($_POST['columns'][$colIndex]['data'], $allowedCols)) {
            $colName = $_POST['columns'][$colIndex]['data'];
            $colSortOrder = ($_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
            $mapCol = [
                'id_payroll'       => 'p.id',
                'nama_karyawan'    => 'a.nama',
                'bulan'            => 'p.bulan',
                'tahun'            => 'p.tahun',
                'total_gaji_pokok' => 'p.gaji_pokok',
                'total_lain_lain'  => 'total_lain_lain',
                'total_gaji_bersih'=> 'p.gaji_bersih'
            ];
            if (isset($mapCol[$colName])) {
                $orderBy = " ORDER BY " . $mapCol[$colName] . " " . $colSortOrder;
            } else {
                $orderBy = " ORDER BY " . $colName . " " . $colSortOrder;
            }
        }
    }
    $limit = " LIMIT ?, ?";
    $paramsData = $params;
    $typesData  = $types . "ii";
    $paramsData[] = $start;
    $paramsData[] = $length;

    $sqlData .= $orderBy . $limit;
    $stmtData = $conn->prepare($sqlData);
    if ($stmtData === false) {
        send_response(1, 'Prepare failed (data): ' . $conn->error);
    }
    if (!empty($paramsData)) {
        $stmtData->bind_param($typesData, ...$paramsData);
    }
    $stmtData->execute();
    $resData = $stmtData->get_result();

    $data = [];
    while ($row = $resData->fetch_assoc()) {
        $rowData = [];
        $rowData["id_payroll"] = $row['id_payroll'];
        $rowData["nama_karyawan"] = htmlspecialchars($row['nama_karyawan']);
        $rowData["bulan"] = getIndonesianMonthName($row['bulan']);
        $rowData["tahun"] = $row['tahun'];
        $rowData["total_gaji_pokok"] = 'Rp ' . number_format($row['total_gaji_pokok'], 2, ',', '.');
        foreach ($GLOBALS['headerPayheads'] as $ph) {
            $alias = "payhead_" . md5($ph);
            $rowData[$alias] = 'Rp ' . number_format($row[$alias], 2, ',', '.');
        }
        $rowData["total_lain_lain"] = 'Rp ' . number_format($row['total_lain_lain'], 2, ',', '.');
        $rowData["total_gaji_bersih"] = 'Rp ' . number_format($row['total_gaji_bersih'], 2, ',', '.');
        $rowData["aksi"] = '
        <div class="dropdown">
          <button class="btn" type="button" id="dropdownMenuButton_' . htmlspecialchars($row['id_payroll']) . '" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-three-dots-vertical"></i>
          </button>
          <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton_' . htmlspecialchars($row['id_payroll']) . '">
            <li>
              <a class="dropdown-item" href="rekap_payroll_details_print.php?jenjang=' . urlencode($jenjang) . '&bulan=' . $row['bulan'] . '&tahun=' . $row['tahun'] . '">
                <i class="fas fa-print"></i> Print Detail
              </a>
            </li>
            <li>
              <a class="dropdown-item btn-view-full-detail" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id_payroll']) . '">
                <i class="fas fa-eye"></i> View Detail
              </a>
            </li>
          </ul>
        </div>';
        $data[] = $rowData;
    }
    $stmtData->close();

    echo json_encode([
        "draw"            => $draw,
        "recordsTotal"    => $recordsTotal,
        "recordsFiltered" => $totalFiltered,
        "data"            => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Fungsi ViewRekapPayrollDetail() tetap sama seperti sebelumnya
function ViewRekapPayrollDetail($conn)
{
    $jenjang = isset($_POST['jenjang']) ? sanitize_input($_POST['jenjang']) : '';
    if (empty($jenjang)) {
        send_response(1, 'Jenjang tidak valid.');
    }

    $stmt = $conn->prepare("
    SELECT pdf.nama_payhead, pdf.jenis, SUM(pdf.amount) AS total_amount
    FROM payroll_detail_final pdf
    JOIN payroll_final pf ON pdf.id_payroll_final = pf.id
    JOIN anggota_sekolah a ON pf.id_anggota = a.id
    WHERE a.jenjang = ?
    GROUP BY pdf.nama_payhead, pdf.jenis
");
    if ($stmt === false) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("s", $jenjang);
    $stmt->execute();
    $result = $stmt->get_result();
    $details = [];
    while ($row = $result->fetch_assoc()) {
        $details[] = $row;
    }
    $stmt->close();

    $earnings   = [];
    $deductions = [];
    foreach ($details as $d) {
        if (strtolower($d['jenis']) === 'earnings') {
            $earnings[] = $d;
        } elseif (strtolower($d['jenis']) === 'deductions') {
            $deductions[] = $d;
        }
    }

    $html  = '<h5 class="mb-3" style="font-size:14px;">Detail Rekap Payroll untuk Jenjang: ' . htmlspecialchars($jenjang) . '</h5>';
    $html .= '<div class="row" style="font-size:12px;">';

    // Earnings
    $html .= '<div class="col-md-6 mb-2">';
    $html .= '  <div class="card">';
    $html .= '    <div class="card-header bg-success text-white p-1">Pendapatan</div>';
    $html .= '    <div class="card-body p-1">';
    $html .= '      <table class="table table-sm table-bordered table-striped mb-0">';
    $html .= '        <thead class="bg-light"><tr><th style="width:5%;">#</th><th>Payhead</th><th style="width:35%;">Jumlah</th></tr></thead>';
    $html .= '        <tbody>';
    if (!empty($earnings)) {
        $no = 1;
        foreach ($earnings as $e) {
            $html .= '<tr>';
            $html .= '<td>' . $no++ . '</td>';
            $html .= '<td>' . htmlspecialchars($e['nama_payhead']) . '</td>';
            $html .= '<td class="text-success">' . formatNominal($e['total_amount']) . '</td>';
            $html .= '</tr>';
        }
    } else {
        $html .= '<tr><td colspan="3" class="text-center">Tidak ada data</td></tr>';
    }
    $html .= '        </tbody>';
    $html .= '      </table>';
    $html .= '    </div>';
    $html .= '  </div>';
    $html .= '</div>';

    // Deductions
    $html .= '<div class="col-md-6 mb-2">';
    $html .= '  <div class="card">';
    $html .= '    <div class="card-header bg-danger text-white p-1">Potongan</div>';
    $html .= '    <div class="card-body p-1">';
    $html .= '      <table class="table table-sm table-bordered table-striped mb-0">';
    $html .= '        <thead class="bg-light"><tr><th style="width:5%;">#</th><th>Payhead</th><th style="width:35%;">Jumlah</th></tr></thead>';
    $html .= '        <tbody>';
    if (!empty($deductions)) {
        $no = 1;
        foreach ($deductions as $d) {
            $html .= '<tr>';
            $html .= '<td>' . $no++ . '</td>';
            $html .= '<td>' . htmlspecialchars($d['nama_payhead']) . '</td>';
            $html .= '<td class="text-danger">' . formatNominal($d['total_amount']) . '</td>';
            $html .= '</tr>';
        }
    } else {
        $html .= '<tr><td colspan="3" class="text-center">Tidak ada data</td></tr>';
    }
    $html .= '        </tbody>';
    $html .= '      </table>';
    $html .= '    </div>';
    $html .= '  </div>';
    $html .= '</div>';

    $html .= '</div>'; // end row

    send_response(0, $html);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Rekap Payroll - <?php echo htmlspecialchars($jenjang); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <!-- DataTables CSS (Bootstrap 5) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.1.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }
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
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
        }
    </style>
</head>
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <?php include __DIR__ . '/../navbar.php'; ?>
                <!-- End of Topbar -->

                <!-- Breadcrumb -->
                <?php include __DIR__ . '/../breadcrumb.php'; ?>

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">
                        <i class="fas fa-chart-bar"></i> Detail Rekap Payroll - <?php echo htmlspecialchars($jenjang); ?>
                    </h1>

                    <!-- Filter Section -->
                    <div class="card mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-white">
                                <i class="fas fa-clipboard-list"></i> Filter Detail Rekap Payroll
                            </h6>
                        </div>
                        <div class="card-body">
                            <form id="filterForm" class="row gy-2 gx-3 align-items-center">
                                <div class="col-auto">
                                    <label for="filterBulan" class="form-label"><strong>Bulan:</strong></label>
                                    <select class="form-select" id="filterBulan" name="bulan" style="width:120px">
                                        <option value="">Semua Bulan</option>
                                        <?php
                                        for ($m = 1; $m <= 12; $m++) {
                                            echo "<option value=\"$m\">" . getIndonesianMonthName($m) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <label for="filterTahun" class="form-label"><strong>Tahun:</strong></label>
                                    <select class="form-select" id="filterTahun" name="tahun" style="width:120px">
                                        <option value="">Semua Tahun</option>
                                        <?php
                                        $stmtTahun = $conn->prepare("SELECT DISTINCT tahun FROM payroll_final WHERE id_anggota IN (SELECT id FROM anggota_sekolah WHERE jenjang = ?) ORDER BY tahun DESC");
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
                                <div class="col-auto">
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
                    <!-- End Filter Section -->

                    <!-- Tabel Detail Rekap Payroll -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-white">
                                <i class="fas fa-clipboard-list"></i> Daftar Detail Payroll - <?php echo htmlspecialchars($jenjang); ?>
                            </h6>
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
                                            <?php
                                            foreach ($headerPayheads as $ph) {
                                                echo '<th>' . htmlspecialchars($ph) . '</th>';
                                            }
                                            ?>
                                            <th>Lain-lain</th>
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
                <!-- End Page Content -->
            </div>
            <!-- End Main Content -->

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
    <!-- End Page Wrapper -->

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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Bangun array kolom dinamis untuk DataTables
        var dynamicColumns = [];
        <?php
        foreach ($headerPayheads as $ph) {
            $colKey = "payhead_" . md5($ph);
            echo "dynamicColumns.push({ data: '$colKey', name: '$colKey' });\n";
        }
        ?>

        $(document).ready(function() {
            var rekapPayrollDetailsTable = $('#rekapPayrollDetailsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: 'rekap_payroll_details.php?ajax=1&jenjang=<?php echo urlencode($jenjang); ?>',
                    type: 'POST',
                    data: function(d) {
                        d.case = 'LoadingRekapPayrollDetails';
                        d.bulan = $('#filterBulan').val();
                        d.tahun = $('#filterTahun').val();
                    },
                    beforeSend: function() {
                        $('#loadingSpinner').show();
                    },
                    complete: function() {
                        $('#loadingSpinner').hide();
                    },
                    error: function(xhr, error, thrown) {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'error',
                            title: 'Terjadi kesalahan load data detail rekap payroll.',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true
                        });
                    }
                },
                columns: [
    { data: 'id_payroll', name: 'id_payroll' },
    { data: 'nama_karyawan', name: 'nama_karyawan' },
    { data: 'bulan', name: 'bulan' },
    { data: 'tahun', name: 'tahun' },
    { data: 'total_gaji_pokok', name: 'total_gaji_pokok' }
].concat(dynamicColumns).concat([
    { data: 'total_lain_lain', name: 'total_lain_lain' }, // Added column
    { data: 'total_gaji_bersih', name: 'total_gaji_bersih' },
    { data: 'aksi', orderable: false, searchable: false }
])
,
                order: [
                    [0, 'desc']
                ],
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
                },
                dom: 'Bfrtip',
                buttons: [{
                        extend: 'excelHtml5',
                        text: '<i class="fas fa-file-excel"></i> Export Excel',
                        className: 'btn btn-success btn-sm',
                        exportOptions: {
                            columns: ':visible:not(:last-child)'
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="fas fa-file-pdf"></i> Export PDF',
                        className: 'btn btn-danger btn-sm',
                        exportOptions: {
                            columns: ':visible:not(:last-child)'
                        },
                        customize: function(doc) {
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
                            columns: ':visible:not(:last-child)'
                        }
                    }
                ],
                responsive: true
            });

            $('#btnApplyFilter').on('click', function() {
                rekapPayrollDetailsTable.ajax.reload();
            });

            $('#btnResetFilter').on('click', function() {
                $('#filterForm')[0].reset();
                rekapPayrollDetailsTable.ajax.reload();
            });

            $('#filterForm').on('keypress', function(e) {
                if (e.which === 13) {
                    $('#btnApplyFilter').click();
                }
            });

            // EVENT: Tampilkan Detail Payroll (View Detail)
            $(document).on('click', '.btn-view-full-detail', function() {
                var idPayroll = $(this).data('id');
                if (idPayroll) {
                    $.ajax({
                        url: "payroll_history.php?ajax=1",
                        type: "POST",
                        data: {
                            case: 'ViewPayrollDetail',
                            id_payroll: idPayroll
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
                                html += '<tr><th>Nama</th><td>' + d.nama + '</td></tr>';
                                html += '<tr><th>Jenjang Pendidikan</th><td>' + d.jenjang + '</td></tr>';
                                html += '<tr><th>Gaji Pokok</th><td>' + d.gaji_pokok + '</td></tr>';
                                html += '<tr><th>Total Pendapatan</th><td>' + d.total_pendapatan;
                                if (d.payheads_detail && d.payheads_detail.length > 0) {
                                    html += '<div class="row mt-2">';
                                    d.payheads_detail.forEach(function(ph) {
                                        if (ph.jenis === 'earnings') {
                                            var nominal = parseFloat(ph.amount).toLocaleString('id-ID', { minimumFractionDigits: 2 });
                                            html += '<div class="col-12 mb-1"><span class="badge bg-success me-2">' + ph.nama_payhead + '</span> <span class="text-success">Rp ' + nominal + '</span></div>';
                                        }
                                    });
                                    html += '</div>';
                                }
                                html += '</td></tr>';
                                html += '<tr><th>Total Potongan</th><td>' + d.total_potongan;
                                if (d.payheads_detail && d.payheads_detail.length > 0) {
                                    html += '<div class="row mt-2">';
                                    d.payheads_detail.forEach(function(ph) {
                                        if (ph.jenis === 'deductions') {
                                            var nominal = parseFloat(ph.amount).toLocaleString('id-ID', { minimumFractionDigits: 2 });
                                            html += '<div class="col-12 mb-1"><span class="badge bg-danger me-2">' + ph.nama_payhead + '</span> <span class="text-danger">Rp ' + nominal + '</span></div>';
                                        }
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
