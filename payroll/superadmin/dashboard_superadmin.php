<?php
// superadmin/dashboard_superadmin.php
session_start();
require_once '../../koneksi.php'; // Sesuaikan path jika diperlukan

// Memeriksa apakah pengguna sudah login dan memiliki peran 'superadmin'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../../login.php");
    exit();
}

// Contoh: Mengambil beberapa data dari database jika diperlukan
try {
    $stmt = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $role_counts = $stmt->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    die("Query gagal: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Superadmin</title>
    <link href="/payroll_absensi_v2/plugins/font-awesome/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link href="/payroll_absensi_v2/assets/css/sb-admin-2.min.css" rel="stylesheet">
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include '../../sidebar.php'; ?> 
        <!-- Pastikan path ini sesuai dengan struktur folder Anda -->

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a href="../../logout.php" class="btn btn-danger btn-sm">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </nav>
                <!-- End Topbar -->

                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Dashboard Superadmin</h1>
                    
                    <!-- Tampilkan notifikasi -->
                    <?php if (isset($_SESSION['notif_success'])): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($_SESSION['notif_success']); ?>
                        </div>
                        <?php unset($_SESSION['notif_success']); ?>
                    <?php endif; ?>
                    
                    <!-- Isi Konten -->
                    <p>Selamat datang, Superadmin! Anda dapat mengakses seluruh sistem.</p>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-left-primary mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Dashboard Keuangan</h5>
                                    <p class="card-text">
                                        Lihat rekap gaji final, pemotongan manual, dsb.
                                    </p>
                                    <a href="../keuangan/dashboard_keuangan.php" class="btn btn-primary">
                                        Akses Dashboard Keuangan
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-left-success mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Dashboard SDM</h5>
                                    <p class="card-text">
                                        Kelola absensi, upload excel, dsb.
                                    </p>
                                    <a href="../sdm/dashboard_sdm.php" class="btn btn-success">
                                        Akses Dashboard SDM
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div> <!-- end container-fluid -->
            </div> <!-- end content -->
        </div> <!-- end content-wrapper -->
    </div> <!-- end wrapper -->

    <script src="../../assets/vendor/jquery/jquery.min.js"></script>
    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../../assets/js/sb-admin-2.min.js"></script>
</body>
</html>
