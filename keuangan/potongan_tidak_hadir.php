<?php
// File: /payroll_absensi_v2/keuangan/potongan_tidak_hadir.php

$pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:Keuangan']);

require_once __DIR__ . '/../koneksi.php';

// Hapus output buffering jika ada
if (ob_get_length()) ob_end_clean();

// ===================
// AJAX HANDLER
// ===================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $case = trim($_POST['case'] ?? '');
        switch ($case) {
            case 'LoadingPotonganTidakHadir': LoadingPotonganTidakHadir($conn); break;
            case 'AddPotonganTidakHadir': AddPotonganTidakHadir($conn); break;
            case 'GetPotonganTidakHadirDetail': GetPotonganTidakHadirDetail($conn); break;
            case 'UpdatePotonganTidakHadir': UpdatePotonganTidakHadir($conn); break;
            case 'DeletePotonganTidakHadir': DeletePotonganTidakHadir($conn); break;
            default: send_response(404, 'Kasus tidak ditemukan.');
        }
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    exit();
}

// ========== CRUD FUNCTIONS ===========
function LoadingPotonganTidakHadir($conn)
{
    $draw   = intval($_POST['draw'] ?? 0);
    $start  = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $search = trim($_POST['search']['value'] ?? '');

    // Filter Tahun & Role
    $filterTahun = trim($_POST['filter_tahun'] ?? '');
    $filterRole = trim($_POST['filter_role'] ?? '');

    $sqlTotal = "SELECT COUNT(*) as total FROM potongan_ketidakhadiran";
    $resultTotal = $conn->query($sqlTotal);
    $recordsTotal = $resultTotal ? $resultTotal->fetch_assoc()['total'] : 0;

    // Query filter
    $sql = "SELECT * FROM potongan_ketidakhadiran WHERE 1=1";
    $params = []; $types = "";

    if ($search !== "") {
        $sql .= " AND (tahun LIKE ? OR role LIKE ? OR keterangan LIKE ?)";
        $s = "%$search%";
        $params[] = $s; $params[] = $s; $params[] = $s;
        $types .= "sss";
    }
    if ($filterTahun !== "") {
        $sql .= " AND tahun = ?";
        $params[] = $filterTahun;
        $types .= "i";
    }
    if ($filterRole !== "") {
        $sql .= " AND role = ?";
        $params[] = $filterRole;
        $types .= "s";
    }
    $sqlCount = "SELECT COUNT(*) as total FROM ($sql) as x";
    // Sorting & Paging
    $sql .= " ORDER BY tahun DESC, role ASC LIMIT ?, ?";
    $params[] = $start; $params[] = $length; $types .= "ii";

    $stmt = $conn->prepare($sql);
    if (!empty($types)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $data = [];
    $res = $stmt->get_result();
    $no = $start + 1;
    while ($row = $res->fetch_assoc()) {
        // badge role
        $roleMap = [
            'P' => '<span class="badge bg-primary">Guru/Karyawan</span>',
            'TK' => '<span class="badge bg-info text-dark">Tendik</span>',
            'M' => '<span class="badge bg-warning text-dark">Manajerial</span>',
        ];
        $roleBadge = $roleMap[$row['role']] ?? htmlspecialchars($row['role']);
        $maxHari = $row['max_hari'] !== null ? $row['max_hari'] : '-';
        $aksi = '
            <div class="dropdown">
              <button class="btn" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item btn-edit" href="#" data-id="' . $row['id'] . '"><i class="fas fa-pencil-alt"></i> Edit</a></li>
                <li><a class="dropdown-item btn-delete" href="#" data-id="' . $row['id'] . '"><i class="fas fa-trash-alt"></i> Hapus</a></li>
              </ul>
            </div>';
        $data[] = [
            'no' => $no++,
            'tahun' => $row['tahun'],
            'role' => $roleBadge,
            'biaya_per_hari' => 'Rp ' . number_format($row['biaya_per_hari'], 0, ',', '.'),
            'max_hari' => $maxHari,
            'keterangan' => htmlspecialchars($row['keterangan'] ?? ''),
            'aksi' => $aksi
        ];
    }
    $stmt->close();

    // recordsFiltered
    $stmt2 = $conn->prepare($sqlCount);
if (strlen($types) > 2) { // ada filter param, exclude LIMIT
    $stmt2->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
}
$stmt2->execute();
$result2 = $stmt2->get_result();
$recordsFiltered = $result2 ? $result2->fetch_assoc()['total'] : 0;
$stmt2->close();


    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $recordsTotal,
        "recordsFiltered" => $recordsFiltered,
        "data" => $data
    ]);
    exit();
}

