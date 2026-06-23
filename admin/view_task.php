<?php
include '../config/db.php';
requireLogin();
requireRole('admin');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];
$task_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$task_id) {
    header("Location: " . ($role === 'admin' ? 'view_tasks.php' : 'my_tasks.php'));
    exit;
}

function getTaskDetails($pdo, $id) {
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, p.project_name, u.username as assignee_name
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

$task = getTaskDetails($pdo, $task_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'admin') {
    $title       = $_POST['title'];
    $status      = $_POST['status'];
    $description = $_POST['description'];
    $file_path   = $task['attachment'];

    if (!empty($_FILES['attachment']['name'])) {
        $target_dir = "../uploads/attachments/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $file_name   = time() . '_' . basename($_FILES["attachment"]["name"]);
        $target_file = $target_dir . $file_name;
        if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
            $file_path = $file_name;
        }
    }

    $update = $pdo->prepare("UPDATE tasks SET title=?, status=?, description=?, attachment=? WHERE id=?");
    if ($update->execute([$title, $status, $description, $file_path, $task_id])) {
        $task = getTaskDetails($pdo, $task_id);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Edit Protocol #<?= $task['id'] ?> | TMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="../fontawesome/css/all.min.css"/>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sidebar-width: 260px;
            --indigo:        #6366f1;
            --indigo-light:  #ede9fe;
            --indigo-text:   #4338ca;
            --slate-900:     #0d1117;
            --slate-800:     #1e293b;
            --slate-700:     #334155;
            --slate-500:     #64748b;
            --slate-400:     #94a3b8;
            --slate-200:     #e2e8f0;
            --slate-100:     #f1f5f9;
            --slate-50:      #f8fafc;
            --white:         #ffffff;
            --page-bg:       #f4f6f9;
            --radius-sm:     8px;
            --radius-md:     10px;
            --radius-lg:     12px;
            --transition:    0.18s ease;
        }

        html, body { height: 100%; overflow-x: hidden; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--page-bg);
            color: var(--slate-800);
            display: flex;
        }

        .layout-wrapper { display: flex; width: 100%; min-height: 100vh; }

        .sidebar-space { width: var(--sidebar-width); flex-shrink: 0; overflow-y: auto; }

        .main-wrapper { flex: 1; display: flex; flex-direction: column; min-width: 0; }

        /* ── Topbar ── */
        .topbar {
            background: var(--page-bg);
            border-bottom: 1px solid var(--slate-200);
            padding: 10px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            font-weight: 600;
            color: var(--slate-500);
        }

        .topbar-brand i { font-size: 15px; color: var(--indigo); }

        .topbar-live {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 600;
            color: #10b981;
        }

        .live-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #10b981;
            animation: blink 1.4s infinite;
        }

        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.2} }

        /* ── Dark Banner (unchanged from screenshot) ── */
        .dark-banner {
            background: #0d1117;
            border-radius: 14px;
            margin: 20px 28px 0;
            padding: 22px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            border: 1.5px solid #1e3a5f;
        }

        .banner-tag {
            font-size: 10px;
            font-weight: 700;
            color: var(--indigo);
            letter-spacing: 2.5px;
            text-transform: uppercase;
            font-family: monospace;
            margin-bottom: 6px;
        }

        .banner-title {
            font-size: 22px;
            font-weight: 700;
            color: #f1f5f9;
            margin: 0;
        }

        .banner-title em { color: #818cf8; font-style: normal; }

        .btn-return {
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.13);
            color: #94a3b8;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: var(--transition);
        }

        .btn-return:hover { background: rgba(255,255,255,.11); color: #f1f5f9; }

        /* ── Page body ── */
        .content-scroll { flex: 1; overflow-y: auto; }

        .page-body {
            display: grid;
            grid-template-columns: 230px minmax(0, 1fr);
            gap: 18px;
            padding: 22px 28px 32px;
            max-width: 1060px;
            margin: 0 auto;
            width: 100%;
        }

        /* ── Cards ── */
        .card {
            background: var(--white);
            border: 0.5px solid var(--slate-200);
            border-radius: var(--radius-lg);
            padding: 16px 18px;
        }

        .card-label {
            font-size: 10px;
            font-weight: 700;
            color: var(--slate-400);
            letter-spacing: 1.5px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 11px;
        }

        .card-label i { font-size: 13px; }

        .divider { height: 0.5px; background: var(--slate-100); margin-bottom: 12px; }

        .meta-key {
            font-size: 10px;
            font-weight: 600;
            color: var(--slate-400);
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .meta-val {
            font-size: 13px;
            font-weight: 500;
            color: var(--slate-700);
            display: flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 11px;
        }

        .avatar-sm {
            width: 24px; height: 24px;
            border-radius: 50%;
            background: var(--indigo-light);
            color: var(--indigo-text);
            font-size: 9px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .status-select {
            width: 100%;
            background: var(--slate-50);
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-sm);
            color: var(--slate-700);
            font-size: 12px;
            font-weight: 500;
            font-family: inherit;
            padding: 8px 10px;
            outline: none;
            appearance: none;
            cursor: pointer;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            margin-top: 8px;
        }

        .badge.pending  { background: #fef9c3; color: #854d0e; }
        .badge.progress { background: var(--indigo-light); color: var(--indigo-text); }
        .badge.complete { background: #dcfce7; color: #166534; }
        .badge-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        .attach-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f0f9ff;
            border: 0.5px solid #bae6fd;
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 12px;
            font-weight: 500;
            color: #0369a1;
            text-decoration: none;
            transition: var(--transition);
            word-break: break-all;
        }

        .attach-chip:hover { background: #e0f2fe; }
        .attach-chip i { font-size: 14px; flex-shrink: 0; }
        .attach-none { font-size: 12px; color: #cbd5e1; font-style: italic; }

        /* ── Sidebar ── */
        .sidebar-col { display: flex; flex-direction: column; gap: 12px; }

        /* ── Main col ── */
        .main-col { display: flex; flex-direction: column; gap: 13px; }

        .field-block {
            background: var(--white);
            border: 0.5px solid var(--slate-200);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
        }

        .field-header {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 12px;
        }

        .field-step {
            width: 21px; height: 21px;
            border-radius: 50%;
            background: var(--indigo);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .field-title {
            font-size: 10px;
            font-weight: 700;
            color: var(--slate-500);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .input-field {
            width: 100%;
            background: var(--slate-50);
            border: 1.5px solid var(--slate-200);
            border-radius: var(--radius-sm);
            color: var(--slate-800);
            font-size: 13px;
            font-family: inherit;
            padding: 10px 13px;
            outline: none;
            transition: var(--transition);
        }

        .input-field:focus {
            border-color: var(--indigo);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(99,102,241,.1);
        }

        .textarea-field {
            width: 100%;
            background: var(--slate-50);
            border: 1.5px solid var(--slate-200);
            border-radius: var(--radius-sm);
            color: var(--slate-700);
            font-size: 13px;
            font-family: inherit;
            padding: 12px 14px;
            outline: none;
            min-height: 120px;
            resize: none;
            line-height: 1.75;
            transition: var(--transition);
        }

        .textarea-field:focus {
            border-color: var(--indigo);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(99,102,241,.1);
        }

        .upload-zone {
            border: 1.5px dashed var(--slate-200);
            border-radius: var(--radius-md);
            padding: 22px 16px;
            text-align: center;
            cursor: pointer;
            background: #fafbff;
            transition: var(--transition);
            display: block;
        }

        .upload-zone:hover { border-color: var(--indigo); background: #f5f3ff; }
        .upload-zone input { display: none; }
        .upload-zone i { font-size: 26px; color: #a5b4fc; display: block; margin-bottom: 7px; }

        .upload-hint {
            font-size: 11px;
            color: var(--slate-400);
            font-weight: 500;
            letter-spacing: .5px;
            display: block;
        }

        .btn-commit {
            width: 100%;
            background: var(--slate-900);
            border: none;
            border-radius: var(--radius-md);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-family: inherit;
            padding: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            transition: var(--transition);
        }

        .btn-commit:hover { background: var(--indigo); }
        .btn-commit:active { transform: scale(0.99); }
        .btn-commit i { font-size: 14px; }

        @media (max-width: 720px) {
            .page-body { grid-template-columns: 1fr; padding: 16px; }
            .dark-banner { margin: 14px 16px 0; }
        }
    </style>
</head>
<body>
<div class="layout-wrapper">
    <div class="sidebar-space"><?php include '../includes/admin_sidebar.php'; ?></div>

    <div class="main-wrapper">

        <?php include '../includes/admin_header.php'; ?>

        <div class="content-scroll">

            <!-- Dark Banner — kept exactly as screenshot -->
            <div class="dark-banner">
                <div>
                    <div class="banner-tag">&gt;_Edit_Protocol_Access</div>
                    <div class="banner-title">Edit <em>Intelligence</em></div>
                </div>
                <a href="<?= $role === 'admin' ? 'all_tasks.php' : 'my_tasks.php' ?>" class="btn-return">
                    <i class="fas fa-arrow-left"></i> Return
                </a>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="page-body">

                    <!-- Sidebar -->
                    <div class="sidebar-col">

                        <div class="card">
                            <div class="card-label"><i class="fas fa-database"></i> System metadata</div>
                            <div class="divider"></div>
                            <div class="meta-key">Project ref</div>
                            <div class="meta-val"><?= htmlspecialchars($task['project_name'] ?? 'N/A') ?></div>
                            <div class="meta-key">Operative</div>
                            <div class="meta-val">
                                <div class="avatar-sm">
                                    <?= strtoupper(substr($task['assignee_name'] ?? 'U', 0, 2)) ?>
                                </div>
                                <?= htmlspecialchars($task['assignee_name'] ?? 'Unassigned') ?>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-label"><i class="fas fa-sliders-h"></i> Status protocol</div>
                            <select name="status" class="status-select">
                                <option value="pending"     <?= ($task['status'] ?? '') == 'pending'     ? 'selected' : '' ?>>Pending</option>
                                <option value="in progress" <?= ($task['status'] ?? '') == 'in progress' ? 'selected' : '' ?>>In progress</option>
                                <option value="complete"    <?= ($task['status'] ?? '') == 'complete'    ? 'selected' : '' ?>>Finalized</option>
                            </select>
                            <?php
                            $s   = $task['status'] ?? 'pending';
                            $cls = $s === 'in progress' ? 'progress' : ($s === 'complete' ? 'complete' : 'pending');
                            $lbl = $s === 'in progress' ? 'In progress' : ($s === 'complete' ? 'Finalized' : 'Pending');
                            ?>
                            <div class="badge <?= $cls ?>">
                                <div class="badge-dot"></div><?= $lbl ?>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-label"><i class="fas fa-paperclip"></i> Current attachment</div>
                            <?php
                            $att = $task['attachment'] ?? null;
                            if (!empty($att) && file_exists("../uploads/attachments/" . $att)): ?>
                                <a class="attach-chip" href="../uploads/attachments/<?= htmlspecialchars($att) ?>" target="_blank">
                                    <i class="fas fa-file-alt"></i>
                                    <?= htmlspecialchars($att) ?>
                                </a>
                            <?php else: ?>
                                <span class="attach-none">No files attached.</span>
                            <?php endif; ?>
                        </div>

                    </div>

                    <!-- Main -->
                    <div class="main-col">

                        <div class="field-block">
                            <div class="field-header">
                                <div class="field-step">1</div>
                                <div class="field-title">Protocol title</div>
                            </div>
                            <input type="text" name="title" class="input-field"
                                   value="<?= htmlspecialchars($task['title'] ?? '') ?>" required>
                        </div>

                        <div class="field-block">
                            <div class="field-header">
                                <div class="field-step">2</div>
                                <div class="field-title">Intelligence report</div>
                            </div>
                            <textarea name="description" class="textarea-field"><?= htmlspecialchars($task['description'] ?? '') ?></textarea>
                        </div>

                        <div class="field-block">
                            <div class="field-header">
                                <div class="field-step">3</div>
                                <div class="field-title">Upload new attachment</div>
                            </div>
                            <label class="upload-zone">
                                <input type="file" name="attachment"
                                       onchange="document.getElementById('fn').textContent = this.files[0].name">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span class="upload-hint" id="fn">Drop file or click to upload</span>
                            </label>
                        </div>

                        <?php if ($role === 'admin'): ?>
                            <button type="submit" class="btn-commit">
                                <i class="fas fa-save"></i> Commit changes
                            </button>
                        <?php endif; ?>

                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>