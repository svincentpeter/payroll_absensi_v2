<?php
// File: /payroll_absensi_v2/payroll/keuangan/list_payroll.php

// Sertakan file helper, inisialisasi session dan CSRF token
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

// Sertakan koneksi ke database
require_once __DIR__ . '/../../koneksi.php';

// Hanya user dengan role 'keuangan' dan 'superadmin' yang diizinkan
authorize(['keuangan','superadmin'], '/payroll_absensi_v2/login.php');

// Panggil fungsi updateSalaryIndexForAll agar data salary index selalu terupdate
updateSalaryIndexForAll($conn);

/* ----------------------------------------------------------------
   BAGIAN AJAX HANDLER 
   (CheckPayrollCompletion & LogSelectMonth tetap dipertahankan)
   + Fungsi tambahan LoadPayrollOverview untuk menampilkan data 
   secara grid/paginasi manual
   ----------------------------------------------------------------*/
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    // Pastikan request menggunakan metode POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verifikasi token CSRF
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $case = isset($_POST['case']) ? trim($_POST['case']) : '';

        switch ($case) {
            case 'LoadPayrollOverview':
                LoadPayrollOverview($conn);
                break;
            case 'CheckPayrollCompletion':
                CheckPayrollCompletion($conn);
                break;
            case 'LogSelectMonth':
                LogSelectMonth($conn);
                break;
            default:
                error_log("DEBUG: Kasus tidak valid: " . $case);
                send_response(1, 'Kasus tidak valid.');
        }
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    $conn->close();
    exit();
}

/**
 * Fungsi CheckPayrollCompletion: Menghitung jumlah anggota (per jenjang)
 * yang belum memiliki payroll final pada periode yang dipilih.
 */
function CheckPayrollCompletion($conn) {
    $bulan = isset($_POST['selectedMonth']) ? intval($_POST['selectedMonth']) : 0;
    $tahun = isset($_POST['selectedYear']) ? intval($_POST['selectedYear']) : 0;
    if ($bulan <= 0 || $tahun <= 0) {
        send_response(1, 'Parameter bulan/tahun tidak valid.');
    }
    $query = "SELECT jenjang, COUNT(*) as pending 
              FROM anggota_sekolah 
              WHERE id NOT IN (
                    SELECT id_anggota FROM payroll_final WHERE bulan = ? AND tahun = ?
              ) 
              GROUP BY jenjang";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $bulan, $tahun);
    if(!$stmt->execute()){
        $error = $stmt->error;
        $stmt->close();
        send_response(1, 'Gagal menghitung payroll: ' . $error);
    }
    $result = $stmt->get_result();
    $messages = [];
    while($row = $result->fetch_assoc()){
        if (intval($row['pending']) > 0) {
            $messages[] = "Terdapat " . $row['pending'] . " anggota dengan jenjang '" . $row['jenjang'] . "' yang belum final.";
        }
    }
    $stmt->close();

    if(empty($messages)){
        send_response(0, ['complete' => true, 'messages' => []]);
    } else {
        send_response(0, ['complete' => false, 'messages' => $messages]);
    }
}

/**
 * Fungsi LogSelectMonth: Mencatat (audit log) pilihan bulan payroll oleh user.
 */
function LogSelectMonth($conn) {
    $selectedMonth = isset($_POST['selectedMonth']) ? intval($_POST['selectedMonth']) : 0;
    $selectedYear  = isset($_POST['selectedYear']) ? intval($_POST['selectedYear']) : 0;
    $user_nip = $_SESSION['nip'] ?? '';
    $details_log = "User dengan NIP $user_nip memilih bulan payroll: $selectedMonth/$selectedYear";
    add_audit_log($conn, $user_nip, 'SelectPayrollMonth', $details_log);
    send_response(0, 'Logged');
}

/**
 * Fungsi LoadPayrollOverview:
 * Mengambil data payroll (yang masih 'draft' & belum di-final) secara AJAX
 * lalu di-render di sisi klien dalam bentuk grid.
 */
