<?php
include '../config/db.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Initializing variables to prevent "Undefined Variable" or "TypeError"
$projects = [];
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';
$userPendingCount = 0;

try {
    // 1. Fetch User Info for Header/Sidebar
    $userQuery = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $userQuery->execute([$user_id]);
    $user = $userQuery->fetch(PDO::FETCH_ASSOC);

    // 2. Pending Count for Stats
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status != 'completed'");
    $countStmt->execute([$user_id]);
    $userPendingCount = $countStmt->fetchColumn();

    // 3. Search and Filter Logic
    $queryStr = "SELECT id, project_name, plan_type, status FROM projects WHERE 1=1";
    $params = [];

    if ($search) {
        $queryStr .= " AND project_name LIKE ?";
        $params[] = "%$search%";
    }

    if ($statusFilter !== 'all') {
        $queryStr .= " AND status = ?";
        $params[] = $statusFilter;
    }

    $queryStr .= " ORDER BY id DESC";
    
    $stmt = $pdo->prepare($queryStr);
    $stmt->execute($params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Log error and continue with empty state
    error_log("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Directory | TMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
    <style>
        :root {
            --bg-body: #f1f5f9;
            --sidebar-width: 260px;
            --primary: #4f46e5;
            --primary-light: #eef2ff;
            --dark-panel: #0f172a;
            --glass-white: #ffffff;
            --border-color: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --success: #059669;
            --warning: #d97706;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; }

        .main-wrapper { flex: 1; margin-left: var(--sidebar-width); transition: 0.3s; width: calc(100% - var(--sidebar-width)); }
        .container { padding: 32px; max-width: 1400px; margin: 0 auto; }

        /* --- Dark Banner --- */
        .dark-banner { 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
            border-radius: 24px; padding: 40px; color: white; 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }
        .banner-content p { font-size: 12px; font-weight: 700; color: #818cf8; text-transform: uppercase; letter-spacing: 2px; }
        .banner-content h1 { font-size: 32px; font-weight: 800; margin-top: 4px; }

        .search-container { position: relative; width: 320px; }
        .search-input { 
            width: 100%; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); 
            border-radius: 14px; padding: 12px 16px 12px 44px; color: white; outline: none; transition: 0.3s;
        }
        .search-input:focus { background: rgba(255,255,255,0.12); border-color: var(--primary); }
        .search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; }

        /* --- Summary Cards --- */
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 32px; }
        .stat-card { 
            background: white; padding: 24px; border-radius: 20px; border: 1px solid var(--border-color);
            display: flex; align-items: center; gap: 16px;
        }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: var(--primary-light); color: var(--primary); }
        .stat-info h4 { font-size: 24px; font-weight: 800; }
        .stat-info p { font-size: 13px; color: var(--text-muted); font-weight: 600; }

        /* --- Filters --- */
        .filter-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .status-pills { display: flex; gap: 8px; background: #e2e8f0; padding: 4px; border-radius: 12px; }
        .pill { 
            padding: 8px 16px; border-radius: 10px; font-size: 13px; font-weight: 700; 
            text-decoration: none; color: var(--text-muted); transition: 0.2s; 
        }
        .pill.active { background: white; color: var(--primary); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }

        /* --- Table Styling --- */
        .table-card { background: white; border-radius: 24px; border: 1px solid var(--border-color); overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 18px 24px; text-align: left; font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border-color); }
        td { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        
        .proj-name-cell { display: flex; align-items: center; gap: 14px; }
        .proj-avatar { width: 36px; height: 36px; border-radius: 8px; background: var(--dark-panel); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 12px; }
        
        .status-badge { 
            display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase;
        }
        .status-active { background: #ecfdf5; color: var(--success); }
        .status-pending { background: #fffbeb; color: var(--warning); }

        .action-btn { 
            padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border-color); 
            background: white; text-decoration: none; color: var(--text-main); font-size: 11px; font-weight: 800; text-transform: uppercase; transition: 0.2s;
        }
        .action-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }

        /* --- Bulk Bar --- */
        .bulk-action-bar { 
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%) translateY(150%);
            background: #0f172a; color: white; padding: 16px 32px; border-radius: 100px;
            display: flex; align-items: center; gap: 20px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.4);
            transition: 0.4s cubic-bezier(0.18, 0.89, 0.32, 1.28); z-index: 1000; border: 1px solid rgba(255,255,255,0.1);
        }
        .bulk-action-bar.active { transform: translateX(-50%) translateY(0); }

        @media (max-width: 1024px) {
            .main-wrapper { margin-left: 0; width: 100%; }
            .dark-banner { flex-direction: column; gap: 20px; text-align: center; }
            .search-container { width: 100%; }
        }
    </style>
