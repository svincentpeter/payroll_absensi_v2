<?php
if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function getExcelCol($num) {
    $alpha = '';
    while ($num > 0) {
        $mod = ($num - 1) % 26;
        $alpha = chr(65 + $mod) . $alpha;
        $num = (int)(($num - $mod) / 26);
    }
    return $alpha;
}

$jenjang = $_GET['jenjang'] ?? '';
$bulan   = intval($_GET['bulan'] ?? date('n'));
$tahun   = intval($_GET['tahun'] ?? date('Y'));

// 1. Daftar jenjang
$jenjangList = [];
$rs = $conn->query("SELECT kode_jenjang, nama_jenjang FROM jenjang_sekolah WHERE is_aktif=1");
while ($j = $rs->fetch_assoc()) $jenjangList[$j['kode_jenjang']] = $j['nama_jenjang'];
$rs->free();

// 2. Konfigurasi payhead
$PAYHEAD_GROUPS = [];
$rs = $conn->query("SELECT group_name FROM payhead_groups WHERE jenis='earnings' GROUP BY group_name ORDER BY MIN(sort_order), group_name");
while ($g = $rs->fetch_assoc()) $PAYHEAD_GROUPS[] = $g['group_name'];
$rs->free();

$groupMembers = [];
$rs = $conn->query("SELECT DISTINCT payhead_name FROM payhead_groups");
while ($gm = $rs->fetch_assoc()) $groupMembers[] = $gm['payhead_name'];
$rs->free();

$inList = $groupMembers
  ? "'" . implode("','", array_map([$conn, 'real_escape_string'], $groupMembers)) . "'"
  : "''";
$earningPayheads = [];
$rs = $conn->query("SELECT DISTINCT nama_payhead FROM payroll_detail_final WHERE jenis='earnings' AND nama_payhead NOT IN ($inList) ORDER BY nama_payhead");
while ($r = $rs->fetch_assoc()) $earningPayheads[] = $r['nama_payhead'];
$rs->free();

$deductionPayheads = [];
$rs = $conn->query("SELECT DISTINCT nama_payhead FROM payroll_detail_final WHERE jenis='deductions' AND nama_payhead NOT IN ($inList) ORDER BY nama_payhead");
while ($r = $rs->fetch_assoc()) $deductionPayheads[] = $r['nama_payhead'];
$rs->free();

$isSemua = ($jenjang === '' || strtolower($jenjang) === 'semua');

// Header untuk mode detail per anggota
$headersDetail = [
  'NIP', 'Nama', 'Keterangan', 'Gaji Pokok', 'Indeks'
];
foreach ($earningPayheads as $ph)      $headersDetail[] = $ph;
foreach ($PAYHEAD_GROUPS as $grp)      $headersDetail[] = $grp;
foreach ($deductionPayheads as $ph)    $headersDetail[] = $ph;
$headersDetail = array_merge($headersDetail, [
  'Pembulatan',
  'Jumlah Pendapatan',
  'Max Pot. Kop',
  'Pot. Koperasi',
  'Jumlah Potongan',
  'Jumlah Yang Diterima'
]);

// Header untuk mode ringkasan per jenjang
$headersRingkas = [
  'No', 'Jenjang', 'Gaji Pokok', 'Indeks'
];
foreach ($earningPayheads as $ph)      $headersRingkas[] = $ph;
foreach ($PAYHEAD_GROUPS as $grp)      $headersRingkas[] = $grp;
foreach ($deductionPayheads as $ph)    $headersRingkas[] = $ph;
$headersRingkas = array_merge($headersRingkas, [
  'Pembulatan',
  'Jumlah Pendapatan',
  'Max Pot. Kop',
  'Pot. Koperasi',
  'Jumlah Potongan',
  'Jumlah Yang Diterima'
]);

