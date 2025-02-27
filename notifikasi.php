<?php
// File: notifikasi.php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/koneksi.php';

start_session_safe();

// Ambil role user
$userRole = $_SESSION['role'] ?? '';

// Ambil hari/bulan/tahun saat ini untuk penentuan default
$currentDay   = (int) date('d');
$currentMonth = (int) date('n');
$currentYear  = (int) date('Y');

/**
 * Untuk SDM/Superadmin: 
 *   Hitung berapa anggota yang belum final di `payroll_final` 
 *   (mirip notifikasi sdm).
 */
$sdmNotificationMsg = '';
if (in_array($userRole, ['sdm','superadmin'])) {
    // Jika tanggal >= 24, asumsikan bulan berikut
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

    // Cek anggota yang belum masuk payroll_final
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
            $sdmNotificationMsg = "Payroll Bulan {$monthName} Terdapat {$pendingCountSdm} Anggota Belum Dibayar";
        }
    } else {
        error_log("Gagal statement notifikasi SDM: " . $conn->error);
    }
}

/**
 * Untuk Keuangan/Superadmin:
 *   Hitung data `draft` di tabel `payroll` 
 *   (yang belum final di `payroll_final`), mirip list_payroll.
 */
$keuNotificationMsg = '';
if (in_array($userRole, ['keuangan','superadmin'])) {
    // Misal kita asumsikan user keuangan selalu memantau "bulan & tahun saat ini" 
    // atau sesuai logika tertentu. Di sini contoh paling sederhana:
    $targetMonth = $currentMonth;
    $targetYear  = $currentYear;

    // Cek payroll status draft, belum ada di payroll_final
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
            $keuNotificationMsg = "Payroll Bulan {$monthName} (Status Draft) Terdapat {$pendingCountKeu} Anggota Belum Final";
        }
    } else {
        error_log("Gagal statement notifikasi keuangan: " . $conn->error);
    }
}

// Tutup koneksi
$conn->close();
?>
<!-- 
  Bagian tampilan: 
  Kita buat 2 card notifikasi (SDM & Keuangan) 
  yang akan muncul hanya jika user termasuk role tsb. 
-->

<div class="container my-4">

  <!-- Notifikasi untuk SDM / superadmin -->
  <?php if (in_array($userRole, ['sdm','superadmin'])): ?>
    <?php if (!empty($sdmNotificationMsg)): ?>
      <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <div><?= htmlspecialchars($sdmNotificationMsg); ?></div>
      </div>
    <?php else: ?>
      <div class="alert alert-success mb-3">
        <i class="bi bi-check-circle-fill"></i> 
        Tidak ada anggota belum dibayar (SDM).
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Notifikasi untuk Keuangan / superadmin -->
  <?php if (in_array($userRole, ['keuangan','superadmin'])): ?>
    <?php if (!empty($keuNotificationMsg)): ?>
      <div class="alert alert-info d-flex align-items-center" role="alert">
        <i class="fas fa-info-circle me-2"></i>
        <div><?= htmlspecialchars($keuNotificationMsg); ?></div>
      </div>
    <?php else: ?>
      <div class="alert alert-success">
        <i class="bi bi-check-circle-fill"></i> 
        Tidak ada data payroll draft untuk keuangan.
      </div>
    <?php endif; ?>
  <?php endif; ?>

</div>
