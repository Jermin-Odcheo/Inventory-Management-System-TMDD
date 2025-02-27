<?php
session_start();
require '../../../../../config/ims-tmdd.php';

// Include Header
include '../../general/header.php';

//If not logged in redirect to the LOGIN PAGE
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "public/index.php"); // Redirect to login page
    exit();
}

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
 * Format JSON data into a list (for the 'Changes' column)
 * to match the audit logs dashboard design.
 */
function formatNewValue($jsonStr)
{
    $data = json_decode($jsonStr, true);
    if (!is_array($data)) {
        return '<span>' . htmlspecialchars($jsonStr) . '</span>';
    }
    $html = '<ul class="list-group">';
    foreach ($data as $key => $value) {
        $displayValue = is_null($value) ? '<em>null</em>' : htmlspecialchars($value);
        $friendlyKey = ucwords(str_replace('_', ' ', $key));
        $html .= '<li class="list-group-item d-flex justify-content-between align-items-center">
                    <strong>' . $friendlyKey . ':</strong> <span>' . $displayValue . '</span>
                  </li>';
    }
    $html .= '</ul>';
    return $html;
}

/**
 * Helper function to return an icon based on action.
 */
function getActionIcon($action)
{
    $action = strtolower($action);
    if ($action === 'modified') {
        return '<i class="fas fa-user-edit"></i>';
    } elseif ($action === 'add') {
        return '<i class="fas fa-user-plus"></i>';
    } elseif ($action === 'soft delete' || $action === 'permanent delete') {
        return '<i class="fas fa-user-slash"></i>';
    } else {
        return '<i class="fas fa-info-circle"></i>';
    }
}

/**
 * Helper function to return a status icon.
 */
