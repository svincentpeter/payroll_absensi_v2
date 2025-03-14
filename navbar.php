<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/koneksi.php';

start_session_safe();

// Definisikan konstanta untuk format tanggal
define('DATE_FORMAT', 'F d, Y');

// Fungsi untuk mendapatkan full role dari session
function getFullRoleNavbar() {
    $userRole = $_SESSION['role'] ?? '';
    $userJobTitle = $_SESSION['job_title'] ?? '';
    if ($userRole !== 'M') {
        return $userRole;
    }
    $normalized = strtolower(trim($userJobTitle));
    if (strpos($normalized, 'superadmin') !== false) {
        return 'M:superadmin';
    }
    if (strpos($normalized, 'sdm') !== false) {
        return 'M:sdm';
    }
    if (strpos($normalized, 'keuangan') !== false) {
        return 'M:keuangan';
    }
    if (strpos($normalized, 'kepala sekolah') !== false) {
        return 'M:kepala sekolah';
    }
    return 'M';
}


// Ambil data dari session
$role     = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? '';
$nama     = $_SESSION['nama'] ?? $username;
$userId   = $_SESSION['id'] ?? 0;
$baseUrl  = getBaseUrl();
$nip      = $_SESSION['nip'] ?? '';
$fullRole = getFullRoleNavbar();

// Foto profil default
$foto = $_SESSION['foto_profil'] ?? ($baseUrl . '/assets/img/undraw_profile.svg');
if (empty($foto)) {
    $foto = $baseUrl . '/assets/img/undraw_profile.svg';
}

// ===============================
// PERHITUNGAN NOTIFIKASI ALERT
// ===============================
$sdmNotification    = "";
$sdmCount           = 0;
$keuNotification    = "";
$keuCount           = 0;
$guruNotification   = "";
$guruCount          = 0;
$kepsekNotification = "";
$kepsekCount        = 0;
$backupAlert        = "";
$backupAlertCount   = 0;

$currentDay   = (int) date('d');
$currentMonth = (int) date('n');
$currentYear  = (int) date('Y');

// 1. Notifikasi untuk M:sdm & M:superadmin
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

    $sqlSdm = "SELECT COUNT(*) AS pending
               FROM anggota_sekolah
               WHERE id NOT IN (
                   SELECT id_anggota FROM payroll_final
                   WHERE bulan = ? AND tahun = ?
               )";
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
            $sdmNotification = "Payroll Bulan {$monthName} terdapat {$pendingSdm} anggota belum dibayar";
            $sdmCount = 1;
        }
    } else {
        error_log("Gagal statement notifikasi SDM: " . $conn->error);
    }
    if ($currentDay >= 20 && $currentDay < 24) {
        $daysLeft = 24 - $currentDay;
        $sdmNotification .= ($sdmNotification ? " | " : "") . "Finalisasi payroll berakhir dalam {$daysLeft} hari";
        $sdmCount = 1;
    }
}

// 2. Notifikasi untuk M:keuangan & M:superadmin
if (in_array($fullRole, ['M:keuangan', 'M:superadmin'])) {
    $thisMonth = $currentMonth;
    $thisYear  = $currentYear;
    $sqlKeu = "SELECT COUNT(*) AS pending
               FROM payroll p
               WHERE p.bulan = ? 
                 AND p.tahun = ?
                 AND p.status = 'draft'
                 AND NOT EXISTS (
                     SELECT 1 FROM payroll_final pf
                     WHERE pf.id_anggota = p.id_anggota
                       AND pf.bulan = p.bulan
                       AND pf.tahun = p.tahun
                 )";
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
            $keuNotification = "Payroll Bulan {$monthName} (Draft) terdapat {$pendingKeu} anggota belum final";
            $keuCount = 1;
        }
    } else {
        error_log("Gagal statement notifikasi keuangan: " . $conn->error);
    }
}

// 3. Notifikasi untuk Guru (P, TK, guru)
if (in_array($fullRole, ['P', 'TK', 'guru'])) {
    $sqlGuru = "SELECT COUNT(*) AS pending 
                FROM pengajuan_ijin 
                WHERE nip = ? 
                  AND status IN ('Diterima','Ditolak') 
                  AND notified = 0";
    try {
        $stmtGuru = $conn->prepare($sqlGuru);
        if ($stmtGuru) {
            $stmtGuru->bind_param("s", $nip);
            $stmtGuru->execute();
            $resultGuru = $stmtGuru->get_result();
            $rowGuru = $resultGuru->fetch_assoc();
            $pendingGuru = intval($rowGuru['pending'] ?? 0);
            $stmtGuru->close();
            if ($pendingGuru > 0) {
                $guruNotification = "Terdapat {$pendingGuru} pengajuan izin yang telah diproses";
                $guruCount = 1;
            }
        } else {
            error_log("Gagal statement notifikasi Guru: " . $conn->error);
        }
    } catch (Exception $e) {
        error_log("Exception notifikasi Guru: " . $e->getMessage());
    }
}

