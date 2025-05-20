<?php
require_once(__DIR__ . '/../../../../config/config.php');
//If not logged in redirect to the LOGIN PAGE
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php"); // Redirect to login page
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
if ($user_id !== null) {
   $stmt = $pdo->prepare("SELECT profile_pic_path FROM users WHERE id = ?");
   $stmt->execute([$user_id]);
   $user = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
   die("User not logged in.");
}

$role = isset($_SESSION["role"]) ? $_SESSION["role"] : "";
$email = $_SESSION['email'];

$user_id = $_SESSION['user_id'] ?? null;

 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">


    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Roboto+Mono:wght@300;500&display=swap"
          rel="stylesheet">

    <!-- jQuery, Bootstrap CSS/JS, and Bootstrap Icons -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/toast.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/header_footer.css">
    
    <style>
       
    </style>

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
            <img
                src="<?= BASE_URL .
                    (
                        !empty($user['profile_pic_path'])
                            ? 'public/' . htmlspecialchars($user['profile_pic_path'])
                            : 'public/assets/img/default_profile.jpg'
                    )
                ?>"
                alt="User Profile"
                class="profile-picture"
            />
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
                        <li><a href="<?php echo BASE_URL; ?>src/view/php/clients/account_details.php">Account
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

    // Close the dropdown if the user clicks outside it
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

            if (mainDropdownMenu && dropdownTrigger) {
                if (mainDropdownMenu.classList.contains('show')) {
                    mainDropdownMenu.classList.remove('show');
                    dropdownTrigger.classList.remove('active');
                }
            }
        }
    }
    window.onload = function() {
        window.scrollTo(0, 0); // Resets the main window scroll to the top.
    };
</script>

</body>
</html>