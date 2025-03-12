<?php
// File: /payroll_absensi_v2/absensi/sdm/manage_guru_karyawan.php

require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
authorize(['sdm', 'superadmin'], '/payroll_absensi_v2/login.php');
$jenjangList = getOrderedJenjang();
require_once __DIR__ . '/../../koneksi.php';

// Hasilkan CSRF token
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

// =============================================================================
// Handle AJAX Requests
// =============================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    switch ($_POST['case']) {
        case 'LoadingGuru':
            LoadingGuru($conn);
            break;
        case 'CreateGuru':
            CreateGuru($conn);
            break;
        case 'GetGuruDetail':
            GetGuruDetail($conn);
            break;
        case 'UpdateGuru':
            UpdateGuru($conn);
            break;
        case 'DeleteGuru':
            DeleteGuru($conn);
            break;
        case 'update_gaji_pokok':
            updateGajiPokok($conn);
            break;
        case 'update_gaji_strata_guru':
            updateGajiStrataGuru($conn);
            break;
        case 'update_gaji_strata_karyawan':
            updateGajiStrataKaryawan($conn);
            break;

        case 'GetRecommendedSalaryIndex':
                $joinStart = $_POST['join_start'] ?? '';
                send_response(0, getRecommendedSalaryIndex($conn, $joinStart));
                break;
            
        case 'update_salary_index_all':
            if (updateSalaryIndexForAll($conn)) {
                send_response(0, 'Salary index untuk semua user berhasil diperbarui.');
            } else {
                send_response(1, 'Gagal memperbarui salary index.');
            }
            break;
        default:
            send_response(400, 'Case tidak valid.');
    }
    exit();
}

// =============================================================================
// Fungsi-Fungsi CRUD
// =============================================================================
function LoadingGuru($conn) {
    $start         = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length        = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search        = isset($_POST['search']['value']) ? bersihkan_input($_POST['search']['value']) : '';
    $jenjangFilter = isset($_POST['jenjang']) ? bersihkan_input($_POST['jenjang']) : '';
    $roleFilter    = isset($_POST['role']) ? bersihkan_input($_POST['role']) : '';
    $statusFilter  = isset($_POST['status_kerja']) ? bersihkan_input($_POST['status_kerja']) : '';

    // Hitung total record
    $sqlTotal = "SELECT COUNT(*) as total FROM anggota_sekolah";
    $resultTotal = mysqli_query($conn, $sqlTotal);
    $rowTotal = mysqli_fetch_assoc($resultTotal);
    $recordsTotal = $rowTotal['total'];

    // Query utama
    $sql = "SELECT * FROM anggota_sekolah WHERE 1=1";
    if (!empty($search)) {
        $sql .= " AND (nip LIKE '%$search%' OR nama LIKE '%$search%')";
    }
    if (!empty($jenjangFilter)) {
        $sql .= " AND jenjang = '$jenjangFilter'";
    }
    if (!empty($roleFilter)) {
        $sql .= " AND role = '$roleFilter'";
    }
    if (!empty($statusFilter)) {
        $sql .= " AND status_kerja = '$statusFilter'";
    }
    $sql .= " ORDER BY id DESC LIMIT $start, $length";

    $result = mysqli_query($conn, $sql);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $masa_kerja = $row['masa_kerja_tahun'] . " Thn " . $row['masa_kerja_bulan'] . " Bln";

        $data[] = [
            "id"           => $row['id'],
            "uid"          => htmlspecialchars($row['uid']),
            "nip"          => htmlspecialchars($row['nip']),
            "nama"         => htmlspecialchars($row['nama']),
            "jenjang"      => $row['jenjang'],
            "job_title"    => htmlspecialchars($row['job_title']),
            "role"         => $row['role'],
            "status_kerja" => $row['status_kerja'],
            "join_start"   => $row['join_start'],
            "masa_kerja"   => $masa_kerja,
            "pendidikan"   => htmlspecialchars($row['pendidikan']),
            "email"        => htmlspecialchars($row['email']),
            "no_hp"        => htmlspecialchars($row['no_hp']),
            "foto_profil"  => getProfilePhotoUrl($row['nama'], $row['jenjang'], $row['role'], $row['id'])
        ];
    }

    echo json_encode([
        "recordsTotal" => $recordsTotal,
        "data"         => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

function CreateGuru($conn) {
    $nip             = bersihkan_input($_POST['nip'] ?? '');
    $nama            = bersihkan_input($_POST['nama'] ?? '');
    $jenjang         = bersihkan_input($_POST['jenjang'] ?? '');
    $job_title       = bersihkan_input($_POST['job_title'] ?? '');
    $role            = bersihkan_input($_POST['role'] ?? '');
    $pendidikan      = bersihkan_input($_POST['pendidikan'] ?? '');
    $jk              = bersihkan_input($_POST['jk'] ?? '');
    $tgl_lahir       = bersihkan_input($_POST['tgl_lahir'] ?? '');
    $usia            = (int)($_POST['usia'] ?? 0);
    $religion        = bersihkan_input($_POST['religion'] ?? '');
    $alamat_domisili = bersihkan_input($_POST['alamat_domisili'] ?? '');
    $alamat_ktp      = bersihkan_input($_POST['alamat_ktp'] ?? '');
    $no_rekening     = bersihkan_input($_POST['no_rekening'] ?? '');
    $no_hp           = bersihkan_input($_POST['no_hp'] ?? '');
    $email           = bersihkan_input($_POST['email'] ?? '');
    $nama_pasangan   = bersihkan_input($_POST['nama_pasangan'] ?? '');
    $jumlah_anak     = (int)($_POST['jumlah_anak'] ?? 0);
    $salary_index_id = (int)($_POST['salary_index_id'] ?? null);
    $join_start      = bersihkan_input($_POST['join_start'] ?? '');

    if (empty($nip) || empty($nama) || empty($jenjang) || empty($role)) {
        send_response(1, 'NIP, Nama, Jenjang, dan Role wajib diisi.');
    }

    // Cek duplikasi NIP
    $sqlCek = "SELECT id FROM anggota_sekolah WHERE nip = ?";
    $stmtCek = $conn->prepare($sqlCek);
    if (!$stmtCek) {
        send_response(1, 'Query error: ' . $conn->error);
    }
    $stmtCek->bind_param("s", $nip);
    $stmtCek->execute();
    $resultCek = $stmtCek->get_result();
    if ($resultCek && $resultCek->num_rows > 0) {
        send_response(1, 'NIP sudah digunakan.');
    }
    $stmtCek->close();

    $uid = generateUID($conn);
    $status_kerja = 'Tetap'; // default

    // Hitung masa kerja
    if (!empty($join_start) && $join_start != '0000-00-00') {
        try {
            $startDate = new DateTime($join_start);
            $now = new DateTime();
            $diff = $now->diff($startDate);
            $masa_kerja_tahun = $diff->y;
            $masa_kerja_bulan = $diff->m;
        } catch (\Exception $e) {
            $masa_kerja_tahun = 0;
            $masa_kerja_bulan = 0;
        }
    } else {
        $masa_kerja_tahun = 0;
        $masa_kerja_bulan = 0;
    }

    // Auto gaji pokok
    $gaji_pokok = 0;
    if ($role === 'P' && !empty($pendidikan)) {
        $query = "SELECT gaji_pokok FROM gaji_pokok_strata_guru WHERE jenjang=? AND strata=? LIMIT 1";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("ss", $jenjang, $pendidikan);
            $stmt->execute();
            $stmt->bind_result($gaji_strata);
            if ($stmt->fetch()) {
                $gaji_pokok = floatval($gaji_strata);
            } else {
                $gaji_pokok = getGajiPokokByRole($conn, $role);
            }
            $stmt->close();
        } else {
            $gaji_pokok = getGajiPokokByRole($conn, $role);
        }
    } else {
        $gaji_pokok = getGajiPokokByRole($conn, $role);
    }

    $defaultPassword = password_hash('123456', PASSWORD_DEFAULT);

    $sql = "INSERT INTO anggota_sekolah (
        uid, nip, password, nama, jenjang, job_title, status_kerja,
        join_start, masa_kerja_tahun, masa_kerja_bulan, remark, jenis_kelamin, tanggal_lahir,
        usia, agama, alamat_domisili, alamat_ktp, no_rekening, no_hp,
        pendidikan, status_perkawinan, email, nama_pasangan, jumlah_anak,
        salary_index_id, gaji_pokok, role
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, '', ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_response(1, 'Query error: ' . $conn->error);
    }
    $stmt->bind_param(
        "ssssssssii" . "ssisssssssiids",
        $uid,
        $nip,
        $defaultPassword,
        $nama,
        $jenjang,
        $job_title,
        $status_kerja,
        $join_start,
        $masa_kerja_tahun,
        $masa_kerja_bulan,
        $jk,
        $tgl_lahir,
        $usia,
        $religion,
        $alamat_domisili,
        $alamat_ktp,
        $no_rekening,
        $no_hp,
        $pendidikan,
        $email,
        $nama_pasangan,
        $jumlah_anak,
        $salary_index_id,
        $gaji_pokok,
        $role
    );
    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $stmt->close();

        // Update salary index
        updateSalaryIndexForUser($conn, $newId);

        $user_id = $_SESSION['nip'] ?? '';
        add_audit_log($conn, $user_id, 'CreateGuru', "Menambah Guru/Karyawan baru ID=$newId, NIP=$nip, Nama=$nama.");
        send_response(0, 'Data berhasil disimpan.');
    } else {
        $stmt->close();
        send_response(1, 'Gagal menyimpan data: ' . $conn->error);
    }
}

