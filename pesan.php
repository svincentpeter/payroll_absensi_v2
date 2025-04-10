<?php
// File: pesan.php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/koneksi.php';

// Mulai session dengan aman
start_session_safe();

// Gunakan authorize() untuk memastikan hanya user yang telah terautentikasi yang bisa mengakses halaman ini.
// Di sini, kita izinkan semua role: Guru/Karyawan (P, TK) dan manajerial (M, M:sdm, M:keuangan, M:superadmin, M:kepala sekolah)
authorize(['P', 'TK', 'M', 'M:sdm', 'M:keuangan', 'M:superadmin', 'M:kepala sekolah']);

// Ambil data user dari session
$userId       = $_SESSION['id'];
$userRole     = $_SESSION['role'] ?? '';
$userJobTitle = $_SESSION['job_title'] ?? '';
$fullRole     = getFullRole(); // Menghasilkan misal: "M:keuangan", "M:sdm", "P", "TK", dll.

// ============================
// Ambil pesan personal dari tabel laporan_surat
// ============================
$sql = "SELECT ls.*, 
               sender.nama AS sender_name, 
               receiver.nama AS receiver_name 
        FROM laporan_surat ls 
        LEFT JOIN anggota_sekolah sender ON ls.id_pengirim = sender.id 
        LEFT JOIN anggota_sekolah receiver ON ls.id_penerima = receiver.id 
        WHERE ls.id_pengirim = ? OR ls.id_penerima = ? 
        ORDER BY ls.tanggal_keluar DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

$personalMessages = [];
while ($row = $result->fetch_assoc()) {
    // Tambahkan properti display_date untuk tampilan standar (fallback)
    $row['display_date'] = date('Y-m-d H:i', strtotime($row['tanggal_keluar']));
    // Sertakan data timestamp mentah untuk Moment.js
    $row['data_timestamp'] = date('Y-m-d H:i:s', strtotime($row['tanggal_keluar']));
    $personalMessages[] = $row;
}
$stmt->close();

// ============================
// Ambil pesan sistem khusus (custom messages) berdasarkan role
// ============================
$customMessages = [];
$currentDay   = (int) date('d');
$currentMonth = (int) date('n');
$currentYear  = (int) date('Y');

// Fungsi bantu untuk mengkonversi nomor bulan ke nama bulan bahasa Indonesia
function getIndonesianMonthName($month)
{
    $months = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
    return $months[$month] ?? $month;
}

