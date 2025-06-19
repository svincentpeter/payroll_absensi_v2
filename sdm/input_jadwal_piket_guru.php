<?php
// File: /payroll_absensi_v2/sdm/input_jadwal_piket_guru.php

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:SDM']);
require_once __DIR__ . '/../koneksi.php';
if (ob_get_length()) ob_end_clean();

// CSRF token
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

// ambil semua jenjang terurut
$jenjangAll = getOrderedJenjang($conn); 

// ambil data guru (nip,nama,jenjang)
$guruData = [];
$res = $conn->query("SELECT nip,nama,jenjang FROM anggota_sekolah ORDER BY nama ASC");
while ($r = $res->fetch_assoc()) {
    $guruData[$r['nip']] = [
        'nip'     => $r['nip'],
        'nama'    => $r['nama'],
        'jenjang' => $r['jenjang'],
    ];
}

// preload semua jadwal yang sudah tersimpan untuk cek konflik
$existing = [];
$res2 = $conn->query("SELECT nip, tanggal FROM jadwal_piket");
while ($r2 = $res2->fetch_assoc()) {
    $existing[$r2['nip']][] = $r2['tanggal'];
}

// =====================================================================
// PROSES PENYIMPANAN DATA
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_schedule'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['error_message'] = "Token CSRF tidak valid.";
        header("Location: input_jadwal_piket_guru.php");
        exit();
    }

    $schedule_type     = sanitize_input($_POST['schedule_type'] ?? '');
    $jumlah_guru       = intval(sanitize_input($_POST['jumlah_guru'] ?? '0'));
    $selected_guru_raw = $_POST['guru'] ?? [];
    $selected_guru     = array_map('sanitize_input', $selected_guru_raw);

    // validasi dasar & duplikat
    $error = '';
    if (!in_array($schedule_type, ['1','2'], true)) {
        $error = "Pilih tipe jadwal valid.";
    } elseif ($jumlah_guru < 1 || $jumlah_guru > 5) {
        $error = "Jumlah guru harus antara 1–5.";
    } elseif (count($selected_guru) !== $jumlah_guru) {
        $error = "Pastikan memilih tepat {$jumlah_guru} guru.";
    } elseif (count($selected_guru) !== count(array_unique($selected_guru))) {
        $error = "Guru tidak boleh dipilih lebih dari satu kali.";
    }

    // ambil & validasi tanggal
    $dates = [];
    if (!$error) {
        $raw = [];
        if ($schedule_type === '1') {
            $raw = array_merge(
                explode(',', $_POST['tanggal_juni'] ?? ''),
                explode(',', $_POST['tanggal_juli'] ?? '')
            );
        } else {
            $raw = array_merge(
                explode(',', $_POST['tanggal_desember'] ?? ''),
                explode(',', $_POST['tanggal_januari'] ?? '')
            );
        }
        foreach ($raw as $d) {
            $d = trim($d);
            if ($d !== '') {
                $dates[] = sanitize_input($d);
            }
        }
        if (empty($dates)) {
            $error = $schedule_type==='1'
                ? "Pilih minimal satu tanggal untuk Juni/Juli."
                : "Pilih minimal satu tanggal untuk Desember/Januari.";
        }
    }

    if ($error) {
        $_SESSION['error_message'] = $error;
        header("Location: input_jadwal_piket_guru.php");
        exit();
    }

    $waktu_piket = "08:00 - 13:00";
    try {
        $conn->begin_transaction();
        foreach ($dates as $tgl) {
            // Validasi format tanggal
            $dt = DateTime::createFromFormat('Y-m-d', $tgl);
            if (!$dt || $dt->format('Y-m-d') !== $tgl) {
                throw new Exception("Format tanggal tidak valid: {$tgl}");
            }
            $m = (int)$dt->format('n');
            // validasi bulan sesuai schedule_type
            if ($schedule_type==='1' && !in_array($m, [6,7], true))
                throw new Exception("Tanggal {$tgl} bukan di bulan Juni/Juli.");
            if ($schedule_type==='2' && !in_array($m, [12,1], true))
                throw new Exception("Tanggal {$tgl} bukan di bulan Desember/Januari.");

            $month_name = getIndonesianMonthName($m);
            $year       = (int)$dt->format('Y');

            foreach ($selected_guru as $nip) {
                if (!isset($guruData[$nip])) {
                    throw new Exception("Data guru dengan NIP {$nip} tidak ditemukan.");
                }
                // cek duplikat entry
                $stmtC = $conn->prepare(
                    "SELECT COUNT(*) AS cnt 
                       FROM jadwal_piket 
                      WHERE nip=? AND tanggal=? AND waktu_piket=?"
                );
                $stmtC->bind_param("sss", $nip, $tgl, $waktu_piket);
                $stmtC->execute();
                $cnt = $stmtC->get_result()->fetch_assoc()['cnt'];
                $stmtC->close();
                if ($cnt) continue;
                // insert
                $nama = $guruData[$nip]['nama'];
                $jenj = $guruData[$nip]['jenjang'];
                $stmtI = $conn->prepare(
                    "INSERT INTO jadwal_piket 
                     (nip, nama_guru, jenjang, waktu_piket, tanggal, bulan, tahun, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')"
                );
                $stmtI->bind_param(
                    "ssssssi",
                    $nip, $nama, $jenj, $waktu_piket,
                    $tgl, $month_name, $year
                );
                $stmtI->execute();
                $stmtI->close();
            }
        }
        $conn->commit();
        $_SESSION['success_message'] = "Jadwal berhasil disimpan.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: input_jadwal_piket_guru.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Input Jadwal Piket Guru (SDM)</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <!-- CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css" rel="stylesheet">
  <style>
  body {
    background: linear-gradient(to right, #eef2f7, #e3ecf9);
    font-family: 'Segoe UI', sans-serif;
    color: #2c3e50;
    font-size: 15px;
  }

  h2 {
    font-weight: 700;
    color: #1565c0;
    border-left: 6px solid #1565c0;
    padding-left: 1rem;
    margin-bottom: 1.5rem;
  }

  .card {
    border-radius: 1rem;
    background: #fff;
    border: none;
    box-shadow: 0 5px 15px rgba(0,0,0,0.06);
  }

  .card-header {
    background: linear-gradient(135deg, #1976d2, #0d47a1);
    color: #fff;
    font-weight: 600;
    padding: 0.75rem 1.25rem;
    border-top-left-radius: 1rem;
    border-top-right-radius: 1rem;
  }

  .btn-schedule .btn,
  .jenjang-toggle .btn {
    border-radius: 0.5rem;
    font-weight: 500;
    padding: 0.4rem 1rem;
  }

  .btn-outline-primary {
    border-color: #1565c0;
    color: #1565c0;
    transition: all 0.2s ease-in-out;
  }

  .btn-outline-primary:hover,
  .btn-check:checked + .btn-outline-primary {
    background-color: #1565c0;
    color: #fff;
  }

  .btn-outline-secondary {
    color: #555;
    border-color: #bbb;
    transition: all 0.2s ease-in-out;
  }

  .btn-outline-secondary:hover,
  .btn-check:checked + .btn-outline-secondary {
    background-color: #42a5f5;
    border-color: #42a5f5;
    color: #fff;
  }

  .form-select, .form-control {
    border-radius: 0.5rem;
    border: 1px solid #ccc;
    padding: 0.5rem 0.75rem;
    background-color: #fff;
    transition: border-color 0.2s ease-in-out;
  }

  .form-label {
    font-weight: 600;
    color: #333;
  }

  .input-group .form-control {
    border-right: none;
  }

  .input-group-text {
    background: #f0f0f0;
    border-left: none;
  }

  .btn-success {
    background-color: #2e7d32;
    border: none;
    transition: background 0.2s ease;
  }

  .btn-success:hover {
    background-color: #1b5e20;
  }

  #btnSave.loading {
    opacity: 0.7;
    pointer-events: none;
  }

  #btnSave.loading::after {
    content: ' ⏳';
    animation: spin 1s linear infinite;
  }

  @keyframes spin {
    to { transform: rotate(360deg); }
  }

  .alert {
    border-radius: 0.5rem;
    font-weight: 500;
    padding: 0.75rem 1rem;
  }

  .alert-success {
    background: #e8f5e9;
    color: #388e3c;
    border: 1px solid #c8e6c9;
  }

  .alert-danger {
    background: #ffebee;
    color: #c62828;
    border: 1px solid #f5c6cb;
  }

  #overlay {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(255, 255, 255, 0.85);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #1976d2;
  }

  #guru_container .card {
    border: 1px solid #e0e0e0;
    border-radius: 0.75rem;
    margin-bottom: 1rem;
  }

  #guru_container .card-header {
    background: #f5f5f5;
    color: #333;
    font-weight: 500;
    border-radius: 0.75rem 0.75rem 0 0;
  }

  .count-info {
    font-size: 0.875rem;
    color: #888;
    display: block;
    margin-top: 0.25rem;
  }

  #guru_container .card {
  transition: transform 0.2s ease, opacity 0.3s ease;
}
#guru_container .card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0,0,0,0.1);
}

