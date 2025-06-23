<?php
require_once __DIR__ . '/helpers.php';

// Mulai session secara aman jika belum berjalan
start_session_safe();

// Pastikan pengguna sudah login (boleh semua role)
authorize(['P', 'TK', 'M', 'Kepala Sekolah']);

// Koneksi ke database
require_once __DIR__ . '/koneksi.php';

// Ambil NIP dari session sebagai identitas unik user
$userNip = $_SESSION['nip'] ?? '';
if (empty($userNip)) {
    echo "NIP tidak ditemukan dalam session.";
    exit();
}

// ================ BAGIAN AJAX UNTUK UPDATE PROFIL ================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // 1. Cek CSRF Token
        $csrf_token = $_POST['csrf_token'] ?? '';
        verify_csrf_token($csrf_token);

        // 2. Ambil data input dari form dan bersihkan
        $id                = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $nip               = bersihkan_input($_POST['nip'] ?? '');
        $nama              = bersihkan_input($_POST['nama'] ?? '');
        $jenis_kelamin     = bersihkan_input($_POST['jenis_kelamin'] ?? '');
        $agama             = bersihkan_input($_POST['agama'] ?? '');
        $tanggal_lahir     = bersihkan_input($_POST['tanggal_lahir'] ?? '');
        $no_hp             = bersihkan_input($_POST['no_hp'] ?? '');
        $email             = bersihkan_input($_POST['email'] ?? '');
        $alamat_ktp        = bersihkan_input($_POST['alamat_ktp'] ?? '');
        $alamat_domisili   = bersihkan_input($_POST['alamat_domisili'] ?? '');
        $status_perkawinan = bersihkan_input($_POST['status_perkawinan'] ?? '');
        $nama_pasangan     = bersihkan_input($_POST['nama_pasangan'] ?? '');
        $jumlah_anak       = isset($_POST['jumlah_anak']) ? intval($_POST['jumlah_anak']) : 0;
        $nama_anak_1       = bersihkan_input($_POST['nama_anak_1'] ?? '');
        $nama_anak_2       = bersihkan_input($_POST['nama_anak_2'] ?? '');
        $nama_anak_3       = bersihkan_input($_POST['nama_anak_3'] ?? '');
        $remark            = bersihkan_input($_POST['remark'] ?? '');
        $password_plain    = trim($_POST['password'] ?? '');

        // 3. Validasi minimal (NIP dan Nama wajib diisi)
        if (empty($nip) || empty($nama)) {
            send_response(1, 'NIP dan Nama wajib diisi.');
        }

        // 4. Validasi bahwa user yang diupdate adalah user yang sedang login
        $stmtLogged = $conn->prepare("SELECT id FROM anggota_sekolah WHERE nip=? LIMIT 1");
        if (!$stmtLogged) { send_response(1, 'Query error: '.$conn->error); }
        $stmtLogged->bind_param("s", $userNip);
        $stmtLogged->execute();
        $resLogged = $stmtLogged->get_result();
        if ($resLogged->num_rows === 0) {
            send_response(1, 'Data pengguna tidak ditemukan.');
        }
        $loggedUser = $resLogged->fetch_assoc();
        $stmtLogged->close();
        if ($id !== (int)$loggedUser['id']) {
            send_response(403, 'Anda tidak diizinkan mengubah data pengguna lain.');
        }

        // 5. Ambil jenjang untuk naming foto agar $jenjang tidak undefined
        $stmtJenjang = $conn->prepare("SELECT jenjang FROM anggota_sekolah WHERE id=?");
        $stmtJenjang->bind_param("i", $id);
        $stmtJenjang->execute();
        $resJenjang = $stmtJenjang->get_result();
        $jenjangRow  = $resJenjang->fetch_assoc();
        $stmtJenjang->close();
        $jenjang = $jenjangRow['jenjang'] ?? '';

        // 6. Proses upload foto profil (jika ada)
        $foto_profil_path = '';
        $uploadDir = __DIR__ . '/uploads/profile_pics/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
            $tmpName  = $_FILES['foto_profil']['tmp_name'];
            $imgInfo  = getimagesize($tmpName);
            if ($imgInfo === false) {
                send_response(1, 'File yang diunggah bukan gambar.');
            }
            switch ($imgInfo['mime']) {
                case 'image/jpeg': $image = imagecreatefromjpeg($tmpName); break;
                case 'image/png':  $image = imagecreatefrompng($tmpName);  break;
                case 'image/gif':  $image = imagecreatefromgif($tmpName);  break;
                default: send_response(1, 'Format gambar tidak didukung.');
            }
            $userName    = strtolower(preg_replace('/\s+/', '_', $nama));
            $userJenjang = strtolower(preg_replace('/\s+/', '_', $jenjang));
            $userRole    = strtolower($_SESSION['role']);
            $newName     = "{$userName}_{$userJenjang}_{$userRole}_{$id}.jpg";
            $destPath    = $uploadDir . $newName;
            if (imagejpeg($image, $destPath, 90)) {
                imagedestroy($image);
                $foto_profil_path = getBaseUrl() . '/uploads/profile_pics/' . $newName;
            } else {
                imagedestroy($image);
                send_response(1, 'Gagal mengonversi gambar ke format JPG.');
            }
        }

        // 7. Cek data user di tabel
        $stmtCheck = $conn->prepare("SELECT foto_profil FROM anggota_sekolah WHERE id=? LIMIT 1");
        $stmtCheck->bind_param("i", $id);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        if ($resCheck->num_rows === 0) {
            send_response(1, 'Data pengguna tidak ditemukan di tabel anggota_sekolah.');
        }
        $rowUser = $resCheck->fetch_assoc();
        $stmtCheck->close();
        $final_foto_profil = !empty($foto_profil_path) ? $foto_profil_path : $rowUser['foto_profil'];

        // 8. Handle password baru (opsional)
        $updatePassword = false;
        if (!empty($password_plain)) {
            $password_hashed = md5($password_plain);
            $updatePassword = true;
        }

        // 9. Siapkan dan eksekusi SQL UPDATE
        if ($updatePassword) {
            $sql = "UPDATE anggota_sekolah
                    SET nama=?, jenis_kelamin=?, agama=?, tanggal_lahir=?, no_hp=?,
                        email=?, alamat_ktp=?, alamat_domisili=?, status_perkawinan=?,
                        nama_pasangan=?, jumlah_anak=?, nama_anak_1=?, nama_anak_2=?, nama_anak_3=?,
                        remark=?, password=?, foto_profil=?
                    WHERE id=?";
            $stmtUpd = $conn->prepare($sql);
            $stmtUpd->bind_param(
                "ssssssssssisssssi",
                $nama, $jenis_kelamin, $agama, $tanggal_lahir, $no_hp,
                $email, $alamat_ktp, $alamat_domisili, $status_perkawinan,
                $nama_pasangan, $jumlah_anak, $nama_anak_1, $nama_anak_2, $nama_anak_3,
                $remark, $password_hashed, $final_foto_profil, $id
            );
        } else {
            $sql = "UPDATE anggota_sekolah
                    SET nama=?, jenis_kelamin=?, agama=?, tanggal_lahir=?, no_hp=?,
                        email=?, alamat_ktp=?, alamat_domisili=?, status_perkawinan=?,
                        nama_pasangan=?, jumlah_anak=?, nama_anak_1=?, nama_anak_2=?, nama_anak_3=?,
                        remark=?, foto_profil=?
                    WHERE id=?";
            $stmtUpd = $conn->prepare($sql);
            $stmtUpd->bind_param(
    "ssssssssssssssssi",
    $nama, $jenis_kelamin, $agama, $tanggal_lahir, $no_hp,
    $email, $alamat_ktp, $alamat_domisili, $status_perkawinan,
    $nama_pasangan, $jumlah_anak, $nama_anak_1, $nama_anak_2, $nama_anak_3,
    $remark, $final_foto_profil, $id
);

        }

        if ($stmtUpd->execute()) {
            $stmtUpd->close();
            // 10. Update session
            $_SESSION['nip']               = $nip;
            $_SESSION['nama']              = $nama;
            $_SESSION['jenis_kelamin']     = $jenis_kelamin;
            $_SESSION['agama']             = $agama;
            $_SESSION['tanggal_lahir']     = $tanggal_lahir;
            $_SESSION['no_hp']             = $no_hp;
            $_SESSION['email']             = $email;
            $_SESSION['alamat_ktp']        = $alamat_ktp;
            $_SESSION['alamat_domisili']   = $alamat_domisili;
            $_SESSION['status_perkawinan'] = $status_perkawinan;
            $_SESSION['nama_pasangan']     = $nama_pasangan;
            $_SESSION['jumlah_anak']       = $jumlah_anak;
            $_SESSION['nama_anak_1']       = $nama_anak_1;
            $_SESSION['nama_anak_2']       = $nama_anak_2;
            $_SESSION['nama_anak_3']       = $nama_anak_3;
            $_SESSION['remark']            = $remark;
            $_SESSION['foto_profil']       = $final_foto_profil;

            send_response(0, 'Data profil berhasil diperbarui.');
        } else {
            $err = $stmtUpd->error;
            $stmtUpd->close();
            send_response(1, 'Gagal memperbarui data: ' . $err);
        }
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    exit();
}


