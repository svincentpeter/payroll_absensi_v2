<?php
// File: /payroll_absensi_v2/payroll/keuangan/list_payroll.php

// Sertakan file helper, inisialisasi session dan CSRF token
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

// Sertakan koneksi ke database
require_once __DIR__ . '/../../koneksi.php';

// Hanya user dengan role 'keuangan' dan 'superadmin' yang diizinkan
authorize(['keuangan','superadmin'], '/payroll_absensi_v2/login.php');

// Panggil fungsi updateSalaryIndexForAll agar data salary index selalu terupdate
updateSalaryIndexForAll($conn);

// ---------------------
// AJAX HANDLER SECTION
// ---------------------
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    // Pastikan request menggunakan metode POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verifikasi token CSRF
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $case = isset($_POST['case']) ? trim($_POST['case']) : '';
        switch ($case) {
            case 'CheckPayrollCompletion':
                CheckPayrollCompletion($conn);
                break;
            case 'LogSelectMonth':
                LogSelectMonth($conn);
                break;
            default:
                error_log("DEBUG: Kasus tidak valid: " . $case);
                send_response(1, 'Kasus tidak valid.');
        }
    } else {
        send_response(405, 'Metode Permintaan Tidak Diizinkan.');
    }
    $conn->close();
    exit();
}

/**
 * Fungsi CheckPayrollCompletion: Menghitung jumlah anggota (per jenjang)
 * yang belum memiliki payroll final pada periode yang dipilih.
 */
function CheckPayrollCompletion($conn) {
    $bulan = isset($_POST['selectedMonth']) ? intval($_POST['selectedMonth']) : 0;
    $tahun = isset($_POST['selectedYear']) ? intval($_POST['selectedYear']) : 0;
    if ($bulan <= 0 || $tahun <= 0) {
        send_response(1, 'Parameter bulan/tahun tidak valid.');
    }
    $query = "SELECT jenjang, COUNT(*) as pending 
              FROM anggota_sekolah 
              WHERE id NOT IN (
                    SELECT id_anggota FROM payroll_final WHERE bulan = ? AND tahun = ?
              ) 
              GROUP BY jenjang";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $bulan, $tahun);
    if(!$stmt->execute()){
        $error = $stmt->error;
        $stmt->close();
        send_response(1, 'Gagal menghitung payroll: ' . $error);
    }
    $result = $stmt->get_result();
    $messages = [];
    while($row = $result->fetch_assoc()){
        if (intval($row['pending']) > 0) {
            $messages[] = "Terdapat " . $row['pending'] . " anggota dengan jenjang '" . $row['jenjang'] . "' yang belum final.";
        }
    }
    
    $stmt->close();
    if(empty($messages)){
        send_response(0, ['complete' => true, 'messages' => []]);
    } else {
        send_response(0, ['complete' => false, 'messages' => $messages]);
    }
}

/**
 * Fungsi LogSelectMonth: Mencatat (audit log) pilihan bulan payroll oleh user.
 */
function LogSelectMonth($conn) {
    $selectedMonth = isset($_POST['selectedMonth']) ? intval($_POST['selectedMonth']) : 0;
    $selectedYear  = isset($_POST['selectedYear']) ? intval($_POST['selectedYear']) : 0;
    $user_nip = $_SESSION['nip'] ?? '';
    $details_log = "User dengan NIP $user_nip memilih bulan payroll: $selectedMonth/$selectedYear";
    add_audit_log($conn, $user_nip, 'SelectPayrollMonth', $details_log);
    send_response(0, 'Logged');
}

// ---------------------
// END AJAX HANDLER
// ---------------------

// Ambil filter dari GET (default: bulan & tahun sekarang)
$filterMonth   = isset($_GET['filterMonth']) ? intval($_GET['filterMonth']) : date("n");
$filterYear    = isset($_GET['filterYear'])  ? intval($_GET['filterYear'])  : date("Y");
$filterJenjang = isset($_GET['filterJenjang']) ? sanitize_input($_GET['filterJenjang']) : '';
$filterRole    = isset($_GET['filterRole'])    ? sanitize_input($_GET['filterRole'])    : '';

