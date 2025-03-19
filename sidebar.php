<?php
// File: /payroll_absensi_v2/sidebar.php

// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/helpers.php';

// Ambil informasi pengguna dari session
$role      = $_SESSION['role'] ?? '';
$username  = $_SESSION['username'] ?? '';
$nama      = $_SESSION['nama'] ?? '';
$nip       = $_SESSION['nip'] ?? '';
$job_title = $_SESSION['job_title'] ?? '';

// Jika job_title belum ada di session dan nip tersedia, ambil dari database
if (empty($job_title) && !empty($nip)) {
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
    // Ambil path yang sedang diakses, misal "/guru/dashboard_guru.php"
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Pastikan baseUrl hanya path juga (misal "/payroll_absensi_v2")
    // Lalu gabungkan dengan $menuUrl
    $basePath = parse_url(getBaseUrl(), PHP_URL_PATH);
    $targetPath = rtrim($basePath, '/') . $menuUrl;

    // Cek apakah currentPath diawali targetPath
    return (strpos($currentPath, $targetPath) === 0) ? 'active' : '';
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
        echo '      <a class="collapse-item ' . $itemActive . '" href="' . getBaseUrl() . htmlspecialchars($url) . '">';
        echo '        ' . htmlspecialchars($label);
        echo '      </a>';
    }
    echo '    </div>';
    echo '  </div>';
    echo '</li>';
}
?>
<style>
    /* Style tambahan untuk sidebar agar lebih rapi */
    .sidebar {
        font-size: 0.9rem;
    }
    .sidebar .nav-item .nav-link {
        padding: 0.65rem 1rem; /* Sedikit kurangi padding agar lebih ringkas */
        transition: background-color 0.2s ease;
    }
    .sidebar .nav-item .nav-link i {
        margin-right: 6px; /* jarak kecil di antara icon dan label */
    }
    .sidebar .nav-item.active > .nav-link,
    .sidebar .nav-item.active > .nav-link:hover {
        background-color: #4e73df;
        color: #fff;
        font-weight: 600; /* buat teks sedikit lebih tebal */
    }
    .sidebar .collapse-inner a.collapse-item {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }

    .sidebar-divider {
        margin: 0.5rem 0;
    }

    /* Panel informasi pengguna */
    .sidebar-user-info {
        color: #fff;                /* Warna teks putih */
        font-weight: 600;           /* Teks lebih tebal */
        text-align: center;         /* Rata tengah */
        padding: 0.75rem;           /* Ruang di sekitar konten */
        border-bottom: 1px solid rgba(255, 255, 255, 0.3); /* Garis bawah tipis */
        margin-bottom: 0.5rem;      /* Jarak dengan elemen berikutnya */
    }
    .sidebar-user-info small {
        font-weight: normal;        /* Boleh normal agar berbeda dengan nama */
        display: block;            /* Set agar tiap info (job_title, NIP) di baris baru */
    }
</style>

<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
    <!-- Sidebar - Brand (Logo / Judul Sistem) -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= getBaseUrl() ?>/index.php">
        <div class="sidebar-brand-icon">
            <img src="<?= getBaseUrl(); ?>/assets/img/logo.png" alt="Logo Sistem" class="img-fluid" style="max-width: 50px;">
        </div>
        <div class="sidebar-brand-text mx-2" style="font-size:0.8rem; line-height:1.2;">
            Sistem Sekolah <br>Nusaputera
        </div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <!-- Panel Informasi Pengguna -->
