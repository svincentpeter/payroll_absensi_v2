<?php
// File: /payroll_absensi_v2/sdm/proses_simpan_payroll.php

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../koneksi.php';

start_session_safe();
init_error_handling();
authorize(['M:SDM']);

function getJenisPayhead($id_payhead, $conn) {
    $jenis = null;
    $stmt = $conn->prepare("SELECT jenis FROM payheads WHERE id = ?");
    $stmt->bind_param("i", $id_payhead);
    $stmt->execute();
    $stmt->bind_result($jenis);
    $stmt->fetch();
    $stmt->close();
    return $jenis;
}

$id_anggota   = intval($_POST['empcode']);
$bulan        = intval($_POST['selectedMonth']);
$tahun        = intval($_POST['selectedYear']);
$no_rekening  = trim($_POST['no_rekening'] ?? '');
$catatan      = trim($_POST['catatan'] ?? '');

// **Perubahan: jika tgl_payroll kosong atau tidak dikirim, gunakan waktu sekarang**
if (!empty($_POST['tgl_payroll']) && trim($_POST['tgl_payroll']) !== '') {
    $tgl_payroll = trim($_POST['tgl_payroll']);
} else {
    $tgl_payroll = date('Y-m-d H:i:s');
}

// Tambahan: parsing potongan_absensi dari POST
$potongan_absensi = 0;
if (isset($_POST['potongan_absensi'])) {
    $potongan_absensi = intval(str_replace('.', '', $_POST['potongan_absensi']));
}

// 1. Upsert payroll DRAFT, tambahkan potongan_absensi
$q = "INSERT INTO payroll (id_anggota, bulan, tahun, no_rekening, catatan, tgl_payroll, potongan_absensi, status)
      VALUES (?, ?, ?, ?, ?, ?, ?, 'draft')
      ON DUPLICATE KEY UPDATE 
        no_rekening=VALUES(no_rekening), 
        catatan=VALUES(catatan), 
        tgl_payroll=VALUES(tgl_payroll), 
        potongan_absensi=VALUES(potongan_absensi),
        status='draft'";
$stmt = $conn->prepare($q);
$stmt->bind_param('iiisssi',
    $id_anggota,       // i
    $bulan,            // i
    $tahun,            // i
    $no_rekening,      // s
    $catatan,          // s
    $tgl_payroll,      // s
    $potongan_absensi  // i
);
$stmt->execute();
$stmt->close();

// 2. Ambil ID payroll draft
$id_payroll = $conn->insert_id;
if ($id_payroll == 0) {
    $rs = $conn->query("SELECT id FROM payroll WHERE id_anggota=$id_anggota AND bulan=$bulan AND tahun=$tahun AND status='draft'");
    $row = $rs->fetch_assoc();
    $id_payroll = intval($row['id'] ?? 0);
}

// 3. Simpan Employee Payheads
$pay_amounts = json_decode($_POST['pay_amounts'], true) ?? [];
$remarks     = $_POST['remarks'] ?? [];
$rapels      = json_decode($_POST['rapels'], true) ?? [];

if ($id_payroll > 0) {
    // Hapus dulu data lama (draft)
    $conn->query("DELETE FROM employee_payheads WHERE id_anggota=$id_anggota AND status='draft'");

    // Path upload
    $uploadRoot = dirname(__DIR__, 1) . "/uploads/payroll_docs";
    $dbRoot     = "/uploads/payroll_docs";

    foreach ($pay_amounts as $id_payhead => $amount) {
        $jenis    = getJenisPayhead($id_payhead, $conn);
        $remark   = trim($remarks[$id_payhead] ?? '');
        $is_rapel = intval($rapels[$id_payhead] ?? 0);

        // Upload handling
        $uploadDir   = "{$uploadRoot}/{$id_anggota}/{$tahun}_{$bulan}/";
        $dbUploadDir = "{$dbRoot}/{$id_anggota}/{$tahun}_{$bulan}/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $docPath = null;
        if (!empty($_FILES["upload_file"]["name"][$id_payhead])) {
            $filename   = basename($_FILES["upload_file"]["name"][$id_payhead]);
            $ext        = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $unique     = time() . rand(1000, 9999);
            $newName    = "payhead_{$id_anggota}_{$id_payhead}_{$tahun}{$bulan}_{$unique}.{$ext}";
            $targetFile = $uploadDir . $newName;
            if (move_uploaded_file($_FILES["upload_file"]["tmp_name"][$id_payhead], $targetFile)) {
                $docPath = $dbUploadDir . $newName;
            }
        }

        $q2 = "INSERT INTO employee_payheads 
               (id_anggota, id_payhead, jenis, amount, status, remarks, support_doc_path, is_rapel)
               VALUES (?, ?, ?, ?, 'draft', ?, ?, ?)";
        $stmt2 = $conn->prepare($q2);
        $stmt2->bind_param('iisdsis',
            $id_anggota,
            $id_payhead,
            $jenis,
            $amount,
            $remark,
            $docPath,
            $is_rapel
        );
        $stmt2->execute();
        $stmt2->close();
    }
}

