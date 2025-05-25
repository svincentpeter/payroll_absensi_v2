<?php
require_once '../helpers.php';
require_once '../koneksi.php';

start_session_safe();
init_error_handling();
authorize(['M:SDM', 'M:Superadmin']);

function getJenisPayhead($id_payhead, $conn) {
    $jenis = null; // Pastikan selalu di-set
    $stmt = $conn->prepare("SELECT jenis FROM payheads WHERE id = ?");
    $stmt->bind_param("i", $id_payhead);
    $stmt->execute();
    $stmt->bind_result($jenis);
    $stmt->fetch();
    $stmt->close();
    return $jenis; // null kalau tidak ketemu
}

$id_anggota   = intval($_POST['empcode']);
$bulan        = intval($_POST['selectedMonth']);
$tahun        = intval($_POST['selectedYear']);
$no_rekening  = trim($_POST['no_rekening'] ?? '');
$catatan      = trim($_POST['catatan'] ?? '');
$tgl_payroll  = $_POST['tgl_payroll'] ?? date('Y-m-d H:i:s');

// 1. Upsert payroll DRAFT
$q = "INSERT INTO payroll (id_anggota, bulan, tahun, no_rekening, catatan, tgl_payroll, status)
      VALUES (?, ?, ?, ?, ?, ?, 'draft')
      ON DUPLICATE KEY UPDATE no_rekening=VALUES(no_rekening), catatan=VALUES(catatan), tgl_payroll=VALUES(tgl_payroll), status='draft'";
$stmt = $conn->prepare($q);
$stmt->bind_param('iiisss', $id_anggota, $bulan, $tahun, $no_rekening, $catatan, $tgl_payroll);
$stmt->execute();

// 2. Ambil ID payroll draft
$id_payroll = $conn->insert_id;
if ($id_payroll == 0) {
    $rs = $conn->query("SELECT id FROM payroll WHERE id_anggota=$id_anggota AND bulan=$bulan AND tahun=$tahun AND status='draft'");
    $id_payroll = $rs->fetch_assoc()['id'] ?? 0;
}

// 3. Simpan Employee Payheads
$pay_amounts = json_decode($_POST['pay_amounts'], true) ?? [];
$remarks     = $_POST['remarks'] ?? [];
$rapels      = json_decode($_POST['rapels'], true) ?? [];

if ($id_payroll > 0) {
    // Hapus dulu data lama (draft)
    $conn->query("DELETE FROM employee_payheads WHERE id_anggota=$id_anggota AND status='draft'");

    foreach ($pay_amounts as $id_payhead => $amount) {
        $jenis   = getJenisPayhead($id_payhead, $conn);
        $remark  = $remarks[$id_payhead] ?? '';
        $is_rapel = $rapels[$id_payhead] ?? 0;

        // Handle upload file jika ada
        $docPath = null;
        $uploadDir = __DIR__ . "/../uploads/payroll_docs/{$id_anggota}/{$tahun}_{$bulan}/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        if (isset($_FILES["upload_file"]["name"][$id_payhead]) && $_FILES["upload_file"]["name"][$id_payhead] != '') {
            $filename = basename($_FILES["upload_file"]["name"][$id_payhead]);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $unique = time();
            $newName = "payhead_{$id_anggota}_{$id_payhead}_{$tahun}{$bulan}_{$unique}." . $ext;
            $targetFile = $uploadDir . $newName;
            if (move_uploaded_file($_FILES["upload_file"]["tmp_name"][$id_payhead], $targetFile)) {
                $docPath = "uploads/payroll_docs/{$id_anggota}/{$tahun}_{$bulan}/{$newName}";
            }
        }

        $q2 = "INSERT INTO employee_payheads (id_anggota, id_payhead, jenis, amount, status, remarks, support_doc_path, is_rapel)
               VALUES (?, ?, ?, ?, 'draft', ?, ?, ?)";
        $stmt2 = $conn->prepare($q2);
        $stmt2->bind_param('iisdsis', $id_anggota, $id_payhead, $jenis, $amount, $remark, $docPath, $is_rapel);
        $stmt2->execute();
    }
}

// 4. Simpan Kenaikan Gaji Tahunan (Jika aktif dan diisi)
if (isset($_POST['chkKenaikanGajiTahunan'])) {
    $nama_kenaikan = trim($_POST['nama_kenaikan'] ?? '');
    $nominal_kenaikan = floatval(str_replace(['.', ','], '', $_POST['nominal_kenaikan'] ?? 0));
    $tanggal_mulai = date('Y-m-d');
    $tanggal_berakhir = date('Y-m-d', strtotime('+1 year -1 day'));

    $cek = $conn->query("SELECT id FROM kenaikan_gaji_tahunan WHERE id_anggota=$id_anggota AND status='aktif'");
    if ($cek->num_rows == 0) {
        $conn->query("INSERT INTO kenaikan_gaji_tahunan (id_anggota, nama_kenaikan, jumlah, tanggal_mulai, tanggal_berakhir, status) 
            VALUES ($id_anggota, '$nama_kenaikan', $nominal_kenaikan, '$tanggal_mulai', '$tanggal_berakhir', 'aktif')");
    }
}

// 5. Return success
echo json_encode(['code' => 0, 'result' => 'Payroll draft berhasil disimpan!']);
