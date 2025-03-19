<?php
// File: /payroll_absensi_v2/sdm/pembuatan_surat.php

// =========================
// 1. Pengaturan Awal
// =========================
require_once __DIR__ . '/../helpers.php';   // sesuaikan path
start_session_safe();
init_error_handling();

// Hanya role sdm & superadmin yang boleh mengakses
authorize(['M:SDM', 'M:Superadmin']);

require_once __DIR__ . '/../koneksi.php';

// Hapus output buffering jika ada
if (ob_get_length()) {
    ob_end_clean();
}

// =========================
// 2. Menangani Permintaan AJAX (CRUD Surat)
// =========================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $case = isset($_POST['case']) ? trim($_POST['case']) : '';
        switch ($case) {
            case 'LoadingSurat':
                LoadingSurat($conn);
                break;
            case 'AddSurat':
                AddSurat($conn);
                break;
            case 'DeleteSurat':
                DeleteSurat($conn);
                break;
            case 'ViewSuratDetail':
                ViewSuratDetail($conn);
                break;
                
            default:
                send_response(404, 'Kasus tidak ditemukan.');
        }
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    exit();
}

// =========================
// 3. Fungsi-Fungsi CRUD
// =========================

/**
 * Memuat data surat secara server-side (DataTables).
 */
