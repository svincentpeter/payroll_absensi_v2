<?php
// File: /sdm/kelebihan_jam_mengajar.php
$pageId = basename(__DIR__) . '_' . pathinfo(__FILE__, PATHINFO_FILENAME);

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
generate_csrf_token();
$csrf = $_SESSION['csrf_token'] ?? '';

require_once __DIR__ . '/../koneksi.php';
authorize(['M:SDM']);

// Ambil periode default dan filter GET
$selectedMonth  = intval($_GET['filterMonth']  ?? date('n'));
$selectedYear   = intval($_GET['filterYear']   ?? date('Y'));
$selectedWeeks  = intval($_GET['filterWeeks']  ?? 4);
$filterJenjang  = $_GET['jenjang']   ?? '';
$filterRole     = $_GET['role']      ?? '';
$filterSearch   = $_GET['search']    ?? '';

// Query daftar jenjang (untuk filter)
$jenjangList = getOrderedJenjang($conn);

// Query guru dengan filter
$sql = "
  SELECT id,nama,nip,foto_profil,jenjang,role,job_title
    FROM anggota_sekolah
   WHERE deleted_at IS NULL
     AND role IN ('P','TK')
";
$params = [];
$types  = '';
if ($filterJenjang!=='') {
  $sql .= " AND jenjang = ?";
  $types .= 's'; $params[] = $filterJenjang;
}
if ($filterRole!=='') {
  $sql .= " AND role = ?";
  $types .= 's'; $params[] = $filterRole;
}
if ($filterSearch!=='') {
  $sql .= " AND (nama LIKE CONCAT('%',?,'%') OR nip LIKE CONCAT('%',?,'%'))";
  $types .= 'ss'; $params[] = $filterSearch; $params[] = $filterSearch;
}
$sql .= " ORDER BY nama";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$emps = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Draft kelebihan jam
$draft = [];
$res2 = $conn->query("
  SELECT id_anggota, minggu_ke, jam_extra
    FROM kelebihan_jam_mengajar
   WHERE bulan={$selectedMonth} AND tahun={$selectedYear}
");
while ($r = $res2->fetch_assoc()) {
    $draft[$r['id_anggota']] = ['minggu_ke'=>$r['minggu_ke'],'jam'=>$r['jam_extra']];
}

// ======== TAMBAHAN: QUERY REKAP KELEBIHAN JAM =========
$rekap = [];
$res3 = $conn->query("
  SELECT k.*, a.nama, a.nip
    FROM kelebihan_jam_mengajar k
    JOIN anggota_sekolah a ON a.id = k.id_anggota
   WHERE k.bulan = {$selectedMonth}
     AND k.tahun = {$selectedYear}
   ORDER BY a.nama
");
while ($r = $res3->fetch_assoc()) $rekap[] = $r;

// Hitung summary hanya untuk yang masuk filter
$countFilled = $totalJam = 0;
foreach ($emps as $e) {
    $v = floatval($draft[$e['id']]['jam'] ?? 0);
    if ($v > 0) {
        $countFilled++;
        $totalJam += $v;
    }
}

$is_final = 0;
if(isset($draft[$e['id']])) $is_final = intval($rekap[array_search($e['id'],$rekap)]['is_final'] ?? 0);

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Honor Kelebihan Jam Mengajar</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- CSS Dependencies -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/autonumeric@4.8.0/dist/autoNumeric.min.js"></script>
  <style>
    body { background: #f7f9fc; }

    :root {
            --primary-gradient: linear-gradient(135deg, #3a7bd5 0%, #00d2ff 100%);
            --secondary-gradient: linear-gradient(to right, #4e54c8, #8f94fb);
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --card-hover-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
    .page-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 2.5rem;
            color: #0d47a1;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 3px solid #1976d2;
            padding-bottom: 0.3rem;
            margin-bottom: 1.5rem;
            animation: fadeInSlide 0.5s ease-in-out both;
        }
    .btn-success,.btn-warning { color:#000!important; }
    .employee-card { border-radius:.85rem;
      box-shadow:0 4px 12px rgba(0,0,0,.09); transition:.2s;
    }
    .employee-card:hover { box-shadow:0 10px 24px rgba(52,85,195,.16); }
    .employee-photo { width:80px;height:80px;object-fit:cover;
      border-radius:50%;margin:.7rem auto;border:2px solid #fff;
      box-shadow:0 2px 6px rgba(0,0,0,.1);
    }
    .modal-header { background: linear-gradient(45deg,#0d47a1,#42a5f5); color:#fff; }
    .month-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:.5rem; }
    .month-grid button { border:none;padding:.5rem;border-radius:.5rem;background:#e9ecef; }
    .month-grid .active { background:#0d6efd;color:#fff; }

    .card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: var(--card-shadow);
        }

        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }
  </style>
  <script> const CSRF_TOKEN = '<?= htmlspecialchars($csrf) ?>'; </script>
</head>
<body>
  <div id="wrapper">
    <?php include __DIR__.'/../sidebar.php'; ?>
    <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
        <?php include __DIR__.'/../navbar.php'; ?>
        <?php include __DIR__.'/../breadcrumb.php'; ?>
        <div class="container-fluid">

          <!-- Title + Pilih Periode -->
          <div class="page-title">
            <i class="bi bi-clock-history"></i> Honor Kelebihan Jam Mengajar
            <button class="btn btn-outline-primary btn-sm ms-auto"
                    data-bs-toggle="modal" data-bs-target="#modalPeriod">
              <i class="bi bi-calendar3"></i> Pilih Periode
            </button>
          </div>

          <!-- Filter Anggota -->
          <div class="card mb-4 shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
              <h6 class="m-0 fw-bold text-white">
                <i class="bi bi-filter-square-fill"></i> Filter Guru
              </h6>
            </div>
            <div class="card-body" style="background-color:#f8f9fa;">
              <form id="filterForm" method="GET" class="row gy-2 gx-3 align-items-center">
                <input type="hidden" name="filterMonth" value="<?= $selectedMonth ?>">
                <input type="hidden" name="filterYear"  value="<?= $selectedYear ?>">
                <input type="hidden" name="filterWeeks" value="<?= $selectedWeeks ?>">
                <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrf) ?>">

                <!-- Jenjang -->
                <div class="col-auto">
                  <label class="form-label mb-0"><strong>Jenjang:</strong></label>
                  <select class="form-select" name="jenjang">
                    <option value="">Semua Jenjang</option>
                    <?php foreach($jenjangList as $kode=>$nama): ?>
                      <option value="<?= $kode ?>"
                        <?= $filterJenjang===$kode?'selected':'' ?>>
                        <?= htmlspecialchars($nama) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Role -->
                <div class="col-auto">
                  <label class="form-label mb-0"><strong>Role:</strong></label>
                  <select class="form-select" name="role">
                    <option value="">Semua Role</option>
                    <?php
                      $roles = ['P'=>'Pendidik','TK'=>'Tenaga Kependidikan'];
                      foreach($roles as $r=>$lab): ?>
                      <option value="<?= $r ?>"
                        <?= $filterRole===$r?'selected':'' ?>>
                        <?= $lab ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Pencarian -->
                <div class="col-auto">
                  <label class="form-label mb-0"><strong>Cari:</strong></label>
                  <input type="text" class="form-control" name="search"
                         placeholder="Nama / NIP..." value="<?= htmlspecialchars($filterSearch) ?>">
                </div>

                <!-- Tombol -->
                <div class="col-auto d-flex align-items-end">
                  <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-filter"></i> Terapkan
                  </button>
                  <a href="kelebihan_jam_mengajar.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                  </a>
                </div>
              </form>
            </div>
          </div>
          <!-- End Filter Guru -->

          <!-- Summary Cards -->
          <div class="row mb-3">
            <div class="col-md-4"><div class="card shadow-sm"><div class="card-body">
              <i class="bi bi-calendar-check-fill text-primary"></i>
              <span class="fw-bold">Periode:</span>
              <?= getIndonesianMonthName($selectedMonth).' '.$selectedYear.' — '.$selectedWeeks.' Minggu' ?>
            </div></div></div>

            <div class="col-md-4"><div class="card shadow-sm"><div class="card-body">
              <i class="bi bi-person-check text-success"></i>
              <span class="fw-bold">Terisi:</span>
              <?= $countFilled ?>/<?= count($emps) ?> guru
            </div></div></div>

            <div class="col-md-4"><div class="card shadow-sm"><div class="card-body">
              <i class="bi bi-plus-circle text-info"></i>
              <span class="fw-bold">Total Jam:</span>
              <?= $totalJam ?>
            </div></div></div>
          </div>

          <!-- Action Buttons -->
          <div class="mb-3">
            <button class="btn btn-success me-2" id="btnSaveAll"><i class="bi bi-save"></i> Simpan Semua</button>
            <button class="btn btn-warning me-2" id="btnProcessAll">
  <i class="bi bi-lock"></i> Finalisasi
</button>
            <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#modalTarif">
              <i class="bi bi-currency-dollar"></i> Edit Tarif
            </button>
          </div>

          <!-- Grid Guru -->
          <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
            <?php foreach($emps as $e):
    $jam    = floatval($draft[$e['id']]['jam'] ?? 0);
    $filled = $jam>0;
    $foto   = ($e['foto_profil'] && strtolower($e['foto_profil'])!=='default.jpg')
              ? rawurlencode($e['foto_profil'])
              : 'undraw_profile.svg';
?>

  <div class="col">
    <div class="card employee-card h-100 text-center" data-id="<?= $e['id'] ?>">
      <div class="card-body d-flex flex-column">
        <img src="<?= getBaseUrl() ?>/assets/img/<?= $foto ?>"
             class="employee-photo" alt="">
        <h6 class="card-title mt-2"><?= htmlspecialchars($e['nama']) ?></h6>
        <p class="small text-muted mb-1">NIP: <?= htmlspecialchars($e['nip']) ?></p>

        <!-- NEW: Jenjang, Role, Job Title -->
        <div class="mb-2">
          <!-- Jenjang -->
          <?php
            // ini pakai helper getBadgeJenjang, membutuhkan $conn:
            echo getBadgeJenjang($e['jenjang'], $conn);
          ?>
          <!-- Role -->
          <?= getBadgeRole($e['role']) ?>
        </div>
        <?php if (!empty($e['job_title'])): ?>
          <div class="small text-secondary mb-2">
            <i class="bi bi-briefcase-fill"></i>
            <?= htmlspecialchars($e['job_title']) ?>
          </div>
        <?php endif; ?>
        <!-- END NEW -->

        <div class="mt-auto">
          <div class="input-group input-group-sm mb-2">
            <span class="input-group-text">Jam</span>
            <input type="text" class="form-control input-jam"
                  value="<?= $jam ?>" placeholder="0" <?= $is_final ? 'readonly disabled' : '' ?> />
          </div>
          <?php if($filled): ?>
            <span class="badge bg-success-subtle text-success small">
              Terisi <?= $jam ?> jam
            </span>
          <?php else: ?>
            <span class="badge bg-warning-subtle text-warning small">
              Belum input
            </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Pilih Periode -->
<div class="modal fade" id="modalPeriod" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-calendar3"></i> Pilih Periode</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Tahun</label>
            <select id="selYear" class="form-select">
              <?php for($y=date('Y');$y>=2020;$y--): ?>
              <option value="<?=$y?>" <?=$y==$selectedYear?'selected':''?>><?=$y?></option>
              <?php endfor;?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Minggu/Bulan</label>
            <select id="selWeeks" class="form-select">
              <option value="4" <?=$selectedWeeks==4?'selected':''?>>4 Minggu</option>
              <option value="5" <?=$selectedWeeks==5?'selected':''?>>5 Minggu</option>
            </select>
          </div>
        </div>
        <div class="month-grid">
          <?php foreach(['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i=>$m): ?>
            <button type="button"
                    class="<?=($i+1)==$selectedMonth?'active':''?>"
                    data-month="<?=$i+1?>">
              <?=$m?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" id="applyPeriod">
          <i class="bi bi-check-circle"></i> Terapkan
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Edit Tarif -->
<div class="modal fade" id="modalTarif" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formTarif">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-currency-dollar"></i> Edit Tarif per Jam</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Nominal (Rp)</label>
          <input type="text" class="form-control" id="tarifNominal" placeholder="0" required />
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- JS Dependencies -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(function(){
  const api = 'includes/sistem_kelebihan_jam_mengajar.php';
  const Toast = Swal.mixin({ toast:true, position:'top-end', timer:2500, showConfirmButton:false });
  let selMonth = <?= $selectedMonth ?>, selYear = <?= $selectedYear ?>, selWeeks = <?= $selectedWeeks ?>;

  // Init AutoNumeric
  new AutoNumeric.multiple('.input-jam',{ decimalPlaces:0, digitGroupSeparator:'' });
  new AutoNumeric('#tarifNominal',{ decimalPlaces:0, digitGroupSeparator:'.', decimalCharacter:',' });

  // Periode
  $('.month-grid button').click(function(){
    $('.month-grid .active').removeClass('active');
    $(this).addClass('active');
    selMonth = $(this).data('month');
  });
  $('#applyPeriod').click(()=>{
    selYear  = $('#selYear').val();
    selWeeks = $('#selWeeks').val();
    window.location = `?filterMonth=${selMonth}&filterYear=${selYear}&filterWeeks=${selWeeks}`;
  });

  // Kumpul data
  function collectData(){
    const fd = new FormData();
    $('.input-jam').each(function(){
      const id  = $(this).closest('.card').data('id');
      const jam = AutoNumeric.getNumber(this)||0;
      fd.append(`jam_extra[${id}]`, jam);
    });
    fd.append('bulan', selMonth);
    fd.append('tahun', selYear);
    fd.append('minggu_ke', selWeeks);
    fd.append('csrf_token', CSRF_TOKEN);
    return fd;
  }

  // SaveDraft
  $('#btnSaveAll').click(()=>{
    let fd = collectData(); fd.append('action','SaveDraft');
    $.ajax({url:api,method:'POST',data:fd,processData:false,contentType:false})
      .done(r=> r.code===0
        ? Toast.fire({icon:'success',title:r.result})
        : Swal.fire('Error',r.result,'error')
      ).fail(()=> Swal.fire('Error','Koneksi gagal','error'));
  });

  // ProcessJam
  $('#btnProcessAll').click(()=>{
  Swal.fire({
    icon: 'warning',
    title: 'Finalisasi Data?',
    html: 'Data yang sudah difinalkan <b>tidak dapat diedit lagi</b> untuk periode ini.<br>Yakin ingin memproses?',
    showCancelButton: true,
    confirmButtonText: 'Finalisasi!',
    cancelButtonText: 'Batal'
  }).then((result)=>{
    if(result.isConfirmed) {
      let fd = collectData(); fd.append('action','FinalizeJam');
      $.ajax({url:api,method:'POST',data:fd,processData:false,contentType:false})
        .done(r=> r.code===0
          ? (Toast.fire({icon:'success',title:r.result}), setTimeout(()=>location.reload(),1200))
          : Swal.fire('Error',r.result,'error')
        ).fail(()=> Swal.fire('Error','Koneksi gagal','error'));
    }
  });
});


  // View/Update Tarif
  $('#modalTarif').on('show.bs.modal',()=>{
    $.post(api,{action:'ViewTarif',csrf_token:CSRF_TOKEN},r=>{
      if(r.code===0) AutoNumeric.getAutoNumericElement('#tarifNominal').set(r.result.nominal);
    },'json');
  });
  $('#formTarif').submit(e=>{
    e.preventDefault();
    const tarif = AutoNumeric.getNumber('#tarifNominal')||0;
    const fd = new FormData();
    fd.append('action','UpdateTarif');
    fd.append('nominal',tarif);
    fd.append('csrf_token',CSRF_TOKEN);
    $.ajax({url:api,method:'POST',data:fd,processData:false,contentType:false})
      .done(r=> r.code===0
        ? (Toast.fire({icon:'success',title:r.result}),$('#modalTarif').modal('hide'))
        : Swal.fire('Error',r.result,'error')
      ).fail(()=> Swal.fire('Error','Koneksi gagal','error'));
  });
});
</script>
</body>
</html>
