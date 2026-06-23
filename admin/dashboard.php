<?php
// Includes and Authentication
include '../config/db.php';
// Assuming these functions are in your included files
if (function_exists('requireLogin')) requireLogin();
if (function_exists('requireRole')) requireRole('admin');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$adminId = $_SESSION['user_id'];
$currentYear = date('Y');

// Fetch Admin User Data
$user = ['username' => 'Administrator'];
try {
    $query = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $query->execute([$adminId]);
    $result = $query->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $user = $result;
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
}

// Initialize Stats
$totalUsers = 0;
$totalTasksCount = 0;
$completeTasks = 0;
$leaderboard = [];

// Gather Stats
try {
    // 1. FIXED: Count team members linked to this admin
    $usersCountStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_by_admin = ?");
    $usersCountStmt->execute([$adminId]);
    $totalUsers = (int)$usersCountStmt->fetchColumn();

    // 2. FIXED: Count all tasks created by this admin
    $totalTasksStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE created_by_admin = ?");
    $totalTasksStmt->execute([$adminId]);
    $totalTasksCount = (int)$totalTasksStmt->fetchColumn();

    // 3. FIXED: Changed 'complete' to 'complete' to match your database ENUM
    $completeTasksStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE created_by_admin = ? AND LOWER(status) = 'complete'");
    $completeTasksStmt->execute([$adminId]);
    $completeTasks = (int)$completeTasksStmt->fetchColumn();

    // 4. FIXED: Leaderboard now looks for 'complete' tasks
    $leaderboardStmt = $pdo->prepare(
        "SELECT u.username, COUNT(t.id) AS tasks_complete 
         FROM users u 
         JOIN tasks t ON u.id = t.assigned_to 
         WHERE LOWER(t.status) = 'complete' AND u.created_by_admin = ? 
         GROUP BY u.id 
         ORDER BY tasks_complete DESC 
         LIMIT 5"
    );
    $leaderboardStmt->execute([$adminId]);
    $leaderboard = $leaderboardStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
}

// Calculate Project Success Rate
$projectSuccessRate = ($totalTasksCount > 0) ? round(($completeTasks / $totalTasksCount) * 100) : 0;

// Prepare Monthly Graph Data
$projectMonthData = array_fill(0, 12, 0);
$taskTrendData = array_fill(0, 12, 0);