// 4. Simpan Kenaikan Gaji Tahunan
if (isset($_POST['chkKenaikanGajiTahunan'])) {
    $nama_kenaikan     = trim($_POST['nama_kenaikan'] ?? '');
    $nominal_kenaikan  = floatval(str_replace(['.', ','], ['', '.'], $_POST['nominal_kenaikan'] ?? 0));
    $ranking_id        = intval($_POST['ranking_id'] ?? 0);

    // Ambil tanggal dari form (sesuai hidden inputs yang baru ditambahkan)
    $tanggal_mulai    = $_POST['tanggal_mulai']    ?? date('Y-m-d', strtotime("{$tahun}-{$bulan}-01"));
    $tanggal_berakhir = $_POST['tanggal_berakhir'] ?? date('Y-m-d', strtotime("+1 year -1 day", strtotime($tanggal_mulai)));

    // Cek apakah sudah ada record aktif periode itu
    $stmtCek = $conn->prepare("
        SELECT id 
          FROM kenaikan_gaji_tahunan 
         WHERE id_anggota=? 
           AND status='aktif' 
           AND tanggal_mulai<=? 
           AND tanggal_berakhir>=?
         LIMIT 1
    ");
    $stmtCek->bind_param('iss', $id_anggota, $tanggal_mulai, $tanggal_mulai);
    $stmtCek->execute();
    $stmtCek->store_result();

    if ($stmtCek->num_rows == 0) {
        // INSERT baru, sertakan ranking_id dan kedua tanggal
        $stmtIns = $conn->prepare("
            INSERT INTO kenaikan_gaji_tahunan 
              (id_anggota, nama_kenaikan, jumlah, tanggal_mulai, tanggal_berakhir, status, ranking_id, dibuat_pada) 
            VALUES (?, ?, ?, ?, ?, 'aktif', ?, NOW())
        ");
        $stmtIns->bind_param('isdssi',
            $id_anggota,
            $nama_kenaikan,
            $nominal_kenaikan,
            $tanggal_mulai,
            $tanggal_berakhir,
            $ranking_id
        );
        $stmtIns->execute();
        $stmtIns->close();
    } else {
        // UPDATE record yang sudah ada juga memperbarui ranking_id dan nama/jumlah
        $stmtUpd = $conn->prepare("
            UPDATE kenaikan_gaji_tahunan 
               SET nama_kenaikan   = ?, 
                   jumlah          = ?, 
                   ranking_id      = ?
             WHERE id_anggota      = ? 
               AND status          = 'aktif' 
               AND tanggal_mulai <= ? 
               AND tanggal_berakhir>= ?
        ");
        $stmtUpd->bind_param('sdiiis',
            $nama_kenaikan,
            $nominal_kenaikan,
            $ranking_id,
            $id_anggota,
            $tanggal_mulai,
            $tanggal_mulai
        );
        $stmtUpd->execute();
        $stmtUpd->close();
    }
    $stmtCek->close();
}
// --------------- MODIFIKASI SELESAI DI SINI ---------------

// 6. Return success
echo json_encode(['code' => 0, 'result' => 'Payroll draft berhasil disimpan!']);