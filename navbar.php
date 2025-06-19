<?php
// File: navbar.php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/koneksi.php';

start_session_safe();
generate_csrf_token();  // agar token tersedia di setiap halaman

$baseUrl   = getBaseUrl();
$nama      = $_SESSION['nama']         ?? $_SESSION['username'] ?? '';
$fotoPath  = $_SESSION['foto_profil']  ?? 'default.jpg';
$foto      = getProfilePhotoUrl($fotoPath);
$pageId    = htmlspecialchars($pageId ?? '');
$csrfToken = htmlspecialchars($_SESSION['csrf_token']);
?>
<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow"
     data-page="<?= $pageId ?>">
  <!-- Sidebar Toggle -->
  <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle me-3">
    <i class="fa fa-bars"></i>
  </button>

  <!-- Search -->
  <form class="d-none d-sm-inline-block form-inline me-auto ms-md-3 my-2 my-md-0 mw-100 navbar-search">
    <div class="input-group">
      <input type="text" class="form-control bg-light border-0 small"
             placeholder="Search for..." aria-label="Search">
      <span class="input-group-text bg-primary text-white">
        <i class="fas fa-search fa-sm"></i>
      </span>
    </div>
  </form>

  <!-- Topbar -->
  <ul class="navbar-nav ms-auto">
    <!-- Alerts -->
    <li class="nav-item dropdown no-arrow mx-1">
      <a class="nav-link dropdown-toggle position-relative" href="#" id="alertsDropdown"
         role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-bell fa-fw"></i>
        <span class="badge bg-danger badge-counter d-none"></span>
      </a>
      <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in"
           aria-labelledby="alertsDropdown">
        <h6 class="dropdown-header">Alerts Center</h6>
        <div id="notificationDropdownList"></div>
        <a class="dropdown-item text-center small text-gray-500"
           href="<?= $baseUrl; ?>/notifikasi.php">Show All Alerts</a>
      </div>
    </li>

    <!-- Messages -->
    <li class="nav-item dropdown no-arrow mx-1">
      <a class="nav-link dropdown-toggle position-relative" href="#" id="messagesDropdown"
         role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-envelope fa-fw"></i>
        <span class="badge bg-danger badge-counter d-none"></span>
      </a>
      <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in"
           aria-labelledby="messagesDropdown">
        <h6 class="dropdown-header">Messages Center</h6>
        <div id="messagesDropdownList"></div>
        <a class="dropdown-item text-center small text-gray-500"
           href="<?= $baseUrl; ?>/pesan.php">Show All Messages</a>
      </div>
    </li>

    <!-- Help -->
    <li class="nav-item mx-1">
      <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#helpModal">
        <i class="fas fa-question-circle fa-fw"></i>
      </a>
    </li>

    <div class="topbar-divider d-none d-sm-block"></div>

    <!-- User -->
    <li class="nav-item dropdown no-arrow">
      <a class="nav-link dropdown-toggle" href="#" id="userDropdown"
         role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="me-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars($nama); ?></span>
        <img class="img-profile rounded-circle"
             src="<?= htmlspecialchars($foto); ?>" alt="Profile"
             style="width:40px;height:40px;object-fit:cover;">
      </a>
      <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in"
           aria-labelledby="userDropdown">
        <a class="dropdown-item" href="<?= $baseUrl; ?>/profile.php">
          <i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Profile
        </a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="#" id="logoutBtn">
  <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i> Logout
</a>

      </div>
    </li>
  </ul>
</nav>

<!-- Modal Panduan -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="helpModalLabel">Panduan Halaman</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><p class="text-center text-muted">Memuat panduan…</p></div>
    </div>
  </div>
</div>

<meta name="csrf-token" content="<?= $csrfToken ?>">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.6/dist/sweetalert2.all.min.js"></script>

