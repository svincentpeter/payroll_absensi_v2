<?php
// File: notifikasi.php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/koneksi.php';

start_session_safe();
generate_csrf_token();  // Pastikan token CSRF tersedia

/********************************************
 *  PROSES ENDPOINT: Mark Notification as Read
 ********************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'markRead') {
    // Mark a manual notification as read
    $notifId = intval($_POST['notifId'] ?? 0);
    if ($notifId > 0) {
        if ($stmtMark = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")) {
            $stmtMark->bind_param("i", $notifId);
            if ($stmtMark->execute()) {
                send_response(0, "Notification marked as read");
            } else {
                send_response(1, "Error marking notification as read: " . $stmtMark->error);
            }
            $stmtMark->close();
        } else {
            send_response(1, "Prepare error: " . $conn->error);
        }
    } else {
        send_response(1, "Invalid notification ID");
    }
}

/********************************************
 *  PROSES DISMISS BACKUP ALERT
 ********************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dismissed'])) {
    $_SESSION['backup_alert_dismissed'] = true;
    send_response(0, "Backup alert dismissed.");
}

/********************************************
 *  INISIALISASI NOTIFIKASI
 ********************************************/
$guruNotificationMsg       = "";
$kepsekNotificationMsg     = "";
$sdmNotificationMsg        = "";
$keuNotificationMsg        = "";
$backupAlertMsg            = "";
$systemAlertMsg            = ""; // untuk notifikasi error / audit

// Tambahan notifikasi baru
$guruExtraMsg              = ""; // untuk Guru: jadwal piket, absensi terlambat, izin mendekati tanggal mulai, slip gaji final.
$sdmExtraMsg               = ""; // untuk SDM: pengajuan izin baru, karyawan baru belum update, ketidakhadiran berulang.
$keuExtraMsg               = ""; // untuk Keuangan: adjustment rapel pending, jadwal pembayaran.

// Hitung jumlah notifikasi tiap kategori
$guruCount   = 0;
$kepsekCount = 0;
$sdmCount    = 0;
$keuCount    = 0;
$backupCount = 0;
$systemCount = 0;

$userId   = $_SESSION['user_id'] ?? 0;
$nip      = $_SESSION['nip'] ?? '';
$fullRole = getFullRole();

$currentDay   = (int) date('d');
$currentMonth = (int) date('n');
$currentYear  = (int) date('Y');

/*
 * Kita menggunakan fungsi getIndonesianMonthName() dari helpers.php.
 */

/********************************************
 * NOTIFIKASI UNTUK GURU / KARYAWAN (Role: P, TK)
 ********************************************/
