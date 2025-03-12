<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Roles Management</title>
</head>

<style>
    /* Base Styles & Reset */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}

:root {
    /* Modern color palette */
    --primary: #4f46e5;
    --primary-hover: #4338ca;
    --primary-light: #eef2ff;
    --danger: #ef4444;
    --danger-light: #fee2e2;
    --success: #10b981;
    --success-light: #d1fae5;
    
    /* Neutral colors */
    --text-primary: #111827;
    --text-secondary: #4b5563;
    --text-tertiary: #6b7280;
    --border: #e5e7eb;
    --border-focus: #a5b4fc;
    --background: #f9fafb;
    --background-alt: #ffffff;
    
    /* Shadows */
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    
    /* Transitions */
    --transition-fast: all 0.15s ease;
    --transition: all 0.2s ease;
    
    /* Borders */
    --radius-sm: 0.25rem;
    --radius-md: 0.375rem;
    --radius-lg: 0.5rem;
    --radius-xl: 0.75rem;
    --radius-full: 9999px;
    
    /* Spacing */
    --spacing-xs: 0.25rem;
    --spacing-sm: 0.5rem;
    --spacing-md: 1rem;
    --spacing-lg: 1.5rem;
    --spacing-xl: 2rem;
    --spacing-2xl: 2.5rem;
}

body {
    background-color: var(--background);
    color: var(--text-primary);
    line-height: 1.6;
    font-size: 0.875rem;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

.container {
    max-width: 1280px;
    margin: 0 auto;
    padding: var(--spacing-xl);
}

/* Header Section */
header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-xl);
    padding-bottom: var(--spacing-md);
    border-bottom: 1px solid var(--border);
}

header h1 {
    color: var(--text-primary);
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: -0.025em;
}

.search-container {
    position: relative;
    width: 320px;
}

.search-container input {
    width: 100%;
    padding: 0.625rem 2.5rem 0.625rem 1rem;
    border: 1px solid var(--border);
    border-radius: var(--radius-full);
    background-color: var(--background-alt);
    transition: var(--transition);
    font-size: 0.875rem;
}

.search-container::after {
    content: "🔍";
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-tertiary);
    font-size: 0.875rem;
    pointer-events: none;
}

.search-container input:focus {
    outline: none;
    border-color: var(--border-focus);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}

/* Filters Container */
.filters-container {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: var(--spacing-xl);
    flex-wrap: wrap;
    gap: var(--spacing-md);
}

.search-role, .filter-container {
    display: flex;
    flex-direction: column;
    min-width: 220px;
}

.search-role label, .filter-container label {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: var(--text-tertiary);
    margin-bottom: var(--spacing-xs);
    font-weight: 600;
    letter-spacing: 0.05em;
}

.search-role input, .filter-container select {
    padding: 0.625rem 0.75rem;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    background-color: var(--background-alt);
    font-size: 0.875rem;
    transition: var(--transition);
}

.search-role input:focus, .filter-container select:focus {
    outline: none;
    border-color: var(--border-focus);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}

.filter-container select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    padding-right: 2.25rem;
}

.action-buttons {
    display: flex;
    align-items: flex-end;
}

#add-user-role-btn {
    background-color: var(--primary);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    padding: 0.625rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    text-transform: capitalize;
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    box-shadow: var(--shadow-sm);
}

#add-user-role-btn::before {
    content: "+";
    font-size: 1rem;
    font-weight: 600;
}

#add-user-role-btn:hover {
    background-color: var(--primary-hover);
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

#add-user-role-btn:active {
    transform: translateY(0);
}

/* Table Styles */
table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background-color: var(--background-alt);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-md);
}

thead {
    background-color: var(--background);
}

th {
    text-align: left;
    padding: 1rem;
    font-weight: 600;
    color: var(--text-secondary);
    border-bottom: 1px solid var(--border);
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}

td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    font-size: 0.875rem;
    color: var(--text-primary);
    vertical-align: middle;
}

tbody tr:last-child td {
    border-bottom: none;
}

tbody tr:hover {
    background-color: var(--primary-light);
}

tr td:first-child {
    font-weight: 500;
}

/* Button Styles */
.edit-btn, .delete-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.375rem;
    border-radius: var(--radius-full);
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
}

.edit-btn {
    color: var(--primary);
}

.delete-btn {
    color: var(--danger);
}

.edit-btn:hover {
    background-color: var(--primary-light);
}

.delete-btn:hover {
    background-color: var(--danger-light);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.4);
    overflow: auto;
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}

.modal-content {
    background-color: var(--background-alt);
    margin: 5% auto;
    padding: var(--spacing-xl);
    width: 650px;
    max-width: 90%;
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-xl);
    animation: modalFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translateY(-1.5rem) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-content h2 {
    color: var(--text-primary);
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-md);
    border-bottom: 1px solid var(--border);
    font-size: 1.25rem;
    text-transform: capitalize;
    font-weight: 600;
}

