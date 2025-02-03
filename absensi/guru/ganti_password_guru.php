<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once '../../koneksi.php';

// Pastikan pengguna sudah login dan memiliki peran 'guru'
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['guru', 'karyawan'])) {
    header("Location: ../../login.php");
    exit();
}

$nip = $_SESSION['nip'] ?? '';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi_password_baru = $_POST['konfirmasi_password_baru'] ?? '';

    // Validasi input
    if (empty($password_lama) || empty($password_baru) || empty($konfirmasi_password_baru)) {
        $error = 'Semua kolom wajib diisi.';
    } elseif ($password_baru !== $konfirmasi_password_baru) {
        $error = 'Password baru dan konfirmasi password tidak cocok.';
    } else {
        // Ambil password dari database
        $stmt = $conn->prepare("SELECT password FROM anggota_sekolah WHERE nip = ?");
        if ($stmt) {
            $stmt->bind_param("s", $nip);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $row = $result->fetch_assoc();
                $stored_password = $row['password'];

                // Tentukan kondisi berdasarkan panjang password tersimpan
                if (strlen($stored_password) == 6) {
                    // Password masih berupa plaintext (PIN)
                    if ($password_lama !== $stored_password) {
                        $error = 'Password lama tidak valid.';
                    }
                } elseif (strlen($stored_password) == 32) {
                    // Password sudah di-hash dengan MD5
                    if (md5($password_lama) !== $stored_password) {
                        $error = 'Password lama tidak valid.';
                    }
                } else {
                    $error = 'Format password di database tidak valid.';
                }

                if (empty($error)) {
                    // Hash password baru menggunakan MD5
                    $hashed_new_password = md5($password_baru);

                    // Update password di database
                    $update_stmt = $conn->prepare("UPDATE anggota_sekolah SET password = ? WHERE nip = ?");
                    if ($update_stmt) {
                        $update_stmt->bind_param("ss", $hashed_new_password, $nip);
                        if ($update_stmt->execute()) {
                            $success = 'Password berhasil diperbarui.';
                        } else {
                            $error = 'Terjadi kesalahan saat memperbarui password.';
                        }
                        $update_stmt->close();
                    } else {
                        $error = 'Gagal menyiapkan SQL: ' . htmlspecialchars($conn->error);
                    }
                }
            } else {
                $error = 'Data pengguna tidak ditemukan.';
            }
            $stmt->close();
        } else {
            $error = 'Gagal menyiapkan SQL: ' . htmlspecialchars($conn->error);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password Guru</title>
    <!-- SB Admin 2 & Bootstrap CSS -->
    <link href="../../assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a href="../../logout.php" class="btn btn-danger btn-sm">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </nav>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Ganti Password</h1>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($success) ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <form action="ganti_password_guru.php" method="POST">
                        <div class="form-group">
                            <label for="password_lama">Password Lama</label>
                            <input type="password" name="password_lama" id="password_lama" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="password_baru">Password Baru</label>
                            <input type="password" name="password_baru" id="password_baru" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="konfirmasi_password_baru">Konfirmasi Password Baru</label>
                            <input type="password" name="konfirmasi_password_baru" id="konfirmasi_password_baru" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Ganti Password</button>
                    </form>
                </div>
                <!-- End of Page Content -->
            </div>
            <!-- End of Main Content -->
        </div>
        <!-- End Content Wrapper -->
    </div>
    <!-- End Page Wrapper -->

    <!-- JavaScript -->
    <script src="../../assets/vendor/jquery/jquery.min.js"></script>
    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../../assets/js/sb-admin-2.min.js"></script>
</body>
</html>
