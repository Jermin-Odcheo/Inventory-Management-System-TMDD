<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check database connection
if (!isset($pdo)) {
    die("Database connection is not established.");
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../../../public/index.php");
    exit();
}

/*
    Query the audit_log to fetch audit records for users that are soft deleted.
    We join with the users table (u) to ensure the user is soft-deleted,
    and join with the operator table (op) to get details of who performed the action.
    We then select only the latest audit record per soft-deleted user.
*/
$query = "
    SELECT 
        a.TrackID AS track_id,
        CONCAT(op.First_Name, ' ', op.Last_Name) AS operator_name,
        op.Email AS operator_email,
        a.Module AS module,
        a.Action AS action,
        a.Details AS details,
        a.NewVal AS new_val,
        a.Status AS status,
        a.Date_Time AS date_time,
        u.User_ID AS deleted_user_id
    FROM audit_log a
    JOIN users u ON a.EntityID = u.User_ID
    JOIN users op ON a.UserID = op.User_ID
    WHERE u.is_deleted = 1
      AND a.TrackID = (
            SELECT MAX(a2.TrackID)
            FROM audit_log a2
            WHERE a2.EntityID = a.EntityID
        )
    ORDER BY a.Date_Time DESC
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$logs) {
        $logs = [];
    }
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

/**
 * Function to format the new value from the audit log.
 * Decodes the JSON string and outputs key: value pairs.
 */
