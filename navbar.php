<?php
// navbar.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn)) {
    require_once __DIR__ . '/koneksi.php';
}
require_once __DIR__ . '/helpers.php';

$role     = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? '';
$nama     = $_SESSION['nama'] ?? $username;
$nip      = $_SESSION['nip'] ?? '';

// Gambar profil default
$foto = $_SESSION['foto_profil'] ?? '/assets/img/undraw_profile.svg';
if (empty($foto)) {
    $foto = '/assets/img/undraw_profile.svg';
}

// ----------------------------------------------
// 1. (Dihapus) Bagian Ambil Data Audit Log
// ----------------------------------------------

// 2. Hitung notifikasi payroll (untuk user dengan role SDM / superadmin saja)
$payrollNotification = "";
$payrollCount        = 0; // penanda apakah ada notifikasi payroll
if (in_array($role, ['sdm', 'superadmin'])) {
    $currentDay   = (int) date('d');
    $currentMonth = (int) date('n');
    $currentYear  = (int) date('Y');

    // Jika sudah tanggal 24 atau lebih, targetkan ke bulan berikutnya
    if ($currentDay >= 24) {
        if ($currentMonth == 12) {
            $targetMonth = 1;
            $targetYear  = $currentYear + 1;
        } else {
            $targetMonth = $currentMonth + 1;
            $targetYear  = $currentYear;
        }
    } else {
        $targetMonth = $currentMonth;
        $targetYear  = $currentYear;
    }

    // Cek anggota yang belum masuk ke payroll_final untuk targetMonth & targetYear
    $sqlPayroll = "SELECT COUNT(*) AS pending
                   FROM anggota_sekolah
                   WHERE id NOT IN (
                       SELECT id_anggota 
                       FROM payroll_final
                       WHERE bulan = ? AND tahun = ?
                   )";
    $stmtPayroll = $conn->prepare($sqlPayroll);
    if ($stmtPayroll) {
        $stmtPayroll->bind_param("ii", $targetMonth, $targetYear);
        $stmtPayroll->execute();
        $resultPayroll = $stmtPayroll->get_result();
        $rowPayroll = $resultPayroll->fetch_assoc();
        $pendingPayroll = intval($rowPayroll['pending'] ?? 0);
        $stmtPayroll->close();
    } else {
        error_log("Gagal menyiapkan statement notifikasi payroll: " . $conn->error);
        $pendingPayroll = 0;
    }

    if ($pendingPayroll > 0) {
        $monthName = getIndonesianMonthName($targetMonth);
        $payrollNotification = "Payroll Bulan {$monthName} Terdapat {$pendingPayroll} Anggota Belum Dibayar";
        // Jika hanya ingin menampilkan 1 notifikasi saja
        $payrollCount = 1;
    }
}

// 3. Total notifikasi hanya dari payroll (karena audit log dihapus)
$totalAlerts = $payrollCount;

// Fungsi helper untuk menampilkan badge
function formatBadge($count) {
    if ($count < 1) return "";
    // Jika count = 1, tampil "1"
    // Jika count > 1, tampil "2+", "3+", dst
    return ($count === 1) ? "1" : ($count . "+");
}
?>

