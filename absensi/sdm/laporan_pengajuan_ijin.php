<?php
// Menampilkan semua error untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../koneksi.php';

// Pastikan pengguna sudah login dan memiliki peran yang sesuai
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sdm') {
    header("Location: ../../login.php");
    exit();
}

// Proses update status pengajuan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    $id = $_POST['id'];
    $status = $_POST['status'];

    if (in_array($status, ['Diterima', 'Ditolak'])) {
        $update_query = "UPDATE pengajuan_ijin SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('si', $status, $id);
        if ($stmt->execute()) {
            $_SESSION['notif_success'] = "Status pengajuan berhasil diperbarui.";
        } else {
            $_SESSION['notif_error'] = "Terjadi kesalahan: " . $conn->error;
        }
    } else {
        $_SESSION['notif_error'] = "Status tidak valid.";
    }
}

// Ambil semua data pengajuan izin
$query = "SELECT * FROM pengajuan_ijin ORDER BY id DESC";
$result = $conn->query($query);

if (!$result) {
    die("Query gagal: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pengajuan Izin</title>
    <link href="../../assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css" rel="stylesheet">
    <style>
        .badge-danger { background-color: #e74a3b; color: #fff; }
        .badge-secondary { background-color: #858796; color: #fff; }
        .badge-success { background-color: #28a745; color: #fff; }
    </style>
</head>
<body id="page-top">
<div id="wrapper">
    <!-- Sidebar -->
    <?php include '../../sidebar.php'; ?>
    <!-- End of Sidebar -->

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
            <!-- End of Topbar -->

            <!-- Begin Page Content -->
            <div class="container-fluid">
                <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-envelope"></i> Laporan Pengajuan Izin</h1>
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">List Pengajuan Izin</h6>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['notif_success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($_SESSION['notif_success']); ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <?php unset($_SESSION['notif_success']); ?>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['notif_error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($_SESSION['notif_error']); ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <?php unset($_SESSION['notif_error']); ?>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table id="pengajuanTable" class="table table-bordered">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>NIP</th>
                                        <th>Nama</th>
                                        <th>Judul Surat</th>
                                        <th>Tanggal</th>
                                        <th>Pesan</th>
                                        <th>Tipe Izin</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['id']); ?></td>
                                                <td><?= htmlspecialchars($row['nip']); ?></td>
                                                <td><?= htmlspecialchars($row['nama']); ?></td>
                                                <td><?= htmlspecialchars($row['judul_surat']); ?></td>
                                                <td><?= htmlspecialchars($row['tanggal']); ?></td>
                                                <td><?= htmlspecialchars($row['pesan']); ?></td>
                                                <td><?= htmlspecialchars($row['tipe_ijin']); ?></td>
                                                <td>
                                                    <span class="<?= $row['status'] === 'Diterima' ? 'badge badge-success' : ($row['status'] === 'Ditolak' ? 'badge badge-danger' : 'badge badge-secondary'); ?>">
                                                        <?= htmlspecialchars($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <form action="" method="POST" style="display: inline-block;">
                                                        <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                                        <button type="submit" name="status" value="Diterima" class="btn btn-success btn-sm">Terima</button>
                                                    </form>
                                                    <form action="" method="POST" style="display: inline-block;">
                                                        <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                                        <button type="submit" name="status" value="Ditolak" class="btn btn-danger btn-sm">Tolak</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                    <!-- Jika tidak ada data, biarkan tbody kosong.
                                         DataTables akan menampilkan pesan kosong sesuai opsi language.emptyTable -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Page Content -->
        </div>
        <!-- End Main Content -->

        <!-- Footer -->
        <footer class="sticky-footer bg-white">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span>&copy; Sistem Nusaputera 2025</span>
                </div>
            </div>
        </footer>
        <!-- End Footer -->
    </div>
    <!-- End Content Wrapper -->
</div>
<!-- End Page Wrapper -->

<script src="../../assets/vendor/jquery/jquery.min.js"></script>
<script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script>
    $(document).ready(function() {
        $('#pengajuanTable').DataTable({
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.13.4/i18n/id.json",
                "emptyTable": "Belum ada data pengajuan izin."
            }
        });
    });
</script>
</body>
</html>
