<?php
// Mengaktifkan error reporting untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../../koneksi.php';

// ---------- PROSES UPDATE STATUS ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id_jadwal = intval($_POST['id_jadwal'] ?? 0);
    // Peroleh status baru dari tombol (nilai tombol dikirim sebagai "new_status")
    $new_status = trim($_POST['new_status'] ?? '');
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

// ---------- PROSES PERMINTAAN TUKAR JADWAL ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tukar_jadwal'])) {
    $id_jadwal_pengaju = intval($_POST['id_jadwal_pengaju'] ?? 0);
    $id_jadwal_tujuan = intval($_POST['id_jadwal_tujuan'] ?? 0);

    if ($id_jadwal_pengaju > 0 && $id_jadwal_tujuan > 0) {
        // Verifikasi bahwa jadwal pengaju milik guru yang sedang login
        $nip = $_SESSION['nip'] ?? '';
        if (empty($nip)) {
            $_SESSION['absensi_error'] = "Anda tidak memiliki akses.";
            header("Location: dashboard_jadwal.php");
            exit();
        }

        // Cek apakah jadwal tujuan milik guru tujuan
        $sql_tujuan = "SELECT nip FROM jadwal_piket WHERE id_jadwal = ?";
        $stmt_tujuan = $conn->prepare($sql_tujuan);
        $stmt_tujuan->bind_param("i", $id_jadwal_tujuan);
        $stmt_tujuan->execute();
        $result_tujuan = $stmt_tujuan->get_result();
        $jadwal_tujuan = $result_tujuan->fetch_assoc();
        $stmt_tujuan->close();

        if ($jadwal_tujuan && !empty($jadwal_tujuan['nip'])) {
            $nip_tujuan = $jadwal_tujuan['nip'];

            // Pastikan jadwal pengaju dan tujuan belum ada permintaan tukar
            $sql_check = "SELECT COUNT(*) AS total FROM permintaan_tukar_jadwal WHERE id_jadwal_pengaju = ? AND id_jadwal_tujuan = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("ii", $id_jadwal_pengaju, $id_jadwal_tujuan);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $count = $result_check->fetch_assoc()['total'];
            $stmt_check->close();

            if ($count == 0) {
                // Simpan permintaan tukar jadwal
                $sql_insert = "INSERT INTO permintaan_tukar_jadwal (id_jadwal_pengaju, id_jadwal_tujuan, status) VALUES (?, ?, 'Pending')";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("ii", $id_jadwal_pengaju, $id_jadwal_tujuan);
                if ($stmt_insert->execute()) {
                    $_SESSION['absensi_success'] = "Permintaan tukar jadwal telah dikirim.";
                } else {
                    $_SESSION['absensi_error'] = "Gagal mengirim permintaan tukar jadwal.";
                }
                $stmt_insert->close();
            } else {
                $_SESSION['absensi_error'] = "Permintaan tukar jadwal sudah ada.";
            }
        } else {
            $_SESSION['absensi_error'] = "Jadwal tujuan tidak valid atau tidak milik guru yang dipilih.";
        }
    } else {
        $_SESSION['absensi_error'] = "Data permintaan tukar jadwal tidak lengkap.";
    }
    header("Location: dashboard_jadwal.php");
    exit();
}

