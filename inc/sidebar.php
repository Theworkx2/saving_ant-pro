<?php
if (!isset($auth)) {
    require_once __DIR__ . '/functions.php';
}

// Get current page name
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<style>
    .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        bottom: 0;
        width: 250px;
        background: #fff;
        box-shadow: 2px 0 5px rgba(11,95,255,0.05);
        z-index: 1000;
        transition: all 0.3s ease;
    }

    .sidebar-header {
        padding: 24px;
        border-bottom: 1px solid #e6eefb;
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
    }

    .logo {
        font-weight: 600;
        font-size: 22px;
        color: var(--dark);
    }

    .nav-menu {
        padding: 8px 0;
    }

    .nav-item {
        display: flex;
        align-items: center;
        padding: 12px 24px;
        text-decoration: none;
        color: #18314d;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s ease;
        margin: 4px 16px;
        border-radius: 8px;
    }

    .nav-item:hover {
        background: #EAF3FF;
        color: var(--primary);
    }

    .nav-item.active {
        background: #EAF3FF;
        color: var(--primary);
        font-weight: 600;
    }

    .nav-item i {
        margin-right: 12px;
        font-size: 20px;
    }

    .user-section {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 16px;
        border-top: 1px solid #e6eefb;
        background: #fff;
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

    .user-info:hover {
        background: #EAF3FF;
    }

    .user-info:focus {
        outline: 2px solid var(--primary);
        outline-offset: -2px;
    }

    .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
        font-size: 16px;
    }

    .user-details {
        flex: 1;
        line-height: 1.4;
    }

    .user-name {
        font-size: 16px;
        font-weight: 500;
        color: #0B2240;
    }

    .user-role {
        font-size: 14px;
        color: #6b7a93;
    }

    .logout-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        margin-top: 8px;
        padding: 8px;
        border: none;
        border-radius: 6px;
        background: none;
        color: #6b7a93;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .logout-btn:hover {
        background: #EAF3FF;
        color: var(--primary);
    }

    /* Main content adjustment */
    .main-content {
        margin-left: 250px;
        padding: 24px;
        min-height: 100vh;
        background: var(--bg);
    }

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar.active {
            transform: translateX(0);
        }

        .main-content {
            margin-left: 0;
        }
    }
</style>

<div class="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="brand">
            <span class="logo">Saving Ant</span>
        </a>
    </div>

    <nav class="nav-menu">
        <a href="dashboard.php" class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <i class="material-icons">dashboard</i>
            Dashboard
        </a>
        <a href="transactions.php" class="nav-item <?= $currentPage === 'transactions.php' ? 'active' : '' ?>">
            <i class="material-icons">sync_alt</i>
            Transactions
        </a>
        <?php if ($auth->hasRole('admin')): ?>
            <a href="users.php" class="nav-item <?= $currentPage === 'users.php' ? 'active' : '' ?>">
                <i class="material-icons">people</i>
                User Management
            </a>
        <?php endif; ?>
        <?php if ($auth->hasRole('admin') || $auth->hasRole('manager')): ?>
            <a href="reports.php" class="nav-item <?= $currentPage === 'reports.php' ? 'active' : '' ?>">
                <i class="material-icons">assessment</i>
                Reports
            </a>
        <?php endif; ?>
    </nav>

    <div class="user-section">
        <a href="profile.php" class="user-info">
            <div class="user-avatar">
                <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?= htmlspecialchars($user['full_name'] ?? 'User') ?></div>
                <div class="user-role">admin</div>
            </div>
        </a>
        <form action="logout.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <button type="submit" class="logout-btn">
                <i class="material-icons" style="font-size: 16px;">logout</i>
                Logout
            </button>
        </form>
    </div>
</div>