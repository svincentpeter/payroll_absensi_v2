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
authorize(['M:SDM', 'M:Superadmin'], '/payroll_absensi_v2/login.php');

// Pastikan CSRF token telah di-generate
generate_csrf_token();

// =========================
// 2. Tangani Permintaan AJAX
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Verifikasi CSRF Token
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $action = $_POST['action'];

    if ($action === 'get_payroll_dashboard') {
        // ================
        // Pie Chart: Jumlah Anggota per Jenjang
        // ================
        $query = "SELECT jenjang, COUNT(*) as total,
                         SUM(CASE WHEN role='P'  THEN 1 ELSE 0 END) as P,
                         SUM(CASE WHEN role='TK' THEN 1 ELSE 0 END) as TK,
                         SUM(CASE WHEN role='M'  THEN 1 ELSE 0 END) as M
                  FROM anggota_sekolah
                  GROUP BY jenjang";
        $result = $conn->query($query);
        $detailData  = [];
        $chartLabels = [];
        $chartData   = [];
        $chartColors = [];

        while ($row = $result->fetch_assoc()) {
            $detailData[]  = $row;
            $chartLabels[] = $row['jenjang'];
            $chartData[]   = (int)$row['total'];

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
        $sql   = "SELECT holiday_title, holiday_desc, holiday_date, holiday_type
                  FROM holidays
                  WHERE holiday_date >= ?
                  ORDER BY holiday_date ASC
                  LIMIT 5";
        $stmt  = $conn->prepare($sql);
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $res = $stmt->get_result();
        $holidays = [];
        while ($row = $res->fetch_assoc()) {
            $holidays[] = $row;
        }
        $stmt->close();
        send_response(0, $holidays);

    } elseif ($action === 'get_unpaid_summary') {
        // ================
        // Hitung anggota (per jenjang) yang BELUM di payroll final
        // ================
        $bulan = isset($_POST['bulan']) ? (int)$_POST['bulan'] : date('n');
        $tahun = isset($_POST['tahun']) ? (int)$_POST['tahun'] : date('Y');

        $sql = "SELECT COALESCE(jenjang, 'Lainnya') AS jenjang,
                       COUNT(a.id) AS total_unpaid
                FROM anggota_sekolah a
                WHERE a.id NOT IN (
                    SELECT p.id_anggota
                    FROM payroll p
                    WHERE p.bulan=? AND p.tahun=? AND p.status='final'
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
                'total'   => (int)$row['total_unpaid']
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

// =========================
// 4. Render Halaman
// =========================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard SDM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5 & SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .chart-container {
            position: relative;
            width: 100%;
            height: 250px; /* agar muat di 1 kolom */
        }
        .card-body {
            overflow: hidden;
        }
        .clock {
            font-size: 1.5rem;
            text-align: center;
            margin-bottom: 10px;
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
        .border-left-info {
            border-left: .25rem solid #36b9cc !important;
        }
        .border-left-primary {
            border-left: .25rem solid #4e73df !important;
        }
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
            <!-- End of Topbar -->

            <!-- (Optional) Breadcrumb -->
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

                <!-- Row Pertama: "Total Anggota Sekolah" & "Upcoming Holidays" -->
                <div class="row mb-4">
                    <!-- Total Anggota Sekolah -->
                    <div class="col-xl-6 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2 text-start">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
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
                                <small class="text-muted d-block mt-2">
                                    Anggota mencakup guru & karyawan di semua jenjang.
                                </small>
                            </div>
                        </div>
                    </div>
                    <!-- Upcoming Holidays -->
                    <div class="col-xl-6 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2 text-start">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            <i class="fas fa-calendar-alt"></i> Upcoming Holidays
                                        </div>
                                        <!-- Daftar 5 hari libur terdekat -->
                                        <div id="holidaysList" class="mt-2">
                                            <!-- Diisi via AJAX -->
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-2">
                                    Menampilkan 5 hari libur mendatang.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row Kedua (3 kolom): Live Calendar & Clock, Belum di Payroll Final, Jumlah Anggota per Jenjang -->
                <div class="row mb-4">
                    <!-- Kolom 1: Live Calendar & Clock (WIB) -->
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header bg-info text-white">
                                <strong><i class="fas fa-clock me-1"></i>Live Calendar & Clock (WIB)</strong>
                            </div>
                            <div class="card-body">
                                <div class="clock" id="digitalClock"></div>
                                <div id="calendarContainer"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Kolom 2: Belum di Payroll Final -->
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header bg-danger text-white">
                                <strong><i class="fas fa-exclamation-circle me-1"></i>Belum di Payroll Final</strong>
                            </div>
                            <div class="card-body">
                                <div id="unpaidSummaryContainer" class="mb-3"></div>
                                <div style="height: 250px;">
                                    <canvas id="unpaidSummaryChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kolom 3: Jumlah Anggota per Jenjang (Pie Chart) -->
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white">
                                <strong><i class="fas fa-chart-pie me-1"></i> Jumlah Anggota per Jenjang</strong>
                            </div>
                            <div class="card-body">
                                <div class="chart-container mb-3">
                                    <canvas id="payrollChart"></canvas>
                                </div>
                                <div id="payrollDetailTable" class="small">
                                    <!-- Tabel ringkasan diisi AJAX -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Row Kedua -->

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
        <!-- End Footer -->
    </div><!-- End Content Wrapper -->
</div><!-- End #wrapper -->

<!-- Loading Spinner -->
<div id="loadingSpinner">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>

<script>
$(document).ready(function(){

    // Deklarasi chart global (tidak double-declare)
    let payrollChart = null;
    let unpaidChart  = null;

    function showSpinner() { $('#loadingSpinner').show(); }
    function hideSpinner() { $('#loadingSpinner').hide(); }

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

    // 1) Pie Chart: Jumlah Anggota per Jenjang
    function fetchPayrollDashboardData() {
        showSpinner();
        $.ajax({
            url: 'dashboard_sdm.php',
            type: 'POST',
            data: {
                action: 'get_payroll_dashboard',
                csrf_token: '<?= $_SESSION['csrf_token']; ?>'
            },
            dataType: 'json',
            success: function(resp) {
                hideSpinner();
                if(resp.code===0) {
                    renderPayrollDashboard(resp.result);
                } else {
                    showToast(resp.result, 'error');
                }
            },
            error: function(){
                hideSpinner();
                showToast('Gagal memuat data Pie Chart.', 'error');
            }
        });
    }
    function renderPayrollDashboard(data) {
        const { chartData, detailData } = data;
        const ctx = document.getElementById('payrollChart').getContext('2d');

        if(payrollChart) {
            payrollChart.destroy();
        }

        payrollChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: chartData.labels,
                datasets: [{
                    data: chartData.data,
                    backgroundColor: chartData.colors,
                    borderWidth:1
                }]
            },
            options: {
                responsive:true,
                plugins:{
                    legend:{ position:'bottom' }
                }
            }
        });

        let html = '<table class="table table-bordered table-sm">';
        html += '<thead><tr><th>Jenjang</th><th>Total</th><th>P</th><th>TK</th><th>M</th></tr></thead><tbody>';
        detailData.forEach(d => {
            html += `<tr>
                        <td>${d.jenjang || '-'}</td>
                        <td>${d.total}</td>
                        <td>${d.P ?? 0}</td>
                        <td>${d.TK ?? 0}</td>
                        <td>${d.M ?? 0}</td>
                     </tr>`;
        });
        html += '</tbody></table>';
        $('#payrollDetailTable').html(html);
    }

    // 2) Upcoming Holidays
    function fetchUpcomingHolidays() {
        showSpinner();
        $.ajax({
            url: 'dashboard_sdm.php',
            type: 'POST',
            data: {
                action: 'get_upcoming_holidays',
                csrf_token: '<?= $_SESSION['csrf_token']; ?>'
            },
            dataType: 'json',
            success: function(resp) {
                hideSpinner();
                if(resp.code===0) {
                    renderHolidays(resp.result);
                } else {
                    showToast(resp.result, 'error');
                }
            },
            error: function(){
                hideSpinner();
                showToast('Gagal memuat data Holidays.', 'error');
            }
        });
    }
    function renderHolidays(data) {
        let html = '';
        if(!data || data.length===0) {
            html = '<div class="text-muted">Tidak ada hari libur mendatang.</div>';
        } else {
            data.forEach(h => {
                html += `<div class="mb-1">
                    <strong>${h.holiday_title}</strong> (${h.holiday_date})<br>
                    <small class="text-muted">${h.holiday_desc}</small>
                </div>`;
            });
        }
        $('#holidaysList').html(html);
    }

    // 3) Live Calendar & Clock
    function updateClock() {
        let now = new Date();
        let opt = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Asia/Jakarta' };
        $('#digitalClock').text(new Intl.DateTimeFormat('id-ID', opt).format(now));
    }
    setInterval(updateClock, 1000);
    updateClock();

    function buildCalendar() {
        let today = new Date();
        let currentYear  = today.getFullYear();
        let currentMonth = today.getMonth();
        let currentDate  = today.getDate();

        let monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        let dayNames   = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];

        let calHtml = `<h5 class="text-center mb-2">${monthNames[currentMonth]} ${currentYear}</h5>`;
        calHtml += '<table class="calendar"><thead><tr>';
        dayNames.forEach(d => { calHtml += `<th>${d}</th>`; });
        calHtml += '</tr></thead><tbody>';

        let firstDay    = new Date(currentYear, currentMonth, 1).getDay();
        let daysInMonth = new Date(currentYear, currentMonth+1, 0).getDate();
        let day = 1;
        for(let row=0; row<6; row++){
            calHtml += '<tr>';
            for(let col=0; col<7; col++){
                if(row===0 && col<firstDay){
                    calHtml += '<td></td>';
                } else if(day>daysInMonth){
                    calHtml += '<td></td>';
                } else {
                    if(day===currentDate){
                        calHtml += `<td class="today">${day}</td>`;
                    } else {
                        calHtml += `<td>${day}</td>`;
                    }
                    day++;
                }
            }
            calHtml += '</tr>';
            if(day>daysInMonth) break;
        }
        calHtml += '</tbody></table>';
        $('#calendarContainer').html(calHtml);
    }
    buildCalendar();

    // 4) Unpaid Summary
    function fetchUnpaidSummary(bulan, tahun) {
        showSpinner();
        $.ajax({
            url: 'dashboard_sdm.php',
            type: 'POST',
            data: {
                action:'get_unpaid_summary',
                bulan, tahun,
                csrf_token:'<?= $_SESSION['csrf_token']; ?>'
            },
            dataType:'json',
            success:function(resp){
                hideSpinner();
                if(resp.code===0){
                    renderUnpaidSummary(resp.result);
                } else {
                    showToast(resp.result, 'error');
                }
            },
            error:function(){
                hideSpinner();
                showToast('Gagal memuat data Unpaid Summary.', 'error');
            }
        });
    }
    function renderUnpaidSummary(data){
        let c = $('#unpaidSummaryContainer');
        if(!data || data.length===0){
            c.html('<div class="alert alert-success mb-2">Semua anggota sudah di payroll final untuk periode ini.</div>');
            if(unpaidChart) { unpaidChart.destroy(); unpaidChart=null; }
            return;
        }
        let totalAll=0;
        let html='<ul class="list-group mb-3">';
        data.forEach(d=>{
            html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                       <strong>${d.jenjang}</strong>
                       <span class="badge bg-danger">${d.total} Belum Final</span>
                     </li>`;
            totalAll += d.total;
        });
        html += '</ul>';
        html += `<p class="fw-bold">Total belum final: ${totalAll}</p>`;
        c.html(html);

        let ctx = document.getElementById('unpaidSummaryChart').getContext('2d');
        if(unpaidChart) unpaidChart.destroy();

        unpaidChart = new Chart(ctx, {
            type:'bar',
            data:{
                labels:data.map(d=>d.jenjang),
                datasets:[{
                    label:'Belum di Payroll Final',
                    data:data.map(d=>d.total),
                    backgroundColor:'rgba(255,99,132,0.6)',
                    borderColor:'rgba(255,99,132,1)',
                    borderWidth:1
                }]
            },
            options:{
                responsive:true,
                scales:{
                    y:{ beginAtZero:true, ticks:{ precision:0 } }
                }
            }
        });
    }

    // ============ Inisialisasi ============

    // 1. Pie Chart Anggota per Jenjang
    fetchPayrollDashboardData();

    // 2. Upcoming Holidays
    fetchUpcomingHolidays();

    // 3. Unpaid Summary (default: bulan & tahun saat ini)
    let now = new Date();
    let currentMonth = now.getMonth()+1;
    let currentYear  = now.getFullYear();
    fetchUnpaidSummary(currentMonth, currentYear);

    // Tombol Filter Periode
    $('#btnApplyFilter').on('click', function(){
        let selBulan = parseInt($('#filterBulan').val()) || currentMonth;
        let selTahun = parseInt($('#filterTahun').val()) || currentYear;
        currentMonth = selBulan;
        currentYear  = selTahun;
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