// ---------- PROSES TANGGAPI PERMINTAAN TUKAR JADWAL ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['respond_swap'])) {
    $id_permintaan = intval($_POST['id_permintaan'] ?? 0);
    $response = trim($_POST['respond_swap'] ?? '');

    if ($id_permintaan > 0 && in_array($response, ['Diterima', 'Ditolak'])) {
        // Ambil detail permintaan
        $sql_detail = "SELECT * FROM permintaan_tukar_jadwal WHERE id = ?";
        $stmt_detail = $conn->prepare($sql_detail);
        $stmt_detail->bind_param("i", $id_permintaan);
        $stmt_detail->execute();
        $result_detail = $stmt_detail->get_result();
        $permintaan = $result_detail->fetch_assoc();
        $stmt_detail->close();

        if ($permintaan) {
            if ($permintaan['status'] === 'Pending') {
                if ($response === 'Diterima') {
                    // Tukar jadwal
                    $id_pengaju = $permintaan['id_jadwal_pengaju'];
                    $id_tujuan = $permintaan['id_jadwal_tujuan'];

                    // Ambil data jadwal pengaju
                    $sql_jp = "SELECT * FROM jadwal_piket WHERE id_jadwal = ?";
                    $stmt_jp = $conn->prepare($sql_jp);
                    $stmt_jp->bind_param("i", $id_pengaju);
                    $stmt_jp->execute();
                    $result_jp = $stmt_jp->get_result();
                    $jadwal_pengaju = $result_jp->fetch_assoc();
                    $stmt_jp->close();

                    // Ambil data jadwal tujuan
                    $sql_jt = "SELECT * FROM jadwal_piket WHERE id_jadwal = ?";
                    $stmt_jt = $conn->prepare($sql_jt);
                    $stmt_jt->bind_param("i", $id_tujuan);
                    $stmt_jt->execute();
                    $result_jt = $stmt_jt->get_result();
                    $jadwal_tujuan = $result_jt->fetch_assoc();
                    $stmt_jt->close();

                    if ($jadwal_pengaju && $jadwal_tujuan) {
                        // Mulai transaksi
                        $conn->begin_transaction();
                        try {
                            // Tukar data jadwal pengaju dan tujuan
                            $sql_update_pengaju = "UPDATE jadwal_piket SET nip = ?, waktu_piket = ? WHERE id_jadwal = ?";
                            $stmt_update_pengaju = $conn->prepare($sql_update_pengaju);
                            $stmt_update_pengaju->bind_param("ssi", $jadwal_tujuan['nip'], $jadwal_tujuan['waktu_piket'], $id_pengaju);
                            $stmt_update_pengaju->execute();
                            $stmt_update_pengaju->close();

                            $sql_update_tujuan = "UPDATE jadwal_piket SET nip = ?, waktu_piket = ? WHERE id_jadwal = ?";
                            $stmt_update_tujuan = $conn->prepare($sql_update_tujuan);
                            $stmt_update_tujuan->bind_param("ssi", $jadwal_pengaju['nip'], $jadwal_pengaju['waktu_piket'], $id_tujuan);
                            $stmt_update_tujuan->execute();
                            $stmt_update_tujuan->close();

                            // Update status permintaan menjadi Diterima
                            $sql_update_status = "UPDATE permintaan_tukar_jadwal SET status = 'Diterima' WHERE id = ?";
                            $stmt_update_status = $conn->prepare($sql_update_status);
                            $stmt_update_status->bind_param("i", $id_permintaan);
                            $stmt_update_status->execute();
                            $stmt_update_status->close();

                            // Commit transaksi
                            $conn->commit();
                            $_SESSION['absensi_success'] = "Permintaan tukar jadwal telah diterima dan jadwal berhasil ditukar.";
                        } catch (Exception $e) {
                            // Rollback transaksi jika ada error
                            $conn->rollback();
                            $_SESSION['absensi_error'] = "Terjadi kesalahan saat menukar jadwal: " . $e->getMessage();
                        }
                    } else {
                        $_SESSION['absensi_error'] = "Data jadwal tidak ditemukan.";
                    }
                } elseif ($response === 'Ditolak') {
                    // Update status permintaan menjadi Ditolak
                    $sql_update_status = "UPDATE permintaan_tukar_jadwal SET status = 'Ditolak' WHERE id = ?";
                    $stmt_update_status = $conn->prepare($sql_update_status);
                    $stmt_update_status->bind_param("i", $id_permintaan);
                    $stmt_update_status->execute();
                    $stmt_update_status->close();

                    $_SESSION['absensi_success'] = "Permintaan tukar jadwal telah ditolak.";
                }
            } else {
                $_SESSION['absensi_error'] = "Permintaan tukar jadwal sudah ditanggapi.";
            }
        } else {
            $_SESSION['absensi_error'] = "Permintaan tukar jadwal tidak ditemukan.";
        }
    } else {
        $_SESSION['absensi_error'] = "Data respon tidak valid.";
    }
    header("Location: dashboard_jadwal.php");
    exit();
}

// Pastikan guru sudah login dan NIP disimpan di session
$nip = $_SESSION['nip'] ?? ''; 
if (empty($nip)) {
    header("Location: ../../login.php");
    exit();
}

// Ambil data jadwal untuk guru tersebut (urutkan berdasarkan tanggal)
$sql = "SELECT id_jadwal, waktu_piket, tanggal, status FROM jadwal_piket WHERE nip = ? ORDER BY tanggal ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nip);
$stmt->execute();
$result = $stmt->get_result();
$jadwal = [];
while ($row = $result->fetch_assoc()) {
    $jadwal[] = $row;
}
$stmt->close();

