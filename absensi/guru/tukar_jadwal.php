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

// Ambil id_jadwal_pengaju dari GET atau POST
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id_jadwal_pengaju = intval($_GET['id_jadwal_pengaju'] ?? 0);
} else {
    $id_jadwal_pengaju = intval($_POST['id_jadwal_pengaju'] ?? 0);
}

if ($id_jadwal_pengaju <= 0) {
    $_SESSION['swap_error'] = "Data jadwal pengaju tidak valid.";
    header("Location: dummy_jadwal.php");
    exit();
}

// Verifikasi bahwa jadwal pengaju milik guru yang sedang login dan berstatus 'Pending'
$sql = "SELECT * FROM jadwal_piket WHERE id_jadwal = ? AND nip = ? AND status = 'Pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $id_jadwal_pengaju, $nip);
$stmt->execute();
$result = $stmt->get_result();
$jadwal_pengaju = $result->fetch_assoc();
$stmt->close();

if (!$jadwal_pengaju) {
    $_SESSION['swap_error'] = "Jadwal pengaju tidak ditemukan, bukan milik Anda, atau sudah ditandai hadir/tidak hadir.";
    header("Location: dummy_jadwal.php");
    exit();
}

// Ambil data jadwal guru lain yang bisa dipilih untuk ditukar (hanya 'Pending')
$sql = "SELECT jp.id_jadwal, jp.nama_guru, jp.waktu_piket, jp.tanggal, jp.status 
        FROM jadwal_piket jp
        WHERE jp.nip != ? AND jp.status = 'Pending'
        ORDER BY jp.tanggal ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nip);
