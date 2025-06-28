<?php
// File: /sdm/includes/sistem_kelebihan_jam_mengajar.php
// Versi FINAL: draft & final dibedakan di kolom is_final
// Payroll hanya NARIK data final (is_final = 1), entry baru = is_final 0

require_once __DIR__ . '/../../helpers.php';
require_once __DIR__ . '/../../koneksi.php';

start_session_safe();
init_error_handling();
authorize(['M:SDM']);

header('Content-Type: application/json; charset=utf-8');

$nip    = $_SESSION['nip'] ?? '';
$action = $_POST['action'] ?? '';

/* ------------------------------------------------------------
 * Helper
 * ----------------------------------------------------------*/
function getTarif(mysqli $conn): float {
    $res = $conn->query("SELECT nominal FROM tarif_honor_jam_lebih WHERE id = 1");
    if (!$res) {
        log_error('[getTarif] ' . $conn->error);
        return 0;
    }
    return (float) ($res->fetch_assoc()['nominal'] ?? 0);
}

/* ------------------------------------------------------------
 * Aksi: ViewTarif
 * ----------------------------------------------------------*/
if ($action === 'ViewTarif') {
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        echo json_encode(['code' => 0, 'result' => ['nominal' => getTarif($conn)]]);
    } catch (Exception $e) {
        log_error('[ViewTarif] ' . $e->getMessage());
        echo json_encode(['code' => 1, 'result' => $e->getMessage()]);
    }
    exit;
}

/* ------------------------------------------------------------
 * Aksi: UpdateTarif
 * ----------------------------------------------------------*/
if ($action === 'UpdateTarif') {
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $nom = (float) ($_POST['nominal'] ?? 0);
        if ($nom <= 0) {
            throw new Exception('Nominal tidak valid');
        }
        $stmt = $conn->prepare('UPDATE tarif_honor_jam_lebih SET nominal = ? WHERE id = 1');
        if (!$stmt) {
            throw new Exception($conn->error);
        }
        $stmt->bind_param('d', $nom);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();

        add_audit_log($conn, $nip, 'UpdateTarif Jam Extra', 'Nominal jadi Rp' . number_format($nom, 0, ',', '.'));
        echo json_encode(['code' => 0, 'result' => 'Tarif diperbarui']);
    } catch (Exception $e) {
        log_error('[UpdateTarif] ' . $e->getMessage());
        echo json_encode(['code' => 1, 'result' => $e->getMessage()]);
    }
    exit;
}

/* ------------------------------------------------------------
 * Aksi: SaveDraft
 * Simpan/update jam ekstra sebagai DRAFT (is_final=0)
 * ----------------------------------------------------------*/
if ($action === 'SaveDraft') {
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');

        $entries = $_POST['jam_extra'] ?? [];
        $minggu  = (int) ($_POST['minggu_ke'] ?? 0);
        $bln     = (int) ($_POST['bulan'] ?? 0);
        $thn     = (int) ($_POST['tahun'] ?? 0);

        if (!$minggu || !$bln || !$thn) {
            throw new Exception('Periode tidak valid');
        }

        $tarif = getTarif($conn); // ambil tarif honor jam lebih SEKARANG

        $stmt = $conn->prepare(
            'INSERT INTO kelebihan_jam_mengajar
             (id_anggota, bulan, tahun, minggu_ke, jam_extra, total_honor, is_final)
             VALUES (?,?,?,?,?,?,0)
             ON DUPLICATE KEY UPDATE
               minggu_ke = VALUES(minggu_ke),
               jam_extra = VALUES(jam_extra),
               total_honor = VALUES(total_honor),
               is_final  = 0,
               updated_at = NOW()'
        );
        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $count = 0;
        foreach ($entries as $id => $j) {
    $jam = (float) $j;
    $total_honor = $jam * $minggu * $tarif; // DIKALI jumlah minggu!
    $stmt->bind_param('iiiidd', $id, $bln, $thn, $minggu, $jam, $total_honor);
    if (!$stmt->execute()) {
        log_error('[SaveDraft] ID ' . $id . ': ' . $stmt->error);
        throw new Exception('Gagal simpan ID ' . $id);
    }
    $count++;
}

        $stmt->close();

        add_audit_log($conn, $nip, 'SaveDraft Jam Extra', "Periode $bln/$thn minggu-$minggu, total $count entri");
        echo json_encode(['code' => 0, 'result' => 'Draft tersimpan']);
    } catch (Exception $e) {
        log_error('[SaveDraft] ' . $e->getMessage());
        echo json_encode(['code' => 1, 'result' => $e->getMessage()]);
    }
    exit;
}