if (in_array($fullRole, ['P', 'TK'])) {
    // 1. Pengajuan Izin yang telah diproses (status selain Pending)
    $sqlGuruIzin = "SELECT COUNT(*) AS cnt FROM pengajuan_ijin WHERE nip = ? AND status <> 'Pending'";
    if ($stmtGuru = $conn->prepare($sqlGuruIzin)) {
        $stmtGuru->bind_param("s", $nip);
        if ($stmtGuru->execute()) {
            $resGuru = $stmtGuru->get_result();
            if ($rowGuru = $resGuru->fetch_assoc()) {
                $processedIzin = intval($rowGuru['cnt'] ?? 0);
                if ($processedIzin > 0) {
                    $guruNotificationMsg = "Anda memiliki {$processedIzin} pengajuan izin yang telah diproses.";
                    $guruCount++;
                }
            }
        } else {
            log_error("Execute query pengajuan izin gagal: " . $stmtGuru->error);
        }
        $stmtGuru->close();
    } else {
        log_error("Prepare query pengajuan izin gagal: " . $conn->error);
    }
    // 2. Jadwal Piket Hari Ini
    $sqlPiket = "SELECT waktu_piket FROM jadwal_piket WHERE nip = ? AND tanggal = CURDATE()";
    if ($stmtPiket = $conn->prepare($sqlPiket)) {
        $stmtPiket->bind_param("s", $nip);
        if ($stmtPiket->execute()) {
            $resPiket = $stmtPiket->get_result();
            if ($rowPiket = $resPiket->fetch_assoc()) {
                $waktu = $rowPiket['waktu_piket'];
                $guruExtraMsg .= "Hari ini Anda memiliki jadwal piket pada jam {$waktu}. ";
                $guruCount++;
            }
        } else {
            log_error("Execute query jadwal piket gagal: " . $stmtPiket->error);
        }
        $stmtPiket->close();
    }
    // 3. Teguran Absensi Terlambat (jika terlambat ≥ 3 kali)
    $sqlTerlambat = "SELECT COUNT(*) AS cnt FROM absensi WHERE nip = ? AND terlambat = 1 AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?";
    if ($stmtTerlambat = $conn->prepare($sqlTerlambat)) {
        $stmtTerlambat->bind_param("sii", $nip, $currentMonth, $currentYear);
        if ($stmtTerlambat->execute()) {
            $resTerlambat = $stmtTerlambat->get_result();
            if ($rowTerlambat = $resTerlambat->fetch_assoc()) {
                $countTerlambat = intval($rowTerlambat['cnt'] ?? 0);
                if ($countTerlambat >= 3) {
                    $guruExtraMsg .= "Anda telah terlambat sebanyak {$countTerlambat} kali bulan ini. ";
                    $guruCount++;
                }
            }
        } else {
            log_error("Execute query terlambat gagal: " . $stmtTerlambat->error);
        }
        $stmtTerlambat->close();
    }
    // 4. Pengajuan Izin Mendekati Tanggal Mulai (1 hari sebelum)
    $sqlIzinBesok = "SELECT COUNT(*) AS cnt FROM pengajuan_ijin WHERE nip = ? AND status = 'Diterima' AND DATEDIFF(STR_TO_DATE(tanggal, '%Y-%m-%d'), CURDATE()) = 1";
    if ($stmtIzinBesok = $conn->prepare($sqlIzinBesok)) {
        $stmtIzinBesok->bind_param("s", $nip);
        if ($stmtIzinBesok->execute()) {
            $resIzinBesok = $stmtIzinBesok->get_result();
            if ($rowIzinBesok = $resIzinBesok->fetch_assoc()) {
                $izinBesok = intval($rowIzinBesok['cnt'] ?? 0);
                if ($izinBesok > 0) {
                    $guruExtraMsg .= "Pengajuan izin Anda akan mulai besok. ";
                    $guruCount++;
                }
            }
        } else {
            log_error("Execute query izin besok gagal: " . $stmtIzinBesok->error);
        }
        $stmtIzinBesok->close();
    }
    // 5. Slip Gaji Final Tersedia (untuk bulan sebelumnya)
    $sqlSlipGaji = "SELECT COUNT(*) AS cnt FROM payroll_final pf JOIN anggota_sekolah a ON pf.id_anggota = a.id WHERE a.nip = ? AND pf.tahun = ? AND pf.bulan = ?";
    $targetMonthSlip = ($currentMonth == 1) ? 12 : $currentMonth - 1;
    $targetYearSlip = ($currentMonth == 1) ? $currentYear - 1 : $currentYear;
    if ($stmtSlipGaji = $conn->prepare($sqlSlipGaji)) {
        $stmtSlipGaji->bind_param("sii", $nip, $targetYearSlip, $targetMonthSlip);
        if ($stmtSlipGaji->execute()) {
            $resSlipGaji = $stmtSlipGaji->get_result();
            if ($rowSlipGaji = $resSlipGaji->fetch_assoc()) {
                $slipCount = intval($rowSlipGaji['cnt'] ?? 0);
                if ($slipCount > 0) {
                    $bulanNama = getIndonesianMonthName($targetMonthSlip);
                    $guruExtraMsg .= "Slip gaji bulan {$bulanNama} sudah tersedia. ";
                    $guruCount++;
                }
            }
        } else {
            log_error("Execute query slip gaji gagal: " . $stmtSlipGaji->error);
        }
        $stmtSlipGaji->close();
    }
    if (!empty($guruExtraMsg)) {
        $guruNotificationMsg .= " " . $guruExtraMsg;
    }
}

/********************************************
 * NOTIFIKASI UNTUK SDM (M:sdm)
 ********************************************/
