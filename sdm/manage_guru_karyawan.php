<?php

/**
 *  manage_guru_karyawan.php  (controller + view)
 *  ---------------------------------------------
 *  – Routing AJAX → fungsi di mgk_crud_guru.php
 *  – Ambil konfigurasi jenjang & strata
 *  – Render halaman
 */

$pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

/* ===== 1. Bootstrap & helpers umum ===== */
require_once __DIR__ . '/../helpers.php';               // global helpers
require_once __DIR__ . '/../koneksi.php';               // koneksi DB

/* ===== 2. Modul-modul khusus MGK ===== */
require_once __DIR__ . '/includes/mgk_date_utils.php';      // addMonths(), calcMasaKerja(), dll
require_once __DIR__ . '/includes/mgk_upload_handler.php';  // save_image_as_jpg(), getProfilePhotoUrl(), dll
require_once __DIR__ . '/includes/mgk_salary_handler.php';  // hitungGajiPokok(), strata config, dll
require_once __DIR__ . '/includes/mgk_crud_guru.php';       // LoadingGuru(), CreateGuru(), dst.

/* ===== 3. Session, error-handling, auth ===== */
start_session_safe();
init_error_handling();
authorize(['M:SDM']);

/* ===== 4. Data dropdown & konfigurasi strata ===== */
$jenjangList   = getOrderedJenjang($conn);          // dari helpers.php
$strataConfig  = getStrataConfig($conn);            // fungsi di mgk_salary_handler.php
$guruConfig     = $strataConfig['guru'];
$karyawanConfig = $strataConfig['karyawan'];

/* ===== 5. Nilai gaji pokok per strata (untuk modal “Atur Gaji Strata…”) ===== */
$guruStrata     = fetchStrataValues($conn, 'guru');      // fungsi di mgk_salary_handler.php
$karyawanStrata = fetchStrataValues($conn, 'karyawan');

/* ===== 6. Siapkan CSRF token ===== */
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

/* ========================================================================
   ==========            ROUTER  –  menangani request AJAX          =========
   ======================================================================== */
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
  verify_csrf_token($_POST['csrf_token'] ?? '');

  switch ($_POST['case'] ?? '') {

    // --- CRUD utama (didefinisikan di mgk_crud_guru.php) ---
    case 'LoadingGuru':
      LoadingGuru($conn);
      break;
    case 'CreateGuru':
      CreateGuru($conn);
      break;
    case 'GetGuruDetail':
      send_response(0, GetGuruDetail($conn));
      break;
    case 'UpdateGuru':
      UpdateGuru($conn);
      break;
    case 'DeleteGuru':
      DeleteGuru($conn);
      break;

    // --- Pengaturan gaji (fungsi ada di mgk_salary_handler.php) ---
    case 'update_gaji_pokok':
      updateGajiPokok($conn);
      break;
    case 'update_gaji_strata_guru':
      updateGajiStrataGuru($conn);
      break;
    case 'update_gaji_strata_karyawan':
      updateGajiStrataKaryawan($conn);
      break;
    case 'GetRecommendedSalaryIndex':
      send_response(0, getRecommendedSalaryIndex($conn, $_POST['join_start'] ?? ''));
      break;
    case 'update_salary_index_all':
      if (updateSalaryIndexForAll($conn)) {
        send_response(0, 'Salary index untuk semua user berhasil diperbarui.');
      }
      send_response(1, 'Gagal memperbarui salary index.');
      break;

    default:
      send_response(400, 'Case tidak valid.');
  }
  exit();   // ====== berhenti di sini untuk request AJAX ======
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>Manajemen Data Guru/Karyawan - Payroll</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <!-- CSS dari CDN -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- SB Admin 2 CSS -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/startbootstrap-sb-admin-2/4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <style>
    /* 1. Base typography & layout */
    body {
      font-family: 'Nunito', sans-serif;
      font-size: 1.05rem;
      line-height: 1.6;
      background-color: #f5f6f7;
      color: #212529 !important;
      padding: 0;
      margin: 0;
    }

    .badge {
      font-weight: 700 !important;
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

    /* Container padding ekstra untuk ruang putih */
    .container-fluid {
      padding: 0.5rem 1rem;
    }

    /* 2. Card styling */
    .card {
      border: none;
      border-radius: 0.5rem;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
      margin-bottom: 1.5rem;
    }

    /* 3. Card headers: gradient lembut */
    .card-header {
      background: linear-gradient(135deg, #3a7bd5 0%, #00d2ff 100%);
      color: white;
      font-weight: 600;
      border-radius: 0.5rem 0.5rem 0 0 !important;
    }

    /* ----------- STYLE TAMBAHAN/REPLACE START ----------- */
    /* Grid untuk employee cards */
    #employeeCards {
      margin-top: 8px;
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      /* <-- fix: 5 kolom! */
      gap: 20px 20px;
      /* Lebih lega */
    }

    /* Card khusus untuk anggota */
    .employee-card {
      border-radius: 22px;
      box-shadow: 0 6px 24px rgba(34, 92, 181, 0.10);
      border: 1.5px solid #eee;
      background: #fff;
      transition: box-shadow 0.2s;
      min-height: 470px;
      /* <-- lebih besar */
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 1.8rem 1.1rem 1.4rem 1.1rem;
      /* atas, kanan, bawah, kiri */
    }

    .employee-card .card-body {
      padding: 1.2rem 0.3rem 0.7rem 0.3rem;
      /* biar konten lebih lega */
    }

    .employee-photo {
      width: 105px;
      height: 105px;
      object-fit: cover;
      border-radius: 50%;
      border: 3px solid #fff;
      margin-bottom: 14px;
      margin-top: -4px;
      box-shadow: 0 2px 10px 0 rgba(32, 40, 67, .09);
    }

    .employee-card h5 {
      font-size: 1.19rem;
    }


    /* NIP */
    .employee-card p.text-muted {
      font-size: 0.98rem;
      margin-bottom: 0.15rem;
    }

    /* Badges */
    .employee-card .badge {
      margin: 2px 2px 0 0;
      font-size: 0.83rem;
      font-weight: 500;
      border-radius: 6px;
      padding: 0.37em 0.8em;
      letter-spacing: 0.01em;
      display: inline-block;
    }

    .employee-card .badge.bg-success {
      background: #48bb78 !important;
      /* green-400 */
      color: #fff !important;
    }

    .employee-card .badge.bg-warning,
    .employee-card .badge.bg-warning.text-dark {
      background: #f6e05e !important;
      /* yellow-300 */
      color: #684200 !important;
    }

    .employee-card .badge.bg-secondary {
      background: #ececec !important;
      color: #333 !important;
    }

    /* Status kerja */
    .employee-card .status-label {
      font-weight: 600;
      color: #333;
      font-size: 0.96rem;
    }

    .employee-card .status-label .badge {
      font-size: 0.94rem;
      margin-left: 4px;
      vertical-align: middle;
    }

    /* Rapel info, agar tidak terlalu rapat */
    .employee-card .rapel-info {
      margin-bottom: 0.5rem;
      font-size: 0.97em;
      color: #555;
    }

    /* Tombol-tombol di dalam card */
    .employee-card .d-grid.gap-2 {
      margin-top: 0.7rem;
      gap: 8px;
    }

    .employee-card .btn {
      font-size: 0.99rem;
      font-weight: 600;
      border-radius: 10px;
      min-width: 100%;
      min-height: 38px;
      letter-spacing: 0.01em;
    }

    .employee-card .btn-primary {
      background: linear-gradient(90deg, #3a7bd5 0%, #00d2ff 100%);
      border: none;
    }

    .employee-card .btn-warning {
      background: #f6e05e;
      color: #7c5700;
      border: none;
    }

    .employee-card .btn-danger {
      background: #fa5252;
      border: none;
    }

    .employee-card .btn-info {
      background: #25a4fa;
      color: #fff;
      border: none;
    }

    .employee-card .btn-secondary {
      background: #ececec;
      color: #333;
      border: none;
    }

    /* Responsive: grid jadi 2 kolom di HP */
    @media (max-width: 1200px) {
      #employeeCards {
        grid-template-columns: repeat(3, 1fr);
        /* Tablet: 3 kolom */
      }
    }

    @media (max-width: 900px) {
      #employeeCards {
        grid-template-columns: repeat(2, 1fr);
        /* HP landscape: 2 kolom */
      }
    }

    @media (max-width: 600px) {
      #employeeCards {
        grid-template-columns: 1fr;
        /* HP potrait: 1 kolom */
      }
    }

    /* ----------- STYLE TAMBAHAN/REPLACE END ----------- */

    /* 8. Filter section */
    #filterSection {
      background-color: #ffffff;
      border-radius: 0.5rem;
      padding: 1rem;
    }

    #filterSection .form-label {
      font-weight: 500;
    }

    /* 9. Pagination */
    .pagination .page-link {
      padding: 0.5rem 0.75rem;
      color: #4A90E2;
      border-radius: 0.25rem;
    }

    .pagination .page-item.active .page-link {
      background-color: #4A90E2;
      border-color: #4A90E2;
      color: #ffffff;
    }

    /* 10. Loading overlay */
    #loadingSpinner {
      display: none;
      position: fixed;
      z-index: 1050;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(255, 255, 255, 0.7);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* 11. Toast size */
    .swal2-toast {
      font-size: 1rem !important;
    }

    /* 12. Konsistensi spasi form-controls */
    .form-control {
      border-radius: 0.375rem;
      padding: 0.5rem 0.75rem;
    }

    /* 13. Navbar & sidebar teks gelap */
    body,
    .text-gray-800 {
      color: #212529 !important;
    }

    /* 14. Footer */
    .sticky-footer {
      padding: 1rem 0;
      font-size: 0.9rem;
      color: #6c757d;
    }

    .d-none {
      display: none !important;
    }
  </style>

