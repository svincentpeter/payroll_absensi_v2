<?php
// File: /payroll_absensi_v2/keuangan/rekap_payroll.php

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:Keuangan', 'M:Superadmin']);
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

require_once __DIR__ . '/../koneksi.php';
if (ob_get_length()) ob_end_clean();

// -----------------------------------------------------------------------------
// 1) Ambil konfigurasi grup payhead & daftar semua anggota grup
// -----------------------------------------------------------------------------
$PAYHEAD_GROUPS = [];
$sqlGrp = "
  SELECT group_name
    FROM payhead_groups
   WHERE jenis='earnings'
GROUP BY group_name
ORDER BY MIN(sort_order), group_name
";
if ($rs = $conn->query($sqlGrp)) {
    while ($g = $rs->fetch_assoc()) {
        $PAYHEAD_GROUPS[] = $g['group_name'];
    }
    $rs->free();
}

// buat array semua payhead_name yang sudah tergroup
$groupMembers = [];
if ($rs2 = $conn->query("SELECT DISTINCT payhead_name FROM payhead_groups")) {
    while ($gm = $rs2->fetch_assoc()) {
        $groupMembers[] = $gm['payhead_name'];
    }
    $rs2->free();
}

// -----------------------------------------------------------------------------
// 2) Ambil payhead “lama” (earnings + deductions) — tapi kecuali yang di‐group
// -----------------------------------------------------------------------------
$earningPayheads = [];
$deductionPayheads = [];

$inList = $groupMembers
    ? "'" . implode("','", array_map([$conn, 'real_escape_string'], $groupMembers)) . "'"
    : "''";

// earnings
$sqlE = "
  SELECT DISTINCT nama_payhead
    FROM payroll_detail_final
   WHERE jenis='earnings'
     AND nama_payhead NOT IN ($inList)
 ORDER BY nama_payhead
";
if ($resE = $conn->query($sqlE)) {
    while ($r = $resE->fetch_assoc()) {
        $earningPayheads[] = $r['nama_payhead'];
    }
    $resE->free();
}

// deductions
$sqlD = "
  SELECT DISTINCT nama_payhead
    FROM payroll_detail_final
   WHERE jenis='deductions'
     AND nama_payhead NOT IN ($inList)
 ORDER BY nama_payhead
";
if ($resD = $conn->query($sqlD)) {
    while ($r = $resD->fetch_assoc()) {
        $deductionPayheads[] = $r['nama_payhead'];
    }
    $resD->free();
}

// semua untuk sub‑query nanti
$PAYHEADS = array_merge($earningPayheads, $deductionPayheads);

// -----------------------------------------------------------------------------
// 3) Audit log akses & default filter
// -----------------------------------------------------------------------------
add_audit_log($conn, $_SESSION['nip'], 'AccessPage', "Akses halaman Rekap Payroll");
$defaultBulan = date('n');
$defaultTahun = date('Y');

