<?php
/**
 * Edit Role Script
 *
 * This script handles the editing of an existing role within the system. It fetches role details,
 * associated privileges, and available modules and privileges for display in an edit form.
 * Upon form submission, it updates the role's name and privileges, logs the changes, and returns
 * the updated information via AJAX.
 */
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../../../../../../config/ims-tmdd.php');

/**
 * Authentication Check
 *
 * Ensures that the user is authenticated by checking for a valid user ID in the session.
 * Sets the user ID for database context or returns an error if not authenticated.
 */
$userId = $_SESSION['user_id'] ?? null; // User ID from session for authentication check
if (!$userId) {
    $pdo->exec("SET @current_user_id = NULL");
    die(json_encode(['success' => false, 'message' => 'Unauthorized access.']));
} else {
    $pdo->exec("SET @current_user_id = " . (int)$userId);
}

/**
 * Validate Role ID
 *
 * Checks if a role ID is provided in the request. Returns an error if not provided.
 */
if (!isset($_GET['id'])) {
    die(json_encode(['success' => false, 'message' => 'Role ID not provided.']));
}
$roleID = $_GET['id']; // Role ID from GET request to identify the role to edit
$ipAddress = $_SERVER['REMOTE_ADDR']; // Client IP address for logging purposes
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

/**
 * Fetch Role Details
 *
 * Retrieves the details of the specified role from the database. Returns an error if the role is not found or is disabled.
 */
$stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ? AND is_disabled = 0");
$stmt->execute([$roleID]);
$role = $stmt->fetch(); // Role details fetched from database
if (!$role) {
    die(json_encode(['success' => false, 'message' => 'Role not found or has been deleted.']));
}

/**
 * Fetch Current Privileges
 *
 * Retrieves all privileges currently assigned to the role, including module and privilege details.
 */