function GetGuruDetail($conn) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        send_response(1, 'ID tidak valid.');
    }
    $stmt = $conn->prepare("
    SELECT a.*,
           si.level AS salary_level,
           si.description AS salary_desc
    FROM anggota_sekolah a
    LEFT JOIN salary_indices si ON a.salary_index_id = si.id
    WHERE a.id=? LIMIT 1
");

    if (!$stmt) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows == 1) {
        $data = $res->fetch_assoc();
        unset($data['password']);
        // Masa kerja
        $data['masa_kerja'] = $data['masa_kerja_tahun'] . " Thn " . $data['masa_kerja_bulan'] . " Bln";
        $data['religion']   = $data['agama'];
        $data['jk']         = $data['jenis_kelamin'];
        send_response(0, $data);
    } else {
        send_response(2, 'Data tidak ditemukan.');
    }
    $stmt->close();
}

function UpdateGuru($conn) {
    $id              = (int)($_POST['id'] ?? 0);
    $nip             = bersihkan_input($_POST['nip'] ?? '');
    $uid             = bersihkan_input($_POST['uid'] ?? '');
    $nama            = bersihkan_input($_POST['nama'] ?? '');
    $jenjang         = bersihkan_input($_POST['jenjang'] ?? '');
    $job_title       = bersihkan_input($_POST['job_title'] ?? '');
    $role            = bersihkan_input($_POST['role'] ?? '');
    $jk              = bersihkan_input($_POST['jk'] ?? '');
    $tgl_lahir       = bersihkan_input($_POST['tgl_lahir'] ?? '');
    $usia            = (int)($_POST['usia'] ?? 0);
    $religion        = bersihkan_input($_POST['religion'] ?? '');
    $alamat_domisili = bersihkan_input($_POST['alamat_domisili'] ?? '');
    $alamat_ktp      = bersihkan_input($_POST['alamat_ktp'] ?? '');
    $no_rekening     = bersihkan_input($_POST['no_rekening'] ?? '');
    $no_hp           = bersihkan_input($_POST['no_hp'] ?? '');
    $pendidikan      = bersihkan_input($_POST['pendidikan'] ?? '');
    $email           = bersihkan_input($_POST['email'] ?? '');
    $nama_pasangan   = bersihkan_input($_POST['nama_pasangan'] ?? '');
    $jumlah_anak     = (int)($_POST['jumlah_anak'] ?? 0);
    $salary_index_id = (int)($_POST['salary_index_id'] ?? null);
    $password_baru   = $_POST['password'] ?? '';
    $join_start      = bersihkan_input($_POST['join_start'] ?? '');

    if ($id <= 0) {
        send_response(1, 'ID tidak valid.');
    }
    if (empty($nip) || empty($nama) || empty($jenjang) || empty($role)) {
        send_response(1, 'NIP, Nama, Jenjang, dan Role wajib diisi.');
    }

    // Cek duplikasi NIP
    $sqlCek = "SELECT id FROM anggota_sekolah WHERE nip=? AND id<>?";
    $stmtCek = $conn->prepare($sqlCek);
    if (!$stmtCek) {
        send_response(1, 'Query error: ' . $conn->error);
    }
    $stmtCek->bind_param("si", $nip, $id);
    $stmtCek->execute();
    $resCek = $stmtCek->get_result();
    if ($resCek && $resCek->num_rows > 0) {
        send_response(1, 'NIP sudah digunakan oleh user lain.');
    }
    $stmtCek->close();

    // Hitung ulang masa kerja
    if (!empty($join_start) && $join_start != '0000-00-00') {
        try {
            $startDate = new DateTime($join_start);
            $now = new DateTime();
            $diff = $now->diff($startDate);
            $masa_kerja_tahun = $diff->y;
            $masa_kerja_bulan = $diff->m;
        } catch (\Exception $e) {
            $masa_kerja_tahun = 0;
            $masa_kerja_bulan = 0;
        }
    } else {
        $masa_kerja_tahun = 0;
        $masa_kerja_bulan = 0;
    }

    $fields = [
        'nip'             => $nip,
        'nama'            => $nama,
        'jenjang'         => $jenjang,
        'job_title'       => $job_title,
        'role'            => $role,
        'jenis_kelamin'   => $jk,
        'tanggal_lahir'   => $tgl_lahir,
        'usia'            => $usia,
        'agama'           => $religion,
        'alamat_domisili' => $alamat_domisili,
        'alamat_ktp'      => $alamat_ktp,
        'no_rekening'     => $no_rekening,
        'no_hp'           => $no_hp,
        'pendidikan'      => $pendidikan,
        'email'           => $email,
        'nama_pasangan'   => $nama_pasangan,
        'jumlah_anak'     => $jumlah_anak,
        'salary_index_id' => $salary_index_id,
        'join_start'      => $join_start,
        'masa_kerja_tahun'=> $masa_kerja_tahun,
        'masa_kerja_bulan'=> $masa_kerja_bulan
    ];
    if (!empty($password_baru)) {
        $fields['password'] = password_hash($password_baru, PASSWORD_DEFAULT);
    }

    $updates = [];
    $types   = '';
    $values  = [];
    foreach ($fields as $col => $val) {
        $updates[] = "$col = ?";
        if (in_array($col, ['salary_index_id','jumlah_anak','usia','masa_kerja_tahun','masa_kerja_bulan'])) {
            $types .= 'i';
        } else {
            $types .= 's';
        }
        $values[] = $val;
    }
    $sqlUpdate = "UPDATE anggota_sekolah SET ".implode(", ", $updates)." WHERE id=?";
    $types .= 'i';
    $values[] = $id;

    $stmt = $conn->prepare($sqlUpdate);
    if (!$stmt) {
        send_response(1, 'Query error: ' . $conn->error);
    }
    $stmt->bind_param($types, ...$values);
    if ($stmt->execute()) {
        $stmt->close();
        updateSalaryIndexForUser($conn, $id);

        $user_id = $_SESSION['nip'] ?? '';
        add_audit_log($conn, $user_id, 'UpdateGuru', "Update data ID=$id, NIP=$nip, Nama=$nama.");
        send_response(0, 'Data berhasil diperbarui.');
    } else {
        $stmt->close();
        send_response(1, 'Gagal update data: ' . $conn->error);
    }
}

