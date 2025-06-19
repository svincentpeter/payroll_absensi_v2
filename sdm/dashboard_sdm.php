<?php
// File: /payroll_absensi_v2/sdm/dashboard_sdm.php

// 1. Inisialisasi Session, Keamanan, & Koneksi Database
$pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
require_once __DIR__ . '/../koneksi.php';

// Otorisasi pengguna (hanya role sdm & superadmin)
authorize(['M:SDM', 'M:superadmin']);

// Pastikan CSRF token telah di-generate
generate_csrf_token();

// --- [MODIF] ---
// Tarik meta jenjang dari DB, bukan hardcode!
$jenjangMeta = [];
$res = $conn->query("SELECT kode_jenjang, nama_jenjang, color_bg, color_fg FROM jenjang_sekolah WHERE is_aktif=1");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $icon = match(strtoupper($row['kode_jenjang'])) {
            'TK'        => 'fas fa-child',
            'SD'        => 'fas fa-book-open',
            'SMP'       => 'fas fa-user-graduate',
            'SMA'       => 'fas fa-chalkboard-teacher',
            'SMK1', 'SMK2' => 'fas fa-tools',
            'STIFERA'   => 'fas fa-microscope',
            'STIFERA'   => 'fas fa-microscope',
            'UMUM'      => 'fas fa-users',
            default     => 'fas fa-school',
        };
        $jenjangMeta[$row['kode_jenjang']] = [
            'icon'  => $icon,
            'color' => $row['color_bg'],
            'fg'    => $row['color_fg'],
            'label' => $row['nama_jenjang']
        ];
    }
    $res->close();
}
// Fallback (Lainnya)
$defaultMeta = [
    'icon'=>'fas fa-school',
    'color'=>'#34495e',
    'fg'=>'#fff',
    'label'=>'Lainnya'
];

// =========================
// 2. Tangani Permintaan AJAX
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $action = $_POST['action'];

    // ── 2a. Data anggota per jenjang ──
    if ($action === 'get_payroll_dashboard') {
        $query = "
          SELECT 
            COALESCE(jenjang,'Lainnya') AS jenjang,
            COUNT(*) AS total,
            SUM(role='P')  AS P,
            SUM(role='TK') AS TK,
            SUM(role='M')  AS M
          FROM anggota_sekolah
          GROUP BY jenjang
          ORDER BY FIELD(jenjang,'TK','SD','SMP','SMA','SMK1','SMK2','STIFERA','UMUM'), jenjang ASC
        ";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            send_response(1, 'Gagal menyiapkan query payroll dashboard: ' . $conn->error);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $detailData = [];
        while ($r = $res->fetch_assoc()) {
            $detailData[] = [
                'jenjang' => $r['jenjang'],
                'total'   => (int)$r['total'],
                'P'       => (int)$r['P'],
                'TK'      => (int)$r['TK'],
                'M'       => (int)$r['M'],
            ];
        }
        $stmt->close();
        send_response(0, [
            'detailData' => $detailData,
            'meta'       => $jenjangMeta,
            'default'    => $defaultMeta
        ]);
        exit;
    }
    // ── 2b. Data upcoming holidays ──
    elseif ($action === 'get_upcoming_holidays') {
        $today = date('Y-m-d');
        $sql   = "SELECT holiday_title, holiday_desc, holiday_date
                  FROM holidays
                  WHERE holiday_date >= ?
                  ORDER BY holiday_date ASC
                  LIMIT 5";
        $stmt  = $conn->prepare($sql);
        if (!$stmt) {
            send_response(1, 'Gagal menyiapkan query holidays: ' . $conn->error);
        }
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $res = $stmt->get_result();
        $holidays = [];
        while ($row = $res->fetch_assoc()) {
            $dt = new DateTime($row['holiday_date']);
            $row['holiday_date'] = $dt->format('j').' '.translate_month_dashboard($dt->format('F')).' '.$dt->format('Y');
            $holidays[] = $row;
        }
        $stmt->close();
        send_response(0, $holidays);
        exit;
    }
    // ── 2c. Data unpaid summary ──
    elseif ($action === 'get_unpaid_summary') {
        $bulan = isset($_POST['bulan']) ? (int)$_POST['bulan'] : date('n');
        $tahun = isset($_POST['tahun']) ? (int)$_POST['tahun'] : date('Y');

        // Validasi periode: bulan 1–12, tahun dalam jangkauan ±5 tahun
        $curY = (int)date('Y');
        if ($bulan < 1 || $bulan > 12 || $tahun < $curY - 5 || $tahun > $curY + 1) {
            send_response(1, 'Periode tidak valid.');
        }

        $sql = "SELECT COALESCE(jenjang, 'Lainnya') AS jenjang,
                       COUNT(a.id) AS total_unpaid
                FROM anggota_sekolah a
                WHERE a.id NOT IN (
                    SELECT p.id_anggota
                    FROM payroll p
                    WHERE p.bulan=? AND p.tahun=? AND p.status='final'
                )
                GROUP BY a.jenjang";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            send_response(1, 'Gagal menyiapkan query unpaid summary: ' . $conn->error);
        }
        $stmt->bind_param("ii", $bulan, $tahun);
        $stmt->execute();
        $res = $stmt->get_result();

        $unpaidData = [];
        while ($row = $res->fetch_assoc()) {
            $unpaidData[] = [
                'jenjang' => $row['jenjang'] ?: 'Lainnya',
                'total'   => (int)$row['total_unpaid']
            ];
        }
        $stmt->close();
        send_response(0, $unpaidData);
        exit;
    }
    // ── 2d. Aksi tidak dikenali ──
    else {
        send_response(404, 'Aksi tidak dikenali.');
        exit;
    }
}

