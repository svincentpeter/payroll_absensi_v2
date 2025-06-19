<?php
// File: /payroll_absensi_v2/keuangan/payroll_history.php

$pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:Keuangan']);
require_once __DIR__ . '/../koneksi.php';
$jenjangList = getOrderedJenjang($conn);;
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

$nonce = '';


if (ob_get_length()) {
    ob_end_clean();
}

// =========================
// 1. Handle AJAX
// =========================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $case = isset($_POST['case']) ? sanitize_input($_POST['case']) : '';
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
// 2. Fungsi-Fungsi
// =========================

function LoadingPayrollHistory($conn)
{
    $draw    = intval($_POST['draw']   ?? 0);
    $start   = intval($_POST['start']  ?? 0);
    $length  = intval($_POST['length'] ?? 10);
    $search  = sanitize_input($_POST['search']['value'] ?? '');
    $jenjang = sanitize_input($_POST['jenjang']  ?? '');
    $bulan   = intval($_POST['bulan']   ?? 0);
    $tahun   = intval($_POST['tahun']   ?? 0);

    // 1) Bangun JOIN + WHERE terpisah
    $baseJoins = "
        FROM payroll_final p
        JOIN anggota_sekolah a ON p.id_anggota = a.id
        LEFT JOIN (
          SELECT id_anggota, SUM(jumlah) AS total_lain_lain
            FROM kenaikan_gaji_tahunan
           WHERE pindah_ke_lain_lain=1
           GROUP BY id_anggota
        ) kg ON p.id_anggota = kg.id_anggota
    ";
    $baseWhere = "WHERE 1=1";
    $params    = [];
    $types     = '';

    if ($jenjang !== '') {
        $baseWhere .= " AND a.jenjang = ?";
        $params[] = $jenjang; $types .= 's';
    }
    if ($bulan > 0) {
        $baseWhere .= " AND p.bulan = ?";
        $params[] = $bulan; $types .= 'i';
    }
    if ($tahun > 0) {
        $baseWhere .= " AND p.tahun = ?";
        $params[] = $tahun; $types .= 'i';
    }
    if ($search !== '') {
        $baseWhere .= " AND (
            CAST(p.id AS CHAR) LIKE ? OR
            a.nama LIKE ? OR
            CAST(p.bulan AS CHAR) LIKE ? OR
            CAST(p.tahun AS CHAR) LIKE ?
        )";
        for ($i=0; $i<4; $i++) {
            $params[] = "%{$search}%"; $types .= 's';
        }
    }

    // 2) Hitung filtered & total
    $stmtF = $conn->prepare("SELECT COUNT(*) AS total $baseJoins $baseWhere");
    if ($types) $stmtF->bind_param($types, ...$params);
    $stmtF->execute();
    $totalFiltered = intval($stmtF->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtF->close();

    $recordsTotal = intval($conn->query("SELECT COUNT(*) AS total FROM payroll_final")
                         ->fetch_assoc()['total'] ?? 0);

    // 3) Ambil data paging
    $sql = "
      SELECT
        p.id                     AS id,
        a.nama                   AS nama,
        a.jenjang                AS jenjang,
        p.bulan,
        p.tahun,
        p.gaji_pokok             AS gaji_pokok,
        p.salary_index_amount    AS salary_index,
        p.total_pendapatan       AS total_pendapatan,
        IFNULL(kg.total_lain_lain,0) AS total_lain_lain,
        p.potongan_koperasi      AS potongan_koperasi,
        p.total_potongan         AS total_potongan,
        p.gaji_bersih            AS gaji_bersih
      $baseJoins
      $baseWhere
      ORDER BY p.id DESC
      LIMIT ?, ?
    ";
    // tambahkan start & length ke params
    $params[] = $start; $params[] = $length;
    $types   .= 'ii';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    // 4) Bangun output
    $data = [];
    while ($r = $res->fetch_assoc()) {
        $data[] = [
            'id'                  => $r['id'],
            'nama'                => htmlspecialchars($r['nama']),
            'jenjang'             => htmlspecialchars($r['jenjang']),
            'bulan'               => getIndonesianMonthName($r['bulan']),
            'tahun'               => $r['tahun'],
            'gaji_pokok'          => formatNominal($r['gaji_pokok']),
            'salary_index'        => formatNominal($r['salary_index']),
            'total_pendapatan'    => formatNominal($r['total_pendapatan']),
            'total_lain_lain'     => formatNominal($r['total_lain_lain']),
            'potongan_koperasi'   => formatNominal($r['potongan_koperasi']),
            'total_potongan'      => formatNominal($r['total_potongan']),
            'gaji_bersih'         => formatNominal($r['gaji_bersih']),
            'aksi'                => '
              <div class="dropdown">
                <button class="btn" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                <ul class="dropdown-menu">
                  <li><a class="dropdown-item" href="payroll-details.php?id_payroll=' . $r['id'] . '">
                        <i class="fas fa-file-invoice"></i> Lihat Payroll
                      </a></li>
                  <li><a class="dropdown-item btn-view-full-detail" href="#" data-id="' . $r['id'] . '">
                        <i class="fas fa-eye"></i> View Detail
                      </a></li>
                </ul>
              </div>'
        ];
    }
    $stmt->close();

    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => $recordsTotal,
        'recordsFiltered' => $totalFiltered,
        'data'            => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}


