<?php
// File: /payroll_absensi_v2/keuangan/rekap_payroll_details.php

// =============================================================================
// 1. Pengaturan Session, Koneksi, dan Helper
// =============================================================================
require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
// Untuk halaman detail, hanya role Keuangan dan Superadmin yang diizinkan
authorize(['M:Keuangan', 'M:Superadmin'], '/payroll_absensi_v2/login.php');
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

require_once __DIR__ . '/../koneksi.php';
if (ob_get_length()) {
    ob_end_clean();
}

// Ambil parameter jenjang dari URL
$jenjang = isset($_GET['jenjang']) ? sanitize_input($_GET['jenjang']) : '';
if (empty($jenjang)) {
    echo "Jenjang tidak valid.";
    exit();
}

// Catat audit log akses halaman detail
add_audit_log(
    $conn,
    $_SESSION['nip'],
    'AccessPage',
    "Pengguna dengan NIP {$_SESSION['nip']} mengakses halaman Detail Rekap Payroll untuk jenjang '" . htmlspecialchars($jenjang) . "'."
);

// =============================================================================
// 2. Menangani Permintaan AJAX (untuk DataTables)
// =============================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        LoadingPayrollDetails($conn, $jenjang);
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    $conn->close();
    exit();
}

/**
 * Fungsi: LoadingPayrollDetails
 * Menghasilkan data JSON untuk DataTables yang menampilkan detail payroll berdasarkan jenjang.
 */
