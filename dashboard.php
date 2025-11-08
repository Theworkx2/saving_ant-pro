<?php
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/get_transaction_stats.php';

// Require login for dashboard
requireAuth();

// Get current user data
$user = $auth->getUser();

// Get transaction statistics
$stats = getTransactionStats($user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Saving Ant</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --primary: #0B5FFF;
            --primary-light: #EAF3FF;
            --dark: #06326B;
            --bg: #F8F9FA;
            --success: #10B981;
            --warning: #F59E0B;
            --purple: #8B5CF6;
            --card-radius: 16px;
            --text-secondary: #64748B;
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
        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 24px;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        .card {
            background: #fff;
            border-radius: var(--card-radius);
            padding: 24px;
            box-shadow: 0 4px 12px rgba(11,95,255,0.05);
        }
        .card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .card h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-admin { background: rgba(11,95,255,0.1); color: var(--primary); }
        .badge-manager { background: rgba(13,128,80,0.1); color: var(--success); }
        .badge-warning { background: rgba(191,140,12,0.1); color: var(--warning); }
        .stat {
            font-size: 28px;
            font-weight: 600;
            color: var(--dark);
            margin: 8px 0;
        }
        .stat-label {
            font-size: 13px;
            color: #6b7a93;
        }
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        .alert-success { background: rgba(13,128,80,0.1); color: var(--success); }
        .alert-warning { background: rgba(191,140,12,0.1); color: var(--warning); }

        .transactions-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .transaction-item {
            padding: 12px 16px;
            border-radius: 12px;
            transition: all 0.2s ease;
        }

        .transaction-item:hover {
            background: var(--bg);
        }

        .transaction-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .transaction-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            font-size: 16px;
        }

        .transaction-title {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 4px;
        }

        .transaction-date {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .quick-actions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: var(--dark);
        }

        .btn-outline {
            background: none;
            border: 1px solid #e6eefb;
            color: var(--text-secondary);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn i {
            font-size: 20px;
        }

        .stat-details {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e6eefb;
            display: flex;
            justify-content: space-between;
            gap: 16px;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .stat-item .amount {
            font-weight: 500;
            font-size: 14px;
        }

        .stat-item .amount.success {
            color: var(--success);
        }

        .stat-item .amount.danger {
            color: #EF4444;
        }

        .stat-item .label {
            font-size: 12px;
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
            }
            .welcome-card {
                padding: 24px;
            }
            .welcome-card h1 {
                font-size: 24px;
            }
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            .quick-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/inc/sidebar.php'; ?>

    <main class="main-content">
        <?php if ($flash = getFlash()): ?>
            <div class="alert alert-<?= $flash['type'] ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <div class="welcome-card">
            <h1>Welcome Back, <?= explode(' ', $user['full_name'])[0] ?>!</h1>
            <p>Manage your savings efficiently with our new silencies and of PHP Saving App.</p>
        </div>

        <div class="dashboard-grid">
            <!-- Balance Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon blue">
                        <i class="material-icons">account_balance_wallet</i>
                    </div>
                    <div>
                        <div class="stat">RWF <?= number_format($stats['total_balance'], 0) ?></div>
                        <div class="stat-label">Total Savings</div>
                    </div>
                </div>
                <div class="stat-details">
                    <div class="stat-item">
                        <span class="amount success">+RWF <?= number_format($stats['today']['deposits'], 0) ?></span>
                        <span class="label">Today's Deposits</span>
                    </div>
                    <div class="stat-item">
                        <span class="amount danger">-RWF <?= number_format($stats['today']['withdrawals'], 0) ?></span>
                        <span class="label">Today's Withdrawals</span>
                    </div>
                </div>
            </div>

            <?php if ($auth->hasRole('admin')): ?>
            <!-- Admin-only card -->
            <?php
            $stmt = $auth->getPdo()->query('SELECT COUNT(*) FROM users WHERE is_active = 1');
            $activeUsers = $stmt->fetchColumn();
            ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-icon purple">
                        <i class="material-icons">groups</i>
                    </div>
                    <div>
                        <div class="stat"><?= number_format($activeUsers) ?></div>
                        <div class="stat-label">Active Users</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($auth->hasRole('manager')): ?>
            <!-- Manager card -->
            <?php
            $stmt = $auth->getPdo()->prepare('SELECT COUNT(*) FROM users u 
                INNER JOIN user_roles ur ON u.id = ur.user_id 
                INNER JOIN roles r ON ur.role_id = r.id 
                WHERE r.name = "user" AND u.is_active = 1');
            $stmt->execute();
            $activeUsers = $stmt->fetchColumn();
            ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-icon green">
                        <i class="material-icons">supervisor_account</i>
                    </div>
                    <div>
                        <div class="stat"><?= number_format($activeUsers) ?></div>
                        <div class="stat-label">Regular Users</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Transactions card -->
            <?php
            $startOfMonth = date('Y-m-01 00:00:00');
            $stmt = $auth->getPdo()->prepare('SELECT COUNT(*) FROM transactions WHERE user_id = ? AND created_at >= ?');
            $stmt->execute([$user['id'], $startOfMonth]);
            $transactionsThisMonth = $stmt->fetchColumn();
            ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-icon green">
                        <i class="material-icons">sync_alt</i>
                    </div>
                    <div>
                        <div class="stat"><?= number_format($stats['today']['count']) ?></div>
                        <div class="stat-label">Today's Transactions</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card">
            <div class="card-header">
                <h3>Recent Transactions</h3>
            </div>
            <?php 
            $transactions = $stats['recent_transactions'];
            ?>
            <div class="transactions-list">
                <?php foreach ($transactions as $t): ?>
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <div class="transaction-avatar">
                                <?= strtoupper(substr($t['full_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="transaction-title">
                                    <?= htmlspecialchars($t['type']) ?> - RWF <?= number_format($t['amount'], 0) ?>
                                </div>
                                <div class="transaction-date">
                                    <?= getTimeAgo(strtotime($t['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php if ($auth->hasRole('admin')): ?>
        <div class="card">
            <div class="card-header">
                <h3>Quick Actions</h3>
            </div>
            <div class="quick-actions">
                <a href="users.php" class="btn btn-primary">
                    <i class="material-icons">group_add</i>
                    Manage Users
                </a>
                <a href="reports.php" class="btn btn-outline">
                    <i class="material-icons">analytics</i>
                    View Reports
                </a>
            </div>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>