// --- Pesan untuk Guru / Karyawan (P, TK) ---
if (in_array($fullRole, ['P', 'TK'])) {
    $nip = $_SESSION['nip'] ?? '';
    // 1. Pengingat Jadwal Piket Hari Ini
    $sqlPiket = "SELECT waktu_piket FROM jadwal_piket WHERE nip = ? AND tanggal = CURDATE()";
    if ($stmtPiket = $conn->prepare($sqlPiket)) {
        $stmtPiket->bind_param("s", $nip);
        $stmtPiket->execute();
        $resPiket = $stmtPiket->get_result();
        if ($rowPiket = $resPiket->fetch_assoc()) {
            $customMessages[] = [
                'title'   => 'Jadwal Piket Hari Ini',
                'message' => "Anda memiliki jadwal piket hari ini pukul " . $rowPiket['waktu_piket'] . ".",
                'link'    => getBaseUrl() . '/jadwal_piket.php',
                'date'    => date('Y-m-d H:i:s')
            ];
        }
        $stmtPiket->close();
    }
    // 2. Peringatan Absensi Terlambat (jika terlambat ≥ 3 kali)
    $sqlTerlambat = "SELECT COUNT(*) AS cnt FROM absensi WHERE nip = ? AND terlambat = 1 AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?";
    if ($stmtTerlambat = $conn->prepare($sqlTerlambat)) {
        $stmtTerlambat->bind_param("sii", $nip, $currentMonth, $currentYear);
        $stmtTerlambat->execute();
        $resTerlambat = $stmtTerlambat->get_result();
        $rowTerlambat = $resTerlambat->fetch_assoc();
        $lateCount = intval($rowTerlambat['cnt'] ?? 0);
        $stmtTerlambat->close();
        if ($lateCount >= 3) {
            $customMessages[] = [
                'title'   => 'Peringatan Absensi Terlambat',
                'message' => "Anda telah terlambat sebanyak {$lateCount} kali bulan ini. Harap perhatikan ketepatan waktu.",
                'link'    => getBaseUrl() . '/absensi_history.php',
                'date'    => date('Y-m-d H:i:s')
            ];
        }
    }
    // 3. Pengajuan Izin Mendekati Tanggal Mulai (1 hari sebelum)
    $sqlIzinBesok = "SELECT COUNT(*) AS cnt FROM pengajuan_ijin WHERE nip = ? AND status = 'Diterima' AND DATEDIFF(STR_TO_DATE(tanggal, '%Y-%m-%d'), CURDATE()) = 1";
    if ($stmtIzinBesok = $conn->prepare($sqlIzinBesok)) {
        $stmtIzinBesok->bind_param("s", $nip);
        $stmtIzinBesok->execute();
        $resIzinBesok = $stmtIzinBesok->get_result();
        $rowIzinBesok = $resIzinBesok->fetch_assoc();
        $izinBesok = intval($rowIzinBesok['cnt'] ?? 0);
        $stmtIzinBesok->close();
        if ($izinBesok > 0) {
            $customMessages[] = [
                'title'   => 'Izin Akan Mulai Besok',
                'message' => "Pengajuan izin Anda akan mulai besok.",
                'link'    => getBaseUrl() . '/pengajuan_ijin.php',
                'date'    => date('Y-m-d H:i:s')
            ];
        }
    }
    // 4. Slip Gaji Final Tersedia (cek di payroll_final untuk bulan sebelumnya)
    $sqlSlipGaji = "SELECT COUNT(*) AS cnt FROM payroll_final pf JOIN anggota_sekolah a ON pf.id_anggota = a.id WHERE a.nip = ? AND pf.tahun = ? AND pf.bulan = ?";
    $targetMonthSlip = ($currentMonth == 1) ? 12 : $currentMonth - 1;
    $targetYearSlip = ($currentMonth == 1) ? $currentYear - 1 : $currentYear;
    if ($stmtSlipGaji = $conn->prepare($sqlSlipGaji)) {
        $stmtSlipGaji->bind_param("sii", $nip, $targetYearSlip, $targetMonthSlip);
        $stmtSlipGaji->execute();
        $resSlipGaji = $stmtSlipGaji->get_result();
        $rowSlipGaji = $resSlipGaji->fetch_assoc();
        $slipCount = intval($rowSlipGaji['cnt'] ?? 0);
        $stmtSlipGaji->close();
        if ($slipCount > 0) {
            $customMessages[] = [
                'title'   => 'Slip Gaji Tersedia',
                'message' => "Slip gaji untuk bulan " . getIndonesianMonthName($targetMonthSlip) . " sudah tersedia.",
                'link'    => getBaseUrl() . '/slip_gaji.php?bulan=' . $targetMonthSlip . '&tahun=' . $targetYearSlip,
                'date'    => date('Y-m-d H:i:s')
            ];
        }
    }
    // 5. Permintaan Tukar Jadwal (cek permintaan Pending untuk user)
    $sqlTukar = "SELECT COUNT(*) AS cnt FROM permintaan_tukar_jadwal WHERE nip_pengaju = ? AND status = 'Pending'";
    if ($stmtTukar = $conn->prepare($sqlTukar)) {
        $stmtTukar->bind_param("s", $nip);
        $stmtTukar->execute();
        $resTukar = $stmtTukar->get_result();
        $rowTukar = $resTukar->fetch_assoc();
        $tukarCount = intval($rowTukar['cnt'] ?? 0);
        $stmtTukar->close();
        if ($tukarCount > 0) {
            $customMessages[] = [
                'title'   => 'Permintaan Tukar Jadwal',
                'message' => "Anda memiliki {$tukarCount} permintaan tukar jadwal yang belum dibalas.",
                'link'    => getBaseUrl() . '/tukar_jadwal.php',
                'date'    => date('Y-m-d H:i:s')
            ];
        }
    }
}

