<?php
// File: /payroll_absensi_v2/sdm/manage_guru_karyawan.php

require_once __DIR__ . '/../helpers.php';
start_session_safe();
init_error_handling();
authorize(['M:SDM', 'M:Superadmin'], '/payroll_absensi_v2/login.php');
$jenjangList = getOrderedJenjang();
require_once __DIR__ . '/../koneksi.php';

// --- TAMBAHKAN: Baca data gaji pokok strata dari database ---
// Konfigurasi untuk Guru (sesuai kebutuhan bisnis)
$guruConfig = [
    'TK'      => ['D3', 'S1', 'S2'],
    'SD'      => ['S1', 'S2'],
    'SMP'     => ['S1', 'S2'],
    'SMA/SMK' => ['S1', 'S2', 'S3']
];
// Konfigurasi untuk Karyawan
$karyawanConfig = [
    'TK'  => ['D3'],
    'SD'  => ['S1'],
    'SMP' => ['S2']
];

$guruStrata = [];
$sqlGuru = "SELECT * FROM gaji_pokok_strata_guru";
$resultGuru = mysqli_query($conn, $sqlGuru);
if ($resultGuru) {
    while ($row = mysqli_fetch_assoc($resultGuru)) {
        // Simpan dengan key: jenjang => strata => gaji_pokok
        $guruStrata[$row['jenjang']][$row['strata']] = $row['gaji_pokok'];
    }
}

$karyawanStrata = [];
$sqlKaryawan = "SELECT * FROM gaji_pokok_strata_karyawan";
$resultKaryawan = mysqli_query($conn, $sqlKaryawan);
if ($resultKaryawan) {
    while ($row = mysqli_fetch_assoc($resultKaryawan)) {
        $karyawanStrata[$row['jenjang']][$row['strata']] = $row['gaji_pokok'];
    }
}

/* -------------------------------------------------------------
 *  UTILITAS kecil : tambah‑bulan & hitung masa kerja efektif
 * ----------------------------------------------------------- */
/**
 * hitungGajiPokok()
 * Mengembalikan nilai gaji pokok berdasarkan role, pendidikan, dan jenjang.
 * - Jika role adalah 'P' (guru), ambil data dari tabel gaji_pokok_strata_guru.
 * - Jika role bukan 'P' (misalnya 'TK' untuk karyawan), ambil data dari gaji_pokok_strata_karyawan.
 *
 * @param mysqli $conn    Koneksi database
 * @param string $role    Role user, misalnya 'P' atau 'TK'
 * @param string $pendidikan  Pendidikan user, misalnya 'S1', 'D3' dll.
 * @param string $jenjang Jenjang sekolah (misalnya 'SD', 'SMP', 'SMA/SMK', atau 'TK')
 * @return float  Nilai gaji pokok
 */
function hitungGajiPokok($conn, $role, $pendidikan, $jenjang)
{
    // Jika role bukan 'P', gunakan tabel gaji_pokok_strata_karyawan
    if ($role !== 'P') {
        $strata = normalizePendidikan($pendidikan);
        $query = "SELECT gaji_pokok FROM gaji_pokok_strata_karyawan WHERE jenjang=? AND strata=? LIMIT 1";
        $st = $conn->prepare($query);
        if ($st) {
            $st->bind_param('ss', $jenjang, $strata);
            $st->execute();
            $g = 0; // inisialisasi variabel $g sebelum bind_result
            $st->bind_result($g);
            $st->fetch();
            $st->close();
            // Jika tidak ada data, fallback ke gaji pokok default berdasarkan role
            return ($g > 0) ? floatval($g) : getGajiPokokByRole($conn, $role);
        } else {
            return getGajiPokokByRole($conn, $role);
        }
    }

    // Jika role adalah 'P', ambil dari tabel gaji_pokok_strata_guru
    $strata = normalizePendidikan($pendidikan);
    $query = "SELECT gaji_pokok FROM gaji_pokok_strata_guru WHERE jenjang=? AND strata=? LIMIT 1";
    $st = $conn->prepare($query);
    if ($st) {
        $st->bind_param('ss', $jenjang, $strata);
        $st->execute();
        $g = 0; // inisialisasi variabel $g
        $st->bind_result($g);
        $st->fetch();
        $st->close();
        return ($g > 0) ? floatval($g) : getGajiPokokByRole($conn, $role);
    } else {
        return getGajiPokokByRole($conn, $role);
    }
}
function addMonths(string $date, int $months): string
{
    try {
        $d = new DateTime($date);
        $d->modify("+{$months} months")->modify('-1 day');   // kontrak berakhir H‑1
        return $d->format('Y-m-d');
    } catch (Exception $e) {
        return '0000-00-00';
    }
}

function calcMasaKerja(string $join_start): array
{
    if (!$join_start || $join_start === '0000-00-00') {
        return [0, 0, 0.00];
    }
    try {
        $start = new DateTime($join_start);
        $now   = new DateTime();
        $diff  = $now->diff($start);
        $tahun = (int)$diff->y;
        $bulan = (int)$diff->m;
        $efektif = round($tahun + $bulan / 12, 2);
        return [$tahun, $bulan, $efektif];
    } catch (Exception $e) {
        return [0, 0, 0.00];
    }
}
// Hasilkan CSRF token
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

// =============================================================================
// Handle AJAX Requests
// =============================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    switch ($_POST['case']) {
        case 'LoadingGuru':
            LoadingGuru($conn);
            break;
        case 'CreateGuru':
            CreateGuru($conn);
            break;
        case 'GetGuruDetail':
            GetGuruDetail($conn);
            break;
        case 'UpdateGuru':
            UpdateGuru($conn);
            break;
        case 'DeleteGuru':
            DeleteGuru($conn);
            break;
        case 'update_gaji_pokok':
            updateGajiPokok($conn);
            break;
        case 'update_gaji_strata_guru':
            updateGajiStrataGuru($conn);
            break;
        case 'update_gaji_strata_karyawan':
            updateGajiStrataKaryawan($conn);
            break;
        case 'GetRecommendedSalaryIndex':
            $joinStart = $_POST['join_start'] ?? '';
            send_response(0, getRecommendedSalaryIndex($conn, $joinStart));
            break;
        case 'update_salary_index_all':
            if (updateSalaryIndexForAll($conn)) {
                send_response(0, 'Salary index untuk semua user berhasil diperbarui.');
            } else {
                send_response(1, 'Gagal memperbarui salary index.');
            }
            break;
        default:
            send_response(400, 'Case tidak valid.');
    }
    exit();
}

// =============================================================================
// Fungsi-Fungsi CRUD
// =============================================================================
/* =============================================================
 * 1. Fungsi LoadingGuru: Mengambil data anggota untuk grid
 * =========================================================== */
function LoadingGuru($conn)
{
    $start   = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length  = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search  = isset($_POST['search']['value']) ? bersihkan_input($_POST['search']['value']) : '';
    $jenjang = isset($_POST['jenjang']) ? bersihkan_input($_POST['jenjang']) : '';
    $role    = isset($_POST['role']) ? bersihkan_input($_POST['role']) : '';
    $status  = isset($_POST['status_kerja']) ? bersihkan_input($_POST['status_kerja']) : '';

    // Hitung total data anggota yang belum dihapus (is_delete = 0)
    $rowTotal = mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT COUNT(*) AS total FROM anggota_sekolah WHERE is_delete = 0")
    );
    $recordsTotal = $rowTotal['total'] ?? 0;

    // Query data anggota yang belum dihapus
    $sql = "SELECT id, uid, nip, nama, jenjang, job_title, role, status_kerja,
                   join_start, lama_kontrak, tgl_kontrak_selesai, masa_kerja_tahun,
                   masa_kerja_bulan, masa_kerja_efektif, pendidikan, email, no_hp
            FROM anggota_sekolah
            WHERE is_delete = 0";

    if ($search)  $sql .= " AND (nip LIKE '%$search%' OR nama LIKE '%$search%')";
    if ($jenjang) $sql .= " AND jenjang = '$jenjang'";
    if ($role)    $sql .= " AND role = '$role'";
    if ($status)  $sql .= " AND status_kerja = '$status'";

    $sql .= " ORDER BY id DESC LIMIT $start, $length";
    $res = mysqli_query($conn, $sql);

    $data = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $masa = $row['masa_kerja_tahun'] . ' Thn ' . $row['masa_kerja_bulan'] . ' Bln';
        $data[] = [
            "id"                  => $row['id'],
            "uid"                 => htmlspecialchars($row['uid']),
            "nip"                 => htmlspecialchars($row['nip']),
            "nama"                => htmlspecialchars($row['nama']),
            "jenjang"             => $row['jenjang'],
            "job_title"           => htmlspecialchars($row['job_title']),
            "role"                => $row['role'],
            "status_kerja"        => $row['status_kerja'],
            "join_start"          => $row['join_start'],
            "lama_kontrak"        => $row['lama_kontrak'],
            "tgl_kontrak_selesai" => $row['tgl_kontrak_selesai'],
            "masa_kerja"          => $masa,
            "pendidikan"          => htmlspecialchars($row['pendidikan']),
            "email"               => htmlspecialchars($row['email']),
            "no_hp"               => htmlspecialchars($row['no_hp']),
            "foto_profil"         => getProfilePhotoUrl($row['nama'], $row['jenjang'], $row['role'], $row['id'])
        ];
    }

    echo json_encode(["recordsTotal" => $recordsTotal, "data" => $data], JSON_UNESCAPED_UNICODE);
    exit();
}

/* =============================================================
 * 2. Fungsi CreateGuru: Insert data baru ke anggota_sekolah
 * =========================================================== */