<div class="sidebar-user-info">
    <?php
    // Tampilkan user info + role
    if (in_array($role, ['M', 'P', 'TK'])) {
        // Nama atau username
        echo '<strong>' . htmlspecialchars($nama ?: $username) . '</strong>';
        // Tampilkan job_title
        echo '<small>' . htmlspecialchars($job_title) . '</small>';
        // Tampilkan NIP
        echo '<small>NIP: ' . htmlspecialchars($nip) . '</small>';
        echo '<small>Role: ' . htmlspecialchars($role) . '</small>';
    } else {
        echo '<strong>Pengguna</strong>';
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
    if ($role !== 'M') {
        if (in_array($role, ['P', 'TK'])) {
            ?>
            <li class="nav-item <?= isActive('/guru/dashboard_guru.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/guru/dashboard_guru.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/guru/pengajuan_surat_ijin.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/guru/pengajuan_surat_ijin.php">
                    <i class="fas fa-paper-plane"></i>
                    <span>Ajukan Ijin</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/guru/laporan_surat.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/guru/laporan_surat.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Laporan Surat</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/guru/list_hari_libur.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/guru/list_hari_libur.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Hari Libur</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/guru/dashboard_jadwal.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/guru/dashboard_jadwal.php">
                    <i class="fas fa-calendar-check"></i>
                    <span>Jadwal Piket</span>
                </a>
            </li>
            <?php
        } else {
            // Role tidak dikenal
            ?>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-question-circle"></i>
                    <span>Role tidak dikenal</span>
                </a>
            </li>
            <?php
        }
    }
    // 2. Jika role === 'M' => cek sub-role di job_title
    else {
        $normalizedJobTitle = strtolower($job_title);

        // M:superadmin => menampilkan menu collapse (Kelola Sistem, Role SDM, Role Keuangan)
        if (strpos($normalizedJobTitle, 'superadmin') !== false) {
            ?>
            <li class="nav-item <?= isActive('/superadmin/dashboard_superadmin.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/superadmin/dashboard_superadmin.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard Superadmin</span>
                </a>
            </li>
            <?php
            // Kelola Sistem (Collapse)
            $kelolaSistemItems = [
                'Backup Database' => '/superadmin/backup_database.php',
                'Audit Logs'      => '/superadmin/logs.php',
                'Error Logs'      => '/superadmin/error_log.php'
            ];
            renderCollapseMenu('collapseKelolaSistem', 'fas fa-user-shield', 'Kelola Sistem', $kelolaSistemItems);

            // Role SDM (Collapse)
            $sdmItems = [
                'Dashboard SDM'        => '/sdm/dashboard_sdm.php',
                'Koreksi Absensi'      => '/sdm/koreksi_absensi.php',
                'Kelola Guru/Karyawan' => '/sdm/manage_guru_karyawan.php',
                'Payroll Anggota'      => '/sdm/employees.php',
                'Payheads'             => '/sdm/payheads.php',
                'Laporan Surat Ijin'   => '/sdm/laporan_pengajuan_ijin.php',
                'Laporan Jadwal Piket'   => '/sdm/laporan_jadwal_piket.php',
                'Pembuatan Surat'      => '/sdm/pembuatan_surat.php',
                'Audit Logs SDM'       => '/sdm/audit_logs_sdm.php',
                'Notifikasi SDM'       => '/sdm/notifikasi_sdm.php'
            ];
            renderCollapseMenu('collapseSDM', 'fas fa-users-cog', 'Role SDM', $sdmItems);

            // Role Keuangan (Collapse)
            $keuanganItems = [
                'Dashboard Keuangan'  => '/keuangan/dashboard_keuangan.php',
                'List Payroll'        => '/keuangan/list_payroll.php',
                'History Payroll'     => '/keuangan/payroll_history.php',
                'Rekap Payroll'       => '/keuangan/rekap_payroll.php',
                'Audit Logs Keuangan' => '/keuangan/audit_logs_keuangan.php',
                'Notifikasi Keuangan' => '/keuangan/notifikasi_keuangan.php'
            ];
            renderCollapseMenu('collapseKeuangan', 'fas fa-wallet', 'Role Keuangan', $keuanganItems);
        }
        // M:keuangan => top-level
        elseif (strpos($normalizedJobTitle, 'keuangan') !== false) {
            ?>
            <li class="nav-item <?= isActive('/keuangan/dashboard_keuangan.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/keuangan/dashboard_keuangan.php">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard Keuangan</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/keuangan/list_payroll.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/keuangan/list_payroll.php">
                    <i class="fas fa-list-ul"></i>
                    <span>List Payroll</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/keuangan/payroll_history.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/keuangan/payroll_history.php">
                    <i class="fas fa-history"></i>
                    <span>History Payroll</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/keuangan/rekap_payroll.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/keuangan/rekap_payroll.php">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Rekap Payroll</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/keuangan/notifikasi_keuangan.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/keuangan/notifikasi_keuangan.php">
                    <i class="fas fa-bell"></i>
                    <span>Notifikasi Keuangan</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/keuangan/audit_logs_keuangan.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/keuangan/audit_logs_keuangan.php">
                    <i class="fas fa-history"></i>
                    <span>Audit Logs Keuangan</span>
                </a>
            </li>
            <?php
        }
        // M:sdm => top-level
        elseif (strpos($normalizedJobTitle, 'sdm') !== false) {
            ?>
            <li class="nav-item <?= isActive('/sdm/dashboard_sdm.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/sdm/dashboard_sdm.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard SDM</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/sdm/koreksi_absensi.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/sdm/koreksi_absensi.php">
                    <i class="fas fa-edit"></i>
                    <span>Koreksi Absensi</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/sdm/manage_guru_karyawan.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/sdm/manage_guru_karyawan.php">
                    <i class="fas fa-users-cog"></i>
                    <span>Kelola Guru/Karyawan</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/sdm/employees.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/sdm/employees.php">
                    <i class="fas fa-money-check-alt"></i>
                    <span>Payroll Anggota</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/sdm/payheads.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/sdm/payheads.php">
                    <i class="fas fa-layer-group"></i>
                    <span>Payheads</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/sdm/laporan_pengajuan_ijin.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/sdm/laporan_pengajuan_ijin.php">
                    <i class="fas fa-envelope-open-text"></i>
                    <span>Laporan Surat Ijin</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/sdm/laporan_jadwal_piket.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/sdm/laporan_jadwal_piket.php">
                    <i class="fas fa-envelope-open-text"></i>
                    <span>Laporan Jadwal Piket</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/sdm/pembuatan_surat.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/sdm/pembuatan_surat.php">
                    <i class="fas fa-envelope"></i>
                    <span>Pembuatan Surat</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/sdm/audit_logs_sdm.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/sdm/audit_logs_sdm.php">
                    <i class="fas fa-history"></i>
                    <span>Audit Logs SDM</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/sdm/notifikasi_sdm.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/sdm/notifikasi_sdm.php">
                    <i class="fas fa-bell"></i>
                    <span>Notifikasi SDM</span>
                </a>
            </li>
            <?php
        }
        // M:kepala sekolah => top-level
        elseif (strpos($normalizedJobTitle, 'kepala sekolah') !== false) {
            ?>
            <li class="nav-item <?= isActive('/kepalasekolah/dashboard_kepala_sekolah.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/kepalasekolah/dashboard_kepala_sekolah.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard Kepala</span>
                </a>
            </li>
            <li class="nav-item <?= isActive('/kepalasekolah/laporan_ijin_ke_kepalasekolah.php'); ?>">
                <a class="nav-link" href="<?= getBaseUrl() ?>/kepalasekolah/laporan_ijin_ke_kepalasekolah.php">
                    <i class="fas fa-envelope"></i>
                    <span>Laporan Ijin</span>
                </a>
            </li>
            <?php
        } 
        else {
            // Role manajerial tidak dikenali
            echo '<div class="sidebar-heading">Status</div>';
            ?>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-question-circle"></i>
                    <span>Menu Manajerial Tidak Dikenal</span>
                </a>
            </li>
            <?php
        }
    }
    ?>

    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">

    <!-- Sidebar Toggler (opsional) -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>
</ul>
<!-- End of Sidebar -->
