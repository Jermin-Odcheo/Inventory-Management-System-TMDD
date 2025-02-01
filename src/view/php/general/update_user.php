<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require '../../../../config/ims-tmdd.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_GET['id'];
    $data = json_decode(file_get_contents('php://input'), true);

    $email = $data['email'];
    $first_name = $data['first_name'];
    $last_name = $data['last_name'];
    $department = $data['department'];
    $status = $data['status'];

    try {
        $stmt = $pdo->prepare("UPDATE users SET Email = ?, First_Name = ?, Last_Name = ?, Department = ?, Status = ? WHERE User_ID = ?");
        $stmt->execute([$email, $first_name, $last_name, $department, $status, $userId]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
