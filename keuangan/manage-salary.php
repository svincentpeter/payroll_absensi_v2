<?php
// File: /payroll_absensi_v2/keuangan/manage-salary.php

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:Keuangan', 'M:Superadmin'], '/payroll_absensi_v2/login.php');

// Koneksi ke database
require_once __DIR__ . '/../koneksi.php';

// Generate CSRF token
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

// Hapus output buffering jika ada
if (ob_get_length()) {
    ob_end_clean();
}

// Ambil Parameter Periode (bulan & tahun)
$bulanVal = 0;
$tahunVal = 0;
if (isset($_GET['bulan'])) {
    if (is_numeric($_GET['bulan'])) {
        $bulanVal = intval($_GET['bulan']);
    } else {
        $bulanVal = monthNameToInt(sanitize_input($_GET['bulan']));
    }
}
if (isset($_GET['tahun'])) {
    $tahunVal = intval($_GET['tahun']);
}
if ($bulanVal <= 0 || $tahunVal <= 0) {
    // Default ke bulan & tahun sekarang
    $bulanVal = date("n");
    $tahunVal = date("Y");
}

/**
 * BAGIAN 1: AJAX HANDLER
 * Menangani UpdatePayhead, RejectPayroll, DeletePayhead
 */
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_response(405, 'Metode Permintaan Tidak Diizinkan (harus POST).');
    }
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $case = sanitize_input($_POST['case'] ?? '');
    
    switch ($case) {
        case 'UpdatePayhead':
            $id_payhead = intval($_POST['id_payhead'] ?? 0);
            $amount     = floatval($_POST['amount'] ?? 0);
            $payroll_id = intval($_POST['payroll_id'] ?? 0);
            if ($payroll_id <= 0 || $id_payhead <= 0) {
                send_response(1, 'Parameter tidak valid (UpdatePayhead).');
            }
            $stmt = $conn->prepare("UPDATE payroll_detail SET amount = ? WHERE id_payroll = ? AND id_payhead = ?");
            if (!$stmt) {
                send_response(1, 'Prepare failed: ' . $conn->error);
            }
            $stmt->bind_param("dii", $amount, $payroll_id, $id_payhead);
            if ($stmt->execute()) {
                $stmt->close();
                $user_nip = $_SESSION['nip'] ?? '';
                $details  = "Memperbarui Payhead ID $id_payhead untuk Payroll ID $payroll_id => " . formatNominal($amount);
                add_audit_log($conn, $user_nip, 'UpdatePayhead', $details);
                send_response(0, 'Payhead berhasil diupdate.');
            } else {
                $stmt->close();
                send_response(1, 'Gagal update payhead: ' . $stmt->error);
            }
            break;
            
        case 'RejectPayroll':
            $id_anggota    = intval($_POST['id_anggota'] ?? 0);
            $bulanFromAjax = intval($_POST['bulan'] ?? 0);
            $tahunFromAjax = intval($_POST['tahun'] ?? 0);
            if ($id_anggota <= 0 || $bulanFromAjax <= 0 || $tahunFromAjax <= 0) {
                send_response(1, 'Parameter tidak valid (RejectPayroll).');
            }
            // Ubah status payroll => 'revisi'
            $stmtPayrollReject = $conn->prepare("UPDATE payroll SET status = 'revisi' WHERE id_anggota = ? AND bulan = ? AND tahun = ?");
            $stmtPayrollReject->bind_param("iii", $id_anggota, $bulanFromAjax, $tahunFromAjax);
            if (!$stmtPayrollReject->execute()) {
                $stmtPayrollReject->close();
                send_response(1, 'Gagal menolak payroll (payroll): ' . $stmtPayrollReject->error);
            }
            $stmtPayrollReject->close();
            send_response(0, 'Payroll berhasil ditolak dan status diubah menjadi revisi.');
            break;
            
        case 'DeletePayhead':
            $id_payhead = intval($_POST['id_payhead'] ?? 0);
            $payroll_id = intval($_POST['payroll_id'] ?? 0);
            if ($payroll_id <= 0 || $id_payhead <= 0) {
                send_response(1, 'Parameter tidak valid (DeletePayhead).');
            }
            $stmtDel = $conn->prepare("DELETE FROM payroll_detail WHERE id_payroll = ? AND id_payhead = ?");
            if (!$stmtDel) {
                send_response(1, 'Prepare failed: ' . $conn->error);
            }
            $stmtDel->bind_param("ii", $payroll_id, $id_payhead);
            if ($stmtDel->execute()) {
                $stmtDel->close();
                $user_nip = $_SESSION['nip'] ?? '';
                $details  = "Menghapus Payhead ID $id_payhead dari Payroll ID $payroll_id.";
                add_audit_log($conn, $user_nip, 'DeletePayhead', $details);
                send_response(0, 'Payhead berhasil dihapus.');
            } else {
                $stmtDel->close();
                send_response(1, 'Gagal menghapus payhead: ' . $stmtDel->error);
            }
            break;
            
        default:
            send_response(1, 'Case AJAX tidak dikenali.');
    }
    exit();
}

