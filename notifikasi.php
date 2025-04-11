<?php
/* =========================================================
 *  NOTIFIKASI  —  Persisten (N‑1 … N‑12)
 * ========================================================= */
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/koneksi.php';

start_session_safe();
generate_csrf_token();

/* ---------- ENDPOINTS ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST') {

    /* mark manual notif as read (N‑12) */
    if ($_POST['action']??'' === 'markRead') {
        $id = intval($_POST['notifId']??0);
        if ($id<=0) send_response(1,'Bad ID');
        $stmt=$conn->prepare("UPDATE notifications SET is_read=1 WHERE id=?");
        $stmt->bind_param('i',$id);
        $ok=$stmt->execute();
        $stmt->close();
        $ok?send_response(0,'OK'):send_response(1,$conn->error);
    }

    /* dismiss backup alert (N‑10) */
    if (isset($_POST['dismissed'])) {
        $_SESSION['backup_alert_dismissed']=true;
        send_response(0,'Dismissed');
    }
}

/* ---------- UTIL ---------- */
function qCount(mysqli $c,string $sql,string $types='',array $p=[]):int{
    $st=$c->prepare($sql); if(!$st){error_log($c->error);return 0;}
    if($types) $st->bind_param($types,...$p);
    $st->execute(); $r=$st->get_result()->fetch_assoc(); $st->close();
    return intval($r['cnt']??0);
}

/* =========================================================
 *  CORE: collectNotifications()
 * ========================================================= */
