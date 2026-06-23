<?php
include '../config/db.php';
requireLogin(); 
requireRole('admin');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$admin_id   = $_SESSION['user_id'];
$task_id    = isset($_GET['id']) ? (int)$_GET['id'] : null;
$activePage = 'projects';
$error      = null;

if (!$task_id) { 
    header("Location: view_tasks.php"); 
    exit; 
}

try {
    // 1. Fetch task + project context
    $stmt = $pdo->prepare("
        SELECT t.*, p.project_name, p.plan_type
        FROM tasks t
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE t.id = ? AND t.created_by_admin = ?
    ");
    $stmt->execute([$task_id, $admin_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) { 
        header("Location: view_tasks.php"); 
        exit; 
    }

    $plan      = $task['plan_type'] ?? 'LITE';
    $planClass = strtolower(str_replace(' ', '-', $plan));

    // 2. Fetch Projects owned by this admin
    $proj_stmt = $pdo->prepare("
        SELECT id, project_name FROM projects
        WHERE created_by_admin = ? ORDER BY project_name ASC
    ");
    $proj_stmt->execute([$admin_id]);
    $projects = $proj_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch ONLY Users managed by THIS admin
    // This prevents Admin A from seeing Admin B's users in the edit dropdown
    $user_stmt = $pdo->prepare("
        SELECT id, username, email 
        FROM users 
        WHERE role != 'admin' 
        AND created_by_admin = ?
        ORDER BY username ASC
    ");
    $user_stmt->execute([$admin_id]);
    $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// 4. Handle form update (Logic remains same, security ensures admin_id matches)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date    = !empty($_POST['due_date'])    ? $_POST['due_date']         : null;
    $status      = $_POST['status'];
    $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
    $project_id  = !empty($_POST['project_id'])  ? (int)$_POST['project_id']  : null;

    if (empty($title)) {
        $error = "Task title is required.";
    } else {
        try {
            $upd = $pdo->prepare("
                UPDATE tasks
                SET title = ?, description = ?, due_date = ?, status = ?,
                    assigned_to = ?, project_id = ?, updated_at = NOW()
                WHERE id = ? AND created_by_admin = ?
            ");
            $upd->execute([$title, $description, $due_date, $status,
                           $assigned_to, $project_id, $task_id, $admin_id]);

            $_SESSION['message'] = ['type' => 'success', 'text' => 'Task updated successfully.'];
            header("Location: all_tasks.php");
            exit;
        } catch (PDOException $e) {
            $error = "Update Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Edit Task | TMS Pro</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../fontawesome/css/all.min.css" />

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --brand: #6366f1; 
            --brand-dark: #4f46e5; 
            --brand-soft: #eef2ff;
            --dark: #0f172a; 
            --bg: #f8fafc; 
            --surface: #ffffff;
            --text-main: #0f172a; 
            --text-muted: #64748b;
            --danger: #ef4444; 
            --border: #e2e8f0;
            --radius-sm: 14px; 
            --radius-md: 24px;
            --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg); 
            color: var(--text-main); 
            -webkit-font-smoothing: antialiased; 
        }

        /* Layout */
        .layout-wrapper { display: flex; min-height: 100vh; }
        .sidebar-spacer { width: 260px; flex-shrink: 0; background: #fff; border-right: 1px solid var(--border); }
        .main-content { flex: 1; display: flex; flex-direction: column; }
        .scroll-area { padding: 3rem 2rem; }
        .container { max-width: 850px; margin: 0 auto; width: 100%; }
        
        /* Navigation */
        .top-nav { margin-bottom: 1.5rem; }
        .back-link { 
            display: inline-flex; 
            align-items: center; 
            gap: 10px; 
            text-decoration: none; 
            color: var(--text-muted); 
            font-size: 14px; 
            font-weight: 700; 
            transition: var(--transition); 
        }
        .back-link:hover { color: var(--brand); transform: translateX(-4px); }
        
        /* Hero Section (From your screenshot) */
        .task-hero { 
            background: #0f172a; 
            border-radius: var(--radius-md); 
            padding: 2.5rem; 
            color: white; 
            margin-bottom: 2.5rem; 
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }
        .plan-tag { 
            display: inline-block; 
            padding: 6px 14px; 
            border-radius: 4px; 
            font-size: 10px; 
            font-weight: 900; 
            text-transform: uppercase; 
            letter-spacing: 0.1em; 
            margin-bottom: 1.5rem; 
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        
        .hero-title { font-size: 36px; font-weight: 800; margin-bottom: 1.2rem; letter-spacing: -0.02em; }
        .hero-meta { color: #94a3b8; font-size: 15px; display: flex; align-items: center; gap: 20px; }
        .meta-item { display: flex; align-items: center; gap: 8px; }
        .meta-item i { color: var(--brand); font-size: 14px; }

        .status-timeline { margin-top: 2.5rem; height: 6px; background: rgba(255,255,255,0.1); border-radius: 10px; }
        .status-progress { 
            height: 100%; border-radius: 10px; background: var(--brand); 
            width: <?= ($task['status'] === 'complete') ? '100%' : (($task['status'] === 'in_progress') ? '60%' : '20%') ?>;
        }

        /* Glass Card */
        .glass-card { 
            background: #fff;
            border-radius: var(--radius-md); 
            padding: 3rem; 
            border: 1px solid var(--border); 
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); 
        }

        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
        .form-group { display: flex; flex-direction: column; gap: 10px; margin-bottom: 1.8rem; }
        
        label { font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: .1em; }
        label i { margin-right: 5px; color: var(--brand); }
        
        .modern-input {
            background: #f1f5f9; 
            border: 2px solid transparent; 
            border-radius: var(--radius-sm);
            padding: 14px 18px; 
            font-size: 15px; 
            font-weight: 600; 
            color: var(--text-main); 
            outline: none; 
            width: 100%; 
            transition: var(--transition);
        }
        .modern-input:focus { background: #fff; border-color: var(--brand); box-shadow: 0 0 0 4px var(--brand-soft); }

        /* Actions */
        .form-actions { 
            margin-top: 1rem; padding-top: 2rem; border-top: 1px solid #f1f5f9; 
            display: flex; justify-content: space-between; align-items: center; 
        }
        
        .btn-update { 
            background: var(--brand); color: #fff; border: none; padding: 16px 36px; 
            border-radius: var(--radius-sm); font-weight: 800; font-size: 15px; 
            cursor: pointer; display: flex; align-items: center; gap: 10px; 
            transition: var(--transition);
        }
        .btn-update:hover { background: var(--brand-dark); transform: translateY(-2px); }
        
        .delete-btn { 
            color: var(--danger); text-decoration: none; font-weight: 700; font-size: 14px;
            display: flex; align-items: center; gap: 8px; padding: 10px; border-radius: 10px;
            transition: var(--transition);
        }
        .delete-btn:hover { background: #fef2f2; }
        
        @media(max-width:768px){
            .sidebar-spacer { display:none; }
            .form-grid { grid-template-columns: 1fr; }
            .glass-card { padding: 1.5rem; }
        }
    </style>
</head>
<body>

<div class="layout-wrapper">
    <div class="sidebar-spacer"><?php include '../includes/admin_sidebar.php'; ?></div>

    <div class="main-content">
        <?php include '../includes/admin_header.php'; ?>

        <main class="scroll-area">
            <div class="container">
                
                <div class="top-nav">
                    <a href="all_tasks.php" class="back-link">
                        <i class="fas fa-arrow-left"></i> Back to Workspace
                    </a>
                </div>

                <div class="task-hero">
                    <span class="plan-tag">ELITE PLAN</span>
                    <h1 class="hero-title"><?= htmlspecialchars($task['title']) ?></h1>
                    
                    <div class="hero-meta">
                        <div class="meta-item">
                            <i class="fas fa-folder"></i>
                            <strong><?= htmlspecialchars($task['project_name'] ?? 'General') ?></strong>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar-alt"></i>
                            Created <?= date('M d, Y', strtotime($task['created_at'])) ?>
                        </div>
                    </div>

                    <div class="status-timeline">
                        <div class="status-progress"></div>
                    </div>
                </div>

                <div class="glass-card">
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-heading"></i> Task Title</label>
                            <input type="text" name="title" required class="modern-input" value="<?= htmlspecialchars($task['title']) ?>">
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label><i class="fas fa-project-diagram"></i> Project</label>
                                <select name="project_id" class="modern-input">
                                    <option value="">General (No Project)</option>
                                    <?php foreach ($projects as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= $task['project_id'] == $p['id'] ? 'selected' : '' ?>>
                                            📁 <?= htmlspecialchars($p['project_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-tasks"></i> Status</label>
                                <select name="status" class="modern-input">
                                    <option value="pending" <?= $task['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="in_progress" <?= ($task['status'] === 'in_progress' || $task['status'] === 'in progress') ? 'selected' : '' ?>>In Progress</option>
                                    <option value="complete" <?= $task['status'] === 'complete' ? 'selected' : '' ?>>Complete</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label><i class="fas fa-user-circle"></i> Assignee</label>
                                <select name="assigned_to" class="modern-input">
                                    <option value="">— Unassigned —</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= $u['id'] ?>" <?= $task['assigned_to'] == $u['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($u['username']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                           <div class="form-group">
    <label><i class="fas fa-clock"></i> Due Date</label>
    <?php 
        // Format the date to YYYY-MM-DD so the browser input can read it
        $formatted_date = "";
        if (!empty($task['due_date'])) {
            $formatted_date = date('Y-m-d', strtotime($task['due_date']));
        }
    ?>
    <input type="date" 
           name="due_date" 
           class="modern-input" 
           value="<?= $formatted_date ?>">
</div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-align-left"></i> Description</label>
                            <textarea name="description" class="modern-input" style="min-height:140px; resize: none;"><?= htmlspecialchars($task['description'] ?? '') ?></textarea>
                        </div>

                        <div class="form-actions">
                            <a href="delete_task.php?id=<?= $task['id'] ?>" class="delete-btn" onclick="return confirm('Delete this task permanently?')">
                                <i class="fas fa-trash-alt"></i> Delete Task
                            </a>
                            <button type="submit" class="btn-update">
                                <i class="fas fa-check-circle"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

</body>
</html>