// Dapatkan tanggal hari ini untuk validasi update status
$currentDate = date("Y-m-d");

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

// Ambil semua guru untuk dropdown "Tukar Jadwal"
$sql_guru = "SELECT DISTINCT jp.id_jadwal, as_sekolah.nip, as_sekolah.nama 
            FROM anggota_sekolah as_sekolah 
            JOIN jadwal_piket jp ON as_sekolah.nip = jp.nip 
            WHERE as_sekolah.nip != ? 
            ORDER BY as_sekolah.nama ASC";
$stmt_guru = $conn->prepare($sql_guru);
$stmt_guru->bind_param("s", $nip);
$stmt_guru->execute();
$result_guru = $stmt_guru->get_result();
$daftar_guru = [];
while ($row = $result_guru->fetch_assoc()) {
    $daftar_guru[] = $row;
}
$stmt_guru->close();

// Ambil permintaan tukar jadwal yang diajukan oleh guru ini
$sql_permintaan = "SELECT permintaan_tukar_jadwal.*, 
                               jp.tanggal AS tanggal_pengaju, jp.waktu_piket AS waktu_pengaju, 
                               jt.tanggal AS tanggal_tujuan, jt.waktu_piket AS waktu_tujuan, 
                               as_pengaju.nama AS nama_pengaju, as_tujuan.nama AS nama_tujuan
                        FROM permintaan_tukar_jadwal 
                        JOIN jadwal_piket AS jp ON permintaan_tukar_jadwal.id_jadwal_pengaju = jp.id_jadwal 
                        JOIN jadwal_piket AS jt ON permintaan_tukar_jadwal.id_jadwal_tujuan = jt.id_jadwal 
                        JOIN anggota_sekolah AS as_pengaju ON jp.nip = as_pengaju.nip 
                        JOIN anggota_sekolah AS as_tujuan ON jt.nip = as_tujuan.nip 
                        WHERE jp.nip = ?
                        ORDER BY permintaan_tukar_jadwal.tanggal_permintaan DESC";
$stmt_permintaan = $conn->prepare($sql_permintaan);
$stmt_permintaan->bind_param("s", $nip);
$stmt_permintaan->execute();
$result_permintaan = $stmt_permintaan->get_result();
$permintaan_tukar = [];
while ($row = $result_permintaan->fetch_assoc()) {
    $permintaan_tukar[] = $row;
}
$stmt_permintaan->close();

// Ambil permintaan tukar jadwal yang ditujukan kepada guru ini
$sql_permintaan_masuk = "SELECT permintaan_tukar_jadwal.*, 
                                 jp.tanggal AS tanggal_pengaju, jp.waktu_piket AS waktu_pengaju, 
                                 jt.tanggal AS tanggal_tujuan, jt.waktu_piket AS waktu_tujuan, 
                                 as_pengaju.nama AS nama_pengaju, as_tujuan.nama AS nama_tujuan
                          FROM permintaan_tukar_jadwal 
                          JOIN jadwal_piket AS jp ON permintaan_tukar_jadwal.id_jadwal_pengaju = jp.id_jadwal 
                          JOIN jadwal_piket AS jt ON permintaan_tukar_jadwal.id_jadwal_tujuan = jt.id_jadwal 
                          JOIN anggota_sekolah AS as_pengaju ON jp.nip = as_pengaju.nip 
                          JOIN anggota_sekolah AS as_tujuan ON jt.nip = as_tujuan.nip 
                          WHERE jt.nip = ? AND permintaan_tukar_jadwal.status = 'Pending'
                          ORDER BY permintaan_tukar_jadwal.tanggal_permintaan DESC";
