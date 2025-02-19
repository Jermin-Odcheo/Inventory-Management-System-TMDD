<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Check for admin privileges (you should implement your privilege check).
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../../../public/index.php");  // Fix redirect to login page
    exit();
}
// Set the audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    // Use the logged-in user's ID.
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    // For anonymous actions, you might set a default.
    $pdo->exec("SET @current_user_id = NULL");
}

// Set IP address; adjust as needed if you use a proxy, etc.
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

// If editing, load user data.
$isEditing = isset($_GET['id']);
$userData = [];
if ($isEditing) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE User_ID = ?");
    $stmt->execute([$_GET['id']]);
    $userData = $stmt->fetch();
}

// Fetch available roles.
$stmt = $pdo->prepare("SELECT * FROM roles");
$stmt->execute();
$roles = $stmt->fetchAll();

$successMessage = ''; // Variable to hold the success message
$errorMessage = '';   // Variable to hold error messages

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Update the departments array to use abbreviations as keys and full names as values
$departments = [
    'SAS' => 'School of Advanced Studies',
    'SOM' => 'School of Medicine',
    'SOL' => 'School of Law',
    'STELA' => 'School of Teacher Education and Liberal Arts',
    'SONAHBS' => 'School of Nursing, Allied Health, and Biological Sciences',
    'SEA' => 'School of Engineering and Architecture',
    'SAMCIS' => 'School of Accountancy, Management, Computing, and Information Studies'
];

