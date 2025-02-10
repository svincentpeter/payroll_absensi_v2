<?php
// File: /payroll_absensi_v2/payroll/keuangan/payroll-details.php (fix)

// =========================
// 1. Pengaturan Keamanan
// =========================
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => true,   // Hanya lewat HTTPS
    'httponly' => true,
    'samesite' => 'Strict'
]);
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
generate_csrf_token();

$nonce = base64_encode(random_bytes(16));
$_SESSION['csp_nonce'] = $nonce;

if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirect);
    exit();
}
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("Content-Security-Policy: default-src 'self'; 
    script-src 'self' https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.jsdelivr.net 'nonce-$nonce'; 
    style-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net 'nonce-$nonce'; 
    img-src 'self'; 
    font-src 'self' https://cdnjs.cloudflare.com; 
    connect-src 'self'");

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['keuangan', 'superadmin'])) {
    header("Location: /payroll_absensi_v2/login.php");
    exit();
}

require_once __DIR__ . '/../../koneksi.php';


// =========================
// 2. Fungsi Pendukung
// =========================
function bulanIntToName($bulan) {
    $map = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
        4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September',
        10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    return isset($map[$bulan]) ? $map[$bulan] : 'Tidak Diketahui';
}


// =========================
// 3. Ambil Parameter & Data Payroll
// =========================
$id_payroll = isset($_GET['id_payroll']) ? intval($_GET['id_payroll']) : 0;
if ($id_payroll <= 0) {
    echo "Parameter tidak valid.";
    exit();
}