.modal-content h3 {
    color: var(--text-primary);
    margin-bottom: var(--spacing-md);
    font-size: 1rem;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.05em;
}

.modal-body {
    margin-bottom: var(--spacing-lg);
}

.form-group {
    margin-bottom: var(--spacing-lg);
}

.form-group label {
    display: block;
    margin-bottom: var(--spacing-sm);
    color: var(--text-secondary);
    font-weight: 500;
    text-transform: capitalize;
    font-size: 0.875rem;
}

.form-group select {
    width: 100%;
    padding: 0.625rem 0.75rem;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    background-color: var(--background-alt);
    font-size: 0.875rem;
    transition: var(--transition);
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    padding-right: 2.25rem;
}

.form-group select:focus {
    outline: none;
    border-color: var(--border-focus);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}

/* Selected Items Styles */
#selected-roles-container,
#selected-users-container,
#added-departments-container {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-sm);
    margin-top: var(--spacing-sm);
    min-height: 2.25rem;
}

.selected-item {
    display: inline-flex;
    align-items: center;
    background-color: var(--primary-light);
    color: var(--primary);
    padding: 0.375rem 0.625rem;
    border-radius: var(--radius-full);
    font-size: 0.8125rem;
    font-weight: 500;
    border: 1px solid rgba(79, 70, 229, 0.2);
}

.remove-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--primary);
    margin-left: var(--spacing-sm);
    font-size: 0.875rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.125rem;
    height: 1.125rem;
    border-radius: 50%;
    transition: var(--transition);
}

.remove-btn:hover {
    background-color: rgba(79, 70, 229, 0.2);
}

/* Lists Table Styles */
#current-users-table,
#departments-table {
    margin-top: var(--spacing-sm);
    max-height: 250px;
    overflow-y: auto;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
}

#current-users-table td,
#departments-table td {
    padding: 0.625rem 0.75rem;
}

/* Table scrollbar styling */
#current-users-table tbody,
#departments-table tbody {
    display: block;
    max-height: 200px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(203, 213, 225, 0.5) transparent;
}

/* Webkit scrollbar styling */
#current-users-table tbody::-webkit-scrollbar,
#departments-table tbody::-webkit-scrollbar {
    width: 0.375rem;
    height: 0.375rem;
}

#current-users-table tbody::-webkit-scrollbar-track
/* Continuing from where the CSS left off - scrollbar styling */

#current-users-table tbody::-webkit-scrollbar-track,
#departments-table tbody::-webkit-scrollbar-track {
    background: transparent;
    border-radius: var(--radius-full);
}

#current-users-table tbody::-webkit-scrollbar-thumb,
#departments-table tbody::-webkit-scrollbar-thumb {
    background-color: rgba(203, 213, 225, 0.5);
    border-radius: var(--radius-full);
}

#current-users-table tbody::-webkit-scrollbar-thumb:hover,
#departments-table tbody::-webkit-scrollbar-thumb:hover {
    background-color: rgba(148, 163, 184, 0.7);
}

/* Modal Footer Styles */
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: var(--spacing-md);
    margin-top: var(--spacing-xl);
    padding-top: var(--spacing-md);
    border-top: 1px solid var(--border);
}

.modal-footer button {
    padding: 0.625rem 1.25rem;
    border-radius: var(--radius-md);
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
}

#close-user-roles-modal,
#close-department-role-modal {
    background-color: var(--background);
    color: var(--text-tertiary);
    border: 1px solid var(--border);
}

#close-user-roles-modal:hover,
#close-department-role-modal:hover {
    background-color: var(--background-alt);
    color: var(--text-secondary);
}

#save-user-roles,
#save-department-role {
    background-color: var(--primary);
    color: white;
    border: none;
    box-shadow: var(--shadow-sm);
}

#save-user-roles:hover,
#save-department-role:hover {
    background-color: var(--primary-hover);
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

/* Empty State */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: var(--spacing-2xl);
    background-color: var(--background-alt);
    border-radius: var(--radius-lg);
    text-align: center;
}

.empty-state-icon {
    font-size: 2.5rem;
    color: var(--text-tertiary);
    margin-bottom: var(--spacing-md);
    opacity: 0.5;
}

.empty-state-message {
    color: var(--text-secondary);
    font-weight: 500;
    margin-bottom: var(--spacing-md);
}

.empty-state-action {
    background-color: var(--primary);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    padding: 0.625rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
}

