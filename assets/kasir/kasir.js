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
        $("#limit").load("http://localhost/simpleton/index.php/main/ajax/limit_belanja/" + n);

        setTimeout(function(){
            var lb = parseInt($("#limit_belanja").html());
            var l = parseInt($("#limit").html());

            if(l>lb){
                alert("belanja anda telah melebihi limit bulanan\n silahkan berbelanja bulan depan");
                location.reload();
            }
        },2000);
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


    function updatettl(){
        var total = 0;
        if (pajak_active) {
            var pajek = 0;
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
        $("#ttx").html("Total Rp " + total.toLocaleString());
        // $("#ttx").html("Total Rp " + pajek.toLocaleString());
        totl = total;
    }

    function checklimit(){
        var lb = parseInt($("#limit_belanja").html());
        var l = parseInt($("#limit").html());

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
                        var exist = $("#cart-list").html();
                        var nama = data[0];
                        var harga = data[1];
                        var jumlah = harga * kuantiti;
                        if(pajak_active){
                            var ppn = data[2];
                        }

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

    var sttime = null;
    $("#npk").keypress(function(e){
        if(sttime==null){
            sttime =performance.now();
        }
        if(e.which==13 && $(this).val()!=""){
            var t = $(this).val();
            console.log(t);
            var x = t.split("-");
            var npk = x.length > 1 ? x[x.length - 1] : $.trim(t);
            $(this).val(npk);
            $("#foto_karyawan").hide().attr("src", "");
            $("#foto-karyawan-placeholder").text("Memuat foto...").show();
            stotime = performance.now();

            $.ajax({
                url: 'http://localhost/simpleton/index.php/main/ajax/karyawan/' + encodeURIComponent(npk),
                // dataType: 'json',
                success: function( resp ) {
                    if(resp!=""){
                        var data = resp.split(":");
                        var nama = data[0];
                        var alamat = data[1];
                        var limit_belanja = data[2];
                        limit = limit_belanja;
                        $("#nkaryawan").html(nama);
                        $("#akaryawan").html(alamat);
                        $("#limit_belanja").html(limit_belanja);
                        check_limit_belanja($("#npk").val());
                        check_jadwal_belanja($("#npk").val());
                        tampilkan_foto(npk);
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

            console.log(stotime-sttime);


            if ((stotime - sttime)>100000 && npk != '1'){
                alert("hanya bisa berbelanja menggunakan kartu");
                location.reload();
            }
            sttime = null;
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

    if(pajak_active){
        $('#my').jexcel({
            data: dx,
            columns: [
                { type: 'text' },
                { type: 'text' },
                { type: 'numeric' },
                { type: 'numeric' },
                { type: 'numeric' },
                { type: 'numeric' },
                { type: 'numeric' },
            ],
            colHeaders: ['kode', 'nama barang', 'harga','kuantiti', 'jumlah', 'ppn', 'pajak'],
            // colWidths: [400, 100, 200],
            colWidths: [100, 250, 150, 75, 150, 75, 150],
        });    
    }else{
        $('#my').jexcel({
            data: dx,
            columns: [
                { type: 'text' },
                { type: 'text' },
                { type: 'numeric' },
                { type: 'numeric' },
                { type: 'numeric' },
            ],
            colHeaders: ['kode', 'nama barang', 'harga','kuantiti', 'jumlah'],
            // colWidths: [400, 100, 200],
            colWidths: [100, 250, 150, 75, 150],
        });    
    }

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

    function checkout(){
        var npk = $.trim($("#npk").val());
        var cashier = $("#nkasir").val();
        var paymentMethod = $("#metode_pembayaran").val();
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
        var epoch = Math.floor((new Date()).getTime() / 1000);
        var now = new Date().toLocaleString();
        var nota = npk + "-" + epoch;
        var cashierName = cashier || "KMS";
        var items = cartRows.map(function (row) {
            return {
                barcode: row[0],
                qty: row[3],
                ppn: pajak_active ? row[6] : 0
            };
        });

        $.ajax({
            method: "POST",
            url: "http://localhost/simpleton/index.php/main/kasir/checkout",
            dataType: "json",
            data: {
                no: nota,
                id_user: cashierName,
                id_karyawan: npk,
                metode_pembayaran: paymentMethod,
                items_json: JSON.stringify(items)
            }
        }).done(function (response) {
            if (!response || !response.success) {
                var message = response && response.message
                    ? response.message
                    : "Transaksi gagal dan belum tercatat.";
                if (response && response.errors && response.errors.length) {
                    message += "\n\n" + response.errors.join("\n");
                }
                alert(message);
                return;
            }
            var receiptUrl = pajak_active
                ? "http://localhost/simpleton/assets/kasir/notabaru.html"
                : "http://localhost/simpleton/assets/kasir/nota.html";
            var receipt = window.open(receiptUrl, "targetWindow", "toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=250,height=500");
            if (!receipt) {
                alert("Transaksi berhasil dicatat, tetapi jendela nota diblokir browser. Nomor nota: " + nota);
                return;
            }
            receipt.pass_data = cartRows;
            receipt.npk = npk;
            receipt.nkar = $("#nkaryawan").html();
            receipt.akar = $("#akaryawan").html();
            receipt.tgl = now;
            receipt.no = nota;
            receipt.nkasir = cashierName;
            receipt.metodePembayaran = paymentMethod;
        }).fail(function () {
            alert("Checkout tidak mendapat respons server. Status pencatatan belum dapat dipastikan; periksa transaksi sebelum mencoba lagi.");
        });
    }

    $("#checkout").click(function(){
        checkout();
    });

    $(document).bind('keydown', function (e) {
        var unicode = e.keyCode;
        if (unicode == 120) {
            checkout();
        }
    });

});  
