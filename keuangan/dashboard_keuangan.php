<?php
// File: /payroll_absensi_v2/keuangan/dashboard_keuangan.php

// =========================
// 1. Inisialisasi Session, Keamanan, & Koneksi Database
// =========================
$pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:keuangan', 'M:superadmin']); // Hanya role keuangan dan superadmin yang diizinkan
require_once __DIR__ . '/../koneksi.php';

// Hapus output buffering jika ada
if (ob_get_length()) {
    ob_end_clean();
}

// =========================
// 2. PROSES PENGAMBILAN DATA PAYROLL DAN FILTER
// =========================

// Filter: Bulan dan Tahun dari parameter GET dengan nilai default bulan sekarang dan tahun sekarang
$bulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : date('n');
$tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');
if ($bulan < 1 || $bulan > 12) {
    $bulan = date('n');
}
if ($tahun < 2000 || $tahun > date('Y')) {
    $tahun = date('Y');
}

// Filter opsional: Jenjang
$jenjang_filter = isset($_GET['jenjang_filter']) ? trim($_GET['jenjang_filter']) : 'all';

// Nama bulan untuk grafik dan dropdown
$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// ---------------------------------------------------------
// Query Data Payroll sesuai filter bulan dan tahun
// ---------------------------------------------------------
$sqlPayroll = "SELECT p.*, a.nama, a.jenjang, si.level
               FROM payroll p
               LEFT JOIN anggota_sekolah a ON p.id_anggota = a.id
               LEFT JOIN salary_indices si ON a.salary_index_id = si.id
               WHERE p.bulan = ? AND p.tahun = ?";
$stmtPayroll = $conn->prepare($sqlPayroll);
if (!$stmtPayroll) {
    die("Prepare failed: " . $conn->error);
}
$stmtPayroll->bind_param("ii", $bulan, $tahun);
$stmtPayroll->execute();
$resultPayroll = $stmtPayroll->get_result();
$stmtPayroll->close();

// ---------------------------------------------------------
// HITUNG TOTAL GAJI POKOK & TOTAL YANG DITERIMA SEKALIGUS
// ---------------------------------------------------------
$sqlTotals = "
    SELECT
      COALESCE(SUM(COALESCE(p.gaji_pokok, a.gaji_pokok)), 0) AS total_gaji_pokok,
      COALESCE(SUM(p.gaji_bersih), 0)                 AS total_diterima
    FROM payroll p
    JOIN anggota_sekolah a ON a.id = p.id_anggota
    WHERE p.bulan = ?
      AND p.tahun = ?
      AND p.status = 'final'
";
$types  = "ii";
$params = [$bulan, $tahun];

if ($jenjang_filter !== 'all') {
    $sqlTotals .= " AND a.jenjang = ?";
    $types     .= "s";
    $params[]  = $jenjang_filter;
}

$stmt = $conn->prepare($sqlTotals);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rowTotals = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalGajiPokok = $rowTotals['total_gaji_pokok'];
$totalDiterima  = $rowTotals['total_diterima'];

// ---------------------------------------------------------
// Data Grafik Tren Gaji Bulanan untuk tahun yang dipilih
// ---------------------------------------------------------
$sqlGajiBulanan = "SELECT p.bulan,
                          SUM(p.gaji_pokok) AS total_gaji_pokok,
                          SUM(p.gaji_bersih) AS total_gaji_bersih
                   FROM payroll p
                   WHERE p.tahun = ?
                     AND p.status = 'final'
                   GROUP BY p.bulan
                   ORDER BY p.bulan ASC";
$stmt = $conn->prepare($sqlGajiBulanan);
$stmt->bind_param("i", $tahun);
$stmt->execute();
$res = $stmt->get_result();
$bulanGrafik        = [];
$gajiBulananPokok   = [];
$gajiBulananBersih  = [];
while ($row = $res->fetch_assoc()) {
    $bulanGrafik[]       = $namaBulan[$row['bulan']] ?? $row['bulan'];
    $gajiBulananPokok[]  = floatval($row['total_gaji_pokok']);
    $gajiBulananBersih[] = floatval($row['total_gaji_bersih']);
}
$stmt->close();

// ---------------------------------------------------------
// Data Perbandingan Guru vs Karyawan
// ---------------------------------------------------------
$sqlJenjangOptions = "SELECT DISTINCT jenjang FROM anggota_sekolah WHERE jenjang IS NOT NULL ORDER BY jenjang ASC";
$resJenjangOptions = $conn->query($sqlJenjangOptions);
$jenjangOptions = [];
if ($resJenjangOptions) {
    while ($rowOpt = $resJenjangOptions->fetch_assoc()) {
        $jenjangOptions[] = $rowOpt['jenjang'];
    }
    $resJenjangOptions->close();
}

