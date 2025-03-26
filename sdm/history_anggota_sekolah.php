<?php
// File: /payroll_absensi_v2/sdm/history_anggota_sekolah.php

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:SDM', 'M:Superadmin'], '/payroll_absensi_v2/login.php');
require_once __DIR__ . '/../koneksi.php';

// Handle AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $case = $_POST['case'] ?? '';
        switch ($case) {
            case 'LoadingHistory':
                LoadingHistory($conn);
                break;
            case 'RestoreAnggota':
                RestoreAnggota($conn);
                break;
            case 'PermanentDelete':
                PermanentDelete($conn);
                break;
            default:
                send_response(404, 'Case tidak ditemukan.');
        }
    } else {
        send_response(405, 'Method Not Allowed.');
    }
    exit();
}

/**
 * Fungsi memuat data yang di-soft delete dengan format server-side DataTables.
 */
function LoadingHistory($conn)
{
    $draw   = isset($_POST['draw'])   ? intval($_POST['draw'])   : 0;
    $start  = isset($_POST['start'])  ? intval($_POST['start'])  : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

    // Total data yang dihapus
    $sqlTotal = "SELECT COUNT(*) AS total FROM anggota_sekolah WHERE is_delete = 1";
    $resTotal = mysqli_query($conn, $sqlTotal);
    $rowTotal = mysqli_fetch_assoc($resTotal);
    $recordsTotal = $rowTotal['total'] ?? 0;

    // Query filter
    $sqlFilter = "SELECT * FROM anggota_sekolah WHERE is_delete = 1";
    $params = [];
    $types  = "";

    // Jika ada pencarian
    if (!empty($search)) {
        $sqlFilter .= " AND (nama LIKE ? OR nip LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types  .= "ss";
    }

    // Urutkan berdasarkan deleted_at DESC
    $sqlFilter .= " ORDER BY deleted_at DESC";

    // Limit
    $sqlFilter .= " LIMIT ?, ?";
    $params[] = $start;
    $params[] = $length;
    $types .= "ii";

    $stmt = $conn->prepare($sqlFilter);
    if (!$stmt) {
        send_response(1, 'Query error: ' . $conn->error);
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    $no = $start + 1;
    while ($row = $result->fetch_assoc()) {
        $deletedAt = $row['deleted_at'] ?? '-';
        $id        = (int)$row['id'];

        // Tombol aksi (dropdown)
        $aksi = '
<div class="dropdown">
  <button class="btn btn-sm" type="button" id="dropdownMenuButton_' . $id . '" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="bi bi-three-dots-vertical"></i>
  </button>
  <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton_' . $id . '">
    <li>
      <a class="dropdown-item btn-restore" href="javascript:void(0)" data-id="' . $id . '">
        <i class="fas fa-undo"></i> Pulihkan
      </a>
    </li>
    <li>
      <a class="dropdown-item btn-permanent-delete" href="javascript:void(0)" data-id="' . $id . '">
        <i class="fas fa-trash-alt"></i> Hapus Permanen
      </a>
    </li>
  </ul>
</div>';

        $data[] = [
            "no"         => $no++,
            "nama"       => htmlspecialchars($row['nama']),
            "nip"        => htmlspecialchars($row['nip']),
            "jenjang"    => $row['jenjang'] ?: '-',
            "role"       => $row['role']    ?: '-',
            "deleted_at" => $deletedAt,
            "aksi"       => $aksi
        ];
    }
    $stmt->close();

    // Hitung recordsFiltered (jika ada pencarian)
    if (!empty($search)) {
        $sqlCount = "SELECT COUNT(*) AS total FROM anggota_sekolah WHERE is_delete = 1 AND (nama LIKE ? OR nip LIKE ?)";
        $stmtCount = $conn->prepare($sqlCount);
        $stmtCount->bind_param("ss", $searchParam, $searchParam);
        $stmtCount->execute();
        $resCount = $stmtCount->get_result();
        $rowCount = $resCount->fetch_assoc();
        $recordsFiltered = $rowCount['total'] ?? 0;
        $stmtCount->close();
    } else {
        $recordsFiltered = $recordsTotal;
    }

    echo json_encode([
        "draw"            => $draw,
        "recordsTotal"    => $recordsTotal,
        "recordsFiltered" => $recordsFiltered,
        "data"            => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

function RestoreAnggota($conn)
{
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        send_response(1, 'ID tidak valid.');
    }
    $sql = "UPDATE anggota_sekolah SET is_delete=0, deleted_at=NULL WHERE id=?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_response(1, 'Query error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $stmt->close();
        add_audit_log($conn, $_SESSION['nip'] ?? '', 'RestoreAnggota', "Restore ID=$id");
        send_response(0, 'Data berhasil dipulihkan.');
    } else {
        $stmt->close();
        send_response(1, 'Gagal memulihkan data.');
    }
}

function PermanentDelete($conn)
{
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        send_response(1, 'ID tidak valid.');
    }
    $sql = "DELETE FROM anggota_sekolah WHERE id=?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_response(1, 'Query error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $stmt->close();
        add_audit_log($conn, $_SESSION['nip'] ?? '', 'PermanentDelete', "Hapus permanen ID=$id");
        send_response(0, 'Data dihapus permanen.');
    } else {
        $stmt->close();
        send_response(1, 'Gagal menghapus permanen.');
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>History Anggota Sekolah yang Dihapus</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- DataTables CSS (Bootstrap 5) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        body {
            padding-top: 20px;
        }

        #main-content {
            transition: opacity 0.3s ease;
        }

        .back-btn {
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }

        thead th {
            background-color: #343a40;
            color: #fff;
        }

        .dropdown-menu a {
            cursor: pointer;
        }

        #loadingSpinner {
            display: none;
            position: fixed;
            z-index: 9999;
            height: 100px;
            width: 100px;
            margin: auto;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
        }
    </style>
