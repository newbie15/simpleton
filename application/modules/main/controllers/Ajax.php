<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Ajax extends CI_Controller {
    function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->helper('url');
        
        // $this->load->library('grocery_CRUD');
    }

    public function index()
    {
        // // $this->load->view('dashboard.php');
        // $this->load->view('d-head.php');
        // $this->load->view('d-content.php');
        // $this->load->view('d-script.php');
        // $this->load->view('d-footer.php');
        
        // echo "<h1>Welcome to the world of Codeigniter</h1>";//Just an example to ensure that we get into the function
        // die();
    }
    
    public function barcode($kode)
    {
        $this->db->select('*');
        $this->db->from('stok');
        $this->db->where('barcode', $kode); 
        
        $query = $this->db->get();

        foreach ($query->result() as $row)
        {
            echo $row->nama;
            echo ":";
            echo $row->harga;
            echo ":";
            echo $row->ppn;
        }
    }

    public function kartu($kode)
    {
        $this->db->select('*');
        $this->db->from('karyawan');
        $this->db->where('npk', $kode); 
        
        $query = $this->db->get();

        foreach ($query->result() as $row)
        {
            echo $row->nama;
            echo ":";
            echo $row->bagian;
        }
    }

    public function karyawan($npk)
    {
        $this->db->select('*');
        $this->db->from('karyawan');
        $this->db->where('npk', $npk); 
        
        $query = $this->db->get();

        foreach ($query->result() as $row)
        {
            echo $row->nama;
            echo ":";
            echo $row->bagian;
            echo ":";
            echo $row->limit_belanja;            
        }
    }

    public function karyawan_list()
    {
        $this->db->select('concat(nama,"-",npk) as nama');
        $this->db->from('karyawan');
        $query = $this->db->get();
        echo(json_encode($query->result()));
    }

    public function kodebooking_list()
    {
        $this->db->select('concat(maskapai,"-",kode_booking) as nama');
        $this->db->from('penjualan_tiket_pesawat');
        $query = $this->db->get();
        echo(json_encode($query->result()));
    }
    
    public function produk_list()
    {
        $this->db->select('concat(nama,"-",barcode) as produk');
        $this->db->from('stok');
        $query = $this->db->get();
        echo(json_encode($query->result()));
    }

    public function upload_excel(){
        if(isset($_POST) == true){
            //generate unique file name
            $fileName = time().'_'.basename($_FILES["file"]["name"]);
            $config['upload_path']          = './assets/uploads/';
            $config['allowed_types']        = 'xls|xlsx|csv|txt';
            $config['file_name']            = 'stok';
            $config['max_size']             = 100000;
            $config['max_width']            = 1024;
            $config['max_height']           = 768;

            $this->load->library('upload', $config);

            if ( ! $this->upload->do_upload('file')){
                $error = array('error' => $this->upload->display_errors());
                $response['status'] = $error;
            }else{
                $data = array('upload_data' => $this->upload->data());
                $response['status'] = "ok";
            }
            
            //render response data in JSON format
            echo json_encode($response);
        }
    }
    public function jadwal_belanja(){
        $npk = $this->uri->segment(4);

        $this->db->select('join_date');		
        $this->db->from('karyawan');		
        $this->db->where('npk', $npk);

        $tgl = null;
        $query = $this->db->get();		
        foreach ($query->result() as $row)		
        {		
            $tgl = $row->join_date;		
        }
        
        $now = time(); // or your date as well
        $your_date = strtotime($tgl);
        $datediff = $now - $your_date;

        $days = round($datediff / (60 * 60 * 24));
        if($days > 91){
            echo "ok";
        }else{
            if(date('j')<21){
                echo "ok";
            }else{
                echo "nok";
            }
        }

    }

    public function limit_belanja(){
        $tahun = date('y');		
        $npk = $this->uri->segment(4);		
        // $bulan = date('-m-');
        $bulan = date('m');		
        $tgl = date('d');
        $startdate = null;

        if($tgl>21){
            $startdate = date('Y-m-')."21 00:00:00";
        }else{
            $lastmonth = null;
            $y = null;

            if(date('n')==1){
                $lastmonth = 12;
                $y = date('Y')-1;
            }else{
                $lastmonth = date('n')-1;
            }

            if($lastmonth<10){
                $lastmonth = "0".$lastmonth;
            }

            if($y==null){
                $startdate = date('Y-').$lastmonth."-21 00:00:00";
            }else{
                $startdate = $y.'-'.$lastmonth."-16 00:00:00";
            }
        }

        $this->db->select('SUM(jumlah) as jumlah');		
        $this->db->from('penjualan');		
        $this->db->where('id_karyawan', $npk);
        $this->db->where('tgl >', $startdate);
        // $this->db->where('tgl <', $npk);
        // $this->db->like('tgl', $bulan); 		
        $query = $this->db->get();		
        foreach ($query->result() as $row)		
        {		
            echo $row->jumlah;		
        }		
    }

    public function upload_excel_karyawan(){
        if(isset($_POST) == true){
            //generate unique file name
            $fileName = time().'_'.basename($_FILES["file"]["name"]);
            $config['upload_path']          = './assets/uploads/';
            $config['allowed_types']        = 'xls|xlsx|csv|txt';
            $config['file_name']            = 'karyawan';
            $config['max_size']             = 100000;
            $config['max_width']            = 1024;
            $config['max_height']           = 768;

            $this->load->library('upload', $config);

            if ( ! $this->upload->do_upload('file')){
                $error = array('error' => $this->upload->display_errors());
                $response['status'] = $error;
            }else{
                $data = array('upload_data' => $this->upload->data());
                $response['status'] = "ok";
            }
            
            //render response data in JSON format
            echo json_encode($response);
        }
    }


    public function checkout()
    {
        // $this->db->select('penjualan.qty,stok.nama,penjualan.harga,penjualan.jumlah');
        // $this->db->from('penjualan,stok');
        // $this->db->where('penjualan.kode', 'stok.barcode'); 
        // $this->db->where('penjualan.nota', $nota); 
        $nota = $_REQUEST['no'];
        $query = $this->db->query("
            SELECT penjualan.qty,stok.nama,penjualan.harga,penjualan.jumlah FROM penjualan,stok
            where penjualan.kode = stok.barcode and
            penjualan.nota = '$nota' order by penjualan.id asc
        ");

        foreach ($query->result() as $row)
        {
            $nama = substr($row->nama,0,7)."...";
            $harga = ($row->harga)/1000;
            $jumlah = number_format($row->jumlah,0,",",".");
            echo "<tr>
        <td style=\"vertical-align: top;\">$row->qty</td>
        <td style=\"vertical-align: top;\">$nama</td>
        <td style=\"vertical-align: top; text-align: right;\">".$harga."K</td>\
        <td style=\"vertical-align: top; text-align: right;\">$jumlah</td>\
        </tr>";
            // // echo $row->nama;
            // // echo ":";
            // // echo $row->harga;
            // // echo "<br>";
            // print_r($row);
        }

    }

    public function data_harian(){
        $tgl = $this->uri->segment(4);

        $query = $this->db->query("
            SELECT penjualan.nota,karyawan.npk,karyawan.nama,karyawan.bagian,stok.barcode,stok.nama as item,penjualan.harga,penjualan.qty,penjualan.jumlah
            FROM penjualan,stok,karyawan
            WHERE penjualan.kode = stok.barcode AND
            karyawan.npk = penjualan.id_karyawan AND
            penjualan.tgl LIKE '%$tgl%' 
            ORDER BY penjualan.id ASC
        ");

        if($this->uri->segment(5)=="d"){
            header('Content-Type: aplication/vnd-ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename=data_harian_'.$tgl.'.xls');
            echo "nota\tnpk\tnama\tbagian\tbarcode\titem\tharga\tqty\tjumlah\n";

            foreach ($query->result() as $row)
            {
                echo $row->nota; echo "\t";
                echo $row->npk; echo "\t";
                echo $row->nama; echo "\t";
                echo $row->bagian; echo "\t";
                echo $row->barcode; echo "\t";
                echo $row->item; echo "\t";
                echo $row->harga; echo "\t";
                echo $row->qty; echo "\t";
                echo $row->jumlah; echo "\n";
            }
        }else{
            echo "nota,npk,nama,bagian,barcode,item,harga,qty,jumlah\n";

            foreach ($query->result() as $row)
            {
                echo $row->nota; echo ",";
                echo $row->npk; echo ",";
                echo $row->nama; echo ",";
                echo $row->bagian; echo ",";
                echo $row->barcode; echo ",";
                echo $row->item; echo ",";
                echo $row->harga; echo ",";
                echo $row->qty; echo ",";
                echo $row->jumlah; echo "\n";
            }
        }
    }

    public function data_perorangan(){
        $tgl = $this->uri->segment(4);
        $npk = $this->uri->segment(5);

        // $year = date('Y');

        $tgl = explode("-",$tgl);
        if($tgl[1]=="xx"){
            $qtgl = $tgl[0]."-";
        }else{
            $qtgl = $tgl[0]."-".$tgl[1];
        }
        $query = $this->db->query("
            SELECT penjualan.tgl,penjualan.nota,karyawan.nama,sum(penjualan.jumlah) as total_belanja
            FROM penjualan,karyawan
            WHERE 
            karyawan.npk = penjualan.id_karyawan AND
            penjualan.tgl LIKE '%$qtgl%'
            GROUP BY penjualan.nota 
        ");

// echo "
//             SELECT penjualan.tgl,penjualan.nota,karyawan.nama,sum(penjualan.jumlah) as total_belanja
//             FROM penjualan,karyawan
//             WHERE 
//             karyawan.npk = penjualan.id_karyawan AND
//             penjualan.tgl LIKE '%$qtgl%'
//             GROUP BY penjualan.nota 
// ";

        $total_belanjaan = 0;

        if($this->uri->segment(6)=="d"){
            header('Content-Type: aplication/vnd-ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename=data_perorangan_'.$tgl.'.xls');
            echo "tanggal\tnota\tnama\ttotal belanja\n";

            foreach ($query->result() as $row)
            {
                echo $row->tgl; echo "\t";
                echo $row->nota; echo "\t";
                echo $row->nama; echo "\t";
                echo $row->total_belanja; echo "\n";
                $total_belanjaan += $row->total_belanja;
            }
            echo "\n";
            echo "\t\tTotal Bulan Ini\t".$total_belanjaan;

        }else{
            echo "tanggal,nota,nama,total belanja\n";

            foreach ($query->result() as $row)
            {
                echo $row->tgl; echo ",";
                echo $row->nota; echo ",";
                echo $row->nama; echo ",";
                echo $row->total_belanja; echo "\n";
                $total_belanjaan += $row->total_belanja;
            }
            echo "\n";
            echo ",,Total Semua,".$total_belanjaan;

        }
    }

    public function data_bulanan(){
        $tgl = $this->uri->segment(4);

        $query = $this->db->query("
            SELECT karyawan.npk,karyawan.nama,karyawan.bagian,sum(penjualan.jumlah) as jumlah
            FROM penjualan,karyawan
            WHERE
            karyawan.npk = penjualan.id_karyawan
            GROUP BY karyawan.npk
            ORDER BY karyawan.bagian
        ");

        // penjualan.tgl LIKE '%$tgl%' 


        if($this->uri->segment(5)=="d"){
            header('Content-Type: aplication/vnd-ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename=data_harian_'.$tgl.'.xls');
            echo "nota\tnpk\tnama\tbagian\tbarcode\titem\tharga\tqty\tjumlah\n";

            foreach ($query->result() as $row)
            {
                echo $row->nota; echo "\t";
                echo $row->npk; echo "\t";
                echo $row->nama; echo "\t";
                echo $row->bagian; echo "\t";
                echo $row->barcode; echo "\t";
                echo $row->item; echo "\t";
                echo $row->harga; echo "\t";
                echo $row->qty; echo "\t";
                echo $row->jumlah; echo "\n";
            }
        }else{
            echo "npk,nama,bagian,jumlah\n";

            foreach ($query->result() as $row)
            {
                // echo $row->nota; echo ",";
                echo $row->npk; echo ",";
                echo $row->nama; echo ",";
                echo $row->bagian; echo ",";
                // echo $row->barcode; echo ",";
                // echo $row->item; echo ",";
                // echo $row->harga; echo ",";
                // echo $row->qty; echo ",";
                echo $row->jumlah; echo "\n";
            }
        }
    }

    public function rekap_harian(){
        $tgl = date('Y-m-d');
        $query = $this->db->query("
            SELECT penjualan.nota,karyawan.npk,karyawan.nama,karyawan.bagian,stok.barcode,stok.nama as item,penjualan.harga,penjualan.qty,penjualan.jumlah
            FROM penjualan,stok,karyawan
            WHERE penjualan.kode = stok.barcode AND
            karyawan.npk = penjualan.id_karyawan AND
            penjualan.tgl LIKE '%$tgl%' 
            ORDER BY penjualan.id ASC
        ");

        // echo "
        //     SELECT penjualan.nota,karyawan.npk,karyawan.nama,karyawan.bagian,stok.barcode,stok.nama,penjualan.harga,penjualan.qty,penjualan.jumlah
        //     FROM penjualan,stok,karyawan
        //     WHERE penjualan.kode = stok.barcode AND
        //     karyawan.npk = penjualan.id_karyawan AND
        //     penjualan.tgl LIKE '%tgl%' 
        //     ORDER BY penjualan.id ASC

        // ";
        foreach ($query->result() as $row)
        {
        //     $nama = substr($row->nama,0,7)."...";
        //     $harga = ($row->harga)/1000;
        //     $jumlah = number_format($row->jumlah,0,",",".");
            echo "
        <tr>
            <td>$row->nota</td>
            <td>$row->npk</td>
            <td>$row->nama</td>
            <td>$row->bagian</td>
            <td>$row->barcode</td>
            <td>$row->item</td>
            <td>$row->harga</td>
            <td>$row->qty</td>
            <td>$row->jumlah</td>
        </tr>";
            // echo $row->nama;
            // echo ":";
            // echo $row->harga;
            // echo "<br>";
            // print_r($row);
        }
    }

    public function rekap_perorangan(){
        $tgl = $this->uri->segment(4);
        $npk = $this->uri->segment(5);

        $startdate = null;
        $startm = null;
        $starty = null;
        $stopdate = null;
        $stopy = null;

        if($tgl="1"){
            $y = date('Y');
            $starty = $y - 1;
            $stopy = $y;
            $startm = '12';
            $startdate = $starty."-".$startm."-21";
            $stopdate = $stopy."-".$tgl."-20";
        }else{
            $y = date('Y');
            $starty = $y;
            $stopy = $y;
            $startm = $tgl - 1;
            $startdate = $starty."-".$startm."-21";
            $stopdate = $stopy."-".$tgl."-20";
        }

        $query = $this->db->query("
            SELECT penjualan.nota,karyawan.npk,karyawan.nama,karyawan.bagian,stok.barcode,stok.nama as item,penjualan.harga,penjualan.qty,penjualan.jumlah
            FROM penjualan,stok,karyawan
            WHERE penjualan.kode = stok.barcode AND
            karyawan.npk = penjualan.id_karyawan AND
            karyawan.npk = '$npk' AND
            penjualan.tgl >= '%$startdate%' AND 
            penjualan.tgl <= '%$stopdate%' 
            ORDER BY penjualan.id ASC
        ");

        if($this->uri->segment(6)=="d"){
            header('Content-Type: aplication/vnd-ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename=data_harian_'.$tgl.'.xls');
            echo "nota\tnpk\tnama\tbagian\tbarcode\titem\tharga\tqty\tjumlah\n";

            foreach ($query->result() as $row)
            {
                echo $row->nota; echo "\t";
                echo $row->npk; echo "\t";
                echo $row->nama; echo "\t";
                echo $row->bagian; echo "\t";
                echo $row->barcode; echo "\t";
                echo $row->item; echo "\t";
                echo $row->harga; echo "\t";
                echo $row->qty; echo "\t";
                echo $row->jumlah; echo "\n";
            }
        }else{
            echo "nota,npk,nama,bagian,barcode,item,harga,qty,jumlah\n";

            foreach ($query->result() as $row)
            {
                echo $row->nota; echo ",";
                echo $row->npk; echo ",";
                echo $row->nama; echo ",";
                echo $row->bagian; echo ",";
                echo $row->barcode; echo ",";
                echo $row->item; echo ",";
                echo $row->harga; echo ",";
                echo $row->qty; echo ",";
                echo $row->jumlah; echo "\n";
            }
        }
    }

    // SELECT sum(jumlah) AS total FROM penjualan WHERE MONTH(tgl) = 9
    // SELECT CAST(tgl AS DATE) AS DATE, sum(jumlah) as summ FROM penjualan GROUP BY CAST(tgl AS DATE) ORDER BY 1
}