// 3. Helper QUERY detail anggota
function fetchDetailRows($conn, $jenjang, $bulan, $tahun, $kategori, $earningPayheads, $PAYHEAD_GROUPS, $deductionPayheads) {
    $PAYHEADS = array_merge($earningPayheads, $deductionPayheads);
    if ($kategori == 'manajer') {
        $where = "a.role='M' AND a.jenjang=? AND pf.bulan=? AND pf.tahun=?";
        $params = [$jenjang, $bulan, $tahun];
        $types = 'sii';
    } else {
        $where = "a.kategori=? AND a.role<>'M' AND a.jenjang=? AND pf.bulan=? AND pf.tahun=?";
        $params = [$kategori, $jenjang, $bulan, $tahun];
        $types = 'ssii';
    }
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
        while ($m = $mrs->fetch_assoc()) $members[] = $conn->real_escape_string($m['payhead_name']);
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
          a.nip, a.nama, a.job_title, a.remark AS keterangan,
          pf.gaji_pokok, pf.salary_index_amount AS idx_amount,
          pf.potongan_koperasi AS pot_koperasi
          $outerSelect,
          pf.gaji_bersih
        FROM payroll_final pf
        JOIN anggota_sekolah a ON pf.id_anggota=a.id
        LEFT JOIN (
          SELECT id_payroll_final, $subSelect
          FROM payroll_detail_final d
          GROUP BY id_payroll_final
        ) agg ON pf.id=agg.id_payroll_final
        WHERE $where
        GROUP BY pf.id
        ORDER BY a.nama
    ";
    $stmt = $conn->prepare($sqlData);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $row = [
            'NIP' => $r['nip'],
            'Nama' => $r['nama'],
            'Keterangan' => $r['keterangan'],
            'Gaji Pokok' => $r['gaji_pokok'],
            'Indeks' => $r['idx_amount'],
        ];
        foreach ($earningPayheads as $ph) $row[$ph] = $r['ph_' . substr(md5($ph), 0, 8)] ?? 0;
        foreach ($PAYHEAD_GROUPS as $grp) $row[$grp] = $r['gr_' . substr(md5($grp), 0, 8)] ?? 0;
        foreach ($deductionPayheads as $ph) $row[$ph] = $r['ph_' . substr(md5($ph), 0, 8)] ?? 0;
        // Perhitungan (persis logic DataTables Anda)
        $sumEarningsPH = 0; foreach ($earningPayheads as $ph) $sumEarningsPH += (float)$r['ph_' . substr(md5($ph), 0, 8)];
        $sumGroupPH = 0; foreach ($PAYHEAD_GROUPS as $grp) $sumGroupPH += (float)$r['gr_' . substr(md5($grp), 0, 8)];
        $gajiPokok = (float)$r['gaji_pokok'];
        $idxAmount = (float)$r['idx_amount'];
        $totalPendapatan = $gajiPokok + $idxAmount + $sumEarningsPH + $sumGroupPH;
        $maxPotKop = $totalPendapatan * 0.65;
        $sumDeductionPH = 0; foreach ($deductionPayheads as $ph) $sumDeductionPH += (float)$r['ph_' . substr(md5($ph), 0, 8)];
        $potKoperasi = (float)$r['pot_koperasi'];
        $totalPotongan = $potKoperasi + $sumDeductionPH;
        $netReceived = $totalPendapatan - $totalPotongan;
        $rounded = round($netReceived / 100) * 100;
        $row['Pembulatan'] = $rounded;
        $row['Jumlah Pendapatan'] = $totalPendapatan;
        $row['Max Pot. Kop'] = $maxPotKop;
        $row['Pot. Koperasi'] = $potKoperasi;
        $row['Jumlah Potongan'] = $totalPotongan;
        $row['Jumlah Yang Diterima'] = $netReceived;
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

// 4. Helper TOTAL per jenjang (untuk mode SEMUA)
function fetchSummaryRow($conn, $jenjang, $bulan, $tahun, $kategori, $earningPayheads, $PAYHEAD_GROUPS, $deductionPayheads) {
    // SYARAT
    if ($kategori == 'manajer') {
        $where = "a.role='M' AND a.jenjang=? AND pf.bulan=? AND pf.tahun=?";
        $params = [$jenjang, $bulan, $tahun];
        $types = 'sii';
    } else {
        $where = "a.kategori=? AND a.role<>'M' AND a.jenjang=? AND pf.bulan=? AND pf.tahun=?";
        $params = [$kategori, $jenjang, $bulan, $tahun];
        $types = 'ssii';
    }
    // 1. AMBIL payroll_final TANPA JOIN
    $sql1 = "SELECT SUM(pf.gaji_pokok) AS gaji_pokok, SUM(pf.salary_index_amount) AS idx_amount, SUM(pf.potongan_koperasi) AS pot_koperasi
         FROM payroll_final pf
         JOIN anggota_sekolah a ON pf.id_anggota=a.id
         WHERE $where";
    $stmt1 = $conn->prepare($sql1);
    $stmt1->bind_param($types, ...$params);
    $stmt1->execute();
    $r1 = $stmt1->get_result()->fetch_assoc();
    $stmt1->close();

    // 2. SUM setiap komponen payhead dari payroll_detail_final (SUBQUERY GROUP BY id payroll)
    $komponen = [];
    foreach ($earningPayheads as $ph) {
        $sql2 = "SELECT SUM(amount) FROM (
                   SELECT SUM(d.amount) AS amount
                   FROM payroll_final pf
                   JOIN anggota_sekolah a ON pf.id_anggota=a.id
                   JOIN payroll_detail_final d ON pf.id = d.id_payroll_final
                   WHERE $where AND d.nama_payhead=? GROUP BY pf.id
                ) AS sub";
        $stmt2 = $conn->prepare($sql2);
        $params2 = array_merge($params, [$ph]);
        $types2 = $types . "s";
        $stmt2->bind_param($types2, ...$params2);
        $stmt2->execute();
        $komponen[$ph] = $stmt2->get_result()->fetch_row()[0] ?? 0;
        $stmt2->close();
    }
    foreach ($PAYHEAD_GROUPS as $grp) {
        // SUM seluruh anggota group dengan SUBQUERY GROUP BY payroll
        $mrs = $conn->query("SELECT payhead_name FROM payhead_groups WHERE group_name='".$conn->real_escape_string($grp)."'");
        $members = [];
        while ($m = $mrs->fetch_assoc()) $members[] = $conn->real_escape_string($m['payhead_name']);
        $mrs->free();
        if (!$members) { $komponen[$grp] = 0; continue; }
        $in = "'" . implode("','", $members) . "'";
        $sql2 = "SELECT SUM(amount) FROM (
                   SELECT SUM(d.amount) AS amount
                   FROM payroll_final pf
                   JOIN anggota_sekolah a ON pf.id_anggota=a.id
                   JOIN payroll_detail_final d ON pf.id = d.id_payroll_final
                   WHERE $where AND d.nama_payhead IN($in) GROUP BY pf.id
                ) AS sub";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param($types, ...$params);
        $stmt2->execute();
        $komponen[$grp] = $stmt2->get_result()->fetch_row()[0] ?? 0;
        $stmt2->close();
    }
    foreach ($deductionPayheads as $ph) {
        $sql2 = "SELECT SUM(amount) FROM (
                   SELECT SUM(d.amount) AS amount
                   FROM payroll_final pf
                   JOIN anggota_sekolah a ON pf.id_anggota=a.id
                   JOIN payroll_detail_final d ON pf.id = d.id_payroll_final
                   WHERE $where AND d.nama_payhead=? GROUP BY pf.id
                ) AS sub";
        $stmt2 = $conn->prepare($sql2);
        $params2 = array_merge($params, [$ph]);
        $types2 = $types . "s";
        $stmt2->bind_param($types2, ...$params2);
        $stmt2->execute();
        $komponen[$ph] = $stmt2->get_result()->fetch_row()[0] ?? 0;
        $stmt2->close();
    }
    // Kalkulasi total
    $gajiPokok   = (float)$r1['gaji_pokok'];
    $idxAmount   = (float)$r1['idx_amount'];
    $potKoperasi = (float)$r1['pot_koperasi'];
    $sumEarningsPH = 0; foreach ($earningPayheads as $ph) $sumEarningsPH += (float)$komponen[$ph];
    $sumGroupPH = 0; foreach ($PAYHEAD_GROUPS as $grp) $sumGroupPH += (float)$komponen[$grp];
    $totalPendapatan = $gajiPokok + $idxAmount + $sumEarningsPH + $sumGroupPH;
    $maxPotKop = $totalPendapatan * 0.65;
    $sumDeductionPH = 0; foreach ($deductionPayheads as $ph) $sumDeductionPH += (float)$komponen[$ph];
    $totalPotongan = $potKoperasi + $sumDeductionPH;
    $netReceived = $totalPendapatan - $totalPotongan;
    $rounded = round($netReceived / 100) * 100;

    $row = [
        'Gaji Pokok' => $gajiPokok,
        'Indeks' => $idxAmount
    ];
    foreach ($earningPayheads as $ph) $row[$ph] = $komponen[$ph];
    foreach ($PAYHEAD_GROUPS as $grp) $row[$grp] = $komponen[$grp];
    foreach ($deductionPayheads as $ph) $row[$ph] = $komponen[$ph];
    $row['Pembulatan'] = $rounded;
    $row['Jumlah Pendapatan'] = $totalPendapatan;
    $row['Max Pot. Kop'] = $maxPotKop;
    $row['Pot. Koperasi'] = $potKoperasi;
    $row['Jumlah Potongan'] = $totalPotongan;
    $row['Jumlah Yang Diterima'] = $netReceived;
    return $row;
}


