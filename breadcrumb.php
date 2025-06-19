<?php
// File: /payroll_absensi_v2/breadcrumb.php

// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/helpers.php';

// Dapatkan path base (misal "/payroll_absensi_v2")
$basePath   = parse_url(getBaseUrl(), PHP_URL_PATH);
// Dapatkan URL (path) halaman saat ini
$currentUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

/**
 * Struktur lengkap menu untuk breadcrumb.
 * 'url' adalah path relatif ke folder root proyek,
 * 'items' adalah daftar [Label => URL relatif].
 */
$menuStructure = [
    'Superadmin' => [
        'url'   => '/superadmin/dashboard_superadmin.php',
        'items' => [
            'Dashboard Superadmin' => '/superadmin/dashboard_superadmin.php',
            'Manage Manajerial' => '/superadmin/manage_manajerial.php',
            'Backup Database'      => '/superadmin/backup_database.php',
            'Error Log'            => '/superadmin/error_log.php',
            'Logs'                 => '/superadmin/logs.php',

        ],
    ],
    'SDM' => [
        'url'   => '/sdm/dashboard_sdm.php',
        'items' => [
            'Dashboard SDM'           => '/sdm/dashboard_sdm.php',
            'Audit Logs SDM'          => '/sdm/audit_logs_sdm.php',
            'Dashboard Employees'     => '/sdm/employees.php',
            'History Anggota Sekolah' => '/sdm/history_anggota_sekolah.php',
            'Holidays'                => '/sdm/holidays.php',
            'Input Jadwal Piket Guru' => '/sdm/input_jadwal_piket_guru.php',
            'Tambah Jadwal Piket'     => '/sdm/tambah_jadwal_piket.php',
            'Koreksi Absensi'         => '/sdm/koreksi_absensi.php',
            'Laporan Jadwal Piket'    => '/sdm/laporan_jadwal_piket.php',
            'Laporan Pengajuan Ijin'  => '/sdm/laporan_pengajuan_ijin.php',
            'Manage Groups'           => '/sdm/manage_groups.php',
            'Manage Guru Karyawan'    => '/sdm/manage_guru_karyawan.php',
            'Manage Salary Indices'   => '/sdm/manage_salary_indices.php',
            'Payheads'                => '/sdm/payheads.php',
            'Payroll Page'            => '/sdm/payroll_page.php',
            'Pembuatan Surat'         => '/sdm/pembuatan_surat.php',
            'Template Surat'          => '/sdm/template_surat.php',
            'Upload Absensi'          => '/sdm/upload_absensi.php',
            'Notifikasi SDM'          => '/sdm/notifikasi_sdm.php',
        ],
    ],
    'Keuangan' => [
        'url'   => '/keuangan/dashboard_keuangan.php',
        'items' => [
            'Dashboard Keuangan'       => '/keuangan/dashboard_keuangan.php',
            'Audit Logs Keuangan'      => '/keuangan/audit_logs_keuangan.php',
            'List Payroll'             => '/keuangan/list_payroll.php',
            'Manage Salary'            => '/keuangan/manage-salary.php',
            'Payroll Details'          => '/keuangan/payroll-details.php',
            'Payroll History'          => '/keuangan/payroll_history.php',
            'Rekap Payroll'            => '/keuangan/rekap_payroll.php',
            'Rekap Payroll Details'    => '/keuangan/rekap_payroll_details.php',
            'Rekap Payroll Jenjang'    => '/keuangan/rekap_payroll_jenjang.php',
        ],
    ],
    'Anggota' => [
        'url'   => '/guru/dashboard_guru.php',
        'items' => [
            'Dashboard Guru'           => '/guru/dashboard_guru.php',
            'Dashboard Jadwal'         => '/guru/dashboard_jadwal.php',
            'Hasil Slip Gaji'          => '/guru/hasil-slip_gaji.php',
            'Laporan Jadwal Piket'     => '/guru/laporan_jadwal_piket.php',
            'Laporan Surat'            => '/guru/laporan_surat.php',
            'List Hari Libur'          => '/guru/list_hari_libur.php',
            'Payroll Details'          => '/guru/payroll-details.php',
            'Pengajuan Surat Ijin'     => '/guru/pengajuan_surat_ijin.php',
            'Request Tukar Jadwal'     => '/guru/request_tukar_jadwal.php',
            'Update Absensi'           => '/guru/update_absensi.php',
        ],
    ],
    'Kepala Sekolah' => [
        'url'   => '/kepalasekolah/dashboard_kepala_sekolah.php',
        'items' => [
            'Dashboard Kepala Sekolah'      => '/kepalasekolah/dashboard_kepala_sekolah.php',
            'Laporan Ijin ke Kepala Sekolah' => '/kepalasekolah/laporan_ijin_ke_kepalasekolah.php',
        ],
    ],
];