// If form is submitted.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $email      = trim($_POST['email']);
        $firstName  = trim($_POST['first_name']);
        $lastName   = trim($_POST['last_name']);
        
        // Store only the department abbreviation
        $department = isset($_POST['custom_department']) && !empty($_POST['custom_department']) 
            ? trim($_POST['custom_department']) 
            : trim($_POST['department']);
        
        $status     = $_POST['status'];
        $roleIDs    = isset($_POST['roles']) ? $_POST['roles'] : [];
        $password   = $_POST['password'];

        // Validate required fields
        if (empty($email) || empty($firstName) || empty($lastName) || empty($department) || (!$isEditing && empty($password))) {
            throw new Exception("Please fill in all required fields.");
        }

        // Validate that at least one role is selected
        if (empty($roleIDs)) {
            throw new Exception("Please assign at least one role to the user.");
        }

        // Check for duplicate email
        if ($isEditing) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE Email = ? AND User_ID != ?");
            $stmt->execute([$email, $userData['User_ID']]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE Email = ?");
            $stmt->execute([$email]);
        }
        $emailCount = $stmt->fetchColumn();

        if ($emailCount > 0) {
            throw new Exception("The email address is already taken. Please choose a different email.");
        }

        // Begin transaction
        $pdo->beginTransaction();

        if (!$isEditing) {
            // Hash the password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $stmt = $pdo->prepare("
                INSERT INTO users (Email, Password, First_Name, Last_Name, Department, Status) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            if (!$stmt->execute([$email, $hashedPassword, $firstName, $lastName, $department, $status])) {
                throw new Exception("Failed to insert user data.");
            }
            
            $userID = $pdo->lastInsertId();
            
            // Insert user roles
            if (!empty($roleIDs)) {
                $stmt = $pdo->prepare("INSERT INTO user_roles (User_ID, Role_ID) VALUES (?, ?)");
                foreach ($roleIDs as $roleID) {
                    if (!$stmt->execute([$userID, $roleID])) {
                        throw new Exception("Failed to assign roles.");
                    }
                }
            }
            
            $pdo->commit();
            $successMessage = "User added successfully!";
            
            // Redirect to user management page after successful addition
            header("Location: user_management.php?success=1");
            exit();
        } else {
            // For edit, update user details. (Password update might be handled separately.)
            $stmt = $pdo->prepare("UPDATE users SET Email = ?, First_Name = ?, Last_Name = ?, Department = ?, Status = ? WHERE User_ID = ?");
            $stmt->execute([$email, $firstName, $lastName, $department, $status, $userData['User_ID']]);
            $userID = $userData['User_ID'];
            $successMessage = "User updated successfully!";
        }

        // Update the user's roles.
        // First, delete existing roles.
        $stmt = $pdo->prepare("DELETE FROM user_roles WHERE User_ID = ?");
        $stmt->execute([$userID]);

        // Then, insert the new roles.
        $stmt = $pdo->prepare("INSERT INTO user_roles (User_ID, Role_ID) VALUES (?, ?)");
        foreach ($roleIDs as $roleID) {
            $stmt->execute([$userID, $roleID]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMessage = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $isEditing ? 'Edit' : 'Add'; ?> User</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../../../styles/css/admin.css">
    <style>
        /* Add styling for main content positioning */
        .main-content {
            margin-left: 300px; /* Match sidebar width */
            padding: 20px;
            margin-bottom: 20px;
            width: auto;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 200px; /* Match sidebar responsive width */
            }
        }
    </style>
</head>
<body>
<?php include '../../general/sidebar.php'; ?>
<div class="main-content">
    <h1 class="mb-4"><?php echo $isEditing ? 'Edit' : 'Add'; ?> User</h1>

    <?php if ($successMessage): ?>
        <div class="alert alert-success" role="alert">
            <?php echo $successMessage; ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo $errorMessage; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label for="email" class="form-label">Email:</label>
            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($userData['Email'] ?? ''); ?>" class="form-control" required>
        </div>

        <?php if (!$isEditing): ?>
            <div class="mb-3">
                <label for="password" class="form-label">Password:</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
        <?php endif; ?>

        <div class="mb-3">
            <label for="first_name" class="form-label">First Name:</label>
            <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($userData['First_Name'] ?? ''); ?>" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="last_name" class="form-label">Last Name:</label>
            <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($userData['Last_Name'] ?? ''); ?>" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="department" class="form-label">Department:</label>
            <select name="department" id="department" class="form-select mb-2" required>
                <option value="">Select Department</option>
                <?php foreach ($departments as $code => $name): ?>
                    <option value="<?php echo htmlspecialchars($code); ?>" 
                        <?php echo (isset($userData['Department']) && $userData['Department'] === $code) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($name); ?>
                    </option>
                <?php endforeach; ?>
                <option value="custom">Custom Department</option>
            </select>
            <input type="text" id="custom-department" name="custom_department" 
                class="form-control" 
                style="display: none;" 
                placeholder="Enter custom department">
        </div>
        <div class="mb-3">
            <label for="status" class="form-label">Status:</label>
            <select name="status" id="status" class="form-select">
                <option value="Online" <?php echo (isset($userData['Status']) && $userData['Status'] === 'Online') ? 'selected' : ''; ?>>Online</option>
                <option value="Offline" <?php echo (isset($userData['Status']) && $userData['Status'] === 'Offline') ? 'selected' : ''; ?>>Offline</option>
            </select>
        </div>

        <fieldset class="mb-3">
            <legend>Assign Roles: <span class="text-danger">*</span></legend>
            <div class="text-muted mb-2">At least one role must be selected</div>
            <?php foreach ($roles as $role):
                // If editing, check which roles the user already has.
                $isAssigned = false;
                if ($isEditing) {
                    $stmt = $pdo->prepare("SELECT 1 FROM user_roles WHERE User_ID = ? AND Role_ID = ?");
                    $stmt->execute([$userData['User_ID'], $role['Role_ID']]);
                    $isAssigned = (bool) $stmt->fetch();
                }
                ?>
                <div class="form-check">
                    <input type="checkbox" name="roles[]" value="<?php echo $role['Role_ID']; ?>" id="role_<?php echo $role['Role_ID']; ?>" class="form-check-input role-checkbox" <?php echo $isAssigned ? 'checked' : ''; ?>>
                    <label for="role_<?php echo $role['Role_ID']; ?>" class="form-check-label"><?php echo htmlspecialchars($role['Role_Name']); ?></label>
                </div>
            <?php endforeach; ?>
        </fieldset>

        <button type="submit" class="btn btn-primary"><?php echo $isEditing ? 'Update' : 'Add'; ?> User</button>
    </form>
    <div class="mt-3">
        <a href="user_management.php" class="btn btn-secondary">Back to User Management</a>
    </div>
</div>

<!-- Bootstrap JS Bundle (Optional, if you need interactive components) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const departmentSelect = document.getElementById('department');
    const customDepartment = document.getElementById('custom-department');

    // Role validation
    form.addEventListener('submit', function(e) {
        const roleCheckboxes = document.querySelectorAll('.role-checkbox');
        let hasRole = false;
        
        roleCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                hasRole = true;
            }
        });
        
        if (!hasRole) {
            e.preventDefault();
            alert('Please assign at least one role to the user.');
        }

        // Handle custom department validation
        if (departmentSelect.value === 'custom') {
            if (customDepartment.value.trim() === '') {
                e.preventDefault();
                alert('Please enter a custom department name.');
            }
        }
    });

    // Department dropdown handling
    departmentSelect.addEventListener('change', function() {
        if (this.value === 'custom') {
            customDepartment.style.display = 'block';
            customDepartment.required = true;
        } else {
            customDepartment.style.display = 'none';
            customDepartment.required = false;
            customDepartment.value = ''; // Clear the custom input when switching back
        }
    });
});
</script>
</body>
</html>
