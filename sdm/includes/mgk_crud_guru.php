<?php

/**
 *  mgk_crud_guru.php
 *  -----------------------------------------------------------------
 *  CRUD (Create-Read-Update-Delete) + helper generateUID
 *  untuk halaman  manage_guru_karyawan.php
 *
 *  Dependency:
 *    – koneksi $conn            (di-require oleh controller)
 *    – $_SESSION (start_session_safe() sudah dipanggil di controller)
 *    – mgk_date_utils.php
 *    – mgk_upload_handler.php
 *    – mgk_salary_handler.php
 */

require_once __DIR__ . '/mgk_date_utils.php';
require_once __DIR__ . '/mgk_upload_handler.php';
require_once __DIR__ . '/mgk_salary_handler.php';

/* ================================================================
 * 0. Fungsi Helper Tambahan
 * ================================================================ */
if (!function_exists('slugify')) {
    function slugify(string $s): string
    {
        return strtolower(preg_replace('/\s+/', '_', trim($s)));
    }
}


/* ================================================================
 * 1. LoadingGuru  (DataTables style)
 * ================================================================ */
function LoadingGuru($conn)
{
    // 1. Ambil parameter paging & filter
    $start   = isset($_POST['start'])         ? intval($_POST['start']) : 0;
    $length  = isset($_POST['length'])        ? intval($_POST['length']) : 10;
    // Tambahan: Ambil juga keyword dari filter form
    $keyword = isset($_POST['keyword']) ? bersihkan_input($_POST['keyword']) : '';
    // Gabungkan pencarian dari DataTables dan filter form (prioritaskan DataTables jika ada)
    $search  = '';
    if (isset($_POST['search']['value']) && $_POST['search']['value'] !== '') {
        $search = bersihkan_input($_POST['search']['value']);
    } elseif (!empty($keyword)) {
        $search = $keyword;
    }

    $jenjang = isset($_POST['jenjang'])       ? bersihkan_input($_POST['jenjang']) : '';
    $role    = isset($_POST['role'])          ? bersihkan_input($_POST['role']) : '';
    $status  = isset($_POST['status_kerja'])  ? bersihkan_input($_POST['status_kerja']) : '';

    // 2. Hitung total data tanpa filter
    $rowTotal = mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT COUNT(*) AS total FROM anggota_sekolah WHERE is_delete = 0")
    );
    $recordsTotal = $rowTotal['total'] ?? 0;

    // 3. Bangun klausa WHERE dinamis
    $where = "WHERE is_delete = 0";
    $params = [];
    $types  = "";

    // Tambahan: cari juga di job_title
    if ($search) {
        $where .= " AND (nip LIKE CONCAT('%', ?, '%') OR nama LIKE CONCAT('%', ?, '%') OR job_title LIKE CONCAT('%', ?, '%'))";
        $types   .= "sss";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    if ($jenjang) {
        $where .= " AND jenjang = ?";
        $types   .= "s";
        $params[] = $jenjang;
    }
    if ($role) {
        $where .= " AND role = ?";
        $types   .= "s";
        $params[] = $role;
    }
    if ($status) {
        $where .= " AND status_kerja = ?";
        $types   .= "s";
        $params[] = $status;
    }

    // 4. Siapkan SQL dengan LIMIT ?, ?
    $sql = "
SELECT
    a.id, a.uid, a.nip, a.nama, a.jenjang, a.unit_penempatan, a.job_title, a.role, a.status_kerja,
    a.join_start, a.lama_kontrak, a.tgl_kontrak_selesai,
    a.masa_kerja_tahun, a.masa_kerja_bulan, a.masa_kerja_efektif,
    a.pendidikan, a.email, a.no_hp,
    a.foto_profil, a.foto_ktp,
    a.faskes_bpjs, a.faskes_inhealth, a.faskes_ket,
    j.color_bg as jenjang_bg,
    j.color_fg as jenjang_fg
FROM anggota_sekolah a
LEFT JOIN jenjang_sekolah j ON a.jenjang = j.kode_jenjang
$where
ORDER BY a.id DESC
LIMIT ?, ?
";


    // tambahkan parameter untuk LIMIT
    $types   .= "ii";
    $params[] = $start;
    $params[] = $length;

    // 5. Eksekusi prepared statement
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_response(1, 'Query error: ' . $conn->error);
    }
    // bind semua param
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    // 6. Ambil data dan format
    $data = [];
    while ($row = $res->fetch_assoc()) {
    $masa = $row['masa_kerja_tahun'] . ' Thn ' . $row['masa_kerja_bulan'] . ' Bln';
    $data[] = [
        "id"           => $row['id'],
        "uid"          => htmlspecialchars($row['uid'] ?? ''),
        "nip"          => htmlspecialchars($row['nip'] ?? ''),
        "nama"         => htmlspecialchars($row['nama'] ?? ''),
        "jenjang"      => $row['jenjang'],
        'unit_penempatan' => $row['unit_penempatan'],
        "jenjang_badge" => getBadgeJenjang($row['jenjang'], $conn),
        "jenjang_bg"   => $row['jenjang_bg'] ?? null,   // <- Tambahkan ini
        "jenjang_fg"   => $row['jenjang_fg'] ?? null,   // <- Tambahkan ini
        "job_title"    => htmlspecialchars($row['job_title'] ?? ''),
        "role"         => $row['role'],
        "status_kerja" => $row['status_kerja'],
        "join_start"   => $row['join_start'],
        "lama_kontrak" => $row['lama_kontrak'],
        "tgl_kontrak_selesai" => $row['tgl_kontrak_selesai'],
        "masa_kerja"   => $masa,
        "pendidikan"   => htmlspecialchars($row['pendidikan'] ?? ''),
        "email"        => htmlspecialchars($row['email'] ?? ''),
        "no_hp"        => htmlspecialchars($row['no_hp'] ?? ''),
        "foto_profil"  => getProfilePhotoUrl($row['foto_profil']),
        "foto_ktp"     => getKtpPhotoUrl($row['foto_ktp']),
        "faskes_bpjs"     => $row['faskes_bpjs'],
        "faskes_inhealth" => $row['faskes_inhealth'],
        "faskes_ket"      => $row['faskes_ket'],
    ];
}
    $stmt->close();

    // 7. Kirim hasil
    echo json_encode(["recordsTotal" => $recordsTotal, "data" => $data], JSON_UNESCAPED_UNICODE);
    exit();
}


