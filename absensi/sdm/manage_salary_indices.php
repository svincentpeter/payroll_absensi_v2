<?php
// File: /payroll_absensi_v2/pegawai/manage_salary_indices.php

// ==============================================================================
// 1. Pengaturan Session, Koneksi, dan Helper
// ==============================================================================
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();

authorize(['sdm', 'superadmin'], '/payroll_absensi_v2/login.php');

// Koneksi ke database
require_once __DIR__ . '/../../koneksi.php';

// Nonaktifkan output buffering jika ada
if (ob_get_length()) {
    ob_end_clean();
}

// ==============================================================================
// 2. Menangani Permintaan AJAX
// ==============================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // (CSRF token dihapus)

        $case = isset($_POST['case']) ? trim($_POST['case']) : '';
        switch ($case) {
            case 'LoadingSalaryIndices':
                LoadingSalaryIndices($conn);
                break;
            case 'AddSalaryIndex':
                AddSalaryIndex($conn);
                break;
            case 'GetSalaryIndexDetail':
                GetSalaryIndexDetail($conn);
                break;
            case 'UpdateSalaryIndex':
                UpdateSalaryIndex($conn);
                break;
            case 'DeleteSalaryIndex':
                DeleteSalaryIndex($conn);
                break;
            case 'AddAuditLog':
                // Contoh pencatatan audit log (opsional)
                $action = isset($_POST['action']) ? trim($_POST['action']) : '';
                $details = isset($_POST['details']) ? trim($_POST['details']) : '';
                if (!empty($action) && !empty($details)) {
                    $user_id = $_SESSION['nip'] ?? '';
                    $logged = add_audit_log($conn, $user_id, $action, $details);
                    if ($logged) {
                        send_response(0, 'Audit log berhasil dicatat.');
                    } else {
                        send_response(1, 'Gagal mencatat audit log.');
                    }
                } else {
                    send_response(1, 'Data audit log tidak lengkap.');
                }
                break;
            default:
                send_response(404, 'Kasus tidak ditemukan.');
        }
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    exit();
}

