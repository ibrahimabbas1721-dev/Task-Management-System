<?php
include '../config/db.php';
requireLogin();
requireRole('admin');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$adminId = $_SESSION['user_id'];
$activePage = 'projects';

// Handle project deletion
if (!empty($_GET['delete_id'])) {
    try {
        $deleteQuery = $pdo->prepare("DELETE FROM projects WHERE id = ? AND created_by_admin = ?");
        $deleteQuery->execute([$_GET['delete_id'], $adminId]);
        header("Location: projects.php?msg=deleted");
        exit();
    } catch (PDOException $ex) {
        $error = "Action failed.";
    }
}

// Retrieve projects with task counts
try {
    $query = $pdo->prepare(
        "SELECT p.id, p.project_name, p.description, p.plan_type, p.status,
            COUNT(t.id) AS total_tasks,
            SUM(CASE WHEN t.status = 'complete' THEN 1 ELSE 0 END) AS complete_tasks
         FROM projects p
         LEFT JOIN tasks t ON p.id = t.project_id
         WHERE p.created_by_admin = ?
         GROUP BY p.id
         ORDER BY p.id DESC"
    );
    $query->execute([$adminId]);
    $projects = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    $projects = [];
}

$totalProjects   = count($projects);
$activeProjects  = count(array_filter($projects, fn($p) => ($p['status'] ?? 'active') === 'active'));
$onHoldProjects  = count(array_filter($projects, fn($p) => ($p['status'] ?? '') === 'on_hold'));
$completeProjects= count(array_filter($projects, fn($p) => ($p['status'] ?? '') === 'complete'));
$totalTasks      = array_sum(array_column($projects, 'total_tasks'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Projects | TMS Pro</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="../fontawesome/css/all.min.css"/>
<style>
/* ════════════════════════════════════════
   RESET & TOKENS
════════════════════════════════════════ */
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --brand:#6366f1;--brand-d:#4f46e5;--brand-l:#818cf8;--brand-soft:#eef2ff;
  --success:#10b981;--success-bg:#ecfdf5;--success-border:#a7f3d0;
  --warning:#f59e0b;--warning-bg:#fef3c7;--warning-border:#fde68a;
  --danger:#ef4444;--danger-bg:#fef2f2;--danger-border:#fecaca;
  --purple:#8b5cf6;--purple-bg:#f5f3ff;
  --ink:#0d1117;--ink-2:#1e293b;--ink-3:#334155;
  --muted:#64748b;--muted-2:#94a3b8;--muted-3:#cbd5e1;
  --bg:#f1f4f9;--surface:#fff;--border:#e2e8f0;--border-2:#f1f5f9;
  --deep:#0f172a;
  --r:14px;--r-lg:20px;--r-xl:26px;
  --sh-sm:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
  --sh:0 4px 16px rgba(0,0,0,.07);
  --sh-lg:0 12px 40px rgba(0,0,0,.1);
  --tr:.18s ease;
}
html,body{height:100%;overflow-x:hidden}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--ink);display:flex}

/* ════════════════════════════════════════
   LAYOUT
════════════════════════════════════════ */
.layout-wrapper{display:flex;height:100vh;width:100%}
.sidebar-space{width:16rem;flex-shrink:0}
.wrapper{flex:1;display:flex;flex-direction:column;overflow:hidden}
.main{flex:1;overflow-y:auto;padding:2rem 2.25rem}
.container{max-width:1440px;margin:0 auto}

/* ════════════════════════════════════════
   BANNER  (kept — only blue accent intact)
════════════════════════════════════════ */
.page-header{
  background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%);
  border-radius:var(--r-xl);padding:2rem 2.5rem;
  margin-bottom:2rem;color:white;
  box-shadow:0 8px 32px rgba(15,23,42,.35);
  position:relative;overflow:hidden;
}
.page-header::before{
  content:'';position:absolute;top:-60px;right:-60px;
  width:340px;height:340px;
  background:radial-gradient(circle,rgba(99,102,241,.22) 0%,transparent 68%);
  border-radius:50%;
}
.page-header::after{
  content:'';position:absolute;bottom:-80px;left:30%;
  width:220px;height:220px;
  background:radial-gradient(circle,rgba(129,140,248,.12) 0%,transparent 65%);
  border-radius:50%;
}
.header-content{position:relative;z-index:1}
.header-top{display:flex;justify-content:space-between;align-items:center;
  flex-wrap:wrap;gap:1.25rem;margin-bottom:1.75rem}
