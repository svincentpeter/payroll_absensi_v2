<?php
// File: crud_guru.php

/* =============================================================
 * 1. Fungsi LoadingGuru: Mengambil data anggota untuk grid
 * =========================================================== */
function LoadingGuru($conn)
{
    // 1. Ambil parameter paging & filter
    $start   = isset($_POST['start'])         ? intval($_POST['start']) : 0;
    $length  = isset($_POST['length'])        ? intval($_POST['length']) : 10;
    $search  = isset($_POST['search']['value']) ? bersihkan_input($_POST['search']['value']) : '';
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

    if ($search) {
        $where .= " AND (nip LIKE CONCAT('%', ?, '%') OR nama LIKE CONCAT('%', ?, '%'))";
        $types   .= "ss";
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
            id, uid, nip, nama, jenjang, job_title, role, status_kerja,
            join_start, lama_kontrak, tgl_kontrak_selesai,
            masa_kerja_tahun, masa_kerja_bulan, masa_kerja_efektif,
            pendidikan, email, no_hp,
            foto_profil, foto_ktp
        FROM anggota_sekolah
        $where
        ORDER BY id DESC
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
            "uid"          => htmlspecialchars($row['uid']),
            "nip"          => htmlspecialchars($row['nip']),
            "nama"         => htmlspecialchars($row['nama']),
            "jenjang"      => $row['jenjang'],
            "jenjang_badge" => getBadgeJenjang($row['jenjang']),
            "job_title"    => htmlspecialchars($row['job_title']),
            "role"         => $row['role'],
            "status_kerja" => $row['status_kerja'],
            "join_start"   => $row['join_start'],
            "lama_kontrak" => $row['lama_kontrak'],
            "tgl_kontrak_selesai" => $row['tgl_kontrak_selesai'],
            "masa_kerja"   => $masa,
            "pendidikan"   => htmlspecialchars($row['pendidikan']),
            "email"        => htmlspecialchars($row['email']),
            "no_hp"        => htmlspecialchars($row['no_hp']),
            "foto_profil"  => getProfilePhotoUrl($row['foto_profil']),
            "foto_ktp"     => getKtpPhotoUrl($row['foto_ktp']),
        ];
    }
    $stmt->close();

    // 7. Kirim hasil
    echo json_encode(["recordsTotal" => $recordsTotal, "data" => $data], JSON_UNESCAPED_UNICODE);
    exit();
}


/* =============================================================
 * 2. Fungsi CreateGuru: Insert data baru ke anggota_sekolah
 * =========================================================== */
