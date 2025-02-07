<?php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // adjust the path as needed

// (Optional) Check for proper privileges before showing the audit logs.
// if (!isset($_SESSION['user_id']) || !userHasAuditPrivilege($_SESSION['user_id'])) {
//     header("Location: login.php");
//     exit();
// }

$stmt = $pdo->prepare($query);
$stmt->execute();
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 */
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
    }
    $html .= '</ul>';
    return $html;
}

/**
 * Helper function to compare old and new JSON data and return only changed fields.
 *
 * @param string $oldJson The JSON string for the old data.
 * @param string $newJson The JSON string for the new data.
{
    $oldData = json_decode($oldJson, true);
    $newData = json_decode($newJson, true);
    if (!is_array($oldData) || !is_array($newData)) {
        return '<span>' . htmlspecialchars($newJson) . '</span>';
    }
    $diffs = [];
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
            <table class="table table-bordered table-striped table-responsive-sm">
                <thead class="thead-dark">
                    <tr>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($auditLogs) > 0): ?>
                        <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td class="data-container">
                                    <?php
                                    } else {
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
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