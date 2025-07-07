<?php
// File: /payroll_absensi_v2/guru/hasil-slip_gaji.php

$pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

require_once __DIR__ . '/../helpers.php';
start_session_safe();
generate_csrf_token();

if (!($_SESSION['non_admin_mode'] ?? false)) {
    authorize(['P', 'TK']);
}
require_once __DIR__ . '/../koneksi.php';

$nip        = $_SESSION['nip']      ?? '';
$id_anggota = $_SESSION['id']       ?? '';
if (!$nip || !$id_anggota) {
    die("NIP atau ID anggota tidak ditemukan dalam session.");
}

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    $case = sanitize_input($_POST['case'] ?? '');
    switch ($case) {
        case 'LoadingPayrollHistory':
            LoadingPayrollHistory($conn, $id_anggota);
            break;
        case 'ViewPayrollDetail':
            ViewPayrollDetail($conn);
            break;
        default:
            send_response(404, 'Kasus tidak ditemukan.');
    }
    exit;
}

function LoadingPayrollHistory($conn, $id_anggota)
{
    $draw    = intval($_POST['draw']   ?? 0);
    $start   = intval($_POST['start']  ?? 0);
    $length  = intval($_POST['length'] ?? 10);
    $search  = sanitize_input($_POST['search']['value'] ?? '');
    $bulan   = intval($_POST['bulan']  ?? 0);
    $tahun   = intval($_POST['tahun']  ?? 0);

    $baseJoins = "
      FROM payroll_final p
      JOIN anggota_sekolah a ON p.id_anggota = a.id
      LEFT JOIN (
        SELECT id_anggota, SUM(jumlah) AS total_lain_lain
        FROM kenaikan_gaji_tahunan
        WHERE pindah_ke_lain_lain=1
        GROUP BY id_anggota
      ) kg ON p.id_anggota = kg.id_anggota
    ";
    $baseWhere = "WHERE p.id_anggota = ?";
    $params    = [$id_anggota];
    $types     = "i";

    if ($bulan > 0) {
    $baseWhere .= " AND p.bulan = ?";
    $params[] = $bulan; $types .= "i";
}
if ($tahun > 0) {
    $baseWhere .= " AND p.tahun = ?";
    $params[] = $tahun; $types .= "i";
}

    if ($search !== '') {
        $baseWhere .= " AND (
            CAST(p.id AS CHAR) LIKE ? OR
            a.nama LIKE ? OR
            CAST(p.bulan AS CHAR) LIKE ? OR
            CAST(p.tahun AS CHAR) LIKE ?
        )";
        for ($i=0; $i<4; $i++) {
            $params[] = "%{$search}%";
            $types   .= "s";
        }
    }

    // count filtered
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt $baseJoins $baseWhere");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalFiltered = intval($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();

    // total count
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM payroll_final WHERE id_anggota = ?");
    $stmt->bind_param("i", $id_anggota);
    $stmt->execute();
    $recordsTotal = intval($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();

    // Query Payroll + KGT aktif
    $sql = "
      SELECT p.*, a.nama, a.jenjang,
             IFNULL(kg.total_lain_lain,0) AS total_lain_lain,
             (
               SELECT IFNULL(SUM(jumlah),0)
                 FROM kenaikan_gaji_tahunan k
                WHERE k.id_anggota=p.id_anggota
                  AND k.status='aktif'
                  AND k.pindah_ke_lain_lain=0
                  AND p.tgl_payroll BETWEEN k.tanggal_mulai AND k.tanggal_berakhir
             ) AS kgt_aktif
      $baseJoins
      $baseWhere
      ORDER BY p.id DESC
      LIMIT ?, ?
    ";
    $params[] = $start; $params[] = $length;
    $types   .= "ii";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $data = [];
    while ($r = $res->fetch_assoc()) {
        $totalPendapatan = $r['total_pendapatan'] + $r['kgt_aktif'];
        // Gaji bersih = gaji_bersih DB + kgt_aktif - potongan_absensi
        $gajiBersihTampil = $r['gaji_bersih'] + $r['kgt_aktif'] - $r['potongan_absensi'];
        $data[] = [
            'id'                => $r['id'],
            'nama'              => htmlspecialchars($r['nama']),
            'jenjang'           => htmlspecialchars($r['jenjang']),
            'bulan'             => getIndonesianMonthName($r['bulan']),
            'tahun'             => $r['tahun'],
            'gaji_pokok'        => formatNominal($r['gaji_pokok']),
            'salary_index'      => formatNominal($r['salary_index_amount']),
            'honor_jam_lebih'   => formatNominal($r['honor_jam_lebih']),
            'potongan_absensi'  => formatNominal($r['potongan_absensi']),
            'total_pendapatan'  => formatNominal($totalPendapatan),
            'total_lain_lain'   => formatNominal($r['total_lain_lain']),
            'potongan_koperasi' => formatNominal($r['potongan_koperasi']),
            'total_potongan'    => formatNominal($r['total_potongan']),
            'gaji_bersih'       => formatNominal($gajiBersihTampil),
            'aksi'              => '
                <div class="dropdown">
                    <button class="btn" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="payroll-details.php?id='.$r['id'].'">
                            <i class="fas fa-file-invoice"></i> Lihat Slip Gaji
                        </a></li>
                        <li><a class="dropdown-item btn-view-full-detail" href="#" data-id="'.$r['id'].'">
                            <i class="fas fa-eye"></i> Detail
                        </a></li>
                    </ul>
                </div>'
        ];
    }
    $stmt->close();

    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => $recordsTotal,
        'recordsFiltered' => $totalFiltered,
        'data'            => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function ViewPayrollDetail($conn)
{
    $id = intval($_POST['id_payroll'] ?? 0);
    if ($id <= 0) {
        send_response(1,'Slip Gaji tidak valid.');
    }

    $stmt = $conn->prepare("
        SELECT p.*, a.uid, a.nip, a.nama, a.jenjang, a.role, a.job_title, a.status_kerja,
               a.masa_kerja_tahun, a.masa_kerja_bulan, a.no_rekening, a.email, a.jenis_kelamin, a.agama
          FROM payroll_final p
          JOIN anggota_sekolah a ON p.id_anggota = a.id
         WHERE p.id = ? LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$row) send_response(1,'Slip Gaji tidak ditemukan.');

    // --- KGT aktif
    $stmtK = $conn->prepare("
        SELECT nama_kenaikan, jumlah
          FROM kenaikan_gaji_tahunan
         WHERE id_anggota = ? AND status = 'aktif' AND pindah_ke_lain_lain = 0
           AND ? BETWEEN tanggal_mulai AND tanggal_berakhir
         LIMIT 1
    ");
    $stmtK->bind_param("is", $row['id_anggota'], $row['tgl_payroll']);
    $stmtK->execute();
    $incName = ''; $incAmt = 0;
    if ($k = $stmtK->get_result()->fetch_assoc()) {
        $incName = $k['nama_kenaikan'] ?: 'Kenaikan Gaji Tahunan';
        $incAmt = floatval($k['jumlah']);
    }
    $stmtK->close();

    // --- Detail payhead
    $stmtPD = $conn->prepare("
        SELECT id_payhead, nama_payhead, jenis, amount
          FROM payroll_detail_final
         WHERE id_payroll_final = ?
         ORDER BY id
    "); $stmtPD->bind_param("i", $id); $stmtPD->execute();
    $dets = []; $hasKGT = false;
    $resPD = $stmtPD->get_result();
    while ($d = $resPD->fetch_assoc()) {
        if (stripos($d['nama_payhead'], 'kenaikan gaji') !== false) $hasKGT = true;
        $dets[] = $d;
    }
    $stmtPD->close();

    if ($incAmt > 0 && !$hasKGT) {
        $dets[] = [
            'id_payhead' => null,
            'nama_payhead' => $incName,
            'jenis' => 'earnings',
            'amount' => $incAmt
        ];
    }

    $total_pendapatan    = floatval($row['total_pendapatan']) + $incAmt;
    $total_potongan      = floatval($row['total_potongan']);
    $potongan_koperasi   = floatval($row['potongan_koperasi']);
    $potongan_absensi    = floatval($row['potongan_absensi']);
    $gaji_pokok          = floatval($row['gaji_pokok']);
    $salary_index_amount = floatval($row['salary_index_amount']);
    $honor_jam_lebih     = floatval($row['honor_jam_lebih']);
    $total_lain_lain     = floatval($row['total_lain_lain'] ?? 0);

    // Kalkulasi ulang gaji bersih:
    $gaji_bersih = $gaji_pokok
                 + $salary_index_amount
                 + $honor_jam_lebih
                 + $total_pendapatan
                 + $total_lain_lain
                 - $total_potongan
                 - $potongan_koperasi
                 - $potongan_absensi;

    // Format kolom angka
    $numCols = ['gaji_pokok', 'salary_index_amount', 'honor_jam_lebih',
        'potongan_absensi', 'potongan_koperasi', 'total_potongan'];
    foreach ($numCols as $c) $row[$c] = formatNominal($row[$c]);
    $row['total_pendapatan'] = formatNominal($total_pendapatan);
    $row['gaji_bersih']      = formatNominal($gaji_bersih);
    $row['bulan'] = getIndonesianMonthName((int)$row['bulan']);
    $mk = [];
    if ($row['masa_kerja_tahun']>0) $mk[] = $row['masa_kerja_tahun'].' Thn';
    if ($row['masa_kerja_bulan']>0) $mk[] = $row['masa_kerja_bulan'].' Bln';
    $row['masa_kerja'] = $mk ? implode(' ',$mk) : '-';
    $row['total_lain_lain'] = formatNominal($row['total_lain_lain'] ?? 0);
    $row['payheads_detail'] = $dets;

    send_response(0, $row);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>History Slip Gaji – <?= getIndonesianMonthName(date('n')) . ' ' . date('Y') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- CSS & LIBS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    .card-header { background: #f8f9fa; color: #000; border-bottom: 2px solid #ddd; }
    .card-header h3 { margin: 0; font-weight: 600; color: #000; }
    thead th { background:#343a40; color:#fff; text-align:center; vertical-align:middle; }
    #payrollTable th, #payrollTable td { font-size:14px; vertical-align:middle; }
    #detailPayrollContent, #detailPayrollContent th, #detailPayrollContent td {
      color: #000 !important;
    }
    #loadingSpinner { display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); z-index:9999; }
  </style>
</head>
<body id="page-top">
  <div id="wrapper">
    <?php include __DIR__ . '/../sidebar.php'; ?>
    <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
        <?php include __DIR__ . '/../navbar.php'; ?>
        <?php include __DIR__ . '/../breadcrumb.php'; ?>
        <div class="container-fluid py-4">

          <div class="card mb-4 shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h3><i class="fas fa-history me-2"></i> History Slip Gaji – <span id="hdrPeriod"><?= getIndonesianMonthName(date('n')) . ' ' . date('Y') ?></span></h3>
              <button id="btnShowAllData" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-list"></i> Melihat Semua Data
              </button>
            </div>
            <div class="card-body">

              <form id="filterPayrollForm" class="row gy-2 gx-3 align-items-center mb-3">
                <div class="col-auto">
                  <label class="form-label mb-0"><strong>Bulan:</strong></label>
                  <select id="filterBulan" name="bulan" class="form-select">
                    <option value="">Semua</option>
                    <?php for($m=1;$m<=12;$m++): ?>
                      <option value="<?=$m?>">
  <?= getIndonesianMonthName($m) ?>
</option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="col-auto">
                  <label class="form-label mb-0"><strong>Tahun:</strong></label>
                  <select id="filterTahun" name="tahun" class="form-select">
                    <option value="">Semua</option>
                    <?php
                      $stmtY = $conn->prepare("SELECT DISTINCT tahun FROM payroll_final WHERE id_anggota=? ORDER BY tahun DESC");
                      $stmtY->bind_param("i",$id_anggota);
                      $stmtY->execute();
                      $resY = $stmtY->get_result();
                      while($y = $resY->fetch_assoc()):
                    ?>
                      <option value="<?=$y['tahun']?>">
  <?=$y['tahun']?>
</option>

                    <?php endwhile; $stmtY->close(); ?>
                  </select>
                </div>
                <div class="col-auto d-flex align-items-end">
                  <button type="button" id="btnApplyFilterPayroll" class="btn btn-primary me-2">
                    <i class="fas fa-filter"></i> Terapkan
                  </button>
                  <button type="button" id="btnResetFilterPayroll" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                  </button>
                </div>
              </form>

              <!-- tabel -->
              <div class="table-responsive">
                <table id="payrollTable" class="table table-sm table-bordered table-striped display nowrap" style="width:100%">
                  <thead>
                    <tr>
                      <th>ID Payroll</th>
                      <th>Nama</th>
                      <th>Jenjang</th>
                      <th>Bulan</th>
                      <th>Tahun</th>
                      <th>Gaji Pokok</th>
                      <th>Salary Indeks</th>
                      <th>Honor Jam Lebih</th>
                      <th>Potongan Absensi</th>
                      <th>Total Pendapatan</th>
                      <th>Lain-lain</th>
                      <th>Potongan Koperasi</th>
                      <th>Total Potongan</th>
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
      </div>
    </div>
  </div>
  <!-- spinner -->
  <div id="loadingSpinner">
    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
  </div>

  <!-- modal detail -->
  <div class="modal fade" id="detailPayrollModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Detail Slip Gaji</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="detailPayrollContent">
          <p>Memuat detail...</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times-circle"></i> Tutup
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- JS -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
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
  $(function(){
    const tbl = $('#payrollTable').DataTable({
      processing: true,
      serverSide: true,
      responsive: true,
      ajax: {
        url: 'hasil-slip_gaji.php?ajax=1',
        type: 'POST',
        data: d => {
          d.case  = 'LoadingPayrollHistory';
          d.bulan = $('#filterBulan').val();
          d.tahun = $('#filterTahun').val();
        },
        beforeSend: () => $('#loadingSpinner').show(),
        complete:   () => $('#loadingSpinner').hide(),
        error: () => Swal.fire('Error','Gagal memuat data','error')
      },
      columns: [
        { data:'id' },
        { data:'nama' },
        { data:'jenjang' },
        { data:'bulan' },
        { data:'tahun' },
        { data:'gaji_pokok' },
        { data:'salary_index' },
        { data:'honor_jam_lebih' },
        { data:'potongan_absensi' },
        { data:'total_pendapatan' },
        { data:'total_lain_lain' },
        { data:'potongan_koperasi' },
        { data:'total_potongan' },
        { data:'gaji_bersih' },
        { data:'aksi', orderable:false, searchable:false }
      ],
      order: [[0,'desc']],
      dom: 'Bfrtip',
      buttons: [
        { extend:'excelHtml5', className:'btn btn-success btn-sm', text:'<i class="fas fa-file-excel"></i> Excel',
          exportOptions:{ columns:[0,1,2,3,4,5,6,7,8,9,10,11,12,13] } },
        { extend:'pdfHtml5', className:'btn btn-danger btn-sm', text:'<i class="fas fa-file-pdf"></i> PDF',
          exportOptions:{ columns:[0,1,2,3,4,5,6,7,8,9,10,11,12,13] },
          customize: doc=>{
            doc.styles.tableHeader.fillColor = '#343a40';
            doc.styles.tableHeader.color     = 'white';
            doc.defaultStyle.fontSize        = 10;
          }
        },
        { extend:'print', className:'btn btn-info btn-sm', text:'<i class="fas fa-print"></i> Print',
          exportOptions:{ columns:[0,1,2,3,4,5,6,7,8,9,10,11,12,13] } }
      ]
    });

    function updateHeader(){
      let b=$('#filterBulan').val(), y=$('#filterTahun').val(),
          txt='Semua Periode';
      if(b&&y){
        const nama=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        txt=`${nama[b]} ${y}`;
      }
      $('#hdrPeriod').text(txt);
    }
    updateHeader();

    $('#btnApplyFilterPayroll').click(()=>{
      updateHeader(); tbl.ajax.reload();
    });
    $('#btnResetFilterPayroll, #btnShowAllData').click(()=>{
      $('#filterPayrollForm')[0].reset();
      updateHeader(); tbl.ajax.reload();
    });

    $(document).on('click','.btn-view-full-detail',function(){
      const id=$(this).data('id');
      if(!id) return;
      $.post('hasil-slip_gaji.php?ajax=1',{ case:'ViewPayrollDetail', id_payroll:id },resp=>{
        if(resp.code!==0){
          $('#detailPayrollContent').html(`<p>${resp.result}</p>`);
        } else {
          const d=resp.result;
          let html=`<table class="table table-bordered">`;
          html+=`<tr><th>UID</th><td>${d.uid||'-'}</td></tr>`;
          html+=`<tr><th>NIP</th><td>${d.nip||'-'}</td></tr>`;
          html+=`<tr><th>Nama</th><td>${d.nama}</td></tr>`;
          html+=`<tr><th>Jenjang</th><td>${d.jenjang}</td></tr>`;
          html+=`<tr><th>Role</th><td>${d.role||'-'}</td></tr>`;
          html+=`<tr><th>Job Title</th><td>${d.job_title||'-'}</td></tr>`;
          html+=`<tr><th>Status Kerja</th><td>${d.status_kerja||'-'}</td></tr>`;
          html+=`<tr><th>Masa Kerja</th><td>${d.masa_kerja}</td></tr>`;
          html+=`<tr><th>No Rekening</th><td>${d.no_rekening||'-'}</td></tr>`;
          html+=`<tr><th>Email</th><td>${d.email||'-'}</td></tr>`;

          // --- Pendapatan ---
          html+=`<tr><th colspan="2" class="table-secondary">Pendapatan</th></tr>`;
          html+=`<tr><th>Gaji Pokok</th><td>${d.gaji_pokok}</td></tr>`;
          html+=`<tr><th>Salary Indeks</th><td>${d.salary_index_amount}</td></tr>`;
          html+=`<tr><th>Honor Jam Lebih</th><td>${d.honor_jam_lebih}</td></tr>`;
          html+=`<tr><th>Total Pendapatan</th><td>${d.total_pendapatan}`;
          d.payheads_detail.filter(x=>x.jenis==='earnings').forEach(x=>{
            let badgeClass = 'bg-success';
            if(/kenaikan gaji/i.test(x.nama_payhead)) badgeClass='bg-primary';
            html+=`<div><span class="badge ${badgeClass} text-black">${x.nama_payhead}</span> Rp ${parseFloat(x.amount).toLocaleString('id-ID',{minimumFractionDigits:2})}</div>`;
          });
          html+=`</td></tr>`;
          html+=`<tr><th>Lain-lain</th><td>${d.total_lain_lain}</td></tr>`;

          // --- Potongan ---
          html+=`<tr><th colspan="2" class="table-secondary">Potongan</th></tr>`;
          html+=`<tr><th>Potongan Absensi</th><td>${d.potongan_absensi}</td></tr>`;
          html+=`<tr><th>Potongan Koperasi</th><td>${d.potongan_koperasi}</td></tr>`;
          html+=`<tr><th>Total Potongan</th><td>${d.total_potongan}`;
          d.payheads_detail.filter(x=>x.jenis==='deductions').forEach(x=>{
            let badgeClass = 'bg-danger';
            if(/koperasi/i.test(x.nama_payhead)) badgeClass='bg-warning text-black';
            else if(/absensi/i.test(x.nama_payhead)) badgeClass='bg-danger text-white';
            html+=`<div><span class="badge ${badgeClass}">${x.nama_payhead}</span> Rp ${parseFloat(x.amount).toLocaleString('id-ID',{minimumFractionDigits:2})}</div>`;
          });
          html+=`</td></tr>`;

          // --- Gaji Bersih dan Info Lain ---
          html+=`<tr><th>Gaji Bersih</th><td><b>${d.gaji_bersih}</b></td></tr>`;
          html+=`<tr><th>Bulan</th><td>${d.bulan}</td></tr>`;
          html+=`<tr><th>Tahun</th><td>${d.tahun}</td></tr>`;
          html+=`</table>`;
          $('#detailPayrollContent').html(html);
        }
      },'json');
      new bootstrap.Modal($('#detailPayrollModal')).show();
    });

  });
  </script>
</body>
</html>
