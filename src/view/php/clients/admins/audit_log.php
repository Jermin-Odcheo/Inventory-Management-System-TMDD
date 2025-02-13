<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Fetch all audit logs (including permanent deletes)
$query = "
    SELECT a.*, u.Email AS user_email 
    FROM audit_log a
    LEFT JOIN users u ON a.User = u.User_ID 
    ORDER BY a.Date_Time DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute();
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Helper function to display JSON data with <br> for new lines.
 */
function formatJsonData($jsonStr)
{
    if (!$jsonStr) {
        return '<em class="text-muted">N/A</em>';
    }
    return nl2br(htmlspecialchars($jsonStr));
}

/**
 * Helper function to compare old/new JSON data for modifications.
 */
function formatAuditDiff($oldJson, $newJson)
{
    $oldData = json_decode($oldJson, true);
    $newData = json_decode($newJson, true);

    if (!is_array($oldData) || !is_array($newData)) {
        return '<span>' . htmlspecialchars($newJson) . '</span>';
    }

    $keys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));
    $descriptions = [];

    foreach ($keys as $key) {
        $lcKey = strtolower($key);

        // Handle password changes
        if ($lcKey === 'password') {
            if (isset($oldData[$key], $newData[$key]) && $oldData[$key] !== $newData[$key]) {
                $descriptions[] = "The password has been changed.";
            }
            continue;
        }

        // Special handling for is_deleted
        if ($lcKey === 'is_deleted') {
            $oldVal = isset($oldData[$key]) ? $oldData[$key] : '';
            $newVal = isset($newData[$key]) ? $newData[$key] : '';
            $oldInt = (int)$oldVal;
            $newInt = (int)$newVal;

            if ($oldInt !== $newInt) {
                if ($oldInt === 0 && $newInt === 1) {
                    $descriptions[] = "The user has been moved to soft delete.";
                } elseif ($oldInt === 1 && $newInt === 0) {
                    $descriptions[] = "The user has been restored from soft delete.";
                } else {
                    $descriptions[] = "The deletion status changed from '<em>{$oldVal}</em>' to '<strong>{$newVal}</strong>'.";
                }
            }
            continue;
        }

        // Generic handling for other fields
        $oldVal = isset($oldData[$key]) ? $oldData[$key] : '';
        $newVal = isset($newData[$key]) ? $newData[$key] : '';
        if ($oldVal !== $newVal) {
            $friendlyField = ucwords(str_replace('_', ' ', $key));
            $descriptions[] = "The {$friendlyField} was changed from '<em>{$oldVal}</em>' to '<strong>{$newVal}</strong>'.";
        }
    }

    if (empty($descriptions)) {
        return '<em>No changes detected.</em>';
    }

    $html = '<ul class="list-unstyled mb-0">';
    $count = count($descriptions);
    $index = 0;
    foreach ($descriptions as $desc) {
        $html .= "<li>{$desc}";
        $index++;
        if ($index < $count) {
            $html .= "<hr class='my-1'>";
        }
        $html .= "</li>";
    }
    $html .= "</ul>";

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
    } elseif ($action === 'deleted' || strpos($action, 'deleted') !== false || $action === 'user permanently deleted') {
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
    <title>Audit Logs Dashboard</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS to force a fixed table layout that wraps text -->
    <style>
        .table-responsive {
            overflow-x: hidden;
        }
        .table {
            table-layout: fixed;
            width: 100%;
        }
        .table th, .table td {
            white-space: normal !important;
            word-break: break-word;
            overflow-wrap: break-word;
            /* Optionally, set a max-width if needed */
            max-width: 200px; /* Adjust this value as needed */
        }
    </style>
    <link rel="stylesheet"
          href="/Inventory-Managment-System-TMDD/src/view/styles/css/audit_log.css">
</head>
<body>
<?php include '../../general/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="text-white">
                    <i class="fas fa-history me-2"></i>
                    Audit Logs Dashboard
                </h3>
            </div>

            <div class="card-body">
                <div class="mb-4">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="searchInput" class="form-control"
                               placeholder="Search in audit logs...">
                    </div>
                </div>

                <!-- Table container -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                        <tr>
                            <th>Track ID</th>
                            <th>User</th>
                            <th>Module</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Changes</th>
                            <th>Status</th>
                            <th>Date & Time</th>
                        </tr>
                        </thead>
                        <tbody id="auditTable">
                        <?php if (!empty($auditLogs)): ?>
                            <?php foreach ($auditLogs as $log): ?>
                                <?php
                                // Normalize action to lower case for comparisons.
                                $actionLower = strtolower($log['Action'] ?? '');
                                ?>
                                <tr>
                                    <!-- TRACK ID -->
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($log['TrackID']); ?>
                                        </span>
                                    </td>

                                    <!-- USER WHO PERFORMED -->
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user-circle me-2"></i>
                                            <?php echo htmlspecialchars($log['user_email'] ?? 'N/A'); ?>
                                        </div>
                                    </td>

                                    <!-- MODULE-->
                                    <td>
                                        <?php echo !empty($log['Module']) ? htmlspecialchars(trim($log['Module'])) : '<em class="text-muted">N/A</em>'; ?>
                                    </td>
                                    <!-- ACTION -->
                                    <td>
                                        <?php
                                        $actionText = !empty($log['Action']) ? $log['Action'] : 'Deleted';
                                        echo "<span class='action-badge action-" . strtolower($actionText) . "'>";
                                        echo getActionIcon($actionText) . ' ' . htmlspecialchars($actionText);
                                        echo "</span>";
                                        ?>
                                    </td>
                                    <!-- DETAILS -->
                                    <td class="data-container">
                                        <?php
                                        if ($actionLower === 'deleted' || $actionLower === 'user permanently deleted') {
                                            echo nl2br(htmlspecialchars('The user has been deleted from database'));
                                        } else {
                                            echo nl2br(htmlspecialchars($log['Details'] ?? ''));
                                        }
                                        ?>
                                    </td>

                                    <!-- CHANGES -->
                                    <td class="data-container">
                                        <?php
                                        if ($actionLower === 'modified') {
                                            echo formatAuditDiff($log['OldVal'], $log['NewVal']);
                                        } elseif ($actionLower === 'add') {
                                            echo formatJsonData($log['NewVal']);
                                        } elseif ($actionLower === 'deleted' || $actionLower === 'user permanently deleted') {
                                            // Display the old record's JSON data
                                            echo formatJsonData($log['OldVal']);
                                        } else {
                                            echo formatJsonData($log['OldVal']);
                                        }
                                        ?>
                                    </td>

                                    <!-- STATUS -->
                                    <td>
                                        <span class="badge <?php echo (strtolower($log['Status'] ?? '') === 'successful') ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo getStatusIcon($log['Status']) . ' ' . htmlspecialchars($log['Status']); ?>
                                        </span>
                                    </td>

                                    <!-- DATE & TIME -->
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="far fa-clock me-2"></i>
                                            <?php echo htmlspecialchars($log['Date_Time'] ?? ''); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <h4>No Audit Logs Found</h4>
                                        <p class="text-muted">There are no audit log entries to display.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div><!-- /.table-responsive -->
            </div><!-- /.card-body -->
        </div><!-- /.card -->
    </div><!-- /.container-fluid -->
</div><!-- /.main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Simple search functionality
    document.getElementById('searchInput').addEventListener('keyup', function () {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('#auditTable tr');

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const match = text.includes(filter);

            if (match) {
                row.style.display = '';
                row.style.opacity = '1';
            } else {
                row.style.opacity = '0';
                setTimeout(() => {
                    row.style.display = 'none';
                }, 200);
            }
        });
    });
</script>
</body>
</html>