function collectNotifications(mysqli $conn):array{

    $uid      = $_SESSION['user_id']??0;
    $nip      = $_SESSION['nip']??'';
    $role     = getFullRole();

    $d   = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    $Y   = intval($d->format('Y'));
    $m   = intval($d->format('n'));
    $day = intval($d->format('j'));
    $isoWeek = intval($d->format('W'));

    /* template penampung */
    $bag = ['guru'=>'','kepsek'=>'','sdm'=>'','keu'=>'','backup'=>'','system'=>''];
    $cnt = array_fill_keys(array_keys($bag),0);

    /* ------------------------------------------------------
     *  N‑1  –  Izin ACC Kepsek tapi belum diproses SDM
     * -----------------------------------------------------*/
    if($role==='M:sdm'){
        $n = qCount($conn,
            "SELECT COUNT(*) cnt FROM pengajuan_ijin
             WHERE status_kepalasekolah='Diterima' AND status='Pending'");
        if($n){ $bag['sdm'].="{$n} izin menunggu diproses SDM. "; $cnt['sdm']++; }
    }

    /* ------------------------------------------------------
     *  N‑2  –  Izin Pending (Kepsek)
     * -----------------------------------------------------*/
    if($role==='M:kepala sekolah'){
        $n=qCount($conn,"SELECT COUNT(*) cnt FROM pengajuan_ijin WHERE status_kepalasekolah='Pending'");
        if($n){ $bag['kepsek']="{$n} pengajuan izin menunggu persetujuan Anda."; $cnt['kepsek']++; }
    }

    /* ------------------------------------------------------
     *  N‑3  –  Terlambat ≥3× bulan ini (guru/karyawan)
     * -----------------------------------------------------*/
    if(in_array($role,['P','TK'])){
        $n=qCount($conn,
            "SELECT COUNT(*) cnt FROM absensi
             WHERE nip=? AND terlambat=1 AND MONTH(tanggal)=? AND YEAR(tanggal)=?",
             'sii',[$nip,$m,$Y]);
        if($n>=3){ $bag['guru'].="Anda terlambat {$n}× bulan ini."; $cnt['guru']++; }
    }

    /* ------------------------------------------------------
     *  N‑4  –  Reminder jadwal piket H‑7 … H
     * -----------------------------------------------------*/
    if(in_array($role,['P','TK'])){
        $n=qCount($conn,
            "SELECT COUNT(*) cnt FROM jadwal_piket
             WHERE nip=? AND tanggal BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)",
             's',[$nip]);
        if($n){ $bag['guru'].="Anda punya {$n} jadwal piket dalam 7 hari."; $cnt['guru']++; }
    }

    /* ------------------------------------------------------
     *  N‑5  –  Payroll draft belum final (SDM)
     * -----------------------------------------------------*/
    if($role==='M:sdm'){
        $n=qCount($conn,
            "SELECT COUNT(*) cnt FROM payroll WHERE bulan=? AND tahun=? AND status='draft'",
            'ii',[$m,$Y]);
        if($n){ $bag['sdm'].="{$n} payroll draft bulan ".getIndonesianMonthName($m)." belum final. "; $cnt['sdm']++; }
    }

    /* ------------------------------------------------------
     *  N‑6  –  Anggota belum dibayar (Keuangan/Superadmin)
     * -----------------------------------------------------*/
    if(in_array($role,['M:keuangan','M:superadmin'])){
        $n=qCount($conn,
            "SELECT COUNT(*) cnt FROM anggota_sekolah a
             WHERE NOT EXISTS(SELECT 1 FROM payroll_final pf
                              WHERE pf.id_anggota=a.id AND pf.bulan=? AND pf.tahun=?)",
            'ii',[$m,$Y]);
        if($n){ $bag['keu'].="{$n} anggota belum ada payroll final bulan ".getIndonesianMonthName($m)."."; $cnt['keu']++; }
    }

    /* ------------------------------------------------------
     *  N‑7  –  Error perhitungan payroll (selisih > 1 000)
     * -----------------------------------------------------*/
    if(in_array($role,['M:keuangan','M:superadmin'])){
        $n=qCount($conn,
            "SELECT COUNT(*) cnt FROM payroll
             WHERE ABS((gaji_pokok+total_pendapatan)-(total_potongan+potongan_koperasi+gaji_bersih))>1000");
        if($n){ $bag['keu'].="{$n} payroll terdeteksi selisih hitung."; $cnt['keu']++; }
    }

    /* ------------------------------------------------------
     *  N‑8  –  Kontrak habis ≤30 hari (SDM)
     * -----------------------------------------------------*/
    if($role==='M:sdm'){
        /* kolom tgl_kontrak_selesai DATE harus ada */
        $n=qCount($conn,
            "SELECT COUNT(*) cnt FROM anggota_sekolah
             WHERE status_kerja='Kontrak'
               AND DATEDIFF(tgl_kontrak_selesai,CURDATE()) BETWEEN 0 AND 30");
        if($n){ $bag['sdm'].="{$n} kontrak kerja akan berakhir ≤30 hari."; $cnt['sdm']++; }
    }

    /* ------------------------------------------------------
     *  N‑9  –  Pengingat upload rekap absensi (Senin)
     * -----------------------------------------------------*/
    if($role==='M:sdm' && $d->format('N')==1){
        $bag['sdm'].="Upload rekap absensi minggu lalu hari ini."; $cnt['sdm']++;
    }

    /* ------------------------------------------------------
     *  N‑10 –  Backup DB tiap tanggal 1 (Superadmin)
     * -----------------------------------------------------*/
    if($role==='M:superadmin' && $day==1 && empty($_SESSION['backup_alert_dismissed'])){
        $bag['backup']="Ingat backup database."; $cnt['backup']++;
    }

    /* ------------------------------------------------------
     *  N‑11 –  Log error sistem 24 jam (Superadmin)
     * -----------------------------------------------------*/
    if($role==='M:superadmin'){
        $n=qCount($conn,
            "SELECT COUNT(*) cnt FROM audit_logs
             WHERE action LIKE '%error%' AND created_at>=DATE_SUB(NOW(),INTERVAL 1 DAY)");
        if($n){ $bag['system']="{$n} log error 24 jam terakhir."; $cnt['system']++; }
    }

    /* ------------------------------------------------------
     *  N‑12 –  Manual (tabel notifications)
     * -----------------------------------------------------*/
    $manual=[];
    $stmt=$conn->prepare(
        "SELECT id,title,message,notification_type,link,created_at
         FROM notifications
         WHERE is_read=0
           AND (role_target IN (?, 'all') OR user_id=?)
         ORDER BY priority,created_at DESC");
    $rt=strtolower($role);
    $stmt->bind_param('si',$rt,$uid);
    $stmt->execute();
    $manual=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $total=array_sum($cnt)+count($manual);

    return [
        'total'=>$total,
        'counter'=>$cnt,
        'messages'=>$bag,
        'manual'=>$manual,
        'fullRole'=>$role,
        'generated'=>$d->format('Y-m-d H:i:s')
    ];
}
/* =========================================================
 *  OUTPUT  (JSON bila AJAX;  HTML bila direct)
 * =========================================================*/
$isAjax = (isset($_GET['ajax']) && $_GET['ajax']=='1') ||
          (isset($_SERVER['HTTP_ACCEPT']) &&
           str_contains($_SERVER['HTTP_ACCEPT'],'application/json'));

$data=collectNotifications($conn);
$conn->close();

if($isAjax){
    header('Content-Type:application/json; charset=utf-8');
    echo json_encode($data); exit;
}

extract($data);    //  => $total,$counter,$messages,$manual,$fullRole
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Notifikasi</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>
<div id="notification-container" class="container my-4">

  <!-- ================== GURU / KARYAWAN ================== -->
  <?php if (in_array($fullRole, ['P','TK'])): ?>
      <?php if ($counter['guru']): ?>
          <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
              <i class="fas fa-envelope-open-text me-2"></i>
              <div>
                  <div class="small text-gray-500" data-timestamp="<?= date('Y-m-d H:i:s'); ?>"></div>
                  <?= htmlspecialchars($messages['guru']); ?>
              </div>
          </div>
      <?php else: ?>
          <div class="alert alert-success mb-3">
              <i class="bi bi-check-circle-fill"></i> Tidak ada notifikasi baru untuk guru/karyawan.
          </div>
      <?php endif; ?>
  <?php endif; ?>

  <!-- ================== KEPALA SEKOLAH ================== -->
  <?php if ($fullRole === 'M:kepala sekolah'): ?>
      <?php if ($counter['kepsek']): ?>
          <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
              <i class="fas fa-chalkboard-teacher me-2"></i>
              <div>
                  <div class="small text-gray-500" data-timestamp="<?= date('Y-m-d H:i:s'); ?>"></div>
                  <?= htmlspecialchars($messages['kepsek']); ?>
              </div>
          </div>
      <?php else: ?>
          <div class="alert alert-success mb-3">
              <i class="bi bi-check-circle-fill"></i> Tidak ada notifikasi baru untuk kepala sekolah.
          </div>
      <?php endif; ?>
  <?php endif; ?>

  <!-- ================== SDM ================== -->
  <?php if ($fullRole === 'M:sdm'): ?>
      <?php if ($counter['sdm']): ?>
          <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
              <i class="fas fa-user-cog me-2"></i>
              <div>
                  <div class="small text-gray-500" data-timestamp="<?= date('Y-m-d H:i:s'); ?>"></div>
                  <?= htmlspecialchars($messages['sdm']); ?>
              </div>
          </div>
      <?php else: ?>
          <div class="alert alert-success mb-3">
              <i class="bi bi-check-circle-fill"></i> Tidak ada notifikasi baru untuk SDM.
          </div>
      <?php endif; ?>
  <?php endif; ?>

  <!-- ================== KEUANGAN ================== -->
  <?php if (in_array($fullRole, ['M:keuangan', 'M:superadmin'])): ?>
      <?php if ($counter['keu']): ?>
          <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
              <i class="fas fa-calculator me-2"></i>
              <div>
                  <div class="small text-gray-500" data-timestamp="<?= date('Y-m-d H:i:s'); ?>"></div>
                  <?= htmlspecialchars($messages['keu']); ?>
              </div>
          </div>
      <?php else: ?>
          <div class="alert alert-success mb-3">
              <i class="bi bi-check-circle-fill"></i> Tidak ada notifikasi baru untuk keuangan.
          </div>
      <?php endif; ?>
  <?php endif; ?>

  <!-- ================== BACKUP (Superadmin) ================== -->
  <?php if ($fullRole === 'M:superadmin' && $counter['backup']): ?>
      <div class="alert alert-danger d-flex align-items-center mb-3 backup-alert-item" role="alert">
          <i class="fas fa-database me-2"></i>
          <div>
              <div class="small text-gray-500" data-timestamp="<?= date('Y-m-d H:i:s'); ?>"></div>
              <?= htmlspecialchars($messages['backup']); ?>
              <a href="/payroll_absensi_v2/payroll/superadmin/backup_database.php" class="alert-link">[Backup Sekarang]</a>
          </div>
      </div>
  <?php endif; ?>

  <!-- ================== SYSTEM (Superadmin) ================== -->
  <?php if ($fullRole === 'M:superadmin' && $counter['system']): ?>
      <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
          <i class="fas fa-exclamation-circle me-2"></i>
          <div>
              <div class="small text-gray-500" data-timestamp="<?= date('Y-m-d H:i:s'); ?>"></div>
              <?= htmlspecialchars($messages['system']); ?>
          </div>
      </div>
  <?php endif; ?>

  <!-- ================== NOTIFIKASI MANUAL ================== -->
  <?php if (!empty($manual)): ?>
      <?php foreach ($manual as $n):
          $cls = [
              'warning' => 'alert-warning',
              'success' => 'alert-success',
              'error'   => 'alert-danger',
              'info'    => 'alert-info'
          ][$n['notification_type'] ?? 'info'];
      ?>
      <a href="<?= htmlspecialchars($n['link'] ?? '#'); ?>"
         class="dropdown-item d-flex align-items-center manual-notif"
         data-id="<?= $n['id']; ?>">
          <div class="me-3">
              <div class="icon-circle <?= $cls; ?>">
                  <i class="fas fa-bell text-white"></i>
              </div>
          </div>
          <div>
              <div class="small text-gray-500" data-timestamp="<?= htmlspecialchars($n['created_at']); ?>"></div>
              <strong><?= htmlspecialchars($n['title']); ?></strong><br>
              <?= htmlspecialchars($n['message']); ?>
          </div>
      </a>
      <?php endforeach; ?>
  <?php endif; ?>

  <!-- ================== TOTAL ================== -->
  <div class="mt-4">
      <span class="badge bg-primary">Total Notifikasi: <?= $total; ?></span>
  </div>
</div>

<!-- =========================================================
     JS – Moment.js untuk "x menit yang lalu" & polling 30 detik
     =========================================================-->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script>
$(function () {
    function updateTimestamps() {
        $('.small.text-gray-500').each(function () {
            const ts = $(this).data('timestamp');
            if (ts) $(this).text(moment(ts, "YYYY-MM-DD HH:mm:ss").fromNow());
        });
    }
    updateTimestamps();

    /* -------- Polling setiap 30 detik -------- */
    setInterval(function () {
        $("#notification-container")
            .load("notifikasi.php #notification-container", updateTimestamps);
    }, 30000);

    /* -------- Mark manual notification as read -------- */
    $(document).on('click', '.manual-notif', function () {
        const notifId = $(this).data('id');
        const $item   = $(this);
        $.post("notifikasi.php",
               { action: 'markRead', notifId: notifId },
               function (resp) {
                   if (resp.code === 0) {
                       $item.fadeOut(300, function () { $item.remove(); });
                   }
               }, "json");
    });
});
</script>
</body>
</html>
