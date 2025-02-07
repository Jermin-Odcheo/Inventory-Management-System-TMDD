<?php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // adjust the path as needed

// (Optional) Check for proper privileges before showing the audit logs.
// if (!isset($_SESSION['user_id']) || !userHasAuditPrivilege($_SESSION['user_id'])) {
//     header("Location: login.php");
//     exit();
// }

// Query audit logs from the audit_log table.
$query = "SELECT * FROM audit_log WHERE Module = 'User Management' ORDER BY Date_Time DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Helper function to pretty-print JSON data.
 * This function is used for non-update actions.
 */
function formatJsonData($jsonStr)
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
        $html .= '<li><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

/**
 * Helper function to compare old and new JSON data and return only changed fields.
 * It displays each changed field as:
 *    FieldName: Old value → New value
 *
 * @param string $oldJson The JSON string for the old data.
 * @param string $newJson The JSON string for the new data.
 * @return string HTML displaying only the fields that changed.
 */function formatAuditDiff($oldJson, $newJson)
{
    $oldData = json_decode($oldJson, true);
    $newData = json_decode($newJson, true);
    
    if (!is_array($oldData) || !is_array($newData)) {
        return '<span>' . htmlspecialchars($newJson) . '</span>';
    }
    
    // Combine all keys from both old and new data.
    $keys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));
    $diffs = [];
    
    foreach ($keys as $key) {
        // Special handling for password field.
        if (strtolower($key) === 'password') {
            if (isset($oldData[$key], $newData[$key]) && $oldData[$key] !== $newData[$key]) {
                $diffs[] = '<li><strong>Password</strong>: Changed</li>';
            }
            // Skip further processing of the password field.
            continue;
        }
        
        $oldVal = isset($oldData[$key]) ? $oldData[$key] : '';
        $newVal = isset($newData[$key]) ? $newData[$key] : '';
        
        if ($oldVal !== $newVal) {
            $diffs[] = '<li><strong>' . htmlspecialchars(ucfirst($key)) . '</strong>: ' 
                     . htmlspecialchars($oldVal) . ' &rarr; ' . htmlspecialchars($newVal) . '</li>';
        }
    }
    
    if (empty($diffs)) {
        return '<em>No changes detected.</em>';
    }
    
    return '<ul class="list-unstyled mb-0">' . implode('', $diffs) . '</ul>';
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Audit Logs</title>
    <!-- Bootstrap CSS (you can upgrade to v5 if desired) -->
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
        }
    </style>
</head>

<body>
    <?php include '../../general/sidebar.php'; ?>
    <div class="main-content">
        <div class="container mt-4">
            <h1 class="mb-4">Audit Logs</h1>
            <table class="table table-bordered table-striped table-responsive-sm">
                <thead class="thead-dark">
                    <tr>
                        <th>TrackID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>Changes</th>
                        <th>Module</th>
                        <th>Status</th>
                        <th>Date &amp; Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($auditLogs) > 0): ?>
                        <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['TrackID']); ?></td>
                                <td><?php echo htmlspecialchars($log['User']); ?></td>
                                <td><?php echo htmlspecialchars($log['Action']); ?></td>
                                <td class="data-container"><?php echo nl2br(htmlspecialchars($log['Details'])); ?></td>
                                
                                <td class="data-container">
                                    <?php
                                    if ($log['Action'] === 'Modified') {
                                        echo formatAuditDiff($log['OldVal'], $log['NewVal']);
                                    } else {
                                        // For Add or Delete actions, show the full data as needed.
                                        if ($log['Action'] === 'Add') {
                                            echo formatJsonData($log['NewVal']);
                                        } elseif ($log['Action'] === 'Delete') {
                                            echo formatJsonData($log['OldVal']);
                                        } else {
                                            echo formatJsonData($log['NewVal']);
                                        }
                                    }
                                    ?>
                                </td>


                                </td>
                                <td><?php echo htmlspecialchars($log['Module']); ?></td>
                                <td><?php echo htmlspecialchars($log['Status']); ?></td>
                                <td><?php echo htmlspecialchars($log['Date_Time']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No audit log entries found.</td>
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