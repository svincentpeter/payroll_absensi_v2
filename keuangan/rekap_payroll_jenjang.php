<?php
// File: /payroll_absensi_v2/keuangan/rekap_payroll_jenjang.php

$pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:Keuangan','M:Superadmin']);
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];
require_once __DIR__ . '/../koneksi.php';

// Ambil parameter
$jenjang = $_GET['jenjang'] ?? '';
$bulan   = intval($_GET['bulan'] ?? date('n'));
$tahun   = intval($_GET['tahun'] ?? date('Y'));

// Mapping warna & icon sesuai jenjang
$jenjangMeta = [
  'TK'  => ['icon'=>'fas fa-child',              'color'=>'#e74c3c'],
  'SD'  => ['icon'=>'fas fa-book-open',          'color'=>'#3498db'],
  'SMP' => ['icon'=>'fas fa-user-graduate',      'color'=>'#2ecc71'],
  'SMA' => ['icon'=>'fas fa-chalkboard-teacher', 'color'=>'#f1c40f'],
  'SMK' => ['icon'=>'fas fa-tools',              'color'=>'#9b59b6'],
];
$meta = $jenjangMeta[$jenjang] ?? ['icon'=>'fas fa-school','color'=>'#34495e'];

// -----------------------------------------------------------------------------
// 1) Ambil konfigurasi payhead
// -----------------------------------------------------------------------------
$PAYHEAD_GROUPS = [];
$rs = $conn->query("
  SELECT group_name
    FROM payhead_groups
   WHERE jenis='earnings'
GROUP BY group_name
ORDER BY MIN(sort_order), group_name
");
while ($g = $rs->fetch_assoc()) {
  $PAYHEAD_GROUPS[] = $g['group_name'];
}
$rs->free();

// semua payhead yang sudah dikelompokkan
$groupMembers = [];
$rs = $conn->query("SELECT DISTINCT payhead_name FROM payhead_groups");
while ($gm = $rs->fetch_assoc()) {
  $groupMembers[] = $gm['payhead_name'];
}
$rs->free();

// ambil semua earning payheads (kecuali yg grouped)
$inList = $groupMembers
  ? "'" . implode("','", array_map([$conn,'real_escape_string'],$groupMembers)) . "'"
  : "''";
$earningPayheads = [];
$rs = $conn->query("
  SELECT DISTINCT nama_payhead
    FROM payroll_detail_final
   WHERE jenis='earnings'
     AND nama_payhead NOT IN ($inList)
   ORDER BY nama_payhead
");
while ($r = $rs->fetch_assoc()) {
  $earningPayheads[] = $r['nama_payhead'];
}
$rs->free();

// ambil semua deduction payheads (kecuali yg grouped)
$deductionPayheads = [];
$rs = $conn->query("
  SELECT DISTINCT nama_payhead
    FROM payroll_detail_final
   WHERE jenis='deductions'
     AND nama_payhead NOT IN ($inList)
   ORDER BY nama_payhead
");
while ($r = $rs->fetch_assoc()) {
  $deductionPayheads[] = $r['nama_payhead'];
}
$rs->free();

// gabungkan untuk sub‐query
$PAYHEADS = array_merge($earningPayheads, $deductionPayheads);

// -----------------------------------------------------------------------------
// 2) AJAX handler untuk DataTables
// -----------------------------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(405,'Metode tidak diizinkan');
  }
  verify_csrf_token($_POST['csrf_token'] ?? '');
  $category = sanitize_input($_GET['category'] ?? '');
  switch ($category) {
    case 'guru':
    case 'karyawan':
      loadDetail($conn, $jenjang, $bulan, $tahun, $category);
      break;
    default:
      send_response(400,'Kategori tidak valid');
  }
  exit;
}

function loadDetail($conn, $jenjang, $bulan, $tahun, $kategori) {
  global $earningPayheads, $deductionPayheads, $PAYHEAD_GROUPS, $PAYHEADS;

  // DataTables params
  $draw   = intval($_POST['draw']   ?? 0);
  $start  = intval($_POST['start']  ?? 0);
  $length = intval($_POST['length'] ?? 10);
  $search = sanitize_input($_POST['search']['value'] ?? '');

  // Build WHERE & params
  $sqlWhere = "WHERE a.jenjang=? AND a.kategori=? AND pf.bulan=? AND pf.tahun=?";
  $params   = [$jenjang, $kategori, $bulan, $tahun];
  $types    = "ssii";
  if ($search !== '') {
    $sqlWhere .= " AND (a.nama LIKE ? OR a.nip LIKE ?)";
    $params[]  = "%$search%";
    $params[]  = "%$search%";
    $types    .= "ss";
  }

  // Count total records
  $stmt = $conn->prepare("
    SELECT COUNT(*) AS cnt
      FROM payroll_final pf
      JOIN anggota_sekolah a ON pf.id_anggota=a.id
    $sqlWhere
  ");
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $cnt = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
  $stmt->close();
  $recordsTotal = intval($cnt);

  // Sub‐select untuk payhead lama
  $subCasesPH  = [];
  $outerColsPH = [];
  foreach ($PAYHEADS as $ph) {
    $esc   = $conn->real_escape_string($ph);
    $alias = 'ph_' . substr(md5($ph), 0, 8);
    $subCasesPH[]  = "SUM(CASE WHEN d.nama_payhead='$esc' THEN d.amount ELSE 0 END) AS `$alias`";
    $outerColsPH[] = ", agg.`$alias`";
  }
  // grouped payhead
  $subCasesGR  = [];
  $outerColsGR = [];
  foreach ($PAYHEAD_GROUPS as $grp) {
    $esc  = $conn->real_escape_string($grp);
    $mrs = $conn->query("SELECT payhead_name FROM payhead_groups WHERE group_name='$esc'");
    $members = [];
    while ($m = $mrs->fetch_assoc()) {
      $members[] = $conn->real_escape_string($m['payhead_name']);
    }
    $mrs->free();
    $in = $members ? "'" . implode("','", $members) . "'" : "''";
    $alias = 'gr_' . substr(md5($grp), 0, 8);
    $subCasesGR[]  = "SUM(CASE WHEN d.nama_payhead IN($in) THEN d.amount ELSE 0 END) AS `$alias`";
    $outerColsGR[] = ", agg.`$alias`";
  }
  $subSelect   = implode(",\n    ", array_merge($subCasesPH, $subCasesGR));
  $outerSelect = implode(" ", array_merge($outerColsPH, $outerColsGR));

  // Ambil data detail
  $sqlData = "
    SELECT
      a.nip,
      a.nama,
      a.job_title     AS keterangan,
      pf.gaji_pokok,
      pf.salary_index_amount AS idx_amount,
      pf.potongan_koperasi    AS pot_koperasi
      $outerSelect,
      pf.gaji_bersih
    FROM payroll_final pf
    JOIN anggota_sekolah a ON pf.id_anggota=a.id
    LEFT JOIN (
      SELECT id_payroll_final,
             $subSelect
        FROM payroll_detail_final d
       GROUP BY id_payroll_final
    ) agg ON pf.id=agg.id_payroll_final
    $sqlWhere
    GROUP BY pf.id
    LIMIT ?, ?
  ";
  $typesData   = $types . "ii";
  $paramsData  = array_merge($params, [$start, $length]);

  $stmt = $conn->prepare($sqlData);
  $stmt->bind_param($typesData, ...$paramsData);
  $stmt->execute();
  $res = $stmt->get_result();

  // bangun array untuk JSON
  $data = [];
  while ($r = $res->fetch_assoc()) {
    $row = [
      'nip'         => htmlspecialchars($r['nip']),
      'nama'        => htmlspecialchars($r['nama']),
      'keterangan'  => htmlspecialchars($r['keterangan']),
      'gaji_pokok'  => formatNominal($r['gaji_pokok']),
      'idx_amount'  => formatNominal($r['idx_amount']),
      'pot_koperasi'=> formatNominal($r['pot_koperasi']),
    ];
    foreach ($PAYHEADS as $ph) {
      $alias = 'ph_' . substr(md5($ph), 0, 8);
      $row[$alias] = formatNominal($r[$alias] ?? 0);
    }
    foreach ($PAYHEAD_GROUPS as $grp) {
      $alias = 'gr_' . substr(md5($grp), 0, 8);
      $row[$alias] = formatNominal($r[$alias] ?? 0);
    }
    $row['gaji_bersih'] = formatNominal($r['gaji_bersih']);
    $data[] = $row;
  }
  $stmt->close();

  // output JSON
  echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $recordsTotal,
    'recordsFiltered' => $recordsTotal,
    'data'            => $data
  ], JSON_UNESCAPED_UNICODE);
}

