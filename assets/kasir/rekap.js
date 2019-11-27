$(document).ready(function () {
    var d1 = [];
    for (var i = 0; i < 14; i += 0.5) {
        d1.push([i, Math.sin(i)]);
    }

    var d2 = [[0, 3], [4, 8], [8, 5], [9, 13]];

    // A null signifies separate line segments

    var d3 = [[0, 12], [7, 12], null, [7, 2.5], [12, 2.5]];

    // $.plot("#placeholder1", [d1]); 
    // $.plot("#placeholder2", [d2]); 
    // $.plot("#placeholder3", [d3]); 

    var options1 = {
        url: "http://localhost/simpleton/index.php/main/ajax/karyawan_list",
        getValue: "nama",
        list: {
            match: {
                enabled: true
            }
        }
    };
    // var options2 = {
    //     url: "http://localhost/simpleton/index.php/main/ajax/produk_list",
    //     getValue: "produk",
    //     requestDelay: 500,
    //     list: {
    //         match: {
    //             enabled: true
    //         }
    //     }
    // };

    $("#npk").easyAutocomplete(options1);
    // $("#barcode").easyAutocomplete(options2);

    $("#npk").keypress(function (e) {
        if (e.which == 13 && $(this).val() != "") {
            var t = $(this).val();
            var x = t.split("-");
            console.log(x.length);
            if (x.length < 2) {
                $(this).val(x[0]);
            } else {
                $(this).val(x[1]);
            }

            // console.log("kode booking:", $(this).val());

            ajax_perorangan_refresh();

        }
    });


    // init here
    $("#harian").show();
    $("#mingguan").hide();
    $("#bulanan").hide();
    $("#tahunan").hide();

    // $("#tanggal").val()
    var date = new Date(); 
    //create date object 
    var min_date = date.toISOString().slice(0,10); ////get current date specific part "2018-02-02"
    $('#tanggal').val(min_date);
    ajax_harian_refresh();

    $("#h").click(function(){
        // alert("helo");
        $("#harian").show();
        $("#mingguan").hide();
        $("#bulanan").hide();
        $("#tahunan").hide();
    });

    $("#m").click(function () {
        $("#harian").hide();
        $("#mingguan").show();
        $("#bulanan").hide();
        $("#tahunan").hide();
    });

    $("#b").click(function () {
        $("#harian").hide();
        $("#mingguan").hide();
        $("#bulanan").show();
        $("#tahunan").hide();
    });

    $("#t").click(function () {
        $("#harian").hide();
        $("#mingguan").hide();
        $("#bulanan").hide();
        $("#tahunan").show();
    });

    $("#tanggal").change(function(){
        ajax_harian_refresh();
    });

    $("#bulan_perorangan").change(function () {
        ajax_perorangan_refresh();
    });


    $("#download-csv-harian").click(function(){
        // download_file_harian();
        window.open("http://localhost/simpleton/index.php/main/ajax/data_harian/" + $("#tanggal").val() + "/d");

    });


    $("#tahun_perorangan").change(function () {
        var syear = parseInt($(this).val());
        var shtml = null; //"<option>"++"</option>"
        var start_year = syear - 2;
        var stop_year = syear + 2;
        for (var i = start_year; i <= stop_year; i++) {
            shtml += "<option>" + i + "</option>";
        }
        $(this).html(shtml);
        $(this).val(syear.toString());

        ajax_perorangan_refresh();
    });

    var tgl = new Date();
    var y = tgl.getFullYear();

    var shtml = null; //"<option>"++"</option>"
    var start_year = y - 2;
    var stop_year = y + 2;
    for (var i = start_year; i <= stop_year; i++) {
        shtml += "<option>" + i + "</option>";
    }
    $("#tahun_perorangan").html(shtml);
    $("#tahun_perorangan").val(y);


    function ajax_harian_refresh() {
        $("#harian-sheet").html("");

        jexcel(document.getElementById('harian-sheet'), {
            csv: 'http://localhost/simpleton/index.php/main/ajax/data_harian/' + $("#tanggal").val(),
            csvHeaders: true,
            search: true,
            pagination: 10,
            columns: [
                { type: 'text', width: 100 },
                { type: 'text', width: 100 },
                { type: 'text', width: 200 },
                { type: 'text', width: 100 },
                { type: 'text', width: 100 },
                { type: 'text', width: 250 },
                { type: 'text', width: 50 },
                { type: 'text', width: 25 },
                { type: 'text', width: 75 },
            ]
        });        
    }

    function ajax_perorangan_refresh() {
        $("#perorangan-sheet").html("");
        console.log('http://localhost/simpleton/index.php/main/ajax/data_perorangan/' + $("#tahun_perorangan").val()+"-"+$("#bulan_perorangan").val() + "/" + $("#npk").val());
        jexcel(document.getElementById('perorangan-sheet'), {
            csv: 'http://localhost/simpleton/index.php/main/ajax/data_perorangan/' + $("#tahun_perorangan").val() + "-" + $("#bulan_perorangan").val() + "/" + $("#npk").val(),
            csvHeaders: true,
            search: true,
            pagination: 10,
            columns: [
                { type: 'text', width: 200 },
                { type: 'text', width: 200 },
                { type: 'text', width: 250 },
                { type: 'text', width: 100 },
                // { type: 'text', width: 100 },
                // { type: 'text', width: 250 },
                // { type: 'text', width: 50 },
                // { type: 'text', width: 25 },
                // { type: 'text', width: 75 },
            ]
        });
    }

    function download_file_harian() {
        Papa.parse("http://localhost/simpleton/index.php/main/ajax/data_harian/" + $("#tanggal").val() +"/d", {
            download: true,
            complete: function (results) {
                console.log(results);
            }
        });
    }
});