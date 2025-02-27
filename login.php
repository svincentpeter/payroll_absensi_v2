<?php
// Aktifkan error reporting untuk debugging (nonaktifkan di produksi)
error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start(); // Mulai output buffering harus diletakkan paling awal
session_start();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/helpers.php'; // Pastikan file ini tersedia

$error = '';

// Jika pengguna sudah login, langsung arahkan ke dashboard sesuai role
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
            header("Location: absensi/kepalasekolah/dashboard_kepala_sekolah.php");
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
                $_SESSION['id']   = $row['id']; 
                $_SESSION['nip']  = $row['nip'];
                $_SESSION['nama'] = $row['nama'];
                
                // Tentukan dashboard berdasarkan job_title dan role
                $jobTitle    = strtolower($row['job_title'] ?? '');
                $anggotaRole = $row['role'] ?? '';

                if ($anggotaRole === 'M') { // Role managerial
                    if (strpos($jobTitle, 'superadmin') !== false) {
                        $_SESSION['role'] = 'superadmin';
                        add_audit_log($conn, $row['nip'], 'Login', "Pengguna dengan NIP '{$row['nip']}' berhasil login sebagai superadmin.");
                        header("Location: payroll/superadmin/dashboard_superadmin.php");
                        exit();
                    } elseif (strpos($jobTitle, 'sdm') !== false) {
                        $_SESSION['role'] = 'sdm';
                        add_audit_log($conn, $row['nip'], 'Login', "Pengguna dengan NIP '{$row['nip']}' berhasil login sebagai sdm.");
                        header("Location: absensi/sdm/dashboard_sdm.php");
                        exit();
                    } elseif (strpos($jobTitle, 'keuangan') !== false) {
                        $_SESSION['role'] = 'keuangan';
                        add_audit_log($conn, $row['nip'], 'Login', "Pengguna dengan NIP '{$row['nip']}' berhasil login sebagai keuangan.");
                        header("Location: payroll/keuangan/dashboard_keuangan.php");
                        exit();
                    } elseif (strpos($jobTitle, 'kepala sekolah') !== false) {
                        $_SESSION['role'] = 'kepala_sekolah';
                        add_audit_log($conn, $row['nip'], 'Login', "Pengguna dengan NIP '{$row['nip']}' berhasil login sebagai kepala_sekolah.");
                        header("Location: absensi/kepalasekolah/dashboard_kepala_sekolah.php");
                        exit();
                    } else {
                        $error = "Role managerial tidak dikenali.";
                        add_audit_log($conn, $row['nip'], 'LoginFailed', "Pengguna dengan NIP '{$row['nip']}' gagal login: Role managerial tidak dikenali.");
                    }
                } elseif (
                    strpos($jobTitle, 'guru') !== false ||
                    strpos($jobTitle, 'karyawan') !== false ||
                    $anggotaRole === 'P' ||
                    $anggotaRole === 'TK'
                ) {
                    $_SESSION['role'] = 'guru';
                    add_audit_log($conn, $row['nip'], 'Login', "Pengguna dengan NIP '{$row['nip']}' berhasil login sebagai guru.");
                    header("Location: absensi/guru/dashboard_guru.php");
                    exit();
                } else {
                    $error = "Role anggota_sekolah tidak dikenali.";
                    add_audit_log($conn, $row['nip'], 'LoginFailed', "Pengguna dengan NIP '{$row['nip']}' gagal login: Role anggota_sekolah tidak dikenali.");
                }
            } else {
                $error = "Password salah.";
                add_audit_log($conn, $row['nip'], 'LoginFailed', "Pengguna dengan NIP '{$row['nip']}' gagal login: Password salah.");
            }
        } else {
            $error = "NIP tidak ditemukan.";
            add_audit_log($conn, NULL, 'LoginFailed', "Pengguna dengan NIP '{$nip_input}' gagal login: Tidak ditemukan di tabel anggota_sekolah.");
        }
    }

    // Jika terjadi error, simpan error di session dan redirect ke login.php
    if (!empty($error)) {
        $_SESSION['error'] = $error;
        header("Location: login.php");
        exit();
    }
}

// Ambil error dari session (jika ada) untuk ditampilkan
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