function CreateGuru(mysqli $conn)
{
    // 1. Sanitasi & validasi dasar
    $nip        = bersihkan_input($_POST['nip']      ?? '');
    $nama       = bersihkan_input($_POST['nama']     ?? '');
    $jenjang    = bersihkan_input($_POST['jenjang']  ?? '');
    $unit_penempatan = bersihkan_input($_POST['unit_penempatan'] ?? '');
    $job_title  = bersihkan_input($_POST['job_title'] ?? '');
    $role       = bersihkan_input($_POST['role']     ?? '');
    $strata     = bersihkan_input($_POST['strata']   ?? '');
    $kategori   = bersihkan_input($_POST['kategori'] ?? 'guru');
    if (!$nip || !$nama || !$jenjang || !$role) {
        send_response(1, 'NIP, Nama, Jenjang, dan Role wajib diisi.');
    }

    // 1a. Slug untuk nama file
    $slugify = fn(string $s) => strtolower(preg_replace('/\s+/', '_', trim($s)));
    $slugName   = $slugify($nama);
    $slugJenjang = $slugify($jenjang);
    $slugRole   = strtolower($role);

    // 2. Cek duplikasi NIP
    $stmtDup = $conn->prepare(
        "SELECT id FROM anggota_sekolah WHERE nip=? AND is_delete=0"
    );
    $stmtDup->bind_param('s', $nip);
    $stmtDup->execute();
    if ($stmtDup->get_result()->num_rows) {
        send_response(1, 'NIP sudah digunakan.');
    }
    $stmtDup->close();

    // 3. Hitung data turunan
    $uid = generateUID($conn, $jenjang);
    $status_kerja = bersihkan_input($_POST['status_kerja'] ?? 'Tetap');
    $join_start   = bersihkan_input($_POST['join_start']   ?? '');
    $lama_kontrak = ($status_kerja === 'Kontrak') ? intval($_POST['lama_kontrak'] ?? 12) : null;
    $tgl_kontrak  = ($status_kerja === 'Kontrak' && $join_start)
        ? hitungTanggalSelesaiKontrak($join_start, $lama_kontrak)
        : null;

    [$masa_tahun, $masa_bulan, $masa_eff] = calcMasaKerja($join_start);

    $pendidikan  = bersihkan_input($_POST['pendidikan'] ?? '');
    $gaji_pokok  = hitungGajiPokok($conn, $role, $pendidikan, $jenjang);
    $defaultPass = password_hash('123456', PASSWORD_DEFAULT);

    // 4. Field foto disiapkan kosong
    $foto_profil_url = '';
    $foto_ktp_url    = '';

    // 5. Data pribadi & kontak
    $remark            = bersihkan_input($_POST['remark']            ?? '');
    $jk                = bersihkan_input($_POST['jk']                ?? '');
    $tgl_lahir         = bersihkan_input($_POST['tgl_lahir']         ?? '');
    $usia              = calcAge($tgl_lahir);
    $religion          = bersihkan_input($_POST['religion']          ?? '');
    $alamat_domisili   = bersihkan_input($_POST['alamat_domisili']   ?? '');
    $alamat_ktp        = bersihkan_input($_POST['alamat_ktp']        ?? '');
    $no_rekening       = bersihkan_input($_POST['no_rekening']       ?? '');
    $no_hp             = bersihkan_input($_POST['no_hp']             ?? '');
    $status_perkawinan = bersihkan_input($_POST['status_perkawinan'] ?? '');
    $email             = bersihkan_input($_POST['email']             ?? '');
    $nama_pasangan     = bersihkan_input($_POST['nama_pasangan']     ?? '');
    $jumlah_anak       = intval($_POST['jumlah_anak']               ?? 0);
    $nama_anak_1       = bersihkan_input($_POST['nama_anak_1']       ?? '');
    $nama_anak_2       = bersihkan_input($_POST['nama_anak_2']       ?? '');
    $nama_anak_3       = bersihkan_input($_POST['nama_anak_3']       ?? '');
    $faskes_bpjs     = isset($_POST['faskes_bpjs']) ? intval($_POST['faskes_bpjs']) : 0;
    $faskes_inhealth = isset($_POST['faskes_inhealth']) ? intval($_POST['faskes_inhealth']) : 0;
    $faskes_ket      = bersihkan_input($_POST['faskes_ket'] ?? null);

    if ($status_perkawinan === 'Belum Menikah') {
        $nama_pasangan = '-';
        $jumlah_anak   = 0;
        $nama_anak_1 = $nama_anak_2 = $nama_anak_3 = '-';
    }

    // 6. INSERT baris baru (foto masih kosong)
    $salary_index_id = null;
    $sudah_kontrak = 0; // default (harus isi, INT!)

    $sql = "INSERT INTO anggota_sekolah (
    uid, nip, password, nama, jenjang, unit_penempatan, strata, job_title, status_kerja,
    join_start, lama_kontrak, tgl_kontrak_selesai, sudah_kontrak,
    masa_kerja_tahun, masa_kerja_bulan, masa_kerja_efektif,
    remark, jenis_kelamin, tanggal_lahir, usia, agama,
    alamat_domisili, alamat_ktp, no_rekening, no_hp, pendidikan,
    status_perkawinan, email, nama_pasangan, jumlah_anak,
    nama_anak_1, nama_anak_2, nama_anak_3,  
    salary_index_id, gaji_pokok, foto_profil, foto_ktp, role, kategori,
    'faskes_bpjs', 'faskes_inhealth', 'faskes_ket'
    ) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )";

    // types: s=string, i=integer, d=double (decimal)
    $types = 'sssssssssisiiidsssis' . 'sssssssssisss' . 'idsss' . 'iis'; // total 41

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_response(1, 'Query error: ' . $conn->error);
    }
    $stmt->bind_param(
        $types,
        $uid,
        $nip,
        $defaultPass,
        $nama,
        $jenjang,
        $unit_penempatan,
        $strata,
        $job_title,
        $status_kerja,
        $join_start,
        $lama_kontrak,
        $tgl_kontrak,
        $sudah_kontrak,
        $masa_tahun,
        $masa_bulan,
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
        $foto_profil_url,
        $foto_ktp_url,
        $role,
        $kategori,
        $faskes_bpjs,
        $faskes_inhealth,
        $faskes_ket
    );

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        send_response(1, "Gagal menyimpan data: $err");
    }

    $newId = $stmt->insert_id;
    $stmt->close();

    // 7. Setelah INSERT → proses upload foto (optional)
    $slugBase = "{$slugName}_{$slugJenjang}_{$slugRole}";

    if (
        !empty($_FILES['foto_profil']['tmp_name']) &&
        $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK
    ) {
        if ($_FILES['foto_profil']['size'] > 2 * 1024 * 1024)
            send_response(1, 'Ukuran foto profil maks 2 MB.');

        $foto_profil_url = save_image_as_jpg(
            $_FILES['foto_profil']['tmp_name'],
            __DIR__ . '/../../uploads/profile_pics/',
            "{$slugBase}_{$newId}.jpg"
        );
    }

    if (
        !empty($_FILES['foto_ktp']['tmp_name']) &&
        $_FILES['foto_ktp']['error'] === UPLOAD_ERR_OK
    ) {
        if ($_FILES['foto_ktp']['size'] > 2 * 1024 * 1024)
            send_response(1, 'Ukuran foto KTP maks 2 MB.');

        $foto_ktp_url = save_image_as_jpg(
            $_FILES['foto_ktp']['tmp_name'],
            __DIR__ . '/../../uploads/ktp_pics/',
            "{$slugBase}_{$newId}_ktp.jpg"
        );
    }

    if ($foto_profil_url || $foto_ktp_url) {
        $u = $conn->prepare(
            "UPDATE anggota_sekolah
                SET foto_profil = IF(?='',foto_profil,?),
                    foto_ktp    = IF(?='',foto_ktp,?)
              WHERE id=?"
        );
        $empty = '';
        $u->bind_param(
            'ssssi',
            $foto_profil_url,
            $foto_profil_url,
            $foto_ktp_url,
            $foto_ktp_url,
            $newId
        );
        $u->execute();
        $u->close();
    }

    // 8. Post-proses
    updateSalaryIndexForUser($conn, $newId);
    add_audit_log(
        $conn,
        $_SESSION['nip'] ?? '',
        'CreateGuru',
        "Menambah Guru/Karyawan baru ID=$newId, NIP=$nip, Nama=$nama"
    );

    send_response(0, 'Data berhasil disimpan.');
}


