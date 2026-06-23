<?php
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$adminId = $_SESSION['user_id'];
$grouped_tasks = [];
$project = null;

if ($projectId <= 0) {
    header('Location: projects.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ajax_update'])) {
        $taskId = (int)$_POST['task_id'];
        $status = $_POST['status'];
        $pdo->prepare('UPDATE tasks SET status = ? WHERE id = ? AND project_id = ?')
            ->execute([$status, $taskId, $projectId]);
        exit;
    }

    if (isset($_POST['quick_add'])) {
        $title = trim($_POST['title']);
        $groupName = $_POST['group_name'];
        $pdo->prepare('INSERT INTO tasks (title, project_id, group_name, status, created_by_admin) VALUES (?, ?, ?, "pending", ?)')
            ->execute([$title, $projectId, $groupName, $adminId]);
        echo json_encode(['success' => true]);
        exit;
    }

    if (isset($_POST['edit_task'])) {
        $taskId = (int)$_POST['task_id'];
        $newTitle = trim($_POST['new_title']);
        $pdo->prepare('UPDATE tasks SET title = ? WHERE id = ?')
            ->execute([$newTitle, $taskId]);
        exit;
    }

    if (isset($_POST['delete_task'])) {
        $taskId = (int)$_POST['task_id'];
        $pdo->prepare('DELETE FROM tasks WHERE id = ?')
            ->execute([$taskId]);
        exit;
    }

    if (isset($_POST['delete_group'])) {
        $groupName = $_POST['group_name'];
        $pdo->prepare('DELETE FROM tasks WHERE group_name = ? AND project_id = ?')
            ->execute([$groupName, $projectId]);
        exit;
    }

    if (isset($_POST['rename_group'])) {
        $oldGroup = $_POST['old_group_name'];
        $newGroup = trim($_POST['new_group_name']);
        if ($newGroup !== '') {
            $pdo->prepare('UPDATE tasks SET group_name = ? WHERE group_name = ? AND project_id = ?')
                ->execute([$newGroup, $oldGroup, $projectId]);
        }
        exit;
    }
}