/* ------------------------------------------------------------
 * Aksi: FinalizeJam
 * Tandai jam ekstra sebagai FINAL (is_final=1) untuk payroll (periode tsb)
 * ----------------------------------------------------------*/
if ($action === 'FinalizeJam') {
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $bln     = (int) ($_POST['bulan'] ?? 0);
        $thn     = (int) ($_POST['tahun'] ?? 0);
        $minggu  = (int) ($_POST['minggu_ke'] ?? 0);

        if (!$bln || !$thn) {
            throw new Exception('Periode tidak valid');
        }
        // Finalkan semua data jam_extra periode tsb
        $where_minggu = $minggu ? "AND minggu_ke = $minggu" : '';
        $sql = "UPDATE kelebihan_jam_mengajar
                   SET is_final = 1, updated_at = NOW()
                 WHERE bulan = ? AND tahun = ? $where_minggu";
        $stmt = $conn->prepare($minggu ?
            "UPDATE kelebihan_jam_mengajar SET is_final=1, updated_at=NOW() WHERE bulan=? AND tahun=? AND minggu_ke=?" :
            "UPDATE kelebihan_jam_mengajar SET is_final=1, updated_at=NOW() WHERE bulan=? AND tahun=?"
        );
        $minggu ? $stmt->bind_param('iii', $bln, $thn, $minggu) : $stmt->bind_param('ii', $bln, $thn);

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();
        add_audit_log($conn, $nip, 'FinalizeJam', "Finalisasi jam extra bulan $bln/$thn" . ($minggu ? " minggu $minggu" : ''));
        echo json_encode(['code' => 0, 'result' => 'Semua jam ekstra telah difinalkan!']);
    } catch (Exception $e) {
        log_error('[FinalizeJam] ' . $e->getMessage());
        echo json_encode(['code' => 1, 'result' => $e->getMessage()]);
    }
    exit;
}

/* ------------------------------------------------------------
 * Aksi: GetDataJam
 * Ambil semua data jam ekstra periode tertentu, termasuk status final/draft
 * ----------------------------------------------------------*/
if ($action === 'GetDataJam') {
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $bln     = (int) ($_POST['bulan'] ?? 0);
        $thn     = (int) ($_POST['tahun'] ?? 0);
        if (!$bln || !$thn) {
            throw new Exception('Periode tidak valid');
        }
        $where = "WHERE bulan=? AND tahun=?";
        $stmt = $conn->prepare("SELECT * FROM kelebihan_jam_mengajar $where ORDER BY id_anggota, minggu_ke");
        $stmt->bind_param('ii', $bln, $thn);
        $stmt->execute();
        $result = [];
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $result[] = $r;
        }
        $stmt->close();
        echo json_encode(['code' => 0, 'result' => $result]);
    } catch (Exception $e) {
        log_error('[GetDataJam] ' . $e->getMessage());
        echo json_encode(['code' => 1, 'result' => $e->getMessage()]);
    }
    exit;
}

/* ------------------------------------------------------------
 * Payroll akan mengambil jam ekstra yang sudah is_final = 1 (saat proses payroll)
 * ----------------------------------------------------------*/

/* ------------------------------------------------------------
 * Aksi tidak dikenal
 * ----------------------------------------------------------*/
log_error('[Unknown action] ' . $action);
echo json_encode(['code' => 1, 'result' => 'Aksi tidak dikenal']);
exit;
?>
