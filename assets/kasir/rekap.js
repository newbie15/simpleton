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

    $("#download-csv-harian").click(function(){
        // download_file_harian();
        window.open("http://localhost/simpleton/index.php/main/ajax/data_harian/" + $("#tanggal").val() + "/d");

    });




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

    function download_file_harian() {
        Papa.parse("http://localhost/simpleton/index.php/main/ajax/data_harian/" + $("#tanggal").val() +"/d", {
            download: true,
            complete: function (results) {
                console.log(results);
            }
        });
    }
});