<?php
session_start();
require_once '../config/ims-tmdd.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['privileges']['can_manage_invoices'] != 1) {
    die("Access Denied: You do not have permission to delete purchase orders.");
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die("Missing purchase order ID.");
}

$stmt = $pdo->prepare("DELETE FROM purchase_orders WHERE id = :id");
$stmt->execute(['id' => $id]);

header('Location: purchase_order_index.php');
exit;