function LoadPayrollOverview($conn) {
    // Pastikan token
    verify_csrf_token($_POST['csrf_token'] ?? '');

    // Ambil parameter paginasi
    $start  = isset($_POST['start'])  ? intval($_POST['start'])  : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;

    // Ambil filter dari POST
    $filterMonth   = isset($_POST['filterMonth'])   ? intval($_POST['filterMonth']) : date("n");
    $filterYear    = isset($_POST['filterYear'])    ? intval($_POST['filterYear'])  : date("Y");
    $filterJenjang = isset($_POST['filterJenjang']) ? sanitize_input($_POST['filterJenjang']) : '';
    $filterRole    = isset($_POST['filterRole'])    ? sanitize_input($_POST['filterRole'])    : '';
    $search        = isset($_POST['search'])        ? sanitize_input($_POST['search'])        : '';

    // Query untuk menghitung total data (tanpa LIMIT)
    $countQuery = "
        SELECT COUNT(*) as total
        FROM payroll p
        JOIN anggota_sekolah a ON p.id_anggota = a.id
        LEFT JOIN salary_indices si ON a.salary_index_id = si.id
        WHERE p.bulan = ? 
          AND p.tahun = ?
          AND p.status = 'draft'
          AND NOT EXISTS (
                SELECT 1 FROM payroll_final pf
                WHERE pf.id_anggota = p.id_anggota
                  AND pf.bulan = p.bulan
                  AND pf.tahun = p.tahun
          )
    ";

    // Susun dynamic WHERE (untuk filter)
    $whereClause = "";
    $params = [$filterMonth, $filterYear];
    $types  = "ii";

    if (!empty($filterJenjang)) {
        $whereClause .= " AND a.jenjang = ? ";
        $params[] = $filterJenjang;
        $types   .= "s";
    }
    if (!empty($filterRole)) {
        $whereClause .= " AND a.role = ? ";
        $params[] = $filterRole;
        $types   .= "s";
    }
    if (!empty($search)) {
        // Pencarian ke nama, nip, dsb.
        $whereClause .= " AND ( a.nama LIKE ? OR a.nip LIKE ? ) ";
        $searchLike   = "%$search%";
        $params[] = $searchLike;
        $params[] = $searchLike;
        $types   .= "ss";
    }

    // Hitung total (recordsTotal)
    $stmtCount = $conn->prepare($countQuery . $whereClause);
    if (!$stmtCount) {
        send_response(1, 'Gagal prepare query count: '.$conn->error);
    }
    $stmtCount->bind_param($types, ...$params);
    $stmtCount->execute();
    $resCount = $stmtCount->get_result();
    $rowCount = $resCount->fetch_assoc();
    $recordsTotal = intval($rowCount['total']);
    $stmtCount->close();

    // Query untuk ambil data (dengan LIMIT)
    $dataQuery = "
        SELECT 
            p.id AS id_payroll,
            p.bulan,
            p.tahun,
            p.tgl_payroll,
            p.status,
            a.id AS id_anggota,
            a.nama,
            a.nip,
            a.jenjang,
            a.role,
            si.level AS salary_index_level
        FROM payroll p
        JOIN anggota_sekolah a ON p.id_anggota = a.id
        LEFT JOIN salary_indices si ON a.salary_index_id = si.id
        WHERE p.bulan = ?
          AND p.tahun = ?
          AND p.status = 'draft'
          AND NOT EXISTS (
                SELECT 1 FROM payroll_final pf
                WHERE pf.id_anggota = p.id_anggota
                  AND pf.bulan = p.bulan
                  AND pf.tahun = p.tahun
          )
    ";
    $dataQuery .= $whereClause;
    $dataQuery .= " ORDER BY p.tgl_payroll DESC ";
    $dataQuery .= " LIMIT ?, ? ";

    // Tambahkan start & length
    $params[] = $start;
    $params[] = $length;
    $types   .= "ii";

    $stmtData = $conn->prepare($dataQuery);
    if (!$stmtData) {
        send_response(1, 'Gagal prepare query data: '.$conn->error);
    }
    $stmtData->bind_param($types, ...$params);
    $stmtData->execute();
    $result = $stmtData->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        // Dapatkan foto profil (jika ada)
        $fotoProfil = getProfilePhotoUrl($row['nama'], $row['jenjang'], $row['role'], $row['id_anggota']);

        $rows[] = [
            'id_payroll'  => $row['id_payroll'],
            'id_anggota'  => $row['id_anggota'],
            'nama'        => $row['nama'],
            'nip'         => $row['nip'],
            'jenjang'     => $row['jenjang'],
            'role'        => $row['role'],
            'bulan'       => $row['bulan'],
            'tahun'       => $row['tahun'],
            'status'      => $row['status'],
            'tgl_payroll' => $row['tgl_payroll'],
            'salary_index_level' => $row['salary_index_level'] ?: '-',
            'foto_profil' => $fotoProfil,
        ];
    }
    $stmtData->close();

    echo json_encode([
        'recordsTotal' => $recordsTotal,
        'data'         => $rows
    ]);
    exit();
}

