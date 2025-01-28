<?php
session_start();
require_once '../config/ims-tmdd.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['privileges']['can_edit_assets'] != 1) {
    die("Access Denied. You do not have permission to edit assets.");
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die("Missing asset ID.");
}

// Fetch asset
$stmt = $pdo->prepare("SELECT * FROM assets WHERE id = :id");
$stmt->execute(['id' => $id]);
$asset = $stmt->fetch();
if (!$asset) {
    die("Asset not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_tag        = $_POST['asset_tag'];
    $asset_desc       = $_POST['asset_description'];
    $brand            = $_POST['brand'];
    $serial           = $_POST['serial_number'];
    $date_acquired    = $_POST['date_acquired'];

    $updateStmt = $pdo->prepare("UPDATE assets
                                 SET asset_tag = :tag,
                                     asset_description = :desc,
                                     brand = :brand,
                                     serial_number = :serial,
                                     date_acquired = :date_acquired
                                 WHERE id = :id");
    $updateStmt->execute([
        'tag' => $asset_tag,
        'desc' => $asset_desc,
        'brand' => $brand,
        'serial' => $serial,
        'date_acquired' => $date_acquired,
        'id' => $id
    ]);
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Asset</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }

        .container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 0 auto;
        }

        h1 {
            margin-bottom: 20px;
            font-size: 2rem;
            color: #333;
            text-align: center;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-control {
            margin-bottom: 1rem;
        }

        .btn-update {
            width: 100%;
            padding: 0.5rem;
            font-size: 1.1rem;
        }

        .btn-back {
            margin-bottom: 1rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Edit Asset</h1>
        <a href="index.php" class="btn btn-secondary btn-back">Back to Asset List</a>

        <form method="POST">
            <div class="mb-3">
                <label for="asset_tag" class="form-label">Asset Tag</label>
                <input type="text" class="form-control" id="asset_tag" name="asset_tag"
                    value="<?php echo htmlspecialchars($asset['asset_tag']); ?>" required>
            </div>

            <div class="mb-3">
                <label for="asset_description" class="form-label">Description</label>
                <input type="text" class="form-control" id="asset_description" name="asset_description"
                    value="<?php echo htmlspecialchars($asset['asset_description']); ?>">
            </div>

            <div class="mb-3">
                <label for="brand" class="form-label">Brand</label>
                <input type="text" class="form-control" id="brand" name="brand"
                    value="<?php echo htmlspecialchars($asset['brand']); ?>">
            </div>

            <div class="mb-3">
                <label for="serial_number" class="form-label">Serial Number</label>
                <input type="text" class="form-control" id="serial_number" name="serial_number"
                    value="<?php echo htmlspecialchars($asset['serial_number']); ?>">
            </div>

            <div class="mb-3">
                <label for="date_acquired" class="form-label">Date Acquired</label>
                <input type="date" class="form-control" id="date_acquired" name="date_acquired"
                    value="<?php echo htmlspecialchars($asset['date_acquired']); ?>">
            </div>

            <button type="submit" class="btn btn-primary btn-update">Update</button>
        </form>
    </div>

    <!-- Bootstrap 5 JS (optional, for certain components) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>