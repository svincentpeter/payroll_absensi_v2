<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/includes/mgk_date_utils.php';
require_once __DIR__ . '/includes/mgk_upload_handler.php';
require_once __DIR__ . '/includes/mgk_salary_handler.php';
require_once __DIR__ . '/includes/mgk_crud_guru.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// ==== Fungsi Penentu Role Otomatis ====
function tentukanRole($job_title)
{
    $jt = strtolower(trim($job_title));
    if (
        strpos($jt, 'guru') !== false ||
        strpos($jt, 'wali kelas') !== false ||
        strpos($jt, 'kepala sekolah') !== false ||
        strpos($jt, 'rektor') !== false
    ) {
        return 'P';
    }
    if (
        strpos($jt, 'keuangan') !== false ||
        strpos($jt, 'sdm') !== false ||
        strpos($jt, 'superadmin') !== false ||
        strpos($jt, 'manajer') !== false
    ) {
        return 'M';
    }
    return 'TK';
}

function clean($val)
{
    return (empty($val) || is_null($val)) ? '-' : trim($val);
}
function hashPasswordIfEmpty($val)
{
    return (empty($val) || $val == '-') ? md5('123456') : $val;
}

// Mapping Excel → DB
$mapping = [
    'NIP' => 'nip',
    'NAMA' => 'nama',
    'JENJANG' => 'jenjang',
    'JOB TITLE' => 'job_title',
    'STATUS' => 'status_kerja',
    'JOIN START' => 'join_start',
    'JENIS KELAMIN' => 'jenis_kelamin',
    'TANGGAL LAHIR' => 'tanggal_lahir',
    'USIA' => 'usia',
    'AGAMA' => 'agama',
    'ALAMAT DOMISILI' => 'alamat_domisili',
    'ALAMAT KTP' => 'alamat_ktp',
    'NO HP' => 'no_hp',
    'PENDIDIKAN' => 'pendidikan',
    'STATUS PERKAWINAN' => 'status_perkawinan',
    'EMAIL' => 'email',
    'NAMA PASANGAN' => 'nama_pasangan',
    'ANAK 1' => 'nama_anak_1',
    'ANAK 2' => 'nama_anak_2',
    'ANAK 3' => 'nama_anak_3',
];

$message = '';
$hasilImport = [];
$logDetails = [];
$pageState = 'upload';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file']['tmp_name'];
    $ext  = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xls', 'xlsx'])) {
        $message = 'File harus bertipe .xls atau .xlsx';
    } else {
        try {
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

            $headers = array_map('strtoupper', array_values($sheet[1]));
            $rows = [];
            for ($i = 2; $i <= count($sheet); $i++) {
                $row = [];
                foreach ($mapping as $excelCol => $dbCol) {
                    $idx = array_search($excelCol, $headers);
                    $col = $idx !== false ? array_keys($sheet[1])[$idx] : null;
                    $row[$dbCol] = $col ? clean($sheet[$i][$col]) : '-';
                }
                $row['password'] = hashPasswordIfEmpty('-');
                $row['role'] = tentukanRole($row['job_title']);
                $row['strata'] = normalizePendidikan($row['pendidikan']);
                list($thn, $bln, $efektif) = calcMasaKerja($row['join_start']);
                $row['masa_kerja_tahun']   = $thn;
                $row['masa_kerja_bulan']   = $bln;
                $row['masa_kerja_efektif'] = $efektif;
                $row['usia']               = calcAge($row['tanggal_lahir']);
                $row['gaji_pokok'] = hitungGajiPokok(
                    $conn,
                    $row['role'],
                    $row['pendidikan'],
                    $row['jenjang']
                );

                // Salary Index ***TIDAK DITAMPILKAN DI PREVIEW!***
                $row['salary_index_id']    = '';
                $row['salary_index_level'] = '';

                $rows[] = $row;
            }

            // Deteksi duplikat NIP
            $nips = array_column($rows, 'nip');
            $nipList = "'" . implode("','", $nips) . "'";
            $dupQ = $conn->query("SELECT nip FROM anggota_sekolah WHERE nip IN ($nipList)");
            $dupNip = [];
            while ($d = $dupQ->fetch_assoc()) $dupNip[] = $d['nip'];

            $pageState = 'preview';
        } catch (Exception $e) {
            $message = 'Gagal membaca file Excel: ' . $e->getMessage();
        }
    }
}

