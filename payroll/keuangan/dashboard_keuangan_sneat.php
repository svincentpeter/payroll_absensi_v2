<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['keuangan', 'superadmin'])) {
    header("Location: /payroll_absensi_v2/login.php");
    exit();
}

require_once __DIR__ . '/../../koneksi.php';

// -------------------------------------
// Bagian PROSES DATA / QUERY
// -------------------------------------
$bulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : date('n');
$tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');

// Validasi
if ($bulan < 1 || $bulan > 12) {
    $bulan = date('n');
}
if ($tahun < 2000 || $tahun > date('Y')) {
    $tahun = date('Y');
}

// Nama bulan
$namaBulan = [
    1 => 'Januari',   2 => 'Februari',  3 => 'Maret',   4 => 'April',
    5 => 'Mei',       6 => 'Juni',      7 => 'Juli',    8 => 'Agustus',
    9 => 'September',10 => 'Oktober', 11 => 'November',12 => 'Desember'
];

// (1) Total Gaji Pokok
$sqlTotalGaji = "SELECT SUM(gaji_pokok) AS total_gaji_pokok FROM payroll WHERE bulan = ? AND tahun = ?";
$stmt = $conn->prepare($sqlTotalGaji);
$stmt->bind_param("ii", $bulan, $tahun);
$stmt->execute();
$res = $stmt->get_result();
$totalGajiPokok = $res->fetch_assoc()['total_gaji_pokok'] ?? 0;
$stmt->close();

// (2) Total Gaji Bersih
$sqlTotalDiterima = "SELECT SUM(gaji_bersih) AS total_diterima FROM payroll WHERE bulan = ? AND tahun = ?";
$stmt = $conn->prepare($sqlTotalDiterima);
$stmt->bind_param("ii", $bulan, $tahun);
$stmt->execute();
$res = $stmt->get_result();
$totalDiterima = $res->fetch_assoc()['total_diterima'] ?? 0;
$stmt->close();

// (3) Data grafik Tren Gaji per bulan
$sqlGajiBulanan = "
    SELECT p.bulan,
           SUM(p.gaji_pokok) AS total_gaji_pokok,
           SUM(p.gaji_bersih) AS total_gaji_bersih
    FROM payroll p
    WHERE p.tahun = ?
    GROUP BY p.bulan
    ORDER BY p.bulan ASC
";
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

// (4) Perbandingan Guru vs Karyawan
$jenjang_filter = $_GET['jenjang_filter'] ?? 'all';

// Ambil daftar jenjang
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
                   WHERE LOWER(job_title) LIKE '%guru%' AND jenjang = ?";
    $stmt = $conn->prepare($sqlTeacher);
    $stmt->bind_param("s", $jenjang_filter);
} else {
    $sqlTeacher = "SELECT COUNT(*) AS teacher_count 
                   FROM anggota_sekolah
                   WHERE LOWER(job_title) LIKE '%guru%'";
    $stmt = $conn->prepare($sqlTeacher);
}
$stmt->execute();
$res = $stmt->get_result();
$teacher_count = $res->fetch_assoc()['teacher_count'] ?? 0;
$stmt->close();

// Hitung jumlah karyawan
if ($jenjang_filter !== 'all') {
    $sqlEmployee = "SELECT COUNT(*) AS employee_count
                    FROM anggota_sekolah
                    WHERE LOWER(job_title) NOT LIKE '%guru%' AND jenjang = ?";
    $stmt = $conn->prepare($sqlEmployee);
    $stmt->bind_param("s", $jenjang_filter);
} else {
    $sqlEmployee = "SELECT COUNT(*) AS employee_count
                    FROM anggota_sekolah
                    WHERE LOWER(job_title) NOT LIKE '%guru%'";
    $stmt = $conn->prepare($sqlEmployee);
}
$stmt->execute();
$res = $stmt->get_result();
$employee_count = $res->fetch_assoc()['employee_count'] ?? 0;
$stmt->close();

// (5) Total Anggota
$sqlTotalAnggota = "SELECT COUNT(*) as total_anggota FROM anggota_sekolah";
$resTotal = $conn->query($sqlTotalAnggota);
$totalAnggota = $resTotal ? $resTotal->fetch_assoc()['total_anggota'] ?? 0 : 0;
if ($resTotal) $resTotal->close();

// Guru & Karyawan total
$sqlGuruAll = "SELECT COUNT(*) as guru_count FROM anggota_sekolah WHERE LOWER(job_title) LIKE '%guru%'";
$resGuruAll = $conn->query($sqlGuruAll);
$guruAll = $resGuruAll ? $resGuruAll->fetch_assoc()['guru_count'] ?? 0 : 0;
if ($resGuruAll) $resGuruAll->close();

