<?php
// dashboard_jadwal.php
// Versi dengan tampilan SB Admin 2, Bootstrap 5.3.3, DataTables (dengan RowGroup extension), Card Header gradient

// Aktifkan error reporting untuk debugging (nonaktifkan pada produksi)
   
$pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../helpers.php';
init_error_handling();   // <<-- SUPAYA LOG_ERRORS NYALA
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
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');

        $id_jadwal   = intval($_POST['id_jadwal'] ?? 0);
        $new_status  = strtolower(trim($_POST['update_status'] ?? ''));

        if ($id_jadwal <= 0 || !in_array($new_status, ['hadir','tidak hadir'])) {
            throw new Exception("Data tidak valid untuk update status.");
        }

        $sql  = "UPDATE jadwal_piket SET status = ? WHERE id_jadwal = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Prepare failed: ".$conn->error);

        $stmt->bind_param("si", $new_status, $id_jadwal);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: ".$stmt->error);
        }
        $stmt->close();

        $_SESSION['absensi_success'] = "Status kehadiran telah diperbarui menjadi '$new_status'.";
    } catch (Throwable $e) {
        log_error("dashboard_jadwal.php [update_status]: ".$e->getMessage());
        $_SESSION['absensi_error'] = "Gagal mengupdate status kehadiran.";
    }
    header("Location: dashboard_jadwal.php");
    exit();
}