$stmt_permintaan_masuk = $conn->prepare($sql_permintaan_masuk);
$stmt_permintaan_masuk->bind_param("s", $nip);
$stmt_permintaan_masuk->execute();
$result_permintaan_masuk = $stmt_permintaan_masuk->get_result();
$permintaan_masuk = [];
while ($row = $result_permintaan_masuk->fetch_assoc()) {
    $permintaan_masuk[] = $row;
}
$stmt_permintaan_masuk->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Jadwal Piket Saya</title>
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
        .badge-diterima {
            background-color: #28a745; /* Hijau */
            color: #fff;
        }
        .badge-ditolak {
            background-color: #dc3545; /* Merah */
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
                    <h1 class="h3 mb-4 text-gray-800">Dashboard Jadwal Piket Saya</h1>
                    
                    <!-- Menampilkan Notifikasi -->
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

                    <!-- Tampilan Laporan -->
                    <?php if (empty($jadwal)): ?>
                        <div class="alert alert-info">Tidak ada jadwal piket untuk Anda.</div>
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
                                        <th>No</th>
                                        <th>Tanggal</th>
                                        <th>Waktu Piket</th>
                                        <th>Status Kehadiran</th>
                                        <th>Aksi</th>
                                        <th>Tukar Jadwal</th>
                                        <th>Status Tukar Jadwal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($months)): ?>
                                        <?php $no_total = 1; ?>
                                        <?php foreach ($months as $month => $days): ?>
                                            <tr>
                                                <td colspan="7" class="table-secondary text-left"><strong><?= htmlspecialchars($month); ?></strong></td>
                                            </tr>
                                            <?php foreach ($days as $day): ?>
                                                <?php
                                                    // Cari jadwal berdasarkan tanggal
                                                    $jadwal_entry = array_filter($jadwal, function($j) use ($day) {
                                                        return $j['tanggal'] === $day['formatted_date'];
                                                    });
                                                    $jadwal_entry = array_values($jadwal_entry);
                                                    $j = $jadwal_entry[0] ?? null;

                                                    // Ambil permintaan tukar jadwal yang dibuat oleh jadwal ini
                                                    $sql_perm = "SELECT * FROM permintaan_tukar_jadwal WHERE id_jadwal_pengaju = ?";
                                                    $stmt_perm = $conn->prepare($sql_perm);
                                                    if ($j) {
                                                        $stmt_perm->bind_param("i", $j['id_jadwal']);
                                                        $stmt_perm->execute();
                                                        $result_perm = $stmt_perm->get_result();
                                                        $perm_request = $result_perm->fetch_assoc();
                                                        $stmt_perm->close();
                                                    } else {
                                                        $perm_request = null;
                                                    }
                                                ?>
                                                <?php if ($j): ?>
                                                    <tr>
                                                        <td><?= $no_total++; ?></td>
                                                        <td><?= htmlspecialchars(date('d F Y', strtotime($j['tanggal']))); ?><br><?= htmlspecialchars($day['day']); ?></td>
                                                        <td><?= htmlspecialchars($j['waktu_piket']); ?></td>
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
                                                                        echo htmlspecialchars($j['status']);
                                                                        break;
                                                                }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                                // Jika Anda ingin menambahkan aksi di sini (misalnya, edit atau delete), tambahkan tombol atau link aksi
                                                                // Namun, berdasarkan logika saat ini, aksi tidak diperlukan di sini
                                                                echo "-";
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                                // Dropdown untuk memilih guru tujuan
                                                                if ($j['tanggal'] === $currentDate && $j['status'] === 'pending') {
                                                                    ?>
                                                                    <form method="POST" action="dashboard_jadwal.php" class="form-inline">
                                                                        <input type="hidden" name="id_jadwal_pengaju" value="<?= $j['id_jadwal']; ?>">
                                                                        <div class="form-group mb-2">
                                                                            <select name="id_jadwal_tujuan" class="form-control" required>
                                                                                <option value="" disabled selected>-- Pilih Guru --</option>
                                                                                <?php foreach ($daftar_guru as $guru): ?>
                                                                                    <option value="<?= htmlspecialchars($guru['id_jadwal']); ?>">
                                                                                        <?= htmlspecialchars($guru['nama']) . " (" . htmlspecialchars($guru['nip']) . ")"; ?>
                                                                                    </option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </div>
                                                                        &nbsp;
                                                                        <button type="submit" name="tukar_jadwal" class="btn btn-primary mb-2">Tukar Jadwal</button>
                                                                    </form>
                                                                    <?php
                                                                } else {
                                                                    echo "-";
                                                                }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                                // Cari permintaan tukar jadwal yang dibuat oleh jadwal ini
                                                                $sql_status = "SELECT status FROM permintaan_tukar_jadwal WHERE id_jadwal_pengaju = ?";
                                                                $stmt_status = $conn->prepare($sql_status);
                                                                if ($j) {
                                                                    $stmt_status->bind_param("i", $j['id_jadwal']);
                                                                    $stmt_status->execute();
                                                                    $result_status = $stmt_status->get_result();
                                                                    $status_swap = $result_status->fetch_assoc()['status'] ?? '-';
                                                                    $stmt_status->close();

                                                                    if ($status_swap !== '-') {
                                                                        switch ($status_swap) {
                                                                            case 'Pending':
                                                                                echo '<span class="badge badge-pending">Pending</span>';
                                                                                break;
                                                                            case 'Diterima':
                                                                                echo '<span class="badge badge-diterima">Diterima</span>';
                                                                                break;
                                                                            case 'Ditolak':
                                                                                echo '<span class="badge badge-ditolak">Ditolak</span>';
                                                                                break;
                                                                            default:
                                                                                echo htmlspecialchars($status_swap);
                                                                                break;
                                                                        }
                                                                    } else {
                                                                        echo '-';
                                                                    }
                                                                } else {
                                                                    echo '-';
                                                                }
                                                            ?>
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <tr>
                                                        <td><?= $no_total++; ?></td>
                                                        <td><?= htmlspecialchars(date('d F Y', strtotime($day['formatted_date']))); ?><br><?= htmlspecialchars($day['day']); ?></td>
                                                        <td><?= htmlspecialchars('Tidak ada jadwal'); ?></td>
                                                        <td><?= htmlspecialchars('Tidak ada data'); ?></td>
                                                        <td><?= htmlspecialchars('Tidak ada aksi'); ?></td>
                                                        <td><?= htmlspecialchars('Tidak ada jadwal'); ?></td>
                                                        <td><?= htmlspecialchars('Tidak ada data'); ?></td>
                                                        <td></td>
                                                    </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <!-- Permintaan Tukar Jadwal Masuk (Untuk Guru Tujuan) -->
                    <?php if (!empty($permintaan_masuk)): ?>
                        <h2 class="mt-5">Permintaan Tukar Jadwal Masuk</h2>
                        <div class="table-responsive">
                            <table class="table table-bordered text-center">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Jadwal Pengaju</th>
                                        <th>Jadwal Tujuan</th>
                                        <th>Nama Pengaju</th>
                                        <th>Tanggal Permintaan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no_perm = 1; ?>
                                    <?php foreach ($permintaan_masuk as $perm): ?>
                                        <tr>
                                            <td><?= $no_perm++; ?></td>
                                            <td>
                                                <?= htmlspecialchars(date('d F Y', strtotime($perm['tanggal_pengaju']))); ?>, <?= htmlspecialchars($perm['waktu_pengaju']); ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars(date('d F Y', strtotime($perm['tanggal_tujuan']))); ?>, <?= htmlspecialchars($perm['waktu_tujuan']); ?>
                                            </td>
                                            <td><?= htmlspecialchars($perm['nama_pengaju']); ?> (<?= htmlspecialchars($perm['nip_pengaju'] ?? ''); ?>)</td>
                                            <td><?= htmlspecialchars(date('d F Y H:i:s', strtotime($perm['tanggal_permintaan']))); ?></td>
                                            <td>
                                                <!-- Tombol untuk membuka modal respon -->
                                                <button type="button" class="btn btn-success btn-sm respond-swap-btn" data-toggle="modal" data-target="#respondSwapModal" data-id="<?= $perm['id']; ?>">
                                                    Terima
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm respond-swap-btn" data-toggle="modal" data-target="#respondSwapModal" data-id="<?= $perm['id']; ?>">
                                                    Tolak
                                                </button>
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

    <!-- Modal untuk Menanggapi Permintaan Tukar Jadwal -->
    <div class="modal fade" id="respondSwapModal" tabindex="-1" aria-labelledby="respondSwapModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form method="POST" action="dashboard_jadwal.php">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="respondSwapModalLabel">Tanggapi Permintaan Tukar Jadwal</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            
            <div class="modal-body">
              <input type="hidden" name="id_permintaan" id="id_permintaan">
              <p>Apakah Anda ingin menerima atau menolak permintaan tukar jadwal ini?</p>
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
    
    <script>
        $(document).ready(function() {
            // Event listener untuk tombol respon tukar jadwal
            $('.respond-swap-btn').on('click', function() {
                var id_permintaan = $(this).data('id');
                $('#id_permintaan').val(id_permintaan);
            });
        });
    </script>

    <!-- JavaScript Bootstrap dan Dependencies -->
    <script src="../../assets/vendor/jquery/jquery.min.js"></script>
    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../../assets/js/sb-admin-2.min.js"></script>
</body>
</html>