// Logging akses halaman overview payroll
$user_nip = $_SESSION['nip'] ?? '';
error_log("DEBUG: User dengan NIP $user_nip mengakses Payroll Overview untuk periode: $filterMonth/$filterYear");
$details_log = "User dengan NIP $user_nip melihat overview payroll untuk periode: $filterMonth/$filterYear";
add_audit_log($conn, $user_nip, 'ViewPayrollOverview', $details_log);

// Ambil data untuk dropdown filter jenjang dan role
$jenjangOptions = [];
$stmtJenjang = $conn->prepare("SELECT DISTINCT jenjang FROM anggota_sekolah WHERE jenjang IS NOT NULL AND jenjang != '' ORDER BY jenjang ASC");
if ($stmtJenjang) {
    $stmtJenjang->execute();
    $resJenjang = $stmtJenjang->get_result();
    while ($row = $resJenjang->fetch_assoc()){
        $jenjangOptions[] = $row['jenjang'];
    }
    $stmtJenjang->close();
}

$roleOptions = [];
$stmtRole = $conn->prepare("SELECT DISTINCT role FROM anggota_sekolah WHERE role IS NOT NULL AND role != '' ORDER BY role ASC");
if ($stmtRole) {
    $stmtRole->execute();
    $resRole = $stmtRole->get_result();
    while ($row = $resRole->fetch_assoc()){
        $roleOptions[] = $row['role'];
    }
    $stmtRole->close();
}

// Ubah query utama untuk menampilkan payroll overview dengan join ke salary_indices
$query = "
    SELECT 
        p.id, 
        p.bulan, 
        p.tahun, 
        p.tgl_payroll, 
        a.id AS id_anggota, 
        a.nama, 
        a.nip, 
        a.jenjang,
        a.role,
        p.status,
        p.catatan,
        si.level AS salary_index_level
    FROM payroll p
    JOIN anggota_sekolah a ON p.id_anggota = a.id
    LEFT JOIN salary_indices si ON a.salary_index_id = si.id
    WHERE p.bulan = ? 
      AND p.tahun = ? 
      AND p.status = 'draft'
      AND NOT EXISTS (
            SELECT 1 FROM payroll_final pf
            WHERE pf.id_anggota = p.id_anggota
              AND pf.bulan = p.bulan
              AND pf.tahun = p.tahun
      )