.empty-state-action:hover {
    background-color: var(--primary-hover);
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

/* Toast Notifications */
.toast-container {
    position: fixed;
    top: var(--spacing-lg);
    right: var(--spacing-lg);
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
    z-index: 9999;
}

.toast {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-md) var(--spacing-lg);
    border-radius: var(--radius-md);
    background-color: var(--background-alt);
    box-shadow: var(--shadow-xl);
    animation: toastSlideIn 0.3s ease forwards;
    max-width: 320px;
    overflow: hidden;
    position: relative;
}

@keyframes toastSlideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.toast-success {
    border-left: 4px solid var(--success);
}

.toast-error {
    border-left: 4px solid var(--danger);
}

.toast-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 50%;
    flex-shrink: 0;
}

.toast-success .toast-icon {
    background-color: var(--success-light);
    color: var(--success);
}

.toast-error .toast-icon {
    background-color: var(--danger-light);
    color: var(--danger);
}

.toast-content {
    flex-grow: 1;
}

.toast-title {
    font-weight: 600;
    font-size: 0.875rem;
    margin-bottom: var(--spacing-xs);
}

.toast-message {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.toast-close {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-tertiary);
    transition: var(--transition);
    padding: var(--spacing-xs);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-full);
}

.toast-close:hover {
    background-color: var(--border);
    color: var(--text-primary);
}

/* Progress bar for toast */
.toast-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background-color: var(--primary);
    animation: toastProgress 5s linear forwards;
}

@keyframes toastProgress {
    from {
        width: 100%;
    }
    to {
        width: 0%;
    }
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.5rem;
    border-radius: var(--radius-full);
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;
}

.badge-blue {
    background-color: var(--primary-light);
    color: var(--primary);
}

.badge-green {
    background-color: var(--success-light);
    color: var(--success);
}

.badge-red {
    background-color: var(--danger-light);
    color: var(--danger);
}

/* Responsive Styles */
@media (max-width: 1024px) {
    .filters-container {
        flex-direction: column;
        align-items: stretch;
    }

    .filters-container > div {
        width: 100%;
        margin-bottom: var(--spacing-md);
    }

    .search-container {
        width: 100%;
        max-width: 100%;
    }
}

@media (max-width: 768px) {
    .container {
        padding: var(--spacing-md);
    }

    header {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--spacing-md);
    }

    header h1 {
        margin-bottom: var(--spacing-sm);
    }

    .search-container {
        width: 100%;
    }

    table {
        display: block;
        overflow-x: auto;
    }

    .modal-content {
        width: 95%;
        padding: var(--spacing-lg);
    }
}

/* Checkbox and Radio custom styling */
input[type="checkbox"],
input[type="radio"] {
    appearance: none;
    width: 1.125rem;
    height: 1.125rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-sm);
    background-color: var(--background-alt);
    transition: var(--transition);
    position: relative;
    cursor: pointer;
}

input[type="radio"] {
    border-radius: 50%;
}

input[type="checkbox"]:checked,
input[type="radio"]:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

input[type="checkbox"]:checked::after {
    content: "";
    position: absolute;
    left: 0.3125rem;
    top: 0.1875rem;
    width: 0.375rem;
    height: 0.5625rem;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

input[type="radio"]:checked::after {
    content: "";
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    background-color: white;
}

input[type="checkbox"]:focus,
input[type="radio"]:focus {
    outline: none;
    border-color: var(--border-focus);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}

/* Loader/Spinner */
.loader {
    width: 1.5rem;
    height: 1.5rem;
    border: 2px solid rgba(79, 70, 229, 0.3);
    border-radius: 50%;
    border-top-color: var(--primary);
    animation: spin 0.8s linear infinite;
    margin: 0 auto;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Button with loader */
.btn-loading {
    position: relative;
    color: transparent !important;
}

.btn-loading::after {
    content: "";
    position: absolute;
    width: 1rem;
    height: 1rem;
    top: 50%;
    left: 50%;
    margin-left: -0.5rem;
    margin-top: -0.5rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 0.8s linear infinite;
}

/* Keyboard shortcut indicators */
.keyboard-shortcut {
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-xs);
    margin-left: var(--spacing-md);
    color: var(--text-tertiary);
    font-size: 0.75rem;
}

.key {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.25rem;
    height: 1.25rem;
    padding: 0 var(--spacing-xs);
    background-color: var(--background);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    box-shadow: var(--shadow-sm);
    font-size: 0.6875rem;
    font-weight: 500;
}

/* Focus styles for accessibility */
:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}
</style>

