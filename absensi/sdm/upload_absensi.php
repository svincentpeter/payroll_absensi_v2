<?php
// upload_absensi.php

session_start();
require_once __DIR__ . '/../../vendor/autoload.php'; // Sesuaikan path jika perlu
require_once __DIR__ . '/../../koneksi.php';         // Koneksi database

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

/**
 * Fungsi untuk mengonversi string tanggal Excel "dd-mm-yyyy" ke format MySQL "yyyy-mm-dd".
 */
function convertStringTglToMysqlDate($str) {
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
function parseTimeString($timeStr) {
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
function parseDateTimeString($mysqlDate, $timeStr) {
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
function impor_absensi($conn, $file, $departemen) {
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
    $normalized_header = array_map(function($col_name) {
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
    function _chooseBestColumn(array $columnIndexes, array $dataRows) {
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

    // Tipe data bind (17 kolom, dengan id_anggota as integer)
    $type_string = 'sssissssississssi'; // 's' untuk string, 'i' untuk integer

    $totalRows    = count($data);
    $successCount = 0;
    $failCount    = 0;
    $details      = [];
    $rowNumber    = 3; // baris data mulai row ke-3

    foreach ($data as $row) {
        // Ekstrak data
        $tanggal_str    = isset($row[$expected_headers['tanggal']]) ? $row[$expected_headers['tanggal']] : '';
        $jadwal         = isset($row[$expected_headers['jadwal']]) ? $row[$expected_headers['jadwal']] : '';
        $jam_kerja      = isset($row[$expected_headers['jam kerja']]) ? $row[$expected_headers['jam kerja']] : '';
        $valid          = isset($row[$expected_headers['valid']]) ? $row[$expected_headers['valid']] : '';
        $pin            = isset($row[$expected_headers['pin']]) ? $row[$expected_headers['pin']] : '';
        $nip            = isset($row[$expected_headers['nip']]) ? trim($row[$expected_headers['nip']]) : '';
        $nama           = isset($row[$expected_headers['nama']]) ? $row[$expected_headers['nama']] : '';
        // departemen → dari form
        $lembur         = isset($row[$expected_headers['lembur']]) ? $row[$expected_headers['lembur']] : '';
        $jam_masuk_raw  = isset($row[$expected_headers['jam masuk']]) ? $row[$expected_headers['jam masuk']] : '';
        $scan_masuk_raw = isset($row[$expected_headers['scan masuk']]) ? $row[$expected_headers['scan masuk']] : '';
        $terlambat      = isset($row[$expected_headers['terlambat']]) ? $row[$expected_headers['terlambat']] : '';
        $scan_i1_raw    = isset($row[$expected_headers['scan istirahat_1']]) ? $row[$expected_headers['scan istirahat_1']] : '';
        $scan_i2_raw    = isset($row[$expected_headers['scan istirahat_2']]) ? $row[$expected_headers['scan istirahat_2']] : '';
        $jam_pulang_raw = isset($row[$expected_headers['jam pulang']]) ? $row[$expected_headers['jam pulang']] : '';
        $scan_pulang_raw= isset($row[$expected_headers['scan pulang']]) ? $row[$expected_headers['scan pulang']] : '';

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

        // (3) Konversi scan/datetime (gabung tanggal + jam)
        $scan_masuk       = parseDateTimeString($tanggal, $scan_masuk_raw);      // yyyy-mm-dd hh:mm:ss
        $scan_istirahat_1 = parseDateTimeString($tanggal, $scan_i1_raw);
        $scan_istirahat_2 = parseDateTimeString($tanggal, $scan_i2_raw);
        $scan_pulang      = parseDateTimeString($tanggal, $scan_pulang_raw);

        // (4) valid, lembur, terlambat ke integer
        $valid     = intval($valid) ?: 0;  // 0 atau 1
        $lembur    = intval($lembur) ?: 0;
        $terlambat = intval($terlambat) ?: 0;

        // Pastikan valid = (0/1) saja
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

        // Binding param
        mysqli_stmt_bind_param(
            $stmt,
            $type_string,
            // kolom-kolom:
            $tanggal,             // DATE
            $jadwal,              // VARCHAR
            $jam_kerja,           // VARCHAR
            $valid,               // TINYINT
            $pin,                 // VARCHAR
            $nip,                 // VARCHAR
            $nama,                // VARCHAR
            $departemen_val_upper,// VARCHAR (diisi dari form)
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

        // Eksekusi
        if (mysqli_stmt_execute($stmt)) {
            $successCount++;
            $details[] = [
                'row' => $rowNumber,
                'status' => 'success',
                'reason' => ''
            ];
        } else {
            $failCount++;
            $details[] = [
                'row' => $rowNumber,
                'status' => 'fail',
                'reason' => mysqli_stmt_error($stmt)
            ];
            // Tambahkan logging ke file log (opsional)
            error_log("Gagal mengimpor baris $rowNumber: " . mysqli_stmt_error($stmt));
        }

        $rowNumber++;
    }

    mysqli_stmt_close($stmt);
    mysqli_stmt_close($stmt_get_id);

    return [
        'status' => 'ok',
        'message' => "Proses impor selesai.",
        'total_rows' => $totalRows,
        'success_count' => $successCount,
        'fail_count' => $failCount,
        'details' => $details
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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Upload Absensi</title>
    <!-- Custom fonts for this template -->
    <link href="../../assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
          rel="stylesheet">
          <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" nonce="<?php echo $nonce; ?>">

    <!-- Custom styles for this template -->
    <link href="../../assets/css/sb-admin-2.min.css" rel="stylesheet">

    <!-- Custom styles for this page -->
    <link href="../../assets/vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link href="../../assets/vendor/datatables/buttons.bootstrap4.min.css" rel="stylesheet">
    <link href="../../assets/vendor/datatables/responsive.bootstrap4.min.css" rel="stylesheet">

    <style>
        .table-bordered th,
        .table-bordered td {
            color: #343a40;
        }
        .modal-content {
            color: #343a40;
        }
        ::placeholder {
            color: #6c757d;
            opacity: 1;
        }
        .form-control {
            color: #343a40;
            background-color: #fff;
            border-color: #ced4da;
        }
        .table-hover tbody tr:hover {
            background-color: #f1f1f1;
        }
        #logTable tfoot th {
            background-color: #e9ecef;
            color: #343a40;
        }
        .dt-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .fas {
            color: #343a40;
        }
        .btn-primary .fas,
        .btn-warning .fas,
        .btn-danger .fas,
        .btn-success .fas,
        .btn-info .fas {
            color: #fff;
        }
        .btn .fas {
            margin-right: 5px;
        }
        .badge-primary {
            background-color: #007bff;
            color: #fff;
        }
        .badge-success {
            background-color: #28a745;
            color: #fff;
        }
        .badge-secondary {
            background-color: #6c757d;
            color: #fff;
        }
        .badge-info {
            background-color: #17a2b8;
            color: #fff;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-danger {
            background-color: #dc3545;
            color: #fff;
        }
        .table-responsive table {
            transition: all 0.3s ease;
        }
    </style>
</head>

<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <?php include __DIR__ . '/../../navbar.php'; ?>
                <!-- End of Topbar -->
<!-- Breadcrumb -->
<?php include __DIR__ . '/../../breadcrumb.php'; ?>

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Upload Absensi</h1>

                    <!-- Notification -->
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <i class="fas fa-info-circle"></i> <?= htmlspecialchars($message) ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Upload Form -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Form Upload Absensi</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                                <div class="form-group">
                                    <label for="departemen">Pilih Departemen</label>
                                    <select name="departemen" id="departemen" class="form-control" required>
                                        <option value="">--Pilih--</option>
                                        <option value="TK">TK</option>
                                        <option value="SD">SD</option>
                                        <option value="SMP">SMP</option>
                                        <option value="SMA">SMA</option>
                                        <option value="SMK">SMK</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="file_absensi">Upload File Absensi (Excel, .xls/.xlsx)</label>
                                    <input type="file" name="file_absensi" id="file_absensi" class="form-control"
                                           accept=".xls,.xlsx" required>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Upload & Import
                                    <span class="spinner-border spinner-border-sm d-none" role="status"
                                          aria-hidden="true"></span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Import Details -->
                    <?php if (!empty($logDetails) && is_iterable($logDetails)): ?>
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Detail Import</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover text-dark" id="logTable"
                                           width="100%" cellspacing="0">
                                        <thead class="thead-light">
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
                                                            <span class="badge badge-success">Sukses</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-danger">Gagal</span>
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
                <!-- /.container-fluid -->
            </div>
            <!-- End of Main Content -->
            
            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; Your Website 2024 | <a href="#">Privacy Policy</a> | <a href="#">Contact</a></span>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <!-- Bootstrap core JavaScript-->
    <script src="../../assets/vendor/jquery/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>

    
    <!-- Core plugin JavaScript-->
    <script src="../../assets/vendor/jquery-easing/jquery.easing.min.js"></script>
    
    <!-- Custom scripts for all pages-->
    <script src="../../assets/js/sb-admin-2.min.js"></script>
    
    <!-- Page level plugins -->
    <script src="../../assets/vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="../../assets/vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script src="../../assets/vendor/datatables/dataTables.buttons.min.js"></script>
    <script src="../../assets/vendor/datatables/buttons.bootstrap4.min.js"></script>
    <script src="../../assets/vendor/datatables/jszip.min.js"></script>
    <script src="../../assets/vendor/datatables/pdfmake.min.js"></script>
    <script src="../../assets/vendor/datatables/vfs_fonts.js"></script>
    <script src="../../assets/vendor/datatables/buttons.html5.min.js"></script>
    <script src="../../assets/vendor/datatables/buttons.print.min.js"></script>
    <script src="../../assets/vendor/datatables/buttons.colVis.min.js"></script>
    <script src="../../assets/vendor/datatables/dataTables.responsive.min.js"></script>
    <script src="../../assets/vendor/datatables/responsive.bootstrap4.min.js"></script>
    
    <!-- Page level custom scripts -->
    <script>
    $(document).ready(function() {
        // Initialize DataTables for logTable
        $('#logTable').DataTable({
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
            },
            "dom": 'Bfrtip',
            "buttons": [
                {
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

        // Tooltip
        $('[data-toggle="tooltip"]').tooltip();

        // Fade out alerts setelah 3 detik
        window.setTimeout(function () {
            $(".alert").fadeTo(500, 0).slideUp(500, function () {
                $(this).remove();
            });
        }, 3000);

        // Spinner on submit
        $('#uploadForm').on('submit', function(e) {
            var $btn = $(this).find('button[type="submit"]');
            $btn.prop('disabled', true);
            $btn.find('.spinner-border').removeClass('d-none');
        });
    });
    </script>
</body>
</html>