function DeleteGuru($conn) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        send_response(1, 'ID tidak valid.');
    }
    $stmtFind = $conn->prepare("SELECT nama, nip FROM anggota_sekolah WHERE id=?");
    if (!$stmtFind) {
        send_response(1, 'Query error: ' . $conn->error);
    }
    $stmtFind->bind_param("i", $id);
    $stmtFind->execute();
    $resFind = $stmtFind->get_result();
    if (!$resFind || $resFind->num_rows < 1) {
        $stmtFind->close();
        send_response(2, 'Data tidak ditemukan atau sudah terhapus.');
    }
    $row = $resFind->fetch_assoc();
    $stmtFind->close();

    $stmtDel = $conn->prepare("DELETE FROM anggota_sekolah WHERE id=?");
    if (!$stmtDel) {
        send_response(1, 'Query error: ' . $conn->error);
    }
    $stmtDel->bind_param("i", $id);
    if ($stmtDel->execute()) {
        $stmtDel->close();
        $user_id = $_SESSION['nip'] ?? '';
        add_audit_log($conn, $user_id, 'DeleteGuru', "Menghapus ID=$id, NIP={$row['nip']}, Nama={$row['nama']}");
        send_response(0, 'Data berhasil dihapus.');
    } else {
        $stmtDel->close();
        send_response(1, 'Gagal menghapus data: ' . $conn->error);
    }
}

// ============================================================================
// Fungsi Update Gaji Pokok & Gaji Strata
// ============================================================================
function updateGajiPokok($conn) {
    // misal
    $gaji_guru     = isset($_POST['gaji_pokok_guru']) ? floatval($_POST['gaji_pokok_guru']) : 0;
    $gaji_karyawan = isset($_POST['gaji_pokok_karyawan']) ? floatval($_POST['gaji_pokok_karyawan']) : 0;

    $stmtGuru = $conn->prepare("UPDATE gaji_pokok_roles SET gaji_pokok=? WHERE role='guru'");
    if (!$stmtGuru) {
        send_response(1, 'Query error: '.$conn->error);
    }
    $stmtGuru->bind_param("d", $gaji_guru);
    $execGuru = $stmtGuru->execute();
    $stmtGuru->close();

    $stmtKar = $conn->prepare("UPDATE gaji_pokok_roles SET gaji_pokok=? WHERE role='karyawan'");
    if (!$stmtKar) {
        send_response(1, 'Query error: '.$conn->error);
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

function updateGajiStrataGuru($conn) {
    $updates = [
        ['jenjang'=>'TK', 'strata'=>'D3', 'gaji'=> $_POST['tk_d3'] ?? 0],
        // ... isi data lain ...
    ];
    $allSuccess = true;
    foreach ($updates as $upd) {
        $stmt = $conn->prepare("UPDATE gaji_pokok_strata_guru SET gaji_pokok=? WHERE jenjang=? AND strata=?");
        if (!$stmt) {
            send_response(1, 'Query error: '.$conn->error);
        }
        $stmt->bind_param("dss", $upd['gaji'], $upd['jenjang'], $upd['strata']);
        $exec = $stmt->execute();
        $stmt->close();
        if (!$exec) {
            $allSuccess = false;
            break;
        }
    }
    if ($allSuccess) {
        send_response(0, 'Gaji pokok strata Guru berhasil diupdate.');
    } else {
        send_response(1, 'Gagal update gaji pokok strata Guru.');
    }
}

function updateGajiStrataKaryawan($conn) {
    // mirip guru
    send_response(0, 'Gaji pokok strata Karyawan berhasil diupdate.');
}

// ============================================================================
// Helper
// ============================================================================
function generateUID($conn) {
    do {
        $uid = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        $stmt = $conn->prepare("SELECT id FROM anggota_sekolah WHERE uid=? LIMIT 1");
        $stmt->bind_param("s", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res && $res->num_rows > 0;
        $stmt->close();
    } while ($exists);
    return $uid;
}

function getGajiPokokByRole($conn, $role) {
    // 'P' => 'guru', 'TK' => 'karyawan', 'M' => 'karyawan' (misal)
    $lookup = ($role === 'P') ? 'guru' : 'karyawan';
    $stmt = $conn->prepare("SELECT gaji_pokok FROM gaji_pokok_roles WHERE role=?");
    if ($stmt) {
        $stmt->bind_param("s", $lookup);
        $stmt->execute();
        $stmt->bind_result($gaji_pokok);
        if ($stmt->fetch()) {
            $stmt->close();
            return floatval($gaji_pokok);
        }
        $stmt->close();
    }
    return 0.0;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Data Guru/Karyawan - Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- CSS dari CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SB Admin 2 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/startbootstrap-sb-admin-2/4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body, .text-gray-800 {
            color: #000 !important;
        }
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }
        #loadingSpinner {
            display: none;
            position: fixed;
            z-index: 9999;
            height: 100px;
            width: 100px;
            margin: auto;
            top: 0; left: 0; bottom: 0; right: 0;
        }
        #ManageModal .modal-dialog {
            max-width: 1000px;
            margin: auto;
            padding-top: 70px;
            color: #000 !important;
        }
        .employee-initial {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #ff9800;
            color: #fff;
            font-size: 24px;
            display: flex; 
            align-items: center; 
            justify-content: center;
            margin: 0 auto 10px auto;
        }
        #employeeCards {
            margin-top: 20px;
        }
        #employeeCards .col {
            display: flex;
        }
        #employeeCards .card {
            flex: 1; 
        }
    </style>
