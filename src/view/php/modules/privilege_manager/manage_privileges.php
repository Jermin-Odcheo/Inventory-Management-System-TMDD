<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
include '../../general/header.php';

// Query to fetch modules with their module-level privileges (role_id = 0)
$sql = "
SELECT 
    m.id AS Module_ID,
    m.module_name AS Module_Name,
    COALESCE((
      SELECT GROUP_CONCAT(p.priv_name ORDER BY p.priv_name SEPARATOR ', ')
      FROM role_module_privileges rmp
      JOIN privileges p ON p.id = rmp.privilege_id
      WHERE rmp.module_id = m.id AND rmp.role_id = 0
    ), 'No privileges') AS Privileges
FROM modules m
ORDER BY m.id
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Module Privilege Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 300px;
            background-color: #2c3e50;
            color: #fff;
        }
        .main-content {
            flex: 1;
            padding: 20px;
            margin-left: 300px;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <?php include '../../general/sidebar.php'; ?>
    </div>

    <!-- Main Content Area -->
    <div class="main-content container-fluid">
        <h1>Module Privilege Management</h1>
        <!-- Alert container -->
        <div id="alertMessage" style="position: fixed; top: 20px; right: 20px; z-index: 1050;"></div>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                <tr>
                    <th>Module ID</th>
                    <th>Module Name</th>
                    <th>Privileges</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if(!empty($modules)): ?>
                    <?php foreach($modules as $module): ?>
                        <tr data-module-id="<?php echo $module['Module_ID']; ?>">
                            <td><?php echo htmlspecialchars($module['Module_ID']); ?></td>
                            <td><?php echo htmlspecialchars($module['Module_Name']); ?></td>
                            <td><?php echo htmlspecialchars($module['Privileges']); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning edit-module-btn"
                                        data-module-id="<?php echo $module['Module_ID']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#editModuleModal">
                                    Edit Privileges
                                </button>
                                <button type="button" class="btn btn-sm btn-danger delete-module-btn"
                                        data-module-id="<?php echo $module['Module_ID']; ?>">
                                    Delete Module
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4">No modules found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div><!-- .table-responsive -->
    </div><!-- .main-content -->
</div><!-- .wrapper -->

<!-- Edit Module Privileges Modal -->
<div class="modal fade" id="editModuleModal" tabindex="-1" aria-labelledby="editModuleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div id="editModuleContent">
                Loading...
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function(){
        // When "Edit Privileges" is clicked, load the edit form via AJAX.
        $('.edit-module-btn').on('click', function(){
            var moduleID = $(this).data('module-id');
            $('#editModuleContent').html("Loading...");
            $.ajax({
                url: 'edit_module_privileges.php',
                type: 'GET',
                data: { module_id: moduleID },
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function(response){
                    $('#editModuleContent').html(response);
                },
                error: function(xhr, status, error){
                    $('#editModuleContent').html('<p class="text-danger">Error loading module privileges. Please try again.</p>');
                }
            });
        });

        // Handle deletion of a module.
        $('.delete-module-btn').on('click', function(){
            if(!confirm('Are you sure you want to delete this module?')){
                return;
            }
            var moduleID = $(this).data('module-id');
            $.ajax({
                url: 'delete_module.php',
                type: 'POST',
                data: { module_id: moduleID },
                dataType: 'json',
                success: function(response){
                    if(response.success){
                        // Use toast notification instead of alert
                        showToast(response.message, 'success');
                        // Optionally, reload page after a short delay so the toast can be seen
                        setTimeout(function(){
                            location.reload();
                        }, 3000);
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function(){
                    showToast('Error deleting module.', 'error');
                }
            });
        });

    });
</script>
<script>
    $(document).ready(function() {
        // Test toast on page load
        showToast('Test toast message!', 'success');
    });
</script>


<?php include '../../general/footer.php';?>
</body>
</html>
