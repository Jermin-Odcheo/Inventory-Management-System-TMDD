<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
require_once('../../clients/admins/RBACService.php');

// Auth guard
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    echo 'Unauthorized';
    exit;
}
$userId = (int)$userId;
 

$moduleId = isset($_GET['module_id']) ? (int)$_GET['module_id'] : 0;
if ($moduleId <= 0) {
    echo 'Invalid module ID.';
    exit;
}

// Fetch module details.
$stmt = $pdo->prepare("SELECT id, module_name FROM modules WHERE id = :module_id");
$stmt->execute(['module_id' => $moduleId]);
$module = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$module) {
    echo 'Module not found.';
    exit;
}

// Fetch all available privileges.
$stmt = $pdo->query("SELECT id, priv_name FROM privileges ORDER BY priv_name");
$allPrivileges = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch current module privileges (for module-level, role_id = 0).
$stmt = $pdo->prepare("SELECT privilege_id FROM role_module_privileges WHERE module_id = :module_id AND role_id = 0");
$stmt->execute(['module_id' => $moduleId]);
$currentPrivileges = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
$currentPrivileges = array_map('intval', $currentPrivileges);
?>
<div class="modal-header">
    <h5 class="modal-title">Edit Privileges for Module: <?php echo htmlspecialchars($module['module_name']); ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <form id="editModulePrivilegesForm">
        <input type="hidden" name="module_id" value="<?php echo $moduleId; ?>">
        <div class="mb-3">
            <label class="form-label">Select Privileges:</label>
            <div class="row">
                <?php foreach ($allPrivileges as $privilege): ?>
                    <div class="col-12 col-md-6">
                        <div class="form-check custom-checkbox">
                            <input class="form-check-input"
                                type="checkbox"
                                name="privileges[]"
                                value="<?php echo $privilege['id']; ?>"
                                id="edit_privilege_<?php echo $privilege['id']; ?>"
                                <?php echo in_array((int)$privilege['id'], $currentPrivileges) ? 'checked' : ''; ?>>
                            <label class="form-check-label"
                                for="edit_privilege_<?php echo $privilege['id']; ?>">
                                <?php echo htmlspecialchars($privilege['priv_name']); ?>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
    <div id="editModulePrivilegesAlert"></div>
</div>

<script>
    $('#editModulePrivilegesForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'update_privileges.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#privilegeTable').load(location.href + ' #privilegeTable', function() {
                        updatePagination();
                        showToast(response.message, 'success', 5000);
                    });
                    $('#editModuleModal').modal('hide');
                    $('.modal-backdrop').remove();
                } else {
                    showToast(response.message || 'An error occurred', 'error', 5000);
                }
            },
            error: function(xhr, status, error) {
                console.error('Update Privileges AJAX Error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                
                let errorMessage = 'Error updating privileges';
                try {
                    // Try to parse the response as JSON
                    const response = JSON.parse(xhr.responseText);
                    if (response && response.message) {
                        errorMessage = response.message;
                    }
                } catch (e) {
                    // If parsing fails, use the raw error
                    console.error('JSON Parse Error:', e);
                    errorMessage += ': ' + error;
                }
                
                showToast(errorMessage, 'error', 5000);
            }
        });
    });
</script>