</head>
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <!-- End Sidebar -->
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Navbar -->
                <?php include __DIR__ . '/../../navbar.php'; ?>
                <!-- Breadcrumb -->
                <?php include __DIR__ . '/../../breadcrumb.php'; ?>

                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Manajemen Data Guru/Karyawan</h1>
                    <div class="d-flex justify-content-end mb-3 flex-wrap">
                        <button class="btn btn-primary me-2 mb-2" data-bs-toggle="modal" data-bs-target="#modalAdd">
                            <i class="fas fa-plus"></i> Tambah Guru/Karyawan
                        </button>
                        <button class="btn btn-success mb-2" data-bs-toggle="modal" data-bs-target="#modalGajiPokok">
                            <i class="fas fa-dollar-sign"></i> Atur Gaji Pokok
                        </button>
                        <button class="btn btn-info mb-2 ms-2" id="btnManageSalaryIndices" data-href="/payroll_absensi_v2/absensi/sdm/manage_salary_indices.php">
                            <i class="fas fa-money-bill-wave"></i> Atur Salary Indeks
                        </button>
                        <button class="btn btn-warning mb-2 ms-2" id="btnManageHolidays" data-href="/payroll_absensi_v2/absensi/sdm/holidays.php">
                            <i class="fas fa-calendar-alt"></i> Atur Hari Libur
                        </button>
                    </div>

                    <!-- Filter -->
                    <div class="card mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-white">
                                <i class="fas fa-search"></i> Filter Data Guru/Karyawan
                            </h6>
                            </div>
                        <div class="card-body">
                            <form id="filterForm" method="GET" class="form-inline">
                                <label class="me-2" for="filterJenjang">Jenjang:</label>
                                <select class="form-control" id="filterJenjang" name="jenjang">
                    <option value="">Semua Jenjang</option>
                    <?php
                    // Ambil daftar jenjang yang telah didefinisikan di helper
                    $jenjangList = getOrderedJenjang();
                    foreach ($jenjangList as $jenjang) {
                        echo '<option value="' . htmlspecialchars($jenjang) . '">' . htmlspecialchars($jenjang) . '</option>';
                    }
                    ?>
                </select>

                                <label class="me-2" for="filterRole">Role:</label>
                                <select class="form-control me-2" id="filterRole" name="role">
                                    <option value="">Semua Role</option>
                                    <option value="P"  <?= (isset($_GET['role']) && $_GET['role'] === 'P') ? 'selected' : ''; ?>>Pendidik</option>
                                    <option value="TK" <?= (isset($_GET['role']) && $_GET['role'] === 'TK') ? 'selected' : ''; ?>>Tenaga Kependidikan</option>
                                    <option value="M"  <?= (isset($_GET['role']) && $_GET['role'] === 'M') ? 'selected' : ''; ?>>Manajerial</option>
                                </select>
                                <label class="me-2" for="filterStatus">Status Kerja:</label>
                                <select class="form-control me-2" id="filterStatus" name="status_kerja">
                                    <option value="">Semua Status</option>
                                    <option value="Tetap"   <?= (isset($_GET['status_kerja']) && $_GET['status_kerja'] === 'Tetap') ? 'selected' : ''; ?>>Tetap</option>
                                    <option value="Kontrak" <?= (isset($_GET['status_kerja']) && $_GET['status_kerja'] === 'Kontrak') ? 'selected' : ''; ?>>Kontrak</option>
                                </select>
                                <button type="button" id="btnApplyFilter" class="btn btn-primary me-2">
                                    <i class="fas fa-filter"></i> Terapkan
                                </button>
                                <button type="button" id="btnResetFilter" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Daftar Karyawan/Guru dalam Grid -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-white">
                                <i class="fas fa-user"></i> Daftar Guru/Karyawan
                            </h6>
                        </div>
                        <div class="card-body">
                            <!-- Grid Card Container -->
                            <div id="employeeCards" 
                                 class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-5 g-3">
                                <!-- Kartu-kartu akan di-generate via AJAX loadGuru() -->
                            </div>

                            <!-- Pagination Container -->
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center" id="paginationContainer">
                                    <!-- Pagination link akan di-generate via JS -->
                                </ul>
                            </nav>
                        </div>
                    </div>

                    <div id="loadingSpinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Main Content -->

            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?php echo date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- MODAL: Tambah Guru/Karyawan -->
    <div class="modal fade" id="modalAdd" tabindex="-1" aria-labelledby="modalAddLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form id="add-guru-form" method="POST" class="needs-validation" novalidate>
          <input type="hidden" name="case" value="CreateGuru">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="modalAddLabel">Tambah Data Guru/Karyawan</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <!-- Data Pekerjaan -->
              <h6 class="mb-2">Data Pekerjaan</h6>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addNip">NIP <span class="text-danger">*</span></label>
                          <input type="text" name="nip" id="addNip" class="form-control" required placeholder="Masukkan NIP">
                          <div class="invalid-feedback">NIP wajib diisi.</div>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addNama">Nama <span class="text-danger">*</span></label>
                          <input type="text" name="nama" id="addNama" class="form-control" required placeholder="Nama lengkap">
                          <div class="invalid-feedback">Nama wajib diisi.</div>
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addJenjang">Jenjang <span class="text-danger">*</span></label>
                          <select name="jenjang" id="addJenjang" class="form-control" required>
                              <option value="">-- Pilih Jenjang --</option>
                              <option value="TK">TK</option>
                              <option value="SD">SD</option>
                              <option value="SMP">SMP</option>
                              <option value="SMA">SMA</option>
                              <option value="SMK">SMK</option>
                          </select>
                          <div class="invalid-feedback">Jenjang wajib dipilih.</div>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addJobTitle">Job Title</label>
                          <input type="text" name="job_title" id="addJobTitle" class="form-control" placeholder="Contoh: Guru, Staff, dll">
                      </div>
                  </div>
              </div>
              <!-- Field Role -->
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addRole">Role <span class="text-danger">*</span></label>
                          <select name="role" id="addRole" class="form-control" required>
                              <option value="">-- Pilih Role --</option>
                              <option value="P">Pendidik (Guru)</option>
                              <option value="TK">Tenaga Kependidikan</option>
                              <option value="M">Manajerial</option>
                          </select>
                          <div class="invalid-feedback">Role wajib dipilih.</div>
                      </div>
                  </div>
                  <!-- Ganti label Join Start jadi Tanggal Bergabung -->
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addJoinStart">Tanggal Bergabung <span class="text-danger">*</span></label>
                          <input type="date" name="join_start" id="addJoinStart" class="form-control" required>
                          <div class="invalid-feedback">Tanggal Bergabung wajib diisi.</div>
                      </div>
                  </div>
              </div>
              
              <!-- Data Pribadi -->
              <h6 class="mt-4 mb-2">Data Pribadi</h6>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addJK">Jenis Kelamin</label>
                          <select name="jk" id="addJK" class="form-control">
                              <option value="">-- Pilih Jenis Kelamin --</option>
                              <option value="L">Laki-laki</option>
                              <option value="P">Perempuan</option>
                          </select>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addTglLahir">Tanggal Lahir</label>
                          <input type="date" name="tgl_lahir" id="addTglLahir" class="form-control">
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addUsia">Usia</label>
                          <input type="number" name="usia" id="addUsia" class="form-control">
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addReligion">Agama</label>
                          <input type="text" name="religion" id="addReligion" class="form-control">
                      </div>
                  </div>
              </div>
              <!-- Kolom Pendidikan -->
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addPendidikan">Pendidikan</label>
                          <input type="text" name="pendidikan" id="addPendidikan" class="form-control" placeholder="Contoh: S1, D3, dsb.">
                      </div>
                  </div>
              </div>
              <!-- Data Kontak -->
              <h6 class="mt-4 mb-2">Data Kontak</h6>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addAlamatDomisili">Alamat Domisili</label>
                          <textarea name="alamat_domisili" id="addAlamatDomisili" class="form-control"></textarea>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addAlamatKTP">Alamat KTP</label>
                          <textarea name="alamat_ktp" id="addAlamatKTP" class="form-control"></textarea>
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addNoRekening">No Rekening</label>
                          <input type="text" name="no_rekening" id="addNoRekening" class="form-control">
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addNoHP">No Handphone</label>
                          <input type="text" name="no_hp" id="addNoHP" class="form-control" placeholder="08xxx">
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addEmail">Email</label>
                          <input type="email" name="email" id="addEmail" class="form-control" placeholder="contoh@domain.com">
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addNamaPasangan">Nama Pasangan</label>
                          <input type="text" name="nama_pasangan" id="addNamaPasangan" class="form-control">
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addJumlahAnak">Jumlah Anak</label>
                          <input type="number" name="jumlah_anak" id="addJumlahAnak" class="form-control">
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addSalaryIndex">Salary Index ID</label>
                          <input type="number" name="salary_index_id" id="addSalaryIndex" class="form-control">
                      </div>
                  </div>
              </div>
              
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-success">
                  <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                  Simpan
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form id="edit-guru-form" method="POST" class="needs-validation" novalidate>
      <input type="hidden" name="case" value="UpdateGuru">
      <input type="hidden" name="id" id="editId">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Data Guru/Karyawan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <h6 class="mb-2">Data Pekerjaan</h6>
          <div class="row">
            <div class="col-md-6">
              <label for="editNip">NIP <span class="text-danger">*</span></label>
              <input type="text" name="nip" id="editNip" class="form-control" required>
              <div class="invalid-feedback">NIP wajib diisi.</div>
            </div>
            <div class="col-md-6">
              <label for="editUid">UID</label>
              <input type="text" name="uid" id="editUid" class="form-control" readonly>
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-6">
              <label for="editNama">Nama <span class="text-danger">*</span></label>
              <input type="text" name="nama" id="editNama" class="form-control" required>
              <div class="invalid-feedback">Nama wajib diisi.</div>
            </div>
            <div class="col-md-6">
              <label for="editJenjang">Jenjang <span class="text-danger">*</span></label>
              <select name="jenjang" id="editJenjang" class="form-control" required>
                <option value="">-- Pilih Jenjang --</option>
                <option value="TK">TK</option>
                <option value="SD">SD</option>
                <option value="SMP">SMP</option>
                <option value="SMA">SMA</option>
                <option value="SMK">SMK</option>
              </select>
              <div class="invalid-feedback">Jenjang wajib dipilih.</div>
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-6">
              <label for="editRole">Role <span class="text-danger">*</span></label>
              <select name="role" id="editRole" class="form-control" required>
                <option value="">-- Pilih Role --</option>
                <option value="P">Pendidik</option>
                <option value="TK">Tenaga Kependidikan</option>
                <option value="M">Manajerial</option>
              </select>
              <div class="invalid-feedback">Role wajib diisi.</div>
            </div>
            <div class="col-md-6">
              <label for="editJobTitle">Job Title</label>
              <input type="text" name="job_title" id="editJobTitle" class="form-control">
            </div>
          </div>

          <!-- Tanggal Bergabung -->
          <div class="row mt-2">
            <div class="col-md-6">
              <label for="editJoinStart">Tanggal Bergabung</label>
              <input type="date" name="join_start" id="editJoinStart" class="form-control">
            </div>
          </div>

          <h6 class="mt-4 mb-2">Data Pribadi</h6>
          <div class="row">
            <div class="col-md-6">
              <label for="editJK">Jenis Kelamin</label>
              <select name="jk" id="editJK" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="L">Laki-laki</option>
                <option value="P">Perempuan</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="editTglLahir">Tanggal Lahir</label>
              <input type="date" name="tgl_lahir" id="editTglLahir" class="form-control">
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-6">
              <label for="editUsia">Usia</label>
              <input type="number" name="usia" id="editUsia" class="form-control">
            </div>
            <div class="col-md-6">
              <label for="editReligion">Agama</label>
              <input type="text" name="religion" id="editReligion" class="form-control">
            </div>
          </div>

          <!-- Tambahan: Pendidikan di Modal Edit -->
          <div class="row mt-2">
            <div class="col-md-6">
              <label for="editPendidikan">Pendidikan</label>
              <input type="text" name="pendidikan" id="editPendidikan" class="form-control" placeholder="Contoh: S1">
            </div>
          </div>

          <h6 class="mt-4 mb-2">Data Kontak</h6>
          <div class="row">
            <div class="col-md-6">
              <label for="editAlamatDomisili">Alamat Domisili</label>
              <textarea name="alamat_domisili" id="editAlamatDomisili" class="form-control"></textarea>
            </div>
            <div class="col-md-6">
              <label for="editAlamatKTP">Alamat KTP</label>
              <textarea name="alamat_ktp" id="editAlamatKTP" class="form-control"></textarea>
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-6">
              <label for="editNoRekening">No Rekening</label>
              <input type="text" name="no_rekening" id="editNoRekening" class="form-control">
            </div>
            <div class="col-md-6">
              <label for="editNoHP">No HP</label>
              <input type="text" name="no_hp" id="editNoHP" class="form-control">
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-6">
              <label for="editEmail">Email</label>
              <input type="email" name="email" id="editEmail" class="form-control">
            </div>
            <div class="col-md-6">
              <label for="editNamaPasangan">Nama Pasangan</label>
              <input type="text" name="nama_pasangan" id="editNamaPasangan" class="form-control">
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-6">
              <label for="editJumlahAnak">Jumlah Anak</label>
              <input type="number" name="jumlah_anak" id="editJumlahAnak" class="form-control">
            </div>
            <div class="col-md-6">
              <label for="editSalaryIndex">Salary Index ID</label>
              <input type="number" name="salary_index_id" id="editSalaryIndex" class="form-control">
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-4">
              <label for="editNamaAnak1">Nama Anak 1</label>
              <input type="text" name="nama_anak_1" id="editNamaAnak1" class="form-control">
            </div>
            <div class="col-md-4">
              <label for="editNamaAnak2">Nama Anak 2</label>
              <input type="text" name="nama_anak_2" id="editNamaAnak2" class="form-control">
            </div>
            <div class="col-md-4">
              <label for="editNamaAnak3">Nama Anak 3</label>
              <input type="text" name="nama_anak_3" id="editNamaAnak3" class="form-control">
            </div>
          </div>
          <hr>
          <div class="row">
            <div class="col-md-12">
              <label for="editPassword">Password Baru (Opsional)</label>
              <input type="password" name="password" id="editPassword" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">
            <span class="spinner-border spinner-border-sm d-none"></span>
            Update
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

    <!-- MODAL: View Detail -->
