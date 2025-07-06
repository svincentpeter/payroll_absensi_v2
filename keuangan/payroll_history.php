<?php
// File: /payroll_absensi_v2/keuangan/payroll_history.php
$pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:Keuangan']);
require_once __DIR__ . '/../koneksi.php';

$jenjangList = getOrderedJenjang($conn);
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];
$nonce = '';

/* ───────── Mapping jenjang ──────── */
$jenjangMap = [];
$res = $conn->query("SELECT kode_jenjang,nama_jenjang FROM jenjang_sekolah");
while ($row = $res->fetch_assoc()) $jenjangMap[$row['kode_jenjang']]=$row['nama_jenjang'];
if (ob_get_length()) ob_end_clean();

/* ───────── AJAX ──────── */
if (isset($_GET['ajax']) && $_GET['ajax']=='1') {
    if ($_SERVER['REQUEST_METHOD']!=='POST') send_response(405,'Metode tidak diizinkan');
    switch (sanitize_input($_POST['case']??'')) {
        case 'LoadingPayrollHistory': LoadingPayrollHistory($conn,$jenjangMap); break;
        case 'ViewPayrollDetail'   : ViewPayrollDetail   ($conn,$jenjangMap); break;
        default: send_response(404,'Kasus tidak ditemukan.');
    }
    exit();
}

/* ════════════════════════════════════════════════
 *  LoadingPayrollHistory
 * ════════════════════════════════════════════════ */
function LoadingPayrollHistory(mysqli $conn,array $jenjangMap){
    $draw   = intval($_POST['draw']  ??0);
    $start  = intval($_POST['start'] ??0);
    $length = intval($_POST['length']??10);
    $search = sanitize_input($_POST['search']['value']??'');
    $jenjang= sanitize_input($_POST['jenjang']??'');
    $bulan  = intval($_POST['bulan'] ??0);
    $tahun  = intval($_POST['tahun'] ??0);

    /* join + where dinamis */
    $baseJoins = "
      FROM payroll_final p
      JOIN anggota_sekolah a ON a.id=p.id_anggota
      LEFT JOIN (
        SELECT id_anggota,SUM(jumlah) total_lain_lain
          FROM kenaikan_gaji_tahunan
         WHERE pindah_ke_lain_lain=1
         GROUP BY id_anggota
      ) kg ON kg.id_anggota=p.id_anggota
    ";
    $baseWhere = "WHERE 1=1";
    $params=[]; $types='';

    if ($jenjang!==''){ $baseWhere.=" AND a.jenjang=?"; $params[]=$jenjang; $types.='s';}
    if ($bulan>0)     { $baseWhere.=" AND p.bulan=?";   $params[]=$bulan ; $types.='i';}
    if ($tahun>0)     { $baseWhere.=" AND p.tahun=?";   $params[]=$tahun ; $types.='i';}
    if ($search!==''){
        $baseWhere.=" AND (CAST(p.id AS CHAR) LIKE ? OR a.nama LIKE ? OR CAST(p.bulan AS CHAR) LIKE ? OR CAST(p.tahun AS CHAR) LIKE ?)";
        for($i=0;$i<4;$i++){ $params[]="%$search%"; $types.='s'; }
    }

    /* hitung total filter */
    $stmtF=$conn->prepare("SELECT COUNT(*) total $baseJoins $baseWhere");
    if($types) $stmtF->bind_param($types,...$params);
    $stmtF->execute();
    $totalFiltered=intval($stmtF->get_result()->fetch_assoc()['total']??0);
    $stmtF->close();

    $recordsTotal=intval($conn->query("SELECT COUNT(*) total FROM payroll_final")->fetch_assoc()['total']??0);

    /* ambil data + hitung kgt aktif */
    $sql="
      SELECT p.*,a.nama,a.jenjang,
             IFNULL(kg.total_lain_lain,0) total_lain_lain,
             /* ambil KGT aktf yang masih berlaku */
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
      LIMIT ?,?
    ";
    $params[]=$start; $params[]=$length; $types.='ii';

    $stmt=$conn->prepare($sql);
    $stmt->bind_param($types,...$params);
    $stmt->execute(); $res=$stmt->get_result();

    $data=[];
    while($r=$res->fetch_assoc()){
        $totalPendapatan = $r['total_pendapatan'] + $r['kgt_aktif'];
        /* gaji bersih tampilan = gaji_bersih DB + kgt_aktif */
        $gajiBersihTampil = $r['gaji_bersih']
                  + $r['kgt_aktif']
                  - $r['potongan_absensi'];

        $data[]=[
            'id'               =>$r['id'],
            'nama'             =>htmlspecialchars($r['nama']),
            'jenjang'          =>htmlspecialchars($jenjangMap[$r['jenjang']]??$r['jenjang']),
            'bulan'            =>getIndonesianMonthName($r['bulan']),
            'tahun'            =>$r['tahun'],
            'gaji_pokok'       =>formatNominal($r['gaji_pokok']),
            'salary_index'     =>formatNominal($r['salary_index_amount']),
            'honor_jam_lebih'  =>formatNominal($r['honor_jam_lebih']),
            'potongan_absensi' =>formatNominal($r['potongan_absensi']),
            'total_pendapatan' =>formatNominal($totalPendapatan),
            'total_lain_lain'  =>formatNominal($r['total_lain_lain']),
            'potongan_koperasi'=>formatNominal($r['potongan_koperasi']),
            'total_potongan'   =>formatNominal($r['total_potongan']),
            'gaji_bersih'      =>formatNominal($gajiBersihTampil),
            'aksi'=>'
              <div class="dropdown">
                <button class="btn" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                <ul class="dropdown-menu">
                  <li><a class="dropdown-item" href="payroll-details.php?id_payroll='.$r['id'].'">
                        <i class="fas fa-file-invoice"></i> Lihat Payroll
                      </a></li>
                  <li><a class="dropdown-item btn-view-full-detail" href="#" data-id="'.$r['id'].'">
                        <i class="fas fa-eye"></i> View Detail
                      </a></li>
                </ul>
              </div>'
        ];
    }
    $stmt->close();

    echo json_encode([
        'draw'=>$draw,
        'recordsTotal'=>$recordsTotal,
        'recordsFiltered'=>$totalFiltered,
        'data'=>$data
    ],JSON_UNESCAPED_UNICODE);
    exit();
}

