<?php
session_start();

// Redirect to login if the user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require '../../../../config/ims-tmdd.php';

// Fetch all users using a prepared statement for security
try {
    $stmt = $pdo->prepare("SELECT * FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle query error
    die("Error fetching users: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Management Dashboard</title>
    <link rel="stylesheet" href="../../styles/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="dashboard-container">
            <h2>User Management</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users): ?>
                            <?php foreach ($users as $user): ?>
                                <tr data-user-id="<?= htmlspecialchars($user['User_ID']) ?>">
                                    <td><?= htmlspecialchars($user['User_ID']) ?></td>
                                    <td>
                                        <input type="email" class="editable" name="email" value="<?= htmlspecialchars($user['Email']) ?>" disabled>
                                    </td>
                                    <td>
                                        <input type="text" class="editable" name="first_name" value="<?= htmlspecialchars($user['First_Name']) ?>" disabled>
                                    </td>
                                    <td>
                                        <input type="text" class="editable" name="last_name" value="<?= htmlspecialchars($user['Last_Name']) ?>" disabled>
                                    </td>
                                    <td>
                                        <input type="text" class="editable" name="department" value="<?= htmlspecialchars($user['Department']) ?>" disabled>
                                    </td>
                                    <td>
                                        <select class="editable" name="status" disabled>
                                            <option value="Online" <?= $user['Status'] === 'Online' ? 'selected' : '' ?>>Online</option>
                                            <option value="Offline" <?= $user['Status'] === 'Offline' ? 'selected' : '' ?>>Offline</option>
                                        </select>
                                    </td>
                                    <td>
                                        <button class="edit-btn" onclick="toggleEdit(this)"><i class="fas fa-edit"></i> Edit</button>
                                        <button class="save-btn" onclick="saveUser(this)" style="display: none;"><i class="fas fa-save"></i> Save</button>
                                        <button class="delete-btn" onclick="deleteUser(<?= htmlspecialchars($user['User_ID']) ?>)"><i class="fas fa-trash-alt"></i> Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">No users found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <a href="add_user.php" class="btn add-user"><i class="fas fa-user-plus"></i> Add New User</a>
        </div>
    </div>

    <script>
        function toggleEdit(button) {
            const row = button.closest('tr');
            const editables = row.querySelectorAll('.editable');
            const saveButton = row.querySelector('.save-btn');
            const editButton = row.querySelector('.edit-btn');

            editables.forEach(input => {
                input.disabled = !input.disabled;
            });

            if (saveButton.style.display === 'none') {
                saveButton.style.display = 'inline-block';
                editButton.style.display = 'none';
            } else {
                saveButton.style.display = 'none';
                editButton.style.display = 'inline-block';
            }
        }

        function saveUser(button) {
            const row = button.closest('tr');
            const userId = row.getAttribute('data-user-id');
            const inputs = row.querySelectorAll('.editable');
            const data = {};

            inputs.forEach(input => {
                data[input.name] = input.value;
            });

            fetch(`update_user.php?id=${userId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data),
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('User updated successfully!');
                        toggleEdit(button); // Switch back to edit mode
                    } else {
                        alert('Failed to update user.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user?')) {
                fetch(`delete_user.php?id=${userId}`, {
                        method: 'DELETE',
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            alert('User deleted successfully!');
                            window.location.reload();
                        } else {
                            alert('Failed to delete user.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }
        }
    </script>
</body>

</html>