/* -------------------- AJAX HANDLING -------------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_response(405, 'Metode tidak diizinkan');
    }
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $case = sanitize_input($_POST['case'] ?? '');
    switch ($case) {
      case 'LoadingRekapPayroll':
        add_audit_log($conn, $_SESSION['nip'], 'LoadingRekapPayroll', 'Memuat rekap payroll');
        LoadingRekapPayroll($conn);
        break;
      case 'AddAuditLog':
        $action  = sanitize_input($_POST['action'] ?? '');
        $details = sanitize_input($_POST['details'] ?? '');
        if ($action && $details) {
            $ok = add_audit_log($conn, $_SESSION['nip'], $action, $details);
            send_response($ok ? 0 : 1, $ok ? 'Audit log dicatat' : 'Gagal mencatat');
        }
        send_response(1, 'Data log tidak lengkap');
      default:
        send_response(400, 'Kasus tidak valid');
    }
    $conn->close();
    exit();
}

/* -------------------- FUNGSI UTAMA -------------------- */
function LoadingRekapPayroll($conn)
{
    global $earningPayheads, $deductionPayheads, $PAYHEADS, $PAYHEAD_GROUPS;

    // ambil params DataTables
    $draw     = intval($_POST['draw']   ?? 0);
    $start    = intval($_POST['start']  ?? 0);
    $length   = intval($_POST['length'] ?? 10);
    $search   = sanitize_input($_POST['search']['value'] ?? '');
    $jenjang  = sanitize_input($_POST['jenjang']  ?? '');
    $bulan    = intval($_POST['bulan']    ?? 0);
    $tahun    = intval($_POST['tahun']    ?? 0);
    $show_all = intval($_POST['show_all'] ?? 0);

    // build WHERE
    $sqlWhere = " WHERE 1=1 ";
    $params   = []; $types = "";
    if ($jenjang !== '') {
        $sqlWhere .= " AND a.jenjang=?";    $params[]=$jenjang;  $types.='s';
    }
    if ($bulan > 0) {
        $sqlWhere .= " AND pf.bulan=?";      $params[]=$bulan;    $types.='i';
    }
    if ($tahun > 0) {
        $sqlWhere .= " AND pf.tahun=?";      $params[]=$tahun;    $types.='i';
    }
    if ($search !== '') {
        $sqlWhere .= " AND a.jenjang LIKE ?"; $params[]="%{$search}%"; $types.='s';
    }
    if ($bulan <= 0 && !$show_all) {
        $sqlWhere .= " AND 0=1 ";
    }

    // totalFiltered
    $stmtF = $conn->prepare("
      SELECT COUNT(DISTINCT a.jenjang) AS total
        FROM payroll_final pf
        JOIN anggota_sekolah a ON pf.id_anggota=a.id
        $sqlWhere
    ");
    if ($types) {
      $stmtF->bind_param($types, ...$params);
    }
    $stmtF->execute();
    $totalFiltered = intval($stmtF->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtF->close();

    // totalRecords
    $recordsTotal = intval(
      $conn->query("SELECT COUNT(DISTINCT a.jenjang) AS total FROM payroll_final pf JOIN anggota_sekolah a ON pf.id_anggota=a.id")
           ->fetch_assoc()['total'] ?? 0
    );

    // --- SUB‐SELECT & OUTER‐SELECT UNTUK PAYHEAD LAMA ---
    $subCasesPH  = []; $outerColsPH = [];
    foreach ($PAYHEADS as $ph) {
        $esc   = $conn->real_escape_string($ph);
        $alias = 'payhead_' . substr(md5($ph),0,8);
        $subCasesPH[]  = "SUM(CASE WHEN d.nama_payhead='$esc' THEN d.amount ELSE 0 END) AS `$alias`";
        $outerColsPH[] = ", SUM(IFNULL(agg.`$alias`,0)) AS `$alias`";
    }

    // --- SUB‐SELECT & OUTER‐SELECT UNTUK GROUPED PAYHEAD ---
    $subCasesGR  = []; $outerColsGR = [];
    foreach ($PAYHEAD_GROUPS as $grp) {
        $esc    = $conn->real_escape_string($grp);
        $mrs    = $conn->query("SELECT payhead_name FROM payhead_groups WHERE group_name='$esc'");
        $members= [];
        while ($m = $mrs->fetch_assoc()) {
            $members[] = $conn->real_escape_string($m['payhead_name']);
        }
        $mrs->free();
        $inList = $members ? "'".implode("','",$members)."'" : "''";
        $alias  = 'grp_' . substr(md5($grp),0,8);
        $subCasesGR[]  = "SUM(CASE WHEN d.nama_payhead IN($inList) THEN d.amount ELSE 0 END) AS `$alias`";
        $outerColsGR[] = ", SUM(IFNULL(agg.`$alias`,0)) AS `$alias`";
    }

    // gabungkan
    $subSelect   = implode(",\n               ", array_merge($subCasesPH, $subCasesGR));
    $outerSelect = implode("\n              ", array_merge($outerColsPH, $outerColsGR));

    // build main SQL
    $sqlData = "
      SELECT
        a.jenjang,
        SUM(pf.gaji_pokok) AS total_gaji_pokok
        $outerSelect,
        SUM(pf.gaji_bersih) AS total_gaji_bersih,
        SUM(IFNULL(kg.total_lain_lain,0)) AS total_lain_lain
      FROM payroll_final pf
      JOIN anggota_sekolah a ON pf.id_anggota=a.id

      LEFT JOIN (
        SELECT id_payroll_final,
               $subSelect
          FROM payroll_detail_final d
         GROUP BY id_payroll_final
      ) agg ON pf.id=agg.id_payroll_final

      LEFT JOIN (
        SELECT id_anggota, SUM(jumlah) AS total_lain_lain
          FROM kenaikan_gaji_tahunan
         WHERE pindah_ke_lain_lain=1
         GROUP BY id_anggota
      ) kg ON a.id=kg.id_anggota

      $sqlWhere
      GROUP BY a.jenjang
    ";

    // ordering
    $orderBy = " ORDER BY a.jenjang ASC";
    if (!empty($_POST['order'][0]['column'])) {
        $idx = intval($_POST['order'][0]['column']);
        $dir = ($_POST['order'][0]['dir']==='asc'?'ASC':'DESC');
        if ($idx === 0) {
            $orderBy = " ORDER BY a.jenjang $dir";
        } elseif ($idx === 1) {
            $orderBy = " ORDER BY total_gaji_pokok $dir";
        } else {
            // susun semua alias urut: payhead lama lalu grouped
            $allAliases = array_map(function($ph){ return 'payhead_'.substr(md5($ph),0,8); }, $GLOBALS['PAYHEADS']);
            $allAliases = array_merge($allAliases,
                array_map(function($g){ return 'grp_'.substr(md5($g),0,8); }, $GLOBALS['PAYHEAD_GROUPS'])
            );
            $pos = $idx - 2;
            if (isset($allAliases[$pos])) {
                $orderBy = " ORDER BY ".$allAliases[$pos]." $dir";
            }
        }
    }

    // paging
    $sqlData .= $orderBy . " LIMIT ?, ?";
    $typesData = $types . "ii";
    $params[]  = $start;
    $params[]  = $length;

    $stmtD = $conn->prepare($sqlData);
    if ($typesData !== 'ii') {
        $stmtD->bind_param($typesData, ...$params);
    } else {
        $stmtD->bind_param('ii', $start, $length);
    }
    $stmtD->execute();
    $res = $stmtD->get_result();

    // bangun output
    $out = [];
    while ($r = $res->fetch_assoc()) {
        $row = [
          'jenjang'          => htmlspecialchars($r['jenjang']),
          'total_gaji_pokok' => formatNominal($r['total_gaji_pokok']),
        ];
        // payhead lama
        foreach ($earningPayheads as $ph) {
            $alias = 'payhead_'.substr(md5($ph),0,8);
            $row[$alias] = formatNominal($r[$alias] ?? 0);
        }
        foreach ($deductionPayheads as $ph) {
            $alias = 'payhead_'.substr(md5($ph),0,8);
            $row[$alias] = formatNominal($r[$alias] ?? 0);
        }
        // grouped payhead
        foreach ($PAYHEAD_GROUPS as $grp) {
            $alias = 'grp_'.substr(md5($grp),0,8);
            $row[$alias] = formatNominal($r[$alias] ?? 0);
        }
        $row['total_lain_lain']   = formatNominal($r['total_lain_lain']);
        $row['total_gaji_bersih'] = formatNominal($r['total_gaji_bersih']);
        $row['aksi'] = '
          <a href="rekap_payroll_details.php?jenjang=' . urlencode($r['jenjang']) . '"
             class="btn btn-info btn-sm" data-bs-toggle="tooltip" title="Lihat Detail">
            <i class="fas fa-file-invoice"></i>
          </a>';
        $out[] = $row;
    }
    $stmtD->close();

    echo json_encode([
      "draw"            => $draw,
      "recordsTotal"    => $recordsTotal,
      "recordsFiltered" => $totalFiltered,
      "data"            => $out
    ], JSON_UNESCAPED_UNICODE);
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Rekap Payroll</title>
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
    .card-header { background: linear-gradient(45deg,#0d47a1,#42a5f5); color:#fff; }
    thead th { background:#343a40; color:#fff; text-align:center; }
    #rekapPayrollTable th, #rekapPayrollTable td { vertical-align:middle; }
    #loadingSpinner { position:fixed; top:50%; left:50%; z-index:9999; display:none; }
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
          <h1 class="h3 mb-4 text-gray-800">
            <i class="fas fa-chart-bar"></i>
            Rekap Payroll – <?= getIndonesianMonthName($defaultBulan) ?> <?= $defaultTahun ?>
          </h1>

          <!-- Filter -->
          <div class="card mb-4 shadow">
            <div class="card-header"><i class="fas fa-search"></i> Filter Rekap Payroll</div>
            <div class="card-body bg-light">
              <form id="filterForm" class="row g-2 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" id="filterShowAll" name="show_all" value="0">

                <div class="col-auto">
                  <label class="form-label">Jenjang</label>
                  <select class="form-select" id="filterJenjang" name="jenjang">
                    <option value="">Semua</option>
                    <?php foreach(getOrderedJenjang() as $j): ?>
                      <option><?=htmlspecialchars($j)?></option>
                    <?php endforeach;?>
                  </select>
                </div>

                <div class="col-auto">
                  <label class="form-label">Bulan</label>
                  <select class="form-select" id="filterBulan" name="bulan">
                    <option value="">--</option>
                    <?php for($m=1;$m<=12;$m++): ?>
                      <option value="<?=$m?>" <?= $m==$defaultBulan?'selected':''; ?>>
                        <?= getIndonesianMonthName($m) ?>
                      </option>
                    <?php endfor;?>
                  </select>
                </div>

                <div class="col-auto">
                  <label class="form-label">Tahun</label>
                  <select class="form-select" id="filterTahun" name="tahun">
                    <option value="">Semua</option>
                    <?php
                      $yr = $conn->query("SELECT DISTINCT tahun FROM payroll_final ORDER BY tahun DESC");
                      while($y=$yr->fetch_assoc()):
                    ?>
                      <option value="<?=$y['tahun']?>" <?= $y['tahun']==$defaultTahun?'selected':''; ?>>
                        <?=$y['tahun']?>
                      </option>
                    <?php endwhile; $yr->free(); ?>
                  </select>
                </div>

                <div class="col-auto">
                  <button id="btnApplyFilter" class="btn btn-primary"><i class="fas fa-filter"></i> Terapkan</button>
                  <button id="btnResetFilter" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</button>
                  <button id="btnShowAll" class="btn btn-warning"><i class="fas fa-eye"></i> Tampilkan Semua</button>
                  <button id="btnExportData" class="btn btn-success"><i class="fas fa-file-export"></i> Export</button>
                </div>
              </form>
            </div>
          </div>

          <!-- Tabel Rekap Payroll -->
          <div class="card shadow mb-4">
            <div class="card-header"><i class="fas fa-clipboard-list"></i> Daftar Rekap Payroll</div>
            <div class="card-body">
              <div class="table-responsive">
                <table id="rekapPayrollTable" class="table table-bordered table-sm nowrap" style="width:100%">
                  <thead>
                    <tr>
                      <th>Jenjang</th>
                      <th>Total Gaji Pokok</th>
                      <!-- PAYHEAD LAMA -->
                      <?php foreach($earningPayheads as $ph): ?>
                        <th><?=htmlspecialchars($ph)?></th>
                      <?php endforeach; ?>
                      <!-- GROUPED PAYHEAD -->
                      <?php foreach($PAYHEAD_GROUPS as $grp): ?>
                        <th><?=htmlspecialchars($grp)?></th>
                      <?php endforeach; ?>
                      <?php foreach($deductionPayheads as $ph): ?>
                        <th><?=htmlspecialchars($ph)?></th>
                      <?php endforeach; ?>
                      
                      <th>Lain‑lain</th>
                      <th>Gaji Bersih</th>
                      <th>Aksi</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>

        </div><!-- /.container -->

        <div id="loadingSpinner">
          <div class="spinner-border text-primary"></div>
        </div>

      </div><!-- #content -->
      <?php include __DIR__ . '/../footer.php'; ?>
    </div><!-- #content-wrapper -->
  </div><!-- #wrapper -->

  <!-- JS Dependencies -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
  // setup dynamicColumns
  var dynamicColumns = [];

  // 1) PAYHEAD LAMA
  <?php foreach($earningPayheads as $ph):
    $alias = 'payhead_'.substr(md5($ph),0,8);
  ?>
    dynamicColumns.push({ data:'<?= $alias ?>', name:'<?= $alias ?>', defaultContent:'0' });
  <?php endforeach; ?>

  // 2) GROUPED PAYHEAD
  <?php foreach($PAYHEAD_GROUPS as $grp):
    $alias = 'grp_'.substr(md5($grp),0,8);
  ?>
    dynamicColumns.push({ data:'<?= $alias ?>', name:'<?= $alias ?>', defaultContent:'0' });
  <?php endforeach; ?>

  // 3) PAYHEAD DEDUCTIONS
  <?php foreach($deductionPayheads as $ph):
    $alias = 'payhead_'.substr(md5($ph),0,8);
  ?>
    dynamicColumns.push({ data:'<?= $alias ?>', name:'<?= $alias ?>', defaultContent:'0' });
  <?php endforeach; ?>

  $(function(){
    // init tooltip
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(el => new bootstrap.Tooltip(el));

    // Toast
    const Toast = Swal.mixin({
      toast:true, position:'top-end', timer:3000,
      showConfirmButton:false,
      didOpen: t=>{
        t.addEventListener('mouseenter', Swal.stopTimer);
        t.addEventListener('mouseleave', Swal.resumeTimer);
      }
    });
    function showToast(msg,icon='success'){ Toast.fire({icon,title:msg}); }

    var csrfToken = '<?= $csrf_token ?>';
    var tbl = $('#rekapPayrollTable').DataTable({
      processing:true, serverSide:true, responsive:true,
      ajax:{
        url:'rekap_payroll.php?ajax=1', type:'POST',
        data: d=>{
          d.case='LoadingRekapPayroll';
          d.csrf_token=csrfToken;
          d.jenjang=$('#filterJenjang').val();
          d.bulan=$('#filterBulan').val();
          d.tahun=$('#filterTahun').val();
          d.show_all=$('#filterShowAll').val();
        },
        beforeSend:()=>$('#loadingSpinner').show(),
        complete: ()=>$('#loadingSpinner').hide(),
        error: ()=>showToast('Gagal load data','error')
      },
      columns:[
        {data:'jenjang',name:'jenjang'},
        {data:'total_gaji_pokok',name:'total_gaji_pokok'}
      ].concat(dynamicColumns).concat([
        {data:'total_lain_lain',name:'total_lain_lain'},
        {data:'total_gaji_bersih',name:'total_gaji_bersih'},
        {data:'aksi',orderable:false,searchable:false}
      ]),
      order:[[0,'asc']],
      language:{ url:"//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json" },
      dom:'Bfrtip',
      buttons:[
        { extend:'excelHtml5', className:'btn btn-success btn-sm',
          text:'<i class="fas fa-file-excel"></i> Export Excel',
          exportOptions:{columns:':visible:not(:last-child)'} },
        { extend:'pdfHtml5', className:'btn btn-danger btn-sm',
          text:'<i class="fas fa-file-pdf"></i> Export PDF',
          exportOptions:{columns:':visible:not(:last-child)'},
          customize:doc=>{
            doc.styles.tableHeader.fillColor='#343a40';
            doc.styles.tableHeader.color='white';
            doc.defaultStyle.fontSize=10;
          }
        },
        { extend:'print', className:'btn btn-info btn-sm',
          text:'<i class="fas fa-print"></i> Print',
          exportOptions:{columns:':visible:not(:last-child)'} }
      ],
      responsive:true
    });

    // filter buttons selalu reload via `tbl`
    $('#btnApplyFilter').click(e=>{
      e.preventDefault();
      tbl.ajax.reload();
    });
    $('#btnResetFilter').click(e=>{
      e.preventDefault();
      $('#filterForm')[0].reset();
      $('#filterShowAll').val("0");
      tbl.ajax.reload();
    });
    $('#btnShowAll').click(e=>{
      e.preventDefault();
      $('#filterShowAll').val("1");
      $('#filterBulan').val("");
      tbl.ajax.reload();
    });
    $('#btnExportData').click(e=>{
      e.preventDefault();
      tbl.button('.buttons-excel').trigger();
    });
  });
</script>

</body>
</html>
