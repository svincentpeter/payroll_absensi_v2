<?php
// File: /payroll_absensi_v2/sidebar.php

// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definisikan konstanta BASE_URL untuk memudahkan perubahan path di masa mendatang
if (!defined('BASE_URL')) {
    define('BASE_URL', '/payroll_absensi_v2');
}

// Mengambil informasi pengguna dari session
$role     = $_SESSION['role'] ?? '';       // Contoh nilai: 'P', 'TK', 'M', 'superadmin', 'sdm', 'keuangan', 'kepala_sekolah'
$username = $_SESSION['username'] ?? '';
$nama     = $_SESSION['nama'] ?? '';
$nip      = $_SESSION['nip'] ?? '';
$job_title= $_SESSION['job_title'] ?? '';

// Jika job_title belum ada di session dan nip tersedia, ambil dari database
if (empty($job_title) && !empty($nip)) {
    require_once __DIR__ . '/koneksi.php'; // Sesuaikan path dengan lokasi koneksi.php
    $stmt = $conn->prepare("SELECT job_title FROM anggota_sekolah WHERE nip = ?");
    $stmt->bind_param("s", $nip);
    $stmt->execute();
    $stmt->bind_result($fetched_job_title);
    $stmt->fetch();
    $stmt->close();

    // Simpan ke session
    $_SESSION['job_title'] = $fetched_job_title;
    $job_title = $fetched_job_title;
}

/**
 * Menentukan apakah link aktif atau tidak berdasarkan URL saat ini.
 * @param string $menuUrl => relative path, misal '/absensi/sdm/dashboard_sdm.php'
 * @return string => 'active' jika cocok, '' jika tidak.
 */
function isActive($menuUrl) {
    // Ambil path saat ini (misal: /payroll_absensi_v2/absensi/sdm/dashboard_sdm.php)
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    // Bangun full path menu
    $fullMenuUrl = BASE_URL . $menuUrl;

    // Cek apakah $currentPath diawali oleh $fullMenuUrl
    if (strpos($currentPath, $fullMenuUrl) === 0) {
        return 'active';
    }
    return '';
}

/**
 * Membuat item menu yang bisa di-collapse (memiliki submenu).
 * @param string $id => id unik untuk collapse (misal: 'collapseKelolaSistem')
 * @param string $iconClass => class icon FontAwesome (misal: 'fas fa-user-tie fa-fw')
 * @param string $title => Judul grup menu (misal: 'Role SDM')
 * @param array $items => array label => url (misal: ['Dashboard SDM' => '/absensi/sdm/dashboard_sdm.php'])
 */
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
    echo '  <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#' . htmlspecialchars($id) . '" aria-expanded="false" aria-controls="' . htmlspecialchars($id) . '">';
    echo '    <i class="' . htmlspecialchars($iconClass) . '"></i>';
    echo '    <span>' . htmlspecialchars($title) . '</span>';
    echo '  </a>';
    echo '  <div id="' . htmlspecialchars($id) . '" class="collapse' . $collapseShow . '" data-bs-parent="#accordionSidebar">';
    echo '    <div class="bg-white py-2 collapse-inner rounded">';
    foreach ($items as $label => $url) {
        $itemActive = isActive($url);
        echo '      <a class="collapse-item ' . $itemActive . '" href="' . BASE_URL . htmlspecialchars($url) . '">' . htmlspecialchars($label) . '</a>';
    }
    echo '    </div>';
    echo '  </div>';
    echo '</li>';
}
?>
<style>
    /* Tambahan style untuk menandai item sidebar yang aktif */
    .sidebar .nav-item.active > .nav-link,
    .sidebar .nav-item.active > .nav-link:hover {
        background-color: #4e73df; /* Warna primer SB Admin 2 */
        color: #fff;
    }
</style>

