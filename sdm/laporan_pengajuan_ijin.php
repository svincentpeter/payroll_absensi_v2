<?php
// laporan_pengajuan_ijin.php

// Tampilkan semua error untuk debugging (non-produksi)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

require_once '../helpers.php';
session_start();
require_once '../koneksi.php';

authorize(['M:SDM', 'M:Superadmin']); // Hanya role sdm dan superadmin yang diizinkan

/*
  PROSES UPDATE STATUS PENGAJUAN IZIN OLEH SDM
  (Tanpa field hidden action_type, SDM mengupdate kolom "status" saja.)
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action_type']) && isset($_POST['id'], $_POST['status'])) {
    $id = $_POST['id'];
    $status = $_POST['status'];
    // SDM hanya boleh mengupdate dengan nilai "Diterima" atau "Ditolak"
    if (in_array($status, ['Diterima', 'Ditolak'])) {
        // Hanya update kolom "status"
        $update_query = "UPDATE pengajuan_ijin SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        if ($stmt) {
            $stmt->bind_param('si', $status, $id);
            if ($stmt->execute()) {
                $_SESSION['notif_success'] = "Status pengajuan berhasil diperbarui oleh SDM.";
            } else {
                $_SESSION['notif_error'] = "Terjadi kesalahan saat update SDM: " . $conn->error;
            }
            $stmt->close();
        } else {
            $_SESSION['notif_error'] = "Gagal menyiapkan statement update SDM: " . $conn->error;
        }
    } else {
        $_SESSION['notif_error'] = "Status tidak valid untuk update SDM.";
    }
}

/*
  PROSES DELETE HISTORY PENGAJUAN IZIN
  Dengan field hidden action_type = "delete", izinkan penghapusan history jika kolom "status" (SDM) sudah bukan "Pending"
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'delete' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $check_query = "SELECT status FROM pengajuan_ijin WHERE id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($statusSDM);
    $stmt->fetch();
    $stmt->close();
    if ($statusSDM === 'Diterima' || $statusSDM === 'Ditolak') {
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
        $_SESSION['notif_error'] = "Tidak dapat menghapus pengajuan yang masih pending.";
    }
}

/*
  Query untuk tabel aktif ("Daftar Pengajuan Izin (Aktif)"):
  Tampilkan data yang sudah disetujui oleh Kepala Sekolah (status_kepalasekolah = 'Diterima')
  dan masih menunggu aksi SDM (status = 'Pending').
*/
$activeQuery = "SELECT * FROM pengajuan_ijin WHERE status_kepalasekolah = 'Diterima' AND status = 'Pending' ORDER BY id DESC";
$activeResult = $conn->query($activeQuery);
if (!$activeResult) {
    die("Query active error: " . $conn->error);
}

