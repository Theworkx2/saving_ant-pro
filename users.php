<?php
require_once __DIR__ . '\inc\functions.php';

// Require admin role
requireRole('admin');

// Get current user data
$user = $auth->getUser();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set JSON header for AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
    }
    
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        } else {
            setFlash('error', 'Invalid CSRF token');
            redirect('/saving_ant/users.php');
        }
    }

    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Unknown action'];
    
    try {
        switch ($action) {
            case 'add_user':
                if (empty($_POST['username']) || empty($_POST['email']) || empty($_POST['password']) || empty($_POST['full_name'])) {
                    throw new Exception('All fields are required');
                }

                $result = $auth->createUser([
                    'username' => $_POST['username'],
                    'email' => $_POST['email'],
                    'password' => $_POST['password'],
                    'full_name' => $_POST['full_name'],
                    'roles' => $_POST['roles'] ?? ['user']
                ]);
                
                if (!$result['success']) {
                    throw new Exception($result['message'] ?? 'Failed to create user');
                }
                
                $response = [
                    'success' => true,
                    'message' => 'User created successfully'
                ];
                break;

            case 'edit_user':
                $userId = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
                if (!$userId) {
                    throw new Exception('Invalid user ID');
                }

                $data = [
                    'username' => $_POST['username'] ?? '',
                    'email' => $_POST['email'] ?? '',
                    'full_name' => $_POST['full_name'] ?? ''
                ];
                
                if (!empty($_POST['password'])) {
                    $data['password'] = $_POST['password'];
                }
                
                if (!$auth->updateUser($userId, $data)) {
                    throw new Exception('Failed to update user');
                }
                
                $response = [
                    'success' => true,
                    'message' => 'User updated successfully'
                ];
                break;

            case 'update_roles':
                $userId = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
                if (!$userId) {
                    throw new Exception('Invalid user ID');
                }

                $roles = $_POST['roles'] ?? [];
                if (empty($roles)) {
                    throw new Exception('User must have at least one role');
                }
                
                if (!$auth->updateUserRoles($userId, $roles)) {
                    throw new Exception('Failed to update user roles');
                }
                
                $response = [
                    'success' => true,
                    'message' => 'User roles updated successfully'
                ];
                break;

            case 'toggle_status':
                $userId = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
                if (!$userId) {
                    throw new Exception('Invalid user ID');
                }

                $status = filter_var($_POST['status'], FILTER_VALIDATE_INT);
                
                if ($userId === $user['id']) {
                    throw new Exception('You cannot deactivate your own account');
                }
                
                if (!$auth->updateUserStatus($userId, $status)) {
                    throw new Exception('Failed to update user status');
                }
                
                $response = [
                    'success' => true,
                    'message' => $status ? 'User activated successfully' : 'User deactivated successfully'
                ];
                break;
        }
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        echo json_encode($response);
        exit;
    } else {
        setFlash($response['success'] ? 'success' : 'error', $response['message']);
        redirect('/saving_ant/users.php');
    }
}

