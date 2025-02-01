<?php
session_start();
require_once '../config/ims-tmdd.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['privileges']['can_delete_assets'] != 1) {
    die("Access Denied. You do not have permission to delete assets.");
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die("Missing asset ID.");
}

$stmt = $pdo->prepare("DELETE FROM assets WHERE id = :id");
$stmt->execute(['id' => $id]);

header('Location: index.php');
exit;