// -----------------------------------------------------------------------------
// 3) HTML & DataTables init
// -----------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Detail <?=htmlspecialchars($jenjang)?> – <?=getIndonesianMonthName($bulan).' '.$tahun?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <!-- CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
  <style>
    /* ── PAGE HEADER ────────────────────────────────── */
    .page-header {
      background: #fff;
      border-left: 5px solid <?= $meta['color'] ?>;
      padding: 1rem 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 .2rem .4rem rgba(0,0,0,.1);
    }
    .page-header h3 {
      margin: 0;
      font-weight: 600;
      color: #333;
    }
    .back-btn {
      color: #fff;
      background: <?= $meta['color'] ?>;
      border: none;
      transition: background .2s;
    }
    .back-btn:hover {
      background: darken(<?= $meta['color'] ?>,10%);
    }
    
    /* font lebih kecil untuk tabel */
    table.dataTable,
    table.dataTable th,
    table.dataTable td {
      font-size: 0.85rem;
    }
    .card-header { background: #0d47a1; color: #fff; }
    table.dataTable thead th { background: #343a40; color: #fff; text-align: center; }
    table.dataTable td, table.dataTable th { vertical-align: middle; }
    tfoot th { font-weight: bold; }
  </style>
</head>
<body>
  <div class="container-fluid py-4">
     <!-- PAGE HEADER -->
     <div class="d-flex align-items-center page-header">
      <button onclick="window.history.back()"
              class="btn back-btn me-3">
        <i class="fas fa-arrow-left"></i>
      </button>
      <h3>
        <i class="<?= $meta['icon'] ?> me-2"></i>
        Rekap <?= htmlspecialchars($jenjang) ?> – <?= getIndonesianMonthName($bulan) . ' ' . $tahun ?>
      </h3>
    </div>

    <?php
      function renderColsHeader($list) {
        foreach ($list as $item) {
          echo "<th>{$item}</th>";
        }
      }
      function renderColsFooter($list) {
        foreach ($list as $_) {
          echo "<th></th>";
        }
      }
    ?>

    <!-- Tabel: Guru -->
    <div class="card mb-4 shadow">
      <div class="card-header"><i class="fas fa-chalkboard-teacher"></i> Rekap Guru</div>
      <div class="card-body">
        <div class="table-responsive">
          <table id="tblGuru" class="table table-sm table-bordered table-striped w-100">
            <thead>
              <tr>
                <th>NIP</th><th>Nama</th><th>Keterangan</th>
                <th>Gaji Pokok</th><th>Indeks</th>
                <?php renderColsHeader($earningPayheads); ?>
                <?php renderColsHeader($PAYHEAD_GROUPS); ?>
                <?php renderColsHeader($deductionPayheads); ?>
                <th>Pot. Koperasi</th><th>Gaji Bersih</th>
              </tr>
            </thead>
            <tfoot>
              <tr>
                <th colspan="3" class="text-end">Jumlah:</th>
                <th></th><th></th>
                <?php renderColsFooter($earningPayheads); ?>
                <?php renderColsFooter($PAYHEAD_GROUPS); ?>
                <?php renderColsFooter($deductionPayheads); ?>
                <th></th><th></th>
              </tr>
            </tfoot>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Tabel: Karyawan -->
    <div class="card mb-4 shadow">
      <div class="card-header"><i class="fas fa-users"></i> Rekap Karyawan</div>
      <div class="card-body">
        <div class="table-responsive">
          <table id="tblKaryawan" class="table table-sm table-bordered table-striped w-100">
            <thead>
              <tr>
                <th>NIP</th><th>Nama</th><th>Keterangan</th>
                <th>Gaji Pokok</th><th>Indeks</th>
                <?php renderColsHeader($earningPayheads); ?>
                <?php renderColsHeader($PAYHEAD_GROUPS); ?>
                <?php renderColsHeader($deductionPayheads); ?>
                <th>Pot. Koperasi</th><th>Gaji Bersih</th>
              </tr>
            </thead>
            <tfoot>
              <tr>
                <th colspan="3" class="text-end">Jumlah:</th>
                <th></th><th></th>
                <?php renderColsFooter($earningPayheads); ?>
                <?php renderColsFooter($PAYHEAD_GROUPS); ?>
                <?php renderColsFooter($deductionPayheads); ?>
                <th></th><th></th>
              </tr>
            </tfoot>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

  </div><!-- /.container -->

  <!-- JS -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script>
    const csrf = '<?=$csrf_token?>';
    const baseUrl = 'rekap_payroll_jenjang.php?ajax=1'
      + '&jenjang=<?=urlencode($jenjang)?>'
      + '&bulan=<?=$bulan?>'
      + '&tahun=<?=$tahun?>';

    // build dynamic columns
    let dynEarnings = [], dynGroups = [], dynDeductions = [];
    <?php foreach($earningPayheads as $ph):
      $alias='ph_'.substr(md5($ph),0,8);
    ?> dynEarnings.push({ data:'<?=$alias?>' }); <?php endforeach; ?>
    <?php foreach($PAYHEAD_GROUPS as $g):
      $alias='gr_'.substr(md5($g),0,8);
    ?> dynGroups.push({ data:'<?=$alias?>' }); <?php endforeach; ?>
    <?php foreach($deductionPayheads as $ph):
      $alias='ph_'.substr(md5($ph),0,8);
    ?> dynDeductions.push({ data:'<?=$alias?>' }); <?php endforeach; ?>

    function initTable(selector, category) {
      return $(selector).DataTable({
        processing: true,
        serverSide: true,
        ajax: {
          url: baseUrl + '&category=' + category,
          type: 'POST',
          data: { csrf_token: csrf }
        },
        columns: [
          { data:'nip' },
          { data:'nama' },
          { data:'keterangan' },
          { data:'gaji_pokok' },
          { data:'idx_amount' }
        ]
        .concat(dynEarnings)
        .concat(dynGroups)
        .concat(dynDeductions)
        .concat([
          { data:'pot_koperasi' },
          { data:'gaji_bersih' }
        ]),
        order: [[1,'asc']],
        footerCallback: function(row, data, start, end, display) {
    let api = this.api();

    // parsing "Rp 1.234.567,89" → number
    let parseVal = v => {
      if (typeof v === 'string') {
        let tok = v.trim().split(' ').pop();
        let num = tok.replace(/\./g, '').replace(/,/g, '.');
        return parseFloat(num) || 0;
      }
      return (typeof v === 'number') ? v : 0;
    };

    // dari kolom ke-4 (idx ≥ 3) dijumlahkan
    api.columns().every(function(idx) {
      if (idx < 3) return;
      let sum = this
        .data()
        .reduce((a, b) => parseVal(a) + parseVal(b), 0);
        $(this.footer()).html(
  'Rp ' + sum.toLocaleString('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  })
);
    });
  },
        lengthMenu: [10,25,50,100],
        language: { url: "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json" },
        dom: 'Bfrtip',
        buttons: ['excel','pdf','print']
      });
    }

    $(document).ready(function(){
      initTable('#tblGuru','guru');
      initTable('#tblKaryawan','karyawan');
    });
  </script>
</body>
</html>
