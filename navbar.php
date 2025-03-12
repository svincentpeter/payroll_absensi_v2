<?php
// File: navbar.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn)) {
    require_once __DIR__ . '/koneksi.php';
}
require_once __DIR__ . '/helpers.php';

/**
 * Fungsi untuk mendapatkan base URL secara dinamis.
 * Jika aplikasi berada di root domain, tidak perlu menambahkan path.
 * Jika berada di subfolder, tambahkan path subfolder-nya.
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ||
                 $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    // Sesuaikan subfolder jika aplikasi Anda berada di subfolder, misal: '/gaji.nusaputera.id'
    $subfolder = ''; // kosong jika domain sudah mengarah langsung ke folder aplikasi
    return $protocol . $host . $subfolder;
}

// Ambil data dari session
$role     = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? '';
$nama     = $_SESSION['nama'] ?? $username;
$nip      = $_SESSION['nip'] ?? '';
$baseUrl  = getBaseUrl();

// Foto profil default
$foto = $_SESSION['foto_profil'] ?? $baseUrl . '/assets/img/undraw_profile.svg';
if (empty($foto)) {
    $foto = $baseUrl . '/assets/img/undraw_profile.svg';
}

// ---------------------------------------------------------------------------
// 1. NOTIFIKASI SDM / SUPERADMIN
// ---------------------------------------------------------------------------
$sdmNotification  = "";
$sdmCount         = 0;
$ijinNotification = "";
$ijinCount        = 0;

if (in_array($role, ['sdm', 'superadmin'])) {
    $currentDay   = (int) date('d');
    $currentMonth = (int) date('n');
    $currentYear  = (int) date('Y');

    // Logika payroll bulanan: jika >= 24, target bulan berikut
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

    // Cek pengajuan ijin (status kepsek=diterima, status=Pending)
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

// ---------------------------------------------------------------------------
// 2. NOTIFIKASI KEUANGAN / SUPERADMIN
// ---------------------------------------------------------------------------
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

// ---------------------------------------------------------------------------
// 3. ALERT BACKUP DATABASE untuk SUPERADMIN di TGL 1
// ---------------------------------------------------------------------------
$backupAlert      = "";
$backupAlertCount = 0;

// Tanggal 1
$todayDay = (int) date('j');
if ($role === 'superadmin' && $todayDay === 1) {
    if (empty($_SESSION['backup_alert_dismissed'])) {
        $backupAlert      = "Ingat untuk Backup Database hari ini.";
        $backupAlertCount = 1;
    }
}

// ---------------------------------------------------------------------------
// 4. Hitung total alert
// ---------------------------------------------------------------------------
$totalAlerts = $sdmCount + $ijinCount + $keuCount + $backupAlertCount;

// Fungsi pembantu menampilkan badge
function formatBadge($count) {
    return ($count < 1) ? "" : (($count === 1) ? "1" : ($count . "+"));
}

// ---------------------------------------------------------------------------
// 5. NOTIFIKASI PESAN (Role P, TK)
// ---------------------------------------------------------------------------
$messages    = [];
$unreadCount = 0;
if (in_array($role, ['P','TK'])) {
    $stmtMsg = $conn->prepare("
        SELECT ls.id, ls.judul, ls.isi, ls.tanggal_keluar, ls.status, sender.nama AS sender_name 
        FROM laporan_surat ls 
        LEFT JOIN anggota_sekolah sender ON ls.id_pengirim = sender.id 
        WHERE ls.id_penerima = ? 
        ORDER BY ls.tanggal_keluar DESC 
        LIMIT 5
    ");
    if ($stmtMsg) {
        $stmtMsg->bind_param("i", $_SESSION['id']);
        $stmtMsg->execute();
        $resultMsg = $stmtMsg->get_result();
        while ($row = $resultMsg->fetch_assoc()) {
            $messages[] = $row;
        }
        $stmtMsg->close();
    }
    $stmtUnread = $conn->prepare("
        SELECT COUNT(*) as unread 
        FROM laporan_surat 
        WHERE id_penerima = ? 
          AND status = 'terkirim'
    ");
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

<style>
/* Sedikit penyesuaian tinggi navbar & item */
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
    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle me-3">
        <i class="fa fa-bars"></i>
    </button>

    <!-- Form Pencarian (Topbar Search) -->
    <form class="d-none d-sm-inline-block form-inline me-auto ms-md-3 my-2 my-md-0 mw-100 navbar-search">
        <div class="input-group">
            <input type="text" class="form-control bg-light border-0 small" 
                   placeholder="Search for..."
                   aria-label="Search" aria-describedby="searchAddon">
            <span class="input-group-text bg-primary text-white" id="searchAddon">
                <i class="fas fa-search fa-sm"></i>
            </span>
        </div>
    </form>

    <!-- Topbar Navbar -->
    <ul class="navbar-nav ms-auto">

        <!-- Nav Item - Alerts -->
        <li class="nav-item dropdown no-arrow mx-1">
            <a class="nav-link dropdown-toggle position-relative" href="#" id="alertsDropdown" 
               role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-bell fa-fw"></i>
                <?php if ($totalAlerts > 0): ?>
                    <span class="badge bg-danger badge-counter">
                        <?= formatBadge($totalAlerts); ?>
                    </span>
                <?php endif; ?>
            </a>
            <!-- Dropdown - Alerts -->
            <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in" 
                 aria-labelledby="alertsDropdown">
                <h6 class="dropdown-header">Alerts Center</h6>

                <!-- Alert SDM -->
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

                <!-- Ijin -->
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

                <!-- Keuangan -->
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

                <!-- Backup Alert Superadmin -->
                <?php if (!empty($backupAlert)): ?>
                    <a class="dropdown-item d-flex align-items-center backup-alert-item" 
                       href="<?= getBaseUrl(); ?>/payroll/superadmin/backup_database.php">
                        <div class="me-3">
                            <div class="icon-circle bg-danger">
                                <i class="fas fa-database text-white"></i>
                            </div>
                        </div>
                        <div>
                            <div class="small text-gray-500"><?= date('F d, Y'); ?></div>
                            <span class="fw-bold"><?= htmlspecialchars($backupAlert); ?></span>
                        </div>
                    </a>
                <?php endif; ?>

                <!-- Jika semua notifikasi kosong -->
                <?php if (empty($sdmNotification) && empty($ijinNotification) && empty($keuNotification) && empty($backupAlert)): ?>
                    <a class="dropdown-item text-center small text-gray-500" href="#">
                        No alerts available
                    </a>
                <?php endif; ?>

                <a class="dropdown-item text-center small text-gray-500" href="#">
                    Show All Alerts
                </a>
            </div>
        </li>

        <!-- Nav Item - Messages (role P, TK) -->
        <?php if (in_array($role, ['P','TK'])): ?>
        <li class="nav-item dropdown no-arrow mx-1">
            <a class="nav-link dropdown-toggle position-relative" href="#" id="messagesDropdown" 
               role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-envelope fa-fw"></i>
                <?php if ($unreadCount > 0): ?>
                    <span class="badge bg-danger badge-counter"><?= $unreadCount; ?></span>
                <?php endif; ?>
            </a>
            <!-- Dropdown - Messages -->
            <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in" 
                 aria-labelledby="messagesDropdown">
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
                        <div class="dropdown-list-image me-3 position-relative">
                            <img class="rounded-circle" src="<?= getBaseUrl(); ?>/assets/img/undraw_profile_1.svg" alt="...">
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
                    <a class="dropdown-item text-center small text-gray-500" href="#">
                        Tidak ada pesan
                    </a>
                <?php endif; ?>
                <a class="dropdown-item text-center small text-gray-500" href="<?= getBaseUrl(); ?>/pesan.php">
                    Lihat Semua Pesan
                </a>
            </div>
        </li>
        <?php endif; ?>

        <!-- Garis pembatas -->
        <div class="topbar-divider d-none d-sm-block"></div>

        <!-- Nav Item - User Information -->
        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" 
               role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="me-2 d-none d-lg-inline text-gray-600 small">
                    <?= htmlspecialchars($nama); ?>
                </span>
                <img class="img-profile rounded-circle" src="<?= htmlspecialchars($foto); ?>">
            </a>
            <!-- Dropdown - User Information -->
            <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="userDropdown">
                <a class="dropdown-item" href="<?= getBaseUrl(); ?>/profile.php">
                    <i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i>
                    Profile
                </a>
                <a class="dropdown-item" href="<?= getBaseUrl(); ?>/settings.php">
                    <i class="fas fa-cogs fa-sm fa-fw me-2 text-gray-400"></i>
                    Settings
                </a>
                <a class="dropdown-item" href="<?= getBaseUrl(); ?>/activity_log.php">
                    <i class="fas fa-list fa-sm fa-fw me-2 text-gray-400"></i>
                    Activity Log
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="<?= getBaseUrl(); ?>/logout.php">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i>
                    Logout
                </a>
            </div>
        </li>

    </ul>
</nav>
<!-- End of Topbar -->

<!-- Script: jQuery untuk menandai backup alert dismissed & update status pesan -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script>
$(document).ready(function(){
    // 1) Backup alert, superadmin
    $('.backup-alert-item').on('click', function(){
        // Ajax POST ke file ini sendiri, menandai backup_alert_dismissed
        $.post("<?= getBaseUrl(); ?>/navbar.php", { dismissed: 1 }, function(resp){
            console.log("Backup alert dismissed =>", resp);
        });
    });

    // Jika request POST "dismissed=1" diterima, set session
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dismissed'])) {
        $_SESSION['backup_alert_dismissed'] = true;
        echo 'console.log("Session backup_alert_dismissed set to true");';
    }
    ?>

    // 2) Update status pesan
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
