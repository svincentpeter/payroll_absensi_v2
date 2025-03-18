<?php
// File: helpers.php

function start_session_safe() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

function sanitize_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Alias bersihkan_input()
if (!function_exists('bersihkan_input')) {
    function bersihkan_input($data) {
        return sanitize_input($data);
    }
}

/**
 * Mengirim respons JSON dan mengakhiri eksekusi script.
 */
function send_response($code, $result) {
    // === HAPUS DEBUG ===
    // if ($code !== 0) {
    //     error_log("Response Code $code: " . json_encode($result));
    // }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => $code, 'result' => $result]);
    exit();
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        send_response(403, 'Token CSRF tidak valid.');
    }
}

/**
 * Mencatat error ke dalam file log, jika memang error serius.
 * (Tetap kita biarkan, karena bisa dipakai saat benar‐benar error.)
 */
function log_error($message) {
    $error_log_path = __DIR__ . '/error.log';
    error_log("[" . date('Y-m-d H:i:s') . "] " . $message . "\n", 3, $error_log_path);
}

/**
 * Menambahkan catatan audit log ke database.
 */
function add_audit_log($conn, $user_nip, $action, $details) {
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

function translateJenis($jenis) {
    $translations = [
        'earnings'   => 'Pendapatan',
        'deductions' => 'Potongan'
    ];
    return $translations[$jenis] ?? 'Tidak Dikenal';
}

function init_error_handling() {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    // Path error_log masih kita biarkan, untuk log error fatal
    ini_set('error_log', __DIR__ . '/error.log');
    error_reporting(E_ALL);
}

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
    // === HAPUS DEBUG ===
    // if (!isset($bulan[$monthNumber])) {
    //     error_log("Bulan tidak ditemukan untuk angka: " . json_encode($monthNumber));
    // }
    return $bulan[$monthNumber] ?? 'Tidak Diketahui';
}

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
    return $map[$lower] ?? 0;
}

function formatNominal($nominal) {
    return 'Rp ' . number_format($nominal, 2, ',', '.');
}

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
        case 'TK': return '<span class="badge bg-success">TK</span>';
        case 'SD': return '<span class="badge bg-primary">SD</span>';
        case 'SMP':return '<span class="badge bg-info text-dark">SMP</span>';
        case 'SMA':return '<span class="badge bg-warning text-dark">SMA</span>';
        case 'SMK':return '<span class="badge bg-secondary">SMK</span>';
        default:   return '<span class="badge bg-light text-dark">' . htmlspecialchars($jenjang) . '</span>';
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
    start_session_safe();
    $userRole     = $_SESSION['role'] ?? '';
    $userJobTitle = $_SESSION['job_title'] ?? '';

    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    $allowed = false;

    foreach ($allowedRoles as $allowedRole) {
        if ($allowedRole === $userRole) {
            $allowed = true;
            break;
        }
        if ($userRole === 'M' && strpos($allowedRole, 'M:') === 0) {
            $allowedDetail = trim(substr($allowedRole, 2)); 
            $allowedDetailNormalized = strtolower(str_replace('_', ' ', $allowedDetail));
            $userJobTitleNormalized = strtolower(str_replace('_', ' ', trim($userJobTitle)));
            if ($allowedDetailNormalized === $userJobTitleNormalized) {
                $allowed = true;
                break;
            }
        }
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

function getProfilePhotoUrl($nama, $jenjang, $role, $id) {
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

function updateSalaryIndexForUser($conn, $userId) {
    // Ambil join_start
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
        return false;
    }
    $stmt->close();
    
    // Hitung masaKerjaAktual
    try {
        $startDate = new DateTime($joinStart);
        $now       = new DateTime();
        if ($startDate > $now) {
            $masaKerjaAktual = 0;
        } else {
            $diff = $now->diff($startDate);
            $masaKerjaAktual = $diff->y + ($diff->m / 12.0);
        }
    } catch (\Exception $e) {
        $masaKerjaAktual = 0;
    }
    
    // Cek SP
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
    $res2 = $stmt2->get_result();
    $row2 = $res2->fetch_assoc();
    $spCount = intval($row2['spCount'] ?? 0);
    $stmt2->close();
    
    $penalty = ($spCount > 0) ? 1 : 0;
    $masaKerjaEfektif = $masaKerjaAktual - $penalty;
    if ($masaKerjaEfektif < 0) {
        $masaKerjaEfektif = 0;
    }

    // === HAPUS DEBUG ===
    // error_log("DEBUG MasaKerja: userId=$userId, masaKerjaAktual=$masaKerjaAktual, penalty=$penalty, effectiveYears=".floor($masaKerjaEfektif));

    $effectiveYearsForIndex = floor($masaKerjaEfektif);

    // Cari salary index
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
    
    // Update
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

function updateSalaryIndexForAll($conn) {
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

function getRecommendedSalaryIndex($conn, $joinStart) {
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
            'explanation' => 'Error parsing date: '.$e->getMessage()
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
            'explanation' => 'Query error: '.$conn->error
        ];
    }
    $stmt->bind_param("ii", $masaKerjaTahun, $masaKerjaTahun);
    $stmt->execute();
    $res2 = $stmt->get_result();
    if ($res2 && $res2->num_rows > 0) {
        $row = $res2->fetch_assoc();
        return [
            'salary_index_id' => (int)$row['id'],
            'explanation' => 'Cocok dengan level: '.$row['level']
        ];
    } else {
        return [
            'salary_index_id' => 0,
            'explanation' => 'Tidak ada level salary_indices yang cocok'
        ];
    }
}

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

if (!function_exists('getBaseUrl')) {
    function getBaseUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
                     || $_SERVER['SERVER_PORT'] == 443)
                    ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $subfolder = '/payroll_absensi_v2';
        return $protocol . $host . $subfolder;
    }
}

function close_db_connection() {
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            if ($conn->thread_id) {
                $conn->close();
            }
        } catch (Throwable $e) {
            // abaikan
        }
        $conn = null;
    }
}