/**
 * BAGIAN 2: Proses POST Finalisasi Payroll (status final)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['ajax'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $id_anggota       = intval($_POST['id_anggota'] ?? 0);
    $bulan_int        = intval($_POST['bulan_int'] ?? 0);
    $tahun            = intval($_POST['tahun'] ?? 0);
    $id_rekap_absensi = intval($_POST['id_rekap_absensi'] ?? 0);
    $no_rekening      = sanitize_input($_POST['no_rekening'] ?? '');

    // Hilangkan pemisah ribuan
    $gaji_pokok       = floatval(str_replace('.', '', $_POST['gaji_pokok'] ?? '0'));
    $total_earnings   = floatval(str_replace('.', '', $_POST['total_earnings'] ?? '0'));
    $total_deductions = floatval(str_replace('.', '', $_POST['total_deductions'] ?? '0'));
    $potongan_koperasi= floatval(str_replace('.', '', $_POST['potongan_koperasi'] ?? '0'));

    $catatan          = sanitize_input($_POST['inputDescription'] ?? '');
    $tgl_payroll      = sanitize_input($_POST['tgl_payroll'] ?? date('Y-m-d H:i:s'));
    
    if (!isset($_POST['payheads_ids']) || empty($_POST['payheads_ids'])) {
        $total_earnings   = 0;
        $total_deductions = 0;
    }

    if ($id_anggota <= 0 || $bulan_int <= 0 || $tahun <= 0 || $id_rekap_absensi <= 0) {
        die("Parameter tidak valid untuk finalisasi payroll.");
    }

$conn->begin_transaction();
try {
    // 1) Insert dulu ke tabel payroll dengan nilai sementara
    $status = 'final';
    $temp_earnings   = 0;  // sementara
    $temp_deductions = 0;  // sementara
    $temp_bersih     = 0;  // sementara

    $stmtPayroll = $conn->prepare("
        INSERT INTO payroll
        (id_anggota, bulan, tahun, tgl_payroll, gaji_pokok,
         total_pendapatan, total_potongan, potongan_koperasi,
         gaji_bersih, no_rekening, catatan, id_rekap_absensi, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    if (!$stmtPayroll) {
        throw new Exception("Prepare failed (insert payroll): " . $conn->error);
    }
    $stmtPayroll->bind_param(
        "iiisdddddssis",
        $id_anggota,
        $bulan_int,
        $tahun,
        $tgl_payroll,
        $gaji_pokok,
        $temp_earnings,
        $temp_deductions,
        $potongan_koperasi,
        $temp_bersih,
        $no_rekening,
        $catatan,
        $id_rekap_absensi,
        $status
    );
    if (!$stmtPayroll->execute()) {
        throw new Exception("Gagal insert payroll: " . $stmtPayroll->error);
    }
    $newPayrollId = $stmtPayroll->insert_id;
    $stmtPayroll->close();

    // 2) Insert detail payhead => payroll_detail (dari hidden field)
    if (!empty($_POST['payheads_ids'])) {
        $stmtDetail = $conn->prepare("
            INSERT INTO payroll_detail
                (id_payroll, id_anggota, id_payhead, jenis, amount, status)
            VALUES (?,?,?,?,?, 'final')
        ");
        if (!$stmtDetail) {
            throw new Exception("Prepare failed (insert payroll_detail): " . $conn->error);
        }
        foreach ($_POST['payheads_ids'] as $i => $ph_id) {
            $ph_id_int = intval($ph_id);
            $jenis     = sanitize_input($_POST['payheads_jenis'][$i] ?? '');
            $amount    = floatval($_POST['payheads_amount'][$i] ?? 0);

            if (!$stmtDetail->bind_param("iiisd", $newPayrollId, $id_anggota, $ph_id_int, $jenis, $amount)) {
                throw new Exception("bind_param error: " . $stmtDetail->error);
            }
            if (!$stmtDetail->execute()) {
                throw new Exception("Gagal insert payroll_detail: " . $stmtDetail->error);
            }
        }
        $stmtDetail->close();
    }

    // 3) Setelah payroll_detail terisi, baru sum total pendapatan & potongan
    $stmtSum = $conn->prepare("
        SELECT 
          SUM(CASE WHEN pd.jenis='earnings' THEN pd.amount ELSE 0 END) AS total_earnings,
          SUM(CASE WHEN pd.jenis='deductions' THEN pd.amount ELSE 0 END) AS total_deductions
        FROM payroll_detail pd
        JOIN payheads ph ON ph.id = pd.id_payhead
        LEFT JOIN employee_payheads ep
               ON ep.id_anggota = ?
              AND ep.id_payhead = pd.id_payhead
        WHERE pd.id_payroll = ?
          AND IFNULL(ep.is_rapel,0)=0
    ");
    if (!$stmtSum) {
        throw new Exception("Prepare failed (sum payroll_detail): " . $conn->error);
    }
    $stmtSum->bind_param("ii", $id_anggota, $newPayrollId);
    if (!$stmtSum->execute()) {
        throw new Exception("Gagal sum payroll_detail: " . $stmtSum->error);
    }
    $resSum = $stmtSum->get_result();
    $rowSum = $resSum->fetch_assoc();
    $stmtSum->close();

    $total_earnings   = floatval($rowSum['total_earnings'] ?? 0);
    $total_deductions = floatval($rowSum['total_deductions'] ?? 0);
    $gaji_bersih      = $gaji_pokok + $total_earnings - $total_deductions - $potongan_koperasi;

    // 4) Update kembali tabel payroll dengan nilai final
    $stmtUpdate = $conn->prepare("
        UPDATE payroll
           SET total_pendapatan = ?,
               total_potongan   = ?,
               gaji_bersih      = ?
         WHERE id = ?
    ");
    if (!$stmtUpdate) {
        throw new Exception("Prepare failed (update payroll): " . $conn->error);
    }
    if (!$stmtUpdate->bind_param("dddi", $total_earnings, $total_deductions, $gaji_bersih, $newPayrollId)) {
        throw new Exception("bind_param error: " . $stmtUpdate->error);
    }
    if (!$stmtUpdate->execute()) {
        throw new Exception("Gagal update payroll: " . $stmtUpdate->error);
    }
    $stmtUpdate->close();

    // 5) Insert data ke payroll_final
    $stmtPayrollFinal = $conn->prepare("
        INSERT INTO payroll_final
        (id_payroll_asal, id_anggota, bulan, tahun, tgl_payroll,
         gaji_pokok, total_pendapatan, total_potongan, potongan_koperasi,
         gaji_bersih, no_rekening, catatan, id_rekap_absensi)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    if (!$stmtPayrollFinal) {
        throw new Exception("Prepare failed (insert payroll_final): " . $conn->error);
    }
    if (!$stmtPayrollFinal->bind_param(
        "iiiisdddddssi",
        $newPayrollId,
        $id_anggota,
        $bulan_int,
        $tahun,
        $tgl_payroll,
        $gaji_pokok,
        $total_earnings,
        $total_deductions,
        $potongan_koperasi,
        $gaji_bersih,
        $no_rekening,
        $catatan,
        $id_rekap_absensi
    )) {
        throw new Exception("bind_param error: " . $stmtPayrollFinal->error);
    }
    if (!$stmtPayrollFinal->execute()) {
        throw new Exception("Gagal insert payroll_final: " . $stmtPayrollFinal->error);
    }
    $id_payroll_final = $stmtPayrollFinal->insert_id;
    $stmtPayrollFinal->close();

    // 6) Snapshot detail => payroll_detail_final
    $stmtDetailSnapshot = $conn->prepare("
        INSERT INTO payroll_detail_final
        (id_payroll_final, id_payhead, nama_payhead, jenis, amount)
        SELECT
          ?,
          pd.id_payhead,
          ph.nama_payhead,
          pd.jenis,
          pd.amount
        FROM payroll_detail pd
        JOIN payheads ph ON ph.id = pd.id_payhead
        LEFT JOIN employee_payheads ep
               ON ep.id_anggota = ?
              AND ep.id_payhead = pd.id_payhead
        WHERE pd.id_payroll = ?
          AND IFNULL(ep.is_rapel,0) = 0
    ");
    if (!$stmtDetailSnapshot) {
        throw new Exception("Prepare failed (snapshot detail): " . $conn->error);
    }
    if (!$stmtDetailSnapshot->bind_param("iii", $id_payroll_final, $id_anggota, $newPayrollId)) {
        throw new Exception("bind_param error: " . $stmtDetailSnapshot->error);
    }
    if (!$stmtDetailSnapshot->execute()) {
        throw new Exception("Gagal insert payroll_detail_final: " . $stmtDetailSnapshot->error);
    }
    $stmtDetailSnapshot->close();

    // 7) Commit
    $conn->commit();

    // Audit log
    $user_nip = $_SESSION['nip'] ?? '';
    $details  = "Finalisasi Payroll untuk Anggota $id_anggota periode $bulan_int-$tahun. "
              . "Pendapatan: " . formatNominal($total_earnings)
              . ", Potongan: " . formatNominal($total_deductions)
              . ", Pot. Koperasi: " . formatNominal($potongan_koperasi)
              . ", Gaji Bersih: " . formatNominal($gaji_bersih);
    add_audit_log($conn, $user_nip, 'InsertPayroll', $details);

    // Redirect ke detail payroll final
    header("Location: payroll-details.php?id_payroll=$id_payroll_final");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    die("Terjadi kesalahan: " . $e->getMessage());
}
}

/**
 * BAGIAN 3: GET -> Tampilkan Review Payroll
 */