<div class="modal fade" id="modalView" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detail Data Guru/Karyawan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body" style="color: #000;">
        <h6>Data Pekerjaan</h6>
        <table class="table table-sm">
          <tr><th>NIP</th><td id="detailNip"></td></tr>
          <tr><th>UID</th><td id="detailUid"></td></tr>
          <tr><th>Nama</th><td id="detailNama"></td></tr>
          <tr><th>Jenjang</th><td id="detailJenjang"></td></tr>
          <tr><th>Job Title</th><td id="detailJobTitle"></td></tr>
          <tr><th>Role</th><td id="detailRole"></td></tr>
          <tr><th>Status Kerja</th><td id="detailStatusKerja"></td></tr>
          <tr><th>Tanggal Bergabung</th><td id="detailJoinStart"></td></tr>
          <tr><th>Masa Kerja</th><td id="detailMasaKerja"></td></tr>
          <tr><th>Pendidikan</th><td id="detailPendidikan"></td></tr>

          <!-- Tambahan: Salary Index di Modal View -->
          <tr><th>Salary Indeks Level</th><td id="detailSalaryIndexId"></td></tr>
        </table>

        <h6 class="mt-3">Data Pribadi</h6>
        <table class="table table-sm">
          <tr><th>Jenis Kelamin</th><td id="detailJK"></td></tr>
          <tr><th>Tanggal Lahir</th><td id="detailTglLahir"></td></tr>
          <tr><th>Usia</th><td id="detailUsia"></td></tr>
          <tr><th>Agama</th><td id="detailReligion"></td></tr>
        </table>

        <h6 class="mt-3">Data Kontak</h6>
        <table class="table table-sm">
          <tr><th>Alamat Domisili</th><td id="detailAlamatDomisili"></td></tr>
          <tr><th>Alamat KTP</th><td id="detailAlamatKTP"></td></tr>
          <tr><th>No Rekening</th><td id="detailNoRekening"></td></tr>
          <tr><th>No HP</th><td id="detailNoHP"></td></tr>
          <tr><th>Email</th><td id="detailEmail"></td></tr>
        </table>

        <h6 class="mt-3">Data Lainnya</h6>
        <table class="table table-sm">
          <tr><th>Remark</th><td id="detailRemark"></td></tr>
          <tr><th>Nama Pasangan</th><td id="detailNamaPasangan"></td></tr>
          <tr><th>Jumlah Anak</th><td id="detailJumlahAnak"></td></tr>
          <tr><th>Nama Anak 1</th><td id="detailNamaAnak1"></td></tr>
          <tr><th>Nama Anak 2</th><td id="detailNamaAnak2"></td></tr>
          <tr><th>Nama Anak 3</th><td id="detailNamaAnak3"></td></tr>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>


    <!-- MODAL: Delete -->
