 <?php
include '../config/db.php';
requireLogin();
requireRole('admin');

// Session Security
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];
$activePage = 'projects'; 
$id = $_GET['id'] ?? null;

if (!$id) { header("Location: projects.php"); exit; }

// Fetch existing project data
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND created_by_admin = ?");
$stmt->execute([$id, $admin_id]);
$project = $stmt->fetch();

if (!$project) { die("Project not found."); }

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = $_POST['project_name'];
    $desc   = $_POST['description'];
    $plan   = $_POST['plan_type'];
    $status = $_POST['status']; // New field

    try {
        $update = $pdo->prepare("UPDATE projects SET project_name = ?, description = ?, plan_type = ?, status = ? WHERE id = ? AND created_by_admin = ?");
        $update->execute([$name, $desc, $plan, $status, $id, $admin_id]);
        header("Location: projects.php?status=updated");
        exit;
    } catch (PDOException $e) { 
        $error = "Update failed: " . $e->getMessage(); 
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Edit Project | TMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
    <link rel="stylesheet" href="../fontawesome/css/all.min.css" />

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --bg-body: #f8fafc;
            --sidebar-width: 16rem;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --white: #ffffff;
            --danger: #ef4444;
            --danger-light: #fef2f2;
            --danger-border: #fee2e2;
            --danger-dark: #991b1b;
            --danger-medium: #b91c1c;
            --radius: 24px;
            --radius-sm: 12px;
            --radius-md: 14px;
            --radius-lg: 18px;
            --deep-dark: #161e2d;
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-2xl: 2.5rem;
            --transition: 0.2s;
            --transition-lg: 0.3s;
            --box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        html, body {
            height: 100%;
            width: 100%;
            overflow-x: hidden;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-body);
            color: var(--text-main);
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        .layout-wrapper {
            display: flex;
            height: 100vh;
            width: 100%;
            overflow-x: hidden;
        }

        .sidebar-spacer {
            width: var(--sidebar-width);
            flex-shrink: 0;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow: hidden;
            width: 100%;
        }

        .scroll-area {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: var(--spacing-2xl);
            width: 100%;
        }

        .content-container {
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
        }

        /* Profile Banner */
        .profile-banner {
            background: var(--deep-dark);
            border-radius: var(--radius);
            padding: var(--spacing-lg) var(--spacing-xl);
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--spacing-xl);
            color: white;
            flex-wrap: wrap;
            gap: var(--spacing-lg);
        }

        .profile-info {
            display: flex;
            align-items: center;
            gap: var(--spacing-lg);
            flex: 1;
            min-width: 0;
        }

        .profile-avatar {
            width: 64px;
            height: 64px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .profile-avatar .material-symbols-outlined {
            font-size: 32px;
            color: #60a5fa;
        }

        .profile-text {
            min-width: 0;
        }

        .profile-text h1 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: var(--spacing-xs);
            color: white;
            line-height: 1.3;
        }

        .profile-text p {
            font-size: 13px;
            color: var(--text-muted);
            word-break: break-word;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: var(--transition);
            cursor: pointer;
            flex-shrink: 0;
            min-height: 40px;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .btn-back:active {
            background: rgba(255, 255, 255, 0.15);
        }

        .back-icon {
            font-size: 18px;
        }

        /* Form Card */
        .form-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--box-shadow);
            padding: var(--spacing-2xl);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--spacing-xl);
        }

        .full-width {
            grid-column: span 2;
        }

        /* Input Group */
        .input-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .input-group label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.05em;
            line-height: 1.4;
        }

        /* Form Input */
        .form-input {
            width: 100%;
            padding: 0.85rem 1.1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 14px;
            color: var(--text-main);
            transition: var(--transition);
            line-height: 1.5;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.05);
        }

        textarea.form-input {
            resize: vertical;
            min-height: 120px;
        }

        /* Select Custom */
        .select-custom {
            position: relative;
            display: flex;
            align-items: center;
        }

        .select-custom select {
            appearance: none;
            background: transparent;
            cursor: pointer;
            width: 100%;
            padding: 0.85rem 2.5rem 0.85rem 1.1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 14px;
            color: var(--text-main);
            transition: var(--transition);
            line-height: 1.5;
        }

        .select-custom select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.05);
        }

        .select-custom .icon {
            position: absolute;
            right: 12px;
            pointer-events: none;
            color: var(--text-muted);
            font-size: 20px;
        }

        /* Form Footer */
        .form-footer {
            margin-top: var(--spacing-2xl);
            padding-top: var(--spacing-lg);
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: var(--spacing-lg);
            flex-wrap: wrap;
        }

        .btn-save {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.85rem 2.5rem;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--transition-lg);
            min-height: 44px;
            line-height: 1.5;
        }

        .btn-save:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-save:active {
            transform: translateY(0);
        }

        .btn-save .material-symbols-outlined {
            font-size: 20px;
        }

        .btn-cancel {
            text-decoration: none;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 600;
            transition: var(--transition);
            line-height: 1.5;
        }

        .btn-cancel:hover {
            color: var(--text-main);
        }

        /* Danger Card */
        .danger-card {
            margin-top: var(--spacing-xl);
            padding: var(--spacing-lg) var(--spacing-xl);
            background: var(--danger-light);
            border: 1px solid var(--danger-border);
            border-radius: var(--radius);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--spacing-lg);
        }

        .danger-info {
            flex: 1;
            min-width: 200px;
        }

        .danger-info h3 {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            color: var(--danger-dark);
            line-height: 1.4;
        }

        .danger-info p {
            margin: var(--spacing-xs) 0 0;
            font-size: 12px;
            color: var(--danger-medium);
            opacity: 0.8;
            line-height: 1.4;
        }

        .btn-delete {
            background: var(--white);
            color: var(--danger);
            border: 1px solid #fecaca;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 40px;
            line-height: 1.5;
            flex-shrink: 0;
        }

        .btn-delete .material-symbols-outlined {
            font-size: 18px;
        }

        .btn-delete:hover {
            background: var(--danger);
            color: white;
            border-color: var(--danger);
        }

        .btn-delete:active {
            background: var(--danger);
        }

        /* Error Message */
        .error-msg {
            background: var(--danger-light);
            color: var(--danger-dark);
            padding: var(--spacing-md);
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-lg);
            font-size: 13px;
            font-weight: 600;
            border: 1px solid var(--danger-border);
            line-height: 1.5;
        }

        /* Responsive Styles (Omitted for brevity as they remain mostly the same, ensuring icons stay scaled) */
    </style>
