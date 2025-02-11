<?php
// File: /payroll_absensi_v2/payroll/keuangan/list_payroll.php
session_start();

// Pastikan hanya user dengan role 'keuangan' atau 'superadmin' yang dapat mengakses halaman ini.
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['keuangan','superadmin'])) {
    header("Location: /payroll_absensi_v2/login.php");
    exit();
}

// Sertakan koneksi ke database dan helper (jika ada)
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../helpers.php';

// Ambil filter bulan & tahun dari GET; jika tidak ada, gunakan bulan dan tahun sekarang.
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
        .header-period { cursor: pointer; }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <!-- End of Sidebar -->

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Navbar -->
                <?php include __DIR__ . '/../../navbar.php'; ?>
                <!-- End of Navbar -->

                <div class="container-fluid">
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/payroll_absensi_v2/payroll/keuangan/dashboard_keuangan.php">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Payroll Overview</li>
                        </ol>
                    </nav>

                    <!-- Header: Tampilkan periode payroll yang terpilih, dengan tombol Ganti Kalender -->
                    <div id="selectedMonthDisplay" class="mb-4 header-period">
                        <h4>Payroll Bulan: <?= date("F", mktime(0,0,0,$filterMonth,1)); ?> <?= $filterYear; ?> 
                            <button id="btnChangeCalendar" class="btn btn-link">Ganti Kalender</button>
                        </h4>
                    </div>

                    <!-- Tabel Payroll Overview -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 bg-primary">
                            <h6 class="m-0 text-white">
                                <i class="fas fa-file-invoice-dollar"></i> Daftar Payroll Periode <?= date("F", mktime(0,0,0,$filterMonth,1)) . " " . $filterYear; ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <?php
                                /*
                                 * Query ini menampilkan data payroll dengan status 'draft' untuk periode yang dipilih,
                                 * namun hanya untuk karyawan yang belum memiliki data final di tabel payroll_final
                                 * untuk bulan dan tahun yang sama.
                                 */
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
                                    p.catatan
                                  FROM payroll p
                                  JOIN anggota_sekolah a ON p.id_anggota = a.id
                                  WHERE p.bulan = ? 
                                    AND p.tahun = ? 
                                    AND p.status = 'draft'
                                    AND NOT EXISTS (
                                        SELECT 1 FROM payroll_final pf
                                        WHERE pf.id_anggota = p.id_anggota
                                          AND pf.bulan = p.bulan
                                          AND pf.tahun = p.tahun
                                    )
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
                                            $periode = date("F", mktime(0,0,0,$row['bulan'],1)) . " " . $row['tahun'];
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['nip']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['jenjang']) . "</td>";
                                            echo "<td>" . $periode . "</td>";
                                            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['tgl_payroll']) . "</td>";
                                            echo "<td>";
                                            echo '<a href="manage-salary.php?id_anggota=' . $row['id_anggota'] .
                                                 '&bulan=' . $row['bulan'] .
                                                 '&tahun=' . $row['tahun'] .
                                                 '" class="btn btn-sm btn-warning mr-1">Review</a>';
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                        $stmt->close();
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
                // Membuat grid bulan: tampilkan 16 pilihan, misalnya 2 bulan sebelum sampai 13 bulan ke depan
                $currentYear  = date('Y');
                $currentMonth = date('n');
                $startMonth = $currentMonth - 2;
                $startYear  = $currentYear;
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
                    // Highlight bulan yang sedang dipilih (berdasarkan filter saat ini)
                    $highlight = ($month == $filterMonth && $year == $filterYear) ? 'bg-warning text-dark font-weight-bold' : 'bg-light';
                    echo '<div class="col-3 mb-3">';
                    echo '  <div class="p-2 ' . $highlight . '" style="border: 1px solid #ddd; border-radius: 5px;">';
                    echo '    <a href="#" class="month-link" data-month-number="' . $month . '" data-month="' . htmlspecialchars(date("F", mktime(0, 0, 0, $month, 1))) . '" data-year="' . $year . '" style="color: inherit; text-decoration: none;">';
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
    <script src="/payroll_absensi_v2/assets/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function(){
            // Inisialisasi DataTable untuk tabel payroll
            $('#payrollTable').DataTable();

            // Saat tombol "Ganti Kalender" (atau area header) diklik, tampilkan modal SalaryMonthModal
            $('#btnChangeCalendar, #selectedMonthDisplay').on('click', function(e){
                e.preventDefault();
                $('#SalaryMonthModal').modal('show');
            });

            // Event handler untuk memilih bulan melalui modal
            $(document).on('click', '.month-link', function(e){
                e.preventDefault();
                var monthNumber = $(this).data('month-number');
                var monthName = $(this).data('month');
                var year = $(this).data('year');

                // Simpan pilihan bulan ke localStorage (jika diperlukan)
                localStorage.setItem('selectedMonthPayroll', monthName);
                localStorage.setItem('selectedMonthNumber', monthNumber);
                localStorage.setItem('selectedYearPayroll', year);

                // Arahkan ulang halaman dengan parameter filter baru
                window.location.href = 'list_payroll.php?filterMonth=' + monthNumber + '&filterYear=' + year;
            });
        });
    </script>
</body>
</html>
