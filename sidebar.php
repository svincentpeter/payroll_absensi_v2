<?php
// /payroll_absensi_v2/sidebar.php (perbaikan untuk Bootstrap 5)

// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definisikan konstanta BASE_URL untuk memudahkan perubahan path di masa mendatang
if (!defined('BASE_URL')) {
    define('BASE_URL', '/payroll_absensi_v2');
}

// Mengambil informasi pengguna dari session
$role     = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? '';
$nama     = $_SESSION['nama'] ?? '';
$nip      = $_SESSION['nip'] ?? '';

// Jika job_title belum ada di session dan nip tersedia, ambil dari database
if (empty($_SESSION['job_title']) && !empty($nip)) {
    require_once __DIR__ . '/koneksi.php'; // pastikan path nya benar
    $stmt = $conn->prepare("SELECT job_title FROM anggota_sekolah WHERE nip = ?");
    $stmt->bind_param("s", $nip);
    $stmt->execute();
    $stmt->bind_result($job_title);
    $stmt->fetch();
    $stmt->close();
    $_SESSION['job_title'] = $job_title;
}
$job_title = $_SESSION['job_title'] ?? '';

// Fungsi helper untuk menentukan apakah link aktif atau tidak.
function isActive($menuUrl) {
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $fullMenuUrl = BASE_URL . $menuUrl;
    if (strpos($currentPath, $fullMenuUrl) === 0) {
        return 'active';
    }
    return '';
}

// Fungsi untuk menghasilkan item collapse menu (digunakan khusus untuk superadmin)
function renderCollapseMenu($id, $iconClass, $title, $items) {
    $isAnyActive = false;
    foreach ($items as $label => $url) {
        if (isActive($url)) {
            $isAnyActive = true;
            break;
        }
    }
    $activeClass  = $isAnyActive ? ' active' : '';
    $collapseShow = $isAnyActive ? ' show' : '';

    echo '<li class="nav-item' . $activeClass . '">';
    echo '    <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#' . htmlspecialchars($id) . '" aria-expanded="false" aria-controls="' . htmlspecialchars($id) . '">';
    echo '        <i class="fas ' . htmlspecialchars($iconClass) . '"></i>';
    echo '        <span>' . htmlspecialchars($title) . '</span>';
    echo '    </a>';
    echo '    <div id="' . htmlspecialchars($id) . '" class="collapse' . $collapseShow . '" data-bs-parent="#accordionSidebar">';
    echo '        <div class="bg-white py-2 collapse-inner rounded">';
    foreach ($items as $label => $url) {
        $itemActive = isActive($url);
        echo '            <a class="collapse-item ' . $itemActive . '" href="' . BASE_URL . htmlspecialchars($url) . '">' . htmlspecialchars($label) . '</a>';
    }
    echo '        </div>';
    echo '    </div>';
    echo '</li>';
}

// Fungsi untuk menampilkan menu secara langsung (tanpa collapse)
function renderMenuItems($items, $iconClass = 'fa-fw') {
    foreach ($items as $label => $url) {
        echo '<li class="nav-item ' . isActive($url) . '">';
        echo '    <a class="nav-link" href="' . BASE_URL . htmlspecialchars($url) . '">';
        echo '        <i class="fas ' . htmlspecialchars($iconClass) . '"></i>';
        echo '        <span>' . htmlspecialchars($label) . '</span>';
        echo '    </a>';
        echo '</li>';
    }
}
?>
<style>
    .sidebar .nav-item.active > .nav-link,
    .sidebar .nav-item.active > .nav-link:hover {
        background-color: #4e73df; /* Warna primer SB Admin 2 */
        color: #fff;
    }
