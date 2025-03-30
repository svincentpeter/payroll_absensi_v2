<?php
// File: /payroll_absensi_v2/absensi/guru/pengajuan_surat_ijin.php

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

// ======================
// 1. Tangani POST Upload / Insert Biasa (Non-AJAX)
// ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['ajax'])) {
    // Verifikasi CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');

    // Bersihkan input
    $judul_surat = sanitize_input($_POST['judul_surat'] ?? '');
    $tanggal     = sanitize_input($_POST['tanggal'] ?? '');
    $pesan       = sanitize_input($_POST['pesan'] ?? '');
    $tipe_ijin   = sanitize_input($_POST['tipe_ijin'] ?? '');

    // --- Proses Upload File Surat Izin ---
    $lampiran = ''; // Default jika user tidak mengupload

    if (isset($_FILES['upload_surat']) && $_FILES['upload_surat']['error'] === UPLOAD_ERR_OK) {
        $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $fileName = $_FILES['upload_surat']['name'];
        $tmpName  = $_FILES['upload_surat']['tmp_name'];
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($fileExt, $allowedExtensions)) {
            $_SESSION['absensi_error'] = "Ekstensi file tidak diperbolehkan (pdf, doc, docx, jpg, jpeg, png).";
            header("Location: pengajuan_surat_ijin.php");
            exit();
        }

        // Validasi MIME type
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpName);
        finfo_close($finfo);
        $allowedMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png'
        ];
        if (!in_array($mimeType, $allowedMimes)) {
            $_SESSION['absensi_error'] = "Tipe file ($mimeType) tidak diperbolehkan.";
            header("Location: pengajuan_surat_ijin.php");
            exit();
        }

        // Folder upload
        $uploadDir = __DIR__ . '/../uploads/surat_ijin/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Buat nama file baru
        $newFileName = $nip . '_' . time() . '.' . $fileExt;
        $destPath = $uploadDir . $newFileName;

        if (!move_uploaded_file($tmpName, $destPath)) {
            $_SESSION['absensi_error'] = "Gagal mengupload file surat izin.";
            header("Location: pengajuan_surat_ijin.php");
            exit();
        }

        // Jika sukses, set $lampiran
        $lampiran = $newFileName;
    }

    // Insert ke DB (tambahkan kolom lampiran)
    if (!empty($judul_surat) && !empty($tanggal) && !empty($pesan) && !empty($tipe_ijin)) {
        $insert_query = "INSERT INTO pengajuan_ijin 
        (nip, nama, judul_surat, tanggal, pesan, tipe_ijin, status, lampiran) 
        VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?)";
        $stmt = $conn->prepare($insert_query);
        if (!$stmt) {
            die("Gagal prepare statement: " . $conn->error);
        }
        $stmt->bind_param("sssssss", $nip, $nama, $judul_surat, $tanggal, $pesan, $tipe_ijin, $lampiran);
        if ($stmt->execute()) {
            $_SESSION['absensi_success'] = "Pengajuan surat izin berhasil diajukan!";
        } else {
            $_SESSION['absensi_error'] = "Terjadi kesalahan: " . $conn->error;
        }
        $stmt->close();
    } else {
        $_SESSION['absensi_error'] = "Semua field harus diisi.";
    }
    header("Location: pengajuan_surat_ijin.php");
    exit();
}

