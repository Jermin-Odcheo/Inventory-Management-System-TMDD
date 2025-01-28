<?php
session_start();
require_once '../config/ims-tmdd.php';

// Check if user is logged in and has permission (e.g., can_manage_invoices == 1)
if (!isset($_SESSION['user']) || $_SESSION['user']['privileges']['can_manage_invoices'] != 1) {
    die("Access Denied: You do not have permission to manage invoices.");
}

// Fetch all invoices
$stmt = $pdo->query("SELECT * FROM charge_invoices ORDER BY id DESC");
$invoices = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
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

        .navbar {
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-weight: 600;
            color: #333;
        }

        .btn-create {
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Invoices</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Back to Dashboard</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <h1>Charge Invoices</h1>

        <!-- Create New Invoice Button -->
        <a href="invoices_create.php" class="btn btn-primary btn-create">Create New Invoice</a>

        <!-- Invoices Table -->
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Invoice No</th>
                    <th>Date of Purchase</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><?php echo $inv['id']; ?></td>
                        <td><?php echo htmlspecialchars($inv['charge_invoice_no']); ?></td>
                        <td><?php echo htmlspecialchars($inv['date_of_purchase']); ?></td>
                        <td>
                            <a href="invoices_edit.php?id=<?php echo $inv['id']; ?>" class="btn btn-warning btn-sm btn-action">Edit</a>
                            <a href="invoices_delete.php?id=<?php echo $inv['id']; ?>"
                                class="btn btn-danger btn-sm btn-action"
                                onclick="return confirm('Are you sure you want to delete this invoice?');">
                                Delete
                            </a>
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