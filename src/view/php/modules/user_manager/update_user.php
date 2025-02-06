<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = $_POST['ID'];
    $email = $_POST['Email'];
    $firstName = $_POST['First_Name'];
    $lastName = $_POST['Last_Name'];
    $department = $_POST['Department'];
    $status = $_POST['Status'];
    $password = $_POST['Password'];

    try {
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $query = "UPDATE users SET Email=?, First_Name=?, Last_Name=?, Department=?, Status=?, Password=? WHERE User_ID=?";
            $params = [$email, $firstName, $lastName, $department, $status, $hashedPassword, $id];
        } else {
            $query = "UPDATE users SET Email=?, First_Name=?, Last_Name=?, Department=?, Status=? WHERE User_ID=?";
            $params = [$email, $firstName, $lastName, $department, $status, $id];
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        echo "User updated successfully!";
    } catch (PDOException $e) {
        echo "Error updating user: " . $e->getMessage();
    }
}
?>