<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Sidebar - Brand (Logo / Judul Sistem) -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= BASE_URL ?>/index.php">
        <div class="sidebar-brand-icon rotate-n-15">
            <!-- Ikon Font Awesome, misalnya graduation-cap untuk sekolah -->
            <i class="fas fa-graduation-cap fa-fw"></i>
        </div>
        <div class="sidebar-brand-text mx-3">Sistem Nusaputera</div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <!-- Panel Informasi Pengguna -->
    <div class="sidebar-heading mt-3 text-center">
        <?php if (in_array($role, ['P', 'TK', 'superadmin', 'sdm', 'keuangan', 'kepala_sekolah'])): ?>
            <strong><?= htmlspecialchars($nama ?: $username) ?></strong><br>
            <small><?= htmlspecialchars($job_title) ?></small><br>
            <small>NIP: <?= htmlspecialchars($nip) ?></small>
        <?php else: ?>
            <strong>Pengguna</strong><br>
            <small>Unknown</small>
        <?php endif; ?>
    </div>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Menu Berdasarkan Peran -->
    <?php if ($role === 'superadmin'): ?>
        <!-- Contoh menu superadmin dengan sub-menu -->
        <?php
        $kelolaSistemItems = [
            'Kelola User' => '/payroll/superadmin/kelola_user.php',
            'Audit Logs'  => '/payroll/superadmin/logs.php'
        ];
        renderCollapseMenu('collapseKelolaSistem', 'fas fa-lock fa-fw', 'Kelola Sistem', $kelolaSistemItems);

        $laporanItems = [
            'Laporan Gaji' => '/payroll/laporan_gaji.php'
        ];
        renderCollapseMenu('collapseLaporan', 'fas fa-file-alt fa-fw', 'Laporan', $laporanItems);

        // Role SDM (superadmin juga bisa akses, contoh)
        $sdmItems = [
            'Dashboard SDM'           => '/absensi/sdm/dashboard_sdm.php',
            'Koreksi Absensi'         => '/absensi/sdm/koreksi_absensi.php',
            'Kelola Guru/Karyawan'    => '/absensi/sdm/manage_guru_karyawan.php',
            'Payroll Anggota'         => '/absensi/sdm/employees.php',
            'Payheads'                => '/absensi/sdm/payheads.php',
            'Laporan Surat Ijin'      => '/absensi/sdm/laporan_pengajuan_ijin.php',
            'Audit Logs SDM'          => '/absensi/sdm/audit_logs_sdm.php',
            'Notifikasi SDM'          => '/absensi/sdm/notifikasi_sdm.php'
        ];
        renderCollapseMenu('collapseSDM', 'fas fa-user-tie fa-fw', 'Role SDM', $sdmItems);

        // Role Keuangan (superadmin juga bisa akses, contoh)
        $keuanganItems = [
            'Dashboard Keuangan'   => '/payroll/keuangan/dashboard_keuangan.php',
            'List Payroll'         => '/payroll/keuangan/list_payroll.php',
            'History Payroll'      => '/payroll/keuangan/payroll_history.php',
            'Rekap Payroll'        => '/payroll/keuangan/rekap_payroll.php',
            'Audit Logs Keuangan'  => '/payroll/keuangan/audit_logs_keuangan.php',
            'Notifikasi Keuangan'  => '/payroll/keuangan/notifikasi_keuangan.php'
        ];
        renderCollapseMenu('collapseKeuangan', 'fas fa-wallet fa-fw', 'Role Keuangan', $keuanganItems);
        ?>
    <?php elseif ($role === 'sdm'): ?>
        <!-- Menu SDM tanpa collapse -->
        <li class="nav-item <?= isActive('/absensi/sdm/dashboard_sdm.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/sdm/dashboard_sdm.php">
                <i class="fas fa-tachometer-alt fa-fw"></i>
                <span>Dashboard SDM</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/sdm/koreksi_absensi.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/sdm/koreksi_absensi.php">
                <i class="fas fa-edit fa-fw"></i>
                <span>Koreksi Absensi</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/sdm/manage_guru_karyawan.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/sdm/manage_guru_karyawan.php">
                <i class="fas fa-users fa-fw"></i>
                <span>Kelola Guru/Karyawan</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/sdm/employees.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/sdm/employees.php">
                <i class="fas fa-file-alt fa-fw"></i>
                <span>Payroll Anggota</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/sdm/payheads.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/sdm/payheads.php">
                <i class="fas fa-layer-group fa-fw"></i>
                <span>Payheads</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/sdm/laporan_pengajuan_ijin.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/sdm/laporan_pengajuan_ijin.php">
                <i class="fas fa-envelope-open fa-fw"></i>
                <span>Laporan Surat Ijin</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/sdm/audit_logs_sdm.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/sdm/audit_logs_sdm.php">
                <i class="fas fa-clipboard-check fa-fw"></i>
                <span>Audit Logs SDM</span>
            </a>
        </li>
    <?php elseif ($role === 'keuangan'): ?>
        <!-- Menu Keuangan tanpa collapse -->
        <li class="nav-item <?= isActive('/payroll/keuangan/dashboard_keuangan.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/payroll/keuangan/dashboard_keuangan.php">
                <i class="fas fa-chart-line fa-fw"></i>
                <span>Dashboard Keuangan</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/payroll/keuangan/list_payroll.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/payroll/keuangan/list_payroll.php">
                <i class="fas fa-list-alt fa-fw"></i>
                <span>List Payroll</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/payroll/keuangan/payroll_history.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/payroll/keuangan/payroll_history.php">
                <i class="fas fa-clock fa-fw"></i>
                <span>History Payroll</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/payroll/keuangan/rekap_payroll.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/payroll/keuangan/rekap_payroll.php">
                <i class="fas fa-file-invoice-dollar fa-fw"></i>
                <span>Rekap Payroll</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/payroll/keuangan/audit_logs_keuangan.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/payroll/keuangan/audit_logs_keuangan.php">
                <i class="fas fa-clipboard-check fa-fw"></i>
                <span>Audit Logs Keuangan</span>
            </a>
        </li>
    <?php elseif ($role === 'kepala_sekolah'): ?>
        <li class="nav-item <?= isActive('/absensi/kepalasekolah/dashboard_kepala_sekolah.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/kepalasekolah/dashboard_kepala_sekolah.php">
                <i class="fas fa-home fa-fw"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/kepalasekolah/laporan_ijin_ke_kepalasekolah.php'); ?>">
    <a class="nav-link" href="<?= BASE_URL ?>/absensi/kepalasekolah/laporan_ijin_ke_kepalasekolah.php">
        <i class="fas fa-envelope fa-fw"></i>
        <span>Laporan Surat Ijin</span>
    </a>
