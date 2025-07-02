<?php
// File: /payroll_absensi_v2/keuangan/manage_ranking_kenaikan.php

// =========================
// 1. Pengaturan Awal
// =========================
$pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:Keuangan','M:superadmin']);

require_once __DIR__ . '/../koneksi.php';

// Hapus output buffering jika ada
if (ob_get_length()) {
    ob_end_clean();
}

// =========================
// 2. Menangani Permintaan AJAX
// =========================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    $case = trim($_POST['case'] ?? '');
    switch ($case) {
        case 'LoadingRanking':
            LoadingRanking($conn);
            break;
        case 'AddRanking':
            AddRanking($conn);
            break;
        case 'GetRankingDetail':
            GetRankingDetail($conn);
            break;
        case 'UpdateRanking':
            UpdateRanking($conn);
            break;
        case 'DeleteRanking':
            DeleteRanking($conn);
            break;
        default:
            send_response(404, 'Kasus tidak ditemukan.');
    }
    exit();
}

// =========================
// 3. Fungsi CRUD untuk Ranking Kenaikan
// =========================
function LoadingRanking($conn)
{
    $draw   = intval($_POST['draw'] ?? 0);
    $start  = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $search = trim($_POST['search']['value'] ?? '');

    // total records
    $recordsTotal = qCount($conn, "SELECT COUNT(*) cnt FROM ranking_kenaikan");

    // filter by search
    $sqlFilter = "FROM ranking_kenaikan WHERE 1=1";
    $params    = [];
    $types     = "";
    if ($search !== '') {
        $sqlFilter .= " AND (nama_ranking LIKE ? OR deskripsi LIKE ?)";
        $params[]   = "%$search%";
        $params[]   = "%$search%";
        $types     .= "ss";
    }

    $recordsFiltered = qCount($conn, "SELECT COUNT(*) cnt $sqlFilter", $types, $params);

    // fetch paged data
    $orderBy = " ORDER BY id DESC";
    $sqlData = "SELECT id,nama_ranking,jumlah,deskripsi,is_aktif $sqlFilter $orderBy LIMIT ?, ?";
    $params[] = $start;
    $params[] = $length;
    $types   .= "ii";

    $stmt = $conn->prepare($sqlData);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $data = [];
    $no   = $start + 1;
    while ($r = $res->fetch_assoc()) {
        $badgeAktif = $r['is_aktif'] ? '<span class="badge bg-success">Aktif</span>' 
                                     : '<span class="badge bg-secondary">Non-aktif</span>';
        $aksi = '
<div class="dropdown">
  <button class="btn btn-sm" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
  <ul class="dropdown-menu">
    <li><a class="dropdown-item btn-edit" href="#" data-id="' . $r['id'] . '"><i class="fas fa-pencil-alt"></i> Edit</a></li>
    <li><a class="dropdown-item btn-delete" href="#" data-id="' . $r['id'] . '"><i class="fas fa-trash-alt"></i> Hapus</a></li>
  </ul>
</div>';
        $data[] = [
            "no"           => $no++,
            "nama_ranking" => htmlspecialchars($r['nama_ranking']),
            "jumlah"       => number_format($r['jumlah'],0,',','.'),
            "deskripsi"    => htmlspecialchars($r['deskripsi']),
            "status"       => $badgeAktif,
            "aksi"         => $aksi,
        ];
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

function AddRanking($conn)
{
    $nama      = trim($_POST['nama_ranking']   ?? '');
    $jumlah    = floatval($_POST['jumlah'] ?? 0);
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $isAktif   = isset($_POST['is_aktif']) ? 1 : 0;

    if ($nama === '' || $deskripsi === '') {
        send_response(2, 'Nama dan deskripsi wajib diisi.');
    }
    if ($jumlah <= 0) {
        send_response(3, 'Jumlah kenaikan harus lebih dari 0.');
    }

    // cek duplikasi
    $stmt = $conn->prepare("SELECT id FROM ranking_kenaikan WHERE nama_ranking=? LIMIT 1");
    $stmt->bind_param("s",$nama);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        send_response(4, 'Rank sudah ada.');
    }
    $stmt->close();

    // insert
    $stmt = $conn->prepare("
        INSERT INTO ranking_kenaikan (nama_ranking,jumlah,deskripsi,is_aktif)
        VALUES(?,?,?,?)
    ");
    $stmt->bind_param("sdsi",$nama,$jumlah,$deskripsi,$isAktif);
    if ($stmt->execute()) {
        add_audit_log($conn, $_SESSION['nip']??'', 'AddRanking', 
            "Menambahkan rank '$nama' => $jumlah, aktif=$isAktif"
        );
        send_response(0, 'Rank berhasil ditambahkan.');
    } else {
        send_response(1, 'Gagal menambah rank: '.$stmt->error);
    }
    $stmt->close();
    exit();
}

function GetRankingDetail($conn)
{
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) send_response(1,'ID tidak valid.');

    $stmt = $conn->prepare("SELECT * FROM ranking_kenaikan WHERE id=? LIMIT 1");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        add_audit_log($conn, $_SESSION['nip']??'', 'ViewRanking', "Melihat rank ID $id");
        send_response(0, [
            'id'           => $row['id'],
            'nama_ranking' => $row['nama_ranking'],
            'jumlah'       => $row['jumlah'],
            'deskripsi'    => $row['deskripsi'],
            'is_aktif'     => (int)$row['is_aktif'],
        ]);
    } else {
        send_response(2,'Data tidak ditemukan.');
    }
    $stmt->close();
    exit();
}