// Ambil parameter GET
$id_anggota      = intval($_GET['id_anggota'] ?? 0);
$bulanParam      = sanitize_input($_GET['bulan'] ?? '');
$tahunStr        = sanitize_input($_GET['tahun'] ?? '');
$tglPayrollParam = sanitize_input($_GET['tgl'] ?? date('Y-m-d H:i:s'));

if ($id_anggota <= 0 || empty($bulanParam) || empty($tahunStr)) {
    die("Parameter tidak valid.");
}
if (is_numeric($bulanParam)) {
    $bulan     = intval($bulanParam);
    $bulanName = getIndonesianMonthName($bulan);
} else {
    $bulan     = monthNameToInt($bulanParam);
    $bulanName = ucfirst($bulanParam);
}
$tahun = intval($tahunStr);
if ($bulan <= 0 || $tahun <= 0) {
    die("Parameter bulan/tahun tidak valid.");
}

// Pastikan salary index update
updateSalaryIndexForUser($conn, $id_anggota);

// Cek payroll
$stmtCheck = $conn->prepare("SELECT id, status FROM payroll WHERE id_anggota=? AND bulan=? AND tahun=? LIMIT 1");
$stmtCheck->bind_param("iii", $id_anggota, $bulan, $tahun);
$stmtCheck->execute();
$resCheck = $stmtCheck->get_result();
if ($resCheck->num_rows > 0) {
    $payroll = $resCheck->fetch_assoc();
    $stmtCheck->close();
    if ($payroll['status'] == 'final') {
        header("Location: payroll-details.php?id_payroll=" . $payroll['id']);
        exit();
    }
    // Gunakan payroll_id
    $id_payroll = $payroll['id'];
} else {
    $stmtCheck->close();
    die("Payroll belum diproses oleh SDM.");
}

