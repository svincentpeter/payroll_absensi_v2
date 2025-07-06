<?php
// File: export_payroll_jenjang.php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__.'/../helpers.php';
require_once __DIR__.'/../koneksi.php';
require_once __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$clrEarn      = 'E3F2FD';
$clrDed       = 'FFEBEE';
$clrDedFont   = 'D32F2F';
$clrHdr       = 'FFF59D';
$clrJumlah    = 'C8E6C9';
$clrZebra     = 'FAFAFA';

$styleHdr = [
  'font' => ['bold'=>true],
  'alignment'=>[
      'horizontal'=>Alignment::HORIZONTAL_CENTER,
      'vertical'  =>Alignment::VERTICAL_CENTER,
      'wrapText'  =>true],
  'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$clrHdr]]
];
$styleEarnHdr         = $styleHdr;
$styleEarnHdr['fill']['startColor']['rgb'] = $clrEarn;

$styleDedHdr          = $styleHdr;
$styleDedHdr['fill']['startColor']['rgb']  = $clrDed;

$styleJumlah = [
  'font'=>['bold'=>true],
  'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$clrJumlah]],
  'borders'=>['top'=>['borderStyle'=>Border::BORDER_THICK]]
];
$styleMoney = ['numberFormat'=>['formatCode'=>'#,##0']];
$styleRedFont = ['font'=>['color'=>['rgb'=>$clrDedFont]]];

function getExcelCol(int $n): string{
  $s=''; while($n>0){ $m=($n-1)%26; $s=chr(65+$m).$s; $n=(int)(($n-$m)/26);} return $s;
}

$jenjang   = $_GET['jenjang'] ?? '';
$bulan     = intval($_GET['bulan'] ?? date('n'));
$tahun     = intval($_GET['tahun'] ?? date('Y'));
$tahunNext = $tahun+1;
$isSemua   = ($jenjang==='' || strtolower($jenjang)==='semua');

$FIXED_EARNINGS = [
  '__kgt_active'      => "Kenaikan Gaji {$tahun}/{$tahunNext}",
  '__honor_jam_lebih' => 'Honor Kelebihan Jam Mengajar'
];
$FIXED_DEDUCTIONS = [
  '__pot_absensi'     => 'Potongan Absensi'
];

$jenjangList=[]; $rs=$conn->query("SELECT kode_jenjang,nama_jenjang FROM jenjang_sekolah WHERE is_aktif=1");
while($j=$rs->fetch_assoc()) $jenjangList[$j['kode_jenjang']]=$j['nama_jenjang']; $rs->free();

