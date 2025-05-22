<?php
// File: salary_handler.php

// 4.1. Load strata‐config & initialisasi untuk view
$guruConfig = [
    'TK'      => ['D3','S1','S2'],
    'SD'      => ['S1','S2'],
    'SMP'     => ['S1','S2'],
    'SMA/SMK' => ['S1','S2','S3']
];
$karyawanConfig = [
    'TK' => ['D3'],
    'SD' => ['S1'],
    'SMP'=> ['S2']
];
// baca dari DB
$guruStrata = []; $res = mysqli_query($conn,"SELECT * FROM gaji_pokok_strata_guru");
while($r=mysqli_fetch_assoc($res)) {
  $guruStrata[$r['jenjang']][$r['strata']]=$r['gaji_pokok'];
}
$karyawanStrata = []; $res2 = mysqli_query($conn,"SELECT * FROM gaji_pokok_strata_karyawan");
while($r=mysqli_fetch_assoc($res2)) {
  $karyawanStrata[$r['jenjang']][$r['strata']]=$r['gaji_pokok'];
}

// 4.2. hitungGajiPokok()
function hitungGajiPokok($conn, $role, $pendidikan, $jenjang)
{
    $strata = normalizePendidikan($pendidikan);
    if ($role !== 'P') {
        $tbl = "gaji_pokok_strata_karyawan";
    } else {
        $tbl = "gaji_pokok_strata_guru";
    }
    $sql = "SELECT gaji_pokok FROM $tbl WHERE jenjang=? AND strata=? LIMIT 1";
    $st  = $conn->prepare($sql);
    if ($st) {
        $st->bind_param('ss',$jenjang,$strata);
        $st->execute();
        $st->bind_result($g);
        $st->fetch();
        $st->close();
        return ($g>0) ? floatval($g) : getGajiPokokByRole($conn,$role);
    }
    return getGajiPokokByRole($conn,$role);
}

// 4.3. updateGajiPokok() & updateGajiStrata...

function updateGajiPokok($conn)
{
    $gaji_guru     = isset($_POST['gaji_pokok_guru']) ? floatval($_POST['gaji_pokok_guru']) : 0;
    $gaji_karyawan = isset($_POST['gaji_pokok_karyawan']) ? floatval($_POST['gaji_pokok_karyawan']) : 0;

    $stmtGuru = $conn->prepare("UPDATE gaji_pokok_roles SET gaji_pokok=? WHERE role='guru'");
    if (!$stmtGuru) {
        send_response(1, 'Query error: ' . $conn->error);
    }
    $stmtGuru->bind_param("d", $gaji_guru);
    $execGuru = $stmtGuru->execute();
    $stmtGuru->close();

    $stmtKar = $conn->prepare("UPDATE gaji_pokok_roles SET gaji_pokok=? WHERE role='karyawan'");
    if (!$stmtKar) {
        send_response(1, 'Query error: ' . $conn->error);
    }
    $stmtKar->bind_param("d", $gaji_karyawan);
    $execKar = $stmtKar->execute();
    $stmtKar->close();

    if ($execGuru && $execKar) {
        send_response(0, 'Gaji pokok berhasil diupdate.');
    } else {
        send_response(1, 'Gagal update gaji pokok.');
    }
}

function updateGajiStrataGuru($conn)
{
    $updates = [];
    $guruConfig = [
        'TK'      => ['D3', 'S1', 'S2'],
        'SD'      => ['S1', 'S2'],
        'SMP'     => ['S1', 'S2'],
        'SMA/SMK' => ['S1', 'S2', 'S3']
    ];
    foreach ($guruConfig as $jenjang => $strataArr) {
        foreach ($strataArr as $strata) {
            $fieldName = strtolower(str_replace('/', '', $jenjang)) . '_' . strtolower($strata);
            $gaji = isset($_POST[$fieldName]) ? floatval($_POST[$fieldName]) : 0;
            $updates[] = ['jenjang' => $jenjang, 'strata' => $strata, 'gaji' => $gaji];
        }
    }
    $allSuccess = true;
    foreach ($updates as $upd) {
        $sql = "INSERT INTO gaji_pokok_strata_guru (jenjang, strata, gaji_pokok)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE gaji_pokok = VALUES(gaji_pokok)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $allSuccess = false;
            continue;
        }
        $stmt->bind_param("ssd", $upd['jenjang'], $upd['strata'], $upd['gaji']);
        if (!$stmt->execute()) {
            $allSuccess = false;
        }
        $stmt->close();
    }
    if ($allSuccess) {
        send_response(0, 'Gaji pokok strata Guru berhasil diupdate.');
    } else {
        send_response(1, 'Gagal update beberapa data strata Guru.');
    }
}

