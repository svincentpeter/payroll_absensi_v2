<?php
session_start();
ini_set('display_errors', 1); // Aktifkan untuk debugging
ini_set('display_startup_errors', 1); // Aktifkan untuk debugging
error_reporting(E_ALL); // Aktifkan semua level error
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../../koneksi.php';

// Pastikan guru sudah login dan NIP disimpan di session
$nip = $_SESSION['nip'] ?? ''; 
if (empty($nip)) {
    header("Location: ../../login.php");
    exit();
}

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ---------- PROSES UPDATE STATUS ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    // Verifikasi CSRF Token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['dummy_error'] = "Token CSRF tidak valid.";
        header("Location: dummy_jadwal.php");
        exit();
    }

    $id_jadwal = intval($_POST['id_jadwal'] ?? 0);
    $new_status = trim($_POST['update_status'] ?? '');
    if ($id_jadwal > 0 && in_array($new_status, ['Hadir', 'Tidak Hadir'])) {
        $sql = "UPDATE jadwal_piket SET status = ? WHERE id_jadwal = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_status, $id_jadwal);
        if ($stmt->execute()) {
            $_SESSION['dummy_success'] = "Status kehadiran telah diperbarui menjadi '$new_status'.";
        } else {
            $_SESSION['dummy_error'] = "Gagal mengupdate status kehadiran.";
        }
        $stmt->close();
    } else {
        $_SESSION['dummy_error'] = "Data tidak valid.";
    }
    header("Location: dummy_jadwal.php");
    exit();
}

// ---------- AMBIL DATA JADWAL ----------
$sql = "SELECT id_jadwal, nip, nama_guru, waktu_piket, tanggal, status 
        FROM jadwal_piket 
        ORDER BY tanggal ASC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$jadwal = [];
while ($row = $result->fetch_assoc()) {
    $jadwal[] = $row;
}
$stmt->close();

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
foreach ($jadwal as $j) {
    $tanggal = $j['tanggal'];
    $dateObj = new DateTime($tanggal);
    $month_name_eng = $dateObj->format('F');
    $year = $dateObj->format('Y');
    $full_month = translate_month($month_name_eng) . " $year";
    $day_short_eng = $dateObj->format('D'); // Mon, Tue, etc.
    $day_full = translate_day($day_short_eng); // Senin, Selasa, etc.

    if (!isset($months[$full_month])) {
        $months[$full_month] = [];
    }

    // Tambahkan jadwal ke dalam bulan yang sesuai
    $months[$full_month][] = [
        'id_jadwal' => $j['id_jadwal'],
        'nip' => $j['nip'],
        'nama_guru' => $j['nama_guru'],
        'waktu_piket' => $j['waktu_piket'],
        'tanggal' => $j['tanggal'],
        'day' => $day_full,
        'status' => $j['status']
    ];
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
        return strtotime($a['tanggal']) - strtotime($b['tanggal']);
    });
}
unset($days);

// Ambil jadwal lain yang berstatus 'Pending' dan milik guru lain
$sql_lain = "SELECT id_jadwal, nama_guru, waktu_piket, tanggal, status 
            FROM jadwal_piket 
            WHERE nip != ? AND status = 'Pending' 
            ORDER BY tanggal ASC";
