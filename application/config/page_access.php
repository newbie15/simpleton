<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['pages'] = array(
	'member_portal' => array('label' => 'Beranda Anggota', 'uri' => 'main/member'),
	'penjualan' => array('label' => 'Penjualan', 'uri' => 'main/examples/penjualan'),
	'tiket' => array('label' => 'Tiket Travel', 'uri' => 'main/tiket'),
	'stok' => array('label' => 'Stok', 'uri' => 'main/examples/stok'),
	'stock_opname' => array('label' => 'Stok Opname', 'uri' => 'main/stock_opname'),
	'purchasing' => array('label' => 'Purchasing', 'uri' => 'main/purchasing'),
	'product_conversion' => array('label' => 'Konversi Kemasan', 'uri' => 'main/product_conversion'),
	'sales_return' => array('label' => 'Retur Penjualan', 'uri' => 'main/sales_return'),
	'supplier_return' => array('label' => 'Retur Supplier', 'uri' => 'main/supplier_return'),
	'accounting' => array('label' => 'Accounting', 'uri' => 'main/accounting'),
	'suppliers' => array('label' => 'Supplier', 'uri' => 'main/purchasing/suppliers'),
	'users' => array('label' => 'Manajemen User', 'uri' => 'auth/index'),
	'karyawan' => array('label' => 'Karyawan', 'uri' => 'main/examples/karyawan'),
	'kartu' => array('label' => 'Kartu Anggota', 'uri' => 'main/examples/kartu'),
	'kamera' => array('label' => 'Foto Anggota', 'uri' => 'main/kamera'),
	'update_data' => array('label' => 'Update Data', 'uri' => 'main/examples/update_data'),
	'rekap_penjualan' => array('label' => 'Rekap Penjualan', 'uri' => 'main/examples/rekap_penjualan'),
	'bank_reconciliation' => array('label' => 'Rekonsiliasi Bank', 'uri' => 'main/bank_reconciliation'),
);