</style>
<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= BASE_URL ?>/index.php">
        <div class="sidebar-brand-icon rotate-n-15">
            <i class="fas fa-school"></i>
        </div>
        <div class="sidebar-brand-text mx-3">Sistem Nusaputera</div>
    </a>

    <hr class="sidebar-divider my-0">

    <!-- Panel Informasi Pengguna -->
    <div class="sidebar-heading mt-3 text-center">
        <?php if ($role === 'guru' || $role === 'karyawan' || $role === 'superadmin'): ?>
            <strong><?= htmlspecialchars($nama) ?></strong><br>
            <small><?= htmlspecialchars($job_title) ?></small><br>
            <small>NIP: <?= htmlspecialchars($nip) ?></small>
        <?php elseif ($username): ?>
            <strong><?= htmlspecialchars($username) ?></strong><br>
            <small><?= htmlspecialchars(ucfirst($role)) ?></small>
        <?php else: ?>
            <strong>Pengguna</strong><br>
            <small>Unknown</small>
        <?php endif; ?>
    </div>

    <hr class="sidebar-divider">

    <!-- Menu Berdasarkan Peran -->
    <?php if ($role === 'superadmin'): ?>
        <!-- Menu Kelola Sistem (Hanya Audit Logs) -->
        <?php
        $kelolaSistemItems = [
            'Audit Logs'  => '/payroll/superadmin/logs.php'
        ];
        renderCollapseMenu('collapseKelolaSistem', 'fa-fw fa-cogs', 'Kelola Sistem', $kelolaSistemItems);
        ?>

        <!-- Menu Role SDM -->
        <?php
        $sdmItems = [
            'Dashboard SDM'           => '/absensi/sdm/dashboard_sdm.php',
            'Koreksi Absensi'         => '/absensi/sdm/koreksi_absensi.php',
            'Kelola Guru/Karyawan'    => '/absensi/sdm/manage_guru_karyawan.php',
            'Payroll Anggota'         => '/absensi/sdm/employees.php',
            'Payheads'                => '/absensi/sdm/payheads.php',
            'Laporan Surat Ijin'      => '/absensi/sdm/laporan_pengajuan_ijin.php',
            'Audit Logs SDM'          => '/absensi/sdm/audit_logs_sdm.php'
        ];
        renderCollapseMenu('collapseSDM', 'fa-fw fa-users-cog', 'Role SDM', $sdmItems);
        ?>

        <!-- Menu Role Keuangan -->
        <?php
        $keuanganItems = [
            'Dashboard Keuangan'   => '/payroll/keuangan/dashboard_keuangan.php',
            'List Payroll'         => '/payroll/keuangan/list_payroll.php',
            'History Payroll'      => '/payroll/keuangan/payroll_history.php',
            'Rekap Payroll'        => '/payroll/keuangan/rekap_payroll.php',
            'Audit Logs Keuangan'  => '/payroll/keuangan/audit_logs_keuangan.php'
        ];
        renderCollapseMenu('collapseKeuangan', 'fa-fw fa-wallet', 'Role Keuangan', $keuanganItems);
        ?>

    <?php elseif ($role === 'sdm'): ?>
        <!-- Menu SDM (tanpa collapse) -->
        <?php
        $sdmItems = [
            'Dashboard SDM'           => '/absensi/sdm/dashboard_sdm.php',
            'Koreksi Absensi'         => '/absensi/sdm/koreksi_absensi.php',
            'Kelola Guru/Karyawan'    => '/absensi/sdm/manage_guru_karyawan.php',
            'Payroll Anggota'         => '/absensi/sdm/employees.php',
            'Payheads'                => '/absensi/sdm/payheads.php',
            'Laporan Surat Ijin'      => '/absensi/sdm/laporan_pengajuan_ijin.php',
            'Audit Logs SDM'          => '/absensi/sdm/audit_logs_sdm.php'
        ];
        renderMenuItems($sdmItems, 'fa-fw fa-users-cog');
        ?>

    <?php elseif ($role === 'keuangan'): ?>
        <!-- Menu Keuangan (tanpa collapse) -->
        <?php
        $keuanganItems = [
            'Dashboard Keuangan'   => '/payroll/keuangan/dashboard_keuangan.php',
            'List Payroll'         => '/payroll/keuangan/list_payroll.php',
            'History Payroll'      => '/payroll/keuangan/payroll_history.php',
            'Audit Logs Keuangan'  => '/payroll/keuangan/audit_logs_keuangan.php'
        ];
        renderMenuItems($keuanganItems, 'fa-fw fa-wallet');
        ?>
    <?php endif; ?>

    <?php if ($role === 'guru'): ?>
        <!-- Menu untuk Guru -->
        <li class="nav-item <?= isActive('/absensi/guru/ganti_password_guru.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/guru/ganti_password_guru.php">
                <i class="fas fa-fw fa-key"></i>
                <span>Ganti Password</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/guru/pengajuan_surat_ijin.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/guru/pengajuan_surat_ijin.php">
                <i class="fas fa-fw fa-envelope"></i>
                <span>Ajukan Permohonan Ijin</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/guru/list_hari_libur.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/guru/list_hari_libur.php">
                <i class="fas fa-fw fa-calendar-alt"></i>
                <span>List Hari Libur</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/guru/dashboard_guru.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/guru/dashboard_guru.php">
                <i class="fas fa-fw fa-arrow-left"></i>
                <span>Kembali ke Dashboard</span>
            </a>
        </li>
    <?php endif; ?>

    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">

    <!-- Sidebar Toggler (Sidebar) -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>
<!-- End of Sidebar -->
