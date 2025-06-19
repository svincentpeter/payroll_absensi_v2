<?php
// File: /payroll_absensi_v2/superadmin/manage_manajerial.php

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../koneksi.php';

// Session & CSRF
start_session_safe();
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'] ?? '';

// Otorisasi hanya Superadmin
authorize(['M:Superadmin']);

// Tangani Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_id'])) {
    header('Content-Type: application/json');
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $id = intval($_POST['reset_id']);
        $stmt = $conn->prepare("
            UPDATE anggota_sekolah
            SET password = MD5('123456')
            WHERE id = ? AND role = 'M'
        ");
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) throw new Exception($stmt->error);
        add_audit_log($conn, $_SESSION['nip'], 'ResetPassword', "Superadmin mereset password user ID $id");
        echo json_encode(['success' => true, 'message' => "Password user berhasil di-reset menjadi <b>123456</b>."]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => "Gagal mereset password: " . $e->getMessage()]);
    }
    exit;
}

// Ambil filter & paging
$search    = trim($_GET['search']    ?? '');
$jobTitle  = trim($_GET['job_title'] ?? '');
$page      = max(1, intval($_GET['page'] ?? 1));
$perPage   = 12;
$offset    = ($page - 1) * $perPage;

// Bangun WHERE
$where = ["role='M'", "is_delete=0"];
$params = [];
$types = '';
if ($search !== '') {
    $where[]   = "(nama LIKE ? OR nip LIKE ?)";
    $params[]  = "%{$search}%";
    $params[]  = "%{$search}%";
    $types    .= 'ss';
}
if ($jobTitle !== '') {
    $where[]  = "job_title = ?";
    $params[] = $jobTitle;
    $types   .= 's';
}
$whereSql = implode(' AND ', $where);

// Hitung total
$stmtTot = $conn->prepare("SELECT COUNT(*) FROM anggota_sekolah WHERE {$whereSql}");
if ($params) $stmtTot->bind_param($types, ...$params);
$stmtTot->execute();
$stmtTot->bind_result($totalRows);
$stmtTot->fetch();
$stmtTot->close();

// Ambil data
$sql = "
  SELECT id, nip, nama, job_title, foto_profil, role
  FROM anggota_sekolah
  WHERE {$whereSql}
  ORDER BY nama
  LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
