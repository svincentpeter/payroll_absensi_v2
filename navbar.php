<?php
// File: navbar.php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/koneksi.php';

start_session_safe();

$baseUrl = getBaseUrl();
$nama    = $_SESSION['nama']     ?? $_SESSION['username'] ?? '';
$fotoPath = $_SESSION['foto_profil'] ?? 'default.jpg';
$foto     = getProfilePhotoUrl($fotoPath);

// Ambil pageId (harus diset di tiap halaman sebelum include)
$pageId  = $pageId ?? '';
?>
<!-- Navbar (Topbar) -->
<!-- Navbar (Topbar) -->
<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow"
     data-page="<?= htmlspecialchars($pageId) ?>">

  <!-- Sidebar Toggle (Topbar) -->
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

  <!-- Topbar Navbar -->
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
        <a class="dropdown-item text-center small text-gray-500" href="<?= $baseUrl; ?>/notifikasi.php">
          Show All Alerts
        </a>
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
        <a class="dropdown-item text-center small text-gray-500" href="<?= $baseUrl; ?>/pesan.php">
          Show All Messages
        </a>
      </div>
    </li>

    <!-- Help Icon -->
    <li class="nav-item mx-1">
      <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#helpModal">
        <i class="fas fa-question-circle fa-fw"></i>
      </a>
    </li>

    <!-- Divider -->
    <div class="topbar-divider d-none d-sm-block"></div>

    <!-- User -->
    <li class="nav-item dropdown no-arrow">
      <a class="nav-link dropdown-toggle" href="#" id="userDropdown"
         role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="me-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars($nama); ?></span>
        <img class="img-profile rounded-circle"
             src="<?= htmlspecialchars($foto); ?>"
             alt="Profile"
             style="width:40px;height:40px;object-fit:cover;">
      </a>
      <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in"
           aria-labelledby="userDropdown">
        <a class="dropdown-item" href="<?= $baseUrl; ?>/profile.php">
          <i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Profile
        </a>
        <a class="dropdown-item" href="<?= $baseUrl; ?>/settings.php">
          <i class="fas fa-cogs fa-sm fa-fw me-2 text-gray-400"></i> Settings
        </a>
        <a class="dropdown-item" href="<?= $baseUrl; ?>/activity_log.php">
          <i class="fas fa-list fa-sm fa-fw me-2 text-gray-400"></i> Activity Log
        </a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="<?= $baseUrl; ?>/logout.php">
          <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i> Logout
        </a>
      </div>
    </li>

  </ul>
</nav>
<!-- End of Topbar -->

<!-- Modal Panduan -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="helpModalLabel">Panduan Halaman</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-center text-muted">Memuat panduan…</p>
      </div>
    </div>
  </div>
</div>