/* ----------------------------------------------------------------
   BAGIAN HALAMAN UTAMA (TETAP DIPERTAHANKAN)
   ----------------------------------------------------------------*/

// Ambil filter dari GET (default: bulan & tahun sekarang)
$filterMonth   = isset($_GET['filterMonth']) ? intval($_GET['filterMonth']) : date("n");
$filterYear    = isset($_GET['filterYear'])  ? intval($_GET['filterYear'])  : date("Y");
$filterJenjang = isset($_GET['filterJenjang']) ? sanitize_input($_GET['filterJenjang']) : '';
$filterRole    = isset($_GET['filterRole'])    ? sanitize_input($_GET['filterRole'])    : '';

// Logging akses halaman overview payroll
$user_nip = $_SESSION['nip'] ?? '';
error_log("DEBUG: User dengan NIP $user_nip mengakses Payroll Overview untuk periode: $filterMonth/$filterYear");
$details_log = "User dengan NIP $user_nip melihat overview payroll untuk periode: $filterMonth/$filterYear";
add_audit_log($conn, $user_nip, 'ViewPayrollOverview', $details_log);

// Ambil data untuk dropdown filter jenjang dan role
$jenjangOptions = [];
$stmtJenjang = $conn->prepare("SELECT DISTINCT jenjang FROM anggota_sekolah WHERE jenjang IS NOT NULL AND jenjang != '' ORDER BY jenjang ASC");
if ($stmtJenjang) {
    $stmtJenjang->execute();
    $resJenjang = $stmtJenjang->get_result();
    while ($row = $resJenjang->fetch_assoc()){
        $jenjangOptions[] = $row['jenjang'];
    }
    $stmtJenjang->close();
}

