<?php
// File: notifikasi.php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/koneksi.php';

// Pastikan session sudah dimulai
start_session_safe();

/**
 * Fungsi pembantu untuk mengonversi (role, job_title) menjadi "full role".
 * Misalnya, (M, 'Keuangan') => M:keuangan, (M, 'Superadmin') => M:superadmin, dsb.
 */
function getFullRole()
{
  $userRole     = $_SESSION['role'] ?? '';
  $userJobTitle = $_SESSION['job_title'] ?? '';

  // Jika role bukan 'M', maka langsung return role (misal P, TK)
  if ($userRole !== 'M') {
    return $userRole;
  }
  // Jika role = 'M', cek sub-role di job_title
  $normalized = strtolower(trim($userJobTitle));
  if (strpos($normalized, 'superadmin') !== false)      return 'M:superadmin';
  if (strpos($normalized, 'sdm') !== false)             return 'M:sdm';
  if (strpos($normalized, 'keuangan') !== false)        return 'M:keuangan';
  if (strpos($normalized, 'kepala sekolah') !== false)  return 'M:kepala sekolah';

  // Jika tidak cocok empat di atas, fallback ke 'M'
  return 'M';
}

// --------------------------------------------
// BAGIAN 1: PROSES DISMISS BACKUP ALERT
// --------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dismissed'])) {
  $_SESSION['backup_alert_dismissed'] = true;
  echo json_encode(["code" => 0, "message" => "Backup alert dismissed."]);
  exit();
}

// --------------------------------------------
// BAGIAN 2: INISIALISASI NOTIFIKASI
// --------------------------------------------
$guruNotificationMsg       = "";
$kepsekNotificationMsg     = "";
$sdmNotificationMsg        = "";
$keuNotificationMsg        = "";
$backupAlertMsg            = "";
$systemAlertMsg            = ""; // untuk notifikasi error / audit

// Tambahan notifikasi baru:
$guruExtraMsg              = ""; // Untuk Guru: jadwal piket, absensi terlambat, izin mendekati tanggal mulai, slip gaji final.
$sdmExtraMsg               = ""; // Untuk SDM: izin baru, karyawan baru belum update, ketidakhadiran berulang.
$keuExtraMsg               = ""; // Untuk Keuangan: adjustment rapel pending, jadwal pembayaran.

$guruCount   = 0;
$kepsekCount = 0;
$sdmCount    = 0;
$keuCount    = 0;
$backupCount = 0;
$systemCount = 0;

// Ambil user ID (atau nip) dan full role
$userId   = $_SESSION['user_id'] ?? 0;
$nip      = $_SESSION['nip'] ?? '';
$fullRole = getFullRole(); // misalnya 'M:superadmin', 'M:keuangan', 'P', 'TK', dsb.

// Ambil hari, bulan, dan tahun saat ini
$currentDay   = (int) date('d');
$currentMonth = (int) date('n');
$currentYear  = (int) date('Y');

// Fungsi bantu untuk konversi bulan ke nama bulan Indonesia
function getIndonesianMonthName($month)
{
  $months = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
  return $months[$month] ?? $month;
}

