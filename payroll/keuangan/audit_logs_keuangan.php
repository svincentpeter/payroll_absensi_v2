<?php
// File: /payroll_absensi_v2/payroll/keuangan/audit_logs_keuangan.php

// =========================
// 1. Pengaturan Keamanan & Session
// =========================

// Set parameter cookie session
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => true,      // Hanya lewat HTTPS
    'httponly' => true,      // Tidak dapat diakses via JavaScript
    'samesite' => 'Strict'
]);

require_once __DIR__ . '/../../helpers.php';
start_session_safe();
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

// Proteksi CSRF
generate_csrf_token();

// Role Checking: hanya role "keuangan" yang boleh akses halaman ini
function authorize($allowed_roles = ['keuangan', 'superadmin']) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: /payroll_absensi_v2/login.php");
        exit();
    }
}
authorize();

// Koneksi ke database
require_once __DIR__ . '/../../koneksi.php';
if (ob_get_length()) {
    ob_end_clean();
}

// Terapkan Content-Security-Policy (CSP) dengan nonce
header("Content-Security-Policy: default-src 'self'; 
    script-src 'self' https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.datatables.net https://cdn.jsdelivr.net 'nonce-$nonce'; 
    style-src 'self' https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com https://cdn.datatables.net https://cdn.jsdelivr.net 'nonce-$nonce'; 
    img-src 'self'; 
    font-src 'self' https://cdnjs.cloudflare.com; 
    connect-src 'self'");

// =========================
// 2. Fungsi Pendukung (Ikon, Warna & Badge)
// =========================

/**
 * Mengembalikan HTML ikon sesuai jenis aktivitas (action).
 */
function getActivityIcon($action) {
    $actionLower = strtolower($action);
    if (strpos($actionLower, 'login') !== false) {
        return '<i class="fas fa-sign-in-alt"></i>';
    } elseif (strpos($actionLower, 'failed') !== false) {
        return '<i class="fas fa-exclamation-triangle"></i>';
    } elseif (strpos($actionLower, 'edit') !== false) {
        return '<i class="fas fa-edit"></i>';
    } elseif (strpos($actionLower, 'delete') !== false) {
        return '<i class="fas fa-trash-alt"></i>';
    } elseif (strpos($actionLower, 'accessauditlogs') !== false) {
        return '<i class="fas fa-list-alt"></i>';
    }
    return '<i class="fas fa-info-circle"></i>';
}

/**
 * Mengembalikan HTML ikon sesuai role pengguna.
 */
function getRoleIcon($role) {
    $role = strtolower($role);
    switch ($role) {
        case 'superadmin':
            return '<i class="fas fa-user-shield"></i>';
        case 'keuangan':
            return '<i class="fas fa-wallet"></i>';
        case 'sdm':
            return '<i class="fas fa-users-cog"></i>';
        case 'guru':
            return '<i class="fas fa-chalkboard-teacher"></i>';
        case 'karyawan':
            return '<i class="fas fa-user-tie"></i>';
        default:
            return '<i class="fas fa-user"></i>';
    }
}

/**
 * Mengembalikan warna (hex code) sesuai jenis aktivitas.
 */
function getActivityColor($action) {
    $actionLower = strtolower($action);
    if (strpos($actionLower, 'login') !== false) {
        return '#1cc88a'; // hijau
    } elseif (strpos($actionLower, 'failed') !== false) {
        return '#e74a3b'; // merah
    } elseif (strpos($actionLower, 'edit') !== false) {
        return '#f6c23e'; // kuning
    } elseif (strpos($actionLower, 'delete') !== false) {
        return '#e74a3b'; // merah
    } elseif (strpos($actionLower, 'accessauditlogs') !== false) {
        return '#36b9cc'; // biru muda
    }
    return '#4e73df'; // biru default
}

// =========================
// 3. Ambil Data Audit Logs dengan Filter (Max 30)
// =========================

// Karena file ini khusus untuk role "keuangan", kita tambahkan kondisi tetap.
$conditions = [];
// Selalu tambahkan kondisi untuk hanya menampilkan log dari role "keuangan"
$conditions[] = "u.role = 'keuangan'";

// Periksa parameter GET untuk filter tanggal dan pencarian
if (!empty($_GET['start_date'])) {
    // Asumsikan format YYYY-MM-DD
    $start_date = $conn->real_escape_string($_GET['start_date']);
    $conditions[] = "a.created_at >= '$start_date 00:00:00'";
}
if (!empty($_GET['end_date'])) {
    $end_date = $conn->real_escape_string($_GET['end_date']);
    $conditions[] = "a.created_at <= '$end_date 23:59:59'";
}
if (!empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $conditions[] = "(a.action LIKE '%$search%' OR a.details LIKE '%$search%')";
}

$whereClause = '';
if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

// Query dengan filter, urutkan DESC, limit 30
$sqlFiltered = "SELECT a.*, u.username, u.role FROM audit_logs a 
                LEFT JOIN users u ON a.user_id = u.id_user 
                $whereClause
                ORDER BY a.created_at DESC
                LIMIT 30";
$resultFiltered = $conn->query($sqlFiltered);
if (!$resultFiltered) {
    die("Query error: " . $conn->error);
}

// Tambahkan audit log bahwa halaman logs diakses (log ini juga akan tercatat sebagai log keuangan)
$user_id = $_SESSION['user_id'] ?? 0;
add_audit_log($conn, $user_id, 'AccessAuditLogs', 'Mengakses halaman Audit Logs Keuangan.');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs Keuangan - Keuangan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 4 CSS & SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/css/sb-admin-2.min.css" nonce="<?php echo $nonce; ?>">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" nonce="<?php echo $nonce; ?>">
    <!-- Custom CSS untuk Card dan Timeline -->
    <style nonce="<?php echo $nonce; ?>">
        body {
            background-color: #f8f9fc;
        }
        /* Card custom dengan ukuran maksimal tidak terlalu besar */
        .card-custom {
            max-width: 900px;
            margin: 20px auto;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        /* Timeline Styles */
        .vertical-timeline {
            position: relative;
            padding: 20px 0;
            margin: 0;
        }
        .vertical-timeline::before {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            left: 30px;
            width: 4px;
            background: #4e73df;
            border-radius: 2px;
        }
        .vertical-timeline-item {
            position: relative;
            margin-bottom: 20px;
            padding-left: 70px;
        }
        .vertical-timeline-item:last-child {
            margin-bottom: 0;
        }
        .vertical-timeline-icon {
            position: absolute;
            left: 12px;
            top: 0;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid #4e73df;
            text-align: center;
            line-height: 32px;
            font-size: 18px;
            /* Warna akan di-set secara inline */
        }
        .vertical-timeline-content {
            background: #ffffff;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #4e73df;
            position: relative;
            margin-bottom: 10px;
        }
        .vertical-timeline-content h5 {
            margin-top: 0;
            margin-bottom: 5px;
            font-size: 1rem;
            font-weight: bold;
            color: #2e59d9;
        }
        .vertical-timeline-content p {
            margin: 0;
            font-size: 0.9rem;
            color: #858796;
        }
        .timeline-meta {
            font-size: 0.8rem;
            color: #6e707e;
            margin-top: 5px;
        }
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .vertical-timeline {
                padding-left: 10px;
            }
            .vertical-timeline-item {
                padding-left: 60px;
            }
            .vertical-timeline-icon {
                left: 5px;
            }
        }
    </style>
</head>
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include(__DIR__ . '/../../sidebar.php'); ?>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fas fa-bars"></i>
                    </button>
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a href="/payroll_absensi_v2/logout.php" class="btn btn-danger btn-sm" title="Logout">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </nav>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 text-gray-800"><i class="fas fa-list-alt"></i> Audit Logs Keuangan</h1>
                    </div>

                    <!-- Filter Form Card -->
                    <div class="card card-custom mb-4">
                        <div class="card-header bg-secondary text-white">
                            <i class="fas fa-filter"></i> Filter Audit Logs
                        </div>
                        <div class="card-body">
                            <form method="GET" id="filterForm" class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="start_date">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="end_date">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="search">Search</label>
                                    <input type="text" class="form-control" id="search" name="search" placeholder="Action or Details" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                </div>
                                <div class="form-group col-md-12 text-right">
                                    <button type="submit" class="btn btn-primary mt-2"><i class="fas fa-search"></i> Filter</button>
                                    <a href="audit_logs_keuangan.php" class="btn btn-warning mt-2 ml-2"><i class="fas fa-sync-alt"></i> Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Audit Logs Timeline Card -->
                    <div class="card card-custom">
                        <div class="card-header bg-primary text-white">
                            <h6 class="m-0 font-weight-bold"><i class="fas fa-clock"></i> Audit Logs Timeline (Max 30)</h6>
                        </div>
                        <div class="card-body">
                            <div class="vertical-timeline">
                                <?php
                                if ($resultFiltered->num_rows == 0) {
                                    echo "<p class='text-center'>No audit logs found with the selected filters.</p>";
                                }
                                while ($row = $resultFiltered->fetch_assoc()):
                                    $actionText  = htmlspecialchars($row['action'], ENT_QUOTES, 'UTF-8');
                                    $detailsText = htmlspecialchars($row['details'], ENT_QUOTES, 'UTF-8');
                                    $username    = htmlspecialchars($row['username'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
                                    $role        = htmlspecialchars($row['role'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
                                    $dateStr     = date("d M Y, H:i", strtotime($row['created_at']));
                                    // Dapatkan warna berdasarkan jenis aksi
                                    $color = getActivityColor($actionText);
                                ?>
                                <div class="vertical-timeline-item">
                                    <div class="vertical-timeline-icon" style="border-color: <?php echo $color; ?>; color: <?php echo $color; ?>;">
                                        <?php echo getActivityIcon($actionText); ?>
                                    </div>
                                    <div class="vertical-timeline-content" style="border-left-color: <?php echo $color; ?>;">
                                        <h5><?php echo $actionText; ?></h5>
                                        <p><?php echo $detailsText; ?></p>
                                        <p class="timeline-meta">
                                            <?php echo getRoleIcon($role) . ' <strong>' . $username . '</strong> (' . ucfirst($role) . ')'; ?>
                                            <span class="float-right"><?php echo $dateStr; ?></span>
                                        </p>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End of Page Content -->
            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?php echo date("Y"); ?> Payroll Management System</span>
                    </div>
                </div>
            </footer>
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <!-- JS Dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>
</body>
</html>

<?php
$conn->close();
?>
