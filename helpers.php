<?php
// File: helpers.php

// Definisikan BASE_URL untuk digunakan di seluruh aplikasi
define('BASE_URL', '/payroll_absensi_v2');

/**
 * Memulai session dengan aman jika belum dimulai.
 *
 * Fungsi ini memastikan bahwa session sudah berjalan. Jika belum,
 * maka akan memulai session baru. Hal ini penting agar data seperti CSRF token
 * atau data user tersimpan dengan benar.
 */
function start_session_safe() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Membersihkan input dari karakter yang tidak diinginkan untuk mencegah XSS.
 *
 * Fungsi ini menghapus spasi ekstra dan mengkonversi karakter khusus
 * menjadi entitas HTML sehingga mencegah serangan Cross-Site Scripting.
 *
 * @param string $data Input yang akan dibersihkan.
 * @return string Input yang sudah dibersihkan.
 */
function sanitize_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Alias untuk fungsi bersihkan_input()
if (!function_exists('bersihkan_input')) {
    function bersihkan_input($data) {
        return sanitize_input($data);
    }
}

/**
 * Mengirim respons JSON dan mengakhiri eksekusi script.
 *
 * @param int $code Kode status (0 untuk sukses, >0 untuk error).
 * @param mixed $result Data atau pesan yang dikirim.
 */
function send_response($code, $result) {
    if ($code !== 0) {
        error_log("Response Code $code: " . json_encode($result));
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => $code, 'result' => $result]);
    exit();
}

/**
 * Menghasilkan CSRF token dan menyimpannya ke dalam session.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Memverifikasi CSRF token yang dikirimkan oleh klien.
 *
 * @param string $token Token yang dikirim dari klien.
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        send_response(403, 'Token CSRF tidak valid.');
    }
}

/**
 * Mencatat error ke dalam file log.
 *
 * @param string $message Pesan error yang akan dicatat.
 */
function log_error($message) {
    $error_log_path = __DIR__ . '/error.log';
    error_log("[" . date('Y-m-d H:i:s') . "] " . $message . "\n", 3, $error_log_path);
}

/**
 * Menambahkan catatan audit log ke database.
 *
 * @param mysqli $conn Koneksi database.
 * @param string $user_nip NIP user yang melakukan aksi.
 * @param string $action Jenis aksi.
 * @param string $details Detail tambahan.
 * @return bool True jika berhasil, false jika gagal.
 */
function add_audit_log($conn, $user_nip, $action, $details) {
    if (empty($user_nip)) {
        return true;
    }
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

    $stmt = $conn->prepare("INSERT INTO audit_logs (nip, action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    if (!$stmt) {
        log_error("Gagal menyiapkan statement untuk audit log: " . $conn->error);
        return false;
    }
    $stmt->bind_param("sssss", $user_nip, $action, $details, $ip_address, $user_agent);
    if (!$stmt->execute()) {
        log_error("Gagal menjalankan audit log: " . $stmt->error);
        $stmt->close();
        return false;
    }
    $stmt->close();
    return true;
}

/**
 * Menerjemahkan jenis payhead dari bahasa Inggris ke Indonesia.
 *
 * @param string $jenis Jenis payhead.
 * @return string Terjemahan jenis payhead.
 */
function translateJenis($jenis) {
    $translations = [
        'earnings'   => 'Pendapatan',
        'deductions' => 'Potongan'
    ];
    return $translations[$jenis] ?? 'Tidak Dikenal';
}

/**
 * Menginisialisasi konfigurasi error handling.
 */
function init_error_handling() {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error.log');
    error_reporting(E_ALL);
}

/**
 * Mengembalikan nama bulan dalam Bahasa Indonesia.
 *
 * @param int $monthNumber Nomor bulan (1-12).
 * @return string Nama bulan.
 */
