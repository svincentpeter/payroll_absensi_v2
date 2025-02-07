<?php
// File: /payroll_absensi_v2/koreksi_absensi.php

// =========================
// 1. Pengaturan Keamanan, Session, dan Koneksi
// =========================

session_set_cookie_params([
    'lifetime' => 0, // Berlaku selama browser dibuka
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => true,      // Hanya melalui HTTPS
    'httponly' => true,      // Tidak dapat diakses via JavaScript
    'samesite' => 'Strict'
]);

require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
generate_csrf_token();

// Buat nonce untuk CSP dan simpan di session
$nonce = base64_encode(random_bytes(16));
$_SESSION['csp_nonce'] = $nonce;

// Paksa HTTPS jika belum
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: " . $redirect);
    exit();
}

// Header HSTS
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

// Susun Content Security Policy (CSP) tanpa newline
$csp = "default-src 'self'; " .
       "script-src 'self' https://code.jquery.com https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com https://cdn.datatables.net https://cdn.jsdelivr.net 'nonce-$nonce'; " .
       "style-src 'self' https://stackpath.bootstrapcdn.com https://fonts.googleapis.com https://cdn.datatables.net 'nonce-$nonce'; " .
       "img-src 'self'; " .
       "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
       "connect-src 'self'";
$csp = str_replace(["\r", "\n"], '', $csp);
header("Content-Security-Policy: $csp");

// Cek role (hanya untuk sdm dan superadmin)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['sdm', 'superadmin'])) {
    header("HTTP/1.1 403 Forbidden");
    echo "Akses ditolak.";
    exit();
}

// Koneksi ke database
require_once __DIR__ . '/../../koneksi.php';
if (ob_get_length()) ob_end_clean();

// Fungsi tambahan
function get_nama_karyawan($conn) {
    $sql = "SELECT nama FROM anggota_sekolah GROUP BY nama";
    $result = mysqli_query($conn, $sql);
    $nama = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $nama[] = $row['nama'];
    }
    return $nama;
}

// Fungsi untuk menghapus data absensi
function delete_absensi($conn, $id_absensi) {
    $sqlDelete = "DELETE FROM absensi WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sqlDelete);
    if (!$stmt) {
        return "Gagal menyiapkan statement: " . mysqli_error($conn);
    }
    mysqli_stmt_bind_param($stmt, 'i', $id_absensi);
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return true;
    } else {
        $error = "Gagal menghapus data absensi ID $id_absensi: " . mysqli_error($conn);
        mysqli_stmt_close($stmt);
        return $error;
    }
}