function AddPotonganTidakHadir($conn)
{
    $tahun = intval($_POST['tahun'] ?? date('Y'));
    $role = trim($_POST['role'] ?? '');
    $biaya = floatval(str_replace(['.', ','], ['', '.'], $_POST['biaya_per_hari'] ?? '0'));
    $max_hari = isset($_POST['max_hari']) && $_POST['max_hari'] !== '' ? intval($_POST['max_hari']) : null;
    $keterangan = trim($_POST['keterangan'] ?? '');

    // Validasi sederhana
    if (!$tahun || !$role || $biaya <= 0) {
        send_response(2, 'Tahun, Role, dan Biaya wajib diisi.');
    }

    // Duplikasi check
    $cek = $conn->prepare("SELECT id FROM potongan_ketidakhadiran WHERE tahun=? AND role=?");
    $cek->bind_param("is", $tahun, $role);
    $cek->execute();
    $cek->store_result();
    if ($cek->num_rows > 0) {
        $cek->close();
        send_response(3, 'Data potongan untuk role & tahun ini sudah ada.');
    }
    $cek->close();

    $stmt = $conn->prepare("INSERT INTO potongan_ketidakhadiran (tahun, role, biaya_per_hari, max_hari, keterangan) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isdss", $tahun, $role, $biaya, $max_hari, $keterangan);
    if ($stmt->execute()) {
        send_response(0, 'Potongan berhasil ditambah.');
    } else {
        send_response(1, 'Gagal tambah data: ' . $stmt->error);
    }
    $stmt->close();
    exit();
}