<script>
$(function(){

  $(document).on('click', '#logoutBtn', function(e) {
  e.preventDefault();
  Swal.fire({
    title: 'Yakin ingin logout?',
    text: "Sesi Anda akan berakhir.",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, logout!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = "<?= $baseUrl; ?>/logout.php";
    }
  });
});


  const csrfToken = $('meta[name="csrf-token"]').attr('content');

  /* helper untuk escape text */
  function esc(text) {
    return $('<div>').text(text).html();
  }

  /* helper menambah item */
  function addItem(icon, bg, msg, link = '#', ts) {
    const time = ts
      ? moment(ts, "YYYY-MM-DD HH:mm:ss").fromNow()
      : '';
    $('#notificationDropdownList').append(`
      <a class="dropdown-item d-flex align-items-center ${bg==='danger'?'backup-alert-item':''}"
         href="${esc(link)}">
        <div class="me-3">
          <div class="icon-circle bg-${esc(bg)}"><i class="${esc(icon)} text-white"></i></div>
        </div>
        <div>
          <div class="small text-gray-500">${esc(time)}</div>
          <span class="fw-bold">${esc(msg)}</span>
        </div>
      </a>
    `);
  }

  /* Load Notifications */
  function loadNotifications(){
    $.getJSON("<?= $baseUrl ?>/notifikasi.php?ajax=1", function(d){
      // Badge
      const $badge = $("#alertsDropdown .badge-counter");
      if (d.total > 0) {
        $badge.text(d.total > 9 ? d.total + "+" : d.total).removeClass("d-none");
      } else {
        $badge.addClass("d-none");
      }
      // Clear list
      const $list = $("#notificationDropdownList").empty();

      // Kategori
      const cfg = {
        guru:   {icon:'fas fa-envelope-open',       bg:'secondary'},
        kepsek: {icon:'fas fa-user-tie',            bg:'primary'},
        sdm:    {icon:'fas fa-user-cog',            bg:'warning'},
        keu:    {icon:'fas fa-calculator',          bg:'info'},
        system: {icon:'fas fa-exclamation-circle',  bg:'danger'},
        backup: {icon:'fas fa-database',            bg:'danger', link:`<?= $baseUrl ?>/payroll/superadmin/backup_database.php`}
      };
      // Non-manual
      for (let cat in cfg) {
        const msgs = d.messages[cat] || [];
        msgs.forEach(m => {
          addItem(cfg[cat].icon, cfg[cat].bg, m, cfg[cat].link, d.generated);
        });
      }
      // Manual
      (d.manual || []).forEach(n => {
        let bg = 'info';
        if (n.notification_type === 'warning') bg = 'warning';
        else if (n.notification_type === 'success') bg = 'success';
        else if (n.notification_type === 'error') bg = 'danger';
        addItem('fas fa-bell', bg, `${n.title}\n${n.message}`, n.link, n.created_at);
      });
      // Fallback
      if (!$('#notificationDropdownList').children().length) {
        $list.append('<a class="dropdown-item text-center small text-gray-500" href="#">No alerts available</a>');
      }
    });
  }

  /* Messages */
  function loadMessages(){
    $.getJSON("<?= $baseUrl ?>/pesan.php?ajax=1", function(d){
      const $badge = $("#messagesDropdown .badge-counter");
      if (d.total > 0) {
        $badge.text(d.total > 9 ? d.total + "+" : d.total).removeClass("d-none");
      } else {
        $badge.addClass("d-none");
      }
      const $list = $("#messagesDropdownList").empty();
      (d.messages || []).slice(0,5).forEach(m => {
        const time = moment(m.created, "YYYY-MM-DD HH:mm:ss").fromNow();
        $list.append(`
          <a class="dropdown-item d-flex align-items-center message-item"
             href="${esc(m.link)}" data-id="${esc(m.id)}" data-source="${esc(m.source)}">
            <div class="me-3">
              <div class="icon-circle bg-primary"><i class="fas fa-envelope text-white"></i></div>
            </div>
            <div>
              <div class="small text-gray-500">${esc(time)}</div>
              <span class="fw-bold">${esc(m.sender_name)}</span><br>
              <span>${esc(m.isi)}</span>
            </div>
          </a>
        `);
      });
      if (!$('#messagesDropdownList').children().length) {
        $list.append('<a class="dropdown-item text-center small text-gray-500" href="#">No messages available</a>');
      }
    });
  }

  /* Initial + polling */
  loadNotifications();
  loadMessages();
  setInterval(loadNotifications, 30000);
  setInterval(loadMessages, 30000);

  /* Handlers */
  $(document).on('click', '.manual-notif', function(e){
    e.preventDefault();
    const $el = $(this), id = $el.data('id');
    $.post("<?= $baseUrl ?>/notifikasi.php", {
      action: 'markRead',
      notifId: id,
      csrf_token: csrfToken
    }, function(resp){
      if (resp.code === 0) $el.fadeOut(300, () => $el.remove());
    }, 'json');
  });

  $(document).on('click', '.backup-alert-item', function(){
    $.post("<?= $baseUrl ?>/notifikasi.php", {
      dismiss_backup: 1,
      csrf_token: csrfToken
    });
  });

  $(document).on('click', '.message-item', function(e){
    e.preventDefault();
    const $el = $(this);
    $.post("<?= $baseUrl ?>/pesan.php", {
      action: 'markRead',
      id: $el.data('id'),
      source: $el.data('source'),
      csrf_token: csrfToken
    }, function(resp){
      if (resp.code === 0) $el.fadeOut(300, () => $el.remove());
    }, 'json');
  });

  /* Help modal loader (tidak berubah) */
  $('#helpModal').on('show.bs.modal', function () {
    const pageId = $('nav.navbar').data('page') || '';
    const [role, ...parts] = pageId.split('_');
    const page = parts.join('_');
    const url  = '<?= $baseUrl ?>/guides/' + role + '/' + page + '.html';
    const $body = $(this).find('.modal-body');
    $(this).find('#helpModalLabel')
           .text('Panduan: ' + role.toUpperCase() + ' – ' + (page.replace(/_/g,' ')||''));
    $body.html('<p class="text-center text-muted">Memuat panduan…</p>')
         .load(url, function(_, status){
           if (status === 'error') {
             $body.html('<p class="text-danger">Panduan belum tersedia untuk halaman ini.</p>');
           }
         });
  });
});
</script>
