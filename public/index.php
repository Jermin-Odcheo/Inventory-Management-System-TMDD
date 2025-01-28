<?php
session_start();
require_once '../config/ims-tmdd.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['privileges']['can_view_assets'] != 1) {
    die("Access Denied. You do not have permission to view assets.");
}

// Fetch all assets
$stmt = $pdo->query("SELECT * FROM assets ORDER BY id DESC");
$assets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset List</title>
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
        }

        h1 {
            margin-bottom: 20px;
            font-size: 2rem;
            color: #333;
        }

        .btn-action {
            margin-right: 5px;
        }

        .table {
            margin-top: 20px;
        }

        .table th,
        .table td {
            vertical-align: middle;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.05);
        }

        .alert-access-denied {
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Asset List</h1>
        <p><a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a></p>

        <?php if ($_SESSION['user']['privileges']['can_create_assets'] == 1): ?>
            <p><a href="create.php" class="btn btn-primary">Add New Asset</a></p>
        <?php endif; ?>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Asset Tag</th>
                    <th>Description</th>
                    <th>Brand</th>
                    <th>Serial #</th>
                    <th>Date Acquired</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assets as $asset): ?>
                    <tr>
                        <td><?php echo $asset['id']; ?></td>
                        <td><?php echo htmlspecialchars($asset['asset_tag']); ?></td>
                        <td><?php echo htmlspecialchars($asset['asset_description']); ?></td>
                        <td><?php echo htmlspecialchars($asset['brand']); ?></td>
                        <td><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                        <td><?php echo htmlspecialchars($asset['date_acquired']); ?></td>
                        <td>
                            <?php if ($_SESSION['user']['privileges']['can_edit_assets'] == 1): ?>
                                <a href="edit.php?id=<?php echo $asset['id']; ?>" class="btn btn-warning btn-sm btn-action">Edit</a>
                            <?php endif; ?>
                            <?php if ($_SESSION['user']['privileges']['can_delete_assets'] == 1): ?>
                                <a href="delete.php?id=<?php echo $asset['id']; ?>"
                                    class="btn btn-danger btn-sm btn-action"
                                    onclick="return confirm('Are you sure?');">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Bootstrap 5 JS (optional, for certain components) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>