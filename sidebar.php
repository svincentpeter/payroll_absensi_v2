<?php
// File: /payroll_absensi_v2/sidebar.php

// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/helpers.php';

// Ambil informasi pengguna dari session
$role      = $_SESSION['role'] ?? '';        // 'M', 'P', 'TK', dsb.
$username  = $_SESSION['username'] ?? '';
$nama      = $_SESSION['nama'] ?? '';
$nip       = $_SESSION['nip'] ?? '';
$job_title = $_SESSION['job_title'] ?? '';

// Jika job_title belum ada di session dan nip tersedia, ambil dari database
if (empty($job_title) && !empty($nip)) {
    require_once __DIR__ . '/koneksi.php'; // Sesuaikan path
    $stmt = $conn->prepare("SELECT job_title FROM anggota_sekolah WHERE nip = ?");
    $stmt->bind_param("s", $nip);
    $stmt->execute();
    $stmt->bind_result($fetched_job_title);
    $stmt->fetch();
    $stmt->close();

    $_SESSION['job_title'] = $fetched_job_title;
    $job_title = $fetched_job_title;
}

/**
 * Menentukan apakah link aktif atau tidak berdasarkan URL saat ini.
 */
function isActive($menuUrl) {
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $fullMenuUrl = getBaseUrl() . $menuUrl;
    return (strpos($currentPath, $fullMenuUrl) === 0) ? 'active' : '';
}

/**
 * Membuat item menu collapse dengan submenu.
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
        echo '      <a class="collapse-item ' . $itemActive . '" href="' . getBaseUrl() . htmlspecialchars($url) . '">' . htmlspecialchars($label) . '</a>';
    }
    echo '    </div>';
    echo '  </div>';
    echo '</li>';
}
?>
<style>
    /* Style tambahan untuk sidebar */
    .sidebar {
        font-size: 0.9rem;
    }
    .sidebar .nav-item .nav-link {
        padding: 0.75rem 1rem;
    }
    .sidebar .nav-item.active > .nav-link,
    .sidebar .nav-item.active > .nav-link:hover {
        background-color: #4e73df;
        color: #fff;
    }
    .sidebar .collapse-inner a.collapse-item {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }
    .sidebar-heading {
        font-size: 0.85rem;
        text-transform: uppercase;
        padding: 0.75rem 1rem;
    }
</style>