function GetPotonganTidakHadirDetail($conn)
{
    $id = intval($_POST['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM potongan_ketidakhadiran WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $d = $res->fetch_assoc();
        send_response(0, $d);
    } else {
        send_response(1, 'Data tidak ditemukan.');
    }
    $stmt->close();
    exit();
}

function UpdatePotonganTidakHadir($conn)
{
    $id = intval($_POST['edit_id'] ?? 0);
    $tahun = intval($_POST['edit_tahun'] ?? date('Y'));
    $role = trim($_POST['edit_role'] ?? '');
    $biaya = floatval(str_replace(['.', ','], ['', '.'], $_POST['edit_biaya_per_hari'] ?? '0'));
    $max_hari = isset($_POST['edit_max_hari']) && $_POST['edit_max_hari'] !== '' ? intval($_POST['edit_max_hari']) : null;
    $keterangan = trim($_POST['edit_keterangan'] ?? '');

    if (!$id || !$tahun || !$role || $biaya <= 0) {
        send_response(2, 'Semua field wajib diisi.');
    }
    // Duplikasi (exclude self)
    $cek = $conn->prepare("SELECT id FROM potongan_ketidakhadiran WHERE tahun=? AND role=? AND id != ?");
    $cek->bind_param("isi", $tahun, $role, $id);
    $cek->execute();
    $cek->store_result();
    if ($cek->num_rows > 0) {
        $cek->close();
        send_response(3, 'Sudah ada potongan role-tahun ini.');
    }
    $cek->close();

    $stmt = $conn->prepare("UPDATE potongan_ketidakhadiran SET tahun=?, role=?, biaya_per_hari=?, max_hari=?, keterangan=? WHERE id=?");
    $stmt->bind_param("isdssi", $tahun, $role, $biaya, $max_hari, $keterangan, $id);
    if ($stmt->execute()) {
        send_response(0, 'Berhasil update.');
    } else {
        send_response(1, 'Gagal update data: ' . $stmt->error);
    }
    $stmt->close();
    exit();
}

function DeletePotonganTidakHadir($conn)
{
    $id = intval($_POST['id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM potongan_ketidakhadiran WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        send_response(0, 'Berhasil dihapus.');
    } else {
        send_response(1, 'Gagal hapus: ' . $stmt->error);
    }
    $stmt->close();
    exit();
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Potongan Ketidakhadiran - Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap, SB Admin 2, DataTables, SweetAlert, AutoNumeric, Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.1.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }
        .page-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 2.5rem;
            color: #0d47a1;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 3px solid #1976d2;
            padding-bottom: 0.3rem;
            margin-bottom: 1.5rem;
            animation: fadeInSlide 0.5s ease-in-out both;
        }
        .page-title i { color: #1976d2; font-size: 2.8rem; }
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
        <i class="bi bi-person-x"></i> Potongan Ketidakhadiran
    </h1>
    <!-- Filter Card -->
    <div class="card mb-4">
        <div class="card-header">
            <strong><i class="fas fa-filter me-2"></i>Filter Potongan</strong>
        </div>
        <div class="card-body">
            <form id="filterForm" class="row align-items-center">
                <div class="col-md-3 mb-2">
                    <label class="form-label">Tahun</label>
                    <input type="number" min="2020" max="2100" step="1" class="form-control" id="filterTahun" name="filter_tahun" placeholder="Tahun">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Role</label>
                    <select class="form-select" id="filterRole" name="filter_role">
                        <option value="">Semua</option>
                        <option value="P">Guru/Karyawan</option>
                        <option value="TK">Tendik</option>
                        <option value="M">Manajerial</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end">
                    <button type="button" class="btn btn-primary me-2" id="btnApplyFilter">
                        <i class="fas fa-check-circle"></i> Terapkan
                    </button>
                    <button type="button" class="btn btn-secondary" id="btnResetFilter">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Data -->
    <div class="card shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-white">
                <i class="fas fa-clipboard-list me-1"></i> Daftar Potongan Ketidakhadiran
            </h6>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addPotonganModal">
                <i class="fas fa-plus"></i> Tambah Potongan
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="potonganTable" class="table table-sm table-bordered table-hover table-striped display nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tahun</th>
                            <th>Role</th>
                            <th>Biaya/Hari</th>
                            <th>Maksimal Hari</th>
                            <th>Keterangan</th>
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

<!-- Modal Tambah -->
<div class="modal fade" id="addPotonganModal" tabindex="-1" aria-labelledby="addPotonganModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="add-potongan-form" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="addPotonganModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>Tambah Potongan Ketidakhadiran
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="case" value="AddPotonganTidakHadir">
                    <div class="mb-3">
                        <label class="form-label">Tahun <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="tahun" required min="2020" max="2100" value="<?= date('Y'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" name="role" required>
                            <option value="">---Pilih---</option>
                            <option value="P">Guru/Karyawan</option>
                            <option value="TK">Tendik</option>
                            <option value="M">Manajerial</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Biaya Potongan / Hari <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="biaya_per_hari" name="biaya_per_hari" placeholder="Contoh: 75000" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Maksimal Hari Potong <small>(boleh dikosongkan)</small></label>
                        <input type="number" class="form-control" name="max_hari" min="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="2" placeholder="Contoh: Maksimal 2x potong, dsb."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Tutup
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="editPotonganModal" tabindex="-1" aria-labelledby="editPotonganModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="edit-potongan-form" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="editPotonganModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Potongan Ketidakhadiran
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="case" value="UpdatePotonganTidakHadir">
                    <input type="hidden" id="edit_id" name="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Tahun <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="edit_tahun" name="edit_tahun" required min="2020" max="2100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_role" name="edit_role" required>
                            <option value="">---Pilih---</option>
                            <option value="P">Guru/Karyawan</option>
                            <option value="TK">Tendik</option>
                            <option value="M">Manajerial</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Biaya Potongan / Hari <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_biaya_per_hari" name="edit_biaya_per_hari" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Maksimal Hari Potong <small>(boleh dikosongkan)</small></label>
                        <input type="number" class="form-control" id="edit_max_hari" name="edit_max_hari" min="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" id="edit_keterangan" name="edit_keterangan" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Tutup
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Hapus -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form id="deleteForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i>Hapus Potongan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="delete_id" name="id">
                    <p>Yakin ingin menghapus data ini?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-check"></i> Ya, Hapus
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Loading Spinner -->
<div id="loadingSpinner" style="display:none;position:fixed;z-index:9999;height:100px;width:100px;margin:auto;top:0;left:0;right:0;bottom:0;">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.1.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/autonumeric@4.8.0/dist/autoNumeric.min.js"></script>
<script>
$(document).ready(function() {
    const Toast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true,
        didOpen: toast => { toast.addEventListener('mouseenter', Swal.stopTimer); toast.addEventListener('mouseleave', Swal.resumeTimer);}
    });
    function showToast(msg, icon='success'){ Toast.fire({icon, title:msg}); }

    // AutoNumeric: Add & Edit
    new AutoNumeric('#biaya_per_hari', {digitGroupSeparator: '.', decimalCharacter: ',', decimalPlaces:0, unformatOnSubmit:true});
    new AutoNumeric('#edit_biaya_per_hari', {digitGroupSeparator: '.', decimalCharacter: ',', decimalPlaces:0, unformatOnSubmit:true});

    // DataTables
    var potonganTable = $('#potonganTable').DataTable({
        processing: true, serverSide: true,
        ajax: {
            url: "potongan_tidak_hadir.php?ajax=1", type: "POST",
            data: function(d){
                d.case='LoadingPotonganTidakHadir';
                d.filter_tahun = $('#filterTahun').val();
                d.filter_role = $('#filterRole').val();
            },
            beforeSend: function(){ $('#loadingSpinner').show(); },
            complete: function(){ $('#loadingSpinner').hide(); },
            error: function(){ showToast('Terjadi kesalahan load data.', 'error'); }
        },
        columns: [
            {data:"no", orderable:false}, {data:"tahun"}, {data:"role"}, {data:"biaya_per_hari"},
            {data:"max_hari"}, {data:"keterangan"}, {data:"aksi", orderable:false}
        ],
        language: { url: "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json" },
        responsive:true, autoWidth:false
    });

    $('#btnApplyFilter').on('click', ()=> potonganTable.ajax.reload());
    $('#btnResetFilter').on('click', function(){
        $('#filterForm')[0].reset();
        potonganTable.ajax.reload();
    });

    // Add
    $('#add-potongan-form').on('submit', function(e){
        e.preventDefault();
        var form = $(this);
        if(!this.checkValidity()) { form.addClass('was-validated'); return; }
        var an = AutoNumeric.getAutoNumericElement('#biaya_per_hari');
        $('#biaya_per_hari').val(an.getNumber());
        $.ajax({
            url:"potongan_tidak_hadir.php?ajax=1", type:"POST", data:form.serialize(), dataType:"json",
            beforeSend:()=>form.find('button[type="submit"]').prop('disabled',true).find('.spinner-border').removeClass('d-none'),
            success: function(resp){
                form.find('button[type="submit"]').prop('disabled',false).find('.spinner-border').addClass('d-none');
                if(resp.code===0){
                    showToast(resp.result,'success');
                    $('#addPotonganModal').modal('hide'); potonganTable.ajax.reload(null,false); form[0].reset(); form.removeClass('was-validated');
                } else { showToast(resp.result,'error'); }
            }, error:()=>showToast('Gagal tambah data','error')
        });
    });

    // Edit
    $(document).on('click', '.btn-edit', function(){
        var id=$(this).data('id');
        var modal=$('#editPotonganModal');
        var form=$('#edit-potongan-form');
        form[0].reset(); form.removeClass('was-validated');
        $.ajax({
            url:"potongan_tidak_hadir.php?ajax=1", type:"POST", data:{id:id,case:'GetPotonganTidakHadirDetail'}, dataType:"json",
            success:function(resp){
                if(resp.code===0){
                    $('#edit_id').val(resp.result.id);
                    $('#edit_tahun').val(resp.result.tahun);
                    $('#edit_role').val(resp.result.role);
                    var an = AutoNumeric.getAutoNumericElement('#edit_biaya_per_hari');
                    an.set(resp.result.biaya_per_hari);
                    $('#edit_max_hari').val(resp.result.max_hari);
                    $('#edit_keterangan').val(resp.result.keterangan);
                    modal.modal('show');
                }else{ showToast(resp.result,'error'); }
            }, error:()=>showToast('Gagal ambil detail.','error')
        });
    });
    $('#edit-potongan-form').on('submit', function(e){
        e.preventDefault();
        var form=$(this);
        var an = AutoNumeric.getAutoNumericElement('#edit_biaya_per_hari');
        $('#edit_biaya_per_hari').val(an.getNumber());
        if(!this.checkValidity()){ form.addClass('was-validated'); return; }
        $.ajax({
            url:"potongan_tidak_hadir.php?ajax=1", type:"POST", data:form.serialize(), dataType:"json",
            beforeSend:()=>form.find('button[type="submit"]').prop('disabled',true).find('.spinner-border').removeClass('d-none'),
            success:function(resp){
                form.find('button[type="submit"]').prop('disabled',false).find('.spinner-border').addClass('d-none');
                if(resp.code===0){
                    showToast(resp.result,'success'); $('#editPotonganModal').modal('hide');
                    potonganTable.ajax.reload(null,false); form[0].reset(); form.removeClass('was-validated');
                }else{ showToast(resp.result,'error'); }
            }, error:()=>showToast('Gagal update.','error')
        });
    });

    // Delete
    $(document).on('click','.btn-delete',function(){
        var id=$(this).data('id');
        $('#delete_id').val(id); $('#deleteModal').modal('show');
    });
    $('#deleteForm').on('submit',function(e){
        e.preventDefault();
        var id=$('#delete_id').val();
        if(!id){ showToast('ID tidak ditemukan.','error'); return;}
        $.ajax({
            url:"potongan_tidak_hadir.php?ajax=1",type:"POST",data:{id:id,case:'DeletePotonganTidakHadir'},dataType:"json",
            beforeSend:()=>$('#deleteForm').find('button[type="submit"]').prop('disabled',true).find('.spinner-border').removeClass('d-none'),
            success:function(resp){
                $('#deleteForm').find('button[type="submit"]').prop('disabled',false).find('.spinner-border').addClass('d-none');
                if(resp.code===0){
                    showToast(resp.result,'success');
                    $('#deleteModal').modal('hide'); potonganTable.ajax.reload(null,false);
                }else{ showToast(resp.result,'error'); }
            }, error:()=>showToast('Gagal hapus.','error')
        });
    });

    $('#filterForm').on('keypress',function(e){ if(e.which===13)$('#btnApplyFilter').click(); });
});
</script>
</body>
</html>