// =========================
// 2. Proses POST: Update, Delete, dan Server-side DataTables
// =========================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Proses Update dan Delete (dengan adanya parameter action)
    if (isset($_POST['action'])) {
        // Verifikasi CSRF
        if (!isset($_POST['csrf_token']) || !verify_csrf_token(trim($_POST['csrf_token']))) {
            $_SESSION['notif_error'] = "Token keamanan tidak valid.";
            header("Location: koreksi_absensi.php");
            exit;
        }
        $action = $_POST['action'];
        if ($action === 'update') {
            $id_absensi      = $_POST['id_absensi'] ?? '';
            $departemen_post = $_POST['departemen'] ?? '';
            $bulan_post      = $_POST['bulan'] ?? '';
            if (empty($id_absensi)) {
                $_SESSION['notif_error'] = "ID Absensi tidak valid.";
                header("Location: koreksi_absensi.php");
                exit;
            }
            // Sanitasi input
            $tanggal          = $_POST['tanggal'] ?? '';
            $jadwal           = $_POST['jadwal'] ?? '';
            $jam_kerja        = $_POST['jam_kerja'] ?? '';
            $valid            = isset($_POST['valid']) ? (int) $_POST['valid'] : 0;
            $pin              = $_POST['pin'] ?? '';
            $nip              = $_POST['nip'] ?? '';
            $nama             = $_POST['nama'] ?? '';
            $lembur           = isset($_POST['lembur']) ? (int) $_POST['lembur'] : 0;
            $jam_masuk        = $_POST['jam_masuk'] ?? '';
            $scan_masuk       = $_POST['scan_masuk'] ?? '';
            $terlambat        = isset($_POST['terlambat']) ? 1 : 0;
            $jam_pulang       = $_POST['jam_pulang'] ?? '';
            $scan_pulang      = $_POST['scan_pulang'] ?? '';
            $jenis_absensi    = $_POST['jenis_absensi'] ?? 'Normal';
            $scan_istirahat_1 = $_POST['scan_istirahat_1'] ?? '';
            $scan_istirahat_2 = $_POST['scan_istirahat_2'] ?? '';

            // Validasi tanggal (format Y-m-d)
            $dtTanggal = DateTime::createFromFormat('Y-m-d', $tanggal);
            if (!$dtTanggal || $dtTanggal->format('Y-m-d') !== $tanggal) {
                $_SESSION['notif_error'] = "Format tanggal tidak valid.";
                header("Location: koreksi_absensi.php");
                exit;
            }
            // Validasi format waktu (HH:MM) untuk field yang ada nilainya
            $valid_time_fields = ['jam_masuk','scan_masuk','jam_pulang','scan_pulang','scan_istirahat_1','scan_istirahat_2'];
            foreach ($valid_time_fields as $field) {
                if (!empty($_POST[$field])) {
                    $dt = DateTime::createFromFormat('H:i', $_POST[$field]);
                    if (!$dt || $dt->format('H:i') !== $_POST[$field]) {
                        $_SESSION['notif_error'] = "Format waktu untuk $field tidak valid (gunakan format HH:MM).";
                        header("Location: koreksi_absensi.php");
                        exit;
                    }
                }
            }
            // Jika scan_* kosong, ambil nilai lama dari database
            if (!empty($scan_masuk)) {
                $scan_masuk_datetime = $tanggal . ' ' . $scan_masuk . ':00';
            } else {
                $querySel = "SELECT scan_masuk FROM absensi WHERE id = ?";
                $stmtSel = mysqli_prepare($conn, $querySel);
                mysqli_stmt_bind_param($stmtSel, 'i', $id_absensi);
                mysqli_stmt_execute($stmtSel);
                mysqli_stmt_bind_result($stmtSel, $old_scan_masuk);
                mysqli_stmt_fetch($stmtSel);
                mysqli_stmt_close($stmtSel);
                $scan_masuk_datetime = $old_scan_masuk;
            }
            if (!empty($scan_pulang)) {
                $scan_pulang_datetime = $tanggal . ' ' . $scan_pulang . ':00';
            } else {
                $querySel = "SELECT scan_pulang FROM absensi WHERE id = ?";
                $stmtSel = mysqli_prepare($conn, $querySel);
                mysqli_stmt_bind_param($stmtSel, 'i', $id_absensi);
                mysqli_stmt_execute($stmtSel);
                mysqli_stmt_bind_result($stmtSel, $old_scan_pulang);
                mysqli_stmt_fetch($stmtSel);
                mysqli_stmt_close($stmtSel);
                $scan_pulang_datetime = $old_scan_pulang;
            }
            if (!empty($scan_istirahat_1)) {
                $scan_istirahat_1_datetime = $tanggal . ' ' . $scan_istirahat_1 . ':00';
            } else {
                $querySel = "SELECT scan_istirahat_1 FROM absensi WHERE id = ?";
                $stmtSel = mysqli_prepare($conn, $querySel);
                mysqli_stmt_bind_param($stmtSel, 'i', $id_absensi);
                mysqli_stmt_execute($stmtSel);
                mysqli_stmt_bind_result($stmtSel, $old_ist1);
                mysqli_stmt_fetch($stmtSel);
                mysqli_stmt_close($stmtSel);
                $scan_istirahat_1_datetime = $old_ist1;
            }
            if (!empty($scan_istirahat_2)) {
                $scan_istirahat_2_datetime = $tanggal . ' ' . $scan_istirahat_2 . ':00';
            } else {
                $querySel = "SELECT scan_istirahat_2 FROM absensi WHERE id = ?";
                $stmtSel = mysqli_prepare($conn, $querySel);
                mysqli_stmt_bind_param($stmtSel, 'i', $id_absensi);
                mysqli_stmt_execute($stmtSel);
                mysqli_stmt_bind_result($stmtSel, $old_ist2);
                mysqli_stmt_fetch($stmtSel);
                mysqli_stmt_close($stmtSel);
                $scan_istirahat_2_datetime = $old_ist2;
            }

            // Update record absensi
            $sqlUpdate = "UPDATE absensi
                          SET 
                            tanggal=?,
                            jadwal=?,
                            jam_kerja=?,
                            valid=?,
                            pin=?,
                            nip=?,
                            nama=?,
                            departemen=?,
                            lembur=?,
                            jam_masuk=?,
                            scan_masuk=?,
                            terlambat=?,
                            scan_istirahat_1=?,
                            scan_istirahat_2=?,
                            jam_pulang=?,
                            scan_pulang=?,
                            jenis_absensi=?
                          WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sqlUpdate);
            if (!$stmt) {
                $_SESSION['notif_error'] = "Gagal menyiapkan statement: " . mysqli_error($conn);
                header("Location: koreksi_absensi.php");
                exit;
            }
            mysqli_stmt_bind_param(
                $stmt,
                'sssissssississsssi',
                $tanggal,
                $jadwal,
                $jam_kerja,
                $valid,
                $pin,
                $nip,
                $nama,
                $departemen_post,
                $lembur,
                $jam_masuk,
                $scan_masuk_datetime,
                $terlambat,
                $scan_istirahat_1_datetime,
                $scan_istirahat_2_datetime,
                $jam_pulang,
                $scan_pulang_datetime,
                $jenis_absensi,
                $id_absensi
            );
            if (mysqli_stmt_execute($stmt)) {
                $audit_details = "Mengupdate data absensi ID $id_absensi. Data: tanggal=$tanggal, jadwal=$jadwal, jam_kerja=$jam_kerja, valid=$valid, pin=$pin, nip=$nip, nama=$nama, departemen=$departemen_post, lembur=$lembur, jam_masuk=$jam_masuk, scan_masuk=$scan_masuk_datetime, terlambat=$terlambat, scan_istirahat_1=$scan_istirahat_1_datetime, scan_istirahat_2=$scan_istirahat_2_datetime, jam_pulang=$jam_pulang, scan_pulang=$scan_pulang_datetime, jenis_absensi=$jenis_absensi.";
                add_audit_log($conn, $_SESSION['user_id'], 'UpdateAbsensi', $audit_details);
                $_SESSION['notif_success'] = "Data absensi ID $id_absensi berhasil dikoreksi.";
                mysqli_stmt_close($stmt);
            } else {
                $_SESSION['notif_error'] = "Gagal mengupdate data absensi ID $id_absensi: " . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
            header("Location: koreksi_absensi.php");
            exit;
        } elseif ($action === 'delete') {
            $id_absensi = $_POST['id_absensi'] ?? '';
            if (empty($id_absensi)) {
                $_SESSION['notif_error'] = "ID Absensi tidak valid.";
                header("Location: koreksi_absensi.php");
                exit;
            }
            $deleteResult = delete_absensi($conn, $id_absensi);
            if ($deleteResult === true) {
                add_audit_log($conn, $_SESSION['user_id'], 'DeleteAbsensi', "Menghapus data absensi ID $id_absensi.");
                $_SESSION['notif_success'] = "Data absensi ID $id_absensi berhasil dihapus.";
            } else {
                $_SESSION['notif_error'] = $deleteResult;
            }
            header("Location: koreksi_absensi.php");
            exit;
        }
    }
    // Proses Server-side DataTables (Ajax)
    elseif (isset($_POST['draw'])) {
        // Tentukan kolom-kolom yang akan diambil
        $columns = [
            'a.id',
            'a.tanggal',
            'a.jadwal',
            'a.jam_kerja',
            'a.valid',
            'a.pin',
            'a.nip',
            'a.nama',
            'a.departemen',
            'a.lembur',
            'a.jam_masuk',
            'a.scan_masuk',
            'a.terlambat',
            'a.scan_istirahat_1',
            'a.scan_istirahat_2',
            'a.jam_pulang',
            'a.scan_pulang',
            'a.jenis_absensi'
        ];

        // Filter dari parameter POST
        $bulan      = isset($_POST['bulan']) ? mysqli_real_escape_string($conn, $_POST['bulan']) : '';
        $departemen = isset($_POST['departemen']) ? mysqli_real_escape_string($conn, $_POST['departemen']) : '';

        // Query dasar
        $sql = "FROM absensi a 
                LEFT JOIN anggota_sekolah g ON a.nip = g.nip
                LEFT JOIN holidays h ON a.tanggal = h.holiday_date
                WHERE 1=1 ";
        if (!empty($bulan)) {
            $sql .= " AND DATE_FORMAT(a.tanggal, '%Y-%m') = '$bulan' ";
        }
        if (!empty($departemen)) {
            $sql .= " AND UPPER(a.departemen) = UPPER('$departemen') ";
        }

        // Total data
        $resTotal = mysqli_query($conn, "SELECT COUNT(*) as total $sql");
        $totalData = mysqli_fetch_assoc($resTotal)['total'];

        // Pencarian global
        $searchValue = $_POST['search']['value'] ?? '';
        if (!empty($searchValue)) {
            $searchValue = mysqli_real_escape_string($conn, $searchValue);
            $sql .= " AND (a.nama LIKE '%$searchValue%' OR a.nip LIKE '%$searchValue%' OR a.departemen LIKE '%$searchValue%') ";
        }
        // Total data setelah filter
        $resTotalFiltered = mysqli_query($conn, "SELECT COUNT(*) as total $sql");
        $totalFiltered = mysqli_fetch_assoc($resTotalFiltered)['total'];

        // Sorting
        $orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
        $orderColumn = $columns[$orderColumnIndex] ?? $columns[0];
        $orderDir = in_array($_POST['order'][0]['dir'] ?? 'asc', ['asc', 'desc']) ? $_POST['order'][0]['dir'] : 'asc';
        $sql .= " ORDER BY $orderColumn $orderDir ";

        // Paging
        $start  = intval($_POST['start'] ?? 0);
        $length = intval($_POST['length'] ?? 10);
        $sql .= " LIMIT $start, $length ";

        // Ambil data
        $query = "SELECT " . implode(", ", $columns) . " $sql";
        $resData = mysqli_query($conn, $query);

        $data = [];
        $no = $start + 1;
        while ($row = mysqli_fetch_assoc($resData)) {
            $jamMasuk  = !empty($row['jam_masuk']) ? date('H:i', strtotime($row['jam_masuk'])) : '-';
            $scanMasuk = !empty($row['scan_masuk']) ? date('H:i', strtotime($row['scan_masuk'])) : '-';
            $jamPulang = !empty($row['jam_pulang']) ? date('H:i', strtotime($row['jam_pulang'])) : '-';
            $scanPulang= !empty($row['scan_pulang']) ? date('H:i', strtotime($row['scan_pulang'])) : '-';

            // Tombol aksi untuk edit dan delete
            $aksi = '<button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#modalEdit" 
                        data-id="'.$row['id'].'"
                        data-tanggal="'.bersihkan_input($row['tanggal']).'"
                        data-jadwal="'.bersihkan_input($row['jadwal']).'"
                        data-jam_kerja="'.bersihkan_input($row['jam_kerja']).'"
                        data-valid="'.$row['valid'].'"
                        data-pin="'.bersihkan_input($row['pin']).'"
                        data-nip="'.bersihkan_input($row['nip']).'"
                        data-nama="'.bersihkan_input($row['nama']).'"
                        data-departemen="'.htmlspecialchars(strtoupper($row['departemen'])).'"
                        data-lembur="'.$row['lembur'].'"
                        data-jam_masuk="'.(($jamMasuk==='-')?'':$jamMasuk).'"
                        data-scan_masuk="'.(($scanMasuk==='-')?'':$scanMasuk).'"
                        data-terlambat="'.$row['terlambat'].'"
                        data-scan_istirahat_1="'.(($row['scan_istirahat_1'])?bersihkan_input($row['scan_istirahat_1']):'').'"
                        data-scan_istirahat_2="'.(($row['scan_istirahat_2'])?bersihkan_input($row['scan_istirahat_2']):'').'"
                        data-jam_pulang="'.(($jamPulang==='-')?'':$jamPulang).'"
                        data-scan_pulang="'.(($scanPulang==='-')?'':$scanPulang).'"
                        data-jenis_absensi="'.$row['jenis_absensi'].'"
                        title="Edit Absensi">
                        <i class="fas fa-edit"></i> Edit
                     </button>
                     <button class="btn btn-danger btn-sm ml-2" data-toggle="modal" data-target="#modalDelete" 
                        data-id="'.$row['id'].'"
                        data-nama="'.bersihkan_input($row['nama']).'"
                        data-tanggal="'.bersihkan_input($row['tanggal']).'"
                        title="Hapus Absensi">
                        <i class="fas fa-trash"></i> Delete
                     </button>';

            $nestedData = [];
            $nestedData[] = $no++;
            $nestedData[] = bersihkan_input($row['tanggal']);
            $nestedData[] = bersihkan_input($row['jadwal']);
            $nestedData[] = bersihkan_input($row['jam_kerja']);
            $nestedData[] = ($row['valid'] == 1) ? '1' : '0';
            $nestedData[] = bersihkan_input($row['pin']);
            $nestedData[] = bersihkan_input($row['nip']);
            $nestedData[] = bersihkan_input($row['nama']);
            $nestedData[] = htmlspecialchars(strtoupper($row['departemen']));
            $nestedData[] = bersihkan_input($row['lembur']);
            $nestedData[] = bersihkan_input($jamMasuk);
            $nestedData[] = bersihkan_input($scanMasuk);
            $nestedData[] = $row['terlambat'] ? '<span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> Ya</span>' : '<span class="badge badge-success"><i class="fas fa-check"></i> Tidak</span>';
            $nestedData[] = ($row['scan_istirahat_1']) ? bersihkan_input($row['scan_istirahat_1']) : '-';
            $nestedData[] = ($row['scan_istirahat_2']) ? bersihkan_input($row['scan_istirahat_2']) : '-';
            $nestedData[] = bersihkan_input($jamPulang);
            $nestedData[] = bersihkan_input($scanPulang);
            $jenis = htmlspecialchars($row['jenis_absensi']);
            $badge = 'badge-secondary';
            switch (strtolower($jenis)) {
                case 'izin':   $badge = 'badge-info'; break;
                case 'sakit':  $badge = 'badge-warning'; break;
                case 'cuti':   $badge = 'badge-primary'; break;
                case 'bolos':  $badge = 'badge-danger'; break;
                case 'libur':  $badge = 'badge-success'; break;
                case 'lembur': $badge = 'badge-info'; break;
            }
            $nestedData[] = '<span class="badge '.$badge.'"><i class="fas fa-check-circle"></i> '.ucfirst($jenis).'</span>';
            $nestedData[] = $aksi;
            $data[] = $nestedData;
        }

        $json_data = [
            "draw"            => intval($_POST['draw'] ?? 1),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data
        ];

        header('Content-Type: application/json');
        echo json_encode($json_data);
        exit;
    }
} // Akhir proses POST

