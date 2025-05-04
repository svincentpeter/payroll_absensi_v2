<?php
// File: /payroll_absensi_v2/absensi/guru/request_tukar_jadwal.php

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
generate_csrf_token();

// Hanya role P atau TK (atau superadmin via non_admin_mode)
if (!($_SESSION['non_admin_mode'] ?? false)) {
    authorize(['P','TK'], '/login.php');
}

require_once __DIR__ . '/../koneksi.php';
$nip  = $_SESSION['nip']  ?? '';
$nama = $_SESSION['nama'] ?? '';
if (!$nip) die("Session NIP hilang.");

// translate helpers
function tgl_id($fmt, $date) {
    return str_replace(
        ['January','February','March','April','May','June','July','August','September','October','November','December'],
        ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'],
        date($fmt, strtotime($date))
    );
}

// -----------------------------------------------------------------------------
// 1. HANDLE POST: pengajuan baru atau respon accept/reject
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    // RESPOND permintaan masuk
    if (isset($_POST['respond_action'], $_POST['request_id'])) {
        $action     = $_POST['respond_action'];
        $req_id     = (int)$_POST['request_id'];
        // ambil request
        $r = single_row($conn,
            "SELECT * FROM permintaan_tukar_jadwal WHERE id=? AND status='Pending'",
            "i", [$req_id]
        ) ?: [];
        if ($r && $r['nip_tujuan']===$nip) {
            if ($action==='accept') {
                // swap jadwal langsung: tukar nip & nama_guru
                $swapSql = "
                    UPDATE jadwal_piket CASE 
                      WHEN id_jadwal={$r['id_jadwal_pengaju']} THEN SET nip=?, nama_guru=? 
                      WHEN id_jadwal={$r['id_jadwal_tujuan']} THEN SET nip=?, nama_guru=? 
                    END
                    WHERE id_jadwal IN (?,?)
                ";
                // untuk simplicity: dua update terpisah
                $conn->begin_transaction();
                // update pengaju → tujuan
                $stmt = $conn->prepare(
                  "UPDATE jadwal_piket SET nip=?, nama_guru=? WHERE id_jadwal=?"
                );
                $stmt->bind_param("ssi",
                  $r['nip_tujuan'],
                  get_name_by_nip($conn,$r['nip_tujuan']),
                  $r['id_jadwal_pengaju']
                ); $stmt->execute(); $stmt->close();
                // update tujuan → pengaju
                if ($r['id_jadwal_tujuan']) {
                    $stmt = $conn->prepare(
                      "UPDATE jadwal_piket SET nip=?, nama_guru=? WHERE id_jadwal=?"
                    );
                    $stmt->bind_param("ssi",
                      $nip,
                      $nama,
                      $r['id_jadwal_tujuan']
                    ); $stmt->execute(); $stmt->close();
                }
                // set status
                $stmt = $conn->prepare(
                  "UPDATE permintaan_tukar_jadwal SET status='Diterima' WHERE id=?"
                );
                $stmt->bind_param("i",$req_id); $stmt->execute(); $stmt->close();
                $conn->commit();
                $_SESSION['swap_success']="Request #{$req_id} diterima.";
            } else {
                // reject
                $stmt = $conn->prepare(
                  "UPDATE permintaan_tukar_jadwal SET status='Ditolak' WHERE id=?"
                );
                $stmt->bind_param("i",$req_id);
                $stmt->execute(); $stmt->close();
                $_SESSION['swap_success']="Request #{$req_id} ditolak.";
            }
        } else {
            $_SESSION['swap_error']="Request tidak ditemukan atau akses ditolak.";
        }
        header("Location: request_tukar_jadwal.php"); exit;
    }

    // PENGAJUAN baru
    if (isset($_POST['id_jadwal_pengaju'], $_POST['nip_tujuan'])) {
        $id_jp   = (int)$_POST['id_jadwal_pengaju'];
        $nip_to  = trim($_POST['nip_tujuan']);
        // validasi: nip_to ≠ diri sendiri
        if ($nip_to && $nip_to!==$nip) {
            // cari apakah sudah ada permintaan serupa
            $exists = single_row($conn,
                "SELECT COUNT(*) AS cnt FROM permintaan_tukar_jadwal 
                 WHERE id_jadwal_pengaju=? AND nip_tujuan=? AND status='Pending'",
                "is", [$id_jp,$nip_to]
            )['cnt'] ?? 0;
            if (!$exists) {
                // ambil tanggal
                $dt = single_row($conn,
                    "SELECT tanggal FROM jadwal_piket WHERE id_jadwal=?",
                    "i", [$id_jp]
                )['tanggal'] ?? '';
                $stmt = $conn->prepare("
                    INSERT INTO permintaan_tukar_jadwal 
                      (id_jadwal_pengaju, id_jadwal_tujuan, nip_tujuan,
                       status, nip_pengaju, nama_pengaju, tanggal_permintaan, tanggal_piket)
                    VALUES (?, NULL, ?, 'Pending', ?, ?, NOW(), ?)
                ");
                $stmt->bind_param("issss",
                  $id_jp, $nip_to,
                  $nip, $nama,
                  $dt
                );
                if ($stmt->execute()) {
                    $_SESSION['swap_success']="Request dikirim ke {$nip_to}.";
                } else {
                    $_SESSION['swap_error']="Gagal mengirim request.";
                }
                $stmt->close();
            } else {
                $_SESSION['swap_error']="Request sudah diajukan sebelumnya.";
            }
        } else {
            $_SESSION['swap_error']="Data request tidak valid.";
        }
        header("Location: request_tukar_jadwal.php"); exit;
    }
}

