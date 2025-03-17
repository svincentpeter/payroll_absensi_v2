<?php
// File: /payroll_absensi_v2/absensi/sdm/tambah_jadwal_piket.php
// Contoh halaman CRUD jadwal piket (Create, Read, Update, Delete)

// 1. Inisialisasi dan Otorisasi
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:SDM', 'M:Superadmin'], '/payroll_absensi_v2/login.php');

// Koneksi DB
require_once __DIR__ . '/../../koneksi.php';

// (Opsional) Generate CSRF token
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

// 2. PROSES CREATE / UPDATE
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_jadwal'])) {
    // Verifikasi CSRF
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $id_jadwal   = intval($_POST['id_jadwal'] ?? 0);
    $nip         = trim($_POST['nip'] ?? '');
    $waktu_piket = trim($_POST['waktu_piket'] ?? '08:00 - 13:00');
    $tanggal     = trim($_POST['tanggal'] ?? '');
    $status      = trim($_POST['status'] ?? 'pending');
    
    // Validasi minimal
    if (empty($nip) || empty($tanggal)) {
        $_SESSION['crud_error'] = "NIP dan Tanggal tidak boleh kosong.";
        header("Location: tambah_jadwal_piket.php");
        exit();
    }
    
    // Pastikan NIP ada di anggota_sekolah
    $sqlCekNIP = "SELECT nama FROM anggota_sekolah WHERE nip = ?";
    $stmtCek = $conn->prepare($sqlCekNIP);
    $stmtCek->bind_param("s", $nip);
    $stmtCek->execute();
    $resCek = $stmtCek->get_result();
    $nama_guru = '';
    if ($resCek->num_rows === 1) {
        $rowCek = $resCek->fetch_assoc();
        $nama_guru = $rowCek['nama'];
    } else {
        $_SESSION['crud_error'] = "NIP $nip tidak ditemukan di tabel anggota_sekolah.";
        header("Location: tambah_jadwal_piket.php");
        exit();
    }
    $stmtCek->close();

    // Dapatkan bulan & tahun dari $tanggal
    $timeObj   = strtotime($tanggal);
    if (!$timeObj) {
        $_SESSION['crud_error'] = "Format tanggal tidak valid.";
        header("Location: tambah_jadwal_piket.php");
        exit();
    }
    $monthStr  = date("F", $timeObj);  // ex: "June" / "December" 
    $yearVal   = (int)date("Y", $timeObj);

    // Apakah create baru, atau update?
    if ($id_jadwal === 0) {
        // CREATE (Insert)
        $sqlInsert = "INSERT INTO jadwal_piket
            (nip, nama_guru, waktu_piket, tanggal, bulan, tahun, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sqlInsert);
        $stmt->bind_param("sssssis", $nip, $nama_guru, $waktu_piket, $tanggal, $monthStr, $yearVal, $status);
        if ($stmt->execute()) {
            $_SESSION['crud_success'] = "Berhasil menambahkan jadwal piket baru.";
        } else {
            $_SESSION['crud_error'] = "Gagal menambahkan jadwal: " . $stmt->error;
        }
        $stmt->close();
    } else {
        // UPDATE
        $sqlUpdate = "UPDATE jadwal_piket
                      SET nip = ?, nama_guru = ?, waktu_piket = ?, tanggal = ?, bulan = ?, tahun = ?, status = ?
                      WHERE id_jadwal = ?";
        $stmt = $conn->prepare($sqlUpdate);
        $stmt->bind_param("sssssisi", $nip, $nama_guru, $waktu_piket, $tanggal, $monthStr, $yearVal, $status, $id_jadwal);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $_SESSION['crud_success'] = "Data jadwal (ID: $id_jadwal) berhasil diperbarui.";
            } else {
                // Kemungkinan tidak ada baris yang berubah, atau id_jadwal salah
                $_SESSION['crud_success'] = "Data jadwal (ID: $id_jadwal) tidak ada perubahan.";
            }
        } else {
            $_SESSION['crud_error'] = "Gagal mengupdate jadwal: " . $stmt->error;
        }
        $stmt->close();
    }
    
    header("Location: tambah_jadwal_piket.php");
    exit();
}

