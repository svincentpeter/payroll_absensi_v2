<?php
// pengajuan_surat_ijin.php

// Inisiasi session secara aman dan buat CSRF token
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
generate_csrf_token();

// Otorisasi: hanya izinkan role Pendidik (P) dan Tenaga Kependidikan (TK)
authorize(['P', 'TK']);

// Koneksi database
require_once __DIR__ . '/../../koneksi.php';

// Ambil NIP dan Nama dari session
$nip  = $_SESSION['nip'] ?? '';
$nama = $_SESSION['nama'] ?? '';

// Pastikan NIP tidak kosong
if (empty($nip)) {
    die("NIP tidak ditemukan dalam session.");
}

// Proses pengajuan izin menggunakan prepared statement agar lebih aman
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifikasi CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    // Bersihkan input (opsional: gunakan fungsi sanitize_input() dari helpers.php)
    $judul_surat = sanitize_input($_POST['judul_surat'] ?? '');
    $tanggal     = sanitize_input($_POST['tanggal'] ?? '');
    $pesan       = sanitize_input($_POST['pesan'] ?? '');
    $tipe_ijin   = sanitize_input($_POST['tipe_ijin'] ?? '');

    // Lakukan validasi sederhana
    if (!empty($judul_surat) && !empty($tanggal) && !empty($pesan) && !empty($tipe_ijin)) {
        $insert_query = "INSERT INTO pengajuan_ijin (nip, nama, judul_surat, tanggal, pesan, tipe_ijin, status) 
                         VALUES (?, ?, ?, ?, ?, ?, 'Pending')";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("ssssss", $nip, $nama, $judul_surat, $tanggal, $pesan, $tipe_ijin);
        if ($stmt->execute()) {
            $_SESSION['absensi_success'] = "Pengajuan surat izin berhasil diajukan!";
        } else {
            $_SESSION['absensi_error'] = "Terjadi kesalahan: " . $conn->error;
        }
        $stmt->close();
    } else {
        $_SESSION['absensi_error'] = "Semua field harus diisi.";
    }
    header("Location: pengajuan_surat_ijin.php");
    exit();
}

// Ambil daftar pengajuan izin milik pengguna
$sql = "SELECT * FROM pengajuan_ijin WHERE nip = ? ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nip);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Surat Izin</title>
    <!-- FontAwesome, Bootstrap 5.3.3, SB Admin 2 CSS, dan DataTables CSS via CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SB Admin 2 CSS (pastikan kompatibel dengan Bootstrap 5) -->
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/css/sb-admin-2.min.css" rel="stylesheet">
    <!-- DataTables CSS (jika diperlukan) -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Flatpickr CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <style>
        body {
            font-family: "Nunito", sans-serif;
        }
        .form-section, .table-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .badge-pending { background-color: #f6c23e; color: #fff; }
        .badge-ditolak { background-color: #e74a3b; color: #fff; }
        .badge-diterima { background-color: #1cc88a; color: #fff; }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <!-- End Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <?php include __DIR__ . '/../../navbar.php'; ?>
                <!-- End Topbar -->

                <!-- Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-envelope"></i> Pengajuan Surat Izin</h1>

                    <div class="row">
                        <!-- Form Pengajuan -->
                        <div class="col-lg-6">
                            <div class="form-section mb-4">
                                <h4 class="mb-4">Form Pengajuan</h4>
                                <form action="" method="POST">
                                    <!-- Sertakan CSRF token -->
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                    <div class="form-group mb-3">
                                        <label for="judul_surat">Judul Surat</label>
                                        <input type="text" name="judul_surat" id="judul_surat" class="form-control" required>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label for="tanggal">Tanggal Izin</label>
                                        <input type="text" name="tanggal" id="tanggal" class="form-control" required>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label for="pesan">Pesan</label>
                                        <textarea name="pesan" id="pesan" class="form-control" rows="5" required></textarea>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label for="tipe_ijin">Tipe Izin/Cuti</label>
                                        <select name="tipe_ijin" id="tipe_ijin" class="form-control" required>
                                            <option value="">Pilih tipe izin</option>
                                            <option value="Sakit">Sakit</option>
                                            <option value="Cuti Biasa">Cuti Biasa</option>
                                            <option value="Ijin Lainnya">Ijin Lainnya</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Ajukan Izin</button>
                                </form>
                            </div>
                        </div>

                        <!-- Daftar Pengajuan -->
                        <div class="col-lg-6">
                            <div class="table-section">
                                <h4 class="mb-4">Daftar Izin Saya</h4>
                                <div class="table-responsive">
                                    <table id="pengajuanTable" class="table table-bordered">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th>Nama</th>
                                                <th>Judul Surat</th>
                                                <th>Tanggal</th>
                                                <th>Pesan</th>
                                                <th>Tipe</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($row = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['nama']); ?></td>
                                                    <td><?= htmlspecialchars($row['judul_surat']); ?></td>
                                                    <td><?= htmlspecialchars($row['tanggal']); ?></td>
                                                    <td><?= htmlspecialchars($row['pesan']); ?></td>
                                                    <td><?= htmlspecialchars($row['tipe_ijin']); ?></td>
                                                    <td>
                                                        <span class="badge 
                                                            <?= $row['status'] === 'Pending' ? 'badge-pending' : ($row['status'] === 'Diterima' ? 'badge-diterima' : 'badge-ditolak'); ?>">
                                                            <?= htmlspecialchars($row['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                            <?php if ($result->num_rows === 0): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">Belum ada data pengajuan izin.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div><!-- End Table Responsive -->
                            </div>
                        </div>
                    </div>
                </div><!-- End Container Fluid -->
            </div><!-- End Content -->
        </div><!-- End Content Wrapper -->
    </div><!-- End Wrapper -->

    <!-- JavaScript: jQuery, Bootstrap 5.3.3, Flatpickr, dan SB Admin 2 JS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Inisialisasi Flatpickr dengan mode single date dan locale Indonesia
        flatpickr("#tanggal", {
            dateFormat: "Y-m-d",
            minDate: "today",
            locale: "id"
        });
    </script>
</body>
</html>