$roleOptions = [];
$stmtRole = $conn->prepare("SELECT DISTINCT role FROM anggota_sekolah WHERE role IS NOT NULL AND role != '' ORDER BY role ASC");
if ($stmtRole) {
    $stmtRole->execute();
    $resRole = $stmtRole->get_result();
    while ($row = $resRole->fetch_assoc()){
        $roleOptions[] = $row['role'];
    }
    $stmtRole->close();
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Payroll Overview - Keuangan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- CSS Bootstrap 5, SB Admin 2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- SweetAlert2 CSS (opsional) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body { color: #000; }
        .breadcrumb { background: none; }
        .card-header { background: #4e73df; color: white; }
        .processed-month {
            background: #343a40 !important;
            color: #fff !important;
            pointer-events: none;
            border: 1px solid #343a40;
        }
        /* Grid styling */
        #payrollCards .col {
            display: flex;
        }
        #payrollCards .card {
            flex: 1;
        }
        .employee-photo {
            width: 60px; 
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 10px;
        }
        .badge-status {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        .badge-status.draft  { background-color: #17a2b8; color: #fff; }
        .badge-status.final  { background-color: #28a745; color: #fff; }
        .badge-status.revisi { background-color: #ffc107; color: #000; }
        .badge-status.default { background-color: #6c757d; color: #fff; }

        /* Spinner (loading) */
        #loadingSpinner {
            display: none;
            margin-left: 10px;
        }
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }
    </style>
</head>
<body id="page-top">
<div id="wrapper">
    <?php include __DIR__ . '/../../sidebar.php'; ?>
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <?php include __DIR__ . '/../../navbar.php'; ?>
            <?php 
                // Jika punya breadcrumb.php, sertakan
                include __DIR__ . '/../../breadcrumb.php'; 
                ?>
            <div class="container-fluid">
                

                <!-- Header: Tampilkan periode payroll yang terpilih (dengan tampilan card) -->
                <div id="selectedMonthDisplay" class="mb-3" style="cursor: pointer;">
                    <div class="card mb-3">
                        <div class="card-body d-flex align-items-center">
                            <i class="bi bi-calendar3 me-2"></i>
                            <span class="fw-bold">
                                Payroll Bulan: <?= date("F", mktime(0,0,0,$filterMonth,1)) . " " . $filterYear; ?>
                            </span>
                            <button id="btnChangeCalendar" class="btn btn-link ms-auto">
                                <i class="bi bi-pencil-square"></i> Ganti Kalender
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filter Form: Jenjang dan Role (ganti DataTables filter => pakai AJAX load) -->
                <div class="card mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
    <h6 class="m-0 fw-bold text-white">
      <i class="fas fa-search"></i> Filter Payroll
    </h6>
  </div>

                    <div class="card-body">
                        <form id="filterForm" class="row align-items-center">
                            <!-- Kita tetap simpan csrf_token di form -->
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">

                            <!-- (Optional) Kita bisa simpan month/year di hidden input, 
                                 tapi di contoh ini kita passing via JavaScript saja -->
                            
                            <div class="form-group mb-2 col-auto">
                                <label for="filterJenjang" class="me-2">Jenjang Pendidikan:</label>
                                <select class="form-control" id="filterJenjang">
                                    <option value="">Semua Jenjang</option>
                                    <?php foreach ($jenjangOptions as $jenjang): ?>
                                        <option value="<?= htmlspecialchars($jenjang); ?>"
                                            <?= ($filterJenjang === $jenjang) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($jenjang); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group mb-2 col-auto">
                                <label for="filterRole" class="me-2">Role:</label>
                                <select class="form-control" id="filterRole">
                                    <option value="">Semua Role</option>
                                    <?php foreach ($roleOptions as $role): ?>
                                        <option value="<?= htmlspecialchars($role); ?>"
                                            <?= ($filterRole === $role) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($role); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group mb-2 col-auto">
                                <label for="filterSearch" class="me-2">Pencarian:</label>
                                <input type="text" class="form-control" id="filterSearch" placeholder="Cari nama / nip...">
                            </div>
                            <div class="form-group mb-2 col-auto">
                                <button type="button" class="btn btn-primary mb-2 me-2" id="btnApplyFilter">
                                    <i class="fas fa-filter"></i> Terapkan Filter
                                </button>
                                <button type="button" class="btn btn-secondary mb-2" id="btnResetFilter">
                                    <i class="fas fa-undo"></i> Reset Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Grid Container -->
                <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
    <h6 class="m-0 fw-bold text-white">
      <i class="fas fa-file-invoice-dollar"></i> Daftar Payroll
    </h6>
                        <div class="spinner-border text-light ms-auto" role="status" id="loadingSpinner">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Di sinilah grid card akan ditampilkan -->
                        <div id="payrollCards" class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
                            <!-- Card payroll akan di-generate via JS -->
                        </div>
                        <!-- Pagination manual -->
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center" id="paginationContainer">
                                <!-- Link pagination di-generate via JS -->
                            </ul>
                        </nav>
                    </div>
                </div>
                <!-- End Grid Container -->
            </div> <!-- End .container-fluid -->
        </div> <!-- End #content -->

        <footer class="sticky-footer bg-white">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span>&copy; <?= date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                </div>
            </div>
        </footer>
    </div> <!-- End #content-wrapper -->
</div> <!-- End #wrapper -->

<!-- MODAL: Select Month (Payroll) -->
<div class="modal fade" id="SalaryMonthModal" tabindex="-1" aria-labelledby="salaryMonthModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" style="max-width: 600px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="salaryMonthModalLabel"><i class="fa fa-calendar"></i> Pilih Bulan untuk Payroll</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="row text-center">
                <?php
                $currentYear  = date('Y');
                $currentMonth = date('n');
                $startMonth   = $currentMonth - 2;
                $startYear    = $currentYear;
                for ($i = 0; $i < 16; $i++) {
                    $month = $startMonth + $i;
                    $year  = $startYear;
                    if ($month <= 0) {
                        $month += 12;
                        $year  -= 1;
                    } elseif ($month > 12) {
                        $month -= 12;
                        $year  += 1;
                    }
                    // Highlight untuk periode terpilih
                    $highlight = ($month == $filterMonth && $year == $filterYear) 
                                 ? 'bg-warning text-dark fw-bold' 
                                 : 'bg-light';
                    echo '<div class="col-3 mb-3">';
                    echo '  <div class="p-2 ' . $highlight . '" style="border: 1px solid #ddd; border-radius: 5px;">';
                    echo '    <a href="#" class="month-link" data-month-number="' . $month . '" 
                                   data-month="' . date("F", mktime(0, 0, 0, $month, 1)) . '" 
                                   data-year="' . $year . '" 
                                   style="color: inherit; text-decoration: none;">';
                    echo '      ' . strtoupper(date("F", mktime(0, 0, 0, $month, 1))) . '<br>' . $year;
                    echo '    </a>';
                    echo '  </div>';
                    echo '</div>';
                }
                ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JS Dependencies -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function(){
    // Toast ringkas dengan SweetAlert2
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
    });
    function showToast(message, icon='success') {
        Toast.fire({ icon: icon, title: message });
    }

    // Paginasi manual
    let currentPage = 1;
    let pageSize    = 8; // jumlah card per halaman

    // Fungsi load data payroll (AJAX)
    function loadPayroll(page) {
        currentPage = page;
        let start = (currentPage - 1) * pageSize;

        $.ajax({
            url: "list_payroll.php?ajax=1",
            type: "POST",
            data: {
                case: "LoadPayrollOverview",
                start: start,
                length: pageSize,
                // Gunakan filterMonth, filterYear dari server (PHP) => 
                // atau boleh disimpan di localStorage, dsb.
                filterMonth: <?= $filterMonth ?>,
                filterYear:  <?= $filterYear ?>,
                filterJenjang: $("#filterJenjang").val(),
                filterRole:   $("#filterRole").val(),
                search:       $("#filterSearch").val(),
                csrf_token: "<?= htmlspecialchars($csrf_token); ?>"
            },
            dataType: "json",
            beforeSend: function(){
                $("#loadingSpinner").show();
            },
            success: function(resp){
                $("#loadingSpinner").hide();
                if(resp.data) {
                    generateCards(resp.data);
                    generatePagination(resp.recordsTotal);
                } else {
                    $("#payrollCards").empty();
                    $("#paginationContainer").empty();
                }
            },
            error: function(xhr, status, error) {
                $("#loadingSpinner").hide();
                showToast("Terjadi kesalahan memuat data: " + error, "error");
            }
        });
    }

    // Fungsi men-generate card
    function generateCards(data) {
        let container = $("#payrollCards");
        container.empty();

        data.forEach(function(item){
            let statusBadge = 'default';
            let st = (item.status || '').toLowerCase();
            if(st === 'draft')  statusBadge = 'draft';
            if(st === 'final')  statusBadge = 'final';
            if(st === 'revisi') statusBadge = 'revisi';

            let photoUrl = (item.foto_profil && item.foto_profil !== '')
                    ? item.foto_profil
                    : '<?= BASE_URL ?>/assets/img/undraw_profile.svg';

            // Link "Review" diarahkan ke manage-salary.php
            let reviewUrl = `manage-salary.php?id_anggota=${item.id_anggota}&bulan=${item.bulan}&tahun=${item.tahun}`;

            let cardHtml = `
              <div class="col">
                <div class="card shadow-sm p-3 h-100 text-center">
                  <img src="${photoUrl}" alt="Foto" class="employee-photo mb-2">
                  <h6 class="mb-0">${item.nama}</h6>
                  <small class="text-muted">NIP: ${item.nip}</small>
                  <p class="mt-2 mb-1" style="font-size:0.85rem;">
                    Role: ${item.role} | <strong>${item.jenjang}</strong>
                  </p>
                  <p style="font-size:0.85rem;">Status: 
                     <span class="badge-status ${statusBadge}">
                        ${item.status}
                     </span>
                  </p>
                  <p style="font-size:0.8rem;">
                    Periode: ${item.bulan}/${item.tahun}
                  </p>
                  <a href="${reviewUrl}" class="btn btn-sm btn-warning mt-2">
                    <i class="bi bi-eye-fill"></i> Review
                  </a>
                </div>
              </div>
            `;
            container.append(cardHtml);
        });
    }

    // Fungsi generate pagination
    function generatePagination(totalRecords) {
        let totalPages = Math.ceil(totalRecords / pageSize);
        let pagination = $("#paginationContainer");
        pagination.empty();

        for (let i = 1; i <= totalPages; i++) {
            let li = $("<li>").addClass("page-item").append(
                $("<a>").addClass("page-link").text(i).attr("href", "#").on("click", function(e){
                    e.preventDefault();
                    loadPayroll(i);
                })
            );
            if (i === currentPage) {
                li.addClass("active");
            }
            pagination.append(li);
        }
    }

    // Panggil loadPayroll pertama kali
    loadPayroll(1);

    // Filter (Apply & Reset)
    $("#btnApplyFilter").on("click", function(){
        loadPayroll(1);
    });
    $("#btnResetFilter").on("click", function(){
        $("#filterJenjang").val("");
        $("#filterRole").val("");
        $("#filterSearch").val("");
        loadPayroll(1);
    });

    // Modal "Ganti Kalender" => menampilkan SalaryMonthModal
    $("#btnChangeCalendar, #selectedMonthDisplay").on("click", function(e){
        e.preventDefault();
        $("#SalaryMonthModal").modal("show");
    });

    // Klik pilihan bulan di modal => cek payroll completion => logSelectMonth => redirect
    $(document).on("click", ".month-link", function(e){
        e.preventDefault();
        let monthNumber = $(this).data('month-number');
        let monthName   = $(this).data('month');
        let year        = $(this).data('year');

        // Lakukan AJAX ke endpoint CheckPayrollCompletion
        $.ajax({
            url: 'list_payroll.php?ajax=1',
            type: 'POST',
            dataType: 'json',
            data: { 
                case: 'CheckPayrollCompletion', 
                selectedMonth: monthNumber, 
                selectedYear: year, 
                csrf_token: '<?= htmlspecialchars($csrf_token); ?>'
            },
            success: function(resp) {
                if(resp.code === 0) {
                    if(resp.result.complete === false) {
                        var messages = resp.result.messages;
                        var messageText = messages.join("<br>");
                        Swal.fire({
                            title: 'Perhatian!',
                            html: messageText + "<br><br>Apakah Anda tetap ingin memilih bulan ini?",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Ya, pilih bulan ini',
                            cancelButtonText: 'Batal'
                        }).then((result) => {
                            if(result.isConfirmed) {
                                // Catat pilihan bulan
                                logSelectMonth(monthNumber, year, monthName);
                            }
                        });
                    } else {
                        // Jika payroll sudah complete, langsung redirect
                        logSelectMonth(monthNumber, year, monthName);
                    }
                } else {
                    Swal.fire('Error', resp.result, 'error');
                }
            },
            error: function(xhr, status, error) {
                Swal.fire('Error', 'Terjadi kesalahan: ' + error, 'error');
            }
        });
    });

    function logSelectMonth(monthNumber, year, monthName) {
        // Catat log
        $.ajax({
            url: 'list_payroll.php?ajax=1',
            type: 'POST',
            data: { 
                case: 'LogSelectMonth', 
                selectedMonth: monthNumber, 
                selectedYear: year, 
                csrf_token: '<?= htmlspecialchars($csrf_token); ?>'
            }
        });
        // Redirect dengan parameter filter baru
        let baseUrl = 'list_payroll.php?filterMonth=' + monthNumber + '&filterYear=' + year;
        window.location.href = baseUrl;
    }

});
</script>
</body>
</html>
<?php
$conn->close();
?>
