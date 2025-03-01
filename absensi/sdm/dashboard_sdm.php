<?php
// File: /payroll_absensi_v2/absensi/sdm/dashboard_sdm.php

// =========================
// 1. Inisialisasi Session, Keamanan, & Koneksi Database
// =========================
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
require_once __DIR__ . '/../../koneksi.php';

// Otorisasi pengguna (hanya role sdm & superadmin)
authorize(['sdm', 'superadmin'], '/payroll_absensi_v2/login.php');

// Pastikan CSRF token telah di-generate
generate_csrf_token();

// =========================
// 2. Tangani Permintaan AJAX
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Verifikasi CSRF Token
    if (!isset($_POST['csrf_token'])) {
        send_response(403, 'Token CSRF tidak ditemukan.');
    }
    verify_csrf_token($_POST['csrf_token']);

    // Otorisasi ulang (opsional)
    authorize(['sdm', 'superadmin'], '/payroll_absensi_v2/login.php');

    $action = $_POST['action'];

    if ($action === 'get_payroll_dashboard') {
        // ================
        // Ambil data pie chart Jumlah Anggota per Jenjang
        // ================
        $query = "SELECT jenjang, COUNT(*) as total,
                         SUM(CASE WHEN role='P'  THEN 1 ELSE 0 END) as P,
                         SUM(CASE WHEN role='TK' THEN 1 ELSE 0 END) as TK,
                         SUM(CASE WHEN role='M'  THEN 1 ELSE 0 END) as M
                  FROM anggota_sekolah
                  GROUP BY jenjang";
        $result = $conn->query($query);
        $detailData = [];
        $chartLabels = [];
        $chartData   = [];
        $chartColors = [];
        while ($row = $result->fetch_assoc()) {
            $detailData[] = $row;
            $chartLabels[] = $row['jenjang'];
            $chartData[]   = intval($row['total']);
            // Warna chart per jenjang
            if ($row['jenjang'] === 'SD') {
                $chartColors[] = '#28a745'; // Hijau
            } elseif ($row['jenjang'] === 'SMP') {
                $chartColors[] = '#ffc107'; // Kuning
            } elseif ($row['jenjang'] === 'SMA') {
                $chartColors[] = '#17a2b8'; // Biru
            } else {
                $chartColors[] = '#6c757d'; // Abu-abu
            }
        }
        send_response(0, [
            'chartData' => [
                'labels' => $chartLabels,
                'data'   => $chartData,
                'colors' => $chartColors
            ],
            'detailData' => $detailData
        ]);

    } elseif ($action === 'get_upcoming_holidays') {
        // ================
        // Ambil 5 hari libur terdekat
        // ================
        $today = date('Y-m-d');
        $query = "SELECT holiday_title, holiday_desc, holiday_date, holiday_type 
                  FROM holidays 
                  WHERE holiday_date >= ? 
                  ORDER BY holiday_date ASC LIMIT 5";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $holidays = [];
        while ($row = $result->fetch_assoc()) {
            $holidays[] = $row;
        }
        $stmt->close();
        send_response(0, $holidays);

    } elseif ($action === 'get_unpaid_summary') {
        // ================
        // Hitung berapa anggota (per jenjang) yang BELUM di payroll final
        // ================
        $bulan = isset($_POST['bulan']) ? intval($_POST['bulan']) : date('n');
        $tahun = isset($_POST['tahun']) ? intval($_POST['tahun']) : date('Y');

        $sql = "SELECT COALESCE(jenjang, 'Lainnya') AS jenjang,
                       COUNT(a.id) AS total_unpaid
                FROM anggota_sekolah a
                WHERE a.id NOT IN (
                    SELECT p.id_anggota 
                    FROM payroll p 
                    WHERE p.bulan = ? AND p.tahun = ? AND p.status = 'final'
                )
                GROUP BY a.jenjang";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            send_response(1, 'Gagal menyiapkan query unpaid summary: ' . $conn->error);
        }
        $stmt->bind_param("ii", $bulan, $tahun);
        $stmt->execute();
        $res = $stmt->get_result();

        $unpaidData = [];
        while ($row = $res->fetch_assoc()) {
            $unpaidData[] = [
                'jenjang' => $row['jenjang'] ?: 'Lainnya',
                'total'   => intval($row['total_unpaid'])
            ];
        }
        $stmt->close();
        send_response(0, $unpaidData);

    } else {
        send_response(404, 'Aksi tidak dikenali.');
    }
    exit();
}

