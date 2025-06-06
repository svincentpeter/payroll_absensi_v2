<?php
// File: /payroll_absensi_v2/sdm/employees.php
$pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();

generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

require_once __DIR__ . '/../koneksi.php';
authorize(['M:SDM', 'M:Superadmin'], '/payroll_absensi_v2/login.php');

// ======= MODULARISASI: load semua fungsi dari includes =======
require_once __DIR__ . '/includes/employees_core.php';
require_once __DIR__ . '/includes/employees_payroll.php';
require_once __DIR__ . '/includes/employees_payhead.php';
require_once __DIR__ . '/includes/employees_rekap.php';

// Get Jenjang list (untuk filter/form)
$jenjangList = getOrderedJenjang($conn);

// Bagian: Parameter Bulan/Tahun (untuk payroll)
$selectedMonth = isset($_GET['filterMonth']) ? intval($_GET['filterMonth']) : date('n');
$selectedYear  = isset($_GET['filterYear'])  ? intval($_GET['filterYear'])  : date('Y');

/* =========================
   AJAX HANDLER (TETAP SAMA)
   ========================= */
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $case = trim($_POST['case'] ?? '');
        switch ($case) {
            case 'LoadingEmployees':
                LoadingEmployees($conn);
                break;
            case 'EditEmployee':
                EditEmployee($conn);
                break;
            case 'ViewEmployeeDetail':
                ViewEmployeeDetail($conn);
                break;
            case 'GetPayheadById':
                GetPayheadById($conn);
                break;
            case 'GetAllPayheads':
                GetAllPayheads($conn);
                break;
            case 'AssignPayheadsToEmployee':
                AssignPayheadsToEmployee($conn);
                break;
            case 'ProcessPayroll':
                ProcessPayroll($conn);
                break;
            case 'CheckPayrollCompletion':
                CheckPayrollCompletion($conn);
                break;
            case 'ViewRekapAbsensi':
                ViewRekapAbsensi($conn);
                break;
            case 'EditRekapAbsensi':
                EditRekapAbsensi($conn);
                break;

            case 'RemovePayrollFile':
                RemovePayrollFile($conn);
                break;

            default:
                send_response(1, 'Kasus tidak valid.');
        }
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan (harus POST).');
    }
    exit();
}



// Ambil data "bulan yg sudah final" (untuk modal)
$processedMonths = [];
$resTotal = $conn->query("SELECT COUNT(*) as total FROM anggota_sekolah");
$totalAnggota = $resTotal ? intval($resTotal->fetch_assoc()['total']) : 0;
$sqlMon = "
  SELECT `bulan`,
         `tahun`,
         COUNT(DISTINCT `id_anggota`) AS completed
    FROM `payroll`
   WHERE `status` = 'final'
GROUP BY `bulan`, `tahun`
";

$resMon = $conn->query($sqlMon);
if ($resMon) {
    while ($rm = $resMon->fetch_assoc()) {
        if (intval($rm['completed']) === $totalAnggota) {
            $processedMonths[] = [
                'bulan' => intval($rm['bulan']),
                'tahun' => intval($rm['tahun'])
            ];
        }
    }
}

// Kenaikan gaji tahunan (untuk modal detail, jika diperlukan)
$kgData = null;
if (isset($_GET['empcode']) && intval($_GET['empcode']) > 0) {
    $empId = intval($_GET['empcode']);
    $stmtKg = $conn->prepare("SELECT * FROM kenaikan_gaji_tahunan WHERE id_anggota = ? ORDER BY tanggal_mulai DESC LIMIT 1");
    if ($stmtKg) {
        $stmtKg->bind_param("i", $empId);
        $stmtKg->execute();
        $resKg = $stmtKg->get_result();
        if ($resKg && $resKg->num_rows > 0) {
            $kgData = $resKg->fetch_assoc();
        }
        $stmtKg->close();
    }
}

