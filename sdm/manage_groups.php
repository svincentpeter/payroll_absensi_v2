<?php
// File: /payroll_absensi_v2/sdm/manage_groups.php

// ==============================================================================
// 1. Pengaturan Session, Koneksi, dan Helper
// ==============================================================================
require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:Keuangan', 'M:Superadmin'], '/payroll_absensi_v2/login.php');

require_once __DIR__ . '/../koneksi.php';
if (ob_get_length()) ob_end_clean();

// ==============================================================================
// 2. Tangani AJAX CRUD untuk Payhead Groups
// ==============================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_response(405, 'Metode Tidak Diizinkan');
    }
    $case = isset($_POST['case']) ? trim($_POST['case']) : '';
    switch ($case) {
        case 'LoadingGroups':       LoadingGroups($conn);       break;
        case 'AddGroup':            AddGroup($conn);            break;
        case 'GetGroupDetail':      GetGroupDetail($conn);      break;
        case 'UpdateGroup':         UpdateGroup($conn);         break;
        case 'DeleteGroup':         DeleteGroup($conn);         break;
        default:                    send_response(404, 'Kasus Tidak Ditemukan');
    }
    exit();
}

// ==============================================================================
// 3. Fungsi CRUD
// ==============================================================================

function LoadingGroups($conn) {
  // DataTables params
  $draw   = intval($_POST['draw'] ?? 0);
  $start  = intval($_POST['start'] ?? 0);
  $length = intval($_POST['length'] ?? 10);
  $search = trim($_POST['search']['value'] ?? '');

  // total distinct group_name
  $sqlTotal = "SELECT COUNT(DISTINCT group_name) AS total FROM payhead_groups";
  $totalRes = $conn->query($sqlTotal)->fetch_assoc();
  $recordsTotal = $totalRes['total'] ?: 0;

  // filtered
  $where = " WHERE 1=1 ";
  $params = []; $types = "";
  if ($search !== '') {
      $where .= " AND group_name LIKE ? ";
      $params[] = "%{$search}%";
      $types   .= "s";
  }
  $sqlFilterCount = "
    SELECT COUNT(DISTINCT group_name) AS total
    FROM payhead_groups
    $where
  ";
  $stmt = $conn->prepare($sqlFilterCount);
  if ($types) {
      $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $recFilt = $stmt->get_result()->fetch_assoc()['total'] ?: 0;
  $stmt->close();

  // -------------------------------------------------------------------------
  // Perbaiki di sini: ONLY_FULL_GROUP_BY compliance
  // -------------------------------------------------------------------------
  $sql = "
    SELECT
      group_name,
      ANY_VALUE(jenis)      AS jenis,
      ANY_VALUE(sort_order) AS sort_order,
      GROUP_CONCAT(payhead_name ORDER BY payhead_name SEPARATOR ', ') AS members
    FROM payhead_groups
    $where
    GROUP BY group_name
    ORDER BY ANY_VALUE(sort_order), group_name
    LIMIT ?, ?
  ";

  // tambahkan start/length ke params
  $typesData = $types . 'ii';
  $params[]  = $start;
  $params[]  = $length;

  $stmt = $conn->prepare($sql);
  $stmt->bind_param($typesData, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();

  $data = []; $no = $start + 1;
  while ($row = $res->fetch_assoc()) {
      $aksi = '
<div class="dropdown">
<button class="btn" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
<ul class="dropdown-menu">
  <li><a href="#" class="dropdown-item btn-edit" data-group="'.htmlspecialchars($row['group_name'],ENT_QUOTES).'"><i class="fas fa-edit"></i> Edit</a></li>
  <li><a href="#" class="dropdown-item btn-delete" data-group="'.htmlspecialchars($row['group_name'],ENT_QUOTES).'"><i class="fas fa-trash-alt"></i> Hapus</a></li>
</ul>
</div>';

      $data[] = [
          "no"          => $no++,
          "group_name"  => htmlspecialchars($row['group_name']),
          "jenis"       => $row['jenis'],
          "members"     => htmlspecialchars($row['members']),
          "sort_order"  => intval($row['sort_order']),
          "aksi"        => $aksi
      ];
  }
  echo json_encode([
      "draw"            => $draw,
      "recordsTotal"    => $recordsTotal,
      "recordsFiltered" => $recFilt,
      "data"            => $data
  ], JSON_UNESCAPED_UNICODE);
}


function AddGroup($conn) {
    $group   = trim($_POST['group_name'] ?? '');
    $jenis   = $_POST['jenis'] ?? '';
    $members = $_POST['members'] ?? [];
    $order   = intval($_POST['sort_order'] ?? 0);

    if ($group===''||!in_array($jenis,['earnings','deductions'],true)||!is_array($members)||count($members)===0) {
        send_response(2,'Semua field wajib diisi dan minimal 1 payhead dipilih.');
    }
    // cek duplikat
    $stmt = $conn->prepare("SELECT 1 FROM payhead_groups WHERE group_name=? LIMIT 1");
    $stmt->bind_param("s",$group);
    $stmt->execute();
    if ($stmt->get_result()->num_rows>0) {
        send_response(1,'Group name sudah ada.');
    }
    $stmt->close();
    // insert
    $stmt = $conn->prepare(
      "INSERT INTO payhead_groups
       (group_name,payhead_name,jenis,sort_order)
       VALUES (?,?,?,?)"
    );
    foreach ($members as $ph) {
        $stmt->bind_param("sssi", $group, $ph, $jenis, $order);
        $stmt->execute();
    }
    $stmt->close();
    add_audit_log($conn, $_SESSION['nip'], 'AddGroup', "Group '$group' ditambahkan.");
    send_response(0,'Group berhasil ditambahkan.');
}

function GetGroupDetail($conn) {
    $group = trim($_POST['group_name'] ?? '');
    if ($group==='') send_response(2,'Group tidak valid.');
    $stmt = $conn->prepare(
      "SELECT jenis, sort_order, payhead_name
       FROM payhead_groups
       WHERE group_name=?
       ORDER BY payhead_name"
    );
    $stmt->bind_param("s",$group);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows===0) {
        send_response(1,'Data tidak ditemukan.');
    }
    $detail = ['group_name'=>$group, 'members'=>[], 'jenis'=>'', 'sort_order'=>0];
    while ($r=$res->fetch_assoc()) {
        $detail['jenis']      = $r['jenis'];
        $detail['sort_order'] = intval($r['sort_order']);
        $detail['members'][]  = $r['payhead_name'];
    }
    $stmt->close();
    add_audit_log($conn, $_SESSION['nip'], 'ViewGroup', "Melihat detail group '$group'.");
    send_response(0, $detail);
}

function UpdateGroup($conn) {
    $old     = trim($_POST['old_group_name'] ?? '');
    $group   = trim($_POST['group_name'] ?? '');
    $jenis   = $_POST['jenis'] ?? '';
    $members = $_POST['members'] ?? [];
    $order   = intval($_POST['sort_order'] ?? 0);

    if ($old===''||$group===''||!in_array($jenis,['earnings','deductions'],true)||!is_array($members)||count($members)===0) {
        send_response(3,'Field wajib diisi.');
    }
    // delete old
    $stmt = $conn->prepare("DELETE FROM payhead_groups WHERE group_name=?");
    $stmt->bind_param("s",$old);
    $stmt->execute();
    $stmt->close();
    // insert new
    $stmt = $conn->prepare(
      "INSERT INTO payhead_groups
       (group_name,payhead_name,jenis,sort_order)
       VALUES (?,?,?,?)"
    );
    foreach ($members as $ph) {
        $stmt->bind_param("sssi", $group, $ph, $jenis, $order);
        $stmt->execute();
    }
    $stmt->close();
    add_audit_log($conn, $_SESSION['nip'], 'UpdateGroup', "Group '$old' diubah jadi '$group'.");
    send_response(0,'Group berhasil diupdate.');
}

function DeleteGroup($conn) {
    $group = trim($_POST['group_name'] ?? '');
    if ($group==='') send_response(2,'Group tidak valid.');
    $stmt = $conn->prepare("DELETE FROM payhead_groups WHERE group_name=?");
    $stmt->bind_param("s",$group);
    $stmt->execute();
    $stmt->close();
    add_audit_log($conn, $_SESSION['nip'], 'DeleteGroup', "Group '$group' dihapus.");
    send_response(0,'Group berhasil dihapus.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Manage Payhead Groups</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <!-- Bootstrap & DataTables CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

<!-- DataTables CSS (Bootstrap 5) -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.1.1/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
<!-- Font Awesome & Bootstrap Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <style>
    body { padding: 20px; }
    .card-header { background: linear-gradient(45deg,#0d47a1,#42a5f5); color:#fff; }
    thead th { background:#343a40; color:#fff; }
    .table-responsive { overflow-x:auto; }
  </style>
</head>
<body>
  <div class="container">
  <button class="btn btn-secondary mb-3" id="btnBack" data-href="/payroll_absensi_v2/sdm/payheads.php">
      <i class="fas fa-arrow-left"></i> Kembali ke Manajemen Payheads
    </button>
    <h1 class="h3 mb-4"><i class="fas fa-layer-group"></i> Manage Payhead Groups</h1>
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modalAdd">
      <i class="fas fa-plus"></i> Tambah Group
    </button>
    <div class="card">
      <div class="card-header"><i class="fas fa-clipboard-list"></i> Daftar Group</div>
      <div class="card-body">
        <div class="table-responsive">
          <table id="tblGroups" class="table table-bordered table-striped display nowrap" style="width:100%">
            <thead>
              <tr>
                <th>No</th>
                <th>Group Name</th>
                <th>Jenis</th>
                <th>Members</th>
                <th>Urutan</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Add/Edit Group -->
  <div class="modal fade" id="modalAdd" tabindex="-1"><div class="modal-dialog"><form id="formAdd">
    <input type="hidden" name="case" value="AddGroup">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Tambah Group</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label>Group Name</label>
          <input name="group_name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label>Jenis</label>
          <select name="jenis" class="form-select" required>
            <option value="">-- Pilih --</option>
            <option value="earnings">earnings</option>
            <option value="deductions">deductions</option>
          </select>
        </div>
        <div class="mb-3">
        <label>Members</label>
<div class="mb-3" style="max-height:200px;overflow-y:auto;border:1px solid #dee2e6;padding:10px;border-radius:4px;">
  <?php
    $rs = $conn->query("SELECT nama_payhead FROM payheads ORDER BY nama_payhead");
    $i = 0;
    while ($r = $rs->fetch_assoc()) {
      $i++;
      $ph = htmlspecialchars($r['nama_payhead'], ENT_QUOTES);
      echo <<<CHK
  <div class="form-check">
    <input class="form-check-input" type="checkbox" 
           name="members[]" id="member_$i" value="$ph">
    <label class="form-check-label" for="member_$i">$ph</label>
  </div>
CHK;
    }
  ?>
</div>

        </div>
        <div class="mb-3">
          <label>Urutan (sort_order)</label>
          <input name="sort_order" type="number" class="form-control" value="0" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">
          Simpan <span class="spinner-border spinner-border-sm d-none"></span>
        </button>
      </div>
    </div>
  </form></div></div>

  <div class="modal fade" id="modalEdit" tabindex="-1"><div class="modal-dialog"><form id="formEdit">
    <input type="hidden" name="case" value="UpdateGroup">
    <input type="hidden" name="old_group_name" id="old_group_name">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Group</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label>Group Name</label>
          <input name="group_name" id="edit_group_name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label>Jenis</label>
          <select name="jenis" id="edit_jenis" class="form-select" required>
            <option value="earnings">earnings</option>
            <option value="deductions">deductions</option>
          </select>
        </div>
        <div class="mb-3">
        <div class="mb-3">
  <label>Members</label>
  <div class="mb-3" style="max-height:200px;overflow-y:auto;border:1px solid #dee2e6;padding:10px;border-radius:4px;">
    <?php
      $rs = $conn->query("SELECT nama_payhead FROM payheads ORDER BY nama_payhead");
      $i = 0;
      while ($r = $rs->fetch_assoc()) {
        $i++;
        $ph = htmlspecialchars($r['nama_payhead'], ENT_QUOTES);
        echo <<<CHK
    <div class="form-check">
      <input class="form-check-input" type="checkbox"
             name="members[]" id="edit_member_$i" value="$ph">
      <label class="form-check-label" for="edit_member_$i">$ph</label>
    </div>
CHK;
      }
    ?>
  </div>
</div>

        </div>
        <div class="mb-3">
          <label>Urutan</label>
          <input name="sort_order" id="edit_sort_order" type="number" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">
          Update <span class="spinner-border spinner-border-sm d-none"></span>
        </button>
      </div>
    </div>
  </form></div></div>

  <!-- Modal Delete -->
  <div class="modal fade" id="modalDelete" tabindex="-1"><div class="modal-dialog"><form id="formDelete">
    <input type="hidden" name="case" value="DeleteGroup">
    <input type="hidden" name="group_name" id="del_group_name">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Hapus Group</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        Apakah Anda yakin ingin menghapus group <strong id="del_group_label"></strong>?
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-danger">
          Hapus <span class="spinner-border spinner-border-sm d-none"></span>
        </button>
      </div>
    </div>
  </form></div></div>

  <!-- Loading Spinner & JS -->
  <div id="loadingSpinner" class="position-fixed top-50 start-50"><div class="spinner-border text-primary"></div></div>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<!-- DataTables Buttons core -->
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>

<!-- Export dependencies -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>

<!-- HTML5 export buttons -->
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>

<!-- **Print button plugin (WAJIB)** -->
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>

<!-- Column visibility (opsional) -->
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.colVis.min.js"></script>


  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
  $(function(){
    const toast = Swal.mixin({ toast: true, position:'top-end', timer:2500, showConfirmButton:false });
    function showToast(msg,icon='success'){ toast.fire({icon, title: msg}); }

    // Init DataTable
    const tbl = $('#tblGroups').DataTable({
      processing: true, serverSide: true,
      ajax: {
  url: 'manage_groups.php?ajax=1',
  type: 'POST',
  data: function(d) {
    // sisipkan case sebelum dikirim
    d.case = 'LoadingGroups';
    // kembalikan seluruh d agar DataTables juga ikut mengirim draw/start/length
    return d;
  },
  beforeSend: function() {
    $('#loadingSpinner').show();
  },
  complete: function() {
    $('#loadingSpinner').hide();
  },
  error: function() {
    showToast('Terjadi kesalahan saat memuat data.', 'error');
  }
},

      columns:[
        {data:'no', orderable:false},
        {data:'group_name'},
        {data:'jenis'},
        {data:'members'},
        {data:'sort_order'},
        {data:'aksi', orderable:false}
      ],
      dom:'Bfrtip',
      buttons:[
        { extend:'excelHtml5', className:'btn btn-success btn-sm', text:'<i class="fas fa-file-excel"></i> Excel' },
        { extend:'pdfHtml5',   className:'btn btn-danger btn-sm',  text:'<i class="fas fa-file-pdf"></i> PDF' },
        { extend:'print',      className:'btn btn-info btn-sm',   text:'<i class="fas fa-print"></i> Print' }
      ],
      responsive:true
    });

    // Add Group
    $('#formAdd').submit(function(e){
      e.preventDefault();
      const f=$(this);
      if(!f[0].checkValidity()) return f.addClass('was-validated');
      $.post('manage_groups.php?ajax=1',f.serialize(),resp=>{
        if(resp.code===0){
          $('#modalAdd').modal('hide'); showToast(resp.result);
          tbl.ajax.reload(null,false);
          f[0].reset(); f.removeClass('was-validated');
        } else showToast(resp.result,'error');
      },'json');
    });

    // Edit: show modal
$('#tblGroups').on('click', '.btn-edit', function(){
  const grp = $(this).data('group');
  $.post('manage_groups.php?ajax=1', {
    case: 'GetGroupDetail',
    group_name: grp
  }, resp => {
    if (resp.code !== 0) {
      return showToast(resp.result, 'error');
    }
    const d = resp.result;
    $('#old_group_name').val(d.group_name);
    $('#edit_group_name').val(d.group_name);
    $('#edit_jenis').val(d.jenis);
    $('#edit_sort_order').val(d.sort_order);

    // --- Checkbox Members ---
    // Hapus semua centang
    $('input[name="members[]"]').prop('checked', false);
    // Centang sesuai data yang diterima
    d.members.forEach(m => {
      $(`input[name="members[]"][value="${m}"]`).prop('checked', true);
    });

    $('#modalEdit').modal('show');
  }, 'json');
});


    // Update
    $('#formEdit').submit(function(e){
      e.preventDefault();
      const f=$(this);
      if(!f[0].checkValidity()) return f.addClass('was-validated');
      $.post('manage_groups.php?ajax=1',f.serialize(),resp=>{
        if(resp.code===0){
          $('#modalEdit').modal('hide'); showToast(resp.result);
          tbl.ajax.reload(null,false);
        } else showToast(resp.result,'error');
      },'json');
    });

    // Delete: show confirm
    $('#tblGroups').on('click','.btn-delete',function(){
      const grp=$(this).data('group');
      $('#del_group_name').val(grp);
      $('#del_group_label').text(grp);
      $('#modalDelete').modal('show');
    });
    // Confirm delete
    $('#formDelete').submit(function(e){
      e.preventDefault();
      $.post('manage_groups.php?ajax=1',$(this).serialize(),resp=>{
        if(resp.code===0){
          $('#modalDelete').modal('hide'); showToast(resp.result);
          tbl.ajax.reload(null,false);
        } else showToast(resp.result,'error');
      },'json');
    });
  });

  $('#btnBack').on('click', function(e) {
      e.preventDefault();
      var url = $(this).data('href');
      $('.container').fadeOut(300, function() {
        window.location.href = url;
      });
    });
  </script>
</body>
</html>
