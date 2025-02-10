<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

$query = "SELECT * FROM audit_log WHERE Module = 'User Management' ORDER BY Date_Time DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper functions remain the same
function formatJsonData($jsonStr)
{ /* ... */
}
/**
 * Helper function to compare old and new JSON data and return only changed fields.
 * It produces a descriptive change message for each field.
 *
 * Special handling:
 * - The 'password' field shows a generic "changed" message.
 * - The 'is_deleted' field outputs descriptive messages:
 *       • When changing from 0 to 1: "The user has been moved to soft delete."
 *       • When changing from 1 to 0: "The user has been restored from soft delete."
 * - Other fields are described as:
 *       "The [Field Name] was changed from '<em>old_value</em>' to '<strong>new_value</strong>'."
 *
 * Additionally, when multiple changes are detected, a horizontal line (<hr>) is added
 * between each change description for better visual separation.
 *
 * @param string $oldJson The JSON string for the old data.
 * @param string $newJson The JSON string for the new data.
 * @return string HTML displaying the change descriptions.
 */
function formatAuditDiff($oldJson, $newJson)
{
    $oldData = json_decode($oldJson, true);
    $newData = json_decode($newJson, true);

    if (!is_array($oldData) || !is_array($newData)) {
        return '<span>' . htmlspecialchars($newJson) . '</span>';
    }

    // Combine keys from both old and new data.
    $keys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));
    $descriptions = [];

    foreach ($keys as $key) {
        $lcKey = strtolower($key);

        // Special handling for the password field.
        if ($lcKey === 'password') {
            if (isset($oldData[$key], $newData[$key]) && $oldData[$key] !== $newData[$key]) {
                $descriptions[] = "The password has been changed.";
            }
            continue;
        }

        // Special handling for is_deleted.
        if ($lcKey === 'is_deleted') {
            $oldVal = isset($oldData[$key]) ? $oldData[$key] : '';
            $newVal = isset($newData[$key]) ? $newData[$key] : '';

            // Cast values to integers for proper numeric comparison.
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

        // Generic handling for other fields.
        $oldVal = isset($oldData[$key]) ? $oldData[$key] : '';
        $newVal = isset($newData[$key]) ? $newData[$key] : '';
        if ($oldVal !== $newVal) {
            // Convert field name from snake_case to a human-friendly format.
            $friendlyField = ucwords(str_replace('_', ' ', $key));
            $descriptions[] = "The {$friendlyField} was changed from '<em>{$oldVal}</em>' to '<strong>{$newVal}</strong>'.";
        }
    }

    if (empty($descriptions)) {
        return '<em>No changes detected.</em>';
    }

    // Build the HTML with a horizontal line between each change.
    $html = '<ul class="list-unstyled mb-0">';
    $count = count($descriptions);
    $index = 0;
    foreach ($descriptions as $desc) {
        $html .= "<li>{$desc}";
        $index++;
        // Insert a horizontal line if this is not the last change.
        if ($index < $count) {
            $html .= "<hr class='my-1'>";
        }
        $html .= "</li>";
    }
    $html .= "</ul>";

    return $html;
}

function getActionIcon($action)
{ /* ... */
}
function getStatusIcon($status)
{ /* ... */
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 280px;
            --header-height: 60px;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }

        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .card-header {
            background: #212529;
            border-bottom: none;
            padding: 1rem 1.5rem;
        }

        .card-header h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }

        .search-box {
            position: relative;
            max-width: 400px;
        }

        .search-box .form-control {
            padding-left: 2.5rem;
            border-radius: 20px;
            border: 1px solid #dee2e6;
        }

        .search-box .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            font-weight: 600;
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            vertical-align: middle;
            font-size: 0.875rem;
        }

        .data-container {
            background-color: #fff;
            border-radius: 0.375rem;
            border: 1px solid rgba(0, 0, 0, .125);
            padding: 0.75rem;
            font-size: 0.875rem;
        }

        .badge {
            font-weight: 500;
            padding: 0.5em 0.75em;
        }

        .action-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
        }

        .action-add {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .action-modified {
            background-color: #cfe2ff;
            color: #084298;
        }

        .action-delete {
            background-color: #f8d7da;
            color: #842029;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
    </style>
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

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Track ID</th>
                                    <th>Actor</th>
                                    <th>Updated User</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>Changes</th>
                                    <th>Status</th>
                                    <th>Date & Time</th>
                                </tr>
                            </thead>
                            <tbody id="auditTable">
                                <?php if (count($auditLogs) > 0): ?>
                                    <?php foreach ($auditLogs as $log): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo htmlspecialchars($log['TrackID']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-user-circle me-2"></i>
                                                    <?php echo htmlspecialchars($log['User']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                if ($log['Action'] === 'Modified') {
                                                    $updatedData = json_decode($log['NewVal'], true);
                                                    if (is_array($updatedData) && isset($updatedData['First_Name'])) {
                                                        echo '<div class="d-flex align-items-center">';
                                                        echo '<i class="fas fa-user-edit me-2"></i>';
                                                        echo htmlspecialchars($updatedData['First_Name'] . ' ' . $updatedData['Last_Name']);
                                                        echo '</div>';
                                                    } else {
                                                        echo '<em class="text-muted">N/A</em>';
                                                    }
                                                } else {
                                                    echo '<em class="text-muted">N/A</em>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                $actionClass = strtolower($log['Action']);
                                                echo "<span class='action-badge action-{$actionClass}'>";
                                                echo getActionIcon($log['Action']) . ' ' . htmlspecialchars($log['Action']);
                                                echo "</span>";
                                                ?>
                                            </td>
                                            <td class="data-container">
                                                <?php echo nl2br(htmlspecialchars($log['Details'])); ?>
                                            </td>
                                            <td class="data-container">
                                                <?php
                                                if ($log['Action'] === 'Modified') {
                                                    echo formatAuditDiff($log['OldVal'], $log['NewVal']);
                                                } else {
                                                    echo formatJsonData($log['Action'] === 'Add' ? $log['NewVal'] : $log['OldVal']);
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo strtolower($log['Status']) === 'success' ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo getStatusIcon($log['Status']) . ' ' . htmlspecialchars($log['Status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="far fa-clock me-2"></i>
                                                    <?php echo htmlspecialchars($log['Date_Time']); ?>
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
                                                <p class="text-muted">There are no audit log entries to display at this time.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#auditTable tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const match = text.includes(filter);

                // Add fade effect for smoother transitions
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