<?php
// File: /payroll_absensi_v2/payroll/keuangan/rekap_payroll.php

require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:Keuangan', 'M:Superadmin']);
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

require_once __DIR__ . '/../../koneksi.php';
if (ob_get_length()) ob_end_clean();

add_audit_log(
    $conn,
    $_SESSION['user_id'],
    'AccessPage',
    "Pengguna dengan ID {$_SESSION['user_id']} dan peran '{$_SESSION['role']}' mengakses halaman Rekap Payroll."
);

// ======== Handle AJAX Requests ========
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verifikasi CSRF
        $csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        verify_csrf_token($csrf);

        $case = isset($_POST['case']) ? sanitize_input($_POST['case']) : '';
        switch ($case) {
            case 'LoadingRekapPayroll':
                add_audit_log(
                    $conn,
                    $_SESSION['user_id'],
                    'LoadingRekapPayroll',
                    "Pengguna dengan ID {$_SESSION['user_id']} memuat data rekap payroll."
                );
                LoadingRekapPayroll($conn);
                break;
            case 'ViewRekapPayrollDetail':
                ViewRekapPayrollDetail($conn);
                break;
            case 'AddAuditLog':
                $action  = isset($_POST['action']) ? sanitize_input($_POST['action']) : '';
                $details = isset($_POST['details']) ? sanitize_input($_POST['details']) : '';
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
                send_response(1, 'Kasus tidak valid.');
        }
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    $conn->close();
    exit();
}

// ======== Fungsi-Fungsi ========
function LoadingRekapPayroll($conn) {
    $draw    = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start   = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length  = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search  = isset($_POST['search']['value']) ? sanitize_input($_POST['search']['value']) : '';

    $jenjang = isset($_POST['jenjang']) ? sanitize_input($_POST['jenjang']) : '';
    $bulan   = isset($_POST['bulan']) ? intval($_POST['bulan']) : 0;
    $tahun   = isset($_POST['tahun']) ? intval($_POST['tahun']) : 0;

    $sqlBase = "FROM payroll p 
                JOIN anggota_sekolah a ON p.id_anggota = a.id 
                WHERE 1=1";
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
        $sqlBase .= " AND a.jenjang LIKE ?";
        $params[] = "%{$search}%";
        $types  .= "s";
    }

    // Hitung total terfilter
    $sqlCountFiltered = "SELECT COUNT(DISTINCT a.jenjang) AS total " . $sqlBase;
    $stmtFiltered = $conn->prepare($sqlCountFiltered);
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

    // Hitung total keseluruhan (tanpa filter)
    $sqlTotal = "SELECT COUNT(DISTINCT a.jenjang) AS total 
                 FROM payroll p 
                 JOIN anggota_sekolah a ON p.id_anggota = a.id";
    $resTotal = $conn->query($sqlTotal);
    if (!$resTotal) {
        send_response(1, 'Query gagal: ' . $conn->error);
    }
    $rowTotal = $resTotal->fetch_assoc();
    $recordsTotal = isset($rowTotal['total']) ? $rowTotal['total'] : 0;

    // Ambil data rekap group by jenjang
    $sqlData = "SELECT a.jenjang,
                       SUM(p.gaji_pokok) AS total_gaji_pokok,
                       SUM(p.total_pendapatan) AS total_pendapatan,
                       SUM(p.total_potongan) AS total_potongan,
                       SUM(p.gaji_bersih) AS total_gaji_bersih
                " . $sqlBase . " 
                GROUP BY a.jenjang";

    $orderBy = " ORDER BY a.jenjang ASC";
    if (isset($_POST['order'][0]['column']) && isset($_POST['columns'])) {
        $colIndex = intval($_POST['order'][0]['column']);
        $allowedCols = [
            'jenjang',
            'total_gaji_pokok',
            'total_pendapatan',
            'total_potongan',
            'total_gaji_bersih'
        ];
        if (isset($_POST['columns'][$colIndex]['data']) && in_array($_POST['columns'][$colIndex]['data'], $allowedCols)) {
            $colName = $_POST['columns'][$colIndex]['data'];
            $colSortOrder = ($_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
            $mapCol = [
                'jenjang'           => 'a.jenjang',
                'total_gaji_pokok'  => 'total_gaji_pokok',
                'total_pendapatan'  => 'total_pendapatan',
                'total_potongan'    => 'total_potongan',
                'total_gaji_bersih' => 'total_gaji_bersih'
            ];
            if (isset($mapCol[$colName])) {
                $orderBy = " ORDER BY " . $mapCol[$colName] . " " . $colSortOrder;
            }
        }
    }

    $limit = " LIMIT ?, ?";
    $paramsData = $params;
    $typesData = $types . "ii";
    $paramsData[] = $start;
    $paramsData[] = $length;
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
            "total_gaji_pokok"  => formatNominal($row['total_gaji_pokok']),
            "total_pendapatan"  => formatNominal($row['total_pendapatan']),
            "total_potongan"    => formatNominal($row['total_potongan']),
            "total_gaji_bersih" => formatNominal($row['total_gaji_bersih']),
            "aksi" => '
                <a href="rekap_payroll_details.php?jenjang=' . urlencode($row['jenjang']) . '" 
                   class="btn btn-info btn-sm me-1" 
                   data-bs-toggle="tooltip" 
                   title="Lihat Detail Payroll">
                    <i class="fas fa-file-invoice"></i>
                </a>
                <button class="btn btn-secondary btn-sm btn-view-rekap-detail" 
                        data-jenjang="' . htmlspecialchars($row['jenjang']) . '" 
                        data-bs-toggle="tooltip" 
                        title="View Detail">
                    <i class="fas fa-eye"></i>
                </button>
            '
        ];
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