function LoadingSurat($conn) {
    // DataTables parameters
    $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

    // Ambil total records tanpa filter
    $sqlTotal = "SELECT COUNT(*) as total FROM laporan_surat";
    $resultTotal = mysqli_query($conn, $sqlTotal);
    if (!$resultTotal) {
        send_response(1, 'Query Error: ' . mysqli_error($conn));
    }
    $rowTotal = mysqli_fetch_assoc($resultTotal);
    $recordsTotal = $rowTotal['total'];

    // Query dasar + JOIN untuk ambil nama penerima
    $sqlFilter = "
        SELECT ls.*, penerima.nama AS nama_penerima 
        FROM laporan_surat ls
        LEFT JOIN anggota_sekolah penerima ON ls.id_penerima = penerima.id
        WHERE 1=1
    ";
    $sqlFilterCount = "
        SELECT COUNT(*) as total 
        FROM laporan_surat ls
        LEFT JOIN anggota_sekolah penerima ON ls.id_penerima = penerima.id
        WHERE 1=1
    ";

    // Kumpulkan parameter untuk prepared statement
    $params = [];
    $types  = "";

    // Jika ada pencarian
    if (!empty($search)) {
        $sqlFilter .= " AND (ls.judul LIKE ? OR ls.isi LIKE ? OR penerima.nama LIKE ?)";
        $sqlFilterCount .= " AND (ls.judul LIKE ? OR ls.isi LIKE ? OR penerima.nama LIKE ?)";
        $searchParam = "%".$search."%";

        $params[] = $searchParam; 
        $params[] = $searchParam; 
        $params[] = $searchParam; 
        $types   .= "sss";
    }

    // Hitung recordsFiltered
    $stmtFiltered = $conn->prepare($sqlFilterCount);
    if ($stmtFiltered === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    if (!empty($params)) {
        $stmtFiltered->bind_param($types, ...$params);
    }
    $stmtFiltered->execute();
    $resultFiltered = $stmtFiltered->get_result();
    if (!$resultFiltered) {
        send_response(1, 'Query Error: ' . $stmtFiltered->error);
    }
    $rowFiltered = $resultFiltered->fetch_assoc();
    $recordsFiltered = isset($rowFiltered['total']) ? $rowFiltered['total'] : 0;
    $stmtFiltered->close();

    // Sorting
    $sortableColumns = [
        "nama_penerima"  => "penerima.nama",
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

    // Paginasi
    $sqlFilter .= $orderBy . " LIMIT ?, ?";
    $params[] = $start;
    $params[] = $length;
    $types   .= "ii";

    // Eksekusi query data
    $stmtData = $conn->prepare($sqlFilter);
    if ($stmtData === false) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    if (!empty($params)) {
        $stmtData->bind_param($types, ...$params);
    }
    $stmtData->execute();
    $dataQuery = $stmtData->get_result();
    if (!$dataQuery) {
        send_response(1, 'Query Error: ' . $stmtData->error);
    }

    // Susun data
    $data = [];
    $no = $start + 1;
    while ($row = $dataQuery->fetch_assoc()) {
        // Buat ringkasan isi
        $isiSingkat = mb_strimwidth(strip_tags($row['isi']), 0, 50, "...");
    
        // Badge status
        if ($row['status'] === 'terkirim') {
            $badgeStatus = '<span class="badge bg-primary" style="color: #000 !important;">Terkirim</span>';
        } else {
            $badgeStatus = '<span class="badge bg-success" style="color: #000 !important;">Dibaca</span>';
        }
    
        // Tanggal
        $tanggal = date('d M Y H:i', strtotime($row['tanggal_keluar']));
    
        // Jika id_penerima = 0, tampilkan "Semua Anggota"
        $nama_penerima = ($row['id_penerima'] == 0) ? 'Semua Anggota' : ($row['nama_penerima'] ?? '-');
    
        // Tombol aksi (View Detail & Delete)
        $aksi = '
        <div class="dropdown">
          <button class="btn" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-three-dots-vertical"></i>
          </button>
          <ul class="dropdown-menu">
            <li>
              <a class="dropdown-item btn-view" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '">
                <i class="fas fa-eye"></i> View Detail
              </a>
            </li>
            <li>
              <a class="dropdown-item btn-delete" href="javascript:void(0)" data-id="' . htmlspecialchars($row['id']) . '">
                <i class="fas fa-trash-alt"></i> Hapus
              </a>
            </li>
          </ul>
        </div>';
    
        $data[] = [
            "no"             => $no++,
            "nama_penerima"  => $nama_penerima,
            "judul"          => htmlspecialchars($row['judul']),
            "isi"            => htmlspecialchars($isiSingkat),
            "tanggal_keluar" => $tanggal,
            "status"         => $badgeStatus,
            "aksi"           => $aksi
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
}

/**
 * Menambahkan Surat baru.
 */
function AddSurat($conn) {
    $id_pengirim = $_SESSION['id'] ?? 0; // ID user yang login
    $id_penerima = isset($_POST['id_penerima']) ? intval($_POST['id_penerima']) : 0;
    $judul       = isset($_POST['judul']) ? trim($_POST['judul']) : '';
    $isi         = isset($_POST['isi']) ? trim($_POST['isi']) : '';

    if ($id_pengirim <= 0) {
        send_response(2, 'Pengirim tidak valid.');
    }
    if ($id_penerima <= 0) {
        send_response(3, 'Penerima belum dipilih.');
    }
    if (empty($judul) || empty($isi)) {
        send_response(4, 'Judul dan isi surat tidak boleh kosong.');
    }

    // Insert surat
    $stmt = $conn->prepare("
        INSERT INTO laporan_surat (id_pengirim, id_penerima, jenis_surat, judul, isi, status)
        VALUES (?, ?, 'peringatan', ?, ?, 'terkirim')
    ");
    if (!$stmt) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("iiss", $id_pengirim, $id_penerima, $judul, $isi);
    if (!$stmt->execute()) {
        send_response(1, 'Gagal menambahkan surat: ' . $stmt->error);
    }
    $newId = $stmt->insert_id;
    $stmt->close();

    // Catat audit log
    $user_nip = $_SESSION['nip'] ?? '';
    $detail_log = "Menambahkan surat peringatan (ID=$newId) kepada penerima ID=$id_penerima, judul='$judul'.";
    add_audit_log($conn, $user_nip, 'AddSuratPeringatan', $detail_log);

    send_response(0, 'Surat peringatan berhasil dibuat.');
}

/**
 * Menghapus Surat.
 */
function DeleteSurat($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        send_response(2, 'ID surat tidak valid.');
    }

    // Hapus data
    $stmt = $conn->prepare("DELETE FROM laporan_surat WHERE id = ?");
    if (!$stmt) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        send_response(1, 'Gagal menghapus surat: ' . $stmt->error);
    }
    $stmt->close();

    // Audit log
    $user_nip = $_SESSION['nip'] ?? '';
    $detail_log = "Menghapus surat peringatan ID=$id.";
    add_audit_log($conn, $user_nip, 'DeleteSuratPeringatan', $detail_log);

    send_response(0, 'Surat berhasil dihapus.');
}

/**
 * Mengambil detail surat berdasarkan ID.
 */
function ViewSuratDetail($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        send_response(1, 'ID surat tidak valid.');
    }
    $stmt = $conn->prepare("
        SELECT ls.*, penerima.nama AS nama_penerima 
        FROM laporan_surat ls
        LEFT JOIN anggota_sekolah penerima ON ls.id_penerima = penerima.id
        WHERE ls.id = ? LIMIT 1
    ");
    if (!$stmt) {
        send_response(1, 'Query Error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows == 1) {
        $row = $result->fetch_assoc();
        send_response(0, $row);
    } else {
        send_response(2, 'Surat tidak ditemukan.');
    }
    $stmt->close();
}


?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Surat</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <!-- DataTables CSS (Bootstrap 5) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.1.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        /* Aturan global untuk semua font menjadi hitam */
        body, label, button, input, textarea {
            color: #000 !important;
        }
        
.card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }
        .table-hover tbody tr:hover {
            background-color: #e2e6ea;
        }
        .table-responsive {
            overflow-x: auto;
        }
        #loadingSpinner {
            display: none;
            position: fixed;
            z-index: 9999;
            height: 100px;
            width: 100px;
            margin: auto;
            top: 0; left: 0; bottom: 0; right: 0;
        }
        
    </style>
</head>
<body id="page-top">

    <!-- Page Wrapper -->
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
                <!-- End of Topbar -->

                <!-- Breadcrumb (opsional) -->
                <?php 
                // Jika punya breadcrumb.php, sertakan
                include __DIR__ . '/../breadcrumb.php'; 
                ?>

                <!-- Page Content -->
                <div class="container-fluid">
                    <!-- Judul Halaman -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-gray-800 mb-0">
            <i class="fas fa-envelope-open-text me-2"></i>Manajemen Surat
        </h1>
        <button id="btnTemplateSurat" class="btn btn-info">
            <i class="fas fa-file-alt"></i> Kelola Template Surat
        </button>
    </div>

                    <!-- Card: Form Tambah Surat -->
                    <div class="card mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
    <h6 class="m-0 fw-bold text-white">
      <i class="fas fa-plus-circle me-2"></i> Buat Surat
    </h6>
  </div>
                        <div class="card-body">
                            <form id="formAddSurat" class="row g-3 needs-validation" novalidate>
                                <!-- Field "case" untuk AJAX di server -->
                                <input type="hidden" name="case" value="AddSurat">

                                <!-- Pilih Penerima -->
                                <div class="col-md-4">
                                    <label for="id_penerima" class="form-label">
                                        <i class="fas fa-user me-1"></i>Pilih Penerima (Guru / Karyawan)
                                    </label>
                                    <select class="form-select" name="id_penerima" id="id_penerima" required>
                                        <option value="">-- Pilih Penerima --</option>
                                        <?php
                                        // Ambil data penerima (role P, TK)
                                        $sqlPenerima = "SELECT id, nama, role FROM anggota_sekolah WHERE role IN ('P','TK')";
                                        $resPenerima = $conn->query($sqlPenerima);
                                        while ($p = $resPenerima->fetch_assoc()) {
                                            echo '<option value="'.$p['id'].'">'.$p['nama'].' ('.$p['role'].')</option>';
                                        }
                                        ?>
                                    </select>
                                    <div class="invalid-feedback">Pilih penerima surat.</div>
                                </div>

                                <!-- Judul Surat -->
                                <div class="col-md-8">
                                    <label for="judul" class="form-label">
                                        <i class="fas fa-heading me-1"></i>Judul Surat
                                    </label>
                                    <input type="text" class="form-control" id="judul" name="judul" placeholder="Contoh: Surat Peringatan I" required>
                                    <div class="invalid-feedback">Judul surat belum diisi.</div>
                                </div>

                                <!-- Isi Surat -->
                                <div class="col-12">
                                    <label for="isi" class="form-label">
                                        <i class="fas fa-file-alt me-1"></i>Isi Surat
                                    </label>
                                    <textarea class="form-control" id="isi" name="isi" rows="4" placeholder="Tulis isi surat peringatan di sini..." required></textarea>
                                    <div class="invalid-feedback">Isi surat belum diisi.</div>
                                </div>

                                <!-- Tombol Submit -->
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Buat Surat
                                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tabel Data Surat -->
                    <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
    <h6 class="m-0 fw-bold text-white">
      <i class="fas fa-envelope me-1"></i> Daftar Surat
    </h6>
  </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tableSurat" class="table table-sm table-bordered table-hover table-striped display nowrap" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Penerima</th>
                                            <th>Judul</th>
                                            <th>Isi (Singkat)</th>
                                            <th>Tanggal Keluar</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody> <!-- DataTables -->
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- end .container-fluid -->
            </div>
            <!-- end #content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?= date("Y"); ?> Sistem Nusaputera | Developed by [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div> <!-- End content-wrapper -->
    </div> <!-- End wrapper -->

    <!-- Modal Hapus Surat -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form id="formDeleteSurat">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-trash-alt me-2"></i>Hapus Surat
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="delete_id" name="id">
                        <p>Yakin ingin menghapus surat ini?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-check"></i> Ya, Hapus
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: View Detail Surat -->
<div class="modal fade" id="modalViewSurat" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detail Surat</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <h5 id="viewJudul"></h5>
        <p><strong>Nama Penerima:</strong> <span id="viewNamaPenerima"></span></p>
        <p><strong>Tanggal Keluar:</strong> <span id="viewTanggal"></span></p>
        <hr>
        <div id="viewIsi" style="white-space: pre-wrap;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times"></i> Tutup
        </button>
      </div>
    </div>
  </div>
</div>


    <!-- Loading Spinner -->
    <div id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    $(document).ready(function() {

        // Inisialisasi Toast
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        function showToast(message, icon = 'success') {
            Toast.fire({ icon: icon, title: message });
        }

        // DataTables
        var tableSurat = $('#tableSurat').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "pembuatan_surat.php?ajax=1",
                type: "POST",
                data: function(d){
                    d.case = "LoadingSurat";
                },
                beforeSend: function(){
                    $('#loadingSpinner').show();
                },
                complete: function(){
                    $('#loadingSpinner').hide();
                },
                error: function(){
                    showToast('Gagal memuat data surat.', 'error');
                }
            },
            columns: [
                { data: "no", orderable: false },
                { data: "nama_penerima" },
                { data: "judul" },
                { data: "isi" },
                { data: "tanggal_keluar" },
                { data: "status" },
                { data: "aksi", orderable: false }
            ],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
            },
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Export Excel',
                    className: 'btn btn-success btn-sm',
                    exportOptions: { columns: [0,1,2,3,4,5] }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> Export PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: { columns: [0,1,2,3,4,5] },
                    customize: function (doc) {
                        doc.styles.tableHeader.fillColor = '#343a40';
                        doc.styles.tableHeader.color = 'white';
                        doc.defaultStyle.fontSize = 9;
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print"></i> Print',
                    className: 'btn btn-info btn-sm',
                    exportOptions: { columns: [0,1,2,3,4,5] }
                }
            ],
            responsive: true,
            autoWidth: false
        });

        // Validasi form bootstrap 5
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Form Tambah Surat
        $('#formAddSurat').on('submit', function(e){
            e.preventDefault();
            var form = $(this);
            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }
            var formData = form.serialize();
            $.ajax({
                url: "pembuatan_surat.php?ajax=1",
                type: "POST",
                data: formData,
                dataType: "json",
                beforeSend: function(){
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success: function(res){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if (res.code == 0) {
                        showToast(res.result, 'success');
                        form[0].reset();
                        form.removeClass('was-validated');
                        tableSurat.ajax.reload(null, false);
                    } else {
                        showToast(res.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat menambah surat.', 'error');
                }
            });
        });

        // Tombol Delete -> Munculkan Modal
        $(document).on('click', '.btn-delete', function() {
            var id = $(this).data('id');
            $('#delete_id').val(id);
            $('#deleteModal').modal('show');
        });

        // Form Delete
        $('#formDeleteSurat').on('submit', function(e){
            e.preventDefault();
            var form = $(this);
            var id = $('#delete_id').val();
            if (!id) {
                showToast('ID surat tidak ditemukan.', 'error');
                return;
            }
            $.ajax({
                url: "pembuatan_surat.php?ajax=1",
                type: "POST",
                data: { case: 'DeleteSurat', id: id },
                dataType: "json",
                beforeSend: function(){
                    form.find('button[type="submit"]').prop('disabled', true);
                    form.find('.spinner-border').removeClass('d-none');
                },
                success: function(res){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    if (res.code == 0) {
                        showToast(res.result, 'success');
                        $('#deleteModal').modal('hide');
                        tableSurat.ajax.reload(null, false);
                    } else {
                        showToast(res.result, 'error');
                    }
                },
                error: function(){
                    form.find('button[type="submit"]').prop('disabled', false);
                    form.find('.spinner-border').addClass('d-none');
                    showToast('Terjadi kesalahan saat menghapus surat.', 'error');
                }
            });
        });

        $(document).on('click', '#btnTemplateSurat', function(e) {
    e.preventDefault();
    var url = "template_surat.php"; // pastikan path sesuai dengan struktur proyek Anda
    $('#content-wrapper').fadeOut(300, function() {
        window.location.href = url;
    });
});

    // Event handler untuk tombol View Detail
$(document).on('click', '.btn-view', function(){
    var suratId = $(this).data('id');
    $.ajax({
        url: "pembuatan_surat.php?ajax=1",
        type: "POST",
        data: { case: 'ViewSuratDetail', id: suratId },
        dataType: "json",
        beforeSend: function(){
            // Opsi: tampilkan loading jika diperlukan
        },
        success: function(response){
            if(response.code === 0) {
                // Isi modal dengan detail surat
                $('#viewJudul').text(response.result.judul);
                $('#viewNamaPenerima').text(response.result.nama_penerima || '-');
                $('#viewTanggal').text(response.result.tanggal_keluar);
                $('#viewIsi').text(response.result.isi);
                // Tampilkan modal
                $('#modalViewSurat').modal('show');
            } else {
                showToast(response.result, 'error');
            }
        },
        error: function(){
            showToast('Terjadi kesalahan saat mengambil detail surat.', 'error');
        }
    });
});


    });
    </script>

</body>
</html>