<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Set the audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    $pdo->exec("SET @current_user_id = NULL");
}

// Set IP address for logging.
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

// Function to log audit events
function logAudit($pdo, $action, $details, $status, $oldData, $entityId = null)
{
    $stmt = $pdo->prepare("
        INSERT INTO audit_log (UserID, EntityID, Module, Action, Details, Status, OldVal, NewVal, Date_Time)
        VALUES (?, ?, 'Receiving Report', ?, ?, ?, ?, NULL, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $entityId, $action, $details, $status, $oldData]);
}

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (isset($_POST['id']) && isset($_POST['permanent']) && $_POST['permanent'] == 1) {
    $rrId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($rrId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid receiving report ID']);
        exit();
    }
    try {
        // First check if the record exists and is already archived
        $checkStmt = $pdo->prepare("SELECT * FROM receive_report WHERE id = ? AND is_disabled = 1");
        $checkStmt->execute([$rrId]);
        $oldData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$oldData) {
            echo json_encode(['status' => 'error', 'message' => 'Receiving report not found or not archived']);
            exit();
        }
        
        // Permanently delete the receiving report
        $stmt = $pdo->prepare("DELETE FROM receive_report WHERE id = ? AND is_disabled = 1");
        $stmt->execute([$rrId]);
        
        // Log the permanent delete action
        logAudit(
            $pdo,
            'Remove',
            'Receiving Report "' . ($oldData['rr_no'] ?? 'ID: '.$rrId) . '" has been permanently removed',
            'Successful',
            json_encode($oldData),
            $rrId
        );
        
        echo json_encode(['status' => 'success', 'message' => 'Receiving report permanently removed']);
    } catch (PDOException $e) {
        // Log the error
        logAudit(
            $pdo,
            'Remove',
            'Receiving Report ID: ' . $rrId . ' permanent deletion failed',
            'Failed',
            json_encode(['id' => $rrId, 'error' => $e->getMessage()]),
            $rrId
        );
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else if (isset($_POST['ids']) && is_array($_POST['ids']) && isset($_POST['permanent']) && $_POST['permanent'] == 1) {
    $rrIds = array_filter(array_map('intval', $_POST['ids']));
    if(empty($rrIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid receiving report IDs provided']);
        exit();
    }
    try {
        $placeholders = implode(",", array_fill(0, count($rrIds), '?'));
        
        // First check if the records exist and are already archived
        $checkStmt = $pdo->prepare("SELECT * FROM receive_report WHERE id IN ($placeholders) AND is_disabled = 1");
        $checkStmt->execute($rrIds);
        $oldDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($oldDataRecords) != count($rrIds)) {
            echo json_encode(['status' => 'error', 'message' => 'Some receiving reports not found or not archived']);
            exit();
        }
        
        // Permanently delete the receiving reports
        $stmt = $pdo->prepare("DELETE FROM receive_report WHERE id IN ($placeholders) AND is_disabled = 1");
        $stmt->execute($rrIds);
        
        // Log each deletion separately for proper auditing
        foreach ($oldDataRecords as $oldData) {
            logAudit(
                $pdo,
                'Remove',
                'Receiving Report "' . ($oldData['rr_no'] ?? 'ID: '.$oldData['id']) . '" has been permanently removed',
                'Successful',
                json_encode($oldData),
                $oldData['id']
            );
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Selected receiving reports permanently removed']);
    } catch (PDOException $e) {
        // Log the error
        logAudit(
            $pdo,
            'Remove',
            'Bulk Receiving Report deletion failed for IDs: ' . implode(', ', $rrIds),
            'Failed',
            json_encode(['ids' => implode(',', $rrIds), 'error' => $e->getMessage()]),
            null
        );
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request or missing permanent flag']);
}
?>