// 4. Notifikasi untuk M:kepala sekolah
if ($fullRole === 'M:kepala sekolah') {
    $sqlKepsek = "SELECT COUNT(*) AS pending 
                  FROM pengajuan_ijin 
                  WHERE status_kepalasekolah = 'Pending'";
    $stmtKepsek = $conn->prepare($sqlKepsek);
    if ($stmtKepsek) {
        $stmtKepsek->execute();
        $resultKepsek = $stmtKepsek->get_result();
        $rowKepsek = $resultKepsek->fetch_assoc();
        $pendingKepsek = intval($rowKepsek['pending'] ?? 0);
        $stmtKepsek->close();
        if ($pendingKepsek > 0) {
            $kepsekNotification = "Terdapat {$pendingKepsek} pengajuan izin yang perlu ditinjau";
            $kepsekCount = 1;
        }
    } else {
        error_log("Gagal statement notifikasi Kepala Sekolah: " . $conn->error);
    }
}

// 5. Alert Backup Database untuk M:superadmin
$todayDay = (int) date('j');
if ($fullRole === 'M:superadmin' && $todayDay === 1) {
    if (empty($_SESSION['backup_alert_dismissed'])) {
        $backupAlert = "Ingat untuk Backup Database hari ini.";
        $backupAlertCount = 1;
    }
}

// 6. Ambil Manual Notifications
$manualNotifications = [];
$userIdSession = $_SESSION['id'] ?? 0;
if (!empty($fullRole) && !empty($userIdSession)) {
    $targetRoleForManual = strtolower($fullRole);
    $sqlManual = "SELECT * FROM notifications
                  WHERE role_target IN (?, 'all')
                    AND user_id = ?
                    AND is_read = 0
                  ORDER BY priority ASC, created_at DESC";
    $stmtManual = $conn->prepare($sqlManual);
    if ($stmtManual) {
        $stmtManual->bind_param("si", $targetRoleForManual, $userIdSession);
        $stmtManual->execute();
        $resManual = $stmtManual->get_result();
        while ($row = $resManual->fetch_assoc()) {
            $manualNotifications[] = $row;
        }
        $stmtManual->close();
    }
}

$totalAlerts = $sdmCount + $keuCount + $guruCount + $kepsekCount + $backupAlertCount + count($manualNotifications);

// ===============================
// PENGAMBILAN DATA PESAN (MESSAGES)
// ===============================
$messages = [];
$sqlPesan = "SELECT ls.*, sender.nama AS sender_name, receiver.nama AS receiver_name 
             FROM laporan_surat ls 
             LEFT JOIN anggota_sekolah sender ON ls.id_pengirim = sender.id 
             LEFT JOIN anggota_sekolah receiver ON ls.id_penerima = receiver.id 
             WHERE ls.id_pengirim = ? OR ls.id_penerima = ? 
             ORDER BY ls.tanggal_keluar DESC 
             LIMIT 5";
$stmtPesan = $conn->prepare($sqlPesan);
if ($stmtPesan) {
    $stmtPesan->bind_param("ii", $userId, $userId);
    $stmtPesan->execute();
    $resultPesan = $stmtPesan->get_result();
    while ($row = $resultPesan->fetch_assoc()) {
        $row['date'] = $row['tanggal_keluar'];
        $messages[] = $row;
    }
    $stmtPesan->close();
}
$messageCount = count($messages);
$latestMessages = $messages;

// Fungsi untuk format badge (dideklarasikan sekali)
function formatBadge($count) {
    if ($count < 1) {
        return "";
    }
    return ($count === 1) ? "1" : ($count . "+");
}
?>
<!-- CSS tambahan -->
<style>
    .navbar-nav .nav-item .nav-link {
        height: 50px; 
        display: flex;
        align-items: center;
    }
    .badge-counter {
        font-size: 0.75rem;
        position: absolute;
        transform: translate(50%, -50%);
        transform-origin: 50% 50%;
    }
</style>