function getStatusIcon($status)
{
    return (strtolower($status) === 'successful')
        ? '<i class="fas fa-check-circle"></i>'
        : '<i class="fas fa-times-circle"></i>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Archived Users Audit Log</title>
    <!-- Bootstrap and Font Awesome CDNs -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS for audit logs -->
    <link rel="stylesheet" href="/Inventory-Managment-System-TMDD/src/view/styles/css/audit_log.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/pagination.css">
</head>
<body>
<?php include '../../general/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="card">
            <!-- Card header -->
            <div class="card-header d-flex justify-content-between align-items-center bg-dark">
                <h3 class="text-white">
                    <i class="fas fa-archive me-2"></i>
                    Archived Users Audit Log
                </h3>
            </div>

            <div class="card-body">
                <!-- Alert message placeholder -->
                <div id="alertMessage"></div>

                <!-- Bulk action buttons -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="bulk-actions mb-3">
                            <button type="button" id="restore-selected" class="btn btn-success" disabled
                                    style="display: none;">Restore Selected
                            </button>
                            <button type="button" id="delete-selected-permanently" class="btn btn-danger" disabled
                                    style="display: none;">Delete Selected Permanently
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table container -->
                <div class="table-responsive">
                    <table class="table table-hover" id="table">
                        <colgroup>
                            <col class="checkbox">
                            <col class="track">
                            <col class="user">
                            <col class="module">
                            <col class="action">
                            <col class="details">
                            <col class="changes">
                            <col class="status">
                            <col class="date">
                            <col class="actions">
                        </colgroup>
                        <thead class="table-light">
                        <tr>
                            <th><input type="checkbox" id="select-all"></th>
                            <th>#</th>
                            <th>User</th>
                            <th>Module</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Changes</th>
                            <th>Status</th>
                            <th>Date &amp; Time</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody id="archiveTable">
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <!-- Checkbox column -->
                                    <td data-label="Select">
                                        <input type="checkbox" class="select-row"
                                               value="<?php echo $log['deleted_user_id']; ?>">
                                    </td>
                                    <!-- Track ID -->
                                    <td data-label="Track ID">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($log['track_id']); ?></span>
                                    </td>
                                    <!-- User -->
                                    <td data-label="User">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user-circle me-2"></i>
                                            <small><?php echo htmlspecialchars($log['operator_email']); ?></small>
                                        </div>
                                    </td>
                                    <!-- Module -->
                                    <td data-label="Module">
                                        <?php echo !empty($log['module']) ? htmlspecialchars(trim($log['module'])) : '<em class="text-muted">N/A</em>'; ?>
                                    </td>
                                    <!-- Action -->
                                    <td data-label="Action">
                                        <?php
                                        $actionText = !empty($log['action']) ? $log['action'] : 'Unknown';
                                        echo '<span class="action-badge action-' . strtolower($actionText) . '">';
                                        echo getActionIcon($actionText) . ' ' . htmlspecialchars($actionText);
                                        echo '</span>';
                                        ?>
                                    </td>
                                    <!-- Details -->
                                    <td data-label="Details">
                                        <?php echo nl2br(htmlspecialchars($log['details'])); ?>
                                    </td>
                                    <!-- Changes -->
                                    <td data-label="Changes">
                                        <?php echo formatNewValue($log['new_val']); ?>
                                    </td>
                                    <!-- Status -->
                                    <td data-label="Status">
                                        <span class="badge <?php echo (strtolower($log['status']) === 'successful') ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo getStatusIcon($log['status']) . ' ' . htmlspecialchars($log['status']); ?>
                                        </span>
                                    </td>
                                    <!-- Date & Time -->
                                    <td data-label="Date &amp; Time">
                                        <div class="d-flex align-items-center">
                                            <i class="far fa-clock me-2"></i>
                                            <?php echo htmlspecialchars($log['date_time']); ?>
                                        </div>
                                    </td>
                                    <!-- Actions (Restore / Permanent Delete) -->
                                    <td data-label="Actions">
                                        <div class="btn-vertical-compact">
                                            <button type="button" class="btn btn-success restore-btn"
                                                    data-id="<?php echo $log['deleted_user_id']; ?>">
                                                <i class="fas fa-undo me-1"></i> Restore
                                            </button>
                                            <button type="button" class="btn btn-danger delete-permanent-btn"
                                                    data-id="<?php echo $log['deleted_user_id']; ?>">
                                                <i class="fas fa-trash me-1"></i> Delete
                                            </button>
                                        </div>

                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state text-center py-4">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <h4>No Archived Users Found</h4>
                                        <p class="text-muted">There are no archived users to display.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div><!-- /.table-responsive -->
                <!-- Pagination Controls -->
                <div class="container-fluid">
                    <div class="row align-items-center g-3">
                        <!-- Pagination Info -->
                        <div class="col-12 col-sm-auto">
                            <div class="text-muted">
                                Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span
                                        id="totalRows">100</span> entries
                            </div>
                        </div>

                        <!-- Pagination Controls -->
                        <div class="col-12 col-sm-auto ms-sm-auto">
                            <div class="d-flex align-items-center gap-2">
                                <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                    <i class="bi bi-chevron-left"></i>
                                    Previous
                                </button>

                                <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                    <option value="10" selected>10</option>
                                    <option value="20">20</option>
                                    <option value="30">30</option>
                                    <option value="50">50</option>
                                </select>

                                <button id="nextPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                    Next
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- New Pagination Page Numbers -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <ul class="pagination justify-content-center" id="pagination"></ul>
                        </div>
                    </div>
                </div> <!-- /.End of Pagination -->

            </div>
        </div>
    </div><!-- /.card-body -->
</div><!-- /.card -->
</div><!-- /.container-fluid -->
</div><!-- /.main-content -->

<!-- JavaScript for bulk actions and AJAX calls -->
<script>
    // Function to update the visibility and state of bulk action buttons
    function updateBulkButtons() {
        const checkboxes = document.querySelectorAll(".select-row:checked");
        const count = checkboxes.length;
        const restoreButton = document.getElementById("restore-selected");
        const deleteButton = document.getElementById("delete-selected-permanently");
        if (count >= 2) {
            restoreButton.disabled = false;
            deleteButton.disabled = false;
            restoreButton.style.display = "";
            deleteButton.style.display = "";
        } else {
            restoreButton.disabled = true;
            deleteButton.disabled = true;
            restoreButton.style.display = "none";
            deleteButton.style.display = "none";
        }
    }

    // Select-all checkbox functionality
    document.getElementById("select-all").addEventListener("change", function () {
        const checkboxes = document.querySelectorAll(".select-row");
        checkboxes.forEach(function (checkbox) {
            checkbox.checked = document.getElementById("select-all").checked;
        });
        updateBulkButtons();
    });

    document.querySelectorAll(".select-row").forEach(function (checkbox) {
        checkbox.addEventListener("change", updateBulkButtons);
    });

    // Restore individual user
    document.querySelectorAll(".restore-btn").forEach(function (button) {
        button.addEventListener("click", function () {
            const userId = this.getAttribute("data-id");
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "../../modules/user_manager/restore_user.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onload = function () {
                if (xhr.status === 200) {
                    document.getElementById("alertMessage").innerHTML = '<div class="alert alert-success alert-dismissible fade show" role="alert">' + xhr.responseText +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                    location.reload();
                } else {
                    document.getElementById("alertMessage").innerHTML = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Error restoring user: ' + xhr.responseText +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                }
            };
            xhr.send("id=" + encodeURIComponent(userId));
        });
    });

    // Permanently delete individual user
    document.querySelectorAll(".delete-permanent-btn").forEach(function (button) {
        button.addEventListener("click", function () {
            const userId = this.getAttribute("data-id");
            if (confirm("Are you sure you want to permanently delete this user?")) {
                const xhr = new XMLHttpRequest();
                xhr.open("POST", "../../modules/user_manager/delete_user.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onload = function () {
                    if (xhr.status === 200) {
                        document.getElementById("alertMessage").innerHTML = '<div class="alert alert-success alert-dismissible fade show" role="alert">' + xhr.responseText +
                            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                        location.reload();
                    } else {
                        document.getElementById("alertMessage").innerHTML = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Error permanently deleting user: ' + xhr.responseText +
                            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                    }
                };
                xhr.send("user_id=" + encodeURIComponent(userId) + "&permanent=1");
            }
        });
    });

    // Bulk restore selected users
    document.getElementById("restore-selected").addEventListener("click", function () {
        const selected = document.querySelectorAll(".select-row:checked");
        const ids = [];
        selected.forEach(function (checkbox) {
            ids.push(checkbox.value);
        });
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "../../modules/user_manager/restore_user.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onload = function () {
            if (xhr.status === 200) {
                document.getElementById("alertMessage").innerHTML = '<div class="alert alert-success alert-dismissible fade show" role="alert">' + xhr.responseText +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                location.reload();
            } else {
                document.getElementById("alertMessage").innerHTML = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Error restoring selected users: ' + xhr.responseText +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            }
        };
        xhr.send("user_ids=" + encodeURIComponent(JSON.stringify(ids)));
    });

    // Bulk permanently delete selected users
    document.getElementById("delete-selected-permanently").addEventListener("click", function () {
        const selected = document.querySelectorAll(".select-row:checked");
        const ids = [];
        selected.forEach(function (checkbox) {
            ids.push(checkbox.value);
        });
        if (confirm("Are you sure you want to permanently delete the selected users?")) {
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "../../modules/user_manager/delete_user.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onload = function () {
                if (xhr.status === 200) {
                    document.getElementById("alertMessage").innerHTML = '<div class="alert alert-success alert-dismissible fade show" role="alert">' + xhr.responseText +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                    location.reload();
                } else {
                    document.getElementById("alertMessage").innerHTML = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Error permanently deleting selected users: ' + xhr.responseText +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                }
            };
            xhr.send("user_ids=" + encodeURIComponent(JSON.stringify(ids)) + "&permanent=1");
        }
    });
</script>

<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
</body>
</html>
