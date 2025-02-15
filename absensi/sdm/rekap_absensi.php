<?php
// File: /payroll_absensi_v2/payroll/keuangan/rekap_absensi.php

// ==============================================================================
// 1. Inisialisasi Session, Helper, dan Pengecekan Role
// ==============================================================================
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();

// Pastikan hanya role "keuangan" dan "superadmin" yang boleh mengakses
authorize(['sdm', 'superadmin'], '/payroll_absensi_v2/login.php');

// Koneksi ke database
require_once __DIR__ . '/../../koneksi.php';

// Hapus output buffering jika ada
if (ob_get_length()) {
    ob_end_clean();
}

// Hasilkan CSRF token (meskipun di sini awalnya sistem CSRF tidak digunakan, 
// kami menyisipkannya agar konsisten dengan halaman lain)
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

// ==============================================================================
// 2. Menangani Permintaan AJAX (Server-side Processing)
// ==============================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Jika diperlukan, verifikasi CSRF token (opsional; di sini Anda bisa menonaktifkan jika memang tidak ingin)
        // verify_csrf_token($_POST['csrf_token'] ?? '');

        $case = isset($_POST['case']) ? bersihkan_input($_POST['case']) : '';
        switch ($case) {
            case 'LoadingRekap':
                LoadingRekap($conn);
                break;
            case 'AddRekap':
                AddRekap($conn);
                break;
            case 'EditRekap':
                EditRekap($conn);
                break;
            case 'DeleteRekap':
                DeleteRekap($conn);
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

// ==============================================================================
// 3. Fungsi-Fungsi CRUD (AJAX)
// ==============================================================================

function LoadingRekap($conn) {
    $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = isset($_POST['search']['value']) ? bersihkan_input($_POST['search']['value']) : '';

    // Bangun query dasar
    $sqlBase = "FROM rekap_absensi WHERE 1=1";
    $params = [];
    $types  = "";
    if (!empty($search)) {
        $sqlBase .= " AND (id LIKE ? OR id_anggota LIKE ? OR bulan LIKE ? OR tahun LIKE ? OR total_hadir LIKE ? OR total_izin LIKE ? OR total_cuti LIKE ? OR total_tanpa_keterangan LIKE ? OR total_sakit LIKE ?)";
        $searchParam = "%" . $search . "%";
        for ($i = 0; $i < 9; $i++) {
            $params[] = $searchParam;
            $types .= "s";
        }
    }

    // Hitung filtered records
    $sqlCountFilter = "SELECT COUNT(*) AS total " . $sqlBase;
    $stmtFiltered = $conn->prepare($sqlCountFilter);
    if ($stmtFiltered === false) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    if (!empty($params)) {
        $stmtFiltered->bind_param($types, ...$params);
    }
    $stmtFiltered->execute();
    $resFiltered = $stmtFiltered->get_result();
    $rowFiltered = $resFiltered->fetch_assoc();
    $totalFiltered = isset($rowFiltered['total']) ? $rowFiltered['total'] : 0;
    $stmtFiltered->close();

    // Hitung total records (tanpa filter)
    $sqlTotal = "SELECT COUNT(*) AS total FROM rekap_absensi";
    $resTotal = $conn->query($sqlTotal);
    if (!$resTotal) {
        send_response(1, 'Query gagal: ' . $conn->error);
    }
    $rowTotal = $resTotal->fetch_assoc();
    $recordsTotal = isset($rowTotal['total']) ? $rowTotal['total'] : 0;

    // Data query
    $sqlData = "SELECT id, id_anggota, bulan, tahun, total_hadir, total_izin, total_cuti, total_tanpa_keterangan, total_sakit " . $sqlBase;

    // Sorting (default: id DESC)
    $orderBy = " ORDER BY id DESC";
    if (isset($_POST['order'][0]['column']) && isset($_POST['columns'])) {
        $colIndex = intval($_POST['order'][0]['column']);
        $allowedCols = ['id', 'id_anggota', 'bulan', 'tahun', 'total_hadir', 'total_izin', 'total_cuti', 'total_tanpa_keterangan', 'total_sakit'];
        if (isset($_POST['columns'][$colIndex]['data']) && in_array($_POST['columns'][$colIndex]['data'], $allowedCols)) {
            $colName = $_POST['columns'][$colIndex]['data'];
            $colSortOrder = ($_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
            $orderBy = " ORDER BY $colName $colSortOrder";
        }
    }

    $limit = " LIMIT ?, ?";
    $paramsData = $params;
    $typesData  = $types;
    $paramsData[] = $start;
    $paramsData[] = $length;
    $typesData .= "ii";
    $sqlData .= $orderBy . $limit;

    $stmtData = $conn->prepare($sqlData);
    if ($stmtData === false) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmtData->bind_param($typesData, ...$paramsData);
    if (!$stmtData->execute()) {
        send_response(1, 'Execute failed: ' . $stmtData->error);
    }
    $resData = $stmtData->get_result();

    $data = [];
    // Fungsi untuk mengonversi integer bulan ke nama bulan (Indonesia)
    $bulanText = function($m) {
        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        return isset($namaBulan[$m]) ? $namaBulan[$m] : 'Tidak Diketahui';
    };

    while ($row = $resData->fetch_assoc()) {
        // Tombol aksi (Edit & Delete) menggunakan Bootstrap 5
        $aksi = '
        <button class="btn btn-primary btn-sm btnEdit" data-id="' . $row['id'] . '">
          <i class="bi bi-pencil-square"></i> Edit
        </button>
        <button class="btn btn-danger btn-sm btnDelete" data-id="' . $row['id'] . '">
          <i class="bi bi-trash-fill"></i> Delete
        </button>';
        
        $data[] = [
            "id" => $row['id'],
            "id_anggota" => $row['id_anggota'],
            "bulan" => $bulanText($row['bulan']),
            "tahun" => $row['tahun'],
            "total_hadir" => $row['total_hadir'],
            "total_izin" => $row['total_izin'],
            "total_cuti" => $row['total_cuti'],
            "total_tanpa_keterangan" => $row['total_tanpa_keterangan'],
            "total_sakit" => $row['total_sakit'],
            "aksi" => $aksi
        ];
    }
    $stmtData->close();

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $recordsTotal,
        "recordsFiltered" => $totalFiltered,
        "data" => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

function AddRekap($conn) {
    $id_anggota = isset($_POST['id_anggota']) ? intval($_POST['id_anggota']) : 0;
    $bulan      = isset($_POST['bulan']) ? intval($_POST['bulan']) : 0;
    $tahun      = isset($_POST['tahun']) ? intval($_POST['tahun']) : 0;
    $hadir      = isset($_POST['total_hadir']) ? intval($_POST['total_hadir']) : 0;
    $izin       = isset($_POST['total_izin']) ? intval($_POST['total_izin']) : 0;
    $cuti       = isset($_POST['total_cuti']) ? intval($_POST['total_cuti']) : 0;
    $tk         = isset($_POST['total_tanpa_keterangan']) ? intval($_POST['total_tanpa_keterangan']) : 0;
    $sakit      = isset($_POST['total_sakit']) ? intval($_POST['total_sakit']) : 0;

    if ($id_anggota <= 0 || $bulan <= 0 || $tahun <= 0) {
        send_response(1, 'Parameter rekap tidak valid.');
    }

    // Cek apakah rekap untuk karyawan dan periode yang sama sudah ada
    $stmtCheck = $conn->prepare("SELECT id FROM rekap_absensi WHERE id_anggota=? AND bulan=? AND tahun=? LIMIT 1");
    if (!$stmtCheck) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmtCheck->bind_param("iii", $id_anggota, $bulan, $tahun);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result();
    if ($resCheck->num_rows > 0) {
        send_response(1, 'Rekap absensi untuk karyawan ini dan periode tersebut sudah ada.');
    }
    $stmtCheck->close();

    $stmt = $conn->prepare("INSERT INTO rekap_absensi (id_anggota, bulan, tahun, total_hadir, total_izin, total_cuti, total_tanpa_keterangan, total_sakit)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("iiiiiiii", $id_anggota, $bulan, $tahun, $hadir, $izin, $cuti, $tk, $sakit);
    if ($stmt->execute()) {
        // Catat audit log menggunakan identifier dari session (misalnya, NIP)
        $user_id = $_SESSION['nip'] ?? '';
        $details_log = "Menambah rekap absensi untuk ID Anggota $id_anggota, Bulan $bulan, Tahun $tahun";
        add_audit_log($conn, $user_id, 'AddRekap', $details_log);
        send_response(0, 'Data rekap absensi berhasil ditambah.');
    } else {
        send_response(1, 'Gagal menambah rekap: ' . $stmt->error);
    }
    $stmt->close();
    exit();
}

function EditRekap($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $id_anggota = isset($_POST['id_anggota']) ? intval($_POST['id_anggota']) : 0;
    $bulan      = isset($_POST['bulan']) ? intval($_POST['bulan']) : 0;
    $tahun      = isset($_POST['tahun']) ? intval($_POST['tahun']) : 0;
    $hadir      = isset($_POST['total_hadir']) ? intval($_POST['total_hadir']) : 0;
    $izin       = isset($_POST['total_izin']) ? intval($_POST['total_izin']) : 0;
    $cuti       = isset($_POST['total_cuti']) ? intval($_POST['total_cuti']) : 0;
    $tk         = isset($_POST['total_tanpa_keterangan']) ? intval($_POST['total_tanpa_keterangan']) : 0;
    $sakit      = isset($_POST['total_sakit']) ? intval($_POST['total_sakit']) : 0;

    if ($id <= 0 || $id_anggota <= 0 || $bulan <= 0 || $tahun <= 0) {
        send_response(1, 'ID rekap_absensi atau parameter tidak valid.');
    }

    // Cek apakah rekap dengan ID tersebut ada
    $stmtCheck = $conn->prepare("SELECT id FROM rekap_absensi WHERE id=? LIMIT 1");
    if (!$stmtCheck) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmtCheck->bind_param("i", $id);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result();
    if ($resCheck->num_rows == 0) {
        send_response(1, 'Rekap absensi dengan ID ini tidak ditemukan.');
    }
    $stmtCheck->close();

    $stmt = $conn->prepare("UPDATE rekap_absensi
                            SET id_anggota=?, bulan=?, tahun=?, total_hadir=?, total_izin=?, total_cuti=?, total_tanpa_keterangan=?, total_sakit=?
                            WHERE id=?");
    if (!$stmt) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("iiiiiiiii", $id_anggota, $bulan, $tahun, $hadir, $izin, $cuti, $tk, $sakit, $id);
    if ($stmt->execute()) {
        $user_id = $_SESSION['nip'] ?? '';
        $details_log = "Mengedit rekap absensi ID $id";
        add_audit_log($conn, $user_id, 'EditRekap', $details_log);
        send_response(0, 'Data rekap absensi berhasil diupdate.');
    } else {
        send_response(1, 'Gagal update rekap: ' . $stmt->error);
    }
    $stmt->close();
    exit();
}

function DeleteRekap($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        send_response(1, 'ID rekap_absensi tidak valid.');
    }

    // Pastikan rekap dengan ID tersebut ada
    $stmtCheck = $conn->prepare("SELECT id FROM rekap_absensi WHERE id=? LIMIT 1");
    if (!$stmtCheck) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmtCheck->bind_param("i", $id);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result();
    if ($resCheck->num_rows == 0) {
        send_response(1, 'Rekap absensi dengan ID ini tidak ditemukan.');
    }
    $stmtCheck->close();

    $stmt = $conn->prepare("DELETE FROM rekap_absensi WHERE id=? LIMIT 1");
    if (!$stmt) {
        send_response(1, 'Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $user_id = $_SESSION['nip'] ?? '';
        $details_log = "Menghapus rekap absensi ID $id";
        add_audit_log($conn, $user_id, 'DeleteRekap', $details_log);
        send_response(0, 'Data rekap absensi berhasil dihapus.');
    } else {
        send_response(1, 'Gagal hapus rekap: ' . $stmt->error);
    }
    $stmt->close();
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Rekap Absensi - Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/css/sb-admin-2.min.css">
    <!-- DataTables CSS (Bootstrap 5) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.1.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        /* Penyesuaian tampilan tabel */
        #rekapTable.table-sm th,
        #rekapTable.table-sm td {
            font-size: 13px;
            padding: 5px 10px;
            vertical-align: middle;
            white-space: nowrap;
        }
        #rekapTable.table-sm thead th {
            background-color: #343a40;
            color: #fff;
        }
        tbody tr:nth-child(odd) {
            background-color: #f9f9f9;
        }
        tbody tr:nth-child(even) {
            background-color: #ffffff;
        }
        tbody tr:hover {
            background-color: #e2e6ea;
        }
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
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
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <?php include __DIR__ . '/../../navbar.php'; ?>
                <!-- Breadcrumb -->
                <?php include __DIR__ . '/../../breadcrumb.php'; ?>

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-chart-bar"></i> Manajemen Rekap Absensi</h1>

                    <!-- Tabel Rekap Absensi -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-white"><i class="fas fa-clipboard-list"></i> Daftar Rekap Absensi</h6>
                            <button class="btn btn-success btn-sm" id="btnAddRekap">
                                <i class="bi bi-plus-circle"></i> Tambah Rekap
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="rekapTable" class="table table-sm table-bordered table-striped display nowrap" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>ID Anggota</th>
                                            <th>Bulan</th>
                                            <th>Tahun</th>
                                            <th>Hadir</th>
                                            <th>Izin</th>
                                            <th>Cuti</th>
                                            <th>Tanpa Ket.</th>
                                            <th>Sakit</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Page Content -->
            </div>
            <!-- End Main Content -->

            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="text-center my-auto">
                        <span>&copy; <?php echo date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Modal Add/Edit Rekap Absensi -->
    <div class="modal fade" id="rekapModal" tabindex="-1" aria-labelledby="rekapModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form id="rekapForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="rekapModalLabel">Tambah Rekap Absensi</h5>
                        <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="rekap_id" name="id">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                        <div class="form-group">
                            <label for="id_anggota">ID Anggota</label>
                            <input type="number" class="form-control" id="id_anggota" name="id_anggota" min="1" required>
                        </div>
                        <div class="form-group">
                            <label for="bulan">Bulan</label>
                            <select class="form-control" id="bulan" name="bulan" required>
                                <option value="">Pilih Bulan</option>
                                <?php
                                // Fungsi sederhana untuk mengonversi integer ke nama bulan
                                function bulanIntToNameSimple($bulan) {
                                    $namaBulan = [
                                        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                                    ];
                                    return isset($namaBulan[$bulan]) ? $namaBulan[$bulan] : 'Invalid';
                                }
                                for ($m = 1; $m <= 12; $m++) {
                                    echo "<option value=\"$m\">" . bulanIntToNameSimple($m) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="tahun">Tahun</label>
                            <input type="number" class="form-control" id="tahun" name="tahun" min="2000" max="2100" required>
                        </div>
                        <div class="form-group">
                            <label for="total_hadir">Total Hadir</label>
                            <input type="number" class="form-control" id="total_hadir" name="total_hadir" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="total_izin">Total Izin</label>
                            <input type="number" class="form-control" id="total_izin" name="total_izin" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="total_cuti">Total Cuti</label>
                            <input type="number" class="form-control" id="total_cuti" name="total_cuti" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="total_tanpa_keterangan">Total Tanpa Keterangan</label>
                            <input type="number" class="form-control" id="total_tanpa_keterangan" name="total_tanpa_keterangan" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="total_sakit">Total Sakit</label>
                            <input type="number" class="form-control" id="total_sakit" name="total_sakit" min="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-primary" id="saveRekap">Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!-- End Modal Add/Edit -->

    <!-- Modal Delete Rekap Absensi -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form id="deleteForm">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Hapus Rekap Absensi</h5>
              <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" id="delete_id" name="id">
              <p>Apakah Anda yakin ingin menghapus rekap absensi ini?</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tidak</button>
              <button type="submit" class="btn btn-danger">Ya, Hapus</button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <!-- End Modal Delete -->

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/autonumeric@4.6.0/dist/autoNumeric.min.js"></script>
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

        // Inisialisasi DataTable untuk Rekap Absensi
        var rekapTable = $('#rekapTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: 'rekap_absensi.php?ajax=1',
                type: 'POST',
                data: function(d){
                    d.case = 'LoadingRekap';
                    // Jika ingin menambahkan parameter filter tambahan, bisa disisipkan di sini
                },
                beforeSend: function(){
                    $('#loadingSpinner').show();
                },
                complete: function(){
                    $('#loadingSpinner').hide();
                },
                error: function(){
                    showToast('Terjadi kesalahan load data rekap.', 'error');
                }
            },
            columns: [
                { data:'id' },
                { data:'id_anggota' },
                { data:'bulan' },
                { data:'tahun' },
                { data:'total_hadir' },
                { data:'total_izin' },
                { data:'total_cuti' },
                { data:'total_tanpa_keterangan' },
                { data:'total_sakit' },
                { data:'aksi', orderable:false, searchable:false }
            ],
            order: [[0,'desc']],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.11.3/i18n/Indonesian.json"
            }
        });

        // EVENT: Tombol Tambah Rekap
        $('#btnAddRekap').on('click', function(){
            $('#rekapForm')[0].reset();
            $('#rekap_id').val('');
            $('#rekapModalLabel').text('Tambah Rekap Absensi');
            var rekapModal = new bootstrap.Modal(document.getElementById('rekapModal'));
            rekapModal.show();
        });

        // EVENT: Submit Form Add/Edit Rekap
        $('#rekapForm').on('submit', function(e){
            e.preventDefault();
            var caseType = $('#rekap_id').val() ? 'EditRekap' : 'AddRekap';
            $.ajax({
                url: 'rekap_absensi.php?ajax=1',
                type: 'POST',
                data: { 
                    case: caseType, 
                    id: $('#rekap_id').val(),
                    id_anggota: $('#id_anggota').val(),
                    bulan: $('#bulan').val(),
                    tahun: $('#tahun').val(),
                    total_hadir: $('#total_hadir').val(),
                    total_izin: $('#total_izin').val(),
                    total_cuti: $('#total_cuti').val(),
                    total_tanpa_keterangan: $('#total_tanpa_keterangan').val(),
                    total_sakit: $('#total_sakit').val(),
                    csrf_token: '<?= htmlspecialchars($csrf_token); ?>'
                },
                dataType: 'json',
                success: function(resp){
                    if(resp.code === 0){
                        showToast(resp.result, 'success');
                        var rekapModalEl = document.getElementById('rekapModal');
                        var rekapModal = bootstrap.Modal.getInstance(rekapModalEl);
                        rekapModal.hide();
                        rekapTable.ajax.reload(null, false);
                    } else {
                        showToast(resp.result, 'error');
                    }
                },
                error: function(){
                    showToast('Error AJAX.', 'error');
                }
            });
        });

        // EVENT: Edit Rekap
        $('#rekapTable tbody').on('click', '.btnEdit', function(){
            var id = $(this).data('id');
            $.ajax({
                url: 'rekap_absensi.php?ajax=1',
                type: 'POST',
                data: { 
                    case: 'LoadingRekap', 
                    start: 0,
                    length: 1,
                    search: { value: id },
                    csrf_token: '<?= htmlspecialchars($csrf_token); ?>'
                },
                dataType: 'json',
                success: function(resp){
                    if(resp.code === 0 && resp.data.length > 0){
                        var data = resp.data.find(item => item.id == id);
                        if(data){
                            $('#rekap_id').val(data.id);
                            $('#id_anggota').val(data.id_anggota);
                            // Karena kolom bulan dikonversi ke teks, kita perlu mengonversinya kembali ke integer
                            // Fungsi konversi sederhana:
                            function getBulanInt(bulanName) {
                                var map = {
                                    'Januari': 1, 'Februari': 2, 'Maret': 3, 'April': 4,
                                    'Mei': 5, 'Juni': 6, 'Juli': 7, 'Agustus': 8,
                                    'September': 9, 'Oktober': 10, 'November': 11, 'Desember': 12
                                };
                                return map[bulanName] || '';
                            }
                            $('#bulan').val(getBulanInt(data.bulan));
                            $('#tahun').val(data.tahun);
                            $('#total_hadir').val(data.total_hadir);
                            $('#total_izin').val(data.total_izin);
                            $('#total_cuti').val(data.total_cuti);
                            $('#total_tanpa_keterangan').val(data.total_tanpa_keterangan);
                            $('#total_sakit').val(data.total_sakit);
                            $('#rekapModalLabel').text('Edit Rekap Absensi');
                            var rekapModal = new bootstrap.Modal(document.getElementById('rekapModal'));
                            rekapModal.show();
                        } else {
                            showToast('Data tidak ditemukan.', 'error');
                        }
                    } else {
                        showToast('Data tidak ditemukan.', 'error');
                    }
                },
                error: function(){
                    showToast('Error AJAX.', 'error');
                }
            });
        });

        // EVENT: Hapus Rekap
        $('#rekapTable tbody').on('click', '.btnDelete', function(){
            var id = $(this).data('id');
            $('#delete_id').val(id);
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        });

        // EVENT: Submit Form Hapus Rekap
        $('#deleteForm').on('submit', function(e){
            e.preventDefault();
            var id = $('#delete_id').val();
            if(!id){
                showToast('ID tidak valid.', 'error');
                return;
            }
            $.ajax({
                url: 'rekap_absensi.php?ajax=1',
                type: 'POST',
                data: { 
                    case: 'DeleteRekap', 
                    id: id,
                    csrf_token: '<?= htmlspecialchars($csrf_token); ?>'
                },
                dataType: 'json',
                success: function(resp){
                    if(resp.code === 0){
                        showToast(resp.result, 'success');
                        var deleteModalEl = document.getElementById('deleteModal');
                        var deleteModal = bootstrap.Modal.getInstance(deleteModalEl);
                        deleteModal.hide();
                        rekapTable.ajax.reload(null, false);
                    } else {
                        showToast(resp.result, 'error');
                    }
                },
                error: function(){
                    showToast('Error AJAX delete.', 'error');
                }
            });
        });
    });
    </script>
</body>
</html>
