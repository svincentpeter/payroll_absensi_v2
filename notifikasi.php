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

// Inisialisasi variabel notifikasi untuk setiap peran
$guruNotificationMsg       = "";
$kepsekNotificationMsg     = "";
$sdmNotificationMsg        = "";
$keuNotificationMsg        = "";
$backupAlertMsg            = "";
$systemAlertMsg            = ""; // untuk notifikasi error / audit

// Penanda notifikasi (untuk badge)
$guruCount   = 0;
$kepsekCount = 0;
$sdmCount    = 0;
$keuCount    = 0;
$backupCount = 0;
$systemCount = 0;

// Ambil role user dan user_id (misal: NIP) dari session
$userRole = $_SESSION['role'] ?? '';
$userId   = $_SESSION['user_id'] ?? 0;

// Ambil hari, bulan, dan tahun saat ini
$currentDay   = (int) date('d');
$currentMonth = (int) date('n');
$currentYear  = (int) date('Y');

// --------------------------------------------------------------------------
// 1. Notifikasi untuk Guru
//    (Contoh: status pengajuan izin, permintaan tukar jadwal, dan konfirmasi upload absensi)
// --------------------------------------------------------------------------
if ($userRole === 'P' || $userRole === 'TK') {
    // Contoh: cek apakah ada pengajuan izin yang statusnya telah diupdate
    $sqlGuruIzin = "SELECT COUNT(*) AS cnt FROM pengajuan_ijin WHERE nip = ? AND status <> 'Pending'";
    $stmtGuru = $conn->prepare($sqlGuruIzin);
    if ($stmtGuru) {
        $stmtGuru->bind_param("s", $_SESSION['nip']);
        $stmtGuru->execute();
        $resGuru = $stmtGuru->get_result();
        $rowGuru = $resGuru->fetch_assoc();
        $pendingIzin = intval($rowGuru['cnt'] ?? 0);
        $stmtGuru->close();
        if ($pendingIzin > 0) {
            $guruNotificationMsg = "Anda memiliki {$pendingIzin} pengajuan izin yang telah diproses.";
            $guruCount = 1;
        }
    }
    // Tambahan: Notifikasi untuk permintaan tukar jadwal dan konfirmasi upload absensi
    // (Query dapat ditambahkan sesuai dengan struktur tabel yang digunakan.)
}

// --------------------------------------------------------------------------
// 2. Notifikasi untuk Kepala Sekolah
//    (Contoh: ada pengajuan izin masuk yang perlu di-approve dan laporan yang perlu ditinjau)
// --------------------------------------------------------------------------
if ($userRole === 'M' && isset($_SESSION['jabatan']) && $_SESSION['jabatan'] === 'kepala_sekolah') {
    // Hitung pengajuan izin baru yang masih Pending untuk kepala sekolah
    $sqlKepsek = "SELECT COUNT(*) AS cnt FROM pengajuan_ijin WHERE status_kepalasekolah = 'Pending'";
    $stmtKepsek = $conn->prepare($sqlKepsek);
    if ($stmtKepsek) {
        $stmtKepsek->execute();
        $resKepsek = $stmtKepsek->get_result();
        $rowKepsek = $resKepsek->fetch_assoc();
        $pendingIzinKepsek = intval($rowKepsek['cnt'] ?? 0);
        $stmtKepsek->close();
        if ($pendingIzinKepsek > 0) {
            $kepsekNotificationMsg = "Terdapat {$pendingIzinKepsek} pengajuan izin baru untuk ditinjau.";
            $kepsekCount = 1;
        }
    }
    // Tambahan: cek laporan yang belum ditinjau jika diperlukan.
}