/** 
 * PROSES REQUEST TUKAR JADWAL
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tukar_jadwal'])) {
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');

        $id_jadwal_pengaju = intval($_POST['id_jadwal_pengaju'] ?? 0);
        $guru_nip_tujuan   = trim($_POST['guru_nip_tujuan'] ?? '');

        if ($id_jadwal_pengaju <= 0 || $guru_nip_tujuan === '') {
            throw new Exception("Data request tidak lengkap.");
        }
        if ($nip !== ($_SESSION['nip'] ?? '')) {
            throw new Exception("Anda tidak memiliki akses.");
        }

        // 1) ambil tanggal & waktu piket pengaju
        $sql  = "SELECT tanggal, waktu_piket FROM jadwal_piket WHERE id_jadwal = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param("i", $id_jadwal_pengaju);
        $stmt->execute();
        $jadwal_pengaju = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$jadwal_pengaju) {
            throw new Exception("Data jadwal pengaju tidak ditemukan.");
        }
        $tanggal_request = $jadwal_pengaju['tanggal'];

        // 2) cek guru tujuan belum punya piket pada tanggal yang sama
        $sql  = "SELECT COUNT(*) AS total 
                 FROM jadwal_piket 
                 WHERE nip = ? 
                   AND tanggal = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param("ss", $guru_nip_tujuan, $tanggal_request);
        $stmt->execute();
        $reg = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($reg['total'] > 0) {
            $_SESSION['absensi_error'] = "Guru tujuan sudah terdaftar di jadwal piket pada tanggal tersebut!";
            header("Location: dashboard_jadwal.php");
            exit();
        }

        // 3) cari jadwal tujuan pending (jika ada)
        $sql  = "
            SELECT id_jadwal 
              FROM jadwal_piket 
             WHERE nip = ? 
               AND tanggal = ? 
               AND status = 'pending'
             LIMIT 1
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param("ss", $guru_nip_tujuan, $tanggal_request);
        $stmt->execute();
        $tujuan = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $id_jadwal_tujuan = $tujuan['id_jadwal'] ?? null;

        // 4) cek duplikat request
        if ($id_jadwal_tujuan === null) {
            $sql  = "SELECT COUNT(*) AS total 
                     FROM permintaan_tukar_jadwal 
                     WHERE id_jadwal_pengaju = ? 
                       AND id_jadwal_tujuan IS NULL";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id_jadwal_pengaju);
        } else {
            $sql  = "SELECT COUNT(*) AS total 
                     FROM permintaan_tukar_jadwal 
                     WHERE id_jadwal_pengaju = ? 
                       AND id_jadwal_tujuan = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $id_jadwal_pengaju, $id_jadwal_tujuan);
        }
        $stmt->execute();
        $dup = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        if ($dup > 0) {
            $_SESSION['absensi_error'] = "Request tukar jadwal sudah ada.";
            header("Location: dashboard_jadwal.php");
            exit();
        }

        // 5) simpan permintaan (gunakan INSERT IGNORE untuk menghindari race condition)
        $sql = "INSERT IGNORE INTO permintaan_tukar_jadwal 
                (id_jadwal_pengaju, id_jadwal_tujuan, nip_tujuan, status, nip_pengaju, nama_pengaju, tanggal_permintaan, tanggal_piket)
                VALUES (?, ?, ?, 'Pending', ?, ?, NOW(), ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param(
            "iissss",
            $id_jadwal_pengaju,
            $id_jadwal_tujuan,
            $guru_nip_tujuan,
            $nip,
            $nama_pengaju,
            $tanggal_request
        );
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $stmt->close();

        $_SESSION['absensi_success'] = "Request tukar jadwal telah dikirim.";
    } catch (Throwable $e) {
        log_error("dashboard_jadwal.php [tukar_jadwal]: " . $e->getMessage());
        $_SESSION['absensi_error'] = "Gagal mengirim request tukar jadwal.";
    }
    header("Location: dashboard_jadwal.php");
    exit();
}

/**
 * PROSES RESPON REQUEST TUKAR JADWAL (Accept / Reject)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id_request'])) {
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');

        $action     = $_POST['action'];
        $id_request = intval($_POST['id_request'] ?? 0);
        if ($id_request <= 0) {
            throw new Exception("Data respon tidak valid.");
        }

        // Ambil request yang masih Pending
        $sql  = "SELECT * FROM permintaan_tukar_jadwal WHERE id = ? AND status = 'Pending'";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param("i", $id_request);
        $stmt->execute();
        $req = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$req) {
            throw new Exception("Request tukar jadwal tidak ditemukan atau sudah diproses.");
        }
        // Pastikan hanya guru tujuan yang boleh respon
        if ($req['nip_tujuan'] !== $nip) {
            throw new Exception("Anda tidak memiliki akses untuk merespon request ini.");
        }

        if ($action === 'accept') {
            // --- SKEMA 1: takeover (jika id_jadwal_tujuan NULL) ---
            if ($req['id_jadwal_tujuan'] === null) {
                // Ambil nama guru tujuan
                $sql = "SELECT nama FROM anggota_sekolah WHERE nip = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $req['nip_tujuan']);
                $stmt->execute();
                $nama_tujuan = $stmt->get_result()->fetch_assoc()['nama'] ?? '';
                $stmt->close();
                if (empty($nama_tujuan)) {
                    throw new Exception("Nama guru tujuan tidak ditemukan.");
                }

                // Transaksi takeover
                $conn->begin_transaction();
                try {
                    // Update pemilik jadwal
                    $sql = "UPDATE jadwal_piket SET nip = ?, nama_guru = ? WHERE id_jadwal = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssi", $req['nip_tujuan'], $nama_tujuan, $req['id_jadwal_pengaju']);
                    $stmt->execute();
                    $stmt->close();

                    // Tandai request diterima
                    $sql = "UPDATE permintaan_tukar_jadwal 
                            SET id_jadwal_tujuan = ?, status = 'Diterima'
                            WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $req['id_jadwal_pengaju'], $id_request);
                    $stmt->execute();
                    $stmt->close();

                    $conn->commit();
                    $_SESSION['absensi_success'] = "Request diterima. Anda sekarang bertugas pada tanggal {$req['tanggal_piket']}.";
                } catch (Throwable $e) {
                    $conn->rollback();
                    throw $e;
                }
            }
            // --- SKEMA 2: swap antar dua guru ---
            else {
                // Ambil nama kedua guru
                $sql = "SELECT nama FROM anggota_sekolah WHERE nip = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $req['nip_pengaju']);
                $stmt->execute();
                $nama_pengaju_ref = $stmt->get_result()->fetch_assoc()['nama'] ?? '';
                $stmt->close();

                $sql = "SELECT nama FROM anggota_sekolah WHERE nip = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $req['nip_tujuan']);
                $stmt->execute();
                $nama_tujuan_ref = $stmt->get_result()->fetch_assoc()['nama'] ?? '';
                $stmt->close();

                if (empty($nama_pengaju_ref) || empty($nama_tujuan_ref)) {
                    throw new Exception("Nama salah satu guru tidak ditemukan.");
                }

                $conn->begin_transaction();
                try {
                    // 1) jadwal pengaju → guru tujuan
                    $sql = "UPDATE jadwal_piket SET nip = ?, nama_guru = ? WHERE id_jadwal = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssi", $req['nip_tujuan'], $nama_tujuan_ref, $req['id_jadwal_pengaju']);
                    $stmt->execute();

                    // 2) jadwal tujuan → guru pengaju
                    $stmt->bind_param("ssi", $req['nip_pengaju'], $nama_pengaju_ref, $req['id_jadwal_tujuan']);
                    $stmt->execute();
                    $stmt->close();

                    // 3) tandai request diterima
                    $sql = "UPDATE permintaan_tukar_jadwal SET status = 'Diterima' WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $id_request);
                    $stmt->execute();
                    $stmt->close();

                    $conn->commit();
                    $_SESSION['absensi_success'] = "Request tukar jadwal diterima dan jadwal berhasil ditukar.";
                } catch (Throwable $e) {
                    $conn->rollback();
                    throw $e;
                }
            }
        }
        // --- Jika Reject ---
        else {
            $sql  = "UPDATE permintaan_tukar_jadwal SET status = 'Ditolak' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
            $stmt->bind_param("i", $id_request);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();
            $_SESSION['absensi_success'] = "Request tukar jadwal telah ditolak.";
        }
    } catch (Throwable $e) {
        log_error("dashboard_jadwal.php [respond_swap]: " . $e->getMessage());
        $_SESSION['absensi_error'] = "Gagal menanggapi permintaan tukar jadwal.";
    }

    header("Location: dashboard_jadwal.php");
    exit();
}


/**
 * PENGAMBILAN DATA UNTUK TAMPILAN (Dashboard)
 */
