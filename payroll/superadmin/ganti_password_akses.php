<?php
// change_overlay_password.php
session_start();
require_once __DIR__ . '/../../helpers.php';
init_error_handling();
generate_csrf_token();

// Hanya user superadmin yang boleh mengakses halaman ini
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: /payroll_absensi_v2/login.php");
    exit();
}

// Lokasi file penyimpanan password overlay
$password_file = __DIR__ . '/../../config/password_akses.txt';
if (file_exists($password_file)) {
    $current_password = trim(file_get_contents($password_file));
} else {
    $current_password = '123456';
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "Token CSRF tidak valid.";
    } else {
        $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
        $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
        
        if ($new_password === '' || $confirm_password === '') {
            $message = "Semua kolom harus diisi.";
        } elseif ($new_password !== $confirm_password) {
            $message = "Password tidak cocok.";
        } else {
            // Simpan password baru ke file
            if (file_put_contents($password_file, $new_password) !== false) {
                $message = "Password overlay berhasil diubah.";
                $current_password = $new_password;
            } else {
                $message = "Gagal mengubah password.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Ganti Password Overlay - Superadmin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap CSS (dengan nonce jika diperlukan) -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { padding-top: 50px; }
        .container { max-width: 500px; }
    </style>
</head>
<body>
<div class="container">
    <h2 class="mb-4">Ganti Password Overlay</h2>
    <?php if ($message): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <form action="" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <div class="form-group">
            <label for="new_password">Password Baru</label>
            <input type="password" name="new_password" id="new_password" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="confirm_password">Konfirmasi Password Baru</label>
            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Ubah Password</button>
    </form>
    <br>
    <p>Password saat ini: <strong><?php echo htmlspecialchars($current_password); ?></strong></p>
</div>
</body>
</html>
