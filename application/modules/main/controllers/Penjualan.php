<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Penjualan extends CI_Controller
{
	public function index()
	{
		$this->load->helper('url');
		$data['css_file'] = array(
			base_url('assets/datatables/css/jquery.dataTables.min.css'),
			base_url('assets/jexcel/css/jquery.jexcel.css'),
			base_url('assets/jexcel/css/jquery.jcalendar.css'),
			base_url('assets/easyautocomplete/easy-autocomplete.min.css')
		);
		$data['js_file'] = array(
			base_url('assets/datatables/js/jquery.dataTables.min.js'),
			base_url('assets/jexcel/js/jquery.jexcel.js'),
			base_url('assets/jexcel/js/jquery.jcalendar.js'),
			base_url('assets/jexcel/js/excel-formula.min.js'),
			base_url('assets/jexcel/js/numeral.min.js'),
			base_url('assets/easyautocomplete/jquery.easy-autocomplete.min.js'),
			base_url('assets/kasir/kasir.js?v=20261024-short-receipt-number')
		);
		$data['m_penjualan'] = TRUE;
		$data['output'] = '
		<main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-4">
		  <br>
          <div class="row">
            <div class="col-sm-4">
              <div class="form-group">
                <label for="npk">NPK / Karyawan</label>
                <input class="form-control" type="text" name="npk" id="npk" placeholder="Cari nama atau masukkan NPK">
              </div>
              <div class="form-group">
                <label for="metode_pembayaran">Metode Pembayaran</label>
                <select class="form-control" id="metode_pembayaran" name="metode_pembayaran">
                  <option value="potong_gaji">Potong gaji</option>
                  <option value="tunai">Tunai / Cash</option>
                  <option value="qris">QRIS</option>
                  <option value="edc">EDC (Debit/Kredit)</option>
                  <option value="transfer">Transfer Bank</option>
                </select>
              </div>
              <div class="form-group">
                <label for="nkasir">Kasir</label>
                <select class="form-control" id="nkasir" name="nkasir">
                  <option value="belum">Memuat daftar kasir...</option>
                </select>
              </div>
            </div>
            <div class="col-sm-4">
              <div style="min-height: 145px; padding-top: 4px;">
                <div><strong id="nkaryawan"></strong></div>
                <div id="akaryawan" class="text-muted"></div>
                <div class="small" style="margin-top: 8px;">Belanja bulan ini: <strong id="limit"></strong></div>
                <div class="small" style="margin-top: 4px;">Limit belanja: <strong id="limit_belanja"></strong></div>
                <div style="margin-top: 14px; font-size: 22px; font-weight: bold;" id="ttx">Total Rp 0</div>
                <div class="checkbox" style="margin-top: 12px;">
                  <label style="font-weight: normal;">
                    <input type="checkbox" id="pajak-active">
                    Aktifkan pajak (PPN)
                  </label>
                </div>
                <span class="help-block" style="margin: 0;">Atur sebelum scan barang.</span>
              </div>
            </div>
            <div class="col-sm-4 text-center">
              <label style="display: block;">Verifikasi karyawan</label>
              <div id="foto-karyawan-placeholder" class="text-muted small" style="width: 150px; height: 190px; margin: 0 auto; border: 1px dashed #aaa; display: flex; align-items: center; justify-content: center; text-align: center;">Foto belum ada</div>
              <img id="foto_karyawan" alt="Foto karyawan" style="display: none; width: 150px; height: 190px; margin: 0 auto; object-fit: cover; border: 1px solid #ddd;">
            </div>
          </div>
          <div class="row">
            <div class="col-sm-9">
              <label for="barcode">Scan / cari barang</label>
              <input class="form-control" type="text" name="barcode" id="barcode" autocomplete="off" placeholder="Scan barcode barang">
            </div>
            <div class="col-sm-3">
              <label>&nbsp;</label>
              <button id="checkout" type="button" class="btn btn-info btn-block">Checkout [ F9 ]</button>
              <button id="clear-cart" type="button" class="btn btn-default btn-block">Kosongkan Keranjang</button>
              <a class="btn btn-link btn-block" target="_blank" href="'.site_url('main/kasir/reprint').'">Cetak ulang nota</a>
            </div>
          </div>
          <br>
          <div style="height:325px;width:100%;overflow:auto;border:1px solid #666;background-color:#ccc;padding:8px;" id="scrll">
            <div id="my"></div>
          </div>
          <div class="modal fade" id="checkout-modal" tabindex="-1" role="dialog" aria-labelledby="checkout-modal-title" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog modal-lg" role="document">
              <div class="modal-content">
                <div class="modal-header">
                  <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                  <h4 class="modal-title" id="checkout-modal-title">Konfirmasi Transaksi</h4>
                </div>
                <div class="modal-body">
                  <p id="checkout-summary"></p>
                  <div id="checkout-retry-notice" class="alert alert-warning" style="display:none;"></div>
                  <div class="row">
                    <div id="checkout-payment-left" class="col-sm-6">
                      <div id="payment-display-section" style="margin-bottom:20px;padding:15px;background-color:#f9f9f9;border-radius:4px;">
                        <div style="background-color:white;padding:12px;border-radius:4px;text-align:center;margin-bottom:12px;">
                          <div style="font-size:12px;color:#666;margin-bottom:5px;">Metode Pembayaran</div>
                          <div id="checkout-metode-display" style="font-size:18px;font-weight:bold;color:#2c3e50;"></div>
                        </div>
                        <div class="form-group" data-checkout-field>
                          <label for="checkout-voucher">Kode Voucher / Gift Card (opsional)</label>
                          <div style="display:flex;gap:8px;">
                            <input class="form-control" type="text" id="checkout-voucher" maxlength="32" autocomplete="off" style="flex:1;">
                            <button type="button" id="check-voucher" class="btn btn-default">Cek</button>
                          </div>
                          <label for="eligible-voucher-list" style="margin-top:10px;">Voucher tersedia untuk NPK ini</label>
                          <select class="form-control" id="eligible-voucher-list"><option value="">Pilih anggota untuk melihat voucher</option></select>
                          <div id="voucher-status" class="help-block" style="margin:6px 0 0;"></div>
                        </div>
                        <div style="background-color:white;padding:14px;border-radius:4px;">
                          <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px;">
                            <span style="font-size:13px;color:#666;">Total Belanja</span>
                            <span id="payment-total-display" style="font-size:20px;font-weight:bold;color:#2c3e50;">Rp 0</span>
                          </div>
                          <div id="payment-diskon-row" style="display:none;justify-content:space-between;align-items:baseline;margin-bottom:8px;">
                            <span style="font-size:13px;color:#666;">Dibayar Voucher / Gift Card</span>
                            <span id="payment-diskon-display" style="font-size:18px;font-weight:bold;color:#c0392b;">- Rp 0</span>
                          </div>
                          <div style="border-top:1px dashed #ccc;margin:10px 0;"></div>
                          <div style="display:flex;justify-content:space-between;align-items:baseline;">
                            <span style="font-size:13px;color:#666;">Sisa Bayar</span>
                            <span id="payment-bayar-display" style="font-size:26px;font-weight:bold;color:#2c3e50;">Rp 0</span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div id="checkout-payment-right" class="col-sm-6">
                      <div class="form-group" id="cash-payment-fields" data-checkout-field style="display:none;">
                        <label for="nominal-bayar">Uang tunai diterima</label>
                        <input class="form-control" type="text" id="nominal-bayar" name="nominal_bayar" maxlength="17" inputmode="numeric" autocomplete="off" placeholder="Contoh: 100.000" style="font-size:20px;height:46px;font-weight:bold;">
                        <div style="margin-top:12px;padding:12px;background-color:#ecf0f1;border-radius:4px;display:flex;justify-content:space-between;align-items:baseline;">
                          <span style="font-size:13px;color:#666;">Kembalian</span>
                          <span id="payment-kembalian-display" style="font-size:24px;font-weight:bold;color:#27ae60;">Rp 0</span>
                        </div>
                        <div id="payment-status-text" class="help-block" style="margin:6px 0 0;"></div>
                      </div>
                      <div class="form-group" id="noncash-payment-fields" data-checkout-field style="display:none;">
                        <label for="kode-konfirmasi" id="kode-konfirmasi-label">Kode konfirmasi</label>
                        <input class="form-control" type="text" id="kode-konfirmasi" name="kode_konfirmasi" maxlength="64" autocomplete="off">
                        <div id="bank-tujuan-wrap" style="display:none;margin-top:12px;">
                          <label for="bank-tujuan">Bank tujuan</label>
                          <input class="form-control" type="text" id="bank-tujuan" name="bank_tujuan" maxlength="64">
                        </div>
                      </div>
                    </div>
                  </div>
                  <input type="hidden" id="nomor-referensi" name="nomor_referensi" value="">
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-default" data-dismiss="modal">Kembali</button>
                  <button type="button" id="confirm-checkout" class="btn btn-primary">Simpan Transaksi</button>
                </div>
              </div>
            </div>
          </div>
        </main>';
		$this->load->view('welcome_message', $data);
	}
}