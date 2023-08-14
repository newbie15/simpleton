var dx = [
    // ['kode', 'nama barang', 1000, 1, '=C1*D1'],
];

$(document).ready(function(){
    
    var limit;
    var totl;
    var show;
    var pajak_active = false;

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

    $("#nkasir").load("http://localhost/simpleton/index.php/main/ajax/daftar_kasir");

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
        // $("#foto_karyawan").attr("src",);
        var image = document.querySelector('#foto_karyawan');
        image.setAttribute('src', "http://localhost/simpleton/assets/uploads/karyawan/" + npk + ".png");
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
            $(this).val(x[1]);
            stotime = performance.now();

            $.ajax({
                url: 'http://localhost/simpleton/index.php/main/ajax/karyawan/' + $(this).val(),
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
                        tampilkan_foto($("#npk").val());
                    }else{
                        console.log("tidak ketemu");
                    }
                },
                error: function( req, status, err ) {
                console.log( 'something went wrong', status, err );
                }
            });

            console.log(stotime-sttime);


            if ((stotime - sttime)>100000 && x[1] != '1'){
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
        if($("#npk").val()==""){
            alert("isi dulu NPK");
        }else if($("#nkasir").val()=="0"){
            alert("pilih dulu kasir");
        }else if(pajak_active){
            var epoch = Math.floor((new Date).getTime() / 1000);
            var j = $('#my').jexcel('getData');
            // $('#txt').val();
            console.log(j);
            var datax = "";
            var d = new Date();
            var n = d.toLocaleString();
            var nota = $("#npk").val()+"-"+epoch;
            var i = j.length;
            var k = 0;
            var nama_kasir = "KMS";
            nama_kasir = $("#nkasir").val();
            j.forEach(element => {
                res = element[1].substr(0, 10);
                total += (element[2] * element[3]) + element[6];
                pjkx += element[6];
                $.ajax({
                    method: "POST",
                    url: "http://localhost/simpleton/index.php/main/kasir/checkout",
                    data: {
                        no : nota,
                        id_user: nama_kasir,
                        id_karyawan: $("#npk").val(),
                        tgl: n,
                        nama: res,
                        harga: element[2],
                        jumlah: element[2] * element[3],
                        pajak: (element[2] * element[3]) * element[5],
                        kode: element[0],
                        kurangstok: element[3],
                    }
                }).done(function (msg) {
                    console.log("Data Saved: " + msg);
                    k++;
                    if(k==i){
                        var newWindow = window.open('http://localhost/simpleton/assets/kasir/notabaru.html', 'targetWindow', 'toolbar=no,location = no,status = no, menubar = no, scrollbars = yes, resizable = yes, width = 250, height = 500');

                        // Access it using its variable
                        newWindow.pass_data = j;
                        newWindow.npk = $("#npk").val();
                        newWindow.nkar = $("#nkaryawan").html();
                        newWindow.akar = $("#akaryawan").html();
                        newWindow.tgl = n;
                        newWindow.no = nota;
                        newWindow.nkasir = nama_kasir;
                    }
                    // console.log(i + " " + k);
                });
            });
        }else{
            var epoch = Math.floor((new Date).getTime() / 1000);
            var j = $('#my').jexcel('getData');
            // $('#txt').val();
            console.log(j);
            var datax = "";
            var d = new Date();
            var n = d.toLocaleString();
            var nota = $("#npk").val()+"-"+epoch;
            var i = j.length;
            var k = 0;
            var nama_kasir = "KMS";
            nama_kasir = $("#nkasir").val();
            j.forEach(element => {
                res = element[1].substr(0, 10);
                total += element[2] * element[3];

                $.ajax({
                    method: "POST",
                    url: "http://localhost/simpleton/index.php/main/kasir/checkout",
                    data: {
                        no : nota,
                        id_user: nama_kasir,
                        id_karyawan: $("#npk").val(),
                        tgl: n,
                        nama: res,
                        harga: element[2],
                        jumlah: element[2] * element[3],
                        kode: element[0],
                        kurangstok: element[3],
                    }
                }).done(function (msg) {
                    console.log("Data Saved: " + msg);
                    k++;
                    if(k==i){
                        var newWindow = window.open('http://localhost/simpleton/assets/kasir/nota.html', 'targetWindow', 'toolbar=no,location = no,status = no, menubar = no, scrollbars = yes, resizable = yes, width = 250, height = 500');

                        // Access it using its variable
                        newWindow.pass_data = j;
                        newWindow.npk = $("#npk").val();
                        newWindow.nkar = $("#nkaryawan").html();
                        newWindow.akar = $("#akaryawan").html();
                        newWindow.tgl = n;
                        newWindow.no = nota;
                        newWindow.nkasir = nama_kasir;
                    }
                    // console.log(i + " " + k);
                });
            });
        }
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
