<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

if (!isset($_SESSION['user_id'])) {
    echo "<p class='text-danger'>Unauthorized access.</p>";
    exit();
}

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role_name = trim($_POST['role_name']);

    if (empty($role_name)) {
        $error = "Role name is required.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE Role_Name = ?");
            $stmt->execute([$role_name]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Role already exists.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO roles (Role_Name) VALUES (?)");
                if ($stmt->execute([$role_name])) {
                    echo "<script>window.location.reload();</script>";
                    exit();
                } else {
                    $error = "Error inserting role. Please try again.";
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!-- Display errors if any -->
<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Role addition form -->
<form id="addRoleForm" method="POST">
    <div class="mb-3">
        <label for="role_name" class="form-label">Role Name</label>
        <input type="text" name="role_name" id="role_name" class="form-control" placeholder="Enter role name" required>
    </div>
    <button type="submit" class="btn btn-primary">Add Role</button>
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
</form>

<script>
    // Submit form via AJAX
    $("#addRoleForm").submit(function(e) {
        e.preventDefault();
        $.post("add_role.php", $(this).serialize(), function(response) {
            $("#addRoleContent").html(response);
        });
    });
</script>