// === SPREADSHEET ===
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$rowNum = 1;

// 0. Siapkan style array reusable (biar gampang reuse di bawah)
$styleHeader = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => '000000'],
        'size' => 12,
        'name' => 'Calibri'
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        'wrapText'   => true,
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
        'color' => ['rgb' => 'f7ff01'],
    ]
];
$styleBlockGuru = [
    'font' => ['bold'=>true, 'color'=>['rgb'=>'1565C0'], 'size'=>12],
    'fill' => ['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor'=>['rgb'=>'E3F2FD']]
];
$styleBlockKaryawan = [
    'font' => ['bold'=>true, 'color'=>['rgb'=>'388E3C'], 'size'=>12],
    'fill' => ['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor'=>['rgb'=>'E8F5E9']]
];
$styleBlockManajer = [
    'font' => ['bold'=>true, 'color'=>['rgb'=>'8E24AA'], 'size'=>12],
    'fill' => ['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor'=>['rgb'=>'F3E5F5']]
];
$styleJumlah = [
    'font' => ['bold'=>true, 'color'=>['rgb'=>'000000']],
    'fill' => ['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor'=>['rgb'=>'FFF176']],
    'borders'=>['top'=>['borderStyle'=>\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM]]
];
$styleUang = [
    'numberFormat' => ['formatCode' => '#,##0']
];


