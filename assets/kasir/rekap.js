$(document).ready(function () {
    console.log("coba");
    // var d1 = [];
    // for (var i = 0; i < 14; i += 0.5) {
    //     d1.push([i, Math.sin(i)]);
    // }

    // var d2 = [[0, 3], [4, 8], [8, 5], [9, 13]];

    // // A null signifies separate line segments

    // var d3 = [[0, 12], [7, 12], null, [7, 2.5], [12, 2.5]];

    // $.plot("#placeholder1", [d1]); 
    // $.plot("#placeholder2", [d2]); 
    // $.plot("#placeholder3", [d3]); 

    $.ajax({
        url: 'http://localhost/simpleton/index.php/main/ajax/rekap_harian',
        // dataType: 'json',
        success: function (resp) {
            // console.log(resp);
            $("#harian").html(resp);
            // $("#dropBox1").html("");
            // alert("Data Source is updated");
        },
        error: function (req, status, err) {
            console.log('something went wrong', status, err);
        }
    });

    // $("#harian").load("http://localhost/simpleton/index.php/main/ajax/rekap_harian");
});