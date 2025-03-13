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
    // Jadwal 2: 1 Desember tahun X - 31 Januari tahun X+1 untuk setiap tahun dalam rentang
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

// Proses data menjadi format untuk tabel laporan
$formattedJadwal = []; // Format: $formattedJadwal[nama_guru]['waktu_piket'] dan $formattedJadwal[nama_guru][tanggal] = status
foreach ($laporan as $lap) {
    $nama_guru = $lap['nama_guru'];
    $tanggal = $lap['tanggal']; // Format: YYYY-MM-DD
    $status = $lap['status'];
    $waktu_piket = $lap['waktu_piket'];
    if (!isset($formattedJadwal[$nama_guru])) {
        $formattedJadwal[$nama_guru] = ['waktu_piket' => $waktu_piket];
    }
    $formattedJadwal[$nama_guru][$tanggal] = $status;
}

// Fungsi untuk menerjemahkan nama bulan dari Inggris ke Indonesia
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

// Fungsi untuk menerjemahkan hari dari Inggris ke Indonesia
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

// Buat daftar bulan dan tanggal berdasarkan data laporan
$months = []; // Format: [ "Juni 2025" => [ ['formatted_date' => '2025-06-01', 'day' => 'Senin'], ... ], ... ]
foreach ($laporan as $lap) {
    $tanggal = $lap['tanggal'];
    $dateObj = new DateTime($tanggal);
    $month_name_eng = $dateObj->format('F');
    $year = $dateObj->format('Y');
    $full_month = translate_month($month_name_eng) . " $year";
    $day_short_eng = $dateObj->format('D');
    $day_full = translate_day($day_short_eng);
    if (!isset($months[$full_month])) {
        $months[$full_month] = [];
    }
    // Tambahkan tanggal jika belum ada
    $existing_dates = array_column($months[$full_month], 'formatted_date');
    if (!in_array($tanggal, $existing_dates)) {
        $months[$full_month][] = [
            'formatted_date' => $tanggal,
            'day' => $day_full
        ];
    }
}
// Urutkan kelompok bulan berdasarkan tahun dan nomor bulan
uksort($months, function($a, $b) {
    $translate_reverse = [
        'Januari'   => 'January',
        'Februari'  => 'February',
        'Maret'     => 'March',
        'April'     => 'April',
        'Mei'       => 'May',
        'Juni'      => 'June',
        'Juli'      => 'July',
        'Agustus'   => 'August',
        'September' => 'September',
        'Oktober'   => 'October',
        'November'  => 'November',
        'Desember'  => 'December'
    ];
    list($monthA, $yearA) = explode(' ', $a);
    list($monthB, $yearB) = explode(' ', $b);
    $monthA_eng = $translate_reverse[$monthA] ?? $monthA;
    $monthB_eng = $translate_reverse[$monthB] ?? $monthB;
    $dateA = DateTime::createFromFormat('F Y', "$monthA_eng $yearA");
    $dateB = DateTime::createFromFormat('F Y', "$monthB_eng $yearB");
    return $dateA <=> $dateB;
});
foreach ($months as $month => &$days) {
    usort($days, function($a, $b) {
        return strtotime($a['formatted_date']) - strtotime($b['formatted_date']);
    });
}
unset($days);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Jadwal Piket Guru</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- SB Admin 2 CSS & Bootstrap 5 CSS via CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS (versi Bootstrap 5) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        th, td {
            vertical-align: middle !important;
            white-space: nowrap;
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

                <!-- Breadcrumb -->
                <?php include __DIR__ . '/../../breadcrumb.php'; ?>

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Laporan Jadwal Piket Guru</h1>
                    
                    <!-- Tampilkan notifikasi -->
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

                    <!-- Form Filter -->
                    <form method="GET" class="row mb-4">
                        <div class="col-md-4">
                            <label for="jadwal_type" class="form-label">Jenis Jadwal:</label>
                            <select name="jadwal_type" id="jadwal_type" class="form-control" required>
                                <option value="1" <?= ($jadwal_type === '1') ? 'selected' : ''; ?>>Jadwal 1 (1 Juni - 30 Juli)</option>
                                <option value="2" <?= ($jadwal_type === '2') ? 'selected' : ''; ?>>Jadwal 2 (1 Desember - 31 Januari)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="start_year" class="form-label">Dari Tahun:</label>
                            <select name="start_year" id="start_year" class="form-control" required>
                                <option value="">-- Pilih Tahun Awal --</option>
                                <?php foreach ($years_display as $y): ?>
                                    <option value="<?= $y; ?>" <?= ($start_year == $y) ? 'selected' : ''; ?>><?= $y; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="end_year" class="form-label">Sampai Tahun:</label>
                            <select name="end_year" id="end_year" class="form-control" required>
                                <option value="">-- Pilih Tahun Akhir --</option>
                                <?php foreach ($years_display as $y): ?>
                                    <option value="<?= $y; ?>" <?= ($end_year == $y) ? 'selected' : ''; ?>><?= $y; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12 mt-3">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                        </div>
                    </form>

                    <?php if (empty($laporan)): ?>
                        <div class="alert alert-info">Tidak ada data laporan sesuai filter.</div>
                    <?php else: ?>
                        <?php
                            // Kumpulkan semua tanggal unik dari laporan
                            $all_dates = [];
                            foreach ($months as $month => $days) {
                                foreach ($days as $day) {
                                    $all_dates[$day['formatted_date']] = $day;
                                }
                            }
                            ksort($all_dates);
                        ?>
                        <div class="table-responsive">
                            <table class="table table-bordered text-center">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="align-middle">Nama</th>
                                        <th rowspan="2" class="align-middle">Waktu Piket</th>
                                        <?php foreach ($months as $month => $days): ?>
                                            <th colspan="<?= count($days); ?>" class="text-center"><?= htmlspecialchars($month); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <?php foreach ($months as $month => $days): ?>
                                            <?php foreach ($days as $day): ?>
                                                <th><?= date('j M', strtotime($day['formatted_date'])); ?><br><?= htmlspecialchars($day['day']); ?></th>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($formattedJadwal as $nama_guru => $data): ?>
                                        <tr>
                                            <td class="align-middle"><?= htmlspecialchars($nama_guru); ?></td>
                                            <td class="align-middle"><?= htmlspecialchars($data['waktu_piket']); ?></td>
                                            <?php foreach ($all_dates as $date => $day_info): ?>
                                                <td>
                                                    <?php
                                                        if (isset($data[$date])) {
                                                            $status = strtolower($data[$date]);
                                                            switch ($status) {
                                                                case 'pending':
                                                                    echo '<span class="badge badge-pending">Pending</span>';
                                                                    break;
                                                                case 'tidak hadir':
                                                                    echo '<span class="badge badge-tidak-hadir">Tidak Hadir</span>';
                                                                    break;
                                                                case 'hadir':
                                                                    echo '<span class="badge badge-hadir">Hadir</span>';
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
                    <?php endif; ?>

                </div><!-- End Page Content -->
            </div><!-- End Main Content -->

            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?php echo date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div><!-- End Content Wrapper -->
    </div><!-- End Page Wrapper -->

    <!-- JavaScript Dependencies -->
    <!-- jQuery, Bootstrap 5.3.3, dan DataTables JS via CDN -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <!-- DataTables Buttons & Responsive (jika diperlukan) -->
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#activeIjinTable').DataTable({
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.13.4/i18n/id.json",
                    emptyTable: "Tidak ada data laporan sesuai filter."
                }
            });
            $('#historyIjinTable').DataTable({
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.13.4/i18n/id.json",
                    emptyTable: "Tidak ada data laporan sesuai filter."
                }
            });
        });
    </script>
</body>
</html>
