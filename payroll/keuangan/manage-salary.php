<?php
session_start();

// 1. Cek Role
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['keuangan', 'superadmin'])) {
    header("Location: /payroll_absensi_v2/login.php");
    exit();
}

// 2. Include Koneksi dan Helpers
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../helpers.php';

// 3. Inisialisasi Error Handling dan CSRF Token
init_error_handling();
generate_csrf_token();

/**
 * Fungsi konversi nama bulan (English) -> integer (1–12).
 */
function monthNameToInt($monthName) {
    $lower = strtolower($monthName);
    $map = [
        'january' => 1, 'february' => 2, 'march' => 3,
        'april' => 4,  'may' => 5,      'june' => 6,
        'july' => 7,   'august' => 8,   'september' => 9,
        'october' => 10,'november' => 11,'december' => 12
    ];
    return isset($map[$lower]) ? $map[$lower] : 0;
}

// ==================================================================
// BAGIAN AJAX: Update / Delete Payhead
// ==================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    verify_csrf_token($csrf_token);
    $case = isset($_POST['case']) ? bersihkan_input($_POST['case']) : '';
    switch($case) {
        case 'UpdatePayhead':
            $id_anggota  = isset($_POST['id_anggota']) ? intval($_POST['id_anggota']) : 0;
            $id_payhead  = isset($_POST['id_payhead']) ? intval($_POST['id_payhead']) : 0;
            $amount      = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
            if ($id_anggota <= 0 || $id_payhead <= 0) {
                send_response(1, 'Parameter invalid.');
            }
            $stmt = $conn->prepare("
                UPDATE employee_payheads
                SET amount = ?
                WHERE id_anggota = ? AND id_payhead = ?
            ");
            if (!$stmt) {
                send_response(1, 'Prepare failed: ' . $conn->error);
            }
            $stmt->bind_param("dii", $amount, $id_anggota, $id_payhead);
            if ($stmt->execute()) {
                $stmt->close();
                $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
                $details = "Memperbarui Payhead ID $id_payhead untuk Karyawan ID $id_anggota dengan jumlah Rp " . number_format($amount, 2, ',', '.');
                if(!add_audit_log($conn, $user_id, 'UpdatePayhead', $details)){
                    send_response(1, 'Payhead berhasil diupdate, tetapi gagal mencatat audit log.');
                }
                send_response(0, 'Payhead berhasil diupdate.');
            } else {
                $stmt->close();
                send_response(1, 'Gagal update payhead: ' . $stmt->error);
            }
            break;

        case 'DeletePayhead':
            $id_anggota  = isset($_POST['id_anggota']) ? intval($_POST['id_anggota']) : 0;
            $id_payhead  = isset($_POST['id_payhead']) ? intval($_POST['id_payhead']) : 0;
            if ($id_anggota <= 0 || $id_payhead <= 0) {
                send_response(1, 'Parameter invalid.');
            }
            $stmtDel = $conn->prepare("
                DELETE FROM employee_payheads
                WHERE id_anggota=? AND id_payhead=?
            ");
            if (!$stmtDel) {
                send_response(1, 'Prepare failed: ' . $conn->error);
            }
            $stmtDel->bind_param("ii", $id_anggota, $id_payhead);
            if ($stmtDel->execute()) {
                $stmtDel->close();
                $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
                $details = "Menghapus Payhead ID $id_payhead untuk Karyawan ID $id_anggota.";
                if(!add_audit_log($conn, $user_id, 'DeletePayhead', $details)){
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    verify_csrf_token($csrf_token);

    $id_anggota       = isset($_POST['id_anggota']) ? intval($_POST['id_anggota']) : 0;
    $bulan_int        = isset($_POST['bulan_int']) ? intval($_POST['bulan_int']) : 0;
    $tahun            = isset($_POST['tahun']) ? intval($_POST['tahun']) : 0;
    $id_rekap_absensi = isset($_POST['id_rekap_absensi']) ? intval($_POST['id_rekap_absensi']) : 0;
    $no_rekening      = isset($_POST['no_rekening']) ? bersihkan_input($_POST['no_rekening']) : '';
    $gaji_pokok       = isset($_POST['gaji_pokok']) ? floatval($_POST['gaji_pokok']) : 0;
    $total_earnings   = isset($_POST['total_earnings']) ? floatval($_POST['total_earnings']) : 0;
    $total_deductions = isset($_POST['total_deductions']) ? floatval($_POST['total_deductions']) : 0;
    $payheads_ids     = isset($_POST['payheads_ids']) ? $_POST['payheads_ids'] : [];
    $payheads_jenis   = isset($_POST['payheads_jenis']) ? $_POST['payheads_jenis'] : [];
    $payheads_amount  = isset($_POST['payheads_amount']) ? $_POST['payheads_amount'] : [];
    $catatan          = isset($_POST['inputDescription']) ? bersihkan_input($_POST['inputDescription']) : '';
    $tgl_payroll      = isset($_POST['tgl_payroll']) ? bersihkan_input($_POST['tgl_payroll']) : date('Y-m-d H:i:s');

    if ($id_anggota <= 0 || $bulan_int <= 0 || $tahun <= 0 || $id_rekap_absensi <= 0) {
        die("Parameter tidak valid.");
    }

    $conn->begin_transaction();
    try {
        $stmtPayroll = $conn->prepare("
            INSERT INTO payroll (
                id_anggota, bulan, tahun, tgl_payroll,
                gaji_pokok, total_pendapatan, total_potongan, gaji_bersih,
                no_rekening, catatan, id_rekap_absensi
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmtPayroll) {
            throw new Exception("Prepare payroll gagal: " . $conn->error);
        }
        $gaji_bersih = $gaji_pokok + $total_earnings - $total_deductions;
        $stmtPayroll->bind_param(
            "iiisddddssi",
            $id_anggota,
            $bulan_int,
            $tahun,
            $tgl_payroll,
            $gaji_pokok,
            $total_earnings,
            $total_deductions,
            $gaji_bersih,
            $no_rekening,
            $catatan,
            $id_rekap_absensi
        );
        if (!$stmtPayroll->execute()) {
            throw new Exception("Gagal insert payroll: " . $stmtPayroll->error);
        }
        $id_payroll = $stmtPayroll->insert_id;
        $stmtPayroll->close();

        $stmtDetail = $conn->prepare("
            INSERT INTO payroll_detail (id_payroll, id_payhead, jenis, amount)
            VALUES (?, ?, ?, ?)
        ");
        if (!$stmtDetail) {
            throw new Exception("Prepare payroll_detail gagal: " . $conn->error);
        }
        foreach ($payheads_ids as $index => $id_payhead) {
            $jenis  = isset($payheads_jenis[$index]) ? bersihkan_input($payheads_jenis[$index]) : '';
            $amount = isset($payheads_amount[$index]) ? floatval($payheads_amount[$index]) : 0;
            $stmtDetail->bind_param("iisd", $id_payroll, $id_payhead, $jenis, $amount);
            if (!$stmtDetail->execute()) {
                throw new Exception("Gagal insert payroll_detail: " . $stmtDetail->error);
            }
        }
        $stmtDetail->close();
        $conn->commit();

        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $details = "Membuat Payroll ID $id_payroll untuk Karyawan ID $id_anggota pada bulan $bulan_int tahun $tahun dengan total pendapatan Rp " . number_format($total_earnings, 2, ',', '.') . ", total potongan Rp " . number_format($total_deductions, 2, ',', '.') . ", dan gaji bersih Rp " . number_format($gaji_bersih, 2, ',', '.');
        if(!add_audit_log($conn, $user_id, 'InsertPayroll', $details)){
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
    $bulanName  = isset($_GET['bulan']) ? bersihkan_input($_GET['bulan']) : '';
    $tahunStr   = isset($_GET['tahun']) ? bersihkan_input($_GET['tahun']) : '';
    $tglPayrollParam = isset($_GET['tgl']) ? bersihkan_input($_GET['tgl']) : date('Y-m-d H:i:s');

    if ($id_anggota <= 0 || empty($bulanName) || empty($tahunStr)) {
        die("Parameter tidak valid.");
    }
    $bulan = monthNameToInt($bulanName);
    $tahun = intval($tahunStr);
    if ($bulan <=0 || $tahun<=0) {
        die("Parameter bulan/tahun tidak valid.");
    }

    // Cek apakah payroll sudah ada
    $stmtCheck = $conn->prepare("
        SELECT id FROM payroll
        WHERE id_anggota=? AND bulan=? AND tahun=?
        LIMIT 1
    ");
    if (!$stmtCheck) {
        die("Prepare gagal: " . $conn->error);
    }
    $stmtCheck->bind_param("iii", $id_anggota, $bulan, $tahun);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result();
    if ($resCheck->num_rows > 0) {
        $payroll = $resCheck->fetch_assoc();
        $stmtCheck->close();
        $id_payroll = $payroll['id'];
        header("Location: payroll-details.php?id_payroll=$id_payroll");
        exit();
    }
    $stmtCheck->close();

    // Pastikan rekap_absensi ada
    $stmtRekap = $conn->prepare("
        SELECT id FROM rekap_absensi
        WHERE id_anggota=? AND bulan=? AND tahun=?
        LIMIT 1
    ");
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
        $stmtIns = $conn->prepare("
            INSERT INTO rekap_absensi
            (id_anggota, bulan, tahun, total_hadir, total_izin, total_cuti, total_tanpa_keterangan, total_sakit)
            VALUES (?, ?, ?, 0,0,0,0,0)
        ");
        if (!$stmtIns) {
            die("Prepare insert rekap gagal: " . $conn->error);
        }
        $stmtIns->bind_param("iii", $id_anggota, $bulan, $tahun);
        if (!$stmtIns->execute()) {
            die("Gagal insert rekap_absensi: " . $stmtIns->error);
        }
        $id_rekap_absensi = $stmtIns->insert_id;
        $stmtIns->close();
    }
    $stmtRekap->close();

    // Ambil payheads
    $stmtPH = $conn->prepare("
        SELECT ep.id_payhead, ph.nama_payhead, ph.jenis, ep.amount
        FROM employee_payheads ep
        JOIN payheads ph ON ep.id_payhead=ph.id
        WHERE ep.id_anggota=?
    ");
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

    // --- MODIFIKASI UNTUK LEVEL INDEKS ---
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
    // Hitung gaji pokok sebagai: gaji_pokok dari DB + base salary (jika ada)
    $gaji_pokok_employee = (float)($karyawan['gaji_pokok'] ?? 0);
    $gaji_pokok = $gaji_pokok_employee + $salary_index_amount;
    // ---------------------------------------

    // Hitung total earnings & deductions dari payheads
    $total_earnings = 0;
    $total_deductions = 0;
    foreach($payheads as $ph) {
        if ($ph['jenis'] === 'earnings') {
            $total_earnings += (float)($ph['amount'] ?? 0);
        } else {
            $total_deductions += (float)($ph['amount'] ?? 0);
        }
    }
    $gaji_kotor  = $gaji_pokok + $total_earnings;
    $gaji_bersih = $gaji_kotor - $total_deductions;
    $masa_kerja = ((int)($karyawan['masa_kerja_tahun'] ?? 0)) . " Tahun, " . ((int)($karyawan['masa_kerja_bulan'] ?? 0)) . " Bulan";
    $namaKaryawan= $karyawan['nama'] ?? '';
    $noRek       = $karyawan['no_rekening'] ?? '';
    $catatan = "";

    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $details = "Mengakses Review Payroll untuk Karyawan ID $id_anggota pada bulan $bulan tahun $tahun.";
    if(!add_audit_log($conn, $user_id, 'ViewPayroll', $details)){
        error_log("Gagal mencatat audit log untuk ViewPayroll ID $id_anggota.");
    }
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Review Payroll - <?= htmlspecialchars($namaKaryawan); ?></title>
  <!-- Sertakan AdminLTE, FontAwesome, dan AutoNumeric -->
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
            <!-- Kiri -->
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
                        <!-- Nama Karyawan -->
                        <div class="form-group">
                            <label for="inputName">Nama Karyawan</label>
                            <input type="text" id="inputName" class="form-control" value="<?= htmlspecialchars($namaKaryawan); ?>" readonly>
                        </div>
                        <!-- Catatan / Deskripsi -->
                        <div class="form-group">
                            <label for="inputDescription">Catatan / Deskripsi Payroll</label>
                            <textarea id="inputDescription" class="form-control" rows="3" placeholder="Tambah catatan jika perlu..."></textarea>
                        </div>
                        <!-- Periode Gaji -->
                        <div class="form-group">
                            <label for="inputStatus">Periode Gaji</label>
                            <input type="text" id="inputStatus" class="form-control" value="<?= htmlspecialchars($bulanName . ' ' . $tahun); ?>" readonly>
                        </div>
                        <!-- Masa Kerja -->
                        <div class="form-group">
                            <label for="inputMasaKerja">Masa Kerja</label>
                            <input type="text" id="inputMasaKerja" class="form-control" value="<?= htmlspecialchars($masa_kerja); ?>" readonly>
                        </div>
                        <!-- Level Indeks (menampilkan nama level dan nominal salary indeks) -->
                        <div class="form-group">
                            <label for="inputLevelIndeks">Level Indeks</label>
                            <input type="text" id="inputLevelIndeks" class="form-control" 
                                   value="<?= htmlspecialchars($salary_index_level ? $salary_index_level . ' (Rp ' . number_format($salary_index_amount, 2, ',', '.') . ')' : 'Belum ada'); ?>" 
                                   readonly>
                        </div>
                        <!-- No Rekening -->
                        <div class="form-group">
                            <label for="inputNoRek">No. Rekening</label>
                            <input type="text" id="inputNoRek" class="form-control" value="<?= htmlspecialchars($noRek); ?>">
                        </div>
                        <!-- Tanggal Payroll -->
                        <div class="form-group">
                            <label for="inputTanggalPayroll">Tanggal Payroll</label>
                            <input type="datetime-local" id="inputTanggalPayroll" class="form-control" value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($tglPayrollParam))); ?>" required>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Kanan -->
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
                        <!-- Gaji Pokok -->
                        <div class="form-group">
                            <label for="inputGajiPokok">Gaji Pokok</label>
                            <!-- Gunakan input text dengan class .currency-input untuk AutoNumeric -->
                            <input type="text" id="inputGajiPokok" class="form-control currency-input" value="<?= htmlspecialchars($gaji_pokok); ?>">
                        </div>
                        <!-- Total Earnings -->
                        <div class="form-group">
                            <label for="inputTotalEarnings">Total Pendapatan (Payheads)</label>
                            <input type="text" id="inputTotalEarnings" class="form-control currency-input" value="<?= htmlspecialchars($total_earnings); ?>" readonly>
                        </div>
                        <!-- Total Deductions -->
                        <div class="form-group">
                            <label for="inputTotalDeductions">Total Potongan</label>
                            <input type="text" id="inputTotalDeductions" class="form-control currency-input" value="<?= htmlspecialchars($total_deductions); ?>" readonly>
                        </div>
                        <!-- Estimasi Gaji Bersih -->
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
                                        <th class="text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($payheads)): ?>
                                        <tr><td colspan="4" class="text-center"><em>Belum ada payhead</em></td></tr>
                                    <?php else: ?>
                                        <?php foreach($payheads as $ph): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($ph['nama_payhead']); ?></td>
                                                <td><?= htmlspecialchars(ucfirst($ph['jenis'])); ?></td>
                                                <td><?= number_format($ph['amount'],2,',','.'); ?></td>
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
            <!-- /Kanan -->
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

                    <input type="hidden" name="no_rekening"        id="fieldNoRek"          value="">
                    <input type="hidden" name="gaji_pokok"         id="fieldGajiPokok"      value="">
                    <input type="hidden" name="total_earnings"     id="fieldTotalEarnings"  value="">
                    <input type="hidden" name="total_deductions"   id="fieldTotalDeductions"value="">
                    <input type="hidden" name="inputDescription"   id="fieldDescription"    value="">
                    <input type="hidden" name="tgl_payroll"        id="fieldTglPayroll"     value="">
                    <input type="hidden" name="csrf_token"         value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <button type="submit" class="btn btn-success float-right">
                        <i class="fas fa-check"></i> Proses Payroll
                    </button>
                </form>
            </div>
        </div>
    </div><!-- /.container-fluid -->
    </section>
    <!-- /.content -->
</div>
<!-- /.wrapper -->

<!-- Modal: Edit Payhead -->
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
                        <!-- Gunakan input text dengan AutoNumeric jika diinginkan -->
                        <input type="text" id="edit_amount" class="form-control currency-input" name="edit_amount" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Konfirmasi Delete -->
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

<!-- jQuery, Bootstrap, AdminLTE, dan AutoNumeric -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- Sertakan plugin AutoNumeric -->
<script src="https://cdn.jsdelivr.net/npm/autonumeric@4.6.0/dist/autoNumeric.min.js"></script>
<script>
    // Inisialisasi AutoNumeric untuk input dengan class .currency-input
    function initAutoNumeric() {
        AutoNumeric.multiple('.currency-input', {
            digitGroupSeparator: '.',
            decimalCharacter: ',',
            decimalPlaces: 2,
            unformatOnSubmit: true
        });
    }
    // Panggil saat dokumen siap
    $(document).ready(function() {
        initAutoNumeric();

        // Re-calc net salary bila input gaji pokok, total earnings, atau total deductions berubah
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
        inputTotalEarnings.on('keyup', recalcNetSalary);
        inputTotalDeductions.on('keyup', recalcNetSalary);

        // Set nilai field tersembunyi saat submit payroll
        $('form[action=""]').on('submit', function(e){
            $('#fieldNoRek').val($('#inputNoRek').val());
            $('#fieldGajiPokok').val(AutoNumeric.getAutoNumericElement(inputGajiPokok[0]).getNumber());
            $('#fieldTotalEarnings').val(AutoNumeric.getAutoNumericElement(inputTotalEarnings[0]).getNumber());
            $('#fieldTotalDeductions').val(AutoNumeric.getAutoNumericElement(inputTotalDeductions[0]).getNumber());
            $('#fieldDescription').val($('#inputDescription').val());
            $('#fieldTglPayroll').val($('#inputTanggalPayroll').val());
        });

        // EDIT PAYHEAD
        let currentIdPayhead = 0;
        $('.btnEditPayhead').on('click', function() {
            currentIdPayhead = $(this).data('idpayhead');
            const amount = $(this).data('amount');
            const jenis = $(this).data('jenis');
            $('#edit_idpayhead').val(currentIdPayhead);
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
                        alert('Berhasil update payhead!');
                        location.reload();
                    } else {
                        alert('Gagal: ' + resp.result);
                    }
                },
                error: function() {
                    alert('Terjadi error saat update payhead');
                }
            });
        });
        // DELETE PAYHEAD
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
                        alert('Payhead berhasil dihapus!');
                        location.reload();
                    } else {
                        alert('Gagal: ' + resp.result);
                    }
                },
                error: function() {
                    alert('Terjadi error saat hapus payhead');
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
