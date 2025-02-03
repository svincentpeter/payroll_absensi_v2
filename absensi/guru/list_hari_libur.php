<?php
session_start();
require_once __DIR__ . '/../../koneksi.php';
// Pastikan pengguna sudah login dan memiliki peran yang tepat
// (CATATAN: Pastikan role 'guru' juga ada di enum database baru jika masih digunakan)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['guru', 'karyawan'])) {
    header("Location: ../../login.php");
    exit();
}

// Query untuk mendapatkan data hari libur di tabel baru `holidays`
$query = "SELECT * FROM holidays ORDER BY holiday_date ASC";
$result = $conn->query($query);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Hari Libur</title>
    <link href="../../assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css" rel="stylesheet">
    <style>
        .badge-danger { background-color: #e74a3b; color: #fff; }
        .badge-secondary { background-color: #858796; color: #fff; }
    </style>
</head>
<body id="page-top">
<div id="wrapper">
    <?php include '../../sidebar.php'; ?>
    <div id="content-wrapper" class="d-flex flex-column">
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

            <div class="container-fluid">
                <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-calendar-alt"></i> Daftar Hari Libur</h1>
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">List Hari Libur</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="holidayTable" class="table table-bordered">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>No</th>
                                        <th>Judul Hari Libur</th>
                                        <th>Deskripsi Hari Libur</th>
                                        <th>Tanggal Hari Libur</th>
                                        <th>Jenis Libur</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php 
                                        $no = 1; // Penomoran
                                        while ($row = $result->fetch_assoc()): 
                                            // Sesuaikan nama kolom dengan tabel `holidays`
                                            $holidayTitle = $row['holiday_title'];
                                            $holidayDesc  = $row['holiday_desc'];
                                            $holidayDate  = $row['holiday_date'];
                                            $holidayType  = $row['holiday_type'];
                                        ?>
                                            <tr>
                                                <td><?= $no++; ?></td>
                                                <td><?= htmlspecialchars($holidayTitle); ?></td>
                                                <td><?= htmlspecialchars($holidayDesc); ?></td>
                                                <td><?= htmlspecialchars(date("d-m-Y", strtotime($holidayDate))); ?></td>
                                                <td>
                                                    <?php if ($holidayType === 'wajib'): ?>
                                                        <!-- Tampilkan badge merah untuk libur wajib -->
                                                        <span class="badge badge-danger">Tanggal Merah</span>
                                                    <?php else: ?>
                                                        <!-- Tampilkan badge abu-abu untuk libur opsional -->
                                                        <span class="badge badge-secondary">Libur Biasa</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">Tidak ada data hari libur.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="sticky-footer bg-white">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span>&copy; Sistem Nusaputera 2025</span>
                </div>
            </div>
        </footer>
        <!-- End of Footer -->
    </div>
</div>

<!-- Script -->
<script src="../../assets/vendor/jquery/jquery.min.js"></script>
<script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script>
    $(document).ready(function() {
        $('#holidayTable').DataTable({
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.13.4/i18n/id.json"
            }
        });
    });
</script>
</body>
</html>
