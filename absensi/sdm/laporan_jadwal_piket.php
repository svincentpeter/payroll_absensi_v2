<?php
// File: /payroll_absensi_v2/laporan_jadwal_piket.php

// ==============================================================================
// 1. Pengaturan Awal & Inisialisasi
// ==============================================================================
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:SDM', 'M:Superadmin'], '/payroll_absensi_v2/login.php');
require_once __DIR__ . '/../../koneksi.php';

// Hasilkan dan ambil CSRF token
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

// ---------- PROSES DELETE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    // Verifikasi CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $id_jadwal = intval($_POST['id_jadwal'] ?? 0);
    if ($id_jadwal > 0) {
        $sql = "DELETE FROM jadwal_piket WHERE id_jadwal = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_jadwal);
        if ($stmt->execute()) {
            $_SESSION['laporan_success'] = "Data jadwal berhasil dihapus.";
        } else {
            $_SESSION['laporan_error'] = "Gagal menghapus data jadwal.";
        }
        $stmt->close();
    } else {
        $_SESSION['laporan_error'] = "ID jadwal tidak valid.";
    }
    header("Location: laporan_jadwal_piket.php");
    exit();
}

// ---------- PROSES FILTER LAPORAN ----------
$jadwal_type = $_GET['jadwal_type'] ?? '1'; // Default ke Jadwal 1
$start_year  = $_GET['start_year'] ?? date("Y");
$end_year    = $_GET['end_year'] ?? date("Y");

// Validasi input
$valid_jadwal_types = ['1', '2'];
if (!in_array($jadwal_type, $valid_jadwal_types)) {
    $jadwal_type = '1';
}
$start_year = intval($start_year);
$end_year   = intval($end_year);
if ($start_year > $end_year) {
    $_SESSION['laporan_error'] = "Tahun akhir tidak boleh lebih kecil dari tahun awal.";
    header("Location: laporan_jadwal_piket.php");
    exit();
}

// Tentukan rentang tanggal berdasarkan jenis jadwal dan rentang tahun
$date_ranges = [];
if ($jadwal_type === '1') {
    // Jadwal 1: 1 Juni - 30 Juli untuk setiap tahun dalam rentang
    for ($y = $start_year; $y <= $end_year; $y++) {
        $date_ranges[] = ["$y-06-01", "$y-07-30"];
    }
} else {
    // Jadwal 2: 1 Desember tahun X - 31 Januari tahun X+1
    for ($y = $start_year; $y <= $end_year; $y++) {
        $date_ranges[] = ["$y-12-01", "$y-12-31"];
        $next_year = $y + 1;
        $date_ranges[] = ["$next_year-01-01", "$next_year-01-31"];
    }
}