// --- Pesan untuk SDM (M:sdm) ---
if ($fullRole === 'M:sdm') {
    // 1. Pengajuan Izin Baru (status Pending)
    $sqlIzinBaru = "SELECT COUNT(*) AS cnt FROM pengajuan_ijin WHERE status = 'Pending'";
    if ($stmtIzinBaru = $conn->prepare($sqlIzinBaru)) {
        $stmtIzinBaru->execute();
        $resIzinBaru = $stmtIzinBaru->get_result();
        $rowIzinBaru = $resIzinBaru->fetch_assoc();
        $izinBaru = intval($rowIzinBaru['cnt'] ?? 0);
        $stmtIzinBaru->close();
        if ($izinBaru > 0) {
            $customMessages[] = [
                'title'   => 'Pengajuan Izin Baru',
                'message' => "Terdapat {$izinBaru} pengajuan izin baru dari guru/karyawan.",
                'link'    => getBaseUrl() . '/pengajuan_ijin.php',
                'date'    => date('Y-m-d H:i:s')
            ];
        }
    }
    // 2. Karyawan Baru Belum Diperbarui (join dalam 7 hari terakhir)
    $sqlKaryawanBaru = "SELECT COUNT(*) AS cnt FROM anggota_sekolah WHERE join_start >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND salary_index_id IS NULL";
    if ($stmtKaryawanBaru = $conn->prepare($sqlKaryawanBaru)) {
        $stmtKaryawanBaru->execute();
        $resKaryawanBaru = $stmtKaryawanBaru->get_result();
        $rowKaryawanBaru = $resKaryawanBaru->fetch_assoc();
        $baruCount = intval($rowKaryawanBaru['cnt'] ?? 0);
        $stmtKaryawanBaru->close();
        if ($baruCount > 0) {
            $customMessages[] = [
                'title'   => 'Karyawan Baru Belum Diperbarui',
                'message' => "Terdapat {$baruCount} karyawan baru yang belum diperbarui data salary-nya.",
                'link'    => getBaseUrl() . '/data_karyawan.php',
                'date'    => date('Y-m-d H:i:s')
            ];
        }
    }
    // 3. Ketidakhadiran Berulang (absensi 'tanpa_keterangan' ≥ 3 hari unik)
    $sqlBolos = "SELECT COUNT(DISTINCT tanggal) AS cnt FROM absensi WHERE nip IN (SELECT nip FROM anggota_sekolah WHERE role IN ('P','TK')) AND status_kehadiran = 'tanpa_keterangan' AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?";
    if ($stmtBolos = $conn->prepare($sqlBolos)) {
        $stmtBolos->bind_param("ii", $currentMonth, $currentYear);
        $stmtBolos->execute();
        $resBolos = $stmtBolos->get_result();
        $rowBolos = $resBolos->fetch_assoc();
        $bolosCount = intval($rowBolos['cnt'] ?? 0);
        $stmtBolos->close();
        if ($bolosCount >= 3) {
            $customMessages[] = [
                'title'   => 'Ketidakhadiran Berulang',
                'message' => "Terdapat {$bolosCount} hari di mana terdapat absensi tanpa keterangan secara berulang.",
                'link'    => getBaseUrl() . '/rekap_absensi.php',
                'date'    => date('Y-m-d H:i:s')
            ];
        }
    }
}

// --- Pesan untuk Keuangan (M:keuangan) ---
if ($fullRole === 'M:keuangan') {
    // 1. Payroll Draft Belum Final
    $sqlDraftPayroll = "
        SELECT COUNT(*) AS cnt
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
    if ($stmtDraft = $conn->prepare($sqlDraftPayroll)) {
        $stmtDraft->bind_param("ii", $currentMonth, $currentYear);
        $stmtDraft->execute();
        $resDraft = $stmtDraft->get_result();
        $rowDraft = $resDraft->fetch_assoc();
        $draftCount = intval($rowDraft['cnt'] ?? 0);
        $stmtDraft->close();
        if ($draftCount > 0) {
            $monthName = getIndonesianMonthName($currentMonth);
            $customMessages[] = [
                'title'   => 'Payroll Draft Belum Final',
                'message' => "Terdapat {$draftCount} payroll draft untuk bulan {$monthName} yang belum di-finalkan.",
                'link'    => getBaseUrl() . '/payroll_draft.php',
                'date'    => date('Y-m-d H:i:s')
            ];
        }
    }
    // 2. Error Perhitungan Payroll
    $sqlErrorPayroll = "SELECT COUNT(*) AS cnt FROM payroll WHERE ABS(total_pendapatan - total_potongan - gaji_bersih) > 1000";
    if ($stmtErrorPayroll = $conn->prepare($sqlErrorPayroll)) {
        $stmtErrorPayroll->execute();
        $resErrorPayroll = $stmtErrorPayroll->get_result();
        $rowErrorPayroll = $resErrorPayroll->fetch_assoc();
        $errorPayrollCount = intval($rowErrorPayroll['cnt'] ?? 0);
        $stmtErrorPayroll->close();
        if ($errorPayrollCount > 0) {
            $customMessages[] = [
                'title'   => 'Error Perhitungan Payroll',
                'message' => "Terdeteksi {$errorPayrollCount} error pada perhitungan payroll. Mohon periksa kembali data.",
                'link'    => getBaseUrl() . '/cek_payroll.php',
                'date'    => date('Y-m-d H:i:s')
            ];
        }
    }
}

