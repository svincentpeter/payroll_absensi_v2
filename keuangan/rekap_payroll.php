<?php
// File: /payroll_absensi_v2/keuangan/rekap_payroll.php

$pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:Keuangan','M:Superadmin']);
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

require_once __DIR__ . '/../koneksi.php';

// default periode
$filterMonth = intval($_GET['bulan'] ?? date('n'));
$filterYear  = intval($_GET['tahun'] ?? date('Y'));

// audit log
add_audit_log(
    $conn,
    $_SESSION['nip'],
    'ViewRekapPayroll',
    "Akses rekap payroll periode {$filterMonth}/{$filterYear}"
);

// daftar jenjang
$jenjangList = getOrderedJenjang();

// Palet warna & icon per jenjang
$jenjangMeta = [
  'TK'  => ['icon'=>'fas fa-child',              'color'=>'#e74c3c','rgba'=>'rgba(231,76,60,0.1)'],
  'SD'  => ['icon'=>'fas fa-book-open',          'color'=>'#3498db','rgba'=>'rgba(52,152,219,0.1)'],
  'SMP' => ['icon'=>'fas fa-user-graduate',      'color'=>'#2ecc71','rgba'=>'rgba(46,204,113,0.1)'],
  'SMA' => ['icon'=>'fas fa-chalkboard-teacher', 'color'=>'#f1c40f','rgba'=>'rgba(241,196,15,0.1)'],
  'SMK' => ['icon'=>'fas fa-tools',              'color'=>'#9b59b6','rgba'=>'rgba(155,89,182,0.1)'],
  // jika ada jenjang lain:
  // 'MAN' => ['icon'=>'fas fa-university','color'=>'#16a085','rgba'=>'rgba(22,160,133,0.1)'],
];
$defaultMeta = ['icon'=>'fas fa-building','color'=>'#16a085','rgba'=>'rgba(22,160,133,0.1)'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Rekap Payroll</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap & SB Admin 2 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">

  <style>
    body { background: #f7f9fc; }

    /* ─── HEADER PERIODE AS CARD ───────────────────────────────────────── */
    .card-header { 
      background: #fff;
      border-bottom: 2px solid #dee2e6;
    }
    .card-header h4 { 
      margin: 0;
      font-size: 1.35rem;
      font-weight: 600;
    }
    .period-badge {
      background: #eef2f5;
      color: #333;
      font-size: 1rem;
      padding: .5rem 1rem;
      border-radius: .5rem;
      margin-left: 1rem;
    }
    #btnChangePeriod {
      border: none;
      background: #fff;
      color: #495057;
      transition: color .2s, box-shadow .2s;
    }
    #btnChangePeriod:hover {
      color: #0056b3;
      box-shadow: 0 .2rem .4rem rgba(0,0,0,.1);
    }

    /* ─── GRID JENJANG ────────────────────────────────────────────────── */
    #jenjangGrid {
      margin-top: 1.5rem;
    }
    .jenjang-card {
      display: block;
      padding: 1.5rem 0;
      border-radius: .75rem;
      text-decoration: none;
      transition: transform .2s, box-shadow .2s;
    }
    .jenjang-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 .6rem 1rem rgba(0,0,0,.1);
    }
    .jenjang-icon {
      font-size: 2.8rem;
      margin-bottom: .5rem;
    }
    .jenjang-label {
      font-weight: 600;
      font-size: 1rem;
      color: #343a40;
    }

    /* ─── MODAL PILIH PERIODE ─────────────────────────────────────────── */
    .modal-dialog { max-width: 700px; }
    .modal-content { border-radius: .75rem; }
    .modal-body { background: #fff; padding: 2rem; }
    .select-period {
      width: 100%;
      padding: .75rem 0;
      border: 1px solid #adb5bd;
      border-radius: .5rem;
      font-weight: 500;
      transition: background .2s, border-color .2s;
    }
    .select-period:hover {
      background: #e9ecef;
      border-color: #868e96;
    }
    .select-period.active {
      background: #3498db;      /* sama dengan SD, tapi kontras */
      border-color: #2980b9;
      color: #fff;
    }
  </style>
</head>
<body id="page-top">
  <div id="wrapper">
    <?php include __DIR__ . '/../sidebar.php'; ?>

    <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
        <?php include __DIR__ . '/../navbar.php'; ?>
        <?php include __DIR__ . '/../breadcrumb.php'; ?>

        <div class="container-fluid py-4">

          <!-- Card Header Periode -->
          <div class="card shadow-sm mb-4">
            <div class="card-header d-flex align-items-center">
              <h4><i class="fas fa-calendar-alt text-primary me-2"></i> Payroll:</h4>
              <span class="period-badge">
                <?= getIndonesianMonthName($filterMonth) . ' ' . $filterYear ?>
              </span>
              <button id="btnChangePeriod" class="btn ms-auto">
                <i class="fas fa-edit me-1"></i> Ganti Periode
              </button>
            </div>
          </div>

          <!-- Grid Jenjang -->
          <div class="row g-3" id="jenjangGrid">
            <?php foreach($jenjangList as $jenjang):
              $meta = $jenjangMeta[$jenjang] ?? $defaultMeta;
            ?>
              <div class="col-6 col-md-4 col-lg-3">
                <a href="rekap_payroll_jenjang.php?jenjang=<?= urlencode($jenjang) ?>&bulan=<?= $filterMonth ?>&tahun=<?= $filterYear ?>"
                   class="jenjang-card text-center"
                   style="background: <?= $meta['rgba'] ?>; border-left: 5px solid <?= $meta['color'] ?>;">
                  <i class="<?= $meta['icon'] ?> jenjang-icon" style="color: <?= $meta['color'] ?>;"></i>
                  <div class="jenjang-label"><?= htmlspecialchars($jenjang) ?></div>
                </a>
              </div>
            <?php endforeach; ?>
          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- Modal Pilih Periode -->
  <div class="modal fade" id="periodModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0">
          <h5 class="modal-title"><i class="fas fa-calendar-alt text-primary me-2"></i> Pilih Periode</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <?php
              $startOffset = -2;
              for ($i = 0; $i < 16; $i++):
                $m = $filterMonth + $startOffset + $i;
                $y = $filterYear;
                if ($m < 1)  { $m += 12; $y--; }
                if ($m > 12) { $m -= 12; $y++; }
                $isActive = ($m === $filterMonth && $y === $filterYear);
            ?>
              <div class="col-6 col-md-3">
                <button class="select-period <?= $isActive ? 'active' : '' ?>"
                        data-month="<?= $m ?>" data-year="<?= $y ?>">
                  <?= strtoupper(getIndonesianMonthName($m)) ?><br>
                  <small><?= $y ?></small>
                </button>
              </div>
            <?php endfor; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- JS -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    $(function(){
      const modal = new bootstrap.Modal($('#periodModal'));
      $('#btnChangePeriod').click(() => modal.show());
      $('.select-period').click(function(){
        const bulan = $(this).data('month'),
              tahun = $(this).data('year');
        window.location.href = `rekap_payroll.php?bulan=${bulan}&tahun=${tahun}`;
      });
    });
  </script>
</body>
</html>
