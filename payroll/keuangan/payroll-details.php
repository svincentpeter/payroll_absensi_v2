<?php
// File: /payroll_absensi_v2/payroll/keuangan/payroll-details.php

// =========================
// 1. Pengaturan Keamanan
// =========================

// Atur session cookie parameters sebelum session_start()
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => true,      // Hanya lewat HTTPS
    'httponly' => true,      // Tidak dapat diakses melalui JavaScript
    'samesite' => 'Strict'
]);

require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
generate_csrf_token();

// Buat nonce untuk CSP dan simpan di session
$nonce = base64_encode(random_bytes(16));
$_SESSION['csp_nonce'] = $nonce;

// Paksa HTTPS jika belum menggunakan HTTPS
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirect);
    exit();
}

// HSTS header
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

// Proteksi CSRF (sudah dibuat token-nya di atas)
generate_csrf_token();

// Pengecekan role
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['keuangan', 'superadmin'])) {
    header("Location: /payroll_absensi_v2/login.php");
    exit();
}

// Implementasi CSP dengan nonce
header("Content-Security-Policy: default-src 'self'; 
    script-src 'self' https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.jsdelivr.net 'nonce-$nonce'; 
    style-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net 'nonce-$nonce'; 
    img-src 'self'; 
    font-src 'self' https://cdnjs.cloudflare.com; 
    connect-src 'self'");

// Koneksi ke database
require_once __DIR__ . '/../../koneksi.php';


// =========================
// 2. Fungsi Pendukung & Inisialisasi
// =========================

/**
 * Fungsi konversi integer bulan ke nama bulan (Indonesia)
 */
function bulanIntToName($bulan) {
    $map = [
        1 => 'Januari',   2 => 'Februari', 3 => 'Maret',
        4 => 'April',     5 => 'Mei',      6 => 'Juni',
        7 => 'Juli',      8 => 'Agustus',  9 => 'September',
        10 => 'Oktober', 11 => 'November',12 => 'Desember'
    ];
    return isset($map[$bulan]) ? $map[$bulan] : 'Tidak Diketahui';
}

// Ambil parameter GET: id_payroll
$id_payroll = isset($_GET['id_payroll']) ? intval($_GET['id_payroll']) : 0;

if ($id_payroll <= 0) {
    echo "Parameter tidak valid.";
    exit();
}

try {
    // 2. Ambil data payroll
    $stmtPayroll = $conn->prepare("SELECT * FROM payroll WHERE id = ? LIMIT 1");
    if ($stmtPayroll === false) {
        throw new Exception("Prepare payroll gagal: " . $conn->error);
    }
    $stmtPayroll->bind_param("i", $id_payroll);
    $stmtPayroll->execute();
    $resPayroll = $stmtPayroll->get_result();
    if ($resPayroll->num_rows == 0) {
        throw new Exception("Payroll tidak ditemukan.");
    }
    $payroll = $resPayroll->fetch_assoc();
    $stmtPayroll->close();

    // 3. Ambil data karyawan
    $id_anggota = $payroll['id_anggota'];
    $stmtKar = $conn->prepare("SELECT * FROM anggota_sekolah WHERE id = ? LIMIT 1");
    if ($stmtKar === false) {
        throw new Exception("Prepare karyawan gagal: " . $conn->error);
    }
    $stmtKar->bind_param("i", $id_anggota);
    $stmtKar->execute();
    $resKar = $stmtKar->get_result();
    if ($resKar->num_rows == 0) {
        throw new Exception("Karyawan tidak ditemukan.");
    }
    $karyawan = $resKar->fetch_assoc();
    $stmtKar->close();

    // 4. Ambil detail payroll
    $stmtDetail = $conn->prepare("
        SELECT pd.*, ph.nama_payhead, ph.jenis 
        FROM payroll_detail pd
        JOIN payheads ph ON pd.id_payhead = ph.id
        WHERE pd.id_payroll = ?
    ");
    if ($stmtDetail === false) {
        throw new Exception("Prepare detail gagal: " . $conn->error);
    }
    $stmtDetail->bind_param("i", $id_payroll);
    $stmtDetail->execute();
    $resDetail = $stmtDetail->get_result();

    $details = [];
    while ($row = $resDetail->fetch_assoc()) {
        $details[] = $row;
    }
    $stmtDetail->close();

    // 5. Hitung ulang total pendapatan & potongan
    $gaji_pokok = isset($payroll['gaji_pokok']) ? (float)$payroll['gaji_pokok'] : 0;
    $total_pendapatan = 0;
    $total_potongan = 0;
    foreach ($details as $detail) {
        if (strtolower($detail['jenis']) === 'earnings') {
            $total_pendapatan += isset($detail['amount']) ? (float)$detail['amount'] : 0;
        } elseif (strtolower($detail['jenis']) === 'deductions') {
            $total_potongan += isset($detail['amount']) ? (float)$detail['amount'] : 0;
        }
    }
    $gaji_bersih = $gaji_pokok + $total_pendapatan - $total_potongan;

    // 6. Format tanggal pembuatan payroll
    $tglPayrollRaw = isset($payroll['tgl_payroll']) ? $payroll['tgl_payroll'] : null;
    if ($tglPayrollRaw) {
        $timestamp = strtotime($tglPayrollRaw);
        $tanggalCetak = date('d', $timestamp) . ' ' 
                        . bulanIntToName((int)date('n', $timestamp)) . ' ' 
                        . date('Y', $timestamp);
    } else {
        $tanggalCetak = bulanIntToName($payroll['bulan']) . ' ' . $payroll['tahun'];
    }

    // 7. Masa kerja karyawan
    $masaKerja = '';
    if (!empty($karyawan['masa_kerja_tahun']) || !empty($karyawan['masa_kerja_bulan'])) {
        $thn = isset($karyawan['masa_kerja_tahun']) ? (int)$karyawan['masa_kerja_tahun'] : 0;
        $bln = isset($karyawan['masa_kerja_bulan']) ? (int)$karyawan['masa_kerja_bulan'] : 0;
        $masaKerja = $thn . ' Tahun';
        if ($bln > 0) {
            $masaKerja .= ' ' . $bln . ' Bulan';
        }
    }

    // 8. Catatan (jika ada)
    $catatan = trim($payroll['catatan']);

    // 9. Tambahkan Audit Log untuk Viewing Payroll
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $details_log = "Mengakses Payroll ID $id_payroll untuk Karyawan ID $id_anggota pada bulan " . bulanIntToName($payroll['bulan']) . " tahun " . $payroll['tahun'] . ".";
    if (!add_audit_log($conn, $user_id, 'ViewPayrollDetails', $details_log)) {
        // Jika gagal mencatat audit log, tetap lanjutkan proses
        error_log("Gagal mencatat audit log untuk ViewPayrollDetails ID $id_payroll.");
    }

} catch (Exception $e) {
    echo "Terjadi kesalahan: " . htmlspecialchars($e->getMessage());
    exit();
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji #<?= htmlspecialchars($id_payroll); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Gunakan nonce pada elemen CSS jika diperlukan -->
    <link rel="stylesheet" href="/payroll_absensi_v2/dist/css/bootstrap.min.css" nonce="<?php echo $nonce; ?>">
    <style nonce="<?php echo $nonce; ?>">
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
            font-size: 16px;
            line-height: 24px;
            font-family: 'Helvetica Neue', 'Helvetica', Arial, sans-serif;
            color: #555;
            background-color: #fff;
        }
        .invoice-box table {
            width: 100%;
            text-align: left;
            border-collapse: collapse;
        }
        .invoice-box table td {
            padding: 5px;
            vertical-align: top;
        }
        .invoice-box table tr.top table td {
            padding-bottom: 20px;
        }
        .invoice-box table tr.top table td.title {
            font-size: 45px;
            color: #333;
        }
        .invoice-box table tr.information table td {
            padding-bottom: 40px;
        }
        .invoice-box table tr.heading td {
            background: #eee;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
        }
        .invoice-box table tr.details td {
            padding-bottom: 20px;
        }
        .invoice-box table tr.item td {
            border-bottom: 1px solid #eee;
        }
        .invoice-box table tr.item.last td {
            border-bottom: none;
        }
        .invoice-box table tr.total td {
            font-weight: bold;
        }
        @media print {  
            .btn-print { display: none; }  
            .invoice-box { margin: 0; padding: 0; }
        }
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .detail-table th, .detail-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        .detail-table th {
            background-color: #f2f2f2;
        }
        .left-align { text-align: left !important; }
        .btn-print {
            text-align: center;
            margin-top: 20px;
        }
        .btn-print button {
            padding: 10px 20px;
            font-size: 16px;
            background-color: #28a745;
            border: none;
            color: #fff;
            cursor: pointer;
            border-radius: 5px;
        }
        .btn-print button:hover {
            background-color: #218838;
        }
        .catatan-box {
            margin-top: 20px;
            padding: 10px;
            border-left: 4px solid #007bff;
            background-color: #f9f9f9;
        }
        .catatan-box h4 {
            margin: 0 0 5px;
            font-size: 18px;
            color: #007bff;
        }
    </style>
</head>
<body class="bg-light">
<div class="invoice-box">
    <table>
        <tr class="top">
            <td colspan="2">
                <table>
                    <tr>
                        <td class="title">
                            <!-- Logo Sekolah -->
                            <img src="/payroll_absensi_v2/assets/img/Logo.png" alt="Logo Sekolah" style="width:50%; max-width:100px;">
                        </td>
                        <td style="text-align:right;">
                            <strong>Slip Gaji #<?= htmlspecialchars($id_payroll); ?></strong><br>
                            Dibuat: <?= htmlspecialchars($tanggalCetak); ?><br>
                            Periode: <?= htmlspecialchars(bulanIntToName($payroll['bulan']) . ' ' . $payroll['tahun']); ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr class="information">
            <td colspan="2">
                <table>
                    <tr>
                        <td>
                            <strong>Sekolah Nasional Nusaputera</strong><br>
                            Jl. Karanganyar No. 574<br>
                            Semarang, Jawa Tengah<br>
                            (024) 3542444
                        </td>
                        <td style="text-align:right;">
                            <strong>Penerima:</strong><br>
                            <?= htmlspecialchars($karyawan['nama']); ?><br>
                            <?= htmlspecialchars($karyawan['email']); ?><br>
                            <?php if ($masaKerja): ?>
                                Masa Kerja: <?= htmlspecialchars($masaKerja); ?><br>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr class="heading">
            <td>Detail Pembayaran</td>
            <td></td>
        </tr>

        <tr class="details">
            <td>Gaji Pokok</td>
            <td style="text-align:right;">Rp <?= number_format($gaji_pokok, 2, ',', '.'); ?></td>
        </tr>

        <tr class="item">
            <td>Total Pendapatan</td>
            <td style="text-align:right;">Rp <?= number_format($total_pendapatan, 2, ',', '.'); ?></td>
        </tr>

        <tr class="item">
            <td>Total Potongan</td>
            <td style="text-align:right;">Rp <?= number_format($total_potongan, 2, ',', '.'); ?></td>
        </tr>

        <tr class="item last">
            <td>Gaji Bersih</td>
            <td style="text-align:right;">Rp <?= number_format($gaji_bersih, 2, ',', '.'); ?></td>
        </tr>

        <tr class="total">
            <td></td>
            <td style="text-align:right;">Total: Rp <?= number_format($gaji_bersih, 2, ',', '.'); ?></td>
        </tr>
    </table>

    <h3>Rincian Pendapatan &amp; Potongan</h3>
    <table class="detail-table">
        <tr class="heading">
            <th>No</th>
            <th class="left-align">Nama Payhead</th>
            <th>Jenis</th>
            <th>Jumlah</th>
        </tr>
        <?php
        $no = 1;
        foreach ($details as $detail) {
            echo "<tr>";
            echo "<td>" . $no . "</td>";
            echo "<td class='left-align'>" . htmlspecialchars($detail['nama_payhead'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars(translateJenis($detail['jenis'] ?? '')) . "</td>";
            echo "<td>Rp " . number_format($detail['amount'] ?? 0, 2, ',', '.') . "</td>";
            echo "</tr>";
            $no++;
        }
        ?>
    </table>

    <?php if (!empty($catatan)): ?>
    <div class="catatan-box">
        <h4>Catatan:</h4>
        <p><?= nl2br(htmlspecialchars($catatan)); ?></p>
    </div>
    <?php endif; ?>

    <div class="btn-print">
        <button onclick="window.print()">Cetak Slip Gaji</button>
    </div>
</div>
</body>
</html>
