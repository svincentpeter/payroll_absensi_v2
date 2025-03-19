<?php
// File: /payroll_absensi_v2/keuangan/payroll-details.php

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:Keuangan', 'M:Superadmin']);
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

require_once __DIR__ . '/../koneksi.php';
if (ob_get_length()) {
    ob_end_clean();
}

// 1. Ambil parameter ID payroll final
$id_payroll_final = isset($_GET['id_payroll']) ? intval($_GET['id_payroll']) : 0;
if ($id_payroll_final <= 0) {
    echo "Parameter tidak valid.";
    exit();
}

try {
    // 2. Ambil data summary payroll_final beserta data karyawan dan salary index
    $stmtFinal = $conn->prepare("
        SELECT pf.*,
               a.nama AS nama_karyawan,
               a.email,
               a.role,
               a.no_rekening AS norek_karyawan,
               a.gaji_pokok AS gaji_pokok_employee,
               a.masa_kerja_tahun,
               a.masa_kerja_bulan,
               si.level AS salary_index_level,
               si.base_salary AS salary_index_amount
          FROM payroll_final pf
          JOIN anggota_sekolah a ON pf.id_anggota = a.id
     LEFT JOIN salary_indices si ON a.salary_index_id = si.id
         WHERE pf.id = ?
         LIMIT 1
    ");
    if (!$stmtFinal) {
        throw new Exception("Prepare payroll_final gagal: " . $conn->error);
    }
    $stmtFinal->bind_param("i", $id_payroll_final);
    $stmtFinal->execute();
    $resFinal = $stmtFinal->get_result();
    if ($resFinal->num_rows === 0) {
        throw new Exception("Data payroll final tidak ditemukan (ID: $id_payroll_final).");
    }
    $payrollFinal = $resFinal->fetch_assoc();
    $nama_karyawan   = $payrollFinal['nama_karyawan'];
$email_karyawan  = $payrollFinal['email'];
$role_karyawan   = $payrollFinal['role'];
    $stmtFinal->close();

    // 3. Ambil detail payhead final (skip payhead rapel)
    $stmtDetailF = $conn->prepare("
        SELECT pdf.*
          FROM payroll_detail_final pdf
         WHERE pdf.id_payroll_final = ?
           AND IFNULL(pdf.is_rapel,0) = 0
         ORDER BY pdf.id
    ");
    if (!$stmtDetailF) {
        throw new Exception("Prepare detail_final gagal: " . $conn->error);
    }
    $stmtDetailF->bind_param("i", $id_payroll_final);
    $stmtDetailF->execute();
    $resDetailF = $stmtDetailF->get_result();
    $details = [];
    while ($rowD = $resDetailF->fetch_assoc()) {
        $details[] = $rowD;
    }
    $stmtDetailF->close();

    // 4. Ambil data dari payroll_final (jika sudah terisi)  
    // Nilai-nilai berikut mungkin bernilai 0 jika belum diupdate dengan benar oleh SDM,
    // maka kita akan override dengan hasil perhitungan dari detail.
    $gaji_pokok_db    = (float)$payrollFinal['gaji_pokok'];
    $total_pendapatan_db = (float)$payrollFinal['total_pendapatan'];
    $total_potongan_db   = (float)$payrollFinal['total_potongan'];
    $potongan_koperasi   = floatval($payrollFinal['potongan_koperasi']);
    $tanggalPayrollRaw   = $payrollFinal['tgl_payroll'];
    $bulan               = (int)$payrollFinal['bulan'];
    $tahun               = (int)$payrollFinal['tahun'];
    $catatan             = trim($payrollFinal['catatan'] ?? '');
    $noRek               = !empty($payrollFinal['no_rekening'])
                           ? $payrollFinal['no_rekening']
                           : $payrollFinal['norek_karyawan'];

    // 5. Data karyawan dan salary index
    $gaji_pokok_employee = (float)$payrollFinal['gaji_pokok_employee'];
    $salary_index_amount = (float)$payrollFinal['salary_index_amount'];

    // 6. Hitung ulang total pendapatan dan potongan dari detail final
    $calcEarnings = 0;
    $calcDeductions = 0;
    foreach ($details as $det) {
        if (strtolower($det['jenis']) === 'earnings') {
            $calcEarnings += (float)$det['amount'];
        } else {
            $calcDeductions += (float)$det['amount'];
        }
    }
    
    // Gaji pokok seharusnya: gaji_pokok_employee + salary_index_amount
    $gajiPokokRecalc = $gaji_pokok_employee + $salary_index_amount;
    $gaji_pokok = ($gaji_pokok_db > 0) ? $gaji_pokok_db : $gajiPokokRecalc;
    $total_pendapatan = ($total_pendapatan_db > 0) ? $total_pendapatan_db : $calcEarnings;
    $total_potongan = ($total_potongan_db > 0) ? $total_potongan_db : $calcDeductions;

    // Hitung Gaji Bersih: Gaji Pokok + Total Pendapatan - Total Potongan - Potongan Koperasi
    $gaji_bersih_calculated = $gaji_pokok + $total_pendapatan - $total_potongan - $potongan_koperasi;

    // 7. Hitung masa kerja untuk tampilan
    $masa_kerja_tahun = (int)$payrollFinal['masa_kerja_tahun'];
    $masa_kerja_bulan = (int)$payrollFinal['masa_kerja_bulan'];
    $masaKerja = ($masa_kerja_tahun > 0 || $masa_kerja_bulan > 0)
                 ? "{$masa_kerja_tahun} Tahun, {$masa_kerja_bulan} Bulan"
                 : "-";

    // 8. Format tanggal dan periode
    $timestamp    = strtotime($tanggalPayrollRaw);
    $tanggalCetak = date('d', $timestamp) . ' ' . getIndonesianMonthName((int)date('n', $timestamp)) . ' ' . date('Y', $timestamp);
    $periode      = getIndonesianMonthName($bulan) . ' ' . $tahun;

    // 9. Catat log akses slip gaji
    $user_id    = $_SESSION['user_id'] ?? '';
    $log_details= "Mengakses Slip Gaji Final ID $id_payroll_final (Anggota {$payrollFinal['id_anggota']}, Periode: $bulan-$tahun).";
    add_audit_log($conn, $user_id, 'ViewPayrollDetailsFinal', $log_details);

} catch (Exception $e) {
    echo "Terjadi kesalahan: " . htmlspecialchars($e->getMessage());
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji Final #<?= htmlspecialchars($id_payroll_final) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5 & SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <style>
  body { 
    background-color: #f8f9fa; 
    margin: 0; 
    padding: 0; 
  }

  /* Wrapper untuk mengatur lebar view di browser */
  @media screen {
    html {
      zoom: 0.8; /* 80% */
    }
  }

  .invoice-box {
      /* Bisa diperlebar sedikit untuk tampilan normal di browser */
      max-width: 900px; 
      margin: 30px auto;
      padding: 30px;
      background: #fff;
      border: 1px solid #dee2e6;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      font-size: 16px;
      line-height: 24px;
      color: #343a40;
  }

  /* Tabel, heading, dsb. tetap sama seperti sebelumnya */
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

  /* Aturan untuk tampilan cetak */
  @media print {
    /* Mengatur ukuran dan margin kertas */
    @page {
      size: A4; 
      margin: 15mm 10mm; 
      /* Silakan sesuaikan margin sesuai kebutuhan */
    }

    body {
      margin: 0;
      padding: 0;
      background: #fff;
    }

    /* Hilangkan elemen tombol saat print */
    .btn-print, .btn-back {
      display: none;
    }

    /* Override lebar & margin invoice-box agar lebih lebar dan di tengah */
    .invoice-box {
      width: 100% !important;   /* Memenuhi seluruh lebar halaman cetak */
      max-width: 100% !important; 
      margin: 0 auto !important; 
      padding: 0 !important;    /* Kurangi padding agar konten lebih "full" */
      border: none !important;  /* Opsional: hilangkan border saat cetak */
      box-shadow: none !important;
    }
  }
</style>

</head>
<body>
<!-- Tombol Kembali -->
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
                            <strong>Slip Gaji Final #<?= htmlspecialchars($id_payroll_final); ?></strong><br>
                            Dibuat: <?= htmlspecialchars($tanggalCetak); ?><br>
                            Periode: <?= htmlspecialchars($periode); ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <!-- Informasi Perusahaan & Penerima -->
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
                            <?= htmlspecialchars($nama_karyawan); ?><br>
                            Email: <?= htmlspecialchars($email_karyawan); ?><br>
                            Role: <?= htmlspecialchars($role_karyawan); ?><br>
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
            <td colspan="2">Rincian Pembayaran (Final)</td>
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
        <tr class="item">
            <td>Potongan Koperasi</td>
            <td style="text-align:right;">Rp <?= number_format($potongan_koperasi, 2, ',', '.'); ?></td>
        </tr>
        <tr class="item last">
            <td>Gaji Bersih</td>
            <td style="text-align:right;">Rp <?= number_format($gaji_bersih_calculated, 2, ',', '.'); ?></td>
        </tr>
        <tr class="total">
            <td></td>
            <td style="text-align:right;">Total: Rp <?= number_format($gaji_bersih_calculated, 2, ',', '.'); ?></td>
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
            <th>Keterangan</th>
        </tr>
        <?php
// Bagian detail payheads
if (count($details) === 0) {
    echo '<tr><td colspan="5"><em>Belum ada data payhead final.</em></td></tr>';
} else {
    $no = 1;
    foreach ($details as $detail) {
        // Gunakan fungsi translateJenis() untuk menerjemahkan jenis
        $jenisTampil = translateJenis($detail['jenis']);
        echo '<tr>';
        echo '<td>' . $no . '</td>';
        echo '<td class="left-align">' . htmlspecialchars($detail['nama_payhead']) . '</td>';
        echo '<td>' . htmlspecialchars($jenisTampil) . '</td>';
        echo '<td>Rp ' . number_format($detail['amount'], 2, ',', '.') . '</td>';
        echo '<td>-</td>';
        echo '</tr>';
        $no++;
    }
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
