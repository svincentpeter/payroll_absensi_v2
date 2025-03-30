<?php
// laporan_jadwal_piket.php
// Versi final yang menggunakan helpers.php, CSRF, dan template SB Admin 2 (Bootstrap 5.3.3)

// Inisiasi session secara aman dan buat token CSRF
require_once __DIR__ . '/../helpers.php';
start_session_safe();
generate_csrf_token();

// Jika user sedang dalam mode non-admin, bypass otorisasi khusus,
// sehingga admin (meski role-nya tidak hanya 'P' atau 'TK') bisa mengakses halaman ini.
if (!($_SESSION['non_admin_mode'] ?? false)) {
    // Jika tidak dalam mode non-admin, otorisasi hanya untuk role Pendidik dan Tenaga Kependidikan.
    authorize(['P', 'TK']);
}

// Koneksi database
require_once __DIR__ . '/../koneksi.php';

$nip  = $_SESSION['nip'] ?? '';
$nama = $_SESSION['nama'] ?? '';
if (empty($nip)) {
    die("NIP tidak ditemukan dalam session.");
}
// ---------- PROSES DELETE GURU ----------
// Menghapus semua data jadwal piket untuk guru dengan NIP tertentu.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_guru'])) {
    // Verifikasi CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $nip_to_delete = trim($_POST['nip'] ?? '');
    if (!empty($nip_to_delete)) {
        $sql = "DELETE FROM jadwal_piket WHERE nip = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $nip_to_delete);
        if ($stmt->execute()) {
            $_SESSION['laporan_success'] = "Data jadwal guru dengan NIP $nip_to_delete berhasil dihapus.";
        } else {
            $_SESSION['laporan_error'] = "Gagal menghapus data jadwal guru dengan NIP $nip_to_delete.";
        }
        $stmt->close();
    } else {
        $_SESSION['laporan_error'] = "NIP tidak valid.";
    }
    header("Location: laporan_jadwal_piket.php");
    exit();
}

// ---------- PROSES DELETE (per baris jadwal) ----------
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
    // Jadwal 1: 1 Juni - 30 Juli setiap tahun dalam rentang
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

// Buat query SQL dinamis untuk filter laporan
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

// Ambil daftar tahun untuk dropdown (2020 hingga 2050)
$years_display = [];
for ($y = 2020; $y <= 2050; $y++) {
    $years_display[] = $y;
}

