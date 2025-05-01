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
        case 'LoadingGroups':   LoadingGroups($conn);    break;
        case 'AddGroup':        AddGroup($conn);         break;
        case 'GetGroupDetail':  GetGroupDetail($conn);   break;
        case 'UpdateGroup':     UpdateGroup($conn);      break;
        case 'DeleteGroup':     DeleteGroup($conn);      break;
        default:                send_response(404, 'Kasus Tidak Ditemukan');
    }
    exit();
}

// ==============================================================================
// 3. Fungsi CRUD
// ==============================================================================

function LoadingGroups($conn) {
    $draw   = intval($_POST['draw'] ?? 0);
    $start  = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $search = trim($_POST['search']['value'] ?? '');

    // total distinct group_name & role
    $sqlTotal = "SELECT COUNT(DISTINCT group_name, role) AS total FROM payhead_groups";
    $recordsTotal = intval($conn->query($sqlTotal)->fetch_assoc()['total']);

    // filtered count
    $where = " WHERE 1=1 ";
    $params = []; $types = "";
    if ($search !== '') {
        $where .= " AND (group_name LIKE ? OR role LIKE ?) ";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $types   .= "ss";
    }
    $sqlFilterCount = "
      SELECT COUNT(DISTINCT group_name, role) AS total
      FROM payhead_groups
      $where
    ";
    $stmt = $conn->prepare($sqlFilterCount);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $recordsFiltered = intval($stmt->get_result()->fetch_assoc()['total']);
    $stmt->close();

    // main query: group by group_name, role
    $sql = "
      SELECT
        group_name,
        role,
        ANY_VALUE(jenis)      AS jenis,
        ANY_VALUE(sort_order) AS sort_order,
        GROUP_CONCAT(payhead_name ORDER BY payhead_name SEPARATOR ', ') AS members
      FROM payhead_groups
      $where
      GROUP BY group_name, role
      ORDER BY ANY_VALUE(sort_order), group_name, role
      LIMIT ?, ?
    ";
    $typesData = $types . 'ii';
    $params[]  = $start;
    $params[]  = $length;
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($typesData, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $data = []; $no = $start + 1;
    while ($row = $res->fetch_assoc()) {
        $aksi = "
<div class=\"dropdown\">
  <button class=\"btn\" data-bs-toggle=\"dropdown\">
    <i class=\"bi bi-three-dots-vertical\"></i>
  </button>
  <ul class=\"dropdown-menu\">
    <li>
      <a href=\"#\" class=\"dropdown-item btn-edit\"
         data-group=\"".htmlspecialchars($row['group_name'],ENT_QUOTES)."\"
         data-role=\"".htmlspecialchars($row['role'],ENT_QUOTES)."\">
        <i class=\"fas fa-edit\"></i> Edit
      </a>
    </li>
    <li>
      <a href=\"#\" class=\"dropdown-item btn-delete\"
         data-group=\"".htmlspecialchars($row['group_name'],ENT_QUOTES)."\"
         data-role=\"".htmlspecialchars($row['role'],ENT_QUOTES)."\">
        <i class=\"fas fa-trash-alt\"></i> Hapus
      </a>
    </li>
  </ul>
</div>";
        $data[] = [
            "no"          => $no++,
            "group_name"  => htmlspecialchars($row['group_name']),
            "role"        => htmlspecialchars($row['role']),
            "jenis"       => $row['jenis'],
            "members"     => htmlspecialchars($row['members']),
            "sort_order"  => intval($row['sort_order']),
            "aksi"        => $aksi
        ];
    }
    echo json_encode([
        "draw"            => $draw,
        "recordsTotal"    => $recordsTotal,
        "recordsFiltered" => $recordsFiltered,
        "data"            => $data
    ], JSON_UNESCAPED_UNICODE);
}

function AddGroup($conn) {
    $group   = trim($_POST['group_name'] ?? '');
    $role    = $_POST['role'] ?? '';
    $jenis   = $_POST['jenis'] ?? '';
    $members = $_POST['members'] ?? [];
    $order   = intval($_POST['sort_order'] ?? 0);

    if ($group===''
        || !in_array($role,['guru','karyawan'],true)
        || !in_array($jenis,['earnings','deductions'],true)
        || !is_array($members)
        || count($members)===0
    ) {
        send_response(2,'Semua field wajib diisi dan minimal 1 payhead dipilih.');
    }
    // cek duplikat
    $stmt = $conn->prepare("SELECT 1 FROM payhead_groups WHERE group_name=? AND role=? LIMIT 1");
    $stmt->bind_param("ss",$group,$role);
    $stmt->execute();
    if ($stmt->get_result()->num_rows>0) {
        send_response(1,'Group untuk role ini sudah ada.');
    }
    $stmt->close();

    // insert
    $stmt = $conn->prepare(
      "INSERT INTO payhead_groups
       (group_name,role,payhead_name,jenis,sort_order)
       VALUES (?,?,?,?,?)"
    );
    foreach ($members as $ph) {
        $stmt->bind_param("ssssi", $group, $role, $ph, $jenis, $order);
        $stmt->execute();
    }
    $stmt->close();
    add_audit_log($conn, $_SESSION['nip'], 'AddGroup', "Group '$group' ($role) ditambahkan.");
    send_response(0,'Group berhasil ditambahkan.');
}

function GetGroupDetail($conn) {
    $group = trim($_POST['group_name'] ?? '');
    $role  = $_POST['role'] ?? '';
    if ($group==='' || !in_array($role,['guru','karyawan'],true)) {
        send_response(2,'Group tidak valid.');
    }
    $stmt = $conn->prepare(
      "SELECT payhead_name, jenis, sort_order
       FROM payhead_groups
       WHERE group_name=? AND role=?
       ORDER BY payhead_name"
    );
    $stmt->bind_param("ss",$group,$role);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows===0) {
        send_response(1,'Data tidak ditemukan.');
    }
    $detail = ['group_name'=>$group,'role'=>$role,'members'=>[],'jenis'=>'','sort_order'=>0];
    while ($r=$res->fetch_assoc()) {
        $detail['jenis']      = $r['jenis'];
        $detail['sort_order'] = intval($r['sort_order']);
        $detail['members'][]  = $r['payhead_name'];
    }
    $stmt->close();
    add_audit_log($conn, $_SESSION['nip'], 'ViewGroup', "Melihat detail group '$group' ($role).");
    send_response(0, $detail);
}

