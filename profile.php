<?php
require_once __DIR__ . '/helpers.php';

// Mulai session (aman) jika belum berjalan
start_session_safe();

// Pastikan pengguna sudah login dan memiliki role yang diizinkan
authorize(['P', 'TK', 'M']);

// Koneksi ke database
require_once __DIR__ . '/koneksi.php';

// Ambil nip dari session sebagai identitas unik user
$userNip = $_SESSION['nip'] ?? '';

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
        $jenjang           = bersihkan_input($_POST['jenjang'] ?? '');
        $job_title         = bersihkan_input($_POST['job_title'] ?? '');
        $no_hp             = bersihkan_input($_POST['no_hp'] ?? '');
        $email             = bersihkan_input($_POST['email'] ?? '');
        $alamat_domisili   = bersihkan_input($_POST['alamat_domisili'] ?? '');
        $tanggal_lahir     = bersihkan_input($_POST['tanggal_lahir'] ?? '');
        $pendidikan        = bersihkan_input($_POST['pendidikan'] ?? '');
        $status_perkawinan = bersihkan_input($_POST['status_perkawinan'] ?? '');
        $password_plain    = trim($_POST['password'] ?? '');

        // 3. Validasi minimal (NIP dan Nama wajib diisi)
        if (empty($nip) || empty($nama)) {
            send_response(1, 'NIP dan Nama wajib diisi.');
        }

        // 4. Validasi bahwa user yang diupdate adalah user yang sedang login
        $sqlLogged = "SELECT id FROM anggota_sekolah WHERE nip=? LIMIT 1";
        $stmtLogged = $conn->prepare($sqlLogged);
        if (!$stmtLogged) {
            send_response(1, 'Query error: ' . $conn->error);
        }
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

        // 5. Proses upload foto profil (jika ada)
