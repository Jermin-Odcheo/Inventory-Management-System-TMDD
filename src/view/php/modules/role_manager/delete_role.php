<?php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust path as needed

// Ensure the request method is POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// Check if the role ID is provided in the POST data.
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'No role ID specified.']);
    exit();
}

$role_id = intval($_POST['id']);

try {
    // Verify that the role exists.
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$role_id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        echo json_encode(['success' => false, 'message' => 'Role not found.']);
        exit();
    }

    // Delete the role from the roles table.
    $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
    if ($stmt->execute([$role_id])) {
        // Log the deletion action in the role_changes table.
        $stmt = $pdo->prepare("INSERT INTO role_changes (UserID, RoleID, Action, OldRoleName) VALUES (?, ?, 'Delete', ?)");
        // Use 'role_name' key based on your table schema.
        $stmt->execute([
            $_SESSION['user_id'],
            $role_id,
            $role['role_name']
        ]);

        echo json_encode(['success' => true, 'message' => 'Role deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete the role. Please try again.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => "Database error: " . $e->getMessage()]);
}

exit();
?>
