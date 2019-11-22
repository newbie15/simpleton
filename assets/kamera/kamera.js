var dx = [
    // ['kode', 'nama barang', 1000, 1, '=C1*D1'],
];

$(document).ready(function () {
    var video = document.querySelector("#videoElement");
    var imageDataURL;

    if (navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: true })
            .then(function (stream) {
                video.srcObject = stream;
            })
            .catch(function (err0r) {
                console.log("Something went wrong!");
            });
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


    $("#npk").easyAutocomplete(options1);

    var no = 1;
    var total = 0;

    $("#npk").keypress(function (e) {
        if (e.which == 13 && $(this).val() != "") {
            var t = $(this).val();
            var x = t.split("-");
            var npk;
            console.log(x.length);
            if (x.length < 2) {
                // $(this).val(x[0]);
                npk = x[0];
            } else {
                // $(this).val(x[1]);
                npk = x[1];
            }

            var image = document.querySelector('img');
            image.setAttribute('src', "http://localhost/simpleton/assets/uploads/karyawan/"+npk+".png");


            // $.ajax({
            //     method: "POST",
            //     url: "http://localhost/simpleton/index.php/main/kamera/load_photo/"+npk,
            //     data: {
            //         // id_karyawan: $("#npk").val(),
            //         // foto: imageDataURL,
            //     }
            // }).done(function (msg) {
            //     console.log(msg);
            // });
        }
    });

    function takeSnapshot() {

        var hidden_canvas = document.querySelector('canvas'),
            video = document.querySelector('#videoElement'),
            image = document.querySelector('img'),

            // Get the exact size of the video element.
            width = video.videoWidth,
            height = video.videoHeight,

            // Context object for working with the canvas.
            context = hidden_canvas.getContext('2d');

        // Set the canvas to the same dimensions as the video.
        hidden_canvas.width = width;
        hidden_canvas.height = height;

        // Draw a copy of the current frame from the video on the canvas.
        context.drawImage(video, 0, 0, width, height);

        // Get an image dataURL from the canvas.
        imageDataURL = hidden_canvas.toDataURL('image/png');

        // Set the dataURL as source of an image element, showing the captured photo.
        image.setAttribute('src', imageDataURL);

    }

    function simpan_foto(){
        console.log(imageDataURL);
    }

    $("#simpan").click(function () {
        // checkout();
        var npk = $("#npk").val();
        if(npk==""){
            alert("masukkan NPK dahulu");
        }else{
            // simpan_foto();
            $.ajax({
                    method: "POST",
                    url: "http://localhost/simpleton/index.php/main/kamera/upload_photo",
                    data: {
                        id_karyawan: $("#npk").val(),
                        foto: imageDataURL,
                    }
                }).done(function (msg) {
                    console.log(msg);
                });


        }
    });



    $("#snapshot").click(function () {
        // checkout();
        takeSnapshot();
    });


});