<body>
    <div class="container">
        <header>
            <h1>USER ROLES MANAGER</h1>
            <div class="search-container">
                <input type="text" id="search-users" placeholder="search user">
            </div>
        </header>
        
        <div class="filters-container">
            <div class="search-role">
                <label for="search-roles">search for role</label>
                <input type="text" id="search-roles">
            </div>
            <div class="filter-container">
                <label for="filter-dropdown">filter</label>
                <select id="filter-dropdown">
                    <option value="">All</option>
                    <option value="dept1">Department 1</option>
                    <option value="dept2">Department 2</option>
                </select>
            </div>
            <div class="action-buttons">
                <button id="add-user-role-btn">add user to role</button>
            </div>
        </div>
        
        <table id="user-roles-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Departments</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td rowspan="3">user 1</td>
                    <td>role 1</td>
                    <td>dept 1, dept 2</td>
                    <td>
                        <button class="edit-btn">✏️</button>
                        <button class="delete-btn">🗑️</button>
                    </td>
                </tr>
                <tr>
                    <td>role 2</td>
                    <td>dept 1, dept 2</td>
                    <td>
                        <button class="edit-btn">✏️</button>
                        <button class="delete-btn">🗑️</button>
                    </td>
                </tr>
                <tr>
                    <td>role 3</td>
                    <td>dept 1, dept 2</td>
                    <td>
                        <button class="edit-btn">✏️</button>
                        <button class="delete-btn">🗑️</button>
                    </td>
                </tr>
                <tr>
                    <td rowspan="2">user 2</td>
                    <td>role 1</td>
                    <td>dept 1, dept 2</td>
                    <td>
                        <button class="edit-btn">✏️</button>
                        <button class="delete-btn">🗑️</button>
                    </td>
                </tr>
                <tr>
                    <td>role 2</td>
                    <td>dept 1, dept 2</td>
                    <td>
                        <button class="edit-btn">✏️</button>
                        <button class="delete-btn">🗑️</button>
                    </td>
                </tr>
                <tr>
                    <td>User 3</td>
                    <td>role 1</td>
                    <td>dept 1, dept 2</td>
                    <td>
                        <button class="edit-btn">✏️</button>
                        <button class="delete-btn">🗑️</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Add User to Roles Modal -->
    <div id="add-user-roles-modal" class="modal">
        <div class="modal-content">
            <h2>add user to roles modal</h2>
            <div class="modal-body">
                <div class="form-group">
                    <label for="search-role-dropdown">search role/s</label>
                    <select id="search-role-dropdown">
                        <option value="">Select roles</option>
                        <option value="role1">Role 1</option>
                        <option value="role2">Role 2</option>
                        <option value="role3">Role 3</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>current role selection</label>
                    <div id="selected-roles-container">
                        <span class="selected-item">Role 1 <button class="remove-btn">✕</button></span>
                        <span class="selected-item">Role 2 <button class="remove-btn">✕</button></span>
                        <span class="selected-item">Role 3 <button class="remove-btn">✕</button></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="search-users-dropdown">search user/s</label>
                    <select id="search-users-dropdown">
                        <option value="">Select users</option>
                        <option value="user1">User 1</option>
                        <option value="user2">User 2</option>
                        <option value="user3">User 3</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>current user selection</label>
                    <div id="selected-users-container">
                        <span class="selected-item">User 1 <button class="remove-btn">✕</button></span>
                        <span class="selected-item">User 2 <button class="remove-btn">✕</button></span>
                        <span class="selected-item">User 3 <button class="remove-btn">✕</button></span>
                    </div>
                </div>
                <div class="form-group">
                    <label>list of current users</label>
                    <table id="current-users-table">
                        <tbody>
                            <tr><td>User 1</td><td><button class="delete-btn">🗑️</button></td></tr>
                            <tr><td>User 2</td><td><button class="delete-btn">🗑️</button></td></tr>
                            <tr><td>User 3</td><td><button class="delete-btn">🗑️</button></td></tr>
                            <tr><td>User 4</td><td><button class="delete-btn">🗑️</button></td></tr>
                            <tr><td>User 5</td><td><button class="delete-btn">🗑️</button></td></tr>
                            <tr><td>User 6</td><td><button class="delete-btn">🗑️</button></td></tr>
                            <tr><td>User 7</td><td><button class="delete-btn">🗑️</button></td></tr>
                            <tr><td>User 8</td><td><button class="delete-btn">🗑️</button></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button id="close-user-roles-modal">Cancel</button>
                <button id="save-user-roles">Save</button>
            </div>
        </div>
    </div>

    <!-- Add Department to Role Modal -->
    <div id="add-department-role-modal" class="modal">
        <div class="modal-content">
            <h2>Add department to role modal</h2>
            <div class="modal-body">
                <h3>ROLE TITLE</h3>
                <div class="form-group">
                    <label>Add department to role</label>
                    <select id="department-dropdown">
                        <option value="">Select department</option>
                        <option value="dept1">Department 1</option>
                        <option value="dept2">Department 2</option>
                        <option value="dept3">Department 3</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>ADDED DEPARTMENTS</label>
                    <div id="added-departments-container">
                        <span class="selected-item">Department 1 <button class="remove-btn">✕</button></span>
                        <span class="selected-item">Department 2 <button class="remove-btn">✕</button></span>
                        <span class="selected-item">Department 3 <button class="remove-btn">✕</button></span>
                    </div>
                </div>
                <div class="form-group">
                    <label>List of Departments</label>
                    <table id="departments-table">
                        <tbody>
                            <tr><td>Department 1</td><td><button class="delete-btn">🗑️</button></td></tr>
                            <tr><td>Department 2</td><td><button class="delete-btn">🗑️</button></td></tr>
                            <tr><td>Department 3</td><td><button class="delete-btn">🗑️</button></td></tr>
                            <tr><td>Department 4</td><td><button class="delete-btn">🗑️</button></td></tr>
                            <tr><td>Department 5</td><td><button class="delete-btn">🗑️</button></td></tr>
                            <tr><td>Department 6</td><td><button class="delete-btn">🗑️</button></td></tr>
                            <tr><td>Department 7</td><td><button class="delete-btn">🗑️</button></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button id="close-department-role-modal">Cancel</button>
                <button id="save-department-role">Save</button>
            </div>
        </div>
    </div>

    <script>
