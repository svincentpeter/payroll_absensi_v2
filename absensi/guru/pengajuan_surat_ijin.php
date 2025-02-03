<?php
// Menampilkan semua error untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../koneksi.php';

// Pastikan pengguna sudah login dan memiliki peran yang tepat
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['guru', 'karyawan'])) {
    header("Location: ../../login.php");
    exit();
}

// Ambil NIP dan Nama dari session
$nip = $_SESSION['nip'] ?? '';
$nama = $_SESSION['nama'] ?? '';

// Pastikan NIP tidak kosong
if (empty($nip)) {
    die("NIP tidak ditemukan dalam session.");
}

// Debug koneksi
if (!$conn) {
    die("Koneksi ke database gagal.");
}

// Proses pengajuan izin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul_surat = $_POST['judul_surat'];
    $tanggal = $_POST['tanggal'];
    $pesan = $_POST['pesan'];
    $tipe_ijin = $_POST['tipe_ijin'];

    $insert_query = "INSERT INTO pengajuan_ijin (nip, nama, judul_surat, tanggal, pesan, tipe_ijin, status) 
                 VALUES ('$nip', '$nama', '$judul_surat', '$tanggal', '$pesan', '$tipe_ijin', 'Pending')";

if ($conn->query($insert_query)) {
        echo "<script>alert('Pengajuan surat izin berhasil diajukan!');</script>";
    } else {
        echo "<script>alert('Terjadi kesalahan: " . $conn->error . "');</script>";
    }
}

// Ambil daftar pengajuan izin
$result = $conn->query("SELECT * FROM pengajuan_ijin WHERE nip = '$nip' ORDER BY id DESC");

if (!$result) {
    die("Query gagal: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Surat Izin</title>
    <link href="../../assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
    <?php include '../../sidebar.php'; ?>
    <!-- End Sidebar -->

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
            <!-- End Topbar -->

            <!-- Page Content -->
            <div class="container-fluid">
                <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-envelope"></i> Pengajuan Surat Izin</h1>

                <div class="row">
                    <!-- Form Pengajuan -->
                    <div class="col-lg-6">
                        <div class="form-section">
                            <h4 class="mb-4">Form Pengajuan</h4>
                            <form action="" method="POST">
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

                    <!-- Daftar Izin -->
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
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Page Content -->
        </div>
    </div>
</div>

<script src="../../assets/vendor/jquery/jquery.min.js"></script>
<script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    flatpickr("#tanggal", {
        mode: "multiple",
        dateFormat: "Y-m-d",
        minDate: "today",
        locale: "id"
    });
</script>
</body>
</html>