";
$params = [$filterMonth, $filterYear];
$types = "ii";
if (!empty($filterJenjang)) {
    $query .= " AND a.jenjang = ?";
    $params[] = $filterJenjang;
    $types .= "s";
}
if (!empty($filterRole)) {
    $query .= " AND a.role = ?";
    $params[] = $filterRole;
    $types .= "s";
}
$query .= " ORDER BY p.tgl_payroll DESC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("DEBUG: Prepare query gagal di list_payroll: " . $conn->error);
    die("Error: " . $conn->error);
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Payroll Overview - Keuangan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- CSS Bootstrap 5, SB Admin 2, dan DataTables -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/css/sb-admin-2.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- SweetAlert2 CSS (opsional) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body { color: #000; }
        .breadcrumb { background: none; }
        .header-period { cursor: pointer; }
        /* Gunakan styling card seperti di employees.php */
        .card-header { background: #4e73df; color: white; }
        .processed-month { background: #343a40 !important; color: #fff !important; pointer-events: none; border: 1px solid #343a40; }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include __DIR__ . '/../../navbar.php'; ?>
                <div class="container-fluid">
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/payroll_absensi_v2/payroll/keuangan/dashboard_keuangan.php">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Payroll Overview</li>
                        </ol>
                    </nav>
                    <!-- Header: Tampilkan periode payroll yang terpilih (dengan tampilan card) -->
                    <div id="selectedMonthDisplay" class="mb-3" style="cursor: pointer;">
                        <div class="card mb-3">
                            <div class="card-body d-flex align-items-center">
                                <i class="bi bi-calendar3 me-2"></i>
                                <span class="fw-bold">
                                    Payroll Bulan: <?= date("F", mktime(0,0,0,$filterMonth,1)) . " " . $filterYear; ?>
                                </span>
                                <button id="btnChangeCalendar" class="btn btn-link ms-auto">
                                    <i class="bi bi-pencil-square"></i> Ganti Kalender
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Filter Form: Jenjang dan Role -->
                    <div class="card mb-4">
                        <div class="card-header fw-bold"><i class="bi bi-filter-square-fill"></i> Filter Payroll</div>
                        <div class="card-body">
                            <form id="filterForm" method="GET" class="row align-items-center">
                                <input type="hidden" name="filterMonth" value="<?= $filterMonth; ?>">
                                <input type="hidden" name="filterYear" value="<?= $filterYear; ?>">
                                <div class="form-group mb-2 col-auto">
                                    <label for="filterJenjang" class="me-2">Jenjang Pendidikan:</label>
                                    <select class="form-control" id="filterJenjang" name="filterJenjang">
                                        <option value="">Semua Jenjang</option>
                                        <?php foreach ($jenjangOptions as $jenjang): ?>
                                            <option value="<?= htmlspecialchars($jenjang); ?>" <?= ($filterJenjang === $jenjang) ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($jenjang); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group mb-2 col-auto">
                                    <label for="filterRole" class="me-2">Role:</label>
                                    <select class="form-control" id="filterRole" name="filterRole">
                                        <option value="">Semua Role</option>
                                        <?php foreach ($roleOptions as $role): ?>
                                            <option value="<?= htmlspecialchars($role); ?>" <?= ($filterRole === $role) ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($role); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group mb-2 col-auto">
                                    <button type="submit" class="btn btn-primary mb-2 me-2">
                                        <i class="fas fa-filter"></i> Terapkan Filter
                                    </button>
                                    <a href="list_payroll.php?filterMonth=<?= $filterMonth; ?>&filterYear=<?= $filterYear; ?>" class="btn btn-secondary mb-2">
                                        <i class="fas fa-undo"></i> Reset Filter
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- Tabel Payroll Overview -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 fw-bold text-white">
                                <i class="fas fa-file-invoice-dollar"></i> Daftar Payroll Periode <?= date("F", mktime(0,0,0,$filterMonth,1)) . " " . $filterYear; ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="payrollTable" class="table table-bordered table-striped" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID Payroll</th>
                                            <th>Nama Karyawan</th>
                                            <th>NIP</th>
                                            <th>Jenjang</th>
                                            <th>Role</th>
                                            <th>Level Indeks</th>
                                            <th>Periode</th>
                                            <th>Status</th>
                                            <th>Tanggal Payroll</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        while ($row = $result->fetch_assoc()) {
                                            $periode = date("F", mktime(0,0,0,$row['bulan'],1)) . " " . $row['tahun'];
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['nip']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['jenjang']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['role']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['salary_index_level'] ?: '-') . "</td>";
                                            echo "<td>" . $periode . "</td>";
                                            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['tgl_payroll']) . "</td>";
                                            echo "<td>";
                                            echo '<a href="manage-salary.php?id_anggota=' . $row['id_anggota'] .
                                                 '&bulan=' . $row['bulan'] .
                                                 '&tahun=' . $row['tahun'] .
                                                 '" class="btn btn-sm btn-warning me-1">Review</a>';
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div> <!-- End Tabel Payroll Overview -->
                </div> <!-- End container-fluid -->
            </div> <!-- End content -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?= date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div> <!-- End content-wrapper -->
    </div> <!-- End wrapper -->
    
    <!-- MODAL: Select Month (Payroll) -->
    <div class="modal fade" id="SalaryMonthModal" tabindex="-1" aria-labelledby="salaryMonthModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-md" style="max-width: 600px;">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="salaryMonthModalLabel"><i class="fa fa-calendar"></i> Pilih Bulan untuk Payroll</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <div class="row text-center">
              <?php
                $currentYear  = date('Y');
                $currentMonth = date('n');
                $startMonth   = $currentMonth - 2;
                $startYear    = $currentYear;
                for ($i = 0; $i < 16; $i++) {
                    $month = $startMonth + $i;
                    $year  = $startYear;
                    if ($month <= 0) {
                        $month += 12;
                        $year  -= 1;
                    } elseif ($month > 12) {
                        $month -= 12;
                        $year  += 1;
                    }
                    $highlight = ($month == $filterMonth && $year == $filterYear) ? 'bg-warning text-dark fw-bold' : 'bg-light';
                    echo '<div class="col-3 mb-3">';
                    echo '  <div class="p-2 ' . $highlight . '" style="border: 1px solid #ddd; border-radius: 5px;">';
                    echo '    <a href="#" class="month-link" data-month-number="' . $month . '" data-month="' . date("F", mktime(0, 0, 0, $month, 1)) . '" data-year="' . $year . '" style="color: inherit; text-decoration: none;">';
                    echo '      ' . strtoupper(date("F", mktime(0, 0, 0, $month, 1))) . '<br>' . $year;
                    echo '    </a>';
                    echo '  </div>';
                    echo '</div>';
                }
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function(){
        $('#payrollTable').DataTable();

        // Tampilkan modal pilih bulan saat klik pada tombol "Ganti Kalender" atau header
        $('#btnChangeCalendar, #selectedMonthDisplay').on('click', function(e){
            e.preventDefault();
            $('#SalaryMonthModal').modal('show');
        });

        // Event handler untuk link bulan di modal
        $(document).on('click', '.month-link', function(e){
            e.preventDefault();
            var monthNumber = $(this).data('month-number');
            var monthName   = $(this).data('month');
            var year        = $(this).data('year');
            // Lakukan AJAX ke endpoint CheckPayrollCompletion
            $.ajax({
                url: 'list_payroll.php?ajax=1',
                type: 'POST',
                dataType: 'json',
                data: { 
                    case: 'CheckPayrollCompletion', 
                    selectedMonth: monthNumber, 
                    selectedYear: year, 
                    csrf_token: '<?= htmlspecialchars($csrf_token); ?>'
                },
                success: function(resp) {
                    if(resp.code === 0) {
                        if(resp.result.complete === false) {
                            var messages = resp.result.messages;
                            var messageText = messages.join("<br>");
                            Swal.fire({
                                title: 'Perhatian!',
                                html: messageText + "<br><br>Apakah Anda tetap ingin memilih bulan ini?",
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: 'Ya, pilih bulan ini',
                                cancelButtonText: 'Batal'
                            }).then((result) => {
                                if(result.isConfirmed) {
                                    // Catat pilihan bulan
                                    $.ajax({
                                        url: 'list_payroll.php?ajax=1',
                                        type: 'POST',
                                        data: { 
                                            case: 'LogSelectMonth', 
                                            selectedMonth: monthNumber, 
                                            selectedYear: year, 
                                            csrf_token: '<?= htmlspecialchars($csrf_token); ?>'
                                        }
                                    });
                                    // Redirect dengan parameter filter baru
                                    var baseUrl = 'list_payroll.php?filterMonth=' + monthNumber + '&filterYear=' + year;
                                    <?php if(!empty($filterJenjang)): ?>
                                        baseUrl += '&filterJenjang=<?= urlencode($filterJenjang); ?>';
                                    <?php endif; ?>
                                    <?php if(!empty($filterRole)): ?>
                                        baseUrl += '&filterRole=<?= urlencode($filterRole); ?>';
                                    <?php endif; ?>
                                    window.location.href = baseUrl;
                                }
                            });
                        } else {
                            // Jika payroll sudah complete, langsung redirect
                            $.ajax({
                                url: 'list_payroll.php?ajax=1',
                                type: 'POST',
                                data: { 
                                    case: 'LogSelectMonth', 
                                    selectedMonth: monthNumber, 
                                    selectedYear: year, 
                                    csrf_token: '<?= htmlspecialchars($csrf_token); ?>'
                                }
                            });
                            var baseUrl = 'list_payroll.php?filterMonth=' + monthNumber + '&filterYear=' + year;
                            <?php if(!empty($filterJenjang)): ?>
                                baseUrl += '&filterJenjang=<?= urlencode($filterJenjang); ?>';
                            <?php endif; ?>
                            <?php if(!empty($filterRole)): ?>
                                baseUrl += '&filterRole=<?= urlencode($filterRole); ?>';
                            <?php endif; ?>
                            window.location.href = baseUrl;
                        }
                    } else {
                        Swal.fire('Error', resp.result, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire('Error', 'Terjadi kesalahan: ' + error, 'error');
                }
            });
        });
    });
    </script>
</body>
</html>
<?php
$conn->close();
?>