// =====================================================
// NOTIFIKASI UNTUK GURU / KARYAWAN (ROLE P, TK)
// =====================================================
if (in_array($fullRole, ['P', 'TK'])) {
  // 1. Pengajuan Izin yang telah diproses
  $sqlGuruIzin = "SELECT COUNT(*) AS cnt FROM pengajuan_ijin WHERE nip = ? AND status <> 'Pending'";
  $stmtGuru = $conn->prepare($sqlGuruIzin);
  if ($stmtGuru) {
    $stmtGuru->bind_param("s", $nip);
    $stmtGuru->execute();
    $resGuru = $stmtGuru->get_result();
    $rowGuru = $resGuru->fetch_assoc();
    $processedIzin = intval($rowGuru['cnt'] ?? 0);
    $stmtGuru->close();
    if ($processedIzin > 0) {
      $guruNotificationMsg = "Anda memiliki {$processedIzin} pengajuan izin yang telah diproses.";
      $guruCount++;
    }
  }
  // 2. Jadwal Piket Hari Ini (dari tabel jadwal_piket)
  $sqlPiket = "SELECT waktu_piket FROM jadwal_piket WHERE nip = ? AND tanggal = CURDATE()";
  $stmtPiket = $conn->prepare($sqlPiket);
  if ($stmtPiket) {
    $stmtPiket->bind_param("s", $nip);
    $stmtPiket->execute();
    $resPiket = $stmtPiket->get_result();
    if ($rowPiket = $resPiket->fetch_assoc()) {
      $waktu = $rowPiket['waktu_piket'];
      $guruExtraMsg .= "Hari ini Anda memiliki jadwal piket pada jam {$waktu}. ";
      $guruCount++;
    }
    $stmtPiket->close();
  }
  // 3. Teguran Absensi Terlambat: Jika terlambat ≥ 3 kali bulan ini
  $sqlTerlambat = "SELECT COUNT(*) AS cnt FROM absensi WHERE nip = ? AND terlambat = 1 AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?";
  $stmtTerlambat = $conn->prepare($sqlTerlambat);
  if ($stmtTerlambat) {
    $stmtTerlambat->bind_param("sii", $nip, $currentMonth, $currentYear);
    $stmtTerlambat->execute();
    $resTerlambat = $stmtTerlambat->get_result();
    $rowTerlambat = $resTerlambat->fetch_assoc();
    $countTerlambat = intval($rowTerlambat['cnt'] ?? 0);
    $stmtTerlambat->close();
    if ($countTerlambat >= 3) {
      $guruExtraMsg .= "Anda telah terlambat sebanyak {$countTerlambat} kali bulan ini. ";
      $guruCount++;
    }
  }
  // 4. Pengajuan Izin Mendekati Tanggal Mulai (jika disetujui dan 1 hari sebelum tanggal efektif)
  // Pastikan kolom tanggal di pengajuan_ijin disimpan dalam format DATE atau dapat dikonversi.
  $sqlIzinBesok = "SELECT COUNT(*) AS cnt FROM pengajuan_ijin WHERE nip = ? AND status = 'Diterima' AND DATEDIFF(STR_TO_DATE(tanggal, '%Y-%m-%d'), CURDATE()) = 1";
  $stmtIzinBesok = $conn->prepare($sqlIzinBesok);
  if ($stmtIzinBesok) {
    $stmtIzinBesok->bind_param("s", $nip);
    $stmtIzinBesok->execute();
    $resIzinBesok = $stmtIzinBesok->get_result();
    $rowIzinBesok = $resIzinBesok->fetch_assoc();
    $izinBesok = intval($rowIzinBesok['cnt'] ?? 0);
    $stmtIzinBesok->close();
    if ($izinBesok > 0) {
      $guruExtraMsg .= "Pengajuan izin Anda akan mulai besok. ";
      $guruCount++;
    }
  }
  // 5. Slip Gaji Final Tersedia (cek di payroll_final)
  $sqlSlipGaji = "SELECT COUNT(*) AS cnt FROM payroll_final pf JOIN anggota_sekolah a ON pf.id_anggota = a.id WHERE a.nip = ? AND pf.tahun = ? AND pf.bulan = ?";
  // Misal, notifikasi untuk bulan sebelumnya (atau sesuai kebijakan)
  $targetMonthSlip = ($currentMonth == 1) ? 12 : $currentMonth - 1;
  $targetYearSlip = ($currentMonth == 1) ? $currentYear - 1 : $currentYear;
  $stmtSlipGaji = $conn->prepare($sqlSlipGaji);
  if ($stmtSlipGaji) {
    $stmtSlipGaji->bind_param("sii", $nip, $targetYearSlip, $targetMonthSlip);
    $stmtSlipGaji->execute();
    $resSlipGaji = $stmtSlipGaji->get_result();
    $rowSlipGaji = $resSlipGaji->fetch_assoc();
    $slipCount = intval($rowSlipGaji['cnt'] ?? 0);
    $stmtSlipGaji->close();
    if ($slipCount > 0) {
      $bulanNama = getIndonesianMonthName($targetMonthSlip);
      $guruExtraMsg .= "Slip gaji bulan {$bulanNama} sudah tersedia. ";
      $guruCount++;
    }
  }
  // Gabungkan pesan notifikasi untuk guru
  if (!empty($guruExtraMsg)) {
    $guruNotificationMsg .= " " . $guruExtraMsg;
  }
}

