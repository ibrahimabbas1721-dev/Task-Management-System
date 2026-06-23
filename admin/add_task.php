<?php
include '../config/db.php';
requireLogin(); 
requireRole('admin');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$error = '';
$admin_id = $_SESSION['user_id'];

try {
    // 1. Fetch Projects owned by THIS admin
    $stmtProj = $pdo->prepare("
        SELECT id, project_name 
        FROM projects 
        WHERE created_by_admin = ? 
        ORDER BY project_name ASC
    ");
    $stmtProj->execute([$admin_id]);
    $projects = $stmtProj->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Users specifically linked to THIS admin
    // We filter by role AND the admin who created them
    $stmtUser = $pdo->prepare("
        SELECT id, username, email 
        FROM users 
        WHERE role != 'admin' 
        AND created_by_admin = ? 
        ORDER BY username ASC
    ");
    $stmtUser->execute([$admin_id]);
    $users = $stmtUser->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database Fetch Error: " . $e->getMessage();
    $projects = [];
    $users = [];
}

// 3. HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $project_id  = !empty($_POST['project_id'])  ? (int)$_POST['project_id']  : null;
    $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
    $due_date    = !empty($_POST['due_date'])    ? $_POST['due_date']         : null;
    $status      = $_POST['status'] ?? 'pending';
    $tags        = (isset($_POST['tags']) && is_array($_POST['tags'])) 
                   ? implode(',', $_POST['tags']) : '';

    if (empty($title)) {
        $error = "Task title is required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO tasks 
                    (project_id, title, description, status, due_date, assigned_to, created_by_admin, tags)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$project_id, $title, $description, $status, $due_date, $assigned_to, $admin_id, $tags]);
            
            header("Location: all_tasks.php");
            exit;
        } catch (PDOException $e) {
            $error = "Insert Error: " . $e->getMessage();
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Create Task | TMS Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    
    <link rel="stylesheet" href="../fontawesome/css/all.min.css" />

    <style>
        :root {
            --light-bg: #f8fafc; --card-bg: #ffffff; --input-bg: #f1f5f9;
            --border-color: #e2e8f0; --text-primary: #1e293b; --text-secondary: #64748b;
            --accent-blue: #3b82f6; --accent-green: #10b981; --accent-yellow: #f59e0b;
            --dark-banner: #161e2d; --radius: 16px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--light-bg); color: var(--text-primary); display: flex; height: 100vh; overflow: hidden; }
        .sidebar-spacer { width: 260px; flex-shrink: 0; }
        .main-wrapper { flex: 1; display: flex; flex-direction: column; height: 100%; overflow: hidden; }
        .scroll-container { flex: 1; padding: 2rem; overflow-y: auto; }
        .content-limit { max-width: 1100px; margin: 0 auto; width: 100%; }

        /* Banner */
        .banner { background: var(--dark-banner); border-radius: 20px; padding: 1.5rem 2rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; color: white; }
        .banner-info { display: flex; align-items: center; gap: 1rem; }
        .banner-icon { width: 50px; height: 50px; background: rgba(255,255,255,.05); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--accent-blue); }
        .banner-icon i { font-size: 24px; } /* Style for Font Awesome */
        
        .btn-back { background: rgba(255,255,255,.05); color: white; border: 1px solid rgba(255,255,255,.1); padding: 8px 16px; border-radius: 10px; text-decoration: none; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px; transition: 0.3s; }
        .btn-back:hover { background: rgba(255,255,255,0.1); }

        /* Form Layout */
        .form-layout { display: grid; grid-template-columns: 1fr 350px; gap: 1.5rem; }
        .form-card { background: var(--card-bg); border-radius: var(--radius); border: 1px solid var(--border-color); padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
        .form-group { margin-bottom: 1.25rem; }
        .label-style { font-size: 12px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block; }
        .input-style { width: 100%; padding: 12px 16px; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 10px; font-size: 14px; outline: none; transition: 0.2s; font-family: inherit; }
        .input-style:focus { background: #fff; border-color: var(--accent-blue); box-shadow: 0 0 0 4px rgba(59,130,246,0.1); }

        /* Status Toggle */
        .status-group { display: flex; gap: 8px; }
        .status-option { flex: 1; position: relative; }
        .status-option input { position: absolute; opacity: 0; cursor: pointer; }
        .status-option label { display: block; padding: 10px; text-align: center; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; color: var(--text-secondary); transition: 0.2s; }
        .status-option input:checked + label { background: var(--accent-blue); color: white; border-color: var(--accent-blue); }

        /* Tags */
        .tags-container { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
        .tag-item { padding: 6px 12px; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 6px; font-size: 12px; display: flex; align-items: center; gap: 6px; cursor: pointer; font-weight: 600; }
        .tag-item input { accent-color: var(--accent-blue); }

        .btn-submit { background: var(--accent-blue); color: white; border: none; padding: 14px 28px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; font-family: inherit; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-submit:hover { opacity: 0.9; transform: translateY(-1px); background: #2563eb; }
        
        .empty-notice { padding: 10px; background: #fffbeb; border: 1px dashed #f59e0b; border-radius: 8px; font-size: 12px; color: #92400e; margin-top: 8px; }

        @media (max-width: 900px) { .form-layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="sidebar-spacer"><?php include '../includes/admin_sidebar.php'; ?></div>

    <div class="main-wrapper">
        <?php include '../includes/admin_header.php'; ?>

        <main class="scroll-container">
            <div class="content-limit">
                <div class="banner">
                    <div class="banner-info">
                        <div class="banner-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div>
                            <h1>Create New Task</h1>
                            <p style="font-size: 13px; color: #94a3b8;">Add a new deliverable to your workspace</p>
                        </div>
                    </div>
                    <a href="all_tasks.php" class="btn-back"><i class="fas fa-long-arrow-alt-left"></i> Back</a>
                </div>

                <?php if ($error): ?>
                    <div style="background:#fee2e2; color:#b91c1c; padding:1rem; border-radius:12px; margin-bottom:1.5rem; font-size:14px; font-weight:600; border: 1px solid #fecaca;">
                      <i class="fas fa-arrow-left"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-layout">
                        <div class="left-col">
                            <div class="form-card">
                                <div class="form-group">
                                    <label class="label-style">Task Title</label>
                                    <input type="text" name="title" required class="input-style" placeholder="e.g. Design System Update" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="label-style">Description</label>
                                    <textarea name="description" class="input-style" style="min-height:150px;" placeholder="Outline the task requirements..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <div class="form-card">
                                <label class="label-style">Tags</label>
                                <div class="tags-container" id="tagsContainer">
                                    <?php foreach (['Frontend','Backend','Design','Bug','Feature'] as $tag): ?>
                                        <label class="tag-item">
                                            <input type="checkbox" name="tags[]" value="<?= $tag ?>"> <?= $tag ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div style="display:flex; gap:8px;">
                                    <input type="text" id="newTagInput" class="input-style" style="padding:8px 12px" placeholder="New tag...">
                                    <button type="button" onclick="addNewTag()" class="input-style" style="width:auto; cursor:pointer; font-weight:700;">Add</button>
                                </div>
                            </div>
                        </div>

                        <div class="right-col">
                            <div class="form-card">
                                <label class="label-style">Status</label>
                                <div class="status-group">
                                    <div class="status-option">
                                        <input type="radio" name="status" value="pending" id="s1" checked>
                                        <label for="s1">Pending</label>
                                    </div>
                                    <div class="status-option">
                                        <input type="radio" name="status" value="in_progress" id="s2">
                                        <label for="s2">Active</label>
                                    </div>
                                    <div class="status-option">
                                        <input type="radio" name="status" value="complete" id="s3">
                                        <label for="s3">Done</label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-card">
                                <div class="form-group">
                                    <label class="label-style">Assignee</label>
                                    <select name="assigned_to" class="input-style">
                                        <option value="">— Unassigned —</option>
                                        <?php foreach ($users as $u): ?>
                                            <option value="<?= $u['id'] ?>" <?= (isset($_POST['assigned_to']) && $_POST['assigned_to'] == $u['id']) ? 'selected' : '' ?>>
                                                👤 <?= htmlspecialchars($u['username']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($users)): ?>
                                        <div class="empty-notice">No registered users found.</div>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="label-style">Project</label>
                                    <select name="project_id" class="input-style">
                                        <option value="">General Task</option>
                                        <?php foreach ($projects as $p): ?>
                                            <option value="<?= $p['id'] ?>" <?= (isset($_POST['project_id']) && $_POST['project_id'] == $p['id']) ? 'selected' : '' ?>>
                                                📁 <?= htmlspecialchars($p['project_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="label-style">Due Date</label>
                                    <input type="date" name="due_date" class="input-style" value="<?= htmlspecialchars($_POST['due_date'] ?? '') ?>">
                                </div>
                            </div>

                            <div style="display:flex; flex-direction:column; gap:10px;">
                                <button type="submit" class="btn-submit">
                               <i class="fas fa-plus-circle"></i> Create Task
                                </button>
                                <a href="all_tasks.php" style="text-align:center; color:var(--text-secondary); text-decoration:none; font-size:13px; font-weight:600;">Cancel</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        function addNewTag() {
            const input = document.getElementById('newTagInput');
            const container = document.getElementById('tagsContainer');
            const val = input.value.trim();
            if (!val) return;

            const lbl = document.createElement('label');
            lbl.className = 'tag-item';
            lbl.innerHTML = `<input type="checkbox" name="tags[]" value="${val}" checked> ${val}`;
            container.appendChild(lbl);
            input.value = '';
        }
    </script>
</body>
</html>