/* =============================================================
 * 3. Fungsi GetGuruDetail: Mengambil detail 1 baris data
 * =========================================================== */
function GetGuruDetail(mysqli $conn): array
{
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        send_response(1, 'ID tidak valid.');
    }

    // 1. Ambil data beserta salary index
    $stmt = $conn->prepare("
        SELECT a.*,
               si.level       AS salary_level,
               si.description AS salary_desc
          FROM anggota_sekolah a
     LEFT JOIN salary_indices si
            ON a.salary_index_id = si.id
         WHERE a.id = ?
         LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();

    if (! $row = $res->fetch_assoc()) {
        send_response(2, 'Data tidak ditemukan.');
    }
    $stmt->close();

    // 2. Hilangkan password
    unset($row['password']);

    // 3. Pastikan sudah_kontrak ikut dikirim
    $row['sudah_kontrak'] = intval($row['sudah_kontrak'] ?? 0);

    // 4. Format masa kerja
    if ($row['status_kerja'] === 'Kontrak') {
        $row['masa_kerja'] = $row['sudah_kontrak'] . " Thn";
    } else {
        $row['masa_kerja'] =
            $row['masa_kerja_tahun'] . " Thn " .
            $row['masa_kerja_bulan'] . " Bln";
    }

    // 5. Samakan nama field singkat
    $row['religion'] = $row['agama'];
    $row['jk']       = $row['jenis_kelamin'];

    // 6. Bangun URL penuh untuk foto_profil & foto_ktp
    $base = getBaseUrl();
    foreach (['foto_profil', 'foto_ktp'] as $field) {
        $dbval    = $row[$field] ?? '';
        $filename = basename($dbval);
        $subdir   = $field === 'foto_profil'
            ? 'profile_pics' : 'ktp_pics';
        $local    = __DIR__ . "/../../uploads/{$subdir}/{$filename}";

        if (strpos($dbval, 'http') === 0) {
            $row[$field] = $dbval;
        } elseif ($filename && file_exists($local)) {
            $row[$field] = "{$base}/uploads/{$subdir}/{$filename}"
                . "?v=" . filemtime($local);
        } else {
            // placeholder umum
            $row[$field] = "{$base}/assets/img/undraw_profile.svg";
        }
    }
    $row['faskes_bpjs']     = intval($row['faskes_bpjs'] ?? 0);
    $row['faskes_inhealth'] = intval($row['faskes_inhealth'] ?? 0);
    $row['faskes_ket']      = $row['faskes_ket'] ?? '';

    send_response(0, $row);
}