</head>

<body>
    <div class="container" id="main-content">
        <!-- Tombol Kembali -->
        <button class="btn btn-secondary back-btn" id="btnBack" data-href="/payroll_absensi_v2/sdm/manage_guru_karyawan.php">
            <i class="fas fa-arrow-left"></i> Kembali ke Manajemen Guru/Karyawan
        </button>

        <h1 class="h3 mb-4 text-dark">
            <i class="fas fa-history"></i> History Anggota Sekolah yang Dihapus
        </h1>

        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-white">
                    <i class="fas fa-clipboard-list"></i> Daftar History Hapus
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="historyTable" class="table table-sm table-bordered table-striped display nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama</th>
                                <th>NIP</th>
                                <th>Jenjang</th>
                                <th>Role</th>
                                <th>Dihapus Pada</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
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
    <!-- DataTables JS (Bootstrap 5) -->
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            // SweetAlert2 Toast
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
                Toast.fire({
                    icon: icon,
                    title: message
                });
            }

            // Inisialisasi DataTables
            let historyTable = $('#historyTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "history_anggota_sekolah.php?ajax=1",
                    type: "POST",
                    data: function(d) {
                        d.case = 'LoadingHistory';
                    },
                    beforeSend: function() {
                        $('#loadingSpinner').show();
                    },
                    complete: function() {
                        $('#loadingSpinner').hide();
                    },
                    error: function() {
                        showToast('Terjadi kesalahan saat memuat data.', 'error');
                    }
                },
                columns: [{
                        data: "no",
                        orderable: false
                    },
                    {
                        data: "nama"
                    },
                    {
                        data: "nip"
                    },
                    {
                        data: "jenjang"
                    },
                    {
                        data: "role"
                    },
                    {
                        data: "deleted_at"
                    },
                    {
                        data: "aksi",
                        orderable: false
                    }
                ],
                responsive: true,
                autoWidth: false,
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
                }
            });

            // Tombol Back
            $('#btnBack').on('click', function(e) {
                e.preventDefault();
                var url = $(this).data('href');
                $('#main-content').fadeOut(300, function() {
                    window.location.href = url;
                });
            });

            // Restore
            $(document).on('click', '.btn-restore', function() {
                let id = $(this).data('id');
                Swal.fire({
                    title: 'Pulihkan Data?',
                    text: "Data akan dikembalikan ke daftar aktif.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Ya, Pulihkan!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "history_anggota_sekolah.php?ajax=1",
                            type: "POST",
                            dataType: "json",
                            data: {
                                case: 'RestoreAnggota',
                                id: id
                            },
                            success: function(response) {
                                if (response.code == 0) {
                                    Swal.fire('Sukses', response.result, 'success');
                                    historyTable.ajax.reload(null, false);
                                } else {
                                    Swal.fire('Error', response.result, 'error');
                                }
                            },
                            error: function() {
                                Swal.fire('Error', 'Terjadi kesalahan saat memulihkan data.', 'error');
                            }
                        });
                    }
                });
            });

            // Hapus Permanen
            $(document).on('click', '.btn-permanent-delete', function() {
                let id = $(this).data('id');
                Swal.fire({
                    title: 'Hapus Permanen?',
                    text: "Data tidak dapat dikembalikan!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Ya, Hapus!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "history_anggota_sekolah.php?ajax=1",
                            type: "POST",
                            dataType: "json",
                            data: {
                                case: 'PermanentDelete',
                                id: id
                            },
                            success: function(response) {
                                if (response.code == 0) {
                                    Swal.fire('Sukses', response.result, 'success');
                                    historyTable.ajax.reload(null, false);
                                } else {
                                    Swal.fire('Error', response.result, 'error');
                                }
                            },
                            error: function() {
                                Swal.fire('Error', 'Terjadi kesalahan saat menghapus data.', 'error');
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>

</html>