$(document).ready(function () {
    var report = document.getElementById('exTab2');
    if (!report) {
        return;
    }

    var ajaxBase = report.getAttribute('data-ajax-base').replace(/\/$/, '');
    var currentDate = new Date();
    var currentYear = currentDate.getFullYear();
    var currentMonth = currentDate.getMonth() + 1;
    var sheetRequests = {};
    var sheetRequestIds = {};
    var periodYear = currentYear;
    var periodMonth = currentMonth;
    if (currentMonth === 12 && currentDate.getDate() >= 17) {
        periodYear++;
        periodMonth = 1;
    } else if (currentDate.getDate() > 20) {
        periodMonth++;
        if (periodMonth > 12) {
            periodYear++;
            periodMonth = 1;
        }
    } else if (currentMonth === 12) {
        periodMonth = 12;
    }

    function pad(value) {
        return value < 10 ? '0' + value : String(value);
    }

    function setYearWindow(select, centerYear) {
        var options = '';
        for (var year = centerYear - 5; year <= centerYear + 5; year++) {
            options += '<option value="' + year + '">' + year + '</option>';
        }
        select.innerHTML = options;
        select.value = String(centerYear);
    }

    function moneyColumn(width) {
        return { type: 'numeric', width: width, mask: '#.##,##', decimal: ',', money: true };
    }

    function fitColumnsToWidth(container, columns) {
        var availableWidth = container.parentElement.clientWidth;
        var columnsWidth = columns.reduce(function (total, column) {
            return total + (parseFloat(column.width) || 50);
        }, 0);
        var extraWidth = availableWidth - columnsWidth - 50;
        if (extraWidth <= 0 || columnsWidth <= 0) {
            return columns;
        }

        var distributedWidth = 0;
        columns.forEach(function (column, index) {
            var baseWidth = parseFloat(column.width) || 50;
            var addedWidth = index === columns.length - 1
                ? extraWidth - distributedWidth
                : Math.floor(extraWidth * baseWidth / columnsWidth);
            column.width = baseWidth + addedWidth;
            distributedWidth += addedWidth;
        });
        return columns;
    }

    function reportUrl(name, period, extra, download) {
        var url = ajaxBase + '/' + name + '/' + period;
        if (extra !== null && extra !== undefined) {
            url += '/' + encodeURIComponent(extra);
        }
        if (download) {
            url += '/d';
        }
        return url;
    }

    function loadSheet(elementId, url, columns, pagination) {
        var container = document.getElementById(elementId);
        container.innerHTML = '';
        sheetRequestIds[elementId] = (sheetRequestIds[elementId] || 0) + 1;
        var requestId = sheetRequestIds[elementId];
        if (sheetRequests[elementId]) {
            sheetRequests[elementId].abort();
        }
        sheetRequests[elementId] = $.ajax({
            url: url,
            dataType: 'text'
        }).done(function (csv) {
            if (requestId !== sheetRequestIds[elementId]) {
                return;
            }
            var parsed = Papa.parse(csv, { skipEmptyLines: true }).data;
            var headers = parsed.shift() || [];
            var rows = parsed.filter(function (row) {
                return row.some(function (value) {
                    return value !== '';
                });
            });
            rows.forEach(function (row) {
                columns.forEach(function (column, index) {
                    if (column.money && row[index] !== undefined && row[index] !== '') {
                        var amount = Number(row[index]);
                        if (isFinite(amount)) {
                            row[index] = amount.toFixed(2);
                        }
                    }
                });
            });
            if (!rows.length) {
                container.textContent = 'Tidak ada data untuk filter ini.';
                return;
            }

            var reportColumns = columns.map(function (column, index) {
                var configuredColumn = $.extend({}, column);
                configuredColumn.title = headers[index] || '';
                return configuredColumn;
            });
            fitColumnsToWidth(container, reportColumns);
            jexcel(container, {
                data: rows,
                search: true,
                pagination: pagination,
                columns: reportColumns,
                editable: false
            });
        }).fail(function (xhr, status) {
            if (status !== 'abort' && requestId === sheetRequestIds[elementId]) {
                container.textContent = 'Gagal memuat laporan. Muat ulang halaman dan coba lagi.';
            }
        });
    }

    function dateColumns() {
        return [
            { type: 'text', width: 100 },
            { type: 'text', width: 100 },
            { type: 'text', width: 200 },
            { type: 'text', width: 100 },
            { type: 'text', width: 100 },
            { type: 'text', width: 250 },
            moneyColumn(100),
            { type: 'text', width: 60 },
            moneyColumn(120),
            moneyColumn(120),
            moneyColumn(120),
            { type: 'text', width: 140 }
        ];
    }

    function periodValue(yearElement, monthElement) {
        return yearElement.value + '-' + monthElement.value;
    }

    function getSelectedNpk() {
        var match = document.getElementById('npk').value.match(/-([^-]+)$/);
        return match ? match[1].trim() : '';
    }

    function loadDaily() {
        var date = document.getElementById('tanggal').value;
        if (date) {
            loadSheet('harian-sheet', reportUrl('data_harian', date, null, false), dateColumns(), 10);
        }
    }

    function loadPersonal() {
        var npk = getSelectedNpk();
        var period = periodValue(
            document.getElementById('tahun_perorangan'),
            document.getElementById('bulan_perorangan')
        );
        if (!npk) {
            document.getElementById('perorangan-sheet').innerHTML =
                '<p class="help-block">Pilih karyawan dari daftar nama/NPK untuk melihat rekap.</p>';
            return;
        }
        loadSheet('perorangan-sheet', reportUrl('data_perorangan', period, npk, false), [
            { type: 'text', width: 100 },
            { type: 'text', width: 180 },
            { type: 'text', width: 100 },
            { type: 'text', width: 200 },
            moneyColumn(150),
            moneyColumn(150),
            moneyColumn(150)
        ], 30);
    }

    function loadMonthly() {
        var period = periodValue(
            document.getElementById('tahun_bulanan'),
            document.getElementById('bulan_bulanan')
        );
        loadSheet('bulanan-sheet', reportUrl('data_bulanan', period, null, false), [
            { type: 'text', width: 100 },
            { type: 'text', width: 220 },
            { type: 'text', width: 180 },
            moneyColumn(150),
            moneyColumn(150),
            moneyColumn(150)
        ], 30);
    }

    function loadAnnual() {
        var year = document.getElementById('tahun_tahunan').value;
        loadSheet('tahunan-sheet', reportUrl('data_tahunan', year, null, false), [
            { type: 'text', width: 160 },
            { type: 'numeric', width: 180 },
            { type: 'numeric', width: 160 },
            moneyColumn(170),
            moneyColumn(170),
            moneyColumn(170)
        ], 12);
    }

    function download(url) {
        window.location.href = url;
    }

    setYearWindow(document.getElementById('tahun_perorangan'), periodYear);
    setYearWindow(document.getElementById('tahun_bulanan'), periodYear);
    setYearWindow(document.getElementById('tahun_tahunan'), currentYear);
    $('#bulan_perorangan, #bulan_bulanan').val(pad(periodMonth));

    $('#npk').easyAutocomplete({
        url: ajaxBase + '/karyawan_list',
        getValue: 'nama',
        list: {
            match: { enabled: true },
            onChooseEvent: loadPersonal
        }
    });

    $('#h, #m, #b, #t').on('click', function (event) {
        event.preventDefault();
        var activeId = this.id;
        $('#exTab2 .nav-tabs li').removeClass('active');
        $(this).parent().addClass('active');
        $('#harian, #perorangan, #bulanan, #tahunan').hide();
        var target = { h: 'harian', m: 'perorangan', b: 'bulanan', t: 'tahunan' }[activeId];
        $('#' + target).show();
        if (activeId === 'm') {
            loadPersonal();
        } else if (activeId === 'b') {
            loadMonthly();
        } else if (activeId === 't') {
            loadAnnual();
        }
    });

    $('#tanggal').val(
        currentDate.getFullYear() + '-' + pad(currentMonth) + '-' + pad(currentDate.getDate())
    ).on('change', loadDaily);
    $('#tahun_perorangan').on('change', function () {
        setYearWindow(this, parseInt(this.value, 10));
        loadPersonal();
    });
    $('#bulan_perorangan').on('change', loadPersonal);
    $('#tahun_bulanan').on('change', function () {
        setYearWindow(this, parseInt(this.value, 10));
        loadMonthly();
    });
    $('#bulan_bulanan').on('change', loadMonthly);
    $('#tahun_tahunan').on('change', function () {
        setYearWindow(this, parseInt(this.value, 10));
        loadAnnual();
    });

    $('#download-csv-harian').on('click', function () {
        download(reportUrl('data_harian', $('#tanggal').val(), null, true));
    });
    $('#download-csv-perorangan').on('click', function () {
        var npk = getSelectedNpk();
        if (npk) {
            download(reportUrl('data_perorangan', periodValue($('#tahun_perorangan')[0], $('#bulan_perorangan')[0]), npk, true));
        }
    });
    $('#download-csv-bulanan').on('click', function () {
        download(reportUrl('data_bulanan', periodValue($('#tahun_bulanan')[0], $('#bulan_bulanan')[0]), null, true));
    });
    $('#download-csv-tahunan').on('click', function () {
        download(reportUrl('data_tahunan', $('#tahun_tahunan').val(), null, true));
    });

    $('#harian').show();
    $('#perorangan, #bulanan, #tahunan').hide();
    loadDaily();
});