// Hitung jumlah guru
if ($jenjang_filter !== 'all') {
    $sqlTeacher = "SELECT COUNT(*) AS teacher_count
                   FROM anggota_sekolah
                   WHERE LOWER(job_title) LIKE '%guru%'
                     AND jenjang = ?";
    $stmt = $conn->prepare($sqlTeacher);
    $stmt->bind_param("s", $jenjang_filter);
} else {
    $sqlTeacher = "SELECT COUNT(*) AS teacher_count
                   FROM anggota_sekolah
                   WHERE LOWER(job_title) LIKE '%guru%'";
    $stmt = $conn->prepare($sqlTeacher);
}
$stmt->execute();
$teacher_count = $stmt->get_result()->fetch_assoc()['teacher_count'] ?? 0;
$stmt->close();

// Hitung jumlah karyawan
if ($jenjang_filter !== 'all') {
    $sqlEmployee = "SELECT COUNT(*) AS employee_count
                    FROM anggota_sekolah
                    WHERE LOWER(job_title) NOT LIKE '%guru%'
                      AND jenjang = ?";
    $stmt = $conn->prepare($sqlEmployee);
    $stmt->bind_param("s", $jenjang_filter);
} else {
    $sqlEmployee = "SELECT COUNT(*) AS employee_count
                    FROM anggota_sekolah
                    WHERE LOWER(job_title) NOT LIKE '%guru%'";
    $stmt = $conn->prepare($sqlEmployee);
}
$stmt->execute();
$employee_count = $stmt->get_result()->fetch_assoc()['employee_count'] ?? 0;
$stmt->close();

// ---------------------------------------------------------
// Data Total Anggota Sekolah
// ---------------------------------------------------------
$sqlTotalAnggota = "SELECT COUNT(*) as total_anggota FROM anggota_sekolah";
$resTotal = $conn->query($sqlTotalAnggota);
$totalAnggota = ($resTotal ? $resTotal->fetch_assoc()['total_anggota'] : 0);

// ---------------------------------------------------------
// Format untuk tampilan
// ---------------------------------------------------------
$displayGajiPokok = 'Rp ' . number_format($totalGajiPokok, 2, ',', '.');
$displayDiterima  = 'Rp ' . number_format($totalDiterima,  2, ',', '.');

