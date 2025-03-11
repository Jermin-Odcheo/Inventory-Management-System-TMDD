<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

if (!isset($_SESSION['user_id'])) {
    echo 'Unauthorized';
    exit;
}

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
            <?php foreach($allPrivileges as $privilege): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="privileges[]" value="<?php echo $privilege['id']; ?>"
                        <?php echo in_array($privilege['id'], $currentPrivileges) ? 'checked' : ''; ?>>
                    <label class="form-check-label"><?php echo htmlspecialchars($privilege['priv_name']); ?></label>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
    <div id="editModulePrivilegesAlert"></div>
</div>
<script>
    $('#editModulePrivilegesForm').on('submit', function(e){
        e.preventDefault();
        $.ajax({
            url: 'update_privileges.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response){
                if(response.success){
                    $('#editModulePrivilegesAlert').html('<div class="alert alert-success">'+response.message+'</div>');
                    setTimeout(function(){
                        location.reload();
                    }, 1500);
                } else {
                    $('#editModulePrivilegesAlert').html('<div class="alert alert-danger">'+response.message+'</div>');
                }
            },
            error: function(){
                $('#editModulePrivilegesAlert').html('<div class="alert alert-danger">An error occurred. Please try again.</div>');
            }
        });
    });
</script>