<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
    <!-- Sidebar - Brand (Logo / Judul Sistem) -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= getBaseUrl() ?>/index.php">
        <div class="sidebar-brand-icon">
            <img src="<?= getBaseUrl(); ?>/assets/img/logo.png" alt="Logo Sistem" class="img-fluid" style="max-width: 50px;">
        </div>
        <div class="sidebar-brand-text mx-3">Sistem Sekolah Nusaputera</div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <!-- Panel Informasi Pengguna -->
    <div class="sidebar-heading text-center">
        <?php
        // Jika role M, P, TK, dsb, tampilkan info pengguna
        if (in_array($role, ['M', 'P', 'TK'])) {
            echo '<strong>' . htmlspecialchars($nama ?: $username) . '</strong><br>';
            echo '<small>' . htmlspecialchars($job_title) . '</small><br>';
            echo '<small>NIP: ' . htmlspecialchars($nip) . '</small>';
        } else {
            // Jika di luar definisi, bisa kita treat sebagai unknown
            echo '<strong>Pengguna</strong><br>';
            echo '<small>Unknown</small>';
        }
        ?>
    </div>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <?php
    /**
     * LOGIKA MENU:
     *  - M:superadmin
     *  - M:sdm
     *  - M:keuangan
     *  - M:kepala sekolah
     *  - P / TK (Guru / Tenaga Kependidikan)
     *  - else => role tidak dikenal
     */

    // 1. Jika role bukan 'M', tapi misal 'P', 'TK' => tampilkan menu Guru/TK
    //    Jika bukan salah satu, kita set role tidak dikenal
    if ($role !== 'M') {
        if (in_array($role, ['P', 'TK'])) {
            // Menu Guru & Tenaga Kependidikan
            ?>
            <li class="nav-item <?= isActive('/absensi/guru/dashboard_guru.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/guru/dashboard_guru.php">
                    <i class="fas fa-home fa-fw"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/absensi/guru/pengajuan_surat_ijin.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/guru/pengajuan_surat_ijin.php">
                    <i class="fas fa-paper-plane fa-fw"></i>
                    <span>Ajukan Permohonan Ijin</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/absensi/guru/laporan_surat.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/guru/laporan_surat.php">
                    <i class="fas fa-paper-plane fa-fw"></i>
                    <span>Laporan Surat</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/absensi/guru/list_hari_libur.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/guru/list_hari_libur.php">
                    <i class="fas fa-calendar-alt fa-fw"></i>
                    <span>List Hari Libur</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/absensi/guru/dashboard_jadwal.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/guru/dashboard_jadwal.php">
                    <i class="fas fa-calendar-check fa-fw"></i>
                    <span>Jadwal Piket</span>
                </a>
            </li>
            <?php
        } else {
            // Role tidak dikenal
            ?>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-question-circle fa-fw"></i>
                    <span>Role tidak dikenal</span>
                </a>
            </li>
            <?php
        }
    }
    // 2. Jika role === 'M', cek sub-role dari job_title
    else {
        // Normalisasi job_title
        $normalizedJobTitle = strtolower($job_title);

        // M:superadmin => menampilkan menu dalam bentuk collapse (Kelola Sistem, Role SDM, Role Keuangan)
        if (strpos($normalizedJobTitle, 'superadmin') !== false) {
            ?>
            <li class="nav-item <?= isActive('/payroll/superadmin/dashboard_superadmin.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/payroll/superadmin/dashboard_superadmin.php">
                    <i class="fas fa-tachometer-alt fa-fw"></i>
                    <span>Dashboard M:Superadmin</span>
                </a>
            </li>
            <?php
            // Kelola Sistem (Collapse)
            $kelolaSistemItems = [
                'Backup Database' => '/payroll/superadmin/backup_database.php',
                'Audit Logs'      => '/payroll/superadmin/logs.php'
            ];
            renderCollapseMenu('collapseKelolaSistem', 'fas fa-user-shield fa-fw', 'Kelola Sistem', $kelolaSistemItems);

            // Role SDM (Collapse)
            $sdmItems = [
                'Dashboard SDM'        => '/absensi/sdm/dashboard_sdm.php',
                'Koreksi Absensi'      => '/absensi/sdm/koreksi_absensi.php',
                'Kelola Guru/Karyawan' => '/absensi/sdm/manage_guru_karyawan.php',
                'Payroll Anggota'      => '/absensi/sdm/employees.php',
                'Payheads'             => '/absensi/sdm/payheads.php',
                'Laporan Surat Ijin'   => '/absensi/sdm/laporan_pengajuan_ijin.php',
                'Pembuatan Surat'      => '/absensi/sdm/pembuatan_surat.php',
                'Audit Logs SDM'       => '/absensi/sdm/audit_logs_sdm.php',
                'Notifikasi SDM'       => '/absensi/sdm/notifikasi_sdm.php'
            ];
            renderCollapseMenu('collapseSDM', 'fas fa-users-cog fa-fw', 'Role SDM', $sdmItems);

            // Role Keuangan (Collapse)
            $keuanganItems = [
                'Dashboard Keuangan'  => '/payroll/keuangan/dashboard_keuangan.php',
                'List Payroll'        => '/payroll/keuangan/list_payroll.php',
                'History Payroll'     => '/payroll/keuangan/payroll_history.php',
                'Rekap Payroll'       => '/payroll/keuangan/rekap_payroll.php',
                'Audit Logs Keuangan' => '/payroll/keuangan/audit_logs_keuangan.php',
                'Notifikasi Keuangan' => '/payroll/keuangan/notifikasi_keuangan.php'
            ];
            renderCollapseMenu('collapseKeuangan', 'fas fa-wallet fa-fw', 'Role Keuangan', $keuanganItems);
        }
        // M:keuangan => top-level (tidak collapse)
        elseif (strpos($normalizedJobTitle, 'keuangan') !== false) {
            ?>
            <li class="nav-item <?= isActive('/payroll/keuangan/dashboard_keuangan.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl() ?>/payroll/keuangan/dashboard_keuangan.php">
                <i class="fas fa-chart-line fa-fw"></i>
                <span>Dashboard Keuangan</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/payroll/keuangan/list_payroll.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl() ?>/payroll/keuangan/list_payroll.php">
                <i class="fas fa-list-ul fa-fw"></i>
                <span>List Payroll</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/payroll/keuangan/payroll_history.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl() ?>/payroll/keuangan/payroll_history.php">
                <i class="fas fa-history fa-fw"></i>
                <span>History Payroll</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/payroll/keuangan/rekap_payroll.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl() ?>/payroll/keuangan/rekap_payroll.php">
                <i class="fas fa-file-invoice-dollar fa-fw"></i>
                <span>Rekap Payroll</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/payroll/keuangan/notifikasi_keuangan.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl() ?>/payroll/keuangan/notifikasi_keuangan.php">
                <i class="fas fa-bell fa-fw"></i>
                <span>Notifikasi Keuangan</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/payroll/keuangan/audit_logs_keuangan.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl() ?>/payroll/keuangan/audit_logs_keuangan.php">
                <i class="fas fa-history fa-fw"></i>
                <span>Audit Logs Keuangan</span>
            </a>
        </li>
            <?php
        }
        // M:sdm => top-level (atau boleh di-collapse, terserah)
        elseif (strpos($normalizedJobTitle, 'sdm') !== false) {
            ?>
            <li class="nav-item <?= isActive('/absensi/sdm/dashboard_sdm.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/sdm/dashboard_sdm.php">
                <i class="fas fa-tachometer-alt fa-fw"></i>
                <span>Dashboard SDM</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/sdm/koreksi_absensi.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/sdm/koreksi_absensi.php">
                <i class="fas fa-edit fa-fw"></i>
                <span>Koreksi Absensi</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/sdm/manage_guru_karyawan.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/sdm/manage_guru_karyawan.php">
                <i class="fas fa-users-cog fa-fw"></i>
                <span>Kelola Guru/Karyawan</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/sdm/employees.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/sdm/employees.php">
                <i class="fas fa-money-check-alt fa-fw"></i>
                <span>Payroll Anggota</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/sdm/payheads.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/sdm/payheads.php">
                <i class="fas fa-layer-group fa-fw"></i>
                <span>Payheads</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/sdm/laporan_pengajuan_ijin.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/sdm/laporan_pengajuan_ijin.php">
                <i class="fas fa-envelope-open-text fa-fw"></i>
                <span>Laporan Surat Ijin</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/sdm/pembuatan_surat.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/sdm/pembuatan_surat.php">
                <i class="fas fa-envelope-open-text fa-fw"></i>
                <span>Pembuatan Surat</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/sdm/audit_logs_sdm.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/sdm/audit_logs_sdm.php">
                <i class="fas fa-history fa-fw"></i>
                <span>Audit Logs SDM</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/absensi/sdm/notifikasi_sdm.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/sdm/notifikasi_sdm.php">
                <i class="fas fa-bell fa-fw"></i>
                <span>Notifikasi SDM</span>
            </a>
        </li>
            <?php
        }
        // M:kepala sekolah => top-level
        elseif (strpos($normalizedJobTitle, 'kepala sekolah') !== false) {
            ?>
            <li class="nav-item <?= isActive('/absensi/kepalasekolah/dashboard_kepala_sekolah.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/kepalasekolah/dashboard_kepala_sekolah.php">
                    <i class="fas fa-home fa-fw"></i>
                    <span>Dashboard M:Kepala Sekolah</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/absensi/kepalasekolah/laporan_ijin_ke_kepalasekolah.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/kepalasekolah/laporan_ijin_ke_kepalasekolah.php">
                    <i class="fas fa-envelope fa-fw"></i>
                    <span>Laporan Surat Ijin</span>
                </a>
            </li>
            <?php
        }
        // Jika job_title manajerial tapi tidak sesuai 4 sub-role di atas
        elseif (in_array($role, ['P', 'TK'])) {
            // Menu untuk Guru & Tenaga Kependidikan
            ?>
            <li class="nav-item <?= isActive('/absensi/guru/dashboard_guru.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/guru/dashboard_guru.php">
                    <i class="fas fa-home fa-fw"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/absensi/guru/pengajuan_surat_ijin.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/guru/pengajuan_surat_ijin.php">
                    <i class="fas fa-paper-plane fa-fw"></i>
                    <span>Ajukan Permohonan Ijin</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/absensi/guru/laporan_surat.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/guru/laporan_surat.php">
                    <i class="fas fa-paper-plane fa-fw"></i>
                    <span>Laporan Surat</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/absensi/guru/list_hari_libur.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/guru/list_hari_libur.php">
                    <i class="fas fa-calendar-alt fa-fw"></i>
                    <span>List Hari Libur</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/absensi/guru/dashboard_jadwal.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/absensi/guru/dashboard_jadwal.php">
                    <i class="fas fa-calendar-check fa-fw"></i>
                    <span>Jadwal Piket</span>
                </a>
            </li>
            <?php
        } else {
            // Jika role tidak dikenali
            ?>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-question-circle fa-fw"></i>
                    <span>Role tidak dikenal</span>
                </a>
            </li>
            <?php
        }
    }
        ?>

    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">

    <!-- Sidebar Toggler -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>
</ul>
<!-- End of Sidebar -->
