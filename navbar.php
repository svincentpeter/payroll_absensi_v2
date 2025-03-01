<?php
// File: navbar.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn)) {
    require_once __DIR__ . '/koneksi.php';
}
require_once __DIR__ . '/helpers.php';

// Ambil data dari session
$role     = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? '';
$nama     = $_SESSION['nama'] ?? $username;
$nip      = $_SESSION['nip'] ?? '';

// Gunakan BASE_URL jika tersedia, atau ganti dengan URL root aplikasi Anda
$baseUrl = defined('BASE_URL') ? BASE_URL : '/payroll_absensi_v2';

// Gambar profil default; pastikan path default konsisten di seluruh aplikasi
$foto = $_SESSION['foto_profil'] ?? $baseUrl . '/assets/img/undraw_profile.svg';
if (empty($foto)) {
    $foto = $baseUrl . '/assets/img/undraw_profile.svg';
}

// ------------------------------------------------------------------------------------
// NOTIFIKASI untuk SDM/Superadmin
// ------------------------------------------------------------------------------------
$sdmNotification  = "";
$sdmCount         = 0;
$ijinNotification = "";
$ijinCount        = 0;

if (in_array($role, ['sdm', 'superadmin'])) {
    $currentDay   = (int) date('d');
    $currentMonth = (int) date('n');
    $currentYear  = (int) date('Y');

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
            SELECT id_anggota 
            FROM payroll_final
            WHERE bulan = ? AND tahun = ?
        )
    ";
    $stmtSdm = $conn->prepare($sqlSdm);
    if ($stmtSdm) {
        $stmtSdm->bind_param("ii", $targetMonth, $targetYear);
        $stmtSdm->execute();
        $resSdm = $stmtSdm->get_result();
        $rowSdm = $resSdm->fetch_assoc();
        $pendingSdm = intval($rowSdm['pending'] ?? 0);
        $stmtSdm->close();

        if ($pendingSdm > 0) {
            $monthName = getIndonesianMonthName($targetMonth);
            $sdmNotification = "Payroll Bulan {$monthName} Terdapat {$pendingSdm} Anggota Belum Dibayar";
            $sdmCount = 1;
        }
    } else {
        error_log("Gagal statement notifikasi sdm: " . $conn->error);
    }

    $sqlIjin = "
        SELECT COUNT(*) AS jml
        FROM pengajuan_ijin
        WHERE status_kepalasekolah = 'Diterima'
          AND status = 'Pending'
    ";
    $stmtIjin = $conn->prepare($sqlIjin);
    if ($stmtIjin) {
        $stmtIjin->execute();
        $resIjin = $stmtIjin->get_result();
        $rowIjin = $resIjin->fetch_assoc();
        $countIjin = intval($rowIjin['jml'] ?? 0);
        $stmtIjin->close();

        if ($countIjin > 0) {
            $ijinNotification = "Terdapat {$countIjin} Pengajuan Izin Baru (Menunggu SDM)";
            $ijinCount = 1;
        }
    } else {
        error_log("Gagal statement notifikasi ijin SDM: " . $conn->error);
    }
}

// ------------------------------------------------------------------------------------
// NOTIFIKASI untuk Keuangan/Superadmin
// ------------------------------------------------------------------------------------
$keuNotification = "";
$keuCount        = 0;

if (in_array($role, ['keuangan', 'superadmin'])) {
    $thisMonth = (int) date('n');
    $thisYear  = (int) date('Y');

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
        $stmtKeu->bind_param("ii", $thisMonth, $thisYear);
        $stmtKeu->execute();
        $resKeu = $stmtKeu->get_result();
        $rowKeu = $resKeu->fetch_assoc();
        $pendingKeu = intval($rowKeu['pending'] ?? 0);
        $stmtKeu->close();

        if ($pendingKeu > 0) {
            $monthName = getIndonesianMonthName($thisMonth);
            $keuNotification = "Payroll Bulan {$monthName} (Draft) Terdapat {$pendingKeu} Anggota Belum Final";
            $keuCount = 1;
        }
    } else {
        error_log("Gagal menyiapkan statement notifikasi keuangan: " . $conn->error);
    }
}

// ------------------------------------------------------------------------------------
// Total Alerts
// ------------------------------------------------------------------------------------
$totalAlerts = $sdmCount + $ijinCount + $keuCount;

function formatBadge($count) {
    if ($count < 1) return "";
    return ($count === 1) ? "1" : ($count . "+");
}

