<?php
// File: /payroll_absensi_v2/absensi/sdm/sdm_input.php

// ==============================================================================
// 1. Pengaturan Awal: Inisialisasi Session, Helper, dan Otorisasi
// ==============================================================================
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
// Batasi akses hanya untuk role "sdm" (Anda dapat menambahkan role lain jika diperlukan)
authorize(['sdm', 'superadmin'], '/payroll_absensi_v2/login.php');

// Sertakan koneksi ke database
require_once __DIR__ . '/../../koneksi.php';

// (Opsional) Hapus output buffering jika ada
if (ob_get_length()) {
    ob_end_clean();
}

// Untuk konsistensi, jika menggunakan CSRF token di halaman lain, bisa dihasilkan
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

// ==============================================================================
// 2. PROSES PENYIMPANAN DATA JADWAL PIKET (POST)
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_schedule'])) {
    // Ambil tipe jadwal: 1 = Jadwal 1 (Juni–Juli); 2 = Jadwal 2 (Desember–Januari)
    $schedule_type = $_POST['schedule_type'] ?? '';
    if (!in_array($schedule_type, ['1', '2'])) {
        $_SESSION['error_message'] = "Pilih tipe jadwal yang valid.";
        header("Location: sdm_input.php");
        exit();
    }
    
    // Ambil jumlah guru yang dipilih, sebagai angka (1 sampai 5)
    $jumlah_guru = intval($_POST['jumlah_guru'] ?? 0);
    if ($jumlah_guru < 1 || $jumlah_guru > 5) {
        $_SESSION['error_message'] = "Pilih jumlah guru yang valid (1 sampai 5).";
        header("Location: sdm_input.php");
        exit();
    }
    
    // Ambil data guru yang dipilih (array nip)
    $selected_guru = $_POST['guru'] ?? [];
    if (count($selected_guru) != $jumlah_guru) {
        $_SESSION['error_message'] = "Pastikan untuk memilih $jumlah_guru guru.";
        header("Location: sdm_input.php");
        exit();
    }
    
    // Ambil tanggal dari masing-masing field sesuai tipe jadwal
    if ($schedule_type === '1') {
        $dates_juni = trim($_POST['tanggal_juni'] ?? '');
        $dates_juli = trim($_POST['tanggal_juli'] ?? '');
        $arr_juni  = !empty($dates_juni) ? array_map('trim', explode(",", $dates_juni)) : [];
        $arr_juli  = !empty($dates_juli) ? array_map('trim', explode(",", $dates_juli)) : [];
        if (empty($arr_juni) && empty($arr_juli)) {
            $_SESSION['error_message'] = "Pilih minimal satu tanggal untuk bulan Juni atau Juli.";
            header("Location: sdm_input.php");
            exit();
        }
    } else { // schedule_type == '2'
        $dates_desember = trim($_POST['tanggal_desember'] ?? '');
        $dates_januari  = trim($_POST['tanggal_januari'] ?? '');
        $arr_desember   = !empty($dates_desember) ? array_map('trim', explode(",", $dates_desember)) : [];
        $arr_januari    = !empty($dates_januari) ? array_map('trim', explode(",", $dates_januari)) : [];
        if (empty($arr_desember) && empty($arr_januari)) {
            $_SESSION['error_message'] = "Pilih minimal satu tanggal untuk bulan Desember atau Januari.";
            header("Location: sdm_input.php");
            exit();
        }
    }
    
    // Ambil data guru (nip dan nama) untuk guru yang dipilih
    $dataGuru = [];
    $sqlGuru = "SELECT nip, nama FROM anggota_sekolah WHERE nip = ?";
    $stmtGuru = $conn->prepare($sqlGuru);
    foreach ($selected_guru as $nip) {
        $nip = trim($nip);
        $stmtGuru->bind_param("s", $nip);
        $stmtGuru->execute();
        $resultGuru = $stmtGuru->get_result();
        if ($resultGuru->num_rows === 1) {
            $row = $resultGuru->fetch_assoc();
            $dataGuru[$nip] = $row['nama'];
        } else {
            $_SESSION['error_message'] = "Data guru dengan NIP $nip tidak ditemukan.";
            header("Location: sdm_input.php");
            exit();
        }
    }
    $stmtGuru->close();
    
    // Waktu piket tetap (contoh, sesuaikan jika diperlukan)
    $waktu_piket = "08:00 - 13:00";
    
    // Simpan data ke tabel jadwal_piket dengan transaksi
    try {
        $conn->begin_transaction();
        
        if ($schedule_type === '1') {
            // Proses untuk Jadwal 1 (Juni dan Juli)
            foreach ($arr_juni as $tanggal) {
                if (empty($tanggal)) continue;
                // Validasi: pastikan tanggal termasuk bulan Juni atau Juli
                $bulan = date("n", strtotime($tanggal));
                if ($bulan != 6 && $bulan != 7) {
                    throw new Exception("Untuk Jadwal 1, tanggal harus di bulan Juni atau Juli.");
                }
                foreach ($dataGuru as $nip => $nama) {
                    // Cek duplikasi jadwal
                    $sql_check = "SELECT COUNT(*) AS jumlah FROM jadwal_piket WHERE nip = ? AND tanggal = ? AND waktu_piket = ?";
                    $stmt = $conn->prepare($sql_check);
                    $stmt->bind_param("sss", $nip, $tanggal, $waktu_piket);
                    $stmt->execute();
                    $res = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($res['jumlah'] > 0) continue;
                    
                    // Dapatkan nama bulan dalam format teks (misalnya, "Juni" atau "Juli")
                    $month_name = date("F", strtotime($tanggal));
                    $year_value = date("Y", strtotime($tanggal));
                    
                    $sql_insert = "INSERT INTO jadwal_piket (nip, nama_guru, waktu_piket, tanggal, bulan, tahun, status)
                                   VALUES (?, ?, ?, ?, ?, ?, 'pending')";
                    $stmt = $conn->prepare($sql_insert);
                    $stmt->bind_param("ssssii", $nip, $nama, $waktu_piket, $tanggal, $month_name, $year_value);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            foreach ($arr_juli as $tanggal) {
                if (empty($tanggal)) continue;
                $bulan = date("n", strtotime($tanggal));
                if ($bulan != 6 && $bulan != 7) {
                    throw new Exception("Untuk Jadwal 1, tanggal harus di bulan Juni atau Juli.");
                }
                foreach ($dataGuru as $nip => $nama) {
                    $sql_check = "SELECT COUNT(*) AS jumlah FROM jadwal_piket WHERE nip = ? AND tanggal = ? AND waktu_piket = ?";
                    $stmt = $conn->prepare($sql_check);
                    $stmt->bind_param("sss", $nip, $tanggal, $waktu_piket);
                    $stmt->execute();
                    $res = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($res['jumlah'] > 0) continue;
                    
                    $month_name = date("F", strtotime($tanggal));
                    $year_value = date("Y", strtotime($tanggal));
                    
                    $sql_insert = "INSERT INTO jadwal_piket (nip, nama_guru, waktu_piket, tanggal, bulan, tahun, status)
                                   VALUES (?, ?, ?, ?, ?, ?, 'pending')";
                    $stmt = $conn->prepare($sql_insert);
                    $stmt->bind_param("ssssii", $nip, $nama, $waktu_piket, $tanggal, $month_name, $year_value);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } else { // schedule_type == '2'
            // Proses untuk Jadwal 2 (Desember dan Januari)
            foreach ($arr_desember as $tanggal) {
                if (empty($tanggal)) continue;
                $bulan = date("n", strtotime($tanggal));
                if ($bulan != 12 && $bulan != 1) {
                    throw new Exception("Untuk Jadwal 2, tanggal harus di bulan Desember atau Januari.");
                }
                foreach ($dataGuru as $nip => $nama) {
                    $sql_check = "SELECT COUNT(*) AS jumlah FROM jadwal_piket WHERE nip = ? AND tanggal = ? AND waktu_piket = ?";
                    $stmt = $conn->prepare($sql_check);
                    $stmt->bind_param("sss", $nip, $tanggal, $waktu_piket);
                    $stmt->execute();
                    $res = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($res['jumlah'] > 0) continue;
                    
                    $month_name = date("F", strtotime($tanggal));
                    $year_value = date("Y", strtotime($tanggal));
                    
                    $sql_insert = "INSERT INTO jadwal_piket (nip, nama_guru, waktu_piket, tanggal, bulan, tahun, status)
                                   VALUES (?, ?, ?, ?, ?, ?, 'pending')";
                    $stmt = $conn->prepare($sql_insert);
                    $stmt->bind_param("ssssii", $nip, $nama, $waktu_piket, $tanggal, $month_name, $year_value);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            foreach ($arr_januari as $tanggal) {
                if (empty($tanggal)) continue;
                $bulan = date("n", strtotime($tanggal));
                if ($bulan != 12 && $bulan != 1) {
                    throw new Exception("Untuk Jadwal 2, tanggal harus di bulan Desember atau Januari.");
                }
                foreach ($dataGuru as $nip => $nama) {
                    $sql_check = "SELECT COUNT(*) AS jumlah FROM jadwal_piket WHERE nip = ? AND tanggal = ? AND waktu_piket = ?";
                    $stmt = $conn->prepare($sql_check);
                    $stmt->bind_param("sss", $nip, $tanggal, $waktu_piket);
                    $stmt->execute();
                    $res = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($res['jumlah'] > 0) continue;
                    
                    $month_name = date("F", strtotime($tanggal));
                    $year_value = date("Y", strtotime($tanggal));
                    
                    $sql_insert = "INSERT INTO jadwal_piket (nip, nama_guru, waktu_piket, tanggal, bulan, tahun, status)
                                   VALUES (?, ?, ?, ?, ?, ?, 'pending')";
                    $stmt = $conn->prepare($sql_insert);
                    $stmt->bind_param("ssssii", $nip, $nama, $waktu_piket, $tanggal, $month_name, $year_value);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        
        $conn->commit();
        $_SESSION['success_message'] = "Jadwal piket berhasil disimpan.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    header("Location: sdm_input.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Jadwal Piket Guru (SDM) - Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5 CSS & SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <!-- Bootstrap Datepicker CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    <style>
        .month-field { display: none; }
    </style>
</head>
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Navbar & Breadcrumb (disesuaikan dengan template) -->
                <?php include __DIR__ . '/../../navbar.php'; ?>
                <?php include __DIR__ . '/../../breadcrumb.php'; ?>

                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Input Jadwal Piket Guru (SDM)</h1>
                    
                    <!-- Notifikasi -->
                    <?php if(isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['success_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>
                    <?php if(isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['error_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>
                    
                    <form method="POST" action="sdm_input.php">
                        <!-- Pilih Tipe Jadwal -->
                        <div class="form-group mb-3">
                            <label for="schedule_type">Pilih Jadwal Piket:</label>
                            <select name="schedule_type" id="schedule_type" class="form-control" required>
                                <option value="">-- Pilih Jadwal Piket --</option>
                                <option value="1">Jadwal 1: Juni - Juli</option>
                                <option value="2">Jadwal 2: Desember - Januari</option>
                            </select>
                        </div>
                        
                        <!-- Pilih Jumlah Guru -->
                        <div class="form-group mb-3">
                            <label for="jumlah_guru">Jumlah Guru/Karyawan yang Piket:</label>
                            <select name="jumlah_guru" id="jumlah_guru" class="form-control" required>
                                <option value="">-- Pilih Jumlah --</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?= $i; ?>"><?= $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <!-- Container Dropdown Guru (dinamis) -->
                        <div id="guru_container" class="mb-3"></div>
                        
                        <!-- Field Tanggal Untuk Jadwal 1 (Juni & Juli) -->
                        <div id="field_jadwal_1" class="month-field mb-3">
                            <div class="form-group">
                                <label for="tanggal_juni">Tanggal Piket Bulan Juni</label>
                                <input type="text" name="tanggal_juni" id="tanggal_juni" class="form-control datepicker" placeholder="Pilih tanggal (pisahkan dengan koma)" autocomplete="off">
                                <small class="form-text text-muted">Pilih tanggal untuk bulan Juni (pisahkan dengan koma).</small>
                            </div>
                            <div class="form-group">
                                <label for="tanggal_juli">Tanggal Piket Bulan Juli</label>
                                <input type="text" name="tanggal_juli" id="tanggal_juli" class="form-control datepicker" placeholder="Pilih tanggal (pisahkan dengan koma)" autocomplete="off">
                                <small class="form-text text-muted">Pilih tanggal untuk bulan Juli (pisahkan dengan koma).</small>
                            </div>
                        </div>
                        
                        <!-- Field Tanggal Untuk Jadwal 2 (Desember & Januari) -->
                        <div id="field_jadwal_2" class="month-field mb-3">
                            <div class="form-group">
                                <label for="tanggal_desember">Tanggal Piket Bulan Desember</label>
                                <input type="text" name="tanggal_desember" id="tanggal_desember" class="form-control datepicker" placeholder="Pilih tanggal (pisahkan dengan koma)" autocomplete="off">
                                <small class="form-text text-muted">Pilih tanggal untuk bulan Desember (pisahkan dengan koma).</small>
                            </div>
                            <div class="form-group">
                                <label for="tanggal_januari">Tanggal Piket Bulan Januari</label>
                                <input type="text" name="tanggal_januari" id="tanggal_januari" class="form-control datepicker" placeholder="Pilih tanggal (pisahkan dengan koma)" autocomplete="off">
                                <small class="form-text text-muted">Pilih tanggal untuk bulan Januari (pisahkan dengan koma).</small>
                            </div>
                        </div>
                        
                        <button type="submit" name="submit_schedule" class="btn btn-primary">Simpan Jadwal</button>
                    </form>
                </div><!-- end container-fluid -->
            </div><!-- end content -->
        </div><!-- end content-wrapper -->
    </div><!-- end wrapper -->

    <!-- JS Dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SB Admin 2 JS (opsional) -->
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <!-- Bootstrap Datepicker JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    <script>
    $(document).ready(function() {
        // Inisialisasi datepicker dengan opsi multidate
        $('.datepicker').datepicker({
            format: "yyyy-mm-dd",
            multidate: true,
            todayHighlight: true,
            autoclose: false
        });
        
        // Tampilkan field tanggal sesuai tipe jadwal yang dipilih
        $('#schedule_type').on('change', function() {
            var selected = $(this).val();
            if(selected == '1'){
                $('#field_jadwal_1').show();
                $('#field_jadwal_2').hide();
            } else if(selected == '2'){
                $('#field_jadwal_1').hide();
                $('#field_jadwal_2').show();
            } else {
                $('.month-field').hide();
            }
        }).trigger('change');
        
        // Dinamis: Tampilkan dropdown guru sesuai jumlah yang dipilih
        $('#jumlah_guru').on('change', function() {
            var jumlah = parseInt($(this).val());
            var container = $('#guru_container');
            container.empty();
            if(jumlah > 0) {
                for(var i = 1; i <= jumlah; i++){
                    var selectHTML = '<div class="form-group mb-2">' +
                        '<label>Pilih Guru ' + i + ':</label>' +
                        '<select name="guru[]" class="form-control" required>' +
                        '<option value="">-- Pilih Guru --</option>';
                    <?php
                        // Ambil data guru dari tabel anggota_sekolah dan buat opsi
                        $guru_options = "";
                        $sql = "SELECT nip, nama FROM anggota_sekolah ORDER BY nama ASC";
                        $res = $conn->query($sql);
                        while ($row = $res->fetch_assoc()) {
                            $guru_options .= '<option value="' . htmlspecialchars($row['nip']) . '">' . htmlspecialchars($row['nama']) . ' (' . htmlspecialchars($row['nip']) . ')</option>';
                        }
                    ?>
                    selectHTML += '<?= $guru_options; ?>' +
                                  '</select></div>';
                    container.append(selectHTML);
                }
            }
        }).trigger('change');
    });
    </script>
</body>
</html>
