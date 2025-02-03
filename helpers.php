<?php
// File: /payroll_absensi_v2/payroll/keuangan/helpers.php

/**
 * Fungsi untuk memulai session dan memastikan session sudah dimulai
 */
function start_session_safe() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Fungsi membersihkan input untuk mencegah XSS
 * @param string $data Input yang akan dibersihkan
 * @return string Input yang sudah dibersihkan
 */
function bersihkan_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Fungsi untuk mengirim respons JSON dan mengakhiri eksekusi script
 * @param int $code Kode status (0 untuk sukses, >0 untuk error)
 * @param mixed $result Data atau pesan yang dikirim
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
 * Fungsi untuk menghasilkan CSRF token dan menyimpannya di session
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Fungsi untuk memverifikasi CSRF token yang dikirimkan
 * @param string $token Token yang dikirim dari klien
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        send_response(403, 'Token CSRF tidak valid.');
    }
}

/**
 * Fungsi untuk meng-log error ke file log
 * @param string $message Pesan error yang akan dicatat
 */
function log_error($message) {
    // Pastikan direktori writable dan path log sesuai
    error_log("[" . date('Y-m-d H:i:s') . "] " . $message . "\n", 3, __DIR__ . '/error.log');
}

/**
 * Fungsi untuk menambahkan audit log
 * @param mysqli $conn Koneksi database
 * @param int $user_id ID pengguna yang melakukan aksi
 * @param string $action Jenis aksi (misal: AddPayhead, EditPayhead)
 * @param string $details Detail tambahan tentang aksi
 */
function add_audit_log($conn, $user_id, $action, $details) {
    // Jika user_id tidak valid (misal, 0), lewati pencatatan audit log.
    if (empty($user_id) || $user_id <= 0) {
        return true;
    }
    
    // Dapatkan alamat IP pengguna
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    
    // Dapatkan informasi User Agent
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    
    // Mempersiapkan statement SQL
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    if (!$stmt) {
        log_error("Gagal menyiapkan statement untuk audit log: " . $conn->error);
        return false;
    }
    $stmt->bind_param("issss", $user_id, $action, $details, $ip_address, $user_agent);
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
 * @param string $jenis Jenis payhead (e.g., 'earnings', 'deductions').
 * @return string Terjemahan jenis payhead.
 */
function translateJenis($jenis) {
    $translations = [
        'earnings' => 'Pendapatan',
        'deductions' => 'Potongan'
    ];
    return isset($translations[$jenis]) ? $translations[$jenis] : 'Tidak Dikenal';
}

/**
 * Fungsi untuk menginisialisasi dan mengkonfigurasi error reporting
 * (Opsional, bisa dipindahkan ke file konfigurasi utama)
 */
function init_error_handling() {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error.log'); // Pastikan path ini writable
    error_reporting(E_ALL);
}
?>
