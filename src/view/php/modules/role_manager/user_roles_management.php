<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Roles Management</title>
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --secondary: #3f37c9;
            --dark: #2b2d42;
            --light: #f8f9fa;
            --gray: #e9ecef;
            --success: #2a9d8f;
            --warning: #e9c46a;
            --danger: #e63946;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --radius: 8px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--gray);
            color: var(--dark);
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }

        h1 {
            font-size: 28px;
            color: var(--dark);
        }

        .search-container {
            position: relative;
            width: 300px;
        }

        .search-container input {
            width: 100%;
            padding: 12px 20px;
            border: 1px solid #ddd;
            border-radius: var(--radius);
            font-size: 16px;
            transition: var(--transition);
        }

        .search-container input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: var(--shadow);
        }

        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .role-card {
            background-color: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .role-card:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background-color: var(--primary);
        }

        .role-card h2 {
            font-size: 18px;
            margin-bottom: 8px;
            color: var(--dark);
        }

        .role-card p {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
        }

        .permissions-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .badge-read {
            background-color: #e9ecef;
            color: var(--dark);
            border: 1px solid #ced4da;
        }

        .badge-write {
            background-color: var(--primary-light);
            color: white;
        }

        .badge-delete {
            background-color: var(--danger);
            color: white;
        }

        .badge-config {
            background-color: var(--secondary);
            color: white;
        }

        .badge-manage {
            background-color: var(--success);
            color: white;
        }

        .badge-approve {
            background-color: var(--warning);
            color: var(--dark);
        }

        .badge-limited {
            position: relative;
            background: repeating-linear-gradient(
                -45deg,
                rgba(255,255,255,0.1),
                rgba(255,255,255,0.1) 5px,
                rgba(255,255,255,0.2) 5px,
                rgba(255,255,255,0.2) 10px
            );
        }

        .badge-limited.badge-write {
            background-color: rgba(72, 149, 239, 0.8);
        }

        .badge-limited.badge-delete {
            background-color: rgba(230, 57, 70, 0.8);
        }

        .badge::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
            background-color: currentColor;
            opacity: 0.7;
        }

        .role-stats {
            display: flex;
            justify-content: space-between;
            margin-top: auto;
            padding-top: 12px;
            border-top: 1px solid var(--gray);
        }

        .stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .stat-number {
            font-size: 22px;
            font-weight: bold;
            color: var(--primary);
        }

        .stat-label {
            font-size: 12px;
            color: #999;
        }

        @media (max-width: 768px) {
            .roles-grid {
                grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            }
            
            header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-container {
                width: 100%;
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>User Roles Management</h1>
            <div class="search-container">
                <input type="text" id="search-roles" placeholder="Search roles...">
                <span class="search-icon">🔍</span>
            </div>
        </header>
        
        <div class="roles-grid" id="roles-container"></div>
    </div>

    <script>
        const roles = [
            { id: 1, name: "Super Admin", description: "Complete system control with all privileges.", userCount: 4, activeCount: 4, permissions: ["Read", "Write", "Delete", "Configure", "Manage Users", "System Settings"] },
            { id: 2, name: "Administrator", description: "Administrative access with user management.", userCount: 12, activeCount: 10, permissions: ["Read", "Write", "Delete", "Manage Users"] },
            { id: 3, name: "Super User", description: "Advanced usage privileges.", userCount: 18, activeCount: 15, permissions: ["Read", "Write", "Limited Delete", "Approve"] },
            { id: 4, name: "Regular User", description: "Standard access for everyday use.", userCount: 145, activeCount: 120, permissions: ["Read", "Limited Write"] }
        ];

        function getBadgeClass(permission) {
            // Base badge class mapping
            const classMap = {
                'Read': 'badge-read',
                'Write': 'badge-write',
                'Delete': 'badge-delete',
                'Configure': 'badge-config',
                'Manage Users': 'badge-manage',
                'System Settings': 'badge-config',
                'Approve': 'badge-approve'
            };
            
            // Handle limited permissions
            if (permission.startsWith('Limited')) {
                const basePerm = permission.replace('Limited ', '');
                return `${classMap[basePerm] || 'badge-primary'} badge-limited`;
            }
            
            return classMap[permission] || 'badge-primary';
        }

        function renderRoleCards(roles) {
            const rolesContainer = document.getElementById('roles-container');
            rolesContainer.innerHTML = roles.map(role => `
                <div class="role-card" data-role-id="${role.id}" onclick="window.location.href='users.php?role=${role.id}'">
                    <h2>${role.name}</h2>
                    <p>${role.description}</p>
                    <div class="permissions-container">
                        ${role.permissions.map(perm => `<span class="badge ${getBadgeClass(perm)}">${perm}</span>`).join('')}
                    </div>
                    <div class="role-stats">
                        <div class="stat"><span class="stat-number">${role.userCount}</span><span class="stat-label">Users</span></div>
                        <div class="stat"><span class="stat-number">${role.activeCount}</span><span class="stat-label">Active</span></div>
                    </div>
                </div>
            `).join('');
        }

        document.getElementById('search-roles').addEventListener('input', (e) => {
            renderRoleCards(roles.filter(role => 
                role.name.toLowerCase().includes(e.target.value.toLowerCase()) || 
                role.description.toLowerCase().includes(e.target.value.toLowerCase())
            ));
        });

        document.addEventListener('DOMContentLoaded', () => renderRoleCards(roles));
    </script>
</body>
</html>