// =========================
// 3. Ambil Data Penting (KPI, dsb.)
// =========================

// 3a. Total Anggota Sekolah
$sqlTotalAnggota = "SELECT COUNT(*) AS total_anggota FROM anggota_sekolah";
$resTotal = $conn->query($sqlTotalAnggota);
$totalAnggota = 0;
if ($resTotal) {
    $row = $resTotal->fetch_assoc();
    $totalAnggota = $row['total_anggota'] ?? 0;
    $resTotal->close();
}

// 3b. Detail guru per jenjang (untuk popover)
$sqlHoverDetail = "SELECT jenjang, COUNT(*) AS totalGuru
                   FROM anggota_sekolah
                   WHERE LOWER(job_title) LIKE '%guru%'
                   GROUP BY jenjang
                   ORDER BY jenjang ASC";
$resHover = $conn->query($sqlHoverDetail);
$hoverDetail = [];
if ($resHover) {
    while ($r = $resHover->fetch_assoc()) {
        $hoverDetail[] = $r;
    }
    $resHover->close();
}
$hoverHtml = '';
if (!empty($hoverDetail)) {
    $hoverHtml .= '<table class="table table-sm mb-0">';
    $hoverHtml .= '<thead><tr><th>Jenjang</th><th>Guru</th></tr></thead><tbody>';
    foreach ($hoverDetail as $d) {
        $hoverHtml .= '<tr><td>'.$d['jenjang'].'</td><td>'.$d['totalGuru'].'</td></tr>';
    }
    $hoverHtml .= '</tbody></table>';
} else {
    $hoverHtml .= 'Belum ada data guru';
}

// =========================
// 4. Render Halaman
// =========================
$nonce = bin2hex(random_bytes(8));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard SDM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5 & SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" nonce="<?= $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" nonce="<?= $nonce; ?>">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" nonce="<?= $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" nonce="<?= $nonce; ?>">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js" nonce="<?= $nonce; ?>"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" nonce="<?= $nonce; ?>"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" nonce="<?= $nonce; ?>"></script>

    <style nonce="<?= $nonce; ?>">
        .chart-container { position: relative; width: 100%; height: 370px; }
        .card-body { overflow: hidden; }

        /* Calendar & Clock */
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
        .border-left-info {
            border-left: .25rem solid #36b9cc !important;
        }
        .border-left-danger {
            border-left: .25rem solid #e74a3b !important;
        }
        /* Simple loading spinner */
        #loadingSpinner {
            display: none;
            position: fixed; 
            z-index: 9999; 
            height: 100px; 
            width: 100px; 
            top: 50%; 
            left: 50%; 
            transform: translate(-50%, -50%);
        }
    </style>
