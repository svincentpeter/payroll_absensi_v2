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

/*──────────────── PARAMETER & META ────────────────*/
$jenjang = $_GET['jenjang'] ?? '';
$bulan   = intval($_GET['bulan'] ?? date('n'));
$tahun   = intval($_GET['tahun'] ?? date('Y'));

$jenjangMeta = [
  'TK'      => ['icon'=>'fas fa-child',              'color'=>'#e74c3c'],
  'SD'      => ['icon'=>'fas fa-book-open',          'color'=>'#3498db'],
  'SMP'     => ['icon'=>'fas fa-user-graduate',      'color'=>'#2ecc71'],
  'SMA'     => ['icon'=>'fas fa-chalkboard-teacher', 'color'=>'#f1c40f'],
  'SMK'     => ['icon'=>'fas fa-tools',              'color'=>'#9b59b6'],
  'SEMUA'   => ['icon'=>'fas fa-layer-group',        'color'=>'#273c75'],
  'MANAJER' => ['icon'=>'fas fa-user-tie',           'color'=>'#b44ef9'],
];
$meta     = $jenjangMeta[strtoupper($jenjang)] ?? ['icon'=>'fas fa-school','color'=>'#34495e'];
$isSemua  = (strtolower($jenjang) === 'semua' || $jenjang === '');

