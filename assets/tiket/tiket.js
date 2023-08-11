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
                    'Tanggal<br>Keberangkatan',
                    'Waktu<br>Keberangkatan',
                    'Tanggal<br>Kedatangan',
                    'Waktu<br>Kedatangan',
                    'Flight Number',
                ],

                colWidths: [150, 150, 100, 150, 200, 250, 100, 150],
                columns: [
                    // { type: 'autocomplete', url: BASE_URL+'station/ajax/' + $("#pabrik").val() },
                    { type: 'text' },
                    { type: 'calendar', options: { format:'DD/MM/YYYY', time:0 } }, 
                    { type: 'number', mask: '##:##' },
                    { type: 'calendar', options: { format:'DD/MM/YYYY', time:0 } }, 
                    { type: 'number', mask: '##:##' },
                    { type: 'text' },
                    { type: 'text' },
                ]
            });

            var list = ['Bawean (BXW)', 'Bandung (BDO)', 'Kabupaten Banyuwangi (BWX)', 'Tasikmalaya (TSY)', 'Cirebon (CBN)', 'Cilacap (CXP)', 'Kabupaten Kepulauan Seribu (PPJ)', 'Jakarta (HLP)', 'Jakarta,Tangerang (CGK)', 'Tangerang Selatan (PCB)', 'Kabupaten Tangerang (BTO)', 'Kabupaten Majalengka (KJT)', 'Kabupaten Pandeglang (–)', 'Jember (JBB)', 'Malang (MLG)', 'Semarang (SRG)', 'Surabaya (SUB)', 'Surakarta,Kabupaten Boyolali (SOC)', 'Yogyakarta,Kabupaten Sleman (JOG)', 'Yogyakarta,Kabupaten Kulon Progo (YIA)', 'Sumatra ()', 'Kabupaten Aceh Tenggara ()', 'Banda Aceh (BTJ)', 'Bandar Lampung (TKG)', 'Batam (BTH)', 'Kepulauan Batu (LSE)', 'Bengkulu (BKS)', 'Dumai (DUM)', 'Gunung Sitoli (GNS)', 'Kota Jambi (DJB)', 'Lubuklinggau (LLG)', 'Medan,Kabupaten Deli Serdang (KNO (Sebelumnya: MES))', 'Medan (MES)', 'Padang (PDG)', 'Palembang (PLM)', 'Pangkal Pinang (PGK)', 'Kabupaten Simalungun,Kabupaten Toba Samosir (SIW)', 'Pekanbaru (PKU)', 'Ranai (NTX)', 'Sibolga (FLZ)', 'Siborong-Borong (DTB)', 'Sinabang (SNX)', 'Sipora (RKI)', 'Suka Makmue (MEQ)', 'Takengon (TXE)', 'Tanjung Pandan (TJQ)', 'Tanjung Pinang (TNJ)', 'Balikpapan (BPN)', 'Banjarmasin (BDJ)', 'Bandar Udara Gusti Syamsir Alam (KBU)', 'Tanjung Redep,Berau (BEJ)', 'Bontang (BXT)', 'Kabupaten Mahakam Ulu (DTD)', 'Ketapang (KTG)', 'Long Apung (LPU)', 'Long Bawan (LBW)', 'Nunukan (NNX)', 'Palangkaraya (PKY)', 'Pangkalanbun (PKN)', 'Pontianak (PNK)', 'Putussibau (PSU)', 'Samarinda (AAP)', 'Sampit (SMQ)', 'Sintang (SQG)', 'Tanjung Selor (TJS)', 'Tarakan (TRK)', 'Bau-Bau (BUW)', 'Kota Gorontalo (GTO)', 'Kendari (KDI)', 'Kabupaten Kolaka (PUM)', 'Makassar (UPG)', 'Mamuju (MJU)', 'Manado (MDC)', 'Masamba (MXB)', 'Melonguane (MNA)', 'Pulau Sangihe (NAH)', 'Morowali (MOH[13])', 'Palu (PLW)', 'Poso (PSJ)', 'Kabupaten Luwu (LLO)', 'Kabupaten Selayar (KSR)', 'Kabupaten Tojo Una-Una (AMP)', 'Nusa Tenggara ()', 'Atambua (ABU)', 'Bima (BMU)', 'Denpasar (DPS)', 'Ende (ENE)', 'Kupang (KOE)', 'Labuan Bajo (LBJ)', 'Mataram (LOP)', 'Mataram (LOP)', 'Maumere (MOF)', 'Pulau Savu (SAU)', 'Sumbawa Besar (SWQ)', 'Waikabubak (TMC)', 'Waingapu (WGP)', 'Pulau Maluku ()', 'Amahai (AHI)', 'Ambon (AMQ)', 'Banda (NDA)', 'Benjina (BJK)', 'Buli (WUB)', 'Dobo (DOB)', 'Galela (GLX)', 'Kao (KAZ[16])', 'Labuha (LAH[16])', 'Tual (LUV)', 'Pulau Moa (JIO)', 'Pulau Morotai (OTI)', 'Namlea (NAM)', 'Namrole (NRE)', 'Saumlaki (SXK)', 'Ternate (TTE)', 'Anggi (AGD)', 'Ayawasi (AYX)', 'Babo (BXB)', 'Biak (BIK)', 'Bintuni (NTI[19])', 'Fakfak (FKQ)', 'Jayapura (DJJ)', 'Kaimana (KNG)', 'Manokwari (MKW)', 'Merauke (MKQ)', 'Kabupaten Nabire (NBX)', 'Noemfoor,Pulau Schouten (FOO)', 'Oksibil (ORG)', 'Sorong (SOQ)', 'Timika (TIM)', 'Wasior (WSR[20])', 'Wamena,Kabupaten Puncak Jaya (WMX)'];
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
                    {
                        type: 'dropdown',
                        source: list
                    },
                    {
                        type: 'dropdown',
                        source: list
                    },
                    {
                        type: 'dropdown',
                        source: ['Garuda Indonesia', 'Citilink', 'Sriwijaya Air', 'NAM Air', 'Batik Air', 'Lion Air', 'Wings Air', 'Trigana', 'Trans Nusa', ]
                    },
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

    function cetak_tiket(bookcode){
        var newWindow = window.open('http://localhost/simpleton/index.php/main/tiket/cetak/'+bookcode, 'targetWindow', 'toolbar=no,location = no,status = no, menubar = no, scrollbars = yes, resizable = yes, width = 1024, height = 500');
    }

    $("#cetak").click(function () {
        var data_a = $('#my-spreadsheet').jexcel('getData');
        // var data_b = $('#my-spreadsheet2').jexcel('getData');
        // var data_c = $('#daftar-penumpang').jexcel('getData');

        // console.log(data_a);
        // console.log(data_b);
        // console.log(data_c);

        var kodebooking = data_a[0][0];
        cetak_tiket(kodebooking);
    });

    $(document).bind('keydown', function (e) {
        var unicode = e.keyCode;
        if (unicode == 120) {
            var data_a = $('#my-spreadsheet').jexcel('getData');
            var kodebooking = data_a[0][0];
            cetak_tiket(kodebooking);
        }
    });


    init();
    refresh();
    refresh_penumpang();

    


});
