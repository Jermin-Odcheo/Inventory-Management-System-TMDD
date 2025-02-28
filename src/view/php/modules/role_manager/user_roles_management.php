<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Roles Management</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: #f5f7fb;
            padding: 2rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 70px 15px;
            margin-left: 274px;
        }
        
        .head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .search-container {
            display: flex;
            gap: 1rem;
        }
        
        .search-bar {
            padding: 0.5rem;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        
        .sort-dropdown {
            padding: 0.5rem;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        
        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 5rem;
            margin-left: 50px;
        }
        
        .role-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid;
            width: 380px;
            height: 200px;
        }
        
        .role-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .role-title {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .user-count {
            background-color: #f0f2f5;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .user-list {
            list-style: none;
            margin-bottom: 1.5rem;
        }
        
        .user-item {
            display: flex;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .user-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            margin-right: 0.8rem;
            background-color: #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: #fff;
        }
    </style>
</head>
<body>
    <?php
    session_start();
    require_once('../../../../../config/ims-tmdd.php');
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../../../../../public/index.php");
        exit();
    }
    include '../../general/header.php';
    include '../../general/sidebar.php';
    try {
        $stmtRoles = $pdo->prepare("SELECT * FROM roles ORDER BY Role_Name ASC");
        $stmtRoles->execute();
        $roles = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);

        $stmtUsers = $pdo->prepare("SELECT u.User_ID, u.First_Name, u.Last_Name, r.Role_Name FROM users u JOIN user_roles ur ON u.User_ID = ur.User_ID JOIN roles r ON ur.Role_ID = r.Role_ID WHERE u.is_deleted = 0 ORDER BY r.Role_Name, u.First_Name, u.Last_Name");
        $stmtUsers->execute();
        $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

        $roleUsers = [];
        foreach ($users as $user) {
            $roleUsers[$user['Role_Name']][] = $user;
        }
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
    ?>

    <div class="container">
        <div class="head">
            <h1>User Roles Management</h1>
            <div class="search-container">
                <input type="text" class="search-bar" placeholder="Search roles or users...">
                <select class="sort-dropdown">
                    <option>Sort by Role Name</option>
                    <option>Sort by User Count</option>
                </select>
            </div>
        </div>
        <div class="roles-grid">
            <?php foreach ($roles as $role): ?>
                <div class="role-card">
                    <div class="role-header">
                        <div class="role-title">
                            <i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars($role['Role_Name']); ?>
                        </div>
                        <span class="user-count">
                            <?php echo isset($roleUsers[$role['Role_Name']]) ? count($roleUsers[$role['Role_Name']]) : 0; ?> users
                        </span>
                    </div>
                    <ul class="user-list">
                        <?php if (isset($roleUsers[$role['Role_Name']])): ?>
                            <?php foreach ($roleUsers[$role['Role_Name']] as $user): ?>
                                <li class="user-item">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($user['First_Name'], 0, 1) . substr($user['Last_Name'], 0, 1)); ?>
                                    </div>
                                    <?php echo htmlspecialchars($user['First_Name'] . ' ' . $user['Last_Name']); ?>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="user-item">No users assigned.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
        document.querySelector('.search-bar').addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            document.querySelectorAll('.role-card').forEach(card => {
                const roleName = card.querySelector('.role-title').textContent.toLowerCase();
                const users = card.querySelectorAll('.user-item');
                let hasMatch = roleName.includes(searchTerm);
                users.forEach(user => {
                    if (user.textContent.toLowerCase().includes(searchTerm)) hasMatch = true;
                });
                card.style.display = hasMatch ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>
