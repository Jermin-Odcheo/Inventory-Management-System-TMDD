<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . '/../../../../config/config.php');
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Roboto+Mono:wght@300;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/header_footer.css">

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
                    <img src="../../../../../../public/assets/img/profile.jpg" alt="User Profile" class="profile-picture">
                    <!-- Small drop-down indicator inside the profile photo -->
                    <span class="dropdown-indicator"><i class="fas fa-caret-down"></i></span>
                </div>
                <div class="user-info">
                    <div class="user-name">Lebron N. James</div>
                    <div class="user-role">WAREHOUSE MANAGER</div>
                </div>
                <div class="dropdown-menu" id="dropdownMenu">
                    <a href="#">Settings</a>
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

        // Close the dropdown if the user clicks outside of it
        window.onclick = function(event) {
            if (!event.target.closest('.user-profile')) {
                const dropdownMenu = document.getElementById('dropdownMenu');
                if (dropdownMenu.classList.contains('show')) {
                    dropdownMenu.classList.remove('show');
                    document.querySelector('.user-profile').classList.remove('active');
                }
            }
        }
    </script>


</body>
</html>