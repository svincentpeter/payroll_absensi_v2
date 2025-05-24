<?php
// File: /sdm/jenjang.php
$pageTitle = "Manajemen Jenjang Pendidikan";
require_once __DIR__ . '/../koneksi.php'; // Load koneksi utama
require_once __DIR__ . '/includes/crud_jenjang.php';
require_once __DIR__ . '/../helpers.php';
start_session_safe();
authorize(['M:SDM', 'M:Superadmin'], '/payroll_absensi_v2/login.php');
$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
$_SESSION['csrf_token'] = $csrf_token;
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title><?= $pageTitle ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/startbootstrap-sb-admin-2/4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <style>
    .page-title { font-family: 'Poppins',sans-serif; font-weight: 600; font-size: 2rem; color: #0d47a1; }
    .card-header { background: linear-gradient(135deg, #3a7bd5 0%, #00d2ff 100%); color:white; }
    .modal-footer .spinner-border { display:none; }
  </style>
</head>
<body id="page-top">
  <div id="wrapper">
    <?php include __DIR__ . '/../sidebar.php'; ?>
    <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
        <?php include __DIR__ . '/../navbar.php'; ?>
        <div class="container-fluid">
          <h1 class="page-title mb-4"><i class="bi bi-collection"></i> <?= $pageTitle ?></h1>
          <div class="d-flex justify-content-end mb-3">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddJenjang">
              <i class="fas fa-plus"></i> Tambah Jenjang
            </button>
          </div>

          <div class="card shadow mb-4">
            <div class="card-header"><i class="fas fa-layer-group"></i> Daftar Jenjang</div>
            <div class="card-body">
              <div id="jenjangTableWrap"></div>
            </div>
          </div>
        </div>
      </div>
      <footer class="sticky-footer bg-white">
        <div class="container my-auto"><div class="text-center my-auto"><span>&copy; <?= date("Y") ?> Payroll System</span></div></div>
      </footer>
    </div>
  </div>

  <!-- Modal Tambah Jenjang -->
  <div class="modal fade" id="modalAddJenjang" tabindex="-1">
    <div class="modal-dialog">
      <form id="formAddJenjang" class="modal-content">
        <input type="hidden" name="case" value="create">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="modal-header"><h5 class="modal-title">Tambah Jenjang</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label>Kode Jenjang <span class="text-danger">*</span></label>
            <input type="text" name="kode_jenjang" class="form-control" required>
          </div>
          <div class="mb-3">
            <label>Nama Jenjang <span class="text-danger">*</span></label>
            <input type="text" name="nama_jenjang" class="form-control" required>
          </div>
          <div class="mb-3">
            <label>Deskripsi</label>
            <textarea name="deskripsi" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <span class="spinner-border spinner-border-sm"></span>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success">Simpan</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Edit Jenjang -->
  <div class="modal fade" id="modalEditJenjang" tabindex="-1">
    <div class="modal-dialog">
      <form id="formEditJenjang" class="modal-content">
        <input type="hidden" name="case" value="update">
        <input type="hidden" name="id" id="editId">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="modal-header"><h5 class="modal-title">Edit Jenjang</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label>Kode Jenjang <span class="text-danger">*</span></label>
            <input type="text" name="kode_jenjang" id="editKodeJenjang" class="form-control" required>
          </div>
          <div class="mb-3">
            <label>Nama Jenjang <span class="text-danger">*</span></label>
            <input type="text" name="nama_jenjang" id="editNamaJenjang" class="form-control" required>
          </div>
          <div class="mb-3">
            <label>Deskripsi</label>
            <textarea name="deskripsi" id="editDeskripsi" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <span class="spinner-border spinner-border-sm"></span>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Hapus Jenjang -->
  <div class="modal fade" id="modalDeleteJenjang" tabindex="-1">
    <div class="modal-dialog">
      <form id="formDeleteJenjang" class="modal-content">
        <input type="hidden" name="case" value="soft_delete">
        <input type="hidden" name="id" id="delJenjangId">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="modal-header"><h5 class="modal-title">Konfirmasi Hapus</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <p>Yakin ingin menghapus jenjang <b id="delJenjangName"></b>?</p>
        </div>
        <div class="modal-footer">
          <span class="spinner-border spinner-border-sm"></span>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">Hapus</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Script Dependencies -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    const handlerUrl = 'includes/jenjang_handler.php';
    // Utility
    function showToast(msg, icon = 'success') {
      Swal.fire({toast:true,position:'top-end',timer:3000,icon:icon,title:msg,showConfirmButton:false});
    }
    function spinner(modal, show) {
      $(modal).find('.spinner-border').toggle(show);
    }
    // Render Table
    function renderJenjangTable(data) {
      let html = `<table class="table table-bordered align-middle text-center"><thead class="table-light">
        <tr><th width="10%">#</th><th width="20%">Kode</th><th width="40%">Nama</th><th>Deskripsi</th><th width="20%">Aksi</th></tr></thead><tbody>`;
      if (data.length === 0) html += `<tr><td colspan="5">Belum ada data.</td></tr>`;
      data.forEach((r,i)=>{
        html += `<tr>
          <td>${i+1}</td>
          <td>${r.kode_jenjang}</td>
          <td>${r.nama_jenjang}</td>
          <td>${r.deskripsi||''}</td>
          <td>
            <button class="btn btn-warning btn-sm btn-edit" data-id="${r.id}" data-kode="${r.kode_jenjang}" data-nama="${r.nama_jenjang}" data-desc="${r.deskripsi||''}">
              <i class="fa fa-edit"></i>
            </button>
            <button class="btn btn-danger btn-sm btn-delete" data-id="${r.id}" data-nama="${r.nama_jenjang}">
              <i class="fa fa-trash"></i>
            </button>
          </td>
        </tr>`;
      });
      html += `</tbody></table>`;
      $('#jenjangTableWrap').html(html);
    }
    function loadJenjang() {
      $.post(handlerUrl, {case:'getAll'}, res => {
        if(res.code === 0) renderJenjangTable(res.data||[]);
        else showToast('Gagal memuat data','error');
      },'json');
    }
    $(document).ready(function(){
      loadJenjang();

      // Add Jenjang
      $('#formAddJenjang').on('submit', function(e){
        e.preventDefault();
        spinner('#modalAddJenjang', true);
        $.post(handlerUrl, $(this).serialize(), res => {
          spinner('#modalAddJenjang', false);
          if(res.code===0){
            $('#modalAddJenjang').modal('hide');
            showToast(res.result);
            loadJenjang();
            this.reset();
          } else showToast(res.result,'error');
        },'json');
      });
      // Edit Jenjang open
      $(document).on('click', '.btn-edit', function(){
        $('#editId').val($(this).data('id'));
        $('#editKodeJenjang').val($(this).data('kode'));
        $('#editNamaJenjang').val($(this).data('nama'));
        $('#editDeskripsi').val($(this).data('desc'));
        $('#modalEditJenjang').modal('show');
      });
      // Edit Jenjang submit
      $('#formEditJenjang').on('submit', function(e){
        e.preventDefault();
        spinner('#modalEditJenjang', true);
        $.post(handlerUrl, $(this).serialize(), res => {
          spinner('#modalEditJenjang', false);
          if(res.code===0){
            $('#modalEditJenjang').modal('hide');
            showToast(res.result);
            loadJenjang();
          } else showToast(res.result,'error');
        },'json');
      });
      // Delete Jenjang open
      $(document).on('click', '.btn-delete', function(){
        $('#delJenjangId').val($(this).data('id'));
        $('#delJenjangName').text($(this).data('nama'));
        $('#modalDeleteJenjang').modal('show');
      });
      // Delete Jenjang submit
      $('#formDeleteJenjang').on('submit', function(e){
        e.preventDefault();
        spinner('#modalDeleteJenjang', true);
        $.post(handlerUrl, $(this).serialize(), res => {
          spinner('#modalDeleteJenjang', false);
          if(res.code===0){
            $('#modalDeleteJenjang').modal('hide');
            showToast(res.result);
            loadJenjang();
          } else showToast(res.result,'error');
        },'json');
      });
    });
  </script>
</body>
</html>