// ============= BAGIAN HALAMAN (BUKAN AJAX) =============
// Ambil data profil user dari tabel anggota_sekolah menggunakan NIP dari session
$sqlProfile = "SELECT * FROM anggota_sekolah WHERE nip=? LIMIT 1";
$stmt = $conn->prepare($sqlProfile);
if (!$stmt) {
    die("Query error: " . $conn->error);
}
$stmt->bind_param("s", $userNip);
$stmt->execute();
$resProfile = $stmt->get_result();
if ($resProfile->num_rows === 0) {
    die("Data user tidak ditemukan di anggota_sekolah.");
}
$profile = $resProfile->fetch_assoc();
$stmt->close();

// Buat CSRF token jika belum ada
generate_csrf_token();

// Format tanggal lahir
$tanggalLahirFormatted = '-';
if (!empty($profile['tanggal_lahir']) && $profile['tanggal_lahir'] !== '0000-00-00') {
    $tanggalLahirFormatted = date('d F Y', strtotime($profile['tanggal_lahir']));
}

// ======= MODIFIKASI: Menentukan URL foto profil =======
$baseUrl = getBaseUrl();
$fotoDb  = $profile['foto_profil'] ?? '';
$filename = basename($fotoDb);
$localPath = __DIR__ . '/uploads/profile_pics/' . $filename;

