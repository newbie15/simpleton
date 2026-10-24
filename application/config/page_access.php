<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['pages'] = array(
	'member_portal' => array('label' => 'Beranda Anggota', 'uri' => 'main/member'),
	'penjualan' => array('label' => 'Penjualan', 'uri' => 'main/penjualan', 'aliases' => array('main/examples/penjualan')),
	'tiket' => array('label' => 'Tiket Travel', 'uri' => 'main/tiket'),
	'stok' => array('label' => 'Stok', 'uri' => 'main/stok', 'aliases' => array('main/examples/stok')),
	'stock_opname' => array('label' => 'Stok Opname', 'uri' => 'main/stock_opname'),
	'purchasing' => array('label' => 'Purchasing', 'uri' => 'main/purchasing'),
	'product_conversion' => array('label' => 'Konversi Kemasan', 'uri' => 'main/product_conversion'),
	'vouchers' => array('label' => 'Voucher', 'uri' => 'main/voucher'),
	'sales_return' => array('label' => 'Retur Penjualan', 'uri' => 'main/sales_return'),
	'supplier_return' => array('label' => 'Retur Supplier', 'uri' => 'main/supplier_return'),
	'accounting' => array('label' => 'Accounting', 'uri' => 'main/accounting'),
	'petty_cash' => array('label' => 'Kas & Petty Cash', 'uri' => 'main/petty_cash'),
	'suppliers' => array('label' => 'Supplier', 'uri' => 'main/purchasing/suppliers'),
	'users' => array('label' => 'Manajemen User', 'uri' => 'auth/index'),
	'karyawan' => array('label' => 'Karyawan', 'uri' => 'main/karyawan', 'aliases' => array('main/examples/karyawan')),
	'kartu' => array('label' => 'Kartu Anggota', 'uri' => 'main/kartu', 'aliases' => array('main/examples/kartu')),
	'kamera' => array('label' => 'Foto Anggota', 'uri' => 'main/kamera'),
	'update_data' => array('label' => 'Update Data', 'uri' => 'main/data_update', 'aliases' => array('main/examples/update_data')),
	'rekap_penjualan' => array('label' => 'Rekap Penjualan', 'uri' => 'main/rekap_penjualan', 'aliases' => array('main/examples/rekap_penjualan')),
	'bank_reconciliation' => array('label' => 'Rekonsiliasi Bank', 'uri' => 'main/bank_reconciliation'),
);
