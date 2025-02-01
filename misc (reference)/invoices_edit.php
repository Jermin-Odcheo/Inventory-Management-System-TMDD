<?php
session_start();
require_once '../config/ims-tmdd.php';

// Check permission
if (!isset($_SESSION['user']) || $_SESSION['user']['privileges']['can_manage_invoices'] != 1) {
    die("Access Denied: You do not have permission to edit invoices.");
}

// Get the invoice ID from the URL
$id = $_GET['id'] ?? null;
if (!$id) {
    die("Missing invoice ID.");
}

// Fetch the invoice record
$stmt = $pdo->prepare("SELECT * FROM charge_invoices WHERE id = :id");
$stmt->execute(['id' => $id]);
$invoice = $stmt->fetch();
if (!$invoice) {
    die("Invoice not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoiceNo      = $_POST['charge_invoice_no'] ?? '';
    $dateOfPurchase = $_POST['date_of_purchase'] ?? null;

    // Update the invoice
    $updateStmt = $pdo->prepare("
        UPDATE charge_invoices
        SET charge_invoice_no = :inv_no,
            date_of_purchase = :dop
        WHERE id = :id
    ");
    $updateStmt->execute([
        'inv_no' => $invoiceNo,
        'dop'    => $dateOfPurchase,
        'id'     => $id
    ]);

    header('Location: invoices_index.php');
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Edit Invoice</title>
</head>

<body>
    <h1>Edit Invoice</h1>
    <p><a href="invoices_index.php">Back to Invoice List</a></p>

    <form method="POST">
        <label>Invoice Number:
            <input type="text" name="charge_invoice_no"
                value="<?php echo htmlspecialchars($invoice['charge_invoice_no']); ?>"
                required>
        </label><br><br>

        <label>Date of Purchase:
            <input type="date" name="date_of_purchase"
                value="<?php echo htmlspecialchars($invoice['date_of_purchase']); ?>">
        </label><br><br>

        <button type="submit">Update</button>
    </form>
</body>

</html>