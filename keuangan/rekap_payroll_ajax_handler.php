<?php
// File: /payroll_absensi_v2/keuangan/rekap_payroll_ajax_handler.php
if (!isset($conn)) die('No direct access.');

global $earningPayheads, $deductionPayheads, $PAYHEAD_GROUPS, $PAYHEADS;

// Pastikan param kategori
$kategori = sanitize_input($_GET['category'] ?? '');

$draw   = intval($_POST['draw']   ?? 0);
$start  = intval($_POST['start']  ?? 0);
$length = intval($_POST['length'] ?? 10);
$search = sanitize_input($_POST['search']['value'] ?? '');

// Build WHERE & params
if (strtolower($jenjang) === 'semua') {
  send_response(400, 'Permintaan tidak valid untuk mode semua jenjang.');
} else {
  $sqlWhere = "WHERE a.jenjang=? AND a.kategori=? AND a.role <> 'M' AND pf.bulan=? AND pf.tahun=?";
  $params   = [$jenjang, $kategori, $bulan, $tahun];
  $types    = "ssii";
}
if ($search !== '') {
  $sqlWhere .= " AND (a.nama LIKE ? OR a.nip LIKE ?)";
  $params[]  = "%$search%";
  $params[]  = "%$search%";
  $types    .= "ss";
}

// Hitung total
$stmt = $conn->prepare("
  SELECT COUNT(*) AS cnt
    FROM payroll_final pf
    JOIN anggota_sekolah a ON pf.id_anggota=a.id
  $sqlWhere
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$cnt = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();
$recordsTotal = intval($cnt);

// Sub-select payhead & grouped payhead
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

// Hash untuk kolom tetap (harus sama dengan frontend!)
$hashKgtActive = substr(md5('__kgt_active'),0,8);
$hashHonorJamLebih = substr(md5('__honor_jam_lebih'),0,8);
$hashPotAbsensi = substr(md5('__pot_absensi'),0,8);

// Query utama
$sqlData = "
  SELECT
    a.nip,
    a.nama,
    a.job_title     AS keterangan,
    pf.gaji_pokok,
    pf.salary_index_amount AS idx_amount
    $outerSelect,
    pf.honor_jam_lebih     AS honor_jam_lebih,
    pf.potongan_absensi    AS pot_absensi,
    IFNULL((
      SELECT SUM(jumlah)
        FROM kenaikan_gaji_tahunan kt
       WHERE kt.id_anggota = a.id
         AND kt.status     = 'aktif'
         AND pf.tgl_payroll BETWEEN kt.tanggal_mulai AND kt.tanggal_berakhir
    ),0)                    AS kgt_active,
    pf.potongan_koperasi   AS pot_koperasi,
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
  LIMIT ?, ?
";
$typesData   = $types . "ii";
$paramsData  = array_merge($params, [$start, $length]);

$stmt = $conn->prepare($sqlData);
$stmt->bind_param($typesData, ...$paramsData);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($r = $res->fetch_assoc()) {
  $gajiPokok   = (float)$r['gaji_pokok'];
  $idxAmount   = (float)$r['idx_amount'];
  $honorJam    = (float)$r['honor_jam_lebih'];
  $potAbsen    = (float)$r['pot_absensi'];
  $potKoperasi = (float)$r['pot_koperasi'];
  $kgtActive   = (float)$r['kgt_active'];

  $sumEarningsPH = 0;
  foreach ($earningPayheads as $ph) {
    $alias = 'ph_' . substr(md5($ph), 0, 8);
    $sumEarningsPH += (float)$r[$alias];
  }
  $sumGroupPH = 0;
  foreach ($PAYHEAD_GROUPS as $grp) {
    $alias = 'gr_' . substr(md5($grp), 0, 8);
    $sumGroupPH += (float)($r[$alias] ?? 0);
  }
  $sumDeductionPH = 0;
  foreach ($deductionPayheads as $ph) {
    $alias = 'ph_' . substr(md5($ph), 0, 8);
    $sumDeductionPH += (float)$r[$alias];
  }

  $totalPendapatan = $gajiPokok + $idxAmount + $sumEarningsPH + $sumGroupPH + $kgtActive + $honorJam;
  $totalPotongan = $potKoperasi + $potAbsen + $sumDeductionPH;
  $maxPotKop = $totalPendapatan * 0.65;
  $netReceived = $totalPendapatan - $totalPotongan;
  $rounded = round($netReceived / 100) * 100;

  // Output row sesuai kolom frontend!
  $row = [
    'nip'        => htmlspecialchars($r['nip']),
    'nama'       => htmlspecialchars($r['nama']),
    'keterangan' => htmlspecialchars($r['keterangan']),
    'gaji_pokok' => formatNominal($gajiPokok),
    'idx_amount' => formatNominal($idxAmount),
    // Fixed earning (kenaikan gaji tahunan & honor jam lebih)
    'ph_'.$hashKgtActive      => formatNominal($kgtActive),
    'ph_'.$hashHonorJamLebih  => formatNominal($honorJam)
  ];
  foreach ($PAYHEAD_GROUPS as $grp) {
    $alias = 'gr_' . substr(md5($grp), 0, 8);
    $row[$alias] = formatNominal($r[$alias] ?? 0);
  }
  foreach ($earningPayheads as $ph) {
    $alias = 'ph_' . substr(md5($ph), 0, 8);
    $row[$alias] = formatNominal($r[$alias] ?? 0);
  }
  // Fixed deduction (potongan absensi)
  $row['d_'.$hashPotAbsensi] = formatNominal($potAbsen);
  foreach ($deductionPayheads as $ph) {
    $alias = 'd_' . substr(md5($ph), 0, 8);
    // Ambil dari kolom payhead (harus prefix d_, bukan ph_)
    $row[$alias] = formatNominal($r['ph_' . substr(md5($ph), 0, 8)] ?? 0);
  }
  $row += [
    'pembulatan'       => formatNominal($rounded),
    'total_pendapatan' => formatNominal($totalPendapatan),
    'max_pot_kop'      => formatNominal($maxPotKop),
    'pot_koperasi'     => formatNominal($potKoperasi),
    'total_potongan'   => formatNominal($totalPotongan),
    'net_received'     => formatNominal($netReceived)
  ];
  $data[] = $row;
}
$stmt->close();

echo json_encode([
  'draw'            => $draw,
  'recordsTotal'    => $recordsTotal,
  'recordsFiltered' => $recordsTotal,
  'data'            => $data
], JSON_UNESCAPED_UNICODE);

?>