if ($fullRole === 'M:sdm') {
    // 1. Izin Baru dari Guru (status pending)
    $sqlIzinBaru = "SELECT COUNT(*) AS cnt FROM pengajuan_ijin WHERE status = 'Pending'";
    if ($stmtIzinBaru = $conn->prepare($sqlIzinBaru)) {
        if ($stmtIzinBaru->execute()) {
            $resIzinBaru = $stmtIzinBaru->get_result();
            if ($rowIzinBaru = $resIzinBaru->fetch_assoc()) {
                $izinBaru = intval($rowIzinBaru['cnt'] ?? 0);
                if ($izinBaru > 0) {
                    $sdmNotificationMsg .= "Terdapat {$izinBaru} pengajuan izin baru. ";
                    $sdmCount++;
                }
            }
        } else {
            log_error("Execute query izin baru gagal: " . $stmtIzinBaru->error);
        }
        $stmtIzinBaru->close();
    }
    // 2. Karyawan Baru yang Belum Update Salary (join dalam 7 hari terakhir)
    $sqlKaryawanBaru = "SELECT COUNT(*) AS cnt FROM anggota_sekolah WHERE join_start >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND salary_index_id IS NULL";
    if ($stmtKaryawanBaru = $conn->prepare($sqlKaryawanBaru)) {
        if ($stmtKaryawanBaru->execute()) {
            $resKaryawanBaru = $stmtKaryawanBaru->get_result();
            if ($rowKaryawanBaru = $resKaryawanBaru->fetch_assoc()) {
                $baruCount = intval($rowKaryawanBaru['cnt'] ?? 0);
                if ($baruCount > 0) {
                    $sdmNotificationMsg .= "Terdapat {$baruCount} karyawan baru yang belum diupdate data salary-nya. ";
                    $sdmCount++;
                }
            }
        } else {
            log_error("Execute query karyawan baru gagal: " . $stmtKaryawanBaru->error);
        }
        $stmtKaryawanBaru->close();
    }
    // 3. Ketidakhadiran Berulang (absensi 'tanpa_keterangan' ≥ 3 hari unik)
    $sqlBolos = "SELECT COUNT(DISTINCT tanggal) AS cnt FROM absensi WHERE nip IN (SELECT nip FROM anggota_sekolah WHERE role IN ('P','TK')) AND status_kehadiran = 'tanpa_keterangan' AND MONTH(tanggal)=? AND YEAR(tanggal)=?";
    if ($stmtBolos = $conn->prepare($sqlBolos)) {
        $stmtBolos->bind_param("ii", $currentMonth, $currentYear);
        if ($stmtBolos->execute()) {
            $resBolos = $stmtBolos->get_result();
            if ($rowBolos = $resBolos->fetch_assoc()) {
                $bolosCount = intval($rowBolos['cnt'] ?? 0);
                if ($bolosCount >= 3) {
                    $sdmNotificationMsg .= "Terdapat sejumlah hari ketidakhadiran berulang (bolos/tanpa keterangan). ";
                    $sdmCount++;
                }
            }
        } else {
            log_error("Execute query bolos gagal: " . $stmtBolos->error);
        }
        $stmtBolos->close();
    }
    // 4. Notifikasi Payroll yang Belum Dibayar
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
    $sqlSdm = "SELECT COUNT(*) AS pending FROM anggota_sekolah WHERE id NOT IN (
                SELECT id_anggota FROM payroll_final WHERE bulan = ? AND tahun = ?
              )";
    if ($stmtSdm = $conn->prepare($sqlSdm)) {
        $stmtSdm->bind_param("ii", $targetMonth, $targetYear);
        if ($stmtSdm->execute()) {
            $resSdm = $stmtSdm->get_result();
            if ($rowSdm = $resSdm->fetch_assoc()) {
                $pendingCountSdm = intval($rowSdm['pending'] ?? 0);
                if ($pendingCountSdm > 0) {
                    $monthName = getIndonesianMonthName($targetMonth);
                    $sdmNotificationMsg .= "Payroll Bulan {$monthName} terdapat {$pendingCountSdm} anggota belum dibayar. ";
                    $sdmCount++;
                }
            }
        } else {
            log_error("Execute query payroll pending gagal: " . $stmtSdm->error);
        }
        $stmtSdm->close();
    }
    // 5. Absensi Perlu Koreksi (valid = 0 pada CURDATE())
    $sqlKoreksi = "SELECT COUNT(*) AS cnt FROM absensi WHERE valid = 0 AND tanggal = CURDATE()";
    if ($stmtKoreksi = $conn->prepare($sqlKoreksi)) {
        if ($stmtKoreksi->execute()) {
            $resKoreksi = $stmtKoreksi->get_result();
            if ($rowKoreksi = $resKoreksi->fetch_assoc()) {
                $pendingKoreksi = intval($rowKoreksi['cnt'] ?? 0);
                if ($pendingKoreksi > 0) {
                    $sdmNotificationMsg .= "Terdapat {$pendingKoreksi} data absensi yang perlu dikoreksi. ";
                    $sdmCount++;
                }
            }
        } else {
            log_error("Execute query absensi koreksi gagal: " . $stmtKoreksi->error);
        }
        $stmtKoreksi->close();
    }
    // 6. Pengingat Finalisasi Payroll (jika hari tersisa ≤ 3)
    $lastDayOfMonth = (int) date("t");
    if (($lastDayOfMonth - $currentDay) <= 3) {
        $daysLeft = $lastDayOfMonth - $currentDay;
        $sdmNotificationMsg .= "Segera finalisasi payroll, tinggal {$daysLeft} hari tersisa. ";
        $sdmCount++;
    }
}

