<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css"
        rel="stylesheet">
    <link rel="stylesheet" href="test_sidebar.css">
</head>

<body>
    <!-- sidebar -->
    <div class="sidebar">
        <nav class="nav flex-column">
            <!-- dropdown -->
            <a class="nav-link" href="#">
                <span class="icon">
                    <i class="bi bi-grid"></i>
                </span>
                <span class="description">Dashboard</span>
            </a>
            <a class="nav-link" href="#">
                <span class="icon">
                    <i class="bi bi-clipboard-check"></i>
                </span>
                <span class="description">Tasks</span>
            </a>
            <a class="nav-link" href="#">
                <span class="icon">
                    <i class="bi bi-bell"></i>
                </span>
                <span class="description">Notifications</span>
            </a>
            <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#submenu" aria-expanded="false"
                aria-controls="submenu">
                <span class="icon">
                    <i class="bi bi-box-seam"></i>
                </span>
                <span class="description">Dropdown
                <i class="bi bi-chevron-down"></i>
                </span>
                
            </a>
            <!-- submenu -->
            <div class="sub-menu collapse" id="submenu">
                <a class="nav-link" href="#">
                    <span class="icon">
                        <i class="bi bi-file-earmark-check"></i>
                    </span>
                    <span class="description">Submenu 1</span>
                </a>
                <a class="nav-link" href="#">
                    <span class="icon">
                        <i class="bi bi-file-earmark-check"></i>
                    </span>
                    <span class="description">Submenu 2</span>
                </a>
            </div>
            <!-- normal dropdown -->
            <a class="nav-link" href="#">
                <span class="icon">
                    <i class="bi bi-gear"></i>
                </span>
                <span class="description">Settings</span>
            </a>
        </nav>
    </div>

    <!-- main content -->
    <div class="main-content">
        <h2>Big Title</h2>
        <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Quisquam, quos.</p>
    </div>


    <!-- same as real sidebar -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>