function CreateGuru(mysqli $conn)
{
    /* ---------- 1. Ambil & sanitasi input ---------- */
    $nip        = bersihkan_input($_POST['nip']         ?? '');
    $nama       = bersihkan_input($_POST['nama']        ?? '');
    $jenjang    = bersihkan_input($_POST['jenjang']     ?? '');
    $job_title  = bersihkan_input($_POST['job_title']   ?? '');
    $role       = bersihkan_input($_POST['role']        ?? '');
    $pendidikan = bersihkan_input($_POST['pendidikan']  ?? '');
    $jk         = bersihkan_input($_POST['jk']          ?? '');
    $tgl_lahir  = bersihkan_input($_POST['tgl_lahir']   ?? '');
    $usia       = intval($_POST['usia'] ?? 0);
    $religion   = bersihkan_input($_POST['religion']    ?? '');
    $alamat_domisili = bersihkan_input($_POST['alamat_domisili'] ?? '');
    $alamat_ktp      = bersihkan_input($_POST['alamat_ktp']      ?? '');
    $no_rekening     = bersihkan_input($_POST['no_rekening']     ?? '');
    $no_hp           = bersihkan_input($_POST['no_hp']           ?? '');
    $email           = bersihkan_input($_POST['email']           ?? '');

    /* pasangan & anak */
    $nama_pasangan   = bersihkan_input($_POST['nama_pasangan']   ?? '');
    $jumlah_anak     = intval($_POST['jumlah_anak'] ?? 0);
    $nama_anak_1     = bersihkan_input($_POST['nama_anak_1']     ?? '');
    $nama_anak_2     = bersihkan_input($_POST['nama_anak_2']     ?? '');
    $nama_anak_3     = bersihkan_input($_POST['nama_anak_3']     ?? '');

    $status_perkawinan = bersihkan_input($_POST['status_perkawinan'] ?? '');
    $remark            = bersihkan_input($_POST['remark']            ?? '');

    /* default pasangan/anak kalau belum menikah */
    if ($status_perkawinan === 'Belum Menikah') {
        $nama_pasangan = '-';
        $jumlah_anak   = 0;
        $nama_anak_1 = $nama_anak_2 = $nama_anak_3 = '-';
    }

    /* validasi wajib */
    if (empty($nip) || empty($nama) || empty($jenjang) || empty($role)) {
        send_response(1, 'NIP, Nama, Jenjang, dan Role wajib diisi.');
    }

    /* ---------- 2. Cek duplikasi NIP ---------- */
    $stmtDup = $conn->prepare("SELECT id FROM anggota_sekolah WHERE nip=?");
    $stmtDup->bind_param('s', $nip);
    $stmtDup->execute();
    if ($stmtDup->get_result()->num_rows) {
        send_response(1, 'NIP sudah digunakan.');
    }
    $stmtDup->close();

    /* ---------- 3. Hitung status & masa kerja ---------- */
    $uid           = generateUID($conn);
    $status_kerja  = bersihkan_input($_POST['status_kerja'] ?? 'Tetap');
    $join_start    = bersihkan_input($_POST['join_start']   ?? '');
    $lama_kontrak  = ($status_kerja === 'Kontrak') ? intval($_POST['lama_kontrak'] ?? 12) : null;
    $tgl_kontrak_selesai = ($status_kerja === 'Kontrak' && $join_start)
        ? addMonths($join_start, $lama_kontrak) : null;

    [$masa_kerja_tahun, $masa_kerja_bulan, $masa_eff] = calcMasaKerja($join_start);

    /* ---------- 4. Hitung gaji pokok ---------- */
    $gaji_pokok = hitungGajiPokok($conn, $role, $pendidikan, $jenjang);

    /* ---------- 5. Persiapan INSERT ---------- */
    $defaultPassword = password_hash('123456', PASSWORD_DEFAULT);
    $salary_index_id = null;                // di‑isi NULL dulu, nanti di‑update

    $sql = "INSERT INTO anggota_sekolah (
              uid, nip, password, nama, jenjang, job_title, status_kerja,
              join_start, masa_kerja_tahun, masa_kerja_bulan, masa_kerja_efektif,
              remark, jenis_kelamin, tanggal_lahir, usia,
              agama, alamat_domisili, alamat_ktp,
              no_rekening, no_hp, pendidikan,
              status_perkawinan, email, nama_pasangan, jumlah_anak,
              nama_anak_1, nama_anak_2, nama_anak_3,
              salary_index_id, gaji_pokok, role
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,
                      ?,?,?,?, ?,?,?,?,?,?,
                      ?,?,?,?, ?,?,?, ?,?,?)";

    /* urutkan variabel persis seperti kolom */
    $stmt = $conn->prepare($sql);

    $types = "ssssssss"     // 1‑8  string
        . "ii"           // 9‑10 int
        . "d"            // 11   double
        . "sss"          // 12‑14 string
        . "i"            // 15   int
        . "ssssss"       // 16‑21 string
        . "sss"          // 22‑24 string
        . "i"            // 25   int
        . "sss"          // 26‑28 string
        . "i"            // 29   int (nullable)
        . "d"            // 30   double
        . "s";           // 31   string

    $stmt->bind_param(
        $types,
        $uid,
        $nip,
        $defaultPassword,
        $nama,
        $jenjang,
        $job_title,
        $status_kerja,
        $join_start,
        $masa_kerja_tahun,
        $masa_kerja_bulan,
        $masa_eff,
        $remark,
        $jk,
        $tgl_lahir,
        $usia,
        $religion,
        $alamat_domisili,
        $alamat_ktp,
        $no_rekening,
        $no_hp,
        $pendidikan,
        $status_perkawinan,
        $email,
        $nama_pasangan,
        $jumlah_anak,
        $nama_anak_1,
        $nama_anak_2,
        $nama_anak_3,
        $salary_index_id,
        $gaji_pokok,
        $role
    );

    /* ---------- 6. Eksekusi ---------- */
    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $stmt->close();

        updateSalaryIndexForUser($conn, $newId);          // refresh salary index
        add_audit_log(
            $conn,
            $_SESSION['nip'] ?? '',
            'CreateGuru',
            "Menambah Guru/Karyawan baru ID=$newId, NIP=$nip, Nama=$nama"
        );
        send_response(0, 'Data berhasil disimpan.');
    } else {
        $err = $stmt->error;
        $stmt->close();
        send_response(1, "Gagal menyimpan data: $err");
    }
}


/* =============================================================
 * 3. Fungsi GetGuruDetail: Mengambil detail 1 baris data
 * =========================================================== */
function GetGuruDetail($conn): array
{
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) send_response(1, 'ID tidak valid.');
    $stmt = $conn->prepare("
        SELECT a.*, si.level AS salary_level, si.description AS salary_desc
        FROM anggota_sekolah a
        LEFT JOIN salary_indices si ON a.salary_index_id = si.id
        WHERE a.id = ? LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows == 1) {
        $data = $res->fetch_assoc();
        unset($data['password']);
        $data['masa_kerja'] = $data['masa_kerja_tahun'] . " Thn " . $data['masa_kerja_bulan'] . " Bln";
        $data['religion']   = $data['agama'];
        $data['jk']         = $data['jenis_kelamin'];
        send_response(0, $data);
    } else {
        send_response(2, 'Data tidak ditemukan.');
    }
    $stmt->close();
}

/* =============================================================
 * 4. Fungsi UpdateGuru: Memperbarui data anggota
 * =========================================================== */
