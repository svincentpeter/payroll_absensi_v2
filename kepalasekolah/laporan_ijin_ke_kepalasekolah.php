<?php
// laporan_ijin_ke_kepalasekolah.php

// Aktifkan error reporting untuk debugging (non-produksi)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../helpers.php';
start_session_safe();
generate_csrf_token();
authorize('kepala sekolah');

// Koneksi database
require_once '../koneksi.php';

// === Ambil jenjang kepala sekolah yang sedang login dari session ===
$kepsekJenjang = $_SESSION['jenjang'] ?? '';
if (!$kepsekJenjang) {
    die("Tidak dapat menentukan jenjang Kepala Sekolah yang login.");
}

// PROSES UPDATE STATUS PENGAJUAN IZIN atau DELETE HISTORY
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifikasi token CSRF
    verify_csrf_token($_POST['csrf_token'] ?? '');

    if (isset($_POST['action_type']) && $_POST['action_type'] === 'update' && isset($_POST['id'], $_POST['status'])) {
        $id = intval($_POST['id']);
        $status = $_POST['status'];
        // Terima hanya nilai "Diterima" atau "Ditolak"
        if (in_array($status, ['Diterima', 'Ditolak'])) {
            $update_query = "UPDATE pengajuan_ijin SET status_kepalasekolah = ? WHERE id = ?";
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
                $_SESSION['notif_error'] = "Gagal menyiapkan pernyataan SQL untuk update: " . $conn->error;
            }
        } else {
            $_SESSION['notif_error'] = "Status tidak valid.";
        }
    } elseif (isset($_POST['action_type']) && $_POST['action_type'] === 'delete' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        // Cek apakah pengajuan dapat dihapus:
        // - Jika status_kepalasekolah = 'Ditolak'
        //   OR jika status_kepalasekolah = 'Diterima' dan status (SDM) bukan 'Pending'
        $check_query = "SELECT status, status_kepalasekolah FROM pengajuan_ijin WHERE id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($statusSDM, $statusKepsek);
        $stmt->fetch();
        $stmt->close();
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
    // Redirect ulang agar form tidak di-submit ulang saat refresh
    header("Location: laporan_ijin_ke_kepalasekolah.php");
    exit();
}

// Query untuk data aktif: hanya jenjang yang sama
$activeStmt = $conn->prepare(
    "SELECT pi.*
       FROM pengajuan_ijin pi
       JOIN anggota_sekolah a ON a.nip = pi.nip
      WHERE pi.status_kepalasekolah = 'Pending'
        AND a.jenjang = ?
   ORDER BY pi.id DESC"
);
$activeStmt->bind_param('s', $kepsekJenjang);
$activeStmt->execute();
$activeResult = $activeStmt->get_result();

// Query untuk data history: hanya jenjang yang sama
$historyStmt = $conn->prepare(
    "SELECT pi.*
       FROM pengajuan_ijin pi
       JOIN anggota_sekolah a ON a.nip = pi.nip
      WHERE pi.status_kepalasekolah <> 'Pending'
        AND a.jenjang = ?
   ORDER BY pi.id DESC"
);
$historyStmt->bind_param('s', $kepsekJenjang);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Pengajuan Izin - Kepala Sekolah</title>
    <!-- FontAwesome, Bootstrap v5.3.3, SB Admin 2, dan DataTables CSS via CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SB Admin 2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
    body {
        background-color: #f8f9fc;
    }
    .card.section-card {
        margin-bottom: 30px;
        border: 1px solid #e3e6f0;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58,59,69,.15);
    }
    .badge {
        padding: 0.5em 0.75em;
        font-size: 0.85em;
        border-radius: 50rem;
    }
    .btn-sm {
        margin: 2px;
    }
    .alert {
        margin-top: 20px;
        box-shadow: 0px 2px 10px rgba(0, 0, 0, 0.1);
    }
    .table thead th {
        vertical-align: middle;
        background-color: #4e73df;
        color: white;
        border-bottom: none;
    }
    .table tbody tr:nth-of-type(odd) {
        background-color: #f8f9fc;
    }
    .table-bordered td, .table-bordered th {
        border: 1px solid #e3e6f0;
    }

    .card-header {
            background: #4e73df;
            color: white;
        }
