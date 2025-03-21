<?php
require_once(__DIR__ . '/../../../../config/config.php');
//If not logged in redirect to the LOGIN PAGE
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "public/index.php"); // Redirect to login page
    exit();
}
$role = isset($_SESSION["role"]) ? $_SESSION["role"] : "";
$email = $_SESSION['email'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Roboto+Mono:wght@300;500&display=swap"
          rel="stylesheet">

    <!-- jQuery, Bootstrap CSS/JS, and Bootstrap Icons -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/toast.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/header_footer.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f7fb;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #1B1F3B;
            padding: 10px 20px;
            box-shadow: 0 4px 30px rgba(11, 25, 51, 0.15);
            position: fixed;
            top: 0;
            left: 300px; /* Match the sidebar width */
            right: 0; /* Extend to the right edge */
            z-index: 900; /* Lower than sidebar's z-index */
        }

        .main-content {
            margin-left: 300px; /* Match sidebar width */
            padding-top: 70px; /* Match header height + some padding */
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 1.2rem;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: #38BDF8;
            border-radius: 10px;
            display: grid;
            place-items: center;
            box-shadow: 0 4px 12px rgba(56, 189, 248, 0.25);
            margin-left: 0; /* Remove the 290px margin */
        }

        .logo-icon svg {
            width: 28px;
            height: 28px;
            fill: white;
        }

        .logo-text {
            font-family: 'Poppins', sans-serif;
            font-size: 1.8rem;
            font-weight: 600;
            color: #E2E8F0;
            letter-spacing: -0.5px;
            margin-left: 5px;
        }

        .logo-subtext {
            color: #94A3B8;
            font-family: 'Roboto Mono', monospace;
            font-size: 0.9rem;
            font-weight: 300;
            letter-spacing: 1px;
            margin-top: 2px;
        }

        .header-widgets {
            display: flex;
            align-items: center;
            gap: 2.5rem;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.6rem 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            position: relative;
            cursor: pointer;
            width: 100%;
        }


        .user-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .user-name {
            font-family: 'Poppins', sans-serif;
            color: #F8FAFC;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .user-role {
            font-family: 'Roboto Mono', monospace;
            color: #94A3B8;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }

        /* Style for the dropdown arrow */
        .dropdown-icon {
            margin-left: 0.5rem;
            font-size: 1rem;
            color: #E2E8F0;
            transition: transform 0.3s;
        }

        /* Optionally rotate the arrow when dropdown is active */
        .user-profile.active .dropdown-icon {
            transform: rotate(180deg);
        }

        /* Adjusted dropdown menu */
        .dropdown-menu {
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
            position: absolute;
            top: 110%; /* positions the dropdown just below the profile area */
            right: 0;
            background: #1B1F3B;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            z-index: 1000;
            min-width: 200px;
            width: 100%;
            background: #1B1F3B !important;  /* Add !important to override any other styles */
            border-radius: 10px;
            overflow: hidden;
            padding: 8px 0;
            color: #ffffff !important;  /* Ensure text color is white */
        }


        .dropdown-menu.show {
            display: block;
            opacity: 1;
        }


        .dropdown-menu.show {
            display: block;
            opacity: 1;
        }

        .dropdown-menu a {
            display: block;
            padding: 0.75rem 1.5rem;
            color: #ffffff !important;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            text-decoration: none;
            transition: background 0.2s;
            text-align: center;
        }

        .dropdown-menu a:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        /* Wrap the profile image to position the indicator */
        .profile-picture-container {
            position: relative;
            display: inline-block;
        }

        /* Profile picture styling remains unchanged */
        .profile-picture {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid #38BDF8;
            object-fit: cover;
            padding: 2px;
            background: #0F172A;
        }

        /* Style for the drop-down indicator */
        .dropdown-indicator {
            position: absolute;
            bottom: 0;
            right: 0;
            background-color: #38bdf8;
            color: white;
            font-size: 0.7rem;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            border: 1px solid white;
            pointer-events: none; /* so that clicking still activates the parent container */
        }

        .dropdown-menu {
            min-width: 200px;
            width: 100%;
            background: #1E293B;
            border-radius: 10px;
            overflow: hidden;
            padding: 8px 0;
        }

        .settings-container {
            position: relative;
            width: 100%;
        }

        .settings-item {
            padding: 12px 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Arial', sans-serif;
            position: relative;
            color: #ecf0f1;
            transition: background 0.3s;
        }

        .settings-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .submenu-arrow {
            position: absolute;
            right: 20px;
            transition: transform 0.3s ease;
        }

        .subdropdown-menu {
            display: none;
            margin: 0;
            padding: 0;
            list-style: none;
            background: #1B1F3B;
        }

        .subdropdown-menu.show {
            display: block;
        }

        .subdropdown-menu li {
            width: 100%;
        }

        .subdropdown-menu li a {
            padding: 8px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            text-decoration: none;
            color: #ecf0f1;
            transition: background 0.3s;
        }

        .subdropdown-menu li a:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .dropdownMenu{
            background: #2d3d59;
        }
        
        /* Rotate arrow for dropdown */
        .settings-item .submenu-arrow {
            transform: rotate(90deg);
        }

        .settings-item .submenu-arrow.show {
            transform: rotate(180deg);
        }

        /* Left side dropdown styling */
        .header-dropdown {
            position: relative;
            margin-right: 20px;
        }

        .dropdown-trigger {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.6rem 1rem;
            background: rgba(56, 189, 248, 0.15);
            border-radius: 10px;
            cursor: pointer;
            color: #F8FAFC;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .dropdown-trigger:hover {
            background: rgba(56, 189, 248, 0.25);
        }

        .dropdown-arrow {
            font-size: 0.75rem;
            transition: transform 0.3s ease;
        }

        .dropdown-trigger.active .dropdown-arrow {
            transform: rotate(180deg);
        }

        .main-dropdown-menu {
            display: none;
            position: absolute;
            top: 110%;
            left: 0;
            min-width: 220px;
            background: #1B1F3B;
            border-radius: 10px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .main-dropdown-menu.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .main-dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.85rem 1.5rem;
            color: #ffffff;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            text-decoration: none;
            transition: background 0.2s, transform 0.2s;
            border-left: 3px solid transparent;
        }

        .main-dropdown-menu a:hover {
            background: rgba(56, 189, 248, 0.1);
            transform: translateX(5px);
            border-left: 3px solid #38BDF8;
        }

        .main-dropdown-menu a i {
            color: #38BDF8;
            font-size: 1rem;
            width: 20px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1.5rem;
                padding: 1.5rem;
                left: 220px; /* Match the sidebar's responsive width */
            }

            .logo-container {
                flex-direction: column;
                text-align: center;
            }

            .header-widgets {
                flex-direction: column;
                gap: 1rem;
                width: 100%;
            }

            .user-profile {
                width: 100%;
                justify-content: center;
            }
            
            .main-content {
                margin-left: 220px; /* Match responsive sidebar width */
            }
        }
    </style>