// ==============================================================================
// 3. Fungsi CRUD untuk Salary Indices dengan Audit Log
// ==============================================================================
function LoadingSalaryIndices($conn) {
    error_log("Memulai LoadingSalaryIndices");
    // Parameter DataTables
    $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

    // Hitung total records
    $sqlTotal = "SELECT COUNT(*) as total FROM salary_indices";
    $resultTotal = mysqli_query($conn, $sqlTotal);
    if (!$resultTotal) {
        send_response(1, 'Query Error: ' . mysqli_error($conn));
    }
    $rowTotal = mysqli_fetch_assoc($resultTotal);
    $recordsTotal = $rowTotal['total'];

    // Bangun query filter
    $sqlFilter = "SELECT * FROM salary_indices WHERE 1=1";
    $sqlFilterCount = "SELECT COUNT(*) as total FROM salary_indices WHERE 1=1";
    $paramsFilter = [];
    $typesFilter = "";
    if (!empty($search)) {
        $sqlFilter      .= " AND (level LIKE ? OR description LIKE ?)";
        $sqlFilterCount .= " AND (level LIKE ? OR description LIKE ?)";
        $searchParam = "%" . $search . "%";
        $paramsFilter = [$searchParam, $searchParam];
        $typesFilter = "ss";
    }

    // Hitung recordsFiltered
    $stmtFiltered = $conn->prepare($sqlFilterCount);
    if ($stmtFiltered === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    if (!empty($paramsFilter)) {
        $stmtFiltered->bind_param($typesFilter, ...$paramsFilter);
    }
    $stmtFiltered->execute();
    $resultFiltered = $stmtFiltered->get_result();
    if (!$resultFiltered) {
        send_response(1, 'Query Error: ' . $stmtFiltered->error);
    }
    $rowFiltered = $resultFiltered->fetch_assoc();
    $recordsFiltered = isset($rowFiltered['total']) ? $rowFiltered['total'] : 0;
    $stmtFiltered->close();

    // Order by (default: id DESC)
    $orderBy = " ORDER BY id DESC";
    if (isset($_POST['order'], $_POST['columns'])) {
        $columnIndex = intval($_POST['order'][0]['column']);
        $allowedColumns = ['level', 'min_years', 'max_years', 'base_salary', 'description'];
        if (isset($_POST['columns'][$columnIndex]['data']) && in_array($_POST['columns'][$columnIndex]['data'], $allowedColumns)) {
            $colName = $_POST['columns'][$columnIndex]['data'];
            $colSortOrder = (isset($_POST['order'][0]['dir']) && $_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
            $orderBy = " ORDER BY $colName $colSortOrder";
        }
    }

    // Limit
    $limit = " LIMIT ?, ?";
    if (!empty($paramsFilter)) {
        $paramsFilter[] = $start;
        $paramsFilter[] = $length;
        $typesFilter .= "ii";
    } else {
        $paramsFilter = [$start, $length];
        $typesFilter = "ii";
    }
    $sqlFilter .= $orderBy . $limit;

    $stmtData = $conn->prepare($sqlFilter);
    if ($stmtData === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmtData->bind_param($typesFilter, ...$paramsFilter);
    $stmtData->execute();
    $dataQuery = $stmtData->get_result();
    if (!$dataQuery) {
        send_response(1, 'Query Error: ' . $stmtData->error);
    }

    $data = [];
    $no = $start + 1;
    while ($row = $dataQuery->fetch_assoc()) {
        $base_salary = number_format($row['base_salary'], 2, ',', '.');
        $max_years = ($row['max_years'] === null) ? '-' : (int)$row['max_years'];

        // Dropdown aksi (Edit & Hapus)
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
            "no"          => $no++,
            "level"       => htmlspecialchars($row['level']),
            "min_years"   => (int)$row['min_years'],
            "max_years"   => $max_years,
            "base_salary" => $base_salary,
            "description" => htmlspecialchars($row['description']),
            "aksi"        => $aksi
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

function AddSalaryIndex($conn) {
    $level       = isset($_POST['level']) ? trim($_POST['level']) : '';
    $min_years   = isset($_POST['min_years']) ? intval($_POST['min_years']) : 0;
    $max_years   = (isset($_POST['max_years']) && $_POST['max_years'] !== '') ? intval($_POST['max_years']) : null;
    $base_salary = isset($_POST['base_salary']) ? floatval($_POST['base_salary']) : 0;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    if (empty($level)) {
        send_response(2, 'Level wajib diisi.');
    }
    // Cek duplikasi level
    $stmt = $conn->prepare("SELECT id FROM salary_indices WHERE level = ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("s", $level);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        send_response(1, 'Level indeks sudah ada.');
    }
    $stmt->close();

    // Insert data
    $stmt = $conn->prepare("INSERT INTO salary_indices (level, min_years, max_years, base_salary, description) VALUES (?, ?, ?, ?, ?)");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("siids", $level, $min_years, $max_years, $base_salary, $description);
    if ($stmt->execute()) {
        $user_id = $_SESSION['nip'] ?? '';
        $details_log = "Menambahkan Indeks Gaji: Level='$level', Min Tahun='$min_years', Max Tahun='" . ($max_years ?? 'NULL') . "', Gaji Pokok='$base_salary', Keterangan='$description'.";
        add_audit_log($conn, $user_id, 'AddSalaryIndex', $details_log);
        send_response(0, 'Indeks gaji berhasil ditambahkan.');
    } else {
        send_response(1, 'Gagal menambah indeks gaji: ' . $stmt->error);
    }
    $stmt->close();
    exit();
}

function GetSalaryIndexDetail($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        send_response(1, 'ID tidak valid.');
    }
    $stmt = $conn->prepare("SELECT * FROM salary_indices WHERE id = ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $stmt->close();
        $user_id = $_SESSION['nip'] ?? '';
        $details_log = "Melihat detail Indeks Gaji ID $id: Level='{$row['level']}', Min Tahun='{$row['min_years']}', Max Tahun='" . ($row['max_years'] ?? 'NULL') . "', Gaji Pokok='{$row['base_salary']}', Keterangan='{$row['description']}'.";
        add_audit_log($conn, $user_id, 'ViewSalaryIndexDetail', $details_log);
        send_response(0, [
            'id' => $row['id'],
            'level' => $row['level'],
            'min_years' => $row['min_years'],
            'max_years' => $row['max_years'],
            'base_salary' => $row['base_salary'],
            'description' => $row['description']
        ]);
    } else {
        send_response(2, 'Data tidak ditemukan.');
    }
    $stmt->close();
    exit();
}

function UpdateSalaryIndex($conn) {
    $id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
    $level = isset($_POST['edit_level']) ? trim($_POST['edit_level']) : '';
    $min_years = isset($_POST['edit_min_years']) ? intval($_POST['edit_min_years']) : 0;
    $max_years = (isset($_POST['edit_max_years']) && $_POST['edit_max_years'] !== '') ? intval($_POST['edit_max_years']) : null;
    $base_salary = isset($_POST['edit_base_salary']) ? floatval($_POST['edit_base_salary']) : 0;
    $description = isset($_POST['edit_description']) ? trim($_POST['edit_description']) : '';

    if ($id <= 0 || empty($level)) {
        send_response(3, 'Field wajib diisi dan ID harus valid.');
    }

    // Cek duplikasi selain record yang sedang diedit
    $stmt = $conn->prepare("SELECT id FROM salary_indices WHERE level = ? AND id != ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("si", $level, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        send_response(5, 'Level indeks sudah dipakai.');
    }
    $stmt->close();

    // Update data
    $stmt = $conn->prepare("UPDATE salary_indices SET level = ?, min_years = ?, max_years = ?, base_salary = ?, description = ? WHERE id = ?");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("siidsi", $level, $min_years, $max_years, $base_salary, $description, $id);
    if ($stmt->execute()) {
        $user_id = $_SESSION['nip'] ?? '';
        $details_log = "Mengupdate Indeks Gaji ID $id: Level='$level', Min Tahun='$min_years', Max Tahun='" . ($max_years ?? 'NULL') . "', Gaji Pokok='$base_salary', Keterangan='$description'.";
        add_audit_log($conn, $user_id, 'UpdateSalaryIndex', $details_log);
        send_response(0, 'Indeks gaji berhasil diupdate.');
    } else {
        send_response(1, 'Gagal mengupdate data: ' . $stmt->error);
    }
    $stmt->close();
    exit();
}

function DeleteSalaryIndex($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        send_response(3, 'ID tidak valid.');
    }
    $stmt = $conn->prepare("DELETE FROM salary_indices WHERE id = ?");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        send_response(1, 'Gagal menghapus data: ' . $stmt->error);
    }
    $user_id = $_SESSION['nip'] ?? '';
    $details_log = "Menghapus Indeks Gaji ID $id.";
    add_audit_log($conn, $user_id, 'DeleteSalaryIndex', $details_log);
    send_response(0, 'Indeks gaji berhasil dihapus.');
    $stmt->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Indeks/Kenaikan Gaji Pokok - Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- DataTables CSS (Bootstrap 5) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.1.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        /* Styling khusus tanpa sidebar dan navbar */
        body { padding-top: 20px; }
        #main-content {
            transition: opacity 0.3s ease;
        }
        .back-btn {
            margin-bottom: 20px;
        }
        .btn {
            transition: background-color 0.3s, transform 0.2s;
        }
        .btn:hover {
            transform: scale(1.05);
        }
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }
        .table-hover tbody tr:hover {
            background-color: #e2e6ea;
        }
        #salaryIndicesTable.table-sm th, #salaryIndicesTable.table-sm td {
            font-size: 13px;
            vertical-align: middle;
            white-space: nowrap;
        }
        thead th {
            background-color: #343a40;
            color: white;
            text-align: left;
        }
        .table-responsive {
            overflow-x: auto;
        }
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
<body>
    <!-- Container Utama Tanpa Sidebar/Navbar -->
    <div class="container" id="main-content">
        <!-- Tombol Back -->
        <button class="btn btn-secondary back-btn" id="btnBack" data-href="/payroll_absensi_v2/absensi/sdm/manage_guru_karyawan.php">
            <i class="fas fa-arrow-left"></i> Kembali ke Manajemen Guru/Karyawan
        </button>

        <!-- Konten Halaman Manajemen Indeks Gaji -->
        <h1 class="h3 mb-4 text-dark"><i class="fas fa-chart-line"></i> Manajemen Indeks/Kenaikan Gaji Pokok</h1>

        <!-- Tombol Tambah -->
        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSalaryIndexModal">
                <i class="fas fa-plus"></i> Tambah Indeks Gaji
            </button>
        </div>

        <!-- Tabel Indeks Gaji -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-white"><i class="fas fa-clipboard-list"></i> Daftar Indeks Gaji</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="salaryIndicesTable" class="table table-sm table-bordered table-striped display nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Level</th>
                                <th>Min. Masa Kerja (Thn)</th>
                                <th>Max. Masa Kerja (Thn)</th>
                                <th>Gaji Pokok (Rp)</th>
                                <th>Keterangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL: Tambah Indeks Gaji -->
        <div class="modal fade" id="addSalaryIndexModal" tabindex="-1" aria-labelledby="addSalaryIndexModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="add-salary-index-form" class="needs-validation" novalidate>
                        <input type="hidden" name="case" value="AddSalaryIndex">
                        <!-- CSRF token dihapus -->
                        <div class="modal-header">
                            <h5 class="modal-title" id="addSalaryIndexModalLabel"><i class="fas fa-plus"></i> Tambah Indeks Gaji</h5>
                            <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="level" class="form-label">Level <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="level" name="level" required>
                                <div class="invalid-feedback">Level belum diisi.</div>
                            </div>
                            <div class="mb-3">
                                <label for="min_years" class="form-label">Min. Masa Kerja (Tahun) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="min_years" name="min_years" min="0" required>
                                <div class="invalid-feedback">Minimal masa kerja belum diisi.</div>
                            </div>
                            <div class="mb-3">
                                <label for="max_years" class="form-label">Max. Masa Kerja (Tahun)</label>
                                <input type="number" class="form-control" id="max_years" name="max_years" min="0">
                            </div>
                            <div class="mb-3">
                                <label for="base_salary" class="form-label">Gaji Pokok (Rp) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="base_salary" name="base_salary" min="0" step="0.01" required>
                                <div class="invalid-feedback">Gaji pokok belum diisi.</div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Keterangan</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Tutup</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL: Edit Indeks Gaji -->
        <div class="modal fade" id="editSalaryIndexModal" tabindex="-1" aria-labelledby="editSalaryIndexModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="edit-salary-index-form" class="needs-validation" novalidate>
                        <input type="hidden" name="case" value="UpdateSalaryIndex">
                        <input type="hidden" id="edit_id" name="edit_id">
                        <!-- CSRF token dihapus -->
                        <div class="modal-header">
                            <h5 class="modal-title" id="editSalaryIndexModalLabel"><i class="fas fa-edit"></i> Edit Indeks Gaji</h5>
                            <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="edit_level" class="form-label">Level <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_level" name="edit_level" required>
                                <div class="invalid-feedback">Level belum diisi.</div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_min_years" class="form-label">Min. Masa Kerja (Tahun) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_min_years" name="edit_min_years" min="0" required>
                                <div class="invalid-feedback">Minimal masa kerja belum diisi.</div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_max_years" class="form-label">Max. Masa Kerja (Tahun)</label>
                                <input type="number" class="form-control" id="edit_max_years" name="edit_max_years" min="0">
                            </div>
                            <div class="mb-3">
                                <label for="edit_base_salary" class="form-label">Gaji Pokok (Rp) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_base_salary" name="edit_base_salary" min="0" step="0.01" required>
                                <div class="invalid-feedback">Gaji pokok belum diisi.</div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_description" class="form-label">Keterangan</label>
                                <textarea class="form-control" id="edit_description" name="edit_description" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Tutup</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL: Hapus Indeks Gaji -->
        <div class="modal fade" id="deleteSalaryIndexModal" tabindex="-1" aria-labelledby="deleteSalaryIndexModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form id="delete-salary-index-form">
                    <input type="hidden" name="case" value="DeleteSalaryIndex">
                    <input type="hidden" id="delete_id" name="id">
                    <!-- CSRF token dihapus -->
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-trash-alt"></i> Hapus Indeks Gaji</h5>
                            <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <p>Yakin ingin menghapus Indeks Gaji <strong id="delete_level"></strong>?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Tidak</button>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Ya, Hapus
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- End Container Utama -->

    <!-- Loading Spinner -->
    <div id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS (Bootstrap 5) -->
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
    <!-- DataTables Buttons JS -->
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.bootstrap5.min.js"></script>
    <!-- Ekstensi ColVis -->
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.colVis.min.js"></script>
    <!-- Ekstensi Ekspor & Print -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    $(document).ready(function() {
        // Inisialisasi SweetAlert2 Toast
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

        // Inisialisasi DataTable untuk Salary Indices
        var salaryIndicesTable = $('#salaryIndicesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "manage_salary_indices.php?ajax=1",
                type: "POST",
                data: function(d) {
                    d.case = 'LoadingSalaryIndices';
                },
                beforeSend: function(){
                    $('#loadingSpinner').show();
                },
                complete: function(){
                    $('#loadingSpinner').hide();
                },
                error: function(){
                    showToast('Terjadi kesalahan saat memuat data.', 'error');
                }
            },
            columns: [
                { data: "no", orderable: false },
                { data: "level" },
                { data: "min_years" },
                { data: "max_years" },
                { data: "base_salary" },
                { data: "description" },
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
                    exportOptions: { columns: [0,1,2,3,4,5] }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> Export PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: { columns: [0,1,2,3,4,5] },
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
                    exportOptions: { columns: [0,1,2,3,4,5] }
                },
                {
                    extend: 'colvis',
                    text: '<i class="fas fa-columns"></i> Kolom',
                    className: 'btn btn-warning btn-sm'
                }
            ],
            responsive: true,
            autoWidth: false
        });

        // Tombol Back dengan transisi smooth
        $('#btnBack').on('click', function(e) {
            e.preventDefault();
            var url = $(this).data('href');
            $('#main-content').fadeOut(300, function() {
                window.location.href = url;
            });
        });

        // Form: Tambah Indeks Gaji
        $('#add-salary-index-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            var formData = form.serialize();
            $.ajax({
                url: "manage_salary_indices.php?ajax=1",
                type: "POST",
                data: formData,
                dataType: "json",
                beforeSend: function(){
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success: function(response) {
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if(response.code == 0) {
                        showToast(response.result, 'success');
                        $('#addSalaryIndexModal').modal('hide');
                        salaryIndicesTable.ajax.reload(null, false);
                        form[0].reset();
                        form.removeClass('was-validated');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function() {
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat menambah data.', 'error');
                }
            });
        });

        // Modal Edit: Buka dan ambil detail data
        $(document).on('click', '.btn-edit', function() {
            var id = $(this).data('id');
            var modal = $('#editSalaryIndexModal');
            var form = $('#edit-salary-index-form');
            form[0].reset();
            form.removeClass('was-validated');
            $.ajax({
                url: "manage_salary_indices.php?ajax=1",
                type: "POST",
                data: { id: id, case: 'GetSalaryIndexDetail' },
                dataType: "json",
                success: function(response) {
                    if(response.code == 0) {
                        $('#edit_id').val(response.result.id);
                        $('#edit_level').val(response.result.level);
                        $('#edit_min_years').val(response.result.min_years);
                        $('#edit_max_years').val(response.result.max_years);
                        $('#edit_base_salary').val(response.result.base_salary);
                        $('#edit_description').val(response.result.description);
                        modal.modal('show');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function() {
                    showToast('Terjadi kesalahan saat mengambil detail data.', 'error');
                }
            });
        });

        // Form: Update Indeks Gaji
        $('#edit-salary-index-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            var formData = form.serialize();
            $.ajax({
                url: "manage_salary_indices.php?ajax=1",
                type: "POST",
                data: formData,
                dataType: "json",
                beforeSend: function(){
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success: function(response) {
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if(response.code == 0) {
                        showToast(response.result, 'success');
                        $('#editSalaryIndexModal').modal('hide');
                        salaryIndicesTable.ajax.reload(null, false);
                        form[0].reset();
                        form.removeClass('was-validated');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function() {
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat update data.', 'error');
                }
            });
        });

        // Modal Hapus: Buka modal hapus
        $(document).on('click', '.btn-delete', function() {
            var id = $(this).data('id');
            $('#delete_id').val(id);
            var level = $(this).closest('tr').find('td:eq(1)').text();
            $('#delete_level').text(level);
            $('#deleteSalaryIndexModal').modal('show');
        });

        // Form: Hapus Indeks Gaji
        $('#delete-salary-index-form').on('submit', function(e) {
            e.preventDefault();
            var id = $('#delete_id').val();
            if (!id) {
                showToast('ID tidak ditemukan.', 'error');
                return;
            }
            $.ajax({
                url: "manage_salary_indices.php?ajax=1",
                type: "POST",
                data: { id: id, case: 'DeleteSalaryIndex' },
                dataType: "json",
                beforeSend: function(){
                    $('#delete-salary-index-form').find('button[type="submit"]').prop('disabled', true);
                    $('#delete-salary-index-form').find('.spinner-border').removeClass('d-none');
                },
                success: function(response) {
                    $('#delete-salary-index-form').find('button[type="submit"]').prop('disabled', false);
                    $('#delete-salary-index-form').find('.spinner-border').addClass('d-none');
                    if(response.code == 0) {
                        showToast(response.result, 'success');
                        $('#deleteSalaryIndexModal').modal('hide');
                        salaryIndicesTable.ajax.reload(null, false);
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function() {
                    $('#delete-salary-index-form').find('button[type="submit"]').prop('disabled', false);
                    $('#delete-salary-index-form').find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat menghapus data.', 'error');
                }
            });
        });
    });
    </script>
</body>
</html>
