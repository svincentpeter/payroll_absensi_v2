    <?php
    // File: helpers.php
    require_once __DIR__ . '/sdm/includes/mgk_salary_handler.php';
// Warna untuk semua badge, terpusat di satu tempat:
$GLOBALS['BADGE_COLORS'] = [
    'status_kerja' => [
        'tetap'   => ['bg' => '#43a047', 'fg'=>'#ffffff'], // Green
        'kontrak' => ['bg' => '#ffe082', 'fg'=>'#212529'], // Soft Yellow
    ],
    'role' => [
        'P'  => ['bg' => '#90caf9', 'fg'=>'#212529'], // Light Blue
        'TK' => ['bg' => '#ffb74d', 'fg'=>'#212529'], // Soft Orange
        'M'  => ['bg' => '#e57373', 'fg'=>'#212529'], // Soft Red
    ],
    'jenjang' => [
        // Semua warna pastel/terang, tetap readable untuk font hitam tebal
        'tk'       => ['bg'=>'#f8bbd0', 'fg'=>'#212529'], // Pastel Pink
        'sd'       => ['bg'=>'#fff59d', 'fg'=>'#212529'], // Lemon Yellow
        'smp'      => ['bg'=>'#80deea', 'fg'=>'#212529'], // Pastel Cyan
        'sma'      => ['bg'=>'#aed581', 'fg'=>'#212529'], // Pastel Green
        'smk1'     => ['bg'=>'#ffd180', 'fg'=>'#212529'], // Light Peach/Orange
        'smk2'     => ['bg'=>'#ce93d8', 'fg'=>'#212529'], // Soft Purple
        'stifera'  => ['bg'=>'#b3e5fc', 'fg'=>'#212529'], // Soft Sky Blue
    ],
];


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
    if ($data === null) {        // ← tambahkan
        return '';               //   kembalikan string kosong
    }
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
        return 'Rp ' . number_format($nominal, 0, ',', '.');
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
    function getIndonesianMonthName(int $month): string {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];
        return $months[$month] ?? '';
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
    function getBadgeRole(string $role): string
{
    $c = $GLOBALS['BADGE_COLORS']['role'][$role] 
       ?? ['bg'=>'#6c757d','fg'=>'#ffffff'];
    $labels = ['P'=>'Pendidik','TK'=>'Tenaga Kependidikan','M'=>'Manajerial'];
    $label = $labels[$role] ?? htmlspecialchars($role);
    return "<span class=\"badge\" "
        . "style=\"background-color:{$c['bg']};color:{$c['fg']};\">"
        . "{$label}</span>";
}

    /**
 * Menghasilkan badge HTML untuk jenjang (TK/SD/SMP/SMA/SMK 1/SMK 2/Univ Stivera).
 *
 * @param string $jenjang e.g. "SMK 1", "SMK 2", "Universitas Stivera"
 * @return string <span class="badge" style="…">…</span>
 */
