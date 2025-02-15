<?php
// File: /payroll_absensi_v2/payroll/keuangan/dashboard_keuangan.php

// =========================
// 1. Inisialisasi Session, Keamanan, & Koneksi Database
// =========================
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
authorize(['keuangan', 'superadmin']); // Hanya role keuangan dan superadmin yang diizinkan
require_once __DIR__ . '/../../koneksi.php';

// Hapus output buffering jika ada
if (ob_get_length()) {
    ob_end_clean();
}

// =========================
// 2. Pengambilan Data Payroll & Filter
// =========================

// Ambil filter dari parameter GET (default: bulan dan tahun sekarang)
$bulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : date('n');
$tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');
if ($bulan < 1 || $bulan > 12) {
    $bulan = date('n');
}
if ($tahun < 2000 || $tahun > date('Y')) {
    $tahun = date('Y');
}

$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Query Data Payroll
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

// Total Gaji Pokok
$sqlTotalGaji = "SELECT SUM(gaji_pokok) AS total_gaji_pokok FROM payroll WHERE bulan = ? AND tahun = ?";
$stmt = $conn->prepare($sqlTotalGaji);
$stmt->bind_param("ii", $bulan, $tahun);
$stmt->execute();
$res = $stmt->get_result();
$totalGajiPokok = $res->fetch_assoc()['total_gaji_pokok'] ?? 0;
$stmt->close();

// Total Gaji Bersih
$sqlTotalDiterima = "SELECT SUM(gaji_bersih) AS total_diterima FROM payroll WHERE bulan = ? AND tahun = ?";
$stmt = $conn->prepare($sqlTotalDiterima);
$stmt->bind_param("ii", $bulan, $tahun);
$stmt->execute();
$res = $stmt->get_result();
$totalDiterima = $res->fetch_assoc()['total_diterima'] ?? 0;
$stmt->close();

// Data Grafik Tren Gaji Bulanan
$sqlGajiBulanan = "SELECT p.bulan, 
                          SUM(p.gaji_pokok) AS total_gaji_pokok, 
                          SUM(p.gaji_bersih) AS total_gaji_bersih
                   FROM payroll p
                   WHERE p.tahun = ?
                   GROUP BY p.bulan
                   ORDER BY p.bulan ASC";
$stmt = $conn->prepare($sqlGajiBulanan);
$stmt->bind_param("i", $tahun);
$stmt->execute();
$res = $stmt->get_result();
$bulanGrafik = [];
$gajiBulananPokok = [];
$gajiBulananBersih = [];
while ($row = $res->fetch_assoc()) {
    $bulanGrafik[] = $namaBulan[$row['bulan']] ?? $row['bulan'];
    $gajiBulananPokok[] = floatval($row['total_gaji_pokok']);
    $gajiBulananBersih[] = floatval($row['total_gaji_bersih']);
}
$stmt->close();

// Data Perbandingan Guru vs Karyawan
$jenjang_filter = isset($_GET['jenjang_filter']) ? sanitize_input($_GET['jenjang_filter']) : 'all';
$sqlJenjangOptions = "SELECT DISTINCT jenjang FROM anggota_sekolah WHERE jenjang IS NOT NULL ORDER BY jenjang ASC";
$resJenjangOptions = $conn->query($sqlJenjangOptions);
$jenjangOptions = [];
if ($resJenjangOptions) {
    while ($rowOpt = $resJenjangOptions->fetch_assoc()) {
        $jenjangOptions[] = $rowOpt['jenjang'];
    }
    $resJenjangOptions->close();
}

if ($jenjang_filter !== 'all') {
    $sqlTeacher = "SELECT COUNT(*) AS teacher_count FROM anggota_sekolah WHERE LOWER(job_title) LIKE '%guru%' AND jenjang = ?";
    $stmt = $conn->prepare($sqlTeacher);
    $stmt->bind_param("s", $jenjang_filter);
} else {
    $sqlTeacher = "SELECT COUNT(*) AS teacher_count FROM anggota_sekolah WHERE LOWER(job_title) LIKE '%guru%'";
    $stmt = $conn->prepare($sqlTeacher);
}
$stmt->execute();
$res = $stmt->get_result();
$teacher_count = $res->fetch_assoc()['teacher_count'] ?? 0;
$stmt->close();

if ($jenjang_filter !== 'all') {
    $sqlEmployee = "SELECT COUNT(*) AS employee_count FROM anggota_sekolah WHERE LOWER(job_title) NOT LIKE '%guru%' AND jenjang = ?";
    $stmt = $conn->prepare($sqlEmployee);
    $stmt->bind_param("s", $jenjang_filter);
} else {
    $sqlEmployee = "SELECT COUNT(*) AS employee_count FROM anggota_sekolah WHERE LOWER(job_title) NOT LIKE '%guru%'";
    $stmt = $conn->prepare($sqlEmployee);
}
$stmt->execute();
$res = $stmt->get_result();
$employee_count = $res->fetch_assoc()['employee_count'] ?? 0;
$stmt->close();

