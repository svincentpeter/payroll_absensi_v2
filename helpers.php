<?php
// File: /payroll_absensi_v2/helpers.php

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

// Tambahkan alias untuk fungsi bersihkan_input()
if (!function_exists('bersihkan_input')) {
    function bersihkan_input($data) {
        return sanitize_input($data);
    }
}

/**
 * Mengirim respons JSON dan mengakhiri eksekusi script.
 *
 * Fungsi ini digunakan untuk mengembalikan hasil (baik sukses maupun error)
 * dalam format JSON. Fungsi ini juga menghentikan eksekusi script setelah mengirim respons.
 *
 * @param int $code Kode status (0 untuk sukses, >0 untuk error).
 * @param mixed $result Data atau pesan yang dikirim.
 */
function send_response($code, $result) {
    if ($code !== 0) {
        // Log error jika kode tidak 0 (error)
        error_log("Response Code $code: " . json_encode($result));
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => $code, 'result' => $result]);
    exit();
}

/**
 * Menghasilkan CSRF token dan menyimpannya ke dalam session.
 *
 * Fungsi ini membuat token unik untuk melindungi form dari serangan CSRF.
 * Jika token belum ada di session, maka token baru akan dibuat.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Memverifikasi CSRF token yang dikirimkan oleh klien.
 *
 * Fungsi ini membandingkan token yang dikirim dari form dengan token yang tersimpan
 * di session. Jika token tidak cocok atau tidak ada, maka respons error akan dikirim.
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
 * Fungsi ini mencatat pesan error ke dalam file log yang terletak di folder yang sama.
 * Ini berguna untuk debugging dan audit kesalahan yang terjadi.
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
 * Fungsi ini mencatat aktivitas atau aksi penting yang dilakukan oleh user ke dalam tabel audit_logs.
 * Ini membantu dalam melacak perubahan dan menjaga keamanan sistem.
 *
 * @param mysqli $conn Koneksi database.
 * @param int $user_id ID pengguna yang melakukan aksi.
 * @param string $action Jenis aksi (misal: AddPayhead, EditPayhead).
 * @param string $details Detail tambahan tentang aksi.
 * @return bool True jika berhasil, false jika gagal.
 */
function add_audit_log($conn, $user_nip, $action, $details) {
    // Jika NIP kosong, lewati pencatatan
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
 * Fungsi ini digunakan untuk menampilkan jenis payhead dengan istilah bahasa Indonesia.
 *
 * @param string $jenis Jenis payhead (misal: 'earnings' atau 'deductions').
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
 *
 * Fungsi ini mengatur konfigurasi PHP agar error tidak ditampilkan langsung ke user,
 * melainkan dicatat di file log. Sangat berguna untuk lingkungan produksi.
 */
function init_error_handling() {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    // Pastikan direktori tempat error log berada memiliki permission yang sesuai
    ini_set('error_log', __DIR__ . '/error.log');
    error_reporting(E_ALL);
}

/**
 * Mengembalikan nama bulan dalam Bahasa Indonesia.
 *
 * Fungsi ini mengonversi angka bulan (1-12) menjadi nama bulan dalam bahasa Indonesia.
 *
 * @param int $monthNumber Nomor bulan (1-12).
 * @return string Nama bulan dalam Bahasa Indonesia.
 */
function getIndonesianMonthName($monthNumber) {
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
    return $bulan[$monthNumber] ?? '';
}

/**
 * Mengonversi nama bulan (dalam bahasa Indonesia) ke angka.
 *
 * Fungsi ini mengubah nama bulan (misalnya, "Januari") menjadi angka (misalnya, 1).
 *
 * @param string $monthName Nama bulan.
 * @return int Angka bulan (1-12) atau 0 jika tidak valid.
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
 * Fungsi ini memformat nilai numerik sehingga tampilannya sesuai dengan format mata uang
 * Indonesia, misalnya "Rp 1.234,56".
 *
 * @param float|int $nominal Nilai numerik yang akan diformat.
 * @return string Nilai yang sudah diformat.
 */
function formatNominal($nominal) {
    return 'Rp ' . number_format($nominal, 2, ',', '.');
}

/** 
 * Helper functions untuk menampilkan badge berwarna
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
 * Memeriksa apakah pengguna yang sedang login memiliki hak akses (role) yang diizinkan.
 *
 * @param string|array $allowedRoles Role yang diizinkan. Bisa berupa string atau array.
 * @param string $redirectUrl URL tujuan jika pengguna tidak memiliki akses.
 * @return void
 */
function authorize($allowedRoles, $redirectUrl = '/payroll_absensi_v2/login.php') {
    // Pastikan session sudah dimulai
    start_session_safe();
    
    // Ambil role dari session (sesuaikan key session jika berbeda)
    $userRole = $_SESSION['role'] ?? '';
    
    // Ubah allowedRoles menjadi array jika bukan array
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    // Jika role pengguna tidak termasuk dalam allowedRoles, redirect
    if (!in_array($userRole, $allowedRoles)) {
        header("Location: " . $redirectUrl);
        exit();
    }
}

?>
