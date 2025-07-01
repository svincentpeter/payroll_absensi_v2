<?php
// payroll_absensi_v2/sdm/laporan_pengajuan_ijin.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../helpers.php';
start_session_safe();
init_error_handling();
require_once '../koneksi.php';

authorize(['M:SDM']);
generate_csrf_token();
$CSRF = $_SESSION['csrf_token'];

// AJAX Handler
if (isset($_GET['ajax']) && $_GET['ajax']=='1') {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $case = $_POST['case'] ?? '';
        switch ($case) {
            case 'LoadAktif':
                $search = trim($_POST['search']['value'] ?? '');
                $where = "status_kepalasekolah='Diterima' AND status='Pending'";
                if ($search !== '') {
                    $where .= " AND (nip LIKE '%$search%' OR nama LIKE '%$search%' OR judul_surat LIKE '%$search%' OR tipe_ijin LIKE '%$search%')";
                }
                $rs = $conn->query("SELECT * FROM pengajuan_ijin WHERE $where ORDER BY id DESC");
                $data = [];
                $no = 1;
                while ($r = $rs->fetch_assoc()) {
                    $lampiran = $r['lampiran'] ? '<a href="/payroll_absensi_v2/uploads/surat_ijin/'.htmlspecialchars($r['lampiran']).'" target="_blank" class="btn btn-sm btn-info">Lampiran</a>' : '<em>-</em>';
                    $aksi = '
                        <div class="btn-group">
                          <button class="btn btn-success btn-sm btnTerima" data-id="'.$r['id'].'"><i class="fa fa-check"></i></button>
                          <button class="btn btn-danger btn-sm btnTolak" data-id="'.$r['id'].'"><i class="fa fa-times"></i></button>
                        </div>';
                    $data[] = [
                        $no++,
                        htmlspecialchars($r['nip']),
                        htmlspecialchars($r['nama']),
                        htmlspecialchars($r['judul_surat']),
                        htmlspecialchars($r['tanggal']),
                        htmlspecialchars($r['pesan']),
                        htmlspecialchars($r['tipe_ijin']),
                        $lampiran,
                        '<span class="badge bg-warning text-dark">'.htmlspecialchars($r['status_kepalasekolah']).'</span>',
                        $aksi
                    ];
                }
                echo json_encode([
                    "data"=>$data,
                    "recordsTotal"=>count($data),
                    "recordsFiltered"=>count($data),
                    "draw"=>(int)($_POST['draw']??1)
                ]); exit;
            case 'LoadHistory':
                $search = trim($_POST['search']['value'] ?? '');
                $where = "status<>'Pending'";
                if ($search !== '') {
                    $where .= " AND (nip LIKE '%$search%' OR nama LIKE '%$search%' OR judul_surat LIKE '%$search%' OR tipe_ijin LIKE '%$search%')";
                }
                $rs = $conn->query("SELECT * FROM pengajuan_ijin WHERE $where ORDER BY id DESC");
                $data = [];
                $no = 1;
                while ($r = $rs->fetch_assoc()) {
                    $lampiran = $r['lampiran'] ? '<a href="/payroll_absensi_v2/uploads/surat_ijin/'.htmlspecialchars($r['lampiran']).'" target="_blank" class="btn btn-sm btn-info">Lampiran</a>' : '<em>-</em>';
                    $badgeKS = $r['status_kepalasekolah']=='Diterima' ? 'success':'danger';
                    $badgeSDM = $r['status']=='Diterima' ? 'success' : ($r['status']=='Ditolak'?'danger':'warning text-dark');
                    $aksi = '';
                    if ($r['status']=='Diterima'||$r['status']=='Ditolak') {
                        $aksi = '<button class="btn btn-danger btn-sm btnHapus" data-id="'.$r['id'].'"><i class="fa fa-trash"></i></button>';
                    } else {
                        $aksi = '<button class="btn btn-secondary btn-sm" disabled><i class="fa fa-trash"></i></button>';
                    }
                    $data[] = [
                        $no++,
                        htmlspecialchars($r['nip']),
                        htmlspecialchars($r['nama']),
                        htmlspecialchars($r['judul_surat']),
                        htmlspecialchars($r['tanggal']),
                        htmlspecialchars($r['pesan']),
                        htmlspecialchars($r['tipe_ijin']),
                        $lampiran,
                        '<span class="badge bg-'.$badgeKS.'">'.htmlspecialchars($r['status_kepalasekolah']).'</span>',
                        '<span class="badge bg-'.$badgeSDM.'">'.htmlspecialchars($r['status']).'</span>',
                        $aksi
                    ];
                }
                echo json_encode([
                    "data"=>$data,
                    "recordsTotal"=>count($data),
                    "recordsFiltered"=>count($data),
                    "draw"=>(int)($_POST['draw']??1)
                ]); exit;
            case 'TerimaIjin':
            case 'TolakIjin':
                $id = intval($_POST['id']??0);
                $status = $case=='TerimaIjin'?'Diterima':'Ditolak';
                $stmt = $conn->prepare("UPDATE pengajuan_ijin SET status=? WHERE id=?");
                $stmt->bind_param('si',$status,$id);
                $ok = $stmt->execute(); $stmt->close();
                echo json_encode($ok?['code'=>0,'result'=>'Berhasil update status']:['code'=>1,'result'=>'Gagal update']); exit;
            case 'HapusIjin':
                $id = intval($_POST['id']??0);
                $rs = $conn->query("SELECT status FROM pengajuan_ijin WHERE id=$id");
                $row = $rs?$rs->fetch_assoc():null;
                if ($row && in_array($row['status'],['Diterima','Ditolak'])) {
                    $ok = $conn->query("DELETE FROM pengajuan_ijin WHERE id=$id");
                    echo json_encode($ok?['code'=>0,'result'=>'History dihapus']:['code'=>1,'result'=>'Gagal hapus']); exit;
                }
                echo json_encode(['code'=>2,'result'=>'Tidak bisa hapus status pending']); exit;
            default:
                echo json_encode(['data'=>[]]); exit;
        }
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Pengajuan Izin</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.4.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .page-title {font-family:'Poppins',sans-serif;font-weight:600;font-size:2.5rem;color:#0d47a1;text-shadow:1px 1px 2px rgba(0,0,0,0.1);display:flex;align-items:center;gap:0.5rem;border-bottom:3px solid #1976d2;padding-bottom:0.3rem;margin-bottom:1.5rem;animation:fadeInSlide 0.5s ease-in-out both;}
        .page-title i {color:#1976d2;font-size:2.8rem;}
        .card-header {background:linear-gradient(45deg,#0d47a1,#42a5f5);color:white;}
        .table-hover tbody tr:hover {background:#eef;}
        #loadingSpinner {display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);}
    </style>
</head>
<body id="page-top">
<div id="wrapper">
<?php include __DIR__.'/../sidebar.php'; ?>
<div id="content-wrapper" class="d-flex flex-column">
    <div id="content">
    <?php include __DIR__.'/../navbar.php'; ?>
    <?php include __DIR__.'/../breadcrumb.php'; ?>
    <div class="container-fluid py-4">

    <h1 class="page-title">
        <i class="fas fa-envelope"></i> Laporan Pengajuan Izin (SDM)
    </h1>

    <!-- FILTER -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header"><strong><i class="fas fa-filter me-1"></i>Filter Pengajuan</strong></div>
        <div class="card-body">
            <form id="filterForm" class="row gy-2 gx-3 align-items-end">
                <div class="col-auto">
                    <label class="form-label"><strong>Cari</strong></label>
                    <input type="text" id="filterSearch" class="form-control" placeholder="Nama / NIP / Judul / Tipe">
                </div>
                <div class="col-auto">
                    <button type="button" id="btnApply" class="btn btn-primary"><i class="fas fa-search"></i> Cari</button>
                    <button type="button" id="btnReset" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ALERT PLACEHOLDER -->
    <div id="alert-placeholder"></div>

    <!-- TABEL PENGAJUAN AKTIF -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center">
            <h6 class="m-0 text-white"><i class="fas fa-list"></i> Daftar Pengajuan Izin (Aktif)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
            <table id="tableAktif" class="table table-sm table-bordered table-hover nowrap" style="width:100%">
                <thead>
                <tr>
                    <th>No</th>
                    <th>NIP</th>
                    <th>Nama</th>
                    <th>Judul</th>
                    <th>Tanggal</th>
                    <th>Pesan</th>
                    <th>Tipe</th>
                    <th>Lampiran</th>
                    <th>Status KS</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
            </div>
        </div>
    </div>
    <!-- HISTORY -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center">
            <h6 class="m-0 text-white"><i class="fas fa-clock"></i> History Pengajuan Izin</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
            <table id="tableHistory" class="table table-sm table-bordered table-hover nowrap" style="width:100%">
                <thead>
                <tr>
                    <th>No</th>
                    <th>NIP</th>
                    <th>Nama</th>
                    <th>Judul</th>
                    <th>Tanggal</th>
                    <th>Pesan</th>
                    <th>Tipe</th>
                    <th>Lampiran</th>
                    <th>Status KS</th>
                    <th>Status SDM</th>
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
<div id="loadingSpinner"><div class="spinner-border text-primary"></div></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.0/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function(){
    const CSRF = '<?= $CSRF ?>';
    const Toast = Swal.mixin({toast:true,position:'top-end',timer:3000,showConfirmButton:false});
    function showToast(msg,icon='success'){ Toast.fire({icon,title:msg}); }

    let tableAktif = $('#tableAktif').DataTable({
        processing:true, serverSide:false, responsive:true, lengthChange:false,
        ajax: {
            url:'?ajax=1',type:'POST',data:function(d){
                d.case = 'LoadAktif';
                d.search = {value:$('#filterSearch').val()};
            },
            beforeSend:()=>$('#loadingSpinner').show(),
            complete:()=>$('#loadingSpinner').hide()
        },
        columns:[null,null,null,null,null,null,null,null,null,null],
        language:{url:'//cdn.datatables.net/plug-ins/1.13.4/i18n/id.json'}
    });

    let tableHistory = $('#tableHistory').DataTable({
        processing:true, serverSide:false, responsive:true, lengthChange:false,
        ajax: {
            url:'?ajax=1',type:'POST',data:function(d){
                d.case = 'LoadHistory';
                d.search = {value:$('#filterSearch').val()};
            },
            beforeSend:()=>$('#loadingSpinner').show(),
            complete:()=>$('#loadingSpinner').hide()
        },
        columns:[null,null,null,null,null,null,null,null,null,null,null],
        language:{url:'//cdn.datatables.net/plug-ins/1.13.4/i18n/id.json'}
    });

    $('#btnApply').click(()=>{ tableAktif.ajax.reload();tableHistory.ajax.reload(); });
    $('#btnReset').click(function(){
        $('#filterForm')[0].reset();
        tableAktif.ajax.reload();
        tableHistory.ajax.reload();
    });

    $('#tableAktif').on('click','.btnTerima',function(){
        let id=$(this).data('id');
        $.post('?ajax=1',{case:'TerimaIjin',id:id,csrf:CSRF},function(resp){
            if(resp.code===0){ showToast('Status diperbarui');tableAktif.ajax.reload();tableHistory.ajax.reload();}
            else showToast(resp.result,'error');
        },'json');
    });
    $('#tableAktif').on('click','.btnTolak',function(){
        let id=$(this).data('id');
        $.post('?ajax=1',{case:'TolakIjin',id:id,csrf:CSRF},function(resp){
            if(resp.code===0){ showToast('Status diperbarui');tableAktif.ajax.reload();tableHistory.ajax.reload();}
            else showToast(resp.result,'error');
        },'json');
    });
    $('#tableHistory').on('click','.btnHapus',function(){
        let id=$(this).data('id');
        Swal.fire({title:'Yakin hapus?',icon:'warning',showCancelButton:true,confirmButtonText:'Hapus'}).then(function(x){
            if(x.isConfirmed){
                $.post('?ajax=1',{case:'HapusIjin',id:id,csrf:CSRF},function(resp){
                    if(resp.code===0){ showToast('History dihapus');tableHistory.ajax.reload(); }
                    else showToast(resp.result,'error');
                },'json');
            }
        });
    });
});
</script>
</body>
</html>
