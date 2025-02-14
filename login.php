<?php
// login.php
session_start();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/helpers.php'; // Pastikan helpers.php terinklusi

// Inisialisasi variabel pesan error
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
    // Ambil data dari form
    $nip_input = trim($_POST['username']); // disini username dianggap NIP
    $password_input = trim($_POST['password']);

    // Validasi input
    if (empty($nip_input) || empty($password_input)) {
        $error = "Username/NIP dan Password harus diisi.";
    } else {
        // Cari data di tabel anggota_sekolah berdasarkan NIP
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
                $_SESSION['nip'] = $row['nip'];
                $_SESSION['nama'] = $row['nama'];
                
                // Tentukan dashboard berdasarkan job_title dan role dari tabel anggota_sekolah
                $jobTitle = strtolower($row['job_title'] ?? '');
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
    <!-- Link Bootstrap 4 -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
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
