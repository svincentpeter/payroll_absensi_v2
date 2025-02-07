<?php
// File: /payroll_absensi_v2/kelola/kelola_user.php

// =========================
// 1. Pengaturan Keamanan
// =========================

// Atur session cookie parameters sebelum session_start()
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => true,      // Hanya lewat HTTPS
    'httponly' => true,
    'samesite' => 'Strict'
]);

// Mulai session dengan aman
session_start();

// Sertakan file helper (misalnya: fungsi sanitasi, CSRF, audit log, dsb)
require_once __DIR__ . '/../../helpers.php';
init_error_handling();
generate_csrf_token();

// Buat nonce untuk CSP dan simpan di session
$nonce = base64_encode(random_bytes(16));
$_SESSION['csp_nonce'] = $nonce;

// Paksa HTTPS jika belum menggunakan HTTPS
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirect);
    exit();
}

// HSTS header
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

// Terapkan Content Security Policy dengan nonce
header("Content-Security-Policy: default-src 'self'; 
    script-src 'self' https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.datatables.net https://cdn.jsdelivr.net 'nonce-$nonce'; 
    style-src 'self' https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com https://cdn.datatables.net https://cdn.jsdelivr.net 'nonce-$nonce'; 
    img-src 'self'; 
    font-src 'self' https://cdnjs.cloudflare.com; 
    connect-src 'self'");

// Fungsi otorisasi: hanya izinkan role sdm dan superadmin
function authorize($allowed_roles = ['sdm', 'superadmin']) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        send_response(403, 'Akses ditolak.');
    }
}
authorize();

// Koneksi ke database
require_once __DIR__ . '/../../koneksi.php';

// Nonaktifkan output buffering jika ada
if (ob_get_length()) ob_end_clean();


// =========================
// 2. Sanitasi Input & CSRF (tersedia di helpers.php)
// =========================