// ======== Mulai HTML view seperti biasa (tanpa perubahan) ========
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Manajemen Anggota - Payroll System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        /* ─── ROOT VARS & CARD OVERRIDES ───────────────────────── */
        :root {
            --primary-gradient: linear-gradient(135deg, #3a7bd5 0%, #00d2ff 100%);
            --secondary-gradient: linear-gradient(to right, #4e54c8, #8f94fb);
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --card-hover-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        /* ===== Page Title Styling ===== */
        .page-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 2.5rem;
            color: #0d47a1;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
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

        .employee-card {
            /* kita hanya styling tambahan, biar tetap ada .card bootstrap */
            border-radius: .75rem;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .employee-photo {
            width: 100px !important;
            height: 100px !important;
            object-fit: cover;
            border-radius: 50%;
            margin: 1rem auto 0;
            border: 3px solid #fff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .employee-card .card-body {
            display: flex;
            flex-direction: column;
        }

        .employee-card .card-title {
            font-size: 1.25rem;
            margin-bottom: .25rem;
        }

        .employee-card .card-text {
            margin-bottom: .5rem;
        }

        .employee-card .badges,
        .employee-card .status,
        .employee-card .rapel {
            margin-bottom: .5rem;
        }

        .employee-card .btn {
            font-size: .9rem;
            padding: .5rem;
        }

        .card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: var(--card-shadow);
        }

        /* ─── GENERIC BUTTON OVERRIDES ───────────────────────── */
        .btn {
            border-radius: 8px;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            color: #fff;
        }

        /* ─── BODY & TEXT COLORS ─────────────────────────────── */
        body {
            background: #f7f9fc;
        }

        body,
        .text-gray-800 {
            color: #000 !important;
        }

        /* ─── HEADER PERIODE (SELECTED MONTH) ─────────────────── */
        #selectedMonthDisplay {
            cursor: pointer;
        }

        #btnChangeCalendar {
            background: #fff;
            border: 1px solid #4e73df;
            /* bootstrap primary */
            color: #4e73df;
            transition: all .2s;
        }

        #btnChangeCalendar:hover {
            color: #0056b3;
            box-shadow: 0 .2rem .4rem rgba(0, 0, 0, 0.1);
        }

        /* ─── CARD HEADER UTAMA ───────────────────────────────── */
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }

        /* ─── GRID ANGGOTA & FOTO ────────────────────────────── */
        #employeeCards {
            margin-top: 20px;
        }

        #employeeCards .col {
            display: flex;
        }

        #employeeCards .card {
            flex: 1;
        }

        .employee-photo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 10px;
        }

        /* ─── BADGE STATUS ───────────────────────────────────── */
        .badge-status {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .badge-status.final {
            background-color: #28a745;
            color: #fff;
        }

        .badge-status.draft {
            background-color: #17a2b8;
            color: #fff;
        }

        .badge-status.revisi {
            background-color: #ffc107;
            color: #000;
        }

        .badge-status.default {
            background-color: #6c757d;
            color: #fff;
        }

        /* ─── LOADING & SPINNER ─────────────────────────────── */
        .spinner-border {
            display: none;
            margin-left: 5px;
        }

        #loadingSpinner {
            display: none;
            position: fixed;
            z-index: 9999;
            height: 100px;
            width: 100px;
            margin: auto;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
        }

        /* ─── MODAL SCROLLABLE ───────────────────────────────── */
        .modal-dialog-scrollable {
            max-height: 90vh;
        }

        /* ─── PROCESSED MONTH BADGE ──────────────────────────── */
        .processed-month {
            background: #343a40 !important;
            color: #fff !important;
            pointer-events: none;
            border: 1px solid #343a40;
        }
    </style>

    <script>
        const CSRF_TOKEN = '<?= htmlspecialchars($csrf_token); ?>';
    </script>
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
                        <i class="bi bi-people-fill"></i>
                        Payroll Anggota
                    </h1>
                    <!-- Header periode -->
                    <div id="selectedMonthDisplay" class="mb-3">
                        <div class="card mb-3 border-0 shadow-sm">
                            <div class="card-body d-flex align-items-center py-3">
                                <i class="bi bi-calendar3 me-2 fs-4 text-primary"></i>
                                <span class="fw-bold fs-5">
                                    Payroll Bulan: <?= date("F", mktime(0, 0, 0, $filterMonth, 1)) . ' ' . $filterYear ?>
                                </span>
                                <button id="btnChangeCalendar" class="btn btn-outline-primary ms-auto">
                                    <i class="bi bi-pencil-square me-1"></i> Ganti Periode
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="alert-placeholder"></div>
                    <!-- Filter Anggota -->
                    <div class="card mb-4 shadow">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-white">
                                <i class="bi bi-filter-square-fill"></i> Filter Anggota
                            </h6>
                        </div>
                        <div class="card-body" style="background-color: #f8f9fa;">
                            <form id="filterForm" method="GET" class="row gy-2 gx-3 align-items-center">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                <!-- Jenjang Pendidikan -->
                                <div class="col-auto">
                                    <label for="filterJenjang" class="form-label mb-0"><strong>Jenjang Pendidikan:</strong></label>
                                    <select class="form-control" id="filterJenjang" name="jenjang">
                                        <option value="">Semua Jenjang</option>
                                        <?php
                                        $jenjangList = getOrderedJenjang($conn); // array: ['TK'=>'Taman Kanak-Kanak', ...]
                                        foreach ($jenjangList as $kode_jenjang => $nama_jenjang) {
                                            echo '<option value="' . htmlspecialchars($kode_jenjang) . '">' . htmlspecialchars($nama_jenjang) . '</option>';
                                        }
                                        ?>

                                    </select>
                                </div>
                                <!-- Role -->
                                <div class="col-auto">
                                    <label for="filterRole" class="form-label mb-0"><strong>Role:</strong></label>
                                    <select class="form-control" id="filterRole" name="role">
                                        <option value="">Semua Role</option>
                                        <?php
                                        $stmtRole = $conn->prepare("
                                            SELECT DISTINCT role
                                            FROM anggota_sekolah
                                            WHERE role IS NOT NULL AND role != ''
                                            ORDER BY role ASC
                                            ");
                                        if ($stmtRole) {
                                            $stmtRole->execute();
                                            $resRole = $stmtRole->get_result();
                                            while ($row = $resRole->fetch_assoc()) {
                                                echo '<option value="' . htmlspecialchars($row['role']) . '">'
                                                    . htmlspecialchars($row['role'])
                                                    . '</option>';
                                            }
                                            $stmtRole->close();
                                        } else {
                                            echo '<option value="">Tidak ada data</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <!-- Pencarian -->
                                <div class="col-auto">
                                    <label for="filterSearch" class="form-label mb-0"><strong>Pencarian:</strong></label>
                                    <input type="text" class="form-control" id="filterSearch" name="search" placeholder="Cari nama / nip...">
                                </div>
                                <!-- Tombol -->
                                <div class="col-auto d-flex align-items-end">
                                    <button type="button" id="btnApplyFilter" class="btn btn-primary me-2">
                                        <i class="fas fa-filter"></i> Terapkan Filter
                                    </button>
                                    <button type="button" id="btnResetFilter" class="btn btn-secondary">
                                        <i class="fas fa-undo"></i> Reset Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- End Filter Anggota -->

                    <!-- Grid Card Container -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex align-items-center">
                            <h6 class="m-0 fw-bold text-white"><i class="fas fa-clipboard-list"></i> Daftar Anggota Sekolah</h6>
                            <div class="spinner-border text-light ms-auto" role="status" id="loadingSpinner">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="employeeCards" class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-4"></div>
                            <!-- Pagination manual -->
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center" id="paginationContainer"></ul>
                            </nav>
                        </div>
                    </div>
                </div><!-- /.container-fluid -->
            </div><!-- /#content -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?= date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div><!-- /#content-wrapper -->
    </div><!-- /#wrapper -->

    <!-- MODAL: Edit anggota -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel" aria-hidden="true">
        <!-- Gunakan modal-xl untuk ukuran ekstra besar, plus modal-dialog-centered agar modal tampil di tengah layar -->
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <form id="editEmployeeForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editEmployeeModalLabel">
                            <i class="bi bi-pencil-square"></i> Update No Rekening Anggota
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Hidden inputs -->
                        <input type="hidden" name="case" value="EditEmployee">
                        <input type="hidden" name="id" id="editEmployeeId">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">

                        <div class="container-fluid">
                            <!-- Row 1 -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="editNip" class="form-label">NIP</label>
                                    <input type="text" name="nip" id="editNip" class="form-control" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label for="editNama" class="form-label">Nama</label>
                                    <input type="text" name="nama" id="editNama" class="form-control" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label for="editJenjang" class="form-label">Jenjang Pendidikan</label>
                                    <input type="text" name="'jenjang'" id="editJenjang" class="form-control" readonly>
                                </div>
                            </div>
                            <!-- Row 2 -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="editJobTitle" class="form-label">Job Title</label>
                                    <input type="text" name="job_title" id="editJobTitle" class="form-control" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label for="editStatusKerja" class="form-label">Status Kerja</label>
                                    <select name="status_kerja" id="editStatusKerja" class="form-control" disabled>
                                        <option value="">---Pilih Status---</option>
                                        <option value="Tetap">Tetap</option>
                                        <option value="Kontrak">Kontrak</option>
                                        <option value="Paruh Waktu">Paruh Waktu</option>
                                        <option value="Magang">Magang</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="editNoRekening" class="form-label">
                                        No Rekening <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="no_rekening" id="editNoRekening" class="form-control" required>
                                </div>
                            </div>
                            <!-- Row 3 -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="editEmail" class="form-label">Email</label>
                                    <input type="email" name="email" id="editEmail" class="form-control" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label for="editJenisKelamin" class="form-label">Jenis Kelamin</label>
                                    <select name="jenis_kelamin" id="editJenisKelamin" class="form-control" disabled>
                                        <option value="">---</option>
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="editAgama" class="form-label">Agama</label>
                                    <input type="text" name="agama" id="editAgama" class="form-control" readonly>
                                </div>
                            </div>
                        </div> <!-- /.container-fluid -->
                    </div> <!-- /.modal-body -->

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: View Detail Anggota -->
    <div class="modal fade" id="viewDetailModal" tabindex="-1" aria-labelledby="viewDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewDetailModalLabel">
                        <i class="bi bi-eye-fill"></i> Detail Anggota
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body" style="color: #000;">

                    <!-- SECTION: Foto Profil & KTP -->
                    <div class="alert alert-primary fw-bold mb-3" role="alert">
                        Foto Profil & KTP
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-6 text-center">
                            <img id="detailFotoProfil"
                                src="<?= getBaseUrl() ?>/assets/img/undraw_profile.svg"
                                alt="Foto Profil"
                                style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #1976d2;">
                            <div class="small text-muted mt-2">Foto Profil</div>
                        </div>
                        <div class="col-md-6 text-center">
                            <img id="detailFotoKtp"
                                src="<?= getBaseUrl() ?>/assets/img/ktp_placeholder.png"
                                alt="Foto KTP"
                                style="width: 180px; height: 135px; border-radius: 10px; object-fit: cover; border: 3px solid #1976d2;">
                            <div class="small text-muted mt-2">Foto KTP</div>
                        </div>
                    </div>

                    <!-- SECTION: Data Pekerjaan -->
                    <div class="alert alert-primary fw-bold mb-3" role="alert">
                        Data Pekerjaan
                    </div>
                    <table class="table table-sm mb-4">
                        <tr>
                            <th>ID</th>
                            <td id="detailId"></td>
                        </tr>
                        <tr>
                            <th>UID</th>
                            <td id="detailUid"></td>
                        </tr>
                        <tr>
                            <th>NIP</th>
                            <td id="detailNip"></td>
                        </tr>
                        <tr>
                            <th>Nama</th>
                            <td id="detailNama"></td>
                        </tr>
                        <tr>
                            <th>Jenjang Pendidikan</th>
                            <td id="detailJenjang"></td>
                        </tr>
                        <tr id="detailUnitPenempatanRow">
                            <th>Unit Penempatan</th>
                            <td id="detailUnitPenempatan"></td>
                        </tr>
                        <tr>
                            <th>Job Title</th>
                            <td id="detailJobTitle"></td>
                        </tr>
                        <tr>
                            <th>Role</th>
                            <td id="detailRole"></td>
                        </tr>
                        <tr>
                            <th>Status Kerja</th>
                            <td id="detailStatusKerja"></td>
                        </tr>
                        <tr>
                            <th>Tanggal Bergabung</th>
                            <td id="detailJoinStart"></td>
                        </tr>
                        <tr>
                            <th>Masa Kerja</th>
                            <td id="detailMasaKerja"></td>
                        </tr>
                        <tr id="detailLamaKontrakRow">
                            <th>Lama Kontrak</th>
                            <td id="detailLamaKontrak"></td>
                        </tr>
                        <tr id="detailTglSelesaiRow">
                            <th>Tanggal Selesai</th>
                            <td id="detailTglSelesai"></td>
                        </tr>
                        <tr>
                            <th>Pendidikan</th>
                            <td id="detailPendidikan"></td>
                        </tr>
                        <tr>
                            <th>Strata</th>
                            <td id="detailStrata"></td>
                        </tr>
                        <tr id="detailGajiPokokRow">
                            <th>Gaji Pokok</th>
                            <td id="detailGajiPokok"></td>
                        </tr>
                        <tr id="detailSalaryIndexRow">
                            <th>Nominal Level Indeks</th>
                            <td id="detailSalaryIndexNominal"></td>
                        </tr>
                        <tr id="detailSalaryIndexLevelRow">
                            <th>Level Salary Indeks</th>
                            <td id="detailSalaryIndexLevel"></td>
                        </tr>
                        <tr>
                            <th>Total Pendapatan</th>
                            <td id="detailTotalPendapatan"></td>
                        </tr>
                        <tr>
                            <th>Total Potongan</th>
                            <td id="detailTotalPotongan"></td>
                        </tr>
                        <tr>
                            <th>Gaji Bersih</th>
                            <td id="detailGajiBersih"></td>
                        </tr>
                        <tr>
                            <th>Kenaikan Gaji Tahunan</th>
                            <td id="detailKenaikanGajiTahunan"></td>
                        </tr>

                        <tr>
                            <th>Payheads</th>
                            <td id="detailPayheads"></td>
                        </tr>
                    </table>

                    <!-- SECTION: Data Pribadi -->
                    <div class="alert alert-primary fw-bold mb-3" role="alert">
                        Data Pribadi
                    </div>
                    <table class="table table-sm mb-4">
                        <tr>
                            <th>Jenis Kelamin</th>
                            <td id="detailJenisKelamin"></td>
                        </tr>
                        <tr>
                            <th>Tanggal Lahir</th>
                            <td id="detailTglLahir"></td>
                        </tr>
                        <tr>
                            <th>Usia</th>
                            <td id="detailUsia"></td>
                        </tr>
                        <tr>
                            <th>Agama</th>
                            <td id="detailAgama"></td>
                        </tr>
                        <tr>
                            <th>Status Perkawinan</th>
                            <td id="detailStatusPerkawinan"></td>
                        </tr>
                        <tr>
                            <th>Nama Pasangan</th>
                            <td id="detailNamaPasangan"></td>
                        </tr>
                        <tr>
                            <th>Jumlah Anak</th>
                            <td id="detailJumlahAnak"></td>
                        </tr>
                        <tr>
                            <th>Nama Anak 1</th>
                            <td id="detailNamaAnak1"></td>
                        </tr>
                        <tr>
                            <th>Nama Anak 2</th>
                            <td id="detailNamaAnak2"></td>
                        </tr>
                        <tr>
                            <th>Nama Anak 3</th>
                            <td id="detailNamaAnak3"></td>
                        </tr>
                    </table>

                    <!-- SECTION: Data Kontak -->
                    <div class="alert alert-primary fw-bold mb-3" role="alert">
                        Data Kontak
                    </div>
                    <table class="table table-sm mb-4">
                        <tr>
                            <th>Alamat Domisili</th>
                            <td id="detailAlamatDomisili"></td>
                        </tr>
                        <tr>
                            <th>Alamat KTP</th>
                            <td id="detailAlamatKTP"></td>
                        </tr>
                        <tr>
                            <th>No Rekening</th>
                            <td id="detailNoRekening"></td>
                        </tr>
                        <tr>
                            <th>No HP</th>
                            <td id="detailNoHP"></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td id="detailEmail"></td>
                        </tr>
                    </table>

                    <!-- SECTION: Lain-lain -->
                    <div class="alert alert-primary fw-bold mb-3" role="alert">
                        Lain-lain
                    </div>
                    <table class="table table-sm mb-2">
                        <tr>
                            <th>Remark</th>
                            <td id="detailRemark"></td>
                        </tr>
                    </table>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Tutup</button>
                </div>
            </div>
        </div>
    </div>


    <!-- MODAL: Review Rekap Absensi -->
    <div class="modal fade" id="rekapReviewModal" tabindex="-1" aria-labelledby="rekapReviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rekapReviewModalLabel"><i class="bi bi-eye"></i> Review Rekap Absensi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="data-display">
                        <p><strong>ID Anggota:</strong> <span id="review_id_anggota"></span></p>
                        <p><strong>Bulan:</strong> <span id="review_bulan"></span></p>
                        <p><strong>Tahun:</strong> <span id="review_tahun"></span></p>
                        <p><strong>Total Hadir:</strong> <span id="review_total_hadir"></span></p>
                        <p><strong>Total Izin:</strong> <span id="review_total_izin"></span></p>
                        <p><strong>Total Cuti:</strong> <span id="review_total_cuti"></span></p>
                        <p><strong>Total Tanpa Keterangan:</strong> <span id="review_total_tanpa_keterangan"></span></p>
                        <p><strong>Total Sakit:</strong> <span id="review_total_sakit"></span></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" id="btnOpenEditRekap">Edit</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: Edit Rekap Absensi -->
    <div class="modal fade" id="rekapAbsensiModal" tabindex="-1" aria-labelledby="rekapAbsensiModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="rekapAbsensiForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="rekapAbsensiModalLabel"><i class="bi bi-calendar-check"></i> Edit Rekap Absensi</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="case" value="EditRekapAbsensi">
                        <input type="hidden" name="id_anggota" id="rekap_id_anggota_edit">
                        <input type="hidden" name="bulan" id="rekap_bulan_edit">
                        <input type="hidden" name="tahun" id="rekap_tahun_edit">
                        <div class="mb-3">
                            <label for="total_hadir_edit" class="form-label">Total Hadir</label>
                            <input type="number" class="form-control" name="total_hadir" id="total_hadir_edit" required>
                        </div>
                        <div class="mb-3">
                            <label for="total_izin_edit" class="form-label">Total Izin</label>
                            <input type="number" class="form-control" name="total_izin" id="total_izin_edit" required>
                        </div>
                        <div class="mb-3">
                            <label for="total_cuti_edit" class="form-label">Total Cuti</label>
                            <input type="number" class="form-control" name="total_cuti" id="total_cuti_edit" required>
                        </div>
                        <div class="mb-3">
                            <label for="total_tanpa_keterangan_edit" class="form-label">Total Tanpa Keterangan</label>
                            <input type="number" class="form-control" name="total_tanpa_keterangan" id="total_tanpa_keterangan_edit" required>
                        </div>
                        <div class="mb-3">
                            <label for="total_sakit_edit" class="form-label">Total Sakit</label>
                            <input type="number" class="form-control" name="total_sakit" id="total_sakit_edit" required>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan Perubahan
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
                            $highlightClass = 'bg-light';
                            foreach ($processedMonths as $pm) {
                                if ($pm['bulan'] == $month && $pm['tahun'] == $year) {
                                    $highlightClass = 'processed-month';
                                    break;
                                }
                            }
                            if ($month == $selectedMonth && $year == $selectedYear) {
                                $highlightClass = 'bg-warning text-dark fw-bold';
                            }
                            echo '<div class="col-3 mb-3">';
                            echo '  <div class="p-2 ' . $highlightClass . '" style="border: 1px solid #ddd; border-radius: 5px;">';
                            echo '    <a href="#" class="month-link" data-month-number="' . $month . '" data-month="' . date("F", mktime(0, 0, 0, $month, 1)) . '" data-year="' . $year . '" style="color: inherit; text-decoration: none;">';
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
    <script src="https://cdn.jsdelivr.net/npm/autonumeric@4.8.0/dist/autoNumeric.min.js"></script>

    <script>
        $(document).ready(function() {

            // === SweetAlert2 Toast ===
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });

            function showToast(message, icon = 'success') {
                Toast.fire({
                    icon: icon,
                    title: message
                });
            }

            // === Variabel Pagination Manual ===
            let currentPage = 1;
            let pageSize = 10;

            function loadEmployees(page) {
                currentPage = page;
                let start = (currentPage - 1) * pageSize;
                let length = pageSize;
                $.ajax({
                    url: "employees.php?ajax=1",
                    type: "POST",
                    data: {
                        case: "LoadingEmployees",
                        start: start,
                        length: length,
                        jenjang: $("#filterJenjang").val(),
                        role: $("#filterRole").val(),
                        search: $("#filterSearch").val(),
                        selectedMonth: localStorage.getItem("selectedMonthNumber") || <?= $selectedMonth ?>,
                        selectedYear: localStorage.getItem("selectedYearPayroll") || <?= $selectedYear ?>,
                        csrf_token: "<?= htmlspecialchars($csrf_token); ?>"
                    },
                    dataType: "json",
                    beforeSend: function() {
                        $("#loadingSpinner").show();
                    },
                    success: function(res) {
                        $("#loadingSpinner").hide();
                        if (res.data) {
                            generateCards(res.data);
                            generatePagination(res.recordsTotal);
                        } else {
                            $("#employeeCards").empty();
                            $("#paginationContainer").empty();
                            showToast("Data kosong atau gagal di-load", "warning");
                        }
                    },
                    error: function(xhr, status, error) {
                        $("#loadingSpinner").hide();
                        showToast("Terjadi kesalahan memuat data: " + error, "error");
                    }
                });
            }

            // Dapatkan baseUrl dari PHP
            let baseUrl = "<?= getBaseUrl(); ?>";

            function generateCards(data) {
                const container = $("#employeeCards").empty();
                data.forEach(item => {
                    const rapelBadge = item.has_rapel ?
                        `<span class="badge" style="background-color:#dc3545;color:#fff;">Ada</span>` :
                        `<span class="badge bg-secondary">-</span>`;
                    const photoUrl = item.foto_profil || baseUrl + "/assets/img/undraw_profile.svg";

                    // --- TAMBAH BADGE WARNA STATUS PAYROLL ---
                    let payrollBadge = '';
                    if (item.payroll_status === 'Final') {
                        payrollBadge = `<span class="badge bg-success"><i class="bi bi-check-circle"></i> Final</span>`;
                    } else if (item.payroll_status === 'Revisi') {
                        payrollBadge = `<span class="badge bg-warning text-dark"><i class="bi bi-pencil"></i> Revisi</span>`;
                    } else if (item.payroll_status === 'Draft') {
                        payrollBadge = `<span class="badge bg-info text-dark"><i class="bi bi-file-earmark-text"></i> Draft</span>`;
                    } else {
                        payrollBadge = `<span class="badge bg-secondary"><i class="bi bi-hourglass-split"></i> Belum Diproses</span>`;
                    }

                    const isPayrollFinal = item.payroll_status === 'Final';
                    const btnAssignDisabled = isPayrollFinal ? 'disabled style="pointer-events:none;opacity:0.6;" title="Payroll sudah final, tidak bisa diubah."' : '';


                    const cardHtml = `
  <div class="col mb-4">
    <div class="card employee-card h-100" data-payroll_status="${item.payroll_status}">
      <img src="${photoUrl}" class="card-img-top employee-photo" alt="Foto Profil">
      <div class="card-body d-flex flex-column text-center">
        <h5 class="card-title">${item.nama}</h5>
        <p class="card-text small text-muted">NIP: ${item.nip}</p>
        <div class="badges">
          ${item.badge_role} ${item.badge_jenjang} ${item.badge_unit}
        </div>
        <div class="status">
          <strong>Status:</strong> ${item.badge_status}
        </div>
        <div class="payroll_status mb-2">
          <strong>Status Payroll:</strong> ${payrollBadge}
        </div>
        <div class="rapel">
          <strong>Rapel:</strong> ${rapelBadge}
        </div>
        <div class="mt-auto d-grid gap-2">
          <button class="btn btn-primary btn-sm btnViewDetail" data-id="${item.id}">
            <i class="bi bi-eye-fill"></i> Detail
          </button>
          <button class="btn btn-warning btn-sm btnEdit" data-id="${item.id}">
            <i class="bi bi-pencil-square"></i> Edit
          </button>
          <button class="btn btn-info btn-sm btnAssignPayheads" data-id="${item.id}" ${btnAssignDisabled}>
            <i class="bi bi-cash-stack"></i> Payroll
          </button>
          <button class="btn btn-secondary btn-sm btnRekapAbsensi" data-id="${item.id}">
            <i class="bi bi-calendar-check"></i> Absensi
          </button>
        </div>
      </div>
    </div>
  </div>`;
                    container.append(cardHtml);
                });
            }


            function generatePagination(totalRecords) {
                let totalPages = Math.ceil(totalRecords / pageSize);
                let pagination = $("#paginationContainer");
                pagination.empty();
                for (let i = 1; i <= totalPages; i++) {
                    let li = $("<li>").addClass("page-item").append(
                        $("<a>").addClass("page-link").text(i).attr("href", "#").on("click", function(e) {
                            e.preventDefault();
                            loadEmployees(i);
                        })
                    );
                    if (i === currentPage) li.addClass("active");
                    pagination.append(li);
                }
            }

            loadEmployees(1);

            $("#btnApplyFilter").on("click", function() {
                loadEmployees(1);
            });
            $("#btnResetFilter").on("click", function() {
                $("#filterForm")[0].reset();
                $("#filterSearch").val("");
                loadEmployees(1);
            });

            $(document).on("click", ".btnAssignPayheads", function() {
                let cardStatus = $(this).parents('.card').attr('data-payroll_status');
                if (typeof cardStatus !== 'undefined' && cardStatus.toLowerCase() === 'final') {
                    showToast("Payroll sudah final, tidak dapat mengubah payheads.", "warning");
                    return;
                }
                let id = $(this).data("id");
                let filterMonth = localStorage.getItem("selectedMonthNumber") || <?= $selectedMonth ?>;
                let filterYear = localStorage.getItem("selectedYearPayroll") || <?= $selectedYear ?>;
                window.location.href = 'payroll_page.php?empcode=' + id + '&filterMonth=' + filterMonth + '&filterYear=' + filterYear;
            });

            function calcPotonganAbsensi(role, totalIzin, totalCuti, totalTK, totalSakit) {
                let totalTidakHadir = totalIzin + totalCuti + totalTK + totalSakit;
                let potongan = 0;
                if (role === 'P' || role === 'TK') {
                    const biayaPerHari = 75000;
                    potongan = Math.min(totalTidakHadir, 2) * biayaPerHari;
                } else if (role === 'M') {
                    const biayaPerHariManajerial = 50000;
                    potongan = totalTidakHadir * biayaPerHariManajerial;
                }
                return potongan;
            }

            // Event handler untuk Edit Data
            $(document).on("click", ".btnEdit", function() {
                let id = $(this).data("id");
                $.ajax({
                    url: 'employees.php?ajax=1',
                    type: 'POST',
                    data: {
                        case: 'ViewEmployeeDetail',
                        id: id,
                        csrf_token: '<?= htmlspecialchars($csrf_token); ?>',
                        selectedMonth: localStorage.getItem("selectedMonthNumber"),
                        selectedYear: localStorage.getItem("selectedYearPayroll")
                    },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.code === 0) {
                            var e = resp.result;
                            $('#editEmployeeId').val(e.id);
                            $('#editNip').val(e.nip);
                            $('#editNama').val(e.nama);
                            $('#editJenjang').val(e.jenjang);
                            $('#editJobTitle').val(e.job_title);
                            $('#editStatusKerja').val(e.status_kerja);
                            $('#editNoRekening').val(e.no_rekening);
                            $('#editEmail').val(e.email);
                            $('#editJenisKelamin').val(e.jenis_kelamin);
                            $('#editAgama').val(e.agama);
                            $('#editEmployeeModal').modal('show');
                        } else {
                            showToast(resp.result, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        showToast('Terjadi kesalahan saat memuat data anggota: ' + error, 'error');
                    }
                });
            });

            $('#editEmployeeForm').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                var dataToSend = {
                    case: 'EditEmployee',
                    id: $('#editEmployeeId').val(),
                    no_rekening: $('#editNoRekening').val(),
                    csrf_token: '<?= htmlspecialchars($csrf_token); ?>'
                };
                $.ajax({
                    url: 'employees.php?ajax=1',
                    type: 'POST',
                    data: dataToSend,
                    dataType: 'json',
                    beforeSend: function() {
                        form.find('button[type="submit"]').prop('disabled', true);
                        form.find('.spinner-border').removeClass('d-none');
                    },
                    success: function(resp) {
                        form.find('button[type="submit"]').prop('disabled', false);
                        form.find('.spinner-border').addClass('d-none');
                        if (resp.code === 0) {
                            showToast(resp.result, 'success');
                            loadEmployees(currentPage);
                            $('#editEmployeeModal').modal('hide');
                        } else {
                            showToast(resp.result, 'error');
                        }
                    },
                    error: function() {
                        form.find('button[type="submit"]').prop('disabled', false);
                        form.find('.spinner-border').addClass('d-none');
                        showToast('Terjadi kesalahan saat memperbarui data anggota.', 'error');
                    }
                });
            });


            function formatDate(str) {
                if (!str) return '-';
                const d = new Date(str);
                if (isNaN(d.getTime())) return '-';
                return d.toLocaleDateString('id-ID', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            }

            // Event handler untuk View Detail
            $(document).on("click", ".btnViewDetail", function() {
                let id = $(this).data("id");
                $.ajax({
                    url: 'employees.php?ajax=1',
                    type: 'POST',
                    data: {
                        case: 'ViewEmployeeDetail',
                        id: id,
                        csrf_token: '<?= htmlspecialchars($csrf_token); ?>',
                        selectedMonth: localStorage.getItem("selectedMonthNumber"),
                        selectedYear: localStorage.getItem("selectedYearPayroll")
                    },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.code === 0) {
                            var e = resp.result;
                            // --- FOTO Profil & KTP
                            $('#detailFotoProfil').attr('src', e.foto_profil || baseUrl + "/assets/img/undraw_profile.svg");
                            $('#detailFotoKtp').attr('src', e.foto_ktp || baseUrl + "/assets/img/ktp_placeholder.png");

                            // --- DATA PEKERJAAN
                            $('#detailId').text(e.id ?? '-');
                            $('#detailUid').text(e.uid ?? '-');
                            $('#detailNip').text(e.nip ?? '-');
                            $('#detailNama').text(e.nama ?? '-');
                            $('#detailJenjang').text(e.jenjang ?? '-');
                            $('#detailUnitPenempatan').text(e.unit_penempatan || '-');
                            $('#detailRole').text(e.role ?? '-');
                            $('#detailJobTitle').text(e.job_title ?? '-');
                            $('#detailStatusKerja').text(e.status_kerja ?? '-');
                            $('#detailJoinStart').text(e.join_start ?? '-');
                            $('#detailMasaKerja').text(e.masa_kerja ?? '-');
                            $('#detailPendidikan').text(e.pendidikan ?? '-');
                            $('#detailStrata').text(e.strata ?? '-');
                            $('#detailLamaKontrak').text(e.lama_kontrak ? (e.lama_kontrak + ' Bln') : '-');
                            $('#detailTglSelesai').text(e.tgl_kontrak_selesai ?? '-');
                            $('#detailGajiPokok').text(e.gaji_pokok !== undefined ? e.gaji_pokok : '-');

                            // --- SALARY INDEX/LEVEL (Nominal)
                            let salaryIndexNom = e.salary_index_base !== undefined && e.salary_index_base !== null ?
                                parseFloat(e.salary_index_base) :
                                0;
                            $('#detailSalaryIndexNominal').text('Rp ' + salaryIndexNom.toLocaleString('id-ID', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            }));

                            $('#detailSalaryIndexLevel').text(e.salary_index_level ?? '-');
                            if ((e.jenjang || '').toUpperCase() === 'UMUM') {
                                $('#detailUnitPenempatanRow').show();
                            } else {
                                $('#detailUnitPenempatanRow').hide();
                            }
                            // --- PAYHEADS (Rincian)
                            if (e.payheads && e.payheads.length > 0) {
                                var s = '<ul>';
                                e.payheads.forEach(function(ph) {
                                    var nominal = parseFloat(ph.amount).toLocaleString('id-ID', {
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0
                                    });
                                    var jns = (ph.jenis_payhead === 'earnings') ? 'Pendapatan' : 'Potongan';
                                    var clr = (ph.jenis_payhead === 'earnings') ? 'green' : 'red';
                                    s += `<li style="color:${clr}">${ph.nama_payhead} (${jns}): Rp ${nominal}`;
                                    if (ph.support_doc_path && ph.support_doc_path.trim() !== "") {
                                        s += ` - <a href="download_payhead.php?file=${encodeURIComponent(ph.support_doc_path)}&name=${encodeURIComponent(ph.nama_payhead)}" download="${ph.nama_payhead}" target="_blank">Lihat Dokumen</a>`;
                                    }
                                    s += `</li>`;
                                });
                                s += '</ul>';
                                $('#detailPayheads').html(s);
                            } else {
                                $('#detailPayheads').html('<i>Tidak ada payheads</i>');
                            }

                            // --- TOTAL PENDAPATAN/POTONGAN/GAJI BERSIH
                            $('#detailTotalPendapatan').text('Rp ' + (parseFloat(e.total_pendapatan) || 0).toLocaleString('id-ID', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            }));
                            $('#detailTotalPotongan').text('Rp ' + (parseFloat(e.total_potongan) || 0).toLocaleString('id-ID', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            }));
                            $('#detailGajiBersih').text('Rp ' + (parseFloat(e.gaji_bersih) || 0).toLocaleString('id-ID', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            }));
                            // --- KENAIKAN GAJI TAHUNAN
                            if (e.kenaikan_gaji_tahunan && e.kenaikan_gaji_tahunan.status === 'aktif') {
                                $('#detailKenaikanGajiTahunan').html(
                                    `<span class="text-success fw-bold">
            ${e.kenaikan_gaji_tahunan.nama_kenaikan || '-'}<br>
            Rp ${(parseFloat(e.kenaikan_gaji_tahunan.jumlah) || 0).toLocaleString('id-ID', {minimumFractionDigits:0})}
            <br><small>Berlaku: ${formatDate(e.kenaikan_gaji_tahunan.tanggal_mulai)} - ${formatDate(e.kenaikan_gaji_tahunan.tanggal_berakhir)}</small>
        </span>`
                                );
                            } else {
                                $('#detailKenaikanGajiTahunan').html('<i>Tidak ada kenaikan gaji tahunan</i>');
                            }

                            // --- DATA PRIBADI
                            $('#detailJenisKelamin').text(e.jenis_kelamin === 'L' ? 'Laki-laki' : e.jenis_kelamin === 'P' ? 'Perempuan' : '-');
                            $('#detailTglLahir').text(e.tanggal_lahir ?? '-');
                            $('#detailUsia').text(e.usia ?? '-');
                            $('#detailAgama').text(e.agama ?? '-');
                            $('#detailStatusPerkawinan').text(e.status_perkawinan ?? '-');
                            $('#detailNamaPasangan').text(e.nama_pasangan ?? '-');
                            $('#detailJumlahAnak').text(e.jumlah_anak ?? '-');
                            $('#detailNamaAnak1').text(e.nama_anak_1 ?? '-');
                            $('#detailNamaAnak2').text(e.nama_anak_2 ?? '-');
                            $('#detailNamaAnak3').text(e.nama_anak_3 ?? '-');

                            // --- DATA KONTAK
                            $('#detailAlamatDomisili').text(e.alamat_domisili ?? '-');
                            $('#detailAlamatKTP').text(e.alamat_ktp ?? '-');
                            $('#detailNoRekening').text(e.no_rekening ?? '-');
                            $('#detailNoHP').text(e.no_hp ?? '-');
                            $('#detailEmail').text(e.email ?? '-');

                            // --- LAIN-LAIN / REMARK
                            $('#detailRemark').text(e.remark ?? '-');

                            // --- KONDISIONAL FIELD KONTRAK
                            if ((e.status_kerja || '').toLowerCase() === 'tetap') {
                                $('#detailLamaKontrakRow,#detailTglSelesaiRow').hide();
                            } else {
                                $('#detailLamaKontrakRow,#detailTglSelesaiRow').show();
                            }

                            // --- MODAL SHOW
                            $('#viewDetailModal').modal('show');
                        } else {
                            showToast(resp.result, 'error');
                        }
                    },
                    error: function() {
                        showToast('Terjadi kesalahan saat mengambil detail anggota.', 'error');
                    }
                });
            });


            // Handler untuk rekapan absensi
            $(document).on('click', '.btnRekapAbsensi', function() {
                var id = $(this).data('id');
                var role = $(this).data('role');
                var selectedMonth = localStorage.getItem('selectedMonthNumber') || <?= $selectedMonth ?>;
                var selectedYear = localStorage.getItem('selectedYearPayroll') || <?= $selectedYear ?>;
                $.ajax({
                    url: 'employees.php?ajax=1',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        case: 'ViewRekapAbsensi',
                        id: id,
                        selectedMonth: selectedMonth,
                        selectedYear: selectedYear,
                        csrf_token: CSRF_TOKEN
                    },
                    success: function(resp) {
                        if (resp.code === 0) {
                            var data = resp.result;
                            $("#review_id_anggota").text(data.id_anggota);
                            $("#review_bulan").text(data.bulan);
                            $("#review_tahun").text(data.tahun);
                            $("#review_total_hadir").text(data.total_hadir);
                            $("#review_total_izin").text(data.total_izin);
                            $("#review_total_cuti").text(data.total_cuti);
                            $("#review_total_tanpa_keterangan").text(data.total_tanpa_keterangan);
                            $("#review_total_sakit").text(data.total_sakit);
                            $('#rekap_id_anggota_edit').val(data.id_anggota);
                            $('#rekap_bulan_edit').val(data.bulan);
                            $('#rekap_tahun_edit').val(data.tahun);
                            $('#total_hadir_edit').val(data.total_hadir);
                            $('#total_izin_edit').val(data.total_izin);
                            $('#total_cuti_edit').val(data.total_cuti);
                            $('#total_tanpa_keterangan_edit').val(data.total_tanpa_keterangan);
                            $('#total_sakit_edit').val(data.total_sakit);
                            let potonganAbsensi = calcPotonganAbsensi(role, data.total_izin, data.total_cuti, data.total_tanpa_keterangan, data.total_sakit);
                            window.potonganAbsensiGlobal = potonganAbsensi;
                            const modalRekap = new bootstrap.Modal(document.getElementById('rekapReviewModal'));
                            modalRekap.show();

                        } else {
                            $("#review_id_anggota, #review_bulan, #review_tahun").text('');
                            $("#review_total_hadir, #review_total_izin, #review_total_cuti, #review_total_tanpa_keterangan, #review_total_sakit").text('0');
                            window.potonganAbsensiGlobal = 0;
                        }
                    },
                    error: function() {
                        $("#review_total_hadir, #review_total_izin, #review_total_cuti, #review_total_tanpa_keterangan, #review_total_sakit").text("0");
                        window.potonganAbsensiGlobal = 0;
                    }
                });
            });

            $('#btnOpenEditRekap').on('click', function() {
                $('#rekapReviewModal').modal('hide');
                $('#rekapAbsensiModal').modal('show');
            });

            $('#rekapAbsensiForm').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                $.ajax({
                    url: 'employees.php?ajax=1',
                    type: 'POST',
                    dataType: 'json',
                    data: form.serialize(),
                    beforeSend: function() {
                        form.find('button[type="submit"]').prop('disabled', true);
                        form.find('.spinner-border').removeClass('d-none');
                    },
                    success: function(resp) {
                        form.find('button[type="submit"]').prop('disabled', false);
                        form.find('.spinner-border').addClass('d-none');
                        if (resp.code === 0) {
                            showToast(resp.result, 'success');
                            $('#rekapAbsensiModal').modal('hide');
                        } else {
                            showToast(resp.result, 'error');
                        }
                    },
                    error: function() {
                        form.find('button[type="submit"]').prop('disabled', false);
                        form.find('.spinner-border').addClass('d-none');
                        showToast('Terjadi kesalahan saat menyimpan rekap absensi.', 'error');
                    }
                });
            });

            function initSelectedMonthDisplay() {
                if (localStorage.getItem('selectedMonthPayroll') && localStorage.getItem('selectedYearPayroll')) {
                    var selMonth = localStorage.getItem('selectedMonthPayroll');
                    var selYear = localStorage.getItem('selectedYearPayroll');
                    $('#selectedMonthDisplay').html(
                        '<div class="card mb-3">' +
                        '<div class="card-body d-flex align-items-center">' +
                        '<i class="bi bi-calendar3 me-2"></i>' +
                        '<span class="fw-bold">Payroll Bulan: ' + selMonth + ' ' + selYear + '</span>' +
                        '<button id="btnChangeCalendar" class="btn btn-link ms-auto">' +
                        '<i class="bi bi-pencil-square"></i> Ganti Kalender' +
                        '</button>' +
                        '</div>' +
                        '</div>'
                    );
                } else {
                    $('#selectedMonthDisplay').html('<h4>Klik di sini untuk memilih bulan payroll</h4>');
                    $('#SalaryMonthModal').modal('show');
                }
            }
            initSelectedMonthDisplay();
            loadEmployees(1);

            $("#btnChangeCalendar").on("click", function() {
                $("#SalaryMonthModal").modal("show");
            });

            $(document).on("click", ".month-link", function(e) {
                e.preventDefault();
                var monthNumber = $(this).data('month-number');
                var monthName = $(this).data('month');
                var year = $(this).data('year');
                if (window.currentEmpId) {
                    var employeeId = window.currentEmpId;
                    var targetUrl = "/payroll_absensi_v2/payroll/keuangan/manage-salary.php";
                    targetUrl += "?id_anggota=" + employeeId;
                    targetUrl += "&bulan=" + encodeURIComponent(monthName);
                    targetUrl += "&tahun=" + encodeURIComponent(year);
                    window.location.href = targetUrl;
                } else {
                    $.ajax({
                        url: 'employees.php?ajax=1',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            case: 'CheckPayrollCompletion',
                            selectedMonth: monthNumber,
                            selectedYear: year,
                            csrf_token: '<?= htmlspecialchars($csrf_token); ?>'
                        },
                        success: function(resp) {
                            if (resp.code === 0) {
                                if (resp.result.complete === false) {
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
                                        if (result.isConfirmed) {
                                            simpanPilihanBulan(monthNumber, monthName, year);
                                        }
                                    });
                                } else {
                                    simpanPilihanBulan(monthNumber, monthName, year);
                                }
                            } else {
                                showToast(resp.result, 'error');
                            }
                        },
                        error: function(xhr, status, error) {
                            showToast('Terjadi kesalahan saat mengecek payroll: ' + error, 'error');
                        }
                    });
                }
            });

            function simpanPilihanBulan(monthNumber, monthName, year) {
                localStorage.setItem('selectedMonthPayroll', monthName);
                localStorage.setItem('selectedMonthNumber', monthNumber);
                localStorage.setItem('selectedYearPayroll', year);
                $('#selectedMonthDisplay').html(
                    '<div class="card mb-3">' +
                    '<div class="card-body d-flex align-items-center">' +
                    '<i class="bi bi-calendar3 me-2"></i>' +
                    '<span class="fw-bold">Payroll Bulan: ' + monthName + ' ' + year + '</span>' +
                    '<button id="btnChangeCalendar" class="btn btn-link ms-auto">' +
                    '<i class="bi bi-pencil-square"></i> Ganti Kalender' +
                    '</button>' +
                    '</div>' +
                    '</div>'
                );
                $('#SalaryMonthModal').modal('hide');
                var newUrl = "employees.php?filterMonth=" + monthNumber + "&filterYear=" + year;
                window.location.href = newUrl;
            }
        });
    </script>
</body>

</html>