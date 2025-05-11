<?php
// upload_absensi.php

session_start();
require_once __DIR__ . '/../vendor/autoload.php'; // Sesuaikan path jika perlu
require_once __DIR__ . '/../koneksi.php';         // Koneksi database

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

/**
 * Fungsi untuk mengonversi string tanggal Excel "dd-mm-yyyy" ke format MySQL "yyyy-mm-dd".
 */
function convertStringTglToMysqlDate($str)
{
    // Misal di Excel tulis "09-12-2024" → "2024-12-09"
    // Jika formatnya berbeda, sesuaikan DateTime::createFromFormat()
    $date = DateTime::createFromFormat('d-m-Y', $str);
    if ($date) {
        return $date->format('Y-m-d');
    }
    return null;
}

/**
 * Fungsi untuk parse jam ke format TIME "HH:ii:ss".
 * Mencoba beberapa pola umum (e.g. "6:28:18", "6:28 AM", "13:05:22" dll.)
 */
function parseTimeString($timeStr)
{
    $timeStr = trim($timeStr);
    if ($timeStr === '') {
        return '00:00:00';
    }
    // Coba beberapa format
    $formats = ['H:i:s', 'H:i', 'g:i A', 'g:i a', 'h:i A', 'h:i a'];
    foreach ($formats as $f) {
        $obj = DateTime::createFromFormat($f, $timeStr);
        if ($obj) {
            return $obj->format('H:i:s');
        }
    }
    // Jika gagal parse, jadikan 00:00:00
    return '00:00:00';
}

/**
 * Fungsi untuk membuat datetime "YYYY-MM-DD HH:ii:ss" dari (tanggal) + (string jam).
 */
function parseDateTimeString($mysqlDate, $timeStr)
{
    // $mysqlDate contohnya "2024-12-09"
    // $timeStr contohnya "6:28 AM"
    $timeFormatted = parseTimeString($timeStr); // ex: "06:28:00"
    if (!$mysqlDate) {
        // Kalau tanggal invalid, fallback
        return '0000-00-00 00:00:00';
    }
    return $mysqlDate . ' ' . $timeFormatted; // "2024-12-09 06:28:00"
}

/**
 * Fungsi untuk mengimpor data absensi dari file Excel.
 * Departemen diisi dari form, bukan dari Excel.
 */
