<?php
include '../config/db.php';

// Authentication & Session
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$adminId = $_SESSION['user_id'];
$settingsFile = 'admin_settings_' . $adminId . '.json';

// --- 1. PERSISTENT LOGO LOGIC ---
$logoPath = '../assets/img/default-logo.png'; 
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
    $logoPath = $settings['report_logo'] ?? $logoPath;
}

if (isset($_FILES['admin_logo']) && $_FILES['admin_logo']['error'] === 0) {
    $uploadDir = '../assets/img/uploads/';
    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
    $ext = pathinfo($_FILES['admin_logo']['name'], PATHINFO_EXTENSION);
    $newName = 'logo_' . $adminId . '.' . $ext;
    $targetPath = $uploadDir . $newName;
    
    if (move_uploaded_file($_FILES['admin_logo']['tmp_name'], $targetPath)) {
        $logoPath = $targetPath;
        file_put_contents($settingsFile, json_encode(['report_logo' => $logoPath]));
    }
}

// --- 2. FILTERS & DATA ---
$filter_user = $_GET['user_id'] ?? '';
$filter_project = $_GET['project_id'] ?? '';
$filter_from_date = $_GET['from_date'] ?? '';
$filter_to_date = $_GET['to_date'] ?? '';

// Fetch Dropdowns (Exclude Admin, Show only Team)
$users = $pdo->prepare("SELECT id, username FROM users WHERE created_by_admin = ?");
$users->execute([$adminId]);
$allUsers = $users->fetchAll();

$projs = $pdo->prepare("SELECT id, project_name FROM projects WHERE created_by_admin = ?");
$projs->execute([$adminId]);
$allProjs = $projs->fetchAll();

$currentProjectName = "Full Operational Overview";
foreach($allProjs as $p) { if($p['id'] == $filter_project) $currentProjectName = $p['project_name']; }

// Main Data Query (Added created_at/completed_at tracking based on your schema structure)
$queryStr = "SELECT t.*, p.project_name, u.username FROM tasks t 
             JOIN projects p ON t.project_id = p.id 
             LEFT JOIN users u ON t.assigned_to = u.id 
             WHERE t.created_by_admin = :adminId";
$params = [':adminId' => $adminId];

if ($filter_user) { $queryStr .= " AND t.assigned_to = :u"; $params[':u'] = $filter_user; }
if ($filter_project) { $queryStr .= " AND t.project_id = :p"; $params[':p'] = $filter_project; }

// Apply Date Boundaries
if ($filter_from_date) { 
    $queryStr .= " AND t.created_at >= :from_date"; 
    $params[':from_date'] = $filter_from_date . ' 00:00:00'; 
}
if ($filter_to_date) { 
    $queryStr .= " AND t.created_at <= :to_date"; 
    $params[':to_date'] = $filter_to_date . ' 23:59:59'; 
}

$stmt = $pdo->prepare($queryStr);
$stmt->execute($params);
$reportData = $stmt->fetchAll();