function CreateGuru(mysqli $conn)
{
    // 1. Sanitasi input awal untuk nanti dipakai menamai file
    $nip        = bersihkan_input($_POST['nip']           ?? '');
    $nama       = bersihkan_input($_POST['nama']          ?? '');
    $jenjang    = bersihkan_input($_POST['jenjang']       ?? '');
    $job_title  = bersihkan_input($_POST['job_title']     ?? '');
    $role       = bersihkan_input($_POST['role']          ?? '');
    if (!$nip || !$nama || !$jenjang || !$role) {
        send_response(1, 'NIP, Nama, Jenjang, dan Role wajib diisi.');
    }
    // untuk nama file nanti
    $uName    = strtolower(preg_replace('/\s+/', '_', $nama));
    $uJenjang = strtolower(preg_replace('/\s+/', '_', $jenjang));
    $uRole    = strtolower($_SESSION['role'] ?? 'user');

    // 2. Cek duplikasi NIP
    $stmtDup = $conn->prepare("SELECT id FROM anggota_sekolah WHERE nip = ?");
    $stmtDup->bind_param('s', $nip);
    $stmtDup->execute();
    if ($stmtDup->get_result()->num_rows) {
        send_response(1, 'NIP sudah digunakan.');
    }
    $stmtDup->close();

    // 3. Hitung UID, kontrak, masa kerja & gaji pokok
    $uid           = generateUID($conn);
    $status_kerja  = bersihkan_input($_POST['status_kerja'] ?? 'Tetap');
    $join_start    = bersihkan_input($_POST['join_start']   ?? '');
    $lama_kontrak  = ($status_kerja === 'Kontrak') ? intval($_POST['lama_kontrak'] ?? 12) : null;
    $tgl_kontrak   = ($status_kerja === 'Kontrak' && $join_start)
        ? addMonths($join_start, $lama_kontrak)
        : null;
    [$masa_tahun, $masa_bulan, $masa_eff] = calcMasaKerja($join_start);
    $pendidikan    = bersihkan_input($_POST['pendidikan'] ?? '');
    $gaji_pokok    = hitungGajiPokok($conn, $role, $pendidikan, $jenjang);
    $defaultPass   = password_hash('123456', PASSWORD_DEFAULT);

    // 4. Proses upload FOTO PROFIL
    $foto_profil_url = '';
    $dirProf        = __DIR__ . '/../uploads/profile_pics/';
    if (!is_dir($dirProf)) mkdir($dirProf, 0755, true);

    if (!empty($_FILES['foto_profil']['tmp_name'])
    && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
    
    // validasi ukuran
    if ($_FILES['foto_profil']['size'] > 2 * 1024 * 1024) {
        send_response(1, 'Ukuran foto profil maksimal 2MB.');
    }

    $tmp  = $_FILES['foto_profil']['tmp_name'];
    $info = getimagesize($tmp);
    if ($info === false) {
        send_response(1, 'File yang diunggah bukan gambar.');
        switch ($info['mime']) {
            case 'image/jpeg':
                $img = imagecreatefromjpeg($tmp);
                break;
            case 'image/png':
                $img = imagecreatefrompng($tmp);
                break;
            case 'image/gif':
                $img = imagecreatefromgif($tmp);
                break;
            default:
                send_response(1, 'Format profil tidak didukung.');
        }
        $stamp   = time();
        $newName = "{$uName}_{$uJenjang}_{$uRole}_{$stamp}.jpg";
        $dest    = $dirProf . $newName;
        if (!imagejpeg($img, $dest, 90)) {
            imagedestroy($img);
            send_response(1, 'Gagal menyimpan foto profil.');
        }
        imagedestroy($img);
        // simpan URL lengkap
        $foto_profil_url = getBaseUrl() . '/uploads/profile_pics/' . $newName;
    }
    }
    // 5. Proses upload FOTO KTP
    $foto_ktp_url = '';
    $dirKtp       = __DIR__ . '/../uploads/ktp_pics/';
    if (!is_dir($dirKtp)) mkdir($dirKtp, 0755, true);

    if (!empty($_FILES['foto_ktp']['tmp_name'])
    && $_FILES['foto_ktp']['error'] === UPLOAD_ERR_OK) {
    
    // validasi ukuran
    if ($_FILES['foto_ktp']['size'] > 2 * 1024 * 1024) {
        send_response(1, 'Ukuran foto profil maksimal 2MB.');
    }

    $tmp  = $_FILES['foto_ktp']['tmp_name'];
    $info = getimagesize($tmp);
    if ($info === false) {
        send_response(1, 'File KTP bukan gambar.');
        switch ($info['mime']) {
            case 'image/jpeg':
                $img = imagecreatefromjpeg($tmp);
                break;
            case 'image/png':
                $img = imagecreatefrompng($tmp);
                break;
            case 'image/gif':
                $img = imagecreatefromgif($tmp);
                break;
            default:
                send_response(1, 'Format KTP tidak didukung.');
        }
        $stamp    = time();
        $newName2 = "{$uName}_{$uJenjang}_{$uRole}_{$stamp}_ktp.jpg";
        $dest2    = $dirKtp . $newName2;
        if (!imagejpeg($img, $dest2, 90)) {
            imagedestroy($img);
            send_response(1, 'Gagal menyimpan foto KTP.');
        }
        imagedestroy($img);
        // simpan URL lengkap
        $foto_ktp_url = getBaseUrl() . '/uploads/ktp_pics/' . $newName2;
    }
}

    // 6. Ambil sisa field dari POST
    $jk                = bersihkan_input($_POST['jk']                ?? '');
    $tgl_lahir         = bersihkan_input($_POST['tgl_lahir']         ?? '');
    $usia              = intval($_POST['usia']                      ?? 0);
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

    if ($status_perkawinan === 'Belum Menikah') {
        $nama_pasangan = '-';
        $jumlah_anak   = 0;
        $nama_anak_1 = $nama_anak_2 = $nama_anak_3 = '-';
    }

    // 7. Insert ke DB
    $sql = "INSERT INTO anggota_sekolah (
                uid, nip, password, nama, jenjang, job_title, status_kerja,
                join_start, lama_kontrak, tgl_kontrak_selesai,
                masa_kerja_tahun, masa_kerja_bulan, masa_kerja_efektif,
                remark, jenis_kelamin, tanggal_lahir, usia, agama,
                alamat_domisili, alamat_ktp, no_rekening, no_hp, pendidikan,
                status_perkawinan, email, nama_pasangan, jumlah_anak,
                nama_anak_1, nama_anak_2, nama_anak_3,
                salary_index_id, gaji_pokok, role,
                foto_profil, foto_ktp
            ) VALUES (
                ?,?,?,?,?,?,?,
                ?,?,?,?,
                ?,?,?,?,?,
                ?,?,?,?,?,
                ?,?,?,?,?,?,
                ?,?,?,?,
                ?,?,?,?,
                ?,?
            )";
    $stmt = $conn->prepare($sql);
    if (!$stmt) send_response(1, 'Query error: ' . $conn->error);

    $salary_index_id = null; // akan di‐update setelah insert
    $stmt->bind_param(
        "sssssss" .   // uid, nip, pass, nama, jenjang, job_title, status_kerja
            "siisii" .    // join_start, lama_kontrak, tgl_kontrak_selesai, masa_thn, masa_bln, masa_eff
            "sissss" .    // remark, jk, tgl_lahir, usia, religion, alamat_domisili
            "sssissss" .  // alamat_ktp, no_rekening, no_hp, pendidikan, status_perkawinan, email, nama_pasangan, jumlah_anak
            "sss" .       // nama_anak_1, nama_anak_2, nama_anak_3
            "idsss",      // salary_index_id, gaji_pokok, role, foto_profil, foto_ktp
        $uid,
        $nip,
        $defaultPass,
        $nama,
        $jenjang,
        $job_title,
        $status_kerja,
        $join_start,
        $lama_kontrak,
        $tgl_kontrak,
        $masa_tahun,
        $masa_bulan,
        $masa_eff,
        $_POST['remark']          ?? '',
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
        $role,
        $foto_profil_url,
        $foto_ktp_url
    );

    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $stmt->close();
        updateSalaryIndexForUser($conn, $newId);
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
        // kontrak: tampilkan akumulasi tahun saja
        $row['masa_kerja'] = $row['sudah_kontrak'] . " Thn";
    } else {
        // tetap: tahun + bulan
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
        $local    = __DIR__ . "/../uploads/{$subdir}/{$filename}";

        if (strpos($dbval, 'http') === 0) {
            $row[$field] = $dbval;
        } elseif ($filename && file_exists($local)) {
            $row[$field] = "{$base}/uploads/{$subdir}/{$filename}"
                . "?v=" . filemtime($local);
        } else {
            $row[$field] = "{$base}/assets/img/undraw_profile.svg";
        }
    }

    send_response(0, $row);
}