// --- Pesan untuk Superadmin (M:superadmin) ---
if ($fullRole === 'M:superadmin') {
    // 1. Backup Database Reminder
    if ($currentDay === 1 && empty($_SESSION['backup_alert_dismissed'])) {
        $customMessages[] = [
            'title'   => 'Backup Database',
            'message' => "Ingat untuk melakukan backup database hari ini.",
            'link'    => getBaseUrl() . '/payroll/superadmin/backup_database.php',
            'date'    => date('Y-m-d H:i:s')
        ];
    }
    // 2. Audit Logs Error Reminder
    $sqlAudit = "SELECT COUNT(*) AS cnt FROM audit_logs WHERE action LIKE '%error%' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
    if ($stmtAudit = $conn->prepare($sqlAudit)) {
        $stmtAudit->execute();
        $resAudit = $stmtAudit->get_result();
        $rowAudit = $resAudit->fetch_assoc();
        $auditErrorCount = intval($rowAudit['cnt'] ?? 0);
        $stmtAudit->close();
        if ($auditErrorCount > 0) {
            $customMessages[] = [
                'title'   => 'Audit Sistem',
                'message' => "Terdapat {$auditErrorCount} log error dalam 24 jam terakhir. Harap periksa audit logs.",
                'link'    => getBaseUrl() . '/audit_logs.php',
                'date'    => date('Y-m-d H:i:s')
            ];
        }
    }
}

// Gabungkan pesan sistem dengan pesan personal
$allMessages = array_merge($customMessages, $personalMessages);

// Urutkan semua pesan berdasarkan tanggal descending
usort($allMessages, function ($a, $b) {
    $dateA = isset($a['display_date']) ? strtotime($a['display_date']) : strtotime($a['date'] ?? 'now');
    $dateB = isset($b['display_date']) ? strtotime($b['display_date']) : strtotime($b['date'] ?? 'now');
    return $dateB - $dateA;
});

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Pesan Surat</title>
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-dC3e6uJll3WpvW1cz5qXfOzue+1t8J0d1Y1+e2kF/4t52uY1oD5UVpZ4KbbV84JxK9a6zTni6ZHBW6+0fllpNQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Bootstrap 5.3.3 CSS CDN -->
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-9ndCyUa6mYv+0cQKXH5Dk8ROJ0R2fzuy+kFsv+u78S5cRYPFfzqF4A/2P5F06F1p"
        crossorigin="anonymous">
</head>

<body>
    <?php include 'navbar.php'; ?>
    <div id="message-container" class="container my-4">
        <h1>Pesan Surat</h1>
        <?php if (empty($allMessages)): ?>
            <div class="alert alert-info">Tidak ada pesan surat atau notifikasi.</div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($allMessages as $msg): ?>
                    <a href="<?= isset($msg['link']) ? htmlspecialchars($msg['link']) : '#' ?>" 
                       class="list-group-item list-group-item-action message-item" 
                       data-id="<?= htmlspecialchars($msg['id'] ?? 0); ?>" 
                       data-timestamp="<?= htmlspecialchars($msg['date']); ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1"><?= htmlspecialchars($msg['title']) ?></h5>
                            <small class="timestamp" data-timestamp="<?= htmlspecialchars($msg['date']); ?>">
                                <?= date(DATE_FORMAT, strtotime($msg['date'] ?? 'now')) ?>
                            </small>
                        </div>
                        <p class="mb-1"><?= htmlspecialchars($msg['message'] ?? '') ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <!-- Bootstrap 5.3.3 JS Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ENjdO4Dr2bkBIFxQpeoOvZHnNQzYfC0RL5jJ5enq+QcPlj3x1p4cW4Md7o8Lk8UR"
        crossorigin="anonymous"></script>
    <!-- jQuery and Moment.js -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
    <script>
    $(document).ready(function(){
        // Fungsi untuk mengupdate timestamp menjadi relative time
        function updateTimestamps() {
            $('.timestamp').each(function() {
                var ts = $(this).data('timestamp');
                if(ts){
                    var relativeTime = moment(ts, "YYYY-MM-DD HH:mm:ss").fromNow();
                    $(this).text(relativeTime);
                }
            });
        }
        updateTimestamps();

        // Polling: Refresh pesan setiap 30 detik
        setInterval(function(){
            $("#message-container").load("pesan.php #message-container", function(){
                updateTimestamps();
                bindMessageClick();
            });
        }, 30000);

        // Mark pesan sebagai read saat di klik (asumsikan endpoint penandaan read telah dibuat di pesan.php)
        function bindMessageClick() {
            $('.message-item').off('click').on('click', function(e){
                // Jika ada aksi mark as read, kirim AJAX ke endpoint (Anda perlu menambahkan logika update di backend jika perlu)
                var pesanId = $(this).data("id");
                var $thisItem = $(this);
                $.ajax({
                    url: "update_message_status.php", // endpoint untuk mark message as read; sesuaikan nama file/URL
                    type: "POST",
                    data: { action: 'markRead', id: pesanId },
                    dataType: "json",
                    success: function(response) {
                        if(response.code === 0){
                            // Misal, setelah pesan di-mark, beri style berbeda atau hilangkan dari daftar
                            $thisItem.addClass('list-group-item-secondary');
                        }
                    },
                    error: function(){
                        console.log("Gagal menandai pesan sebagai sudah dibaca.");
                    }
                });
            });
        }
        bindMessageClick();
    });
    </script>
</body>

</html>
