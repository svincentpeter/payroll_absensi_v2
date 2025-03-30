<?php
// dashboard_jadwal.php
// Versi dengan tampilan SB Admin 2, Bootstrap 5.3.3, DataTables (dengan RowGroup extension), Card Header gradient

// Aktifkan error reporting untuk debugging (nonaktifkan pada produksi)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Ambil nama guru untuk header
$sql = "SELECT nama FROM anggota_sekolah WHERE nip = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nip);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$nama_pengaju = $row['nama'] ?? '';
$stmt->close();

// Ambil data jadwal_piket milik guru
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

/** 
 * PROSES UPDATE STATUS (Hadir/Tidak Hadir)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');

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
 * Request hanya boleh diajukan kepada guru yang tidak terdaftar di jadwal_piket.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tukar_jadwal'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $id_jadwal_pengaju = intval($_POST['id_jadwal_pengaju'] ?? 0);
    $guru_nip_tujuan = trim($_POST['guru_nip_tujuan'] ?? '');
    if ($id_jadwal_pengaju > 0 && !empty($guru_nip_tujuan)) {
        if ($nip !== $_SESSION['nip']) {
            $_SESSION['absensi_error'] = "Anda tidak memiliki akses.";
            header("Location: dashboard_jadwal.php");
            exit();
        }
        // Cek: pastikan guru tujuan tidak terdaftar di jadwal_piket
        $sql_check_reg = "SELECT COUNT(*) AS total FROM jadwal_piket WHERE nip = ?";
        $stmt = $conn->prepare($sql_check_reg);
        $stmt->bind_param("s", $guru_nip_tujuan);
        $stmt->execute();
        $result_reg = $stmt->get_result();
        $data_reg = $result_reg->fetch_assoc();
        $stmt->close();
        if ($data_reg['total'] > 0) {
            $_SESSION['absensi_error'] = "Request penukaran jadwal hanya bisa diajukan kepada guru yang tidak terdaftar di jadwal piket!";
            header("Location: dashboard_jadwal.php");
            exit();
        }

        // Cari jadwal tujuan pending guru tujuan (jika ada)
        $sql_tujuan = "SELECT id_jadwal FROM jadwal_piket WHERE nip = ? AND status = 'pending' ORDER BY tanggal ASC LIMIT 1";
        $stmt = $conn->prepare($sql_tujuan);
        $stmt->bind_param("s", $guru_nip_tujuan);
        $stmt->execute();
        $result_tujuan = $stmt->get_result();
        $data_tujuan = $result_tujuan->fetch_assoc();
        $stmt->close();
        $id_jadwal_tujuan = $data_tujuan ? $data_tujuan['id_jadwal'] : NULL;

        // Ambil tanggal dari jadwal pengaju
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

        // Cek apakah request sudah ada
        if ($id_jadwal_tujuan === NULL) {
            $sql_check = "SELECT COUNT(*) AS total FROM permintaan_tukar_jadwal 
                          WHERE id_jadwal_pengaju = ? AND id_jadwal_tujuan IS NULL";
            $stmt = $conn->prepare($sql_check);
            $stmt->bind_param("i", $id_jadwal_pengaju);
        } else {
            $sql_check = "SELECT COUNT(*) AS total FROM permintaan_tukar_jadwal 
                          WHERE id_jadwal_pengaju = ? AND id_jadwal_tujuan = ?";
            $stmt = $conn->prepare($sql_check);
            $stmt->bind_param("ii", $id_jadwal_pengaju, $id_jadwal_tujuan);
        }
        $stmt->execute();
        $result_check = $stmt->get_result();
        $count = $result_check->fetch_assoc()['total'];
        $stmt->close();

        if ($count == 0) {
            // Simpan request tukar jadwal
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
 * Hanya guru tujuan yang boleh merespon.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id_request'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $action = $_POST['action'];
    $id_request = intval($_POST['id_request'] ?? 0);
    if ($id_request > 0) {
        $sql = "SELECT * FROM permintaan_tukar_jadwal 
                WHERE id = ? AND status = 'Pending'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_request);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();
        $stmt->close();

        if ($request) {
            // Pastikan hanya guru tujuan yang dapat merespon
            if ($request['nip_tujuan'] !== $nip) {
                $_SESSION['absensi_error'] = "Anda tidak memiliki akses untuk merespon request ini.";
                header("Location: dashboard_jadwal.php");
                exit();
            }
            if ($action === 'accept') {
                if ($request['id_jadwal_tujuan'] === NULL) {
                    // Skema: jadwal pengaju diambil alih guru tujuan
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
                    // Ambil nama guru tujuan
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
                    // Update jadwal_pengaju: ganti nip & nama_guru jadi guru tujuan
                    $sql_update = "UPDATE jadwal_piket 
                                   SET nip = ?, nama_guru = ? 
                                   WHERE id_jadwal = ?";
                    $stmt = $conn->prepare($sql_update);
                    $stmt->bind_param("ssi", $nip, $nama_tujuan, $jadwal_id);
                    if ($stmt->execute()) {
                        $stmt->close();
                        // Update request: set id_jadwal_tujuan & status = Diterima
                        $sql_update_req = "UPDATE permintaan_tukar_jadwal 
                                           SET id_jadwal_tujuan = ?, status = 'Diterima' 
                                           WHERE id = ?";
                        $stmt = $conn->prepare($sql_update_req);
                        $stmt->bind_param("ii", $jadwal_id, $id_request);
                        $stmt->execute();
                        $stmt->close();

                        $_SESSION['absensi_success'] = "Request diterima. Jadwal pada tanggal "
                            . date('d F Y', strtotime($jadwal_pengaju['tanggal']))
                            . " kini telah dipindahkan ke Anda.";
                    } else {
                        $_SESSION['absensi_error'] = "Gagal mengupdate jadwal untuk guru tujuan.";
                    }
                } else {
                    // Skema: tukar jadwal antar 2 guru
                    $id_pengaju = $request['id_jadwal_pengaju'];
                    $id_tujuan = $request['id_jadwal_tujuan'];

                    $conn->begin_transaction();
                    try {
                        $sql_update = "UPDATE jadwal_piket SET nip = ?, nama_guru = ? WHERE id_jadwal = ?";
                        $stmt = $conn->prepare($sql_update);

                        // 1) Update jadwal pengaju => jadi milik guru tujuan
                        $stmt->bind_param("ssi", $request['nip_tujuan'], '', $id_pengaju);
                        $stmt->execute();

                        // 2) Update jadwal tujuan => jadi milik guru pengaju
                        $stmt->bind_param("ssi", $nip, '', $id_tujuan);
                        $stmt->execute();
                        $stmt->close();

                        // 3) Update status request
                        $sql_update_status = "UPDATE permintaan_tukar_jadwal 
                                             SET status = 'Diterima' 
                                             WHERE id = ?";
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
                $sql = "UPDATE permintaan_tukar_jadwal 
                        SET status = 'Ditolak' 
                        WHERE id = ?";
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
 * (Jika diperlukan, kita refresh data setelah proses)
 */
