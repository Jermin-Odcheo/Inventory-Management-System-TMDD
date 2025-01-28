<?php
session_start();
require_once '../config/ims-tmdd.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['privileges']['can_create_assets'] != 1) {
    die("Access Denied. You do not have permission to create assets.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_tag        = $_POST['asset_tag'];
    $asset_desc       = $_POST['asset_description'];
    $brand            = $_POST['brand'];
    $serial           = $_POST['serial_number'];
    $date_acquired    = $_POST['date_acquired'];

    $stmt = $pdo->prepare("INSERT INTO assets 
                           (asset_tag, asset_description, brand, serial_number, date_acquired) 
                           VALUES (:tag, :desc, :brand, :serial, :date_acquired)");
    $stmt->execute([
        'tag' => $asset_tag,
        'desc' => $asset_desc,
        'brand' => $brand,
        'serial' => $serial,
        'date_acquired' => $date_acquired
    ]);
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Create Asset</title>
</head>

<body>
    <h1>Create Asset</h1>
    <p><a href="index.php">Back to Asset List</a></p>
    <form method="POST">
        <label>Asset Tag:
            <input type="text" name="asset_tag" required>
        </label><br><br>
        <label>Description:
            <input type="text" name="asset_description">
        </label><br><br>
        <label>Brand:
            <input type="text" name="brand">
        </label><br><br>
        <label>Serial Number:
            <input type="text" name="serial_number">
        </label><br><br>
        <label>Date Acquired:
            <input type="date" name="date_acquired">
        </label><br><br>
        <button type="submit">Save</button>
    </form>
</body>

</html>