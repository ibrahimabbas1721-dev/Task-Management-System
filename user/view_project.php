<?php
include '../config/db.php';
requireLogin();

// 1. DATA VALIDATION & SECURE FETCHING
$project_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

// Initialize to prevent "Undefined Variable" warnings
$project = null;
$projectTasks = [];

try {
    // Fetch Project Details
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    // If project ID is invalid or missing, bounce user back to project list
    if (!$project) {
        header("Location: projects.php?error=invalid_id");
        exit;
    }

    // Fetch ONLY tasks assigned to this user for this project
    $stmtTasks = $pdo->prepare("SELECT * FROM tasks WHERE project_id = ? AND assigned_to = ? ORDER BY due_date ASC");
    $stmtTasks->execute([$project_id, $user_id]);
    $projectTasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (PDOException $e) {
    // If the database crashes, don't show the user a broken page
    die("<div style='font-family:sans-serif; padding:50px; text-align:center;'>
            <h2 style='color:#ef4444;'>SYSTEM_SYNC_FAILURE</h2>
            <p>Connection lost. Please try again or contact IT.</p>
         </div>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($project['project_name']) ?> | Project Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
    <style>
        :root {
            --bg-body: #f8fafc;
            --sidebar-width: 260px;
            --primary: #6366f1;
            --primary-light: rgba(99,102,241,0.1);
            --dark-panel: #020617;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            --success: #10b981;
            --warning: #f59e0b;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: var(--text-main); display: flex; }
        
        .main-wrapper { flex: 1; margin-left: var(--sidebar-width); display: flex; flex-direction: column; min-width: 0; }
        .container { padding: 40px; max-width: 1300px; width: 100%; margin: 0 auto; }

        /* Navigation */
        .breadcrumb { display: flex; align-items: center; gap: 8px; margin-bottom: 24px; font-size: 13px; font-weight: 600; }
        .breadcrumb a { color: var(--text-muted); text-decoration: none; }
        .breadcrumb .current { color: var(--text-main); }

        /* Dark Hero Banner */
        .dark-banner { 
            background: linear-gradient(135deg, #020617 0%, #1e1b4b 100%); 
            border-radius: 24px; padding: 40px; color: white; 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 40px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }
        .banner-content p { font-size: 10px; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 3px; margin-bottom: 8px; }
        .banner-content h1 { font-size: 32px; font-weight: 800; letter-spacing: -1px; }

        .status-box { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 16px 28px; border-radius: 20px; text-align: center; }
        .status-box .label { font-size: 9px; font-weight: 800; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 4px; }
        .status-box .value { font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 10px; }

        .pulse-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--primary); animation: pulse 2s infinite; }
        .status-completed .pulse-dot { background: var(--success); }

        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(99,102,241,0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(99,102,241,0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(99,102,241,0); }
        }

        /* Task Cards */
        .section-title { font-size: 18px; font-weight: 800; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .task-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 20px; }

        .task-card { 
            background: white; border-radius: 20px; padding: 24px; 
            display: flex; flex-direction: column; justify-content: space-between;
            border: 1px solid #e2e8f0; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--card-shadow);
        }
        .task-card:hover { transform: translateY(-5px); border-color: var(--primary); }

        .task-header { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 20px; }
        .task-icon { width: 48px; height: 48px; background: var(--primary-light); color: var(--primary); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        
        .task-title-area h4 { font-size: 16px; font-weight: 700; margin-bottom: 4px; }
        .protocol-id { font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; }

        .task-footer { display: flex; align-items: center; justify-content: space-between; padding-top: 15px; border-top: 1px solid #f1f5f9; }
        .meta-tag { font-size: 12px; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }

        .btn-update { 
            background: var(--dark-panel); color: white; padding: 10px 18px; 
            border-radius: 12px; font-size: 13px; font-weight: 700; text-decoration: none;
            display: flex; align-items: center; gap: 8px; transition: 0.3s;
        }
        .btn-update:hover { background: var(--primary); transform: scale(1.05); }

        @media (max-width: 1024px) { .main-wrapper { margin-left: 0; } }
        @media (max-width: 768px) { .task-grid { grid-template-columns: 1fr; } .dark-banner { flex-direction: column; gap: 20px; text-align: center; } }
    </style>
</head>

<body>
    <?php include '../includes/user_sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include '../includes/user_header.php'; ?>

        <main class="container">
            <nav class="breadcrumb">
                <a href="projects.php">System Archive</a>
                <span class="material-symbols-outlined" style="font-size:16px;">chevron_right</span>
                <span class="current">Protocol Directory</span>
            </nav>

            <header class="dark-banner">
                <div class="banner-content">
                    <p>Sector Identifier // <?= htmlspecialchars($project['plan_type']) ?></p>
                    <h1><?= htmlspecialchars($project['project_name']) ?></h1>
                </div>

                <div class="status-box <?= (strtolower($project['status']) == 'completed') ? 'status-completed' : '' ?>">
                    <span class="label">Operational Status</span>
                    <div class="value">
                        <span class="pulse-dot"></span>
                        <?= strtoupper(htmlspecialchars($project['status'])) ?>
                    </div>
                </div>
            </header>

            <h2 class="section-title">
                <span class="material-symbols-outlined">analytics</span>
                Deployment Protocols (<?= count($projectTasks) ?>)
            </h2>

            <div class="task-grid">
                <?php foreach ($projectTasks as $t): ?>
                    <div class="task-card">
                        <div class="task-header">
                            <div class="task-icon">
                                <span class="material-symbols-outlined">terminal</span>
                            </div>
                            <div class="task-title-area">
                                <h4><?= htmlspecialchars($t['title']) ?></h4>
                            </div>
                        </div>

                        <div class="task-footer">
                            <div class="meta-tag">
                                <span class="material-symbols-outlined" style="font-size:16px;">calendar_today</span>
                                <?= $t['due_date'] ? date('d M, Y', strtotime($t['due_date'])) : 'Standby' ?>
                            </div>

                            <a href="update_task.php?id=<?= $t['id'] ?>" class="btn-update">
                                <span class="material-symbols-outlined" style="font-size:18px;">sync</span>
                                Update
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($projectTasks)): ?>
                    <div style="grid-column: 1 / -1; text-align:center; padding:80px; background:white; border-radius:24px; border:2px dashed #e2e8f0;">
                        <span class="material-symbols-outlined" style="font-size:48px; color:#cbd5e1;">folder_off</span>
                        <p style="margin-top:10px; font-weight:600; color:#94a3b8;">No active protocols in this cluster.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>