// Stats for Charts
$stats = ['pending' => 0, 'in_progress' => 0, 'complete' => 0];
foreach($reportData as $row) { if(isset($stats[$row['status']])) $stats[$row['status']]++; }
$totalTasks = count($reportData);
$successRate = ($totalTasks > 0) ? round(($stats['complete'] / $totalTasks) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Intelligence Report | TMS Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../fontawesome/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --primary: #6366f1; --dark: #0f172a; --border: #e2e8f0; --bg: #f8fafc; --success: #10b981; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); margin: 0; display: flex; height: 100vh; overflow: hidden; }
        .sidebar-spacer { width: 260px; flex-shrink: 0; border-right: 1px solid var(--border); }
        .main-container { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .content-scroll { flex: 1; padding: 2.5rem; overflow-y: auto; }

        /* Controls */
        .admin-controls { background: white; padding: 1.5rem; border-radius: 20px; border: 1px solid var(--border); margin-bottom: 2rem; }
        .control-flex { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .control-group { display: flex; flex-direction: column; gap: 5px; }
        .control-group label { font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; }
        select, input { padding: 10px; border-radius: 10px; border: 1px solid var(--border); font-family: inherit; font-size: 13px; }

        .btn { padding: 10px 20px; border-radius: 10px; border: none; font-weight: 700; cursor: pointer; text-decoration: none; font-size: 13px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-outline { border: 1px solid var(--border); color: #64748b; background: white; }
        .btn-dark { background: var(--dark); color: white; }

        /* Report Visuals */
        .stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
        .stat-tile { background: white; padding: 1.5rem; border-radius: 20px; border: 1px solid var(--border); text-align: center; }
        .stat-tile h3 { font-size: 11px; color: #64748b; text-transform: uppercase; margin: 0; }
        .stat-tile p { font-size: 28px; font-weight: 800; margin: 10px 0 0; color: var(--dark); }

        .chart-row { display: grid; grid-template-columns: 1fr 1.5fr; gap: 1.5rem; margin-bottom: 2rem; }
        .chart-card { background: white; padding: 1.5rem; border-radius: 24px; border: 1px solid var(--border); height: 320px; }

        /* Print Override */
        .print-cover { display: none; height: 100vh; text-align: center; flex-direction: column; justify-content: center; background: white; }
        @media print {
            .sidebar-spacer, .admin-controls, header, .no-print { display: none !important; }
            body, .main-container, .content-scroll { display: block; overflow: visible; height: auto; background: white; padding: 0; }
            .print-cover { display: flex !important; page-break-after: always; }
            .report-section { page-break-before: always; padding: 40px; }
        }
    </style>
</head>
<body>

    <div class="sidebar-spacer"><?php include '../includes/admin_sidebar.php'; ?></div>

    <div class="main-container">
        <?php include '../includes/admin_header.php'; ?>
        
        <main class="content-scroll">
            <div class="admin-controls no-print">
                <div class="control-flex">
                    <form method="POST" enctype="multipart/form-data" style="border-right: 1px solid var(--border); padding-right: 20px;">
                        <input type="hidden" name="existing_logo" value="<?= $logoPath ?>">
                        <div class="control-group">
                            <label>Update Logo</label>
                            <input type="file" name="admin_logo" onchange="this.form.submit()">
                        </div>
                    </form>

                    <form method="GET" class="control-flex">
                        <div class="control-group">
                            <label>Project</label>
                            <select name="project_id">
                                <option value="">All Projects</option>
                                <?php foreach($allProjs as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $filter_project == $p['id'] ? 'selected' : '' ?>><?= $p['project_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="control-group">
                            <label>Member</label>
                            <select name="user_id">
                                <option value="">Active Team</option>
                                <?php foreach($allUsers as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= $filter_user == $u['id'] ? 'selected' : '' ?>><?= $u['username'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="control-group">
                            <label>From Date</label>
                            <input type="date" name="from_date" value="<?= htmlspecialchars($filter_from_date) ?>">
                        </div>
                        <div class="control-group">
                            <label>To Date</label>
                            <input type="date" name="to_date" value="<?= htmlspecialchars($filter_to_date) ?>">
                        </div>

                        <button type="submit" class="btn btn-dark">Filter</button>
                        <a href="reports.php" class="btn btn-outline">Clear</a>
                        <button type="button" class="btn btn-primary" onclick="window.print()">Export PDF</button>
                    </form>
                </div>
            </div>

            <div class="print-cover">
                <img src="<?= $logoPath ?>" style="max-width: 250px; margin: 0 auto 40px auto;">
                <h1 style="font-size: 80px; margin: 0; letter-spacing: -3px; color: var(--dark);">Report</h1>
                <p style="font-size: 24px; color: #64748b; font-weight: 600;"><?= htmlspecialchars($currentProjectName) ?></p>
                <div style="margin-top: 100px; font-weight: 800; color: #cbd5e1; text-transform: uppercase; letter-spacing: 5px;">
                    Date: <?= date('F d, Y') ?>
                </div>
            </div>

            <div class="report-section">
                <div class="stat-grid">
                    <div class="stat-tile" style="border-top: 5px solid var(--primary)"><h3>Tasks</h3><p><?= $totalTasks ?></p></div>
                    <div class="stat-tile" style="border-top: 5px solid var(--success)"><h3>complete</h3><p><?= $stats['complete'] ?></p></div>
                    <div class="stat-tile" style="border-top: 5px solid #f59e0b"><h3>Rate</h3><p><?= $successRate ?>%</p></div>
                    <div class="stat-tile" style="border-top: 5px solid #ef4444"><h3>Active</h3><p><?= $stats['in_progress'] ?></p></div>
                </div>

                <div class="chart-row">
                    <div class="chart-card"><canvas id="statusChart"></canvas></div>
                    <div class="chart-card"><canvas id="productivityChart"></canvas></div>
                </div>

                <div style="background: white; border-radius: 20px; border: 1px solid var(--border); overflow: hidden;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background: #f1f5f9; text-align: left;">
                            <tr>
                                <th style="padding: 15px; font-size: 11px; color: #64748b;">PROJECT</th>
                                <th style="padding: 15px; font-size: 11px; color: #64748b;">TASK</th>
                                <th style="padding: 15px; font-size: 11px; color: #64748b;">MEMBER</th>
                                <th style="padding: 15px; font-size: 11px; color: #64748b;">STATUS</th>
                                <th style="padding: 15px; font-size: 11px; color: #64748b;">DATE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($reportData as $row): ?>
                            <tr style="border-bottom: 1px solid var(--border);">
                                <td style="padding: 15px; font-size: 13px; font-weight: 700;"><?= $row['project_name'] ?></td>
                                <td style="padding: 15px; font-size: 13px;"><?= $row['title'] ?></td>
                                <td style="padding: 15px; font-size: 13px; color: #64748b;"><?= $row['username'] ?? 'Unassigned' ?></td>
                                <td style="padding: 15px; font-size: 11px; font-weight: 800; color: <?= $row['status']=='complete'?'#10b981':'#6366f1' ?>;">
                                    <?= strtoupper(str_replace('_', ' ', $row['status'])) ?>
                                </td>
                                <td style="padding: 15px; font-size: 12px; color: #64748b;">
                                    <?php 
                                        $displayDate = !empty($row['completed_at']) ? $row['completed_at'] : ($row['created_at'] ?? null);
                                        echo $displayDate ? date('M d, Y', strtotime($displayDate)) : '--'; 
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Complete'],
                datasets: [{
                    data: [<?= $stats['pending'] ?>, <?= $stats['in_progress'] ?>, <?= $stats['complete'] ?>],
                    backgroundColor: ['#e2e8f0', '#6366f1', '#10b981', '#f59e0b']
                }]
            },
            options: { maintainAspectRatio: false, plugins: { title: { display: true, text: 'Completion Status Breakdown' }}}
        });

        new Chart(document.getElementById('productivityChart'), {
            type: 'bar',
            data: {
                labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                datasets: [{
                    label: 'Volume',
                    data: [0, 0, 0, <?= $totalTasks ?>],
                    backgroundColor: '#0f172a'
                }]
            },
            options: { maintainAspectRatio: false }
        });
    </script>
</body>
</html>