<?php
/**
 * SIDEBAR COMPONENT - Font Awesome 5 Edition
 */

// 1. Active page detection logic
$current_file = basename($_SERVER['PHP_SELF']);
$activePage = ($current_file == 'manage_users.php' || $current_file == 'edit_user.php') 
                ? 'manage_users' 
                : str_replace('.php', '', $current_file);

// 2. Navigation Mapping (Updated with Font Awesome Classes)
$nav_links = [
    'dashboard'    => ['text' => 'Analytics', 'icon' => 'fas fa-chart-network'], // Note: 'chart-network' requires Pro, falling back to 'chart-line' for Free
    'projects'     => ['text' => 'Projects', 'icon' => 'fas fa-project-diagram'],
    'all_tasks'    => ['text' => 'Task Force', 'icon' => 'fas fa-tasks'],
    'manage_users' => ['text' => 'Manage Users', 'icon' => 'fas fa-users-cog'],
    'Reports' => ['text' => 'reports', 'icon' => 'fas fa-chart-bar'],

];

// Fallback for icons if using FA Free 5.15.4
$nav_icons = [
    'dashboard'    => 'fas fa-th-large',
    'projects'     => 'fas fa-folder-open',
    'all_tasks'    => 'fas fa-stream',
    'manage_users' => 'fas fa-user-shield',
    'Reports' => 'fas fa-chart-bar',
];

$user_display = [
    'username' => $user['username'] ?? 'Admin',
    'initial'  => strtoupper(substr($user['username'] ?? 'A', 0, 1))
];
?>

<link rel="stylesheet" href="../fontawesome/css/all.min.css">

