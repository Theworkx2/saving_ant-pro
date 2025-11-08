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
        width: 280px;
        background: #f8f9fa;
        box-shadow: 1px 0 20px rgba(0,0,0,0.08);
        z-index: 1000;
        transition: all 0.3s ease;
        border-radius: 0 24px 24px 0;
    }

    .sidebar-header {
        padding: 24px;
        background: linear-gradient(135deg, #0B5FFF 0%, #0047CC 100%);
        border-radius: 0 24px 0 0;
        margin-bottom: 16px;
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
    }

    .logo {
        font-weight: 600;
        font-size: 24px;
        color: white;
    }

    .nav-menu {
        padding: 8px 0;
    }

    .nav-item {
        display: flex;
        align-items: center;
        padding: 14px 24px;
        text-decoration: none;
        color: #64748B;
        font-size: 15px;
        font-weight: 500;
        transition: all 0.3s ease;
        margin: 4px 16px;
        border-radius: 12px;
        position: relative;
        overflow: hidden;
    }

    .nav-item:hover {
        background: rgba(11,95,255,0.08);
        color: var(--primary);
    }

    .nav-item.active {
        background: rgba(11,95,255,0.1);
        color: var(--primary);
        font-weight: 600;
    }

    .nav-item.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--primary);
        border-radius: 0 4px 4px 0;
    }

    .nav-item i {
        margin-right: 12px;
        font-size: 20px;
        opacity: 0.9;
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

    /* Hamburger Menu */
    .hamburger-menu {
        display: none;
        position: fixed;
        top: 16px;
        left: 16px;
        z-index: 1001;
        background: white;
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 8px;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }

    .hamburger-menu:hover {
        background: var(--primary-light);
    }

    .hamburger-menu i {
        color: var(--primary);
        font-size: 24px;
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        z-index: 999;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .sidebar-overlay.active {
        opacity: 1;
    }

    /* Main content adjustment */
    .main-content {
        margin-left: 280px;
        padding: 24px;
        min-height: 100vh;
        background: var(--bg);
        transition: margin-left 0.3s ease;
    }

    @media (max-width: 768px) {
        .hamburger-menu {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar {
            transform: translateX(-100%);
            box-shadow: none;
        }
        
        .sidebar.active {
            transform: translateX(0);
            box-shadow: 2px 0 20px rgba(0,0,0,0.1);
        }

        .sidebar-overlay {
            display: block;
        }

        .main-content {
            margin-left: 0;
            padding-top: 72px;
        }
    }
</style>

<button class="hamburger-menu" id="hamburgerMenu">
    <i class="material-icons">menu</i>
</button>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const hamburgerMenu = document.getElementById('hamburgerMenu');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        }

        // Toggle sidebar when hamburger is clicked
        hamburgerMenu.addEventListener('click', toggleSidebar);

        // Close sidebar when clicking the overlay
        sidebarOverlay.addEventListener('click', toggleSidebar);

        // Close sidebar when clicking a nav item (on mobile)
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleSidebar();
                }
            });
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
</script>