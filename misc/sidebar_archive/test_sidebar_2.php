<html>

<head>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.2/css/bootstrap.min.css'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'>
    <link rel='stylesheet' href='test_sidebar_2.css'>
    <title>Test Sidebar</title>
</head>

<body>
    <div class="wrapper">
        <div class="sidebar">
            <div class="sb-item-list">
                <div class="sb-item"><i class="sb-icon fa fa-address-card"></i><span class="sb-text">Sidebar
                        Item1</span></div>
                <div class="sb-item"><i class="sb-icon fa fa-address-card"></i><span class="sb-text">Sidebar
                        Item2</span></div>
                <div class="sb-item sb-menu"><i class="sb-icon fa fa-address-card"></i><span class="sb-text">Sidebar
                        Menu</span>
                    <div class="sb-submenu">
                        <div class="sb-item"><i class="sb-icon fa fa-address-card"></i><span class="sb-text">Level
                                2</span></div>
                        <div class="sb-item sb-menu"><i class="sb-icon fa fa-address-card"></i><span
                                class="sb-text">Level 2</span>
                            <div class="sb-submenu">
                                <div class="sb-item"><i class="sb-icon fa fa-address-card"></i><span
                                        class="sb-text">Level 3</span></div>
                                <div class="sb-item"><i class="sb-icon fa fa-address-card"></i><span
                                        class="sb-text">Level 3</span></div>
                                <div class="sb-item"><i class="sb-icon fa fa-address-card"></i><span
                                        class="sb-text">Level 3</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sb-item"><i class="sb-icon fa fa-address-card"></i><span class="sb-text">Sidebar
                        Item3</span></div>
                <div class="btn-toggle-sidebar sb-item"><i class="sb-icon fa fa-angle-double-left"></i><span
                        class="sb-text">Collapse Sidebar</span><i class="sb-icon fa fa-angle-double-right"></i></div>
            </div>
        </div>
        <div class="main">
            <h1>
                Guten Tag
            </h1>
            <p>
                Loram ipam
            </p>
        </div>
    </div>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.2/js/bootstrap.min.js'></script>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js'></script>
    <script>
        $(function () {
            // toggle sidebar collapse
            $('.btn-toggle-sidebar').on('click', function () {
                $('.wrapper').toggleClass('sidebar-collapse');
            });
            // mark sidebar item as active when clicked
            $('.sb-item').on('click', function () {
                if ($(this).hasClass('btn-toggle-sidebar')) {
                    return; // already actived
                }
                $(this).siblings().removeClass('active');
                $(this).siblings().find('.sb-item').removeClass('active');
                $(this).addClass('active');
            })
        });
    </script>
</body>

</html>