function ViewRekapPayrollDetail($conn) {
    $jenjang = isset($_POST['jenjang']) ? sanitize_input($_POST['jenjang']) : '';
    if (empty($jenjang)) {
        send_response(1, 'Jenjang tidak valid.');
    }

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
    $stmt->execute();
    $result = $stmt->get_result();
    $details = [];
    while ($row = $result->fetch_assoc()) {
        $details[] = $row;
    }
    $stmt->close();

    $earnings = [];
    $deductions = [];
    foreach ($details as $d) {
        if ($d['jenis'] === 'earnings') {
            $earnings[] = $d;
        } elseif ($d['jenis'] === 'deductions') {
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
    <title>Rekap Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5.3.3 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <!-- DataTables CSS (Bootstrap 5) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        /* Custom styles untuk halaman Rekap Payroll */
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }
        .table-hover tbody tr:hover {
            background-color: #e2e6ea;
        }
        thead th {
            background-color: #343a40;
            color: #fff;
            text-align: center;
            vertical-align: middle;
        }
        #rekapPayrollTable th, #rekapPayrollTable td {
            font-size: 14px;
            vertical-align: middle;
        }
        #loadingSpinner {
            position: fixed;
            top: 50%;
            left: 50%;
            z-index: 9999;
            display: none;
        }
        .card-filter-header {
            background-color: #4e73df; /* Warna utama SB Admin 2 */
            color: #fff;
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
        }
        .form-select {
            min-width: 160px; /* perlebar select agar tulisan tidak terpotong */
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
                    <h1 class="h3 mb-4 text-gray-800">
                        <i class="fas fa-chart-bar"></i> Rekap Payroll
                    </h1>

                    <!-- Filter Section -->
                    <div class="card mb-4 shadow" style="border-radius: 0.5rem;">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
    <h6 class="m-0 fw-bold text-white">
      <i class="fas fa-search"></i> Filter Rekap Payroll
    </h6>
  </div>
                        <div class="card-body" style="background-color: #f8f9fa;">
                            <form id="filterForm" class="row gy-2 gx-3 align-items-center">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <!-- Jenjang -->
                                <div class="col-auto">
                                <label for="filterJenjang" class="form-label mb-0"><strong>Jenjang Pendidikan:</strong></strong></label>
                <select class="form-control" id="filterJenjang" name="jenjang">
                    <option value="">Semua Jenjang</option>
                    <?php
                    // Ambil daftar jenjang yang telah didefinisikan di helper
                    $jenjangList = getOrderedJenjang();
                    foreach ($jenjangList as $jenjang) {
                        echo '<option value="' . htmlspecialchars($jenjang) . '">' . htmlspecialchars($jenjang) . '</option>';
                    }
                    ?>
                </select>
            </div>
                                <!-- Bulan -->
                                <div class="col-auto">
                                    <label for="filterBulan" class="form-label mb-0"><strong>Bulan:</strong></label>
                                    <select class="form-select" id="filterBulan" name="bulan">
                                        <option value="">Semua Bulan</option>
                                        <?php
                                        for ($m = 1; $m <= 12; $m++) {
                                            echo "<option value=\"$m\">" . getIndonesianMonthName($m) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <!-- Tahun -->
                                <div class="col-auto">
                                    <label for="filterTahun" class="form-label mb-0"><strong>Tahun:</strong></label>
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
                                <!-- Tombol -->
                                <div class="col-auto d-flex align-items-end">
                                    <button type="button" class="btn btn-primary me-2" id="btnApplyFilter">
                                        <i class="fas fa-filter"></i> Terapkan Filter
                                    </button>
                                    <button type="button" class="btn btn-secondary me-2" id="btnResetFilter">
                                        <i class="fas fa-undo"></i> Reset Filter
                                    </button>
                                    <button type="button" class="btn btn-success" id="btnExportData">
                                        <i class="fas fa-file-export"></i> Export Data
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- End Filter Section -->

                    <!-- Tabel Rekap Payroll -->
                    <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
    <h6 class="m-0 fw-bold text-white">
      <i class="fas fa-clipboard-list"></i> Daftar Rekap Payroll
    </h6>
  </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="rekapPayrollTable" class="table table-sm table-bordered table-striped table-hover display nowrap" style="width:100%">
                                    <thead>
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
                <!-- End Page Content -->

                <!-- Loading Spinner -->
                <div id="loadingSpinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <!-- DataTables JS (Bootstrap 5) -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function(){
        // Tooltip Bootstrap 5
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // SweetAlert2 Toast
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

        var csrfToken = '<?php echo $csrf_token; ?>';

        // DataTables
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
                error: function(){
                    showToast('Terjadi kesalahan load data rekap payroll.', 'error');
                }
            },
            columns: [
                { data:'jenjang', name:'jenjang' },
                { data:'total_gaji_pokok', name:'total_gaji_pokok' },
                { data:'total_pendapatan', name:'total_pendapatan' },
                { data:'total_potongan', name:'total_potongan' },
                { data:'total_gaji_bersih', name:'total_gaji_bersih' },
                { data:'aksi', orderable:false, searchable:false }
            ],
            order: [[0,'asc']],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
            },
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Export Excel',
                    className: 'btn btn-success btn-sm',
                    exportOptions: { columns: [0,1,2,3,4] }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> Export PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: { columns: [0,1,2,3,4] },
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
                    exportOptions: { columns: [0,1,2,3,4] }
                }
            ],
            responsive: true
        });

        // Apply Filter
        $('#btnApplyFilter').on('click', function(){
            $.ajax({
                url: 'rekap_payroll.php?ajax=1',
                type: 'POST',
                data: {
                    case: 'AddAuditLog',
                    csrf_token: csrfToken,
                    action: 'ApplyFilter',
                    details: `Filter diterapkan: Jenjang=${$('#filterJenjang').val()||'Semua'}, Bulan=${$('#filterBulan').val()||'Semua'}, Tahun=${$('#filterTahun').val()||'Semua'}.`
                }
            });
            rekapPayrollTable.ajax.reload();
        });

        // Reset Filter
        $('#btnResetFilter').on('click', function(){
            $.ajax({
                url: 'rekap_payroll.php?ajax=1',
                type: 'POST',
                data: {
                    case: 'AddAuditLog',
                    csrf_token: csrfToken,
                    action: 'ResetFilter',
                    details: 'Filter direset.'
                }
            });
            $('#filterForm')[0].reset();
            rekapPayrollTable.ajax.reload();
        });

        // Export Data (Excel)
        $('#btnExportData').on('click', function(){
            rekapPayrollTable.button('.buttons-excel').trigger();
            $.ajax({
                url: 'rekap_payroll.php?ajax=1',
                type: 'POST',
                data: {
                    case: 'AddAuditLog',
                    csrf_token: csrfToken,
                    action: 'ExportData',
                    details: 'Data diekspor ke Excel.'
                }
            });
        });

        // Detail Rekap Payroll
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

        // Trigger filter on Enter key
        $('#filterForm').on('keypress', function(e){
            if(e.which === 13){
                $('#btnApplyFilter').click();
            }
        });
    });
    </script>
</body>
</html>