/* ════════════════════════════════════════════════
 *  ViewPayrollDetail
 * ════════════════════════════════════════════════ */
function ViewPayrollDetail(mysqli $conn, array $jenjangMap) {
    $id = intval($_POST['id_payroll'] ?? 0);
    if ($id <= 0) send_response(1, 'ID Payroll Final tidak valid.');

    $stmt = $conn->prepare("
        SELECT p.*, a.uid, a.nip, a.nama, a.jenjang, a.role, a.job_title, a.status_kerja,
               a.masa_kerja_tahun, a.masa_kerja_bulan, a.no_rekening, a.email,
               a.jenis_kelamin, a.agama
          FROM payroll_final p
          JOIN anggota_sekolah a ON a.id = p.id_anggota
         WHERE p.id = ? LIMIT 1
    ");
    $stmt->bind_param("i", $id); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$row) send_response(1, 'Payroll final tidak ditemukan.');

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

$row['total_pendapatan'] = formatNominal($total_pendapatan);
$row['gaji_bersih']      = formatNominal($gaji_bersih);


    // Format kolom angka
    $numCols = ['gaji_pokok', 'salary_index_amount', 'honor_jam_lebih',
        'potongan_absensi', 'potongan_koperasi', 'total_potongan'];
    foreach ($numCols as $c) $row[$c] = formatNominal($row[$c]);
    $row['bulan'] = getIndonesianMonthName((int)$row['bulan']);
    $row['jenjang'] = $jenjangMap[$row['jenjang']] ?? $row['jenjang'];
    $masa = '';
    if ($row['masa_kerja_tahun'] > 0) $masa .= $row['masa_kerja_tahun'] . ' Thn ';
    if ($row['masa_kerja_bulan'] > 0) $masa .= $row['masa_kerja_bulan'] . ' Bln';
    $row['masa_kerja'] = trim($masa) ?: '-';
    $row['total_lain_lain'] = formatNominal($row['total_lain_lain'] ?? 0);
    $row['payheads_detail'] = $dets;

    send_response(0, $row);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>History Payroll - Payroll Management System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .page-title { font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 2.5rem; color: #0d47a1; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 0.5rem; border-bottom: 3px solid #1976d2; padding-bottom: 0.3rem; margin-bottom: 1.5rem; }
        .page-title i { color: #1976d2; font-size: 2.8rem; }
        .card-header { background: linear-gradient(45deg, #0d47a1, #42a5f5); color: white; }
        thead th { background-color: #343a40; color: #fff; text-align: center; vertical-align: middle; white-space: nowrap; }
        #payrollTable th, #payrollTable td { font-size: 14px; vertical-align: middle; white-space: nowrap; }
        .table-hover tbody tr:hover { background-color: #e2e6ea; }
        .form-select { min-width: 160px; }
        #loadingSpinner { display: none; position: fixed; top: 50%; left: 50%; z-index: 9999; }
        @media (max-width: 768px) { .row .col-auto { width: 100%; margin-bottom: 10px; } }
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
                <h1 class="page-title">
                    <i class="fas fa-history"></i> History Payroll
                </h1>
                <!-- Filter Section -->
                <div class="card mb-4 shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-white"><i class="fas fa-search"></i> Filter Payroll History</h6>
                    </div>
                    <div class="card-body" style="background-color: #f8f9fa;">
                        <form id="filterPayrollForm" class="row gy-2 gx-3 align-items-center">
                            <div class="col-auto">
                                <label for="filterJenjang" class="form-label mb-0"><strong>Jenjang Pendidikan:</strong></label>
                                <select class="form-control" id="filterJenjang" name="jenjang">
                                    <option value="">Semua Jenjang</option>
                                    <?php foreach ($jenjangList as $kode_jenjang => $nama_jenjang) {
                                        echo '<option value="' . htmlspecialchars($kode_jenjang) . '">' . htmlspecialchars($nama_jenjang) . '</option>';
                                    } ?>
                                </select>
                            </div>
                            <div class="col-auto">
                                <label for="filterBulan" class="form-label mb-0"><strong>Bulan:</strong></label>
                                <select class="form-select" id="filterBulan" name="bulan">
                                    <option value="">Semua Bulan</option>
                                    <?php for ($m = 1; $m <= 12; $m++) {
                                        echo '<option value="' . $m . '">' . getIndonesianMonthName($m) . '</option>';
                                    } ?>
                                </select>
                            </div>
                            <div class="col-auto">
                                <label for="filterTahun" class="form-label mb-0"><strong>Tahun:</strong></label>
                                <select class="form-select" id="filterTahun" name="tahun">
                                    <option value="">Semua Tahun</option>
                                    <?php $stmtTahun = $conn->prepare("SELECT DISTINCT tahun FROM payroll_final ORDER BY tahun DESC");
                                    if ($stmtTahun) { $stmtTahun->execute();
                                        $resTahun = $stmtTahun->get_result();
                                        while ($row = $resTahun->fetch_assoc()) {
                                            echo '<option value="' . htmlspecialchars($row['tahun']) . '">' . htmlspecialchars($row['tahun']) . '</option>';
                                        }
                                        $stmtTahun->close();
                                    } ?>
                                </select>
                            </div>
                            <div class="col-auto d-flex align-items-end">
                                <button type="button" class="btn btn-primary me-2" id="btnApplyFilterPayroll">
                                    <i class="fas fa-filter"></i> Terapkan Filter
                                </button>
                                <button type="button" class="btn btn-secondary" id="btnResetFilterPayroll">
                                    <i class="fas fa-undo"></i> Reset Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- End Filter Section -->

                <!-- Tabel History Payroll -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-white"><i class="fas fa-clipboard-list"></i> Daftar History Payroll</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="payrollTable" class="table table-sm table-bordered table-striped table-hover display nowrap" style="width:100%">
                                <thead>
                                <tr>
                                    <th>ID Payroll</th>
                                    <th>Nama Karyawan</th>
                                    <th>Jenjang</th>
                                    <th>Bulan</th>
                                    <th>Tahun</th>
                                    <th>Gaji Pokok</th>
                                    <th>Salary Indeks</th>
                                    <th>Honor Jam Lebih</th>
                                    <th>Potongan Absensi</th>
                                    <th>Total Pendapatan</th>
                                    <th>Lain‑lain</th>
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
            <!-- End Page Content -->
            <div id="loadingSpinner">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Modal: Detail Payroll -->
<div class="modal fade" id="detailPayrollModal" tabindex="-1" aria-labelledby="detailPayrollModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailPayrollModalLabel">Detail Payroll</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body" id="detailPayrollContent">
                <p>Memuat detail payroll...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times-circle"></i> Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JS Dependencies -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>
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
<script src="https://cdn.jsdelivr.net/npm/autonumeric@4.6.0/dist/autoNumeric.min.js"></script>
<script>
$(document).ready(function() {
    var payrollTable = $('#payrollTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "payroll_history.php?ajax=1",
            type: "POST",
            data: function(d) {
                d.case = 'LoadingPayrollHistory';
                d.jenjang = $('#filterJenjang').val();
                d.bulan = $('#filterBulan').val();
                d.tahun = $('#filterTahun').val();
            },
            beforeSend: function() { $('#loadingSpinner').show(); },
            complete: function() { $('#loadingSpinner').hide(); },
            error: function() {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan saat memuat data payroll.' });
            }
        },
        columns: [
            { data:'id' }, { data:'nama' }, { data:'jenjang' }, { data:'bulan' }, { data:'tahun' },
            { data:'gaji_pokok' }, { data:'salary_index' }, { data:'honor_jam_lebih' }, { data:'potongan_absensi' },
            { data:'total_pendapatan' }, { data:'total_lain_lain' }, { data:'potongan_koperasi' },
            { data:'total_potongan' }, { data:'gaji_bersih' }, { data:'aksi', orderable:false, searchable:false }
        ],
        order: [ [0, 'desc'] ],
        language: { url: "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json" },
        dom: 'Bfrtip',
        buttons: [
            { extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Export Excel', className: 'btn btn-success btn-sm', exportOptions: { columns: ':visible' } },
            { extend: 'pdfHtml5', text: '<i class="fas fa-file-pdf"></i> Export PDF', className: 'btn btn-danger btn-sm', exportOptions: { columns: ':visible' }, customize: function(doc) { doc.styles.tableHeader.fillColor = '#343a40'; doc.styles.tableHeader.color = 'white'; doc.defaultStyle.fontSize = 10; } },
            { extend: 'print', text: '<i class="fas fa-print"></i> Print', className: 'btn btn-info btn-sm', exportOptions: { columns: ':visible' } }
        ],
        responsive: true,
        autoWidth: false
    });

    $('#btnApplyFilterPayroll').on('click', function() { payrollTable.ajax.reload(); });
    $('#btnResetFilterPayroll').on('click', function() { $('#filterPayrollForm')[0].reset(); payrollTable.ajax.reload(); });

    $(document).on('click', '.btn-view-full-detail', function() {
        var idPayroll = $(this).data('id');
        if (idPayroll) {
            $.ajax({
                url: "payroll_history.php?ajax=1",
                type: "POST",
                dataType: "json",
                data: { case: 'ViewPayrollDetail', id_payroll: idPayroll },
                beforeSend: function() {
                    $('#detailPayrollContent').html('<p>Memuat detail payroll...</p>');
                    var detailModal = new bootstrap.Modal(document.getElementById('detailPayrollModal'));
                    detailModal.show();
                },
                success: function(response) {
                    if (response.code === 0) {
                        var d = response.result;
                        var html = '<table class="table table-bordered">';
                        html += '<tr><th>ID Payroll</th><td>' + d.id + '</td></tr>';
                        html += '<tr><th>UID</th><td>'    + (d.uid  || '-') + '</td></tr>';
                        html += '<tr><th>NIP</th><td>'    + (d.nip  || '-') + '</td></tr>';
                        html += '<tr><th>Nama</th><td>'   + d.nama + '</td></tr>';
                        html += '<tr><th>Jenjang</th><td>'+ d.jenjang + '</td></tr>';
                        html += '<tr><th>Role</th><td>'   + (d.role || '-') + '</td></tr>';
                        html += '<tr><th>Job Title</th><td>'+ (d.job_title||'-') + '</td></tr>';
                        html += '<tr><th>Status Kerja</th><td>'+ (d.status_kerja||'-') + '</td></tr>';
                        html += '<tr><th>Masa Kerja</th><td>'+ d.masa_kerja + '</td></tr>';
                        html += '<tr><th>No Rekening</th><td>'+ (d.no_rekening||'-') + '</td></tr>';
                        html += '<tr><th>Email</th><td>'  + (d.email||'-') + '</td></tr>';
                        html += '<tr><th>Jenis Kelamin</th><td>'+ (d.jenis_kelamin||'-') + '</td></tr>';
                        html += '<tr><th>Agama</th><td>'  + (d.agama||'-') + '</td></tr>';
                        html += '<tr><th>Gaji Pokok</th><td>' + d.gaji_pokok + '</td></tr>';
                        html += '<tr><th>Salary Indeks</th><td>' + d.salary_index_amount + '</td></tr>';
                        html += '<tr><th>Honor Jam Lebih</th><td>' + d.honor_jam_lebih + '</td></tr>';
                        html += '<tr><th>Potongan Absensi</th><td>' + d.potongan_absensi + '</td></tr>';
                        html += '<tr><th>Total Pendapatan</th><td>' + d.total_pendapatan;
                        if (d.payheads_detail) {
                            d.payheads_detail.filter(ph => ph.jenis==='earnings').forEach(ph => {
                                var nom = parseFloat(ph.amount).toLocaleString('id-ID',{minimumFractionDigits:2});
                                html += '<div><span class="badge bg-success me-2 text-black">'+ph.nama_payhead+'</span> Rp '+nom+'</div>';
                            });
                        }
                        html += '</td></tr>';
                        html += '<tr><th>Lain‑lain</th><td>' + d.total_lain_lain + '</td></tr>';
                        html += '<tr><th>Potongan Koperasi</th><td>' + d.potongan_koperasi + '</td></tr>';
                        html += '<tr><th>Total Potongan</th><td>' + d.total_potongan;
                        if (d.payheads_detail) {
                            d.payheads_detail.filter(ph => ph.jenis==='deductions').forEach(ph => {
                                var nom = parseFloat(ph.amount).toLocaleString('id-ID',{minimumFractionDigits:2});
                                html += '<div><span class="badge bg-danger me-2 text-black">'+ph.nama_payhead+'</span> Rp '+nom+'</div>';
                            });
                        }
                        html += '</td></tr>';
                        html += '<tr><th>Gaji Bersih</th><td>' + d.gaji_bersih + '</td></tr>';
                        html += '<tr><th>Bulan</th><td>'      + d.bulan + '</td></tr>';
                        html += '<tr><th>Tahun</th><td>'      + d.tahun + '</td></tr>';
                        html += '</table>';
                        $('#detailPayrollContent').html(html);
                    } else {
                        $('#detailPayrollContent').html('<p>'+response.result+'</p>');
                    }
                },
                error: function() {
                    $('#detailPayrollContent').html('<p>Terjadi kesalahan saat memuat detail payroll.</p>');
                }
            });
        }
    });
});
</script>
</body>
</html>
<?php $conn->close(); ?>