/********************************************
 * NOTIFIKASI UNTUK KEUANGAN (M:keuangan & M:superadmin)
 ********************************************/
if (in_array($fullRole, ['M:keuangan', 'M:superadmin'])) {
    $targetMonth = $currentMonth;
    $targetYear  = $currentYear;
    $sqlKeu = "SELECT COUNT(*) AS pending FROM payroll p
            WHERE p.bulan = ? 
              AND p.tahun = ?
              AND p.status = 'draft'
              AND NOT EXISTS (
                    SELECT 1 FROM payroll_final pf
                    WHERE pf.id_anggota = p.id_anggota
                      AND pf.bulan = p.bulan
                      AND pf.tahun = p.tahun
              )";
    if ($stmtKeu = $conn->prepare($sqlKeu)) {
        $stmtKeu->bind_param("ii", $targetMonth, $targetYear);
        if ($stmtKeu->execute()) {
            $resKeu = $stmtKeu->get_result();
            if ($rowKeu = $resKeu->fetch_assoc()) {
                $pendingCountKeu = intval($rowKeu['pending'] ?? 0);
                if ($pendingCountKeu > 0) {
                    $monthName = getIndonesianMonthName($targetMonth);
                    $keuNotificationMsg .= "Payroll Bulan {$monthName} (Draft) terdapat {$pendingCountKeu} anggota belum final. ";
                    $keuCount++;
                }
            }
        } else {
            log_error("Execute query payroll draft gagal: " . $stmtKeu->error);
        }
        $stmtKeu->close();
    }
    // Cek Error Perhitungan Payroll (selisih > 1000)
    $sqlError = "SELECT COUNT(*) AS cnt FROM payroll WHERE ABS(total_pendapatan - total_potongan - gaji_bersih) > 1000";
    if ($stmtError = $conn->prepare($sqlError)) {
        if ($stmtError->execute()) {
            $resError = $stmtError->get_result();
            if ($rowError = $resError->fetch_assoc()) {
                $errorCount = intval($rowError['cnt'] ?? 0);
                if ($errorCount > 0) {
                    $keuNotificationMsg .= "Terjadi {$errorCount} error pada perhitungan payroll. ";
                    $keuCount++;
                }
            }
        } else {
            log_error("Execute query error payroll gagal: " . $stmtError->error);
        }
        $stmtError->close();
    }
    if (!empty($keuExtraMsg)) {
        $keuNotificationMsg .= " " . $keuExtraMsg;
    }
}

/********************************************
 * NOTIFIKASI UNTUK SUPERADMIN
 ********************************************/