/*
  Query untuk tabel history ("History Pengajuan Izin"):
  Tampilkan data yang sudah diproses oleh SDM, yaitu data dengan status (SDM) bukan "Pending".
*/
$historyQuery = "SELECT * FROM pengajuan_ijin WHERE status <> 'Pending' ORDER BY id DESC";
$historyResult = $conn->query($historyQuery);
if (!$historyResult) {
    $historyRows = [];
} else {
    $historyRows = [];
    while ($row = $historyResult->fetch_assoc()) {
        $historyRows[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Pengajuan Izin - SDM</title>
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* ===== Page Title Styling ===== */
.page-title {
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    font-size: 2.5rem;
    color: #0d47a1;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
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
        .badge-pending {
            background-color: #f6c23e;
            color: #fff;
        }

        /* kuning */
        .badge-success {
            background-color: #28a745;
            color: #fff;
        }

        /* hijau */
        .badge-danger {
            background-color: #e74a3b;
            color: #fff;
        }

        /* merah */
        .badge-secondary {
            background-color: #858796;
            color: #fff;
        }

        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }
    </style>
    <script>
        // Fungsi untuk membuka file lampiran di jendela baru dan otomatis memicu print dialog
        function printLampiran(url) {
            var printWindow = window.open(url, '_blank');
            printWindow.onload = function() {
                printWindow.print();
            };
        }
    </script>
</head>

<body id="page-top">
    <div id="wrapper">
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include __DIR__ . '/../navbar.php'; ?>
                <?php include __DIR__ . '/../breadcrumb.php'; ?>
                <!-- Begin Page Content -->
                <div class="container-fluid">
<h1 class="page-title">
        <i class="fas fa-envelope"></i> Laporan Pengajuan Izin (SDM)</h1>
    </h1>
                    <!-- Notifikasi -->
                    <?php if (isset($_SESSION['notif_success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['notif_success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['notif_success']); ?>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['notif_error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['notif_error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['notif_error']); ?>
                    <?php endif; ?>

                    <!-- Tabel Pengajuan Izin Aktif -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-white">
                                <i class="fas fa-list"></i> Daftar Pengajuan Izin (Aktif)
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <!-- Tabel dengan 10 kolom: ID, NIP, Nama, Judul Surat, Tanggal, Pesan, Tipe Ijin, Lampiran, Status Kepala Sekolah, Aksi -->
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
                                            <th>Lampiran</th>
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
                                                        <?php
                                                        if (!empty($row['lampiran'])) {
                                                            $uploadDirRelative = '/payroll_absensi_v2/uploads/surat_ijin/';
                                                            // Misal ingin cetak di tab baru:
                                                            echo '<a href="' . $uploadDirRelative . htmlspecialchars($row['lampiran']) . '" 
             target="_blank" class="btn btn-sm btn-info">Lihat Lampiran</a>';
                                                        } else {
                                                            echo '<em>Tidak ada</em>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-pending">
                                                            <?= htmlspecialchars($row['status_kepalasekolah']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <!-- Tombol aksi untuk update status oleh SDM -->
                                                        <form method="POST" action="" style="display:inline-block;">
                                                            <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                                            <button type="submit" name="status" value="Diterima" class="btn btn-success btn-sm">Terima</button>
                                                        </form>
                                                        <form method="POST" action="" style="display:inline-block;">
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

                    <!-- Tabel History Pengajuan Izin -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-white">
                                <i class="fas fa-clock"></i> History Pengajuan Izin
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <!-- Tabel dengan 11 kolom: ID, NIP, Nama, Judul Surat, Tanggal, Pesan, Tipe Ijin, Lampiran, Status Kepala Sekolah, Status Persetujuan SDM, Aksi -->
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
                                            <th>Lampiran</th>
                                            <th>Status Kepala Sekolah</th>
                                            <th>Status Persetujuan SDM</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($historyRows) > 0): ?>
                                            <?php foreach ($historyRows as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['id']); ?></td>
                                                    <td><?= htmlspecialchars($row['nip']); ?></td>
                                                    <td><?= htmlspecialchars($row['nama']); ?></td>
                                                    <td><?= htmlspecialchars($row['judul_surat']); ?></td>
                                                    <td><?= htmlspecialchars($row['tanggal']); ?></td>
                                                    <td><?= htmlspecialchars($row['pesan']); ?></td>
                                                    <td><?= htmlspecialchars($row['tipe_ijin']); ?></td>
                                                    <td>
                                                        <?php
                                                        if (!empty($row['lampiran'])) {
                                                            $uploadDirRelative = '/payroll_absensi_v2/uploads/surat_ijin/';
                                                            // Misal ingin cetak di tab baru:
                                                            echo '<a href="' . $uploadDirRelative . htmlspecialchars($row['lampiran']) . '" 
             target="_blank" class="btn btn-sm btn-info">Lihat Lampiran</a>';
                                                        } else {
                                                            echo '<em>Tidak ada</em>';
                                                        }
                                                        ?>
                                                    </td>
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
                                                        <!-- Tombol Hapus History: Aktif jika status SDM sudah bukan "Pending" -->
                                                        <?php if ($row['status'] === 'Diterima' || $row['status'] === 'Ditolak'): ?>
                                                            <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Anda yakin ingin menghapus history ini?');">
                                                                <input type="hidden" name="action_type" value="delete">
                                                                <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                                                <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                                            </form>
                                                        <?php else: ?>
                                                            <button class="btn btn-secondary btn-sm" disabled>Hapus</button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <!-- Jika tabel history kosong, <tbody> dibiarkan kosong -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div><!-- End Page Content -->
            </div><!-- End Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; Sistem Nusaputera 2025</span>
                    </div>
                </div>
            </footer>
        </div><!-- End Content Wrapper -->
    </div><!-- End Page Wrapper -->

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        $(document).ready(function() {
            $('#activeIjinTable').DataTable({
                "language": {
                    "url": "https://cdn.datatables.net/plug-ins/1.13.4/i18n/id.json",
                    "emptyTable": "Tidak ada pengajuan izin yang pending."
                },
                "columns": [null, null, null, null, null, null, null, null, null, null]
            });
            $('#historyIjinTable').DataTable({
                "language": {
                    "url": "https://cdn.datatables.net/plug-ins/1.13.4/i18n/id.json",
                    "emptyTable": "Tidak ada history pengajuan izin."
                },
                "columns": [null, null, null, null, null, null, null, null, null, null, null]
            });
        });
    </script>
</body>

</html>