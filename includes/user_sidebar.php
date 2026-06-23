<?php
// Active page detection
$currentFile = basename($_SERVER['PHP_SELF']);
if ($currentFile == 'dashboard.php') $activePage = 'dashboard';
elseif ($currentFile == 'projects.php') $activePage = 'projects';
elseif ($currentFile == 'my_tasks.php') $activePage = 'my_tasks';
elseif ($currentFile == 'profile.php') $activePage = 'profile';
else $activePage = '';
?>

<link rel="stylesheet" href="../fontawesome/css/all.min.css">

<style>
    #sidebar-overlay {
        position: fixed; inset: 0;
        background-color: rgba(15,23,42,0.6);
        backdrop-filter: blur(4px);
        z-index: 45; display: none; opacity: 0;
        transition: opacity 0.3s ease;
    }

    #sidebar {
        position: fixed; left: 0; top: 0;
        height: 100%; width: 260px;
        background-color: #ffffff;
        border-right: 1px solid #f1f5f9;
        z-index: 50; display: flex; flex-direction: column;
        transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
    }

    .sidebar-header { padding: 24px; border-bottom: 1px solid #f8fafc; display: flex; align-items: center; justify-content: space-between; }
    .logo-container { display: flex; align-items: center; gap: 12px; }
    .logo-accent { height: 24px; width: 4px; background-color: #2563eb; border-radius: 99px; }
    .logo-text h1 { font-size: 18px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; margin: 0; }
    .logo-text h1 span { color: #2563eb; }
    .logo-text p { font-size: 8px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px; margin-top: 2px; }

    .close-sidebar { display: none; border: none; background: none; color: #94a3b8; cursor: pointer; padding: 8px; border-radius: 8px; transition: 0.2s; }
    .close-sidebar:hover { background-color: #f1f5f9; color: #0f172a; }

    .sidebar-nav { padding: 16px; flex-grow: 1; overflow-y: auto; }
    .nav-section-label { padding: 0 16px; font-size: 9px; font-weight: 800; color: #cbd5e1; text-transform: uppercase; letter-spacing: 2px; margin: 24px 0 12px 0; }
    .nav-section-label:first-child { margin-top: 0; }
    .nav-list { list-style: none; padding: 0; margin: 0; }
    .nav-item { margin-bottom: 4px; }

    .nav-link { display: flex; align-items: center; padding: 10px 16px; border-radius: 12px; text-decoration: none; transition: all 0.2s; color: #475569; }
    .nav-link i { margin-right: 12px; font-size: 16px; width: 20px; text-align: center; display: inline-block; }
    .nav-link span { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
    .nav-link:hover { background-color: #eff6ff; color: #2563eb; }
    .nav-link.active { background-color: #2563eb; color: #ffffff; box-shadow: 0 10px 15px -3px rgba(37,99,235,0.2); }
    .nav-link.active i { color: #ffffff; }

    .sidebar-footer { padding: 16px; border-top: 1px solid #f8fafc; }
    .user-card { background-color: #f8fafc; border: 1px solid #f1f5f9; border-radius: 16px; padding: 12px; display: flex; align-items: center; justify-content: space-between; }
    .user-info-group { display: flex; align-items: center; gap: 12px; }
    .user-initials { height: 32px; width: 32px; background-color: #2563eb; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px; font-weight: 800; }
    .user-details { line-height: 1.2; }
    .user-details .name { font-size: 10px; font-weight: 800; color: #0f172a; text-transform: uppercase; max-width: 80px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .user-status { font-size: 8px; font-weight: 800; color: #3b82f6; text-transform: uppercase; display: flex; align-items: center; gap: 4px; }
    .status-pulse { height: 4px; width: 4px; background-color: #10b981; border-radius: 50%; animation: pulse 2s infinite; }

    @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }

    .logout-btn { height: 28px; width: 28px; display: flex; align-items: center; justify-content: center; background-color: #ffffff; border: 1px solid #f1f5f9; border-radius: 8px; color: #94a3b8; text-decoration: none; transition: 0.2s; }
    .logout-btn:hover { color: #ef4444; border-color: #fee2e2; }

    @media (max-width: 1024px) {
        #sidebar { transform: translateX(-100%); }
        .close-sidebar { display: flex; }
    }
</style>

<div id="sidebar-overlay" onclick="toggleSidebar()"></div>

<aside id="sidebar">
    <div class="sidebar-header">
        <div class="logo-container">
            <div class="logo-accent"></div>
            <div class="logo-text">
                <h1>TMS <span>Pro</span></h1>
                <p>Command Center</p>
            </div>
        </div>
        <button onclick="toggleSidebar()" class="close-sidebar">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <p class="nav-section-label">Main Menu</p>
        <ul class="nav-list">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?= ($activePage == 'dashboard') ? 'active' : ''; ?>">
                    <i class="fas fa-th-large"></i>
                    <span>Analytics</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="projects.php" class="nav-link <?= ($activePage == 'projects') ? 'active' : ''; ?>">
                    <i class="fas fa-project-diagram"></i>
                    <span>My Projects</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="my_tasks.php" class="nav-link <?= ($activePage == 'my_tasks') ? 'active' : ''; ?>">
                    <i class="fas fa-shield-alt"></i>
                    <span>Task Force</span>
                </a>
            </li>
        </ul>

        <p class="nav-section-label">Settings</p>
        <ul class="nav-list">
            <li class="nav-item">
                <a href="profile.php" class="nav-link <?= ($activePage == 'profile') ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle"></i>
                    <span>Profile Control</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-info-group">
                <div class="user-initials">
                    <?= strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="user-details">
                    <div class="name"><?= htmlspecialchars($user['username'] ?? 'User'); ?></div>
                    <span class="user-status">
                        <span class="status-pulse"></span>
                        Online
                    </span>
                </div>
            </div>
            <a href="../auth/logout.php" class="logout-btn" title="Exit System">
                <i class="fas fa-sign-out-alt" style="font-size: 14px;"></i>
            </a>
        </div>
    </div>
</aside>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const style = window.getComputedStyle(sidebar);
        const matrix = new WebKitCSSMatrix(style.transform);
        const isHidden = matrix.m41 < 0;

        if (isHidden) {
            sidebar.style.transform = 'translateX(0)';
            overlay.style.display = 'block';
            setTimeout(() => overlay.style.opacity = '1', 10);
        } else {
            sidebar.style.transform = 'translateX(-100%)';
            overlay.style.opacity = '0';
            setTimeout(() => overlay.style.display = 'none', 300);
        }
    }
</script>