if ($fullRole === 'M:superadmin') {
    // Backup Alert: hanya jika hari pertama bulan dan belum di-dismiss
    if ($currentDay === 1 && empty($_SESSION['backup_alert_dismissed'])) {
        $backupAlertMsg = "Ingat untuk Backup Database hari ini. ";
        $backupCount++;
    }
    // Notifikasi Audit / Sistem (cek audit_logs error selama 24 jam terakhir)
    $sqlSys = "SELECT COUNT(*) AS cnt FROM audit_logs WHERE action LIKE '%error%' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
    if ($stmtSys = $conn->prepare($sqlSys)) {
        if ($stmtSys->execute()) {
            $resSys = $stmtSys->get_result();
            if ($rowSys = $resSys->fetch_assoc()) {
                $errorLogs = intval($rowSys['cnt'] ?? 0);
                if ($errorLogs > 0) {
                    $systemAlertMsg = "Terdapat {$errorLogs} log error dalam 24 jam terakhir. Silakan periksa logs untuk detail. ";
                    $systemCount++;
                }
            }
        } else {
            log_error("Execute query audit logs gagal: " . $stmtSys->error);
        }
        $stmtSys->close();
    }
}

/********************************************
 * NOTIFIKASI MANUAL (dari tabel notifications)
 ********************************************/
$manualNotifications = [];
$sqlManual = "SELECT * FROM notifications WHERE role_target IN (?, 'all') AND user_id = ? AND is_read = 0 ORDER BY priority ASC, created_at DESC";
if ($stmtManual = $conn->prepare($sqlManual)) {
    $targetRoleForManual = strtolower($fullRole);
    $stmtManual->bind_param("si", $targetRoleForManual, $userId);
    if ($stmtManual->execute()) {
        $resManual = $stmtManual->get_result();
        while ($row = $resManual->fetch_assoc()) {
            $manualNotifications[] = $row;
        }
    } else {
        log_error("Execute query manual notifications gagal: " . $stmtManual->error);
    }
    $stmtManual->close();
}

$totalAlerts = $guruCount + $kepsekCount + $sdmCount + $keuCount + $backupCount + $systemCount + count($manualNotifications);

$conn->close();
?>