</head>
<body>

    <?php include '../includes/user_sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include '../includes/user_header.php'; ?>

        <main class="container">
            <!-- Dark Banner Header -->
            <header class="dark-banner">
                <div class="banner-content">
                    <p>Network Systems</p>
                    <h1>Global Registry</h1>
                </div>
                <div class="search-container">
                    <span class="material-symbols-outlined search-icon">search</span>
                    <form method="GET" action="">
                        <input type="text" name="search" class="search-input" placeholder="Search project database..." value="<?= htmlspecialchars($search) ?>">
                    </form>
                </div>
            </header>

            <!-- Summary Section -->
            <section class="summary-grid">
                <div class="stat-card">
                    <div class="stat-icon"><span class="material-symbols-outlined">database</span></div>
                    <div class="stat-info">
                        <h4><?= count($projects) ?></h4>
                        <p>Total Records</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fff7ed; color:#ea580c;"><span class="material-symbols-outlined">assignment_late</span></div>
                    <div class="stat-info">
                        <h4><?= $userPendingCount ?></h4>
                        <p>My Pending Tasks</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#f0fdf4; color:#16a34a;"><span class="material-symbols-outlined">verified</span></div>
                    <div class="stat-info">
                        <h4>Active</h4>
                        <p>System Status</p>
                    </div>
                </div>
            </section>

            <!-- Filters -->
            <div class="filter-row">
                <div class="status-pills">
                    <a href="?status=all" class="pill <?= $statusFilter == 'all' ? 'active' : '' ?>">All</a>
                    <a href="?status=active" class="pill <?= $statusFilter == 'active' ? 'active' : '' ?>">Active</a>
                    <a href="?status=completed" class="pill <?= $statusFilter == 'completed' ? 'active' : '' ?>">Completed</a>
                </div>
            </div>

            <!-- Table -->
            <form id="bulkForm" method="POST" action="bulk_process.php">
                <div class="table-card">
                    <table>
                        <thead>
                            <tr>
                                <th width="50"><input type="checkbox" id="selectAll"></th>
                                <th>Project Identity</th>
                                <th>Service Tier</th>
                                <th>Status</th>
                                <th style="text-align:right;">Control</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($projects)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding: 80px; color: var(--text-muted);">
                                        <span class="material-symbols-outlined" style="font-size: 48px; opacity: 0.2; display: block; margin-bottom: 10px;">folder_off</span>
                                        No projects found matching your criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_ids[]" value="<?= $project['id'] ?>" class="project-check"></td>
                                    <td>
                                        <div class="proj-name-cell">
                                            <div class="proj-avatar"><?= strtoupper(substr($project['project_name'], 0, 1)); ?></div>
                                            <div>
                                                <div style="font-weight:700; font-size:14px; color:var(--text-main);"><?= htmlspecialchars($project['project_name']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span style="font-weight:600; font-size:13px;"><?= htmlspecialchars($project['plan_type']); ?></span></td>
                                    <td>
                                        <?php $is_active = strtolower($project['status']) === 'active'; ?>
                                        <span class="status-badge <?= $is_active ? 'status-active' : 'status-pending' ?>">
                                            <span class="material-symbols-outlined" style="font-size:14px;">fiber_manual_record</span>
                                            <?= htmlspecialchars($project['status']); ?>
                                        </span>
                                    </td>
                                    <td style="text-align:right;">
                                        <a href="view_project.php?id=<?= $project['id']; ?>" class="action-btn">Inspect</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </main>
    </div>

    <!-- Bulk Action Footer -->
    <div class="bulk-action-bar" id="bulkBar">
        <div style="display:flex; align-items:center; gap:12px; border-right: 1px solid rgba(255,255,255,0.1); padding-right: 20px;">
            <span id="countDisplay" style="font-weight:800; font-size:18px; color: var(--primary);">0</span>
            <span style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Selected</span>
        </div>
        <button type="button" onclick="handleBulkDelete()" style="background:none; border:none; color:#fda4af; cursor:pointer; font-weight:700; display:flex; align-items:center; gap:8px; font-family:inherit; font-size:12px; text-transform:uppercase;">
            <span class="material-symbols-outlined" style="font-size:20px;">delete_forever</span> Purge Selected
        </button>
    </div>

    <script>
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.project-check');
        const bulkBar = document.getElementById('bulkBar');
        const countDisplay = document.getElementById('countDisplay');

        function updateBulkBar() {
            const checkedCount = document.querySelectorAll('.project-check:checked').length;
            countDisplay.innerText = checkedCount;
            if(checkedCount > 0) bulkBar.classList.add('active');
            else bulkBar.classList.remove('active');
        }

        selectAll.addEventListener('change', () => {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkBar();
        });

        checkboxes.forEach(cb => {
            cb.addEventListener('change', () => {
                updateBulkBar();
                if(!cb.checked) selectAll.checked = false;
            });
        });

        function handleBulkDelete() {
            if (confirm("CRITICAL ACTION: Are you sure you want to permanently delete the selected entries? This cannot be undone.")) {
                const bulkForm = document.getElementById('bulkForm');
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'bulk_action';
                actionInput.value = 'delete';
                bulkForm.appendChild(actionInput);
                bulkForm.submit();
            }
        }
    </script>
</body>
</html>