try {
    // Ambil data payroll
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

    // Ambil data karyawan
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

    // Ambil detail payroll
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

    // Hitung total pendapatan & potongan
    $gaji_pokok = isset($payroll['gaji_pokok']) ? (float)$payroll['gaji_pokok'] : 0;
    $total_pendapatan = 0;
    $total_potongan = 0;
    foreach ($details as $detail) {
        if (strtolower($detail['jenis']) === 'earnings') {
            $total_pendapatan += (float)$detail['amount'];
        } elseif (strtolower($detail['jenis']) === 'deductions') {
            $total_potongan += (float)$detail['amount'];
        }
    }
    $gaji_bersih = $gaji_pokok + $total_pendapatan - $total_potongan;

    // --- Ambil data Level Indeks (jika ada) ---
    $salary_index_level = '';
    $salary_index_amount = 0;
    if (!empty($karyawan['salary_index_id'])) {
        $stmtIndex = $conn->prepare("SELECT level, base_salary FROM salary_indices WHERE id = ?");
        if ($stmtIndex) {
            $stmtIndex->bind_param("i", $karyawan['salary_index_id']);
            $stmtIndex->execute();
            $resultIndex = $stmtIndex->get_result();
            if ($resultIndex->num_rows > 0) {
                $salaryData = $resultIndex->fetch_assoc();
                $salary_index_level = $salaryData['level'];
                $salary_index_amount = (float)$salaryData['base_salary'];
            }
            $stmtIndex->close();
        }
    }
    // Gaji pokok yang ditampilkan adalah gaji pokok employee + level indeks
    $gaji_pokok_employee = isset($karyawan['gaji_pokok']) ? (float)$karyawan['gaji_pokok'] : 0;
    $gaji_pokok = $gaji_pokok_employee + $salary_index_amount;

    // Format tanggal payroll
    $tglPayrollRaw = $payroll['tgl_payroll'];
    $timestamp = strtotime($tglPayrollRaw);
    $tanggalCetak = date('d', $timestamp) . ' ' . bulanIntToName((int)date('n', $timestamp)) . ' ' . date('Y', $timestamp);

    // Format periode
    $periode = bulanIntToName((int)$payroll['bulan']) . ' ' . $payroll['tahun'];

    // Masa kerja karyawan
    $thn = isset($karyawan['masa_kerja_tahun']) ? (int)$karyawan['masa_kerja_tahun'] : 0;
    $bln = isset($karyawan['masa_kerja_bulan']) ? (int)$karyawan['masa_kerja_bulan'] : 0;
    $masaKerja = ($thn > 0 || $bln > 0) ? $thn . " Tahun" . ($bln > 0 ? " " . $bln . " Bulan" : "") : "";

    // Nomor rekening: gunakan nilai dari karyawan, atau jika kosong, dari data payroll
    $noRek = !empty($karyawan['no_rekening']) 
             ? $karyawan['no_rekening'] 
             : (isset($payroll['no_rekening']) && !empty($payroll['no_rekening']) ? $payroll['no_rekening'] : 'Belum ada');
             
    if ($payroll['status'] !== 'final') {
                die("Slip gaji hanya tersedia untuk payroll yang sudah final.");
            }
            
    // Catatan payroll
    $catatan = trim($payroll['catatan']);

    // Audit Log
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $log_details = "Mengakses Payroll ID $id_payroll untuk Karyawan ID $id_anggota pada periode $periode.";
    if (!add_audit_log($conn, $user_id, 'ViewPayrollDetails', $log_details)) {
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
    <link rel="stylesheet" href="/payroll_absensi_v2/dist/css/bootstrap.min.css" nonce="<?php echo $nonce; ?>">
    <style nonce="<?php echo $nonce; ?>">
        body { background-color: #f8f9fa; }
        .invoice-box {
            max-width: 800px;
            margin: 30px auto;
            padding: 30px;
            background: #fff;
            border: 1px solid #dee2e6;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            font-size: 16px;
            line-height: 24px;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #343a40;
        }
        .invoice-box table {
            width: 100%;
            border-collapse: collapse;
        }
        .invoice-box table td {
            padding: 8px;
            vertical-align: top;
        }
        .invoice-box table tr.top table td {
            padding-bottom: 20px;
        }
        .invoice-box table tr.top table td.title {
            font-size: 36px;
            font-weight: bold;
            color: #007bff;
        }
        .invoice-box table tr.information table td {
            padding-bottom: 20px;
            font-size: 14px;
        }
        .invoice-box table tr.heading td {
            background: #e9ecef;
            border-bottom: 1px solid #dee2e6;
            font-weight: bold;
        }
        .invoice-box table tr.item td {
            border-bottom: 1px solid #dee2e6;
        }
        .invoice-box table tr.item.last td {
            border-bottom: none;
        }
        .invoice-box table tr.total td {
            font-weight: bold;
        }
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .detail-table th, .detail-table td {
            border: 1px solid #dee2e6;
            padding: 8px;
            text-align: center;
        }
        .detail-table th {
            background: #e9ecef;
        }
        .left-align { text-align: left; }
        .btn-print {
            text-align: center;
            margin-top: 30px;
        }
        .btn-print button {
            padding: 10px 20px;
            font-size: 16px;
            background: #28a745;
            border: none;
            color: #fff;
            cursor: pointer;
            border-radius: 4px;
        }
        .btn-print button:hover {
            background: #218838;
        }
        .catatan-box {
            margin-top: 20px;
            padding: 15px;
            border-left: 4px solid #007bff;
            background: #f1f1f1;
        }
        .catatan-box h4 {
            margin-top: 0;
            font-size: 18px;
            color: #007bff;
        }
        /* Tombol Kembali di pojok kiri atas */
        .btn-back {
            position: fixed;
            top: 10px;
            left: 10px;
            padding: 8px 12px;
            background: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            z-index: 1000;
        }
        .btn-back:hover {
            background: #0056b3;
        }
        /* Aturan untuk tampilan cetak (print) */
        @media print {
            /* Atur margin halaman cetak menjadi 20mm */
            @page {
                margin: 20mm;
            }
            body, .invoice-box {
                margin: 0;
                padding: 0;
                border: none;
                box-shadow: none;
                width: 100%;
            }
            /* Sembunyikan tombol cetak dan tombol kembali pada saat mencetak */
            .btn-print,
            .btn-back {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Tombol Kembali di pojok kiri atas -->
    <a href="payroll_history.php" class="btn-back">Kembali</a>
    
    <div class="invoice-box">
        <!-- Header Invoice -->
        <table>
            <tr class="top">
                <td colspan="2">
                    <table>
                        <tr>
                            <td class="title">
                                <img src="/payroll_absensi_v2/assets/img/Logo.png" alt="Logo Sekolah" style="max-width:100px;">
                            </td>
                            <td style="text-align:right;">
                                <strong>Slip Gaji #<?= htmlspecialchars($id_payroll); ?></strong><br>
                                Dibuat: <?= htmlspecialchars($tanggalCetak); ?><br>
                                Periode: <?= htmlspecialchars($periode); ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <!-- Informasi Penerima -->
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
                                Email: <?= htmlspecialchars($karyawan['email']); ?><br>
                                Role: <?= htmlspecialchars($karyawan['role']); ?><br>
                                <?php if (!empty($salary_index_level)): ?>
                                    Level Indeks: <?= htmlspecialchars($salary_index_level); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($masaKerja)): ?>
                                    Masa Kerja: <?= htmlspecialchars($masaKerja); ?><br>
                                <?php endif; ?>
                                No. Rekening: <?= htmlspecialchars($noRek); ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    
        <!-- Rincian Pembayaran -->
        <table>
            <tr class="heading">
                <td colspan="2">Rincian Pembayaran</td>
            </tr>
            <tr class="details">
                <td>Gaji Pokok (Employee)</td>
                <td style="text-align:right;">Rp <?= number_format($gaji_pokok_employee, 2, ',', '.'); ?></td>
            </tr>
            <tr class="details">
                <td>Nominal Level Indeks</td>
                <td style="text-align:right;">Rp <?= number_format($salary_index_amount, 2, ',', '.'); ?></td>
            </tr>
            <tr class="details">
                <td><strong>Subtotal Gaji Pokok</strong></td>
                <td style="text-align:right;"><strong>Rp <?= number_format($gaji_pokok, 2, ',', '.'); ?></strong></td>
            </tr>
            <tr class="item">
                <td>Total Pendapatan (Payheads)</td>
                <td style="text-align:right;">Rp <?= number_format($total_pendapatan, 2, ',', '.'); ?></td>
            </tr>
            <tr class="item">
                <td>Total Potongan (Payheads)</td>
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
    
        <!-- Detail Payheads -->
        <h3>Detail Pendapatan &amp; Potongan</h3>
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
                $jenis_tampil = function_exists('translateJenis')
                    ? translateJenis($detail['jenis'])
                    : ucfirst($detail['jenis']);
                echo "<tr>";
                echo "<td>" . $no . "</td>";
                echo "<td class='left-align'>" . htmlspecialchars($detail['nama_payhead']) . "</td>";
                echo "<td>" . htmlspecialchars($jenis_tampil) . "</td>";
                echo "<td>Rp " . number_format($detail['amount'], 2, ',', '.') . "</td>";
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
    
        <!-- Tombol Cetak -->
        <div class="btn-print">
            <button onclick="window.print()">Cetak Slip Gaji</button>
        </div>
    </div>
</body>
</html>
