<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

//@current_user_id is For audit logs
if (!isset($_SESSION['user_id'])) {
    echo "<p class='text-danger'>Unauthorized access.</p>";
    $pdo->exec("SET @current_user_id = NULL");
    exit();
} else {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
}
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

// Process form submission via POST and return JSON response.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $role_name = trim($_POST['role_name']);

    if (empty($role_name)) {
        echo json_encode(['success' => false, 'message' => 'Role name is required.']);
        exit();
    } else {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE Role_Name = ?");
            $stmt->execute([$role_name]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Role already exists.']);
                exit();
            } else {
                $stmt = $pdo->prepare("INSERT INTO roles (Role_Name) VALUES (?)");
                if ($stmt->execute([$role_name])) {
                    $roleID = $pdo->lastInsertId();

                    // Log the action in the role_changes table.
                    $stmt = $pdo->prepare("INSERT INTO role_changes (UserID, RoleID, Action, NewRoleName) VALUES (?, ?, 'Add', ?)");
                    $stmt->execute([$_SESSION['user_id'], $roleID, $role_name]);

                    echo json_encode(['success' => true, 'message' => 'Role created successfully.']);
                    exit();
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error inserting role. Please try again.']);
                    exit();
                }
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit();
        }
    }
}
?>

<!-- Display the add role form when not processing a POST request -->
<form id="addRoleForm" method="POST" action="add_role.php">
    <div class="mb-3">
        <label for="role_name" class="form-label">Role Name</label>
        <input type="text" name="role_name" id="role_name" class="form-control" placeholder="Enter role name" required>
    </div>
    <div class="d-flex justify-content-end">
        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Role</button>
    </div>
</form>

<script>
    // Submit form via AJAX with toast notifications.
    $("#addRoleForm").submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: "add_role.php",
            type: "POST",
            data: $(this).serialize(),
            dataType: "json",
            success: function(response) {
                if(response.success) {
                    $('#rolesTable').load(location.href + ' #rolesTable', function() {
                        updatePagination();
                        showToast(response.message, 'success', 5000);
                    });
                    $('#addRoleModal').modal('hide');
                    $('.modal-backdrop').remove();
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function() {
                showToast('System error occurred. Please try again.', 'error');
            },

        });
    });
</script>
