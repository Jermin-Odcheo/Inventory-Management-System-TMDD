/**
 * Users Module
 *
 * This file provides functionality to manage users and their role assignments in the system. It handles the display and management of user data, including role assignments and permissions. The module ensures proper validation, user authorization, and maintains data consistency across the system.
 *
 * @package    InventoryManagementSystem
 * @subpackage RolesAndPrivilegeManager
 * @author     TMDD Interns 25'
 */
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
            --archive: #6c757d;
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
        
        /* Action Button Styles */
        .action-buttons {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .batch-archive-btn {
            background-color: var(--archive);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
            opacity: 0.5;
            pointer-events: none;
        }
        
        .batch-archive-btn.active {
            opacity: 1;
            pointer-events: all;
        }
        
        .batch-archive-btn:hover {
            background-color: var(--dark);
        }
        
        /* Checkbox Styles */
        .checkbox-cell {
            width: 40px;
        }
        
        .user-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        /* Archive Button Styles */
        .archive-btn {
            background-color: var(--archive);
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: var(--radius);
            cursor: pointer;
            font-size: 14px;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .archive-btn:hover {
            background-color: var(--dark);
        }
        
        .archive-icon {
            font-size: 12px;
        }
        
        /* Archived User Style */
        tr.archived {
            opacity: 0.6;
            background-color: rgba(200, 200, 200, 0.2);
        }
        
        tr.archived td {
            color: var(--archive);
        }
        
        .restored-btn {
            background-color: var(--success);
        }
        
        .restored-btn:hover {
            background-color: #218878;
        }
        
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal {
            background-color: white;
            width: 90%;
            max-width: 500px;
            border-radius: var(--radius);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            padding: 0;
            transform: translateY(-20px);
            transition: var(--transition);
        }
        
        .modal-overlay.active .modal {
            transform: translateY(0);
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #777;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .modal-close:hover {
            color: var(--danger);
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .selected-users {
            margin-bottom: 20px;
        }
        
        .selected-users-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        
        .selected-user-badge {
            background-color: var(--primary-light);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--gray);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .modal-footer button {
            padding: 10px 16px;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .btn-cancel {
            background-color: var(--light);
            border: 1px solid #ddd;
            color: var(--dark);
        }
        
        .btn-cancel:hover {
            background-color: var(--gray);
        }
        
        .btn-confirm {
            background-color: var(--archive);
            border: none;
            color: white;
        }
        
        .btn-confirm:hover {
            background-color: var(--dark);
        }
        
        .warning-text {
            color: var(--archive);
            font-weight: 500;
            margin-bottom: 15px;
        }
        
        .select-all-container {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-right: 20px;
        }
        
        #select-all {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .archived-filter-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: 15px;
        }
        
        #show-archived {
            width: 18px;
            height: 18px;
            cursor: pointer;
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
            
            .modal {
                width: 95%;
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
                <div class="action-buttons">
                    <button id="batch-archive-btn" class="batch-archive-btn">
                        <span></span> Archive Selected
                    </button>
                    
                    <div class="archived-filter-container">
                        <input type="checkbox" id="show-archived">
                        <label for="show-archived">Show archived</label>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="users-table">
            <table>
                <thead>
                    <tr>
                        <th class="checkbox-cell">
                            <div class="select-all-container">
                                <input type="checkbox" id="select-all">
                            </div>
                        </th>
                        <th>User Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
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
    
    <!-- Modal for batch archive confirmation -->
    <div class="modal-overlay" id="archive-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Archive Users</h3>
                <button class="modal-close" id="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="warning-text">Are you sure you want to archive the following users?</p>
                <div class="selected-users">
                    <div class="selected-users-list" id="selected-users-list">
                        <!-- Selected users will be displayed here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" id="cancel-archive">Cancel</button>
                <button class="btn-confirm" id="confirm-archive">Archive Users</button>
            </div>
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

        // Updated sample user data to include archive status
        const users = [
            { id: 1, name: "John Smith", email: "john.smith@example.com", roleId: 1, status: "active", archived: false },
            { id: 2, name: "Emily Johnson", email: "emily.j@example.com", roleId: 2, status: "active", archived: false },
            { id: 3, name: "Michael Brown", email: "michael.b@example.com", roleId: 3, status: "active", archived: false },
            { id: 4, name: "Sarah Davis", email: "sarah.d@example.com", roleId: 4, status: "inactive", archived: true },
            { id: 5, name: "David Wilson", email: "david.w@example.com", roleId: 1, status: "active", archived: false },
            { id: 6, name: "Jennifer Lee", email: "jennifer.l@example.com", roleId: 2, status: "pending", archived: false },
            { id: 7, name: "Robert Taylor", email: "robert.t@example.com", roleId: 3, status: "active", archived: false },
            { id: 8, name: "Linda Miller", email: "linda.m@example.com", roleId: 2, status: "active", archived: false },
            { id: 9, name: "William Clark", email: "william.c@example.com", roleId: 3, status: "inactive", archived: true },
            { id: 10, name: "Elizabeth Walker", email: "elizabeth.w@example.com", roleId: 4, status: "active", archived: false },
            { id: 11, name: "Richard Hall", email: "richard.h@example.com", roleId: 3, status: "pending", archived: false },
            { id: 12, name: "Patricia Young", email: "patricia.y@example.com", roleId: 2, status: "active", archived: false },
            { id: 13, name: "Joseph Allen", email: "joseph.a@example.com", roleId: 1, status: "active", archived: false },
            { id: 14, name: "Barbara Harris", email: "barbara.h@example.com", roleId: 4, status: "inactive", archived: true },
            { id: 15, name: "Thomas King", email: "thomas.k@example.com", roleId: 4, status: "active", archived: false },
            { id: 16, name: "Margaret Scott", email: "margaret.s@example.com", roleId: 4, status: "active", archived: false },
            { id: 17, name: "Charles Wright", email: "charles.w@example.com", roleId: 2, status: "active", archived: false },
            { id: 18, name: "Susan Green", email: "susan.g@example.com", roleId: 4, status: "pending", archived: false },
            { id: 19, name: "Christopher Adams", email: "chris.a@example.com", roleId: 4, status: "active", archived: false },
            { id: 20, name: "Jessica Baker", email: "jessica.b@example.com", roleId: 3, status: "active", archived: false }
        ];

        // Track selected users
        let selectedUsers = [];
        
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
            document.getElementById('select-all').addEventListener('change', toggleSelectAll);
            document.getElementById('batch-archive-btn').addEventListener('click', openArchiveModal);
            document.getElementById('close-modal').addEventListener('click', closeArchiveModal);
            document.getElementById('cancel-archive').addEventListener('click', closeArchiveModal);
            document.getElementById('confirm-archive').addEventListener('click', archiveSelectedUsers);
            document.getElementById('show-archived').addEventListener('change', filterAndRenderUsers);
            
            // Update batch archive button state
            updateBatchArchiveButtonState();
        }
        
        // Filter and render users based on selected role and search term
        function filterAndRenderUsers() {
            const roleId = document.getElementById('role-select').value;
            const searchTerm = document.getElementById('search-users').value.toLowerCase();
            const showArchived = document.getElementById('show-archived').checked;
            
            // Filter users based on role, search term, and archive status
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
            
            if (!showArchived) {
                filteredUsers = filteredUsers.filter(user => !user.archived);
            }
            
            // Clear selection when filter changes
            selectedUsers = [];
            updateBatchArchiveButtonState();
            
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
                
                // Add archived class if user is archived
                if (user.archived) {
                    row.classList.add('archived');
                }
                
                // Create checkbox for selection (don't allow selecting archived users)
                const isChecked = selectedUsers.includes(user.id);
                const checkboxDisabled = user.archived ? 'disabled' : '';
                
                // Create archive/restore button based on user archive status
                const actionButton = user.archived 
                    ? `<button class="archive-btn restored-btn" data-user-id="${user.id}" onclick="restoreUser(${user.id})">
                        <span class="archive-icon"></span> Restore
                      </button>`
                    : `<button class="archive-btn" data-user-id="${user.id}" onclick="archiveUser(${user.id})">
                        <span class="archive-icon"></span> Archive
                      </button>`;
                
                row.innerHTML = `
                    <td class="checkbox-cell">
                        <input type="checkbox" class="user-checkbox" data-user-id="${user.id}" ${isChecked ? 'checked' : ''} ${checkboxDisabled}>
                    </td>
                    <td>${user.name}</td>
                    <td>${user.email}</td>
                    <td><span class="role-badge">${role.name}</span></td>
                    <td>${actionButton}</td>
                `;
                
                tbody.appendChild(row);
            });
            
            // Add event listeners to checkboxes
            document.querySelectorAll('.user-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', toggleUserSelection);
            });
        }
        
        // Toggle user selection
        function toggleUserSelection(event) {
            const userId = parseInt(event.target.getAttribute('data-user-id'));
            
            if (event.target.checked) {
                // Add user to selected users if not already in the array
                if (!selectedUsers.includes(userId)) {
                    selectedUsers.push(userId);
                }
            } else {
                // Remove user from selected users
                selectedUsers = selectedUsers.filter(id => id !== userId);
                
                // Uncheck "select all" if any user is unchecked
                document.getElementById('select-all').checked = false;
            }
            
            // Update batch archive button state
            updateBatchArchiveButtonState();
        }
        
        // Toggle select all users
        function toggleSelectAll(event) {
            const isChecked = event.target.checked;
            const checkboxes = document.querySelectorAll('.user-checkbox:not([disabled])');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
                const userId = parseInt(checkbox.getAttribute('data-user-id'));
                
                if (isChecked && !selectedUsers.includes(userId)) {
                    selectedUsers.push(userId);
                }
            });
            
            // Clear selected users if unchecked
            if (!isChecked) {
                selectedUsers = [];
            }
            
            // Update batch archive button state
            updateBatchArchiveButtonState();
        }
        
        // Update batch archive button state based on selected users
        function updateBatchArchiveButtonState() {
            const archiveButton = document.getElementById('batch-archive-btn');
            
            if (selectedUsers.length > 0) {
                archiveButton.classList.add('active');
            } else {
                archiveButton.classList.remove('active');
            }
        }
        
        // Archive a single user
        function archiveUser(userId) {
            const userIndex = users.findIndex(u => u.id === userId);
            if (userIndex !== -1) {
                users[userIndex].archived = true;
                filterAndRenderUsers();
            }
        }
        
        // Restore a single user
        function restoreUser(userId) {
            const userIndex = users.findIndex(u => u.id === userId);
            if (userIndex !== -1) {
                users[userIndex].archived = false;
                filterAndRenderUsers();
            }
        }
        
        // Open archive modal for batch operations
        function openArchiveModal() {
            if (selectedUsers.length === 0) return;
            
            // Populate selected users list
            const selectedUsersList = document.getElementById('selected-users-list');
            selectedUsersList.innerHTML = '';
            
            selectedUsers.forEach(userId => {
                const user = users.find(u => u.id === userId);
                if (user) {
                    const userBadge = document.createElement('div');
                    userBadge.className = 'selected-user-badge';
                    userBadge.textContent = user.name;
                    selectedUsersList.appendChild(userBadge);
                }
            });
            
            // Show modal
            document.getElementById('archive-modal').classList.add('active');
        }
        
        // Close archive modal
        function closeArchiveModal() {
            document.getElementById('archive-modal').classList.remove('active');
        }
        
        // Archive selected users
        function archiveSelectedUsers() {
            // Update user archive status
            selectedUsers.forEach(userId => {
                const userIndex = users.findIndex(u => u.id === userId);
                if (userIndex !== -1) {
                    users[userIndex].archived = true;
                }
            });
            
            // Re-render users
            filterAndRenderUsers();
            
            // Close modal
            closeArchiveModal();
            
            // Clear selection
            selectedUsers = [];
            updateBatchArchiveButtonState();
        }
        
        // Go back to roles page
        function goBack() {
            window.location.href = 'user_roles_management.php';
        }
        
        // Initialize the page when DOM is loaded
        document.addEventListener('DOMContentLoaded', initPage);
        
        // Make the archiveUser and restoreUser functions available globally
        window.archiveUser = archiveUser;
        window.restoreUser = restoreUser;
    </script>
</body>
</html>