<!-- Topbar -->
<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

    <!-- Sidebar Toggle (Topbar) -->
    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle me-3">
        <i class="fa fa-bars"></i>
    </button>

    <!-- Topbar Search -->
    <form class="d-none d-sm-inline-block form-inline me-auto ms-md-3 my-2 my-md-0 mw-100 navbar-search">
        <div class="input-group">
            <input type="text" class="form-control bg-light border-0 small" 
                   placeholder="Search for..." aria-label="Search" 
                   aria-describedby="basic-addon2">
            <div class="input-group-append">
                <button class="btn btn-primary" type="button">
                    <i class="fas fa-search fa-sm"></i>
                </button>
            </div>
        </div>
    </form>

    <!-- Topbar Navbar -->
    <ul class="navbar-nav ms-auto">

        <!-- Nav Item - Search Dropdown (Visible Only XS) -->
        <li class="nav-item dropdown no-arrow d-sm-none">
            <a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button"
               data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-search fa-fw"></i>
            </a>
            <!-- Dropdown - Search -->
            <div class="dropdown-menu dropdown-menu-end p-3 shadow animated--grow-in"
                 aria-labelledby="searchDropdown">
                <form class="form-inline mr-auto w-100 navbar-search">
                    <div class="input-group">
                        <input type="text" class="form-control bg-light border-0 small"
                               placeholder="Search for..." aria-label="Search"
                               aria-describedby="basic-addon2">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="button">
                                <i class="fas fa-search fa-sm"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </li>

        <!-- Nav Item - Alerts -->
        <li class="nav-item dropdown no-arrow mx-1">
            <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" 
               role="button" data-bs-toggle="dropdown" 
               aria-haspopup="true" aria-expanded="false">
               
                <i class="fas fa-bell fa-fw"></i>
                <?php if ($totalAlerts > 0): ?>
                    <!-- Tampilkan badge dengan format 1 atau 2+ -->
                    <span class="badge badge-danger badge-counter">
                        <?= formatBadge($totalAlerts); ?>
                    </span>
                <?php endif; ?>
            </a>
            <!-- Dropdown - Alerts -->
            <div class="dropdown-list dropdown-menu dropdown-menu-end shadow animated--grow-in"
                 aria-labelledby="alertsDropdown">
                <h6 class="dropdown-header">
                    Alerts Center
                </h6>

                <!-- Notifikasi Payroll -->
                <?php if (!empty($payrollNotification)): ?>
                    <a class="dropdown-item d-flex align-items-center" href="#">
                        <div class="mr-3">
                            <div class="icon-circle bg-warning">
                                <i class="fas fa-exclamation-triangle text-white"></i>
                            </div>
                        </div>
                        <div>
                            <!-- Tanggal "sekarang" untuk info notifikasi -->
                            <div class="small text-gray-500"><?= date('F d, Y'); ?></div>
                            <span class="font-weight-bold">
                                <?= htmlspecialchars($payrollNotification); ?>
                            </span>
                        </div>
                    </a>
                <?php else: ?>
                    <!-- Jika tidak ada notifikasi payroll -->
                    <a class="dropdown-item text-center small text-gray-500" href="#">
                        No alerts available
                    </a>
                <?php endif; ?>

                <a class="dropdown-item text-center small text-gray-500" href="#">
                    Show All Alerts
                </a>
            </div>
        </li>

        <!-- Nav Item - Messages -->
        <li class="nav-item dropdown no-arrow mx-1">
            <a class="nav-link dropdown-toggle" href="#" id="messagesDropdown" 
               role="button" data-bs-toggle="dropdown" 
               aria-haspopup="true" aria-expanded="false">
               
                <i class="fas fa-envelope fa-fw"></i>
                <span class="badge badge-danger badge-counter">7</span>
            </a>
            <!-- Dropdown - Messages -->
            <div class="dropdown-list dropdown-menu dropdown-menu-end shadow animated--grow-in"
                 aria-labelledby="messagesDropdown">
                <h6 class="dropdown-header">
                    Message Center
                </h6>
                <!-- Contoh pesan statis -->
                <a class="dropdown-item d-flex align-items-center" href="#">
                    <div class="dropdown-list-image me-3">
                        <img class="rounded-circle" src="img/undraw_profile_1.svg" alt="...">
                        <div class="status-indicator bg-success"></div>
                    </div>
                    <div class="font-weight-bold">
                        <div class="text-truncate">
                            Hi there! I am wondering if you can help me with a problem I've been having.
                        </div>
                        <div class="small text-gray-500">Emily Fowler · 58m</div>
                    </div>
                </a>
                <a class="dropdown-item text-center small text-gray-500" href="#">
                    Read More Messages
                </a>
            </div>
        </li>

        <div class="topbar-divider d-none d-sm-block"></div>

        <!-- Nav Item - User Information -->
        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" 
               role="button" data-bs-toggle="dropdown" 
               aria-haspopup="true" aria-expanded="false">
               
                <span class="me-2 d-none d-lg-inline text-gray-600 small">
                    <?= htmlspecialchars($nama); ?>
                </span>
                <img class="img-profile rounded-circle" src="<?= htmlspecialchars($foto); ?>">
            </a>
            <!-- Dropdown - User Information -->
            <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in"
                 aria-labelledby="userDropdown">
                <a class="dropdown-item" href="<?= BASE_URL ?>/profile.php">
                    <i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i>
                    Profile
                </a>
                <a class="dropdown-item" href="<?= BASE_URL ?>/settings.php">
                    <i class="fas fa-cogs fa-sm fa-fw me-2 text-gray-400"></i>
                    Settings
                </a>
                <a class="dropdown-item" href="<?= BASE_URL ?>/activity_log.php">
                    <i class="fas fa-list fa-sm fa-fw me-2 text-gray-400"></i>
                    Activity Log
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i>
                    Logout
                </a>
            </div>
        </li>

    </ul>

</nav>
<!-- End of Topbar -->
