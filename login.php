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
            header("Location: superadmin/dashboard_superadmin.php");
            exit();
        case 'sdm':
            header("Location: sdm/dashboard_sdm.php");
            exit();
        case 'keuangan':
            header("Location: keuangan/dashboard_keuangan.php");
            exit();
        case 'guru':
            header("Location: guru/dashboard_guru.php");
            exit();
        case 'karyawan':
            header("Location: karyawan/dashboard_karyawan.php");
            exit();
        case 'kepala_sekolah':
            header("Location: kepalasekolah/dashboard_kepala_sekolah.php");
            exit();
        case 'M':  // Jika sudah login sebagai manajerial, arahkan ke halaman sesuai job_title
            $jobTitle = strtolower($_SESSION['job_title'] ?? '');
            if (strpos($jobTitle, 'superadmin') !== false) {
                header("Location: superadmin/dashboard_superadmin.php");
            } elseif (strpos($jobTitle, 'sdm') !== false) {
                header("Location: sdm/dashboard_sdm.php");
            } elseif (strpos($jobTitle, 'keuangan') !== false) {
                header("Location: keuangan/dashboard_keuangan.php");
            } elseif (strpos($jobTitle, 'kepala sekolah') !== false) {
                header("Location: kepalasekolah/dashboard_kepala_sekolah.php");
            } else {
                header("Location: logout.php");
            }
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
                
                // Tentukan role dan simpan job_title
                $jobTitle    = strtolower($row['job_title'] ?? '');
                $anggotaRole = $row['role'] ?? '';

                if ($anggotaRole === 'M') { // User manajerial
                    $_SESSION['role']      = 'M';
                    $_SESSION['job_title'] = $row['job_title'];
                    
                    add_audit_log($conn, $row['nip'], 'Login',
                        "Pengguna dengan NIP '{$row['nip']}' berhasil login sebagai M dengan job_title '{$row['job_title']}'."
                    );
                    if (strpos($jobTitle, 'superadmin') !== false) {
                        header("Location: superadmin/dashboard_superadmin.php");
                        exit();
                    } elseif (strpos($jobTitle, 'sdm') !== false) {
                        header("Location: sdm/dashboard_sdm.php");
                        exit();
                    } elseif (strpos($jobTitle, 'keuangan') !== false) {
                        header("Location: keuangan/dashboard_keuangan.php");
                        exit();
                    } elseif (strpos($jobTitle, 'kepala sekolah') !== false) {
                        header("Location: kepalasekolah/dashboard_kepala_sekolah.php");
                        exit();
                    } else {
                        $error = "Role managerial tidak dikenali.";
                        add_audit_log($conn, $row['nip'], 'LoginFailed', "Pengguna dengan NIP '{$row['nip']}' gagal login: Role managerial tidak dikenali.");
                    }
                } elseif (
                    strpos($jobTitle, 'guru') !== false ||
                    strpos($jobTitle, 'wali') !== false ||
                    $anggotaRole === 'P' ||
                    $anggotaRole === 'TK'
                ) {
                    $_SESSION['role'] = 'guru';
                    add_audit_log($conn, $row['nip'], 'Login', "Pengguna dengan NIP '{$row['nip']}' berhasil login sebagai guru.");
                    header("Location: guru/dashboard_guru.php");
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
    <!-- Google Font (contoh) -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:400,500,600&display=swap">

    <style>
        /* RESET & GLOBAL STYLE */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: row;
            /* Background gradient atau bisa diganti dengan background-image */
            background: linear-gradient(to right, #74c0fc, #4e73df);
        }
        

        /* BAGIAN KIRI (SPLIT SCREEN) */
        .left {
            flex: 1;
            position: relative;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            overflow: hidden;
        }
        /* Contoh: Menambahkan gambar background di sisi kiri */
        .left::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url('https://images.unsplash.com/photo-1603575448363-9229ef31cde6?ixlib=rb-4.0.3&auto=format&fit=crop&w=1470&q=80') center center / cover no-repeat;
            opacity: 0.75;
        }
        .left-content {
            position: relative;
            z-index: 1;
            max-width: 450px;
            text-align: center;
        }
        .left-content h1 {
            font-size: 2.2rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        .left-content p {
            font-size: 1rem;
            line-height: 1.6;
            opacity: 0.9;
        }

        /* BAGIAN KANAN (FORM LOGIN) */
        .right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            background: #f8f9fc;
        }

        /* CARD FORM */
        .login-form {
            width: 100%;
            max-width: 400px;
            border-radius: 15px;
            padding: 2rem;
            position: relative;
            text-align: center;
            /* Glassmorphism effect */
            background: rgba(255, 255, 255, 0.8);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* LOGO & TAGLINE */
        .logo-container {
            margin-bottom: 1.5rem;
        }
        .logo-container img {
            width: 90px;
            height: auto;
        }
        .tagline {
            font-size: 0.9rem;
            color: #555;
            margin-top: 0.5rem;
        }

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
            margin-bottom: 1.2rem;
        }
        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.3rem;
            display: inline-block;
        }

        /* INPUT DENGAN ICON */
        .icon-input-container {
            display: flex;
            align-items: center;
            border: 1px solid #d1d3e2;
            border-radius: 5px;
            background-color: #fff;
            height: 45px;
            padding: 0 10px;
        }
        .icon-input-container i {
            color: #888;
            font-size: 1rem;
            margin-right: 8px;
        }
        .icon-input-container .form-control {
            border: none;
            box-shadow: none;
            height: 100%;
            padding: 0;
        }
        .icon-input-container:focus-within {
            border-color: #4e73df;
            box-shadow: 0 0 5px rgba(78,115,223,0.3);
        }

        /* TOMBOL LOGIN */
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

        /* RESPONSIVE */
        @media(max-width: 768px) {
            .left {
                display: none;
            }
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

    <!-- BAGIAN KIRI -->
    <div class="left">
        <div class="left-content">
            <h1>Sistem Terpadu Sekolah Nusaputera</h1>
            <p>Selamat datang di portal login. Silakan masuk dengan NIP dan password Anda untuk mengakses sistem.</p>
        </div>
    </div>

    <!-- BAGIAN KANAN (FORM LOGIN) -->
    <div class="right">
        <div class="login-form">
            <!-- Logo -->
            <div class="logo-container">
                <!-- Ganti path logo sesuai kebutuhan -->
                <img src="assets/img/Logo.png" alt="Logo Sekolah Nusaputera">
                <div class="tagline">Sekolah Nusaputera</div>
            </div>

            <h2>Login</h2>

            <!-- TAMPILAN ERROR (JIKA ADA) -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- FORM LOGIN -->
            <form action="login.php" method="POST">
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
                            placeholder="Masukkan NIP Anda..."
                        >
                    </div>
                </div>

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
                            placeholder="Masukkan password Anda..."
                        >
                    </div>
                </div>

                <button type="submit" class="btn-login">Login</button>
            </form>
        </div>
    </div>

    <!-- Script (jika menggunakan Bootstrap) -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
