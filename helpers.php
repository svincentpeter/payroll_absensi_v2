<?php
// File: helpers.php

/************************************
 * 1. SESSION & KEAMANAN
 ************************************/

/**
 * Memulai sesi PHP secara aman.
 * Cek status sesi, jika belum dimulai, maka panggil session_start().
 */
function start_session_safe()
{
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Membersihkan input dari karakter yang tidak diinginkan.
 * Menghilangkan spasi berlebih dan meng-encode karakter khusus untuk mencegah XSS.
 *
 * @param string $data Input data.
 * @return string Data yang telah disanitasi.
 */
function sanitize_input($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Alias untuk fungsi sanitize_input()
if (!function_exists('bersihkan_input')) {
    function bersihkan_input($data)
    {
        return sanitize_input($data);
    }
}

/**
 * Mengirim respons JSON dan mengakhiri eksekusi script.
 *
 * @param int   $code   Kode status.
 * @param mixed $result Data hasil response.
 */
function send_response($code, $result)
{
    // === HAPUS DEBUG ===
    // Jika diperlukan, aktifkan pencatatan error untuk non-0 code
    // if ($code !== 0) {
    //     error_log("Response Code $code: " . json_encode($result));
    // }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => $code, 'result' => $result]);
    exit();
}

/**
 * Menghasilkan token CSRF dan menyimpannya ke dalam session.
 * Token ini digunakan untuk mengamankan form dari serangan CSRF.
 */
function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Memverifikasi token CSRF yang diberikan dengan yang ada di session.
 *
 * @param string $token Token CSRF yang dikirimkan.
 */
function verify_csrf_token($token)
{
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        send_response(403, 'Token CSRF tidak valid.');
    }
}

/************************************
 * 2. LOGGING & AUDITING
 ************************************/

/**
 * Mencatat pesan error ke file log.
 *
 * @param string $message Pesan error yang akan dicatat.
 */
function log_error($message)
{
    $error_log_path = __DIR__ . '/error.log';
    error_log("[" . date('Y-m-d H:i:s') . "] " . $message . "\n", 3, $error_log_path);
}

/**
 * Menambahkan catatan audit log ke database.
 *
 * @param mysqli $conn       Koneksi database.
 * @param string $user_nip   Nomor induk pegawai (NIP).
 * @param string $action     Aksi yang dilakukan.
 * @param string $details    Rincian atau keterangan aksi.
 * @return bool True jika berhasil, false jika gagal.
 */
function add_audit_log($conn, $user_nip, $action, $details)
{
    if (empty($user_nip)) {
        return true;
    }
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

    $stmt = $conn->prepare("INSERT INTO audit_logs (nip, action, details, ip_address, user_agent, created_at)
                            VALUES (?, ?, ?, ?, ?, NOW())");
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
 * Inisialisasi penanganan error.
 * Menonaktifkan display error di browser dan mengaktifkan logging error.
 */
function init_error_handling()
{
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error.log');
    error_reporting(E_ALL);
}

/************************************
 * 3. FORMAT & UTILITY TEKS/ANGKA
 ************************************/

/**
 * Mengubah angka menjadi format nominal Rupiah.
 *
 * @param float $nominal Angka nominal.
 * @return string Format Rupiah, misalnya "Rp 1.234,56".
 */
function formatNominal($nominal)
{
    return 'Rp ' . number_format($nominal, 2, ',', '.');
}

/**
 * Menerjemahkan jenis ke dalam bahasa Indonesia.
 *
 * @param string $jenis 'earnings' atau 'deductions'.
 * @return string 'Pendapatan' atau 'Potongan'.
 */
function translateJenis($jenis)
{
    $translations = [
        'earnings'   => 'Pendapatan',
        'deductions' => 'Potongan'
    ];
    return $translations[$jenis] ?? 'Tidak Dikenal';
}

/**
 * Mengubah nama bulan angka (1-12) menjadi nama bulan dalam bahasa Indonesia.
 *
 * @param int $monthNumber Nomor bulan.
 * @return string Nama bulan atau 'Tidak Diketahui' jika tidak valid.
 */
function getIndonesianMonthName($monthNumber)
{
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
    // === HAPUS DEBUG ===
    // Jika nomor bulan tidak ditemukan, bisa dicatat log.
    return $bulan[$monthNumber] ?? 'Tidak Diketahui';
}

/**
 * Mengubah nama bulan dalam bahasa Indonesia menjadi angka (1-12).
 *
 * @param string $monthName Nama bulan.
 * @return int Nomor bulan atau 0 jika tidak valid.
 */
function monthNameToInt($monthName)
{
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
    return $map[$lower] ?? 0;
}

/**
 * Menerjemahkan nama bulan berbahasa Inggris ke bahasa Indonesia untuk dashboard.
 *
 * @param string $month_eng Nama bulan dalam bahasa Inggris.
 * @return string Nama bulan dalam bahasa Indonesia.
 */
if (!function_exists('translate_month_dashboard')) {
    function translate_month_dashboard($month_eng)
    {
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
 * Alias untuk translate_month_dashboard.
 *
 * @param string $month_eng Nama bulan dalam bahasa Inggris.
 * @return string Nama bulan dalam bahasa Indonesia.
 */
if (!function_exists('translate_month')) {
    function translate_month($month_eng)
    {
        return translate_month_dashboard($month_eng);
    }
}

/**
 * Menerjemahkan nama hari singkat berbahasa Inggris ke bahasa Indonesia untuk dashboard.
 *
 * @param string $day_eng Nama hari singkat (contoh: Mon, Tue).
 * @return string Nama hari dalam bahasa Indonesia.
 */
if (!function_exists('translate_day_dashboard')) {
    function translate_day_dashboard($day_eng)
    {
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

/**
 * Alias untuk translate_day_dashboard.
 *
 * @param string $day_eng Nama hari singkat.
 * @return string Nama hari dalam bahasa Indonesia.
 */
if (!function_exists('translate_day')) {
    function translate_day($day_eng)
    {
        return translate_day_dashboard($day_eng);
    }
}

/************************************
 * 4. BADGE / LABEL UTILITY
 ************************************/

/**
 * Menghasilkan badge HTML berdasarkan peran user.
 *
 * @param string $role Kode peran (misal: 'P', 'TK', 'M').
 * @return string HTML badge.
 */
function getBadgeRole($role)
{
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

/**
 * Menghasilkan badge HTML berdasarkan jenjang pendidikan.
 *
 * @param string $jenjang Jenjang pendidikan.
 * @return string HTML badge.
 */
function getBadgeJenjang($jenjang)
{
    // Normalisasi input dengan uppercase
    $jenjangUpper = strtoupper(trim($jenjang));

    switch ($jenjangUpper) {
        case 'TK':
            return '<span class="badge bg-success">TK</span>';
        case 'SD':
            return '<span class="badge bg-primary">SD</span>';
        case 'SMP':
            return '<span class="badge bg-info text-dark">SMP</span>';
        case 'SMA':
            return '<span class="badge bg-warning text-dark">SMA</span>';
        case 'SMK':
        case 'SMK 1':
        case 'SMK 2':
            return '<span class="badge bg-secondary">SMK</span>';
        case 'UNIVERSITAS STIVERA':
        case 'STIFERA':
            return '<span class="badge bg-dark">Universitas Stivera</span>';
        default:
            return '<span class="badge bg-light text-dark">' . htmlspecialchars($jenjang) . '</span>';
    }
}

/**
 * Menghasilkan badge HTML untuk status kerja karyawan.
 *
 * @param string $status Status kerja (misal: 'tetap' atau 'kontrak').
 * @return string HTML badge.
 */
function getBadgeStatusKerja($status)
{
    if (strtolower($status) === 'tetap') {
        return '<span class="badge bg-success">Tetap</span>';
    } else if (strtolower($status) === 'kontrak') {
        return '<span class="badge bg-warning text-dark">Kontrak</span>';
    } else {
        return '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
}

/************************************
 * 5. OTORISASI & AKSES
 ************************************/

/**
 * Mengecek apakah user memiliki hak akses untuk halaman tertentu.
 * Jika tidak memenuhi, user akan dialihkan ke URL yang ditentukan (default: login.php).
 *
 * @param mixed  $allowedRoles Array atau string peran yang diizinkan.
 * @param string $redirectUrl  URL untuk redirect jika akses ditolak.
 */
function authorize($allowedRoles, $redirectUrl = null)
{
    start_session_safe();

    // Ambil role dan job title dari session
    $userRole     = $_SESSION['role'] ?? '';
    $userJobTitle = $_SESSION['job_title'] ?? '';

    // Override role jika non_admin_mode aktif: anggap sebagai TK (guru/karyawan)
    if (!empty($_SESSION['non_admin_mode']) && $_SESSION['non_admin_mode'] === true) {
        $userRole = 'TK';
    }

    // Pastikan parameter allowedRoles merupakan array
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    $allowed = false;
    foreach ($allowedRoles as $allowedRole) {
        // Jika parameter authorize adalah 'kepala sekolah', periksa job title
        if ($allowedRole === 'kepala sekolah') {
            if (stripos($userJobTitle, 'kepala sekolah') !== false) {
                $allowed = true;
                break;
            }
        }
        // Jika role langsung cocok, diizinkan
        if ($allowedRole === $userRole) {
            $allowed = true;
            break;
        }
        // Penanganan khusus untuk role manajerial (M) dengan detail (contoh: 'M:sdm')
        if ($userRole === 'M' && strpos($allowedRole, 'M:') === 0) {
            $allowedDetail = trim(substr($allowedRole, 2));
            $allowedDetailNormalized = strtolower(str_replace('_', ' ', $allowedDetail));
            $userJobTitleNormalized = strtolower(str_replace('_', ' ', trim($userJobTitle)));
            if (strpos($userJobTitleNormalized, $allowedDetailNormalized) !== false) {
                $allowed = true;
                break;
            }
        }
        // Jika allowedRole adalah 'M' dan user memiliki role 'M'
        if ($allowedRole === 'M' && $userRole === 'M') {
            $allowed = true;
            break;
        }
    }

    if (!$allowed) {
        if ($redirectUrl === null) {
            $redirectUrl = getBaseUrl() . '/login.php';
        }
        header("Location: " . $redirectUrl);
        exit();
    }
}


/**
 * Menghasilkan URL dasar aplikasi.
 * Menggabungkan protocol, host, dan subfolder aplikasi.
 *
 * @return string URL dasar aplikasi.
 */
if (!function_exists('getBaseUrl')) {
    function getBaseUrl()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
            || $_SERVER['SERVER_PORT'] == 443)
            ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $subfolder = '/payroll_absensi_v2';
        return $protocol . $host . $subfolder;
    }
}

/**
 * Menentukan halaman dashboard yang sesuai berdasarkan role dan job title user.
 *
 * @param string $role     Kode role user.
 * @param string $jobTitle Jabatan atau title user.
 * @return mixed Route dashboard atau false jika tidak cocok.
 */
function getDashboardRoute($role, $jobTitle)
{
    // Jika mode non-admin aktif, override dan kembalikan dashboard_guru.php
    if (!empty($_SESSION['non_admin_mode']) && $_SESSION['non_admin_mode'] === true) {
        return "guru/dashboard_guru.php";
    }

    $jobTitleLower = strtolower($jobTitle);
    if ($role === 'M') {
        if (strpos($jobTitleLower, 'superadmin') !== false) {
            return "superadmin/dashboard_superadmin.php";
        } elseif (strpos($jobTitleLower, 'sdm') !== false) {
            return "sdm/dashboard_sdm.php";
        } elseif (strpos($jobTitleLower, 'keuangan') !== false) {
            return "keuangan/dashboard_keuangan.php";
        }
    } elseif ($role === 'P' || $role === 'TK') {
        // Untuk role P dan TK, jika job_title mengandung "kepala sekolah", tetap arahkan ke guru/dashboard_guru.php
        if (strpos($jobTitleLower, 'kepala sekolah') !== false) {
            return "guru/dashboard_guru.php";
        } else {
            return "guru/dashboard_guru.php";
        }
    }
    return false;
}


/************************************
 * 6. PROFIL & UPLOAD
 ************************************/

/**
 * Menghasilkan URL foto profil berdasarkan nama, jenjang, role, dan ID user.
 * Jika foto tidak ditemukan, mengembalikan URL gambar default.
 *
 * @param string $nama   Nama user.
 * @param string $jenjang Jenjang pendidikan.
 * @param string $role    Role user.
 * @param int    $id      ID user.
 * @return string URL foto profil.
 */
function getProfilePhotoUrl($nama, $jenjang, $role, $id)
{
    $fileName = strtolower(preg_replace('/\s+/', '_', $nama))
        . '_' . strtolower(preg_replace('/\s+/', '_', $jenjang))
        . '_' . strtolower($role)
        . '_' . $id . '.jpg';
    $filePath = __DIR__ . '/uploads/profile_pics/' . $fileName;

    if (file_exists($filePath)) {
        return getBaseUrl() . '/uploads/profile_pics/' . $fileName;
    } else {
        return getBaseUrl() . '/assets/img/undraw_profile.svg';
    }
}

/************************************
 * 7. MANAJEMEN GAJI & INDEKS SALARY
 ************************************/

/**
 * Menghitung masa kerja, menentukan salary index yang sesuai, 
 * dan mengupdate data user terkait salary index di database.
 *
 * @param mysqli $conn   Koneksi database.
 * @param int    $userId ID user.
 * @return bool True jika update berhasil, false jika gagal.
 */
if (!function_exists('updateSalaryIndexForUser')) {
    function updateSalaryIndexForUser($conn, $userId)
    {
        // Inisialisasi variabel untuk menghindari warning
        $role = '';
        $join_start = '';
        $pendidikan = '';
        $jenjang = '';

        // Ambil data user dari tabel anggota_sekolah
        $stmt = $conn->prepare("SELECT role, join_start, pendidikan, jenjang FROM anggota_sekolah WHERE id = ?");
        if (!$stmt) {
            error_log("Prepare error (masa kerja): " . $conn->error);
            return false;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($role, $join_start, $pendidikan, $jenjang);
        if (!$stmt->fetch()) {
            $stmt->close();
            return false;
        }
        $stmt->close();

        // Hitung lama kerja dalam tahun menggunakan DateTime
        $years = 0;
        if (!empty($join_start) && $join_start != '0000-00-00') {
            try {
                $startDate = new DateTime($join_start);
                $now       = new DateTime();
                $diff = $now->diff($startDate);
                $years = $diff->y;
            } catch (Exception $e) {
                $years = 0;
            }
        }

        // Inisialisasi variabel salary index
        $salaryIndexId = 0;
        $baseSalary = 0.0;
        $level = '';

        // Cari salary index yang sesuai berdasarkan masa kerja
        $stmt3 = $conn->prepare("SELECT id, base_salary, level FROM salary_indices WHERE min_years <= ? AND (max_years IS NULL OR ? <= max_years) ORDER BY min_years DESC LIMIT 1");
        if (!$stmt3) {
            error_log("Prepare error (salary_indices): " . $conn->error);
            return false;
        }
        $stmt3->bind_param("ii", $years, $years);
        $stmt3->execute();
        $stmt3->bind_result($salaryIndexId, $baseSalary, $level);
        if (!$stmt3->fetch()) {
            $stmt3->close();
            error_log("Tidak ada salary index yang cocok untuk tahun = $years");
            return false;
        }
        $stmt3->close();

        // Tentukan gaji pokok, khusus untuk role 'P' (Pendidik)
        $gaji_pokok = $baseSalary; // Default ke base salary
        if ($role === 'P' && !empty($pendidikan)) {
            $guru_salary = 0.0;
            $stmtStrata = $conn->prepare("SELECT gaji_pokok FROM gaji_pokok_strata_guru WHERE jenjang=? AND strata=? LIMIT 1");
            if ($stmtStrata) {
                $stmtStrata->bind_param("ss", $jenjang, $pendidikan);
                $stmtStrata->execute();
                $stmtStrata->bind_result($guru_salary);
                if ($stmtStrata->fetch()) {
                    $gaji_pokok = floatval($guru_salary);
                }
                $stmtStrata->close();
            }
        }

        // Update data user dengan salary index yang telah dihitung
        $stmtUpdate = $conn->prepare("UPDATE anggota_sekolah SET salary_index_id = ?, salary_index_level = ?, gaji_pokok = ?, masa_kerja_efektif = ? WHERE id = ?");
        if (!$stmtUpdate) {
            error_log("Prepare error (update anggota): " . $conn->error);
            return false;
        }
        $masaKerjaEfektif = $years;
        $stmtUpdate->bind_param("isidi", $salaryIndexId, $level, $gaji_pokok, $masaKerjaEfektif, $userId);
        $result = $stmtUpdate->execute();
        if (!$result) {
            error_log("Execute error (update anggota): " . $stmtUpdate->error);
        }
        $stmtUpdate->close();
        return $result;
    }
}

/**
 * Menormalisasi input pendidikan agar konsisten (misal: S1, S2, dll).
 *
 * @param string $pendidikan Input pendidikan.
 * @return string Pendidikan yang telah dinormalisasi.
 */
function normalizePendidikan($pendidikan)
{
    $pendidikan = strtoupper($pendidikan);
    if (strpos($pendidikan, 'D3') !== false) {
        return 'D3';
    } elseif (strpos($pendidikan, 'S1') !== false) {
        return 'S1';
    } elseif (strpos($pendidikan, 'S2') !== false) {
        return 'S2';
    } elseif (strpos($pendidikan, 'S3') !== false) {
        return 'S3';
    } else {
        return $pendidikan;
    }
}

/**
 * Melakukan update salary index untuk semua user dengan role 'P' atau 'TK'.
 *
 * @param mysqli $conn Koneksi database.
 * @return bool True jika semua update berhasil.
 */
function updateSalaryIndexForAll($conn)
{
    $sql = "SELECT id FROM anggota_sekolah WHERE role IN ('P', 'TK')";
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        error_log("Query error (select all): " . mysqli_error($conn));
        return false;
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $userId = intval($row['id']);
        updateSalaryIndexForUser($conn, $userId);
    }
    mysqli_free_result($res);
    return true;
}

/**
 * Mengembalikan rekomendasi salary index berdasarkan tanggal bergabung.
 *
 * @param mysqli $conn      Koneksi database.
 * @param string $joinStart Tanggal bergabung user.
 * @return array Array dengan salary_index_id dan penjelasan.
 */
function getRecommendedSalaryIndex($conn, $joinStart)
{
    if (empty($joinStart) || $joinStart == '0000-00-00') {
        return [
            'salary_index_id' => 0,
            'explanation' => 'Tanggal bergabung belum diisi / tidak valid'
        ];
    }
    try {
        $startDate = new DateTime($joinStart);
        $now       = new DateTime();
        if ($startDate > $now) {
            $masaKerjaTahun = 0;
        } else {
            $diff = $now->diff($startDate);
            $masaKerjaTahun = $diff->y;
        }
    } catch (\Exception $e) {
        return [
            'salary_index_id' => 0,
            'explanation' => 'Error parsing date: ' . $e->getMessage()
        ];
    }

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
            'explanation' => 'Query error: ' . $conn->error
        ];
    }
    $stmt->bind_param("ii", $masaKerjaTahun, $masaKerjaTahun);
    $stmt->execute();
    $res2 = $stmt->get_result();
    if ($res2 && $res2->num_rows > 0) {
        $row = $res2->fetch_assoc();
        return [
            'salary_index_id' => (int)$row['id'],
            'explanation' => 'Cocok dengan level: ' . $row['level']
        ];
    } else {
        return [
            'salary_index_id' => 0,
            'explanation' => 'Tidak ada level salary_indices yang cocok'
        ];
    }
}

/************************************
 * 8. LAIN-LAIN
 ************************************/

/**
 * Mengembalikan urutan jenjang pendidikan yang telah ditentukan.
 *
 * @return array Urutan jenjang.
 */
function getOrderedJenjang(): array
{
    return [
        'TK',
        'SD',
        'SMP',
        'SMA',
        'SMK 1',
        'SMK 2',
        'Universitas Stivera'
    ];
}

/**
 * Menutup koneksi database global (jika masih terbuka).
 */
function close_db_connection()
{
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            if ($conn->thread_id) {
                $conn->close();
            }
        } catch (Throwable $e) {
            // Abaikan error penutupan koneksi
        }
        $conn = null;
    }
}

// Tambahkan fungsi formatNominal jika belum ada di helpers.php
if (!function_exists('formatNominal')) {
    function formatNominal($angka) {
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }
}

/************************************
 * 9. FUNGSI TAMBAHAN UNTUK NOTIFIKASI
 ************************************/

/**
 * Menghasilkan full role berdasarkan nilai role dan job_title dari session.
 *
 * @return string Full role user.
 */
function getFullRole() {
    $userRole     = $_SESSION['role'] ?? '';
    $userJobTitle = $_SESSION['job_title'] ?? '';

    // Jika role bukan 'M', kembalikan nilai role yang ada
    if ($userRole !== 'M') {
        return $userRole;
    }
    $normalized = strtolower(trim($userJobTitle));
    
    // Jika job title mengandung "kepala sekolah", maka return TK
    if (strpos($normalized, 'kepala sekolah') !== false) {
        return 'TK';
    }
    
    // Pertahankan pengecekan untuk role M lainnya
    if (strpos($normalized, 'superadmin') !== false) {
        return 'M:superadmin';
    }
    if (strpos($normalized, 'sdm') !== false) {
        return 'M:sdm';
    }
    if (strpos($normalized, 'keuangan') !== false) {
        return 'M:keuangan';
    }
    
    return 'M';
}
