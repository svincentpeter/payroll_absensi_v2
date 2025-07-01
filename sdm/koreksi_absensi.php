<?php
// File: /payroll_absensi_v2/koreksi_absensi.php

// ==============================================================================
// 1. Pengaturan Awal & Inisialisasi
// ==============================================================================
$pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../koneksi.php';

start_session_safe();
init_error_handling();
authorize(['M:SDM']); // Hanya SDM 
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

// Hapus output buffering jika ada
if (ob_get_length()) {
  ob_end_clean();
}

// ==============================================================================
// 2. Konstanta
// ==============================================================================
define('JENIS_ABSENSI', [
  'Normal',
  'Izin',
  'Sakit',
  'Cuti',
  'Bolos',
  'Libur',
  'Lembur'
]);

// ==============================================================================
// 3. Helper Lokal
// ==============================================================================
function validate_date(string $date): bool
{
  $d = DateTime::createFromFormat('Y-m-d', $date);
  return $d && $d->format('Y-m-d') === $date;
}
function validate_time(string $time): bool
{
  $t = DateTime::createFromFormat('H:i', $time);
  return $t && $t->format('H:i') === $time;
}
function get_absensi_field(mysqli $conn, int $id_absensi, string $field)
{
  $stmt = $conn->prepare("SELECT `$field` FROM absensi WHERE id = ?");
  $stmt->bind_param('i', $id_absensi);
  $stmt->execute();
  $stmt->bind_result($val);
  $stmt->fetch();
  $stmt->close();
  return $val;
}
function log_before_delete(mysqli $conn, int $id_absensi, string $nip): void
{
  $res = $conn->query("SELECT * FROM absensi WHERE id = {$id_absensi}");
  $row = $res->fetch_assoc();
  add_audit_log($conn, $nip, 'DeleteAbsensi', "Data before delete: " . json_encode($row));
}
function map_status_kehadiran(string $jenis): string
{
  switch (strtolower($jenis)) {
    case 'bolos':
      return 'tanpa_keterangan';
    case 'izin':
    case 'sakit':
    case 'cuti':
    case 'libur':
      return strtolower($jenis);
    default:
      return 'hadir';
  }
}
/**
 * Ambil daftar nama karyawan untuk autocomplete
 */
function get_nama_karyawan(mysqli $conn): array
{
  $sql    = "SELECT nama FROM anggota_sekolah GROUP BY nama";
  $result = mysqli_query($conn, $sql);
  $names  = [];
  while ($row = mysqli_fetch_assoc($result)) {
    $names[] = $row['nama'];
  }
  return $names;
}

