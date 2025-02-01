<?php
session_start();
require_once '../config/ims-tmdd.php';

// Suppose we check can_manage_invoices to view or manage invoices
if (!isset($_SESSION['user']) || $_SESSION['user']['privileges']['can_manage_invoices'] != 1) {
    die("Access Denied: You do not have permission to manage invoices.");
}


$id = $_GET['id'] ?? null;
if (!$id) {
    die("Missing purchase order ID.");
}

// Fetch the order
$stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = :id");
$stmt->execute(['id' => $id]);
$po = $stmt->fetch();
if (!$po) {
    die("Purchase Order not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poNumber  = $_POST['purchase_order_no'];
    $units     = $_POST['units'];
    $orderDate = $_POST['order_date'];

    $updateStmt = $pdo->prepare("
        UPDATE purchase_orders
        SET purchase_order_no = :po_no,
            units = :units,
            order_date = :order_date
        WHERE id = :id
    ");
    $updateStmt->execute([
        'po_no' => $poNumber,
        'units' => $units,
        'order_date' => $orderDate,
        'id' => $id
    ]);
    header('Location: purchase_order_index.php');
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Edit Purchase Order</title>
</head>

<body>
    <h1>Edit Purchase Order</h1>
    <p><a href="purchase_order_index.php">Back to PO List</a></p>
    <form method="POST">
        <label>PO Number:
            <input type="text" name="purchase_order_no" value="<?php echo htmlspecialchars($po['purchase_order_no']); ?>" required>
        </label><br><br>
        <label>Units:
            <input type="number" name="units" value="<?php echo htmlspecialchars($po['units']); ?>">
        </label><br><br>
        <label>Order Date:
            <input type="date" name="order_date" value="<?php echo htmlspecialchars($po['order_date']); ?>">
        </label><br><br>
        <button type="submit">Update</button>
    </form>
</body>

</html>