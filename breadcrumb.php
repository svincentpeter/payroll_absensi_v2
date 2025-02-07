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
  Definisikan struktur menu untuk breadcrumb. Sesuaikan dengan data sidebar Anda.
  Setiap grup memiliki:
    - 'url'  : URL utama grup (sebagai tautan ketika grup ditampilkan di breadcrumb)
    - 'items': array item dengan key sebagai label dan value sebagai URL.
*/
$menuStructure = [
    // Grup untuk Keuangan (digunakan untuk role "keuangan" dan "superadmin")
    'Keuangan' => [
         'url' => '/payroll/keuangan/dashboard_keuangan.php',
         'items' => [
             'Dashboard Keuangan'   => '/payroll/keuangan/dashboard_keuangan.php',
             'Payroll Anggota'      => '/payroll/keuangan/employees.php',
             'Payheads'             => '/payroll/keuangan/payheads.php',
             'Rekap Absensi'        => '/payroll/keuangan/rekap_absensi.php',
             'History Payroll'      => '/payroll/keuangan/payroll_history.php',
             'Rekap Payroll'        => '/payroll/keuangan/rekap_payroll.php',
             'Audit Logs Keuangan'  => '/payroll/keuangan/audit_logs_keuangan.php'
         ]
    ],
    // Grup untuk Kelola Sistem (superadmin)
    'Kelola Sistem' => [
         'url' => '/payroll/superadmin/kelola_user.php',
         'items' => [
              'Kelola User' => '/payroll/superadmin/kelola_user.php',
              'Audit Logs'  => '/payroll/superadmin/logs.php'
         ]
    ],
    // Grup untuk Laporan (superadmin)
    'Laporan' => [
         'url' => '/payroll/laporan_gaji.php',
         'items' => [
              'Laporan Gaji' => '/payroll/laporan_gaji.php'
         ]
    ],
    // Grup untuk SDM
    'SDM' => [
         'url' => '/absensi/sdm/dashboard_sdm.php',
         'items' => [
             'Dashboard SDM'          => '/absensi/sdm/dashboard_sdm.php',
             'Upload Absensi'         => '/absensi/sdm/upload_absensi.php',
             'Review Absensi'        => '/absensi/sdm/koreksi_absensi.php',
             'Tambah Hari Libur'      => '/absensi/sdm/tambah_hari_libur.php',
             'Tambah Jadwal Piket'    => '/absensi/sdm/tambah_jadwal_piket.php',
             'Kelola Guru/Karyawan'   => '/absensi/sdm/manage_guru_karyawan.php',
             'Lihat Password Guru'    => '/absensi/sdm/lihat_password_guru.php',
             'Pengajuan Surat Ijin'   => '/absensi/sdm/laporan_pengajuan_ijin.php'
         ]
    ],
    // Grup untuk Guru
    'Dashboard Guru' => [
         'url' => '/absensi/guru/dashboard_guru.php',
         'items' => [
             'Ganti Password'         => '/absensi/guru/ganti_password_guru.php',
             'Ajukan Permohonan Ijin'   => '/absensi/guru/pengajuan_surat_ijin.php',
             'List Hari Libur'        => '/absensi/guru/list_hari_libur.php',
             'Kembali ke Dashboard'     => '/absensi/guru/dashboard_guru.php'
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
