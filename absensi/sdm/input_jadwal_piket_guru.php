<?php
// File: /payroll_absensi_v2/absensi/sdm/input_jadwal_piket_guru.php

require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
// Batasi akses hanya untuk role "M:SDM" dan "M:Superadmin"
authorize(['M:SDM', 'M:Superadmin'], '/payroll_absensi_v2/login.php');

// Sertakan koneksi ke database
require_once __DIR__ . '/../../koneksi.php';

// (Opsional) Hapus output buffering jika ada
if (ob_get_length()) {
    ob_end_clean();
}

// (Opsional) Generate CSRF token
generate_csrf_token();
$csrf_token = $_SESSION['csrf_token'];

// ==============================================================================
// 1. PROSES PENYIMPANAN DATA JADWAL PIKET (POST)
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_schedule'])) {
    $schedule_type = $_POST['schedule_type'] ?? '';
    if (!in_array($schedule_type, ['1', '2'])) {
        $_SESSION['error_message'] = "Pilih tipe jadwal yang valid.";
        header("Location: input_jadwal_piket_guru.php");
        exit();
    }
    
    $jumlah_guru = intval($_POST['jumlah_guru'] ?? 0);
    if ($jumlah_guru < 1 || $jumlah_guru > 5) {
        $_SESSION['error_message'] = "Pilih jumlah guru yang valid (1 sampai 5).";
        header("Location: input_jadwal_piket_guru.php");
        exit();
    }
    
    $selected_guru = $_POST['guru'] ?? [];
    if (count($selected_guru) != $jumlah_guru) {
        $_SESSION['error_message'] = "Pastikan untuk memilih $jumlah_guru guru.";
        header("Location: input_jadwal_piket_guru.php");
        exit();
    }
    
    // Ambil tanggal sesuai tipe jadwal
    if ($schedule_type === '1') {
        $dates_juni = trim($_POST['tanggal_juni'] ?? '');
        $dates_juli = trim($_POST['tanggal_juli'] ?? '');
        $arr_juni   = !empty($dates_juni) ? array_map('trim', explode(",", $dates_juni)) : [];
        $arr_juli   = !empty($dates_juli) ? array_map('trim', explode(",", $dates_juli)) : [];
        if (empty($arr_juni) && empty($arr_juli)) {
            $_SESSION['error_message'] = "Pilih minimal satu tanggal untuk bulan Juni atau Juli.";
            header("Location: input_jadwal_piket_guru.php");
            exit();
        }
    } else {
        $dates_desember = trim($_POST['tanggal_desember'] ?? '');
        $dates_januari  = trim($_POST['tanggal_januari'] ?? '');
        $arr_desember   = !empty($dates_desember) ? array_map('trim', explode(",", $dates_desember)) : [];
        $arr_januari    = !empty($dates_januari) ? array_map('trim', explode(",", $dates_januari)) : [];
        if (empty($arr_desember) && empty($arr_januari)) {
            $_SESSION['error_message'] = "Pilih minimal satu tanggal untuk bulan Desember atau Januari.";
            header("Location: input_jadwal_piket_guru.php");
            exit();
        }
    }
    
    // Ambil data guru (nip, nama)
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
            header("Location: input_jadwal_piket_guru.php");
            exit();
        }
    }
    $stmtGuru->close();
    
    // Contoh jam piket
    $waktu_piket = "08:00 - 13:00";
    
    // Simpan ke tabel jadwal_piket (transaksi)
    try {
        $conn->begin_transaction();
        
        if ($schedule_type === '1') {
            // Jadwal 1 (Juni & Juli)
            foreach ($arr_juni as $tanggal) {
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
        } else {
            // Jadwal 2 (Desember & Januari)
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
    
    header("Location: input_jadwal_piket_guru.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Jadwal Piket Guru (SDM) - Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Bootstrap Datepicker CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    <style>
        /* Agar mirip dengan style manage_salary_indices */
        body { padding-top: 20px; }
        #main-content {
            transition: opacity 0.3s ease;
        }
        .back-btn {
            margin-bottom: 20px;
            transition: background-color 0.3s, transform 0.2s;
        }
        .back-btn:hover {
            transform: scale(1.05);
        }
        .card-header {
            background: linear-gradient(45deg, #0d47a1, #42a5f5);
            color: white;
        }
        .month-field { display: none; }
        /* Minimalkan form field agar rapih seperti contoh */
        .form-label { font-weight: 600; }
        .table-hover tbody tr:hover {
            background-color: #e2e6ea;
        }
    </style>
</head>
<body id="page-top">
    <!-- Container utama (tanpa sidebar) -->
    <div class="container" id="main-content">
        <!-- Tombol Kembali -->
        <button class="btn btn-secondary back-btn" id="btnBack" data-href="/payroll_absensi_v2/absensi/sdm/laporan_jadwal_piket.php">
            <i class="fas fa-arrow-left"></i> Kembali
        </button>

        <!-- Judul Halaman -->
        <h1 class="h3 mb-4 text-dark">
            <i class="fas fa-calendar-plus"></i> Input Jadwal Piket Guru (SDM)
        </h1>

        <!-- Card untuk form -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-white">
                    <i class="fas fa-clipboard-list"></i> Form Jadwal Piket
                </h6>
            </div>
            <div class="card-body">
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

                <!-- Form Input Jadwal Piket -->
                <form method="POST" action="input_jadwal_piket_guru.php" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">

                    <!-- Pilih Tipe Jadwal -->
                    <div class="mb-3">
                        <label for="schedule_type" class="form-label">Pilih Jadwal Piket</label>
                        <select name="schedule_type" id="schedule_type" class="form-select" required>
                            <option value="">-- Pilih Jadwal Piket --</option>
                            <option value="1">Jadwal 1: Juni - Juli</option>
                            <option value="2">Jadwal 2: Desember - Januari</option>
                        </select>
                        <div class="invalid-feedback">Harap pilih jadwal piket.</div>
                    </div>

                    <!-- Pilih Jumlah Guru -->
                    <div class="mb-3">
                        <label for="jumlah_guru" class="form-label">Jumlah Guru/Karyawan yang Piket</label>
                        <select name="jumlah_guru" id="jumlah_guru" class="form-select" required>
                            <option value="">-- Pilih Jumlah --</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i; ?>"><?= $i; ?></option>
                            <?php endfor; ?>
                        </select>
                        <div class="invalid-feedback">Harap pilih jumlah guru/karyawan.</div>
                    </div>

                    <!-- Container Dropdown Guru (dinamis) -->
                    <div id="guru_container" class="mb-3"></div>

                    <!-- Field Tanggal Jadwal 1 -->
                    <div id="field_jadwal_1" class="month-field mb-3">
                        <div class="mb-3">
                            <label for="tanggal_juni" class="form-label">Tanggal Piket Bulan Juni</label>
                            <input type="text" name="tanggal_juni" id="tanggal_juni" class="form-control datepicker" placeholder="Pilih tanggal (pisahkan dengan koma)" autocomplete="off">
                            <small class="text-muted">Pilih tanggal untuk bulan Juni (pisahkan dengan koma).</small>
                        </div>
                        <div class="mb-3">
                            <label for="tanggal_juli" class="form-label">Tanggal Piket Bulan Juli</label>
                            <input type="text" name="tanggal_juli" id="tanggal_juli" class="form-control datepicker" placeholder="Pilih tanggal (pisahkan dengan koma)" autocomplete="off">
                            <small class="text-muted">Pilih tanggal untuk bulan Juli (pisahkan dengan koma).</small>
                        </div>
                    </div>

                    <!-- Field Tanggal Jadwal 2 -->
                    <div id="field_jadwal_2" class="month-field mb-3">
                        <div class="mb-3">
                            <label for="tanggal_desember" class="form-label">Tanggal Piket Bulan Desember</label>
                            <input type="text" name="tanggal_desember" id="tanggal_desember" class="form-control datepicker" placeholder="Pilih tanggal (pisahkan dengan koma)" autocomplete="off">
                            <small class="text-muted">Pilih tanggal untuk bulan Desember (pisahkan dengan koma).</small>
                        </div>
                        <div class="mb-3">
                            <label for="tanggal_januari" class="form-label">Tanggal Piket Bulan Januari</label>
                            <input type="text" name="tanggal_januari" id="tanggal_januari" class="form-control datepicker" placeholder="Pilih tanggal (pisahkan dengan koma)" autocomplete="off">
                            <small class="text-muted">Pilih tanggal untuk bulan Januari (pisahkan dengan koma).</small>
                        </div>
                    </div>

                    <button type="submit" name="submit_schedule" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Jadwal
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </form>
            </div>
        </div><!-- End Card -->
    </div>
    <!-- End Container -->

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    <script>
    $(document).ready(function() {
        // Inisialisasi datepicker
        $('.datepicker').datepicker({
            format: "yyyy-mm-dd",
            multidate: true,
            todayHighlight: true,
            autoclose: false
        });

        // Tampilkan field tanggal sesuai tipe jadwal
        $('#schedule_type').on('change', function() {
            var selected = $(this).val();
            if (selected == '1') {
                $('#field_jadwal_1').show();
                $('#field_jadwal_2').hide();
            } else if (selected == '2') {
                $('#field_jadwal_1').hide();
                $('#field_jadwal_2').show();
            } else {
                $('.month-field').hide();
            }
        }).trigger('change');

        // Dinamis: Tampilkan dropdown guru sesuai jumlah
        $('#jumlah_guru').on('change', function() {
            var jumlah = parseInt($(this).val());
            var container = $('#guru_container');
            container.empty();
            if (jumlah > 0) {
                for (var i = 1; i <= jumlah; i++) {
                    var selectHTML = '<div class="mb-2">' +
                        '<label class="form-label">Pilih Guru ' + i + ':</label>' +
                        '<select name="guru[]" class="form-control" required>' +
                        '<option value="">-- Pilih Guru --</option>';
                    <?php
                        // Ambil data guru dari tabel anggota_sekolah dan buat opsi
                        $guru_options = "";
                        $sql = "SELECT nip, nama FROM anggota_sekolah ORDER BY nama ASC";
                        $res = $conn->query($sql);
                        while ($row = $res->fetch_assoc()) {
                            $guru_options .= '<option value="' . htmlspecialchars($row['nip']) . '">' 
                                             . htmlspecialchars($row['nama']) 
                                             . ' (' . htmlspecialchars($row['nip']) . ')</option>';
                        }
                    ?>
                    selectHTML += '<?= $guru_options; ?>';
                    selectHTML += '</select></div>';
                    container.append(selectHTML);
                }
            }
        }).trigger('change');

        // Tombol Back
        $('#btnBack').on('click', function(e) {
            e.preventDefault();
            var url = $(this).data('href');
            $('#main-content').fadeOut(300, function() {
                window.location.href = url;
            });
        });

        // Fade out alert
        setTimeout(function() {
            $(".alert").fadeTo(500, 0).slideUp(500, function() {
                $(this).remove();
            });
        }, 3000);

        // Validasi form
        $('form.needs-validation').on('submit', function(e) {
            var form = this;
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            $(form).addClass('was-validated');
        });
    });
    </script>
</body>
</html>
