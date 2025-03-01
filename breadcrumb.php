<?php
// File: /payroll_absensi_v2/breadcrumb.php

// Pastikan session sudah dimulai dan BASE_URL sudah didefinisikan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('BASE_URL')) {
    define('BASE_URL', '/payroll_absensi_v2');
}

// Dapatkan URL halaman saat ini (hanya path)
$currentUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

/*
  Definisikan struktur menu untuk breadcrumb.
  Setiap grup memiliki:
    - 'url'  : URL utama grup (sebagai tautan ketika grup ditampilkan di breadcrumb)
    - 'items': array item dengan key sebagai label dan value sebagai URL.
*/
$menuStructure = [
    // Grup untuk Kelola Sistem (superadmin)
    'Kelola Sistem' => [
         'url' => '/payroll/superadmin/kelola_user.php',
         'items' => [
              'Backup Database' => '/payroll/superadmin/backup_database.php',
              'Audit Logs'      => '/payroll/superadmin/logs.php'
         ]
    ],
    // Grup untuk Laporan (superadmin)
    'Laporan' => [
         'url' => '/payroll/laporan_gaji.php',
         'items' => [
              'Laporan Gaji' => '/payroll/laporan_gaji.php'
         ]
    ],
    // Grup untuk Role SDM (untuk superadmin dan SDM)
    'SDM' => [
         'url' => '/absensi/sdm/dashboard_sdm.php',
         'items' => [
             'Dashboard SDM'          => '/absensi/sdm/dashboard_sdm.php',
             'Koreksi Absensi'        => '/absensi/sdm/koreksi_absensi.php',
             'Kelola Guru/Karyawan'   => '/absensi/sdm/manage_guru_karyawan.php',
             'Payroll Anggota'        => '/absensi/sdm/employees.php',
             'Payheads'               => '/absensi/sdm/payheads.php',
             'Laporan Surat Ijin'     => '/absensi/sdm/laporan_pengajuan_ijin.php',
             'Pembuatan Surat'        => '/absensi/sdm/pembuatan_surat.php',
             'Audit Logs SDM'         => '/absensi/sdm/audit_logs_sdm.php',
             'Notifikasi SDM'         => '/absensi/sdm/notifikasi_sdm.php'
         ]
    ],
    // Grup untuk Role Keuangan (untuk superadmin dan keuangan)
    'Keuangan' => [
         'url' => '/payroll/keuangan/dashboard_keuangan.php',
         'items' => [
             'Dashboard Keuangan'  => '/payroll/keuangan/dashboard_keuangan.php',
             'List Payroll'        => '/payroll/keuangan/list_payroll.php',
             'History Payroll'     => '/payroll/keuangan/payroll_history.php',
             'Rekap Payroll'       => '/payroll/keuangan/rekap_payroll.php',
             'Audit Logs Keuangan' => '/payroll/keuangan/audit_logs_keuangan.php',
             'Notifikasi Keuangan' => '/payroll/keuangan/notifikasi_keuangan.php'
         ]
    ],
    // Grup untuk Guru/Pendidik (role P dan TK)
    'Dashboard Guru' => [
         'url' => '/absensi/guru/dashboard_guru.php',
         'items' => [
             'Dashboard'              => '/absensi/guru/dashboard_guru.php',
             'Ganti Password'         => '/absensi/guru/ganti_password_guru.php',
             'Ajukan Permohonan Ijin'   => '/absensi/guru/pengajuan_surat_ijin.php',
             'Laporan Surat'          => '/absensi/guru/laporan_surat.php',
             'List Hari Libur'        => '/absensi/guru/list_hari_libur.php',
             'Jadwal Piket'           => '/absensi/guru/dashboard_jadwal.php'
         ]
    ],
    // Grup untuk Kepala Sekolah
    'Kepala Sekolah' => [
         'url' => '/absensi/kepalasekolah/dashboard_kepala_sekolah.php',
         'items' => [
             'Dashboard'            => '/absensi/kepalasekolah/dashboard_kepala_sekolah.php',
             'Laporan Surat Ijin'   => '/absensi/kepalasekolah/laporan_ijin_ke_kepalasekolah.php'
         ]
    ]
];

// Inisialisasi variabel untuk menyimpan grup dan item aktif
$activeGroup = null;
$activeItemLabel = null;
$activeItemUrl = null;

// Lakukan pencarian dalam struktur menu berdasarkan URL saat ini
foreach ($menuStructure as $groupLabel => $groupData) {
    foreach ($groupData['items'] as $itemLabel => $itemUrl) {
         // Bandingkan dengan URL saat ini; perhatikan BASE_URL agar konsisten
         if (strpos($currentUrl, BASE_URL . $itemUrl) === 0 || strpos($currentUrl, $itemUrl) === 0) {
             $activeGroup = $groupLabel;
             $activeItemLabel = $itemLabel;
             $activeItemUrl = $itemUrl;
             break 2; // Keluar dari kedua perulangan jika sudah ditemukan
         }
    }
}

// Susun array breadcrumb
$breadcrumb = [];

// Item pertama: Home (misalnya halaman utama sistem)
$breadcrumb[] = [
    'label' => 'Home',
    'url'   => '/index.php'
];

// Jika ditemukan grup aktif, tambahkan grup dan item aktif ke breadcrumb
if ($activeGroup !== null) {
    $groupUrl = isset($menuStructure[$activeGroup]['url']) ? $menuStructure[$activeGroup]['url'] : '#';
    $breadcrumb[] = [
         'label' => $activeGroup,
         'url'   => $groupUrl
    ];
    if ($activeItemLabel !== null) {
         $breadcrumb[] = [
              'label'  => $activeItemLabel,
              'active' => true
         ];
    }
} else {
    // Jika tidak ditemukan, tampilkan "Halaman Aktif" sebagai default
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
                    <li class="breadcrumb-item">
                        <a href="<?= BASE_URL . $item['url'] ?>">
                            <?= htmlspecialchars($item['label']) ?>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ol>
    </nav>
</div>
