<?php
/**
 * Verifikasi cepat halaman kasir (penjualan) merender elemen baru di modal.
 * Jalankan: php tests/check_penjualan_page.php
 *
 * Catatan: endpoint ini butuh login, jadi tes ini melaporkan apa yang benar-benar
 * dikembalikan server (mis. redirect ke login) tanpa menganggapnya kegagalan.
 */

$url = 'http://localhost/simpleton/index.php/main/penjualan';
$context = stream_context_create(array(
    'http' => array('timeout' => 10, 'ignore_errors' => true),
));

$html = @file_get_contents($url, FALSE, $context);

if ($html === FALSE) {
    echo "SKIP  Tidak dapat menghubungi $url (server tidak berjalan?).\n";
    exit(0);
}

echo 'Panjang respons: '.strlen($html)." byte\n";

$response_headers = isset($http_response_header) ? implode("\n", $http_response_header) : '';
$is_login = (strpos($html, 'Login untuk memulai') !== FALSE) ||
    (strpos($html, 'auth/login') !== FALSE) ||
    preg_match('/^Refresh:\s*0;url=.*auth\/login/im', $response_headers);

if ($is_login) {
    echo "SKIP  Halaman mengalihkan ke login (perlu sesi admin untuk melihat modal).\n";
    echo "      Login sebagai admin lalu buka /index.php/main/penjualan untuk verifikasi visual.\n";
    exit(0);
}

$expected = array(
    'checkout-metode-display',
    'check-voucher',
    'payment-bayar-display',
    'payment-diskon-display',
    'kode-konfirmasi',
    'noncash-payment-fields',
    'cash-payment-fields',
    'metode_pembayaran',
    'checkout-voucher',
    'eligible-voucher-list',
);

$missing = 0;
foreach ($expected as $id) {
    $found = (strpos($html, $id) !== FALSE);
    echo ($found ? 'FOUND   ' : 'MISSING ').$id."\n";
    if ( ! $found) {
        $missing++;
    }
}

echo "\n".($missing === 0 ? 'OK: semua elemen modal ada.' : "PERINGATAN: $missing elemen tidak ditemukan.")."\n";
exit($missing === 0 ? 0 : 1);
