<?php
// File: /payroll_absensi_v2/absensi/guru/dashboard_guru.php

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();

// Batasi akses hanya untuk Pendidik (P) dan Tenaga Kependidikan (TK)
if (!($_SESSION['non_admin_mode'] ?? false)) {
    // Jika tidak dalam mode non-admin, otorisasi hanya untuk role Pendidik dan Tenaga Kependidikan.
    authorize(['P', 'TK']);
}
// Koneksi Database
require_once __DIR__ . '/../koneksi.php';
if (!$conn) {
    die("Koneksi database gagal.");
}

// Ambil NIP dari session
$nip = $_SESSION['nip'] ?? '';
if (empty($nip)) {
    header("Location: " . getBaseUrl() . "/index.php");
    exit();
}

// Ambil data pengguna
$userData = fetchSingleRow(
    $conn,
    "SELECT id, nama, role, job_title FROM anggota_sekolah WHERE nip = ? LIMIT 1",
    "s",
    [$nip]
);
if (!$userData) {
    header("Location: " . getBaseUrl() . "/index.php");
    exit();
}

// Simpan role dan job_title ke session
$_SESSION['role']      = $userData['role'];
$_SESSION['job_title'] = $userData['job_title'];

// Pastikan user diarahkan ke route yang benar
$expected = getDashboardRoute($userData['role'], $userData['job_title']);
if ($expected !== "guru/dashboard_guru.php") {
    header("Location: " . getBaseUrl() . "/" . $expected);
    exit();
}

$nama_pengguna = $userData['nama'];
$id_anggota    = $userData['id'];

// Catat audit log
add_audit_log($conn, $nip, 'AccessDashboard', 'Mengakses dashboard Guru.');

// Judul
$dashboard_title = 'Dashboard Guru';

// Filter bulan & tahun
$filterMonth = intval($_GET['bulan'] ?? date('n'));
$filterYear  = intval($_GET['tahun']  ?? date('Y'));
$displayBulan = getIndonesianMonthName($filterMonth);

// Ambil ringkasan kehadiran
$sumData = fetchSingleRow(
    $conn,
    "SELECT
         SUM(CASE WHEN status_kehadiran='hadir'            THEN 1 ELSE 0 END) AS total_hadir,
         SUM(CASE WHEN status_kehadiran='izin'             THEN 1 ELSE 0 END) AS total_izin,
         SUM(CASE WHEN status_kehadiran='cuti'             THEN 1 ELSE 0 END) AS total_cuti,
         SUM(CASE WHEN status_kehadiran='tanpa_keterangan' THEN 1 ELSE 0 END) AS total_tanpa_keterangan,
         SUM(CASE WHEN status_kehadiran='sakit'            THEN 1 ELSE 0 END) AS total_sakit
     FROM absensi
     WHERE nip = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?",
    "sii",
    [$nip, $filterMonth, $filterYear]
);