// 3. PROSES DELETE
// --------------------------------------------------
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    if ($delete_id > 0) {
        // Lakukan delete
        $sqlDel = "DELETE FROM jadwal_piket WHERE id_jadwal = ?";
        $stmt = $conn->prepare($sqlDel);
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $_SESSION['crud_success'] = "Jadwal (ID: $delete_id) berhasil dihapus.";
        } else {
            $_SESSION['crud_error'] = "Gagal menghapus jadwal (ID: $delete_id).";
        }
        $stmt->close();
    }
    header("Location: tambah_jadwal_piket.php");
    exit();
}

// 4. TAMPILKAN DATA (READ) + FORM
// --------------------------------------------------
// Jika ada mode edit, ambil datanya
$edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
$edit_data = null;
if ($edit_id > 0) {
    $sqlEd = "SELECT * FROM jadwal_piket WHERE id_jadwal = ?";
    $stmt = $conn->prepare($sqlEd);
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $resEd = $stmt->get_result();
    if ($resEd->num_rows == 1) {
        $edit_data = $resEd->fetch_assoc();
    }
    $stmt->close();
}

// Ambil semua data jadwal untuk daftar
$sqlAll = "SELECT * FROM jadwal_piket ORDER BY tanggal DESC, id_jadwal DESC";
$resAll = $conn->query($sqlAll);
$all_jadwal = [];
if ($resAll && $resAll->num_rows > 0) {
    while($row = $resAll->fetch_assoc()) {
        $all_jadwal[] = $row;
    }
}

// Ambil daftar guru/karyawan dari tabel anggota_sekolah (role P atau TK)
$sqlGuru = "SELECT nip, nama, role FROM anggota_sekolah
            WHERE role IN ('P','TK')
            ORDER BY nama ASC";
