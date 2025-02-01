<?php
session_start();
require_once '../../../../config/ims-tmdd.php'; // Ensure this initializes $db with mysqli

// Helper function to check for a specific privilege
function hasPrivilege($privilege)
{
    return isset($_SESSION['privileges']) && in_array($privilege, $_SESSION['privileges']);
}

// Access control: Ensure the user is logged in and has the 'View' privilege
// if (!isset($_SESSION['user_id']) || !hasPrivilege('View')) {
//     // Redirect to Access Denied page
//     header("Location: /path/to/access_denied.php");
//     exit;
// }

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

        <?php if (hasPrivilege('Add')): ?>
            <p><a href="create.php" class="btn btn-primary">Add New Asset</a></p>
        <?php endif; ?>

        <?php if (!empty($assets)): ?>
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
                            <td><?php echo htmlspecialchars($asset['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($asset['asset_tag'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($asset['asset_description'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($asset['brand'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($asset['serial_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($asset['date_acquired'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php if (hasPrivilege('Edit')): ?>
                                    <a href="edit.php?id=<?php echo urlencode($asset['id']); ?>" class="btn btn-warning btn-sm btn-action">Edit</a>
                                <?php endif; ?>
                                <?php if (hasPrivilege('Delete')): ?>
                                    <a href="delete.php?id=<?php echo urlencode($asset['id']); ?>"
                                        class="btn btn-danger btn-sm btn-action"
                                        onclick="return confirm('Are you sure you want to delete this asset?');">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">No assets found.</div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap 5 JS (optional, for certain components) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>