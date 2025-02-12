<?php
// File: /payroll_absensi_v2/kelola/manage_guru_karyawan.php

// =========================
// 1. Pengaturan Keamanan, Session, dan Koneksi
// =========================

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

require_once __DIR__ . '/../../helpers.php'; // Pastikan file ini berisi fungsi‐fungsi bantuan
start_session_safe();
init_error_handling();
generate_csrf_token();

$nonce = base64_encode(random_bytes(16));
$_SESSION['csp_nonce'] = $nonce;

if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: " . $redirect);
    exit();
}
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
generate_csrf_token();

function authorize($allowed_roles = ['sdm', 'superadmin']) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        send_response(403, 'Akses ditolak.');
    }
}
authorize();

require_once __DIR__ . '/../../koneksi.php';
if (ob_get_length()) ob_end_clean();

// Perhatikan bahwa pada header CSP kami menghindari newline dan menyertakan nonce
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.datatables.net https://cdn.jsdelivr.net 'nonce-$nonce'; style-src 'self' https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com https://cdn.datatables.net https://cdn.jsdelivr.net 'nonce-$nonce'; img-src 'self'; font-src 'self' https://cdnjs.cloudflare.com; connect-src 'self'");

// =========================
// Fungsi Helper
// =========================

/**
 * Menghasilkan Badge (label) untuk Status Kerja.
 */
function getStatusBadge($status) {
    $status_lower = strtolower($status);
    switch ($status_lower) {
        case 'tetap':
            return '<span class="badge bg-success">Tetap</span>';
        case 'kontrak':
            return '<span class="badge bg-secondary">Kontrak</span>';
        default:
            return '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
}

/**
 * Mengambil Gaji Pokok berdasarkan role (P = guru, lainnya = karyawan)
 */
function getGajiPokokByRole($conn, $role) {
    if ($role === 'P') {
        $lookup = 'guru';
    } else {
        $lookup = 'karyawan';
    }
    $stmt = $conn->prepare("SELECT gaji_pokok FROM gaji_pokok_roles WHERE role = ?");
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

// =========================
// 2. Menangani Permintaan AJAX
// =========================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf_token = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';
        verify_csrf_token($csrf_token);
        authorize();

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
            case 'update_gaji_strata_guru':
                updateGajiStrataGuru($conn);
                break;
            case 'update_gaji_strata_karyawan':
                updateGajiStrataKaryawan($conn);
                break;
            case 'update_tunjangan':
                updateTunjangan($conn);
                break;
            case 'AssignPayheadsToEmployee':
                AssignPayheadsToEmployee($conn);
                break;
            // Tambahan kasus lain jika diperlukan (misalnya AddAuditLog)
            default:
                send_response(404, 'Kasus tidak ditemukan.');
        }
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    exit();
}

// =========================
// Fungsi-Fungsi AJAX CRUD
// =========================

function LoadingGuru($conn) {
    // Contoh sederhana untuk keperluan DataTables
    $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = isset($_POST['search']['value']) ? bersihkan_input($_POST['search']['value']) : '';

    $sqlTotal = "SELECT COUNT(*) as total FROM anggota_sekolah";
    $resultTotal = mysqli_query($conn, $sqlTotal);
    $rowTotal = mysqli_fetch_assoc($resultTotal);
    $recordsTotal = $rowTotal['total'];

    $sql = "SELECT * FROM anggota_sekolah WHERE 1=1";
    if (!empty($search)) {
        $sql .= " AND (nip LIKE '%$search%' OR nama LIKE '%$search%')";
    }
    $sql .= " ORDER BY id DESC LIMIT $start, $length";
    $result = mysqli_query($conn, $sql);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $aksi = '
<div class="dropdown">
  <button class="btn" type="button" id="dropdownMenuButton_' . htmlspecialchars($row['id']) . '" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <i class="bi bi-three-dots-vertical"></i>
  </button>
  <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_' . htmlspecialchars($row['id']) . '">
    <a class="dropdown-item btn-view" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '">
      <i class="fas fa-eye"></i> View Detail
    </a>
    <a class="dropdown-item btn-edit" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '">
      <i class="fas fa-pencil-alt"></i> Edit
    </a>
    <a class="dropdown-item btn-delete" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '">
      <i class="fas fa-trash-alt"></i> Hapus
    </a>
    <!-- Tombol Assign Payheads dan Select Month -->
    <a class="dropdown-item btn-assign" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '">
      <i class="bi bi-cash-stack"></i> Assign Payheads
    </a>
    <a class="dropdown-item btn-select-month" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '">
      <i class="fa fa-calendar"></i> Select Month
    </a>
  </div>
</div>';
        $data[] = [
            "id"           => $row['id'],
            "uid"          => htmlspecialchars($row['uid']),
            "nip"          => htmlspecialchars($row['nip']),
            "nama"         => htmlspecialchars($row['nama']),
            "jenjang"      => htmlspecialchars($row['jenjang']),
            "job_title"    => htmlspecialchars($row['job_title']),
            "role"         => htmlspecialchars($row['role']),
            "status_kerja" => getStatusBadge($row['status_kerja']),
            "masa_kerja"   => $row['masa_kerja_tahun'] . " Thn " . $row['masa_kerja_bulan'] . " Bln",
            "pendidikan"   => htmlspecialchars($row['pendidikan']),
            "email"        => htmlspecialchars($row['email']),
            "no_hp"        => htmlspecialchars($row['no_hp']),
            "aksi"         => $aksi
        ];
    }
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $recordsTotal,
        "recordsFiltered" => $recordsTotal,
        "data" => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

