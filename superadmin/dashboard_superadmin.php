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
    authorize(['M:Superadmin']);

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
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Superadmin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap & SB Admin 2 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom Style -->
    <style>
        body { background: #f8fafc; }
        .page-title {
            font-family: 'Poppins',sans-serif;
            font-weight: 700;
            font-size: 2.5rem;
            color: #0d47a1;
            text-shadow: 1px 1px 3px rgba(33,150,243,0.08);
            display: flex; align-items: center; gap: 1rem;
            border-bottom: 4px solid #1976d2;
            padding-bottom: 0.3rem;
            margin-bottom: 1.8rem;
            letter-spacing: -1px;
        }
        .icon-lg-main {
            background: linear-gradient(45deg,#1976d2,#1cc88a);
            color: #fff;
            width: 74px;height: 74px;
            border-radius: 18px;
            display: flex;align-items: center;justify-content: center;
            font-size: 2.8rem;
            box-shadow: 0 2px 8px 0 rgba(33,150,243,0.12);
        }
        /* Stat Cards */
        .stats-card {
            background: #fff;
            border: none;
            box-shadow: 0 3px 14px 0 rgba(33,150,243,.08);
            border-radius: 16px;
            transition: transform 0.13s;
            padding: 18px 22px;
            margin-bottom: 18px;
        }
        .stats-card:hover { transform: scale(1.03); box-shadow: 0 8px 32px 0 rgba(33,150,243,0.10);}
        .stats-title { font-size: 1.02rem; color: #1976d2; font-weight: 500; margin-bottom: 2px;}
        .stats-value { font-size: 2.1rem; font-weight: 700; color: #1b2653; }
        .stats-icon {
            width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;
            font-size: 1.45rem; border-radius: 50%;
            margin-bottom: 8px;
        }
        .stats-primary { background: #e3f0fd; color: #1976d2; }
        .stats-success { background: #e9f8f2; color: #1cc88a; }
        .stats-warning { background: #fff8e1; color: #f6c23e; }
        .stats-danger  { background: #fbe9e7; color: #e74a3b; }
        /* Timeline Styles: (ambil dari punyamu, cuma diperhalus) */
        .vertical-timeline { position: relative; padding: 20px 0; margin: 0;}
        .vertical-timeline::before { content: ""; position: absolute; top: 0; bottom: 0; left: 30px; width: 4px; background: #1976d2; border-radius: 2px;}
        .vertical-timeline-item { position: relative; margin-bottom: 24px; padding-left: 70px;}
        .vertical-timeline-item:last-child { margin-bottom: 0;}
        .vertical-timeline-icon {
            position: absolute; left: 12px; top: 0;
            width: 36px; height: 36px; border-radius: 50%;
            background: #fff; border: 2px solid #1976d2; text-align: center;
            line-height: 32px; font-size: 18px; box-shadow:0 2px 12px 0 rgba(33,150,243,0.10);
        }
        .vertical-timeline-content {
            background: #fff; padding: 15px 18px;
            border-radius: 8px; border-left: 5px solid #1976d2;
            position: relative; margin-bottom: 8px;
            box-shadow: 0 1px 8px 0 rgba(33,150,243,0.06);
        }
        .vertical-timeline-content h5 { margin:0 0 3px 0; font-size: 1.05rem; font-weight: bold; color: #2e59d9;}
        .vertical-timeline-content p { margin: 0; font-size: 0.93rem; color: #616e8e;}
        .timeline-meta { font-size: 0.84rem; color: #6e707e; margin-top: 3px;}
        /* Menu Cards */
        .menu-card {
            background: linear-gradient(45deg, #42a5f5 40%, #1cc88a 100%);
            color: #fff; border: none; border-radius: 16px;
            box-shadow: 0 3px 20px 0 rgba(33,150,243,0.10);
            padding: 1.5rem 1.2rem; transition: transform 0.13s;
        }
        .menu-card:hover { transform: scale(1.04); box-shadow: 0 8px 38px 0 rgba(33,150,243,0.13);}
        .menu-card .card-title { color:#fff; font-size:1.15rem;font-weight:600;}
        .menu-card .btn { background:rgba(255,255,255,0.12); border:none; color:#fff;}
        .menu-card .btn:hover { background:rgba(255,255,255,0.32);}
        @media (max-width: 768px) {
            .icon-lg-main { width:48px;height:48px; font-size:1.3rem;}
            .vertical-timeline { padding-left: 8px;}
            .vertical-timeline-item { padding-left: 60px;}
            .vertical-timeline-icon { left: 5px;}
            .stats-value { font-size:1.3rem;}
        }
    </style>
</head>
<body id="page-top">
<div id="wrapper">
    <?php include __DIR__ . '/../sidebar.php'; ?>
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <?php include __DIR__ . '/../navbar.php'; ?>
            <?php include __DIR__ . '/../breadcrumb.php'; ?>

            <div class="container-fluid">
                <!-- Judul besar + icon -->
                <div class="d-flex align-items-center mb-4">
                    <div class="icon-lg-main me-3"><i class="fas fa-user-shield"></i></div>
                    <h1 class="page-title mb-0">Dashboard Superadmin</h1>
                </div>

                <?php if (isset($_SESSION['notif_success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['notif_success']); ?></div>
                <?php unset($_SESSION['notif_success']); endif; ?>

                <!-- Stat cards: -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="stats-icon stats-primary mb-1"><i class="fas fa-users"></i></div>
                            <div class="stats-title">Total Anggota</div>
                            <div class="stats-value">
                                <?php
                                // Query total anggota (role!=NULL)
                                $qTotal = $conn->query("SELECT COUNT(*) as jml FROM anggota_sekolah WHERE role IS NOT NULL");
                                echo $qTotal ? number_format($qTotal->fetch_assoc()['jml']) : '-';
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="stats-icon stats-success mb-1"><i class="fas fa-user-tie"></i></div>
                            <div class="stats-title">Total Manajerial</div>
                            <div class="stats-value">
                                <?php
                                $qM = $conn->query("SELECT COUNT(*) as jml FROM anggota_sekolah WHERE role='M'");
                                echo $qM ? number_format($qM->fetch_assoc()['jml']) : '-';
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="stats-icon stats-warning mb-1"><i class="fas fa-users-cog"></i></div>
                            <div class="stats-title">Total SDM</div>
                            <div class="stats-value">
                                <?php
                                $qSDM = $conn->query("SELECT COUNT(*) as jml FROM anggota_sekolah WHERE LOWER(job_title) LIKE '%sdm%'");
                                echo $qSDM ? number_format($qSDM->fetch_assoc()['jml']) : '-';
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="stats-icon stats-danger mb-1"><i class="fas fa-wallet"></i></div>
                            <div class="stats-title">Total Keuangan</div>
                            <div class="stats-value">
                                <?php
                                $qKeu = $conn->query("SELECT COUNT(*) as jml FROM anggota_sekolah WHERE LOWER(job_title) LIKE '%keuangan%'");
                                echo $qKeu ? number_format($qKeu->fetch_assoc()['jml']) : '-';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Menu cards: akses cepat -->
<div class="row mb-4">
    <div class="col-md-6 mb-3">
        <div class="card menu-card">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="me-2"><i class="fas fa-wallet fa-2x"></i></div>
                    <div>
                        <div class="card-title">Dashboard Keuangan</div>
                        <div class="card-text mb-2">Lihat rekap gaji final, pemotongan manual, dsb.</div>
                    </div>
                </div>
                <a href="<?= getUrl('keuangan/dashboard_keuangan.php') ?>" class="btn btn-light">
                    <i class="fas fa-arrow-right"></i> Akses Dashboard Keuangan
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card menu-card">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="me-2"><i class="fas fa-users-cog fa-2x"></i></div>
                    <div>
                        <div class="card-title">Dashboard SDM</div>
                        <div class="card-text mb-2">Kelola absensi, upload excel, dsb.</div>
                    </div>
                </div>
                <a href="<?= getUrl('sdm/dashboard_sdm.php') ?>" class="btn btn-success">
                    <i class="fas fa-arrow-right"></i> Akses Dasboard SDM
                </a>
            </div>
        </div>
    </div>
</div>


                <!-- Audit Logs Timeline -->
                <div class="card mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center" style="background:linear-gradient(45deg,#1976d2,#36b9cc);">
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
            </div><!-- end container-fluid -->
        </div><!-- end content -->
        <!-- Footer -->
        <footer class="sticky-footer bg-white">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span>&copy; <?php echo date("Y"); ?> Payroll Management System</span>
                </div>
            </div>
        </footer>
    </div><!-- end content-wrapper -->
</div><!-- end wrapper -->
<!-- JS Dependencies -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery.easing@1.4.1/jquery.easing.min.js"></script>
</body>
</html>
<?php close_db_connection(); ?>