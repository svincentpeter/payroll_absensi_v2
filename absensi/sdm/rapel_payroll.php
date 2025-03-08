<?php
// File: /payroll_absensi_v2/absensi/sdm/rapel_payroll.php

require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();

generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

require_once __DIR__ . '/../../koneksi.php';
// Pastikan hanya SDM / Superadmin yang boleh akses
authorize(['sdm','superadmin'], '/payroll_absensi_v2/login.php');

// 1. Ambil parameter bulan/tahun terpilih
$selectedMonth = isset($_GET['filterMonth']) ? intval($_GET['filterMonth']) : date('n');
$selectedYear  = isset($_GET['filterYear'])  ? intval($_GET['filterYear'])  : date('Y');

// 2. Ambil data "bulan/tahun yang punya data rapel" untuk highlight
$rapelMonths = [];
$sqlRapel = "SELECT bulan, tahun, COUNT(*) as total_rapel
             FROM rapel_payroll
             GROUP BY bulan, tahun
             ORDER BY tahun, bulan";
$resRapel = $conn->query($sqlRapel);
if ($resRapel) {
    while ($row = $resRapel->fetch_assoc()) {
        if (intval($row['total_rapel']) > 0) {
            $rapelMonths[] = [
                'bulan' => intval($row['bulan']),
                'tahun' => intval($row['tahun']),
            ];
        }
    }
}

// 3. Ambil data rapel untuk $selectedMonth & $selectedYear
$stmtData = $conn->prepare("
    SELECT rp.*, a.nama, a.nip
    FROM rapel_payroll rp
    JOIN anggota_sekolah a ON rp.id_anggota = a.id
    WHERE rp.bulan = ? AND rp.tahun = ?
    ORDER BY rp.created_at DESC
");
$stmtData->bind_param("ii", $selectedMonth, $selectedYear);
$stmtData->execute();
$resultData = $stmtData->get_result();
$rapelList = [];
while($r = $resultData->fetch_assoc()){
    $rapelList[] = $r;
}
$stmtData->close();

/* ===========================================================
   BAGIAN: HANDLER AJAX CRUD RAPEL
   =========================================================== */
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    // Pastikan request method POST & CSRF
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_response(405, 'Metode Permintaan Tidak Diizinkan (harus POST).');
    }
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $case = trim($_POST['case'] ?? '');
    switch($case) {
        case 'EditRapel':
            EditRapel($conn);
            break;
        case 'ProcessRapel':
            ProcessRapel($conn);
            break;
        default:
            send_response(1, 'Kasus tidak valid.');
    }
    exit();
}

