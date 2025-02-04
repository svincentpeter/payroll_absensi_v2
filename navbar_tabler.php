<?php
// /payroll_absensi_v2/navbar_tabler.php

// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definisikan konstanta BASE_URL untuk memudahkan referensi file
if (!defined('BASE_URL')) {
    define('BASE_URL', '/payroll_absensi_v2');
}

// Ambil informasi pengguna dari session
$role     = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? '';
$nama     = $_SESSION['nama'] ?? '';
$nip      = $_SESSION['nip'] ?? '';

// Fungsi sederhana untuk menghasilkan submenu dropdown
function renderDropdownItem($label, $url) {
    echo '<li><a class="dropdown-item" href="' . BASE_URL . htmlspecialchars($url) . '">' . htmlspecialchars($label) . '</a></li>';
}
?>
<!-- Navbar dengan tampilan Tabler / Bootstrap 5 -->
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
  <div class="container-fluid">
    <!-- Brand -->
    <a class="navbar-brand" href="<?= BASE_URL ?>/index.php">
      <!-- Logo: pastikan file logo ada di folder dist/img -->
      <img src="<?= BASE_URL ?>/dist/img/Logo.png" alt="Logo" class="navbar-brand-image" style="height: 30px;">
      Sistem Nusaputera
    </a>
    <!-- Toggler untuk tampilan mobile -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" 
            aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
       <span class="navbar-toggler-icon"></span>
    </button>
    <!-- Konten Navbar -->
    <div class="collapse navbar-collapse" id="navbarContent">
      <!-- Menu Utama (kiri) -->
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php if ($role === 'superadmin' || $role === 'keuangan'): ?>
          <li class="nav-item dropdown">
             <a class="nav-link dropdown-toggle" href="#" id="navbarKeuangan" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                 <i class="fas fa-wallet"></i> Dashboard Keuangan
             </a>
             <ul class="dropdown-menu" aria-labelledby="navbarKeuangan">
                <?php
                renderDropdownItem('Dashboard Keuangan', '/payroll/keuangan/dashboard_keuangan.php');
                renderDropdownItem('Payroll Anggota', '/payroll/keuangan/employees.php');
                renderDropdownItem('Payheads', '/payroll/keuangan/payheads.php');
                renderDropdownItem('Rekap Absensi', '/payroll/keuangan/rekap_absensi.php');
                renderDropdownItem('History Payroll', '/payroll/keuangan/payroll_history.php');
                renderDropdownItem('Rekap Payroll', '/payroll/keuangan/rekap_payroll.php');
                renderDropdownItem('Audit Logs Keuangan', '/payroll/keuangan/audit_logs_keuangan.php');
                ?>
             </ul>
          </li>
        <?php endif; ?>

        <?php if ($role === 'superadmin'): ?>
          <li class="nav-item dropdown">
             <a class="nav-link dropdown-toggle" href="#" id="navbarKelolaSistem" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                 <i class="fas fa-cogs"></i> Kelola Sistem
             </a>
             <ul class="dropdown-menu" aria-labelledby="navbarKelolaSistem">
                <?php
                renderDropdownItem('Kelola User', '/payroll/superadmin/kelola_user.php');
                renderDropdownItem('Audit Logs', '/payroll/superadmin/logs.php');
                ?>
             </ul>
          </li>
          <li class="nav-item dropdown">
             <a class="nav-link dropdown-toggle" href="#" id="navbarLaporan" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                 <i class="fas fa-file-invoice-dollar"></i> Laporan
             </a>
             <ul class="dropdown-menu" aria-labelledby="navbarLaporan">
                <?php renderDropdownItem('Laporan Gaji', '/payroll/laporan_gaji.php'); ?>
             </ul>
          </li>
          <li class="nav-item dropdown">
             <a class="nav-link dropdown-toggle" href="#" id="navbarSDM" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                 <i class="fas fa-users-cog"></i> Dashboard SDM
             </a>
             <ul class="dropdown-menu" aria-labelledby="navbarSDM">
                <?php
                renderDropdownItem('Dashboard SDM', '/absensi/sdm/dashboard_sdm.php');
                renderDropdownItem('Upload Absensi', '/absensi/sdm/upload_absensi.php');
                renderDropdownItem('Laporan Absensi', '/absensi/sdm/laporan_absensi.php');
                renderDropdownItem('Koreksi Absensi', '/absensi/sdm/koreksi_absensi.php');
                renderDropdownItem('Pengaturan Gaji Pokok', '/absensi/sdm/manage_salary_indices.php');
                renderDropdownItem('Kelola Guru/Karyawan', '/absensi/sdm/manage_guru_karyawan.php');
                renderDropdownItem('Hari Libur', '/absensi/sdm/holidays.php');
                renderDropdownItem('Laporan Surat Ijin', '/absensi/sdm/laporan_pengajuan_ijin.php');
                ?>
             </ul>
          </li>
        <?php endif; ?>

        <?php if ($role === 'sdm'): ?>
          <li class="nav-item">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/sdm/dashboard_sdm.php">
              <i class="fas fa-tachometer-alt"></i> Dashboard SDM
            </a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="navbarKelolaAbsensi" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-users"></i> Kelola Absensi
            </a>
            <ul class="dropdown-menu" aria-labelledby="navbarKelolaAbsensi">
              <?php
              renderDropdownItem('Upload Absensi', '/absensi/sdm/upload_absensi.php');
              renderDropdownItem('Laporan Absensi', '/absensi/sdm/laporan_absensi.php');
              renderDropdownItem('Koreksi Absensi', '/absensi/sdm/koreksi_absensi.php');
              renderDropdownItem('Tambah Hari Libur', '/absensi/sdm/tambah_hari_libur.php');
              renderDropdownItem('Tambah Jadwal Piket', '/absensi/sdm/tambah_jadwal_piket.php');
              ?>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="navbarKelolaGuru" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-users"></i> Kelola Guru
            </a>
            <ul class="dropdown-menu" aria-labelledby="navbarKelolaGuru">
              <?php
              renderDropdownItem('Kelola Guru/Karyawan', '/absensi/sdm/manage_guru_karyawan.php');
              renderDropdownItem('Lihat Password Guru', '/absensi/sdm/lihat_password_guru.php');
              renderDropdownItem('Pengajuan Surat Ijin', '/absensi/sdm/laporan_pengajuan_ijin.php');
              ?>
            </ul>
          </li>
        <?php endif; ?>

        <?php if ($role === 'guru'): ?>
          <li class="nav-item">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/guru/ganti_password_guru.php">
              <i class="fas fa-key"></i> Ganti Password
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/guru/pengajuan_surat_ijin.php">
              <i class="fas fa-envelope"></i> Ajukan Permohonan Ijin
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/guru/list_hari_libur.php">
              <i class="fas fa-calendar-alt"></i> List Hari Libur
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="<?= BASE_URL ?>/absensi/guru/dashboard_guru.php">
              <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
          </li>
        <?php endif; ?>
      </ul>
      <!-- Bagian kanan navbar: User info dan Logout -->
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
         <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="navbarUser" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?php 
                  // Tampilkan nama pengguna sesuai role
                  if ($role === 'guru' || $role === 'karyawan') {
                      echo htmlspecialchars($nama);
                  } elseif ($username) {
                      echo htmlspecialchars($username);
                  } else {
                      echo "Pengguna";
                  }
                ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarUser">
                <li><a class="dropdown-item" href="<?= BASE_URL ?>/profile.php">Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
         </li>
      </ul>
    </div>
  </div>
</nav>
