<?php
// upload_absensi.php

session_start();
require_once __DIR__ . '/../vendor/autoload.php'; // Sesuaikan path jika perlu
require_once __DIR__ . '/../koneksi.php';         // Koneksi database
require_once __DIR__ . '/../helpers.php';         // Tambah agar fungsi dropdown bisa dipakai!

authorize(['M:SDM']);
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

// === PATCH BEGIN: CSRF Token ===
generate_csrf_token();
// === PATCH END

/**
 * Fungsi untuk mengonversi string tanggal Excel "dd-mm-yyyy" ke format MySQL "yyyy-mm-dd".
 */
function convertStringTglToMysqlDate($str)
{
    $date = DateTime::createFromFormat('d-m-Y', $str);
    if ($date) {
        return $date->format('Y-m-d');
    }
    return null;
}

/**
 * Fungsi untuk parse jam ke format TIME "HH:ii:ss".
 */
function parseTimeString($timeStr)
{
    $timeStr = trim($timeStr);
    if ($timeStr === '') {
        return '00:00:00';
    }
    $formats = ['H:i:s', 'H:i', 'g:i A', 'g:i a', 'h:i A', 'h:i a'];
    foreach ($formats as $f) {
        $obj = DateTime::createFromFormat($f, $timeStr);
        if ($obj) {
            return $obj->format('H:i:s');
        }
    }
    return '00:00:00';
}

/**
 * Fungsi untuk membuat datetime "YYYY-MM-DD HH:ii:ss" dari (tanggal) + (string jam).
 */
function parseDateTimeString($mysqlDate, $timeStr)
{
    $timeFormatted = parseTimeString($timeStr);
    if (!$mysqlDate) {
        return '0000-00-00 00:00:00';
    }
    return $mysqlDate . ' ' . $timeFormatted;
}

/**
 * Fungsi untuk mengimpor data absensi dari file Excel.
 */