</head>
<body>

<header class="header">
    <!-- Left side dropdown -->
    <div class="header-dropdown">
        <div class="dropdown-trigger" onclick="toggleMainDropdown()">
            <i class="fas fa-bars"></i>
            <span>Menu</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
        </div>
        <div class="main-dropdown-menu" id="mainDropdownMenu">
            <a href="#"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="#"><i class="fas fa-box"></i> Inventory</a>
            <a href="#"><i class="fas fa-chart-line"></i> Analytics</a>
            <a href="#"><i class="fas fa-users"></i> Users</a>
            <a href="#"><i class="fas fa-truck"></i> Suppliers</a>
            <a href="#"><i class="fas fa-cog"></i> System Settings</a>
        </div>
    </div>
    
    <div class="logo-container">
        <div class="logo-icon">
            <svg viewBox="0 0 24 24">
                <path d="M20 6h-4V4c0-1.1-.9-2-2-2h-4c-1.1 0-2 .9-2 2v2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zM10 4h4v2h-4V4zm10 16H4V8h16v12z"/>
            </svg>
        </div>
        <div>
            <div class="logo-text">Inventory Management System</div>
        </div>
    </div>
    
    <div class="header-widgets">
        <div class="user-profile" onclick="toggleDropdown()">
            <div class="profile-picture-container">
                <img src="../../../../../public/assets/img/profile.jpg" alt="User Profile" class="profile-picture">
                <!-- Small drop-down indicator inside the profile photo -->
                <span class="dropdown-indicator"><i class="fas fa-caret-down"></i></span>
            </div>
            <div class="user-info">
                <!--TO CHANGE FOR USERNAME-->
                <div class="user-name"><?php echo $email; ?></div>
                <div class="user-role"><?php echo $role; ?> </div>
            </div>
            <div class="dropdown-menu" id="dropdownMenu">
                <div class="settings-container">
                    <div class="settings-item" onclick="toggleSubDropdown(event)">
                        Settings <span class="submenu-arrow">▸</span>
                    </div>
                    <ul class="subdropdown-menu">
                        <li><a href="<?php echo BASE_URL; ?>src/view/php/clients/admins/account_details.php">Account
                                Details</a></li>
                        <li><a href="#">Personalization</a></li>
                    </ul>
                </div>

                <a href="<?php echo BASE_URL; ?>src/view/php/general/logout.php">Logout</a>
            </div>
        </div>
    </div>
