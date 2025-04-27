<?php
/************************************************************
 *  pesan.php – Pesan “sekali-baca” (P-1 … P-6)
 ************************************************************/
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/koneksi.php';
require_once __DIR__.'/pustaka_feed.php';   // berisi collectMessages()

start_session_safe();
generate_csrf_token();

/* =========================================================
 *  HANDLER AJAX markRead
 * =========================================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'markRead')
{
    /* —— CSRF —— */
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        send_json(1,'Invalid CSRF token');
    }

    /* —— rate-limit 50 POST / session —— */
    if (!isset($_SESSION['post_hits'])) $_SESSION['post_hits']=0;
    if (++$_SESSION['post_hits'] > 50) {
        send_json(1,'Rate limit exceeded');
    }

    $id     = $_POST['id']     ?? '';
    $source = $_POST['source'] ?? '';

    /* a. pesan pribadi (laporan_surat) */
    if ($source==='laporan' && ctype_digit($id)) {
        $st = $conn->prepare("
            UPDATE laporan_surat
               SET is_read_receiver = 1
             WHERE id = ? AND id_penerima = ?
        ");
        $st->bind_param('ii', $id, $_SESSION['user_id']);
        $st->execute(); $st->close();
        send_json(0,'OK');
    }

    /* b. broadcast manual (notifications) */
    if ($source==='notif' && ctype_digit($id)) {
        $st = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=?");
        $st->bind_param('i',$id);
        $st->execute(); $st->close();
        send_json(0,'OK');
    }

    /* c. pesan sistem → catat di msg_read */
    if ($source==='system') {
        $uid = intval($_SESSION['user_id']);
        $st  = $conn->prepare("
            INSERT INTO msg_read(user_id,msg_id)
            VALUES(?,?)
            ON DUPLICATE KEY UPDATE msg_id = msg_id
        ");
        $st->bind_param('is',$uid,$id);
        $st->execute(); $st->close();
        send_json(0,'OK');
    }

    send_json(1,'Bad parameters');
}

/* =========================================================
 *  AMBIL DATA & DETEKSI AJAX
 * =========================================================*/
$data   = collectMessages($conn);     // fungsi dari pustaka_feed
$isAjax = (isset($_GET['ajax']) && $_GET['ajax']=='1') ||
          (str_contains($_SERVER['HTTP_ACCEPT'] ?? '','application/json'));
$conn->close();

if ($isAjax) {
    send_json(0,'OK',$data);
}

/* ---------- variabel utk view ---------- */
extract($data);   // $total, $messages, $generated
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Pesan</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container my-4" id="message-container">

  <button class="btn btn-secondary mb-3" onclick="history.back()">
    <i class="fas fa-arrow-left"></i> Kembali
  </button>

  <h2 class="mb-3">Pesan Anda</h2>
  <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

  <?php if ($total): ?>
    <div class="list-group">
      <?php foreach ($messages as $m): ?>
        <a href="<?= htmlspecialchars($m['link'] ?: '#'); ?>"
           class="list-group-item list-group-item-action message-item"
           data-id="<?= $m['id']; ?>" data-source="<?= $m['source']; ?>">
          <div class="d-flex justify-content-between">
            <h5 class="mb-1"><?= htmlspecialchars($m['sender_name']); ?></h5>
            <small class="timestamp text-muted"
                   data-timestamp="<?= $m['created']; ?>"></small>
          </div>
          <p class="mb-1"><?= htmlspecialchars($m['isi']); ?></p>
          <small><?= htmlspecialchars($m['judul']); ?></small>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="alert alert-success">
      <i class="fas fa-check-circle"></i> Tidak ada pesan baru.
    </div>
  <?php endif; ?>

  <span class="badge bg-primary mt-3">Total Pesan: <?= $total; ?></span>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script>
$(function(){

  function renderTime(){
      $('.timestamp').each(function(){
         const t=$(this).data('timestamp');
         if(t) $(this).text(moment(t,"YYYY-MM-DD HH:mm:ss").fromNow());
      });
  }
  renderTime();

  /* polling setiap 30 detik */
  setInterval(()=>{
      $("#message-container")
         .load("pesan.php?ajax=1 #message-container",renderTime);
  },30000);

  /* klik → markRead */
  $(document).on('click','.message-item',function(e){
      const $it=$(this);
      $.post("pesan.php",{
          action:'markRead',
          id:$it.data('id'),
          source:$it.data('source'),
          csrf_token:$('#csrf_token').val()
      },resp=>{
          if(resp.code===0){
              $it.fadeOut(300,()=>$it.remove());
          }
      },'json');
  });

});
</script>
</body>
</html>
