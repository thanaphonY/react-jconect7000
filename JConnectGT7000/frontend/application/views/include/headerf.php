<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>

    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <?php $setting = getSetting(); ?>
    <title><?php echo (getSetting('website')) ? getSetting('website') : 'Dasboard'; ?></title>
    <!-- Favicon-->
    <link rel="apple-touch-icon" sizes="128x128" href="<?php echo iaBase(); ?>assets/images/touch-icon-ipad.png">
    <link rel="icon" href="<?php echo getSetting('favicon') ? iaBase() . 'assets/images/' . getSetting('favicon') : iaBase() . 'assets/images/favicon.png'; ?>" type="image/x-icon">

    <link rel="stylesheet" href="<?php echo iaBase(); ?>assets/plugins/DataTables/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="<?php echo iaBase(); ?>assets/plugins/DataTables/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="<?php echo iaBase(); ?>assets/plugins/DataTables/buttons.bootstrap5.css">
    <link rel="stylesheet" href="<?php echo iaBase(); ?>assets/plugins/DataTables/buttons.dataTables.min.css">
    <link rel="stylesheet" href="<?php echo iaBase(); ?>assets/plugins/bootstrap-icons-1.11.3/font/bootstrap-icons.min.css">
    <!--end::Fonts-->
    <!--begin::Third Party Plugin(OverlayScrollbars)-->
    <link rel="stylesheet" href="<?php echo iaBase(); ?>assets/plugins/popper/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="<?php echo iaBase(); ?>assets/plugins/bootstrap-icons-1.11.3/font/bootstrap-icons.min.css" />

    <link href="<?php echo iaBase() ?>assets/adminlte/css/adminlte.css" rel="stylesheet" />
    <link href="<?php echo iaBase(); ?>assets/plugins/gridstack/dist/gridstack.min.css" rel="stylesheet" />
    <script src="<?php echo iaBase(); ?>assets/plugins/gridstack/dist/gridstack-all.min.js"></script>
    <script src="<?php echo iaBase(); ?>assets/plugins/Chartjs/dist/chart.umd.min.js"></script>
    <script src="<?php echo iaBase(); ?>assets/plugins/chartjs-adapter-date-fns/src/chartjs-adapter-date-fns.bundle.min.js"></script>
    <link href="<?php echo iaBase() ?>assets/plugins/apexcharts/dist/apexcharts.css" rel="stylesheet" />
    <script src="<?php echo iaBase(); ?>assets/plugins/apexcharts/dist/apexcharts.min.js"></script>

    <script src="<?php echo iaBase(); ?>assets/plugins/echarts/dist/echarts.min.js"></script>

    <link rel="stylesheet" href="<?php echo iaBase(); ?>assets/plugins/select2/select2.min.css" />
    <link rel="stylesheet" href="<?php echo iaBase(); ?>assets/plugins/select2/select2-bootstrap-5-theme.min.css" />
    <script src="<?php echo iaBase(); ?>assets/plugins/jquery/jquery-3.7.1.js"></script>
    <script src="<?php echo iaBase() . 'assets/js/widgets/value_box.js'; ?>"></script>
    <script src="<?php echo iaBase() . 'assets/js/widgets/map_view.js'; ?>"></script>
    <script src="<?php echo iaBase() . 'assets/js/widgets/ratio.js'; ?>"></script>
    <script src="<?php echo iaBase() . 'assets/js/widgets/temp_hum.js'; ?>"></script>
    <script src="<?php echo iaBase() . 'assets/js/widgets/line_chart.js'; ?>"></script>
    <script src="<?php echo iaBase() . 'assets/js/widgets/bar_chart.js'; ?>"></script>
    <link rel="stylesheet" href="<?php echo iaBase(); ?>assets/plugins/jquery/jquery-ui.css">
    <script src="<?php echo iaBase(); ?>assets/plugins/jquery/jquery-ui.js"></script>



    <style>
        .app-sidebar {
            font-size: 90%;
        }

        .main-sidebar {
            font-size: 0.9rem;
        }

        table,
        th,
        td {
            font-size: 0.85rem;
            /*color: #999 !important;*/
        }


        .time-item {
            position: absolute;
            bottom: 15px;
            left: 48%;
            transform: translateX(-50%);
            margin-bottom: 0;
        }

        .page-loader-wrapper {
            z-index: 99999999;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: #eee;
            overflow: hidden;
            text-align: center;
        }

        .page-loader-wrapper p {
            font-size: 13px;
            margin-top: 10px;
            font-weight: bold;
            color: #444;
        }

        .page-loader-wrapper .loader {
            position: relative;
            top: calc(50% - 30px);
        }

        @font-face {
            font-family: 'Sarabun';
            src: url('/assets/fonts/Sarabun-Regular.ttf');
        }


        body {
            font-family: 'Sarabun', sans-serif;
        }
    </style>



    <!-- Bootstrap Core Js -->