.header-left{display:flex;align-items:center;gap:1.1rem}
.header-icon{
  width:54px;height:54px;background:rgba(99,102,241,.2);
  border:1px solid rgba(99,102,241,.35);border-radius:14px;
  display:flex;align-items:center;justify-content:center;
  backdrop-filter:blur(8px);
}
.header-icon i{font-size:22px;color:#a5b4fc}
.header-title h1{font-size:26px;font-weight:900;letter-spacing:-.5px}
.header-title p{font-size:13px;opacity:.6;margin-top:3px}
.btn-new{
  background:var(--brand);color:white;
  padding:11px 20px;border-radius:12px;
  text-decoration:none;font-size:13px;font-weight:700;
  display:inline-flex;align-items:center;gap:8px;
  transition:var(--tr);border:none;cursor:pointer;
  box-shadow:0 4px 14px rgba(99,102,241,.4);
  font-family:inherit;
}
.btn-new:hover{background:var(--brand-d);transform:translateY(-1px);box-shadow:0 6px 18px rgba(99,102,241,.5)}
.btn-new i{font-size:13px}

/* ── FILTER BAR inside banner ── */
.filter-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.search-wrap{position:relative;flex:1;min-width:220px;max-width:340px}
.search-wrap i{
  position:absolute;left:13px;top:50%;transform:translateY(-50%);
  font-size:13px;color:rgba(255,255,255,.45);pointer-events:none;
}
.filter-input{
  width:100%;padding:10px 13px 10px 36px;
  background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14);
  border-radius:10px;color:white;font-size:13px;font-family:inherit;
  outline:none;transition:var(--tr);backdrop-filter:blur(8px);
}
.filter-input::placeholder{color:rgba(255,255,255,.42)}
.filter-input:focus{background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.28)}
.filter-select{
  padding:10px 14px;
  background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14);
  border-radius:10px;color:white;font-size:13px;font-family:inherit;
  outline:none;cursor:pointer;transition:var(--tr);
  backdrop-filter:blur(8px);min-width:140px;
}
.filter-select:focus{background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.28)}
.filter-select option{background:#1e293b;color:white}

/* ════════════════════════════════════════
   STAT CARDS ROW
════════════════════════════════════════ */
.stats-row{
  display:grid;
  grid-template-columns:repeat(5,1fr);
  gap:14px;margin-bottom:1.75rem;
}
.stat-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--r-lg);padding:1.3rem 1.5rem;
  display:flex;align-items:center;gap:1rem;
  box-shadow:var(--sh-sm);
  transition:var(--tr);cursor:default;position:relative;overflow:hidden;
}
.stat-card::after{
  content:'';position:absolute;inset:0;
  background:linear-gradient(135deg,transparent 60%,rgba(99,102,241,.03));
  pointer-events:none;
}
.stat-card:hover{transform:translateY(-2px);box-shadow:var(--sh)}
.stat-icon-wrap{
  width:46px;height:46px;border-radius:12px;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.stat-icon-wrap i{font-size:18px}
.si-all{background:rgba(99,102,241,.1)} .si-all i{color:var(--brand)}
.si-active{background:rgba(16,185,129,.1)} .si-active i{color:var(--success)}
.si-hold{background:rgba(245,158,11,.1)} .si-hold i{color:var(--warning)}
.si-done{background:rgba(139,92,246,.1)} .si-done i{color:var(--purple)}
.si-tasks{background:rgba(15,23,42,.07)} .si-tasks i{color:var(--ink-2)}
.stat-body{}
.stat-num{font-size:26px;font-weight:900;line-height:1;color:var(--ink);letter-spacing:-.5px}
.stat-lbl{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-top:3px;letter-spacing:.04em}

/* ════════════════════════════════════════
   TOOLBAR (bulk + view toggle)
════════════════════════════════════════ */
.toolbar{
  display:flex;align-items:center;justify-content:space-between;
  gap:12px;margin-bottom:14px;flex-wrap:wrap;
}
.toolbar-left{display:flex;align-items:center;gap:10px}
.result-label{font-size:13px;font-weight:700;color:var(--muted)}
.result-label span{color:var(--ink)}

.bulk-bar{
  display:none;align-items:center;gap:10px;
  background:var(--surface);border:1px solid var(--border);
  border-radius:12px;padding:10px 16px;
  box-shadow:var(--sh-sm);
}
.bulk-bar.on{display:flex}
.bulk-count{font-size:13px;font-weight:700;color:var(--ink)}
.bulk-btn{
  padding:7px 14px;border-radius:8px;font-size:12px;font-weight:700;
  border:none;cursor:pointer;font-family:inherit;
  display:flex;align-items:center;gap:6px;transition:var(--tr);
}
.bulk-del{background:var(--danger-bg);color:var(--danger)}
.bulk-del:hover{background:#fecaca}
.bulk-cancel{background:var(--bg);color:var(--muted)}
.bulk-cancel:hover{background:var(--border)}

/* View toggle */
.view-toggle{display:flex;background:#f1f5f9;border-radius:10px;padding:3px;gap:2px}
.vt-btn{
  width:34px;height:30px;border:none;background:transparent;
  border-radius:8px;cursor:pointer;font-size:13px;color:var(--muted);
  display:flex;align-items:center;justify-content:center;transition:var(--tr);
}
.vt-btn.active{background:white;color:var(--brand);box-shadow:var(--sh-sm)}
.vt-btn:hover:not(.active){color:var(--ink)}

/* ════════════════════════════════════════
   TABLE VIEW
════════════════════════════════════════ */
#tableView{}
.tbl-wrap{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--r-xl);overflow:hidden;box-shadow:var(--sh-sm);
}
.tbl-head{
  display:grid;
  grid-template-columns:42px 2.4fr 1fr 1fr 1.4fr 110px;
  gap:12px;padding:12px 20px;
  background:#f8fafc;border-bottom:1px solid var(--border);
  font-size:11px;font-weight:800;text-transform:uppercase;
  letter-spacing:.06em;color:var(--muted);align-items:center;
}
.tbl-row{
  display:grid;
  grid-template-columns:42px 2.4fr 1fr 1fr 1.4fr 110px;
  gap:12px;padding:15px 20px;
  border-bottom:1px solid var(--border-2);
  transition:background var(--tr);align-items:center;
}
.tbl-row:last-child{border-bottom:none}
.tbl-row:hover{background:#fafbff}
.tbl-row.selected{background:rgba(99,102,241,.04)}
.tbl-row.hidden{display:none}

.cb{display:flex;align-items:center;justify-content:center}
input[type=checkbox]{
  width:16px;height:16px;cursor:pointer;
  accent-color:var(--brand);border-radius:4px;
}

/* Project name cell */
.proj-name-cell{display:flex;align-items:center;gap:12px;min-width:0}
.proj-avatar{
  width:38px;height:38px;border-radius:10px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  font-size:15px;font-weight:900;color:white;
  background:linear-gradient(135deg,var(--brand),var(--brand-l));
}
.proj-detail{min-width:0}
.proj-nm{
  font-size:14px;font-weight:800;color:var(--ink);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.proj-sub{font-size:11px;color:var(--muted);margin-top:2px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

/* Plan badge */
.plan-tag{
  display:inline-block;padding:3px 9px;border-radius:5px;
  font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;
  background:var(--brand-soft);color:var(--brand);
}

/* Status badge */
.st-badge{
  display:inline-flex;align-items:center;gap:6px;
  padding:5px 11px;border-radius:7px;font-size:11px;font-weight:700;
  width:fit-content;
}
.st-badge::before{content:'';width:6px;height:6px;border-radius:50%;flex-shrink:0}
.st-badge.active{background:var(--success-bg);color:#047857}
.st-badge.active::before{background:var(--success)}
.st-badge.on_hold{background:var(--warning-bg);color:#b45309}
.st-badge.on_hold::before{background:var(--warning)}
.st-badge.complete{background:var(--purple-bg);color:#6d28d9}
.st-badge.complete::before{background:var(--purple)}

/* Tasks cell */
.tasks-cell{display:flex;flex-direction:column;gap:1px}
.tasks-num{font-size:18px;font-weight:900;color:var(--ink);line-height:1;font-family:'DM Mono',monospace}
.tasks-sub{font-size:11px;color:var(--muted);font-weight:600}

/* Progress cell */
.prog-cell{display:flex;flex-direction:column;gap:5px}
.prog-bar{width:100%;height:5px;background:#f1f5f9;border-radius:99px;overflow:hidden}
.prog-fill{
  height:100%;border-radius:99px;
  background:linear-gradient(90deg,var(--brand),var(--brand-l));
  transition:width 1s cubic-bezier(.4,0,.2,1);
}
.prog-bar.high .prog-fill{background:linear-gradient(90deg,var(--success),#34d399)}
.prog-text{font-size:11px;font-weight:700;color:var(--muted);font-family:'DM Mono',monospace}

/* Actions */
.act-cell{display:flex;gap:5px;justify-content:flex-end;align-items:center}
.act-btn{
  width:30px;height:30px;border-radius:8px;border:1px solid var(--border);
  background:white;color:var(--muted);cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  text-decoration:none;transition:var(--tr);font-size:12px;
}
.act-btn:hover{border-color:var(--brand);color:var(--brand);background:var(--brand-soft)}
.act-btn.del:hover{border-color:var(--danger);color:var(--danger);background:var(--danger-bg)}

/* More dropdown */
.more-wrap{position:relative}
.more-drop{
  position:absolute;right:0;top:36px;width:168px;
  background:white;border:1px solid var(--border);border-radius:12px;
  box-shadow:var(--sh-lg);padding:6px;z-index:200;
  display:none;animation:fadeUp .15s ease;
}
.more-drop.open{display:block}
@keyframes fadeUp{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.drop-item{
  display:flex;align-items:center;gap:9px;
  padding:9px 11px;border-radius:8px;font-size:13px;font-weight:600;
  color:var(--ink);text-decoration:none;transition:background var(--tr);
  cursor:pointer;border:none;background:none;width:100%;font-family:inherit;
}
.drop-item i{font-size:13px;color:var(--muted);width:14px;text-align:center}
.drop-item:hover{background:#f8fafc}
.drop-item.danger{color:var(--danger)}
.drop-item.danger i{color:var(--danger)}
.drop-item.danger:hover{background:var(--danger-bg)}
.drop-divider{height:1px;background:var(--border);margin:4px 0}

/* ════════════════════════════════════════
   GRID / CARD VIEW
════════════════════════════════════════ */
#gridView{display:none}
.cards-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(310px,1fr));
  gap:18px;
}
.proj-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--r-xl);padding:1.5rem;
  box-shadow:var(--sh-sm);transition:var(--tr);
  position:relative;overflow:hidden;
  display:flex;flex-direction:column;gap:14px;
}
.proj-card::before{
  content:'';position:absolute;top:0;left:0;right:0;height:3px;
  background:linear-gradient(90deg,var(--brand),var(--brand-l));
}
.proj-card.hidden{display:none}
.proj-card:hover{transform:translateY(-3px);box-shadow:var(--sh-lg)}

.card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
.card-avatar{
  width:44px;height:44px;border-radius:12px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  font-size:18px;font-weight:900;color:white;
  background:linear-gradient(135deg,var(--brand),var(--brand-l));
}
.card-name-wrap{flex:1;min-width:0}
.card-name{font-size:15px;font-weight:800;color:var(--ink);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.card-plan{font-size:10px;font-weight:800;color:var(--brand);
  text-transform:uppercase;letter-spacing:.05em;margin-top:3px}
.card-menu-btn{
  border:none;background:transparent;cursor:pointer;
  color:var(--muted);padding:4px;border-radius:6px;transition:var(--tr);
}
.card-menu-btn:hover{background:var(--bg);color:var(--ink)}

.card-desc{
  font-size:12.5px;color:var(--muted);line-height:1.6;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
}

.card-prog-row{display:flex;flex-direction:column;gap:6px}
.card-prog-top{display:flex;justify-content:space-between;align-items:center}
.card-prog-lbl{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
.card-prog-pct{font-size:13px;font-weight:900;color:var(--brand);font-family:'DM Mono',monospace}
.card-prog-bar{width:100%;height:6px;background:#f1f5f9;border-radius:99px;overflow:hidden}
.card-prog-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--brand),var(--brand-l));transition:width 1s cubic-bezier(.4,0,.2,1)}
.card-prog-bar.high .card-prog-fill{background:linear-gradient(90deg,var(--success),#34d399)}

.card-footer{display:flex;align-items:center;justify-content:space-between;
  padding-top:12px;border-top:1px solid var(--border-2);}
.card-stats{display:flex;gap:14px}
.card-stat{display:flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:var(--muted)}
.card-stat i{font-size:11px}
.card-actions{display:flex;gap:5px}

/* ════════════════════════════════════════
   EMPTY STATE
════════════════════════════════════════ */
.empty-state{
  text-align:center;padding:80px 20px;
  display:flex;flex-direction:column;align-items:center;gap:14px;
}
.empty-icon-wrap{
  width:80px;height:80px;border-radius:20px;background:#f1f5f9;
  display:flex;align-items:center;justify-content:center;
}
.empty-icon-wrap i{font-size:32px;color:var(--muted-3)}
.empty-state h3{font-size:18px;font-weight:800;color:var(--ink)}
.empty-state p{font-size:14px;color:var(--muted);max-width:300px}

/* ════════════════════════════════════════
   ACTIVE FILTER PILLS
════════════════════════════════════════ */
.active-filters{
  display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;align-items:center;
}
.af-label{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}
.af-pill{
  display:inline-flex;align-items:center;gap:6px;
  padding:5px 12px;border-radius:8px;
  background:var(--brand-soft);color:var(--brand);
  font-size:12px;font-weight:700;border:1px solid #c7d2fe;
}
.af-pill button{
  border:none;background:none;cursor:pointer;color:var(--brand);
  font-size:11px;padding:0;line-height:1;display:flex;
}
.af-pill button:hover{color:var(--brand-d)}
#activeFilters{display:none}
#activeFilters.has-filters{display:flex}

/* ════════════════════════════════════════
   RESPONSIVE
════════════════════════════════════════ */
@media(max-width:1200px){
  .stats-row{grid-template-columns:repeat(3,1fr)}
}
@media(max-width:1024px){
  .sidebar-space{display:none}
  .tbl-head,.tbl-row{grid-template-columns:42px 2fr 1fr 1.2fr 100px}
  .tasks-cell{display:none}
}
@media(max-width:768px){
  .main{padding:1.25rem 1rem}
  .page-header{padding:1.5rem}
  .stats-row{grid-template-columns:1fr 1fr}
  .tbl-head{display:none}
  .tbl-row{grid-template-columns:32px 1fr auto;gap:10px}
  .proj-name-cell .proj-avatar{width:32px;height:32px;font-size:13px;border-radius:8px}
}
</style>
</head>
<body>
<div class="layout-wrapper">
  <div class="sidebar-space"><?php include '../includes/admin_sidebar.php'; ?></div>

  <div class="wrapper">
    <?php include '../includes/admin_header.php'; ?>

    <main class="main">
      <div class="container">

        <!-- ══ BANNER ══ -->
        <div class="page-header">
          <div class="header-content">
            <div class="header-top">
              <div class="header-left">
                <div class="header-icon"><i class="fas fa-layer-group"></i></div>
                <div class="header-title">
                  <h1>Projects</h1>
                  <p><?= $totalProjects ?> total · <?= $activeProjects ?> active</p>
                </div>
              </div>
              <a href="add_project.php" class="btn-new">
                <i class="fas fa-plus"></i> New Project
              </a>
            </div>

            <!-- FILTER BAR -->
            <div class="filter-bar">
              <div class="search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="projSearch" class="filter-input" placeholder="Search projects…" oninput="applyFilters()">
              </div>
              <select id="filterStatus" class="filter-select" onchange="applyFilters()">
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="on_hold">On Hold</option>
                <option value="complete">Complete</option>
              </select>
              <select id="filterPlan" class="filter-select" onchange="applyFilters()">
                <option value="all">All Plans</option>
                <?php
                $plans = array_unique(array_column($projects, 'plan_type'));
                foreach($plans as $pl): if(!$pl) continue; ?>
                  <option value="<?= htmlspecialchars($pl) ?>"><?= htmlspecialchars($pl) ?></option>
                <?php endforeach; ?>
              </select>
              <select id="filterProgress" class="filter-select" onchange="applyFilters()">
                <option value="all">Any Progress</option>
                <option value="none">Not Started (0%)</option>
                <option value="low">Low (&lt;30%)</option>
                <option value="mid">Mid (30–70%)</option>
                <option value="high">High (&gt;70%)</option>
                <option value="done">Complete (100%)</option>
              </select>
              <button class="btn-new" style="background:rgba(255,255,255,.12);box-shadow:none;border:1px solid rgba(255,255,255,.15)" onclick="resetFilters()">
                <i class="fas fa-undo"></i> Reset
              </button>
            </div>
          </div>
        </div>

        <!-- ══ STAT CARDS ══ -->
        <div class="stats-row">
          <div class="stat-card" onclick="setStatusFilter('all')" style="cursor:pointer">
            <div class="stat-icon-wrap si-all"><i class="fas fa-layer-group"></i></div>
            <div class="stat-body">
              <div class="stat-num"><?= $totalProjects ?></div>
              <div class="stat-lbl">Total</div>
            </div>
          </div>
          <div class="stat-card" onclick="setStatusFilter('active')" style="cursor:pointer">
            <div class="stat-icon-wrap si-active"><i class="fas fa-bolt"></i></div>
            <div class="stat-body">
              <div class="stat-num"><?= $activeProjects ?></div>
              <div class="stat-lbl">Active</div>
            </div>
          </div>
          <div class="stat-card" onclick="setStatusFilter('on_hold')" style="cursor:pointer">
            <div class="stat-icon-wrap si-hold"><i class="fas fa-pause-circle"></i></div>
            <div class="stat-body">
              <div class="stat-num"><?= $onHoldProjects ?></div>
              <div class="stat-lbl">On Hold</div>
            </div>
          </div>
          <div class="stat-card" onclick="setStatusFilter('complete')" style="cursor:pointer">
            <div class="stat-icon-wrap si-done"><i class="fas fa-check-double"></i></div>
            <div class="stat-body">
              <div class="stat-num"><?= $completeProjects ?></div>
              <div class="stat-lbl">Complete</div>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon-wrap si-tasks"><i class="fas fa-tasks"></i></div>
            <div class="stat-body">
              <div class="stat-num"><?= $totalTasks ?></div>
              <div class="stat-lbl">Total Tasks</div>
            </div>
          </div>
        </div>

        <!-- ══ ACTIVE FILTER PILLS ══ -->
        <div class="active-filters" id="activeFilters">
          <span class="af-label">Filters:</span>
          <div id="pillContainer"></div>
          <button onclick="resetFilters()" style="font-size:12px;font-weight:700;color:var(--muted);background:none;border:none;cursor:pointer;padding:4px 8px;border-radius:6px" onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--muted)'">
            Clear all
          </button>
        </div>

        <!-- ══ TOOLBAR ══ -->
        <div class="toolbar">
          <div class="toolbar-left">
            <!-- Bulk bar -->
            <div class="bulk-bar" id="bulkBar">
              <span class="bulk-count"><span id="selCount">0</span> selected</span>
              <button class="bulk-btn bulk-del" onclick="bulkDelete()">
                <i class="fas fa-trash-alt"></i> Delete
              </button>
              <button class="bulk-btn bulk-cancel" onclick="clearSelection()">
                Cancel
              </button>
            </div>
            <span class="result-label" id="resultLabel"><span id="visCount"><?= $totalProjects ?></span> projects</span>
          </div>
          <div class="view-toggle">
            <button class="vt-btn active" id="vtTable" onclick="setView('table')" title="Table view">
              <i class="fas fa-list"></i>
            </button>
            <button class="vt-btn" id="vtGrid" onclick="setView('grid')" title="Grid view">
              <i class="fas fa-th-large"></i>
            </button>
          </div>
        </div>

        <!-- ══ TABLE VIEW ══ -->
        <div id="tableView">
          <div class="tbl-wrap">
            <div class="tbl-head">
              <div class="cb"><input type="checkbox" id="selAll" onchange="toggleAll()"></div>
              <div>Project</div>
              <div>Status</div>
              <div>Tasks</div>
              <div>Progress</div>
              <div style="text-align:right">Actions</div>
            </div>

            <div id="tableBody">
              <?php if(empty($projects)): ?>
                <div class="empty-state">
                  <div class="empty-icon-wrap"><i class="fas fa-folder-open"></i></div>
                  <h3>No projects yet</h3>
                  <p>Create your first project to get started</p>
                </div>
              <?php endif; ?>

              <?php foreach($projects as $p):
                $prog   = ($p['total_tasks'] > 0) ? round(($p['complete_tasks']/$p['total_tasks'])*100) : 0;
                $status = $p['status'] ?? 'active';
                $letter = strtoupper(substr($p['project_name']??'P',0,1));
                $stLbl  = ucwords(str_replace('_',' ',$status));
                $progCls= $prog >= 70 ? 'high' : '';
              ?>
              <div class="tbl-row"
                   data-status="<?= htmlspecialchars($status) ?>"
                   data-name="<?= htmlspecialchars(strtolower($p['project_name'])) ?>"
                   data-plan="<?= htmlspecialchars(strtolower($p['plan_type']??'')) ?>"
                   data-prog="<?= $prog ?>"
                   data-id="<?= $p['id'] ?>">

                <div class="cb">
                  <input type="checkbox" class="row-cb" value="<?= $p['id'] ?>" onchange="toggleRow(this)">
                </div>

                <div class="proj-name-cell">
                  <div class="proj-avatar"><?= $letter ?></div>
                  <div class="proj-detail">
                    <div class="proj-nm"><?= htmlspecialchars($p['project_name']) ?></div>
                    <div class="proj-sub">
                      <span class="plan-tag"><?= htmlspecialchars($p['plan_type']??'Standard') ?></span>
                      <?php if($p['description']): ?>
                        &nbsp;<?= htmlspecialchars(substr($p['description'],0,55)) ?><?= strlen($p['description'])>55?'…':'' ?>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>

                <div><span class="st-badge <?= $status ?>"><?= $stLbl ?></span></div>

                <div class="tasks-cell">
                  <div class="tasks-num"><?= $p['total_tasks'] ?></div>
                  <div class="tasks-sub"><?= $p['complete_tasks'] ?> done</div>
                </div>

                <div class="prog-cell">
                  <div class="prog-bar <?= $progCls ?>">
                    <div class="prog-fill" style="width:<?= $prog ?>%"></div>
                  </div>
                  <span class="prog-text"><?= $prog ?>%</span>
                </div>

                <div class="act-cell">
                  <a href="view_project.php?id=<?= $p['id'] ?>" class="act-btn" title="View project">
                    <i class="fas fa-eye"></i>
                  </a>
                  <a href="edit_project.php?id=<?= $p['id'] ?>" class="act-btn" title="Edit project">
                    <i class="fas fa-pen"></i>
                  </a>
                  <div class="more-wrap">
                    <button class="act-btn" onclick="toggleMenu(<?= $p['id'] ?>,event)" title="More">
                      <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <div class="more-drop" id="menu-<?= $p['id'] ?>">
                      <a href="view_project.php?id=<?= $p['id'] ?>" class="drop-item">
                        <i class="fas fa-eye"></i> View Board
                      </a>
                      <a href="edit_project.php?id=<?= $p['id'] ?>" class="drop-item">
                        <i class="fas fa-pen"></i> Edit
                      </a>
                      <div class="drop-divider"></div>
                      <a href="?delete_id=<?= $p['id'] ?>" class="drop-item danger"
                         onclick="return confirm('Delete this project and all its tasks?')">
                        <i class="fas fa-trash-alt"></i> Delete
                      </a>
                    </div>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- ══ GRID VIEW ══ -->
        <div id="gridView">
          <div class="cards-grid" id="cardsGrid">
            <?php foreach($projects as $p):
              $prog   = ($p['total_tasks'] > 0) ? round(($p['complete_tasks']/$p['total_tasks'])*100) : 0;
              $status = $p['status'] ?? 'active';
              $letter = strtoupper(substr($p['project_name']??'P',0,1));
              $stLbl  = ucwords(str_replace('_',' ',$status));
              $progCls= $prog >= 70 ? 'high' : '';
            ?>
            <div class="proj-card"
                 data-status="<?= htmlspecialchars($status) ?>"
                 data-name="<?= htmlspecialchars(strtolower($p['project_name'])) ?>"
                 data-plan="<?= htmlspecialchars(strtolower($p['plan_type']??'')) ?>"
                 data-prog="<?= $prog ?>">

              <div class="card-top">
                <div class="card-avatar"><?= $letter ?></div>
                <div class="card-name-wrap">
                  <div class="card-name"><?= htmlspecialchars($p['project_name']) ?></div>
                  <div class="card-plan"><?= htmlspecialchars($p['plan_type']??'Standard') ?></div>
                </div>
                <div class="more-wrap">
                  <button class="card-menu-btn" onclick="toggleMenu('c<?= $p['id'] ?>',event)">
                    <i class="fas fa-ellipsis-h"></i>
                  </button>
                  <div class="more-drop" id="menu-c<?= $p['id'] ?>">
                    <a href="view_project.php?id=<?= $p['id'] ?>" class="drop-item">
                      <i class="fas fa-eye"></i> View Board
                    </a>
                    <a href="edit_project.php?id=<?= $p['id'] ?>" class="drop-item">
                      <i class="fas fa-pen"></i> Edit
                    </a>
                    <div class="drop-divider"></div>
                    <a href="?delete_id=<?= $p['id'] ?>" class="drop-item danger"
                       onclick="return confirm('Delete this project?')">
                      <i class="fas fa-trash-alt"></i> Delete
                    </a>
                  </div>
                </div>
              </div>

              <?php if($p['description']): ?>
              <div class="card-desc"><?= htmlspecialchars($p['description']) ?></div>
              <?php endif; ?>

              <div class="card-prog-row">
                <div class="card-prog-top">
                  <span class="card-prog-lbl">Progress</span>
                  <span class="card-prog-pct"><?= $prog ?>%</span>
                </div>
                <div class="card-prog-bar <?= $progCls ?>">
                  <div class="card-prog-fill" style="width:<?= $prog ?>%"></div>
                </div>
              </div>

              <div class="card-footer">
                <div class="card-stats">
                  <span class="card-stat"><i class="fas fa-tasks"></i><?= $p['total_tasks'] ?> tasks</span>
                  <span class="card-stat"><i class="fas fa-check"></i><?= $p['complete_tasks'] ?> done</span>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                  <span class="st-badge <?= $status ?>"><?= $stLbl ?></span>
                  <a href="view_project.php?id=<?= $p['id'] ?>" class="act-btn" title="View">
                    <i class="fas fa-arrow-right"></i>
                  </a>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div><!-- /container -->
    </main>
  </div>
</div>

<script>
/* ═══════════════════════════════════
   STATE
═══════════════════════════════════ */
let selected   = new Set();
let activeView = 'table';

/* ═══════════════════════════════════
   VIEW TOGGLE
═══════════════════════════════════ */
function setView(v){
  activeView = v;
  document.getElementById('tableView').style.display = v==='table' ? 'block' : 'none';
  document.getElementById('gridView').style.display  = v==='grid'  ? 'block' : 'none';
  document.getElementById('vtTable').classList.toggle('active', v==='table');
  document.getElementById('vtGrid').classList.toggle('active',  v==='grid');
}

/* ═══════════════════════════════════
   FILTERS
═══════════════════════════════════ */
function applyFilters(){
  const search   = document.getElementById('projSearch').value.toLowerCase().trim();
  const status   = document.getElementById('filterStatus').value;
  const plan     = document.getElementById('filterPlan').value.toLowerCase();
  const progress = document.getElementById('filterProgress').value;

  let vis = 0;

  // TABLE rows
  document.querySelectorAll('.tbl-row').forEach(row => {
    const ok = matchRow(row, search, status, plan, progress);
    row.classList.toggle('hidden', !ok);
    if(ok) vis++;
  });

  // GRID cards
  document.querySelectorAll('.proj-card').forEach(card => {
    const ok = matchRow(card, search, status, plan, progress);
    card.classList.toggle('hidden', !ok);
  });

  document.getElementById('visCount').textContent = vis;
  updatePills(search, status, plan, progress);
}

function matchRow(el, search, status, plan, progress){
  const name = el.dataset.name || '';
  const st   = el.dataset.status || '';
  const pl   = (el.dataset.plan || '').toLowerCase();
  const prog = parseInt(el.dataset.prog || '0');

  if(search && !name.includes(search)) return false;
  if(status !== 'all' && st !== status) return false;
  if(plan   !== 'all' && pl !== plan)   return false;

  if(progress === 'none' && prog !== 0)   return false;
  if(progress === 'low'  && prog >= 30)   return false;
  if(progress === 'mid'  && (prog < 30 || prog > 70)) return false;
  if(progress === 'high' && (prog <= 70 || prog >= 100)) return false;
  if(progress === 'done' && prog !== 100) return false;

  return true;
}

function resetFilters(){
  document.getElementById('projSearch').value   = '';
  document.getElementById('filterStatus').value = 'all';
  document.getElementById('filterPlan').value   = 'all';
  document.getElementById('filterProgress').value = 'all';
  applyFilters();
}

function setStatusFilter(v){
  document.getElementById('filterStatus').value = v;
  applyFilters();
}

/* Active filter pills */
function updatePills(search, status, plan, progress){
  const container = document.getElementById('activeFilters');
  const pills     = document.getElementById('pillContainer');
  const active    = [];

  if(search)           active.push({lbl:'Search: '+search,     clear:()=>{document.getElementById('projSearch').value='';applyFilters()}});
  if(status!=='all')   active.push({lbl:'Status: '+status.replace('_',' '), clear:()=>{document.getElementById('filterStatus').value='all';applyFilters()}});
  if(plan!=='all')     active.push({lbl:'Plan: '+plan,          clear:()=>{document.getElementById('filterPlan').value='all';applyFilters()}});
  if(progress!=='all') active.push({lbl:'Progress: '+progress,  clear:()=>{document.getElementById('filterProgress').value='all';applyFilters()}});

  if(active.length){
    container.classList.add('has-filters');
    pills.innerHTML = active.map((a,i)=>`
      <span class="af-pill">
        ${a.lbl}
        <button onclick="clearPill(${i})" title="Remove filter">✕</button>
      </span>`).join('');
    window._pills = active;
  } else {
    container.classList.remove('has-filters');
    pills.innerHTML = '';
  }
}
window._pills = [];
function clearPill(i){ window._pills[i].clear(); }

/* ═══════════════════════════════════
   BULK SELECT
═══════════════════════════════════ */
function toggleAll(){
  const checked = document.getElementById('selAll').checked;
  document.querySelectorAll('.row-cb').forEach(cb=>{
    const row = cb.closest('.tbl-row');
    if(row && !row.classList.contains('hidden')){
      cb.checked = checked;
      if(checked){ selected.add(cb.value); row.classList.add('selected'); }
      else        { selected.delete(cb.value); row.classList.remove('selected'); }
    }
  });
  updateBulk();
}

function toggleRow(cb){
  const row = cb.closest('.tbl-row');
  if(cb.checked){ selected.add(cb.value); row.classList.add('selected'); }
  else           { selected.delete(cb.value); row.classList.remove('selected'); document.getElementById('selAll').checked = false; }
  updateBulk();
}

function clearSelection(){
  selected.clear();
  document.querySelectorAll('.row-cb').forEach(cb=>{
    cb.checked = false;
    cb.closest('.tbl-row')?.classList.remove('selected');
  });
  document.getElementById('selAll').checked = false;
  updateBulk();
}

function updateBulk(){
  document.getElementById('selCount').textContent = selected.size;
  document.getElementById('bulkBar').classList.toggle('on', selected.size > 0);
}

function bulkDelete(){
  if(!selected.size) return;
  if(!confirm(`Delete ${selected.size} project(s)? This cannot be undone.`)) return;
  const form = document.createElement('form');
  form.method = 'POST'; form.action = 'bulk_delete_projects.php';
  const inp = document.createElement('input');
  inp.type='hidden'; inp.name='project_ids'; inp.value=[...selected].join(',');
  form.appendChild(inp); document.body.appendChild(form); form.submit();
}

/* ═══════════════════════════════════
   DROPDOWN MENUS
═══════════════════════════════════ */
function toggleMenu(id, e){
  e.stopPropagation();
  const menu = document.getElementById('menu-'+id);
  const was  = menu.classList.contains('open');
  document.querySelectorAll('.more-drop').forEach(m=>m.classList.remove('open'));
  if(!was) menu.classList.add('open');
}

document.addEventListener('click', ()=>{
  document.querySelectorAll('.more-drop').forEach(m=>m.classList.remove('open'));
});
</script>
</body>
</html>