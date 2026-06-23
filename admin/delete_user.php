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

// Get User ID from either GET (to show the page) or POST (to delete)
$user_id = isset($_REQUEST['delete_user_id']) ? (int)$_REQUEST['delete_user_id'] : 0;
$admin_id = $_SESSION['user_id'] ?? 0;

// Prevent self-deletion
if ($user_id === (int)$admin_id) {
    header("Location: manage_users.php?error=cannot_delete_self");
    exit();
}

if (!$user_id) {
    header("Location: manage_users.php?error=invalid_id");
    exit();
}

// Fetch user details to show in the confirmation box
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_to_delete = $stmt->fetch();

if (!$user_to_delete) {
    header("Location: manage_users.php?error=not_found");
    exit();
}

// Handle Actual Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        header("Location: manage_users.php?msg=user_deleted");
        exit();
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Confirm Delete User | Protocol</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="../fontawesome/css/all.min.css" />

    <style>
        :root {
            --primary: #6366f1;
            --dark: #0f172a;
            --bg-main: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --danger: #ef4444;
            --danger-hover: #dc2626;
            --danger-bg: #fef2f2;
            --danger-light: #fee2e2;
            --radius-md: 28px;
            --radius-sm: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .layout-wrapper {
            display: flex;
            width: 100%;
            height: 100%;
        }

        .sidebar-spacer {
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
            padding: 3.5rem 2.5rem;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.08);
            animation: slideUp 0.4s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .warning-icon-wrapper {
            width: 84px;
            height: 84px;
            background-color: var(--danger-bg);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            border: 1px solid var(--danger-light);
            transform: rotate(-4deg);
        }

        .warning-icon {
            font-size: 2rem;
            color: var(--danger);
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: var(--dark);
            letter-spacing: -0.02em;
        }

        .card-desc {
            font-size: 0.95rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .user-info-box {
            background: var(--bg-main);
            padding: 1.5rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.5rem;
            border: 1px dashed var(--border-color);
        }

        .user-name {
            display: block;
            font-weight: 800;
            color: var(--dark);
            font-size: 1.1rem;
            margin-bottom: 4px;
        }

        .user-email {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .irreversible-tag {
            font-size: 10px;
            font-weight: 900;
            color: var(--danger);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 2.5rem;
            display: block;
        }

        .btn-stack {
            display: flex;
            flex-direction: column;
            gap: 12px;
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
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer;
            border: none;
        }

        .btn-confirm {
            background-color: var(--dark);
            color: white;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
        }

        .btn-confirm:hover {
            background-color: var(--danger);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.25);
        }

        .btn-cancel {
            background-color: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
        }

        .btn-cancel:hover {
            background-color: #f1f5f9;
            color: var(--dark);
            border-color: var(--dark);
        }

        @media (max-width: 768px) {
            body { overflow-y: auto; height: auto; }
            .layout-wrapper { flex-direction: column; }
            .sidebar-spacer { width: 100%; border-right: none; border-bottom: 1px solid var(--border-color); }
            .content-area { padding: 1.5rem; min-height: 80vh; }
            .delete-card { padding: 2.5rem 1.5rem; border-radius: 20px; }
        }
    </style>
</head>
<body>
    <div class="layout-wrapper">
        <div class="sidebar-spacer">
            <?php include '../includes/admin_sidebar.php'; ?>
        </div>

        <div class="main-wrapper">
            <?php include '../includes/admin_header.php'; ?>

            <main class="content-area">
                <div class="delete-card">
                    <div class="warning-icon-wrapper">
                        <i class="fas fa-user-shield warning-icon"></i>
                    </div>

                    <h2 class="card-title">Revoke Access?</h2>
                    <p class="card-desc">You are about to permanently terminate the credentials for:</p>

                    <div class="user-info-box">
                        <span class="user-name"><?= htmlspecialchars($user_to_delete['username']); ?></span>
                        <span class="user-email"><?= htmlspecialchars($user_to_delete['email']); ?></span>
                    </div>

                    <span class="irreversible-tag"><i class="fas fa-exclamation-triangle"></i> This action is irreversible</span>

                    <form method="POST" class="btn-stack">
                        <input type="hidden" name="delete_user_id" value="<?= $user_id ?>">

                        <button type="submit" name="confirm_delete" class="btn btn-confirm">
                            <i class="fas fa-user-times"></i> Confirm Termination
                        </button>

                        <a href="manage_users.php" class="btn btn-cancel">
                            Cancel & Return
                        </a>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>
</html>