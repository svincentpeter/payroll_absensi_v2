<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../../koneksi.php';

// Pastikan guru sudah login dan NIP disimpan di session
$nip = $_SESSION['nip'] ?? ''; 
if (empty($nip)) {
    header("Location: ../../login.php");
    exit();
}

// Ambil semua jadwal dari guru yang sedang login
$sql = "SELECT id_jadwal FROM jadwal_piket WHERE nip = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nip);
$stmt->execute();
$result = $stmt->get_result();
$jadwal_guru_login = [];
while ($row = $result->fetch_assoc()) {
    $jadwal_guru_login[] = $row['id_jadwal'];
}
$stmt->close();

// Jika tidak ada jadwal, tampilkan pesan
if (empty($jadwal_guru_login)) {
    $_SESSION['swap_error'] = "Anda tidak memiliki jadwal piket.";
    header("Location: request_tukar_jadwal.php");
    exit();
}

// Ambil permintaan tukar jadwal yang masuk (id_jadwal_tujuan adalah salah satu jadwal guru login)
$placeholders = implode(',', array_fill(0, count($jadwal_guru_login), '?'));
$types = str_repeat('i', count($jadwal_guru_login));

$sql = "SELECT ptj.id, ptj.id_jadwal_pengaju, ptj.id_jadwal_tujuan, ptj.status, ptj.tanggal_permintaan, jp_pengaju.nama_guru AS nama_guru_pengaju, jp_pengaju.waktu_piket AS waktu_piket_pengaju, jp_pengaju.tanggal AS tanggal_pengaju
        FROM permintaan_tukar_jadwal ptj
        JOIN jadwal_piket jp_pengaju ON ptj.id_jadwal_pengaju = jp_pengaju.id_jadwal
        WHERE ptj.id_jadwal_tujuan IN ($placeholders) AND ptj.status = 'Pending'
        ORDER BY ptj.tanggal_permintaan DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$jadwal_guru_login);
$stmt->execute();
$result = $stmt->get_result();
$swap_requests = [];
while ($row = $result->fetch_assoc()) {
    $swap_requests[] = $row;
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

// Proses menerima atau menolak permintaan tukar jadwal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id_request'])) {
    $action = $_POST['action'];
    $id_request = intval($_POST['id_request'] ?? 0);
    
    if ($id_request > 0) {
        // Ambil permintaan tukar jadwal
        $sql = "SELECT * FROM permintaan_tukar_jadwal WHERE id = ? AND status = 'Pending'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_request);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();
        $stmt->close();
        
        if ($request) {
            $id_jadwal_pengaju = $request['id_jadwal_pengaju'];
            $id_jadwal_tujuan = $request['id_jadwal_tujuan'];
            
            if ($action === 'accept') {
                // Mulai transaksi
                $conn->begin_transaction();
                try {
                    // Ambil data jadwal_pengaju
                    $sql = "SELECT * FROM jadwal_piket WHERE id_jadwal = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $id_jadwal_pengaju);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $jadwal_pengaju = $result->fetch_assoc();
                    $stmt->close();
                    
                    // Ambil data jadwal_tujuan
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $id_jadwal_tujuan);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $jadwal_tujuan = $result->fetch_assoc();
                    $stmt->close();
                    
                    // Pastikan kedua jadwal memiliki status 'Hadir'
                    if ($jadwal_pengaju['status'] !== 'Hadir' || $jadwal_tujuan['status'] !== 'Hadir') {
                        throw new Exception("Salah satu jadwal tidak tersedia untuk ditukar.");
                    }
                    
                    // Swap NIP dan nama_guru antara jadwal_pengaju dan jadwal_tujuan
                    $sql = "UPDATE jadwal_piket SET nip = ?, nama_guru = ? WHERE id_jadwal = ?";
                    $stmt = $conn->prepare($sql);
                    // Update jadwal_pengaju dengan data jadwal_tujuan
                    $stmt->bind_param("ssi", $jadwal_tujuan['nip'], $jadwal_tujuan['nama_guru'], $id_jadwal_pengaju);
                    $stmt->execute();
                    // Update jadwal_tujuan dengan data jadwal_pengaju
                    $stmt->bind_param("ssi", $jadwal_pengaju['nip'], $jadwal_pengaju['nama_guru'], $id_jadwal_tujuan);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Update status permintaan menjadi 'Diterima'
                    $sql = "UPDATE permintaan_tukar_jadwal SET status = 'Diterima' WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $id_request);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Commit transaksi
                    $conn->commit();
                    
                    $_SESSION['swap_success'] = "Permintaan tukar jadwal telah diterima dan jadwal telah ditukar.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['swap_error'] = "Gagal menerima permintaan tukar jadwal: " . $e->getMessage();
                }
            } elseif ($action === 'reject') {
                // Update status permintaan menjadi 'Ditolak'
                $sql = "UPDATE permintaan_tukar_jadwal SET status = 'Ditolak' WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id_request);
                if ($stmt->execute()) {
                    $_SESSION['swap_success'] = "Permintaan tukar jadwal telah ditolak.";
                } else {
                    $_SESSION['swap_error'] = "Gagal menolak permintaan tukar jadwal.";
                }
                $stmt->close();
            }
        } else {
            $_SESSION['swap_error'] = "Permintaan tukar jadwal tidak ditemukan atau sudah ditanggapi.";
        }
    } else {
        $_SESSION['swap_error'] = "Data tidak valid.";
    }
    header("Location: request_tukar_jadwal.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Request Tukar Jadwal Piket</title>
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
        .badge-diterima {
            background-color: #28a745; /* Hijau */
            color: #fff;
        }
        .badge-ditolak {
            background-color: #dc3545; /* Merah */
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
                    <h1 class="h3 mb-4 text-gray-800">Request Tukar Jadwal Piket</h1>
                    
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
                    
                    <!-- Tampilan Permintaan Tukar Jadwal -->
                    <?php if (empty($swap_requests)): ?>
                        <div class="alert alert-info">Tidak ada permintaan tukar jadwal yang masuk.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered text-center">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Id Permintaan</th>
                                        <th>Nama Guru Pengaju</th>
                                        <th>Waktu Piket Guru Pengaju</th>
                                        <th>Tanggal Guru Pengaju</th>
                                        <th>Status Permintaan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                        $no = 1;
                                        foreach ($swap_requests as $request): 
                                    ?>
                                        <tr>
                                            <td><?= $no++; ?></td>
                                            <td><?= htmlspecialchars($request['id']); ?></td>
                                            <td><?= htmlspecialchars($request['nama_guru_pengaju']); ?></td>
                                            <td><?= htmlspecialchars($request['waktu_piket_pengaju']); ?></td>
                                            <td>
                                                <?= htmlspecialchars(date('d F Y', strtotime($request['tanggal_pengaju']))); ?><br>
                                                <?= htmlspecialchars(translate_day(date('D', strtotime($request['tanggal_pengaju'])))); ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-pending">Pending</span>
                                            </td>
                                            <td>
                                                <form method="POST" action="request_tukar_jadwal.php" style="display:inline-block;">
                                                    <input type="hidden" name="id_request" value="<?= $request['id']; ?>">
                                                    <input type="hidden" name="action" value="accept">
                                                    <button type="submit" class="btn btn-success btn-sm">Terima</button>
                                                </form>
                                                &nbsp;
                                                <form method="POST" action="request_tukar_jadwal.php" style="display:inline-block;">
                                                    <input type="hidden" name="id_request" value="<?= $request['id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn btn-danger btn-sm">Tolak</button>
                                                </form>
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