if (!empty($fotoDb) && strpos($fotoDb, 'http') === 0) {
    // Jika sudah URL absolut di DB
    $foto = $fotoDb;
} elseif ($filename && file_exists($localPath)) {
    // Jika file ada di folder uploads
    $foto = "{$baseUrl}/uploads/profile_pics/{$filename}?v=" . filemtime($localPath);
} else {
    // Fallback ke placeholder
    $foto = "{$baseUrl}/assets/img/undraw_profile.svg";
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Profile - Payroll System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap CSS & SB Admin 2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .profile-card {
            max-width: 1000px;
            margin: 20px auto;
        }

        /* ===== Page Title Styling ===== */
        .page-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 2.5rem;
            color: #0d47a1;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 3px solid #1976d2;
            padding-bottom: 0.3rem;
            margin-bottom: 1.5rem;
            animation: fadeInSlide 0.5s ease-in-out both;
        }

        .page-title i {
            color: #1976d2;
            font-size: 2.8rem;
        }

        .profile-img {
            width: 180px;
            height: 180px;
            object-fit: cover;
            border: 3px solid #4e73df;
            border-radius: 50%;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        @media (max-width: 576px) {
            .profile-img {
                width: 140px;
                height: 140px;
            }
        }

        .profile-data-row {
            margin-bottom: 0.75rem;
        }

        .profile-data-row .col-sm-4 {
            font-weight: 600;
        }
    </style>
</head>

<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/sidebar.php'; ?>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Navbar -->
                <?php include __DIR__ . '/navbar.php'; ?>
                <!-- End Navbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <!-- Heading Halaman -->
                    <h1 class="page-title">
                        <i class="fas fa-user-circle me-2"></i>Profil Saya
                    </h1>

                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEditProfile">
                        <i class="fas fa-user-edit fa-sm text-white-50"></i> Edit Profil
                    </button>
                    <!-- Kartu Profil -->
                    <div class="card profile-card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="m-0">
                                <i class="fas fa-info-circle me-2"></i>Informasi Profil
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- Foto Profil -->
                                <div class="col-md-4 text-center mb-3 d-flex flex-column align-items-center">
                                    <img src="<?= htmlspecialchars($foto); ?>"
                                        alt="Foto Profil"
                                        class="img-fluid rounded-circle profile-img mb-3">
                                    <h5 class="text-primary"><?= htmlspecialchars($profile['nama'] ?? ''); ?></h5>
                                    <span class="text-muted"><?= htmlspecialchars($profile['job_title'] ?? ''); ?></span>
                                    <div class="col-sm-8"><?= htmlspecialchars($profile['nip'] ?? ''); ?></div>
                                </div>

                                <!-- Data Profil -->
                                <div class="col-md-8">
                                    <div class="row">
                                        <!-- Kolom Kiri -->
                                        <div class="col-md-6">
                                            <h6 class="mb-2 text-primary">
                                                <i class="fas fa-id-card me-1"></i>Identitas
                                            </h6>
                                            <div class="row profile-data-row">
                                                <div class="col-sm-4">UID</div>
                                                <div class="col-sm-8"><?= htmlspecialchars($profile['uid']); ?></div>
                                            </div>
                                            <div class="row profile-data-row">
                                                <div class="col-sm-4">NIP</div>
                                                <div class="col-sm-8"><?= htmlspecialchars($profile['nip']); ?></div>
                                            </div>
                                            <div class="row profile-data-row">
                                                <div class="col-sm-4">JK</div>
                                                <div class="col-sm-8">
                                                    <?= $profile['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?>
                                                </div>
                                            </div>
                                            <div class="row profile-data-row">
                                                <div class="col-sm-4">Tgl. Lahir</div>
                                                <div class="col-sm-8"><?= $tanggalLahirFormatted; ?></div>
                                            </div>
                                            <?php if (!empty($profile['usia'])): ?>
                                                <div class="row profile-data-row">
                                                    <div class="col-sm-4">Usia</div>
                                                    <div class="col-sm-8"><?= htmlspecialchars($profile['usia']); ?> tahun</div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($profile['agama'])): ?>
                                                <div class="row profile-data-row">
                                                    <div class="col-sm-4">Agama</div>
                                                    <div class="col-sm-8"><?= htmlspecialchars($profile['agama']); ?></div>
                                                </div>
                                            <?php endif; ?>

                                            <hr class="my-3">

                                            <h6 class="mb-2 text-primary">
                                                <i class="fas fa-address-book me-1"></i>Kontak & Alamat
                                            </h6>
                                            <?php if (!empty($profile['email'])): ?>
                                                <div class="row profile-data-row">
                                                    <div class="col-sm-4">Email</div>
                                                    <div class="col-sm-8"><?= htmlspecialchars($profile['email']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($profile['no_hp'])): ?>
                                                <div class="row profile-data-row">
                                                    <div class="col-sm-4">No. HP</div>
                                                    <div class="col-sm-8"><?= htmlspecialchars($profile['no_hp']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($profile['alamat_domisili'])): ?>
                                                <div class="row profile-data-row">
                                                    <div class="col-sm-4">Domisili</div>
                                                    <div class="col-sm-8"><?= htmlspecialchars($profile['alamat_domisili']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($profile['alamat_ktp'])): ?>
                                                <div class="row profile-data-row">
                                                    <div class="col-sm-4">Alamat KTP</div>
                                                    <div class="col-sm-8"><?= htmlspecialchars($profile['alamat_ktp']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Kolom Kanan -->
                                        <div class="col-md-6">
                                            <h6 class="mb-2 text-primary">
                                                <i class="fas fa-briefcase me-1"></i>Informasi Profesional
                                            </h6>
                                            <?php if (!empty($profile['jenjang'])): ?>
                                                <div class="row profile-data-row">
                                                    <div class="col-sm-4">Jenjang</div>
                                                    <div class="col-sm-8"><?= htmlspecialchars($profile['jenjang']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($profile['status_kerja'])): ?>
                                                <div class="row profile-data-row">
                                                    <div class="col-sm-4">Status</div>
                                                    <div class="col-sm-8"><?= htmlspecialchars($profile['status_kerja']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($profile['join_start'])): ?>
                                                <div class="row profile-data-row">
                                                    <div class="col-sm-4">Join</div>
                                                    <div class="col-sm-8">
                                                        <?= date('d F Y', strtotime($profile['join_start'])); ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($profile['masa_kerja_tahun']) || !empty($profile['masa_kerja_bulan'])): ?>
                                                <div class="row profile-data-row">
                                                    <div class="col-sm-4">Masa Kerja</div>
                                                    <div class="col-sm-8">
                                                        <?= $profile['masa_kerja_tahun'] . ' th ' . $profile['masa_kerja_bulan'] . ' bln'; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($profile['remark'])): ?>
                                                <div class="row profile-data-row">
                                                    <div class="col-sm-4">Catatan</div>
                                                    <div class="col-sm-8"><?= htmlspecialchars($profile['remark']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($profile['pendidikan'])): ?>
                                                <div class="row profile-data-row">
                                                    <div class="col-sm-4">Pendidikan</div>
                                                    <div class="col-sm-8"><?= htmlspecialchars($profile['pendidikan']); ?></div>
                                                </div>
                                            <?php endif; ?>

                                            <hr class="my-3">

                                            <h6 class="mb-2 text-primary">
                                                <i class="fas fa-user-friends me-1"></i>Informasi Keluarga
                                            </h6>
                                            <?php if (!empty($profile['status_perkawinan'])): ?>
                                                <div class="row profile-data-row">
                                                    <div class="col-sm-4">Status</div>
                                                    <div class="col-sm-8"><?= htmlspecialchars($profile['status_perkawinan']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($profile['nama_pasangan'])): ?>
                                                <div class="row profile-data-row">
                                                    <div class="col-sm-4">Pasangan</div>
                                                    <div class="col-sm-8"><?= htmlspecialchars($profile['nama_pasangan']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (isset($profile['jumlah_anak'])): ?>
                                                <div class="row profile-data-row">
                                                    <div class="col-sm-4">Anak</div>
                                                    <div class="col-sm-8"><?= htmlspecialchars($profile['jumlah_anak']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($profile['nama_anak_1'])): ?>
                                                <div class="row profile-data-row">
                                                    <div class="col-sm-4">Anak 1</div>
                                                    <div class="col-sm-8"><?= htmlspecialchars($profile['nama_anak_1']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($profile['nama_anak_2'])): ?>
                                                <div class="row profile-data-row">
                                                    <div class="col-sm-4">Anak 2</div>
                                                    <div class="col-sm-8"><?= htmlspecialchars($profile['nama_anak_2']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($profile['nama_anak_3'])): ?>
                                                <div class="row profile-data-row">
                                                    <div class="col-sm-4">Anak 3</div>
                                                    <div class="col-sm-8"><?= htmlspecialchars($profile['nama_anak_3']); ?></div>
                                                </div>
                                            <?php endif; ?>

                                            <hr class="my-3">

                                            <h6 class="mb-3 text-primary">
                                                <i class="fas fa-money-bill-wave me-2"></i>Gaji & Role
                                            </h6>
                                            <div class="row profile-data-row">
                                                <div class="col-sm-4 fw-bold">Gaji Pokok:</div>
                                                <div class="col-sm-8">Rp <?= number_format($profile['gaji_pokok'], 2, ',', '.'); ?></div>
                                            </div>
                                            <div class="row profile-data-row text-nowrap">
                                                <div class="col-sm-4 fw-bold">Salary Index:</div>
                                                <div class="col-sm-8">
                                                    <?= htmlspecialchars($profile['salary_index_level'] ?? '-'); ?>
                                                </div>
                                            </div>
                                            <div class="row profile-data-row">
                                                <div class="col-sm-4 fw-bold">Role:</div>
                                                <div class="col-sm-8"><?= htmlspecialchars($profile['role']); ?></div>
                                            </div>

                                        </div>
                                    </div><!-- End row -->
                                </div><!-- End col-md-8 -->
                            </div><!-- End row -->
                        </div><!-- End Card Body -->
                    </div><!-- End Kartu Profil -->
                </div><!-- End Container Fluid -->
            </div><!-- End Main Content -->

            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?= date("Y"); ?> Payroll Management System</span>
                    </div>
                </div>
            </footer>
        </div><!-- End Content Wrapper -->
    </div><!-- End Page Wrapper -->

    <div class="modal fade" id="modalEditProfile" tabindex="-1" aria-labelledby="modalEditProfileLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form id="edit-profile-form" class="needs-validation" novalidate enctype="multipart/form-data">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="modalEditProfileLabel">
                            <i class="fas fa-user-edit me-2"></i>Edit Profil
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="container-fluid">
                            <!-- READONLY: Data Sistem -->
                            <h6 class="mb-2 text-primary"><i class="fas fa-id-card me-1"></i>Identitas Sistem</h6>
                            <div class="row mb-3">
                                <div class="col-md-2">
                                    <label class="form-label">UID</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($profile['uid'] ?? ''); ?>" readonly>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">NIP</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($profile['nip'] ?? ''); ?>" readonly>
                                    <input type="hidden" name="nip" value="<?= htmlspecialchars($profile['nip'] ?? ''); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Jenjang</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($profile['jenjang'] ?? ''); ?>" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Pekerjaan</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($profile['job_title'] ?? ''); ?>" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status Kerja</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($profile['status_kerja'] ?? ''); ?>" readonly>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Tanggal Join</label>
                                    <input type="text" class="form-control" value="<?= !empty($profile['join_start']) ? date('d F Y', strtotime($profile['join_start'])) : '-'; ?>" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Masa Kerja</label>
                                    <input type="text" class="form-control" value="<?= $profile['masa_kerja_tahun'] . ' tahun ' . $profile['masa_kerja_bulan'] . ' bulan'; ?>" readonly>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Gaji Pokok</label>
                                    <input type="text" class="form-control" value="Rp <?= number_format($profile['gaji_pokok'], 2, ',', '.'); ?>" readonly>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Salary Index</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($profile['salary_index_level'] ?? '-'); ?>" readonly>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($profile['role'] ?? ''); ?>" readonly>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Pendidikan</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($profile['pendidikan'] ?? ''); ?>" readonly>
                                    <input type="hidden" name="pendidikan" value="<?= htmlspecialchars($profile['pendidikan'] ?? ''); ?>">
                                </div>
                            </div>
                            <hr>

                            <!-- EDITABLE: Data Pribadi -->
                            <h6 class="mb-2 text-primary"><i class="fas fa-user me-1"></i>Data Pribadi</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="editNama" class="form-label">Nama <span class="text-danger">*</span></label>
                                    <input type="text" name="nama" id="editNama" class="form-control"
                                        value="<?= htmlspecialchars($profile['nama'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Nama wajib diisi.</div>
                                </div>
                                <div class="col-md-3">
                                    <label for="jenis_kelamin" class="form-label">Jenis Kelamin</label>
                                    <select name="jenis_kelamin" id="jenis_kelamin" class="form-control" required>
                                        <option value="">-- Pilih --</option>
                                        <option value="L" <?= ($profile['jenis_kelamin'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                        <option value="P" <?= ($profile['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="agama" class="form-label">Agama</label>
                                    <select name="agama" id="agama" class="form-control">
                                        <?php
                                        $daftarAgama = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Lainnya'];
                                        $selectedAgama = $profile['agama'] ?? '';
                                        foreach ($daftarAgama as $agama) {
                                            $selected = ($selectedAgama == $agama) ? 'selected' : '';
                                            echo "<option value=\"$agama\" $selected>$agama</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="tanggal_lahir" class="form-label">Tanggal Lahir</label>
                                    <input type="date" name="tanggal_lahir" id="tanggal_lahir" class="form-control"
                                        value="<?= htmlspecialchars($profile['tanggal_lahir'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="no_hp" class="form-label">No. HP</label>
                                    <input type="text" name="no_hp" id="no_hp" class="form-control"
                                        value="<?= htmlspecialchars($profile['no_hp'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" name="email" id="email" class="form-control"
                                        value="<?= htmlspecialchars($profile['email'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="alamat_ktp" class="form-label">Alamat KTP</label>
                                    <input type="text" name="alamat_ktp" id="alamat_ktp" class="form-control"
                                        value="<?= htmlspecialchars($profile['alamat_ktp'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="alamat_domisili" class="form-label">Alamat Domisili</label>
                                    <input type="text" name="alamat_domisili" id="alamat_domisili" class="form-control"
                                        value="<?= htmlspecialchars($profile['alamat_domisili'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="status_perkawinan" class="form-label">Status Perkawinan</label>
                                    <select name="status_perkawinan" id="status_perkawinan" class="form-control">
                                        <?php
                                        $daftarStatus = ['Belum Kawin', 'Kawin', 'Cerai Hidup', 'Cerai Mati'];
                                        $selectedStatus = $profile['status_perkawinan'] ?? '';
                                        foreach ($daftarStatus as $stat) {
                                            $selected = ($selectedStatus == $stat) ? 'selected' : '';
                                            echo "<option value=\"$stat\" $selected>$stat</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="nama_pasangan" class="form-label">Nama Pasangan</label>
                                    <input type="text" name="nama_pasangan" id="nama_pasangan" class="form-control"
                                        value="<?= htmlspecialchars($profile['nama_pasangan'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="jumlah_anak" class="form-label">Jumlah Anak</label>
                                    <input type="number" name="jumlah_anak" id="jumlah_anak" class="form-control"
                                        value="<?= htmlspecialchars($profile['jumlah_anak'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="nama_anak_1" class="form-label">Nama Anak 1</label>
                                    <input type="text" name="nama_anak_1" id="nama_anak_1" class="form-control"
                                        value="<?= htmlspecialchars($profile['nama_anak_1'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="nama_anak_2" class="form-label">Nama Anak 2</label>
                                    <input type="text" name="nama_anak_2" id="nama_anak_2" class="form-control"
                                        value="<?= htmlspecialchars($profile['nama_anak_2'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="nama_anak_3" class="form-label">Nama Anak 3</label>
                                    <input type="text" name="nama_anak_3" id="nama_anak_3" class="form-control"
                                        value="<?= htmlspecialchars($profile['nama_anak_3'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="remark" class="form-label">Catatan (Remark)</label>
                                    <input type="text" name="remark" id="remark" class="form-control"
                                        value="<?= htmlspecialchars($profile['remark'] ?? ''); ?>">
                                </div>
                            </div>
                            <hr>

                            <!-- Ubah Password & Foto Profil -->
                            <h6 class="mb-3 text-primary"><i class="fas fa-lock me-2"></i>Ubah Password & Foto</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="editPassword" class="form-label">Password Baru (opsional)</label>
                                    <input type="password" name="password" id="editPassword" class="form-control"
                                        placeholder="Kosongkan jika tidak ingin mengganti password">
                                </div>
                                <div class="col-md-6">
                                    <label for="foto_profil" class="form-label">Ganti Foto Profil (opsional)</label>
                                    <input type="file" name="foto_profil" id="foto_profil" class="form-control">
                                    <small class="text-muted">Maksimal 2MB (jpg/jpeg/png).</small>
                                </div>
                            </div>
                            <!-- Hidden input untuk CSRF token dan ID user -->
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($profile['id']); ?>">
                        </div><!-- /.container-fluid -->
                    </div><!-- /.modal-body -->
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            Update Profil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- JS Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            $('#edit-profile-form').on('submit', function(e) {
                e.preventDefault();
                var form = $(this)[0];
                if (!form.checkValidity()) {
                    e.stopPropagation();
                    $(this).addClass('was-validated');
                    return;
                }
                var formData = new FormData(form);
                $.ajax({
                    url: 'profile.php?ajax=1',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    processData: false,
                    contentType: false,
                    beforeSend: function() {
                        $('#edit-profile-form button[type="submit"]').prop('disabled', true);
                        $('#edit-profile-form .spinner-border').removeClass('d-none');
                    },
                    success: function(resp) {
                        $('#edit-profile-form button[type="submit"]').prop('disabled', false);
                        $('#edit-profile-form .spinner-border').addClass('d-none');
                        if (resp.code === 0) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil',
                                text: resp.result,
                                timer: 1500,
                                showConfirmButton: false
                            });
                            setTimeout(function() {
                                location.reload();
                            }, 1600);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: resp.result
                            });
                        }
                    },
                    error: function() {
                        $('#edit-profile-form button[type="submit"]').prop('disabled', false);
                        $('#edit-profile-form .spinner-border').addClass('d-none');
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Terjadi kesalahan saat mengupdate profil.'
                        });
                    }
                });
            });
        });
    </script>
</body>

</html>
<?php
$conn->close();
?>