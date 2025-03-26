    <?php
    // File: /payroll_absensi_v2/sdm/template_surat.php

    // ==============================================================================
    // 1. Pengaturan Session, Koneksi, dan Helper
    // ==============================================================================
    require_once __DIR__ . '/../helpers.php';
    start_session_safe();
    init_error_handling();

    authorize(['M:SDM', 'M:Superadmin']);

    require_once __DIR__ . '/../koneksi.php';

    // Nonaktifkan output buffering jika ada
    if (ob_get_length()) {
        ob_end_clean();
    }

    // ==============================================================================
    // 2. Menangani Permintaan AJAX (CRUD Template Surat dan Kirim Surat)
    // ==============================================================================
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $case = isset($_POST['case']) ? trim($_POST['case']) : '';
            switch ($case) {
                case 'LoadTemplates':
                    LoadTemplates($conn);
                    break;
                case 'AddTemplate':
                    AddTemplate($conn);
                    break;
                case 'GetTemplateDetail':
                    GetTemplateDetail($conn);
                    break;
                case 'UpdateTemplate':
                    UpdateTemplate($conn);
                    break;
                case 'DeleteTemplate':
                    DeleteTemplate($conn);
                    break;

                // Inilah case “KirimSurat” ketika user klik “Kirim” di modal detail
                case 'KirimSurat':
                    KirimSurat($conn);
                    break;

                default:
                    send_response(404, 'Kasus tidak ditemukan.');
            }
        } else {
            send_response(405, 'Metode Permintaan Tidak Diizinkan.');
        }
        exit();
    }

    // ==============================================================================
    // 3. Fungsi-Fungsi CRUD Template Surat
    // ==============================================================================
    function LoadTemplates($conn)
    {
        $sql = "SELECT * FROM template_surat ORDER BY created_at DESC";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            send_response(1, 'Query Error: ' . mysqli_error($conn));
        }
        $templates = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $templates[] = $row;
        }
        send_response(0, $templates);
    }

    function AddTemplate($conn)
    {
        $jenis_surat = isset($_POST['jenis_surat']) ? trim($_POST['jenis_surat']) : '';
        $judul       = isset($_POST['judul']) ? trim($_POST['judul']) : '';
        $isi         = isset($_POST['isi']) ? trim($_POST['isi']) : '';
        $created_by  = $_SESSION['id'] ?? 0;

        if (empty($jenis_surat) || empty($judul) || empty($isi)) {
            send_response(2, 'Jenis surat, judul, dan isi template wajib diisi.');
        }

        $stmt = $conn->prepare("
            INSERT INTO template_surat (jenis_surat, judul, isi, created_by)
            VALUES (?, ?, ?, ?)
        ");
        if (!$stmt) {
            send_response(1, 'Query Error: ' . $conn->error);
        }
        $stmt->bind_param("sssi", $jenis_surat, $judul, $isi, $created_by);
        if ($stmt->execute()) {
            send_response(0, 'Template surat berhasil ditambahkan.');
        } else {
            send_response(1, 'Gagal menambahkan template: ' . $stmt->error);
        }
        $stmt->close();
    }

    function GetTemplateDetail($conn)
    {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id <= 0) {
            send_response(1, 'ID tidak valid.');
        }
        $stmt = $conn->prepare("SELECT * FROM template_surat WHERE id=? LIMIT 1");
        if (!$stmt) {
            send_response(1, 'Query Error: ' . $conn->error);
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows == 1) {
            $row = $result->fetch_assoc();
            send_response(0, $row);
        } else {
            send_response(2, 'Template tidak ditemukan.');
        }
        $stmt->close();
    }

    function UpdateTemplate($conn)
    {
        $id          = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
        $jenis_surat = isset($_POST['edit_jenis_surat']) ? trim($_POST['edit_jenis_surat']) : '';
        $judul       = isset($_POST['edit_judul']) ? trim($_POST['edit_judul']) : '';
        $isi         = isset($_POST['edit_isi']) ? trim($_POST['edit_isi']) : '';

        if ($id <= 0 || empty($jenis_surat) || empty($judul) || empty($isi)) {
            send_response(3, 'Field wajib diisi dan ID harus valid.');
        }

        $stmt = $conn->prepare("UPDATE template_surat SET jenis_surat = ?, judul = ?, isi = ? WHERE id = ?");
        if (!$stmt) {
            send_response(1, 'Query Error: ' . $conn->error);
        }
        $stmt->bind_param("sssi", $jenis_surat, $judul, $isi, $id);
        if ($stmt->execute()) {
            send_response(0, 'Template berhasil diupdate.');
        } else {
            send_response(1, 'Gagal mengupdate template: ' . $stmt->error);
        }
        $stmt->close();
    }


    function DeleteTemplate($conn)
    {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id <= 0) {
            send_response(3, 'ID tidak valid.');
        }
        $stmt = $conn->prepare("DELETE FROM template_surat WHERE id=?");
        if (!$stmt) {
            send_response(1, 'Query Error: ' . $conn->error);
        }
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            send_response(0, 'Template berhasil dihapus.');
        } else {
            send_response(1, 'Gagal menghapus template: ' . $stmt->error);
        }
        $stmt->close();
    }

    // ==============================================================================
    // 4. Fungsi "KirimSurat" untuk memasukkan data ke laporan_surat
    // ==============================================================================
    function KirimSurat($conn)
    {
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $id_pengirim = $_SESSION['id'] ?? 0; // ID user yang login
        $penerima_option = isset($_POST['penerima_option']) ? trim($_POST['penerima_option']) : 'perorangan';
        $id_penerima = isset($_POST['id_penerima']) ? intval($_POST['id_penerima']) : 0;

        // Dapatkan data template
        $stmtT = $conn->prepare("SELECT * FROM template_surat WHERE id=? LIMIT 1");
        if (!$stmtT) {
            send_response(1, 'Query Error (template): ' . $conn->error);
        }
        $stmtT->bind_param("i", $template_id);
        $stmtT->execute();
        $resT = $stmtT->get_result();
        if (!$resT || $resT->num_rows < 1) {
            $stmtT->close();
            send_response(2, 'Template tidak ditemukan.');
        }
        $rowT = $resT->fetch_assoc();
        $stmtT->close();

        // Ambil data template
        $jenis_surat = $rowT['jenis_surat'];
        $judul       = $rowT['judul'];
        $isi         = $rowT['isi'];

        if ($penerima_option === 'semua') {
            // Insert satu baris surat dengan id_penerima = 0 untuk menandakan "Semua Anggota"
            if (!insertSurat($conn, $template_id, $id_pengirim, 0, $jenis_surat, $judul, $isi)) {
                send_response(1, 'Gagal mengirim surat ke semua anggota.');
            }
            send_response(0, "Surat berhasil dikirim ke semua anggota.");
        } else {
            // Perorangan: pastikan ID penerima valid
            if ($id_penerima <= 0) {
                send_response(3, 'ID penerima tidak valid.');
            }
            if (!insertSurat($conn, $template_id, $id_pengirim, $id_penerima, $jenis_surat, $judul, $isi)) {
                send_response(1, 'Gagal mengirim surat.');
            }
            send_response(0, 'Surat berhasil dikirim ke penerima yang dipilih.');
        }
    }


    /**
     * Helper untuk insert ke laporan_surat
     * 
     * @return bool true jika berhasil, false jika gagal
     */
    function insertSurat($conn, $template_id, $id_pengirim, $id_penerima, $jenis_surat, $judul, $isi)
    {
        // Asumsikan kolom 'jenis_surat' di laporan_surat bisa diisi sesuai template
        // Status default 'terkirim'
        $stmt = $conn->prepare("
            INSERT INTO laporan_surat 
                (id_pengirim, id_penerima, jenis_surat, judul, isi, status, template_id)
            VALUES (?, ?, ?, ?, ?, 'terkirim', ?)
        ");
        if (!$stmt) {
            error_log('Query Insert Error: ' . $conn->error);
            return false;
        }
        $stmt->bind_param("iisssi", $id_pengirim, $id_penerima, $jenis_surat, $judul, $isi, $template_id);
        $res = $stmt->execute();
        if (!$res) {
            error_log('Insert Surar Error: ' . $stmt->error);
        }
        $stmt->close();
        return $res;
    }
    ?>
    <!DOCTYPE html>
    <html lang="id">

    <head>
        <meta charset="UTF-8">
        <title>Manajemen Template Surat - Sistem Sekolah</title>
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <!-- Bootstrap 5 CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <!-- Font Awesome & Bootstrap Icons -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
        <style>
            body {
                padding-top: 20px;
            }

            .card-template {
                cursor: pointer;
                transition: transform 0.2s;
            }

            .card-template:hover {
                transform: scale(1.03);
            }

            .card-header {
                background: linear-gradient(45deg, #0d47a1, #42a5f5);
                color: white;
            }

            #loadingSpinner {
                display: none;
                position: fixed;
                z-index: 9999;
                height: 100px;
                width: 100px;
                margin: auto;
                top: 0;
                left: 0;
                bottom: 0;
                right: 0;
            }
        </style>
    </head>

    <body>
        <div class="container">
            <button class="btn btn-secondary mb-3" id="btnBack" data-href="/payroll_absensi_v2/sdm/pembuatan_surat.php">
                <i class="fas fa-arrow-left"></i> Kembali ke Pembuatan Surat
            </button>
            <h1 class="mb-4 text-dark">
                <i class="fas fa-file-alt"></i> Manajemen Template Surat
            </h1>

            <!-- Tombol Tambah Template -->
            <div class="d-flex justify-content-end mb-3">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                    <i class="fas fa-plus"></i> Tambah Template Surat
                </button>
            </div>

            <!-- Container Card Template -->
            <div class="row" id="templateContainer">
                <!-- Card akan dimuat via AJAX -->
            </div>
        </div>

        <!-- Loading Spinner -->
        <div id="loadingSpinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

        <!-- MODAL: Review & Kirim Surat -->
        <div class="modal fade" id="templateModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Review Template</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="selected_template_id">
                        <h5 id="modalJudul"></h5>
                        <p><strong>Jenis Surat:</strong> <span id="modalJenis"></span></p>
                        <hr>
                        <div id="modalIsi" style="white-space: pre-wrap;"></div>

                        <!-- Pilih Penerima: "Semua" atau "Perorangan" -->
                        <div class="mt-3 border-top pt-3">
                            <h5>Pilih Penerima</h5>
                            <select class="form-select mb-3" id="penerima_option">
                                <option value="perorangan">Perorangan</option>
                                <option value="semua">Semua Anggota</option>
                            </select>

                            <!-- Form Pilih Perorangan -->
                            <div id="groupPerorangan">
                                <label class="form-label">Pilih Penerima</label>
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control" id="nama_anggota" placeholder="Pilih anggota..." readonly>
                                    <button type="button" class="btn btn-secondary" id="btnPilihAnggota">
                                        Pilih
                                    </button>
                                </div>
                                <input type="hidden" id="id_penerima">
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Tutup
                        </button>
                        <button type="button" class="btn btn-warning" id="editTemplateBtn">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button type="button" class="btn btn-danger" id="deleteTemplateBtn">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                        <button type="button" class="btn btn-success" id="btnKirimSurat">
                            <i class="fas fa-paper-plane"></i> Kirim Surat
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: Tambah Template -->
        <div class="modal fade" id="addTemplateModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="add-template-form" class="needs-validation" novalidate>
                        <input type="hidden" name="case" value="AddTemplate">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-plus"></i> Tambah Template Surat</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="jenis_surat" class="form-label">Jenis Surat <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="jenis_surat" name="jenis_surat" required>
                                <div class="invalid-feedback">Jenis surat wajib diisi.</div>
                            </div>
                            <div class="mb-3">
                                <label for="judul" class="form-label">Judul Template <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="judul" name="judul" required>
                                <div class="invalid-feedback">Judul wajib diisi.</div>
                            </div>
                            <div class="mb-3">
                                <label for="isi" class="form-label">Isi Template <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="isi" name="isi" rows="4" required></textarea>
                                <div class="invalid-feedback">Isi template wajib diisi.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan
                                <span class="spinner-border spinner-border-sm d-none"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL: Edit Template -->
        <div class="modal fade" id="editTemplateModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="edit-template-form" class="needs-validation" novalidate>
                        <input type="hidden" name="case" value="UpdateTemplate">
                        <input type="hidden" id="edit_id" name="edit_id">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Template Surat</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="edit_jenis_surat" class="form-label">Jenis Surat <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_jenis_surat" name="edit_jenis_surat" required>
                                <div class="invalid-feedback">Jenis surat wajib diisi.</div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_judul" class="form-label">Judul Template <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_judul" name="edit_judul" required>
                                <div class="invalid-feedback">Judul wajib diisi.</div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_isi" class="form-label">Isi Template <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="edit_isi" name="edit_isi" rows="4" required></textarea>
                                <div class="invalid-feedback">Isi template wajib diisi.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update
                                <span class="spinner-border spinner-border-sm d-none"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL: Hapus Template -->
        <div class="modal fade" id="deleteTemplateModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form id="delete-template-form">
                    <input type="hidden" name="case" value="DeleteTemplate">
                    <input type="hidden" id="delete_id" name="id">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-trash-alt"></i> Hapus Template</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Yakin ingin menghapus template <strong id="delete_judul"></strong>?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Hapus
                                <span class="spinner-border spinner-border-sm d-none"></span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Pilih Anggota (Perorangan) -->
        <div class="modal fade" id="modalPilihAnggota" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5>Pilih Anggota Sekolah</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Pilih</th>
                                        <th>NIP</th>
                                        <th>Nama</th>
                                        <th>Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sqlA = "SELECT id, nip, nama, role FROM anggota_sekolah ORDER BY nama ASC";
                                    $resA = $conn->query($sqlA);
                                    while ($rowA = $resA->fetch_assoc()):
                                    ?>
                                        <tr>
                                            <td>
                                                <input type="radio" name="radioAnggota" class="radio-anggota"
                                                    data-id="<?= $rowA['id'] ?>"
                                                    data-nama="<?= htmlspecialchars($rowA['nama']) ?>">
                                            </td>
                                            <td><?= htmlspecialchars($rowA['nip']) ?></td>
                                            <td><?= htmlspecialchars($rowA['nama']) ?></td>
                                            <td><?= htmlspecialchars($rowA['role']) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <button class="btn btn-primary" id="btnKonfirmasiPilihAnggota">
                            <i class="fas fa-check"></i> Pilih
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- JS Dependencies -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
        <!-- SweetAlert2 -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <script>
            $(document).ready(function() {

                // Fungsi helper toast
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer);
                        toast.addEventListener('mouseleave', Swal.resumeTimer);
                    }
                });

                function showToast(msg, icon = 'success') {
                    Toast.fire({
                        icon: icon,
                        title: msg
                    });
                }

                // 1. Load Templates (Cards)
                function loadTemplates() {
                    $.ajax({
                        url: "template_surat.php?ajax=1",
                        type: "POST",
                        data: {
                            case: "LoadTemplates"
                        },
                        dataType: "json",
                        beforeSend: function() {
                            $('#loadingSpinner').show();
                        },
                        success: function(res) {
                            $('#loadingSpinner').hide();
                            if (res.code == 0) {
                                let data = res.result;
                                let html = '';
                                if (data.length > 0) {
                                    $.each(data, function(i, t) {
                                        html += `<div class="col-md-4 mb-3">
                                    <div class="card card-template" 
                                            data-id="${t.id}"
                                            data-jenis_surat="${t.jenis_surat}"
                                            data-judul="${t.judul}"
                                            data-isi="${t.isi}">
                                        <div class="card-header">${t.judul}</div>
                                        <div class="card-body">
                                        <p class="card-text"><strong>Jenis: </strong>${t.jenis_surat}</p>
                                        </div>
                                    </div>
                                    </div>`;
                                    });
                                } else {
                                    html = `<div class="col-12">
                                    <p class="text-center">Belum ada template surat.</p>
                                </div>`;
                                }
                                $('#templateContainer').html(html);
                            } else {
                                showToast(res.result, 'error');
                            }
                        },
                        error: function() {
                            $('#loadingSpinner').hide();
                            showToast('Gagal memuat template surat.', 'error');
                        }
                    });
                }
                loadTemplates();

                // 2. Klik Card -> Munculkan Modal Review
                $(document).on('click', '.card-template', function() {
                    let tid = $(this).data('id');
                    let jenis = $(this).data('jenis_surat');
                    let judul = $(this).data('judul');
                    let isi = $(this).data('isi');

                    $('#selected_template_id').val(tid);
                    $('#modalJenis').text(jenis);
                    $('#modalJudul').text(judul);
                    $('#modalIsi').text(isi);

                    // Reset penerima
                    $('#penerima_option').val('perorangan');
                    $('#id_penerima').val('');
                    $('#nama_anggota').val('');
                    $('#groupPerorangan').show();

                    $('#templateModal').modal('show');
                });

                // 3. Select "Semua" / "Perorangan"
                $('#penerima_option').on('change', function() {
                    if ($(this).val() === 'semua') {
                        $('#groupPerorangan').hide();
                        $('#id_penerima').val('');
                        $('#nama_anggota').val('');
                    } else {
                        $('#groupPerorangan').show();
                    }
                });

                // 4. Tombol Pilih Anggota (Perorangan)
                $('#btnPilihAnggota').on('click', function() {
                    $('input.radio-anggota').prop('checked', false);
                    $('#modalPilihAnggota').modal('show');
                });
                $('#btnKonfirmasiPilihAnggota').on('click', function() {
                    let checkedRadio = $('input.radio-anggota:checked');
                    if (checkedRadio.length === 0) {
                        showToast('Pilih satu anggota!', 'warning');
                        return;
                    }
                    let pid = checkedRadio.data('id');
                    let pnama = checkedRadio.data('nama');
                    $('#id_penerima').val(pid);
                    $('#nama_anggota').val(pnama);
                    $('#modalPilihAnggota').modal('hide');
                });

                // 5. Kirim Surat
                $('#btnKirimSurat').on('click', function() {
                    let template_id = $('#selected_template_id').val();
                    let penerima_option = $('#penerima_option').val();
                    let id_penerima = $('#id_penerima').val() || 0;

                    $.ajax({
                        url: "template_surat.php?ajax=1",
                        type: "POST",
                        data: {
                            case: 'KirimSurat',
                            template_id: template_id,
                            penerima_option: penerima_option,
                            id_penerima: id_penerima
                        },
                        dataType: "json",
                        beforeSend: function() {
                            $('#btnKirimSurat').prop('disabled', true);
                        },
                        success: function(res) {
                            $('#btnKirimSurat').prop('disabled', false);
                            if (res.code == 0) {
                                showToast(res.result, 'success');
                                $('#templateModal').modal('hide');
                            } else {
                                showToast(res.result, 'error');
                            }
                        },
                        error: function() {
                            $('#btnKirimSurat').prop('disabled', false);
                            showToast('Terjadi kesalahan saat mengirim surat.', 'error');
                        }
                    });
                });

                // 6. Tombol Back
                $('#btnBack').on('click', function(e) {
                    e.preventDefault();
                    let url = $(this).data('href');
                    window.location.href = url;
                });

                // 7. CRUD: Tambah Template
                $('#add-template-form').on('submit', function(e) {
                    e.preventDefault();
                    let form = $(this);
                    if (!this.checkValidity()) {
                        e.stopPropagation();
                        form.addClass('was-validated');
                        return;
                    }
                    $.ajax({
                        url: "template_surat.php?ajax=1",
                        type: "POST",
                        data: form.serialize(),
                        dataType: "json",
                        beforeSend: function() {
                            form.find('button[type="submit"]').prop('disabled', true);
                            form.find('.spinner-border').removeClass('d-none');
                        },
                        success: function(res) {
                            form.find('button[type="submit"]').prop('disabled', false);
                            form.find('.spinner-border').addClass('d-none');
                            if (res.code == 0) {
                                showToast(res.result, 'success');
                                $('#addTemplateModal').modal('hide');
                                form[0].reset();
                                form.removeClass('was-validated');
                                loadTemplates();
                            } else {
                                showToast(res.result, 'error');
                            }
                        },
                        error: function() {
                            form.find('button[type="submit"]').prop('disabled', false);
                            form.find('.spinner-border').addClass('d-none');
                            showToast('Gagal menambahkan template.', 'error');
                        }
                    });
                });

                // 8. Edit Template
                $('#edit-template-form').on('submit', function(e) {
                    e.preventDefault();
                    let form = $(this);
                    if (!this.checkValidity()) {
                        e.stopPropagation();
                        form.addClass('was-validated');
                        return;
                    }
                    $.ajax({
                        url: "template_surat.php?ajax=1",
                        type: "POST",
                        data: form.serialize(),
                        dataType: "json",
                        beforeSend: function() {
                            form.find('button[type="submit"]').prop('disabled', true);
                            form.find('.spinner-border').removeClass('d-none');
                        },
                        success: function(res) {
                            form.find('button[type="submit"]').prop('disabled', false);
                            form.find('.spinner-border').addClass('d-none');
                            if (res.code == 0) {
                                showToast(res.result, 'success');
                                $('#editTemplateModal').modal('hide');
                                loadTemplates(); // Reload daftar template
                            } else {
                                showToast(res.result, 'error');
                            }
                        },
                        error: function() {
                            form.find('button[type="submit"]').prop('disabled', false);
                            form.find('.spinner-border').addClass('d-none');
                            showToast('Terjadi kesalahan saat update template.', 'error');
                        }
                    });
                });

                $('#templateModal').on('click', '#editTemplateBtn', function() {
                    let tid = $('#selected_template_id').val();
                    $.ajax({
                        url: "template_surat.php?ajax=1",
                        type: "POST",
                        data: {
                            case: 'GetTemplateDetail',
                            id: tid
                        },
                        dataType: "json",
                        success: function(res) {
                            if (res.code == 0) {
                                $('#edit_id').val(res.result.id);
                                $('#edit_jenis_surat').val(res.result.jenis_surat);
                                $('#edit_judul').val(res.result.judul);
                                $('#edit_isi').val(res.result.isi);
                                $('#templateModal').modal('hide');
                                $('#editTemplateModal').modal('show');
                            } else {
                                showToast(res.result, 'error');
                            }
                        },
                        error: function() {
                            showToast('Gagal mengambil detail template.', 'error');
                        }
                    });
                });


                // 9. Hapus Template
                // Tombol Delete Template: Buka modal hapus
                $('#templateModal').on('click', '#deleteTemplateBtn', function() {
                    let tid = $('#selected_template_id').val();
                    let judul = $('#modalJudul').text();
                    $('#delete_id').val(tid);
                    $('#delete_judul').text(judul);
                    $('#templateModal').modal('hide');
                    $('#deleteTemplateModal').modal('show');
                });

                // Form: Delete Template
                $('#delete-template-form').on('submit', function(e) {
                    e.preventDefault();
                    let f = $(this);
                    let id = $('#delete_id').val();
                    if (!id) {
                        showToast('ID tidak ditemukan.', 'error');
                        return;
                    }
                    $.ajax({
                        url: "template_surat.php?ajax=1",
                        type: "POST",
                        data: f.serialize(),
                        dataType: "json",
                        beforeSend: function() {
                            f.find('button[type="submit"]').prop('disabled', true);
                            f.find('.spinner-border').removeClass('d-none');
                        },
                        success: function(res) {
                            f.find('button[type="submit"]').prop('disabled', false);
                            f.find('.spinner-border').addClass('d-none');
                            if (res.code == 0) {
                                showToast(res.result, 'success');
                                $('#deleteTemplateModal').modal('hide');
                                loadTemplates();
                            } else {
                                showToast(res.result, 'error');
                            }
                        },
                        error: function() {
                            f.find('button[type="submit"]').prop('disabled', false);
                            f.find('.spinner-border').addClass('d-none');
                            showToast('Gagal menghapus template.', 'error');
                        }
                    });
                });

            });
        </script>
    </body>

    </html>