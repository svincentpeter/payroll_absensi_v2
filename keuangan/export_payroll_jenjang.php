<?php
// File: /payroll_absensi_v2/keuangan/export_payroll_jenjang.php

// WAJIB: Tidak ada spasi/baris apapun sebelum <?php !!

// Clean output buffer sebelum export (penting!)
if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../koneksi.php';

// Autoload PhpSpreadsheet
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Helper konversi kolom Excel (1->A, 2->B, ..., 27->AA dst)
function getExcelCol($num) {
    $alpha = '';
    while ($num > 0) {
        $mod = ($num - 1) % 26;
        $alpha = chr(65 + $mod) . $alpha;
        $num = (int)(($num - $mod) / 26);
    }
    return $alpha;
}

// Ambil parameter
$jenjang = $_GET['jenjang'] ?? '';
$bulan   = intval($_GET['bulan'] ?? date('n'));
$tahun   = intval($_GET['tahun'] ?? date('Y'));
$kategori = $_GET['kategori'] ?? 'guru'; // atau 'karyawan'

// --- Logic payhead sama dengan di rekap_payroll_jenjang.php
$PAYHEAD_GROUPS = [];
$rs = $conn->query("
  SELECT group_name
    FROM payhead_groups
   WHERE jenis='earnings'
GROUP BY group_name
ORDER BY MIN(sort_order), group_name
");
while ($g = $rs->fetch_assoc()) {
  $PAYHEAD_GROUPS[] = $g['group_name'];
}
$rs->free();

$groupMembers = [];
$rs = $conn->query("SELECT DISTINCT payhead_name FROM payhead_groups");
while ($gm = $rs->fetch_assoc()) {
  $groupMembers[] = $gm['payhead_name'];
}
$rs->free();

$inList = $groupMembers
  ? "'" . implode("','", array_map([$conn,'real_escape_string'],$groupMembers)) . "'"
  : "''";
$earningPayheads = [];
$rs = $conn->query("
  SELECT DISTINCT nama_payhead
    FROM payroll_detail_final
   WHERE jenis='earnings'
     AND nama_payhead NOT IN ($inList)
   ORDER BY nama_payhead
");
while ($r = $rs->fetch_assoc()) {
  $earningPayheads[] = $r['nama_payhead'];
}
$rs->free();

$deductionPayheads = [];
$rs = $conn->query("
  SELECT DISTINCT nama_payhead
    FROM payroll_detail_final
   WHERE jenis='deductions'
     AND nama_payhead NOT IN ($inList)
   ORDER BY nama_payhead
");
while ($r = $rs->fetch_assoc()) {
  $deductionPayheads[] = $r['nama_payhead'];
}
$rs->free();

$PAYHEADS = array_merge($earningPayheads, $deductionPayheads);

// --- Query data, tanpa limit paging!
if (strtolower($jenjang) === 'semua') {
  $sqlWhere = "WHERE a.kategori=? AND pf.bulan=? AND pf.tahun=?";
  $params   = [$kategori, $bulan, $tahun];
  $types    = "sii";
} else {
  $sqlWhere = "WHERE a.jenjang=? AND a.kategori=? AND pf.bulan=? AND pf.tahun=?";
  $params   = [$jenjang, $kategori, $bulan, $tahun];
  $types    = "ssii";
}

// Build columns
$subCasesPH  = [];
$outerColsPH = [];
foreach ($PAYHEADS as $ph) {
  $esc   = $conn->real_escape_string($ph);
  $alias = 'ph_' . substr(md5($ph), 0, 8);
  $subCasesPH[]  = "SUM(CASE WHEN d.nama_payhead='$esc' THEN d.amount ELSE 0 END) AS `$alias`";
  $outerColsPH[] = ", agg.`$alias`";
}
$subCasesGR  = [];
$outerColsGR = [];
foreach ($PAYHEAD_GROUPS as $grp) {
  $esc  = $conn->real_escape_string($grp);
  $mrs = $conn->query("SELECT payhead_name FROM payhead_groups WHERE group_name='$esc'");
  $members = [];
  while ($m = $mrs->fetch_assoc()) {
    $members[] = $conn->real_escape_string($m['payhead_name']);
  }
  $mrs->free();
  $in = $members ? "'" . implode("','", $members) . "'" : "''";
  $alias = 'gr_' . substr(md5($grp), 0, 8);
  $subCasesGR[]  = "SUM(CASE WHEN d.nama_payhead IN($in) THEN d.amount ELSE 0 END) AS `$alias`";
  $outerColsGR[] = ", agg.`$alias`";
}
$subSelect   = implode(",\n    ", array_merge($subCasesPH, $subCasesGR));
$outerSelect = implode(" ", array_merge($outerColsPH, $outerColsGR));

$sqlData = "
  SELECT
    a.nip,
    a.nama,
    a.job_title     AS keterangan,
    pf.gaji_pokok,
    pf.salary_index_amount AS idx_amount,
    pf.potongan_koperasi    AS pot_koperasi
    $outerSelect,
    pf.gaji_bersih
  FROM payroll_final pf
  JOIN anggota_sekolah a ON pf.id_anggota=a.id
  LEFT JOIN (
    SELECT id_payroll_final,
           $subSelect
      FROM payroll_detail_final d
     GROUP BY id_payroll_final
  ) agg ON pf.id=agg.id_payroll_final
  $sqlWhere
  GROUP BY pf.id
";
$stmt = $conn->prepare($sqlData);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($r = $res->fetch_assoc()) {
  $gajiPokok   = (float)$r['gaji_pokok'];
  $idxAmount   = (float)$r['idx_amount'];
  $potKoperasi = (float)$r['pot_koperasi'];
  $sumEarningsPH = 0;
  foreach ($earningPayheads as $ph) {
    $alias = 'ph_' . substr(md5($ph), 0, 8);
    $sumEarningsPH += (float)$r[$alias];
  }
  $sumGroupPH = 0;
  foreach ($PAYHEAD_GROUPS as $grp) {
    $alias = 'gr_' . substr(md5($grp), 0, 8);
    $sumGroupPH += (float)$r[$alias];
  }
  $totalPendapatan = $gajiPokok + $idxAmount + $sumEarningsPH + $sumGroupPH;
  $maxPotKop = $totalPendapatan * 0.65;
  $sumDeductionPH = 0;
  foreach ($deductionPayheads as $ph) {
    $alias = 'ph_' . substr(md5($ph), 0, 8);
    $sumDeductionPH += (float)$r[$alias];
  }
  $totalPotongan = $potKoperasi + $sumDeductionPH;
  $netReceived = $totalPendapatan - $totalPotongan;
  $rounded = round($netReceived / 100) * 100;

  $row = [
    'nip'               => $r['nip'],
    'nama'              => $r['nama'],
    'keterangan'        => $r['keterangan'],
    'gaji_pokok'        => $gajiPokok,
    'idx_amount'        => $idxAmount,
  ];
  foreach ($earningPayheads as $ph) {
    $alias = 'ph_' . substr(md5($ph), 0, 8);
    $row[$alias] = $r[$alias] ?? 0;
  }
  foreach ($PAYHEAD_GROUPS as $grp) {
    $alias = 'gr_' . substr(md5($grp), 0, 8);
    $row[$alias] = $r[$alias] ?? 0;
  }
  foreach ($deductionPayheads as $ph) {
    $alias = 'ph_' . substr(md5($ph), 0, 8);
    $row[$alias] = $r[$alias] ?? 0;
  }
  $row['pembulatan']       = $rounded;
  $row['total_pendapatan'] = $totalPendapatan;
  $row['max_pot_kop']      = $maxPotKop;
  $row['pot_koperasi']     = $potKoperasi;
  $row['total_potongan']   = $totalPotongan;
  $row['net_received']     = $netReceived;

  $data[] = $row;
}
$stmt->close();

// --- Generate Excel ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Header: judul
$sheet->setCellValue('A1', 'Rekap Payroll '.ucfirst($kategori).' '.strtoupper($jenjang));
$sheet->mergeCells('A1:Z1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->setCellValue('A2', 'Periode: '.getIndonesianMonthName($bulan).' '.$tahun);
$sheet->mergeCells('A2:Z2');

// Header kolom
$headers = ['NIP', 'Nama', 'Keterangan', 'Gaji Pokok', 'Indeks'];
foreach ($earningPayheads as $ph)      $headers[] = $ph;
foreach ($PAYHEAD_GROUPS as $grp)      $headers[] = $grp;
foreach ($deductionPayheads as $ph)    $headers[] = $ph;
$headers = array_merge($headers, [
  'Pembulatan',
  'Jumlah Pendapatan',
  'Max Pot. Kop',
  'Pot. Koperasi',
  'Jumlah Potongan',
  'Jumlah Yang Diterima'
]);

// Penulisan header
$colIndex = 0;
foreach ($headers as $h) {
  $colLetter = getExcelCol($colIndex + 1);
  $sheet->setCellValue($colLetter.'4', $h);
  $sheet->getStyle($colLetter.'4')->getFont()->setBold(true);
  $colIndex++;
}

// Isi data
$rowNum = 5;
foreach ($data as $row) {
  $colIndex = 0;
  foreach ($headers as $h) {
    $colLetter = getExcelCol($colIndex + 1);
    // Map header ke field
    $key = '';
    switch ($h) {
      case 'NIP': $key = 'nip'; break;
      case 'Nama': $key = 'nama'; break;
      case 'Keterangan': $key = 'keterangan'; break;
      case 'Gaji Pokok': $key = 'gaji_pokok'; break;
      case 'Indeks': $key = 'idx_amount'; break;
      case 'Pembulatan': $key = 'pembulatan'; break;
      case 'Jumlah Pendapatan': $key = 'total_pendapatan'; break;
      case 'Max Pot. Kop': $key = 'max_pot_kop'; break;
      case 'Pot. Koperasi': $key = 'pot_koperasi'; break;
      case 'Jumlah Potongan': $key = 'total_potongan'; break;
      case 'Jumlah Yang Diterima': $key = 'net_received'; break;
      default:
        foreach ($earningPayheads as $ph) if ($h === $ph) $key = 'ph_' . substr(md5($ph), 0, 8);
        foreach ($PAYHEAD_GROUPS as $grp) if ($h === $grp) $key = 'gr_' . substr(md5($grp), 0, 8);
        foreach ($deductionPayheads as $ph) if ($h === $ph) $key = 'ph_' . substr(md5($ph), 0, 8);
        break;
    }
    $sheet->setCellValue($colLetter.$rowNum, $row[$key]??0);
    $colIndex++;
  }
  $rowNum++;
}

// Styling basic: auto-size all columns
for ($i = 1; $i <= count($headers); $i++) {
  $colLetter = getExcelCol($i);
  $sheet->getColumnDimension($colLetter)->setAutoSize(true);
}
$lastCol = getExcelCol(count($headers));
$sheet->getStyle("A4:{$lastCol}".($rowNum-1))
    ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

// Output Excel
$filename = 'Rekap_Payroll_'.$kategori.'_'.$jenjang.'_'.$bulan.'_'.$tahun.'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0');
ob_end_clean(); // Bersihkan sebelum output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
