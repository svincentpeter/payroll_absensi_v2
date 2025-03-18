// script_employees.js

$(document).ready(function() {
    // Inisialisasi DataTables dengan Buttons dan Responsive
    var empTable = $('#employees').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "../../payroll/keuangan/employees.php?ajax=1&case=LoadingEmployees",
            "type": "POST"
        },
        "columns": [
            { "data": 0 }, // ID
            { "data": 1 }, // NIP
            { "data": 2 }, // Nama
            { "data": 3 }, // Gelar
            { "data": 4 }, // Jenjang
            { "data": 5 }, // Job Title
            { "data": 6 }, // Status
            { "data": 7 }, // No Rekening
            { "data": 8 }, // Email
            { "data": 9, "orderable": false } // Aksi
        ],
        "order": [[0, 'desc']],
        "dom": 'Bfrtip',
        "buttons": [
            {
                extend: 'copyHtml5',
                text: '<i class="fas fa-copy"></i> Copy',
                className: 'btn btn-secondary btn-sm'
            },
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-success btn-sm'
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-danger btn-sm',
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: {
                    columns: ':visible'
                },
                customize: function (doc) {
                    doc.styles.tableHeader.alignment = 'center';
                    doc.styles.tableBody.alignment = 'center';
                    doc.styles.tableHeader.fontSize = 8;
                    doc.styles.tableBody.fontSize = 7;
                    doc.pageMargins = [10, 10, 10, 10];
                }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> Print',
                className: 'btn btn-info btn-sm',
                exportOptions: {
                    columns: ':visible'
                },
                customize: function (win) {
                    $(win.document.body).css('font-size', '10pt');
                    $(win.document.body).find('table')
                        .addClass('compact')
                        .css('font-size', 'inherit');
                }
            },
            {
                extend: 'colvis',
                text: '<i class="fas fa-columns"></i> Kolom',
                className: 'btn btn-warning btn-sm'
            }
        ],
        "responsive": true,
        "pageLength": 10,
        "autoWidth": false,
        "columnDefs": [
            { "orderable": false, "targets": -1 }, // Non-ordinable kolom terakhir (Aksi)
            // Menetapkan responsivePriority untuk kolom-kolom penting
            { "responsivePriority": 1, "targets": 0 }, // ID
            { "responsivePriority": 2, "targets": 1 }, // NIP
            { "responsivePriority": 3, "targets": 2 }, // Nama
            { "responsivePriority": 4, "targets": 5 }, // Job Title
            { "responsivePriority": 5, "targets": 6 }, // Status
            { "responsivePriority": 6, "targets": 3 }, // Gelar
            { "responsivePriority": 7, "targets": 4 }, // Jenjang
            { "responsivePriority": 8, "targets": 7 }, // No Rekening
            { "responsivePriority": 9, "targets": 8 }, // Email
            { "responsivePriority": 10, "targets": 9 } // Aksi
        ]
    });

    // Form Submission untuk Tambah Karyawan
    $('#addEmployeeForm').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var formData = form.serialize();

        $.ajax({
            url: '../../payroll/keuangan/employees.php?ajax=1&case=AddEmployee',
            type: 'POST',
            data: formData,
            dataType: 'json',
            beforeSend: function() {
                form.find('button[type="submit"]').prop('disabled', true);
                form.find('.spinner-border').removeClass('d-none');
            },
            success: function(response) {
                form.find('button[type="submit"]').prop('disabled', false);
                form.find('.spinner-border').addClass('d-none');

                if (response.code === 0) {
                    $.notify({
                        icon: 'fas fa-check-circle',
                        message: response.result
                    },{
                        type: 'success',
                        delay: 3000,
                        placement: {
                            from: "top",
                            align: "right"
                        }
                    });
                    $('#addEmployeeModal').modal('hide');
                    empTable.ajax.reload(null, false);
                    form[0].reset();
                } else {
                    $.notify({
                        icon: 'fas fa-times-circle',
                        message: response.result
                    },{
                        type: 'danger',
                        delay: 3000,
                        placement: {
                            from: "top",
                            align: "right"
                        }
                    });
                }
            },
            error: function() {
                form.find('button[type="submit"]').prop('disabled', false);
                form.find('.spinner-border').addClass('d-none');
                $.notify({
                    icon: 'fas fa-exclamation-triangle',
                    message: 'Terjadi kesalahan saat menambahkan karyawan.'
                },{
                    type: 'danger',
                    delay: 3000,
                    placement: {
                        from: "top",
                        align: "right"
                    }
                });
            }
        });
    });

    // Inisialisasi Datepicker (Jika diperlukan, saat menggunakan input type date)
    // Tidak perlu karena browser modern sudah mendukung input type date

    // Mengisi data ke Modal Edit saat tombol Edit diklik
    $('#employees tbody').on('click', '.btnEdit', function() {
        var btn = $(this);
        var id = btn.data('id');

        // Mengambil data karyawan melalui AJAX
        $.ajax({
            url: '../../payroll/keuangan/employees.php?ajax=1&case=ViewEmployeeDetail',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.code === 0) {
                    var emp = response.result;
                    $('#editEmployeeId').val(emp.id);
                    $('#editNip').val(emp.nip);
                    $('#editNama').val(emp.nama);
                    $('#editGelar').val(emp.gelar);
                    $('#editJenjang').val(emp.jenjang);
                    $('#editJobTitle').val(emp.job_title);
                    $('#editStatus').val(emp.status);
                    $('#editJoinStart').val(emp.join_start);
                    $('#editMasaKerjaYear').val(emp.masa_kerja_year);
                    $('#editMasaKerjaMonth').val(emp.masa_kerja_month);
                    $('#editRemark').val(emp.remark);
                    if(emp.jk === 'L') {
                        $('#editJkL').prop('checked', true);
                    } else {
                        $('#editJkP').prop('checked', true);
                    }
                    $('#editTglLahir').val(emp.tgl_lahir);
                    $('#editUsia').val(emp.usia);
                    $('#editReligion').val(emp.religion);
                    $('#editAlamatDomisili').val(emp.alamat_domisili);
                    $('#editAlamatKTP').val(emp.alamat_ktp);
                    $('#editNoHP').val(emp.no_hp);
                    $('#editPendidikan').val(emp.pendidikan);
                    $('#editMarital').val(emp.marital);
                    $('#editEmail').val(emp.email);
                    $('#editNoRekening').val(emp.no_rekening);
                    // Jika ada salary_index_id, tambahkan sesuai kebutuhan

                    // Menampilkan modal Edit
                    $('#editEmployeeModal').modal('show');
                } else {
                    $.notify({
                        icon: 'fas fa-times-circle',
                        message: response.result
                    },{
                        type: 'danger',
                        delay: 3000,
                        placement: {
                            from: "top",
                            align: "right"
                        }
                    });
                }
            },
            error: function() {
                $.notify({
                    icon: 'fas fa-exclamation-triangle',
                    message: 'Terjadi kesalahan saat mengambil data karyawan.'
                },{
                    type: 'danger',
                    delay: 3000,
                    placement: {
                        from: "top",
                        align: "right"
                    }
                });
            }
        });
    });

    // Form Submission untuk Edit Karyawan
    $('#editEmployeeForm').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var formData = form.serialize();

        $.ajax({
            url: '../../payroll/keuangan/employees.php?ajax=1&case=EditEmployee',
            type: 'POST',
            data: formData,
            dataType: 'json',
            beforeSend: function() {
                form.find('button[type="submit"]').prop('disabled', true);
                form.find('.spinner-border').removeClass('d-none');
            },
            success: function(response) {
                form.find('button[type="submit"]').prop('disabled', false);
                form.find('.spinner-border').addClass('d-none');

                if (response.code === 0) {
                    $.notify({
                        icon: 'fas fa-check-circle',
                        message: response.result
                    },{
                        type: 'success',
                        delay: 3000,
                        placement: {
                            from: "top",
                            align: "right"
                        }
                    });
                    $('#editEmployeeModal').modal('hide');
                    empTable.ajax.reload(null, false);
                } else {
                    $.notify({
                        icon: 'fas fa-times-circle',
                        message: response.result
                    },{
                        type: 'danger',
                        delay: 3000,
                        placement: {
                            from: "top",
                            align: "right"
                        }
                    });
                }
            },
            error: function() {
                form.find('button[type="submit"]').prop('disabled', false);
                form.find('.spinner-border').addClass('d-none');
                $.notify({
                    icon: 'fas fa-exclamation-triangle',
                    message: 'Terjadi kesalahan saat memperbarui karyawan.'
                },{
                    type: 'danger',
                    delay: 3000,
                    placement: {
                        from: "top",
                        align: "right"
                    }
                });
            }
        });
    });

    // Menghapus Karyawan saat tombol Delete diklik
    $('#employees tbody').on('click', '.btnDelete', function() {
        var btn = $(this);
        var id = btn.data('id');

        if (confirm('Apakah Anda yakin ingin menghapus karyawan ini?')) {
            $.ajax({
                url: '../../payroll/keuangan/employees.php?ajax=1&case=DeleteEmployee',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.code === 0) {
                        $.notify({
                            icon: 'fas fa-check-circle',
                            message: response.result
                        },{
                            type: 'success',
                            delay: 3000,
                            placement: {
                                from: "top",
                                align: "right"
                            }
                        });
                        empTable.ajax.reload(null, false);
                    } else {
                        $.notify({
                            icon: 'fas fa-times-circle',
                            message: response.result
                        },{
                            type: 'danger',
                            delay: 3000,
                            placement: {
                                from: "top",
                                align: "right"
                            }
                        });
                    }
                },
                error: function() {
                    $.notify({
                        icon: 'fas fa-exclamation-triangle',
                        message: 'Terjadi kesalahan saat menghapus karyawan.'
                    },{
                        type: 'danger',
                        delay: 3000,
                        placement: {
                            from: "top",
                            align: "right"
                        }
                    });
                }
            });
        }
    });

    // Mengassign Payheads saat tombol Assign Payheads diklik
    $('#employees tbody').on('click', '.btnAssignPayheads', function() {
        var btn = $(this);
        var id = btn.data('id');

        $('#assignEmployeeId').val(id);
        $('#availablePayheads').empty();
        $('#assignedPayheads').empty();
        $('#payheadAmounts').empty();

        // Mengambil payheads yang tersedia
        $.ajax({
            url: '../../payroll/keuangan/employees.php?ajax=1&case=GetAvailablePayheads',
            type: 'POST',
            data: { employee_id: id },
            dataType: 'json',
            success: function(response) {
                if (response.code === 0) {
                    if (response.result.length > 0) {
                        $.each(response.result, function(index, payhead) {
                            $('#availablePayheads').append(
                                $('<option>', {
                                    value: payhead.id,
                                    text: payhead.nama_payhead + ' (' + payhead.jenis_payhead.charAt(0).toUpperCase() + payhead.jenis_payhead.slice(1) + ')',
                                    class: payhead.jenis_payhead === 'earnings' ? 'text-success' : 'text-danger'
                                })
                            );
                        });
                    } else {
                        $('#availablePayheads').append('<option value="">Tidak ada payheads yang tersedia.</option>');
                    }
                }
            },
            error: function() {
                $.notify({
                    icon: 'fas fa-exclamation-triangle',
                    message: 'Terjadi kesalahan saat mengambil payheads.'
                },{
                    type: 'danger',
                    delay: 3000,
                    placement: {
                        from: "top",
                        align: "right"
                    }
                });
            }
        });

        // Mengambil payheads yang sudah ditugaskan
        $.ajax({
            url: '../../payroll/keuangan/employees.php?ajax=1&case=ViewEmployeeDetail',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.code === 0 && response.result.payheads.length > 0) {
                    $.each(response.result.payheads, function(index, payhead) {
                        $('#assignedPayheads').append(
                            $('<option>', {
                                value: payhead.nama_payhead, // Sesuaikan jika payhead_id diperlukan
                                text: payhead.nama_payhead + ' (' + payhead.jenis_payhead.charAt(0).toUpperCase() + payhead.jenis_payhead.slice(1) + ')',
                                selected: true
                            })
                        );
                        // Menambahkan input jumlah payhead
                        $('#payheadAmounts').append(
                            '<div class="form-group">' +
                                '<label for="amount_' + payhead.nama_payhead + '">' + payhead.nama_payhead + ' (Rp)</label>' +
                                '<input type="number" step="0.01" name="pay_amounts[' + payhead.nama_payhead + ']" id="amount_' + payhead.nama_payhead + '" class="form-control" value="' + payhead.amount + '">' +
                            '</div>'
                        );
                    });
                }
            },
            error: function() {
                $.notify({
                    icon: 'fas fa-exclamation-triangle',
                    message: 'Terjadi kesalahan saat mengambil payheads yang ditugaskan.'
                },{
                    type: 'danger',
                    delay: 3000,
                    placement: {
                        from: "top",
                        align: "right"
                    }
                });
            }
        });

        // Menampilkan modal Assign Payheads
        $('#assignPayheadsModal').modal('show');
    });

    // Form Submission untuk Assign Payheads
    $('#assignPayheadsForm').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var formData = form.serialize();

        $.ajax({
            url: '../../payroll/keuangan/employees.php?ajax=1&case=AssignPayheadsToEmployee',
            type: 'POST',
            data: formData,
            dataType: 'json',
            beforeSend: function() {
                form.find('button[type="submit"]').prop('disabled', true);
                form.find('.spinner-border').removeClass('d-none');
            },
            success: function(response) {
                form.find('button[type="submit"]').prop('disabled', false);
                form.find('.spinner-border').addClass('d-none');

                if (response.code === 0) {
                    $.notify({
                        icon: 'fas fa-check-circle',
                        message: response.result
                    },{
                        type: 'success',
                        delay: 3000,
                        placement: {
                            from: "top",
                            align: "right"
                        }
                    });
                    $('#assignPayheadsModal').modal('hide');
                    empTable.ajax.reload(null, false);
                } else {
                    $.notify({
                        icon: 'fas fa-times-circle',
                        message: response.result
                    },{
                        type: 'danger',
                        delay: 3000,
                        placement: {
                            from: "top",
                            align: "right"
                        }
                    });
                }
            },
            error: function() {
                form.find('button[type="submit"]').prop('disabled', false);
                form.find('.spinner-border').addClass('d-none');
                $.notify({
                    icon: 'fas fa-exclamation-triangle',
                    message: 'Terjadi kesalahan saat menugaskan payheads.'
                },{
                    type: 'danger',
                    delay: 3000,
                    placement: {
                        from: "top",
                        align: "right"
                    }
                });
            }
        });
    });

    // Menghapus Karyawan (Sudah dihandle sebelumnya)

    // Mengisi data ke Modal View Detail saat tombol View Detail diklik
    $('#employees tbody').on('click', '.btnViewDetail', function() {
        var btn = $(this);
        var id = btn.data('id');

        // Mengambil data detail karyawan melalui AJAX
        $.ajax({
            url: '../../payroll/keuangan/employees.php?ajax=1&case=ViewEmployeeDetail',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.code === 0) {
                    var emp = response.result;
                    $('#detailId').text(emp.id);
                    $('#detailNip').text(emp.nip);
                    $('#detailNama').text(emp.nama);
                    $('#detailGelar').text(emp.gelar);
                    $('#detailJenjang').text(emp.jenjang);
                    $('#detailJobTitle').text(emp.job_title);
                    $('#detailStatus').text(emp.status);
                    $('#detailNoRekening').text(emp.no_rekening);
                    $('#detailEmail').text(emp.email);
                    $('#detailJoinStart').text(emp.join_start);
                    $('#detailMasaKerja').text(emp.masa_kerja_year + ' Thn ' + emp.masa_kerja_month + ' Bln');
                    $('#detailJK').text(emp.jk === 'L' ? 'Laki-laki' : 'Perempuan');
                    $('#detailTglLahir').text(emp.tgl_lahir);
                    $('#detailUsia').text(emp.usia + ' Thn');
                    $('#detailAgama').text(emp.religion);
                    $('#detailPendidikan').text(emp.pendidikan);
                    $('#detailMarital').text(emp.marital);
                    $('#detailAlamatDomisili').text(emp.alamat_domisili);
                    $('#detailAlamatKTP').text(emp.alamat_ktp);
                    $('#detailNoHP').text(emp.no_hp);

                    // Menampilkan payheads
                    if (emp.payheads.length > 0) {
                        var payheads_html = '<ul>';
                        $.each(emp.payheads, function(index, payhead) {
                            payheads_html += '<li>' + payhead.nama_payhead + ' (' + payhead.jenis_payhead.charAt(0).toUpperCase() + payhead.jenis_payhead.slice(1) + '): Rp ' + parseFloat(payhead.amount).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</li>';
                        });
                        payheads_html += '</ul>';
                        $('#detailPayheads').html(payheads_html);
                    } else {
                        $('#detailPayheads').html('<p>Tidak ada payheads yang ditugaskan.</p>');
                    }

                    // Menampilkan modal View Detail
                    $('#viewDetailModal').modal('show');
                } else {
                    $.notify({
                        icon: 'fas fa-times-circle',
                        message: response.result
                    },{
                        type: 'danger',
                        delay: 3000,
                        placement: {
                            from: "top",
                            align: "right"
                        }
                    });
                }
            },
            error: function() {
                $.notify({
                    icon: 'fas fa-exclamation-triangle',
                    message: 'Terjadi kesalahan saat mengambil detail karyawan.'
                },{
                    type: 'danger',
                    delay: 3000,
                    placement: {
                        from: "top",
                        align: "right"
                    }
                });
            }
        });
    });

    // Menampilkan spinner saat form disubmit
    $('form').on('submit', function(e) {
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        $btn.prop('disabled', true);
        $btn.find('.spinner-border').removeClass('d-none');
    });

    // Menampilkan notifikasi dan reload DataTables setelah aksi berhasil
    function showNotification(type, message) {
        $.notify({
            icon: type === 'success' ? 'fas fa-check-circle' : 'fas fa-times-circle',
            message: message
        },{
            type: type,
            delay: 3000,
            placement: {
                from: "top",
                align: "right"
            }
        });
    }

    // Reset form saat modal ditutup
    $('#addEmployeeModal').on('hidden.bs.modal', function () {
        $('#addEmployeeForm')[0].reset();
    });

    $('#editEmployeeModal').on('hidden.bs.modal', function () {
        $('#editEmployeeForm')[0].reset();
    });

    $('#assignPayheadsModal').on('hidden.bs.modal', function () {
        $('#assignPayheadsForm')[0].reset();
        $('#availablePayheads').empty();
        $('#assignedPayheads').empty();
        $('#payheadAmounts').empty();
    });

    $('#viewDetailModal').on('hidden.bs.modal', function () {
        // Tidak perlu reset
    });

});