// =====================================================
// NOTIFIKASI UNTUK SDM (M:sdm)
// =====================================================
if ($fullRole === 'M:sdm') {
  // 1. Izin Baru dari Guru (yang status masih Pending)
  $sqlIzinBaru = "SELECT COUNT(*) AS cnt FROM pengajuan_ijin WHERE status = 'Pending'";
  $stmtIzinBaru = $conn->prepare($sqlIzinBaru);
  if ($stmtIzinBaru) {
    $stmtIzinBaru->execute();
    $resIzinBaru = $stmtIzinBaru->get_result();
    $rowIzinBaru = $resIzinBaru->fetch_assoc();
    $izinBaru = intval($rowIzinBaru['cnt'] ?? 0);
    $stmtIzinBaru->close();
    if ($izinBaru > 0) {
      $sdmNotificationMsg .= "Terdapat {$izinBaru} pengajuan izin baru. ";
      $sdmCount++;
    }
  }
  // 2. Karyawan Baru Belum Diperbarui (cek anggota_sekolah dengan join_start 7 hari terakhir dan salary_index_id IS NULL)
  $sqlKaryawanBaru = "SELECT COUNT(*) AS cnt FROM anggota_sekolah WHERE join_start >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND salary_index_id IS NULL";
  $stmtKaryawanBaru = $conn->prepare($sqlKaryawanBaru);
  if ($stmtKaryawanBaru) {
    $stmtKaryawanBaru->execute();
    $resKaryawanBaru = $stmtKaryawanBaru->get_result();
    $rowKaryawanBaru = $resKaryawanBaru->fetch_assoc();
    $baruCount = intval($rowKaryawanBaru['cnt'] ?? 0);
    $stmtKaryawanBaru->close();
    if ($baruCount > 0) {
      $sdmNotificationMsg .= "Terdapat {$baruCount} karyawan baru yang belum diupdate data salary-nya. ";
      $sdmCount++;
    }
  }
  // 3. Ketidakhadiran Berulang (misalnya, absensi 'tanpa_keterangan' ≥ 3 kali bulan ini)
  $sqlBolos = "SELECT COUNT(DISTINCT tanggal) AS cnt FROM absensi WHERE nip IN (SELECT nip FROM anggota_sekolah WHERE role IN ('P','TK')) AND status_kehadiran = 'tanpa_keterangan' AND MONTH(tanggal)=? AND YEAR(tanggal)=?";
  $stmtBolos = $conn->prepare($sqlBolos);
  if ($stmtBolos) {
    // Untuk SDM, cek seluruh guru/karyawan; misalnya total unik tanggal di mana setidaknya 1 guru bolos.
    $stmtBolos->bind_param("ii", $currentMonth, $currentYear);
    $stmtBolos->execute();
    $resBolos = $stmtBolos->get_result();
    $rowBolos = $resBolos->fetch_assoc();
    $bolosCount = intval($rowBolos['cnt'] ?? 0);
    $stmtBolos->close();
    if ($bolosCount >= 3) {
      $sdmNotificationMsg .= "Terdapat sejumlah hari ketidakhadiran berulang (bolos/tanpa keterangan). ";
      $sdmCount++;
    }
  }
  // (Notifikasi Payroll dan Absensi SDM sudah ada pada BAGIAN 5 di file asli.)
  // Kita biarkan notifikasi payroll dan absensi yang belum final tetap berjalan.
}