<!-- Topbar -->
<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
    <!-- Tombol toggle sidebar (hanya muncul di layar kecil) -->
    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle me-3" type="button">
        <i class="fa fa-bars"></i>
    </button>

    <!-- Form Pencarian -->
    <form class="d-none d-sm-inline-block form-inline me-auto ms-md-3 my-2 my-md-0 mw-100 navbar-search">
        <div class="input-group">
            <input type="text" class="form-control bg-light border-0 small" placeholder="Search for..."
                   aria-label="Search" aria-describedby="searchAddon">
            <span class="input-group-text bg-primary text-white" id="searchAddon">
                <i class="fas fa-search fa-sm"></i>
            </span>
        </div>
    </form>

    <!-- Topbar Navbar -->
    <ul class="navbar-nav ms-auto">
        <!-- Dropdown Alerts -->
        <li class="nav-item dropdown no-arrow mx-1">
            <a class="nav-link dropdown-toggle position-relative" href="#" id="alertsDropdown"
               role="button" data-bs-toggle="dropdown" aria-expanded="false" onkeypress="if(event.key==='Enter'){this.click();}">
                <i class="fas fa-bell fa-fw"></i>
                <?php if ($totalAlerts > 0): ?>
                    <span class="badge bg-danger badge-counter">
                        <?= formatBadge($totalAlerts); ?>
                    </span>
                <?php endif; ?>
            </a>
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
                            <div class="small text-gray-500"><?= date(DATE_FORMAT); ?></div>
                            <span class="fw-bold"><?= htmlspecialchars($sdmNotification); ?></span>
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
                            <div class="small text-gray-500"><?= date(DATE_FORMAT); ?></div>
                            <span class="fw-bold"><?= htmlspecialchars($keuNotification); ?></span>
                        </div>
                    </a>
                <?php endif; ?>
                <?php if (!empty($guruNotification)): ?>
                    <a class="dropdown-item d-flex align-items-center" href="#">
                        <div class="me-3">
                            <div class="icon-circle bg-secondary">
                                <i class="fas fa-envelope-open text-white"></i>
                            </div>
                        </div>
                        <div>
                            <div class="small text-gray-500"><?= date(DATE_FORMAT); ?></div>
                            <span class="fw-bold"><?= htmlspecialchars($guruNotification); ?></span>
                        </div>
                    </a>
                <?php endif; ?>
                <?php if (!empty($kepsekNotification)): ?>
                    <a class="dropdown-item d-flex align-items-center" href="#">
                        <div class="me-3">
                            <div class="icon-circle bg-primary">
                                <i class="fas fa-user-tie text-white"></i>
                            </div>
                        </div>
                        <div>
                            <div class="small text-gray-500"><?= date(DATE_FORMAT); ?></div>
                            <span class="fw-bold"><?= htmlspecialchars($kepsekNotification); ?></span>
                        </div>
                    </a>
                <?php endif; ?>
                <?php if (!empty($backupAlert) && $fullRole === 'M:superadmin'): ?>
                    <a class="dropdown-item d-flex align-items-center backup-alert-item" href="<?= getBaseUrl(); ?>/payroll/superadmin/backup_database.php">
                        <div class="me-3">
                            <div class="icon-circle bg-danger">
                                <i class="fas fa-database text-white"></i>
                            </div>
                        </div>
                        <div>
                            <div class="small text-gray-500"><?= date(DATE_FORMAT); ?></div>
                            <span class="fw-bold"><?= htmlspecialchars($backupAlert); ?></span>
                        </div>
                    </a>
                <?php endif; ?>
                <?php if (!empty($manualNotifications)): ?>
                    <?php foreach ($manualNotifications as $mn): ?>
                        <?php 
                        switch ($mn['notification_type'] ?? 'info') {
                            case 'warning': $alertClass = 'alert-warning'; break;
                            case 'success': $alertClass = 'alert-success'; break;
                            case 'error':   $alertClass = 'alert-danger'; break;
                            default:        $alertClass = 'alert-info'; break;
                        }
                        ?>
                        <a class="dropdown-item d-flex align-items-center" href="<?= htmlspecialchars($mn['link'] ?? '#'); ?>">
                            <div class="me-3">
                                <div class="icon-circle <?= $alertClass; ?>">
                                    <i class="fas fa-bell text-white"></i>
                                </div>
                            </div>
                            <div>
                                <div class="small text-gray-500"><?= date(DATE_FORMAT, strtotime($mn['created_at'] ?? 'now')); ?></div>
                                <span class="fw-bold"><?= htmlspecialchars($mn['title'] ?? 'Notifikasi'); ?></span><br>
                                <span><?= htmlspecialchars($mn['message']); ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (
                    empty($sdmNotification) &&
                    empty($keuNotification) &&
                    empty($guruNotification) &&
                    empty($kepsekNotification) &&
                    empty($backupAlert) &&
                    empty($manualNotifications)
                ): ?>
                    <a class="dropdown-item text-center small text-gray-500" href="#">
                        No alerts available
                    </a>
                <?php endif; ?>
                <a class="dropdown-item text-center small text-gray-500" href="#">
                    Show All Alerts
                </a>
            </div>
        </li>
        <!-- Dropdown Messages -->
        <li class="nav-item dropdown no-arrow mx-1">
            <a class="nav-link dropdown-toggle position-relative" href="#" id="messagesDropdown"
               role="button" data-bs-toggle="dropdown" aria-expanded="false" onkeypress="if(event.key==='Enter'){this.click();}">
                <i class="fas fa-envelope fa-fw"></i>
                <?php if ($messageCount > 0): ?>
                    <span class="badge bg-danger badge-counter">
                        <?= formatBadge($messageCount); ?>
                    </span>
                <?php endif; ?>
            </a>
            <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="messagesDropdown">
                <h6 class="dropdown-header">Messages Center</h6>
                <?php if (!empty($latestMessages)): ?>
                    <?php foreach ($latestMessages as $msg): ?>
                        <a class="dropdown-item d-flex align-items-center message-item" href="<?= htmlspecialchars($msg['receiver_name'] ? getBaseUrl() . '/pesan.php' : '#'); ?>" data-id="<?= htmlspecialchars($msg['id'] ?? 0); ?>">
                            <div class="me-3">
                                <div class="icon-circle bg-primary">
                                    <i class="fas fa-envelope text-white"></i>
                                </div>
                            </div>
                            <div>
                                <div class="small text-gray-500"><?= date(DATE_FORMAT, strtotime($msg['date'])); ?></div>
                                <span class="fw-bold"><?= htmlspecialchars($msg['sender_name'] ?? $msg['title'] ?? 'Pesan'); ?></span><br>
                                <span><?= htmlspecialchars($msg['message'] ?? ''); ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <a class="dropdown-item text-center small text-gray-500" href="#">
                        No messages available
                    </a>
                <?php endif; ?>
                <a class="dropdown-item text-center small text-gray-500" href="<?= getBaseUrl(); ?>/pesan.php">
                    Show All Messages
                </a>
            </div>
        </li>
        <!-- Divider -->
        <div class="topbar-divider d-none d-sm-block"></div>
        <!-- User Information -->
        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle" href="#" id="userDropdown"
               role="button" data-bs-toggle="dropdown" aria-expanded="false" onkeypress="if(event.key==='Enter'){this.click();}">
                <span class="me-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars($nama); ?></span>
                <img class="img-profile rounded-circle" src="<?= htmlspecialchars($foto); ?>" alt="Profile Picture">
            </a>
            <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="userDropdown">
                <a class="dropdown-item" href="<?= getBaseUrl(); ?>/profile.php">
                    <i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Profile
                </a>
                <a class="dropdown-item" href="<?= getBaseUrl(); ?>/settings.php">
                    <i class="fas fa-cogs fa-sm fa-fw me-2 text-gray-400"></i> Settings
                </a>
                <a class="dropdown-item" href="<?= getBaseUrl(); ?>/activity_log.php">
                    <i class="fas fa-list fa-sm fa-fw me-2 text-gray-400"></i> Activity Log
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="<?= getBaseUrl(); ?>/logout.php">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i> Logout
                </a>
            </div>
        </li>
    </ul>
</nav>
<!-- End of Topbar -->

<!-- jQuery dan script untuk update status pesan serta menandai backup alert dismissed -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script>
$(document).ready(function(){
    $('.backup-alert-item').on('click', function(){
        $.post("<?= getBaseUrl(); ?>/navbar.php", { dismissed: 1 }, function(resp){
            console.log("Backup alert dismissed =>", resp);
        });
    });

    $('.message-item').on('click', function(e){
        e.preventDefault();
        var $this = $(this);
        var pesanId = $this.data('id');
        $.ajax({
            url: "<?= getBaseUrl(); ?>/laporan_surat.php?ajax=1",
            type: "POST",
            data: { case: "UpdateStatus", id: pesanId },
            dataType: "json",
            success: function(response) {
                if(response.code === 0){
                    $this.fadeOut(300, function(){
                        $(this).remove();
                        var $badge = $('.badge-counter');
                        var currentCount = parseInt($badge.text());
                        if(!isNaN(currentCount) && currentCount > 1){
                            $badge.text(currentCount - 1);
                        } else {
                            $badge.remove();
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
