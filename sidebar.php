<?php
// File: /payroll_absensi_v2/sidebar.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/helpers.php';

$role      = $_SESSION['role']       ?? '';
$username  = $_SESSION['username']   ?? '';
$nama      = $_SESSION['nama']       ?? '';
$nip       = $_SESSION['nip']        ?? '';
$job_title = $_SESSION['job_title']  ?? '';

// Jika job_title belum ada di session dan NIP tersedia, ambil dari database
if (empty($job_title) && !empty($nip) && isset($conn)) {
    $stmt = $conn->prepare("SELECT job_title FROM anggota_sekolah WHERE nip = ?");
    if ($stmt) {
        $stmt->bind_param("s", $nip);
        $stmt->execute();
        $stmt->bind_result($fetched_job_title);
        if ($stmt->fetch()) {
            $_SESSION['job_title'] = $fetched_job_title;
            $job_title = $fetched_job_title;
        }
        $stmt->close();
    }
}

$nonAdmin = $_SESSION['non_admin_mode'] ?? false;

/**
 * Cek menu aktif.
 */
function isActive(string $menuUrl): string
{
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $basePath    = parse_url(getBaseUrl(), PHP_URL_PATH);
    $targetPath  = rtrim($basePath, '/') . $menuUrl;
    return (strpos($currentPath, $targetPath) === 0) ? 'active' : '';
}

/**
 * Render collapse menu (submenu).
 */
