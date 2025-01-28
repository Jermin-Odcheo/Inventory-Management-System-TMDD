<?php
session_start();
require_once '../config/ims-tmdd.php';

// Check permission
if (!isset($_SESSION['user']) || $_SESSION['user']['privileges']['can_manage_invoices'] != 1) {
    die("Access Denied: You do not have permission to delete invoices.");
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die("Missing invoice ID.");
}

// Delete the invoice
$stmt = $pdo->prepare("DELETE FROM charge_invoices WHERE id = :id");
$stmt->execute(['id' => $id]);

header('Location: invoices_index.php');
exit;
