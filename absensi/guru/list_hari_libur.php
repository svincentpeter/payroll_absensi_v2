<?php
// list_hari_libur.php

// Inisiasi session secara aman dan buat CSRF token
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
generate_csrf_token();

// Hanya izinkan pengguna dengan role Pendidik (P) dan Tenaga Kependidikan (TK)
// (Sesuaikan role yang diperbolehkan sesuai kebutuhan)
authorize(['P', 'TK']);

// Koneksi database
require_once __DIR__ . '/../../koneksi.php';

// Query untuk mendapatkan data hari libur dari tabel `holidays`
$query = "SELECT * FROM holidays ORDER BY holiday_date ASC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Hari Libur</title>
    <!-- FontAwesome, Bootstrap 5.3.3, SB Admin 2, dan DataTables CSS via CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SB Admin 2 CSS (pastikan kompatibel dengan Bootstrap 5) -->
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <!-- DataTables Bootstrap 5 CSS -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .badge-danger { background-color: #e74a3b; color: #fff; }
        .badge-secondary { background-color: #858796; color: #fff; }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <ul class="navbar-nav ms-auto">
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
                    
                    <!-- Tampilkan Notifikasi -->
                    <?php if (isset($_SESSION['laporan_success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['laporan_success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['laporan_success']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['laporan_error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['laporan_error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['laporan_error']); ?>
                    <?php endif; ?>
                    
                    <!-- Tampilkan Tabel Hari Libur -->
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
                                                            <span class="badge badge-danger">Tanggal Merah</span>
                                                        <?php else: ?>
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
                            </div><!-- End Table Responsive -->
                        </div><!-- End Card Body -->
                    </div><!-- End Card -->
                </div><!-- End Container Fluid -->
            </div><!-- End Content -->
            
            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; Sistem Nusaputera 2025</span>
                    </div>
                </div>
            </footer>
            <!-- End Footer -->
        </div><!-- End Content Wrapper -->
    </div><!-- End Wrapper -->
    
    <!-- JavaScript: Bootstrap 5.3.3, jQuery, dan DataTables JS via CDN -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
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
