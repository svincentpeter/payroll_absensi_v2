<?php
// File: /payroll_absensi_v2/absensi/guru/laporan_surat.php
   
$pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

require_once __DIR__ . '/../helpers.php';
start_session_safe();
generate_csrf_token();

// Jika user sedang dalam mode non-admin, bypass otorisasi khusus,
// sehingga admin (meski role-nya tidak hanya 'P' atau 'TK') bisa mengakses halaman ini.
if (!($_SESSION['non_admin_mode'] ?? false)) {
    // Jika tidak dalam mode non-admin, otorisasi hanya untuk role Pendidik dan Tenaga Kependidikan.
    authorize(['P', 'TK']);
}

// Koneksi database
require_once __DIR__ . '/../koneksi.php';

$nip  = $_SESSION['nip'] ?? '';
$nama = $_SESSION['nama'] ?? '';
if (empty($nip)) {
    die("NIP tidak ditemukan dalam session.");
}

// Tambahkan query untuk ambil userId
$userId = 0;
$stmt = $conn->prepare("SELECT id FROM anggota_sekolah WHERE nip = ? LIMIT 1");
$stmt->bind_param("s", $nip);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $userId = (int)$row['id'];
}
$stmt->close();

if ($userId <= 0) {
    die("ID anggota tidak ditemukan untuk nip: $nip");
}

