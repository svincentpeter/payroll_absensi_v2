<?php
// dashboard_jadwal.php
// File lengkap setelah perbaikan

// Aktifkan error reporting untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../../koneksi.php';

// Pastikan guru sudah login dan NIP disimpan di session
$nip = $_SESSION['nip'] ?? '';
if (empty($nip)) {
    header("Location: ../../login.php");
    exit();
}

/**
 * Fungsi untuk menerjemahkan nama bulan ke Bahasa Indonesia.
 */
function translate_month_dashboard($month_eng) {
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

/**
 * Fungsi untuk menerjemahkan nama hari ke Bahasa Indonesia.
 */
function translate_day_dashboard($day_eng) {
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

// Alias fungsi agar dapat dipanggil dengan nama translate_month() dan translate_day()
if (!function_exists('translate_month')) {
    function translate_month($month_eng) {
        return translate_month_dashboard($month_eng);
    }
}
if (!function_exists('translate_day')) {
    function translate_day($day_eng) {
        return translate_day_dashboard($day_eng);
    }
}

// Ambil nama guru dari tabel anggota_sekolah (untuk ditampilkan di header)
$sql = "SELECT nama FROM anggota_sekolah WHERE nip = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nip);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$nama_pengaju = $row['nama'] ?? '';
$stmt->close();

// Ambil data jadwal_piket milik guru (untuk ditampilkan di dashboard)
$sql = "SELECT * FROM jadwal_piket WHERE nip = ? ORDER BY tanggal ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nip);
$stmt->execute();
$result = $stmt->get_result();
$jadwal = [];
while ($row = $result->fetch_assoc()) {
    $jadwal[] = $row;
}
$stmt->close();

// Ambil daftar guru (untuk dropdown tukar jadwal) selain diri sendiri
$sql = "SELECT nip, nama FROM anggota_sekolah WHERE nip != ? ORDER BY nama ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nip);
$stmt->execute();
$result = $stmt->get_result();
$daftar_guru = [];
while ($row = $result->fetch_assoc()) {
    $daftar_guru[] = $row;
}
$stmt->close();

// PROSES UPDATE STATUS (Hadir/Tidak Hadir)
// Perbaikan: gunakan nilai dari 'update_status' (bukan 'new_status')
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id_jadwal = intval($_POST['id_jadwal'] ?? 0);
    $new_status = strtolower(trim($_POST['update_status'] ?? ''));
    if ($id_jadwal > 0 && in_array($new_status, ['hadir', 'tidak hadir'])) {
        $sql = "UPDATE jadwal_piket SET status = ? WHERE id_jadwal = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_status, $id_jadwal);
        if ($stmt->execute()) {
            $_SESSION['absensi_success'] = "Status kehadiran telah diperbarui menjadi '$new_status'.";
        } else {
            $_SESSION['absensi_error'] = "Gagal mengupdate status kehadiran.";
        }
        $stmt->close();
    } else {
        $_SESSION['absensi_error'] = "Data tidak valid.";
    }
    header("Location: dashboard_jadwal.php");
    exit();
}

/** 
 * PROSES REQUEST TUKAR JADWAL
 * Guru pengaju memilih salah satu jadwal miliknya (dari dropdown) dan guru tujuan.
 * Nilai tanggal_piket diambil dari jadwal pengaju (misalnya, "2025-07-15") dan disimpan ke request.
 *
 * **Konsep baru:** Request penukaran jadwal hanya boleh diajukan kepada guru yang **tidak terdaftar** di tabel jadwal_piket.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tukar_jadwal'])) {
    $id_jadwal_pengaju = intval($_POST['id_jadwal_pengaju'] ?? 0);
    $guru_nip_tujuan = trim($_POST['guru_nip_tujuan'] ?? '');
    if ($id_jadwal_pengaju > 0 && !empty($guru_nip_tujuan)) {
        if ($nip !== $_SESSION['nip']) {
            $_SESSION['absensi_error'] = "Anda tidak memiliki akses.";
            header("Location: dashboard_jadwal.php");
            exit();
        }
        // **Cek tambahan:** Pastikan guru tujuan tidak terdaftar di tabel jadwal_piket
        $sql_check_reg = "SELECT COUNT(*) AS total FROM jadwal_piket WHERE nip = ?";
        $stmt = $conn->prepare($sql_check_reg);
        $stmt->bind_param("s", $guru_nip_tujuan);
        $stmt->execute();
        $result_reg = $stmt->get_result();
        $data_reg = $result_reg->fetch_assoc();
        $stmt->close();
        if ($data_reg['total'] > 0) {
            $_SESSION['absensi_error'] = "Request penukaran jadwal hanya bisa di ajukan kepada Guru yang tidak terdaftar di jadwal piket!";
            header("Location: dashboard_jadwal.php");
            exit();
        }
        
        // Cari jadwal tujuan pending milik guru tujuan (jika ada)
        $sql_tujuan = "SELECT id_jadwal FROM jadwal_piket WHERE nip = ? AND status = 'pending' ORDER BY tanggal ASC LIMIT 1";
        $stmt = $conn->prepare($sql_tujuan);
        $stmt->bind_param("s", $guru_nip_tujuan);
        $stmt->execute();
        $result_tujuan = $stmt->get_result();
        $data_tujuan = $result_tujuan->fetch_assoc();
        $stmt->close();
        // Jika tidak ada, set ke NULL
        $id_jadwal_tujuan = $data_tujuan ? $data_tujuan['id_jadwal'] : NULL;
        // Ambil tanggal dan waktu_piket dari jadwal pengaju (sebagai tanggal request tukar)
        $sql_jadwal = "SELECT tanggal, waktu_piket FROM jadwal_piket WHERE id_jadwal = ?";
        $stmt = $conn->prepare($sql_jadwal);
        $stmt->bind_param("i", $id_jadwal_pengaju);
        $stmt->execute();
        $result_jadwal = $stmt->get_result();
        $jadwal_pengaju = $result_jadwal->fetch_assoc();
        $stmt->close();
        if (!$jadwal_pengaju) {
            $_SESSION['absensi_error'] = "Data jadwal pengaju tidak ditemukan.";
            header("Location: dashboard_jadwal.php");
            exit();
        }
        $tanggal_request = $jadwal_pengaju['tanggal'];
        $waktu_piket = $jadwal_pengaju['waktu_piket'];
        
        // Cek apakah request sudah ada untuk jadwal ini
        if ($id_jadwal_tujuan === NULL) {
            $sql_check = "SELECT COUNT(*) AS total FROM permintaan_tukar_jadwal WHERE id_jadwal_pengaju = ? AND id_jadwal_tujuan IS NULL";
            $stmt = $conn->prepare($sql_check);
            $stmt->bind_param("i", $id_jadwal_pengaju);
        } else {
            $sql_check = "SELECT COUNT(*) AS total FROM permintaan_tukar_jadwal WHERE id_jadwal_pengaju = ? AND id_jadwal_tujuan = ?";
            $stmt = $conn->prepare($sql_check);
            $stmt->bind_param("ii", $id_jadwal_pengaju, $id_jadwal_tujuan);
        }
        $stmt->execute();
        $result_check = $stmt->get_result();
        $count = $result_check->fetch_assoc()['total'];
        $stmt->close();
        if ($count == 0) {
            // Simpan request tukar jadwal dengan nilai tanggal_piket diambil dari jadwal pengaju
            $sql_insert = "INSERT INTO permintaan_tukar_jadwal 
                (id_jadwal_pengaju, id_jadwal_tujuan, nip_tujuan, status, nip_pengaju, nama_pengaju, tanggal_permintaan, tanggal_piket)
                VALUES (?, ?, ?, 'Pending', ?, ?, NOW(), ?)";
            $stmt = $conn->prepare($sql_insert);
            $stmt->bind_param("iissss", $id_jadwal_pengaju, $id_jadwal_tujuan, $guru_nip_tujuan, $nip, $nama_pengaju, $tanggal_request);
            if ($stmt->execute()) {
                $_SESSION['absensi_success'] = "Request tukar jadwal telah dikirim.";
            } else {
                $_SESSION['absensi_error'] = "Gagal mengirim request tukar jadwal.";
            }
            $stmt->close();
        } else {
            $_SESSION['absensi_error'] = "Request tukar jadwal sudah ada.";
        }
    } else {
        $_SESSION['absensi_error'] = "Data request tidak lengkap.";
    }
    header("Location: dashboard_jadwal.php");
    exit();
}

/**
 * PROSES RESPON REQUEST TUKAR JADWAL (Accept / Reject)
 * Hanya guru yang menjadi tujuan (nip_tujuan) yang boleh merespon.
 * Jika menerima dan id_jadwal_tujuan NULL, maka sistem akan mengupdate record jadwal_pengaju untuk menggantikan guru pengaju dengan guru tujuan.
 * Jika id_jadwal_tujuan tidak NULL, maka dilakukan swap (pertukaran) jadwal.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id_request'])) {
    $action = $_POST['action'];
    $id_request = intval($_POST['id_request'] ?? 0);
    if ($id_request > 0) {
        $sql = "SELECT * FROM permintaan_tukar_jadwal WHERE id = ? AND status = 'Pending'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_request);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();
        $stmt->close();
        if ($request) {
            // Pastikan hanya guru yang dituju (nip_tujuan) yang dapat merespon
            if ($request['nip_tujuan'] !== $nip) {
                $_SESSION['absensi_error'] = "Anda tidak memiliki akses untuk merespon request ini.";
                header("Location: dashboard_jadwal.php");
                exit();
            }
            if ($action === 'accept') {
                if ($request['id_jadwal_tujuan'] === NULL) {
                    // Guru tujuan belum memiliki jadwal pending:
                    // Lakukan update pada jadwal_pengaju untuk mengganti nip dan nama_guru dengan guru tujuan.
                    $jadwal_id = $request['id_jadwal_pengaju'];
                    $sql_jp = "SELECT * FROM jadwal_piket WHERE id_jadwal = ?";
                    $stmt = $conn->prepare($sql_jp);
                    $stmt->bind_param("i", $jadwal_id);
                    $stmt->execute();
                    $result_jp = $stmt->get_result();
                    $jadwal_pengaju = $result_jp->fetch_assoc();
                    $stmt->close();
                    if (!$jadwal_pengaju) {
                        $_SESSION['absensi_error'] = "Data jadwal pengaju tidak ditemukan.";
                        header("Location: dashboard_jadwal.php");
                        exit();
                    }
                    // Ambil nama guru tujuan (dari database)
                    $sql_nama = "SELECT nama FROM anggota_sekolah WHERE nip = ?";
                    $stmt = $conn->prepare($sql_nama);
                    $stmt->bind_param("s", $request['nip_tujuan']);
                    $stmt->execute();
                    $result_nama = $stmt->get_result();
                    $data_nama = $result_nama->fetch_assoc();
                    $nama_tujuan = $data_nama['nama'] ?? '';
                    $stmt->close();
                    if (empty($nama_tujuan)) {
                        $_SESSION['absensi_error'] = "Nama guru tujuan tidak ditemukan.";
                        header("Location: dashboard_jadwal.php");
                        exit();
                    }
                    // Update jadwal_pengaju: ganti nip dan nama_guru menjadi guru tujuan
                    $sql_update = "UPDATE jadwal_piket SET nip = ?, nama_guru = ? WHERE id_jadwal = ?";
                    $stmt = $conn->prepare($sql_update);
                    $stmt->bind_param("ssi", $nip, $nama_tujuan, $jadwal_id);
                    if ($stmt->execute()) {
                        $stmt->close();
                        // Update request: set id_jadwal_tujuan = jadwal_id dan status jadi Diterima
                        $sql_update_req = "UPDATE permintaan_tukar_jadwal SET id_jadwal_tujuan = ?, status = 'Diterima' WHERE id = ?";
                        $stmt = $conn->prepare($sql_update_req);
                        $stmt->bind_param("ii", $jadwal_id, $id_request);
                        $stmt->execute();
                        $stmt->close();
                        $_SESSION['absensi_success'] = "Request diterima. Jadwal pada tanggal " . date('d F Y', strtotime($jadwal_pengaju['tanggal'])) . " kini telah dipindahkan ke Anda.";
                    } else {
                        $_SESSION['absensi_error'] = "Gagal mengupdate jadwal untuk guru tujuan.";
                    }
                } else {
                    // Jika id_jadwal_tujuan tidak NULL, lakukan swap jadwal antara guru pengaju dan guru tujuan
                    $id_pengaju = $request['id_jadwal_pengaju'];
                    $id_tujuan = $request['id_jadwal_tujuan'];
                    $conn->begin_transaction();
                    try {
                        $sql_update = "UPDATE jadwal_piket SET nip = ?, nama_guru = ? WHERE id_jadwal = ?";
                        $stmt = $conn->prepare($sql_update);
                        // Swap: update jadwal pengaju dengan nilai nip guru tujuan
                        $stmt->bind_param("ssi", $request['nip_tujuan'], '', $id_pengaju);
                        $stmt->execute();
                        // Update jadwal tujuan dengan nilai nip guru pengaju (yaitu $nip)
                        $stmt->bind_param("ssi", $nip, '', $id_tujuan);
                        $stmt->execute();
                        $stmt->close();
                        $sql_update_status = "UPDATE permintaan_tukar_jadwal SET status = 'Diterima' WHERE id = ?";
                        $stmt = $conn->prepare($sql_update_status);
                        $stmt->bind_param("i", $id_request);
                        $stmt->execute();
                        $stmt->close();
                        $conn->commit();
                        $_SESSION['absensi_success'] = "Request tukar jadwal telah diterima dan jadwal telah ditukar.";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $_SESSION['absensi_error'] = "Gagal menerima request tukar jadwal: " . $e->getMessage();
                    }
                }
            } elseif ($action === 'reject') {
                $sql = "UPDATE permintaan_tukar_jadwal SET status = 'Ditolak' WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id_request);
                if ($stmt->execute()) {
                    $_SESSION['absensi_success'] = "Request tukar jadwal telah ditolak.";
                } else {
                    $_SESSION['absensi_error'] = "Gagal menolak request tukar jadwal.";
                }
                $stmt->close();
            }
        } else {
            $_SESSION['absensi_error'] = "Request tukar jadwal tidak ditemukan atau sudah diproses.";
        }
    } else {
        $_SESSION['absensi_error'] = "Data respon tidak valid.";
    }
    header("Location: dashboard_jadwal.php");
    exit();
}

/**
 * PENGAMBILAN DATA UNTUK TAMPILAN (Dashboard)
 */
// Ambil data jadwal_piket untuk guru (untuk ditampilkan di dashboard)
$sql = "SELECT * FROM jadwal_piket WHERE nip = ? ORDER BY tanggal ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nip);
$stmt->execute();
$result = $stmt->get_result();
$jadwal = [];
while ($row = $result->fetch_assoc()) {
    $dateObj = new DateTime($row['tanggal']);
    $row['day'] = translate_day_dashboard($dateObj->format('D'));
    $jadwal[] = $row;
}
$stmt->close();

// Ambil data request tukar jadwal untuk guru tujuan
if (!empty($jadwal)) {
    $jadwal_ids = array_map(function($j) { return $j['id_jadwal']; }, $jadwal);
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Jadwal Piket</title>
    <link href="../../assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="../../assets/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .badge-pending { background-color: #ffc107; color: #212529; }
        .badge-tidak-hadir { background-color: #dc3545; color: #fff; }
        .badge-hadir { background-color: #28a745; color: #fff; }
        .badge-diterima { background-color: #28a745; color: #fff; }
        .badge-ditolak { background-color: #dc3545; color: #fff; }
        .badge-secondary { background-color: #6c757d; color: #fff; }
        .badge-info { background-color: #17a2b8; color: #fff; }
        th, td { vertical-align: middle !important; white-space: nowrap; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .table thead th { background-color: #f8f9fc; color: #5a5c69; border-bottom: 2px solid #e3e6f0; }
        .table tbody tr:nth-child(even) { background-color: #f8f9fc; }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a href="../../logout.php" class="btn btn-danger btn-sm">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </nav>
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Dashboard Jadwal Piket </h1>
                    
                    <!-- Notifikasi -->
                    <?php if (isset($_SESSION['absensi_success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['absensi_success']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['absensi_success']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['absensi_error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['absensi_error']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['absensi_error']); ?>
                    <?php endif; ?>
                    
                    <!-- Tampilan Jadwal Piket Saya -->
                    <div class="table-responsive mb-4">
                        <h4>Jadwal Piket Saya</h4>
                        <table class="table table-bordered text-center">
                            <thead>
                                <tr>
                                    <th>Nama Guru</th>
                                    <th>Waktu Piket</th>
                                    <th>Tanggal</th>
                                    <th>Bulan</th>
                                    <th>Tahun</th>
                                    <th>Status Kehadiran</th>
                                    <th>Aksi</th>
                                    <th>Tukar Jadwal</th>
                                    <th>Status Request</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Kelompokkan jadwal berdasarkan bulan
                                $months = [];
                                foreach ($jadwal as $j) {
                                    $dateObj = new DateTime($j['tanggal']);
                                    $full_month = translate_month_dashboard($dateObj->format('F')) . " " . $dateObj->format('Y');
                                    $j['day'] = translate_day_dashboard($dateObj->format('D'));
                                    $months[$full_month][] = $j;
                                }
                                ksort($months);
                                foreach ($months as $month => $days):
                                ?>
                                    <tr>
                                        <td colspan="9" class="table-secondary text-left"><strong><?= htmlspecialchars($month); ?></strong></td>
                                    </tr>
                                    <?php foreach ($days as $j): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($j['nama_guru']); ?></td>
                                            <td><?= htmlspecialchars($j['waktu_piket']); ?></td>
                                            <td><?= htmlspecialchars(date('d F Y', strtotime($j['tanggal']))); ?><br><?= htmlspecialchars($j['day']); ?></td>
                                            <td><?= htmlspecialchars(translate_month($j['bulan'])); ?></td>
                                            <td><?= htmlspecialchars($j['tahun']); ?></td>
                                            <td>
                                                <?php
                                                $status = strtolower($j['status']);
                                                if ($status == 'pending') {
                                                    echo '<span class="badge badge-pending">Pending</span>';
                                                } elseif ($status == 'tidak hadir') {
                                                    echo '<span class="badge badge-tidak-hadir">Tidak Hadir</span>';
                                                } elseif ($status == 'hadir') {
                                                    echo '<span class="badge badge-hadir">Hadir</span>';
                                                } else {
                                                    echo '<span class="badge badge-secondary">-</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($j['status'] === 'pending'): ?>
                                                    <form method="POST" action="dashboard_jadwal.php" style="display:inline-block;">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                                        <input type="hidden" name="id_jadwal" value="<?= $j['id_jadwal']; ?>">
                                                        <button type="submit" name="update_status" value="hadir" class="btn btn-success btn-sm">Hadir</button>
                                                    </form>
                                                    &nbsp;
                                                    <form method="POST" action="dashboard_jadwal.php" style="display:inline-block;">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                                        <input type="hidden" name="id_jadwal" value="<?= $j['id_jadwal']; ?>">
                                                        <button type="submit" name="update_status" value="tidak hadir" class="btn btn-danger btn-sm">Tidak Hadir</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($j['status'] === 'pending' && $j['nip'] === $nip): ?>
                                                    <form method="POST" action="dashboard_jadwal.php" style="display:inline-block;">
                                                        <input type="hidden" name="id_jadwal_pengaju" value="<?= $j['id_jadwal']; ?>">
                                                        <div class="form-group">
                                                            <select name="guru_nip_tujuan" class="form-control form-control-sm" required>
                                                                <option value="" disabled selected>-- Pilih Guru --</option>
                                                                <?php foreach ($daftar_guru as $guru): ?>
                                                                    <option value="<?= htmlspecialchars($guru['nip']); ?>">
                                                                        <?= htmlspecialchars($guru['nama']) . " (" . htmlspecialchars($guru['nip']) . ")"; ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <br>
                                                        <button type="submit" name="tukar_jadwal" class="btn btn-primary btn-sm">Tukar Jadwal</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $sql_req = "SELECT status FROM permintaan_tukar_jadwal WHERE id_jadwal_pengaju = ? ORDER BY tanggal_permintaan DESC LIMIT 1";
                                                    $stmt_req = $conn->prepare($sql_req);
                                                    $stmt_req->bind_param("i", $j['id_jadwal']);
                                                    $stmt_req->execute();
                                                    $result_req = $stmt_req->get_result();
                                                    if ($result_req->num_rows > 0) {
                                                        $row_req = $result_req->fetch_assoc();
                                                        echo '<span class="badge badge-info">' . htmlspecialchars($row_req['status']) . '</span>';
                                                    } else {
                                                        echo '<span class="badge badge-secondary">-</span>';
                                                    }
                                                    $stmt_req->close();
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                   
                    
                </div><!-- End Container Fluid -->
            </div><!-- End Content -->
        </div><!-- End Content Wrapper -->
    </div><!-- End Wrapper -->
    
    <!-- (Opsional) Modal untuk Menanggapi Request Tukar Jadwal (Jika diperlukan) -->
    <div class="modal fade" id="respondSwapModal" tabindex="-1" aria-labelledby="respondSwapModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form method="POST" action="dashboard_jadwal.php">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="respondSwapModalLabel">Tanggapi Request Tukar Jadwal</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="id_request" id="id_request">
              <p>Apakah Anda ingin menerima atau menolak request tukar jadwal ini?</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
              <button type="submit" name="respond_swap" value="Diterima" class="btn btn-success">Terima</button>
              <button type="submit" name="respond_swap" value="Ditolak" class="btn btn-danger">Tolak</button>
            </div>
          </div>
        </form>
      </div>
    </div>
    
    <!-- JavaScript SB Admin 2 & Bootstrap JS -->
    <script src="../../assets/vendor/jquery/jquery.min.js"></script>
    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../../assets/js/sb-admin-2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Jika menggunakan modal untuk respon, set nilai id_request pada modal
            $('.respond-swap-btn').on('click', function() {
                var id_request = $(this).data('id');
                $('#id_request').val(id_request);
            });
        });
    </script>
</body>
</html>