$stmt->execute();
$result = $stmt->get_result();
$jadwal_lain = [];
while ($row = $result->fetch_assoc()) {
    $jadwal_lain[] = $row;
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

// Proses pengajuan tukar jadwal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['swap_request'])) {
    // Verifikasi CSRF Token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['swap_error'] = "Token CSRF tidak valid.";
        header("Location: dummy_jadwal.php");
        exit();
    }

    $id_jadwal_tujuan = intval($_POST['id_jadwal_tujuan'] ?? 0);
    
    // Validasi
    if ($id_jadwal_tujuan > 0 && $id_jadwal_tujuan !== $id_jadwal_pengaju) {
        // Pastikan jadwal tujuan ada dan statusnya 'Pending'
        $sql = "SELECT * FROM jadwal_piket WHERE id_jadwal = ? AND status = 'Pending'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_jadwal_tujuan);
        $stmt->execute();
        $result = $stmt->get_result();
        $jadwal_tujuan = $result->fetch_assoc();
        $stmt->close();
        
        if ($jadwal_tujuan) {
            // Cek apakah sudah ada permintaan tukar antara dua jadwal ini yang masih 'Pending'
            $sql = "SELECT * FROM permintaan_tukar_jadwal 
                    WHERE id_jadwal_pengaju = ? AND id_jadwal_tujuan = ? AND status = 'Pending'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $id_jadwal_pengaju, $id_jadwal_tujuan);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $_SESSION['swap_error'] = "Anda sudah mengajukan permintaan tukar jadwal dengan jadwal ini.";
            } else {
                // Insert permintaan tukar jadwal
                $sql = "INSERT INTO permintaan_tukar_jadwal (id_jadwal_pengaju, id_jadwal_tujuan) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $id_jadwal_pengaju, $id_jadwal_tujuan);
                // Logging untuk debugging
                error_log("Mengajukan tukar jadwal: Pengaju ID = $id_jadwal_pengaju, Tujuan ID = $id_jadwal_tujuan");
                if ($stmt->execute()) {
                    $_SESSION['swap_success'] = "Permintaan tukar jadwal berhasil diajukan.";
                    error_log("Insert permintaan tukar jadwal berhasil.");
                } else {
                    $_SESSION['swap_error'] = "Gagal mengajukan permintaan tukar jadwal.";
                    error_log("Insert permintaan tukar jadwal gagal: " . $stmt->error);
                }
                $stmt->close();
            }
        } else {
            $_SESSION['swap_error'] = "Jadwal tujuan tidak ditemukan atau tidak tersedia untuk ditukar.";
        }
    } else {
        $_SESSION['swap_error'] = "Data tidak valid atau Anda mencoba menukar jadwal dengan diri sendiri.";
    }
    header("Location: dummy_jadwal.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tukar Jadwal Piket</title>
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
                    <h1 class="h3 mb-4 text-gray-800">Tukar Jadwal Piket</h1>
                    
                    <!-- Menampilkan Notifikasi -->
                    <?php
                    if (isset($_SESSION['swap_success'])) {
                        echo "<div class='alert alert-success alert-dismissible fade show' role='alert'>" 
                                . htmlspecialchars($_SESSION['swap_success']) .
                             "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>
                                <span aria-hidden='true'>&times;</span>
                              </button></div>";
                        unset($_SESSION['swap_success']);
                    }
                    if (isset($_SESSION['swap_error'])) {
                        echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" 
                                . htmlspecialchars($_SESSION['swap_error']) .
                             "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>
                                <span aria-hidden='true'>&times;</span>
                              </button></div>";
                        unset($_SESSION['swap_error']);
                    }
                    ?>
                    
                    <!-- Informasi Jadwal Pengaju -->
                    <div class="mb-4">
                        <h5><strong>Jadwal Anda:</strong></h5>
                        <p>
                            <strong>Nama Guru:</strong> <?= htmlspecialchars($jadwal_pengaju['nama_guru']); ?><br>
                            <strong>Waktu Piket:</strong> <?= htmlspecialchars($jadwal_pengaju['waktu_piket']); ?><br>
                            <strong>Tanggal:</strong> <?= htmlspecialchars(date('d F Y', strtotime($jadwal_pengaju['tanggal']))); ?> (<?= htmlspecialchars(translate_day(date('D', strtotime($jadwal_pengaju['tanggal'])))); ?>)
                        </p>
                    </div>
                    
                    <!-- Tampilan Jadwal Guru Lain -->
                    <?php if (empty($jadwal_lain)): ?>
                        <div class="alert alert-info">Tidak ada jadwal guru lain untuk ditukar.</div>
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
                                        foreach ($jadwal_lain as $j): 
                                    ?>
                                        <tr>
                                            <td><?= $no_total++; ?></td>
                                            <td><?= htmlspecialchars($j['id_jadwal']); ?></td>
                                            <td><?= htmlspecialchars($j['nama_guru']); ?></td>
                                            <td><?= htmlspecialchars($j['waktu_piket']); ?></td>
                                            <td>
                                                <?= htmlspecialchars(date('d F Y', strtotime($j['tanggal']))); ?><br>
                                                <?= htmlspecialchars(translate_day(date('D', strtotime($j['tanggal'])))); ?>
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
                                                        <input type="hidden" name="id_jadwal" value="<?= $j['id_jadwal']; ?>">
                                                        <button type="submit" name="update_status" value="hadir" class="btn btn-success btn-sm">Hadir</button>
                                                    </form>
                                                    &nbsp;
                                                    <form method="POST" action="dummy_jadwal.php" style="display:inline-block;">
                                                        <input type="hidden" name="id_jadwal" value="<?= $j['id_jadwal']; ?>">
                                                        <button type="submit" name="update_status" value="tidak hadir" class="btn btn-danger btn-sm">Tidak Hadir</button>
                                                    </form>
                                                <?php else: ?>
                                                    <?= "Status: " . htmlspecialchars($j['status']); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <!-- Kolom "Tukar Jadwal" -->
                                                <form method="POST" action="tukar_jadwal.php" style="display:inline-block;">
                                                    <input type="hidden" name="id_jadwal_pengaju" value="<?= $id_jadwal_pengaju; ?>">
                                                    <input type="hidden" name="id_jadwal_tujuan" value="<?= $j['id_jadwal']; ?>">
                                                    <!-- Tambahkan CSRF Token -->
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                                    <button type="submit" name="swap_request" class="btn btn-primary btn-sm">Tukar</button>
                                                </form>
                                            </td>
                                            <td>
                                                <!-- Kolom "Status Tukar Jadwal" (Placeholder) -->
                                                <span class="badge badge-secondary">-</span>
                                            </td>
                                        </tr>
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
</body>
</html>
