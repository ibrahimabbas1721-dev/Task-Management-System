<?php
// 1. Database and Session Initialization
include '../config/db.php';
requireLogin();
requireRole('admin');

// Ensure session is started for auth checks
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$admin_id = $_SESSION['user_id'];

if (!$id) {
    header("Location: view_tasks.php?error=invalid_id");
    exit();
}

// 3. Fetch Task Data
try {
    $stmt = $pdo->prepare("SELECT title FROM tasks WHERE id = ? AND created_by_admin = ?");
    $stmt->execute([$id, $admin_id]);
    $task = $stmt->fetch();

    if (!$task) {
        header("Location: view_tasks.php?error=not_found");
        exit();
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// 4. Handle Confirmed Deletion
if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    try {
        $delStmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND created_by_admin = ?");
        $delStmt->execute([$id, $admin_id]);
        header("Location: all_tasks.php");
        exit();
    } catch (PDOException $e) {
        header("Location: all_tasks.php?error=db_error");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Confirm Delete | Protocol</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="../fontawesome/css/all.min.css" />
    
    <style>
        :root {
            --bg-main: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --danger: #ef4444;
            --danger-hover: #dc2626;
            --radius-md: 28px;
            --radius-sm: 14px;
            --transition: all 0.2s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            height: 100vh;
            display: flex;
            overflow: hidden;
        }

        .layout-wrapper {
            display: flex;
            width: 100%;
            height: 100%;
        }

        /* Sidebar and Header Integration */
        .sidebar-space {
            width: 260px;
            flex-shrink: 0;
            border-right: 1px solid var(--border-color);
            background: #fff;
        }

        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .content-area {
            flex: 1;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-y: auto;
        }

        /* Delete Card UI */
        .delete-card {
            width: 100%;
            max-width: 440px;
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 3rem 2.5rem;
            text-align: center;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
        }

        .warning-icon-wrapper {
            width: 84px;
            height: 84px;
            background-color: #fef2f2;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            transform: rotate(-5deg);
        }

        .warning-icon {
            font-size: 2.2rem;
            color: var(--danger);
        }

        .delete-card h2 {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            letter-spacing: -0.02em;
        }

        .delete-card p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .task-title-box {
            background: #f8fafc;
            padding: 1.25rem;
            border-radius: var(--radius-sm);
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 2rem;
            border: 1px dashed var(--border-color);
            word-break: break-word;
            font-size: 0.9rem;
        }

        .btn-stack {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .btn {
            width: 100%;
            padding: 1rem;
            border-radius: 16px;
            font-weight: 700;
            font-size: 0.85rem;
            transition: var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: none;
            cursor: pointer;
        }

        .btn-confirm {
            background-color: var(--danger);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        }

        .btn-confirm:hover {
            background-color: var(--danger-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-cancel {
            background-color: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
        }

        .btn-cancel:hover {
            background-color: #f1f5f9;
            color: var(--text-main);
            border-color: #cbd5e1;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body { overflow-y: auto; height: auto; }
            .layout-wrapper { flex-direction: column; }
            .sidebar-space { width: 100%; height: auto; border-right: none; border-bottom: 1px solid var(--border-color); }
            .content-area { padding: 1.5rem; min-height: 80vh; }
            .delete-card { padding: 2rem 1.5rem; border-radius: 20px; }
        }
    </style>
</head>
<body>
    <div class="layout-wrapper">
        <div class="sidebar-space">
            <?php include '../includes/admin_sidebar.php'; ?>
        </div>

        <div class="main-wrapper">
            <?php include '../includes/admin_header.php'; ?>

            <main class="content-area">
                <div class="delete-card">
                    <div class="warning-icon-wrapper">
                        <i class="fas fa-trash-alt warning-icon"></i>
                    </div>
                    
                    <h2>Confirm Deletion</h2>
                    <p>Are you sure? This protocol will be permanently removed from the system.</p>
                    
                    <div class="task-title-box">
                        <i class="fas fa-file-alt" style="margin-right: 8px; color: var(--text-muted)"></i>
                        <?= htmlspecialchars($task['title']); ?>
                    </div>

                    <div class="btn-stack">
                        <a href="?id=<?= $id; ?>&confirm=yes" class="btn btn-confirm">
                            <i class="fas fa-check-circle"></i> Confirm Delete
                        </a>
                        <a href="all_tasks.php" class="btn btn-cancel">
                            Cancel
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>