function CreateGuru($conn) {
    // Tangkap input, termasuk kolom pendidikan
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
    $nama_suami      = bersihkan_input($_POST['nama_suami'] ?? '');
    $jumlah_anak     = (int)($_POST['jumlah_anak'] ?? 0);
    $salary_index_id = (int)($_POST['salary_index_id'] ?? null);
    $nama_anak_1     = bersihkan_input($_POST['nama_anak_1'] ?? '');
    $nama_anak_2     = bersihkan_input($_POST['nama_anak_2'] ?? '');
    $nama_anak_3     = bersihkan_input($_POST['nama_anak_3'] ?? '');

    if (empty($nip) || empty($nama) || empty($jenjang) || empty($role)) {
        send_response(1, 'NIP, Nama, Jenjang, dan Role wajib diisi.');
    }

    // Cek duplikasi NIP
    $sqlCek = "SELECT id FROM anggota_sekolah WHERE nip = ?";
    $stmtCek = $conn->prepare($sqlCek);
    if (!$stmtCek) {
        send_response(1, 'Terjadi kesalahan query: ' . $conn->error);
    }
    $stmtCek->bind_param("s", $nip);
    $stmtCek->execute();
    $resultCek = $stmtCek->get_result();
    if ($resultCek && $resultCek->num_rows > 0) {
        send_response(1, 'NIP sudah digunakan.');
    }
    $stmtCek->close();

    $uid = generateUID($conn);
    $status_kerja = 'Tetap';
    
    // AUTO-GENERATE GAJI POKOK BERDASARKAN STRATA
    if ($role === 'P' && !empty($pendidikan)) {
        $query = "SELECT gaji_pokok FROM gaji_pokok_strata_guru WHERE jenjang = ? AND strata = ? LIMIT 1";
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
        masa_kerja_tahun, masa_kerja_bulan, remark, jenis_kelamin, tanggal_lahir,
        usia, agama, alamat_domisili, alamat_ktp, no_rekening, no_hp,
        pendidikan, status_perkawinan, email, nama_suami, jumlah_anak,
        nama_anak_1, nama_anak_2, nama_anak_3, salary_index_id, gaji_pokok, role
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, '', ?, ?, ?, ?, ?, ?, ?, ?, '', '', ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_response(1, 'Terjadi kesalahan query: ' . $conn->error);
    }
    $stmt->bind_param(
        "sssssssisssssssssssssssssds",
        $uid,
        $nip,
        $defaultPassword,
        $nama,
        $jenjang,
        $job_title,
        $status_kerja,
        $jk,
        $tgl_lahir,
        $usia,
        $religion,
        $alamat_domisili,
        $alamat_ktp,
        $no_rekening,
        $no_hp,
        $email,
        $nama_suami,
        $jumlah_anak,
        $nama_anak_1,
        $nama_anak_2,
        $nama_anak_3,
        $salary_index_id,
        $gaji_pokok,
        $role
    );
    $exec = $stmt->execute();
    if ($exec) {
        $idBaru = $stmt->insert_id;
        $stmt->close();
        $user_id = $_SESSION['user_id'] ?? 0;
        $details_log = "Menambah Guru/Karyawan baru (ID: $idBaru), NIP=$nip, Nama=$nama.";
        add_audit_log($conn, $user_id, 'CreateGuru', $details_log);
        send_response(0, 'Data berhasil disimpan.');
    } else {
        $stmt->close();
        send_response(1, 'Gagal menyimpan data: ' . $conn->error);
    }
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
        $data['religion'] = $data['agama'];
        $data['jk'] = $data['jenis_kelamin'];
        $stmt->close();
        $user_id = $_SESSION['user_id'] ?? 0;
        $details_log = "Melihat detail data guru/karyawan ID $id: Nama='" . $data['nama'] . "'.";
        add_audit_log($conn, $user_id, 'GetGuruDetail', $details_log);
        send_response(0, $data);
    } else {
        send_response(2, 'Data tidak ditemukan.');
    }
    $stmt->close();
    exit();
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
    $nama_suami      = bersihkan_input($_POST['nama_suami'] ?? '');
    $jumlah_anak     = (int)($_POST['jumlah_anak'] ?? 0);
    $nama_anak_1     = bersihkan_input($_POST['nama_anak_1'] ?? '');
    $nama_anak_2     = bersihkan_input($_POST['nama_anak_2'] ?? '');
    $nama_anak_3     = bersihkan_input($_POST['nama_anak_3'] ?? '');
    $salary_index_id = (int)($_POST['salary_index_id'] ?? null);
    $password_baru   = $_POST['password'] ?? '';

    if ($id <= 0) {
        send_response(1, 'ID tidak valid.');
    }
    if (empty($nip) || empty($nama) || empty($jenjang) || empty($role)) {
        send_response(1, 'NIP, Nama, Jenjang, dan Role wajib diisi.');
    }

    $sqlCek = "SELECT id FROM anggota_sekolah WHERE nip = ? AND id <> ?";
    $stmtCek = $conn->prepare($sqlCek);
    if (!$stmtCek) {
        send_response(1, 'Terjadi kesalahan query: ' . $conn->error);
    }
    $stmtCek->bind_param("si", $nip, $id);
    $stmtCek->execute();
    $resultCek = $stmtCek->get_result();
    if ($resultCek && $resultCek->num_rows > 0) {
        send_response(1, 'NIP sudah digunakan oleh user lain.');
    }
    $stmtCek->close();

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
        'nama_suami'      => $nama_suami,
        'jumlah_anak'     => $jumlah_anak,
        'nama_anak_1'     => $nama_anak_1,
        'nama_anak_2'     => $nama_anak_2,
        'nama_anak_3'     => $nama_anak_3,
        'salary_index_id' => $salary_index_id
    ];

    if (!empty($password_baru)) {
        $fields['password'] = password_hash($password_baru, PASSWORD_DEFAULT);
    }

    $updates = [];
    $types   = '';
    $values  = [];
    foreach ($fields as $col => $val) {
        $updates[] = "$col = ?";
        if ($col === 'salary_index_id' || $col === 'jumlah_anak' || $col === 'usia') {
            $types .= 'i';
        } else {
            $types .= 's';
        }
        $values[] = $val;
    }

    $sqlUpdate = "UPDATE anggota_sekolah SET " . implode(", ", $updates) . " WHERE id = ?";
    $types .= 'i';
    $values[] = $id;

    $stmt = $conn->prepare($sqlUpdate);
    if (!$stmt) {
        send_response(1, 'Terjadi kesalahan query: ' . $conn->error);
    }
    $stmt->bind_param($types, ...$values);
    $exec = $stmt->execute();
    if ($exec) {
        $stmt->close();
        $user_id = $_SESSION['user_id'] ?? 0;
        $details_log = "Update data Guru/Karyawan ID $id, NIP=$nip, Nama=$nama.";
        add_audit_log($conn, $user_id, 'UpdateGuru', $details_log);
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

    $stmtFind = $conn->prepare("SELECT nama, nip FROM anggota_sekolah WHERE id = ?");
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

    $stmtDel = $conn->prepare("DELETE FROM anggota_sekolah WHERE id = ?");
    if (!$stmtDel) {
        send_response(1, 'Query error: ' . $conn->error);
    }
    $stmtDel->bind_param("i", $id);
    $execDel = $stmtDel->execute();
    $stmtDel->close();

    if ($execDel) {
        $user_id = $_SESSION['user_id'] ?? 0;
        $details_log = "Menghapus Guru/Karyawan ID $id, NIP=" . $row['nip'] . ", Nama=" . $row['nama'];
        add_audit_log($conn, $user_id, 'DeleteGuru', $details_log);
        send_response(0, 'Data berhasil dihapus.');
    } else {
        send_response(1, 'Gagal menghapus data: ' . $conn->error);
    }
}

