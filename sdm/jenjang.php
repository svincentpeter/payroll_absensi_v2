<?php
// File: /payroll_absensi_v2/sdm/jenjang.php

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();

generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

authorize(['M:SDM']);

require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/includes/crud_jenjang.php';

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_token($_POST['csrf_token'] ?? '');

        $case = isset($_POST['case']) ? bersihkan_input($_POST['case']) : '';
        switch ($case) {
            case 'LoadingJenjang':       handlerLoadingJenjang($conn);        break;
            case 'AddJenjang':           handlerAddJenjang($conn);            break;
            case 'GetJenjangDetail':     handlerGetJenjangDetail($conn);      break;
            case 'UpdateJenjang':        handlerUpdateJenjang($conn);         break;
            case 'DeleteJenjang':        handlerDeleteJenjang($conn);         break;
            default:                     send_response(404, 'Kasus tidak ditemukan.');
        }
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Jenjang Pendidikan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        body { padding-top: 20px; }
        .page-title { font-family:'Poppins',sans-serif; font-weight:600; font-size:2.5rem; color:#0d47a1; border-bottom:3px solid #1976d2; margin-bottom:1.5rem; display:flex; align-items:center; gap:.5rem; }
        .page-title i { color:#1976d2; font-size:2.8rem; }
        .back-btn { margin-bottom:20px; }
        .btn { transition: transform .2s; }
        .btn:hover { transform: scale(1.05); }
        .card-header { background: linear-gradient(45deg,#0d47a1,#42a5f5); color:white; }
        .table-hover tbody tr:hover { background-color:#e2e6ea; }
        #jenjangTable tbody tr:nth-of-type(odd)  { background:#f9f9f9; }
        #jenjangTable tbody tr:nth-of-type(even) { background:#fff; }
        .table-sm th,.table-sm td { font-size:13px; vertical-align:middle; white-space:nowrap; }
        thead th { background:#343a40; color:white; text-align:left; }
        .table-responsive { overflow-x:auto; }
        #loadingSpinner { display:none; position:fixed; z-index:9999; top:0;left:0;right:0;bottom:0; margin:auto; height:100px; width:100px; }
        .color-preview { width:25px; height:20px; border-radius:3px; border:1px solid #ccc; display:block; margin:auto;}
        .color-label { font-size:11px; color:#888; text-align:center;}
    </style>
</head>
<body id="page-top">
<div class="container" id="main-content">
    <button class="btn btn-secondary back-btn" id="btnBack" data-href="/payroll_absensi_v2/sdm/manage_guru_karyawan.php">
        <i class="fas fa-arrow-left"></i> Kembali
    </button>

    <h1 class="page-title"><i class="fas fa-layer-group"></i> Manajemen Jenjang Pendidikan</h1>

    <div class="card shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-white"><i class="fas fa-list"></i> Daftar Jenjang</h6>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addJenjangModal">
                <i class="fas fa-plus-circle"></i> Tambah Jenjang
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="jenjangTable" class="table table-sm table-bordered table-hover nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Deskripsi</th>
                            <th>Warna BG</th>
                            <th>Warna Teks</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="loadingSpinner">
    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
</div>

<!-- Modal Tambah Jenjang -->
<div class="modal fade" id="addJenjangModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="add-jenjang-form" class="modal-content needs-validation" novalidate>
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Tambah Jenjang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="case" value="AddJenjang">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                <div class="mb-3">
                    <label class="form-label">Kode Jenjang *</label>
                    <input type="text" name="kode_jenjang" class="form-control" required>
                    <div class="invalid-feedback">Wajib diisi.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Jenjang *</label>
                    <input type="text" name="nama_jenjang" class="form-control" required>
                    <div class="invalid-feedback">Wajib diisi.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" class="form-control" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Warna Latar (BG) *</label>
                    <input type="color" name="color_bg" class="form-control form-control-color" value="#6c757d" required>
                    <div class="invalid-feedback">Pilih warna latar.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Warna Teks (FG) *</label>
                    <input type="color" name="color_fg" class="form-control form-control-color" value="#ffffff" required>
                    <div class="invalid-feedback">Pilih warna teks.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="submit" class="btn btn-primary">
                    Simpan <span class="spinner-border spinner-border-sm d-none"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Jenjang -->
<div class="modal fade" id="editJenjangModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="edit-jenjang-form" class="modal-content needs-validation" novalidate>
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Jenjang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="case" value="UpdateJenjang">
                <input type="hidden" id="edit_id" name="edit_id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                <div class="mb-3">
                    <label class="form-label">Kode Jenjang *</label>
                    <input type="text" id="edit_kode_jenjang" name="kode_jenjang" class="form-control" required>
                    <div class="invalid-feedback">Wajib diisi.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Jenjang *</label>
                    <input type="text" id="edit_nama_jenjang" name="nama_jenjang" class="form-control" required>
                    <div class="invalid-feedback">Wajib diisi.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Deskripsi</label>
                    <textarea id="edit_deskripsi" name="deskripsi" class="form-control" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Warna Latar (BG) *</label>
                    <input type="color" id="edit_color_bg" name="color_bg" class="form-control form-control-color" required>
                    <div class="invalid-feedback">Pilih warna latar.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Warna Teks (FG) *</label>
                    <input type="color" id="edit_color_fg" name="color_fg" class="form-control form-control-color" required>
                    <div class="invalid-feedback">Pilih warna teks.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="submit" class="btn btn-primary">
                    Update <span class="spinner-border spinner-border-sm d-none"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JS Dependencies -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(function() {
    // SweetAlert2 Toast
    const Toast = Swal.mixin({ toast:true, position:'top-end', showConfirmButton:false, timer:3000 });
    function showToast(msg, icon='success'){ Toast.fire({ icon, title: msg }); }

    // DataTable
    const table = $('#jenjangTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'jenjang.php?ajax=1',
            type: 'POST',
            data: d => { d.case = 'LoadingJenjang'; d.csrf_token = '<?= $csrf_token ?>'; },
            beforeSend: () => $('#loadingSpinner').show(),
            complete: () => $('#loadingSpinner').hide(),
            error: () => showToast('Gagal memuat data jenjang.', 'error')
        },
        columns: [
            { data: 'no', orderable: false },
            { data: 'kode' },
            { data: 'nama' },
            { data: 'deskripsi' },
            { data: 'color_bg', orderable: false },
            { data: 'color_fg', orderable: false },
            { data: 'aksi', orderable: false }
        ],
        language: { url: "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json" },
        responsive: true,
        autoWidth: false,
        columnDefs: [
            { targets: [4,5], className: "text-center" }
        ]
    });

    // Tombol Back
    $('#btnBack').click(function(){
        $('#main-content').fadeOut(200, () => window.location.href = $(this).data('href'));
    });

    // Bootstrap form validation
    (function(){
        'use strict';
        var forms = document.getElementsByClassName('needs-validation');
        Array.prototype.forEach.call(forms, function(form){
            form.addEventListener('submit', function(event){
                if (!form.checkValidity()){
                    event.preventDefault(); event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    })();

    // Tambah Jenjang
    $('#add-jenjang-form').on('submit', function(e){
        e.preventDefault();
        var f = $(this);
        if (!this.checkValidity()) return;
        f.find('button[type="submit"]').prop('disabled', true).find('.spinner-border').removeClass('d-none');
        $.post('jenjang.php?ajax=1', f.serialize(), function(res){
            f.find('button[type="submit"]').prop('disabled', false).find('.spinner-border').addClass('d-none');
            if (res.code === 0){
                showToast(res.result,'success');
                $('#addJenjangModal').modal('hide');
                table.ajax.reload(null,false);
                f[0].reset(); f.removeClass('was-validated');
            } else {
                showToast(res.result,'error');
            }
        }, 'json').fail(function(){
            showToast('Gagal menambah jenjang.','error');
            f.find('button[type="submit"]').prop('disabled', false).find('.spinner-border').addClass('d-none');
        });
    });

    // Buka modal Edit Jenjang
    $('#jenjangTable').on('click', '.btn-edit', function(){
        var id = $(this).data('id');
        $.post('jenjang.php?ajax=1', {
            case: 'GetJenjangDetail',
            id: id,
            csrf_token: '<?= $csrf_token ?>'
        }, function(res){
            if (res.code === 0){
                $('#edit_id').val(res.result.id);
                $('#edit_kode_jenjang').val(res.result.kode);
                $('#edit_nama_jenjang').val(res.result.nama);
                $('#edit_deskripsi').val(res.result.deskripsi);
                $('#edit_color_bg').val(res.result.color_bg);
                $('#edit_color_fg').val(res.result.color_fg);
                $('#editJenjangModal').modal('show');
            } else showToast(res.result,'error');
        }, 'json').fail(() => showToast('Gagal mengambil detail.','error'));
    });

    // Update Jenjang
    $('#edit-jenjang-form').on('submit', function(e){
        e.preventDefault();
        var f = $(this);
        if (!this.checkValidity()) return;
        f.find('button[type="submit"]').prop('disabled', true).find('.spinner-border').removeClass('d-none');
        $.post('jenjang.php?ajax=1', f.serialize(), function(res){
            f.find('button[type="submit"]').prop('disabled', false).find('.spinner-border').addClass('d-none');
            if (res.code === 0){
                showToast(res.result,'success');
                $('#editJenjangModal').modal('hide');
                table.ajax.reload(null,false);
            } else {
                showToast(res.result,'error');
            }
        }, 'json').fail(function(){
            showToast('Gagal mengupdate jenjang.','error');
            f.find('button[type="submit"]').prop('disabled', false).find('.spinner-border').addClass('d-none');
        });
    });

    // Hapus Jenjang
    $('#jenjangTable').on('click', '.btn-delete', function(){
        var id = $(this).data('id');
        Swal.fire({
            title: 'Yakin ingin menghapus?',
            text: 'Data jenjang akan dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed){
                $.post('jenjang.php?ajax=1', {
                    case: 'DeleteJenjang',
                    id: id,
                    csrf_token: '<?= $csrf_token ?>'
                }, function(res){
                    if (res.code === 0){
                        showToast(res.result,'success');
                        table.ajax.reload(null,false);
                    } else {
                        showToast(res.result,'error');
                    }
                }, 'json').fail(() => showToast('Gagal menghapus jenjang.','error'));
            }
        });
    });

});
</script>
</body>
</html>
