<?php
// 1. DATABASE & SESSION INITIALIZATION
include '../config/db.php';
requireLogin();

$userId = $_SESSION['user_id'];
$todayDate = date('d M, Y');

try {
    // 2. FETCH PROJECT COUNT
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT project_id) FROM tasks WHERE assigned_to = ?");
    $stmt->execute([$userId]);
    $myProjectsCount = $stmt->fetchColumn() ?: 0;

    // 3. FETCH TOTAL ASSIGNED TASKS
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ?");
    $stmt->execute([$userId]);
    $myTotalTasks = $stmt->fetchColumn() ?: 0;

    // 4. FETCH IN PROGRESS TASKS — FIXED: was 'in_progress', now consistent
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'in_progress'");
    $stmt->execute([$userId]);
    $inProgressCount = $stmt->fetchColumn() ?: 0;

    // 5. CALCULATE SUCCESS RATE
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'completed'");
    $stmt->execute([$userId]);
    $completedCount = $stmt->fetchColumn() ?: 0;

    $successRate = ($myTotalTasks > 0) ? round(($completedCount / $myTotalTasks) * 100) : 0;

    // 6. FETCH OVERDUE TASKS
    $stmt = $pdo->prepare("SELECT id, title, due_date FROM tasks WHERE assigned_to = ? AND status != 'completed' AND due_date < CURDATE()");
    $stmt->execute([$userId]);
    $overdueTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $overdueCount = count($overdueTasks);

    // 7. FETCH ACTIVE ASSIGNMENTS — FIXED: p.name -> p.project_name
    $stmt = $pdo->prepare("
        SELECT t.*, p.project_name 
        FROM tasks t 
        LEFT JOIN projects p ON t.project_id = p.id 
        WHERE t.assigned_to = ? AND t.status != 'completed' 
        ORDER BY t.due_date ASC
    ");
    $stmt->execute([$userId]);
    $assignedTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 8. FETCH USER INFO for sidebar/header
    $userQuery = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $userQuery->execute([$userId]);
    $user = $userQuery->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $errorMsg = "System sync in progress...";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TMS | User Dashboard</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../fontawesome/css/all.min.css">

    <style>
        :root {
            --bg-main: #f8fafc;
            --sidebar-width: 260px;
            --primary: #2563eb;
            --dark: #0f172a;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --border: #f1f5f9;
            --text-main: #334155;
            --text-muted: #64748b;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-main); display: flex; height: 100vh; overflow: hidden; }

        .main-container { flex: 1; display: flex; flex-direction: column; overflow-y: auto; margin-left: var(--sidebar-width); }
        .dashboard-content { padding: 40px; max-width: 1400px; width: 100%; margin: 0 auto; }

        /* --- Header Banner (Preserved) --- */
        .header-banner { background: var(--dark); border-radius: 24px; padding: 40px; color: white; display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .banner-text h1 { font-size: 32px; font-weight: 800; letter-spacing: -1px; }
        .banner-text h1 span { color: var(--primary); }
        .banner-text p { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 3px; color: var(--primary); margin-bottom: 4px; }

        .date-display { text-align: right; }
        .date-label { font-size: 9px; font-weight: 800; text-transform: uppercase; color: var(--primary); }
        .date-value { font-size: 16px; font-weight: 700; opacity: 0.9; }

        /* Alert Trigger */
        .alert-trigger { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #ef4444; padding: 10px 20px; border-radius: 12px; display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 11px; font-weight: 800; text-transform: uppercase; transition: 0.3s; margin-bottom: 15px; }
        .alert-trigger:hover { background: #ef4444; color: white; }
        .badge-dot { width: 8px; height: 8px; background: #ef4444; border-radius: 50%; box-shadow: 0 0 10px #ef4444; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

        /* Stat Cards */
        .grid-layout { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; margin-bottom: 32px; }
        .stat-card { background: white; padding: 24px; border-radius: 20px; border: 1px solid var(--border); position: relative; transition: transform 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { position: absolute; right: 20px; top: 20px; color: #f1f5f9; font-size: 24px !important; }
        .label-text { font-size: 9px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; }
        .value-text { display: block; font-size: 32px; font-weight: 900; color: #0f172a; margin-top: 8px; }
        .value-warning { color: var(--warning); }
        .value-success { color: var(--success); }

        /* Analytics Box */
        .analytics-box { background: white; border-radius: 32px; padding: 40px; display: flex; gap: 40px; align-items: center; border: 1px solid var(--border); margin-bottom: 40px; }
        .chart-circle { position: relative; width: 140px; height: 140px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: conic-gradient(var(--primary) <?= ($successRate * 3.6) ?>deg, #f1f5f9 0deg); flex-shrink: 0; }
        .chart-inner { width: 110px; height: 110px; background: white; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .chart-val { font-size: 32px; font-weight: 900; color: #0f172a; }
        .analytics-text { flex: 1; border-left: 4px solid var(--primary); padding-left: 24px; }
        .analytics-quote { font-style: italic; color: #64748b; font-size: 14px; line-height: 1.6; }

        /* --- UPDATED: Active Assignments Queue Section --- */
        .assignment-container { margin-bottom: 40px; }
        .section-header-flex { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; padding: 0 8px; }
        .section-title-group p { font-size: 10px; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 4px; }
        .section-title-group h2 { font-size: 24px; font-weight: 800; color: var(--dark); }
        
        .task-stack { display: flex; flex-direction: column; gap: 12px; }
        
        .task-item-card { 
            background: white; 
            padding: 24px; 
            border-radius: 24px; 
            border: 1px solid var(--border); 
            display: grid; 
            grid-template-columns: 180px 1fr 150px 40px; 
            align-items: center; 
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
            cursor: pointer;
            text-decoration: none;
        }

        .task-item-card:hover { 
            transform: scale(1.01) translateX(10px); 
            border-color: var(--primary); 
            box-shadow: 0 15px 30px rgba(0,0,0,0.05); 
        }

        .proj-badge { 
            font-size: 10px; 
            font-weight: 800; 
            color: var(--primary); 
            background: rgba(37, 99, 235, 0.08); 
            padding: 6px 12px; 
            border-radius: 8px; 
            text-transform: uppercase;
            width: fit-content;
        }

        .task-info h3 { font-size: 16px; font-weight: 700; color: var(--dark); margin-bottom: 4px; }
        .task-info p { font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }

        .status-wrapper { text-align: right; }
        .pill-status { 
            display: inline-block;
            padding: 6px 16px; 
            border-radius: 100px; 
            font-size: 9px; 
            font-weight: 900; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            border: 1px solid transparent;
        }
        
        /* Status Colors */
        .st-pending { background: #f8fafc; color: #64748b; border-color: #e2e8f0; }
        .st-in-progress { background: #fff7ed; color: #c2410c; border-color: #ffedd5; }
        .st-completed { background: #f0fdf4; color: #16a34a; border-color: #dcfce7; }

        .action-chevron { color: #cbd5e1; font-size: 18px; transition: 0.3s; text-align: right; }
        .task-item-card:hover .action-chevron { color: var(--primary); transform: translateX(4px); }

        .empty-state-card { background: white; border-radius: 24px; padding: 60px; text-align: center; border: 2px dashed var(--border); }

        /* Flash Message */
        .flash-message { padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-size: 13px; font-weight: 700; }
        .flash-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .flash-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(2,6,23,0.85); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .modal-content { background: white; width: 95%; max-width: 500px; border-radius: 32px; padding: 40px; position: relative; }
        .warning-icon-wrapper { width: 80px; height: 80px; background: #fef2f2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; }
        .warning-icon-wrapper i { font-size: 32px; color: var(--error); }
        .overdue-list { max-height: 250px; overflow-y: auto; margin: 20px 0; border-top: 1px solid #f1f5f9; padding-right: 5px; }
        .overdue-item { padding: 15px 0; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .overdue-task-date { font-size: 10px; color: var(--error); font-weight: 800; text-transform: uppercase; margin-top: 4px; }
        .btn-resolve { width: 100%; padding: 18px; background: var(--dark); color: white; border: none; border-radius: 16px; font-weight: 800; cursor: pointer; transition: 0.3s; text-transform: uppercase; letter-spacing: 1px; }
        .btn-resolve:hover { background: #000; transform: translateY(-2px); }

        @media (max-width: 1024px) { 
            .main-container { margin-left: 0; } 
            .task-item-card { grid-template-columns: 1fr 1fr; gap: 20px; }
            .action-chevron, .proj-badge { display: none; }
        }
    </style>
</head>
<body>
    <?php include '../includes/user_sidebar.php'; ?>

    <div class="main-container">
        <?php include '../includes/user_header.php'; ?>

        <main class="dashboard-content">

            <?php if (isset($_SESSION['message'])): ?>
                <div class="flash-message flash-<?= $_SESSION['message']['type'] ?>">
                    <?= htmlspecialchars($_SESSION['message']['text']) ?>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <!-- Header Banner (UNCHANGED) -->
            <header class="header-banner">
                <div class="banner-text">
                    <p>Personal Workspace</p>
                    <h1>Mission <span>Control</span></h1>
                </div>

                <div class="banner-right">
                    <?php if ($overdueCount > 0): ?>
                        <div class="alert-trigger" onclick="toggleModal(true)">
                            <div class="badge-dot"></div>
                            <span><?= $overdueCount ?> Critical Issues</span>
                        </div>
                    <?php endif; ?>

                    <div class="date-display">
                        <span class="date-label">Current Cycle</span>
                        <div class="date-value"><?= $todayDate ?></div>
                    </div>
                </div>
            </header>

            <div class="grid-layout">
                <div class="stat-card">
                    <i class="fas fa-folder-open stat-icon"></i>
                    <span class="label-text">My Projects</span>
                    <span class="value-text"><?= $myProjectsCount ?></span>
                </div>
                <div class="stat-card">
                    <i class="fas fa-tasks stat-icon"></i>
                    <span class="label-text">My Tasks</span>
                    <span class="value-text"><?= $myTotalTasks ?></span>
                </div>
                <div class="stat-card">
                    <i class="fas fa-spinner stat-icon"></i>
                    <span class="label-text">In Progress</span>
                    <span class="value-text value-warning"><?= $inProgressCount ?></span>
                </div>
                <div class="stat-card">
                    <i class="fas fa-microchip stat-icon"></i>
                    <span class="label-text">Success Rate</span>
                    <span class="value-text value-success"><?= $successRate ?>%</span>
                </div>
            </div>

            <section class="analytics-box">
                <div class="chart-circle">
                    <div class="chart-inner">
                        <span class="chart-val"><?= $successRate ?>%</span>
                        <span class="label-text chart-label">Rating</span>
                    </div>
                </div>
                <div class="analytics-text">
                    <p class="analytics-quote">
                        <i class="fas fa-quote-left" style="color: var(--primary); margin-right: 10px; opacity: 0.5;"></i>
                        Telemetry check complete for <strong><?= $todayDate ?></strong>. Operations are currently focused on <strong><?= $inProgressCount ?></strong> active assignments within the terminal stack.
                    </p>
                </div>
            </section>

            <!-- FULLY UPDATED: Active Assignments Section -->
            <section class="assignment-container">
                <div class="section-header-flex">
                    <div class="section-title-group">
                        <p>Real-time Queue</p>
                        <h2>Active Assignments</h2>
                    </div>
                    <div class="label-text"><?= count($assignedTasks) ?> Tasks Syncing</div>
                </div>

                <div class="task-stack">
                    <?php if (empty($assignedTasks)): ?>
                        <div class="empty-state-card">
                            <i class="fas fa-wind" style="font-size: 32px; color: var(--border); margin-bottom: 16px; display: block;"></i>
                            <p style="color: #94a3b8; font-weight: 600;">Queue is empty. No active assignments detected.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($assignedTasks as $task): 
                            // Determine status class
                            $stClass = 'st-pending';
                            if($task['status'] == 'in_progress') $stClass = 'st-in-progress';
                            if($task['status'] == 'completed') $stClass = 'st-completed';
                        ?>
                            <a href="my_tasks.php?id=<?= $task['id'] ?>" class="task-item-card">
                                <div class="proj-col">
                                    <span class="proj-badge">
                                        <?= htmlspecialchars($task['project_name'] ?? 'General'); ?>
                                    </span>
                                </div>
                                
                                <div class="task-info">
                                    <h3><?= htmlspecialchars($task['title']); ?></h3>
                                    <p><i class="far fa-calendar-alt"></i> Assigned to your current cycle</p>
                                </div>

                                <div class="status-wrapper">
                                    <span class="pill-status <?= $stClass ?>">
                                        <?= strtoupper(str_replace('_', ' ', $task['status'])); ?>
                                    </span>
                                </div>

                                <div class="action-chevron">
                                    <i class="fas fa-chevron-right"></i>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <!-- Overdue Modal -->
    <div class="modal-overlay" id="overdueModal">
        <div class="modal-content">
            <div class="modal-header" style="text-align: center;">
                <div class="warning-icon-wrapper">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h2 style="font-weight: 800; color: #0f172a;">Critical Delay</h2>
                <p style="color: #64748b; font-size: 14px; margin-top: 8px;">Immediate intervention required for overdue nodes.</p>
            </div>

            <div class="overdue-list">
                <?php foreach ($overdueTasks as $oTask): ?>
                    <div class="overdue-item">
                        <div>
                            <div style="font-weight:700; font-size:13px; color: #1e293b;"><?= htmlspecialchars($oTask['title']) ?></div>
                            <div class="overdue-task-date">DUE: <?= date('M d', strtotime($oTask['due_date'])) ?></div>
                        </div>
                        <a href="update_task.php?id=<?= $oTask['id'] ?>" style="text-decoration:none; color:var(--primary); transition: 0.2s;">
                            <i class="fas fa-arrow-alt-circle-right" style="font-size: 20px;"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <button class="btn-resolve" onclick="toggleModal(false)">Acknowledge Protocol</button>
        </div>
    </div>

    <script>
        function toggleModal(show) {
            const modal = document.getElementById('overdueModal');
            modal.style.display = show ? 'flex' : 'none';
        }
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') toggleModal(false);
        });
        document.getElementById('overdueModal').addEventListener('click', function(e) {
            if (e.target === this) toggleModal(false);
        });
    </script>
</body>
</html>