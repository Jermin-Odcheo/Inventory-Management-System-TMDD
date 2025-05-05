<?php
// config/config.php

// Base URL for links
define('BASE_URL', '/Inventory-Managment-System-TMDD/');

// ROOT_PATH is your project’s root directory (one level up from config/)
define('ROOT_PATH', realpath(__DIR__ . '/..') . '/');

// Autoload RBACService (and any other classes you drop under clients/admins)
require_once ROOT_PATH . 'src/view/php/clients/admins/RBACService.php';
