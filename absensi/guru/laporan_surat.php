<?php
// File: /payroll_absensi_v2/absensi/guru/laporan_surat.php (atau di folder karyawan)

require_once __DIR__ . '/../../helpers.php';
start_session_safe();
authorize(['P','TK']);

require_once __DIR__ . '/../../koneksi.php';

// Ambil ID pengguna dari session
$userId = $_SESSION['id'] ?? 0;
if ($userId <= 0) {
    die("ID User tidak valid atau belum login.");
}

if (ob_get_length()) {
    ob_end_clean();
}

// Tangani permintaan AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $case = isset($_POST['case']) ? trim($_POST['case']) : '';
        switch ($case) {
            case 'LoadingSurat':
                // DataTables parameters
                $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
                $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
                $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
                $search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

                // Ambil total records tanpa filter: surat untuk user OR surat untuk semua (id_penerima=0)
                $sqlTotal = "SELECT COUNT(*) as total FROM laporan_surat WHERE id_penerima = ? OR id_penerima = 0";
                $stmtTotal = $conn->prepare($sqlTotal);
                $stmtTotal->bind_param("i", $userId);
                $stmtTotal->execute();
                $resultTotal = $stmtTotal->get_result();
                $rowTotal = mysqli_fetch_assoc($resultTotal);
                $recordsTotal = $rowTotal['total'];
                $stmtTotal->close();

                // Query dasar + JOIN untuk ambil nama pengirim
                $sqlFilter = "
                    SELECT ls.*, pengirim.nama AS nama_pengirim 
                    FROM laporan_surat ls
                    LEFT JOIN anggota_sekolah pengirim ON ls.id_pengirim = pengirim.id
                    WHERE (ls.id_penerima = ? OR ls.id_penerima = 0)
                ";
                $sqlFilterCount = "
                    SELECT COUNT(*) as total 
                    FROM laporan_surat ls
                    LEFT JOIN anggota_sekolah pengirim ON ls.id_penerima = pengirim.id
                    WHERE (ls.id_penerima = ? OR ls.id_penerima = 0)
                ";

                $params = [$userId];
                $types  = "i";

                if (!empty($search)) {
                    $sqlFilter .= " AND (ls.judul LIKE ? OR ls.isi LIKE ? OR pengirim.nama LIKE ?)";
                    $sqlFilterCount .= " AND (ls.judul LIKE ? OR ls.isi LIKE ? OR pengirim.nama LIKE ?)";
                    $searchParam = "%" . $search . "%";
                    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
                    $types  .= "sss";
                }

                $stmtFiltered = $conn->prepare($sqlFilterCount);
                if ($stmtFiltered === false) {
                    send_response(1, 'Query Error: ' . $conn->error);
                }
                $stmtFiltered->bind_param($types, ...$params);
                $stmtFiltered->execute();
                $resultFiltered = $stmtFiltered->get_result();
                $rowFiltered = $resultFiltered->fetch_assoc();
                $recordsFiltered = isset($rowFiltered['total']) ? $rowFiltered['total'] : 0;
                $stmtFiltered->close();

                // Sorting
                $sortableColumns = [
                    "nama_pengirim"  => "pengirim.nama",
                    "judul"          => "ls.judul",
                    "isi"            => "ls.isi",
                    "tanggal_keluar" => "ls.tanggal_keluar",
                    "status"         => "ls.status"
                ];
                $orderBy = " ORDER BY ls.id DESC";
                if (isset($_POST['order'][0]['column']) && isset($_POST['columns'])) {
                    $columnIndex = intval($_POST['order'][0]['column']);
                    $colData = $_POST['columns'][$columnIndex]['data'] ?? '';
                    $colSortOrder = ($_POST['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
                    if (array_key_exists($colData, $sortableColumns)) {
                        $colName = $sortableColumns[$colData];
                        $orderBy = " ORDER BY $colName $colSortOrder";
                    }
                }

                $sqlFilter .= $orderBy . " LIMIT ?, ?";
                $params[] = $start;
                $params[] = $length;
                $types   .= "ii";

                $stmtData = $conn->prepare($sqlFilter);
                if ($stmtData === false) {
                    send_response(1, 'Query Error: ' . $conn->error);
                }
                $stmtData->bind_param($types, ...$params);
                $stmtData->execute();
                $dataQuery = $stmtData->get_result();
                if (!$dataQuery) {
                    send_response(1, 'Query Error: ' . $stmtData->error);
                }

                $data = [];
                $no = $start + 1;
                while ($row = $dataQuery->fetch_assoc()) {
                    $isiSingkat = mb_strimwidth(strip_tags($row['isi']), 0, 50, "...");
                    $tglKeluar  = date('d M Y H:i', strtotime($row['tanggal_keluar']));
                    $badgeClass = ($row['status'] === 'dibaca') ? 'badge-dibaca' : 'badge-terkirim';
                    $badgeText  = ucfirst($row['status']);
                    $namaPengirim = htmlspecialchars($row['nama_pengirim'] ?? 'SDM/Superadmin');
                    $data[] = [
                        "no"             => $no++,
                        "nama_pengirim"  => $namaPengirim,
                        "judul"          => htmlspecialchars($row['judul']),
                        "isi"            => htmlspecialchars($isiSingkat),
                        "tanggal_keluar" => $tglKeluar,
                        "status"         => '<span class="badge '.$badgeClass.'">'.$badgeText.'</span>',
                        "aksi"           => '<button type="button" class="btn btn-sm btn-info btn-detail" data-id="'.$row['id'].'" data-judul="'.htmlspecialchars($row['judul']).'" data-isi="'.htmlspecialchars($row['isi']).'" data-pengirim="'.htmlspecialchars($namaPengirim).'" data-tanggal="'.$tglKeluar.'">Lihat Detail</button>'
                    ];
                }
                $stmtData->close();
                echo json_encode([
                    "draw"            => $draw,
                    "recordsTotal"    => $recordsTotal,
                    "recordsFiltered" => $recordsFiltered,
                    "data"            => $data
                ], JSON_UNESCAPED_UNICODE);
                exit();
            case 'UpdateStatus':
                $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
                if ($id <= 0) {
                    send_response(2, 'ID surat tidak valid.');
                }
                $stmt = $conn->prepare("UPDATE laporan_surat SET status = 'dibaca' WHERE id = ?");
                if (!$stmt) {
                    send_response(1, 'Query Error: ' . $conn->error);
                }
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    send_response(0, 'Status berhasil diperbarui.');
                } else {
                    send_response(1, 'Gagal memperbarui status: ' . $stmt->error);
                }
                $stmt->close();
                exit();
            case 'DeleteSurat':
                $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
                if ($id <= 0) {
                    send_response(2, 'ID surat tidak valid.');
                }
                $stmt = $conn->prepare("DELETE FROM laporan_surat WHERE id = ?");
                if (!$stmt) {
                    send_response(1, 'Query Error: ' . $conn->error);
                }
                $stmt->bind_param("i", $id);
                if (!$stmt->execute()) {
                    send_response(1, 'Gagal menghapus surat: ' . $stmt->error);
                }
                $stmt->close();
                send_response(0, 'Surat berhasil dihapus.');
                exit();
            default:
                send_response(404, 'Kasus tidak ditemukan.');
        }
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
}