<style>
    :root {
        --sidebar-bg: #ffffff;
        --sidebar-border: #f1f5f9;
        --sidebar-text: #64748b;
        --sidebar-text-dark: #0f172a;
        --sidebar-hover: #f0f7ff;
        --sidebar-active: #2563eb;
        --sidebar-profile-bg: #f8fafc;
        --sidebar-overlay: rgba(15, 23, 42, 0.4);
        --logout-hover: #ef4444;
        --sidebar-width: 260px;
    }
    *{ box-sizing: border-box; padding: 0; margin: 0; }
    #sidebar {
        position: fixed;
        left: 0; top: 0; height: 100vh;
        width: var(--sidebar-width);
        background: var(--sidebar-bg);
        border-right: 1px solid var(--sidebar-border);
        z-index: 1000;
        display: flex;
        flex-direction: column;
        transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    #sidebar-overlay {
        position: fixed; inset: 0;
        background: var(--sidebar-overlay);
        backdrop-filter: blur(4px);
        z-index: 999;
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    /* --- Responsive Logic --- */
    @media (max-width: 1024px) {
        #sidebar { transform: translateX(-100%); }
        #sidebar.sidebar-open { transform: translateX(0); }
        #sidebar-overlay.overlay-visible { display: block; opacity: 1; }
    }

    @media (min-width: 1025px) {
        #sidebar { transform: translateX(0); }
        #sidebar-overlay { display: none !important; }
    }

    /* --- Header --- */
    .side-header {
        padding: 1.5rem;
        border-bottom: 1px solid #f8fafc;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .brand-group { display: flex; align-items: center; gap: 12px; }
    .brand-accent { height: 22px; width: 4px; background: var(--sidebar-active); border-radius: 4px; }
    .brand-text h1 { font-size: 1.1rem; font-weight: 800; color: var(--sidebar-text-dark); margin: 0; letter-spacing: -0.02em; }
    .brand-highlight { color: var(--sidebar-active); }

    /* --- Nav Links --- */
    .nav-container { padding: 1.2rem; flex: 1; overflow-y: auto; }
    .nav-section-title { 
        padding: 0 1rem; font-size: 10px; font-weight: 800; 
        color: #94a3b8; text-transform: uppercase; 
        letter-spacing: 0.15em; margin: 1.5rem 0 0.75rem; 
    }
    
    .nav-link {
        display: flex; align-items: center; padding: 0.9rem 1.2rem; border-radius: 14px;
        text-decoration: none; color: var(--sidebar-text); font-weight: 700; font-size: 13px;
        margin-bottom: 6px; transition: all 0.2s ease;
    }

    .nav-link:hover { background: var(--sidebar-hover); color: var(--sidebar-active); }
    
    .nav-link.active { 
        background: var(--sidebar-active); color: #fff; 
        box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.3); 
    }

    /* Icon Sizing for FA */
    .nav-link i { 
        width: 20px; 
        margin-right: 15px; 
        font-size: 16px; 
        text-align: center;
        transition: transform 0.3s;
    }
    .nav-link:hover i { transform: scale(1.1); }

    /* --- Footer Profile --- */
    .side-footer { padding: 1.2rem; border-top: 1px solid #f8fafc; }
    .profile-card {
        background: var(--sidebar-profile-bg); border: 1px solid var(--sidebar-border);
        padding: 12px; border-radius: 16px; display: flex; align-items: center; justify-content: space-between;
    }

    .avatar {
        width: 36px; height: 36px; border-radius: 10px;
        background: var(--sidebar-text-dark); color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: 12px; font-weight: 800;
    }

    .user-name-text { font-size: 11px; font-weight: 800; color: var(--sidebar-text-dark); text-transform: uppercase; margin: 0; }
    
    .logout-btn { 
        color: var(--sidebar-text); 
        padding: 8px;
        border-radius: 8px;
        transition: 0.2s; 
    }
    .logout-btn:hover { background: #fee2e2; color: var(--logout-hover); }

    /* Scrollbar */
    .nav-container::-webkit-scrollbar { width: 4px; }
    .nav-container::-webkit-scrollbar-thumb { background: var(--sidebar-border); border-radius: 10px; }
</style>

<div id="sidebar-overlay" onclick="toggleSidebar()"></div>

<aside id="sidebar">
    <div class="side-header">
        <div class="brand-group">
            <div class="brand-accent"></div>
            <div class="brand-text">
                <h1>TMS <span class="brand-highlight">Pro</span></h1>
            </div>
        </div>
    </div>

    <nav class="nav-container">
        <p class="nav-section-title">Operations</p>
        <?php foreach ($nav_links as $slug => $data): ?>
            <?php 
                // Determine icon based on slug
                $iconClass = $nav_icons[$slug] ?? 'fas fa-circle-notch';
            ?>
            <a href="<?= $slug ?>.php" class="nav-link <?= ($activePage == $slug) ? 'active' : '' ?>">
                <i class="<?= $iconClass ?>"></i>
                <span><?= $data['text'] ?></span>
            </a>
        <?php endforeach; ?>

        <p class="nav-section-title">System</p>
        <a href="profile.php" class="nav-link <?= ($activePage == 'profile') ? 'active' : '' ?>">
            <i class="fas fa-fingerprint"></i>
            <span>Root Access</span>
        </a>
    </nav>

    <div class="side-footer">
        <div class="profile-card">
            <div class="profile-meta">
                <div class="avatar"><?= $user_display['initial'] ?></div>
                <div style="margin-left: 10px;">
                    <p class="user-name-text"><?= htmlspecialchars($user_display['username']) ?></p>
                </div>
            </div>
            <a href="../auth/logout.php" class="logout-btn" title="Terminate Session">
                <i class="fas fa-power-off"></i>
            </a>
        </div>
    </div>
</aside>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (sidebar && overlay) {
        const isOpening = !sidebar.classList.contains('sidebar-open');
        sidebar.classList.toggle('sidebar-open', isOpening);
        overlay.classList.toggle('overlay-visible', isOpening);
        document.body.style.overflow = (isOpening && window.innerWidth < 1024) ? 'hidden' : 'auto';
    }
}

window.addEventListener('resize', () => {
    if (window.innerWidth >= 1024) {
        document.getElementById('sidebar').classList.remove('sidebar-open');
        document.getElementById('sidebar-overlay').classList.remove('overlay-visible');
        document.body.style.overflow = 'auto';
    }
});
</script>