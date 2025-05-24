<?php
// /sdm/includes/crud_jenjang.php

// Fungsi ini tidak memanggil koneksi, koneksi dilempar via parameter $conn

if (!function_exists('getAllJenjang')) {
    function getAllJenjang(mysqli $conn)
    {
        $rows = [];
        $sql = "SELECT * FROM jenjang WHERE deleted_at IS NULL ORDER BY id DESC";
        $res = $conn->query($sql);
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }
}

if (!function_exists('insertJenjang')) {
    function insertJenjang(mysqli $conn, string $kode, string $nama, string $desc = '')
    {
        $stmt = $conn->prepare("INSERT INTO jenjang (kode_jenjang, nama_jenjang, deskripsi) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $kode, $nama, $desc);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('updateJenjang')) {
    function updateJenjang(mysqli $conn, int $id, string $kode, string $nama, string $desc = '')
    {
        $stmt = $conn->prepare("UPDATE jenjang SET kode_jenjang=?, nama_jenjang=?, deskripsi=? WHERE id=?");
        $stmt->bind_param("sssi", $kode, $nama, $desc, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('softDeleteJenjang')) {
    function softDeleteJenjang(mysqli $conn, int $id)
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE jenjang SET deleted_at=? WHERE id=?");
        $stmt->bind_param("si", $now, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('activateJenjang')) {
    function activateJenjang(mysqli $conn, int $id)
    {
        $stmt = $conn->prepare("UPDATE jenjang SET deleted_at=NULL WHERE id=?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
