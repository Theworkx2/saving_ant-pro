<?php
require_once __DIR__ . '\inc\functions.php';

// Require login for dashboard
requireAuth();

// Get current user data
$user = $auth->getUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Saving Ant</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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

        <div class="welcome-section">
            <div class="welcome-content">
                <div class="welcome-header-wrap">
                    <img src="images/logo.png" alt="Saving Ant Logo" class="welcome-logo">
                    <div>
                        <h1 class="welcome-header">Welcome Back, <?= explode(' ', $user['full_name'])[0] ?>! 👋</h1>
                        <p class="welcome-text">Track your savings, manage transactions, and achieve your financial goals with Saving Ant.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <?php
            $pdo = $auth->getPdo();
            
            if ($auth->hasRole('admin')) {
                // For admin, show total balance of all users
                $stmt = $pdo->query("
                    SELECT 
                        COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END), 0) as total_balance,
                        COALESCE(SUM(CASE 
                            WHEN type = 'deposit' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN amount
                            WHEN type = 'withdrawal' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN -amount
                            ELSE 0
                        END), 0) as monthly_change
                    FROM transactions
                ");
                $result = $stmt->fetch();
                $balance = $result['total_balance'];
                $monthlyChange = $result['monthly_change'];
            } else {
                // For regular users, show their own balance
                $balance = getUserBalance($user['id']);
                
                // Calculate month-over-month change
                $stmt = $pdo->prepare("
                    SELECT 
                        COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END), 0) as balance_change
                    FROM transactions 
                    WHERE user_id = ? 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ");
                $stmt->execute([$user['id']]);
                $monthlyChange = $stmt->fetchColumn();
            }
            
            $changePercentage = $balance != 0 ? ($monthlyChange / $balance) * 100 : 0;
            ?>
            
            <!-- Balance Card -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="material-icons">account_balance_wallet</i>
                    </div>
                    <div>
                        <div class="stat-value">RWF <?= number_format($balance, 0) ?></div>
                        <div class="stat-label"><?= $auth->hasRole('admin') ? 'Total Platform Balance' : 'Your Balance' ?></div>
                        <?php if ($monthlyChange != 0): ?>
                        <div class="stat-trend <?= $monthlyChange > 0 ? 'trend-up' : 'trend-down' ?>">
                            <i class="material-icons"><?= $monthlyChange > 0 ? 'trending_up' : 'trending_down' ?></i>
                            <?php if ($auth->hasRole('admin')): ?>
                                RWF <?= number_format(abs($monthlyChange), 0) ?> <?= $monthlyChange > 0 ? 'increase' : 'decrease' ?> this month
                            <?php else: ?>
                                <?= number_format(abs($changePercentage), 1) ?>% this month
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php
            // Get transaction statistics
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_count,
                    SUM(CASE WHEN type = 'deposit' THEN 1 ELSE 0 END) as deposit_count,
                    SUM(CASE WHEN type = 'withdrawal' THEN 1 ELSE 0 END) as withdrawal_count,
                    AVG(CASE WHEN type = 'deposit' THEN amount END) as avg_deposit,
                    AVG(CASE WHEN type = 'withdrawal' THEN amount END) as avg_withdrawal
                FROM transactions 
                WHERE user_id = ? 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute([$user['id']]);
            $stats = $stmt->fetch();
            ?>

            <!-- Transaction Activity Card -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon success">
                        <i class="material-icons">sync_alt</i>
                    </div>
                    <div>
                        <div class="stat-value"><?= number_format($stats['total_count']) ?></div>
                        <div class="stat-label">Monthly Transactions</div>
                        <div class="stat-trend">
                            <span style="color: var(--success);">↑<?= number_format($stats['deposit_count']) ?> deposits</span>
                            <span style="color: var(--danger);">↓<?= number_format($stats['withdrawal_count']) ?> withdrawals</span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($auth->hasRole('admin')): ?>
            <!-- Admin Statistics -->
            <?php
            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
                    (
                        SELECT COUNT(*) 
                        FROM transactions 
                        WHERE created_at >= CURDATE()
                    ) as transactions_today,
                    (
                        SELECT COUNT(*) 
                        FROM transactions 
                        WHERE type = 'deposit'
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ) as deposits_30d,
                    (
                        SELECT COUNT(*) 
                        FROM transactions 
                        WHERE type = 'withdrawal'
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ) as withdrawals_30d
                FROM users
            ");
            $adminStats = $stmt->fetch();

            // Get total transactions for current month
            $stmt = $pdo->query("
                SELECT COUNT(*) as total
                FROM transactions 
                WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
                AND YEAR(created_at) = YEAR(CURRENT_DATE())
            ");
            $monthlyTransactions = $stmt->fetchColumn();
            ?>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon purple">
                        <i class="material-icons">groups</i>
                    </div>
                    <div>
                        <div class="stat-value"><?= number_format($adminStats['active_users']) ?></div>
                        <div class="stat-label">Active Users</div>
                        <div class="stat-trend">
                            <i class="material-icons" style="font-size: 16px;">today</i>
                            <?= number_format($adminStats['transactions_today']) ?> transactions today
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Transactions Card -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon success">
                        <i class="material-icons">sync_alt</i>
                    </div>
                    <div>
                        <div class="stat-value"><?= number_format($monthlyTransactions) ?></div>
                        <div class="stat-label">Monthly Transactions</div>
                        <div class="stat-trend">
                            <span style="color: var(--success); margin-right: 8px;">
                                <i class="material-icons" style="font-size: 16px; vertical-align: text-bottom;">arrow_upward</i>
                                <?= number_format($adminStats['deposits_30d']) ?> deposits
                            </span>
                            <span style="color: var(--danger);">
                                <i class="material-icons" style="font-size: 16px; vertical-align: text-bottom;">arrow_downward</i>
                                <?= number_format($adminStats['withdrawals_30d']) ?> withdrawals
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($auth->hasRole('manager')): ?>
            <!-- Manager Statistics -->
            <?php
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT u.id) as user_count,
                       COUNT(DISTINCT t.id) as transaction_count
                FROM users u 
                LEFT JOIN transactions t ON u.id = t.user_id AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                INNER JOIN user_roles ur ON u.id = ur.user_id 
                INNER JOIN roles r ON ur.role_id = r.id 
                WHERE r.name = 'user' AND u.is_active = 1
            ");
            $stmt->execute();
            $managerStats = $stmt->fetch();
            ?>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon warning">
                        <i class="material-icons">supervisor_account</i>
                    </div>
                    <div>
                        <div class="stat-value"><?= number_format($managerStats['user_count']) ?></div>
                        <div class="stat-label">Active Users</div>
                        <div class="stat-trend">
                            <?= number_format($managerStats['transaction_count']) ?> transactions this month
                        </div>
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
                        <div class="stat"><?= number_format($transactionsThisMonth) ?></div>
                        <div class="stat-label">Pending Transactions</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="transactions-section">
            <!-- Recent Transactions -->
            <div class="transaction-card">
                <div class="card-header">
                    <h3 class="card-title">Recent Transactions</h3>
                    <a href="transactions.php" class="btn btn-outline">View All</a>
                </div>
                <?php
                $stmt = $auth->getPdo()->prepare('
                    SELECT t.*, u.full_name, u.id as user_id 
                    FROM transactions t 
                    JOIN users u ON t.user_id = u.id 
                    WHERE t.user_id = ? 
                    ORDER BY t.created_at DESC LIMIT 5
                ');
                $stmt->execute([$user['id']]);
                $transactions = $stmt->fetchAll();
                ?>
                <div class="transaction-list">
                    <?php foreach ($transactions as $t): ?>
                        <div class="transaction-item">
                            <div class="transaction-icon <?= $t['type'] ?>">
                                <i class="material-icons">
                                    <?= $t['type'] === 'deposit' ? 'arrow_downward' : 'arrow_upward' ?>
                                </i>
                            </div>
                            <div class="transaction-details">
                                <div class="transaction-title">
                                    <?= ucfirst($t['type']) ?> via <?= htmlspecialchars($t['payment_method']) ?>
                                </div>
                                <div class="transaction-meta">
                                    <span><?= getTimeAgo(strtotime($t['created_at'])) ?></span>
                                    <span class="transaction-amount amount-<?= $t['type'] ?>">
                                        <?= $t['type'] === 'deposit' ? '+' : '-' ?>RWF <?= number_format($t['amount'], 0) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Activity Timeline -->
            <div class="transaction-card">
                <div class="card-header">
                    <h3 class="card-title">Activity Timeline</h3>
                </div>
                <div class="timeline">
                    <?php
                    // Get recent activities
                    $stmt = $auth->getPdo()->prepare("
                        SELECT 
                            'transaction' as type,
                            created_at as date,
                            CONCAT(type, ' of RWF ', amount) as description,
                            payment_method
                        FROM transactions 
                        WHERE user_id = ?
                        ORDER BY created_at DESC 
                        LIMIT 5
                    ");
                    $stmt->execute([$user['id']]);
                    $activities = $stmt->fetchAll();
                    
                    foreach ($activities as $activity): ?>
                        <div class="timeline-item">
                            <div class="timeline-date"><?= getTimeAgo(strtotime($activity['date'])) ?></div>
                            <div class="timeline-title"><?= ucfirst($activity['description']) ?></div>
                            <div class="timeline-description">via <?= $activity['payment_method'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions Section -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Quick Actions</h3>
            </div>
            <div class="quick-actions">
                <a href="transactions.php" class="action-card">
                    <div class="action-icon">
                        <i class="material-icons">receipt_long</i>
                    </div>
                    <h4 class="action-title">New Transaction</h4>
                    <p class="action-description">Make a deposit or withdrawal</p>
                </a>

                <?php if ($auth->hasRole('admin')): ?>
                <a href="users.php" class="action-card">
                    <div class="action-icon" style="background: rgba(139,92,246,0.1); color: var(--purple);">
                        <i class="material-icons">group_add</i>
                    </div>
                    <h4 class="action-title">Manage Users</h4>
                    <p class="action-description">Add or update user accounts</p>
                </a>

                <a href="reports.php" class="action-card">
                    <div class="action-icon" style="background: rgba(16,185,129,0.1); color: var(--success);">
                        <i class="material-icons">analytics</i>
                    </div>
                    <h4 class="action-title">View Reports</h4>
                    <p class="action-description">Analyze transactions and trends</p>
                </a>
                <?php endif; ?>

                <a href="profile.php" class="action-card">
                    <div class="action-icon" style="background: rgba(245,158,11,0.1); color: var(--warning);">
                        <i class="material-icons">account_circle</i>
                    </div>
                    <h4 class="action-title">Profile Settings</h4>
                    <p class="action-description">Update your account details</p>
                </a>
            </div>
        </div>

        <?php if ($auth->hasRole('admin')): ?>
        <!-- Admin Analytics Chart -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <h3 class="card-title">Transaction Analytics</h3>
                <select id="chartPeriod" class="btn btn-outline">
                    <option value="7">Last 7 days</option>
                    <option value="30" selected>Last 30 days</option>
                    <option value="90">Last 90 days</option>
                </select>
            </div>
            <div style="padding: 20px;">
                <canvas id="transactionChart" height="200"></canvas>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script>
        // Initialize tooltips
        const tooltipTriggers = document.querySelectorAll('[data-tooltip]');
        tooltipTriggers.forEach(trigger => {
            trigger.addEventListener('mouseenter', showTooltip);
            trigger.addEventListener('mouseleave', hideTooltip);
        });

        function showTooltip(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = e.target.dataset.tooltip;
            document.body.appendChild(tooltip);

            const rect = e.target.getBoundingClientRect();
            tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
            tooltip.style.left = rect.left + (rect.width - tooltip.offsetWidth) / 2 + 'px';
        }

        function hideTooltip() {
            const tooltip = document.querySelector('.tooltip');
            if (tooltip) {
                tooltip.remove();
            }
        }

        <?php if ($auth->hasRole('admin')): ?>
        // Transaction Chart
        const ctx = document.getElementById('transactionChart').getContext('2d');
        let transactionChart;

        async function updateChart(days) {
            try {
                const response = await fetch(`inc/get_transaction_stats.php?days=${days}`);
                const data = await response.json();

                if (transactionChart) {
                    transactionChart.destroy();
                }

                transactionChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Deposits',
                            data: data.deposits,
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16,185,129,0.1)',
                            tension: 0.4,
                            fill: true
                        }, {
                            label: 'Withdrawals',
                            data: data.withdrawals,
                            borderColor: '#EF4444',
                            backgroundColor: 'rgba(239,68,68,0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0,0,0,0.05)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Error loading chart data:', error);
            }
        }

        // Initialize chart with 30 days data
        updateChart(30);

        // Update chart when period changes
        document.getElementById('chartPeriod').addEventListener('change', function(e) {
            updateChart(e.target.value);
        });
        <?php endif; ?>

        // Add smooth scroll behavior to timeline
        const timeline = document.querySelector('.timeline');
        if (timeline) {
            timeline.addEventListener('wheel', (e) => {
                e.preventDefault();
                timeline.scrollTop += e.deltaY;
            });
        }

        // Add hover effect to transaction items
        const transactionItems = document.querySelectorAll('.transaction-item');
        transactionItems.forEach(item => {
            item.addEventListener('mouseenter', () => {
                item.style.transform = 'translateX(8px)';
            });
            item.addEventListener('mouseleave', () => {
                item.style.transform = 'translateX(0)';
            });
        });
    </script>
</body>
</html>