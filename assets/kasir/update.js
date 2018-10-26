$(document).ready(function(){
    
    function parse(fl) {
        $.ajax({
            url: 'http://localhost/simpleton/index.php/main/kasir/excel/' + fl,
            // dataType: 'json',
            success: function (resp) {
        $("#dropBox").html(event.target.value + " uploading...");

                $("#dropBox").html("Parsing Data is in progress.");
                console.log(resp);
                $("#dropBox").html("Data Source is being updated");
                delete_parse(fl);
            },
            error: function (req, status, err) {
                console.log('something went wrong', status, err);
            }
        });
    }

    function delete_parse(f){
        $.ajax({
            url: 'http://localhost/simpleton/index.php/main/kasir/delete_excel/' + f,
            // dataType: 'json',
            success: function (resp) {
                console.log(resp);
                $("#dropBox").html("");
                $("#dropBox1").html("");
                alert("Data Source is updated");
            },
            error: function (req, status, err) {
                console.log('something went wrong', status, err);
            }
        });
    }

    function fileUpload(event,part) {
        //notify user about the file upload status
        if (part == "stok") {
            $("#dropBox").html(event.target.value + " uploading...");
        
        }else{
            $("#dropBox1").html(event.target.value + " uploading...");

        }

        //get selected file
        files = event.target.files;

        //form data check the above bullet for what it is  
        var data = new FormData();

        //file data is presented as an array
        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            //append the uploadable file to FormData object
            data.append('file', file, file.name);

            //create a new XMLHttpRequest
            var xhr = new XMLHttpRequest();

            //post file data for upload
            if(part=="stok"){
                xhr.open('POST', 'http://localhost/simpleton/index.php/main/ajax/upload_excel', true);
            }else if(part=="karyawan"){
                xhr.open('POST', 'http://localhost/simpleton/index.php/main/ajax/upload_excel_karyawan', true);
            }
            // xhr.open('POST', 'http://localhost/simpleton/index.php/main/ajax/upload_excel', true);
            xhr.send(data);
            xhr.onload = function () {
                //get response and show the uploading status
                var response = JSON.parse(xhr.responseText);
                if (xhr.status === 200 && response.status == 'ok') {
                    if(part=="stok"){
                        $("#dropBox").html("File has been uploaded successfully. Parsing is in progress.");
                    }else{
                        $("#dropBox1").html("File has been uploaded successfully. Parsing is in progress.");
                    }
                    // alert(file.name);
                    parse(part);
                } else if (response.status == 'type_err') {
                    if (part == "stok") { 
                        $("#dropBox").html("Please choose another file. Click to upload another.");
                    } else { 
                        $("#dropBox1").html("Please choose another file. Click to upload another.");
                    }

                } else {
                    if (part == "stok") {
                        $("#dropBox").html("Some problem occured, please try again.");
                    } else { 
                        $("#dropBox1").html("Some problem occured, please try again.");
                    }

                }
            };
        }
    }

    $("#stok").change(function(e){
        fileUpload(e,'stok');
    });

    $("#karyawan").change(function (e) {
        fileUpload(e, 'karyawan');
    });

    // $("#karyawan").click(function (e) {
    //     alert("asdf");
    // });

});