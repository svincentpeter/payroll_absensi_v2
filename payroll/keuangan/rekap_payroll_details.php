<?php
// File: /payroll_absensi_v2/payroll/keuangan/rekap_payroll_details.php

// =========================
// 1. Inisialisasi Session & Pengaturan Awal
// =========================
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
authorize(['keuangan', 'superadmin', 'sdm']);
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];
require_once __DIR__ . '/../../koneksi.php';
if (ob_get_length()) {
    ob_end_clean();
}

// =========================
// 2. Ambil Parameter & Catat Audit Log
// =========================
$jenjang = isset($_GET['jenjang']) ? sanitize_input($_GET['jenjang']) : '';
if (empty($jenjang)) {
    echo "Jenjang tidak valid.";
    exit();
}

add_audit_log(
    $conn,
    $_SESSION['user_id'],
    'AccessPage',
    "Pengguna dengan ID {$_SESSION['user_id']} dan peran '{$_SESSION['role']}' mengakses halaman Rekap Payroll Details untuk jenjang '{$jenjang}'."
);

// =========================
// 3. Handle Permintaan AJAX
// =========================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Catatan: verifikasi token CSRF dapat ditambahkan jika diperlukan
        $case = isset($_POST['case']) ? sanitize_input($_POST['case']) : '';
        switch ($case) {
            case 'LoadingRekapPayrollDetails':
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
    $search = isset($_POST['search']['value']) ? sanitize_input($_POST['search']['value']) : '';

    // Filter tambahan: Bulan dan Tahun (jika ada)
    $bulan = isset($_POST['bulan']) ? intval($_POST['bulan']) : 0;
    $tahun = isset($_POST['tahun']) ? intval($_POST['tahun']) : 0;

    // Query dasar: join payroll dan anggota_sekolah
    $sqlBase = "FROM payroll p JOIN anggota_sekolah a ON p.id_anggota = a.id WHERE a.jenjang = ?";
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

    // Query data: ambil kolom yang diperlukan
    $sqlData = "SELECT p.id AS id_payroll, a.nama AS nama_karyawan, p.bulan, p.tahun, p.gaji_pokok, p.total_pendapatan, p.total_potongan, p.gaji_bersih " . $sqlBase;
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
    $stmtData->bind_param($typesData, ...$paramsData);
    $stmtData->execute();
    $resData = $stmtData->get_result();

    $data = [];
    while ($row = $resData->fetch_assoc()) {
        $data[] = [
            "id_payroll"       => $row['id_payroll'],
            "nama_karyawan"    => htmlspecialchars($row['nama_karyawan']),
            "bulan"            => getIndonesianMonthName($row['bulan']),
            "tahun"            => $row['tahun'],
            "gaji_pokok"       => 'Rp ' . number_format($row['gaji_pokok'], 2, ',', '.'),
            "total_pendapatan" => 'Rp ' . number_format($row['total_pendapatan'], 2, ',', '.'),
            "total_potongan"   => 'Rp ' . number_format($row['total_potongan'], 2, ',', '.'),
            "gaji_bersih"      => 'Rp ' . number_format($row['gaji_bersih'], 2, ',', '.'),
            "aksi"             => '
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
</div>'
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
    <!-- DataTables CSS untuk Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.1.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
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
                        <i class="fas fa-chart-bar"></i> Detail Rekap Payroll - <?php echo htmlspecialchars($jenjang); ?>
                    </h1>

                    <!-- Filter Section -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <strong>Filter Detail Rekap Payroll</strong>
                        </div>
                        <div class="card-body">
                            <form id="filterForm" class="row gy-2 gx-3 align-items-center">
                                <!-- Filter Bulan -->
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
                                <!-- Filter Tahun -->
                                <div class="col-auto">
                                    <label for="filterTahun" class="form-label"><strong>Tahun:</strong></label>
                                    <select class="form-select" id="filterTahun" name="tahun" style="width:120px">
                                        <option value="">Semua Tahun</option>
                                        <?php
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
    <script src="https://cdn.jsdelivr.net/npm/autonumeric@4.6.0/dist/autoNumeric.min.js"></script>
    <script>
    $(document).ready(function(){
        // Inisialisasi tooltip dengan Bootstrap 5
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });

        var rekapPayrollDetailsTable = $('#rekapPayrollDetailsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: 'rekap_payroll_details.php?ajax=1&jenjang=<?php echo urlencode($jenjang); ?>',
                type: 'POST',
                data: function(d){
                    d.case = 'LoadingRekapPayrollDetails';
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

        function showToast(message, icon = 'success') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: icon,
                title: message,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        }

        // EVENT: Apply Filter
        $('#btnApplyFilter').on('click', function(){
            rekapPayrollDetailsTable.ajax.reload();
        });

        // EVENT: Reset Filter
        $('#btnResetFilter').on('click', function(){
            $('#filterForm')[0].reset();
            rekapPayrollDetailsTable.ajax.reload();
        });

        // EVENT: Trigger filter on Enter key
        $('#filterForm').on('keypress', function(e){
            if(e.which === 13){
                $('#btnApplyFilter').click();
            }
        });

        // EVENT: Tampilkan Detail Payroll (View Detail)
        $(document).on('click', '.btn-view-full-detail', function(){
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
                                    var nominal = parseFloat(ph.amount).toLocaleString('id-ID',{minimumFractionDigits:2});
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
                                    var nominal = parseFloat(ph.amount).toLocaleString('id-ID',{minimumFractionDigits:2});
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
