<?php
// File: /payroll_absensi_v2/laporan_absensi.php

// =========================
// 1. Pengaturan Keamanan, Session, dan Koneksi
// =========================

// Atur parameter cookie session sebelum session_start()
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
    'httponly' => true,
    'samesite' => 'Strict'
]);

// Sertakan helper dan mulai session dengan aman
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
generate_csrf_token();

// Buat nonce untuk CSP dan simpan di session
$nonce = base64_encode(random_bytes(16));
$_SESSION['csp_nonce'] = $nonce;

// Paksa HTTPS jika belum
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: " . $redirect);
    exit();
}

// Header HSTS
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

// Susun Content Security Policy (CSP) dengan nonce (tanpa newline)
$csp = "default-src 'self'; " .
       "script-src 'self' https://code.jquery.com https://cdnjs.cloudflare.com https://cdn.datatables.net https://cdn.jsdelivr.net 'nonce-$nonce'; " .
       "style-src 'self' https://stackpath.bootstrapcdn.com https://fonts.googleapis.com https://cdn.datatables.net 'nonce-$nonce'; " .
       "img-src 'self'; " .
       "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; " .
       "connect-src 'self'";
$csp = str_replace(["\r", "\n"], '', $csp);
header("Content-Security-Policy: $csp");

// =========================
// 2. Role Checking (Hanya untuk sdm dan superadmin)
// =========================
function authorize($allowed_roles = ['sdm', 'superadmin']) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        http_response_code(403);
        echo "Akses ditolak.";
        exit();
    }
}
authorize();

// Koneksi ke database
require_once __DIR__ . '/../../koneksi.php';
if (ob_get_length()) ob_end_clean();

// =========================
// 3. Fungsi-Fungsi Utilitas
// =========================

/**
 * Ambil data absensi berdasarkan filter departemen dan bulan.
 */
function get_absensi($conn, $departemen = '', $bulan = '')
{
    $query = "SELECT * FROM absensi WHERE 1=1";
    $bindTypes = "";
    $bindParams = [];

    if (!empty($departemen)) {
        $query .= " AND UPPER(departemen) = UPPER(?)";
        $bindTypes .= "s";
        $bindParams[] = $departemen;
    }
    if (!empty($bulan)) {
        $query .= " AND DATE_FORMAT(tanggal, '%Y-%m') = ?";
        $bindTypes .= "s";
        $bindParams[] = $bulan;
    }
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        $_SESSION['notif_error'] = "Gagal menyiapkan query: " . mysqli_error($conn);
        return [];
    }
    if (!empty($bindTypes)) {
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindParams);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        mysqli_free_result($result);
    } else {
        $_SESSION['notif_error'] = "Terjadi kesalahan saat mengambil data absensi: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
    return $data;
}


/**
 * Fungsi export PDF berdasarkan data absensi.
 */
