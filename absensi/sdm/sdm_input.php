<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../../koneksi.php';

// Ini file tambah jadwal piket di dashboard sdm

// PROSES: Penyimpanan Data Jadwal Piket
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
    
    // Waktu piket fix
    $waktu_piket = "08:00 - 13:00";
    
    // Simpan data ke tabel jadwal_piket
    try {
        $conn->begin_transaction();
        
        if ($schedule_type === '1') {
            // Jadwal 1: Juni - Juli
            foreach ($arr_juni as $tanggal) {
                if (empty($tanggal)) continue;
                // Pastikan tanggal valid dan merupakan bulan Juni (6)
                if (date("n", strtotime($tanggal)) != 6 && date("n", strtotime($tanggal)) != 7) {
                    throw new Exception("Pilih bulan yang sesuai jadwal! Untuk Jadwal 1, tanggal harus di bulan Juni atau Juli.");
                }
                foreach ($dataGuru as $nip => $nama) {
                    // Cek apakah jadwal sudah ada
                    $sql_check = "SELECT COUNT(*) AS jumlah FROM jadwal_piket WHERE nip = ? AND tanggal = ? AND waktu_piket = ?";
                    $stmt = $conn->prepare($sql_check);
                    $stmt->bind_param("sss", $nip, $tanggal, $waktu_piket);
                    $stmt->execute();
                    $res = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($res['jumlah'] > 0) continue;
                    
                    // Tentukan bulan dan tahun
                    $month_num = date("n", strtotime($tanggal));
                    $month_name = date("F", strtotime($tanggal)); // Nama bulan penuh
                    $year_value = date("Y", strtotime($tanggal));
                    
                    // Insert jadwal
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
                if (date("n", strtotime($tanggal)) != 6 && date("n", strtotime($tanggal)) != 7) {
                    throw new Exception("Pilih bulan yang sesuai jadwal! Untuk Jadwal 1, tanggal harus di bulan Juni atau Juli.");
                }
                foreach ($dataGuru as $nip => $nama) {
                    $sql_check = "SELECT COUNT(*) AS jumlah FROM jadwal_piket WHERE nip = ? AND tanggal = ? AND waktu_piket = ?";
                    $stmt = $conn->prepare($sql_check);
                    $stmt->bind_param("sss", $nip, $tanggal, $waktu_piket);
                    $stmt->execute();
                    $res = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($res['jumlah'] > 0) continue;
                    
                    $month_num = date("n", strtotime($tanggal));
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
            foreach ($arr_desember as $tanggal) {
                if (empty($tanggal)) continue;
                if (date("n", strtotime($tanggal)) != 12 && date("n", strtotime($tanggal)) != 1) {
                    throw new Exception("Pilih bulan yang sesuai jadwal! Untuk Jadwal 2, tanggal harus di bulan Desember atau Januari.");
                }
                foreach ($dataGuru as $nip => $nama) {
                    $sql_check = "SELECT COUNT(*) AS jumlah FROM jadwal_piket WHERE nip = ? AND tanggal = ? AND waktu_piket = ?";
                    $stmt = $conn->prepare($sql_check);
                    $stmt->bind_param("sss", $nip, $tanggal, $waktu_piket);
                    $stmt->execute();
                    $res = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($res['jumlah'] > 0) continue;
                    
                    $month_num = date("n", strtotime($tanggal));
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
                if (date("n", strtotime($tanggal)) != 12 && date("n", strtotime($tanggal)) != 1) {
                    throw new Exception("Pilih bulan yang sesuai jadwal! Untuk Jadwal 2, tanggal harus di bulan Desember atau Januari.");
                }
                foreach ($dataGuru as $nip => $nama) {
                    $sql_check = "SELECT COUNT(*) AS jumlah FROM jadwal_piket WHERE nip = ? AND tanggal = ? AND waktu_piket = ?";
                    $stmt = $conn->prepare($sql_check);
                    $stmt->bind_param("sss", $nip, $tanggal, $waktu_piket);
                    $stmt->execute();
                    $res = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($res['jumlah'] > 0) continue;
                    
                    $month_num = date("n", strtotime($tanggal));
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
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Input Jadwal Piket Guru (SDM)</title>
  <!-- SB Admin 2 & Bootstrap CSS -->
  <link href="../../assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
  <link href="../../assets/css/sb-admin-2.min.css" rel="stylesheet">
  <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Datepicker CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
  <style>
    .month-field { display: none; }
  </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a href="../../logout.php" class="btn btn-danger btn-sm">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </nav>
                
                <!-- Main Content SDM Input -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Input Jadwal Piket Guru (SDM)</h1>
                    
                    <!-- Menampilkan Notifikasi -->
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['success_message']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['error_message']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>
                    
                    <form method="POST" action="sdm_input.php">
                        <!-- Pilih Tipe Jadwal -->
                        <div class="form-group">
                            <label for="schedule_type">Pilih Jadwal Piket:</label>
                            <select name="schedule_type" id="schedule_type" class="form-control" required>
                                <option value="">-- Pilih Jadwal Piket --</option>
                                <option value="1">Jadwal 1: Juni - Juli</option>
                                <option value="2">Jadwal 2: Desember - Januari</option>
                            </select>
                        </div>

                        <!-- Pilih Jumlah Guru -->
                        <div class="form-group">
                            <label for="jumlah_guru">Jumlah Guru/Karyawan yang Piket:</label>
                            <select name="jumlah_guru" id="jumlah_guru" class="form-control" required>
                                <option value="">-- Pilih Jumlah --</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?= $i; ?>"><?= $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <!-- Container untuk dropdown guru dinamis -->
                        <div id="guru_container"></div>

                        <!-- Field Tanggal Untuk Jadwal 1 (Juni & Juli) -->
                        <div id="field_jadwal_1" class="month-field">
                            <div class="form-group">
                                <label for="tanggal_juni">Tanggal Piket Bulan Juni</label>
                                <input type="text" name="tanggal_juni" id="tanggal_juni" class="form-control datepicker" placeholder="Pilih tanggal (Pisahkan dengan koma)" autocomplete="off">
                                <small class="form-text text-muted">Pilih tanggal untuk bulan Juni (multi-select diizinkan, pisahkan dengan koma).</small>
                            </div>
                            <div class="form-group">
                                <label for="tanggal_juli">Tanggal Piket Bulan Juli</label>
                                <input type="text" name="tanggal_juli" id="tanggal_juli" class="form-control datepicker" placeholder="Pilih tanggal (Pisahkan dengan koma)" autocomplete="off">
                                <small class="form-text text-muted">Pilih tanggal untuk bulan Juli (multi-select diizinkan, pisahkan dengan koma).</small>
                            </div>
                        </div>

                        <!-- Field Tanggal Untuk Jadwal 2 (Desember & Januari) -->
                        <div id="field_jadwal_2" class="month-field">
                            <div class="form-group">
                                <label for="tanggal_desember">Tanggal Piket Bulan Desember</label>
                                <input type="text" name="tanggal_desember" id="tanggal_desember" class="form-control datepicker" placeholder="Pilih tanggal (Pisahkan dengan koma)" autocomplete="off">
                                <small class="form-text text-muted">Pilih tanggal untuk bulan Desember (multi-select diizinkan, pisahkan dengan koma).</small>
                            </div>
                            <div class="form-group">
                                <label for="tanggal_januari">Tanggal Piket Bulan Januari</label>
                                <input type="text" name="tanggal_januari" id="tanggal_januari" class="form-control datepicker" placeholder="Pilih tanggal (Pisahkan dengan koma)" autocomplete="off">
                                <small class="form-text text-muted">Pilih tanggal untuk bulan Januari (multi-select diizinkan, pisahkan dengan koma).</small>
                            </div>
                        </div>

                        <button type="submit" name="submit_schedule" class="btn btn-primary">Simpan Jadwal</button>
                    </form>
                </div> <!-- end container-fluid -->
            </div> <!-- end content -->
        </div> <!-- end content-wrapper -->
    </div> <!-- end wrapper -->

    <!-- jQuery, Bootstrap JS, dan Datepicker JS -->
    <script src="../../assets/vendor/jquery/jquery.min.js"></script>
    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../../assets/js/sb-admin-2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    <script>
        // Inisialisasi datepicker dengan opsi multidate
        $('.datepicker').datepicker({
            format: "yyyy-mm-dd",
            multidate: true,
            todayHighlight: true,
            autoclose: false
        });

        // Tampilkan field sesuai tipe jadwal
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
            container.empty(); // Bersihkan container
            if(jumlah > 0) {
                for(var i=1; i<=jumlah; i++){
                    // Gunakan field name "guru[]" agar hasilnya menjadi array
                    var selectHTML = '<div class="form-group">' +
                        '<label>Pilih Guru ' + i + ':</label>' +
                        '<select name="guru[]" class="form-control" required>' +
                        '<option value="">-- Pilih Guru --</option>';
                    <?php
                        // Ambil data guru dari tabel anggota_sekolah dan buat opsi dalam bentuk HTML string
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

        // Validasi tanggal sebelum submit:
        $('form').on('submit', function(e) {
            var scheduleType = $('#schedule_type').val();
            var valid = true;
            var errorMsg = "";
            if(scheduleType == '1'){
                var datesJuni = $('#tanggal_juni').val().split(",");
                var datesJuli = $('#tanggal_juli').val().split(",");
                $.each(datesJuni.concat(datesJuli), function(index, dateStr) {
                    if(dateStr.trim() !== ""){
                        var d = new Date(dateStr.trim());
                        var m = d.getMonth() + 1;
                        if(m != 6 && m != 7) {
                            valid = false;
                            errorMsg = "Untuk Jadwal 1, pilih tanggal di bulan Juni atau Juli!";
                            return false;
                        }
                    }
                });
            } else if(scheduleType == '2'){
                var datesDesember = $('#tanggal_desember').val().split(",");
                var datesJanuari = $('#tanggal_januari').val().split(",");
                $.each(datesDesember.concat(datesJanuari), function(index, dateStr) {
                    if(dateStr.trim() !== ""){
                        var d = new Date(dateStr.trim());
                        var m = d.getMonth() + 1;
                        if(m != 12 && m != 1) {
                            valid = false;
                            errorMsg = "Untuk Jadwal 2, pilih tanggal di bulan Desember atau Januari!";
                            return false;
                        }
                    }
                });
            }
            if(!valid){
                alert(errorMsg);
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