// Tangani permintaan AJAX (server-side DataTables)
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $case = isset($_POST['case']) ? trim($_POST['case']) : '';
        switch ($case) {

            // =========== [ Server-side DataTables ] ===========
            case 'LoadingSurat':
                // DataTables parameters
                $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
                $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
                $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
                $search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

                // Total record (tanpa filter)
                $sqlTotal = "SELECT COUNT(*) as total FROM laporan_surat WHERE id_penerima = ? OR id_penerima = 0";
                $stmtTotal = $conn->prepare($sqlTotal);
                $stmtTotal->bind_param("i", $userId);
                $stmtTotal->execute();
                $resultTotal = $stmtTotal->get_result();
                $rowTotal = $resultTotal->fetch_assoc();
                $recordsTotal = $rowTotal['total'] ?? 0;
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
                    LEFT JOIN anggota_sekolah pengirim ON ls.id_pengirim = pengirim.id
                    WHERE (ls.id_penerima = ? OR ls.id_penerima = 0)
                ";

                // Persiapan parameter
                $params = [$userId];
                $types  = "i";

                // Jika ada pencarian (search)
                if (!empty($search)) {
                    $sqlFilter .= " AND (ls.judul LIKE ? OR ls.isi LIKE ? OR pengirim.nama LIKE ?)";
                    $sqlFilterCount .= " AND (ls.judul LIKE ? OR ls.isi LIKE ? OR pengirim.nama LIKE ?)";
                    $searchParam = "%" . $search . "%";
                    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
                    $types .= "sss";
                }

                // Hitung filtered record
                $stmtFilterCount = $conn->prepare($sqlFilterCount);
                if (!$stmtFilterCount) {
                    echo json_encode([
                        "draw" => $draw,
                        "recordsTotal" => 0,
                        "recordsFiltered" => 0,
                        "data" => []
                    ]);
                    exit();
                }
                $stmtFilterCount->bind_param($types, ...$params);
                $stmtFilterCount->execute();
                $resultFilterCount = $stmtFilterCount->get_result();
                $rowFilterCount = $resultFilterCount->fetch_assoc();
                $recordsFiltered = $rowFilterCount['total'] ?? 0;
                $stmtFilterCount->close();

                // Sorting
                $sortableColumns = [
                    "nama_pengirim"  => "pengirim.nama",
                    "judul"          => "ls.judul",
                    "isi"            => "ls.isi",
                    "tanggal_keluar" => "ls.tanggal_keluar",
                    "status"         => "ls.status"
                ];
                $orderBy = " ORDER BY ls.id DESC"; // default
                if (isset($_POST['order'][0]['column']) && isset($_POST['columns'])) {
                    $columnIndex = intval($_POST['order'][0]['column']);
                    $colData = $_POST['columns'][$columnIndex]['data'] ?? '';
                    $colSortOrder = ($_POST['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
                    if (array_key_exists($colData, $sortableColumns)) {
                        $colName = $sortableColumns[$colData];
                        $orderBy = " ORDER BY $colName $colSortOrder";
                    }
                }

                // Limit (pagination)
                $sqlFilter .= $orderBy . " LIMIT ?, ?";
                $params[] = $start;
                $params[] = $length;
                $types .= "ii";

                // Eksekusi data query
                $stmtData = $conn->prepare($sqlFilter);
                $stmtData->bind_param($types, ...$params);
                $stmtData->execute();
                $dataQuery = $stmtData->get_result();

                // Menyiapkan data untuk DataTables
                $data = [];
                $no = $start + 1;
                while ($row = $dataQuery->fetch_assoc()) {
                    $isiSingkat   = mb_strimwidth(strip_tags($row['isi']), 0, 50, "...");
                    $tglKeluar    = date('d M Y H:i', strtotime($row['tanggal_keluar']));
                    $badgeClass   = ($row['status'] === 'dibaca') ? 'badge-dibaca' : 'badge-terkirim';
                    $badgeText    = ucfirst($row['status']);
                    $namaPengirim = htmlspecialchars($row['nama_pengirim'] ?? 'SDM/Superadmin');

                    $data[] = [
                        "no"             => $no++,
                        "nama_pengirim"  => $namaPengirim,
                        "judul"          => htmlspecialchars($row['judul']),
                        "isi"            => htmlspecialchars($isiSingkat),
                        "tanggal_keluar" => $tglKeluar,
                        "status"         => '<span class="badge ' . $badgeClass . '">' . $badgeText . '</span>',
                        "aksi"           => '
                            <button type="button" class="btn btn-sm btn-info btn-detail"
                                    data-id="' . $row['id'] . '"
                                    data-judul="' . htmlspecialchars($row['judul']) . '"
                                    data-isi="' . htmlspecialchars($row['isi']) . '"
                                    data-pengirim="' . $namaPengirim . '"
                                    data-tanggal="' . $tglKeluar . '">
                                Lihat Detail
                            </button>'
                    ];
                }
                $stmtData->close();

                // Kirim response JSON
                echo json_encode([
                    "draw"            => $draw,
                    "recordsTotal"    => $recordsTotal,
                    "recordsFiltered" => $recordsFiltered,
                    "data"            => $data
                ], JSON_UNESCAPED_UNICODE);
                exit();

                // =========== [ Update Status Surat ] ===========
            case 'UpdateStatus':
                $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
                if ($id <= 0) {
                    echo json_encode(["code" => 2, "message" => "ID surat tidak valid."]);
                    exit();
                }
                $stmt = $conn->prepare("UPDATE laporan_surat SET status = 'dibaca' WHERE id = ?");
                if (!$stmt) {
                    echo json_encode(["code" => 1, "message" => "Query Error: " . $conn->error]);
                    exit();
                }
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    echo json_encode(["code" => 0, "message" => "Status berhasil diperbarui."]);
                } else {
                    echo json_encode(["code" => 1, "message" => "Gagal memperbarui status: " . $stmt->error]);
                }
                $stmt->close();
                exit();

                // =========== [ Delete Surat (opsional) ] ===========
            case 'DeleteSurat':
                $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
                if ($id <= 0) {
                    echo json_encode(["code" => 2, "message" => "ID surat tidak valid."]);
                    exit();
                }
                $stmt = $conn->prepare("DELETE FROM laporan_surat WHERE id = ?");
                if (!$stmt) {
                    echo json_encode(["code" => 1, "message" => "Query Error: " . $conn->error]);
                    exit();
                }
                $stmt->bind_param("i", $id);
                if (!$stmt->execute()) {
                    echo json_encode(["code" => 1, "message" => "Gagal menghapus surat: " . $stmt->error]);
                } else {
                    echo json_encode(["code" => 0, "message" => "Surat berhasil dihapus."]);
                }
                $stmt->close();
                exit();

            default:
                echo json_encode(["code" => 404, "message" => "Kasus tidak ditemukan."]);
                exit();
        }
    } else {
        echo json_encode(["code" => 405, "message" => "Metode Permintaan Tidak Diizinkan."]);
        exit();
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

    <!-- FontAwesome, Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- DataTables CSS (Bootstrap 5) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

    <!-- DataTables Buttons CSS (untuk export PDF/Excel/Print) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">

    <!-- DataTables Responsive CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">

    <!-- SB Admin 2 (opsional) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">

    <style>
        body {
            font-family: "Nunito", sans-serif;
        }
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
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }

        .badge-terkirim {
            background-color: #0d6efd;
        }

        .badge-dibaca {
            background-color: #198754;
        }

        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .modal-title {
            color: #000;
        }

        .modal-body {
            color: #000;
            font-size: 1rem;
            line-height: 1.5;
        }
    </style>