function ViewPayrollDetail($conn)
{
    $id_payroll_final = isset($_POST['id_payroll']) ? intval($_POST['id_payroll']) : 0;
    if ($id_payroll_final <= 0) {
        send_response(1, 'ID Payroll Final tidak valid.');
    }

    // 1. Ambil data ringkasan dari payroll_final
    $stmt = $conn->prepare("
        SELECT p.*,
               a.uid, a.nip, a.nama, a.jenjang, a.role, a.job_title, a.status_kerja,
               a.masa_kerja_tahun, a.masa_kerja_bulan, a.no_rekening, a.email, a.jenis_kelamin, a.agama,
               si.level AS salary_index_level, si.base_salary AS salary_index_base
          FROM payroll_final p
          JOIN anggota_sekolah a ON p.id_anggota = a.id
     LEFT JOIN salary_indices si ON a.salary_index_id = si.id
         WHERE p.id = ?
         LIMIT 1
    ");
    if (!$stmt) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("i", $id_payroll_final);
    if (!$stmt->execute()) {
        send_response(1, 'Execute failed: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        send_response(1, 'Payroll final tidak ditemukan.');
    }
    $row = $result->fetch_assoc();
    $stmt->close();

    // Format data summary
    $row['gaji_pokok']        = formatNominal($row['gaji_pokok']);
    $row['salary_index_amount'] = formatNominal($row['salary_index_amount']);
    $row['total_pendapatan']  = formatNominal($row['total_pendapatan']);
    $row['total_potongan']    = formatNominal($row['total_potongan']);
    $row['potongan_koperasi'] = formatNominal($row['potongan_koperasi']);
    $row['gaji_bersih']       = formatNominal($row['gaji_bersih']);
    // Konversi bulan -> nama
    $row['bulan']            = getIndonesianMonthName((int)$row['bulan']);

    // Masa kerja
    $masaKerja = "";
    if ($row['masa_kerja_tahun'] > 0) {
        $masaKerja .= $row['masa_kerja_tahun'] . " Thn ";
    }
    if ($row['masa_kerja_bulan'] > 0) {
        $masaKerja .= $row['masa_kerja_bulan'] . " Bln";
    }
    $row['masa_kerja'] = trim($masaKerja) ?: "-";

    // 2. Ambil detail payroll dari payroll_detail_final
    //    => Data snapshot final. Tidak perlu filter rapel di sini, 
    //       karena kita asumsikan detail final adalah data “akhir” yang sudah diset.
    $sqlPDF = "
        SELECT pdf.id_payhead,
               pdf.nama_payhead,
               pdf.jenis,
               pdf.amount
          FROM payroll_detail_final pdf
         WHERE pdf.id_payroll_final = ?
         ORDER BY pdf.id
    ";
    $stmtPD = $conn->prepare($sqlPDF);
    if (!$stmtPD) {
        send_response(1, 'Prepare detail failed: ' . $conn->error);
    }
    $stmtPD->bind_param("i", $id_payroll_final);
    if (!$stmtPD->execute()) {
        send_response(1, 'Execute detail failed: ' . $stmtPD->error);
    }
    $resPD = $stmtPD->get_result();

    $payheads_detail = [];
    while ($rowPD = $resPD->fetch_assoc()) {
        $payheads_detail[] = [
            'id_payhead'   => $rowPD['id_payhead'],
            'nama_payhead' => $rowPD['nama_payhead'],
            'jenis'        => $rowPD['jenis'],
            'amount'       => $rowPD['amount']
        ];
    }
    $stmtPD->close();

    // Masukkan detail payhead ke array result
    $row['payheads_detail'] = $payheads_detail;

    // Kirim respons JSON ke AJAX
    send_response(0, $row);
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>History Payroll - Payroll Management System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3.3 & SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">

    <!-- DataTables (Bootstrap 5) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        /* Judul Halaman yang Diperbarui */
        .page-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 2.5rem;
            color: #0d47a1;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 3px solid #1976d2;
            padding-bottom: 0.3rem;
            margin-bottom: 1.5rem;

        }
        .page-title i {
            color: #1976d2;
            font-size: 2.8rem;
        }
        

        /* Card Header */
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }

        /* Tabel */
        thead th {
            background-color: #343a40;
            color: #fff;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
        }

        #payrollTable th,
        #payrollTable td {
            font-size: 14px;
            vertical-align: middle;
            white-space: nowrap;
        }

        .table-hover tbody tr:hover {
            background-color: #e2e6ea;
        }

        /* Perlebar select agar teks tidak terpotong */
        .form-select {
            min-width: 160px;
        }

        /* Loading Spinner */
        #loadingSpinner {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            z-index: 9999;
        }

        /* Responsive tambahan untuk form filter */
        @media (max-width: 768px) {
            .row .col-auto {
                width: 100%;
                margin-bottom: 10px;
            }
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
                <!-- Navbar -->
                <?php include __DIR__ . '/../navbar.php'; ?>
                <!-- Breadcrumb -->
                <?php include __DIR__ . '/../breadcrumb.php'; ?>

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <h1 class="page-title">
                        <i class="fas fa-history"></i> History Payroll
                    </h1>

                    <!-- Filter Section -->
                    <div class="card mb-4 shadow">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-white">
                                <i class="fas fa-search"></i> Filter Payroll History
                            </h6>
                        </div>
                        <div class="card-body" style="background-color: #f8f9fa;">
                            <form id="filterPayrollForm" class="row gy-2 gx-3 align-items-center">
                                <!-- Jenjang -->
                                <div class="col-auto">
                                    <label for="filterJenjang" class="form-label mb-0"><strong>Jenjang Pendidikan:</strong></label>
                                    <select class="form-control" id="filterJenjang" name="jenjang">
                                        <option value="">Semua Jenjang</option>
                                        <?php
$jenjangList = getOrderedJenjang($conn); // array: ['TK'=>'Taman Kanak-Kanak', ...]
foreach ($jenjangList as $kode_jenjang => $nama_jenjang) {
    echo '<option value="' . htmlspecialchars($kode_jenjang) . '">' . htmlspecialchars($nama_jenjang) . '</option>';
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
                                            echo '<option value="' . $m . '">' . getIndonesianMonthName($m) . '</option>';
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
                                        $stmtTahun = $conn->prepare("SELECT DISTINCT tahun FROM payroll_final ORDER BY tahun DESC");
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

                    <!-- Tabel History Payroll -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-white">
                                <i class="fas fa-clipboard-list"></i> Daftar History Payroll
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="payrollTable" class="table table-sm table-bordered table-striped table-hover display nowrap" style="width:100%">
                                <thead>
  <tr>
    <th>ID Payroll</th>
    <th>Nama Karyawan</th>
    <th>Jenjang</th>
    <th>Bulan</th>
    <th>Tahun</th>
    <th>Gaji Pokok</th>
    <th>Salary Indeks</th>
    <th>Total Pendapatan</th>
    <th>Lain‑lain</th>
    <th>Potongan Koperasi</th>
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

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>

    <!-- DataTables & Extensions (Bootstrap 5) -->
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

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- AutoNumeric (Opsional) -->
    <script src="https://cdn.jsdelivr.net/npm/autonumeric@4.6.0/dist/autoNumeric.min.js"></script>

    <script>
        $(document).ready(function() {
            // Inisialisasi DataTable
            var payrollTable = $('#payrollTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "payroll_history.php?ajax=1",
                    type: "POST",
                    data: function(d) {
                        d.case = 'LoadingPayrollHistory';
                        d.jenjang = $('#filterJenjang').val();
                        d.bulan = $('#filterBulan').val();
                        d.tahun = $('#filterTahun').val();
                    },
                    beforeSend: function() {
                        $('#loadingSpinner').show();
                    },
                    complete: function() {
                        $('#loadingSpinner').hide();
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Terjadi kesalahan saat memuat data payroll.'
                        });
                    }
                },
                columns: [
                    { data:'id',               name:'id' },
                    { data:'nama',             name:'nama' },
                    { data:'jenjang',          name:'jenjang' },
                    { data:'bulan',            name:'bulan' },
                    { data:'tahun',            name:'tahun' },
                    { data:'gaji_pokok',       name:'gaji_pokok' },
                    { data:'salary_index',     name:'salary_index' },
                    { data:'total_pendapatan', name:'total_pendapatan' },
                    { data:'total_lain_lain',  name:'total_lain_lain' },
                    { data:'potongan_koperasi',name:'potongan_koperasi' },
                    { data:'total_potongan',   name:'total_potongan' },
                    { data:'gaji_bersih',      name:'gaji_bersih' },
                    { data:'aksi', orderable:false, searchable:false }
                ],
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
                            columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="fas fa-file-pdf"></i> Export PDF',
                        className: 'btn btn-danger btn-sm',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
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
                            columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                        }
                    }
                ],
                responsive: true,
                autoWidth: false
            });

            // Filter
            $('#btnApplyFilterPayroll').on('click', function() {
                payrollTable.ajax.reload();
            });
            $('#btnResetFilterPayroll').on('click', function() {
                $('#filterPayrollForm')[0].reset();
                payrollTable.ajax.reload();
            });

            // Detail Payroll
            $(document).on('click', '.btn-view-full-detail', function() {
                var idPayroll = $(this).data('id');
                if (idPayroll) {
                    $.ajax({
                        url: "payroll_history.php?ajax=1",
                        type: "POST",
                        dataType: "json",
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
                                html += '<tr><th>UID</th><td>'    + (d.uid  || '-') + '</td></tr>';
                                html += '<tr><th>NIP</th><td>'    + (d.nip  || '-') + '</td></tr>';
                                html += '<tr><th>Nama</th><td>'   + d.nama + '</td></tr>';
                                html += '<tr><th>Jenjang</th><td>'+ d.jenjang + '</td></tr>';
                                html += '<tr><th>Role</th><td>'   + (d.role || '-') + '</td></tr>';
                                html += '<tr><th>Job Title</th><td>'+ (d.job_title||'-') + '</td></tr>';
                                html += '<tr><th>Status Kerja</th><td>'+ (d.status_kerja||'-') + '</td></tr>';
                                html += '<tr><th>Masa Kerja</th><td>'+ d.masa_kerja + '</td></tr>';
                                html += '<tr><th>No Rekening</th><td>'+ (d.no_rekening||'-') + '</td></tr>';
                                html += '<tr><th>Email</th><td>'  + (d.email||'-') + '</td></tr>';
                                html += '<tr><th>Jenis Kelamin</th><td>'+ (d.jenis_kelamin||'-') + '</td></tr>';
                                html += '<tr><th>Agama</th><td>'  + (d.agama||'-') + '</td></tr>';

                                // 1. Gaji Pokok
                                html += '<tr><th>Gaji Pokok</th><td>' + d.gaji_pokok + '</td></tr>';
                                // 2. Salary Indeks
                                html += '<tr><th>Salary Indeks</th><td>' + d.salary_index_amount + '</td></tr>';

                                // Total Pendapatan + list earnings payheads
                                html += '<tr><th>Total Pendapatan</th><td>' + d.total_pendapatan;
                                if (d.payheads_detail) {
                                    d.payheads_detail.filter(ph => ph.jenis==='earnings')
                                    .forEach(ph => {
                                        var nom = parseFloat(ph.amount).toLocaleString('id-ID',{minimumFractionDigits:2});
                                        html += '<div><span class="badge bg-success me-2 text-black">'+ph.nama_payhead+'</span> Rp '+nom+'</div>';
                                    });
                                }
                                html += '</td></tr>';

                                // Lain‑lain
                                html += '<tr><th>Lain‑lain</th><td>' + d.total_lain_lain + '</td></tr>';

                                // Potongan Koperasi
                                html += '<tr><th>Potongan Koperasi</th><td>' + d.potongan_koperasi + '</td></tr>';

                                // Total Potongan + list deductions payheads
                                html += '<tr><th>Total Potongan</th><td>' + d.total_potongan;
                                if (d.payheads_detail) {
                                    d.payheads_detail.filter(ph => ph.jenis==='deductions')
                                    .forEach(ph => {
                                        var nom = parseFloat(ph.amount).toLocaleString('id-ID',{minimumFractionDigits:2});
                                        html += '<div><span class="badge bg-danger me-2 text-black">'+ph.nama_payhead+'</span> Rp '+nom+'</div>';
                                    });
                                }
                                html += '</td></tr>';

                                // Gaji Bersih
                                html += '<tr><th>Gaji Bersih</th><td>' + d.gaji_bersih + '</td></tr>';
                                html += '<tr><th>Bulan</th><td>'      + d.bulan + '</td></tr>';
                                html += '<tr><th>Tahun</th><td>'      + d.tahun + '</td></tr>';
                                html += '</table>';

                                $('#detailPayrollContent').html(html);
                            } else {
                                $('#detailPayrollContent').html('<p>'+response.result+'</p>');
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
<?php
$conn->close();
?>
