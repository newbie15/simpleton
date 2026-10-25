var dx = [
    // ['kode', 'nama barang', 1000, 1, '=C1*D1'],
];

$(document).ready(function(){
    
    var limit;
    var totl;
    var show;
    var pajak_active = window.localStorage.getItem("simpleton_pajak_active") === "true";
    $("#pajak-active").prop("checked", pajak_active).on("change", function () {
        if (dx.length > 0) {
            alert("Kosongkan keranjang atau mulai transaksi baru sebelum mengubah status pajak.");
            $(this).prop("checked", pajak_active);
            return;
        }
        pajak_active = this.checked;
        window.localStorage.setItem("simpleton_pajak_active", pajak_active ? "true" : "false");
        window.location.reload();
    });

    function check_limit_belanja(n){
        $("#limit").load("http://localhost/simpleton/index.php/main/ajax/limit_belanja/" + n, function(response, status, xhr) {
            if (status !== "success") {
                console.log("Failed to load limit_belanja");
                return;
            }
            var rawLimit = parseInt(response);
            if (!isNaN(rawLimit)) {
                $("#limit").html(rawLimit.toLocaleString('id-ID')).data('raw', rawLimit);
            }
            var lb = parseInt($('#limit_belanja').data('raw') !== undefined ? $('#limit_belanja').data('raw') : $('#limit_belanja').html());
            var l = parseInt($('#limit').data('raw') !== undefined ? $('#limit').data('raw') : $('#limit').html());

            if (!isNaN(l) && !isNaN(lb) && l > lb){
                alert("belanja anda telah melebihi limit bulanan\n silahkan berbelanja bulan depan");
                location.reload();
            }
        });
    }

    function check_jadwal_belanja(n) {
        $.ajax({
            method: "GET",
            url: "http://localhost/simpleton/index.php/main/ajax/jadwal_belanja/" + n,
        }).done(function (msg) {
            // alert("Data Saved: " + msg);
            setTimeout(function () {
                if (msg != "ok") {
                    alert("jadwal belanja anda adalah mulai tgl 1 sampai 20, selain jadwal silahkan berbelanja secara cash");
                    location.reload();
                }
            }, 2000);
        });
    }


    function formatRupiah(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function parseRupiahInput(value) {
        var digits = String(value || "").replace(/\D/g, "");
        return digits ? parseInt(digits, 10) : 0;
    }

    function formatRupiahInput(input) {
        var caret = input.selectionStart;
        var digitsBeforeCaret = input.value.slice(0, caret).replace(/\D/g, "").length;
        var digits = input.value.replace(/\D/g, "");
        var formatted = digits.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        input.value = formatted;

        var nextCaret = 0;
        var countedDigits = 0;
        while (nextCaret < formatted.length && countedDigits < digitsBeforeCaret) {
            if (/\d/.test(formatted.charAt(nextCaret))) {
                countedDigits++;
            }
            nextCaret++;
        }
        if (document.activeElement === input) {
            input.setSelectionRange(nextCaret, nextCaret);
        }
    }

    function updatettl(){
        var total = 0;
        if (pajak_active) {
            var pajek = 0;
            var pjk;
            dx.forEach(element => {
                console.log(element);
                pjk = ((parseFloat(element[2]) * parseFloat(element[3]) * parseFloat(element[5])));
                total += (parseFloat(element[2]) * parseFloat(element[3])) + pjk;
                pajek += pjk;
            });
        }else{
            dx.forEach(element => {
                total += element[2] * element[3];
            });
        }
        $("#ttx").html("Total Rp " + formatRupiah(Math.round(total)));
        // $("#ttx").html("Total Rp " + pajek.toLocaleString());
        totl = total;
    }

    function checklimit(){
        var lbEl = $('#limit_belanja');
        var lEl = $('#limit');
        var lb = parseInt(lbEl.data('raw') !== undefined ? lbEl.data('raw') : lbEl.html());
        var l = parseInt(lEl.data('raw') !== undefined ? lEl.data('raw') : lEl.html());

        lb = lb || 0;
        l = l || 0;

        var b = (l+totl);

        console.log("lb :",lb);
        console.log("l :",l);
        console.log("totl :",totl);
        console.log("b :",b);

        if(b>lb){
            if(show){
                alert("total belanja anda akan melebihi limit bulanan\nmohon kurangi jumlah belanja anda kali ini");
                $("#ttx").css("color","red");
            }
            show = false;
        }else{
            show = true;
            $("#ttx").css("color","black");
        }

    }

    function updatescroll(){
        var el = document.getElementById("scrll");
        el.scrollTop = el.scrollHeight;
    }

    var options1 = {
        url: "http://localhost/simpleton/index.php/main/ajax/karyawan_list",
        getValue: "nama",
        list: {
            match: {
                enabled: true
            }
        }
    };
    var options2 = {
        url: "http://localhost/simpleton/index.php/main/ajax/produk_list",
        getValue: "produk",
        requestDelay: 500,
        list: {
            match: {
                enabled: true
            }
        }
    };

    $("#npk").easyAutocomplete(options1);
    $("#barcode").easyAutocomplete(options2);
    var no = 1;
    var total = 0;
    var checkoutInProgress = false;
    var pendingCheckout = null;

    // Metode pembayaran dipilih di halaman kasir; modal hanya menampilkan & menyiapkan input.
    function currentPaymentMethod() {
        return $.trim($("#metode_pembayaran").val());
    }

    function isCashMethod(method) {
        return method === "tunai" || method === "cash";
    }

    function paymentMethodLabel(method) {
        return {
            potong_gaji: "Potong Gaji",
            tunai: "Tunai / Cash",
            cash: "Tunai / Cash",
            qris: "QRIS",
            edc: "EDC (Debit/Kredit)",
            transfer: "Transfer Bank"
        }[method] || "-";
    }

    // Nilai pembayaran voucher gift card dari server (0 jika belum/ tidak valid) — gift card sebagai alat bayar, PPN tetap.
    var activeVoucher = null;

    function updatePaymentFields() {
        var method = currentPaymentMethod();
        var cash = isCashMethod(method);
        var nonCash = (method === "edc" || method === "transfer" || method === "qris");
        var hasRightColumn = cash || nonCash;

        $("#checkout-payment-left")
            .toggleClass("col-sm-6", hasRightColumn)
            .toggleClass("col-sm-12", !hasRightColumn);
        $("#checkout-payment-right").toggle(hasRightColumn);

        $("#cash-payment-fields").toggle(cash);
        $("#noncash-payment-fields").toggle(nonCash);
        $("#bank-tujuan-wrap").toggle(method === "transfer");

        // Label kode konfirmasi menyesuaikan metode.
        if (method === "edc") {
            $("#kode-konfirmasi-label").text("Nomor referensi / approval EDC");
        } else if (method === "qris") {
            $("#kode-konfirmasi-label").text("Kode konfirmasi pembayaran QRIS");
        } else if (method === "transfer") {
            $("#kode-konfirmasi-label").text("Kode konfirmasi / nomor transfer");
        }

        $("#checkout-metode-display").text(paymentMethodLabel(method)).show();
        updatePaymentDisplay();
    }

    // Modal tidak menyediakan pilihan metode; perubahan tetap ikut select di halaman.
    $("#metode_pembayaran").on("change", updatePaymentFields);

    $("#nkasir").load("http://localhost/simpleton/index.php/main/ajax/daftar_kasir", function (response, status) {
        if (status !== "success") {
            $(this).html('<option value="0">Gagal memuat daftar kasir</option>');
            return;
        }
        var cashierOptions = $(this).find("option").filter(function () {
            return this.value !== "0" && this.value !== "belum";
        });
        if (cashierOptions.length === 1) {
            $(this).val(cashierOptions.first().val());
        }
    });

    function add(kode,kuantiti){
        var sama = 0;
        var index = 0;
        var len, ln, last, pajak;
        dx.forEach(element => {
            // console.log(element[0]);
            if (kode == element[0]){
                console.log("sama");
                sama = 1;
                console.log(dx[index]);
                console.log(dx[index][3]);
                dx[index][3] = parseInt(dx[index][3]) + parseInt(kuantiti);
                console.log(dx[index][3]);
                $('#my').jexcel('setData', dx, true);
                updatettl();
            }
            index++;
        });
        if(sama==0){
            $.ajax({
                url: 'http://localhost/simpleton/index.php/main/ajax/barcode/' + kode,
                // dataType: 'json',
                success: function (resp) {
                    // $( '#target').html( resp.people[0].name );
                    if (resp != "") {
                        var data = resp.split(":");

                        // Validate barcode response format
                        if (data.length < 2) {
                            console.log("Invalid barcode response format: insufficient fields");
                            alert("Data barcode tidak valid. Hubungi admin.");
                            return;
                        }

                        var nama = data[0] || "";
                        var hargaStr = (data[1] || "").trim();
                        var ppnStr = (data[2] || "").trim();

                        // Validate numeric values
                        var harga = parseFloat(hargaStr);
                        if (isNaN(harga) || harga <= 0) {
                            console.log("Invalid harga: " + hargaStr);
                            alert("Harga barang tidak valid. Hubungi admin.");
                            return;
                        }

                        if (pajak_active) {
                            var ppn = parseFloat(ppnStr);
                            if (isNaN(ppn) || ppn < 0) {
                                console.log("Invalid ppn: " + ppnStr);
                                alert("PPN barang tidak valid. Hubungi admin.");
                                return;
                            }
                        }

                        var exist = $("#cart-list").html();
                        var jumlah = harga * kuantiti;

                        console.log(dx);
                        len = dx.length;
                        // console.log(len);

                        if (len > 0) {
                            if (dx[len - 1][0] == "") {
                                dx.pop();
                            }
                        }
                        len = dx.length;
                        ln = len + 1;
                        last = '=C' + ln + '*D' + ln;
                        if(pajak_active){
                            pajak = '=E' +ln + '*F' + ln;
                            dx.push([kode, nama, harga, kuantiti, last, ppn, pajak]);
                        }else{
                            dx.push([kode, nama, harga, kuantiti, last]);
                        }
                        console.log(dx);
                        $('#my').jexcel('setData', dx, true);
                        updatettl();
                        checklimit();
                    } else {
                        console.log("tidak ketemu");
                    }


                },
                error: function (req, status, err) {
                    console.log('something went wrong', status, err);
                }
            });
        }else{

        }
        updatescroll();
    }

    function tampilkan_foto(npk){
        var image = document.querySelector('#foto_karyawan');
        var placeholder = document.querySelector('#foto-karyawan-placeholder');
        image.style.display = "none";
        placeholder.style.display = "flex";
        image.onload = function () {
            image.style.display = "block";
            placeholder.style.display = "none";
        };
        image.onerror = function () {
            image.style.display = "none";
            placeholder.style.display = "flex";
        };
        image.setAttribute('src', "http://localhost/simpleton/assets/uploads/karyawan/" + encodeURIComponent(npk) + ".png?v=" + new Date().getTime());
    }

    $("#npk").on("change blur", function(){
        sttime = null;
    });

    $("#npk").keypress(function(e){
        if(e.which==13 && $(this).val()!=""){
            var scanStartTime = performance.now();
            var t = $(this).val();
            console.log(t);
            var x = t.split("-");
            var npk = x.length > 1 ? x[x.length - 1] : $.trim(t);
            $(this).val(npk);
            $("#foto_karyawan").hide().attr("src", "");
            $("#foto-karyawan-placeholder").text("Memuat foto...").show();

            $.ajax({
                url: 'http://localhost/simpleton/index.php/main/ajax/karyawan/' + encodeURIComponent(npk),
                // dataType: 'json',
                success: function( resp ) {
                    var scanEndTime = performance.now();
                    var elapsedTime = scanEndTime - scanStartTime;

                    if(resp!=""){
                        var data = resp.split(":");
                        var nama = data[0];
                        var alamat = data[1];
                        var limit_belanja = data[2];
                        limit = limit_belanja;
                        $("#nkaryawan").html(nama);
                        $("#akaryawan").html(alamat);
                        $("#limit_belanja").html(Number(limit_belanja).toLocaleString('id-ID')).data('raw', limit_belanja);
                        check_limit_belanja($("#npk").val());
                        check_jadwal_belanja($("#npk").val());
                        tampilkan_foto(npk);

                        console.log(elapsedTime);
                        if (elapsedTime > 100000 && npk != '1'){
                            alert("hanya bisa berbelanja menggunakan kartu");
                            location.reload();
                        }
                    }else{
                        console.log("tidak ketemu");
                        $("#nkaryawan, #akaryawan, #limit, #limit_belanja").html("");
                        $("#foto_karyawan").hide().attr("src", "");
                        $("#foto-karyawan-placeholder").text("Foto tidak tersedia");
                        $("#foto-karyawan-placeholder").show();
                    }
                },
                error: function( req, status, err ) {
                    console.log( 'something went wrong', status, err );
                }
            });
        }
    });

    $("#barcode").keypress(function(e){
        if(e.which==13 && $(this).val()!=""){
            var t = $(this).val();
            var x = t.split("-");
            console.log(x.length);
            if(x.length<2){
                $(this).val(x[0]);
            }else{
                $(this).val(x[1]);
            }

            var data = $(this).val();
            data = data.split("*");
            // console.log(data.length);
            if(data.length==2){
                kuantiti = data[0];
                kode = parseInt(data[1],10);
            }else{
                kuantiti = 1;
                kode = parseInt(data[0],10);
            }
            console.log("kode:"+kode+" - jumlah:"+kuantiti);
            $(this).val("");
            add(kode,kuantiti);
            // $(this).off("focus");
            window.setTimeout($("#npk").focus(), 1500);
            window.setTimeout($("#barcode").focus(), 1700);
        }
        // alert(e.which);
    });

    var baseColumns = [
        { type: 'text' },
        { type: 'text' },
        { type: 'numeric' },
        { type: 'numeric' },
        { type: 'numeric' },
    ];
    var baseColHeaders = ['kode', 'nama barang', 'harga','kuantiti', 'jumlah'];
    var baseColWidths = [100, 250, 150, 75, 150];

    if (pajak_active) {
        baseColumns.push({ type: 'numeric' }, { type: 'numeric' });
        baseColHeaders.push('ppn', 'pajak');
        baseColWidths.push(75, 150);
    }

    $('#my').jexcel({
        data: dx,
        columns: baseColumns,
        colHeaders: baseColHeaders,
        colWidths: baseColWidths,
    });

    $('#my').jexcel('updateSettings', {
        cells: function (cell, col, row) {
            updatettl();
            checklimit();
            if (col < 1) {
                // value = $('#my').jexcel('getValue', $(cell));
                // console.log(value);
                // val = numeral($(cell).text()).format('0,0.00');
                // $(cell).html('<input type="hidden" value="' + value + '">' + val);
            }
        }
    });

    // Sisa bayar setelah pembayaran voucher gift card (PPN tetap, voucher sebagai alat bayar).
    function checkoutGrandTotal() {
        var diskon = activeVoucher ? activeVoucher.diskon : 0;
        return Math.max(0, totl - diskon);
    }

    function updatePaymentDisplay() {
        var method = currentPaymentMethod();
        var cash = isCashMethod(method);
        var diskon = activeVoucher ? activeVoucher.diskon : 0;
        var finalTotl = checkoutGrandTotal();

        // Ringkasan nilai: total belanja utuh (sudah PPN), dibayar voucher gift card, sisa bayar.
        $("#payment-total-display").html("Rp " + formatRupiah(Math.round(totl)));
        if (diskon > 0) {
            $("#payment-diskon-row").css("display", "flex");
            $("#payment-diskon-display").html("- Rp " + formatRupiah(Math.round(diskon)));
        } else {
            $("#payment-diskon-row").hide();
        }
        $("#payment-bayar-display").html("Rp " + formatRupiah(Math.round(finalTotl)));

        // Kembalian hanya relevan untuk pembayaran tunai.
        var nominalBayar = parseRupiahInput($("#nominal-bayar").val());
        var kembalian = nominalBayar - finalTotl;
        var statusText = "";

        if (cash) {
            if (nominalBayar <= 0) {
                statusText = "Masukkan nominal uang yang diterima.";
            } else if (kembalian >= 0) {
                statusText = "Kembalian Rp " + formatRupiah(Math.round(kembalian)) + ".";
            } else {
                statusText = "Masih kurang Rp " + formatRupiah(Math.round(Math.abs(kembalian))) + ".";
            }

            $("#payment-kembalian-display")
                .html("Rp " + formatRupiah(Math.round(Math.abs(kembalian))))
                .css("color", kembalian >= 0 ? "#27ae60" : "#c0392b");
        }

        $("#payment-status-text").text(statusText);
    }

    // ------------------------------------------------------------------
    // Voucher gift card: validasi ke server, tampilkan nominal pembayaran voucher, hitung ulang sisa bayar (PPN tetap).
    // ------------------------------------------------------------------
    function clearVoucher(message, isError) {
        activeVoucher = null;
        $("#voucher-status")
            .text(message || "")
            .css("color", isError ? "#c0392b" : "#666");
        updatePaymentDisplay();
    }

    function applyVoucherFromServer(result) {
        activeVoucher = { kode: $.trim($("#checkout-voucher").val()), diskon: result.diskon };
        $("#voucher-status")
            .text("Voucher Gift Card berlaku. Dibayar voucher Rp " + formatRupiah(Math.round(result.diskon)) + ".")
            .css("color", "#27ae60");
        updatePaymentDisplay();
    }

    function checkVoucher() {
        var kode = $.trim($("#checkout-voucher").val());
        if (kode === "") {
            clearVoucher("", false);
            return;
        }

        $("#check-voucher").prop("disabled", true);
        $("#voucher-status").text("Memeriksa voucher...").css("color", "#666");

        $.ajax({
            method: "GET",
            url: "http://localhost/simpleton/index.php/main/kasir/validate_voucher/"
                + encodeURIComponent(kode) + "/" + Math.round(totl),
            data: { npk: $.trim($("#npk").val()) },
            dataType: "json"
        }).done(function (result) {
            if (!result || !result.valid) {
                clearVoucher(result && result.message ? result.message : "Voucher tidak valid.", true);
                return;
            }
            applyVoucherFromServer(result);
        }).fail(function () {
            clearVoucher("Gagal memeriksa voucher. Coba lagi.", true);
        }).always(function () {
            $("#check-voucher").prop("disabled", false);
        });
    }

    function loadEligibleVouchers(npk) {
        var list = $("#eligible-voucher-list");
        list.empty().append($('<option>').val('').text('Memuat voucher...'));
        if (!npk) {
            list.empty().append($('<option>').val('').text('Pilih anggota untuk melihat voucher'));
            return;
        }
        $.ajax({
            method: "GET",
            url: "http://localhost/simpleton/index.php/main/kasir/eligible_vouchers",
            data: { npk: npk },
            dataType: "json"
        }).done(function (result) {
            if ($.trim($("#npk").val()) !== npk) {
                return;
            }
            list.empty().append($('<option>').val('').text('Pilih voucher'));
            var vouchers = result && $.isArray(result.data) ? result.data : [];
            if (!vouchers.length) {
                list.empty().append($('<option>').val('').text('Tidak ada voucher tersedia untuk NPK ini'));
                return;
            }
            vouchers.forEach(function (voucher) {
                var amount = Number(voucher.nilai);
                var amountLabel = isFinite(amount) ? ' - Rp ' + formatRupiah(Math.round(amount)) : '';
                list.append($('<option>').val(voucher.serial_code).text(
                    voucher.nama + ' (' + voucher.serial_code + ')' + amountLabel
                ));
            });
        }).fail(function () {
            list.empty().append($('<option>').val('').text('Gagal memuat voucher'));
        });
    }

    $("#nominal-bayar").on("input", function () {
        formatRupiahInput(this);
        updatePaymentDisplay();
    });
	$("#eligible-voucher-list").on("change", function () {
		var code = $.trim($(this).val() || "");
		$("#checkout-voucher").val(code);
		if (code) {
			checkVoucher();
		} else {
			clearVoucher("", false);
		}
	});
	$("#npk").on("input", function () {
		if (activeVoucher) {
			clearVoucher("NPK berubah, pilih ulang voucher.", false);
		}
		$("#eligible-voucher-list").empty().append($('<option>').val('').text('Pilih anggota untuk melihat voucher'));
	});
    $("#check-voucher").on("click", checkVoucher);
    $("#checkout-voucher").on("keypress", function (e) {
        if (e.which === 13) {
            e.preventDefault();
            checkVoucher();
        }
    });
    // Edit kode voucher membatalkan hasil validasi sebelumnya.
    $("#checkout-voucher").on("input", function () {
        if (activeVoucher && $.trim($(this).val()) !== activeVoucher.kode) {
            clearVoucher("Kode voucher berubah, tekan Cek untuk memvalidasi ulang.", false);
        }
    });

    function openCheckout(){
        if (checkoutInProgress) {
            return;
        }
        if (pendingCheckout) {
            $("#checkout-modal").modal("show");
            return;
        }
        var npk = $.trim($("#npk").val());
        var cashier = $("#nkasir").val();
        if(npk === ""){
            alert("Isi dulu NPK.");
            return;
        }
        if(!cashier || cashier === "0" || cashier === "belum"){
            alert("Belum ada kasir yang dipilih. Pilih kasir dari daftar sebelum checkout.");
            return;
        }
        var data = $('#my').jexcel('getData') || [];
        var cartRows = data.filter(function (row) {
            return row && $.trim(String(row[0] || "")) !== "";
        });
        if(cartRows.length === 0){
            alert("Keranjang belanja masih kosong.");
            return;
        }

        $("#checkout-summary").text(
            "Anggota: " + ($("#nkaryawan").text() || npk) +
            " (" + npk + ") | Kasir: " + cashier +
            " | " + $("#ttx").text()
        );
        updatePaymentFields();
        loadEligibleVouchers(npk);
        $("#checkout-modal").modal("show");
        updatePaymentDisplay();
    }

    function buildCheckoutPayload() {
        var npk = $.trim($("#npk").val());
        var cashier = $("#nkasir").val();
        var paymentMethod = currentPaymentMethod();
        var data = $("#my").jexcel("getData") || [];
        var cartRows = data.filter(function (row) {
            return row && $.trim(String(row[0] || "")) !== "";
        });
        if (cartRows.length === 0) {
            alert("Keranjang belanja masih kosong.");
            return null;
        }

        var cash = isCashMethod(paymentMethod);
        var amountPaid = cash ? parseRupiahInput($("#nominal-bayar").val()) : checkoutGrandTotal();
        var kodeKonfirmasi = $.trim($("#kode-konfirmasi").val());
        var targetBank = $.trim($("#bank-tujuan").val());

        if (cash && (!isFinite(amountPaid) || amountPaid <= 0)) {
            alert("Masukkan jumlah uang tunai yang diterima.");
            $("#nominal-bayar").focus();
            return null;
        }
        if (paymentMethod === "edc" && kodeKonfirmasi === "") {
            alert("Masukkan nomor referensi atau approval EDC.");
            $("#kode-konfirmasi").focus();
            return null;
        }
        if (paymentMethod === "qris" && kodeKonfirmasi === "") {
            alert("Masukkan kode konfirmasi pembayaran QRIS.");
            $("#kode-konfirmasi").focus();
            return null;
        }
        if (paymentMethod === "transfer" && kodeKonfirmasi === "") {
            alert("Masukkan kode konfirmasi / nomor transfer.");
            $("#kode-konfirmasi").focus();
            return null;
        }
        if (paymentMethod === "transfer" && targetBank === "") {
            alert("Masukkan nama bank tujuan.");
            $("#bank-tujuan").focus();
            return null;
        }

        // Backend memakai nama nomor_referensi untuk semua pembayaran non-tunai.
        $("#nomor-referensi").val(kodeKonfirmasi);

        var timestamp = (new Date()).getTime();
        var requestId = npk + "-" + timestamp + "-" + Math.floor(Math.random() * 1000000);
        var items = cartRows.map(function (row) {
            return {
                barcode: row[0],
                qty: row[3],
                ppn: pajak_active ? row[5] : 0
            };
        });
        return {
            rows: cartRows,
            npk: npk,
            cashierName: cashier,
            paymentMethod: paymentMethod,
            now: new Date().toLocaleString(),
            payload: {
                request_id: requestId,
                id_user: cashier,
                id_karyawan: npk,
                metode_pembayaran: paymentMethod,
                nominal_bayar: amountPaid,
                kode_voucher: activeVoucher ? activeVoucher.kode : "",
                nomor_referensi: kodeKonfirmasi,
                bank_tujuan: targetBank,
                items_json: JSON.stringify(items)
            }
        };
    }

    function handleCheckoutSuccess(response, completedCheckout) {
        if (!response || !response.success) {
            var message = response && response.message
                ? response.message
                : "Transaksi gagal dan belum tercatat.";
            if (response && response.errors && response.errors.length) {
                message += "\n\n" + response.errors.join("\n");
            }
            pendingCheckout = null;
            $("[data-checkout-field]").find("input").prop("disabled", false);
            $("#checkout-retry-notice").hide().text("");
            $("#confirm-checkout").text("Simpan Transaksi");
            alert(message);
            return;
        }
        var receiptNo = response.data && response.data.nota;
        if (!receiptNo) {
            $(["#confirm-checkout"]).text("Coba Ulang dengan Nota yang Sama");
            $("#checkout-retry-notice")
                .text("Transaksi tersimpan, tetapi nomor nota belum diterima. Coba ulang untuk mengambil nomor yang sama.")
                .show();
            return;
        }
        completedCheckout = pendingCheckout;
        completedCheckout.receiptNo = receiptNo;
        pendingCheckout = null;
        $("[data-checkout-field]").find("input").prop("disabled", false);
        $("#checkout-retry-notice").hide().text("");
        $("#checkout-modal").modal("hide");
        var receiptUrl = pajak_active
            ? "http://localhost/simpleton/assets/kasir/notabaru.html"
            : "http://localhost/simpleton/assets/kasir/nota.html";
        receiptUrl += "?v=20261024-short-receipt-number&nota=" + encodeURIComponent(completedCheckout.receiptNo);
        var receipt = window.open(receiptUrl, "targetWindow", "toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=250,height=500");
        dx = [];
        $("#my").jexcel("setData", dx, true);
        updatettl();
        checklimit();
        if (!receipt) {
              $("#confirm-checkout").text("Coba Ulang dengan Nota yang Sama");
            return;
        }
        var applyReceiptData = function () {
            if (receipt.closed) {
                return;
            }
            receipt.pass_data = completedCheckout.rows;
            receipt.npk = completedCheckout.npk;
            receipt.nkar = $("#nkaryawan").html();
            receipt.akar = $("#akaryawan").html();
            receipt.tgl = completedCheckout.now;
            receipt.no = completedCheckout.receiptNo;
            receipt.nkasir = completedCheckout.cashierName;
            receipt.metodePembayaran = completedCheckout.paymentMethod;
            receipt.totalTagihan = response.data ? response.data.total_tagihan : totl;
            receipt.diskonVoucher = response.data ? response.data.diskon_voucher : 0;
            receipt.kembalian = response.data ? response.data.kembalian : 0;
            receipt.nominalBayar = completedCheckout.payload.nominal_bayar;
            if (typeof receipt.renderReceiptBarcode === "function") {
                receipt.renderReceiptBarcode();
            }
        };
        receipt.onload = applyReceiptData;
        applyReceiptData();
        activeVoucher = null;
        $("#checkout-voucher").val("");
        $("#eligible-voucher-list").empty().append($('<option>').val('').text('Pilih anggota untuk melihat voucher'));
        $("#voucher-status").empty();
        $("#nominal-bayar, #kode-konfirmasi, #bank-tujuan").val("");
        updatePaymentDisplay();
    }

    function handleCheckoutFailure(request) {
        if (request.status === 409 && request.responseJSON) {
            pendingCheckout = null;
            $("[data-checkout-field]").find("input").prop("disabled", false);
            $("#checkout-retry-notice").hide().text("");
            $("#confirm-checkout").text("Simpan Transaksi");
            alert(request.responseJSON.message || "Nomor transaksi sudah digunakan.");
            return;
        }
        $("#checkout-retry-notice")
            .text("Respons server tidak diterima. Status transaksi belum dapat dipastikan. Gunakan tombol ulang untuk mengirim nota yang sama; jangan ubah data transaksi.")
            .show();
        $("[data-checkout-field]").find("input").prop("disabled", true);
            $("#confirm-checkout").text("Coba Ulang dengan Nota yang Sama");
    }

    function submitCheckout() {
        if (checkoutInProgress) {
            return;
        }

        if (!pendingCheckout) {
            pendingCheckout = buildCheckoutPayload();
            if (!pendingCheckout) {
                return;
            }
        }

        checkoutInProgress = true;
        $("#confirm-checkout").prop("disabled", true).text("Menyimpan...");
        $.ajax({
            method: "POST",
            url: "http://localhost/simpleton/index.php/main/kasir/checkout",
            dataType: "json",
            data: pendingCheckout.payload
        }).done(function (response) {
            handleCheckoutSuccess(response, pendingCheckout);
        }).fail(function (request) {
            handleCheckoutFailure(request);
        }).always(function () {
            checkoutInProgress = false;
            $("#confirm-checkout").prop("disabled", false);
        });
    }

    $("#checkout").on("click", openCheckout);
    $("#confirm-checkout").on("click", submitCheckout);
    $("#clear-cart").on("click", function () {
        if (pendingCheckout) {
            alert("Transaksi belum dipastikan. Selesaikan atau ulangi checkout dengan nota yang sama terlebih dahulu.");
            return;
        }
        if (dx.length > 0 && !window.confirm("Kosongkan semua barang di keranjang?")) {
            return;
        }
        dx = [];
        $("#my").jexcel("setData", dx, true);
        updatettl();
        checklimit();
    });

    $(document).bind('keydown', function (e) {
        if (e.keyCode === 120) {
            e.preventDefault();
            if ($("#checkout-modal").hasClass("in")) {
                submitCheckout();
            } else {
                openCheckout();
            }
        }
    });

});  