$sqlKaryawanAll = "SELECT COUNT(*) as karyawan_count FROM anggota_sekolah WHERE LOWER(job_title) NOT LIKE '%guru%'";
$resKaryawanAll = $conn->query($sqlKaryawanAll);
$karyawanAll = $resKaryawanAll ? $resKaryawanAll->fetch_assoc()['karyawan_count'] ?? 0 : 0;
if ($resKaryawanAll) $resKaryawanAll->close();

// (6) Query data payroll (untuk tabel detail)
$sqlPayroll = "
    SELECT p.*, a.nama, a.jenjang, si.level
    FROM payroll p
    LEFT JOIN anggota_sekolah a ON p.id_anggota = a.id
    LEFT JOIN salary_indices si ON a.salary_index_id = si.id
    WHERE p.bulan = ? AND p.tahun = ?
";
?>
<!DOCTYPE html>
<html lang="id" class="light-style layout-menu-fixed" dir="ltr"
      data-theme="theme-default"
      data-assets-path="../../assets/"
      data-template="vertical-menu-template-free"
      data-style="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"/>
  <title>Dashboard Keuangan - Payroll System</title>

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../../assets/img/favicon/favicon.ico" />

  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" />

  <!-- Core CSS (Sneat) -->
  <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../../assets/vendor/css/demo.css" />

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

  <!-- DataTables CSS -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css">

  <!-- Helpers -->
  <script src="../../assets/vendor/js/helpers.js"></script>
  <!-- Config -->
  <script src="../../assets/vendor/js/config.js"></script>


</head>

<body>
  <!-- Layout wrapper -->
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">

      <!-- SIDEBAR -->
      <?php include __DIR__ . '/../../sidebar_sneat.php'; ?>
      <!-- /SIDEBAR -->

      <!-- Layout page -->
      <div class="layout-page">

        <!-- NAVBAR -->
        <?php include __DIR__ . '/../../navbar_sneat.php'; ?>
        <!-- /NAVBAR -->

        <!-- Content wrapper -->
        <div class="content-wrapper">
          <!-- Content -->
          <div class="container-xxl flex-grow-1 container-p-y">
            
            <!-- Judul & Breadcrumb -->
            <div class="row mb-3">
              <div class="col-12">
                <h4 class="fw-bold py-3 mb-4">
                  <span class="text-muted fw-light">Dashboard /</span> Keuangan
                </h4>
              </div>
            </div>

            <!-- (A) Filter Payroll -->
            <div class="row mb-4">
              <div class="col-12">
                <div class="card">
                  <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                      <div class="col-md-3">
                        <label for="bulan" class="form-label">Bulan</label>
                        <select id="bulan" name="bulan" class="form-select" required>
                          <?php foreach($namaBulan as $num => $name): ?>
                          <option value="<?= $num ?>" <?= ($bulan === $num) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($name) ?>
                          </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="col-md-3">
                        <label for="tahun" class="form-label">Tahun</label>
                        <select id="tahun" name="tahun" class="form-select" required>
                          <?php for($y = date("Y"); $y >= 2000; $y--): ?>
                          <option value="<?= $y ?>" <?= ($tahun === $y) ? 'selected' : '' ?>>
                            <?= $y ?>
                          </option>
                          <?php endfor; ?>
                        </select>
                      </div>
                      <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                          <i class="bx bx-search"></i> Cari
                        </button>
                      </div>
                      <div class="col-md-2">
                        <a href="?bulan=<?= date('n') ?>&tahun=<?= date('Y') ?>"
                           class="btn btn-outline-secondary w-100">
                          Bulan Ini
                        </a>
                      </div>
                      <div class="col-md-2">
                        <a href="?tahun=<?= date('Y') ?>" class="btn btn-outline-secondary w-100">
                          Tahun Ini
                        </a>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>

            <!-- (B) Cards ringkasan -->
            <div class="row">
              <div class="col-sm-6 col-md-4 mb-4">
                <div class="card h-100">
                  <div class="card-body">
                    <div class="d-flex align-items-center">
                      <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-success">
                          <i class="bx bx-money fs-4"></i>
                        </span>
                      </div>
                      <div>
                        <h6 class="mb-1">Total Gaji Pokok</h6>
                        <h5 class="mb-0">
                          Rp <?= number_format($totalGajiPokok, 2, ',', '.') ?>
                        </h5>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 col-md-4 mb-4">
                <div class="card h-100">
                  <div class="card-body">
                    <div class="d-flex align-items-center">
                      <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-info">
                          <i class="bx bx-hand-holding-usd fs-4"></i>
                        </span>
                      </div>
                      <div>
                        <h6 class="mb-1">Total Yang Diterima</h6>
                        <h5 class="mb-0">
                          Rp <?= number_format($totalDiterima, 2, ',', '.') ?>
                        </h5>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 col-md-4 mb-4">
                <div class="card h-100"
                     data-bs-toggle="popover" data-bs-trigger="hover" data-bs-html="true"
                     title="Detail Anggota Sekolah"
                     data-bs-content="Guru: <?= $guruAll ?>, Karyawan: <?= $karyawanAll ?>">
                  <div class="card-body">
                    <div class="d-flex align-items-center">
                      <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-warning">
                          <i class="bx bx-user fs-4"></i>
                        </span>
                      </div>
                      <div>
                        <h6 class="mb-1">Total Anggota Sekolah</h6>
                        <h5 class="mb-0">
                          <?= number_format($totalAnggota) ?>
                        </h5>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- (C) Row untuk 3 item: Tren Gaji, Perbandingan Guru vs Karyawan, dan Calendar -->