if ($params) {
    $typesAll  = $types . 'ii';
    $valuesAll = array_merge($params, [$perPage, $offset]);
    $bindParams = [];
    $bindParams[] = &$typesAll;
    foreach ($valuesAll as $i => $v) $bindParams[] = &$valuesAll[$i];
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$res = $stmt->get_result();

// Daftar job_title untuk filter
$jtRes = $conn->query("SELECT DISTINCT job_title FROM anggota_sekolah WHERE role='M' AND job_title IS NOT NULL AND job_title != '' ORDER BY job_title");
$jobTitles = $jtRes->fetch_all(MYSQLI_ASSOC);

add_audit_log($conn, $_SESSION['nip'], 'ViewManageManajerial', "Superadmin melihat halaman manage manajerial");
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Reset Password Manajerial — Superadmin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap & SB Admin 2 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(90deg, #667eea 0%, #7f53ac 100%);
            --secondary-gradient: linear-gradient(90deg, #4e54c8, #8f94fb 100%);
        }

        /* Page Title */
        .page-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 2.1rem;
            color: #0d47a1;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.09);
            display: flex;
            align-items: center;
            gap: 0.7rem;
            border-bottom: 3px solid #1976d2;
            padding-bottom: 0.3rem;
            margin-bottom: 1.5rem;
            animation: fadeInSlide 0.5s ease-in-out both;
        }

        .page-title i {
            color: #1976d2;
            font-size: 2.3rem;
        }

        /* Filter Card Style */
        .card-filter {
            background: var(--secondary-gradient);
            border-radius: 14px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(60, 72, 146, 0.06);
            color: #fff;
        }

        .card-filter .form-control,
        .card-filter .form-select {
            border-radius: 8px;
            min-height: 42px;
        }

        .card-filter label {
            color: #fff !important;
            font-weight: 500;
        }

        .card-filter .btn {
            border-radius: 8px;
            font-weight: 500;
            padding-left: 2rem;
            padding-right: 2rem;
        }

        .card-filter .btn-primary {
            background: #354ad9;
            border: none;
        }

        .card-filter .btn-outline-secondary {
            border: 1.5px solid #fff;
            color: #fff;
        }

        /* Profile Card Style */
        .profile-card {
            background: #fff;
            border-radius: 18px;
            border: none;
            box-shadow: 0 3px 12px rgba(30, 60, 110, .08);
            margin-bottom: 24px;
            transition: box-shadow 0.23s cubic-bezier(0.25, 0.8, 0.25, 1);
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .profile-card:hover {
            box-shadow: 0 8px 24px rgba(60, 72, 146, 0.12);
            transform: translateY(-4px) scale(1.01);
        }

        .profile-avatar {
            width: 82px;
            height: 82px;
            border-radius: 50%;
            object-fit: cover;
            margin: -46px auto 0;
            border: 4px solid #fff;
            background: #e0e5f6;
            box-shadow: 0 2px 8px rgba(60, 72, 146, 0.13);
        }

        .profile-card .card-body {
            text-align: center;
            padding-top: 2.3rem;
            padding-bottom: 1.2rem;
            flex: 1 1 auto;
        }

        .profile-card .card-title {
            font-size: 1.09rem;
            font-weight: 700;
            color: #2d3675;
            margin-bottom: 0.3rem;
        }

        .profile-card .card-subtitle {
            font-size: 0.99rem;
            color: #6c757d;
            font-weight: 500;
        }

        .profile-card .info-group {
            font-size: 0.92rem;
            color: #6c7d8d;
            margin-bottom: 0.25rem;
        }

        .profile-card .reset-btn {
            width: 100%;
            background: var(--primary-gradient);
            color: #fff;
            font-weight: 600;
            border: none;
            border-radius: 9px;
            padding: 0.63rem 0;
            margin-top: 1rem;
            transition: box-shadow 0.15s;
        }

        .profile-card .reset-btn:hover {
            box-shadow: 0 4px 12px #7f53ac33;
        }

        .profile-card .card-footer {
            background: #f3f4fa;
            border-top: none;
            border-radius: 0 0 18px 18px;
            font-size: 0.97rem;
            color: #72809b;
            text-align: center;
            padding: 0.7rem 0.5rem;
        }

        @media (max-width: 767px) {
            .profile-card {
                margin-bottom: 1.2rem;
            }

            .page-title {
                font-size: 1.4rem;
            }

            .card-filter {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>

<body id="page-top">
    <div id="wrapper">
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include __DIR__ . '/../navbar.php'; ?>
                <?php include __DIR__ . '/../breadcrumb.php'; ?>

                <div class="container-fluid py-4">
                    <!-- Page Title -->
                    <h1 class="page-title">
                        <i class="fas fa-user-shield"></i> Reset Password Manajerial
                    </h1>

                    <!-- Filter Card -->
                    <div class="card card-filter shadow-sm mb-4">
                        <div class="card-body">
                            <form class="row g-2 align-items-end" method="get" id="filterForm">
                                <div class="col-md-4">
                                    <label for="filterNama" class="form-label mb-1">Cari Nama / NIP</label>
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                        class="form-control" id="filterNama" placeholder="Nama atau NIP…">
                                </div>
                                <div class="col-md-4">
                                    <label for="filterJobTitle" class="form-label mb-1">Job Title</label>
                                    <select name="job_title" id="filterJobTitle" class="form-select">
                                        <option value="">— Semua Job Title —</option>
                                        <?php foreach ($jobTitles as $jt): ?>
                                            <option value="<?= htmlspecialchars($jt['job_title']) ?>"
                                                <?= $jt['job_title'] === $jobTitle ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($jt['job_title']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-1"></i> Terapkan
                                    </button>
                                    <a href="manage_manajerial.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-undo"></i>
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Card Grid -->
                    <div class="row g-4">
                        <?php if ($res->num_rows === 0): ?>
                            <div class="col-12">
                                <div class="alert alert-info text-center">Tidak ada user manajerial ditemukan.</div>
                            </div>
                        <?php else: ?>
                            <?php while ($row = $res->fetch_assoc()):
                                $img = ($row['foto_profil'] && file_exists(__DIR__ . "/../uploads/profile_pics/{$row['foto_profil']}"))
                                    ? getBaseUrl() . "/uploads/profile_pics/{$row['foto_profil']}?v=" . filemtime(__DIR__ . "/../uploads/profile_pics/{$row['foto_profil']}")
                                    : getBaseUrl() . "/assets/img/undraw_profile.svg";
                            ?>
                                <div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex">
                                    <div class="card profile-card shadow-sm w-100">
                                        <div style="height:50px;"></div>
                                        <img src="<?= $img ?>" class="profile-avatar" alt="">
                                        <div class="card-body">
                                            <div class="card-title text-truncate"><?= htmlspecialchars($row['nama']) ?></div>
                                            <div class="card-subtitle mb-1"><?= htmlspecialchars($row['job_title'] ?? 'Manajerial') ?></div>
                                            <div class="info-group">NIP: <?= htmlspecialchars($row['nip']) ?></div>
                                            <div class="info-group mb-2"><?= htmlspecialchars($row['role'] ?? '-') ?></div>

                                            <button class="reset-btn" data-id="<?= $row['id'] ?>"
                                                data-nama="<?= htmlspecialchars($row['nama']) ?>">
                                                <i class="fas fa-key me-1"></i> Reset Password
                                            </button>
                                        </div>
                                        <div class="card-footer">
                                            Manajerial
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                    <!-- Pagination -->
                    <?php
                    $totalPages = ceil($totalRows / $perPage);
                    if ($totalPages > 1):
                    ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                        <a class="page-link"
                                            href="?search=<?= urlencode($search) ?>&job_title=<?= urlencode($jobTitle) ?>&page=<?= $p ?>">
                                            <?= $p ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>

                </div><!-- /.container-fluid -->
            </div><!-- /#content -->

            <footer class="sticky-footer bg-white">
                <div class="container my-auto text-center small">
                    &copy; <?= date("Y"); ?> Payroll Management System
                </div>
            </footer>
        </div><!-- /#content-wrapper -->
    </div><!-- /#wrapper -->

    <!-- JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(function() {
            // Handle reset password button
            $('.reset-btn').click(function(e) {
                e.preventDefault();
                let userId = $(this).data('id');
                let nama = $(this).data('nama');
                Swal.fire({
                    title: 'Reset Password?',
                    html: `Password <b>${nama}</b> akan di-reset menjadi <code>123456</code>.<br><small>User harus login ulang.</small>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Reset!',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#667eea'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post('manage_manajerial.php', {
                            reset_id: userId,
                            csrf_token: "<?= htmlspecialchars($csrf_token) ?>"
                        }, function(resp) {
                            if (resp.success) {
                                Swal.fire({
                                    icon: 'success',
                                    html: resp.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    html: resp.message
                                });
                            }
                        }, 'json');
                    }
                });
            });
        });
    </script>
</body>

</html>
<?php $conn->close(); ?>