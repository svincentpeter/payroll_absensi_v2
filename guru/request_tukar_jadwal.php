<?php
// request_tukar_jadwal.php
   
$pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

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

/**
 * Fungsi untuk menerjemahkan nama bulan dan hari ke Bahasa Indonesia.
 */
function translate_month_dashboard($month_eng)
{
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

function translate_day_dashboard($day_eng)
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

if (!function_exists('translate_month')) {
    function translate_month($month_eng)
    {
        return translate_month_dashboard($month_eng);
    }
}
if (!function_exists('translate_day')) {
    function translate_day($day_eng)
    {
        return translate_day_dashboard($day_eng);
    }
}

/**
 * PENGAMBILAN DATA:
 * - Ambil data jadwal_piket milik guru (sebagai referensi)
 */
$sql = "SELECT * FROM jadwal_piket WHERE nip = ? ORDER BY tanggal ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nip);
$stmt->execute();
$result = $stmt->get_result();
$jadwal_guru = [];
while ($row = $result->fetch_assoc()) {
    $jadwal_guru[] = $row;
}
$stmt->close();
$has_schedule = !empty($jadwal_guru);

/**
 * Ambil daftar request tukar jadwal untuk guru tujuan.
 * Jika guru memiliki jadwal, gunakan id_jadwal dari jadwal_guru untuk membuat filter.
 */
if ($has_schedule) {
    $jadwal_ids = array_map(function ($j) {
        return $j['id_jadwal'];
    }, $jadwal_guru);
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
    $request_list = [];
    while ($row = $result->fetch_assoc()) {
        $request_list[] = $row;
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
    $request_list = [];
    while ($row = $result->fetch_assoc()) {
        $request_list[] = $row;
    }
    $stmt->close();
}

/**
 * PROSES RESPON REQUEST TUKAR JADWAL (Accept / Reject)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id_request'])) {
    // Verifikasi CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $action = $_POST['action']; // "accept" atau "reject"
    $id_request = intval($_POST['id_request'] ?? 0);
    if ($id_request > 0) {
        // Ambil data request
        $sql = "SELECT * FROM permintaan_tukar_jadwal WHERE id = ? AND status = 'Pending'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_request);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();
        $stmt->close();
        if ($request) {
            // Pastikan hanya guru tujuan yang dapat merespon
            if ($request['nip_tujuan'] !== $nip) {
                $_SESSION['swap_error'] = "Anda tidak memiliki akses untuk merespon request ini.";
                header("Location: request_tukar_jadwal.php");
                exit();
            }
            if ($action === 'accept') {
                if ($request['id_jadwal_tujuan'] === NULL) {
                    // Guru tujuan belum memiliki jadwal pending: update jadwal pengaju
                    $jadwal_id = $request['id_jadwal_pengaju'];
                    // Ambil data jadwal pengaju
                    $sql_jp = "SELECT * FROM jadwal_piket WHERE id_jadwal = ?";
                    $stmt = $conn->prepare($sql_jp);
                    $stmt->bind_param("i", $jadwal_id);
                    $stmt->execute();
                    $result_jp = $stmt->get_result();
                    $jadwal_pengaju = $result_jp->fetch_assoc();
                    $stmt->close();
                    if (!$jadwal_pengaju) {
                        $_SESSION['swap_error'] = "Data jadwal pengaju tidak ditemukan.";
                        header("Location: request_tukar_jadwal.php");
                        exit();
                    }
                    // Ambil nama guru tujuan dari database
                    $sql_nama = "SELECT nama FROM anggota_sekolah WHERE nip = ?";
                    $stmt = $conn->prepare($sql_nama);
                    $stmt->bind_param("s", $request['nip_tujuan']);
                    $stmt->execute();
                    $result_nama = $stmt->get_result();
                    $data_nama = $result_nama->fetch_assoc();
                    $nama_tujuan = $data_nama['nama'] ?? '';
                    $stmt->close();
                    if (empty($nama_tujuan)) {
                        $_SESSION['swap_error'] = "Nama guru tujuan tidak ditemukan.";
                        header("Location: request_tukar_jadwal.php");
                        exit();
                    }
                    // Update jadwal pengaju: ganti nip dan nama_guru dengan guru tujuan
                    $sql_update = "UPDATE jadwal_piket SET nip = ?, nama_guru = ? WHERE id_jadwal = ?";
                    $stmt = $conn->prepare($sql_update);
                    $stmt->bind_param("ssi", $nip, $nama_tujuan, $jadwal_id);
                    if ($stmt->execute()) {
                        $stmt->close();
                        // Update request: set id_jadwal_tujuan dan status jadi Diterima
                        $sql_update_req = "UPDATE permintaan_tukar_jadwal SET id_jadwal_tujuan = ?, status = 'Diterima' WHERE id = ?";
                        $stmt = $conn->prepare($sql_update_req);
                        $stmt->bind_param("ii", $jadwal_id, $id_request);
                        $stmt->execute();
                        $stmt->close();
                        $_SESSION['swap_success'] = "Request diterima. Jadwal pada tanggal " . date('d F Y', strtotime($jadwal_pengaju['tanggal'])) . " kini telah dipindahkan ke Anda.";
                    } else {
                        $_SESSION['swap_error'] = "Gagal mengupdate jadwal untuk guru tujuan.";
                    }
                } else {
                    // Jika id_jadwal_tujuan tidak NULL, lakukan swap jadwal antara guru pengaju dan guru tujuan.
                    $id_pengaju = $request['id_jadwal_pengaju'];
                    $id_tujuan = $request['id_jadwal_tujuan'];
                    $conn->begin_transaction();
                    try {
                        $sql_update = "UPDATE jadwal_piket SET nip = ?, nama_guru = ? WHERE id_jadwal = ?";
                        $stmt = $conn->prepare($sql_update);
                        // Update jadwal pengaju: set nip menjadi guru tujuan
                        $stmt->bind_param("ssi", $request['nip_tujuan'], '', $id_pengaju);
                        $stmt->execute();
                        // Update jadwal tujuan: set nip menjadi guru pengaju ($nip)
                        $stmt->bind_param("ssi", $nip, '', $id_tujuan);
                        $stmt->execute();
                        $stmt->close();
                        $sql_update_status = "UPDATE permintaan_tukar_jadwal SET status = 'Diterima' WHERE id = ?";
                        $stmt = $conn->prepare($sql_update_status);
                        $stmt->bind_param("i", $id_request);
                        $stmt->execute();
                        $stmt->close();
                        $conn->commit();
                        $_SESSION['swap_success'] = "Request tukar jadwal telah diterima dan jadwal telah ditukar.";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $_SESSION['swap_error'] = "Gagal menerima request tukar jadwal: " . $e->getMessage();
                    }
                }
            } elseif ($action === 'reject') {
                $sql = "UPDATE permintaan_tukar_jadwal SET status = 'Ditolak' WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id_request);
                if ($stmt->execute()) {
                    $_SESSION['swap_success'] = "Request tukar jadwal telah ditolak.";
                } else {
                    $_SESSION['swap_error'] = "Gagal menolak request tukar jadwal.";
                }
                $stmt->close();
            }
        } else {
            $_SESSION['swap_error'] = "Request tukar jadwal tidak ditemukan atau sudah diproses.";
        }
    } else {
        $_SESSION['swap_error'] = "Data respon tidak valid.";
    }
    header("Location: request_tukar_jadwal.php");
    exit();
}

/**
 * PROSES REQUEST TUKAR JADWAL (Pengajuan Request)
 * Jika guru tujuan sudah terdaftar di jadwal untuk tanggal yang sama, sistem akan mengecek jumlah guru.
 * Jika jumlah guru tepat 2, request tidak diizinkan.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tukar_jadwal'])) {
    // Verifikasi CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $id_jadwal_pengaju = intval($_POST['id_jadwal_pengaju'] ?? 0);
    $guru_nip_tujuan = trim($_POST['guru_nip_tujuan'] ?? '');
    if ($id_jadwal_pengaju > 0 && !empty($guru_nip_tujuan)) {
        if ($nip !== $_SESSION['nip']) {
            $_SESSION['absensi_error'] = "Anda tidak memiliki akses.";
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
        $id_jadwal_tujuan = $data_tujuan ? $data_tujuan['id_jadwal'] : NULL;

        // Ambil data jadwal pengaju (tanggal dan waktu_piket)
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

        // Jika guru tujuan sudah terdaftar di jadwal pada tanggal dan waktu tersebut, cek jumlah guru
        if ($id_jadwal_tujuan !== NULL) {
            $sql_count = "SELECT COUNT(*) AS total FROM jadwal_piket WHERE tanggal = ? AND waktu_piket = ?";
            $stmt = $conn->prepare($sql_count);
            $stmt->bind_param("ss", $tanggal_request, $waktu_piket);
            $stmt->execute();
            $result_count = $stmt->get_result();
            $data_count = $result_count->fetch_assoc();
            $total_count = $data_count['total'];
            $stmt->close();
            // Jika tepat dua guru terdaftar, tolak request
            if ($total_count == 2) {
                $_SESSION['absensi_error'] = "Tidak bisa request pertukaran jadwal karena hanya ada dua guru yang terdaftar!";
                header("Location: dashboard_jadwal.php");
                exit();
            }
        }

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
            // Simpan request tukar jadwal dengan tanggal_piket dari jadwal pengaju
            $sql_insert = "INSERT INTO permintaan_tukar_jadwal 
                (id_jadwal_pengaju, id_jadwal_tujuan, nip_tujuan, status, nip_pengaju, nama_pengaju, tanggal_permintaan, tanggal_piket)
                VALUES (?, ?, ?, 'Pending', ?, ?, NOW(), ?)";
            $stmt = $conn->prepare($sql_insert);
            // Asumsikan nama_pengaju sudah tersedia dari session (atau diambil sebelumnya)
            $nama_pengaju = $_SESSION['nama'] ?? '';
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
 * PENGAMBILAN DATA UNTUK TAMPILAN (Dashboard)
 */
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
    <title>Request Tukar Jadwal Piket</title>
    <!-- FontAwesome, Bootstrap 5.3.3, dan SB Admin 2 CSS via CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SB Admin 2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <style>
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }

        .badge-diterima {
            background-color: #28a745;
            color: #fff;
        }

        .badge-ditolak {
            background-color: #dc3545;
            color: #fff;
        }

        .badge-secondary {
            background-color: #6c757d;
            color: #fff;
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
                    <h1 class="h3 mb-4 text-gray-800">Request Tukar Jadwal Piket</h1>

                    <!-- Notifikasi -->
                    <?php if (isset($_SESSION['swap_success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['swap_success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['swap_success']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['swap_error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['swap_error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['swap_error']); ?>
                    <?php endif; ?>

                    <!-- Tampilan Request Tukar Jadwal (Untuk Guru Tujuan) -->
                    <div class="table-responsive">
                        <table class="table table-bordered text-center">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>ID Request</th>
                                    <th>Nama Guru Pengaju</th>
                                    <th>Tanggal Request (Tanggal Piket)</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($request_list)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Tidak ada request tukar jadwal.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1;
                                    foreach ($request_list as $req): ?>
                                        <tr>
                                            <td><?= $no++; ?></td>
                                            <td><?= htmlspecialchars($req['id']); ?></td>
                                            <td><?= htmlspecialchars($req['nama_guru_pengaju']); ?></td>
                                            <td>
                                                <?= htmlspecialchars(date('d F Y', strtotime($req['tanggal_piket']))); ?><br>
                                                <?= htmlspecialchars(translate_day(date('D', strtotime($req['tanggal_piket'])))); ?>
                                            </td>
                                            <td><span class="badge badge-pending"><?= htmlspecialchars($req['status']); ?></span></td>
                                            <td>
                                                <form method="POST" action="request_tukar_jadwal.php" class="d-inline-block">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                                    <input type="hidden" name="id_request" value="<?= $req['id']; ?>">
                                                    <input type="hidden" name="action" value="accept">
                                                    <button type="submit" class="btn btn-success btn-sm">Terima</button>
                                                </form>
                                                &nbsp;
                                                <form method="POST" action="request_tukar_jadwal.php" class="d-inline-block">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                                    <input type="hidden" name="id_request" value="<?= $req['id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn btn-danger btn-sm">Tolak</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div><!-- End Container Fluid -->
            </div><!-- End Content -->
        </div><!-- End Content Wrapper -->
    </div><!-- End Wrapper -->

    <!-- JavaScript: Bootstrap 5.3.3, jQuery, dan SB Admin 2 JS via CDN -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Jika menggunakan modal untuk respon (jika diperlukan), set nilai id_request pada modal
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