<!-- jQuery & Moment -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script>    
$(function(){

/* ==========================================================
 *  NOTIFICATIONS (🔔)
 * =========================================================*/
function loadNotifications(){
    $.getJSON("<?=$baseUrl;?>/notifikasi.php?ajax=1", function(d){

        /* --- badge --- */
        const $badge=$("#alertsDropdown .badge-counter");
        if(d.total>0){
            $badge.text(d.total>9?d.total+"+":d.total).removeClass("d-none");
        }else{ $badge.addClass("d-none"); }

        /* --- dropdown list --- */
        const $list=$("#notificationDropdownList").empty();

        // helper
        const addItem=(icon,bg,msg,link='#')=>{
            $list.append(`
              <a class="dropdown-item d-flex align-items-center ${bg==='danger'?'backup-alert-item':''}"
                 href="${link}">
                <div class="me-3">
                  <div class="icon-circle bg-${bg}"><i class="${icon} text-white"></i></div>
                </div>
                <div>
                  <div class="small text-gray-500">${moment().format("D MMM YYYY")}</div>
                  <span class="fw-bold">${msg}</span>
                </div>
              </a>`);
        };

        if(d.counter.guru)   addItem('fas fa-envelope-open','secondary',d.messages.guru);
        if(d.counter.kepsek) addItem('fas fa-user-tie','primary',d.messages.kepsek);
        if(d.counter.sdm)    addItem('fas fa-user-cog','warning',d.messages.sdm);
        if(d.counter.keu)    addItem('fas fa-calculator','info',d.messages.keu);
        if(d.counter.system) addItem('fas fa-exclamation-circle','danger',d.messages.system);
        if(d.counter.backup) addItem('fas fa-database','danger',d.messages.backup,"<?=$baseUrl;?>/payroll/superadmin/backup_database.php");

        // manual notif
        if(d.manual){
            d.manual.forEach(n=>{
                let cls='info';
                if(n.notification_type==='warning') cls='warning';
                else if(n.notification_type==='success') cls='success';
                else if(n.notification_type==='error') cls='danger';
                $list.append(`
                  <a class="dropdown-item d-flex align-items-center manual-notif"
                     href="${n.link||'#'}" data-id="${n.id}">
                    <div class="me-3">
                      <div class="icon-circle bg-${cls}">
                        <i class="fas fa-bell text-white"></i>
                      </div>
                    </div>
                    <div>
                      <div class="small text-gray-500">${moment(n.created_at).format("D MMM YYYY")}</div>
                      <span class="fw-bold">${n.title}</span><br>
                      <span>${n.message}</span>
                    </div>
                  </a>`);
            });
        }

        if(!$list.children().length){
            $list.append('<a class="dropdown-item text-center small text-gray-500" href="#">No alerts available</a>');
        }
    });
}

/* ==========================================================
 *  MESSAGES (✉️)
 * =========================================================*/
function loadMessages(){
    $.getJSON("<?=$baseUrl;?>/pesan.php?ajax=1", function(d){

        /* --- badge --- */
        const $badge=$("#messagesDropdown .badge-counter");
        if(d.total>0){
            $badge.text(d.total>9?d.total+"+":d.total).removeClass("d-none");
        }else{ $badge.addClass("d-none"); }

        /* --- dropdown list --- */
        const $list=$("#messagesDropdownList").empty();

        if(d.messages && d.messages.length){
            d.messages.slice(0,5).forEach(m=>{
                $list.append(`
                  <a class="dropdown-item d-flex align-items-center message-item"
                     href="${m.link||'#'}"
                     data-id="${m.id}" data-source="${m.source}">
                    <div class="me-3">
                      <div class="icon-circle bg-primary">
                        <i class="fas fa-envelope text-white"></i>
                      </div>
                    </div>
                    <div>
                      <div class="small text-gray-500">${moment(m.created).format("D MMM YYYY")}</div>
                      <span class="fw-bold">${m.sender_name}</span><br>
                      <span>${m.isi.substring(0,50)}...</span>
                    </div>
                  </a>`);
            });
        }else{
            $list.append('<a class="dropdown-item text-center small text-gray-500" href="#">No messages available</a>');
        }
    });
}

/* ==========================================================
 *  POLLING + CLICK HANDLERS
 * =========================================================*/
loadNotifications();
loadMessages();
setInterval(loadNotifications,30000);
setInterval(loadMessages,30000);

/* mark manual notif read */
$(document).on('click','.manual-notif',function(){
    const id=$(this).data('id'), $el=$(this);
    $.post("<?=$baseUrl;?>/notifikasi.php",{action:'markRead',notifId:id},res=>{
        if(res.code===0) $el.fadeOut(300,()=>$el.remove());
    },'json');
});

/* dismiss backup alert */
$(document).on('click','.backup-alert-item',()=>$.post("<?=$baseUrl;?>/notifikasi.php",{dismissed:1}));

/* mark message read */
$(document).on('click','.message-item',function(){
    const id=$(this).data('id'), src=$(this).data('source'), $el=$(this);
    $.post("<?=$baseUrl;?>/pesan.php",{action:'markRead',id:id,source:src},res=>{
        if(res.code===0) $el.fadeOut(300,()=>$el.remove());
    },'json');
});

 /* ==========================================================
   *  HELP MODAL (❓)
   * ========================================================== */
  $('#helpModal').on('show.bs.modal', function () {
    const pageId = $('nav.navbar').data('page') || '';
    // pisah role dan page
    const [role, ...parts] = pageId.split('_');
    const page = parts.join('_');
    const url  = '<?= $baseUrl; ?>/guides/' + role + '/' + page + '.html';
    const $body = $(this).find('.modal-body');
    // ubah judul
    $(this).find('#helpModalLabel')
      .text('Panduan: ' + role.toUpperCase() + ' – ' + (page.replace(/_/g,' ')||''));
    // load konten
    $body.html('<p class="text-center text-muted">Memuat panduan…</p>')
         .load(url, function(_, status){
           if(status === 'error'){
             $body.html('<p class="text-danger">Panduan belum tersedia untuk halaman ini.</p>');
           }
         });
  });
  
});
</script>