</li>

        <!-- Tambahkan menu tambahan khusus Kepala Sekolah jika dibutuhkan -->
        <!--
        <li class="nav-item <?= isActive('/absensi/kepalasekolah/laporan_absensi.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/kepalasekolah/laporan_absensi.php">
                <i class="fas fa-file-alt fa-fw"></i>
                <span>Laporan Absensi</span>
            </a>
        </li>
        -->
    <?php elseif (in_array($role, ['P', 'TK'])): ?>
        <!-- Menu untuk Guru/Pendidik (P) dan Tenaga Kependidikan (TK) -->
        <li class="nav-item <?= isActive('/absensi/guru/dashboard_guru.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/guru/dashboard_guru.php">
                <i class="fas fa-home fa-fw"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/guru/pengajuan_surat_ijin.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/guru/pengajuan_surat_ijin.php">
                <i class="fas fa-envelope fa-fw"></i>
                <span>Ajukan Permohonan Ijin</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/guru/list_hari_libur.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/guru/list_hari_libur.php">
                <i class="fas fa-calendar-alt fa-fw"></i>
                <span>List Hari Libur</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/guru/dashboard_jadwal.php'); ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/guru/dashboard_jadwal.php">
                <i class="fas fa-briefcase fa-fw"></i>
                <span>Jadwal Piket</span>
            </a>
        </li>
    <?php else: ?>
        <!-- Jika role tidak dikenal -->
        <li class="nav-item">
            <a class="nav-link" href="#">
                <i class="fas fa-question-circle fa-fw"></i>
                <span>Role tidak dikenal</span>
            </a>
        </li>
    <?php endif; ?>

    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">

    <!-- Sidebar Toggler (untuk show/hide sidebar) -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>
</ul>
<!-- End of Sidebar -->
