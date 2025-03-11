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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/header_footer.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/toast.css">

</head>
<body>

<header class="header">
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

<!-- Include a small script to toggle aria-expanded on click -->
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

    // Close the dropdown if the user clicks outside of it
    window.onclick = function (event) {
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
    }
</script>

</body>
</html>