function UpdateGuru(mysqli $conn)
{
    // 1. Validasi & data lama
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        send_response(1, 'ID tidak valid.');
    }

    $stmt0 = $conn->prepare(
        "SELECT nama, jenjang, foto_profil, foto_ktp
           FROM anggota_sekolah
          WHERE id=? LIMIT 1"
    );
    $stmt0->bind_param('i', $id);
    $stmt0->execute();
    $old = $stmt0->get_result()->fetch_assoc();
    $stmt0->close();

    if (!$old) {
        send_response(1, 'Data tidak ditemukan.');
    }

    // 2. Helper slug
    $slugify = fn(string $s) => strtolower(preg_replace('/\s+/', '_', trim($s)));

    // 3. Input utama
    $nip          = bersihkan_input($_POST['nip']          ?? '');
    $nama         = bersihkan_input($_POST['nama']         ?? $old['nama']);
    $jenjang      = bersihkan_input($_POST['jenjang']      ?? $old['jenjang']);
    $unit_penempatan = bersihkan_input($_POST['unit_penempatan'] ?? '');
    $job_title    = bersihkan_input($_POST['job_title']    ?? '');
    $role         = bersihkan_input($_POST['role']         ?? '');
    $strata       = bersihkan_input($_POST['strata']       ?? ''); // TAMBAHAN
    $status_kerja = bersihkan_input($_POST['status_kerja'] ?? 'Tetap');
    $kategori     = bersihkan_input($_POST['kategori']     ?? 'guru'); // kategori wajib ikut
    $join_start   = bersihkan_input($_POST['join_start']   ?? '');

    if (!$nip || !$nama || !$jenjang || !$role) {
        send_response(1, 'NIP, Nama, Jenjang dan Role wajib diisi.');
    }

    // 4. Cek duplikasi NIP
    $ck = $conn->prepare("SELECT id FROM anggota_sekolah WHERE nip=? AND id<>?");
    $ck->bind_param('si', $nip, $id);
    $ck->execute();
    if ($ck->get_result()->num_rows) {
        send_response(1, 'NIP sudah digunakan oleh user lain.');
    }
    $ck->close();

    // 5. Hitung kontrak & masa kerja
    $lama_kontrak = ($status_kerja === 'Kontrak') ? intval($_POST['lama_kontrak'] ?? 12) : null;
    $tgl_kontrak  = ($status_kerja === 'Kontrak' && $join_start)
        ? hitungTanggalSelesaiKontrak($join_start, $lama_kontrak) : null;
    [$masa_tahun, $masa_bulan, $masa_eff] = calcMasaKerja($join_start);

    // 6. Upload foto (jika ada)
    $fotoProfilUrl = $old['foto_profil'];
    $fotoKtpUrl    = $old['foto_ktp'];
    $slugBase = $slugify($nama) . '_' . $slugify($jenjang) . '_' . strtolower($role);

    // Profil
    if (
        !empty($_FILES['foto_profil']['tmp_name']) &&
        $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK
    ) {
        if ($_FILES['foto_profil']['size'] > 2 * 1024 * 1024)
            send_response(1, 'Ukuran foto profil maksimal 2MB.');

        $fotoProfilUrl = save_image_as_jpg(
            $_FILES['foto_profil']['tmp_name'],
            __DIR__ . '/../../uploads/profile_pics/',
            "{$slugBase}_{$id}.jpg"
        );
    }

    // KTP
    if (
        !empty($_FILES['foto_ktp']['tmp_name']) &&
        $_FILES['foto_ktp']['error'] === UPLOAD_ERR_OK
    ) {
        if ($_FILES['foto_ktp']['size'] > 2 * 1024 * 1024)
            send_response(1, 'Ukuran foto KTP maksimal 2MB.');

        $fotoKtpUrl = save_image_as_jpg(
            $_FILES['foto_ktp']['tmp_name'],
            __DIR__ . '/../../uploads/ktp_pics/',
            "{$slugBase}_{$id}_ktp.jpg"
        );
    }

    // 7. Data lainnya
    $remark            = bersihkan_input($_POST['remark']            ?? '');
    $jk                = bersihkan_input($_POST['jk']                ?? '');
    $tgl_lahir         = bersihkan_input($_POST['tgl_lahir']         ?? '');
    $usia              = intval($_POST['usia'] ?? 0);
    $religion          = bersihkan_input($_POST['religion']          ?? '');
    $alamat_domisili   = bersihkan_input($_POST['alamat_domisili']   ?? '');
    $alamat_ktp        = bersihkan_input($_POST['alamat_ktp']        ?? '');
    $no_rekening       = bersihkan_input($_POST['no_rekening']       ?? '');
    $no_hp             = formatPhoneNumber(bersihkan_input($_POST['no_hp'] ?? ''));
    $pendidikan        = bersihkan_input($_POST['pendidikan']        ?? '');
    $status_perkawinan = bersihkan_input($_POST['status_perkawinan'] ?? '');
    $email             = bersihkan_input($_POST['email']             ?? '');
    $nama_pasangan     = bersihkan_input($_POST['nama_pasangan']     ?? '');
    $jumlah_anak       = intval($_POST['jumlah_anak']               ?? 0);
    $nama_anak_1       = bersihkan_input($_POST['nama_anak_1']       ?? '');
    $nama_anak_2       = bersihkan_input($_POST['nama_anak_2']       ?? '');
    $nama_anak_3       = bersihkan_input($_POST['nama_anak_3']       ?? '');
    $faskes_bpjs     = isset($_POST['faskes_bpjs']) ? intval($_POST['faskes_bpjs']) : 0;
    $faskes_inhealth = isset($_POST['faskes_inhealth']) ? intval($_POST['faskes_inhealth']) : 0;
    $faskes_ket      = bersihkan_input($_POST['faskes_ket'] ?? null);

    if ($status_perkawinan === 'Belum Menikah') {
        $nama_pasangan = '-';
        $jumlah_anak   = 0;
        $nama_anak_1 = $nama_anak_2 = $nama_anak_3 = '-';
    }

    // 8. Siapkan array kolom (kategori sekarang wajib ikut)
    $cols = [
        'nip' => $nip,
        'nama' => $nama,
        'jenjang' => $jenjang,
        'unit_penempatan' => $unit_penempatan,
        'job_title' => $job_title,
        'role' => $role,
        'status_kerja' => $status_kerja,
        'kategori' => $kategori,
        'join_start' => $join_start,
        'lama_kontrak' => $lama_kontrak,
        'tgl_kontrak_selesai' => $tgl_kontrak,
        'masa_kerja_tahun' => $masa_tahun,
        'masa_kerja_bulan' => $masa_bulan,
        'masa_kerja_efektif' => $masa_eff,
        'remark' => $remark,
        'jenis_kelamin' => $jk,
        'tanggal_lahir' => $tgl_lahir,
        'usia' => $usia,
        'agama' => $religion,
        'alamat_domisili' => $alamat_domisili,
        'alamat_ktp' => $alamat_ktp,
        'no_rekening' => $no_rekening,
        'no_hp' => $no_hp,
        'pendidikan' => $pendidikan,
        'strata' => $strata,           // <<<<<<==== TAMBAHAN DI SINI
        'status_perkawinan' => $status_perkawinan,
        'email' => $email,
        'nama_pasangan' => $nama_pasangan,
        'jumlah_anak' => $jumlah_anak,
        'nama_anak_1' => $nama_anak_1,
        'nama_anak_2' => $nama_anak_2,
        'nama_anak_3' => $nama_anak_3,
        'foto_profil' => $fotoProfilUrl,
        'foto_ktp' => $fotoKtpUrl,
        'faskes_bpjs'     => $faskes_bpjs,
        'faskes_inhealth' => $faskes_inhealth,
        'faskes_ket'      => $faskes_ket,
    ];

    $cols['salary_index_id'] = ($_POST['salary_index_id'] ?? '') !== ''
        ? intval($_POST['salary_index_id']) : null;

    // 9. Build dynamic UPDATE
    $setParts = [];
    $types = '';
    $vals = [];
    foreach ($cols as $c => $v) {
        if ($c === 'salary_index_id' && $v === null) {
            $setParts[] = "salary_index_id=NULL";
            continue;
        }
        $setParts[] = "$c=?";
        $types .= in_array($c, [
            'lama_kontrak',
            'masa_kerja_tahun',
            'masa_kerja_bulan',
            'usia',
            'jumlah_anak',
            'salary_index_id'
        ]) ? 'i'
            : ($c === 'masa_kerja_efektif' ? 'd' : 's');
        $vals[] = $v;
    }
    $types .= 'i';
    $vals[] = $id;

    $sql = "UPDATE anggota_sekolah SET " . implode(', ', $setParts) . " WHERE id=?";
    $st = $conn->prepare($sql);
    if (!$st) send_response(1, 'Query error: ' . $conn->error);
    $st->bind_param($types, ...$vals);

    if ($st->execute()) {
        $st->close();
        updateSalaryIndexForUser($conn, $id);
        add_audit_log(
            $conn,
            $_SESSION['nip'] ?? '',
            'UpdateGuru',
            "Update ID=$id, NIP=$nip"
        );
        send_response(0, 'Data berhasil diperbarui.');
    } else {
        $e = $st->error;
        $st->close();
        send_response(1, 'Gagal update data: ' . $e);
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

/* ================================================================
 * 6. generateUID  (8 char hex unik)
 * ================================================================ */
if (!function_exists('generateUID')) {
    function generateUID(mysqli $conn, string $jenjang): string
    {
        return mapJenjangToUidCode($jenjang); // Hanya 2 digit
    }
}

/**
 * Konversi teks jenjang (“TK”, “SMK 1”, dst.) menjadi
 * 2-digit string sesuai aturan perusahaan.
 */
function mapJenjangToUidCode(string $jenjang): string
{
    $map = [
        'tk'       => '01',
        'sd'       => '02',
        'smp'      => '03',
        'sma'      => '04',
        'smk 1'    => '05',
        'smk1'     => '05',   // agar “SMK1” tanpa spasi tetap ter-handle
        'smk 2'    => '06',
        'smk2'     => '06',
        'stifera'  => '07',
        'umum'     => '08',
    ];

    $key = strtolower(trim($jenjang));
    return $map[$key] ?? '00';           // fallback “00” bila jenjang tak dikenal
}
