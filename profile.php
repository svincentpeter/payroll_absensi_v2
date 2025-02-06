<?php
// profile.php (fix)

session_start();

// Redirect jika pengguna belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: /payroll_absensi_v2/login.php");
    exit();
}

// Pastikan userId diambil dari session
$userId = $_SESSION['user_id'];

// Include koneksi database
require_once __DIR__ . '/koneksi.php';

/**
 * Fungsi untuk mengirim response JSON
 */
function send_response($code, $result) {
    header('Content-Type: application/json');
    echo json_encode(['code' => $code, 'result' => $result], JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Fungsi sederhana untuk membersihkan input
 */
function bersihkan_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Fungsi untuk memverifikasi CSRF token (sesuaikan implementasinya)
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        send_response(403, 'Token CSRF tidak valid.');
    }
}

// Proses AJAX untuk update profil
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verifikasi CSRF token
        $csrf_token = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';
        verify_csrf_token($csrf_token);

        // Ambil data input
        $id                = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $nip               = isset($_POST['nip']) ? trim($_POST['nip']) : '';
        $nama              = isset($_POST['nama']) ? trim($_POST['nama']) : '';
        $jenjang           = isset($_POST['jenjang']) ? trim($_POST['jenjang']) : '';
        $job_title         = isset($_POST['job_title']) ? trim($_POST['job_title']) : '';
        $no_hp             = isset($_POST['no_hp']) ? trim($_POST['no_hp']) : '';
        $email             = isset($_POST['email']) ? trim($_POST['email']) : '';
        $alamat_domisili   = isset($_POST['alamat_domisili']) ? trim($_POST['alamat_domisili']) : '';
        $tanggal_lahir     = isset($_POST['tanggal_lahir']) ? trim($_POST['tanggal_lahir']) : '';
        $pendidikan        = isset($_POST['pendidikan']) ? trim($_POST['pendidikan']) : '';
        $status_perkawinan = isset($_POST['status_perkawinan']) ? trim($_POST['status_perkawinan']) : '';

        // Jika pengguna mengupdate password (opsional)
        $password_plain = isset($_POST['password']) ? trim($_POST['password']) : '';
        $updatePassword = false;
        if (!empty($password_plain)) {
            // Gunakan MD5 atau sebaiknya gunakan password_hash untuk keamanan yang lebih baik
            $password_hashed = md5($password_plain);
            $updatePassword = true;
        }

        // Cek apakah data wajib (NIP dan Nama) telah diisi
        if (empty($nip) || empty($nama)) {
            send_response(1, 'NIP dan Nama wajib diisi.');
        }

        // Cek terlebih dahulu apakah data tersedia di tabel anggota_sekolah
        $stmt = $conn->prepare("SELECT id FROM anggota_sekolah WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $resultCheck = $stmt->get_result();
        $existsInAnggota = ($resultCheck && $resultCheck->num_rows > 0);
        $stmt->close();

        if ($existsInAnggota) {
            if ($updatePassword) {
                $sql = "UPDATE anggota_sekolah 
                        SET nip = ?, nama = ?, jenjang = ?, job_title = ?, no_hp = ?, email = ?, alamat_domisili = ?, tanggal_lahir = ?, pendidikan = ?, status_perkawinan = ?, password = ?
                        WHERE id = ?";
                $stmtUpdate = $conn->prepare($sql);
                if ($stmtUpdate === false) {
                    send_response(1, 'Query Error: ' . $conn->error);
                }
                $stmtUpdate->bind_param("sssssssssssi", $nip, $nama, $jenjang, $job_title, $no_hp, $email, $alamat_domisili, $tanggal_lahir, $pendidikan, $status_perkawinan, $password_hashed, $id);
            } else {
                $sql = "UPDATE anggota_sekolah 
                        SET nip = ?, nama = ?, jenjang = ?, job_title = ?, no_hp = ?, email = ?, alamat_domisili = ?, tanggal_lahir = ?, pendidikan = ?, status_perkawinan = ?
                        WHERE id = ?";
                $stmtUpdate = $conn->prepare($sql);
                if ($stmtUpdate === false) {
                    send_response(1, 'Query Error: ' . $conn->error);
                }
                $stmtUpdate->bind_param("ssssssssssi", $nip, $nama, $jenjang, $job_title, $no_hp, $email, $alamat_domisili, $tanggal_lahir, $pendidikan, $status_perkawinan, $id);
            }
        } else {
            // Jika data tidak ditemukan di anggota_sekolah, update di tabel users
            if ($updatePassword) {
                $sql = "UPDATE users 
                        SET username = ?, password = ?
                        WHERE id_user = ?";
                $stmtUpdate = $conn->prepare($sql);
                if ($stmtUpdate === false) {
                    send_response(1, 'Query Error: ' . $conn->error);
                }
                $stmtUpdate->bind_param("ssi", $nama, $password_hashed, $id);
            } else {
                $sql = "UPDATE users 
                        SET username = ?
                        WHERE id_user = ?";
                $stmtUpdate = $conn->prepare($sql);
                if ($stmtUpdate === false) {
                    send_response(1, 'Query Error: ' . $conn->error);
                }
                $stmtUpdate->bind_param("si", $nama, $id);
            }
        }

        if ($stmtUpdate->execute()) {
            // Setelah update berhasil, perbarui juga data di session agar konsisten dengan sidebar dan navbar
            $_SESSION['nama']             = $nama;
            $_SESSION['nip']              = $nip;
            $_SESSION['jenjang']          = $jenjang;
            $_SESSION['job_title']        = $job_title;
            $_SESSION['no_hp']            = $no_hp;
            $_SESSION['email']            = $email;
            $_SESSION['alamat_domisili']  = $alamat_domisili;
            $_SESSION['tanggal_lahir']    = $tanggal_lahir;
            $_SESSION['pendidikan']       = $pendidikan;
            $_SESSION['status_perkawinan']= $status_perkawinan;
            // Jika foto profil atau gaji_pokok diupdate lewat form, tambahkan pembaruan di sini

            send_response(0, 'Data profil berhasil diperbarui.');
        } else {
            send_response(1, 'Gagal memperbarui data: ' . $stmtUpdate->error);
        }
        $stmtUpdate->close();
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    exit();
}