/*──────────── 1. KONFIGURASI PAYHEAD ────────────*/
$PAYHEAD_GROUPS  = [];
$rs = $conn->query("
  SELECT group_name
    FROM payhead_groups
   WHERE jenis='earnings'
GROUP BY group_name
ORDER BY MIN(sort_order), group_name");
while ($g = $rs->fetch_assoc()) $PAYHEAD_GROUPS[] = $g['group_name'];
$rs->free();

/* daftar payhead individual (di luar group) */
$groupMembers = [];
$rs = $conn->query("SELECT DISTINCT payhead_name FROM payhead_groups");
while ($gm = $rs->fetch_assoc()) $groupMembers[] = $gm['payhead_name'];
$rs->free();
$inList = $groupMembers
  ? "'" . implode("','", array_map([$conn,'real_escape_string'],$groupMembers)) . "'"
  : "''";

/* earnings */
$earningPayheads = [];
$rs = $conn->query("
  SELECT DISTINCT nama_payhead
    FROM payroll_detail_final
   WHERE jenis='earnings' AND nama_payhead NOT IN ($inList)
   ORDER BY nama_payhead");
while ($r = $rs->fetch_assoc()) $earningPayheads[] = $r['nama_payhead'];
$rs->free();

/* deductions */
$deductionPayheads = [];
$rs = $conn->query("
  SELECT DISTINCT nama_payhead
    FROM payroll_detail_final
   WHERE jenis='deductions' AND nama_payhead NOT IN ($inList)
   ORDER BY nama_payhead");
while ($r = $rs->fetch_assoc()) $deductionPayheads[] = $r['nama_payhead'];
$rs->free();

/*─────────── kolom fixed yg selalu ada ───────────*/
$FIXED_EARNINGS = [
  '__kgt_active'      => 'Kenaikan Gaji ' . $tahun . '/' . ($tahun+1),
  '__honor_jam_lebih' => 'Honor Kelebihan Jam'
];
$FIXED_DEDUCTIONS = [
  '__pot_absensi'     => 'Potongan Absensi'
];

/*────────── 2.  FUNGSI fetchSummaryRow() ─────────*/
function fetchSummaryRow(
  mysqli $conn,
  string $jenjang,
  int $bulan,
  int $tahun,
  string $kategori,
  array $earningPayheads,
  array $PAYHEAD_GROUPS,
  array $deductionPayheads
) {
    /* WHERE dinamis */
    if ($kategori === 'manajer') {
        $where  = "a.role='M' AND a.jenjang=? AND pf.bulan=? AND pf.tahun=?";
        $params = [$jenjang, $bulan, $tahun];
        $types  = 'sii';
    } else {
        $where  = "a.kategori=? AND a.role<>'M' AND a.jenjang=? AND pf.bulan=? AND pf.tahun=?";
        $params = [$kategori, $jenjang, $bulan, $tahun];
        $types  = 'ssii';
    }

    /* SUM pokok, indeks, koperasi, honor jam, absensi */
    $sql1 = "SELECT SUM(pf.gaji_pokok)          AS gaji_pokok,
                    SUM(pf.salary_index_amount) AS idx_amount,
                    SUM(pf.potongan_koperasi)   AS pot_koperasi,
                    SUM(pf.honor_jam_lebih)     AS honor_jam,
                    SUM(pf.potongan_absensi)    AS pot_absensi,
                    /* KGT aktif */
                    SUM((
                        SELECT IFNULL(SUM(k.jumlah),0)
                          FROM kenaikan_gaji_tahunan k
                         WHERE k.id_anggota = pf.id_anggota
                           AND k.status='aktif'
                           AND k.pindah_ke_lain_lain=0
                           AND pf.tgl_payroll BETWEEN k.tanggal_mulai AND k.tanggal_berakhir
                    ))                             AS kgt_active
              FROM payroll_final pf
              JOIN anggota_sekolah a ON pf.id_anggota = a.id
             WHERE $where";
    $stmt1 = $conn->prepare($sql1);
    $stmt1->bind_param($types, ...$params);
    $stmt1->execute();
    $r1 = $stmt1->get_result()->fetch_assoc();
    $stmt1->close();

    $komponen = [];

    /* earnings individual */
    foreach ($earningPayheads as $ph) {
        $sql2 = "SELECT SUM(amount) FROM (
                   SELECT SUM(d.amount) AS amount
                     FROM payroll_final pf
                     JOIN anggota_sekolah a ON pf.id_anggota = a.id
                     JOIN payroll_detail_final d ON pf.id = d.id_payroll_final
                    WHERE $where AND d.nama_payhead=? GROUP BY pf.id
                 ) AS sub";
        $stmt2  = $conn->prepare($sql2);
        $stmt2->bind_param($types.'s', ...array_merge($params,[$ph]));
        $stmt2->execute();
        $komponen[$ph] = $stmt2->get_result()->fetch_row()[0] ?? 0;
        $stmt2->close();
    }

    /* earnings group */
    foreach ($PAYHEAD_GROUPS as $grp) {
        $mrs = $conn->query("SELECT payhead_name FROM payhead_groups WHERE group_name='".$conn->real_escape_string($grp)."'");
        $members=[]; while($m=$mrs->fetch_assoc()) $members[]=$conn->real_escape_string($m['payhead_name']); $mrs->free();
        if(!$members){ $komponen[$grp]=0; continue; }
        $in="'".implode("','",$members)."'";
        $sql2="SELECT SUM(amount) FROM (
                 SELECT SUM(d.amount) AS amount
                   FROM payroll_final pf
                   JOIN anggota_sekolah a ON pf.id_anggota = a.id
                   JOIN payroll_detail_final d ON pf.id = d.id_payroll_final
                  WHERE $where AND d.nama_payhead IN($in) GROUP BY pf.id
               ) sub";
        $stmt2=$conn->prepare($sql2);
        $stmt2->bind_param($types,...$params); $stmt2->execute();
        $komponen[$grp]=$stmt2->get_result()->fetch_row()[0]??0; $stmt2->close();
    }

    /* deductions individual */
    foreach ($deductionPayheads as $ph) {
        $sql2 = "SELECT SUM(amount) FROM (
                   SELECT SUM(d.amount) AS amount
                     FROM payroll_final pf
                     JOIN anggota_sekolah a ON pf.id_anggota = a.id
                     JOIN payroll_detail_final d ON pf.id = d.id_payroll_final
                    WHERE $where AND d.nama_payhead=? GROUP BY pf.id
                 ) sub";
        $stmt2=$conn->prepare($sql2);
        $stmt2->bind_param($types.'s',...array_merge($params,[$ph]));
        $stmt2->execute();
        $komponen[$ph]=$stmt2->get_result()->fetch_row()[0]??0; $stmt2->close();
    }

    /* rekap */
    $gajiPokok   = (float)$r1['gaji_pokok'];
    $idxAmount   = (float)$r1['idx_amount'];
    $potKoperasi = (float)$r1['pot_koperasi'];
    $honorJam    = (float)$r1['honor_jam'];
    $potAbsensi  = (float)$r1['pot_absensi'];
    $kgtActive   = (float)$r1['kgt_active'];

    $sumEarnPH = array_sum(array_intersect_key($komponen,array_flip($earningPayheads)));
    $sumGrpPH  = array_sum(array_intersect_key($komponen,array_flip($PAYHEAD_GROUPS)));
    $sumDedPH  = array_sum(array_intersect_key($komponen,array_flip($deductionPayheads)));

    $totalPendapatan = $gajiPokok + $idxAmount + $sumEarnPH + $sumGrpPH + $kgtActive + $honorJam;
    $totalPotongan   = $potKoperasi + $sumDedPH + $potAbsensi;

    $maxPotKop  = $totalPendapatan * 0.65;
    $netReceived= $totalPendapatan - $totalPotongan;
    $rounded    = round($netReceived/100)*100;

    /* row hasil, gunakan prefix sesuai mapping frontend */
    $row = [
        'Gaji Pokok'  => $gajiPokok,
        'Indeks'      => $idxAmount,
        'ph_'.substr(md5('__kgt_active'),0,8) => $kgtActive,
        'ph_'.substr(md5('__honor_jam_lebih'),0,8) => $honorJam
    ];
    foreach($PAYHEAD_GROUPS as $grp)
      $row['gr_'.substr(md5($grp),0,8)] = $komponen[$grp];
    foreach($earningPayheads as $ph)
      $row['ph_'.substr(md5($ph),0,8)] = $komponen[$ph];
    $row['d_'.substr(md5('__pot_absensi'),0,8)] = $potAbsensi;
    foreach($deductionPayheads as $ph)
      $row['d_'.substr(md5($ph),0,8)] = $komponen[$ph];

    $row += [
        'Pembulatan'          => $rounded,
        'Jumlah Pendapatan'   => $totalPendapatan,
        'Max Pot. Kop'        => $maxPotKop,
        'Pot. Koperasi'       => $potKoperasi,
        'Jumlah Potongan'     => $totalPotongan,
        'Jumlah Yang Diterima'=> $netReceived
    ];
    return $row;
}

