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
    <style>
        :root {
            --primary: #0B5FFF;
            --dark: #06326B;
            --bg: #EAF3FF;
            --success: #0d8050;
            --warning: #bf8c0c;
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
        .user-menu {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .user-info {
            text-align: right;
            line-height: 1.3;
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
        .btn-logout {
            padding: 6px 12px;
            background: none;
            border: 1px solid #e6eefb;
            border-radius: 6px;
            color: #6b7a93;
            font-size: 13px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-logout:hover {
            background: #fbfcff;
            border-color: #d1e2f9;
            color: var(--dark);
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

        @media (max-width: 768px) {
            .navbar { padding: 12px 16px; }
            .nav-links { display: none; }
            .container { padding: 84px 16px 24px; }
            .page-title { font-size: 20px; }
            .dashboard-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <a href="dashboard.php" class="brand">
                <span class="logo">Saving Ant</span>
            </a>
            
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <?php if ($auth->hasRole('admin')): ?>
                    <a href="users.php">User Management</a>
                <?php endif; ?>
                <?php if ($auth->hasRole('admin') || $auth->hasRole('manager')): ?>
                    <a href="reports.php">Reports</a>
                <?php endif; ?>
                <a href="transactions.php">Transactions</a>
            </div>

            <div class="user-menu">
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="user-role"><?= htmlspecialchars(implode(', ', $user['roles'])) ?></div>
                </div>
                <form action="logout.php" method="post" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <button type="submit" class="btn-logout">Logout</button>
                </form>
            </div>
        </div>
    </nav>

    <main class="container">
        <?php if ($flash = getFlash()): ?>
            <div class="alert alert-<?= $flash['type'] ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <h1 class="page-title">Dashboard</h1>

        <div class="dashboard-grid">
            <!-- Common card for all users -->
            <div class="card">
                <div class="card-header">
                    <h3>Your Balance</h3>
                </div>
                <div class="stat">$1,234.56</div>
                <div class="stat-label">Current balance</div>
            </div>

            <?php if ($auth->hasRole('admin')): ?>
            <!-- Admin-only card -->
            <div class="card">
                <div class="card-header">
                    <h3>System Status</h3>
                    <span class="badge badge-admin">Admin</span>
                </div>
                <div class="stat">127</div>
                <div class="stat-label">Active users</div>
            </div>
            <?php endif; ?>

            <?php if ($auth->hasRole('manager')): ?>
            <!-- Manager card -->
            <div class="card">
                <div class="card-header">
                    <h3>Department Overview</h3>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <div class="stat">45</div>
                <div class="stat-label">Team members</div>
            </div>
            <?php endif; ?>

            <!-- Regular user card -->
            <div class="card">
                <div class="card-header">
                    <h3>Recent Activity</h3>
                </div>
                <div class="stat">12</div>
                <div class="stat-label">Transactions this month</div>
            </div>
        </div>

        <?php if ($auth->hasRole('admin')): ?>
        <div class="card">
            <div class="card-header">
                <h3>Quick Actions</h3>
            </div>
            <div style="display:flex;gap:12px;margin-top:12px">
                <a href="users.php" style="text-decoration:none">
                    <button class="btn-logout">Manage Users</button>
                </a>
                <a href="reports.php" style="text-decoration:none">
                    <button class="btn-logout">View Reports</button>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>