// Data Total Anggota Sekolah
$sqlTotalAnggota = "SELECT COUNT(*) as total_anggota FROM anggota_sekolah";
$resTotal = $conn->query($sqlTotalAnggota);
$totalAnggota = 0;
if ($resTotal) {
    $row = $resTotal->fetch_assoc();
    $totalAnggota = $row['total_anggota'] ?? 0;
    $resTotal->close();
}

// Konversi data grafik ke JSON untuk Chart.js
$bulanGrafik_json = json_encode($bulanGrafik, JSON_UNESCAPED_SLASHES);
$gajiBulananPokok_json = json_encode($gajiBulananPokok, JSON_UNESCAPED_SLASHES);
$gajiBulananBersih_json = json_encode($gajiBulananBersih, JSON_UNESCAPED_SLASHES);

// =========================
// 3. Logging & Audit
// =========================
// Catat audit log bahwa dashboard keuangan diakses
$user_nip = $_SESSION['nip'] ?? '';
add_audit_log($conn, $user_nip, 'ViewDashboardKeuangan', "Dashboard Keuangan diakses oleh user dengan NIP $user_nip.");

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Keuangan - Payroll Management System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5 CSS & SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/css/sb-admin-2.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap4.min.css">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body, .text-gray-800 { color: #000 !important; }
        .table-responsive { overflow-x: auto; }
        #loadingSpinner {
            display: none;
            position: fixed;
            z-index: 9999;
            height: 100px;
            width: 100px;
            margin: auto;
            top: 0; left: 0; bottom: 0; right: 0;
        }
        /* Gunakan layout dan style card yang konsisten */
        .card-header {
            background-color: #4e73df;
            color: #fff;
        }
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
    </style>
</head>
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar (dari template SB Admin 2) -->
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Navbar -->
                <?php include __DIR__ . '/../../navbar.php'; ?>
                <!-- End of Navbar -->

                <!-- Breadcrumb -->
                <?php include __DIR__ . '/../../breadcrumb.php'; ?>

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <!-- Header -->
                    <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-wallet me-2"></i> Dashboard Keuangan</h1>

                    <!-- Filter Payroll -->
                    <form method="GET" class="mb-4">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label for="bulan">Bulan</label>
                                <select id="bulan" name="bulan" class="form-control" required>
                                    <?php foreach($namaBulan as $num => $name): ?>
                                        <option value="<?= $num ?>" <?= ($bulan === $num) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="tahun">Tahun</label>
                                <select id="tahun" name="tahun" class="form-control" required>
                                    <?php for($y = date("Y"); $y >= 2000; $y--): ?>
                                        <option value="<?= $y ?>" <?= ($tahun === $y) ? 'selected' : '' ?>>
                                            <?= $y ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary mt-2">
                                    <i class="fas fa-search"></i> Cari
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <!-- Total Gaji Pokok -->
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col me-2 text-start">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                <i class="fas fa-money-bill-wave"></i> Total Gaji Pokok
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold">
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
                                    <div class="row align-items-center">
                                        <div class="col me-2 text-start">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                <i class="fas fa-hand-holding-usd"></i> Total Yang Diterima
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold">
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
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col me-2 text-start">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                <i class="fas fa-users"></i> Total Anggota Sekolah
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold">
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
                        <!-- Grafik Tren Gaji (Bar + Line Chart) -->
                        <div class="card shadow mb-4">
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
                        <div class="card shadow mb-4">
                            <div class="card-header card-chart-pie d-flex justify-content-between align-items-center py-3">
                                <h6 class="m-0 font-weight-bold text-white">
                                    <i class="fas fa-chart-pie"></i> Perbandingan Guru vs Karyawan
                                </h6>
                                <form method="GET" id="filterJenjangForm" class="d-inline">
                                    <input type="hidden" name="bulan" value="<?= $bulan ?>">
                                    <input type="hidden" name="tahun" value="<?= $tahun ?>">
                                    <select name="jenjang_filter" class="form-control form-control-sm" onchange="document.getElementById('filterJenjangForm').submit()">
                                        <option value="all" <?= ($jenjang_filter === 'all') ? 'selected' : ''; ?>>Semua Jenjang</option>
                                        <?php foreach($jenjangOptions as $jenjangOpt): ?>
                                            <option value="<?= htmlspecialchars($jenjangOpt) ?>" <?= ($jenjang_filter === $jenjangOpt) ? 'selected' : ''; ?>>
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
                        <div class="card shadow mb-4">
                            <div class="card-header card-calendar py-3">
                                <h6 class="m-0 font-weight-bold text-white">
                                    <i class="fas fa-calendar-alt"></i> Live Calendar & Clock
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="clock text-center mb-3" id="digitalClock">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div id="calendarContainer"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Detail Data Payroll -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-white">Detail Data Payroll</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="dataPayrollTable" class="table table-bordered table-hover display nowrap" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Nama</th>
                                            <th>Jenjang</th>
                                            <th>Gaji Pokok (Rp)</th>
                                            <th>Gaji Bersih (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $resultPayroll->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['nama']) ?></td>
                                                <td><?= htmlspecialchars($row['jenjang'] ?? 'Tidak Ditentukan') ?></td>
                                                <td><?= number_format($row['gaji_pokok'], 2, ',', '.') ?></td>
                                                <td><?= number_format($row['gaji_bersih'], 2, ',', '.') ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Export/Print Data Payroll -->
                    <div class="mb-4">
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
                <!-- End of Container Fluid -->
            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?= date("Y") ?> Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <!-- Loading Spinner -->
    <div id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- JavaScript Dependencies -->