</style>

</head>

<body id="page-top">
    <div id="wrapper">
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Navbar -->
                <?php include __DIR__ . '/../navbar.php'; ?>
                <!-- End Navbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Laporan Pengajuan Izin (Kepala Sekolah jenjang <?= htmlspecialchars($kepsekJenjang) ?>)</h1>

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

                    <!-- Section: Daftar Pengajuan Izin Aktif -->
                    <div class="card section-card">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-white">
            <i class="fas fa-calendar-check"></i> Daftar Pengajuan Izin (Aktif)
        </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
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
                                                        <?php if (!empty($row['lampiran'])): ?>
                                                            <a href="/payroll_absensi_v2/uploads/surat_ijin/<?= htmlspecialchars($row['lampiran']) ?>" target="_blank" class="btn btn-sm btn-info">Lihat Lampiran</a>
                                                        <?php else: ?>
                                                            <em>Tidak ada</em>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><span class="badge badge-pending"><?= htmlspecialchars($row['status_kepalasekolah']); ?></span></td>
                                                    <td>
                                                        <form method="POST" class="d-inline-block">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="action_type" value="update">
                                                            <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                                            <button type="submit" name="status" value="Diterima" class="btn btn-success btn-sm">Setuju</button>
                                                        </form>
                                                        <form method="POST" class="d-inline-block">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="action_type" value="update">
                                                            <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                                            <button type="submit" name="status" value="Ditolak" class="btn btn-danger btn-sm">Tolak</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Section: History Pengajuan Izin -->
                    <div class="card section-card">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-bold text-white">
            <i class="fas fa-history"></i> History Pengajuan Izin
        </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
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
                                                        <?php if (!empty($row['lampiran'])): ?>
                                                            <a href="/payroll_absensi_v2/uploads/surat_ijin/<?= htmlspecialchars($row['lampiran']) ?>" target="_blank" class="btn btn-sm btn-info">Lihat Lampiran</a>
                                                        <?php else: ?>
                                                            <em>Tidak ada</em>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="<?= $row['status_kepalasekolah'] === 'Diterima' ? 'badge badge-success' : 'badge badge-danger'; ?>">
                                                            <?= htmlspecialchars($row['status_kepalasekolah']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="<?= $row['status'] === 'Diterima' ? 'badge badge-success' : ($row['status'] === 'Ditolak' ? 'badge badge-danger' : 'badge badge-pending'); ?>">
                                                            <?= htmlspecialchars($row['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($row['status_kepalasekolah'] === 'Ditolak' || ($row['status_kepalasekolah'] === 'Diterima' && $row['status'] !== 'Pending')): ?>
                                                            <form method="POST" class="d-inline-block" onsubmit="return confirm('Anda yakin ingin menghapus history ini?');">
                                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
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
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div><!-- End Page Content -->
            </div><!-- End Main Content -->
        </div><!-- End Content Wrapper -->
    </div><!-- End Page Wrapper -->

    <!-- JavaScript: jQuery, Bootstrap, dan DataTables JS via CDN -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#activeIjinTable, #historyIjinTable').DataTable({
            language: {
                url: "https://cdn.datatables.net/plug-ins/1.13.4/i18n/id.json",
                emptyTable: "Tidak ada data."
            },
            "pageLength": 10,
            "lengthMenu": [5, 10, 25, 50],
            "order": [[ 0, "desc" ]],
            "stripeClasses": []
        });
    });
</script>

</body>

</html>
<?php
// Tutup statement
$activeStmt->close();
$historyStmt->close();

// Tutup koneksi database menggunakan fungsi dari helpers.php
close_db_connection();
?>
