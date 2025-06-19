<?php
// File: /sdm/includes/crud_jenjang.php
// CRUD untuk jenjang_sekolah, sudah support color_bg dan color_fg

// =====================================================================
// 1. Fungsi dasar operasi tabel jenjang_sekolah
// =====================================================================

if (!function_exists('getAllJenjang')) {
    function getAllJenjang(mysqli $conn)
    {
        $rows = [];
        $sql = "SELECT id, kode_jenjang, nama_jenjang, deskripsi, color_bg, color_fg
                  FROM jenjang_sekolah
                 WHERE is_aktif = 1
              ORDER BY id DESC";
        $res = $conn->query($sql);
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }
}

if (!function_exists('insertJenjang')) {
    function insertJenjang(mysqli $conn, string $kode, string $nama, string $desc, string $bg, string $fg)
    {
        $stmt = $conn->prepare(
            "INSERT INTO jenjang_sekolah
             (kode_jenjang, nama_jenjang, deskripsi, is_aktif, color_bg, color_fg)
             VALUES (?, ?, ?, 1, ?, ?)"
        );
        $stmt->bind_param("sssss", $kode, $nama, $desc, $bg, $fg);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('updateJenjang')) {
    function updateJenjang(mysqli $conn, int $id, string $kode, string $nama, string $desc, string $bg, string $fg)
    {
        $stmt = $conn->prepare(
            "UPDATE jenjang_sekolah
                SET kode_jenjang = ?, nama_jenjang = ?, deskripsi = ?, color_bg = ?, color_fg = ?
              WHERE id = ? AND is_aktif = 1"
        );
        $stmt->bind_param("sssssi", $kode, $nama, $desc, $bg, $fg, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('softDeleteJenjang')) {
    function softDeleteJenjang(mysqli $conn, int $id)
    {
        // Nonaktifkan jenjang
        $stmt = $conn->prepare(
            "UPDATE jenjang_sekolah
                SET is_aktif = 0
              WHERE id = ?"
        );
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}


// =====================================================================
// 2. AJAX handler untuk jenjang.php (DataTables server-side + CRUD)
// =====================================================================

// 2.1 Loading DataTables
if (!function_exists('handlerLoadingJenjang')) {
    function handlerLoadingJenjang(mysqli $conn)
    {
        $draw   = intval($_POST['draw']   ?? 0);
        $start  = intval($_POST['start']  ?? 0);
        $length = intval($_POST['length'] ?? 10);
        $search = trim($_POST['search']['value'] ?? '');

        // Hitung total jenjang aktif
        $totalQ = $conn->query(
            "SELECT COUNT(*) AS total
               FROM jenjang_sekolah
              WHERE is_aktif = 1"
        );
        $total  = (int) $totalQ->fetch_assoc()['total'];

        // Query dasar dengan filter aktif
        $sql    = "SELECT id, kode_jenjang, nama_jenjang, deskripsi, color_bg, color_fg
                      FROM jenjang_sekolah
                     WHERE is_aktif = 1";
        $params = [];
        $types  = "";
        if ($search !== '') {
            $sql    .= " AND (kode_jenjang LIKE ? OR nama_jenjang LIKE ? OR deskripsi LIKE ? )";
            $like    = "%{$search}%";
            $params  = [$like, $like, $like];
            $types   = "sss";
        }

        // Hitung filtered count
        $stmt = $conn->prepare($sql);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $recordsFiltered = $stmt->get_result()->num_rows;
        $stmt->close();

        // Order dan pagination
        $cols    = [0=>'id',1=>'kode_jenjang',2=>'nama_jenjang',3=>'deskripsi',4=>'color_bg',5=>'color_fg'];
        $ordCol  = intval($_POST['order'][0]['column'] ?? 0);
        $ordDir  = ($_POST['order'][0]['dir'] ?? 'desc')==='asc'?'ASC':'DESC';
        $colName = $cols[$ordCol] ?? 'id';
        $sql    .= " ORDER BY {$colName} {$ordDir} LIMIT ?, ?";

        $stmt = $conn->prepare($sql);
        if ($types) {
            $types    .= 'ii';
            $params[]  = $start;
            $params[]  = $length;
            $stmt->bind_param($types, ...$params);
        } else {
            $stmt->bind_param('ii', $start, $length);
        }
        $stmt->execute();
        $res = $stmt->get_result();

        // Format data
        $data = [];
        $no   = $start + 1;
        while ($row = $res->fetch_assoc()) {
            $aksi = '<button class="btn btn-sm btn-warning btn-edit" data-id="' . $row['id'] . '"><i class="fas fa-edit"></i></button> '
                  . '<button class="btn btn-sm btn-danger btn-delete" data-id="' . $row['id'] . '"><i class="fas fa-trash-alt"></i></button>';
            $swatchBg = '<div style="width:25px; height:20px; border-radius:3px; border:1px solid #ccc; background:'.htmlspecialchars($row['color_bg']).';margin:auto;"></div>
                         <div class="small text-muted text-center">'.htmlspecialchars($row['color_bg']).'</div>';
            $swatchFg = '<div style="width:25px; height:20px; border-radius:3px; border:1px solid #ccc; background:'.htmlspecialchars($row['color_fg']).';margin:auto;"></div>
                         <div class="small text-muted text-center">'.htmlspecialchars($row['color_fg']).'</div>';
            $data[] = [
                'no'        => $no++, 
                'kode'      => htmlspecialchars($row['kode_jenjang']),
                'nama'      => htmlspecialchars($row['nama_jenjang']),
                'deskripsi' => htmlspecialchars($row['deskripsi']),
                'color_bg'  => $swatchBg,
                'color_fg'  => $swatchFg,
                'aksi'      => $aksi
            ];
        }
        $stmt->close();

        echo json_encode([
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// 2.2 Tambah Jenjang
if (!function_exists('handlerAddJenjang')) {
    function handlerAddJenjang(mysqli $conn)
    {
        $kode = trim($_POST['kode_jenjang'] ?? '');
        $nama = trim($_POST['nama_jenjang'] ?? '');
        $desc = trim($_POST['deskripsi']    ?? '');
        $bg   = trim($_POST['color_bg']     ?? '#6c757d');
        $fg   = trim($_POST['color_fg']     ?? '#ffffff');

        if ($kode === '' || $nama === '') {
            send_response(2, 'Kode dan Nama jenjang wajib diisi.');
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $bg) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $fg)) {
            send_response(4, 'Format warna tidak valid.');
        }
        if (insertJenjang($conn, $kode, $nama, $desc, $bg, $fg)) {
            send_response(0, 'Jenjang berhasil ditambahkan.');
        } else {
            send_response(1, 'Gagal menambah jenjang.');
        }
        exit();
    }
}

// 2.3 Ambil detail Jenjang untuk modal Edit
if (!function_exists('handlerGetJenjangDetail')) {
    function handlerGetJenjangDetail(mysqli $conn)
    {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) send_response(1, 'ID jenjang tidak valid.');

        $stmt = $conn->prepare(
            "SELECT id, kode_jenjang, nama_jenjang, deskripsi, color_bg, color_fg
               FROM jenjang_sekolah
              WHERE id = ? AND is_aktif = 1
              LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 1) {
            $row = $res->fetch_assoc();
            send_response(0, [
                'id'        => $row['id'],
                'kode'      => $row['kode_jenjang'],
                'nama'      => $row['nama_jenjang'],
                'deskripsi' => $row['deskripsi'],
                'color_bg'  => $row['color_bg'],
                'color_fg'  => $row['color_fg']
            ]);
        } else {
            send_response(2, 'Jenjang tidak ditemukan.');
        }
        exit();
    }
}

// 2.4 Update Jenjang
if (!function_exists('handlerUpdateJenjang')) {
    function handlerUpdateJenjang(mysqli $conn)
    {
        $id   = intval($_POST['edit_id']    ?? 0);
        $kode = trim($_POST['kode_jenjang'] ?? '');
        $nama = trim($_POST['nama_jenjang'] ?? '');
        $desc = trim($_POST['deskripsi']    ?? '');
        $bg   = trim($_POST['color_bg']     ?? '#6c757d');
        $fg   = trim($_POST['color_fg']     ?? '#ffffff');

        if ($id <= 0 || $kode === '' || $nama === '') {
            send_response(3, 'Data tidak lengkap atau ID tidak valid.');
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $bg) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $fg)) {
            send_response(4, 'Format warna tidak valid.');
        }
        if (updateJenjang($conn, $id, $kode, $nama, $desc, $bg, $fg)) {
            send_response(0, 'Jenjang berhasil diupdate.');
        } else {
            send_response(1, 'Gagal mengupdate jenjang.');
        }
        exit();
    }
}

// 2.5 Soft-delete Jenjang (set is_aktif=0)
if (!function_exists('handlerDeleteJenjang')) {
    function handlerDeleteJenjang(mysqli $conn)
    {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) send_response(3, 'ID jenjang tidak valid.');

        if (softDeleteJenjang($conn, $id)) {
            send_response(0, 'Jenjang berhasil dihapus.');
        } else {
            send_response(1, 'Gagal menghapus jenjang.');
        }
        exit();
    }
}