// --------------------------------------------------------------------------
// 3. Notifikasi untuk SDM/Superadmin (Payroll anggota yang belum final & koreksi absensi)
// --------------------------------------------------------------------------
if (in_array($userRole, ['sdm', 'superadmin'])) {
    // Target bulan: jika hari >= 24, asumsikan target bulan berikutnya
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
            $sdmNotificationMsg = "Payroll Bulan {$monthName} terdapat {$pendingCountSdm} anggota belum dibayar.";
            $sdmCount = 1;
        }
    }
    // Cek data absensi yang perlu dikoreksi (misalnya, data validasi gagal)
    $sqlKoreksi = "SELECT COUNT(*) AS cnt FROM absensi WHERE valid = 0 AND tanggal = CURDATE()";
    $stmtKoreksi = $conn->prepare($sqlKoreksi);
    if ($stmtKoreksi) {
        $stmtKoreksi->execute();
        $resKoreksi = $stmtKoreksi->get_result();
        $rowKoreksi = $resKoreksi->fetch_assoc();
        $pendingKoreksi = intval($rowKoreksi['cnt'] ?? 0);
        $stmtKoreksi->close();
        if ($pendingKoreksi > 0) {
            $sdmNotificationMsg .= " Selain itu, terdapat {$pendingKoreksi} data absensi yang perlu dikoreksi.";
            $sdmCount = 1;
        }
    }
    // Reminder deadline finalisasi payroll (misalnya, 3 hari sebelum akhir bulan)
    $lastDayOfMonth = (int) date("t");
    if (($lastDayOfMonth - $currentDay) <= 3) {
        $sdmNotificationMsg .= " Segera finalisasi payroll bulan ini, tinggal " . ($lastDayOfMonth - $currentDay) . " hari tersisa.";
        $sdmCount = 1;
    }
}

// --------------------------------------------------------------------------
// 4. Notifikasi untuk Keuangan/Superadmin (Payroll draft dan error/inconsistency)
// --------------------------------------------------------------------------
if (in_array($userRole, ['keuangan', 'superadmin'])) {
    $targetMonth = $currentMonth;
    $targetYear  = $currentYear;
    // Cek payroll dengan status 'draft' yang belum final
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
            $keuNotificationMsg = "Payroll Bulan {$monthName} (Draft) terdapat {$pendingCountKeu} anggota belum final.";
            $keuCount = 1;
        }
    }
    // Cek error atau ketidaksesuaian data pada payroll (misalnya, selisih perhitungan)
    $sqlError = "SELECT COUNT(*) AS cnt FROM payroll WHERE ABS(total_pendapatan - total_potongan - gaji_bersih) > 1000";
    $stmtError = $conn->prepare($sqlError);
    if ($stmtError) {
        $stmtError->execute();
        $resError = $stmtError->get_result();
        $rowError = $resError->fetch_assoc();
        $errorCount = intval($rowError['cnt'] ?? 0);
        $stmtError->close();
        if ($errorCount > 0) {
            $keuNotificationMsg .= " Terjadi {$errorCount} error pada perhitungan payroll.";
            $keuCount = 1;
        }
    }
}

// --------------------------------------------------------------------------
// 5. Notifikasi Backup Database untuk Superadmin
// --------------------------------------------------------------------------
if ($userRole === 'superadmin' && $currentDay === 1) {
    if (empty($_SESSION['backup_alert_dismissed'])) {
        $backupAlertMsg = "Ingat untuk Backup Database hari ini. ";
        $backupCount = 1;
    }
}

// --------------------------------------------------------------------------
// 6. Notifikasi Sistem / Audit untuk Superadmin
//    (Misalnya, notifikasi jika terdapat aktivitas mencurigakan atau log error penting)
// --------------------------------------------------------------------------
if ($userRole === 'superadmin') {
    $sqlSys = "SELECT COUNT(*) AS cnt FROM audit_logs WHERE action LIKE '%error%' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
    $stmtSys = $conn->prepare($sqlSys);
    if ($stmtSys) {
        $stmtSys->execute();
        $resSys = $stmtSys->get_result();
        $rowSys = $resSys->fetch_assoc();
        $errorLogs = intval($rowSys['cnt'] ?? 0);
        $stmtSys->close();
        if ($errorLogs > 0) {
            $systemAlertMsg = "Terdapat {$errorLogs} log error dalam 24 jam terakhir. Silakan periksa logs untuk detail.";
            $systemCount = 1;
        }
    }
}

