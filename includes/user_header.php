<link rel="stylesheet" href="../fontawesome/css/all.min.css">

<style>
    .main-header {
        min-height: 90px;
        background-color: rgba(255,255,255,0.9);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 40;
        box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);
        padding: 0 2rem;
    }

    .header-left { display: flex; align-items: center; gap: 2rem; }

    .menu-toggle {
        display: none;
        width: 44px; height: 44px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        color: #475569;
        cursor: pointer;
        transition: all 0.2s;
        align-items: center;
        justify-content: center;
    }
    .menu-toggle:active { transform: scale(0.95); }

    .header-accent-line { height: 56px; width: 4px; background-color: #2563eb; border-radius: 9999px; box-shadow: 0 0 15px rgba(37,99,235,0.3); }

    .node-info { display: flex; flex-direction: column; gap: 8px; }
    .node-info h2 { font-size: 13px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.3em; color: #94a3b8; margin: 0; }
    .node-info h2 span.highlight { color: #2563eb; }

    .status-badge { display: flex; align-items: center; gap: 10px; background: #f8fafc; padding: 6px 12px; border-radius: 9999px; border: 1px solid #f1f5f9; width: fit-content; }

    .ping-container { position: relative; display: flex; height: 10px; width: 10px; }
    .ping-dot { position: absolute; height: 100%; width: 100%; border-radius: 50%; background: #10b981; }
    .ping-anim { animation: ping 1s cubic-bezier(0,0,0.2,1) infinite; opacity: 0.75; }
    @keyframes ping { 75%, 100% { transform: scale(2); opacity: 0; } }

    .status-text { font-size: 11px; font-weight: 900; color: #334155; text-transform: uppercase; letter-spacing: 0.1em; }

    .header-right { display: flex; align-items: center; gap: 2.5rem; }

    .user-meta { display: flex; align-items: center; gap: 20px; }
    .user-text { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
    .user-name { font-size: 16px; font-weight: 900; color: #0f172a; text-transform: uppercase; letter-spacing: -0.5px; }

    .verified-badge { background: #eff6ff; border: 1px solid #dbeafe; padding: 2px 8px; border-radius: 6px; display: flex; align-items: center; gap: 5px; }
    .verified-badge span { font-size: 9px; font-weight: 900; color: #2563eb; text-transform: uppercase; letter-spacing: 0.1em; }
    .verified-badge i { font-size: 9px; color: #2563eb; }

    .avatar-wrapper { position: relative; text-decoration: none; display: block; }
    .avatar-box { width: 56px; height: 56px; background: rgba(255,255,255,0.4); backdrop-filter: blur(20px); border-radius: 16px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.8); transition: all 0.5s ease; }
    .avatar-wrapper:hover .avatar-box { background: #2563eb; border-color: #2563eb; box-shadow: 0 10px 15px -3px rgba(37,99,235,0.3); }
    .avatar-box i { color: #94a3b8; font-size: 24px; transition: all 0.6s cubic-bezier(0.4,0,0.2,1); }
    .avatar-wrapper:hover i { color: white; transform: rotate(360deg); }

    .online-indicator { position: absolute; top: -2px; right: -2px; width: 18px; height: 18px; background: #10b981; border: 4px solid white; border-radius: 50%; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }

    @media (max-width: 1024px) {
        .main-header { min-height: 100px; padding: 0 1.5rem; }
        .menu-toggle { display: flex; }
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
            <i class="fas fa-bars"></i>
        </button>

        <div class="header-accent-line"></div>

        <div class="node-info">
            <h2><span class="node-prefix">Node / </span><span class="highlight">Terminal_User</span></h2>
            <div class="status-badge">
                <span class="ping-container">
                    <span class="ping-dot ping-anim"></span>
                    <span class="ping-dot"></span>
                </span>
                <span class="status-text">Network Secure</span>
            </div>
        </div>
    </div>

    <div class="header-right">
        <div class="user-meta">
            <div class="user-text">
                <p class="user-name"><?= htmlspecialchars($user['username'] ?? 'Agent'); ?></p>
                <div class="verified-badge">
                    <i class="fas fa-shield-alt"></i>
                    <span>Verified Agent</span>
                </div>
            </div>

            <a href="profile.php" class="avatar-wrapper">
                <div class="avatar-box">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="online-indicator"></div>
            </a>
        </div>
    </div>
</header>