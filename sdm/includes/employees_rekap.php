<?php
// File: /payroll_absensi_v2/sdm/includes/employees_rekap.php

// Semua fungsi rekap absensi karyawan
if (!function_exists('ViewRekapAbsensi')) {
    function ViewRekapAbsensi($conn)
    {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $id_anggota = intval($_POST['id'] ?? 0);
        $bulan      = intval($_POST['selectedMonth'] ?? 0);
        $tahun      = intval($_POST['selectedYear']  ?? 0);

        $stmt = $conn->prepare("SELECT * FROM rekap_absensi
                            WHERE id_anggota = ? AND bulan = ? AND tahun = ?
                            LIMIT 1");
        $stmt->bind_param("iii", $id_anggota, $bulan, $tahun);
        if (!$stmt->execute()) {
            send_response(1, 'Gagal mengambil rekap absensi.');
        }
        $res = $stmt->get_result();
        // [Audit log baru]
        $user_nip = $_SESSION['nip'] ?? '';
        add_audit_log($conn, $user_nip, 'ViewRekapAbsensi', "id_anggota=$id_anggota, bulan=$bulan, tahun=$tahun");

        if ($res->num_rows > 0) {
            $rekap = $res->fetch_assoc();
            send_response(0, $rekap);
        } else {
            send_response(0, [
                'id'                     => 0,
                'id_anggota'            => $id_anggota,
                'bulan'                 => $bulan,
                'tahun'                 => $tahun,
                'total_hadir'           => 0,
                'total_izin'            => 0,
                'total_cuti'            => 0,
                'total_tanpa_keterangan' => 0,
                'total_sakit'           => 0
            ]);
        }
    }
}


if (!function_exists('EditRekapAbsensi')) {
    function EditRekapAbsensi($conn)
    {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $id_anggota              = intval($_POST['id_anggota'] ?? 0);
        $bulan                   = intval($_POST['bulan'] ?? 0);
        $tahun                   = intval($_POST['tahun'] ?? 0);
        $total_hadir             = intval($_POST['total_hadir'] ?? 0);
        $total_izin              = intval($_POST['total_izin']  ?? 0);
        $total_cuti              = intval($_POST['total_cuti']  ?? 0);
        $total_tanpa_keterangan  = intval($_POST['total_tanpa_keterangan'] ?? 0);
        $total_sakit             = intval($_POST['total_sakit'] ?? 0);

        $stmtCek = $conn->prepare("SELECT id FROM rekap_absensi
                               WHERE id_anggota = ? AND bulan = ? AND tahun = ?
                               LIMIT 1");
        $stmtCek->bind_param("iii", $id_anggota, $bulan, $tahun);
        if (!$stmtCek->execute()) {
            send_response(1, 'Gagal mengecek rekap absensi.');
        }
        $resCek = $stmtCek->get_result();
        // [Audit log baru]
        $user_nip = $_SESSION['nip'] ?? '';
        $detail   = "EditRekapAbsensi for ID=$id_anggota, bulan=$bulan, tahun=$tahun";
        add_audit_log($conn, $user_nip, 'EditRekapAbsensi', $detail);

        if ($resCek->num_rows > 0) {
            $row = $resCek->fetch_assoc();
            $idRekap = $row['id'];
            $stmtU = $conn->prepare("UPDATE rekap_absensi
                                 SET total_hadir = ?, total_izin = ?,
                                     total_cuti = ?, total_tanpa_keterangan = ?,
                                     total_sakit = ?
                                 WHERE id = ?");
            $stmtU->bind_param(
                "iiiiii",
                $total_hadir,
                $total_izin,
                $total_cuti,
                $total_tanpa_keterangan,
                $total_sakit,
                $idRekap
            );
            if ($stmtU->execute()) {
                send_response(0, 'Rekap absensi berhasil diperbarui.');
            } else {
                send_response(1, 'Gagal memperbarui rekap absensi.');
            }
            $stmtU->close();
        } else {
            $stmtI = $conn->prepare("INSERT INTO rekap_absensi
            (id_anggota, bulan, tahun, total_hadir, total_izin,
             total_cuti, total_tanpa_keterangan, total_sakit)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtI->bind_param(
                "iiiiiiii",
                $id_anggota,
                $bulan,
                $tahun,
                $total_hadir,
                $total_izin,
                $total_cuti,
                $total_tanpa_keterangan,
                $total_sakit
            );
            if ($stmtI->execute()) {
                send_response(0, 'Rekap absensi berhasil disimpan.');
            } else {
                send_response(1, 'Gagal menyimpan rekap absensi.');
            }
            $stmtI->close();
        }
    }
}