$foto_profil_path = '';
$uploadDir = __DIR__ . '/uploads/profile_pics/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
    $tmpName  = $_FILES['foto_profil']['tmp_name'];
    $origName = basename($_FILES['foto_profil']['name']);
    $ext      = pathinfo($origName, PATHINFO_EXTENSION);

    // Buat nama file baru dengan format: [nama]_[jenjang]_[role]_[id].[ext]
    $userName    = strtolower(preg_replace('/\s+/', '_', $nama));
    $userJenjang = strtolower(preg_replace('/\s+/', '_', $jenjang));
    $userRole    = strtolower($_SESSION['role']); // Misal: P, TK, atau M
    $newName     = "{$userName}_{$userJenjang}_{$userRole}_{$id}." . strtolower($ext);
    $destPath    = $uploadDir . $newName;

    if (move_uploaded_file($tmpName, $destPath)) {
        // Path yang disimpan di database
        $foto_profil_path = BASE_URL . '/uploads/profile_pics/' . $newName;
    } else {
        send_response(1, 'Gagal upload foto profil.');
    }
}


        // 6. Cek apakah data user ada di tabel anggota_sekolah
        $checkSql = "SELECT id, foto_profil FROM anggota_sekolah WHERE id=? LIMIT 1";
        $stmtCheck = $conn->prepare($checkSql);
        if (!$stmtCheck) {
            send_response(1, 'Query error: ' . $conn->error);
        }
        $stmtCheck->bind_param("i", $id);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        if ($resCheck->num_rows === 0) {
            send_response(1, 'Data pengguna tidak ditemukan di tabel anggota_sekolah.');
        }
        $rowUser = $resCheck->fetch_assoc();
        $stmtCheck->close();

        // 7. Siapkan kolom foto_profil akhir
        $final_foto_profil = $rowUser['foto_profil'];
        if (!empty($foto_profil_path)) {
            $final_foto_profil = $foto_profil_path;
        }

        // 8. Handle password baru (opsional)
        $updatePassword = false;
        $password_hashed = '';
        if (!empty($password_plain)) {
            $password_hashed = md5($password_plain); // Contoh: MD5, sebaiknya gunakan password_hash() di production
            $updatePassword = true;
        }

        // 9. Buat SQL UPDATE
        if ($updatePassword) {
            $updateSql = "UPDATE anggota_sekolah
                          SET nip=?, nama=?, jenjang=?, job_title=?,
                              no_hp=?, email=?, alamat_domisili=?,
                              tanggal_lahir=?, pendidikan=?, status_perkawinan=?,
                              password=?, foto_profil=?
                          WHERE id=?";
            $stmtUpd = $conn->prepare($updateSql);
            if (!$stmtUpd) {
                send_response(1, 'Query error: ' . $conn->error);
            }
            $stmtUpd->bind_param(
                "sssssssssssis",
                $nip, $nama, $jenjang, $job_title,
                $no_hp, $email, $alamat_domisili,
                $tanggal_lahir, $pendidikan, $status_perkawinan,
                $password_hashed, $final_foto_profil,
                $id
            );
        } else {
            $updateSql = "UPDATE anggota_sekolah
                          SET nip=?, nama=?, jenjang=?, job_title=?,
                              no_hp=?, email=?, alamat_domisili=?,
                              tanggal_lahir=?, pendidikan=?, status_perkawinan=?,
                              foto_profil=?
                          WHERE id=?";
            $stmtUpd = $conn->prepare($updateSql);
            if (!$stmtUpd) {
                send_response(1, 'Query error: ' . $conn->error);
            }
            $stmtUpd->bind_param(
                "sssssssssssi",
                $nip, $nama, $jenjang, $job_title,
                $no_hp, $email, $alamat_domisili,
                $tanggal_lahir, $pendidikan, $status_perkawinan,
                $final_foto_profil,
                $id
            );
        }

        // 10. Eksekusi update data
        if ($stmtUpd->execute()) {
            $stmtUpd->close();

            // 11. Perbarui data session
            $_SESSION['nip']               = $nip;
            $_SESSION['nama']              = $nama;
            $_SESSION['jenjang']           = $jenjang;
            $_SESSION['job_title']         = $job_title;
            $_SESSION['no_hp']             = $no_hp;
            $_SESSION['email']             = $email;
            $_SESSION['alamat_domisili']   = $alamat_domisili;
            $_SESSION['tanggal_lahir']     = $tanggal_lahir;
            $_SESSION['pendidikan']        = $pendidikan;
            $_SESSION['status_perkawinan'] = $status_perkawinan;
            $_SESSION['foto_profil']       = $final_foto_profil;

            send_response(0, 'Data profil berhasil diperbarui.');
        } else {
            $stmtUpd->close();
            send_response(1, 'Gagal memperbarui data: ' . $stmtUpd->error);
        }
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    exit();
}

// ============= BAGIAN HALAMAN (BUKAN AJAX) =============
// Ambil data profil user dari tabel anggota_sekolah menggunakan nip dari session
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

