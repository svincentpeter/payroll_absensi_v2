<?php
// ---------------------------
// Bagian PHP Tetap Sama
// ---------------------------
session_start();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/helpers.php'; // Pastikan helpers.php terinklusi

$error = '';

// Jika pengguna sudah login, langsung arahkan ke dashboard sesuai role yang tersimpan di session
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
        case 'kepala_sekolah':
            header("Location: dashboard_kepala_sekolah.php");
            exit();
        default:
            header("Location: logout.php");
            exit();
    }
}

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nip_input      = trim($_POST['username']);
    $password_input = trim($_POST['password']);

    if (empty($nip_input) || empty($password_input)) {
        $error = "Username/NIP dan Password harus diisi.";
    } else {
        $stmt = $conn->prepare("SELECT id, nip, nama, password, job_title, role FROM anggota_sekolah WHERE nip = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $nip_input);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
        } else {
            error_log("Gagal menyiapkan statement: " . $conn->error);
            $error = "Terjadi kesalahan saat memproses permintaan Anda. Silakan coba lagi.";
            add_audit_log($conn, NULL, 'LoginError', "Gagal menyiapkan statement pada login anggota_sekolah: " . $conn->error);
        }

        if ($row) {
            // Verifikasi password dengan MD5
            if (md5($password_input) === $row['password']) {
                // Simpan data di session
                $_SESSION['nip']  = $row['nip'];
                $_SESSION['nama'] = $row['nama'];
                
                // Tentukan dashboard berdasarkan job_title dan role
                $jobTitle    = strtolower($row['job_title'] ?? '');
                $anggotaRole = $row['role'] ?? '';

                if ($anggotaRole === 'M') { // Role managerial
                    if (strpos($jobTitle, 'superadmin') !== false) {
                        $_SESSION['role'] = 'superadmin';
                        add_audit_log($conn, $row['id'], 'Login', "Pengguna dengan NIP '{$row['nip']}' berhasil login sebagai superadmin.");
                        header("Location: payroll/superadmin/dashboard_superadmin.php");
                        exit();
                    } elseif (strpos($jobTitle, 'sdm') !== false) {
                        $_SESSION['role'] = 'sdm';
                        add_audit_log($conn, $row['id'], 'Login', "Pengguna dengan NIP '{$row['nip']}' berhasil login sebagai sdm.");
                        header("Location: absensi/sdm/dashboard_sdm.php");
                        exit();
                    } elseif (strpos($jobTitle, 'keuangan') !== false) {
                        $_SESSION['role'] = 'keuangan';
                        add_audit_log($conn, $row['id'], 'Login', "Pengguna dengan NIP '{$row['nip']}' berhasil login sebagai keuangan.");
                        header("Location: payroll/keuangan/dashboard_keuangan.php");
                        exit();
                    } elseif (strpos($jobTitle, 'kepala sekolah') !== false) {
                        $_SESSION['role'] = 'kepala_sekolah';
                        add_audit_log($conn, $row['id'], 'Login', "Pengguna dengan NIP '{$row['nip']}' berhasil login sebagai kepala_sekolah.");
                        header("Location: dashboard_kepala_sekolah.php");
                        exit();
                    } else {
                        $error = "Role managerial tidak dikenali.";
                        add_audit_log($conn, $row['id'], 'LoginFailed', "Pengguna dengan NIP '{$row['nip']}' gagal login: Role managerial tidak dikenali.");
                    }
                } elseif (
                    strpos($jobTitle, 'guru') !== false ||
                    strpos($jobTitle, 'karyawan') !== false ||
                    $anggotaRole === 'P' ||
                    $anggotaRole === 'TK'
                ) {
                    $_SESSION['role'] = 'guru';
                    add_audit_log($conn, $row['id'], 'Login', "Pengguna dengan NIP '{$row['nip']}' berhasil login sebagai guru.");
                    header("Location: absensi/guru/dashboard_guru.php");
                    exit();
                } else {
                    $error = "Role anggota_sekolah tidak dikenali.";
                    add_audit_log($conn, $row['id'], 'LoginFailed', "Pengguna dengan NIP '{$row['nip']}' gagal login: Role anggota_sekolah tidak dikenali.");
                }
            } else {
                $error = "Password salah.";
                add_audit_log($conn, $row['id'], 'LoginFailed', "Pengguna dengan NIP '{$row['nip']}' gagal login: Password salah.");
            }
        } else {
            $error = "NIP tidak ditemukan.";
            add_audit_log($conn, NULL, 'LoginFailed', "Pengguna dengan NIP '{$nip_input}' gagal login: Tidak ditemukan di tabel anggota_sekolah.");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Sekolah Nusaputera</title>
    <!-- Bootstrap (Opsional) -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* RESET & GLOBAL STYLE */
        * {
            margin: 0; 
            padding: 0; 
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', sans-serif;
            min-height: 100vh;
            display: flex;
        }

        /* SPLIT SCREEN LAYOUT */
        .left {
            flex: 1;
            /* Gunakan warna biru gradient seperti tema sebelumnya */
            background: linear-gradient(45deg, #4e73df, rgb(172, 234, 255));
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .left-content {
            max-width: 400px;
            text-align: center;
            color: #fff;
        }
        .left-content h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
            font-weight: bold;
        }
        .left-content p {
            font-size: 1rem;
            line-height: 1.5;
            opacity: 0.9;
        }

        .right {
            flex: 1;
            background: #f8f9fc; /* Latar form, senada dengan biru/abu */
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        /* LOGIN FORM STYLE */
        .login-form {
            width: 100%;
            max-width: 380px;
            background: #fff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            text-align: center; /* Untuk memusatkan logo */
        }
        /* Tempat untuk logo */
        .logo-container {
            margin-bottom: 1rem;
        }
        .logo-container img {
            width: 80px; /* Sesuaikan ukuran logo */
            height: auto;
        }

        .login-form h2 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: #333;
        }
        .form-group label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #444;
            float: left; /* label rata kiri */
        }
        .form-control {
            border-radius: 5px;
            margin-bottom: 1rem;
            height: 45px;
            font-size: 0.9rem;
            border: 1px solid #d1d3e2;
        }
        .form-control:focus {
            outline: none;
            border-color: #4e73df;
            box-shadow: 0 0 5px rgba(78,115,223,0.3);
        }

        /* Tombol Login */
        .btn-login {
            width: 100%;
            height: 45px;
            border: none;
            border-radius: 20px;
            background-color: #4e73df;
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s ease;
            margin-top: 0.5rem;
        }
        .btn-login:hover {
            background-color: #2e59d9;
        }

        /* ALERT */
        .alert {
            margin-bottom: 1rem;
            border-radius: 5px;
        }

        /* RESPONSIVE */
        @media(max-width: 768px) {
            body {
                flex-direction: column;
            }
            .left, .right {
                flex: unset;
                width: 100%;
                height: auto;
            }
            .left {
                padding: 1rem;
            }
            .right {
                padding: 1rem;
            }
            .left-content {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- Bagian kiri (Welcome section) -->
    <div class="left">
        <div class="left-content">
            <h1>Welcome to website</h1>
            <p>
                Lorem ipsum dolor sit amet, consectetur adipiscing elit. 
                Sed diam nonummy nibh euismod tincidunt ut laoreet dolore 
                magna aliquam erat volutpat.
            </p>
        </div>
    </div>

    <!-- Bagian kanan (Login Form) -->
    <div class="right">
        <div class="login-form">
            <!-- Tempat untuk logo -->
            <div class="logo-container">
                <!-- Pastikan path logo sesuai dengan file Anda -->
                <img src="assets/img/Logo.png" alt="Logo Sekolah Nusaputera">
            </div>

            <h2>User Login</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group text-left">
                    <label for="username">Username / NIP</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>" 
                           required>
                </div>
                <div class="form-group text-left">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>

                <!-- Tombol Login -->
                <button type="submit" class="btn-login">Login</button>
            </form>
        </div>
    </div>

    <!-- Script (jika menggunakan Bootstrap) -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