// Pastikan rekap_absensi ada
$stmtRekap = $conn->prepare("SELECT id FROM rekap_absensi WHERE id_anggota=? AND bulan=? AND tahun=? LIMIT 1");
$stmtRekap->bind_param("iii", $id_anggota, $bulan, $tahun);
$stmtRekap->execute();
$resRekap = $stmtRekap->get_result();
if ($resRekap->num_rows > 0) {
    $rowRekap = $resRekap->fetch_assoc();
    $id_rekap_absensi = $rowRekap['id'];
} else {
    $stmtIns = $conn->prepare("
        INSERT INTO rekap_absensi (id_anggota, bulan, tahun, total_hadir, total_izin, total_cuti, total_tanpa_keterangan, total_sakit) 
        VALUES (?,?,?,0,0,0,0,0)
    ");
    $stmtIns->bind_param("iii", $id_anggota, $bulan, $tahun);
    if (!$stmtIns->execute()) {
        die("Gagal insert rekap_absensi: " . $stmtIns->error);
    }
    $id_rekap_absensi = $stmtIns->insert_id;
    $stmtIns->close();
}
$stmtRekap->close();

// Ambil data detail payheads
$stmtPH = $conn->prepare("
  SELECT 
    ph.id AS id_payhead,
    ph.nama_payhead,
    ph.jenis,
    pd.amount,
    ep.status,
    ep.is_rapel,
    ep.remarks,
    ep.support_doc_path
  FROM payheads ph
  JOIN employee_payheads ep 
       ON ep.id_payhead = ph.id
      AND ep.id_anggota = ?
  LEFT JOIN payroll_detail pd 
       ON pd.id_payhead = ph.id
      AND pd.id_payroll = ?
");
$stmtPH->bind_param("ii", $id_anggota, $id_payroll);
$stmtPH->execute();
$resPH = $stmtPH->get_result();

$payheads = [];
$totalPendapatan = 0;
$totalPotongan   = 0;

while ($r = $resPH->fetch_assoc()) {
    $payheads[] = $r;
    // Jika rapel => skip
    if (!empty($r['is_rapel']) && $r['is_rapel'] == 1) {
        continue;
    }
    if ($r['jenis'] === 'earnings') {
        $totalPendapatan += floatval($r['amount'] ?? 0);
    } else {
        $totalPotongan += floatval($r['amount'] ?? 0);
    }
}
$stmtPH->close();

// Ambil data karyawan
$stmtKar = $conn->prepare("SELECT * FROM anggota_sekolah WHERE id=? LIMIT 1");
$stmtKar->bind_param("i", $id_anggota);
$stmtKar->execute();
$resKar = $stmtKar->get_result();
if ($resKar->num_rows == 0) {
    die("Karyawan tidak ditemukan.");
}
$karyawan = $resKar->fetch_assoc();
$stmtKar->close();
$nip = $karyawan['nip'] ?? 'unknown';

// Ambil data salary index
$salary_index_level  = '';
$salary_index_amount = 0;
if (!empty($karyawan['salary_index_id'])) {
    $stmtIndex = $conn->prepare("SELECT level, base_salary FROM salary_indices WHERE id=?");
    $stmtIndex->bind_param("i", $karyawan['salary_index_id']);
    $stmtIndex->execute();
    $resIndex = $stmtIndex->get_result();
    if ($resIndex->num_rows > 0) {
        $rowIdx = $resIndex->fetch_assoc();
        $salary_index_level  = $rowIdx['level'] ?? '';
        $salary_index_amount = floatval($rowIdx['base_salary'] ?? 0);
    }
    $stmtIndex->close();
}

$gaji_pokok_employee = floatval($karyawan['gaji_pokok'] ?? 0);
$gaji_pokok = $gaji_pokok_employee + $salary_index_amount;

$gaji_kotor  = $gaji_pokok + $totalPendapatan;
$gaji_bersih = $gaji_kotor - $totalPotongan;

$masa_kerja   = ((int)($karyawan['masa_kerja_tahun'] ?? 0)) . " Tahun, " . ((int)($karyawan['masa_kerja_bulan'] ?? 0)) . " Bulan";
$namaKaryawan = $karyawan['nama'] ?? '';
$noRek        = $karyawan['no_rekening'] ?? '';
$catatan      = '';

$user_nip     = $_SESSION['nip'] ?? '';
$detailsLog   = "Mengakses Review Payroll untuk Anggota ID $id_anggota pada bulan $bulan tahun $tahun.";
add_audit_log($conn, $user_nip, 'ViewPayroll', $detailsLog);

$timestamp    = strtotime($tglPayrollParam);
$tanggalCetak = date('d', $timestamp) . ' ' . getIndonesianMonthName((int)date('n', $timestamp)) . ' ' . date('Y', $timestamp);
$periode      = getIndonesianMonthName($bulan) . ' ' . $tahun;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Review Payroll - <?= htmlspecialchars($namaKaryawan); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        /* Ukuran font sedikit diperkecil */
        body {
            font-size: 0.8rem;
        }
        #main-content {
            transition: opacity 0.3s ease;
        }
        .btn {
            transition: background-color 0.3s, transform 0.2s;
        }
        .btn:hover {
            transform: scale(1.05);
        }
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }
        .spinner-border {
            display: none;
            margin-left: 5px;
        }

        /* Kecilkan jarak padding card & table */
        .card {
            margin-bottom: 0.5rem; 
        }
        .card-body, .card-header {
            padding: 0.75rem; 
        }
        .table thead th, .table tbody td {
            padding: 0.2rem;
            text-align: center; /* Teks tabel di tengah */
            vertical-align: middle; /* Agar konten badge/tombol tepat di tengah sel */
        }
        .table-sm td, .table-sm th {
            padding: 0.2rem;
        }

        /* Kolom non-editable -> warna lebih gelap */
        .non-editable {
            background-color: #f0f0f0; /* atau sesuaikan warna yang diinginkan */
        }
    </style>
    <script>
        const CSRF_TOKEN = '<?= htmlspecialchars($csrf_token); ?>';
        const EMPLOYEE_ID = <?= json_encode($id_anggota); ?>;
        const PAYROLL_ID = <?= json_encode($id_payroll); ?>;
        const TOTAL_EARNINGS = <?= $totalPendapatan; ?>;
        const TOTAL_DEDUCTIONS = <?= $totalPotongan; ?>;
    </script>
