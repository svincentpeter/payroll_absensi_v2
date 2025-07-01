<?php
// File: /payroll_absensi_v2/sdm/proses_simpan_payroll.php

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../koneksi.php';

start_session_safe();
init_error_handling();
authorize(['M:SDM']);

function getJenisPayhead($id_payhead, $conn) {
    $jenis = null;                    // ← add this line
    $stmt  = $conn->prepare("SELECT jenis FROM payheads WHERE id = ?");
    $stmt->bind_param("i", $id_payhead);
    $stmt->execute();
    $stmt->bind_result($jenis);
    $stmt->fetch();
    $stmt->close();
    return $jenis;
}


// 1. Ambil input dasar
$id_anggota      = intval($_POST['empcode']       ?? 0);
$bulan           = intval($_POST['selectedMonth'] ?? 0);
$tahun           = intval($_POST['selectedYear']  ?? 0);
$no_rekening     = trim($_POST['no_rekening']     ?? '');
$catatan         = trim($_POST['catatan']         ?? '');

// Tanggal payroll: pakai POST atau sekarang
if (!empty($_POST['tgl_payroll']) && trim($_POST['tgl_payroll']) !== '') {
    $tgl_payroll = trim($_POST['tgl_payroll']);
} else {
    $tgl_payroll = date('Y-m-d H:i:s');
}

// Potongan absensi (hilangkan titik ribuan)
$potongan_absensi = isset($_POST['potongan_absensi'])
    ? intval(str_replace('.', '', $_POST['potongan_absensi']))
    : 0;

// 2. Upsert payroll DRAFT
//   asumsi sudah ada UNIQUE KEY (id_anggota,bulan,tahun,status)
$sql = "
  INSERT INTO payroll
    (id_anggota, bulan, tahun,
     no_rekening, catatan, tgl_payroll,
     potongan_absensi, status)
  VALUES
    (?, ?, ?, ?, ?, ?, ?, 'draft')
  ON DUPLICATE KEY UPDATE
    no_rekening      = VALUES(no_rekening),
    catatan          = VALUES(catatan),
    tgl_payroll      = VALUES(tgl_payroll),
    potongan_absensi = VALUES(potongan_absensi),
    status           = 'draft'
";
$stmt = $conn->prepare($sql);
$stmt->bind_param(
  'iiisssi',
  $id_anggota,
  $bulan,
  $tahun,
  $no_rekening,
  $catatan,
  $tgl_payroll,
  $potongan_absensi
);
$stmt->execute();
$stmt->close();