function getBadgeJenjang($kode_jenjang, $conn) {
    $sql = "SELECT nama_jenjang, color_bg, color_fg FROM jenjang_sekolah WHERE kode_jenjang=? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $kode_jenjang);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return "<span class='badge' style='background:{$row['color_bg']};color:{$row['color_fg']};'><strong>{$row['nama_jenjang']}</strong></span>";
    }
    return "<span class='badge bg-secondary'><strong>$kode_jenjang</strong></span>";
}





    /**
     * Menghasilkan badge HTML untuk status kerja karyawan.
     *
     * @param string $status Status kerja (misal: 'tetap' atau 'kontrak').
     * @return string HTML badge.
     */
    function getBadgeStatusKerja(string $status): string
{
    $key = strtolower($status);
    $c   = $GLOBALS['BADGE_COLORS']['status_kerja'][$key] 
         ?? ['bg'=>'#6c757d','fg'=>'#ffffff'];
    $label = ucfirst($key);
    return "<span class=\"badge\" "
        . "style=\"background-color:{$c['bg']};color:{$c['fg']};\">"
        . "{$label}</span>";
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
     * @param  string $pathDb  Nilai kolom foto_profil di DB, bisa:
     *                         - 'default.jpg'
     *                         - '/payroll_absensi_v2/uploads/profile_pics/…'
     * @return string URL lengkap ke foto, atau placeholder SVG
     */
    function getProfilePhotoUrl(string $pathDb): string
    {
        $base = getBaseUrl(); 

        // 1) Jika default atau kosong: pakai placeholder
        if (empty($pathDb) || $pathDb === 'default.jpg') {
            return "{$base}/assets/img/placeholder_foto_profil.svg";
        }

        // 2) Jika sudah URL lengkap
        if (preg_match('#^https?://#i', $pathDb)) {
            return $pathDb;
        }

        // 3) Jika path relatif seperti '/…/uploads/...'
        return rtrim($base, '/') . '/' . ltrim($pathDb, '/');
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
        // Inisialisasi variabel
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

        // Ambil salary index sesuai masa kerja
        $salaryIndexId = 0;
        $baseSalary = 0.0;
        $level = '';
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

        // ==== LOGIC FIX: Panggil fungsi hitungGajiPokok agar pencocokan konsisten ====
        if (!function_exists('hitungGajiPokok')) {
            require_once __DIR__ . '/mgk_salary_handler.php'; // pastikan ada
        }
        $gaji_pokok = hitungGajiPokok($conn, $role, $pendidikan, $jenjang);

        // Update data user
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

    // helpers.php
/**
 * Mengambil daftar jenjang dari database, terurut sesuai urutan id.
 * Return: array asosiatif kode_jenjang => nama_jenjang
 * @param mysqli $conn Koneksi database
 * @param bool $aktifOnly Hanya yang aktif
 * @return array
 */
function getOrderedJenjang(mysqli $conn, bool $aktifOnly = true): array
{
    $list = [];
    $sql = "SELECT kode_jenjang, nama_jenjang FROM jenjang_sekolah"
         . ($aktifOnly ? " WHERE is_aktif = 1" : "")
         . " ORDER BY id ASC";
    $res = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($res)) {
        $list[$row['kode_jenjang']] = $row['nama_jenjang'];
    }
    return $list;
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
            return 'P';
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

    /**
     * Menghasilkan badge dalam bentuk string berdasarkan jumlah notifikasi.
     *
     * @param int $count Jumlah notifikasi.
     * @return string String badge; kosong jika count < 1, "1" jika 1, atau "X+" jika lebih dari 1.
     */
    function formatBadge($count)
    {
        if ($count < 1) {
            return "";
        }
        return ($count === 1) ? "1" : ($count . "+");
    }

    if (!function_exists('qCount')) {
        /**
         * Helper singkat: eksekusi SELECT … COUNT(*) dan kembalikan int.
         * @param mysqli  $conn
         * @param string  $sql   query dengan alias AS cnt
         * @param string  $types string tipe bind_param ('' jika tidak ada)
         * @param array   $params parameter bind
         * @return int
         */
        function qCount(mysqli $conn, string $sql, string $types = '', array $params = []): int
        {
            $stmt = $conn->prepare($sql);
            if (!$stmt) { error_log($conn->error); return 0; }
            if ($types) { $stmt->bind_param($types, ...$params); }
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();
            return intval($row['cnt'] ?? 0);
        }
    }

    /**
     * Hitung tanggal kontrak selesai.
     * @param string $joinStart   (Y-m-d)
     * @param int    $lamaKontrak (bulan)
     * @return string|null        (Y-m-d) atau NULL bila input tidak valid
     */
    // Tempatkan fungsi formatPhoneNumber() di luar fungsi hitungTanggalSelesaiKontrak()
    function formatPhoneNumber($phone) {
        $phone = trim($phone);
        // Jika nomor dimulai dengan '0', ubah menjadi '62'
        if (substr($phone, 0, 1) === '0') {
            return '62' . substr($phone, 1);
        }
        return $phone;
    }

    function hitungTanggalSelesaiKontrak(string $joinStart, int $lamaKontrak): ?string {
        if ($lamaKontrak <= 0 || $joinStart === '0000-00-00' || empty($joinStart)) {
            return null;
        }
        try {
            $d = new DateTime($joinStart);
            $d->modify("+{$lamaKontrak} months")->modify('-1 day'); // selesai H‑1
            return $d->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }

    /* =========================================================
    *  Helper umum – panggil di mana saja
    * ========================================================= */

    /** kirim JSON & henti eksekusi */
    function send_json(int $code, string $msg, array $data = []): void {
        header('Content-Type:application/json; charset=utf-8');
        echo json_encode(['code'=>$code,'message'=>$msg]+$data);
        exit;
    }

    /************************************
 * 10. GENERIC DB WRAPPERS & SCHEDULE HELPERS
 ************************************/

/**
 * Fetch satu baris hasil query.
 *
 * @param mysqli $conn   Koneksi database.
 * @param string $sql    Query dengan placeholder (?).
 * @param string $types  String tipe untuk bind_param (misal "si").
 * @param array  $params Array parameter untuk bind_param.
 * @return array|null    Baris pertama hasil fetch_assoc(), atau null jika tidak ada.
 */
if (!function_exists('fetchSingleRow')) {
    function fetchSingleRow(mysqli $conn, string $sql, string $types = '', array $params = [])
    {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            log_error("fetchSingleRow prepare error: " . $conn->error);
            return null;
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc() ?: null;
        $stmt->close();
        return $row;
    }
}

/**
 * Fetch semua baris hasil query.
 *
 * @param mysqli $conn   Koneksi database.
 * @param string $sql    Query dengan placeholder (?).
 * @param string $types  String tipe untuk bind_param.
 * @param array  $params Array parameter untuk bind_param.
 * @return array         Array of associative arrays.
 */
if (!function_exists('fetchAllRows')) {
    function fetchAllRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
    {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            log_error("fetchAllRows prepare error: " . $conn->error);
            return [];
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $stmt->close();
        return $rows;
    }
}

/**
 * Dapatkan nama anggota berdasarkan NIP.
 *
 * @param mysqli $conn Koneksi database.
 * @param string $nip  NIP anggota.
 * @return string      Nama anggota, atau string kosong jika tidak ditemukan.
 */
if (!function_exists('getNameByNip')) {
    function getNameByNip(mysqli $conn, string $nip): string
    {
        $row = fetchSingleRow($conn,
            "SELECT nama FROM anggota_sekolah WHERE nip = ? LIMIT 1",
            "s",
            [$nip]
        );
        return $row['nama'] ?? '';
    }
}

/**
 * Dapatkan seluruh jadwal piket untuk satu anggota.
 *
 * @param mysqli $conn Koneksi database.
 * @param string $nip  NIP anggota.
 * @return array       Array jadwal_piket.
 */
if (!function_exists('getScheduleByUser')) {
    function getScheduleByUser(mysqli $conn, string $nip): array
    {
        return fetchAllRows($conn,
            "SELECT * FROM jadwal_piket WHERE nip = ? ORDER BY tanggal ASC",
            "s",
            [$nip]
        );
    }
}

/**
 * Dapatkan seluruh permintaan tukar jadwal untuk satu anggota (sebagai pengaju atau tujuan).
 *
 * @param mysqli $conn Koneksi database.
 * @param string $nip  NIP anggota.
 * @return array       Array permintaan_tukar_jadwal dengan join data jadwal_pengaju.
 */
if (!function_exists('getSwapRequestsForUser')) {
    function getSwapRequestsForUser(mysqli $conn, string $nip): array
    {
        // Gabungkan semua request di mana user adalah pengaju atau tujuan dan masih 'Pending'
        $sql = "
            SELECT ptj.*, 
                   jp_pengaju.nama_guru AS nama_guru_pengaju, 
                   jp_pengaju.tanggal AS tanggal_piket_pengaju, 
                   jp_pengaju.waktu_piket AS waktu_piket_pengaju
              FROM permintaan_tukar_jadwal ptj
              JOIN jadwal_piket jp_pengaju ON ptj.id_jadwal_pengaju = jp_pengaju.id_jadwal
             WHERE (ptj.nip_pengaju = ? OR ptj.nip_tujuan = ?)
               AND ptj.status = 'Pending'
             ORDER BY ptj.tanggal_permintaan DESC
        ";
        return fetchAllRows($conn, $sql, "ss", [$nip, $nip]);
    }
}

/**
 * Ambil satu baris hasil query sebagai associative array.
 *
 * @param mysqli $conn   Koneksi database
 * @param string $sql    SQL dengan placeholder (?)
 * @param string $types  String tipe untuk bind_param (misal "is")
 * @param array  $params Array nilai untuk bind_param
 * @return array|null    Baris pertama hasil query, atau null jika tidak ada
 */
function single_row(mysqli $conn, string $sql, string $types = "", array $params = [])
{
    $stmt = $conn->prepare($sql);
    if ($types !== "") {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Ambil semua baris hasil query sebagai array of associative array.
 *
 * @param mysqli $conn   Koneksi database
 * @param string $sql    SQL dengan placeholder (?)
 * @param string $types  String tipe untuk bind_param
 * @param array  $params Array nilai untuk bind_param
 * @return array         Semua baris hasil query
 */
function all_rows(mysqli $conn, string $sql, string $types = "", array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if ($types !== "") {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * Cari nama guru/karyawan berdasarkan NIP.
 *
 * @param mysqli $conn Koneksi database
 * @param string $nip  NIP yang dicari
 * @return string      Nama, atau string kosong jika tidak ditemukan
 */
function get_name_by_nip(mysqli $conn, string $nip): string
{
    $r = single_row($conn,
        "SELECT nama FROM anggota_sekolah WHERE nip = ? LIMIT 1",
        "s",
        [$nip]
    );
    return $r['nama'] ?? '';
}

/**
 * Validasi apakah tanggal masuk ke periode piket (Juni, Juli, Desember, Januari).
 *
 * @param string $tanggal Tanggal dalam format 'Y-m-d'
 * @return bool           True jika bulan 6,7,12 atau 1, false otherwise
 */
function isValidPiketDate(string $tanggal): bool
{
    $ts = strtotime($tanggal);
    if (!$ts) {
        return false;
    }
    $month = (int) date('n', $ts);
    return in_array($month, [6, 7, 12, 1], true);
}


/* ---------- Bulan & Hari (versi ringkas, ENG ➜ IND) ---------- */
if (!function_exists('indo_month')) {
    function indo_month(string $eng): string
    {
        static $m = [
            'January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April',
            'May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus',
            'September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'
        ];
        return $m[$eng] ?? $eng;
    }
}
if (!function_exists('indo_day')) {
    function indo_day(string $shortEng): string
    {
        static $d = [
            'Mon'=>'Senin','Tue'=>'Selasa','Wed'=>'Rabu','Thu'=>'Kamis',
            'Fri'=>'Jumat','Sat'=>'Sabtu','Sun'=>'Minggu'
        ];
        return $d[$shortEng] ?? $shortEng;
    }
}

/**
 * Ambil semua tanggal pending milik satu guru.
 * Hasil: array ['2025-06-01', '2025-06-08', …]
 *
 * @param  mysqli $conn
 * @param  string $nip
 * @return array
 */
if (!function_exists('getExistingScheduleByNip')) {
    function getExistingScheduleByNip(mysqli $conn, string $nip): array
    {
        $rows = fetchAllRows(
            $conn,
            "SELECT tanggal FROM jadwal_piket
              WHERE nip = ? AND status = 'pending'",
            "s",
            [$nip]
        );
        return array_column($rows, 'tanggal');
    }
}

// Agar skrip migrasi DB tahu nama index standar
if (!defined('JADWAL_DUP_IDX')) {
    define('JADWAL_DUP_IDX', 'idx_piket_nip_tanggal');
}