/* =============================================================
 * 4. Fungsi UpdateGuru: Memperbarui data anggota
 * =========================================================== */
function UpdateGuru(mysqli $conn)
{
    // 1. Validasi ID
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        send_response(1, 'ID tidak valid.');
    }

    // 2. Ambil data lama (nama, jenjang, foto lama)
    $stmt0 = $conn->prepare("
        SELECT nama, jenjang, foto_profil, foto_ktp
        FROM anggota_sekolah
        WHERE id = ? LIMIT 1
    ");
    $stmt0->bind_param("i", $id);
    $stmt0->execute();
    $old = $stmt0->get_result()->fetch_assoc();
    $stmt0->close();

    $namaOld      = $old['nama'];
    $jenjangOld   = $old['jenjang'];
    $urlProfilOld = $old['foto_profil'];
    $urlKtpOld    = $old['foto_ktp'];

    // 3. Sanitasi input dasar
    $nip          = bersihkan_input($_POST['nip'] ?? '');
    $nama         = bersihkan_input($_POST['nama'] ?? $namaOld);
    $jenjang      = bersihkan_input($_POST['jenjang'] ?? $jenjangOld);
    $job_title    = bersihkan_input($_POST['job_title'] ?? '');
    $role         = bersihkan_input($_POST['role'] ?? '');
    $status_kerja = bersihkan_input($_POST['status_kerja'] ?? 'Tetap');
    $join_start   = bersihkan_input($_POST['join_start'] ?? '');

    if (!$nip || !$nama || !$jenjang || !$role) {
        send_response(1, 'NIP, Nama, Jenjang dan Role wajib diisi.');
    }

    // 4. Cek duplikasi NIP
    $ck = $conn->prepare("SELECT id FROM anggota_sekolah WHERE nip = ? AND id <> ?");
    $ck->bind_param("si", $nip, $id);
    $ck->execute();
    if ($ck->get_result()->num_rows) {
        send_response(1, 'NIP sudah digunakan oleh user lain.');
    }
    $ck->close();

    // 5. Hitung tgl_kontrak & masa kerja
    $lama_kontrak = ($status_kerja === 'Kontrak')
        ? intval($_POST['lama_kontrak'] ?? 12)
        : null;
    $tgl_kontrak  = ($status_kerja === 'Kontrak' && $join_start)
        ? addMonths($join_start, $lama_kontrak)
        : null;
    list($masa_tahun, $masa_bulan, $masa_eff) = calcMasaKerja($join_start);

    // **LOGIKA TAMBAHAN**: Akumulasi tahun kontrak setiap perpanjangan
    if ($status_kerja === 'Kontrak' && $lama_kontrak) {
        $tambahTahun = floor($lama_kontrak / 12);
        if ($tambahTahun > 0) {
            $st2 = $conn->prepare("
                UPDATE anggota_sekolah
                   SET sudah_kontrak = IFNULL(sudah_kontrak,0) + ?
                 WHERE id = ?
            ");
            $st2->bind_param("ii", $tambahTahun, $id);
            $st2->execute();
            $st2->close();
        }
    }

    // siapkan naming
    $u = strtolower(preg_replace('/\s+/', '_', $nama));
    $j = strtolower(preg_replace('/\s+/', '_', $jenjang));
    $r = strtolower($_SESSION['role'] ?? 'user');

    // 6. Upload & rename FOTO PROFIL
    $fotoProfilUrl = $urlProfilOld;  // fallback URL lama
    $dirProf = __DIR__ . '/../uploads/profile_pics/';
    if (!is_dir($dirProf)) mkdir($dirProf, 0755, true);

    if (
        !empty($_FILES['foto_profil']['tmp_name'])
        && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK
    ) {
        $tmp  = $_FILES['foto_profil']['tmp_name'];
        $info = getimagesize($tmp);
        if ($info === false) {
            send_response(1, 'File yang diunggah bukan gambar.');
        }
        switch ($info['mime']) {
            case 'image/jpeg':
                $img = imagecreatefromjpeg($tmp);
                break;
            case 'image/png':
                $img = imagecreatefrompng($tmp);
                break;
            case 'image/gif':
                $img = imagecreatefromgif($tmp);
                break;
            default:
                send_response(1, 'Format gambar tidak didukung. Hanya JPG/PNG/GIF.');
        }
        $fnProf = "{$u}_{$j}_{$r}_{$id}.jpg";
        $dstProf = $dirProf . $fnProf;
        if (!imagejpeg($img, $dstProf, 90)) {
            imagedestroy($img);
            send_response(1, 'Gagal menyimpan foto profil.');
        }
        imagedestroy($img);
        // HAPUS FILE LAMA JIKA BUKAN DEFAULT/PLACEHOLDER
        if ($urlProfilOld) {
            $oldFilePath = __DIR__ . '/../uploads/profile_pics/' . basename($urlProfilOld);
            // Jangan hapus jika file adalah placeholder SVG/ikon default
            if (file_exists($oldFilePath) && strpos($urlProfilOld, 'undraw_profile.svg') === false) {
                unlink($oldFilePath);
            }
        }
        $fotoProfilUrl = getBaseUrl() . '/uploads/profile_pics/' . $fnProf;
    }

    // 7. Upload & rename FOTO KTP
    $fotoKtpUrl = $urlKtpOld;
    $dirKtp = __DIR__ . '/../uploads/ktp_pics/';
    if (!is_dir($dirKtp)) mkdir($dirKtp, 0755, true);

    if (
        !empty($_FILES['foto_ktp']['tmp_name'])
        && $_FILES['foto_ktp']['error'] === UPLOAD_ERR_OK
    ) {
        $tmp  = $_FILES['foto_ktp']['tmp_name'];
        $info = getimagesize($tmp);
        if ($info === false) {
            send_response(1, 'File KTP bukan gambar.');
        }
        switch ($info['mime']) {
            case 'image/jpeg':
                $img = imagecreatefromjpeg($tmp);
                break;
            case 'image/png':
                $img = imagecreatefrompng($tmp);
                break;
            case 'image/gif':
                $img = imagecreatefromgif($tmp);
                break;
            default:
                send_response(1, 'Format KTP tidak didukung.');
        }
        $fnKtp = "{$u}_{$j}_{$r}_{$id}_ktp.jpg";
        $dstKtp = $dirKtp . $fnKtp;
        if (!imagejpeg($img, $dstKtp, 90)) {
            imagedestroy($img);
            send_response(1, 'Gagal menyimpan foto KTP.');
        }
        imagedestroy($img);
        // HAPUS FILE LAMA JIKA BUKAN DEFAULT/PLACEHOLDER
        if ($urlKtpOld) {
            $oldKtpPath = __DIR__ . '/../uploads/ktp_pics/' . basename($urlKtpOld);
            if (file_exists($oldKtpPath) && strpos($urlKtpOld, 'ktp_placeholder.png') === false) {
                unlink($oldKtpPath);
            }
        }
        $fotoKtpUrl = getBaseUrl() . '/uploads/ktp_pics/' . $fnKtp;
    }

    // 8. Ambil & sanitasi field lain
    $remark            = bersihkan_input($_POST['remark']            ?? '');
    $jk                = bersihkan_input($_POST['jk']                ?? '');
    $tgl_lahir         = bersihkan_input($_POST['tgl_lahir']         ?? '');
    $usia              = intval($_POST['usia']                       ?? 0);
    $religion          = bersihkan_input($_POST['religion']          ?? '');
    $alamat_domisili   = bersihkan_input($_POST['alamat_domisili']   ?? '');
    $alamat_ktp        = bersihkan_input($_POST['alamat_ktp']        ?? '');
    $no_rekening       = bersihkan_input($_POST['no_rekening']       ?? '');
    $no_hp             = bersihkan_input($_POST['no_hp']             ?? '');
    $pendidikan        = bersihkan_input($_POST['pendidikan']        ?? '');
    $status_perkawinan = bersihkan_input($_POST['status_perkawinan'] ?? '');
    $email             = bersihkan_input($_POST['email']             ?? '');
    $nama_pasangan     = bersihkan_input($_POST['nama_pasangan']     ?? '');
    $jumlah_anak       = intval($_POST['jumlah_anak']               ?? 0);
    $nama_anak_1       = bersihkan_input($_POST['nama_anak_1']       ?? '');
    $nama_anak_2       = bersihkan_input($_POST['nama_anak_2']       ?? '');
    $nama_anak_3       = bersihkan_input($_POST['nama_anak_3']       ?? '');

    if ($status_perkawinan === 'Belum Menikah') {
        $nama_pasangan = '-';
        $jumlah_anak   = 0;
        $nama_anak_1 = $nama_anak_2 = $nama_anak_3 = '-';
    }

    // 9. Build dynamic UPDATE
    $cols = [
        'nip'                 => $nip,
        'nama'                => $nama,
        'jenjang'             => $jenjang,
        'job_title'           => $job_title,
        'role'                => $role,
        'status_kerja'        => $status_kerja,
        'join_start'          => $join_start,
        'lama_kontrak'        => $lama_kontrak,
        'tgl_kontrak_selesai' => $tgl_kontrak,
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
        'foto_profil'         => $fotoProfilUrl,
        'foto_ktp'            => $fotoKtpUrl,
    ];

    // salary_index_id handling
    if (isset($_POST['salary_index_id']) && $_POST['salary_index_id'] !== '') {
        $cols['salary_index_id'] = intval($_POST['salary_index_id']);
    } else {
        $cols['salary_index_id'] = null;
    }

    // build SET clause & types/values
    $setParts = [];
    $types = '';
    $vals  = [];
    foreach ($cols as $col => $val) {
        if ($col === 'salary_index_id' && $val === null) {
            $setParts[] = "salary_index_id = NULL";
            continue;
        }
        $setParts[] = "$col = ?";
        if (in_array($col, ['lama_kontrak', 'masa_kerja_tahun', 'masa_kerja_bulan', 'usia', 'jumlah_anak', 'salary_index_id'])) {
            $types .= 'i';
        } elseif ($col === 'masa_kerja_efektif') {
            $types .= 'd';
        } else {
            $types .= 's';
        }
        $vals[] = $val;
    }
    // WHERE id
    $types .= 'i';
    $vals[]  = $id;

    $sql = "UPDATE anggota_sekolah
            SET " . implode(', ', $setParts) . "
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_response(1, 'Query error: ' . $conn->error);
    }
    $stmt->bind_param($types, ...$vals);

    // 10. Eksekusi & response
    if ($stmt->execute()) {
        $stmt->close();
        updateSalaryIndexForUser($conn, $id);
        add_audit_log($conn, $_SESSION['nip'] ?? '', 'UpdateGuru', "Update ID=$id, NIP=$nip");
        send_response(0, 'Data berhasil diperbarui.');
    } else {
        $err = $stmt->error;
        $stmt->close();
        send_response(1, 'Gagal update data: ' . $err);
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