// Buat query SQL dinamis
if (empty($date_ranges)) {
    $sql = "SELECT id_jadwal, nip, nama_guru, waktu_piket, tanggal, tahun, status 
            FROM jadwal_piket 
            ORDER BY tanggal ASC";
    $stmt = $conn->prepare($sql);
} else {
    $conditions = [];
    $params = [];
    $types = '';
    foreach ($date_ranges as $range) {
        $conditions[] = "(tanggal BETWEEN ? AND ?)";
        $params[] = $range[0];
        $params[] = $range[1];
        $types .= 'ss';
    }
    $where_clause = implode(' OR ', $conditions);
    $sql = "SELECT id_jadwal, nip, nama_guru, waktu_piket, tanggal, tahun, status 
            FROM jadwal_piket 
            WHERE $where_clause 
            ORDER BY tanggal ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$laporan = [];
while ($row = $result->fetch_assoc()) {
    $laporan[] = $row;
}
$stmt->close();

// Ambil daftar tahun untuk dropdown (misal, 2020 hingga 2050)
$years_display = [];
for ($y = 2020; $y <= 2050; $y++) {
    $years_display[] = $y;
}

// Proses data menjadi format pivot untuk tabel laporan
$formattedJadwal = []; // Format: $formattedJadwal[nama_guru]['waktu_piket'] dan $formattedJadwal[nama_guru][tanggal] = status
foreach ($laporan as $lap) {
    $nama_guru = $lap['nama_guru'];
    $tanggal   = $lap['tanggal']; // Format: YYYY-MM-DD
    $status    = $lap['status'];
    $waktu_piket = $lap['waktu_piket'];
    if (!isset($formattedJadwal[$nama_guru])) {
        $formattedJadwal[$nama_guru] = ['waktu_piket' => $waktu_piket];
    }
    $formattedJadwal[$nama_guru][$tanggal] = $status;
}

// Fungsi terjemahan bulan & hari
function translate_month($month_eng) {
    $months = [
        'January'   => 'Januari',
        'February'  => 'Februari',
        'March'     => 'Maret',
        'April'     => 'April',
        'May'       => 'Mei',
        'June'      => 'Juni',
        'July'      => 'Juli',
        'August'    => 'Agustus',
        'September' => 'September',
        'October'   => 'Oktober',
        'November'  => 'November',
        'December'  => 'Desember'
    ];
    return $months[$month_eng] ?? $month_eng;
}
function translate_day($day_eng) {
    $days = [
        'Mon' => 'Senin',
        'Tue' => 'Selasa',
        'Wed' => 'Rabu',
        'Thu' => 'Kamis',
        'Fri' => 'Jumat',
        'Sat' => 'Sabtu',
        'Sun' => 'Minggu'
    ];
    return $days[$day_eng] ?? $day_eng;
}

// Buat daftar tanggal unik dari laporan (sebagai header kolom)
$all_dates = [];
foreach ($laporan as $lap) {
    $tanggal = $lap['tanggal'];
    if (!isset($all_dates[$tanggal])) {
        $dateObj = new DateTime($tanggal);
        $all_dates[$tanggal] = [
            'formatted_date' => $tanggal,
            'day'            => translate_day($dateObj->format('D')),
            'month'          => translate_month($dateObj->format('F')),
            'year'           => $dateObj->format('Y')
        ];
    }
}
ksort($all_dates);

// Kelompokkan header berdasarkan bulan (untuk header multi baris)
$months = [];
foreach ($all_dates as $date => $info) {
    $full_month = $info['month'] . " " . $info['year'];
    $months[$full_month][] = $info;
}
uksort($months, function($a, $b) {
    // Urutkan berdasarkan waktu (gunakan strtotime)
    return strtotime($a) <=> strtotime($b);
});
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Jadwal Piket Guru</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5.3.3 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <!-- DataTables CSS (Bootstrap 5) - opsional -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        /* Card header gradient ala rekap_payroll */
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }
        .table-hover tbody tr:hover {
            background-color: #e2e6ea;
        }
        thead th {
            background-color: #343a40;
            color: #fff;
            text-align: center;
            vertical-align: middle;
        }
        /* Tabel pivot jadwal */
        th, td {
            vertical-align: middle !important;
            white-space: nowrap;
        }
        /* Untuk table.dataTable agar border hitam (jika diperlukan) */
        table.dataTable,
        table.dataTable thead th,
        table.dataTable tbody td {
            border: 1px solid #000 !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border: 1px solid #000 !important;
        }
        /* Agar border tabel pivot juga jadi hitam */
        .table.table-bordered, 
        .table.table-bordered thead th, 
        .table.table-bordered tbody td {
            border: 1px solid #000 !important;
        }
        /* Efek transisi fade out */
        .smooth-transition {
            transition: opacity 0.3s ease;
        }
        /* Filter Card mirip rekap_payroll */
        .card-filter-header {
            background-color: #4e73df; 
            color: #fff;
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
        }
        .form-select {
            min-width: 160px; /* perlebar select agar tulisan tidak terpotong */
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

                <!-- Breadcrumb (jika ada) -->
                <?php include __DIR__ . '/../../breadcrumb.php'; ?>

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">
                        <i class="fas fa-table"></i> Laporan Jadwal Piket Guru
                    </h1>

                    <!-- Filter Section (mirip rekap_payroll) -->
                    <div class="card mb-4 shadow" style="border-radius: 0.5rem;">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-white">
                                <i class="fas fa-search"></i> Filter Laporan Jadwal
                            </h6>
                        </div>
                        <div class="card-body" style="background-color: #f8f9fa;">
                            <form method="GET" id="filterForm" class="row gy-2 gx-3 align-items-center">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                <!-- Jenis Jadwal -->
                                <div class="col-auto">
                                    <label for="jadwal_type" class="form-label mb-0">
                                        <strong>Jenis Jadwal:</strong>
                                    </label>
                                    <select name="jadwal_type" id="jadwal_type" class="form-select" required>
                                        <option value="1" <?= ($jadwal_type === '1') ? 'selected' : ''; ?>>Jadwal 1 (1 Juni - 30 Juli)</option>
                                        <option value="2" <?= ($jadwal_type === '2') ? 'selected' : ''; ?>>Jadwal 2 (1 Desember - 31 Januari)</option>
                                    </select>
                                </div>
                                <!-- Start Year -->
                                <div class="col-auto">
                                    <label for="start_year" class="form-label mb-0"><strong>Dari Tahun:</strong></label>
                                    <select name="start_year" id="start_year" class="form-select" required>
                                        <option value="">-- Pilih Tahun Awal --</option>
                                        <?php foreach ($years_display as $y): ?>
                                            <option value="<?= $y; ?>" <?= ($start_year == $y) ? 'selected' : ''; ?>>
                                                <?= $y; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- End Year -->
                                <div class="col-auto">
                                    <label for="end_year" class="form-label mb-0"><strong>Sampai Tahun:</strong></label>
                                    <select name="end_year" id="end_year" class="form-select" required>
                                        <option value="">-- Pilih Tahun Akhir --</option>
                                        <?php foreach ($years_display as $y): ?>
                                            <option value="<?= $y; ?>" <?= ($end_year == $y) ? 'selected' : ''; ?>>
                                                <?= $y; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- Tombol -->
                                <div class="col-auto d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2" id="btnApplyFilter">
                                        <i class="fas fa-search"></i> Terapkan Filter
                                    </button>
                                    <a href="laporan_jadwal_piket.php" class="btn btn-secondary me-2" id="btnResetFilter">
                                        <i class="fas fa-undo"></i> Reset Filter
                                    </a>
                                    <a href="input_jadwal_piket_guru.php" class="btn btn-success me-2 smooth-transition">
                                        <i class="fas fa-plus"></i> Input Jadwal Piket
                                    </a>
                                    <a href="tambah_jadwal_piket.php" class="btn btn-warning smooth-transition">
        <i class="fas fa-user-plus"></i> Tambah Jadwal Piket
    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- End Filter Section -->

                    <!-- Notifikasi -->
                    <?php if(isset($_SESSION['laporan_success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['laporan_success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['laporan_success']); ?>
                    <?php endif; ?>
                    <?php if(isset($_SESSION['laporan_error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['laporan_error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['laporan_error']); ?>
                    <?php endif; ?>

                    <?php if (empty($laporan)): ?>
                        <div class="alert alert-info">Tidak ada data laporan sesuai filter.</div>
                    <?php else: ?>
                        <!-- Card untuk Tabel Laporan -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 fw-bold text-white">
                                    <i class="fas fa-clipboard-list"></i> Data Laporan Jadwal Piket
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <!-- Pivot Tabel -->
                                    <table class="table table-bordered table-hover" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th rowspan="2" class="align-middle">Nama</th>
                                                <th rowspan="2" class="align-middle">Waktu Piket</th>
                                                <?php foreach ($months as $month => $days): ?>
                                                    <th colspan="<?= count($days); ?>" class="text-center">
                                                        <?= htmlspecialchars($month); ?>
                                                    </th>
                                                <?php endforeach; ?>
                                            </tr>
                                            <tr>
                                                <?php foreach ($months as $month => $days): ?>
                                                    <?php foreach ($days as $day): ?>
                                                        <th>
                                                            <?= date('j M', strtotime($day['formatted_date'])); ?>
                                                            <br>
                                                            <?= htmlspecialchars($day['day']); ?>
                                                        </th>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($formattedJadwal as $nama_guru => $data): ?>
                                                <tr>
                                                    <td class="align-middle"><?= htmlspecialchars($nama_guru); ?></td>
                                                    <td class="align-middle"><?= htmlspecialchars($data['waktu_piket']); ?></td>
                                                    <?php foreach ($all_dates as $date => $info): ?>
                                                        <td>
                                                            <?php
                                                                if (isset($data[$date])) {
                                                                    $status = strtolower($data[$date]);
                                                                    switch ($status) {
                                                                        case 'pending':
                                                                            echo '<span class="badge bg-warning text-dark">Pending</span>';
                                                                            break;
                                                                        case 'tidak hadir':
                                                                            echo '<span class="badge bg-danger">Tidak Hadir</span>';
                                                                            break;
                                                                        case 'hadir':
                                                                            echo '<span class="badge bg-success">Hadir</span>';
                                                                            break;
                                                                        default:
                                                                            echo htmlspecialchars($data[$date]);
                                                                            break;
                                                                    }
                                                                } else {
                                                                    echo '';
                                                                }
                                                            ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div><!-- End card -->
                    <?php endif; ?>
                </div><!-- End container-fluid -->
            </div><!-- End content -->

            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?= date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div><!-- End Content Wrapper -->
    </div><!-- End Page Wrapper -->

    <!-- (Opsional) Loading Spinner -->
    <div id="loadingSpinner" style="display:none; position: fixed; z-index:9999; top:50%; left:50%; transform:translate(-50%,-50%);">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <!-- DataTables (Opsional) -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
    $(document).ready(function() {
        // Fade out alert
        setTimeout(function() {
            $(".alert").fadeTo(500, 0).slideUp(500, function() {
                $(this).remove();
            });
        }, 3000);

        // Smooth transition
        $(document).on('click', 'a.smooth-transition', function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            $('#wrapper').fadeOut(300, function() {
                window.location.href = url;
            });
        });
    });
    </script>
</body>
</html>