$sql = "SELECT * FROM jadwal_piket WHERE nip = ? ORDER BY tanggal ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nip);
$stmt->execute();
$result = $stmt->get_result();
$jadwal = [];
while ($row = $result->fetch_assoc()) {
    $dateObj       = new DateTime($row['tanggal']);
    $row['day']    = translate_day($dateObj->format('D'));
    $row['group']  = translate_month($row['bulan']) . " " . $row['tahun'];
    $jadwal[]      = $row;
}
$stmt->close();

/**
 * Ambil data REQUEST TUKAR JADWAL MASUK
 * (incoming untuk modal/respond)
 */
$swap_requests = [];
if (!empty($jadwal)) {
    $ids          = array_column($jadwal, 'id_jadwal');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "
        SELECT ptj.*,
               jp.nama_guru        AS nama_guru_pengaju,
               jp.waktu_piket      AS waktu_piket_pengaju,
               jp.tanggal     AS tanggal_piket_pengaju
        FROM permintaan_tukar_jadwal ptj
        JOIN jadwal_piket jp
          ON ptj.id_jadwal_pengaju = jp.id_jadwal
        WHERE (ptj.id_jadwal_tujuan IN ($placeholders)
               OR ptj.nip_tujuan = ?)
          AND ptj.status = 'Pending'
        ORDER BY ptj.tanggal_permintaan DESC
    ";
    $stmt = $conn->prepare($sql);
    // tipe param: semua id_jadwal (integer) + 1 string (nip)
    $types  = str_repeat('i', count($ids)) . 's';
    $params = array_merge($ids, [$nip]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        // hitung nama hari dari tanggal
        $dateObj = new DateTime($r['tanggal_piket_pengaju']);
        $r['day_piket_pengaju'] = translate_day($dateObj->format('D'));
    
        $swap_requests[] = $r;
    }
    $stmt->close();
} else {
    // fallback: jika belum ada jadwal sama sekali
    $sql = "
        SELECT ptj.*,
               jp.nama_guru AS nama_guru_pengaju,
               jp.waktu_piket AS waktu_piket_pengaju,
               jp.tanggal     AS tanggal_piket_pengaju
        FROM permintaan_tukar_jadwal ptj
        JOIN jadwal_piket jp
          ON ptj.id_jadwal_pengaju = jp.id_jadwal
        WHERE ptj.nip_tujuan = ?
          AND ptj.status = 'Pending'
        ORDER BY ptj.tanggal_permintaan DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nip);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        // hitung nama hari dari tanggal
        $dateObj = new DateTime($r['tanggal_piket_pengaju']);
        $r['day_piket_pengaju'] = translate_day($dateObj->format('D'));
    
        $swap_requests[] = $r;
    }
    $stmt->close();
}