function UpdateGuru($conn)
{
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        send_response(1, 'ID tidak valid.');
    }

    // Ambil nilai POST sebagai variabel lokal untuk menghindari error bind_param
    $nip       = bersihkan_input($_POST['nip'] ?? '');
    $uid       = bersihkan_input($_POST['uid'] ?? '');
    $nama      = bersihkan_input($_POST['nama'] ?? '');
    $jenjang   = bersihkan_input($_POST['jenjang'] ?? '');
    $job_title = bersihkan_input($_POST['job_title'] ?? '');
    $role      = bersihkan_input($_POST['role'] ?? '');
    $status_kerja = bersihkan_input($_POST['status_kerja'] ?? 'Tetap');
    // Jika status kerja Kontrak, ambil lama kontrak dari form; jika tidak, nilainya null.
    $lama_kontrak = ($status_kerja === 'Kontrak') ? intval($_POST['lama_kontrak'] ?? 12) : null;

    if (!$nip || !$nama || !$jenjang || !$role) {
        send_response(1, 'NIP, Nama, Jenjang dan Role wajib diisi.');
    }

    // Cek duplikasi NIP (selain record ini)
    $cek = $conn->prepare("SELECT id FROM anggota_sekolah WHERE nip = ? AND id <> ?");
    $cek->bind_param("si", $nip, $id);
    $cek->execute();
    if ($cek->get_result()->num_rows > 0) {
        send_response(1, 'NIP sudah digunakan oleh user lain.');
    }
    $cek->close();

    $join_start = bersihkan_input($_POST['join_start'] ?? '');
    // Jika status Kontrak dan join_start ada, hitung tanggal kontrak selesai
    $tgl_kontrak_selesai = ($status_kerja === 'Kontrak' && $join_start) ? addMonths($join_start, $lama_kontrak) : null;

    // Hitung masa kerja (tahun, bulan, efektif) dengan fungsi helper (pastikan fungsi ini sudah didefinisikan)
    list($masa_tahun, $masa_bulan, $masa_eff) = calcMasaKerja($join_start);

    // Ambil nilai POST lainnya ke variabel lokal (untuk bind_param, menghindari passing value langsung)
    $remark           = $_POST['remark'] ?? null;
    $jk               = $_POST['jk'] ?? null;
    $tgl_lahir        = $_POST['tgl_lahir'] ?? null;
    $usia             = $_POST['usia'] ?? null;
    $religion         = $_POST['religion'] ?? null;
    $alamat_domisili  = $_POST['alamat_domisili'] ?? null;
    $alamat_ktp       = $_POST['alamat_ktp'] ?? null;
    $no_rekening      = $_POST['no_rekening'] ?? null;
    $no_hp            = $_POST['no_hp'] ?? null;
    $pendidikan       = $_POST['pendidikan'] ?? null;
    $status_perkawinan = $_POST['status_perkawinan'] ?? null;
    $email            = $_POST['email'] ?? null;
    $nama_pasangan    = $_POST['nama_pasangan'] ?? null;
    $jumlah_anak      = intval($_POST['jumlah_anak'] ?? 0);
    $nama_anak_1      = $_POST['nama_anak_1'] ?? '';
    $nama_anak_2      = $_POST['nama_anak_2'] ?? '';
    $nama_anak_3      = $_POST['nama_anak_3'] ?? '';
    $salary_index_id  = $_POST['salary_index_id'] ?? null;

    // Jika status perkawinan "Belum Menikah", set nilai default
    if ($status_perkawinan === 'Belum Menikah') {
        $nama_pasangan = '-';
        $jumlah_anak = 0;
        $nama_anak_1 = '-';
        $nama_anak_2 = '-';
        $nama_anak_3 = '-';
    }

    // Jika password baru diisi, siapkan nilainya
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }

    // Siapkan data kolom yang akan diupdate ke array asosiatif
    $cols = [
        'nip'                 => $nip,
        'nama'                => $nama,
        'jenjang'             => $jenjang,
        'job_title'           => $job_title,
        'role'                => $role,
        'status_kerja'        => $status_kerja,
        'join_start'          => $join_start,
        'lama_kontrak'        => $lama_kontrak,
        'tgl_kontrak_selesai' => $tgl_kontrak_selesai,
        'masa_kerja_tahun'    => $masa_tahun,
        'masa_kerja_bulan'    => $masa_bulan,
        'masa_kerja_efektif'  => $masa_eff,
        'remark'              => $remark,
        'jenis_kelamin'       => $jk,
        'tanggal_lahir'       => $tgl_lahir,
        'usia'                => $usia,
        'agama'               => $religion,
        'alamat_domisili'     => $alamat_domisili,
        'alamat_ktp'          => $alamat_ktp,
        'no_rekening'         => $no_rekening,
        'no_hp'               => $no_hp,
        'pendidikan'          => $pendidikan,
        'status_perkawinan'   => $status_perkawinan,
        'email'               => $email,
        'nama_pasangan'       => $nama_pasangan,
        'jumlah_anak'         => $jumlah_anak,
        'nama_anak_1'         => $nama_anak_1,
        'nama_anak_2'         => $nama_anak_2,
        'nama_anak_3'         => $nama_anak_3,
        'salary_index_id'     => $salary_index_id
    ];
    if (!empty($password)) { // Jika password baru diisi, tambahkan ke array
        $cols['password'] = $password;
    }

    // Bangun query update secara dinamis
    $set = [];
    $types = "";
    $vals = [];
    foreach ($cols as $col => $val) {
        $set[] = "$col = ?";
        // Tentukan tipe data: kolom integer/double gunakan 'i' atau 'd', sisanya gunakan 's'
        if (in_array($col, ['lama_kontrak', 'masa_kerja_tahun', 'masa_kerja_bulan', 'usia', 'jumlah_anak', 'salary_index_id'])) {
            $types .= 'i';
        } elseif ($col === 'masa_kerja_efektif') {
            $types .= 'd';
        } else {
            $types .= 's';
        }
        $vals[] = $val;
    }
    // Tambahkan parameter WHERE (id)
    $types .= 'i';
    $vals[] = $id;
    $sql = "UPDATE anggota_sekolah SET " . implode(", ", $set) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_response(1, 'Query error: ' . $conn->error);
    }
    // Gunakan bind_param dengan parameter yang sudah tersimpan dalam variabel $vals
    $stmt->bind_param($types, ...$vals);

    if ($stmt->execute()) {
        $stmt->close();
        updateSalaryIndexForUser($conn, $id);
        $user_id = $_SESSION['nip'] ?? '';
        add_audit_log($conn, $user_id, 'UpdateGuru', "Update data ID=$id, NIP=$nip, Nama=$nama.");
        send_response(0, 'Data berhasil diperbarui.');
    } else {
        $stmt->close();
        send_response(1, 'Gagal update data: ' . $conn->error);
    }
}

function DeleteGuru($conn)
{
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        send_response(1, 'ID tidak valid.');
    }

    // Update is_delete dan deleted_at
    $sql = "UPDATE anggota_sekolah 
            SET is_delete = 1, deleted_at = NOW() 
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_response(1, 'Query error: ' . $conn->error);
    }

    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $stmt->close();
        $user_id = $_SESSION['nip'] ?? '';
        add_audit_log($conn, $user_id, 'DeleteGuru', "Soft delete ID=$id");
        send_response(0, 'Data berhasil dihapus.');
    } else {
        $stmt->close();
        send_response(1, 'Gagal menghapus data: ' . $conn->error);
    }
}


function updateGajiPokok($conn)
{
    $gaji_guru     = isset($_POST['gaji_pokok_guru']) ? floatval($_POST['gaji_pokok_guru']) : 0;
    $gaji_karyawan = isset($_POST['gaji_pokok_karyawan']) ? floatval($_POST['gaji_pokok_karyawan']) : 0;

    $stmtGuru = $conn->prepare("UPDATE gaji_pokok_roles SET gaji_pokok=? WHERE role='guru'");
    if (!$stmtGuru) {
        send_response(1, 'Query error: ' . $conn->error);
    }
    $stmtGuru->bind_param("d", $gaji_guru);
    $execGuru = $stmtGuru->execute();
    $stmtGuru->close();

    $stmtKar = $conn->prepare("UPDATE gaji_pokok_roles SET gaji_pokok=? WHERE role='karyawan'");
    if (!$stmtKar) {
        send_response(1, 'Query error: ' . $conn->error);
    }
    $stmtKar->bind_param("d", $gaji_karyawan);
    $execKar = $stmtKar->execute();
    $stmtKar->close();

    if ($execGuru && $execKar) {
        send_response(0, 'Gaji pokok berhasil diupdate.');
    } else {
        send_response(1, 'Gagal update gaji pokok.');
    }
}

function updateGajiStrataGuru($conn)
{
    $updates = [];
    $guruConfig = [
        'TK'      => ['D3', 'S1', 'S2'],
        'SD'      => ['S1', 'S2'],
        'SMP'     => ['S1', 'S2'],
        'SMA/SMK' => ['S1', 'S2', 'S3']
    ];
    foreach ($guruConfig as $jenjang => $strataArr) {
        foreach ($strataArr as $strata) {
            $fieldName = strtolower(str_replace('/', '', $jenjang)) . '_' . strtolower($strata);
            $gaji = isset($_POST[$fieldName]) ? floatval($_POST[$fieldName]) : 0;
            $updates[] = ['jenjang' => $jenjang, 'strata' => $strata, 'gaji' => $gaji];
        }
    }
    $allSuccess = true;
    foreach ($updates as $upd) {
        $sql = "INSERT INTO gaji_pokok_strata_guru (jenjang, strata, gaji_pokok)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE gaji_pokok = VALUES(gaji_pokok)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $allSuccess = false;
            continue;
        }
        $stmt->bind_param("ssd", $upd['jenjang'], $upd['strata'], $upd['gaji']);
        if (!$stmt->execute()) {
            $allSuccess = false;
        }
        $stmt->close();
    }
    if ($allSuccess) {
        send_response(0, 'Gaji pokok strata Guru berhasil diupdate.');
    } else {
        send_response(1, 'Gagal update beberapa data strata Guru.');
    }
}

function updateGajiStrataKaryawan($conn)
{
    $updates = [];
    $karyawanConfig = [
        'TK'  => ['D3'],
        'SD'  => ['S1'],
        'SMP' => ['S2']
    ];
    foreach ($karyawanConfig as $jenjang => $strataArr) {
        foreach ($strataArr as $strata) {
            $fieldName = strtolower(str_replace('/', '', $jenjang)) . '_' . strtolower($strata);
            $gaji = isset($_POST[$fieldName]) ? floatval($_POST[$fieldName]) : 0;
            $updates[] = ['jenjang' => $jenjang, 'strata' => $strata, 'gaji' => $gaji];
        }
    }
    $allSuccess = true;
    foreach ($updates as $upd) {
        $sql = "INSERT INTO gaji_pokok_strata_karyawan (jenjang, strata, gaji_pokok)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE gaji_pokok = VALUES(gaji_pokok)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $allSuccess = false;
            continue;
        }
        $stmt->bind_param("ssd", $upd['jenjang'], $upd['strata'], $upd['gaji']);
        if (!$stmt->execute()) {
            $allSuccess = false;
        }
        $stmt->close();
    }
    if ($allSuccess) {
        send_response(0, 'Gaji pokok strata Karyawan berhasil diupdate.');
    } else {
        send_response(1, 'Gagal update beberapa data strata Karyawan.');
    }
}

