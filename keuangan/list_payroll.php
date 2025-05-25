<?php
// File: /payroll_absensi_v2/keuangan/list_payroll.php

// Sertakan file helper, inisialisasi session dan CSRF token
$pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

require_once __DIR__ . '/../helpers.php';
start_session_safe();
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

// Sertakan koneksi ke database
require_once __DIR__ . '/../koneksi.php';

// Hanya user dengan role 'keuangan' dan 'superadmin' yang diizinkan
authorize(['M:Keuangan', 'M:Superadmin'], '/payroll_absensi_v2/login.php');
$jenjangList = getOrderedJenjang($conn);;
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
function CheckPayrollCompletion($conn)
{
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
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        send_response(1, 'Gagal menghitung payroll: ' . $error);
    }
    $result = $stmt->get_result();
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        if (intval($row['pending']) > 0) {
            $messages[] = "Terdapat " . $row['pending'] . " anggota dengan jenjang '" . $row['jenjang'] . "' yang belum final.";
        }
    }
    $stmt->close();

    if (empty($messages)) {
        send_response(0, ['complete' => true, 'messages' => []]);
    } else {
        send_response(0, ['complete' => false, 'messages' => $messages]);
    }
}

/**
 * Fungsi LogSelectMonth: Mencatat (audit log) pilihan bulan payroll oleh user.
 */
