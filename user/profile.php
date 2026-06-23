<?php
include '../config/db.php';
requireLogin();

$uid = $_SESSION['user_id'];

// 1. Fetch user information
$stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();

if (!$user) {
    die("Access Denied: User not found.");
}

// 2. Retrieve user tasks
$taskQuery = $pdo->prepare("SELECT status FROM tasks WHERE assigned_to = ?");
$taskQuery->execute([$uid]);
$userTasks = $taskQuery->fetchAll(PDO::FETCH_ASSOC);

// FIXED: consistent underscore status keys
$statuses = ['completed' => 0, 'in_progress' => 0, 'pending' => 0];
foreach ($userTasks as $t) {
    if (isset($statuses[$t['status']])) {
        $statuses[$t['status']]++;
    }
}

// 3. Calculate system-wide tasks
$totalTasks = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$userTaskCount = count($userTasks);
$otherTasks = max(0, $totalTasks - $userTaskCount);

// 4. Get project-wise distribution
$projQuery = $pdo->prepare(
    "SELECT p.project_name, COUNT(t.id) AS count 
     FROM tasks t 
     JOIN projects p ON t.project_id = p.id 
     WHERE t.assigned_to = ? 
     GROUP BY p.id"
);
$projQuery->execute([$uid]);
$projects = $projQuery->fetchAll(PDO::FETCH_ASSOC);