// Proses data menjadi format yang sesuai untuk tabel laporan
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
function translate_month($month_eng)
{
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
function translate_day($day_eng)
{
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

// Buat daftar bulan dan tanggal berdasarkan laporan
$months = []; // Format: [ "Juni 2025" => [ ['formatted_date' => '2025-06-01', 'day' => 'Senin'], ... ], ... ]
foreach ($laporan as $lap) {
    $tanggal = $lap['tanggal'];
    $dateObj = new DateTime($tanggal);
    $month_name_eng = $dateObj->format('F');
    $year = $dateObj->format('Y');
    $full_month = translate_month($month_name_eng) . " " . $year;
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
$monthMapping = [
    'Januari'   => 1,
    'Februari'  => 2,
    'Maret'     => 3,
    'April'     => 4,
    'Mei'       => 5,
    'Juni'      => 6,
    'Juli'      => 7,
    'Agustus'   => 8,
    'September' => 9,
    'Oktober'   => 10,
    'November'  => 11,
    'Desember'  => 12
];
uksort($months, function ($a, $b) use ($monthMapping) {
    list($monthA, $yearA) = explode(' ', $a);
    list($monthB, $yearB) = explode(' ', $b);
    $yearA = (int)$yearA;
    $yearB = (int)$yearB;
    if ($yearA === $yearB) {
        return $monthMapping[$monthA] <=> $monthMapping[$monthB];
    }
    return $yearA <=> $yearB;
});
foreach ($months as $month => &$days) {
    usort($days, function ($a, $b) {
        return strtotime($a['formatted_date']) - strtotime($b['formatted_date']);
    });
}
unset($days);

// Ambil data request tukar jadwal untuk guru tujuan
if (!empty($laporan)) {
    $jadwal_ids = array_map(function ($j) {
        return $j['id_jadwal'];
    }, $laporan);
    $placeholders = implode(',', array_fill(0, count($jadwal_ids), '?'));
    $sql = "SELECT ptj.*, 
                   jp_pengaju.nama_guru AS nama_guru_pengaju, 
                   jp_pengaju.waktu_piket AS waktu_piket_pengaju
            FROM permintaan_tukar_jadwal ptj
            JOIN jadwal_piket jp_pengaju ON ptj.id_jadwal_pengaju = jp_pengaju.id_jadwal
            WHERE (ptj.id_jadwal_tujuan IN ($placeholders) OR ptj.nip_tujuan = ?)
              AND ptj.status = 'Pending'
            ORDER BY ptj.tanggal_permintaan DESC";
    $stmt = $conn->prepare($sql);
    $types = str_repeat('i', count($jadwal_ids)) . "s";
    $params = array_merge($jadwal_ids, [$nip]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $swap_requests = [];
    while ($row = $result->fetch_assoc()) {
        $swap_requests[] = $row;
    }
    $stmt->close();
} else {
    $sql = "SELECT ptj.*, 
                   jp_pengaju.nama_guru AS nama_guru_pengaju, 
                   jp_pengaju.waktu_piket AS waktu_piket_pengaju
            FROM permintaan_tukar_jadwal ptj
            JOIN jadwal_piket jp_pengaju ON ptj.id_jadwal_pengaju = jp_pengaju.id_jadwal
            WHERE ptj.nip_tujuan = ? AND ptj.status = 'Pending'
            ORDER BY ptj.tanggal_permintaan DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nip);
    $stmt->execute();
    $result = $stmt->get_result();
    $swap_requests = [];
    while ($row = $result->fetch_assoc()) {
        $swap_requests[] = $row;
    }
    $stmt->close();
}

// --- Query untuk daftar guru terdaftar di jadwal_piket (distinct) ---
$sql_guru = "SELECT DISTINCT nip, nama_guru FROM jadwal_piket ORDER BY nama_guru ASC";
$stmt_guru = $conn->prepare($sql_guru);
$stmt_guru->execute();
$result_guru = $stmt_guru->get_result();
$guru_list = [];
while ($row = $result_guru->fetch_assoc()) {
    $guru_list[] = $row;
}
$stmt_guru->close();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Jadwal Piket Guru</title>
    <!-- FontAwesome, SB Admin 2, dan Bootstrap 5.3.3 CSS via CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SB Admin 2 CSS (pastikan kompatibel dengan Bootstrap 5) -->
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <style>
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }

        .badge-tidak-hadir {
            background-color: #dc3545;
            color: #fff;
        }

        .badge-hadir {
            background-color: #28a745;
            color: #fff;
        }

        .badge-info {
            background-color: #17a2b8;
            color: #fff;
        }

        .badge-secondary {
            background-color: #6c757d;
            color: #fff;
        }

        th,
        td {
            vertical-align: middle !important;
            white-space: nowrap;
        }

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
    <div id="wrapper">
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a href="../../logout.php" class="btn btn-danger btn-sm">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </nav>
                <!-- End Topbar -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Laporan Jadwal Piket Guru</h1>

                    <!-- Tampilkan Notifikasi -->
                    <?php if (isset($_SESSION['laporan_success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['laporan_success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['laporan_success']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['laporan_error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['laporan_error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['laporan_error']); ?>
                    <?php endif; ?>

                    <!-- Tampilkan Tabel Daftar Guru Terdaftar di Jadwal Piket -->
                    <h2>Daftar Guru Terdaftar di Jadwal Piket</h2>
                    <table class="table table-bordered text-center mb-4">
                        <thead>
                            <tr>
                                <th>NIP</th>
                                <th>Nama Guru</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($guru_list)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">Tidak ada data guru terdaftar.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($guru_list as $guru): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($guru['nip']); ?></td>
                                        <td><?= htmlspecialchars($guru['nama_guru']); ?></td>
                                        <td>
                                            <form method="POST" action="laporan_jadwal_piket.php" onsubmit="return confirm('Apakah Anda yakin ingin menghapus semua data jadwal untuk guru ini?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                                <input type="hidden" name="nip" value="<?= htmlspecialchars($guru['nip']); ?>">
                                                <button type="submit" name="delete_guru" class="btn btn-danger btn-sm">Hapus Guru</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Tampilan Filter Laporan -->
                    <h2>Laporan Jadwal Piket Guru</h2>
                    <form method="GET" action="laporan_jadwal_piket.php" class="row gy-2 gx-3 align-items-center mb-4">
                        <div class="col-auto">
                            <label for="jadwal_type" class="col-form-label">Jenis Jadwal:</label>
                        </div>
                        <div class="col-auto">
                            <select name="jadwal_type" id="jadwal_type" class="form-select" required>
                                <option value="1" <?= ($jadwal_type === '1') ? 'selected' : ''; ?>>Jadwal 1 (1 Juni - 30 Juli)</option>
                                <option value="2" <?= ($jadwal_type === '2') ? 'selected' : ''; ?>>Jadwal 2 (1 Desember - 31 Januari)</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <label for="start_year" class="col-form-label">Dari Tahun:</label>
                        </div>
                        <div class="col-auto">
                            <select name="start_year" id="start_year" class="form-select" required>
                                <option value="">-- Pilih Tahun Awal --</option>
                                <?php foreach ($years_display as $y): ?>
                                    <option value="<?= $y; ?>" <?= ($start_year == $y) ? 'selected' : ''; ?>><?= $y; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <label for="end_year" class="col-form-label">Sampai Tahun:</label>
                        </div>
                        <div class="col-auto">
                            <select name="end_year" id="end_year" class="form-select" required>
                                <option value="">-- Pilih Tahun Akhir --</option>
                                <?php foreach ($years_display as $y): ?>
                                    <option value="<?= $y; ?>" <?= ($end_year == $y) ? 'selected' : ''; ?>><?= $y; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>
                    </form>

                    <?php
                    if ($start_year > $end_year) {
                        echo "<div class='alert alert-danger'>Tahun akhir tidak boleh lebih kecil dari tahun awal.</div>";
                    }
                    ?>

                    <!-- Tampilan Laporan Jadwal Piket -->
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

                </div><!-- End Container Fluid -->
            </div><!-- End Content -->
        </div><!-- End Content Wrapper -->
    </div><!-- End Page Wrapper -->

    <!-- jQuery, Bootstrap 5.3.3 JS, dan SB Admin 2 JS via CDN -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
</body>

</html>
<?php
// Tutup koneksi database menggunakan fungsi dari helpers.php
close_db_connection();
?>