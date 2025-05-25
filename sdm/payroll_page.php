<?php
// payroll_page.php

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../koneksi.php';
start_session_safe();
init_error_handling();
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

// Pastikan user berhak akses
authorize(['M:SDM', 'M:Superadmin'], '/payroll_absensi_v2/login.php');

// Get parameter
$empcode = isset($_GET['empcode']) ? intval($_GET['empcode']) : 0;
$selectedMonth = isset($_GET['filterMonth']) ? intval($_GET['filterMonth']) : date('n');
$selectedYear  = isset($_GET['filterYear'])  ? intval($_GET['filterYear'])  : date('Y');

// INIT prefill variables
$payrollDraft = null;
$payheadDraft = [];
$kgData = null;

if ($empcode > 0) {
    // 1. Cek payroll draft (status draft)
    $stmt = $conn->prepare("SELECT * FROM payroll WHERE id_anggota = ? AND bulan = ? AND tahun = ? AND status = 'draft' LIMIT 1");
    $stmt->bind_param('iii', $empcode, $selectedMonth, $selectedYear);
    $stmt->execute();
    $payrollDraft = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 2. Ambil komponen gaji DRAFT dari employee_payheads (bukan payroll_detail, karena simpan temporary)
    $stmt2 = $conn->prepare(
        "SELECT ep.*, p.nama_payhead, p.jenis 
         FROM employee_payheads ep 
         JOIN payheads p ON ep.id_payhead = p.id
         WHERE ep.id_anggota = ? AND ep.status = 'draft'"
    );
    $stmt2->bind_param('i', $empcode);
    $stmt2->execute();
    $phResult = $stmt2->get_result();
    while ($row = $phResult->fetch_assoc()) {
        $payheadDraft[] = $row;
    }
    $stmt2->close();

    // 3. Cek kenaikan gaji tahunan aktif (hanya yang aktif & masa berlaku masih berlaku)
    $now = date('Y-m-d');
    $stmtKg = $conn->prepare("SELECT * FROM kenaikan_gaji_tahunan 
        WHERE id_anggota = ? AND status = 'aktif' 
        AND tanggal_mulai <= ? AND tanggal_berakhir >= ?
        ORDER BY tanggal_mulai DESC LIMIT 1");
    $stmtKg->bind_param('iss', $empcode, $now, $now);
    $stmtKg->execute();
    $kgData = $stmtKg->get_result()->fetch_assoc();
    $stmtKg->close();
}

// 4. Ambil data anggota
$anggota = [];
if ($empcode > 0) {
    $stmt3 = $conn->prepare("SELECT * FROM anggota_sekolah WHERE id = ?");
    $stmt3->bind_param('i', $empcode);
    $stmt3->execute();
    $anggota = $stmt3->get_result()->fetch_assoc();
    $stmt3->close();
}

// 5. Jika tidak ada payroll draft, set default agar tidak error pada prefill JS
if (!$payrollDraft) $payrollDraft = [];
if (!$payheadDraft) $payheadDraft = [];
if (!$kgData) $kgData = [];

$tgl_payroll_value = '';
if (isset($payrollDraft['tgl_payroll']) && $payrollDraft['tgl_payroll']) {
    $ts = strtotime($payrollDraft['tgl_payroll']);
    $tgl_payroll_value = $ts ? date('Y-m-d\TH:i', $ts) : date('Y-m-d\TH:i');
} else {
    $tgl_payroll_value = date('Y-m-d\TH:i');
}

// Misal menerima dari AJAX/POST
$nominal_kenaikan = isset($_POST['nominal_kenaikan']) ? $_POST['nominal_kenaikan'] : 0;
$nominal_kenaikan = str_replace('.', '', $nominal_kenaikan);
$nominal_kenaikan = str_replace(',', '.', $nominal_kenaikan);
$nominal_kenaikan = floatval($nominal_kenaikan); // sekarang benar: 125000

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Payroll Anggota - Pengaturan Payheads</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Sertakan CSS Bootstrap 5 & SB Admin 2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        /* Custom styling */
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }

        .file-label {
            cursor: pointer;
        }

        /* ----- Umum & Card Layout ----- */
        .card {
            border-radius: 6px;
        }

        .card-header {
            border-top-left-radius: 6px;
            border-top-right-radius: 6px;
        }

        .card-body {
            padding: 1rem;
        }

        .card-footer {
            border-bottom-left-radius: 6px;
            border-bottom-right-radius: 6px;
        }

        /* ----- Table Payheads ----- */
        #selected_payamount_table {
            font-size: 14px;
        }

        #selected_payamount_table thead th {
            text-align: center;
            vertical-align: middle;
            background-color: #f8f9fa;
            /* Warna latar kepala tabel */
        }

        #selected_payamount_table tbody td {
            vertical-align: middle;
        }

        /* Buat kolom 'Nominal' lebih sempit */
        #selected_payamount_table th:nth-child(3),
        #selected_payamount_table td:nth-child(3) {
            width: 100px;
            /* atur sesuai kebutuhan, misalnya 80-120px */
            min-width: 80px;
            text-align: center;
        }

        /* Kolom 'Upload Dokumen' bisa diperkecil juga */
        #selected_payamount_table th:nth-child(6),
        #selected_payamount_table td:nth-child(6) {
            width: 130px;
        }

        /* Kolom 'Keterangan' agar cukup luas */
        #selected_payamount_table th:nth-child(4),
        #selected_payamount_table td:nth-child(4) {
            width: 25%;
            word-wrap: break-word;
        }

        /* ----- Bagian Check / Kenaikan Gaji Tahunan ----- */
        .form-check-input {
            cursor: pointer;
        }

        #kenaikanGajiTahunanFields {
            margin-top: 1rem;
            border: 1px dashed #ccc;
            padding: 10px;
            border-radius: 4px;
            background-color: #fefefe;
        }

        /* Agar label 'Aktifkan Kenaikan Gaji Tahunan' terlihat lebih jelas */
        #chkKenaikanGajiTahunan+.form-check-label {
            font-weight: 500;
            margin-left: 8px;
            cursor: pointer;
        }

        /* ----- Kolom-kolom ringkas ----- */
        .form-control {
            font-size: 14px;
            padding: 6px 8px;
        }

        /* Ukuran input Nominal Kenaikan lebih ringkas */
        #inputNominalKenaikan {
            max-width: 120px;
            /* Anda bisa sesuaikan */
        }

        /* ----- Kartu Rekap Absensi ----- */
        .card-body.text-center.p-2 p {
            font-size: 16px;
            margin: 0;
            font-weight: bold;
        }

        /* ===== Page Title Styling ===== */
        .page-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 2.5rem;
            color: #0d47a1;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 3px solid #1976d2;
            padding-bottom: 0.3rem;
            margin-bottom: 1.5rem;
            animation: fadeInSlide 0.5s ease-in-out both;
        }

        .page-title i {
            color: #1976d2;
            font-size: 2.8rem;
        }
    </style>
    <script>
        const CSRF_TOKEN = '<?= htmlspecialchars($csrf_token); ?>';
        // Prefill data dari PHP (sudah di-escape json)
        const payrollDraft = <?= json_encode($payrollDraft); ?>;
        const payheadDraft = <?= json_encode($payheadDraft); ?>;
        const kgData = <?= json_encode($kgData); ?>;
        const anggota = <?= json_encode($anggota); ?>;
    </script>

