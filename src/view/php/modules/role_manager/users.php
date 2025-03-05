<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
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
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .back-button {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        h1 {
            font-size: 28px;
            color: var(--dark);
        }
        
        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .select-container {
            position: relative;
            min-width: 200px;
        }
        
        select {
            width: 100%;
            padding: 12px 15px;
            appearance: none;
            border: 1px solid #ddd;
            border-radius: var(--radius);
            background-color: white;
            font-size: 16px;
            cursor: pointer;
            transition: var(--transition);
        }
        
        select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: var(--shadow);
        }
        
        .select-container:after {
            content: '▼';
            font-size: 12px;
            color: #999;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
        }
        
        .search-filter {
            display: flex;
            gap: 10px;
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
        
        .users-table {
            width: 100%;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid var(--gray);
        }
        
        th {
            background-color: var(--light);
            font-weight: 600;
            color: var(--dark);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tbody tr {
            transition: var(--transition);
        }
        
        tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background-color: rgba(42, 157, 143, 0.2);
            color: var(--success);
        }
        
        .status-inactive {
            background-color: rgba(230, 57, 70, 0.2);
            color: var(--danger);
        }
        
        .status-pending {
            background-color: rgba(233, 196, 106, 0.2);
            color: var(--warning);
        }
        
        .role-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background-color: var(--primary-light);
            color: white;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            gap: 5px;
        }
        
        .pagination button {
            background: white;
            border: 1px solid #ddd;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: var(--radius);
            transition: var(--transition);
        }
        
        .pagination button:hover, .pagination button.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .no-results {
            text-align: center;
            padding: 30px;
            color: #777;
        }
        
        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-filter {
                flex-direction: column;
                width: 100%;
            }
            
            .search-container {
                width: 100%;
            }
            
            th, td {
                padding: 10px;
            }
            
            .users-table {
                overflow-x: auto;
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-left">
                <button class="back-button" onclick="goBack()">← Back</button>
                <h1>Users Management</h1>
            </div>
        </header>
        
        <div class="controls">
            <div class="select-container">
                <select id="role-select">
                    <option value="">Select Role</option>
                    <!-- Role options will be populated dynamically -->
                </select>
            </div>
            
            <div class="search-filter">
                <div class="search-container">
                    <input type="text" id="search-users" placeholder="Search users...">
                    <span class="search-icon">🔍</span>
                </div>
            </div>
        </div>
        
        <div class="users-table">
            <table>
                <thead>
                    <tr>
                        <th>User Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="users-tbody">
                    <!-- User rows will be populated dynamically -->
                </tbody>
            </table>
            <div id="no-results" class="no-results" style="display: none;">
                No users found matching your criteria.
            </div>
        </div>
        
        <div class="pagination" id="pagination">
            <!-- Pagination will be populated dynamically -->
        </div>
    </div>

    <script>
        // Updated role data with the specific roles
        const roles = [
            {
                id: 1,
                name: "Super Admin",
                description: "Complete system control with all privileges and configuration capabilities",
                userCount: 4,
                activeCount: 4,
                permissions: ["Read", "Write", "Delete", "Configure", "Manage Users", "System Settings"]
            },
            {
                id: 2,
                name: "Administrator",
                description: "Administrative access with user management privileges",
                userCount: 12,
                activeCount: 10,
                permissions: ["Read", "Write", "Delete", "Manage Users"]
            },
            {
                id: 3,
                name: "Super User",
                description: "Advanced usage privileges with limited administrative capabilities",
                userCount: 18,
                activeCount: 15,
                permissions: ["Read", "Write", "Limited Delete", "Approve"]
            },
            {
                id: 4,
                name: "Regular User",
                description: "Standard access for everyday system usage",
                userCount: 145,
                activeCount: 120,
                permissions: ["Read", "Limited Write"]
            }
        ];

        // Updated sample user data to match the new roles
        const users = [
            { id: 1, name: "John Smith", email: "john.smith@example.com", roleId: 1, status: "active" },
            { id: 2, name: "Emily Johnson", email: "emily.j@example.com", roleId: 2, status: "active" },
            { id: 3, name: "Michael Brown", email: "michael.b@example.com", roleId: 3, status: "active" },
            { id: 4, name: "Sarah Davis", email: "sarah.d@example.com", roleId: 4, status: "inactive" },
            { id: 5, name: "David Wilson", email: "david.w@example.com", roleId: 1, status: "active" },
            { id: 6, name: "Jennifer Lee", email: "jennifer.l@example.com", roleId: 2, status: "pending" },
            { id: 7, name: "Robert Taylor", email: "robert.t@example.com", roleId: 3, status: "active" },
            { id: 8, name: "Linda Miller", email: "linda.m@example.com", roleId: 2, status: "active" },
            { id: 9, name: "William Clark", email: "william.c@example.com", roleId: 3, status: "inactive" },
            { id: 10, name: "Elizabeth Walker", email: "elizabeth.w@example.com", roleId: 4, status: "active" },
            { id: 11, name: "Richard Hall", email: "richard.h@example.com", roleId: 3, status: "pending" },
            { id: 12, name: "Patricia Young", email: "patricia.y@example.com", roleId: 2, status: "active" },
            { id: 13, name: "Joseph Allen", email: "joseph.a@example.com", roleId: 1, status: "active" },
            { id: 14, name: "Barbara Harris", email: "barbara.h@example.com", roleId: 4, status: "inactive" },
            { id: 15, name: "Thomas King", email: "thomas.k@example.com", roleId: 4, status: "active" },
            { id: 16, name: "Margaret Scott", email: "margaret.s@example.com", roleId: 4, status: "active" },
            { id: 17, name: "Charles Wright", email: "charles.w@example.com", roleId: 2, status: "active" },
            { id: 18, name: "Susan Green", email: "susan.g@example.com", roleId: 4, status: "pending" },
            { id: 19, name: "Christopher Adams", email: "chris.a@example.com", roleId: 4, status: "active" },
            { id: 20, name: "Jessica Baker", email: "jessica.b@example.com", roleId: 3, status: "active" }
        ];

        // Initialize page with roles and retrieve roleId from URL
        function initPage() {
            // Populate role select dropdown
            const roleSelect = document.getElementById('role-select');
            roleSelect.innerHTML = '<option value="">Select Role</option>';
            
            roles.forEach(role => {
                const option = document.createElement('option');
                option.value = role.id;
                option.textContent = role.name;
                roleSelect.appendChild(option);
            });
            
            // Get role ID from URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            const roleId = urlParams.get('role');
            
            if (roleId) {
                roleSelect.value = roleId;
            }
            
            // Initial render of users
            filterAndRenderUsers();
            
            // Set up event listeners
            roleSelect.addEventListener('change', filterAndRenderUsers);
            document.getElementById('search-users').addEventListener('input', filterAndRenderUsers);
        }
        
        // Filter and render users based on selected role and search term
        function filterAndRenderUsers() {
            const roleId = document.getElementById('role-select').value;
            const searchTerm = document.getElementById('search-users').value.toLowerCase();
            
            // Filter users based on role and search term
            let filteredUsers = users;
            
            if (roleId) {
                filteredUsers = filteredUsers.filter(user => user.roleId == roleId);
            }
            
            if (searchTerm) {
                filteredUsers = filteredUsers.filter(user => 
                    user.name.toLowerCase().includes(searchTerm) || 
                    user.email.toLowerCase().includes(searchTerm)
                );
            }
            
            // Render filtered users
            renderUsers(filteredUsers);
        }
        
        // Render users table
        function renderUsers(usersToRender) {
            const tbody = document.getElementById('users-tbody');
            const noResults = document.getElementById('no-results');
            
            // Show/hide no results message
            if (usersToRender.length === 0) {
                tbody.innerHTML = '';
                noResults.style.display = 'block';
                return;
            } else {
                noResults.style.display = 'none';
            }
            
            // Clear existing rows
            tbody.innerHTML = '';
            
            // Create user rows
            usersToRender.forEach(user => {
                const role = roles.find(r => r.id == user.roleId);
                const row = document.createElement('tr');
                
                // Set status class based on user status
                let statusClass = '';
                switch(user.status) {
                    case 'active':
                        statusClass = 'status-active';
                        break;
                    case 'inactive':
                        statusClass = 'status-inactive';
                        break;
                    case 'pending':
                        statusClass = 'status-pending';
                        break;
                }
                
                row.innerHTML = `
                    <td>${user.name}</td>
                    <td>${user.email}</td>
                    <td><span class="role-badge">${role.name}</span></td>
                    <td><span class="status ${statusClass}">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</span></td>
                `;
                
                tbody.appendChild(row);
            });
        }
        
        // Go back to roles page
        function goBack() {
            window.location.href = 'user_roles_management.php';
        }
        
        // Initialize the page when DOM is loaded
        document.addEventListener('DOMContentLoaded', initPage);
    </script>
</body>
</html>