/*────────── 3.  HANDLER AJAX ──────────*/
if(isset($_GET['ajax']) && $_GET['ajax']==='1'){
  if($_SERVER['REQUEST_METHOD']!=='POST') send_response(405,'Metode tidak diizinkan');
  verify_csrf_token($_POST['csrf_token']??'');
  $category=sanitize_input($_GET['category']??'');

  /* === SEMUA JENJANG === */
  if($isSemua){
    $jenjangList=[];
    $rs=$conn->query("SELECT kode_jenjang,nama_jenjang FROM jenjang_sekolah WHERE is_aktif=1");
    while($j=$rs->fetch_assoc()) $jenjangList[$j['kode_jenjang']]=$j['nama_jenjang']; $rs->free();

    $data=[]; $no=1;
    foreach($jenjangList as $kode=>$nama){
      $t=fetchSummaryRow($conn,$kode,$bulan,$tahun,$category,$earningPayheads,$PAYHEAD_GROUPS,$deductionPayheads);

      $row=[
        'no'=>$no++,
        'jenjang'=>$nama,
        'gaji_pokok'=>formatNominal($t['Gaji Pokok']),
        'idx_amount'=>formatNominal($t['Indeks'])
      ];
      // Fixed earnings
      $row['ph_'.substr(md5('__kgt_active'),0,8)] = formatNominal($t['ph_'.substr(md5('__kgt_active'),0,8)]);
      $row['ph_'.substr(md5('__honor_jam_lebih'),0,8)] = formatNominal($t['ph_'.substr(md5('__honor_jam_lebih'),0,8)]);
      // Dynamic groups & earnings
      foreach($PAYHEAD_GROUPS as $grp)
        $row['gr_'.substr(md5($grp),0,8)] = formatNominal($t['gr_'.substr(md5($grp),0,8)]);
      foreach($earningPayheads as $ph)
        $row['ph_'.substr(md5($ph),0,8)] = formatNominal($t['ph_'.substr(md5($ph),0,8)]);
      // Fixed deduction
      $row['d_'.substr(md5('__pot_absensi'),0,8)] = formatNominal($t['d_'.substr(md5('__pot_absensi'),0,8)]);
      // Dynamic deductions
      foreach($deductionPayheads as $ph)
        $row['d_'.substr(md5($ph),0,8)] = formatNominal($t['d_'.substr(md5($ph),0,8)]);

      $row += [
        'pembulatan'       => formatNominal($t['Pembulatan']),
        'total_pendapatan' => formatNominal($t['Jumlah Pendapatan']),
        'max_pot_kop'      => formatNominal($t['Max Pot. Kop']),
        'pot_koperasi'     => formatNominal($t['Pot. Koperasi']),
        'total_potongan'   => formatNominal($t['Jumlah Potongan']),
        'net_received'     => formatNominal($t['Jumlah Yang Diterima'])
      ];
      $data[]=$row;
    }
    echo json_encode([
      'draw'=>1,'recordsTotal'=>count($data),'recordsFiltered'=>count($data),'data'=>$data
    ],JSON_UNESCAPED_UNICODE); exit;
  }

  /* === detail per-jenjang (file terpisah) === */
  $PAYHEADS=array_merge($earningPayheads,$deductionPayheads);
  require_once __DIR__.'/rekap_payroll_ajax_handler.php';
  exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Rekap Payroll – <?= htmlspecialchars($jenjang) ?> <?= getIndonesianMonthName($bulan).' '.$tahun ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
  <style>
    .page-header{background:#fff;border-left:5px solid <?= $meta['color']?>;padding:1rem 1.5rem;margin-bottom:1.5rem;box-shadow:0 .2rem .4rem rgba(0,0,0,.1);}
    .page-title{font-family:'Poppins',sans-serif;font-weight:600;font-size:2.5rem;color:#0d47a1;text-shadow:1px 1px 2px rgba(0,0,0,.1);display:flex;align-items:center;gap:.5rem;border-bottom:3px solid #1976d2;padding-bottom:.3rem;margin-bottom:1.5rem;}
    .page-title i{color:#1976d2;font-size:2.8rem;}
    .back-btn{color:#fff;background:<?= $meta['color']?>;border:none;}
    .back-btn:hover{filter:brightness(90%);}
    table.dataTable,table.dataTable th,table.dataTable td{font-size:.85rem;}
    .card-header{background:#0d47a1;color:#fff;}
    table.dataTable thead th{background:#343a40;color:#fff;text-align:center;}
    tfoot th{font-weight:bold;}
    .manajer-header{background:#b44ef9!important;color:#fff!important;}
  </style>
</head>
<body>
<div class="container-fluid py-4">
  <div class="d-flex align-items-center page-header">
    <button onclick="window.history.back()" class="btn back-btn me-3"><i class="fas fa-arrow-left"></i></button>
    <h1 class="page-title"><i class="<?= $meta['icon']?> me-2"></i>Rekap <?= strtoupper($jenjang)=='SEMUA'?'Semua Jenjang':htmlspecialchars($jenjang) ?> – <?= getIndonesianMonthName($bulan).' '.$tahun ?></h1>
    <a href="export_payroll_jenjang.php?jenjang=<?= urlencode($jenjang) ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn btn-success ms-auto d-flex align-items-center" style="height:42px" target="_blank"><i class="fas fa-file-excel me-2"></i> Export Excel</a>
  </div>
</div>
<?php
function renderColsHeaderArr($arr){foreach($arr as $k=>$v)echo"<th>{$v}</th>";}
function renderColsHeader($list){foreach($list as $item)echo"<th>{$item}</th>";}
?>
<?php foreach(['Guru'=>'guru','Karyawan'=>'karyawan','Manajer'=>'manajer'] as $label=>$role):?>
<div class="card mb-4 shadow">
  <div class="card-header <?= $role=='manajer'?'manajer-header':''?> d-flex justify-content-between align-items-center">
    <span><i class="fas <?= $role=='guru'?'fa-chalkboard-teacher':($role=='karyawan'?'fa-users':'fa-user-tie')?>"></i> Rekap <?= $label ?></span>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table id="tbl<?= ucfirst($role)?>" class="table table-sm table-bordered table-striped w-100">
        <thead><tr>
<?php if($isSemua):?>
  <th>No</th><th>Jenjang</th><th>Gaji Pokok</th><th>Indeks</th>
  <?php renderColsHeaderArr($FIXED_EARNINGS);?>
  <?php renderColsHeader($PAYHEAD_GROUPS);?>
  <?php renderColsHeader($earningPayheads);?>
  <?php renderColsHeaderArr($FIXED_DEDUCTIONS);?>
  <?php renderColsHeader($deductionPayheads);?>
  <th>Pembulatan</th><th>Jumlah Pendapatan</th><th>Max Pot. Kop</th><th>Pot. Koperasi</th><th>Jumlah Potongan</th><th>Jumlah Yang Diterima</th>
<?php else: /* detail per-jenjang (per-orang) */ ?>
  <th>NIP</th><th>Nama</th><th>Keterangan</th><th>Gaji Pokok</th><th>Indeks</th>
  <?php renderColsHeaderArr($FIXED_EARNINGS);?>
  <?php renderColsHeader($PAYHEAD_GROUPS);?>
  <?php renderColsHeader($earningPayheads);?>
  <?php renderColsHeaderArr($FIXED_DEDUCTIONS);?>
  <?php renderColsHeader($deductionPayheads);?>
  <th>Pembulatan</th><th>Jumlah Pendapatan</th><th>Max Pot. Kop</th><th>Pot. Koperasi</th><th>Jumlah Potongan</th><th>Jumlah Yang Diterima</th>
<?php endif;?>
        </tr></thead>
        <tfoot><tr>
<?php if($isSemua):?>
  <th></th><th class="text-end fw-bold">JUMLAH:</th>
  <?php
    $extra = count($FIXED_EARNINGS)+count($PAYHEAD_GROUPS)+count($earningPayheads)+count($FIXED_DEDUCTIONS)+count($deductionPayheads)+8;
    for($i=0;$i<$extra;$i++) echo'<th></th>';
  ?>
<?php else:?>
  <th colspan="3"></th><th class="text-end">Jumlah:</th>
  <?php
    $extra = count($FIXED_EARNINGS)+count($PAYHEAD_GROUPS)+count($earningPayheads)+count($FIXED_DEDUCTIONS)+count($deductionPayheads)+6;
    for($i=0;$i<$extra;$i++) echo'<th></th>';
  ?>
<?php endif;?>
        </tr></tfoot>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>
<?php endforeach;?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script>
const csrf     = '<?= $csrf_token ?>';
const baseUrl  = 'rekap_payroll_jenjang.php?ajax=1&jenjang=<?= urlencode($jenjang) ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>';
const isSemua  = <?= $isSemua?'true':'false' ?>;

/* kolom dinamis */
let fixedEarn  = [
  {data:'ph_<?= substr(md5("__kgt_active"),0,8) ?>'},
  {data:'ph_<?= substr(md5("__honor_jam_lebih"),0,8) ?>'}
];
let fixedDed   = [
  {data:'d_<?= substr(md5("__pot_absensi"),0,8) ?>', render:redNegative}
];
let dynGroups  = [];
<?php foreach($PAYHEAD_GROUPS as $g):?>
dynGroups.push({data:'gr_<?= substr(md5($g),0,8) ?>'});
<?php endforeach;?>
let dynEarn    = [];
<?php foreach($earningPayheads as $ph):?>
dynEarn.push({data:'ph_<?= substr(md5($ph),0,8) ?>'});
<?php endforeach;?>
let dynDed=[];
<?php foreach($deductionPayheads as $ph):?>
dynDed.push({data:'d_<?= substr(md5($ph),0,8) ?>',render:redNegative});
<?php endforeach;?>

function redNegative(data){if(parseFloat(String(data).replace(/[^\d.-]/g,''))>0)return'<span style="color:#d32f2f;font-weight:bold">'+data+'</span>';return data;}

function getCols(role){
  if(isSemua){
    return [
      {data:'no'},{data:'jenjang'},{data:'gaji_pokok'},{data:'idx_amount'}
    ].concat(fixedEarn,dynGroups,dynEarn,fixedDed,dynDed).concat([
      {data:'pembulatan'},{data:'total_pendapatan'},{data:'max_pot_kop'},
      {data:'pot_koperasi',render:redNegative},
      {data:'total_potongan',render:redNegative},
      {data:'net_received'}
    ]);
  }
  /* detail (per-karyawan) */
  return [
    {data:'nip'},{data:'nama'},{data:'keterangan'},{data:'gaji_pokok'},{data:'idx_amount'}
  ].concat(fixedEarn,dynGroups,dynEarn,fixedDed,dynDed).concat([
    {data:'pembulatan'},
    {data:'total_pendapatan'},
    {data:'max_pot_kop'},
    {data:'pot_koperasi',render:redNegative},
    {data:'total_potongan',render:redNegative},
    {data:'net_received'}
  ]);
}

function initTbl(sel,role){
  $(sel).DataTable({
    processing:true,serverSide:true,
    ajax:{url:baseUrl+'&category='+role,type:'POST',data:{csrf_token:csrf}},
    columns:getCols(role),
    order:[[isSemua?1:1,'asc']],
    footerCallback:function(row,data){let api=this.api();let start=isSemua?2:3;
      if(isSemua)$(api.column(1).footer()).html('<span class="text-end fw-bold">JUMLAH:</span>');
      else $(api.column(2).footer()).html('<span class="text-end">Jumlah:</span>');
      api.columns().every(function(i){if(i<start)return;
        let tot=this.data().reduce((a,b)=>a+(parseInt(String(b).replace(/[^0-9\-]/g,''))||0),0);
        $(this.footer()).html('Rp '+tot.toLocaleString('id-ID'));
      });
    },
    dom:'Bfrtip',buttons:['excel','pdf'],
    lengthMenu:[10,25,50,100],
    language:{url:'../assets/js/Indonesian.json'}
  });
}
$(document).ready(function(){
  initTbl('#tblGuru','guru');
  initTbl('#tblKaryawan','karyawan');
  initTbl('#tblManajer','manajer');
});
</script>
</body>
</html>