// Data models
let usersData = [
    { id: 1, name: "User 1" },
    { id: 2, name: "User 2" },
    { id: 3, name: "User 3" },
    { id: 4, name: "User 4" },
    { id: 5, name: "User 5" }
];

let rolesData = [
    { id: 1, name: "Role 1" },
    { id: 2, name: "Role 2" },
    { id: 3, name: "Role 3" },
    { id: 4, name: "Role 4" }
];

let departmentsData = [
    { id: 1, name: "Dept 1" },
    { id: 2, name: "Dept 2" },
    { id: 3, name: "Dept 3" },
    { id: 4, name: "Dept 4" }
];

// User-role-department assignments
let userRoleDepartments = [
    { userId: 1, roleId: 1, departmentIds: [1, 2] },
    { userId: 1, roleId: 2, departmentIds: [1, 2] },
    { userId: 1, roleId: 3, departmentIds: [1, 2] },
    { userId: 2, roleId: 1, departmentIds: [1, 2] },
    { userId: 2, roleId: 2, departmentIds: [1, 2] },
    { userId: 3, roleId: 1, departmentIds: [1, 2] }
];

// DOM elements
const addUserRoleBtn = document.getElementById('add-user-role-btn');
const userRolesTable = document.getElementById('user-roles-table');
const searchUsersInput = document.getElementById('search-users');
const searchRolesInput = document.getElementById('search-roles');
const filterDropdown = document.getElementById('filter-dropdown');

// Modal elements
const addUserRolesModal = document.getElementById('add-user-roles-modal');
const addDepartmentRoleModal = document.getElementById('add-department-role-modal');
const closeUserRolesModal = document.getElementById('close-user-roles-modal');
const closeDepartmentRoleModal = document.getElementById('close-department-role-modal');
const saveUserRolesBtn = document.getElementById('save-user-roles');
const saveDepartmentRoleBtn = document.getElementById('save-department-role');

// Dropdowns
const searchRoleDropdown = document.getElementById('search-role-dropdown');
const searchUsersDropdown = document.getElementById('search-users-dropdown');
const departmentDropdown = document.getElementById('department-dropdown');

// Selected containers
const selectedRolesContainer = document.getElementById('selected-roles-container');
const selectedUsersContainer = document.getElementById('selected-users-container');
const addedDepartmentsContainer = document.getElementById('added-departments-container');

// State management
let selectedRoles = [];
let selectedUsers = [];
let selectedDepartments = [];
let currentEditingData = null;

// Toast notification system
const toastContainer = document.createElement('div');
toastContainer.className = 'toast-container';
document.body.appendChild(toastContainer);