// -----------------------------------------------------------------------------
// 2. FETCH DATA untuk tampilan
// -----------------------------------------------------------------------------
// a) jadwal Anda (pending atau upcoming)
$jadwalList = all_rows($conn,
  "SELECT id_jadwal, tanggal, waktu_piket 
   FROM jadwal_piket 
   WHERE nip=? AND status='pending' 
   ORDER BY tanggal ASC",
   "s", [$nip]
);

// b) daftar target guru (exclude diri sendiri)
$guruList = all_rows($conn,
  "SELECT nip, nama 
   FROM anggota_sekolah 
   WHERE nip<>? AND (role IN ('P','TK')) 
   ORDER BY nama ASC",
   "s", [$nip]
);

// c) permintaan masuk
$inRequests = all_rows($conn,
  "SELECT ptj.id, ptj.tanggal_permintaan, ptj.tanggal_piket,
          jp.nama_guru AS pengaju
   FROM permintaan_tukar_jadwal ptj
   JOIN jadwal_piket jp ON ptj.id_jadwal_pengaju=jp.id_jadwal
   WHERE ptj.nip_tujuan=? AND ptj.status='Pending'
   ORDER BY ptj.tanggal_permintaan DESC",
   "s", [$nip]
);

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Request Tukar Jadwal Piket</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">

  <h2 class="mb-4"><i class="fas fa-exchange-alt"></i> Tukar Jadwal Piket</h2>

  <!-- Notifikasi -->
  <?php if(isset($_SESSION['swap_success'])): ?>
    <div class="alert alert-success"><?= $_SESSION['swap_success'] ?></div>
  <?php unset($_SESSION['swap_success']); endif;?>
  <?php if(isset($_SESSION['swap_error'])): ?>
    <div class="alert alert-danger"><?= $_SESSION['swap_error'] ?></div>
  <?php unset($_SESSION['swap_error']); endif;?>

  <!-- a) Panel: Pengajuan Baru -->
  <div class="card mb-4 shadow-sm">
    <div class="card-header bg-primary text-white">
      <i class="fas fa-plus-circle"></i> Pengajuan Baru
    </div>
    <div class="card-body">
      <?php if(empty($jadwalList)): ?>
        <p class="text-muted">Anda belum memiliki jadwal piket pending.</p>
      <?php else: ?>
        <div class="row gy-2">
          <?php foreach($jadwalList as $j): ?>
            <div class="col-md-4">
              <div class="card h-100">
                <div class="card-body">
                  <h6><?= tgl_id('d F Y', $j['tanggal']) ?></h6>
                  <p class="mb-1"><small>Jam: <?= htmlspecialchars($j['waktu_piket']) ?></small></p>
                  <button 
                    class="btn btn-sm btn-outline-primary openModal" 
                    data-id="<?= $j['id_jadwal'] ?>"
                    >Tukar</button>
                </div>
              </div>
            </div>
          <?php endforeach;?>
        </div>
      <?php endif;?>
    </div>
  </div>

  <!-- b) Panel: Request Masuk -->
  <div class="card shadow-sm">
    <div class="card-header bg-secondary text-white">
      <i class="fas fa-inbox"></i> Request Masuk
    </div>
    <div class="card-body p-0">
      <table class="table table-striped mb-0">
        <thead>
          <tr>
            <th>#</th><th>Pengaju</th><th>Tgl Piket</th><th>Waktu Req</th><th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($inRequests)): ?>
            <tr><td colspan="5" class="text-center py-3">Tidak ada request.</td></tr>
          <?php else: ?>
            <?php foreach($inRequests as $i=>$r): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><?= htmlspecialchars($r['pengaju']) ?></td>
              <td><?= tgl_id('d F Y', $r['tanggal_piket']) ?></td>
              <td><?= tgl_id('d F Y H:i', $r['tanggal_permintaan']) ?></td>
              <td>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                  <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                  <button name="respond_action" value="accept" class="btn btn-sm btn-success">Terima</button>
                </form>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                  <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                  <button name="respond_action" value="reject" class="btn btn-sm btn-danger">Tolak</button>
                </form>
              </td>
            </tr>
            <?php endforeach;?>
          <?php endif;?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Modal Tukar -->
<div class="modal fade" id="modalTukar" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <input type="hidden" name="id_jadwal_pengaju" id="modal_jadwal_id">
      <div class="modal-header">
        <h5 class="modal-title">Pilih Guru Tujuan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <select name="nip_tujuan" class="form-select" required>
          <option value="">-- Pilih Guru --</option>
          <?php foreach($guruList as $g): ?>
            <option value="<?= htmlspecialchars($g['nip']) ?>">
              <?= htmlspecialchars($g['nama']) ?> (<?= $g['nip'] ?>)
            </option>
          <?php endforeach;?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary" name="tukar_jadwal">Kirim Request</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.querySelectorAll('.openModal').forEach(btn=>{
    btn.addEventListener('click',()=>{
      document.getElementById('modal_jadwal_id').value = btn.dataset.id;
      new bootstrap.Modal(document.getElementById('modalTukar')).show();
    });
  });
</script>
</body>
</html>