// Get all users with their roles
$users = $auth->getAllUsers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Saving Ant</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #0B5FFF;
            --dark: #06326B;
            --bg: #EAF3FF;
            --success: #0d8050;
            --warning: #bf8c0c;
            --danger: #db3737;
            --card-radius: 12px;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: #0b2240;
            line-height: 1.5;
            min-height: 100vh;
        }
        .main-content {
            margin-left: 250px;
            padding: 24px;
            min-height: 100vh;
            background: var(--bg);
        }
        .navbar {
            background: #fff;
            box-shadow: 0 1px 3px rgba(11,95,255,0.1);
            padding: 12px 24px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }
        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .logo {
            font-weight: 600;
            font-size: 20px;
            color: var(--dark);
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        .nav-links a {
            color: #18314d;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        .nav-links a:hover {
            color: var(--primary);
        }
        .nav-links a.active {
            color: var(--primary);
            font-weight: 600;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 92px 24px 40px;
        }
        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 24px;
        }
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        .alert-success { background: rgba(13,128,80,0.1); color: var(--success); }
        .alert-error { background: rgba(219,55,55,0.1); color: var(--danger); }
        
        .users-table {
            width: 100%;
            background: #fff;
            border-radius: var(--card-radius);
            box-shadow: 0 4px 12px rgba(11,95,255,0.05);
            border-collapse: collapse;
            margin-bottom: 24px;
            overflow: hidden;
        }
        .users-table th,
        .users-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e6eefb;
        }
        .users-table th {
            font-weight: 500;
            color: #6b7a93;
            font-size: 13px;
            background: #f8faff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .users-table td {
            font-size: 14px;
            color: #18314d;
            vertical-align: middle;
        }
        .users-table tr:last-child td {
            border-bottom: none;
        }
        .users-table tr:hover td {
            background: #fbfcff;
        }
        .role-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            margin-right: 4px;
            margin-bottom: 4px;
        }
        .role-badge-admin { background: rgba(11,95,255,0.1); color: var(--primary); }
        .role-badge-manager { background: rgba(13,128,80,0.1); color: var(--success); }
        .role-badge-user { background: rgba(191,140,12,0.1); color: var(--warning); }
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: none;
        }
        .btn-primary {
            background: var(--primary);
            color: #fff;
        }
        .btn-primary:hover {
            background: var(--dark);
        }
        .btn-outline {
            background: none;
            border: 1px solid #e6eefb;
            color: #6b7a93;
        }
        .btn-outline:hover {
            border-color: #d1e2f9;
            color: var(--dark);
            background: #fbfcff;
        }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            
        }
       .user-info {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px;
        text-decoration: none;
        color: #0B2240;
        border-radius: 8px;
        transition: all 0.2s ease;
        width: 218px;
        height: 49.8px;
        font-family: Poppins, system-ui, -apple-system, 'Segoe UI', sans-serif;
        font-size: 16px;
    }
        .user-name {
            font-weight: 500;
            font-size: 14px;
            color: var(--dark);
        }
        .user-role {
            font-size: 12px;
            color: #6b7a93;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(6,50,107,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.show {
            display: flex;
        }
        .modal-content {
            background: #fff;
            border-radius: var(--card-radius);
            padding: 24px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 8px 24px rgba(11,95,255,0.1);
        }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            color: #6b7a93;
            cursor: pointer;
        }
        .close-modal:hover {
            color: var(--dark);
        }
        .role-checkbox {
            margin-bottom: 16px;
        }
        .role-checkbox label {
            margin-left: 8px;
            font-size: 14px;
            color: #18314d;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #18314d;
        }
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e6eefb;
            border-radius: 6px;
            font-size: 14px;
        }
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .btn-success {
            background: var(--success);
            color: white;
            border: none;
        }
        .btn-success:hover {
            background: #0a6d42;
        }
        .btn-danger {
            background: var(--danger);
            color: white;
            border: none;
        }
        .btn-danger:hover {
            background: #c23030;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-success {
            background: rgba(13,128,80,0.1);
            color: var(--success);
        }
        .badge-danger {
            background: rgba(219,55,55,0.1);
            color: var(--danger);
        }
        .badge .material-icons {
            font-size: 14px;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .action-buttons .btn {
            padding: 6px 12px;
            font-size: 12px;
            min-width: 32px;
            height: 32px;
        }
        .action-buttons .material-icons {
            font-size: 18px !important;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
            .page-title { 
                font-size: 20px; 
                margin-bottom: 16px;
            }
            .users-table { 
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
    <?php include 'inc/sidebar.php'; ?>

    <main class="main-content">
        <?php if ($flash = getFlash()): ?>
            <div class="alert alert-<?= $flash['type'] ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <h1 class="page-title">User Management</h1>

        <table class="users-table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Roles</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['id']) ?></td>
                    <td><?= htmlspecialchars($u['full_name']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <?php foreach ($u['roles'] as $role): ?>
                            <span class="role-badge role-badge-<?= strtolower($role) ?>">
                                <?= htmlspecialchars(ucfirst($role)) ?>
                            </span>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <?php if ($u['is_active']): ?>
                            <span class="badge badge-success">
                                <i class="material-icons">check_circle</i>
                                Active
                            </span>
                        <?php else: ?>
                            <span class="badge badge-danger">
                                <i class="material-icons">cancel</i>
                                Inactive
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-outline" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)" title="Edit user">
                                <i class="material-icons">edit</i>
                            </button>
                            <button class="btn btn-outline" onclick="editRoles(<?= htmlspecialchars(json_encode($u)) ?>)" title="Manage roles">
                                <i class="material-icons">manage_accounts</i>
                            </button>
                            <?php if ($u['id'] !== $user['id']): ?>
                                <?php if ($u['is_active']): ?>
                                    <button class="btn btn-danger" onclick="toggleUserStatus(<?= $u['id'] ?>, 0)" title="Deactivate user">
                                        <i class="material-icons">block</i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-success" onclick="toggleUserStatus(<?= $u['id'] ?>, 1)" title="Activate user">
                                        <i class="material-icons">check_circle</i>
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="card">
            <div class="card-header">
                <h3>Add New User</h3>
            </div>
            <button class="btn btn-primary" onclick="showAddUserModal()">
                <i class="material-icons" style="font-size: 18px;">person_add</i>
                Add User
            </button>
        </div>
    </main>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New User</h3>
                <button class="close-modal" onclick="closeModal('addUserModal')">&times;</button>
            </div>
            <form id="addUserForm" method="post">
                <input type="hidden" name="action" value="add_user">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                
                <div class="form-group">
                    <label for="newUsername">Username</label>
                    <input type="text" id="newUsername" name="username" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="newEmail">Email</label>
                    <input type="email" id="newEmail" name="email" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="newFullName">Full Name</label>
                    <input type="text" id="newFullName" name="full_name" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="newPassword">Password</label>
                    <input type="password" id="newPassword" name="password" required class="form-control">
                </div>

                <div class="role-checkbox">
                    <input type="checkbox" name="roles[]" value="admin" id="newRoleAdmin">
                    <label for="newRoleAdmin">Administrator</label>
                </div>
                
                <div class="role-checkbox">
                    <input type="checkbox" name="roles[]" value="manager" id="newRoleManager">
                    <label for="newRoleManager">Manager</label>
                </div>
                
                <div class="role-checkbox">
                    <input type="checkbox" name="roles[]" value="user" id="newRoleUser" checked>
                    <label for="newRoleUser">Regular User</label>
                </div>

                <button type="submit" class="btn btn-primary">Add User</button>
                <button type="button" class="btn btn-outline" onclick="closeModal('addUserModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit User</h3>
                <button class="close-modal" onclick="closeModal('editUserModal')">&times;</button>
            </div>
            <form id="editUserForm" method="post">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="user_id" id="editUserId">
                
                <div class="form-group">
                    <label for="editUsername">Username</label>
                    <input type="text" id="editUsername" name="username" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="editEmail">Email</label>
                    <input type="email" id="editEmail" name="email" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="editFullName">Full Name</label>
                    <input type="text" id="editFullName" name="full_name" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="editPassword">New Password (leave blank to keep current)</label>
                    <input type="password" id="editPassword" name="password" class="form-control">
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-outline" onclick="closeModal('editUserModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Edit Roles Modal -->
    <div id="editRolesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit User Roles</h3>
                <button class="close-modal" onclick="closeModal('editRolesModal')">&times;</button>
            </div>
            <form id="editRolesForm" method="post">
                <input type="hidden" name="action" value="update_roles">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="user_id" id="roleUserId">
                
                <div class="role-checkbox">
                    <input type="checkbox" name="roles[]" value="admin" id="roleAdmin">
                    <label for="roleAdmin">Administrator</label>
                </div>
                
                <div class="role-checkbox">
                    <input type="checkbox" name="roles[]" value="manager" id="roleManager">
                    <label for="roleManager">Manager</label>
                </div>
                
                <div class="role-checkbox">
                    <input type="checkbox" name="roles[]" value="user" id="roleUser">
                    <label for="roleUser">Regular User</label>
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-outline" onclick="closeModal('editRolesModal')">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        // Show success or error message using SweetAlert2
        function showMessage(success, message) {
            Swal.fire({
                title: success ? 'Success!' : 'Error!',
                text: message,
                icon: success ? 'success' : 'error',
                confirmButtonColor: '#0B5FFF'
            });
        }

        // Handle form submissions with AJAX
        function handleFormSubmit(formElement, successCallback = null) {
            formElement.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                try {
                    // Show loading state
                    Swal.fire({
                        title: 'Processing...',
                        text: 'Please wait',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    const formData = new FormData(this);
                    
                    const response = await fetch('/saving_ant/users.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    let data;
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        data = await response.json();
                    } else {
                        // If not JSON, show error
                        throw new Error('Invalid response format');
                    }

                    await Swal.fire({
                        title: data.success ? 'Success!' : 'Error!',
                        text: data.message,
                        icon: data.success ? 'success' : 'error',
                        confirmButtonColor: '#0B5FFF'
                    });

                    if (data.success) {
                        closeModal(formElement.closest('.modal').id);
                        if (successCallback) successCallback();
                        window.location.reload();
                    }
                } catch (error) {
                    console.error('Error:', error);
                    await Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred. Please try again.',
                        icon: 'error',
                        confirmButtonColor: '#0B5FFF'
                    });
                }
            });
        }

        // Initialize form handlers
        document.addEventListener('DOMContentLoaded', function() {
            handleFormSubmit(document.getElementById('addUserForm'));
            handleFormSubmit(document.getElementById('editUserForm'));
            handleFormSubmit(document.getElementById('editRolesForm'));
        });

        function showAddUserModal() {
            document.getElementById('addUserModal').classList.add('show');
        }

        function editUser(user) {
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editUsername').value = user.username;
            document.getElementById('editEmail').value = user.email;
            document.getElementById('editFullName').value = user.full_name;
            document.getElementById('editPassword').value = '';
            document.getElementById('editUserModal').classList.add('show');
        }

        function editRoles(user) {
            document.getElementById('roleUserId').value = user.id;
            document.getElementById('roleAdmin').checked = user.roles.includes('admin');
            document.getElementById('roleManager').checked = user.roles.includes('manager');
            document.getElementById('roleUser').checked = user.roles.includes('user');
            document.getElementById('editRolesModal').classList.add('show');
        }

        function toggleUserStatus(userId, status) {
            Swal.fire({
                title: 'Are you sure?',
                text: `Do you want to ${status ? 'activate' : 'deactivate'} this user?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: status ? '#0d8050' : '#db3737',
                cancelButtonColor: '#6b7a93',
                confirmButtonText: status ? 'Yes, activate!' : 'Yes, deactivate!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'toggle_status');
                    formData.append('csrf_token', '<?= generateCsrfToken() ?>');
                    formData.append('user_id', userId);
                    formData.append('status', status);

                    fetch('/saving_ant/users.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        showMessage(data.success, data.message);
                        if (data.success) {
                            setTimeout(() => window.location.reload(), 1500);
                        }
                    })
                    .catch(error => {
                        showMessage(false, 'An error occurred. Please try again.');
                    });
                }
            });
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }

        // Show flash message if exists
        <?php if ($flash = getFlash()): ?>
        showMessage(
            '<?= $flash['type'] ?>' === 'success',
            '<?= htmlspecialchars(addslashes($flash['message'])) ?>'
        );
        <?php endif; ?>
    </script>

</body>
</html>