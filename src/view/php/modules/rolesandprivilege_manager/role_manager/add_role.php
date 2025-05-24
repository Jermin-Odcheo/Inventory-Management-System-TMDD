<?php
session_start();
require_once('../../../../../../config/ims-tmdd.php');

//@current_user_id is For audit logs
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo "<p class='text-danger'>Unauthorized access.</p>";
    $pdo->exec("SET @current_user_id = NULL");
    exit();
} else {
    $pdo->exec("SET @current_user_id = " . (int)$userId);
}
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

// Process form submission via POST and return JSON response.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $userId   = $_SESSION['user_id'] ?? null;
    $roleName = trim($_POST['role_name']);

    if (empty($roleName)) {
        echo json_encode(['success' => false, 'message' => 'Role name is required.']);
        exit();
    }

    try {
        // 1) Start transaction
        $pdo->beginTransaction();

        // 2) Check for duplicate
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE Role_Name = ? AND is_disabled = 0");
        $stmt->execute([$roleName]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Role already exists.']);
            exit();
        }

        // 3) Insert new role
        $stmt = $pdo->prepare("INSERT INTO roles (Role_Name, is_disabled) VALUES (?, 0)");
        if (!$stmt->execute([$roleName])) {
            throw new Exception('Error inserting role.');
        }
        $roleID = $pdo->lastInsertId();

        // 4) Fetch the freshly inserted row (only the columns we need)
        $stmt    = $pdo->prepare("SELECT id, Role_Name FROM roles WHERE id = ?");
        $stmt->execute([$roleID]);
        $newRole = $stmt->fetch(PDO::FETCH_ASSOC);

        // 5) Build the audit payload in the exact shape formatNewValue() expects
        $newValueArray = [
            'role_id'                => $newRole['id'],
            'role_name'              => $newRole['Role_Name'],
            // if you have default privileges on create, fetch them here; otherwise leave empty
            'modules_and_privileges' => []
        ];
        $newValue = json_encode($newValueArray);

        // 6) Insert into audit_log
        $detailsMessage = "Role '{$roleName}' has been created";
        $stmt = $pdo->prepare("
            INSERT INTO audit_log
              (UserID, EntityID, Action, Details, OldVal, NewVal, Module, Date_Time, Status)
            VALUES
              (?, ?, 'Create', ?, NULL, ?, 'Roles and Privileges', NOW(), 'Successful')
        ");
        $stmt->execute([
            $userId,
            $roleID,
            $detailsMessage,
            $newValue
        ]);

        // 7) (Optional) legacy role_changes table
        $stmt = $pdo->prepare("
            INSERT INTO role_changes (UserID, RoleID, Action, NewRoleName)
            VALUES (?, ?, 'Add', ?)
        ");
        $stmt->execute([$userId, $roleID, $roleName]);

        // 8) Commit & respond
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Role created successfully.']);
        exit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Duplicate-entry check
        if ($e->getCode() === '23000' && strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $errMsg = 'Role Name already exists.';
        } else {
            $errMsg = 'Database error: ' . $e->getMessage();
        }
        echo json_encode(['success' => false, 'message' => $errMsg]);
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
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
                    showToast(response.message, 'success', 5000);
                    // Close the modal before refreshing table
                    $('#addRoleModal').modal('hide');
                    $('body').removeClass('modal-open');
                    $('body').css('overflow', '');
                    $('body').css('padding-right', '');
                    $('.modal-backdrop').remove();
                    
                    // Refresh the table without reloading the whole page
                    window.parent.refreshRolesTable();
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
