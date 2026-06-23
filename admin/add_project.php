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
$activePage = 'projects';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_name = trim($_POST['name']); 
    $description = trim($_POST['description']);
    $status = $_POST['status'] ?? 'active';
    $plan_type = $_POST['plan_type'] ?? 'LITE'; 
    $admin_id = $_SESSION['user_id']; 

    if (empty($project_name)) {
        $error = "Project name is required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO projects (project_name, description, status, plan_type, created_by_admin) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$project_name, $description, $status, $plan_type, $admin_id]);
            header("Location: projects.php?msg=initialized");
            exit;
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Initialize Project | TMS Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    
    <link rel="stylesheet" href="../fontawesome/css/all.min.css" />
    
    <style>
        :root {
            --deep-dark: #020617;
            --dark-card: #0f172a;
            --primary: #4f46e5;
            --bg: #f8fafc;
            --surface: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --radius-lg: 24px;
            --radius-md: 16px;
            
            --color-active: #4f46e5;
            --color-hold: #f59e0b;
            --color-elite: #0f172a;
            --color-lite: #0ea5e9;
            
            --error-bg: #fef2f2;
            --error-text: #991b1b;
            --error-border: #fee2e2;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .sidebar-spacer { width: 280px; flex-shrink: 0; }
        .main-container { flex: 1; display: flex; flex-direction: column; height: 100%; overflow: hidden; }
        .content-scroll { flex: 1; padding: 2rem; overflow-y: auto; }
        .form-container { max-width: 800px; margin: 0 auto; }

        /* Banner Updated */
        .page-banner {
            background: var(--deep-dark);
            border-radius: var(--radius-lg);
            padding: 2.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
            position: relative;
        }

        .banner-icon {
            width: 56px; height: 56px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            color: var(--primary);
        }

        /* Icon Fix */
        .banner-icon i { font-size: 24px; }

        .banner-text h1 { color: white; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.5px; }
        .banner-text p { color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; margin-top: 4px; letter-spacing: 1px; }

        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 2.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .full-width { grid-column: span 2; }

        .label-style { display: block; font-size: 10px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px; padding-left: 4px; }
        .input-style { width: 100%; background: #f8fafc; border: 1.5px solid var(--border); border-radius: var(--radius-md); padding: 14px 18px; color: var(--text-main); font-size: 14px; font-weight: 600; outline: none; transition: 0.2s; font-family: inherit; }
        .input-style:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }

        textarea.input-style { resize: none; min-height: 120px; }

        .radio-grid { display: flex; gap: 10px; }
        .radio-option { flex: 1; position: relative; cursor: pointer; border: 1.5px solid var(--border); border-radius: 14px; padding: 12px; text-align: center; transition: all 0.2s; }
        .radio-option input { display: none; }
        .radio-option span { font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--text-muted); }

        /* Themes */
        .theme-active:has(input:checked) { background: var(--color-active); border-color: var(--color-active); }
        .theme-hold:has(input:checked) { background: var(--color-hold); border-color: var(--color-hold); }
        .theme-elite:has(input:checked) { background: var(--color-elite); border-color: var(--color-elite); }
        .theme-lite:has(input:checked) { background: var(--color-lite); border-color: var(--color-lite); }
        .radio-option:has(input:checked) span { color: white; }

        .btn-row { margin-top: 2rem; display: flex; gap: 1rem; }
        .btn { flex: 1; padding: 16px; border-radius: 16px; font-weight: 800; font-size: 12px; text-transform: uppercase; cursor: pointer; border: none; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; font-family: inherit; text-decoration: none; }
        
        /* Submit Button Fix */
        .btn-primary { background: var(--deep-dark); color: white; }
        .btn-primary:hover { background: var(--primary); transform: translateY(-2px); }
        .btn-primary i { font-size: 14px; }

        .btn-cancel { background: #f1f5f9; color: var(--text-muted); }
        .btn-cancel:hover { background: #e2e8f0; color: var(--text-main); }
    </style>
</head>

<body>
    <div class="sidebar-spacer">
        <?php include '../includes/admin_sidebar.php'; ?>
    </div>

    <div class="main-container">
        <?php include '../includes/admin_header.php'; ?>

        <main class="content-scroll">
            <div class="form-container">
                
                <header class="page-banner">
                    <div class="banner-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div class="banner-text">
                        <h1>Initialize Project</h1>
                        <p>Create a new managed workspace for your team</p>
                    </div>
                </header>

                <?php if(isset($error) && $error): ?>
                    <div class="alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="card">
                        <div class="form-group">
                            <label class="label-style">Project Title</label>
                            <input type="text" name="name" required placeholder="Project name or code" class="input-style">
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="label-style">Initial Status</label>
                                <div class="radio-grid">
                                    <label class="radio-option theme-active">
                                        <input type="radio" name="status" value="active" checked>
                                        <span>Active</span>
                                    </label>
                                    <label class="radio-option theme-hold">
                                        <input type="radio" name="status" value="on_hold">
                                        <span>On Hold</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="label-style">Deployment Plan</label>
                                <div class="radio-grid">
                                    <label class="radio-option theme-elite">
                                        <input type="radio" name="plan_type" value="ELITE">
                                        <span>Elite</span>
                                    </label>
                                    <label class="radio-option theme-active">
                                        <input type="radio" name="plan_type" value="PLUS">
                                        <span>Plus</span>
                                    </label>
                                    <label class="radio-option theme-lite">
                                        <input type="radio" name="plan_type" value="LITE" checked>
                                        <span>Lite</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label class="label-style">Project Briefing</label>
                            <textarea name="description" placeholder="Describe the project goals and scope..." class="input-style"></textarea>
                        </div>

                        <div class="btn-row">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i>
                                Deploy Project
                            </button>
                            <a href="projects.php" class="btn btn-cancel">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>