function generatePdfAbsensi($laporan_absensi, $bulan, $departemen = '')
{
    // Format judul bulan
    $judulBulan = empty($bulan) ? 'Semua Bulan' : date('F Y', strtotime($bulan . '-01'));
    $html = '<h3>Laporan Absensi Bulan: ' . htmlspecialchars($judulBulan) . '</h3>';
    $html .= (!empty($departemen)) ? '<p>Departemen: ' . htmlspecialchars($departemen) . '</p>' : '<p>Departemen: Semua</p>';

    // Buat tabel HTML
    $html .= '
    <style>
        table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 8px; }
        th, td { border: 1px solid #343a40; padding: 5px; text-align: center; }
        th { background-color: #007bff; color: white; }
        .highlight-red { background-color: #dc3545 !important; color: white; }
        .highlight-yellow { background-color: #ffc107 !important; color: #212529; }
        .highlight-green { background-color: #28a745 !important; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
    </style>
    <table>
      <thead>
        <tr>
          <th>No</th>
          <th>Tanggal</th>
          <th>Jadwal</th>
          <th>Jam Kerja</th>
          <th>Valid</th>
          <th>PIN</th>
          <th>NIP</th>
          <th>Nama</th>
          <th>Departemen</th>
          <th>Lembur</th>
          <th>Jam Masuk</th>
          <th>Scan Masuk</th>
          <th>Terlambat</th>
          <th>Scan Istirahat 1</th>
          <th>Scan Istirahat 2</th>
          <th>Jam Pulang</th>
          <th>Scan Pulang</th>
          <th>Keterangan Pulang</th>
        </tr>
      </thead>
      <tbody>
    ';

    foreach ($laporan_absensi as $index => $absen) {
        // Format waktu
        $jam_masuk = DateTime::createFromFormat('H:i:s', $absen['jam_masuk']);
        $scan_masuk = DateTime::createFromFormat('Y-m-d H:i:s', $absen['scan_masuk']);
        $jam_pulang = DateTime::createFromFormat('H:i:s', $absen['jam_pulang']);
        $scan_pulang = DateTime::createFromFormat('Y-m-d H:i:s', $absen['scan_pulang']);
        $jam_masuk_time = $jam_masuk ? $jam_masuk->format('H:i') : '-';
        $scan_masuk_time = $scan_masuk ? $scan_masuk->format('H:i') : '-';
        $jam_pulang_time = $jam_pulang ? $jam_pulang->format('H:i') : '-';
        $scan_pulang_time = $scan_pulang ? $scan_pulang->format('H:i') : '-';

        // Hitung keterlambatan
        $terlambatDisplay = 'Tidak';
        $terlambatClass = '';
        if ($jam_masuk && $scan_masuk && $scan_masuk > $jam_masuk) {
            $interval = $jam_masuk->diff($scan_masuk);
            $minutes = ($interval->h * 60) + $interval->i;
            $terlambatDisplay = $minutes . ' menit';
            $terlambatClass = 'highlight-red';
        }

        // Keterangan pulang
        $keteranganPulang = '';
        $keteranganPulangClass = '';
        if ($jam_pulang && $scan_pulang) {
            if ($scan_pulang == $jam_pulang) {
                $keteranganPulang = 'Tepat Waktu';
                $keteranganPulangClass = 'highlight-green';
            } elseif ($scan_pulang > $jam_pulang) {
                $keteranganPulang = 'Overtime';
                $keteranganPulangClass = 'highlight-yellow';
            } else {
                $keteranganPulang = 'Lebih Cepat';
                $keteranganPulangClass = 'highlight-yellow';
            }
        }

        $html .= '<tr>
                    <td>' . ($index + 1) . '</td>
                    <td>' . htmlspecialchars($absen['tanggal']) . '</td>
                    <td>' . htmlspecialchars($absen['jadwal']) . '</td>
                    <td>' . htmlspecialchars($absen['jam_kerja']) . '</td>
                    <td>' . htmlspecialchars($absen['valid']) . '</td>
                    <td>' . htmlspecialchars($absen['pin']) . '</td>
                    <td>' . htmlspecialchars($absen['nip']) . '</td>
                    <td>' . htmlspecialchars($absen['nama']) . '</td>
                    <td>' . htmlspecialchars($absen['departemen']) . '</td>
                    <td>' . htmlspecialchars($absen['lembur']) . '</td>
                    <td>' . htmlspecialchars($jam_masuk_time) . '</td>
                    <td>' . htmlspecialchars($scan_masuk_time) . '</td>
                    <td class="' . $terlambatClass . '">' . htmlspecialchars($terlambatDisplay) . '</td>
                    <td>' . htmlspecialchars($absen['scan_istirahat_1']) . '</td>
                    <td>' . htmlspecialchars($absen['scan_istirahat_2']) . '</td>
                    <td>' . htmlspecialchars($jam_pulang_time) . '</td>
                    <td>' . htmlspecialchars($scan_pulang_time) . '</td>
                    <td class="' . $keteranganPulangClass . '">' . htmlspecialchars($keteranganPulang) . '</td>
                  </tr>';
    }
    $html .= '</tbody></table>';

    // Inisialisasi Dompdf
    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $filename = "laporan_absensi_" . (empty($bulan) ? 'all' : $bulan) . ".pdf";
    $dompdf->stream($filename, ["Attachment" => true]);
    exit;
}

/**
 * Fungsi untuk menghasilkan Excel dari data absensi.
 */
function generateExcelAbsensi($laporan_absensi, $bulan, $departemen = '')
{
    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Header kolom
    $headers = [
        'A1' => 'No',
        'B1' => 'Tanggal',
        'C1' => 'Jadwal',
        'D1' => 'Jam Kerja',
        'E1' => 'Valid',
        'F1' => 'PIN',
        'G1' => 'NIP',
        'H1' => 'Nama',
        'I1' => 'Departemen',
        'J1' => 'Lembur',
        'K1' => 'Jam Masuk',
        'L1' => 'Scan Masuk',
        'M1' => 'Terlambat',
        'N1' => 'Scan Istirahat 1',
        'O1' => 'Scan Istirahat 2',
        'P1' => 'Jam Pulang',
        'Q1' => 'Scan Pulang',
        'R1' => 'Keterangan Pulang',
    ];
    foreach ($headers as $cell => $header) {
        $sheet->setCellValue($cell, $header);
    }
    // Styling header
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => [
            'fillType' => PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['argb' => 'FF007BFF'],
        ],
        'alignment' => [
            'horizontal' => PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical'   => PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN],
        ],
    ];
    $sheet->getStyle('A1:R1')->applyFromArray($headerStyle);

    $rowNum = 2;
    foreach ($laporan_absensi as $index => $absen) {
        $jam_masuk = DateTime::createFromFormat('H:i:s', $absen['jam_masuk']);
        $scan_masuk = DateTime::createFromFormat('Y-m-d H:i:s', $absen['scan_masuk']);
        $jam_pulang = DateTime::createFromFormat('H:i:s', $absen['jam_pulang']);
        $scan_pulang = DateTime::createFromFormat('Y-m-d H:i:s', $absen['scan_pulang']);
        $jam_masuk_time = $jam_masuk ? $jam_masuk->format('H:i') : '-';
        $scan_masuk_time = $scan_masuk ? $scan_masuk->format('H:i') : '-';
        $jam_pulang_time = $jam_pulang ? $jam_pulang->format('H:i') : '-';
        $scan_pulang_time = $scan_pulang ? $scan_pulang->format('H:i') : '-';

        $terlambatDisplay = 'Tidak';
        if ($jam_masuk && $scan_masuk && $scan_masuk > $jam_masuk) {
            $interval = $jam_masuk->diff($scan_masuk);
            $minutes = ($interval->h * 60) + $interval->i;
            $terlambatDisplay = $minutes . ' menit';
        }

        $keteranganPulang = '';
        if ($jam_pulang && $scan_pulang) {
            if ($scan_pulang == $jam_pulang) {
                $keteranganPulang = 'Tepat Waktu';
            } elseif ($scan_pulang > $jam_pulang) {
                $keteranganPulang = 'Overtime';
            } else {
                $keteranganPulang = 'Lebih Cepat';
            }
        }

        $sheet->setCellValue('A' . $rowNum, $index + 1);
        $sheet->setCellValue('B' . $rowNum, $absen['tanggal']);
        $sheet->setCellValue('C' . $rowNum, $absen['jadwal']);
        $sheet->setCellValue('D' . $rowNum, $absen['jam_kerja']);
        $sheet->setCellValue('E' . $rowNum, $absen['valid']);
        $sheet->setCellValue('F' . $rowNum, $absen['pin']);
        $sheet->setCellValue('G' . $rowNum, $absen['nip']);
        $sheet->setCellValue('H' . $rowNum, $absen['nama']);
        $sheet->setCellValue('I' . $rowNum, $absen['departemen']);
        $sheet->setCellValue('J' . $rowNum, $absen['lembur']);
        $sheet->setCellValue('K' . $rowNum, $jam_masuk_time);
        $sheet->setCellValue('L' . $rowNum, $scan_masuk_time);
        $sheet->setCellValue('M' . $rowNum, $terlambatDisplay);
        $sheet->setCellValue('N' . $rowNum, $absen['scan_istirahat_1']);
        $sheet->setCellValue('O' . $rowNum, $absen['scan_istirahat_2']);
        $sheet->setCellValue('P' . $rowNum, $jam_pulang_time);
        $sheet->setCellValue('Q' . $rowNum, $scan_pulang_time);
        $sheet->setCellValue('R' . $rowNum, $keteranganPulang);

        $rowNum++;
    }
    foreach(range('A','R') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }
    $filename = "laporan_absensi_" . (empty($bulan) ? 'all' : $bulan) . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Cache-Control: max-age=0');
    $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// =========================
// 4. Tangani Permintaan AJAX (Server-side processing)
// =========================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verifikasi CSRF
        if (!isset($_POST['csrf_token']) || !verify_csrf_token(trim($_POST['csrf_token']))) {
            send_response(403, 'Token keamanan tidak valid.');
        }
        // Kita gunakan query sederhana untuk server-side DataTables
        $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
        $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
        $search = isset($_POST['search']['value']) ? bersihkan_input($_POST['search']['value']) : '';

        $queryBase = "FROM absensi WHERE 1=1";
        $params = [];
        $types = "";

        // Filter departemen dan bulan (jika dikirim melalui AJAX)
        if (!empty($_POST['departemen'])) {
            $queryBase .= " AND UPPER(departemen) = UPPER(?)";
            $types .= "s";
            $params[] = $_POST['departemen'];
        }
        if (!empty($_POST['bulan'])) {
            $queryBase .= " AND DATE_FORMAT(tanggal, '%Y-%m') = ?";
            $types .= "s";
            $params[] = $_POST['bulan'];
        }
        if (!empty($search)) {
            $queryBase .= " AND (nama LIKE ? OR nip LIKE ? OR departemen LIKE ?)";
            $types .= "sss";
            $searchParam = "%" . $search . "%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
        }

        // Total data
        $resTotal = mysqli_query($conn, "SELECT COUNT(*) as total $queryBase");
        $totalData = mysqli_fetch_assoc($resTotal)['total'];

        // Order by default (tanggal DESC)
        $orderBy = " ORDER BY tanggal DESC";
        if (isset($_POST['order'][0]['column']) && isset($_POST['order'][0]['dir'])) {
            $columns = ['tanggal', 'jadwal', 'jam_kerja', 'valid', 'pin', 'nip', 'nama', 'departemen', 'lembur', 'jam_masuk', 'scan_masuk', 'terlambat', 'scan_istirahat_1', 'scan_istirahat_2', 'jam_pulang', 'scan_pulang'];
            $colIndex = intval($_POST['order'][0]['column']);
            if (isset($columns[$colIndex])) {
                $orderBy = " ORDER BY " . $columns[$colIndex] . " " . (($_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC');
            }
        }

        // Limit
        $queryLimit = " LIMIT ?, ?";
        $types .= "ii";
        $params[] = $start;
        $params[] = $length;

        $sql = "SELECT * $queryBase $orderBy $queryLimit";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt === false) {
            send_response(1, 'Query Error: ' . mysqli_error($conn));
        }
        if (!empty($types)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = [];
        $no = $start + 1;
        while ($row = mysqli_fetch_assoc($result)) {
            // Format waktu
            $jamMasuk  = !empty($row['jam_masuk']) ? date('H:i', strtotime($row['jam_masuk'])) : '-';
            $scanMasuk = !empty($row['scan_masuk']) ? date('H:i', strtotime($row['scan_masuk'])) : '-';
            $jamPulang = !empty($row['jam_pulang']) ? date('H:i', strtotime($row['jam_pulang'])) : '-';
            $scanPulang= !empty($row['scan_pulang']) ? date('H:i', strtotime($row['scan_pulang'])) : '-';

            // Siapkan baris data (sesuaikan dengan kebutuhan tampilan)
            $nestedData = [];
            $nestedData[] = $no++;
            $nestedData[] = htmlspecialchars($row['tanggal']);
            $nestedData[] = htmlspecialchars($row['jadwal']);
            $nestedData[] = htmlspecialchars($row['jam_kerja']);
            $nestedData[] = htmlspecialchars($row['valid']);
            $nestedData[] = htmlspecialchars($row['pin']);
            $nestedData[] = htmlspecialchars($row['nip']);
            $nestedData[] = htmlspecialchars($row['nama']);
            $nestedData[] = htmlspecialchars($row['departemen']);
            $nestedData[] = htmlspecialchars($row['lembur']);
            $nestedData[] = htmlspecialchars($jamMasuk);
            $nestedData[] = htmlspecialchars($scanMasuk);
            // Hitung keterlambatan
            $terlambatDisplay = 'Tidak';
            if (!empty($row['jam_masuk']) && !empty($row['scan_masuk']) && strtotime($row['scan_masuk']) > strtotime($row['jam_masuk'])) {
                $diff = strtotime($row['scan_masuk']) - strtotime($row['jam_masuk']);
                $minutes = floor($diff / 60);
                $terlambatDisplay = $minutes . ' menit';
            }
            $nestedData[] = $terlambatDisplay;
            $nestedData[] = htmlspecialchars($row['scan_istirahat_1']);
            $nestedData[] = htmlspecialchars($row['scan_istirahat_2']);
            $nestedData[] = htmlspecialchars($jamPulang);
            $nestedData[] = htmlspecialchars($scanPulang);
            // Untuk kolom keterangan pulang, misalnya:
            $keteranganPulang = '';
            if (!empty($row['jam_pulang']) && !empty($row['scan_pulang'])) {
                if ($row['scan_pulang'] == $row['jam_pulang']) {
                    $keteranganPulang = 'Tepat Waktu';
                } elseif (strtotime($row['scan_pulang']) > strtotime($row['jam_pulang'])) {
                    $keteranganPulang = 'Overtime';
                } else {
                    $keteranganPulang = 'Lebih Cepat';
                }
            }
            $nestedData[] = $keteranganPulang;
            $data[] = $nestedData;
        }
        mysqli_stmt_close($stmt);
        send_response($draw, [
            "draw" => $draw,
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => intval($totalData),
            "data" => $data
        ]);
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    exit();
}

// =========================
// 5. Handle Export (PDF / Excel) jika ada parameter export di GET
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['export'])) {
    $exportType = $_GET['export'];
    // Ambil filter dengan filter_input
    $bulan = filter_input(INPUT_GET, 'bulan', FILTER_SANITIZE_STRING) ?? '';
    $departemen = filter_input(INPUT_GET, 'departemen', FILTER_SANITIZE_STRING) ?? '';
    $laporan_absensi = get_absensi($conn, $departemen, $bulan);
    // Catat audit log export (jika diperlukan)
    if (isset($_SESSION['user_id'])) {
        $action = ($exportType === 'pdf') ? 'export_pdf' : (($exportType === 'excel') ? 'export_excel' : 'export_unknown');
        $details = "Export laporan absensi bulan {$bulan} departemen {$departemen} ke format {$exportType}";
        log_audit_logs($conn, $_SESSION['user_id'], $action, $details);
    }
    if ($exportType === 'pdf') {
        // Pastikan library Dompdf sudah dimuat melalui autoload
        generatePdfAbsensi($laporan_absensi, $bulan, $departemen);
    } elseif ($exportType === 'excel') {
        // Pastikan library PhpSpreadsheet sudah dimuat melalui autoload
        generateExcelAbsensi($laporan_absensi, $bulan, $departemen);
    } else {
        $_SESSION['notif_error'] = "Format export tidak dikenali.";
        header("Location: laporan_absensi.php");
        exit;
    }
}

// Ambil filter GET untuk tampilan halaman
$bulan = filter_input(INPUT_GET, 'bulan', FILTER_SANITIZE_STRING) ?? '';
$departemen = filter_input(INPUT_GET, 'departemen', FILTER_SANITIZE_STRING) ?? '';
$dataAbsensi = get_absensi($conn, $departemen, $bulan);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Absensi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Font Awesome & Bootstrap Icons -->
    <link href="../../assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" nonce="<?php echo $nonce; ?>">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="/payroll_absensi_v2/bootstrap/css/bootstrap.css" nonce="<?php echo $nonce; ?>">
    <!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="../../assets/css/sb-admin-2.min.css" nonce="<?php echo $nonce; ?>">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.7.1/css/buttons.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css" nonce="<?php echo $nonce; ?>">
    <style>
        .no-column { width: 70px; text-align: center; }
        #absensiTable th, #absensiTable td {
            font-size: 12px; padding: 8px; text-align: center; vertical-align: middle; white-space: nowrap;
        }
        .table-responsive { overflow-x: auto; }
        .card-header { background: linear-gradient(45deg, #0d47a1, #42a5f5); color: white; }
        .alert { margin-top: 20px; }
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }
    </style>
</head>
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <!-- End Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a href="../../logout.php" class="btn btn-danger btn-sm" title="Logout">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </nav>
                <!-- End Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-clipboard-list"></i> Laporan Absensi</h1>

                    <!-- Notifikasi dengan SweetAlert2 (jika ada) -->
                    <?php if (isset($_SESSION['notif_success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['notif_success']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['notif_success']); ?>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['notif_error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-times-circle"></i> <?= htmlspecialchars($_SESSION['notif_error']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['notif_error']); ?>
                    <?php endif; ?>

                    <!-- Filter Form -->
                    <div class="d-flex justify-content-between mb-3">
                        <form method="GET" class="form-inline">
                            <label class="mr-2" for="bulan">Pilih Bulan:</label>
                            <input type="month" name="bulan" id="bulan" class="form-control mr-3" value="<?= htmlspecialchars($bulan) ?>">
                            
                            <label class="mr-2" for="departemen">Departemen:</label>
                            <select name="departemen" id="departemen" class="form-control mr-3">
                                <option value="">-- Semua Departemen --</option>
                                <option value="TK" <?= ($departemen === 'TK') ? 'selected' : '' ?>>TK</option>
                                <option value="SD" <?= ($departemen === 'SD') ? 'selected' : '' ?>>SD</option>
                                <option value="SMP" <?= ($departemen === 'SMP') ? 'selected' : '' ?>>SMP</option>
                                <option value="SMA" <?= ($departemen === 'SMA') ? 'selected' : '' ?>>SMA</option>
                                <option value="SMK" <?= ($departemen === 'SMK') ? 'selected' : '' ?>>SMK</option>
                            </select>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Tampilkan
                            </button>
                        </form>

                        <!-- Export Buttons -->
                        <div class="btn-group">
                            <a href="?export=pdf&bulan=<?= urlencode($bulan) ?>&departemen=<?= urlencode($departemen) ?>" class="btn btn-danger btn-sm">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </a>
                            <a href="?export=excel&bulan=<?= urlencode($bulan) ?>&departemen=<?= urlencode($departemen) ?>" class="btn btn-success btn-sm">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </a>
                        </div>
                    </div>

                    <!-- Tabel Laporan Absensi (untuk tampilan statis; DataTables juga bisa digunakan secara server-side jika diinginkan) -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-table"></i> Daftar Absensi</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="absensiTable" class="table table-bordered table-hover display nowrap" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th class="no-column">No</th>
                                            <th>Tanggal</th>
                                            <th>Jadwal</th>
                                            <th>Jam Kerja</th>
                                            <th>Valid</th>
                                            <th>PIN</th>
                                            <th>NIP</th>
                                            <th>Nama</th>
                                            <th>Departemen</th>
                                            <th>Lembur</th>
                                            <th>Jam Masuk</th>
                                            <th>Scan Masuk</th>
                                            <th>Terlambat</th>
                                            <th>Scan Istirahat 1</th>
                                            <th>Scan Istirahat 2</th>
                                            <th>Jam Pulang</th>
                                            <th>Scan Pulang</th>
                                            <th>Keterangan Pulang</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($dataAbsensi)): ?>
                                            <?php foreach ($dataAbsensi as $index => $absen): ?>
                                                <?php
                                                    $jam_masuk   = DateTime::createFromFormat('H:i:s', $absen['jam_masuk']);
                                                    $scan_masuk  = DateTime::createFromFormat('Y-m-d H:i:s', $absen['scan_masuk']);
                                                    $jam_pulang  = DateTime::createFromFormat('H:i:s', $absen['jam_pulang']);
                                                    $scan_pulang = DateTime::createFromFormat('Y-m-d H:i:s', $absen['scan_pulang']);
                                                    $jam_masuk_time   = $jam_masuk ? $jam_masuk->format('H:i') : '-';
                                                    $scan_masuk_time  = $scan_masuk ? $scan_masuk->format('H:i') : '-';
                                                    $jam_pulang_time  = $jam_pulang ? $jam_pulang->format('H:i') : '-';
                                                    $scan_pulang_time = $scan_pulang ? $scan_pulang->format('H:i') : '-';
                                                    $terlambatDisplay = 'Tidak';
                                                    $terlambatClass   = '';
                                                    if ($jam_masuk && $scan_masuk && strtotime($absen['scan_masuk']) > strtotime($absen['jam_masuk'])) {
                                                        $diff = strtotime($absen['scan_masuk']) - strtotime($absen['jam_masuk']);
                                                        $minutes = floor($diff / 60);
                                                        $terlambatDisplay = $minutes . ' menit';
                                                        $terlambatClass = 'highlight-red';
                                                    }
                                                    $keteranganPulang = '';
                                                    $keteranganPulangClass = '';
                                                    if ($jam_pulang && $scan_pulang) {
                                                        if ($absen['scan_pulang'] == $absen['jam_pulang']) {
                                                            $keteranganPulang = 'Tepat Waktu';
                                                            $keteranganPulangClass = 'highlight-green';
                                                        } elseif (strtotime($absen['scan_pulang']) > strtotime($absen['jam_pulang'])) {
                                                            $keteranganPulang = 'Overtime';
                                                            $keteranganPulangClass = 'highlight-yellow';
                                                        } else {
                                                            $keteranganPulang = 'Lebih Cepat';
                                                            $keteranganPulangClass = 'highlight-yellow';
                                                        }
                                                    }
                                                ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td><?= htmlspecialchars($absen['tanggal']) ?></td>
                                                    <td><?= htmlspecialchars($absen['jadwal']) ?></td>
                                                    <td><?= htmlspecialchars($absen['jam_kerja']) ?></td>
                                                    <td><?= htmlspecialchars($absen['valid']) ?></td>
                                                    <td><?= htmlspecialchars($absen['pin']) ?></td>
                                                    <td><?= htmlspecialchars($absen['nip']) ?></td>
                                                    <td><?= htmlspecialchars($absen['nama']) ?></td>
                                                    <td><?= htmlspecialchars($absen['departemen']) ?></td>
                                                    <td><?= htmlspecialchars($absen['lembur']) ?></td>
                                                    <td><?= htmlspecialchars($jam_masuk_time) ?></td>
                                                    <td><?= htmlspecialchars($scan_masuk_time) ?></td>
                                                    <td class="<?= $terlambatClass ?>"><?= htmlspecialchars($terlambatDisplay) ?></td>
                                                    <td><?= htmlspecialchars($absen['scan_istirahat_1']) ?></td>
                                                    <td><?= htmlspecialchars($absen['scan_istirahat_2']) ?></td>
                                                    <td><?= htmlspecialchars($jam_pulang_time) ?></td>
                                                    <td><?= htmlspecialchars($scan_pulang_time) ?></td>
                                                    <td class="<?= $keteranganPulangClass ?>"><?= htmlspecialchars($keteranganPulang) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="18" class="text-center">Data tidak ditemukan atau masih kosong.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Page Content -->
            </div>
            <!-- End Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?= date("Y") ?> Sistem Nusaputera</span>
                    </div>
                </div>
            </footer>
        </div>
        <!-- End Content Wrapper -->
    </div>
    <!-- End Page Wrapper -->

    <!-- JavaScript Dependencies -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" nonce="<?php echo $nonce; ?>"></script>
    <!-- Bootstrap Bundle (termasuk Popper) -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/dataTables.buttons.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.html5.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.print.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.colVis.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" nonce="<?php echo $nonce; ?>"></script>
    <!-- SB Admin 2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/js/sb-admin-2.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script>
        $(document).ready(function() {
            $('#absensiTable').DataTable({
                processing: true,
                serverSide: false, // Jika Anda ingin menggunakan server-side, aktifkan dan sesuaikan endpoint AJAX
                responsive: true,
                autoWidth: false,
                columnDefs: [
                    { orderable: false, targets: [] }
                ],
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
                },
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'copyHtml5',
                        text: '<i class="fas fa-copy"></i> Copy',
                        className: 'btn btn-secondary btn-sm'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Print',
                        className: 'btn btn-info btn-sm',
                        exportOptions: { columns: ':visible' }
                    },
                    {
                        extend: 'colvis',
                        text: '<i class="fas fa-columns"></i> Kolom',
                        className: 'btn btn-warning btn-sm'
                    }
                ],
                pageLength: 10
            });

            // Fade out alerts setelah 3 detik
            window.setTimeout(function () {
                $(".alert").fadeTo(500, 0).slideUp(500, function () { $(this).remove(); });
            }, 3000);
        });
    </script>
</body>
</html>
