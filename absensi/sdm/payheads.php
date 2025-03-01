<?php
// File: /payroll_absensi_v2/absensi/sdm/payheads.php

// =========================
// 1. Pengaturan Awal
// =========================
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
authorize(['sdm', 'superadmin']);

require_once __DIR__ . '/../../koneksi.php';

// Hapus output buffering jika ada
if (ob_get_length()) {
    ob_end_clean();
}

// =========================
// 2. Menangani Permintaan AJAX
// =========================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Debug: Log seluruh data POST (opsional)
        error_log("DEBUG: Data POST: " . print_r($_POST, true));

        // Ambil case
        $case = isset($_POST['case']) ? trim($_POST['case']) : '';

        switch ($case) {
            case 'LoadingPayheads':
                LoadingPayheads($conn);
                break;
            case 'AddPayhead':
                AddPayhead($conn);
                break;
            case 'GetPayheadDetail':
                GetPayheadDetail($conn);
                break;
            case 'UpdatePayhead':
                UpdatePayhead($conn);
                break;
            case 'DeletePayhead':
                DeletePayhead($conn);
                break;
            default:
                send_response(404, 'Kasus tidak ditemukan.');
        }
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    exit();
}

// =========================
// 3. Fungsi CRUD untuk Payheads
// =========================

/**
 * Memuat data Payheads secara server-side dengan DataTables.
 */