// 3. Ambil ID payroll draft (insert_id = 0 jika update)
$id_payroll = $conn->insert_id;
if ($id_payroll === 0) {
    $rs = $conn->prepare("
      SELECT id FROM payroll
       WHERE id_anggota = ? AND bulan = ? AND tahun = ? AND status = 'draft'
       LIMIT 1
    ");
    $rs->bind_param('iii', $id_anggota, $bulan, $tahun);
    $rs->execute();
    $rs->bind_result($id_payroll);
    $rs->fetch();
    $rs->close();
}

// 4. Simpan komponen payheads DRAFT
$pay_amounts = json_decode($_POST['pay_amounts'], true) ?? [];
$remarks     = $_POST['remarks']     ?? [];
$rapels      = json_decode($_POST['rapels'], true) ?? [];

if ($id_payroll > 0) {
    // Hapus dulu semua draft lama
    $conn->query("
      DELETE FROM employee_payheads
       WHERE id_anggota = {$id_anggota}
         AND status     = 'draft'
    ");

    // Siapkan direktori upload
    $uploadRoot = __DIR__ . "/../../uploads/payroll_docs";
    $dbRoot     = "/uploads/payroll_docs";

    foreach ($pay_amounts as $id_payhead => $amount) {
        $jenis    = getJenisPayhead($id_payhead, $conn);
        $remark   = trim($remarks[$id_payhead] ?? '');
        $is_rapel = intval($rapels[$id_payhead] ?? 0);

        // Persiapkan path
        $dirLocal  = "{$uploadRoot}/{$id_anggota}/{$tahun}_{$bulan}/";
        $dirDB     = "{$dbRoot}/{$id_anggota}/{$tahun}_{$bulan}/";
        if (!is_dir($dirLocal)) {
            mkdir($dirLocal, 0775, true);
        }

        $docPath = null;
        if (!empty($_FILES['upload_file']['name'][$id_payhead])) {
            $fn      = basename($_FILES['upload_file']['name'][$id_payhead]);
            $ext     = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
            $uniq    = time() . rand(1000,9999);
            $newName = "payhead_{$id_anggota}_{$id_payhead}_{$tahun}{$bulan}_{$uniq}.{$ext}";
            $tolocal = $dirLocal . $newName;
            if (move_uploaded_file($_FILES['upload_file']['tmp_name'][$id_payhead], $tolocal)) {
                $docPath = $dirDB . $newName;
            }
        }

        $sql2 = "
          INSERT INTO employee_payheads
            (id_anggota, id_payhead, jenis, amount,
             status, remarks, support_doc_path, is_rapel)
          VALUES
            (?, ?, ?, ?, 'draft', ?, ?, ?)
        ";
        $st2 = $conn->prepare($sql2);
        $st2->bind_param(
          'iisdsis',
          $id_anggota,
          $id_payhead,
          $jenis,
          $amount,
          $remark,
          $docPath,
          $is_rapel
        );
        $st2->execute();
        $st2->close();
    }
}

// 5. Simpan Kenaikan Gaji Tahunan (cek dulu ada yg aktif?)
if (!empty($_POST['chkKenaikanGajiTahunan'])) {
    $rid = intval($_POST['ranking_id'] ?? 0);
    if ($rid <= 0) {
        send_response(1, 'Ranking kenaikan wajib dipilih.');
    }
    // cek duplikasi
    $tglMulai = date('Y-m-d', strtotime($tgl_payroll));
    $tglAkhir = date('Y-m-d', strtotime("$tglMulai +1 year -1 day"));
    $chk = $conn->prepare("
      SELECT id FROM kenaikan_gaji_tahunan
       WHERE id_anggota = ? AND status='aktif'
         AND tanggal_mulai <= ? AND tanggal_berakhir >= ?
       LIMIT 1
    ");
    $chk->bind_param('iss', $id_anggota, $tglAkhir, $tglMulai);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows === 0) {
        // ambil nama & jumlah dari ranking
        $row = $conn->query("SELECT nama_ranking, jumlah FROM ranking_kenaikan WHERE id={$rid} LIMIT 1")
                    ->fetch_assoc();
        if (!$row) {
            send_response(1, 'Ranking tidak valid.');
        }
        $nama  = $conn->real_escape_string($row['nama_ranking']);
        $jumlah= floatval($row['jumlah']);
        $ins = $conn->prepare("
          INSERT INTO kenaikan_gaji_tahunan
            (id_anggota, nama_kenaikan, jumlah,
             tanggal_mulai, tanggal_berakhir,
             status, ranking_id, dibuat_pada)
          VALUES
            (?, ?, ?, ?, ?, 'aktif', ?, NOW())
        ");
        $ins->bind_param(
          'isssii',
          $id_anggota,
          $nama,
          $jumlah,
          $tglMulai,
          $tglAkhir,
          $rid
        );
        $ins->execute();
        $ins->close();
    }
    $chk->close();
}

// 6. Update honor_jam_lebih di header payroll
$stmtH = $conn->prepare("
  SELECT SUM(total_honor) AS honor_jam_lebih
    FROM kelebihan_jam_mengajar
   WHERE id_anggota = ? AND bulan = ? AND tahun = ? AND is_final = 1
");
$stmtH->bind_param('iii', $id_anggota, $bulan, $tahun);
$stmtH->execute();
$resH = $stmtH->get_result()->fetch_assoc();
$stmtH->close();

$hjl = floatval($resH['honor_jam_lebih'] ?? 0);
$upd = $conn->prepare("
  UPDATE payroll
     SET honor_jam_lebih = ?
   WHERE id = ?
");
$upd->bind_param('di', $hjl, $id_payroll);
$upd->execute();
$upd->close();

// 7. Generate semua detail (panggil SP)
$sp = $conn->prepare("CALL sp_generate_payroll(?,?)");
$sp->bind_param('ii', $bulan, $tahun);
$sp->execute();
$sp->close();

// 8. Selesai
echo json_encode([
    'code'   => 0,
    'result' => 'Payroll draft berhasil disimpan!'
]);