try {
    $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = ? AND created_by_admin = ?');
    $stmt->execute([$projectId, $adminId]);
    $project = $stmt->fetch();
    if (!$project) {
        die('Access Denied.');
    }

    $stmt = $pdo->prepare(
        'SELECT t.*, u.username AS assignee_name
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.project_id = ?
        ORDER BY t.group_name ASC,
        CASE
            WHEN t.status = "in_progress" THEN 1
            WHEN t.status = "pending" THEN 2
            WHEN t.status = "complete" THEN 3
            ELSE 4
        END ASC,
        t.id DESC'
    );
    $stmt->execute([$projectId]);
    $all_tasks_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $complete_count = 0;
    foreach ($all_tasks_raw as $tsk) {
        if ($tsk['status'] === 'complete') {
            $complete_count++;
        }
        $grp = !empty($tsk['group_name']) ? $tsk['group_name'] : 'General Tasks';
        $grouped_tasks[$grp][] = $tsk;
    }

    $progress_percent = count($all_tasks_raw) > 0 ? round(($complete_count / count($all_tasks_raw)) * 100) : 0;
} catch (PDOException $ex) {
    die('DB Error: ' . $ex->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($project['project_name'] ?? 'Project'); ?> | TMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../fontawesome/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg: #f8fafc;
            --surface: #ffffff;
            --border: #e2e8f0;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }

        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text-dark); display: flex; height: 100vh; overflow: hidden; }

        .layout-wrapper { display: flex; height: 100vh; width: 100%; }
        .sidebar-space { width: 16rem; flex-shrink: 0; border-right: 1px solid var(--border); }
        .main-container { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

        .content-area { flex: 1; overflow-y: auto; padding: 1.5rem; scroll-behavior: smooth; }
        .container { max-width: 1600px; margin: 0 auto; }

        /* ── PAGE HEADER ── */
        .page-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            color: white;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        .header-top { display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 1; }
        .header-left { display: flex; align-items: center; gap: 1.25rem; }
        .project-icon {
            width: 64px; height: 64px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; font-weight: 800; color: var(--primary-light);
        }
        .header-actions { display: flex; gap: 10px; align-items: center; }

        /* ── BUTTONS ── */
        .btn-primary, .btn-secondary {
            padding: 10px 18px; border-radius: 12px; font-size: 14px; font-weight: 600;
            display: inline-flex; align-items: center; gap: 8px; border: none;
            cursor: pointer; transition: all 0.2s ease; text-decoration: none;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); }
        .btn-secondary { background: rgba(255, 255, 255, 0.1); color: white; border: 1px solid rgba(255,255,255,0.1); }
        .btn-secondary:hover { background: rgba(255, 255, 255, 0.2); }

        /* ── STATS ── */
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 2.5rem; }
        .stat-box { background: white; border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem; }
        .stat-label { color: var(--text-muted); font-size: 13px; font-weight: 600; text-transform: uppercase; }
        .stat-value { font-size: 32px; font-weight: 800; margin-top: 4px; display: block; }
        .progress-bar { width: 100%; height: 8px; background: #f1f5f9; border-radius: 10px; overflow: hidden; margin-top: 12px; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary), var(--primary-light)); transition: width 1s ease; }

        /* ── KANBAN ── */
        .kanban-board { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; align-items: start; }
        .kanban-column { background: #f1f5f9; border-radius: 20px; padding: 1rem; min-height: 200px; display: flex; flex-direction: column; }

        .column-header { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.5rem 1rem 0.5rem; }
        .column-title { font-weight: 700; font-size: 15px; display: flex; align-items: center; gap: 8px; }
        .column-count { background: white; padding: 2px 8px; border-radius: 20px; font-size: 12px; color: var(--text-muted); }

        /* ── TASK CARD ── */
        .task-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            position: relative;
            box-shadow: var(--shadow-sm);
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }
        .task-card:hover { box-shadow: 0 8px 20px -4px rgba(0,0,0,0.1); transform: translateY(-1px); }
        .task-card.complete { opacity: 0.7; background: #f8fafc; }
        .task-card.complete .task-title { text-decoration: line-through; color: var(--text-muted); }

        /* Clickable task title link */
        .task-title-link {
            text-decoration: none;
            color: inherit;
            display: block;
            flex: 1;
            min-width: 0;
        }
        .task-title-link:hover .task-title { color: var(--primary); }
        .task-title {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-dark);
            transition: color 0.15s;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .task-card.complete .task-title-link:hover .task-title { color: var(--text-muted); }

        /* ── TASK DROPDOWN ── */
        .task-dropdown {
            position: absolute; right: 10px; top: 45px; width: 200px;
            background: white; border: 1px solid var(--border); border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); padding: 8px;
            display: none; z-index: 100;
        }
        .task-dropdown.active { display: block; }

        .dropdown-item {
            width: 100%; padding: 10px 12px; border: none; background: none;
            text-align: left; cursor: pointer; display: flex; align-items: center;
            gap: 10px; border-radius: 8px; font-size: 14px; color: var(--text-dark);
            font-family: inherit;
        }
        .dropdown-item:hover { background: #f1f5f9; }
        .dropdown-item i { width: 18px; color: var(--text-muted); text-align: center; }

        /* ── STATUS DOTS ── */
        .status-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .dot-complete { background: var(--success); }
        .dot-progress { background: var(--warning); }
        .dot-pending { background: #cbd5e1; }

        /* ── QUICK ADD ── */
        .quick-add-form { background: white; border-radius: 12px; padding: 4px; display: flex; gap: 4px; margin-top: 0.5rem; border: 1px solid var(--border); }
        .quick-input { flex: 1; padding: 10px 12px; border: none; outline: none; font-size: 14px; font-family: inherit; }
        .btn-add-icon { background: var(--primary); color: white; border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .btn-add-icon:hover { background: var(--primary-dark); }

        /* ── MISC ── */
        .badge-user { background: #eff6ff; color: #1d4ed8; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .btn-icon-sm { border: none; background: transparent; cursor: pointer; padding: 4px; border-radius: 6px; }
        .btn-icon-sm:hover { background: rgba(0,0,0,0.05); }

        /* ── VIEW TASK BUTTON inside dropdown ── */
        .dropdown-item.view-task-item { color: var(--primary); }
        .dropdown-item.view-task-item i { color: var(--primary); }
    </style>
</head>
<body>
<div class="layout-wrapper">
    <div class="sidebar-space"><?php include '../includes/admin_sidebar.php'; ?></div>

    <div class="main-container">
        <?php include '../includes/admin_header.php'; ?>

        <main class="content-area">
            <div class="container">

                <!-- PAGE HEADER -->
                <div class="page-header">
                    <div class="header-top">
                        <div class="header-left">
                            <div class="project-icon"><?= strtoupper(substr($project['project_name'] ?? 'P', 0, 1)); ?></div>
                            <div class="header-title">
                                <h1 style="font-size:28px; font-weight:800;"><?= htmlspecialchars($project['project_name'] ?? 'Project Overview'); ?></h1>
                                <p style="opacity:0.8; font-size:14px; margin-top:4px;">
                                    <i class="far fa-calendar-alt" style="margin-right:5px;"></i>
                                    <?= htmlspecialchars($project['plan_type'] ?? 'Standard'); ?> Plan &bull; <?= count($all_tasks_raw) ?> Total Tasks
                                </p>
                            </div>
                        </div>
                        <div class="header-actions">
                            <button class="btn-secondary" onclick="addNewGroup()">
                                <i class="fas fa-folder-plus"></i> New Column
                            </button>
                            <a href="add_task.php?project_id=<?= $projectId; ?>" class="btn-primary">
                                <i class="fas fa-plus"></i> Create Task
                            </a>
                        </div>
                    </div>
                </div>

                <!-- STATS -->
                <div class="stats-row">
                    <div class="stat-box">
                        <span class="stat-label">Project Load</span>
                        <span class="stat-value"><?= count($all_tasks_raw) ?></span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-label">Resolved</span>
                        <span class="stat-value" style="color:var(--success);"><?= $complete_count ?></span>
                    </div>
                    <div class="stat-box">
                        <div style="display:flex; justify-content:space-between; align-items:center">
                            <span class="stat-label">Completion</span>
                            <span style="font-weight:700; color:var(--primary);"><?= $progress_percent ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width:<?= $progress_percent ?>%;"></div>
                        </div>
                    </div>
                </div>

                <!-- KANBAN BOARD -->
                <div class="kanban-board">
                    <?php foreach ($grouped_tasks as $group_name => $tasks): $gid = md5($group_name); ?>
                        <div class="kanban-column">
                            <div class="column-header">
                                <div class="column-title">
                                    <?= htmlspecialchars($group_name); ?>
                                    <span class="column-count"><?= count($tasks) ?></span>
                                </div>
                                <div style="display:flex; gap:4px;">
                                    <button class="btn-icon-sm" title="Rename column" onclick="editGroup('<?= addslashes($group_name); ?>')">
                                        <i class="fas fa-edit" style="color:var(--text-muted);"></i>
                                    </button>
                                    <button class="btn-icon-sm" title="Delete column" onclick="deleteGroup('<?= addslashes($group_name); ?>')">
                                        <i class="fas fa-trash-alt" style="color:var(--danger);"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="tasks-list">
                                <?php foreach ($tasks as $task):
                                    $is_c      = ($task['status'] === 'complete');
                                    $is_p      = ($task['status'] === 'in_progress');
                                    $dot_class = $is_c ? 'dot-complete' : ($is_p ? 'dot-progress' : 'dot-pending');
                                    $view_url  = 'view_task.php?id=' . (int)$task['id'];
                                ?>
                                    <div class="task-card <?= $is_c ? 'complete' : ''; ?>">

                                        <!-- TOP ROW: title link + menu button -->
                                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                                            <a href="<?= $view_url ?>" class="task-title-link" title="View task details">
                                                <span class="task-title"><?= htmlspecialchars($task['title']); ?></span>
                                            </a>
                                            <button onclick="toggleTaskMenu(event, <?= $task['id']; ?>)"
                                                    style="border:none; background:none; cursor:pointer; color:var(--text-muted); flex-shrink:0; padding:2px 4px;">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                        </div>

                                        <!-- DROPDOWN MENU -->
                                        <div class="task-dropdown" id="menu-<?= $task['id']; ?>">
                                            <a href="<?= $view_url ?>" class="dropdown-item view-task-item">
                                                <i class="fas fa-eye"></i> View Task
                                            </a>
                                            <div style="height:1px; background:var(--border); margin:4px 0;"></div>
                                            <button class="dropdown-item" onclick="updateStatus(<?= $task['id']; ?>, 'pending')">
                                                <i class="far fa-clock"></i> Move to Pending
                                            </button>
                                            <button class="dropdown-item" onclick="updateStatus(<?= $task['id']; ?>, 'in_progress')">
                                                <i class="fas fa-spinner"></i> In Progress
                                            </button>
                                            <button class="dropdown-item" onclick="updateStatus(<?= $task['id']; ?>, 'complete')">
                                                <i class="far fa-check-circle"></i> Mark Complete
                                            </button>
                                            <div style="height:1px; background:var(--border); margin:4px 0;"></div>
                                            <button class="dropdown-item" onclick="editTask(<?= $task['id']; ?>, '<?= addslashes($task['title']); ?>')">
                                                <i class="fas fa-pen-nib"></i> Rename
                                            </button>
                                            <button class="dropdown-item" onclick="deleteTask(<?= $task['id']; ?>)" style="color:var(--danger);">
                                                <i class="fas fa-trash"></i> Delete Task
                                            </button>
                                        </div>

                                        <!-- BOTTOM ROW: assignee + status dot -->
                                        <div style="margin-top:16px; display:flex; justify-content:space-between; align-items:center;">
                                            <div class="badge-user">
                                                <i class="fas fa-user-circle"></i>
                                                <?= htmlspecialchars($task['assignee_name'] ?? 'Unassigned') ?>
                                            </div>
                                            <span class="status-dot <?= $dot_class ?>"></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- QUICK ADD -->
                            <div class="quick-add-form">
                                <input type="text" id="q-<?= $gid; ?>" class="quick-input" placeholder="New task..."
                                       onkeydown="if(event.key==='Enter') quickAdd('<?= $gid; ?>','<?= addslashes($group_name); ?>')">
                                <button class="btn-add-icon" onclick="quickAdd('<?= $gid; ?>','<?= addslashes($group_name); ?>')">
                                    <i class="fas fa-plus-circle"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div><!-- /container -->
        </main>
    </div>
</div>

<script>
    /* ── DROPDOWN TOGGLE ── */
    function toggleTaskMenu(e, id) {
        e.stopPropagation();
        e.preventDefault();
        const menu = document.getElementById('menu-' + id);
        const wasActive = menu.classList.contains('active');
        document.querySelectorAll('.task-dropdown').forEach(m => m.classList.remove('active'));
        if (!wasActive) menu.classList.add('active');
    }
    window.addEventListener('click', () => {
        document.querySelectorAll('.task-dropdown').forEach(m => m.classList.remove('active'));
    });

    /* ── STATUS UPDATE ── */
    function updateStatus(id, stat) {
        const fd = new FormData();
        fd.append('task_id', id);
        fd.append('status', stat);
        fd.append('ajax_update', '1');
        fetch(window.location.href, { method: 'POST', body: fd })
            .then(() => location.reload());
    }

    /* ── QUICK ADD ── */
    function quickAdd(gid, group, title = null) {
        const val = title || document.getElementById('q-' + gid).value.trim();
        if (!val) return;
        const fd = new FormData();
        fd.append('quick_add', '1');
        fd.append('title', val);
        fd.append('group_name', group);
        fetch(window.location.href, { method: 'POST', body: fd })
            .then(() => location.reload());
    }

    /* ── ADD NEW GROUP ── */
    function addNewGroup() {
        const name = prompt('Column Name:');
        if (name && name.trim()) quickAdd(null, name.trim(), 'Initialization Task');
    }

    /* ── RENAME TASK ── */
    function editTask(id, old) {
        const t = prompt('Rename Task:', old);
        if (t && t.trim() !== old) {
            const fd = new FormData();
            fd.append('edit_task', '1');
            fd.append('task_id', id);
            fd.append('new_title', t.trim());
            fetch(window.location.href, { method: 'POST', body: fd })
                .then(() => location.reload());
        }
    }

    /* ── DELETE TASK ── */
    function deleteTask(id) {
        if (confirm('Delete this task permanently?')) {
            const fd = new FormData();
            fd.append('delete_task', '1');
            fd.append('task_id', id);
            fetch(window.location.href, { method: 'POST', body: fd })
                .then(() => location.reload());
        }
    }

    /* ── DELETE GROUP ── */
    function deleteGroup(name) {
        if (confirm('Delete column and ALL tasks inside it?')) {
            const fd = new FormData();
            fd.append('delete_group', '1');
            fd.append('group_name', name);
            fetch(window.location.href, { method: 'POST', body: fd })
                .then(() => location.reload());
        }
    }

    /* ── RENAME GROUP ── */
    function editGroup(old) {
        const n = prompt('Rename Column:', old);
        if (n && n.trim() !== old) {
            const fd = new FormData();
            fd.append('rename_group', '1');
            fd.append('old_group_name', old);
            fd.append('new_group_name', n.trim());
            fetch(window.location.href, { method: 'POST', body: fd })
                .then(() => location.reload());
        }
    }
</script>
</body>
</html>