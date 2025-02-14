<?php
// File: /payroll_absensi_v2/koreksi_absensi.php

// ==============================================================================
// 1. Pengaturan Awal & Keamanan
// ==============================================================================

session_start();
require_once __DIR__ . '/../../helpers.php';
require_once __DIR__ . '/../../koneksi.php';

// Hapus output buffering jika ada
if (ob_get_length()) ob_end_clean();

// Pastikan hanya role sdm dan superadmin yang boleh mengakses halaman ini
authorize(['sdm', 'superadmin'], '/payroll_absensi_v2/login.php');


// Generate CSRF token (jika belum ada)
generate_csrf_token();

// ==============================================================================
// 2. Fungsi Tambahan Khusus Koreksi Absensi
// ==============================================================================

function get_nama_karyawan($conn) {
    $sql = "SELECT nama FROM anggota_sekolah GROUP BY nama";
    $result = mysqli_query($conn, $sql);
    $nama = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $nama[] = $row['nama'];
    }
    return $nama;
}

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

// ==============================================================================
// 3. Proses POST: Update, Delete, dan Server-side DataTables
// ==============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pastikan CSRF token valid untuk semua proses POST
    if (!isset($_POST['csrf_token'])) {
        send_response(403, 'Token CSRF tidak ditemukan.');
    }
    verify_csrf_token($_POST['csrf_token']);

    // Proses Update dan Delete (dengan adanya parameter action)
    if (isset($_POST['action'])) {
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
            // Sanitasi dan ambil input
            $tanggal          = $_POST['tanggal'] ?? '';
            $jadwal           = $_POST['jadwal'] ?? '';
            $jam_kerja        = $_POST['jam_kerja'] ?? '';
            $valid            = isset($_POST['valid']) ? (int) $_POST['valid'] : 0;
            $pin              = $_POST['pin'] ?? '';
            $nip              = $_POST['nip'] ?? '';
            $nama             = $_POST['nama'] ?? '';
            $departemen       = $departemen_post;
            $lembur           = isset($_POST['lembur']) ? (int) $_POST['lembur'] : 0;
            $jam_masuk        = $_POST['jam_masuk'] ?? '';
            $scan_masuk       = $_POST['scan_masuk'] ?? '';
            $terlambat        = isset($_POST['terlambat']) ? 1 : 0;
            $jam_pulang       = $_POST['jam_pulang'] ?? '';
            $scan_pulang      = $_POST['scan_pulang'] ?? '';
            $jenis_absensi    = $_POST['jenis_absensi'] ?? 'Normal';
            $scan_istirahat_1 = $_POST['scan_istirahat_1'] ?? '';
            $scan_istirahat_2 = $_POST['scan_istirahat_2'] ?? '';

            // Validasi format tanggal (Y-m-d)
            $dtTanggal = DateTime::createFromFormat('Y-m-d', $tanggal);
            if (!$dtTanggal || $dtTanggal->format('Y-m-d') !== $tanggal) {
                $_SESSION['notif_error'] = "Format tanggal tidak valid.";
                header("Location: koreksi_absensi.php");
                exit;
            }
            // Validasi format waktu (HH:MM) untuk field yang diisi
            $time_fields = ['jam_masuk','scan_masuk','jam_pulang','scan_pulang','scan_istirahat_1','scan_istirahat_2'];
            foreach ($time_fields as $field) {
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
                $departemen,
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
                $audit_details = "Mengupdate data absensi ID $id_absensi. Data: tanggal=$tanggal, jadwal=$jadwal, jam_kerja=$jam_kerja, valid=$valid, pin=$pin, nip=$nip, nama=$nama, departemen=$departemen, lembur=$lembur, jam_masuk=$jam_masuk, scan_masuk=$scan_masuk_datetime, terlambat=$terlambat, scan_istirahat_1=$scan_istirahat_1_datetime, scan_istirahat_2=$scan_istirahat_2_datetime, jam_pulang=$jam_pulang, scan_pulang=$scan_pulang_datetime, jenis_absensi=$jenis_absensi.";
                // MODIFIKASI: Gunakan NIP untuk audit log
                add_audit_log($conn, $_SESSION['nip'], 'UpdateAbsensi', $audit_details);
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
                // MODIFIKASI: Gunakan NIP untuk audit log
                add_audit_log($conn, $_SESSION['nip'], 'DeleteAbsensi', "Menghapus data absensi ID $id_absensi.");
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

        $bulan      = isset($_POST['bulan']) ? mysqli_real_escape_string($conn, $_POST['bulan']) : '';
        $departemen = isset($_POST['departemen']) ? mysqli_real_escape_string($conn, $_POST['departemen']) : '';

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

        $resTotal = mysqli_query($conn, "SELECT COUNT(*) as total $sql");
        $totalData = mysqli_fetch_assoc($resTotal)['total'];

        $searchValue = $_POST['search']['value'] ?? '';
        if (!empty($searchValue)) {
            $searchValue = mysqli_real_escape_string($conn, $searchValue);
            $sql .= " AND (a.nama LIKE '%$searchValue%' OR a.nip LIKE '%$searchValue%' OR a.departemen LIKE '%$searchValue%') ";
        }
        $resTotalFiltered = mysqli_query($conn, "SELECT COUNT(*) as total $sql");
        $totalFiltered = mysqli_fetch_assoc($resTotalFiltered)['total'];

        $orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
        $orderColumn = $columns[$orderColumnIndex] ?? $columns[0];
        $orderDir = in_array($_POST['order'][0]['dir'] ?? 'asc', ['asc', 'desc']) ? $_POST['order'][0]['dir'] : 'asc';
        $sql .= " ORDER BY $orderColumn $orderDir ";

        $start  = intval($_POST['start'] ?? 0);
        $length = intval($_POST['length'] ?? 10);
        $sql .= " LIMIT $start, $length ";

        $query = "SELECT " . implode(", ", $columns) . " $sql";
        $resData = mysqli_query($conn, $query);

        $data = [];
        $no = $start + 1;
        while ($row = mysqli_fetch_assoc($resData)) {
            // Format waktu untuk kolom yang relevan
            $jamMasuk   = !empty($row['jam_masuk']) ? date('H:i', strtotime($row['jam_masuk'])) : '-';
            $scanMasuk  = !empty($row['scan_masuk']) ? date('H:i', strtotime($row['scan_masuk'])) : '-';
            $jamPulang  = !empty($row['jam_pulang']) ? date('H:i', strtotime($row['jam_pulang'])) : '-';
            $scanPulang = !empty($row['scan_pulang']) ? date('H:i', strtotime($row['scan_pulang'])) : '-';

            // Buat dropdown aksi (tombol edit dan delete)
            $aksi = '
            <div class="dropdown">
              <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton_'.$row['id'].'" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-v"></i>
              </button>
              <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton_'.$row['id'].'">
                <li>
                  <a class="dropdown-item btn-edit" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalEdit"
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
                    <i class="fas fa-edit"></i> Edit Absensi
                  </a>
                </li>
                <li>
                  <a class="dropdown-item btn-delete" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalDelete"
                     data-id="'.$row['id'].'"
                     data-nama="'.bersihkan_input($row['nama']).'"
                     data-tanggal="'.bersihkan_input($row['tanggal']).'"
                     title="Hapus Absensi">
                    <i class="fas fa-trash-alt"></i> Hapus Absensi
                  </a>
                </li>
              </ul>
            </div>
            ';

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
            $nestedData[] = $row['terlambat'] ? '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i> Ya</span>' : '<span class="badge bg-success"><i class="fas fa-check"></i> Tidak</span>';
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
            $nestedData[] = '<span class="badge ' . $badge . '"><i class="fas fa-check-circle"></i> ' . ucfirst($jenis) . '</span>';
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

// ==============================================================================
// 4. Tampilan HTML
// ==============================================================================

// Ambil filter GET untuk tampilan
$bulan      = $_GET['bulan'] ?? '';
$departemen = $_GET['departemen'] ?? '';
$namaKaryawan = get_nama_karyawan($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Absensi</title>
    <!-- Custom fonts and styles -->
    <link href="../../assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="../../assets/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../../assets/vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link href="../../assets/vendor/datatables/buttons.bootstrap4.min.css" rel="stylesheet">
    <link href="../../assets/vendor/datatables/responsive.bootstrap4.min.css" rel="stylesheet">
    <style>
        /* Custom styles here */
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include __DIR__ . '/../../navbar.php'; ?>
                <?php include __DIR__ . '/../../breadcrumb.php'; ?>
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Upload Absensi</h1>
                    <?php if ($message): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <i class="fas fa-info-circle"></i> <?= htmlspecialchars($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Form Upload Absensi</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                                <div class="mb-3">
                                    <label for="departemen" class="form-label">Pilih Departemen</label>
                                    <select name="departemen" id="departemen" class="form-control" required>
                                        <option value="">--Pilih--</option>
                                        <option value="TK">TK</option>
                                        <option value="SD">SD</option>
                                        <option value="SMP">SMP</option>
                                        <option value="SMA">SMA</option>
                                        <option value="SMK">SMK</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="file_absensi" class="form-label">Upload File Absensi (Excel, .xls/.xlsx)</label>
                                    <input type="file" name="file_absensi" id="file_absensi" class="form-control" accept=".xls,.xlsx" required>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Upload & Import
                                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php if (!empty($logDetails) && is_iterable($logDetails)): ?>
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Detail Import</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" id="logTable" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>Baris</th>
                                                <th>Status</th>
                                                <th>Alasan</th>
                                            </tr>
                                        </thead>
                                        <tfoot>
                                            <tr>
                                                <th>Baris</th>
                                                <th>Status</th>
                                                <th>Alasan</th>
                                            </tr>
                                        </tfoot>
                                        <tbody>
                                            <?php foreach ($logDetails as $detail): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($detail['row']) ?></td>
                                                    <td>
                                                        <?php if ($detail['status'] === 'success'): ?>
                                                            <span class="badge bg-success">Sukses</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Gagal</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($detail['reason']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; Your Website 2024 | <a href="#">Privacy Policy</a> | <a href="#">Contact</a></span>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    <script src="../../assets/vendor/jquery/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../../assets/js/sb-admin-2.min.js"></script>
    <script src="../../assets/vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="../../assets/vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script src="../../assets/vendor/datatables/dataTables.buttons.min.js"></script>
    <script src="../../assets/vendor/datatables/buttons.bootstrap4.min.js"></script>
    <script src="../../assets/vendor/datatables/jszip.min.js"></script>
    <script src="../../assets/vendor/datatables/pdfmake.min.js"></script>
    <script src="../../assets/vendor/datatables/vfs_fonts.js"></script>
    <script src="../../assets/vendor/datatables/buttons.html5.min.js"></script>
    <script src="../../assets/vendor/datatables/buttons.print.min.js"></script>
    <script src="../../assets/vendor/datatables/buttons.colVis.min.js"></script>
    <script src="../../assets/vendor/datatables/dataTables.responsive.min.js"></script>
    <script src="../../assets/vendor/datatables/responsive.bootstrap4.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#logTable').DataTable({
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
            },
            "dom": 'Bfrtip',
            "buttons": [
                {
                    extend: 'copyHtml5',
                    text: '<i class="fas fa-copy"></i> Copy',
                    className: 'btn btn-secondary btn-sm'
                },
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Excel',
                    className: 'btn btn-success btn-sm'
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    className: 'btn btn-danger btn-sm'
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print"></i> Print',
                    className: 'btn btn-info btn-sm'
                },
                {
                    extend: 'colvis',
                    text: '<i class="fas fa-columns"></i> Kolom',
                    className: 'btn btn-warning btn-sm'
                }
            ],
            "responsive": true,
            "pageLength": 10
        });

        $('[data-toggle="tooltip"]').tooltip();

        window.setTimeout(function () {
            $(".alert").fadeTo(500, 0).slideUp(500, function () {
                $(this).remove();
            });
        }, 3000);

        $('#uploadForm').on('submit', function(e) {
            var $btn = $(this).find('button[type="submit"]');
            $btn.prop('disabled', true);
            $btn.find('.spinner-border').removeClass('d-none');
        });
    });
    </script>
</body>
</html>