// 3. Ambil Data Penting (KPI, dsb.)
$sqlTotalAnggota = "SELECT COUNT(*) AS total_anggota FROM anggota_sekolah";
$resTotal        = $conn->query($sqlTotalAnggota);
$totalAnggota    = 0;
if ($resTotal) {
    $totalAnggota = $resTotal->fetch_assoc()['total_anggota'] ?? 0;
    $resTotal->close();
}

// 4. Render Halaman
$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
    4 => 'April',   5 => 'Mei',      6 => 'Juni',
    7 => 'Juli',    8 => 'Agustus',  9 => 'September',
    10=> 'Oktober', 11=> 'November',12 => 'Desember'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard SDM</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <!-- Bootstrap 5 & SB Admin 2 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
  <!-- Icons -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body, .text-gray-800 { color: #000 !important; }
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
    .card-header-filter { background: linear-gradient(45deg, #4e73df, #224abe); color: #fff; }
    .chart-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
    .card-calendar { background: linear-gradient(45deg, #0f2027, #203a43, #2c5364); color: #fff; }
    .calendar { width:100%; table-layout:fixed; }
    .calendar th, .calendar td { text-align:center; padding:5px; font-size:.9rem; }
    .calendar .today { background:#42a5f5; color:#fff; font-weight:bold; }
    #digitalClock {
      font-size:2rem; font-weight:bold; text-align:center;
      background:#f8f9fc; padding:.75rem; border-radius:.25rem;
      margin-bottom:1rem;
    }
    .jenjang-table tbody tr:nth-child(odd) { background:rgba(0,0,0,0.03); }
    .jenjang-table tbody tr:hover          { background:rgba(0,0,0,0.05); }
  </style>
</head>
<body id="page-top">
  <div id="wrapper">
    <?php include __DIR__ . '/../sidebar.php'; ?>
    <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
        <?php include __DIR__ . '/../navbar.php'; ?>
        <?php include __DIR__ . '/../breadcrumb.php'; ?>

        <div class="container-fluid">
          <h1 class="page-title">
            <i class="fas fa-users me-2"></i>
            Dashboard SDM
          </h1>
          <!-- Filter Periode -->
          <div class="card mb-4">
            <div class="card-header card-header-filter">
              <i class="bi bi-funnel-fill"></i> Filter Periode
            </div>
            <div class="card-body">
              <div class="row g-3 align-items-end">
                <div class="col-md-3">
                  <label class="form-label">Bulan</label>
                  <select id="filterBulan" class="form-select">
                    <?php $nowM = date('n');
                    foreach ($monthNames as $num => $name): ?>
                      <option value="<?= $num ?>" <?= $num == $nowM ? 'selected' : '' ?>>
                        <?= $name ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Tahun</label>
                  <select id="filterTahun" class="form-select">
                    <?php $nowY = date('Y');
                    for ($y = $nowY - 3; $y <= $nowY + 3; $y++): ?>
                      <option value="<?= $y ?>" <?= $y == $nowY ? 'selected' : '' ?>>
                        <?= $y ?>
                      </option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <button id="btnApplyFilter" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Terapkan
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- KPI: Total Anggota & Holidays -->
          <div class="row mb-4">
            <div class="col-md-6 mb-4">
              <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                  <div class="row align-items-center">
                    <div class="col">
                      <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                        <i class="fas fa-users"></i> Total Anggota Sekolah
                      </div>
                      <div class="h5 mb-0 font-weight-bold text-gray-800">
                        <?= number_format($totalAnggota) ?>
                      </div>
                    </div>
                    <div class="col-auto">
                      <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                  </div>
                  <small class="text-muted">Guru & karyawan di semua jenjang.</small>
                </div>
              </div>
            </div>
            <div class="col-md-6 mb-4">
              <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                  <div class="row align-items-center">
                    <div class="col">
                      <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                        <i class="fas fa-calendar-alt"></i> Upcoming Holidays
                      </div>
                      <div id="holidaysList" class="mt-2" aria-live="polite"></div>
                    </div>
                    <div class="col-auto">
                      <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                    </div>
                  </div>
                  <small class="text-muted">5 hari libur terdekat.</small>
                </div>
              </div>
            </div>
          </div>

          <!-- Grid Widget -->
          <div class="chart-grid mb-4">
            <!-- Live Calendar & Clock -->
            <div class="card shadow">
              <div class="card-header card-calendar py-3">
                <h6 class="m-0 text-white">
                  <i class="fas fa-calendar-alt"></i> Live Calendar & Clock
                </h6>
              </div>
              <div class="card-body">
                <div id="digitalClock"></div>
                <div id="calendarContainer"></div>
              </div>
            </div>

            <!-- Unpaid Summary -->
            <div class="card shadow card-unpaid">
              <div class="card-header bg-danger text-white py-3">
                <h6 class="m-0 font-weight-bold">
                  <i class="fas fa-exclamation-circle"></i> Belum di Payroll Final
                </h6>
              </div>
              <div class="card-body">
                <div id="unpaidSummaryContainer" class="mb-3"></div>
                <canvas id="unpaidSummaryChart"></canvas>
              </div>
            </div>

            <!-- Jumlah Anggota per Jenjang -->
            <div class="card shadow mb-4">
              <div class="card-header bg-primary text-white py-3">
                <h6 class="m-0">
                  <i class="fas fa-chart-pie"></i> Jumlah Anggota per Jenjang
                </h6>
              </div>
              <div class="card-body">
                <ul id="jenjangList" class="list-group mb-3"></ul>
                <div class="table-responsive">
                  <table id="jenjangDetailTable" class="table table-bordered table-sm jenjang-table">
                    <thead>
                      <tr><th>Jenjang</th><th>Total</th><th>P</th><th>TK</th><th>M</th></tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div> <!-- /.container-fluid -->
      </div> <!-- /#content -->

      <footer class="sticky-footer bg-white">
        <div class="container text-center my-auto">
          &copy; <?= date("Y") ?> Payroll Management System | Developed By [Nama Anda]
        </div>
      </footer>
    </div> <!-- /#content-wrapper -->
  </div> <!-- /#wrapper -->

  <!-- Loading Spinner -->
  <div id="loadingSpinner" style="display:none;position:fixed;z-index:9999;
       top:50%;left:50%;transform:translate(-50%,-50%)">
    <div class="spinner-border text-primary"><span class="visually-hidden">Loading...</span></div>
  </div>

  <!-- JS Libraries -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
  $(function(){
    const jenjangMeta = <?= json_encode($jenjangMeta) ?>;
    const defaultMeta = <?= json_encode($defaultMeta) ?>;
    const Toast = Swal.mixin({
      toast:true,position:'top-end',showConfirmButton:false,
      timer:3000,timerProgressBar:true,
      didOpen:t=>{t.addEventListener('mouseenter',Swal.stopTimer);t.addEventListener('mouseleave',Swal.resumeTimer);}
    });
    const showToast = (msg,icon='success')=>Toast.fire({icon,title:msg});
    // Warna untuk unpaid summary chart
    function getJenjangColor(jenjang) {
      return jenjangMeta[jenjang]?.color || defaultMeta.color;
    }

    function updateClock(){
      const now=new Date(),
            opts={hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false,timeZone:'Asia/Jakarta'};
      $('#digitalClock').text(new Intl.DateTimeFormat('id-ID',opts).format(now));
    }
    setInterval(updateClock,1000);
    updateClock();

    function buildCalendar(){
      const t=new Date(), Y=t.getFullYear(), M=t.getMonth(), D=t.getDate();
      const monthNames=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
      const dayNames=['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
      let html=`<h5 class="text-center mb-2">${monthNames[M]} ${Y}</h5>
                <table class="calendar table table-bordered"><thead><tr>`;
      dayNames.forEach(d=>html+=`<th>${d}</th>`); html+='</tr></thead><tbody>';
      let firstDay=new Date(Y,M,1).getDay(), daysInMonth=new Date(Y,M+1,0).getDate(), day=1;
      for(let r=0;r<6;r++){
        html+='<tr>';
        for(let c=0;c<7;c++){
          if(r===0&&c<firstDay) html+='<td></td>';
          else if(day>daysInMonth) html+='<td></td>';
          else html+= (day===D)?`<td class="today">${day}</td>`:`<td>${day}</td>`, day++;
        }
        html+='</tr>';
        if(day>daysInMonth) break;
      }
      html+='</tbody></table>';
      $('#calendarContainer').html(html);
    }
    buildCalendar();

    function fetchHolidays(){
      $('#loadingSpinner').show();
      $.post('dashboard_sdm.php',{action:'get_upcoming_holidays',csrf_token:'<?= $_SESSION['csrf_token']?>'},resp=>{
        $('#loadingSpinner').hide();
        let html='<div class="text-muted">Tidak ada hari libur mendatang.</div>';
        if(resp.code===0&&resp.result.length){
          html=resp.result.map(h=>`
            <div class="mb-1">
              <strong>${h.holiday_title}</strong> (${h.holiday_date})<br>
              <small class="text-muted">${h.holiday_desc}</small>
            </div>
          `).join('');
        }
        $('#holidaysList').html(html);
      },'json').fail(()=>{$('#loadingSpinner').hide();showToast('Gagal memuat holidays','error');});
    }

    let unpaidChart;
    function fetchUnpaid(b,t){
      $('#loadingSpinner').show();
      $.post('dashboard_sdm.php',{action:'get_unpaid_summary',bulan:b,tahun:t,csrf_token:'<?= $_SESSION['csrf_token']?>'},resp=>{
        $('#loadingSpinner').hide();
        if(resp.code!==0) return showToast(resp.result,'error');
        const data=resp.result;
        if(!data.length){
          $('#unpaidSummaryContainer').html('<div class="alert alert-success">Semua sudah final.</div>');
          if(unpaidChart) unpaidChart.destroy();
          return;
        }
        let totalAll=0;
        let listHtml='<ul class="list-group mb-3">';
        data.forEach(d=>{
          totalAll+=d.total;
          const cfg = jenjangMeta[d.jenjang] ?? defaultMeta;
          const label = cfg.label || d.jenjang;
          listHtml+=`
            <li class="list-group-item d-flex justify-content-between align-items-center"
                style="border-left:.25rem solid ${cfg.color};">
              <strong><i class="${cfg.icon} me-2" style="color:${cfg.color}"></i>${label}</strong>
              <span class="badge bg-danger">${d.total}</span>
            </li>`;
        });
        listHtml+='</ul><p class="fw-bold">Total belum final: '+totalAll+'</p>';
        $('#unpaidSummaryContainer').html(listHtml);

        const ctx=$('#unpaidSummaryChart')[0].getContext('2d');
        if(unpaidChart) unpaidChart.destroy();
        unpaidChart=new Chart(ctx,{
          type:'bar',
          data:{
            labels:data.map(d=>{
              const cfg = jenjangMeta[d.jenjang] ?? defaultMeta;
              return cfg.label || d.jenjang;
            }),
            datasets:[{
              label:'Belum Final',
              data:data.map(d=>d.total),
              backgroundColor:data.map(d=>getJenjangColor(d.jenjang)),
              borderColor:data.map(d=>getJenjangColor(d.jenjang)),
              borderWidth:1
            }]
          },
          options:{responsive:true,scales:{y:{beginAtZero:true,ticks:{precision:0}}}}
        });
      }).fail(()=>{$('#loadingSpinner').hide();showToast('Gagal memuat unpaid','error');});
    }

    function fetchPayroll(){
      $('#loadingSpinner').show();
      $.post('dashboard_sdm.php',{action:'get_payroll_dashboard',csrf_token:'<?= $_SESSION['csrf_token']?>'},resp=>{
        $('#loadingSpinner').hide();
        if(resp.code!==0) return showToast(resp.result,'error');
        const { detailData, meta, default: defaultMeta } = resp.result;
        $('#jenjangList').html(detailData.map(d => {
          const cfg = meta[d.jenjang] ?? defaultMeta;
          const label = cfg.label || d.jenjang;
          return `
            <li class="list-group-item d-flex justify-content-between align-items-center"
                style="border-left:.25rem solid ${cfg.color};">
              <div><i class="${cfg.icon} me-2" style="color:${cfg.color}"></i>${label}</div>
              <span class="badge" style="background-color:${cfg.color};color:${cfg.fg}">${d.total}</span>
            </li>`;
        }).join(''));
        $('#jenjangDetailTable tbody').html(detailData.map(d=>{
          const cfg = meta[d.jenjang] ?? defaultMeta;
          const label = cfg.label || d.jenjang;
          return `
            <tr>
              <td><i class="${cfg.icon} me-2" style="color:${cfg.color}"></i>${label}</td>
              <td>${d.total}</td>
              <td>${d.P}</td>
              <td>${d.TK}</td>
              <td>${d.M}</td>
            </tr>`;
        }).join(''));
      }).fail(err=>console.error(err));
    }

    fetchHolidays();
    const now=new Date(), curM=now.getMonth()+1, curY=now.getFullYear();
    fetchUnpaid(curM,curY);
    fetchPayroll();

    $('#btnApplyFilter').click(()=>{
      const m=+$('#filterBulan').val(), y=+$('#filterTahun').val();
      fetchUnpaid(m,y);
      showToast(`Menampilkan periode ${monthNames[m]} ${y}`,'info');
    });
  });
  </script>
</body>
</html>
<?php close_db_connection(); ?>
