var dx = [
    // ['kode', 'nama barang', 1000, 1, '=C1*D1'],
];

$(document).ready(function(){
    
    function updatettl(){
        var total = 0;
        dx.forEach(element => {
            total += element[2] * element[3];
        });
        $("#ttx").html("Total Rp " + total.toLocaleString());
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

    function add(kode,kuantiti){
        var sama = 0;
        var index = 0;
        dx.forEach(element => {
            // console.log(element[0]);
            if (kode == element[0]){
                console.log("sama");
                sama = 1;
                console.log(dx[index]);
                // console.log(dx[index][3]);
                // dx[index][3] = parseInt(dx[index][3]) + parseInt(kuantiti);
                // console.log(dx[index][3]);
                // $('#my').jexcel('setData', dx, true);
                // updatettl();
            }
            index++;
        });
        if(sama==0){
            $.ajax({
                url: 'http://localhost/simpleton/index.php/main/ajax/kartu/' + encodeURI(kode),
                // dataType: 'json',
                success: function (resp) {
                    // $( '#target').html( resp.people[0].name );
                    if (resp != "") {
                        var data = resp.split(":");
                        var exist = $("#cart-list").html();
                        var nama = data[0];
                        var bagian = data[1];
                        // var jumlah = harga * kuantiti;

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
                        // last = '=C' + ln + '*D' + ln;
                        dx.push([kode, nama, bagian]);
                        console.log(dx);
                        $('#my').jexcel('setData', dx, true);
                        // updatettl();
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
    }

    // $("#npk").keypress(function(e){
    //     if(e.which==13 && $(this).val()!=""){
    //         var t = $(this).val();
    //         console.log(t);
    //         var x = t.split("-");
    //         $(this).val(x[1]);

    //     $.ajax({
    //         url: 'http://localhost/simpleton/index.php/main/ajax/karyawan/' + $(this).val(),
    //         // dataType: 'json',
    //         success: function( resp ) {
    //             if(resp!=""){
    //                 var data = resp.split(":");
    //                 var nama = data[0];
    //                 var alamat = data[1];
    //                 var limit_belanja = data[2];
    //                 $("#nkaryawan").html(nama);
    //                 $("#akaryawan").html(alamat);
    //                 $("#limit_belanja").html(limit_belanja);
    //             }else{
    //                 console.log("tidak ketemu");
    //             }
    //         },
    //         error: function( req, status, err ) {
    //         console.log( 'something went wrong', status, err );
    //         }
    //     });
    //     }
    // });
    
    $("#npk").keypress(function(e){
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
                kode = data[1];
                // kode = parseInt(data[1],10);
            }else{
                kuantiti = 1;
                kode = data[0];
                // kode = parseInt(data[0],10);
            }
            console.log("kode:"+kode+" - jumlah:"+kuantiti);
            $(this).val("");
            add(kode,kuantiti);
            // $(this).off("focus");
            window.setTimeout($("#npk").focus(), 1500);
            // window.setTimeout($("#barcode").focus(), 1700);
        }
        // alert(e.which);
    });

    $('#my').jexcel({
        data: dx,
        columns: [
            { type: 'text' },
            { type: 'text' },
            { type: 'text' },
        ],
        colHeaders: ['NPK', 'Nama Karyawan', 'Bagian'],
        // colWidths: [400, 100, 200],
        colWidths: [100, 250, 250, 75, 150],
    });

    $('#my').jexcel('updateSettings', {
        cells: function (cell, col, row) {
            updatettl();
            if (col < 1) {
                // value = $('#my').jexcel('getValue', $(cell));
                // console.log(value);
                // val = numeral($(cell).text()).format('0,0.00');
                // $(cell).html('<input type="hidden" value="' + value + '">' + val);
            }
        }
    });

    function checkout(){
        var j = $('#my').jexcel('getData');

        var newWindow = window.open('http://localhost/simpleton/assets/kartu/cetak.html', 'targetWindow', 'toolbar=no,location = no,status = no, menubar = no, scrollbars = yes, resizable = yes, width = 250, height = 500');
        newWindow.pass_data = j;

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