function updateGajiStrataKaryawan($conn)
{
    $updates = [];
    $karyawanConfig = [
        'TK'  => ['D3'],
        'SD'  => ['S1'],
        'SMP' => ['S2']
    ];
    foreach ($karyawanConfig as $jenjang => $strataArr) {
        foreach ($strataArr as $strata) {
            $fieldName = strtolower(str_replace('/', '', $jenjang)) . '_' . strtolower($strata);
            $gaji = isset($_POST[$fieldName]) ? floatval($_POST[$fieldName]) : 0;
            $updates[] = ['jenjang' => $jenjang, 'strata' => $strata, 'gaji' => $gaji];
        }
    }
    $allSuccess = true;
    foreach ($updates as $upd) {
        $sql = "INSERT INTO gaji_pokok_strata_karyawan (jenjang, strata, gaji_pokok)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE gaji_pokok = VALUES(gaji_pokok)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $allSuccess = false;
            continue;
        }
        $stmt->bind_param("ssd", $upd['jenjang'], $upd['strata'], $upd['gaji']);
        if (!$stmt->execute()) {
            $allSuccess = false;
        }
        $stmt->close();
    }
    if ($allSuccess) {
        send_response(0, 'Gaji pokok strata Karyawan berhasil diupdate.');
    } else {
        send_response(1, 'Gagal update beberapa data strata Karyawan.');
    }
}


// 4.4. salary-index helpers

function updateSalaryIndexForUser($conn, $id)
{
    $stmt = $conn->prepare("SELECT role, join_start, pendidikan, jenjang FROM anggota_sekolah WHERE id = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$row = $result->fetch_assoc()) {
        $stmt->close();
        return false;
    }
    $role = $row['role'];
    $join_start = $row['join_start'];
    $pendidikan = $row['pendidikan'];
    $jenjang = $row['jenjang'];
    $stmt->close();

    $years = 0;
    if (!empty($join_start) && $join_start != '0000-00-00') {
        try {
            $startDate = new DateTime($join_start);
            $now = new DateTime();
            $diff = $now->diff($startDate);
            $years = $diff->y;
        } catch (Exception $e) {
            $years = 0;
        }
    }

    $stmtIndex = $conn->prepare("SELECT id, base_salary, level FROM salary_indices WHERE min_years <= ? AND (max_years >= ? OR max_years IS NULL) LIMIT 1");
    if (!$stmtIndex) {
        return false;
    }
    $stmtIndex->bind_param("ii", $years, $years);
    $stmtIndex->execute();
    $resultIndex = $stmtIndex->get_result();
    if ($indexRow = $resultIndex->fetch_assoc()) {
        $salary_index_id = $indexRow['id'];
        $base_salary = floatval($indexRow['base_salary']);
        $level = $indexRow['level'];
        $stmtIndex->close();
    } else {
        $stmtIndex->close();
        return false;
    }

    $gaji_pokok = 0;
    if ($role === 'P' && !empty($pendidikan)) {
        $normalizedPendidikan = normalizePendidikan($pendidikan);
        $stmtStrata = $conn->prepare("SELECT gaji_pokok FROM gaji_pokok_strata_guru WHERE jenjang=? AND strata=? LIMIT 1");
        if ($stmtStrata) {
            $stmtStrata->bind_param("ss", $jenjang, $normalizedPendidikan);
            $stmtStrata->execute();
            $guru_salary = 0;
            $stmtStrata->bind_result($guru_salary);
            if ($stmtStrata->fetch()) {
                $gaji_pokok = floatval($guru_salary);
            } else {
                $gaji_pokok = $base_salary;
            }
            $stmtStrata->close();
        } else {
            $gaji_pokok = $base_salary;
        }
    } else {
        $gaji_pokok = $base_salary;
    }

    $stmtUpdate = $conn->prepare("UPDATE anggota_sekolah SET salary_index_id=?, salary_index_level=?, gaji_pokok=? WHERE id=?");
    if (!$stmtUpdate) {
        return false;
    }
    $stmtUpdate->bind_param("isdi", $salary_index_id, $level, $gaji_pokok, $id);
    $exec = $stmtUpdate->execute();
    $stmtUpdate->close();
    return $exec;
}

function updateSalaryIndexForAll($conn) { /* ... */ }
function getRecommendedSalaryIndex($conn, $joinStart) { /* ... */ }
