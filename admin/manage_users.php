<?php
include '../config/db.php';
// Assuming these functions handle session_start internally
requireLogin(); 
requireRole('admin');

$admin_id = $_SESSION['user_id'] ?? null;
$error = null;
$users = []; // Initialize as empty array to prevent count() errors

// 1. Double check authentication if helper functions aren't strict
if (!$admin_id || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
$role = $user['role'] ?? 'User'; // Fallback agar role na mile
$badgeClass = ($role == 'admin') ? 'bg-danger' : 'bg-primary';
// 2. Get filter values
$search = $_GET['search'] ?? '';

// 3. Handle Deletion Logic
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        // Ensure the admin can only delete users they created
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND created_by_admin = ?");
        $stmt->execute([$delete_id, $admin_id]);
        header("Location: manage_users.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        $error = "Delete failed: " . $e->getMessage();
    }
}

try {
    // 4. Build Query (Fixed ORDER BY column name to match common schemas)
    $sql = "SELECT id, username, email, role 
            FROM users 
            WHERE created_by_admin = ?";
    $params = [$admin_id];

    if (!empty($search)) {
        $sql .= " AND (username LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql .= " ORDER BY id DESC"; // Changed from created_at to avoid column mismatch errors
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
    $users = []; // Reset to empty array on failure
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Team Registry | TMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../fontawesome/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: #6366f1;
            --dark: #0f172a;
            --deep-dark: #161e2d;
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --border: #f1f5f9;
            --border-main: #e2e8f0;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --radius: 24px;
            --radius-sm: 12px;
            --radius-md: 14px;
            --radius-lg: 16px;
            --danger: #ef4444;
            --success: #10b981;
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 0.75rem;
            --spacing-lg: 1rem;
            --spacing-xl: 1.5rem;
            --spacing-2xl: 2rem;
            --spacing-3xl: 2.5rem;
            --transition: 0.2s;
            --transition-lg: 0.3s;
            --box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-body);
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        .layout-wrapper { display: flex; height: 100vh; width: 100%; }
        .sidebar-spacer { width: 260px; flex-shrink: 0; overflow-y: auto; }
        .wrapper { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .main-content { flex: 1; padding: var(--spacing-3xl) var(--spacing-2xl); max-width: 1200px; margin: 0 auto; width: 100%; overflow-y: auto; }

        /* Banner Header */
        .banner {
            background: var(--deep-dark);
            border-radius: var(--radius);
            padding: 1.8rem var(--spacing-3xl);
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--spacing-2xl);
            color: white;
            border-left: 6px solid var(--primary);
            gap: var(--spacing-xl);
        }

        .banner-info { display: flex; align-items: center; gap: var(--spacing-xl); }
        .banner-icon {
            width: 50px; height: 50px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            color: var(--primary); font-size: 20px;
        }

        .banner-text h1 { font-size: 1.4rem; font-weight: 800; }
        .banner-subtitle { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }

        .btn-add {
            background: var(--primary); color: white;
            padding: 12px 20px; border-radius: var(--radius-sm);
            text-decoration: none; font-size: 11px; font-weight: 700;
            text-transform: uppercase; display: flex; align-items: center; gap: 8px;
            transition: var(--transition-lg); border: none; cursor: pointer;
        }

        .btn-add:hover { transform: translateY(-2px); box-shadow: var(--box-shadow); }

        /* Filter Bar */
        .filter-section { display: flex; align-items: flex-end; gap: 15px; margin-bottom: 25px; }
        .filter-group { display: flex; flex-direction: column; gap: 8px; }
        .filter-label { font-size: 10px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; }
        .filter-input {
            background: white; border: 1px solid var(--border-main);
            padding: 12px 16px; border-radius: var(--radius-md);
            font-size: 13px; min-width: 300px; outline: none; transition: var(--transition);
        }
        .filter-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); }
        .btn-reset {
            background: #f1f5f9; color: var(--text-main);
            padding: 12px 20px; border-radius: var(--radius-sm);
            text-decoration: none; font-size: 13px; font-weight: 700;
            border: 1px solid var(--border); transition: var(--transition);
        }

        /* Table */
        .table-container {
            background: var(--bg-card); border-radius: var(--radius);
            padding: var(--spacing-xl); border: 1px solid var(--border-main);
        }
        table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        th { padding: 10px 20px; text-align: left; font-size: 10px; text-transform: uppercase; color: var(--text-muted); font-weight: 800; }
        td { padding: 1rem 1.5rem; background: var(--bg-card); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
        tr td:first-child { border-left: 1px solid var(--border); border-radius: 16px 0 0 16px; }
        tr td:last-child { border-right: 1px solid var(--border); border-radius: 0 16px 16px 0; }

        .user-block { display: flex; align-items: center; gap: 12px; }
        .user-avatar {
            width: 40px; height: 40px; background: #f1f5f9;
            border-radius: var(--radius-sm); display: flex; align-items: center;
            justify-content: center; font-weight: 800; color: var(--dark); border: 1px solid var(--border-main);
        }
        .user-name { font-weight: 700; font-size: 14px; color: var(--text-main); }
        .user-email { font-size: 11px; color: var(--text-muted); }

        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
            background-color: #e2e8f0; /* Default light gray */
            color: #475569;
        }

        /* Specific colors for roles */
        .role-badge:not(:empty) {
            background-color: #3b82f6; /* Blue for active roles */
            color: white;
        }
        /* Action Buttons */
        .action-btns { display: flex; align-items: center; justify-content: flex-end; gap: 10px; }
        .icon-link {
            width: 38px; height: 38px; display: flex; align-items: center;
            justify-content: center; border-radius: var(--radius-lg);
            color: var(--text-muted); border: 1px solid var(--border-main);
            transition: var(--transition); text-decoration: none;
        }

        .btn-profile:hover { color: var(--success) !important; border-color: var(--success) !important; background: #f0fdf4 !important; }
        .btn-edit:hover { color: var(--primary) !important; border-color: var(--primary) !important; background: #f5f3ff !important; }
        .btn-delete:hover { color: var(--danger) !important; border-color: var(--danger) !important; background: #fff1f1 !important; }

        @media (max-width: 1024px) {
            .sidebar-spacer { display: none; }
            .banner { flex-direction: column; align-items: flex-start; }
            .btn-add { width: 100%; }
            .filter-input { min-width: 100%; }
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
                <header class="banner">
                    <div class="banner-info">
                        <div class="banner-icon"><i class="fa-solid fa-shield-halved"></i></div>
                        <div class="banner-text">
                            <h1>Team Registry</h1>
                            <div class="banner-subtitle">Active Direct Reports (<?= count($users); ?>)</div>
                        </div>
                    </div>
                    <a href="add_user.php" class="btn-add">
                        <i class="fa-solid fa-plus"></i> New Operative
                    </a>
                </header>

                <form method="GET" class="filter-section">
                    <div class="filter-group">
                        <label class="filter-label">Search Operative</label>
                        <input type="text" name="search" class="filter-input" placeholder="Search name or email..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <a href="manage_users.php" class="btn-reset">Reset</a>
                </form>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Member Identity</th>
                                <th>Access Tier</th>
                                <th>Management</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="3" style="text-align:center; color:var(--text-muted);">No operatives found in registry.</td></tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="user-block">
                                                <div class="user-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
                                                <div class="user-info">
                                                    <div class="user-name"><?= htmlspecialchars($user['username']) ?></div>
                                                    <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="role-badge <?= $badgeClass ?>">
                                            <?= htmlspecialchars($role) ?>
                                        </span></td>
                                        <td>
                                           <div class="action-btns">
                                                <a href="user_profile.php?id=<?= $user['id'] ?>" class="icon-link btn-profile" title="View Progress">
                                                    <i class="fas fa-chart-line"></i>
                                                </a>
                                                <a href="edit_user.php?id=<?= $user['id'] ?>" class="icon-link btn-edit" title="Edit Parameters">
                                                    <i class="fas fa-sliders"></i>
                                                </a>
                                                <a href="delete_user.php?delete_user_id=<?= $user['id'] ?>" class="icon-link btn-delete" title="Purge Record">
                                                    <i class="fas fa-skull-crossbones"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
</body>
</html>