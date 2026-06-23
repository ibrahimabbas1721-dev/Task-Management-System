<?php
include '../config/db.php';
requireLogin();

$user_id = $_SESSION['user_id'];

$userQuery = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$userQuery->execute([$user_id]);
$user = $userQuery->fetch(PDO::FETCH_ASSOC);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status != 'complete'");
$countStmt->execute([$user_id]);
$userPendingCount = $countStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT t.*, p.project_name, p.id as p_id
    FROM tasks t
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE t.assigned_to = ?
    ORDER BY t.status ASC, t.due_date ASC
");
$stmt->execute([$user_id]);
$tasks = $stmt->fetchAll();

// Pre-process tasks for JS calendar
$tasksJson = [];
foreach ($tasks as $t) {
    $rawStatus = strtolower(trim(str_replace(' ', '_', $t['status'])));
    $dueTs     = strtotime($t['due_date']);
    // complete tasks are NEVER overdue
    $isOver    = ($dueTs < strtotime('today') && $rawStatus !== 'complete');
    $tasksJson[] = [
        'id'        => $t['id'],
        'title'     => $t['title'],
        'project'   => $t['project_name'] ?? '',
        'p_id'      => $t['p_id'] ?? '',
        'due'       => date('Y-m-d', $dueTs),
        'status'    => $rawStatus,
        'isOverdue' => $isOver,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Task Force | <?= htmlspecialchars($user['username']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
<style>
/* ─────────────────────────────────────────────
   TOKENS
───────────────────────────────────────────── */
:root {
    --bg:          #f1f5f9;
    --sidebar-w:   260px;
    --primary:     #6366f1;
    --primary-lt:  rgba(99,102,241,.12);
    --dark:        #0f172a;
    --card:        #ffffff;
    --border:      #e2e8f0;
    --text:        #1e293b;
    --muted:       #64748b;
    --c-pending:   #f59e0b;
    --c-active:    #6366f1;
    --c-done:      #10b981;
    --c-overdue:   #ef4444;
    --radius-card: 20px;
    --shadow:      0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.04);
}

*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;font-size:14px;}

/* ─────────────────────────────────────────────
   LAYOUT
───────────────────────────────────────────── */
.main-wrapper{flex:1;display:flex;flex-direction:column;min-width:0;margin-left:var(--sidebar-w);transition:margin-left .3s;}
.container{padding:28px 32px;max-width:1400px;width:100%;margin:0 auto;}

/* ─────────────────────────────────────────────
   FLASH
───────────────────────────────────────────── */
.flash{padding:14px 20px;border-radius:14px;margin-bottom:20px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:10px;}
.flash-success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}
.flash-error  {background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}

/* ─────────────────────────────────────────────
   BANNER
───────────────────────────────────────────── */
.banner{
    background:var(--dark);
    border-radius:24px;
    padding:32px 40px;
    color:#fff;
    position:relative;
    overflow:hidden;
    margin-bottom:24px;
    display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:20px;
}
.banner::before{
    content:'';position:absolute;top:-80px;right:-80px;
    width:320px;height:320px;
    background:radial-gradient(circle,rgba(99,102,241,.35) 0%,transparent 70%);
    pointer-events:none;
}
.banner-left .tag{font-size:9px;font-weight:900;letter-spacing:2px;text-transform:uppercase;color:var(--primary);margin-bottom:6px;display:block;}
.banner-left h1{font-size:clamp(22px,4vw,34px);font-weight:900;font-style:italic;text-transform:uppercase;line-height:1.1;}
.banner-left h1 span{color:var(--primary);}
.banner-right{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}