// Prepare JSON for Charts
$statusJson = json_encode([$statuses['pending'], $statuses['in_progress'], $statuses['completed']]);
$contributionJson = json_encode([$userTaskCount, $otherTasks]);
$projectLabels = json_encode(array_column($projects, 'project_name'));
$projectValues = json_encode(array_column($projects, 'count'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intelligence Profile | <?= htmlspecialchars($user['username']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg-body: #f8fafc;
            --sidebar-width: 260px;
            --primary: #6366f1;
            --dark-panel: #020617;
            --border-color: #f1f5f9;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --warning: #f59e0b;
            --success: #10b981;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-body);
            display: flex; height: 100vh; overflow: hidden;
        }

        .main-wrapper {
            flex: 1; display: flex; flex-direction: column;
            overflow-y: auto; margin-left: var(--sidebar-width);
        }

        .container { padding: 40px; max-width: 1200px; width: 100%; margin: 0 auto; }

        /* Banner */
        .dark-banner {
            background: linear-gradient(135deg, #020617 0%, #1e1b4b 100%);
            border-radius: 24px; padding: 40px 50px;
            color: white; margin-bottom: 30px; border-left: 8px solid var(--primary);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }
        .banner-tag { color: var(--primary); font-weight: 900; font-size: 10px; letter-spacing: 4px; text-transform: uppercase; margin-bottom: 8px; display: block; }
        .dark-banner h1 { font-size: 32px; font-weight: 800; text-transform: uppercase; letter-spacing: -1px; }
        .dark-banner h1 span { color: var(--primary); }

        /* Grid & Cards */
        .section-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px; margin-bottom: 30px; }

        .card {
            background: white; border-radius: 28px; padding: 30px;
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease;
        }
        .card:hover { transform: translateY(-5px); }

        .card-header { margin-bottom: 25px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f8fafc; padding-bottom: 15px; }
        .card-header h3 { font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: var(--text-muted); }
        .card-header .material-symbols-outlined { color: var(--primary); font-size: 20px; }

        .chart-container { position: relative; width: 100%; overflow: hidden; }
        .h-300 { height: 300px; }
        .h-400 { height: 400px; }

        /* Stats Row */
        .stat-strip { display: flex; justify-content: space-around; margin-top: 20px; background: #f8fafc; padding: 15px; border-radius: 20px; }
        .stat-item { text-align: center; }
        .stat-item span { font-size: 9px; font-weight: 900; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 8px; }
        .stat-item p { font-size: 20px; font-weight: 900; }
        .stat-warning { color: var(--warning); }
        .stat-primary { color: var(--primary); }
        .stat-success { color: var(--success); }

        .system-caption { text-align: center; font-size: 10px; font-weight: 800; color: var(--text-muted); margin-top: 20px; text-transform: uppercase; }
        .footer-text { text-align: center; font-size: 10px; font-weight: 900; color: #cbd5e1; letter-spacing: 3px; margin: 40px 0; }

        @media (max-width: 1024px) { .main-wrapper { margin-left: 0; } }
        @media (max-width: 900px) { .section-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <?php include '../includes/user_sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include '../includes/user_header.php'; ?>

        <main class="container">
            <header class="dark-banner">
                <span class="banner-tag">>_ Neural_Analytics / Registry_v3</span>
                <h1>Intelligence <span>Hub</span></h1>
            </header>

            <div class="section-grid">
                <div class="card">
                    <div class="card-header">
                        <span class="material-symbols-outlined">bolt</span>
                        <h3>Protocol Performance</h3>
                    </div>
                    <div class="chart-container h-300">
                        <canvas id="statusChart"></canvas>
                    </div>
                    <div class="stat-strip">
                        <div class="stat-item">
                            <span>Pending</span>
                            <p class="stat-warning"><?= $statuses['pending'] ?></p>
                        </div>
                        <div class="stat-item">
                            <span>Active</span>
                            <p class="stat-primary"><?= $statuses['in_progress'] ?></p>
                        </div>
                        <div class="stat-item">
                            <span>Success</span>
                            <p class="stat-success"><?= $statuses['completed'] ?></p>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="material-symbols-outlined">donut_large</span>
                        <h3>System Load</h3>
                    </div>
                    <div class="chart-container h-300">
                        <canvas id="contributionChart"></canvas>
                    </div>
                    <p class="system-caption">Personal Contribution Weight</p>
                </div>
            </div>

            <div class="card" style="margin-bottom: 30px;">
                <div class="card-header">
                    <span class="material-symbols-outlined">hub</span>
                    <h3>Operational Focus Distribution</h3>
                </div>
                <div class="chart-container h-400">
                    <canvas id="projectChart"></canvas>
                </div>
            </div>

            <p class="footer-text">--- SYSTEM DATA STREAM ENDED ---</p>
        </main>
    </div>

    <script>
        const statusData = <?= $statusJson; ?>;
        const contributionData = <?= $contributionJson; ?>;
        const projLabels = <?= $projectLabels; ?>;
        const projValues = <?= $projectValues; ?>;

        Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
        Chart.defaults.color = '#64748b';

        window.addEventListener('load', () => {
            // Bar Chart
            const ctxStatus = document.getElementById('statusChart').getContext('2d');
            const statusGradient = ctxStatus.createLinearGradient(0, 0, 0, 300);
            statusGradient.addColorStop(0, '#6366f1');
            statusGradient.addColorStop(1, '#a5b4fc');

            new Chart(ctxStatus, {
                type: 'bar',
                data: {
                    labels: ['PENDING', 'ACTIVE', 'SUCCESS'],
                    datasets: [{
                        data: statusData,
                        backgroundColor: ['#f59e0b', statusGradient, '#10b981'],
                        borderRadius: 15,
                        barThickness: 50
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { borderDash: [5, 5], color: '#f1f5f9' } },
                        x: { grid: { display: false }, ticks: { font: { weight: '700', size: 10 } } }
                    }
                }
            });

            // Doughnut Chart
            new Chart(document.getElementById('contributionChart'), {
                type: 'doughnut',
                data: {
                    labels: ['CORE', 'SATELLITE'],
                    datasets: [{
                        data: contributionData,
                        backgroundColor: ['#6366f1', '#f1f5f9'],
                        hoverOffset: 10,
                        borderWidth: 0,
                        cutout: '75%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { weight: '800', size: 10 }, usePointStyle: true, padding: 25 } }
                    }
                }
            });

            // Line Chart
            const ctxProject = document.getElementById('projectChart').getContext('2d');
            const lineGradient = ctxProject.createLinearGradient(0, 0, 0, 400);
            lineGradient.addColorStop(0, 'rgba(99,102,241,0.2)');
            lineGradient.addColorStop(1, 'rgba(99,102,241,0)');

            new Chart(ctxProject, {
                type: 'line',
                data: {
                    labels: projLabels,
                    datasets: [{
                        label: 'Task Intensity',
                        data: projValues,
                        borderColor: '#6366f1',
                        backgroundColor: lineGradient,
                        fill: true,
                        tension: 0.45,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#6366f1',
                        pointBorderWidth: 3,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#f8fafc' }, ticks: { stepSize: 1 } },
                        x: { grid: { display: false }, ticks: { font: { weight: '600' } } }
                    }
                }
            });
        });
    </script>
</body>
</html>