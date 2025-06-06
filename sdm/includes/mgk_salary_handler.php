<?php
/**
 *  mgk_salary_handler.php
 *  -------------------------------------------------------------
 *  Semua fungsi & helper gaji untuk halaman manage_guru_karyawan
 *
 *  • getStrataConfig()              → daftar strata valid / jenjang
 *  • fetchStrataValues()            → ambil gaji-pokok per strata dari DB
 *  • normalizePendidikan()          → standarisasi input “s-1”, “S1”, “Sarjana” → “S1”
 *  • getGajiPokokByRole()           → gaji default/role (tabel gaji_pokok_roles)
 *  • hitungGajiPokok()              → prioritas: tabel strata ⟹ gaji_pokok_roles
 *
 *  • getRecommendedSalaryIndex()    → salary-index ideal (berdasar tahun masa kerja)
 *  • updateSalaryIndexForUser()     → set salary_index_id + gaji_pokok (1 user)
 *  • updateSalaryIndexForAll()      → loop semua user
 *
 *  • updateGajiPokok()              → update tabel gaji_pokok_roles
 *  • updateGajiStrataGuru()/Karyawan→ insert-on-dup untuk per-strata
 *
 *  NB: Dibungkus `if (!function_exists())` agar aman bila di-include berkali-kali
 */

/* ================================================================
 * 1. Konfigurasi & Helper Strata
 * ================================================================ */
function getStrataConfig(mysqli $conn): array {
    // Ambil semua kode_jenjang aktif dari DB
    $jenjang = [];
    $res = $conn->query("SELECT kode_jenjang FROM jenjang_sekolah WHERE is_aktif=1 ORDER BY id");
    while ($r = $res->fetch_assoc()) {
        $jenjang[] = $r['kode_jenjang'];
    }
    // Strata bisa tetap (D3, S1, S2, S3), atau load dari tabel lain jika Anda punya tabel strata
    $strata = ['D3', 'S1', 'S2', 'S3'] ;
    return [
        'guru' => array_fill_keys($jenjang, $strata),
        'karyawan' => array_fill_keys($jenjang, $strata),
    ];
}




if (!function_exists('fetchStrataValues')) {
    /**
     * Ambil gaji per-strata → [jenjang][strata] => gaji
     * @param string $type  'guru' | 'karyawan'
     */
    function fetchStrataValues(mysqli $conn, string $type = 'guru'): array
    {
        $table = ($type === 'guru')
            ? 'gaji_pokok_strata_guru'
            : 'gaji_pokok_strata_karyawan';

        $out   = [];
        $sql   = "SELECT jenjang,strata,gaji_pokok FROM {$table}";
        $res   = $conn->query($sql);
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $out[$r['jenjang']][$r['strata']] = floatval($r['gaji_pokok']);
            }
        }
        return $out;
    }
}

if (!function_exists('normalizePendidikan')) {
    /**
     * Standarisasi string pendidikan – huruf besar tanpa spasi
     *   contoh: “s-1”, “sarjana”, “S1” → “S1”
     */
    function normalizePendidikan(string $pend): string
    {
        $p = strtoupper(preg_replace('/[^A-Z0-9]/', '', $pend));
        if (in_array($p, ['S1','S2','S3','D3','D4'])) return $p;
        if (str_starts_with($p, 'SARJANA'))           return 'S1';
        if (str_starts_with($p, 'MAGISTER'))          return 'S2';
        if (str_starts_with($p, 'DIPLOMA4'))          return 'D4';
        if (str_starts_with($p, 'DIPLOMA') ||
            str_starts_with($p, 'D3'))                return 'D3';
        return 'S1';    // default
    }
}

/* ================================================================
 * 2. Gaji Pokok
 * ================================================================ */
if (!function_exists('getGajiPokokByRole')) {
    /**
     * Ambil gaji pokok default berdasarkan role (guru|karyawan)
     */
    function getGajiPokokByRole(mysqli $conn, string $role): float
    {
        $lookup = ($role === 'P') ? 'guru' : 'karyawan';
        $gaji   = 0.0;

        $st = $conn->prepare("SELECT gaji_pokok FROM gaji_pokok_roles WHERE role=?");
        if ($st) {
            $st->bind_param("s", $lookup);
            $st->execute();
            $st->bind_result($gaji);
            $st->fetch();
            $st->close();
        }
        return floatval($gaji);
    }
}

