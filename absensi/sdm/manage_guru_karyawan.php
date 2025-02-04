<?php
// File: /payroll_absensi_v2/kelola/manage_guru_karyawan.php

// =========================
// 1. Pengaturan Keamanan, Session, dan Koneksi
// =========================

// Atur parameter cookie session sebelum session_start()
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => true,      // Hanya lewat HTTPS
    'httponly' => true,      // Tidak dapat diakses via JavaScript
    'samesite' => 'Strict'
]);

require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
generate_csrf_token();

// Buat nonce untuk CSP dan simpan di session
$nonce = base64_encode(random_bytes(16));
$_SESSION['csp_nonce'] = $nonce;

// Paksa HTTPS jika belum digunakan
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: " . $redirect);
    exit();
}

// HSTS header
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

// Pastikan CSRF token tersedia
generate_csrf_token();

// Role Checking: hanya role sdm dan superadmin yang boleh akses
function authorize($allowed_roles = ['sdm', 'superadmin']) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        send_response(403, 'Akses ditolak.');
    }
}
authorize();

// Koneksi ke database
require_once __DIR__ . '/../../koneksi.php';
if (ob_get_length()) ob_end_clean();

// Implementasi CSP dengan nonce
header("Content-Security-Policy: default-src 'self'; 
    script-src 'self' https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.datatables.net https://cdn.jsdelivr.net 'nonce-$nonce'; 
    style-src 'self' https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com https://cdn.datatables.net https://cdn.jsdelivr.net 'nonce-$nonce'; 
    img-src 'self'; 
    font-src 'self' https://cdnjs.cloudflare.com; 
    connect-src 'self'");

// =========================
// 2. DEFINISI FUNGSI UTILITY KHUSUS
// =========================

/**
 * Fungsi untuk mendapatkan badge status kerja.
 */
function getStatusBadge($status) {
    $status_lower = strtolower($status);
    switch ($status_lower) {
        case 'tetap':
            return '<span class="badge badge-success">Tetap</span>';
        case 'kontrak':
            return '<span class="badge badge-secondary">Kontrak</span>';
        default:
            return '<span class="badge badge-secondary">' . htmlspecialchars($status) . '</span>';
    }
}

/**
 * Fungsi untuk mendapatkan gaji pokok berdasarkan role.
 */
function getGajiPokokByRole($conn, $role) {
    $stmt = $conn->prepare("SELECT gaji_pokok FROM gaji_pokok_roles WHERE role = ?");
    if ($stmt) {
        $stmt->bind_param("s", $role);
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

// =========================
// 3. MENANGANI PERMINTAAN AJAX (SERVER–SIDE PROCESSING)
// =========================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verifikasi CSRF
        $csrf_token = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';
        verify_csrf_token($csrf_token);
        // Role Check
        authorize();

        // Ambil parameter case
        $case = isset($_POST['case']) ? bersihkan_input($_POST['case']) : '';
        switch ($case) {
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
            case 'AddAuditLog':
                $action = isset($_POST['action']) ? bersihkan_input($_POST['action']) : '';
                $details = isset($_POST['details']) ? bersihkan_input($_POST['details']) : '';
                if (!empty($action) && !empty($details)) {
                    $logged = add_audit_log(
                        $conn,
                        $_SESSION['user_id'],
                        $action,
                        $details
                    );
                    if ($logged) {
                        send_response(0, 'Audit log berhasil dicatat.');
                    } else {
                        send_response(1, 'Gagal mencatat audit log.');
                    }
                } else {
                    send_response(1, 'Data audit log tidak lengkap.');
                }
                break;
            default:
                send_response(404, 'Kasus tidak ditemukan.');
        }
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    exit();
}

// ------------------------------
// Fungsi AJAX CRUD untuk Data Guru/Karyawan
// ------------------------------

function LoadingGuru($conn) {
    // Parameter DataTables
    $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = isset($_POST['search']['value']) ? bersihkan_input($_POST['search']['value']) : '';
    $filterJenjang = isset($_POST['jenjang']) ? bersihkan_input($_POST['jenjang']) : '';

    // Total records
    $sqlTotal = "SELECT COUNT(*) as total FROM anggota_sekolah";
    $resultTotal = mysqli_query($conn, $sqlTotal);
    if (!$resultTotal) {
        send_response(1, 'Query Error: ' . mysqli_error($conn));
    }
    $rowTotal = mysqli_fetch_assoc($resultTotal);
    $recordsTotal = $rowTotal['total'];

    // Bangun query filter
    $sqlFilter = "SELECT * FROM anggota_sekolah WHERE 1=1";
    $sqlFilterCount = "SELECT COUNT(*) as total FROM anggota_sekolah WHERE 1=1";
    $paramsFilter = [];
    $typesFilter = "";

    if (!empty($search)) {
        $sqlFilter      .= " AND (nip LIKE ? OR uid LIKE ? OR nama LIKE ? OR jenjang LIKE ? OR job_title LIKE ?)";
        $sqlFilterCount .= " AND (nip LIKE ? OR uid LIKE ? OR nama LIKE ? OR jenjang LIKE ? OR job_title LIKE ?)";
        $searchParam = "%" . $search . "%";
        $paramsFilter = array_fill(0, 5, $searchParam);
        $typesFilter = "sssss";
    }
    if (!empty($filterJenjang)) {
        $sqlFilter      .= " AND jenjang = ?";
        $sqlFilterCount .= " AND jenjang = ?";
        $paramsFilter[] = $filterJenjang;
        $typesFilter .= "s";
    }

    // Hitung recordsFiltered
    $stmtFiltered = $conn->prepare($sqlFilterCount);
    if ($stmtFiltered === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    if (!empty($paramsFilter)) {
        $stmtFiltered->bind_param($typesFilter, ...$paramsFilter);
    }
    $stmtFiltered->execute();
    $resultFiltered = $stmtFiltered->get_result();
    if (!$resultFiltered) {
        send_response(1, 'Query Error: ' . $stmtFiltered->error);
    }
    $rowFiltered = $resultFiltered->fetch_assoc();
    $recordsFiltered = isset($rowFiltered['total']) ? $rowFiltered['total'] : 0;
    $stmtFiltered->close();

    // Pengurutan (default: id DESC)
    $orderBy = " ORDER BY id DESC";
    if (isset($_POST['order'], $_POST['columns'])) {
        $columnIndex = intval($_POST['order'][0]['column']);
        // Hanya izinkan pengurutan pada kolom yang akan ditampilkan
        $allowedColumns = ['id', 'uid', 'nip', 'nama', 'jenjang', 'job_title', 'status_kerja', 'join_start', 'masa_kerja', 'pendidikan', 'email', 'no_hp'];
        if (isset($_POST['columns'][$columnIndex]['data']) && in_array($_POST['columns'][$columnIndex]['data'], $allowedColumns)) {
            $colName = $_POST['columns'][$columnIndex]['data'];
            $colSortOrder = ($_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
            $orderBy = " ORDER BY $colName $colSortOrder";
        }
    }

    // Limit
    $limit = " LIMIT ?, ?";
    if (!empty($paramsFilter)) {
        $paramsFilter[] = $start;
        $paramsFilter[] = $length;
        $typesFilter .= "ii";
    } else {
        $paramsFilter = [$start, $length];
        $typesFilter = "ii";
    }
    $sqlFilter .= $orderBy . $limit;

    $stmtData = $conn->prepare($sqlFilter);
    if ($stmtData === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmtData->bind_param($typesFilter, ...$paramsFilter);
    $stmtData->execute();
    $dataQuery = $stmtData->get_result();
    if (!$dataQuery) {
        send_response(1, 'Query Error: ' . $stmtData->error);
    }
    $data = [];
    while ($row = $dataQuery->fetch_assoc()) {
        // Persiapkan tombol aksi dengan dropdown: View Detail, Edit, Hapus
        $aksi = '
<div class="dropdown">
  <button class="btn" type="button" id="dropdownMenuButton_' . htmlspecialchars($row['id']) . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <i class="bi bi-three-dots-vertical"></i>
  </button>
  <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_' . htmlspecialchars($row['id']) . '">
    <a class="dropdown-item btn-view" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '" title="View Detail">
        <i class="fas fa-eye"></i> View Detail
    </a>
    <a class="dropdown-item btn-edit" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '" title="Edit">
        <i class="fas fa-pencil-alt"></i> Edit
    </a>
    <a class="dropdown-item btn-delete" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '" title="Hapus">
        <i class="fas fa-trash-alt"></i> Hapus
    </a>
  </div>
</div>';

        // Data ringkas yang ditampilkan (kolom gaji_pokok dihilangkan)
        $data[] = [
            "id"           => $row['id'],
            "uid"          => htmlspecialchars($row['uid']),
            "nip"          => htmlspecialchars($row['nip']),
            "nama"         => htmlspecialchars($row['nama']),
            "jenjang"      => htmlspecialchars($row['jenjang']),
            "job_title"    => htmlspecialchars($row['job_title']),
            "status_kerja" => getStatusBadge($row['status_kerja']),
            "join_start"   => !empty($row['join_start']) ? date("d-M-Y", strtotime($row['join_start'])) : '-',
            "masa_kerja"   => (int)$row['masa_kerja_tahun'] . " Thn " . (int)$row['masa_kerja_bulan'] . " Bln",
            "pendidikan"   => $row['pendidikan'],
            "email"        => $row['email'],
            "no_hp"        => $row['no_hp'],
            "aksi"         => $aksi
        ];
    }
    $stmtData->close();

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $recordsTotal,
        "recordsFiltered" => $recordsFiltered,
        "data" => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

function CreateGuru($conn) {
    // Ambil data input
    $nip               = isset($_POST['nip']) ? trim($_POST['nip']) : '';
    $nama              = isset($_POST['nama']) ? trim($_POST['nama']) : '';
    $jenjang           = isset($_POST['jenjang']) ? trim($_POST['jenjang']) : '';
    $job_title         = isset($_POST['job_title']) ? trim($_POST['job_title']) : '';
    $status_kerja      = isset($_POST['status']) ? trim($_POST['status']) : '';
    $join_start        = isset($_POST['join_start']) ? trim($_POST['join_start']) : '';
    $masa_kerja_tahun  = isset($_POST['masa_kerja_year']) ? intval($_POST['masa_kerja_year']) : 0;
    $masa_kerja_bulan  = isset($_POST['masa_kerja_month']) ? intval($_POST['masa_kerja_month']) : 0;
    $remark            = isset($_POST['remark']) ? trim($_POST['remark']) : '';
    $jenis_kelamin     = isset($_POST['jk']) ? trim($_POST['jk']) : '';
    $tanggal_lahir     = isset($_POST['tgl_lahir']) ? trim($_POST['tgl_lahir']) : '';
    $usia              = isset($_POST['usia']) ? intval($_POST['usia']) : 0;
    $agama             = isset($_POST['religion']) ? trim($_POST['religion']) : '';
    $alamat_domisili   = isset($_POST['alamat_domisili']) ? trim($_POST['alamat_domisili']) : '';
    $alamat_ktp        = isset($_POST['alamat_ktp']) ? trim($_POST['alamat_ktp']) : '';
    $no_rekening       = isset($_POST['no_rekening']) ? trim($_POST['no_rekening']) : '';
    $no_hp             = isset($_POST['no_hp']) ? trim($_POST['no_hp']) : '';
    $pendidikan        = isset($_POST['pendidikan']) ? trim($_POST['pendidikan']) : '';
    $status_perkawinan = isset($_POST['marital']) ? trim($_POST['marital']) : '';
    $email             = isset($_POST['email']) ? trim($_POST['email']) : '';
    $nama_suami        = isset($_POST['nama_suami']) ? trim($_POST['nama_suami']) : '';
    $jumlah_anak       = isset($_POST['jumlah_anak']) ? intval($_POST['jumlah_anak']) : 0;
    $nama_anak_1       = isset($_POST['nama_anak_1']) ? trim($_POST['nama_anak_1']) : '';
    $nama_anak_2       = isset($_POST['nama_anak_2']) ? trim($_POST['nama_anak_2']) : '';
    $nama_anak_3       = isset($_POST['nama_anak_3']) ? trim($_POST['nama_anak_3']) : '';
    $salary_index_id   = isset($_POST['salary_index_id']) ? intval($_POST['salary_index_id']) : 0;

    // Password default = '123456' disimpan MD5
    $password_hashed = md5('123456');

    // Tentukan role berdasarkan job_title
    $job_title_lower = strtolower($job_title);
    $role = (strpos($job_title_lower, 'guru') !== false) ? 'guru' : 'karyawan';
    // Dapatkan gaji pokok berdasarkan role
    $gaji_pokok = floatval(getGajiPokokByRole($conn, $role));

    // Cek duplikasi NIP
    $stmt = $conn->prepare("SELECT id FROM anggota_sekolah WHERE nip = ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("s", $nip);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        send_response(4, 'NIP sudah digunakan.');
    }
    $stmt->close();

    // Insert data (UID tidak diisi melalui form, diasumsikan sudah di‑generate)
    $sql = "INSERT INTO anggota_sekolah
        (nip, nama, jenjang, job_title, status_kerja, join_start, masa_kerja_tahun, masa_kerja_bulan, remark, jenis_kelamin, tanggal_lahir, usia, agama, alamat_domisili, alamat_ktp, no_rekening, no_hp, pendidikan, status_perkawinan, email, password, gaji_pokok, nama_suami, jumlah_anak, nama_anak_1, nama_anak_2, nama_anak_3, salary_index_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtInsert = $conn->prepare($sql);
    if ($stmtInsert === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    // Pastikan urutan dan tipe data sesuai dengan field tabel
    $stmtInsert->bind_param("ssssssiisssisssssssssdsisssi", 
        $nip, $nama, $jenjang, $job_title, $status_kerja, $join_start,
        $masa_kerja_tahun, $masa_kerja_bulan, $remark, $jenis_kelamin, $tanggal_lahir, $usia, $agama,
        $alamat_domisili, $alamat_ktp, $no_rekening, $no_hp, $pendidikan, $status_perkawinan, $email,
        $password_hashed, $gaji_pokok, $nama_suami, $jumlah_anak, $nama_anak_1, $nama_anak_2, $nama_anak_3, $salary_index_id);
    if ($stmtInsert->execute()) {
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $details_log = "Menambahkan data guru/karyawan: NIP='$nip', Nama='$nama', Role='$role'.";
        if (!add_audit_log($conn, $user_id, 'CreateGuru', $details_log)) {
            log_error("Gagal mencatat audit log untuk CreateGuru ID " . $stmtInsert->insert_id . ".");
        }
        send_response(0, 'Data guru/karyawan berhasil ditambahkan. Password default: 123456');
    } else {
        send_response(1, 'Gagal menambah data: ' . $stmtInsert->error);
    }
    $stmtInsert->close();
    exit();
}

function GetGuruDetail($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        send_response(1, 'ID tidak valid.');
    }
    $stmt = $conn->prepare("SELECT * FROM anggota_sekolah WHERE id = ? LIMIT 1");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows == 1) {
        $data = $result->fetch_assoc();
        unset($data['password']);
        unset($data['gaji_pokok']);
        // Mapping: buat field 'religion' dari kolom 'agama' dan 'jk' dari kolom 'jenis_kelamin'
        $data['religion'] = $data['agama'];
        $data['jk'] = $data['jenis_kelamin'];
        $stmt->close();
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $details_log = "Melihat detail data guru/karyawan ID $id: Nama='" . $data['nama'] . "'.";
        if (!add_audit_log($conn, $user_id, 'GetGuruDetail', $details_log)) {
            log_error("Gagal mencatat audit log untuk GetGuruDetail ID $id.");
        }
        send_response(0, $data);
    } else {
        send_response(2, 'Data tidak ditemukan.');
    }
    $stmt->close();
    exit();
}

function UpdateGuru($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $nip = isset($_POST['nip']) ? trim($_POST['nip']) : '';
    $nama = isset($_POST['nama']) ? trim($_POST['nama']) : '';
    $jenjang = isset($_POST['jenjang']) ? trim($_POST['jenjang']) : '';
    $job_title = isset($_POST['job_title']) ? trim($_POST['job_title']) : '';
    $status_kerja = isset($_POST['status']) ? trim($_POST['status']) : '';
    $join_start = isset($_POST['join_start']) ? trim($_POST['join_start']) : '';
    $masa_kerja_tahun = isset($_POST['masa_kerja_year']) ? intval($_POST['masa_kerja_year']) : 0;
    $masa_kerja_bulan = isset($_POST['masa_kerja_month']) ? intval($_POST['masa_kerja_month']) : 0;
    $remark = isset($_POST['remark']) ? trim($_POST['remark']) : '';
    $jenis_kelamin = isset($_POST['jk']) ? trim($_POST['jk']) : '';
    $tanggal_lahir = isset($_POST['tgl_lahir']) ? trim($_POST['tgl_lahir']) : '';
    $usia = isset($_POST['usia']) ? intval($_POST['usia']) : 0;
    $agama = isset($_POST['religion']) ? trim($_POST['religion']) : '';
    $alamat_domisili = isset($_POST['alamat_domisili']) ? trim($_POST['alamat_domisili']) : '';
    $alamat_ktp = isset($_POST['alamat_ktp']) ? trim($_POST['alamat_ktp']) : '';
    $no_rekening = isset($_POST['no_rekening']) ? trim($_POST['no_rekening']) : '';
    $no_hp = isset($_POST['no_hp']) ? trim($_POST['no_hp']) : '';
    $pendidikan = isset($_POST['pendidikan']) ? trim($_POST['pendidikan']) : '';
    $status_perkawinan = isset($_POST['marital']) ? trim($_POST['marital']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $nama_suami = isset($_POST['nama_suami']) ? trim($_POST['nama_suami']) : '';
    $jumlah_anak = isset($_POST['jumlah_anak']) ? intval($_POST['jumlah_anak']) : 0;
    $nama_anak_1 = isset($_POST['nama_anak_1']) ? trim($_POST['nama_anak_1']) : '';
    $nama_anak_2 = isset($_POST['nama_anak_2']) ? trim($_POST['nama_anak_2']) : '';
    $nama_anak_3 = isset($_POST['nama_anak_3']) ? trim($_POST['nama_anak_3']) : '';
    $salary_index_id = isset($_POST['salary_index_id']) ? intval($_POST['salary_index_id']) : 0;
    $password_plain = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Cek duplikasi NIP untuk data selain ID ini
    $stmtCheck = $conn->prepare("SELECT id FROM anggota_sekolah WHERE nip = ? AND id != ?");
    if ($stmtCheck) {
        $stmtCheck->bind_param("si", $nip, $id);
        $stmtCheck->execute();
        $stmtCheck->store_result();
        if ($stmtCheck->num_rows > 0) {
            send_response(4, 'NIP sudah digunakan oleh data lain.');
        }
        $stmtCheck->close();
    } else {
        send_response(1, 'Query Error: ' . $conn->error);
    }

    if (!empty($password_plain)) {
        $password_hashed = md5($password_plain);
        $sqlUpdate = "UPDATE anggota_sekolah
            SET nip=?, nama=?, jenjang=?, job_title=?, status_kerja=?, join_start=?, masa_kerja_tahun=?, masa_kerja_bulan=?,
                remark=?, jenis_kelamin=?, tanggal_lahir=?, usia=?, agama=?, alamat_domisili=?, alamat_ktp=?, no_rekening=?,
                no_hp=?, pendidikan=?, status_perkawinan=?, email=?, nama_suami=?, jumlah_anak=?, nama_anak_1=?, nama_anak_2=?, nama_anak_3=?, salary_index_id=?, password=?
            WHERE id=?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        if ($stmtUpdate) {
            $stmtUpdate->bind_param("ssssssiisssisssssssssiisssi", 
                $nip, $nama, $jenjang, $job_title, $status_kerja, $join_start,
                $masa_kerja_tahun, $masa_kerja_bulan, $remark, $jenis_kelamin, $tanggal_lahir, $usia, $agama,
                $alamat_domisili, $alamat_ktp, $no_rekening, $no_hp, $pendidikan, $status_perkawinan, $email,
                $nama_suami, $jumlah_anak, $nama_anak_1, $nama_anak_2, $nama_anak_3, $salary_index_id, $password_hashed, $id);
        }
    } else {
        $sqlUpdate = "UPDATE anggota_sekolah
            SET nip=?, nama=?, jenjang=?, job_title=?, status_kerja=?, join_start=?, masa_kerja_tahun=?, masa_kerja_bulan=?,
                remark=?, jenis_kelamin=?, tanggal_lahir=?, usia=?, agama=?, alamat_domisili=?, alamat_ktp=?, no_rekening=?,
                no_hp=?, pendidikan=?, status_perkawinan=?, email=?, nama_suami=?, jumlah_anak=?, nama_anak_1=?, nama_anak_2=?, nama_anak_3=?, salary_index_id=?
            WHERE id=?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        if ($stmtUpdate) {
            $stmtUpdate->bind_param("ssssssiisssisssssssssiisssi", 
                $nip, $nama, $jenjang, $job_title, $status_kerja, $join_start,
                $masa_kerja_tahun, $masa_kerja_bulan, $remark, $jenis_kelamin, $tanggal_lahir, $usia, $agama,
                $alamat_domisili, $alamat_ktp, $no_rekening, $no_hp, $pendidikan, $status_perkawinan, $email,
                $nama_suami, $jumlah_anak, $nama_anak_1, $nama_anak_2, $nama_anak_3, $salary_index_id, $id);
        }
    }
    if (isset($stmtUpdate) && $stmtUpdate) {
        if ($stmtUpdate->execute()) {
            $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
            $details_log = "Mengupdate data guru/karyawan ID $id: NIP='$nip', Nama='$nama'.";
            if (!add_audit_log($conn, $user_id, 'UpdateGuru', $details_log)) {
                log_error("Gagal mencatat audit log untuk UpdateGuru ID $id.");
            }
            send_response(0, 'Data guru/karyawan berhasil diupdate.');
        } else {
            send_response(1, 'Gagal memperbarui data: ' . $stmtUpdate->error);
        }
        $stmtUpdate->close();
    } else {
        send_response(1, 'Gagal menyiapkan SQL: ' . $conn->error);
    }
    exit();
}

function DeleteGuru($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        send_response(3, 'ID tidak valid.');
    }
    $stmt = $conn->prepare("DELETE FROM anggota_sekolah WHERE id = ?");
    if ($stmt === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        send_response(1, 'Gagal menghapus data: ' . $stmt->error);
    }
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $details_log = "Menghapus data guru/karyawan ID $id.";
    if (!add_audit_log($conn, $user_id, 'DeleteGuru', $details_log)) {
        log_error("Gagal mencatat audit log untuk DeleteGuru ID $id.");
    }
    send_response(0, 'Data guru/karyawan berhasil dihapus.');
    $stmt->close();
    exit();
}

/**
 * Fungsi untuk memperbarui gaji pokok guru dan karyawan.
 */
function updateGajiPokok($conn) {
    $gaji_guru = isset($_POST['gaji_pokok_guru']) ? floatval($_POST['gaji_pokok_guru']) : 0;
    $gaji_karyawan = isset($_POST['gaji_pokok_karyawan']) ? floatval($_POST['gaji_pokok_karyawan']) : 0;

    $stmtGuru = $conn->prepare("UPDATE gaji_pokok_roles SET gaji_pokok = ? WHERE role = 'guru'");
    if ($stmtGuru === false) {
        send_response(1, 'Query Error (guru): ' . $conn->error);
    }
    $stmtGuru->bind_param("d", $gaji_guru);
    if (!$stmtGuru->execute()) {
        send_response(1, 'Gagal mengupdate gaji pokok guru: ' . $stmtGuru->error);
    }
    $stmtGuru->close();

    $stmtKaryawan = $conn->prepare("UPDATE gaji_pokok_roles SET gaji_pokok = ? WHERE role = 'karyawan'");
    if ($stmtKaryawan === false) {
        send_response(1, 'Query Error (karyawan): ' . $conn->error);
    }
    $stmtKaryawan->bind_param("d", $gaji_karyawan);
    if (!$stmtKaryawan->execute()) {
        send_response(1, 'Gagal mengupdate gaji pokok karyawan: ' . $stmtKaryawan->error);
    }
    $stmtKaryawan->close();

    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $details_log = "Mengupdate gaji pokok: Guru = $gaji_guru, Karyawan = $gaji_karyawan.";
    if (!add_audit_log($conn, $user_id, 'update_gaji_pokok', $details_log)) {
        log_error("Gagal mencatat audit log untuk update_gaji_pokok.");
    }
    send_response(0, 'Gaji pokok berhasil diperbarui.');
}

// =========================
// 4. TAMPILAN HALAMAN (HTML + AJAX)
// =========================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Data Guru/Karyawan - Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- SB Admin 2, Bootstrap, FontAwesome, dan DataTables CSS -->
    <link href="../../assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" nonce="<?php echo $nonce; ?>">
    <link href="../../assets/css/sb-admin-2.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <link href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap4.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <link href="https://cdn.datatables.net/buttons/1.7.1/css/buttons.bootstrap4.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <link href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" nonce="<?php echo $nonce; ?>">
    <!-- Custom Styles -->
    <style nonce="<?php echo $nonce; ?>">
        #guruTable th, #guruTable td {
            vertical-align: middle;
            white-space: nowrap;
        }
        .table-responsive { overflow-x: auto; }
        .card-header { background: linear-gradient(45deg, #0d47a1, #42a5f5); color: white; }
        #loadingSpinner {
            display: none;
            position: fixed;
            z-index: 9999;
            height: 100px;
            width: 100px;
            margin: auto;
            top: 0; left: 0; bottom: 0; right: 0;
        }
        .invisible-field {
            display: none;
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
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a href="../../logout.php" class="btn btn-danger btn-sm" title="Logout">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </nav>
                <!-- End Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Manajemen Data Guru/Karyawan</h1>

                    <!-- Tombol Tambah & Atur Gaji Pokok -->
                    <div class="d-flex justify-content-end mb-3 flex-wrap">
                        <button class="btn btn-primary mr-2 mb-2" data-toggle="modal" data-target="#modalAdd">
                            <i class="fas fa-plus"></i> Tambah Guru/Karyawan
                        </button>
                        <button class="btn btn-success mb-2" data-toggle="modal" data-target="#modalGajiPokok">
                            <i class="fas fa-dollar-sign"></i> Atur Gaji Pokok
                        </button>
                    </div>

                    <!-- FILTER Jenjang -->
                    <div class="card mb-4">
                        <div class="card-header">Filter Data Guru/Karyawan</div>
                        <div class="card-body">
                            <form id="filterForm" method="GET" class="form-inline">
                                <label class="mr-2" for="filterJenjang">Jenjang:</label>
                                <select class="form-control mr-2" id="filterJenjang" name="jenjang">
                                    <option value="">Semua Jenjang</option>
                                    <?php
                                    $sqlJenjang = "SELECT DISTINCT jenjang FROM anggota_sekolah ORDER BY jenjang ASC";
                                    $resJ = $conn->query($sqlJenjang);
                                    if ($resJ && $resJ->num_rows > 0) {
                                        while ($rowJ = $resJ->fetch_assoc()) {
                                            $selected = (isset($_GET['jenjang']) && $_GET['jenjang'] === $rowJ['jenjang']) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($rowJ['jenjang']) . '" ' . $selected . '>' . htmlspecialchars($rowJ['jenjang']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                <button type="button" id="btnApplyFilter" class="btn btn-primary mr-2">
                                    <i class="fas fa-filter"></i> Terapkan
                                </button>
                                <button type="button" id="btnResetFilter" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Tabel Data Guru/Karyawan (versi ringkas) tanpa kolom Gaji Pokok -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-white">
                                <i class="fas fa-table"></i> Daftar Guru/Karyawan
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="guruTable" class="table table-sm table-bordered table-striped display nowrap" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>UID</th>
                                            <th>NIP</th>
                                            <th>Nama</th>
                                            <th>Jenjang</th>
                                            <th>Job Title</th>
                                            <th>Status Kerja</th>
                                            <th>Join Start</th>
                                            <th>Masa Kerja</th>
                                            <th>Pendidikan</th>
                                            <th>Email</th>
                                            <th>No HP</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!-- End Tabel -->

                    <!-- Loading Spinner -->
                    <div id="loadingSpinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
                <!-- End Container Fluid -->
            </div>
            <!-- End Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?php echo date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div>
        <!-- End Content Wrapper -->
    </div>
    <!-- End Page Wrapper -->

    <!-- MODAL: Tambah Guru/Karyawan -->
    <div class="modal fade" id="modalAdd" tabindex="-1" aria-labelledby="modalAddLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form id="add-guru-form" method="POST" class="needs-validation" novalidate>
          <input type="hidden" name="case" value="CreateGuru">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="modalAddLabel">Tambah Data Guru/Karyawan</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                <span>&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <!-- Field input dengan grid -->
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addNip">NIP <span class="text-danger">*</span></label>
                          <input type="text" name="nip" id="addNip" class="form-control" required placeholder="Masukkan NIP (6 digit)">
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
                          <input type="text" name="job_title" id="addJobTitle" class="form-control" placeholder="Contoh: Guru, Karyawan">
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addStatus">Status Kerja <span class="text-danger">*</span></label>
                          <select name="status" id="addStatus" class="form-control" required>
                              <option value="">-- Pilih Status --</option>
                              <option value="Tetap">Tetap</option>
                              <option value="Kontrak">Kontrak</option>
                          </select>
                          <div class="invalid-feedback">Status wajib dipilih.</div>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addJoinStart">Join Start</label>
                          <input type="date" name="join_start" id="addJoinStart" class="form-control">
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label>Masa Kerja</label>
                          <div class="row">
                              <div class="col">
                                  <input type="number" name="masa_kerja_year" id="addMasaKerjaYear" class="form-control" placeholder="Tahun" readonly>
                              </div>
                              <div class="col">
                                  <input type="number" name="masa_kerja_month" id="addMasaKerjaMonth" class="form-control" placeholder="Bulan" readonly>
                              </div>
                          </div>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addRemark">Remark</label>
                          <textarea name="remark" id="addRemark" class="form-control"></textarea>
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addJK">Jenis Kelamin</label>
                          <select name="jk" id="addJK" class="form-control">
                              <option value="">-- Pilih Jenis Kelamin --</option>
                              <option value="Laki-laki">Laki-laki</option>
                              <option value="Perempuan">Perempuan</option>
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
                          <label for="addPendidikan">Pendidikan</label>
                          <input type="text" name="pendidikan" id="addPendidikan" class="form-control">
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addMarital">Status Pernikahan</label>
                          <input type="text" name="marital" id="addMarital" class="form-control">
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
                          <label for="addNamaSuami">Nama Suami</label>
                          <input type="text" name="nama_suami" id="addNamaSuami" class="form-control">
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
              <div class="row">
                  <div class="col-md-4">
                      <div class="form-group">
                          <label for="addNamaAnak1">Nama Anak 1</label>
                          <input type="text" name="nama_anak_1" id="addNamaAnak1" class="form-control">
                      </div>
                  </div>
                  <div class="col-md-4">
                      <div class="form-group">
                          <label for="addNamaAnak2">Nama Anak 2</label>
                          <input type="text" name="nama_anak_2" id="addNamaAnak2" class="form-control">
                      </div>
                  </div>
                  <div class="col-md-4">
                      <div class="form-group">
                          <label for="addNamaAnak3">Nama Anak 3</label>
                          <input type="text" name="nama_anak_3" id="addNamaAnak3" class="form-control">
                      </div>
                  </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-success">
                  <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                  Simpan
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: Edit Guru/Karyawan (Grid Form) -->
    <div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form id="edit-guru-form" method="POST" class="needs-validation" novalidate>
          <input type="hidden" name="case" value="UpdateGuru">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="id" id="editId">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Edit Data Guru/Karyawan</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                <span>&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <!-- Gunakan grid 2 kolom -->
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editNip">NIP <span class="text-danger">*</span></label>
                          <input type="text" name="nip" id="editNip" class="form-control" required>
                          <div class="invalid-feedback">NIP wajib diisi.</div>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editUid">UID</label>
                          <input type="text" name="uid" id="editUid" class="form-control" readonly>
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editNama">Nama <span class="text-danger">*</span></label>
                          <input type="text" name="nama" id="editNama" class="form-control" required>
                          <div class="invalid-feedback">Nama wajib diisi.</div>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editJenjang">Jenjang <span class="text-danger">*</span></label>
                          <!-- Pastikan value yang dipilih sesuai dengan opsi -->
                          <select name="jenjang" id="editJenjang" class="form-control" required>
                              <option value="">-- Pilih Jenjang --</option>
                              <option value="TK">TK</option>
                              <option value="SD">SD</option>
                              <option value="SMP">SMP</option>
                              <option value="SMA">SMA</option>
                              <option value="SMK">SMK</option>
                          </select>
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editJobTitle">Job Title</label>
                          <input type="text" name="job_title" id="editJobTitle" class="form-control">
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editStatus">Status Kerja <span class="text-danger">*</span></label>
                          <select name="status" id="editStatus" class="form-control" required>
                              <option value="">-- Pilih Status --</option>
                              <option value="Tetap">Tetap</option>
                              <option value="Kontrak">Kontrak</option>
                          </select>
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editJoinStart">Join Start</label>
                          <input type="date" name="join_start" id="editJoinStart" class="form-control">
                      </div>
                  </div>
                  <div class="col-md-6">
                      <label>Masa Kerja</label>
                      <div class="row">
                          <div class="col">
                              <input type="number" name="masa_kerja_year" id="editMasaKerjaYear" class="form-control" placeholder="Tahun" readonly>
                          </div>
                          <div class="col">
                              <input type="number" name="masa_kerja_month" id="editMasaKerjaMonth" class="form-control" placeholder="Bulan" readonly>
                          </div>
                      </div>
                  </div>
              </div>
              <hr>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editRemark">Remark</label>
                          <textarea name="remark" id="editRemark" class="form-control"></textarea>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editJK">Jenis Kelamin</label>
                          <!-- Gunakan key "jk" yang sudah dipetakan -->
                          <select name="jk" id="editJK" class="form-control">
                              <option value="">-- Pilih Jenis Kelamin --</option>
                              <option value="Laki-laki">Laki-laki</option>
                              <option value="Perempuan">Perempuan</option>
                          </select>
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editTglLahir">Tanggal Lahir</label>
                          <input type="date" name="tgl_lahir" id="editTglLahir" class="form-control">
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editUsia">Usia</label>
                          <input type="number" name="usia" id="editUsia" class="form-control">
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editReligion">Agama</label>
                          <!-- Gunakan key "religion" yang sudah dipetakan -->
                          <input type="text" name="religion" id="editReligion" class="form-control">
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editAlamatDomisili">Alamat Domisili</label>
                          <textarea name="alamat_domisili" id="editAlamatDomisili" class="form-control"></textarea>
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editAlamatKTP">Alamat KTP</label>
                          <textarea name="alamat_ktp" id="editAlamatKTP" class="form-control"></textarea>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editNoRekening">No Rekening</label>
                          <input type="text" name="no_rekening" id="editNoRekening" class="form-control">
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editNoHP">No HP</label>
                          <input type="text" name="no_hp" id="editNoHP" class="form-control">
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editPendidikan">Pendidikan</label>
                          <input type="text" name="pendidikan" id="editPendidikan" class="form-control">
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editMarital">Status Pernikahan</label>
                          <!-- Ambil nilai dari kolom status_perkawinan -->
                          <input type="text" name="marital" id="editMarital" class="form-control">
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editEmail">Email</label>
                          <input type="email" name="email" id="editEmail" class="form-control">
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editNamaSuami">Nama Suami</label>
                          <input type="text" name="nama_suami" id="editNamaSuami" class="form-control">
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editJumlahAnak">Jumlah Anak</label>
                          <input type="number" name="jumlah_anak" id="editJumlahAnak" class="form-control">
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editSalaryIndex">Salary Index ID</label>
                          <input type="number" name="salary_index_id" id="editSalaryIndex" class="form-control">
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-4">
                      <div class="form-group">
                          <label for="editNamaAnak1">Nama Anak 1</label>
                          <input type="text" name="nama_anak_1" id="editNamaAnak1" class="form-control">
                      </div>
                  </div>
                  <div class="col-md-4">
                      <div class="form-group">
                          <label for="editNamaAnak2">Nama Anak 2</label>
                          <input type="text" name="nama_anak_2" id="editNamaAnak2" class="form-control">
                      </div>
                  </div>
                  <div class="col-md-4">
                      <div class="form-group">
                          <label for="editNamaAnak3">Nama Anak 3</label>
                          <input type="text" name="nama_anak_3" id="editNamaAnak3" class="form-control">
                      </div>
                  </div>
              </div>
              <hr>
              <div class="row">
                  <div class="col-md-12">
                      <div class="form-group">
                          <label for="editPassword">Password Baru (Opsional)</label>
                          <input type="password" name="password" id="editPassword" class="form-control" placeholder="Isi jika ingin mengubah password">
                          <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah password.</small>
                      </div>
                  </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-primary">
                  <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                  Update
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: View Detail Guru/Karyawan (menggunakan label yang sama seperti form edit) -->
    <div class="modal fade" id="modalView" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Detail Data Guru/Karyawan</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
              <span>&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <!-- Detail tampilkan dalam grid table -->
            <table class="table table-bordered" id="detailContent">
            </table>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: Delete Guru/Karyawan -->
    <div class="modal fade" id="modalDelete" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog">
        <form id="delete-guru-form" class="modal-content">
          <input type="hidden" name="case" value="DeleteGuru">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="id" id="delId">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Hapus Data Guru/Karyawan</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <p>Anda yakin ingin menghapus data berikut?</p>
              <p><strong id="delNama"></strong></p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-danger">
                  <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                  Hapus
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: Atur Gaji Pokok -->
    <div class="modal fade" id="modalGajiPokok" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-md">
        <form id="gaji-pokok-form" method="POST" class="modal-content">
          <input type="hidden" name="case" value="update_gaji_pokok">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
          <div class="modal-header">
            <h5 class="modal-title">Atur Gaji Pokok</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="form-group">
                <label for="gajiPokokGuru">Gaji Pokok Guru</label>
                <input type="number" name="gaji_pokok_guru" id="gajiPokokGuru" class="form-control" min="0" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="gajiPokokKaryawan">Gaji Pokok Karyawan</label>
                <input type="number" name="gaji_pokok_karyawan" id="gajiPokokKaryawan" class="form-control" min="0" step="0.01" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success">
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                Simpan
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- JavaScript Dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/dataTables.buttons.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.html5.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.print.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.colVis.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" nonce="<?php echo $nonce; ?>"></script>
    <script nonce="<?php echo $nonce; ?>">
    // Inisialisasi SweetAlert2 Toast
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

    $(document).ready(function() {
        var csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

        // Inisialisasi DataTable
        var guruTable = $('#guruTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "manage_guru_karyawan.php?ajax=1",
                type: "POST",
                data: function(d) {
                    d.case = 'LoadingGuru';
                    d.csrf_token = csrfToken;
                    d.jenjang = $('#filterJenjang').val();
                },
                beforeSend: function(){
                    $('#loadingSpinner').show();
                },
                complete: function(){
                    $('#loadingSpinner').hide();
                },
                error: function(){
                    showToast('Terjadi kesalahan saat memuat data.', 'error');
                }
            },
            columns: [
                { data: "id" },
                { data: "uid" },
                { data: "nip" },
                { data: "nama" },
                { data: "jenjang" },
                { data: "job_title" },
                { data: "status_kerja" },
                { data: "join_start" },
                { data: "masa_kerja" },
                { data: "pendidikan" },
                { data: "email" },
                { data: "no_hp" },
                { data: "aksi", orderable: false }
            ],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
            },
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Export Excel',
                    className: 'btn btn-success btn-sm',
                    exportOptions: { columns: ':not(:last-child)' }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> Export PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: { columns: ':not(:last-child)' },
                    customize: function (doc) {
                        doc.styles.tableHeader.fillColor = '#343a40';
                        doc.styles.tableHeader.color = 'white';
                        doc.defaultStyle.fontSize = 10;
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print"></i> Print',
                    className: 'btn btn-info btn-sm',
                    exportOptions: { columns: ':not(:last-child)' }
                },
                {
                    extend: 'colvis',
                    text: '<i class="fas fa-columns"></i> Kolom',
                    className: 'btn btn-warning btn-sm'
                }
            ],
            responsive: true,
            autoWidth: false
        });

        // Filter Apply & Reset
        $('#btnApplyFilter').on('click', function(){
            $.ajax({
                url: "manage_guru_karyawan.php?ajax=1",
                type: "POST",
                data: {
                    case: 'AddAuditLog',
                    csrf_token: csrfToken,
                    action: 'ApplyFilter',
                    details: 'Pengguna menerapkan filter data guru/karyawan.'
                },
                success: function(response){
                    if(response.code === 0){
                        showToast('Filter diterapkan.');
                    }
                },
                error: function(){
                    showToast('Gagal mencatat audit log.', 'error');
                }
            });
            guruTable.ajax.reload();
        });

        $('#btnResetFilter').on('click', function(){
            $('#filterForm')[0].reset();
            $.ajax({
                url: "manage_guru_karyawan.php?ajax=1",
                type: "POST",
                data: {
                    case: 'AddAuditLog',
                    csrf_token: csrfToken,
                    action: 'ResetFilter',
                    details: 'Pengguna mereset filter data guru/karyawan.'
                },
                success: function(response){
                    if(response.code === 0){
                        showToast('Filter direset.');
                    }
                },
                error: function(){
                    showToast('Gagal mencatat audit log.', 'error');
                }
            });
            guruTable.ajax.reload();
        });

        // Tombol Tambah
        $('#add-guru-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            var formData = form.serialize();
            $.ajax({
                url: "manage_guru_karyawan.php?ajax=1",
                type: "POST",
                data: formData,
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
                        guruTable.ajax.reload(null, false);
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

        // Tombol View Detail
        $(document).on('click', '.btn-view', function() {
            var id = $(this).data('id');
            $.ajax({
                url: "manage_guru_karyawan.php?ajax=1",
                type: "POST",
                data: { case: 'GetGuruDetail', id: id, csrf_token: csrfToken },
                dataType: "json",
                beforeSend: function(){
                    $('#loadingSpinner').show();
                },
                success: function(response) {
                    $('#loadingSpinner').hide();
                    if(response.code == 0) {
                        // Mapping field ke label yang sesuai dengan form edit
                        const fieldLabels = {
                            nip: "NIP",
                            uid: "UID",
                            nama: "Nama",
                            jenjang: "Jenjang",
                            job_title: "Job Title",
                            status_kerja: "Status Kerja",
                            join_start: "Join Start",
                            masa_kerja_tahun: "Masa Kerja (Tahun)",
                            masa_kerja_bulan: "Masa Kerja (Bulan)",
                            remark: "Remark",
                            jk: "Jenis Kelamin",
                            tanggal_lahir: "Tanggal Lahir",
                            usia: "Usia",
                            religion: "Agama",
                            alamat_domisili: "Alamat Domisili",
                            alamat_ktp: "Alamat KTP",
                            no_rekening: "No Rekening",
                            no_hp: "No HP",
                            pendidikan: "Pendidikan",
                            status_perkawinan: "Status Pernikahan",
                            email: "Email",
                            nama_suami: "Nama Suami",
                            jumlah_anak: "Jumlah Anak",
                            nama_anak_1: "Nama Anak 1",
                            nama_anak_2: "Nama Anak 2",
                            nama_anak_3: "Nama Anak 3",
                            salary_index_id: "Salary Index ID"
                        };
                        var detailHtml = '';
                        for (var key in fieldLabels) {
                            if(response.result.hasOwnProperty(key)) {
                                detailHtml += '<tr><th>' + fieldLabels[key] + '</th><td>' + response.result[key] + '</td></tr>';
                            }
                        }
                        $('#detailContent').html(detailHtml);
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

        // Tombol Edit
        $(document).on('click', '.btn-edit', function() {
            var id = $(this).data('id');
            var modal = $('#modalEdit');
            var form = $('#edit-guru-form');
            form[0].reset();
            form.removeClass('was-validated');
            $.ajax({
                url: "manage_guru_karyawan.php?ajax=1",
                type: "POST",
                data: { case: 'GetGuruDetail', id: id, csrf_token: csrfToken },
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
                        $('#editJenjang').val(response.result.jenjang.toUpperCase());
                        $('#editJobTitle').val(response.result.job_title);
                        $('#editStatus').val(response.result.status_kerja);
                        $('#editJoinStart').val(response.result.join_start);
                        $('#editMasaKerjaYear').val(response.result.masa_kerja_tahun);
                        $('#editMasaKerjaMonth').val(response.result.masa_kerja_bulan);
                        $('#editRemark').val(response.result.remark);
                        // Gunakan key "jk" yang telah dipetakan (hasil dari DB "jenis_kelamin")
                        $('#editJK').val(response.result.jk ? response.result.jk.trim() : '');
                        $('#editTglLahir').val(response.result.tanggal_lahir);
                        $('#editUsia').val(response.result.usia);
                        // Gunakan key "religion" yang telah dipetakan (hasil dari DB "agama")
                        $('#editReligion').val(response.result.religion ? response.result.religion.trim() : '');
                        $('#editAlamatDomisili').val(response.result.alamat_domisili);
                        $('#editAlamatKTP').val(response.result.alamat_ktp);
                        $('#editNoRekening').val(response.result.no_rekening);
                        $('#editNoHP').val(response.result.no_hp);
                        $('#editPendidikan').val(response.result.pendidikan);
                        $('#editMarital').val(response.result.status_perkawinan);
                        $('#editEmail').val(response.result.email);
                        $('#editNamaSuami').val(response.result.nama_suami);
                        $('#editJumlahAnak').val(response.result.jumlah_anak);
                        $('#editNamaAnak1').val(response.result.nama_anak_1);
                        $('#editNamaAnak2').val(response.result.nama_anak_2);
                        $('#editNamaAnak3').val(response.result.nama_anak_3);
                        $('#editSalaryIndex').val(response.result.salary_index_id);
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

        // Tombol Delete
        $(document).on('click', '.btn-delete', function() {
            var id = $(this).data('id');
            $('#delId').val(id);
            $('#delNama').text($(this).closest('tr').find('td:eq(3)').text());
            $('#modalDelete').modal('show');
        });

        // Proses Delete via AJAX
        $('#delete-guru-form').on('submit', function(e) {
            e.preventDefault();
            var id = $('#delId').val();
            if (!id) {
                showToast('ID tidak ditemukan.', 'error');
                return;
            }
            $.ajax({
                url: "manage_guru_karyawan.php?ajax=1",
                type: "POST",
                data: { case: 'DeleteGuru', id: id, csrf_token: csrfToken },
                dataType: "json",
                beforeSend: function(){
                    $('#delete-guru-form').find('button[type="submit"]').prop('disabled', true);
                },
                success: function(response) {
                    $('#delete-guru-form').find('button[type="submit"]').prop('disabled', false);
                    if(response.code == 0) {
                        showToast(response.result);
                        $('#modalDelete').modal('hide');
                        guruTable.ajax.reload(null, false);
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    $('#delete-guru-form').find('button[type="submit"]').prop('disabled', false);
                    showToast('Terjadi kesalahan saat menghapus data.', 'error');
                }
            });
        });

        // Proses form Edit via AJAX
        $('#edit-guru-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            var formData = form.serialize();
            $.ajax({
                url: "manage_guru_karyawan.php?ajax=1",
                type: "POST",
                data: formData,
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
                        guruTable.ajax.reload(null, false);
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

        // Fungsi menghitung masa kerja dan usia secara otomatis
        function calculateWorkExperience(joinDateStr) {
            if (!joinDateStr) return { years: '', months: '' };
            var joinDate = new Date(joinDateStr);
            var today = new Date();
            var years = today.getFullYear() - joinDate.getFullYear();
            var months = today.getMonth() - joinDate.getMonth();
            var days = today.getDate() - joinDate.getDate();
            if (days < 0) { months--; }
            if (months < 0) { years--; months += 12; }
            return { years: years, months: months };
        }
        function calculateAge(birthDateStr) {
            if (!birthDateStr) return '';
            var birthDate = new Date(birthDateStr);
            var today = new Date();
            var age = today.getFullYear() - birthDate.getFullYear();
            var m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) { age--; }
            return age;
        }
        $('#addJoinStart').on('change', function() {
            var result = calculateWorkExperience($(this).val());
            $('#addMasaKerjaYear').val(result.years);
            $('#addMasaKerjaMonth').val(result.months);
        });
        $('#addTglLahir').on('change', function() {
            $('#addUsia').val(calculateAge($(this).val()));
        });
        $('#editJoinStart').on('change', function() {
            var result = calculateWorkExperience($(this).val());
            $('#editMasaKerjaYear').val(result.years);
            $('#editMasaKerjaMonth').val(result.months);
        });
        $('#editTglLahir').on('change', function() {
            $('#editUsia').val(calculateAge($(this).val()));
        });

        // Modal Gaji Pokok
        $('#modalGajiPokok').on('show.bs.modal', function() {
            <?php
                $stmtGuru = $conn->prepare("SELECT gaji_pokok FROM gaji_pokok_roles WHERE role = 'guru'");
                $gajiGuru = 0;
                if ($stmtGuru) {
                    $stmtGuru->execute();
                    $stmtGuru->bind_result($gajiGuru);
                    $stmtGuru->fetch();
                    $stmtGuru->close();
                }
                $stmtKaryawan = $conn->prepare("SELECT gaji_pokok FROM gaji_pokok_roles WHERE role = 'karyawan'");
                $gajiKaryawan = 0;
                if ($stmtKaryawan) {
                    $stmtKaryawan->execute();
                    $stmtKaryawan->bind_result($gajiKaryawan);
                    $stmtKaryawan->fetch();
                    $stmtKaryawan->close();
                }
            ?>
            $('#gajiPokokGuru').val(<?= $gajiGuru; ?>);
            $('#gajiPokokKaryawan').val(<?= $gajiKaryawan; ?>);
        });

        // Proses form Atur Gaji Pokok
        $('#gaji-pokok-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            var formData = form.serialize();
            $.ajax({
                url: "manage_guru_karyawan.php?ajax=1",
                type: "POST",
                data: formData,
                dataType: "json",
                beforeSend: function(){
                    form.find('button[type="submit"]').prop('disabled', true);
                },
                success: function(response){
                    form.find('button[type="submit"]').prop('disabled', false);
                    if(response.code == 0) {
                        showToast(response.result);
                        $('#modalGajiPokok').modal('hide');
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    showToast('Terjadi kesalahan saat mengupdate gaji pokok.', 'error');
                }
            });
        });
    });
    </script>
</body>
</html>
