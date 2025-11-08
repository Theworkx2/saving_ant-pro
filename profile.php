<?php
require_once __DIR__ . '/inc/functions.php';

// Require login
requireAuth();

// Get current user data
$user = $auth->getUser();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        setFlash('warning', 'Invalid request. Please try again.');
        redirect('profile.php');
    }

    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);

    try {
        $pdo = $auth->getPdo();
        
        // First check if the phone_number column exists
        $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone_number'")->fetchAll();
        $hasPhoneNumber = !empty($columns);

        if ($hasPhoneNumber) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = ?, email = ?, phone_number = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $email, $phone, $user['id']]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = ?, email = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $email, $user['id']]);
        }
        setFlash('success', 'Profile updated successfully.');
        redirect('profile.php');
    } catch (Exception $e) {
        setFlash('warning', 'Error updating profile: ' . $e->getMessage());
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        setFlash('warning', 'Invalid request. Please try again.');
        redirect('profile.php');
    }

    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if ($newPassword !== $confirmPassword) {
        setFlash('warning', 'New passwords do not match.');
        redirect('profile.php');
    }

    try {
        $pdo = $auth->getPdo();
        
        // Verify current password
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $userData = $stmt->fetch();

        if (!password_verify($currentPassword, $userData['password_hash'])) {
            setFlash('warning', 'Current password is incorrect.');
            redirect('profile.php');
        }

        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password_hash = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$hashedPassword, $user['id']]);
        setFlash('success', 'Password changed successfully.');
        redirect('profile.php');
    } catch (Exception $e) {
        setFlash('warning', 'Error changing password: ' . $e->getMessage());
    }
}