function UpdateRanking($conn)
{
    $id        = intval($_POST['edit_id'] ?? 0);
    $nama      = trim($_POST['edit_nama_ranking'] ?? '');
    $jumlah    = floatval($_POST['jumlah'] ?? 0);
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $isAktif   = isset($_POST['is_aktif']) ? 1 : 0;

    if ($id <= 0 || $nama === '' || $deskripsi === '') {
        send_response(2,'Field wajib diisi dan ID valid.');
    }
    if ($jumlah <= 0) {
        send_response(3,'Jumlah kenaikan harus > 0.');
    }

    // cek duplikasi
    $stmt = $conn->prepare("
        SELECT id FROM ranking_kenaikan 
         WHERE nama_ranking=? AND id<>? LIMIT 1
    ");
    $stmt->bind_param("si",$nama,$id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        send_response(4,'Nama rank sudah dipakai.');
    }
    $stmt->close();

    // update
    $stmt = $conn->prepare("
        UPDATE ranking_kenaikan
           SET nama_ranking=?, jumlah=?, deskripsi=?, is_aktif=?
         WHERE id=?
    ");
    $stmt->bind_param("sdssi",$nama,$jumlah,$deskripsi,$isAktif,$id);
    if ($stmt->execute()) {
        add_audit_log($conn, $_SESSION['nip']??'', 'UpdateRanking', 
            "Update rank ID $id => '$nama': $jumlah, aktif=$isAktif"
        );
        send_response(0,'Rank berhasil diupdate.');
    } else {
        send_response(1,'Gagal update: '.$stmt->error);
    }
    $stmt->close();
    exit();
}

function DeleteRanking($conn)
{
    $id = intval($_POST['id'] ?? 0);
    if ($id<=0) send_response(2,'ID tidak valid.');

    // hapus
    $stmt = $conn->prepare("DELETE FROM ranking_kenaikan WHERE id=?");
    $stmt->bind_param("i",$id);
    if ($stmt->execute()) {
        add_audit_log($conn, $_SESSION['nip']??'', 'DeleteRanking', "Hapus rank ID $id");
        send_response(0,'Rank berhasil dihapus.');
    } else {
        send_response(1,'Gagal hapus: '.$stmt->error);
    }
    $stmt->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Manajemen Ranking Kenaikan Gaji</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SB Admin 2 -->
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.1.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <!-- Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
    .page-title {
      font-family:'Poppins',sans-serif; font-weight:600; font-size:2.5rem;
      color:#0d47a1; text-shadow:1px 1px 2px rgba(0,0,0,0.1);
      border-bottom:3px solid #1976d2; padding-bottom:.3rem; margin-bottom:1.5rem;
      display:flex; align-items:center; gap:.5rem;
    }
    .page-title i { color:#1976d2; font-size:2.8rem; }
    #loadingSpinner { display:none; position:fixed; z-index:9999;
      height:100px;width:100px;margin:auto;top:0;left:0;right:0;bottom:0;
    }
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
        <h1 class="page-title"><i class="fas fa-layer-group"></i> Ranking Kenaikan Gaji</h1>

        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center bg-gradient-primary text-white">
            <h6 class="m-0"><i class="fas fa-clipboard-list"></i> Daftar Ranking</h6>
            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="fas fa-plus"></i> Tambah Ranking
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="rankTable" class="table table-striped table-bordered table-hover nowrap" style="width:100%">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Nama</th>
                    <th>Jumlah (Rp)</th>
                    <th>Deskripsi</th>
                    <th>Status</th>
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
    <footer class="sticky-footer bg-white">
      <div class="container text-center">&copy; <?= date('Y') ?> Payroll System</div>
    </footer>
  </div>
</div>

<!-- Modal Add -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="add-form" class="needs-validation" novalidate>
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Tambah Ranking</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="case" value="AddRanking">
          <div class="mb-3">
            <label class="form-label">Nama Ranking <span class="text-danger">*</span></label>
            <input type="text" name="nama_ranking" class="form-control" required>
            <div class="invalid-feedback">Nama ranking wajib diisi.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Jumlah Kenaikan (Rp) <span class="text-danger">*</span></label>
            <input type="text" name="jumlah" id="add_jumlah" class="form-control" required>
            <div class="invalid-feedback">Masukkan jumlah kenaikan.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Deskripsi <span class="text-danger">*</span></label>
            <textarea name="deskripsi" class="form-control" rows="2" required></textarea>
            <div class="invalid-feedback">Deskripsi wajib diisi.</div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="add_aktif" name="is_aktif" checked>
            <label class="form-check-label" for="add_aktif">Aktifkan</label>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Batal</button>
          <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan
            <span class="spinner-border spinner-border-sm d-none"></span>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="edit-form" class="needs-validation" novalidate>
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Ranking</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="case" value="UpdateRanking">
          <input type="hidden" name="edit_id" id="edit_id">
          <div class="mb-3">
            <label class="form-label">Nama Ranking <span class="text-danger">*</span></label>
            <input type="text" name="edit_nama_ranking" id="edit_nama" class="form-control" required>
            <div class="invalid-feedback">Nama ranking wajib diisi.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Jumlah Kenaikan (Rp) <span class="text-danger">*</span></label>
            <input type="text" name="jumlah" id="edit_jumlah" class="form-control" required>
            <div class="invalid-feedback">Masukkan jumlah kenaikan.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Deskripsi <span class="text-danger">*</span></label>
            <textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="2" required></textarea>
            <div class="invalid-feedback">Deskripsi wajib diisi.</div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="edit_aktif" name="is_aktif">
            <label class="form-check-label" for="edit_aktif">Aktifkan</label>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Batal</button>
          <button class="btn btn-primary"><i class="fas fa-save"></i> Update
            <span class="spinner-border spinner-border-sm d-none"></span>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Modal Delete -->
<div class="modal fade" id="delModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="del-form">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-trash-alt"></i> Hapus Ranking</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="del_id" name="id">
          <p>Yakin ingin menghapus ranking ini?</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Batal</button>
          <button class="btn btn-danger"><i class="fas fa-check"></i> Ya, Hapus
            <span class="spinner-border spinner-border-sm d-none"></span>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<div id="loadingSpinner"><div class="spinner-border text-primary"></div></div>

<!-- JS Dependencies -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.1.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/autonumeric@4.6.0/dist/autoNumeric.min.js"></script>

<script>
$(function(){
  const Toast = Swal.mixin({ toast:true, position:'top-end', showConfirmButton:false, timer:3000 });
  function showToast(msg,icon='success'){ Toast.fire({ icon, title:msg }); }

  // AutoNumeric
  new AutoNumeric('#add_jumlah',{ digitGroupSeparator:'.', decimalCharacter:',', decimalPlaces:2, unformatOnSubmit:true });
  new AutoNumeric('#edit_jumlah',{ digitGroupSeparator:'.', decimalCharacter:',', decimalPlaces:2, unformatOnSubmit:true });

  var table = $('#rankTable').DataTable({
    processing:true, serverSide:true,
    ajax:{
      url:'?ajax=1', type:'POST',
      data:d=>{ d.case='LoadingRanking'; d.search.value=$('input[type=search]').val(); },
      beforeSend:()=>$('#loadingSpinner').show(),
      complete:()=>$('#loadingSpinner').hide()
    },
    columns:[
      {data:'no',orderable:false},
      {data:'nama_ranking'},
      {data:'jumlah'},
      {data:'deskripsi'},
      {data:'status',orderable:false},
      {data:'aksi',orderable:false}
    ],
    dom:'Bfrtip',
    buttons:[
      { extend:'excelHtml5', className:'btn btn-success btn-sm', text:'<i class="fas fa-file-excel"></i> Excel', exportOptions:{columns:[0,1,2,3,4]} },
      { extend:'pdfHtml5',   className:'btn btn-danger btn-sm',  text:'<i class="fas fa-file-pdf"></i> PDF',  exportOptions:{columns:[0,1,2,3,4]} },
      { extend:'print',      className:'btn btn-info btn-sm',   text:'<i class="fas fa-print"></i> Print', exportOptions:{columns:[0,1,2,3,4]} }
    ],
    responsive:true, autoWidth:false,
    language:{ url:'//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json' }
  });

  // Bootstrap validation
  (function(){
    'use strict';
    window.addEventListener('load',()=>{
      Array.from(document.getElementsByClassName('needs-validation')).forEach(form=>{
        form.addEventListener('submit',evt=>{
          if(!form.checkValidity()){ evt.preventDefault(); evt.stopPropagation(); }
          form.classList.add('was-validated');
        },false);
      });
    },false);
  })();

  // Tambah
  $('#add-form').on('submit',function(e){
    e.preventDefault();
    if(!this.checkValidity()) return;
    var an = AutoNumeric.getAutoNumericElement('#add_jumlah');
    $(this).find('input[name=jumlah]').val(an.getNumber());
    var btn = $(this).find('button[type=submit]');
    btn.prop('disabled',true).find('.spinner-border').removeClass('d-none');
    $.post('?ajax=1',$(this).serialize(),resp=>{
      btn.prop('disabled',false).find('.spinner-border').addClass('d-none');
      if(resp.code===0){
        showToast(resp.result);
        $('#addModal').modal('hide');
        table.ajax.reload(null,false);
        this.reset(); this.classList.remove('was-validated');
      } else showToast(resp.result,'error');
    },'json').fail(()=>{ btn.prop('disabled',false).find('.spinner-border').addClass('d-none'); showToast('Error server','error'); });
  });

  // Edit
  $(document).on('click','.btn-edit',function(){
    var id=$(this).data('id');
    $('#edit-form')[0].reset(); $('#edit-form').removeClass('was-validated');
    $.post('?ajax=1',{case:'GetRankingDetail',id},resp=>{
      if(resp.code===0){
        $('#edit_id').val(resp.result.id);
        $('#edit_nama').val(resp.result.nama_ranking);
        AutoNumeric.getAutoNumericElement('#edit_jumlah').set(resp.result.jumlah);
        $('#edit_deskripsi').val(resp.result.deskripsi);
        $('#edit_aktif').prop('checked', !!resp.result.is_aktif);
        $('#editModal').modal('show');
      } else showToast(resp.result,'error');
    },'json');
  });

  // Update
  $('#edit-form').on('submit',function(e){
    e.preventDefault();
    if(!this.checkValidity()) return;
    var an = AutoNumeric.getAutoNumericElement('#edit_jumlah');
    $(this).find('input[name=jumlah]').val(an.getNumber());
    var btn = $(this).find('button[type=submit]');
    btn.prop('disabled',true).find('.spinner-border').removeClass('d-none');
    $.post('?ajax=1',$(this).serialize(),resp=>{
      btn.prop('disabled',false).find('.spinner-border').addClass('d-none');
      if(resp.code===0){
        showToast(resp.result);
        $('#editModal').modal('hide');
        table.ajax.reload(null,false);
        this.reset(); this.classList.remove('was-validated');
      } else showToast(resp.result,'error');
    },'json').fail(()=>{ btn.prop('disabled',false).find('.spinner-border').addClass('d-none'); showToast('Error server','error'); });
  });

  // Hapus
  $(document).on('click','.btn-delete',function(){
    $('#del_id').val($(this).data('id'));
    $('#delModal').modal('show');
  });
  $('#del-form').on('submit',function(e){
    e.preventDefault();
    var id=$('#del_id').val(), btn=$(this).find('button[type=submit]');
    btn.prop('disabled',true).find('.spinner-border').removeClass('d-none');
    $.post('?ajax=1',{case:'DeleteRanking',id},resp=>{
      btn.prop('disabled',false).find('.spinner-border').addClass('d-none');
      if(resp.code===0){
        showToast(resp.result);
        $('#delModal').modal('hide');
        table.ajax.reload(null,false);
      } else showToast(resp.result,'error');
    },'json').fail(()=>{ btn.prop('disabled',false).find('.spinner-border').addClass('d-none'); showToast('Error server','error'); });
  });
});
</script>
</body>
</html>