function updateGajiPokok($conn) {
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
        $user_id = $_SESSION['user_id'] ?? 0;
        $details_log = "Update gaji pokok: Guru=$gaji_guru, Karyawan=$gaji_karyawan.";
        add_audit_log($conn, $user_id, 'update_gaji_pokok', $details_log);
        send_response(0, 'Gaji pokok berhasil diupdate.');
    } else {
        send_response(1, 'Gagal update gaji pokok.');
    }
}

function updateGajiStrataGuru($conn) {
    $updates = [
        ['jenjang'=>'TK', 'strata'=>'D3', 'gaji'=> isset($_POST['tk_d3']) ? floatval($_POST['tk_d3']) : 0],
        ['jenjang'=>'TK', 'strata'=>'S1', 'gaji'=> isset($_POST['tk_s1']) ? floatval($_POST['tk_s1']) : 0],
        ['jenjang'=>'TK', 'strata'=>'S2', 'gaji'=> isset($_POST['tk_s2']) ? floatval($_POST['tk_s2']) : 0],
        ['jenjang'=>'SD', 'strata'=>'D3', 'gaji'=> isset($_POST['sd_d3']) ? floatval($_POST['sd_d3']) : 0],
        ['jenjang'=>'SD', 'strata'=>'S1', 'gaji'=> isset($_POST['sd_s1']) ? floatval($_POST['sd_s1']) : 0],
        ['jenjang'=>'SD', 'strata'=>'S2', 'gaji'=> isset($_POST['sd_s2']) ? floatval($_POST['sd_s2']) : 0],
        ['jenjang'=>'SMP', 'strata'=>'S1', 'gaji'=> isset($_POST['smp_s1']) ? floatval($_POST['smp_s1']) : 0],
        ['jenjang'=>'SMP', 'strata'=>'S2', 'gaji'=> isset($_POST['smp_s2']) ? floatval($_POST['smp_s2']) : 0],
        ['jenjang'=>'SMA', 'strata'=>'S1', 'gaji'=> isset($_POST['sma_s1']) ? floatval($_POST['sma_s1']) : 0],
        ['jenjang'=>'SMA', 'strata'=>'S2', 'gaji'=> isset($_POST['sma_s2']) ? floatval($_POST['sma_s2']) : 0],
        ['jenjang'=>'SMA', 'strata'=>'S3', 'gaji'=> isset($_POST['sma_s3']) ? floatval($_POST['sma_s3']) : 0],
    ];
    $allSuccess = true;
    foreach ($updates as $upd) {
        $stmt = $conn->prepare("UPDATE gaji_pokok_strata_guru SET gaji_pokok=? WHERE jenjang=? AND strata=?");
        if (!$stmt) {
            send_response(1, 'Query error: ' . $conn->error);
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
        $user_id = $_SESSION['user_id'] ?? 0;
        add_audit_log($conn, $user_id, 'update_gaji_strata_guru', "Gaji pokok berdasarkan strata untuk Guru berhasil diperbarui.");
        send_response(0, 'Gaji pokok strata Guru berhasil diupdate.');
    } else {
        send_response(1, 'Gagal update gaji pokok strata Guru.');
    }
}

function updateGajiStrataKaryawan($conn) {
    $updates = [
        ['jenjang'=>'TK', 'strata'=>'D3', 'gaji'=> isset($_POST['tk_d3']) ? floatval($_POST['tk_d3']) : 0],
        ['jenjang'=>'TK', 'strata'=>'S1', 'gaji'=> isset($_POST['tk_s1']) ? floatval($_POST['tk_s1']) : 0],
        ['jenjang'=>'TK', 'strata'=>'S2', 'gaji'=> isset($_POST['tk_s2']) ? floatval($_POST['tk_s2']) : 0],
        ['jenjang'=>'SD', 'strata'=>'D3', 'gaji'=> isset($_POST['sd_d3']) ? floatval($_POST['sd_d3']) : 0],
        ['jenjang'=>'SD', 'strata'=>'S1', 'gaji'=> isset($_POST['sd_s1']) ? floatval($_POST['sd_s1']) : 0],
        ['jenjang'=>'SD', 'strata'=>'S2', 'gaji'=> isset($_POST['sd_s2']) ? floatval($_POST['sd_s2']) : 0],
        ['jenjang'=>'SMP', 'strata'=>'S1', 'gaji'=> isset($_POST['smp_s1']) ? floatval($_POST['smp_s1']) : 0],
        ['jenjang'=>'SMP', 'strata'=>'S2', 'gaji'=> isset($_POST['smp_s2']) ? floatval($_POST['smp_s2']) : 0],
        ['jenjang'=>'SMA', 'strata'=>'S1', 'gaji'=> isset($_POST['sma_s1']) ? floatval($_POST['sma_s1']) : 0],
        ['jenjang'=>'SMA', 'strata'=>'S2', 'gaji'=> isset($_POST['sma_s2']) ? floatval($_POST['sma_s2']) : 0],
        ['jenjang'=>'SMA', 'strata'=>'S3', 'gaji'=> isset($_POST['sma_s3']) ? floatval($_POST['sma_s3']) : 0],
    ];
    $allSuccess = true;
    foreach ($updates as $upd) {
        $stmt = $conn->prepare("UPDATE gaji_pokok_strata_karyawan SET gaji_pokok=? WHERE jenjang=? AND strata=?");
        if (!$stmt) {
            send_response(1, 'Query error: ' . $conn->error);
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
        $user_id = $_SESSION['user_id'] ?? 0;
        add_audit_log($conn, $user_id, 'update_gaji_strata_karyawan', "Gaji pokok berdasarkan strata untuk Karyawan berhasil diperbarui.");
        send_response(0, 'Gaji pokok strata Karyawan berhasil diupdate.');
    } else {
        send_response(1, 'Gagal update gaji pokok strata Karyawan.');
    }
}

function updateTunjangan($conn) {
    $tunjangan_struktural = isset($_POST['tunjangan_struktural']) ? floatval($_POST['tunjangan_struktural']) : 0;
    $tunjangan_walikelas = isset($_POST['tunjangan_walikelas']) ? floatval($_POST['tunjangan_walikelas']) : 0;
    $tunjangan_kehadiran = isset($_POST['tunjangan_kehadiran']) ? floatval($_POST['tunjangan_kehadiran']) : 0;
    
    $stmt = $conn->prepare("UPDATE tunjangan SET tunjangan_struktural=?, tunjangan_walikelas=?, tunjangan_kehadiran=? WHERE id=1");
    if (!$stmt) {
        send_response(1, 'Query error: ' . $conn->error);
    }
    $stmt->bind_param("ddd", $tunjangan_struktural, $tunjangan_walikelas, $tunjangan_kehadiran);
    $exec = $stmt->execute();
    $stmt->close();
    if ($exec) {
        $user_id = $_SESSION['user_id'] ?? 0;
        add_audit_log($conn, $user_id, 'update_tunjangan', "Update tunjangan: Struktural=$tunjangan_struktural, Wali Kelas/Strata/Profesi=$tunjangan_walikelas, Kehadiran Fix=$tunjangan_kehadiran.");
        send_response(0, 'Tunjangan berhasil diupdate.');
    } else {
        send_response(1, 'Gagal update tunjangan.');
    }
}

function generateUID($conn) {
    do {
        $uid = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        $stmt = $conn->prepare("SELECT id FROM anggota_sekolah WHERE uid = ? LIMIT 1");
        $stmt->bind_param("s", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res && $res->num_rows > 0;
        $stmt->close();
    } while ($exists);
    return $uid;
}

/**
 * Fungsi AssignPayheadsToEmployee:
 * Menetapkan payheads ke karyawan sekaligus menyimpan alasan perubahan dan dokumen pendukung.
 * Data payroll disimpan dengan status "draft".
 */
function AssignPayheadsToEmployee($conn) {
    // Ambil data
    $empcode       = isset($_POST['empcode']) ? intval($_POST['empcode']) : 0;
    $payheads      = isset($_POST['payheads']) ? $_POST['payheads'] : [];
    $pay_amounts   = isset($_POST['pay_amounts']) ? $_POST['pay_amounts'] : [];
    $change_reason = isset($_POST['change_reason']) ? bersihkan_input($_POST['change_reason']) : '';
    
    if ($empcode <= 0) {
        send_response(1, 'ID karyawan tidak valid.');
    }
    if (empty($payheads)) {
        send_response(1, 'Tidak ada payheads yang dipilih.');
    }
    
    // Tangani file upload dokumen pendukung, jika ada
    $doc_path = null;
    if (isset($_FILES['supporting_document']) && $_FILES['supporting_document']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['supporting_document']['tmp_name'];
        $fileName = $_FILES['supporting_document']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedfileExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($fileExtension, $allowedfileExtensions)) {
            send_response(1, 'Tipe file tidak diperbolehkan. Hanya PDF, JPG, JPEG, dan PNG yang diperbolehkan.');
        }
        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
        $uploadFileDir = __DIR__ . '/../../uploads/changes/';
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0755, true);
        }
        $dest_path = $uploadFileDir . $newFileName;
        if(!move_uploaded_file($fileTmpPath, $dest_path)) {
            send_response(1, 'Terjadi kesalahan saat mengunggah file.');
        }
        $doc_path = '/uploads/changes/' . $newFileName;
    }
    
    $conn->begin_transaction();
    try {
        $stmtDelete = $conn->prepare("DELETE FROM employee_payheads WHERE id_anggota = ?");
        $stmtDelete->bind_param("i", $empcode);
        if (!$stmtDelete->execute()) {
            throw new Exception("Execute failed: " . $stmtDelete->error);
        }
        $stmtDelete->close();

        $stmtGetJenis = $conn->prepare("SELECT jenis FROM payheads WHERE id = ? LIMIT 1");
        if ($stmtGetJenis === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmtInsert = $conn->prepare("INSERT INTO employee_payheads (id_anggota, id_payhead, jenis, amount, change_reason, doc_path, status_payroll)
            VALUES (?, ?, ?, ?, ?, ?, 'draft')");
        if ($stmtInsert === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        foreach ($payheads as $payhead_id) {
            $payhead_id = intval($payhead_id);
            $nilai = isset($pay_amounts[$payhead_id])
                ? floatval(str_replace(['.', ','], ['', '.'], $pay_amounts[$payhead_id]))
                : 0.0;
            if ($nilai < 0) {
                throw new Exception("Nilai payhead tidak boleh negatif.");
            }
            $stmtGetJenis->bind_param("i", $payhead_id);
            if (!$stmtGetJenis->execute()) {
                throw new Exception("Execute failed: " . $stmtGetJenis->error);
            }
            $resultJenis = $stmtGetJenis->get_result();
            if ($resultJenis->num_rows === 0) {
                throw new Exception("Payhead dengan ID $payhead_id tidak ditemukan.");
            }
            $rowJenis = $resultJenis->fetch_assoc();
            $jenis = $rowJenis['jenis'];
            
            $stmtInsert->bind_param("iisdss", $empcode, $payhead_id, $jenis, $nilai, $change_reason, $doc_path);
            if (!$stmtInsert->execute()) {
                throw new Exception("Insert failed: " . $stmtInsert->error);
            }
        }
        $stmtGetJenis->close();
        $stmtInsert->close();
        
        $conn->commit();
        $user_id = $_SESSION['user_id'] ?? 0;
        $details = "Menetapkan payheads ke karyawan ID $empcode. Payheads: " . implode(', ', $payheads) .
                   ". Alasan: " . $change_reason .
                   ($doc_path ? " (Dokumen: $doc_path)" : "");
        add_audit_log($conn, $user_id, 'AssignPayheadsToEmployee', $details);
        send_response(0, 'Payheads berhasil ditugaskan / diperbarui.');
    } catch (Exception $e) {
        $conn->rollback();
        send_response(1, 'Gagal menugaskan payheads: ' . $e->getMessage());
    }
}

// =========================
// 3. Render Halaman HTML (jika bukan AJAX)
// =========================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Data Guru/Karyawan - Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- CSS dari CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" nonce="<?php echo $nonce; ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/startbootstrap-sb-admin-2/4.1.4/css/sb-admin-2.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <link href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap4.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <link href="https://cdn.datatables.net/buttons/1.7.1/css/buttons.bootstrap4.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <link href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" nonce="<?php echo $nonce; ?>">
    <style nonce="<?php echo $nonce; ?>">
        body, .text-gray-800 { color: #000 !important; }
        #guruTable th, #guruTable td { vertical-align: middle; white-space: nowrap; }
        .table-responsive { overflow-x: auto; }
        .card-header { background: linear-gradient(45deg, #0d47a1, #42a5f5); color: white; }
        #loadingSpinner { display: none; position: fixed; z-index: 9999; height: 100px; width: 100px; margin: auto; top: 0; left: 0; bottom: 0; right: 0; }
        .invisible-field { display: none; }
        #ManageModal .modal-dialog {
            max-width: 1000px;
            margin: auto;
            padding-top: 70px;
            color: #000 !important;
        }
    </style>
</head>
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <!-- End of Sidebar -->
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <?php include __DIR__ . '/../../navbar.php'; ?>
                <!-- End of Topbar -->
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
                        <button class="btn btn-info ms-2 mb-2" data-bs-toggle="modal" data-bs-target="#modalTunjangan">
                            <i class="fas fa-hand-holding-usd"></i> Tunjangan
                        </button>
                    </div>

                    <!-- FILTER -->
                    <div class="card mb-4">
                        <div class="card-header">Filter Data Guru/Karyawan</div>
                        <div class="card-body">
                            <form id="filterForm" method="GET" class="form-inline">
                                <label class="me-2" for="filterJenjang">Jenjang:</label>
                                <select class="form-control me-2" id="filterJenjang" name="jenjang">
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
                                <button type="button" id="btnApplyFilter" class="btn btn-primary me-2">
                                    <i class="fas fa-filter"></i> Terapkan
                                </button>
                                <button type="button" id="btnResetFilter" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Tabel Data -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-white">
                                <i class="fas fa-table"></i> Daftar Guru/Karyawan
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="guruTable" class="table table-sm table-bordered table-striped display nowrap text-dark" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>UID</th>
                                            <th>NIP</th>
                                            <th>Nama</th>
                                            <th>Jenjang</th>
                                            <th>Job Title</th>
                                            <th>Role</th>
                                            <th>Status Kerja</th>
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

                    <div id="loadingSpinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>

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
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
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
                          <input type="text" name="job_title" id="addJobTitle" class="form-control" placeholder="Contoh: Guru, Karyawan">
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
              <!-- Kolom Pendidikan untuk auto-generate gaji berdasarkan strata -->
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="addPendidikan">Pendidikan (misal: S1, S2, dsb.)</label>
                          <input type="text" name="pendidikan" id="addPendidikan" class="form-control" placeholder="Masukkan strata pendidikan, contoh: S1">
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

    <!-- MODAL: Edit Guru/Karyawan -->
    <div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form id="edit-guru-form" method="POST" class="needs-validation" novalidate>
          <input type="hidden" name="case" value="UpdateGuru">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="id" id="editId">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Edit Data Guru/Karyawan</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <!-- Data Pekerjaan -->
              <h6 class="mb-2">Data Pekerjaan</h6>
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
                  <!-- Field Role pada Edit -->
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editRole">Role <span class="text-danger">*</span></label>
                          <select name="role" id="editRole" class="form-control" required>
                              <option value="">-- Pilih Role --</option>
                              <option value="P">Pendidik</option>
                              <option value="TK">Tenaga Kependidikan</option>
                              <option value="M">Manajerial</option>
                          </select>
                          <div class="invalid-feedback">Role wajib diisi.</div>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editJobTitle">Job Title</label>
                          <input type="text" name="job_title" id="editJobTitle" class="form-control">
                      </div>
                  </div>
              </div>

              <!-- Data Pribadi -->
              <h6 class="mt-4 mb-2">Data Pribadi</h6>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editJK">Jenis Kelamin</label>
                          <select name="jk" id="editJK" class="form-control">
                              <option value="">-- Pilih Jenis Kelamin --</option>
                              <option value="L">Laki-laki</option>
                              <option value="P">Perempuan</option>
                          </select>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editTglLahir">Tanggal Lahir</label>
                          <input type="date" name="tgl_lahir" id="editTglLahir" class="form-control">
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editUsia">Usia</label>
                          <input type="number" name="usia" id="editUsia" class="form-control">
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editReligion">Agama</label>
                          <input type="text" name="religion" id="editReligion" class="form-control">
                      </div>
                  </div>
              </div>

              <!-- Data Kontak -->
              <h6 class="mt-4 mb-2">Data Kontak</h6>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editAlamatDomisili">Alamat Domisili</label>
                          <textarea name="alamat_domisili" id="editAlamatDomisili" class="form-control"></textarea>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editAlamatKTP">Alamat KTP</label>
                          <textarea name="alamat_ktp" id="editAlamatKTP" class="form-control"></textarea>
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editNoRekening">No Rekening</label>
                          <input type="text" name="no_rekening" id="editNoRekening" class="form-control">
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editNoHP">No HP</label>
                          <input type="text" name="no_hp" id="editNoHP" class="form-control">
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editEmail">Email</label>
                          <input type="email" name="email" id="editEmail" class="form-control">
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editNamaSuami">Nama Suami</label>
                          <input type="text" name="nama_suami" id="editNamaSuami" class="form-control">
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label for="editJumlahAnak">Jumlah Anak</label>
                          <input type="number" name="jumlah_anak" id="editJumlahAnak" class="form-control">
                      </div>
                  </div>
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
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-primary">
                  <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                  Update
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: View Detail Guru/Karyawan -->
    <div class="modal fade" id="modalView" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Detail Data Guru/Karyawan</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body" style="color: #000">
            <h6>Data Pekerjaan</h6>
            <table class="table table-sm">
              <tr><th>NIP</th><td id="detailNip"></td></tr>
              <tr><th>UID</th><td id="detailUid"></td></tr>
              <tr><th>Nama</th><td id="detailNama"></td></tr>
              <tr><th>Jenjang</th><td id="detailJenjang"></td></tr>
              <tr><th>Job Title</th><td id="detailJobTitle"></td></tr>
              <tr><th>Role</th><td id="detailRole"></td></tr>
              <tr><th>Status Kerja</th><td id="detailStatusKerja"></td></tr>
              <tr><th>Masa Kerja</th><td id="detailMasaKerja"></td></tr>
              <tr><th>Pendidikan</th><td id="detailPendidikan"></td></tr>
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
              <tr><th>Nama Suami</th><td id="detailNamaSuami"></td></tr>
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

    <!-- MODAL: Delete Guru/Karyawan -->
    <div class="modal fade" id="modalDelete" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form id="delete-guru-form" class="modal-content">
          <input type="hidden" name="case" value="DeleteGuru">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="id" id="delId">
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
                  <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
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
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
          <div class="modal-header">
            <h5 class="modal-title" id="modalGajiPokokLabel">Atur Gaji Pokok</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
            <hr>
            <div class="form-group text-center">
                <button type="button" class="btn btn-secondary me-2" data-bs-toggle="modal" data-bs-target="#modalGajiStrataGuru">
                    <i class="fas fa-chart-bar"></i> Atur Gaji Strata Guru
                </button>
                <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#modalGajiStrataKaryawan">
                    <i class="fas fa-chart-bar"></i> Atur Gaji Strata Karyawan
                </button>
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
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
          <div class="modal-header">
            <h5 class="modal-title" id="modalGajiStrataGuruLabel">Atur Gaji Pokok Berdasarkan Strata Pendidikan (Guru)</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <div class="table-responsive">
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
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
          <div class="modal-header">
            <h5 class="modal-title" id="modalGajiStrataKaryawanLabel">Atur Gaji Pokok Berdasarkan Strata Pendidikan (Karyawan)</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <div class="table-responsive">
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

    <!-- MODAL: Tunjangan -->
    <div class="modal fade" id="modalTunjangan" tabindex="-1" aria-labelledby="modalTunjanganLabel" aria-hidden="true">
      <div class="modal-dialog modal-md modal-dialog-scrollable">
        <form id="tunjangan-form" method="POST" class="modal-content">
          <input type="hidden" name="case" value="update_tunjangan">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
          <div class="modal-header">
            <h5 class="modal-title" id="modalTunjanganLabel">Atur Tunjangan</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label for="tunjanganStruktural">Tunjangan Struktural (Rp)</label>
              <input type="number" step="0.01" name="tunjangan_struktural" id="tunjanganStruktural" class="form-control" value="1000000" required>
            </div>
            <div class="form-group">
              <label for="tunjanganWaliKelas">Tunjangan Wali Kelas / Strata / Profesi / Pindah Jenjang (Rp)</label>
              <input type="number" step="0.01" name="tunjangan_walikelas" id="tunjanganWaliKelas" class="form-control" value="500000" required>
            </div>
            <div class="form-group">
              <label for="tunjanganKehadiran">Tunjangan Kehadiran Fix (Rp)</label>
              <input type="number" step="0.01" name="tunjangan_kehadiran" id="tunjanganKehadiran" class="form-control" value="250000" required>
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

    <!-- MODAL: Assign Payheads -->
    <div class="modal fade" id="ManageModal" tabindex="-1" aria-labelledby="manageModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h4 class="modal-title text-center" id="manageModalLabel">Tetapkan Payheads ke Karyawan</h4>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="assign-payhead-form" enctype="multipart/form-data">
            <div class="modal-body d-flex" style="gap:15px;">
              <!-- Hidden Inputs -->
              <input type="hidden" name="case" value="AssignPayheadsToEmployee">
              <input type="hidden" name="empcode" id="empcode">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
              
              <!-- Bagian kiri: Payheads Tersedia -->
              <div style="flex:1;">
                <label><strong>Payheads Tersedia:</strong></label>
                <input type="text" id="searchAllPayheads" class="form-control mb-2" placeholder="Cari Payheads Tersedia...">
                <button type="button" id="selectHeads" class="btn btn-success btn-sm mb-2">
                  <i class="fa fa-arrow-circle-right"></i> Tetapkan
                </button>
                <select id="all_payheads" class="form-control" multiple size="10"></select>
              </div>
              
              <!-- Bagian Tengah: Payheads Terpilih -->
              <div style="flex:1;">
                <label><strong>Payheads Terpilih:</strong></label>
                <input type="text" id="searchSelectedPayheads" class="form-control mb-2" placeholder="Cari Payheads Terpilih...">
                <button type="button" id="removeHeads" class="btn btn-danger btn-sm mb-2">
                  <i class="fa fa-arrow-circle-left"></i> Hapus
                </button>
                <select id="selected_payheads" class="form-control" multiple size="10"></select>
              </div>
              
              <!-- Bagian Kanan: Input Amount dan Keterangan Perubahan -->
              <div style="flex:1;">
                <label><strong>Tetapkan Jumlah & Keterangan:</strong></label>
                <div id="selected_payamount"></div>
                <div class="form-group mt-2">
                  <label for="change_reason"><small>Alasan Perubahan:</small></label>
                  <textarea name="change_reason" id="change_reason" class="form-control" placeholder="Masukkan alasan perubahan (misalnya, promosi, penyesuaian tunjangan)"></textarea>
                </div>
                <div class="form-group mt-2">
                  <label for="supporting_document"><small>Upload Dokumen Pendukung:</small></label>
                  <input type="file" name="supporting_document" id="supporting_document" class="form-control-file" accept=".pdf,.jpg,.png">
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-check-circle"></i> Tetapkan Payheads
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
              </button>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times-circle"></i> Batal
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- MODAL: Select Month -->
    <div class="modal fade" id="SalaryMonthModal" tabindex="-1" aria-labelledby="salaryMonthModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-md" style="max-width: 600px;">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="salaryMonthModalLabel">
              <i class="fa fa-calendar"></i> Pilih Bulan untuk Payroll
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row text-center">
              <?php
              // Hitung periode: 16 bulan (2 bulan sebelumnya hingga 14 bulan ke depan)
              $months = [];
              $years  = [];
              $currentYear  = date('Y');
              $currentMonth = date('n');
              $startMonth = $currentMonth - 2;
              $startYear = $currentYear;
              for ($i = 0; $i < 16; $i++) {
                  $month = $startMonth + $i;
                  $year = $startYear;
                  if ($month <= 0) {
                      $month += 12;
                      $year -= 1;
                  } elseif ($month > 12) {
                      $month -= 12;
                      $year += 1;
                  }
                  $months[] = $month;
                  $years[]  = $year;
              }
              function getIndonesianMonthName($month) {
                  $names = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
                  return isset($names[$month]) ? $names[$month] : '';
              }
              for ($i = 0; $i < count($months); $i++) {
                  $m = getIndonesianMonthName($months[$i]);
                  $y = $years[$i];
                  $highlightClass = ($months[$i] == $currentMonth && $y == $currentYear) ? 'bg-warning fw-bold' : '';
                  echo '<div class="col-sm-3 mb-3">';
                  echo '  <div class="' . $highlightClass . '" style="padding:10px; border:1px solid #ddd; border-radius:5px;">';
                  echo '    <a href="#" class="month-link" data-month="' . $months[$i] . '" data-year="' . $y . '" style="color:#333; text-decoration:none;">';
                  echo strtoupper($m) . '<br>' . $y;
                  echo '    </a>';
                  echo '  </div>';
                  echo '</div>';
              }
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- JavaScript Dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/startbootstrap-sb-admin-2/4.1.4/js/sb-admin-2.min.js" nonce="<?php echo $nonce; ?>"></script>
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

        function getStatusBadge(status) {
            let s = (status || '').toLowerCase();
            if (s === 'tetap') {
                return '<span class="badge bg-success">Tetap</span>';
            } else if (s === 'kontrak') {
                return '<span class="badge bg-secondary">Kontrak</span>';
            } else {
                return '<span class="badge bg-secondary">' + (status || '') + '</span>';
            }
        }

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
                { data: "role" },
                { data: "status_kerja" },
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

        // Form create
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

        // View detail
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
                        $('#detailNip').text(response.result.nip);
                        $('#detailUid').text(response.result.uid);
                        $('#detailNama').text(response.result.nama);
                        $('#detailJenjang').text(response.result.jenjang);
                        $('#detailJobTitle').text(response.result.job_title);
                        $('#detailRole').text(response.result.role);
                        $('#detailStatusKerja').html(getStatusBadge(response.result.status_kerja));
                        $('#detailMasaKerja').text(response.result.masa_kerja);
                        $('#detailPendidikan').text(response.result.pendidikan);

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
                        $('#detailNamaSuami').text(response.result.nama_suami || '');
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
                        $('#editJenjang').val((response.result.jenjang || '').toUpperCase());
                        $('#editJobTitle').val(response.result.job_title || '');
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
                        $('#editNamaSuami').val(response.result.nama_suami || '');
                        $('#editJumlahAnak').val(response.result.jumlah_anak || 0);
                        $('#editNamaAnak1').val(response.result.nama_anak_1 || '');
                        $('#editNamaAnak2').val(response.result.nama_anak_2 || '');
                        $('#editNamaAnak3').val(response.result.nama_anak_3 || '');
                        $('#editSalaryIndex').val(response.result.salary_index_id || 0);
                        $('#editRole').val(response.result.role || '');
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
            $('#delNama').text($(this).closest('tr').find('td:eq(3)').text());
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
                data: { case: 'DeleteGuru', id: id, csrf_token: csrfToken },
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
                        guruTable.ajax.reload(null, false);
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

        // Update
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

        // Assign Payheads (Fitur Tambahan)
        $(document).on('click', '.btn-assign', function() {
            $('#assign-payhead-form').on('submit', function(e){
                e.preventDefault();
                var form = $(this);
                var formData = new FormData(this);
                var isValid = true;
                $('#selected_payheads option').each(function() {
                    var payheadId = $(this).val();
                    var inputSelector = 'input[name="pay_amounts[' + payheadId + ']"]';
                    var amount = $(inputSelector).val();
                    if(amount === '' || parseFloat(amount) < 0){
                        $(inputSelector).addClass('is-invalid');
                        isValid = false;
                    } else {
                        $(inputSelector).removeClass('is-invalid');
                    }
                });
                if(!isValid){
                    showToast('Pastikan semua jumlah payhead valid (>=0)!', 'error');
                    return;
                }
                $.ajax({
                    url:'manage_guru_karyawan.php?ajax=1',
                    type:'POST',
                    data: formData,
                    dataType:'json',
                    processData: false,
                    contentType: false,
                    beforeSend:function(){
                        form.find('button[type="submit"]').prop('disabled', true);
                        form.find('.spinner-border').removeClass('d-none');
                    },
                    success:function(resp){
                        form.find('button[type="submit"]').prop('disabled', false);
                        form.find('.spinner-border').addClass('d-none');
                        if(resp.code === 0){
                            showToast(resp.result, 'success');
                            // Jika tabel karyawan di-refresh (misalnya, jika menggunakan variabel empTable)
                            guruTable.ajax.reload(null, false);
                            setTimeout(function(){
                                $('#ManageModal').modal('hide');
                                form[0].reset();
                                $('#all_payheads').empty();
                                $('#selected_payheads').empty();
                                $('#selected_payamount').empty();
                            }, 200);
                        } else {
                            showToast(resp.result, 'error');
                        }
                    },
                    error:function(){
                        form.find('button[type="submit"]').prop('disabled', false);
                        form.find('.spinner-border').addClass('d-none');
                        showToast('Terjadi kesalahan saat menetapkan payheads.', 'error');
                    }
                });
            });
            $('#ManageModal').modal('show');
        });

        // Select Month untuk payroll (misalnya, pada event tombol "Select Month")
        $('#guruTable tbody').on('click', '.btn-select-month', function() {
            var employeeId = $(this).data('id');
            window.currentEmpId = employeeId;
            $('#SalaryMonthModal').modal('show');
        });
        $(document).on('click', '.month-link', function(e) {
            e.preventDefault();
            var month = $(this).data('month'); // nomor bulan (1-12)
            var year  = $(this).data('year');
            var employeeId = window.currentEmpId || 0;
            if (employeeId === 0) {
                showToast('ID Karyawan tidak valid!', 'error');
                return;
            }
            var targetUrl = "/payroll_absensi_v2/sdm/manage-payroll-review.php";
            targetUrl += "?id_anggota=" + employeeId;
            targetUrl += "&bulan=" + encodeURIComponent(month);
            targetUrl += "&tahun=" + encodeURIComponent(year);
            window.location.href = targetUrl;
        });
    });
    </script>
</body>
</html>
