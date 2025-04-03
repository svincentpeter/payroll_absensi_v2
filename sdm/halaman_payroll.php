<?php
// File: /payroll_absensi_v2/sdm/halaman_payroll.php

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();

generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

require_once __DIR__ . '/../koneksi.php';
authorize(['M:SDM', 'M:Superadmin'], '/payroll_absensi_v2/login.php');

// Ambil parameter dari URL
$id_anggota    = isset($_GET['id']) ? intval($_GET['id']) : 0;
$selectedMonth = isset($_GET['bulan']) ? intval($_GET['bulan']) : date('n');
$selectedYear  = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');

// Dapatkan data anggota
$stmt = $conn->prepare("
    SELECT a.*, si.level AS salary_index_level, si.base_salary AS salary_index_base
    FROM anggota_sekolah a
    LEFT JOIN salary_indices si ON a.salary_index_id = si.id
    WHERE a.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id_anggota);
$stmt->execute();
$res = $stmt->get_result();
$empData = $res->fetch_assoc();
$stmt->close();

// Ambil payheads yang sudah ditetapkan (assigned)
$stmtPH = $conn->prepare("
    SELECT ep.id_payhead, ph.nama_payhead, ph.jenis AS jenis_payhead,
           ep.amount, ep.support_doc_path, ep.is_rapel, ep.remarks
    FROM employee_payheads ep
    JOIN payheads ph ON ep.id_payhead = ph.id
    WHERE ep.id_anggota = ?
");
$stmtPH->bind_param("i", $id_anggota);
$stmtPH->execute();
$resPH = $stmtPH->get_result();
$assignedPayheads = [];
while ($rw = $resPH->fetch_assoc()) {
    $assignedPayheads[$rw['id_payhead']] = $rw;
}
$stmtPH->close();

// Ambil semua payheads dari master
$sqlAllPH = "SELECT id, nama_payhead, jenis, nominal FROM payheads ORDER BY nama_payhead ASC";
$resAllPH = $conn->query($sqlAllPH);
$allPayheads = [];
while ($row = $resAllPH->fetch_assoc()) {
    $allPayheads[] = $row;
}
// Tentukan payheads yang masih tersedia (belum ditetapkan)
$availablePayheads = array_filter($allPayheads, function($ph) use ($assignedPayheads) {
    return !isset($assignedPayheads[$ph['id']]);
});

// Ambil rekap absensi (pastikan data yang diambil sesuai dengan parameter URL)
$stmtAbsensi = $conn->prepare("
    SELECT *
    FROM rekap_absensi
    WHERE id_anggota = ? AND bulan = ? AND tahun = ?
    LIMIT 1
");
$stmtAbsensi->bind_param("iii", $id_anggota, $selectedMonth, $selectedYear);
$stmtAbsensi->execute();
$rekapAbsensi = $stmtAbsensi->get_result()->fetch_assoc();
$stmtAbsensi->close();

// Hitung potongan absensi
$totalIzin   = $rekapAbsensi['total_izin'] ?? 0;
$totalCuti   = $rekapAbsensi['total_cuti'] ?? 0;
$totalTK     = $rekapAbsensi['total_tanpa_keterangan'] ?? 0;
$totalSakit  = $rekapAbsensi['total_sakit'] ?? 0;
$potonganAbsensi = 0;
if ($empData['role'] === 'P' || $empData['role'] === 'TK') {
    $biayaPerHari    = 75000;
    $totalTidakHadir = $totalIzin + $totalCuti + $totalTK + $totalSakit;
    $potonganAbsensi = min($totalTidakHadir, 2) * $biayaPerHari;
} elseif ($empData['role'] === 'M') {
    $biayaPerHari    = 50000;
    $totalTidakHadir = $totalIzin + $totalCuti + $totalTK + $totalSakit;
    $potonganAbsensi = $totalTidakHadir * $biayaPerHari;
}

// Hitung gaji
$gajiPokok       = floatval($empData['gaji_pokok']);
$salaryIndex     = floatval($empData['salary_index_base']);
$totalEarnings   = 0;
$totalDeductions = 0;
foreach ($assignedPayheads as $ph) {
    if ($ph['jenis_payhead'] === 'earnings') {
        $totalEarnings += floatval($ph['amount']);
    } else {
        $totalDeductions += floatval($ph['amount']);
    }
}
$gajiBersih = $gajiPokok + $salaryIndex + $totalEarnings - $totalDeductions - $potonganAbsensi;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Payroll - <?= htmlspecialchars($empData['nama']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap & Icon CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        /* Global Styles */
        body { font-family: Arial, sans-serif; font-size: 0.9rem; background-color: #f4f6f9; color: #333; }
        .container-fluid { max-width: 1800px; margin: 20px auto; padding: 0 15px; }
        .card { background: #fff; border: none; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-header-custom { background: linear-gradient(45deg, #0d47a1, #42a5f5); color: #fff; border-radius: 8px 8px 0 0; font-size: 1.1rem; font-weight: bold; padding: 15px 20px; }
        .employee-photo { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #0d47a1; margin-bottom: 10px; }
        .form-control { border-radius: 4px; height: auto; padding: 6px 10px; }
        .available-payheads { max-height: 350px; overflow-y: auto; padding: 10px; background-color: #f8f9fa; border-radius: 5px; }
        .available-payheads .payhead-item { display: flex; align-items: center; padding: 8px; margin-bottom: 8px; background-color: #fff; border: 1px solid #ddd; border-radius: 5px; transition: background-color 0.2s; }
        .available-payheads .payhead-item:hover { background-color: #e9ecef; }
        .available-payheads .payhead-item button { margin-right: 10px; }
        #selectedPayheadsTable { width: 100%; border-collapse: collapse; }
        #selectedPayheadsTable th, #selectedPayheadsTable td { padding: 10px; border: 1px solid #dee2e6; vertical-align: middle; }
        #selectedPayheadsTable thead th { background-color: #f1f3f5; position: sticky; top: 0; z-index: 2; }
        /* Rekap Absensi Styles */
        .absensi-cards { display: flex; justify-content: center; gap: 20px; padding: 20px 0; margin: 0 auto; max-width: 1000px; }
        .absensi-card { min-width: 130px; background-color: #fff; border: 2px solid #ddd; border-radius: 8px; text-align: center; padding: 15px 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; }
        .absensi-card:hover { transform: translateY(-2px); box-shadow: 0 3px 6px rgba(0,0,0,0.15); }
        .absensi-card .label { display: block; font-size: 0.85rem; margin-bottom: 5px; color: #333; }
        .absensi-card .value { font-size: 1.1rem; font-weight: bold; margin: 0; }
        .hadir-card { border-color: #28a745; }
        .izin-card { border-color: #fd7e14; }
        .cuti-card { border-color: #ffc107; }
        .tk-card { border-color: #dc3545; }
        .sakit-card { border-color: #6c757d; }
        .absensi-potongan { margin-top: 10px; text-align: right; }
        .absensi-potongan .label { font-weight: bold; color: #dc3545; }
        .absensi-potongan .nominal { color: #dc3545; font-weight: bold; }
        .currency-input { text-align: right; }
    </style>
    <script>
        const CSRF_TOKEN = '<?= htmlspecialchars($csrf_token); ?>';
    </script>
</head>
<body id="page-top">
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0"><i class="bi bi-cash-coin"></i> Proses Payroll</h1>
        <a href="employees.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
    
    <!-- Informasi Anggota -->
    <div class="card mb-3">
        <div class="card-header card-header-custom"><i class="bi bi-person-badge"></i> Informasi Anggota</div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-2 text-center">
                    <img src="<?= getProfilePhotoUrl($empData['nama'], $empData['jenjang'], $empData['role'], $empData['id']) ?>" class="employee-photo" alt="Foto Profil">
                </div>
                <div class="col-md-10">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <small class="text-muted">Nama</small>
                            <p class="fw-bold mb-1"><?= htmlspecialchars($empData['nama']) ?></p>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">NIP</small>
                            <p class="fw-bold mb-1"><?= htmlspecialchars($empData['nip']) ?></p>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Periode</small>
                            <p class="fw-bold mb-1"><?= date('F Y', mktime(0,0,0,$selectedMonth,1,$selectedYear)) ?></p>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Gaji Pokok</small>
                            <p class="fw-bold mb-1"><?= 'Rp ' . number_format($gajiPokok, 2, ',', '.') ?></p>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Indeks Gaji</small>
                            <p class="fw-bold mb-1"><?= 'Rp ' . number_format($salaryIndex, 2, ',', '.') ?></p>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">No Rekening</small>
                            <p class="fw-bold mb-1"><?= htmlspecialchars($empData['no_rekening']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Rekap Absensi -->
    <div class="card mb-3">
        <div class="card-header card-header-custom"><i class="bi bi-calendar-check"></i> Rekap Absensi</div>
        <div class="card-body">
            <div class="absensi-cards">
                <div class="absensi-card hadir-card">
                    <span class="label">Hadir</span>
                    <span class="value"><?= $rekapAbsensi['total_hadir'] ?? 0 ?></span>
                </div>
                <div class="absensi-card izin-card">
                    <span class="label">Izin</span>
                    <span class="value"><?= $totalIzin ?></span>
                </div>
                <div class="absensi-card cuti-card">
                    <span class="label">Cuti</span>
                    <span class="value"><?= $totalCuti ?></span>
                </div>
                <div class="absensi-card tk-card">
                    <span class="label">Tanpa Ket.</span>
                    <span class="value"><?= $totalTK ?></span>
                </div>
                <div class="absensi-card sakit-card">
                    <span class="label">Sakit</span>
                    <span class="value"><?= $totalSakit ?></span>
                </div>
            </div>
            <div class="absensi-potongan">
                <span class="label">Potongan Absensi:</span>
                <span class="nominal"><?= 'Rp ' . number_format($potonganAbsensi, 2, ',', '.') ?></span>
            </div>
            <!-- Tombol untuk review/edit rekap absensi
                 Data bulan dan tahun di-push ke data-attribute agar konsisten -->
            <div class="text-end mt-2">
                <button type="button" class="btn btn-sm btn-info btnRekapAbsensi"
                        data-id="<?= $id_anggota ?>"
                        data-role="<?= $empData['role'] ?>"
                        data-bulan="<?= $selectedMonth ?>"
                        data-tahun="<?= $selectedYear ?>">
                    Edit Rekap Absensi
                </button>
            </div>
        </div>
    </div>
    
    <!-- Form Payroll -->
    <form id="payrollForm" class="card mb-3" enctype="multipart/form-data">
        <div class="card-header card-header-custom"><i class="bi bi-clipboard-data"></i> Komponen Payroll</div>
        <div class="card-body">
            <!-- Hidden Inputs -->
            <input type="hidden" name="case" value="AssignPayheadsToEmployee">
            <input type="hidden" name="empcode" value="<?= $id_anggota ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="selectedMonth" value="<?= $selectedMonth ?>">
            <input type="hidden" name="selectedYear" value="<?= $selectedYear ?>">
            
            <div class="row g-3">
                <!-- Payheads Tersedia -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <strong><i class="bi bi-list"></i> Payheads Tersedia</strong>
                        </div>
                        <div class="card-body available-payheads">
                            <?php if (!empty($availablePayheads)): ?>
                                <?php foreach ($availablePayheads as $ph): ?>
                                    <div class="payhead-item" data-id="<?= $ph['id'] ?>" data-nominal="<?= $ph['nominal'] ?>" data-jenis="<?= $ph['jenis'] ?>">
                                        <button type="button" class="btn btn-sm btn-primary btnAddPayhead"><i class="bi bi-plus"></i></button>
                                        <span class="payhead-name <?= ($ph['jenis'] === 'earnings') ? 'text-success' : 'text-danger' ?>">
                                            <?= htmlspecialchars($ph['nama_payhead']) ?>
                                            (<?= $ph['jenis'] === 'earnings' ? 'Pendapatan' : 'Potongan' ?>)
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">Tidak ada payhead tersedia.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Payheads Terpilih -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <strong><i class="bi bi-check-circle"></i> Payheads Terpilih</strong>
                        </div>
                        <div class="card-body p-2">
                            <div style="overflow-x:auto;">
                                <table class="table table-bordered mb-0" id="selectedPayheadsTable">
                                    <thead>
                                        <tr>
                                            <th style="width:5%;">No.</th>
                                            <th style="width:25%;">Nama Payhead</th>
                                            <th style="width:15%;">Nominal</th>
                                            <th style="width:25%;">Keterangan</th>
                                            <th style="width:10%;">Rapel</th>
                                            <th style="width:10%;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $i = 1;
                                        if (!empty($assignedPayheads)):
                                            foreach ($assignedPayheads as $ap):
                                        ?>
                                        <tr data-id="<?= $ap['id_payhead'] ?>" data-jenis="<?= $ap['jenis_payhead'] ?>">
                                            <td><?= $i++; ?></td>
                                            <td><?= htmlspecialchars($ap['nama_payhead']) ?></td>
                                            <td>
                                                <input type="text" class="form-control currency-input"
                                                       name="pay_amounts[<?= $ap['id_payhead'] ?>]"
                                                       value="<?= number_format($ap['amount'], 2, ',', '.') ?>">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control"
                                                       name="remarks[<?= $ap['id_payhead'] ?>]"
                                                       value="<?= htmlspecialchars($ap['remarks']) ?>">
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" class="rapel-checkbox"
                                                       name="rapel[<?= $ap['id_payhead'] ?>]"
                                                       <?= $ap['is_rapel'] ? 'checked' : '' ?>>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-danger btnRemovePayhead"><i class="bi bi-trash"></i></button>
                                            </td>
                                        </tr>
                                        <?php
                                            endforeach;
                                        endif;
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- /.row -->
            
            <!-- Ringkasan Perhitungan -->
            <div class="row mt-4">
                <div class="col-md-5 ms-auto">
                    <table class="table table-bordered">
                        <tr>
                            <th>Total Pendapatan</th>
                            <td class="text-success" id="totalEarningsDisplay"><?= 'Rp ' . number_format($totalEarnings, 2, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <th>Total Potongan</th>
                            <td class="text-danger" id="totalDeductionsDisplay"><?= 'Rp ' . number_format($totalDeductions + $potonganAbsensi, 2, ',', '.') ?></td>
                        </tr>
                        <tr class="table-active">
                            <th>Gaji Bersih</th>
                            <td class="fw-bold" id="gajiBersihDisplay"><?= 'Rp ' . number_format($gajiBersih, 2, ',', '.') ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Tombol Aksi -->
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Perubahan</button>
                <button type="button" class="btn btn-success" id="btnProsesPayroll"><i class="bi bi-check-circle"></i> Proses Payroll</button>
            </div>
        </div>
    </form>
</div> <!-- /.container-fluid -->

<!-- Modal: Review Rekap Absensi -->
<div class="modal fade" id="rekapReviewModal" tabindex="-1" aria-labelledby="rekapReviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rekapReviewModalLabel"><i class="bi bi-eye"></i> Review Rekap Absensi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="data-display">
                    <p><strong>ID Anggota:</strong> <span id="review_id_anggota"></span></p>
                    <p><strong>Bulan:</strong> <span id="review_bulan"></span></p>
                    <p><strong>Tahun:</strong> <span id="review_tahun"></span></p>
                    <p><strong>Total Hadir:</strong> <span id="review_total_hadir"></span></p>
                    <p><strong>Total Izin:</strong> <span id="review_total_izin"></span></p>
                    <p><strong>Total Cuti:</strong> <span id="review_total_cuti"></span></p>
                    <p><strong>Total Tanpa Keterangan:</strong> <span id="review_total_tanpa_keterangan"></span></p>
                    <p><strong>Total Sakit:</strong> <span id="review_total_sakit"></span></p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Tutup</button>
                <button type="button" class="btn btn-primary" id="btnOpenEditRekap">Edit</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Edit Rekap Absensi -->
<div class="modal fade" id="rekapAbsensiModal" tabindex="-1" aria-labelledby="rekapAbsensiModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="rekapAbsensiForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="rekapAbsensiModalLabel"><i class="bi bi-calendar-check"></i> Edit Rekap Absensi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="case" value="EditRekapAbsensi">
                    <input type="hidden" name="id_anggota" id="rekap_id_anggota_edit">
                    <input type="hidden" name="bulan" id="rekap_bulan_edit">
                    <input type="hidden" name="tahun" id="rekap_tahun_edit">
                    <div class="mb-3">
                        <label for="total_hadir_edit" class="form-label">Total Hadir</label>
                        <input type="number" class="form-control" name="total_hadir" id="total_hadir_edit" required>
                    </div>
                    <div class="mb-3">
                        <label for="total_izin_edit" class="form-label">Total Izin</label>
                        <input type="number" class="form-control" name="total_izin" id="total_izin_edit" required>
                    </div>
                    <div class="mb-3">
                        <label for="total_cuti_edit" class="form-label">Total Cuti</label>
                        <input type="number" class="form-control" name="total_cuti" id="total_cuti_edit" required>
                    </div>
                    <div class="mb-3">
                        <label for="total_tanpa_keterangan_edit" class="form-label">Total Tanpa Keterangan</label>
                        <input type="number" class="form-control" name="total_tanpa_keterangan" id="total_tanpa_keterangan_edit" required>
                    </div>
                    <div class="mb-3">
                        <label for="total_sakit_edit" class="form-label">Total Sakit</label>
                        <input type="number" class="form-control" name="total_sakit" id="total_sakit_edit" required>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Perubahan
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Pilih Bulan (Payroll) -->
<div class="modal fade" id="SalaryMonthModal" tabindex="-1" aria-labelledby="salaryMonthModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" style="max-width: 600px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="salaryMonthModalLabel"><i class="fa fa-calendar"></i> Pilih Bulan untuk Payroll</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="row text-center">
                    <?php
                    $currentYear  = date('Y');
                    $currentMonth = date('n');
                    $startMonth   = $currentMonth - 2;
                    $startYear    = $currentYear;
                    for ($i = 0; $i < 16; $i++) {
                        $month = $startMonth + $i;
                        $year  = $startYear;
                        if ($month <= 0) {
                            $month += 12;
                            $year  -= 1;
                        } elseif ($month > 12) {
                            $month -= 12;
                            $year  += 1;
                        }
                        $highlightClass = 'bg-light';
                        foreach ($processedMonths as $pm) {
                            if ($pm['bulan'] == $month && $pm['tahun'] == $year) {
                                $highlightClass = 'processed-month';
                                break;
                            }
                        }
                        if ($month == $selectedMonth && $year == $selectedYear) {
                            $highlightClass = 'bg-warning text-dark fw-bold';
                        }
                        echo '<div class="col-3 mb-3">';
                        echo '  <div class="p-2 ' . $highlightClass . '" style="border: 1px solid #ddd; border-radius: 5px;">';
                        echo '    <a href="#" class="month-link" data-month-number="' . $month . '" data-month="' . date("F", mktime(0, 0, 0, $month, 1)) . '" data-year="' . $year . '" style="color: inherit; text-decoration: none;">';
                        echo '      ' . strtoupper(date("F", mktime(0, 0, 0, $month, 1))) . '<br>' . $year;
                        echo '    </a>';
                        echo '  </div>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JS Dependencies: jQuery, Bootstrap, SweetAlert2, AutoNumeric -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/autonumeric@4.8.0/dist/autoNumeric.min.js"></script>
<script>
$(document).ready(function(){
    // Inisialisasi AutoNumeric untuk elemen yang ada
    $('.currency-input').each(function(){
        new AutoNumeric(this, {
            digitGroupSeparator: '.',
            decimalCharacter: ',',
            decimalPlaces: 2,
            unformatOnSubmit: true
        });
    });
    
    // Fungsi untuk menghitung ulang total (abaikan baris dengan checkbox rapel dicentang)
    function recalcTotals(){
        let totalEarnings = 0, totalDeductions = 0;
        $('#selectedPayheadsTable tbody tr').each(function(){
            if ($(this).find('input.rapel-checkbox').prop('checked')) return;
            const jenis = ($(this).data('jenis') || "").toLowerCase();
            const valStr = $(this).find('.currency-input').val() || '0';
            const amount = parseFloat(valStr.replace(/\./g,'').replace(',', '.')) || 0;
            if(jenis === "earnings"){
                totalEarnings += amount;
            } else {
                totalDeductions += amount;
            }
        });
        const potonganAbsensi = <?= $potonganAbsensi ?>;
        const totalPotongan = totalDeductions + potonganAbsensi;
        const netSalary = <?= $gajiPokok ?> + <?= $salaryIndex ?> + totalEarnings - totalDeductions - potonganAbsensi;
        function toIDR(num){ return 'Rp ' + num.toLocaleString('id-ID',{minimumFractionDigits:2}); }
        $('#totalEarningsDisplay').text(toIDR(totalEarnings));
        $('#totalDeductionsDisplay').text(toIDR(totalPotongan));
        $('#gajiBersihDisplay').text(toIDR(netSalary));
    }
    
    $(document).on('input','.currency-input', function(){ recalcTotals(); });
    
    // Handler untuk checkbox rapel
    $(document).on('change', '.rapel-checkbox', function(){
        const $row = $(this).closest('tr');
        if($(this).prop('checked')){
            $row.data('oldNominal', $row.find('.currency-input').val());
            $row.data('oldRemarks', $row.find('input[name^="remarks"]').val());
            $row.find('.currency-input, input[name^="remarks"]').prop('disabled', true).val('Rapel');
        } else {
            $row.find('.currency-input').prop('disabled', false).val($row.data('oldNominal') || '');
            $row.find('input[name^="remarks"]').prop('disabled', false).val($row.data('oldRemarks') || '');
        }
        recalcTotals();
    });
    
    // Tambah payhead dari daftar "Tersedia"
    $(document).on('click','.btnAddPayhead', function(){
        const $item = $(this).closest('.payhead-item');
        const phId = $item.data('id'),
              phName = $item.find('.payhead-name').text(),
              phJenis = $item.data('jenis'),
              phNominal = parseFloat($item.data('nominal')) || 0;
        $item.remove();
        const rowCount = $('#selectedPayheadsTable tbody tr').length + 1;
        const nominalStr = phNominal.toFixed(2).replace('.',',');
        const newRow = `
            <tr data-id="${phId}" data-jenis="${phJenis}">
                <td>${rowCount}</td>
                <td>${phName}</td>
                <td><input type="text" class="form-control currency-input" name="pay_amounts[${phId}]" value="${nominalStr}" required></td>
                <td><input type="text" class="form-control" name="remarks[${phId}]" value=""></td>
                <td class="text-center"><input type="checkbox" class="rapel-checkbox" name="rapel[${phId}]"></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-danger btnRemovePayhead"><i class="bi bi-trash"></i></button></td>
            </tr>
        `;
        $('#selectedPayheadsTable tbody').append(newRow);
        $(newRow).find('.currency-input').each(function(){
            new AutoNumeric(this, {
                digitGroupSeparator: '.',
                decimalCharacter: ',',
                decimalPlaces: 2,
                unformatOnSubmit: true
            });
        });
        recalcTotals();
    });
    
    // Hapus payhead dari tabel "Terpilih" dan kembalikan ke daftar "Tersedia"
    $(document).on('click','.btnRemovePayhead', function(){
        const $row = $(this).closest('tr');
        const phId = $row.data('id'),
              phJenis = $row.data('jenis'),
              phName = $row.find('td:nth-child(2)').text(),
              valStr = $row.find('.currency-input').val() || '0';
        $row.remove();
        $('#selectedPayheadsTable tbody tr').each(function(i){ $(this).find('td:first').text(i+1); });
        const availableItem = `
            <div class="payhead-item" data-id="${phId}" data-nominal="${valStr.replace('.','').replace(',','.')}" data-jenis="${phJenis}">
                <button type="button" class="btn btn-sm btn-primary btnAddPayhead"><i class="bi bi-plus"></i></button>
                <span class="payhead-name">${phName}</span>
            </div>
        `;
        $('.available-payheads').append(availableItem);
        recalcTotals();
    });
    
    // Submit form Payroll (AssignPayheadsToEmployee)
    $('#payrollForm').on('submit', function(e){
        e.preventDefault();
        const formData = new FormData(this);
        $.ajax({
            url: 'employees.php?ajax=1',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function(){ $('button[type="submit"]').prop('disabled', true); },
            success: function(resp){
                $('button[type="submit"]').prop('disabled', false);
                if(resp.code === 0){
                    Swal.fire('Berhasil', resp.result, 'success');
                } else {
                    Swal.fire('Gagal', resp.result, 'error');
                }
            },
            error: function(xhr, status, error){
                $('button[type="submit"]').prop('disabled', false);
                Swal.fire('Error', 'Terjadi kesalahan: ' + error, 'error');
            }
        });
    });
    
    // Tombol Proses Payroll
    $('#btnProsesPayroll').click(function(){
        Swal.fire({
            title: 'Proses Payroll?',
            text: "Pastikan data sudah benar sebelum diproses.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Proses',
            cancelButtonText: 'Batal'
        }).then((result)=>{
            if(result.isConfirmed){
                $.ajax({
                    url: 'employees.php?ajax=1',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        case: 'ProcessPayroll',
                        id_anggota: <?= $id_anggota ?>,
                        selectedMonth: <?= $selectedMonth ?>,
                        selectedYear: <?= $selectedYear ?>,
                        csrf_token: '<?= htmlspecialchars($csrf_token) ?>'
                    },
                    success: function(resp){
                        if(resp.code === 0){
                            Swal.fire('Berhasil', resp.result, 'success').then(()=>{
                                window.location.href = 'employees.php';
                            });
                        } else {
                            Swal.fire('Gagal', resp.result, 'error');
                        }
                    },
                    error: function(xhr, status, error){
                        Swal.fire('Error', 'Terjadi kesalahan: ' + error, 'error');
                    }
                });
            }
        });
    });
    
    // Handler untuk tombol Rekap Absensi (Review)
    $(document).on('click', '.btnRekapAbsensi', function(){
        const id = $(this).data('id'),
              role = $(this).data('role'),
              bulan = $(this).data('bulan'),
              tahun = $(this).data('tahun');
        $.ajax({
            url: 'employees.php?ajax=1',
            type: 'POST',
            dataType: 'json',
            data: {
                case: 'ViewRekapAbsensi',
                id: id,
                selectedMonth: bulan,
                selectedYear: tahun,
                csrf_token: CSRF_TOKEN
            },
            success: function(resp){
                if(resp.code === 0){
                    const data = resp.result;
                    $("#review_id_anggota").text(data.id_anggota);
                    $("#review_bulan").text(data.bulan);
                    $("#review_tahun").text(data.tahun);
                    $("#review_total_hadir").text(data.total_hadir);
                    $("#review_total_izin").text(data.total_izin);
                    $("#review_total_cuti").text(data.total_cuti);
                    $("#review_total_tanpa_keterangan").text(data.total_tanpa_keterangan);
                    $("#review_total_sakit").text(data.total_sakit);
                    $('#rekap_id_anggota_edit').val(data.id_anggota);
                    $('#rekap_bulan_edit').val(data.bulan);
                    $('#rekap_tahun_edit').val(data.tahun);
                    $('#total_hadir_edit').val(data.total_hadir);
                    $('#total_izin_edit').val(data.total_izin);
                    $('#total_cuti_edit').val(data.total_cuti);
                    $('#total_tanpa_keterangan_edit').val(data.total_tanpa_keterangan);
                    $('#total_sakit_edit').val(data.total_sakit);
                    window.potonganAbsensiGlobal = <?= $potonganAbsensi ?>;
                    recalcTotals();
                    $('#rekapReviewModal').modal('show');
                } else {
                    Swal.fire('Gagal', 'Data rekap absensi tidak ditemukan.', 'error');
                }
            },
            error: function(){
                Swal.fire('Error', 'Terjadi kesalahan saat mengambil rekap absensi.', 'error');
            }
        });
    });
    
    // Handler tombol "Edit" pada modal review rekap absensi
    $('#btnOpenEditRekap').on('click', function(){
        $('#rekapReviewModal').modal('hide');
        $('#rekapAbsensiModal').modal('show');
    });
    
    // Submit form Edit Rekap Absensi
    $('#rekapAbsensiForm').on('submit', function(e){
        e.preventDefault();
        const form = $(this);
        $.ajax({
            url: 'employees.php?ajax=1',
            type: 'POST',
            dataType: 'json',
            data: form.serialize(),
            beforeSend: function(){
                form.find('button[type="submit"]').prop('disabled', true);
                form.find('.spinner-border').removeClass('d-none');
            },
            success: function(resp){
                form.find('button[type="submit"]').prop('disabled', false);
                form.find('.spinner-border').addClass('d-none');
                if(resp.code === 0){
                    Swal.fire('Berhasil', resp.result, 'success');
                    $('#rekapAbsensiModal').modal('hide');
                    // Opsional: refresh data rekap absensi di card jika diperlukan
                } else {
                    Swal.fire('Gagal', resp.result, 'error');
                }
            },
            error: function(){
                form.find('button[type="submit"]').prop('disabled', false);
                form.find('.spinner-border').addClass('d-none');
                Swal.fire('Error', 'Terjadi kesalahan saat menyimpan rekap absensi.', 'error');
            }
        });
    });
    
    // Kalkulasi awal
    recalcTotals();
});
</script>
</body>
</html>
