<?php
// /sdm/includes/jenjang_handler.php

require_once __DIR__ . '/../../koneksi.php';         // <--- Koneksi dari root
require_once __DIR__ . '/crud_jenjang.php';

header('Content-Type: application/json');

$case = $_POST['case'] ?? '';

switch ($case) {
    case 'getAll':
        $rows = getAllJenjang($conn);
        echo json_encode(['code'=>0, 'result'=>'OK', 'data'=>$rows]);
        break;

    case 'create':
        $kode = trim($_POST['kode_jenjang'] ?? '');
        $nama = trim($_POST['nama_jenjang'] ?? '');
        $desc = trim($_POST['deskripsi'] ?? '');
        if ($kode === '' || $nama === '') {
            echo json_encode(['code'=>1, 'result'=>'Kode & Nama Jenjang wajib diisi.']);
            break;
        }
        $ok = insertJenjang($conn, $kode, $nama, $desc);
        echo json_encode([
            'code' => $ok ? 0 : 1,
            'result' => $ok ? 'Berhasil menambah Jenjang.' : 'Gagal menambah Jenjang.'
        ]);
        break;

    case 'update':
        $id   = intval($_POST['id'] ?? 0);
        $kode = trim($_POST['kode_jenjang'] ?? '');
        $nama = trim($_POST['nama_jenjang'] ?? '');
        $desc = trim($_POST['deskripsi'] ?? '');
        if ($id <= 0 || $kode === '' || $nama === '') {
            echo json_encode(['code'=>1, 'result'=>'Data tidak valid.']);
            break;
        }
        $ok = updateJenjang($conn, $id, $kode, $nama, $desc);
        echo json_encode([
            'code' => $ok ? 0 : 1,
            'result' => $ok ? 'Data Jenjang berhasil diupdate.' : 'Gagal update Jenjang.'
        ]);
        break;

    case 'soft_delete':
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['code'=>1, 'result'=>'ID tidak valid.']);
            break;
        }
        $ok = softDeleteJenjang($conn, $id);
        echo json_encode([
            'code' => $ok ? 0 : 1,
            'result' => $ok ? 'Jenjang dinonaktifkan.' : 'Gagal nonaktifkan Jenjang.'
        ]);
        break;

    case 'activate':
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['code'=>1, 'result'=>'ID tidak valid.']);
            break;
        }
        $ok = activateJenjang($conn, $id);
        echo json_encode([
            'code' => $ok ? 0 : 1,
            'result' => $ok ? 'Jenjang diaktifkan kembali.' : 'Gagal aktifkan Jenjang.'
        ]);
        break;

    default:
        echo json_encode(['code'=>1, 'result'=>'Request tidak dikenali.']);
}

$conn->close();
