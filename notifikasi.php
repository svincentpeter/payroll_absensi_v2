<?php
// File: notifikasi.php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/koneksi.php';

start_session_safe();

// Jika ada request POST untuk menandai backup alert dismissed, proses dan keluar.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dismissed'])) {
    $_SESSION['backup_alert_dismissed'] = true;
    echo json_encode(["code" => 0, "message" => "Backup alert dismissed."]);
    exit();
}

// Variabel global notifikasi
$sdmNotificationMsg = "";
$keuNotificationMsg = "";
$backupAlertMsg     = "";
// Penanda apakah notifikasi muncul (bisa digunakan untuk badge)
$sdmCount    = 0;
$keuCount    = 0;
$backupCount = 0;

// Ambil role user
$userRole = $_SESSION['role'] ?? '';

// Ambil hari, bulan, dan tahun saat ini
$currentDay   = (int) date('d');
$currentMonth = (int) date('n');
$currentYear  = (int) date('Y');

// --------------------------------------------------------------------------
// 1. Notifikasi untuk SDM/Superadmin
// --------------------------------------------------------------------------
if (in_array($userRole, ['sdm', 'superadmin'])) {
    // Jika tanggal >= 24, asumsikan target bulan berikutnya
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
    // Cek anggota yang belum final di payroll_final
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
            $sdmNotificationMsg = "Payroll Bulan {$monthName} terdapat {$pendingCountSdm} anggota belum dibayar";
            $sdmCount = 1;
        }
    } else {
        error_log("Gagal statement notifikasi SDM: " . $conn->error);
    }
}

// --------------------------------------------------------------------------
// 2. Notifikasi untuk Keuangan/Superadmin
// --------------------------------------------------------------------------
if (in_array($userRole, ['keuangan', 'superadmin'])) {
    $targetMonth = $currentMonth;
    $targetYear  = $currentYear;
    // Cek payroll status draft yang belum final di payroll_final
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
            $keuNotificationMsg = "Payroll Bulan {$monthName} (Draft) terdapat {$pendingCountKeu} anggota belum final";
            $keuCount = 1;
        }
    } else {
        error_log("Gagal statement notifikasi keuangan: " . $conn->error);
    }
}

// --------------------------------------------------------------------------
// 3. Alert Backup Database untuk Superadmin (Tampilkan jika tanggal 1)
// --------------------------------------------------------------------------
if ($userRole === 'superadmin' && $currentDay === 1) {
    if (empty($_SESSION['backup_alert_dismissed'])) {
        $backupAlertMsg = "Ingat untuk Backup Database hari ini.";
        $backupCount = 1;
    }
}

// --------------------------------------------------------------------------
// 4. Hitung total alert
// --------------------------------------------------------------------------
$totalAlerts = $sdmCount + $keuCount + $backupCount;

// Bungkus semua notifikasi ke dalam array agar dapat di-include
$NOTIF = [
    'sdmNotificationMsg' => $sdmNotificationMsg,
    'sdmCount'           => $sdmCount,
    'keuNotificationMsg' => $keuNotificationMsg,
    'keuCount'           => $keuCount,
    'backupAlertMsg'     => $backupAlertMsg,
    'backupCount'        => $backupCount,
    'totalAlerts'        => $totalAlerts
];

$conn->close();
?>
<!-- Bagian tampilan notifikasi -->
<div class="container my-4">
  <!-- Notifikasi untuk SDM / Superadmin -->
  <?php if (in_array($userRole, ['sdm','superadmin'])): ?>
    <?php if (!empty($NOTIF['sdmNotificationMsg'])): ?>
      <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <div><?= htmlspecialchars($NOTIF['sdmNotificationMsg']); ?></div>
      </div>
    <?php else: ?>
      <div class="alert alert-success mb-3">
        <i class="bi bi-check-circle-fill"></i> Tidak ada anggota belum dibayar (SDM).
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Notifikasi untuk Keuangan / Superadmin -->
  <?php if (in_array($userRole, ['keuangan','superadmin'])): ?>
    <?php if (!empty($NOTIF['keuNotificationMsg'])): ?>
      <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-info-circle me-2"></i>
        <div><?= htmlspecialchars($NOTIF['keuNotificationMsg']); ?></div>
      </div>
    <?php else: ?>
      <div class="alert alert-success mb-3">
        <i class="bi bi-check-circle-fill"></i> Tidak ada payroll draft untuk keuangan.
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Notifikasi Alert Backup Database untuk Superadmin -->
  <?php if ($userRole === 'superadmin'): ?>
    <?php if (!empty($NOTIF['backupAlertMsg'])): ?>
      <div class="alert alert-danger d-flex align-items-center" role="alert">
        <i class="fas fa-database me-2"></i>
        <div>
          <?= htmlspecialchars($NOTIF['backupAlertMsg']); ?>
          <a href="/payroll_absensi_v2/payroll/superadmin/backup_database.php" class="alert-link backup-dismiss">[Backup Sekarang]</a>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>

</div>