$sumHadir    = intval($sumData['total_hadir'] ?? 0);
$sumIzin     = intval($sumData['total_izin']  ?? 0);
$sumCuti     = intval($sumData['total_cuti']  ?? 0);
$sumTanpaKet = intval($sumData['total_tanpa_keterangan'] ?? 0);
$sumSakit    = intval($sumData['total_sakit'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($dashboard_title) ?></title>
    <!-- Bootstrap 5 CSS & SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <!-- jQuery & SweetAlert2 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #f8f9fc;
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
        .welcome-message {
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }

        .stat-card .card-body p {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }

        .gradient-hadir {
            background: linear-gradient(135deg, #e0ffe0 0%, #90ee90 100%);
            color: #064d06;
        }

        .gradient-izin {
            background: linear-gradient(135deg, #d6f5ff 0%, #80dfff 100%);
            color: #064b5c;
        }

        .gradient-cuti {
            background: linear-gradient(135deg, #fff8d6 0%, #ffe680 100%);
            color: #665400;
        }

        .gradient-tanpa {
            background: linear-gradient(135deg, #ffd6d6 0%, #ff9f9f 100%);
            color: #5e0000;
        }

        .gradient-sakit {
            background: linear-gradient(135deg, #f0f0f0 0%, #dcdcdc 100%);
            color: #333;
        }

        .icon-size {
            font-size: 1.5rem;
        }

        .ringkasan-gaji .text-xs {
            font-size: 0.75rem;
        }

        .ringkasan-gaji .h5 {
            font-size: 1rem;
        }
    </style>
</head>

<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <!-- End Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Navbar -->
                <?php include __DIR__ . '/../navbar.php'; ?>
                <!-- End Navbar -->
                <?php include __DIR__ . '/../breadcrumb.php'; ?>
                <!-- Begin Page Content -->
                <div class="container-fluid py-3">
                    <!-- Header / Judul Halaman -->
<h1 class="page-title">
        <i class="fas fa-tachometer-alt me-2"></i><?= htmlspecialchars($dashboard_title) ?>
    </h1>
                    <!-- Sambutan -->
                    <div class="welcome-message mb-4">
                        <div class="card shadow bg-white rounded">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-smile-beam me-2" style="font-size:1.5rem;"></i>
                                    <div>
                                        <h5 class="card-title mb-1" style="font-size:1rem;">Selamat Datang</h5>
                                        <p class="mb-0" style="font-size:0.9rem;">Halo, <strong><?= htmlspecialchars($nama_pengguna) ?></strong>. Semoga hari Anda menyenangkan!</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notifikasi Session -->
                    <?php
                    if (isset($_SESSION['success_message'])) {
                        echo "<div class='alert alert-success alert-dismissible fade show' role='alert'>"
                            . htmlspecialchars($_SESSION['success_message']) .
                            "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
                        unset($_SESSION['success_message']);
                    }
                    ?>

                    <!-- Form Filter Bulan dan Tahun -->
                    <form method="GET" action="" class="mb-4">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label for="bulan" class="form-label">Bulan</label>
                                <select name="bulan" id="bulan" class="form-select">
                                    <?php
                                    for ($i = 1; $i <= 12; $i++) {
                                        $selected = ($i === $filterMonth) ? 'selected' : '';
                                        echo "<option value=\"$i\" $selected>" . getIndonesianMonthName($i) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="tahun" class="form-label">Tahun</label>
                                <select name="tahun" id="tahun" class="form-select">
                                    <?php
                                    $startYear = date('Y') - 5;
                                    $endYear = date('Y') + 1;
                                    for ($year = $startYear; $year <= $endYear; $year++) {
                                        $selected = ($year === $filterYear) ? 'selected' : '';
                                        echo "<option value=\"$year\" $selected>$year</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>
                        </div>
                    </form>
                    <div class="text-center mb-3">
                        <small>Menampilkan data kehadiran untuk Bulan <?= htmlspecialchars($displayBulan) ?> Tahun <?= htmlspecialchars($filterYear) ?></small>
                    </div>

                    <!-- ITEM: Tanggal & Waktu Realtime -->
                    <div class="card shadow mb-4">
                        <div class="card-header text-center" style="background: linear-gradient(45deg, #ff8a00, #e52e71); color: white;">
                            <h6 class="m-0 font-weight-bold">Waktu Sekarang</h6>
                        </div>
                        <div class="card-body text-center">
                            <div id="currentDateTime" class="h4"></div>
                        </div>
                    </div>

                    <!-- STATISTIK KEHADIRAN (5 CARD) -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <div><i class="fas fa-chart-bar me-2"></i>Statistik Kehadiran</div>
                        </div>
                        <div class="card-body">
                            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-5 g-2 justify-content-center text-center">
                                <!-- Card: Hadir -->
                                <div class="col">
                                    <div class="card h-100 border-0 shadow-sm gradient-hadir stat-card p-2">
                                        <div class="card-body d-flex flex-column align-items-center justify-content-center py-2">
                                            <i class="fas fa-check-circle icon-size mb-1"></i>
                                            <h6 class="fw-bold mb-1" style="font-size:0.85rem;">Hadir</h6>
                                            <p><?= $sumHadir; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <!-- Card: Izin -->
                                <div class="col">
                                    <div class="card h-100 border-0 shadow-sm gradient-izin stat-card p-2">
                                        <div class="card-body d-flex flex-column align-items-center justify-content-center py-2">
                                            <i class="fas fa-user-clock icon-size mb-1"></i>
                                            <h6 class="fw-bold mb-1" style="font-size:0.85rem;">Izin</h6>
                                            <p><?= $sumIzin; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <!-- Card: Cuti -->
                                <div class="col">
                                    <div class="card h-100 border-0 shadow-sm gradient-cuti stat-card p-2">
                                        <div class="card-body d-flex flex-column align-items-center justify-content-center py-2">
                                            <i class="fas fa-plane icon-size mb-1"></i>
                                            <h6 class="fw-bold mb-1" style="font-size:0.85rem;">Cuti</h6>
                                            <p><?= $sumCuti; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <!-- Card: Tanpa Keterangan -->
                                <div class="col">
                                    <div class="card h-100 border-0 shadow-sm gradient-tanpa stat-card p-2">
                                        <div class="card-body d-flex flex-column align-items-center justify-content-center py-2">
                                            <i class="fas fa-question-circle icon-size mb-1"></i>
                                            <h6 class="fw-bold mb-1" style="font-size:0.85rem;">Tanpa Keterangan</h6>
                                            <p><?= $sumTanpaKet; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <!-- Card: Sakit -->
                                <div class="col">
                                    <div class="card h-100 border-0 shadow-sm gradient-sakit stat-card p-2">
                                        <div class="card-body d-flex flex-column align-items-center justify-content-center py-2">
                                            <i class="fas fa-user-injured icon-size mb-1"></i>
                                            <h6 class="fw-bold mb-1" style="font-size:0.85rem;">Sakit</h6>
                                            <p><?= $sumSakit; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MENU CEPAT -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <div><i class="fas fa-bars me-2"></i>Menu Cepat</div>
                        </div>
                        <div class="card-body">
                            <div class="row row-cols-2 row-cols-sm-3 row-cols-md-6 g-2">
                                <!-- Contoh Menu: Jadwal Piket -->
                                <div class="col">
                                    <a href="<?= getBaseUrl(); ?>/absensi/guru/dashboard_jadwal.php" class="text-decoration-none">
                                        <div class="card text-center shadow-sm h-100 p-2">
                                            <div class="card-body">
                                                <i class="fas fa-calendar-alt" style="font-size:1.5rem;"></i>
                                                <h6 class="card-title mt-1" style="font-size:0.85rem;">Jadwal Piket</h6>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <!-- Menu lainnya -->
                                <div class="col">
                                    <a href="<?= getBaseUrl(); ?>/absensi/guru/laporan_jadwal_piket.php" class="text-decoration-none">
                                        <div class="card text-center shadow-sm h-100 p-2">
                                            <div class="card-body">
                                                <i class="fas fa-table" style="font-size:1.5rem;"></i>
                                                <h6 class="card-title mt-1" style="font-size:0.85rem;">Laporan Jadwal</h6>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col">
                                    <a href="<?= getBaseUrl(); ?>/absensi/guru/laporan_surat.php" class="text-decoration-none">
                                        <div class="card text-center shadow-sm h-100 p-2">
                                            <div class="card-body">
                                                <i class="fas fa-envelope" style="font-size:1.5rem;"></i>
                                                <h6 class="card-title mt-1" style="font-size:0.85rem;">Laporan Surat</h6>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col">
                                    <a href="<?= getBaseUrl(); ?>/absensi/guru/pengajuan_surat_ijin.php" class="text-decoration-none">
                                        <div class="card text-center shadow-sm h-100 p-2">
                                            <div class="card-body">
                                                <i class="fas fa-file-upload" style="font-size:1.5rem;"></i>
                                                <h6 class="card-title mt-1" style="font-size:0.85rem;">Pengajuan Izin</h6>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col">
                                    <a href="<?= getBaseUrl(); ?>/absensi/guru/request_tukar_jadwal.php" class="text-decoration-none">
                                        <div class="card text-center shadow-sm h-100 p-2">
                                            <div class="card-body">
                                                <i class="fas fa-exchange-alt" style="font-size:1.5rem;"></i>
                                                <h6 class="card-title mt-1" style="font-size:0.85rem;">Tukar Jadwal</h6>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col">
                                    <a href="<?= getBaseUrl(); ?>/absensi/guru/payroll_overview.php" class="text-decoration-none">
                                        <div class="card text-center shadow-sm h-100 p-2">
                                            <div class="card-body">
                                                <i class="fas fa-money-bill-wave" style="font-size:1.5rem;"></i>
                                                <h6 class="card-title mt-1" style="font-size:0.85rem;">Payroll</h6>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- HARI LIBUR & JADWAL HARI INI -->
                    <div class="row">
                        <!-- Hari Libur Mendatang -->
                        <div class="col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                    <div><i class="fas fa-calendar-alt me-2"></i>Hari Libur Mendatang</div>
                                </div>
                                <div class="card-body p-2" style="font-size:0.85rem;">
                                    <?php
                                    $todayDate = date('Y-m-d');
                                    $queryHolidays = "SELECT * FROM holidays WHERE holiday_date >= ? ORDER BY holiday_date ASC LIMIT 3";
                                    $stmtHoliday = $conn->prepare($queryHolidays);
                                    $stmtHoliday->bind_param("s", $todayDate);
                                    $stmtHoliday->execute();
                                    $resultHoliday = $stmtHoliday->get_result();
                                    if ($resultHoliday->num_rows > 0) {
                                        echo '<ul class="list-group">';
                                        while ($holiday = $resultHoliday->fetch_assoc()) {
                                            $holidayDate = date('d M Y', strtotime($holiday['holiday_date']));
                                            $badgeClass = ($holiday['holiday_type'] == 'wajib') ? 'bg-danger' : 'bg-secondary';
                                            echo '<li class="list-group-item d-flex justify-content-between align-items-center">'
                                                . htmlspecialchars($holiday['holiday_title']) . ' - ' . $holidayDate
                                                . '<span class="badge ' . $badgeClass . '">'
                                                . ucfirst($holiday['holiday_type'])
                                                . '</span>'
                                                . '</li>';
                                        }
                                        echo '</ul>';
                                    } else {
                                        echo '<p class="mb-0">Tidak ada hari libur mendatang.</p>';
                                    }
                                    $stmtHoliday->close();
                                    ?>
                                </div>
                            </div>
                        </div>
                        <!-- Jadwal Hari Ini -->
                        <div class="col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                    <div><i class="fas fa-clock me-2"></i>Jadwal Hari Ini</div>
                                </div>
                                <div class="card-body p-2" style="font-size:0.85rem;">
                                    <?php
                                    $querySchedule = "SELECT * FROM jadwal_piket WHERE nip = ? AND tanggal = ? ORDER BY waktu_piket ASC";
                                    $stmtSchedule = $conn->prepare($querySchedule);
                                    $stmtSchedule->bind_param("ss", $nip, $todayDate);
                                    $stmtSchedule->execute();
                                    $resultSchedule = $stmtSchedule->get_result();
                                    if ($resultSchedule->num_rows > 0) {
                                        echo '<table class="table table-sm table-bordered">';
                                        echo '<thead class="table-light"><tr><th>Waktu Piket</th><th>Status</th></tr></thead><tbody>';
                                        while ($schedule = $resultSchedule->fetch_assoc()) {
                                            echo '<tr><td>' . htmlspecialchars($schedule['waktu_piket']) . '</td><td>'
                                                . htmlspecialchars(ucfirst($schedule['status'])) . '</td></tr>';
                                        }
                                        echo '</tbody></table>';
                                    } else {
                                        echo '<p class="mb-0">Tidak ada jadwal untuk hari ini.</p>';
                                    }
                                    $stmtSchedule->close();
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RINGKASAN GAJI -->
                    <div class="card shadow mb-4 ringkasan-gaji">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <div><i class="fas fa-money-check-alt me-2"></i>Ringkasan Gaji</div>
                        </div>
                        <div class="card-body p-2">
                            <?php
                            // Ambil data payroll dari table payroll_final berdasarkan id_anggota, bulan, dan tahun
                            $queryPayroll = "SELECT * FROM payroll_final WHERE id_anggota = ? AND bulan = ? AND tahun = ? ORDER BY finalized_at DESC LIMIT 1";
                            $stmtPayroll = $conn->prepare($queryPayroll);
                            $stmtPayroll->bind_param("iii", $id_anggota, $filterMonth, $filterYear);
                            $stmtPayroll->execute();
                            $resultPayroll = $stmtPayroll->get_result();
                            if ($resultPayroll->num_rows > 0) {
                                $payroll = $resultPayroll->fetch_assoc();
                            ?>
                                <div class="row text-center">
                                    <div class="col-sm-3 mb-2">
                                        <div class="card border-left-primary shadow-sm h-100 py-2">
                                            <div class="card-body p-2">
                                                <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                                    Gaji Pokok
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    <?= formatNominal($payroll['gaji_pokok']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-3 mb-2">
                                        <div class="card border-left-info shadow-sm h-100 py-2">
                                            <div class="card-body p-2">
                                                <div class="text-xs fw-bold text-info text-uppercase mb-1">
                                                    Total Pendapatan
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    <?= formatNominal($payroll['total_pendapatan']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-3 mb-2">
                                        <div class="card border-left-warning shadow-sm h-100 py-2">
                                            <div class="card-body p-2">
                                                <div class="text-xs fw-bold text-warning text-uppercase mb-1">
                                                    Total Potongan
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    <?= formatNominal($payroll['total_potongan']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-3 mb-2">
                                        <div class="card border-left-success shadow-sm h-100 py-2">
                                            <div class="card-body p-2">
                                                <div class="text-xs fw-bold text-success text-uppercase mb-1">
                                                    Gaji Bersih
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    <?= formatNominal($payroll['gaji_bersih']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-2 text-center">
                                    Payroll terakhir diproses pada: <?= date('d M Y H:i', strtotime($payroll['tgl_payroll'])); ?>
                                </p>
                            <?php
                            } else {
                                echo '<p class="text-center">Payroll belum diproses untuk periode ini.</p>';
                            }
                            $stmtPayroll->close();
                            ?>
                        </div>
                    </div>
                    <!-- END RINGKASAN GAJI -->

                </div>
                <!-- End Page Content -->
            </div>
            <!-- End Main Content -->


        </div>
        <!-- End Content Wrapper -->
    </div>
    <!-- End Page Wrapper -->

    <!-- JavaScript Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js"></script>

    <!-- Script untuk update tanggal & waktu secara realtime -->
    <script>
        function updateDateTime() {
            var now = new Date();
            var options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
            var dateString = now.toLocaleDateString('id-ID', options);
            var timeString = now.toLocaleTimeString('id-ID', {
                hour12: false
            });
            document.getElementById('currentDateTime').innerHTML = dateString + ' - ' + timeString;
        }
        setInterval(updateDateTime, 1000);
        updateDateTime();
    </script>
</body>

</html>
<?php
// Tutup koneksi database menggunakan fungsi dari helpers.php
close_db_connection();
?>