<div class="row">
  <!-- 1) Tren Gaji -->
  <div class="col-lg-4 col-md-12 mb-4">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="m-0">Tren Gaji Tahun <?= htmlspecialchars($tahun) ?></h5>
      </div>
      <div class="card-body">
        <canvas id="gajiTrendChart" height="180"></canvas>
      </div>
    </div>
  </div>

  <!-- 2) Perbandingan Guru vs Karyawan -->
  <div class="col-lg-4 col-md-12 mb-4">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="m-0">Perbandingan Guru vs Karyawan</h5>
        <form method="GET" id="filterJenjangForm" class="d-inline">
          <!-- Hidden agar filter bulan/tahun tidak hilang -->
          <input type="hidden" name="bulan" value="<?= $bulan ?>">
          <input type="hidden" name="tahun" value="<?= $tahun ?>">
          <select name="jenjang_filter" class="form-select form-select-sm"
            onchange="document.getElementById('filterJenjangForm').submit()">
            <option value="all" <?= ($jenjang_filter === 'all') ? 'selected' : '' ?>>Semua Jenjang</option>
            <?php foreach($jenjangOptions as $jenjangOpt): ?>
              <option value="<?= htmlspecialchars($jenjangOpt) ?>"
                <?= ($jenjang_filter === $jenjangOpt) ? 'selected' : '' ?>>
                <?= htmlspecialchars($jenjangOpt) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      <div class="card-body">
        <canvas id="rekapJenjangChart" height="180"></canvas>
      </div>
    </div>
  </div>

  <!-- 3) Live Calendar & Clock -->
  <div class="col-lg-4 col-md-12 mb-4">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="m-0">Live Calendar &amp; Clock</h5>
      </div>
      <div class="card-body">
        <div class="clock mb-3" id="digitalClock">
          <i class="bx bx-time"></i>
        </div>
        <div id="calendarContainer"></div>
      </div>
    </div>
  </div>