$resGuru = $conn->query($sqlGuru);
$listGuru = [];
if ($resGuru && $resGuru->num_rows > 0) {
    while($r = $resGuru->fetch_assoc()) {
        $listGuru[] = $r;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>CRUD Jadwal Piket</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .table-hover tbody tr:hover {
            background-color: #f8f9fc; /* abu2 tipis */
        }
        .smooth-transition {
            transition: background-color 0.3s, transform 0.2s;
        }
        .smooth-transition:hover {
            transform: scale(1.02);
        }
        th, td {
            vertical-align: middle !important;
        }
        label.form-label {
            font-weight: 600;
        }
    </style>
</head>
<body id="page-top">
    <div class="container py-4">
        <div class="mb-3">
            <a href="/payroll_absensi_v2/absensi/sdm/laporan_jadwal_piket.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>

        <h1 class="h3 mb-4 text-dark"><i class="fas fa-calendar"></i> Tambah Jadwal Piket (CRUD)</h1>

        <!-- Notif -->
        <?php if(isset($_SESSION['crud_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['crud_success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['crud_success']); ?>
        <?php endif; ?>
        <?php if(isset($_SESSION['crud_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['crud_error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['crud_error']); ?>
        <?php endif; ?>

        <!-- FORM CREATE / EDIT -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <?php if($edit_data): ?>
                    <h5 class="card-title mb-0"><i class="fas fa-edit"></i> Edit Jadwal (ID: <?= $edit_data['id_jadwal']; ?>)</h5>
                <?php else: ?>
                    <h5 class="card-title mb-0"><i class="fas fa-plus"></i> Tambah Jadwal Baru</h5>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form action="tambah_jadwal_piket.php" method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="id_jadwal" value="<?= $edit_data['id_jadwal'] ?? 0; ?>">

                    <!-- Field Pilih Guru -->
                    <div class="col-md-4">
                        <label for="nip" class="form-label">Pilih Guru/Karyawan</label>
                        <select name="nip" id="nip" class="form-select" required>
                            <option value="">-- Pilih --</option>
                            <?php foreach($listGuru as $g): ?>
                                <?php
                                    $selected = '';
                                    if($edit_data && $edit_data['nip'] == $g['nip']) {
                                        $selected = 'selected';
                                    }
                                ?>
                                <option value="<?= htmlspecialchars($g['nip']); ?>" <?= $selected; ?>>
                                    <?= htmlspecialchars($g['nama']); ?> (<?= htmlspecialchars($g['nip']); ?>) [<?= $g['role']; ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Harap pilih guru/karyawan.</div>
                    </div>

                    <!-- Field Tanggal -->
                    <div class="col-md-4">
                        <label for="tanggal" class="form-label">Tanggal Piket</label>
                        <input type="date" name="tanggal" id="tanggal" class="form-control" required
                               value="<?= $edit_data['tanggal'] ?? ''; ?>">
                        <div class="invalid-feedback">Harap pilih tanggal.</div>
                    </div>

                    <!-- Field Waktu Piket -->
                    <div class="col-md-4">
                        <label for="waktu_piket" class="form-label">Waktu Piket</label>
                        <input type="text" name="waktu_piket" id="waktu_piket" class="form-control"
                               value="<?= $edit_data['waktu_piket'] ?? '08:00 - 13:00'; ?>">
                    </div>

                    <!-- Field Status -->
                    <div class="col-md-4">
                        <label for="status" class="form-label">Status</label>
                        <?php
                            $currentStatus = $edit_data['status'] ?? 'pending';
                        ?>
                        <select name="status" id="status" class="form-select">
                            <option value="pending"     <?= ($currentStatus=='pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="hadir"       <?= ($currentStatus=='hadir') ? 'selected' : ''; ?>>Hadir</option>
                            <option value="tidak hadir" <?= ($currentStatus=='tidak hadir') ? 'selected' : ''; ?>>Tidak Hadir</option>
                        </select>
                    </div>

                    <!-- Tombol Submit -->
                    <div class="col-md-12 mt-3">
                        <button type="submit" class="btn btn-success" name="save_jadwal">
                            <i class="fas fa-save"></i>
                            <?= $edit_data ? 'Update Jadwal' : 'Simpan Jadwal'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div><!-- End card form -->

        <!-- TABEL DATA JADWAL -->
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-table"></i> Daftar Jadwal Piket Terkini</h5>
            </div>
            <div class="card-body table-responsive">
                <?php if(empty($all_jadwal)): ?>
                    <div class="alert alert-info">Belum ada data jadwal sama sekali.</div>
                <?php else: ?>
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>ID Jadwal</th>
                                <th>NIP</th>
                                <th>Nama Guru</th>
                                <th>Tanggal</th>
                                <th>Waktu</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no=1;
                            foreach($all_jadwal as $jd):
                            ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td><?= $jd['id_jadwal']; ?></td>
                                <td><?= htmlspecialchars($jd['nip']); ?></td>
                                <td><?= htmlspecialchars($jd['nama_guru']); ?></td>
                                <td><?= htmlspecialchars($jd['tanggal']); ?></td>
                                <td><?= htmlspecialchars($jd['waktu_piket']); ?></td>
                                <td>
                                    <?php
                                        $st = strtolower($jd['status']);
                                        if($st==='pending') {
                                            echo '<span class="badge bg-warning text-dark">Pending</span>';
                                        } elseif($st==='hadir') {
                                            echo '<span class="badge bg-success">Hadir</span>';
                                        } elseif($st==='tidak hadir') {
                                            echo '<span class="badge bg-danger">Tidak Hadir</span>';
                                        } else {
                                            echo htmlspecialchars($jd['status']);
                                        }
                                    ?>
                                </td>
                                <td>
                                    <a href="tambah_jadwal_piket.php?edit_id=<?= $jd['id_jadwal']; ?>"
                                       class="btn btn-sm btn-primary smooth-transition">
                                       <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="tambah_jadwal_piket.php?delete_id=<?= $jd['id_jadwal']; ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Apakah yakin ingin menghapus jadwal ID: <?= $jd['id_jadwal']; ?> ?');">
                                       <i class="fas fa-trash"></i> Hapus
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div><!-- End card table -->

    </div><!-- /.container -->

    <!-- JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <script>
    (function() {
        'use strict';
        // Fade out alert setelah 3 detik
        setTimeout(function(){
            let alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(el) {
                el.classList.add('fade');
                setTimeout(function(){ el.remove(); }, 500);
            });
        }, 3000);
    })();
    </script>
</body>
</html>