// =====================================================
// NOTIFIKASI UNTUK KEUANGAN (M:keuangan)
// =====================================================
if ($fullRole === 'M:keuangan') {
  // 1. Adjustment Rapel Pending: Cek employee_payheads dengan is_rapel = 1 dan status 'draft'
  $sqlRapel = "SELECT COUNT(*) AS cnt FROM employee_payheads WHERE is_rapel = 1 AND status = 'draft'";
  $stmtRapel = $conn->prepare($sqlRapel);
  if ($stmtRapel) {
    $stmtRapel->execute();
    $resRapel = $stmtRapel->get_result();
    $rowRapel = $resRapel->fetch_assoc();
    $rapelCount = intval($rowRapel['cnt'] ?? 0);
    $stmtRapel->close();
    if ($rapelCount > 0) {
      $keuExtraMsg .= "Terdapat {$rapelCount} adjustment rapel pending. ";
      $keuCount++;
    }
  }
  // 2. Pengingat Jadwal Pembayaran: Cek payroll_final yang tgl_payroll kurang dari 3 hari dari hari ini
  $sqlBayar = "SELECT COUNT(*) AS cnt FROM payroll_final WHERE DATEDIFF(tgl_payroll, CURDATE()) BETWEEN 0 AND 3";
  $stmtBayar = $conn->prepare($sqlBayar);
  if ($stmtBayar) {
    $stmtBayar->execute();
    $resBayar = $stmtBayar->get_result();
    $rowBayar = $resBayar->fetch_assoc();
    $bayarCount = intval($rowBayar['cnt'] ?? 0);
    $stmtBayar->close();
    if ($bayarCount > 0) {
      $keuExtraMsg .= "Perhatian: ada {$bayarCount} payroll final mendekati jadwal pembayaran. ";
      $keuCount++;
    }
  }
  // 3. (Notifikasi error perhitungan payroll sudah ada.)
  if (!empty($keuExtraMsg)) {
    $keuNotificationMsg .= " " . $keuExtraMsg;
  }
}

// ==============================================
// BAGIAN 5 & 6: NOTIFIKASI UNTUK M:sdm & M:keuangan & M:superadmin
// (Kode aslinya untuk payroll, absensi, backup, sistem audit)
// ==============================================
if (in_array($fullRole, ['M:sdm', 'M:superadmin'])) {
  if ($currentDay >= 24) {
    if ($currentMonth == 12) {
      $targetMonth = 1;
      $targetYear  = $currentYear + 1;
    } else {
      $targetMonth = $currentMonth + 1;
      $targetYear  = $currentYear;
    }
  } else {
    $targetMonth = $currentMonth;
    $targetYear  = $currentYear;
  }
  $sqlSdm = "
        SELECT COUNT(*) AS pending
        FROM anggota_sekolah
        WHERE id NOT IN (
            SELECT id_anggota FROM payroll_final
            WHERE bulan = ? AND tahun = ?
        )
    ";
  $stmtSdm = $conn->prepare($sqlSdm);
  if ($stmtSdm) {
    $stmtSdm->bind_param("ii", $targetMonth, $targetYear);
    $stmtSdm->execute();
    $resSdm = $stmtSdm->get_result();
    $rowSdm = $resSdm->fetch_assoc();
    $pendingCountSdm = intval($rowSdm['pending'] ?? 0);
    $stmtSdm->close();
    if ($pendingCountSdm > 0) {
      $monthName = getIndonesianMonthName($targetMonth);
      $sdmNotificationMsg .= "Payroll Bulan {$monthName} terdapat {$pendingCountSdm} anggota belum dibayar. ";
      $sdmCount++;
    }
  }
  $sqlKoreksi = "SELECT COUNT(*) AS cnt FROM absensi WHERE valid = 0 AND tanggal = CURDATE()";
  $stmtKoreksi = $conn->prepare($sqlKoreksi);
  if ($stmtKoreksi) {
    $stmtKoreksi->execute();
    $resKoreksi = $stmtKoreksi->get_result();
    $rowKoreksi = $resKoreksi->fetch_assoc();
    $pendingKoreksi = intval($rowKoreksi['cnt'] ?? 0);
    $stmtKoreksi->close();
    if ($pendingKoreksi > 0) {
      $sdmNotificationMsg .= "Selain itu, terdapat {$pendingKoreksi} data absensi yang perlu dikoreksi. ";
      $sdmCount++;
    }
  }
  $lastDayOfMonth = (int) date("t");
  if (($lastDayOfMonth - $currentDay) <= 3) {
    $sdmNotificationMsg .= "Segera finalisasi payroll, tinggal " . ($lastDayOfMonth - $currentDay) . " hari tersisa. ";
    $sdmCount++;
  }
}
if (in_array($fullRole, ['M:keuangan', 'M:superadmin'])) {
  $targetMonth = $currentMonth;
  $targetYear  = $currentYear;
  $sqlKeu = "
        SELECT COUNT(*) AS pending
        FROM payroll p
        WHERE p.bulan = ? 
          AND p.tahun = ?
          AND p.status = 'draft'
          AND NOT EXISTS (
                SELECT 1 FROM payroll_final pf
                WHERE pf.id_anggota = p.id_anggota
                  AND pf.bulan = p.bulan
                  AND pf.tahun = p.tahun
          )
    ";
  $stmtKeu = $conn->prepare($sqlKeu);
  if ($stmtKeu) {
    $stmtKeu->bind_param("ii", $targetMonth, $targetYear);
    $stmtKeu->execute();
    $resKeu = $stmtKeu->get_result();
    $rowKeu = $resKeu->fetch_assoc();
    $pendingCountKeu = intval($rowKeu['pending'] ?? 0);
    $stmtKeu->close();
    if ($pendingCountKeu > 0) {
      $monthName = getIndonesianMonthName($targetMonth);
      $keuNotificationMsg .= "Payroll Bulan {$monthName} (Draft) terdapat {$pendingCountKeu} anggota belum final. ";
      $keuCount++;
    }
  }
  $sqlError = "SELECT COUNT(*) AS cnt FROM payroll WHERE ABS(total_pendapatan - total_potongan - gaji_bersih) > 1000";
  $stmtError = $conn->prepare($sqlError);
  if ($stmtError) {
    $stmtError->execute();
    $resError = $stmtError->get_result();
    $rowError = $resError->fetch_assoc();
    $errorCount = intval($rowError['cnt'] ?? 0);
    $stmtError->close();
    if ($errorCount > 0) {
      $keuNotificationMsg .= "Terjadi {$errorCount} error pada perhitungan payroll. ";
      $keuCount++;
    }
  }
}

