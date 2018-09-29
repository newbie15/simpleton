<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Simpleton | Dashboard</title>
    <!-- Tell the browser to be responsive to screen width -->
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <!-- Bootstrap 3.3.5 -->
    <link rel="stylesheet" href="<?= base_url('assets/adminlte/bootstrap/css/bootstrap.min.css') ?>">
    <!-- Font Awesome -->
    <!-- <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css"> -->
    <link rel="stylesheet" href="<?= base_url('assets/misc/font-awesome.min.css') ?>">

    <!-- Ionicons -->
    <!-- <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css"> -->
    <link rel="stylesheet" href="<?= base_url('assets/misc/ionicons.min.css') ?>">
    <!-- Theme style -->
    <link rel="stylesheet" href="<?= base_url('assets/adminlte/dist/css/AdminLTE.min.css') ?>">
    <!-- AdminLTE Skins. Choose a skin from the css/skins
         folder instead of downloading all of them to reduce the load. -->
    <link rel="stylesheet" href="<?= base_url('assets/adminlte/dist/css/skins/_all-skins.min.css') ?>">
    <!-- iCheck -->
    <!-- <link rel="stylesheet" href="<?= base_url('assets/adminlte/plugins/iCheck/flat/blue.css') ?>"> -->
    <!-- bootstrap wysihtml5 - text editor -->
    <!-- <link rel="stylesheet" href="<?= base_url('assets/adminlte/plugins/bootstrap-wysihtml5/bootstrap3-wysihtml5.min.css') ?>"> -->
    <?php if (isset($css_files)) { ?>
    <?php
    foreach($css_files as $file): ?>
      <link type="text/css" rel="stylesheet" href="<?php echo $file; ?>" />
    <?php endforeach; ?>
    <?php foreach($js_files as $file): ?>
      <script src="<?php echo $file; ?>"></script>
    <?php endforeach; ?>
    <?php } ?>
    <?php if (isset($css_file)) { ?>
    <?php
    foreach($css_file as $file): ?>
      <link type="text/css" rel="stylesheet" href="<?php echo $file; ?>" />
    <?php endforeach; ?>
    <?php } ?>
  </head>
  <body class="hold-transition skin-blue sidebar-mini">
    <div class="wrapper">

      <header class="main-header">
        <!-- Logo -->
        <a href="<?=base_url('')?>" class="logo">
          <!-- mini logo for sidebar mini 50x50 pixels -->
          <span class="logo-mini"><b>KMS</b></span>
          <!-- logo for regular state and mobile devices -->
          <span class="logo-lg"><b>Koperasi KMS</b></span>
        </a>
        <!-- Header Navbar: style can be found in header.less -->
        <nav class="navbar navbar-static-top" role="navigation">
          <!-- Sidebar toggle button-->
          <a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
            <span class="sr-only">Toggle navigation</span>
          </a>

        </nav>
      </header>
      <!-- Left side column. contains the logo and sidebar -->
      <aside class="main-sidebar">
        <!-- sidebar: style can be found in sidebar.less -->
        <section class="sidebar">
          <ul class="sidebar-menu">
            <?php $this->load->view('main_menu', '', FALSE); ?>
            <?php if($this->ion_auth->logged_in()) { ?>
            <?php $this->load->view('admin_menu', '', FALSE); ?>
            <?php } ?>
          </ul>
        </section>
        <!-- /.sidebar -->
      </aside>

      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <?php if(isset($judul_besar)) { ?>
        <section class="content-header">
          <h1>
            <?= $judul_besar ?>
            <small><?= $judul_kecil ?></small>
          </h1>
        </section>
        <?php } ?>
        <!-- Main content -->
        <section class="content">
          <!-- Small boxes (Stat box) -->
          <div>
            <?php echo $output; ?>
          </div>

        </section><!-- /.content -->
      </div><!-- /.content-wrapper -->
      <!-- <footer class="main-footer">
        <div class="pull-right hidden-xs">
            <b>Version</b> 1.0.0
          </div>
          <strong>Developed by &copy; 2018 <a href="http://fajarrukmo.wordpress.com">Fajar Rukmo</a>.</strong>
          <!-- <br> -->
          <!-- <strong>Copyright &copy; 2015 <a href="http://heruprambadi.com">Heru Prambadi</a>.</strong> All rights reserved. -->
      <!-- </footer> --> 

      
      <!-- Add the sidebar's background. This div must be placed
           immediately after the control sidebar -->
      <div class="control-sidebar-bg"></div>
    </div><!-- ./wrapper -->

    <!-- <script>
      $.widget.bridge('uibutton', $.ui.button);
    </script> -->
    <?php if (!isset($js_files)) { ?>
    <!-- jQuery 2.1.4 -->
    <script src="<?= base_url('assets/adminlte/plugins/jQuery/jQuery-2.1.4.min.js') ?>"></script>
    <?php } ?>
    <!-- Bootstrap 3.3.5 -->
    <script src="<?= base_url('assets/adminlte/bootstrap/js/bootstrap.min.js') ?>"></script>
    <!-- AdminLTE App -->
    <script src="<?= base_url('assets/adminlte/dist/js/app.min.js') ?>"></script>
    <!-- AdminLTE dashboard demo (This is only for demo purposes) -->
    <!-- <script src="<?= base_url('assets/adminlte/dist/js/pages/dashboard.js') ?>"></script> -->
    <!-- AdminLTE for demo purposes -->
    <script src="<?= base_url('assets/adminlte/dist/js/demo.js') ?>"></script>
    <?php if (isset($js_file)) { ?>
    <?php foreach($js_file as $file): ?>
      <script src="<?php echo $file; ?>"></script>
    <?php endforeach; ?>
    <?php } ?>
  </body>
</html>