// Tambahkan ikon untuk setiap grup (Font Awesome 5)
$groupIcons = [
    'Superadmin'      => 'fa-user-shield',
    'SDM'             => 'fa-users-cog',
    'Keuangan'        => 'fa-coins',
    'Anggota'         => 'fa-user-tie',
    'Kepala Sekolah'  => 'fa-user-graduate',
];

// Tentukan grup dan item aktif
$activeGroup     = null;
$activeItemLabel = null;

foreach ($menuStructure as $groupLabel => $groupData) {
    foreach ($groupData['items'] as $itemLabel => $itemUrl) {
        $fullPath = $basePath . $itemUrl;
        if (strpos($currentUrl, $fullPath) === 0) {
            $activeGroup     = $groupLabel;
            $activeItemLabel = $itemLabel;
            break 2;
        }
    }
}

// Susun breadcrumb
$breadcrumb = [
    [
        'label' => 'Home',
        'url'   => '/index.php',
    ],
];

if ($activeGroup !== null) {
    // Grup
    $breadcrumb[] = [
        'label' => $activeGroup,
        'url'   => $menuStructure[$activeGroup]['url'],
    ];
    // Item aktif
    $breadcrumb[] = [
        'label'  => $activeItemLabel,
        'active' => true,
    ];
} else {
    // Default jika tidak ketemu
    $breadcrumb[] = [
        'label'  => 'Halaman Aktif',
        'active' => true,
    ];
}
?>
<!-- Pastikan Font Awesome sudah di-include di layout utama -->
<!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"> -->

<style>
.breadcrumb {
    background-color: #fff;
    padding: 0.5rem 1rem;
    border-radius: .375rem;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
.breadcrumb-item + .breadcrumb-item::before {
    content: "\f054";
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
    color: #6c757d;
    margin: 0 0.5rem;
}
.breadcrumb-item a {
    color: #007bff;
    text-decoration: none;
}
.breadcrumb-item a:hover {
    text-decoration: underline;
}
.breadcrumb-item.active {
    color: #495057;
    font-weight: 600;
}
</style>

<div class="container-fluid">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <?php foreach ($breadcrumb as $idx => $item): ?>
                <li class="breadcrumb-item <?= !empty($item['active']) ? 'active' : '' ?>"
                    <?= !empty($item['active']) ? 'aria-current="page"' : '' ?>>
                    <?php if (empty($item['active'])): ?>
                        <a href="<?= htmlspecialchars($basePath . $item['url']) ?>">
                            <?php if ($idx === 0): ?>
                                <i class="fas fa-home me-1"></i>
                            <?php elseif ($idx === 1 && $activeGroup && isset($groupIcons[$activeGroup])): ?>
                                <i class="fas <?= $groupIcons[$activeGroup] ?> me-1"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($item['label']) ?>
                        </a>
                    <?php else: ?>
                        <i class="fas fa-file-alt me-1"></i>
                        <?= htmlspecialchars($item['label']) ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
</div>
