<?php
// File: /payroll_absensi_v2/payroll/keuangan/dashboard_keuangan.php

// =========================
// 1. Pengaturan Keamanan
// =========================

// Jika BASE_URL belum didefinisikan, definisikan di sini
if (!defined('BASE_URL')) {
    define('BASE_URL', '/payroll_absensi_v2');
}

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
generate_csrf_token();

$nonce = base64_encode(random_bytes(16));
$_SESSION['csp_nonce'] = $nonce;

if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirect);
    exit();
}

header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("Content-Security-Policy: default-src 'self'; " .
       "script-src 'self' " .
           BASE_URL . "/dist/libs/jquery/jquery-3.7.1.min.js " .
           BASE_URL . "/dist/libs/bootstrap/dist/js/bootstrap.bundle.min.js " .
           BASE_URL . "/dist/libs/datatables/datatables.min.js " .
           BASE_URL . "/dist/libs/chart.js/chart.min.js 'nonce-$nonce'; " .
       "style-src 'self' 'nonce-$nonce' 'unsafe-inline'; " .
       "img-src 'self' data:; " .
       "font-src 'self'; " .
       "connect-src 'self'");

// =========================
// 2. Pemeriksaan Akses
// =========================
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['keuangan', 'superadmin'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

require_once __DIR__ . '/../../koneksi.php';

// =========================
// 3. Proses Data Payroll & Filter
// =========================
$bulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : date('n');
$tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');
if ($bulan < 1 || $bulan > 12) { $bulan = date('n'); }
if ($tahun < 2000 || $tahun > date('Y')) { $tahun = date('Y'); }

$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Data Payroll
$sqlPayroll = "SELECT p.*, a.nama, a.jenjang, si.level 
               FROM payroll p
               LEFT JOIN anggota_sekolah a ON p.id_anggota = a.id
               LEFT JOIN salary_indices si ON a.salary_index_id = si.id
               WHERE p.bulan = ? AND p.tahun = ?";
$stmtPayroll = $conn->prepare($sqlPayroll);
$stmtPayroll->bind_param("ii", $bulan, $tahun);
$stmtPayroll->execute();
$resultPayroll = $stmtPayroll->get_result();

// Total Gaji Pokok
$sqlTotalGaji = "SELECT SUM(gaji_pokok) AS total_gaji_pokok FROM payroll WHERE bulan = ? AND tahun = ?";
$stmtTotalGaji = $conn->prepare($sqlTotalGaji);
$stmtTotalGaji->bind_param("ii", $bulan, $tahun);
$stmtTotalGaji->execute();
$resultTotalGaji = $stmtTotalGaji->get_result();
$totalGajiPokok = $resultTotalGaji->fetch_assoc()['total_gaji_pokok'] ?? 0;
$stmtTotalGaji->close();

// Total Gaji Bersih
$sqlTotalDiterima = "SELECT SUM(gaji_bersih) AS total_diterima FROM payroll WHERE bulan = ? AND tahun = ?";
$stmtTotalDiterima = $conn->prepare($sqlTotalDiterima);
$stmtTotalDiterima->bind_param("ii", $bulan, $tahun);
$stmtTotalDiterima->execute();
$resultTotalDiterima = $stmtTotalDiterima->get_result();
$totalDiterima = $resultTotalDiterima->fetch_assoc()['total_diterima'] ?? 0;
$stmtTotalDiterima->close();

// Grafik tren gaji bulanan
$sqlGajiBulanan = "SELECT p.bulan, SUM(p.gaji_pokok) AS total_gaji_pokok, SUM(p.gaji_bersih) AS total_gaji_bersih 
                   FROM payroll p
                   WHERE p.tahun = ?
                   GROUP BY p.bulan
                   ORDER BY p.bulan ASC";
$stmtGajiBulanan = $conn->prepare($sqlGajiBulanan);
$stmtGajiBulanan->bind_param("i", $tahun);
$stmtGajiBulanan->execute();
$resultGajiBulanan = $stmtGajiBulanan->get_result();
$bulanGrafik = $gajiBulananPokok = $gajiBulananBersih = [];
while ($row = $resultGajiBulanan->fetch_assoc()) {
    $bulanGrafik[] = $namaBulan[$row['bulan']] ?? $row['bulan'];
    $gajiBulananPokok[] = floatval($row['total_gaji_pokok']);
    $gajiBulananBersih[] = floatval($row['total_gaji_bersih']);
}
$stmtGajiBulanan->close();

// Data master anggota sekolah
$sqlTotalAnggota = "SELECT COUNT(*) as total_anggota FROM anggota_sekolah";
$resultTotalAnggota = $conn->query($sqlTotalAnggota);
$totalAnggota = ($resultTotalAnggota) ? $resultTotalAnggota->fetch_assoc()['total_anggota'] : 0;

$sqlGuruAll = "SELECT COUNT(*) as guru_count FROM anggota_sekolah WHERE LOWER(job_title) LIKE '%guru%'";
$resultGuruAll = $conn->query($sqlGuruAll);
$guruAll = ($resultGuruAll) ? $resultGuruAll->fetch_assoc()['guru_count'] : 0;

$sqlKaryawanAll = "SELECT COUNT(*) as karyawan_count FROM anggota_sekolah WHERE LOWER(job_title) NOT LIKE '%guru%'";
$resultKaryawanAll = $conn->query($sqlKaryawanAll);
$karyawanAll = ($resultKaryawanAll) ? $resultKaryawanAll->fetch_assoc()['karyawan_count'] : 0;

// Filter perbandingan Guru vs Karyawan
$jenjang_filter = isset($_GET['jenjang_filter']) ? $_GET['jenjang_filter'] : 'all';
$sqlJenjangOptions = "SELECT DISTINCT jenjang FROM anggota_sekolah WHERE jenjang IS NOT NULL ORDER BY jenjang ASC";
$resultJenjangOptions = $conn->query($sqlJenjangOptions);
$jenjangOptions = [];
if ($resultJenjangOptions) {
    while ($row = $resultJenjangOptions->fetch_assoc()){
       $jenjangOptions[] = $row['jenjang'];
    }
}

if ($jenjang_filter !== 'all') {
    $sqlTeacher = "SELECT COUNT(*) AS teacher_count FROM anggota_sekolah WHERE LOWER(job_title) LIKE '%guru%' AND jenjang = ?";
    $stmtTeacher = $conn->prepare($sqlTeacher);
    $stmtTeacher->bind_param("s", $jenjang_filter);
} else {
    $sqlTeacher = "SELECT COUNT(*) AS teacher_count FROM anggota_sekolah WHERE LOWER(job_title) LIKE '%guru%'";
    $stmtTeacher = $conn->prepare($sqlTeacher);
}
$stmtTeacher->execute();
$resultTeacher = $stmtTeacher->get_result();
$teacher_count = $resultTeacher->fetch_assoc()['teacher_count'] ?? 0;
$stmtTeacher->close();

if ($jenjang_filter !== 'all') {
    $sqlEmployee = "SELECT COUNT(*) AS employee_count FROM anggota_sekolah WHERE LOWER(job_title) NOT LIKE '%guru%' AND jenjang = ?";
    $stmtEmployee = $conn->prepare($sqlEmployee);
    $stmtEmployee->bind_param("s", $jenjang_filter);
} else {
    $sqlEmployee = "SELECT COUNT(*) AS employee_count FROM anggota_sekolah WHERE LOWER(job_title) NOT LIKE '%guru%'";
    $stmtEmployee = $conn->prepare($sqlEmployee);
}
$stmtEmployee->execute();
$resultEmployee = $stmtEmployee->get_result();
$employee_count = $resultEmployee->fetch_assoc()['employee_count'] ?? 0;
$stmtEmployee->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Keuangan - Payroll System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <!-- Asset CSS lokal -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/dist/css/tabler.min.css" nonce="<?php echo $nonce; ?>">
  <!-- Custom CSS -->
  <style nonce="<?php echo $nonce; ?>">
    body {
      transition: background-color 0.3s, color 0.3s;
    }
    /* Dark mode */
    .dark-mode {
      background-color: #343a40;
      color: #f8f9fa;
    }
    .dark-mode .card {
      background-color: #495057;
      color: #f8f9fa;
    }
    .dark-mode .card-header {
      background: linear-gradient(45deg, #212529, #343a40);
    }
    .card-header {
      background: linear-gradient(45deg, #0d47a1, #42a5f5);
      color: white;
    }
    .table.dataTable th, .table.dataTable td {
      text-align: center;
      vertical-align: middle;
    }
    .chart-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      grid-gap: 20px;
    }
    .calendar {
      width: 100%;
      border-collapse: collapse;
    }
    .calendar th, .calendar td {
      border: 1px solid #dee2e6;
      padding: 5px;
      text-align: center;
    }
    .calendar th {
      background-color: #f8f9fc;
    }
    .calendar .today {
      background-color: #42a5f5;
      color: #fff;
      font-weight: bold;
    }
    .clock {
      font-size: 1.5rem;
      text-align: center;
      margin-bottom: 10px;
    }
    .breadcrumb {
      background-color: transparent;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body id="page-top">
  <!-- Memanggil Navbar Tabler -->
  <?php include __DIR__ . '/../../navbar_tabler.php'; ?>

  <!-- Container Utama -->
  <div class="page-wrapper">
    <div class="page-body">
      <div class="container-xl">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="my-3">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Dashboard Keuangan</li>
          </ol>
        </nav>
        <!-- Judul Halaman -->
        <h1 class="h3 mb-4 text-gray-800"><i class="bi bi-wallet-fill"></i> Dashboard Keuangan</h1>

        <!-- Filter Payroll -->
        <form method="GET" class="mb-4">
          <div class="row g-3 align-items-end">
            <div class="col-md-3">
              <label for="bulan" class="form-label">Bulan</label>
              <select id="bulan" name="bulan" class="form-select" required>
                <?php foreach($namaBulan as $num => $name): ?>
                  <option value="<?= htmlspecialchars($num) ?>" <?= ($bulan === $num) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($name) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label for="tahun" class="form-label">Tahun</label>
              <select id="tahun" name="tahun" class="form-select" required>
                <?php for($y = date("Y"); $y >= 2000; $y--): ?>
                  <option value="<?= htmlspecialchars($y) ?>" <?= ($tahun === $y) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($y) ?>
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
              <a href="?bulan=<?= date('n') ?>&tahun=<?= date('Y') ?>" class="btn btn-outline-info w-100">
                Bulan Ini
              </a>
            </div>
            <div class="col-md-2">
              <a href="?tahun=<?= date('Y') ?>" class="btn btn-outline-info w-100">
                Tahun Ini
              </a>
            </div>
          </div>
        </form>

        <!-- Cards Ringkasan -->
        <div class="row mb-4">
          <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
              <div class="card-body">
                <div class="row align-items-center">
                  <div class="col">
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
          <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
              <div class="card-body">
                <div class="row align-items-center">
                  <div class="col">
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
          <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2" data-bs-toggle="popover" data-bs-trigger="hover" data-bs-html="true" 
                 title="Detail Anggota Sekolah" 
                 data-bs-content="Guru: <?= $guruAll ?>, Karyawan: <?= $karyawanAll ?>">
              <div class="card-body">
                <div class="row align-items-center">
                  <div class="col">
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

        <!-- Grid untuk Chart & Informasi -->
        <div class="chart-grid mb-4">
          <!-- Grafik Tren Gaji (Bar + Line Chart) -->
          <div class="card shadow">
            <div class="card-header py-3">
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
            <div class="card-header d-flex justify-content-between align-items-center py-3">
              <h6 class="m-0 font-weight-bold text-white">
                <i class="fas fa-chart-pie"></i> Perbandingan Guru vs Karyawan
              </h6>
              <!-- Filter Jenjang -->
              <form method="GET" id="filterJenjangForm" class="form-inline">
                <input type="hidden" name="bulan" value="<?= $bulan ?>">
                <input type="hidden" name="tahun" value="<?= $tahun ?>">
                <select name="jenjang_filter" class="form-control form-control-sm" onchange="document.getElementById('filterJenjangForm').submit()">
                  <option value="all" <?= ($jenjang_filter === 'all') ? 'selected' : '' ?>>Semua Jenjang</option>
                  <?php foreach($jenjangOptions as $jenjangOpt): ?>
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
            <div class="card-header py-3">
              <h6 class="m-0 font-weight-bold text-white">
                <i class="fas fa-calendar-alt"></i> Live Calendar & Clock
              </h6>
            </div>
            <div class="card-body">
              <div class="clock" id="digitalClock"><i class="fas fa-clock"></i></div>
              <div id="calendarContainer"></div>
            </div>
          </div>
        </div>

        <!-- Tabel Data Payroll -->
        <div class="card shadow mb-4">
          <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-white">Detail Data Payroll</h6>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-bordered table-hover display nowrap" id="dataPayrollTable" width="100%" cellspacing="0">
                <thead class="table-light">
                  <tr>
                    <th>Nama</th>
                    <th>Jenjang</th>
                    <th>Gaji Pokok (Rp)</th>
                    <th>Gaji Bersih (Rp)</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while($row = $resultPayroll->fetch_assoc()): ?>
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

        <!-- Tombol Export -->
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

      </div><!-- /.container-xl -->
    </div><!-- /.page-body -->

    <!-- Footer -->
    <footer class="sticky-footer bg-white">
      <div class="container my-auto">
        <div class="copyright text-center my-auto">
          <span>&copy; <?= date("Y") ?> Payroll Management System | Developed By [Nama Anda]</span>
        </div>
      </div>
    </footer>
  </div><!-- /.page-wrapper -->

  <!-- Help Modal -->
  <div class="modal fade" id="helpModal" tabindex="-1" role="dialog" aria-labelledby="helpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
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
            <li>Anda dapat beralih antara mode Dark dan Light menggunakan tombol di pojok kanan atas.</li>
          </ul>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Tutup</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Loading Spinner (opsional) -->
  <div id="loadingSpinner" style="display: none; position: fixed; z-index: 9999; height: 100px; width: 100px; margin: auto; top: 0; left: 0; bottom: 0; right: 0;">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
  </div>

  <!-- JS Dependencies (asset lokal dari /dist) -->
  <script src="<?= BASE_URL ?>/dist/libs/jquery/jquery.min.js" nonce="<?php echo $nonce; ?>"></script>
  <script src="<?= BASE_URL ?>/dist/libs/bootstrap/dist/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>
  <script src="<?= BASE_URL ?>/dist/libs/datatables/datatables.min.js" nonce="<?php echo $nonce; ?>"></script>
  <script src="<?= BASE_URL ?>/dist/libs/chart.js/chart.min.js" nonce="<?php echo $nonce; ?>"></script>

  <!-- Inisialisasi Script -->
  <script nonce="<?php echo $nonce; ?>">
    $(document).ready(function() {
      // Inisialisasi DataTables
      $('#dataPayrollTable').DataTable({
        responsive: true,
        language: {
          url: "<?= BASE_URL ?>/dist/libs/datatables/i18n/Indonesian.json"
        }
      });

      // Inisialisasi Popover dan Tooltip (Bootstrap 5)
      var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
      var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
      });
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });

      // Grafik Tren Gaji menggunakan Chart.js
      var ctxTrend = document.getElementById('gajiTrendChart').getContext('2d');
      var gajiTrendChart = new Chart(ctxTrend, {
        type: 'bar',
        data: {
          labels: <?= json_encode($bulanGrafik) ?>,
          datasets: [{
            label: 'Gaji Pokok',
            data: <?= json_encode($gajiBulananPokok) ?>,
            backgroundColor: 'rgba(75, 192, 192, 0.6)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
          },
          {
            label: 'Gaji Bersih',
            data: <?= json_encode($gajiBulananBersih) ?>,
            type: 'line',
            fill: false,
            borderColor: 'rgba(255, 159, 64, 1)',
            backgroundColor: 'rgba(255, 159, 64, 0.6)',
            borderWidth: 2
          }]
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
                  var label = context.dataset.label || '';
                  if (label) { label += ': '; }
                  if (context.parsed.y !== null) {
                    label += 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                  }
                  return label;
                }
              }
            }
          }
        }
      });

      // Grafik Perbandingan Guru vs Karyawan (Pie Chart)
      var ctxComparison = document.getElementById('rekapJenjangChart').getContext('2d');
      var comparisonChart = new Chart(ctxComparison, {
        type: 'pie',
        data: {
          labels: ['Guru', 'Karyawan'],
          datasets: [{
            data: [<?= $teacher_count ?>, <?= $employee_count ?>],
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
                  var total = dataset.data.reduce((prev, curr) => prev + curr);
                  var currentValue = dataset.data[context.dataIndex];
                  var percentage = Math.floor(((currentValue / total) * 100) + 0.5);
                  return context.label + ": " + percentage + "% (" + currentValue + ")";
                }
              }
            }
          }
        }
      });

      // Fungsi Export Excel
      $('#exportExcel').click(function() {
        var url = '<?= BASE_URL ?>/payroll/keuangan/export_rekap_gaji.php?format=excel&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&jenjang_filter=<?= urlencode($jenjang_filter) ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>';
        window.location.href = url;
      });

      // Fungsi Export PDF
      $('#exportPDF').click(function() {
        var url = '<?= BASE_URL ?>/payroll/keuangan/export_rekap_gaji.php?format=pdf&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&jenjang_filter=<?= urlencode($jenjang_filter) ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>';
        window.location.href = url;
      });

      // Fungsi Print Tabel Data
      $('#printTable').click(function() {
        var divToPrint = document.getElementById("dataPayrollTable");
        var newWin = window.open("");
        newWin.document.write("<html><head><title>Print Data Payroll</title>");
        newWin.document.write("<link rel='stylesheet' href='<?= BASE_URL ?>/dist/css/tabler.min.css'>");
        newWin.document.write("</head><body>");
        newWin.document.write(divToPrint.outerHTML);
        newWin.document.write("</body></html>");
        newWin.print();
        newWin.close();
      });

      // Live Clock & Calendar
      function updateClock() {
        var now = new Date();
        var options = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Asia/Jakarta' };
        var timeString = new Intl.DateTimeFormat('id-ID', options).format(now);
        $('#digitalClock').text(timeString);
      }
      setInterval(updateClock, 1000);
      updateClock();

      function buildCalendar() {
        var today = new Date();
        var currentYear = today.getFullYear();
        var currentMonth = today.getMonth();
        var currentDate = today.getDate();
        var monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        var dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        var calendarHtml = '<h5 class="text-center mb-2">' + monthNames[currentMonth] + ' ' + currentYear + '</h5>';
        calendarHtml += '<table class="calendar"><thead><tr>';
        for (var i = 0; i < dayNames.length; i++) {
          calendarHtml += '<th>' + dayNames[i] + '</th>';
        }
        calendarHtml += '</tr></thead><tbody>';
        var firstDay = new Date(currentYear, currentMonth, 1).getDay();
        var daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        var day = 1;
        for (var i = 0; i < 6; i++) {
          calendarHtml += '<tr>';
          for (var j = 0; j < 7; j++) {
            if (i === 0 && j < firstDay) {
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

      // Dark/Light Mode Toggle
      $('#modeToggle').click(function() {
        $('body').toggleClass('dark-mode');
        $(this).find('i').toggleClass('fa-moon fa-sun');
      });

      // Help Modal
      $('#helpButton').click(function() {
        $('#helpModal').modal('show');
      });
    });
  </script>
</body>
</html>
