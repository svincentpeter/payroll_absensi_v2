    <?php
    // File: /payroll_absensi_v2/superadmin/dashboard_superadmin.php

    // 1. Pengaturan Session, Error Handling, Keamanan, dan CSRF
    require_once __DIR__ . '/../helpers.php';
    start_session_safe();
    init_error_handling();
    generate_csrf_token();

    // Hapus pembuatan nonce karena tidak digunakan lagi
    // $nonce = base64_encode(random_bytes(16));
    // $_SESSION['csp_nonce'] = $nonce;

    // Koneksi ke database
    require_once __DIR__ . '/../koneksi.php';

    // Pastikan hanya superadmin yang dapat mengakses halaman ini
    authorize('M:superadmin', '/payroll_absensi_v2/login.php');

    // 2. Query Audit Logs untuk Preview (5 data terbaru)
    $sqlLogs = "SELECT a.*, u.nama AS username, u.role 
                FROM audit_logs a 
                LEFT JOIN anggota_sekolah u ON a.nip = u.nip 
                ORDER BY a.created_at DESC 
                LIMIT 5";
    $resultLogs = $conn->query($sqlLogs);
    if (!$resultLogs) {
        die("Query error: " . $conn->error);
    }

    // 3. Fungsi Pendukung untuk Audit Logs Preview
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

    function getActivityColor($action)
    {
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
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Dashboard Superadmin</title>
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <!-- Bootstrap 5.3.3 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- SB Admin 2 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
        <!-- Font Awesome & Bootstrap Icons -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
        <!-- Custom CSS untuk Timeline Audit Logs -->
        <style>
            .card-header {
                background: linear-gradient(45deg, #0d47a1, #42a5f5);
                color: white;
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
                        <!-- Page Heading -->
<h1 class="page-title">
        <i class="fas fa-users me-2"></i>
        Dashboard Superadmin
    </h1>
                        <!-- Notifikasi jika ada -->
                        <?php if (isset($_SESSION['notif_success'])): ?>
                            <div class="alert alert-success">
                                <?= htmlspecialchars($_SESSION['notif_success']); ?>
                            </div>
                            <?php unset($_SESSION['notif_success']); ?>
                        <?php endif; ?>

                        <!-- Card Menu Utama -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-start-primary mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title">Dashboard Keuangan</h5>
                                        <p class="card-text">Lihat rekap gaji final, pemotongan manual, dsb.</p>
                                        <a href="/payroll_absensi_v2/payroll/keuangan/dashboard_keuangan.php" class="btn btn-primary">
                                            Akses Dashboard Keuangan
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-start-success mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title">Dashboard SDM</h5>
                                        <p class="card-text">Kelola absensi, upload excel, dsb.</p>
                                        <a href="/payroll_absensi_v2/sdm/dashboard_sdm.php" class="btn btn-success">
                                            Akses Dashboard SDM
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Preview Audit Logs (5 data terbaru) -->
                        <div class="card mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 fw-bold text-white">
                                    <i class="fas fa-clock"></i> Recent Audit Logs
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="vertical-timeline">
                                    <?php
                                    if ($resultLogs->num_rows == 0) {
                                        echo "<p class='text-center'>No audit logs available.</p>";
                                    }
                                    while ($row = $resultLogs->fetch_assoc()):
                                        $actionText  = htmlspecialchars($row['action'], ENT_QUOTES, 'UTF-8');
                                        $detailsText = htmlspecialchars($row['details'], ENT_QUOTES, 'UTF-8');
                                        $username    = htmlspecialchars($row['username'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
                                        $roleText    = htmlspecialchars($row['role'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
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
                                                    <?php echo getRoleIcon($roleText) . ' <strong>' . $username . '</strong> (' . ucfirst($roleText) . ')'; ?>
                                                    <span class="float-end"><?php echo $dateStr; ?></span>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                                <div class="text-end">
                                    <a href="logs.php" class="btn btn-secondary mt-3">
                                        <i class="fas fa-list-alt"></i> View All Logs
                                    </a>
                                </div>
                            </div>
                        </div>

                    </div> <!-- end container-fluid -->
                </div> <!-- end content -->

                <!-- Footer -->
                <footer class="sticky-footer bg-white">
                    <div class="container my-auto">
                        <div class="copyright text-center my-auto">
                            <span>&copy; <?php echo date("Y"); ?> Payroll Management System</span>
                        </div>
                    </div>
                </footer>
            </div> <!-- end content-wrapper -->
        </div> <!-- end wrapper -->

        <!-- JS Dependencies -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/jquery.easing@1.4.1/jquery.easing.min.js"></script>
    </body>

    </html>
    <?php
    // Tutup koneksi database menggunakan fungsi dari helpers.php
    close_db_connection();
    ?>