$stmtCurrent = $pdo->prepare("
    SELECT rp.module_id, rp.privilege_id, p.priv_name, m.module_name
    FROM role_module_privileges rp
    JOIN privileges p ON rp.privilege_id = p.id
    JOIN modules m ON rp.module_id = m.id
    WHERE rp.role_id = ?
");
$stmtCurrent->execute([$roleID]);
$currentPrivileges = $stmtCurrent->fetchAll(PDO::FETCH_ASSOC); // Array of current privileges assigned to the role

/**
 * Create Privilege Combinations
 *
 * Creates a simple array of module|privilege combinations for easy checking of assigned privileges.
 */
$currentPrivilegeCombos = array_map(function($item) {
    return $item['module_id'] . '|' . $item['privilege_id'];
}, $currentPrivileges); // Array of module|privilege strings for quick lookup

/**
 * Fetch All Modules
 *
 * Retrieves all available modules from the database for display in the edit form.
 */
$stmtModules = $pdo->query("SELECT * FROM modules ORDER BY id");
$modules = $stmtModules->fetchAll(PDO::FETCH_ASSOC); // Array of all modules available in the system

/**
 * Fetch All Privileges
 *
 * Retrieves all non-disabled privileges from the database for display in the edit form.
 */
$stmtPrivileges = $pdo->query("SELECT * FROM privileges WHERE is_disabled = 0 ORDER BY priv_name");
$allPrivileges = $stmtPrivileges->fetchAll(PDO::FETCH_ASSOC); // Array of all non-disabled privileges

/**
 * Identify Specific Module and Privilege IDs
 *
 * Identifies specific module and privilege IDs for validation purposes (e.g., Audit module with Track privilege,
 * Reports module with View and Export privileges).
 */
$auditModuleId = null; // ID of the Audit module
$trackPrivilegeId = null; // ID of the Track privilege
foreach ($modules as $mod) {
    if (strtolower($mod['module_name']) === 'audit') {
        $auditModuleId = $mod['id'];
        break;
    }
}
foreach ($allPrivileges as $priv) {
    if (strtolower($priv['priv_name']) === 'track') {
        $trackPrivilegeId = $priv['id'];
        break;
    }
}

$reportModuleId = null; // ID of the Reports module
$exportPrivilegeId = null; // ID of the Export privilege
$viewPrivilegeId = null; // ID of the View privilege

foreach ($modules as $mod) {
    if (strtolower($mod['module_name']) === 'reports') {
        $reportModuleId = $mod['id'];
        break;
    }
}
foreach ($allPrivileges as $priv) {
    if (strtolower($priv['priv_name']) === 'export') {
        $exportPrivilegeId = $priv['id'];
        break;
    }
}
foreach ($allPrivileges as $priv) {
    if (strtolower($priv['priv_name']) === 'view') {
        $viewPrivilegeId = $priv['id'];
        break;
    }
}

/**
 * Handle Role Update
 *
 * Processes the form submission for updating the role's name and privileges. Validates input,
 * checks for duplicates, updates the database, and logs changes for audit purposes.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $roleName = trim($_POST['role_name']); // Trimmed role name from form submission
    $selected = $_POST['privileges'] ?? []; // Array of selected privileges from form submission

    if (empty($roleName)) {
        echo json_encode(['success' => false, 'message' => 'Role name cannot be empty.']);
        exit;
    }

    // Check for duplicate role name
    $stmtDuplicate = $pdo->prepare("SELECT id FROM roles WHERE role_name = ? AND id != ? AND is_disabled = 0");
    $stmtDuplicate->execute([$roleName, $roleID]);
    if ($stmtDuplicate->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'message' => 'Role name already exists.']);
        exit;
    }

    // Filter privileges for Audit and Reports modules accordingly
    $filteredSelected = []; // Array to store filtered privileges based on module rules
    foreach ($selected as $value) {
        list($moduleID, $privilegeID) = explode('|', $value); // Split value into module and privilege IDs

        // For Audit module, only allow Track privilege
        if ($auditModuleId && $moduleID == $auditModuleId) {
            if ($privilegeID == $trackPrivilegeId) {
                $filteredSelected[] = $value;
            }
            continue;
        }

        // For Reports module, only allow View and Export privileges
        if ($reportModuleId && $moduleID == $reportModuleId) {
            if ($privilegeID == $viewPrivilegeId || $privilegeID == $exportPrivilegeId) {
                $filteredSelected[] = $value;
            }
            // skip other privileges for report module
            continue;
        }

        // For all other modules, allow all privileges
        $filteredSelected[] = $value;
    }
    $selected = $filteredSelected; // Update selected privileges with filtered list



    try {
        $pdo->beginTransaction();

        // Store original role data for audit
        $stmtOldRole = $pdo->prepare("SELECT id, role_name FROM roles WHERE id = ?");
        $stmtOldRole->execute([$roleID]);
        $oldRole = $stmtOldRole->fetch(PDO::FETCH_ASSOC); // Original role data before update
        
        // Format old privileges for audit log
        $formattedOldPrivileges = []; // Array to store formatted old privileges by module
        foreach ($currentPrivileges as $priv) {
            if (!isset($formattedOldPrivileges[$priv['module_name']])) {
                $formattedOldPrivileges[$priv['module_name']] = [];
            }
            $formattedOldPrivileges[$priv['module_name']][] = $priv['priv_name'];
        }
        
        $oldRoleData = [
            'role_id' => $oldRole['id'],
            'role_name' => $oldRole['role_name'],
            'privileges' => $formattedOldPrivileges
        ]; // Structured old role data for audit
        $oldValue = json_encode($oldRoleData, JSON_PRETTY_PRINT); // JSON encoded old role data

        // Update role name
        $stmtUpdate = $pdo->prepare("UPDATE roles SET role_name = ? WHERE id = ?");
        $stmtUpdate->execute([$roleName, $roleID]);

        // Remove old privileges for this role
        $stmtDelete = $pdo->prepare("DELETE FROM role_module_privileges WHERE role_id = ?");
        $stmtDelete->execute([$roleID]);

        // Insert new privileges for this role
        $stmtInsert = $pdo->prepare("
            INSERT INTO role_module_privileges (role_id, module_id, privilege_id)
            VALUES (?, ?, ?)
        ");
        foreach ($selected as $value) {
            list($moduleID, $privilegeID) = explode('|', $value);
            $stmtInsert->execute([$roleID, $moduleID, $privilegeID]);
        }

        // Get updated role data for audit log
        $stmtNewRole = $pdo->prepare("SELECT id, role_name FROM roles WHERE id = ?");
        $stmtNewRole->execute([$roleID]);
        $newRole = $stmtNewRole->fetch(PDO::FETCH_ASSOC); // Updated role data after changes
        
        // Fetch new privileges for audit log
        $stmtNewPrivs = $pdo->prepare("
            SELECT m.module_name, p.priv_name 
            FROM role_module_privileges rmp
            JOIN modules m ON m.id = rmp.module_id
            JOIN privileges p ON p.id = rmp.privilege_id
            WHERE rmp.role_id = ?
            ORDER BY m.module_name, p.priv_name
        ");
        $stmtNewPrivs->execute([$roleID]);
        $newPrivilegesData = $stmtNewPrivs->fetchAll(PDO::FETCH_ASSOC); // Updated privileges after changes
        
        // Format new privileges for audit log
        $formattedNewPrivileges = []; // Array to store formatted new privileges by module
        foreach ($newPrivilegesData as $priv) {
            if (!isset($formattedNewPrivileges[$priv['module_name']])) {
                $formattedNewPrivileges[$priv['module_name']] = [];
            }
            $formattedNewPrivileges[$priv['module_name']][] = $priv['priv_name'];
        }
        
        // Create a list of modified fields for the details
        $modifiedFields = []; // Array to store names of modified fields for audit details
        
        // Check if role name was changed
        if ($oldRole['role_name'] !== $newRole['role_name']) {
            $modifiedFields[] = "Role Name";
        }
        
        // Check for changed privileges
        $privilegeChanges = []; // Array to store changes in privileges by module
        $allModules = array_unique(array_merge(array_keys($formattedOldPrivileges), array_keys($formattedNewPrivileges))); // All unique module names
        
        foreach ($allModules as $module) {
            $oldModulePrivs = $formattedOldPrivileges[$module] ?? []; // Old privileges for this module
            $newModulePrivs = $formattedNewPrivileges[$module] ?? []; // New privileges for this module
            
            $added = array_diff($newModulePrivs, $oldModulePrivs); // Privileges added
            $removed = array_diff($oldModulePrivs, $newModulePrivs); // Privileges removed
            
            if (!empty($added) || !empty($removed)) {
                $privilegeChanges[$module] = [
                    'added' => $added,
                    'removed' => $removed
                ];
            }
        }
        
        // Add privilege changes to modified fields
        foreach ($privilegeChanges as $module => $changes) {
            if (!empty($changes['added'])) {
                $modifiedFields[] = "$module: Added " . implode(", ", $changes['added']);
            }
            if (!empty($changes['removed'])) {
                $modifiedFields[] = "$module: Removed " . implode(", ", $changes['removed']);
            }
        }
        
        // Generate a clear, concise details message
        $details = "Modified Fields: " . implode(", ", $modifiedFields); // Detailed message of changes for audit
        
        // Create new value data for audit log
        $newValue = json_encode([
            'role_id' => $newRole['id'],
            'role_name' => $newRole['role_name'],
            'privileges' => $formattedNewPrivileges
        ], JSON_PRETTY_PRINT); // JSON encoded new role data

        // Log to audit_log table
        $stmtAuditLog = $pdo->prepare("INSERT INTO audit_log 
            (UserID, EntityID, Action, Details, OldVal, NewVal, Module, Date_Time, Status) 
            VALUES (?, ?, 'Modified', ?, ?, ?, 'Roles and Privileges', NOW(), 'Successful')");
        $stmtAuditLog->execute([
            $userId,
            $roleID,
            $details,
            $oldValue,
            $newValue
        ]);

        // Log changes in role_changes table
        $stmtLog = $pdo->prepare("
            INSERT INTO role_changes (UserID, RoleID, Action, OldRoleName, NewRoleName, OldPrivileges, NewPrivileges)
            VALUES (?, ?, 'Modified', ?, ?, ?, ?)
        ");
        $stmtLog->execute([
            $userId,
            $roleID,
            $role['role_name'],
            $roleName,
            json_encode($currentPrivilegeCombos),
            json_encode($selected)
        ]);

        $pdo->commit();

        // Fetch updated privileges for display in the UI
        $stmtUpdated = $pdo->prepare("
            SELECT m.module_name, p.priv_name 
            FROM role_module_privileges rp
            JOIN modules m ON rp.module_id = m.id
            JOIN privileges p ON rp.privilege_id = p.id
            WHERE rp.role_id = ?
        ");
        $stmtUpdated->execute([$roleID]);
        $updatedPrivileges = $stmtUpdated->fetchAll(PDO::FETCH_ASSOC); // Updated privileges for UI display

        echo json_encode([
            'success' => true,
            'message' => 'Role updated successfully.',
            'role_id' => $roleID,
            'role_name' => $roleName,
            'privileges' => $updatedPrivileges
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error updating role: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error updating role: ' . $e->getMessage()]);
        exit;
    }
}
?>

<!-- Modal Content -->
<div class="modal-content border-0 shadow-lg rounded-4">
    <div class="modal-header bg-primary py-3 px-4 border-0">
        <h5 class="modal-title text-white m-0 d-flex align-items-center">
            <i class="bi bi-shield-lock me-2"></i>Edit Role
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>

    <div class="modal-body p-4">
        <form id="editRoleForm" method="POST">
            <!-- Role name input -->
            <div class="form-floating mb-4">
                <input type="text"
                       class="form-control"
                       id="role_name"
                       name="role_name"
                       placeholder="Role Name"
                       value="<?php echo htmlspecialchars($role['role_name']); ?>"
                       required>
                <label for="role_name">Role Name</label>
            </div>

            <!-- Permissions section -->
            <h6 class="mb-3">Permissions</h6>

            <!-- Module Navigation Tabs -->
            <ul class="nav nav-tabs mb-3" id="modulesTabs" role="tablist">
                <?php foreach ($modules as $index => $mod): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo ($index === 0) ? 'active' : ''; ?>"
                                id="module-<?php echo $mod['id']; ?>-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#module-<?php echo $mod['id']; ?>"
                                type="button"
                                role="tab">
                            <?php echo htmlspecialchars($mod['module_name']); ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>

         <!-- Tab Content -->
<div class="tab-content" id="modulesContent">
    <?php foreach ($modules as $index => $mod): ?>
        <div class="tab-pane fade <?php echo ($index === 0) ? 'show active' : ''; ?>"
             id="module-<?php echo $mod['id']; ?>"
             role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span><?php echo htmlspecialchars($mod['module_name']); ?></span>
                    <span class="badge bg-light text-primary">Module</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php 
                        // Get privileges for this module from role_module_privileges
                        $stmt = $pdo->prepare("
                            SELECT DISTINCT p.* 
                            FROM privileges p 
                            LEFT JOIN role_module_privileges rmp ON p.id = rmp.privilege_id 
                            WHERE rmp.module_id = ? OR p.is_disabled = 0
                            ORDER BY p.id
                        ");
                        $stmt->execute([$mod['id']]);
                        $modulePrivileges = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($modulePrivileges as $priv): 

                            // For Audit module, only show Track privilege
                            if ($mod['id'] == $auditModuleId) {
                                if (strtolower($priv['priv_name']) !== 'track') {
                                    continue;
                                }
                                // Render Track privilege checkbox for Audit module
                                $value = $mod['id'] . '|' . $priv['id'];
                                $isChecked = in_array($value, $currentPrivilegeCombos);

                                $iconClass = 'bi-graph-up'; // icon for Track
                                ?>
                                <div class="col-md-4 col-lg-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               role="switch"
                                               name="privileges[]"
                                               value="<?php echo $value; ?>"
                                               id="priv_<?php echo $mod['id'] . '_' . $priv['id']; ?>"
                                               <?php echo $isChecked ? 'checked' : ''; ?>>
                                        <label class="form-check-label"
                                               for="priv_<?php echo $mod['id'] . '_' . $priv['id']; ?>">
                                            <i class="bi <?php echo $iconClass; ?> me-1"></i>
                                            <?php echo htmlspecialchars($priv['priv_name']); ?>
                                        </label>
                                    </div>
                                </div>
                                <?php
                                continue;
                            }

                            // For Report module, only show View and Export privileges
                            if ($mod['id'] == $reportModuleId) {
                                $privNameLower = strtolower($priv['priv_name']);
                                if ($privNameLower !== 'view' && $privNameLower !== 'export') {
                                    continue;
                                }
                                $value = $mod['id'] . '|' . $priv['id'];
                                $isChecked = in_array($value, $currentPrivilegeCombos);

                                // Map icons for Report module View and Export privileges
                                $iconClass = 'bi-shield-check'; // default icon
                                if ($privNameLower === 'view') {
                                    $iconClass = 'bi-eye';
                                } elseif ($privNameLower === 'export') {
                                    $iconClass = 'bi-file-earmark-arrow-down';
                                }
                                ?>
                                <div class="col-md-4 col-lg-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               role="switch"
                                               name="privileges[]"
                                               value="<?php echo $value; ?>"
                                               id="priv_<?php echo $mod['id'] . '_' . $priv['id']; ?>"
                                               <?php echo $isChecked ? 'checked' : ''; ?>>
                                        <label class="form-check-label"
                                               for="priv_<?php echo $mod['id'] . '_' . $priv['id']; ?>">
                                            <i class="bi <?php echo $iconClass; ?> me-1"></i>
                                            <?php echo htmlspecialchars($priv['priv_name']); ?>
                                        </label>
                                    </div>
                                </div>
                                <?php
                                continue;
                            }

                           // For other modules, show all privileges except Export
$privNameLower = strtolower($priv['priv_name']);
if ($privNameLower === 'export') {
    continue; // skip Export here
}

$value = $mod['id'] . '|' . $priv['id'];
$isChecked = in_array($value, $currentPrivilegeCombos);

// Map privilege names to icons
$iconClass = 'bi-shield-check'; // default
switch ($privNameLower) {
    case 'track':
        $iconClass = 'bi-graph-up';
        break;
    case 'view':
        $iconClass = 'bi-eye';
        break;
    case 'create':
        $iconClass = 'bi-plus-circle';
        break;
    case 'edit':
        $iconClass = 'bi-pencil';
        break;
    case 'delete':
    case 'remove':
        $iconClass = 'bi-trash';
        break;
    case 'modify':
        $iconClass = 'bi-pencil-square';
        break;
    case 'restore':
        $iconClass = 'bi-arrow-counterclockwise';
        break;
    case 'permanently delete':
        $iconClass = 'bi-trash-fill';
        break;
}
?>
<div class="col-md-4 col-lg-3">
    <div class="form-check form-switch">
        <input class="form-check-input"
               type="checkbox"
               role="switch"
               name="privileges[]"
               value="<?php echo $value; ?>"
               id="priv_<?php echo $mod['id'] . '_' . $priv['id']; ?>"
               <?php echo $isChecked ? 'checked' : ''; ?>>
        <label class="form-check-label"
               for="priv_<?php echo $mod['id'] . '_' . $priv['id']; ?>">
            <i class="bi <?php echo $iconClass; ?> me-1"></i>
            <?php echo htmlspecialchars($priv['priv_name']); ?>
        </label>
    </div>
</div>
<?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div><!-- /.tab-pane -->
    <?php endforeach; ?>
</div><!-- /.tab-content -->

        </form>
    </div>

    <!-- Footer with actions -->
    <div class="modal-footer border-top py-3">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="editRoleForm" class="btn btn-primary">
            <i class="bi bi-check2 me-1"></i>Update Role
        </button>
    </div>
</div>

<!-- Add this to your CSS -->
<style>
    .nav-tabs .nav-link {
        color: #495057;
        font-weight: 500;
    }
    .nav-tabs .nav-link.active {
        color: #0d6efd;
        border-bottom-color: #0d6efd;
        border-bottom-width: 2px;
    }
    .form-check-input:checked {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }
</style>

<script>
    // Use window-scoped variables to avoid redeclaration issues when the modal is reopened
    window.editRoleHelpers = window.editRoleHelpers || {};
    
    // Function to capture the initial form state
    window.editRoleHelpers.captureFormState = function() {
        const roleName = $('#role_name').val();
        const checkedPrivileges = [];
        
        $('input[name="privileges[]"]:checked').each(function() {
            checkedPrivileges.push($(this).val());
        });
        
        return {
            roleName: roleName,
            privileges: checkedPrivileges.sort().join(',')
        };
    };
    
    // Function to compare form states and detect changes
    window.editRoleHelpers.hasFormChanged = function() {
        const currentState = window.editRoleHelpers.captureFormState();
        
        // Compare role name
        if (currentState.roleName !== window.editRoleHelpers.originalFormState.roleName) {
            return true;
        }
        
        // Compare privileges
        if (currentState.privileges !== window.editRoleHelpers.originalFormState.privileges) {
            return true;
        }
        
        return false;
    };
    
    // Function to get the "View" privilege checkbox for a specific module
    window.editRoleHelpers.getViewCheckboxForModule = function(moduleId) {
        // Find all privilege checkboxes for this module
        const checkboxes = $(`input[name="privileges[]"][id^="priv_${moduleId}_"]`);
        let viewCheckbox = null;
        
        // Find the one with "view" in its label
        checkboxes.each(function() {
            const label = $(`label[for="${$(this).attr('id')}"]`).text().trim().toLowerCase();
            if (label === 'view') {
                viewCheckbox = $(this);
                return false; // Break the loop
            }
        });
        
        return viewCheckbox;
    };
    
    // Function to handle privilege checkbox changes
    window.editRoleHelpers.handlePrivilegeChange = function() {
        // Get the module ID from the checkbox ID
        const checkboxId = $(this).attr('id');
        const moduleId = checkboxId.split('_')[1];
        const privilegeId = checkboxId.split('_')[2];
        const checkboxLabel = $(`label[for="${checkboxId}"]`).text().trim().toLowerCase();
        
        // Skip this logic if the current checkbox is "View"
        if (checkboxLabel === 'view') {
            return;
        }
        
        // Find the View checkbox for this specific module only
        const viewCheckbox = window.editRoleHelpers.getViewCheckboxForModule(moduleId);
        
        if (viewCheckbox) {
            // If this checkbox is checked and it's not View, make sure View is checked too
            // But ONLY for the current module
            if ($(this).prop('checked')) {
                viewCheckbox.prop('checked', true);
            }
        }
    };
    
    // Initialize the form when loaded
    $(document).ready(function() {
        // Remove any previously bound handlers to prevent duplicates
        $('input[name="privileges[]"]').off('change', window.editRoleHelpers.handlePrivilegeChange);
        
        // Capture initial state
        window.editRoleHelpers.originalFormState = window.editRoleHelpers.captureFormState();
        console.log('Original form state captured:', window.editRoleHelpers.originalFormState);
        
        // Add event listener to all privilege checkboxes
        $('input[name="privileges[]"]').on('change', window.editRoleHelpers.handlePrivilegeChange);
        
        // Run once on page load to ensure initial state is correct
        // Process each module separately to avoid cross-module effects
        const moduleIds = new Set();
        $('input[name="privileges[]"]').each(function() {
            const id = $(this).attr('id');
            const moduleId = id.split('_')[1];
            moduleIds.add(moduleId);
        });
        
        // Process each module independently
        moduleIds.forEach(function(moduleId) {
            const moduleCheckboxes = $(`input[name="privileges[]"][id^="priv_${moduleId}_"]:checked`).filter(function() {
                const label = $(`label[for="${$(this).attr('id')}"]`).text().trim().toLowerCase();
                return label !== 'view';
            });
            
            // If any non-View privileges are checked in this module, ensure View is checked for this module
            if (moduleCheckboxes.length > 0) {
                const viewCheckbox = window.editRoleHelpers.getViewCheckboxForModule(moduleId);
                if (viewCheckbox && !viewCheckbox.prop('checked')) {
                    viewCheckbox.prop('checked', true);
                }
            }
        });
    });

    // Handle the Edit Role form submission via AJAX.
    $(document).off('submit', '#editRoleForm').on('submit', '#editRoleForm', function (e) {
        e.preventDefault();
        
        // Check if form has changed
        if (!window.editRoleHelpers.hasFormChanged()) {
            showToast('No changes were made to the role.', 'info');
            return false;
        }
        
        // Process each module separately during form submission
        const moduleIds = new Set();
        
        // Get all unique module IDs from checkboxes
        $('input[name="privileges[]"]').each(function() {
            const id = $(this).attr('id');
            const moduleId = id.split('_')[1];
            moduleIds.add(moduleId);
        });
        
        // Process each module independently - avoid cross-module effects
        moduleIds.forEach(function(moduleId) {
            // Check if any non-View privileges are checked for this specific module
            const hasNonViewChecked = $(`input[name="privileges[]"][id^="priv_${moduleId}_"]:checked`).filter(function() {
                const label = $(`label[for="${$(this).attr('id')}"]`).text().trim().toLowerCase();
                return label !== 'view';
            }).length > 0;
            
            // If any non-View privileges are checked, ensure View is checked too - for this module only
            if (hasNonViewChecked) {
                const viewCheckbox = window.editRoleHelpers.getViewCheckboxForModule(moduleId);
                if (viewCheckbox && !viewCheckbox.prop('checked')) {
                    viewCheckbox.prop('checked', true);
                    
                    // Get the module name for the notification
                    const moduleName = $(`button[data-bs-target="#module-${moduleId}"]`).text().trim();
                    showToast(`"View" privilege automatically added for ${moduleName} module`, 'info');
                }
            }
        });
        
        const submitBtn = $('button[type="submit"]', this);

        // Disable button and show loading state
        submitBtn.html('<span class="spinner-border spinner-border-sm me-2"></span> Updating...');
        submitBtn.prop('disabled', true);

        $.ajax({
            url: 'edit_roles.php?id=<?php echo $roleID; ?>',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    submitBtn.blur();
                    // Hide the modal using Bootstrap's modal method
                    $('#editRoleModal').modal('hide');
                    $('body').removeClass('modal-open');
                    $('body').css('overflow', '');
                    $('body').css('padding-right', '');
                    $('.modal-backdrop').remove();
                    
                    // Show success message
                    showToast(response.message, 'success');
                    
                    // Refresh the table without reloading the whole page
                    window.parent.refreshRolesTable();
                } else {
                    showToast(response.message || 'An error occurred while updating the role', 'error');
                }
            },
            error: function () {
                showToast('System error occurred. Please try again.', 'error');
            },
            complete: function () {
                // Restore button state.
                submitBtn.html('<i class="bi bi-check2 me-1"></i>Update Role');
                submitBtn.prop('disabled', false);
            }
        });
    });

    // Remove lingering modal backdrop when any modal is hidden.
    $('#editRoleModal, #addRoleModal, #confirmDeleteModal').on('hidden.bs.modal', function () {
        setTimeout(function() {
            $('body').removeClass('modal-open');
            $('body').css('overflow', '');
            $('body').css('padding-right', '');
            $('.modal-backdrop').remove();
        }, 100);
    });
</script>