/* View Toggle */
.view-toggle{display:flex;background:rgba(255,255,255,.1);border-radius:12px;padding:4px;gap:4px;}
.view-btn{background:transparent;border:none;color:rgba(255,255,255,.5);padding:8px 14px;border-radius:9px;cursor:pointer;font-size:13px;font-weight:700;transition:all .2s;display:flex;align-items:center;gap:6px;}
.view-btn.active{background:var(--primary);color:#fff;}
.view-btn:hover:not(.active){color:#fff;background:rgba(255,255,255,.1);}

/* ─────────────────────────────────────────────
   FILTERS
───────────────────────────────────────────── */
.filters-bar{
    background:var(--card);
    border-radius:16px;
    padding:16px 20px;
    margin-bottom:16px;
    border:1px solid var(--border);
    display:flex;align-items:center;flex-wrap:wrap;gap:12px;
    box-shadow:var(--shadow);
}
.filter-group{display:flex;flex-direction:column;gap:4px;flex:1;min-width:160px;}
.filter-label{font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:1.5px;color:var(--muted);}
.filter-input,.filter-select{
    border:1px solid var(--border);border-radius:10px;
    padding:9px 13px;font-size:13px;font-family:inherit;color:var(--text);background:#f8fafc;
    transition:border-color .2s,box-shadow .2s;outline:none;
}
.filter-input:focus,.filter-select:focus{border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-lt);}
.filter-select{cursor:pointer;}
.btn-reset{
    background:var(--dark);color:#fff;border:none;
    padding:9px 18px;border-radius:10px;font-size:12px;font-weight:800;
    cursor:pointer;transition:all .2s;white-space:nowrap;display:flex;align-items:center;gap:7px;
    align-self:flex-end;
}
.btn-reset:hover{background:var(--primary);}

/* Status Tabs */
.status-tabs{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:18px;}
.tab-btn{
    background:transparent;border:1.5px solid var(--border);
    padding:7px 16px;border-radius:100px;font-size:12px;font-weight:700;
    cursor:pointer;transition:all .2s;color:var(--muted);display:flex;align-items:center;gap:6px;
}
.tab-btn.active{background:var(--primary);border-color:var(--primary);color:#fff;}
.tab-btn:hover:not(.active){border-color:var(--primary);color:var(--primary);}
.tab-count{
    background:rgba(0,0,0,.08);padding:1px 7px;border-radius:100px;
    font-size:10px;font-weight:900;
}
.tab-btn.active .tab-count{background:rgba(255,255,255,.25);}
.tab-dot{width:7px;height:7px;border-radius:50%;}

/* ─────────────────────────────────────────────
   TABLE VIEW
───────────────────────────────────────────── */
.table-wrap{background:var(--card);border-radius:var(--radius-card);border:1px solid var(--border);overflow:hidden;box-shadow:var(--shadow);}

table{width:100%;border-collapse:collapse;}
thead tr{border-bottom:1px solid var(--border);}
thead th{
    padding:12px 20px;font-size:10px;font-weight:900;
    text-transform:uppercase;letter-spacing:1.2px;color:var(--muted);text-align:left;
    background:#fafbfc;
}
thead th.tc{text-align:center;}
thead th.tr{text-align:right;}

.task-row{border-bottom:1px solid #f1f5f9;transition:background .15s;}
.task-row:last-child{border-bottom:none;}
.task-row:hover{background:#f8fafc;}
.task-row td{padding:16px 20px;vertical-align:middle;}

/* Identity cell */
.task-identity{display:flex;align-items:center;gap:13px;}
.task-icon{
    width:42px;height:42px;border-radius:13px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;
    font-weight:900;font-size:15px;text-transform:uppercase;
}
.task-title{font-size:13.5px;font-weight:800;text-transform:uppercase;color:var(--dark);display:block;line-height:1.2;margin-bottom:3px;}
.project-link{font-size:9px;font-weight:900;color:var(--primary);text-decoration:none;letter-spacing:.5px;}
.project-link:hover{opacity:.7;}

/* Intel */
.intel{color:var(--muted);font-size:13px;line-height:1.5;max-width:380px;}

/* Date */
.date-cell{display:flex;flex-direction:column;align-items:center;gap:3px;}
.date-val{font-weight:800;font-size:13px;}
.date-val.is-red  {color:var(--c-overdue);}
.date-val.is-green{color:var(--c-done);}
.date-val.is-black{color:var(--text);}
.overdue-badge{
    font-size:8px;font-weight:900;letter-spacing:.5px;
    color:var(--c-overdue);text-transform:uppercase;
    display:flex;align-items:center;gap:3px;
    animation:blink 2s infinite;
}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:.4;}}

/* Status Pill */
.pill{
    display:inline-flex;align-items:center;gap:6px;
    padding:5px 12px;border-radius:100px;
    font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.5px;
    border:1.5px solid;
}
.pill-dot{width:6px;height:6px;border-radius:50%;}

/* PILL COLOR VARIANTS */
.pill-done    {color:var(--c-done);   border-color:rgba(16,185,129,.3); background:rgba(16,185,129,.08);}
.pill-done    .pill-dot{background:var(--c-done);}
.pill-active  {color:var(--c-active); border-color:rgba(99,102,241,.3); background:rgba(99,102,241,.08);}
.pill-active  .pill-dot{background:var(--c-active);}
.pill-pending {color:var(--c-pending);border-color:rgba(245,158,11,.3);  background:rgba(245,158,11,.08);}
.pill-pending .pill-dot{background:var(--c-pending);}
.pill-overdue {color:var(--c-overdue);border-color:rgba(239,68,68,.3);  background:rgba(239,68,68,.08);}
.pill-overdue .pill-dot{background:var(--c-overdue);}

/* ROW LEFT-BORDER accent */
.row-done   {border-left:3px solid var(--c-done);}
.row-active {border-left:3px solid var(--c-active);}
.row-pending{border-left:3px solid var(--c-pending);}
.row-overdue{border-left:3px solid var(--c-overdue);}

/* ICON bg */
.icon-done   {background:rgba(16,185,129,.12); color:var(--c-done);}
.icon-active {background:rgba(99,102,241,.12); color:var(--c-active);}
.icon-pending{background:rgba(245,158,11,.12); color:var(--c-pending);}
.icon-overdue{background:rgba(239,68,68,.12);  color:var(--c-overdue);}

/* complete row subtle green wash */
.row-done td { background: rgba(16,185,129,.03); }
.row-done:hover td { background: rgba(16,185,129,.06) !important; }

/* Action btn */
.btn-action{
    background:var(--dark);color:#fff;text-decoration:none;
    padding:9px 16px;border-radius:10px;font-size:10px;font-weight:900;
    text-transform:uppercase;display:inline-flex;align-items:center;gap:7px;
    transition:all .2s;white-space:nowrap;
}
.btn-action:hover{background:var(--primary);transform:translateY(-1px);}

/* ─────────────────────────────────────────────
   EMPTY STATE
───────────────────────────────────────────── */
.empty{
    background:var(--card);border-radius:var(--radius-card);
    padding:70px 20px;text-align:center;border:1px solid var(--border);
}
.empty i{font-size:42px;color:#cbd5e1;margin-bottom:14px;}
.empty h3{font-weight:900;text-transform:uppercase;font-size:16px;margin-bottom:6px;}
.empty p{color:var(--muted);font-size:10px;font-weight:800;letter-spacing:2px;}

/* ─────────────────────────────────────────────
   CALENDAR VIEW
───────────────────────────────────────────── */
#calendar-view{display:none;}
.cal-card{background:var(--card);border-radius:var(--radius-card);border:1px solid var(--border);overflow:hidden;box-shadow:var(--shadow);}
.cal-header{
    display:flex;align-items:center;justify-content:space-between;
    padding:20px 28px;border-bottom:1px solid var(--border);
    background:#fafbfc;
}
.cal-title{font-size:18px;font-weight:900;text-transform:uppercase;color:var(--dark);}
.cal-nav{display:flex;gap:8px;}
.cal-nav-btn{
    background:transparent;border:1.5px solid var(--border);
    width:36px;height:36px;border-radius:10px;cursor:pointer;
    font-size:13px;color:var(--muted);transition:all .2s;
    display:flex;align-items:center;justify-content:center;
}
.cal-nav-btn:hover{border-color:var(--primary);color:var(--primary);}
.cal-legend{display:flex;gap:12px;flex-wrap:wrap;}
.leg-item{display:flex;align-items:center;gap:5px;font-size:10px;font-weight:700;color:var(--muted);}
.leg-dot{width:8px;height:8px;border-radius:50%;}

.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);}
.cal-day-name{
    padding:12px 8px;text-align:center;
    font-size:10px;font-weight:900;text-transform:uppercase;
    color:var(--muted);letter-spacing:1px;border-bottom:1px solid var(--border);
}
.cal-cell{
    min-height:110px;padding:8px;border-right:1px solid #f1f5f9;border-bottom:1px solid #f1f5f9;
    transition:background .15s;
}
.cal-cell:hover{background:#f8fafc;}
.cal-cell:nth-child(7n){border-right:none;}
.cal-num{
    font-size:12px;font-weight:700;color:var(--muted);
    width:26px;height:26px;display:flex;align-items:center;justify-content:center;
    border-radius:8px;margin-bottom:6px;
}
.cal-num.today{background:var(--primary);color:#fff;font-weight:900;}
.cal-num.other-month{color:#cbd5e1;}
.cal-chip{
    display:block;padding:3px 7px;border-radius:6px;
    font-size:9px;font-weight:800;text-transform:uppercase;
    cursor:pointer;text-decoration:none;margin-bottom:3px;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    transition:opacity .2s;position:relative;
}
.cal-chip:hover{opacity:.8;}
.chip-done   {background:rgba(16,185,129,.15);  color:#065f46;}
.chip-active {background:rgba(99,102,241,.15);  color:#3730a3;}
.chip-pending{background:rgba(245,158,11,.15);  color:#92400e;}
.chip-overdue{background:rgba(239,68,68,.15);   color:#991b1b;}

/* ─────────────────────────────────────────────
   RESPONSIVE
───────────────────────────────────────────── */
@media(max-width:900px){
    .main-wrapper{margin-left:0;}
    .container{padding:16px;}
    .banner{padding:24px;}
    .intel{display:none;}
    thead th:nth-child(2){display:none;}
    .task-row td:nth-child(2){display:none;}
}
@media(max-width:600px){
    .filter-group{min-width:100%;}
    .cal-chip{display:none;}
    .cal-cell{min-height:60px;}
}
</style>
</head>
<body>
<?php include '../includes/user_sidebar.php'; ?>

<div class="main-wrapper">
<?php include '../includes/user_header.php'; ?>

<main class="container">

    <!-- Flash Message -->
    <?php if(isset($_SESSION['message'])): ?>
    <div class="flash flash-<?= $_SESSION['message']['type'] ?>">
        <i class="fa-solid <?= $_SESSION['message']['type']==='success' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
        <?= htmlspecialchars($_SESSION['message']['text']) ?>
    </div>
    <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <!-- BANNER -->
    <header class="banner">
        <div class="banner-left">
            <span class="tag">&gt;_ Operation_Log / Active_Deployment</span>
            <h1>My Assigned <span>Tasks</span></h1>
        </div>
        <div class="banner-right">
            <div class="view-toggle">
                <button class="view-btn active" id="btn-list" onclick="switchView('list')">
                    <i class="fa-solid fa-list-ul"></i> List View
                </button>
                <button class="view-btn" id="btn-cal" onclick="switchView('calendar')">
                    <i class="fa-solid fa-calendar-days"></i> Calendar
                </button>
            </div>
        </div>
    </header>

    <!-- FILTERS BAR -->
    <div class="filters-bar" id="filter-bar">
        <div class="filter-group">
            <span class="filter-label">Search Task</span>
            <input type="text" class="filter-input" id="f-search" placeholder="Type task name..." oninput="applyFilters()"/>
        </div>
        <div class="filter-group" style="max-width:200px;">
            <span class="filter-label">Status</span>
            <select class="filter-select" id="f-status" onchange="applyFilters()">
                <option value="">All Statuses</option>
                <option value="complete">complete</option>
                <option value="in_progress">In Progress</option>
                <option value="pending">Pending</option>
                <option value="overdue">Overdue</option>
            </select>
        </div>
        <div class="filter-group" style="max-width:200px;">
            <span class="filter-label">Project</span>
            <select class="filter-select" id="f-project" onchange="applyFilters()">
                <option value="">All Projects</option>
                <?php
                $uniqueProjects = [];
                foreach($tasks as $t){
                    if(!empty($t['project_name']) && !in_array($t['project_name'],$uniqueProjects)){
                        $uniqueProjects[] = $t['project_name'];
                        echo '<option value="'.htmlspecialchars($t['project_name']).'">'.htmlspecialchars($t['project_name']).'</option>';
                    }
                }
                ?>
            </select>
        </div>
        <button class="btn-reset" onclick="resetFilters()">
            <i class="fa-solid fa-rotate-left"></i> Reset
        </button>
    </div>

    <!-- STATUS TABS -->
    <?php
    $total    = count($tasks);
    $cDone    = 0; $cActive = 0; $cPending = 0; $cOverdue = 0;
    foreach($tasks as $t){
        $ns = strtolower(trim(str_replace(' ','_',$t['status'])));
        // complete tasks are NEVER counted as overdue
        $od = ($ns !== 'complete') && (strtotime($t['due_date']) < strtotime('today'));
        if($ns === 'complete')   $cDone++;
        elseif($od)               $cOverdue++;
        elseif($ns === 'in_progress') $cActive++;
        else                      $cPending++;
    }
    ?>
    <div class="status-tabs" id="status-tabs">
        <button class="tab-btn active" data-tab="all" onclick="setTab(this,'all')">
            All Tasks <span class="tab-count"><?= $total ?></span>
        </button>
        <button class="tab-btn" data-tab="in_progress" onclick="setTab(this,'in_progress')">
            <span class="tab-dot" style="background:var(--c-active);"></span>
            In Progress <span class="tab-count"><?= $cActive ?></span>
        </button>
        <button class="tab-btn" data-tab="pending" onclick="setTab(this,'pending')">
            <span class="tab-dot" style="background:var(--c-pending);"></span>
            Pending <span class="tab-count"><?= $cPending ?></span>
        </button>
        <button class="tab-btn" data-tab="complete" onclick="setTab(this,'complete')">
            <span class="tab-dot" style="background:var(--c-done);"></span>
            complete <span class="tab-count"><?= $cDone ?></span>
        </button>
        <button class="tab-btn" data-tab="overdue" onclick="setTab(this,'overdue')">
            <span class="tab-dot" style="background:var(--c-overdue);"></span>
            Overdue <span class="tab-count"><?= $cOverdue ?></span>
        </button>
    </div>

    <!-- LIST VIEW -->
    <div id="list-view">
        <?php if(empty($tasks)): ?>
            <div class="empty">
                <i class="fa-solid fa-inbox"></i>
                <h3>Queue Empty</h3>
                <p>AWAITING NEW ORDERS</p>
            </div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Protocol / Project</th>
                        <th>Intelligence</th>
                        <th class="tc">Deadline</th>
                        <th class="tc">Status</th>
                        <th class="tr">Management</th>
                    </tr>
                </thead>
                <tbody id="task-tbody">
                <?php foreach($tasks as $task):
                    // ── NORMALIZE STATUS ──────────────────────────────────
                    $rawStatus = strtolower(trim(str_replace(' ', '_', $task['status'])));

                    $dueTs = strtotime($task['due_date']);
                    $today = strtotime('today');

                    // ── complete ALWAYS WINS — force $isOverdue false ────
                    // A complete task is NEVER overdue, regardless of due date
                    if ($rawStatus === 'complete') {
                        $isOverdue = false;
                        $rowClass  = 'row-done';
                        $iconClass = 'icon-done';
                        $pillClass = 'pill-done';
                    } elseif ($dueTs < $today) {
                        // Not complete + past due = overdue
                        $isOverdue = true;
                        $rowClass  = 'row-overdue';
                        $iconClass = 'icon-overdue';
                        $pillClass = 'pill-overdue';
                    } elseif ($rawStatus === 'in_progress') {
                        $isOverdue = false;
                        $rowClass  = 'row-active';
                        $iconClass = 'icon-active';
                        $pillClass = 'pill-active';
                    } else {
                        $isOverdue = false;
                        $rowClass  = 'row-pending';
                        $iconClass = 'icon-pending';
                        $pillClass = 'pill-pending';
                    }

                    // ── DATE COLOR ────────────────────────────────────────
                    // Green for complete (even if past due), red for overdue, black otherwise
                    if ($rawStatus === 'complete') {
                        $dateClass = 'is-green';
                    } elseif ($isOverdue) {
                        $dateClass = 'is-red';
                    } else {
                        $dateClass = 'is-black';
                    }

                    // ── STATUS LABEL ──────────────────────────────────────
                    $statusLabel = strtoupper(str_replace('_', ' ', $rawStatus));
                    if(empty($statusLabel)) $statusLabel = 'PENDING';

                    // ── DATA ATTRIBUTES for JS filtering ─────────────────
                    $dataStatus  = $isOverdue ? 'overdue' : $rawStatus;
                    $dataProject = htmlspecialchars($task['project_name'] ?? '');
                    $dataTitle   = htmlspecialchars(strtolower($task['title']));
                ?>
                <tr class="task-row <?= $rowClass ?>"
                    data-status="<?= $dataStatus ?>"
                    data-project="<?= $dataProject ?>"
                    data-title="<?= $dataTitle ?>">

                    <!-- Protocol / Project -->
                    <td>
                        <div class="task-identity">
                            <div class="task-icon <?= $iconClass ?>">
                                <?= htmlspecialchars(strtoupper(substr($task['title'],0,1))) ?>
                            </div>
                            <div>
                                <span class="task-title"><?= htmlspecialchars($task['title']) ?></span>
                                <?php if(!empty($task['p_id'])): ?>
                                <a href="view_project.php?id=<?= $task['p_id'] ?>" class="project-link">
                                    # <?= htmlspecialchars($task['project_name']) ?>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>

                    <!-- Intelligence -->
                    <td>
                        <p class="intel"><?= htmlspecialchars($task['description'] ?: 'NO_INTEL_LOGGED') ?></p>
                    </td>

                    <!-- Deadline -->
                    <td style="text-align:center;">
                        <div class="date-cell">
                            <span class="date-val <?= $dateClass ?>">
                                <?= date('M d, Y', $dueTs) ?>
                            </span>
                            <?php if($isOverdue): ?>
                            <span class="overdue-badge">
                                <i class="fa-solid fa-triangle-exclamation" style="font-size:9px;"></i>
                                OVERDUE
                            </span>
                            <?php endif; ?>
                        </div>
                    </td>

                    <!-- Status -->
                    <td style="text-align:center;">
                        <span class="pill <?= $pillClass ?>">
                            <span class="pill-dot"></span>
                            <?= $statusLabel ?>
                        </span>
                    </td>

                    <!-- Management -->
                    <td style="text-align:right;">
                        <a href="update_task.php?id=<?= $task['id'] ?>" class="btn-action">
                            Update Task
                            <i class="fa-solid fa-pen-to-square"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- CALENDAR VIEW -->
    <div id="calendar-view">
        <div class="cal-card">
            <div class="cal-header">
                <div>
                    <div class="cal-title" id="cal-month-label">Month Year</div>
                    <div class="cal-legend" style="margin-top:8px;">
                        <span class="leg-item"><span class="leg-dot" style="background:var(--c-done);"></span>   complete</span>
                        <span class="leg-item"><span class="leg-dot" style="background:var(--c-active);"></span> In Progress</span>
                        <span class="leg-item"><span class="leg-dot" style="background:var(--c-pending);"></span>Pending</span>
                        <span class="leg-item"><span class="leg-dot" style="background:var(--c-overdue);"></span>Overdue</span>
                    </div>
                </div>
                <div class="cal-nav">
                    <button class="cal-nav-btn" onclick="calNav(-1)"><i class="fa-solid fa-chevron-left"></i></button>
                    <button class="cal-nav-btn" onclick="calNav(1)"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
            <div class="cal-grid">
                <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
                <div class="cal-day-name"><?= $d ?></div>
                <?php endforeach; ?>
            </div>
            <div class="cal-grid" id="cal-body"></div>
        </div>
    </div>

</main>
</div>

<script>
/* ─────────────────────────────────────────────
   TASKS DATA (from PHP)
───────────────────────────────────────────── */
const TASKS = <?= json_encode($tasksJson, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

/* ─────────────────────────────────────────────
   VIEW TOGGLE
───────────────────────────────────────────── */
function switchView(v){
    document.getElementById('list-view').style.display     = v==='list'     ? 'block' : 'none';
    document.getElementById('calendar-view').style.display = v==='calendar' ? 'block' : 'none';
    document.getElementById('filter-bar').style.display    = v==='list'     ? 'flex'  : 'none';
    document.getElementById('status-tabs').style.display   = v==='list'     ? 'flex'  : 'none';
    document.getElementById('btn-list').classList.toggle('active', v==='list');
    document.getElementById('btn-cal').classList.toggle('active',  v==='calendar');
}

/* ─────────────────────────────────────────────
   FILTERS
───────────────────────────────────────────── */
let activeTab = 'all';

function setTab(el, tab){
    activeTab = tab;
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    applyFilters();
}

function applyFilters(){
    const search  = document.getElementById('f-search').value.toLowerCase().trim();
    const status  = document.getElementById('f-status').value.toLowerCase();
    const project = document.getElementById('f-project').value.toLowerCase();

    document.querySelectorAll('#task-tbody .task-row').forEach(row => {
        const rTitle   = row.dataset.title   || '';
        const rStatus  = row.dataset.status  || '';
        const rProject = row.dataset.project.toLowerCase() || '';

        const tabMatch     = activeTab === 'all' || rStatus === activeTab;
        const searchMatch  = !search  || rTitle.includes(search);
        const statusMatch  = !status  || rStatus === status;
        const projectMatch = !project || rProject === project;

        row.style.display = (tabMatch && searchMatch && statusMatch && projectMatch) ? '' : 'none';
    });
}

function resetFilters(){
    document.getElementById('f-search').value  = '';
    document.getElementById('f-status').value  = '';
    document.getElementById('f-project').value = '';
    activeTab = 'all';
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('[data-tab="all"]').classList.add('active');
    applyFilters();
}

/* ─────────────────────────────────────────────
   CALENDAR
───────────────────────────────────────────── */
const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
let calYear, calMonth;

function initCalendar(){
    const now = new Date();
    calYear  = now.getFullYear();
    calMonth = now.getMonth();
    renderCalendar();
}

function calNav(dir){
    calMonth += dir;
    if(calMonth > 11){ calMonth = 0; calYear++; }
    if(calMonth <  0){ calMonth = 11; calYear--; }
    renderCalendar();
}

function getChipClass(task){
    // complete ALWAYS green — never red, regardless of due date
    if(task.status === 'complete') return 'chip-done';
    if(task.isOverdue)              return 'chip-overdue';
    if(task.status === 'in_progress') return 'chip-active';
    return 'chip-pending';
}

function renderCalendar(){
    document.getElementById('cal-month-label').textContent = MONTHS[calMonth] + ' ' + calYear;

    const firstDay  = new Date(calYear, calMonth, 1).getDay();
    const daysInMon = new Date(calYear, calMonth+1, 0).getDate();
    const prevDays  = new Date(calYear, calMonth, 0).getDate();
    const todayStr  = new Date().toISOString().slice(0,10);

    const body = document.getElementById('cal-body');
    body.innerHTML = '';

    // Group tasks by due date
    const taskMap = {};
    TASKS.forEach(t => {
        if(!taskMap[t.due]) taskMap[t.due] = [];
        taskMap[t.due].push(t);
    });

    const totalCells = Math.ceil((firstDay + daysInMon) / 7) * 7;

    for(let i = 0; i < totalCells; i++){
        const cell = document.createElement('div');
        cell.className = 'cal-cell';

        let dayNum, dateStr, isOtherMonth = false;

        if(i < firstDay){
            dayNum = prevDays - firstDay + i + 1;
            const y  = calMonth === 0 ? calYear-1 : calYear;
            const mm = calMonth === 0 ? '12' : String(calMonth).padStart(2,'0');
            const d  = String(dayNum).padStart(2,'0');
            dateStr = y+'-'+mm+'-'+d;
            isOtherMonth = true;
        } else if(i >= firstDay + daysInMon){
            dayNum = i - firstDay - daysInMon + 1;
            const y  = calMonth === 11 ? calYear+1 : calYear;
            const mm = calMonth === 11 ? '01' : String(calMonth+2).padStart(2,'0');
            const d  = String(dayNum).padStart(2,'0');
            dateStr = y+'-'+mm+'-'+d;
            isOtherMonth = true;
        } else {
            dayNum  = i - firstDay + 1;
            const mm = String(calMonth+1).padStart(2,'0');
            const dd = String(dayNum).padStart(2,'0');
            dateStr = calYear+'-'+mm+'-'+dd;
        }

        const numDiv = document.createElement('div');
        numDiv.className = 'cal-num'
            + (dateStr === todayStr ? ' today' : '')
            + (isOtherMonth ? ' other-month' : '');
        numDiv.textContent = dayNum;
        cell.appendChild(numDiv);

        if(taskMap[dateStr]){
            taskMap[dateStr].forEach(t => {
                const chip = document.createElement('a');
                chip.href      = 'update_task.php?id=' + t.id;
                chip.className = 'cal-chip ' + getChipClass(t);
                chip.textContent = t.title;
                chip.title = t.title
                    + (t.project ? ' | ' + t.project : '')
                    + ' | ' + t.due
                    + ' | ' + t.status.toUpperCase().replace('_',' ');
                cell.appendChild(chip);
            });
        }

        body.appendChild(cell);
    }
}

// Init
initCalendar();
</script>
</body>
</html>