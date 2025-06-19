<?php
// File: /payroll_absensi_v2/keuangan/rekap_payroll_details.php

// =============================================================================
// 1. Session, koneksi, helper
// =============================================================================
require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:Keuangan']);
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

require_once __DIR__ . '/../koneksi.php';
if (ob_get_length()) { ob_end_clean(); }

// Ambil jenjang
$jenjang = sanitize_input($_GET['jenjang'] ?? '');
if (!$jenjang) {
    die("Jenjang tidak valid.");
}

// Audit log
add_audit_log(
    $conn,
    $_SESSION['nip'],
    'AccessPage',
    "Mengakses Detail Rekap Payroll jenjang '{$jenjang}'."
);

// Ambil header payheads untuk <th>
$earningHeaderPayheads = [];
$resE = $conn->query("
    SELECT DISTINCT nama_payhead
      FROM payroll_detail_final
     WHERE jenis='earnings'
  ORDER BY nama_payhead
");
while ($r = $resE->fetch_assoc()) {
    $earningHeaderPayheads[] = $r['nama_payhead'];
}
$resE->free();

$deductionHeaderPayheads = [];
$resD = $conn->query("
    SELECT DISTINCT nama_payhead
      FROM payroll_detail_final
     WHERE jenis='deductions'
  ORDER BY nama_payhead
");
while ($r = $resD->fetch_assoc()) {
    $deductionHeaderPayheads[] = $r['nama_payhead'];
}
$resD->free();

// =============================================================================
// 2. AJAX handler untuk DataTables
// =============================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        LoadingPayrollDetails($conn, $jenjang);
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    exit();
}

function LoadingPayrollDetails($conn, $jenjang)
{
    // DataTables params
    $draw   = intval($_POST['draw']   ?? 0);
    $start  = intval($_POST['start']  ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $search = sanitize_input($_POST['search']['value'] ?? '');

    // totalRecords
    $stmtTotal = $conn->prepare("
        SELECT COUNT(*) AS total
          FROM payroll_final p
          JOIN anggota_sekolah a ON p.id_anggota=a.id
         WHERE a.jenjang = ?
    ");
    $stmtTotal->bind_param('s', $jenjang);
    $stmtTotal->execute();
    $recordsTotal = intval($stmtTotal->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtTotal->close();

    // recordsFiltered
    $types = 's'; $params = [$jenjang];
    $sqlF = "
        SELECT COUNT(*) AS total
          FROM payroll_final p
          JOIN anggota_sekolah a ON p.id_anggota=a.id
         WHERE a.jenjang = ?
    ";
    if ($search !== '') {
        $sqlF .= " AND (a.nama LIKE ? OR p.id LIKE ?)";
        $types .= 'ss';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    $stmtF = $conn->prepare($sqlF);
    $stmtF->bind_param($types, ...$params);
    $stmtF->execute();
    $recordsFiltered = intval($stmtF->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtF->close();

    // daftar payheads
    $payheads = [];
    $res1 = $conn->query("SELECT DISTINCT nama_payhead FROM payroll_detail_final WHERE jenis='earnings' ORDER BY nama_payhead");
    while ($r = $res1->fetch_assoc()) $payheads[] = $r['nama_payhead'];
    $res1->free();
    $res2 = $conn->query("SELECT DISTINCT nama_payhead FROM payroll_detail_final WHERE jenis='deductions' ORDER BY nama_payhead");
    while ($r = $res2->fetch_assoc()) $payheads[] = $r['nama_payhead'];
    $res2->free();

    // subselect CASE untuk setiap payhead
    $subCases = [];
    foreach ($payheads as $ph) {
        $esc   = $conn->real_escape_string($ph);
        $alias = 'ph_' . substr(md5($ph),0,8);
        $subCases[] = "SUM(CASE WHEN d.nama_payhead='$esc' THEN d.amount ELSE 0 END) AS `$alias`";
    }
    $subSelect = $subCases ? implode(", ", $subCases) : '0 AS dummy';

    // outer select untuk payhead columns
    $outerCols = '';
    foreach ($payheads as $ph) {
        $alias = 'ph_' . substr(md5($ph),0,8);
        $outerCols .= ", IFNULL(det.`$alias`,0) AS `$alias`";
    }

    // query utama
    $sql = "
        SELECT
          p.id                   AS id_payroll,
          a.nama                 AS nama_karyawan,
          p.bulan,
          p.tahun,
          MAX(p.gaji_pokok)          AS total_gaji_pokok,
          MAX(p.salary_index_amount) AS total_salary_index,
          p.potongan_koperasi    AS total_potongan_koperasi
          $outerCols,
          IFNULL(kg.total_lain_lain,0) AS total_lain_lain,
          p.gaji_bersih          AS total_gaji_bersih
        FROM payroll_final p
        JOIN anggota_sekolah a ON p.id_anggota=a.id
        LEFT JOIN (
            SELECT id_payroll_final, $subSelect
              FROM payroll_detail_final d
             GROUP BY id_payroll_final
        ) det ON p.id = det.id_payroll_final
        LEFT JOIN (
            SELECT id_anggota, SUM(jumlah) AS total_lain_lain
              FROM kenaikan_gaji_tahunan
             WHERE pindah_ke_lain_lain=1
             GROUP BY id_anggota
        ) kg ON a.id = kg.id_anggota
        WHERE a.jenjang = ?
    ";

    // bind filter, paging
    $types = 's'; $params = [$jenjang];
    if ($search !== '') {
        $sql .= " AND (a.nama LIKE ? OR p.id LIKE ?) ";
        $types .= 'ss';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    $sql .= " GROUP BY p.id ORDER BY p.id DESC LIMIT ?, ?";
    $types .= 'ii';
    $params[] = $start;
    $params[] = $length;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $data = [];
    while ($r = $res->fetch_assoc()) {
        $row = [
            'id_payroll'           => $r['id_payroll'],
            'nama_karyawan'        => htmlspecialchars($r['nama_karyawan']),
            'bulan'                => getIndonesianMonthName($r['bulan']),
            'tahun'                => $r['tahun'],
            'total_gaji_pokok'     => formatNominal($r['total_gaji_pokok']),
            'total_salary_index'   => formatNominal($r['total_salary_index']),
            'total_potongan_koperasi' => formatNominal($r['total_potongan_koperasi']),
        ];
        foreach ($payheads as $ph) {
            $alias = 'ph_' . substr(md5($ph),0,8);
            $row[$alias] = formatNominal($r[$alias] ?? 0);
        }
        $row['total_lain_lain']   = formatNominal($r['total_lain_lain']);
        $row['total_gaji_bersih'] = formatNominal($r['total_gaji_bersih']);
        $row['aksi'] = '
          <a href="payroll-details.php?id_payroll=' . $r['id_payroll'] . '"
             class="btn btn-info btn-sm" title="Lihat Payroll">
            <i class="fas fa-file-invoice"></i>
          </a>';
        $data[] = $row;
        
    }
    $stmt->close();

    echo json_encode([
        "draw"            => $draw,
        "recordsTotal"    => $recordsTotal,
        "recordsFiltered" => $recordsFiltered,
        "data"            => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Detail Rekap Payroll – <?= htmlspecialchars($jenjang) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- SB Admin 2 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
  <!-- DataTables CSS (Bootstrap 5) -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
  <!-- Font Awesome & Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <style>
    body{padding-top:20px;}
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
    .card-header{background:linear-gradient(45deg,#0d47a1,#42a5f5);color:#fff;}
    .table-responsive{overflow-x:auto;}
    table.dataTable th, table.dataTable td{white-space:nowrap;}
  </style>
</head>
<body>
  <div class="container-fluid">
    <a href="rekap_payroll.php" class="btn btn-secondary mb-3">
      <i class="fas fa-arrow-left"></i> Kembali
    </a>

<h1 class="page-title">
        <i class="fas fa-file-invoice"></i>
  Detail Rekap – <?= htmlspecialchars($jenjang) ?>
    </h1>
    
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 fw-bold"><i class="fas fa-list"></i> Daftar Detail Payroll</h6>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table id="detailPayrollTable" class="table table-striped table-bordered dt-responsive nowrap" style="width:100%">
            <thead>
            <tr>
                <th>ID Payroll</th>
                <th>Nama Karyawan</th>
                <th>Bulan</th>
                <th>Tahun</th>
                <th>Gaji Pokok</th>
                <th>Salary Index</th>
                <!-- PAYHEAD LAMA -->
                <?php foreach($earningHeaderPayheads as $ph): ?>
                  <th><?= htmlspecialchars($ph) ?></th>
                <?php endforeach; ?>
                <th>Lain‑lain</th>
                <th>Potongan Koperasi</th>
                <!-- PAYHEAD DEDUCTIONS -->
                <?php foreach($deductionHeaderPayheads as $ph): ?>
                  <th><?= htmlspecialchars($ph) ?></th>
                <?php endforeach; ?>
                <th>Gaji Bersih</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Loading Spinner -->
  <div id="loadingSpinner" style="display:none;position:fixed;top:50%;left:50%;z-index:9999">
    <div class="spinner-border text-primary" role="status"></div>
  </div>

  <!-- JS -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    // build dynamic columns
    let dynEarn = [], dynDeduct = [];
    <?php foreach($earningHeaderPayheads as $ph):
      $a = 'ph_'.substr(md5($ph),0,8);
    ?>
      dynEarn.push({ data:'<?= $a ?>', name:'<?= $a ?>', defaultContent:'0' });
    <?php endforeach; ?>
    <?php foreach($deductionHeaderPayheads as $ph):
      $a = 'ph_'.substr(md5($ph),0,8);
    ?>
      dynDeduct.push({ data:'<?= $a ?>', name:'<?= $a ?>', defaultContent:'0' });
    <?php endforeach; ?>

    $(function(){
      const Toast = Swal.mixin({ toast:true, position:'top-end', timer:3000, showConfirmButton:false });
      function showToast(m){ Toast.fire({ icon:'error', title:m }); }

      $('#detailPayrollTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        ajax: {
          url: 'rekap_payroll_details.php?ajax=1&jenjang=<?= urlencode($jenjang) ?>',
          type: 'POST',
          data: d => {
            d.case = 'LoadingPayrollDetails';
            d.csrf_token = '<?= $csrf_token ?>';
          },
          beforeSend: ()=>$('#loadingSpinner').show(),
          complete:   ()=>$('#loadingSpinner').hide(),
          error:      ()=>showToast('Gagal load data')
        },
        columns: [
          { data:'id_payroll' },
          { data:'nama_karyawan' },
          { data:'bulan' },
          { data:'tahun' },
          { data:'total_gaji_pokok' },
          { data:'total_salary_index' }
        ]
        .concat(dynEarn)
        .concat([
          { data:'total_lain_lain' },
          { data:'total_potongan_koperasi' }
        ])
        .concat(dynDeduct)
        .concat([
          { data:'total_gaji_bersih' },
          { data:'aksi', orderable:false, searchable:false }
        ]),
        order:[[0,'desc']],
        language:{ url:"//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json" }
      });
    });
  </script>
</body>
</html>
