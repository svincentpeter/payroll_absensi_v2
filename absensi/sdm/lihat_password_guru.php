<?php
session_start();

// Aktifkan error reporting untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../koneksi.php';

// Pastikan hanya superadmin yang bisa mengakses halaman ini
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../../login.php");
    exit();
}

// Tentukan mode pengurutan dari parameter GET (default: nonemptyfirst)
$order_mode = isset($_GET['order_mode']) ? $_GET['order_mode'] : 'nonemptyfirst';

if ($order_mode === 'nonemptyfirst') {
    // Baris dengan password terisi (non-empty) muncul di atas
    $order_clause = "ORDER BY (CASE WHEN password = '' THEN 1 ELSE 0 END) ASC, nip DESC";
} elseif ($order_mode === 'nonemptylast') {
    // Baris dengan password kosong muncul di bawah
    $order_clause = "ORDER BY (CASE WHEN password = '' THEN 0 ELSE 1 END) ASC, nip DESC";
} else {
    $order_clause = "ORDER BY nip DESC";
}

// Query mengambil data guru dengan pengurutan sesuai parameter
$sql = "SELECT nip, nama, password FROM anggota_sekolah $order_clause";
$result = $conn->query($sql);

// Periksa apakah query berhasil
if (!$result) {
    die("Query Error: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data Password Guru</title>
    <!-- Bootstrap 4 & SB Admin 2 CSS -->
    <link href="../../assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/sb-admin-2.min.css" rel="stylesheet">
    <!-- Optional: Include additional CSS if needed -->
    <style>
        /* Tambahkan styling tambahan jika diperlukan */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .table thead th {
            vertical-align: middle;
            text-align: center;
            background-color: #f8f9fc;
            color: #5a5c69;
            border-bottom: 2px solid #e3e6f0;
        }
        .table tbody tr:nth-child(even) {
            background-color: #f8f9fc;
        }
        .highlight {
            background-color: #add8e6 !important; /* Biru muda */
        }
        #searchNip {
            margin-bottom: 20px;
        }
    </style>
</head>
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../sidebar.php'; ?>
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
                    <h1 class="h3 mb-4 text-gray-800">Data Password Guru</h1>
                    
                    <!-- Menampilkan Notifikasi -->
                    <?php
                    if (isset($_SESSION['dummy_success'])) {
                        echo "<div class='alert alert-success alert-dismissible fade show' role='alert'>" 
                                . htmlspecialchars($_SESSION['dummy_success']) .
                             "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>
                                <span aria-hidden='true'>&times;</span>
                              </button></div>";
                        unset($_SESSION['dummy_success']);
                    }
                    if (isset($_SESSION['dummy_error'])) {
                        echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" 
                                . htmlspecialchars($_SESSION['dummy_error']) .
                             "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>
                                <span aria-hidden='true'>&times;</span>
                              </button></div>";
                        unset($_SESSION['dummy_error']);
                    }
                    ?>
                    
                    <!-- Tombol Pengurutan -->
                    <div class="btn-group order-buttons" role="group" aria-label="Order Buttons">
                        <a href="?order_mode=nonemptyfirst" class="btn btn-outline-secondary <?= $order_mode === 'nonemptyfirst' ? 'active' : ''; ?>">
                            Non-empty di Atas
                        </a>
                        <a href="?order_mode=nonemptylast" class="btn btn-outline-secondary <?= $order_mode === 'nonemptylast' ? 'active' : ''; ?>">
                            Non-empty di Bawah
                        </a>
                    </div>

                    <!-- Search Bar -->
                    <div class="form-group">
                        <input type="text" id="searchNip" class="form-control" placeholder="Cari NIP Guru">
                    </div>
    
                    <!-- Card Tabel Data Guru -->
                    <div class="card shadow">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="guruTable">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>NIP</th>
                                            <th>Nama Guru</th>
                                            <th>Password</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                            $no_total = 1; 
                                            while($row = $result->fetch_assoc()): 
                                        ?>
                                        <tr>
                                            <td><?= $no_total++; ?></td>
                                            <td><?= htmlspecialchars($row['nip']) ?></td>
                                            <td><?= htmlspecialchars($row['nama']) ?></td>
                                            <td><?= htmlspecialchars($row['password']) ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($result->num_rows === 0): ?>
                                <div class="alert alert-info mt-3" role="alert">
                                    Belum ada data guru.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- End Page Content -->
            </div>
            <!-- End Main Content -->
        </div>
        <!-- End Content Wrapper -->
    </div>
    <!-- End Page Wrapper -->

    <!-- JavaScript: Bootstrap, jQuery, SB Admin 2 -->
    <script src="../../assets/vendor/jquery/jquery.min.js"></script>
    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../../assets/js/sb-admin-2.min.js"></script>

    <!-- Custom JavaScript untuk Pencarian -->
    <script>
        $(document).ready(function() {
            // Fungsi untuk mengembalikan urutan semula berdasarkan no urut
            function restoreOriginalOrder() {
                $("#guruTable tbody tr").sort(function(a, b) {
                    return $(a).find("td:first").text() - $(b).find("td:first").text();
                }).appendTo("#guruTable tbody");
            }

            // Fungsi untuk mengurutkan baris yang mengandung teks yang dicari ke atas dan menambahkan highlight
            function searchAndHighlight() {
                var searchVal = $("#searchNip").val().toLowerCase().trim();
                if (searchVal === "") {
                    // Jika search kosong, hapus highlight dan kembalikan urutan asli
                    $("#guruTable tbody tr").removeClass("highlight");
                    restoreOriginalOrder();
                } else {
                    $("#guruTable tbody tr").each(function() {
                        var nipText = $(this).find("td:nth-child(2)").text().toLowerCase(); // NIP berada di kolom kedua
                        if (nipText.indexOf(searchVal) > -1) {
                            // Baris cocok: tambahkan highlight dan pindahkan ke atas
                            $(this).addClass("highlight").prependTo("#guruTable tbody");
                        } else {
                            // Baris tidak cocok: hilangkan highlight
                            $(this).removeClass("highlight");
                        }
                    });
                }
            }

            // Panggil fungsi searchAndHighlight setiap kali ada perubahan pada search bar
            $("#searchNip").on("keyup", function() {
                searchAndHighlight();
            });
        });
    </script>
</body>
</html>