// Get user statistics
try {
    $pdo = $auth->getPdo();
    
    // Total transactions
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_transactions,
            SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END) as total_deposits,
            SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END) as total_withdrawals
        FROM transactions 
        WHERE user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Most used payment method
    $stmt = $pdo->prepare("
        SELECT payment_method, COUNT(*) as count
        FROM transactions
        WHERE user_id = ?
        GROUP BY payment_method
        ORDER BY count DESC
        LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $preferredPayment = $stmt->fetch(PDO::FETCH_ASSOC);

    // Account age
    $stmt = $pdo->prepare("
        SELECT DATEDIFF(NOW(), created_at) as days
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$user['id']]);
    $accountAge = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    setFlash('warning', 'Error fetching statistics: ' . $e->getMessage());
    $stats = $preferredPayment = $accountAge = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Saving Ant</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/toast.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --primary: #0B5FFF;
            --dark: #06326B;
            --bg: #F8FAFF;
            --success: #0d8050;
            --warning: #bf8c0c;
            --danger: #db3737;
            --card-radius: 16px;
            --transition: all 0.3s ease;
            --section-padding: 32px;
            --card-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
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
        .profile-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 32px;
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--section-padding);
        }

        .profile-sidebar {
            position: sticky;
            top: 92px;
            height: fit-content;
        }

        .profile-main {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(11,95,255,0.1);
        }

        .section-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-toggle {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 14px;
            padding: 8px;
            border-radius: 6px;
            transition: var(--transition);
        }

        .section-toggle:hover {
            background: rgba(11,95,255,0.05);
        }

        .section-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .section-content.visible {
            max-height: 1000px;
        }
        .card {
            background: #fff;
            border-radius: var(--card-radius);
            padding: 24px;
            box-shadow: var(--card-shadow);
            margin-bottom: 24px;
            transition: var(--transition);
        }
        .card:hover {
            box-shadow: 0 8px 24px rgba(11,95,255,0.1);
            transform: translateY(-2px);
        }
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 8px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e6eefb;
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
            background: #fbfcff;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(11,95,255,0.1);
        }
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }
        .btn-primary {
            background: var(--primary);
            color: #fff;
        }
        .btn-primary:hover {
            background: var(--dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(11,95,255,0.2);
        }
        .stat-label {
            font-size: 14px;
            color: #6b7a93;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .stat-label i {
            font-size: 18px;
            color: var(--primary);
        }
        .stat-value {
            font-size: 28px;
            font-weight: 600;
            color: var(--dark);
            transition: var(--transition);
        }
        .stat-value.positive {
            color: var(--success);
        }
        .stat-value.negative {
            color: var(--danger);
        }
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title i {
            font-size: 24px;
            color: var(--primary);
        }
        .divider {
            height: 2px;
            background: linear-gradient(to right, var(--primary), transparent);
            margin: 32px 0;
            border-radius: 2px;
        }
        .avatar-section {
            text-align: center;
            margin-bottom: 32px;
            padding: 24px;
            border-radius: 20px;
            background: linear-gradient(145deg, #fff, #f8faff);
        }
        .avatar {
            width: 140px;
            height: 140px;
            border-radius: 70px;
            background: linear-gradient(145deg, var(--primary), var(--dark));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 56px;
            font-weight: 600;
            margin: 0 auto 20px;
            box-shadow: 0 8px 24px rgba(11,95,255,0.2);
            transition: var(--transition);
        }
        .avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 12px 32px rgba(11,95,255,0.3);
        }
        .roles {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .role-badge {
            background: var(--bg);
            color: var(--primary);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            transition: var(--transition);
            border: 1px solid rgba(11,95,255,0.2);
        }
        .role-badge:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        .main-content {
            margin-left: 250px;
            padding: 24px;
            min-height: 100vh;
            background: var(--bg);
            transition: var(--transition);
        }

        /* Toast Enhancements */
        .toast {
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(11,95,255,0.15);
        }

        /* Mobile Responsiveness */
        .quick-stats {
            margin-top: 24px;
            display: grid;
            gap: 16px;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: var(--bg);
            border-radius: 12px;
            transition: var(--transition);
        }

        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
            background: white;
        }

        .stat-item i {
            font-size: 24px;
            color: var(--primary);
            padding: 8px;
            background: var(--bg);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(11,95,255,0.1);
            transition: var(--transition);
        }

        .stat-item:hover i {
            background: var(--primary);
            color: white;
            transform: scale(1.1);
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6b7a93;
            margin-bottom: 4px;
            font-size: 13px;
            transition: var(--transition);
        }

        .requirement.met {
            color: var(--success);
        }

        .requirement i {
            font-size: 16px;
        }

        .requirement.met i {
            color: var(--success);
        }

        .form-feedback {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px;
            border-radius: 8px;
            margin-top: 16px;
            font-size: 14px;
            animation: slideIn 0.3s ease-out;
        }

        .form-feedback.success {
            background: rgba(13,128,80,0.1);
            color: var(--success);
        }

        .form-feedback.error {
            background: rgba(219,55,55,0.1);
            color: var(--danger);
        }

        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-info {
            flex: 1;
        }

        .stat-label {
            font-size: 13px;
            color: #6b7a93;
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
        }

        .avatar-section h2 {
            font-size: 20px;
            margin: 12px 0 8px;
            color: var(--dark);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-full {
            grid-column: 1 / -1;
        }

        .password-requirements {
            font-size: 12px;
            color: #6b7a93;
            margin-top: 4px;
        }

        .password-requirements i {
            font-size: 14px;
            vertical-align: middle;
            margin-right: 4px;
        }

        .form-footer {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid rgba(11,95,255,0.1);
        }

        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
                padding: 12px;
            }
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
            .container { 
                padding: 76px 12px 20px; 
            }
            .card {
                padding: 20px;
                margin-bottom: 16px;
            }
            .avatar {
                width: 100px;
                height: 100px;
                font-size: 40px;
            }
            .form-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            .stat-value {
                font-size: 14px;
            }
            .section-header h2 {
                font-size: 18px;
            }
            .btn {
                width: 100%;
                margin-bottom: 8px;
            }
            .form-control {
                padding: 10px 14px;
            }
            .stat-card {
                padding: 16px;
            }
            .divider {
                margin: 24px 0;
            }
            .roles {
                gap: 6px;
            }
            .role-badge {
                padding: 4px 10px;
                font-size: 12px;
            }
            .profile-sidebar {
                position: static;
            }
            .section-toggle {
                padding: 4px;
            }
            .quick-stats {
                grid-template-columns: 1fr;
            }
        }

        /* Extra Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stat-card {
            animation: fadeIn 0.3s ease-out forwards;
        }
        
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        .stat-card:nth-child(5) { animation-delay: 0.5s; }
        .stat-card:nth-child(6) { animation-delay: 0.6s; }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/inc/sidebar.php'; ?>

    <main class="main-content">
        <div id="toastContainer" class="toast-container"></div>
        <?php if ($flash = getFlash()): ?>
            <script>
                window.addEventListener('DOMContentLoaded', () => {
                    showToast('<?= $flash['type'] ?>', '<?= htmlspecialchars(addslashes($flash['message'])) ?>');
                });
            </script>
        <?php endif; ?>

        <div class="profile-grid">
            <!-- Profile Sidebar -->
            <div class="profile-sidebar">
                <div class="card">
                    <div class="avatar-section">
                        <div class="avatar">
                            <?= strtoupper(substr($user['full_name'] ?? '', 0, 1)) ?>
                        </div>
                        <h2><?= htmlspecialchars($user['full_name'] ?? '') ?></h2>
                        <div class="roles">
                            <?php if (isset($user['roles']) && is_array($user['roles'])): ?>
                                <?php foreach ($user['roles'] as $role): ?>
                                    <span class="role-badge">
                                        <i class="material-icons"><?= $role === 'admin' ? 'admin_panel_settings' : ($role === 'manager' ? 'manage_accounts' : 'person') ?></i>
                                        <?= ucfirst($role) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Account Statistics Summary -->
                    <div class="quick-stats">
                        <div class="stat-item">
                            <i class="material-icons">receipt_long</i>
                            <div class="stat-info">
                                <div class="stat-label">Transactions</div>
                                <div class="stat-value"><?= number_format($stats['total_transactions'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="stat-item">
                            <i class="material-icons">account_balance_wallet</i>
                            <div class="stat-info">
                                <div class="stat-label">Balance</div>
                                <div class="stat-value">RWF <?= number_format(($stats['total_deposits'] ?? 0) - ($stats['total_withdrawals'] ?? 0)) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="profile-main">
                <!-- Personal Information Section -->
                <div class="card">
                    <div class="section-header">
                        <h2>
                            <i class="material-icons">person_outline</i>
                            Personal Information
                        </h2>
                        <button type="button" class="section-toggle" onclick="toggleSection('personal-info')">
                            <i class="material-icons">expand_more</i>
                            <span>Show</span>
                        </button>
                    </div>
                    
                    <div id="personal-info" class="section-content">
                        <form method="POST" class="profile-form">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="name">
                                        <i class="material-icons">badge</i>
                                        Full Name
                                    </label>
                                    <input type="text" id="name" name="name" class="form-control" 
                                           value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="email">
                                        <i class="material-icons">email</i>
                                        Email Address
                                    </label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                </div>

                                <div class="form-group form-full">
                                    <label for="phone">
                                        <i class="material-icons">phone</i>
                                        Phone Number
                                    </label>
                                    <input type="tel" id="phone" name="phone" class="form-control" 
                                           value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>"
                                           placeholder="+250 XXX XXX XXX">
                                </div>

                                <div class="form-footer form-full">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="material-icons">save</i>
                                        Update Profile
                                    </button>
                                </div>
                            </div>
                        </form>

                <!-- Password Change Section -->
                <div class="card">
                    <div class="section-header">
                        <h2>
                            <i class="material-icons">lock</i>
                            Change Password
                        </h2>
                        <button type="button" class="section-toggle" onclick="toggleSection('password-section')">
                            <i class="material-icons">expand_more</i>
                            <span>Show</span>
                        </button>
                    </div>
                    
                    <div id="password-section" class="section-content">
                        <form method="POST" class="password-form">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            
                            <div class="form-grid">
                                <div class="form-group form-full">
                                    <label for="current_password">
                                        <i class="material-icons">key</i>
                                        Current Password
                                    </label>
                                    <input type="password" id="current_password" name="current_password" 
                                           class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label for="new_password">
                                        <i class="material-icons">vpn_key</i>
                                        New Password
                                    </label>
                                    <input type="password" id="new_password" name="new_password" 
                                           class="form-control" required minlength="8">
                                    <div id="password-requirements" class="password-requirements"></div>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password">
                                        <i class="material-icons">done_all</i>
                                        Confirm New Password
                                    </label>
                                    <input type="password" id="confirm_password" name="confirm_password" 
                                           class="form-control" required minlength="8">
                                </div>

                                <div class="form-footer form-full">
                                    <button type="submit" name="change_password" class="btn btn-primary">
                                        <i class="material-icons">lock_reset</i>
                                        Change Password
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        // Toast Notification System
        function showToast(type, message) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const icon = type === 'success' ? 'check_circle' : 
                        type === 'warning' ? 'warning' : 
                        'error';
            
            toast.innerHTML = `
                <span class="material-icons toast-icon">${icon}</span>
                <div class="toast-message">${message}</div>
                <span class="material-icons toast-close" onclick="this.parentElement.remove()">close</span>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease-out forwards';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        // Section Toggle Functionality
        function toggleSection(sectionId) {
            const section = document.getElementById(sectionId);
            const button = section.previousElementSibling.querySelector('.section-toggle');
            const icon = button.querySelector('.material-icons');
            const text = button.querySelector('span');

            section.classList.toggle('visible');
            
            if (section.classList.contains('visible')) {
                icon.textContent = 'expand_less';
                text.textContent = 'Hide';
                section.style.maxHeight = section.scrollHeight + 'px';
            } else {
                icon.textContent = 'expand_more';
                text.textContent = 'Show';
                section.style.maxHeight = '0';
            }
        }

        // Initialize sections
        document.addEventListener('DOMContentLoaded', function() {
            // Show personal info section by default
            toggleSection('personal-info');

            // Password confirmation validation
            document.querySelector('.password-form').addEventListener('submit', function(e) {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;

                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    showToast('warning', 'New passwords do not match.');
                }
            });

            // Add password requirements validation
            const newPasswordInput = document.getElementById('new_password');
            const requirementsList = document.getElementById('password-requirements');

            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                const requirements = [
                    { regex: /.{8,}/, text: 'At least 8 characters' },
                    { regex: /[A-Z]/, text: 'One uppercase letter' },
                    { regex: /[a-z]/, text: 'One lowercase letter' },
                    { regex: /[0-9]/, text: 'One number' },
                    { regex: /[^A-Za-z0-9]/, text: 'One special character' }
                ];

                requirementsList.innerHTML = requirements.map(req => `
                    <div class="requirement ${req.regex.test(password) ? 'met' : ''}">
                        <i class="material-icons">${req.regex.test(password) ? 'check_circle' : 'radio_button_unchecked'}</i>
                        ${req.text}
                    </div>
                `).join('');
            });
        });

        // Show form feedback
        function showFormFeedback(type, message) {
            const feedback = document.createElement('div');
            feedback.className = `form-feedback ${type}`;
            feedback.innerHTML = `
                <i class="material-icons">${type === 'success' ? 'check_circle' : 'error'}</i>
                <span>${message}</span>
            `;
            
            const activeForm = document.activeElement.closest('form');
            if (activeForm) {
                const existingFeedback = activeForm.querySelector('.form-feedback');
                if (existingFeedback) {
                    existingFeedback.remove();
                }
                activeForm.appendChild(feedback);
                
                setTimeout(() => feedback.remove(), 3000);
            }
        }
    </script>
</body>
</html>