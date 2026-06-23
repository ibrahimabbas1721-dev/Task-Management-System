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
$user_id = $_GET['id'] ?? null;
$error = null;
$success = null;

if (!$user_id) {
    header("Location: manage_users.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("User not found.");
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $role     = $_POST['role'];
    $new_password = $_POST['new_password'];
    $prev_password = $_POST['prev_password'];

    try {
        $can_update = true;

        if (!empty($new_password)) {
            if (empty($prev_password)) {
                $error = "Please enter the current password to set a new one.";
                $can_update = false;
            } elseif (!password_verify($prev_password, $user['password'])) {
                $error = "The current password you entered is incorrect.";
                $can_update = false;
            } elseif (strlen($new_password) < 6) {
                $error = "New password must be at least 6 characters.";
                $can_update = false;
            }
        }

        if ($can_update) {
            if (!empty($new_password)) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, password = ? WHERE id = ?");
                $params = [$username, $email, $role, $hashed, $user_id];
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
                $params = [$username, $email, $role, $user_id];
            }

            if ($stmt->execute($params)) {
                $success = "Member profile updated successfully.";
                $user['username'] = $username;
                $user['email'] = $email;
                $user['role'] = $role;
                if (!empty($new_password)) {
                    $user['password'] = $hashed;
                }
            }
        }
    } catch (PDOException $e) {
        $error = ($e->getCode() == 23000) ? "This email is already in use." : "Update Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Security Settings | TMS Pro</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../fontawesome/css/all.min.css" />

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --dark: #0f172a;
            --deep-dark: #161e2d;
            --bg: #f8fafc;
            --surface: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --danger: #ef4444;
            --danger-light: #fef2f2;
            --danger-border: #fee2e2;
            --success: #10b981;
            --success-light: #ecfdf5;
            --success-border: #d1fae5;
            --radius: 24px;
            --radius-sm: 12px;
            --radius-md: 16px;
            --box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.03);
            --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text-main);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        .layout-wrapper { display: flex; min-height: 100vh; }

        .sidebar-spacer {
            width: 260px;
            flex-shrink: 0;
            background: #fff;
            border-right: 1px solid var(--border);
        }

        .wrapper { flex: 1; display: flex; flex-direction: column; min-width: 0; }

        .main-content {
            flex: 1;
            padding: 3rem 2rem;
            overflow-y: auto;
        }

        .content-limit { max-width: 900px; margin: 0 auto; width: 100%; }

        /* Dark Banner */
        .banner {
            background: var(--deep-dark);
            border-radius: var(--radius);
            padding: 1.5rem 2.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            color: white;
            gap: 1.5rem;
        }

        .banner-info { display: flex; align-items: center; gap: 1.5rem; flex: 1; }

        .banner-icon {
            width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 24px;
        }

        .banner-text h1 {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .banner-text p {
            font-size: 11px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-top: 4px;
        }

        .highlight { color: var(--primary); }

        .btn-back {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-back:hover { background: rgba(255, 255, 255, 0.1); transform: translateX(-4px); }

        /* Form Card */
        .form-card {
            background: var(--surface);
            padding: 40px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--box-shadow);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }

        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .full-width { grid-column: span 2; }

        label {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
            padding-left: 4px;
        }

        .input-box {
            width: 100%;
            padding: 14px 16px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            font-size: 14px;
            font-weight: 600;
            background: #f8fafc;
            transition: var(--transition);
            color: var(--text-main);
        }

        .input-box:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid transparent;
        }

        .alert-error { background: var(--danger-light); color: var(--danger); border-color: var(--danger-border); }
        .alert-success { background: var(--success-light); color: var(--success); border-color: var(--success-border); }

        /* Form Footer */
        .form-footer {
            padding-top: 24px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-submit {
            background: var(--dark);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: var(--radius-sm);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-submit:hover { background: var(--primary); transform: translateY(-2px); }

        @media (max-width: 768px) {
            .sidebar-spacer { display: none; }
            .form-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
            .banner { flex-direction: column; align-items: flex-start; }
            .btn-back { width: 100%; justify-content: center; }
        }
    </style>
</head>

<body>
    <div class="layout-wrapper">
        <div class="sidebar-spacer">
            <?php include '../includes/admin_sidebar.php'; ?>
        </div>

        <div class="wrapper">
            <?php include '../includes/admin_header.php'; ?>

            <main class="main-content">
                <div class="content-limit">

                    <header class="banner">
                        <div class="banner-info">
                            <div class="banner-icon">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="banner-text">
                                <h1>Security Settings</h1>
                                <p>Account: <span class="highlight"><?= htmlspecialchars($user['username']) ?></span></p>
                            </div>
                        </div>
                        <a href="manage_users.php" class="btn-back">
                            <i class="fas fa-arrow-left"></i>
                            Back to Team
                        </a>
                    </header>

                    <?php if (isset($error) && $error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($success) && $success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-card">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Profile Username</label>
                                    <input type="text" name="username" class="input-box" value="<?= htmlspecialchars($user['username']) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Work Email Address</label>
                                    <input type="email" name="email" class="input-box" value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>

                                <div class="form-group full-width">
                                    <label>System Access Role</label>
                                    <select name="role" class="input-box">
                                        <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User (Restricted Access)</option>
                                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin (Full Privileges)</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Verify Current Password</label>
                                    <input type="password" name="prev_password" class="input-box" placeholder="Required for password change">
                                </div>

                                <div class="form-group">
                                    <label>New Secure Password</label>
                                    <input type="password" name="new_password" class="input-box" placeholder="Leave blank to keep current">
                                </div>
                            </div>

                            <div class="form-footer">
                                <p style="font-size: 11px; color: var(--text-muted); font-weight: 600;">
                                    Last update: <?= date('M d, Y', strtotime($user['created_at'])) ?>
                                </p>
                                <button type="submit" class="btn-submit">
                                    <i class="fas fa-save"></i>
                                    Update Credentials
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