</head>

<body id="page-top">

    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <!-- End Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <?php include __DIR__ . '/../navbar.php'; ?>
                <!-- End Topbar -->
                <?php include __DIR__ . '/../breadcrumb.php'; ?>
                <!-- Page Content -->
                <div class="container-fluid">
<h1 class="page-title">
        <i class="fas fa-envelope-open-text me-2"></i>Laporan Surat
    </h1>
                    <!-- Card untuk tabel surat -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-white">
                                <i class="fas fa-envelope me-1"></i> Daftar Surat
                            </h6>
                            <!-- Bisa tambahkan tombol di sini jika perlu -->
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <!-- Tabel DataTables (server-side) -->
                                <table id="tabelSurat"
                                    class="table table-striped table-bordered table-hover"
                                    style="width:100%; white-space: normal; word-wrap: break-word;">

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
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div> <!-- End card-body -->
                    </div> <!-- End card -->

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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <!-- DataTables Buttons (Export PDF/Excel/Print) + dependencies -->
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>

    <!-- DataTables Responsive JS -->
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>

    <!-- SB Admin 2 (opsional) -->
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>

    <script>
        $(document).ready(function() {
            var table = $('#tabelSurat').DataTable({
                processing: true,
                serverSide: true,
                responsive: true, // Boleh diaktifkan agar kolom menyusut/tersembunyi
                autoWidth: false, // Boleh tetap false agar lebar kolom tidak 'lompat'

                ajax: {
                    url: 'laporan_surat.php?ajax=1',
                    type: 'POST',
                    data: {
                        case: 'LoadingSurat'
                    }
                },
                columns: [{
                        data: 'no',
                        name: 'no'
                    },
                    {
                        data: 'nama_pengirim',
                        name: 'nama_pengirim'
                    },
                    {
                        data: 'judul',
                        name: 'judul'
                    },
                    {
                        data: 'isi',
                        name: 'isi'
                    },
                    {
                        data: 'tanggal_keluar',
                        name: 'tanggal_keluar'
                    },
                    {
                        data: 'status',
                        name: 'status'
                    },
                    {
                        data: 'aksi',
                        name: 'aksi',
                        orderable: false,
                        searchable: false
                    }
                ],
                dom: 'Bfrtip',
                buttons: [{
                        extend: 'excelHtml5',
                        className: 'btn btn-success btn-sm',
                        text: '<i class="fas fa-file-excel me-1"></i> Export Excel'
                    },
                    {
                        extend: 'pdfHtml5',
                        className: 'btn btn-danger btn-sm',
                        text: '<i class="fas fa-file-pdf me-1"></i> Export PDF',
                        orientation: 'portrait',
                        pageSize: 'A4'
                    },
                    {
                        extend: 'print',
                        className: 'btn btn-secondary btn-sm',
                        text: '<i class="fas fa-print me-1"></i> Print'
                    }
                ]
            });


            // Event untuk tombol "Lihat Detail" dsb. tetap sama
            $(document).on('click', '.btn-detail', function() {
                var btn = $(this);
                var suratId = btn.data('id');

                $.ajax({
                    url: "laporan_surat.php?ajax=1",
                    type: "POST",
                    data: {
                        case: "UpdateStatus",
                        id: suratId
                    },
                    dataType: "json",
                    success: function(response) {
                        if (response.code === 0) {
                            btn.closest('tr').find('span.badge')
                                .removeClass('badge-terkirim')
                                .addClass('badge-dibaca')
                                .text('Dibaca');
                        }
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
<?php
// Tutup koneksi database menggunakan fungsi dari helpers.php
close_db_connection();
?>