<?php

/************************************************************
 *  pesan.php – Pesan “sekali‑baca” (P‑1 … P‑6)
 ************************************************************/
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/koneksi.php';

start_session_safe();
generate_csrf_token();

$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$nip    = $_SESSION['nip']     ?? '';
$role   = getFullRole();

/* =========================================================
 * ENDPOINT  markRead  (laporan | notif | system)
 * =========================================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'markRead') {

    $id     = $_POST['id']     ?? '';
    $source = $_POST['source'] ?? '';

    /* -------- a. pesan pribadi (laporan_surat) -------- */
    if ($source === 'laporan' && ctype_digit($id)) {
        $st = $conn->prepare("UPDATE laporan_surat SET is_read_receiver = 1 WHERE id = ? AND id_penerima = ?");
        $st->bind_param('ii', $id, $userId);
        $st->execute();
        $st->close();
        send_response(0, 'OK');
    }
    

    /* -------- b. pesan broadcast manual (notifications) -------- */
    if ($source === 'notif' && ctype_digit($id)) {
        $st = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=?");
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        send_response(0, 'OK');
    }

    /* -------- c. pesan sistem (id = sys‑…  hanya di‑session) -------- */
    if ($source === 'system') {
        $_SESSION['msg_read'][$id] = true;          // tandai sudah dibaca
        send_response(0, 'OK');
    }
    send_response(1, 'Bad parameters');
}

/* =========================================================
 *  HELPER: qCount & dismissed()
 * =========================================================*/
function qCount(mysqli $c, string $sql, string $t = '', array $p = []): int
{
    $st = $c->prepare($sql);
    if (!$st) return 0;
    if ($t) $st->bind_param($t, ...$p);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();
    return intval($r['cnt'] ?? 0);
}
function dismissed(string $id): bool
{
    return !empty($_SESSION['msg_read'][$id]);
}

/* =========================================================
 *  CORE: collectMessages()
 * =========================================================*/
function collectMessages(mysqli $conn): array
{

    $uid   = $_SESSION['user_id'] ?? 0;
    $nip   = $_SESSION['nip']     ?? '';
    $role  = getFullRole();

    $msgs = [];

    /* ------------------------------------------------------
 *  P‑5 – Pesan pribadi  (laporan_surat)
 * -----------------------------------------------------*/
    $st = $conn->prepare("
SELECT  ls.id            AS id,
        ls.judul         AS judul,
        ls.isi           AS isi,
        ls.tanggal_keluar AS created,
        sender.nama      AS sender_name,
        CONCAT('detail_surat.php?id=', ls.id) AS link
FROM laporan_surat ls
LEFT JOIN anggota_sekolah sender ON sender.id = ls.id_pengirim
WHERE ls.id_penerima = ? AND ls.is_read_receiver = 0
ORDER BY ls.tanggal_keluar DESC
");
    $st->bind_param('i', $uid);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['source'] = 'laporan';
        $msgs[] = $row;
    }
    $st->close();

    /* ------------------------------------------------------
 *  P‑6 – Broadcast manual sekali klik (notifications)
 * -----------------------------------------------------*/
    $st = $conn->prepare("
SELECT  n.id           AS id,
        n.title        AS judul,
        n.message      AS isi,
        n.created_at   AS created,
        'Broadcast'    AS sender_name,
        n.link         AS link
FROM notifications n
WHERE n.is_once = 1
  AND n.is_read = 0
  AND (n.role_target IN (?, 'all') OR n.user_id = ?)
ORDER BY n.created_at DESC
");
    $rt = strtolower($role);
    $st->bind_param('si', $rt, $uid);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['source'] = 'notif';
        $msgs[] = $row;
    }
    $st->close();

    /* ------------------------------------------------------
     *  P‑1  –  Slip gaji bulan sebelumnya (guru/karyawan)
     * -----------------------------------------------------*/
    if (in_array($role, ['P', 'TK'])) {
        $prev = new DateTimeImmutable('first day of -1 month');
        $bln  = intval($prev->format('n'));
        $thn  = intval($prev->format('Y'));

        $pf = qCount(
            $conn,
            "SELECT COUNT(*) cnt FROM payroll_final
               WHERE id_anggota=? AND bulan=? AND tahun=?",
            'iii',
            [$uid, $bln, $thn]
        );

        $id = "sys-slip-{$thn}{$bln}";
        if ($pf && !dismissed($id)) {
            $msgs[] = [
                'id'          => $id,
                'judul'       => 'Slip gaji sudah terbit',
                'isi'         => 'Slip gaji bulan ' . getIndonesianMonthName($bln) . ' telah tersedia.',
                'created'     => $prev->format('Y-m-d 00:00:00'),
                'sender_name' => 'Sistem',
                'link'        => "slip_gaji.php?bulan={$bln}&tahun={$thn}",
                'source'      => 'system'
            ];
        }
    }

    /* ------------------------------------------------------
     *  P‑2  –  Permintaan tukar jadwal masih Pending (guru)
     * -----------------------------------------------------*/
    if (in_array($role, ['P', 'TK'])) {
        $st = $conn->prepare("
            SELECT id, tanggal_permintaan AS created
            FROM permintaan_tukar_jadwal
            WHERE nip_pengaju=? AND status='Pending'");
        $st->bind_param('s', $nip);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
        foreach ($rows as $r) {
            $id_msg = "sys-tukar-{$r['id']}";
            if (dismissed($id_msg)) continue;
            $msgs[] = [
                'id'          => $id_msg,
                'judul'       => 'Permintaan tukar jadwal',
                'isi'         => 'Permintaan tukar jadwal Anda masih “Pending”.',
                'created'     => $r['created'],
                'sender_name' => 'Sistem',
                'link'        => 'tukar_jadwal.php',
                'source'      => 'system'
            ];
        }
    }
    

    /* ------------------------------------------------------
     *  P‑3  –  Reminder Backup DB (superadmin, sekali klik)
     * -----------------------------------------------------*/
    if ($role === 'M:superadmin') {
        $id = 'sys-backup-' . date('Ymd');
        if (!dismissed($id)) {
            $msgs[] = [
                'id' => $id,
                'judul' => 'Backup database',
                'isi' => 'Segera lakukan backup DB hari ini.',
                'created' => date('Y-m-d 00:00:00'),
                'sender_name' => 'Sistem',
                'link' => 'backup_database.php',
                'source' => 'system'
            ];
        }
    }

    /* ------------------------------------------------------
     *  P‑4  –  Audit log error (superadmin, sekali klik)
     * -----------------------------------------------------*/
    if ($role === 'M:superadmin') {
        $err = qCount(
            $conn,
            "SELECT COUNT(*) cnt FROM audit_logs
             WHERE action LIKE '%error%' AND created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)"
        );
        if ($err) {
            $id = 'sys-audit-' . date('YmdHi');
            if (!dismissed($id)) {
                $msgs[] = [
                    'id' => $id,
                    'judul' => 'Log error sistem',
                    'isi' => "Terdapat {$err} error log 24 jam terakhir.",
                    'created' => date('Y-m-d H:i:s'),
                    'sender_name' => 'Sistem',
                    'link' => 'audit_logs.php',
                    'source' => 'system'
                ];
            }
        }
    }

    /* ----------- sort semua pesan paling baru di atas ----------- */
    usort($msgs, function ($a, $b) {
        return strtotime($b['created']) <=> strtotime($a['created']);
    });

    return [
        'total' => count($msgs),
        'messages' => $msgs,
        'generated' => date('Y-m-d H:i:s')
    ];
}