// --- Jika bukan request AJAX, tampilkan halaman profile ---

/*
  Karena data pengguna (seperti nama, nip, dll.) sudah disimpan di session (seperti pada sidebar.php),
  kita akan menyusun array $profile berdasarkan nilai-nilai session tersebut.
  Jika ada data yang belum tersedia di session, Anda dapat menambahkan query database sebagai fallback.
*/
$profile = [
    'id'              => $userId,
    'nama'            => $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'User'),
    'nip'             => $_SESSION['nip'] ?? '',
    'jenjang'         => $_SESSION['jenjang'] ?? '',
    'job_title'       => $_SESSION['job_title'] ?? '',
    'no_hp'           => $_SESSION['no_hp'] ?? '',
    'email'           => $_SESSION['email'] ?? '',
    'alamat_domisili' => $_SESSION['alamat_domisili'] ?? '',
    'tanggal_lahir'   => $_SESSION['tanggal_lahir'] ?? '',
    'pendidikan'      => $_SESSION['pendidikan'] ?? '',
    'status_perkawinan'=> $_SESSION['status_perkawinan'] ?? '',
    'foto_profil'     => $_SESSION['foto_profil'] ?? 'img/undraw_profile.svg',
    'gaji_pokok'      => $_SESSION['gaji_pokok'] ?? ''
];

// Format tanggal lahir (jika ada dan valid)
$tanggalLahir = (isset($profile['tanggal_lahir']) && $profile['tanggal_lahir'] && $profile['tanggal_lahir'] !== '0000-00-00')
                ? date('d F Y', strtotime($profile['tanggal_lahir']))
                : '-';

// Buat CSRF token jika belum ada
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Profile - Payroll System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- CSS SB Admin 2 & Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <!-- Custom styles for SB Admin 2 -->
    <link href="/payroll_absensi_v2/assets/css/sb-admin-2.min.css" rel="stylesheet">
    <style>
        .profile-card {
            max-width: 800px;
            margin: 20px auto;
        }
        .profile-card .profile-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
        }
    </style>
