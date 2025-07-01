<?php
// File: /payroll_absensi_v2/keuangan/audit_logs_keuangan.php

// =========================
// 1. Pengaturan Dasar & Session
// =========================

// Mulai session (tanpa pengaturan cookie khusus)
session_start();

require_once __DIR__ . '/../helpers.php';
init_error_handling(); // Hanya sistem error log yang dipertahankan

// Pastikan hanya keuangan yang dapat mengakses halaman ini
authorize(['M:Keuangan']);

// Koneksi ke database
require_once __DIR__ . '/../koneksi.php';
if (ob_get_length()) {
    ob_end_clean();
}

// =========================
// 2. Fungsi Pendukung (Ikon, Warna & Badge)
// =========================

function getActivityIcon($action)
{
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

function getRoleIcon($role)
{
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

function getActivityColor($action)
{
    $actionLower = strtolower($action);
    if (strpos($actionLower, 'login') !== false) {
        return '#1cc88a';
    } elseif (strpos($actionLower, 'failed') !== false) {
        return '#e74a3b';
    } elseif (strpos($actionLower, 'edit') !== false) {
        return '#f6c23e';
    } elseif (strpos($actionLower, 'delete') !== false) {
        return '#e74a3b';
    } elseif (strpos($actionLower, 'accessauditlogs') !== false) {
        return '#36b9cc';
    }
    return '#4e73df';
}

// =========================
// 3. Ambil Data Audit Logs dengan Filter (Max 30)
// =========================

// Periksa parameter GET (start_date, end_date, role, search)
$conditions = [];
if (!empty($_GET['start_date'])) {
    $start_date = $conn->real_escape_string($_GET['start_date']);
    $conditions[] = "a.created_at >= '$start_date 00:00:00'";
}
if (!empty($_GET['end_date'])) {
    $end_date = $conn->real_escape_string($_GET['end_date']);
    $conditions[] = "a.created_at <= '$end_date 23:59:59'";
}
if (!empty($_GET['role'])) {
    $role_filter = $conn->real_escape_string($_GET['role']);
    $conditions[] = "u.role = '$role_filter'";
}
if (!empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $conditions[] = "(a.action LIKE '%$search%' OR a.details LIKE '%$search%')";
}
$whereClause = '';
if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

$sqlFiltered = "SELECT a.*, u.nama AS username, u.role 
                FROM audit_logs a 
                LEFT JOIN anggota_sekolah u ON a.nip = u.nip 
                $whereClause
                ORDER BY a.created_at DESC
                LIMIT 30";
$resultFiltered = $conn->query($sqlFiltered);
if (!$resultFiltered) {
    die("Query error: " . $conn->error);
}

// Catat audit log bahwa halaman logs diakses
$user_id = $_SESSION['user_id'] ?? 0;
add_audit_log($conn, $user_id, 'AccessAuditLogs', 'Mengakses halaman Audit Logs.');
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Audit Logs Keuangan - Superadmin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5 CSS & SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <!-- Custom CSS untuk Card dan Timeline -->
    <style>
        body {
            background-color: #f8f9fc;
        }
            .card-header {
                background: linear-gradient(45deg, #0d47a1, #42a5f5);
                color: white;
            }
        .card-custom {
            max-width: 900px;
            margin: 20px auto;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

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
        }

        .vertical-timeline-content {
            background: #ffffff;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #4e73df;
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
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <?php include __DIR__ . '/../navbar.php'; ?>
                <!-- End of Topbar -->
                <!-- Breadcrumb -->
                <?php include __DIR__ . '/../breadcrumb.php'; ?>

                <!-- Begin Page Content -->
                <div class="container-fluid">
                        <h1 class="page-title">
        <i class="fas fa-list-alt"></i> Audit Logs Keuangan</h1>
    </h1>
<!-- Filter Audit Logs -->
<div class="card mb-4 shadow">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-bold text-white">
            <i class="fas fa-filter"></i> Filter Audit Logs
        </h6>
    </div>
    <div class="card-body" style="background-color: #f8f9fa;">
        <form method="GET" id="filterForm" class="row gy-2 gx-3 align-items-center">
            <!-- Start Date -->
            <div class="col-auto">
                <label for="start_date" class="form-label mb-0"><strong>Start Date:</strong></label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
            </div>
            <!-- End Date -->
            <div class="col-auto">
                <label for="end_date" class="form-label mb-0"><strong>End Date:</strong></label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">
            </div>
            <!-- Role -->
            <div class="col-auto">
                <label for="role" class="form-label mb-0"><strong>Role:</strong></label>
                <input type="text" class="form-control" id="role" name="role" placeholder="Filter by Role" value="<?php echo isset($_GET['role']) ? htmlspecialchars($_GET['role']) : ''; ?>">
            </div>
            <!-- Search -->
            <div class="col-auto">
                <label for="search" class="form-label mb-0"><strong>Search:</strong></label>
                <input type="text" class="form-control" id="search" name="search" placeholder="Action or Details" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            </div>
            <!-- Tombol -->
            <div class="col-auto d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="audit_logs_keuangan.php" class="btn btn-warning">
                    <i class="fas fa-sync-alt"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>
<!-- End Filter Audit Logs -->


                    <!-- Audit Logs Timeline Card -->
                    <div class="card card-custom">
                        <div class="card-header bg-primary text-white">
                            <h6 class="m-0 fw-bold"><i class="fas fa-clock"></i> Audit Logs Timeline (Max 30)</h6>
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
                                    $color       = getActivityColor($actionText);
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
                                                <span class="float-end"><?php echo $dateStr; ?></span>
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
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->


    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery.easing@1.4.1/jquery.easing.min.js"></script>

</body>

</html>

<?php
$conn->close();
?>