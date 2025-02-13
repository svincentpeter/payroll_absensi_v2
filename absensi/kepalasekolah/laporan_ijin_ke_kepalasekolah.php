<?php
// laporan_ijin_ke_kepalasekolah.php

// Aktifkan error reporting untuk debugging (non-produksi)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../koneksi.php';

// Pastikan pengguna sudah login dan memiliki role "kepalasekolah"
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'kepalasekolah') {
    header("Location: ../../login.php");
    exit();
}

/* 
   PROSES UPDATE STATUS PENGAJUAN IZIN (HANYA UNTUK kolom status_kepalasekolah)
   dan PROSES DELETE HISTORY.
   Gunakan field hidden "action_type" untuk membedakan:
   - Jika action_type = "update" maka update status_kepalasekolah (nilai hanya "Diterima" atau "Ditolak")
   - Jika action_type = "delete" maka hapus data dari tabel history, 
     dengan logika tombol "Hapus" aktif jika:
         * Jika status_kepalasekolah = "Ditolak" (langsung aktif), atau
         * Jika status_kepalasekolah = "Diterima" dan kolom status (SDM) sudah bukan "Pending".
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_type']) && $_POST['action_type'] === 'update' && isset($_POST['id'], $_POST['status'])) {
        $id = $_POST['id'];
        $status = $_POST['status'];
        // Hanya terima nilai "Diterima" atau "Ditolak" untuk update oleh kepala sekolah
        if (in_array($status, ['Diterima', 'Ditolak'])) {
            $update_query = "UPDATE pengajuan_ijin SET status_kepalasekolah = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            if ($stmt) {
                $stmt->bind_param('si', $status, $id);
                if ($stmt->execute()) {
                    $_SESSION['notif_success'] = "Status pengajuan berhasil diperbarui.";
                } else {
                    $_SESSION['notif_error'] = "Terjadi kesalahan: " . $conn->error;
                }
                $stmt->close();
            } else {
                $_SESSION['notif_error'] = "Gagal menyiapkan pernyataan SQL: " . $conn->error;
            }
        } else {
            $_SESSION['notif_error'] = "Status tidak valid.";
        }
    } elseif (isset($_POST['action_type']) && $_POST['action_type'] === 'delete' && isset($_POST['id'])) {
        // Proses hapus history: izinkan hapus jika (status SDM bukan "Pending") 
        // atau jika status_kepalasekolah adalah "Ditolak"
        $id = $_POST['id'];
        $check_query = "SELECT status, status_kepalasekolah FROM pengajuan_ijin WHERE id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($statusSDM, $statusKepsek);
        $stmt->fetch();
        $stmt->close();
        // Tombol hapus aktif jika:
        // - status_kepalasekolah = 'Ditolak'
        //   OR jika status_kepalasekolah = 'Diterima' dan status (SDM) sudah bukan 'Pending'
        if ($statusKepsek === 'Ditolak' || ($statusKepsek === 'Diterima' && $statusSDM !== 'Pending')) {
            $delete_query = "DELETE FROM pengajuan_ijin WHERE id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['notif_success'] = "History pengajuan izin berhasil dihapus.";
            } else {
                $_SESSION['notif_error'] = "Gagal menghapus history: " . $conn->error;
            }
            $stmt->close();
        } else {
            $_SESSION['notif_error'] = "Tidak dapat menghapus pengajuan yang masih pending SDM.";
        }
    }
}

// Query untuk data aktif: status_kepalasekolah = 'Pending'
$activeQuery = "SELECT * FROM pengajuan_ijin WHERE status_kepalasekolah = 'Pending' ORDER BY id DESC";
$activeResult = $conn->query($activeQuery);
if (!$activeResult) {
    die("Query active error: " . $conn->error);
}

// Query untuk data history: status_kepalasekolah <> 'Pending'
$historyQuery = "SELECT * FROM pengajuan_ijin WHERE status_kepalasekolah <> 'Pending' ORDER BY id DESC";
$historyResult = $conn->query($historyQuery);
if (!$historyResult) {
    die("Query history error: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Pengajuan Izin - Kepala Sekolah</title>
    <link href="../../assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css" rel="stylesheet">
    <style>
        .badge-pending { background-color: #f6c23e; color: #fff; } /* kuning */
        .badge-success { background-color: #28a745; color: #fff; } /* hijau */
        .badge-danger { background-color: #e74a3b; color: #fff; }   /* merah */
        .badge-secondary { background-color: #858796; color: #fff; }
        /* Tambahan margin antar section */
        .section-card { margin-bottom: 20px; }
    </style>
</head>
<body id="page-top">
<div id="wrapper">
    <?php include '../../sidebar.php'; ?>
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
            <!-- End Topbar -->

            <!-- Begin Page Content -->
            <div class="container-fluid">
                <h1 class="h3 mb-4 text-gray-800">Laporan Pengajuan Izin (Kepala Sekolah)</h1>

                <!-- Notifikasi -->
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

                <!-- Section: Daftar Pengajuan Izin Aktif -->
                <div class="card section-card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Daftar Pengajuan Izin (Aktif)</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <!-- Tabel dengan 9 kolom -->
                            <table id="activeIjinTable" class="table table-bordered">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>NIP</th>
                                        <th>Nama</th>
                                        <th>Judul Surat</th>
                                        <th>Tanggal</th>
                                        <th>Pesan</th>
                                        <th>Tipe Ijin</th>
                                        <th>Status Kepala Sekolah</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($activeResult->num_rows > 0): ?>
                                        <?php while ($row = $activeResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['id']); ?></td>
                                                <td><?= htmlspecialchars($row['nip']); ?></td>
                                                <td><?= htmlspecialchars($row['nama']); ?></td>
                                                <td><?= htmlspecialchars($row['judul_surat']); ?></td>
                                                <td><?= htmlspecialchars($row['tanggal']); ?></td>
                                                <td><?= htmlspecialchars($row['pesan']); ?></td>
                                                <td><?= htmlspecialchars($row['tipe_ijin']); ?></td>
                                                <td>
                                                    <span class="badge badge-pending">
                                                        <?= htmlspecialchars($row['status_kepalasekolah']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <!-- Tombol aksi untuk update status oleh Kepala Sekolah -->
                                                    <form method="POST" action="" style="display:inline-block;">
                                                        <input type="hidden" name="action_type" value="update">
                                                        <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                                        <button type="submit" name="status" value="Diterima" class="btn btn-success btn-sm">Setuju</button>
                                                    </form>
                                                    <form method="POST" action="" style="display:inline-block;">
                                                        <input type="hidden" name="action_type" value="update">
                                                        <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                                        <button type="submit" name="status" value="Ditolak" class="btn btn-danger btn-sm">Tolak</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                    <!-- Jika tabel aktif kosong, <tbody> dibiarkan kosong -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Section: History Pengajuan Izin -->
                <div class="card section-card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">History Pengajuan Izin</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <!-- Tabel dengan 10 kolom -->
                            <table id="historyIjinTable" class="table table-bordered">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>NIP</th>
                                        <th>Nama</th>
                                        <th>Judul Surat</th>
                                        <th>Tanggal</th>
                                        <th>Pesan</th>
                                        <th>Tipe Ijin</th>
                                        <th>Status Kepala Sekolah</th>
                                        <th>Status Persetujuan SDM</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($historyResult->num_rows > 0): ?>
                                        <?php while ($row = $historyResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['id']); ?></td>
                                                <td><?= htmlspecialchars($row['nip']); ?></td>
                                                <td><?= htmlspecialchars($row['nama']); ?></td>
                                                <td><?= htmlspecialchars($row['judul_surat']); ?></td>
                                                <td><?= htmlspecialchars($row['tanggal']); ?></td>
                                                <td><?= htmlspecialchars($row['pesan']); ?></td>
                                                <td><?= htmlspecialchars($row['tipe_ijin']); ?></td>
                                                <td>
                                                    <span class="<?= ($row['status_kepalasekolah'] === 'Diterima') ? 'badge badge-success' : 'badge badge-danger'; ?>">
                                                        <?= htmlspecialchars($row['status_kepalasekolah']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="<?= ($row['status'] === 'Diterima') ? 'badge badge-success' : (($row['status'] === 'Ditolak') ? 'badge badge-danger' : 'badge badge-pending'); ?>">
                                                        <?= htmlspecialchars($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <!-- Tombol Hapus History: Aktif jika:
                                                         - Jika status_kepalasekolah adalah "Ditolak", tombol hapus aktif, atau
                                                         - Jika status_kepalasekolah adalah "Diterima" DAN status (SDM) bukan "Pending"
                                                    -->
                                                    <?php if (($row['status_kepalasekolah'] === 'Ditolak') || 
                                                              ($row['status_kepalasekolah'] === 'Diterima' && $row['status'] !== 'Pending')): ?>
                                                        <form method="POST" action="" style="display:inline-block;">
                                                            <input type="hidden" name="action_type" value="delete">
                                                            <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button class="btn btn-secondary btn-sm" disabled>Hapus</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                    <!-- Jika tabel history kosong, <tbody> dibiarkan kosong -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            </div><!-- End Page Content -->
        </div><!-- End Main Content -->
    </div><!-- End Content Wrapper -->
</div><!-- End Page Wrapper -->

<!-- JavaScript -->
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
<script src="https://cdn.jsdelivr.net/npm/jquery/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    // Inisialisasi DataTables untuk tabel "Daftar Izin Saya" (7 kolom)
    $('#pengajuanTable').DataTable({
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.13.4/i18n/id.json",
            "emptyTable": "Belum ada data pengajuan izin."
        },
        "columns": [ null, null, null, null, null, null, null ]
    });
    
    // Inisialisasi DataTables untuk tabel "History Pengajuan Izin" (9 kolom)
    $('#historyTable').DataTable({
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.13.4/i18n/id.json",
            "emptyTable": "Belum ada history pengajuan izin."
        },
        "columns": [ null, null, null, null, null, null, null, null, null ]
    });
});
</script>
</body>
</html>
