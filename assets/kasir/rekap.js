$(document).ready(function () {
    var report = document.getElementById('exTab2');
    if (!report) {
        return;
    }

    function sameOriginUrl(url) {
        var parsedUrl = document.createElement('a');
        parsedUrl.href = url;
        return window.location.protocol + '//' + window.location.host
            + parsedUrl.pathname + parsedUrl.search + parsedUrl.hash;
    }

    var ajaxBase = sameOriginUrl(report.getAttribute('data-ajax-base')).replace(/\/$/, '');
    var rekapBase = sameOriginUrl(report.getAttribute('data-rekap-base')).replace(/\/$/, '');
    var reprintBase = sameOriginUrl(report.getAttribute('data-reprint-base')).replace(/\/$/, '');
    var currentDate = new Date();
    var currentYear = currentDate.getFullYear();
    var currentMonth = currentDate.getMonth() + 1;
    var sheetRequests = {};
    var sheetRequestIds = {};
    var rawHarianRows = [];
    var chartInstance = null;

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
        if (!select) return;
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

    function formatRupiah(amount) {
        return 'Rp ' + Number(amount || 0).toLocaleString('id-ID', {
            maximumFractionDigits: 0
        });
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

    // Modal Detail Nota Pop-up
    window.showNotaDetail = function (nota) {
        if (!nota) return;
        var modal = $('#modal-detail-nota');
        var body = $('#modal-detail-nota-body');
        var printBtn = $('#btn-print-receipt');

        body.html('<div class="text-center" style="padding: 30px;"><i class="fa fa-spinner fa-spin fa-2x text-primary"></i><p style="margin-top: 10px;">Mengambil data nota ' + nota + '...</p></div>');
        printBtn.attr('href', reprintBase + '?nota=' + encodeURIComponent(nota));
        modal.modal('show');

        $.ajax({
            url: rekapBase + '/detail_nota?nota=' + encodeURIComponent(nota),
            dataType: 'json'
        }).done(function (res) {
            if (res.success && res.data) {
                var r = res.data.receipt;
                var items = res.data.items || [];
                var methodLabel = (r.payment_method === 'tunai' || r.payment_method === 'cash') ? '<span class="label label-success">Tunai</span>' : '<span class="label label-primary">Potong Gaji</span>';

                var html = '<div class="row">' +
                    '<div class="col-xs-6">' +
                        '<strong>No. Nota:</strong> <code style="font-size: 14px;">' + r.nota + '</code><br>' +
                        '<strong>Tanggal:</strong> ' + r.transaction_date + '<br>' +
                        '<strong>Kasir:</strong> ' + (r.cashier_name || '-') +
                    '</div>' +
                    '<div class="col-xs-6 text-right">' +
                        '<strong>Anggota:</strong> ' + (r.employee_name || '-') + ' (' + r.npk + ')<br>' +
                        '<strong>Bagian:</strong> ' + (r.bagian || '-') + '<br>' +
                        '<strong>Metode:</strong> ' + methodLabel +
                    '</div>' +
                '</div><hr style="margin: 10px 0;">';

                html += '<div class="table-responsive"><table class="table table-striped table-condensed" style="font-size: 13px;">' +
                    '<thead><tr class="bg-gray">' +
                        '<th>Item Barang</th>' +
                        '<th class="text-right">Harga</th>' +
                        '<th class="text-center">Qty</th>' +
                        '<th class="text-right">Subtotal</th>' +
                    '</tr></thead><tbody>';

                var totalQty = 0;
                items.forEach(function (it) {
                    totalQty += parseInt(it.qty, 10) || 0;
                    html += '<tr>' +
                        '<td>' + it.product_name + '<br><small class="text-muted">' + it.barcode + '</small></td>' +
                        '<td class="text-right">' + formatRupiah(it.harga) + '</td>' +
                        '<td class="text-center">' + it.qty + '</td>' +
                        '<td class="text-right" style="font-weight: bold;">' + formatRupiah(it.jumlah) + '</td>' +
                    '</tr>';
                });

                html += '</tbody><tfoot>' +
                    '<tr class="bg-gray-light" style="font-weight: bold;">' +
                        '<td>TOTAL</td>' +
                        '<td></td>' +
                        '<td class="text-center">' + totalQty + '</td>' +
                        '<td class="text-right" style="color: #00a65a; font-size: 15px;">' + formatRupiah(r.total_paid) + '</td>' +
                    '</tr>' +
                '</tfoot></table></div>';

                if (parseFloat(r.amount_paid) > 0) {
                    html += '<div class="text-right small text-muted">' +
                        'Dibayar: ' + formatRupiah(r.amount_paid) + ' | Kembalian: ' + formatRupiah(r.change_amount) +
                    '</div>';
                }

                body.html(html);
            } else {
                body.html('<div class="alert alert-danger"><i class="fa fa-times"></i> ' + (res.message || 'Gagal memuat nota') + '</div>');
            }
        }).fail(function () {
            body.html('<div class="alert alert-danger"><i class="fa fa-times"></i> Gagal menghubungi server.</div>');
        });
    };

    function loadSheet(elementId, url, columns, pagination, onLoadedCallback) {
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

            if (typeof onLoadedCallback === 'function') {
                rows = onLoadedCallback(rows, headers) || rows;
            }

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
            var spreadsheet = jexcel(container, {
                data: rows,
                search: true,
                pagination: pagination,
                columns: reportColumns,
                editable: false,
                updateTable: function (instance, cell, col, row, val, label, cellName) {
                    // Cari kolom nota (biasanya kolom 0 atau kolom 1)
                    var colTitle = (reportColumns[col] && reportColumns[col].title) ? reportColumns[col].title.toLowerCase() : '';
                    if ((colTitle === 'nota' || col === 0 || (col === 1 && elementId === 'perorangan-sheet')) && val && String(val).indexOf('TOTAL') === -1) {
                        cell.classList.add('nota-clickable');
                        cell.setAttribute('title', 'Klik untuk melihat detail struk ' + val);
                    }
                }
            });

            // Event delegasi klik langsung pada cell nota
            $(container).off('click', 'td.nota-clickable').on('click', 'td.nota-clickable', function (e) {
                e.preventDefault();
                var textVal = $(this).text().trim();
                if (textVal && textVal.indexOf('TOTAL') === -1) {
                    showNotaDetail(textVal);
                }
            });
        }).fail(function (xhr, status) {
            if (status !== 'abort' && requestId === sheetRequestIds[elementId]) {
                container.textContent = 'Gagal memuat laporan. Muat ulang halaman dan coba lagi.';
            }
        });
    }

    function dateColumns() {
        return [
            { type: 'text', width: 150 }, // nota
            { type: 'text', width: 90 },  // npk
            { type: 'text', width: 180 }, // nama
            { type: 'text', width: 90 },  // bagian
            { type: 'text', width: 100 }, // barcode
            { type: 'text', width: 220 }, // item
            moneyColumn(90),              // harga
            { type: 'numeric', width: 60 }, // qty
            moneyColumn(110),             // jumlah
            moneyColumn(100),             // retur_disetujui
            moneyColumn(110),             // tertagih
            { type: 'text', width: 100 }, // metode
            { type: 'text', width: 110 }  // status_validasi
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
        if (!date) return;

        loadSheet('harian-sheet', reportUrl('data_harian', date, null, false), dateColumns(), 20, function (rows, headers) {
            rawHarianRows = rows;
            var filterMetode = $('#filter-metode-harian').val();

            var totalNet = 0;
            var totalQty = 0;
            var totalTunai = 0;
            var totalKredit = 0;
            var countTunai = 0;
            var countKredit = 0;
            var distinctNotas = {};

            var filteredRows = [];
            rows.forEach(function (r) {
                var metode = (r[11] || '').toLowerCase();
                var jumlah = parseFloat(r[8]) || 0;
                var retur = parseFloat(r[9]) || 0;
                var tertagih = parseFloat(r[10]) || 0;
                var qty = parseInt(r[7], 10) || 0;
                var nota = r[0];

                distinctNotas[nota] = true;
                totalNet += tertagih;
                totalQty += qty;

                if (metode === 'tunai' || metode === 'cash') {
                    totalTunai += tertagih;
                    countTunai++;
                } else {
                    totalKredit += tertagih;
                    countKredit++;
                }

                if (filterMetode === 'semua' || (filterMetode === 'tunai' && (metode === 'tunai' || metode === 'cash')) || (filterMetode === 'potong_gaji' && metode !== 'tunai' && metode !== 'cash')) {
                    filteredRows.push(r);
                }
            });

            // Update KPI Cards
            $('#kpi-harian-omset').text(formatRupiah(totalNet));
            $('#kpi-harian-qty').text(totalQty.toLocaleString('id-ID') + ' Qty terjual');
            $('#kpi-harian-nota').text(Object.keys(distinctNotas).length + ' Nota');
            $('#kpi-harian-tunai').text(formatRupiah(totalTunai));
            $('#kpi-harian-tunai-count').text(countTunai + ' baris tunai');
            $('#kpi-harian-kredit').text(formatRupiah(totalKredit));
            $('#kpi-harian-kredit-count').text(countKredit + ' baris kredit');

            // Tambahkan baris TOTAL di bawah
            if (filteredRows.length > 0) {
                var sumQty = 0, sumJumlah = 0, sumRetur = 0, sumTertagih = 0;
                filteredRows.forEach(function (fr) {
                    sumQty += parseInt(fr[7], 10) || 0;
                    sumJumlah += parseFloat(fr[8]) || 0;
                    sumRetur += parseFloat(fr[9]) || 0;
                    sumTertagih += parseFloat(fr[10]) || 0;
                });
                var totalRow = ['TOTAL SUMMARY', '', '', '', '', '', '', sumQty, sumJumlah.toFixed(2), sumRetur.toFixed(2), sumTertagih.toFixed(2), '', ''];
                filteredRows.push(totalRow);
            }

            return filteredRows;
        });
    }

    var todayCashTotal = 0;
    var todayCashCount = 0;

    function parseMoneyInput(val) {
        if (!val) return 0;
        return parseFloat(String(val).replace(/\./g, '').replace(/,/g, '.')) || 0;
    }

    function formatRupiahInputVal(value) {
        var str = String(value || '').replace(/[^,\d]/g, ''),
            split = str.split(','),
            sisa = split[0].length % 3,
            rupiah = split[0].substr(0, sisa),
            ribuan = split[0].substr(sisa).match(/\d{3}/gi);
        if (ribuan) {
            var separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }
        return split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
    }

    function updateModalCashCount() {
        var floatVal = parseMoneyInput($('#modal-cash-float').val());
        var targetAmount = floatVal + todayCashTotal;

        $('#modal-cash-today-sales').text(formatRupiah(todayCashTotal));
        $('#modal-cash-today-count').text(todayCashCount + ' transaksi tunai');
        $('#modal-display-float').text(formatRupiah(floatVal));
        $('#modal-display-sales').text(formatRupiah(todayCashTotal));
        $('#modal-target-display').text(formatRupiah(targetAmount)).attr('data-sales', todayCashTotal);

        var totalFisik = 0;
        $('.modal-count-input').each(function () {
            var count = parseInt($(this).val(), 10) || 0;
            if (count < 0) count = 0;
            var denom = parseInt($(this).attr('data-denomination'), 10) || 0;
            var sub = count * denom;
            totalFisik += sub;
            $(this).closest('tr').find('.modal-denom-subtotal').text(formatRupiah(sub));
        });

        $('#modal-count-total').text(formatRupiah(totalFisik));
        var variance = totalFisik - targetAmount;
        var varEl = $('#modal-count-variance');
        var statusEl = $('#modal-count-status');

        if (Math.abs(variance) < 1) {
            varEl.attr('class', 'text-green').text('Rp 0 (PAS)');
            statusEl.html('<span class="text-green"><i class="fa fa-check-circle"></i> Uang fisik laci cocok dengan target sistem.</span>');
        } else if (variance < 0) {
            varEl.attr('class', 'text-red').text('-Rp ' + Math.abs(Math.round(variance)).toLocaleString('id-ID'));
            statusEl.html('<span class="text-red"><i class="fa fa-exclamation-triangle"></i> Uang fisik KURANG Rp ' + Math.abs(Math.round(variance)).toLocaleString('id-ID') + ' dari target.</span>');
        } else {
            varEl.attr('class', 'text-yellow').text('+Rp ' + Math.round(variance).toLocaleString('id-ID'));
            statusEl.html('<span class="text-yellow"><i class="fa fa-info-circle"></i> Uang fisik LEBIH Rp ' + Math.round(variance).toLocaleString('id-ID') + ' dari target.</span>');
        }
    }

    function loadCashSummary() {
        $('#tunai-summary-error').text('');
        $.ajax({
            url: ajaxBase + '/ringkasan_tunai',
            dataType: 'json'
        }).done(function (summary) {
            todayCashTotal = parseFloat(summary.hari_ini.total_tunai) || 0;
            todayCashCount = parseInt(summary.hari_ini.jumlah_transaksi, 10) || 0;
            $('#tunai-hari-total').text(formatRupiah(todayCashTotal));
            $('#tunai-hari-count').text(todayCashCount + ' transaksi');
            $('#tunai-bulan-total').text(formatRupiah(summary.bulan_ini.total_tunai));
            $('#tunai-bulan-count').text(summary.bulan_ini.jumlah_transaksi + ' transaksi');
            updateModalCashCount();
        }).fail(function () {
            $('#tunai-summary-error').text('Gagal memuat ringkasan penjualan tunai. Muat ulang halaman dan coba lagi.');
        });
    }

    function loadCashSales() {
        var date = document.getElementById('tanggal-tunai').value;
        if (date) {
            loadSheet('tunai-sheet', reportUrl('data_tunai', date, null, false), [
                { type: 'text', width: 140 },
                { type: 'text', width: 160 },
                { type: 'text', width: 90 },
                { type: 'text', width: 180 },
                { type: 'text', width: 140 },
                { type: 'text', width: 120 },
                moneyColumn(150)
            ], 20, function (rows) {
                if (rows.length > 0) {
                    var sum = 0;
                    rows.forEach(function (r) { sum += parseFloat(r[6]) || 0; });
                    rows.push(['TOTAL', '', '', '', '', '', sum.toFixed(2)]);
                }
                return rows;
            });
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
                '<p class="help-block text-muted" style="padding: 20px;"><i class="fa fa-info-circle"></i> Pilih karyawan dari saran NPK/Nama untuk melihat rekapitulasi.</p>';
            return;
        }
        loadSheet('perorangan-sheet', reportUrl('data_perorangan', period, npk, false), [
            { type: 'text', width: 100 },
            { type: 'text', width: 160 },
            { type: 'text', width: 90 },
            { type: 'text', width: 180 },
            moneyColumn(140),
            moneyColumn(120),
            moneyColumn(140),
            { type: 'text', width: 120 }
        ], 30, function (rows) {
            if (rows.length > 0) {
                var sumTot = 0, sumRet = 0, sumTag = 0;
                rows.forEach(function (r) {
                    sumTot += parseFloat(r[4]) || 0;
                    sumRet += parseFloat(r[5]) || 0;
                    sumTag += parseFloat(r[6]) || 0;
                });
                rows.push(['TOTAL', '', '', '', sumTot.toFixed(2), sumRet.toFixed(2), sumTag.toFixed(2), '']);
            }
            return rows;
        });
    }

    function loadMonthly() {
        var period = periodValue(
            document.getElementById('tahun_bulanan'),
            document.getElementById('bulan_bulanan')
        );
        loadSheet('bulanan-sheet', reportUrl('data_bulanan', period, null, false), [
            { type: 'text', width: 100 },
            { type: 'text', width: 220 },
            { type: 'text', width: 160 },
            moneyColumn(150),
            moneyColumn(130),
            moneyColumn(150)
        ], 30, function (rows) {
            if (rows.length > 0) {
                var sumTot = 0, sumRet = 0, sumTag = 0;
                rows.forEach(function (r) {
                    sumTot += parseFloat(r[3]) || 0;
                    sumRet += parseFloat(r[4]) || 0;
                    sumTag += parseFloat(r[5]) || 0;
                });
                rows.push(['TOTAL', '', '', sumTot.toFixed(2), sumRet.toFixed(2), sumTag.toFixed(2)]);
            }
            return rows;
        });
    }

    function loadAnnual() {
        var year = document.getElementById('tahun_tahunan').value;
        loadSheet('tahunan-sheet', reportUrl('data_tahunan', year, null, false), [
            { type: 'text', width: 140 },
            { type: 'numeric', width: 140 },
            { type: 'numeric', width: 130 },
            moneyColumn(160),
            moneyColumn(140),
            moneyColumn(160)
        ], 12, function (rows) {
            if (rows.length > 0) {
                var sumTx = 0, sumQty = 0, sumTot = 0, sumRet = 0, sumTag = 0;
                rows.forEach(function (r) {
                    sumTx += parseInt(r[1], 10) || 0;
                    sumQty += parseInt(r[2], 10) || 0;
                    sumTot += parseFloat(r[3]) || 0;
                    sumRet += parseFloat(r[4]) || 0;
                    sumTag += parseFloat(r[5]) || 0;
                });
                rows.push(['TOTAL', sumTx, sumQty, sumTot.toFixed(2), sumRet.toFixed(2), sumTag.toFixed(2)]);
            }
            return rows;
        });
    }

    function loadAnalytics() {
        var start = $('#chart_start_date').val();
        var end = $('#chart_end_date').val();
        var tbody = $('#top-products-tbody');
        tbody.html('<tr><td colspan="4" class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Memuat data analitik...</td></tr>');

        $.ajax({
            url: rekapBase + '/top_produk?start_date=' + encodeURIComponent(start) + '&end_date=' + encodeURIComponent(end),
            dataType: 'json'
        }).done(function (res) {
            if (!res.success || !res.data) {
                tbody.html('<tr><td colspan="4" class="text-center text-danger">Gagal memuat data.</td></tr>');
                return;
            }

            // 1. Render Top Products Table
            var tp = res.data.top_products || [];
            if (tp.length === 0) {
                tbody.html('<tr><td colspan="4" class="text-center text-muted">Tidak ada penjualan pada rentang tanggal ini.</td></tr>');
            } else {
                var html = '';
                tp.forEach(function (item, idx) {
                    var rankBadge = (idx === 0) ? '<span class="badge bg-yellow">1</span>' :
                                    (idx === 1) ? '<span class="badge bg-gray">2</span>' :
                                    (idx === 2) ? '<span class="badge bg-orange">3</span>' : (idx + 1);
                    html += '<tr>' +
                        '<td style="text-align: center;">' + rankBadge + '</td>' +
                        '<td><strong>' + item.item + '</strong><br><small class="text-muted">' + item.barcode + '</small></td>' +
                        '<td class="text-right" style="font-weight: bold;">' + parseInt(item.total_qty, 10).toLocaleString('id-ID') + '</td>' +
                        '<td class="text-right" style="color: #00a65a;">' + formatRupiah(item.total_omset) + '</td>' +
                    '</tr>';
                });
                tbody.html(html);
            }

            // 2. Render Trend Chart
            var trends = res.data.daily_trends || [];
            var labels = [];
            var dataOmset = [];
            trends.forEach(function (t) {
                labels.push(t.tgl);
                dataOmset.push(parseFloat(t.total_omset) || 0);
            });

            var ctx = document.getElementById('salesTrendChart').getContext('2d');
            if (chartInstance) {
                chartInstance.destroy();
            }

            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Omset Bersih (Rp)',
                        fillColor: 'rgba(60,141,188,0.2)',
                        strokeColor: 'rgba(60,141,188,1)',
                        pointColor: '#3b8bba',
                        pointStrokeColor: 'rgba(60,141,188,1)',
                        pointHighlightFill: '#fff',
                        pointHighlightStroke: 'rgba(60,141,188,1)',
                        data: dataOmset
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scaleShowGridLines: true,
                    scaleGridLineColor: 'rgba(0,0,0,.05)',
                    bezierCurve: true,
                    bezierCurveTension: 0.3
                }
            });
        }).fail(function () {
            tbody.html('<tr><td colspan="4" class="text-center text-danger">Gagal menghubungi server analitik.</td></tr>');
        });
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

    $('#exTab2 .nav-tabs li a').on('click', function (event) {
        event.preventDefault();
        var href = $(this).attr('href');
        $('#exTab2 .nav-tabs li').removeClass('active');
        $(this).parent().addClass('active');
        $('#harian, #tunai, #perorangan, #bulanan, #tahunan, #analitik').hide();
        $(href).show();

        if (href === '#harian') {
            loadDaily();
        } else if (href === '#tunai') {
            loadCashSummary();
            loadCashSales();
        } else if (href === '#perorangan') {
            loadPersonal();
        } else if (href === '#bulanan') {
            loadMonthly();
        } else if (href === '#tahunan') {
            loadAnnual();
        } else if (href === '#analitik') {
            loadAnalytics();
        }
    });

    $('#tanggal').val(
        currentDate.getFullYear() + '-' + pad(currentMonth) + '-' + pad(currentDate.getDate())
    ).on('change', loadDaily);

    $('#filter-metode-harian').on('change', loadDaily);

    $('#tanggal-tunai').val(
        currentDate.getFullYear() + '-' + pad(currentMonth) + '-' + pad(currentDate.getDate())
    ).on('change', loadCashSales);

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

    $('#btn-filter-chart').on('click', loadAnalytics);

    $('#download-csv-harian').on('click', function () {
        download(reportUrl('data_harian', $('#tanggal').val(), null, true));
    });
    $('#download-excel-harian').on('click', function () {
        download(rekapBase + '/export_excel?type=harian&date=' + encodeURIComponent($('#tanggal').val()));
    });
    $('#download-csv-tunai').on('click', function () {
        download(reportUrl('data_tunai', $('#tanggal-tunai').val(), null, true));
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

    // Modal Hitung Kas Laci POS
    $('#btn-open-cash-count').on('click', function () {
        $('#modal-cash-count').modal('show');
        updateModalCashCount();
    });

    $('#modal-cash-float').on('keyup input', function () {
        this.value = formatRupiahInputVal(this.value);
        updateModalCashCount();
    });

    $('.modal-count-input').on('input change', function () {
        updateModalCashCount();
    });

    $('#btn-modal-reset-count').on('click', function () {
        $('.modal-count-input').val('0');
        $('#modal-cash-float').val('0');
        updateModalCashCount();
    });

    $('#btn-modal-print-slip').on('click', function () {
        var now = new Date();
        var timeStr = now.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' +
            now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        var floatVal = parseMoneyInput($('#modal-cash-float').val());
        var targetAmount = floatVal + todayCashTotal;
        var totalFisik = 0;
        var rowsHtml = '';

        $('.modal-count-input').each(function () {
            var count = parseInt($(this).val(), 10) || 0;
            var denom = parseInt($(this).attr('data-denomination'), 10) || 0;
            var sub = count * denom;
            totalFisik += sub;
            if (count > 0) {
                rowsHtml += '<tr>' +
                    '<td style="padding: 2px 0;">Rp ' + denom.toLocaleString('id-ID') + ' x ' + count + '</td>' +
                    '<td style="text-align: right; padding: 2px 0;">' + formatRupiah(sub) + '</td>' +
                '</tr>';
            }
        });

        var variance = totalFisik - targetAmount;
        var varianceLabel = Math.abs(variance) < 1 ? 'PAS / SESUAI' : (variance < 0 ? 'KURANG ' + formatRupiah(Math.abs(variance)) : 'LEBIH ' + formatRupiah(variance));

        var printHtml = '<!DOCTYPE html><html><head><title>Slip Opname Kasir</title>' +
            '<style>' +
            'body { font-family: "Courier New", Courier, monospace; font-size: 13px; margin: 0; padding: 15px; width: 300px; color: #000; }' +
            'h3, h4, p { margin: 0 0 4px 0; text-align: center; }' +
            '.line { border-top: 1px dashed #000; margin: 8px 0; }' +
            'table { width: 100%; font-size: 13px; border-collapse: collapse; }' +
            'th, td { vertical-align: top; }' +
            '.text-right { text-align: right; }' +
            '.ttd { margin-top: 25px; text-align: center; }' +
            '.ttd-col { width: 50%; float: left; }' +
            '@media print { body { padding: 0; width: 100%; } }' +
            '</style></head><body>' +
            '<h3>KOPERASI MAKMUR INDAH</h3>' +
            '<h4>SLIP OPNAME FISIK KASIR POS</h4>' +
            '<p style="font-size: 11px;">' + timeStr + '</p>' +
            '<div class="line"></div>' +
            '<table>' +
            '<tr><td>Modal Awal (Float)</td><td class="text-right">' + formatRupiah(floatVal) + '</td></tr>' +
            '<tr><td>Penjualan Tunai</td><td class="text-right">' + formatRupiah(todayCashTotal) + '</td></tr>' +
            '<tr style="font-weight: bold; border-top: 1px solid #000;">' +
            '<td>Target Uang Laci</td><td class="text-right">' + formatRupiah(targetAmount) + '</td>' +
            '</tr></table>' +
            '<div class="line"></div>' +
            '<p style="text-align: left; font-weight: bold; margin-bottom: 4px;">Rincian Fisik Terhitung:</p>' +
            '<table>' + (rowsHtml || '<tr><td colspan="2" style="font-style: italic;">Tidak ada lembar kas diinput.</td></tr>') +
            '<tr style="font-weight: bold; border-top: 1px solid #000;">' +
            '<td>TOTAL FISIK</td><td class="text-right">' + formatRupiah(totalFisik) + '</td>' +
            '</tr></table>' +
            '<div class="line"></div>' +
            '<table><tr style="font-weight: bold; font-size: 14px;">' +
            '<td>SELISIH:</td><td class="text-right">' + varianceLabel + '</td>' +
            '</tr></table>' +
            '<div class="line"></div>' +
            '<div class="ttd">' +
            '<div class="ttd-col">Kasir Bertugas,<br><br><br><br>( ____________ )</div>' +
            '<div class="ttd-col">Supervisor,<br><br><br><br>( ____________ )</div>' +
            '<div style="clear: both;"></div>' +
            '</div>' +
            '<script>window.onload = function() { window.print(); };<\/script>' +
            '</body></html>';

        var printWin = window.open('', '_blank', 'width=350,height=550,top=50,left=50');
        if (printWin) {
            printWin.document.open();
            printWin.document.write(printHtml);
            printWin.document.close();
        } else {
            alert('Harap izinkan pop-up browser untuk mencetak slip opname kasir.');
        }
    });

    $('#harian').show();
    $('#tunai, #perorangan, #bulanan, #tahunan, #analitik').hide();
    loadDaily();
});