// ---------------------------------------------------
// FUNGSI-FUNGSI CRUD
// ---------------------------------------------------
function EditRapel($conn) {
    // Contoh: ubah status => 'revisi' & ubah nilai_rapel / keterangan
    $id_rapel   = intval($_POST['id_rapel'] ?? 0);
    $nilai_rapel= floatval($_POST['nilai_rapel'] ?? 0);
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($id_rapel <= 0) {
        send_response(1, 'ID rapel tidak valid.');
    }
    if ($nilai_rapel < 0) {
        send_response(1, 'Nilai rapel tidak boleh negatif.');
    }

    $stmt = $conn->prepare("UPDATE rapel_payroll
                            SET nilai_rapel = ?, keterangan = ?, status = 'revisi', updated_at = NOW()
                            WHERE id = ?");
    if (!$stmt) {
        send_response(1, 'Prepare statement gagal: '.$conn->error);
    }
    $stmt->bind_param("dsi", $nilai_rapel, $keterangan, $id_rapel);
    if ($stmt->execute()) {
        $stmt->close();
        send_response(0, 'Rapel berhasil diubah menjadi revisi.');
    } else {
        $stmt->close();
        send_response(1, 'Gagal update rapel: '.$stmt->error);
    }
}

function ProcessRapel($conn) {
    // Contoh: ubah status => 'final'
    // Di sinilah Anda bisa menambahkan logika integrasi ke payroll.
    $id_rapel = intval($_POST['id_rapel'] ?? 0);
    if ($id_rapel <= 0) {
        send_response(1, 'ID rapel tidak valid.');
    }

    // Opsional: cek apakah payroll user di bulan ini sudah final,
    //           kalau belum => boleh proses, dsb.
    // ...

    $stmt = $conn->prepare("UPDATE rapel_payroll
                            SET status = 'final', updated_at = NOW()
                            WHERE id = ?");
    if (!$stmt) {
        send_response(1, 'Prepare statement gagal: '.$conn->error);
    }
    $stmt->bind_param("i", $id_rapel);
    if ($stmt->execute()) {
        $stmt->close();

        // TODO: Integrasi ke payroll (misalnya menambahkan rapel ke gaji user)
        // Contoh pseudo:
        // $rowRapel = ... (SELECT rapel, id_anggota, bulan, tahun)
        // lalu update payroll atau insert payhead dsb.

        send_response(0, 'Rapel berhasil diproses (status final).');
    } else {
        $stmt->close();
        send_response(1, 'Gagal memproses rapel: '.$stmt->error);
    }
}

// Fungsi kecil untuk respon JSON
function send_response($code, $message) {
    http_response_code(200);
    echo json_encode([
        'code' => $code,
        'result' => $message
    ]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rapel Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- CSS Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- SB Admin 2 (opsional jika ingin gaya seragam) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
      .rapel-month {
        background-color: #343a40 !important; /* abu gelap */
        color: #fff !important;
        pointer-events: none; /* agar tidak bisa diklik */
        border: 1px solid #343a40;
      }
      .selected-month {
        background-color: #ffc107 !important; 
        color: #000 !important;
        font-weight: bold;
      }
    </style>
</head>
<body>

<div class="container my-4">
    <h1 class="mb-4">Manajemen Rapel Payroll</h1>

    <!-- Bagian menampilkan bulan rapel terpilih -->
    <div class="card mb-3">
        <div class="card-body d-flex align-items-center">
            <i class="bi bi-calendar3 me-2"></i>
            <span class="fw-bold">
                Rapel Bulan: <?= date('F', mktime(0,0,0, $selectedMonth,1)) . ' ' . $selectedYear; ?>
            </span>
            <button id="btnSelectMonth" class="btn btn-link ms-auto">
                <i class="bi bi-pencil-square"></i> Ganti Bulan
            </button>
        </div>
    </div>

    <!-- Daftar Data Rapel (sesuai $selectedMonth & $selectedYear) -->
    <div class="card mb-4">
        <div class="card-header">
            Data Rapel untuk Periode <?= date('F Y', mktime(0,0,0,$selectedMonth,1,$selectedYear)); ?>
        </div>
        <div class="card-body">
          <?php if(count($rapelList) > 0): ?>
            <table class="table table-bordered align-middle">
              <thead class="table-light">
                <tr>
                  <th>No</th>
                  <th>NIP</th>
                  <th>Nama</th>
                  <th>Nilai Rapel</th>
                  <th>Status</th>
                  <th>Keterangan</th>
                  <th>Dibuat Pada</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($rapelList as $idx => $row): ?>
                  <tr>
                    <td><?= $idx+1; ?></td>
                    <td><?= htmlspecialchars($row['nip']); ?></td>
                    <td><?= htmlspecialchars($row['nama']); ?></td>
                    <td><?= number_format($row['nilai_rapel'],2,',','.'); ?></td>
                    <td>
                      <?php
                        $badgeClass = 'badge bg-secondary';
                        if ($row['status'] == 'draft')  $badgeClass = 'badge bg-info';
                        if ($row['status'] == 'revisi') $badgeClass = 'badge bg-warning text-dark';
                        if ($row['status'] == 'final')  $badgeClass = 'badge bg-success';
                      ?>
                      <span class="<?= $badgeClass; ?>">
                        <?= htmlspecialchars($row['status']); ?>
                      </span>
                    </td>
                    <td><?= htmlspecialchars($row['keterangan']); ?></td>
                    <td><?= $row['created_at']; ?></td>
                    <td>
                      <?php if($row['status'] != 'final'): ?>
                        <button class="btn btn-sm btn-warning btnEditRapel"
                                data-id="<?= $row['id']; ?>"
                                data-nilai="<?= $row['nilai_rapel']; ?>"
                                data-ket="<?= htmlspecialchars($row['keterangan']); ?>">
                          <i class="bi bi-pencil-square"></i> Edit
                        </button>
                        <button class="btn btn-sm btn-success btnProcessRapel"
                                data-id="<?= $row['id']; ?>">
                          <i class="bi bi-check-circle"></i> Proses
                        </button>
                      <?php else: ?>
                        <span class="text-muted">-</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="alert alert-secondary">
              Belum ada data rapel untuk periode ini.
            </div>
          <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL: Select Month (Rapel) -->
<div class="modal fade" id="selectMonthModal" tabindex="-1" aria-labelledby="selectMonthModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" style="max-width:600px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="selectMonthModalLabel">
                    <i class="bi bi-calendar3"></i> Pilih Bulan untuk Rapel
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="row text-center">
                <?php
                // Buat range 16 bulan ke depan/ke belakang (silakan sesuaikan)
                $currentYear  = date('Y');
                $currentMonth = date('n');
                $startMonth   = $currentMonth - 2;
                $startYear    = $currentYear;
                for($i=0; $i<16; $i++){
                    $month = $startMonth + $i;
                    $year  = $startYear;
                    if($month <= 0){
                        $month += 12;
                        $year -= 1;
                    } elseif($month > 12){
                        $month -= 12;
                        $year += 1;
                    }

                    // Default class
                    $boxClass = 'bg-light';
                    
                    // Apakah bulan/tahun ini punya rapel data?
                    foreach($rapelMonths as $rm){
                        if($rm['bulan'] == $month && $rm['tahun'] == $year){
                            // Beri highlight "rapel-month"
                            $boxClass = 'rapel-month';
                            break;
                        }
                    }

                    // Apakah ini bulan/tahun yang sedang dipilih?
                    if($month == $selectedMonth && $year == $selectedYear){
                        $boxClass = 'selected-month';
                    }

                    echo '<div class="col-3 mb-3">';
                    echo '  <div class="p-2 '.$boxClass.'" style="border:1px solid #ddd; border-radius:5px;">';
                    echo '    <a href="#" class="month-link" data-month-number="'.$month.'" data-month="'.date('F', mktime(0,0,0,$month,1)).'" data-year="'.$year.'" style="color:inherit; text-decoration:none;">';
                    echo '      '.strtoupper(date('F', mktime(0,0,0,$month,1))).'<br>'.$year;
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

<!-- MODAL: Edit Rapel -->
<div class="modal fade" id="editRapelModal" tabindex="-1" aria-labelledby="editRapelModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form id="formEditRapel" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editRapelModalLabel">
          <i class="bi bi-pencil-square"></i> Edit Rapel (Status: revisi)
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="case" value="EditRapel">
        <input type="hidden" name="id_rapel" id="id_rapel_edit">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
        
        <div class="mb-3">
          <label for="nilai_rapel_edit" class="form-label">Nilai Rapel</label>
          <input type="number" step="0.01" class="form-control" name="nilai_rapel" id="nilai_rapel_edit" required>
        </div>
        <div class="mb-3">
          <label for="keterangan_edit" class="form-label">Keterangan</label>
          <textarea class="form-control" name="keterangan" id="keterangan_edit" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save"></i> Simpan Perubahan
          <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle"></i> Batal
        </button>
      </div>
    </form>
  </div>
</div>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function(){
    // Saat tombol "Ganti Bulan" ditekan, tampilkan modal
    $("#btnSelectMonth").on("click", function(){
        $("#selectMonthModal").modal("show");
    });

    // Klik di salah satu month-link => ganti filterMonth & filterYear
    $(document).on("click", ".month-link", function(e){
        e.preventDefault();
        var monthNumber = $(this).data('month-number');
        var monthName   = $(this).data('month');
        var year        = $(this).data('year');

        // Simpan ke localStorage (opsional)
        localStorage.setItem('selectedRapelMonthName', monthName);
        localStorage.setItem('selectedRapelMonthNumber', monthNumber);
        localStorage.setItem('selectedRapelYear', year);

        // Lakukan redirect / reload dengan query string
        var newUrl = "rapel_payroll.php?filterMonth=" + monthNumber + "&filterYear=" + year;
        window.location.href = newUrl;
    });

    // Klik tombol Edit Rapel => tampilkan modal + isi data
    $(document).on('click', '.btnEditRapel', function(){
        var idRapel   = $(this).data('id');
        var nilaiRapel= $(this).data('nilai');
        var ket       = $(this).data('ket');

        $("#id_rapel_edit").val(idRapel);
        $("#nilai_rapel_edit").val(nilaiRapel);
        $("#keterangan_edit").val(ket);

        $("#editRapelModal").modal("show");
    });

    // Submit formEditRapel => AJAX panggil EditRapel
    $("#formEditRapel").on('submit', function(e){
        e.preventDefault();
        var form = $(this);
        $.ajax({
            url: "rapel_payroll.php?ajax=1",
            type: "POST",
            data: form.serialize(),
            dataType: "json",
            beforeSend: function(){
                form.find('button[type="submit"]').prop('disabled', true);
                form.find('.spinner-border').removeClass('d-none');
            },
            success: function(resp){
                form.find('button[type="submit"]').prop('disabled', false);
                form.find('.spinner-border').addClass('d-none');

                if(resp.code === 0){
                    alert(resp.result);
                    location.reload();
                } else {
                    alert("Gagal: " + resp.result);
                }
            },
            error: function(xhr, status, error){
                form.find('button[type="submit"]').prop('disabled', false);
                form.find('.spinner-border').addClass('d-none');
                alert("Terjadi kesalahan: " + error);
            }
        });
    });

    // Klik tombol Proses Rapel => AJAX panggil ProcessRapel
    $(document).on('click', '.btnProcessRapel', function(){
        if(!confirm("Yakin memproses rapel ini menjadi final?")) return;

        var idRapel = $(this).data('id');
        $.ajax({
            url: "rapel_payroll.php?ajax=1",
            type: "POST",
            dataType: "json",
            data: {
                case: "ProcessRapel",
                id_rapel: idRapel,
                csrf_token: "<?= htmlspecialchars($csrf_token); ?>"
            },
            success: function(resp){
                if(resp.code === 0){
                    alert(resp.result);
                    location.reload();
                } else {
                    alert("Gagal: " + resp.result);
                }
            },
            error: function(xhr, status, error){
                alert("Terjadi kesalahan: " + error);
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
