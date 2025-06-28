<?php
// File: /payroll_absensi_v2/keuangan/rekap_payroll_jenjang.php

$pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:Keuangan']);
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];
require_once __DIR__ . '/../koneksi.php';

// Ambil parameter
$jenjang = $_GET['jenjang'] ?? '';
$bulan   = intval($_GET['bulan'] ?? date('n'));
$tahun   = intval($_GET['tahun'] ?? date('Y'));

$jenjangMeta = [
  'TK'    => ['icon' => 'fas fa-child',              'color' => '#e74c3c'],
  'SD'    => ['icon' => 'fas fa-book-open',          'color' => '#3498db'],
  'SMP'   => ['icon' => 'fas fa-user-graduate',      'color' => '#2ecc71'],
  'SMA'   => ['icon' => 'fas fa-chalkboard-teacher', 'color' => '#f1c40f'],
  'SMK'   => ['icon' => 'fas fa-tools',              'color' => '#9b59b6'],
  'SEMUA' => ['icon' => 'fas fa-layer-group',        'color' => '#273c75'],
  'MANAJER' => ['icon' => 'fas fa-user-tie',         'color' => '#b44ef9'],
];
$meta = $jenjangMeta[strtoupper($jenjang)] ?? ['icon' => 'fas fa-school', 'color' => '#34495e'];

$isSemua = (strtolower($jenjang) === 'semua' || $jenjang === '');