function updateSalaryIndexForUser($conn, $id)
{
    $stmt = $conn->prepare("SELECT role, join_start, pendidikan, jenjang FROM anggota_sekolah WHERE id = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$row = $result->fetch_assoc()) {
        $stmt->close();
        return false;
    }
    $role = $row['role'];
    $join_start = $row['join_start'];
    $pendidikan = $row['pendidikan'];
    $jenjang = $row['jenjang'];
    $stmt->close();

    $years = 0;
    if (!empty($join_start) && $join_start != '0000-00-00') {
        try {
            $startDate = new DateTime($join_start);
            $now = new DateTime();
            $diff = $now->diff($startDate);
            $years = $diff->y;
        } catch (Exception $e) {
            $years = 0;
        }
    }

    $stmtIndex = $conn->prepare("SELECT id, base_salary, level FROM salary_indices WHERE min_years <= ? AND (max_years >= ? OR max_years IS NULL) LIMIT 1");
    if (!$stmtIndex) {
        return false;
    }
    $stmtIndex->bind_param("ii", $years, $years);
    $stmtIndex->execute();
    $resultIndex = $stmtIndex->get_result();
    if ($indexRow = $resultIndex->fetch_assoc()) {
        $salary_index_id = $indexRow['id'];
        $base_salary = floatval($indexRow['base_salary']);
        $level = $indexRow['level'];
        $stmtIndex->close();
    } else {
        $stmtIndex->close();
        return false;
    }

    $gaji_pokok = 0;
    if ($role === 'P' && !empty($pendidikan)) {
        $normalizedPendidikan = normalizePendidikan($pendidikan);
        $stmtStrata = $conn->prepare("SELECT gaji_pokok FROM gaji_pokok_strata_guru WHERE jenjang=? AND strata=? LIMIT 1");
        if ($stmtStrata) {
            $stmtStrata->bind_param("ss", $jenjang, $normalizedPendidikan);
            $stmtStrata->execute();
            $guru_salary = 0;
            $stmtStrata->bind_result($guru_salary);
            if ($stmtStrata->fetch()) {
                $gaji_pokok = floatval($guru_salary);
            } else {
                $gaji_pokok = $base_salary;
            }
            $stmtStrata->close();
        } else {
            $gaji_pokok = $base_salary;
        }
    } else {
        $gaji_pokok = $base_salary;
    }

    $stmtUpdate = $conn->prepare("UPDATE anggota_sekolah SET salary_index_id=?, salary_index_level=?, gaji_pokok=? WHERE id=?");
    if (!$stmtUpdate) {
        return false;
    }
    $stmtUpdate->bind_param("isdi", $salary_index_id, $level, $gaji_pokok, $id);
    $exec = $stmtUpdate->execute();
    $stmtUpdate->close();
    return $exec;
}

function generateUID($conn)
{
    do {
        $uid = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        $stmt = $conn->prepare("SELECT id FROM anggota_sekolah WHERE uid=? LIMIT 1");
        $stmt->bind_param("s", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res && $res->num_rows > 0;
        $stmt->close();
    } while ($exists);
    return $uid;
}

function getGajiPokokByRole($conn, $role)
{
    $lookup = ($role === 'P') ? 'guru' : 'karyawan';
    $gaji_pokok = 0.0;

    $stmt = $conn->prepare("SELECT gaji_pokok FROM gaji_pokok_roles WHERE role=?");
    if ($stmt) {
        $stmt->bind_param("s", $lookup);
        $stmt->execute();
        $stmt->bind_result($gaji_pokok);
        $stmt->fetch();
        $stmt->close();
    }

    return floatval($gaji_pokok);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Manajemen Data Guru/Karyawan - Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- CSS dari CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SB Admin 2 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/startbootstrap-sb-admin-2/4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body,
        .text-gray-800 {
            color: #000 !important;
        }

        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }

        #loadingSpinner {
            display: none;
            position: fixed;
            z-index: 9999;
            height: 100px;
            width: 100px;
            margin: auto;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
        }

        #ManageModal .modal-dialog {
            max-width: 1000px;
            margin: auto;
            padding-top: 70px;
            color: #000 !important;
        }

        .employee-initial {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #ff9800;
            color: #fff;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px auto;
        }

        #employeeCards {
            margin-top: 20px;
        }

        #employeeCards .col {
            display: flex;
        }

        #employeeCards .card {
            flex: 1;
        }
    </style>
</head>