function impor_absensi($conn, $file, $departemen)
{
    // PATCH: Validasi file (ekstensi & MIME & size)
    $filePath = $file['tmp_name'] ?? '';
    $fileSize = $file['size'] ?? 0;
    $allowedMime = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
        'application/vnd.ms-excel' // .xls
    ];

    if (!file_exists($filePath) || $fileSize < 10) {
        return [
            'status' => 'error',
            'message' => "File tidak ditemukan atau gagal di-upload.",
            'total_rows' => 0,
            'success_count' => 0,
            'fail_count' => 0,
            'details' => []
        ];
    }
    $mime = mime_content_type($filePath);
    if (!in_array($mime, $allowedMime)) {
        return [
            'status' => 'error',
            'message' => "File yang di-upload bukan file Excel yang valid.",
            'total_rows' => 0,
            'success_count' => 0,
            'fail_count' => 0,
            'details' => []
        ];
    }
    if ($fileSize > 2 * 1024 * 1024) { // max 2MB
        return [
            'status' => 'error',
            'message' => "Ukuran file terlalu besar, maksimal 2 MB.",
            'total_rows' => 0,
            'success_count' => 0,
            'fail_count' => 0,
            'details' => []
        ];
    }

    // PATCH: Validasi departemen via helpers (getOrderedJenjang)
    $allowed_departemen = array_map('strtoupper', array_keys(getOrderedJenjang($conn, true)));
    $departemen_val_upper = strtoupper($departemen);
    if (!in_array($departemen_val_upper, $allowed_departemen)) {
        return [
            'status' => 'error',
            'message' => "Departemen yang dipilih tidak valid.",
            'total_rows' => 0,
            'success_count' => 0,
            'fail_count' => 0,
            'details' => []
        ];
    }

    $tabel_absensi = "absensi";
    $qcheck = "SHOW TABLES LIKE '$tabel_absensi'";
    $rcheck = mysqli_query($conn, $qcheck);
    if (mysqli_num_rows($rcheck) == 0) {
        return [
            'status' => 'error',
            'message' => "Tabel absensi tidak ditemukan.",
            'total_rows' => 0,
            'success_count' => 0,
            'fail_count' => 0,
            'details' => []
        ];
    }

    // Baca file Excel
    try {
        $reader = IOFactory::createReaderForFile($filePath);
        $spreadsheet = $reader->load($filePath);
    } catch (ReaderException $e) {
        return [
            'status' => 'error',
            'message' => "Gagal membaca file Excel: " . $e->getMessage(),
            'total_rows' => 0,
            'success_count' => 0,
            'fail_count' => 0,
            'details' => []
        ];
    }

    $sheet = $spreadsheet->getActiveSheet();
    $data = $sheet->toArray();

    if (empty($data) || count($data) <= 2) {
        return [
            'status' => 'error',
            'message' => "File Excel kosong atau format tidak sesuai.",
            'total_rows' => 0,
            'success_count' => 0,
            'fail_count' => 0,
            'details' => []
        ];
    }

    // Hapus baris info & header
    array_shift($data);
    $header = array_shift($data);

    // ======== PATCH: Mapping Header dinamis pakai JSON ========
    $mappingFile = __DIR__ . '/includes/mapping_template.json';
    if (!file_exists($mappingFile)) {
        return [
            'status' => 'error',
            'message' => "File mapping_template.json tidak ditemukan di folder includes.",
            'total_rows' => 0,
            'success_count' => 0,
            'fail_count' => 0,
            'details' => []
        ];
    }
    $mapping = json_decode(file_get_contents($mappingFile), true);
    if (!$mapping || !is_array($mapping)) {
        return [
            'status' => 'error',
            'message' => "Format file mapping_template.json tidak valid.",
            'total_rows' => 0,
            'success_count' => 0,
            'fail_count' => 0,
            'details' => []
        ];
    }

    // Buat mapping: kolom excel => nama field DB
    $colMap = [];
    foreach ($header as $idx => $colName) {
        $colNameNorm = trim($colName);
        if (isset($mapping[$colNameNorm])) {
            $colMap[$mapping[$colNameNorm]] = $idx;
        }
    }

    // Field wajib
    $wajib = ['tanggal', 'nip', 'nama', 'scan_masuk', 'scan_pulang'];
    $missing = [];
    foreach ($wajib as $w) {
        if (!isset($colMap[$w])) $missing[] = $w;
    }
    if (!empty($missing)) {
        return [
            'status' => 'error',
            'message' => "Header Excel kurang kolom wajib: " . implode(', ', $missing),
            'total_rows' => 0,
            'success_count' => 0,
            'fail_count' => 0,
            'details' => []
        ];
    }
    // ======== END PATCH ========

    // PATCH: BEGIN Transaction
    $conn->begin_transaction();

    $type_string = 'sssissssissssssssi';

    $sql_insert = "
        INSERT INTO `absensi`
        (tanggal, jadwal, jam_kerja, valid, pin, nip, nama, departemen,
         lembur, jam_masuk, scan_masuk, terlambat, scan_istirahat_1,
         scan_istirahat_2, jam_pulang, scan_pulang, status_kehadiran, id_anggota)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt = mysqli_prepare($conn, $sql_insert);
    if (!$stmt) {
        $conn->rollback();
        return [
            'status' => 'error',
            'message' => "Gagal menyiapkan statement SQL: " . mysqli_error($conn),
            'total_rows' => 0,
            'success_count' => 0,
            'fail_count' => 0,
            'details' => []
        ];
    }

    $sql_get_id = "SELECT id FROM anggota_sekolah WHERE nip = ?";
    $stmt_get_id = mysqli_prepare($conn, $sql_get_id);
    if (!$stmt_get_id) {
        $conn->rollback();
        return [
            'status' => 'error',
            'message' => "Gagal menyiapkan statement pengambilan ID anggota: " . mysqli_error($conn),
            'total_rows' => 0,
            'success_count' => 0,
            'fail_count' => 0,
            'details' => []
        ];
    }

    $totalRows    = count($data);
    $successCount = 0;
    $failCount    = 0;
    $details      = [];
    $rowNumber    = 3;

    $affected_periods = [];

    foreach ($data as $row) {
        // --- PATCH: Ambil data dengan mapping ---
        $tanggal_str    = isset($colMap['tanggal']) ? $row[$colMap['tanggal']] : '';
        $jadwal         = isset($colMap['jadwal']) ? $row[$colMap['jadwal']] : '';
        $jam_kerja      = isset($colMap['jam_kerja']) ? $row[$colMap['jam_kerja']] : '';
        $valid          = isset($colMap['valid']) ? $row[$colMap['valid']] : '';
        $pin            = isset($colMap['pin']) ? $row[$colMap['pin']] : '';
        $nip            = isset($colMap['nip']) ? trim($row[$colMap['nip']]) : '';
        $nama           = isset($colMap['nama']) ? $row[$colMap['nama']] : '';
        $lembur         = isset($colMap['lembur']) ? $row[$colMap['lembur']] : '';
        $jam_masuk_raw  = isset($colMap['jam_masuk']) ? $row[$colMap['jam_masuk']] : '';
        $scan_masuk_raw = isset($colMap['scan_masuk']) ? $row[$colMap['scan_masuk']] : '';
        $terlambat      = isset($colMap['terlambat']) ? $row[$colMap['terlambat']] : '';
        $scan_i1_raw    = isset($colMap['scan_istirahat_1']) ? $row[$colMap['scan_istirahat_1']] : '';
        $scan_i2_raw    = isset($colMap['scan_istirahat_2']) ? $row[$colMap['scan_istirahat_2']] : '';
        $jam_pulang_raw = isset($colMap['jam_pulang']) ? $row[$colMap['jam_pulang']] : '';
        $scan_pulang_raw = isset($colMap['scan_pulang']) ? $row[$colMap['scan_pulang']] : '';

        $tanggal = convertStringTglToMysqlDate($tanggal_str);
        if (!$tanggal) {
            $failCount++;
            $details[] = [
                'row' => $rowNumber,
                'status' => 'fail',
                'reason' => "Format tanggal tidak valid: '$tanggal_str'."
            ];
            $rowNumber++;
            continue;
        }

        $jam_masuk  = parseTimeString($jam_masuk_raw);
        $jam_pulang = parseTimeString($jam_pulang_raw);
        $scan_masuk       = parseDateTimeString($tanggal, $scan_masuk_raw);
        $scan_istirahat_1 = parseDateTimeString($tanggal, $scan_i1_raw);
        $scan_istirahat_2 = parseDateTimeString($tanggal, $scan_i2_raw);
        $scan_pulang      = parseDateTimeString($tanggal, $scan_pulang_raw);

        $valid     = intval($valid) ?: 0;
        $lembur    = intval($lembur) ?: 0;
        $terlambat = intval($terlambat) ?: 0;
        $valid     = ($valid === 1) ? 1 : 0;
        $status_kehadiran = 'hadir';

        mysqli_stmt_bind_param($stmt_get_id, 's', $nip);
        mysqli_stmt_execute($stmt_get_id);
        $result_id = mysqli_stmt_get_result($stmt_get_id);
        $row_id    = mysqli_fetch_assoc($result_id);
        mysqli_stmt_reset($stmt_get_id);

        if (!$row_id || empty($row_id['id'])) {
            $failCount++;
            $details[] = [
                'row' => $rowNumber,
                'status' => 'fail',
                'reason' => "NIP '$nip' tidak ditemukan di tabel anggota_sekolah."
            ];
            $rowNumber++;
            continue;
        }
        $id_anggota = intval($row_id['id']);

        // PATCH: Cek duplikasi (pastikan ada UNIQUE INDEX di DB)
        $sql_cek = "SELECT id FROM absensi WHERE tanggal = ? AND nip = ?";
        $stmt_cek = $conn->prepare($sql_cek);
        $stmt_cek->bind_param("ss", $tanggal, $nip);
        $stmt_cek->execute();
        $res_cek = $stmt_cek->get_result();
        if ($res_cek->num_rows > 0) {
            $stmt_cek->close();
            $failCount++;
            $details[] = [
                'row' => $rowNumber,
                'status' => 'fail',
                'reason' => "Data absensi untuk tanggal $tanggal dan NIP $nip sudah ada. Diabaikan (duplikat)."
            ];
            $rowNumber++;
            continue;
        }
        $stmt_cek->close();

        mysqli_stmt_bind_param(
            $stmt,
            $type_string,
            $tanggal, $jadwal, $jam_kerja, $valid, $pin, $nip, $nama, $departemen_val_upper,
            $lembur, $jam_masuk, $scan_masuk, $terlambat, $scan_istirahat_1, $scan_istirahat_2, $jam_pulang, $scan_pulang,
            $status_kehadiran, $id_anggota
        );
        if (mysqli_stmt_execute($stmt)) {
            $successCount++;
            $details[] = [
                'row' => $rowNumber,
                'status' => 'success',
                'reason' => ''
            ];
            $bulan = date('n', strtotime($tanggal));
            $tahun = date('Y', strtotime($tanggal));
            $key = "{$id_anggota}-{$bulan}-{$tahun}";
            if (!isset($affected_periods[$key])) {
                $affected_periods[$key] = [
                    'id_anggota' => $id_anggota,
                    'bulan'      => $bulan,
                    'tahun'      => $tahun
                ];
            }
        } else {
            $failCount++;
            $details[] = [
                'row' => $rowNumber,
                'status' => 'fail',
                'reason' => mysqli_stmt_error($stmt)
            ];
            error_log("Gagal mengimpor baris $rowNumber: " . mysqli_stmt_error($stmt));
        }
        $rowNumber++;
    }

    mysqli_stmt_close($stmt);
    mysqli_stmt_close($stmt_get_id);

    // PATCH: Commit transaksi
    $conn->commit();

    foreach ($affected_periods as $period) {
        $stmt_rekap = $conn->prepare("CALL UpdateRekapAbsensi(?, ?, ?)");
        $stmt_rekap->bind_param(
            "iii",
            $period['id_anggota'],
            $period['bulan'],
            $period['tahun']
        );
        $stmt_rekap->execute();
        $stmt_rekap->close();
    }

    $dates = [];
    foreach ($affected_periods as $period) {
        $dates[] = date('Y-m-d', strtotime($period['tahun'] . '-' . $period['bulan'] . '-01'));
    }
    if (!empty($dates)) {
        $min_date = min($dates);
        $max_date = max($dates);

        $stmt_rekap = $conn->prepare("CALL UpdateRekapMingguan(?, ?)");
        $stmt_rekap->bind_param("ss", $min_date, $max_date);
        $stmt_rekap->execute();

        add_audit_log(
            $conn,
            $_SESSION['nip'] ?? 'system',
            'UpdateRekapMingguan',
            "Update rekap dari $min_date hingga $max_date"
        );
    }

    return [
        'status' => 'ok',
        'message' => "Proses impor selesai.",
        'total_rows' => $totalRows,
        'success_count' => $successCount,
        'fail_count' => $failCount,
        'details' => $details,
        'rekap_periode' => [$min_date ?? null, $max_date ?? null]
    ];
}