</head>
<body id="page-top">
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
                <!-- End Topbar -->

                <!-- Breadcrumb (optional) -->
                <?php include __DIR__ . '/../../breadcrumb.php'; ?>

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">
                        <i class="fas fa-users me-2"></i>Dashboard SDM
                    </h1>

                    <!-- Filter Periode (opsional) -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="bi bi-filter"></i> Filter Periode
                        </div>
                        <div class="card-body">
                            <div class="row align-items-end g-3">
                                <div class="col-md-3">
                                    <label for="filterBulan" class="form-label">Bulan</label>
                                    <select id="filterBulan" class="form-select">
                                        <?php
                                        $bulanNow = date('n');
                                        for ($m = 1; $m <= 12; $m++) {
                                            $selected = ($m == $bulanNow) ? 'selected' : '';
                                            echo "<option value='$m' $selected>$m</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="filterTahun" class="form-label">Tahun</label>
                                    <select id="filterTahun" class="form-select">
                                        <?php
                                        $yearNow = date('Y');
                                        for ($y = $yearNow - 3; $y <= $yearNow + 3; $y++) {
                                            $sel = ($y == $yearNow) ? 'selected' : '';
                                            echo "<option value='$y' $sel>$y</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mt-2">
                                    <button id="btnApplyFilter" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i> Terapkan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row Pertama (3 Grid) -->
                    <div class="row mb-4">
                        <!-- Grid 1: Total Anggota -->
                        <div class="col-lg-4 mb-4">
                            <div class="card border-left-info shadow h-100 py-2"
                                 data-bs-toggle="popover"
                                 data-bs-trigger="hover"
                                 data-bs-html="true"
                                 title="Detail Guru per Jenjang"
                                 data-bs-content="<?= htmlspecialchars($hoverHtml, ENT_QUOTES); ?>">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col me-2 text-start">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                <i class="fas fa-users"></i> Total Anggota Sekolah
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?= number_format($totalAnggota); ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                    <small class="text-muted mt-1 d-block">
                                        (Hover di kartu ini untuk melihat detail guru per jenjang)
                                    </small>
                                </div>
                            </div>
                        </div>
                        <!-- Grid 2: Upcoming Holidays -->
                        <div class="col-lg-4 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header bg-success text-white">
                                    <strong><i class="fas fa-calendar-alt me-1"></i>Upcoming Holidays</strong>
                                </div>
                                <div class="card-body">
                                    <div id="holidaysList"><!-- Diisi via AJAX --></div>
                                </div>
                            </div>
                        </div>
                        <!-- Grid 3: Calendar & Clock -->
                        <div class="col-lg-4 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header bg-info text-white">
                                    <strong><i class="fas fa-clock me-1"></i>Live Calendar & Clock (WIB)</strong>
                                </div>
                                <div class="card-body">
                                    <!-- Clock -->
                                    <div class="clock text-center" id="digitalClock"></div>
                                    <!-- Calendar -->
                                    <div id="calendarContainer"></div>
                                </div>
                            </div>
                        </div>
                    </div><!-- End Row Pertama -->

                    <!-- Row Kedua (2 Grid) -->
                    <div class="row mb-4">
                        <!-- Grid 1: Grafik Payroll Realtime -->
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow">
                                <div class="card-header bg-primary text-white">
                                    <strong><i class="fas fa-chart-pie me-1"></i>Grafik Jumlah Anggota per Jenjang</strong>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container mb-3">
                                        <canvas id="payrollChart"></canvas>
                                    </div>
                                    <div id="payrollDetailTable" class="small"><!-- Tabel ringkasan diisi AJAX --></div>
                                </div>
                            </div>
                        </div>
                        <!-- Grid 2: Unpaid Summary -->
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow">
                                <div class="card-header bg-danger text-white">
                                    <strong><i class="fas fa-exclamation-circle me-1"></i>Anggota Belum di Payroll Final</strong>
                                    <small class="ms-1">(Sesuai Bulan/Tahun)</small>
                                </div>
                                <div class="card-body">
                                    <div id="unpaidSummaryContainer" class="mb-3"><!-- AJAX --></div>
                                    <div style="height: 300px;">
                                        <canvas id="unpaidSummaryChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- End Row Kedua -->

                </div><!-- /.container-fluid -->
            </div><!-- End #content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?= date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div><!-- End Content Wrapper -->
    </div><!-- End Wrapper -->

    <!-- Loading Spinner (opsional) -->
    <div id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>

    <script nonce="<?= $nonce; ?>">
    $(document).ready(function() {

        // Inisialisasi popover
        $('[data-bs-toggle="popover"]').popover();

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
        function showToast(msg, icon='success') {
            Toast.fire({ icon, title: msg });
        }

        let payrollChart, unpaidChart;

        // Loading spinner
        function showSpinner() { $('#loadingSpinner').show(); }
        function hideSpinner() { $('#loadingSpinner').hide(); }

        // 1) Grafik Payroll Realtime
        function fetchPayrollDashboardData() {
            showSpinner();
            $.ajax({
                url: 'dashboard_sdm.php',
                method: 'POST',
                data: { 
                    action: 'get_payroll_dashboard', 
                    csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                },
                dataType: 'json',
                success: function(resp) {
                    hideSpinner();
                    if(resp.code !== 0) {
                        showToast(resp.result, 'error');
                        return;
                    }
                    renderPayrollDashboard(resp.result);
                },
                error: function() {
                    hideSpinner();
                    showToast('Gagal memuat data payroll.', 'error');
                }
            });
        }
        function renderPayrollDashboard(data) {
            const { chartData, detailData } = data;
            const ctx = document.getElementById('payrollChart').getContext('2d');

            if(payrollChart) payrollChart.destroy();
            payrollChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: chartData.colors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' },
                        title: { display: true, text: 'Jumlah Anggota per Jenjang' }
                    }
                }
            });

            let html = '<table class="table table-bordered table-sm mt-2">';
            html += '<thead><tr><th>Jenjang</th><th>Total</th><th>P</th><th>TK</th><th>M</th></tr></thead><tbody>';
            detailData.forEach(item => {
                html += '<tr>';
                html += `<td>${item.jenjang ?? '-'}</td>`;
                html += `<td>${item.total}</td>`;
                html += `<td>${item.P ?? 0}</td>`;
                html += `<td>${item.TK ?? 0}</td>`;
                html += `<td>${item.M ?? 0}</td>`;
                html += '</tr>';
            });
            html += '</tbody></table>';
            $('#payrollDetailTable').html(html);
        }

        // 2) Upcoming Holidays
        function fetchUpcomingHolidays() {
            showSpinner();
            $.ajax({
                url: 'dashboard_sdm.php',
                method: 'POST',
                data: { 
                    action: 'get_upcoming_holidays', 
                    csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                },
                dataType: 'json',
                success: function(resp) {
                    hideSpinner();
                    if(resp.code !== 0) {
                        showToast(resp.result, 'error');
                        return;
                    }
                    renderHolidays(resp.result);
                },
                error: function() {
                    hideSpinner();
                    showToast('Gagal memuat data Holidays.', 'error');
                }
            });
        }
        function renderHolidays(holidays) {
            let html = '<ul class="list-group list-group-flush">';
            if(holidays.length === 0) {
                html += '<li class="list-group-item">Tidak ada hari libur mendatang.</li>';
            } else {
                holidays.forEach(h => {
                    html += `<li class="list-group-item">
                        <strong>${h.holiday_title}</strong> (${h.holiday_date})<br>
                        <small>${h.holiday_desc} - ${capitalize(h.holiday_type)}</small>
                    </li>`;
                });
            }
            html += '</ul>';
            $('#holidaysList').html(html);
        }
        function capitalize(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        // 3) Calendar & Clock
        function updateClock() {
            const now = new Date();
            const opt = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Asia/Jakarta' };
            $('#digitalClock').text(new Intl.DateTimeFormat('id-ID', opt).format(now));
        }
        setInterval(updateClock, 1000);
        updateClock();

        function buildCalendar() {
            const today = new Date();
            const currentYear  = today.getFullYear();
            const currentMonth = today.getMonth();
            const currentDate  = today.getDate();

            const monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            const dayNames   = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];

            let calendarHtml = `<h5 class="text-center mb-2">${monthNames[currentMonth]} ${currentYear}</h5>`;
            calendarHtml += '<table class="calendar"><thead><tr>';
            dayNames.forEach(d => { calendarHtml += `<th>${d}</th>`; });
            calendarHtml += '</tr></thead><tbody>';

            const firstDay = new Date(currentYear, currentMonth, 1).getDay();
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
            let day = 1;

            for(let row=0; row<6; row++){
                calendarHtml += '<tr>';
                for(let col=0; col<7; col++){
                    if(row===0 && col<firstDay) {
                        calendarHtml += '<td></td>';
                    } else if(day > daysInMonth){
                        calendarHtml += '<td></td>';
                    } else {
                        if(day === currentDate){
                            calendarHtml += `<td class="today">${day}</td>`;
                        } else {
                            calendarHtml += `<td>${day}</td>`;
                        }
                        day++;
                    }
                }
                calendarHtml += '</tr>';
                if(day>daysInMonth) break;
            }
            calendarHtml += '</tbody></table>';
            $('#calendarContainer').html(calendarHtml);
        }
        buildCalendar();

        // 4) Unpaid Summary
        function fetchUnpaidSummary(bulan, tahun) {
            showSpinner();
            $.ajax({
                url: 'dashboard_sdm.php',
                method: 'POST',
                data: {
                    action: 'get_unpaid_summary',
                    bulan, tahun,
                    csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                },
                dataType: 'json',
                success: function(resp) {
                    hideSpinner();
                    if(resp.code===0) {
                        renderUnpaidSummary(resp.result);
                    } else {
                        showToast(resp.result, 'error');
                    }
                },
                error: function() {
                    hideSpinner();
                    showToast('Gagal memuat data unpaid summary.', 'error');
                }
            });
        }
        function renderUnpaidSummary(data) {
            const c = $('#unpaidSummaryContainer');
            if(!data || data.length===0) {
                c.html('<div class="alert alert-info mb-2">Semua anggota sudah di payroll final untuk periode ini.</div>');
                if(unpaidChart) { 
                    unpaidChart.destroy(); 
                    unpaidChart = null; 
                }
                return;
            }
            let totalAll=0;
            let html = '<ul class="list-group mb-3">';
            data.forEach(d => {
                html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>${d.jenjang}</strong>
                            <span class="badge bg-danger">${d.total} Belum Final</span>
                         </li>`;
                totalAll += d.total;
            });
            html += '</ul>';
            html += `<p class="fw-bold">Total belum final: ${totalAll}</p>`;
            c.html(html);

            // Bar chart
            const ctx = document.getElementById('unpaidSummaryChart').getContext('2d');
            if(unpaidChart) unpaidChart.destroy();

            unpaidChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d=>d.jenjang),
                    datasets:[{
                        label:'Belum di Payroll Final',
                        data: data.map(d=>d.total),
                        backgroundColor: 'rgba(255, 99, 132, 0.6)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth:1
                    }]
                },
                options: {
                    responsive:true,
                    scales:{
                        y:{beginAtZero:true, ticks:{precision:0}}
                    }
                }
            });
        }

        // Default: Bulan & Tahun Saat Ini
        const now = new Date();
        let currentMonth = now.getMonth()+1;
        let currentYear  = now.getFullYear();

        // Load pertama
        fetchPayrollDashboardData();
        fetchUpcomingHolidays();
        fetchUnpaidSummary(currentMonth, currentYear);

        // Filter Periode
        $('#btnApplyFilter').on('click', function(){
            const selBulan = parseInt($('#filterBulan').val()) || currentMonth;
            const selTahun = parseInt($('#filterTahun').val()) || currentYear;
            // Update global?
            currentMonth = selBulan;
            currentYear  = selTahun;

            // Panggil unpaidSummary (bisa juga panggil fetchPayrollDashboardData() jika relevan)
            fetchUnpaidSummary(currentMonth, currentYear);
            showToast(`Menampilkan data untuk periode ${selBulan}-${selTahun}`, 'info');
        });
    });
    </script>
</body>
</html>
<?php
$conn->close();
?>
