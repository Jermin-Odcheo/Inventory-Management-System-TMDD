<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require '../../../../config/ims-tmdd.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $department = $_POST['department'];
    $status = $_POST['status'];

    $stmt = $pdo->prepare("INSERT INTO users (Email, Password, First_Name, Last_Name, Department, Status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$email, $password, $first_name, $last_name, $department, $status]);
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Add User</title>
    <link rel="stylesheet" type="text/css" href="../../styles\css/add_user.css">
</head>

<body>
    <div class="form-container">
        <h2>Add New User</h2>
        <form method="post">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="text" name="first_name" placeholder="First Name" required>
            <input type="text" name="last_name" placeholder="Last Name" required>
            <input type="text" name="department" placeholder="Department">
            <button type="submit">Add User</button>
        </form>
    </div>
</body>

</html>