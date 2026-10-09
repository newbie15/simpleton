<?php
/**
 * Bootstrap untuk pengujian lightweight (tanpa PHPUnit).
 * Mendefinisikan BASEPATH agar controller bisa di-include dan diintrospeksi
 * tanpa menjalankan CodeIgniter penuh.
 */

if ( ! defined('BASEPATH')) {
    define('BASEPATH', dirname(__DIR__).'/system/');
}

if ( ! function_exists('show_error')) {
    function show_error($message, $status_code = 500)
    {
        throw new RuntimeException($message, (int) $status_code);
    }
}

if ( ! function_exists('show_404')) {
    function show_404()
    {
        throw new RuntimeException('404 Not Found', 404);
    }
}

/**
 * Stub tipis CI_Controller agar controller dapat di-load untuk diintrospeksi
 * (memeriksa & memanggil method murni) tanpa menjalankan CodeIgniter penuh.
 */
if ( ! class_exists('CI_Controller', FALSE)) {
    class CI_Controller
    {
        public function __construct()
        {
        }
    }
}
