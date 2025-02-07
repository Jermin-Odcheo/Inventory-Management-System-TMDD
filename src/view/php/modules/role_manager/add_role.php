<?php
session_start();
require_once('../../../../config/ims-tmdd.php'); // Adjust path as needed

// Optional: Check if the logged-in user has permission to manage roles.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and trim the role name input
    $role_name = trim($_POST['role_name']);

    // Basic validation: check if the role name is provided
    if (empty($role_name)) {
        $error = "Role name is required.";
    } else {
        try {
            // Check if the role already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE Role_Name = ?");
            $stmt->execute([$role_name]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Role already exists.";
            } else {
                // Insert the new role into the database
                $stmt = $pdo->prepare("INSERT INTO roles (Role_Name) VALUES (?)");
                if ($stmt->execute([$role_name])) {
                    // If successful, you can redirect to the role management page.
                    header("Location: manage_roles.php");
                    exit();
                } else {
                    $error = "Error inserting role. Please try again.";
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add New Role</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Basic dark-themed styling */
        body {
            background-color: #181818;
            color: #e0e0e0;
            font-family: Arial, sans-serif;
        }

        .container {
            margin-top: 50px;
            max-width: 600px;
        }

        .form-control {
            background-color: #242424;
            border: 1px solid #0d6efd;
            color: #e0e0e0;
        }

        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
    </style>
</head>

<body>
    <!-- Include Sidebar -->
    <?php include '../general/sidebar.php'; ?>

    <div class="container">
        <h1 class="mb-4">Add New Role</h1>

        <!-- Display errors if any -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Optionally, display a success message (or redirect immediately) -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Role addition form -->
        <form action="add_role.php" method="POST">
            <div class="mb-3">
                <label for="role_name" class="form-label">Role Name</label>
                <input type="text" name="role_name" id="role_name" class="form-control" placeholder="Enter role name" required>
            </div>
            <!-- If you wish to add fields for privileges or modules, you can expand this form -->
            <button type="submit" class="btn btn-primary">Add Role</button>
            <a href="manage_roles.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>

    <!-- Bootstrap Bundle with Popper (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>