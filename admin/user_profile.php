<?php
include '../config/db.php';
requireLogin();
requireRole('admin');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$targetUserId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$adminUserId  = $_SESSION['user_id'];

if (!$targetUserId) {
    header('Location: manage_users.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, username, email, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$targetUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) { header('Location: manage_users.php?error=notfound'); exit; }

    $stats = ['total_tasks' => 0, 'complete_tasks' => 0, 'in_progress_tasks' => 0, 'pending_tasks' => 0];

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'complete'     THEN 1 ELSE 0 END) AS complete,
            SUM(CASE WHEN status = 'in_progress'  THEN 1 ELSE 0 END) AS in_progress,
            SUM(CASE WHEN status = 'pending'       THEN 1 ELSE 0 END) AS pending
        FROM tasks WHERE assigned_to = ?
    ");
    $stmt->execute([$targetUserId]);
    $ts = $stmt->fetch(PDO::FETCH_ASSOC);

    $stats['total_tasks']       = (int)($ts['total']       ?? 0);
    $stats['complete_tasks']    = (int)($ts['complete']     ?? 0);
    $stats['in_progress_tasks'] = (int)($ts['in_progress']  ?? 0);
    $stats['pending_tasks']     = (int)($ts['pending']       ?? 0);

    $efficiency = $stats['total_tasks'] > 0
        ? round(($stats['complete_tasks'] / $stats['total_tasks']) * 100)
        : 0;

    // Monthly data (last 6 months) — assigned vs completed
    $monthly_assigned  = [];
    $monthly_completed = [];
    $monthly_labels    = [];
    for ($i = 5; $i >= 0; $i--) {
        $month_start = date('Y-m-01', strtotime("-$i months"));
        $month_end   = date('Y-m-t',  strtotime("-$i months"));
        $monthly_labels[] = date('M', strtotime($month_start));

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND created_at BETWEEN ? AND ?");
        $stmt->execute([$targetUserId, $month_start . ' 00:00:00', $month_end . ' 23:59:59']);
        $monthly_assigned[] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'complete' AND updated_at BETWEEN ? AND ?");
        $stmt->execute([$targetUserId, $month_start . ' 00:00:00', $month_end . ' 23:59:59']);
        $monthly_completed[] = (int)$stmt->fetchColumn();
    }

    // Weekly activity (last 7 days)
    $weekly_data   = [];
    $weekly_labels = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i days"));
        $weekly_labels[] = date('D', strtotime($day));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND DATE(updated_at) = ?");
        $stmt->execute([$targetUserId, $day]);
        $weekly_data[] = (int)$stmt->fetchColumn();
    }

} catch (PDOException $e) {
    die('Database Error: ' . $e->getMessage());
}

$bar_data      = json_encode([$stats['complete_tasks'], $stats['in_progress_tasks']]);
$gauge_data    = json_encode([$efficiency, 100 - $efficiency]);
$monthly_a_json = json_encode($monthly_assigned);
$monthly_c_json = json_encode($monthly_completed);
$monthly_l_json = json_encode($monthly_labels);
$weekly_d_json  = json_encode($weekly_data);
$weekly_l_json  = json_encode($weekly_labels);
$pie_data       = json_encode([$stats['complete_tasks'], $stats['in_progress_tasks'], $stats['pending_tasks']]);