function getIndonesianMonthName($monthNumber) {
    $monthNumber = intval($monthNumber);
    $bulan = [
        1  => 'Januari',
        2  => 'Februari',
        3  => 'Maret',
        4  => 'April',
        5  => 'Mei',
        6  => 'Juni',
        7  => 'Juli',
        8  => 'Agustus',
        9  => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];
    if (!isset($bulan[$monthNumber])) {
        error_log("Bulan tidak ditemukan untuk angka: " . json_encode($monthNumber));
    }
    return $bulan[$monthNumber] ?? 'Tidak Diketahui';
}

/**
 * Mengonversi nama bulan ke angka.
 *
 * @param string $monthName Nama bulan.
 * @return int Angka bulan.
 */
function monthNameToInt($monthName) {
    $lower = strtolower($monthName);
    $map = [
        'januari'   => 1,
        'februari'  => 2,
        'maret'     => 3,
        'april'     => 4,
        'mei'       => 5,
        'juni'      => 6,
        'juli'      => 7,
        'agustus'   => 8,
        'september' => 9,
        'oktober'   => 10,
        'november'  => 11,
        'desember'  => 12
    ];
    return isset($map[$lower]) ? $map[$lower] : 0;
}

/**
 * Mengonversi nilai numerik ke format mata uang Rupiah.
 *
 * @param float|int $nominal Nilai numerik.
 * @return string Format mata uang.
 */
function formatNominal($nominal) {
    return 'Rp ' . number_format($nominal, 2, ',', '.');
}

/** 
 * Helper functions untuk menampilkan badge berwarna.
 */
function getBadgeRole($role) {
    switch ($role) {
        case 'P':
            return '<span class="badge bg-primary">Pendidik</span>';
        case 'TK':
            return '<span class="badge bg-info text-dark">Tenaga Kependidikan</span>';
        case 'M':
            return '<span class="badge bg-danger">Manajerial</span>';
        default:
            return '<span class="badge bg-secondary">' . htmlspecialchars($role) . '</span>';
    }
}

function getBadgeJenjang($jenjang) {
    switch ($jenjang) {
        case 'TK':
            return '<span class="badge bg-success">TK</span>';
        case 'SD':
            return '<span class="badge bg-primary">SD</span>';
        case 'SMP':
            return '<span class="badge bg-info text-dark">SMP</span>';
        case 'SMA':
            return '<span class="badge bg-warning text-dark">SMA</span>';
        case 'SMK':
            return '<span class="badge bg-secondary">SMK</span>';
        default:
            return '<span class="badge bg-light text-dark">' . htmlspecialchars($jenjang) . '</span>';
    }
}

