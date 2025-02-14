<?php
// File: /payroll_absensi_v2/laporan_pengajuan_ijin.php

// ==============================================================================
// 1. Pengaturan Awal & Inisialisasi Helper
// ==============================================================================
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
authorize(['sdm', 'superadmin'], '/payroll_absensi_v2/login.php');
require_once __DIR__ . '/../../koneksi.php';

// Hasilkan CSRF token
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

// ==============================================================================
// PROSES UPDATE / DELETE
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pastikan untuk memverifikasi CSRF token di setiap proses POST
    verify_csrf_token($_POST['csrf_token'] ?? '');

    // Proses HAPUS HISTORY PENGAJUAN IZIN (action_type = delete)
    if (isset($_POST['action_type']) && $_POST['action_type'] === 'delete' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        // Cek status pengajuan (SDM)
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
    // Proses UPDATE STATUS PENGAJUAN IZIN (update oleh SDM)
    elseif (!isset($_POST['action_type']) && isset($_POST['id'], $_POST['status'])) {
        $id = intval($_POST['id']);
        $status = $_POST['status'];

        if (in_array($status, ['Diterima', 'Ditolak'])) {
            $update_query = "UPDATE pengajuan_ijin SET status = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            if ($stmt) {
                $stmt->bind_param('si', $status, $id);
                if ($stmt->execute()) {
                    $_SESSION['notif_success'] = "Status pengajuan berhasil diperbarui.";
                } else {
                    $_SESSION['notif_error'] = "Terjadi kesalahan saat update: " . $conn->error;
                }
                $stmt->close();
            } else {
                $_SESSION['notif_error'] = "Gagal menyiapkan statement update: " . $conn->error;
            }
        } else {
            $_SESSION['notif_error'] = "Status tidak valid.";
        }
    }
    header("Location: laporan_pengajuan_ijin.php");
    exit();
}

// ==============================================================================
// 2. QUERY DATA PENGAJUAN IZIN
// ==============================================================================
// Tabel Pengajuan Izin Aktif (yang sudah disetujui Kepala Sekolah dan masih pending SDM)
$activeQuery = "SELECT * FROM pengajuan_ijin WHERE status_kepalasekolah = 'Diterima' AND status = 'Pending' ORDER BY id DESC";
$activeResult = $conn->query($activeQuery);
if (!$activeResult) {
    die("Query active error: " . $conn->error);
}

// Tabel History Pengajuan Izin (yang sudah diproses oleh SDM)
$historyQuery = "SELECT * FROM pengajuan_ijin WHERE status <> 'Pending' ORDER BY id DESC";
$historyResult = $conn->query($historyQuery);
if (!$historyResult) {
    die("Query history error: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Pengajuan Izin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- SB Admin 2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/dist/css/sb-admin-2.min.css" rel="stylesheet">
    <!-- DataTables Bootstrap 5 CSS -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        /* Custom Badge Styles untuk Bootstrap 5 */
        .badge.bg-danger { background-color: #e74a3b; }
        .badge.bg-secondary { background-color: #858796; }
        .badge.bg-success { background-color: #28a745; }
        .badge.bg-pending { background-color: #f6c23e; } /* custom untuk status pending */
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
                <?php include __DIR__ . '/../../navbar.php'; ?>
                <!-- End of Topbar -->
                <!-- Breadcrumb -->
                <?php include __DIR__ . '/../../breadcrumb.php'; ?>

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">
                        <i class="fas fa-envelope"></i> Laporan Pengajuan Izin
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

                    <!-- Card: Pengajuan Izin Aktif -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 fw-bold text-primary">Daftar Pengajuan Izin (Aktif)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="activeIjinTable" class="table table-bordered">
                                    <thead class="table-dark">
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
                                                        <span class="badge <?= ($row['status_kepalasekolah'] === 'Diterima') ? 'bg-success' : 'bg-danger'; ?>">
                                                            <?= htmlspecialchars($row['status_kepalasekolah']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <!-- Form update status oleh SDM -->
                                                        <form action="" method="POST" class="d-inline">
                                                            <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                                            <button type="submit" name="status" value="Diterima" class="btn btn-success btn-sm">Terima</button>
                                                        </form>
                                                        <form action="" method="POST" class="d-inline">
                                                            <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                                            <button type="submit" name="status" value="Ditolak" class="btn btn-danger btn-sm">Tolak</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                        <!-- Jika tidak ada data, DataTables akan menampilkan pesan kosong -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Card: History Pengajuan Izin -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 fw-bold text-primary">History Pengajuan Izin</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="historyIjinTable" class="table table-bordered">
                                    <thead class="table-dark">
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
                                                        <span class="badge <?= ($row['status_kepalasekolah'] === 'Diterima') ? 'bg-success' : 'bg-danger'; ?>">
                                                            <?= htmlspecialchars($row['status_kepalasekolah']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="<?= ($row['status'] === 'Diterima') ? 'badge bg-success' : (($row['status'] === 'Ditolak') ? 'badge bg-danger' : 'badge bg-pending'); ?>">
                                                            <?= htmlspecialchars($row['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($row['status'] === 'Diterima' || $row['status'] === 'Ditolak'): ?>
                                                            <form method="POST" action="" class="d-inline">
                                                                <input type="hidden" name="action_type" value="delete">
                                                                <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                                                <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                                            </form>
                                                        <?php else: ?>
                                                            <button class="btn btn-secondary btn-sm" disabled>Hapus</button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                        <!-- Jika tidak ada data, DataTables akan menampilkan pesan kosong -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- End Page Content -->
            </div>
            <!-- End Main Content -->

            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="text-center my-auto">
                        <span>&copy; Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div>
        <!-- End Content Wrapper -->
    </div>
    <!-- End Page Wrapper -->

    <!-- JavaScript Dependencies -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#activeIjinTable').DataTable({
                "language": {
                    "url": "https://cdn.datatables.net/plug-ins/1.13.4/i18n/id.json",
                    "emptyTable": "Tidak ada pengajuan izin yang pending."
                }
            });
            $('#historyIjinTable').DataTable({
                "language": {
                    "url": "https://cdn.datatables.net/plug-ins/1.13.4/i18n/id.json",
                    "emptyTable": "Tidak ada history pengajuan izin."
                }
            });
        });
    </script>
</body>
</html>
