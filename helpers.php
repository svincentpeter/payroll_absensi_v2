<?php
// File: helpers.php

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

function authorize($allowedRoles, $redirectUrl = null) {
    // Pastikan session sudah berjalan
    start_session_safe();
    
    $userRole     = $_SESSION['role'] ?? '';
    $userJobTitle = $_SESSION['job_title'] ?? '';

    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    $allowed = false;

    foreach ($allowedRoles as $allowedRole) {
        // 1) Cek jika allowedRole sama dengan userRole
        if ($allowedRole === $userRole) {
            $allowed = true;
            break;
        }

        // 2) Cek format "M:xxx"
        if ($userRole === 'M' && strpos($allowedRole, 'M:') === 0) {
            // Ambil detail setelah "M:"
            $allowedDetail = trim(substr($allowedRole, 2)); 
            // Normalisasi
            $allowedDetailNormalized = strtolower(str_replace('_', ' ', $allowedDetail));
            $userJobTitleNormalized = strtolower(str_replace('_', ' ', trim($userJobTitle)));

            if ($allowedDetailNormalized === $userJobTitleNormalized) {
                $allowed = true;
                break;
            }
        }

        // 3) Jika allowedRole hanya "M" => semua user role M diizinkan
        if ($allowedRole === 'M' && $userRole === 'M') {
            $allowed = true;
            break;
        }
    }

    // 4) Redirect jika tidak diizinkan
    if (!$allowed) {
        if ($redirectUrl === null) {
            $redirectUrl = getBaseUrl() . '/login.php';
        }
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

    // Cek apakah file foto profil ada di folder uploads
    if (file_exists($filePath)) {
        return getBaseUrl() . '/uploads/profile_pics/' . $fileName;
    } else {
        return getBaseUrl() . '/assets/img/undraw_profile.svg';
    }
}

/**
 * Menghitung dan mengupdate salary_index_id, gaji_pokok, serta masa_kerja_efektif untuk user.
 *
 * Fungsi ini:
 *  - Mengambil masa kerja (tahun dan bulan) dari anggota_sekolah.
 *  - Mengecek jumlah SP di tabel laporan_surat untuk user tersebut.
 *  - Menghitung masa kerja efektif = (masa kerja aktual) - penalty (1 tahun jika ada SP).
 *  - Menyimpan nilai masa_kerja_efektif ke kolom yang bersesuaian.
 *  - Menggunakan nilai floor dari masa kerja efektif untuk mencari salary index yang sesuai.
 *  - Mengupdate kolom salary_index_id, gaji_pokok, dan masa_kerja_efektif.
 *
 * @param mysqli $conn Koneksi database.
 * @param int $userId ID user di tabel anggota_sekolah.
 * @return bool True jika update berhasil, false jika gagal.
 */
function updateSalaryIndexForUser($conn, $userId) {
    // Ambil join_start beserta masa kerja
    $stmt = $conn->prepare("SELECT join_start, masa_kerja_tahun, masa_kerja_bulan 
                            FROM anggota_sekolah 
                            WHERE id = ?");
    if (!$stmt) {
        error_log("Prepare error (masa kerja): " . $conn->error);
        return false;
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($joinStart, $tahun, $bulan);
    if (!$stmt->fetch()) {
        $stmt->close();
        return false; // User tidak ditemukan
    }
    $stmt->close();
    
    // Gunakan join_start untuk menghitung masa kerja aktual
    try {
        $startDate = new DateTime($joinStart);
        $now       = new DateTime();
        // Jika tanggal bergabung di masa depan, set masa kerja aktual = 0
        if ($startDate > $now) {
            $masaKerjaAktual = 0;
        } else {
            $diff = $now->diff($startDate);
            $masaKerjaAktual = $diff->y + ($diff->m / 12.0);
        }
    } catch (\Exception $e) {
        $masaKerjaAktual = 0;
    }
    
    // Cek apakah ada SP untuk user tersebut
    $stmt2 = $conn->prepare("SELECT COUNT(*) as spCount 
                             FROM laporan_surat 
                             WHERE id_penerima = ? 
                               AND jenis_surat = 'peringatan'");
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
    
    // Penalty: jika ada SP (minimal 1), penalty 1 tahun; jika tidak, penalty 0.
    $penalty = ($spCount > 0) ? 1 : 0;
    
    // Hitung masa kerja efektif
    $masaKerjaEfektif = $masaKerjaAktual - $penalty;
    if ($masaKerjaEfektif < 0) {
        $masaKerjaEfektif = 0;
    }
    
    // Untuk pencarian salary index, gunakan effectiveYearsForIndex = floor(masaKerjaEfektif)
    $effectiveYearsForIndex = floor($masaKerjaEfektif);
    error_log("DEBUG MasaKerja: userId=$userId, masaKerjaAktual=$masaKerjaAktual, penalty=$penalty, effectiveYears=$effectiveYearsForIndex");

    // Cari salary index yang sesuai dengan masa kerja efektif
    $stmt3 = $conn->prepare("SELECT id, base_salary 
                             FROM salary_indices 
                             WHERE min_years <= ? 
                               AND (max_years IS NULL OR ? <= max_years)
                             ORDER BY min_years DESC
                             LIMIT 1");
    if (!$stmt3) {
        error_log("Prepare error (salary_indices): " . $conn->error);
        return false;
    }
    $stmt3->bind_param("ii", $effectiveYearsForIndex, $effectiveYearsForIndex);
    $stmt3->execute();
    $stmt3->bind_result($salaryIndexId, $baseSalary);
    if (!$stmt3->fetch()) {
        $stmt3->close();
        error_log("Tidak ada salary index yang cocok untuk effectiveYears = $effectiveYearsForIndex");
        return false;
    }
    $stmt3->close();
    
    // Update data anggota_sekolah dengan salary_index_id, gaji_pokok, dan masa_kerja_efektif
    $stmt4 = $conn->prepare("UPDATE anggota_sekolah 
                             SET salary_index_id = ?, 
                                 gaji_pokok = ?, 
                                 masa_kerja_efektif = ? 
                             WHERE id = ?");
    if (!$stmt4) {
        error_log("Prepare error (update anggota): " . $conn->error);
        return false;
    }
    $stmt4->bind_param("iddi", $salaryIndexId, $baseSalary, $masaKerjaEfektif, $userId);
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

/**
 * Menghitung perkiraan salary_index_id berdasarkan Tanggal Bergabung,
 * tanpa mempertimbangkan SP (karena ini baru perkiraan di form create).
 *
 * @param mysqli $conn
 * @param string $joinStart format YYYY-MM-DD
 * @return array berisi ['salary_index_id' => int, 'explanation' => string]
 */
function getRecommendedSalaryIndex($conn, $joinStart) {
    // Jika joinStart kosong atau '0000-00-00', langsung kembalikan 0
    if (empty($joinStart) || $joinStart == '0000-00-00') {
        return [
            'salary_index_id' => 0,
            'explanation' => 'Tanggal bergabung belum diisi / tidak valid'
        ];
    }

    // Hitung selisih tahun (dibulatkan ke bawah)
    try {
        $startDate = new DateTime($joinStart);
        $now       = new DateTime();
        
        // Jika join_start di masa depan, kita set masa kerja = 0
        if ($startDate > $now) {
            $masaKerjaTahun = 0;
        } else {
            // normal
            $diff = $now->diff($startDate);
            $masaKerjaTahun = $diff->y;
        }
    } catch (\Exception $e) {
        return [
            'salary_index_id' => 0,
            'explanation' => 'Error parsing date: '.$e->getMessage()
        ];
    }

    // Cari salary_index yang cocok
    $sql = "SELECT id, level 
            FROM salary_indices
            WHERE min_years <= ?
              AND (max_years IS NULL OR ? <= max_years)
            ORDER BY min_years DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [
            'salary_index_id' => 0,
            'explanation' => 'Query error: '.$conn->error
        ];
    }
    $stmt->bind_param("ii", $masaKerjaTahun, $masaKerjaTahun);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        return [
            'salary_index_id' => (int)$row['id'],
            'explanation' => 'Cocok dengan level: '.$row['level']
        ];
    } else {
        // kalau tidak ada, kembalikan 0
        return [
            'salary_index_id' => 0,
            'explanation' => 'Tidak ada level salary_indices yang cocok'
        ];
    }
}

/**
 * Menerjemahkan nama bulan dari bahasa Inggris ke Bahasa Indonesia.
 *
 * Fungsi ini berguna untuk mengonversi string nama bulan (misal "January")
 * ke dalam format Bahasa Indonesia (misal "Januari").
 */
if (!function_exists('translate_month_dashboard')) {
    function translate_month_dashboard($month_eng) {
        $months = [
            'January'   => 'Januari',
            'February'  => 'Februari',
            'March'     => 'Maret',
            'April'     => 'April',
            'May'       => 'Mei',
            'June'      => 'Juni',
            'July'      => 'Juli',
            'August'    => 'Agustus',
            'September' => 'September',
            'October'   => 'Oktober',
            'November'  => 'November',
            'December'  => 'Desember'
        ];
        return $months[$month_eng] ?? $month_eng;
    }
}

/**
 * Menerjemahkan nama hari dari bahasa Inggris ke Bahasa Indonesia.
 *
 * Fungsi ini mengonversi singkatan hari (misal "Mon") ke dalam format Bahasa Indonesia (misal "Senin").
 */
if (!function_exists('translate_day_dashboard')) {
    function translate_day_dashboard($day_eng) {
        $days = [
            'Mon' => 'Senin',
            'Tue' => 'Selasa',
            'Wed' => 'Rabu',
            'Thu' => 'Kamis',
            'Fri' => 'Jumat',
            'Sat' => 'Sabtu',
            'Sun' => 'Minggu'
        ];
        return $days[$day_eng] ?? $day_eng;
    }
}

// Alias agar fungsi mudah dipanggil di seluruh aplikasi
if (!function_exists('translate_month')) {
    function translate_month($month_eng) {
        return translate_month_dashboard($month_eng);
    }
}

if (!function_exists('translate_day')) {
    function translate_day($day_eng) {
        return translate_day_dashboard($day_eng);
    }
}

/**
 * Mendapatkan daftar jenjang (fixed) dengan urutan tertentu
 * 
 * @return array
 */
function getOrderedJenjang(): array {
    return [
        'TK',
        'SD',
        'SMP',
        'SMA',
        'SMK 1',
        'SMK 2',
        'STIFERA'
    ];
}

/**
 * Fungsi untuk mendapatkan base URL secara dinamis.
 * Jika aplikasi berada di subfolder, tentukan path subfolder di sini.
 * Contoh: '/payroll_absensi_v2' atau '/payroll_ujicoba_2'.
 */
if (!function_exists('getBaseUrl')) {
    function getBaseUrl() {
        // Deteksi protokol
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
                     || $_SERVER['SERVER_PORT'] == 443)
                    ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];

        // Ganti ini dengan folder subdirektori aplikasi Anda:
        // Misal: '/payroll_absensi_v2' jika memang foldernya bernama payroll_absensi_v2
        $subfolder = '/payroll_absensi_v2';

        return $protocol . $host . $subfolder;
    }
}