</head>

<body>
    <div class="layout-wrapper">
        <div class="sidebar-spacer"><?php include '../includes/admin_sidebar.php'; ?></div>

        <div class="wrapper">
                <?php include '../includes/admin_header.php'; ?>

            <main class="scroll-area">
                <div class="content-container">

                    <div class="profile-banner">
                        <div class="profile-info">
                            <div class="profile-avatar">
                                <span class="material-symbols-outlined">edit_note</span>
                            </div>
                            <div class="profile-text">
                                <h1>Modify Project</h1>
                                <p>Editing: <?= htmlspecialchars($project['project_name']) ?></p>
                            </div>
                        </div>
                        <a href="projects.php" class="btn-back">
                            <span class="material-symbols-outlined back-icon">arrow_back_ios_new</span> Back
                        </a>
                    </div>

                    <div class="form-card">
                        <?php if(isset($error)): ?>
                            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="form-grid">
                                <div class="input-group full-width">
                                    <label>Project Name</label>
                                    <input type="text" name="project_name" 
                                           value="<?= htmlspecialchars($project['project_name']) ?>" 
                                           class="form-input" required>
                                </div>

                                <div class="input-group full-width">
                                    <label>Description</label>
                                    <textarea name="description" class="form-input"><?= htmlspecialchars($project['description']) ?></textarea>
                                </div>

                                <div class="input-group">
                                    <label>Plan Type</label>
                                    <div class="select-custom">
                                        <select name="plan_type">
                                            <option value="lite" <?= $project['plan_type'] == 'lite' ? 'selected' : '' ?>>Lite Plan</option>
                                            <option value="plus" <?= $project['plan_type'] == 'plus' ? 'selected' : '' ?>>Plus Plan</option>
                                            <option value="elite" <?= $project['plan_type'] == 'elite' ? 'selected' : '' ?>>Elite Plan</option>
                                        </select>
                                        <span class="material-symbols-outlined icon">keyboard_arrow_down</span>
                                    </div>
                                </div>

                                <div class="input-group">
                                    <label>Project Status</label>
                                    <div class="select-custom">
                                        <select name="status">
                                            <option value="active" <?= $project['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="on_hold" <?= $project['status'] == 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                                            <option value="complete" <?= $project['status'] == 'complete' ? 'selected' : '' ?>>Complete</option>
                                        </select>
                                        <span class="material-symbols-outlined icon">keyboard_arrow_down</span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-footer">
                                <a href="projects.php" class="btn-cancel">Cancel</a>
                                <button type="submit" class="btn-save">
                                    <span class="material-symbols-outlined">check_circle</span>
                                    Save Project Changes
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="danger-card">
                        <div class="danger-info">
                            <h3>Delete Project</h3>
                            <p>This action is permanent and will delete all project data.</p>
                        </div>
                        <a href="projects.php?delete_id=<?= $project['id'] ?>" 
                            onclick="return confirm('Delete this project forever?')" 
                            class="btn-delete">
                            <span class="material-symbols-outlined">delete_forever</span>
                            Delete Project
                        </a>
                    </div>

                </div>
            </main>
        </div>
    </div>
</body>
</html>