/**
 * Ambil data OUTGOING PENDING REQUESTS milik user
 * (untuk men-disable tombol Tukar dan menampilkan nama guru tujuan)
 */
$pending_requests = [];  // [id_jadwal_pengaju => ['nip'=>..., 'nama'=>...]]
$sql = "
    SELECT ptj.id_jadwal_pengaju,
           ptj.nip_tujuan,
           ag.nama AS nama_tujuan
    FROM permintaan_tukar_jadwal ptj
    JOIN anggota_sekolah ag
      ON ptj.nip_tujuan = ag.nip
    WHERE ptj.nip_pengaju = ?
      AND ptj.status     = 'Pending'
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nip);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $pending_requests[$r['id_jadwal_pengaju']] = [
        'nip'  => $r['nip_tujuan'],
        'nama' => $r['nama_tujuan'],
    ];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Dashboard Jadwal Piket</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <!-- SB Admin 2, Bootstrap 5, FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.3/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/rowgroup/1.3.1/css/rowGroup.bootstrap5.min.css">

    <style>
        /* Global */
        body {
            background: linear-gradient(to right, #eef2f7, #e3ecf9);
            font-family: 'Segoe UI', sans-serif;
            color: #212529;
        }

        h1 {
            color: #1565c0;
            font-weight: 700;
        }

        /* Card & Header */
        .card {
            border-radius: 1rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.06);
            border: none;
        }