</head>

<body>
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <div class="container-fluid">
                <h1 class="page-title">
                    <i class="bi bi-cash-stack"></i> Halaman Payroll
                </h1>

                <!-- Tombol kembali ke daftar anggota -->
                <a href="employees.php" class="btn btn-secondary mb-3">
                    <i class="bi bi-arrow-left"></i> Kembali ke Daftar Anggota
                </a>
                <!-- Form Pengaturan Payheads -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h5 class="m-0 fw-bold">Tetapkan / Perbarui Payroll Anggota</h5>
                    </div>
                    <div class="card-body">
                        <form id="assign-payhead-form" enctype="multipart/form-data" method="POST" action="proses_simpan_payroll.php">
                            <div class="container-fluid">
                                <!-- Row 1: Informasi Anggota & Payroll -->
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <div class="card border-primary shadow-sm">
                                            <div class="card-header">
                                                <i class="bi bi-person-badge me-1"></i> <strong>Informasi Anggota & Payroll</strong>
                                            </div>
                                            <div class="card-body">
                                                <div class="row g-3 mb-3">
                                                    <div class="col-md-4">
                                                        <label>Nama Anggota</label>
                                                        <input type="text" class="form-control" id="fieldAnggota" value="<?= htmlspecialchars($anggota['nama'] ?? '') ?>" readonly>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label>Role</label>
                                                        <input type="text" class="form-control" id="fieldRole" value="<?= htmlspecialchars($anggota['role'] ?? '') ?>" readonly>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label>Job Title</label>
                                                        <input type="text" class="form-control" id="fieldJobTitle" value="<?= htmlspecialchars($anggota['job_title'] ?? '') ?>" readonly>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label>Periode</label>
                                                    <input type="text" class="form-control" id="fieldPeriode" value="<?= $selectedMonth . '/' . $selectedYear ?>" readonly>
                                                </div>
                                                <div class="mb-3">
                                                    <label>Masa Kerja</label>
                                                    <input type="text" class="form-control" id="fieldMasaKerja" value="<?= htmlspecialchars($anggota['masa_kerja'] ?? '') ?>" readonly>
                                                </div>
                                                <div class="mb-3">
                                                    <label>No. Rekening</label>
                                                    <input type="text" class="form-control" id="inputNoRek" name="no_rekening" value="<?= htmlspecialchars($payroll['no_rekening'] ?? $anggota['no_rekening'] ?? '') ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label>Tanggal Payroll</label>
                                                    <input type="datetime-local" class="form-control" id="inputTanggalPayroll" name="tgl_payroll"
    value="<?= htmlspecialchars($tgl_payroll_value) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label>Catatan Payroll</label>
                                                    <textarea class="form-control" id="inputDescription" name="catatan" rows="3" placeholder="Tambahkan catatan..."><?= htmlspecialchars($payroll['catatan'] ?? '') ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card border-warning shadow-sm">
                                            <div class="card-header">
                                                <i class="bi bi-calculator me-1"></i> <strong>Perhitungan Payroll</strong>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <label>Level Indeks</label>
                                                    <input type="text" class="form-control" id="inputIndexLevel" value="<?= htmlspecialchars($anggota['salary_index_level'] ?? '') ?>" readonly>
                                                </div>
                                                <div class="mb-3">
                                                    <label>Nominal Indeks</label>
                                                    <input type="text" class="form-control currency-input" id="inputIndexNominal" value="<?= number_format($anggota['salary_index_base'] ?? 0, 0, ',', '.') ?>" readonly>
                                                </div>
                                                <div class="mb-3">
                                                    <label>Gaji Pokok</label>
                                                    <input type="text" class="form-control currency-input" id="inputGajiPokok" value="<?= number_format($anggota['gaji_pokok'] ?? 0, 0, ',', '.') ?>" readonly>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="text-success">Total Pendapatan</label>
                                                    <input type="text" class="form-control" id="inputTotalEarnings" value="<?= number_format($payroll['total_pendapatan'] ?? 0, 0, ',', '.') ?>" readonly>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="text-danger">Total Potongan</label>
                                                    <input type="text" class="form-control" id="inputTotalDeductions" value="<?= number_format($payroll['total_potongan'] ?? 0, 0, ',', '.') ?>" readonly>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="text-danger">Potongan Tidak Hadir</label>
                                                    <input type="text" class="form-control" id="inputPotonganAbsensi" value="<?= number_format($payroll['potongan_koperasi'] ?? 0, 0, ',', '.') ?>" readonly>
                                                </div>
                                                <div class="mb-3">
                                                    <label>Estimasi Gaji Bersih</label>
                                                    <input type="text" class="form-control" id="inputNetSalary" value="<?= number_format($payroll['gaji_bersih'] ?? 0, 0, ',', '.') ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- End Row 1 -->

                                <!-- Card Kenaikan Gaji Tahunan -->
                                <div class="card border-danger mb-2" style="border-width:1px; font-size:13px;">
                                    <div class="card-header bg-danger text-white py-1 px-2 d-flex align-items-center" style="font-size:13px;">
                                        <i class="bi bi-arrow-up-right-circle me-1"></i>
                                        <span><strong>Kenaikan Gaji Tahunan (1 Tahun)</strong></span>
                                    </div>
                                    <div class="card-body py-2 px-2" style="background-color: #fff8f8;">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="chkKenaikanGajiTahunan" name="chkKenaikanGajiTahunan" value="1"
                                                <?= isset($kgData['status']) && $kgData['status'] === 'aktif' ? 'checked disabled' : '' ?>>
                                            <label class="form-check-label fw-bold ms-1" for="chkKenaikanGajiTahunan" style="font-size:13px;">
                                                Aktifkan Kenaikan Gaji Tahunan
                                            </label>
                                        </div>
                                        <div id="kenaikanGajiTahunanFields" style="display:<?= (isset($kgData['status']) && $kgData['status'] === 'aktif') ? 'block' : 'none' ?>;">
                                            <div class="row g-1">
                                                <div class="col-md-6 mb-2">
                                                    <label class="mb-0" style="font-size:13px;">Nama Kenaikan (misal 2024/2025)</label>
                                                    <input type="text" class="form-control form-control-sm"
                                                        id="inputNamaKenaikan" name="nama_kenaikan"
                                                        value="<?= htmlspecialchars($kgData['nama_kenaikan'] ?? '') ?>"
                                                        placeholder="Kenaikan Gaji 2024/2025"
                                                        <?= (isset($kgData['status']) && $kgData['status'] === 'aktif') ? 'readonly' : '' ?>>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label class="mb-0" style="font-size:13px;">Nominal Kenaikan</label>
                                                    <input type="text" class="form-control form-control-sm currency-input"
                                                        id="inputNominalKenaikan" name="nominal_kenaikan"
                                                        value="<?= number_format($kgData['jumlah'] ?? 0, 0, ',', '.') ?>"
                                                        placeholder="0"
                                                        <?= (isset($kgData['status']) && $kgData['status'] === 'aktif') ? 'readonly' : '' ?>>
                                                </div>
                                            </div>
                                            <?php if (isset($kgData['tanggal_mulai'])): ?>
                                                <div class="mt-1 small text-muted">
                                                    Berlaku: <?= date('d M Y', strtotime($kgData['tanggal_mulai'])) ?> - <?= date('d M Y', strtotime($kgData['tanggal_berakhir'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <!-- End Card Kenaikan Gaji Tahunan -->

                                <!-- Row 2: Rekap Absensi -->
                                <!-- (biarkan seperti semula, tidak perlu diubah) -->

                                <!-- Row 3: Payheads -->
                                <div class="row g-3">
                                    <!-- Payheads Tersedia -->
                                    <div class="col-md-3">
                                        <div class="card border-primary">
                                            <div class="card-header">
                                                <i class="bi bi-clipboard-data me-1"></i><strong>Komponen Gaji Tersedia</strong>
                                            </div>
                                            <div class="card-body" style="max-height: 250px; overflow-y: auto;">
                                                <div class="form-group mb-2">
                                                    <input type="text" id="searchAllPayheads" class="form-control" placeholder="Cari payheads...">
                                                </div>
                                                <div id="all_payheads"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Payheads Terpilih -->
                                    <div class="col-md-9">
                                        <div class="card border-success">
                                            <div class="card-header">
                                                <i class="bi bi-check2-circle me-1"></i><strong>Komponen Gaji Terpilih</strong>
                                            </div>
                                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                                <table class="table table-bordered mb-0" id="selected_payamount_table">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 5%;">No.</th>
                                                            <th style="width: 25%;">Nama Payhead</th>
                                                            <th style="width: 15%;">Nominal</th>
                                                            <th style="width: 30%;">Keterangan</th>
                                                            <th style="width: 5%;">Rapel</th>
                                                            <th style="width: 15%;">Upload Dokumen</th>
                                                            <th style="width: 5%;">Aksi</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (!empty($payrollDetails)): ?>
                                                            <?php foreach ($payrollDetails as $i => $ph): ?>
                                                                <tr>
                                                                    <td><?= $i + 1 ?></td>
                                                                    <td><?= htmlspecialchars($ph['nama_payhead']) ?></td>
                                                                    <td>
                                                                        <input type="text" name="pay_amounts[<?= $ph['id_payhead'] ?>]" class="form-control currency-input" value="<?= number_format($ph['amount'], 0, ',', '.') ?>">
                                                                    </td>
                                                                    <td>
                                                                        <textarea name="remarks[<?= $ph['id_payhead'] ?>]" class="form-control"><?= htmlspecialchars($ph['remarks']) ?></textarea>
                                                                    </td>
                                                                    <td>
                                                                        <input type="checkbox" name="rapel[<?= $ph['id_payhead'] ?>]" class="rapel-checkbox" <?= $ph['is_rapel'] ? 'checked' : '' ?>>
                                                                    </td>
                                                                    <td>
                                                                        <?php if (!empty($ph['support_doc_path'])): ?>
                                                                            <a href="<?= htmlspecialchars($ph['support_doc_path']) ?>" target="_blank" class="btn btn-info btn-sm mb-1">Lihat File</a>
                                                                        <?php endif; ?>
                                                                        <input type="file" name="upload_file[<?= $ph['id_payhead'] ?>]" class="form-control form-control-sm mt-1">
                                                                    </td>
                                                                    <td>
                                                                        <button type="button" class="btn btn-danger btn-sm btnRemoveRow"><i class="bi bi-dash"></i></button>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- End Row 3 -->
                            </div><!-- End container-fluid -->

                            <!-- Hidden Fields -->
                            <input type="hidden" name="case" value="AssignPayheadsToEmployee">
                            <input type="hidden" name="empcode" id="empcode" value="<?= htmlspecialchars($empcode); ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="selectedMonth" id="selectedMonth" value="<?= $selectedMonth; ?>">
                            <input type="hidden" name="selectedYear" id="selectedYear" value="<?= $selectedYear; ?>">
                        </form>
                    </div>
                    <!-- Footer Form -->
                    <div class="card-footer">
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-success" id="btnProcessPayroll">
                                <i class="bi bi-check-circle"></i> Proses Payroll
                            </button>
                            <button type="submit" form="assign-payhead-form" class="btn btn-primary ms-2">
                                <i class="fas fa-check-circle"></i> Simpan Payroll
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            </button>
                            <a href="employees.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-times-circle"></i> Batal
                            </a>
                        </div>
                    </div>
                </div>
            </div><!-- /.container-fluid -->
        </div><!-- /#content -->
        <footer class="sticky-footer bg-white">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span>&copy; <?= date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                </div>
            </div>
        </footer>
    </div><!-- /#content-wrapper -->
</body>


<!-- JS Dependencies -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/autonumeric@4.8.0/dist/autoNumeric.min.js"></script>
<script>
    $(document).ready(function() {

        // Prefill field dari draft jika ada
        if (typeof payrollDraft === 'object' && payrollDraft && Object.keys(payrollDraft).length > 0) {
            $("#inputNoRek").val(payrollDraft.no_rekening || "");
            $("#inputTanggalPayroll").val(payrollDraft.tgl_payroll ? payrollDraft.tgl_payroll.replace(' ', 'T') : "");
            $("#inputDescription").val(payrollDraft.catatan || "");
        }
        // Prefill tabel komponen payroll draft
        if (Array.isArray(payheadDraft) && payheadDraft.length > 0) {
            renderAssignedPayheads(payheadDraft);
        }
        // Prefill kenaikan gaji tahunan (draft/aktif)
        if (kgData && kgData.status === "aktif") {
            $("#chkKenaikanGajiTahunan").prop("checked", true).trigger("change");
            $("#inputNamaKenaikan").val(kgData.nama_kenaikan);
            $("#inputNominalKenaikan").val(Number(kgData.jumlah).toLocaleString('id-ID', {
                minimumFractionDigits: 0
            }));
        }

        // Initialize a SweetAlert2 Toast
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        // Define the showToast function for displaying toast messages
        function showToast(message, icon = 'success') {
            Toast.fire({
                icon: icon,
                title: message
            });
        }
        // Inisialisasi AutoNumeric khusus untuk Nominal Indeks dan Gaji Pokok
        const anIndexNominal = new AutoNumeric('#inputIndexNominal', {
            digitGroupSeparator: '.',
            decimalCharacter: ',',
            decimalPlaces: 0,
            readOnly: true
        });
        const anGajiPokok = new AutoNumeric('#inputGajiPokok', {
            digitGroupSeparator: '.',
            decimalCharacter: ',',
            decimalPlaces: 0,
            readOnly: true
        });
        // Inisialisasi AutoNumeric untuk input lain yang menggunakan class .currency-input
        if ($('.currency-input:not(#inputIndexNominal):not(#inputGajiPokok)').length > 0) {
            new AutoNumeric('.currency-input:not(#inputIndexNominal):not(#inputGajiPokok)', {
                digitGroupSeparator: '.',
                decimalCharacter: ',',
                decimalPlaces: 0,
                unformatOnSubmit: true
            });
        }

        // --- Variabel Global ---
        let empcode = <?= json_encode($empcode); ?>;
        let selectedMonth = <?= json_encode($selectedMonth); ?>;
        let selectedYear = <?= json_encode($selectedYear); ?>;

        // 1) Jika empcode > 0, load detail anggota
        if (empcode > 0) {
            $.ajax({
                url: 'employees.php?ajax=1',
                type: 'POST',
                dataType: 'json',
                data: {
                    case: 'ViewEmployeeDetail',
                    id: empcode,
                    csrf_token: CSRF_TOKEN,
                    selectedMonth: localStorage.getItem("selectedMonthNumber") || selectedMonth,
                    selectedYear: localStorage.getItem("selectedYearPayroll") || selectedYear,
                    includeRapel: 1
                },
                success: function(resp) {
                    if (resp.code === 0) {
                        let e = resp.result;
                        $('#fieldAnggota').val(e.nama);
                        $('#fieldRole').val(e.role);
                        $('#fieldJobTitle').val(e.job_title);
                        var selMonth = localStorage.getItem('selectedMonthPayroll') || '';
                        var selYear = localStorage.getItem('selectedYearPayroll') || '';
                        $('#fieldPeriode').val(selMonth + " " + selYear);
                        $('#fieldMasaKerja').val(e.masa_kerja);
                        $('#inputIndexLevel').val(e.salary_index_level);
                        $('#inputNoRek').val(e.no_rekening);
                        // Set nilai menggunakan AutoNumeric
                        anGajiPokok.set(e.gaji_pokok_val);
                        anIndexNominal.set(e.salary_index_base);
                        $('#empcode').val(e.id);

                        // Simpan data payheads yang sudah terassign ke variabel global
                        window.assignedPayheads = e.payheads || [];
                        if (window.assignedPayheads.length > 0) {
                            renderAssignedPayheads(window.assignedPayheads);
                        }
                        // Load rekap absensi, dsb...
                        loadRekapAbsensi(e.id, e.role);
                    } else {
                        Swal.fire('Error', resp.result, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire('Error', 'Gagal mengambil detail anggota: ' + error, 'error');
                }
            });
        }

        // Kemudian, ketika Anda memanggil GetAllPayheads:
        $.ajax({
            url: 'employees.php?ajax=1',
            type: 'POST',
            dataType: 'json',
            data: {
                case: 'GetAllPayheads',
                csrf_token: CSRF_TOKEN
            },
            success: function(resp) {
                if (resp.code === 0) {
                    let assignedIds = [];
                    if (window.assignedPayheads && window.assignedPayheads.length > 0) {
                        assignedIds = window.assignedPayheads.map(ph => parseInt(ph.id_payhead, 10));
                    }
                    // Filter payheads yang belum diassign:
                    let availablePayheads = resp.result.filter(ph => !assignedIds.includes(parseInt(ph.id, 10)));
                    renderAllPayheads(availablePayheads);
                } else {
                    console.error('Gagal mengambil semua payheads:', resp.result);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error saat ambil payheads:', error);
            }
        });


        $('#chkKenaikanGajiTahunan').on('change', function() {
            if ($(this).is(':checked')) {
                $('#kenaikanGajiTahunanFields').show();
            } else {
                $('#kenaikanGajiTahunanFields').hide();
            }
        });

        // Fungsi load rekap absensi
        function loadRekapAbsensi(id_anggota, role) {
            let sMonth = localStorage.getItem("selectedMonthNumber") || selectedMonth;
            let sYear = localStorage.getItem("selectedYearPayroll") || selectedYear;
            $.ajax({
                url: 'employees.php?ajax=1',
                type: 'POST',
                dataType: 'json',
                data: {
                    case: 'ViewRekapAbsensi',
                    id: id_anggota,
                    selectedMonth: sMonth,
                    selectedYear: sYear,
                    csrf_token: CSRF_TOKEN
                },
                success: function(resp) {
                    if (resp.code === 0) {
                        let data = resp.result;
                        $("#rekap_total_hadir").text(data.total_hadir);
                        $("#rekap_total_izin").text(data.total_izin);
                        $("#rekap_total_cuti").text(data.total_cuti);
                        $("#rekap_total_tanpa_keterangan").text(data.total_tanpa_keterangan);
                        $("#rekap_total_sakit").text(data.total_sakit);
                        let potonganAbsensi = calcPotonganAbsensi(role, data.total_izin, data.total_cuti, data.total_tanpa_keterangan, data.total_sakit);
                        window.potonganAbsensiGlobal = potonganAbsensi;
                        recalcPayheadsTotals();
                    } else {
                        $("#rekap_total_hadir,#rekap_total_izin,#rekap_total_cuti,#rekap_total_tanpa_keterangan,#rekap_total_sakit").text("0");
                        window.potonganAbsensiGlobal = 0;
                        recalcPayheadsTotals();
                    }
                },
                error: function() {
                    $("#rekap_total_hadir,#rekap_total_izin,#rekap_total_cuti,#rekap_total_tanpa_keterangan,#rekap_total_sakit").text("0");
                    window.potonganAbsensiGlobal = 0;
                    recalcPayheadsTotals();
                }
            });
        }

        // Fungsi perhitungan potongan absensi
        function calcPotonganAbsensi(role, totalIzin, totalCuti, totalTK, totalSakit) {
            let totalTidakHadir = totalIzin + totalCuti + totalTK + totalSakit;
            let potongan = 0;
            if (role === 'P' || role === 'TK') {
                const biayaPerHari = 75000;
                potongan = Math.min(totalTidakHadir, 2) * biayaPerHari;
            } else if (role === 'M') {
                const biayaPerHariManajerial = 50000;
                potongan = totalTidakHadir * biayaPerHariManajerial;
            }
            return potongan;
        }

        // Fungsi render semua payheads yang tersedia
        function renderAllPayheads(allPayheads) {
            let container = $("#all_payheads");
            container.empty();
            // Render daftar available berdasarkan data yang sudah difilter (jika sudah assign, tidak muncul)
            allPayheads.forEach(function(ph) {
                // gunakan data-nama untuk menyimpan nama asli
                let namaAsli = ph.nama_payhead;
                let labelText = namaAsli + ' (' + ph.jenis_payhead_idn + ')';
                let textColor = (ph.jenis_payhead === 'earnings') ? 'text-success' : 'text-danger';
                let itemHtml = `
            <div class="payhead-item d-flex align-items-center mb-1"
                 data-id="${ph.id}"
                 data-nominal="${ph.nominal}"
                 data-type="${ph.jenis_payhead}"
                 data-nama="${namaAsli}">
                <button type="button" class="btn btn-sm btn-primary btnAddPayhead me-2">
                    <i class="bi bi-plus"></i>
                </button>
                <span class="payhead-name ${textColor}">${labelText}</span>
            </div>
        `;
                container.append(itemHtml);
            });
        }


        // Fungsi render payheads terpilih
        // Fungsi render payheads terpilih
        function renderAssignedPayheads(payheads) {
            const tbody = $("#selected_payamount_table tbody");
            tbody.empty();
            payheads.forEach(function(ph, index) {
                const payheadId = ph.id_payhead;
                const payheadType = (ph.jenis_payhead || '').toLowerCase();
                const defaultAmt = ph.amount || "0";
                const remarksVal = ph.remarks || '';
                const isRapel = (ph.is_rapel == 1);
                const supportDocPath = ph.support_doc_path || "";
                let fileHtml = "";
                if (supportDocPath) {
                    const fileName = supportDocPath.split('/').pop();
                    fileHtml = `
                <div class="uploaded-file-info mb-1">
                    <a href="${supportDocPath}" target="_blank" class="btn btn-info btn-sm mb-1">Lihat File</a>
                    <span class="text-muted small">${fileName}</span>
                    <button type="button" class="btn btn-danger btn-sm btn-remove-uploaded-file ms-1" data-payhead="${payheadId}">Hapus</button>
                </div>
            `;
                }
                // Badge sesuai jenis payhead
                let badgeHTML = (payheadType === 'earnings') ?
                    '<span class="badge bg-success text-white me-1">Pendapatan</span>' :
                    '<span class="badge bg-danger text-white me-1">Potongan</span>';
                const rapelChecked = isRapel ? "checked" : "";
                const disabledAttr = isRapel ? "disabled" : "";
                const removeButtonHtml = isRapel ? "" : `
            <button type="button" class="btn btn-danger btn-sm btnRemoveRow">
                <i class="bi bi-dash"></i>
            </button>
        `;
                // Kolom upload file
                const uploadHtml = `
            <div class="input-group">
                <input type="file" name="upload_file[${payheadId}]" class="file-input d-none" id="upload_file_${payheadId}" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                <label for="upload_file_${payheadId}" class="btn btn-sm btn-info file-label me-2">
                    Pilih File
                </label>
                <button type="button" class="btn btn-sm btn-danger btn-clear-file">
                    Hapus
                </button>
            </div>
            ${fileHtml}
        `;
                // Row HTML
                const rowHtml = `
            <tr data-id="${payheadId}" data-type="${ph.jenis_payhead}" data-nama="${ph.nama_payhead}">
                <td>${index + 1}</td>
                <td>
                    ${badgeHTML}<span class="payhead-text">${ph.nama_payhead}</span>
                </td>
                <td>
                    <input type="text" name="pay_amounts[${payheadId}]"
                        class="form-control currency-input"
                        value="${defaultAmt}"
                        ${disabledAttr} required>
                </td>
                <td>
                    <textarea name="remarks[${payheadId}]"
                        class="form-control"
                        ${disabledAttr}>${isRapel ? "Rapel" : remarksVal}</textarea>
                </td>
                <td>
                    <input type="checkbox" name="rapel[${payheadId}]"
                        class="rapel-checkbox" ${rapelChecked}>
                </td>
                <td>
                    ${uploadHtml}
                </td>
                <td>
                    ${removeButtonHtml}
                </td>
            </tr>
        `;
                const $row = $(rowHtml);
                $row.find('.currency-input').each(function() {
                    new AutoNumeric(this, {
                        digitGroupSeparator: '.',
                        decimalCharacter: ',',
                        decimalPlaces: 0,
                        unformatOnSubmit: true
                    });
                });
                tbody.append($row);
            });
            recalcPayheadsTotals();
        }



        // Event: checkbox rapel
        $(document).on('change', '.rapel-checkbox', function() {
            let $row = $(this).closest('tr');
            let isChecked = $(this).prop('checked');
            let $nominal = $row.find('input.currency-input');
            let $remarks = $row.find('textarea');
            let $removeBtn = $row.find('.btnRemoveRow'); // tombol hapus

            // Ambil nilai sekarang
            let currentNominal = $nominal.val();
            let currentRemarks = $remarks.val();

            if (isChecked) {
                // Simpan old values ke data tr
                $row.data('oldNominalVal', currentNominal);
                $row.data('oldRemarksVal', currentRemarks);

                // Nonaktifkan input
                $nominal.prop('disabled', true);
                $remarks.val('Rapel').prop('disabled', true);

                // Sembunyikan tombol remove
                $removeBtn.hide();
            } else {
                // Kembalikan ke kondisi sebelum rapel
                let oldNominal = $row.data('oldNominalVal') || currentNominal;
                let oldRemarks = $row.data('oldRemarksVal') || '';

                $nominal.val(oldNominal).prop('disabled', false);
                $remarks.val(oldRemarks).prop('disabled', false);

                // Tampilkan lagi tombol remove
                $removeBtn.show();
            }

            // Recalc
            recalcPayheadsTotals();
        });

        // Event: file input dan clear
        $(document).on('change', '.file-input', function() {
            const fileName = $(this).prop('files')[0] ? $(this).prop('files')[0].name : 'Pilih File';
            $(this).siblings('label.file-label').text(fileName);
        });

        $(document).on('click', '.btn-clear-file', function() {
            const inputFile = $(this).siblings('.file-input');
            inputFile.val('');
            $(this).siblings('label.file-label').text('Pilih File');
        });

        // Event: tambah payhead ke daftar terpilih
        $(document).on('click', '.btnAddPayhead', function() {
            const item = $(this).closest('.payhead-item');
            // Ambil nama asli dari data attribute agar tidak menggandakan suffix
            const payheadName = item.data('nama');
            const payheadId = item.data('id');
            const defaultAmt = item.data('nominal') || "0";
            const payType = (item.data('type') || '').toLowerCase();

            // Hapus item dari daftar "payheads tersedia"
            item.remove();

            // Cek payhead mana saja yang sudah ada di table
            const existingRows = $("#selected_payamount_table tbody tr")
                .map(function() {
                    return $(this).data('id');
                }).get();

            if (!existingRows.includes(payheadId)) {
                const newIndex = existingRows.length;
                // Hitung label yang benar—gunakan payheadName asli
                let badgeHTML = '';
                if (payType === 'earnings') {
                    badgeHTML = '<span class="badge bg-success text-white me-1">Pendapatan</span>';
                } else {
                    badgeHTML = '<span class="badge bg-danger text-white me-1">Potongan</span>';
                }
                // Buat row dengan data-nama (nama asli) sebagai data attribute
                const rowHtml = `
          <tr data-id="${payheadId}" data-type="${payType}" data-nama="${payheadName}">
            <td>${newIndex + 1}</td>
            <td>${badgeHTML}<span class="payhead-text">${payheadName}</span></td>
            <td>
              <input type="text" name="pay_amounts[${payheadId}]"
                     class="form-control currency-input" 
                     value="${defaultAmt}" required>
            </td>
            <td>
              <textarea name="remarks[${payheadId}]"
                        class="form-control"></textarea>
            </td>
            <td>
              <input type="checkbox" name="rapel[${payheadId}]"
                     class="rapel-checkbox">
            </td>
            <td>
              <div class="input-group">
                <input type="file" name="upload_file[${payheadId}]"
                       class="file-input d-none"
                       id="upload_file_${payheadId}"
                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                <label for="upload_file_${payheadId}"
                       class="btn btn-sm btn-info file-label me-2">
                       Pilih File
                </label>
                <button type="button" class="btn btn-sm btn-danger btn-clear-file">
                  Hapus
                </button>
              </div>
            </td>
            <td>
              <button type="button" class="btn btn-danger btn-sm btnRemoveRow">
                <i class="bi bi-dash"></i>
              </button>
            </td>
          </tr>
        `;
                const $newRow = $(rowHtml);
                $newRow.find('.currency-input').each(function() {
                    new AutoNumeric(this, {
                        digitGroupSeparator: '.',
                        decimalCharacter: ',',
                        decimalPlaces: 0,
                        unformatOnSubmit: true
                    });
                });
                $("#selected_payamount_table tbody").append($newRow);
                recalcPayheadsTotals();
            }
        });


        // Event: hapus baris payhead terpilih
        $(document).on('click', '.btnRemoveRow', function() {
            const row = $(this).closest('tr');
            const payheadId = row.data('id');
            const payheadType = row.data('type');
            // Ambil nama asli dari data attribute
            const payheadName = row.data('nama');
            const defaultAmt = row.find("input.currency-input").val() || "0";
            row.remove();
            // Update nomor urut di tabel selected
            $("#selected_payamount_table tbody tr").each(function(index) {
                $(this).find("td:first").text(index + 1);
            });
            // Susun label available dengan format yang sesuai:
            let labelText = "";
            if (payheadType.toLowerCase() === 'earnings') {
                labelText = payheadName + ' (Pendapatan)';
            } else {
                labelText = payheadName + ' (Potongan)';
            }
            // Tambahkan item ke daftar available hanya jika belum ada
            if ($("#all_payheads").find(`[data-id="${payheadId}"]`).length === 0) {
                const availableItem = $(`
            <div class="payhead-item d-flex align-items-center mb-1" data-id="${payheadId}" data-nominal="${defaultAmt}" data-type="${payheadType}" data-nama="${payheadName}">
                <button type="button" class="btn btn-sm btn-primary btnAddPayhead me-2">
                    <i class="bi bi-plus"></i>
                </button>
                <span class="payhead-name ${payheadType === 'earnings' ? 'text-success' : 'text-danger'}">
                    ${labelText}
                </span>
            </div>
        `);
                $("#all_payheads").append(availableItem);
                sortAvailablePayheads();
            }
            recalcPayheadsTotals();
        });


        function sortAvailablePayheads() {
            let items = $("#all_payheads .payhead-item").get();
            items.sort(function(a, b) {
                let textA = $(a).find('.payhead-name').text().toUpperCase();
                let textB = $(b).find('.payhead-name').text().toUpperCase();
                if (textA < textB) return -1;
                if (textA > textB) return 1;
                return 0;
            });
            $("#all_payheads").empty();
            $.each(items, function(index, item) {
                $("#all_payheads").append(item);
            });
        }


        // Fungsi re-calc total payheads
        function recalcPayheadsTotals() {
            let totalEarnings = 0;
            let totalDeductions = 0;
            $("#selected_payamount_table tbody tr").each(function() {
                let rapelChecked = $(this).find("input.rapel-checkbox").prop("checked");
                if (rapelChecked) {
                    return true;
                }
                let type = ($(this).data("type") || "").toLowerCase();
                let val = $(this).find("input.currency-input").val();
                let amount = parseFloat(val.replace(/\./g, '').replace(',', '.')) || 0;
                if (type === "earnings") {
                    totalEarnings += amount;
                } else if (type === "deduction" || type === "deductions" || type === "potongan") {
                    totalDeductions += amount;
                }
            });

            function formatNumber(num) {
                return num.toLocaleString('id-ID', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                });
            }
            $("#inputTotalEarnings").val("Rp " + formatNumber(totalEarnings));
            $("#inputTotalDeductions").val("Rp " + formatNumber(totalDeductions));
            let gajiPokokText = $("#inputGajiPokok").val().replace(/[Rp\s.]/g, '').replace(',', '.');
            let gajiPokok = parseFloat(gajiPokokText) || 0;
            let indexNominalText = $("#inputIndexNominal").val().replace(/[Rp\s.]/g, '').replace(',', '.');
            let indexNominal = parseFloat(indexNominalText) || 0;
            let potonganAbsensi = window.potonganAbsensiGlobal || 0;
            $("#inputPotonganAbsensi").val("Rp " + formatNumber(potonganAbsensi));
            let netSalary = gajiPokok + indexNominal + totalEarnings - totalDeductions - potonganAbsensi;
            $("#inputNetSalary").val("Rp " + formatNumber(netSalary));
        }

        $("#selected_payamount_table").on("input", "input.currency-input", function() {
            recalcPayheadsTotals();
        });

        // Fungsi simpan assignment payheads
        function saveAssignmentPayheads(callback) {
            let form = $('#assign-payhead-form');
            let payHeads = [];
            $("#selected_payamount_table tbody tr").each(function() {
                payHeads.push($(this).data('id'));
            });
            let payAmounts = {};
            payHeads.forEach(function(payheadId) {
                let inputSel = `input[name="pay_amounts[${payheadId}]"]`;
                let numericVal = AutoNumeric.getNumber($(inputSel)[0]) || 0;
                payAmounts[payheadId] = numericVal;
            });
            let rapels = {};
            $("#selected_payamount_table tbody tr").each(function() {
                let payheadId = $(this).data("id");
                let checked = $(this).find("input.rapel-checkbox").prop("checked") ? 1 : 0;
                rapels[payheadId] = checked;
            });
            let isValid = true;
            payHeads.forEach(function(pid) {
                let val = payAmounts[pid];
                if (!val || isNaN(val) || val <= 0) {
                    $(`input[name="pay_amounts[${pid}]"]`).addClass('is-invalid');
                    isValid = false;
                } else {
                    $(`input[name="pay_amounts[${pid}]"]`).removeClass('is-invalid');
                }
            });
            if (!isValid) {
                Swal.fire('Error', 'Pastikan semua jumlah payhead valid (angka & > 0)!', 'error');
                return;
            }
            let formData = new FormData(form[0]);
            formData.append('payheads', JSON.stringify(payHeads));
            formData.append('pay_amounts', JSON.stringify(payAmounts));
            formData.append('rapels', JSON.stringify(rapels));
            formData.append('tgl_payroll', $("#inputTanggalPayroll").val());
            $.ajax({
                url: 'employees.php?ajax=1',
                type: 'POST',
                dataType: 'json',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success: function(resp) {
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if (resp.code === 0) {
                        Swal.fire('Sukses', resp.result, 'success').then(function() {
                            window.location.href = 'employees.php';
                        });
                        if (typeof callback === 'function') {
                            callback();
                        }
                    } else {
                        Swal.fire('Error', resp.result, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    Swal.fire('Error', 'Terjadi kesalahan saat menetapkan payheads: ' + error, 'error');
                }
            });
        }

        // Handler untuk membuka modal Assign Payheads
        $(document).on("click", ".btnAssignPayheads", function() {
            // Cegah jika payroll sudah final
            let cardStatus = $(this).closest('.card').data('payroll_status');
            if (typeof cardStatus !== 'undefined' && cardStatus.toLowerCase() === 'final') {
                showToast("Payroll sudah final, tidak dapat mengubah payheads.", "warning");
                return;
            }
            let id = $(this).data("id");
            $('#empcode').val(id);
            $('#all_payheads').empty();
            $("#selected_payamount_table tbody").empty();
            $.ajax({
                type: "POST",
                dataType: "json",
                url: 'employees.php?ajax=1',
                data: {
                    case: 'ViewEmployeeDetail',
                    id: id,
                    csrf_token: CSRF_TOKEN,
                    selectedMonth: localStorage.getItem("selectedMonthNumber"),
                    selectedYear: localStorage.getItem("selectedYearPayroll"),
                    includeRapel: 1
                },
                success: function(result) {
                    if (result.code === 0) {
                        var e = result.result;
                        $('#fieldAnggota').val(e.nama);
                        $('#fieldRole').val(e.role);
                        $('#fieldJobTitle').val(e.job_title);
                        var selMonth = localStorage.getItem('selectedMonthPayroll') || '';
                        var selYear = localStorage.getItem('selectedYearPayroll') || '';
                        $('#fieldPeriode').val(selMonth + " " + selYear);
                        $('#fieldMasaKerja').val(e.masa_kerja);
                        $('#inputIndexLevel').val(e.salary_index_level);
                        $('#inputNoRek').val(e.no_rekening);
                        var now = new Date();
                        $('#inputTanggalPayroll').val(now.toISOString().slice(0, 16));
                        $('#inputDescription').val('');
                        $('#inputGajiPokok').val(e.gaji_pokok_val);
                        let indexBaseFormatted = e.salary_index_base.toLocaleString('id-ID', {
                            minimumFractionDigits: 0
                        });
                        $('#inputIndexNominal').val(indexBaseFormatted);
                        $('#inputTotalEarnings').val('Rp 0');
                        $('#inputTotalDeductions').val('Rp 0');
                        $('#inputNetSalary').val('Rp ' + parseFloat(e.gaji_pokok_val).toLocaleString('id-ID', {
                            minimumFractionDigits: 0
                        }));
                        $.ajax({
                            url: 'employees.php?ajax=1',
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                case: 'ViewRekapAbsensi',
                                id: id,
                                selectedMonth: localStorage.getItem('selectedMonthNumber'),
                                selectedYear: localStorage.getItem('selectedYearPayroll'),
                                csrf_token: CSRF_TOKEN
                            },
                            success: function(resp) {
                                if (resp.code === 0) {
                                    var data = resp.result;
                                    $("#rekap_total_hadir").text(data.total_hadir);
                                    $("#rekap_total_izin").text(data.total_izin);
                                    $("#rekap_total_cuti").text(data.total_cuti);
                                    $("#rekap_total_tanpa_keterangan").text(data.total_tanpa_keterangan);
                                    $("#rekap_total_sakit").text(data.total_sakit);
                                    let potonganAbsensi = calcPotonganAbsensi(e.role, data.total_izin, data.total_cuti, data.total_tanpa_keterangan, data.total_sakit);
                                    window.potonganAbsensiGlobal = potonganAbsensi;
                                } else {
                                    $("#rekap_total_hadir, #rekap_total_izin, #rekap_total_cuti, #rekap_total_tanpa_keterangan, #rekap_total_sakit").text("0");
                                    window.potonganAbsensiGlobal = 0;
                                }
                                recalcPayheadsTotals();
                            },
                            error: function() {
                                $("#rekap_total_hadir, #rekap_total_izin, #rekap_total_cuti, #rekap_total_tanpa_keterangan, #rekap_total_sakit").text("0");
                                window.potonganAbsensiGlobal = 0;
                                recalcPayheadsTotals();
                            }
                        });
                        $.ajax({
                            type: "POST",
                            dataType: "json",
                            url: 'employees.php?ajax=1',
                            data: {
                                case: 'GetAllPayheads',
                                csrf_token: CSRF_TOKEN
                            },
                            success: function(allPayheadsResult) {
                                if (allPayheadsResult.code === 0) {
                                    var allPayheadsList = allPayheadsResult.result;
                                    var assignedPayheads = e.payheads || [];
                                    var assignedIds = assignedPayheads.map(function(ph) {
                                        return parseInt(ph.id_payhead, 10);
                                    });
                                    var availablePayheads = allPayheadsList.filter(function(ph) {
                                        return !assignedIds.includes(parseInt(ph.id, 10));
                                    });
                                    const availableDiv = $("#all_payheads");
                                    availableDiv.empty();
                                    availablePayheads.forEach(function(ph) {
                                        const labelText = ph.nama_payhead + ' (' + ph.jenis_payhead_idn + ')';
                                        const item = $(`
                                      <div class="payhead-item d-flex align-items-center mb-1" data-id="${ph.id}" data-nominal="${ph.nominal}" data-type="${ph.jenis_payhead}">
                                        <button type="button" class="btn btn-sm btn-primary btnAddPayhead me-2">
                                          <i class="bi bi-plus"></i>
                                        </button>
                                        <span class="payhead-name ${ph.jenis_payhead === 'earnings' ? 'text-success' : 'text-danger'}">
                                          ${labelText}
                                        </span>
                                      </div>
                                    `);
                                        availableDiv.append(item);
                                    });
                                    renderAssignedPayheads(assignedPayheads);
                                    recalcPayheadsTotals();
                                    $('#ManageModal').modal('show');
                                } else {
                                    showToast(allPayheadsResult.result, 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                showToast('Terjadi kesalahan saat memuat semua payheads: ' + error, 'error');
                            }
                        });
                    } else {
                        showToast(result.result, 'error');
                    }
                },
                error: function() {
                    showToast('Terjadi kesalahan saat load payheads.', 'error');
                }
            });
        });

        // Handler untuk submit form Assign Payheads
        $('#assign-payhead-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var payHeads = [];
            $("#selected_payamount_table tbody tr").each(function() {
                payHeads.push($(this).data('id'));
            });
            var payAmounts = {};
            payHeads.forEach(function(payheadId) {
                var inputSel = `input[name="pay_amounts[${payheadId}]"]`;
                var amount = $(inputSel).val();
                payAmounts[payheadId] = amount;
            });
            // Kumpulkan nilai checkbox rapel
            var rapels = {};
            $("#selected_payamount_table tbody tr").each(function() {
                var payheadId = $(this).data("id");
                var checked = $(this).find("input.rapel-checkbox").prop("checked") ? 1 : 0;
                rapels[payheadId] = checked;
            });
            var isValid = true;
            payHeads.forEach(function(payheadId) {
                var amount = payAmounts[payheadId];
                var numericAmount = parseFloat(amount.replace(/\./g, '').replace(',', '.'));
                if (!amount || isNaN(numericAmount) || numericAmount <= 0) {
                    $(`input[name="pay_amounts[${payheadId}]"]`).addClass('is-invalid');
                    isValid = false;
                } else {
                    $(`input[name="pay_amounts[${payheadId}]"]`).removeClass('is-invalid');
                }
            });
            if (!isValid) {
                showToast('Pastikan semua jumlah payhead valid (angka & > 0)!', 'error');
                return;
            }
            var formData = new FormData(form[0]);
            formData.append('payheads', JSON.stringify(payHeads));
            formData.append('pay_amounts', JSON.stringify(payAmounts));
            formData.append('rapels', JSON.stringify(rapels));
            $.ajax({
        url: 'employees.php?ajax=1',
        type: 'POST',
        dataType: 'json',
        data: formData,
        processData: false,
        contentType: false,
        beforeSend: function() {
            form.find('button[type="submit"]').prop('disabled', true);
            form.find('.spinner-border').removeClass('d-none');
        },
        success: function(resp) {
            form.find('button[type="submit"]').prop('disabled', false);
            form.find('.spinner-border').addClass('d-none');
            if (resp.code === 0) {
                showToast(resp.result, 'success');
                window.location.href = 'employees.php';
                setTimeout(function() {
                    $('#ManageModal').modal('hide');
                    form[0].reset();
                    $("#all_payheads").empty();
                    $("#selected_payamount_table tbody").empty();
                }, 200);
            } else {
                showToast(resp.result, 'error');
            }
        },
        error: function(xhr, status, error) {
            form.find('button[type="submit"]').prop('disabled', false);
            form.find('.spinner-border').addClass('d-none');
            showToast('Terjadi kesalahan saat menetapkan payheads: ' + error, 'error');
        }
    });
});

        function savePayheads(callback) {
            var form = $('#assign-payhead-form');
            var payHeads = [];
            $("#selected_payamount_table tbody tr").each(function() {
                payHeads.push($(this).data('id'));
            });
            var payAmounts = {};
            payHeads.forEach(function(payheadId) {
                var inputSel = `input[name="pay_amounts[${payheadId}]"]`;
                var amount = $(inputSel).val();
                payAmounts[payheadId] = amount;
            });
            var rapels = {};
            $("#selected_payamount_table tbody tr").each(function() {
                var payheadId = $(this).data("id");
                var checked = $(this).find("input.rapel-checkbox").prop("checked") ? 1 : 0;
                rapels[payheadId] = checked;
            });
            var isValid = true;
            payHeads.forEach(function(payheadId) {
                var amount = payAmounts[payheadId];
                var numericAmount = parseFloat(amount.replace(/\./g, '').replace(',', '.'));
                if (!amount || isNaN(numericAmount) || numericAmount <= 0) {
                    $(`input[name="pay_amounts[${payheadId}]"]`).addClass('is-invalid');
                    isValid = false;
                } else {
                    $(`input[name="pay_amounts[${payheadId}]"]`).removeClass('is-invalid');
                }
            });
            if (!isValid) {
                showToast('Pastikan semua jumlah payhead valid (angka & > 0)!', 'error');
                return;
            }
            var formData = new FormData(form[0]);
            formData.append('payheads', JSON.stringify(payHeads));
            formData.append('pay_amounts', JSON.stringify(payAmounts));
            formData.append('rapels', JSON.stringify(rapels));
            $.ajax({
                url: 'employees.php?ajax=1',
                type: 'POST',
                dataType: 'json',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success: function(resp) {
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if (resp.code === 0) {
                        showToast(resp.result, 'success');
                        setTimeout(function() {
                            window.location.href = 'employees.php'; // Redirect ke halaman utama
                        }, 1500);
                        if (typeof callback === 'function') {
                            callback();
                        }
                    } else {
                        showToast(resp.result, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat menetapkan payheads: ' + error, 'error');
                }
            });
        }

        function callProcessPayroll(empcode, selectedMonth, selectedYear) {
            $.ajax({
                url: 'employees.php?ajax=1',
                type: 'POST',
                dataType: 'json',
                data: {
                    case: 'ProcessPayroll',
                    id_anggota: empcode,
                    selectedMonth: selectedMonth,
                    selectedYear: selectedYear,
                    csrf_token: '<?= htmlspecialchars($csrf_token); ?>'
                },
                success: function(resp) {
                    if (resp.code === 0) {
                        Swal.fire('Berhasil', resp.result, 'success').then(() => {
                            window.location.href = 'employees.php';
                            $('#ManageModal').modal('hide');
                        });
                    } else {
                        Swal.fire('Gagal', resp.result, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire('Error', 'Terjadi kesalahan saat memproses payroll: ' + error, 'error');
                }
            });
        }

        $(document).on('click', '.btn-remove-uploaded-file', function() {
            const payheadId = $(this).data('payhead');
            const empcode = $('#empcode').val();
            const btn = $(this);
            Swal.fire({
                title: 'Hapus File?',
                text: "File akan dihapus permanen untuk payhead ini.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'employees.php?ajax=1',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            case: 'RemovePayrollFile',
                            empcode: empcode,
                            payhead_id: payheadId,
                            csrf_token: CSRF_TOKEN
                        },
                        success: function(resp) {
                            if (resp.code === 0) {
                                btn.closest('.uploaded-file-info').remove();
                                showToast('File berhasil dihapus');
                            } else {
                                showToast('Gagal hapus file: ' + resp.result, 'error');
                            }
                        },
                        error: function() {
                            showToast('Terjadi kesalahan saat hapus file', 'error');
                        }
                    });
                }
            });
        });

        // Proses payroll: Trigger insert payroll_detail (exclude payheads rapel)
        $('#btnProcessPayroll').on('click', function() {
            var selectedMonth = localStorage.getItem('selectedMonthNumber') || 0;
            var selectedYear = localStorage.getItem('selectedYearPayroll') || 0;
            var empcode = $('#empcode').val();
            if (!empcode || selectedMonth == 0 || selectedYear == 0) {
                Swal.fire('Error', 'Pastikan ID anggota dan bulan payroll valid!', 'error');
                return;
            }
            Swal.fire({
                title: 'Proses Payroll',
                text: "Apakah Anda yakin data payroll sudah benar dan ingin diproses?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, proses sekarang',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    if ($("#selected_payamount_table tbody tr").length > 0) {
                        savePayheads(function() {
                            callProcessPayroll(empcode, selectedMonth, selectedYear);
                        });
                    } else {
                        callProcessPayroll(empcode, selectedMonth, selectedYear);
                    }
                }
            });
        });
function toDatetimeLocal(val) {
  if (!val) return '';
  const date = new Date(val.replace(' ', 'T'));
  if (isNaN(date.getTime())) return '';
  return date.toISOString().slice(0,16); // "YYYY-MM-DDTHH:mm"
}
$("#inputTanggalPayroll").val(toDatetimeLocal(payrollDraft.tgl_payroll));


    });
</script>
</body>

</html>