<!-- Tampilan Notifikasi (dibungkus dengan container agar bisa di-refresh secara realtime) -->
<div id="notification-container" class="container my-4">
  <?php if (in_array($fullRole, ['P', 'TK'])): ?>
    <?php if (!empty($guruNotificationMsg)): ?>
      <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-envelope-open-text me-2"></i>
        <!-- Gunakan timestamp dengan data attribute -->
        <div>
          <div class="small text-gray-500 timestamp" data-timestamp="<?= date('Y-m-d H:i:s'); ?>">
              <?= date(DATE_FORMAT); ?>
          </div>
          <?= htmlspecialchars($guruNotificationMsg); ?>
        </div>
      </div>
    <?php else: ?>
      <div class="alert alert-success mb-3">
        <i class="bi bi-check-circle-fill"></i> Tidak ada notifikasi baru untuk guru.
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($fullRole === 'M:kepala sekolah'): ?>
    <?php if (!empty($kepsekNotificationMsg)): ?>
      <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-chalkboard-teacher me-2"></i>
        <div>
          <div class="small text-gray-500 timestamp" data-timestamp="<?= date('Y-m-d H:i:s'); ?>">
              <?= date(DATE_FORMAT); ?>
          </div>
          <?= htmlspecialchars($kepsekNotificationMsg); ?>
        </div>
      </div>
    <?php else: ?>
      <div class="alert alert-success mb-3">
        <i class="bi bi-check-circle-fill"></i> Tidak ada notifikasi baru untuk kepala sekolah.
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($fullRole === 'M:sdm'): ?>
    <?php if (!empty($sdmNotificationMsg)): ?>
      <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-user-cog me-2"></i>
        <div>
          <div class="small text-gray-500 timestamp" data-timestamp="<?= date('Y-m-d H:i:s'); ?>">
              <?= date(DATE_FORMAT); ?>
          </div>
          <?= htmlspecialchars($sdmNotificationMsg); ?>
        </div>
      </div>
    <?php else: ?>
      <div class="alert alert-success mb-3">
        <i class="bi bi-check-circle-fill"></i> Tidak ada notifikasi baru untuk SDM.
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($fullRole === 'M:keuangan'): ?>
    <?php if (!empty($keuNotificationMsg)): ?>
      <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-calculator me-2"></i>
        <div>
          <div class="small text-gray-500 timestamp" data-timestamp="<?= date('Y-m-d H:i:s'); ?>">
              <?= date(DATE_FORMAT); ?>
          </div>
          <?= htmlspecialchars($keuNotificationMsg); ?>
        </div>
      </div>
    <?php else: ?>
      <div class="alert alert-success mb-3">
        <i class="bi bi-check-circle-fill"></i> Tidak ada notifikasi baru untuk keuangan.
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($fullRole === 'M:superadmin' && !empty($backupAlertMsg)): ?>
    <div class="alert alert-danger d-flex align-items-center mb-3 backup-alert-item" role="alert">
      <i class="fas fa-database me-2"></i>
      <div>
        <div class="small text-gray-500 timestamp" data-timestamp="<?= date('Y-m-d H:i:s'); ?>">
            <?= date(DATE_FORMAT); ?>
        </div>
        <?= htmlspecialchars($backupAlertMsg); ?>
        <a href="/payroll_absensi_v2/payroll/superadmin/backup_database.php" class="alert-link">[Backup Sekarang]</a>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($fullRole === 'M:superadmin' && !empty($systemAlertMsg)): ?>
    <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
      <i class="fas fa-exclamation-circle me-2"></i>
      <div>
        <div class="small text-gray-500 timestamp" data-timestamp="<?= date('Y-m-d H:i:s'); ?>">
            <?= date(DATE_FORMAT); ?>
        </div>
        <?= htmlspecialchars($systemAlertMsg); ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Notifikasi Manual -->
  <?php if (!empty($manualNotifications)): ?>
    <?php foreach ($manualNotifications as $notif):
        // Tentukan class alert berdasarkan notification_type
        switch ($notif['notification_type'] ?? 'info') {
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
      <a href="<?= htmlspecialchars($notif['link'] ?? '#'); ?>" class="dropdown-item d-flex align-items-center manual-notif" data-id="<?= htmlspecialchars($notif['id']); ?>">
        <div class="me-3">
          <div class="icon-circle <?= $alertClass; ?>">
            <i class="fas fa-bell text-white"></i>
          </div>
        </div>
        <div>
          <div class="small text-gray-500 timestamp" data-timestamp="<?= htmlspecialchars($notif['created_at']); ?>">
            <?= date(DATE_FORMAT, strtotime($notif['created_at'] ?? 'now')); ?>
          </div>
          <strong><?= htmlspecialchars($notif['title']); ?></strong><br>
          <?= htmlspecialchars($notif['message']); ?>
        </div>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="mt-4">
    <span class="badge bg-primary">Total Notifikasi: <?= $totalAlerts; ?></span>
  </div>
</div>

<!-- SCRIPT: Moment.js untuk relative time dan jQuery polling & mark manual notification as read -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Moment.js CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>

<script>
$(document).ready(function(){
    // Fungsi untuk perbaharui tampilan relative timestamp
    function updateTimestamps(){
        $('.timestamp').each(function(){
            var ts = $(this).data('timestamp');
            if(ts) {
                var relativeTime = moment(ts, "YYYY-MM-DD HH:mm:ss").fromNow();
                $(this).text(relativeTime);
            }
        });
    }
    updateTimestamps();
    
    // Polling: Refresh container notifikasi setiap 30 detik
    setInterval(function(){
        $("#notification-container").load("notifikasi.php #notification-container", function(){
            updateTimestamps();
        });
    }, 30000);
    
    // Mark manual notification as read ketika diklik
    $('.manual-notif').on('click', function(e){
        // Agar default link bisa tetap dijalankan setelah mark as read
        var notifId = $(this).data("id");
        var $thisItem = $(this);
        $.ajax({
            url: "notifikasi.php",
            type: "POST",
            data: { action: 'markRead', notifId: notifId },
            dataType: "json",
            success: function(response) {
                if(response.code === 0) {
                    // Sembunyikan notifikasi yang sudah di-mark as read
                    $thisItem.fadeOut(300, function(){
                        $(this).remove();
                    });
                }
            },
            error: function(){
                console.log("Gagal menandai notifikasi sebagai sudah dibaca.");
            }
        });
    });
});
</script>