$PAYHEAD_GROUPS=[];$rs=$conn->query("SELECT group_name FROM payhead_groups
                                     WHERE jenis='earnings'
                                     GROUP BY group_name
                                     ORDER BY MIN(sort_order),group_name");
while($g=$rs->fetch_assoc()) $PAYHEAD_GROUPS[]=$g['group_name']; $rs->free();

$groupMembers=[];$rs=$conn->query("SELECT DISTINCT payhead_name FROM payhead_groups");
while($r=$rs->fetch_assoc()) $groupMembers[]=$r['payhead_name']; $rs->free();
$inList=$groupMembers?"'".implode("','",array_map([$conn,'real_escape_string'],$groupMembers))."'":"''";

$earningPayheads=[];$rs=$conn->query("SELECT DISTINCT nama_payhead FROM payroll_detail_final
                                      WHERE jenis='earnings' AND nama_payhead NOT IN($inList)
                                      ORDER BY nama_payhead");
while($r=$rs->fetch_assoc()) $earningPayheads[]=$r['nama_payhead']; $rs->free();

$deductionPayheads=[];$rs=$conn->query("SELECT DISTINCT nama_payhead FROM payroll_detail_final
                                        WHERE jenis='deductions' AND nama_payhead NOT IN($inList)
                                        ORDER BY nama_payhead");
while($r=$rs->fetch_assoc()) $deductionPayheads[]=$r['nama_payhead']; $rs->free();

$headersDetail = array_merge(
  ['NIP','Nama','Keterangan','Gaji Pokok','Indeks'],
  array_values($FIXED_EARNINGS),
  $PAYHEAD_GROUPS,
  $earningPayheads,
  array_values($FIXED_DEDUCTIONS),
  $deductionPayheads,
  ['Pembulatan','Jumlah Pendapatan','Max Pot. Kop','Pot. Koperasi','Jumlah Potongan','Jumlah Yang Diterima']
);
$headersRingkas = array_merge(
  ['No','Jenjang','Gaji Pokok','Indeks'],
  array_values($FIXED_EARNINGS),
  $PAYHEAD_GROUPS,
  $earningPayheads,
  array_values($FIXED_DEDUCTIONS),
  $deductionPayheads,
  ['Pembulatan','Jumlah Pendapatan','Max Pot. Kop','Pot. Koperasi','Jumlah Potongan','Jumlah Yang Diterima']
);

function fetchDetailRows(
  mysqli $conn,$jenjang,$bulan,$tahun,$kategori,
  $earningPayheads,$PAYHEAD_GROUPS,$deductionPayheads,
  $FIXED_EARNINGS,$FIXED_DEDUCTIONS
){
  $PAYHEADS=array_merge($earningPayheads,$deductionPayheads);
  if($kategori==='manajer'){
    $where="a.role='M' AND a.jenjang=? AND pf.bulan=? AND pf.tahun=?";
    $types='sii'; $params=[$jenjang,$bulan,$tahun];
  }else{
    $where="a.kategori=? AND a.role<>'M' AND a.jenjang=? AND pf.bulan=? AND pf.tahun=?";
    $types='ssii'; $params=[$kategori,$jenjang,$bulan,$tahun];
  }

  $subPH=[];$outerPH=[]; foreach($PAYHEADS as $ph){
    $alias='ph_'.substr(md5($ph),0,8);
    $esc=$conn->real_escape_string($ph);
    $subPH[]  ="SUM(CASE WHEN d.nama_payhead='$esc' THEN d.amount ELSE 0 END) AS `$alias`";
    $outerPH[]=", agg.`$alias`";
  }
  $subGR=[];$outerGR=[]; foreach($PAYHEAD_GROUPS as $grp){
    $alias='gr_'.substr(md5($grp),0,8);
    $mems=[];$m=$conn->query("SELECT payhead_name FROM payhead_groups WHERE group_name='".$conn->real_escape_string($grp)."'");
    while($x=$m->fetch_assoc()) $mems[]=$conn->real_escape_string($x['payhead_name']); $m->free();
    $in=$mems?"'".implode("','",$mems)."'":"''";
    $subGR[]  ="SUM(CASE WHEN d.nama_payhead IN($in) THEN d.amount ELSE 0 END) AS `$alias`";
    $outerGR[]=", agg.`$alias`";
  }
  $subSelect = implode(",\n    ",array_merge($subPH,$subGR));
  $outerSelect=implode(" ",array_merge($outerPH,$outerGR));

  $sql="
    SELECT a.nip,a.nama,a.remark AS keterangan,
           pf.gaji_pokok,pf.salary_index_amount AS idx_amount,
           pf.honor_jam_lebih,pf.potongan_absensi,pf.potongan_koperasi,
           (SELECT IFNULL(SUM(k.jumlah),0)
              FROM kenaikan_gaji_tahunan k
             WHERE k.id_anggota=pf.id_anggota
               AND k.status='aktif'
               AND k.pindah_ke_lain_lain=0
               AND pf.tgl_payroll BETWEEN k.tanggal_mulai AND k.tanggal_berakhir
           ) AS kgt_active
           $outerSelect
    FROM payroll_final pf
    JOIN anggota_sekolah a ON pf.id_anggota=a.id
    LEFT JOIN (
        SELECT id_payroll_final, $subSelect
          FROM payroll_detail_final d
      GROUP BY id_payroll_final
    ) agg ON pf.id=agg.id_payroll_final
    WHERE $where
    ORDER BY a.nama";
  $stmt=$conn->prepare($sql); $stmt->bind_param($types,...$params); $stmt->execute();
  $res=$stmt->get_result(); $rows=[];
  while($r=$res->fetch_assoc()){
    $row=[
      'NIP'=>$r['nip'],'Nama'=>$r['nama'],'Keterangan'=>$r['keterangan'],
      'Gaji Pokok'=>$r['gaji_pokok'],'Indeks'=>$r['idx_amount'],
      $FIXED_EARNINGS['__kgt_active']      =>$r['kgt_active'],
      $FIXED_EARNINGS['__honor_jam_lebih'] =>$r['honor_jam_lebih']
    ];
    foreach($PAYHEAD_GROUPS as $grp)   $row[$grp]=$r['gr_'.substr(md5($grp),0,8)]??0;
    foreach($earningPayheads as $ph)   $row[$ph]=$r['ph_'.substr(md5($ph),0,8)]??0;
    $row[$FIXED_DEDUCTIONS['__pot_absensi']]=$r['potongan_absensi'];
    foreach($deductionPayheads as $ph) $row[$ph]=$r['ph_'.substr(md5($ph),0,8)]??0;

    $sumEarn=0;foreach($earningPayheads as $ph)$sumEarn+=$row[$ph];
    $sumGrp=0; foreach($PAYHEAD_GROUPS  as $g)$sumGrp+=$row[$g];
    $sumDed=0; foreach($deductionPayheads as $ph)$sumDed+=$row[$ph];

    $totalPend = $r['gaji_pokok']+$r['idx_amount']+$r['kgt_active']+$r['honor_jam_lebih']+$sumEarn+$sumGrp;
    $totalPot  = $r['potongan_koperasi']+$r['potongan_absensi']+$sumDed;
    $net=$totalPend-$totalPot; $rounded=round($net/100)*100;

    $row += [
      'Pembulatan'=>$rounded,
      'Jumlah Pendapatan'=>$totalPend,
      'Max Pot. Kop'=>$totalPend*0.65,
      'Pot. Koperasi'=>$r['potongan_koperasi'],
      'Jumlah Potongan'=>$totalPot,
      'Jumlah Yang Diterima'=>$net
    ];
    $rows[]=$row;
  }$stmt->close();
  return $rows;
}

function fetchSummaryRow(
  mysqli $conn,$jenjang,$bulan,$tahun,$kategori,
  $earningPayheads,$PAYHEAD_GROUPS,$deductionPayheads,
  $FIXED_EARNINGS,$FIXED_DEDUCTIONS
){
  if($kategori==='manajer'){
    $where="a.role='M' AND a.jenjang=? AND pf.bulan=? AND pf.tahun=?";
    $types='sii'; $params=[$jenjang,$bulan,$tahun];
  }else{
    $where="a.kategori=? AND a.role<>'M' AND a.jenjang=? AND pf.bulan=? AND pf.tahun=?";
    $types='ssii'; $params=[$kategori,$jenjang,$bulan,$tahun];
  }
  $sql="SELECT
           SUM(pf.gaji_pokok)          AS gaji_pokok,
           SUM(pf.salary_index_amount) AS idx_amount,
           SUM(pf.honor_jam_lebih)     AS honor_jam,
           SUM(pf.potongan_absensi)    AS pot_absensi,
           SUM(pf.potongan_koperasi)   AS pot_koperasi,
           SUM((
               SELECT IFNULL(SUM(k.jumlah),0) FROM kenaikan_gaji_tahunan k
                WHERE k.id_anggota=pf.id_anggota AND k.status='aktif'
                  AND k.pindah_ke_lain_lain=0
                  AND pf.tgl_payroll BETWEEN k.tanggal_mulai AND k.tanggal_berakhir
           )) AS kgt_active
        FROM payroll_final pf
        JOIN anggota_sekolah a ON pf.id_anggota=a.id
       WHERE $where";
  $st=$conn->prepare($sql); $st->bind_param($types,...$params); $st->execute();
  $r=$st->get_result()->fetch_assoc(); $st->close();

  $komp=[];
  foreach($earningPayheads as $ph){
    $q="SELECT SUM(amount) FROM (
        SELECT SUM(d.amount) AS amount
          FROM payroll_final pf JOIN anggota_sekolah a ON pf.id_anggota=a.id
          JOIN payroll_detail_final d ON pf.id=d.id_payroll_final
         WHERE $where AND d.nama_payhead=? GROUP BY pf.id) sub";
    $p=$conn->prepare($q); $p->bind_param($types.'s',...array_merge($params,[$ph])); $p->execute();
    $komp[$ph]=$p->get_result()->fetch_row()[0]??0; $p->close();
  }
  foreach($PAYHEAD_GROUPS as $grp){
    $mems=[];$m=$conn->query("SELECT payhead_name FROM payhead_groups WHERE group_name='".$conn->real_escape_string($grp)."'");
    while($x=$m->fetch_assoc()) $mems[]=$conn->real_escape_string($x['payhead_name']); $m->free();
    $in=$mems?"'".implode("','",$mems)."'":"''";
    $q="SELECT SUM(amount) FROM (
        SELECT SUM(d.amount) AS amount
          FROM payroll_final pf JOIN anggota_sekolah a ON pf.id_anggota=a.id
          JOIN payroll_detail_final d ON pf.id=d.id_payroll_final
         WHERE $where AND d.nama_payhead IN($in) GROUP BY pf.id) sub";
    $p=$conn->prepare($q); $p->bind_param($types,...$params); $p->execute();
    $komp[$grp]=$p->get_result()->fetch_row()[0]??0; $p->close();
  }
  foreach($deductionPayheads as $ph){
    $q="SELECT SUM(amount) FROM (
        SELECT SUM(d.amount) AS amount
          FROM payroll_final pf JOIN anggota_sekolah a ON pf.id_anggota=a.id
          JOIN payroll_detail_final d ON pf.id=d.id_payroll_final
         WHERE $where AND d.nama_payhead=? GROUP BY pf.id) sub";
    $p=$conn->prepare($q); $p->bind_param($types.'s',...array_merge($params,[$ph])); $p->execute();
    $komp[$ph]=$p->get_result()->fetch_row()[0]??0; $p->close();
  }

  $sumEarn=0;foreach($earningPayheads as $ph)$sumEarn+=$komp[$ph];
  $sumGrp=0; foreach($PAYHEAD_GROUPS as $g)$sumGrp+=$komp[$g];
  $sumDed=0; foreach($deductionPayheads as $ph)$sumDed+=$komp[$ph];

  $totalPend = $r['gaji_pokok']+$r['idx_amount']+$r['kgt_active']+$r['honor_jam']+$sumEarn+$sumGrp;
  $totalPot  = $r['pot_koperasi']+$r['pot_absensi']+$sumDed;
  $net=$totalPend-$totalPot; $rounded=round($net/100)*100;

  $row=[
    'Gaji Pokok'=>$r['gaji_pokok'],
    'Indeks'=>$r['idx_amount'],
    $FIXED_EARNINGS['__kgt_active']      =>$r['kgt_active'],
    $FIXED_EARNINGS['__honor_jam_lebih'] =>$r['honor_jam']
  ];
  foreach($PAYHEAD_GROUPS as $g) $row[$g]=$komp[$g];
  foreach($earningPayheads as $ph) $row[$ph]=$komp[$ph];
  $row[$FIXED_DEDUCTIONS['__pot_absensi']]=$r['pot_absensi'];
  foreach($deductionPayheads as $ph) $row[$ph]=$komp[$ph];

  $row += [
    'Pembulatan'=>$rounded,
    'Jumlah Pendapatan'=>$totalPend,
    'Max Pot. Kop'=>$totalPend*0.65,
    'Pot. Koperasi'=>$r['pot_koperasi'],
    'Jumlah Potongan'=>$totalPot,
    'Jumlah Yang Diterima'=>$net
  ];
  return $row;
}

