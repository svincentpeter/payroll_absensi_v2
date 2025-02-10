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

// Fungsi helper untuk menentukan apakah link aktif atau tidak.
function isActive($menuUrl) {
    // Dapatkan path URL halaman saat ini
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    // Gabungkan BASE_URL dengan URL menu agar perbandingan konsisten
    $fullMenuUrl = BASE_URL . $menuUrl;
    // Jika halaman saat ini dimulai dengan URL menu, kembalikan 'active'
    if (strpos($currentPath, $fullMenuUrl) === 0) {
        return 'active';
    }
    return '';
}

// Fungsi untuk menghasilkan item collapse menu (update ke Bootstrap 5)
function renderCollapseMenu($id, $iconClass, $title, $items) {
    // Agar variabel $role bisa digunakan di dalam fungsi
    global $role;
    
    $isAnyActive = false;
    foreach ($items as $label => $url) {
        if (isActive($url)) {
            $isAnyActive = true;
            break;
        }
    }
    // Jika user dengan role keuangan dan menu ini adalah dropdown keuangan, paksa aktifkan dropdown-nya.
    if ($role === 'keuangan' && $id === 'collapseKeuangan') {
         $isAnyActive = true;
    }
    // Jika ada item aktif, tambahkan kelas "active" pada parent <li> dan "show" pada collapse-nya.
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
?>
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
        <?php if ($role === 'guru' || $role === 'karyawan'): ?>
            <strong><?= htmlspecialchars($nama) ?></strong><br>
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

    <!-- Menu berdasarkan Peran -->

    <?php if ($role === 'superadmin' || $role === 'keuangan'): ?>
        <?php
        // Dashboard Keuangan untuk Superadmin dan Keuangan
        $keuanganItems = [
            'Dashboard Keuangan'   => '/payroll/keuangan/dashboard_keuangan.php',
            'Payroll Anggota'      => '/payroll/keuangan/employees.php',
            'Payheads'             => '/payroll/keuangan/payheads.php',
            'Rekap Absensi'        => '/payroll/keuangan/rekap_absensi.php',
            'List Payroll'        => '/payroll/keuangan/list_payroll.php',
            'History Payroll'      => '/payroll/keuangan/payroll_history.php',
            'Rekap Payroll'        => '/payroll/keuangan/rekap_payroll.php',
            'Audit Logs Keuangan'  => '/payroll/keuangan/audit_logs_keuangan.php'
        ];
        renderCollapseMenu('collapseKeuangan', 'fa-fw fa-wallet', 'Dashboard Keuangan', $keuanganItems);
        ?>
    <?php endif; ?>

    <?php if ($role === 'superadmin'): ?>
        <?php
        // Menu Kelola Sistem untuk Superadmin
        $kelolaSistemItems = [
            'Kelola User' => '/payroll/superadmin/kelola_user.php',
            'Audit Logs'  => '/payroll/superadmin/logs.php'
        ];
        renderCollapseMenu('collapseKelolaSistem', 'fa-fw fa-cogs', 'Kelola Sistem', $kelolaSistemItems);

        // Menu Laporan untuk Superadmin
        $laporanItems = [
            'Laporan Gaji' => '/payroll/laporan_gaji.php'
        ];
        renderCollapseMenu('collapseLaporan', 'fa-fw fa-file-invoice-dollar', 'Laporan', $laporanItems);

        // Menu Dashboard SDM untuk Superadmin
        $sdmSuperadminItems = [
            'Dashboard SDM'           => '/absensi/sdm/dashboard_sdm.php',
            'Upload Absensi'          => '/absensi/sdm/upload_absensi.php',
            'Review Absensi'         => '/absensi/sdm/koreksi_absensi.php',
            'Pengaturan Gaji Pokok'   => '/absensi/sdm/manage_salary_indices.php',
            'Kelola Guru/Karyawan'    => '/absensi/sdm/manage_guru_karyawan.php',
            'Hari Libur'              => '/absensi/sdm/holidays.php',
            'Laporan Surat Ijin'      => '/absensi/sdm/laporan_pengajuan_ijin.php'
        ];
        renderCollapseMenu('collapseSDMSuperadmin', 'fa-fw fa-users-cog', 'Dashboard SDM', $sdmSuperadminItems);
        ?>
    <?php endif; ?>

    <?php if ($role === 'sdm'): ?>
        <!-- Dashboard SDM -->
        <li class="nav-item <?= isActive('/absensi/sdm/dashboard_sdm.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/sdm/dashboard_sdm.php">
                <i class="fas fa-fw fa-tachometer-alt"></i>
                <span>Dashboard SDM</span>
            </a>
        </li>
        <hr class="sidebar-divider">

        <?php
        // Kelola Absensi untuk SDM
        $kelolaAbsensiItems = [
            'Upload Absensi'      => '/absensi/sdm/upload_absensi.php',
            'Review Absensi'     => '/absensi/sdm/koreksi_absensi.php',
            'Tambah Hari Libur'   => '/absensi/sdm/tambah_hari_libur.php',
            'Tambah Jadwal Piket' => '/absensi/sdm/tambah_jadwal_piket.php'
        ];
        renderCollapseMenu('collapseKelolaAbsensiSDM', 'fa-fw fa-users', 'Kelola Absensi', $kelolaAbsensiItems);

        // Kelola Guru untuk SDM
        $kelolaGuruItems = [
            'Kelola Guru/Karyawan'    => '/absensi/sdm/manage_guru_karyawan.php',
            'Lihat Password Guru'     => '/absensi/sdm/lihat_password_guru.php',
            'Pengajuan Surat Ijin'    => '/absensi/sdm/laporan_pengajuan_ijin.php'
        ];
        renderCollapseMenu('collapseKelolaGuruSDM', 'fa-fw fa-users', 'Kelola Guru', $kelolaGuruItems);
        ?>
    <?php endif; ?>

    <?php if ($role === 'keuangan'): ?>
        <!-- Rekap Gaji -->
        <li class="nav-item <?= isActive('/payroll/rekap_gaji.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/payroll/rekap_gaji.php">
                <i class="fas fa-fw fa-chart-line"></i>
                <span>Rekap Gaji</span>
            </a>
        </li>
        <!-- Pemotongan Manual -->
        <li class="nav-item <?= isActive('/payroll/potongan_manual.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/payroll/potongan_manual.php">
                <i class="fas fa-fw fa-minus-circle"></i>
                <span>Pemotongan Manual</span>
            </a>
        </li>
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
