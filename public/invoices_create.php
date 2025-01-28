<?php
session_start();
require_once '../config/ims-tmdd.php';

// Check if user is logged in & can manage invoices
if (!isset($_SESSION['user']) || $_SESSION['user']['privileges']['can_manage_invoices'] != 1) {
    die("Access Denied: You do not have permission to create invoices.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoiceNo       = $_POST['charge_invoice_no'] ?? '';
    $dateOfPurchase  = $_POST['date_of_purchase'] ?? null;

    // Insert the new invoice
    $stmt = $pdo->prepare("
        INSERT INTO charge_invoices (charge_invoice_no, date_of_purchase)
        VALUES (:inv_no, :dop)
    ");
    $stmt->execute([
        'inv_no' => $invoiceNo,
        'dop'    => $dateOfPurchase
    ]);

    // After creation, redirect back to index
    header('Location: invoices_index.php');
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Create Invoice</title>
</head>

<body>
    <h1>Create Invoice</h1>
    <p><a href="invoices_index.php">Back to Invoice List</a></p>

    <form method="POST">
        <label>Invoice Number:
            <input type="text" name="charge_invoice_no" required>
        </label><br><br>

        <label>Date of Purchase:
            <input type="date" name="date_of_purchase">
        </label><br><br>

        <button type="submit">Create</button>
    </form>
</body>

</html>