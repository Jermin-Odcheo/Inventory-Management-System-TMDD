<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Roles Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fb;
            padding: 2rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
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
            padding: 0.8rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            width: 300px;
            font-size: 1rem;
        }

        .sort-dropdown {
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: white;
        }

        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .role-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid;
        }

        .role-card.admin { border-color: #e74c3c; }
        .role-card.editor { border-color: #3498db; }
        .role-card.viewer { border-color: #2ecc71; }
        .role-card.contributor { border-color: #f1c40f; }

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

        .user-item:last-child {
            border-bottom: none;
        }

        .user-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            margin-right: 0.8rem;
        }

        .total-users {
            color: #666;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .search-container {
                width: 100%;
                flex-direction: column;
            }
            
            .search-bar {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
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
            <!-- Admin Role -->
            <div class="role-card admin">
                <div class="role-header">
                    <div class="role-title">
                        <i class="fas fa-shield-alt"></i> Administrator
                    </div>
                    <span class="user-count">5 users</span>
                </div>
                <ul class="user-list">
                    <li class="user-item">
                        <img src="https://i.pravatar.cc/30" alt="avatar" class="user-avatar">
                        Sarah Johnson
                    </li>
                    <!-- Add more users -->
                </ul>
                <div class="total-users">Total Administrators: 5</div>
            </div>

            <!-- Editor Role -->
            <div class="role-card editor">
                <div class="role-header">
                    <div class="role-title">
                        <i class="fas fa-edit"></i> Editor
                    </div>
                    <span class="user-count">12 users</span>
                </div>
                <ul class="user-list">
                    <!-- Users list -->
                </ul>
                <div class="total-users">Total Editors: 12</div>
            </div>

            <!-- Add more role cards -->
        </div>
    </div>

    <script>
        // Add JavaScript for search and sorting functionality
        const searchInput = document.querySelector('.search-bar');
        const roleCards = document.querySelectorAll('.role-card');

        searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            
            roleCards.forEach(card => {
                const roleName = card.querySelector('.role-title').textContent.toLowerCase();
                const users = card.querySelectorAll('.user-item');
                let hasMatch = roleName.includes(searchTerm);

                users.forEach(user => {
                    const userName = user.textContent.toLowerCase();
                    if (userName.includes(searchTerm)) hasMatch = true;
                });

                card.style.display = hasMatch ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>