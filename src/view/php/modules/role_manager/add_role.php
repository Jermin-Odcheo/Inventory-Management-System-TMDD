<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

if (!isset($_SESSION['user_id'])) {
    echo "<p class='text-danger'>Unauthorized access.</p>";
    exit();
}

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
<form id="addRoleForm" method="POST">
    <div class="mb-3">
        <label for="role_name" class="form-label">Role Name</label>
        <input type="text" name="role_name" id="role_name" class="form-control" placeholder="Enter role name" required>
    </div>
    <button type="submit" class="btn btn-primary">Add Role</button>
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
</form>

<script>
    // Submit form via AJAX with toast notifications.
    $("#addRoleForm").submit(function(e) {
        e.preventDefault();
        const submitBtn = $("button[type='submit']", this);
        submitBtn.prop('disabled', true);
        submitBtn.html('<span class="spinner-border spinner-border-sm me-2"></span> Adding...');
        $.ajax({
            url: "add_role.php",
            type: "POST",
            data: $(this).serialize(),
            dataType: "json",
            success: function(response) {
                if(response.success) {
                    $('#rolesTable').load(location.href + ' #rolesTable', function() {
                        showToast(response.message, 'success');
                    });
                    $('#addRoleModal').modal('hide');
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function() {
                showToast('System error occurred. Please try again.', 'error');
            },
            complete: function() {
                submitBtn.prop('disabled', false);
                submitBtn.html('Add Role');
            }
        });
    });
</script>