function LogSelectMonth($conn)
{
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
function LoadPayrollOverview($conn)
{
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
        send_response(1, 'Gagal prepare query count: ' . $conn->error);
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
            a.foto_profil,
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
        send_response(1, 'Gagal prepare query data: ' . $conn->error);
    }
    $stmtData->bind_param($types, ...$params);
    $stmtData->execute();
    $result = $stmtData->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        // ambil nilai dari kolom foto_profil
        $fotoDb   = $row['foto_profil'] ?? '';
        $filename = basename($fotoDb);
        $local    = __DIR__ . '/../uploads/profile_pics/' . $filename;
        $baseUrl  = getBaseUrl();

        if ($fotoDb && strpos($fotoDb, 'http') === 0) {
            // URL eksternal
            $fotoUrl = $fotoDb;
        } elseif ($filename && file_exists($local)) {
            // file lokal tersedia
            $fotoUrl = "{$baseUrl}/uploads/profile_pics/{$filename}?v=" . filemtime($local);
        } else {
            // fallback
            $fotoUrl = "{$baseUrl}/assets/img/undraw_profile.svg";
        }

        $rows[] = [
            'id_payroll'         => $row['id_payroll'],
            'id_anggota'         => $row['id_anggota'],
            'nama'               => $row['nama'],
            'nip'                => $row['nip'],
            'jenjang'            => $row['jenjang'],
            'role'               => $row['role'],
            'bulan'              => $row['bulan'],
            'tahun'              => $row['tahun'],
            'status'             => $row['status'],
            'tgl_payroll'        => $row['tgl_payroll'],
            'salary_index_level' => $row['salary_index_level'] ?: '-',
            // gunakan URL yang sudah benar
            'foto_profil'        => $fotoUrl,
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
$details_log = "User dengan NIP $user_nip melihat overview payroll untuk periode: $filterMonth/$filterYear";
add_audit_log($conn, $user_nip, 'ViewPayrollOverview', $details_log);

// Ambil data untuk dropdown filter jenjang dan role
$jenjangOptions = [];
$stmtJenjang = $conn->prepare("SELECT DISTINCT jenjang FROM anggota_sekolah WHERE jenjang IS NOT NULL AND jenjang != '' ORDER BY jenjang ASC");
if ($stmtJenjang) {
    $stmtJenjang->execute();
    $resJenjang = $stmtJenjang->get_result();
    while ($row = $resJenjang->fetch_assoc()) {
        $jenjangOptions[] = $row['jenjang'];
    }
    $stmtJenjang->close();
}

$roleOptions = [];
$stmtRole = $conn->prepare("SELECT DISTINCT role FROM anggota_sekolah WHERE role IS NOT NULL AND role != '' ORDER BY role ASC");
if ($stmtRole) {
    $stmtRole->execute();
    $resRole = $stmtRole->get_result();
    while ($row = $resRole->fetch_assoc()) {
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
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(to right, #4e54c8, #8f94fb);
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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

        body {
            color: #2d3748;
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }


        .card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: var(--card-shadow);
        }

.form-select {
    min-height: 45px !important;
    padding-right: 2.5rem !important;
    font-size: 1rem !important;
    line-height: 1.5 !important;
    background-position: right 1rem center !important;
}

        .card-header {
            background: var(--secondary-gradient);
            color: white;
            font-family: 'Poppins', sans-serif;
            letter-spacing: 0.5px;
            padding: 1rem 1.5rem;
        }

        .card-header h6 {
            font-weight: 600;
            margin-bottom: 0;
        }

        .processed-month {
            background: #343a40 !important;
            color: #fff !important;
            pointer-events: none;
            border: 1px solid #343a40;
        }

        /* Grid styling */
        #payrollCards .col {
            display: flex;
            margin-bottom: 1.5rem;
        }

        .payroll-card {
            flex: 1;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }


        .employee-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 15px;
            border: 3px solid #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .badge-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .badge-status.draft {
            background-color: #17a2b8;
            color: #fff;
        }

        .badge-status.final {
            background-color: #28a745;
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

        /* Spinner (loading) */
        #loadingSpinner {
            display: none;
            margin-left: 10px;
            width: 1.5rem;
            height: 1.5rem;
            border-width: 0.2em;
        }

        /* Filter section */
        .filter-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
        }

        .form-control,
        .form-select {
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            border: 1px solid #ced4da;
        }

        .btn {
            border-radius: 8px;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
        }

        /* Pagination */
        .pagination .page-item.active .page-link {
            background-color: #667eea;
            border-color: #667eea;
        }

        .page-link {
            color: #667eea;
            border-radius: 8px !important;
            margin: 0 3px;
            border: none;
        }

        /* Skeleton loader */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
            border-radius: 8px;
        }



        /* Responsive adjustments */
        @media (max-width: 768px) {
            #payrollCards .col {
                flex: 0 0 50%;
                max-width: 50%;
            }

            .filter-section .col-auto {
                margin-bottom: 10px;
                width: 100%;
            }

            .employee-photo {
                width: 60px;
                height: 60px;
            }
        }

        @media (max-width: 576px) {
            #payrollCards .col {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }
    </style>
</head>

<body id="page-top">
    <div id="wrapper">
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include __DIR__ . '/../navbar.php'; ?>
                <?php
                // Jika punya breadcrumb.php, sertakan
                include __DIR__ . '/../breadcrumb.php';
                ?>
                <div class="container-fluid">
                    <!-- Page Title -->
                    <h1 class="page-title">
                        <i class="fas fa-file-invoice-dollar"></i>
                        Payroll Overview
                    </h1>
                    <!-- Header: Tampilkan periode payroll yang terpilih (dengan tampilan card) -->
                    <div id="selectedMonthDisplay" class="mb-3" style="cursor: pointer;">
                        <div class="card mb-3 border-0 shadow-sm">
                            <div class="card-body d-flex align-items-center py-3">
                                <i class="bi bi-calendar3 me-2 fs-4 text-primary"></i>
                                <span class="fw-bold fs-5">
                                    Payroll Bulan: <?= date("F", mktime(0, 0, 0, $filterMonth, 1)) . " " . $filterYear; ?>
                                </span>
                                <button id="btnChangeCalendar" class="btn btn-outline-primary ms-auto">
                                    <i class="bi bi-pencil-square me-1"></i> Ganti Periode
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Section: Jenjang, Role, dan Pencarian -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-white">
                                <i class="fas fa-sliders-h me-2"></i> Filter Payroll
                            </h6>
                        </div>
                        <div class="card-body filter-section">
                            <form id="filterPayrollForm" class="row gy-2 gx-3 align-items-center">
                                <!-- Simpan csrf_token -->
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">

                                <!-- Jenjang -->
                                <div class="col-auto">
                                    <label for="filterJenjang" class="form-label mb-1"><strong>Jenjang Pendidikan:</strong></label>
                                    <select class="form-select" id="filterJenjang" name="jenjang">
                                        <option value="">Semua Jenjang</option>
                                        <?php
$jenjangList = getOrderedJenjang($conn); // array: ['TK'=>'Taman Kanak-Kanak', ...]
foreach ($jenjangList as $kode_jenjang => $nama_jenjang) {
    echo '<option value="' . htmlspecialchars($kode_jenjang) . '"'
        . ($filterJenjang === $kode_jenjang ? ' selected' : '')
        . '>' . htmlspecialchars($nama_jenjang) . '</option>';
}
?>

                                    </select>
                                </div>

                                <!-- Role -->
                                <div class="col-auto">
                                    <label for="filterRole" class="form-label mb-1"><strong>Role:</strong></label>
                                    <select class="form-select" id="filterRole" name="role">
                                        <option value="">Semua Role</option>
                                        <?php foreach ($roleOptions as $role): ?>
                                            <option value="<?= htmlspecialchars($role); ?>" <?= ($filterRole === $role) ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($role); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Pencarian -->
                                <div class="col-auto">
                                    <label for="filterSearch" class="form-label mb-1"><strong>Pencarian:</strong></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        <input type="text" class="form-control" id="filterSearch" name="search" placeholder="Cari nama / nip..." value="<?= htmlspecialchars($search ?? '') ?>">
                                    </div>
                                </div>

                                <!-- Tombol -->
                                <div class="col-auto d-flex align-items-end">
                                    <button type="button" class="btn btn-primary me-2" id="btnApplyFilterPayroll" data-toggle="tooltip" title="Terapkan filter yang dipilih">
                                        <i class="fas fa-filter me-1"></i> Terapkan
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="btnResetFilterPayroll" data-toggle="tooltip" title="Reset semua filter">
                                        <i class="fas fa-undo me-1"></i> Reset
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Skeleton Loader (hidden by default) -->
                    <div id="loadingSkeleton" style="display:none;">
                        <div class="card shadow-sm mb-4">
                            <div class="card-body">
                                <div class="row">
                                    <?php for ($i = 0; $i < 4; $i++): ?>
                                        <div class="col-md-3 mb-4">
                                            <div class="card h-100">
                                                <div class="card-body text-center">
                                                    <div class="skeleton mx-auto" style="width:80px; height:80px; border-radius:50%; margin-bottom:15px;"></div>
                                                    <div class="skeleton" style="width:80%; height:20px; margin:0 auto 10px;"></div>
                                                    <div class="skeleton" style="width:60%; height:15px; margin:0 auto 8px;"></div>
                                                    <div class="skeleton" style="width:70%; height:15px; margin:0 auto 8px;"></div>
                                                    <div class="skeleton" style="width:50%; height:30px; margin:15px auto 0; border-radius:20px;"></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grid Container -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-white">
                                <i class="fas fa-file-invoice-dollar me-2"></i> Daftar Payroll
                            </h6>
                            <div class="d-flex align-items-center">
                                <span class="me-2 small text-white" id="recordCount">Memuat data...</span>
                                <div class="spinner-border text-light" role="status" id="loadingSpinner">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Di sinilah grid card akan ditampilkan -->
                            <div id="payrollCards" class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
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
                        <span>&copy; <?= date("Y"); ?> Payroll Management System</span>
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
                    <h5 class="modal-title" id="salaryMonthModalLabel"><i class="fa fa-calendar me-2"></i> Pilih Bulan untuk Payroll</h5>
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
        $(document).ready(function() {
            // Inisialisasi tooltip
            $('[data-toggle="tooltip"]').tooltip();

            // Toast ringkas dengan SweetAlert2
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });

            function showToast(message, icon = 'success') {
                Toast.fire({
                    icon: icon,
                    title: message
                });
            }

            // Fungsi untuk mendapatkan icon berdasarkan status
            function getStatusIcon(status) {
                const statusMap = {
                    'draft': 'pen',
                    'final': 'check-circle',
                    'revisi': 'sync-alt'
                };
                return statusMap[status.toLowerCase()] || 'info-circle';
            }

            // Fungsi untuk memformat tanggal
            function formatDate(dateString) {
                if (!dateString) return '-';
                const options = {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric'
                };
                return new Date(dateString).toLocaleDateString('id-ID', options);
            }

            // Paginasi manual
            let currentPage = 1;
            let pageSize = 8; // jumlah card per halaman

            // Fungsi untuk menampilkan/menyembunyikan skeleton loader
            function showSkeletonLoader(show = true) {
                if (show) {
                    $("#payrollCards").hide();
                    $("#loadingSkeleton").show();
                } else {
                    $("#loadingSkeleton").hide();
                    $("#payrollCards").show();
                }
            }

            // Fungsi load data payroll (AJAX)
            function loadPayroll(page) {
                currentPage = page;
                let start = (currentPage - 1) * pageSize;

                showSkeletonLoader(true);
                $("#loadingSpinner").show();

                $.ajax({
                    url: "list_payroll.php?ajax=1",
                    type: "POST",
                    data: {
                        case: "LoadPayrollOverview",
                        start: start,
                        length: pageSize,
                        filterMonth: <?= $filterMonth ?>,
                        filterYear: <?= $filterYear ?>,
                        filterJenjang: $("#filterJenjang").val(),
                        filterRole: $("#filterRole").val(),
                        search: $("#filterSearch").val(),
                        csrf_token: "<?= htmlspecialchars($csrf_token); ?>"
                    },
                    dataType: "json",
                    success: function(resp) {
                        showSkeletonLoader(false);
                        $("#loadingSpinner").hide();

                        if (resp.data) {
                            generateCards(resp.data);
                            generatePagination(resp.recordsTotal);
                            $("#recordCount").text(`${resp.data.length} dari ${resp.recordsTotal} data ditampilkan`);
                        } else {
                            $("#payrollCards").html('<div class="col-12 text-center py-5"><i class="fas fa-inbox fa-3x text-muted mb-3"></i><p class="text-muted">Tidak ada data payroll yang ditemukan</p></div>');
                            $("#paginationContainer").empty();
                            $("#recordCount").text("0 data ditemukan");
                        }
                    },
                    error: function(xhr, status, error) {
                        showSkeletonLoader(false);
                        $("#loadingSpinner").hide();
                        showToast("Terjadi kesalahan memuat data: " + error, "error");
                        $("#recordCount").text("Gagal memuat data");
                    }
                });
            }

            // Dapatkan baseUrl dari PHP
            let baseUrl = "<?= getBaseUrl(); ?>";

            // Fungsi generate card
            function generateCards(data) {
                let container = $("#payrollCards");
                container.empty();

                if (data.length === 0) {
                    container.html('<div class="col-12 text-center py-5"><i class="fas fa-inbox fa-3x text-muted mb-3"></i><p class="text-muted">Tidak ada data payroll yang ditemukan</p></div>');
                    return;
                }

                data.forEach(function(item) {
                    let statusClass = 'default';
                    let stLower = (item.status || '').toLowerCase();
                    if (stLower === 'draft') statusClass = 'draft';
                    else if (stLower === 'final') statusClass = 'final';
                    else if (stLower === 'revisi') statusClass = 'revisi';

                    // Gunakan item.foto_profil (sudah di-return full URL oleh getProfilePhotoUrl)
                    let photoUrl = item.foto_profil && item.foto_profil !== '' ?
                        item.foto_profil :
                        baseUrl + "/assets/img/undraw_profile.svg";

                    // Link "Review" -> sebaiknya prefix path subfolder
                    let reviewUrl = baseUrl + "/keuangan/manage-salary.php" +
                        `?id_anggota=${item.id_anggota}&bulan=${item.bulan}&tahun=${item.tahun}`;

                    let cardHtml = `
                        <div class="col">
                            <div class="card payroll-card h-100">
                                <div class="card-header py-2">
                                    <h6 class="mb-0 text-truncate">${item.nama}</h6>
                                </div>
                                <div class="card-body text-center pt-4 pb-3">
                                    <div class="position-relative mb-3">
                                        <img src="${photoUrl}" alt="Foto" class="employee-photo shadow">
                                        <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-success">
                                            ${item.salary_index_level}
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between small mb-2">
                                        <span class="text-muted">NIP:</span>
                                        <span>${item.nip}</span>
                                    </div>
                                    <div class="d-flex justify-content-between small mb-2">
                                        <span class="text-muted">Jenjang:</span>
                                        <span class="fw-bold">${item.jenjang}</span>
                                    </div>
                                    <hr class="my-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge-status ${statusClass}">
                                            <i class="fas fa-${getStatusIcon(item.status)} me-1"></i>
                                            ${item.status}
                                        </span>
                                        <a href="${reviewUrl}" class="btn btn-sm btn-primary" data-toggle="tooltip" title="Review payroll">
                                            <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="card-footer small text-muted text-center">
                                    Diperbarui: ${formatDate(item.tgl_payroll)}
                                </div>
                            </div>
                        </div>
                    `;
                    container.append(cardHtml);
                });

                // Inisialisasi ulang tooltip untuk elemen baru
                $('[data-toggle="tooltip"]').tooltip();
            }

            // Fungsi generate pagination
            function generatePagination(totalRecords) {
                let totalPages = Math.ceil(totalRecords / pageSize);
                let pagination = $("#paginationContainer");
                pagination.empty();

                if (totalPages <= 1) return;

                // Tombol Previous
                pagination.append(
                    $("<li>").addClass("page-item" + (currentPage === 1 ? " disabled" : "")).append(
                        $("<a>").addClass("page-link").html("&laquo;").attr("href", "#").on("click", function(e) {
                            e.preventDefault();
                            if (currentPage > 1) loadPayroll(currentPage - 1);
                        })
                    )
                );

                // Tampilkan maksimal 5 halaman di sekitar current page
                let startPage = Math.max(1, currentPage - 2);
                let endPage = Math.min(totalPages, currentPage + 2);

                if (startPage > 1) {
                    pagination.append(
                        $("<li>").addClass("page-item").append(
                            $("<a>").addClass("page-link").text("1").attr("href", "#").on("click", function(e) {
                                e.preventDefault();
                                loadPayroll(1);
                            })
                        )
                    );
                    if (startPage > 2) {
                        pagination.append(
                            $("<li>").addClass("page-item disabled").append(
                                $("<a>").addClass("page-link").text("...")
                            )
                        );
                    }
                }

                for (let i = startPage; i <= endPage; i++) {
                    let li = $("<li>").addClass("page-item" + (i === currentPage ? " active" : "")).append(
                        $("<a>").addClass("page-link").text(i).attr("href", "#").on("click", function(e) {
                            e.preventDefault();
                            loadPayroll(i);
                        })
                    );
                    pagination.append(li);
                }

                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) {
                        pagination.append(
                            $("<li>").addClass("page-item disabled").append(
                                $("<a>").addClass("page-link").text("...")
                            )
                        );
                    }
                    pagination.append(
                        $("<li>").addClass("page-item").append(
                            $("<a>").addClass("page-link").text(totalPages).attr("href", "#").on("click", function(e) {
                                e.preventDefault();
                                loadPayroll(totalPages);
                            })
                        )
                    );
                }

                // Tombol Next
                pagination.append(
                    $("<li>").addClass("page-item" + (currentPage === totalPages ? " disabled" : "")).append(
                        $("<a>").addClass("page-link").html("&raquo;").attr("href", "#").on("click", function(e) {
                            e.preventDefault();
                            if (currentPage < totalPages) loadPayroll(currentPage + 1);
                        })
                    )
                );
            }


            // Panggil loadPayroll pertama kali
            loadPayroll(1);

            // Filter (Apply & Reset)
            $("#btnApplyFilterPayroll").on("click", function() {
                loadPayroll(1);
            });

            $("#btnResetFilterPayroll").on("click", function() {
                $("#filterJenjang").val("");
                $("#filterRole").val("");
                $("#filterSearch").val("");
                loadPayroll(1);
            });

            // Submit form filter saat tekan Enter di input search
            $("#filterSearch").on("keypress", function(e) {
                if (e.which === 13) {
                    loadPayroll(1);
                    return false;
                }
            });

            // Modal "Ganti Kalender" => menampilkan SalaryMonthModal
            $("#btnChangeCalendar, #selectedMonthDisplay").on("click", function(e) {
                e.preventDefault();
                $("#SalaryMonthModal").modal("show");
            });

            // Klik pilihan bulan di modal => cek payroll completion => logSelectMonth => redirect
            $(document).on("click", ".month-link", function(e) {
                e.preventDefault();
                let monthNumber = $(this).data('month-number');
                let monthName = $(this).data('month');
                let year = $(this).data('year');

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
                                    cancelButtonText: 'Batal',
                                    confirmButtonColor: '#4e73df',
                                    cancelButtonColor: '#6c757d'
                                }).then((result) => {
                                    if (result.isConfirmed) {
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