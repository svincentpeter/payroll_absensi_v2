<?php
/* =========================================================
 *  pustaka_feed.php  –  Kolektor data Notifikasi & Pesan
 *                      (re-usable untuk semua endpoint)
 * =========================================================*/
require_once __DIR__.'/helpers.php';


/* ---------------------------------------------------------
 *  Utility: cek apakah pesan sistem sudah “dibaca”
 *           (tabel msg_read  →  PRIMARY KEY(user_id,msg_id))
 * --------------------------------------------------------*/
function msg_dismissed(mysqli $c, int $uid, string $msgId): bool
{
    $st = $c->prepare("SELECT 1 FROM msg_read WHERE user_id=? AND msg_id=?");
    $st->bind_param('is',$uid,$msgId);
    $st->execute();
    $ok = (bool)$st->get_result()->fetch_row();
    $st->close();
    return $ok;
}

/* =========================================================
 *  COLLECT NOTIFICATIONS  (N-1 … N-12)
 * ========================================================*/
function collectNotifications(mysqli $conn): array
{
    $uid   = intval($_SESSION['user_id'] ?? 0);
    $nip   = $_SESSION['nip'] ?? '';
    $role  = getFullRole();

    $now   = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    $Y     = (int)$now->format('Y');
    $m     = (int)$now->format('n');
    $day   = (int)$now->format('j');
    $isMon = ($now->format('N') === '1');

    /* wadah pesan per-kategori */
    $msg = [
        'guru'=>[], 'kepsek'=>[], 'sdm'=>[],
        'keu'=>[],  'backup'=>[], 'system'=>[]
    ];

    /* ---------- N-1  izin ACC Kepsek menunggu SDM ---------- */
    if ($role === 'M:sdm') {
        $n = qCount($conn,
           "SELECT COUNT(*) cnt FROM pengajuan_ijin
             WHERE status_kepalasekolah='Diterima' AND status='Pending'");
        if ($n) $msg['sdm'][] = "$n izin menunggu diproses SDM.";
    }

/* ---------- N-2  izin Pending untuk Kepala Sekolah (per jenjang) ---------- */
$jobTitle = $_SESSION['job_title'] ?? '';
$kepsekJenjang = $_SESSION['jenjang'] ?? '';
if (
    $role === 'P'
    && stripos($jobTitle, 'kepala sekolah') !== false
    && $kepsekJenjang !== ''
) {
    $n = qCount(
        $conn,
        "SELECT COUNT(*) cnt
           FROM pengajuan_ijin pi
           JOIN anggota_sekolah a ON a.nip = pi.nip
          WHERE pi.status_kepalasekolah = 'Pending'
            AND a.jenjang = ?",
        's',
        [$kepsekJenjang]
    );
    if ($n) {
        $msg['kepsek'][] = "$n pengajuan izin menunggu persetujuan Anda.";
    }
}



    /* ---------- N-3  terlambat >=3× bulan ini ---------- */
    if (in_array($role,['P','TK'],true)) {
        $n = qCount($conn,
           "SELECT COUNT(*) cnt FROM absensi
             WHERE nip=? AND terlambat=1
               AND MONTH(tanggal)=? AND YEAR(tanggal)=?", 'sii',[$nip,$m,$Y]);
        if ($n >= 3) $msg['guru'][] = "Anda terlambat {$n}× bulan ini.";
    }

    /* ---------- N-4  jadwal piket 7 hari ke depan ---------- */
    if (in_array($role,['P','TK'],true)) {
        $n = qCount($conn,
           "SELECT COUNT(*) cnt FROM jadwal_piket
             WHERE nip=? AND tanggal BETWEEN CURDATE()
                                       AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)",
           's',[$nip]);
        if ($n) $msg['guru'][] = "Anda punya $n jadwal piket dalam 7 hari.";
    }

    /* ---------- N-5  payroll draft belum final ---------- */
    if ($role === 'M:sdm') {
        $n = qCount($conn,
           "SELECT COUNT(*) cnt FROM payroll
             WHERE bulan=? AND tahun=? AND status='draft'",'ii',[$m,$Y]);
        if ($n) $msg['sdm'][] = "$n payroll draft bulan "
              . getIndonesianMonthName($m) . " belum final.";
    }

    /* ---------- N-6  anggota belum dibayar ---------- */
    if (in_array($role,['M:keuangan','M:superadmin'],true)) {
        $n = qCount($conn,
           "SELECT COUNT(*) cnt
              FROM anggota_sekolah a
             WHERE NOT EXISTS(
                   SELECT 1 FROM payroll_final pf
                    WHERE pf.id_anggota=a.id AND pf.bulan=? AND pf.tahun=?)",
           'ii',[$m,$Y]);
        if ($n) $msg['keu'][] = "$n anggota belum ada payroll final bulan "
              . getIndonesianMonthName($m) . ".";
    }

    /* ---------- N-7  selisih hitung payroll >1000 ---------- */
    if (in_array($role,['M:keuangan','M:superadmin'],true)) {
        $n = qCount($conn,
           "SELECT COUNT(*) cnt FROM payroll
             WHERE ABS(
IFNULL(gaji_pokok,0)+IFNULL(total_pendapatan,0) -
(IFNULL(total_potongan,0)+IFNULL(potongan_koperasi,0)+IFNULL(gaji_bersih,0))
            )>1000");
        if ($n) $msg['keu'][] = "$n payroll terdeteksi selisih hitung.";
    }

    /* ---------- N-8  kontrak habis ≤30 hari ---------- */
    if ($role === 'M:sdm') {
        $n = qCount($conn,
           "SELECT COUNT(*) cnt FROM anggota_sekolah
             WHERE status_kerja='Kontrak'
               AND tgl_kontrak_selesai IS NOT NULL
               AND DATEDIFF(tgl_kontrak_selesai,CURDATE()) BETWEEN 0 AND 30");
        if ($n) $msg['sdm'][] = "$n kontrak kerja akan berakhir ≤30 hari.";
    }

    /* ---------- N-9  ingat upload rekap (Senin) ---------- */
    if ($role === 'M:sdm' && $isMon) {
        $msg['sdm'][] = "Upload rekap absensi minggu lalu hari ini.";
    }

    /* ---------- N-10  backup DB (tgl 1) ---------- */
    if ($role === 'M:superadmin' && $day === 1) {
        $dismissed = qCount($conn,
           "SELECT 1 cnt FROM backup_dismiss
             WHERE user_id=? AND yyyymm=?",
           'is',[$uid,$now->format('Ym')]);
        if (!$dismissed) $msg['backup'][] = "Ingat backup database.";
    }

    /* ---------- N-11  error log 24 jam ---------- */
    if ($role === 'M:superadmin') {
        $n = qCount($conn,
           "SELECT COUNT(*) cnt FROM audit_logs
             WHERE action LIKE '%error%'
               AND created_at>=DATE_SUB(NOW(),INTERVAL 1 DAY)");
        if ($n) $msg['system'][] = "$n log error 24 jam terakhir.";
    }

    /* ---------- N-12  notifikasi manual (table notifications) ---------- */
    $rt = strtolower($role);
    if (str_starts_with($rt,'m:')) $rt = substr($rt,2);   // buang prefix "m:"
    $st = $conn->prepare("
        SELECT id,title,message,notification_type,link,created_at
          FROM notifications
         WHERE is_read=0
           AND (role_target IN (?, 'all') OR user_id=?)
         ORDER BY priority, created_at DESC");
    $st->bind_param('si',$rt,$uid);
    $st->execute();
    $manual = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();

    $counter = array_map('count',$msg);
    $total   = array_sum($counter) + count($manual);

    return [
        'total'     => $total,
        'counter'   => $counter,
        'messages'  => $msg,
        'manual'    => $manual,
        'fullRole'  => $role,
        'generated' => $now->format('Y-m-d H:i:s')
    ];
}

/* =========================================================
 *  COLLECT MESSAGES  (P-1 … P-6)
 * ========================================================*/
function collectMessages(mysqli $conn): array
{
    $uid   = intval($_SESSION['user_id'] ?? 0);
    $nip   = $_SESSION['nip'] ?? '';
    $role  = getFullRole();
    $out   = [];

    /* ---------- P-5  pesan pribadi ---------- */
    $st = $conn->prepare("
        SELECT ls.id,ls.judul,ls.isi,ls.tanggal_keluar AS created,
               sender.nama AS sender_name,
               CONCAT('detail_surat.php?id=',ls.id) AS link
          FROM laporan_surat ls
          JOIN anggota_sekolah sender ON sender.id = ls.id_pengirim
         WHERE ls.id_penerima=? AND ls.is_read_receiver=0
         ORDER BY ls.tanggal_keluar DESC");
    $st->bind_param('i',$uid);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $r) { $r['source']='laporan'; $out[]=$r; }
    $st->close();

    /* ---------- P-6  broadcast manual sekali klik ---------- */
    $rt = strtolower($role);
    if (str_starts_with($rt,'m:')) $rt = substr($rt,2);
    $st = $conn->prepare("
        SELECT id,title AS judul,message AS isi,created_at AS created,
               'Broadcast' AS sender_name,link
          FROM notifications
         WHERE is_once=1 AND is_read=0
           AND (role_target IN (?, 'all') OR user_id=?)
         ORDER BY created_at DESC");
    $st->bind_param('si',$rt,$uid);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $r) { $r['source']='notif'; $out[]=$r; }
    $st->close();

    /* ---------- P-1  slip gaji bulan lalu ---------- */
    if (in_array($role,['P','TK'],true)) {
        $prev = new DateTimeImmutable('first day of -1 month');
        $bln  = (int)$prev->format('n');
        $thn  = (int)$prev->format('Y');
        $have = qCount($conn,
             "SELECT COUNT(*) cnt FROM payroll_final
               WHERE id_anggota=? AND bulan=? AND tahun=?",
             'iii',[$uid,$bln,$thn]);
        $msgId = "sys-slip-{$thn}{$bln}";
        if ($have && !msg_dismissed($conn,$uid,$msgId)) {
            $out[] = [
                'id'=>$msgId, 'judul'=>'Slip gaji sudah terbit',
                'isi'=>'Slip gaji bulan '.getIndonesianMonthName($bln).' telah tersedia.',
                'created'=>$prev->format('Y-m-d 00:00:00'),
                'sender_name'=>'Sistem',
                'link'=>"slip_gaji.php?bulan=$bln&tahun=$thn",
                'source'=>'system'
            ];
        }
    }

    /* ---------- P-2  tukar jadwal pending ---------- */
    if (in_array($role,['P','TK'],true)) {
        $st = $conn->prepare("
            SELECT id,tanggal_permintaan AS created
              FROM permintaan_tukar_jadwal
             WHERE nip_pengaju=? AND status='Pending'");
        $st->bind_param('s',$nip);
        $st->execute();
        foreach ($st->get_result() as $row) {
            $msgId="sys-tukar-{$row['id']}";
            if (msg_dismissed($conn,$uid,$msgId)) continue;
            $out[]=[
                'id'=>$msgId,'judul'=>'Permintaan tukar jadwal',
                'isi'=>'Permintaan tukar jadwal Anda masih “Pending”.',
                'created'=>$row['created'],
                'sender_name'=>'Sistem',
                'link'=>'tukar_jadwal.php',
                'source'=>'system'
            ];
        }
        $st->close();
    }

    /* ---------- P-3  backup DB (superadmin) ---------- */
    if ($role==='M:superadmin') {
        $msgId='sys-backup-'.date('Ymd');
        if (!msg_dismissed($conn,$uid,$msgId)) {
            $out[]=[
              'id'=>$msgId,'judul'=>'Backup database',
              'isi'=>'Segera lakukan backup DB hari ini.',
              'created'=>date('Y-m-d 00:00:00'),
              'sender_name'=>'Sistem','link'=>'backup_database.php',
              'source'=>'system'
            ];
        }
    }

    /* ---------- P-4  error log 24 jam ---------- */
    if ($role==='M:superadmin') {
        $n = qCount($conn,
           "SELECT COUNT(*) cnt FROM audit_logs
             WHERE action LIKE '%error%'
               AND created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)");
        if ($n) {
            $msgId='sys-audit-'.date('YmdHi');
            if (!msg_dismissed($conn,$uid,$msgId)) {
                $out[]=[
                  'id'=>$msgId,'judul'=>'Log error sistem',
                  'isi'=>"Terdapat $n error log 24 jam terakhir.",
                  'created'=>date('Y-m-d H:i:s'),
                  'sender_name'=>'Sistem','link'=>'audit_logs.php',
                  'source'=>'system'
                ];
            }
        }
    }

    /* ---------- sort terbaru ---------- */
    usort($out,fn($a,$b)=>strtotime($b['created'])<=>strtotime($a['created']));

    return [
        'total'=>count($out),
        'messages'=>$out,
        'generated'=>date('Y-m-d H:i:s')
    ];
}
