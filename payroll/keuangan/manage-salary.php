<?php
// File: /payroll_absensi_v2/payroll/keuangan/manage-salary.php

// Mulai session dengan aman dan sertakan file helpers serta koneksi database
require_once __DIR__ . '/../../helpers.php';
start_session_safe();

// Cek Role: hanya 'keuangan' dan 'superadmin' yang boleh akses
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['keuangan', 'superadmin'])) {
    header("Location: /payroll_absensi_v2/login.php");
    exit();
}

require_once __DIR__ . '/../../koneksi.php';

// Inisialisasi error handling dan CSRF token
init_error_handling();
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

// --- Ambil parameter periode (bulan dan tahun) dari GET atau POST ---
// Kami memanfaatkan fungsi sanitize_input() dan monthNameToInt() dari helpers.php
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
    // Jika tidak tersedia di GET, cek POST
    if (isset($_POST['bulan_int']) && isset($_POST['tahun'])) {
        $bulanVal = intval($_POST['bulan_int']);
        $tahunVal = intval($_POST['tahun']);
    }
}

// ==================================================================
// BAGIAN AJAX: Update / Delete Payhead / Reject Payroll
// ==================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    $csrf_token_post = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    verify_csrf_token($csrf_token_post);
    $case = isset($_POST['case']) ? sanitize_input($_POST['case']) : '';

    switch ($case) {
        case 'UpdatePayhead':
            $id_anggota = isset($_POST['id_anggota']) ? intval($_POST['id_anggota']) : 0;
            $id_payhead = isset($_POST['id_payhead']) ? intval($_POST['id_payhead']) : 0;
            $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
            if ($id_anggota <= 0 || $id_payhead <= 0) {
                send_response(1, 'Parameter invalid.');
            }
            $stmt = $conn->prepare("UPDATE employee_payheads SET amount = ? WHERE id_anggota = ? AND id_payhead = ?");
            if (!$stmt) {
                send_response(1, 'Prepare failed: ' . $conn->error);
            }
            $stmt->bind_param("dii", $amount, $id_anggota, $id_payhead);
            if ($stmt->execute()) {
                $stmt->close();
                $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
                $details = "Memperbarui Payhead ID $id_payhead untuk Karyawan ID $id_anggota dengan jumlah Rp " . number_format($amount, 2, ',', '.');
                if (!add_audit_log($conn, $user_id, 'UpdatePayhead', $details)) {
                    send_response(1, 'Payhead berhasil diupdate, tetapi gagal mencatat audit log.');
                }
                send_response(0, 'Payhead berhasil diupdate.');
            } else {
                $stmt->close();
                send_response(1, 'Gagal update payhead: ' . $stmt->error);
            }
            break;

        case 'RejectPayroll':
            $id_anggota = isset($_POST['id_anggota']) ? intval($_POST['id_anggota']) : 0;
            $bulanFromAjax = isset($_POST['bulan']) ? intval($_POST['bulan']) : 0;
            $tahunFromAjax = isset($_POST['tahun']) ? intval($_POST['tahun']) : 0;
            if ($id_anggota <= 0 || $bulanFromAjax <= 0 || $tahunFromAjax <= 0) {
                send_response(1, 'Parameter tidak valid (reject).');
            }
            $stmtReject = $conn->prepare("UPDATE employee_payheads SET status = 'revisi' WHERE id_anggota=? AND status IN ('draft')");
            $stmtReject->bind_param("i", $id_anggota);
            if (!$stmtReject->execute()) {
                $stmtReject->close();
                send_response(1, 'Gagal menolak payroll (employee_payheads): ' . $stmtReject->error);
            }
            $stmtReject->close();
            $stmtPayrollReject = $conn->prepare("UPDATE payroll SET status = 'revisi' WHERE id_anggota=? AND bulan=? AND tahun=?");
            $stmtPayrollReject->bind_param("iii", $id_anggota, $bulanFromAjax, $tahunFromAjax);
            if (!$stmtPayrollReject->execute()) {
                $stmtPayrollReject->close();
                send_response(1, 'Gagal menolak payroll (payroll): ' . $stmtPayrollReject->error);
            }
            $stmtPayrollReject->close();
            send_response(0, 'Payroll ditolak. Status payheads dan payroll telah diubah menjadi revisi.');
            break;

        case 'DeletePayhead':
            $id_anggota = isset($_POST['id_anggota']) ? intval($_POST['id_anggota']) : 0;
            $id_payhead = isset($_POST['id_payhead']) ? intval($_POST['id_payhead']) : 0;
            if ($id_anggota <= 0 || $id_payhead <= 0) {
                send_response(1, 'Parameter invalid.');
            }
            $stmtDel = $conn->prepare("DELETE FROM employee_payheads WHERE id_anggota=? AND id_payhead=?");
            if (!$stmtDel) {
                send_response(1, 'Prepare failed: ' . $conn->error);
            }
            $stmtDel->bind_param("ii", $id_anggota, $id_payhead);
            if ($stmtDel->execute()) {
                $stmtDel->close();
                $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
                $details = "Menghapus Payhead ID $id_payhead untuk Karyawan ID $id_anggota.";
                if (!add_audit_log($conn, $user_id, 'DeletePayhead', $details)) {
                    send_response(1, 'Payhead berhasil dihapus, tetapi gagal mencatat audit log.');
                }
                send_response(0, 'Payhead berhasil dihapus.');
            } else {
                $stmtDel->close();
                send_response(1, 'Gagal hapus payhead: ' . $stmtDel->error);
            }
            break;

        default:
            send_response(1, 'Case tidak dikenali.');
            break;
    }
    exit;
}