function formatNewValue($jsonStr) {
    // Ensure that $jsonStr is a string (or an empty string if null)
    $jsonStr = $jsonStr ?? '';
    $data = json_decode($jsonStr, true);
    if (!is_array($data)) {
        return htmlspecialchars($jsonStr);
    }
    $output = "";
    foreach ($data as $key => $value) {
        // Cast $value to string if necessary
        $output .= "<strong>" . htmlspecialchars($key) . ":</strong> " . htmlspecialchars((string)$value) . "<br>";
    }
    return $output;
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Archived Users Audit Log</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- jQuery, Bootstrap CSS/JS, and Bootstrap Icons -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Updated styles */
        .main-content {
            margin-left: 300px;
            padding: 2rem;
        }

        .content-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .page-title {
            color: #333;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #eee;
        }

        /* Updated button styles */
        /* Updated button styles */
        .btn-group-actions {
            display: flex;
            gap: 8px;
        }

        .btn-group-actions .btn {
            padding: 6px 16px;
            border-radius: 6px;
            font-size: 14px;
            min-width: 90px;  /* Reduced min-width */
            text-align: center;
            font-weight: 500;
            border: none;
            transition: all 0.2s ease;
            line-height: 1.2;  /* Adjust line height */
        }

        /* Restore button */
        .btn-group-actions .btn-success {
            background-color: #2c974b;  /* Adjusted to match image */
            color: white;
        }

        .btn-group-actions .btn-success:hover {
            background-color: #246c3a;
        }

        /* Delete button */
        .btn-group-actions .btn-danger {
            background-color: #cf4c4c;  /* Adjusted to match image */
            color: white;
        }

        .btn-group-actions .btn-danger:hover {
            background-color: #b54141;
        }

        /* Prevent text wrapping */
        .btn-group-actions .btn span {
            white-space: nowrap;
            display: inline-block;
        }

        /* Button hover effects */
        .btn-success:hover {
            background-color: #198754;
            transform: translateY(-1px);
            transition: all 0.2s ease;
        }

        .btn-danger:hover {
            background-color: #dc3545;
            transform: translateY(-1px);
            transition: all 0.2s ease;
        }
    </style>
</head>
<body>
<div class="sidebar">
    <?php include '../../general/sidebar.php'; ?>
</div>

<div class="main-content">
    <div class="content-container">
        <h1 class="page-title">Archived Users Audit Log</h1>

        <div id="alertMessage"></div>

        <!-- Bulk action buttons -->
        <div class="bulk-actions">
            <button type="button" id="restore-selected" class="btn btn-success" style="display: none;" disabled>Restore Selected</button>
            <button type="button" id="delete-selected-permanently" class="btn btn-danger" style="display: none;" disabled>Delete Selected Permanently</button>
        </div>

        <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>Track ID</th>
                <th>User</th>
                <th>Module</th>
                <th>Action</th>
                <th>Details</th>
                <th>Changes</th>
                <th>Status</th>
                <th>Date & Time</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($logs as $log): ?>
                <tr>
                    <td>
                        <input type="checkbox" class="select-row" value="<?php echo $log['deleted_user_id']; ?>">
                    </td>
                    <td><?php echo htmlspecialchars($log['track_id']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($log['operator_name']); ?><br>
                        <small><?php echo htmlspecialchars($log['operator_email']); ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($log['module']); ?></td>
                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                    <td><?php echo htmlspecialchars($log['details']); ?></td>
                    <td><?php echo formatNewValue($log['new_val']); ?></td>
                    <td><?php echo htmlspecialchars($log['status']); ?></td>
                    <td><?php echo htmlspecialchars($log['date_time']); ?></td>
                    <td>
                        <!-- Group the action buttons -->
                        <div class="btn-group-actions">
                            <button type="button" class="btn btn-success restore-btn" data-id="<?php echo $log['deleted_user_id']; ?>">
                                <span>Restore</span>
                            </button>
                            <button type="button" class="btn btn-danger delete-permanent-btn" data-id="<?php echo $log['deleted_user_id']; ?>">
                                <span>Permanent Delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div><!-- /.table-responsive -->
</div><!-- /.main-content -->

<!-- JavaScript for handling bulk and individual actions -->
<script>
    $(document).ready(function(){
        // Function to update bulk action buttons based on selection count
        function updateBulkButtons(){
            var count = $(".select-row:checked").length;
            if(count >= 2){
                $("#restore-selected, #delete-selected-permanently").prop("disabled", false).show();
            } else {
                $("#restore-selected, #delete-selected-permanently").prop("disabled", true).hide();
            }
        }

        // "Select All" functionality
        $("#select-all").change(function(){
            $(".select-row").prop("checked", $(this).prop("checked"));
            updateBulkButtons();
        });
        $(".select-row").change(function(){
            updateBulkButtons();
        });

        // Individual Restore action
        $(".restore-btn").click(function(){
            var userId = $(this).data("id");
            $.ajax({
                type: "POST",
                url: "../../modules/user_manager/restore_user.php",
                data: { id: userId },
                success: function(response){
                    $("#alertMessage").html('<div class="alert alert-success alert-dismissible fade show" role="alert">'+response+
                        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
                    location.reload();
                },
                error: function(xhr, status, error){
                    $("#alertMessage").html('<div class="alert alert-danger alert-dismissible fade show" role="alert">Error restoring user: ' + xhr.responseText +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
                }
            });
        });

        // Individual Permanent Delete action
        $(".delete-permanent-btn").click(function(){
            var userId = $(this).data("id");
            if(confirm("Are you sure you want to permanently delete this user?")){
                $.ajax({
                    type: "POST",
                    url: "../../modules/user_manager/delete_user.php",
                    data: { user_id: userId, permanent: "1" },
                    success: function(response){
                        $("#alertMessage").html('<div class="alert alert-success alert-dismissible fade show" role="alert">'+response+
                            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
                        location.reload();
                    },
                    error: function(xhr, status, error){
                        $("#alertMessage").html('<div class="alert alert-danger alert-dismissible fade show" role="alert">Error permanently deleting user: ' + xhr.responseText +
                            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
                    }
                });
            }
        });

        // Bulk Restore action
        $("#restore-selected").click(function(){
            var ids = [];
            $(".select-row:checked").each(function(){
                ids.push($(this).val());
            });
            $.ajax({
                type: "POST",
                url: "../../modules/user_manager/restore_user.php",
                data: { user_ids: ids },
                success: function(response){
                    $("#alertMessage").html('<div class="alert alert-success alert-dismissible fade show" role="alert">'+response+
                        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
                    location.reload();
                },
                error: function(xhr, status, error){
                    $("#alertMessage").html('<div class="alert alert-danger alert-dismissible fade show" role="alert">Error restoring selected users: ' + xhr.responseText +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
                }
            });
        });

        // Bulk Permanent Delete action
        $("#delete-selected-permanently").click(function(){
            var ids = [];
            $(".select-row:checked").each(function(){
                ids.push($(this).val());
            });
            if(confirm("Are you sure you want to permanently delete the selected users?")){
                $.ajax({
                    type: "POST",
                    url: "../../modules/user_manager/delete_user.php",
                    data: { user_ids: ids, permanent: "1" },
                    success: function(response){
                        $("#alertMessage").html('<div class="alert alert-success alert-dismissible fade show" role="alert">'+response+
                            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
                        location.reload();
                    },
                    error: function(xhr, status, error){
                        $("#alertMessage").html('<div class="alert alert-danger alert-dismissible fade show" role="alert">Error permanently deleting selected users: ' + xhr.responseText +
                            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
                    }
                });
            }
        });
    });
</script>
</body>
</html>
