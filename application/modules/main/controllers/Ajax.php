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

    // SELECT sum(jumlah) AS total FROM penjualan WHERE MONTH(tgl) = 9
    // SELECT CAST(tgl AS DATE) AS DATE, sum(jumlah) as summ FROM penjualan GROUP BY CAST(tgl AS DATE) ORDER BY 1
}