function LoadingPayrollDetails($conn, $jenjang)
{
    // Parameter DataTables
    $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = isset($_POST['search']['value']) ? sanitize_input($_POST['search']['value']) : '';

    // Dasar filter: berdasarkan jenjang
    $sqlWhere = " WHERE a.jenjang = ? ";
    $params   = [$jenjang];
    $types    = "s";
    if (!empty($search)) {
        $sqlWhere .= " AND (a.nama LIKE ? OR p.id LIKE ?)";
        $searchParam = "%" . $search . "%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types   .= "ss";
    }

    // Total record (tanpa filter pencarian)
    $sqlCountTotal = "
        SELECT COUNT(*) AS total
        FROM payroll_final p
        JOIN anggota_sekolah a ON p.id_anggota = a.id
        $sqlWhere
    ";
    $stmtTotal = $conn->prepare($sqlCountTotal);
    if (!$stmtTotal) {
        send_response(1, 'Prepare failed (total): ' . $conn->error);
    }
    $stmtTotal->bind_param($types, ...$params);
    $stmtTotal->execute();
    $rowTotal = $stmtTotal->get_result()->fetch_assoc();
    $stmtTotal->close();
    $recordsTotal = isset($rowTotal['total']) ? $rowTotal['total'] : 0;

    // Total record terfilter (dengan pencarian)
    $sqlCountFiltered = $sqlCountTotal;
    $stmtFiltered = $conn->prepare($sqlCountFiltered);
    if (!$stmtFiltered) {
        send_response(1, 'Prepare failed (filtered): ' . $conn->error);
    }
    $stmtFiltered->bind_param($types, ...$params);
    $stmtFiltered->execute();
    $rowFiltered = $stmtFiltered->get_result()->fetch_assoc();
    $stmtFiltered->close();
    $recordsFiltered = isset($rowFiltered['total']) ? $rowFiltered['total'] : 0;

    // Ambil daftar payheads (earnings + deductions)
    $earningPayheads = [];
    $resEarnings = $conn->query("SELECT DISTINCT nama_payhead FROM payroll_detail_final WHERE jenis='earnings' ORDER BY nama_payhead ASC");
    if ($resEarnings) {
        while ($row = $resEarnings->fetch_assoc()) {
            $earningPayheads[] = $row['nama_payhead'];
        }
    }
    $deductionPayheads = [];
    $resDeductions = $conn->query("SELECT DISTINCT nama_payhead FROM payroll_detail_final WHERE jenis='deductions' ORDER BY nama_payhead ASC");
    if ($resDeductions) {
        while ($row = $resDeductions->fetch_assoc()) {
            $deductionPayheads[] = $row['nama_payhead'];
        }
    }
    $headerPayheads = array_merge($earningPayheads, $deductionPayheads);

    // Buat query dinamis untuk aggregasi payheads
    $dynamicCases = [];
    foreach ($headerPayheads as $ph) {
        $escaped = $conn->real_escape_string($ph);
        $alias   = "payhead_" . md5($ph);
        $dynamicCases[] = "SUM(CASE WHEN pdf.nama_payhead = '$escaped' THEN pdf.amount ELSE 0 END) AS `$alias`";
    }
    $dynamicSelectSubquery = empty($dynamicCases) ? "0 AS dummy" : implode(", ", $dynamicCases);

    // Outer dynamic select untuk kolom-kolom payheads
    $outerDynamicSelect = "";
    foreach ($headerPayheads as $ph) {
        $alias = "payhead_" . md5($ph);
        $outerDynamicSelect .= ", IFNULL(agg_detail.`$alias`, 0) AS `$alias` ";
    }

    // Query utama untuk mengambil data payroll
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
        $sqlWhere
        GROUP BY p.id
    ";
    $orderBy = " ORDER BY p.id DESC";
    $limit   = " LIMIT ?, ?";
    $paramsData = $params;
    $typesData  = $types . "ii";
    $paramsData[] = $start;
    $paramsData[] = $length;
    $sqlData .= $orderBy . $limit;

    $stmtData = $conn->prepare($sqlData);
    if (!$stmtData) {
        send_response(1, 'Prepare failed (data): ' . $conn->error);
    }
    $stmtData->bind_param($typesData, ...$paramsData);
    $stmtData->execute();
    $resData = $stmtData->get_result();

    $data = [];
    while ($row = $resData->fetch_assoc()) {
        $rowData = [];
        $rowData["id_payroll"]     = $row['id_payroll'];
        $rowData["nama_karyawan"]  = htmlspecialchars($row['nama_karyawan']);
        $rowData["bulan"]          = getIndonesianMonthName($row['bulan']);
        $rowData["tahun"]          = $row['tahun'];
        $rowData["total_gaji_pokok"] = formatNominal($row['total_gaji_pokok']);
        foreach ($headerPayheads as $ph) {
            $alias = "payhead_" . md5($ph);
            $val   = isset($row[$alias]) ? $row[$alias] : 0;
            $rowData[$alias] = formatNominal($val);
        }
        $rowData["total_lain_lain"]   = formatNominal($row['total_lain_lain']);
        $rowData["total_gaji_bersih"] = formatNominal($row['total_gaji_bersih']);
        $data[] = $rowData;
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Rekap Payroll - <?php echo htmlspecialchars($jenjang); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- DataTables CSS (Bootstrap 5) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        /* Styling khusus tanpa sidebar/navbar */
        body {
            padding-top: 20px;
        }
        .back-btn {
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: #fff;
        }
        .table-hover tbody tr:hover {
            background-color: #e2e6ea;
        }
        /* Agar tabel tetap berada di dalam card dan responsif */
        .table-responsive {
            max-width: 100%;
            overflow-x: auto;
        }
        /* Opsional: untuk mengatur white-space agar teks tidak dipotong */
        table.dataTable thead th,
        table.dataTable tbody td {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Tombol Back -->
        <a href="rekap_payroll.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Kembali ke Rekap Payroll</a>
        <h1 class="h3 mb-4 text-dark"><i class="fas fa-file-invoice"></i> Detail Rekap Payroll - <?php echo htmlspecialchars($jenjang); ?></h1>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 fw-bold"><i class="fas fa-list"></i> Daftar Detail Payroll</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="detailPayrollTable" class="table table-sm table-bordered table-striped dt-responsive nowrap" style="width:100%;">
                        <thead>
                            <tr>
                                <th>ID Payroll</th>
                                <th>Nama Karyawan</th>
                                <th>Bulan</th>
                                <th>Tahun</th>
                                <th>Gaji Pokok</th>
                                <?php
                                // Tampilkan header dinamis untuk payheads
                                $earningHeaderPayheads = [];
                                $resE = $conn->query("SELECT DISTINCT nama_payhead FROM payroll_detail_final WHERE jenis='earnings' ORDER BY nama_payhead ASC");
                                if ($resE) {
                                    while ($rw = $resE->fetch_assoc()) {
                                        $earningHeaderPayheads[] = $rw['nama_payhead'];
                                        echo '<th>' . htmlspecialchars($rw['nama_payhead']) . '</th>';
                                    }
                                }
                                $deductionHeaderPayheads = [];
                                $resD = $conn->query("SELECT DISTINCT nama_payhead FROM payroll_detail_final WHERE jenis='deductions' ORDER BY nama_payhead ASC");
                                if ($resD) {
                                    while ($rw = $resD->fetch_assoc()) {
                                        $deductionHeaderPayheads[] = $rw['nama_payhead'];
                                        echo '<th>' . htmlspecialchars($rw['nama_payhead']) . '</th>';
                                    }
                                }
                                ?>
                                <th>Lain-lain</th>
                                <th>Total Gaji Bersih</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div> <!-- .table-responsive -->
            </div> <!-- .card-body -->
        </div> <!-- .card -->
    </div> <!-- .container-fluid -->

    <!-- Loading Spinner -->
    <div id="loadingSpinner" style="display:none; position:fixed; top:50%; left:50%; z-index:9999;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    $(document).ready(function() {
        // Susun array kolom untuk DataTable
        var columns = [
            { data: 'id_payroll', name: 'id_payroll' },
            { data: 'nama_karyawan', name: 'nama_karyawan' },
            { data: 'bulan', name: 'bulan' },
            { data: 'tahun', name: 'tahun' },
            { data: 'total_gaji_pokok', name: 'total_gaji_pokok' }
        ];
        <?php
        $allPayheads = array_merge($earningHeaderPayheads, $deductionHeaderPayheads);
        foreach ($allPayheads as $ph) {
            $colKey = "payhead_" . md5($ph);
            echo "columns.push({ data: '$colKey', name: '$colKey', defaultContent: '0' });\n";
        }
        ?>
        columns.push({ data: 'total_lain_lain', name: 'total_lain_lain' });
        columns.push({ data: 'total_gaji_bersih', name: 'total_gaji_bersih' });

        // Inisialisasi DataTable
        var detailTable = $('#detailPayrollTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: 'rekap_payroll_details.php?ajax=1&jenjang=<?php echo urlencode($jenjang); ?>',
                type: 'POST',
                data: function(d) {
                    d.case = 'LoadingPayrollDetails';
                    d.csrf_token = '<?php echo $csrf_token; ?>';
                },
                beforeSend: function() {
                    $('#loadingSpinner').show();
                },
                complete: function() {
                    $('#loadingSpinner').hide();
                },
                error: function() {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'error',
                        title: 'Terjadi kesalahan memuat data detail payroll.',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                }
            },
            columns: columns,
            order: [[0, 'desc']],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
            },
            responsive: true
        });
    });
    </script>
</body>
</html>