$sql = "SELECT * FROM jadwal_piket WHERE nip = ? ORDER BY tanggal ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nip);
$stmt->execute();
$result = $stmt->get_result();
$jadwal = [];
while ($row = $result->fetch_assoc()) {
    $dateObj = new DateTime($row['tanggal']);
    // Menggunakan fungsi translate_day() dari helpers.php
    $row['day'] = translate_day($dateObj->format('D'));
    // Siapkan nilai grouping: gabungan Bulan & Tahun
    $row['group'] = translate_month($row['bulan']) . " " . $row['tahun'];
    $jadwal[] = $row;
}
$stmt->close();

// Ambil data request tukar jadwal (jika diperlukan di tampilan)
$swap_requests = [];
if (!empty($jadwal)) {
    $jadwal_ids = array_map(function ($j) {
        return $j['id_jadwal'];
    }, $jadwal);
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
    <!-- FontAwesome, SB Admin 2, dan Bootstrap 5.3.3 CSS via CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SB Admin 2 CSS (pastikan kompatibel dengan Bootstrap 5) -->
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.3/css/dataTables.bootstrap5.min.css">
    <!-- DataTables RowGroup CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/rowgroup/1.3.1/css/rowGroup.bootstrap5.min.css">

    <style>
        /* Gradient untuk Card Header */
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }

        /* Custom Badge (opsional) */
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

        .badge-secondary {
            background-color: #6c757d;
            color: #fff;
        }

        .badge-info {
            background-color: #17a2b8;
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

    <!-- Wrapper SB Admin 2 -->
    <div id="wrapper">
        <?php include __DIR__ . '/../sidebar.php'; ?>

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <?php include __DIR__ . '/../navbar.php'; ?>
                <!-- End of Topbar -->

                <div class="container-fluid">
                    <!-- Page Heading -->
                    <h1 class="h3 mb-4 text-gray-800">Dashboard Jadwal Piket</h1>

                    <!-- Card Jadwal -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-white">Jadwal Piket Saya</h6>
                        </div>
                        <div class="card-body">

                            <!-- Notifikasi -->
                            <?php if (isset($_SESSION['absensi_success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($_SESSION['absensi_success']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['absensi_success']); ?>
                            <?php endif; ?>

                            <?php if (isset($_SESSION['absensi_error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($_SESSION['absensi_error']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['absensi_error']); ?>
                            <?php endif; ?>

                            <div class="table-responsive">
                                <table
                                    id="tableJadwal"
                                    class="table table-bordered table-striped text-center"
                                    style="width:100%">
                                    <thead>
                                        <tr>
                                            <!-- Hidden column untuk grouping -->
                                            <th style="display:none;">Group</th>
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
                                        <?php foreach ($jadwal as $j): ?>
                                            <tr>
                                                <!-- Kolom hidden untuk grouping -->
                                                <td style="display:none;"><?= htmlspecialchars($j['group']); ?></td>
                                                <td><?= htmlspecialchars($j['nama_guru']); ?></td>
                                                <td><?= htmlspecialchars($j['waktu_piket']); ?></td>
                                                <td>
                                                    <?= htmlspecialchars(date('d F Y', strtotime($j['tanggal']))); ?>
                                                    <br>
                                                    <small><?= htmlspecialchars($j['day']); ?></small>
                                                </td>
                                                <td><?= htmlspecialchars(translate_month($j['bulan'])); ?></td>
                                                <td><?= htmlspecialchars($j['tahun']); ?></td>
                                                <td>
                                                    <?php
                                                    $status = strtolower($j['status']);
                                                    if ($status == 'pending') {
                                                        echo '<span class="badge bg-warning text-dark">Pending</span>';
                                                    } elseif ($status == 'tidak hadir') {
                                                        echo '<span class="badge bg-danger">Tidak Hadir</span>';
                                                    } elseif ($status == 'hadir') {
                                                        echo '<span class="badge bg-success">Hadir</span>';
                                                    } else {
                                                        echo '<span class="badge bg-secondary">-</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($j['status'] === 'pending'): ?>
                                                        <form method="POST" action="dashboard_jadwal.php" class="d-inline-block">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                                            <input type="hidden" name="id_jadwal" value="<?= $j['id_jadwal']; ?>">
                                                            <button type="submit" name="update_status" value="hadir" class="btn btn-success btn-sm">Hadir</button>
                                                        </form>
                                                        <form method="POST" action="dashboard_jadwal.php" class="d-inline-block">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                                            <input type="hidden" name="id_jadwal" value="<?= $j['id_jadwal']; ?>">
                                                            <button type="submit" name="update_status" value="tidak hadir" class="btn btn-danger btn-sm">Tidak Hadir</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($j['status'] === 'pending' && $j['nip'] === $nip): ?>
                                                        <form method="POST" action="dashboard_jadwal.php" class="d-inline-block">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                                            <input type="hidden" name="id_jadwal_pengaju" value="<?= $j['id_jadwal']; ?>">
                                                            <select name="guru_nip_tujuan" class="form-select form-select-sm" required>
                                                                <option value="" disabled selected>-- Pilih Guru --</option>
                                                                <?php foreach ($daftar_guru as $guru): ?>
                                                                    <option value="<?= htmlspecialchars($guru['nip']); ?>">
                                                                        <?= htmlspecialchars($guru['nama']) . " (" . htmlspecialchars($guru['nip']) . ")"; ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <button type="submit" name="tukar_jadwal" class="btn btn-primary btn-sm mt-2">
                                                                Tukar Jadwal
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $sql_req = "SELECT status FROM permintaan_tukar_jadwal 
                                                                    WHERE id_jadwal_pengaju = ? 
                                                                    ORDER BY tanggal_permintaan DESC 
                                                                    LIMIT 1";
                                                    $stmt_req = $conn->prepare($sql_req);
                                                    $stmt_req->bind_param("i", $j['id_jadwal']);
                                                    $stmt_req->execute();
                                                    $result_req = $stmt_req->get_result();
                                                    if ($result_req->num_rows > 0) {
                                                        $row_req = $result_req->fetch_assoc();
                                                        echo '<span class="badge bg-info text-white">' . htmlspecialchars($row_req['status']) . '</span>';
                                                    } else {
                                                        echo '<span class="badge bg-secondary">-</span>';
                                                    }
                                                    $stmt_req->close();
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div> <!-- End table-responsive -->
                        </div> <!-- End card-body -->
                    </div> <!-- End card -->

                </div> <!-- End .container-fluid -->
            </div> <!-- End #content -->
        </div> <!-- End #content-wrapper -->
    </div> <!-- End #wrapper -->

    <!-- Modal (opsional) untuk Menanggapi Request Tukar Jadwal -->
    <div class="modal fade" id="respondSwapModal" tabindex="-1" aria-labelledby="respondSwapModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="dashboard_jadwal.php">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="respondSwapModalLabel">Tanggapi Request Tukar Jadwal</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                        <input type="hidden" name="id_request" id="id_request">
                        <p>Apakah Anda ingin menerima atau menolak request tukar jadwal ini?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="action" value="accept" class="btn btn-success">Terima</button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger">Tolak</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript: jQuery 3.6.0, Bootstrap 5.3.3, SB Admin 2, DataTables & RowGroup -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.3/js/dataTables.bootstrap5.min.js"></script>
    <!-- DataTables RowGroup JS -->
    <script src="https://cdn.datatables.net/rowgroup/1.3.1/js/dataTables.rowGroup.min.js"></script>

    <script>
        $(document).ready(function() {
            // Inisialisasi DataTables dengan RowGroup
            $('#tableJadwal').DataTable({
                "lengthMenu": [
                    [5, 10, 25, -1],
                    [5, 10, 25, "All"]
                ],
                "pageLength": 5,
                "ordering": false,
                "language": {
                    "url": "https://cdn.datatables.net/plug-ins/1.13.3/i18n/id.json"
                },
                // Gunakan kolom pertama (hidden) sebagai data grouping
                rowGroup: {
                    dataSrc: 0,
                    startRender: function(rows, group) {
                        return $('<tr/>')
                            .append('<td colspan="9" class="fw-bold text-start">' + group + '</td>');
                    }
                },
                // Definisikan kolom (pastikan kolom pertama diset tidak terlihat)
                "columnDefs": [{
                    "visible": false,
                    "targets": 0
                }]
            });

            // Jika menggunakan modal untuk respon request tukar jadwal
            $('.respond-swap-btn').on('click', function() {
                var id_request = $(this).data('id');
                $('#id_request').val(id_request);
            });
        });
    </script>

</body>

</html>
<?php
// Tutup koneksi database menggunakan fungsi dari helpers.php
close_db_connection();
?>