</header>

<!-- JavaScript for dropdown functionality -->
<script>
    function toggleDropdown() {
        const dropdownMenu = document.getElementById('dropdownMenu');
        dropdownMenu.classList.toggle('show');
        document.querySelector('.user-profile').classList.toggle('active');
    }

    function toggleSubDropdown(event) {
        event.stopPropagation();
        const submenu = event.currentTarget.nextElementSibling;
        const arrow = event.currentTarget.querySelector('.submenu-arrow');

        submenu.classList.toggle('show');
        if (submenu.classList.contains('show')) {
            arrow.style.transform = 'rotate(180deg)';
        } else {
            arrow.style.transform = 'rotate(90deg)';
        }
    }
    
    function toggleMainDropdown() {
        const mainDropdownMenu = document.getElementById('mainDropdownMenu');
        const dropdownTrigger = document.querySelector('.dropdown-trigger');
        
        mainDropdownMenu.classList.toggle('show');
        dropdownTrigger.classList.toggle('active');
    }

    // Close the dropdown if the user clicks outside of it
    window.onclick = function(event) {
        // Handle the profile dropdown
        if (!event.target.closest('.user-profile')) {
            const dropdownMenu = document.getElementById('dropdownMenu');
            const subdropdowns = document.querySelectorAll('.subdropdown-menu');
            const arrows = document.querySelectorAll('.submenu-arrow');

            if (dropdownMenu.classList.contains('show')) {
                dropdownMenu.classList.remove('show');
                document.querySelector('.user-profile').classList.remove('active');
                subdropdowns.forEach(submenu => submenu.classList.remove('show'));
                arrows.forEach(arrow => arrow.style.transform = 'rotate(90deg)');
            }
        }
        
        // Handle the new main dropdown
        if (!event.target.closest('.header-dropdown')) {
            const mainDropdownMenu = document.getElementById('mainDropdownMenu');
            const dropdownTrigger = document.querySelector('.dropdown-trigger');
            
            if (mainDropdownMenu.classList.contains('show')) {
                mainDropdownMenu.classList.remove('show');
                dropdownTrigger.classList.remove('active');
            }
        }
    }
</script>

</body>
</html>