<?php
// File: /payroll_absensi_v2/payroll/keuangan/list_payroll.php
session_start();

// Pastikan hanya user dengan role 'keuangan' atau 'superadmin' yang bisa mengakses halaman ini.
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['keuangan','superadmin'])) {
    header("Location: /payroll_absensi_v2/login.php");
    exit();
}

// Sertakan koneksi ke database
require_once __DIR__ . '/../../koneksi.php';

// Atur default filter: gunakan bulan dan tahun sekarang jika tidak ada parameter GET
$filterMonth = isset($_GET['filterMonth']) ? intval($_GET['filterMonth']) : date("n");
$filterYear  = isset($_GET['filterYear'])  ? intval($_GET['filterYear'])  : date("Y");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Payroll Overview - Keuangan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- CSS Bootstrap & DataTables -->
    <link rel="stylesheet" href="/payroll_absensi_v2/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/payroll_absensi_v2/assets/css/sb-admin-2.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap4.min.css">
    <style>
        body { color: #000; }
        .breadcrumb { background: none; }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <!-- Include sidebar -->
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Include navbar -->
                <?php include __DIR__ . '/../../navbar.php'; ?>
                <div class="container-fluid">
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/payroll_absensi_v2/payroll/keuangan/dashboard_keuangan.php">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Payroll Overview</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-file-invoice-dollar"></i> Payroll Overview</h1>
                    
                    <!-- Filter Form -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form id="filterForm" class="form-inline">
                                <label for="filterMonth" class="mr-2">Bulan:</label>
                                <select class="form-control mr-3" id="filterMonth" name="filterMonth">
                                    <?php
                                    // Tampilkan opsi bulan 1 sampai 12
                                    for ($m = 1; $m <= 12; $m++) {
                                        $monthName = date("F", mktime(0, 0, 0, $m, 1));
                                        $selected = ($m == $filterMonth) ? "selected" : "";
                                        echo '<option value="'.$m.'" '.$selected.'>'.$monthName.'</option>';
                                    }
                                    ?>
                                </select>
                                <label for="filterYear" class="mr-2">Tahun:</label>
                                <select class="form-control mr-3" id="filterYear" name="filterYear">
                                    <?php
                                    // Tampilkan opsi tahun, misalnya dari 5 tahun yang lalu sampai 1 tahun mendatang
                                    $currentYear = date("Y");
                                    for ($y = $currentYear - 5; $y <= $currentYear + 1; $y++) {
                                        $selected = ($y == $filterYear) ? "selected" : "";
                                        echo '<option value="'.$y.'" '.$selected.'>'.$y.'</option>';
                                    }
                                    ?>
                                </select>
                                <button type="button" id="applyFilter" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Terapkan Filter
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Table Payroll Overview -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                              Daftar Payroll Periode <?= date("F", mktime(0,0,0,$filterMonth,1))." ".$filterYear; ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <?php
                                // Query data payroll berdasarkan bulan & tahun filter; sertakan a.id sebagai id_anggota
                                // Perbaiki: gunakan subquery untuk menentukan effective_status
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
                                    p.status,
                                    (CASE 
                                       WHEN (SELECT COUNT(*) FROM employee_payheads e 
                                             WHERE e.id_anggota = a.id AND e.status = 'revisi') > 0 
                                       THEN 'revisi'
                                       ELSE p.status
                                    END) AS effective_status
                                  FROM payroll p
                                  JOIN anggota_sekolah a ON p.id_anggota = a.id
                                  WHERE p.bulan = ? AND p.tahun = ?
                                  ORDER BY p.tgl_payroll DESC
                                ";
                                $stmt = $conn->prepare($query);
                                $stmt->bind_param("ii", $filterMonth, $filterYear);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                ?>
                                <table id="payrollTable" class="table table-bordered table-striped" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID Payroll</th>
                                            <th>Nama Karyawan</th>
                                            <th>NIP</th>
                                            <th>Jenjang</th>
                                            <th>Periode</th>
                                            <th>Status</th>
                                            <th>Tanggal Payroll</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        while ($row = $result->fetch_assoc()) {
                                            // Gunakan effective_status sebagai status tampilan
                                            $status = $row['effective_status'];
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['nip']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['jenjang']) . "</td>";
                                            $periode = date("F", mktime(0,0,0,$row['bulan'],1)) . " " . $row['tahun'];
                                            echo "<td>" . $periode . "</td>";
                                            echo "<td>" . htmlspecialchars($status) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['tgl_payroll']) . "</td>";
                                            
                                            // Aksi: Jika status (effective) masih draft atau revisi, tampilkan tombol "Review"
                                            // jika final, tampilkan tombol "Detail"
                                            echo "<td>";
                                            if ($status == 'draft' || $status == 'revisi') {
                                                echo '<a href="manage-salary.php?id_anggota=' . $row['id_anggota'] .
                                                     '&bulan=' . $row['bulan'] .
                                                     '&tahun=' . $row['tahun'] .
                                                     '" class="btn btn-sm btn-warning mr-1">Review</a>';
                                                echo '<span class="btn btn-sm btn-secondary disabled" title="Payroll belum final">Detail</span>';
                                            } else if ($status == 'final') {
                                                echo '<a href="payroll-details.php?id_payroll=' . $row['id'] .
                                                     '" class="btn btn-sm btn-info">Detail</a>';
                                            }
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                        $stmt->close();
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                </div> <!-- /.container-fluid -->
    </div> <!-- End of #wrapper -->

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="/payroll_absensi_v2/assets/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function(){
            // Inisialisasi DataTable
            $('#payrollTable').DataTable();

            // Saat tombol filter ditekan, arahkan ulang halaman dengan parameter filter
            $('#applyFilter').on('click', function(){
                var month = $('#filterMonth').val();
                var year  = $('#filterYear').val();
                window.location.href = 'list_payroll.php?filterMonth=' + month + '&filterYear=' + year;
            });
        });
    </script>
</body>
</html>