// Konversi data grafik ke JSON
$bulanGrafik_json        = json_encode($bulanGrafik, JSON_UNESCAPED_SLASHES);
$gajiBulananPokok_json   = json_encode($gajiBulananPokok, JSON_UNESCAPED_SLASHES);
$gajiBulananBersih_json  = json_encode($gajiBulananBersih, JSON_UNESCAPED_SLASHES);

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Dashboard Keuangan - Payroll System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5 CSS & SB Admin 2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap4.min.css">
    <!-- Bootstrap Icons & FontAwesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <style>
        .text-gray-800 {
            color: #000 !important;
        }

        body {
            color: #000 !important;
        }

        /* ===== Page Title Styling ===== */
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
    animation: fadeInSlide 0.5s ease-in-out both;
}
.page-title i {
    color: #1976d2;
    font-size: 2.8rem;
}

        /* Card heading dengan gradient */
        .card-header-gradient {
            background: linear-gradient(45deg, #36b9cc, #1cc88a);
            color: #fff;
        }

        /* Card heading gradient untuk filter */
        .card-header-filter {
            background: linear-gradient(45deg, #4e73df, #224abe);
            color: #fff;
        }

        /* Kustom styling chart grid */
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .card-calendar {
            background: linear-gradient(45deg, #0f2027, #203a43, #2c5364);
            color: #fff;
        }

        .card-chart-bar {
            background: linear-gradient(45deg, #6a11cb, #2575fc);
            color: #fff;
        }

        .card-chart-pie {
            background: linear-gradient(45deg, #ff416c, #ff4b2b);
            color: #fff;
        }

        /* Agar card ringkasan sewarna, misalnya: border-left-primary, dsb. */
        .border-left-primary {
            border-left: .25rem solid #4e73df !important;
        }

        .border-left-success {
            border-left: .25rem solid #1cc88a !important;
        }

        .border-left-info {
            border-left: .25rem solid #36b9cc !important;
        }

        .border-left-warning {
            border-left: .25rem solid #f6c23e !important;
        }

        .table.dataTable th,
        .table.dataTable td {
            text-align: center;
            vertical-align: middle;
        }

        /* Hover effect on cards */
        .card:hover {
            box-shadow: 0 0 11px rgba(33, 33, 33, .2);
        }

        /* Aturan CSS untuk kalender responsif */
        #calendarContainer {
            overflow-x: auto;
            /* Menambahkan scroll horizontal jika diperlukan */
        }

        .calendar {
            width: 100%;
            table-layout: fixed;
        }

        .calendar th,
        .calendar td {
            text-align: center;
            vertical-align: middle;
            padding: 5px;
            font-size: 0.9rem;
            /* Sesuaikan ukuran font jika perlu */
            word-wrap: break-word;
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

                <!-- Breadcrumb (Opsional) -->
                <?php include __DIR__ . '/../breadcrumb.php'; ?>

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <!-- Heading -->
                    <!-- Page Title -->
    <h1 class="page-title">
        <i class="fas fa-file-invoice-dollar"></i>
        Dashboard Keuangan
    </h1>

                    <!-- Card Filter Bulan/Tahun -->
                    <div class="card mb-4">
                        <div class="card-header card-header-filter">
                            <h5 class="m-0">
                                <i class="bi bi-funnel-fill"></i> Filter Bulan & Tahun
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label for="bulan" class="form-label">Bulan</label>
                                    <select id="bulan" name="bulan" class="form-control" required>
                                        <?php foreach ($namaBulan as $num => $name): ?>
                                            <option value="<?= $num ?>" <?= ($bulan === $num) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($name) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="tahun" class="form-label">Tahun</label>
                                    <select id="tahun" name="tahun" class="form-control" required>
                                        <?php for ($y = date("Y"); $y >= 2000; $y--): ?>
                                            <option value="<?= $y ?>" <?= ($tahun === $y) ? 'selected' : '' ?>>
                                                <?= $y ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i> Cari
                                    </button>
                                </div>
                                <div class="col-md-2">
                                    <a href="?bulan=<?= date('n') ?>&tahun=<?= date('Y') ?>"
                                        class="btn btn-outline-info w-100">
                                        Bulan Ini
                                    </a>
                                </div>
                                <div class="col-md-2">
                                    <a href="?tahun=<?= date('Y') ?>" class="btn btn-outline-info w-100">
                                        Tahun Ini
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Cards: Total Gaji Pokok, Total Yang Diterima, & Total Anggota -->
                    <div class="row mb-4">
                        <!-- Total Gaji Pokok -->
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col me-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                <i class="fas fa-money-bill-wave"></i> Total Gaji Pokok
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                Rp <?= number_format($totalGajiPokok, 2, ',', '.') ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Total Yang Diterima -->
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col me-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                <i class="fas fa-hand-holding-usd"></i> Total Yang Diterima
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                Rp <?= number_format($totalDiterima, 2, ',', '.') ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Total Anggota Sekolah -->
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-warning shadow h-100 py-2"
                                data-bs-toggle="popover" data-bs-trigger="hover" data-bs-html="true"
                                title="Detail Anggota Sekolah"
                                data-bs-content="Guru: <?= $teacher_count ?>, Karyawan: <?= $employee_count ?>">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col me-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                <i class="fas fa-users"></i> Total Anggota Sekolah
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?= number_format($totalAnggota) ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grid Chart & Informasi -->
                    <div class="chart-grid mb-4">
                        <!-- Grafik Tren Gaji (Bar + Line) -->
                        <div class="card shadow">
                            <div class="card-header card-chart-bar py-3">
                                <h6 class="m-0 font-weight-bold text-white">
                                    <i class="fas fa-chart-bar"></i> Tren Gaji Tahun <?= htmlspecialchars($tahun) ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <canvas id="gajiTrendChart"></canvas>
                            </div>
                        </div>
                        <!-- Grafik Perbandingan Guru vs Karyawan (Pie Chart) -->
                        <div class="card shadow">
                            <div class="card-header card-chart-pie d-flex justify-content-between align-items-center py-3">
                                <h6 class="m-0 font-weight-bold text-white">
                                    <i class="fas fa-chart-pie"></i> Perbandingan Guru vs Karyawan
                                </h6>
                                <form method="GET" id="filterJenjangForm" class="d-inline">
                                    <input type="hidden" name="bulan" value="<?= $bulan ?>">
                                    <input type="hidden" name="tahun" value="<?= $tahun ?>">
                                    <select name="jenjang_filter" class="form-control form-control-sm"
                                        onchange="document.getElementById('filterJenjangForm').submit()">
                                        <option value="all" <?= ($jenjang_filter === 'all') ? 'selected' : '' ?>>
                                            Semua Jenjang
                                        </option>
                                        <?php foreach ($jenjangOptions as $jenjangOpt): ?>
                                            <option value="<?= htmlspecialchars($jenjangOpt) ?>" <?= ($jenjang_filter === $jenjangOpt) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($jenjangOpt) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                            <div class="card-body">
                                <canvas id="rekapJenjangChart"></canvas>
                            </div>
                        </div>
                        <!-- Live Calendar & Clock -->
                        <div class="card shadow">
                            <div class="card-header card-calendar py-3">
                                <h6 class="m-0 font-weight-bold text-white">
                                    <i class="fas fa-calendar-alt"></i> Live Calendar & Clock
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="clock" id="digitalClock" style="font-size:1.5rem; text-align:center; margin-bottom:10px;">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div id="calendarContainer"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Data Payroll -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 card-header-gradient">
                            <h6 class="m-0 font-weight-bold text-white">
                                <i class="bi bi-table"></i> Detail Data Payroll
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover display nowrap"
                                    id="dataPayrollTable" width="100%" cellspacing="0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Nama</th>
                                            <th>Jenjang</th>
                                            <th>Gaji Pokok (Rp)</th>
                                            <th>Gaji Bersih (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmtShow = $conn->prepare($sqlPayroll);
                                        $stmtShow->bind_param("ii", $bulan, $tahun);
                                        $stmtShow->execute();
                                        $resShow = $stmtShow->get_result();
                                        while ($row = $resShow->fetch_assoc()):
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['nama']) ?></td>
                                                <td><?= htmlspecialchars($row['jenjang'] ?? 'Tidak Ditentukan') ?></td>
                                                <td><?= number_format($row['gaji_pokok'], 2, ',', '.') ?></td>
                                                <td><?= number_format($row['gaji_bersih'], 2, ',', '.') ?></td>
                                            </tr>
                                        <?php
                                        endwhile;
                                        $stmtShow->close();
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Export, Print -->
                    <div class="mb-4 d-flex flex-wrap gap-2">
                        <button id="exportExcel" class="btn btn-success" data-bs-toggle="tooltip" title="Export ke Excel">
                            <i class="bi bi-file-earmark-excel-fill"></i> Export Excel
                        </button>
                        <button id="exportPDF" class="btn btn-danger" data-bs-toggle="tooltip" title="Export ke PDF">
                            <i class="bi bi-file-earmark-pdf-fill"></i> Export PDF
                        </button>
                        <button id="printTable" class="btn btn-secondary" data-bs-toggle="tooltip" title="Print Data">
                            <i class="bi bi-printer-fill"></i> Print
                        </button>
                    </div>
                </div>
                <!-- /.container-fluid -->
            </div>
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <!-- Help Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="helpModalLabel">Panduan Penggunaan Dashboard</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Dashboard Keuangan ini menyediakan informasi terkait data payroll, tren gaji, serta rekap anggota sekolah.</p>
                    <ul>
                        <li>Gunakan filter di atas untuk memilih bulan/tahun dan jenjang yang diinginkan.</li>
                        <li>Klik tombol <strong>Bulan Ini</strong> atau <strong>Tahun Ini</strong> untuk cepat mengatur filter.</li>
                        <li>Pada grafik, Anda dapat melihat tren gaji pokok (bar) dan gaji bersih (line) secara bersamaan.</li>
                        <li>Gunakan tombol ekspor untuk mengunduh data dalam format Excel, PDF, atau mencetak langsung tabel.</li>
                        <li>Anda dapat beralih antara mode Dark dan Light menggunakan tombol di pojok kanan atas (jika diaktifkan).</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        $(document).ready(function() {
            // Inisialisasi DataTables
            $('#dataPayrollTable').DataTable({
                "language": {
                    "url": "https://cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
                },
                "responsive": true
            });

            // Tooltip & Popover
            $('[data-bs-toggle="tooltip"]').tooltip();
            $('[data-bs-toggle="popover"]').popover();

            // Chart: Tren Gaji
            var ctxTrend = document.getElementById('gajiTrendChart').getContext('2d');
            var gajiTrendChart = new Chart(ctxTrend, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($bulanGrafik, JSON_UNESCAPED_SLASHES); ?>,
                    datasets: [{
                            label: 'Gaji Pokok',
                            data: <?= json_encode($gajiBulananPokok, JSON_UNESCAPED_SLASHES); ?>,
                            backgroundColor: 'rgba(75, 192, 192, 0.6)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Gaji Bersih',
                            data: <?= json_encode($gajiBulananBersih, JSON_UNESCAPED_SLASHES); ?>,
                            type: 'line',
                            fill: false,
                            borderColor: 'rgba(255, 159, 64, 1)',
                            backgroundColor: 'rgba(255, 159, 64, 0.6)',
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + value.toLocaleString('id-ID');
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ": Rp " + context.parsed.y.toLocaleString('id-ID');
                                }
                            }
                        }
                    }
                }
            });

            // Chart: Perbandingan Guru vs Karyawan
            var ctxComparison = document.getElementById('rekapJenjangChart').getContext('2d');
            var comparisonChart = new Chart(ctxComparison, {
                type: 'pie',
                data: {
                    labels: ['Guru', 'Karyawan'],
                    datasets: [{
                        data: [<?= $teacher_count; ?>, <?= $employee_count; ?>],
                        backgroundColor: ['rgba(54, 162, 235, 0.6)', 'rgba(255, 99, 132, 0.6)'],
                        borderColor: '#fff',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var dataset = context.dataset;
                                    var total = dataset.data.reduce(function(prev, curr) {
                                        return prev + curr;
                                    });
                                    var currentValue = dataset.data[context.dataIndex];
                                    var percentage = Math.floor((currentValue / total * 100) + 0.5);
                                    return context.label + ": " + percentage + "% (" + currentValue + ")";
                                }
                            }
                        }
                    }
                }
            });

            // Export Excel
            $('#exportExcel').click(function() {
                var url = '/payroll_absensi_v2/keuangan/export_rekap_gaji.php' +
                    '?format=excel&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&jenjang_filter=<?= urlencode($jenjang_filter) ?>';
                window.location.href = url;
            });

            // Export PDF
            $('#exportPDF').click(function() {
                var url = '/payroll_absensi_v2/keuangan/export_rekap_gaji.php' +
                    '?format=pdf&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&jenjang_filter=<?= urlencode($jenjang_filter) ?>';
                window.location.href = url;
            });

            // Print Tabel
            $('#printTable').click(function() {
                var divToPrint = document.getElementById("dataPayrollTable");
                var newWin = window.open("");
                newWin.document.write("<html><head><title>Print Data Payroll</title>");
                newWin.document.write("<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'>");
                newWin.document.write("</head><body>");
                newWin.document.write(divToPrint.outerHTML);
                newWin.document.write("</body></html>");
                newWin.print();
                newWin.close();
            });

            // Live Clock
            function updateClock() {
                var now = new Date();
                var options = {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false,
                    timeZone: 'Asia/Jakarta'
                };
                var timeString = new Intl.DateTimeFormat('id-ID', options).format(now);
                $('#digitalClock').text(timeString);
            }
            setInterval(updateClock, 1000);
            updateClock();

            // Build Calendar
            function buildCalendar() {
                var today = new Date();
                var currentYear = today.getFullYear();
                var currentMonth = today.getMonth();
                var currentDate = today.getDate();
                var monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                var dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

                var calendarHtml = '<h5 class="text-center mb-2">' + monthNames[currentMonth] + ' ' + currentYear + '</h5>';
                calendarHtml += '<table class="calendar table table-bordered"><thead><tr>';
                for (var i = 0; i < dayNames.length; i++) {
                    calendarHtml += '<th>' + dayNames[i] + '</th>';
                }
                calendarHtml += '</tr></thead><tbody>';

                var firstDay = new Date(currentYear, currentMonth, 1).getDay();
                var daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
                var day = 1;

                for (var row = 0; row < 6; row++) {
                    calendarHtml += '<tr>';
                    for (var col = 0; col < 7; col++) {
                        if (row === 0 && col < firstDay) {
                            calendarHtml += '<td></td>';
                        } else if (day > daysInMonth) {
                            calendarHtml += '<td></td>';
                        } else {
                            if (day === currentDate) {
                                calendarHtml += '<td class="table-primary fw-bold">' + day + '</td>';
                            } else {
                                calendarHtml += '<td>' + day + '</td>';
                            }
                            day++;
                        }
                    }
                    calendarHtml += '</tr>';
                    if (day > daysInMonth) break;
                }
                calendarHtml += '</tbody></table>';
                $('#calendarContainer').html(calendarHtml);
            }
            buildCalendar();
        });
    </script>
</body>

</html>
<?php
$conn->close();
?>