// ==================================================================
// BAGIAN POST => Proses Insert payroll dan payroll_detail
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['ajax'])) {
    $csrf_token_post = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    verify_csrf_token($csrf_token_post);

    $id_anggota       = isset($_POST['id_anggota']) ? intval($_POST['id_anggota']) : (isset($_GET['id_anggota']) ? intval($_GET['id_anggota']) : 0);
    $bulan_int        = isset($_POST['bulan_int']) ? intval($_POST['bulan_int']) : (isset($_GET['bulan_int']) ? intval($_GET['bulan_int']) : 0);
    $tahun            = isset($_POST['tahun']) ? intval($_POST['tahun']) : (isset($_GET['tahun']) ? intval($_GET['tahun']) : 0);
    $id_rekap_absensi = isset($_POST['id_rekap_absensi']) ? intval($_POST['id_rekap_absensi']) : (isset($_GET['id_rekap_absensi']) ? intval($_GET['id_rekap_absensi']) : 0);

    $no_rekening      = isset($_POST['no_rekening']) ? sanitize_input($_POST['no_rekening']) : '';
    $gaji_pokok       = isset($_POST['gaji_pokok']) ? floatval($_POST['gaji_pokok']) : 0;
    $total_earnings   = isset($_POST['total_earnings']) ? floatval($_POST['total_earnings']) : 0;
    $total_deductions = isset($_POST['total_deductions']) ? floatval($_POST['total_deductions']) : 0;
    $payheads_ids     = isset($_POST['payheads_ids']) ? $_POST['payheads_ids'] : [];
    $payheads_jenis   = isset($_POST['payheads_jenis']) ? $_POST['payheads_jenis'] : [];
    $payheads_amount  = isset($_POST['payheads_amount']) ? $_POST['payheads_amount'] : [];
    $catatan          = isset($_POST['inputDescription']) ? sanitize_input($_POST['inputDescription']) : '';
    $tgl_payroll      = isset($_POST['tgl_payroll']) ? sanitize_input($_POST['tgl_payroll']) : date('Y-m-d H:i:s');

    if ($id_anggota <= 0 || $bulan_int <= 0 || $tahun <= 0 || $id_rekap_absensi <= 0) {
        var_dump($_POST);
        die("Parameter tidak valid.");
    }
    
    $conn->begin_transaction();
    try {
        // (A) Update employee_payheads menjadi 'final'
        $stmtToFinal = $conn->prepare("UPDATE employee_payheads SET status='final' WHERE id_anggota=? AND status IN ('draft','revisi')");
        if (!$stmtToFinal) {
            throw new Exception("Gagal prepare update status payheads: " . $conn->error);
        }
        $stmtToFinal->bind_param("i", $id_anggota);
        if (!$stmtToFinal->execute()) {
            throw new Exception("Gagal update status payheads: " . $stmtToFinal->error);
        }
        $stmtToFinal->close();

        // (B) Insert ke tabel payroll dengan status 'final'
        $status = 'final';
        $stmtPayroll = $conn->prepare("INSERT INTO payroll (id_anggota, bulan, tahun, tgl_payroll, gaji_pokok, total_pendapatan, total_potongan, gaji_bersih, no_rekening, catatan, id_rekap_absensi, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmtPayroll) {
            throw new Exception("Prepare payroll gagal: " . $conn->error);
        }
        $gaji_bersih = $gaji_pokok + $total_earnings - $total_deductions;
        $stmtPayroll->bind_param("iiisddddssis", $id_anggota, $bulan_int, $tahun, $tgl_payroll, $gaji_pokok, $total_earnings, $total_deductions, $gaji_bersih, $no_rekening, $catatan, $id_rekap_absensi, $status);
        if (!$stmtPayroll->execute()) {
            throw new Exception("Gagal insert payroll: " . $stmtPayroll->error);
        }
        $id_payroll = $stmtPayroll->insert_id;
        $stmtPayroll->close();

        // Insert ke tabel payroll_final (tanpa kolom 'status')
        $stmtPayrollFinal = $conn->prepare("INSERT INTO payroll_final (id_anggota, bulan, tahun, tgl_payroll, gaji_pokok, total_pendapatan, total_potongan, gaji_bersih, no_rekening, catatan, id_rekap_absensi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmtPayrollFinal) {
            throw new Exception("Prepare payroll_final gagal: " . $conn->error);
        }
        $stmtPayrollFinal->bind_param("iiisddddsis", $id_anggota, $bulan_int, $tahun, $tgl_payroll, $gaji_pokok, $total_earnings, $total_deductions, $gaji_bersih, $no_rekening, $catatan, $id_rekap_absensi);
        if (!$stmtPayrollFinal->execute()) {
            throw new Exception("Gagal insert payroll_final: " . $stmtPayrollFinal->error);
        }
        $stmtPayrollFinal->close();

        // (C) Insert detail payroll ke payroll_detail
        $stmtDetail = $conn->prepare("INSERT INTO payroll_detail (id_payroll, id_payhead, jenis, amount) VALUES (?, ?, ?, ?)");
        if (!$stmtDetail) {
            throw new Exception("Prepare payroll_detail gagal: " . $conn->error);
        }
        foreach ($payheads_ids as $index => $id_payhead) {
            $jenis = isset($payheads_jenis[$index]) ? sanitize_input($payheads_jenis[$index]) : '';
            $amount = isset($payheads_amount[$index]) ? floatval($payheads_amount[$index]) : 0;
            $stmtDetail->bind_param("iisd", $id_payroll, $id_payhead, $jenis, $amount);
            if (!$stmtDetail->execute()) {
                throw new Exception("Gagal insert payroll_detail: " . $stmtDetail->error);
            }
        }
        $stmtDetail->close();

        $conn->commit();

        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $details = "Membuat Payroll ID $id_payroll untuk Karyawan ID $id_anggota pada bulan $bulan_int tahun $tahun. " 
                 . "Pendapatan = Rp " . number_format($total_earnings, 2, ',', '.') 
                 . ", Potongan = Rp " . number_format($total_deductions, 2, ',', '.') 
                 . ", Gaji Bersih = Rp " . number_format($gaji_bersih, 2, ',', '.');
        if (!add_audit_log($conn, $user_id, 'InsertPayroll', $details)) {
            error_log("Gagal mencatat audit log untuk InsertPayroll ID $id_payroll.");
        }

        header("Location: payroll-details.php?id_payroll=$id_payroll");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        die("Terjadi kesalahan: " . $e->getMessage());
    }
} else {
    // ==================================================================
    // BAGIAN GET => Review Payroll (Belum Insert ke payroll)
    // ==================================================================
    $id_anggota = isset($_GET['id_anggota']) ? intval($_GET['id_anggota']) : 0;
    $bulanParam  = isset($_GET['bulan']) ? sanitize_input($_GET['bulan']) : '';
    $tahunStr   = isset($_GET['tahun']) ? sanitize_input($_GET['tahun']) : '';
    $tglPayrollParam = isset($_GET['tgl']) ? sanitize_input($_GET['tgl']) : date('Y-m-d H:i:s');

    if ($id_anggota <= 0 || empty($bulanParam) || empty($tahunStr)) {
        die("Parameter tidak valid.");
    }

    if (is_numeric($bulanParam)) {
        $bulan = intval($bulanParam);
        $bulanName = getIndonesianMonthName($bulan);
    } else {
        $bulan = monthNameToInt($bulanParam);
        $bulanName = ucfirst($bulanParam);
    }
    $tahun = intval($tahunStr);
    if ($bulan <= 0 || $tahun <= 0) {
        die("Parameter bulan/tahun tidak valid.");
    }

    // Cek apakah payroll sudah ada untuk periode ini
    $stmtCheck = $conn->prepare("SELECT id, status FROM payroll WHERE id_anggota=? AND bulan=? AND tahun=? LIMIT 1");
    if (!$stmtCheck) {
        die("Prepare gagal: " . $conn->error);
    }
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
    } else {
        $stmtCheck->close();
    }

    // Pastikan rekap_absensi ada
    $stmtRekap = $conn->prepare("SELECT id FROM rekap_absensi WHERE id_anggota=? AND bulan=? AND tahun=? LIMIT 1");
    if (!$stmtRekap) {
        die("Prepare rekap gagal: " . $conn->error);
    }
    $stmtRekap->bind_param("iii", $id_anggota, $bulan, $tahun);
    $stmtRekap->execute();
    $resRekap = $stmtRekap->get_result();
    if ($resRekap->num_rows > 0) {
        $rowRekap = $resRekap->fetch_assoc();
        $id_rekap_absensi = $rowRekap['id'];
    } else {
        $stmtIns = $conn->prepare("INSERT INTO rekap_absensi (id_anggota, bulan, tahun, total_hadir, total_izin, total_cuti, total_tanpa_keterangan, total_sakit) VALUES (?, ?, ?, 0,0,0,0,0)");
        $stmtIns->bind_param("iii", $id_anggota, $bulan, $tahun);
        if (!$stmtIns->execute()) {
            die("Gagal insert rekap_absensi: " . $stmtIns->error);
        }
        $id_rekap_absensi = $stmtIns->insert_id;
        $stmtIns->close();
    }
    $stmtRekap->close();

    // Ambil data payhead (status, remarks, dokumen)
    $stmtPH = $conn->prepare("SELECT ep.id_payhead, ph.nama_payhead, ph.jenis, ep.amount, ep.status, ep.remarks, ep.support_doc_path FROM employee_payheads ep JOIN payheads ph ON ep.id_payhead = ph.id WHERE ep.id_anggota=?");
    if (!$stmtPH) {
        die("Prepare payheads gagal: " . $conn->error);
    }
    $stmtPH->bind_param("i", $id_anggota);
    $stmtPH->execute();
    $resPH = $stmtPH->get_result();
    $payheads = [];
    while($r = $resPH->fetch_assoc()){
        $payheads[] = $r;
    }
    $stmtPH->close();

    // Ambil data karyawan
    $stmtKar = $conn->prepare("SELECT * FROM anggota_sekolah WHERE id=? LIMIT 1");
    if (!$stmtKar) {
        die("Prepare karyawan gagal: " . $conn->error);
    }
    $stmtKar->bind_param("i", $id_anggota);
    $stmtKar->execute();
    $resKar = $stmtKar->get_result();
    if ($resKar->num_rows == 0) {
        die("Karyawan tidak ditemukan.");
    }
    $karyawan = $resKar->fetch_assoc();
    $stmtKar->close();

    // Level indeks
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
    $gaji_pokok_employee = (float)($karyawan['gaji_pokok'] ?? 0);
    $gaji_pokok = $gaji_pokok_employee + $salary_index_amount;

    $total_earnings = 0;
    $total_deductions = 0;
    foreach($payheads as $ph) {
        if ($ph['jenis'] === 'earnings') {
            $total_earnings += (float)$ph['amount'];
        } else {
            $total_deductions += (float)$ph['amount'];
        }
    }
    $gaji_kotor  = $gaji_pokok + $total_earnings;
    $gaji_bersih = $gaji_kotor - $total_deductions;

    $masa_kerja = ((int)($karyawan['masa_kerja_tahun'] ?? 0)) . " Tahun, " . ((int)($karyawan['masa_kerja_bulan'] ?? 0)) . " Bulan";

    $namaKaryawan = $karyawan['nama'] ?? '';
    $noRek        = $karyawan['no_rekening'] ?? '';
    $catatan      = "";

    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $log_details = "Mengakses Review Payroll untuk Karyawan ID $id_anggota pada bulan $bulan tahun $tahun.";
    if (!add_audit_log($conn, $user_id, 'ViewPayroll', $log_details)) {
        error_log("Gagal mencatat audit log untuk ViewPayroll ID $id_anggota.");
    }
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Review Payroll - <?= htmlspecialchars($namaKaryawan); ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css">
  <style>
    .content-wrapper { margin-left: 0; }
    .main-header.navbar { width: 100%; }
    .footer.main-footer { width: 100%; margin-left: 0; }
  </style>
  <script>
      const CSRF_TOKEN = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>';
  </script>
