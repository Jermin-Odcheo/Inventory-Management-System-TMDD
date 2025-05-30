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
    
    // Additional validation and debugging for role name
    $originalRoleName = $_POST['role_name']; // Before trim
    $trimmedRoleName = trim($originalRoleName); // After trim
    
    // Check for invisible characters or encoding issues
    $hexEncoded = bin2hex($trimmedRoleName);
    $unicodePoints = [];
    for ($i = 0; $i < mb_strlen($trimmedRoleName, 'UTF-8'); $i++) {
        $char = mb_substr($trimmedRoleName, $i, 1, 'UTF-8');
        $unicodePoints[] = 'U+' . dechex(mb_ord($char));
    }

    try {
        // 1) Start transaction
        $pdo->beginTransaction();

        // 2) Check for duplicate (case-insensitive) with improved debugging
        $checkQuery = "SELECT COUNT(*) FROM roles 
                      WHERE LOWER(TRIM(Role_Name)) = LOWER(TRIM(?)) 
                      AND is_disabled = 0";
        $stmt = $pdo->prepare($checkQuery);
        $stmt->execute([$roleName]);
        $count = $stmt->fetchColumn();
        
        // Also check if there are any roles with similar names (ignoring case and whitespace)
        $similarQuery = "SELECT id, Role_Name, HEX(Role_Name) as hex_name 
                        FROM roles 
                        WHERE (SOUNDEX(Role_Name) = SOUNDEX(?) 
                        OR LOWER(REPLACE(TRIM(Role_Name), ' ', '')) = LOWER(REPLACE(TRIM(?), ' ', '')))
                        AND is_disabled = 0
                        LIMIT 5";
        $similarStmt = $pdo->prepare($similarQuery);
        $similarStmt->execute([$roleName, $roleName]);
        $similarRoles = $similarStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($count > 0) {
            // Let's find what role is matching to help debugging
            $debugStmt = $pdo->prepare("SELECT id, Role_Name, HEX(Role_Name) as hex_name, 
                                      LENGTH(Role_Name) as name_length,
                                      LENGTH(TRIM(Role_Name)) as trimmed_length
                                      FROM roles 
                                      WHERE LOWER(TRIM(Role_Name)) = LOWER(TRIM(?)) 
                                      AND is_disabled = 0");
            $debugStmt->execute([$roleName]);
            $matchingRole = $debugStmt->fetch(PDO::FETCH_ASSOC);
            
            // Return detailed error with the matching role information
            echo json_encode([
                'success' => false, 
                'message' => 'Role already exists.', 
                'debug' => [
                    'input_role' => [
                        'original' => $originalRoleName,
                        'trimmed' => $trimmedRoleName,
                        'hex' => $hexEncoded,
                        'unicode' => $unicodePoints,
                        'length_original' => strlen($originalRoleName),
                        'length_trimmed' => strlen($trimmedRoleName)
                    ],
                    'matching_role' => [
                        'name' => $matchingRole['Role_Name'] ?? 'Unknown',
                        'hex' => $matchingRole['hex_name'] ?? 'Unknown',
                        'id' => $matchingRole['id'] ?? 'Unknown',
                        'length' => $matchingRole['name_length'] ?? 0,
                        'trimmed_length' => $matchingRole['trimmed_length'] ?? 0
                    ],
                    'similar_roles' => $similarRoles,
                    'query' => $checkQuery
                ]
            ]);
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
<form id="addRoleForm" method="POST">
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
        // Prevent the default form submission
        e.preventDefault();
        
        // Disable the submit button to prevent double submission
        $(this).find('button[type="submit"]').prop('disabled', true);
        
        $.ajax({
            url: "add_role.php",
            type: "POST",
            data: $(this).serialize(),
            dataType: "json",
            success: function(response) {
                if(response.success) {
                    // Show only success message
                    showToast(response.message, 'success', 5000);
                    
                    // Close the modal before refreshing table
                    $('#addRoleModal').modal('hide');
                    
                    // Clean up modal elements
                    setTimeout(function() {
                        $('body').removeClass('modal-open');
                        $('body').css('overflow', '');
                        $('body').css('padding-right', '');
                        $('.modal-backdrop').remove();
                        
                        // Refresh the table without reloading the whole page
                        if (typeof window.parent.refreshRolesTable === 'function') {
                            window.parent.refreshRolesTable();
                        }
                    }, 300);
                } else {
                    // Re-enable the submit button on error
                    $("#addRoleForm").find('button[type="submit"]').prop('disabled', false);
                    
                    // Check if we have debug information
                    if (response.debug) {
                        console.log('Role creation debug info:', response.debug);
                        
                        // Create a simplified error message
                        let errorMessage = 'Role "' + response.debug.input_role.trimmed + '" already exists.';
                        
                        showToast(errorMessage, 'error', 5000);
                    } else {
                        showToast(response.message, 'error', 5000);
                    }
                }
            },
            error: function() {
                // Re-enable the submit button on error
                $("#addRoleForm").find('button[type="submit"]').prop('disabled', false);
                showToast('System error occurred. Please try again.', 'error', 5000);
            },
            complete: function() {
                // Additional cleanup if needed
            }
        });
    });
</script>
