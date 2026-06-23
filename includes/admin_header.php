<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
<link rel="stylesheet" href="../fontawesome/css/all.min.css">

<style>
    :root {
        --primary: #2563eb;
        --slate-900: #0f172a;
        --slate-700: #334155;
        --slate-500: #64748b;
        --slate-200: #e2e8f0;
        --glass-bg: rgba(255, 255, 255, 0.85);
        --header-height: 90px;
    }

    /* Header Container */
    .main-header {
        height: var(--header-height);
        background-color: var(--glass-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-bottom: 1px solid var(--slate-200);
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 100;
        padding: 0 2.5rem;
    }

    /* Left Section: Branding & Node Info */
    .header-left { display: flex; align-items: center; gap: 2rem; }

    .menu-toggle {
        display: none; /* Shown on Mobile via Media Query */
        width: 44px; height: 44px;
        background: #f8fafc;
        border: 1px solid var(--slate-200);
        border-radius: 12px;
        color: var(--slate-700);
        cursor: pointer;
        transition: all 0.2s;
    }

    .header-accent-line {
        height: 48px;
        width: 4px;
        background: var(--primary);
        border-radius: 999px;
        box-shadow: 0 0 15px rgba(37, 99, 235, 0.2);
    }

    .node-info { display: flex; flex-direction: column; gap: 4px; }
    .node-info h2 {
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.25em;
        color: var(--slate-500);
        margin: 0;
    }
    .node-info .highlight { color: var(--primary); }

    .glass-icon-container {
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #fff;
        font-size: 24px;
    }
    .status-badge {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #f0fdf4;
        padding: 4px 12px;
        border-radius: 999px;
        border: 1px solid #dcfce7;
        width: fit-content;
    }

    /* Pulse Animation */
    .ping-container { position: relative; display: flex; height: 8px; width: 8px; }
    .ping-dot { position: absolute; height: 100%; width: 100%; border-radius: 50%; background: #10b981; }
    .ping-anim { animation: pulse 1.5s cubic-bezier(0, 0, 0.2, 1) infinite; }

    @keyframes pulse { 75%, 100% { transform: scale(2.5); opacity: 0; } }

    .status-text { font-size: 10px; font-weight: 800; color: #166534; text-transform: uppercase; letter-spacing: 0.05em; }

    /* Right Section: User & Stats */
    .header-right { display: flex; align-items: center; gap: 2rem; }

    .user-meta { display: flex; align-items: center; gap: 16px; }
    .user-text { display: flex; flex-direction: column; align-items: flex-end; }
    
    .user-name { 
        font-size: 15px; 
        font-weight: 800; 
        color: var(--slate-900); 
        text-transform: uppercase; 
        letter-spacing: -0.3px; 
    }

    .role-badge {
        font-size: 9px;
        font-weight: 900;
        color: var(--primary);
        background: #eff6ff;
        padding: 2px 8px;
        border-radius: 6px;
        text-transform: uppercase;
        border: 1px solid #dbeafe;
    }

    .avatar-wrapper { position: relative; text-decoration: none; }
    .avatar-box {
        width: 52px; height: 52px;
        background: white;
        border: 1px solid var(--slate-200);
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .avatar-box .material-symbols-outlined { color: var(--slate-500); font-size: 28px; transition: 0.5s; }

    .avatar-wrapper:hover .avatar-box { 
        border-color: var(--primary); 
        background: var(--primary); 
        box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.2);
    }
    .avatar-wrapper:hover .material-symbols-outlined { color: white; transform: rotate(10deg); }

    .online-indicator {
        position: absolute; bottom: -2px; right: -2px;
        width: 14px; height: 14px;
        background: #10b981;
        border: 3px solid white;
        border-radius: 50%;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .main-header { padding: 0 1.5rem; }
        .menu-toggle { display: flex; align-items: center; justify-content: center; }
        .header-accent-line { display: none; }
    }

    @media (max-width: 640px) {
        .user-text { display: none; }
        .node-prefix { display: none; }
    }
</style>

<header class="main-header">
    <div class="header-left">
        <button onclick="toggleSidebar()" class="menu-toggle" aria-label="Toggle Menu">
            <span class="material-symbols-outlined">menu</span>
        </button>

        <div class="header-accent-line"></div>
        
        <div class="node-info">
            <h2><span class="node-prefix">Node / </span><span class="highlight">Terminal_Admin</span></h2>
            <div class="status-badge">
                <span class="ping-container">
                    <span class="ping-dot ping-anim"></span>
                    <span class="ping-dot"></span>
                </span>
                <span class="status-text">Encrypted Connection</span>
            </div>
        </div>
    </div>

    <div class="header-right">
        <div class="user-meta">
            <div class="user-text">
                <p class="user-name"><?= htmlspecialchars($user['username'] ?? 'Authorized Agent'); ?></p>
                <div class="role-badge">Verified Session</div>
            </div>
            
            <a href="profile.php" class="avatar-wrapper">
                <div class="avatar-box">
                   <i class="fas fa-shield-alt"></i>
                </div>
                <div class="online-indicator"></div>
            </a>
        </div>
    </div>
</header>

<script>
    /**
     * Toggles the navigation sidebar.
     * Ensure your sidebar element has the ID 'sidebar' and a transition property.
     */
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        
        if (!sidebar) return;

        // Toggle using a specific class for better state management
        const isOpen = sidebar.classList.toggle('sidebar-open');
        
        if (isOpen) {
            sidebar.style.transform = "translateX(0px)";
            if (overlay) overlay.style.display = "block";
            document.body.style.overflow = "hidden";
        } else {
            sidebar.style.transform = "translateX(-100%)";
            if (overlay) overlay.style.display = "none";
            document.body.style.overflow = "auto";
        }
    }
</script>