</div>
<!-- /Row 3 kolom -->


            <!-- (E) Tabel Data Payroll -->
            <div class="card mb-4">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="m-0">Detail Data Payroll</h5>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-hover table-bordered" id="dataPayrollTable" width="100%">
                    <thead>
                      <tr>
                        <th>Nama</th>
                        <th>Jenjang</th>
                        <th>Gaji Pokok (Rp)</th>
                        <th>Gaji Bersih (Rp)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      // Ambil data payroll detail
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
                      <?php endwhile; $stmtShow->close(); ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- (F) Tombol Export & Print -->
            <div class="mb-4">
              <button id="exportExcel" class="btn btn-success me-2">
                <i class="bx bx-file"></i> Export Excel
              </button>
              <button id="exportPDF" class="btn btn-danger me-2">
                <i class="bx bx-file"></i> Export PDF
              </button>
              <button id="printTable" class="btn btn-secondary">
                <i class="bx bx-printer"></i> Print
              </button>
            </div>

          </div>
          <!-- /Content -->

          <!-- FOOTER -->
          <footer class="content-footer footer bg-footer-theme">
            <div class="container-xxl">
              <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
                <div class="mb-2 mb-md-0">
                  © <?= date("Y") ?> Payroll Management System | Developed by [Nama Anda]
                </div>
                <div>
                  <a href="#" class="footer-link me-4">License</a>
                  <a href="#" class="footer-link me-4">More Themes</a>
                  <a href="#" class="footer-link me-4">Documentation</a>
                  <a href="#" class="footer-link">Support</a>
                </div>
              </div>
            </div>
          </footer>
          <!-- /FOOTER -->

          <div class="content-backdrop fade"></div>
        </div>
        <!-- /Content wrapper -->
      </div>
      <!-- /Layout page -->

    </div>
    <!-- /Layout container -->

    <div class="layout-overlay layout-menu-toggle"></div>
  </div>
  <!-- /Layout wrapper -->

  <!-- Core JS -->
  <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../../assets/vendor/libs/popper/popper.js"></script>
  <script src="../../assets/vendor/js/bootstrap.js"></script>
  <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../../assets/vendor/js/menu.js"></script>

  <!-- DataTables JS -->
  <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>

  <!-- Main JS -->
  <script src="../../assets/vendor/js/main.js"></script>

  <script>
  $(document).ready(function() {
    // Inisialisasi DataTables
    $('#dataPayrollTable').DataTable({
      language: {
        url: "https://cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
      },
      responsive: true
    });

    // Aktifkan popover
    $('[data-bs-toggle="popover"]').popover();

    // Chart Tren Gaji
    var ctxTrend = document.getElementById('gajiTrendChart').getContext('2d');
    var gajiTrendChart = new Chart(ctxTrend, {
      type: 'bar',
      data: {
        labels: <?= json_encode($bulanGrafik, JSON_UNESCAPED_SLASHES); ?>,
        datasets: [
          {
            label: 'Gaji Pokok',
            data: <?= json_encode($gajiBulananPokok, JSON_UNESCAPED_SLASHES); ?>,
            backgroundColor: 'rgba(75,192,192,0.6)',
            borderColor: 'rgba(75,192,192,1)',
            borderWidth: 1
          },
          {
            label: 'Gaji Bersih',
            data: <?= json_encode($gajiBulananBersih, JSON_UNESCAPED_SLASHES); ?>,
            type: 'line',
            fill: false,
            borderColor: 'rgba(255,159,64,1)',
            backgroundColor: 'rgba(255,159,64,0.6)',
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
                return context.dataset.label + ": Rp " + 
                       context.parsed.y.toLocaleString('id-ID');
              }
            }
          }
        }
      }
    });

    // Chart Perbandingan Guru vs Karyawan (Pie)
    var ctxPerbandingan = document.getElementById('rekapJenjangChart').getContext('2d');
    var rekapJenjangChart = new Chart(ctxPerbandingan, {
      type: 'pie',
      data: {
        labels: ['Guru', 'Karyawan'],
        datasets: [{
          data: [<?= $teacher_count ?>, <?= $employee_count ?>],
          backgroundColor: ['#42A5F5','#FFA726']
        }]
      },
      options: { responsive: true }
    });

    // Export Excel
    $('#exportExcel').click(function() {
      window.location.href = 
        '/payroll_absensi_v2/payroll/keuangan/export_rekap_gaji.php?format=excel&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&jenjang_filter=<?= urlencode($jenjang_filter) ?>';
    });

    // Export PDF
    $('#exportPDF').click(function() {
      window.location.href = 
        '/payroll_absensi_v2/payroll/keuangan/export_rekap_gaji.php?format=pdf&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&jenjang_filter=<?= urlencode($jenjang_filter) ?>';
    });

    // Print Tabel
    $('#printTable').click(function() {
      var divToPrint = document.getElementById("dataPayrollTable");
      var newWin = window.open("");
      newWin.document.write("<html><head><title>Print Data Payroll</title>");
      newWin.document.write("<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css'>");
      newWin.document.write("</head><body>");
      newWin.document.write(divToPrint.outerHTML);
      newWin.document.write("</body></html>");
      newWin.print();
      newWin.close();
    });

    // Live Clock
    function updateClock() {
      var now = new Date();
      var options = { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:false, timeZone:'Asia/Jakarta' };
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
      var monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus',
                        'September','Oktober','November','Desember'];
      var dayNames = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];

      var calendarHtml = '<h5 class="text-center mb-2">' 
                         + monthNames[currentMonth] + ' ' + currentYear + '</h5>';
      calendarHtml += '<table class="table table-bordered"><thead><tr>';
      dayNames.forEach(function(day) {
        calendarHtml += '<th class="text-center">' + day + '</th>';
      });
      calendarHtml += '</tr></thead><tbody>';

      var firstDay = new Date(currentYear, currentMonth, 1).getDay();
      var daysInMonth = new Date(currentYear, currentMonth+1, 0).getDate();
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
              calendarHtml += '<td class="bg-primary text-white fw-bold text-center">' + day + '</td>';
            } else {
              calendarHtml += '<td class="text-center">' + day + '</td>';
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
