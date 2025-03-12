<?php
// add_user.php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Set audit log variables
if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
    // Determine if we're in edit mode
    $isEditing = isset($_GET['id']);  // Checks if an ID parameter exists in the URL
} else {
    $pdo->exec("SET @current_user_id = NULL");
}

$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

// Fetch available departments from database
$departments = [];
$deptStmt = $pdo->query("SELECT id, department_name FROM departments WHERE is_disabled = 0");
while ($dept = $deptStmt->fetch(PDO::FETCH_ASSOC)) {
    $departments[$dept['id']] = $dept['department_name'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Retrieve form values
        $email = trim($_POST['email']);
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $password = $_POST['password'];
        $roleIDs = $_POST['roles'] ?? [];
        $departmentID = $_POST['department'];
        $customDept = trim($_POST['custom_department'] ?? '');

        // Validate required fields
        $errors = [];
        if (empty($email)) $errors[] = "Email is required";
        if (empty($firstName)) $errors[] = "First name is required";
        if (empty($lastName)) $errors[] = "Last name is required";
        if (empty($roleIDs)) $errors[] = "At least one role must be selected";
        if (!$isEditing && empty($password)) $errors[] = "Password is required";

        // Handle department selection
        $selectedDeptId = null;
        if ($departmentID === 'custom' && !empty($customDept)) {
            // Check if custom department exists
            $stmt = $pdo->prepare("SELECT id FROM departments WHERE department_name = ?");
            $stmt->execute([$customDept]);
            $existingDept = $stmt->fetch();

            if ($existingDept) {
                $selectedDeptId = $existingDept['id'];
            } else {
                // Insert new department
                $stmt = $pdo->prepare("INSERT INTO departments (department_name) VALUES (?)");
                $stmt->execute([$customDept]);
                $selectedDeptId = $pdo->lastInsertId();
            }
        } else {
            $selectedDeptId = $departmentID;
        }

        if (!empty($errors)) {
            throw new Exception(implode("<br>", $errors));
        }

        // Check email uniqueness
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("Email address already exists");
        }

        $pdo->beginTransaction();

        // Create user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users 
            (username, email, password, first_name, last_name, date_created) 
            VALUES (?, ?, ?, ?, ?, NOW())");

        $username = strtolower($firstName[0] . $lastName);
        $stmt->execute([
            $username,
            $email,
            $hashedPassword,
            $firstName,
            $lastName
        ]);
        $userID = $pdo->lastInsertId();

        // Add department association
        $stmt = $pdo->prepare("INSERT INTO user_departments (user_id, department_id) VALUES (?, ?)");
        $stmt->execute([$userID, $selectedDeptId]);

        // Add roles
        $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        foreach ($roleIDs as $roleID) {
            $stmt->execute([$userID, $roleID]);
        }

        $pdo->commit();

        // Success response with a toast message
        echo json_encode([
            'success' => true,
            'message' => 'User created successfully',
            'user_id' => $userID
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    exit();
}

// Fetch roles for form
$roles = [];
$roleStmt = $pdo->query("SELECT id, role_name FROM roles");
while ($role = $roleStmt->fetch(PDO::FETCH_ASSOC)) {
    $roles[] = $role;
}

// Return data for form initialization (for GET requests)
echo json_encode([
    'departments' => $departments,
    'roles' => $roles
]);
?>