// Setelah konfirmasi preview
if (isset($_POST['do_import']) && isset($_POST['data'])) {
    $countInsert = $countUpdate = $countSkip = 0;
    $details = [];
    $idsToUpdate = []; // <--- UNTUK salary index batch
    foreach ($_POST['data'] as $idx => $d) {
        $nip = $conn->real_escape_string($d['nip']);
        $q = $conn->query("SELECT id FROM anggota_sekolah WHERE nip='$nip'");
        $exist = $q && $q->num_rows > 0;

        if ($exist && !isset($_POST['overwrite'][$idx])) {
            $countSkip++;
            $details[] = ['row' => $idx + 2, 'status' => 'skip', 'reason' => 'Duplikat NIP, tidak overwrite'];
            continue;
        }
        if (!$exist) {
            $d['uid'] = generateUID($conn);
        }
        $d['role'] = tentukanRole($d['job_title']);
        $d['strata'] = normalizePendidikan($d['pendidikan']);
        list($thn, $bln, $efektif) = calcMasaKerja($d['join_start']);
        $d['masa_kerja_tahun']   = $thn;
        $d['masa_kerja_bulan']   = $bln;
        $d['masa_kerja_efektif'] = $efektif;
        $d['usia']               = calcAge($d['tanggal_lahir']);
        $d['gaji_pokok'] = hitungGajiPokok(
            $conn,
            $d['role'],
            $d['pendidikan'],
            $d['jenjang']
        );

        // Salary Index akan dihitung SETELAH data masuk
        unset($d['salary_index_id']);
        unset($d['salary_index_level']);

        // Build insert/update query (NO duplicate fields)
        $fields = [];
        foreach ($d as $k => $v) {
            $fields[$k] = "'" . $conn->real_escape_string($v) . "'";
        }
        if ($exist) {
            $sets = [];
            foreach ($fields as $k => $v) $sets[] = "`$k`=$v";
            $conn->query("UPDATE anggota_sekolah SET " . implode(',', $sets) . " WHERE nip='$nip'");
            $row = $conn->query("SELECT id FROM anggota_sekolah WHERE nip='$nip'")->fetch_assoc();
            if ($row) $idsToUpdate[] = intval($row['id']);
            $details[] = ['row' => $idx + 2, 'status' => 'update', 'reason' => 'Data di-update'];
            $countUpdate++;
        } else {
            $conn->query("INSERT INTO anggota_sekolah (`" . implode('`,`', array_keys($fields)) . "`) VALUES (" . implode(',', $fields) . ")");
            $id_baru = $conn->insert_id;
            $idsToUpdate[] = $id_baru;
            $details[] = ['row' => $idx + 2, 'status' => 'insert', 'reason' => 'Data baru'];
            $countInsert++;
        }
    }
    // Update salary index untuk SEMUA ID yang baru diimport/diupdate
    foreach ($idsToUpdate as $id) {
        updateSalaryIndexForUser($conn, $id);
    }
    $message = "Import selesai.<br>Insert: $countInsert &bull; Update: $countUpdate &bull; Skip: $countSkip";
    $logDetails = $details;
    $pageState = 'import';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Import Anggota Sekolah</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.6/dist/sweetalert2.min.css">
    <style>body { padding: 20px; }</style>
</head>
<body>
<div class="container">
    <a href="manage_guru_karyawan.php" class="btn btn-secondary mb-3"><i class="fa fa-arrow-left"></i> Kembali</a>
    <h2 class="mb-3"><i class="fas fa-file-excel"></i> Import Anggota Sekolah</h2>

    <?php if ($pageState == 'upload'): ?>
        <?php if ($message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <div class="card mb-3">
            <div class="card-header">Upload Excel Anggota Sekolah</div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data" id="formUploadExcel">
                    <div class="mb-3">
                        <label for="excel_file" class="form-label">Pilih File Excel</label>
                        <input type="file" name="excel_file" id="excel_file" class="form-control" accept=".xls,.xlsx" required>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-upload"></i> Upload & Preview
                    </button>
                </form>
            </div>
        </div>
    <?php elseif ($pageState == 'preview'): ?>
        <form method="post" action="import_anggota_sekolah.php">
            <div class="alert alert-info mb-3">
                <b>Preview Data:</b> Periksa data di bawah. Centang "Overwrite" jika ingin update data lama (NIP sama).
            </div>
            <div class="table-responsive mb-3">
                <table class="table table-bordered table-hover table-sm">
                    <thead class="table-light">
                        <tr>
                            <?php foreach ($mapping as $excelCol => $dbCol): ?>
                                <th><?= htmlspecialchars(strtoupper($excelCol)) ?></th>
                            <?php endforeach; ?>
                            <th>ROLE</th>
                            <th>STRATA</th>
                            <th>MASA KERJA (Tahun)</th>
                            <th>MASA KERJA (Bulan)</th>
                            <th>MASA KERJA (Efektif)</th>
                            <th>GAJI POKOK</th>
                            <th>Duplikat?</th>
                            <th>Overwrite?</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $idx => $r): ?>
                            <tr>
                                <?php foreach ($mapping as $excelCol => $dbCol): ?>
                                    <td><?= htmlspecialchars($r[$dbCol]) ?></td>
                                <?php endforeach; ?>
                                <td><?= htmlspecialchars($r['role']) ?></td>
                                <td><?= htmlspecialchars($r['strata']) ?></td>
                                <td><?= htmlspecialchars($r['masa_kerja_tahun']) ?></td>
                                <td><?= htmlspecialchars($r['masa_kerja_bulan']) ?></td>
                                <td><?= htmlspecialchars($r['masa_kerja_efektif']) ?></td>
                                <td><?= htmlspecialchars(number_format($r['gaji_pokok'], 0, ',', '.')) ?></td>
                                <?php $isDup = in_array($r['nip'], $dupNip); ?>
                                <td><?= $isDup ? '<span class="badge bg-warning text-dark">YA</span>' : 'TIDAK' ?></td>
                                <td>
                                    <?php if ($isDup): ?>
                                        <input type="checkbox" name="overwrite[<?= $idx ?>]" value="1">
                                    <?php endif; ?>
                                </td>
                                <?php foreach ($r as $k => $v): ?>
                                    <input type="hidden" name="data[<?= $idx ?>][<?= htmlspecialchars($k) ?>]" value="<?= htmlspecialchars($v) ?>">
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" name="do_import" class="btn btn-primary">
                <i class="fas fa-database"></i> Proses Import
            </button>
        </form>
    <?php elseif ($pageState == 'import'): ?>
        <div id="swal-message" data-message="<?= htmlspecialchars($message) ?>"></div>
        <div class="card mb-3">
            <div class="card-header">Log Detail Import</div>
            <div class="card-body">
                <table class="table table-bordered table-sm" id="logTable">
                    <thead>
                        <tr>
                            <th>Baris Excel</th>
                            <th>Status</th>
                            <th>Alasan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logDetails as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['row']) ?></td>
                                <td>
                                    <?php if ($row['status'] === 'insert'): ?>
                                        <span class="badge bg-success">Insert</span>
                                    <?php elseif ($row['status'] === 'update'): ?>
                                        <span class="badge bg-primary">Update</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Skip</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['reason']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <a href="import_anggota_sekolah.php" class="btn btn-success mt-2"><i class="fa fa-upload"></i> Import Lagi</a>
            </div>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.6/dist/sweetalert2.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var msgDiv = document.getElementById('swal-message');
    if (msgDiv && msgDiv.dataset.message) {
        Swal.fire({
            icon: 'info',
            title: 'Hasil Import',
            html: msgDiv.dataset.message,
            confirmButtonColor: '#1976d2'
        });
    }
});
</script>
</body>
</html>