</head>

<body id="page-top">
  <!-- Page Wrapper -->
  <div id="wrapper">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../sidebar.php'; ?>
    <!-- End Sidebar -->
    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column">
      <!-- Main Content -->
      <div id="content">
        <!-- Navbar -->
        <?php include __DIR__ . '/../navbar.php'; ?>
        <!-- Breadcrumb -->
        <?php include __DIR__ . '/../breadcrumb.php'; ?>

        <div class="container-fluid">
          <h1 class="page-title">
            <i class="bi bi-people-fill me-2"></i>
            Manajemen Data Guru/Karyawan
          </h1>
          <!-- Bagian Tombol Aksi -->
          <div class="d-flex justify-content-end mb-3 flex-wrap gap-2">
            <!-- Tombol Tambah -->
            <button
              class="btn btn-sm"
              data-bs-toggle="modal" data-bs-target="#modalAdd"
              style="background:#ffd600; color:#111; border:none;">
              <i class="fas fa-plus"></i> Tambah Guru/Karyawan
            </button>

            <!-- Tombol History -->
            <a href="history_anggota_sekolah.php"
              class="btn btn-sm"
              style="background:#a7ffeb; color:#111; border:none;">
              <i class="fas fa-history"></i> Lihat History
            </a>

            <!-- Tombol Atur Gaji Pokok -->
            <button
              class="btn btn-sm"
              data-bs-toggle="modal" data-bs-target="#modalGajiPokok"
              style="background:#ffe082; color:#111; border:none;">
              <i class="fas fa-dollar-sign"></i> Atur Gaji Pokok
            </button>

            <!-- Tombol Atur Salary Indeks -->
            <button
              class="btn btn-sm"
              id="btnManageSalaryIndices"
              data-href="/payroll_absensi_v2/sdm/manage_salary_indices.php"
              style="background:#b2ff59; color:#111; border:none;">
              <i class="fas fa-money-bill-wave"></i> Atur Salary Indeks
            </button>

            <!-- Tombol Atur Hari Libur -->
            <button
              class="btn btn-sm"
              id="btnManageHolidays"
              data-href="/payroll_absensi_v2/sdm/holidays.php"
              style="background:#c5e1a5; color:#111; border:none;">
              <i class="fas fa-calendar-alt"></i> Atur Hari Libur
            </button>

            <!-- Tombol Kelola Jenjang -->
            <a href="jenjang.php"
              class="btn btn-sm"
              style="background:#ffd1dc; color:#111; border:none;">
              <i class="fas fa-layer-group"></i> Kelola Jenjang
            </a>

            <!-- Tombol Sinkron Gaji Massal -->
            <button
              class="btn btn-sm"
              id="btnSyncAllGajiPokok"
              title="Update semua gaji pokok anggota agar sesuai dengan Strata dan Jenjang"
              style="background:#ffccbc; color:#111; border:none;">
              <i class="fas fa-sync-alt"></i> Sinkron Gaji Massal
            </button>

            <!-- Tombol Import Excel -->
            <a href="import_anggota_sekolah.php"
              class="btn btn-sm"
              style="background:#b3e5fc; color:#111; border:none;">
              <i class="fas fa-file-excel"></i> Import Excel
            </a>
          </div>



          <!-- Filter Section: Data Guru/Karyawan -->
          <div class="card mb-4 shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
              <h6 class="m-0 fw-bold text-white">
                <i class="fas fa-search"></i> Filter Data Guru/Karyawan
              </h6>
            </div>
            <div class="card-body" style="background-color: #f8f9fa;">

              <form id="filterForm" method="GET" class="row gy-2 gx-3 align-items-center">
                <!-- Jenjang --><!-- Search Keyword -->
                <div class="col-auto">
                  <label for="filterKeyword" class="form-label mb-0"><strong>Pencarian:</strong></label>
                  <input type="text" class="form-control" id="filterKeyword" name="keyword" placeholder="Nama, NIP, Job Title...">
                </div>

                <div class="col-auto">
                  <label for="filterJenjang" class="form-label mb-0"><strong>Jenjang:</strong></label>
                  <select class="form-control" id="filterJenjang" name="jenjang">
                    <option value="">Semua Jenjang</option>
                    <?php
                    $jenjangList = getOrderedJenjang($conn); // ['TK' => 'Taman Kanak-Kanak', ...]
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
                    <option value="P" <?= (isset($_GET['role']) && $_GET['role'] === 'P') ? 'selected' : ''; ?>>Pendidik</option>
                    <option value="TK" <?= (isset($_GET['role']) && $_GET['role'] === 'TK') ? 'selected' : ''; ?>>Tenaga Kependidikan</option>
                    <option value="M" <?= (isset($_GET['role']) && $_GET['role'] === 'M') ? 'selected' : ''; ?>>Manajerial</option>
                  </select>
                </div>
                <!-- Status Kerja -->
                <div class="col-auto">
                  <label for="filterStatus" class="form-label mb-0"><strong>Status Kerja:</strong></label>
                  <select class="form-control" id="filterStatus" name="status_kerja">
                    <option value="">Semua Status</option>
                    <option value="Tetap" <?= (isset($_GET['status_kerja']) && $_GET['status_kerja'] === 'Tetap') ? 'selected' : ''; ?>>Tetap</option>
                    <option value="Kontrak" <?= (isset($_GET['status_kerja']) && $_GET['status_kerja'] === 'Kontrak') ? 'selected' : ''; ?>>Kontrak</option>
                  </select>
                </div>
                <!-- Tombol -->
                <div class="col-auto d-flex align-items-end">
                  <button type="button" id="btnApplyFilter" class="btn btn-primary me-2">
                    <i class="fas fa-filter"></i> Terapkan
                  </button>
                  <button type="button" id="btnResetFilter" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                  </button>
                </div>
              </form>
            </div>
          </div>
          <!-- End Filter Section -->

          <!-- Daftar Karyawan/Guru dalam Grid -->
          <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
              <h6 class="m-0 fw-bold text-white">
                <i class="fas fa-user"></i> Daftar Guru/Karyawan
              </h6>
            </div>
            <div class="card-body">
              <div id="employeeCards">
              </div>
              <nav class="mt-4">
                <ul class="pagination justify-content-center" id="paginationContainer">
                </ul>
              </nav>
            </div>
          </div>

          <div id="loadingSpinner">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
          </div>
        </div>
      </div>
      <!-- End Main Content -->

      <footer class="sticky-footer bg-white">
        <div class="container my-auto">
          <div class="copyright text-center my-auto">
            <span>&copy; <?php echo date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
          </div>
        </div>
      </footer>
    </div>
  </div>
  <!-- MODAL: Tambah Guru/Karyawan -->
  <div class="modal fade" id="modalAdd" tabindex="-1" aria-labelledby="modalAddLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <form id="add-guru-form" method="POST" class="needs-validation" novalidate enctype="multipart/form-data">
        <input type="hidden" name="case" value="CreateGuru">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title" id="modalAddLabel">Tambah Data Guru/Karyawan</h5>
            <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">

            <!-- SECTION: Foto Profil & KTP -->
            <div class="alert alert-primary fw-bold mb-3" role="alert">
              Foto Profil & KTP
            </div>
            <div class="row mb-4">
              <div class="col-md-6 text-center mb-3 mb-md-0">
                <label for="addFotoProfil" class="form-label fw-bold">Foto Profil</label>
                <input type="file" class="form-control" name="foto_profil" id="addFotoProfil" accept="image/*">
                <div class="mt-2">
                  <img id="previewAddFotoProfil" src="<?= getBaseUrl() ?>/assets/img/undraw_profile.svg"
                    style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border:2px solid #1976d2;">
                </div>
                <div class="small text-muted mt-1">Rasio 1:1, max 2MB</div>
              </div>
              <div class="col-md-6 text-center">
                <label for="addFotoKtp" class="form-label fw-bold">Foto KTP</label>
                <input type="file" class="form-control" name="foto_ktp" id="addFotoKtp" accept="image/*">
                <div class="mt-2">
                  <img id="previewAddFotoKtp" src="<?= getBaseUrl() ?>/assets/img/ktp_placeholder.png"
                    style="width: 160px; height: 120px; border-radius: 10px; object-fit: cover; border:2px solid #1976d2;">
                </div>
                <div class="small text-muted mt-1">Rasio 4:3, max 2MB</div>
              </div>
            </div>

            <!-- SECTION: Data Pekerjaan -->
            <div class="alert alert-primary fw-bold mb-3" role="alert">
              Data Pekerjaan
            </div>
            <div class="row mb-2">
              <div class="col-md-6">
                <label for="addNip">NIP <span class="text-danger">*</span></label>
                <input type="text" name="nip" id="addNip" class="form-control" required placeholder="Masukkan NIP">
                <div class="invalid-feedback">NIP wajib diisi.</div>
              </div>
              <div class="col-md-6">
                <label for="addNama">Nama <span class="text-danger">*</span></label>
                <input type="text" name="nama" id="addNama" class="form-control" required placeholder="Nama lengkap">
                <div class="invalid-feedback">Nama wajib diisi.</div>
              </div>
            </div>
            <div class="row mb-2">
              <div class="col-md-4">
                <label for="addJenjang">Jenjang <span class="text-danger">*</span></label>
                <select name="jenjang" id="addJenjang" class="form-control" required>
                  <option value="">-- Pilih Jenjang --</option>
                  <?php foreach ($jenjangList as $kode_jenjang => $nama_jenjang): ?>
                    <option value="<?= htmlspecialchars($kode_jenjang) ?>"><?= htmlspecialchars($nama_jenjang) ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Jenjang wajib dipilih.</div>
              </div>
              <div class="col-md-4 d-none" id="addUnitPenempatanContainer">
                <label for="addUnitPenempatan">Unit Penempatan <span class="text-danger">*</span></label>
                <input type="text" name="unit_penempatan" id="addUnitPenempatan" class="form-control" placeholder="Contoh: Perpustakaan, TU, dsb">
                <div class="invalid-feedback">Unit penempatan wajib diisi untuk Jenjang UMUM.</div>
              </div>
              <div class="col-md-4">
                <label for="addRole">Role <span class="text-danger">*</span></label>
                <select name="role" id="addRole" class="form-control" required>
                  <option value="">-- Pilih Role --</option>
                  <option value="P">Pendidik (Guru)</option>
                  <option value="TK">Tenaga Kependidikan</option>
                  <option value="M">Manajerial</option>
                </select>
                <div class="invalid-feedback">Role wajib dipilih.</div>
              </div>
              <div class="col-md-4">
                <label for="addJobTitle">Job Title</label>
                <input type="text" name="job_title" id="addJobTitle" class="form-control" placeholder="Contoh: Guru, Staff, dll">
              </div>
            </div>
            <div class="row mb-2">
              <div class="col-md-4">
                <label for="addRemark">Remark</label>
                <input type="text" name="remark" id="addRemark" class="form-control" placeholder="Catatan tambahan">
              </div>
              <div class="col-md-4">
                <label for="addPendidikan">Pendidikan Lengkap</label>
                <input type="text" name="pendidikan" id="addPendidikan" class="form-control" placeholder="Contoh: S2 Sistem Informasi, S1 Manajemen">
              </div>
              <div class="col-md-4">
                <label for="addStrata">Strata <span class="text-danger">*</span></label>
                <select name="strata" id="addStrata" class="form-control" required>
                  <option value="">-- Pilih Strata --</option>
                  <option value="D3">D3</option>
                  <option value="S1">S1</option>
                  <option value="S2">S2</option>
                  <option value="S3">S3</option>
                </select>
                <div class="invalid-feedback">Strata wajib dipilih.</div>
              </div>
            </div>
            <div class="row mb-2">
              <div class="col-md-4">
                <label for="addStatusKerja">Status Kerja <span class="text-danger">*</span></label>
                <select name="status_kerja" id="addStatusKerja" class="form-control" required>
                  <option value="Tetap" selected>Tetap</option>
                  <option value="Kontrak">Kontrak</option>
                </select>
              </div>
              <div class="col-md-4 add-kontrak-only d-none" id="addLamaKontrakContainer">
                <label for="addLamaKontrak">Lama Kontrak (bulan)</label>
                <select name="lama_kontrak" id="addLamaKontrak" class="form-control">
                  <option value="12" selected>12</option>
                  <option value="24">24</option>
                  <option value="36">36</option>
                </select>
              </div>
              <div class="col-md-4 add-kontrak-only d-none" id="addTglSelesaiContainer">
                <label for="addTglSelesai">Tanggal Selesai Kontrak</label>
                <input type="date" id="addTglSelesai" class="form-control" readonly>
              </div>
            </div>
            <div class="row mb-4">
              <div class="col-md-4">
                <label for="addJoinStart">Tanggal Bergabung <span class="text-danger">*</span></label>
                <input type="date" name="join_start" id="addJoinStart" class="form-control" required>
                <div class="invalid-feedback">Tanggal Bergabung wajib diisi.</div>
              </div>
            </div>

            <!-- SECTION: Data Pribadi -->
            <div class="alert alert-primary fw-bold mb-3" role="alert">
              Data Pribadi
            </div>
            <div class="row mb-2">
              <div class="col-md-4">
                <label for="addJK">Jenis Kelamin</label>
                <select name="jk" id="addJK" class="form-control">
                  <option value="">-- Pilih Jenis Kelamin --</option>
                  <option value="L">Laki-laki</option>
                  <option value="P">Perempuan</option>
                </select>
              </div>
              <div class="col-md-4">
                <label for="addTglLahir">Tanggal Lahir</label>
                <input type="date" name="tgl_lahir" id="addTglLahir" class="form-control">
              </div>
              <div class="col-md-4">
                <label for="addUsia">Usia</label>
                <input type="number" name="usia" id="addUsia" class="form-control">
              </div>
            </div>
            <div class="row mb-4">
              <div class="col-md-4">
                <label for="addReligion">Agama</label>
                <input type="text" name="religion" id="addReligion" class="form-control">
              </div>
            </div>

            <!-- SECTION: Data Kontak -->
            <div class="alert alert-primary fw-bold mb-3" role="alert">
              Data Kontak
            </div>
            <div class="row mb-2">
              <div class="col-md-6">
                <label for="addAlamatDomisili">Alamat Domisili</label>
                <textarea name="alamat_domisili" id="addAlamatDomisili" class="form-control"></textarea>
              </div>
              <div class="col-md-6">
                <label for="addAlamatKTP">Alamat KTP</label>
                <textarea name="alamat_ktp" id="addAlamatKTP" class="form-control"></textarea>
              </div>
            </div>
            <div class="row mb-4">
              <div class="col-md-4">
                <label for="addNoRekening">No Rekening</label>
                <input type="text" name="no_rekening" id="addNoRekening" class="form-control">
              </div>
              <div class="col-md-4">
                <label for="addNoHP">No Handphone</label>
                <input type="text" name="no_hp" id="addNoHP" class="form-control" placeholder="08xxx">
              </div>
              <div class="col-md-4">
                <label for="addEmail">Email</label>
                <input type="email" name="email" id="addEmail" class="form-control" placeholder="contoh@domain.com">
              </div>
            </div>

            <!-- SECTION: Data Keluarga & Lainnya -->
            <div class="alert alert-primary fw-bold mb-3" role="alert">
              Data Keluarga & Lainnya
            </div>
            <div class="row mb-2">
              <div class="col-md-4">
                <label for="addStatusPerkawinan">Status Perkawinan <span class="text-danger">*</span></label>
                <select name="status_perkawinan" id="addStatusPerkawinan" class="form-control" required>
                  <option value="">-- Pilih Status --</option>
                  <option value="Menikah">Menikah</option>
                  <option value="Belum Menikah">Belum Menikah</option>
                </select>
                <div class="invalid-feedback">Status Perkawinan wajib dipilih.</div>
              </div>
              <div class="col-md-4">
                <label for="addNamaPasangan">Nama Pasangan</label>
                <input type="text" name="nama_pasangan" id="addNamaPasangan" class="form-control" placeholder="Nama Pasangan">
              </div>
              <div class="col-md-4">
                <label for="addJumlahAnak">Jumlah Anak</label>
                <input type="number" name="jumlah_anak" id="addJumlahAnak" class="form-control" placeholder="Jumlah Anak">
              </div>
            </div>
            <div class="row mb-4">
              <div class="col-md-4">
                <label for="addNamaAnak1">Nama Anak 1</label>
                <input type="text" name="nama_anak_1" id="addNamaAnak1" class="form-control" placeholder="Nama Anak 1">
              </div>
              <div class="col-md-4">
                <label for="addNamaAnak2">Nama Anak 2</label>
                <input type="text" name="nama_anak_2" id="addNamaAnak2" class="form-control" placeholder="Nama Anak 2">
              </div>
              <div class="col-md-4">
                <label for="addNamaAnak3">Nama Anak 3</label>
                <input type="text" name="nama_anak_3" id="addNamaAnak3" class="form-control" placeholder="Nama Anak 3">
              </div>
            </div>

            <!-- SECTION: FASKES KESEHATAN -->
            <div class="alert alert-primary fw-bold mb-3" role="alert">
              FASKES KESEHATAN
            </div>
            <div class="row mb-2">
              <div class="col-md-4">
                <label for="addFaskesBpjs">BPJS</label>
                <select name="faskes_bpjs" id="addFaskesBpjs" class="form-control">
                  <option value="0" selected>Tidak</option>
                  <option value="1">Terdaftar</option>
                </select>
              </div>
              <div class="col-md-4">
                <label for="addFaskesInhealth">IN HEALTH</label>
                <select name="faskes_inhealth" id="addFaskesInhealth" class="form-control">
                  <option value="0" selected>Tidak</option>
                  <option value="1">Terdaftar</option>
                </select>
              </div>
              <div class="col-md-4">
                <label for="addFaskesKet">Keterangan Faskes</label>
                <input type="text" name="faskes_ket" id="addFaskesKet" class="form-control" maxlength="100" placeholder="Keterangan lain-lain">
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success">
              <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
              Simpan
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>


  <!-- MODAL: Edit Guru/Karyawan -->
  <div class="modal fade" id="modalEdit" tabindex="-1" aria-labelledby="modalEditLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <form id="edit-guru-form" method="POST" class="needs-validation" novalidate enctype="multipart/form-data">
        <input type="hidden" name="case" value="UpdateGuru">
        <input type="hidden" name="id" id="editId">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title" id="modalEditLabel">Edit Data Guru/Karyawan</h5>
            <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">

            <!-- SECTION: Foto Profil & KTP -->
            <div class="alert alert-primary fw-bold mb-3" role="alert">
              Foto Profil & KTP
            </div>
            <div class="row mb-4">
              <div class="col-md-6 text-center mb-3 mb-md-0">
                <label for="editFotoProfil" class="form-label fw-bold">Foto Profil</label>
                <input type="file" class="form-control" name="foto_profil" id="editFotoProfil" accept="image/*">
                <div class="mt-2">
                  <img id="previewEditFotoProfil" src="<?= getBaseUrl() ?>/assets/img/undraw_profile.svg"
                    style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border:2px solid #1976d2;">
                </div>
                <div class="small text-muted mt-1">Rasio 1:1, max 2MB</div>
              </div>
              <div class="col-md-6 text-center">
                <label for="editFotoKtp" class="form-label fw-bold">Foto KTP</label>
                <input type="file" class="form-control" name="foto_ktp" id="editFotoKtp" accept="image/*">
                <div class="mt-2">
                  <img id="previewEditFotoKtp" src="<?= getBaseUrl() ?>/assets/img/ktp_placeholder.png"
                    style="width: 160px; height: 120px; border-radius: 10px; object-fit: cover; border:2px solid #1976d2;">
                </div>
                <div class="small text-muted mt-1">Rasio 4:3, max 2MB</div>
              </div>
            </div>

            <!-- SECTION: Data Pekerjaan -->
            <div class="alert alert-primary fw-bold mb-3" role="alert">
              Data Pekerjaan
            </div>
            <div class="row mb-2">
              <div class="col-md-6">
                <label for="editNip">NIP <span class="text-danger">*</span></label>
                <input type="text" name="nip" id="editNip" class="form-control" required>
                <div class="invalid-feedback">NIP wajib diisi.</div>
              </div>
              <div class="col-md-6">
                <label for="editUid">UID</label>
                <input type="text" name="uid" id="editUid" class="form-control" readonly>
              </div>
            </div>
            <div class="row mb-2">
              <div class="col-md-6">
                <label for="editNama">Nama <span class="text-danger">*</span></label>
                <input type="text" name="nama" id="editNama" class="form-control" required>
                <div class="invalid-feedback">Nama wajib diisi.</div>
              </div>
              <div class="col-md-6">
                <label for="editJobTitle">Job Title</label>
                <input type="text" name="job_title" id="editJobTitle" class="form-control" placeholder="Contoh: Guru, Staff, dll">
              </div>
            </div>
            <div class="row mb-2">
              <div class="col-md-4">
                <label for="editJenjang">Jenjang <span class="text-danger">*</span></label>
                <select name="jenjang" id="editJenjang" class="form-control" required>
                  <option value="">-- Pilih Jenjang --</option>
                  <?php foreach ($jenjangList as $kode_jenjang => $nama_jenjang): ?>
                    <option value="<?= htmlspecialchars($kode_jenjang) ?>"><?= htmlspecialchars($nama_jenjang) ?></option>
                  <?php endforeach; ?>
                </select>

                <div class="invalid-feedback">Jenjang wajib dipilih.</div>
              </div>
              <div class="col-md-4 d-none" id="editUnitPenempatanContainer">
                <label for="editUnitPenempatan">Unit Penempatan <span class="text-danger">*</span></label>
                <input type="text" name="unit_penempatan" id="editUnitPenempatan" class="form-control" placeholder="Contoh: Perpustakaan, TU, dsb">
                <div class="invalid-feedback">Unit penempatan wajib diisi untuk Jenjang UMUM.</div>
              </div>

              <div class="col-md-4">
                <label for="editRole">Role <span class="text-danger">*</span></label>
                <select name="role" id="editRole" class="form-control" required>
                  <option value="">-- Pilih Role --</option>
                  <option value="P">Pendidik</option>
                  <option value="TK">Tenaga Kependidikan</option>
                  <option value="M">Manajerial</option>
                </select>
                <div class="invalid-feedback">Role wajib dipilih.</div>
              </div>
              <div class="col-md-4">
                <label for="editRemark">Remark</label>
                <input type="text" name="remark" id="editRemark" class="form-control" placeholder="Catatan tambahan">
              </div>
            </div>
            <div class="row mb-2">
              <div class="col-md-8">
                <label for="editPendidikan">Pendidikan Lengkap</label>
                <input type="text" name="pendidikan" id="editPendidikan" class="form-control" placeholder="Contoh: S1 Matematika, S2 Manajemen">
              </div>
              <div class="col-md-4">
                <label for="editStrata">Strata <span class="text-danger">*</span></label>
                <select name="strata" id="editStrata" class="form-control" required>
                  <option value="">-- Pilih Strata --</option>
                  <option value="D3">D3</option>
                  <option value="S1">S1</option>
                  <option value="S2">S2</option>
                  <option value="S3">S3</option>
                </select>
                <div class="invalid-feedback">Strata wajib dipilih.</div>
              </div>
            </div>
            <div class="row mb-2">
              <div class="col-md-4">
                <label for="editStatusKerja">Status Kerja <span class="text-danger">*</span></label>
                <select name="status_kerja" id="editStatusKerja" class="form-control" required>
                  <option value="Tetap">Tetap</option>
                  <option value="Kontrak">Kontrak</option>
                </select>
              </div>
              <div class="col-md-4 edit-kontrak-only d-none" id="editLamaKontrakContainer">
                <label for="editLamaKontrak">Lama Kontrak (bulan)</label>
                <select name="lama_kontrak" id="editLamaKontrak" class="form-control">
                  <option value="12">12</option>
                  <option value="24">24</option>
                  <option value="36">36</option>
                </select>
              </div>
              <div class="col-md-4 edit-kontrak-only d-none" id="editTglSelesaiContainer">
                <label for="editTglSelesai">Tanggal Selesai Kontrak</label>
                <input type="date" id="editTglSelesai" class="form-control" readonly>
              </div>
            </div>
            <div class="row mb-4">
              <div class="col-md-4">
                <label for="editSudahKontrak">Sudah Kontrak (tahun)</label>
                <input type="number" name="sudah_kontrak" id="editSudahKontrak" class="form-control" readonly>
              </div>
              <div class="col-md-4">
                <label for="editJoinStart">Tanggal Bergabung</label>
                <input type="date" name="join_start" id="editJoinStart" class="form-control">
              </div>
            </div>

            <!-- SECTION: Data Pribadi -->
            <div class="alert alert-primary fw-bold mb-3" role="alert">
              Data Pribadi
            </div>
            <div class="row mb-2">
              <div class="col-md-4">
                <label for="editJK">Jenis Kelamin</label>
                <select name="jk" id="editJK" class="form-control">
                  <option value="">-- Pilih --</option>
                  <option value="L">Laki-laki</option>
                  <option value="P">Perempuan</option>
                </select>
              </div>
              <div class="col-md-4">
                <label for="editTglLahir">Tanggal Lahir</label>
                <input type="date" name="tgl_lahir" id="editTglLahir" class="form-control">
              </div>
              <div class="col-md-4">
                <label for="editUsia">Usia</label>
                <input type="number" name="usia" id="editUsia" class="form-control">
              </div>
            </div>
            <div class="row mb-4">
              <div class="col-md-4">
                <label for="editReligion">Agama</label>
                <input type="text" name="religion" id="editReligion" class="form-control">
              </div>
            </div>

            <!-- SECTION: Data Kontak -->
            <div class="alert alert-primary fw-bold mb-3" role="alert">
              Data Kontak
            </div>
            <div class="row mb-2">
              <div class="col-md-6">
                <label for="editAlamatDomisili">Alamat Domisili</label>
                <textarea name="alamat_domisili" id="editAlamatDomisili" class="form-control"></textarea>
              </div>
              <div class="col-md-6">
                <label for="editAlamatKTP">Alamat KTP</label>
                <textarea name="alamat_ktp" id="editAlamatKTP" class="form-control"></textarea>
              </div>
            </div>
            <div class="row mb-4">
              <div class="col-md-4">
                <label for="editNoRekening">No Rekening</label>
                <input type="text" name="no_rekening" id="editNoRekening" class="form-control">
              </div>
              <div class="col-md-4">
                <label for="editNoHP">No HP</label>
                <input type="text" name="no_hp" id="editNoHP" class="form-control">
              </div>
              <div class="col-md-4">
                <label for="editEmail">Email</label>
                <input type="email" name="email" id="editEmail" class="form-control">
              </div>
            </div>

            <!-- SECTION: Data Keluarga & Lainnya -->
            <div class="alert alert-primary fw-bold mb-3" role="alert">
              Data Keluarga & Lainnya
            </div>
            <div class="row mb-2">
              <div class="col-md-4">
                <label for="editStatusPerkawinan">Status Perkawinan <span class="text-danger">*</span></label>
                <select name="status_perkawinan" id="editStatusPerkawinan" class="form-control" required>
                  <option value="">-- Pilih Status --</option>
                  <option value="Menikah">Menikah</option>
                  <option value="Belum Menikah">Belum Menikah</option>
                </select>
                <div class="invalid-feedback">Status Perkawinan wajib dipilih.</div>
              </div>
              <div class="col-md-4">
                <label for="editNamaPasangan">Nama Pasangan</label>
                <input type="text" name="nama_pasangan" id="editNamaPasangan" class="form-control">
              </div>
              <div class="col-md-4">
                <label for="editJumlahAnak">Jumlah Anak</label>
                <input type="number" name="jumlah_anak" id="editJumlahAnak" class="form-control">
              </div>
            </div>
            <div class="row mb-4">
              <div class="col-md-4">
                <label for="editNamaAnak1">Nama Anak 1</label>
                <input type="text" name="nama_anak_1" id="editNamaAnak1" class="form-control">
              </div>
              <div class="col-md-4">
                <label for="editNamaAnak2">Nama Anak 2</label>
                <input type="text" name="nama_anak_2" id="editNamaAnak2" class="form-control">
              </div>
              <div class="col-md-4">
                <label for="editNamaAnak3">Nama Anak 3</label>
                <input type="text" name="nama_anak_3" id="editNamaAnak3" class="form-control">
              </div>
            </div>

            <!-- SECTION: FASKES KESEHATAN -->
            <div class="alert alert-primary fw-bold mb-3" role="alert">
              FASKES KESEHATAN
            </div>
            <div class="row mb-4">
              <div class="col-md-4">
                <label for="editFaskesBpjs">BPJS</label>
                <select name="faskes_bpjs" id="editFaskesBpjs" class="form-control">
                  <option value="0">Tidak</option>
                  <option value="1">Terdaftar</option>
                </select>
              </div>
              <div class="col-md-4">
                <label for="editFaskesInhealth">IN HEALTH</label>
                <select name="faskes_inhealth" id="editFaskesInhealth" class="form-control">
                  <option value="0">Tidak</option>
                  <option value="1">Terdaftar</option>
                </select>
              </div>
              <div class="col-md-4">
                <label for="editFaskesKet">Keterangan Faskes</label>
                <input type="text" name="faskes_ket" id="editFaskesKet" class="form-control" maxlength="100" placeholder="Keterangan lain-lain">
              </div>
            </div>

            <!-- SECTION: Ubah Password -->
            <div class="alert alert-primary fw-bold mb-3" role="alert">
              Ubah Password (Opsional)
            </div>
            <div class="row mb-3">
              <div class="col-md-12">
                <label for="editPassword">Password Baru</label>
                <input type="password" name="password" id="editPassword" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password">
              </div>
            </div>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">
              <span class="spinner-border spinner-border-sm d-none"></span>
              Update
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL: View Detail (Dengan Foto Profil & Foto KTP) -->
  <div class="modal fade" id="modalView" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Detail Data Guru/Karyawan</h5>
          <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
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
              <th>NIP</th>
              <td id="detailNip"></td>
            </tr>
            <tr>
              <th>UID</th>
              <td id="detailUid"></td>
            </tr>
            <tr>
              <th>Nama</th>
              <td id="detailNama"></td>
            </tr>
            <tr>
              <th>Jenjang</th>
              <td id="detailJenjang"></td>
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
              <th>Salary Index</th>
              <td id="detailSalaryLevel"></td>
            </tr>
          </table>

          <!-- SECTION: Data Pribadi -->
          <div class="alert alert-primary fw-bold mb-3" role="alert">
            Data Pribadi
          </div>
          <table class="table table-sm mb-4">
            <tr>
              <th>Jenis Kelamin</th>
              <td id="detailJK"></td>
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
              <td id="detailReligion"></td>
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

          <!-- SECTION: Data Keluarga & Lainnya -->
          <div class="alert alert-primary fw-bold mb-3" role="alert">
            Data Keluarga & Lainnya
          </div>
          <table class="table table-sm mb-2">
            <tr>
              <th>Remark</th>
              <td id="detailRemark"></td>
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

            <!-- SECTION: FASKES KESEHATAN -->
            <div class="alert alert-primary fw-bold mb-3" role="alert">
              FASKES KESEHATAN
            </div>
            <table class="table table-sm mb-4">
              <tr>
                <th>BPJS</th>
                <td id="detailFaskesBpjs"></td>
              </tr>
              <tr>
                <th>IN HEALTH</th>
                <td id="detailFaskesInhealth"></td>
              </tr>
              <tr>
                <th>Keterangan Faskes</th>
                <td id="detailFaskesKet"></td>
              </tr>
            </table>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
        </div>
      </div>
    </div>
  </div>


  <!-- MODAL: Delete -->
  <div class="modal fade" id="modalDelete" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="delete-guru-form">
          <input type="hidden" name="case" value="DeleteGuru">
          <input type="hidden" name="id" id="delId">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
          <div class="modal-header">
            <h5 class="modal-title">Hapus Data Guru/Karyawan</h5>
            <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <p>Anda yakin ingin menghapus data berikut?</p>
            <p><strong id="delNama"></strong></p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-danger">
              <span class="spinner-border spinner-border-sm d-none"></span>
              Hapus
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL: Atur Gaji Pokok -->
  <div class="modal fade" id="modalGajiPokok" tabindex="-1" aria-labelledby="modalGajiPokokLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
      <form id="gaji-pokok-form" method="POST" class="modal-content">
        <input type="hidden" name="case" value="update_gaji_pokok">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="modalGajiPokokLabel">Atur Gaji Pokok</h5>
          <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <div class="text-center mb-3">
            <!-- Ubah tombol ini menjadi tanpa data-bs-toggle/data-bs-target -->
            <button type="button" id="btnGajiStrataGuru" class="btn btn-secondary me-2">
              <i class="fas fa-chart-bar"></i> Atur Gaji Strata Guru
            </button>
            <button type="button" id="btnGajiStrataKaryawan" class="btn btn-secondary">
              <i class="fas fa-chart-bar"></i> Atur Gaji Strata Karyawan
            </button>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success">
            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
            Simpan
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL: Atur Gaji Strata Guru -->
  <div class="modal fade" id="modalGajiStrataGuru" tabindex="-1" aria-labelledby="modalGajiStrataGuruLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <form id="gaji-strata-form-guru" method="POST" class="modal-content">
        <input type="hidden" name="case" value="update_gaji_strata_guru">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="modalGajiStrataGuruLabel">Atur Gaji Pokok Berdasarkan Strata Pendidikan (Guru)</h5>
          <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>Jenjang</th>
                  <th>Strata Pendidikan</th>
                  <th>Gaji Pokok (Rp)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($guruConfig as $jenjang => $strataArr): ?>
                  <?php $first = true; ?>
                  <?php foreach ($strataArr as $strata): ?>
                    <tr>
                      <?php if ($first): ?>
                        <td rowspan="<?= count($strataArr) ?>"><?= htmlspecialchars($jenjang) ?></td>
                        <?php $first = false; ?>
                      <?php endif; ?>
                      <td><?= htmlspecialchars($strata) ?></td>
                      <td>
                        <input type="number" step="0.01"
                          name="<?= strtolower(str_replace('/', '', $jenjang)) . '_' . strtolower($strata) ?>"
                          class="form-control"
                          value="<?= isset($guruStrata[$jenjang][$strata]) ? $guruStrata[$jenjang][$strata] : '' ?>" required>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">
            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
            Simpan
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL: Atur Gaji Strata Karyawan -->
  <div class="modal fade" id="modalGajiStrataKaryawan" tabindex="-1" aria-labelledby="modalGajiStrataKaryawanLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <form id="gaji-strata-form-karyawan" method="POST" class="modal-content">
        <input type="hidden" name="case" value="update_gaji_strata_karyawan">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="modalGajiStrataKaryawanLabel">Atur Gaji Pokok Berdasarkan Strata Pendidikan (Karyawan)</h5>
          <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>Jenjang</th>
                  <th>Strata Pendidikan</th>
                  <th>Gaji Pokok (Rp)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($karyawanConfig as $jenjang => $strataArr): ?>
                  <?php $first = true; ?>
                  <?php foreach ($strataArr as $strata): ?>
                    <tr>
                      <?php if ($first): ?>
                        <td rowspan="<?= count($strataArr) ?>"><?= htmlspecialchars($jenjang) ?></td>
                        <?php $first = false; ?>
                      <?php endif; ?>
                      <td><?= htmlspecialchars($strata) ?></td>
                      <td>
                        <input type="number" step="0.01"
                          name="<?= strtolower(str_replace('/', '', $jenjang)) . '_' . strtolower($strata) ?>"
                          class="form-control"
                          value="<?= isset($karyawanStrata[$jenjang][$strata]) ? $karyawanStrata[$jenjang][$strata] : '' ?>" required>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">
            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
            Simpan
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- JavaScript Dependencies -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Inject konfigurasi Strata dari PHP ke JS -->
  <script>
    const GURU_CONFIG = <?= json_encode($guruConfig); ?>;
    const KARYAWAN_CONFIG = <?= json_encode($karyawanConfig); ?>;
  </script>

  <script>
    /* ============================================================
   =============== [1] TOAST & BADGE UTILS ====================
   ============================================================ */
    // Inisialisasi Toast
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
    // Badge status kerja
    function getStatusBadge(status) {
      let s = (status || '').toLowerCase();
      if (s === 'tetap') return '<span class="badge bg-success">Tetap</span>';
      if (s === 'kontrak') return '<span class="badge bg-warning text-dark">Kontrak</span>';
      return `<span class="badge bg-secondary">${status||''}</span>`;
    }
    // Badge role
    function getBadgeRole(role) {
      switch ((role || '').trim()) {
        case 'P':
          return '<span class="badge" style="background:#007bff;color:#fff;">Pendidik</span>';
        case 'TK':
          return '<span class="badge" style="background:#17a2b8;color:#212529;">Tenaga Kependidikan</span>';
        case 'M':
          return '<span class="badge" style="background:#dc3545;color:#fff;">Manajerial</span>';
        default:
          return `<span class="badge bg-secondary">${role||''}</span>`;
      }
    }

    /* ============================================================
       =============== [2] SYNC STRATA DROPDOWN ===================
       ============================================================ */
    function syncStrataOptions(jenjang, role, prefix = '', callback = null) {
      let sel = $(`#${prefix}Strata`);
      sel.empty().append('<option value="">-- Pilih Strata --</option>');

      jenjang = (jenjang || '').trim().toUpperCase();
      role = (role || '').trim();

      let arr = [];
      if (role === 'P' && GURU_CONFIG[jenjang]) {
        arr = GURU_CONFIG[jenjang];
      }
      if ((role === 'TK' || role === 'M') && KARYAWAN_CONFIG[jenjang]) {
        arr = KARYAWAN_CONFIG[jenjang];
      }

      arr.forEach(s => sel.append(`<option value="${s}">${s}</option>`));

      // Jalankan callback jika ada, setelah semua option selesai di-append
      if (typeof callback === 'function') callback();
    }



    /* ============================================================
       =============== [3] MAIN JQUERY HANDLER ===================
       ============================================================ */
    $(document).ready(function() {
      // --- [3.1] Inisialisasi & Filter Tombol ---
      let currentPage = 1,
        pageSize = 10,
        baseUrl = "<?= getBaseUrl(); ?>";
      loadGuru(1);
      $('#btnApplyFilter').click(() => loadGuru(1));
      $('#btnResetFilter').click(function() {
        $('#filterForm')[0].reset();
        loadGuru(1);
      });

      // --- [3.2] STRATA pada Modal Add/Edit ---
      // Untuk form TAMBAH
      $('#addJenjang, #addRole').change(function() {
        syncStrataOptions($('#addJenjang').val(), $('#addRole').val(), 'add');
      });
      $('#modalAdd').on('shown.bs.modal', function() {
        syncStrataOptions($('#addJenjang').val(), $('#addRole').val(), 'add');
      });

      // Untuk form EDIT
      $('#editJenjang, #editRole').change(function() {
        syncStrataOptions($('#editJenjang').val(), $('#editRole').val(), 'edit');
      });
      $('#modalEdit').on('shown.bs.modal', function() {
        syncStrataOptions($('#editJenjang').val(), $('#editRole').val(), 'edit');
        // Set value strata lama jika sedang edit (opsional)
        setTimeout(() => {
          $('#editStrata').val($('#editStrata').data('old') || $('#editStrata').val() || '');
        }, 100);
      });


      // --- [3.3] AJAX LOAD & RENDER CARDS ---
      function loadGuru(page) {
        currentPage = page;
        let start = (page - 1) * pageSize;
        $.ajax({
          url: "manage_guru_karyawan.php?ajax=1",
          type: "POST",
          data: {
            case: 'LoadingGuru',
            start: start,
            length: pageSize,
            jenjang: $('#filterJenjang').val(),
            role: $('#filterRole').val(),
            status_kerja: $('#filterStatus').val(),
            keyword: $('#filterKeyword').val(),
            csrf_token: "<?= htmlspecialchars($csrf_token); ?>"
          },
          dataType: "json",
          beforeSend: () => $('#loadingSpinner').show(),
          success: res => {
            $('#loadingSpinner').hide();
            if (res.data) {
              generateCards(res.data);
              generatePagination(res.recordsTotal);
            } else {
              showToast('Data kosong atau gagal di-load.', 'warning');
              $('#employeeCards,#paginationContainer').empty();
            }
          },
          error: () => {
            $('#loadingSpinner').hide();
            showToast('Terjadi kesalahan saat memuat data.', 'error');
          }
        });
      }

      function generateCards(data) {
        let c = $('#employeeCards');
        c.empty();
        data.forEach(item => {
          let photo = item.foto_profil ? item.foto_profil : baseUrl + "/assets/img/undraw_profile.svg";

          // Badge untuk jenjang
          let jenjangBadge = '';
if (item.jenjang) {
  if (item.jenjang.toUpperCase() === 'UMUM') {
    jenjangBadge = `<span class="badge bg-secondary me-1">Umum</span>`;
  } else {
    // Pakai warna dari backend (dari DB)
    let bg = item.jenjang_bg || '#ececec';
    let fg = item.jenjang_fg || '#333';
    jenjangBadge = `<span class="badge me-1" style="background:${bg};color:${fg};">${item.jenjang}</span>`;
  }
}

          // Badge untuk unit penempatan (khusus UMUM)
          let unitBadge = '';
          if ((item.jenjang && item.jenjang.toUpperCase() === 'UMUM') && item.unit_penempatan) {
            unitBadge = `<span class="badge" style="background:#0097A7;color:#fff;margin-left:3px;">${item.unit_penempatan}</span>`;
          }

          // Role badge
          let roleBadge = getBadgeRole(item.role);

          // ========== PERUBAHAN UTAMA DI SINI ==========
          // Bagian job_title, gunakan style transparan, bold, rounded
          let jobTitleDisplay = '';
          if (item.job_title) {
            jobTitleDisplay = `
        <span 
          class="d-inline-block mb-2 px-3 py-1 fw-semibold"
          style="background:rgba(0,123,255,0.13); color:#212529; border-radius:1rem; font-size:15px;">
          ${item.job_title}
        </span>
      `;
          }
          // ========== END PERUBAHAN ==========

          let html = `
      <div class="card employee-card h-100">
        <div class="card-body text-center pt-4 pb-3">
          <img src="${photo}" class="employee-photo rounded-circle mb-3">
          <h5 class="mb-1">${item.nama}</h5>
          <p class="text-muted small mb-2">NIP: ${item.nip}</p>
          ${jobTitleDisplay}
          <div class="small mb-1">
            ${jenjangBadge}${unitBadge} ${roleBadge}
          </div>
          <div class="status-label mb-1">
            Status: ${getStatusBadge(item.status_kerja)}
          </div>
          <div class="d-grid gap-2">
            <button class="btn btn-primary btn-sm btn-view" data-id="${item.id}">
              <i class="fas fa-eye"></i> Detail
            </button>
            <button class="btn btn-warning btn-sm btn-edit" data-id="${item.id}">
              <i class="fas fa-pencil-alt"></i> Edit
            </button>
            <button class="btn btn-danger btn-sm btn-delete" data-id="${item.id}">
              <i class="fas fa-trash-alt"></i> Hapus
            </button>
          </div>
        </div>
      </div>`;
          c.append(html);
        });
      }


      function generatePagination(total) {
        let pages = Math.ceil(total / pageSize),
          ul = $('#paginationContainer').empty();
        for (let i = 1; i <= pages; i++) {
          let li = $('<li>').addClass('page-item' + (i === currentPage ? ' active' : ''))
            .append($('<a>').addClass('page-link').attr('href', '#').text(i)
              .click(e => {
                e.preventDefault();
                loadGuru(i);
              }));
          ul.append(li);
        }
      }

      $('#filterKeyword').on('keydown', function(e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          loadGuru(1);
        }
      });

      // --- [3.4] TOGGLE KONTRAK FIELDS ---
      function toggleKontrak(pref) {
        let st = $(`#${pref}StatusKerja`).val();
        if (st === 'Kontrak') {
          $(`.${pref}-kontrak-only`).removeClass('d-none');
          $(`#${pref}LamaKontrak`).prop('required', true);
        } else {
          $(`.${pref}-kontrak-only`).addClass('d-none');
          $(`#${pref}LamaKontrak`).prop('required', false).val('');
          $(`#${pref}TglSelesai`).val('');
        }
      }
      $('#addStatusKerja').change(() => toggleKontrak('add'));
      $('#modalAdd').on('shown.bs.modal', () => toggleKontrak('add'));
      $('#editStatusKerja').change(() => toggleKontrak('edit'));
      $('#modalEdit').on('shown.bs.modal', () => toggleKontrak('edit'));

      // --- [3.5] STATUS PERKAWINAN ---
      $('#addStatusPerkawinan').change(function() {
        if (this.value === 'Belum Menikah') {
          $('#addNamaPasangan,#addJumlahAnak,#addNamaAnak1,#addNamaAnak2,#addNamaAnak3')
            .prop('disabled', true).val('-');
        } else {
          $('#addNamaPasangan,#addJumlahAnak,#addNamaAnak1,#addNamaAnak2,#addNamaAnak3')
            .prop('disabled', false).val('');
        }
      });
      $('#editStatusPerkawinan').change(function() {
        if (this.value === 'Belum Menikah') {
          $('#editNamaPasangan,#editJumlahAnak,#editNamaAnak1,#editNamaAnak2,#editNamaAnak3')
            .prop('disabled', true).val('-');
        } else {
          $('#editNamaPasangan,#editJumlahAnak,#editNamaAnak1,#editNamaAnak2,#editNamaAnak3')
            .prop('disabled', false).val('');
        }
      });

      // --- [3.6] IMAGE PREVIEW ---
      function previewImage(inputSel, imgSel) {
        $(inputSel).change(function() {
          if (this.files && this.files[0]) {
            let reader = new FileReader();
            reader.onload = e => $(imgSel).attr('src', e.target.result);
            reader.readAsDataURL(this.files[0]);
          }
        });
      }
      previewImage("#addFotoProfil", "#previewAddFotoProfil");
      previewImage("#addFotoKtp", "#previewAddFotoKtp");
      previewImage("#editFotoProfil", "#previewEditFotoProfil");
      previewImage("#editFotoKtp", "#previewEditFotoKtp");

      // --- [3.7] PREVIEW TANGGAL SELESAI KONTRAK ---
      function previewTglSelesai(prefix) {
        let j = $(`#${prefix}JoinStart`).val(),
          l = parseInt($(`#${prefix}LamaKontrak`).val());
        if (j && l) {
          let d = new Date(j);
          d.setMonth(d.getMonth() + l);
          d.setDate(d.getDate() - 1);
          let yyyy = d.getFullYear(),
            mm = (d.getMonth() + 1).toString().padStart(2, '0'),
            dd = d.getDate().toString().padStart(2, '0');
          $(`#${prefix}TglSelesai`).val(`${yyyy}-${mm}-${dd}`);
        } else {
          $(`#${prefix}TglSelesai`).val('');
        }
      }
      $('#addJoinStart,#addLamaKontrak').change(() => {
        toggleKontrak('add');
        previewTglSelesai('add');
      });
      $('#editJoinStart,#editLamaKontrak').change(() => {
        toggleKontrak('edit');
        previewTglSelesai('edit');
      });
      $('#modalAdd').on('shown.bs.modal', () => previewTglSelesai('add'));
      $('#modalEdit').on('shown.bs.modal', () => {
        toggleKontrak('edit');
        previewTglSelesai('edit');
      });

      // --- [3.8] AUTO HITUNG USIA ---
      function autoCalcAge(dobSel, ageSel) {
        let dob = $(dobSel).val();
        if (!dob) {
          $(ageSel).val('');
          return;
        }
        let b = new Date(dob),
          t = new Date(),
          age = t.getFullYear() - b.getFullYear(),
          m = t.getMonth() - b.getMonth();
        if (m < 0 || (m === 0 && t.getDate() < b.getDate())) age--;
        $(ageSel).val(age);
      }
      $('#addTglLahir').change(() => autoCalcAge('#addTglLahir', '#addUsia'));
      $('#editTglLahir').change(() => autoCalcAge('#editTglLahir', '#editUsia'));
      $('#modalAdd').on('shown.bs.modal', () => autoCalcAge('#addTglLahir', '#addUsia'));
      $('#modalEdit').on('shown.bs.modal', () => autoCalcAge('#editTglLahir', '#editUsia'));

      // --- [3.9] CRUD AJAX HANDLERS ---
      // VIEW DETAIL
      $(document).on('click', '.btn-view', function() {
        let id = $(this).data('id');
        $.ajax({
          url: "manage_guru_karyawan.php?ajax=1",
          type: "POST",
          data: {
            case: 'GetGuruDetail',
            id: id,
            csrf_token: "<?= htmlspecialchars($csrf_token); ?>"
          },
          dataType: "json",
          beforeSend: () => $('#loadingSpinner').show(),
          success: res => {
            $('#loadingSpinner').hide();
            if (res.code === 0) {
              let r = res.result;
              $('#detailNip').text(r.nip);
              $('#detailUid').text(r.uid);
              $('#detailNama').text(r.nama);
              $('#detailJenjang').text(r.jenjang);
              $('#detailJobTitle').text(r.job_title);
              $('#detailRole').text(r.role);
              $('#detailStatusKerja').html(getStatusBadge(r.status_kerja));
              $('#detailGajiPokok').text(r.gaji_pokok);
              $('#detailSalaryLevel').text(r.salary_level);
              $('#detailLamaKontrak').text(r.lama_kontrak ? r.lama_kontrak + ' Bln' : '-');
              $('#detailTglSelesai').text(r.tgl_kontrak_selesai || '-');
              if (r.status_kerja === 'Tetap') $('#detailLamaKontrakRow,#detailTglSelesaiRow').hide();
              else $('#detailLamaKontrakRow,#detailTglSelesaiRow').show();
              $('#detailJoinStart').text(r.join_start);
              $('#detailSudahKontrak').text(r.sudah_kontrak + ' Thn');
              $('#detailMasaKerja').text(r.masa_kerja);
              $('#detailPendidikan').text(r.pendidikan);
              $('#detailStrata').text(r.strata || '');
              $('#detailJK').text(r.jk);
              $('#detailTglLahir').text(r.tanggal_lahir);
              $('#detailUsia').text(r.usia);
              $('#detailReligion').text(r.religion);
              $('#detailAlamatDomisili').text(r.alamat_domisili);
              $('#detailAlamatKTP').text(r.alamat_ktp);
              $('#detailNoRekening').text(r.no_rekening);
              $('#detailNoHP').text(r.no_hp);
              $('#detailEmail').text(r.email);
              $('#detailRemark').text(r.remark || '');
              $('#detailStatusPerkawinan').text(r.status_perkawinan);
              $('#detailNamaPasangan').text(r.nama_pasangan || '');
              $('#detailJumlahAnak').text(r.jumlah_anak || 0);
              $('#detailNamaAnak1').text(r.nama_anak_1 || '');
              $('#detailNamaAnak2').text(r.nama_anak_2 || '');
              $('#detailNamaAnak3').text(r.nama_anak_3 || '');
              $('#detailFotoProfil').attr('src', r.foto_profil || baseUrl + "/assets/img/undraw_profile.svg");
              $('#detailFotoKtp').attr('src', r.foto_ktp || baseUrl + "/assets/img/ktp_placeholder.png");

              // SECTION: FASKES KESEHATAN
              $('#detailFaskesBpjs').html(getFaskesBadge(r.faskes_bpjs, 'BPJS'));
              $('#detailFaskesInhealth').html(getFaskesBadge(r.faskes_inhealth, 'INHEALTH'));
              $('#detailFaskesKet').text(r.faskes_ket || '-');

              $('#modalView').modal('show');
            } else showToast(res.result, 'error');
          },
          error: () => {
            $('#loadingSpinner').hide();
            showToast('Terjadi kesalahan saat mengambil detail.', 'error');
          }
        });
      });

      /**
       * Badge generator untuk FASKES
       * value = 1 atau 0
       * type = 'BPJS' | 'INHEALTH'
       */
      function getFaskesBadge(value, type) {
        if (value == 1) {
          return `<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Terdaftar</span>`;
        } else {
          return `<span class="badge bg-secondary"><i class="fas fa-minus-circle me-1"></i>Tidak</span>`;
        }
      }

      $(document).on('click', '.btn-edit', function() {
        let id = $(this).data('id'),
          modal = $('#modalEdit'),
          form = $('#edit-guru-form');

        form[0].reset();
        form.removeClass('was-validated');

        $.ajax({
          url: "manage_guru_karyawan.php?ajax=1",
          type: "POST",
          data: {
            case: 'GetGuruDetail',
            id: id,
            csrf_token: "<?= htmlspecialchars($csrf_token); ?>"
          },
          dataType: "json",
          beforeSend: () => $('#loadingSpinner').show(),
          success: res => {
            $('#loadingSpinner').hide();
            if (res.code === 0) {
              let r = res.result;

              // Isi field lain (biasa)
              $('#editId').val(r.id);
              $('#editNip').val(r.nip);
              $('#editUid').val(r.uid);
              $('#editNama').val(r.nama);
              $('#editJobTitle').val(r.job_title || '');
              $('#editRemark').val(r.remark || '');
              $('#editPendidikan').val(r.pendidikan || '');
              $('#editJoinStart').val(r.join_start || '');
              $('#editStatusKerja').val(r.status_kerja || 'Tetap');
              $('#editSudahKontrak').val(r.sudah_kontrak || '');
              $('#editTglSelesai').val(r.tgl_kontrak_selesai || '');
              if (r.status_kerja === 'Kontrak') $('#editLamaKontrak').val(r.lama_kontrak || '12');
              else $('#editLamaKontrak').val('');

              // Jenjang dan Role DULU!
              $('#editJenjang').val(r.jenjang || '');
              $('#editUnitPenempatan').val(r.unit_penempatan || '');
              toggleUnitPenempatan(r.jenjang, 'edit');
              $('#editRole').val(r.role || '');

              // Generate ulang dropdown Strata
              syncStrataOptions(r.jenjang, r.role, 'edit', function() {
                // Setelah opsi Strata sudah ada, baru set nilainya
                $('#editStrata').val((r.strata || '').trim().toUpperCase());
              });

              $('#editUnitPenempatan').val(r.unit_penempatan || '');
              // Field lain
              $('#editJK').val(r.jk || '');
              $('#editTglLahir').val(r.tanggal_lahir || '');
              $('#editUsia').val(r.usia || '');
              $('#editReligion').val(r.religion || '');
              $('#editAlamatDomisili').val(r.alamat_domisili || '');
              $('#editAlamatKTP').val(r.alamat_ktp || '');
              $('#editNoRekening').val(r.no_rekening || '');
              $('#editNoHP').val(r.no_hp || '');
              $('#editEmail').val(r.email || '');
              $('#editStatusPerkawinan').val(r.status_perkawinan || '');
              $('#editNamaPasangan').val(r.nama_pasangan || '');
              $('#editJumlahAnak').val(r.jumlah_anak || 0);
              $('#editNamaAnak1').val(r.nama_anak_1 || '');
              $('#editNamaAnak2').val(r.nama_anak_2 || '');
              $('#editNamaAnak3').val(r.nama_anak_3 || '');
              $('#editFaskesBpjs').val(r.faskes_bpjs || '0');
              $('#editFaskesInhealth').val(r.faskes_inhealth || '0');
              $('#editFaskesKet').val(r.faskes_ket || '');

              // Foto profil/ktp
              $('#previewEditFotoProfil').attr('src', r.foto_profil || "<?= getBaseUrl() ?>/assets/img/undraw_profile.svg");
              $('#previewEditFotoKtp').attr('src', r.foto_ktp || "<?= getBaseUrl() ?>/assets/img/ktp_placeholder.png");

              // Toggle kontrak
              toggleKontrak('edit');

              modal.modal('show');
            } else showToast(res.result, 'error');
          },
          error: () => {
            $('#loadingSpinner').hide();
            showToast('Gagal mengambil detail.', 'error');
          }
        });
      });

      // DELETE
      $(document).on('click', '.btn-delete', function() {
        $('#delId').val($(this).data('id'));
        $('#delNama').text('ID: ' + $(this).data('id'));
        $('#modalDelete').modal('show');
      });
      $('#delete-guru-form').submit(function(e) {
        e.preventDefault();
        let id = $('#delId').val();
        if (!id) {
          return showToast('ID tidak ditemukan.', 'error');
        }
        let f = $(this);
        $.ajax({
          url: "manage_guru_karyawan.php?ajax=1",
          type: "POST",
          data: {
            case: 'DeleteGuru',
            id: id,
            csrf_token: "<?= htmlspecialchars($csrf_token); ?>"
          },
          dataType: "json",
          beforeSend: () => {
            f.find('button[type="submit"]').prop('disabled', true);
            f.find('.spinner-border').removeClass('d-none');
          },
          success: res => {
            f.find('button[type="submit"]').prop('disabled', false);
            f.find('.spinner-border').addClass('d-none');
            if (res.code === 0) {
              showToast(res.result);
              $('#modalDelete').modal('hide');
              loadGuru(currentPage);
            } else showToast(res.result, 'error');
          },
          error: () => {
            f.find('button[type="submit"]').prop('disabled', false);
            f.find('.spinner-border').addClass('d-none');
            showToast('Gagal menghapus data.', 'error');
          }
        });
      });

      // SUBMIT EDIT
      $('#edit-guru-form').submit(function(e) {
        e.preventDefault();
        let formEl = this;
        if (!formEl.checkValidity()) {
          e.stopPropagation();
          $(formEl).addClass('was-validated');
          return;
        }
        let data = new FormData(formEl);
        data.set('case', 'UpdateGuru');
        data.set('csrf_token', '<?= htmlspecialchars($csrf_token) ?>');
        $.ajax({
          url: "manage_guru_karyawan.php?ajax=1",
          type: "POST",
          data: data,
          processData: false,
          contentType: false,
          dataType: "json",
          beforeSend: () => {
            $(formEl).find('button[type="submit"]').prop('disabled', true);
            $(formEl).find('.spinner-border').removeClass('d-none');
          },
          success: res => {
            $(formEl).find('button[type="submit"]').prop('disabled', false);
            $(formEl).find('.spinner-border').addClass('d-none');
            if (res.code === 0) {
              showToast(res.result);
              $('#modalEdit').modal('hide');
              loadGuru(currentPage);
            } else showToast(res.result, 'error');
          },
          error: () => {
            $(formEl).find('button[type="submit"]').prop('disabled', false);
            $(formEl).find('.spinner-border').addClass('d-none');
            showToast('Gagal mengupdate data.', 'error');
          }
        });
      });

      // SUBMIT ADD
      $('#add-guru-form').submit(function(e) {
        e.preventDefault();
        let formEl = this;
        if (!formEl.checkValidity()) {
          e.stopPropagation();
          $(formEl).addClass('was-validated');
          return;
        }
        let data = new FormData(formEl);
        data.set('case', 'CreateGuru');
        data.set('csrf_token', '<?= htmlspecialchars($csrf_token) ?>');
        $.ajax({
          url: "manage_guru_karyawan.php?ajax=1",
          type: "POST",
          data: data,
          processData: false,
          contentType: false,
          dataType: "json",
          beforeSend: () => {
            $(formEl).find('button[type="submit"]').prop('disabled', true);
            $(formEl).find('.spinner-border').removeClass('d-none');
          },
          success: res => {
            $(formEl).find('button[type="submit"]').prop('disabled', false);
            $(formEl).find('.spinner-border').addClass('d-none');
            if (res.code === 0) {
              showToast(res.result);
              $('#modalAdd').modal('hide');
              loadGuru(1);
              formEl.reset();
              $(formEl).removeClass('was-validated');
            } else showToast(res.result, 'error');
          },
          error: () => {
            $(formEl).find('button[type="submit"]').prop('disabled', false);
            $(formEl).find('.spinner-border').addClass('d-none');
            showToast('Gagal menambah data.', 'error');
          }
        });
      });

      // SUBMIT GAJI STRATA GURU
      $('#gaji-strata-form-guru').submit(function(e) {
        e.preventDefault();
        let f = $(this);
        if (!this.checkValidity()) {
          e.stopPropagation();
          f.addClass('was-validated');
          return;
        }
        $.ajax({
          url: "manage_guru_karyawan.php?ajax=1",
          type: "POST",
          data: f.serialize(),
          dataType: "json",
          beforeSend: () => {
            f.find('button[type="submit"]').prop('disabled', true);
            f.find('.spinner-border').removeClass('d-none');
          },
          success: res => {
            f.find('button[type="submit"]').prop('disabled', false);
            f.find('.spinner-border').addClass('d-none');
            if (res.code === 0) {
              showToast(res.result);
              $('#modalGajiStrataGuru').modal('hide');
            } else showToast(res.result, 'error');
          },
          error: () => {
            f.find('button[type="submit"]').prop('disabled', false);
            f.find('.spinner-border').addClass('d-none');
            showToast('Gagal update gaji strata Guru.', 'error');
          }
        });
      });

      // SUBMIT GAJI STRATA KARYAWAN
      $('#gaji-strata-form-karyawan').submit(function(e) {
        e.preventDefault();
        let f = $(this);
        if (!this.checkValidity()) {
          e.stopPropagation();
          f.addClass('was-validated');
          return;
        }
        $.ajax({
          url: "manage_guru_karyawan.php?ajax=1",
          type: "POST",
          data: f.serialize(),
          dataType: "json",
          beforeSend: () => {
            f.find('button[type="submit"]').prop('disabled', true);
            f.find('.spinner-border').removeClass('d-none');
          },
          success: res => {
            f.find('button[type="submit"]').prop('disabled', false);
            f.find('.spinner-border').addClass('d-none');
            if (res.code === 0) {
              showToast(res.result);
              $('#modalGajiStrataKaryawan').modal('hide');
            } else showToast(res.result, 'error');
          },
          error: () => {
            f.find('button[type="submit"]').prop('disabled', false);
            f.find('.spinner-border').addClass('d-none');
            showToast('Gagal update gaji strata Karyawan.', 'error');
          }
        });
      });

      // --- [3.10] Modal Gaji Pokok & Navigasi ---
      $('#btnGajiStrataGuru').click(function() {
        $('#modalGajiPokok').modal('hide');
        $('#modalGajiStrataGuru').modal('show');
      });
      $('#btnGajiStrataKaryawan').click(function() {
        $('#modalGajiPokok').modal('hide');
        $('#modalGajiStrataKaryawan').modal('show');
      });
      $(document).on('click', '#btnManageSalaryIndices', function(e) {
        e.preventDefault();
        $('#content-wrapper').fadeOut(300, () => window.location.href = $(this).data('href'));
      });
      $(document).on('click', '#btnManageHolidays', function(e) {
        e.preventDefault();
        $('#content-wrapper').fadeOut(300, () => window.location.href = $(this).data('href'));
      });

      $('#btnSyncAllGajiPokok').on('click', function() {
        Swal.fire({
          title: 'Sinkronisasi Gaji Pokok',
          text: 'Semua anggota akan diupdate sesuai data Strata & Jenjang terbaru. Lanjutkan?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Ya, Sinkronkan!',
          cancelButtonText: 'Batal',
          reverseButtons: true
        }).then((result) => {
          if (result.isConfirmed) {
            let btn = $('#btnSyncAllGajiPokok');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
            $.post('includes/proses_update_gaji_strata.php', function(resp) {
              // Pastikan response format: { code: 0/1, result: "Pesan..." }
              if (resp.code === 0) {
                Toast.fire({
                  icon: 'success',
                  title: resp.result || 'Berhasil update semua gaji pokok!'
                });
              } else {
                Toast.fire({
                  icon: 'error',
                  title: resp.result || 'Gagal sinkronisasi!'
                });
              }
            }, 'json').fail(function() {
              Toast.fire({
                icon: 'error',
                title: 'Terjadi kesalahan pada server.'
              });
            }).always(function() {
              btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i> Sinkron Gaji Semua');
              // Optional: location.reload();
            });
          }
        });

      });

      /* ============================================================
   =============== [2.1] TOGGLE UNIT PENEMPATAN ================
   ============================================================ */
      function toggleUnitPenempatan(jenjang, prefix) {
        const isUmum = (jenjang || '').toUpperCase() === 'UMUM';
        const kontainer = $(`#${prefix}UnitPenempatanContainer`);
        const input = $(`#${prefix}UnitPenempatan`);

        if (isUmum) {
          kontainer.removeClass('d-none');
          input.prop('required', true);
        } else {
          kontainer.addClass('d-none');
          input.prop('required', false).val('');
        }
      }


      // === PANGGIL toggleUnitPenempatan di event Jenjang Add/Edit ===
      $('#addJenjang').change(function() {
        toggleUnitPenempatan($(this).val(), 'add');
      });
      $('#editJenjang').change(function() {
        toggleUnitPenempatan($(this).val(), 'edit');
      });
      $('#modalAdd').on('shown.bs.modal', function() {
        toggleUnitPenempatan($('#addJenjang').val(), 'add');
      });
      $('#modalEdit').on('shown.bs.modal', function() {
        toggleUnitPenempatan($('#editJenjang').val(), 'edit');
      });


    }); // end document.ready
  </script>
</body>

</html>