</head>
<body>
<div class="wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0">Review Payroll Karyawan</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active">Review Payroll</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <!-- Main content -->
    <section class="content">
    <div class="container-fluid">
        <div class="row">
            <!-- Kolom Kiri -->
            <div class="col-md-6">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">General</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Form fields untuk review payroll -->
                        <div class="form-group">
                            <label for="inputName">Nama Karyawan</label>
                            <input type="text" id="inputName" class="form-control" value="<?= htmlspecialchars($namaKaryawan); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="inputDescription">Catatan / Deskripsi Payroll</label>
                            <textarea id="inputDescription" class="form-control" rows="3" placeholder="Tambah catatan jika perlu..."></textarea>
                        </div>
                        <div class="form-group">
                            <label for="inputStatus">Periode Gaji</label>
                            <input type="text" id="inputStatus" class="form-control" value="<?= htmlspecialchars($bulanName . ' ' . $tahun); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="inputMasaKerja">Masa Kerja</label>
                            <input type="text" id="inputMasaKerja" class="form-control" value="<?= htmlspecialchars($masa_kerja); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="inputLevelIndeks">Level Indeks</label>
                            <input type="text" id="inputLevelIndeks" class="form-control" value="<?= htmlspecialchars($salary_index_level ? $salary_index_level . ' (Rp ' . number_format($salary_index_amount, 2, ',', '.') . ')' : 'Belum ada'); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="inputNoRek">No. Rekening</label>
                            <input type="text" id="inputNoRek" class="form-control" value="<?= htmlspecialchars($noRek); ?>">
                        </div>
                        <div class="form-group">
                            <label for="inputTanggalPayroll">Tanggal Payroll</label>
                            <input type="datetime-local" id="inputTanggalPayroll" class="form-control" value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($tglPayrollParam))); ?>" required>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Kolom Kanan: Perhitungan payroll & detail payheads -->
            <div class="col-md-6">
                <div class="card card-secondary">
                    <div class="card-header">
                        <h3 class="card-title">Payroll Calculation</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="inputGajiPokok">Gaji Pokok</label>
                            <input type="text" id="inputGajiPokok" class="form-control currency-input" value="<?= htmlspecialchars($gaji_pokok); ?>">
                        </div>
                        <div class="form-group">
                            <label for="inputTotalEarnings">Total Pendapatan (Payheads)</label>
                            <input type="text" id="inputTotalEarnings" class="form-control currency-input" value="<?= htmlspecialchars($total_earnings); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="inputTotalDeductions">Total Potongan</label>
                            <input type="text" id="inputTotalDeductions" class="form-control currency-input" value="<?= htmlspecialchars($total_deductions); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="inputNetSalary">Estimasi Gaji Bersih</label>
                            <input type="text" id="inputNetSalary" class="form-control currency-input" value="<?= htmlspecialchars($gaji_bersih); ?>" readonly>
                        </div>
                    </div>
                </div>

                <!-- Card Info Payheads -->
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">Payheads</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                  <tr>
                                    <th>Nama Payhead</th>
                                    <th>Jenis</th>
                                    <th>Nominal</th>
                                    <th>Status</th>
                                    <th>Keterangan</th>
                                    <th>Dokumen</th>
                                    <th class="text-right">Aksi</th>
                                  </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($payheads)): ?>
                                    <tr><td colspan="7" class="text-center"><em>Belum ada payhead</em></td></tr>
                                <?php else: ?>
                                    <?php foreach($payheads as $ph): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($ph['nama_payhead']); ?></td>
                                            <td><?= htmlspecialchars(ucfirst($ph['jenis'])); ?></td>
                                            <td><?= number_format($ph['amount'],2,',','.'); ?></td>
                                            <td><?= htmlspecialchars($ph['status']); ?></td>
                                            <td>
                                               <?php if (!empty($ph['remarks'])): ?>
                                                   <small><em><?= htmlspecialchars($ph['remarks']); ?></em></small>
                                               <?php else: ?>
                                                   <small><em>-</em></small>
                                               <?php endif; ?>
                                            </td>
                                            <td>
                                               <?php if (!empty($ph['support_doc_path'])): ?>
                                                   <a href="<?= htmlspecialchars($ph['support_doc_path']); ?>" target="_blank">Lihat Dokumen</a>
                                               <?php else: ?>
                                                   <em>-</em>
                                               <?php endif; ?>
                                            </td>
                                            <td class="text-right py-0 align-middle">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-info btnEditPayhead"
                                                            data-idpayhead="<?= htmlspecialchars($ph['id_payhead']); ?>"
                                                            data-amount="<?= htmlspecialchars($ph['amount']); ?>"
                                                            data-jenis="<?= htmlspecialchars($ph['jenis']); ?>">
                                                        <i class="fas fa-pen"></i>
                                                    </button>
                                                    <button class="btn btn-danger btnDeletePayhead"
                                                            data-idpayhead="<?= htmlspecialchars($ph['id_payhead']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- /.card-info -->
            </div>
        </div>
        <!-- Tombol Bawah -->
        <div class="row">
            <div class="col-12">
                <a href="/payroll_absensi_v2/payroll/keuangan/employees.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancel
                </a>

                <!-- Form Insert Payroll -->
                <form action="" method="POST" style="display:inline;">
                    <input type="hidden" name="id_anggota" value="<?= htmlspecialchars($id_anggota); ?>">
                    <input type="hidden" name="bulan_int"  value="<?= htmlspecialchars($bulan); ?>">
                    <input type="hidden" name="tahun"      value="<?= htmlspecialchars($tahun); ?>">
                    <input type="hidden" name="id_rekap_absensi" value="<?= htmlspecialchars($id_rekap_absensi); ?>">

                    <?php foreach($payheads as $ph): ?>
                        <input type="hidden" name="payheads_ids[]"    value="<?= htmlspecialchars($ph['id_payhead']); ?>">
                        <input type="hidden" name="payheads_jenis[]"  value="<?= htmlspecialchars($ph['jenis']); ?>">
                        <input type="hidden" name="payheads_amount[]" value="<?= htmlspecialchars($ph['amount']); ?>">
                    <?php endforeach; ?>

                    <input type="hidden" name="no_rekening"        id="fieldNoRek"           value="">
                    <input type="hidden" name="gaji_pokok"         id="fieldGajiPokok"       value="">
                    <input type="hidden" name="total_earnings"     id="fieldTotalEarnings"   value="">
                    <input type="hidden" name="total_deductions"   id="fieldTotalDeductions" value="">
                    <input type="hidden" name="inputDescription"   id="fieldDescription"     value="">
                    <input type="hidden" name="tgl_payroll"        id="fieldTglPayroll"      value="">
                    <input type="hidden" name="csrf_token"         value="<?= htmlspecialchars($csrf_token); ?>">
                    
                    <button type="submit" class="btn btn-success float-right">
                        <i class="fas fa-check"></i> Proses Payroll
                    </button>
                </form>

                <!-- Tombol Tolak Payroll -->
                <button type="button" class="btn btn-warning mr-2 float-right" id="btnRejectPayroll">
                    <i class="fas fa-times"></i> Tolak Payroll
                </button>
            </div>
        </div>
    </div><!-- /.container-fluid -->
    </section>
    <!-- /.content -->