// Ambil filter GET untuk tampilan
$bulan      = $_GET['bulan'] ?? '';
$departemen = $_GET['departemen'] ?? '';
$csrf_token = $_SESSION['csrf_token'];
$namaKaryawan = get_nama_karyawan($conn);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Koreksi Absensi - Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Font Awesome -->
    <link href="../../assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" nonce="<?php echo $nonce; ?>">
<!-- SB Admin 2 CSS -->
    <link href="../../assets/css/sb-admin-2.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap4.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <link href="https://cdn.datatables.net/buttons/1.7.1/css/buttons.bootstrap4.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <link href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <!-- FullCalendar CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <!-- jQuery UI CSS -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" nonce="<?php echo $nonce; ?>">
    <style nonce="<?php echo $nonce; ?>">
        .fc-holiday {
            background-color: #ffcccc !important;
            border-color: #ff6666 !important;
            color: #000 !important;
        }
        .no-column {
            width: 70px;
            text-align: center;
        }
        #absensiTable th,
        #absensiTable td {
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
        }
        .ui-autocomplete {
            z-index: 1050 !important;
        }
        #calendar-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .fc-view-container {
            font-size: 0.85rem;
        }
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
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
                    <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-edit"></i> Koreksi Absensi</h1>
                    
                    <!-- Notifikasi (jika ada) -->
                    <?php if (isset($_SESSION['notif_success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo bersihkan_input($_SESSION['notif_success']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['notif_success']); ?>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['notif_error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-times-circle"></i> <?php echo bersihkan_input($_SESSION['notif_error']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['notif_error']); ?>
                    <?php endif; ?>

                    <!-- Filter Form -->
                    <div class="d-flex justify-content-between mb-3">
                        <form method="GET" class="form-inline">
                            <label class="mr-2" for="bulan"><i class="fas fa-calendar-alt"></i> Pilih Bulan:</label>
                            <input type="month" name="bulan" id="bulan" class="form-control mr-3" value="<?php echo bersihkan_input($bulan); ?>">
                            <label class="mr-2" for="departemen"><i class="fas fa-building"></i> Departemen:</label>
                            <input type="text" name="departemen" id="departemen" class="form-control mr-3" placeholder="Masukkan departemen" value="<?php echo bersihkan_input($departemen); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Tampilkan
                            </button>
                        </form>
                    </div>

                    <!-- Kalender Hari Libur (optional) -->
                    <div class="mb-4" id="calendar-container">
                        <div id="calendar"></div>
                    </div>

                    <!-- Tabel Absensi dengan Server-side Processing -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold"><i class="fas fa-table"></i> Daftar Absensi</h6>
                        </div>
                        <div class="card-body">
                            <table id="absensiTable" class="table table-sm table-bordered table-striped display nowrap" style="width:100%">
                                <thead>
                                    <tr>
                                        <th class="no-column"><i class="fas fa-hashtag"></i> No</th>
                                        <th>Tanggal</th>
                                        <th>Jadwal</th>
                                        <th>Jam Kerja</th>
                                        <th>Valid</th>
                                        <th>PIN</th>
                                        <th>NIP</th>
                                        <th>Nama</th>
                                        <th>Departemen</th>
                                        <th>Lembur</th>
                                        <th>Jam Masuk</th>
                                        <th>Scan Masuk</th>
                                        <th>Terlambat</th>
                                        <th>Scan Istirahat 1</th>
                                        <th>Scan Istirahat 2</th>
                                        <th>Jam Pulang</th>
                                        <th>Scan Pulang</th>
                                        <th>Jenis Absensi</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>

                    <!-- Modal Edit Absensi -->
                    <div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" aria-labelledby="modalEditLabel" aria-hidden="true">
                      <div class="modal-dialog modal-lg" role="document">
                        <form method="POST" class="modal-content">
                          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                          <input type="hidden" name="action" value="update">
                          <input type="hidden" name="id_absensi" id="edit_id_absensi">
                          <input type="hidden" name="departemen" id="edit_departemen">
                          <input type="hidden" name="bulan" value="<?php echo htmlspecialchars($bulan); ?>">
                          <div class="modal-header">
                            <h5 class="modal-title" id="modalEditLabel"><i class="fas fa-edit"></i> Edit Absensi</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close" title="Tutup Modal">
                              <span aria-hidden="true">&times;</span>
                            </button>
                          </div>
                          <div class="modal-body">
                            <!-- Form Update -->
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                  <label for="edit_tanggal"><i class="fas fa-calendar-alt"></i> Tanggal</label>
                                  <input type="date" name="tanggal" id="edit_tanggal" class="form-control" required>
                                </div>
                                <div class="form-group col-md-3">
                                  <label for="edit_jadwal"><i class="fas fa-clock"></i> Jadwal</label>
                                  <input type="text" name="jadwal" id="edit_jadwal" class="form-control" placeholder="Misal: Guru/Karyawan" autocomplete="off">
                                </div>
                                <div class="form-group col-md-3">
                                  <label for="edit_jam_kerja"><i class="fas fa-business-time"></i> Jam Kerja</label>
                                  <input type="text" name="jam_kerja" id="edit_jam_kerja" class="form-control" placeholder="08:00-16:00" autocomplete="off">
                                </div>
                                <div class="form-group col-md-3">
                                  <label for="edit_valid"><i class="fas fa-check"></i> Valid</label>
                                  <input type="number" name="valid" id="edit_valid" class="form-control" min="0" max="1" placeholder="0 atau 1">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                  <label for="edit_pin"><i class="fas fa-key"></i> PIN</label>
                                  <input type="text" name="pin" id="edit_pin" class="form-control" placeholder="Masukkan PIN" autocomplete="off">
                                </div>
                                <div class="form-group col-md-3">
                                  <label for="edit_nip"><i class="fas fa-id-card"></i> NIP</label>
                                  <input type="text" name="nip" id="edit_nip" class="form-control" placeholder="Masukkan NIP" autocomplete="off">
                                </div>
                                <div class="form-group col-md-6">
                                  <label for="edit_nama"><i class="fas fa-user"></i> Nama</label>
                                  <input type="text" name="nama" id="edit_nama" class="form-control autocomplete-nama" placeholder="Masukkan nama" autocomplete="off">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-2">
                                  <label for="edit_departemen_label"><i class="fas fa-building"></i> Departemen</label>
                                  <input type="text" id="edit_departemen_label" class="form-control" disabled>
                                </div>
                                <div class="form-group col-md-2">
                                  <label for="edit_lembur"><i class="fas fa-hourglass-half"></i> Lembur</label>
                                  <input type="number" name="lembur" id="edit_lembur" class="form-control" placeholder="0/1" min="0" max="1" autocomplete="off">
                                </div>
                                <div class="form-group col-md-2">
                                  <label for="edit_jam_masuk"><i class="fas fa-sign-in-alt"></i> Jam Masuk</label>
                                  <input type="time" name="jam_masuk" id="edit_jam_masuk" class="form-control">
                                </div>
                                <div class="form-group col-md-2">
                                  <label for="edit_scan_masuk"><i class="fas fa-fingerprint"></i> Scan Masuk</label>
                                  <input type="time" name="scan_masuk" id="edit_scan_masuk" class="form-control">
                                </div>
                                <div class="form-group col-md-2">
                                  <label for="edit_terlambat"><i class="fas fa-exclamation-triangle"></i> Terlambat</label>
                                  <div class="form-check">
                                      <input class="form-check-input" type="checkbox" name="terlambat" id="edit_terlambat" value="1">
                                      <label class="form-check-label" for="edit_terlambat">Ya</label>
                                  </div>
                                </div>
                                <div class="form-group col-md-2">
                                  <label for="edit_jenis_absensi"><i class="fas fa-info-circle"></i> Absensi</label>
                                  <select name="jenis_absensi" id="edit_jenis_absensi" class="form-control">
                                      <option value="Normal">Normal</option>
                                      <option value="Izin">Izin</option>
                                      <option value="Sakit">Sakit</option>
                                      <option value="Cuti">Cuti</option>
                                      <option value="Bolos">Bolos</option>
                                      <option value="Libur">Libur</option>
                                      <option value="Lembur">Lembur</option>
                                  </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                  <label for="edit_scan_istirahat_1"><i class="fas fa-utensils"></i> Scan Istirahat 1</label>
                                  <input type="time" name="scan_istirahat_1" id="edit_scan_istirahat_1" class="form-control">
                                </div>
                                <div class="form-group col-md-3">
                                  <label for="edit_scan_istirahat_2"><i class="fas fa-utensils"></i> Scan Istirahat 2</label>
                                  <input type="time" name="scan_istirahat_2" id="edit_scan_istirahat_2" class="form-control">
                                </div>
                                <div class="form-group col-md-3">
                                  <label for="edit_jam_pulang"><i class="fas fa-sign-out-alt"></i> Jam Pulang</label>
                                  <input type="time" name="jam_pulang" id="edit_jam_pulang" class="form-control">
                                </div>
                                <div class="form-group col-md-3">
                                  <label for="edit_scan_pulang"><i class="fas fa-fingerprint"></i> Scan Pulang</label>
                                  <input type="time" name="scan_pulang" id="edit_scan_pulang" class="form-control">
                                </div>
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal" title="Batal"><i class="fas fa-times"></i> Batal</button>
                            <button type="submit" class="btn btn-primary" title="Simpan Perubahan">
                                <i class="fas fa-save"></i> Simpan Perubahan
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>

                    <!-- Modal Delete Absensi -->
                    <div class="modal fade" id="modalDelete" tabindex="-1" role="dialog" aria-labelledby="modalDeleteLabel" aria-hidden="true">
                      <div class="modal-dialog" role="document">
                        <form method="POST" class="modal-content">
                          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id_absensi" id="delete_id_absensi">
                          <input type="hidden" name="departemen" id="delete_departemen" value="<?php echo htmlspecialchars($departemen); ?>">
                          <input type="hidden" name="bulan" value="<?php echo htmlspecialchars($bulan); ?>">
                          <div class="modal-header">
                            <h5 class="modal-title" id="modalDeleteLabel"><i class="fas fa-trash-alt"></i> Hapus Absensi</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close" title="Tutup Modal">
                              <span aria-hidden="true">&times;</span>
                            </button>
                          </div>
                          <div class="modal-body">
                            <p>Apakah Anda yakin ingin menghapus data absensi untuk <strong id="delete_nama"></strong> pada tanggal <strong id="delete_tanggal"></strong>?</p>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal" title="Batal"><i class="fas fa-times"></i> Batal</button>
                            <button type="submit" class="btn btn-danger" title="Hapus"><i class="fas fa-trash"></i> Hapus</button>
                          </div>
                        </form>
                      </div>
                    </div>

                </div>
                <!-- End Page Content -->
            </div>
            <!-- End Main Content -->
            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?php echo date("Y"); ?> Sistem Nusaputera</span>
                    </div>
                </div>
            </footer>
            <!-- End Footer -->
        </div>
        <!-- End Content Wrapper -->
    </div>
    <!-- End Page Wrapper -->

    <!-- JavaScript Dependencies -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/dataTables.buttons.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.html5.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.print.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.colVis.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
    <!-- FullCalendar -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script nonce="<?php echo $nonce; ?>">
    $(document).ready(function() {
        // Inisialisasi DataTables dengan server-side processing
        var table = $('#absensiTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: 'koreksi_absensi.php',
                type: 'POST',
                data: {
                    bulan: "<?php echo bersihkan_input($bulan); ?>",
                    departemen: "<?php echo bersihkan_input($departemen); ?>"
                }
            },
            language: {
                url: "https://cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
            },
            dom: 'Bfrtip',
            buttons: [
                { extend: 'copyHtml5', text: '<i class="fas fa-copy"></i> Copy', className: 'btn btn-secondary btn-sm' },
                { extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn btn-success btn-sm' },
                { extend: 'pdfHtml5', text: '<i class="fas fa-file-pdf"></i> PDF', className: 'btn btn-danger btn-sm' },
                { extend: 'print', text: '<i class="fas fa-print"></i> Print', className: 'btn btn-info btn-sm' },
                { extend: 'colvis', text: '<i class="fas fa-columns"></i> Kolom', className: 'btn btn-warning btn-sm' }
            ],
            responsive: true,
            autoWidth: false,
            pageLength: 10,
            columnDefs: [
                { orderable: false, targets: 18 }
            ]
        });

        // Tooltip
        $('[data-toggle="tooltip"]').tooltip();

        // Modal Edit: Isi data saat modal ditampilkan
        $('#modalEdit').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget); 
            var modal  = $(this);
            modal.find('#edit_id_absensi').val(button.data('id'));
            modal.find('#edit_tanggal').val(button.data('tanggal'));
            modal.find('#edit_jadwal').val(button.data('jadwal'));
            modal.find('#edit_jam_kerja').val(button.data('jam_kerja'));
            modal.find('#edit_valid').val(button.data('valid'));
            modal.find('#edit_pin').val(button.data('pin'));
            modal.find('#edit_nip').val(button.data('nip'));
            modal.find('#edit_nama').val(button.data('nama'));
            modal.find('#edit_departemen_label').val(button.data('departemen'));
            modal.find('#edit_departemen').val(button.data('departemen'));
            modal.find('#edit_lembur').val(button.data('lembur'));
            modal.find('#edit_jam_masuk').val(button.data('jam_masuk'));
            modal.find('#edit_scan_masuk').val(button.data('scan_masuk'));
            if (button.data('terlambat') == 1) {
                modal.find('#edit_terlambat').prop('checked', true);
            } else {
                modal.find('#edit_terlambat').prop('checked', false);
            }
            modal.find('#edit_scan_istirahat_1').val(button.data('scan_istirahat_1'));
            modal.find('#edit_scan_istirahat_2').val(button.data('scan_istirahat_2'));
            modal.find('#edit_jam_pulang').val(button.data('jam_pulang'));
            modal.find('#edit_scan_pulang').val(button.data('scan_pulang'));
            modal.find('#edit_jenis_absensi').val(button.data('jenis_absensi'));
        });

        // Modal Delete: Isi data saat modal ditampilkan
        $('#modalDelete').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var modal  = $(this);
            modal.find('#delete_id_absensi').val(button.data('id'));
            modal.find('#delete_nama').text(button.data('nama'));
            modal.find('#delete_tanggal').text(button.data('tanggal'));
        });

        // Reset form saat modal tertutup
        $('#modalEdit, #modalDelete').on('hidden.bs.modal', function () {
            $(this).find('form')[0].reset();
        });

        // Tampilkan spinner saat form disubmit
        $('form').on('submit', function(e) {
            var $form = $(this);
            var $btn  = $form.find('button[type="submit"]');
            $btn.prop('disabled', true);
            $btn.find('.spinner-border').removeClass('d-none');
        });

        // Inisialisasi FullCalendar (optional)
        $('#calendar').fullCalendar({
            header: {
                left: 'prev,next today',
                center: 'title',
                right: 'month,agendaWeek,agendaDay'
            },
            editable: false,
            events: [] // Tambahkan event hari libur jika diperlukan
        });

        // Autocomplete untuk nama karyawan
        var namaKaryawan = <?php echo json_encode($namaKaryawan); ?>;
        $(".autocomplete-nama").autocomplete({
            source: namaKaryawan,
            minLength: 2
        });

        // Fade out alert setelah 3 detik
        window.setTimeout(function () {
            $(".alert").fadeTo(500, 0).slideUp(500, function () {
                $(this).remove();
            });
        }, 3000);
    });
    </script>
</body>
</html>