</style>

  <script>
    // kirim data ke JS
    window.GURU_DATA        = <?= json_encode($guruData) ?>;
    window.JENJANG_ALL      = <?= json_encode($jenjangAll) ?>;
    window.EXISTING_SCHEDULE = <?= json_encode($existing) ?>;
  </script>
</head>
<body>
<div class="container py-4">
  <button id="btnBack" class="btn btn-light mb-4 shadow-sm" data-href="/payroll_absensi_v2/sdm/laporan_jadwal_piket.php">
    <i class="fas fa-arrow-left"></i> Kembali
  </button>
  <h2 class="mb-4"><i class="fas fa-calendar-plus text-primary"></i> Input Jadwal Piket Guru</h2>

  <div class="card shadow-sm mb-5">
    <div class="card-body">
      <!-- Alerts -->
      <?php if(isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
          <?= htmlspecialchars($_SESSION['success_message']) ?>
          <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php unset($_SESSION['success_message']); endif; ?>
      <?php if(isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
          <?= htmlspecialchars($_SESSION['error_message']) ?>
          <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php unset($_SESSION['error_message']); endif; ?>

      <form id="frm" method="POST" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="submit_schedule" value="1">

        <!-- Tipe Jadwal -->
        <div class="mb-4">
          <label class="form-label fw-semibold">Tipe Jadwal</label>
          <div class="btn-group btn-schedule" role="group">
            <input type="radio" class="btn-check" name="schedule_type" id="sch1" value="1" required>
            <label class="btn btn-outline-primary" for="sch1">Juni–Juli</label>
            <input type="radio" class="btn-check" name="schedule_type" id="sch2" value="2">
            <label class="btn btn-outline-primary" for="sch2">Desember–Januari</label>
          </div>
          <div class="invalid-feedback">Pilih tipe jadwal.</div>
        </div>

        <!-- Jumlah Guru -->
        <div class="mb-4">
          <label class="form-label fw-semibold">Jumlah Guru</label>
          <select name="jumlah_guru" id="jumlah_guru" class="form-select" required>
            <option value="">-- Pilih Jumlah --</option>
            <?php for($i=1;$i<=5;$i++): ?>
              <option value="<?=$i?>"><?=$i?></option>
            <?php endfor; ?>
          </select>
          <div class="invalid-feedback">Pilih jumlah guru.</div>
        </div>

        <!-- Filter Jenjang -->
        <div class="mb-4">
          <label class="form-label fw-semibold">Filter Jenjang</label><br>
          <div class="jenjang-toggle">
            <?php foreach($jenjangAll as $j): ?>
              <input type="checkbox" class="btn-check" id="j-<?= $j ?>" value="<?= $j ?>">
              <label class="btn btn-outline-secondary" for="j-<?= $j ?>"><?= $j ?></label>
            <?php endforeach; ?>
          </div>
          <small class="text-muted">Kosong = semua jenjang</small>
        </div>

        <!-- Tanggal Juni/Juli -->
        <div id="field_jadwal_1" class="row g-3 mb-4" style="display:none;">
          <?php $months1=['Juni'=>'tanggal_juni','Juli'=>'tanggal_juli'];
          foreach($months1 as $lab=>$name): ?>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Tanggal <?= $lab ?></label>
              <div class="input-group">
                <input type="text" class="form-control datepicker" name="<?= $name ?>" placeholder="yyyy-mm-dd,..." autocomplete="off">
                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
              </div>
              <small class="text-muted count-info"></small>
            </div>
          <?php endforeach;?>
        </div>

        <!-- Tanggal Desember/Januari -->
        <div id="field_jadwal_2" class="row g-3 mb-4" style="display:none;">
          <?php $months2=['Desember'=>'tanggal_desember','Januari'=>'tanggal_januari'];
          foreach($months2 as $lab=>$name): ?>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Tanggal <?= $lab ?></label>
              <div class="input-group">
                <input type="text" class="form-control datepicker" name="<?= $name ?>" placeholder="yyyy-mm-dd,..." autocomplete="off">
                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
              </div>
              <small class="text-muted count-info"></small>
            </div>
          <?php endforeach;?>
        </div>

        <!-- Reset & Simpan -->
        <div class="d-flex justify-content-between mb-2">
          <button type="button" id="btnReset" class="btn btn-outline-secondary">Reset Form</button>
          <button type="button" id="btnSave" class="btn btn-success px-4">
            <i class="fas fa-save me-2"></i> Simpan Jadwal
          </button>
        </div>

        <!-- Dynamic Guru Cards -->
        <div id="guru_container" class="mb-4"></div>
      </form>
    </div>
  </div>
</div>

<!-- overlay -->
<div id="overlay" style="display:none">⏳ Menyimpan…</div>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/js/bootstrap-datepicker.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function(){
  // init datepicker
  function initPickers(viewDate) {
    $('.datepicker').datepicker('destroy').datepicker({
      format: 'yyyy-mm-dd',
      multidate: true,
      todayHighlight: true,
      defaultViewDate: viewDate
    });
  }
  const now = new Date();
  initPickers({year:now.getFullYear(), month:now.getMonth(), day:1});

  // update count tanggal
  $(document).on('change', '.datepicker', function(){
    const cnt = $(this).val()
      .split(',')
      .filter(d=>d.trim()!=='').length;
    $(this).closest('.col-md-6').find('.count-info')
      .text(`Total tanggal: ${cnt}`);
    disableSelectedOptions();
  });

  // toggle fields by tipe
  $('input[name="schedule_type"]').change(function(){
    $('#field_jadwal_1').toggle(this.value==='1');
    $('#field_jadwal_2').toggle(this.value==='2');
    const viewDate = this.value==='1'
      ? {year:now.getFullYear(),month:5,day:1}
      : {year:now.getFullYear(),month:11,day:1};
    initPickers(viewDate);
  });

  // build opsi guru
  function buildOpts(){
    const jenjangS = $('.jenjang-toggle input:checked')
      .map((_,c)=>c.value).get();
    let html = '<option value="">-- Pilih Guru --</option>';
    Object.values(window.GURU_DATA).forEach(g=>{
      if (!jenjangS.length||jenjangS.includes(g.jenjang)){
        html += `<option value="${g.nip}">${g.nama} (${g.nip})</option>`;
      }
    });
    return html;
  }

  // render guru selects
  function renderGuru(){
    const cnt = parseInt($('#jumlah_guru').val())||0;
    const ctn = $('#guru_container').empty();
    for(let i=1;i<=cnt;i++){
      const card = $(`
        <div class="card mb-3">
          <div class="card-header">
            <i class="fas fa-user"></i> Guru ${i}
          </div>
          <div class="card-body">
            <select name="guru[]" class="form-select" required></select>
            <div class="invalid-feedback">Pilih guru ${i}.</div>
          </div>
        </div>`);
      card.find('select').html(buildOpts());
      ctn.append(card);
    }
    disableSelectedOptions();
  }
  $('#jumlah_guru, .jenjang-toggle input').change(renderGuru);
  renderGuru();

  // disable opsi duplikat atau konflik
  function disableSelectedOptions(){
    // kumpulkan NIP yang sudah dipilih di dropdown
    const picked = $('select[name="guru[]"]').map((_,el)=>el.value).get();
    // kumpulkan semua tanggal terpilih
    const dates = $('.datepicker').map((_,el)=>
      el.value.split(',').map(d=>d.trim())
    ).get().flat().filter(d=>d);
    $('select[name="guru[]"]').each(function(){
      const curr = this.value;
      $(this).find('option').each(function(){
        const nip = this.value;
        // cek duplikat
        let disable = nip && picked.includes(nip) && nip!==curr;
        // cek konflik existing schedule
        if (!disable && nip && window.EXISTING_SCHEDULE[nip]) {
          const conflict = window.EXISTING_SCHEDULE[nip]
            .filter(d=>dates.includes(d));
          if (conflict.length && nip!==curr) {
            disable = true;
            $(this).attr('title',
              `Sudah terjadwal pada: ${conflict.join(', ')}`);
          }
        }
        $(this).prop('disabled', disable);
      });
    });
  }
  $(document).on('change','select[name="guru[]"]', disableSelectedOptions);

  // reset form
  $('#btnReset').click(function(){
    $('#frm')[0].reset();
    $('#guru_container').empty();
    $('.count-info').text('');
    $('.needs-validation').removeClass('was-validated');
  });

  // back
  $('#btnBack').click(()=> window.location = $('#btnBack').data('href'));

  // simpan & konfirmasi
  $('#btnSave').click(function(){
    const form = document.getElementById('frm');
    if (!form.checkValidity()) {
      form.classList.add('was-validated');
      form.querySelector(':invalid')?.scrollIntoView({behavior:'smooth', block:'center'});
      return;
    }
    // cek minimal satu tanggal
    if (!$('.datepicker').map((_,el)=>el.value).get().join(',').trim()) {
      Swal.fire('Tanggal Kosong','Pilih minimal satu tanggal.','warning');
      return;
    }
    // build tabel konfirmasi
    let rows = '';
    $('select[name="guru[]"]').each((i,sel)=>{
      const txt = sel.selectedOptions[0]?.text || '';
      const colTgl = $('.datepicker').map((_,el)=>el.value)
        .get().filter(v=>v).join('<br>');
      rows += `<tr><td>${txt}</td><td>${colTgl}</td></tr>`;
    });
    const html = `
      <table class="table table-sm">
        <thead><tr><th>Guru</th><th>Tanggal</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>`;
    Swal.fire({
      title: 'Konfirmasi Jadwal',
      html,
      icon: 'info',
      showCancelButton: true,
      confirmButtonText: 'Ya, simpan',
      width: 600
    }).then(r=>{
      if (r.isConfirmed) {
        $('#overlay').show();
        $('#btnSave').addClass('loading');
        form.submit();
      }
    });
  });
});
</script>
</body>
</html>