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
            case 'update_gaji_pokok':
                updateGajiPokok($conn);
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
    
    // Filter Jenjang jika dikirim
    $filterJenjang = isset($_POST['jenjang']) ? bersihkan_input($_POST['jenjang']) : '';

    // Total records
    $sqlTotal = "SELECT COUNT(*) as total FROM anggota_sekolah";
    $resultTotal = mysqli_query($conn, $sqlTotal);
    if (!$resultTotal) {
        send_response(1, 'Query Error: ' . mysqli_error($conn));
    }
    $rowTotal = mysqli_fetch_assoc($resultTotal);
    $recordsTotal = $rowTotal['total'];

    // Query filter: ambil semua kolom kecuali password
    $sqlFilter = "SELECT * FROM anggota_sekolah WHERE 1=1";
    $sqlFilterCount = "SELECT COUNT(*) as total FROM anggota_sekolah WHERE 1=1";
    $paramsFilter = [];
    $typesFilter = "";

    if (!empty($search)) {
        // Cari pada nip, uid, nama, jenjang, job_title
        $sqlFilter      .= " AND (nip LIKE ? OR uid LIKE ? OR nama LIKE ? OR jenjang LIKE ? OR job_title LIKE ?)";
        $sqlFilterCount .= " AND (nip LIKE ? OR uid LIKE ? OR nama LIKE ? OR jenjang LIKE ? OR job_title LIKE ?)";
        $searchParam = "%" . $search . "%";
        $paramsFilter = array_fill(0, 5, $searchParam);
        $typesFilter = "sssss";
    }
    
    // Filter berdasarkan jenjang
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

    // Order by (default: id DESC)
    $orderBy = " ORDER BY id DESC";
    $allowedColumns = [
        'id', 'uid', 'nip', 'nama', 'jenjang', 'job_title',
        'status_kerja', 'join_start', 'masa_kerja_tahun', 'masa_kerja_bulan',
        'remark', 'jenis_kelamin', 'tanggal_lahir', 'usia', 'agama',
        'alamat_domisili', 'alamat_ktp', 'no_rekening', 'no_hp', 'pendidikan',
        'status_perkawinan', 'email', 'nama_suami', 'jumlah_anak',
        'nama_anak_1', 'nama_anak_2', 'nama_anak_3', 'salary_index_id', 'gaji_pokok'
    ];
    if (isset($_POST['order'], $_POST['columns'])) {
        $columnIndex = intval($_POST['order'][0]['column']);
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
        // Tombol aksi untuk Edit/Hapus
        $aksi = '
<div class="dropdown">
  <button class="btn" type="button" id="dropdownMenuButton_' . htmlspecialchars($row['id']) . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <i class="bi bi-three-dots-vertical"></i>
  </button>
  <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_' . htmlspecialchars($row['id']) . '">
    <a class="dropdown-item btn-edit" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '" title="Edit">
        <i class="fas fa-pencil-alt"></i> Edit
    </a>
    <a class="dropdown-item btn-delete" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '" title="Hapus">
        <i class="fas fa-trash-alt"></i> Hapus
    </a>
  </div>
</div>';

        $data[] = [
            "id"                => $row['id'],
            "uid"               => $row['uid'],
            "nip"               => $row['nip'],
            "nama"              => $row['nama'],
            "jenjang"           => $row['jenjang'],
            "job_title"         => $row['job_title'],
            "status_kerja"      => getStatusBadge($row['status_kerja']),
            "join_start"        => !empty($row['join_start']) ? date("d-M-Y", strtotime($row['join_start'])) : '-',
            "masa_kerja_tahun"  => $row['masa_kerja_tahun'],
            "masa_kerja_bulan"  => $row['masa_kerja_bulan'],
            "remark"            => $row['remark'],
            "jenis_kelamin"     => $row['jenis_kelamin'],
            "tanggal_lahir"     => !empty($row['tanggal_lahir']) ? date("d-M-Y", strtotime($row['tanggal_lahir'])) : '-',
            "usia"              => $row['usia'],
            "agama"             => $row['agama'],
            "alamat_domisili"   => $row['alamat_domisili'],
            "alamat_ktp"        => $row['alamat_ktp'],
            "no_rekening"       => $row['no_rekening'],
            "no_hp"             => $row['no_hp'],
            "pendidikan"        => $row['pendidikan'],
            "status_perkawinan" => $row['status_perkawinan'],
            "email"             => $row['email'],
            "nama_suami"        => $row['nama_suami'],
            "jumlah_anak"       => $row['jumlah_anak'],
            "nama_anak_1"       => $row['nama_anak_1'],
            "nama_anak_2"       => $row['nama_anak_2'],
            "nama_anak_3"       => $row['nama_anak_3'],
            "salary_index_id"   => $row['salary_index_id'],
            "gaji_pokok"        => number_format($row['gaji_pokok'], 2, ',', '.'),
            "aksi"              => $aksi
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
    // Ambil data input dari form
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
    $no_hp             = isset($_POST['no_hp']) ? trim($_POST['no_hp']) : '';
    $pendidikan        = isset($_POST['pendidikan']) ? trim($_POST['pendidikan']) : '';
    $status_perkawinan = isset($_POST['marital']) ? trim($_POST['marital']) : '';
    $email             = isset($_POST['email']) ? trim($_POST['email']) : '';

    // Password default: '123456' (disimpan dalam bentuk MD5)
    $password_hashed = md5('123456');

    // Tentukan role berdasarkan job_title (untuk menentukan gaji pokok)
    $job_title_lower = strtolower($job_title);
    $role = (strpos($job_title_lower, 'guru') !== false) ? 'guru' : 'karyawan';
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

    // Insert data (UID akan di-generate secara internal misalnya melalui trigger atau logika lain)
    $sql = "INSERT INTO anggota_sekolah
        (nip, nama, jenjang, job_title, status_kerja, join_start, masa_kerja_tahun, masa_kerja_bulan, remark, jenis_kelamin, tanggal_lahir, usia, agama, alamat_domisili, alamat_ktp, no_hp, pendidikan, status_perkawinan, email, password, gaji_pokok)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtInsert = $conn->prepare($sql);
    if ($stmtInsert === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $type_str = "ssssssisssisssssssssd";
    $stmtInsert->bind_param($type_str, $nip, $nama, $jenjang, $job_title, $status_kerja, $join_start,
        $masa_kerja_tahun, $masa_kerja_bulan, $remark, $jenis_kelamin, $tanggal_lahir, $usia,
        $agama, $alamat_domisili, $alamat_ktp, $no_hp, $pendidikan, $status_perkawinan, $email, $password_hashed, $gaji_pokok);
    if ($stmtInsert->execute()) {
        // Catat audit log
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
        $stmt->close();
        // Catat audit log
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
    $no_hp = isset($_POST['no_hp']) ? trim($_POST['no_hp']) : '';
    $pendidikan = isset($_POST['pendidikan']) ? trim($_POST['pendidikan']) : '';
    $status_perkawinan = isset($_POST['marital']) ? trim($_POST['marital']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $gaji_pokok = isset($_POST['gaji_pokok']) ? floatval($_POST['gaji_pokok']) : 0.0;
    $password_plain = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Cek NIP unik untuk record lain
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
                remark=?, jenis_kelamin=?, tanggal_lahir=?, usia=?, agama=?, alamat_domisili=?, alamat_ktp=?, no_hp=?,
                pendidikan=?, status_perkawinan=?, email=?, password=?, gaji_pokok=?
            WHERE id=?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        if ($stmtUpdate) {
            $type_str = "ssssssiisssissssssssdii";
            $stmtUpdate->bind_param($type_str, $nip, $nama, $jenjang, $job_title, $status_kerja, $join_start,
                $masa_kerja_tahun, $masa_kerja_bulan, $remark, $jenis_kelamin, $tanggal_lahir, $usia, $agama,
                $alamat_domisili, $alamat_ktp, $no_hp, $pendidikan, $status_perkawinan, $email, $password_hashed, $gaji_pokok, $id);
        }
    } else {
        $sqlUpdate = "UPDATE anggota_sekolah
            SET nip=?, nama=?, jenjang=?, job_title=?, status_kerja=?, join_start=?, masa_kerja_tahun=?, masa_kerja_bulan=?,
                remark=?, jenis_kelamin=?, tanggal_lahir=?, usia=?, agama=?, alamat_domisili=?, alamat_ktp=?, no_hp=?,
                pendidikan=?, status_perkawinan=?, email=?, gaji_pokok=?
            WHERE id=?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        if ($stmtUpdate) {
            $type_str = "ssssssiisssisssssssdi";
            $stmtUpdate->bind_param($type_str, $nip, $nama, $jenjang, $job_title, $status_kerja, $join_start,
                $masa_kerja_tahun, $masa_kerja_bulan, $remark, $jenis_kelamin, $tanggal_lahir, $usia, $agama,
                $alamat_domisili, $alamat_ktp, $no_hp, $pendidikan, $status_perkawinan, $email, $gaji_pokok, $id);
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
    <style nonce="<?php echo $nonce; ?>">
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
        table.dataTable th, table.dataTable td {
            white-space: nowrap;
        }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <!-- End Sidebar -->

        <div id="content-wrapper" class="d-flex flex-column">
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

                    <!-- SweetAlert2 Toast -->
                    <script nonce="<?php echo $nonce; ?>">
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
                    </script>

                    <!-- Tombol Tambah & Atur Gaji Pokok -->
                    <div class="d-flex justify-content-end mb-3 flex-wrap">
                        <button class="btn btn-primary mr-2 mb-2" data-toggle="modal" data-target="#modalAdd">
                            <i class="fas fa-plus"></i> Tambah Guru/Karyawan
                        </button>
                        <button class="btn btn-success mb-2" data-toggle="modal" data-target="#modalGajiPokok">
                            <i class="fas fa-dollar-sign"></i> Atur Gaji Pokok
                        </button>
                    </div>

                    <!-- Filter Jenjang -->
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

                    <!-- Tabel Data Guru/Karyawan -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-table"></i> Daftar Guru/Karyawan</h6>
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
                                            <th>Masa Kerja Thn</th>
                                            <th>Masa Kerja Bln</th>
                                            <th>Remark</th>
                                            <th>Jenis Kelamin</th>
                                            <th>Tanggal Lahir</th>
                                            <th>Usia</th>
                                            <th>Agama</th>
                                            <th>Alamat Domisili</th>
                                            <th>Alamat KTP</th>
                                            <th>No Rekening</th>
                                            <th>No HP</th>
                                            <th>Pendidikan</th>
                                            <th>Status Pernikahan</th>
                                            <th>Email</th>
                                            <th>Nama Suami</th>
                                            <th>Jumlah Anak</th>
                                            <th>Nama Anak 1</th>
                                            <th>Nama Anak 2</th>
                                            <th>Nama Anak 3</th>
                                            <th>Salary Index ID</th>
                                            <th>Gaji Pokok</th>
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
        <form id="add-guru-form" class="needs-validation" novalidate>
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
              <!-- Field-field sesuai tabel anggota_sekolah (UID tidak diinput) -->
              <div class="form-group">
                  <label for="addNip">NIP <span class="text-danger">*</span></label>
                  <input type="text" name="nip" id="addNip" class="form-control" required placeholder="Masukkan NIP (6 digit)">
                  <div class="invalid-feedback">NIP wajib diisi.</div>
              </div>
              <div class="form-group">
                  <label for="addNama">Nama <span class="text-danger">*</span></label>
                  <input type="text" name="nama" id="addNama" class="form-control" required placeholder="Nama lengkap">
                  <div class="invalid-feedback">Nama wajib diisi.</div>
              </div>
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
              <div class="form-group">
                  <label for="addJobTitle">Job Title</label>
                  <input type="text" name="job_title" id="addJobTitle" class="form-control" placeholder="Contoh: Guru, Karyawan">
              </div>
              <div class="form-group">
                  <label for="addStatus">Status Kerja <span class="text-danger">*</span></label>
                  <select name="status" id="addStatus" class="form-control" required>
                      <option value="">-- Pilih Status --</option>
                      <option value="Tetap">Tetap</option>
                      <option value="Kontrak">Kontrak</option>
                  </select>
                  <div class="invalid-feedback">Status wajib dipilih.</div>
              </div>
              <div class="form-group">
                  <label for="addJoinStart">Join Start</label>
                  <input type="date" name="join_start" id="addJoinStart" class="form-control">
              </div>
              <div class="form-group">
                  <label>Masa Kerja</label>
                  <div class="form-row">
                      <div class="col">
                          <input type="number" name="masa_kerja_year" id="addMasaKerjaYear" class="form-control" placeholder="Tahun" readonly>
                      </div>
                      <div class="col">
                          <input type="number" name="masa_kerja_month" id="addMasaKerjaMonth" class="form-control" placeholder="Bulan" readonly>
                      </div>
                  </div>
              </div>
              <div class="form-group">
                  <label for="addRemark">Remark</label>
                  <textarea name="remark" id="addRemark" class="form-control" rows="2"></textarea>
              </div>
              <div class="form-group">
                  <label>Jenis Kelamin <span class="text-danger">*</span></label>
                  <div class="form-check">
                      <input type="radio" name="jk" id="jkL" value="L" class="form-check-input" required>
                      <label class="form-check-label" for="jkL">Laki-laki</label>
                  </div>
                  <div class="form-check">
                      <input type="radio" name="jk" id="jkP" value="P" class="form-check-input" required>
                      <label class="form-check-label" for="jkP">Perempuan</label>
                  </div>
              </div>
              <div class="form-group">
                  <label for="addTglLahir">Tanggal Lahir</label>
                  <input type="date" name="tgl_lahir" id="addTglLahir" class="form-control">
              </div>
              <div class="form-group">
                  <label for="addUsia">Usia</label>
                  <input type="number" name="usia" id="addUsia" class="form-control" placeholder="Dalam tahun" readonly>
              </div>
              <div class="form-group">
                  <label for="addReligion">Agama</label>
                  <input type="text" name="religion" id="addReligion" class="form-control" placeholder="Agama">
              </div>
              <div class="form-group">
                  <label for="addAlamatDomisili">Alamat Domisili</label>
                  <textarea name="alamat_domisili" id="addAlamatDomisili" class="form-control" rows="2"></textarea>
              </div>
              <div class="form-group">
                  <label for="addAlamatKTP">Alamat KTP</label>
                  <textarea name="alamat_ktp" id="addAlamatKTP" class="form-control" rows="2"></textarea>
              </div>
              <div class="form-group">
                  <label for="addNoHP">No Handphone</label>
                  <input type="text" name="no_hp" id="addNoHP" class="form-control" placeholder="08xxx">
              </div>
              <div class="form-group">
                  <label for="addPendidikan">Pendidikan</label>
                  <input type="text" name="pendidikan" id="addPendidikan" class="form-control">
              </div>
              <div class="form-group">
                  <label for="addMarital">Status Pernikahan</label>
                  <select name="marital" id="addMarital" class="form-control">
                      <option value="">-- Pilih Status Pernikahan --</option>
                      <option value="Menikah">Menikah</option>
                      <option value="Belum Menikah">Belum Menikah</option>
                      <option value="Duda">Duda</option>
                      <option value="Janda">Janda</option>
                  </select>
              </div>
              <div class="form-group">
                  <label for="addEmail">Email</label>
                  <input type="email" name="email" id="addEmail" class="form-control" placeholder="contoh@domain.com">
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

    <!-- MODAL: Edit Guru/Karyawan -->
    <div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form id="edit-guru-form" class="needs-validation" novalidate>
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
              <!-- Field-field sesuai tabel anggota_sekolah -->
              <div class="form-group">
                  <label for="editNip">NIP <span class="text-danger">*</span></label>
                  <input type="text" name="nip" id="editNip" class="form-control" required>
                  <div class="invalid-feedback">NIP wajib diisi.</div>
              </div>
              <div class="form-group">
                  <label for="editUid">UID</label>
                  <input type="text" name="uid" id="editUid" class="form-control" readonly>
              </div>
              <div class="form-group">
                  <label for="editNama">Nama <span class="text-danger">*</span></label>
                  <input type="text" name="nama" id="editNama" class="form-control" required>
                  <div class="invalid-feedback">Nama wajib diisi.</div>
              </div>
              <div class="form-group">
                  <label for="editJenjang">Jenjang <span class="text-danger">*</span></label>
                  <select name="jenjang" id="editJenjang" class="form-control" required>
                      <option value="">-- Pilih Jenjang --</option>
                      <option value="TK">TK</option>
                      <option value="SD">SD</option>
                      <option value="SMP">SMP</option>
                      <option value="SMA">SMA</option>
                      <option value="SMK">SMK</option>
                  </select>
              </div>
              <div class="form-group">
                  <label for="editJobTitle">Job Title</label>
                  <input type="text" name="job_title" id="editJobTitle" class="form-control">
              </div>
              <div class="form-group">
                  <label for="editStatus">Status Kerja <span class="text-danger">*</span></label>
                  <select name="status" id="editStatus" class="form-control" required>
                      <option value="">-- Pilih Status --</option>
                      <option value="Tetap">Tetap</option>
                      <option value="Kontrak">Kontrak</option>
                  </select>
              </div>
              <div class="form-group">
                  <label for="editJoinStart">Join Start</label>
                  <input type="date" name="join_start" id="editJoinStart" class="form-control">
              </div>
              <div class="form-group">
                  <label>Masa Kerja</label>
                  <div class="form-row">
                      <div class="col">
                          <input type="number" name="masa_kerja_year" id="editMasaKerjaYear" class="form-control" placeholder="Tahun" readonly>
                      </div>
                      <div class="col">
                          <input type="number" name="masa_kerja_month" id="editMasaKerjaMonth" class="form-control" placeholder="Bulan" readonly>
                      </div>
                  </div>
              </div>
              <div class="form-group">
                  <label for="editRemark">Remark</label>
                  <textarea name="remark" id="editRemark" class="form-control" rows="2"></textarea>
              </div>
              <div class="form-group">
                  <label>Jenis Kelamin <span class="text-danger">*</span></label>
                  <div class="form-check">
                      <input type="radio" name="jk" id="editJkL" value="L" class="form-check-input" required>
                      <label class="form-check-label" for="editJkL">Laki-laki</label>
                  </div>
                  <div class="form-check">
                      <input type="radio" name="jk" id="editJkP" value="P" class="form-check-input" required>
                      <label class="form-check-label" for="editJkP">Perempuan</label>
                  </div>
              </div>
              <div class="form-group">
                  <label for="editTglLahir">Tanggal Lahir</label>
                  <input type="date" name="tgl_lahir" id="editTglLahir" class="form-control">
              </div>
              <div class="form-group">
                  <label for="editUsia">Usia</label>
                  <input type="number" name="usia" id="editUsia" class="form-control" readonly>
              </div>
              <div class="form-group">
                  <label for="editReligion">Agama</label>
                  <input type="text" name="religion" id="editReligion" class="form-control">
              </div>
              <div class="form-group">
                  <label for="editAlamatDomisili">Alamat Domisili</label>
                  <textarea name="alamat_domisili" id="editAlamatDomisili" class="form-control" rows="2"></textarea>
              </div>
              <div class="form-group">
                  <label for="editAlamatKTP">Alamat KTP</label>
                  <textarea name="alamat_ktp" id="editAlamatKTP" class="form-control" rows="2"></textarea>
              </div>
              <div class="form-group">
                  <label for="editNoHP">No Handphone</label>
                  <input type="text" name="no_hp" id="editNoHP" class="form-control">
              </div>
              <div class="form-group">
                  <label for="editPendidikan">Pendidikan</label>
                  <input type="text" name="pendidikan" id="editPendidikan" class="form-control">
              </div>
              <div class="form-group">
                  <label for="editMarital">Status Pernikahan</label>
                  <select name="marital" id="editMarital" class="form-control">
                      <option value="">-- Pilih Status --</option>
                      <option value="Menikah">Menikah</option>
                      <option value="Belum Menikah">Belum Menikah</option>
                      <option value="Duda">Duda</option>
                      <option value="Janda">Janda</option>
                  </select>
              </div>
              <div class="form-group">
                  <label for="editEmail">Email</label>
                  <input type="email" name="email" id="editEmail" class="form-control">
              </div>
              <div class="form-group">
                  <label for="editGajiPokok">Gaji Pokok</label>
                  <input type="number" name="gaji_pokok" id="editGajiPokok" class="form-control" min="0" step="0.01" required>
              </div>
              <div class="form-group">
                  <label for="editPassword">Password Baru (Opsional)</label>
                  <input type="password" name="password" id="editPassword" class="form-control" placeholder="Isi jika ingin mengubah password">
                  <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah password.</small>
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
                <span>&times;</span>
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
              <span>&times;</span>
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
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js" nonce="<?php echo $nonce; ?>"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" nonce="<?php echo $nonce; ?>"></script>
    <script nonce="<?php echo $nonce; ?>">
    $(document).ready(function() {
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

        var csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

        // Inisialisasi DataTable dengan server‑side processing
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
                { data: "masa_kerja_tahun" },
                { data: "masa_kerja_bulan" },
                { data: "remark" },
                { data: "jenis_kelamin" },
                { data: "tanggal_lahir" },
                { data: "usia" },
                { data: "agama" },
                { data: "alamat_domisili" },
                { data: "alamat_ktp" },
                { data: "no_rekening" },
                { data: "no_hp" },
                { data: "pendidikan" },
                { data: "status_perkawinan" },
                { data: "email" },
                { data: "nama_suami" },
                { data: "jumlah_anak" },
                { data: "nama_anak_1" },
                { data: "nama_anak_2" },
                { data: "nama_anak_3" },
                { data: "salary_index_id" },
                { data: "gaji_pokok" },
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
                        showToast('Filter diterapkan.', 'success');
                    }
                },
                error: function(){
                    showToast('Gagal mencatat audit log.', 'warning');
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
                        showToast('Filter direset.', 'success');
                    }
                },
                error: function(){
                    showToast('Gagal mencatat audit log.', 'warning');
                }
            });
            guruTable.ajax.reload();
        });

        // Proses form Tambah Guru/Karyawan
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
                        showToast(response.result, 'success');
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

        // Modal Edit: ambil detail via AJAX
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
                        $('#editJenjang').val(response.result.jenjang);
                        $('#editJobTitle').val(response.result.job_title);
                        $('#editStatus').val(response.result.status_kerja);
                        $('#editJoinStart').val(response.result.join_start);
                        $('#editMasaKerjaYear').val(response.result.masa_kerja_tahun);
                        $('#editMasaKerjaMonth').val(response.result.masa_kerja_bulan);
                        $('#editRemark').val(response.result.remark);
                        if(response.result.jenis_kelamin === 'L') {
                            $('#editJkL').prop('checked', true);
                        } else {
                            $('#editJkP').prop('checked', true);
                        }
                        $('#editTglLahir').val(response.result.tanggal_lahir);
                        $('#editUsia').val(response.result.usia);
                        $('#editReligion').val(response.result.agama);
                        $('#editAlamatDomisili').val(response.result.alamat_domisili);
                        $('#editAlamatKTP').val(response.result.alamat_ktp);
                        $('#editNoHP').val(response.result.no_hp);
                        $('#editPendidikan').val(response.result.pendidikan);
                        $('#editMarital').val(response.result.status_perkawinan);
                        $('#editEmail').val(response.result.email);
                        $('#editGajiPokok').val(response.result.gaji_pokok);
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

        // Proses Update Guru/Karyawan
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
                        showToast(response.result, 'success');
                        $('#modalEdit').modal('hide');
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
                    showToast('Terjadi kesalahan saat mengupdate data.', 'error');
                }
            });
        });

        // Modal Delete: ambil ID untuk hapus
        $(document).on('click', '.btn-delete', function() {
            var id = $(this).data('id');
            $('#delId').val(id);
            // Ambil nama dari kolom "nama" (misal kolom ke-4)
            $('#delNama').text($(this).closest('tr').find('td:eq(3)').text());
            $('#modalDelete').modal('show');
        });

        // Proses Delete Guru/Karyawan
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
                        showToast(response.result, 'success');
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

        // Fungsi menghitung masa kerja dan usia
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

        // Saat modal Gaji Pokok dibuka, isi inputnya dari DB
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

        // Proses form Atur Gaji Pokok via AJAX
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
                        showToast(response.result, 'success');
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
