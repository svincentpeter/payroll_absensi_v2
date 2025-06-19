    <?php
    // File: /payroll_absensi_v2/sdm/laporan_jadwal_piket.php

    // =========================
    // 1. Pengaturan Awal
    // =========================
    $pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

    ini_set('display_errors',1);
    ini_set('display_startup_errors',1);
    error_reporting(E_ALL);
    require_once __DIR__ . '/../helpers.php';
    start_session_safe();
    init_error_handling();
    authorize(['M:SDM']);

    require_once __DIR__ . '/../koneksi.php';
    if (ob_get_length()) ob_end_clean();

    // buat CSRF‐token
    generate_csrf_token();
    $CSRF = $_SESSION['csrf_token'];

    // =========================
    // 2. AJAX Handler
    // =========================
    if (isset($_GET['ajax']) && $_GET['ajax']=='1') {
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            $case = $_POST['case'] ?? '';
            switch ($case) {
                case 'LoadingJadwal':
                    LoadingJadwal($conn);
                    break;
                case 'DeleteJadwal':
                    DeleteJadwal($conn);
                    break;
                case 'UpdateJadwal':
                    UpdateJadwal($conn);
                    break;
                default:
                    send_response(404,'Kasus tidak ditemukan');
            }
        } else {
            send_response(405,'Metode tidak diizinkan');
        }
        exit();
    }

    // =========================
    // 3. Fungsi CRUD Jadwal
    // =========================

    function LoadingJadwal($conn) {
        $draw       = intval($_POST['draw']   ?? 0);
        $start      = intval($_POST['start']  ?? 0);
        $length     = intval($_POST['length'] ?? 10);
        // override DataTables built-in search
        $search     = trim($_POST['search']['value'] ?? '');
        $jenjang    = trim($_POST['jenjang']      ?? '');
        $role       = trim($_POST['role']         ?? '');
        $jadwal_type= $_POST['jadwal_type']       ?? '1';
        $start_year = intval($_POST['start_year'] ?? date('Y'));
        $end_year   = intval($_POST['end_year']   ?? date('Y'));

        // total records (tanpa filter)
        $resTotal = $conn->query("SELECT COUNT(*) AS total FROM jadwal_piket");
        $total    = $resTotal->fetch_assoc()['total'];

        // FROM clause
        $from = "jadwal_piket j
                LEFT JOIN anggota_sekolah a
                ON j.nip = a.nip";

        //
        // ————— Refaktorisasi filter tanggal + search + jenjang + role —————
        //
        // 1) bangun rentang tanggal (OR)
        $dateConds = [];
        $params    = [];
        $types     = '';
        if ($jadwal_type==='1') {
            for ($y=$start_year; $y<=$end_year; $y++) {
                $dateConds[] = "(j.tanggal BETWEEN ? AND ?)";
                $params[]    = "$y-06-01";
                $params[]    = "$y-07-30";
                $types      .= "ss";
            }
        } else {
            for ($y=$start_year; $y<=$end_year; $y++) {
                $n = $y + 1;
                $dateConds[] = "(j.tanggal BETWEEN ? AND ?)";
                $params[]    = "$y-12-01";
                $params[]    = "$y-12-31";
                $dateConds[] = "(j.tanggal BETWEEN ? AND ?)";
                $params[]    = "$n-01-01";
                $params[]    = "$n-01-31";
                $types      .= "ssss";
            }
        }

        // 2) kumpulkan semua WHERE clause, pertama rentang tanggal
        $whereClauses = [];
        if ($dateConds) {
            $whereClauses[] = '(' . implode(' OR ', $dateConds) . ')';
        }

        // 3) filter text search
        if ($search !== '') {
            $whereClauses[] = "(j.nama_guru LIKE ? OR j.nip LIKE ?)";
            $params[]       = "%$search%";
            $params[]       = "%$search%";
            $types         .= "ss";
        }

        // 4) filter jenjang
        if ($jenjang !== '') {
            $whereClauses[] = "j.jenjang = ?";
            $params[]       = $jenjang;
            $types         .= "s";
        }

        // 5) filter role
        if ($role !== '') {
            $whereClauses[] = "a.role = ?";
            $params[]       = $role;
            $types         .= "s";
        }

        // 6) final WHERE digabung AND
        $where = $whereClauses
            ? implode(' AND ', $whereClauses)
            : '1=1';

        // hitung filtered count
        $sqlCnt = "SELECT COUNT(*) AS total
                    FROM $from
                    WHERE $where";
        $stmt = $conn->prepare($sqlCnt);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $filtered = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        // urut & paging
    // urut & paging (telah ditambahkan a.role)
    $cols = [
        'j.id_jadwal',
        'j.nip',
        'j.nama_guru',
        'j.jenjang',
        'a.role',        // ← pastikan ini tepat
        'j.waktu_piket',
        'j.tanggal',
        'j.bulan',
        'j.status'
    ];
    
    $orderCol = intval($_POST['order'][0]['column'] ?? 5);
    $orderDir = ($_POST['order'][0]['dir'] ?? 'asc')==='asc'?'ASC':'DESC';
    $orderBy  = isset($cols[$orderCol])
                ? "ORDER BY {$cols[$orderCol]} $orderDir"
                : "ORDER BY j.tanggal ASC";

        // tambahkan LIMIT params
        $params[] = $start;
        $params[] = $length;
        $types   .= "ii";

        // ambil data
        $sql = "SELECT
        j.id_jadwal,
        j.nip,
        j.nama_guru,
        j.jenjang,
        a.role AS role,
        DATE_FORMAT(j.tanggal,'%Y-%m-%d') AS tanggal,
        j.waktu_piket,
        j.bulan,
        j.tahun,
        j.status
    FROM $from
    WHERE $where
    $orderBy
    LIMIT ?, ?";


        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rs = $stmt->get_result();

        $data = []; $no = $start + 1;
        while ($r = $rs->fetch_assoc()) {
            // badge status
            switch (strtolower($r['status'])) {
            case 'pending':
                $badge = '<span class="badge bg-warning text-dark">Pending</span>';
                break;
            case 'hadir':
                $badge = '<span class="badge bg-success">Hadir</span>';
                break;
            default:
                $badge = '<span class="badge bg-danger">Tidak Hadir</span>';
            }
            $aksi = '
    <div class="btn-group">
        <button class="btn btn-sm btn-primary btn-edit"
                data-id="'. $r['id_jadwal'] .'"
                data-tanggal="'. $r['tanggal'] .'"
                data-waktu="'. $r['waktu_piket'] .'" title="Edit">
        <i class="fas fa-edit"></i>
        </button>
        <button class="btn btn-sm btn-danger btn-delete"
                data-id="'. $r['id_jadwal'] .'" title="Hapus">
        <i class="fas fa-trash"></i>
        </button>
    </div>';
    $data[] = [
        'no'         => $no++,
        'nip'        => htmlspecialchars($r['nip']),
        'nama_guru'  => htmlspecialchars($r['nama_guru']),
        'jenjang'    => htmlspecialchars($r['jenjang']),
        'role'       => htmlspecialchars($r['role']),    // ← tambahan
        'waktu_piket'=> htmlspecialchars($r['waktu_piket']),
        'tanggal'    => htmlspecialchars($r['tanggal']),
        'bulan'      => htmlspecialchars($r['bulan'].' '.$r['tahun']),
        'status'     => $badge,
        'aksi'       => $aksi
    ];
    
        }
        $stmt->close();

        echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => $total,
        'recordsFiltered' => $filtered,
        'data'            => $data
        ], JSON_UNESCAPED_UNICODE);
    }

    function DeleteJadwal($conn){
        $id = intval($_POST['id_jadwal'] ?? 0);
        if ($id < 1) send_response(2,'ID tidak valid');
        $stmt = $conn->prepare("DELETE FROM jadwal_piket WHERE id_jadwal=?");
        $stmt->bind_param("i",$id);
        $ok = $stmt->execute(); $stmt->close();
        if ($ok) {
            add_audit_log($conn,$_SESSION['nip'],'DeleteJadwal',"ID=$id");
            send_response(0,'Jadwal terhapus');
        }
        send_response(1,'Gagal menghapus');
    }

    function UpdateJadwal($conn){
        $id  = intval($_POST['id_jadwal'] ?? 0);
        $tgl = $_POST['tanggal']     ?? '';
        $wkt = $_POST['waktu_piket'] ?? '';
        $d = DateTime::createFromFormat('Y-m-d',$tgl);
        if (!$d || $d->format('Y-m-d')!==$tgl) send_response(2,'Tanggal tidak valid');
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$wkt)) send_response(3,'Waktu tidak valid');
        $bl = $d->format('F');
        $th = $d->format('Y');
        $stmt = $conn->prepare("
        UPDATE jadwal_piket
            SET tanggal=?, waktu_piket=?, bulan=?, tahun=?
        WHERE id_jadwal=?
        ");
        $stmt->bind_param("ssssi",$tgl,$wkt,$bl,$th,$id);
        $ok = $stmt->execute(); $stmt->close();
        if ($ok) {
            add_audit_log($conn,$_SESSION['nip'],'UpdateJadwal',"ID=$id => $tgl $wkt");
            send_response(0,'Jadwal terupdate');
        }
        send_response(1,'Gagal update');
    }

    // =========================
    // 4. Tampilan Halaman
    // =========================
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
    <meta charset="UTF-8">
    <title>Laporan &amp; Manajemen Jadwal Piket</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.4.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* ===== Page Title Styling ===== */
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
.page-title i {
    color: #1976d2;
    font-size: 2.8rem;
}
        .card-header { background: linear-gradient(45deg,#0d47a1,#42a5f5); color:white; }
        .table-hover tbody tr:hover { background:#eef; }
        #loadingSpinner { display:none; position:fixed; top:50%;left:50%;transform:translate(-50%,-50%); }
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
        <i class="fas fa-calendar-alt me-2"></i>Laporan Jadwal Piket
    </h1>
            <!-- Filter Card -->
            <div class="card mb-4 shadow-sm">
            <div class="card-header"><strong><i class="fas fa-filter me-1"></i>Filter Jadwal</strong></div>
            <div class="card-body">
                <form id="filterForm" class="row gy-2 gx-3 align-items-end">
                <div class="col-auto">
                    <label class="form-label"><strong>Cari</strong></label>
                    <input type="text" id="filterSearch" class="form-control" placeholder="Nama atau NIP">
                </div>
                <div class="col-auto">
                    <label class="form-label"><strong>Jenjang</strong></label>
                    <select id="filterJenjang" class="form-select">
                    <option value="">Semua Jenjang</option>
                    <?php foreach(getOrderedJenjang($conn) as $j): ?>
                        <option value="<?= htmlspecialchars($j) ?>"><?= htmlspecialchars($j) ?></option>
                    <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label"><strong>Role</strong></label>
                    <select id="filterRole" class="form-select">
                    <option value="">Semua Role</option>
                    <option value="P">Pendidik</option>
                    <option value="TK">Tenaga Kependidikan</option>
                    <option value="M">Manajerial</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label"><strong>Jenis Jadwal</strong></label>
                    <select id="filterType" class="form-select">
                    <option value="1">Jadwal 1 (1 Juni–30 Juli)</option>
                    <option value="2">Jadwal 2 (1 Des–31 Jan)</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label"><strong>Dari Tahun</strong></label>
                    <select id="filterStart" class="form-select">
                    <?php for($y=2020;$y<=2050;$y++): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label"><strong>Sampai Tahun</strong></label>
                    <select id="filterEnd" class="form-select">
                    <?php for($y=2020;$y<=2050;$y++): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="button" id="btnApply" class="btn btn-primary"><i class="fas fa-check"></i> Terapkan</button>
                    <button type="button" id="btnReset" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</button>
                    <a href="input_jadwal_piket_guru.php" class="btn btn-success"><i class="fas fa-plus"></i> Input Jadwal</a>
                </div>
                </form>
            </div>
            </div>

            <!-- Alerts -->
            <div id="alert-placeholder"></div>

            <!-- Tabel -->
            <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 text-white"><i class="fas fa-table me-1"></i>Data Jadwal</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                <table id="jadwalTable" class="table table-sm table-bordered table-hover nowrap" style="width:100%">
                    <thead>
                    <tr>
                    <th style="width:40px; text-align:center;">No</th>
    <th>NIP</th>
    <th>Nama</th>
    <th>Jenjang</th>
    <th>Role</th>        <!-- ← tambahan -->
    <th>Waktu</th>
    <th>Tanggal</th>
    <th>Bulan</th>
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

        <footer class="sticky-footer bg-white py-2">
        <div class="container text-center"><small>&copy; <?= date('Y') ?> Payroll System</small></div>
        </footer>
    </div>
    </div>

    <!-- Modals -->

    <!-- Edit -->
    <div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="edit-form" class="needs-validation" novalidate>
        <div class="modal-content">
            <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-edit me-1"></i>Edit Jadwal</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
            <input type="hidden" name="case" value="UpdateJadwal">
            <input type="hidden" name="id_jadwal" id="edit_id">
            <div class="mb-3">
                <label class="form-label">Tanggal</label>
                <input type="date" class="form-control" name="tanggal" id="edit_tgl" required>
                <div class="invalid-feedback">Pilih tanggal valid.</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Waktu Piket</label>
                <input type="time" class="form-control" name="waktu_piket" id="edit_wkt" required>
                <div class="invalid-feedback">Pilih waktu valid.</div>
            </div>
            </div>
            <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Batal</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </div>
        </form>
    </div>
    </div>

    <!-- Delete -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="delete-form">
        <div class="modal-content">
            <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-trash me-1"></i>Hapus Jadwal</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
            <input type="hidden" name="case" value="DeleteJadwal">
            <input type="hidden" name="id_jadwal" id="delete_id">
            <p>Yakin ingin menghapus jadwal ini?</p>
            </div>
            <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Batal</button>
            <button type="submit" class="btn btn-danger"><i class="fas fa-check"></i> Ya, Hapus</button>
            </div>
        </div>
        </form>
    </div>
    </div>

    <!-- Loading Spinner -->
    <div id="loadingSpinner"><div class="spinner-border text-primary"></div></div>

    <!-- JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(function(){
    const CSRF = '<?= $CSRF ?>';
    const Toast = Swal.mixin({ toast:true, position:'top-end', timer:3000, showConfirmButton:false });

    function showToast(msg, icon='success'){
        Toast.fire({icon, title:msg});
    }

    // DataTable
    var table = $('#jadwalTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
        url: '?ajax=1',
        type: 'POST',
        data: function(d){
            // override DataTables search with our own
            d.search.value = $('#filterSearch').val();
            return $.extend(d,{
            case:         'LoadingJadwal',
            jadwal_type:  $('#filterType').val(),
            start_year:   $('#filterStart').val(),
            end_year:     $('#filterEnd').val(),
            jenjang:      $('#filterJenjang').val(),
            role:         $('#filterRole').val()
            });
        },
        beforeSend: ()=>$('#loadingSpinner').show(),
        complete:   ()=>$('#loadingSpinner').hide()
        },
        columns: [
    { data:'no',          orderable:false },
    { data:'nip' },
    { data:'nama_guru' },
    { data:'jenjang' },
    { data:'role' },         // ← tambahan
    { data:'waktu_piket' },
    { data:'tanggal' },
    { data:'bulan' },
    { data:'status',      orderable:false },
    { data:'aksi',        orderable:false }
    ],

        responsive: true,
        lengthChange: false,
        language: { url:'//cdn.datatables.net/plug-ins/1.13.4/i18n/id.json' }
    });

    $('#btnApply').click(()=>table.ajax.reload());
    $('#btnReset').click(function(){
        $('#filterForm')[0].reset();
        table.ajax.reload();
    });

    // Edit
    $('#jadwalTable').on('click','.btn-edit',function(){
        let id  = $(this).data('id'),
            tgl = $(this).data('tanggal'),
            wkt = $(this).data('waktu');
        $('#edit_id').val(id);
        $('#edit_tgl').val(tgl);
        $('#edit_wkt').val(wkt);
        new bootstrap.Modal($('#editModal')).show();
    });
    $('#edit-form').on('submit',function(e){
        e.preventDefault();
        if (!this.checkValidity()) {
        this.classList.add('was-validated');
        return;
        }
        var data = $(this).serialize() + '&csrf=' + CSRF;
        $.post('?ajax=1', data, function(resp){
        if (resp.code===0) {
            showToast(resp.result);
            $('#editModal').modal('hide');
            table.ajax.reload(null,false);
        } else {
            showToast(resp.result,'error');
        }
        },'json');
    });

    // Delete
    $('#jadwalTable').on('click','.btn-delete',function(){
        $('#delete_id').val($(this).data('id'));
        new bootstrap.Modal($('#deleteModal')).show();
    });
    $('#delete-form').on('submit',function(e){
        e.preventDefault();
        var data = $(this).serialize() + '&csrf=' + CSRF;
        $.post('?ajax=1', data, function(resp){
        if (resp.code===0) {
            showToast(resp.result);
            $('#deleteModal').modal('hide');
            table.ajax.reload(null,false);
        } else {
            showToast(resp.result,'error');
        }
        },'json');
    });

    });
    </script>
    </body>
    </html>
