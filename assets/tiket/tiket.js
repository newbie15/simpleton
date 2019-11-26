$(document).ready(function(){
    var sukses = function () {
        $(".n_success").show();
        $(".n_success").fadeOut(3000);
    }


    var options1 = {
        url: "http://localhost/simpleton/index.php/main/ajax/kodebooking_list",
        getValue: "nama",
        list: {
            match: {
                enabled: true
            }
        }
    };


    $("#kodebooking").easyAutocomplete(options1);

    $("#kodebooking").keypress(function (e) {
        if (e.which == 13 && $(this).val() != "") {
            var t = $(this).val();
            var x = t.split("-");
            console.log(x.length);
            if (x.length < 2) {
                $(this).val(x[0]);
            } else {
                $(this).val(x[1]);
            }

            console.log("kode booking:",$(this).val());

            refresh_penumpang();
            refresh();

        }
    });

    function init(){}

    function refresh_penumpang() {
        $.ajax({
            method: "POST",
            url: "http://localhost/simpleton/index.php/main/tiket/load_penumpang",
            data: {
                kode_booking: $("#kodebooking").val(),
            }
        }).done(function (msg) {
            console.log(msg);
            data = JSON.parse(msg);
            console.log(data);
            $('#daftar-penumpang').jexcel({
                data: data,
                allowInsertColumn: false,

                colHeaders: [
                    // 'Station',
                    'Nama',
                    'No Ktp',
                    'No telepon',
                    'Harga',
                ],

                colWidths: [350, 200, 200, 150, 150, 150, 100, 150],
                columns: [
                    // { type: 'autocomplete', url: BASE_URL+'station/ajax/' + $("#pabrik").val() },
                    { type: 'text' },
                    { type: 'text' }, 
                    { type: 'text' },
                    { type: 'text' },
                ]
            });
        });
    }


    function refresh() {
        $.ajax({
            method: "POST",
            url: "http://localhost/simpleton/index.php/main/tiket/load_tiket",
            data: {
                kode_booking: $("#kodebooking").val(),
            }
        }).done(function (msg) {
            var data1,data2;
            if(msg!="[]"){
                console.log(msg);
                data = JSON.parse(msg);
                console.log("data ajax:", data);
                data1 = data[0];
                data2 = data[0];
                console.log(data1);
                console.log(data2);

                data1 = data1.slice(0,6);
                data2 = data2.slice(6,10);
                console.log("data1 new", data1);
                console.log("data2 new", data2);

                var x = [];
                var y = [];

                x[0] = data1;
                y[0] = data2;
                console.log(x);
                data1 = x;
                data2 = y;
            }else{

            }


            $('#my-spreadsheet').jexcel({
                data: data1,
                allowInsertColumn: false,
                allowInsertRow: false,

                colHeaders: [
                    // 'Station',
                    'Kode Booking',
                    'Tanggal',
                    'Waktu',
                    'Nama kontak',
                    'No<br>telepon',
                    'Email',
                    // 'Asal',
                    // 'Tujuan',
                    // 'Maskapai',
                    // 'Fasilitas'
                ],

                colWidths: [150, 150, 100, 150, 200, 250, 100, 150],
                columns: [
                    // { type: 'autocomplete', url: BASE_URL+'station/ajax/' + $("#pabrik").val() },
                    { type: 'text' },
                    { type: 'calendar',option: {format:'DD/MM/YYYY'} }, 
                    { type: 'text', mask: '##:##' },
                    { type: 'text' },
                    { type: 'text' },
                    { type: 'text' },
                    { type: 'text' },
                    { type: 'text' },
                    { type: 'text' },
                    { type: 'text' },
                    { type: 'text' },
                    { type: 'text' },
                ]
            });

            $('#my-spreadsheet2').jexcel({
                allowInsertColumn: false,
                allowInsertRow: false,
                data : data2,
                colHeaders: [
                    // 'Station',
                    'Asal',
                    'Tujuan',
                    'Maskapai',
                    'Fasilitas'
                ],

                colWidths: [200, 200, 200, 200, 150, 150, 100, 150],
                columns: [
                    // { type: 'autocomplete', url: BASE_URL+'station/ajax/' + $("#pabrik").val() },
                    { type: 'text' },
                    { type: 'text' }, 
                    { type: 'text' },
                    { type: 'text' },
                    { type: 'text' },
                    { type: 'text' },
                    { type: 'text' },
                    { type: 'text' },
                    { type: 'text' },
                    { type: 'text' },
                    { type: 'text' },
                    { type: 'text' },
                ]
            });
        });
    }

    $("#simpan").click(function () {
        var data_a = $('#my-spreadsheet').jexcel('getData');
        var data_b = $('#my-spreadsheet2').jexcel('getData');
        var data_c = $('#daftar-penumpang').jexcel('getData');

        console.log(data_a);
        console.log(data_b);
        console.log(data_c);

        var kodebooking = data_a[0][0];

        console.log("kodebooking :",kodebooking);

        $.ajax({
            method: "POST",
            url: "http://localhost/simpleton/index.php/main/tiket/simpan_tiket",
            success: sukses,
            data: {
                data_1: JSON.stringify(data_a),
                data_2: JSON.stringify(data_b),
                kode_booking: kodebooking,

                // data_json: JSON.stringify(data_j),
            }
        }).done(function (msg) {
            console.log(msg);
        });

        $.ajax({
            method: "POST",
            url: "http://localhost/simpleton/index.php/main/tiket/simpan_penumpang",
            success: sukses,
            data: {
                data_penumpang: JSON.stringify(data_c),
                kode_booking: kodebooking,

                // data_json: JSON.stringify(data_j),
            }
        }).done(function (msg) {
            console.log(msg);
        });
    });



    init();
    refresh();
    refresh_penumpang();

    


});