<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <!-- End Sidebar -->
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Navbar -->
                <?php include __DIR__ . '/../navbar.php'; ?>
                <!-- Breadcrumb -->
                <?php include __DIR__ . '/../breadcrumb.php'; ?>

                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Manajemen Data Guru/Karyawan</h1>

                    <!-- Bagian Tombol Aksi -->
                    <div class="d-flex justify-content-end mb-3 flex-wrap gap-2">
                        <!-- Tombol Tambah -->
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAdd">
                            <i class="fas fa-plus"></i> Tambah Guru/Karyawan
                        </button>

                        <!-- Tombol History -->
                        <a href="history_anggota_sekolah.php" class="btn btn-dark btn-sm">
                            <i class="fas fa-history"></i> Lihat History
                        </a>

                        <!-- Tombol Atur Gaji Pokok -->
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalGajiPokok">
                            <i class="fas fa-dollar-sign"></i> Atur Gaji Pokok
                        </button>

                        <!-- Tombol Atur Salary Indeks -->
                        <button class="btn btn-info btn-sm" id="btnManageSalaryIndices" data-href="/payroll_absensi_v2/sdm/manage_salary_indices.php">
                            <i class="fas fa-money-bill-wave"></i> Atur Salary Indeks
                        </button>

                        <!-- Tombol Atur Hari Libur -->
                        <button class="btn btn-warning btn-sm" id="btnManageHolidays" data-href="/payroll_absensi_v2/sdm/holidays.php">
                            <i class="fas fa-calendar-alt"></i> Atur Hari Libur
                        </button>
                    </div>

                    <!-- Filter Section: Data Guru/Karyawan -->
                    <div class="card mb-4 shadow">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-white">
                                <i class="fas fa-search"></i> Filter Data Guru/Karyawan
                            </h6>
                        </div>
                        <div class="card-body" style="background-color: #f8f9fa;">
                            <form id="filterForm" method="GET" class="row gy-2 gx-3 align-items-center">
                                <!-- Jenjang -->
                                <div class="col-auto">
                                    <label for="filterJenjang" class="form-label mb-0"><strong>Jenjang:</strong></label>
                                    <select class="form-control" id="filterJenjang" name="jenjang">
                                        <option value="">Semua Jenjang</option>
                                        <?php
                                        $jenjangList = getOrderedJenjang();
                                        foreach ($jenjangList as $jenjang) {
                                            echo '<option value="' . htmlspecialchars($jenjang) . '">' . htmlspecialchars($jenjang) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <!-- Role -->
                                <div class="col-auto">
                                    <label for="filterRole" class="form-label mb-0"><strong>Role:</strong></label>
                                    <select class="form-control" id="filterRole" name="role">
                                        <option value="">Semua Role</option>
                                        <option value="P" <?= (isset($_GET['role']) && $_GET['role'] === 'P') ? 'selected' : ''; ?>>Pendidik</option>
                                        <option value="TK" <?= (isset($_GET['role']) && $_GET['role'] === 'TK') ? 'selected' : ''; ?>>Tenaga Kependidikan</option>
                                        <option value="M" <?= (isset($_GET['role']) && $_GET['role'] === 'M') ? 'selected' : ''; ?>>Manajerial</option>
                                    </select>
                                </div>
                                <!-- Status Kerja -->
                                <div class="col-auto">
                                    <label for="filterStatus" class="form-label mb-0"><strong>Status Kerja:</strong></label>
                                    <select class="form-control" id="filterStatus" name="status_kerja">
                                        <option value="">Semua Status</option>
                                        <option value="Tetap" <?= (isset($_GET['status_kerja']) && $_GET['status_kerja'] === 'Tetap') ? 'selected' : ''; ?>>Tetap</option>
                                        <option value="Kontrak" <?= (isset($_GET['status_kerja']) && $_GET['status_kerja'] === 'Kontrak') ? 'selected' : ''; ?>>Kontrak</option>
                                    </select>
                                </div>
                                <!-- Tombol -->
                                <div class="col-auto d-flex align-items-end">
                                    <button type="button" id="btnApplyFilter" class="btn btn-primary me-2">
                                        <i class="fas fa-filter"></i> Terapkan
                                    </button>
                                    <button type="button" id="btnResetFilter" class="btn btn-secondary">
                                        <i class="fas fa-undo"></i> Reset
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- End Filter Section -->

                    <!-- Daftar Karyawan/Guru dalam Grid -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-white">
                                <i class="fas fa-user"></i> Daftar Guru/Karyawan
                            </h6>
                        </div>
                        <div class="card-body">
                            <div id="employeeCards" class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-5 g-3">
                            </div>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center" id="paginationContainer">
                                </ul>
                            </nav>
                        </div>
                    </div>

                    <div id="loadingSpinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Main Content -->

            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?php echo date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- MODAL: Tambah Guru/Karyawan -->
    <div class="modal fade" id="modalAdd" tabindex="-1" aria-labelledby="modalAddLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form id="add-guru-form" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="case" value="CreateGuru">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalAddLabel">Tambah Data Guru/Karyawan</h5>
                        <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Data Pekerjaan -->
                        <h6 class="mb-2">Data Pekerjaan</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addNip">NIP <span class="text-danger">*</span></label>
                                    <input type="text" name="nip" id="addNip" class="form-control" required placeholder="Masukkan NIP">
                                    <div class="invalid-feedback">NIP wajib diisi.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addNama">Nama <span class="text-danger">*</span></label>
                                    <input type="text" name="nama" id="addNama" class="form-control" required placeholder="Nama lengkap">
                                    <div class="invalid-feedback">Nama wajib diisi.</div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addJenjang">Jenjang <span class="text-danger">*</span></label>
                                    <select name="jenjang" id="addJenjang" class="form-control" required>
                                        <option value="">-- Pilih Jenjang --</option>
                                        <?php foreach ($jenjangList as $jenjang): ?>
                                            <option value="<?= htmlspecialchars($jenjang) ?>">
                                                <?= htmlspecialchars($jenjang) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Jenjang wajib dipilih.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addJobTitle">Job Title</label>
                                    <input type="text" name="job_title" id="addJobTitle" class="form-control" placeholder="Contoh: Guru, Staff, dll">
                                </div>
                            </div>
                        </div>
                        <!-- Field Role dan Tanggal Bergabung -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addRole">Role <span class="text-danger">*</span></label>
                                    <select name="role" id="addRole" class="form-control" required>
                                        <option value="">-- Pilih Role --</option>
                                        <option value="P">Pendidik (Guru)</option>
                                        <option value="TK">Tenaga Kependidikan</option>
                                        <option value="M">Manajerial</option>
                                    </select>
                                    <div class="invalid-feedback">Role wajib dipilih.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addJoinStart">Tanggal Bergabung <span class="text-danger">*</span></label>
                                    <input type="date" name="join_start" id="addJoinStart" class="form-control" required>
                                    <div class="invalid-feedback">Tanggal Bergabung wajib diisi.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addRemark">Remark</label>
                                    <input type="text" name="remark" id="addRemark" class="form-control" placeholder="Catatan tambahan">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addPendidikan">Pendidikan</label>
                                    <input type="text" name="pendidikan" id="addPendidikan" class="form-control" placeholder="Contoh: S1, D3">
                                </div>
                            </div>
                            <!-- Status Kerja -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addStatusKerja">Status Kerja <span class="text-danger">*</span></label>
                                    <select name="status_kerja" id="addStatusKerja" class="form-control" required>
                                        <option value="Tetap" selected>Tetap</option>
                                        <option value="Kontrak">Kontrak</option>
                                    </select>
                                </div>
                            </div>
                            <!-- Lama Kontrak -->
                            <div class="col-md-6 add-kontak-only d-none">
                                <div class="form-group">
                                    <label for="addLamaKontrak">Lama Kontrak (bulan)</label>
                                    <select name="lama_kontrak" id="addLamaKontrak" class="form-control">
                                        <option value="6">6</option>
                                        <option value="12" selected>12</option>
                                        <option value="24">24</option>
                                        <option value="36">36</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Data Pribadi -->
                        <h6 class="mt-4 mb-2">Data Pribadi</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addJK">Jenis Kelamin</label>
                                    <select name="jk" id="addJK" class="form-control">
                                        <option value="">-- Pilih Jenis Kelamin --</option>
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addTglLahir">Tanggal Lahir</label>
                                    <input type="date" name="tgl_lahir" id="addTglLahir" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addUsia">Usia</label>
                                    <input type="number" name="usia" id="addUsia" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addReligion">Agama</label>
                                    <input type="text" name="religion" id="addReligion" class="form-control">
                                </div>
                            </div>
                        </div>
                        <!-- Data Kontak -->
                        <h6 class="mt-4 mb-2">Data Kontak</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addAlamatDomisili">Alamat Domisili</label>
                                    <textarea name="alamat_domisili" id="addAlamatDomisili" class="form-control"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addAlamatKTP">Alamat KTP</label>
                                    <textarea name="alamat_ktp" id="addAlamatKTP" class="form-control"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addNoRekening">No Rekening</label>
                                    <input type="text" name="no_rekening" id="addNoRekening" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addNoHP">No Handphone</label>
                                    <input type="text" name="no_hp" id="addNoHP" class="form-control" placeholder="08xxx">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addEmail">Email</label>
                                    <input type="email" name="email" id="addEmail" class="form-control" placeholder="contoh@domain.com">
                                </div>
                            </div>
                        </div>
                        <!-- Data Lainnya -->
                        <h6 class="mt-4 mb-2">Data Lainnya</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addStatusPerkawinan">Status Perkawinan <span class="text-danger">*</span></label>
                                    <select name="status_perkawinan" id="addStatusPerkawinan" class="form-control" required>
                                        <option value="">-- Pilih Status --</option>
                                        <option value="Menikah">Menikah</option>
                                        <option value="Belum Menikah">Belum Menikah</option>
                                    </select>
                                    <div class="invalid-feedback">Status Perkawinan wajib dipilih.</div>
                                </div>
                            </div>
                        </div>
                        <!-- Pasangan dan Anak -->
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addNamaPasangan">Nama Pasangan</label>
                                    <input type="text" name="nama_pasangan" id="addNamaPasangan" class="form-control" placeholder="Nama Pasangan">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="addJumlahAnak">Jumlah Anak</label>
                                    <input type="number" name="jumlah_anak" id="addJumlahAnak" class="form-control" placeholder="Jumlah Anak">
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="addNamaAnak1">Nama Anak 1</label>
                                    <input type="text" name="nama_anak_1" id="addNamaAnak1" class="form-control" placeholder="Nama Anak 1">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="addNamaAnak2">Nama Anak 2</label>
                                    <input type="text" name="nama_anak_2" id="addNamaAnak2" class="form-control" placeholder="Nama Anak 2">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="addNamaAnak3">Nama Anak 3</label>
                                    <input type="text" name="nama_anak_3" id="addNamaAnak3" class="form-control" placeholder="Nama Anak 3">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            Simpan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-labelledby="modalEditLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form id="edit-guru-form" method="POST" class="needs-validation" novalidate>
      <input type="hidden" name="case" value="UpdateGuru">
      <input type="hidden" name="id" id="editId">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalEditLabel">Edit Data Guru/Karyawan</h5>
          <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <h6 class="mb-2">Data Pekerjaan</h6>
          <div class="row">
            <div class="col-md-6">
              <label for="editNip">NIP <span class="text-danger">*</span></label>
              <input type="text" name="nip" id="editNip" class="form-control" required>
              <div class="invalid-feedback">NIP wajib diisi.</div>
            </div>
            <div class="col-md-6">
              <label for="editUid">UID</label>
              <input type="text" name="uid" id="editUid" class="form-control" readonly>
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-6">
              <label for="editNama">Nama <span class="text-danger">*</span></label>
              <input type="text" name="nama" id="editNama" class="form-control" required>
              <div class="invalid-feedback">Nama wajib diisi.</div>
            </div>
            <div class="col-md-6">
              <label for="editJenjang">Jenjang <span class="text-danger">*</span></label>
              <select name="jenjang" id="editJenjang" class="form-control" required>
                <option value="">-- Pilih Jenjang --</option>
                <?php foreach ($jenjangList as $jenjang): ?>
                  <option value="<?= htmlspecialchars($jenjang) ?>">
                    <?= htmlspecialchars($jenjang) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Jenjang wajib dipilih.</div>
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-6">
              <label for="editRole">Role <span class="text-danger">*</span></label>
              <select name="role" id="editRole" class="form-control" required>
                <option value="">-- Pilih Role --</option>
                <option value="P">Pendidik</option>
                <option value="TK">Tenaga Kependidikan</option>
                <option value="M">Manajerial</option>
              </select>
              <div class="invalid-feedback">Role wajib dipilih.</div>
            </div>
            <div class="col-md-6">
              <label for="editJobTitle">Job Title</label>
              <input type="text" name="job_title" id="editJobTitle" class="form-control">
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-6">
              <label for="editRemark">Remark</label>
              <input type="text" name="remark" id="editRemark" class="form-control" placeholder="Catatan tambahan">
            </div>
            <!-- Status Kerja -->
            <div class="col-md-6">
              <div class="form-group">
                <label for="editStatusKerja">Status Kerja <span class="text-danger">*</span></label>
                <select name="status_kerja" id="editStatusKerja" class="form-control" required>
                  <option value="Tetap">Tetap</option>
                  <option value="Kontrak">Kontrak</option>
                </select>
              </div>
            </div>
            <!-- Lama Kontrak (Tampil jika Status Kerja = Kontrak) -->
            <div class="col-md-6 d-none" id="editLamaKontrakContainer">
              <div class="form-group">
                <label for="editLamaKontrak">Lama Kontrak (bulan)</label>
                <select name="lama_kontrak" id="editLamaKontrak" class="form-control">
                  <option value="6">6</option>
                  <option value="12" selected>12</option>
                  <option value="24">24</option>
                  <option value="36">36</option>
                </select>
              </div>
            </div>
          </div>
          <!-- Tanggal Bergabung -->
          <div class="row mt-2">
            <div class="col-md-6">
              <label for="editJoinStart">Tanggal Bergabung</label>
              <input type="date" name="join_start" id="editJoinStart" class="form-control">
            </div>
          </div>
          <h6 class="mt-4 mb-2">Data Pribadi</h6>
          <div class="row">
            <div class="col-md-6">
              <label for="editJK">Jenis Kelamin</label>
              <select name="jk" id="editJK" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="L">Laki-laki</option>
                <option value="P">Perempuan</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="editTglLahir">Tanggal Lahir</label>
              <input type="date" name="tgl_lahir" id="editTglLahir" class="form-control">
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-6">
              <label for="editUsia">Usia</label>
              <input type="number" name="usia" id="editUsia" class="form-control">
            </div>
            <div class="col-md-6">
              <label for="editReligion">Agama</label>
              <input type="text" name="religion" id="editReligion" class="form-control">
            </div>
          </div>
          <!-- Pendidikan -->
          <div class="row mt-2">
            <div class="col-md-6">
              <label for="editPendidikan">Pendidikan</label>
              <input type="text" name="pendidikan" id="editPendidikan" class="form-control" placeholder="Contoh: S1">
            </div>
          </div>
          <!-- Data Kontak -->
          <h6 class="mt-4 mb-2">Data Kontak</h6>
          <div class="row">
            <div class="col-md-6">
              <label for="editAlamatDomisili">Alamat Domisili</label>
              <textarea name="alamat_domisili" id="editAlamatDomisili" class="form-control"></textarea>
            </div>
            <div class="col-md-6">
              <label for="editAlamatKTP">Alamat KTP</label>
              <textarea name="alamat_ktp" id="editAlamatKTP" class="form-control"></textarea>
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-6">
              <label for="editNoRekening">No Rekening</label>
              <input type="text" name="no_rekening" id="editNoRekening" class="form-control">
            </div>
            <div class="col-md-6">
              <label for="editNoHP">No HP</label>
              <input type="text" name="no_hp" id="editNoHP" class="form-control">
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-6">
              <label for="editEmail">Email</label>
              <input type="email" name="email" id="editEmail" class="form-control">
            </div>
          </div>
          <!-- Data Lainnya -->
          <h6 class="mt-4 mb-2">Data Lainnya</h6>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="editStatusPerkawinan">Status Perkawinan <span class="text-danger">*</span></label>
                <select name="status_perkawinan" id="editStatusPerkawinan" class="form-control" required>
                  <option value="">-- Pilih Status --</option>
                  <option value="Menikah">Menikah</option>
                  <option value="Belum Menikah">Belum Menikah</option>
                </select>
                <div class="invalid-feedback">Status Perkawinan wajib dipilih.</div>
              </div>
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-6">
              <div class="form-group">
                <label for="editNamaPasangan">Nama Pasangan</label>
                <input type="text" name="nama_pasangan" id="editNamaPasangan" class="form-control">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="editJumlahAnak">Jumlah Anak</label>
                <input type="number" name="jumlah_anak" id="editJumlahAnak" class="form-control">
              </div>
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-4">
              <div class="form-group">
                <label for="editNamaAnak1">Nama Anak 1</label>
                <input type="text" name="nama_anak_1" id="editNamaAnak1" class="form-control">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label for="editNamaAnak2">Nama Anak 2</label>
                <input type="text" name="nama_anak_2" id="editNamaAnak2" class="form-control">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label for="editNamaAnak3">Nama Anak 3</label>
                <input type="text" name="nama_anak_3" id="editNamaAnak3" class="form-control">
              </div>
            </div>
          </div>
          <hr>
          <div class="row">
            <div class="col-md-12">
              <label for="editPassword">Password Baru (Opsional)</label>
              <input type="password" name="password" id="editPassword" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">
            <span class="spinner-border spinner-border-sm d-none"></span>
            Update
          </button>
        </div>
      </div>
    </form>
  </div>
</div>



        <!-- MODAL: View Detail -->
        <div class="modal fade" id="modalView" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Detail Data Guru/Karyawan</h5>
                        <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body" style="color: #000;">
                        <h6>Data Pekerjaan</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>NIP</th>
                                <td id="detailNip"></td>
                            </tr>
                            <tr>
                                <th>UID</th>
                                <td id="detailUid"></td>
                            </tr>
                            <tr>
                                <th>Nama</th>
                                <td id="detailNama"></td>
                            </tr>
                            <tr>
                                <th>Jenjang</th>
                                <td id="detailJenjang"></td>
                            </tr>
                            <tr>
                                <th>Job Title</th>
                                <td id="detailJobTitle"></td>
                            </tr>
                            <tr>
                                <th>Role</th>
                                <td id="detailRole"></td>
                            </tr>
                            <tr>
                                <th>Status Kerja</th>
                                <td id="detailStatusKerja"></td>
                            </tr>
                            <tr>
                                <th>Tanggal Bergabung</th>
                                <td id="detailJoinStart"></td>
                            </tr>
                            <tr>
                                <th>Masa Kerja</th>
                                <td id="detailMasaKerja"></td>
                            </tr>
                            <tr>
                                <th>Pendidikan</th>
                                <td id="detailPendidikan"></td>
                            </tr>
                            <tr>
                                <th>Salary Indeks Level</th>
                                <td id="detailSalaryIndexId"></td>
                            </tr>
                        </table>

                        <h6 class="mt-3">Data Pribadi</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Jenis Kelamin</th>
                                <td id="detailJK"></td>
                            </tr>
                            <tr>
                                <th>Tanggal Lahir</th>
                                <td id="detailTglLahir"></td>
                            </tr>
                            <tr>
                                <th>Usia</th>
                                <td id="detailUsia"></td>
                            </tr>
                            <tr>
                                <th>Agama</th>
                                <td id="detailReligion"></td>
                            </tr>
                        </table>

                        <h6 class="mt-3">Data Kontak</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Alamat Domisili</th>
                                <td id="detailAlamatDomisili"></td>
                            </tr>
                            <tr>
                                <th>Alamat KTP</th>
                                <td id="detailAlamatKTP"></td>
                            </tr>
                            <tr>
                                <th>No Rekening</th>
                                <td id="detailNoRekening"></td>
                            </tr>
                            <tr>
                                <th>No HP</th>
                                <td id="detailNoHP"></td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td id="detailEmail"></td>
                            </tr>
                        </table>

                        <h6 class="mt-3">Data Lainnya</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Remark</th>
                                <td id="detailRemark"></td>
                            </tr>
                            <tr>
                                <th>Status Perkawinan</th>
                                <td id="detailStatusPerkawinan"></td>
                            </tr>
                            <tr>
                                <th>Nama Pasangan</th>
                                <td id="detailNamaPasangan"></td>
                            </tr>
                            <tr>
                                <th>Jumlah Anak</th>
                                <td id="detailJumlahAnak"></td>
                            </tr>
                            <tr>
                                <th>Nama Anak 1</th>
                                <td id="detailNamaAnak1"></td>
                            </tr>
                            <tr>
                                <th>Nama Anak 2</th>
                                <td id="detailNamaAnak2"></td>
                            </tr>
                            <tr>
                                <th>Nama Anak 3</th>
                                <td id="detailNamaAnak3"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: Delete -->
        <div class="modal fade" id="modalDelete" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="delete-guru-form">
                        <input type="hidden" name="case" value="DeleteGuru">
                        <input type="hidden" name="id" id="delId">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Hapus Data Guru/Karyawan</h5>
                            <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <p>Anda yakin ingin menghapus data berikut?</p>
                            <p><strong id="delNama"></strong></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger">
                                <span class="spinner-border spinner-border-sm d-none"></span>
                                Hapus
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL: Atur Gaji Pokok -->
        <div class="modal fade" id="modalGajiPokok" tabindex="-1" aria-labelledby="modalGajiPokokLabel" aria-hidden="true">
            <div class="modal-dialog modal-md">
                <form id="gaji-pokok-form" method="POST" class="modal-content">
                    <input type="hidden" name="case" value="update_gaji_pokok">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalGajiPokokLabel">Atur Gaji Pokok</h5>
                        <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <!-- Ubah tombol ini menjadi tanpa data-bs-toggle/data-bs-target -->
                            <button type="button" id="btnGajiStrataGuru" class="btn btn-secondary me-2">
                                <i class="fas fa-chart-bar"></i> Atur Gaji Strata Guru
                            </button>
                            <button type="button" id="btnGajiStrataKaryawan" class="btn btn-secondary">
                                <i class="fas fa-chart-bar"></i> Atur Gaji Strata Karyawan
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Atur Gaji Strata Guru -->
        <div class="modal fade" id="modalGajiStrataGuru" tabindex="-1" aria-labelledby="modalGajiStrataGuruLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <form id="gaji-strata-form-guru" method="POST" class="modal-content">
                    <input type="hidden" name="case" value="update_gaji_strata_guru">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalGajiStrataGuruLabel">Atur Gaji Pokok Berdasarkan Strata Pendidikan (Guru)</h5>
                        <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Jenjang</th>
                                        <th>Strata Pendidikan</th>
                                        <th>Gaji Pokok (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($guruConfig as $jenjang => $strataArr): ?>
                                        <?php $first = true; ?>
                                        <?php foreach ($strataArr as $strata): ?>
                                            <tr>
                                                <?php if ($first): ?>
                                                    <td rowspan="<?= count($strataArr) ?>"><?= htmlspecialchars($jenjang) ?></td>
                                                    <?php $first = false; ?>
                                                <?php endif; ?>
                                                <td><?= htmlspecialchars($strata) ?></td>
                                                <td>
                                                    <input type="number" step="0.01"
                                                        name="<?= strtolower(str_replace('/', '', $jenjang)) . '_' . strtolower($strata) ?>"
                                                        class="form-control"
                                                        value="<?= isset($guruStrata[$jenjang][$strata]) ? $guruStrata[$jenjang][$strata] : '' ?>" required>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Atur Gaji Strata Karyawan -->
        <div class="modal fade" id="modalGajiStrataKaryawan" tabindex="-1" aria-labelledby="modalGajiStrataKaryawanLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <form id="gaji-strata-form-karyawan" method="POST" class="modal-content">
                    <input type="hidden" name="case" value="update_gaji_strata_karyawan">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalGajiStrataKaryawanLabel">Atur Gaji Pokok Berdasarkan Strata Pendidikan (Karyawan)</h5>
                        <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Jenjang</th>
                                        <th>Strata Pendidikan</th>
                                        <th>Gaji Pokok (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($karyawanConfig as $jenjang => $strataArr): ?>
                                        <?php $first = true; ?>
                                        <?php foreach ($strataArr as $strata): ?>
                                            <tr>
                                                <?php if ($first): ?>
                                                    <td rowspan="<?= count($strataArr) ?>"><?= htmlspecialchars($jenjang) ?></td>
                                                    <?php $first = false; ?>
                                                <?php endif; ?>
                                                <td><?= htmlspecialchars($strata) ?></td>
                                                <td>
                                                    <input type="number" step="0.01"
                                                        name="<?= strtolower(str_replace('/', '', $jenjang)) . '_' . strtolower($strata) ?>"
                                                        class="form-control"
                                                        value="<?= isset($karyawanStrata[$jenjang][$strata]) ? $karyawanStrata[$jenjang][$strata] : '' ?>" required>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- JavaScript Dependencies -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });

            function showToast(message, icon = 'success') {
                Toast.fire({
                    icon: icon,
                    title: message
                });
            }

            function getStatusBadge(status) {
                let s = (status || '').toLowerCase();
                if (s === 'tetap') {
                    return '<span class="badge bg-success">Tetap</span>';
                } else if (s === 'kontrak') {
                    return '<span class="badge bg-warning text-dark">Kontrak</span>';
                } else {
                    return '<span class="badge bg-secondary">' + (status || '') + '</span>';
                }
            }

            $(document).ready(function() {
                let currentPage = 1;
                let pageSize = 10;

                loadGuru(1);

                $('#btnApplyFilter').on('click', function() {
                    loadGuru(1);
                });
                $('#btnResetFilter').on('click', function() {
                    $('#filterForm')[0].reset();
                    loadGuru(1);
                });

                function loadGuru(page) {
                    currentPage = page;
                    let start = (currentPage - 1) * pageSize;

                    $.ajax({
                        url: "manage_guru_karyawan.php?ajax=1",
                        type: "POST",
                        data: {
                            case: 'LoadingGuru',
                            start: start,
                            length: pageSize,
                            jenjang: $('#filterJenjang').val(),
                            role: $('#filterRole').val(),
                            status_kerja: $('#filterStatus').val(),
                            csrf_token: "<?= htmlspecialchars($csrf_token); ?>"
                        },
                        dataType: "json",
                        beforeSend: function() {
                            $('#loadingSpinner').show();
                        },
                        success: function(res) {
                            $('#loadingSpinner').hide();
                            if (res.data) {
                                generateCards(res.data);
                                generatePagination(res.recordsTotal);
                            } else {
                                showToast('Data kosong atau gagal di-load.', 'warning');
                                $('#employeeCards').empty();
                                $('#paginationContainer').empty();
                            }
                        },
                        error: function() {
                            $('#loadingSpinner').hide();
                            showToast('Terjadi kesalahan saat memuat data.', 'error');
                        }
                    });
                }

                let baseUrl = "<?= getBaseUrl(); ?>";

                function generateCards(data) {
                    let container = $('#employeeCards');
                    container.empty();

                    data.forEach(item => {
                        let photoUrl = item.foto_profil && item.foto_profil !== '' ?
                            item.foto_profil :
                            baseUrl + "/assets/img/undraw_profile.svg";

                        let cardHtml = `
                <div class="col">
                  <div class="card shadow-sm text-center p-3 h-100">
                    <img src="${photoUrl}"
                         alt="Foto Profil"
                         class="rounded-circle mb-2"
                         style="width: 60px; height: 60px; object-fit: cover; margin: 0 auto;">
                    <h6 class="mb-0">${item.nama}</h6>
                    <p class="text-muted" style="font-size:0.9rem;">NIP: ${item.nip}</p>
                    <p style="font-size:0.85rem;">
                      <strong>Masa Kerja:</strong> ${item.masa_kerja || '0 Thn'}<br>
                      <strong>Jenjang:</strong> ${item.jenjang || '-'}<br>
                      <strong>Role:</strong> ${item.role} | ${getStatusBadge(item.status_kerja)}
                    </p>
                    <div class="d-grid gap-2">
                      <button class="btn btn-sm btn-primary btn-view" data-id="${item.id}">
                        <i class="fas fa-eye"></i> Detail
                      </button>
                      <button class="btn btn-sm btn-warning btn-edit" data-id="${item.id}">
                        <i class="fas fa-pencil-alt"></i> Edit
                      </button>
                      <button class="btn btn-sm btn-danger btn-delete" data-id="${item.id}">
                        <i class="fas fa-trash-alt"></i> Hapus
                      </button>
                    </div>
                  </div>
                </div>
                `;
                        container.append(cardHtml);
                    });
                }

                function generatePagination(totalRecords) {
                    let totalPages = Math.ceil(totalRecords / pageSize);
                    let pagination = $('#paginationContainer');
                    pagination.empty();

                    for (let i = 1; i <= totalPages; i++) {
                        let li = $('<li>').addClass('page-item').append(
                            $('<a>').addClass('page-link').text(i).attr('href', '#').on('click', function(e) {
                                e.preventDefault();
                                loadGuru(i);
                            })
                        );
                        if (i === currentPage) {
                            li.addClass('active');
                        }
                        pagination.append(li);
                    }
                }

                $('#addStatusPerkawinan').on('change', function() {
                    if ($(this).val() === 'Belum Menikah') {
                        $('#addNamaPasangan, #addJumlahAnak, #addNamaAnak1, #addNamaAnak2, #addNamaAnak3')
                            .prop('disabled', true)
                            .val('-');
                    } else {
                        $('#addNamaPasangan, #addJumlahAnak, #addNamaAnak1, #addNamaAnak2, #addNamaAnak3')
                            .prop('disabled', false)
                            .val('');
                    }
                });

                $('#editStatusPerkawinan').on('change', function() {
                    if ($(this).val() === 'Belum Menikah') {
                        $('#editNamaPasangan, #editJumlahAnak, #editNamaAnak1, #editNamaAnak2, #editNamaAnak3')
                            .prop('disabled', true)
                            .val('-');
                    } else {
                        $('#editNamaPasangan, #editJumlahAnak, #editNamaAnak1, #editNamaAnak2, #editNamaAnak3')
                            .prop('disabled', false)
                            .val('');
                    }
                });

                // View Detail
                $(document).on('click', '.btn-view', function() {
                    var id = $(this).data('id');
                    $.ajax({
                        url: "manage_guru_karyawan.php?ajax=1",
                        type: "POST",
                        data: {
                            case: 'GetGuruDetail',
                            id: id,
                            csrf_token: "<?= htmlspecialchars($csrf_token); ?>"
                        },
                        dataType: "json",
                        beforeSend: function() {
                            $('#loadingSpinner').show();
                        },
                        success: function(response) {
                            $('#loadingSpinner').hide();
                            if (response.code == 0) {
                                $('#detailNip').text(response.result.nip);
                                $('#detailUid').text(response.result.uid);
                                $('#detailNama').text(response.result.nama);
                                $('#detailJenjang').text(response.result.jenjang);
                                $('#detailJobTitle').text(response.result.job_title);
                                $('#detailRole').text(response.result.role);
                                $('#detailStatusKerja').html(getStatusBadge(response.result.status_kerja));
                                $('#detailJoinStart').text(response.result.join_start);
                                $('#detailMasaKerja').text(response.result.masa_kerja);
                                $('#detailPendidikan').text(response.result.pendidikan);
                                $('#detailSalaryIndexId').text(response.result.salary_level);
                                $('#detailJK').text(response.result.jk);
                                $('#detailTglLahir').text(response.result.tanggal_lahir);
                                $('#detailUsia').text(response.result.usia);
                                $('#detailReligion').text(response.result.religion);
                                $('#detailAlamatDomisili').text(response.result.alamat_domisili);
                                $('#detailAlamatKTP').text(response.result.alamat_ktp);
                                $('#detailNoRekening').text(response.result.no_rekening);
                                $('#detailNoHP').text(response.result.no_hp);
                                $('#detailEmail').text(response.result.email);
                                $('#detailRemark').text(response.result.remark || '');
                                $('#detailStatusPerkawinan').text(response.result.status_perkawinan);
                                $('#detailNamaPasangan').text(response.result.nama_pasangan || '');
                                $('#detailJumlahAnak').text(response.result.jumlah_anak || 0);
                                $('#detailNamaAnak1').text(response.result.nama_anak_1 || '');
                                $('#detailNamaAnak2').text(response.result.nama_anak_2 || '');
                                $('#detailNamaAnak3').text(response.result.nama_anak_3 || '');
                                $('#modalView').modal('show');
                            } else {
                                showToast(response.result, 'error');
                            }
                        },
                        error: function() {
                            $('#loadingSpinner').hide();
                            showToast('Terjadi kesalahan saat mengambil detail.', 'error');
                        }
                    });
                });

                /* ===== Toggle Lama Kontrak sesuai Status Kerja ===== */
                function toggleKontrak(prefix) {
                    const status = $(`#${prefix}StatusKerja`).val();
                    if (status === 'Kontrak') {
                        $(`.${prefix}-kontak-only`).removeClass('d-none');
                        $(`#${prefix}LamaKontrak`).prop('required', true);
                    } else {
                        $(`.${prefix}-kontak-only`).addClass('d-none');
                        $(`#${prefix}LamaKontrak`).prop('required', false).val('');
                    }
                }
                $('#addStatusKerja').on('change', () => toggleKontrak('add'));
                $('#editStatusKerja').on('change', () => toggleKontrak('edit'));
                $('#modalAdd').on('shown.bs.modal', () => toggleKontrak('add'));
                $('#modalEdit').on('shown.bs.modal', () => toggleKontrak('edit'));

                function toggleLamaKontrakEdit() {
                    var status = $('#editStatusKerja').val();
                    if (status === 'Kontrak') {
                        $('#editLamaKontrakContainer').removeClass('d-none');
                        $('#editLamaKontrak').prop('required', true);
                    } else {
                        $('#editLamaKontrakContainer').addClass('d-none');
                        $('#editLamaKontrak').prop('required', false).val('');
                    }
                }

                // Bind event saat dropdown editStatusKerja berubah
                $('#editStatusKerja').on('change', function() {
                    toggleLamaKontrakEdit();
                });

                // Panggil sekali saat modal edit ditampilkan
                $('#modalEdit').on('shown.bs.modal', function() {
                    toggleLamaKontrakEdit();
                });


                // Event handler untuk tombol Edit
                $(document).on('click', '.btn-edit', function() {
                    var id = $(this).data('id');
                    var modal = $('#modalEdit');
                    var form = $('#edit-guru-form');
                    // Reset form dan hilangkan kelas validasi sebelumnya
                    form[0].reset();
                    form.removeClass('was-validated');

                    $.ajax({
                        url: "manage_guru_karyawan.php?ajax=1",
                        type: "POST",
                        data: {
                            case: 'GetGuruDetail',
                            id: id,
                            csrf_token: "<?= htmlspecialchars($csrf_token); ?>"
                        },
                        dataType: "json",
                        beforeSend: function() {
                            $('#loadingSpinner').show();
                        },
                        success: function(response) {
                            $('#loadingSpinner').hide();
                            if (response.code == 0) {
                                // Populate field pada modal edit
                                $('#editId').val(response.result.id);
                                $('#editNip').val(response.result.nip);
                                $('#editUid').val(response.result.uid);
                                $('#editNama').val(response.result.nama);
                                $('#editJenjang').val((response.result.jenjang || '').toUpperCase());
                                $('#editJobTitle').val(response.result.job_title || '');
                                $('#editRole').val(response.result.role || '');
                                $('#editJK').val(response.result.jk || '');
                                $('#editTglLahir').val(response.result.tanggal_lahir || '');
                                $('#editUsia').val(response.result.usia || '');
                                $('#editReligion').val(response.result.religion || '');
                                $('#editAlamatDomisili').val(response.result.alamat_domisili || '');
                                $('#editAlamatKTP').val(response.result.alamat_ktp || '');
                                $('#editNoRekening').val(response.result.no_rekening || '');
                                $('#editNoHP').val(response.result.no_hp || '');
                                $('#editEmail').val(response.result.email || '');
                                $('#editPendidikan').val(response.result.pendidikan || '');
                                $('#editStatusPerkawinan').val(response.result.status_perkawinan || '');
                                $('#editRemark').val(response.result.remark || '');
                                $('#editNamaPasangan').val(response.result.nama_pasangan || '');
                                $('#editJumlahAnak').val(response.result.jumlah_anak || 0);
                                $('#editNamaAnak1').val(response.result.nama_anak_1 || '');
                                $('#editNamaAnak2').val(response.result.nama_anak_2 || '');
                                $('#editNamaAnak3').val(response.result.nama_anak_3 || '');
                                $('#editJoinStart').val(response.result.join_start || '');

                                // --- MODIFIKASI UNTUK STATUS KERJA ---
                                // Set select status kerja sesuai data dari server (misalnya "Kontrak" atau "Tetap")
                                $('#editStatusKerja').val(response.result.status_kerja || 'Tetap');
                                // Jika status kerja adalah "Kontrak", populate field lama kontrak
                                if (response.result.status_kerja === 'Kontrak') {
                                    $('#editLamaKontrak').val(response.result.lama_kontrak || '12');
                                }
                                // Panggil fungsi toggle agar container field "Lama Kontrak" tampil/tersimpan dengan benar
                                toggleLamaKontrakEdit();

                                modal.modal('show');
                            } else {
                                showToast(response.result, 'error');
                            }
                        },
                        error: function() {
                            $('#loadingSpinner').hide();
                            showToast('Terjadi kesalahan saat mengambil detail.', 'error');
                        }
                    });
                });



                $(document).on('click', '.btn-delete', function() {
                    var id = $(this).data('id');
                    $('#delId').val(id);
                    $('#delNama').text('ID: ' + id);
                    $('#modalDelete').modal('show');
                });

                $('#delete-guru-form').on('submit', function(e) {
                    e.preventDefault();
                    var id = $('#delId').val();
                    if (!id) {
                        showToast('ID tidak ditemukan.', 'error');
                        return;
                    }
                    var form = $(this);
                    $.ajax({
                        url: "manage_guru_karyawan.php?ajax=1",
                        type: "POST",
                        data: {
                            case: 'DeleteGuru',
                            id: id,
                            csrf_token: "<?= htmlspecialchars($csrf_token); ?>"
                        },
                        dataType: "json",
                        beforeSend: function() {
                            form.find('button[type="submit"]').prop('disabled', true);
                            form.find('.spinner-border').removeClass('d-none');
                        },
                        success: function(response) {
                            form.find('button[type="submit"]').prop('disabled', false);
                            form.find('.spinner-border').addClass('d-none');
                            if (response.code == 0) {
                                showToast(response.result);
                                $('#modalDelete').modal('hide');
                                loadGuru(currentPage);
                            } else {
                                showToast(response.result, 'error');
                            }
                        },
                        error: function() {
                            form.find('button[type="submit"]').prop('disabled', false);
                            form.find('.spinner-border').addClass('d-none');
                            showToast('Terjadi kesalahan saat menghapus data.', 'error');
                        }
                    });
                });

                $('#edit-guru-form').on('submit', function(e) {
                    e.preventDefault();
                    var form = $(this);
                    if (!this.checkValidity()) {
                        e.stopPropagation();
                        form.addClass('was-validated');
                        return;
                    }
                    $.ajax({
                        url: "manage_guru_karyawan.php?ajax=1",
                        type: "POST",
                        data: form.serialize(),
                        dataType: "json",
                        beforeSend: function() {
                            form.find('button[type="submit"]').prop('disabled', true);
                            form.find('.spinner-border').removeClass('d-none');
                        },
                        success: function(response) {
                            form.find('button[type="submit"]').prop('disabled', false);
                            form.find('.spinner-border').addClass('d-none');
                            if (response.code == 0) {
                                showToast(response.result);
                                $('#modalEdit').modal('hide');
                                loadGuru(currentPage);
                            } else {
                                showToast(response.result, 'error');
                            }
                        },
                        error: function() {
                            form.find('button[type="submit"]').prop('disabled', false);
                            form.find('.spinner-border').addClass('d-none');
                            showToast('Terjadi kesalahan saat mengupdate data.', 'error');
                        }
                    });
                });

                $('#add-guru-form').on('submit', function(e) {
                    e.preventDefault();
                    var form = $(this);
                    if (!this.checkValidity()) {
                        e.stopPropagation();
                        form.addClass('was-validated');
                        return;
                    }
                    $.ajax({
                        url: "manage_guru_karyawan.php?ajax=1",
                        type: "POST",
                        data: form.serialize(),
                        dataType: "json",
                        beforeSend: function() {
                            form.find('button[type="submit"]').prop('disabled', true);
                            form.find('.spinner-border').removeClass('d-none');
                        },
                        success: function(response) {
                            form.find('button[type="submit"]').prop('disabled', false);
                            form.find('.spinner-border').addClass('d-none');
                            if (response.code == 0) {
                                showToast(response.result);
                                $('#modalAdd').modal('hide');
                                loadGuru(1);
                                form[0].reset();
                                form.removeClass('was-validated');
                            } else {
                                showToast(response.result, 'error');
                            }
                        },
                        error: function() {
                            form.find('button[type="submit"]').prop('disabled', false);
                            form.find('.spinner-border').addClass('d-none');
                            showToast('Terjadi kesalahan saat menambah data.', 'error');
                        }
                    });
                });

                $('#gaji-strata-form-guru').on('submit', function(e) {
                    e.preventDefault();
                    var form = $(this);
                    if (!this.checkValidity()) {
                        e.stopPropagation();
                        form.addClass('was-validated');
                        return;
                    }
                    $.ajax({
                        url: "manage_guru_karyawan.php?ajax=1",
                        type: "POST",
                        data: form.serialize(),
                        dataType: "json",
                        beforeSend: function() {
                            form.find('button[type="submit"]').prop('disabled', true);
                            form.find('.spinner-border').removeClass('d-none');
                        },
                        success: function(response) {
                            form.find('button[type="submit"]').prop('disabled', false);
                            form.find('.spinner-border').addClass('d-none');
                            if (response.code == 0) {
                                showToast(response.result);
                                $('#modalGajiStrataGuru').modal('hide');
                            } else {
                                showToast(response.result, 'error');
                            }
                        },
                        error: function() {
                            form.find('button[type="submit"]').prop('disabled', false);
                            form.find('.spinner-border').addClass('d-none');
                            showToast('Terjadi kesalahan saat mengupdate gaji strata Guru.', 'error');
                        }
                    });
                });

                $('#gaji-strata-form-karyawan').on('submit', function(e) {
                    e.preventDefault();
                    var form = $(this);
                    if (!this.checkValidity()) {
                        e.stopPropagation();
                        form.addClass('was-validated');
                        return;
                    }
                    $.ajax({
                        url: "manage_guru_karyawan.php?ajax=1",
                        type: "POST",
                        data: form.serialize(),
                        dataType: "json",
                        beforeSend: function() {
                            form.find('button[type="submit"]').prop('disabled', true);
                            form.find('.spinner-border').removeClass('d-none');
                        },
                        success: function(response) {
                            form.find('button[type="submit"]').prop('disabled', false);
                            form.find('.spinner-border').addClass('d-none');
                            if (response.code == 0) {
                                showToast(response.result);
                                $('#modalGajiStrataKaryawan').modal('hide');
                            } else {
                                showToast(response.result, 'error');
                            }
                        },
                        error: function() {
                            form.find('button[type="submit"]').prop('disabled', false);
                            form.find('.spinner-border').addClass('d-none');
                            showToast('Terjadi kesalahan saat mengupdate gaji strata Karyawan.', 'error');
                        }
                    });
                });

                // Tambahan: Event binding untuk tombol di dalam modalGajiPokok agar tidak terjadi nested modal
                $('#btnGajiStrataGuru').on('click', function() {
                    $('#modalGajiPokok').modal('hide');
                    // Pastikan modalGajiStrataGuru sudah terinisialisasi
                    $('#modalGajiStrataGuru').modal('show');
                });
                $('#btnGajiStrataKaryawan').on('click', function() {
                    $('#modalGajiPokok').modal('hide');
                    $('#modalGajiStrataKaryawan').modal('show');
                });

                // Navigasi ke halaman lain
                $(document).on('click', '#btnManageSalaryIndices', function(e) {
                    e.preventDefault();
                    var url = $(this).data('href');
                    $('#content-wrapper').fadeOut(300, function() {
                        window.location.href = url;
                    });
                });
                $(document).on('click', '#btnManageHolidays', function(e) {
                    e.preventDefault();
                    var url = $(this).data('href');
                    $('#content-wrapper').fadeOut(300, function() {
                        window.location.href = url;
                    });
                });
            });
        </script>
</body>

</html>