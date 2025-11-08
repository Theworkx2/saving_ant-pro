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
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        .card {
            background: #fff;
            border-radius: var(--card-radius);
            padding: 24px;
            box-shadow: 0 4px 12px rgba(11,95,255,0.05);
            margin-bottom: 24px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 8px;
        }
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e6eefb;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .btn-primary {
            background: var(--primary);
            color: #fff;
        }
        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(11,95,255,0.05);
            margin-bottom: 16px;
        }
        .stat-label {
            font-size: 14px;
            color: #6b7a93;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
        }
        .stat-value.positive {
            color: var(--success);
        }
        .stat-value.negative {
            color: var(--danger);
        }
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 16px;
        }
        .divider {
            height: 1px;
            background: #e6eefb;
            margin: 24px 0;
        }
        .avatar-section {
            text-align: center;
            margin-bottom: 24px;
        }
        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 60px;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 600;
            margin: 0 auto 16px;
        }
        .roles {
            display: flex;
            gap: 8px;
            margin-top: 8px;
            justify-content: center;
        }
        .role-badge {
            background: var(--bg);
            color: var(--primary);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            .container { 
                padding: 84px 16px 24px; 
            }
        }
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
            <div class="main-content">
                <div class="card">
                    <h2 class="section-title">Personal Information</h2>
                    <form method="POST" class="profile-form">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" class="form-control" 
                                   value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>">
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">
                            Update Profile
                        </button>
                    </form>

                    <div class="divider"></div>

                    <h2 class="section-title">Change Password</h2>
                    <form method="POST" class="password-form">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" 
                                   class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" 
                                   class="form-control" required minlength="8">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   class="form-control" required minlength="8">
                        </div>

                        <button type="submit" name="change_password" class="btn btn-primary">
                            Change Password
                        </button>
                    </form>
                </div>
            </div>

            <div class="sidebar">
                <div class="card">
                    <div class="avatar-section">
                        <div class="avatar">
                            <?= strtoupper(substr($user['full_name'] ?? '', 0, 1)) ?>
                        </div>
                        <h3><?= htmlspecialchars($user['full_name'] ?? '') ?></h3>
                        <div class="roles">
                            <?php if (isset($user['roles']) && is_array($user['roles'])): ?>
                                <?php foreach ($user['roles'] as $role): ?>
                                    <span class="role-badge"><?= ucfirst($role) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <h3 class="section-title">Account Statistics</h3>
                    
                    <div class="stat-card">
                        <div class="stat-label">Total Transactions</div>
                        <div class="stat-value">
                            <?= number_format($stats['total_transactions'] ?? 0) ?>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-label">Total Deposits</div>
                        <div class="stat-value positive">
                            RWF <?= number_format($stats['total_deposits'] ?? 0) ?>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-label">Total Withdrawals</div>
                        <div class="stat-value negative">
                            RWF <?= number_format($stats['total_withdrawals'] ?? 0) ?>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-label">Account Balance</div>
                        <div class="stat-value">
                            RWF <?= number_format(($stats['total_deposits'] ?? 0) - ($stats['total_withdrawals'] ?? 0)) ?>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-label">Preferred Payment Method</div>
                        <div class="stat-value" style="font-size: 18px;">
                            <?php if ($preferredPayment): ?>
                                <img src="images/<?= strtolower($preferredPayment['payment_method']) ?>.png" 
                                     alt="<?= htmlspecialchars($preferredPayment['payment_method']) ?>"
                                     style="height: 20px; vertical-align: middle; margin-right: 8px;">
                                <?= ucfirst($preferredPayment['payment_method']) ?>
                            <?php else: ?>
                                No transactions yet
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-label">Account Age</div>
                        <div class="stat-value" style="font-size: 18px;">
                            <?= number_format($accountAge['days'] ?? 0) ?> days
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
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease-out forwards';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        // Password confirmation validation
        document.querySelector('.password-form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                showToast('warning', 'New passwords do not match.');
            }
        });
    </script>
</body>
</html>