/* ===== Page Title Styling ===== */
.page-title {
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    font-size: 2.5rem;
    color: #0d47a1;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-bottom: 3px solid #1976d2;
    padding-bottom: 0.3rem;
    margin-bottom: 1.5rem;
    animation: fadeInSlide 0.5s ease-in-out both;
}
.page-title i {
    color: #1976d2;
    font-size: 2.8rem;
}
        .card-header {
            background: linear-gradient(135deg, #0d47a1, #42a5f5);
            color: #fff;
            font-weight: 600;
            border-top-left-radius: 1rem;
            border-top-right-radius: 1rem;
        }

        .card-header h6 {
            margin: 0;
        }

        /* Table */
        .table thead th {
            background: #f8f9fc;
            color: #212529 !important;
            border-bottom: 2px solid #e3e6f0;
        }

        .table tbody td,
  .table tbody th {
    color: #212529 !important;
  }

  .dataTables_empty {
    color: #212529 !important;
  }

        .table tbody tr:nth-child(even) {
            background: #f8f9fc;
        }

        th,
        td {
            vertical-align: middle !important;
            white-space: nowrap;
        }

        /* Badges */
        .badge-pending {
            background: #ffc107;
            color: #212529;
        }

        .badge-hadir {
            background: #28a745;
            color: #fff;
        }

        .badge-tidak-hadir {
            background: #dc3545;
            color: #fff;
        }

        .badge-secondary {
            background: #6c757d;
            color: #fff;
        }

        /* Modal */
        .modal-content {
            border-radius: 1rem;
        }

        /* Select2 overrides */
        .select2-container .select2-selection--single {
            height: calc(1.5em + .75rem + 2px);
            padding: .375rem .75rem;
            border-radius: .5rem;
            border: 1px solid #ccc;
        }

        .select2-container--bootstrap5 .select2-selection--single .select2-selection__rendered {
            line-height: 1.5;
        }
    </style>
</head>

<body id="page-top">
    <div id="wrapper">
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include __DIR__ . '/../navbar.php'; ?>
                <?php include __DIR__ . '/../breadcrumb.php'; ?>
                <div class="container-fluid">
<h1 class="page-title">
        <i class="fas fa-user-circle me-2"></i>Dashboard Jadwal Piket
    </h1>
                    <!-- Jadwal Card -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6>Jadwal Piket Saya</h6>
                        </div>
                        <div class="card-body">
                            <!-- Notifikasi -->
                            <?php if (isset($_SESSION['absensi_success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show">
                                    <?= htmlspecialchars($_SESSION['absensi_success']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php unset($_SESSION['absensi_success']); ?>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['absensi_error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <?= htmlspecialchars($_SESSION['absensi_error']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php unset($_SESSION['absensi_error']); ?>
                            <?php endif; ?>

                            <div class="table-responsive">
                                <table id="tableJadwal" class="table table-bordered table-striped text-center text-dark" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th style="display:none;">Group</th>
                                            <th>Nama Guru</th>
                                            <th>Waktu</th>
                                            <th>Tanggal</th>
                                            <th>Bulan</th>
                                            <th>Tahun</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                            <th>Tukar</th>
                                            <th>Request</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($jadwal as $j): ?>
                                        <tr>
                                            <td style="display:none;"><?= htmlspecialchars($j['group']); ?></td>
                                            <td><?= htmlspecialchars($j['nama_guru']); ?></td>
                                            <td><?= htmlspecialchars($j['waktu_piket']); ?></td>
                                            <td>
                                                <?= date('d F Y', strtotime($j['tanggal'])); ?><br>
                                                <small><?= htmlspecialchars($j['day']); ?></small>
                                            </td>
                                            <td><?= htmlspecialchars(translate_month($j['bulan'])); ?></td>
                                            <td><?= htmlspecialchars($j['tahun']); ?></td>
                                            <td>
                                                <?php
                                                    $s = strtolower($j['status']);
                                                    if ($s === 'pending')        echo '<span class="badge badge-pending">Pending</span>';
                                                    elseif ($s === 'hadir')      echo '<span class="badge badge-hadir">Hadir</span>';
                                                    elseif ($s === 'tidak hadir')echo '<span class="badge badge-tidak-hadir">Tidak Hadir</span>';
                                                    else                         echo '<span class="badge badge-secondary">-</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($j['status'] === 'pending'): ?>
                                                    <!-- tombol Hadir / Tidak Hadir -->
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="id_jadwal" value="<?= $j['id_jadwal']; ?>">
                                                        <button name="update_status" value="hadir" class="btn btn-success btn-sm">Hadir</button>
                                                    </form>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="id_jadwal" value="<?= $j['id_jadwal']; ?>">
                                                        <button name="update_status" value="tidak hadir" class="btn btn-danger btn-sm">Tidak</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                    $idJ = $j['id_jadwal'];
                                                    if (isset($pending_requests[$idJ])): ?>
                                                        <span class="text-dark">
                                                            Menunggu konfirmasi:<br>
                                                            <strong>
                                                                <?= htmlspecialchars($pending_requests[$idJ]['nama'])
                                                                    . " (" . htmlspecialchars($pending_requests[$idJ]['nip']) . ")"; ?>
                                                            </strong>
                                                        </span>
                                                    <?php elseif ($j['status'] === 'pending'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                            <input type="hidden" name="id_jadwal_pengaju" value="<?= $j['id_jadwal'] ?>">
                                                            <input type="hidden" name="tukar_jadwal" value="1">
                                                            <select name="guru_nip_tujuan" class="form-select form-select-sm" required>
                                                                <option value="" disabled selected>-- Pilih Guru --</option>
                                                                <?php foreach ($daftar_guru as $g): ?>
                                                                    <option value="<?= htmlspecialchars($g['nip']); ?>">
                                                                        <?= htmlspecialchars($g['nama']) . " ({$g['nip']})"; ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <button type="button" class="btn btn-primary btn-sm btn-swap">Tukar</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">-</span>
                                                    <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                    $stmt2 = $conn->prepare(
                                                        "SELECT status FROM permintaan_tukar_jadwal 
                                                         WHERE id_jadwal_pengaju=? 
                                                         ORDER BY tanggal_permintaan DESC 
                                                         LIMIT 1"
                                                    );
                                                    $stmt2->bind_param("i", $j['id_jadwal']);
                                                    $stmt2->execute();
                                                    $st = $stmt2->get_result()->fetch_assoc()['status'] ?? '';
                                                    $stmt2->close();
                                                    echo $st
                                                        ? '<span class="badge badge-info">'.htmlspecialchars($st).'</span>'
                                                        : '<span class="badge badge-secondary">-</span>';
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($swap_requests)): ?>
  <div class="card mb-4">
    <div class="card-header bg-warning text-white">
      <h6>Permintaan Tukar Masuk</h6>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered text-center text-dark">
          <thead>
            <tr>
              <th>#</th>
              <th>Pengaju</th>
              <th>Tgl. Piket</th>
              <th>Waktu</th>
              <th>Waktu Request</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($swap_requests as $i => $req): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><?= htmlspecialchars($req['nama_guru_pengaju']) ?></td>
              <td>
  <?= date('d F Y', strtotime($req['tanggal_piket_pengaju'])) ?><br>
  <small><?= htmlspecialchars($req['day_piket_pengaju']) ?></small>
</td>
              <td><?= htmlspecialchars($req['waktu_piket_pengaju']) ?></td>
              <td><?= date('d-m-Y H:i', strtotime($req['tanggal_permintaan'])) ?></td>
              <td>
                <button class="btn btn-sm btn-success respond-swap-btn"
                        data-id="<?= $req['id'] ?>"
                        data-bs-toggle="modal"
                        data-bs-target="#respondSwapModal">
                  Terima / Tolak
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

                    <!-- Modal untuk respond swap -->
                    <div class="modal fade" id="respondSwapModal" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="POST" action="dashboard_jadwal.php" class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Tanggapi Request Tukar Jadwal</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="id_request" id="id_request" value="">
                                    <p>Terima atau tolak permintaan tukar jadwal ini?</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" name="action" value="accept" class="btn btn-success">Terima</button>
                                    <button type="submit" name="action" value="reject" class="btn btn-danger">Tolak</button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div> <!-- /.container-fluid -->
            </div> <!-- /#content -->
        </div> <!-- /#content-wrapper -->
    </div> <!-- /#wrapper -->

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>

    <!-- DataTables & RowGroup -->
    <script src="https://cdn.datatables.net/1.13.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.3/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/rowgroup/1.3.1/js/dataTables.rowGroup.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
$(function() {
  // Inisialisasi DataTable dengan grouping
  $('#tableJadwal').DataTable({
    lengthMenu: [
      [5, 10, 25, -1],
      [5, 10, 25, "All"]
    ],
    pageLength: 5,
    ordering: false,
    language: {
      url: 'https://cdn.datatables.net/plug-ins/1.13.3/i18n/id.json'
    },
    rowGroup: {
      dataSrc: 0,
      startRender: function(rows, group) {
        return $('<tr/>')
          .append('<td colspan="10" class="fw-bold text-start bg-light">' + group + '</td>');
      }
    },
    columnDefs: [
      {
        targets: 0,
        visible: false
      }
    ]
  });  // <-- tutup DataTable()

  // Set id_request di modal (jika pakai tombol respons)
  $('.respond-swap-btn').on('click', function() {
    $('#id_request').val($(this).data('id'));
  });

  // Tangkap klik tombol tukar
  $('.btn-swap').on('click', function(e) {
    e.preventDefault();
    const form = $(this).closest('form');

    Swal.fire({
      title: 'Konfirmasi Tukar Jadwal',
      text: 'Apakah Anda yakin ingin mengirim permintaan tukar jadwal?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Ya, kirim',
      cancelButtonText: 'Batal',
      reverseButtons: true
    }).then((res) => {
      if (res.isConfirmed) form.submit();
    });
  });

});  // <-- tutup $(function()
</script>


</body>

</html>
<?php close_db_connection(); ?>