/* =========================================================
 *  OUTPUT  (JSON vs HTML)
 * =========================================================*/
$isAjax = (isset($_GET['ajax']) && $_GET['ajax'] == '1') ||
    (isset($_SERVER['HTTP_ACCEPT']) &&
        str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

$data = collectMessages($conn);
$conn->close();

if ($isAjax) {
    header('Content-Type:application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

extract($data);   // $total, $messages …
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Pesan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>

<body>
    <div id="message-container" class="container my-4">
        <h2>Pesan Anda</h2>

        <?php if ($total): ?>
            <div class="list-group">
                <?php foreach ($messages as $m): ?>
                    <a href="<?= htmlspecialchars($m['link'] ?? '#'); ?>"
                        class="list-group-item list-group-item-action message-item"
                        data-id="<?= htmlspecialchars($m['id']); ?>"
                        data-source="<?= htmlspecialchars($m['source']); ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1"><?= htmlspecialchars($m['sender_name']); ?></h5>
                            <small class="timestamp" data-timestamp="<?= htmlspecialchars($m['created']); ?>"></small>
                        </div>
                        <p class="mb-1"><?= htmlspecialchars($m['isi']); ?></p>
                        <small><?= htmlspecialchars($m['judul']); ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> Tidak ada pesan baru.</div>
        <?php endif; ?>

        <div class="mt-4">
            <span class="badge bg-primary">Total Pesan: <?= $total; ?></span>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
    <script>
        $(function() {
            const updTime = () => $('.timestamp').each(function() {
                const ts = $(this).data('timestamp');
                if (ts) $(this).text(moment(ts, "YYYY-MM-DD HH:mm:ss").fromNow());
            });
            updTime();

            setInterval(() => $("#message-container")
                .load("pesan.php #message-container", updTime), 30000);

            $(document).on('click', '.message-item', function() {
                const id = $(this).data('id');
                const src = $(this).data('source');
                const $item = $(this);
                $.post("pesan.php", {
                        action: 'markRead',
                        id: id,
                        source: src
                    },
                    resp => {
                        if (resp.code === 0) {
                            $item.fadeOut(300, () => $item.remove());
                        }
                    },
                    'json');
            });
        });
    </script>
</body>

</html>