// 1. Ambil konfigurasi payhead
$PAYHEAD_GROUPS = [];
$rs = $conn->query("
  SELECT group_name
    FROM payhead_groups
   WHERE jenis='earnings'
GROUP BY group_name
ORDER BY MIN(sort_order), group_name
");
while ($g = $rs->fetch_assoc()) $PAYHEAD_GROUPS[] = $g['group_name'];
$rs->free();

$groupMembers = [];
$rs = $conn->query("SELECT DISTINCT payhead_name FROM payhead_groups");
while ($gm = $rs->fetch_assoc()) $groupMembers[] = $gm['payhead_name'];
$rs->free();

$inList = $groupMembers
  ? "'" . implode("','", array_map([$conn, 'real_escape_string'], $groupMembers)) . "'"
  : "''";
$earningPayheads = [];
$rs = $conn->query("
  SELECT DISTINCT nama_payhead
    FROM payroll_detail_final
   WHERE jenis='earnings'
     AND nama_payhead NOT IN ($inList)
   ORDER BY nama_payhead
");
while ($r = $rs->fetch_assoc()) $earningPayheads[] = $r['nama_payhead'];
$rs->free();

$deductionPayheads = [];
$rs = $conn->query("
  SELECT DISTINCT nama_payhead
    FROM payroll_detail_final
   WHERE jenis='deductions'
     AND nama_payhead NOT IN ($inList)
   ORDER BY nama_payhead
");
while ($r = $rs->fetch_assoc()) $deductionPayheads[] = $r['nama_payhead'];
$rs->free();

// Untuk fetchSummaryRow (ambil dari file export)
function fetchSummaryRow($conn, $jenjang, $bulan, $tahun, $kategori, $earningPayheads, $PAYHEAD_GROUPS, $deductionPayheads) {
    if ($kategori == 'manajer') {
        $where = "a.role='M' AND a.jenjang=? AND pf.bulan=? AND pf.tahun=?";
        $params = [$jenjang, $bulan, $tahun];
        $types = 'sii';
    } else {
        $where = "a.kategori=? AND a.role<>'M' AND a.jenjang=? AND pf.bulan=? AND pf.tahun=?";
        $params = [$kategori, $jenjang, $bulan, $tahun];
        $types = 'ssii';
    }
    $sql1 = "SELECT SUM(pf.gaji_pokok) AS gaji_pokok, SUM(pf.salary_index_amount) AS idx_amount, SUM(pf.potongan_koperasi) AS pot_koperasi
         FROM payroll_final pf
         JOIN anggota_sekolah a ON pf.id_anggota=a.id
         WHERE $where";
    $stmt1 = $conn->prepare($sql1);
    $stmt1->bind_param($types, ...$params);
    $stmt1->execute();
    $r1 = $stmt1->get_result()->fetch_assoc();
    $stmt1->close();

    $komponen = [];
    foreach ($earningPayheads as $ph) {
        $sql2 = "SELECT SUM(amount) FROM (
                   SELECT SUM(d.amount) AS amount
                   FROM payroll_final pf
                   JOIN anggota_sekolah a ON pf.id_anggota=a.id
                   JOIN payroll_detail_final d ON pf.id = d.id_payroll_final
                   WHERE $where AND d.nama_payhead=? GROUP BY pf.id
                ) AS sub";
        $stmt2 = $conn->prepare($sql2);
        $params2 = array_merge($params, [$ph]);
        $types2 = $types . "s";
        $stmt2->bind_param($types2, ...$params2);
        $stmt2->execute();
        $komponen[$ph] = $stmt2->get_result()->fetch_row()[0] ?? 0;
        $stmt2->close();
    }
    foreach ($PAYHEAD_GROUPS as $grp) {
        $mrs = $conn->query("SELECT payhead_name FROM payhead_groups WHERE group_name='".$conn->real_escape_string($grp)."'");
        $members = [];
        while ($m = $mrs->fetch_assoc()) $members[] = $conn->real_escape_string($m['payhead_name']);
        $mrs->free();
        if (!$members) { $komponen[$grp] = 0; continue; }
        $in = "'" . implode("','", $members) . "'";
        $sql2 = "SELECT SUM(amount) FROM (
                   SELECT SUM(d.amount) AS amount
                   FROM payroll_final pf
                   JOIN anggota_sekolah a ON pf.id_anggota=a.id
                   JOIN payroll_detail_final d ON pf.id = d.id_payroll_final
                   WHERE $where AND d.nama_payhead IN($in) GROUP BY pf.id
                ) AS sub";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param($types, ...$params);
        $stmt2->execute();
        $komponen[$grp] = $stmt2->get_result()->fetch_row()[0] ?? 0;
        $stmt2->close();
    }
    foreach ($deductionPayheads as $ph) {
        $sql2 = "SELECT SUM(amount) FROM (
                   SELECT SUM(d.amount) AS amount
                   FROM payroll_final pf
                   JOIN anggota_sekolah a ON pf.id_anggota=a.id
                   JOIN payroll_detail_final d ON pf.id = d.id_payroll_final
                   WHERE $where AND d.nama_payhead=? GROUP BY pf.id
                ) AS sub";
        $stmt2 = $conn->prepare($sql2);
        $params2 = array_merge($params, [$ph]);
        $types2 = $types . "s";
        $stmt2->bind_param($types2, ...$params2);
        $stmt2->execute();
        $komponen[$ph] = $stmt2->get_result()->fetch_row()[0] ?? 0;
        $stmt2->close();
    }
    $gajiPokok   = (float)$r1['gaji_pokok'];
    $idxAmount   = (float)$r1['idx_amount'];
    $potKoperasi = (float)$r1['pot_koperasi'];
    $sumEarningsPH = 0; foreach ($earningPayheads as $ph) $sumEarningsPH += (float)$komponen[$ph];
    $sumGroupPH = 0; foreach ($PAYHEAD_GROUPS as $grp) $sumGroupPH += (float)$komponen[$grp];
    $totalPendapatan = $gajiPokok + $idxAmount + $sumEarningsPH + $sumGroupPH;
    $maxPotKop = $totalPendapatan * 0.65;
    $sumDeductionPH = 0; foreach ($deductionPayheads as $ph) $sumDeductionPH += (float)$komponen[$ph];
    $totalPotongan = $potKoperasi + $sumDeductionPH;
    $netReceived = $totalPendapatan - $totalPotongan;
    $rounded = round($netReceived / 100) * 100;

    $row = [
        'Gaji Pokok' => $gajiPokok,
        'Indeks' => $idxAmount
    ];
    foreach ($earningPayheads as $ph) $row[$ph] = $komponen[$ph];
    foreach ($PAYHEAD_GROUPS as $grp) $row[$grp] = $komponen[$grp];
    foreach ($deductionPayheads as $ph) $row[$ph] = $komponen[$ph];
    $row['Pembulatan'] = $rounded;
    $row['Jumlah Pendapatan'] = $totalPendapatan;
    $row['Max Pot. Kop'] = $maxPotKop;
    $row['Pot. Koperasi'] = $potKoperasi;
    $row['Jumlah Potongan'] = $totalPotongan;
    $row['Jumlah Yang Diterima'] = $netReceived;
    return $row;
}