// --- PESAN: untuk role P dan TK ---
$messages = [];
$unreadCount = 0;
if (in_array($role, ['P','TK'])) {
    $stmtMsg = $conn->prepare("SELECT ls.id, ls.judul, ls.isi, ls.tanggal_keluar, ls.status, sender.nama AS sender_name 
                               FROM laporan_surat ls 
                               LEFT JOIN anggota_sekolah sender ON ls.id_pengirim = sender.id 
                               WHERE ls.id_penerima = ? 
                               ORDER BY ls.tanggal_keluar DESC 
                               LIMIT 5");
    if ($stmtMsg) {
        $stmtMsg->bind_param("i", $_SESSION['id']);
        $stmtMsg->execute();
        $resultMsg = $stmtMsg->get_result();
        while ($row = $resultMsg->fetch_assoc()) {
            $messages[] = $row;
        }
        $stmtMsg->close();
    }
    $stmtUnread = $conn->prepare("SELECT COUNT(*) as unread FROM laporan_surat WHERE id_penerima = ? AND status = 'terkirim'");
    if ($stmtUnread) {
        $stmtUnread->bind_param("i", $_SESSION['id']);
        $stmtUnread->execute();
        $resultUnread = $stmtUnread->get_result();
        $rowUnread = $resultUnread->fetch_assoc();
        $unreadCount = intval($rowUnread['unread'] ?? 0);
        $stmtUnread->close();
    }
}
?>

<!-- Topbar -->
<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

    <!-- Sidebar Toggle (Topbar) -->
    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle me-3">
        <i class="fa fa-bars"></i>
    </button>

    <!-- Topbar Search -->
    <form class="d-none d-sm-inline-block form-inline me-auto ms-md-3 my-2 my-md-0 mw-100 navbar-search">
        <div class="input-group">
            <input type="text" class="form-control bg-light border-0 small" placeholder="Search for..." aria-label="Search">
            <span class="input-group-text bg-primary text-white"><i class="fas fa-search fa-sm"></i></span>
        </div>
    </form>

    <!-- Topbar Navbar -->
    <ul class="navbar-nav ms-auto">

        <!-- Nav Item - Alerts -->
        <li class="nav-item dropdown no-arrow mx-1">
            <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-bell fa-fw"></i>
                <?php if ($totalAlerts > 0): ?>
                    <span class="badge bg-danger badge-counter"><?= formatBadge($totalAlerts); ?></span>
                <?php endif; ?>
            </a>
            <!-- Dropdown - Alerts -->
            <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="alertsDropdown">
                <h6 class="dropdown-header">Alerts Center</h6>
                <?php if (!empty($sdmNotification)): ?>
                    <a class="dropdown-item d-flex align-items-center" href="#">
                        <div class="me-3">
                            <div class="icon-circle bg-warning">
                                <i class="fas fa-exclamation-triangle text-white"></i>
                            </div>
                        </div>
                        <div>
                            <div class="small text-gray-500"><?= date('F d, Y'); ?></div>
                            <span class="fw-bold"><?= htmlspecialchars($sdmNotification); ?></span>
                        </div>
                    </a>
                <?php endif; ?>
                <?php if (!empty($ijinNotification)): ?>
                    <a class="dropdown-item d-flex align-items-center" href="#">
                        <div class="me-3">
                            <div class="icon-circle bg-success">
                                <i class="fas fa-envelope text-white"></i>
                            </div>
                        </div>
                        <div>
                            <div class="small text-gray-500"><?= date('F d, Y'); ?></div>
                            <span class="fw-bold"><?= htmlspecialchars($ijinNotification); ?></span>
                        </div>
                    </a>
                <?php endif; ?>
                <?php if (!empty($keuNotification)): ?>
                    <a class="dropdown-item d-flex align-items-center" href="#">
                        <div class="me-3">
                            <div class="icon-circle bg-info">
                                <i class="fas fa-info-circle text-white"></i>
                            </div>
                        </div>
                        <div>
                            <div class="small text-gray-500"><?= date('F d, Y'); ?></div>
                            <span class="fw-bold"><?= htmlspecialchars($keuNotification); ?></span>
                        </div>
                    </a>
                <?php endif; ?>
                <?php if (empty($sdmNotification) && empty($ijinNotification) && empty($keuNotification)): ?>
                    <a class="dropdown-item text-center small text-gray-500" href="#">No alerts available</a>
                <?php endif; ?>
                <a class="dropdown-item text-center small text-gray-500" href="#">Show All Alerts</a>
            </div>
        </li>

        <!-- Nav Item - Messages -->
        <?php if (in_array($role, ['P','TK'])): ?>
        <li class="nav-item dropdown no-arrow mx-1">
            <a class="nav-link dropdown-toggle" href="#" id="messagesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-envelope fa-fw"></i>
                <?php if ($unreadCount > 0): ?>
                    <span class="badge bg-danger badge-counter"><?= $unreadCount; ?></span>
                <?php endif; ?>
            </a>
            <!-- Dropdown - Messages -->
            <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="messagesDropdown">
                <h6 class="dropdown-header">Message Center</h6>
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $msg): 
                        $isiRingkas = mb_strimwidth(strip_tags($msg['isi']), 0, 100, "...");
                        $tgl = date('d M Y H:i', strtotime($msg['tanggal_keluar']));
                        $pengirim = htmlspecialchars($msg['sender_name'] ?? 'SDM/Superadmin');
                    ?>
                    <a class="dropdown-item d-flex align-items-center message-item" href="pesan_detail.php?id=<?= $msg['id']; ?>"
                       data-id="<?= $msg['id']; ?>"
                       data-judul="<?= htmlspecialchars($msg['judul']); ?>"
                       data-isi="<?= htmlspecialchars($msg['isi']); ?>"
                       data-pengirim="<?= $pengirim; ?>"
                       data-tanggal="<?= $tgl; ?>">
                        <div class="dropdown-list-image me-3">
                            <img class="rounded-circle" src="<?= $baseUrl; ?>/assets/img/undraw_profile_1.svg" alt="...">
                            <?php if($msg['status'] == 'terkirim'): ?>
                                <div class="status-indicator bg-danger"></div>
                            <?php else: ?>
                                <div class="status-indicator bg-success"></div>
                            <?php endif; ?>
                        </div>
                        <div class="fw-bold">
                            <div class="text-truncate"><?= htmlspecialchars($msg['judul']); ?></div>
                            <div class="small text-gray-500"><?= $pengirim; ?> · <?= $tgl; ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <a class="dropdown-item text-center small text-gray-500" href="#">Tidak ada pesan</a>
                <?php endif; ?>
                <a class="dropdown-item text-center small text-gray-500" href="<?= $baseUrl; ?>/pesan.php">Lihat Semua Pesan</a>
            </div>
        </li>
        <?php endif; ?>

        <div class="topbar-divider d-none d-sm-block"></div>

        <!-- Nav Item - User Information -->
        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="me-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars($nama); ?></span>
                <img class="img-profile rounded-circle" src="<?= htmlspecialchars($foto); ?>">
            </a>
            <!-- Dropdown - User Information -->
            <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="userDropdown">
                <a class="dropdown-item" href="<?= $baseUrl; ?>/profile.php">
                    <i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i>
                    Profile
                </a>
                <a class="dropdown-item" href="<?= $baseUrl; ?>/settings.php">
                    <i class="fas fa-cogs fa-sm fa-fw me-2 text-gray-400"></i>
                    Settings
                </a>
                <a class="dropdown-item" href="<?= $baseUrl; ?>/activity_log.php">
                    <i class="fas fa-list fa-sm fa-fw me-2 text-gray-400"></i>
                    Activity Log
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="<?= $baseUrl; ?>/logout.php">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i>
                    Logout
                </a>
            </div>
        </li>

    </ul>
</nav>
<!-- End of Topbar -->

<!-- Script untuk update status pesan ketika user klik salah satu pesan di dropdown -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script>
$(document).ready(function(){
    $('.message-item').on('click', function(e){
        var $this = $(this);
        var pesanId = $this.data('id');
        $.ajax({
            url: "<?= $baseUrl; ?>/laporan_surat.php?ajax=1",
            type: "POST",
            data: { case: "UpdateStatus", id: pesanId },
            dataType: "json",
            success: function(response) {
                if(response.code === 0){
                    $this.fadeOut(300, function(){
                        $(this).remove();
                        var currentCount = parseInt($('.badge-counter').text());
                        if(!isNaN(currentCount) && currentCount > 1){
                            $('.badge-counter').text(currentCount - 1);
                        } else {
                            $('.badge-counter').remove();
                        }
                    });
                }
            },
            error: function() {
                console.log("Gagal memperbarui status pesan.");
            }
        });
    });
});
</script>