function LoadingPayheads($conn) {
    // DataTables parameters
    $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

    // Filter Jenis Payhead
    $filterJenis = isset($_POST['jenis_payhead']) ? trim($_POST['jenis_payhead']) : '';

    // Ambil total records tanpa filter
    $sqlTotal = "SELECT COUNT(*) as total FROM payheads";
    $resultTotal = mysqli_query($conn, $sqlTotal);
    if (!$resultTotal) {
        send_response(1, 'Query Error: ' . mysqli_error($conn));
    }
    $rowTotal = mysqli_fetch_assoc($resultTotal);
    $recordsTotal = $rowTotal['total'];

    // Bangun query filter
    $sqlFilter = "SELECT * FROM payheads WHERE 1=1";
    $sqlFilterCount = "SELECT COUNT(*) as total FROM payheads WHERE 1=1";
    $paramsFilterCount = [];
    $typesFilterCount = "";
    $paramsFilter = [];
    $typesFilter = "";

    // Jika ada pencarian
    if (!empty($search)) {
        $sqlFilter      .= " AND (nama_payhead LIKE ? OR jenis LIKE ? OR deskripsi LIKE ?)";
        $sqlFilterCount .= " AND (nama_payhead LIKE ? OR jenis LIKE ? OR deskripsi LIKE ?)";
        $searchParam = "%" . $search . "%";
        $paramsFilterCount = [$searchParam, $searchParam, $searchParam];
        $typesFilterCount = "sss";
        $paramsFilter = [$searchParam, $searchParam, $searchParam];
        $typesFilter = "sss";
    }

    // Jika filter jenis dipilih
    if (!empty($filterJenis)) {
        $sqlFilter      .= " AND jenis = ?";
        $sqlFilterCount .= " AND jenis = ?";
        $paramsFilterCount[] = $filterJenis;
        $typesFilterCount .= "s";
        $paramsFilter[] = $filterJenis;
        $typesFilter .= "s";
    }

    // Hitung recordsFiltered
    $stmtFiltered = $conn->prepare($sqlFilterCount);
    if ($stmtFiltered === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    if (!empty($paramsFilterCount)) {
        $stmtFiltered->bind_param($typesFilterCount, ...$paramsFilterCount);
    }
    $stmtFiltered->execute();
    $resultFiltered = $stmtFiltered->get_result();
    if (!$resultFiltered) {
        send_response(1, 'Query Error: ' . $stmtFiltered->error);
    }
    $rowFiltered = $resultFiltered->fetch_assoc();
    $recordsFiltered = isset($rowFiltered['total']) ? $rowFiltered['total'] : 0;
    $stmtFiltered->close();

    // Sorting (order by)
    // Mapping kolom DataTables ke kolom database
    $sortableColumns = [
        "nama_payhead"   => "nama_payhead",
        "jenis"          => "jenis",
        "deskripsi"      => "deskripsi",
        "nominal_tetap"  => "nominal"
    ];
    $orderBy = " ORDER BY id DESC"; // default
    if (isset($_POST['order'][0]['column']) && isset($_POST['columns'])) {
        $columnIndex = intval($_POST['order'][0]['column']);
        $colData = isset($_POST['columns'][$columnIndex]['data']) ? $_POST['columns'][$columnIndex]['data'] : '';
        $colSortOrder = (isset($_POST['order'][0]['dir']) && $_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
        if (array_key_exists($colData, $sortableColumns)) {
            $colName = $sortableColumns[$colData];
            $orderBy = " ORDER BY $colName $colSortOrder";
        }
    }

    // Paginasi / limit
    $limit = " LIMIT ?, ?";
    $paramsFilter[] = $start;
    $paramsFilter[] = $length;
    $typesFilter .= "ii";
    $sqlFilter .= $orderBy . $limit;

    // Eksekusi query data
    $stmtData = $conn->prepare($sqlFilter);
    if ($stmtData === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    if (!empty($paramsFilter)) {
        $stmtData->bind_param($typesFilter, ...$paramsFilter);
    }
    $stmtData->execute();
    $dataQuery = $stmtData->get_result();
    if (!$dataQuery) {
        send_response(1, 'Query Error: ' . $stmtData->error);
    }

    // Susun data untuk DataTables
    $data = [];
    $no = $start + 1;

    while ($row = $dataQuery->fetch_assoc()) {
        // Format nominal menggunakan fungsi formatNominal() dari helpers.php
        $nominal_tetap = formatNominal($row['nominal']);

        // Badge untuk jenis
        $jenis = ($row['jenis'] == 'earnings') 
            ? '<span class="badge bg-success"><i class="fas fa-plus-circle me-1"></i>Pendapatan</span>' 
            : '<span class="badge bg-danger"><i class="fas fa-minus-circle me-1"></i>Potongan</span>';

        // Tombol Aksi
        $aksi = '
<div class="dropdown">
  <button class="btn" type="button" id="dropdownMenuButton_' . htmlspecialchars($row['id']) . '" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="bi bi-three-dots-vertical"></i>
  </button>
  <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton_' . htmlspecialchars($row['id']) . '">
    <li>
      <a class="dropdown-item btn-edit" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '" title="Edit">
        <i class="fas fa-pencil-alt"></i> Edit
      </a>
    </li>
    <li>
      <a class="dropdown-item btn-delete" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '" title="Hapus">
        <i class="fas fa-trash-alt"></i> Hapus
      </a>
    </li>
  </ul>
</div>';

        $data[] = [
            "no"             => $no++,
            "nama_payhead"   => trim($row['nama_payhead']),
            "jenis"          => $jenis,
            "deskripsi"      => trim($row['deskripsi']),
            "nominal_tetap"  => $nominal_tetap,
            "aksi"           => $aksi
        ];
    }
    $stmtData->close();

    echo json_encode([
        "draw"            => $draw,
        "recordsTotal"    => $recordsTotal,
        "recordsFiltered" => $recordsFiltered,
        "data"            => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Menambahkan Payhead baru ke dalam database.
 */
function AddPayhead($conn) {
    error_log("DEBUG: AddPayhead POST: " . print_r($_POST, true));

    $nama_payhead   = isset($_POST['nama_payhead']) ? trim($_POST['nama_payhead']) : '';
    $jenis          = isset($_POST['jenis_payhead']) ? trim($_POST['jenis_payhead']) : '';
    $deskripsi      = isset($_POST['deskripsi']) ? trim($_POST['deskripsi']) : '';
    $nominal_input  = isset($_POST['nominal']) ? $_POST['nominal'] : '';
    $nominal        = floatval($nominal_input);

    error_log("DEBUG: Nilai nominal input: " . var_export($nominal_input, true) . " | Setelah floatval: " . $nominal);

    // Validasi sederhana
    if (empty($nama_payhead) || empty($jenis)) {
        send_response(2, 'Semua field wajib diisi.');
    }
    if (!in_array($jenis, ['earnings', 'deductions'])) {
        send_response(3, 'Jenis Payhead tidak valid.');
    }
    if ($nominal <= 0) {
        error_log("DEBUG: Nominal tidak valid atau kosong. Nilai: " . $nominal);
        send_response(5, 'Masukkan nominal payhead.');
    }

    // Cek duplikasi
    $stmt = $conn->prepare("SELECT id FROM payheads WHERE nama_payhead = ? AND jenis = ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("ss", $nama_payhead, $jenis);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        send_response(4, 'Payhead sudah ada.');
    }
    $stmt->close();

    // Insert ke DB
    $stmt = $conn->prepare("INSERT INTO payheads (nama_payhead, jenis, deskripsi, nominal) VALUES (?, ?, ?, ?)");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("sssd", $nama_payhead, $jenis, $deskripsi, $nominal);
    if ($stmt->execute()) {
        // Catat audit log menggunakan identifier dari session (misalnya, NIP)
        $user_id = $_SESSION['nip'] ?? '';
        $details_log = "Menambahkan Payhead: Nama='$nama_payhead', Jenis='$jenis', Nominal='$nominal'.";
        add_audit_log($conn, $user_id, 'AddPayhead', $details_log);
        send_response(0, 'Payhead berhasil ditambahkan.');
    } else {
        send_response(1, 'Gagal menambah payhead: ' . $stmt->error);
    }
    $stmt->close();
    exit();
}

/**
 * Mengambil detail Payhead untuk kebutuhan edit.
 */
function GetPayheadDetail($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        send_response(1, 'ID Payhead tidak valid.');
    }

    $stmt = $conn->prepare("SELECT * FROM payheads WHERE id = ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows == 1) {
        $payhead = $result->fetch_assoc();
        $stmt->close();
        error_log("DEBUG: Detail Payhead: " . print_r($payhead, true));
        // Catat audit log
        $user_id = $_SESSION['nip'] ?? '';
        $details_log = "Melihat detail Payhead ID $id: Nama='" . $payhead['nama_payhead'] . "'.";
        add_audit_log($conn, $user_id, 'GetPayheadDetail', $details_log);
        send_response(0, [
            'id'            => $payhead['id'],
            'nama_payhead'  => $payhead['nama_payhead'],
            'jenis'         => $payhead['jenis'],
            'deskripsi'     => $payhead['deskripsi'],
            'nominal'       => $payhead['nominal']
        ]);
    } else {
        send_response(2, 'Payhead tidak ditemukan.');
    }
    $stmt->close();
    exit();
}

/**
 * Mengupdate data Payhead berdasarkan ID.
 */
function UpdatePayhead($conn) {
    error_log("DEBUG: UpdatePayhead POST: " . print_r($_POST, true));

    $id             = isset($_POST['edit_payhead_id']) ? intval($_POST['edit_payhead_id']) : 0;
    $nama_payhead   = isset($_POST['edit_nama_payhead']) ? trim($_POST['edit_nama_payhead']) : '';
    $jenis          = isset($_POST['edit_jenis_payhead']) ? trim($_POST['edit_jenis_payhead']) : '';
    $deskripsi      = isset($_POST['edit_deskripsi']) ? trim($_POST['edit_deskripsi']) : '';
    $nominal_input  = isset($_POST['nominal']) ? $_POST['nominal'] : '';
    $nominal        = floatval($nominal_input);

    error_log("DEBUG: UpdatePayhead - Nilai nominal input: " . var_export($nominal_input, true) . " | Setelah floatval: " . $nominal);
    
    // Validasi sederhana
    if ($id <= 0 || empty($nama_payhead) || empty($jenis)) {
        send_response(3, 'Field wajib diisi dan ID Payhead harus valid.');
    }
    if (!in_array($jenis, ['earnings', 'deductions'])) {
        send_response(4, 'Jenis Payhead tidak valid.');
    }
    if ($nominal <= 0) {
        error_log("DEBUG: UpdatePayhead - Nominal tidak valid atau kosong. Nilai: " . $nominal);
        send_response(5, 'Masukkan nominal payhead.');
    }
    
    // Cek duplikasi sederhana
    $stmt = $conn->prepare("SELECT id FROM payheads WHERE nama_payhead = ? AND jenis = ? AND id != ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("ssi", $nama_payhead, $jenis, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        send_response(5, 'Payhead sudah ada.');
    }
    $stmt->close();
    
    // Update data
    $stmt = $conn->prepare("UPDATE payheads SET nama_payhead = ?, jenis = ?, deskripsi = ?, nominal = ? WHERE id = ?");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("sssdi", $nama_payhead, $jenis, $deskripsi, $nominal, $id);
    if ($stmt->execute()) {
        // Catat audit log
        $user_id = $_SESSION['nip'] ?? '';
        $details_log = "Mengupdate Payhead ID $id: Nama='$nama_payhead', Jenis='$jenis', Nominal='$nominal'.";
        add_audit_log($conn, $user_id, 'UpdatePayhead', $details_log);
        send_response(0, 'Payhead berhasil diupdate.');
    } else {
        send_response(1, 'Gagal mengupdate payhead: ' . $stmt->error);
    }
    $stmt->close();
    exit();
}

/**
 * Menghapus data Payhead dari database.
 */
function DeletePayhead($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        send_response(3, 'ID Payhead tidak valid.');
    }

    // Cek apakah payhead sedang digunakan di payroll_detail
    $stmt = $conn->prepare("SELECT id FROM payroll_detail WHERE id_payhead = ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        send_response(1, 'Eksekusi Query Error: ' . $stmt->error);
    }
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        send_response(4, 'Payhead tidak bisa dihapus karena sedang dipakai.');
    }
    $stmt->close();

    // Hapus data
    $stmt = $conn->prepare("DELETE FROM payheads WHERE id = ?");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        send_response(1, 'Gagal menghapus payhead: ' . $stmt->error);
    }

    // Catat audit log
    $user_id = $_SESSION['nip'] ?? '';
    $details_log = "Menghapus Payhead ID $id.";
    add_audit_log($conn, $user_id, 'DeletePayhead', $details_log);

    send_response(0, 'Payhead berhasil dihapus.');
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Payheads - Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <!-- DataTables CSS (Bootstrap 5) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.1.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        /* Contoh style tambahan / custom:
           - Menghilangkan background card-header custom agar tidak tabrakan dengan SB Admin 2
           - Silakan sesuaikan jika ingin tampilan berbeda */
        
        /* Hilangkan style gradien card-header agar konsisten dengan SB Admin 2 */
        .card-header {
            background-color: #4e73df; /* SB Admin 2 default header color */
            color: #fff;
        }

        /* Hover effect pada baris tabel */
        .table-hover tbody tr:hover {
            background-color: #e2e6ea;
        }

        /* Tabel responsif tambahan */
        .table-responsive {
            overflow-x: auto;
        }

        /* Spinner loading di tengah layar */
        #loadingSpinner {
            display: none;
            position: fixed;
            z-index: 9999;
            height: 100px;
            width: 100px;
            margin: auto;
            top: 0; left: 0; bottom: 0; right: 0;
        }
    </style>
</head>
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar (sudah disesuaikan dengan SB Admin 2) -->
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <?php include __DIR__ . '/../../navbar.php'; ?>
                <!-- End of Topbar -->

                <!-- Breadcrumb (opsional, jika pakai) -->
                <?php include __DIR__ . '/../../breadcrumb.php'; ?>

                <!-- Page Content -->
                <div class="container-fluid">
                    <!-- Judul Halaman -->
                    <h1 class="h3 mb-4 text-gray-800">
                        <i class="fas fa-money-check-alt me-2"></i>Manajemen Payheads
                    </h1>

                    <!-- Card Filter -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <strong><i class="fas fa-filter me-2"></i>Filter Payheads</strong>
                        </div>
                        <div class="card-body">
                            <form id="filterForm" class="row align-items-center">
                                <!-- Filter Jenis Payhead -->
                                <div class="col-md-3 mb-2">
                                    <label for="filterJenisPayhead" class="form-label">
                                        <i class="fas fa-layer-group me-1"></i>Jenis Payhead
                                    </label>
                                    <select class="form-select" id="filterJenisPayhead" name="jenis_payhead">
                                        <option value="">Semua Jenis</option>
                                        <option value="earnings">Earnings (Pendapatan)</option>
                                        <option value="deductions">Deductions (Potongan)</option>
                                    </select>
                                </div>
                                <!-- Tombol Apply Filter -->
                                <div class="col-md-3 mb-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-primary me-2" id="btnApplyFilter">
                                        <i class="fas fa-check-circle"></i> Terapkan
                                    </button>
                                    <button type="button" class="btn btn-secondary me-2" id="btnResetFilter">
                                        <i class="fas fa-undo"></i> Reset
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Placeholder Alert (jika diperlukan) -->
                    <div id="alert-placeholder"></div>

                    <!-- Tabel Data Payheads -->
                    <div class="card shadow mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-white">
                                <i class="fas fa-clipboard-list me-1"></i>Daftar Payheads
                            </h6>
                            <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addPayheadModal">
                                <i class="fas fa-plus"></i> Tambah Payhead
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="payheadsTable" class="table table-sm table-bordered table-hover table-striped display nowrap" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Payhead</th>
                                            <th>Jenis</th>
                                            <th>Deskripsi</th>
                                            <th>Nominal Tetap</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody> <!-- Akan diisi DataTables -->
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end .container-fluid -->
            </div>
            <!-- end #content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?= date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div> <!-- End content-wrapper -->
    </div> <!-- End wrapper -->

    <!-- Modal Tambah Payhead -->
    <div class="modal fade" id="addPayheadModal" tabindex="-1" aria-labelledby="addPayheadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="add-payhead-form" class="needs-validation" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="addPayheadModalLabel">
                            <i class="fas fa-plus-circle me-2"></i>Tambah Payhead
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Field "case" untuk AJAX di server -->
                        <input type="hidden" name="case" value="AddPayhead">
                        <!-- Nama Payhead -->
                        <div class="mb-3">
                            <label for="nama_payhead" class="form-label">
                                <i class="fas fa-tag me-1"></i>Nama Payhead <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="nama_payhead" name="nama_payhead" placeholder="Contoh: Tunjangan Kesehatan" required>
                            <div class="invalid-feedback">Nama payhead belum diisi.</div>
                            <div class="form-text">Masukkan nama payhead, misalnya <em>Tunjangan Kesehatan</em>.</div>
                        </div>
                        <!-- Jenis Payhead -->
                        <div class="mb-3">
                            <label for="jenis_payhead" class="form-label">
                                <i class="fas fa-layer-group me-1"></i>Jenis <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="jenis_payhead" name="jenis_payhead" required>
                                <option value="">---Pilih Jenis---</option>
                                <option value="earnings">Earnings (Pendapatan)</option>
                                <option value="deductions">Deductions (Potongan)</option>
                            </select>
                            <div class="invalid-feedback">Pilih jenis payhead.</div>
                            <div class="form-text">Pilih jenis payhead, misalnya <em>Earnings</em> untuk pendapatan atau <em>Deductions</em> untuk potongan.</div>
                        </div>
                        <!-- Deskripsi -->
                        <div class="mb-3">
                            <label for="deskripsi" class="form-label">
                                <i class="fas fa-info-circle me-1"></i>Deskripsi <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" placeholder="Contoh: Tunjangan kesehatan bulanan untuk karyawan" required></textarea>
                            <div class="invalid-feedback">Masukkan deskripsi payhead.</div>
                            <div class="form-text">Jelaskan detail payhead, misalnya <em>Tunjangan kesehatan bulanan untuk karyawan</em>.</div>
                        </div>
                        <!-- Nominal -->
                        <div class="mb-3">
                            <label for="nominal" class="form-label">
                                <i class="fas fa-money-bill-wave me-1"></i>Nominal Tetap <span class="text-danger">*</span>
                            </label>
                            <input type="text" step="0.01" class="form-control" id="nominal" name="nominal" placeholder="Contoh: 1500000" required>
                            <div class="invalid-feedback">Masukkan nominal payhead.</div>
                            <div class="form-text">Masukkan nilai nominal, misalnya <em>1500000</em> (gunakan format angka tanpa simbol).</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Tutup
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan
                            <!-- Spinner kecil saat loading -->
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Payhead -->
    <div class="modal fade" id="editPayheadModal" tabindex="-1" aria-labelledby="editPayheadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="edit-payhead-form" class="needs-validation" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="editPayheadModalLabel">
                            <i class="fas fa-edit me-2"></i>Edit Payhead
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Field "case" untuk AJAX di server -->
                        <input type="hidden" name="case" value="UpdatePayhead">
                        <input type="hidden" id="edit_payhead_id" name="edit_payhead_id">
                        <!-- Nama Payhead -->
                        <div class="mb-3">
                            <label for="edit_nama_payhead" class="form-label">
                                <i class="fas fa-tag me-1"></i>Nama Payhead <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="edit_nama_payhead" name="edit_nama_payhead" placeholder="Contoh: Tunjangan Kesehatan" required>
                            <div class="invalid-feedback">Nama payhead belum diisi.</div>
                            <div class="form-text">Masukkan nama payhead, misalnya <em>Tunjangan Kesehatan</em>.</div>
                        </div>
                        <!-- Jenis Payhead -->
                        <div class="mb-3">
                            <label for="edit_jenis_payhead" class="form-label">
                                <i class="fas fa-layer-group me-1"></i>Jenis <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="edit_jenis_payhead" name="edit_jenis_payhead" required>
                                <option value="">---Pilih Jenis---</option>
                                <option value="earnings">Earnings (Pendapatan)</option>
                                <option value="deductions">Deductions (Potongan)</option>
                            </select>
                            <div class="invalid-feedback">Pilih jenis payhead.</div>
                            <div class="form-text">Pilih jenis payhead, misalnya <em>Earnings</em> atau <em>Deductions</em>.</div>
                        </div>
                        <!-- Deskripsi -->
                        <div class="mb-3">
                            <label for="edit_deskripsi" class="form-label">
                                <i class="fas fa-info-circle me-1"></i>Deskripsi <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" id="edit_deskripsi" name="edit_deskripsi" rows="3" placeholder="Contoh: Tunjangan kesehatan bulanan untuk karyawan" required></textarea>
                            <div class="invalid-feedback">Masukkan deskripsi payhead.</div>
                            <div class="form-text">Jelaskan detail payhead, misalnya <em>Tunjangan kesehatan bulanan untuk karyawan</em>.</div>
                        </div>
                        <!-- Nominal -->
                        <div class="mb-3">
                            <label for="edit_nominal" class="form-label">
                                <i class="fas fa-money-bill-wave me-1"></i>Nominal Tetap <span class="text-danger">*</span>
                            </label>
                            <input type="text" step="0.01" class="form-control" id="edit_nominal" name="nominal" placeholder="Contoh: 1500000" required>
                            <div class="invalid-feedback">Masukkan nominal payhead.</div>
                            <div class="form-text">Masukkan nilai nominal, misalnya <em>1500000</em>.</div>
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

    <!-- Modal Delete Payhead -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form id="deleteForm">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                <i class="fas fa-trash-alt me-2"></i>Hapus Payhead
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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

    <!-- Loading Spinner (untuk menandakan proses loading AJAX) -->
    <div id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
    <!-- SB Admin 2 (opsional jika Anda memakainya) -->
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- AutoNumeric -->
    <script src="https://cdn.jsdelivr.net/npm/autonumeric@4.6.0/dist/autoNumeric.min.js"></script>

    <script>
    $(document).ready(function() {
        console.log("DEBUG: Document ready.");

        // Inisialisasi Toast dengan SweetAlert2
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

        // Inisialisasi AutoNumeric untuk input nominal di modal Tambah & Edit
        new AutoNumeric('#nominal', {
            digitGroupSeparator: '.',
            decimalCharacter: ',',
            decimalPlaces: 2,
            unformatOnSubmit: true
        });
        new AutoNumeric('#edit_nominal', {
            digitGroupSeparator: '.',
            decimalCharacter: ',',
            decimalPlaces: 2,
            unformatOnSubmit: true
        });

        // Definisikan DataTable
        var payheadsTable = $('#payheadsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "payheads.php?ajax=1",
                type: "POST",
                data: function (d) {
                    d.case = 'LoadingPayheads';
                    d.jenis_payhead = $('#filterJenisPayhead').val();
                },
                beforeSend: function(){
                    // Tampilkan spinner
                    $('#loadingSpinner').show();
                },
                complete: function(){
                    // Sembunyikan spinner
                    $('#loadingSpinner').hide();
                },
                error: function(){
                    showToast('Terjadi kesalahan saat memuat data payheads.', 'error');
                }
            },
            columns: [
                { data: "no", orderable: false },
                { data: "nama_payhead" },
                { data: "jenis" },
                { data: "deskripsi" },
                { data: "nominal_tetap" },
                { data: "aksi", orderable: false }
            ],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
            },
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Export Excel',
                    className: 'btn btn-success btn-sm',
                    exportOptions: { columns: [0,1,2,3,4] }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> Export PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: { columns: [0,1,2,3,4] },
                    customize: function (doc) {
                        doc.styles.tableHeader.fillColor = '#343a40';
                        doc.styles.tableHeader.color = 'white';
                        doc.defaultStyle.fontSize = 10;
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print"></i> Print',
                    className: 'btn btn-info btn-sm',
                    exportOptions: { columns: [0,1,2,3,4] }
                }
            ],
            responsive: true,
            autoWidth: false
        });

        // Tombol "Terapkan Filter"
        $('#btnApplyFilter').on('click', function(){
            payheadsTable.ajax.reload();
        });

        // Tombol "Reset Filter"
        $('#btnResetFilter').on('click', function(){
            $('#filterForm')[0].reset();
            payheadsTable.ajax.reload();
        });

        // Tombol "Export Data" -> Trigger Export Excel
        $('#btnExportData').on('click', function(){
            payheadsTable.button('.buttons-excel').trigger();
        });

        // Validasi form bootstrap 5 (menggunakan .needs-validation)
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Form Tambah Payhead
        $('#add-payhead-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            var formData = form.serialize();
            $.ajax({
                url: "payheads.php?ajax=1",
                type: "POST",
                data: formData,
                dataType: "json",
                beforeSend: function(){
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success: function(response){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if (response.code == 0) {
                        showToast(response.result, 'success');
                        $('#addPayheadModal').modal('hide');
                        payheadsTable.ajax.reload(null, false);
                        form[0].reset();
                        form.removeClass('was-validated');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat menambah payhead.', 'error');
                }
            });
        });

        // Tombol Edit -> ambil detail
        $(document).on('click', '.btn-edit', function() {
            var id = $(this).data('id');
            var modal = $('#editPayheadModal');
            var form = $('#edit-payhead-form');
            form[0].reset();
            form.removeClass('was-validated');
            $.ajax({
                url: "payheads.php?ajax=1",
                type: "POST",
                data: { id: id, case: 'GetPayheadDetail' },
                dataType: "json",
                success: function(response){
                    if (response.code == 0) {
                        $('#edit_payhead_id').val(response.result.id);
                        $('#edit_nama_payhead').val(response.result.nama_payhead);
                        $('#edit_jenis_payhead').val(response.result.jenis);
                        $('#edit_deskripsi').val(response.result.deskripsi);
                        // Set nominal ke AutoNumeric (modal Edit)
                        var anEdit = AutoNumeric.getAutoNumericElement('#edit_nominal');
                        anEdit.set(response.result.nominal);

                        modal.modal('show');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    showToast('Terjadi kesalahan mengambil detail payhead.', 'error');
                }
            });
        });

        // Form Update Payhead
        $('#edit-payhead-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);

            // Update nilai input dari AutoNumeric (modal Edit) sebelum submit
            var anEdit = AutoNumeric.getAutoNumericElement('#edit_nominal');
            $('#edit_nominal').val(anEdit.getNumber());

            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            var formData = form.serialize();
            $.ajax({
                url: "payheads.php?ajax=1",
                type: "POST",
                data: formData,
                dataType: "json",
                beforeSend: function(){
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success: function(response){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if (response.code == 0) {
                        showToast(response.result, 'success');
                        $('#editPayheadModal').modal('hide');
                        payheadsTable.ajax.reload(null, false);
                        form[0].reset();
                        form.removeClass('was-validated');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat update payhead.', 'error');
                }
            });
        });

        // Tombol Hapus -> Tampilkan modal konfirmasi
        $(document).on('click', '.btn-delete', function() {
            var id = $(this).data('id');
            $('#delete_id').val(id);
            $('#deleteModal').modal('show');
        });

        // Form Delete Payhead
        $('#deleteForm').on('submit', function(e){
            e.preventDefault();
            var id = $('#delete_id').val();
            if (!id) {
                showToast('ID Payhead tidak ditemukan.', 'error');
                return;
            }
            $.ajax({
                url: "payheads.php?ajax=1",
                type: "POST",
                data: {
                    id: id,
                    case: 'DeletePayhead'
                },
                dataType: "json",
                beforeSend: function(){
                    $('#deleteForm').find('button[type="submit"]').prop('disabled', true);
                    $('#deleteForm').find('.spinner-border').removeClass('d-none');
                },
                success: function(response){
                    $('#deleteForm').find('button[type="submit"]').prop('disabled', false);
                    $('#deleteForm').find('.spinner-border').addClass('d-none');
                    if (response.code == 0) {
                        showToast(response.result, 'success');
                        $('#deleteModal').modal('hide');
                        payheadsTable.ajax.reload(null, false);
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(xhr, status, error){
                    $('#deleteForm').find('button[type="submit"]').prop('disabled', false);
                    $('#deleteForm').find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat menghapus payhead: ' + error, 'error');
                }
            });
        });

        // Aksi tekan Enter di filter form
        $('#filterForm').on('keypress', function(e){
            if(e.which === 13) {
                $('#btnApplyFilter').click();
            }
        });
    });
    </script>
</body>
</html>
