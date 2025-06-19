<?php
// File: /payroll_absensi_v2/sdm/notifikasi_sdm.php

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../koneksi.php';

start_session_safe();

// Hanya SDM atau Superadmin
authorize(['M:SDM']);

$currentDay   = (int) date('d');
$currentMonth = (int) date('n');
$currentYear  = (int) date('Y');
$sdmNotifications = [];


// --- A) Pengajuan Izin Pending ---
$sqlIzinBaru = "SELECT COUNT(*) AS cnt FROM pengajuan_ijin WHERE status = 'Pending'";
$stmtIzinBaru = $conn->prepare($sqlIzinBaru);
if ($stmtIzinBaru) {
    $stmtIzinBaru->execute();
    $resIzinBaru = $stmtIzinBaru->get_result();
    $rowIzinBaru = $resIzinBaru->fetch_assoc();
    $stmtIzinBaru->close();
    $izinBaru = intval($rowIzinBaru['cnt'] ?? 0);
    if ($izinBaru > 0) {
        $sdmNotifications[] = [
            'title'   => 'Pengajuan Izin Pending',
            'message' => "Terdapat {$izinBaru} pengajuan izin (guru/karyawan) berstatus Pending.",
            'icon'    => 'fas fa-user-clock text-primary'
        ];
    }
}

// --- B) Karyawan Baru Belum Update ---
$sqlKaryawanBaru = "
    SELECT COUNT(*) AS cnt
    FROM anggota_sekolah
    WHERE join_start >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
      AND salary_index_id IS NULL
";
$stmtKaryawanBaru = $conn->prepare($sqlKaryawanBaru);
if ($stmtKaryawanBaru) {
    $stmtKaryawanBaru->execute();
    $resKaryawanBaru = $stmtKaryawanBaru->get_result();
    $rowKaryawanBaru = $resKaryawanBaru->fetch_assoc();
    $stmtKaryawanBaru->close();
    $baruCount = intval($rowKaryawanBaru['cnt'] ?? 0);
    if ($baruCount > 0) {
        $sdmNotifications[] = [
            'title'   => 'Karyawan Baru Belum Diupdate',
            'message' => "Ada {$baruCount} karyawan baru (7 hari terakhir) tanpa update salary.",
            'icon'    => 'fas fa-user-plus text-warning'
        ];
    }
}

// --- C) Ketidakhadiran Berulang (tanpa keterangan) ---
$sqlBolos = "
    SELECT COUNT(DISTINCT tanggal) AS cnt
    FROM absensi
    WHERE nip IN (
        SELECT nip FROM anggota_sekolah WHERE role IN ('P','TK')
    )
      AND status_kehadiran = 'tanpa_keterangan'
      AND MONTH(tanggal) = ?
      AND YEAR(tanggal)  = ?
";
$stmtBolos = $conn->prepare($sqlBolos);
if ($stmtBolos) {
    $stmtBolos->bind_param("ii", $currentMonth, $currentYear);
    $stmtBolos->execute();
    $resBolos = $stmtBolos->get_result();
    $rowBolos = $resBolos->fetch_assoc();
    $stmtBolos->close();
    $bolosCount = intval($rowBolos['cnt'] ?? 0);
    if ($bolosCount >= 3) {
        $sdmNotifications[] = [
            'title'   => 'Ketidakhadiran Berulang',
            'message' => "Terdapat {$bolosCount} hari dengan absensi tanpa keterangan.",
            'icon'    => 'fas fa-user-times text-danger'
        ];
    }
}

// --- D) Payroll Belum Final (mirip Bagian 5 & 6 di notifikasi) ---
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
    $stmtSdm->close();
    $pendingCountSdm = intval($rowSdm['pending'] ?? 0);
    if ($pendingCountSdm > 0) {
        $monthName = getIndonesianMonthName($targetMonth);
        $sdmNotifications[] = [
            'title'   => 'Payroll Belum Final',
            'message' => "Payroll bulan {$monthName} - {$targetYear}: {$pendingCountSdm} anggota belum final.",
            'icon'    => 'fas fa-money-check-alt text-info'
        ];
    }
}

// --- E) Absensi Invalid (valid=0) hari ini ---
$sqlKoreksi = "SELECT COUNT(*) AS cnt FROM absensi WHERE valid = 0 AND tanggal = CURDATE()";
$stmtKoreksi = $conn->prepare($sqlKoreksi);
if ($stmtKoreksi) {
    $stmtKoreksi->execute();
    $resKoreksi = $stmtKoreksi->get_result();
    $rowKoreksi = $resKoreksi->fetch_assoc();
    $stmtKoreksi->close();
    $pendingKoreksi = intval($rowKoreksi['cnt'] ?? 0);
    if ($pendingKoreksi > 0) {
        $sdmNotifications[] = [
            'title'   => 'Absensi Belum Valid',
            'message' => "Ada {$pendingKoreksi} data absensi hari ini yang perlu koreksi (valid=0).",
            'icon'    => 'fas fa-exclamation-circle text-danger'
        ];
    }
}

// --- F) Deadline Finalisasi Payroll (sisa <= 3 hari di bulan ini)
$lastDayOfMonth = (int) date("t");
$daysLeft = $lastDayOfMonth - $currentDay;
if ($daysLeft <= 3 && $daysLeft >= 0) {
    $sdmNotifications[] = [
        'title'   => 'Deadline Finalisasi Payroll',
        'message' => "Sisa {$daysLeft} hari sampai akhir bulan. Harap finalisasi payroll segera!",
        'icon'    => 'fas fa-hourglass-half text-warning'
    ];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Notifikasi SDM</title>
    <!-- SB Admin 2 + Bootstrap 5.3.3 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
    <style>
        /* ===== Page Title Styling ===== */
.page-title {
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    font-size: 2.5rem;
    color: #0d47a1;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-bottom: 3px solid #1976d2;
    padding-bottom: 0.3rem;
    margin-bottom: 1.5rem;
    animation: fadeInSlide 0.5s ease-in-out both;
}
.page-title i {
    color: #1976d2;
    font-size: 2.8rem;
}
    </style>
</head>

<body id="page-top">

    <div class="container my-4">

<h1 class="page-title">
        <i class="fas fa-bell"></i> Notifikasi SDM
    </h1>
        <?php if (empty($sdmNotifications)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i> Tidak ada notifikasi SDM saat ini.
            </div>
        <?php else: ?>
            <?php foreach ($sdmNotifications as $notif): ?>
                <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
                    <!-- Ikon custom -->
                    <i class="<?= $notif['icon'] ?? 'fas fa-info-circle'; ?> me-3" style="font-size:1.5rem;"></i>
                    <div>
                        <strong><?= htmlspecialchars($notif['title']); ?></strong><br>
                        <?= htmlspecialchars($notif['message']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <!-- Optional JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>