<!-- JS Dependencies -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery.easing@1.4.1/jquery.easing.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/js/sb-admin-2.min.js"></script>
    <!-- DataTables JS & Extensions -->
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
    <!-- Chart.js & Plugin Data Labels -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    $(document).ready(function() {
        // Inisialisasi DataTable untuk tabel payroll (jika diperlukan)
        $('#dataPayrollTable').DataTable({
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
            },
            "responsive": true
        });

        // Inisialisasi Tooltip & Popover (Bootstrap 5)
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });

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

        // Chart: Tren Gaji Bulanan (Bar + Line)
        var ctxTrend = document.getElementById('gajiTrendChart').getContext('2d');
        var gajiTrendChart = new Chart(ctxTrend, {
            type: 'bar',
            data: {
                labels: <?= $bulanGrafik_json; ?>,
                datasets: [
                    {
                        label: 'Gaji Pokok',
                        data: <?= $gajiBulananPokok_json; ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Gaji Bersih',
                        data: <?= $gajiBulananBersih_json; ?>,
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
                    },
                    legend: { position: 'bottom' },
                    datalabels: {
                        anchor: 'end',
                        align: 'end',
                        color: '#444',
                        font: {
                            weight: 'bold',
                            size: 12
                        },
                        formatter: function(value) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });

        // Chart: Perbandingan Guru vs Karyawan (Pie Chart)
        var ctxComparison = document.getElementById('rekapJenjangChart').getContext('2d');
        var comparisonChart = new Chart(ctxComparison, {
            type: 'pie',
            data: {
                labels: ['Guru', 'Karyawan'],
                datasets: [{
                    data: [<?= $teacher_count; ?>, <?= $employee_count; ?>],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 99, 132, 0.6)'
                    ],
                    borderColor: '#fff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var dataset = context.dataset;
                                var total = dataset.data.reduce((prev, curr) => prev + curr, 0);
                                var currentValue = dataset.data[context.dataIndex];
                                var percentage = Math.floor((currentValue / total * 100) + 0.5);
                                return context.label + ": " + percentage + "% (" + currentValue + ")";
                            }
                        }
                    }
                }
            }
        });

        // Tombol Export Excel
        $('#exportExcel').click(function() {
            var url = '/payroll_absensi_v2/payroll/keuangan/export_rekap_gaji.php'
                    + '?format=excel&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&jenjang_filter=<?= urlencode($jenjang_filter) ?>';
            window.location.href = url;
        });

        // Tombol Export PDF
        $('#exportPDF').click(function() {
            var url = '/payroll_absensi_v2/payroll/keuangan/export_rekap_gaji.php'
                    + '?format=pdf&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&jenjang_filter=<?= urlencode($jenjang_filter) ?>';
            window.location.href = url;
        });

        // Tombol Print Tabel
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
            var options = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Asia/Jakarta' };
            var timeString = new Intl.DateTimeFormat('id-ID', options).format(now);
            $('#digitalClock').text(timeString);
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Build Simple Calendar
        function buildCalendar() {
            var today = new Date();
            var currentYear = today.getFullYear();
            var currentMonth = today.getMonth();
            var currentDate = today.getDate();
            var monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            var dayNames = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];

            var calendarHtml = '<h5 class="text-center mb-2">' + monthNames[currentMonth] + ' ' + currentYear + '</h5>';
            calendarHtml += '<table class="calendar"><thead><tr>';
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
                            calendarHtml += '<td class="today">' + day + '</td>';
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

        // Mode Dark/Light Toggle (misalnya tombol dengan id "modeToggle")
        $('#modeToggle').click(function() {
            $('body').toggleClass('dark-mode');
            $(this).find('i').toggleClass('fa-moon fa-sun');
        });

        // Help Modal Trigger (misalnya tombol dengan id "helpButton")
        $('#helpButton').click(function() {
            $('#helpModal').modal('show');
        });
    });
    </script>
</body>
</html>
<?php
$conn->close();
?>
