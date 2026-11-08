$(document).ready(function () {
    var page = document.getElementById('data-update');
    if (!page) {
        return;
    }

    var uploadUrl = page.getAttribute('data-upload-url').replace(/\/$/, '');
    var activeUploads = {};
    var maxFileSize = 100 * 1024 * 1024;
    var allowedExtensions = ['xls', 'xlsx', 'csv', 'txt'];

    function showProgress(type, percent, processing, failed) {
        var progress = $('#' + type + '-progress');
        var bar = progress.find('.progress-bar');
        var value = Math.max(0, Math.min(100, Math.round(percent)));
        progress.show();
        bar.toggleClass('progress-bar-striped active', processing);
        bar.toggleClass('progress-bar-danger', failed);
        bar.toggleClass('progress-bar-success', !failed);
        bar.css('width', value + '%')
            .attr('aria-valuenow', value)
            .find('.sr-only').text(value + '%');
    }

    function showErrors(type, errors) {
        var status = $('#' + (type === 'stok' ? 'dropBox' : 'dropBox1'));
        if (!errors || !errors.length) {
            return;
        }
        var list = $('<ul class="text-danger"></ul>');
        errors.forEach(function (error) {
            var message = error.baris ? 'Baris ' + error.baris + ': ' : '';
            list.append($('<li></li>').text(message + error.pesan));
        });
        status.append(list);
    }

    function successMessage(type, response) {
        var result = response.data || {};
        if (type === 'karyawan') {
            return response.message + ' ' + result.jumlah_data + ' baris diproses; ' +
                result.karyawan_baru + ' baru, ' + result.karyawan_diperbarui +
                ' diperbarui, dan ' + result.ditandai_resign + ' ditandai resign.';
        }
        return response.message + ' ' + result.jumlah_data + ' produk dimuat.';
    }

    function uploadFile(type, file) {
        if (activeUploads[type]) {
            return;
        }
        var extension = file.name.split('.').pop().toLowerCase();
        if ($.inArray(extension, allowedExtensions) === -1) {
            $('#' + (type === 'stok' ? 'dropBox' : 'dropBox1'))
                .text('Format harus XLS, XLSX, CSV, atau TXT.');
            return;
        }
        if (file.size < 1 || file.size > maxFileSize) {
            $('#' + (type === 'stok' ? 'dropBox' : 'dropBox1'))
                .text('Ukuran file harus lebih dari 0 dan maksimal 100 MB.');
            return;
        }
        var confirmation = type === 'karyawan'
            ? 'Karyawan aktif yang tidak tercantum di file akan ditandai resign (datanya tidak dihapus). Lanjutkan?'
            : 'File ini akan mengganti seluruh data stok yang ada. Lanjutkan?';
        if (!window.confirm(confirmation)) {
            $('#karyawan').val('');
            return;
        }

        activeUploads[type] = true;
        var input = $('#' + type).prop('disabled', true);
        var status = $('#' + (type === 'stok' ? 'dropBox' : 'dropBox1'));
        status.text('Mengunggah file...');
        showProgress(type, 0, false, false);

        var data = new FormData();
        data.append('file', file, file.name);
        var xhr = new XMLHttpRequest();
        var processingTimer = null;
        xhr.open('POST', uploadUrl + '/' + encodeURIComponent(type), true);
        xhr.timeout = 600000;
        xhr.upload.addEventListener('progress', function (event) {
            if (event.lengthComputable) {
                showProgress(type, event.loaded / event.total * 70, false, false);
            }
        });
        xhr.upload.addEventListener('load', function () {
            status.text('File diterima. Memvalidasi dan memperbarui data...');
            var processingProgress = 72;
            showProgress(type, processingProgress, true, false);
            processingTimer = window.setInterval(function () {
                processingProgress = Math.min(processingProgress + 1, 95);
                showProgress(type, processingProgress, true, false);
            }, 1200);
        });
        xhr.onload = function () {
            if (processingTimer) {
                window.clearInterval(processingTimer);
            }
            var response;
            try {
                response = JSON.parse(xhr.responseText);
            } catch (error) {
                response = null;
            }
            if (xhr.status >= 200 && xhr.status < 300 && response && response.success) {
                showProgress(type, 100, false, false);
                status.text(successMessage(type, response));
            } else {
                showProgress(type, 100, false, true);
                status.text(response && response.message
                    ? response.message
                    : 'Update gagal. Periksa koneksi atau muat ulang halaman, lalu coba lagi.');
                showErrors(type, response && response.data ? response.data.errors : null);
            }
            finish();
        };
        xhr.onerror = function () {
            fail('Koneksi terputus. Data belum dapat dipastikan diperbarui; periksa data sebelum mengunggah ulang.');
        };
        xhr.ontimeout = function () {
            fail('Proses melewati batas waktu. Periksa kondisi data sebelum mengunggah ulang.');
        };
        xhr.onabort = function () {
            fail('Proses unggah dibatalkan.');
        };
        xhr.send(data);

        function fail(message) {
            if (processingTimer) {
                window.clearInterval(processingTimer);
            }
            showProgress(type, 100, false, true);
            status.text(message);
            finish();
        }

        function finish() {
            activeUploads[type] = false;
            input.prop('disabled', false).val('');
        }
    }

    $('#stok').on('change', function () {
        if (this.files && this.files.length) {
            uploadFile('stok', this.files[0]);
        }
    });

    $('#karyawan').on('change', function () {
        if (this.files && this.files.length) {
            uploadFile('karyawan', this.files[0]);
        }
    });
});
