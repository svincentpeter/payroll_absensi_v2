<?php
// File: /payroll_absensi_v2/keuangan/payroll-details.php

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:Keuangan']);
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
    // 2. Ambil data summary payroll_final + karyawan + salary index
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
    $stmtFinal->bind_param("i", $id_payroll_final);
    $stmtFinal->execute();
    $resFinal = $stmtFinal->get_result();
    if ($resFinal->num_rows === 0) {
        throw new Exception("Data payroll final tidak ditemukan (ID: $id_payroll_final).");
    }
    $payrollFinal = $resFinal->fetch_assoc();
    $stmtFinal->close();

    $nama_karyawan  = $payrollFinal['nama_karyawan'] ?? '-';
    $email_karyawan = $payrollFinal['email'] ?? '-';
    $role_karyawan  = $payrollFinal['role'] ?? '-';

    // Ambil honor kelebihan jam & potongan absensi
    $potongan_absensi = floatval($payrollFinal['potongan_absensi'] ?? 0);
    $honor_jam_lebih  = floatval($payrollFinal['honor_jam_lebih'] ?? 0);

    /* === Ambil data kenaikan gaji tahunan (KGT) yang masih aktif === */
    $increment_amt  = 0.0;
    $increment_name = '';
    $stmtKG = $conn->prepare("
        SELECT nama_kenaikan, jumlah
          FROM kenaikan_gaji_tahunan
         WHERE id_anggota = ?
           AND status     = 'aktif'
           AND ? BETWEEN tanggal_mulai AND tanggal_berakhir
         ORDER BY tanggal_mulai DESC
         LIMIT 1
    ");
    $stmtKG->bind_param("is", $payrollFinal['id_anggota'], $payrollFinal['tgl_payroll']);
    $stmtKG->execute();
    $resKG = $stmtKG->get_result();
    if ($rowKG = $resKG->fetch_assoc()) {
        $increment_amt  = floatval($rowKG['jumlah']);
        $increment_name = $rowKG['nama_kenaikan'] ?: 'Kenaikan Gaji Tahunan';
    }
    $stmtKG->close();

    // 3. Ambil detail payhead final (skip rapel)
    $stmtDetailF = $conn->prepare("
        SELECT pdf.*
          FROM payroll_detail_final pdf
         WHERE pdf.id_payroll_final = ?
           AND IFNULL(pdf.is_rapel,0) = 0
         ORDER BY pdf.id
    ");
    $stmtDetailF->bind_param("i", $id_payroll_final);
    $stmtDetailF->execute();
    $resDetailF = $stmtDetailF->get_result();
    $details = [];
    while ($rowD = $resDetailF->fetch_assoc()) {
        $details[] = $rowD;
    }
    $stmtDetailF->close();

    // 4. Ambil data payroll_final
    $gaji_pokok_db       = (float)$payrollFinal['gaji_pokok'];
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

    // 5. Data karyawan & salary index
    $gaji_pokok_employee = (float)$payrollFinal['gaji_pokok_employee'];
    $salary_index_amount = (float)$payrollFinal['salary_index_amount'];
    $salary_index_level  = $payrollFinal['salary_index_level'];

    // 6. Hitung ulang earnings & deductions dari detail
    $calcEarnings   = 0;
    $calcDeductions = 0;
    foreach ($details as $det) {
        if (strtolower($det['jenis']) === 'earnings') {
            $calcEarnings += (float)$det['amount'];
        } else {
            $calcDeductions += (float)$det['amount'];
        }
    }

    // Rumus transparan untuk slip
    $gaji_pokok_base   = $gaji_pokok_db > 0 ? $gaji_pokok_db : $gaji_pokok_employee;
    $salary_index_amt  = $salary_index_amount;
    $subtotal_gaji     = $gaji_pokok_base + $salary_index_amt;

    $total_pendapatan_payheads = $total_pendapatan_db > 0 ? $total_pendapatan_db : $calcEarnings;
    $total_pendapatan_slip     = $total_pendapatan_payheads + $honor_jam_lebih + $increment_amt;
    $total_potongan            = $total_potongan_db   > 0 ? $total_potongan_db   : $calcDeductions;

    $gaji_bersih_calculated =
      $gaji_pokok_base
    + $salary_index_amt
    + $total_pendapatan_payheads   // semua payhead (earnings)
    + $honor_jam_lebih             // honor jam  lebih (bukan payhead)
    + $increment_amt               // KGT (bukan payhead)
    - $total_potongan              // semua payhead (deductions)
    - $potongan_koperasi
    - $potongan_absensi; 

    // 7. Masa kerja
    $masa_kerja_tahun = (int)$payrollFinal['masa_kerja_tahun'];
    $masa_kerja_bulan = (int)$payrollFinal['masa_kerja_bulan'];
    $masaKerja = ($masa_kerja_tahun || $masa_kerja_bulan)
        ? "{$masa_kerja_tahun} Tahun, {$masa_kerja_bulan} Bulan"
        : "-";

    // 8. Format tanggal & periode
    $timestamp    = strtotime($tanggalPayrollRaw);
    $tanggalCetak = date('d', $timestamp)
                 . ' ' . getIndonesianMonthName((int)date('n', $timestamp))
                 . ' ' . date('Y', $timestamp);
    $periode      = getIndonesianMonthName($bulan) . ' ' . $tahun;

    // 9. Log akses
    $user_id     = $_SESSION['user_id'] ?? '';
    $log_details = "Mengakses Slip Gaji Final ID $id_payroll_final "
                 . "(Anggota {$payrollFinal['id_anggota']}, Periode: $bulan-$tahun).";
    add_audit_log($conn, $user_id, 'ViewPayrollDetailsFinal', $log_details);

    // Deteksi apakah sudah ada honor & KGT di detail
    $adaHonorJamLebih = $adaIncrementKGT = false;
    foreach ($details as $det) {
        $namaPH = strtolower($det['nama_payhead']);
        if (strpos($namaPH, 'kelebihan jam') !== false || strpos($namaPH, 'honor lebih') !== false) {
            $adaHonorJamLebih = true;
        }
        if (strpos($namaPH, 'kenaikan gaji') !== false) {
            $adaIncrementKGT = true;
        }
    }

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
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <style>
      body { background-color:#f8f9fa; margin:0; padding:0; }
      @media screen { html { zoom:0.8; } }
      .invoice-box {
        max-width:900px; margin:30px auto; padding:30px;
        background:#fff; border:1px solid #dee2e6; box-shadow:0 0 10px rgba(0,0,0,0.1);
        font-size:16px; line-height:24px; color:#343a40;
      }
      .invoice-box table { width:100%; border-collapse:collapse; }
      .invoice-box table td { padding:8px; vertical-align:top; }
      .invoice-box table tr.top table td { padding-bottom:20px; }
      .invoice-box table tr.top table td.title {
        font-size:36px; font-weight:bold; color:#007bff;
      }
      .invoice-box table tr.information table td {
        padding-bottom:20px; font-size:14px;
      }
      .invoice-box table tr.heading td {
        background:#e9ecef; border-bottom:1px solid #dee2e6; font-weight:bold;
      }
      .invoice-box table tr.item td {
        border-bottom:1px solid #dee2e6;
      }
      .invoice-box table tr.item.last td { border-bottom:none; }
      .invoice-box table tr.total td { font-weight:bold; }

      .detail-table {
        width:100%; border-collapse:collapse; margin-top:20px;
      }
      .detail-table th, .detail-table td {
        border:1px solid #dee2e6; padding:8px; text-align:center;
      }
      .detail-table th { background:#e9ecef; }
      .left-align { text-align:left; }

      .btn-print { text-align:center; margin-top:30px; }
      .btn-print button {
        padding:10px 20px; font-size:16px; background:#28a745;
        border:none; color:#fff; cursor:pointer; border-radius:4px;
      }
      .btn-print button:hover { background:#218838; }

      .catatan-box {
        margin-top:20px; padding:15px; border-left:4px solid #007bff;
        background:#f1f1f1;
      }
      .catatan-box h4 { margin-top:0; font-size:18px; color:#007bff; }

      .btn-back {
        position:fixed; top:10px; left:10px; padding:8px 12px;
        background:#007bff; color:#fff; text-decoration:none; border-radius:4px; z-index:1000;
      }
      .btn-back:hover { background:#0056b3; }

      @media print {
        @page { size:A4; margin:15mm 10mm; }
        body { margin:0; padding:0; background:#fff; }
        .btn-print, .btn-back { display:none; }
        .invoice-box {
          width:100% !important; max-width:100% !important;
          margin:0 auto !important; padding:0 !important; border:none !important;
          box-shadow:none !important;
        }
      }
    </style>
</head>
<body>
  <!-- Tombol Kembali -->
  <a href="payroll_history.php" class="btn-back">Kembali</a>

  <div class="invoice-box">
    <!-- Header -->
    <table>
      <tr class="top">
        <td colspan="2">
          <table>
            <tr>
              <td class="title">
                <img src="/payroll_absensi_v2/assets/img/Logo.png"
                     alt="Logo Sekolah"
                     style="max-width:100px;">
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
      <!-- Informasi -->
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
                <?php if ($salary_index_level): ?>
                  Level Indeks: <?= htmlspecialchars($salary_index_level); ?><br>
                <?php endif; ?>
                <?php if ($masaKerja !== "-"): ?>
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
        <td>Gaji Pokok</td>
        <td style="text-align:right;">Rp <?= number_format($gaji_pokok_base, 0, ',', '.') ?></td>
      </tr>
      <tr class="details">
        <td>Salary Indeks</td>
        <td style="text-align:right;">Rp <?= number_format($salary_index_amt, 0, ',', '.') ?></td>
      </tr>
      <tr class="details">
        <td><strong>Subtotal Gaji</strong></td>
        <td style="text-align:right;">
          <strong>Rp <?= number_format($subtotal_gaji, 0, ',', '.') ?></strong>
        </td>
      </tr>
      <tr class="item">
        <td>Potongan Absensi</td>
        <td class="text-danger" style="text-align:right;">
          Rp <?= number_format($potongan_absensi, 0, ',', '.') ?>
        </td>
      </tr>
      <tr class="item">
        <td>Potongan Koperasi</td>
        <td class="text-danger" style="text-align:right;">
          Rp <?= number_format($potongan_koperasi, 0, ',', '.') ?>
        </td>
      </tr>
      <tr class="details">
        <td>Honor Kelebihan Jam Mengajar</td>
        <td style="text-align:right;">Rp <?= number_format($honor_jam_lebih, 0, ',', '.') ?></td>
      </tr>
      <?php if ($increment_amt > 0): ?>
      <tr class="details">
        <td><?= htmlspecialchars($increment_name) ?></td>
        <td style="text-align:right;">Rp <?= number_format($increment_amt, 0, ',', '.') ?></td>
      </tr>
      <?php endif; ?>
      <tr class="item">
        <td>Total Pendapatan (Payheads)</td>
        <td style="text-align:right;">Rp <?= number_format($total_pendapatan_payheads, 0, ',', '.') ?></td>
      </tr>
      <tr class="item">
        <td>Total Potongan (Payheads)</td>
        <td class="text-danger" style="text-align:right;">
          Rp <?= number_format($total_potongan, 0, ',', '.') ?>
        </td>
      </tr>
      <tr class="item last">
        <td>Gaji Bersih</td>
        <td style="text-align:right;">Rp <?= number_format($gaji_bersih_calculated, 0, ',', '.') ?></td>
      </tr>
      <tr class="total">
        <td></td>
        <td style="text-align:right;">
          Total: Rp <?= number_format($gaji_bersih_calculated, 0, ',', '.') ?>
        </td>
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
      <?php if (empty($details)): ?>
        <tr><td colspan="5"><em>Belum ada data payhead final.</em></td></tr>
      <?php else: ?>
        <?php $no = 1; ?>
        <?php foreach ($details as $det): ?>
          <?php $isDeduction = strtolower($det['jenis']) !== 'earnings'; ?>
          <tr>
            <td><?= $no ?></td>
            <td class="left-align"><?= htmlspecialchars($det['nama_payhead']) ?></td>
            <td><?= htmlspecialchars(translateJenis($det['jenis'])) ?></td>
            <td class="<?= $isDeduction ? 'text-danger' : '' ?>">
              Rp <?= number_format($det['amount'], 0, ',', '.') ?>
            </td>
            <td>-</td>
          </tr>
          <?php $no++; ?>
        <?php endforeach; ?>
        <?php if ($honor_jam_lebih > 0 && !$adaHonorJamLebih): ?>
          <tr>
            <td><?= $no ?></td>
            <td class="left-align">Honor Kelebihan Jam Mengajar</td>
            <td>Pendapatan</td>
            <td>Rp <?= number_format($honor_jam_lebih, 0, ',', '.') ?></td>
            <td>-</td>
          </tr>
          <?php $no++; ?>
        <?php endif; ?>
        <?php if ($increment_amt > 0 && !$adaIncrementKGT): ?>
          <tr>
            <td><?= $no ?></td>
            <td class="left-align"><?= htmlspecialchars($increment_name) ?></td>
            <td>Pendapatan</td>
            <td>Rp <?= number_format($increment_amt, 0, ',', '.') ?></td>
            <td>-</td>
          </tr>
        <?php endif; ?>
      <?php endif; ?>
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