ob_end_flush(); // Akhiri output buffering
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Sekolah Nusaputera</title>
    <!-- Agar tampilan responsive di mobile -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Bootstrap CSS (opsional, jika Anda menggunakannya) -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome (untuk ikon) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

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
            overflow-x: hidden;
        }

        /* SPLIT SCREEN LAYOUT */
        .left {
            flex: 1;
            background: linear-gradient(135deg, #4e73df, #74c0fc);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
        }
        .left-content {
            max-width: 450px;
            text-align: center;
            color: #fff;
        }
        .left-content h1 {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            font-weight: bold;
        }
        .left-content p {
            font-size: 1.1rem;
            line-height: 1.6;
            opacity: 0.9;
        }

        .right {
            flex: 1;
            background: #f8f9fc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        /* LOGIN FORM CARD */
        .login-form {
            width: 100%;
            max-width: 400px;
            background: #fff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: box-shadow 0.4s ease, transform 0.3s ease;
        }

        .login-form:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.1), 0 0 15px rgba(78,115,223,0.5);
            transform: translateY(-3px);
        }

        /* Efek animasi radial background */
        .login-form::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at center, rgba(78,115,223,0.2), transparent 50%);
            animation: rotateGradient 6s linear infinite;
            z-index: -1;
        }

        @keyframes rotateGradient {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        /* Logo & Tagline */
        .logo-container {
            margin-bottom: 1rem;
        }
        .logo-container img {
            width: 100px;
            height: auto;
        }
        .tagline {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.5rem;
        }

        /* Judul */
        .login-form h2 {
            font-size: 1.6rem;
            margin-bottom: 1.5rem;
            color: #333;
            font-weight: 700;
        }

        /* ALERT ERROR */
        .alert-danger {
            color: #fff;
            background-color: #dc3545;
            border-color: #dc3545;
            font-weight: 600;
            margin-bottom: 1rem;
            border-radius: 5px;
            padding: 0.75rem 1rem;
        }

        /* FORM GROUP */
        .form-group {
            text-align: left;
            margin-bottom: 1.5rem; /* Spasi bawah antar field */
        }
        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
            display: inline-block;
        }

        /*
          PENTING:
          Di bawah ini adalah kunci pendekatan Flex 
          untuk ikon di sisi kiri input
        */
        .icon-input-container {
            display: flex;
            align-items: center;
            border: 1px solid #d1d3e2;
            border-radius: 5px;
            background-color: #fff;
            height: 45px;
            padding: 0 10px; /* Ruang kiri & kanan */
        }

        /* Style ikon */
        .icon-input-container i {
            color: #888;
            font-size: 1rem;
            margin-right: 8px; /* Jarak antara ikon & input */
        }

        /* Style input agar menyatu dengan container */
        .icon-input-container .form-control {
            border: none;
            box-shadow: none;
            height: 100%;
            padding: 0; /* Hilangkan padding default */
        }

        /* Efek fokus: highlight border container */
        .icon-input-container:focus-within {
            border-color: #4e73df;
            box-shadow: 0 0 5px rgba(78,115,223,0.3);
        }

        /* BUTTON */
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
            margin-top: 0.5rem;
            transition: background-color 0.3s ease, box-shadow 0.3s ease, transform 0.2s ease;
        }
        .btn-login:hover {
            background-color: #2e59d9;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transform: translateY(-2px);
        }

        /* RESPONSIVE DESIGN */
        @media(max-width: 768px) {
            /* Sembunyikan bagian kiri */
            .left {
                display: none;
            }
            /* Buat bagian kanan memenuhi lebar */
            .right {
                flex: unset;
                width: 100%;
                height: auto;
                padding: 1rem;
            }
            .login-form {
                margin: 0 auto;
                max-width: 400px;
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
            <!-- Logo -->
            <div class="logo-container">
                <img src="assets/img/Logo.png" alt="Logo Sekolah Nusaputera">
                <div class="tagline">Sekolah Nusaputera</div>
            </div>

            <h2>Login</h2>

            <!-- Contoh jika ada error -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Form Login -->
            <form action="login.php" method="POST">
                <!-- NIP -->
                <div class="form-group">
                    <label for="username">NIP</label>
                    <div class="icon-input-container">
                        <i class="fas fa-user"></i>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="username" 
                            name="username" 
                            required
                            value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                        >
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="icon-input-container">
                        <i class="fas fa-lock"></i>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            required
                        >
                    </div>
                </div>

                <!-- Tombol Submit -->
                <button type="submit" class="btn-login">Login</button>
            </form>
        </div>
    </div>

    <!-- Script (jika menggunakan Bootstrap) -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