function impor_absensi($conn, $file, $departemen)
{
    // Validasi departemen yang dipilih dari form
    $allowed_departemen = ['TK', 'SD', 'SMP', 'SMA', 'SMK'];
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

    // Periksa apakah tabel absensi ada
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

    // Periksa file
    $filePath = $file['tmp_name'] ?? '';
    if (!file_exists($filePath)) {
        return [
            'status' => 'error',
            'message' => "File tidak ditemukan atau gagal di-upload.",
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

    // Periksa apakah ada data
    if (empty($data) || count($data) <= 2) {
        // Minimal 3 baris (Row 1: info, Row 2: header, Row 3+: data)
        return [
            'status' => 'error',
            'message' => "File Excel kosong atau format tidak sesuai.",
            'total_rows' => 0,
            'success_count' => 0,
            'fail_count' => 0,
            'details' => []
        ];
    }

    // Hapus baris pertama (Row 1, misal berisi info)
    array_shift($data);

    // Hapus header (Row 2)
    $header = array_shift($data);

    // Normalisasi nama header
    $normalized_header = array_map(function ($col_name) {
        return strtolower(trim($col_name));
    }, $header);

    // Definisikan alias kolom untuk setiap field
    // *Tanpa* departemen, karena diisi dari form
    $field_aliases = [
        'tanggal'           => ['tanggal'],
        'jadwal'            => ['jadwal'],
        'jam kerja'         => ['jam kerja'],
        'valid'             => ['valid'],
        'pin'               => ['pin'],
        'nip'               => ['nip'],
        'nama'              => ['nama'],
        'lembur'            => ['lembur'],
        'jam masuk'         => ['jam masuk'],
        'scan masuk'        => ['scan masuk'],
        'terlambat'         => ['terlambat'],
        'scan istirahat_1'  => ['scan istirahat 1'],
        'scan istirahat_2'  => ['scan istirahat 2'],
        'jam pulang'        => ['jam pulang'],
        'scan pulang'       => ['scan pulang']
    ];

    // Kumpulkan indeks kolom
    $possibleIndexes = [];
    foreach ($normalized_header as $index => $col_name) {
        foreach ($field_aliases as $field => $possible_names) {
            $possible_names_lower = array_map('strtolower', $possible_names);
            if (in_array($col_name, $possible_names_lower)) {
                $possibleIndexes[$field][] = $index;
            }
        }
    }

    // Fungsi untuk memilih satu kolom paling sesuai (jika duplikat)
    function _chooseBestColumn(array $columnIndexes, array $dataRows)
    {
        if (count($columnIndexes) === 0) {
            return null;
        }
        if (count($columnIndexes) === 1) {
            return $columnIndexes[0];
        }

        // Pilih kolom dengan sel non-kosong terbanyak
        $bestIndex = null;
        $bestNonEmptyCount = -1;
        foreach ($columnIndexes as $col) {
            $nonEmptyCount = 0;
            foreach ($dataRows as $row) {
                $val = isset($row[$col]) ? trim($row[$col]) : '';
                if ($val !== '') {
                    $nonEmptyCount++;
                }
            }
            if ($nonEmptyCount > $bestNonEmptyCount) {
                $bestNonEmptyCount = $nonEmptyCount;
                $bestIndex = $col;
            }
        }
        return $bestIndex;
    }

    // Tentukan final $expected_headers
    $expected_headers = array_fill_keys(array_keys($field_aliases), null);
    foreach ($expected_headers as $field => $nullVal) {
        $colCandidates = $possibleIndexes[$field] ?? [];
        $chosenIndex   = _chooseBestColumn($colCandidates, $data);
        $expected_headers[$field] = $chosenIndex;
    }

    // Cek apakah ada field penting yang tidak berhasil ditemukan
    $missing_headers = [];
    foreach ($expected_headers as $field => $pos) {
        if ($pos === null) {
            $missing_headers[] = ucfirst($field);
        }
    }

    if (!empty($missing_headers)) {
        return [
            'status' => 'error',
            'message' => "Header kolom Excel tidak sesuai. Kolom yang hilang: " . implode(', ', $missing_headers),
            'total_rows' => 0,
            'success_count' => 0,
            'fail_count' => 0,
            'details' => []
        ];
    }

    // Siapkan statement INSERT
    $sql_insert = "
        INSERT INTO $tabel_absensi
        (tanggal, jadwal, jam_kerja, valid, pin, nip, nama, departemen,
         lembur, jam_masuk, scan_masuk, terlambat, scan_istirahat_1,
         scan_istirahat_2, jam_pulang, scan_pulang, id_anggota)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt = mysqli_prepare($conn, $sql_insert);
    if (!$stmt) {
        return [
            'status' => 'error',
            'message' => "Gagal menyiapkan statement SQL: " . mysqli_error($conn),
            'total_rows' => 0,
            'success_count' => 0,
            'fail_count' => 0,
            'details' => []
        ];
    }

    // Statement untuk cek dan mengambil id_anggota dari anggota_sekolah
    $sql_get_id = "SELECT id FROM anggota_sekolah WHERE nip = ?";
    $stmt_get_id = mysqli_prepare($conn, $sql_get_id);
    if (!$stmt_get_id) {
        return [
            'status' => 'error',
            'message' => "Gagal menyiapkan statement pengambilan ID anggota: " . mysqli_error($conn),
            'total_rows' => 0,
            'success_count' => 0,
            'fail_count' => 0,
            'details' => []
        ];
    }

    // Tipe data bind (17 kolom, dengan id_anggota sebagai integer)
    $type_string = 'sssissssississssi'; // 's' untuk string, 'i' untuk integer

    $totalRows    = count($data);
    $successCount = 0;
    $failCount    = 0;
    $details      = [];
    $rowNumber    = 3; // baris data mulai dari row ke-3

    // Array untuk menyimpan periode terpengaruh (untuk update rekap)
    $affected_periods = [];

    foreach ($data as $row) {
        // Ekstrak data dari masing-masing kolom berdasarkan $expected_headers
        $tanggal_str    = isset($row[$expected_headers['tanggal']]) ? $row[$expected_headers['tanggal']] : '';
        $jadwal         = isset($row[$expected_headers['jadwal']]) ? $row[$expected_headers['jadwal']] : '';
        $jam_kerja      = isset($row[$expected_headers['jam kerja']]) ? $row[$expected_headers['jam kerja']] : '';
        $valid          = isset($row[$expected_headers['valid']]) ? $row[$expected_headers['valid']] : '';
        $pin            = isset($row[$expected_headers['pin']]) ? $row[$expected_headers['pin']] : '';
        $nip            = isset($row[$expected_headers['nip']]) ? trim($row[$expected_headers['nip']]) : '';
        $nama           = isset($row[$expected_headers['nama']]) ? $row[$expected_headers['nama']] : '';
        // departemen → dari form, sudah diubah ke uppercase sebelumnya
        $lembur         = isset($row[$expected_headers['lembur']]) ? $row[$expected_headers['lembur']] : '';
        $jam_masuk_raw  = isset($row[$expected_headers['jam masuk']]) ? $row[$expected_headers['jam masuk']] : '';
        $scan_masuk_raw = isset($row[$expected_headers['scan masuk']]) ? $row[$expected_headers['scan masuk']] : '';
        $terlambat      = isset($row[$expected_headers['terlambat']]) ? $row[$expected_headers['terlambat']] : '';
        $scan_i1_raw    = isset($row[$expected_headers['scan istirahat_1']]) ? $row[$expected_headers['scan istirahat_1']] : '';
        $scan_i2_raw    = isset($row[$expected_headers['scan istirahat_2']]) ? $row[$expected_headers['scan istirahat_2']] : '';
        $jam_pulang_raw = isset($row[$expected_headers['jam pulang']]) ? $row[$expected_headers['jam pulang']] : '';
        $scan_pulang_raw = isset($row[$expected_headers['scan pulang']]) ? $row[$expected_headers['scan pulang']] : '';

        // (1) Konversi tanggal
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

        // (2) Konversi jam/time
        $jam_masuk  = parseTimeString($jam_masuk_raw);            // format hh:mm:ss
        $jam_pulang = parseTimeString($jam_pulang_raw);

        // (3) Konversi scan/datetime (gabungkan tanggal + jam)
        $scan_masuk       = parseDateTimeString($tanggal, $scan_masuk_raw);      // yyyy-mm-dd hh:mm:ss
        $scan_istirahat_1 = parseDateTimeString($tanggal, $scan_i1_raw);
        $scan_istirahat_2 = parseDateTimeString($tanggal, $scan_i2_raw);
        $scan_pulang      = parseDateTimeString($tanggal, $scan_pulang_raw);

        // (4) Konversi valid, lembur, terlambat ke integer
        $valid     = intval($valid) ?: 0;  // 0 atau 1
        $lembur    = intval($lembur) ?: 0;
        $terlambat = intval($terlambat) ?: 0;

        // Pastikan valid hanya bernilai 0 atau 1
        $valid     = ($valid === 1) ? 1 : 0;

        // (5) Cek dan ambil id_anggota dari anggota_sekolah berdasarkan nip
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

        // Binding parameter untuk statement INSERT
        mysqli_stmt_bind_param(
            $stmt,
            $type_string,
            $tanggal,             // DATE
            $jadwal,              // VARCHAR
            $jam_kerja,           // VARCHAR
            $valid,               // TINYINT
            $pin,                 // VARCHAR
            $nip,                 // VARCHAR
            $nama,                // VARCHAR
            $departemen_val_upper, // VARCHAR (diisi dari form)
            $lembur,              // TINYINT
            $jam_masuk,           // TIME
            $scan_masuk,          // DATETIME
            $terlambat,           // TINYINT
            $scan_istirahat_1,    // DATETIME
            $scan_istirahat_2,    // DATETIME
            $jam_pulang,          // TIME
            $scan_pulang,         // DATETIME
            $id_anggota           // INT (foreign key)
        );

        // Eksekusi statement INSERT
        if (mysqli_stmt_execute($stmt)) {
            $successCount++;
            $details[] = [
                'row' => $rowNumber,
                'status' => 'success',
                'reason' => ''
            ];

            // Catat periode yang terpengaruh
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

    // Update rekap absensi untuk setiap periode yang terpengaruh
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

    // ================== MODIFIKASI DI BAWAH INI ==================
    // [1] Kumpulkan semua tanggal yang berhasil diimpor
    $dates = [];
    foreach ($affected_periods as $period) {
        $dates[] = date('Y-m-d', strtotime($period['tahun'] . '-' . $period['bulan'] . '-01'));
    }

    // [2] Cari rentang tanggal terbaru
    if (!empty($dates)) {
        $min_date = min($dates);
        $max_date = max($dates);

        // [3] Panggil UpdateRekapMingguan
        $stmt_rekap = $conn->prepare("CALL UpdateRekapMingguan(?, ?)");
        $stmt_rekap->bind_param("ss", $min_date, $max_date);
        $stmt_rekap->execute();

        // Tambahkan log audit untuk tracking (pastikan fungsi add_audit_log() sudah ada)
        add_audit_log(
            $conn,
            $_SESSION['nip'] ?? 'system',
            'UpdateRekapMingguan',
            "Update rekap dari $min_date hingga $max_date"
        );
    }
    // ================== MODIFIKASI DI ATAS INI ==================

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
    text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
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
        <i class="fas fa-upload"></i> Upload Absensi</h1>
    </h1>
        <!-- Notifikasi -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle"></i> <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

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
                            <option value="TK">TK</option>
                            <option value="SD">SD</option>
                            <option value="SMP">SMP</option>
                            <option value="SMA">SMA</option>
                            <option value="SMK">SMK</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="file_absensi" class="form-label">Upload File Absensi (Excel, .xls/.xlsx)</label>
                        <input type="file" name="file_absensi" id="file_absensi" class="form-control" accept=".xls,.xlsx" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload & Import
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
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
                        <table class="table table-bordered table-hover" id="logTable" width="100%" cellspacing="0">
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
    <!-- Script custom -->
    <script>
        $(document).ready(function() {
            // Back button dengan transisi smooth
            $('#btnBack').on('click', function(e) {
                e.preventDefault();
                var url = $(this).data('href');
                $('#main-content').fadeOut(300, function() {
                    window.location.href = url;
                });
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