function showToast(type, title, message) {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const iconText = type === 'success' ? '✓' : '✗';
    
    toast.innerHTML = `
        <div class="toast-icon">${iconText}</div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close">✕</button>
        <div class="toast-progress"></div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        toast.remove();
    }, 5000);
    
    // Close button handler
    toast.querySelector('.toast-close').addEventListener('click', () => {
        toast.remove();
    });
}

// Initialize dropdowns with data
function initializeDropdowns() {
    // Clear existing options first
    searchRoleDropdown.innerHTML = '<option value="">Select roles</option>';
    searchUsersDropdown.innerHTML = '<option value="">Select users</option>';
    departmentDropdown.innerHTML = '<option value="">Select department</option>';
    filterDropdown.innerHTML = '<option value="">All Departments</option>';
    
    // Populate role dropdown
    rolesData.forEach(role => {
        const option = document.createElement('option');
        option.value = role.id;
        option.textContent = role.name;
        searchRoleDropdown.appendChild(option);
    });
    
    // Populate users dropdown
    usersData.forEach(user => {
        const option = document.createElement('option');
        option.value = user.id;
        option.textContent = user.name;
        searchUsersDropdown.appendChild(option);
    });
    
    // Populate departments dropdown
    departmentsData.forEach(dept => {
        const option = document.createElement('option');
        option.value = dept.id;
        option.textContent = dept.name;
        departmentDropdown.appendChild(option);
        
        // Also add to filter dropdown
        const filterOption = option.cloneNode(true);
        filterDropdown.appendChild(filterOption);
    });
}

// Render user roles table
function renderUserRolesTable(filterUserId = null, filterRoleName = null, filterDepartmentId = null) {
    const tbody = userRolesTable.querySelector('tbody');
    tbody.innerHTML = '';
    
    // Group by userId for rowspan
    const userGroups = {};
    
    userRoleDepartments.forEach(assignment => {
        if (!userGroups[assignment.userId]) {
            userGroups[assignment.userId] = [];
        }
        userGroups[assignment.userId].push(assignment);
    });
    
    // Apply filters
    const filteredUserIds = Object.keys(userGroups).filter(userId => {
        // User filter
        if (filterUserId && !getUserById(parseInt(userId)).name.toLowerCase().includes(filterUserId.toLowerCase())) {
            return false;
        }
        
        // Role filter (any role matches)
        if (filterRoleName) {
            const hasMatchingRole = userGroups[userId].some(assignment => {
                const role = getRoleById(assignment.roleId);
                return role && role.name.toLowerCase().includes(filterRoleName.toLowerCase());
            });
            if (!hasMatchingRole) return false;
        }
        
        // Department filter (any department matches)
        if (filterDepartmentId) {
            const hasMatchingDept = userGroups[userId].some(assignment => 
                assignment.departmentIds.includes(parseInt(filterDepartmentId))
            );
            if (!hasMatchingDept) return false;
        }
        
        return true;
    });
    
    // No results
    if (filteredUserIds.length === 0) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td colspan="4">
                <div class="empty-state">
                    <div class="empty-state-icon">🔍</div>
                    <div class="empty-state-message">No matching user roles found</div>
                    <button class="empty-state-action" id="clear-filters-btn">Clear filters</button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
        
        document.getElementById('clear-filters-btn').addEventListener('click', () => {
            searchUsersInput.value = '';
            searchRolesInput.value = '';
            filterDropdown.value = '';
            renderUserRolesTable();
        });
        
        return;
    }
    
    // Generate rows
    filteredUserIds.forEach(userId => {
        const user = getUserById(parseInt(userId));
        const userAssignments = userGroups[userId];
        
        userAssignments.forEach((assignment, index) => {
            const role = getRoleById(assignment.roleId);
            const departmentNames = assignment.departmentIds.map(deptId => 
                getDepartmentById(deptId).name
            ).join(', ');
            
            const tr = document.createElement('tr');
            
            // First row of each user gets rowspan
            if (index === 0) {
                tr.innerHTML = `
                    <td rowspan="${userAssignments.length}">${user.name}</td>
                    <td>${role.name}</td>
                    <td>${departmentNames}</td>
                    <td>
                        <button class="edit-btn" data-user-id="${userId}" data-role-id="${assignment.roleId}">✏️</button>
                        <button class="delete-btn" data-user-id="${userId}" data-role-id="${assignment.roleId}">🗑️</button>
                    </td>
                `;
            } else {
                tr.innerHTML = `
                    <td>${role.name}</td>
                    <td>${departmentNames}</td>
                    <td>
                        <button class="edit-btn" data-user-id="${userId}" data-role-id="${assignment.roleId}">✏️</button>
                        <button class="delete-btn" data-user-id="${userId}" data-role-id="${assignment.roleId}">🗑️</button>
                    </td>
                `;
            }
            
            tbody.appendChild(tr);
        });
    });
    
    // Add event listeners to the new buttons
    addEventListenersToButtons();
}

// Helper functions for data access
function getUserById(id) {
    return usersData.find(user => user.id === id) || { name: 'Unknown User' };
}

function getRoleById(id) {
    return rolesData.find(role => role.id === id) || { name: 'Unknown Role' };
}

function getDepartmentById(id) {
    return departmentsData.find(dept => dept.id === id) || { name: 'Unknown Dept' };
}

function getNextId(array) {
    return array.length > 0 ? Math.max(...array.map(item => item.id)) + 1 : 1;
}

// Handling selection in modals
function addItemToSelection(containerId, item, type) {
    const container = document.getElementById(containerId);
    
    // Check if already added
    if (containerId === 'selected-roles-container' && selectedRoles.some(r => r.id === item.id)) {
        return;
    }
    if (containerId === 'selected-users-container' && selectedUsers.some(u => u.id === item.id)) {
        return;
    }
    if (containerId === 'added-departments-container' && selectedDepartments.some(d => d.id === item.id)) {
        return;
    }
    
    // Add to selected array
    if (type === 'role') selectedRoles.push(item);
    if (type === 'user') selectedUsers.push(item);
    if (type === 'department') selectedDepartments.push(item);
    
    // Create UI element
    const selectedItem = document.createElement('span');
    selectedItem.className = 'selected-item';
    selectedItem.dataset.id = item.id;
    selectedItem.innerHTML = `
        ${item.name} 
        <button class="remove-btn" data-id="${item.id}" data-type="${type}">✕</button>
    `;
    
    container.appendChild(selectedItem);
    
    // Add event listener for remove button
    selectedItem.querySelector('.remove-btn').addEventListener('click', function() {
        // Remove from array
        if (type === 'role') {
            selectedRoles = selectedRoles.filter(r => r.id !== item.id);
        }
        if (type === 'user') {
            selectedUsers = selectedUsers.filter(u => u.id !== item.id);
        }
        if (type === 'department') {
            selectedDepartments = selectedDepartments.filter(d => d.id !== item.id);
        }
        
        // Remove from UI
        selectedItem.remove();
    });
}

// Event listeners
function addEventListenersToButtons() {
    // Edit buttons
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = parseInt(this.dataset.userId);
            const roleId = parseInt(this.dataset.roleId);
            
            // Find the assignment
            const assignment = userRoleDepartments.find(a => 
                a.userId === userId && a.roleId === roleId
            );
            
            if (assignment) {
                // Set current editing data
                currentEditingData = {
                    userId: assignment.userId,
                    roleId: assignment.roleId,
                    originalDeptIds: [...assignment.departmentIds]
                };
                
                // Update modal title
                const modalTitle = addDepartmentRoleModal.querySelector('h2');
                const roleTitle = addDepartmentRoleModal.querySelector('h3');
                const userName = getUserById(userId).name;
                const roleName = getRoleById(roleId).name;
                
                modalTitle.textContent = `Edit departments for ${userName}`;
                roleTitle.textContent = roleName.toUpperCase();
                
                // Clear existing selections
                addedDepartmentsContainer.innerHTML = '';
                selectedDepartments = [];
                
                // Add current departments
                assignment.departmentIds.forEach(deptId => {
                    const dept = getDepartmentById(deptId);
                    addItemToSelection('added-departments-container', dept, 'department');
                });
                
                // Show modal
                addDepartmentRoleModal.style.display = 'block';
            }
        });
    });
    
    // Delete buttons
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = parseInt(this.dataset.userId);
            const roleId = parseInt(this.dataset.roleId);
            
            if (confirm('Are you sure you want to delete this role assignment?')) {
                // Remove assignment
                userRoleDepartments = userRoleDepartments.filter(a => 
                    !(a.userId === userId && a.roleId === roleId)
                );
                
                // Re-render table
                renderUserRolesTable();
                
                showToast('success', 'Deleted', 'Role assignment has been removed successfully');
            }
        });
    });
}

// Modal selection handlers
searchRoleDropdown.addEventListener('change', function() {
    const roleId = parseInt(this.value);
    if (roleId) {
        const role = getRoleById(roleId);
        addItemToSelection('selected-roles-container', role, 'role');
        this.value = ''; // Reset dropdown
    }
});

searchUsersDropdown.addEventListener('change', function() {
    const userId = parseInt(this.value);
    if (userId) {
        const user = getUserById(userId);
        addItemToSelection('selected-users-container', user, 'user');
        this.value = ''; // Reset dropdown
    }
});

departmentDropdown.addEventListener('change', function() {
    const deptId = parseInt(this.value);
    if (deptId) {
        const dept = getDepartmentById(deptId);
        addItemToSelection('added-departments-container', dept, 'department');
        this.value = ''; // Reset dropdown
    }
});

// Filter handlers
searchUsersInput.addEventListener('input', function() {
    const filterUserId = this.

    value;
    const filterRoleName = searchRolesInput.value;
    const filterDepartmentId = filterDropdown.value;
    
    renderUserRolesTable(filterUserId, filterRoleName, filterDepartmentId);
});

searchRolesInput.addEventListener('input', function() {
    const filterUserId = searchUsersInput.value;
    const filterRoleName = this.value;
    const filterDepartmentId = filterDropdown.value;
    
    renderUserRolesTable(filterUserId, filterRoleName, filterDepartmentId);
});

filterDropdown.addEventListener('change', function() {
    const filterUserId = searchUsersInput.value;
    const filterRoleName = searchRolesInput.value;
    const filterDepartmentId = this.value;
    
    renderUserRolesTable(filterUserId, filterRoleName, filterDepartmentId);
});

// Modal open/close handlers
addUserRoleBtn.addEventListener('click', function() {
    // Clear existing selections
    selectedRoles = [];
    selectedUsers = [];
    selectedRolesContainer.innerHTML = '';
    selectedUsersContainer.innerHTML = '';
    
    // Show modal
    addUserRolesModal.style.display = 'block';
});

closeUserRolesModal.addEventListener('click', function() {
    addUserRolesModal.style.display = 'none';
});

closeDepartmentRoleModal.addEventListener('click', function() {
    addDepartmentRoleModal.style.display = 'none';
});

// Save handlers
saveUserRolesBtn.addEventListener('click', function() {
    if (selectedUsers.length === 0 || selectedRoles.length === 0) {
        showToast('error', 'Validation Error', 'Please select at least one user and one role');
        return;
    }
    
    // Create new assignments
    let newAssignments = [];
    
    selectedUsers.forEach(user => {
        selectedRoles.forEach(role => {
            // Check if this assignment already exists
            const existingAssignment = userRoleDepartments.find(a => 
                a.userId === user.id && a.roleId === role.id
            );
            
            if (existingAssignment) {
                // Skip if already exists
                return;
            }
            
            // Create new assignment with default departments
            newAssignments.push({
                userId: user.id,
                roleId: role.id,
                departmentIds: [1] // Default to first department
            });
        });
    });
    
    // Add new assignments
    userRoleDepartments = [...userRoleDepartments, ...newAssignments];
    
    // Close modal
    addUserRolesModal.style.display = 'none';
    
    // Re-render table
    renderUserRolesTable();
    
    // Show success toast
    showToast('success', 'Success', `${newAssignments.length} new role assignments added`);
});

saveDepartmentRoleBtn.addEventListener('click', function() {
    if (selectedDepartments.length === 0) {
        showToast('error', 'Validation Error', 'Please select at least one department');
        return;
    }
    
    if (currentEditingData) {
        // Find the assignment
        const assignmentIndex = userRoleDepartments.findIndex(a => 
            a.userId === currentEditingData.userId && a.roleId === currentEditingData.roleId
        );
        
        if (assignmentIndex !== -1) {
            // Update department IDs
            userRoleDepartments[assignmentIndex].departmentIds = selectedDepartments.map(dept => dept.id);
            
            // Close modal
            addDepartmentRoleModal.style.display = 'none';
            
            // Re-render table
            renderUserRolesTable();
            
            // Show success toast
            showToast('success', 'Success', 'Departments updated successfully');
        }
    }
});

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    if (event.target === addUserRolesModal) {
        addUserRolesModal.style.display = 'none';
    }
    if (event.target === addDepartmentRoleModal) {
        addDepartmentRoleModal.style.display = 'none';
    }
});

// Add new role-department assignment
function addRoleDepartmentAssignment(userId, roleId, departmentIds) {
    // Check if this assignment already exists
    const existingAssignment = userRoleDepartments.find(a => 
        a.userId === userId && a.roleId === roleId
    );
    
    if (existingAssignment) {
        // Update existing assignment
        existingAssignment.departmentIds = [...departmentIds];
    } else {
        // Create new assignment
        userRoleDepartments.push({
            userId,
            roleId,
            departmentIds
        });
    }
    
    // Re-render table
    renderUserRolesTable();
}

// Delete role-department assignment
function deleteRoleDepartmentAssignment(userId, roleId) {
    userRoleDepartments = userRoleDepartments.filter(a => 
        !(a.userId === userId && a.roleId === roleId)
    );
    
    // Re-render table
    renderUserRolesTable();
}

// Keyboard shortcuts
document.addEventListener('keydown', function(event) {
    // Escape key to close modals
    if (event.key === 'Escape') {
        addUserRolesModal.style.display = 'none';
        addDepartmentRoleModal.style.display = 'none';
    }
    
    // Ctrl+K to focus search
    if (event.ctrlKey && event.key === 'k') {
        event.preventDefault();
        searchUsersInput.focus();
    }
    
    // Ctrl+N to open add user role modal
    if (event.ctrlKey && event.key === 'n') {
        event.preventDefault();
        addUserRoleBtn.click();
    }
});

// Initialize the application
function initializeApp() {
    initializeDropdowns();
    renderUserRolesTable();
    
    // Show welcome toast
    setTimeout(() => {
        showToast('success', 'Welcome', 'User Roles Management System is ready');
    }, 500);
}

// Start the application
initializeApp();
    </script>
</body>
</html>