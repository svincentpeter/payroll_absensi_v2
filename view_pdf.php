<?php
// view_pdf.php
$fileName = $_GET['file'] ?? '';
$filePath = __DIR__ . '/uploads/surat_ijin/' . $fileName;

// Pastikan file ada dan ekstensinya PDF
if (!file_exists($filePath) || strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'pdf') {
    die("File tidak ditemukan atau bukan PDF.");
}

// Kirim header agar browser menampilkan PDF, bukan download
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($fileName) . '"');
header('Content-Length: ' . filesize($filePath));

readfile($filePath);
exit;