function UpdateGroup($conn) {
    $old     = trim($_POST['old_group_name'] ?? '');
    $oldRole = $_POST['old_role'] ?? '';
    $group   = trim($_POST['group_name'] ?? '');
    $role    = $_POST['role'] ?? '';
    $jenis   = $_POST['jenis'] ?? '';
    $members = $_POST['members'] ?? [];
    $order   = intval($_POST['sort_order'] ?? 0);

    if ($old==='' || !in_array($oldRole,['guru','karyawan'],true)
        || $group==='' || !in_array($role,['guru','karyawan'],true)
        || !in_array($jenis,['earnings','deductions'],true)
        || !is_array($members) || count($members)===0
    ) {
        send_response(3,'Field wajib diisi.');
    }
    // delete old
    $stmt = $conn->prepare("DELETE FROM payhead_groups WHERE group_name=? AND role=?");
    $stmt->bind_param("ss",$old,$oldRole);
    $stmt->execute();
    $stmt->close();
    // insert new
    $stmt = $conn->prepare(
      "INSERT INTO payhead_groups
       (group_name,role,payhead_name,jenis,sort_order)
       VALUES (?,?,?,?,?)"
    );
    foreach ($members as $ph) {
        $stmt->bind_param("ssssi", $group, $role, $ph, $jenis, $order);
        $stmt->execute();
    }
    $stmt->close();
    add_audit_log($conn, $_SESSION['nip'], 'UpdateGroup', "Group '$old' ($oldRole) diubah jadi '$group' ($role).");
    send_response(0,'Group berhasil diupdate.');
}