// ==============================================================================
// 4. Handler POST
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf_token($_POST['csrf_token'] ?? '');

  // 4a. Server-side DataTables
  if (isset($_POST['draw'])) {
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
    $bulan      = mysqli_real_escape_string($conn, $_POST['bulan'] ?? '');
    $departemen = mysqli_real_escape_string($conn, $_POST['departemen'] ?? '');
    $jenisAbsensi = mysqli_real_escape_string($conn, $_POST['jenis_absensi'] ?? '');
    $sql = "FROM absensi a
                LEFT JOIN anggota_sekolah g ON a.nip=g.nip
                LEFT JOIN holidays h ON a.tanggal=h.holiday_date
                WHERE 1=1";
    if ($bulan)      $sql .= " AND DATE_FORMAT(a.tanggal,'%Y-%m')='$bulan'";
    if ($departemen) $sql .= " AND UPPER(a.departemen)=UPPER('$departemen')";
    if ($jenisAbsensi) $sql .= " AND a.jenis_absensi = '$jenisAbsensi'";
    $totalData = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total $sql"))['total'];
    $search = mysqli_real_escape_string($conn, $_POST['search']['value'] ?? '');
    if ($search) {
      $sql .= " AND (a.nama LIKE '%{$search}%' OR a.nip LIKE '%{$search}%' OR a.departemen LIKE '%{$search}%')";
    }
    $totalFiltered = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total $sql"))['total'];
    $orderColIdx = intval($_POST['order'][0]['column'] ?? 0);
    $orderDir    = in_array($_POST['order'][0]['dir'] ?? 'asc', ['asc', 'desc']) ? $_POST['order'][0]['dir'] : 'asc';
    $sql .= " ORDER BY {$columns[$orderColIdx]} {$orderDir}";
    $start  = intval($_POST['start']  ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $sql .= " LIMIT {$start},{$length}";
    $res = mysqli_query($conn, "SELECT " . implode(',', $columns) . " $sql");
    $data = [];
    $no = $start + 1;
    while ($row = mysqli_fetch_assoc($res)) {
      $jamMasuk   = $row['jam_masuk']   ? date('H:i', strtotime($row['jam_masuk'])) : '-';
      $scanMasuk  = $row['scan_masuk']  ? date('H:i', strtotime($row['scan_masuk'])) : '-';
      $jamPulang  = $row['jam_pulang']  ? date('H:i', strtotime($row['jam_pulang'])) : '-';
      $scanPulang = $row['scan_pulang'] ? date('H:i', strtotime($row['scan_pulang'])) : '-';
      $aksi = '<div class="dropdown">
            <button class="btn btn-sm" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item btn-edit" href="#" data-bs-toggle="modal" data-bs-target="#modalEdit"
                   '
    . 'data-id="' . $row['id'] . '" '
    . 'data-tanggal="' . htmlspecialchars($row['tanggal'] ?? '') . '" '
    . 'data-jadwal="' . htmlspecialchars($row['jadwal'] ?? '') . '" '
    . 'data-jam_kerja="' . htmlspecialchars($row['jam_kerja'] ?? '') . '" '
    . 'data-valid="' . $row['valid'] . '" '
    . 'data-pin="' . htmlspecialchars($row['pin'] ?? '') . '" '
    . 'data-nip="' . htmlspecialchars($row['nip'] ?? '') . '" '
    . 'data-nama="' . htmlspecialchars($row['nama'] ?? '') . '" '
    . 'data-departemen="' . htmlspecialchars($row['departemen'] ?? '') . '" '
    . 'data-lembur="' . $row['lembur'] . '" '
    . 'data-jam_masuk="' . ($jamMasuk === '-' ? '' : $jamMasuk) . '" '
    . 'data-scan_masuk="' . ($scanMasuk === '-' ? '' : $scanMasuk) . '" '
    . 'data-terlambat="' . $row['terlambat'] . '" '
    . 'data-scan_istirahat_1="' . htmlspecialchars($row['scan_istirahat_1'] ?? '') . '" '
    . 'data-scan_istirahat_2="' . htmlspecialchars($row['scan_istirahat_2'] ?? '') . '" '
    . 'data-jam_pulang="' . ($jamPulang === '-' ? '' : $jamPulang) . '" '
    . 'data-scan_pulang="' . ($scanPulang === '-' ? '' : $scanPulang) . '" '
    . 'data-jenis_absensi="' . htmlspecialchars($row['jenis_absensi'] ?? '') . '"'
    . '><i class="fas fa-edit"></i> Edit</a></li>
              <li><a class="dropdown-item btn-delete" href="#" data-bs-toggle="modal" data-bs-target="#modalDelete"
                   data-id="' . $row['id'] . '" 
                   data-nama="' . htmlspecialchars($row['nama'] ?? '') . '" 
                   data-tanggal="' . htmlspecialchars($row['tanggal'] ?? '') . '">
                   <i class="fas fa-trash-alt"></i> Hapus</a></li>
            </ul>
          </div>';

      $badgeJenis = 'badge-secondary';
      switch (strtolower($row['jenis_absensi'])) {
        case 'izin':
          $badgeJenis = 'badge-info';
          break;
        case 'sakit':
          $badgeJenis = 'badge-warning';
          break;
        case 'cuti':
          $badgeJenis = 'badge-primary';
          break;
        case 'bolos':
          $badgeJenis = 'badge-danger';
          break;
        case 'libur':
          $badgeJenis = 'badge-success';
          break;
        case 'lembur':
          $badgeJenis = 'badge-info';
          break;
      }
      $nested = [
  $no++,
  htmlspecialchars($row['tanggal'] ?? ''),
  htmlspecialchars($row['jadwal'] ?? ''),
  htmlspecialchars($row['jam_kerja'] ?? ''),
  $row['valid'] ? '1' : '0',
  htmlspecialchars($row['pin'] ?? ''),
  htmlspecialchars($row['nip'] ?? ''),
  htmlspecialchars($row['nama'] ?? ''),
  htmlspecialchars(strtoupper($row['departemen'] ?? '')),
  htmlspecialchars($row['lembur'] ?? ''),
  htmlspecialchars($jamMasuk ?? ''),
  htmlspecialchars($scanMasuk ?? ''),
  $row['terlambat'] ? '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i> Ya</span>' : '<span class="badge bg-success"><i class="fas fa-check"></i> Tidak</span>',
  htmlspecialchars($row['scan_istirahat_1'] ?: '-'),
  htmlspecialchars($row['scan_istirahat_2'] ?: '-'),
  htmlspecialchars($jamPulang ?? ''),
  htmlspecialchars($scanPulang ?? ''),
  '<span class="badge ' . $badgeJenis . '"><i class="fas fa-check-circle"></i> ' . ucfirst($row['jenis_absensi']) . '</span>',
  $aksi
];

      $data[] = $nested;
    }
    $out = [
      "draw"            => intval($_POST['draw']),
      "recordsTotal"    => $totalData,
      "recordsFiltered" => $totalFiltered,
      "data"            => $data
    ];
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
  }

  // 4b. Update Absensi
  if (($_POST['action'] ?? '') === 'update') {
    $id_absensi = (int)($_POST['id_absensi'] ?? 0);
    if ($id_absensi <= 0) {
      $_SESSION['notif_error'] = "ID tidak valid.";
      header("Location:koreksi_absensi.php");
      exit;
    }
    $tanggal = $_POST['tanggal'] ?? '';
    if (!validate_date($tanggal)) {
      $_SESSION['notif_error'] = "Format tanggal tidak valid.";
      header("Location:koreksi_absensi.php");
      exit;
    }
    foreach (['jam_masuk', 'scan_masuk', 'scan_istirahat_1', 'scan_istirahat_2', 'jam_pulang', 'scan_pulang'] as $f) {
      if (!empty($_POST[$f]) && !validate_time($_POST[$f])) {
        $_SESSION['notif_error'] = "Format waktu $f tidak valid.";
        header("Location:koreksi_absensi.php");
        exit;
      }
    }
    $scan_masuk_dt   = !empty($_POST['scan_masuk'])   ? "$tanggal {$_POST['scan_masuk']}:00"   : get_absensi_field($conn, $id_absensi, 'scan_masuk');
    $scan_pulang_dt  = !empty($_POST['scan_pulang'])  ? "$tanggal {$_POST['scan_pulang']}:00"  : get_absensi_field($conn, $id_absensi, 'scan_pulang');
    $ist1_dt         = !empty($_POST['scan_istirahat_1']) ? "$tanggal {$_POST['scan_istirahat_1']}:00" : get_absensi_field($conn, $id_absensi, 'scan_istirahat_1');
    $ist2_dt         = !empty($_POST['scan_istirahat_2']) ? "$tanggal {$_POST['scan_istirahat_2']}:00" : get_absensi_field($conn, $id_absensi, 'scan_istirahat_2');
    $jadwal         = $_POST['jadwal'] ?? '';
    $jam_kerja      = $_POST['jam_kerja'] ?? '';
    $valid          = (int)($_POST['valid'] ?? 0);
    $pin            = $_POST['pin'] ?? '';
    $nip            = $_POST['nip'] ?? '';
    $nama           = $_POST['nama'] ?? '';
    $departemen     = $_POST['departemen'] ?? '';
    $lembur         = (int)($_POST['lembur'] ?? 0);
    $jam_masuk      = $_POST['jam_masuk'] ?? '';
    $terlambat      = isset($_POST['terlambat']) ? 1 : 0;
    $jenis_absensi  = in_array($_POST['jenis_absensi'] ?? '', JENIS_ABSENSI) ? $_POST['jenis_absensi'] : 'Normal';
    $status_kehad   = map_status_kehadiran($jenis_absensi);
    $stmt = $conn->prepare("UPDATE absensi SET
            tanggal=?,jadwal=?,jam_kerja=?,valid=?,pin=?,nip=?,nama=?,departemen=?,
            lembur=?,jam_masuk=?,scan_masuk=?,terlambat=?,scan_istirahat_1=?,
            scan_istirahat_2=?,jam_pulang=?,scan_pulang=?,jenis_absensi=?,status_kehadiran=?
            WHERE id=?");
    $stmt->bind_param(
      'sssissssississssssi',
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
      $scan_masuk_dt,
      $terlambat,
      $ist1_dt,
      $ist2_dt,
      $_POST['jam_pulang'],
      $scan_pulang_dt,
      $jenis_absensi,
      $status_kehad,
      $id_absensi
    );
    if ($stmt->execute()) {
      add_audit_log($conn, $_SESSION['nip'], 'UpdateAbsensi', "Update ID $id_absensi");
      $stmt->close();
      $c = $conn->prepare("SELECT id_anggota FROM absensi WHERE id=?");
      $c->bind_param('i', $id_absensi);
      $c->execute();
      $c->bind_result($id_anggota);
      $c->fetch();
      $c->close();
      $bl = date('n', strtotime($tanggal));
      $th = date('Y', strtotime($tanggal));
      $u1 = $conn->prepare("CALL UpdateRekapAbsensi(?,?,?)");
      $u1->bind_param('iii', $id_anggota, $bl, $th);
      $u1->execute();
      $u1->close();
      $u2 = $conn->prepare("CALL UpdateRekapMingguan(?,?)");
      $u2->bind_param('ss', $tanggal, $tanggal);
      $u2->execute();
      $u2->close();
      $_SESSION['notif_success'] = "ID $id_absensi berhasil dikoreksi.";
    } else {
      $_SESSION['notif_error'] = "Gagal update: " . $conn->error;
    }
    header("Location:koreksi_absensi.php");
    exit;
  }

  // 4c. Delete Absensi
  if (($_POST['action'] ?? '') === 'delete') {
    $id_absensi = (int)($_POST['id_absensi'] ?? 0);
    if ($id_absensi <= 0) {
      $_SESSION['notif_error'] = "ID tidak valid.";
      header("Location:koreksi_absensi.php");
      exit;
    }
    log_before_delete($conn, $id_absensi, $_SESSION['nip']);
    $st0 = $conn->prepare("SELECT id_anggota,tanggal FROM absensi WHERE id=?");
    $st0->bind_param('i', $id_absensi);
    $st0->execute();
    $st0->bind_result($id_anggota, $tanggal);
    $st0->fetch();
    $st0->close();
    $st1 = $conn->prepare("DELETE FROM absensi WHERE id=?");
    $st1->bind_param('i', $id_absensi);
    if ($st1->execute()) {
      add_audit_log($conn, $_SESSION['nip'], 'DeleteAbsensi', "Deleted ID $id_absensi");
      $_SESSION['notif_success'] = "ID $id_absensi berhasil dihapus.";
      if ($tanggal) {
        $bl = date('n', strtotime($tanggal));
        $th = date('Y', strtotime($tanggal));
        $u1 = $conn->prepare("CALL UpdateRekapAbsensi(?,?,?)");
        $u1->bind_param('iii', $id_anggota, $bl, $th);
        $u1->execute();
        $u1->close();
        $u2 = $conn->prepare("CALL UpdateRekapMingguan(?,?)");
        $u2->bind_param('ss', $tanggal, $tanggal);
        $u2->execute();
        $u2->close();
      }
    } else {
      $_SESSION['notif_error'] = "Gagal hapus: " . $conn->error;
    }
    header("Location:koreksi_absensi.php");
    exit;
  }
}

// ==============================================================================
// 5. Render View HTML
// ==============================================================================
$bulan        = $_GET['bulan'] ?? '';
$departemen   = $_GET['departemen'] ?? '';
$jenisAbsensi = $_GET['jenis_absensi'] ?? '';
$namaKaryawan = get_nama_karyawan($conn);
$jenjangList  = getOrderedJenjang($conn);

// [Kalender Absensi] Ambil data absensi dari database untuk FullCalendar
$jenisAbsensi = $_GET['jenis_absensi'] ?? '';
$whereKal = [];
$paramsKal = [];
$typesKal = "";

if ($bulan) {
  $whereKal[] = "DATE_FORMAT(a.tanggal, '%Y-%m') = ?";
  $paramsKal[] = $bulan;
  $typesKal .= "s";
}
if ($departemen) {
  $whereKal[] = "UPPER(a.departemen) = UPPER(?)";
  $paramsKal[] = $departemen;
  $typesKal .= "s";
}
if ($jenisAbsensi) {
  $whereKal[] = "a.jenis_absensi = ?";
  $paramsKal[] = $jenisAbsensi;
  $typesKal .= "s";
}
$whereSqlKal = $whereKal ? "WHERE " . implode(" AND ", $whereKal) : "";

$sqlKal = "SELECT a.tanggal, a.nama, a.jenis_absensi FROM absensi a $whereSqlKal";
$absensiEvents = [];
if (!empty($paramsKal)) {
  $stmtKal = $conn->prepare($sqlKal);
  $stmtKal->bind_param($typesKal, ...$paramsKal);
  $stmtKal->execute();
  $resKal = $stmtKal->get_result();
  while ($row = $resKal->fetch_assoc()) {
    $color = match (strtolower($row['jenis_absensi'])) {
      'izin'   => '#29b6f6',
      'sakit'  => '#fbc02d',
      'cuti'   => '#ab47bc',
      'bolos'  => '#e53935',
      'libur'  => '#43a047',
      'lembur' => '#8d6e63',
      default  => '#1976d2'
    };
    $absensiEvents[] = [
      'title' => $row['nama'] . ' (' . ucfirst($row['jenis_absensi']) . ')',
      'start' => $row['tanggal'],
      'color' => $color,
    ];
  }
  $stmtKal->close();
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>Koreksi Absensi - Payroll</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <!-- CSS Dependencies -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/startbootstrap-sb-admin-2/4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css" rel="stylesheet">
  <link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
  <style>
    body,
    .text-gray-800 {
      color: #000 !important;
    }

    /* ===== Page Title Styling ===== */
    .page-title {
      font-family: 'Poppins', sans-serif;
      font-weight: 600;
      font-size: 2.5rem;
      color: #0d47a1;
      text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
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

    .fc .fc-day-top,
    .fc .fc-day-number,
    .fc .fc-event-title,
    .fc .fc-event,
    .fc-toolbar h2,
    .fc-day-header {
      color: #000 !important;
    }

    .card-header {
      background: linear-gradient(45deg, #0d47a1, #42a5f5);
      color: #fff;
    }
  </style>
  <script>
    var absensiEvents = <?= json_encode($absensiEvents); ?>;
  </script>

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
            <i class="fas fa-edit"></i>
            Koreksi Absensi
          </h1>
          <!-- Notifikasi -->
          <?php if (isset($_SESSION['notif_success'])): ?>
            <script>
              document.addEventListener("DOMContentLoaded", function() {
                Swal.fire({
                  icon: 'success',
                  title: 'Sukses',
                  text: <?= json_encode($_SESSION['notif_success']); ?>,
                  timer: 3000,
                  showConfirmButton: false
                });
              });
            </script>
          <?php unset($_SESSION['notif_success']);
          endif; ?>
          <?php if (isset($_SESSION['notif_error'])): ?>
            <script>
              document.addEventListener("DOMContentLoaded", function() {
                Swal.fire({
                  icon: 'error',
                  title: 'Error',
                  text: <?= json_encode($_SESSION['notif_error']); ?>,
                  timer: 3000,
                  showConfirmButton: false
                });
              });
            </script>
          <?php unset($_SESSION['notif_error']);
          endif; ?>

          <!-- Filter -->
          <div class="card mb-4 shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
              <h6 class="m-0 fw-bold text-white"><i class="fas fa-search"></i> Filter Departemen</h6>
            </div>
            <div class="card-body" style="background-color:#f8f9fa;">
              <form method="GET" class="row gy-2 gx-3 align-items-center">
                <div class="col-auto">
                  <label for="bulan" class="form-label mb-0"><strong>Pilih Bulan:</strong></label>
                  <input type="month" name="bulan" id="bulan" class="form-control" value="<?= htmlspecialchars($bulan); ?>">
                </div>
                <div class="col-auto">
                  <label for="jenis_absensi" class="form-label mb-0"><strong>Jenis Absensi:</strong></label>
                  <select name="jenis_absensi" id="jenis_absensi" class="form-control">
                    <option value="">Semua</option>
                    <?php foreach (JENIS_ABSENSI as $ja): ?>
                      <option value="<?= $ja ?>" <?= (($_GET['jenis_absensi'] ?? '') == $ja) ? 'selected' : ''; ?>>
                        <?= $ja ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-auto">
                  <label for="departemen" class="form-label mb-0"><strong>Departemen:</strong></label>
                  <select name="departemen" id="departemen" class="form-control">
                    <option value="">Semua</option>
                    <?php foreach ($jenjangList as $j): ?>
                      <option value="<?= htmlspecialchars($j); ?>" <?= $departemen === $j ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($j); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-auto d-flex align-items-end">
                  <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search"></i> Tampilkan</button>
                  <a href="upload_absensi.php" class="btn btn-success"><i class="fas fa-upload"></i> Upload Absensi</a>
                </div>
              </form>
            </div>
          </div>

          <!-- Kalender -->
          <div class="mb-4" id="calendar-container">
            <div id="calendar"></div>
          </div>

          <!-- Tabel Absensi -->
          <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
              <h6 class="m-0 fw-bold text-white"><i class="fas fa-table"></i> Daftar Absensi</h6>
            </div>
            <div class="card-body">
              <table id="absensiTable" class="table table-sm table-bordered table-striped display nowrap" style="width:100%">
                <thead>
                  <tr>
                    <th class="no-column">No</th>
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

          <!-- Modal Edit -->
          <div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
              <form method="POST" class="modal-content">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id_absensi" id="edit_id_absensi">
                <input type="hidden" name="departemen" id="edit_departemen">
                <input type="hidden" name="bulan" value="<?= htmlspecialchars($bulan); ?>">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                <div class="modal-header">
                  <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Absensi</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <div class="row">
                    <div class="col-md-3 mb-3">
                      <label><i class="fas fa-calendar-alt"></i> Tanggal</label>
                      <input type="date" name="tanggal" id="edit_tanggal" class="form-control" required>
                    </div>
                    <div class="col-md-3 mb-3">
                      <label><i class="fas fa-clock"></i> Jadwal</label>
                      <input type="text" name="jadwal" id="edit_jadwal" class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                      <label><i class="fas fa-business-time"></i> Jam Kerja</label>
                      <input type="text" name="jam_kerja" id="edit_jam_kerja" class="form-control" placeholder="08:00-16:00">
                    </div>
                    <div class="col-md-3 mb-3">
                      <label><i class="fas fa-check"></i> Valid</label>
                      <input type="number" name="valid" id="edit_valid" class="form-control" min="0" max="1">
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-md-3 mb-3">
                      <label><i class="fas fa-key"></i> PIN</label>
                      <input type="text" name="pin" id="edit_pin" class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                      <label><i class="fas fa-id-card"></i> NIP</label>
                      <input type="text" name="nip" id="edit_nip" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                      <label><i class="fas fa-user"></i> Nama</label>
                      <input type="text" name="nama" id="edit_nama" class="form-control autocomplete-nama">
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-md-2 mb-3">
                      <label><i class="fas fa-building"></i> Departemen</label>
                      <input type="text" id="edit_departemen_label" class="form-control" disabled>
                    </div>
                    <div class="col-md-2 mb-3">
                      <label><i class="fas fa-hourglass-half"></i> Lembur</label>
                      <input type="number" name="lembur" id="edit_lembur" class="form-control" min="0" max="1">
                    </div>
                    <div class="col-md-2 mb-3">
                      <label><i class="fas fa-sign-in-alt"></i> Jam Masuk</label>
                      <input type="time" name="jam_masuk" id="edit_jam_masuk" class="form-control">
                    </div>
                    <div class="col-md-2 mb-3">
                      <label><i class="fas fa-fingerprint"></i> Scan Masuk</label>
                      <input type="time" name="scan_masuk" id="edit_scan_masuk" class="form-control">
                    </div>
                    <div class="col-md-2 mb-3">
                      <label><i class="fas fa-exclamation-triangle"></i> Terlambat</label>
                      <div class="form-check">
                        <input type="checkbox" name="terlambat" id="edit_terlambat" class="form-check-input" value="1">
                        <label class="form-check-label" for="edit_terlambat">Ya</label>
                      </div>
                    </div>
                    <div class="col-md-2 mb-3">
                      <label><i class="fas fa-info-circle"></i> Absensi</label>
                      <select name="jenis_absensi" id="edit_jenis_absensi" class="form-control">
                        <?php foreach (JENIS_ABSENSI as $ja): ?>
                          <option value="<?= $ja; ?>"><?= $ja; ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-md-3 mb-3">
                      <label><i class="fas fa-utensils"></i> Scan Istirahat 1</label>
                      <input type="time" name="scan_istirahat_1" id="edit_scan_istirahat_1" class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                      <label><i class="fas fa-utensils"></i> Scan Istirahat 2</label>
                      <input type="time" name="scan_istirahat_2" id="edit_scan_istirahat_2" class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                      <label><i class="fas fa-sign-out-alt"></i> Jam Pulang</label>
                      <input type="time" name="jam_pulang" id="edit_jam_pulang" class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                      <label><i class="fas fa-fingerprint"></i> Scan Pulang</label>
                      <input type="time" name="scan_pulang" id="edit_scan_pulang" class="form-control">
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Batal</button>
                  <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
                    <span class="spinner-border spinner-border-sm d-none"></span>
                  </button>
                </div>
              </form>
            </div>
          </div>

          <!-- Modal Delete -->
          <div class="modal fade" id="modalDelete" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
              <form method="POST" class="modal-content">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id_absensi" id="delete_id_absensi">
                <input type="hidden" name="departemen" value="<?= htmlspecialchars($departemen); ?>">
                <input type="hidden" name="bulan" value="<?= htmlspecialchars($bulan); ?>">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                <div class="modal-header bg-danger text-white">
                  <h5 class="modal-title"><i class="fas fa-trash-alt"></i> Hapus Absensi</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <p>Yakin ingin menghapus data untuk <strong id="delete_nama"></strong> pada tanggal <strong id="delete_tanggal"></strong>?</p>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Batal</button>
                  <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Hapus</button>
                </div>
              </form>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- JS Dependencies -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/startbootstrap-sb-admin-2/4.1.4/js/sb-admin-2.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
  <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
  <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    $(function() {
      // Tooltip
      $('[data-bs-toggle="tooltip"]').tooltip();

      // Toast setup
      const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (t) => {
          t.addEventListener('mouseenter', Swal.stopTimer);
          t.addEventListener('mouseleave', Swal.resumeTimer);
        }
      });

      function showToast(msg, icon = 'success') {
        Toast.fire({
          icon,
          title: msg
        });
      }

      // DataTable
      var table = $('#absensiTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
          url: 'koreksi_absensi.php',
          type: 'POST',
          data: function(d) {
            d.bulan = $('#bulan').val();
            d.departemen = $('#departemen').val();
            d.jenis_absensi = $('#jenis_absensi').val();
            d.csrf_token = "<?= $csrf_token; ?>";
          }

        },

        language: {
          url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
        },
        dom: 'Bfrtip',
        buttons: [{
            extend: 'copyHtml5',
            className: 'btn btn-secondary btn-sm',
            text: '<i class="fas fa-copy"></i> Copy',
            exportOptions: {
              modifier: {
                page: 'all'
              }
            }
          },
          {
            extend: 'excelHtml5',
            className: 'btn btn-success btn-sm',
            text: '<i class="fas fa-file-excel"></i> Excel',
            exportOptions: {
              modifier: {
                page: 'all'
              }
            }
          },
          {
            extend: 'pdfHtml5',
            className: 'btn btn-danger btn-sm',
            text: '<i class="fas fa-file-pdf"></i> PDF',
            exportOptions: {
              modifier: {
                page: 'all'
              }
            }
          },
          {
            extend: 'print',
            className: 'btn btn-info btn-sm',
            text: '<i class="fas fa-print"></i> Print',
            exportOptions: {
              modifier: {
                page: 'all'
              }
            }
          },
          {
            extend: 'colvis',
            className: 'btn btn-warning btn-sm',
            text: '<i class="fas fa-columns"></i> Kolom'
          }
        ],

        responsive: true,
        autoWidth: false,
        pageLength: 10,
        columnDefs: [{
          orderable: false,
          targets: 18
        }]
      });

      // FullCalendar
      $('#calendar').fullCalendar({
        header: {
          left: 'prev,next today',
          center: 'title',
          right: 'month,agendaWeek,agendaDay'
        },
        editable: false,
        events: absensiEvents,
        eventRender: function(event, element) {
          element.attr('title', event.title); // Tooltip info nama dan status
        }
      });


      // Autocomplete Nama
      var namaK = <?= json_encode($namaKaryawan); ?>;
      $('.autocomplete-nama').autocomplete({
        source: namaK,
        minLength: 2
      });

      // Modal Edit populate
      $('#modalEdit').on('show.bs.modal', function(e) {
        var btn = $(e.relatedTarget),
          m = $(this);
        m.find('#edit_id_absensi').val(btn.data('id'));
        m.find('#edit_tanggal').val(btn.data('tanggal'));
        m.find('#edit_jadwal').val(btn.data('jadwal'));
        m.find('#edit_jam_kerja').val(btn.data('jam_kerja'));
        m.find('#edit_valid').val(btn.data('valid'));
        m.find('#edit_pin').val(btn.data('pin'));
        m.find('#edit_nip').val(btn.data('nip'));
        m.find('#edit_nama').val(btn.data('nama'));
        m.find('#edit_departemen_label').val(btn.data('departemen'));
        m.find('#edit_departemen').val(btn.data('departemen'));
        m.find('#edit_lembur').val(btn.data('lembur'));
        m.find('#edit_jam_masuk').val(btn.data('jam_masuk'));
        m.find('#edit_scan_masuk').val(btn.data('scan_masuk'));
        m.find('#edit_terlambat').prop('checked', btn.data('terlambat') == 1);
        m.find('#edit_scan_istirahat_1').val(btn.data('scan_istirahat_1'));
        m.find('#edit_scan_istirahat_2').val(btn.data('scan_istirahat_2'));
        m.find('#edit_jam_pulang').val(btn.data('jam_pulang'));
        m.find('#edit_scan_pulang').val(btn.data('scan_pulang'));
        m.find('#edit_jenis_absensi').val(btn.data('jenis_absensi'));
      });

      // Modal Delete populate
      $('#modalDelete').on('show.bs.modal', function(e) {
        var btn = $(e.relatedTarget),
          m = $(this);
        m.find('#delete_id_absensi').val(btn.data('id'));
        m.find('#delete_nama').text(btn.data('nama'));
        m.find('#delete_tanggal').text(btn.data('tanggal'));
      });

      // Reset & spinner
      $('.modal').on('hidden.bs.modal', function() {
        $(this).find('form')[0].reset();
      });
      $('form').on('submit', function() {
        var b = $(this).find('button[type="submit"]');
        b.prop('disabled', true);
        b.find('.spinner-border').removeClass('d-none');
      });

      $('form').on('submit', function(e) {
        e.preventDefault(); // mencegah reload default
        table.ajax.reload(); // reload DataTables dengan filter terbaru
      });

    });
  </script>
</body>

</html>
<?php $conn->close(); ?>