</head>
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/sidebar.php'; ?>
        <!-- End Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Navbar -->
                <?php include __DIR__ . '/navbar.php'; ?>
                <!-- End Navbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Profile Saya</h1>
                        <button class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#modalEditProfile">
                            <i class="fas fa-user-edit fa-sm text-white-50"></i> Edit Profile
                        </button>
                    </div>

                    <!-- Profile Card -->
                    <div class="card profile-card shadow mb-4">
                        <div class="card-body">
                            <div class="row">
                                <!-- Foto Profil -->
                                <div class="col-md-3 text-center">
                                    <img src="<?= htmlspecialchars($profile['foto_profil']); ?>" alt="Foto Profil" class="img-profile rounded-circle profile-img mb-3">
                                </div>
                                <!-- Informasi Profil -->
                                <div class="col-md-9">
                                    <h3><?= htmlspecialchars($profile['nama']); ?></h3>
                                    <?php if (!empty($profile['nip'])): ?>
                                        <p><strong>NIP:</strong> <?= htmlspecialchars($profile['nip']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['job_title'])): ?>
                                        <p><strong>Job Title:</strong> <?= htmlspecialchars($profile['job_title']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['jenjang'])): ?>
                                        <p><strong>Jenjang:</strong> <?= htmlspecialchars($profile['jenjang']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['no_hp'])): ?>
                                        <p><strong>No. HP:</strong> <?= htmlspecialchars($profile['no_hp']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['email'])): ?>
                                        <p><strong>Email:</strong> <?= htmlspecialchars($profile['email']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['alamat_domisili'])): ?>
                                        <p><strong>Alamat Domisili:</strong> <?= htmlspecialchars($profile['alamat_domisili']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['tanggal_lahir'])): ?>
                                        <p><strong>Tanggal Lahir:</strong> <?= $tanggalLahir; ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['pendidikan'])): ?>
                                        <p><strong>Pendidikan:</strong> <?= htmlspecialchars($profile['pendidikan']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['status_perkawinan'])): ?>
                                        <p><strong>Status Pernikahan:</strong> <?= htmlspecialchars($profile['status_perkawinan']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['gaji_pokok'])): ?>
                                        <p><strong>Gaji Pokok:</strong> Rp <?= number_format($profile['gaji_pokok'], 2, ',', '.'); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- End Profile Card -->
                </div>
                <!-- End Container Fluid -->
            </div>
            <!-- End Main Content -->

            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?= date("Y") ?> Payroll Management System</span>
                    </div>
                </div>
            </footer>
        </div>
        <!-- End Content Wrapper -->
    </div>
    <!-- End Page Wrapper -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- MODAL: Edit Profile -->
    <div class="modal fade" id="modalEditProfile" tabindex="-1" role="dialog" aria-labelledby="modalEditProfileLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form id="edit-profile-form" class="needs-validation" novalidate>
          <input type="hidden" name="case" value="UpdateProfile">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="id" value="<?= htmlspecialchars($profile['id']); ?>">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="modalEditProfileLabel">Edit Profile Saya</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                <span>&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <!-- Row 1: NIP dan Nama -->
              <div class="form-row">
                <div class="form-group col-md-6">
                  <label for="editNip">NIP</label>
                  <input type="text" name="nip" id="editNip" class="form-control" value="<?= htmlspecialchars($profile['nip'] ?? ''); ?>" required>
                  <div class="invalid-feedback">NIP wajib diisi.</div>
                </div>
                <div class="form-group col-md-6">
                  <label for="editNama">Nama</label>
                  <input type="text" name="nama" id="editNama" class="form-control" value="<?= htmlspecialchars($profile['nama']); ?>" required>
                  <div class="invalid-feedback">Nama wajib diisi.</div>
                </div>
              </div>
              <!-- Row 2: Jenjang dan Job Title -->
              <div class="form-row">
                <div class="form-group col-md-6">
                  <label for="editJenjang">Jenjang</label>
                  <select name="jenjang" id="editJenjang" class="form-control" required>
                    <option value="">-- Pilih Jenjang --</option>
                    <option value="TK" <?= (isset($profile['jenjang']) && $profile['jenjang'] == 'TK') ? 'selected' : ''; ?>>TK</option>
                    <option value="SD" <?= (isset($profile['jenjang']) && $profile['jenjang'] == 'SD') ? 'selected' : ''; ?>>SD</option>
                    <option value="SMP" <?= (isset($profile['jenjang']) && $profile['jenjang'] == 'SMP') ? 'selected' : ''; ?>>SMP</option>
                    <option value="SMA" <?= (isset($profile['jenjang']) && $profile['jenjang'] == 'SMA') ? 'selected' : ''; ?>>SMA</option>
                    <option value="SMK" <?= (isset($profile['jenjang']) && $profile['jenjang'] == 'SMK') ? 'selected' : ''; ?>>SMK</option>
                  </select>
                </div>
                <div class="form-group col-md-6">
                  <label for="editJobTitle">Job Title</label>
                  <input type="text" name="job_title" id="editJobTitle" class="form-control" value="<?= htmlspecialchars($profile['job_title'] ?? ''); ?>">
                </div>
              </div>
              <!-- Row 3: No. HP dan Email -->
              <div class="form-row">
                <div class="form-group col-md-6">
                  <label for="editNoHP">No. HP</label>
                  <input type="text" name="no_hp" id="editNoHP" class="form-control" value="<?= htmlspecialchars($profile['no_hp'] ?? ''); ?>">
                </div>
                <div class="form-group col-md-6">
                  <label for="editEmail">Email</label>
                  <input type="email" name="email" id="editEmail" class="form-control" value="<?= htmlspecialchars($profile['email'] ?? ''); ?>">
                </div>
              </div>
              <!-- Row 4: Alamat Domisili dan Tanggal Lahir -->
              <div class="form-row">
                <div class="form-group col-md-6">
                  <label for="editAlamatDomisili">Alamat Domisili</label>
                  <textarea name="alamat_domisili" id="editAlamatDomisili" class="form-control" rows="2"><?= htmlspecialchars($profile['alamat_domisili'] ?? ''); ?></textarea>
                </div>
                <div class="form-group col-md-6">
                  <label for="editTanggalLahir">Tanggal Lahir</label>
                  <input type="date" name="tanggal_lahir" id="editTanggalLahir" class="form-control" value="<?= htmlspecialchars($profile['tanggal_lahir'] ?? ''); ?>">
                </div>
              </div>
              <!-- Row 5: Pendidikan dan Status Pernikahan -->
              <div class="form-row">
                <div class="form-group col-md-6">
                  <label for="editPendidikan">Pendidikan</label>
                  <input type="text" name="pendidikan" id="editPendidikan" class="form-control" value="<?= htmlspecialchars($profile['pendidikan'] ?? ''); ?>">
                </div>
                <div class="form-group col-md-6">
                  <label for="editStatusPernikahan">Status Pernikahan</label>
                  <select name="status_perkawinan" id="editStatusPernikahan" class="form-control">
                    <option value="">-- Pilih Status --</option>
                    <option value="Menikah" <?= (isset($profile['status_perkawinan']) && $profile['status_perkawinan'] == 'Menikah') ? 'selected' : ''; ?>>Menikah</option>
                    <option value="Belum Menikah" <?= (isset($profile['status_perkawinan']) && $profile['status_perkawinan'] == 'Belum Menikah') ? 'selected' : ''; ?>>Belum Menikah</option>
                    <option value="Duda" <?= (isset($profile['status_perkawinan']) && $profile['status_perkawinan'] == 'Duda') ? 'selected' : ''; ?>>Duda</option>
                    <option value="Janda" <?= (isset($profile['status_perkawinan']) && $profile['status_perkawinan'] == 'Janda') ? 'selected' : ''; ?>>Janda</option>
                  </select>
                </div>
              </div>
              <!-- Row 6: Password Baru (full width) -->
              <div class="form-group">
                <label for="editPassword">Password Baru (Opsional)</label>
                <input type="password" name="password" id="editPassword" class="form-control" placeholder="Isi jika ingin mengubah password">
                <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah password.</small>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-primary">
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                Update Profile
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- SweetAlert2 dan JS dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        // Fungsi untuk menampilkan SweetAlert2 Toast
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
        function showToast(message, icon = 'success') {
            Toast.fire({ icon: icon, title: message });
        }

        // Proses submit form Edit Profile via AJAX
        $('#edit-profile-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            var formData = form.serialize();
            $.ajax({
                url: "profile.php?ajax=1",
                type: "POST",
                data: formData,
                dataType: "json",
                beforeSend: function(){
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success: function(response) {
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if(response.code == 0) {
                        showToast(response.result, 'success');
                        $('#modalEditProfile').modal('hide');
                        // Reload halaman untuk menampilkan data terbaru
                        setTimeout(function(){ location.reload(); }, 1500);
                    } else {
                        showToast(response.result, 'error');
                    }
                },
                error: function() {
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat mengupdate profil.', 'error');
                }
            });
        });
    });
    </script>
</body>
</html>