$spreadsheet=new Spreadsheet();
$sheet=$spreadsheet->getActiveSheet();
$rowNum=1;

$sheet->mergeCells("B{$rowNum}:M{$rowNum}")
      ->setCellValue("B{$rowNum}",'SEKOLAH NUSAPUTERA')
      ->getStyle("B{$rowNum}")->getFont()->setBold(true)->setSize(16);
$rowNum++;
$sheet->mergeCells("B{$rowNum}:M{$rowNum}")
      ->setCellValue("B{$rowNum}",'REKAP GAJI PER '.strtoupper(getIndonesianMonthName($bulan))." $tahun")
      ->getStyle("B{$rowNum}")->getFont()->setBold(true)->setSize(14);
$rowNum+=2;

foreach(['guru'=>'GURU','karyawan'=>'KARYAWAN','manajer'=>'MANAJER'] as $cat=>$title){
  $sheet->setCellValue("B{$rowNum}",'REKAP GAJI '.$title)
        ->getStyle("B{$rowNum}")->getFont()->setBold(true)->setSize(12);
  $rowNum++;
  $sheet->setCellValue("B{$rowNum}",'PERIODE : '.strtoupper(getIndonesianMonthName($bulan))." $tahun");
  $rowNum+=2;

  $headers = $isSemua
    ? $headersRingkas
    : $headersDetail;
    
  $colStart      = 2;
  $hdrRow        = $rowNum;
  $deductionCols = [];

  foreach($headers as $i=>$h){
    $colLetter = getExcelCol($colStart+$i);
    $sheet->setCellValue("{$colLetter}{$hdrRow}",strtoupper($h));
    if(in_array($h,$deductionPayheads,true) || $h===$FIXED_DEDUCTIONS['__pot_absensi']){
      $sheet->getStyle("{$colLetter}{$hdrRow}")->applyFromArray($styleDedHdr);
      $deductionCols[]=$colLetter;
    }elseif(in_array($h,$earningPayheads,true) || in_array($h,$PAYHEAD_GROUPS,true)
            || in_array($h,$FIXED_EARNINGS,true)){
      $sheet->getStyle("{$colLetter}{$hdrRow}")->applyFromArray($styleEarnHdr);
    }else{
      $sheet->getStyle("{$colLetter}{$hdrRow}")->applyFromArray($styleHdr);
    }
  }
  $rowNum++;

  if($isSemua){
    $start=$rowNum; $no=1;
    foreach($jenjangList as $kode=>$nama){
      $t=fetchSummaryRow($conn,$kode,$bulan,$tahun,$cat,$earningPayheads,$PAYHEAD_GROUPS,$deductionPayheads,
                         $FIXED_EARNINGS,$FIXED_DEDUCTIONS);
      $col=$colStart;
      $sheet->setCellValue(getExcelCol($col++).$rowNum,$no++);
      $sheet->setCellValue(getExcelCol($col++).$rowNum,$nama);
      $sheet->setCellValue(getExcelCol($col++).$rowNum,$t['Gaji Pokok']);
      $sheet->setCellValue(getExcelCol($col++).$rowNum,$t['Indeks']);
      foreach($FIXED_EARNINGS as $lbl)                    $sheet->setCellValue(getExcelCol($col++).$rowNum,$t[$lbl]);
      foreach($PAYHEAD_GROUPS  as $grp)                   $sheet->setCellValue(getExcelCol($col++).$rowNum,$t[$grp]);
      foreach($earningPayheads as $ph)                    $sheet->setCellValue(getExcelCol($col++).$rowNum,$t[$ph]);
      $sheet->setCellValue(getExcelCol($col++).$rowNum,$t[$FIXED_DEDUCTIONS['__pot_absensi']]);
      foreach($deductionPayheads as $ph)                  $sheet->setCellValue(getExcelCol($col++).$rowNum,$t[$ph]);
      foreach(['Pembulatan','Jumlah Pendapatan','Max Pot. Kop','Pot. Koperasi','Jumlah Potongan','Jumlah Yang Diterima'] as $k)
          $sheet->setCellValue(getExcelCol($col++).$rowNum,$t[$k]);
      if($no%2==0) $sheet->getStyle("B{$rowNum}:".getExcelCol($col-1).$rowNum)
                         ->getFill()->setFillType(Fill::FILL_SOLID)
                         ->getStartColor()->setRGB($clrZebra);
      $rowNum++;
    }
    $end=$rowNum-1;
    $sheet->setCellValue(getExcelCol($colStart).$rowNum,'JUMLAH');
    for($i=$colStart+2;$i<$colStart+count($headers);$i++){
      $c=getExcelCol($i);
      $sheet->setCellValue("{$c}{$rowNum}","=SUM({$c}{$start}:{$c}{$end})")
            ->getStyle("{$c}{$rowNum}")->applyFromArray($styleJumlah);
    }
    foreach($deductionCols as $c)
      $sheet->getStyle("{$c}{$start}:{$c}{$rowNum}")->applyFromArray($styleRedFont);
    $rowNum+=2;
  }else{
    $details = fetchDetailRows($conn,$jenjang,$bulan,$tahun,$cat,$earningPayheads,$PAYHEAD_GROUPS,$deductionPayheads,
                               $FIXED_EARNINGS,$FIXED_DEDUCTIONS);
    $start=$rowNum;
    foreach($details as $r){
      $col=$colStart;
      foreach($headers as $h) $sheet->setCellValue(getExcelCol($col++).$rowNum,$r[$h]??'');
      if((($rowNum-$start)%2)==1)
        $sheet->getStyle("B{$rowNum}:".getExcelCol($col-1).$rowNum)
              ->getFill()->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setRGB($clrZebra);
      $rowNum++;
    }
    $end=$rowNum-1;
    $sheet->setCellValue(getExcelCol($colStart).$rowNum,'JUMLAH');
    for($i=$colStart+3;$i<$colStart+count($headers);$i++){
      $c=getExcelCol($i);
      $sheet->setCellValue("{$c}{$rowNum}","=SUM({$c}{$start}:{$c}{$end})");
    }
    $sheet->getStyle(getExcelCol($colStart).$rowNum.':'.getExcelCol($colStart+count($headers)-1).$rowNum)
          ->applyFromArray($styleJumlah);
    foreach($deductionCols as $c)
      $sheet->getStyle("{$c}{$start}:{$c}{$rowNum}")->applyFromArray($styleRedFont);
    $rowNum+=2;
  }
}

$maxCol = getExcelCol(($isSemua?count($headersRingkas):count($headersDetail))+1);
$sheet->getStyle("B1:{$maxCol}".($rowNum-1))->getBorders()->getAllBorders()
      ->setBorderStyle(Border::BORDER_THIN);
for($i=2;$i<=($isSemua?count($headersRingkas):count($headersDetail))+1;$i++){
  $c=getExcelCol($i); $sheet->getColumnDimension($c)->setAutoSize(true);
  if($i>=($isSemua?4:5))
    $sheet->getStyle("{$c}3:{$c}".($rowNum-1))->applyFromArray($styleMoney);
}

$filename='Rekap_Payroll_'.($jenjang===''?'Semua':$jenjang)."_{$bulan}_{$tahun}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0'); ob_end_clean();
$writer=new Xlsx($spreadsheet); $writer->save('php://output'); exit;
?>
