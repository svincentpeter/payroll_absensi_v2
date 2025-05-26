<?php
// File: /payroll_absensi_v2/sdm/includes/employees_core.php

// Semua fungsi utama: Load, Edit, View Detail Employee

require_once __DIR__ . '/../../helpers.php';



if (!function_exists('getFullPhotoUrl')) {
    function getFullPhotoUrl($dbval, $subdir = 'profile_pics')
    {
        $base = getBaseUrl();
        $filename = basename($dbval);
        $local = __DIR__ . "/../../uploads/{$subdir}/{$filename}";
        if (strpos($dbval, 'http') === 0) {
            return $dbval;
        } elseif ($filename && file_exists($local)) {
            return "{$base}/uploads/{$subdir}/{$filename}?v=" . filemtime($local);
        } else {
            return "{$base}/assets/img/undraw_profile.svg";
        }
    }
}


if (!function_exists('LoadingEmployees')) {
    function LoadingEmployees($conn)
    {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $start         = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length        = isset($_POST['length']) ? intval($_POST['length']) : 10;
        $search        = sanitize_input($_POST['search'] ?? '');
        $jenjang       = sanitize_input($_POST['jenjang'] ?? '');
        $role          = sanitize_input($_POST['role'] ?? '');
        $selectedMonth = intval($_POST['selectedMonth'] ?? date('n'));
        $selectedYear  = intval($_POST['selectedYear']  ?? date('Y'));

        // Subquery payroll status
        $subqueryPayrollStatus = "(
        SELECT p.status
          FROM payroll p
         WHERE p.id_anggota = a.id
           AND p.bulan = $selectedMonth
           AND p.tahun = $selectedYear
      ORDER BY FIELD(p.status, 'final','revisi','draft')
         LIMIT 1
     ) AS payroll_status";

        // Subquery rapel
        $subqueryRapel = "(SELECT COUNT(*)
                         FROM employee_payheads ep
                        WHERE ep.id_anggota = a.id
                          AND ep.is_rapel = 1
                      ) AS has_rapel";

        // Hitung total data
        $sqlTotal   = "SELECT COUNT(*) AS total FROM anggota_sekolah";
        $rowTotal   = $conn->query($sqlTotal)->fetch_assoc();
        $recordsTotal = intval($rowTotal['total']);

        // Query utama (termasuk foto_profil)
        $sql = "SELECT 
                a.id, a.uid, a.nip, a.nama, a.jenjang, a.role,
                a.job_title, a.status_kerja, a.masa_kerja_tahun,
                a.masa_kerja_bulan, a.gaji_pokok, a.no_rekening,
                a.email, a.foto_profil, 
                si.level   AS salary_index_level,
                si.base_salary AS salary_index_base,
                $subqueryPayrollStatus,
                $subqueryRapel
            FROM anggota_sekolah a
       LEFT JOIN salary_indices si ON a.salary_index_id = si.id
           WHERE 1=1";

        // Filter jenjang
        if ($jenjang !== '') {
            $sql .= " AND a.jenjang = '" . $conn->real_escape_string($jenjang) . "'";
        }
        // Filter role
        if ($role !== '') {
            $sql .= " AND a.role = '" . $conn->real_escape_string($role) . "'";
        }
        // Filter search
        if ($search !== '') {
            $s = $conn->real_escape_string($search);
            $sql .= " AND (
            a.id LIKE '%$s%' OR a.uid LIKE '%$s%' OR a.nip LIKE '%$s%' OR a.nama LIKE '%$s%'
            OR a.jenjang LIKE '%$s%' OR a.role LIKE '%$s%' OR a.job_title LIKE '%$s%'
            OR a.status_kerja LIKE '%$s%' OR a.no_rekening LIKE '%$s%' OR a.email LIKE '%$s%'
        )";
        }

        $sql .= " ORDER BY a.id DESC LIMIT $start, $length";

        $res = $conn->query($sql);
        if (!$res) {
            send_response(1, 'Gagal query data employees: ' . $conn->error);
        }

        $baseUrl = getBaseUrl();
        $data = [];
        while ($row = $res->fetch_assoc()) {
            // format masa kerja & gaji
            $masaKerja = trim(
                ($row['masa_kerja_tahun'] > 0 ? $row['masa_kerja_tahun'] . ' Thn ' : '')
                    . ($row['masa_kerja_bulan'] > 0 ? $row['masa_kerja_bulan'] . ' Bln' : '')
            ) ?: '-';
            $gajiPokokFmt = number_format($row['gaji_pokok'], 0, ',', '.');

            // status payroll
            $statusPayroll = 'Belum Diproses';
            if (!empty($row['payroll_status'])) {
                if ($row['payroll_status'] === 'final')   $statusPayroll = 'Final';
                if ($row['payroll_status'] === 'revisi')  $statusPayroll = 'Revisi';
                if ($row['payroll_status'] === 'draft')   $statusPayroll = 'Draft';
            }

            // hitung URL foto profil
            $fotoDb   = $row['foto_profil'] ?? '';
            $filename = basename($fotoDb);
            $local    = __DIR__ . '/../uploads/profile_pics/' . $filename;
            if ($fotoDb && strpos($fotoDb, 'http') === 0) {
                $fotoUrl = $fotoDb;
            } elseif ($filename && file_exists($local)) {
                $fotoUrl = "{$baseUrl}/uploads/profile_pics/{$filename}?v=" . filemtime($local);
            } else {
                $fotoUrl = "{$baseUrl}/assets/img/undraw_profile.svg";
            }

            $data[] = [
                'id'                  => $row['id'],
                'uid'                 => $row['uid'],
                'nip'                 => $row['nip'],
                'nama'                => $row['nama'],
                'jenjang'             => $row['jenjang'],
                'role'                => $row['role'],
                'job_title'           => $row['job_title'],
                'status_kerja'        => $row['status_kerja'],
                'masa_kerja'          => $masaKerja,
                'gaji_pokok'          => $gajiPokokFmt,
                'no_rekening'         => $row['no_rekening'],
                'email'               => $row['email'],
                'salary_index_level'  => $row['salary_index_level'] ?: '-',
                'salary_index_base'   => floatval($row['salary_index_base'] ?: 0),
                'payroll_status'      => $statusPayroll,
                'has_rapel'           => intval($row['has_rapel']) > 0,
                'foto_profil'         => $fotoUrl,
                'badge_role'      => getBadgeRole($row['role']),
                'badge_jenjang'   => getBadgeJenjang($row['jenjang'], $conn),
                'badge_status'    => getBadgeStatusKerja($row['status_kerja']),
            ];
        }

        // audit
        $user_nip = $_SESSION['nip'] ?? '';
        add_audit_log(
            $conn,
            $user_nip,
            'LoadingEmployees',
            "start=$start, length=$length, filter jenjang=$jenjang, role=$role, search=$search"
        );

        echo json_encode([
            'recordsTotal' => $recordsTotal,
            'data'         => $data
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

if (!function_exists('EditEmployee')) {
    function EditEmployee($conn)
    {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $id          = intval($_POST['id'] ?? 0);
        $no_rekening = trim(sanitize_input($_POST['no_rekening'] ?? ''));
        if ($id <= 0 || empty($no_rekening)) {
            send_response(1, 'ID & No Rekening wajib diisi.');
        }
        $stmtU = $conn->prepare("UPDATE anggota_sekolah SET no_rekening = ? WHERE id = ?");
        if (!$stmtU) {
            send_response(1, 'Prepare failed EditEmployee: ' . $conn->error);
        }
        $stmtU->bind_param("si", $no_rekening, $id);
        if ($stmtU->execute()) {
            $stmtU->close();

            // [Audit log baru]
            $user_nip = $_SESSION['nip'] ?? '';
            $detail   = "EditEmployee: ID=$id, Update no_rekening=$no_rekening";
            add_audit_log($conn, $user_nip, 'EditEmployee', $detail);

            send_response(0, 'No Rekening anggota berhasil diperbarui.');
        } else {
            send_response(1, 'Gagal memperbarui No Rekening: ' . $stmtU->error);
        }
    }
}


if (!function_exists('ViewEmployeeDetail')) {
    function ViewEmployeeDetail($conn)
    {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            send_response(1, 'ID anggota tidak valid.');
        }
        $selectedMonth = isset($_POST['selectedMonth']) ? intval($_POST['selectedMonth']) : date('n');
        $selectedYear  = isset($_POST['selectedYear']) ? intval($_POST['selectedYear']) : date('Y');

        $includeRapel = isset($_POST['includeRapel']) ? intval($_POST['includeRapel']) : 0;

        // 1. Ambil data anggota seperti sebelumnya
        $stmt = $conn->prepare("
            SELECT a.*, si.level AS salary_index_level, si.base_salary AS salary_index_base,
                (SELECT p.status FROM payroll p WHERE p.id_anggota = a.id AND p.bulan = ? AND p.tahun = ? ORDER BY p.tgl_payroll DESC LIMIT 1) AS payroll_status
            FROM anggota_sekolah a
            LEFT JOIN salary_indices si ON a.salary_index_id = si.id
            WHERE a.id = ?
            LIMIT 1
        ");
        $stmt->bind_param("iii", $selectedMonth, $selectedYear, $id);
        if (!$stmt->execute()) {
            send_response(1, 'Execute failed (ViewEmployeeDetail): ' . $stmt->error);
        }
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $emp = $res->fetch_assoc();
            $stmt->close();

            $masaKerja = ($emp['masa_kerja_tahun'] > 0 ? $emp['masa_kerja_tahun'] . ' Tahun ' : '')
                . ($emp['masa_kerja_bulan'] > 0 ? $emp['masa_kerja_bulan'] . ' Bulan' : '');
            $masaKerja = trim($masaKerja) ?: '-';

            $gajiPokokVal  = floatval($emp['gaji_pokok']);
            $levelIndexVal = floatval($emp['salary_index_base']);

            // --- Tambahan: Cek payroll draft untuk periode ini ---
            $payrollDraft = null;
            $payrollDetailDraft = [];
            $stmtDraft = $conn->prepare("SELECT * FROM payroll WHERE id_anggota = ? AND bulan = ? AND tahun = ? AND status = 'draft' LIMIT 1");
            $stmtDraft->bind_param("iii", $id, $selectedMonth, $selectedYear);
            if ($stmtDraft->execute()) {
                $resDraft = $stmtDraft->get_result();
                if ($resDraft->num_rows > 0) {
                    $payrollDraft = $resDraft->fetch_assoc();
                    // Ambil payroll_detail draft
                    $payrollIdDraft = $payrollDraft['id'];
                    $stmtPD = $conn->prepare("
                        SELECT d.*, p.nama_payhead, p.jenis AS jenis_payhead
                        FROM payroll_detail d
                        JOIN payheads p ON d.id_payhead = p.id
                        WHERE d.id_payroll = ?
                    ");
                    $stmtPD->bind_param("i", $payrollIdDraft);
                    if ($stmtPD->execute()) {
                        $resPD = $stmtPD->get_result();
                        while ($pd = $resPD->fetch_assoc()) {
                            $payrollDetailDraft[] = $pd;
                        }
                        $stmtPD->close();
                    }
                }
                $stmtDraft->close();
            }
            // ----------------------------

            // Ambil data payheads (employee_payheads) seperti sebelumnya
            $stmtPH = $conn->prepare("
                SELECT ep.id_payhead, ph.nama_payhead, ph.jenis AS jenis_payhead,
                    ep.amount, ep.support_doc_path, ep.is_rapel, ep.remarks
                FROM employee_payheads ep
                JOIN payheads ph ON ep.id_payhead = ph.id
                WHERE ep.id_anggota = ?
            ");
            if (!$stmtPH) {
                send_response(1, 'Prepare failed (payheads): ' . $conn->error);
            }
            $stmtPH->bind_param("i", $id);
            if (!$stmtPH->execute()) {
                send_response(1, 'Execute failed (payheads): ' . $stmtPH->error);
            }
            $resPH = $stmtPH->get_result();
            $assigned = [];
            $totalPendapatan = 0;
            $totalPotongan   = 0;
            while ($rw = $resPH->fetch_assoc()) {
                if (!$includeRapel && intval($rw['is_rapel']) === 1) {
                    continue;
                }
                $assigned[] = [
                    'id_payhead'        => $rw['id_payhead'],
                    'nama_payhead'      => $rw['nama_payhead'],
                    'jenis_payhead'     => $rw['jenis_payhead'],
                    'jenis_payhead_idn' => translateJenis($rw['jenis_payhead']),
                    'amount'            => $rw['amount'],
                    'support_doc_path'  => $rw['support_doc_path'],
                    'is_rapel'          => $rw['is_rapel'],
                    'remarks'           => $rw['remarks'],
                ];
                if ($rw['jenis_payhead'] === 'earnings') {
                    $totalPendapatan += floatval($rw['amount']);
                } else {
                    $totalPotongan += floatval($rw['amount']);
                }
            }
            $stmtPH->close();
            $gajiBersihVal = $gajiPokokVal + $levelIndexVal + $totalPendapatan - $totalPotongan;

            // Audit log
            $user_nip   = $_SESSION['nip'] ?? '';
            $detailsLog = "Melihat detail anggota ID $id (oleh $user_nip).";
            add_audit_log($conn, $user_nip, 'ViewEmployeeDetail', $detailsLog);

            $periodePayroll = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
$stmtKG = $conn->prepare("SELECT * FROM kenaikan_gaji_tahunan
    WHERE id_anggota = ? AND status = 'aktif'
    AND tanggal_mulai <= ? AND tanggal_berakhir >= ?
    ORDER BY tanggal_mulai DESC LIMIT 1");
$stmtKG->bind_param("iss", $id, $periodePayroll, $periodePayroll);
$stmtKG->execute();
$resKG = $stmtKG->get_result();
$kenaikanGajiAktif = $resKG->num_rows > 0 ? $resKG->fetch_assoc() : null;
$stmtKG->close();

            // ---- Kirim payroll_draft ke frontend ----
            send_response(0, [
    'id'                    => $emp['id'],
    'uid'                   => $emp['uid'],
    'nip'                   => $emp['nip'],
    'nama'                  => $emp['nama'],
    'jenjang'               => $emp['jenjang'],
    'job_title'             => $emp['job_title'],
    'role'                  => $emp['role'],
    'status_kerja'          => $emp['status_kerja'],
    'masa_kerja'            => $masaKerja,
    'gaji_pokok_val'        => $gajiPokokVal,
    'gaji_pokok'            => 'Rp ' . number_format($gajiPokokVal, 0, ',', '.'),
    'salary_index_amount'   => $levelIndexVal,
    'salary_index_level'    => $emp['salary_index_level'] ?? '-',
'salary_index_base'     => isset($emp['salary_index_base']) ? floatval($emp['salary_index_base']) : 0,
    'no_rekening'           => $emp['no_rekening'],
    'email'                 => $emp['email'],
    'jenis_kelamin'         => $emp['jenis_kelamin'],
    'agama'                 => $emp['agama'],
    'masa_kerja_tahun'      => $emp['masa_kerja_tahun'],
    'masa_kerja_bulan'      => $emp['masa_kerja_bulan'],
    'payheads'              => $assigned,
    'total_pendapatan'      => $totalPendapatan,
    'total_potongan'        => $totalPotongan,
    'gaji_bersih'           => $gajiBersihVal,
    'kenaikan_gaji_tahunan' => $kenaikanGajiAktif,
    'payroll_status'        => $emp['payroll_status'],
    // TAMBAHAN UNTUK DETAIL MODAL:
    'alamat_domisili'       => $emp['alamat_domisili'],
    'alamat_ktp'            => $emp['alamat_ktp'],
    'no_hp'                 => $emp['no_hp'],
    'remark'                => $emp['remark'],
    'pendidikan'            => $emp['pendidikan'],
    'strata'                => $emp['strata'],
    'join_start'            => $emp['join_start'],
    'lama_kontrak'          => $emp['lama_kontrak'],
    'tgl_kontrak_selesai'   => $emp['tgl_kontrak_selesai'],
    'usia'                  => $emp['usia'],
    'tanggal_lahir'         => $emp['tanggal_lahir'],
    'status_perkawinan'     => $emp['status_perkawinan'],
    'nama_pasangan'         => $emp['nama_pasangan'],
    'jumlah_anak'           => $emp['jumlah_anak'],
    'nama_anak_1'           => $emp['nama_anak_1'],
    'nama_anak_2'           => $emp['nama_anak_2'],
    'nama_anak_3'           => $emp['nama_anak_3'],
    // FOTO harus pakai url
    'foto_profil'           => getFullPhotoUrl($emp['foto_profil'], 'profile_pics'),
    'foto_ktp'              => getFullPhotoUrl($emp['foto_ktp'], 'ktp_pics'),

                // tambahkan di sini!
                'payroll_draft'         => $payrollDraft ?: null,
                'payroll_detail_draft'  => $payrollDetailDraft,
            ]);
        } else {
            $stmt->close();
            send_response(1, 'Anggota tidak ditemukan.');
        }
    }
}