// ======================
// 2. Tangani Permintaan AJAX (server-side DataTables) untuk Daftar Izin
// ======================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $case = $_POST['case'] ?? '';
        switch ($case) {
            case 'LoadingPengajuanIzin':
                // DataTables parameters
                $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
                $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
                $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
                $search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

                // Total record (tanpa filter) milik user
                $sqlTotal = "SELECT COUNT(*) as total FROM pengajuan_ijin WHERE nip = ?";
                $stmtTotal = $conn->prepare($sqlTotal);
                $stmtTotal->bind_param("s", $nip);
                $stmtTotal->execute();
                $resTotal = $stmtTotal->get_result();
                $rowTotal = $resTotal->fetch_assoc();
                $recordsTotal = $rowTotal['total'] ?? 0;
                $stmtTotal->close();

                // Query dasar
                $sqlFilter = "SELECT * FROM pengajuan_ijin WHERE nip = ?";
                $sqlFilterCount = "SELECT COUNT(*) as total FROM pengajuan_ijin WHERE nip = ?";

                // Parameter
                $params = [$nip];
                $types  = "s";

                // Jika ada pencarian
                if (!empty($search)) {
                    $sqlFilter      .= " AND (judul_surat LIKE ? OR tanggal LIKE ? OR pesan LIKE ? OR tipe_ijin LIKE ? OR status LIKE ?)";
                    $sqlFilterCount .= " AND (judul_surat LIKE ? OR tanggal LIKE ? OR pesan LIKE ? OR tipe_ijin LIKE ? OR status LIKE ?)";
                    $searchParam = "%" . $search . "%";
                    for ($i = 0; $i < 5; $i++) {
                        $params[] = $searchParam;
                    }
                    $types .= "sssss";
                }

                // Hitung filtered
                $stmtCount = $conn->prepare($sqlFilterCount);
                if (!$stmtCount) {
                    echo json_encode(["draw" => $draw, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => []]);
                    exit();
                }
                $stmtCount->bind_param($types, ...$params);
                $stmtCount->execute();
                $resCount = $stmtCount->get_result();
                $rowCount = $resCount->fetch_assoc();
                $recordsFiltered = $rowCount['total'] ?? 0;
                $stmtCount->close();

                // Sorting
                $sortableColumns = [
                    "nama"         => "nama",
                    "judul_surat"  => "judul_surat",
                    "tanggal"      => "tanggal",
                    "pesan"        => "pesan",
                    "tipe_ijin"    => "tipe_ijin",
                    "status"       => "status"
                ];
                $orderBy = " ORDER BY id DESC";
                if (isset($_POST['order'][0]['column']) && isset($_POST['columns'])) {
                    $colIndex = intval($_POST['order'][0]['column']);
                    $colData  = $_POST['columns'][$colIndex]['data'] ?? '';
                    $colDir   = ($_POST['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
                    if (array_key_exists($colData, $sortableColumns)) {
                        $colName = $sortableColumns[$colData];
                        $orderBy = " ORDER BY $colName $colDir";
                    }
                }

                // Limit
                $sqlFilter .= $orderBy . " LIMIT ?, ?";
                $params[] = $start;
                $params[] = $length;
                $types .= "ii";

                // Eksekusi
                $stmtData = $conn->prepare($sqlFilter);
                $stmtData->bind_param($types, ...$params);
                $stmtData->execute();
                $resultData = $stmtData->get_result();

                // Susun data
                $data = [];
                while ($row = $resultData->fetch_assoc()) {
                    // Badge
                    $statusBadge = '';
                    if ($row['status'] === 'Pending') {
                        $statusBadge = '<span class="badge badge-pending">Pending</span>';
                    } elseif ($row['status'] === 'Diterima') {
                        $statusBadge = '<span class="badge badge-diterima">Diterima</span>';
                    } else {
                        $statusBadge = '<span class="badge badge-ditolak">Ditolak</span>';
                    }

                    // File surat (review)
                    $reviewLink = '<em>Tidak ada</em>';
                    if (!empty($row['lampiran'])) {
                        $reviewLink = '<a href="/payroll_absensi_v2/uploads/surat_ijin/'
                            . htmlspecialchars($row['lampiran'])
                            . '" target="_blank" class="btn btn-sm btn-info">Lihat Surat</a>';
                    }

                    $data[] = [
                        "nama"        => htmlspecialchars($row['nama']),
                        "judul_surat" => htmlspecialchars($row['judul_surat']),
                        "tanggal"     => htmlspecialchars($row['tanggal']),
                        "pesan"       => htmlspecialchars($row['pesan']),
                        "tipe_ijin"   => htmlspecialchars($row['tipe_ijin']),
                        "status"      => $statusBadge,
                        "review_surat" => $reviewLink
                    ];
                }
                $stmtData->close();

                // Kirim JSON
                echo json_encode([
                    "draw"            => $draw,
                    "recordsTotal"    => $recordsTotal,
                    "recordsFiltered" => $recordsFiltered,
                    "data"            => $data
                ], JSON_UNESCAPED_UNICODE);
                exit();

            default:
                echo json_encode(["code" => 404, "message" => "Kasus AJAX tidak ditemukan."]);
                exit();
        }
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Pengajuan Surat Izin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- FontAwesome, Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">

    <!-- DataTables CSS (Bootstrap 5) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

    <!-- DataTables Buttons CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">

    <!-- DataTables Responsive CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">

    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        body {
            font-family: "Nunito", sans-serif;
        }

        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }

        .badge-pending {
            background-color: #f6c23e;
            color: #fff;
        }

        .badge-ditolak {
            background-color: #e74a3b;
            color: #fff;
        }

        .badge-diterima {
            background-color: #1cc88a;
            color: #fff;
        }

        .form-section label,
        .form-section .form-label,
        .form-section .form-control,
        .form-section .form-select,
        .form-section .btn {
            color: #212529 !important;
            /* Warna teks hitam khas Bootstrap */
        }

        /* Jika ingin latar belakang form tetap putih dan sedikit bayangan: */
        .form-section {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        /* Untuk label agar sedikit lebih tebal dan margin bawah kecil: */
        .form-section label.form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        /* Atur margin antar form-group */
        .form-section .form-group {
            margin-bottom: 1rem;
        }

        .table-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
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

                <!-- Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-envelope"></i> Pengajuan Surat Izin</h1>

                    <div class="row">
                        <!-- Form Pengajuan -->
                        <div class="col-lg-6">
                            <div class="form-section mb-4">
                                <h4 class="mb-4">Form Pengajuan</h4>
                                <?php
                                // Tampilkan notifikasi success/error
                                if (!empty($_SESSION['absensi_success'])) {
                                    echo '<div class="alert alert-success">' . $_SESSION['absensi_success'] . '</div>';
                                    unset($_SESSION['absensi_success']);
                                }
                                if (!empty($_SESSION['absensi_error'])) {
                                    echo '<div class="alert alert-danger">' . $_SESSION['absensi_error'] . '</div>';
                                    unset($_SESSION['absensi_error']);
                                }
                                ?>
                                <form action="" method="POST" enctype="multipart/form-data">
                                    <!-- Sertakan CSRF token -->
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                                    <div class="form-group mb-3">
                                        <label for="judul_surat">Judul Surat</label>
                                        <input type="text" name="judul_surat" id="judul_surat" class="form-control" required>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label for="tanggal">Tanggal Izin</label>
                                        <input type="text" name="tanggal" id="tanggal" class="form-control" required>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label for="pesan">Pesan</label>
                                        <textarea name="pesan" id="pesan" class="form-control" rows="5" required></textarea>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label for="tipe_ijin">Tipe Izin/Cuti</label>
                                        <select name="tipe_ijin" id="tipe_ijin" class="form-control" required>
                                            <option value="">Pilih tipe izin</option>
                                            <option value="Sakit">Sakit</option>
                                            <option value="Cuti Biasa">Cuti Biasa</option>
                                            <option value="Ijin Lainnya">Ijin Lainnya</option>
                                        </select>
                                    </div>
                                    <!-- Tambahan: Input File Upload Surat Izin -->
                                    <div class="form-group mb-3">
                                        <label for="upload_surat">Upload Surat Izin</label>
                                        <input type="file" name="upload_surat" id="upload_surat" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    </div>
                                    <button type="submit" class="btn btn-success">Ajukan Izin</button>
                                </form>
                            </div>
                        </div>

                        <!-- Daftar Pengajuan (Server-side DataTables) -->
                        <div class="col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 fw-bold text-white">
                                        <i class="fas fa-list me-1"></i> Daftar Izin Saya
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="pengajuanTable" class="table table-bordered table-hover dt-responsive nowrap" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Nama</th>
                                                    <th>Judul Surat</th>
                                                    <th>Tanggal</th>
                                                    <th>Pesan</th>
                                                    <th>Tipe</th>
                                                    <th>Status</th>
                                                    <th>Review Surat</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- DataTables server-side akan isi -->
                                            </tbody>
                                        </table>
                                    </div><!-- End Table Responsive -->
                                </div><!-- End card-body -->
                            </div><!-- End card -->
                        </div>
                    </div>
                </div><!-- End Container Fluid -->
            </div><!-- End Content -->
        </div><!-- End Content Wrapper -->
    </div><!-- End Wrapper -->

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- SB Admin 2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>

    <!-- Flatpickr -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>

    <!-- DataTables + Plugins -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>

    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            // Inisialisasi Flatpickr
            flatpickr("#tanggal", {
                dateFormat: "Y-m-d",
                minDate: "today",
                locale: "id"
            });

            // DataTables Server-side
            var pengajuanTable = $('#pengajuanTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                ajax: {
                    url: 'pengajuan_surat_ijin.php?ajax=1',
                    type: 'POST',
                    data: {
                        case: 'LoadingPengajuanIzin'
                    }
                },
                columns: [{
                        data: 'nama',
                        name: 'nama'
                    },
                    {
                        data: 'judul_surat',
                        name: 'judul_surat'
                    },
                    {
                        data: 'tanggal',
                        name: 'tanggal'
                    },
                    {
                        data: 'pesan',
                        name: 'pesan'
                    },
                    {
                        data: 'tipe_ijin',
                        name: 'tipe_ijin'
                    },
                    {
                        data: 'status',
                        name: 'status'
                    },
                    {
                        data: 'review_surat',
                        name: 'review_surat',
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
        });
    </script>
</body>

</html>
<?php
// Tutup koneksi database menggunakan fungsi dari helpers.php
close_db_connection();
?>