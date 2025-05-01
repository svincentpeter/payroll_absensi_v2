<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/pustaka_feed.php';

start_session_safe();
generate_csrf_token();

// Data user
$nama     = $_SESSION['nama']      ?? ($_SESSION['username'] ?? 'Pengguna');
$fullRole = getFullRole();

// Ambil data
$dataNotif = collectNotifications($conn);
$dataPesan  = collectMessages($conn);
$conn->close();

// AJAX response
if ((isset($_GET['ajax']) && $_GET['ajax']==='1')
  || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
) {
    send_json(0, 'OK', [
      'generated'   => date('Y-m-d H:i:s'),
      'notifikasi'  => $dataNotif,
      'pesan'       => $dataPesan,
      'total_semua' => $dataNotif['total'] + $dataPesan['total'],
    ]);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pusat Info</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    body { background: #f0f4f8; }

    .card-header-gradient {
      /* Dari biru norak, diganti teal lembut */
      background: linear-gradient(135deg, #1cc88a 0%, #17a673 100%);
      color: #fff;
    }
    .badge-role { font-size: .9rem; }

    /* Highlight area nav-pills */
    .pills-container {
      background: #fff;
      border-radius: .5rem;
      padding: .75rem;
      box-shadow: 0 .125rem .25rem rgba(0,0,0,.075);
      margin-bottom: 1.5rem;
    }
    .nav-pills .nav-link {
      border-radius: .5rem;
      transition: background-color .2s;
    }
    .nav-pills .nav-link:hover {
      background-color: rgba(23,166,115,.1);
    }
    .nav-pills .nav-link.active {
      background-color: #17a673 !important;
      color: #fff;
    }

    .notif-item { border-left-width: 4px; }
    .notif-guru   { border-left-color: #f6c23e; }
    .notif-kepsek { border-left-color: #36b9cc; }
    .notif-sdm    { border-left-color: #f6c23e; }
    .notif-keu    { border-left-color: #1cc88a; }
    .notif-backup { border-left-color: #e74a3b; }
    .notif-system { border-left-color: #858796; }
  </style>
</head>
<body>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <button class="btn btn-light shadow-sm" onclick="window.history.back()">
      <i class="fas fa-arrow-left"></i> Kembali
    </button>
    <h1 class="h3 mb-0">Pusat Info</h1>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header card-header-gradient d-flex justify-content-between align-items-center">
      <div>
        <i class="fas fa-user-circle me-2"></i>
        Halo, <strong><?= htmlspecialchars($nama) ?></strong>
        <span class="badge bg-light text-dark badge-role"><?= htmlspecialchars($fullRole) ?></span>
      </div>
      <small><?= date('d M Y, H:i') ?></small>
    </div>
    <div class="card-body p-3">

      <!-- BEGIN HIGHLIGHTED PILLS -->
      <div class="pills-container text-center">
        <ul class="nav nav-pills justify-content-center" id="tabInfo">
          <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tabNotif">
              <i class="fas fa-bell me-1"></i> Notifikasi
              <span class="badge bg-danger ms-1"><?= $dataNotif['total'] ?></span>
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabPesan">
              <i class="fas fa-envelope me-1"></i> Pesan
              <span class="badge bg-danger ms-1"><?= $dataPesan['total'] ?></span>
            </button>
          </li>
        </ul>
      </div>
      <!-- END HIGHLIGHTED PILLS -->

      <div class="tab-content">
        <!-- Notifikasi -->
        <div class="tab-pane fade show active" id="tabNotif">
          <h5 class="mb-3">Notifikasi untuk Role: <em><?= htmlspecialchars($fullRole) ?></em></h5>
          <?php if ($dataNotif['total']): ?>
            <?php foreach ($dataNotif['messages'] as $cat => $rows): ?>
              <?php foreach ($rows as $r): ?>
                <?php $cls = match($cat) {
                    'guru'   => 'notif-guru',
                    'kepsek' => 'notif-kepsek',
                    'sdm'    => 'notif-sdm',
                    'keu'    => 'notif-keu',
                    'backup' => 'notif-backup',
                    default  => 'notif-system',
                  };
                ?>
                <div class="notif-item alert alert-light shadow-sm <?= $cls ?> mb-2 d-flex align-items-center">
                  <i class="fas fa-info-circle me-3 text-secondary"></i>
                  <div class="flex-fill">
                    <?= htmlspecialchars(
                          is_array($r)
                            ? ($r['txt'] ?? implode(' – ', array_slice($r,0,3)))
                            : $r
                        ); ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endforeach; ?>

            <?php foreach ($dataNotif['manual'] as $m): ?>
              <div class="alert alert-info shadow-sm mb-2 d-flex">
                <i class="fas fa-bell me-3 text-primary"></i>
                <div>
                  <strong><?= htmlspecialchars($m['title']) ?></strong><br>
                  <?= htmlspecialchars($m['message']) ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="alert alert-success text-center shadow-sm">
              <i class="fas fa-check-circle me-2"></i> Tidak ada notifikasi.
            </div>
          <?php endif; ?>
        </div>

        <!-- Pesan -->
        <div class="tab-pane fade" id="tabPesan">
          <h5 class="mb-3">Pesan untuk Role: <em><?= htmlspecialchars($fullRole) ?></em></h5>
          <?php if ($dataPesan['total']): ?>
            <div class="list-group">
              <?php foreach ($dataPesan['messages'] as $p): ?>
                <a href="<?= htmlspecialchars($p['link'] ?? '#') ?>"
                   class="list-group-item list-group-item-action d-flex align-items-start mb-2 shadow-sm">
                  <div class="me-3 text-primary">
                    <i class="fas fa-envelope-open-text fa-lg"></i>
                  </div>
                  <div class="flex-fill">
                    <div class="d-flex justify-content-between">
                      <h6 class="mb-1"><?= htmlspecialchars($p['sender_name']) ?></h6>
                      <small class="text-muted"><?= htmlspecialchars($p['created']) ?></small>
                    </div>
                    <p class="mb-1"><?= htmlspecialchars($p['isi']) ?></p>
                    <small class="badge bg-secondary"><?= htmlspecialchars($p['judul']) ?></small>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="alert alert-success text-center shadow-sm">
              <i class="fas fa-check-circle me-2"></i> Tidak ada pesan.
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