try {
    $projectGraphStmt = $pdo->prepare(
        "SELECT MONTH(created_at) AS month, COUNT(*) AS count 
         FROM projects 
         WHERE created_by_admin = ? AND YEAR(created_at) = ? 
         GROUP BY MONTH(created_at)"
    );
    $projectGraphStmt->execute([$adminId, $currentYear]);
    while ($row = $projectGraphStmt->fetch()) {
        $projectMonthData[$row['month'] - 1] = (int)$row['count'];
    }

    $taskTrendStmt = $pdo->prepare(
        "SELECT MONTH(created_at) AS month, COUNT(*) AS count 
         FROM tasks 
         WHERE created_by_admin = ? AND YEAR(created_at) = ? 
         GROUP BY MONTH(created_at)"
    );
    $taskTrendStmt->execute([$adminId, $currentYear]);
    while ($row = $taskTrendStmt->fetch()) {
        $taskTrendData[$row['month'] - 1] = (int)$row['count'];
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
}

$current_date_display = date('l, F jS, Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Command Center | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link rel="stylesheet" href="../fontawesome/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --deep-dark: #0f172a; --primary: #6366f1; --success: #10b981;
            --warning: #f59e0b; --bg: #f8fafc; --surface: #ffffff;
            --text-main: #1e293b; --text-muted: #64748b; --border: #e2e8f0;
            --purple: #8b5cf6; --sky: #0ea5e9;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        .sidebar-spacer { width: 260px; flex-shrink: 0; border-right: 1px solid var(--border); }
        .main-container { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .content-scroll { flex: 1; padding: 2.5rem; overflow-y: auto; }
        .dashboard-content { max-width: 1300px; margin: 0 auto; width: 100%; }

        .page-banner { background: var(--deep-dark); border-radius: 28px; padding: 2.5rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem; color: white; position: relative; overflow: hidden; }
        .banner-title h1 { font-size: 1.6rem; font-weight: 800; }
        .banner-title p { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; margin-top: 4px; }
        .date-badge { background: rgba(255, 255, 255, 0.05); padding: 10px 18px; border-radius: 14px; font-size: 12px; font-weight: 700; border: 1px solid rgba(255,255,255,0.1); }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2.5rem; }
        .tile { background: var(--surface); border: 1px solid var(--border); padding: 1.8rem; border-radius: 26px; position: relative; overflow: hidden; }
        .tile-icon { font-size: 28px; color: var(--primary); margin-bottom: 1rem; display: block; }
        .tile-icon.icon-sky { color: var(--sky); }
        .tile-icon.icon-purple { color: var(--purple); }
        .tile-icon.icon-success { color: var(--success); }
        .tile-val { font-size: 28px; font-weight: 900; letter-spacing: -1px; }
        .tile-label { font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-top: 4px; }
        .progress-mini-bar { position: absolute; bottom: 0; left: 0; width: 100%; height: 6px; background: #f1f5f9; }
        .progress-mini-fill { height: 100%; background: var(--primary); transition: width 1s ease-out; }

        .charts-row { display: grid; grid-template-columns: 1.5fr 1fr; gap: 1.5rem; }
        .chart-box { background: var(--surface); border: 1px solid var(--border); padding: 2rem; border-radius: 28px; }
        .chart-header { font-size: 12px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px; }
        .chart-wrapper { height: 300px; }

        .leaderboard-card { background: var(--surface); border: 1px solid var(--border); border-radius: 28px; padding: 2rem; margin-top: 2.5rem; }
        .table-ui { width: 100%; border-collapse: collapse; }
        .table-ui th { text-align: left; padding: 1rem; border-bottom: 2px solid var(--bg); font-size: 11px; color: var(--text-muted); }
        .table-ui td { padding: 1.2rem 1rem; font-size: 14px; font-weight: 600; border-bottom: 1px solid var(--bg); }
        .rank-tag { background: var(--bg); padding: 4px 8px; border-radius: 6px; font-size: 12px; }
        .empty-state { text-align: center; padding: 3rem; color: var(--text-muted); }
        .progress-bar-wrapper { width: 100px; height: 6px; background: #f1f5f9; border-radius: 10px; overflow: hidden; }
        .progress-bar-fill { height: 100%; background: var(--primary); }

        @media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .charts-row { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { body { flex-direction: column; } .sidebar-spacer { width: 100%; border-right: none; border-bottom: 1px solid var(--border); } .content-scroll { padding: 1.5rem; } .stats-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <div class="sidebar-spacer"><?php include '../includes/admin_sidebar.php'; ?></div>

    <div class="main-container">
        <?php include '../includes/admin_header.php'; ?>
        <main class="content-scroll">
            <div class="dashboard-content">
                <header class="page-banner">
                    <div class="banner-title">
                        <h1>Command Center</h1>
                        <p>Total Operational Intelligence for <?= htmlspecialchars($user['username']) ?></p>
                    </div>
                    <div class="date-badge"><?= $current_date_display ?></div>
                </header>

                <div class="stats-grid">
                    <div class="tile">
                        <i class="fas fa-users tile-icon"></i>
                        <h3 class="counter tile-val" data-target="<?= $totalUsers ?>">0</h3>
                        <p class="tile-label">Team Strength</p>
                    </div>
                    <div class="tile">
                        <i class="fas fa-cubes tile-icon icon-sky"></i>
                        <h3 class="counter tile-val" data-target="<?= $totalTasksCount ?>">0</h3>
                        <p class="tile-label">Total Protocols</p>
                    </div>
                    <div class="tile">
                        <i class="fas fa-chart-line tile-icon icon-purple"></i>
                        <h3 class="counter tile-val" data-target="<?= $projectSuccessRate ?>">0%</h3>
                        <p class="tile-label">Success Rate</p>
                        <div class="progress-mini-bar">
                            <div class="progress-mini-fill" data-width="<?= $projectSuccessRate ?>"></div>
                        </div>
                    </div>
                    <div class="tile">
                        <i class="fas fa-check-double tile-icon icon-success"></i>
                        <h3 class="counter tile-val" data-target="<?= $completeTasks ?>">0</h3>
                        <p class="tile-label">Finalized Work</p>
                    </div>
                </div>

                <div class="charts-row">
                    <div class="chart-box">
                        <h4 class="chart-header"><i class="fas fa-stream"></i> Monthly Task Creation</h4>
                        <div class="chart-wrapper"><canvas id="taskTrendChart"></canvas></div>
                    </div>
                    <div class="chart-box">
                        <h4 class="chart-header"><i class="fas fa-bolt"></i> Project Velocity</h4>
                        <div class="chart-wrapper"><canvas id="projectChart"></canvas></div>
                    </div>
                </div>

               <div class="leaderboard-card">
    <h4 class="chart-header"><i class="fas fa-medal"></i> Performance Leaderboard</h4>
    <table class="table-ui">
        <thead>
            <tr>
                <th>RANK</th>
                <th>MEMBER</th>
                <th>COMPLETE</th>
                <th>VISUAL</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($leaderboard)): ?>
                <tr><td colspan="4" class="empty-state">No complete tasks found.</td></tr>
            <?php else: 
                $rank = 1; 
                foreach($leaderboard as $row): 
                    /** * Calculation logic: 
                     * Ensure $completeTasks is the variable name used in your 
                     * top-level PHP stats gathering for 'complete' status.
                     */
                    $barWidth = min(100, ($row['tasks_complete'] / max(1, $completeTasks)) * 100); 
            ?>
                <tr>
                    <td><span class="rank-tag">#<?= $rank++ ?></span></td>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= $row['tasks_complete'] ?> Tasks</td>
                    <td>
                        <div class="progress-bar-wrapper">
                            <div class="progress-bar-fill" style="width: <?= $barWidth ?>%"></div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
            </div>
        </main>
    </div>

    <script>
        function initCounters() {
            document.querySelectorAll('.counter').forEach(counter => {
                const target = parseInt(counter.getAttribute('data-target'));
                if (isNaN(target)) return;
                const isPercent = counter.innerText.includes('%') || counter.getAttribute('data-target').includes('%') || counter.parentElement.querySelector('.tile-label').innerText.includes('RATE');
                let current = 0;
                const update = () => {
                    const increment = Math.ceil(target / 40);
                    if (current < target) {
                        current = (current + increment > target) ? target : current + increment;
                        counter.innerText = isPercent ? current + '%' : current;
                        setTimeout(update, 30);
                    } else { counter.innerText = isPercent ? target + '%' : target; }
                };
                update();
            });
        }

        function initProgressBars() {
            document.querySelectorAll('.progress-mini-fill').forEach(bar => {
                const width = bar.getAttribute('data-width');
                if (width) { setTimeout(() => { bar.style.width = width + '%'; }, 100); }
            });
        }

        document.addEventListener("DOMContentLoaded", function() {
            initCounters();
            initProgressBars();
            
            new Chart(document.getElementById('taskTrendChart'), {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        data: <?= json_encode($taskTrendData) ?>,
                        borderColor: '#6366f1', borderWidth: 4, tension: 0.4, fill: true,
                        backgroundColor: 'rgba(99, 102, 241, 0.05)', pointRadius: 0
                    }]
                },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } },
                    scales: { x: { display: false }, y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { color: '#64748b', font: { size: 11 } } } }
                }
            });

            new Chart(document.getElementById('projectChart'), {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        data: <?= json_encode($projectMonthData) ?>,
                        backgroundColor: '#0f172a', borderRadius: 4
                    }]
                },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } },
                    scales: { x: { display: false }, y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { color: '#64748b', font: { size: 11 } } } }
                }
            });
        });
    </script>
</body>
</html>