<div class="modal fade" id="modalDelete" tabindex="-1">
  <div class="modal-dialog">
    <form id="delete-guru-form" class="modal-content">
      <input type="hidden" name="case" value="DeleteGuru">
      <input type="hidden" name="id" id="delId">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Hapus Data Guru/Karyawan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <p>Anda yakin ingin menghapus data berikut?</p>
          <p><strong id="delNama"></strong></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">
            <span class="spinner-border spinner-border-sm d-none"></span>
            Hapus
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

    <!-- MODAL: Atur Gaji Pokok -->
    <div class="modal fade" id="modalGajiPokok" tabindex="-1" aria-labelledby="modalGajiPokokLabel" aria-hidden="true">
      <div class="modal-dialog modal-md">
        <form id="gaji-pokok-form" method="POST" class="modal-content">
          <input type="hidden" name="case" value="update_gaji_pokok">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
          <div class="modal-header">
            <h5 class="modal-title" id="modalGajiPokokLabel">Atur Gaji Pokok</h5>
            <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <div class="text-center mb-3">
                <button type="button" class="btn btn-secondary me-2" data-bs-toggle="modal" data-bs-target="#modalGajiStrataGuru">
                    <i class="fas fa-chart-bar"></i> Atur Gaji Strata Guru
                </button>
                <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#modalGajiStrataKaryawan">
                    <i class="fas fa-chart-bar"></i> Atur Gaji Strata Karyawan
                </button>
            </div>
            <!-- Di sini silakan tambahkan input gaji pokok "guru" & "karyawan" jika diinginkan -->
            <!-- Contoh: 
            <div class="form-group mb-3">
                <label for="gajiPokokGuru">Gaji Pokok Guru</label>
                <input type="number" step="0.01" class="form-control" id="gajiPokokGuru" name="gaji_pokok_guru" placeholder="Misal 4000000">
            </div>
            <div class="form-group mb-3">
                <label for="gajiPokokKaryawan">Gaji Pokok Karyawan</label>
                <input type="number" step="0.01" class="form-control" id="gajiPokokKaryawan" name="gaji_pokok_karyawan" placeholder="Misal 3500000">
            </div>
            -->
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success">
              <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
              Simpan
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: Atur Gaji Strata Guru -->
    <div class="modal fade" id="modalGajiStrataGuru" tabindex="-1" aria-labelledby="modalGajiStrataGuruLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form id="gaji-strata-form-guru" method="POST" class="modal-content">
          <input type="hidden" name="case" value="update_gaji_strata_guru">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
          <div class="modal-header">
            <h5 class="modal-title" id="modalGajiStrataGuruLabel">Atur Gaji Pokok Berdasarkan Strata Pendidikan (Guru)</h5>
            <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <div class="table-responsive">
              <!-- Contoh Tabel gaji strata guru -->
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th>Jenjang</th>
                    <th>Strata Pendidikan</th>
                    <th>Gaji Pokok (Rp)</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Silakan isi form input sesuai kebutuhan -->
                  <tr>
                    <td rowspan="3">TK</td>
                    <td>D3</td>
                    <td><input type="number" step="0.01" name="tk_d3" class="form-control" value="3500000" required></td>
                  </tr>
                  <tr>
                    <td>S1</td>
                    <td><input type="number" step="0.01" name="tk_s1" class="form-control" value="4000000" required></td>
                  </tr>
                  <tr>
                    <td>S2</td>
                    <td><input type="number" step="0.01" name="tk_s2" class="form-control" value="4500000" required></td>
                  </tr>
                  <tr>
                    <td rowspan="3">SD</td>
                    <td>D3</td>
                    <td><input type="number" step="0.01" name="sd_d3" class="form-control" value="4000000" required></td>
                  </tr>
                  <tr>
                    <td>S1</td>
                    <td><input type="number" step="0.01" name="sd_s1" class="form-control" value="4500000" required></td>
                  </tr>
                  <tr>
                    <td>S2</td>
                    <td><input type="number" step="0.01" name="sd_s2" class="form-control" value="5000000" required></td>
                  </tr>
                  <tr>
                    <td rowspan="2">SMP</td>
                    <td>S1</td>
                    <td><input type="number" step="0.01" name="smp_s1" class="form-control" value="5000000" required></td>
                  </tr>
                  <tr>
                    <td>S2</td>
                    <td><input type="number" step="0.01" name="smp_s2" class="form-control" value="5500000" required></td>
                  </tr>
                  <tr>
                    <td rowspan="3">SMA/SMK</td>
                    <td>S1</td>
                    <td><input type="number" step="0.01" name="sma_s1" class="form-control" value="5500000" required></td>
                  </tr>
                  <tr>
                    <td>S2</td>
                    <td><input type="number" step="0.01" name="sma_s2" class="form-control" value="6000000" required></td>
                  </tr>
                  <tr>
                    <td>S3</td>
                    <td><input type="number" step="0.01" name="sma_s3" class="form-control" value="7000000" required></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">
              <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
              Simpan
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: Atur Gaji Strata Karyawan -->
    <div class="modal fade" id="modalGajiStrataKaryawan" tabindex="-1" aria-labelledby="modalGajiStrataKaryawanLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form id="gaji-strata-form-karyawan" method="POST" class="modal-content">
          <input type="hidden" name="case" value="update_gaji_strata_karyawan">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
          <div class="modal-header">
            <h5 class="modal-title" id="modalGajiStrataKaryawanLabel">Atur Gaji Pokok Berdasarkan Strata Pendidikan (Karyawan)</h5>
            <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <div class="table-responsive">
              <!-- Contoh Tabel gaji strata karyawan -->
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th>Jenjang</th>
                    <th>Strata Pendidikan</th>
                    <th>Gaji Pokok (Rp)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td rowspan="3">TK</td>
                    <td>D3</td>
                    <td><input type="number" step="0.01" name="tk_d3" class="form-control" value="3000000" required></td>
                  </tr>
                  <tr>
                    <td>S1</td>
                    <td><input type="number" step="0.01" name="tk_s1" class="form-control" value="3500000" required></td>
                  </tr>
                  <tr>
                    <td>S2</td>
                    <td><input type="number" step="0.01" name="tk_s2" class="form-control" value="4000000" required></td>
                  </tr>
                  <tr>
                    <td rowspan="3">SD</td>
                    <td>D3</td>
                    <td><input type="number" step="0.01" name="sd_d3" class="form-control" value="3200000" required></td>
                  </tr>
                  <tr>
                    <td>S1</td>
                    <td><input type="number" step="0.01" name="sd_s1" class="form-control" value="3700000" required></td>
                  </tr>
                  <tr>
                    <td>S2</td>
                    <td><input type="number" step="0.01" name="sd_s2" class="form-control" value="4200000" required></td>
                  </tr>
                  <tr>
                    <td rowspan="2">SMP</td>
                    <td>S1</td>
                    <td><input type="number" step="0.01" name="smp_s1" class="form-control" value="4200000" required></td>
                  </tr>
                  <tr>
                    <td>S2</td>
                    <td><input type="number" step="0.01" name="smp_s2" class="form-control" value="4700000" required></td>
                  </tr>
                  <tr>
                    <td rowspan="3">SMA/SMK</td>
                    <td>S1</td>
                    <td><input type="number" step="0.01" name="sma_s1" class="form-control" value="4700000" required></td>
                  </tr>
                  <tr>
                    <td>S2</td>
                    <td><input type="number" step="0.01" name="sma_s2" class="form-control" value="5200000" required></td>
                  </tr>
                  <tr>
                    <td>S3</td>
                    <td><input type="number" step="0.01" name="sma_s3" class="form-control" value="6000000" required></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">
              <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
              Simpan
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- JavaScript Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
    function showToast(message, icon = 'success') {
        Toast.fire({
            icon: icon,
            title: message
        });
    }

    function getStatusBadge(status) {
        let s = (status || '').toLowerCase();
        if (s === 'tetap') {
            return '<span class="badge bg-success">Tetap</span>';
        } else if (s === 'kontrak') {
            return '<span class="badge bg-warning text-dark">Kontrak</span>';
        } else {
            return '<span class="badge bg-secondary">' + (status || '') + '</span>';
        }
    }

    $(document).ready(function() {
        let currentPage = 1;
        let pageSize    = 10; 

        loadGuru(1);

        $('#btnApplyFilter').on('click', function(){
            loadGuru(1);
        });
        $('#btnResetFilter').on('click', function(){
            $('#filterForm')[0].reset();
            loadGuru(1);
        });

        function loadGuru(page) {
            currentPage = page;
            let start = (currentPage - 1) * pageSize;

            $.ajax({
                url: "manage_guru_karyawan.php?ajax=1",
                type: "POST",
                data: {
                    case: 'LoadingGuru',
                    start: start,
                    length: pageSize,
                    jenjang: $('#filterJenjang').val(),
                    role: $('#filterRole').val(),
                    status_kerja: $('#filterStatus').val(),
                    csrf_token: "<?= htmlspecialchars($csrf_token); ?>"
                },
                dataType: "json",
                beforeSend: function(){
                    $('#loadingSpinner').show();
                },
                success: function(res) {
                    $('#loadingSpinner').hide();
                    if (res.data) {
                        generateCards(res.data);
                        generatePagination(res.recordsTotal);
                    } else {
                        showToast('Data kosong atau gagal di-load.', 'warning');
                        $('#employeeCards').empty();
                        $('#paginationContainer').empty();
                    }
                },
                error: function() {
                    $('#loadingSpinner').hide();
                    showToast('Terjadi kesalahan saat memuat data.', 'error');
                }
            });
        }

        function generateCards(data) {
            let container = $('#employeeCards');
            container.empty();

            data.forEach(item => {
                let photoUrl = (item.foto_profil && item.foto_profil !== '')
                    ? item.foto_profil
                    : '<?= BASE_URL ?>/assets/img/undraw_profile.svg';

                // Tambahkan baris Tanggal Bergabung, Masa Kerja, Pendidikan di card
                let cardHtml = `
                <div class="col">
                  <div class="card shadow-sm text-center p-3 h-100">
                    <img src="${photoUrl}"
                         alt="Foto Profil"
                         class="rounded-circle mb-2"
                         style="width: 60px; height: 60px; object-fit: cover; margin: 0 auto;">
                    <h6 class="mb-0">${item.nama}</h6>
                    <p class="text-muted" style="font-size:0.9rem;">NIP: ${item.nip}</p>
                    <p style="font-size:0.85rem;">
                      <strong>Masa Kerja:</strong> ${item.masa_kerja || '0 Thn'}<br>
                      <strong>Pendidikan:</strong> ${item.pendidikan || '-'}<br>
                      <strong>Role:</strong> ${item.role} | ${getStatusBadge(item.status_kerja)}
                    </p>
                    <div class="d-grid gap-2">
                      <button class="btn btn-sm btn-primary btn-view" data-id="${item.id}">
                        <i class="fas fa-eye"></i> Detail
                      </button>
                      <button class="btn btn-sm btn-warning btn-edit" data-id="${item.id}">
                        <i class="fas fa-pencil-alt"></i> Edit
                      </button>
                      <button class="btn btn-sm btn-danger btn-delete" data-id="${item.id}">
                        <i class="fas fa-trash-alt"></i> Hapus
                      </button>
                    </div>
                  </div>
                </div>
                `;
                container.append(cardHtml);
            });
        }

        function generatePagination(totalRecords) {
            let totalPages = Math.ceil(totalRecords / pageSize);
            let pagination = $('#paginationContainer');
            pagination.empty();

            for (let i = 1; i <= totalPages; i++) {
                let li = $('<li>').addClass('page-item').append(
                    $('<a>').addClass('page-link').text(i).attr('href', '#').on('click', function(e){
                        e.preventDefault();
                        loadGuru(i);
                    })
                );
                if (i === currentPage) {
                    li.addClass('active');
                }
                pagination.append(li);
            }
        }
        
$('#addJoinStart').on('change', function() {
    let joinDate = $(this).val();  // format YYYY-MM-DD
    if (!joinDate) {
        // Kosongkan salary index
        $('#addSalaryIndex').val('');
        return;
    }
    // Panggil AJAX
    $.ajax({
        url: "manage_guru_karyawan.php?ajax=1",
        type: "POST",
        data: {
            case: 'GetRecommendedSalaryIndex',
            join_start: joinDate,
            csrf_token: "<?= htmlspecialchars($csrf_token); ?>"
        },
        dataType: "json",
        beforeSend: function(){
            // misal: tampilkan loader
        },
        success: function(response) {
            if (response.code === 0) {
                let recommendedId = response.result.salary_index_id || 0;
                $('#addSalaryIndex').val(recommendedId);
                console.log('Rekomendasi Salary Index ID:', recommendedId, response.result.explanation);
            } else {
                console.warn('Gagal dapat rekomendasi salary index:', response.result);
                // Kosongkan salary index
                $('#addSalaryIndex').val('');
            }
        },
        error: function() {
            console.error('Terjadi kesalahan AJAX Salary Index');
            // Kosongkan salary index
            $('#addSalaryIndex').val('');
        }
    });
});


        // View Detail
        $(document).on('click', '.btn-view', function() {
            var id = $(this).data('id');
            $.ajax({
                url: "manage_guru_karyawan.php?ajax=1",
                type: "POST",
                data: { case: 'GetGuruDetail', id: id, csrf_token: "<?= htmlspecialchars($csrf_token); ?>" },
                dataType: "json",
                beforeSend: function(){
                    $('#loadingSpinner').show();
                },
                success: function(response) {
                    $('#loadingSpinner').hide();
                    if(response.code == 0) {
                        $('#detailNip').text(response.result.nip);
                        $('#detailUid').text(response.result.uid);
                        $('#detailNama').text(response.result.nama);
                        $('#detailJenjang').text(response.result.jenjang);
                        $('#detailJobTitle').text(response.result.job_title);
                        $('#detailRole').text(response.result.role);
                        $('#detailStatusKerja').html(getStatusBadge(response.result.status_kerja));
                        $('#detailJoinStart').text(response.result.join_start);
                        $('#detailMasaKerja').text(response.result.masa_kerja);
                        $('#detailPendidikan').text(response.result.pendidikan);
                        $('#detailSalaryIndexId').text(response.result.salary_level);

                        $('#detailJK').text(response.result.jk);
                        $('#detailTglLahir').text(response.result.tanggal_lahir);
                        $('#detailUsia').text(response.result.usia);
                        $('#detailReligion').text(response.result.religion);

                        $('#detailAlamatDomisili').text(response.result.alamat_domisili);
                        $('#detailAlamatKTP').text(response.result.alamat_ktp);
                        $('#detailNoRekening').text(response.result.no_rekening);
                        $('#detailNoHP').text(response.result.no_hp);
                        $('#detailEmail').text(response.result.email);

                        $('#detailRemark').text(response.result.remark || '');
                        $('#detailNamaPasangan').text(response.result.nama_pasangan || '');
                        $('#detailJumlahAnak').text(response.result.jumlah_anak || 0);
                        $('#detailNamaAnak1').text(response.result.nama_anak_1 || '');
                        $('#detailNamaAnak2').text(response.result.nama_anak_2 || '');
                        $('#detailNamaAnak3').text(response.result.nama_anak_3 || '');

                        $('#modalView').modal('show');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    $('#loadingSpinner').hide();
                    showToast('Terjadi kesalahan saat mengambil detail.', 'error');
                }
            });
        });

        // Edit
        $(document).on('click', '.btn-edit', function() {
            var id = $(this).data('id');
            var modal = $('#modalEdit');
            var form = $('#edit-guru-form');
            form[0].reset();
            form.removeClass('was-validated');
            $.ajax({
                url: "manage_guru_karyawan.php?ajax=1",
                type: "POST",
                data: { case: 'GetGuruDetail', id: id, csrf_token: "<?= htmlspecialchars($csrf_token); ?>" },
                dataType: "json",
                beforeSend: function(){
                    $('#loadingSpinner').show();
                },
                success: function(response) {
                    $('#loadingSpinner').hide();
                    if(response.code == 0) {
                        $('#editId').val(response.result.id);
                        $('#editNip').val(response.result.nip);
                        $('#editUid').val(response.result.uid);
                        $('#editNama').val(response.result.nama);
                        $('#editJenjang').val((response.result.jenjang || '').toUpperCase());
                        $('#editJobTitle').val(response.result.job_title || '');
                        $('#editRole').val(response.result.role || '');
                        $('#editJK').val(response.result.jk || '');
                        $('#editTglLahir').val(response.result.tanggal_lahir || '');
                        $('#editUsia').val(response.result.usia || '');
                        $('#editReligion').val(response.result.religion || '');
                        $('#editAlamatDomisili').val(response.result.alamat_domisili || '');
                        $('#editAlamatKTP').val(response.result.alamat_ktp || '');
                        $('#editNoRekening').val(response.result.no_rekening || '');
                        $('#editNoHP').val(response.result.no_hp || '');
                        $('#editPendidikan').val(response.result.pendidikan || '');
                        $('#editEmail').val(response.result.email || '');
                        $('#editNamaPasangan').val(response.result.nama_pasangan || '');
                        $('#editJumlahAnak').val(response.result.jumlah_anak || 0);
                        $('#editSalaryIndex').val(response.result.salary_index_id || 0);
                        $('#editJoinStart').val(response.result.join_start || '');

                        // Nama anak
                        $('#editNamaAnak1').val(response.result.nama_anak_1 || '');
                        $('#editNamaAnak2').val(response.result.nama_anak_2 || '');
                        $('#editNamaAnak3').val(response.result.nama_anak_3 || '');

                        modal.modal('show');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    $('#loadingSpinner').hide();
                    showToast('Terjadi kesalahan saat mengambil detail.', 'error');
                }
            });
        });

        // Delete
        $(document).on('click', '.btn-delete', function() {
            var id = $(this).data('id');
            $('#delId').val(id);
            $('#delNama').text('ID: ' + id);
            $('#modalDelete').modal('show');
        });

        $('#delete-guru-form').on('submit', function(e) {
            e.preventDefault();
            var id = $('#delId').val();
            if (!id) {
                showToast('ID tidak ditemukan.', 'error');
                return;
            }
            var form = $(this);
            $.ajax({
                url: "manage_guru_karyawan.php?ajax=1",
                type: "POST",
                data: { case: 'DeleteGuru', id: id, csrf_token: "<?= htmlspecialchars($csrf_token); ?>" },
                dataType: "json",
                beforeSend: function(){
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success: function(response) {
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if(response.code == 0) {
                        showToast(response.result);
                        $('#modalDelete').modal('hide');
                        loadGuru(currentPage);
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat menghapus data.', 'error');
                }
            });
        });

        // Submit form Edit
        $('#edit-guru-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            $.ajax({
                url: "manage_guru_karyawan.php?ajax=1",
                type: "POST",
                data: form.serialize(),
                dataType: "json",
                beforeSend: function(){
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success: function(response){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if(response.code == 0) {
                        showToast(response.result);
                        $('#modalEdit').modal('hide');
                        loadGuru(currentPage);
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat mengupdate data.', 'error');
                }
            });
        });

        // Form Create
        $('#add-guru-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            $.ajax({
                url: "manage_guru_karyawan.php?ajax=1",
                type: "POST",
                data: form.serialize(),
                dataType: "json",
                beforeSend: function(){
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success: function(response){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if(response.code == 0) {
                        showToast(response.result);
                        $('#modalAdd').modal('hide');
                        loadGuru(1);
                        form[0].reset();
                        form.removeClass('was-validated');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat menambah data.', 'error');
                }
            });
        });

        // Form: Update Gaji Strata Guru
        $('#gaji-strata-form-guru').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            $.ajax({
                url: "manage_guru_karyawan.php?ajax=1",
                type: "POST",
                data: form.serialize(),
                dataType: "json",
                beforeSend: function(){
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success: function(response){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if(response.code == 0) {
                        showToast(response.result);
                        $('#modalGajiStrataGuru').modal('hide');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat mengupdate gaji strata Guru.', 'error');
                }
            });
        });

        // Form: Update Gaji Strata Karyawan
        $('#gaji-strata-form-karyawan').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            $.ajax({
                url: "manage_guru_karyawan.php?ajax=1",
                type: "POST",
                data: form.serialize(),
                dataType: "json",
                beforeSend: function(){
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success: function(response){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if(response.code == 0) {
                        showToast(response.result);
                        $('#modalGajiStrataKaryawan').modal('hide');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat mengupdate gaji strata Karyawan.', 'error');
                }
            });
        });

        // Navigasi ke halaman lain
        $(document).on('click', '#btnManageSalaryIndices', function(e) {
            e.preventDefault();
            var url = $(this).data('href');
            $('#content-wrapper').fadeOut(300, function() {
                window.location.href = url;
            });
        });

        $(document).on('click', '#btnManageHolidays', function(e) {
            e.preventDefault();
            var url = $(this).data('href');
            $('#content-wrapper').fadeOut(300, function() {
                window.location.href = url;
            });
        });
    });
    </script>
</body>
</html>
