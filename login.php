<?php
// login.php
session_start();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/helpers.php'; // Pastikan helpers.php terinklusi

// Inisialisasi variabel pesan error
$error = '';

// Jika pengguna sudah login, arahkan ke dashboard sesuai role
if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'superadmin':
            header("Location: payroll/superadmin/dashboard_superadmin.php");
            exit();
        case 'sdm':
            header("Location: absensi/sdm/dashboard_sdm.php");
            exit();
        case 'keuangan':
            header("Location: payroll/keuangan/dashboard_keuangan.php");
            exit();
        case 'guru':
            header("Location: absensi/guru/dashboard_guru.php");
            exit();
        case 'karyawan':
            header("Location: absensi/karyawan/dashboard_karyawan.php");
            exit();
        case 'petinggi':
            header("Location: dashboard_petinggi.php");
            exit();
        default:
            // Role tidak dikenal, logout
            header("Location: logout.php");
            exit();
    }
}

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mengambil data dari form
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Validasi input
    if (empty($username) || empty($password)) {
        $error = "Username dan Password harus diisi.";
    } else {
        // 1) CARI DI TABEL `users` (untuk role superadmin, sdm, keuangan)
        $stmt = $conn->prepare("SELECT id_user, username, password, role FROM users WHERE username = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
        } else {
            // Hindari menampilkan error database ke pengguna
            error_log("Gagal menyiapkan statement: " . $conn->error);
            $error = "Terjadi kesalahan saat memproses permintaan Anda. Silakan coba lagi.";
            $user = null;

            // **Audit Log untuk Kesalahan Sistem**
            add_audit_log($conn, NULL, 'LoginError', "Gagal menyiapkan statement pada login: " . $conn->error);
        }

        if ($user) {
            // Menggunakan MD5 untuk verifikasi password
            if (md5($password) === $user['password']) {
                // Password valid
                $_SESSION['user_id'] = $user['id_user'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                session_regenerate_id(true);

                // **Audit Log untuk Login Sukses**
                add_audit_log($conn, $user['id_user'], 'Login', "Pengguna '{$user['username']}' berhasil login sebagai '{$user['role']}'.");

                // Redirect berdasarkan role di tabel users
                switch ($user['role']) {
                    case 'superadmin':
                        header("Location: payroll/superadmin/dashboard_superadmin.php");
                        exit();
                    case 'keuangan':
                        header("Location: payroll/keuangan/dashboard_keuangan.php");
                        exit();
                    case 'sdm':
                        header("Location: absensi/sdm/dashboard_sdm.php");
                        exit();
                    default:
                        $error = "Role tidak dikenali di tabel users.";
                        // **Audit Log untuk Role Tidak Dikenali**
                        add_audit_log($conn, $user['id_user'], 'LoginFailed', "Pengguna '{$user['username']}' gagal login: Role tidak dikenali.");
                }
            } else {
                $error = "Password salah.";

                // **Audit Log untuk Login Gagal (Password Salah)**
                add_audit_log($conn, $user['id_user'], 'LoginFailed', "Pengguna '{$user['username']}' gagal login: Password salah.");
            }
        } else {
            // 2) TIDAK DITEMUKAN DI TABEL `users`, cek di TABEL `anggota_sekolah`
            //    Di sini kita asumsikan login-nya pakai nip = $username
            $stmt2 = $conn->prepare("SELECT id, nip, nama, password, job_title, role FROM anggota_sekolah WHERE nip = ? LIMIT 1");
            if ($stmt2) {
                $stmt2->bind_param("s", $username);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                $rowAnggota = $result2->fetch_assoc();
                $stmt2->close();

                if ($rowAnggota) {
                    // Menggunakan MD5 untuk verifikasi password
                    if (md5($password) === $rowAnggota['password']) {
                        // Berhasil login di anggota_sekolah
                        $_SESSION['nip']  = $rowAnggota['nip'];
                        $_SESSION['nama'] = $rowAnggota['nama'];

                        // Ambil job_title dan role dari tabel anggota_sekolah
                        $jobTitle    = strtolower($rowAnggota['job_title'] ?? '');
                        $anggotaRole = $rowAnggota['role'] ?? '';

                        // Jika role managerial (M) gunakan pengecekan job_title untuk menentukan dashboard
                        if ($anggotaRole === 'M') {
                            if (strpos($jobTitle, 'superadmin') !== false) {
                                $_SESSION['role'] = 'superadmin';
                                add_audit_log($conn, $rowAnggota['id'], 'Login', "Pengguna dengan NIP '{$rowAnggota['nip']}' berhasil login sebagai 'superadmin'.");
                                header("Location: payroll/superadmin/dashboard_superadmin.php");
                                exit();
                            } elseif (strpos($jobTitle, 'sdm') !== false) {
                                $_SESSION['role'] = 'sdm';
                                add_audit_log($conn, $rowAnggota['id'], 'Login', "Pengguna dengan NIP '{$rowAnggota['nip']}' berhasil login sebagai 'sdm'.");
                                header("Location: absensi/sdm/dashboard_sdm.php");
                                exit();
                            } elseif (strpos($jobTitle, 'keuangan') !== false) {
                                $_SESSION['role'] = 'keuangan';
                                add_audit_log($conn, $rowAnggota['id'], 'Login', "Pengguna dengan NIP '{$rowAnggota['nip']}' berhasil login sebagai 'keuangan'.");
                                header("Location: payroll/keuangan/dashboard_keuangan.php");
                                exit();
                            } elseif (strpos($jobTitle, 'kepala sekolah') !== false) {
                                $_SESSION['role'] = 'petinggi';
                                add_audit_log($conn, $rowAnggota['id'], 'Login', "Pengguna dengan NIP '{$rowAnggota['nip']}' berhasil login sebagai 'petinggi'.");
                                header("Location: dashboard_petinggi.php");
                                exit();
                            } else {
                                $error = "Role managerial tidak dikenali.";
                                add_audit_log($conn, $rowAnggota['id'], 'LoginFailed', "Pengguna dengan NIP '{$rowAnggota['nip']}' gagal login: Role managerial tidak dikenali.");
                            }
                        }
                        // Jika job_title guru/karyawan atau role P/TK maka masuk ke dashboard guru (sesuai ketentuan)
                        elseif (
                            strpos($jobTitle, 'guru') !== false ||
                            strpos($jobTitle, 'karyawan') !== false ||
                            $anggotaRole === 'P' ||
                            $anggotaRole === 'TK'
                        ) {
                            $_SESSION['role'] = 'guru';
                            add_audit_log($conn, $rowAnggota['id'], 'Login', "Pengguna dengan NIP '{$rowAnggota['nip']}' berhasil login sebagai 'guru'.");
                            header("Location: absensi/guru/dashboard_guru.php");
                            exit();
                        } else {
                            $error = "Role anggota_sekolah tidak dikenali.";
                            add_audit_log($conn, $rowAnggota['id'], 'LoginFailed', "Pengguna dengan NIP '{$rowAnggota['nip']}' gagal login: Role anggota_sekolah tidak dikenali.");
                        }
                    } else {
                        $error = "Password salah.";

                        // **Audit Log untuk Login Gagal (Password Salah) di anggota_sekolah**
                        add_audit_log($conn, $rowAnggota['id'], 'LoginFailed', "Pengguna dengan NIP '{$rowAnggota['nip']}' gagal login: Password salah.");
                    }
                } else {
                    $error = "Username/NIP tidak ditemukan.";

                    // **Audit Log untuk Login Gagal (Username/NIP Tidak Ditemukan)**
                    add_audit_log($conn, NULL, 'LoginFailed', "Pengguna dengan username/NIP '{$username}' gagal login: Tidak ditemukan di tabel users dan anggota_sekolah.");
                }
            } else {
                // Hindari menampilkan error database ke pengguna
                error_log("Gagal menyiapkan statement untuk anggota_sekolah: " . $conn->error);
                $error = "Terjadi kesalahan saat memproses permintaan Anda. Silakan coba lagi.";

                // **Audit Log untuk Kesalahan Sistem pada anggota_sekolah**
                add_audit_log($conn, NULL, 'LoginError', "Gagal menyiapkan statement pada anggota_sekolah login: " . $conn->error);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Sekolah Nusaputera</title>
    <!-- Link Bootstrap 4 -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
    /* Style yang sudah Anda buat */
    body {
        background: linear-gradient(45deg, #4e73df, rgb(172, 234, 255));
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        font-family: 'Arial', sans-serif;
    }
    .login-container {
        width: 100%;
        max-width: 400px;
        background-color: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        overflow: hidden;
        animation: fadeIn 1s ease-in-out;
    }
    .card-header {
        background-color: white;
        text-align: center;
        padding: 20px;
        border-bottom: none;
    }
    .card-header img {
        width: 80px;
        height: auto;
        margin-bottom: 10px;
    }
    .card-header h2 {
        font-size: 1.8rem;
        font-weight: bold;
        color: #4e73df;
    }
    .card-body {
        padding: 30px;
    }
    .form-group label {
        font-weight: bold;
        color: #4e73df;
    }
    .form-control {
        border-radius: 10px;
        border: 2px solid #d1d3e2;
        transition: border-color 0.3s ease-in-out;
    }
    .form-control:focus {
        border-color: #4e73df;
        box-shadow: 0 0 10px rgba(78, 115, 223, 0.2);
    }
    .btn-primary {
        background-color: #4e73df;
        border-radius: 20px;
        font-size: 1.2rem;
        padding: 10px 20px;
        transition: background-color 0.3s ease-in-out;
        border: none;
    }
    .btn-primary:hover {
        background-color: #2e59d9;
    }
    .btn i {
        margin-right: 8px;
    }
    .form-icon {
        font-size: 1.5rem;
        color: #4e73df;
        margin-right: 10px;
    }
    .alert {
        border-radius: 10px;
        margin-top: 10px;
    }
    @keyframes fadeIn {
        from {opacity: 0; transform: translateY(20px);}
        to   {opacity: 1; transform: translateY(0);}
    }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <img src="assets/img/Logo.png" alt="Logo Sekolah Nusaputera">
                <div style="text-align: center; margin-top: 10px;">
                    <span style="color:rgb(48, 179, 249); font-size:1.7rem; font-weight:bold;">Sekolah </span>
                    <span style="color:#0d47a1; font-size:1.7rem; font-weight:bold;">Nusaputera</span>
                </div>
            </div>
            <div class="card-body">
                <!-- Tampilkan pesan error jika ada -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <div class="form-group">
                        <label for="username"><i class="fas fa-user form-icon"></i> Username / NIP</label>
                        <input type="text" class="form-control" id="username" name="username" required
                               value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                    </div>
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock form-icon"></i> Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block mt-3">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
            </div>
        </div>
    </div>
    <!-- Script JavaScript -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