</head>

<?php
$this->load->helper('cookie');
$cookie = get_cookie('theme_color');
if ((!isset($cookie)) || (isset($cookie) && empty($cookie))) {
    $cookie = 'theme-blue';
}

$cookieSidebar = get_cookie('sidebarstate');
if ($cookieSidebar !== 'sidebar-collapse' && $cookieSidebar !== 'sidebar-open') {
    $cookieSidebar = '';
}
?>

<body class="layout-fixed sidebar-expand-md bg-body-tertiary<?= $cookie; ?> <?= $cookieSidebar; ?>" data-base-url="<?= iaBase(); ?>/">
    <!-- Page Loader -->

    <div class="app-wrapper">


        <div class="page-loader-wrapper">
            <div class="loader">
                <div class="preloader">
                    <div class="spinner-layer pl-red">
                        <div class="circle-clipper left">
                            <div class="circle"></div>
                        </div>
                        <div class="circle-clipper right">
                            <div class="circle"></div>
                        </div>
                    </div>
                </div>
                <p><?php echo lang('please_wait'); ?>...</p>
            </div>
        </div>
        <!-- #END# Page Loader -->
        <!-- Overlay For Sidebars -->
        <div class="overlay"></div>
        <!-- #END# Overlay For Sidebars -->


        <!--begin::Header-->
        <nav class="app-header navbar navbar-expand bg-body">
            <!--begin::Container-->
            <div class="container-fluid">
                <!--begin::Start Navbar Links-->
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                            <i class="bi bi-list"></i>
                        </a>
                    </li>
                    <li class="nav-item" style="display: flex;align-items: center;">
                        <div id="navbarSupportedContent"> </div>
                    </li>
                    <?php if (function_exists('site_menu_allowed') && site_menu_allowed('site')) {
                        $isHomePage = ($this->router->class === 'home');
                        ?>
                    <li class="nav-item">
                        <a class="nav-link" id="home-view-site-btn"
                           href="<?php echo $isHomePage ? '#' : base_url('home') . '#site'; ?>"
                           data-home-toggle="<?php echo $isHomePage ? '1' : '0'; ?>"
                           title="View Site">
                            <i class="bi bi-diagram-3"></i> <span class="home-view-site-label">View Site</span>
                        </a>
                    </li>
                    <?php } ?>
                </ul>
                <!--end::Start Navbar Links-->
                <!--begin::End Navbar Links-->
                <ul class="navbar-nav ms-auto">

                    <li class="nav-item" id="elementSoundBtn">
                        
                    </li>
                    <li class="nav-item dropdown">
                        <?php $siteLang = $this->session->userdata('site_lang') ?: 'english'; ?>
                        <a class="nav-link" data-bs-toggle="dropdown" href="#">
                            <?php echo $siteLang === 'thai' ? lang('lang_name_thai') : lang('lang_name_english'); ?>
                            <b class="caret" style="color: #ffffff;"></b>
                        </a>

                        <div class="dropdown-menu dropdown-menu-xs dropdown-menu-end">

                            <a href='<?php echo base_url(); ?>LanguageSwitcher/switchLang/english' class="dropdown-item">English</a>
                            <div class="dropdown-divider"></div>
                            <a href='<?php echo base_url(); ?>LanguageSwitcher/switchLang/thai' class="dropdown-item">ไทย</a>
                        </div>
                    </li>

                    <!--begin::Dark Mode Toggle-->
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="toggleColorMode" title="Toggle Dark Mode">
                            <i id="colorModeIcon" class="bi bi-moon-fill"></i>
                        </a>
                    </li>
                    <!--end::Dark Mode Toggle-->

                    <!--begin::Fullscreen Toggle-->
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-lte-toggle="fullscreen">
                            <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                            <i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i>
                        </a>
                    </li>
                    <!--end::Fullscreen Toggle-->


                    <!--begin::User Menu Dropdown-->
                    <li class="nav-item dropdown user-menu">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                            <img src="<?php echo iaBase(); ?>assets/images/<?php echo isset($this->session->get_userdata()['user_details'][0]->profile_pic) ? $this->session->get_userdata()['user_details'][0]->profile_pic : 'user.png'; ?>" class="user-image rounded-circle shadow" />
                            <span class="d-none d-md-inline"><?php echo isset($this->session->get_userdata()['user_details'][0]->name) ? ucwords($this->session->get_userdata()['user_details'][0]->name) : ''; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                            <!--begin::User Image-->
                            <li class="user-header text-bg-primary">
                                <img src="<?php echo iaBase(); ?>assets/images/<?php echo isset($this->session->get_userdata()['user_details'][0]->profile_pic) ? $this->session->get_userdata()['user_details'][0]->profile_pic : 'user.png' ?>" width="40" height="40" alt="User" />
                                <p>
                                    <?php echo isset($this->session->get_userdata()['user_details'][0]->name) ? ucwords($this->session->get_userdata()['user_details'][0]->name) : ''; ?>
                                </p>
                            </li>
                            <!--end::User Image-->
                            <!--begin::Menu Body-->
                            <li class="user-body">
                                <!--begin::Row
                                <div class="row">
                                    <div class="col-4 text-center">
                                        <a href="<?php echo base_url() . 'setting'; ?>">
                                            <?php echo lang('settings'); ?>
                                        </a>
                                    </div>

                                </div>
                               end::Row-->
                            </li>
                            <!--end::Menu Body-->
                            <!--begin::Menu Footer-->
                            <li class="user-footer">
                                <a href="<?php echo base_url() . 'profile'; ?>" class="btn btn-default btn-flat"><?php echo lang('profile'); ?></a>
                                <a href="<?php echo base_url() . 'user/logout'; ?>" class="btn btn-default btn-flat float-end"><?php echo lang('Signout'); ?></a>
                            </li>
                            <!--end::Menu Footer-->
                        </ul>
                    </li>
                    <!--end::User Menu Dropdown-->
                </ul>
                <!--end::End Navbar Links-->
            </div>
            <!--end::Container-->
        </nav>
        <!--end::Header-->




        <!--begin::Sidebar-->
        <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
            <!--begin::Sidebar Brand-->
            <div class="sidebar-brand" style="margin-left: 20px !important;justify-content: left;">
                <!--begin::Brand Link-->


                <a href="<?php echo base_url(); ?>home" class="brand-link">
                    <!--begin::Brand Image-->
                    <?php
                    $logo = getSetting('logo');
                    if ($logo != '') {
                    ?>
                        <img src="<?php echo iaBase() . 'assets/images/' . $logo; ?>" id="logo" class="brand-image opacity-75 shadow">
                    <?php } ?>
                    <!--end::Brand Image-->
                    <!--begin::Brand Text-->
                    <span class="brand-text fw-light"><?php echo getSetting('website');  ?></span>
                    <!--end::Brand Text-->
                </a>
                <!--end::Brand Link-->
            </div>
            <!--end::Sidebar Brand-->
            <!--begin::Sidebar Wrapper-->
            <div class="sidebar-wrapper">
                <nav class="mt-2">
                    <!--begin::Sidebar Menu-->
                    <ul
                        class="nav sidebar-menu flex-column"
                        data-lte-toggle="treeview"
                        role="menu"
                        data-accordion="false">

                        <li class="nav-item <?= ($this->router->class === "home") ? "active" : "not-active" ?>">
                            <a href="<?php echo base_url('home'); ?>" class="nav-link">
                                <i class="nav-icon bi bi-house"></i>
                                <p><?php echo lang("home"); ?></p>
                            </a>
                        </li>

                        <?php if (CheckPermission("dashboard", "own_read") || CheckPermission("dashboard", "all_read")) { ?>
                            <li class="nav-item <?= ($this->router->class === "dashboard") ? "active" : "not-active" ?>">
                                <a href="<?php echo base_url('dashboard'); ?>" class="nav-link">
                                    <i class="nav-icon bi bi-speedometer"></i>
                                    <p><?php echo lang("dashboard"); ?></p>
                                </a>
                            </li>
                        <?php } ?>


                        <?php if (CheckPermission("devices", "own_read") || CheckPermission("devices", "all_read")) { ?>
                            <li class="nav-item <?= ($this->router->class === "device") ? "active" : "not-active" ?>">
                                <a href="<?php echo base_url(); ?>device" class="nav-link">
                                    <i class="nav-icon bi bi-cpu"></i>
                                    <p><?php echo lang('devices') ?></p>
                                </a>
                            </li>
                        <?php } ?>


                        <?php if (CheckPermission("notification", "own_read") || CheckPermission("notification", "all_read")) { ?>
                            <li class="nav-item <?= ($this->router->class === "notification_manager") ? "active" : "not-active" ?>">
                                <a href="<?php echo base_url(); ?>notification_manager" class="nav-link">
                                    <i class="nav-icon bi bi-bell"></i>
                                    <p><?php echo lang('notification') ?></p>
                                </a>
                            </li>
                        <?php } ?>


                        <li class="nav-item <?= ($this->router->method === "profile") ? "active" : "not-active" ?>">
                            <a href="<?php echo base_url('profile'); ?>" class="nav-link">
                                <i class="nav-icon bi bi-person"></i>
                                <p><?php echo lang("profile"); ?></p>
                            </a>
                        </li>

                        <?php if (CheckPermission("user", "own_read") || CheckPermission("user", "all_read")) { ?>
                            <li class="nav-item <?= ($this->router->method === "userlist") ? "active" : "not-active" ?>">
                                <a href="<?php echo base_url(); ?>user/userlist" class="nav-link">
                                    <i class="nav-icon bi bi-people"></i>
                                    <p><?php echo lang('users') ?></p>
                                </a>
                            </li>
                        <?php }  ?>


                        <?php if (CheckPermission("Eventlog", "own_read") || CheckPermission("Eventlog", "all_read")) { ?>
                            <li class="nav-item <?= ($this->router->class === "eventlog") ? "active" : "not-active" ?>">
                                <a href="<?php echo base_url("eventlogs"); ?>" class="nav-link">
                                    <i class="nav-icon bi bi-clock-history"></i>
                                    <p><?php echo lang('auditlogs'); ?></p>
                                </a>
                            </li>
                        <?php }  ?>


                        <?php if (CheckPermission("alarmlogs", "own_read") || CheckPermission("alarmlogs", "all_read")) { ?>
                            <li class="nav-item <?= ($this->router->class === "alarmlogs") ? "active" : "not-active" ?>">
                                <a href="<?php echo base_url("alarmlogs"); ?>" class="nav-link">
                                    <i class="nav-icon bi bi-clock-history"></i>
                                    <p><?php echo lang('alarmlog'); ?></p>
                                </a>
                            </li>
                        <?php }  ?>

                        <?php if (CheckPermission("controllogs", "own_read") || CheckPermission("controllogs", "all_read") || CheckPermission("control", "own_read") || CheckPermission("control", "all_read")) { ?>
                            <li class="nav-item <?= ($this->router->class === "controllogs") ? "active" : "not-active" ?>">
                                <a href="<?php echo base_url("controllogs"); ?>" class="nav-link">
                                    <i class="nav-icon bi bi-journal-text"></i>
                                    <p><?php echo lang('controllog'); ?></p>
                                </a>
                            </li>
                        <?php }  ?>

                        <?php if (CheckPermission("reports", "own_read") || CheckPermission("reports", "all_read")) { ?>
                            <li class="<?= ($this->router->class === "reports") ? "active" : "not-active" ?>">
                                <a href="<?php echo base_url("reports"); ?>" class="nav-link">
                                    <i class="nav-icon bi bi-file-earmark-bar-graph"></i>
                                    <p><?php echo lang('reports') ?></p>
                                </a>
                            </li>
                        <?php } ?>


                    
                        <?php if (CheckPermission("devicetemplate", "own_read") || CheckPermission("devicetemplate", "all_read")) { ?>
                            <li class="<?= ($this->router->class === "devicetemplate") ? "active" : "not-active" ?>">
                                <a href="<?php echo base_url("devicetemplate"); ?>" class="nav-link">
                                    <i class="nav-icon bi bi-gear"></i>
                                    <p><?php echo lang('device_template') ?></p>
                                </a>
                            </li>
                            <li class="<?= ($this->router->class === "calculation") ? "active" : "not-active" ?>">
                                <a href="<?php echo base_url("calculation"); ?>" class="nav-link">
                                    <i class="nav-icon bi bi-calculator"></i>
                                    <p>Calculate</p>
                                </a>
                            </li>
                        <?php } ?>

                        <?php if (CheckPermission("control", "own_read") || CheckPermission("control", "all_read")) { ?>
                            <li class="<?= ($this->router->class === "control") ? "active" : "not-active" ?>">
                                <a href="<?php echo base_url("control"); ?>" class="nav-link">
                                    <i class="nav-icon bi bi-toggles"></i>
                                    <p><?php echo lang('control'); ?></p>
                                </a>
                            </li>
                        <?php } ?>

                        <?php if (CheckPermission("setting", "own_read") || CheckPermission("setting", "all_read")) { ?>
                            <li class="<?= ($this->router->class === "setting") ? "active" : "not-active" ?>">
                                <a href="<?php echo base_url("setting"); ?>" class="nav-link">
                                    <i class="nav-icon bi bi-gear"></i>
                                    <p><?php echo lang('settings'); ?></p>
                                </a>
                            </li>
                        <?php }  ?>


                     



                    </ul>
                    <!--end::Sidebar Menu-->
                    <text class="time-item text-light"><?php echo date("Y-m-d H:i"); ?></text>
                </nav>

            </div>
            <!--end::Sidebar Wrapper-->

        </aside>



        <!--end::Sidebar-->

        <main class="app-main">

            <script>
                $(document).ready(function() {

                    function updateClock() {

                        var clockapi = '/api/timeNow';
                        $.ajax({
                            url: clockapi,
                            method: 'GET',
                            dataType: 'json',
                            success: function(data) {
                                var serverDate = data.date;
                                var serverTime = data.time;
                                $('.time-item').text(serverDate + ' ' + serverTime);
                            },
                            error: function(error) {
                                console.error('Error server time:', error);
                            }
                        });
                    }


                    setInterval(updateClock, 60000);
                });
            </script>

            <script>
                const htmlTag = document.documentElement;
                const toggleBtn = document.getElementById('toggleColorMode');
                const icon = document.getElementById('colorModeIcon');

                const applyTheme = (mode) => {
                    htmlTag.setAttribute('data-bs-theme', mode);
                    icon.className = mode === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
                    localStorage.setItem('jconnect_color_mode', mode);
                };

                toggleBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const currentMode = htmlTag.getAttribute('data-bs-theme');
                    applyTheme(currentMode === 'dark' ? 'light' : 'dark');
                });

                // Load from localStorage on first load
                window.addEventListener('DOMContentLoaded', () => {
                    const saved = localStorage.getItem('jconnect_color_mode') ?? 'dark';
                    if (saved === 'dark' || saved === 'light') {
                        applyTheme(saved);
                    }
                });
            </script>
