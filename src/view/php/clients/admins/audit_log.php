<?php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // adjust the path as needed

// (Optional) Check for proper privileges before showing the audit logs.
// if (!isset($_SESSION['user_id']) || !userHasAuditPrivilege($_SESSION['user_id'])) {
//     header("Location: login.php");
//     exit();
// }

// Query audit logs for the "users" table joined with the users table to get the email.
$query = "SELECT a.*, u.Email AS ChangedByEmail 
          FROM audit_log a 
          LEFT JOIN users u ON a.ChangedBy = u.User_ID 
          WHERE a.TableName = 'users' 
          ORDER BY a.ChangeTime DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Helper function to format audit data as a full HTML unordered list.
 * Used for INSERT and DELETE actions.
 *
 * @param string $jsonStr The JSON string from the audit_log.
 * @return string An HTML unordered list of key/value pairs, or a placeholder if empty.
 */
function formatAuditDataToHtml($jsonStr)
{
    if (empty($jsonStr)) {
        return '<em>No data</em>';
    }
    $decoded = json_decode($jsonStr, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return '<span>' . htmlspecialchars($jsonStr) . '</span>';
    }
    $html = '<ul class="list-unstyled mb-0">';
    foreach ($decoded as $key => $value) {
        $html .= '<li><strong>' . htmlspecialchars(ucfirst($key)) . ':</strong> ' . htmlspecialchars($value) . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

/**
 * Helper function to compare old and new JSON data and return only changed fields.
 * It displays for each field: "FieldName: old value → new value"
 *
 * @param string $oldJson The JSON string for the old data.
 * @param string $newJson The JSON string for the new data.
 * @return string An HTML unordered list of only the fields that changed.
 */
function formatAuditUpdateData($oldJson, $newJson)
{
    $oldData = json_decode($oldJson, true);
    $newData = json_decode($newJson, true);

    if (!is_array($oldData) || !is_array($newData)) {
        return '<span>' . htmlspecialchars($newJson) . '</span>';
    }

    $allKeys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));
    $diffs = [];
    foreach ($allKeys as $key) {
        $oldValue = isset($oldData[$key]) ? $oldData[$key] : '';
        $newValue = isset($newData[$key]) ? $newData[$key] : '';
        if ($oldValue !== $newValue) {
            $diffs[] = '<li><strong>' . htmlspecialchars(ucfirst($key)) . '</strong>: ' .
                '<em>' . htmlspecialchars($oldValue) . '</em> &rarr; ' .
                '<em>' . htmlspecialchars($newValue) . '</em></li>';
        }
    }
    if (empty($diffs)) {
        return '<em>No changes detected.</em>';
    }
    return '<ul class="list-unstyled mb-0">' . implode('', $diffs) . '</ul>';
}

/**
 * Helper function to decide which display action to use.
 * For UPDATE actions that change only the is_deleted field, return:
 *   "Account Deleted" if is_deleted changes from 0 to 1, or
 *   "Account Restored" if is_deleted changes from 1 to 0.
 *
 * @param array $log The audit log entry.
 * @return string The display action.
 */
function getDisplayAction($log)
{
    $action = $log['Action'];
    if ($action === 'UPDATE') {
        $oldData = json_decode($log['OldData'], true);
        $newData = json_decode($log['NewData'], true);
        if (is_array($oldData) && is_array($newData)) {
            $allKeys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));
            $changedKeys = [];
            foreach ($allKeys as $key) {
                $oldValue = isset($oldData[$key]) ? $oldData[$key] : '';
                $newValue = isset($newData[$key]) ? $newData[$key] : '';
                if ($oldValue !== $newValue) {
                    $changedKeys[] = $key;
                }
            }
            if (count($changedKeys) === 1 && in_array('is_deleted', $changedKeys)) {
                if (isset($newData['is_deleted']) && $newData['is_deleted'] == 1) {
                    $action = 'Account Deleted';
                } elseif (isset($newData['is_deleted']) && $newData['is_deleted'] == 0) {
                    $action = 'Account Restored';
                }
            }
        }
    }
    return $action;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Audit Logs</title>
    <!-- Bootstrap CSS (using Bootstrap 4; upgrade to v5 if desired) -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .data-container {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
        }

        ul.list-unstyled li {
            line-height: 1.5;
        }

        .main-content {
            margin-left: 300px;
            /* Adjust according to your sidebar width */
            padding: 20px;

            border-radius: 8px;
            margin-bottom: 20px;
            width: auto;
        }
    </style>
</head>

<body>
    <?php include '../../general/sidebar.php'; ?>
    <div class="main-content">
        <div class="container mt-4">
            <h1 class="mb-4">User Audit Logs</h1>
            <table class="table table-bordered table-striped table-responsive-sm">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Action</th>
                        <th>Changed By (Email)</th>
                        <th>Change Details</th>
                        <th>IP Address</th>
                        <th>Change Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($auditLogs) > 0): ?>
                        <?php foreach ($auditLogs as $log): ?>
                            <?php $displayAction = getDisplayAction($log); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['AuditLogID']); ?></td>
                                <td><?php echo htmlspecialchars($displayAction); ?></td>
                                <td><?php echo htmlspecialchars($log['ChangedByEmail'] ?? 'N/A'); ?></td>
                                <td class="data-container">
                                    <?php
                                    if ($log['Action'] === 'UPDATE') {
                                        echo formatAuditUpdateData($log['OldData'], $log['NewData']);
                                    } elseif ($log['Action'] === 'INSERT') {
                                        echo formatAuditDataToHtml($log['NewData']);
                                    } elseif ($log['Action'] === 'DELETE') {
                                        echo formatAuditDataToHtml($log['OldData']);
                                    } else {
                                        echo '<span>' . htmlspecialchars($log['NewData']) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($log['IPAddress']); ?></td>
                                <td><?php echo htmlspecialchars($log['ChangeTime']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No audit log entries found for the users table.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>