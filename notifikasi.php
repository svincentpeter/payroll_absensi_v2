<?php
/* =========================================================
 *  NOTIFIKASI  —  Persisten (N-1 … N-12)
 * ========================================================= */
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/koneksi.php';
require_once __DIR__.'/pustaka_feed.php';   // kolektor terpusat

start_session_safe();
generate_csrf_token();

/* =========================================================
 *  HANDLER POST  (markRead · dismiss_backup)
 * =========================================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* —— validasi CSRF —— */
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        send_json(1,'Invalid CSRF token');
    }

    /* —— rate-limit 50 POST / session —— */
    if (!isset($_SESSION['post_hits'])) $_SESSION['post_hits'] = 0;
    if (++$_SESSION['post_hits'] > 50) {
        send_json(1,'Rate limit exceeded');
    }

    /* ---------- a. tandai manual notif (N-12) dibaca ---------- */
    if (($_POST['action'] ?? '') === 'markRead') {
        $id = intval($_POST['notifId'] ?? 0);
        if ($id <= 0) send_json(1,'Bad ID');

        $st = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=?");
        $st->bind_param('i',$id);
        $ok = $st->execute();
        $st->close();
        $ok ? send_json(0,'OK') : send_json(1,$conn->error);
    }

    /* ---------- b. dismiss backup alert (N-10) ---------- */
    if (isset($_POST['dismiss_backup'])) {
        $uid    = intval($_SESSION['user_id'] ?? 0);
        $yyyymm = date('Ym');
        $conn->query("INSERT IGNORE INTO backup_dismiss(user_id,yyyymm)
                      VALUES($uid,'$yyyymm')");
        send_json(0,'Dismissed');
    }

    /* kalau tidak cocok parameter */
    send_json(1,'Bad parameters');
}

/* =========================================================
 *  AMBIL DATA + DETEKSI AJAX
 * =========================================================*/
$data   = collectNotifications($conn);          // fungsi dari pustaka_feed
$isAjax = (isset($_GET['ajax']) && $_GET['ajax']=='1') ||
          (str_contains($_SERVER['HTTP_ACCEPT'] ?? '','application/json'));
$conn->close();

if ($isAjax) {
    send_json(0,'OK',$data);
}

/* ====== variabel untuk tampilan HTML ====== */
extract($data);   // $total, $counter, $messages, $manual, $fullRole, $generated
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Notifikasi</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container my-4">

  <!-- Tombol kembali -->
  <button class="btn btn-secondary mb-3" onclick="history.back()">
    <i class="fas fa-arrow-left"></i> Kembali
  </button>

  <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

  <div id="notification-container">
  <?php
    /* ---------- loop per-kategori ---------- */
    $ikon = [
      'guru'=>'fas fa-envelope-open',   'kepsek'=>'fas fa-user-tie',
      'sdm'=>'fas fa-user-cog',         'keu'=>'fas fa-calculator',
      'backup'=>'fas fa-database',      'system'=>'fas fa-exclamation-circle'
    ];
    $warna = [
      'guru'=>'warning','kepsek'=>'info','sdm'=>'warning',
      'keu'=>'info','backup'=>'danger','system'=>'danger'
    ];
    foreach ($messages as $cat=>$rows):
        if (!$rows) continue;
        foreach ($rows as $msg):
  ?>
      <div class="alert alert-<?= $warna[$cat] ?> d-flex align-items-center mb-3" role="alert">
        <i class="<?= $ikon[$cat] ?> me-2"></i>
        <div>
          <div class="small text-gray-500" data-timestamp="<?= $generated; ?>"></div>
          <?= htmlspecialchars(is_array($msg)?$msg['txt']??$msg:$msg); ?>
        </div>
      </div>
  <?php
        endforeach;
    endforeach;
  ?>

    <!-- ---------- notifikasi manual ---------- -->
    <?php foreach ($manual as $n):
        $cls = match($n['notification_type']) {
            'warning'=>'alert-warning','success'=>'alert-success',
            'error'=>'alert-danger', default=>'alert-info'
        };
    ?>
      <a href="<?= htmlspecialchars($n['link'] ?: '#'); ?>"
         class="alert <?= $cls ?> d-flex align-items-center mb-3 manual-notif"
         data-id="<?= $n['id']; ?>">
        <div class="me-3"><i class="fas fa-bell text-white"></i></div>
        <div>
          <div class="small text-gray-500" data-timestamp="<?= $n['created_at']; ?>"></div>
          <strong><?= htmlspecialchars($n['title']); ?></strong><br>
          <?= htmlspecialchars($n['message']); ?>
        </div>
      </a>
    <?php endforeach; ?>

    <span class="badge bg-primary">Total : <?= $total; ?></span>
  </div><!-- /container -->
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script>
$(function(){

  /* fungsi waktu relatif */
  function renderTime(){
    $('.text-gray-500').each(function(){
      const t = $(this).data('timestamp');
      if (t) $(this).text(moment(t,"YYYY-MM-DD HH:mm:ss").fromNow());
    });
  }
  renderTime();

  /* polling 30 detik */
  setInterval(()=>{
     $("#notification-container")
         .load("notifikasi.php?ajax=1 #notification-container",renderTime);
  },30000);

  /* klik manual → mark read */
  $(document).on('click','.manual-notif',function(e){
      const id=$(this).data('id'), $row=$(this);
      $.post("notifikasi.php",{
          action:'markRead', notifId:id, csrf_token:$('#csrf_token').val()
      }, resp=>{
          if(resp.code===0) $row.fadeOut(300,()=>$row.remove());
      },'json');
  });

});
</script>
</body>
</html>