if (!function_exists('hitungGajiPokok')) {
    /**
     * Prioritas:
     *   1. tabel gaji_pokok_strata_*  (matching jenjang + strata)
     *   2. fallback ke tabel gaji_pokok_roles
     */
    function hitungGajiPokok(
        mysqli $conn,
        string  $role,
        string  $pendidikan,
        string  $jenjang
    ): float {
        $strata = normalizePendidikan($pendidikan);

        // --- Karyawan / Manajerial ---
        if ($role !== 'P') {
            $q = "SELECT gaji_pokok FROM gaji_pokok_strata_karyawan
                  WHERE jenjang=? AND strata=? LIMIT 1";
            $st = $conn->prepare($q);
            if ($st) {
                $st->bind_param("ss", $jenjang, $strata);
                $st->execute();
                $st->bind_result($gaji);
                if ($st->fetch()) {
                    $st->close();
                    return floatval($gaji);
                }
                $st->close();
            }
            return getGajiPokokByRole($conn, $role);
        }

        // --- Guru ---
        $q = "SELECT gaji_pokok FROM gaji_pokok_strata_guru
              WHERE jenjang=? AND strata=? LIMIT 1";
        $st = $conn->prepare($q);
        if ($st) {
            $st->bind_param("ss", $jenjang, $strata);
            $st->execute();
            $st->bind_result($gaji);
            if ($st->fetch()) {
                $st->close();
                return floatval($gaji);
            }
            $st->close();
        }
        return getGajiPokokByRole($conn, $role);
    }
}

/* ================================================================
 * 3. Salary-Index
 * ================================================================ */
if (!function_exists('getRecommendedSalaryIndex')) {
    /**
     * Row salary_indices terdekat (atau null) menggunakan masa kerja efektif (float tahun)
     */
    function getRecommendedSalaryIndex(mysqli $conn, string $joinStart): ?array
{
    if (!$joinStart || $joinStart === '0000-00-00') return null;

    try {
        $start = new DateTime($joinStart);
        $now = new DateTime();
        $interval = $now->diff($start);
        $masaKerjaTahun = intval($interval->y); // gunakan tahun bulat saja!
    } catch (Exception $e) {
        $masaKerjaTahun = 0;
    }

    // SQL QUERY HARUS: min_years <= ? AND (max_years IS NULL OR max_years >= ?)
    $sql = "SELECT * FROM salary_indices
            WHERE min_years <= ?
              AND (max_years IS NULL OR max_years >= ?)
            ORDER BY min_years DESC
            LIMIT 1";
    $st = $conn->prepare($sql);
    if (!$st) {
        error_log("Error preparing statement: " . $conn->error);
        return null;
    }

    // Pastikan tipe data INT, pakai 'ii'
    $st->bind_param("ii", $masaKerjaTahun, $masaKerjaTahun);

    if (!$st->execute()) {
        error_log("Execute error: " . $conn->error);
        $st->close();
        return null;
    }

    $res = $st->get_result();
    $row = $res->fetch_assoc();
    $st->close();

    return $row ?: null;
}
function getRecommendedSalaryIndex(mysqli $conn, string $joinStart): ?array
{
    if (!$joinStart || $joinStart === '0000-00-00') return null;

    try {
        $start = new DateTime($joinStart);
        $now = new DateTime();
        $interval = $now->diff($start);
        $masaKerjaTahun = intval($interval->y); // gunakan tahun bulat saja!
    } catch (Exception $e) {
        $masaKerjaTahun = 0;
    }

    // SQL QUERY HARUS: min_years <= ? AND (max_years IS NULL OR max_years >= ?)
    $sql = "SELECT * FROM salary_indices
            WHERE min_years <= ?
              AND (max_years IS NULL OR max_years >= ?)
            ORDER BY min_years DESC
            LIMIT 1";
    $st = $conn->prepare($sql);
    if (!$st) {
        error_log("Error preparing statement: " . $conn->error);
        return null;
    }

    // Pastikan tipe data INT, pakai 'ii'
    $st->bind_param("ii", $masaKerjaTahun, $masaKerjaTahun);

    if (!$st->execute()) {
        error_log("Execute error: " . $conn->error);
        $st->close();
        return null;
    }

    $res = $st->get_result();
    $row = $res->fetch_assoc();
    $st->close();

    return $row ?: null;
}



if (!function_exists('updateSalaryIndexForUser')) {
    /**
     * Hitung salary_index & gaji_pokok **1 user**; return bool success
     */
    function updateSalaryIndexForUser(mysqli $conn, int $id): bool
{
    $st = $conn->prepare(
        "SELECT role, join_start, pendidikan, jenjang
         FROM anggota_sekolah WHERE id=? LIMIT 1"
    );
    if (!$st) {
        file_put_contents(__DIR__ . "/log_test.txt", "[ERR] Tidak bisa prepare anggota_sekolah id=$id\n", FILE_APPEND);
        return false;
    }
    $st->bind_param("i", $id);
    $st->execute();
    $info = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$info) {
        file_put_contents(__DIR__ . "/log_test.txt", "[ERR] Tidak dapat fetch anggota id=$id\n", FILE_APPEND);
        return false;
    }

    $idx = getRecommendedSalaryIndex($conn, $info['join_start']);
    if (!$idx) {
        file_put_contents(__DIR__ . "/log_test.txt", "[ERR] Tidak dapat getRecommendedSalaryIndex untuk id=$id, join_start=" . $info['join_start'] . "\n", FILE_APPEND);
        return false;
    }

    $gajiPokok = hitungGajiPokok($conn, $info['role'], $info['pendidikan'], $info['jenjang']);

$u = $conn->prepare(
    "UPDATE anggota_sekolah
        SET salary_index_id   = ?,
            salary_index_level = ?,
            gaji_pokok         = ?
      WHERE id = ?"
);
    if (!$u) {
        file_put_contents(__DIR__ . "/log_test.txt", "[ERR] Tidak bisa prepare UPDATE anggota_sekolah id=$id\n", FILE_APPEND);
        return false;
    }
    $u->bind_param(
        "isdi",
        $idx['id'],
        $idx['level'],
        $gajiPokok,
        $id
    );
    $ok = $u->execute();
    if (!$ok) {
        file_put_contents(__DIR__ . "/log_test.txt", "[ERR] Gagal UPDATE anggota_sekolah id=$id : " . $conn->error . "\n", FILE_APPEND);
    }
    $u->close();
    return $ok;
}

}