$initials = strtoupper(substr($user['username'] ?? 'U', 0, 2));
$joined   = !empty($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= htmlspecialchars($user['username']) ?> | Performance · TMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="../fontawesome/css/all.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sidebar-width: 260px;
            --indigo:   #6366f1;
            --green:    #10b981;
            --amber:    #f59e0b;
            --blue:     #3b82f6;
            --slate-50: #f8fafc;
            --slate-100:#f1f5f9;
            --slate-200:#e2e8f0;
            --slate-400:#94a3b8;
            --slate-500:#64748b;
            --slate-700:#334155;
            --slate-800:#1e293b;
            --slate-900:#0f172a;
            --white:    #ffffff;
            --page-bg:  #f4f6f9;
            --radius-lg:12px;
            --tr:       0.18s ease;
        }

        html, body { height: 100%; overflow-x: hidden; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--page-bg);
            color: var(--slate-800);
            display: flex;
        }
        
        .layout-wrapper { display: flex; width: 100%; min-height: 100vh; }
        .sidebar-space   { width: var(--sidebar-width); flex-shrink: 0; overflow-y: auto; }
        .main-wrapper    { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .content-scroll  { flex: 1; overflow-y: auto; }

        /* ── Topbar ── */
        .topbar {
            background: var(--page-bg);
            border-bottom: 1px solid var(--slate-200);
            padding: 9px 24px;
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

        .topbar-brand i { font-size: 14px; color: var(--indigo); }

        .topbar-live {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 600;
            color: var(--green);
        }

        .live-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--green);
            animation: blink 1.4s infinite;
        }

        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.2} }

        /* ── Dark Banner ── */
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

        .banner-left { display: flex; align-items: center; gap: 18px; }

        .avatar-lg {
            width: 54px; height: 54px;
            border-radius: 12px;
            background: #1e3a5f;
            border: 1.5px solid #2d5a8e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            color: #93c5fd;
            flex-shrink: 0;
        }

        .banner-tag {
            font-size: 10px;
            font-weight: 700;
            color: var(--indigo);
            letter-spacing: 2.5px;
            text-transform: uppercase;
            font-family: monospace;
            margin-bottom: 4px;
        }

        .banner-h {
            font-size: 20px;
            font-weight: 700;
            color: #f1f5f9;
            margin: 0;
        }

        .banner-h em { color: #818cf8; font-style: normal; }

        .banner-sub { font-size: 11px; color: #4a7ab5; margin-top: 3px; }

        .banner-actions { display: flex; align-items: center; gap: 10px; }

        .btn-back {
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.13);
            color: #94a3b8;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 9px 16px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: var(--tr);
        }

        .btn-back:hover { background: rgba(255,255,255,.11); color: #f1f5f9; }

        .btn-edit {
            background: var(--indigo);
            border: none;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 9px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--tr);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-edit:hover { background: #4f46e5; }

        /* ── Page body ── */
        .page-body {
            padding: 20px 28px 32px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        /* ── Stat row ── */
        .stat-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        .stat-card {
            background: var(--white);
            border: 0.5px solid var(--slate-200);
            border-radius: var(--radius-lg);
            padding: 15px 17px;
        }

        .stat-label {
            font-size: 10px;
            font-weight: 700;
            color: var(--slate-400);
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin-bottom: 7px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stat-label i { font-size: 13px; }
        .stat-num { font-size: 28px; font-weight: 700; color: var(--slate-900); line-height: 1; }

        .stat-sub {
            font-size: 11px;
            font-weight: 500;
            margin-top: 4px;
        }

        .sub-green  { color: var(--green); }
        .sub-indigo { color: var(--indigo); }
        .sub-amber  { color: var(--amber); }
        .sub-slate  { color: var(--slate-500); }

        /* ── Charts ── */
        .charts-top {
            display: grid;
            grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
            gap: 14px;
        }

        .charts-bottom {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .card {
            background: var(--white);
            border: 0.5px solid var(--slate-200);
            border-radius: var(--radius-lg);
            padding: 17px 19px;
        }

        .card-title {
            font-size: 10px;
            font-weight: 700;
            color: var(--slate-500);
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin-bottom: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .card-title i { font-size: 14px; color: var(--indigo); }

        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 10px;
            font-size: 11px;
            color: var(--slate-500);
        }

        .legend span { display: flex; align-items: center; gap: 4px; }
        .leg-sq { width: 9px; height: 9px; border-radius: 2px; flex-shrink: 0; }

        /* ── Gauge ── */
        .gauge-wrap { position: relative; height: 160px; }

        .gauge-center {
            position: absolute;
            top: 63%; left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }

        .gauge-val { font-size: 22px; font-weight: 700; color: var(--green); }
        .gauge-lbl { font-size: 10px; color: var(--slate-400); font-weight: 600; letter-spacing: 1px; text-transform: uppercase; }

        /* ── Detail rows ── */
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 0.5px solid var(--slate-100);
            font-size: 12px;
        }

        .detail-row:last-child { border-bottom: none; }
        .detail-key { color: var(--slate-500); font-weight: 500; }
        .detail-val { font-weight: 700; color: var(--slate-900); }

        /* ── Progress bars ── */
        .pb-row { margin-bottom: 10px; }
        .pb-row:last-child { margin-bottom: 0; }

        .pb-meta {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 5px;
        }

        .pb-key { color: var(--slate-500); font-weight: 500; }
        .pb-val { font-weight: 700; color: var(--slate-900); }

        .pb-track {
            background: var(--slate-100);
            border-radius: 99px;
            height: 7px;
            overflow: hidden;
        }

        .pb-fill { height: 100%; border-radius: 99px; }

        @media (max-width: 1000px) {
            .sidebar-space { display: none; }
            .charts-top    { grid-template-columns: 1fr; }
            .charts-bottom { grid-template-columns: 1fr; }
            .stat-row      { grid-template-columns: repeat(2, 1fr); }
            .dark-banner   { margin: 14px 16px 0; }
            .page-body     { padding: 16px; }
        }
    </style>
</head>
<body>
<div class="layout-wrapper">
    <div class="sidebar-space"><?php include '../includes/admin_sidebar.php'; ?></div>

    <div class="main-wrapper">

        <?php include '../includes/admin_header.php'; ?>

        <div class="content-scroll">

            <!-- Dark Banner — kept exactly as design spec -->
            <div class="dark-banner">
                <div class="banner-left">
                    <div class="avatar-lg"><?= $initials ?></div>
                    <div>
                        <div class="banner-tag">&gt;_Operative_Analytics</div>
                        <div class="banner-h"><?= htmlspecialchars(explode('_', $user['username'])[0] ?? $user['username']) ?>_<em><?= htmlspecialchars(explode('_', $user['username'])[1] ?? '') ?></em></div>
                        <div class="banner-sub">
                            <?= htmlspecialchars($user['email']) ?>
                            &nbsp;&middot;&nbsp; Joined <?= $joined ?>
                            &nbsp;&middot;&nbsp; Role: <?= strtoupper($user['role']) ?>
                        </div>
                    </div>
                </div>
                <div class="banner-actions">
                    <a href="manage_users.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn-edit">
                        <i class="fas fa-pen"></i> Edit Profile
                    </a>
                </div>
            </div>

            <div class="page-body">

                <!-- Stat Cards -->
                <div class="stat-row">
                    <div class="stat-card">
                        <div class="stat-label"><i class="fas fa-list-check"></i> Total workload</div>
                        <div class="stat-num"><?= $stats['total_tasks'] ?></div>
                        <div class="stat-sub sub-slate">All assigned tasks</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label"><i class="fas fa-spinner"></i> In progress</div>
                        <div class="stat-num"><?= $stats['in_progress_tasks'] ?></div>
                        <div class="stat-sub sub-indigo">Active operations</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label"><i class="fas fa-circle-check"></i> Completed</div>
                        <div class="stat-num"><?= $stats['complete_tasks'] ?></div>
                        <div class="stat-sub sub-green">Finalized tasks</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label"><i class="fas fa-clock"></i> Pending</div>
                        <div class="stat-num"><?= $stats['pending_tasks'] ?></div>
                        <div class="stat-sub sub-amber">Awaiting start</div>
                    </div>
                </div>

                <!-- Top Charts -->
                <div class="charts-top">
                    <div class="card">
                        <div class="card-title"><i class="fas fa-chart-bar"></i> Monthly task completion</div>
                        <div class="legend">
                            <span><span class="leg-sq" style="background:#6366f1"></span> Assigned</span>
                            <span><span class="leg-sq" style="background:#10b981"></span> Completed</span>
                        </div>
                        <div style="position:relative;height:220px">
                            <canvas id="monthlyChart" role="img" aria-label="Monthly bar chart comparing assigned vs completed tasks">Monthly task data.</canvas>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-title"><i class="fas fa-gauge-high"></i> Performance score</div>
                        <div class="gauge-wrap">
                            <canvas id="gaugeChart" role="img" aria-label="Gauge chart showing task completion percentage">Completion rate.</canvas>
                            <div class="gauge-center">
                                <div class="gauge-val"><?= $efficiency ?>%</div>
                                <div class="gauge-lbl">Closed</div>
                            </div>
                        </div>
                        <div class="detail-row"><span class="detail-key">Current role</span><span class="detail-val"><?= strtoupper($user['role']) ?></span></div>
                        <div class="detail-row"><span class="detail-key">Total tasks</span><span class="detail-val"><?= $stats['total_tasks'] ?></span></div>
                        <div class="detail-row"><span class="detail-key">Completion rate</span><span class="detail-val"><?= $efficiency ?>%</span></div>
                    </div>
                </div>

                <!-- Bottom Charts -->
                <div class="charts-bottom">
                    <div class="card">
                        <div class="card-title"><i class="fas fa-chart-line"></i> Weekly activity</div>
                        <div style="position:relative;height:170px">
                            <canvas id="lineChart" role="img" aria-label="Line chart of task activity over last 7 days">Weekly activity.</canvas>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-title"><i class="fas fa-chart-pie"></i> Task breakdown</div>
                        <div style="position:relative;height:140px">
                            <canvas id="pieChart" role="img" aria-label="Doughnut chart of task status distribution">Task distribution.</canvas>
                        </div>
                        <div class="legend" style="margin-top:10px;margin-bottom:0">
                            <span><span class="leg-sq" style="background:#10b981"></span> Complete</span>
                            <span><span class="leg-sq" style="background:#6366f1"></span> Active</span>
                            <span><span class="leg-sq" style="background:#f59e0b"></span> Pending</span>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-title"><i class="fas fa-medal"></i> Skill metrics</div>
                        <div class="pb-row">
                            <div class="pb-meta"><span class="pb-key">Completion rate</span><span class="pb-val"><?= $efficiency ?>%</span></div>
                            <div class="pb-track"><div class="pb-fill" style="width:<?= $efficiency ?>%;background:#10b981"></div></div>
                        </div>
                        <?php
                        $ontime   = $stats['total_tasks'] > 0 ? min(100, round(($stats['complete_tasks'] / $stats['total_tasks']) * 120)) : 0;
                        $velocity = $stats['total_tasks'] > 0 ? min(100, round(($stats['in_progress_tasks'] + $stats['complete_tasks']) / $stats['total_tasks'] * 100)) : 0;
                        ?>
                        <div class="pb-row">
                            <div class="pb-meta"><span class="pb-key">On-time delivery</span><span class="pb-val"><?= $ontime ?>%</span></div>
                            <div class="pb-track"><div class="pb-fill" style="width:<?= $ontime ?>%;background:#6366f1"></div></div>
                        </div>
                        <div class="pb-row">
                            <div class="pb-meta"><span class="pb-key">Task velocity</span><span class="pb-val"><?= $velocity ?>%</span></div>
                            <div class="pb-track"><div class="pb-fill" style="width:<?= $velocity ?>%;background:#f59e0b"></div></div>
                        </div>
                        <div class="pb-row">
                            <div class="pb-meta"><span class="pb-key">Active workload</span><span class="pb-val"><?= $stats['total_tasks'] > 0 ? round($stats['in_progress_tasks'] / $stats['total_tasks'] * 100) : 0 ?>%</span></div>
                            <div class="pb-track"><div class="pb-fill" style="width:<?= $stats['total_tasks'] > 0 ? round($stats['in_progress_tasks'] / $stats['total_tasks'] * 100) : 0 ?>%;background:#3b82f6"></div></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
const gridColor = '#f1f5f9';
const tickColor = '#94a3b8';

document.addEventListener('DOMContentLoaded', function () {

    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: {
            labels: <?= $monthly_l_json ?>,
            datasets: [
                { label: 'Assigned',  data: <?= $monthly_a_json ?>, backgroundColor: '#6366f1', borderRadius: 5, barPercentage: 0.4, categoryPercentage: 0.7 },
                { label: 'Completed', data: <?= $monthly_c_json ?>, backgroundColor: '#10b981', borderRadius: 5, barPercentage: 0.5, categoryPercentage: 0.7 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, font: { size: 11 } } },
                x: { grid: { display: false },              ticks: { color: tickColor, font: { size: 11 } } }
            }
        }
    });

    new Chart(document.getElementById('gaugeChart'), {
        type: 'doughnut',
        data: {
            datasets: [{
                data: <?= $gauge_data ?>,
                backgroundColor: ['#10b981', '#f1f5f9'],
                borderWidth: 0,
                circumference: 180,
                rotation: 270,
                cutout: '80%'
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: false } }
        }
    });

    new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: {
            labels: <?= $weekly_l_json ?>,
            datasets: [{
                label: 'Activity',
                data: <?= $weekly_d_json ?>,
                borderColor: '#6366f1',
                backgroundColor: '#ede9fe',
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: '#6366f1',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, font: { size: 10 } } },
                x: { grid: { display: false },              ticks: { color: tickColor, font: { size: 10 } } }
            }
        }
    });

    new Chart(document.getElementById('pieChart'), {
        type: 'doughnut',
        data: {
            labels: ['Complete', 'In Progress', 'Pending'],
            datasets: [{
                data: <?= $pie_data ?>,
                backgroundColor: ['#10b981', '#6366f1', '#f59e0b'],
                borderWidth: 0,
                cutout: '65%'
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });

});
</script>
</body>
</html>