</div>
<!-- /.wrapper -->

<!-- MODAL: Edit Payhead -->
<div class="modal fade" id="modalEditPayhead" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formEditPayhead">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Payhead</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit_idpayhead" name="edit_idpayhead">
                    <div class="form-group">
                        <label>Jenis</label>
                        <input type="text" id="edit_jenis" class="form-control" name="edit_jenis" readonly>
                    </div>
                    <div class="form-group">
                        <label>Nilai (Amount)</label>
                        <input type="text" id="edit_amount" class="form-control currency-input" name="edit_amount" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: Konfirmasi Delete -->
<div class="modal fade" id="modalDeletePayhead" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Hapus Payhead</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Anda yakin ingin menghapus payhead ini?</p>
                <input type="hidden" id="del_idpayhead" name="del_idpayhead">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="btnConfirmDelete">Ya, Hapus</button>
            </div>
        </div>
    </div>
</div>

<!-- JS Dependencies -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/autonumeric@4.6.0/dist/autoNumeric.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function initAutoNumeric() {
    AutoNumeric.multiple('.currency-input', {
        digitGroupSeparator: '.',
        decimalCharacter: ',',
        decimalPlaces: 2,
        unformatOnSubmit: true
    });
}
$(document).ready(function() {
    initAutoNumeric();

    // Recalculate gaji bersih jika input gaji pokok berubah
    const inputGajiPokok = $('#inputGajiPokok');
    const inputTotalEarnings = $('#inputTotalEarnings');
    const inputTotalDeductions = $('#inputTotalDeductions');
    const inputNetSalary = $('#inputNetSalary');

    function recalcNetSalary() {
        const gp = parseFloat(AutoNumeric.getAutoNumericElement(inputGajiPokok[0]).getNumber()) || 0;
        const te = parseFloat(AutoNumeric.getAutoNumericElement(inputTotalEarnings[0]).getNumber()) || 0;
        const td = parseFloat(AutoNumeric.getAutoNumericElement(inputTotalDeductions[0]).getNumber()) || 0;
        AutoNumeric.getAutoNumericElement(inputNetSalary[0]).set(gp + te - td);
    }
    inputGajiPokok.on('keyup', recalcNetSalary);

    // Set nilai hidden field saat submit "Proses Payroll"
    $('form[action=""]').on('submit', function(e){
        $('#fieldNoRek').val($('#inputNoRek').val());
        $('#fieldGajiPokok').val(AutoNumeric.getAutoNumericElement(inputGajiPokok[0]).getNumber());
        $('#fieldTotalEarnings').val(AutoNumeric.getAutoNumericElement(inputTotalEarnings[0]).getNumber());
        $('#fieldTotalDeductions').val(AutoNumeric.getAutoNumericElement(inputTotalDeductions[0]).getNumber());
        $('#fieldDescription').val($('#inputDescription').val());
        $('#fieldTglPayroll').val($('#inputTanggalPayroll').val());
    });

    // Edit Payhead
    $('.btnEditPayhead').on('click', function() {
        const payheadId = $(this).data('idpayhead');
        const amount = $(this).data('amount');
        const jenis = $(this).data('jenis');
        $('#edit_idpayhead').val(payheadId);
        $('#edit_amount').val(amount);
        $('#edit_jenis').val(jenis.charAt(0).toUpperCase() + jenis.slice(1));
        $('#modalEditPayhead').modal('show');
    });
    $('#formEditPayhead').on('submit', function(e) {
        e.preventDefault();
        const idPayhead = $('#edit_idpayhead').val();
        const newAmount = parseFloat($('#edit_amount').val()) || 0;
        $.ajax({
            url: '?ajax=1',
            type: 'POST',
            dataType: 'json',
            data: {
                case: 'UpdatePayhead',
                id_anggota: '<?= htmlspecialchars($id_anggota); ?>',
                id_payhead: idPayhead,
                amount: newAmount,
                csrf_token: CSRF_TOKEN
            },
            success: function(resp) {
                if (resp.code === 0) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil update payhead!',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(function() {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: resp.result
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Terjadi error saat update payhead'
                });
            }
        });
    });

    // Tolak Payroll (status => revisi)
    $('#btnRejectPayroll').on('click', function(){
        Swal.fire({
            title: 'Anda yakin menolak payroll ini?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Tolak',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '?ajax=1',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        case: 'RejectPayroll',
                        id_anggota: '<?= htmlspecialchars($id_anggota); ?>',
                        bulan: '<?= htmlspecialchars($bulanVal); ?>',
                        tahun: '<?= htmlspecialchars($tahunVal); ?>',
                        csrf_token: CSRF_TOKEN
                    },
                    success: function(resp) {
                        if (resp.code === 0) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Payroll ditolak. Status diubah menjadi revisi.',
                                showConfirmButton: false,
                                timer: 1500
                            }).then(function() {
                                window.location.href = '/payroll_absensi_v2/payroll/keuangan/list_payroll.php';
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: resp.result
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Terjadi kesalahan saat menolak payroll.'
                        });
                    }
                });
            }
        });
    });

    // Delete Payhead
    $('.btnDeletePayhead').on('click', function() {
        const payheadId = $(this).data('idpayhead');
        $('#del_idpayhead').val(payheadId);
        $('#modalDeletePayhead').modal('show');
    });
    $('#btnConfirmDelete').on('click', function() {
        const pid = $('#del_idpayhead').val();
        $.ajax({
            url: '?ajax=1',
            type: 'POST',
            dataType: 'json',
            data: {
                case: 'DeletePayhead',
                id_anggota: '<?= htmlspecialchars($id_anggota); ?>',
                id_payhead: pid,
                csrf_token: CSRF_TOKEN
            },
            success: function(resp) {
                if (resp.code === 0) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Payhead berhasil dihapus!',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(function() {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: resp.result
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Terjadi error saat hapus payhead'
                });
            }
        });
    });
});
</script>

</body>
</html>
<?php
// Akhir tampilan review payroll
}
?>