// 2. AJAX Handler
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_response(405, 'Metode tidak diizinkan');
  verify_csrf_token($_POST['csrf_token'] ?? '');
  $category = sanitize_input($_GET['category'] ?? '');

  if ($isSemua) {
    $jenjangList = [];
$rs = $conn->query("SELECT kode_jenjang, nama_jenjang FROM jenjang_sekolah WHERE is_aktif=1");
while ($j = $rs->fetch_assoc()) $jenjangList[$j['kode_jenjang']] = $j['nama_jenjang'];

    $rs->free();

    $data = [];
    $no = 1;
    foreach ($jenjangList as $kode => $nama) {
      $t = fetchSummaryRow($conn, $kode, $bulan, $tahun, $category, $earningPayheads, $PAYHEAD_GROUPS, $deductionPayheads);
      $row = [
        'no'        => $no++,
        'jenjang'   => $nama,
        'gaji_pokok'=> formatNominal($t['Gaji Pokok'] ?? 0),
        'idx_amount'=> formatNominal($t['Indeks'] ?? 0)
      ];
      foreach ($earningPayheads as $ph)      $row['ph_' . substr(md5($ph), 0, 8)] = formatNominal($t[$ph] ?? 0);
      foreach ($PAYHEAD_GROUPS as $grp)      $row['gr_' . substr(md5($grp), 0, 8)] = formatNominal($t[$grp] ?? 0);
      foreach ($deductionPayheads as $ph)    $row['d_'  . substr(md5($ph), 0, 8)] = formatNominal($t[$ph] ?? 0);
      $row['pembulatan']        = formatNominal($t['Pembulatan'] ?? 0);
      $row['total_pendapatan']  = formatNominal($t['Jumlah Pendapatan'] ?? 0);
      $row['max_pot_kop']       = formatNominal($t['Max Pot. Kop'] ?? 0);
      $row['pot_koperasi']      = formatNominal($t['Pot. Koperasi'] ?? 0);
      $row['total_potongan']    = formatNominal($t['Jumlah Potongan'] ?? 0);
      $row['net_received']      = formatNominal($t['Jumlah Yang Diterima'] ?? 0);
      $data[] = $row;
    }
    echo json_encode([
      'draw'            => 1,
      'recordsTotal'    => count($data),
      'recordsFiltered' => count($data),
      'data'            => $data
    ], JSON_UNESCAPED_UNICODE); exit;
  }

  $PAYHEADS = array_merge($earningPayheads, $deductionPayheads);

  // JENJANG SPESIFIK: gunakan loadDetail seperti biasa
  require_once __DIR__ . '/rekap_payroll_ajax_handler.php';
  exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Rekap Payroll – <?= htmlspecialchars($jenjang) ?> <?= getIndonesianMonthName($bulan) . ' ' . $tahun ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
  <style>
    .page-header { background: #fff; border-left: 5px solid <?= $meta['color'] ?>; padding: 1rem 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 .2rem .4rem rgba(0, 0, 0, .1);}
    .page-title {font-family: 'Poppins', sans-serif;font-weight: 600;font-size: 2.5rem;color: #0d47a1;text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);display: flex;align-items: center;gap: 0.5rem;border-bottom: 3px solid #1976d2;padding-bottom: 0.3rem;margin-bottom: 1.5rem;animation: fadeInSlide 0.5s ease-in-out both;}
    .page-title i {color: #1976d2;font-size: 2.8rem;}
    .page-header h3 {margin: 0;font-weight: 600;color: #333;}
    .back-btn {color: #fff;background: <?= $meta['color'] ?>;border: none;}
    .back-btn:hover {filter: brightness(90%);}
    table.dataTable, table.dataTable th, table.dataTable td {font-size: 0.85rem;}
    .card-header {background: #0d47a1;color: #fff;}
    table.dataTable thead th {background: #343a40;color: #fff;text-align: center;}
    table.dataTable td, table.dataTable th {vertical-align: middle;}
    tfoot th {font-weight: bold;}
    .manajer-header {background: #b44ef9 !important;color: #fff !important;}
    
  </style>
</head>
<body>
  <div class="container-fluid py-4">
    <div class="d-flex align-items-center page-header">
      <button onclick="window.history.back()" class="btn back-btn me-3"><i class="fas fa-arrow-left"></i></button>
      <h1 class="page-title">
        <i class="<?= $meta['icon'] ?> me-2"></i>
        Rekap <?= strtoupper($jenjang) == 'SEMUA' ? 'Semua Jenjang' : htmlspecialchars($jenjang) ?> – <?= getIndonesianMonthName($bulan) . ' ' . $tahun ?>
      </h1>
      <a href="export_payroll_jenjang.php?jenjang=<?= urlencode($jenjang) ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>"
        class="btn btn-success ms-auto d-flex align-items-center"
        style="height:42px"
        target="_blank"
        title="Export Excel Rekap Gabungan">
        <i class="fas fa-file-excel me-2"></i> Export Excel
      </a>
    </div>
  </div>
  <?php
  function renderColsHeader($list) { foreach ($list as $item) { echo "<th>{$item}</th>"; } }
  function renderColsFooter($list) { foreach ($list as $_) { echo "<th></th>"; } }
  ?>
  <?php foreach (['Guru'=>'guru', 'Karyawan'=>'karyawan', 'Manajer'=>'manajer'] as $label => $role): ?>
  <div class="card mb-4 shadow">
    <div class="card-header <?= $role=='manajer'?'manajer-header':'' ?> d-flex justify-content-between align-items-center">
      <span>
        <i class="fas <?= $role=='guru'?'fa-chalkboard-teacher':($role=='karyawan'?'fa-users':'fa-user-tie') ?>"></i>
        Rekap <?= $label ?>
      </span>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table id="tbl<?= ucfirst($role) ?>" class="table table-sm table-bordered table-striped w-100">
          <thead>
            <tr>
            <?php if ($isSemua): ?>
              <th>No</th>
              <th>Jenjang</th>
              <th>Gaji Pokok</th>
              <th>Indeks</th>
              <?php renderColsHeader($earningPayheads); ?>
              <?php renderColsHeader($PAYHEAD_GROUPS); ?>
              <?php renderColsHeader($deductionPayheads); ?>
              <th>Pembulatan</th>
              <th>Jumlah Pendapatan</th>
              <th>Max Pot. Kop</th>
              <th>Pot. Koperasi</th>
              <th>Jumlah Potongan</th>
              <th>Jumlah Yang Diterima</th>
            <?php else: ?>
              <th>NIP</th>
              <th>Nama</th>
              <th>Keterangan</th>
              <th>Gaji Pokok</th>
              <th>Indeks</th>
              <?php renderColsHeader($earningPayheads); ?>
              <?php renderColsHeader($PAYHEAD_GROUPS); ?>
              <th>Honor Jam Lebih</th>
              <th>Potongan Absensi</th>
              <?php renderColsHeader($deductionPayheads); ?>
              <th>Pembulatan</th>
              <th>Jumlah Pendapatan</th>
              <th>Max Pot. Kop</th>
              <th>Pot. Koperasi</th>
              <th>Jumlah Potongan</th>
              <th>Jumlah Yang Diterima</th>
            <?php endif; ?>
            </tr>
          </thead>
          <tfoot>
            <tr>
            <?php if ($isSemua): ?>
              <th></th>
              <th class="text-end fw-bold">JUMLAH:</th>
              <?php for($i=0;$i<count($earningPayheads)+count($PAYHEAD_GROUPS)+count($deductionPayheads)+8;$i++) echo '<th></th>'; ?>
            <?php else: ?>
              <th colspan="3"></th>
              <th class="text-end">Jumlah:</th>
              <?php for($i=0;$i<count($earningPayheads)+count($PAYHEAD_GROUPS)+2+count($deductionPayheads)+6;$i++) echo '<th></th>'; ?>
            <?php endif; ?>
            </tr>
          </tfoot>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- JS -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
  <script>
const csrf = '<?= $csrf_token ?>';
const baseUrl = 'rekap_payroll_jenjang.php?ajax=1' +
  '&jenjang=<?= urlencode($jenjang) ?>' +
  '&bulan=<?= $bulan ?>' +
  '&tahun=<?= $tahun ?>';
const isSemua = <?= $isSemua ? 'true' : 'false' ?>;

// Kolom dinamis JS
let dynEarnings = [], dynGroups = [], dynDeductions = [];
<?php foreach ($earningPayheads as $ph): ?>
  dynEarnings.push({ data: 'ph_<?= substr(md5($ph), 0, 8) ?>' });
<?php endforeach; ?>
<?php foreach ($PAYHEAD_GROUPS as $g): ?>
  dynGroups.push({ data: 'gr_<?= substr(md5($g), 0, 8) ?>' });
<?php endforeach; ?>
<?php foreach ($deductionPayheads as $ph): ?>
  dynDeductions.push({ 
    data: isSemua ? 'd_<?= substr(md5($ph), 0, 8) ?>' : 'ph_<?= substr(md5($ph), 0, 8) ?>',
    render: function(data, type, row) {
      if (parseFloat(String(data).replace(/[^\d.-]/g, '')) > 0)
        return '<span style="color:#d32f2f;font-weight:bold">' + data + '</span>';
      return data;
    }
  });
<?php endforeach; ?>

function getColumns(role) {
  if (isSemua) {
    let cols = [
      { data: 'no' }, 
      { data: 'jenjang' }, 
      { data: 'gaji_pokok' }, 
      { data: 'idx_amount' }
    ].concat(dynEarnings)
    .concat(dynGroups)
    .concat(dynDeductions)
    .concat([
      { data: 'pembulatan' },
      { data: 'total_pendapatan' },
      { data: 'max_pot_kop' },
      // Potongan Koperasi
      {
        data: 'pot_koperasi',
        render: function(data, type, row) {
          if (parseFloat(String(data).replace(/[^\d.-]/g, '')) > 0)
            return '<span style="color:#d32f2f;font-weight:bold">' + data + '</span>';
          return data;
        }
      },
      // Jumlah Potongan
      {
        data: 'total_potongan',
        render: function(data, type, row) {
          if (parseFloat(String(data).replace(/[^\d.-]/g, '')) > 0)
            return '<span style="color:#d32f2f;font-weight:bold">' + data + '</span>';
          return data;
        }
      },
      { data: 'net_received' }
    ]);
    return cols;
  } else {
    let cols = [
      { data: 'nip' }, 
      { data: 'nama' }, 
      { data: 'keterangan' },
      { data: 'gaji_pokok' }, 
      { data: 'idx_amount' }
    ].concat(dynEarnings)
    .concat(dynGroups)
    .concat([
      { data: 'honor_jam_lebih' },
      // Potongan Absensi
      {
        data: 'pot_absensi',
        render: function(data, type, row) {
          if (parseFloat(String(data).replace(/[^\d.-]/g, '')) > 0)
            return '<span style="color:#d32f2f;font-weight:bold">' + data + '</span>';
          return data;
        }
      }
    ])
    .concat(dynDeductions)
    .concat([
      { data: 'pembulatan' },
      { data: 'total_pendapatan' },
      { data: 'max_pot_kop' },
      // Potongan Koperasi
      {
        data: 'pot_koperasi',
        render: function(data, type, row) {
          if (parseFloat(String(data).replace(/[^\d.-]/g, '')) > 0)
            return '<span style="color:#d32f2f;font-weight:bold">' + data + '</span>';
          return data;
        }
      },
      // Jumlah Potongan
      {
        data: 'total_potongan',
        render: function(data, type, row) {
          if (parseFloat(String(data).replace(/[^\d.-]/g, '')) > 0)
            return '<span style="color:#d32f2f;font-weight:bold">' + data + '</span>';
          return data;
        }
      },
      { data: 'net_received' }
    ]);
    return cols;
  }
}

function initTable(selector, role) {
  $(selector).DataTable({
    processing: true,
    serverSide: true,
    ajax: {
      url: baseUrl + '&category=' + role,
      type: 'POST',
      data: { csrf_token: csrf }
    },
    columns: getColumns(role),
    order: [ [isSemua ? 1 : 1, 'asc'] ],
    footerCallback: function(row, data, start, end, display) {
      let api = this.api();
      let firstNumberCol = isSemua ? 2 : 3;
      if (isSemua)
        $(api.column(1).footer()).html('<span class="text-end fw-bold">JUMLAH:</span>');
      else
        $(api.column(2).footer()).html('<span class="text-end">Jumlah:</span>');
      api.columns().every(function(idx) {
        if (idx < firstNumberCol) return;
        let total = this.data().reduce(function(a, b) {
          let v = typeof b === 'string'
            ? parseInt(String(b).replace(/[^0-9\-]/g, ''), 10) || 0
            : (typeof b === 'number' ? b : 0);
          return a + v;
        }, 0);
        $(this.footer()).html('Rp ' + total.toLocaleString('id-ID', { minimumFractionDigits: 0 }));
      });
    },
    dom: 'Bfrtip',
    buttons: ['excel', 'pdf'],
    lengthMenu: [10, 25, 50, 100],
    language: { url: "../assets/js/Indonesian.json" }
  });
}

$(document).ready(function() {
  initTable('#tblGuru', 'guru');
  initTable('#tblKaryawan', 'karyawan');
  initTable('#tblManajer', 'manajer');
});
</script>

</body>
</html>