// Jika bukan AJAX, tampilkan halaman HTML
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Surat Peringatan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- FontAwesome, Bootstrap 5, SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/css/sb-admin-2.min.css">
    <style>
        body { font-family: "Nunito", sans-serif; }
        .table-section { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .badge-terkirim { background-color: #0d6efd; }
        .badge-dibaca { background-color: #198754; }
        .modal-header { background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        .modal-title { color: #000; }
        .modal-body { color: #000; font-size: 1rem; line-height: 1.5; }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <!-- End Sidebar -->
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <?php include __DIR__ . '/../../navbar.php'; ?>
                <!-- End Topbar -->
                <!-- Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">
                        <i class="fas fa-envelope-open-text me-2"></i>Laporan Surat Peringatan
                    </h1>
                    <div class="table-section">
                        <h4 class="mb-3">Surat Peringatan untuk Anda</h4>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>No</th>
                                        <th>Dari (Pengirim)</th>
                                        <th>Judul</th>
                                        <th>Isi Singkat</th>
                                        <th>Tanggal Keluar</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php 
                                // Query langsung tanpa DataTables
                                $sql = "
                                    SELECT ls.*, 
                                           pengirim.nama AS nama_pengirim,
                                           pengirim.job_title AS job_title_pengirim
                                    FROM laporan_surat ls
                                    LEFT JOIN anggota_sekolah pengirim ON ls.id_pengirim = pengirim.id
                                    WHERE ls.id_penerima = ? OR ls.id_penerima = 0
                                    ORDER BY ls.tanggal_keluar DESC
                                ";
                                $stmt = $conn->prepare($sql);
                                if ($stmt) {
                                    $stmt->bind_param("i", $userId);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                } else {
                                    die("Gagal prepare statement: " . $conn->error);
                                }
                                if ($result->num_rows > 0):
                                    $no = 1;
                                    while ($row = $result->fetch_assoc()):
                                        $isiSingkat = mb_strimwidth(strip_tags($row['isi']), 0, 50, "...");
                                        $tglKeluar  = date('d M Y H:i', strtotime($row['tanggal_keluar']));
                                        $badgeClass = ($row['status'] === 'dibaca') ? 'badge-dibaca' : 'badge-terkirim';
                                        $badgeText  = ucfirst($row['status']);
                                        $namaPengirim = htmlspecialchars($row['nama_pengirim'] ?? 'SDM/Superadmin');
                                        $jobTitle = trim($row['job_title_pengirim'] ?? '');
                                        $displayPengirim = $namaPengirim;
                                        if (!empty($jobTitle)) {
                                            $displayPengirim .= " - " . htmlspecialchars($jobTitle);
                                        }
                                ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= $displayPengirim; ?></td>
                                        <td><?= htmlspecialchars($row['judul']); ?></td>
                                        <td><?= htmlspecialchars($isiSingkat); ?></td>
                                        <td><?= $tglKeluar; ?></td>
                                        <td>
                                            <span class="badge <?= $badgeClass; ?>">
                                                <?= $badgeText; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info btn-detail" 
                                                data-id="<?= $row['id']; ?>"
                                                data-judul="<?= htmlspecialchars($row['judul']); ?>"
                                                data-isi="<?= htmlspecialchars($row['isi']); ?>"
                                                data-pengirim="<?= $displayPengirim; ?>"
                                                data-tanggal="<?= $tglKeluar; ?>">
                                                Lihat Detail
                                            </button>
                                        </td>
                                    </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Belum ada surat peringatan untuk Anda.</td>
                                    </tr>
                                <?php endif; 
                                $stmt->close();
                                ?>
                                </tbody>
                            </table>
                        </div>
                    </div> <!-- End table-section -->
                </div><!-- End Container Fluid -->
            </div><!-- End Content -->
        </div><!-- End Content Wrapper -->
    </div><!-- End Wrapper -->

    <!-- Modal Detail Surat -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="detailModalLabel">Detail Surat</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <div class="mb-2"><strong>Dari:</strong> <span id="detailPengirim"></span></div>
            <div class="mb-2"><strong>Tanggal:</strong> <span id="detailTanggal"></span></div>
            <hr>
            <div id="detailIsi" style="white-space: pre-wrap;"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>

    <!-- JS Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/js/sb-admin-2.min.js"></script>

    <script>
    $(document).ready(function(){
        $(document).on('click', '.btn-detail', function(){
            var btn = $(this);
            var suratId = btn.data('id');
            $.ajax({
                url: "laporan_surat.php?ajax=1",
                type: "POST",
                data: { case: "UpdateStatus", id: suratId },
                dataType: "json",
                success: function(response){
                    if(response.code === 0){
                        btn.closest('tr').find('span.badge')
                           .removeClass('badge-terkirim')
                           .addClass('badge-dibaca')
                           .text('Dibaca');
                    }
                },
                error: function(){
                    console.log('Gagal memperbarui status surat.');
                }
            });
            $('#detailModalLabel').text(btn.data('judul'));
            $('#detailPengirim').text(btn.data('pengirim'));
            $('#detailTanggal').text(btn.data('tanggal'));
            $('#detailIsi').html(btn.data('isi'));
            var detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
            detailModal.show();
        });
    });
    </script>
</body>
</html>