// Siapkan path foto profil (gunakan default jika kosong)
$foto_profil = $profile['foto_profil'];
if (empty($foto_profil)) {
    // Anda bisa gunakan path default dengan BASE_URL juga
    $foto_profil = BASE_URL . '/assets/img/undraw_profile.svg';
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
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/sb-admin-2.min.css">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* Styling Card Profil */
        .profile-card {
            max-width: 900px;
            margin: 20px auto;
        }
        .profile-img {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border: 3px solid #4e73df;
            border-radius: 50%;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        /* Responsiveness untuk gambar */
        @media (max-width: 576px) {
            .profile-img {
                width: 150px;
                height: 150px;
            }
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
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Profil Saya</h1>
                        <!-- Tombol Edit Profil di Heading Halaman -->
<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEditProfile">
    <i class="fas fa-user-edit fa-sm text-white-50"></i> Edit Profil
</button>
                    </div>

                    <!-- Kartu Profil (Informasi Profil) -->
                    <div class="card profile-card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="m-0">Informasi Profil</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- Foto Profil -->
                                <div class="col-md-4 text-center mb-3">
                                    <img src="<?= htmlspecialchars($foto_profil); ?>" 
                                         alt="Foto Profil" 
                                         class="img-fluid rounded-circle profile-img">
                                </div>
                                <!-- Data Profil -->
                                <div class="col-md-8">
                                    <div class="row mb-2">
                                        <div class="col-sm-4 font-weight-bold">Nama:</div>
                                        <div class="col-sm-8"><?= htmlspecialchars($profile['nama']); ?></div>
                                    </div>
                                    <hr>
                                    <div class="row mb-2">
                                        <div class="col-sm-4 font-weight-bold">UID:</div>
                                        <div class="col-sm-8"><?= htmlspecialchars($profile['uid']); ?></div>
                                    </div>
                                    <hr>
                                    <div class="row mb-2">
                                        <div class="col-sm-4 font-weight-bold">NIP:</div>
                                        <div class="col-sm-8"><?= htmlspecialchars($profile['nip']); ?></div>
                                    </div>
                                    <hr>
                                    <div class="row mb-2">
                                        <div class="col-sm-4 font-weight-bold">Role:</div>
                                        <div class="col-sm-8"><?= htmlspecialchars($profile['role']); ?></div>
                                    </div>
                                    <?php if (!empty($profile['job_title'])): ?>
                                        <hr>
                                        <div class="row mb-2">
                                            <div class="col-sm-4 font-weight-bold">Job Title:</div>
                                            <div class="col-sm-8"><?= htmlspecialchars($profile['job_title']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['jenjang'])): ?>
                                        <hr>
                                        <div class="row mb-2">
                                            <div class="col-sm-4 font-weight-bold">Jenjang:</div>
                                            <div class="col-sm-8"><?= htmlspecialchars($profile['jenjang']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['no_hp'])): ?>
                                        <hr>
                                        <div class="row mb-2">
                                            <div class="col-sm-4 font-weight-bold">No. HP:</div>
                                            <div class="col-sm-8"><?= htmlspecialchars($profile['no_hp']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['email'])): ?>
                                        <hr>
                                        <div class="row mb-2">
                                            <div class="col-sm-4 font-weight-bold">Email:</div>
                                            <div class="col-sm-8"><?= htmlspecialchars($profile['email']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['alamat_domisili'])): ?>
                                        <hr>
                                        <div class="row mb-2">
                                            <div class="col-sm-4 font-weight-bold">Alamat:</div>
                                            <div class="col-sm-8"><?= htmlspecialchars($profile['alamat_domisili']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['tanggal_lahir'])): ?>
                                        <hr>
                                        <div class="row mb-2">
                                            <div class="col-sm-4 font-weight-bold">Tanggal Lahir:</div>
                                            <div class="col-sm-8"><?= $tanggalLahirFormatted; ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['pendidikan'])): ?>
                                        <hr>
                                        <div class="row mb-2">
                                            <div class="col-sm-4 font-weight-bold">Pendidikan:</div>
                                            <div class="col-sm-8"><?= htmlspecialchars($profile['pendidikan']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['status_perkawinan'])): ?>
                                        <hr>
                                        <div class="row mb-2">
                                            <div class="col-sm-4 font-weight-bold">Status:</div>
                                            <div class="col-sm-8"><?= htmlspecialchars($profile['status_perkawinan']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['gaji_pokok'])): ?>
                                        <hr>
                                        <div class="row mb-2">
                                            <div class="col-sm-4 font-weight-bold">Gaji Pokok:</div>
                                            <div class="col-sm-8">
                                                Rp <?= number_format($profile['gaji_pokok'], 2, ',', '.'); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Akhir Kartu Profil -->
                </div>
                <!-- End Container Fluid -->
            </div>
            <!-- End Main Content -->

            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?= date("Y"); ?> Payroll Management System</span>
                    </div>
                </div>
            </footer>
        </div>
        <!-- End Content Wrapper -->
    </div>
    <!-- End Page Wrapper -->

    <!-- MODAL: Edit Profil -->
    <div class="modal fade" id="modalEditProfile" tabindex="-1" aria-labelledby="modalEditProfileLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form id="edit-profile-form" class="needs-validation" novalidate enctype="multipart/form-data">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="modalEditProfileLabel">Edit Profil</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
              <!-- Gunakan container-fluid untuk memastikan grid memenuhi lebar modal -->
              <div class="container-fluid">
                <!-- Hidden input untuk CSRF token dan ID user -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="id" value="<?= htmlspecialchars($profile['id']); ?>">

                <!-- Baris 1: NIP dan Nama -->
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="editNip">NIP <span class="text-danger">*</span></label>
                      <input type="text" name="nip" id="editNip" class="form-control" 
                             value="<?= htmlspecialchars($profile['nip']); ?>" required>
                      <div class="invalid-feedback">NIP wajib diisi.</div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="editNama">Nama <span class="text-danger">*</span></label>
                      <input type="text" name="nama" id="editNama" class="form-control" 
                             value="<?= htmlspecialchars($profile['nama']); ?>" required>
                      <div class="invalid-feedback">Nama wajib diisi.</div>
                    </div>
                  </div>
                </div>

                <!-- Baris 2: Jenjang dan Job Title -->
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="editJenjang">Jenjang</label>
                      <input type="text" name="jenjang" id="editJenjang" class="form-control" 
                             value="<?= htmlspecialchars($profile['jenjang'] ?? ''); ?>">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="editJobTitle">Job Title</label>
                      <input type="text" name="job_title" id="editJobTitle" class="form-control" 
                             value="<?= htmlspecialchars($profile['job_title'] ?? ''); ?>">
                    </div>
                  </div>
                </div>

                <!-- Baris 3: Kontak (No. HP dan Email) -->
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="editNoHP">No. HP</label>
                      <input type="text" name="no_hp" id="editNoHP" class="form-control" 
                             value="<?= htmlspecialchars($profile['no_hp'] ?? ''); ?>">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="editEmail">Email</label>
                      <input type="email" name="email" id="editEmail" class="form-control" 
                             value="<?= htmlspecialchars($profile['email'] ?? ''); ?>">
                    </div>
                  </div>
                </div>

                <!-- Baris 4: Alamat Domisili -->
                <div class="row">
                  <div class="col-12">
                    <div class="form-group">
                      <label for="editAlamatDomisili">Alamat Domisili</label>
                      <textarea name="alamat_domisili" id="editAlamatDomisili" rows="2" 
                                class="form-control"><?= htmlspecialchars($profile['alamat_domisili'] ?? ''); ?></textarea>
                    </div>
                  </div>
                </div>

                <!-- Baris 5: Tanggal Lahir dan Pendidikan -->
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="editTanggalLahir">Tanggal Lahir</label>
                      <input type="date" name="tanggal_lahir" id="editTanggalLahir" class="form-control" 
                             value="<?= htmlspecialchars($profile['tanggal_lahir'] ?? ''); ?>">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="editPendidikan">Pendidikan</label>
                      <input type="text" name="pendidikan" id="editPendidikan" class="form-control" 
                             value="<?= htmlspecialchars($profile['pendidikan'] ?? ''); ?>">
                    </div>
                  </div>
                </div>

                <!-- Baris 6: Status Pernikahan -->
                <div class="row">
                  <div class="col-12">
                    <div class="form-group">
                      <label for="editStatusPernikahan">Status Pernikahan</label>
                      <input type="text" name="status_perkawinan" id="editStatusPernikahan" class="form-control" 
                             value="<?= htmlspecialchars($profile['status_perkawinan'] ?? ''); ?>">
                    </div>
                  </div>
                </div>

                <!-- Baris 7: Password Baru dan Ganti Foto Profil -->
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="editPassword">Password Baru (opsional)</label>
                      <input type="password" name="password" id="editPassword" class="form-control" 
                             placeholder="Kosongkan jika tidak ingin mengganti password">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="foto_profil">Ganti Foto Profil (opsional)</label>
                      <input type="file" name="foto_profil" id="foto_profil" class="form-control-file">
                      <small class="text-muted">Maksimal 2MB (jpg/jpeg/png).</small>
                    </div>
                  </div>
                </div>

              </div><!-- /.container-fluid -->
            </div><!-- /.modal-body -->
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
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
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