if (!function_exists('updateSalaryIndexForAll')) {
    function updateSalaryIndexForAll(mysqli $conn): bool {
        $allOk = true;
        $res = $conn->query("SELECT id FROM anggota_sekolah WHERE is_delete=0");
        if (!$res) {
            log_error("Query gagal pada updateSalaryIndexForAll: " . $conn->error);
            return false;
        }
        while ($row = $res->fetch_assoc()) {
            $ok = updateSalaryIndexForUser($conn, intval($row['id']));
            if (!$ok) {
                // LOG lebih detail user dan data terkait
                $log = date('Y-m-d H:i:s') . " | ERROR updateSalaryIndexForUser id={$row['id']}\n";
                file_put_contents(__DIR__ . "/log_test.txt", $log, FILE_APPEND);

                // Debug: Tambah detail data anggota
                $stmt = $conn->prepare("SELECT id, nama, role, join_start, pendidikan, jenjang FROM anggota_sekolah WHERE id=?");
                $stmt->bind_param("i", $row['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $anggota = $result->fetch_assoc();
                $stmt->close();
                file_put_contents(__DIR__ . "/log_test.txt", "[DATA] " . json_encode($anggota) . "\n", FILE_APPEND);

                $allOk = false;
            }
        }
        return $allOk;
    }
}


/* ================================================================
 * 4. Admin – Update tabel Gaji Pokok
 * ================================================================ */
if (!function_exists('updateGajiPokok')) {
    /**
     * Versi “controller” – membaca $_POST['gaji_pokok_guru/karyawan']
     *  agar kompatibel dengan manage_guru_karyawan.php
     */
    function updateGajiPokok(mysqli $conn): void
    {
        $gajiGuru = floatval($_POST['gaji_pokok_guru']     ?? 0);
        $gajiKar  = floatval($_POST['gaji_pokok_karyawan'] ?? 0);

        $q  = "UPDATE gaji_pokok_roles SET gaji_pokok=? WHERE role=?";
        $st1= $conn->prepare($q);
        $st2= $conn->prepare($q);
        if (!$st1 || !$st2) send_response(1,'Query error.');

        $role1 = 'guru';
        $st1->bind_param("ds",$gajiGuru,$role1);
        $ok1 = $st1->execute();
        $st1->close();

        $role2 = 'karyawan';
        $st2->bind_param("ds",$gajiKar,$role2);
        $ok2 = $st2->execute();
        $st2->close();

        ($ok1 && $ok2)
            ? send_response(0,'Gaji pokok berhasil di-update.')
            : send_response(1,'Gagal update gaji pokok.');
    }
}

if (!function_exists('updateGajiStrataGuru')) {
    function updateGajiStrataGuru(mysqli $conn): void
    {
        $cfg = getStrataConfig($conn)['guru'];  // Perbaikan: kirim $conn
        $updates = [];
        foreach ($cfg as $jenjang => $arr) {
            foreach ($arr as $strata) {
                $field = strtolower(str_replace('/','',$jenjang)).'_'.strtolower($strata);
                $gaji  = floatval($_POST[$field] ?? 0);
                $updates[] = [$jenjang,$strata,$gaji];
            }
        }
        $ok = _runStrataUpdate($conn,'guru',$updates);
        $ok
          ? send_response(0,'Gaji strata Guru berhasil diperbarui.')
          : send_response(1,'Sebagian data Guru gagal diperbarui.');
    }
}

if (!function_exists('updateGajiStrataKaryawan')) {
    function updateGajiStrataKaryawan(mysqli $conn): void
    {
        $cfg = getStrataConfig($conn)['karyawan'];  // Perbaikan: kirim $conn
        $updates = [];
        foreach ($cfg as $jenjang => $arr) {
            foreach ($arr as $strata) {
                $field = strtolower(str_replace('/','',$jenjang)).'_'.strtolower($strata);
                $gaji  = floatval($_POST[$field] ?? 0);
                $updates[] = [$jenjang,$strata,$gaji];
            }
        }
        $ok = _runStrataUpdate($conn,'karyawan',$updates);
        $ok
          ? send_response(0,'Gaji strata Karyawan berhasil diperbarui.')
          : send_response(1,'Sebagian data Karyawan gagal diperbarui.');
    }
}


if (!function_exists('syncAllGajiPokokToStrata')) {
    /**
     * Update massal gaji pokok semua anggota agar sesuai strata & jenjang
     */
    function syncAllGajiPokokToStrata(mysqli $conn): array
    {
        $res = $conn->query("SELECT id, role, pendidikan, jenjang FROM anggota_sekolah WHERE is_delete=0");
        if (!$res) return ['ok'=>false, 'total'=>0, 'updated'=>0];
        $total = 0;
        $updated = 0;
        while ($row = $res->fetch_assoc()) {
            $total++;
            $gaji_pokok = hitungGajiPokok($conn, $row['role'], $row['pendidikan'], $row['jenjang']);
            $u = $conn->prepare("UPDATE anggota_sekolah SET gaji_pokok=? WHERE id=?");
            if ($u) {
                $u->bind_param("di", $gaji_pokok, $row['id']);
                if ($u->execute()) $updated++;
                $u->close();
            }
        }
        return ['ok'=>true, 'total'=>$total, 'updated'=>$updated];
    }
}


/* ================================================================
 * 5. Util private
 * ================================================================ */
if (!function_exists('_runStrataUpdate')) {
    /**
     * Helper internal untuk insert-on-duplicate per strata
     */
    function _runStrataUpdate(mysqli $conn, string $type, array $rows): bool
    {
        $tbl = ($type==='guru')
            ? 'gaji_pokok_strata_guru'
            : 'gaji_pokok_strata_karyawan';

        $st = $conn->prepare(
            "INSERT INTO {$tbl} (jenjang,strata,gaji_pokok)
             VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE gaji_pokok = VALUES(gaji_pokok)"
        );
        if (!$st) return false;

        $all = true;
        foreach ($rows as [$jenjang,$strata,$gaji]) {
            $st->bind_param("ssd",$jenjang,$strata,$gaji);
            if (!$st->execute()) $all = false;
        }
        $st->close();
        return $all;
    }
}
}