$stmt_lain = $conn->prepare($sql_lain);
$stmt_lain->bind_param("s", $nip);
$stmt_lain->execute();
$result_lain = $stmt_lain->get_result();
$jadwal_lain = [];
while ($row = $result_lain->fetch_assoc()) {
    $jadwal_lain[] = $row;
}
$stmt_lain->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dummy Dashboard Jadwal Piket</title>
    <!-- SB Admin 2 & Bootstrap CSS -->
    <link href="../../assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
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
        .badge-secondary {
            background-color: #6c757d; /* Abu-abu */
            color: #fff;
        }
        /* Styling untuk tabel */
        th, td {
            vertical-align: middle !important;
            white-space: nowrap;
        }
        /* Tambahan styling sesuai sb-admin-2 */
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
        
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a href="../../logout.php" class="btn btn-danger btn-sm">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </nav>
                <!-- End of Topbar -->
                
                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Dummy Dashboard Jadwal Piket</h1>
                    
                    <!-- Menampilkan Notifikasi -->
                    <?php
                    if (isset($_SESSION['dummy_success'])) {
                        echo "<div class='alert alert-success alert-dismissible fade show' role='alert'>" 
                                . htmlspecialchars($_SESSION['dummy_success']) .
                             "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>
                                <span aria-hidden='true'>&times;</span>
                              </button></div>";
                        unset($_SESSION['dummy_success']);
                    }
                    if (isset($_SESSION['dummy_error'])) {
                        echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" 
                                . htmlspecialchars($_SESSION['dummy_error']) .
                             "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>
                                <span aria-hidden='true'>&times;</span>
                              </button></div>";
                        unset($_SESSION['dummy_error']);
                    }
                    ?>
                    
                    <!-- Form Pengajuan Tukar Jadwal -->
                    <div class="mb-4">
                        <h5><strong>Ajukan Tukar Jadwal</strong></h5>
                        <form method="POST" action="dummy_jadwal.php" class="form-inline">
                            <!-- Tambahkan CSRF Token -->
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                            
                            <div class="form-group mb-2">
                                <label for="id_jadwal_pengaju" class="sr-only">Jadwal Pengaju</label>
                                <select name="id_jadwal_pengaju" id="id_jadwal_pengaju" class="form-control mr-2" required>
                                    <option value="">-- Pilih Jadwal Anda (Pengaju) --</option>
                                    <?php
                                    // Ambil jadwal "Pending" milik guru yang sedang login
                                    $sql_pengaju = "SELECT id_jadwal, nama_guru, waktu_piket, tanggal 
                                                    FROM jadwal_piket 
                                                    WHERE nip = ? AND status = 'Pending'
                                                    ORDER BY tanggal ASC";
                                    $stmt_pengaju = $conn->prepare($sql_pengaju);
                                    $stmt_pengaju->bind_param("s", $nip);
                                    $stmt_pengaju->execute();
                                    $result_pengaju = $stmt_pengaju->get_result();
                                    while ($pengaju = $result_pengaju->fetch_assoc()) {
                                        $formatted_date = htmlspecialchars(date('d F Y', strtotime($pengaju['tanggal'])));
                                        $day = translate_day(date('D', strtotime($pengaju['tanggal'])));
                                        echo "<option value=\"{$pengaju['id_jadwal']}\">ID: {$pengaju['id_jadwal']} | {$pengaju['nama_guru']} | {$pengaju['waktu_piket']} | {$formatted_date} ({$day})</option>";
                                    }
                                    $stmt_pengaju->close();
                                    ?>
                                </select>
                            </div>
                            <div class="form-group mb-2">
                                <label for="id_jadwal_tujuan" class="sr-only">Jadwal Tujuan</label>
                                <select name="id_jadwal_tujuan" id="id_jadwal_tujuan" class="form-control mr-2" required>
                                    <option value="">-- Pilih Jadwal Tujuan --</option>
                                    <?php
                                    // Ambil jadwal "Pending" milik guru lain
                                    $sql_tujuan = "SELECT id_jadwal, nama_guru, waktu_piket, tanggal 
                                                   FROM jadwal_piket 
                                                   WHERE nip != ? AND status = 'Pending'
                                                   ORDER BY tanggal ASC";
                                    $stmt_tujuan = $conn->prepare($sql_tujuan);
                                    $stmt_tujuan->bind_param("s", $nip);
                                    $stmt_tujuan->execute();
                                    $result_tujuan = $stmt_tujuan->get_result();
                                    while ($tujuan = $result_tujuan->fetch_assoc()) {
                                        $formatted_date = htmlspecialchars(date('d F Y', strtotime($tujuan['tanggal'])));
                                        $day = translate_day(date('D', strtotime($tujuan['tanggal'])));
                                        echo "<option value=\"{$tujuan['id_jadwal']}\">ID: {$tujuan['id_jadwal']} | {$tujuan['nama_guru']} | {$tujuan['waktu_piket']} | {$formatted_date} ({$day})</option>";
                                    }
                                    $stmt_tujuan->close();
                                    ?>
                                </select>
                            </div>
                            <button type="submit" name="swap_request" class="btn btn-primary mb-2">Ajukan Tukar</button>
                        </form>
                    </div>
                    
                    <!-- Modal untuk Tukar Jadwal -->
                    <div class="modal fade" id="swapModal" tabindex="-1" role="dialog" aria-labelledby="swapModalLabel" aria-hidden="true">
                      <div class="modal-dialog" role="document">
                        <form method="POST" action="tukar_jadwal.php">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title" id="swapModalLabel">Ajukan Tukar Jadwal</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                  <span aria-hidden="true">&times;</span>
                                </button>
                              </div>
                              <div class="modal-body">
                                  <!-- Tambahkan CSRF Token -->
                                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                  <!-- ID Jadwal Pengaju -->
                                  <input type="hidden" name="id_jadwal_pengaju" id="modal_id_jadwal_pengaju" value="">
                                  <!-- Dropdown Jadwal Tujuan -->
                                  <div class="form-group">
                                      <label for="modal_id_jadwal_tujuan">Pilih Jadwal Tujuan:</label>
                                      <select name="id_jadwal_tujuan" id="modal_id_jadwal_tujuan" class="form-control" required>
                                          <option value="">-- Pilih Jadwal Tujuan --</option>
                                          <?php foreach ($jadwal_lain as $tujuan): ?>
                                              <?php
                                                  $formatted_date = htmlspecialchars(date('d F Y', strtotime($tujuan['tanggal'])));
                                                  $day = translate_day(date('D', strtotime($tujuan['tanggal'])));
                                              ?>
                                              <option value="<?= htmlspecialchars($tujuan['id_jadwal']); ?>">
                                                  ID: <?= htmlspecialchars($tujuan['id_jadwal']); ?> | 
                                                  <?= htmlspecialchars($tujuan['nama_guru']); ?> | 
                                                  <?= htmlspecialchars($tujuan['waktu_piket']); ?> | 
                                                  <?= $formatted_date; ?> (<?= $day; ?>)
                                              </option>
                                          <?php endforeach; ?>
                                      </select>
                                  </div>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                <button type="submit" name="swap_request" class="btn btn-primary">Ajukan Tukar</button>
                              </div>
                            </div>
                        </form>
                      </div>
                    </div>
                    
                    <!-- Tampilan Laporan Jadwal -->
                    <?php if (empty($jadwal)): ?>
                        <div class="alert alert-info">Tidak ada jadwal piket.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered text-center">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Id</th>
                                        <th>Nama Guru</th>
                                        <th>Waktu Piket</th>
                                        <th>Tanggal</th>
                                        <th>Bulan</th>
                                        <th>Tahun</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                        <th>Tukar Jadwal</th>
                                        <th>Status Tukar Jadwal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                        $no_total = 1; 
                                        foreach ($months as $month => $days): 
                                    ?>
                                        <tr>
                                            <td colspan="11" class="table-secondary text-left"><strong><?= htmlspecialchars($month); ?></strong></td>
                                        </tr>
                                        <?php foreach ($days as $j): ?>
                                            <tr>
                                                <td><?= $no_total++; ?></td>
                                                <td><?= htmlspecialchars($j['id_jadwal']); ?></td>
                                                <td><?= htmlspecialchars($j['nama_guru']); ?></td>
                                                <td><?= htmlspecialchars($j['waktu_piket']); ?></td>
                                                <td>
                                                    <?= htmlspecialchars(date('d F Y', strtotime($j['tanggal']))); ?><br>
                                                    <?= htmlspecialchars($j['day']); ?>
                                                </td>
                                                <td><?= htmlspecialchars(translate_month(date('F', strtotime($j['tanggal'])))); ?></td>
                                                <td><?= htmlspecialchars(date('Y', strtotime($j['tanggal']))); ?></td>
                                                <td>
                                                    <?php
                                                        $status = strtolower($j['status']);
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
                                                                echo '<span class="badge badge-secondary">-</span>';
                                                                break;
                                                        }
                                                    ?>
                                                </td>
                                                <td>
                                                    <!-- Kolom "Aksi" (Tetap seperti sebelumnya) -->
                                                    <?php if ($j['status'] === 'pending'): ?>
                                                        <form method="POST" action="dummy_jadwal.php" style="display:inline-block;">
                                                            <!-- Tambahkan CSRF Token -->
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                                            <input type="hidden" name="id_jadwal" value="<?= $j['id_jadwal']; ?>">
                                                            <button type="submit" name="update_status" value="Hadir" class="btn btn-success btn-sm">Hadir</button>
                                                        </form>
                                                        &nbsp;
                                                        <form method="POST" action="dummy_jadwal.php" style="display:inline-block;">
                                                            <!-- Tambahkan CSRF Token -->
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                                            <input type="hidden" name="id_jadwal" value="<?= $j['id_jadwal']; ?>">
                                                            <button type="submit" name="update_status" value="Tidak Hadir" class="btn btn-danger btn-sm">Tidak Hadir</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <?= "Status: " . htmlspecialchars($j['status']); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <!-- Kolom "Tukar Jadwal" -->
                                                    <?php if ($j['status'] === 'pending' && $j['nip'] === $nip): ?>
                                                        <!-- Jika jadwal milik guru yang sedang login dan statusnya 'Pending' -->
                                                        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#swapModal" data-id="<?= htmlspecialchars($j['id_jadwal']); ?>">
                                                            Tukar
                                                        </button>
                                                    <?php else: ?>
                                                        <!-- Jadwal milik guru lain atau statusnya tidak 'Pending' -->
                                                        <span class="badge badge-secondary">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <!-- Kolom "Status Tukar Jadwal" (Placeholder) -->
                                                    <span class="badge badge-secondary">-</span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- End Page Content -->
            </div>
            <!-- End Main Content -->
        </div>
        <!-- End Content Wrapper -->
    </div>
    <!-- End Page Wrapper -->

    <!-- JavaScript SB Admin 2 & Bootstrap JS -->
    <script src="../../assets/vendor/jquery/jquery.min.js"></script>
    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../../assets/js/sb-admin-2.min.js"></script>

    <!-- JavaScript untuk Mengisi Modal dengan ID Jadwal Pengaju -->
    <script>
        $('#swapModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget); // Tombol yang memicu modal
            var idJadwalPengaju = button.data('id'); // Mengambil data-id dari tombol
            var modal = $(this);
            modal.find('#modal_id_jadwal_pengaju').val(idJadwalPengaju);
        });
    </script>
</body>
</html>
