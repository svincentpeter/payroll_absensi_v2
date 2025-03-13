<?php
// File: /payroll_absensi_v2/payroll/keuangan/notifikasi_keuangan.php

require_once __DIR__ . '/../../koneksi.php';    
require_once __DIR__ . '/../../helpers.php';     

start_session_safe();

// Hanya untuk user dengan role keuangan / superadmin
authorize(['M:Keuangan', 'M:Superadmin'], '/payroll_absensi_v2/login.php');

// Inisialisasi variabel filter (bulan & tahun)
$filterMonth = isset($_GET['filterMonth']) ? intval($_GET['filterMonth']) : date('n');
$filterYear  = isset($_GET['filterYear'])  ? intval($_GET['filterYear'])  : date('Y');

// Ambil nama bulan Indonesia
$monthName = getIndonesianMonthName($filterMonth);

/**
 * Di sini kita menghitung jumlah payroll dengan status 'draft' 
 * (belum final) persis seperti list_payroll, 
 * lalu mengelompokkan berdasarkan jenjang, 
 * dan menjumlah role-nya (P, TK, M).
 */
$sql = "
    SELECT 
        a.jenjang,
        SUM(CASE WHEN a.role = 'P'  THEN 1 ELSE 0 END) AS p_count,
        SUM(CASE WHEN a.role = 'TK' THEN 1 ELSE 0 END) AS tk_count,
        SUM(CASE WHEN a.role = 'M'  THEN 1 ELSE 0 END) AS m_count
    FROM payroll p
    JOIN anggota_sekolah a ON p.id_anggota = a.id
    WHERE p.bulan = ?
      AND p.tahun = ?
      AND p.status = 'draft'
      AND NOT EXISTS (
            SELECT 1 
            FROM payroll_final pf
            WHERE pf.id_anggota = p.id_anggota
              AND pf.bulan = p.bulan
              AND pf.tahun = p.tahun
      )
    GROUP BY a.jenjang
    ORDER BY a.jenjang ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $filterMonth, $filterYear);
$stmt->execute();
$result = $stmt->get_result();
$groupedData = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Notifikasi Keuangan</title>
  <!-- SB Admin 2 + Bootstrap 5.3.3 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body id="page-top">

  <!-- Page Wrapper -->
  <div id="wrapper">

    <!-- Sidebar -->
    <?php include __DIR__ . '/../../sidebar.php'; ?>
    <!-- End of Sidebar -->

    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column">
    <?php 
                // Jika punya breadcrumb.php, sertakan
                include __DIR__ . '/../../breadcrumb.php'; 
                ?>
      <!-- Main Content -->
      <div id="content">



        <!-- Begin Page Content -->
        <div class="container-fluid">

          <!-- Page Heading -->
          <h1 class="h3 mb-4 text-gray-800">
            <i class="fas fa-bell"></i> Notifikasi Keuangan
          </h1>

          <!-- Card Filter -->
          <div class="card mb-4">
            <div class="card-header">
              <i class="bi bi-funnel-fill"></i> Filter Alerts
            </div>
            <div class="card-body">
              <form method="GET" class="row g-3 align-items-end">
                <div class="col-auto">
                  <label for="filterMonth" class="form-label fw-bold">Bulan</label>
                  <select name="filterMonth" id="filterMonth" class="form-select">
                    <?php
                    for ($m = 1; $m <= 12; $m++):
                      $selected = ($m == $filterMonth) ? 'selected' : '';
                      echo "<option value='{$m}' {$selected}>" . getIndonesianMonthName($m) . "</option>";
                    endfor;
                    ?>
                  </select>
                </div>
                <div class="col-auto">
                  <label for="filterYear" class="form-label fw-bold">Tahun</label>
                  <select name="filterYear" id="filterYear" class="form-select">
                    <?php
                    $currentY = date('Y');
                    for ($y = $currentY - 2; $y <= $currentY + 2; $y++):
                      $selected = ($y == $filterYear) ? 'selected' : '';
                      echo "<option value='{$y}' {$selected}>{$y}</option>";
                    endfor;
                    ?>
                  </select>
                </div>
                <div class="col-auto">
                  <button type="submit" class="btn btn-primary mt-2">
                    <i class="fas fa-filter"></i> Terapkan
                  </button>
                </div>
              </form>
            </div>
          </div>
          <!-- End Card Filter -->

          <!-- Info Periode -->
          <div class="alert alert-info fw-bold">
            Periode: <?= htmlspecialchars($monthName . " " . $filterYear); ?>
          </div>

          <!-- Notifikasi List -->
          <?php if (!empty($groupedData)): ?>
            <?php foreach ($groupedData as $row): ?>
              <?php 
                $jenjang = $row['jenjang'] ?: 'Tidak Diketahui';
                $p_count = (int) $row['p_count'];
                $tk_count= (int) $row['tk_count'];
                $m_count = (int) $row['m_count'];
                $total   = $p_count + $tk_count + $m_count;
              ?>
              <!-- Card Notifikasi -->
              <div class="card mb-3 shadow-sm">
                <div class="card-body d-flex">
                  <!-- Ikon di sisi kiri -->
                  <div class="flex-shrink-0 me-3">
                    <div class="icon-circle bg-warning text-center" style="width:48px; height:48px; border-radius:50%;">
                      <i class="fas fa-exclamation-triangle text-white" style="line-height:48px;"></i>
                    </div>
                  </div>
                  <!-- Isi Notifikasi -->
                  <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <div>
                        <span class="badge bg-warning text-dark me-2">
                          Jenjang: <?= htmlspecialchars($jenjang); ?>
                        </span>
                        <strong><?= $total; ?> Anggota (status draft)</strong>
                      </div>
                      <small class="text-muted">
                        <i class="bi bi-calendar-check"></i>
                        <?= htmlspecialchars($monthName . " " . $filterYear); ?>
                      </small>
                    </div>
                    <div class="text-muted small">
                      Role:
                      <strong>P = <?= $p_count; ?></strong>;
                      <strong>TK = <?= $tk_count; ?></strong>;
                      <strong>M = <?= $m_count; ?></strong>
                    </div>
                  </div>
                </div>
              </div>
              <!-- End Card Notifikasi -->
            <?php endforeach; ?>

          <?php else: ?>
            <div class="alert alert-success">
              <i class="bi bi-check-circle-fill"></i>
              Tidak ada data payroll <em>draft</em> di periode ini!
            </div>
          <?php endif; ?>
          <!-- End Notifikasi List -->

        </div>
        <!-- End Page Content -->

      </div>
      <!-- End Main Content -->

      <!-- Footer -->
      <footer class="sticky-footer bg-white">
        <div class="container my-auto">
          <div class="copyright text-center my-auto">
            <span>&copy; <?= date('Y'); ?> - Your School/Company</span>
          </div>
        </div>
      </footer>
      <!-- End Footer -->

    </div>
    <!-- End Content Wrapper -->

  </div>
  <!-- End Page Wrapper -->

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>
