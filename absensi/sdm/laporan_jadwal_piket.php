<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../../koneksi.php';

// ---------- PROSES DELETE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
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
$start_year = $_GET['start_year'] ?? date("Y");
$end_year = $_GET['end_year'] ?? date("Y");

// Validasi input
$valid_jadwal_types = ['1', '2'];
if (!in_array($jadwal_type, $valid_jadwal_types)) {
    $jadwal_type = '1';
}

// Validasi tahun
$start_year = intval($start_year);
$end_year = intval($end_year);
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

// Buat query SQL dinamis dengan multiple OR conditions untuk mendapatkan tanggal yang ada di database
if (empty($date_ranges)) {
    // Jika tidak ada rentang tanggal, tampilkan semua data
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
    // Bind parameters
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$laporan = [];
while ($row = $result->fetch_assoc()) {
    $laporan[] = $row;
}
$stmt->close();

// Ambil daftar tahun untuk dropdown (2020 hingga 2050)
$years_display = [];
for ($y = 2020; $y <= 2050; $y++) {
    $years_display[] = $y;
}

// Proses data menjadi format yang sesuai untuk tabel
$formattedJadwal = []; // [nama_guru]['waktu_piket'] dan [nama_guru][tanggal] = status

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
        'January' => 'Januari',
        'February' => 'Februari',
        'March' => 'Maret',
        'April' => 'April',
        'May' => 'Mei',
        'June' => 'Juni',
        'July' => 'Juli',
        'August' => 'Agustus',
        'September' => 'September',
        'October' => 'Oktober',
        'November' => 'November',
        'December' => 'Desember'
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

// Buat daftar bulan dan tanggal berdasarkan tanggal yang ada di database
$months = []; // ['Juni 2025' => [ ['formatted_date' => '2025-06-01', 'day' => 'Senin'], ... ], ...]

foreach ($laporan as $lap) {
    $tanggal = $lap['tanggal'];
    $dateObj = new DateTime($tanggal);
    $month_name_eng = $dateObj->format('F');
    $year = $dateObj->format('Y');
    $full_month = translate_month($month_name_eng) . " $year";
    $day_short_eng = $dateObj->format('D'); // Mon, Tue, etc.
    $day_full = translate_day($day_short_eng); // Senin, Selasa, etc.

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

// Urutkan bulan berdasarkan tanggal
uksort($months, function($a, $b) {
    // Karena bulan sudah dalam Bahasa Indonesia, kita perlu menerjemahkan kembali ke Bahasa Inggris untuk pengurutan
    $translate_reverse = [
        'Januari' => 'January',
        'Februari' => 'February',
        'Maret' => 'March',
        'April' => 'April',
        'Mei' => 'May',
        'Juni' => 'June',
        'Juli' => 'July',
        'Agustus' => 'August',
        'September' => 'September',
        'Oktober' => 'October',
        'November' => 'November',
        'Desember' => 'December'
    ];

    list($month_a, $year_a) = explode(' ', $a);
    list($month_b, $year_b) = explode(' ', $b);

    $month_a_eng = $translate_reverse[$month_a] ?? $month_a;
    $month_b_eng = $translate_reverse[$month_b] ?? $month_b;

    $date_a = DateTime::createFromFormat('F Y', "$month_a_eng $year_a");
    $date_b = DateTime::createFromFormat('F Y', "$month_b_eng $year_b");

    return $date_a <=> $date_b;
});

// Urutkan tanggal dalam setiap bulan
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
    <!-- Custom fonts for this template-->
    <link href="../../assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="../../assets/css/sb-admin-2.min.css" rel="stylesheet">
    <!-- Bootstrap CSS (optional, since sb-admin-2 includes Bootstrap) -->
    <style>
        /* Styling untuk badge status */
        .badge-pending {
            background-color: #ffc107; /* Kuning */
            color: #212529;
        }
        .badge-tidak-hadir {
            background-color: #dc3545; /* Merah */
            color: #fff;
        }
        .badge-hadir {
            background-color: #28a745; /* Hijau */
            color: #fff;
        }
        /* Styling untuk tabel */
        th, td {
            vertical-align: middle !important;
            white-space: nowrap;
        }
        /* Tambahan styling sesuai dashboard_guru.php */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .table thead th {
            background-color: #f8f9fc;
            color: #5a5c69;
            border-bottom: 2px solid #e3e6f0;
        }
        .table tbody tr:nth-child(even) {
            background-color: #f8f9fc;
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
                    
                    <!-- Menampilkan Notifikasi -->
                    <?php if (isset($_SESSION['laporan_success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['laporan_success']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['laporan_success']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['laporan_error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['laporan_error']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['laporan_error']); ?>
                    <?php endif; ?>

                    <!-- Form Filter Berdasarkan Jenis Jadwal dan Rentang Tahun -->
                    <form method="GET" action="laporan_jadwal_piket.php" class="form-inline mb-4">
                        <div class="form-group mr-3">
                            <label for="jadwal_type" class="mr-2">Jenis Jadwal:</label>
                            <select name="jadwal_type" id="jadwal_type" class="form-control" required>
                                <option value="1" <?= ($jadwal_type === '1') ? 'selected' : ''; ?>>Jadwal 1 (1 Juni - 30 Juli)</option>
                                <option value="2" <?= ($jadwal_type === '2') ? 'selected' : ''; ?>>Jadwal 2 (1 Desember - 31 Januari)</option>
                            </select>
                        </div>
                        <div class="form-group mr-3">
                            <label for="start_year" class="mr-2">Dari Tahun:</label>
                            <select name="start_year" id="start_year" class="form-control" required>
                                <option value="">-- Pilih Tahun Awal --</option>
                                <?php foreach ($years_display as $y): ?>
                                    <option value="<?= $y; ?>" <?= ($start_year == $y) ? 'selected' : ''; ?>><?= $y; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mr-3">
                            <label for="end_year" class="mr-2">Sampai Tahun:</label>
                            <select name="end_year" id="end_year" class="form-control" required>
                                <option value="">-- Pilih Tahun Akhir --</option>
                                <?php foreach ($years_display as $y): ?>
                                    <option value="<?= $y; ?>" <?= ($end_year == $y) ? 'selected' : ''; ?>><?= $y; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </form>

                    <!-- Validasi Rentang Tahun di Server-side -->
                    <?php
                    if ($start_year > $end_year) {
                        echo "<div class='alert alert-danger'>Tahun akhir tidak boleh lebih kecil dari tahun awal.</div>";
                    }
                    ?>

                    <!-- Tampilan Laporan -->
                    <?php if (empty($laporan)): ?>
                        <div class="alert alert-info">Tidak ada data laporan sesuai filter.</div>
                    <?php else: ?>
                        <?php
                            // Kumpulkan semua tanggal unik untuk menghindari duplikasi
                            $all_dates = [];
                            foreach ($months as $month => $days) {
                                foreach ($days as $day) {
                                    $all_dates[$day['formatted_date']] = $day;
                                }
                            }

                            // Urutkan tanggal
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
                </div>
                <!-- /.container-fluid -->
            </div>
            <!-- End of Main Content -->
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <!-- jQuery, Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <!-- FontAwesome JS -->
    <script src="../../assets/vendor/fontawesome-free/js/all.min.js"></script>
</body>
</html>