</head>
<body>
    <!-- Container Utama Tanpa Sidebar & Navbar -->
    <div class="container" id="main-content">
        <h1 class="h3 mb-4 text-dark"><i class="fas fa-file-invoice-dollar me-2"></i>Review Payroll</h1>

        <div class="row">
            <!-- Informasi Umum -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h5 class="card-title mb-0"><strong>Informasi Umum</strong></h5>
                    </div>
                    <div class="card-body">
                        <!-- Baris 1: Nama Karyawan & Periode -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label"><strong>Nama Karyawan</strong></label>
                                <input type="text" class="form-control non-editable"
                                       value="<?= htmlspecialchars($namaKaryawan); ?>"
                                       readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><strong>Periode</strong></label>
                                <input type="text" class="form-control non-editable"
                                       value="<?= htmlspecialchars($bulanName . ' ' . $tahun); ?>"
                                       readonly>
                            </div>
                        </div>

                        <!-- Baris 2: Masa Kerja & No. Rekening -->
                        <div class="row g-3 mt-3">
                            <div class="col-md-6">
                                <label class="form-label"><strong>Masa Kerja</strong></label>
                                <input type="text" class="form-control non-editable"
                                       value="<?= htmlspecialchars($masa_kerja); ?>"
                                       readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><strong>No. Rekening</strong></label>
                                <input type="text" id="inputNoRek" class="form-control"
                                       value="<?= htmlspecialchars($noRek); ?>">
                            </div>
                        </div>

                        <!-- Baris 3: Level Indeks & Tanggal Payroll -->
                        <div class="row g-3 mt-3">
                            <div class="col-md-6">
                                <label class="form-label"><strong>Level Indeks</strong></label>
                                <input type="text" id="inputLevelIndeks" class="form-control non-editable"
                                       value="<?= htmlspecialchars($salary_index_level
                                            ? $salary_index_level . ' (Rp ' . number_format($salary_index_amount, 2, ',', '.') . ')'
                                            : 'Belum ada'); ?>"
                                       readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><strong>Tanggal Payroll</strong></label>
                                <input type="datetime-local" id="inputTanggalPayroll" class="form-control"
                                       value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($tglPayrollParam))); ?>"
                                       required>
                            </div>
                        </div>

                        <!-- Baris 4: Catatan -->
                        <div class="mt-3">
                            <label class="form-label"><strong>Catatan / Deskripsi Payroll</strong></label>
                            <textarea id="inputDescription" class="form-control" rows="3"
                                      placeholder="Tambah catatan jika perlu..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Perhitungan Payroll -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h5 class="card-title mb-0"><strong>Perhitungan Payroll</strong></h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="inputGajiPokok"><strong>Gaji Pokok</strong></label>
                            <input type="text" id="inputGajiPokok" class="form-control currency-input non-editable"
                                   value="<?= htmlspecialchars($gaji_pokok); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label><strong>Total Pendapatan (Payheads)</strong></label>
                            <input type="text" id="inputTotalEarnings" class="form-control currency-input non-editable"
                                   value="<?= htmlspecialchars($totalPendapatan); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label><strong>Total Potongan</strong></label>
                            <input type="text" id="inputTotalDeductions" class="form-control currency-input non-editable"
                                   value="<?= htmlspecialchars($totalPotongan); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label><strong>Potongan Koperasi (Wajib Diisi)</strong></label>
                            <input type="text" id="inputPotonganKoperasi" class="form-control currency-input"
                                   value="0">
                        </div>
                        <div class="mb-3">
                            <label><strong>Estimasi Gaji Bersih</strong></label>
                            <input type="text" id="inputNetSalary" class="form-control currency-input non-editable"
                                   value="<?= htmlspecialchars($gaji_bersih); ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row baru untuk Detail Payheads agar melebar full -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h5 class="card-title mb-0"><strong>Detail Payheads</strong></h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table id="payheadsTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Nama Payhead</th>
                                        <th>Jenis</th>
                                        <th>Nominal</th>
                                        <th>Status</th>
                                        <th>Keterangan</th>
                                        <th>Dokumen</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($payheads)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center"><em>Belum ada payhead</em></td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($payheads as $ph): ?>
                                            <?php
                                                // Buat nama file untuk dokumen
                                                $ext = '';
                                                $downloadName = '';
                                                if (!empty($ph['support_doc_path'])) {
                                                    $ext = pathinfo($ph['support_doc_path'], PATHINFO_EXTENSION);
                                                    $cleanPayheadName = preg_replace('/[^A-Za-z0-9_\- ]+/', '', $ph['nama_payhead'] ?? '');
                                                    $cleanPayheadName = str_replace(' ', '_', trim($cleanPayheadName));
                                                    $downloadName = $nip . '_' . $bulanVal . '_' . $cleanPayheadName . '.' . $ext;
                                                }
                                            ?>
                                            <tr>
                                                <!-- Kolom Nama Payhead (non-editable) -->
                                                <td class="non-editable">
                                                    <?= htmlspecialchars($ph['nama_payhead'] ?? ''); ?>
                                                </td>
                                                <!-- Kolom Jenis (non-editable) -->
                                                <td class="non-editable">
                                                    <?= htmlspecialchars(ucfirst($ph['jenis'] ?? '')); ?>
                                                </td>
                                                <!-- Kolom Nominal (bisa diedit via tombol) -->
                                                <td>
                                                    <?= number_format($ph['amount'] ?? 0, 2, ',', '.'); ?>
                                                </td>
                                                <!-- Kolom Status (non-editable) -->
                                                <td class="non-editable">
                                                    <?= htmlspecialchars($ph['status'] ?? ''); ?>
                                                </td>
                                                <!-- Kolom Keterangan (non-editable) -->
                                                <td class="non-editable">
                                                    <?php if (!empty($ph['remarks'])): ?>
                                                        <small><em><?= htmlspecialchars($ph['remarks'] ?? ''); ?></em></small>
                                                    <?php else: ?>
                                                        <small><em>-</em></small>
                                                    <?php endif; ?>
                                                </td>
                                                <!-- Kolom Dokumen (non-editable) -->
                                                <td class="non-editable">
                                                    <?php if (!empty($ph['support_doc_path'])): ?>
                                                        <a class="btn btn-sm btn-info"
                                                           href="<?= '/payroll_absensi_v2/uploads/payhead_support/' . basename($ph['support_doc_path']); ?>"
                                                           download="<?= $downloadName; ?>">
                                                            <i class="bi bi-download"></i> Download Dokumen
                                                        </a>
                                                    <?php else: ?>
                                                        <em>-</em>
                                                    <?php endif; ?>
                                                </td>
                                                <!-- Kolom Aksi -->
                                                <td class="text-center">
                                                    <?php if (!empty($ph['is_rapel']) && $ph['is_rapel'] == 1): ?>
                                                        <span class="badge bg-secondary">Rapel</span>
                                                    <?php else: ?>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-info btn-edit-payhead"
                                                                    data-idpayhead="<?= htmlspecialchars($ph['id_payhead'] ?? ''); ?>"
                                                                    data-amount="<?= htmlspecialchars($ph['amount'] ?? 0); ?>"
                                                                    data-jenis="<?= htmlspecialchars($ph['jenis'] ?? ''); ?>">
                                                                <i class="fas fa-pen"></i>
                                                            </button>
                                                            <button class="btn btn-danger btn-delete-payhead"
                                                                    data-idpayhead="<?= htmlspecialchars($ph['id_payhead'] ?? ''); ?>">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div><!-- /.table-responsive -->
                    </div><!-- /.card-body -->
                </div><!-- /.card -->
            </div><!-- /.col-12 -->
        </div><!-- /.row -->

        <!-- Tombol Aksi -->
        <div class="row mb-4">
            <div class="col-12">
                <a href="/payroll_absensi_v2/keuangan/list_payroll.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancel
                </a>
                <form id="formPayroll" action="" method="POST" class="d-inline">
                    <input type="hidden" name="id_anggota" value="<?= htmlspecialchars($id_anggota); ?>">
                    <input type="hidden" name="bulan_int" value="<?= htmlspecialchars($bulan); ?>">
                    <input type="hidden" name="tahun" value="<?= htmlspecialchars($tahun); ?>">
                    <input type="hidden" name="id_rekap_absensi" value="<?= htmlspecialchars($id_rekap_absensi); ?>">
                    <?php if (!empty($payheads)): ?>
                        <?php foreach ($payheads as $ph): ?>
                            <input type="hidden" name="payheads_ids[]" value="<?= htmlspecialchars($ph['id_payhead'] ?? ''); ?>">
                            <input type="hidden" name="payheads_jenis[]" value="<?= htmlspecialchars($ph['jenis'] ?? ''); ?>">
                            <input type="hidden" name="payheads_amount[]" value="<?= htmlspecialchars($ph['amount'] ?? 0); ?>">
                            <input type="hidden" name="payheads_is_rapel[]" value="<?= htmlspecialchars($ph['is_rapel'] ?? 0); ?>">
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <!-- Field tersembunyi -->
                    <input type="hidden" name="no_rekening" id="fieldNoRek" value="">
                    <input type="hidden" name="gaji_pokok" id="fieldGajiPokok" value="">
                    <input type="hidden" name="total_earnings" id="fieldTotalEarnings" value="">
                    <input type="hidden" name="total_deductions" id="fieldTotalDeductions" value="">
                    <input type="hidden" name="potongan_koperasi" id="fieldPotonganKoperasi" value="0">
                    <input type="hidden" name="inputDescription" id="fieldDescription" value="">
                    <input type="hidden" name="tgl_payroll" id="fieldTglPayroll" value="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                    <button type="submit" class="btn btn-success float-end">
                        <i class="fas fa-check"></i> Proses Payroll
                    </button>
                </form>
                <button type="button" class="btn btn-warning float-end me-2" id="btnRejectPayroll">
                    <i class="fas fa-times"></i> Tolak Payroll
                </button>
            </div>
        </div>
    </div>
    <!-- End Container Utama -->

     <!-- MODAL: Edit Payhead -->
     <div class="modal fade" id="modalEditPayhead" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="formEditPayhead">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Payhead</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="edit_idpayhead" name="id_payhead">
                        <div class="mb-3">
                            <label>Jenis</label>
                            <input type="text" id="edit_jenis" class="form-control" name="edit_jenis" readonly>
                        </div>
                        <div class="mb-3">
                            <label>Nilai (Amount)</label>
                            <input type="text" id="edit_amount" class="form-control currency-input" name="edit_amount" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: Konfirmasi Delete Payhead -->
    <div class="modal fade" id="modalDeletePayhead" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Hapus Payhead</h5>
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Anda yakin ingin menghapus payhead ini?</p>
                    <input type="hidden" id="del_idpayhead" name="del_idpayhead">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmDelete">Ya, Hapus</button>
                </div>
            </div>
        </div>
    </div>

    <?php $conn->close(); ?>
    <!-- JS Dependencies: Pastikan urutannya benar -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/autonumeric@4.6.0/dist/autoNumeric.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
    <script>
    $(document).ready(function() {
        // Inisialisasi DataTable
        $('#payheadsTable').DataTable({
    responsive: true,
    autoWidth: false,
    paging: false,        
    info: false,          
    lengthChange: false,  
    language: { url: "/payroll_absensi_v2/assets/plugins/Indonesian.json" }
});
        
        // Inisialisasi AutoNumeric
        const anGajiPokok = new AutoNumeric('#inputGajiPokok', {
            digitGroupSeparator: '.',
            decimalCharacter: ',',
            decimalPlaces: 2,
            unformatOnSubmit: true,
            decimalCharacterAlternative: '.'
        });
        const anTotalEarnings = new AutoNumeric('#inputTotalEarnings', {
            digitGroupSeparator: '.',
            decimalCharacter: ',',
            decimalPlaces: 2,
            unformatOnSubmit: true
        });
        const anTotalDeductions = new AutoNumeric('#inputTotalDeductions', {
            digitGroupSeparator: '.',
            decimalCharacter: ',',
            decimalPlaces: 2,
            unformatOnSubmit: true
        });
        const anPotKop = new AutoNumeric('#inputPotonganKoperasi', {
            digitGroupSeparator: '.',
            decimalCharacter: ',',
            decimalPlaces: 2,
            unformatOnSubmit: true
        });
        const anNetSalary = new AutoNumeric('#inputNetSalary', {
            digitGroupSeparator: '.',
            decimalCharacter: ',',
            decimalPlaces: 2,
            unformatOnSubmit: true,
            readOnly: true
        });
        const anEditAmount = new AutoNumeric('#edit_amount', {
            digitGroupSeparator: '.',
            decimalCharacter: ',',
            decimalPlaces: 2,
            unformatOnSubmit: true
        });

        // Fungsi perhitungan ulang gaji bersih
        function recalcNetSalary() {
            let gajiPokok = parseFloat(anGajiPokok.getNumber()) || 0;
            let totalEarnings = parseFloat(anTotalEarnings.getNumber()) || 0;
            let totalDeductions = parseFloat(anTotalDeductions.getNumber()) || 0;
            let potKoperasi = parseFloat(anPotKop.getNumber()) || 0;
            let netSalary = gajiPokok + totalEarnings - totalDeductions - potKoperasi;
            anNetSalary.set(netSalary);
        }
        $('#inputPotonganKoperasi').on('input', recalcNetSalary);
        recalcNetSalary();

        // Event: Edit Payhead
        $(document).on('click', '.btn-edit-payhead', function(){
            const idPayhead = $(this).data('idpayhead');
            const amount = $(this).data('amount');
            const jenis = $(this).data('jenis');
            $('#edit_idpayhead').val(idPayhead);
            $('#edit_jenis').val(jenis);
            anEditAmount.set(amount);
            new bootstrap.Modal(document.getElementById('modalEditPayhead')).show();
        });
        $('#formEditPayhead').on('submit', function(e){
            e.preventDefault();
            const idPayhead = $('#edit_idpayhead').val();
            const newAmount = anEditAmount.getNumber();
            $.ajax({
                url: '?ajax=1',
                type: 'POST',
                dataType: 'json',
                data: {
                    case: 'UpdatePayhead',
                    payroll_id: PAYROLL_ID,
                    id_payhead: idPayhead,
                    amount: newAmount,
                    csrf_token: CSRF_TOKEN
                },
                beforeSend: function(){
                    $('#modalEditPayhead button[type="submit"]').prop('disabled', true);
                },
                success: function(resp){
                    $('#modalEditPayhead button[type="submit"]').prop('disabled', false);
                    if(resp.code === 0) {
                        Swal.fire({
                            icon: 'success',
                            title: resp.result,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => { location.reload(); });
                    } else {
                        Swal.fire('Gagal', resp.result, 'error');
                    }
                },
                error: function(){
                    $('#modalEditPayhead button[type="submit"]').prop('disabled', false);
                    Swal.fire('Error', 'Terjadi kesalahan saat mengupdate payhead.', 'error');
                }
            });
        });

        // Event: Delete Payhead
        $(document).on('click', '.btn-delete-payhead', function(){
            const idPayhead = $(this).data('idpayhead');
            $('#del_idpayhead').val(idPayhead);
            new bootstrap.Modal(document.getElementById('modalDeletePayhead')).show();
        });
        $('#btnConfirmDelete').on('click', function(){
            const idPayhead = $('#del_idpayhead').val();
            if(!idPayhead) {
                Swal.fire('Error', 'ID Payhead tidak ditemukan.', 'error');
                return;
            }
            $.ajax({
                url: '?ajax=1',
                type: 'POST',
                dataType: 'json',
                data: {
                    case: 'DeletePayhead',
                    payroll_id: PAYROLL_ID,
                    id_payhead: idPayhead,
                    csrf_token: CSRF_TOKEN
                },
                beforeSend: function(){
                    $('#btnConfirmDelete').prop('disabled', true);
                },
                success: function(resp){
                    $('#btnConfirmDelete').prop('disabled', false);
                    if(resp.code === 0) {
                        Swal.fire({
                            icon: 'success',
                            title: resp.result,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => { location.reload(); });
                    } else {
                        Swal.fire('Gagal', resp.result, 'error');
                    }
                },
                error: function(){
                    $('#btnConfirmDelete').prop('disabled', false);
                    Swal.fire('Error', 'Terjadi kesalahan saat menghapus payhead.', 'error');
                }
            });
        });

        // Event: Reject Payroll
        $('#btnRejectPayroll').on('click', function(){
            Swal.fire({
                title: 'Anda yakin menolak payroll ini?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Tolak',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if(result.isConfirmed) {
                    $.ajax({
                        url: '?ajax=1',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            case: 'RejectPayroll',
                            id_anggota: EMPLOYEE_ID,
                            bulan: BULAN_VAL,
                            tahun: TAHUN_VAL,
                            csrf_token: CSRF_TOKEN
                        },
                        success: function(resp) {
                            if(resp.code === 0) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Payroll ditolak!',
                                    showConfirmButton: false,
                                    timer: 1500
                                }).then(() => {
                                    window.location.href = '/payroll_absensi_v2/keuangan/list_payroll.php';
                                });
                            } else {
                                Swal.fire('Gagal', resp.result, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'Terjadi kesalahan saat menolak payroll.', 'error');
                        }
                    });
                }
            });
        });

        // Sebelum submit finalisasi, salin nilai input ke hidden field
        $('#formPayroll').on('submit', function(){
            $('#fieldNoRek').val($('#inputNoRek').val());
            $('#fieldGajiPokok').val($('#inputGajiPokok').val());
            $('#fieldTotalEarnings').val($('#inputTotalEarnings').val());
            $('#fieldTotalDeductions').val($('#inputTotalDeductions').val());
            $('#fieldDescription').val($('#inputDescription').val());
            $('#fieldTglPayroll').val($('#inputTanggalPayroll').val());
            let potKoperasiNum = anPotKop.getNumber();
            $('#fieldPotonganKoperasi').val(potKoperasiNum);
        });
    });
    </script>
</body>
</html>