function getBadgeStatusKerja($status) {
    if (strtolower($status) === 'tetap') {
        return '<span class="badge bg-success">Tetap</span>';
    } else if (strtolower($status) === 'kontrak') {
        return '<span class="badge bg-warning text-dark">Kontrak</span>';
    } else {
        return '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
}

/**
 * Memeriksa apakah pengguna memiliki akses berdasarkan role.
 *
 * @param string|array $allowedRoles Role yang diizinkan.
 * @param string $redirectUrl URL tujuan jika tidak memiliki akses.
 */
function authorize($allowedRoles, $redirectUrl = BASE_URL . '/login.php') {
    start_session_safe();
    $userRole = $_SESSION['role'] ?? '';
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    if (!in_array($userRole, $allowedRoles)) {
        header("Location: " . $redirectUrl);
        exit();
    }
}

/**
 * Mendapatkan URL foto profil berdasarkan nama, jenjang, role, dan ID.
 *
 * @param string $nama
 * @param string $jenjang
 * @param string $role
 * @param int $id
 * @return string URL foto profil.
 */
function getProfilePhotoUrl($nama, $jenjang, $role, $id) {
    $fileName = strtolower(preg_replace('/\s+/', '_', $nama))
              . '_' . strtolower(preg_replace('/\s+/', '_', $jenjang))
              . '_' . strtolower($role)
              . '_' . $id . '.jpg';
    $filePath = __DIR__ . '/uploads/profile_pics/' . $fileName;
    if (file_exists($filePath)) {
        return BASE_URL . '/uploads/profile_pics/' . $fileName;
    } else {
        return BASE_URL . '/assets/img/undraw_profile.svg';
    }
}

/**
 * Menghitung salary index untuk satu user berdasarkan masa kerja dan riwayat SP.
 *
 * Jika ada SP (surat peringatan) maka terdapat penalty 1 tahun.
 *
 * @param mysqli $conn Koneksi database.
 * @param int $userId ID user di tabel anggota_sekolah.
 * @return bool True jika update berhasil, false jika gagal.
 */
function updateSalaryIndexForUser($conn, $userId) {
    // Ambil masa kerja dari anggota_sekolah
    $stmt = $conn->prepare("SELECT masa_kerja_tahun, masa_kerja_bulan FROM anggota_sekolah WHERE id = ?");
    if (!$stmt) {
        error_log("Prepare error (masa kerja): " . $conn->error);
        return false;
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($tahun, $bulan);
    if (!$stmt->fetch()) {
        $stmt->close();
        return false; // User tidak ditemukan
    }
    $stmt->close();
    
    // Cek apakah ada SP untuk user tersebut
    $stmt2 = $conn->prepare("SELECT COUNT(*) as spCount FROM laporan_surat WHERE id_penerima = ? AND jenis_surat = 'peringatan'");
    if (!$stmt2) {
        error_log("Prepare error (SP): " . $conn->error);
        return false;
    }
    $stmt2->bind_param("i", $userId);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $row2 = $result2->fetch_assoc();
    $spCount = intval($row2['spCount'] ?? 0);
    $stmt2->close();
    
    // Jika ada SP, berikan penalty 1 tahun
    $penalty = ($spCount > 0) ? 1 : 0;
    // Hitung masa kerja efektif (dalam tahun, dibulatkan ke bawah)
    $effectiveYears = floor(($tahun + $bulan / 12) - $penalty);
    
    // Cari salary index yang sesuai dengan masa kerja efektif
    $stmt3 = $conn->prepare("SELECT id, base_salary FROM salary_indices 
                              WHERE min_years <= ? 
                                AND (max_years IS NULL OR ? <= max_years)
                              ORDER BY min_years DESC
                              LIMIT 1");
    if (!$stmt3) {
        error_log("Prepare error (salary_indices): " . $conn->error);
        return false;
    }
    $stmt3->bind_param("ii", $effectiveYears, $effectiveYears);
    $stmt3->execute();
    $stmt3->bind_result($salaryIndexId, $baseSalary);
    if (!$stmt3->fetch()) {
        $stmt3->close();
        error_log("Tidak ada salary index yang cocok untuk effectiveYears = $effectiveYears");
        return false;
    }
    $stmt3->close();
    
    // Update data anggota_sekolah dengan salary_index_id dan gaji_pokok baru
    $stmt4 = $conn->prepare("UPDATE anggota_sekolah SET salary_index_id = ?, gaji_pokok = ? WHERE id = ?");
    if (!$stmt4) {
        error_log("Prepare error (update anggota): " . $conn->error);
        return false;
    }
    $stmt4->bind_param("idi", $salaryIndexId, $baseSalary, $userId);
    $result = $stmt4->execute();
    if (!$result) {
        error_log("Execute error (update anggota): " . $stmt4->error);
    }
    $stmt4->close();
    return $result;
}

/**
 * Update salary index untuk semua anggota dengan role P atau TK.
 *
 * @param mysqli $conn Koneksi database.
 * @return bool True jika berhasil, false jika gagal.
 */
function updateSalaryIndexForAll($conn) {
    $sql = "SELECT id FROM anggota_sekolah WHERE role IN ('P', 'TK')";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        error_log("Query error (select all): " . mysqli_error($conn));
        return false;
    }
    while ($row = mysqli_fetch_assoc($result)) {
        $userId = intval($row['id']);
        updateSalaryIndexForUser($conn, $userId);
    }
    mysqli_free_result($result);
    return true;
}
?>