function renderCollapseMenu(string $id, string $iconClass, string $title, array $items): void
{
    $isAnyActive = false;
    foreach ($items as $label => $url) {
        if (isActive($url)) { $isAnyActive = true; break; }
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
        echo '      <a class="collapse-item ' . isActive($url) . '" href="' . getBaseUrl() . htmlspecialchars($url) . '">' . htmlspecialchars($label) . '</a>';
    }
    echo '    </div>';
    echo '  </div>';
    echo '</li>';
}
?>
<!-- ============ STYLE TAMBAHAN ============ -->
<style>
    .sidebar{font-size:.9rem}
    .sidebar .nav-item .nav-link{padding:.65rem 1rem;transition:background-color .2s}
    .sidebar .nav-item .nav-link i{margin-right:6px}
    .sidebar .nav-item.active>.nav-link,
    .sidebar .nav-item.active>.nav-link:hover{background-color:#4e73df;color:#fff;font-weight:600}
    .sidebar .collapse-inner a.collapse-item{padding:.5rem 1rem;font-size:.85rem}
    .sidebar-divider{margin:.5rem 0}
    .sidebar-user-info{color:#fff;font-weight:600;text-align:center;padding:.75rem;border-bottom:1px solid rgba(255,255,255,.3);margin-bottom:.5rem}
    .sidebar-user-info small{font-weight:normal;display:block}
</style>

<!-- ============ SIDEBAR MULAI ============ -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center">
        <div class="sidebar-brand-icon">
            <img src="<?= getBaseUrl(); ?>/assets/img/logo.png" class="img-fluid" style="max-width:50px" alt="Logo">
        </div>
        <div class="sidebar-brand-text mx-2" style="font-size:.8rem;line-height:1.2">Sistem Sekolah<br>Nusaputera</div>
    </a>

    <hr class="sidebar-divider my-0">

    <!-- Panel User -->
    <div class="sidebar-user-info">
        <?php if (in_array($role, ['M','P','TK','Kepala Sekolah'], true)): ?>
            <strong><?= htmlspecialchars($nama ?: $username); ?></strong>
            <small><?= htmlspecialchars($job_title); ?></small>
            <small>NIP: <?= htmlspecialchars($nip); ?></small>
            <small>Role: <?= htmlspecialchars($role); ?></small>
        <?php else: ?>
            <strong>Pengguna</strong>
            <small>Unknown</small>
        <?php endif; ?>
    </div>

    <hr class="sidebar-divider">

    <!-- ---- PUSAT INFO (MENU UNIVERSAL) ---- -->
    <li class="nav-item <?= isActive('/pusat_info.php'); ?>">
        <a class="nav-link" href="<?= getBaseUrl(); ?>/pusat_info.php">
            <i class="fas fa-info-circle"></i>
            <span>Pusat Info</span>
        </a>
    </li>
    <!-- ------------------------------------- -->

    <?php
    // ===== LOGIKA MENU =====
    if ($nonAdmin) {
        // Mode Non-Admin (guru/TK sederhana)
        ?>
        <li class="nav-item <?= isActive('/guru/dashboard_guru.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl(); ?>/guru/dashboard_guru.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/guru/pengajuan_surat_ijin.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl(); ?>/guru/pengajuan_surat_ijin.php">
                <i class="fas fa-paper-plane"></i>
                <span>Ajukan Ijin</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/guru/laporan_surat.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl(); ?>/guru/laporan_surat.php">
                <i class="fas fa-file-alt"></i>
                <span>Laporan Surat</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/guru/list_hari_libur.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl(); ?>/guru/list_hari_libur.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Hari Libur</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/guru/dashboard_jadwal.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl(); ?>/guru/dashboard_jadwal.php">
                <i class="fas fa-calendar-check"></i>
                <span>Jadwal Piket</span>
            </a>
        </li>
        <li class="nav-item <?= isActive('/guru/hasil-slip_gaji.php'); ?>">
            <a class="nav-link" href="<?= getBaseUrl(); ?>/guru/hasil-slip_gaji.php">
                <i class="fas fa-receipt"></i>
                <span>Slip Gaji</span>
            </a>
        </li>
        <?php
    } else {
        // Mode Admin / Full (bedakan role manajerial, keuangan, sdm, kepala, dsb.)
        $normalizedJobTitle = strtolower($job_title);

        if ($role !== 'M') {
            // Role P / TK / Kepala sekolah non-manajerial
            if ($role === 'Kepala Sekolah' || ($role === 'P' && strpos($normalizedJobTitle,'kepala sekolah')!==false)) {
                // Menu Kepala Sekolah
                ?>
                <li class="nav-item <?= isActive('/kepalasekolah/dashboard_kepala_sekolah.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/kepalasekolah/dashboard_kepala_sekolah.php">
                        <i class="fas fa-home"></i><span>Dashboard Kepala</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/kepalasekolah/laporan_ijin_ke_kepalasekolah.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/kepalasekolah/laporan_ijin_ke_kepalasekolah.php">
                        <i class="fas fa-envelope"></i><span>Laporan Ijin</span>
                    </a>
                </li>
                <?php
            } elseif (in_array($role, ['P','TK'], true)) {
                // Menu Guru/TK
                ?>
                <li class="nav-item <?= isActive('/guru/dashboard_guru.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/guru/dashboard_guru.php">
                        <i class="fas fa-home"></i><span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/guru/pengajuan_surat_ijin.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/guru/pengajuan_surat_ijin.php">
                        <i class="fas fa-paper-plane"></i><span>Ajukan Ijin</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/guru/laporan_surat.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/guru/laporan_surat.php">
                        <i class="fas fa-file-alt"></i><span>Laporan Surat</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/guru/list_hari_libur.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/guru/list_hari_libur.php">
                        <i class="fas fa-calendar-alt"></i><span>Hari Libur</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/guru/dashboard_jadwal.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/guru/dashboard_jadwal.php">
                        <i class="fas fa-calendar-check"></i><span>Jadwal Piket</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/guru/hasil-slip_gaji.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/guru/hasil-slip_gaji.php">
                        <i class="fas fa-receipt"></i><span>Slip Gaji</span>
                    </a>
                </li>
                <?php
            } else {
                // Role P/TK lain atau tak terduga
                ?>
                <li class="nav-item">
                    <a class="nav-link" href="#">
                        <i class="fas fa-question-circle"></i><span>Role tidak dikenal</span>
                    </a>
                </li>
                <?php
            }
        } else {
            // Role M (Manajerial)
            if ($role === 'M' && strpos($normalizedJobTitle,'superadmin') !== false) {
    // === SUPERADMIN ===

    // 1) Dashboard Superadmin
    ?>
    <li class="nav-item <?= isActive('/superadmin/dashboard_superadmin.php'); ?>">
        <a class="nav-link" href="<?= getBaseUrl(); ?>/superadmin/dashboard_superadmin.php">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard Superadmin</span>
        </a>
    </li>
    <?php

    // 2) Profil Superadmin
    ?>
    <li class="nav-item <?= isActive('/superadmin/manage_manajerial.php'); ?>">
        <a class="nav-link" href="<?= getBaseUrl(); ?>/superadmin/manage_manajerial.php">
            <i class="fas fa-user-circle"></i>
            <span>Manage Manajerial</span>
        </a>
    </li>
    <?php

    // 3) Kelola Sistem (fitur khusus Superadmin)
    $kelolaSistemItems = [
        'Backup Database' => '/superadmin/backup_database.php',
        'Audit Logs'      => '/superadmin/logs.php',
        'Error Logs'      => '/superadmin/error_log.php',
    ];
    renderCollapseMenu(
        'collapseKelolaSistem',
        'fas fa-cogs',
        'Kelola Sistem',
        $kelolaSistemItems
    );

    // 4) Sekedar lihat dashboard SDM & Keuangan (tanpa akses fitur lain)
    ?>
    <li class="nav-item <?= isActive('/sdm/dashboard_sdm.php'); ?>">
        <a class="nav-link" href="<?= getBaseUrl(); ?>/sdm/dashboard_sdm.php">
            <i class="fas fa-users-cog"></i>
            <span>Dashboard SDM</span>
        </a>
    </li>
    <li class="nav-item <?= isActive('/keuangan/dashboard_keuangan.php'); ?>">
        <a class="nav-link" href="<?= getBaseUrl(); ?>/keuangan/dashboard_keuangan.php">
            <i class="fas fa-wallet"></i>
            <span>Dashboard Keuangan</span>
        </a>
    </li>
    <?php
}
            elseif (strpos($normalizedJobTitle,'keuangan')!==false) {
                // Keuangan saja
                ?>
                <li class="nav-item <?= isActive('/keuangan/dashboard_keuangan.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/keuangan/dashboard_keuangan.php">
                        <i class="fas fa-chart-line"></i><span>Dashboard Keuangan</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/keuangan/list_payroll.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/keuangan/list_payroll.php">
                        <i class="fas fa-list-ul"></i><span>List Payroll</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/keuangan/payroll_history.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/keuangan/payroll_history.php">
                        <i class="fas fa-history"></i><span>History Payroll</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/keuangan/rekap_payroll.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/keuangan/rekap_payroll.php">
                        <i class="fas fa-file-invoice-dollar"></i><span>Rekap Payroll</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/keuangan/potongan_tidak_hadir.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/keuangan/potongan_tidak_hadir.php">
                        <i class="fas fa-file-invoice-dollar"></i><span>Potongan Ketidakhadiran</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/keuangan/manage_ranking_kenaikan.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/keuangan/manage_ranking_kenaikan.php">
                        <i class="fas fa-file-invoice-dollar"></i><span>Ranking Kenaikan Gaji</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/keuangan/audit_logs_keuangan.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/keuangan/audit_logs_keuangan.php">
                        <i class="fas fa-history"></i><span>Audit Logs Keuangan</span>
                    </a>
                </li>
                <?php
            }
            elseif (strpos($normalizedJobTitle,'sdm')!==false) {
                // SDM saja
                ?>
                <li class="nav-item <?= isActive('/sdm/dashboard_sdm.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/sdm/dashboard_sdm.php">
                        <i class="fas fa-tachometer-alt"></i><span>Dashboard SDM</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/sdm/koreksi_absensi.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/sdm/koreksi_absensi.php">
                        <i class="fas fa-edit"></i><span>Koreksi Absensi</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/sdm/manage_guru_karyawan.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/sdm/manage_guru_karyawan.php">
                        <i class="fas fa-users-cog"></i><span>Kelola Guru/Karyawan</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/sdm/employees.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/sdm/employees.php">
                        <i class="fas fa-money-check-alt"></i><span>Payroll Anggota</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/sdm/payheads.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/sdm/payheads.php">
                        <i class="fas fa-layer-group"></i><span>Komponen Gaji</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/sdm/laporan_pengajuan_ijin.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/sdm/laporan_pengajuan_ijin.php">
                        <i class="fas fa-envelope-open-text"></i><span>Laporan Surat Ijin</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/sdm/laporan_jadwal_piket.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/sdm/laporan_jadwal_piket.php">
                        <i class="fas fa-calendar-week"></i><span>Laporan Jadwal Piket</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/sdm/pembuatan_surat.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/sdm/pembuatan_surat.php">
                        <i class="fas fa-envelope"></i><span>Pembuatan Surat</span>
                    </a>
                </li>
                <li class="nav-item <?= isActive('/sdm/audit_logs_sdm.php'); ?>">
                    <a class="nav-link" href="<?= getBaseUrl(); ?>/sdm/audit_logs_sdm.php">
                        <i class="fas fa-history"></i><span>Audit Logs SDM</span>
                    </a>
                </li>
                <?php
            }
            else {
                // Fallback untuk manajerial lainnya
                echo '<div class="sidebar-heading">Status</div>';
                ?>
                <li class="nav-item">
                    <a class="nav-link" href="#">
                        <i class="fas fa-question-circle"></i><span>Menu Manajerial Tidak Dikenal</span>
                    </a>
                </li>
                <?php
            }
        }
    }
    ?>

    <hr class="sidebar-divider d-none d-md-block">

    <!-- Sidebar Toggler -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>
</ul>
<!-- ============ SIDEBAR SELESAI ============ -->