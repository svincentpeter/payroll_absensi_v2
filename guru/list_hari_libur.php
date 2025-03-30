<?php
// File: /payroll_absensi_v2/absensi/guru/list_hari_libur.php

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
// Tangani permintaan AJAX (server-side DataTables)
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $case = isset($_POST['case']) ? trim($_POST['case']) : '';
        switch ($case) {
            // =========== [ Server-side DataTables untuk Holidays ] ===========
            case 'LoadingHolidays':
                // DataTables parameters
                $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
                $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
                $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
                $search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

                // Total record (tanpa filter)
                $sqlTotal = "SELECT COUNT(*) as total FROM holidays";
                $resultTotal = mysqli_query($conn, $sqlTotal);
                $rowTotal = $resultTotal ? mysqli_fetch_assoc($resultTotal) : [];
                $recordsTotal = $rowTotal['total'] ?? 0;

                // Query dasar
                $sqlFilter = "SELECT * FROM holidays WHERE 1=1";
                $sqlFilterCount = "SELECT COUNT(*) as total FROM holidays WHERE 1=1";

                $params = [];
                $types  = "";

                // Jika ada pencarian (search) -> cari di kolom holiday_title, holiday_desc
                if (!empty($search)) {
                    $sqlFilter      .= " AND (holiday_title LIKE ? OR holiday_desc LIKE ?)";
                    $sqlFilterCount .= " AND (holiday_title LIKE ? OR holiday_desc LIKE ?)";
                    $likeParam = "%" . $search . "%";
                    $params    = [$likeParam, $likeParam];
                    $types     = "ss";
                }

                // Hitung filtered record
                $stmtFiltered = $conn->prepare($sqlFilterCount);
                if ($stmtFiltered === false) {
                    echo json_encode([
                        "draw" => $draw,
                        "recordsTotal" => 0,
                        "recordsFiltered" => 0,
                        "data" => []
                    ]);
                    exit();
                }
                if (!empty($params)) {
                    $stmtFiltered->bind_param($types, ...$params);
                }
                $stmtFiltered->execute();
                $resultFilterCount = $stmtFiltered->get_result();
                $rowFilterCount = $resultFilterCount->fetch_assoc();
                $recordsFiltered = $rowFilterCount['total'] ?? 0;
                $stmtFiltered->close();

                // Sorting (sesuaikan dengan kolom di DataTables)
                $sortableColumns = [
                    "holiday_title" => "holiday_title",
                    "holiday_desc"  => "holiday_desc",
                    "holiday_date"  => "holiday_date",
                    "holiday_type"  => "holiday_type"
                ];
                $orderBy = " ORDER BY holiday_date ASC"; // default
                if (isset($_POST['order'][0]['column']) && isset($_POST['columns'])) {
                    $columnIndex = intval($_POST['order'][0]['column']);
                    $colData = $_POST['columns'][$columnIndex]['data'] ?? '';
                    $colSortOrder = ($_POST['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
                    if (array_key_exists($colData, $sortableColumns)) {
                        $colName = $sortableColumns[$colData];
                        $orderBy = " ORDER BY $colName $colSortOrder";
                    }
                }

                // Limit (pagination)
                $sqlFilter .= $orderBy . " LIMIT ?, ?";
                // Bind param start & length
                if (!empty($params)) {
                    // Sudah ada param
                    $params[] = $start;
                    $params[] = $length;
                    $types   .= "ii";
                } else {
                    // Belum ada param
                    $params = [$start, $length];
                    $types  = "ii";
                }

                // Eksekusi query data
                $stmtData = $conn->prepare($sqlFilter);
                if (!empty($params)) {
                    $stmtData->bind_param($types, ...$params);
                }
                $stmtData->execute();
                $dataQuery = $stmtData->get_result();

                // Menyiapkan data untuk DataTables
                $data = [];
                $no = $start + 1;
                while ($row = $dataQuery->fetch_assoc()) {
                    $holidayId    = $row['id'] ?? 0;
                    $title        = htmlspecialchars($row['holiday_title'] ?? '');
                    $desc         = htmlspecialchars($row['holiday_desc'] ?? '');
                    $date         = htmlspecialchars($row['holiday_date'] ?? '');
                    $type         = htmlspecialchars($row['holiday_type'] ?? '');
                    $tanggal      = date('d-m-Y', strtotime($date));

                    if ($type === 'wajib') {
                        $badgeClass = 'badge-tanggal_merah';
                        $badgeText  = 'Tanggal Merah';
                    } else {
                        $badgeClass = 'badge-libur_biasa';
                        $badgeText  = 'Libur Biasa';
                    }

                    $data[] = [
                        "no"            => $no++,
                        "holiday_title" => $title,
                        "holiday_desc"  => $desc,
                        "holiday_date"  => $tanggal,
                        "holiday_type"  => '<span class="badge ' . $badgeClass . '">' . $badgeText . '</span>'
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
    <title>Daftar Hari Libur</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- FontAwesome, Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- DataTables CSS (Bootstrap 5) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- DataTables Buttons CSS (untuk export PDF/Excel/Print) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">
    <!-- DataTables Responsive CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">

    <!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">

    <style>
        body {
            font-family: "Nunito", sans-serif;
        }

        /* Card-header dengan gradasi ala laporan_surat.php */
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }

        .badge-libur_biasa {
            background-color: rgb(146, 146, 146);
            color: #fff;
            padding: 0.4em 0.6em;
            border-radius: 0.25rem;
        }

        .badge-tanggal_merah {
            background-color: rgb(255, 0, 0);
            color: #fff;
            padding: 0.4em 0.6em;
            border-radius: 0.25rem;
        }
    </style>
</head>

<body id="page-top">

    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <?php include __DIR__ . '/../navbar.php'; ?>
                <!-- End Topbar -->

                <!-- Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">
                        <i class="fas fa-calendar-alt me-2"></i>Daftar Hari Libur
                    </h1>

                    <!-- Card untuk tabel hari libur -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-white">
                                <i class="fas fa-calendar-check me-1"></i> List Hari Libur
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <!-- Tabel DataTables (server-side) -->
                                <table id="holidayTable"
                                    class="table table-bordered table-hover dt-responsive nowrap"
                                    style="width:100%;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>No</th>
                                            <th>Judul Hari Libur</th>
                                            <th>Deskripsi Hari Libur</th>
                                            <th>Tanggal Hari Libur</th>
                                            <th>Jenis Libur</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Tbody dikosongkan, karena DataTables server-side akan mengisinya -->
                                    </tbody>
                                </table>
                            </div><!-- End .table-responsive -->
                        </div><!-- End card-body -->
                    </div><!-- End card -->
                </div><!-- End container-fluid -->
            </div><!-- End content -->

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

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <!-- DataTables Buttons + dependencies -->
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
            // Inisialisasi DataTables dengan server-side
            $('#holidayTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: true, // agar responsive + ikon plus/minus di layar sempit
                autoWidth: false,

                ajax: {
                    url: 'list_hari_libur.php?ajax=1', // Memanggil file ini sendiri
                    type: 'POST',
                    data: {
                        case: 'LoadingHolidays'
                    }
                },
                columns: [{
                        data: 'no',
                        name: 'no'
                    },
                    {
                        data: 'holiday_title',
                        name: 'holiday_title'
                    },
                    {
                        data: 'holiday_desc',
                        name: 'holiday_desc'
                    },
                    {
                        data: 'holiday_date',
                        name: 'holiday_date'
                    },
                    {
                        data: 'holiday_type',
                        name: 'holiday_type'
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
                ],
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.13.4/i18n/id.json"
                }
            });
        });
    </script>

</body>

</html>
<?php
// Tutup koneksi database menggunakan fungsi dari helpers.php
close_db_connection();
?>