// --------------------------------------------------------------------------
// 7. Ambil Notifikasi Manual dari Tabel notifications
//    (Menggunakan struktur tabel yang telah diimprove)
// --------------------------------------------------------------------------
$manualNotifications = [];
$sqlManual = "SELECT * FROM notifications WHERE role_target IN (?, 'all') AND user_id = ? AND is_read = 0 ORDER BY priority ASC, created_at DESC";
$stmtManual = $conn->prepare($sqlManual);
if ($stmtManual) {
    // Role target disesuaikan: misal 'keuangan', 'sdm', atau untuk guru (sesuaikan dengan nilai userRole)
    $targetRoleForManual = strtolower($userRole);
    $stmtManual->bind_param("si", $targetRoleForManual, $userId);
    $stmtManual->execute();
    $resManual = $stmtManual->get_result();
    while ($row = $resManual->fetch_assoc()) {
        $manualNotifications[] = $row;
    }
    $stmtManual->close();
}

// Total Alerts dihitung dari semua count di atas
$totalAlerts = $guruCount + $kepsekCount + $sdmCount + $keuCount + $backupCount + $systemCount + count($manualNotifications);

$conn->close();
?>
<!-- Bagian tampilan notifikasi -->
<div class="container my-4">
  <!-- Notifikasi untuk Guru -->
  <?php if ($userRole === 'P' || $userRole === 'TK'): ?>
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

  <!-- Notifikasi untuk Kepala Sekolah -->
  <?php if ($userRole === 'M' && isset($_SESSION['jabatan']) && $_SESSION['jabatan'] === 'kepala_sekolah'): ?>
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

  <!-- Notifikasi untuk SDM -->
  <?php if (in_array($userRole, ['sdm', 'superadmin'])): ?>
    <?php if (!empty($sdmNotificationMsg)): ?>
      <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-user-cog me-2"></i>
        <div><?= htmlspecialchars($sdmNotificationMsg); ?></div>
      </div>
    <?php else: ?>
      <div class="alert alert-success mb-3">
        <i class="bi bi-check-circle-fill"></i> Tidak ada notifikasi untuk SDM.
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Notifikasi untuk Keuangan -->
  <?php if (in_array($userRole, ['keuangan', 'superadmin'])): ?>
    <?php if (!empty($keuNotificationMsg)): ?>
      <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-calculator me-2"></i>
        <div><?= htmlspecialchars($keuNotificationMsg); ?></div>
      </div>
    <?php else: ?>
      <div class="alert alert-success mb-3">
        <i class="bi bi-check-circle-fill"></i> Tidak ada notifikasi untuk keuangan.
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Notifikasi Backup Database untuk Superadmin -->
  <?php if ($userRole === 'superadmin' && !empty($backupAlertMsg)): ?>
    <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
      <i class="fas fa-database me-2"></i>
      <div>
        <?= htmlspecialchars($backupAlertMsg); ?>
        <a href="/payroll_absensi_v2/payroll/superadmin/backup_database.php" class="alert-link backup-dismiss">[Backup Sekarang]</a>
      </div>
    </div>
  <?php endif; ?>

  <!-- Notifikasi Sistem / Audit untuk Superadmin -->
  <?php if ($userRole === 'superadmin' && !empty($systemAlertMsg)): ?>
    <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
      <i class="fas fa-exclamation-circle me-2"></i>
      <div><?= htmlspecialchars($systemAlertMsg); ?></div>
    </div>
  <?php endif; ?>

  <!-- Notifikasi Manual dari Tabel notifications -->
  <?php if (!empty($manualNotifications)): ?>
    <?php foreach ($manualNotifications as $notif): 
          // Tentukan alert class berdasarkan tipe notifikasi
          switch ($notif['notification_type']) {
              case 'warning': $alertClass = 'alert-warning'; break;
              case 'success': $alertClass = 'alert-success'; break;
              case 'error':   $alertClass = 'alert-danger'; break;
              default:        $alertClass = 'alert-info'; break;
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
