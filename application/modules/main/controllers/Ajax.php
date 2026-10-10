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
        if (!is_string($npk) || $npk === '' || strlen($npk) > 10) {
            show_error('NPK tidak valid.', 400);
        }

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
        $npk = $this->uri->segment(4);
        if (!is_string($npk) || $npk === '' || strlen($npk) > 10) {
            show_error('NPK tidak valid.', 400);
        }

        $tahun = date('y');		
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
        $nota = $this->input->post('no', TRUE);
        $query = $this->db->query("
            SELECT penjualan.qty,stok.nama,penjualan.harga,penjualan.jumlah FROM penjualan,stok
            where penjualan.kode = stok.barcode and
            penjualan.nota = ? order by penjualan.id asc
        ", array($nota));

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

    public function data_harian()
    {
        $date = $this->uri->segment(4);
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
            date('Y-m-d', strtotime($date)) !== $date) {
            show_error('Tanggal laporan tidak valid.', 400);
        }

        $start = $date.' 00:00:00';
        $end = date('Y-m-d', strtotime($date.' +1 day')).' 00:00:00';
        $rows = $this->db->query("
            SELECT sale.nota, sale.id_karyawan AS npk, employee.nama, employee.bagian,
                sale.kode AS barcode, product.nama AS item, sale.harga, sale.qty, sale.jumlah,
                sale.metode_pembayaran
            FROM penjualan sale
            LEFT JOIN stok product ON product.barcode = sale.kode
            LEFT JOIN karyawan employee ON employee.npk = sale.id_karyawan
            WHERE sale.tgl >= ? AND sale.tgl < ?
            ORDER BY sale.tgl ASC, sale.id ASC
        ", array($start, $end))->result_array();
        $this->_sales_csv(
            array('nota', 'npk', 'nama', 'bagian', 'barcode', 'item', 'harga', 'qty', 'jumlah',
                'metode_pembayaran'),
            $rows,
            'data_harian_'.$date.'.csv',
            $this->uri->segment(5) === 'd'
        );
    }

    public function data_perorangan()
    {
        $period = $this->uri->segment(4);
        $npk = $this->uri->segment(5);
        $range = $this->_sales_month_range($period);
        if ($range === FALSE) {
            show_error('Periode laporan tidak valid.', 400);
        }
        if (!is_string($npk) || $npk === '' || strlen($npk) > 10) {
            $this->_sales_csv(array('tanggal', 'nota', 'npk', 'nama', 'total_belanja'), array(), 'data_perorangan.csv', $this->uri->segment(6) === 'd');
            return;
        }

        $rows = $this->db->query("
            SELECT DATE(sale.tgl) AS tanggal, sale.nota, sale.id_karyawan AS npk,
                employee.nama, SUM(sale.jumlah) AS total_belanja
            FROM penjualan sale
            LEFT JOIN karyawan employee ON employee.npk = sale.id_karyawan
            WHERE sale.id_karyawan = ? AND sale.tgl >= ? AND sale.tgl < ?
            GROUP BY DATE(sale.tgl), sale.nota, sale.id_karyawan, employee.nama
            ORDER BY MIN(sale.tgl) ASC, sale.nota ASC
        ", array($npk, $range['start'], $range['end']))->result_array();
        $this->_sales_csv(
            array('tanggal', 'nota', 'npk', 'nama', 'total_belanja'),
            $rows,
            'data_perorangan_'.$period.'_'.$npk.'.csv',
            $this->uri->segment(6) === 'd'
        );
    }

    public function data_bulanan()
    {
        $period = $this->uri->segment(4);
        $range = $this->_sales_month_range($period);
        if ($range === FALSE) {
            show_error('Periode laporan tidak valid.', 400);
        }

        $rows = $this->db->query("
            SELECT sale.id_karyawan AS npk, employee.nama, employee.bagian, SUM(sale.jumlah) AS jumlah
            FROM penjualan sale
            LEFT JOIN karyawan employee ON employee.npk = sale.id_karyawan
            WHERE sale.tgl >= ? AND sale.tgl < ?
            GROUP BY sale.id_karyawan, employee.nama, employee.bagian
            ORDER BY employee.bagian ASC, employee.nama ASC
        ", array($range['start'], $range['end']))->result_array();
        $this->_sales_csv(
            array('npk', 'nama', 'bagian', 'jumlah'),
            $rows,
            'data_bulanan_'.$period.'.csv',
            $this->uri->segment(5) === 'd'
        );
    }

    public function data_tahunan()
    {
        $year = $this->uri->segment(4);
        if (!is_string($year) || !preg_match('/^\d{4}$/', $year) ||
            (int) $year < 2000 || (int) $year > 2100) {
            show_error('Tahun laporan tidak valid.', 400);
        }

        $start = $year.'-01-01 00:00:00';
        $end = ((int) $year + 1).'-01-01 00:00:00';
        $rows = $this->db->query("
            SELECT DATE_FORMAT(sale.tgl, '%Y-%m') AS periode,
                COUNT(DISTINCT CONCAT(DATE(sale.tgl), ':', sale.nota)) AS jumlah_transaksi,
                SUM(sale.qty) AS qty_terjual,
                SUM(sale.jumlah) AS jumlah_penjualan
            FROM penjualan sale
            WHERE sale.tgl >= ? AND sale.tgl < ?
            GROUP BY DATE_FORMAT(sale.tgl, '%Y-%m')
            ORDER BY periode ASC
        ", array($start, $end))->result_array();
        $this->_sales_csv(
            array('periode', 'jumlah_transaksi', 'qty_terjual', 'jumlah_penjualan'),
            $rows,
            'data_tahunan_'.$year.'.csv',
            $this->uri->segment(5) === 'd'
        );
    }

    private function _sales_month_range($period)
    {
        if (!is_string($period) || !preg_match('/^(\d{4})-(\d{2})$/', $period, $matches)) {
            return FALSE;
        }
        $year = (int) $matches[1];
        $month = (int) $matches[2];
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            return FALSE;
        }

        if ($month === 1) {
            $start = sprintf('%04d-12-17 00:00:00', $year - 1);
            $end = sprintf('%04d-01-21 00:00:00', $year);
        } elseif ($month === 12) {
            $start = sprintf('%04d-11-21 00:00:00', $year);
            $end = sprintf('%04d-12-17 00:00:00', $year);
        } else {
            $previous_month = $month - 1;
            $start = sprintf('%04d-%02d-21 00:00:00', $year, $previous_month);
            $end = sprintf('%04d-%02d-21 00:00:00', $year, $month);
        }

        return array('start' => $start, 'end' => $end);
    }

    private function _sales_csv($headers, $rows, $filename, $download)
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            $values = array();
            foreach ($headers as $header) {
                $values[] = isset($row[$header]) ? $row[$header] : '';
            }
            fputcsv($stream, $values);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        $this->output->set_content_type('text/csv', 'utf-8');
        if ($download) {
            $this->output->set_header('Content-Disposition: attachment; filename="'.$filename.'"');
        }
        $this->output->set_output($csv);
    }

    public function rekap_harian(){
        date_default_timezone_set('Asia/Jakarta');
        $tgl = date('Y-m-d');
        $start = $tgl.' 00:00:00';
        $end = date('Y-m-d', strtotime($tgl.' +1 day')).' 00:00:00';
        $query = $this->db->query("
            SELECT penjualan.nota,karyawan.npk,karyawan.nama,karyawan.bagian,stok.barcode,stok.nama as item,penjualan.harga,penjualan.qty,penjualan.jumlah
            FROM penjualan,stok,karyawan
            WHERE penjualan.kode = stok.barcode AND
            karyawan.npk = penjualan.id_karyawan AND
            penjualan.tgl >= ? AND penjualan.tgl < ?
            ORDER BY penjualan.id ASC
        ", array($start, $end));

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
        $seg_periode = $this->uri->segment(4);
        $npk = $this->uri->segment(5);

        // Periode dipakai untuk rentang tanggal sekaligus nama file unduhan.
        if (!is_string($seg_periode) || !preg_match('/^\d{1,2}$/', $seg_periode)) {
            show_error('Periode laporan tidak valid.', 400);
        }
        $tgl = (int) $seg_periode;
        if ($tgl < 1 || $tgl > 12) {
            show_error('Periode laporan tidak valid.', 400);
        }
        if (!is_string($npk) || $npk === '' || strlen($npk) > 10) {
            show_error('NPK tidak valid.', 400);
        }

        $startdate = null;
        $startm = null;
        $starty = null;
        $stopdate = null;
        $stopy = null;

        if($tgl==1){
            $y = date('Y');
            $starty = $y - 1;
            $stopy = $y;
            $startm = '12';
            $startdate = $starty."-".$startm."-21";
            $stopdate = $stopy."-".sprintf('%02d', $tgl)."-20";
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
            karyawan.npk = ? AND
            penjualan.tgl >= ? AND
            penjualan.tgl <= ?
            ORDER BY penjualan.id ASC
        ", array($npk, $startdate.' 00:00:00', $stopdate.' 23:59:59'));

        $columns = array('nota', 'npk', 'nama', 'bagian', 'barcode', 'item', 'harga', 'qty', 'jumlah');
        $rows = array();
        foreach ($query->result_array() as $row) {
            $rows[] = $row;
        }

        $download = ($this->uri->segment(6) == "d");
        if ($download) {
            header('Content-Type: application/vnd-ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename=data_harian_'.$tgl.'.xls');
            $this->_format_report($columns, $rows, "\t");
        } else {
            $this->_format_report($columns, $rows, ",");
        }
    }

    /**
     * Cetak laporan sebagai baris terpisah dengan pemisah tertentu.
     */
    private function _format_report($columns, $rows, $separator)
    {
        echo implode($separator, $columns)."\n";
        foreach ($rows as $row) {
            $values = array();
            foreach ($columns as $column) {
                $values[] = isset($row[$column]) ? $row[$column] : '';
            }
            echo implode($separator, $values)."\n";
        }
    }

    // SELECT sum(jumlah) AS total FROM penjualan WHERE MONTH(tgl) = 9
    // SELECT CAST(tgl AS DATE) AS DATE, sum(jumlah) as summ FROM penjualan GROUP BY CAST(tgl AS DATE) ORDER BY 1

    public function daftar_kasir(){
        $this->load->library('page_access');
        if (!$this->page_access->can_access('penjualan')) {
            show_error('Anda tidak memiliki akses untuk melihat daftar kasir.', 403);
        }

        $auth_tables = $this->config->item('tables', 'ion_auth');
        $users_table = $auth_tables['users'];
        $users_groups_table = $auth_tables['users_groups'];
        $groups_table = $auth_tables['groups'];
        $columns = array('id', 'username', 'email', 'first_name', 'last_name');
        if ($this->db->field_exists('npk', $users_table)) {
            $columns[] = 'npk';
        }
        $query = $this->db
            ->select('users.'.implode(', users.', $columns), FALSE)
            ->from($users_table.' users')
            ->join($users_groups_table.' users_groups', 'users_groups.user_id = users.id')
            ->join($groups_table.' groups', 'groups.id = users_groups.group_id')
            ->where('users.active', 1)
            ->where('groups.name', 'kasir')
            ->group_by('users.id')
            ->order_by('users.first_name', 'asc')
            ->get();

        echo '<option value="0">===pilih salah satu===</option>';
        foreach ($query->result() as $row) {
            $name = trim($row->first_name . ' ' . $row->last_name);
            if ($name === '') {
                $name = !empty($row->npk) ? $row->npk : $row->username;
            }
            if ($name === '') {
                $name = $row->email;
            }

            echo '<option value="' . (int) $row->id . '">' . html_escape($name) . '</option>';
        }
    }

}