// --------------------------------------------
// BAGIAN 7: Notifikasi Backup Database -> HANYA M:superadmin
// --------------------------------------------
if ($fullRole === 'M:superadmin' && $currentDay === 1) {
  if (empty($_SESSION['backup_alert_dismissed'])) {
    $backupAlertMsg = "Ingat untuk Backup Database hari ini. ";
    $backupCount++;
  }
}

// --------------------------------------------
// BAGIAN 8: Notifikasi Sistem / Audit -> HANYA M:superadmin
// --------------------------------------------
if ($fullRole === 'M:superadmin') {
  $sqlSys = "SELECT COUNT(*) AS cnt FROM audit_logs WHERE action LIKE '%error%' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
  $stmtSys = $conn->prepare($sqlSys);
  if ($stmtSys) {
    $stmtSys->execute();
    $resSys = $stmtSys->get_result();
    $rowSys = $resSys->fetch_assoc();
    $errorLogs = intval($rowSys['cnt'] ?? 0);
    $stmtSys->close();
    if ($errorLogs > 0) {
      $systemAlertMsg = "Terdapat {$errorLogs} log error dalam 24 jam terakhir. Silakan periksa logs untuk detail. ";
      $systemCount++;
    }
  }
}

// --------------------------------------------
// BAGIAN 9: Notifikasi Manual dari Tabel notifications
// --------------------------------------------
$manualNotifications = [];
$sqlManual = "SELECT * FROM notifications WHERE role_target IN (?, 'all') AND user_id = ? AND is_read = 0 ORDER BY priority ASC, created_at DESC";
$stmtManual = $conn->prepare($sqlManual);
if ($stmtManual) {
  $targetRoleForManual = strtolower($fullRole);
  $stmtManual->bind_param("si", $targetRoleForManual, $userId);
  $stmtManual->execute();
  $resManual = $stmtManual->get_result();
  while ($row = $resManual->fetch_assoc()) {
    $manualNotifications[] = $row;
  }
  $stmtManual->close();
}

// Hitung total notifikasi
$totalAlerts = $guruCount + $kepsekCount + $sdmCount + $keuCount + $backupCount + $systemCount + count($manualNotifications);