function DeleteGroup($conn) {
    $group = trim($_POST['group_name'] ?? '');
    $role  = $_POST['role'] ?? '';
    if ($group==='' || !in_array($role,['guru','karyawan'],true)) {
        send_response(2,'Group tidak valid.');
    }
    $stmt = $conn->prepare("DELETE FROM payhead_groups WHERE group_name=? AND role=?");
    $stmt->bind_param("ss",$group,$role);
    $stmt->execute();
    $stmt->close();
    add_audit_log($conn, $_SESSION['nip'], 'DeleteGroup', "Group '$group' ($role) dihapus.");
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
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

    <div class="card mb-4 shadow">
      <div class="card-header"><i class="fas fa-clipboard-list"></i> Daftar Group</div>
      <div class="card-body">
        <div class="table-responsive">
          <table id="tblGroups" class="table table-bordered table-striped display nowrap" style="width:100%">
            <thead>
              <tr>
                <th>No</th>
                <th>Group Name</th>
                <th>Role</th>
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

  <!-- Modal Add Group -->
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
          <label>Role</label>
          <select name="role" class="form-select" required>
            <option value="">-- Pilih Role --</option>
            <option value="guru">Guru</option>
            <option value="karyawan">Karyawan</option>
          </select>
        </div>
        <div class="mb-3">
          <label>Jenis</label>
          <select name="jenis" class="form-select" required>
            <option value="">-- Pilih Jenis --</option>
            <option value="earnings">Earnings</option>
            <option value="deductions">Deductions</option>
          </select>
        </div>
        <div class="mb-3">
          <label>Members</label>
          <div style="max-height:200px;overflow-y:auto;padding:10px;border:1px solid #dee2e6;border-radius:4px;">
            <?php
              $rs = $conn->query("SELECT nama_payhead FROM payheads ORDER BY nama_payhead");
              $i = 0;
              while ($r = $rs->fetch_assoc()) {
                $i++;
                $ph = htmlspecialchars($r['nama_payhead'], ENT_QUOTES);
                echo <<<CHK
<div class="form-check">
  <input class="form-check-input" type="checkbox"
         name="members[]" id="add_member_$i" value="$ph">
  <label class="form-check-label" for="add_member_$i">$ph</label>
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
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </div>
  </form></div></div>

  <!-- Modal Edit Group -->
  <div class="modal fade" id="modalEdit" tabindex="-1"><div class="modal-dialog"><form id="formEdit">
    <input type="hidden" name="case" value="UpdateGroup">
    <input type="hidden" name="old_group_name" id="old_group_name">
    <input type="hidden" name="old_role"       id="old_role">
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
          <label>Role</label>
          <select name="role" id="edit_role" class="form-select" required>
            <option value="guru">Guru</option>
            <option value="karyawan">Karyawan</option>
          </select>
        </div>
        <div class="mb-3">
          <label>Jenis</label>
          <select name="jenis" id="edit_jenis" class="form-select" required>
            <option value="earnings">Earnings</option>
            <option value="deductions">Deductions</option>
          </select>
        </div>
        <div class="mb-3">
          <label>Members</label>
          <div style="max-height:200px;overflow-y:auto;padding:10px;border:1px solid #dee2e6;border-radius:4px;">
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
        <div class="mb-3">
          <label>Urutan</label>
          <input name="sort_order" id="edit_sort_order" type="number" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </div>
  </form></div></div>

  <!-- Modal Delete Group -->
  <div class="modal fade" id="modalDelete" tabindex="-1"><div class="modal-dialog"><form id="formDelete">
    <input type="hidden" name="case" value="DeleteGroup">
    <input type="hidden" name="group_name" id="del_group_name">
    <input type="hidden" name="role"       id="del_role">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Hapus Group</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        Apakah Anda yakin ingin menghapus group <strong id="del_group_label"></strong> (<span id="del_role_label"></span>)?
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-danger">Hapus</button>
      </div>
    </div>
  </form></div></div>

  <!-- Loading Spinner & JS -->
  <div id="loadingSpinner" class="position-fixed top-50 start-50"><div class="spinner-border text-primary"></div></div>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.colVis.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
  $(function(){
    const toast = Swal.mixin({ toast:true, position:'top-end', timer:2500, showConfirmButton:false });
    function showToast(msg,icon='success'){ toast.fire({icon, title: msg}); }

    // Init DataTable
    const tbl = $('#tblGroups').DataTable({
      processing: true, serverSide: true,
      ajax:{
        url: 'manage_groups.php?ajax=1',
        type:'POST',
        data: d=>{ d.case='LoadingGroups'; return d; },
        beforeSend: ()=>$('#loadingSpinner').show(),
        complete:  ()=>$('#loadingSpinner').hide(),
        error:     ()=>showToast('Gagal load data','error')
      },
      columns:[
        {data:'no',orderable:false},
        {data:'group_name'},
        {data:'role'},
        {data:'jenis'},
        {data:'members'},
        {data:'sort_order'},
        {data:'aksi',orderable:false}
      ],
      dom:'Bfrtip',
      buttons:['excel','pdf','print','colvis'],
      responsive:true
    });

    // Add Group
    $('#formAdd').submit(function(e){
      e.preventDefault();
      const f=$(this);
      if(!f[0].checkValidity()) return f.addClass('was-validated');
      $.post('manage_groups.php?ajax=1', f.serialize(), resp=>{
        if(resp.code===0){
          $('#modalAdd').modal('hide'); showToast(resp.result);
          tbl.ajax.reload(null,false);
          f[0].reset(); f.removeClass('was-validated');
        } else showToast(resp.result,'error');
      },'json');
    });

    // Edit Group – isi form
    $('#tblGroups').on('click','.btn-edit', function(){
      const grp = $(this).data('group'), role = $(this).data('role');
      $.post('manage_groups.php?ajax=1', { case:'GetGroupDetail', group_name:grp, role:role }, resp=>{
        if(resp.code!==0) return showToast(resp.result,'error');
        const d = resp.result;
        $('#old_group_name').val(d.group_name);
        $('#old_role').val(d.role);
        $('#edit_group_name').val(d.group_name);
        $('#edit_role').val(d.role);
        $('#edit_jenis').val(d.jenis);
        $('#edit_sort_order').val(d.sort_order);
        $('input[name="members[]"]').prop('checked',false);
        d.members.forEach(m=> $('input[name="members[]"][value="'+m+'"]').prop('checked',true));
        $('#modalEdit').modal('show');
      },'json');
    });

    // Update Group
    $('#formEdit').submit(function(e){
      e.preventDefault();
      const f=$(this);
      if(!f[0].checkValidity()) return f.addClass('was-validated');
      $.post('manage_groups.php?ajax=1', f.serialize(), resp=>{
        if(resp.code===0){
          $('#modalEdit').modal('hide'); showToast(resp.result);
          tbl.ajax.reload(null,false);
        } else showToast(resp.result,'error');
      },'json');
    });

    // Delete Group – konfirmasi
    $('#tblGroups').on('click','.btn-delete',function(){
      const grp = $(this).data('group'), role = $(this).data('role');
      $('#del_group_name').val(grp);
      $('#del_role').val(role);
      $('#del_group_label').text(grp);
      $('#del_role_label').text(role);
      $('#modalDelete').modal('show');
    });
    $('#formDelete').submit(function(e){
      e.preventDefault();
      $.post('manage_groups.php?ajax=1', $(this).serialize(), resp=>{
        if(resp.code===0){
          $('#modalDelete').modal('hide'); showToast(resp.result);
          tbl.ajax.reload(null,false);
        } else showToast(resp.result,'error');
      },'json');
    });

    $('#btnBack').click(e=>{
      e.preventDefault();
      $('.container').fadeOut(300, ()=>window.location.href = $('#btnBack').data('href'));
    });
  });
  </script>
</body>
</html>