// =========================
// 3. Menangani Permintaan AJAX
// =========================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verifikasi CSRF
        $csrf_token = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';
        verify_csrf_token($csrf_token);

        // Role check: hanya superadmin & sdm yang boleh
        authorize(['sdm', 'superadmin']);

        // Ambil parameter 'case'
        $case = isset($_POST['case']) ? bersihkan_input($_POST['case']) : '';

        switch ($case) {
            case 'LoadingUsers':
                LoadingUsers($conn);
                break;
            case 'CreateUser':
                CreateUser($conn);
                break;
            case 'GetUserDetail':
                GetUserDetail($conn);
                break;
            case 'UpdateUser':
                UpdateUser($conn);
                break;
            case 'DeleteUser':
                DeleteUser($conn);
                break;
            case 'AddAuditLog':
                $action = isset($_POST['action_name']) ? bersihkan_input($_POST['action_name']) : '';
                $details = isset($_POST['details']) ? bersihkan_input($_POST['details']) : '';
                if (!empty($action) && !empty($details)) {
                    $logged = add_audit_log($conn, $_SESSION['user_id'], $action, $details);
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


// =========================
// 4. Fungsi CRUD dengan Audit Logs untuk User
// =========================

function LoadingUsers($conn) {
    // Parameter DataTables
    $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = isset($_POST['search']['value']) ? bersihkan_input($_POST['search']['value']) : '';
    
    // Ambil parameter filter role (misalnya: "superadmin", "keuangan", dll)
    $filterRole = isset($_POST['role']) ? bersihkan_input($_POST['role']) : '';

    // Hitung total record
    $sqlTotal = "SELECT COUNT(*) as total FROM users";
    $resultTotal = mysqli_query($conn, $sqlTotal);
    if (!$resultTotal) {
        send_response(1, 'Query Error: ' . mysqli_error($conn));
    }
    $rowTotal = mysqli_fetch_assoc($resultTotal);
    $recordsTotal = $rowTotal['total'];

    // Membangun query filter
    $sqlFilter = "SELECT * FROM users WHERE 1=1";
    $sqlFilterCount = "SELECT COUNT(*) as total FROM users WHERE 1=1";
    $params = [];
    $types = "";

    // Filter berdasarkan pencarian (search)
    if (!empty($search)) {
        $sqlFilter      .= " AND (username LIKE ? OR role LIKE ?)";
        $sqlFilterCount .= " AND (username LIKE ? OR role LIKE ?)";
        $searchParam = "%" . $search . "%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "ss";
    }

    // Filter berdasarkan role
    if (!empty($filterRole)) {
        $sqlFilter      .= " AND role = ?";
        $sqlFilterCount .= " AND role = ?";
        $params[] = $filterRole;
        $types .= "s";
    }

    // Hitung filtered records
    $stmtFiltered = $conn->prepare($sqlFilterCount);
    if ($stmtFiltered === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    if (!empty($params)) {
        $stmtFiltered->bind_param($types, ...$params);
    }
    $stmtFiltered->execute();
    $resultFiltered = $stmtFiltered->get_result();
    if (!$resultFiltered) {
        send_response(1, 'Query Error: ' . $stmtFiltered->error);
    }
    $rowFiltered = $resultFiltered->fetch_assoc();
    $recordsFiltered = $rowFiltered['total'];
    $stmtFiltered->close();

    // Order by (default: id_user DESC)
    $orderBy = " ORDER BY id_user DESC";
    if (isset($_POST['order'], $_POST['columns'])) {
        $columnIndex = intval($_POST['order'][0]['column']);
        $allowedColumns = ['id_user', 'username', 'role', 'created_at'];
        if (isset($_POST['columns'][$columnIndex]['data']) && in_array($_POST['columns'][$columnIndex]['data'], $allowedColumns)) {
            $colName = $_POST['columns'][$columnIndex]['data'];
            $colSortOrder = ($_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
            $orderBy = " ORDER BY $colName $colSortOrder";
        }
    }

    // Limit
    $limit = " LIMIT ?, ?";
    $params[] = $start;
    $params[] = $length;
    $types .= "ii";
    $sqlFilter .= $orderBy . $limit;

    $stmtData = $conn->prepare($sqlFilter);
    if ($stmtData === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    if (!empty($params)) {
        $stmtData->bind_param($types, ...$params);
    }
    $stmtData->execute();
    $dataQuery = $stmtData->get_result();
    if (!$dataQuery) {
        send_response(1, 'Query Error: ' . $stmtData->error);
    }

    $data = [];
    $no = $start + 1;
    while ($row = $dataQuery->fetch_assoc()) {
        // Tampilkan badge untuk role dengan ikon
        $roleBadge = '';
        if ($row['role'] === 'superadmin') {
            $roleBadge = '<span class="badge badge-primary"><i class="fas fa-user-shield"></i> ' . htmlspecialchars(ucfirst($row['role'])) . '</span>';
        } elseif ($row['role'] === 'keuangan') {
            $roleBadge = '<span class="badge badge-success"><i class="fas fa-wallet"></i> ' . htmlspecialchars(ucfirst($row['role'])) . '</span>';
        } else {
            $roleBadge = '<span class="badge badge-secondary"><i class="fas fa-user"></i> ' . htmlspecialchars(ucfirst($row['role'])) . '</span>';
        }
        // Tombol aksi: dropdown dengan ikon tiga titik vertikal
        $aksi = '
<div class="dropdown">
  <button class="btn" type="button" id="dropdownMenuButton_' . htmlspecialchars($row['id_user']) . '" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <i class="bi bi-three-dots-vertical"></i>
  </button>
  <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_' . htmlspecialchars($row['id_user']) . '">
    <a class="dropdown-item btn-edit" href="javascript:void(0)" data-id_user="' . htmlspecialchars($row['id_user']) . '" title="Edit">
        <i class="fas fa-edit"></i> Edit
    </a>
    <a class="dropdown-item btn-delete" href="javascript:void(0)" data-id_user="' . htmlspecialchars($row['id_user']) . '" title="Hapus">
        <i class="fas fa-trash-alt"></i> Hapus
    </a>
  </div>
</div>';
        $data[] = [
            "no"         => $no++,
            "id_user"    => $row['id_user'],
            "username"   => bersihkan_input($row['username']),
            "role"       => $roleBadge,
            "created_at" => bersihkan_input($row['created_at']),
            "aksi"       => $aksi
        ];
    }
    $stmtData->close();

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $recordsTotal,
        "recordsFiltered" => $recordsFiltered,
        "data" => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}


function CreateUser($conn) {
    $username = isset($_POST['username']) ? bersihkan_input($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $role     = isset($_POST['role']) ? bersihkan_input($_POST['role']) : '';

    if (empty($username) || empty($password) || empty($role)) {
        send_response(2, 'Semua field wajib diisi.');
    }
    // Cek duplikasi username
    $stmt = $conn->prepare("SELECT id_user FROM users WHERE username = ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        send_response(3, 'Username sudah terdaftar.');
    }
    $stmt->close();

    // Hash password (gunakan MD5 di sini, namun sebaiknya gunakan password_hash)
    $passwordHash = md5($password);

    $stmt = $conn->prepare("INSERT INTO users (username, password, role, created_at) VALUES (?, ?, ?, NOW())");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("sss", $username, $passwordHash, $role);
    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        $details_log = "Menambahkan User: Username='$username', Role='$role'.";
        if (!add_audit_log($conn, $_SESSION['user_id'], 'CreateUser', $details_log)) {
            log_error("Gagal mencatat audit log untuk CreateUser ID " . $user_id . ".");
        }
        send_response(0, 'User berhasil ditambahkan.');
    } else {
        send_response(1, 'Gagal menambah user: ' . $stmt->error);
    }
    $stmt->close();
    exit();
}

function GetUserDetail($conn) {
    $id_user = isset($_POST['id_user']) ? intval($_POST['id_user']) : 0;
    if ($id_user <= 0) {
        send_response(1, 'ID User tidak valid.');
    }
    $stmt = $conn->prepare("SELECT * FROM users WHERE id_user = ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id_user);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $stmt->close();
        $details_log = "Melihat detail User ID $id_user: Username='" . $user['username'] . "', Role='" . $user['role'] . "'.";
        if (!add_audit_log($conn, $_SESSION['user_id'], 'ViewUserDetail', $details_log)) {
            log_error("Gagal mencatat audit log untuk ViewUserDetail ID $id_user.");
        }
        send_response(0, [
            'id_user'  => $user['id_user'],
            'username' => $user['username'],
            'role'     => $user['role']
        ]);
    } else {
        send_response(2, 'User tidak ditemukan.');
    }
    $stmt->close();
    exit();
}

function UpdateUser($conn) {
    $id_user  = isset($_POST['id_user']) ? intval($_POST['id_user']) : 0;
    $username = isset($_POST['username']) ? bersihkan_input($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $role     = isset($_POST['role']) ? bersihkan_input($_POST['role']) : '';

    if ($id_user <= 0 || empty($username) || empty($role)) {
        send_response(3, 'Field wajib diisi dan ID User harus valid.');
    }
    // Cek duplikasi username untuk user lain
    $stmt = $conn->prepare("SELECT id_user FROM users WHERE username = ? AND id_user != ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("si", $username, $id_user);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        send_response(4, 'Username sudah terdaftar.');
    }
    $stmt->close();

    if (!empty($password)) {
        $passwordHash = md5($password);
        $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id_user = ?");
        if ($stmt === false) {
            send_response(1, 'Query Error: ' . $conn->error);
        }
        $stmt->bind_param("sssi", $username, $passwordHash, $role, $id_user);
    } else {
        $stmt = $conn->prepare("UPDATE users SET username = ?, role = ? WHERE id_user = ?");
        if ($stmt === false) {
            send_response(1, 'Query Error: ' . $conn->error);
        }
        $stmt->bind_param("ssi", $username, $role, $id_user);
    }
    if ($stmt->execute()) {
        $details_log = "Mengupdate User ID $id_user: Username='$username', Role='$role'.";
        if (!add_audit_log($conn, $_SESSION['user_id'], 'UpdateUser', $details_log)) {
            log_error("Gagal mencatat audit log untuk UpdateUser ID $id_user.");
        }
        send_response(0, 'User berhasil diupdate.');
    } else {
        send_response(1, 'Gagal mengupdate user: ' . $stmt->error);
    }
    $stmt->close();
    exit();
}

function DeleteUser($conn) {
    $id_user = isset($_POST['id_user']) ? intval($_POST['id_user']) : 0;
    if ($id_user <= 0) {
        send_response(3, 'ID User tidak valid.');
    }
    $stmt = $conn->prepare("DELETE FROM users WHERE id_user = ?");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id_user);
    if (!$stmt->execute()) {
        send_response(1, 'Gagal menghapus user: ' . $stmt->error);
    }
    $details_log = "Menghapus User ID $id_user.";
    if (!add_audit_log($conn, $_SESSION['user_id'], 'DeleteUser', $details_log)) {
        log_error("Gagal mencatat audit log untuk DeleteUser ID $id_user.");
    }
    send_response(0, 'User berhasil dihapus.');
    $stmt->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Anggota - Manajemen User</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap CSS (versi 5.x) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" nonce="<?php echo $nonce; ?>">
    <!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/css/sb-admin-2.min.css" nonce="<?php echo $nonce; ?>">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.7.1/css/buttons.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" nonce="<?php echo $nonce; ?>">
    <!-- Bootstrap Notify CSS -->
    <link rel="stylesheet" href="/payroll_absensi_v2/plugins/bootstrap-notify/bootstrap-notify.min.css" nonce="<?php echo $nonce; ?>">
    <style nonce="<?php echo $nonce; ?>">
        .no-column { width: 60px; text-align: center; }
        #userTable th, #userTable td {
            font-size: 13px;
            vertical-align: middle;
            white-space: nowrap;
        }
        thead th {
            background-color: #343a40;
            color: white;
            text-align: left;
        }
        .table-responsive { overflow-x: auto; }
        #loadingSpinner {
            display: none;
            position: fixed;
            z-index: 9999;
            height: 100px;
            width: 100px;
            margin: auto;
            top: 0; left: 0; bottom: 0; right: 0;
        }

        /* Custom Styles untuk Kartu */
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
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
                <!-- End of Topbar -->
<!-- Breadcrumb -->
<?php include __DIR__ . '/../../breadcrumb.php'; ?>

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">
                        <i class="fas fa-users"></i> Manajemen User (Kelola Anggota)
                    </h1>

                    <!-- Notifikasi -->
                    <div id="alert-placeholder"></div>

                    <!-- Filter User -->
                    <div class="card mb-4" style="background-color: #f8f9fa; border-radius: 0.5rem;">
                        <div class="card-header">
                            <strong>Filter User</strong>
                        </div>
                        <div class="card-body">
                            <form id="filterForm" class="form-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <div class="form-group mb-2 me-3">
                                    <label for="filterRole" class="me-2"><strong>Role:</strong></label>
                                    <select class="form-control" id="filterRole" name="role" style="width:200px">
                                        <option value="">Semua Role</option>
                                        <option value="superadmin">Superadmin</option>
                                        <option value="keuangan">Keuangan</option>
                                        <option value="sdm">SDM</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                                <div class="form-group mb-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-primary me-2" id="btnApplyFilter">
                                        <i class="fas fa-filter"></i> Filter
                                    </button>
                                    <button type="button" class="btn btn-secondary me-2" id="btnResetFilter">
                                        <i class="fas fa-undo"></i> Reset
                                    </button>
                                    <button type="button" class="btn btn-success" id="btnExportData">
                                        <i class="fas fa-file-export"></i> Export
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tabel Data User -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-clipboard-list"></i> Daftar User</h6>
                            <button type="button" class="btn btn-primary btn-success" data-bs-toggle="modal" data-bs-target="#createUserModal">
                                <i class="fas fa-plus"></i> Tambah User
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="userTable" class="table table-sm table-bordered table-striped display nowrap" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Role</th>
                                            <th>Created At</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!-- End Tabel Data User -->

                    <!-- Loading Spinner -->
                    <div id="loadingSpinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
                <!-- End Container Fluid -->
            </div>
            <!-- End Content -->

            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?php echo date("Y"); ?> Sistem Manajemen User | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- =======================
         MODAL: CREATE USER
         ======================= -->
    <div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form id="create-user-form" class="needs-validation" novalidate>
          <input type="hidden" name="case" value="CreateUser">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="createUserModalLabel">Tambah User</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                  <label for="username">Username <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="username" name="username" required>
                  <div class="invalid-feedback">Username wajib diisi.</div>
              </div>
              <div class="form-group">
                  <label for="password">Password <span class="text-danger">*</span></label>
                  <input type="password" class="form-control" id="password" name="password" required>
                  <div class="invalid-feedback">Password wajib diisi.</div>
              </div>
              <div class="form-group">
                  <label for="role">Role <span class="text-danger">*</span></label>
                  <select class="form-control" id="role" name="role" required>
                      <option value="">--- Pilih Role ---</option>
                      <option value="superadmin">Superadmin</option>
                      <option value="keuangan">Keuangan</option>
                      <option value="sdm">SDM</option>
                      <option value="admin">Admin</option>
                  </select>
                  <div class="invalid-feedback">Pilih role user.</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
              <button type="submit" class="btn btn-primary">
                  <i class="fas fa-save"></i> Simpan
                  <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- =======================
         MODAL: UPDATE USER
         ======================= -->
    <div class="modal fade" id="updateUserModal" tabindex="-1" aria-labelledby="updateUserModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form id="update-user-form" class="needs-validation" novalidate>
          <input type="hidden" name="case" value="UpdateUser">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <input type="hidden" name="id_user" id="upd_id_user">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="updateUserModalLabel">Edit User</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                  <label for="upd_username">Username <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="upd_username" name="username" required>
                  <div class="invalid-feedback">Username wajib diisi.</div>
              </div>
              <div class="form-group">
                  <label for="upd_password">Password (Kosongkan jika tidak diubah)</label>
                  <input type="password" class="form-control" id="upd_password" name="password">
              </div>
              <div class="form-group">
                  <label for="upd_role">Role <span class="text-danger">*</span></label>
                  <select class="form-control" id="upd_role" name="role" required>
                      <option value="">--- Pilih Role ---</option>
                      <option value="superadmin">Superadmin</option>
                      <option value="keuangan">Keuangan</option>
                      <option value="sdm">SDM</option>
                      <option value="admin">Admin</option>
                  </select>
                  <div class="invalid-feedback">Pilih role user.</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
              <button type="submit" class="btn btn-primary">
                  <i class="fas fa-save"></i> Update
                  <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- =======================
         MODAL: DELETE USER
         ======================= -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form id="delete-user-form" class="needs-validation" novalidate>
          <input type="hidden" name="case" value="DeleteUser">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <input type="hidden" name="id_user" id="del_id_user">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="deleteUserModalLabel">Hapus User</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <p>Anda yakin ingin menghapus user berikut?</p>
              <p><strong id="delNama"></strong></p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tidak</button>
              <button type="submit" class="btn btn-danger">
                  <i class="fas fa-trash"></i> Ya, Hapus
                  <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Loading Spinner -->
    <div id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>

    <!-- =======================
         JS DEPENDENCIES
         ======================= -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/dataTables.buttons.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.html5.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.print.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.colVis.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/js/sb-admin-2.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script nonce="<?php echo $nonce; ?>">
    $(document).ready(function() {
        // Inisialisasi tooltip
        $('[data-toggle="tooltip"]').tooltip();

        // SweetAlert2 Toast
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
        var csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

        // Inisialisasi DataTable untuk User (tanpa scroll horizontal)
        var userTable = $('#userTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "kelola_user.php?ajax=1",
                type: "POST",
                data: function(d) {
                    d.case = 'LoadingUsers';
                    d.csrf_token = csrfToken;
                    d.role = $('#filterRole').val();
                },
                beforeSend: function(){
                    $('#loadingSpinner').show();
                },
                complete: function(){
                    $('#loadingSpinner').hide();
                },
                error: function(){
                    showToast('Terjadi kesalahan saat memuat data user.', 'error');
                }
            },
            columns: [
                { data: "no", orderable: false },
                { data: "id_user" },
                { data: "username" },
                { data: "role" },
                { data: "created_at" },
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

        // Filter Apply & Reset
        $('#btnApplyFilter').on('click', function(){
            $.ajax({
                url: "kelola_user.php?ajax=1",
                type: "POST",
                data: {
                    case: 'AddAuditLog',
                    csrf_token: csrfToken,
                    action_name: 'ApplyFilter',
                    details: 'Pengguna menerapkan filter Role: ' + ($('#filterRole').val() || 'Semua')
                },
                success: function(response){
                    if(response.code === 0){
                        showToast('Filter berhasil diterapkan.', 'success');
                    }
                },
                error: function(){
                    showToast('Terjadi kesalahan saat mencatat audit log.', 'warning');
                }
            });
            userTable.ajax.reload();
        });

        $('#btnResetFilter').on('click', function(){
            $('#filterForm')[0].reset();
            $.ajax({
                url: "kelola_user.php?ajax=1",
                type: "POST",
                data: {
                    case: 'AddAuditLog',
                    csrf_token: csrfToken,
                    action_name: 'ResetFilter',
                    details: 'Pengguna mereset filter user.'
                },
                success: function(response){
                    if(response.code === 0){
                        showToast('Filter berhasil direset.', 'success');
                    }
                },
                error: function(){
                    showToast('Terjadi kesalahan saat mencatat audit log.', 'warning');
                }
            });
            userTable.ajax.reload();
        });

        $('#btnExportData').on('click', function(){
            userTable.button('.buttons-excel').trigger();
            $.ajax({
                url: "kelola_user.php?ajax=1",
                type: "POST",
                data: {
                    case: 'AddAuditLog',
                    csrf_token: csrfToken,
                    action_name: 'ExportData',
                    details: 'Pengguna mengekspor data user.'
                },
                success: function(response){
                    if(response.code === 0){
                        showToast('Data berhasil diekspor.', 'success');
                    }
                },
                error: function(){
                    showToast('Terjadi kesalahan saat mencatat audit log.', 'warning');
                }
            });
        });

        // Proses Create User via AJAX
        $('#create-user-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            var formData = form.serialize();
            $.ajax({
                url: "kelola_user.php?ajax=1",
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
                    if(response.code == 0) {
                        showToast(response.result, 'success');
                        // Tutup modal Create
                        var createModalEl = document.getElementById('createUserModal');
                        var createModal = bootstrap.Modal.getInstance(createModalEl);
                        if(!createModal){
                            createModal = new bootstrap.Modal(createModalEl);
                        }
                        createModal.hide();
                        userTable.ajax.reload(null, false);
                        form[0].reset();
                        form.removeClass('was-validated');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat menambah user.', 'error');
                }
            });
        });

        // Buka modal Update: ambil detail user via AJAX dan tampilkan modal Update
        $(document).on('click', '.btn-edit', function() {
            var id_user = $(this).data('id_user');
            var form = $('#update-user-form');
            form[0].reset();
            form.removeClass('was-validated');
            $.ajax({
                url: "kelola_user.php?ajax=1",
                type: "POST",
                data: { id_user: id_user, case: 'GetUserDetail', csrf_token: csrfToken },
                dataType: "json",
                beforeSend: function(){
                    $('#loadingSpinner').show();
                },
                success: function(response){
                    $('#loadingSpinner').hide();
                    if(response.code == 0) {
                        $('#upd_id_user').val(response.result.id_user);
                        $('#upd_username').val(response.result.username);
                        $('#upd_role').val(response.result.role);
                        // Tampilkan modal Update dengan Bootstrap 5 API
                        var updateModalEl = document.getElementById('updateUserModal');
                        var updateModal = bootstrap.Modal.getInstance(updateModalEl);
                        if(!updateModal){
                            updateModal = new bootstrap.Modal(updateModalEl);
                        }
                        updateModal.show();
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    $('#loadingSpinner').hide();
                    showToast('Terjadi kesalahan saat mengambil detail user.', 'error');
                }
            });
        });

        // Update User via AJAX
        $('#update-user-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            var formData = form.serialize();
            $.ajax({
                url: "kelola_user.php?ajax=1",
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
                    if(response.code == 0) {
                        showToast(response.result, 'success');
                        var updateModalEl = document.getElementById('updateUserModal');
                        var updateModal = bootstrap.Modal.getInstance(updateModalEl);
                        if(updateModal){
                            updateModal.hide();
                        }
                        userTable.ajax.reload(null, false);
                        form[0].reset();
                        form.removeClass('was-validated');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat mengupdate user.', 'error');
                }
            });
        });

        // Buka modal Delete: isi data dan tampilkan modal Delete
        $(document).on('click', '.btn-delete', function() {
            var id_user = $(this).data('id_user');
            $('#del_id_user').val(id_user);
            // Dapatkan nama dari kolom ke-3 (index 2) pada baris tersebut
            $('#delNama').text($(this).closest('tr').find('td:eq(2)').text());
            var deleteModalEl = document.getElementById('deleteUserModal');
            var deleteModal = bootstrap.Modal.getInstance(deleteModalEl);
            if(!deleteModal){
                deleteModal = new bootstrap.Modal(deleteModalEl);
            }
            deleteModal.show();
        });

        // Proses Delete User via AJAX
        $('#delete-user-form').on('submit', function(e){
            e.preventDefault();
            var id_user = $('#del_id_user').val();
            if (!id_user) {
                showToast('ID User tidak ditemukan.', 'error');
                return;
            }
            $.ajax({
                url: "kelola_user.php?ajax=1",
                type: "POST",
                data: { id_user: id_user, case: 'DeleteUser', csrf_token: csrfToken },
                dataType: "json",
                beforeSend: function(){
                    $('#delete-user-form').find('button[type="submit"]').prop('disabled', true);
                    $('#delete-user-form').find('.spinner-border').removeClass('d-none');
                },
                success: function(response){
                    $('#delete-user-form').find('button[type="submit"]').prop('disabled', false);
                    $('#delete-user-form').find('.spinner-border').addClass('d-none');
                    if(response.code == 0) {
                        showToast(response.result, 'success');
                        var deleteModalEl = document.getElementById('deleteUserModal');
                        var deleteModal = bootstrap.Modal.getInstance(deleteModalEl);
                        if(deleteModal){
                            deleteModal.hide();
                        }
                        userTable.ajax.reload(null, false);
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(xhr, status, error){
                    $('#delete-user-form').find('button[type="submit"]').prop('disabled', false);
                    $('#delete-user-form').find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat menghapus user: ' + error, 'error');
                }
            });
        });

        // Reset form saat modal ditutup
        $('#createUserModal, #updateUserModal, #deleteUserModal').on('hidden.bs.modal', function () {
            $(this).find('form')[0].reset();
        });

        // Fade out alerts setelah 3 detik
        setTimeout(function() {
            $(".alert").fadeTo(500, 0).slideUp(500, function() { $(this).remove(); });
        }, 3000);
    });
    </script>
</body>
</html>