// ===========================
// Handle form submission
// ===========================
$message = '';
$logDetails = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // === PATCH: CSRF VALIDATION ===
    verify_csrf_token($_POST['csrf_token'] ?? '');
    // === PATCH END
    $departemen = $_POST['departemen'] ?? '';
    $file = $_FILES['file_absensi'] ?? null;

    if ($file && $departemen) {
        $hasilImport = impor_absensi($conn, $file, $departemen);
        if ($hasilImport['status'] === 'ok') {
            $message = "Proses impor selesai. " .
                "Total baris: " . $hasilImport['total_rows'] .
                ", Sukses: " . $hasilImport['success_count'] .
                ", Gagal: " . $hasilImport['fail_count'];

            if (!empty($hasilImport['details']) && is_array($hasilImport['details'])) {
                $logDetails = $hasilImport['details'];
            } else {
                $logDetails = [];
                $message .= " Namun, detail impor tidak tersedia.";
            }
        } else {
            $message = $hasilImport['message'];
        }
    } else {
        $message = 'Departemen atau file tidak valid!';
    }
}

// --- Baca mapping_template.json untuk template preview (sekali render) ---
$mappingFile = __DIR__ . '/includes/mapping_template.json';
$templateHeaders = [];
if (file_exists($mappingFile)) {
    $mapping = json_decode(file_get_contents($mappingFile), true);
    if ($mapping && is_array($mapping)) {
        $templateHeaders = array_keys($mapping);
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Upload Absensi</title>
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.6/dist/sweetalert2.min.css">
    <!-- Custom styles (sesuaikan dengan kebutuhan) -->
    <style>
        body {
            padding-top: 20px;
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

        #main-content {
            transition: opacity 0.3s ease;
        }

        .back-btn {
            margin-bottom: 20px;
            transition: background-color 0.3s, transform 0.2s;
        }

        .back-btn:hover {
            transform: scale(1.05);
        }

        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }

        .table-hover tbody tr:hover {
            background-color: #e2e6ea;
        }

        ::placeholder {
            color: #6c757d;
            opacity: 1;
        }

        .spinner-border {
            vertical-align: middle;
        }

            .template-table th, .template-table td {
        padding: 6px 8px !important;
        font-size: 0.96rem;
        white-space: nowrap;
    }
    .template-table th {
        background: #e3f0ff;
        font-weight: 600;
        border-top: 2px solid #9dc1f6;
    }
    @media (min-width: 1200px) {
        .modal-xl {
            --bs-modal-width: 95vw; /* Lebih lebar di layar besar */
        }
    }
    </style>
</head>

<body id="page-top">
    <!-- Container Utama Tanpa Sidebar/Navbar -->
    <div class="container" id="main-content">
        <!-- Tombol Back -->
        <button class="btn btn-secondary back-btn" id="btnBack" data-href="/payroll_absensi_v2/sdm/koreksi_absensi.php" title="Kembali">
            <i class="fas fa-arrow-left"></i> Kembali
        </button>

        <!-- Header Halaman -->
        <h1 class="page-title">
            <i class="fas fa-upload"></i> Upload Absensi
        </h1>
        </h1>

        <!-- Notifikasi Rekap Mingguan -->
        <?php if (!empty($hasilImport['rekap_periode'])): ?>
            <div class="alert alert-success mt-3">
                <i class="fas fa-calendar-check"></i> Rekap mingguan telah diperbarui:<br>
                Periode: <?= date('d M Y', strtotime($hasilImport['rekap_periode'][0])) ?> -
                <?= date('d M Y', strtotime($hasilImport['rekap_periode'][1])) ?>
            </div>
        <?php endif; ?>

        <!-- === Panduan Statis (Card) === -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-question-circle"></i> Panduan Upload Absensi</h5>
            </div>
            <div class="card-body" style="color:#000;">
                <ul class="mb-0">
                    <li><strong>Pilih Departemen</strong> sesuai jenjang (TK/SD/SMP/SMA/SMK).</li>
                    <li><strong>Pilih File Excel</strong> hasil export fingerprint (.xls/.xlsx).</li>
                    <li><strong>Klik</strong> <kbd>Upload &amp; Import</kbd> → tunggu proses selesai.</li>
                    <li>Periksa ringkasan Total Baris / Sukses / Gagal di atas form.</li>
                    <li>Jika gagal, periksa format kolom tanggal (dd-mm-yyyy) &amp; jam di file Excel.</li>
                </ul>
            </div>
        </div>
        <!-- === End Panduan === -->

        <!-- Form Upload Absensi -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h6 class="m-0">Form Upload Absensi</h6>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="mb-3">
                        <label for="departemen" class="form-label">Pilih Departemen</label>
                        <select name="departemen" id="departemen" class="form-select" required>
    <option value="">--Pilih--</option>
    <?php
    // PATCH: gunakan getOrderedJenjang() dari helpers
    $jenjangList = getOrderedJenjang($conn, true);
    foreach ($jenjangList as $kode => $nama):
    ?>
        <option value="<?= htmlspecialchars($kode) ?>">
            <?= htmlspecialchars($nama) ?>
        </option>
    <?php endforeach; ?>
</select>
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    </div>
                    <div class="mb-3">
                        <label for="file_absensi" class="form-label">Upload File Absensi (Excel, .xls/.xlsx)</label>
                        <input type="file" name="file_absensi" id="file_absensi" class="form-control" accept=".xls,.xlsx" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload & Import
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                    <button type="button" class="btn btn-outline-info" id="btnShowTemplate">
        <i class="fas fa-table"></i> Lihat Template
    </button>
                </form>
            </div>
        </div>

        <!-- Detail Import (jika ada) -->
        <?php if (!empty($logDetails) && is_iterable($logDetails)): ?>
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 class="m-0">Detail Import</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle text-center mb-0 template-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Baris (tanpa header)</th>
                                    <th>Status</th>
                                    <th>Alasan</th>
                                </tr>
                            </thead>
                            <tfoot>
                                <tr>
                                    <th>Baris (tanpa header)</th>
                                    <th>Status</th>
                                    <th>Alasan</th>
                                </tr>
                            </tfoot>
                            <tbody>
                                <?php foreach ($logDetails as $detail): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($detail['row']) ?></td>
                                        <td>
                                            <?php if ($detail['status'] === 'success'): ?>
                                                <span class="badge bg-success">Sukses</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Gagal</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($detail['reason']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
<!-- Modal Bootstrap untuk preview template -->
<div class="modal fade" id="templateModal" tabindex="-1" aria-labelledby="templateModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered"> <!-- ubah dari modal-lg ke modal-xl -->
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="templateModalLabel"><i class="fas fa-table"></i> Struktur Template Excel Absensi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info mb-3">
          <i class="fas fa-info-circle"></i> <b>Gunakan urutan dan nama kolom berikut di baris pertama Excel Anda:</b>
        </div>
        <div class="table-responsive">
          <table class="table table-bordered align-middle text-center mb-0 template-table">
            <thead class="table-primary">
              <tr>
                <?php foreach ($templateHeaders as $col): ?>
                  <th><?= htmlspecialchars($col) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <tr>
                <?php foreach ($templateHeaders as $col): ?>
                  <td><em>(isi data)</em></td>
                <?php endforeach; ?>
              </tr>
            </tbody>
          </table>
        </div>
        <small class="text-muted">
          Kolom <b>wajib:</b> <?= htmlspecialchars(implode(', ', ['tanggal', 'nip', 'nama', 'scan_masuk', 'scan_pulang'])) ?>
        </small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
    </div>
    <!-- End Container Utama -->

    <!-- Loading Spinner (opsional) -->
    <div id="loadingSpinner" style="display:none; position: fixed; z-index: 9999; top: 0; left: 0; bottom: 0; right: 0; margin: auto;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <!-- DataTables JS (opsional jika diperlukan) -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.6/dist/sweetalert2.all.min.js"></script>
    <!-- Script custom -->
    <script>
        $(document).ready(function() {
            // === Notifikasi Import Absensi (SweetAlert2) ===
    <?php if (!empty($message)): ?>
        Swal.fire({
            icon: '<?= (isset($hasilImport) && $hasilImport['status']==='ok') ? 'success' : 'error' ?>',
            title: <?= json_encode((isset($hasilImport) && $hasilImport['status']==='ok') ? 'Berhasil!' : 'Gagal!') ?>,
            html: <?= json_encode($message) ?>,
            confirmButtonColor: '#1976d2',
            customClass: {
                title: 'swal2-title mb-2',
                popup: 'swal2-popup swal2-popup-wide'
            }
        });
    <?php endif; ?>

            // Back button dengan transisi smooth
            $('#btnBack').on('click', function(e) {
                e.preventDefault();
                var url = $(this).data('href');
                $('#main-content').fadeOut(300, function() {
                    window.location.href = url;
                });
            });

            // Show modal saat tombol diklik
    $('#btnShowTemplate').on('click', function() {
        $('#templateModal').modal('show');
    });
            // Inisialisasi DataTables untuk #logTable (jika tabel import detail ingin interaktif)
            $('#logTable').DataTable({
                "language": {
                    "url": "https://cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
                },
                "dom": 'Bfrtip',
                "buttons": [{
                        extend: 'copyHtml5',
                        text: '<i class="fas fa-copy"></i> Copy',
                        className: 'btn btn-secondary btn-sm'
                    },
                    {
                        extend: 'excelHtml5',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn btn-success btn-sm'
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn btn-danger btn-sm'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Print',
                        className: 'btn btn-info btn-sm'
                    },
                    {
                        extend: 'colvis',
                        text: '<i class="fas fa-columns"></i> Kolom',
                        className: 'btn btn-warning btn-sm'
                    }
                ],
                "responsive": true,
                "pageLength": 10
            });

            // Tampilkan spinner saat form dikirim
            $('#uploadForm').on('submit', function(e) {
                var $btn = $(this).find('button[type="submit"]');
                $btn.prop('disabled', true);
                $btn.find('.spinner-border').removeClass('d-none');
            });

            // Fade out alert setelah 3 detik
            window.setTimeout(function() {
                $(".alert").fadeTo(500, 0).slideUp(500, function() {
                    $(this).remove();
                });
            }, 3000);
        });
    </script>
</body>

</html>