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

$PAYHEADS = array_merge($earningPayheads, $deductionPayheads);

// --- HEADER UTAMA (bisa diubah sesuai kebutuhan)
$commonHeaders = ['no', 'NAMA', 'KETERANGAN', 'JABATAN', 'NO. REKENING', 'Gaji Pokok', 'KENAIKAN INDEX'];
foreach ($earningPayheads as $ph)      $commonHeaders[] = $ph;
foreach ($PAYHEAD_GROUPS as $grp)      $commonHeaders[] = $grp;
foreach ($deductionPayheads as $ph)    $commonHeaders[] = $ph;
$commonHeaders = array_merge($commonHeaders, [
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
        $where = "a.role='M'";
        $params = [$jenjang, $bulan, $tahun];
        $types = 'sii';
    } else {
        $where = "a.kategori=? AND a.role<>'M'";
        $params = [$kategori, $jenjang, $bulan, $tahun];
        $types = 'ssii';
    }
    $where .= " AND a.jenjang=? AND pf.bulan=? AND pf.tahun=?";
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
          a.nip, a.nama, a.job_title, a.no_rekening, a.keterangan,
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
    $no = 1;
    while ($r = $res->fetch_assoc()) {
        $row = [
            'no' => $no++,
            'NAMA' => $r['nama'],
            'KETERANGAN' => $r['keterangan'],
            'JABATAN' => $r['job_title'],
            'NO. REKENING' => $r['no_rekening'],
            'Gaji Pokok' => $r['gaji_pokok'],
            'KENAIKAN INDEX' => $r['idx_amount'],
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

// 4. Helper TOTAL
function fetchSummaryRow($conn, $jenjang, $bulan, $tahun, $kategori, $earningPayheads, $PAYHEAD_GROUPS, $deductionPayheads) {
    $PAYHEADS = array_merge($earningPayheads, $deductionPayheads);
    if ($kategori == 'manajer') {
        $where = "a.role='M'";
        $params = [$jenjang, $bulan, $tahun];
        $types = 'sii';
    } else {
        $where = "a.kategori=? AND a.role<>'M'";
        $params = [$kategori, $jenjang, $bulan, $tahun];
        $types = 'ssii';
    }
    $where .= " AND a.jenjang=? AND pf.bulan=? AND pf.tahun=?";
    $sumCols = [
        "SUM(pf.gaji_pokok) AS gaji_pokok",
        "SUM(pf.salary_index_amount) AS idx_amount",
        "SUM(pf.potongan_koperasi) AS pot_koperasi"
    ];
    foreach ($earningPayheads as $ph) {
        $esc = $conn->real_escape_string($ph);
        $alias = 'ph_' . substr(md5($ph), 0, 8);
        $sumCols[] = "SUM(CASE WHEN d.nama_payhead='$esc' THEN d.amount ELSE 0 END) AS `$alias`";
    }
    foreach ($PAYHEAD_GROUPS as $grp) {
        $esc = $conn->real_escape_string($grp);
        $mrs = $conn->query("SELECT payhead_name FROM payhead_groups WHERE group_name='$esc'");
        $members = [];
        while ($m = $mrs->fetch_assoc()) $members[] = $conn->real_escape_string($m['payhead_name']);
        $mrs->free();
        $in = $members ? "'" . implode("','", $members) . "'" : "''";
        $alias = 'gr_' . substr(md5($grp), 0, 8);
        $sumCols[] = "SUM(CASE WHEN d.nama_payhead IN($in) THEN d.amount ELSE 0 END) AS `$alias`";
    }
    foreach ($deductionPayheads as $ph) {
        $esc = $conn->real_escape_string($ph);
        $alias = 'ph_' . substr(md5($ph), 0, 8);
        $sumCols[] = "SUM(CASE WHEN d.nama_payhead='$esc' THEN d.amount ELSE 0 END) AS `$alias`";
    }
    $sql = "
      SELECT
        " . implode(", ", $sumCols) . "
      FROM payroll_final pf
      JOIN anggota_sekolah a ON pf.id_anggota=a.id
      LEFT JOIN payroll_detail_final d ON pf.id = d.id_payroll_final
      WHERE $where
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $gajiPokok   = (float)$r['gaji_pokok'];
    $idxAmount   = (float)$r['idx_amount'];
    $potKoperasi = (float)$r['pot_koperasi'];
    $sumEarningsPH = 0; foreach ($earningPayheads as $ph) $sumEarningsPH += (float)$r['ph_' . substr(md5($ph), 0, 8)];
    $sumGroupPH = 0; foreach ($PAYHEAD_GROUPS as $grp) $sumGroupPH += (float)$r['gr_' . substr(md5($grp), 0, 8)];
    $totalPendapatan = $gajiPokok + $idxAmount + $sumEarningsPH + $sumGroupPH;
    $maxPotKop = $totalPendapatan * 0.65;
    $sumDeductionPH = 0; foreach ($deductionPayheads as $ph) $sumDeductionPH += (float)$r['ph_' . substr(md5($ph), 0, 8)];
    $totalPotongan = $potKoperasi + $sumDeductionPH;
    $netReceived = $totalPendapatan - $totalPotongan;
    $rounded = round($netReceived / 100) * 100;
    $row = [
        'Gaji Pokok' => $gajiPokok,
        'KENAIKAN INDEX' => $idxAmount
    ];
    foreach ($earningPayheads as $ph) $row[$ph] = $r['ph_' . substr(md5($ph), 0, 8)] ?? 0;
    foreach ($PAYHEAD_GROUPS as $grp) $row[$grp] = $r['gr_' . substr(md5($grp), 0, 8)] ?? 0;
    foreach ($deductionPayheads as $ph) $row[$ph] = $r['ph_' . substr(md5($ph), 0, 8)] ?? 0;
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
    // 2) SUB-JUDUL BLOK
    $sheet->mergeCells("B{$rowNum}:M{$rowNum}");
    $sheet->setCellValue("B{$rowNum}", "REKAP GAJI {$labelCat}");
    $sheet->getStyle("B{$rowNum}")->getFont()->setBold(true)->setSize(12);
    $rowNum++;
    $sheet->setCellValue("B{$rowNum}", 'PERIODE : '.strtoupper(getIndonesianMonthName($bulan))." {$tahun}");
    $rowNum += 2;

    // 3) HEADER KOLOM
    $headers = $commonHeaders;
    $colStart = 2; // kolom B
    foreach ($headers as $i => $h) {
        $col = getExcelCol($colStart + $i);
        $sheet->setCellValue("{$col}{$rowNum}", $h);
        $sheet->getStyle("{$col}{$rowNum}")
              ->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("{$col}{$rowNum}")
              ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
              ->getStartColor()->setRGB('333333');
        // tengah-kan header
        $sheet->getStyle("{$col}{$rowNum}")
              ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    }
    $rowNum++;

    // 4) ISI DATA atau TOTAL PER JENJANG
    if ($jenjang===''||strtolower($jenjang)==='semua') {
        // ringkas: satu baris per jenjang
        $startData = $rowNum;
        $no=1;
        foreach ($jenjangList as $kode=>$nama) {
            $t = fetchSummaryRow($conn,$kode,$bulan,$tahun,$cat,$earningPayheads,$PAYHEAD_GROUPS,$deductionPayheads);
            $col = $colStart;
            $sheet->setCellValue(getExcelCol($col++).$rowNum, $no++);
            $sheet->setCellValue(getExcelCol($col++).$rowNum, $nama);
            foreach ($headers as $h) {
                if (in_array($h,['no','NAMA'])) continue;
                $sheet->setCellValue(getExcelCol($col++).$rowNum, $t[$h] ?? 0);
            }
            $rowNum++;
        }
        // TOTAL BARIS DENGAN FORMULA
        $endData = $rowNum-1;
        $sheet->setCellValue("B{$rowNum}", 'JUMLAH');
        for ($i=2;$i < count($headers)+2; $i++) {
            $col = getExcelCol($i);
            // mulai kolom C (i=2), header 'KETERANGAN' tapi formula dijalankan hanya di numeric
            if ($i>=4) {
                $sheet->setCellValue("{$col}{$rowNum}",
                  "=SUM({$col}{$startData}:{$col}{$endData})"
                );
            }
            // bold dan warna bg
            $sheet->getStyle("{$col}{$rowNum}")
                  ->getFont()->setBold(true);
            $sheet->getStyle("{$col}{$rowNum}")
                  ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                  ->getStartColor()->setRGB('FFFF00');
        }
        $rowNum += 2;
    } else {
        // detail anggota + TOTAL
        $detail = fetchDetailRows($conn,$jenjang,$bulan,$tahun,$cat,$earningPayheads,$PAYHEAD_GROUPS,$deductionPayheads);
        $startData = $rowNum;
        foreach ($detail as $r) {
            $col = $colStart;
            foreach ($headers as $h) {
                $sheet->setCellValue(getExcelCol($col++).$rowNum, $r[$h] ?? '');
            }
            $rowNum++;
        }
        // TOTAL DENGAN FORMULA
        $endData = $rowNum-1;
        $sheet->setCellValue("B{$rowNum}", 'JUMLAH');
        for ($i=2;$i < count($headers)+2; $i++) {
            $col = getExcelCol($i);
            if ($i>=4) {
                $sheet->setCellValue("{$col}{$rowNum}",
                  "=SUM({$col}{$startData}:{$col}{$endData})"
                );
            }
            $sheet->getStyle("{$col}{$rowNum}")->getFont()->setBold(true);
            $sheet->getStyle("{$col}{$rowNum}")
                  ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                  ->getStartColor()->setRGB('FFFF00');
        }
        $rowNum += 2;
    }
}

// 5) STYLING AKHIR: border & auto-width
$maxCol = getExcelCol(count($commonHeaders)+1);
$sheet->getStyle("B1:{$maxCol}".($rowNum-1))
      ->getBorders()->getAllBorders()
      ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
for ($i=2; $i<= count($commonHeaders)+1; $i++) {
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