// 1) JUDUL UTAMA
$sheet->mergeCells("B{$rowNum}:M{$rowNum}");
$sheet->setCellValue("B{$rowNum}", 'SEKOLAH NUSAPUTERA');
$sheet->getStyle("B{$rowNum}")->getFont()->setBold(true)->setSize(16);
$rowNum++;
$sheet->mergeCells("B{$rowNum}:M{$rowNum}");
$sheet->setCellValue("B{$rowNum}", 'REKAP GAJI PER '.strtoupper(getIndonesianMonthName($bulan)).' '.$tahun);
$sheet->getStyle("B{$rowNum}")->getFont()->setBold(true)->setSize(14);
$rowNum += 2;

foreach (['guru'=>'Guru','karyawan'=>'Karyawan','manajer'=>'Manajer'] as $cat => $labelCat) {
    $blockStyle = $cat=='guru' ? $styleBlockGuru : ($cat=='karyawan' ? $styleBlockKaryawan : $styleBlockManajer);

    // 2) SUB-JUDUL BLOK
    $sheet->mergeCells("B{$rowNum}:M{$rowNum}");
    $sheet->setCellValue("B{$rowNum}", "REKAP GAJI {$labelCat}");
    $sheet->getStyle("B{$rowNum}:M{$rowNum}")->applyFromArray($blockStyle);
    $rowNum++;
    $sheet->setCellValue("B{$rowNum}", 'PERIODE : '.strtoupper(getIndonesianMonthName($bulan))." {$tahun}");
    $sheet->getStyle("B{$rowNum}")->getFont()->setItalic(true);
    $rowNum += 2;

    // 3) HEADER KOLOM
    $headers = $isSemua ? $headersRingkas : $headersDetail;
    $colStart = 2; // kolom B
    $headerRowNum = $rowNum;
    foreach ($headers as $i => $h) {
        $col = getExcelCol($colStart + $i);
        $sheet->setCellValue("{$col}{$rowNum}", strtoupper($h));
    }
    $sheet->getStyle(getExcelCol($colStart).$headerRowNum.":".
        getExcelCol($colStart+count($headers)-1).$headerRowNum)->applyFromArray($styleHeader);
    $rowNum++;

    // 4) ISI DATA + format uang
    if ($isSemua) {
        $startData = $rowNum;
        $no=1;
        foreach ($jenjangList as $kode=>$nama) {
            $t = fetchSummaryRow($conn,$kode,$bulan,$tahun,$cat,$earningPayheads,$PAYHEAD_GROUPS,$deductionPayheads);
            $col = $colStart;
            $sheet->setCellValue(getExcelCol($col++).$rowNum, $no++);
            $sheet->setCellValue(getExcelCol($col++).$rowNum, $nama);
            $sheet->setCellValue(getExcelCol($col++).$rowNum, $t['Gaji Pokok'] ?? 0);
            $sheet->setCellValue(getExcelCol($col++).$rowNum, $t['Indeks'] ?? 0);
            foreach ($earningPayheads as $ph)   $sheet->setCellValue(getExcelCol($col++).$rowNum, $t[$ph] ?? 0);
            foreach ($PAYHEAD_GROUPS as $grp)   $sheet->setCellValue(getExcelCol($col++).$rowNum, $t[$grp] ?? 0);
            foreach ($deductionPayheads as $ph) $sheet->setCellValue(getExcelCol($col++).$rowNum, $t[$ph] ?? 0);
            $sheet->setCellValue(getExcelCol($col++).$rowNum, $t['Pembulatan'] ?? 0);
            $sheet->setCellValue(getExcelCol($col++).$rowNum, $t['Jumlah Pendapatan'] ?? 0);
            $sheet->setCellValue(getExcelCol($col++).$rowNum, $t['Max Pot. Kop'] ?? 0);
            $sheet->setCellValue(getExcelCol($col++).$rowNum, $t['Pot. Koperasi'] ?? 0);
            $sheet->setCellValue(getExcelCol($col++).$rowNum, $t['Jumlah Potongan'] ?? 0);
            $sheet->setCellValue(getExcelCol($col++).$rowNum, $t['Jumlah Yang Diterima'] ?? 0);
            $rowNum++;
        }
        // STYLE JUMLAH
        $endData = $rowNum-1;
        $sheet->setCellValue(getExcelCol($colStart).$rowNum, 'JUMLAH');
        for ($i=3; $i < count($headersRingkas)+$colStart; $i++) {
            $col = getExcelCol($i);
            $sheet->setCellValue("{$col}{$rowNum}", "=SUM({$col}{$startData}:{$col}{$endData})");
            $sheet->getStyle("{$col}{$rowNum}")->applyFromArray($styleJumlah);
        }
        // Format kolom nominal
        foreach (range($colStart+2, $colStart+count($headersRingkas)-1) as $ci)
            $sheet->getStyle(getExcelCol($ci).($startData).":".getExcelCol($ci).($rowNum))->applyFromArray($styleUang);
        $rowNum += 2;
    } else {
        // DETAIL PER ANGGOTA
        $detail = fetchDetailRows($conn,$jenjang,$bulan,$tahun,$cat,$earningPayheads,$PAYHEAD_GROUPS,$deductionPayheads);
        $startData = $rowNum;
foreach ($detail as $r) {
    $col = $colStart;
    foreach ($headers as $h) {
        $sheet->setCellValue(getExcelCol($col++).$rowNum, $r[$h] ?? '');
    }
    $rowNum++;
}
$endData = $rowNum - 1; // <--- PENTING: baris terakhir data
$sheet->setCellValue(getExcelCol($colStart).$rowNum, 'JUMLAH');
for ($i=4; $i < count($headersDetail)+$colStart; $i++) {
    $col = getExcelCol($i);
    $sheet->setCellValue("{$col}{$rowNum}", "=SUM({$col}{$startData}:{$col}{$endData})");
}
$sheet->getStyle(getExcelCol($colStart).$rowNum.":".getExcelCol($colStart+count($headersDetail)-1).$rowNum)->applyFromArray($styleJumlah);
$rowNum += 2;
    }
}

// 5) STYLING AKHIR: border & auto-width
$maxCol = getExcelCol(($isSemua ? count($headersRingkas) : count($headersDetail)) + 1);
$sheet->getStyle("B1:{$maxCol}".($rowNum-1))
      ->getBorders()->getAllBorders()
      ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
for ($i=2; $i <= ($isSemua ? count($headersRingkas) : count($headersDetail)) + 1; $i++) {
    $sheet->getColumnDimension(getExcelCol($i))->setAutoSize(true);
}

// 6) OUTPUT
$filename = 'Rekap_Payroll_'.($jenjang===''?'Semua':$jenjang).'_'.$bulan.'_'.$tahun.'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0');
ob_end_clean();
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
