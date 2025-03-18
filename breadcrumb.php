<?php
// File: /payroll_absensi_v2/breadcrumb.php

// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/helpers.php';

// Dapatkan path base dari getBaseUrl (misal "/payroll_absensi_v2")
$basePath = parse_url(getBaseUrl(), PHP_URL_PATH);

// Dapatkan URL (path) halaman saat ini, misalnya "/payroll_absensi_v2/superadmin/dashboard_superadmin.php"
$currentUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

/**
 * Definisikan struktur menu untuk breadcrumb.
 * Setiap grup memiliki:
 *   - 'url'   : URL utama grup (relatif, tanpa subfolder)
 *   - 'items' : array item => [label => urlRelatif].
 */
$menuStructure = [
    // Grup Superadmin
    'Superadmin' => [
        'url' => '/superadmin/dashboard_superadmin.php',
        'items' => [
            'Dashboard Superadmin' => '/superadmin/dashboard_superadmin.php',
            'Backup Database'      => '/superadmin/backup_database.php',
            'Audit Logs'           => '/superadmin/logs.php'
        ]
    ],
    // Grup SDM
    'SDM' => [
        'url' => '/sdm/dashboard_sdm.php',
        'items' => [
            'Dashboard SDM'        => '/sdm/dashboard_sdm.php',
            'Koreksi Absensi'      => '/sdm/koreksi_absensi.php',
            'Kelola Guru/Karyawan' => '/sdm/manage_guru_karyawan.php',
            'Payroll Anggota'      => '/sdm/employees.php',
            'Payheads'             => '/sdm/payheads.php',
            'Laporan Surat Ijin'   => '/sdm/laporan_pengajuan_ijin.php',
            'Pembuatan Surat'      => '/sdm/pembuatan_surat.php',
            'Audit Logs SDM'       => '/sdm/audit_logs_sdm.php',
            'Notifikasi SDM'       => '/sdm/notifikasi_sdm.php'
        ]
    ],
    // Grup Keuangan
    'Keuangan' => [
        'url' => '/keuangan/dashboard_keuangan.php',
        'items' => [
            'Dashboard Keuangan'  => '/keuangan/dashboard_keuangan.php',
            'List Payroll'        => '/keuangan/list_payroll.php',
            'History Payroll'     => '/keuangan/payroll_history.php',
            'Rekap Payroll'       => '/keuangan/rekap_payroll.php',
            'Audit Logs Keuangan' => '/keuangan/audit_logs_keuangan.php',
            'Notifikasi Keuangan' => '/keuangan/notifikasi_keuangan.php'
        ]
    ],
    // Grup Guru
    'Dashboard Guru' => [
        'url' => '/guru/dashboard_guru.php',
        'items' => [
            'Dashboard'            => '/guru/dashboard_guru.php',
            'Ganti Password'       => '/guru/ganti_password_guru.php',
            'Ajukan Permohonan Ijin' => '/guru/pengajuan_surat_ijin.php',
            'Laporan Surat'        => '/guru/laporan_surat.php',
            'List Hari Libur'      => '/guru/list_hari_libur.php',
            'Jadwal Piket'         => '/guru/dashboard_jadwal.php'
        ]
    ],
    // Grup Kepala Sekolah
    'Kepala Sekolah' => [
        'url' => '/kepalasekolah/dashboard_kepala_sekolah.php',
        'items' => [
            'Dashboard'          => '/kepalasekolah/dashboard_kepala_sekolah.php',
            'Laporan Surat Ijin' => '/kepalasekolah/laporan_ijin_ke_kepalasekolah.php'
        ]
    ]
];

// Inisialisasi variabel penyimpan grup dan item aktif
$activeGroup      = null;
$activeItemLabel  = null;
$activeItemUrl    = null;

// Cari dalam struktur menu berdasarkan URL saat ini
// Perhatikan bahwa $itemUrl adalah path relatif (misal "/superadmin/logs.php")
// sedangkan $basePath adalah "/payroll_absensi_v2" (atau subfolder lain).
// Maka, gabungan $basePath . $itemUrl => "/payroll_absensi_v2/superadmin/logs.php"
foreach ($menuStructure as $groupLabel => $groupData) {
    foreach ($groupData['items'] as $itemLabel => $itemUrl) {
        $fullItemPath = $basePath . $itemUrl; 
        // Cek apakah $currentUrl diawali dengan $fullItemPath
        if (strpos($currentUrl, $fullItemPath) === 0) {
            $activeGroup     = $groupLabel;
            $activeItemLabel = $itemLabel;
            $activeItemUrl   = $itemUrl;
            break 2;
        }
    }
}

// Susun array breadcrumb
$breadcrumb = [];

// Item pertama: Home (bisa diarahkan ke index.php di root subfolder)
$breadcrumb[] = [
    'label' => 'Home',
    'url'   => '/index.php' // relatif, nanti digabungkan dengan $basePath saat ditampilkan
];

// Jika ditemukan grup aktif, tambahkan grup dan item aktif
if ($activeGroup !== null) {
    // Ambil URL grup
    $groupUrl = $menuStructure[$activeGroup]['url'] ?? '#';
    $breadcrumb[] = [
        'label' => $activeGroup,
        'url'   => $groupUrl
    ];
    // Tambahkan item aktif jika ada
    if ($activeItemLabel !== null) {
        $breadcrumb[] = [
            'label'  => $activeItemLabel,
            'active' => true
        ];
    }
} else {
    // Jika tidak ditemukan, asumsikan "Halaman Aktif" sebagai default
    $breadcrumb[] = [
        'label'  => 'Halaman Aktif',
        'active' => true
    ];
}
?>
<!-- Tampilkan Breadcrumb -->
<div class="container-fluid">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <?php foreach ($breadcrumb as $item): ?>
                <?php if (!empty($item['active'])): ?>
                    <li class="breadcrumb-item active" aria-current="page">
                        <?= htmlspecialchars($item['label']) ?>
                    </li>
                <?php else: ?>
                    <?php 
                    // Buat URL absolut untuk link breadcrumb
                    // (Base path + item url). Contoh: "/payroll_absensi_v2" + "/superadmin/dashboard_superadmin.php"
                    $fullBreadcrumbUrl = $basePath . ($item['url'] ?? '/');
                    ?>
                    <li class="breadcrumb-item">
                        <a href="<?= htmlspecialchars($fullBreadcrumbUrl) ?>">
                            <?= htmlspecialchars($item['label']) ?>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ol>
    </nav>
</div>