// Tutup koneksi
$conn->close();
?>
<!-- Bagian tampilan notifikasi -->
<div class="container my-4">
  <!-- Notifikasi untuk Guru (P, TK) -->
  <?php if (in_array($fullRole, ['P', 'TK'])): ?>
    <?php if (!empty($guruNotificationMsg)): ?>
      <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-envelope-open-text me-2"></i>
        <div><?= htmlspecialchars($guruNotificationMsg); ?></div>
      </div>
    <?php else: ?>
      <div class="alert alert-success mb-3">
        <i class="bi bi-check-circle-fill"></i> Tidak ada notifikasi baru untuk guru.
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Notifikasi untuk M:kepala sekolah -->
  <?php if ($fullRole === 'M:kepala sekolah'): ?>
    <?php if (!empty($kepsekNotificationMsg)): ?>
      <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-chalkboard-teacher me-2"></i>
        <div><?= htmlspecialchars($kepsekNotificationMsg); ?></div>
      </div>
    <?php else: ?>
      <div class="alert alert-success mb-3">
        <i class="bi bi-check-circle-fill"></i> Tidak ada notifikasi baru untuk kepala sekolah.
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Notifikasi untuk SDM (M:sdm) -->
  <?php if ($fullRole === 'M:sdm'): ?>
    <?php if (!empty($sdmNotificationMsg)): ?>
      <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-user-cog me-2"></i>
        <div><?= htmlspecialchars($sdmNotificationMsg); ?></div>
      </div>
    <?php else: ?>
      <div class="alert alert-success mb-3">
        <i class="bi bi-check-circle-fill"></i> Tidak ada notifikasi baru untuk SDM.
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Notifikasi untuk Keuangan (M:keuangan) -->
  <?php if ($fullRole === 'M:keuangan'): ?>
    <?php if (!empty($keuNotificationMsg)): ?>
      <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-calculator me-2"></i>
        <div><?= htmlspecialchars($keuNotificationMsg); ?></div>
      </div>
    <?php else: ?>
      <div class="alert alert-success mb-3">
        <i class="bi bi-check-circle-fill"></i> Tidak ada notifikasi baru untuk keuangan.
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Notifikasi Backup Database untuk M:superadmin -->
  <?php if ($fullRole === 'M:superadmin' && !empty($backupAlertMsg)): ?>
    <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
      <i class="fas fa-database me-2"></i>
      <div>
        <?= htmlspecialchars($backupAlertMsg); ?>
        <a href="/payroll_absensi_v2/payroll/superadmin/backup_database.php" class="alert-link backup-dismiss">[Backup Sekarang]</a>
      </div>
    </div>
  <?php endif; ?>

  <!-- Notifikasi Sistem / Audit untuk M:superadmin -->
  <?php if ($fullRole === 'M:superadmin' && !empty($systemAlertMsg)): ?>
    <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
      <i class="fas fa-exclamation-circle me-2"></i>
      <div><?= htmlspecialchars($systemAlertMsg); ?></div>
    </div>
  <?php endif; ?>

  <!-- Notifikasi Manual dari Tabel notifications -->
  <?php if (!empty($manualNotifications)): ?>
    <?php foreach ($manualNotifications as $notif):
      switch ($notif['notification_type']) {
        case 'warning':
          $alertClass = 'alert-warning';
          break;
        case 'success':
          $alertClass = 'alert-success';
          break;
        case 'error':
          $alertClass = 'alert-danger';
          break;
        default:
          $alertClass = 'alert-info';
          break;
      }
    ?>
      <div class="alert <?= $alertClass; ?> d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-bell me-2"></i>
        <div>
          <strong><?= htmlspecialchars($notif['title']); ?></strong><br>
          <?= htmlspecialchars($notif['message']); ?>
          <?php if (!empty($notif['link'])): ?>
            <a href="<?= htmlspecialchars($notif['link']); ?>" class="alert-link">[Detail]</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- Ringkasan Total Alert -->
  <div class="mt-4">
    <span class="badge bg-primary">Total Notifikasi: <?= $totalAlerts; ?></span>
  </div>
</div>