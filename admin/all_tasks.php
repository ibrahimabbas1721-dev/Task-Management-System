<?php
require '../config/db.php';
requireLogin();
requireRole('admin');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$adminId = $_SESSION['user_id'];
$messageHtml = '';

if (!empty($_SESSION['message'])) {
    $msgType = $_SESSION['message']['type'];
    $msgText = $_SESSION['message']['text'];
    $iconName = $msgType === 'success' ? 'check_circle' : 'error';
    $messageHtml = "<div class='notice-box {$msgType}'>
                        <span class='material-symbols-outlined'>{$iconName}</span>
                        {$msgText}
                     </div>";
    unset($_SESSION['message']);
}

// Handle Bulk Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $taskIds = $_POST['task_ids'] ?? [];

    if (is_array($taskIds) && !empty($taskIds)) {
        try {
            $placeholders = rtrim(str_repeat('?,', count($taskIds)), ',');

            if ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM tasks WHERE id IN ({$placeholders}) AND created_by_admin = ?");
                $stmt->execute([...$taskIds, $adminId]);
                $_SESSION['message'] = ['type' => 'success', 'text' => count($taskIds) . ' task(s) deleted successfully'];
            } elseif ($action === 'complete') {
                $stmt = $pdo->prepare("UPDATE tasks SET status = 'complete' WHERE id IN ({$placeholders}) AND created_by_admin = ?");
                $stmt->execute([...$taskIds, $adminId]);
                $_SESSION['message'] = ['type' => 'success', 'text' => count($taskIds) . ' task(s) marked as complete'];
            } elseif ($action === 'progress') {
                $stmt = $pdo->prepare("UPDATE tasks SET status = 'in_progress' WHERE id IN ({$placeholders}) AND created_by_admin = ?");
                $stmt->execute([...$taskIds, $adminId]);
                $_SESSION['message'] = ['type' => 'success', 'text' => count($taskIds) . ' task(s) set to in progress'];
            }

            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $ex) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Bulk action failed: ' . $ex->getMessage()];
        }
    }
}

// Fetch tasks and prepare filters and counts
try {
    $sql = "
        SELECT t.*, u.username AS assigned_username,
               p.project_name AS project_display_name, p.id AS project_id
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE t.created_by_admin = ?
        ORDER BY t.updated_at DESC, t.due_date ASC, t.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$adminId]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filterProjects = array_unique(array_filter(array_column($tasks, 'project_display_name')));
    $filterAssignees = array_unique(array_filter(array_column($tasks, 'assigned_username')));
    $filterGroups = array_unique(array_filter(array_column($tasks, 'group_name')));

    $statusCounts = ['pending' => 0, 'in_progress' => 0, 'complete' => 0];
    foreach ($tasks as $task) {
        $key = str_replace([' ', '-'], '_', strtolower($task['status']));
        if (isset($statusCounts[$key])) {
            $statusCounts[$key]++;
        }
    }
} catch (PDOException $ex) {
    $messageHtml = "<div class='notice-box error'>Database Error: " . $ex->getMessage() . "</div>";
    $tasks = [];
    $filterProjects = [];
    $filterAssignees = [];
    $filterGroups = [];
    $statusCounts = ['pending' => 0, 'in_progress' => 0, 'complete' => 0];
}

// Generate JSON array for JavaScript calendar
$tasksJson = [];
foreach ($tasks as $task) {
    $statusSlug = str_replace(['_', ' '], '-', strtolower(trim($task['status'] ?? 'in_progress')));

    $dueDate = '';
    if (!empty($task['due_date'])) {
        try {
            $dueDate = (new DateTime($task['due_date']))->format('Y-m-d');
        } catch (Exception $e) {
            $dueDate = substr($task['due_date'], 0, 10);
        }
    }

    if ($dueDate === '') {
        continue;
    }

    $tasksJson[] = [
        'id'       => (int)$task['id'],
        'title'    => $task['title'],
        'status'   => $statusSlug,
        'due_date' => $dueDate,
        'project'  => $task['project_display_name'] ?? 'General',
        'assignee' => $task['assigned_username'] ?? 'Unassigned',
        'group'    => $task['group_name'] ?? 'General Tasks',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Master Tasks | TMS Pro</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
<link rel="stylesheet" href="../fontawesome/css/all.min.css" />

<style>
/* ══════════════════════════════════════
   BASE RESET & VARIABLES
══════════════════════════════════════ */
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --brand:#6366f1;--brand-soft:#eef2ff;--brand-dark:#4f46e5;
  --dark:#0f172a;--deep-dark:#161e2d;
  --bg:#f8fafc;--surface:#fff;
  --text-main:#1e293b;--text-muted:#64748b;--border:#e2e8f0;
  --danger:#ef4444;--danger-bg:#fef2f2;--danger-border:#fecaca;
  --success:#10b981;--success-bg:#ecfdf5;--success-border:#a7f3d0;
  --warning:#f59e0b;--warning-bg:#fef3c7;--warning-border:#fcd34d;
  --radius:24px;--tr:0.2s;--tr-lg:0.3s;
}
html,body{height:100%;overflow-x:hidden}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text-main);display:flex;flex-direction:column}

/* ── LAYOUT ── */
.layout-wrapper{display:flex;height:100vh;width:100%;overflow-x:hidden}
.sidebar-spacer{width:260px;flex-shrink:0;overflow-y:auto;overflow-x:hidden}
.wrapper{flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0}
.main-content{flex:1;padding:2.5rem;overflow-y:auto;overflow-x:hidden}
.content-limit{max-width:1600px;margin:0 auto;width:100%}

/* ── BANNER ── */
.banner{
  background:var(--deep-dark);border-radius:var(--radius);
  padding:1.5rem 2.5rem;display:flex;align-items:center;
  justify-content:space-between;margin-bottom:1.75rem;
  color:white;flex-wrap:wrap;gap:1.5rem;
}
.banner-info{display:flex;align-items:center;gap:1.5rem;flex:1;min-width:0}
.banner-icon{
  width:56px;height:56px;background:rgba(255,255,255,.05);
  border:1px solid rgba(255,255,255,.1);border-radius:16px;
  display:flex;align-items:center;justify-content:center;
  color:var(--brand);flex-shrink:0;
}
.banner-icon .fas{font-size:22px}
.banner-text h1{font-size:1.5rem;font-weight:800;letter-spacing:-.5px;line-height:1.3}
.banner-text p{font-size:12px;color:var(--text-muted);margin-top:2px}
.banner-actions{display:flex;align-items:center;gap:10px;flex-shrink:0}

/* ── VIEW SWITCHER BUTTON ── */
.view-switcher{position:relative;flex-shrink:0}
.view-switcher-btn{
  background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);
  color:white;padding:10px 16px;border-radius:12px;
  font-size:13px;font-weight:700;font-family:inherit;
  cursor:pointer;display:flex;align-items:center;gap:8px;
  transition:var(--tr);min-height:40px;white-space:nowrap;
}
.view-switcher-btn:hover{background:rgba(255,255,255,.14)}
.view-switcher-btn .vs-chevron{font-size:13px;transition:transform .2s}
.view-switcher-btn.open .vs-chevron{transform:rotate(180deg)}

.view-dropdown{
  position:absolute;top:calc(100% + 8px);right:0;
  background:var(--surface);border:1px solid var(--border);
  border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.12);
  min-width:185px;z-index:200;overflow:hidden;
  opacity:0;transform:translateY(-8px);pointer-events:none;
  transition:opacity .18s,transform .18s;
}
.view-dropdown.open{opacity:1;transform:translateY(0);pointer-events:all}
.view-dropdown-item{
  display:flex;align-items:center;gap:10px;
  padding:11px 16px;font-size:13px;font-weight:600;
  color:var(--text-main);cursor:pointer;transition:background .15s;
  border:none;background:none;width:100%;text-align:left;font-family:inherit;
}
.view-dropdown-item:hover{background:var(--bg)}
.view-dropdown-item.active{color:var(--brand);background:var(--brand-soft)}
.view-dropdown-item .fas{font-size:14px;color:var(--text-muted);width:16px}
.view-dropdown-item.active .fas{color:var(--brand)}

/* ── DEPLOY BUTTON ── */
.btn-create{
  background:var(--brand);color:white;padding:12px 20px;
  border-radius:14px;text-decoration:none;font-size:13px;font-weight:700;
  display:flex;align-items:center;gap:8px;transition:var(--tr-lg);
  min-height:40px;line-height:1.5;flex-shrink:0;
}
.btn-create:hover{background:var(--brand-dark);transform:translateY(-1px)}
.btn-create .fas{font-size:14px}

/* ══════════════════════════════════════
   FILTER ROW — all filters in one line
══════════════════════════════════════ */
.filter-row{
  display:flex;
  flex-wrap:wrap;
  gap:10px;
  align-items:flex-end;
  margin-bottom:1.25rem;
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:16px;
  padding:14px 16px;
}

.filter-group{display:flex;flex-direction:column;gap:5px;flex-shrink:0}
.filter-group.grow{flex:1;min-width:140px}

.filter-label{
  font-size:10px;font-weight:800;color:var(--text-muted);
  text-transform:uppercase;letter-spacing:.06em;padding-left:2px;
}

.filter-input{
  background:white;border:1px solid var(--border);padding:0 12px;
  border-radius:10px;font-size:13px;font-family:inherit;font-weight:600;
  color:var(--dark);outline:none;transition:var(--tr);
  height:36px;line-height:36px;cursor:pointer;
}
.filter-input:focus{border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-soft)}

/* Date inputs */
.filter-input[type="date"]{
  width:138px;
  color-scheme:light;
  padding:0 10px;
}
.filter-input[type="date"]::-webkit-calendar-picker-indicator{
  opacity:.5;cursor:pointer;
}
.filter-input[type="date"]:focus::-webkit-calendar-picker-indicator{opacity:.9}

/* Date range wrapper */
.date-range-wrap{display:flex;align-items:center;gap:6px}
.date-sep{font-size:13px;font-weight:700;color:var(--text-muted);line-height:36px}

/* Error hint */
.date-error{
  font-size:11px;color:var(--danger);margin-top:3px;
  padding-left:2px;display:none;font-weight:600;
}

/* Active date filter indicator */
.filter-input[type="date"].has-value{
  border-color:var(--brand);
  background:var(--brand-soft);
  color:var(--brand-dark);
}

/* Reset button */
.btn-reset{
  background:#f1f5f9;border:1px solid #cbd5e1;
  color:var(--text-main);cursor:pointer;
  display:flex;align-items:center;gap:6px;
  padding:0 14px;height:36px;border-radius:10px;
  font-size:12px;font-weight:700;font-family:inherit;
  transition:var(--tr);white-space:nowrap;
}
.btn-reset:hover{background:#e2e8f0;border-color:#94a3b8}
.btn-reset .fas{font-size:12px}

/* Active date range badge shown on filter row */
.date-active-badge{
  display:none;align-items:center;gap:6px;
  background:#eef2ff;border:1px solid #c7d2fe;
  color:#4338ca;border-radius:8px;padding:0 10px;
  height:24px;font-size:11px;font-weight:700;
  align-self:flex-end;margin-bottom:6px;
}
.date-active-badge.on{display:flex}
.date-active-badge .x{cursor:pointer;font-size:13px;line-height:1;margin-left:2px;opacity:.6}
.date-active-badge .x:hover{opacity:1}

/* ── STATS STRIP ── */
.stats-strip{display:flex;gap:10px;margin-bottom:1.5rem;flex-wrap:wrap}
.stat-pill{
  background:white;padding:9px 16px;border-radius:12px;border:1px solid var(--border);
  display:flex;align-items:center;gap:9px;font-size:12px;font-weight:700;
  cursor:pointer;transition:var(--tr);line-height:1.5;
}
.stat-pill:hover{border-color:var(--brand)}
.stat-pill.active{background:var(--brand);color:white;border-color:var(--brand)}
.dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.dot-progress{background:var(--brand)}
.stat-pill.active .dot-progress{background:white}
.dot-success{background:var(--success)}
.stat-pill.active .dot-success{background:white}

/* ── NOTICE BOX ── */
.notice-box{
  padding:14px 20px;border-radius:16px;margin-bottom:1.5rem;
  font-size:14px;font-weight:700;display:flex;align-items:center;
  gap:10px;border:1px solid transparent;line-height:1.5;
}
.notice-box.success{background:var(--success-bg);color:#065f46;border-color:var(--success-border)}
.notice-box.error{background:var(--danger-bg);color:#991b1b;border-color:var(--danger-border)}

/* ══════════════════════════════════════
   LIST VIEW – TABLE
══════════════════════════════════════ */
.bulk-actions-bar{
  background:white;border:1px solid var(--border);border-radius:16px;
  padding:1rem 1.5rem;margin-bottom:1.25rem;
  display:none;align-items:center;gap:1rem;flex-wrap:wrap;
}
.bulk-actions-bar.active{display:flex}
.bulk-info{font-size:13px;font-weight:700;color:var(--dark)}
.bulk-btn{
  padding:8px 16px;border-radius:10px;font-size:12px;font-weight:700;
  border:1px solid var(--border);background:white;cursor:pointer;
  transition:var(--tr);display:flex;align-items:center;gap:6px;
  min-height:36px;font-family:inherit;
}
.bulk-btn .fas{font-size:13px}
.bulk-btn:hover{background:#f8fafc}
.bulk-btn.btn-complete:hover{background:var(--success-bg);border-color:var(--success);color:var(--success)}
.bulk-btn.btn-progress:hover{background:var(--brand-soft);border-color:var(--brand);color:var(--brand)}
.bulk-btn.btn-delete:hover{background:var(--danger-bg);border-color:var(--danger);color:var(--danger)}

.table-container{
  background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);
  box-shadow:0 4px 6px -1px rgba(0,0,0,.02);display:flex;flex-direction:column;
  height:calc(100vh - 530px);min-height:380px;
}
.table-wrapper{flex:1;overflow-y:auto;overflow-x:auto}
.table-wrapper::-webkit-scrollbar{width:7px}
.table-wrapper::-webkit-scrollbar-track{background:#f9fafb}
.table-wrapper::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:8px}
.table-wrapper::-webkit-scrollbar-thumb:hover{background:#94a3b8}

table{width:100%;border-collapse:collapse;min-width:1100px}
th{
  background:#f8fafc;padding:13px 16px;text-align:left;
  font-size:11px;text-transform:uppercase;color:var(--text-muted);
  font-weight:800;border-bottom:1px solid var(--border);
  white-space:nowrap;position:sticky;top:0;z-index:10;
}
th.text-right{text-align:right}
th.cb-col{width:48px;text-align:center;padding:13px 12px}
td{
  padding:13px 16px;border-bottom:1px solid #f1f5f9;
  font-size:13px;vertical-align:middle;white-space:nowrap;
  overflow:hidden;text-overflow:ellipsis;
}
td.text-right{text-align:right}
td.cb-col{text-align:center;padding:13px 12px}

.task-row.hidden{display:none}
.task-row.overdue{background:var(--danger-bg)}
.task-row.overdue td{border-bottom-color:var(--danger-border)}

.group-label{font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase}
.task-name-link{text-decoration:none}
.task-name-link:hover .task-name{color:var(--brand)}
.task-name{font-weight:700;color:var(--dark);max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;transition:color .15s}

.project-link{text-decoration:none}
.project-link:hover .project-label{color:#4338ca;text-decoration:underline}
.project-label{font-size:11px;font-weight:700;color:var(--brand);text-transform:uppercase}
.task-row.overdue .project-label{color:var(--danger)}

.assignee-name{font-weight:600;color:var(--text-main)}
.activity-time{font-size:12px;color:var(--text-muted)}

.status-badge{
  padding:5px 10px;border-radius:6px;font-size:10px;font-weight:800;
  text-transform:uppercase;border:1px solid transparent;
  display:inline-block;line-height:1.2;white-space:nowrap;
}
.status-badge.complete{background:#dcfce7;color:#15803d;border-color:#86efac}
.status-badge.in-progress{background:#e0e7ff;color:#4338ca;border-color:#c7d2fe}
.status-badge.pending{background:#f1f5f9;color:#475569;border-color:#cbd5e1}
.status-badge.overdue{background:var(--danger-bg);color:var(--danger);border-color:var(--danger-border)}

.tag-badge{font-size:9px;font-weight:700;padding:3px 7px;border-radius:4px;background:#f3f4f6;color:#6b7280;margin-right:3px;display:inline-block}
.deadline-text{font-weight:600;color:var(--text-muted)}
.task-row.overdue .deadline-text{color:var(--danger);font-weight:800}
.overdue-label{
  display:inline-flex;align-items:center;gap:3px;font-size:9px;font-weight:800;
  color:var(--danger);background:white;padding:3px 6px;border-radius:4px;margin-left:5px;
}
.overdue-label .fas{font-size:10px}

.action-btns{display:flex;gap:6px;justify-content:flex-end}
.icon-btn{
  width:32px;height:32px;border-radius:8px;display:flex;align-items:center;
  justify-content:center;border:1px solid var(--border);color:var(--text-muted);
  text-decoration:none;transition:var(--tr);background:white;
}
.icon-btn .fas{font-size:13px}
.icon-btn:hover{border-color:var(--dark);color:var(--dark);background:#f8fafc}
.icon-btn.delete:hover{border-color:var(--danger);color:var(--danger);background:var(--danger-bg)}

.checkbox-input{width:18px;height:18px;cursor:pointer;accent-color:var(--brand)}

/* ══════════════════════════════════════
   CALENDAR VIEW
══════════════════════════════════════ */
#calendarView{display:none}

/* Toolbar */
.cal-toolbar{
  background:var(--surface);border:1px solid var(--border);border-radius:18px;
  padding:13px 18px;display:flex;align-items:center;
  justify-content:space-between;margin-bottom:14px;gap:12px;flex-wrap:wrap;
}
.cal-nav{display:flex;align-items:center;gap:7px}
.cal-nav-btn{
  width:36px;height:36px;border-radius:10px;border:1px solid var(--border);
  background:white;display:flex;align-items:center;justify-content:center;
  cursor:pointer;color:var(--text-muted);font-family:inherit;transition:var(--tr);
}
.cal-nav-btn:hover{border-color:var(--brand);color:var(--brand);background:var(--brand-soft)}
.cal-nav-btn .fas{font-size:14px}
.cal-today-btn{
  padding:8px 15px;border-radius:10px;border:1px solid var(--border);background:white;
  font-family:inherit;font-size:12px;font-weight:700;color:var(--text-main);
  cursor:pointer;transition:var(--tr);height:36px;
}
.cal-today-btn:hover{border-color:var(--brand);color:var(--brand);background:var(--brand-soft)}
.cal-title{font-size:16px;font-weight:800;color:var(--dark);min-width:210px;text-align:center}
.cal-sub-views{display:flex;gap:3px;background:#f1f5f9;padding:4px;border-radius:12px}
.cal-sub-btn{
  padding:7px 16px;border-radius:9px;border:none;background:transparent;
  font-family:inherit;font-size:12px;font-weight:700;color:var(--text-muted);cursor:pointer;transition:var(--tr);
}
.cal-sub-btn.active{background:white;color:var(--brand);box-shadow:0 1px 4px rgba(0,0,0,.08)}
.cal-sub-btn:hover:not(.active){color:var(--text-main)}

/* Month Grid */
.cal-month{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.cal-month-header{display:grid;grid-template-columns:repeat(7,1fr);border-bottom:1px solid var(--border)}
.cal-month-dow{
  padding:12px 8px;text-align:center;font-size:11px;font-weight:800;
  color:var(--text-muted);text-transform:uppercase;background:#f8fafc;
}
.cal-month-dow:first-child,.cal-month-dow:last-child{color:#e11d48}

.cal-month-body{display:grid;grid-template-columns:repeat(7,1fr)}
.cal-day{
  border-right:1px solid var(--border);border-bottom:1px solid var(--border);
  min-height:115px;padding:8px;cursor:pointer;transition:background .13s;
}
.cal-day:hover{background:#fafbff}
.cal-day:nth-child(7n){border-right:none}
.cal-day.other-month{background:#fafafa}
.cal-day.other-month .cal-dn{color:#cbd5e1}
.cal-day.today{background:var(--brand-soft)}
.cal-day.today .cal-dn-inner{
  background:var(--brand);color:white;border-radius:50%;
  width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:12px;
}
/* Highlight days within selected date range */
.cal-day.in-range{background:#f0f4ff}
.cal-day.range-start,.cal-day.range-end{background:#e0e7ff}

.cal-day-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:5px}
.cal-dn{font-size:12px;font-weight:800;color:var(--dark);width:26px;height:26px;display:flex;align-items:center;justify-content:center}
.cal-cnt{
  font-size:10px;font-weight:800;padding:2px 7px;border-radius:5px;
  background:var(--brand);color:white;line-height:1.4;display:none;
}
.cal-cnt.on{display:inline-block}

.cal-chip{
  font-size:10px;font-weight:700;padding:3px 7px;border-radius:5px;margin-bottom:3px;
  display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  text-decoration:none;transition:opacity .15s;
}
.cal-chip:hover{opacity:.75}
.cal-chip.complete{background:#dcfce7;color:#15803d}
.cal-chip.in-progress{background:#e0e7ff;color:#4338ca}
.cal-chip.pending{background:#f1f5f9;color:#475569}
.cal-chip.overdue{background:var(--danger-bg);color:var(--danger)}

.cal-more{font-size:10px;font-weight:800;color:var(--brand);padding:2px 0;cursor:pointer;display:block}
.cal-more:hover{text-decoration:underline}

/* Week View */
.cal-week{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.cal-wk-grid{display:grid;grid-template-columns:68px repeat(7,1fr)}
.cal-wk-head{background:#f8fafc;border-bottom:1px solid var(--border)}
.cal-wk-gutter-h{padding:12px 6px;border-right:1px solid var(--border)}
.cal-wk-day-h{padding:12px 8px;text-align:center;border-left:1px solid var(--border)}
.cal-wk-day-h .wdow{font-size:10px;font-weight:800;text-transform:uppercase;color:var(--text-muted)}
.cal-wk-day-h .wnum{font-size:20px;font-weight:900;color:var(--dark);margin-top:1px;letter-spacing:-1px}
.cal-wk-day-h.today .wnum{color:var(--brand)}
.cal-wk-day-h .wcnt{font-size:10px;font-weight:600;color:var(--text-muted);margin-top:2px}
.cal-wk-day-h.busy .wcnt{color:var(--brand);font-weight:800}
.cal-wk-gutter-b{border-right:1px solid var(--border);padding:8px 6px;min-height:120px}
.cal-wk-cell{border-left:1px solid var(--border);border-bottom:1px solid var(--border);padding:8px;min-height:120px}
.cal-wk-cell.today-col{background:#fafbff}
.cal-wk-cell.in-range-col{background:#f0f4ff}
.cal-wk-cell .cal-chip{margin-bottom:3px}

/* Day View */
.cal-day-view{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.cal-dv-header{
  padding:20px 24px;border-bottom:1px solid var(--border);background:#f8fafc;
  display:flex;align-items:center;gap:16px;flex-wrap:wrap;
}
.dv-date-block .dv-big{font-size:3rem;font-weight:900;color:var(--dark);letter-spacing:-2px;line-height:1}
.dv-date-block .dv-label{font-size:12px;color:var(--text-muted);font-weight:600;margin-top:4px}
.dv-badge{padding:8px 18px;border-radius:12px;background:var(--brand);color:white;font-size:13px;font-weight:800}
.dv-badge.empty{background:#f1f5f9;color:var(--text-muted)}

.dv-list{padding:16px 20px;display:flex;flex-direction:column;gap:8px}
.dv-item{
  display:flex;align-items:center;gap:12px;padding:12px 16px;
  border:1px solid var(--border);border-radius:12px;
  text-decoration:none;transition:var(--tr);background:white;
}
.dv-item:hover{border-color:var(--brand);background:var(--brand-soft)}
.dv-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.dv-dot.complete{background:var(--success)}
.dv-dot.in-progress{background:var(--brand)}
.dv-dot.pending{background:#94a3b8}
.dv-dot.overdue{background:var(--danger)}
.dv-info{flex:1;min-width:0}
.dv-title{font-size:13px;font-weight:700;color:var(--dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dv-meta{font-size:11px;color:var(--text-muted);margin-top:2px}

.cal-empty{padding:52px 24px;text-align:center;color:var(--text-muted);font-size:13px;font-weight:600}
.cal-empty .fas{font-size:40px;color:#e2e8f0;display:block;margin-bottom:10px}

/* Day hover popup */
.day-popup{
  position:fixed;background:var(--dark);color:white;border-radius:12px;
  padding:12px 16px;font-size:11px;z-index:9999;pointer-events:none;
  opacity:0;transition:opacity .15s;min-width:165px;
  box-shadow:0 12px 32px rgba(0,0,0,.22);
}
.day-popup.on{opacity:1}
.dp-date{font-weight:800;margin-bottom:7px;font-size:10px;color:#94a3b8;text-transform:uppercase}
.dp-row{display:flex;justify-content:space-between;gap:14px;margin-bottom:4px}
.dp-lbl{color:#94a3b8;font-weight:600}
.dp-val{font-weight:800}
.dp-total{border-top:1px solid rgba(255,255,255,.1);padding-top:5px;margin-top:3px}

/* ── RESPONSIVE ── */
@media(max-width:1200px){.filter-row{gap:8px}}
@media(max-width:1024px){.sidebar-spacer{display:none}table{min-width:960px}}
@media(max-width:768px){
  .main-content{padding:1.5rem 1rem}
  .banner{padding:1.5rem;flex-direction:column;align-items:flex-start}
  .btn-create{width:100%}
  .table-container{height:auto;min-height:300px}
  .filter-row{flex-direction:column}
  .filter-input[type="date"]{width:100%}
}
</style>
</head>
<body>
<div class="layout-wrapper">

  <!-- SIDEBAR -->
  <div class="sidebar-spacer">
    <?php include '../includes/admin_sidebar.php'; ?>
  </div>

  <div class="wrapper">
    <!-- HEADER -->
    <?php include '../includes/admin_header.php'; ?>

    <main class="main-content">
      <div class="content-limit">

        <!-- ══ BANNER ══ -->
        <header class="banner">
          <div class="banner-info">
            <div class="banner-icon">
              <i class="fas fa-tasks"></i>
            </div>
            <div class="banner-text">
              <h1>Master Tasks</h1>
              <p>Real-time oversight of all team deliverables</p>
            </div>
          </div>
          <div class="banner-actions">

            <!-- View switcher -->
            <div class="view-switcher" id="viewSwitcher">
              <button class="view-switcher-btn" id="vsBtn" onclick="toggleVD()">
                <i class="fas fa-th-list" id="vsIcon"></i>
                <span id="vsLabel">List View</span>
                <i class="fas fa-chevron-down vs-chevron"></i>
              </button>
              <div class="view-dropdown" id="viewDropdown">
                <button class="view-dropdown-item active" id="vdList" onclick="switchView('list')">
                  <i class="fas fa-th-list"></i> List View
                </button>
                <button class="view-dropdown-item" id="vdCalendar" onclick="switchView('calendar')">
                  <i class="fas fa-calendar-alt"></i> Calendar View
                </button>
              </div>
            </div>

            <a href="add_task.php" class="btn-create">
              <i class="fas fa-plus-circle"></i> Deploy Task
            </a>
          </div>
        </header>

        <!-- ══ FILTER ROW (single line, all filters) ══ -->
        <div class="filter-row" id="filterRow">

          <!-- Search -->
          <div class="filter-group grow">
            <label class="filter-label">Search Deliverable</label>
            <input type="text" id="searchTask" class="filter-input" placeholder="Type task name…" oninput="applyFilters()">
          </div>

          <!-- Project -->
          <div class="filter-group">
            <label class="filter-label">Project</label>
            <select id="filterProject" class="filter-input" onchange="applyFilters()">
              <option value="all">All Projects</option>
              <?php foreach ($filterProjects as $p): ?>
                <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Group -->
          <div class="filter-group">
            <label class="filter-label">Group</label>
            <select id="filterGroup" class="filter-input" onchange="applyFilters()">
              <option value="all">All Groups</option>
              <?php foreach ($filterGroups as $g): ?>
                <option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Assignee -->
          <div class="filter-group">
            <label class="filter-label">Assignee</label>
            <select id="filterAssignee" class="filter-input" onchange="applyFilters()">
              <option value="all">Everyone</option>
              <?php foreach ($filterAssignees as $u): ?>
                <option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Date Range -->
          <div class="filter-group">
            <label class="filter-label">Date Range</label>
            <div class="date-range-wrap">
              <input type="date" id="dateFrom" class="filter-input"
                     title="From date" onchange="onDateChange()">
              <span class="date-sep">→</span>
              <input type="date" id="dateTo" class="filter-input"
                     title="To date" onchange="onDateChange()">
            </div>
            <span class="date-error" id="dateError">
              <i class="fas fa-exclamation-circle"></i> End must be after start
            </span>
          </div>

          <!-- Reset -->
          <div class="filter-group" style="justify-content:flex-end">
            <label class="filter-label">&nbsp;</label>
            <button class="btn-reset" onclick="resetFilters()">
              <i class="fas fa-undo"></i> Reset
            </button>
          </div>

        </div>

        <!-- ══ STATS PILLS (always visible) ══ -->
        <div class="stats-strip">
          <div class="stat-pill active" data-s="all" onclick="setPill('all',this)">All Tasks</div>
          <div class="stat-pill" data-s="in-progress" onclick="setPill('in-progress',this)">
            <div class="dot dot-progress"></div><?= $statusCounts['in_progress'] ?? 0 ?> In Progress
          </div>
          <div class="stat-pill" data-s="complete" onclick="setPill('complete',this)">
            <div class="dot dot-success"></div><?= $statusCounts['complete'] ?? 0 ?> Complete
          </div>
        </div>

        <?= $messageHtml ?>

        <!-- ══════════════════════════════════════
             LIST VIEW
        ══════════════════════════════════════ -->
        <div id="listView">
          <form method="POST" id="bulkForm">

            <!-- Bulk bar -->
            <div class="bulk-actions-bar" id="bulkBar">
              <span class="bulk-info"><span id="selCount">0</span> task(s) selected</span>
              <button type="button" class="bulk-btn btn-complete" onclick="doBulk('complete')">
                <i class="fas fa-check-circle"></i> Mark Complete
              </button>
              <button type="button" class="bulk-btn btn-progress" onclick="doBulk('progress')">
                <i class="fas fa-sync-alt"></i> Set In Progress
              </button>
              <button type="button" class="bulk-btn btn-delete" onclick="doBulk('delete')">
                <i class="fas fa-trash-alt"></i> Delete Selected
              </button>
            </div>

            <!-- Table -->
            <div class="table-container">
              <div class="table-wrapper">
                <table>
                  <thead>
                    <tr>
                      <th class="cb-col"><input type="checkbox" class="checkbox-input" id="selAll" onchange="toggleAll()"></th>
                      <th>Status</th>
                      <th>Task</th>
                      <th>Group</th>
                      <th>Project</th>
                      <th>Tags</th>
                      <th>Assignee</th>
                      <th>Deadline</th>
                      <th>Last Activity</th>
                      <th class="text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="taskBody">
                  <?php
                  $today_dt = new DateTime();
                  foreach ($tasks as $task):
                    $status_raw  = $task['status'] ?? 'pending';
                    $status_slug = str_replace([' ','_'], '-', strtolower($status_raw));
                    $proj_val    = $task['project_display_name'] ?? 'General';
                    $proj_id     = $task['project_id'] ?? null;
                    $group_val   = $task['group_name'] ?? 'General Tasks';
                    $user_val    = $task['assigned_username'] ?? 'Unassigned';
                    $tags        = !empty($task['tags']) ? explode(',', $task['tags']) : [];
                    $due_dt      = new DateTime($task['due_date']);
                    $is_overdue  = ($due_dt < $today_dt && $status_slug !== 'complete');
                    $upd         = new DateTime($task['updated_at'] ?? $task['created_at']);
                    $diff        = (new DateTime())->diff($upd);
                    $ago = $diff->days === 0
                         ? ($diff->h === 0 ? $diff->i.'m ago' : $diff->h.'h ago')
                         : $upd->format('M d, Y');
                    // Store due_date as data attribute for JS date filtering
                    $due_key = $due_dt->format('Y-m-d');
                  ?>
                  <tr class="task-row <?= $is_overdue ? 'overdue' : '' ?>"
                      data-status="<?= $status_slug ?>"
                      data-project="<?= htmlspecialchars($proj_val) ?>"
                      data-group="<?= htmlspecialchars($group_val) ?>"
                      data-user="<?= htmlspecialchars($user_val) ?>"
                      data-title="<?= strtolower(htmlspecialchars($task['title'])) ?>"
                      data-due="<?= $due_key ?>">
                    <td class="cb-col">
                      <input type="checkbox" class="checkbox-input task-cb" name="task_ids[]" value="<?= $task['id'] ?>" onchange="updateBulk()">
                    </td>
                    <td>
                      <span class="status-badge <?= $status_slug ?>">
                        <?= ucwords(str_replace(['-','_'],' ', $status_raw)) ?>
                      </span>
                    </td>
                    <td>
                      <a href="view_task.php?id=<?= $task['id'] ?>" class="task-name-link">
                        <span class="task-name"><?= htmlspecialchars($task['title']) ?></span>
                      </a>
                    </td>
                    <td><span class="group-label"><?= htmlspecialchars($group_val) ?></span></td>
                    <td>
                      <?php if ($proj_id): ?>
                        <a href="view_project.php?id=<?= $proj_id ?>" class="project-link">
                          <span class="project-label"><?= htmlspecialchars($proj_val) ?></span>
                        </a>
                      <?php else: ?>
                        <span class="project-label"><?= htmlspecialchars($proj_val) ?></span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php foreach (array_slice($tags, 0, 2) as $tag): ?>
                        <span class="tag-badge"><?= htmlspecialchars(trim($tag)) ?></span>
                      <?php endforeach; ?>
                    </td>
                    <td><span class="assignee-name"><?= htmlspecialchars($user_val) ?></span></td>
                    <td>
                      <span class="deadline-text"><?= date('M d, Y', strtotime($task['due_date'])) ?></span>
                      <?php if ($is_overdue): ?>
                        <span class="overdue-label">
                          <i class="fas fa-exclamation-triangle"></i> OVERDUE
                        </span>
                      <?php endif; ?>
                    </td>
                    <td><span class="activity-time"><?= $ago ?></span></td>
                    <td class="text-right">
                      <div class="action-btns">
                        <a href="view_task.php?id=<?= $task['id'] ?>" class="icon-btn" title="View">
                          <i class="fas fa-eye"></i>
                        </a>
                        <a href="edit_task.php?id=<?= $task['id'] ?>" class="icon-btn" title="Edit">
                          <i class="fas fa-edit"></i>
                        </a>
                        <a href="delete_task.php?id=<?= $task['id'] ?>" class="icon-btn delete" title="Delete"
                           onclick="return confirm('Permanently delete this task?')">
                          <i class="fas fa-trash-alt"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <input type="hidden" name="bulk_action" id="bulkActionVal">
          </form>
        </div><!-- /listView -->

        <!-- ══════════════════════════════════════
             CALENDAR VIEW
        ══════════════════════════════════════ -->
        <div id="calendarView">

          <!-- Calendar toolbar -->
          <div class="cal-toolbar">
            <div class="cal-nav">
              <button class="cal-nav-btn" onclick="calNav(-1)">
                <i class="fas fa-chevron-left"></i>
              </button>
              <button class="cal-today-btn" onclick="calToday()">Today</button>
              <button class="cal-nav-btn" onclick="calNav(1)">
                <i class="fas fa-chevron-right"></i>
              </button>
            </div>
            <div class="cal-title" id="calTitle">—</div>
            <div class="cal-sub-views">
              <button class="cal-sub-btn active" id="sbMonth" onclick="setSub('month',this)">Month</button>
              <button class="cal-sub-btn" id="sbWeek"  onclick="setSub('week',this)">Week</button>
              <button class="cal-sub-btn" id="sbDay"   onclick="setSub('day',this)">Day</button>
            </div>
          </div>

          <!-- Calendar body rendered by JS -->
          <div id="calBody"></div>
        </div><!-- /calendarView -->

      </div><!-- /content-limit -->
    </main>
  </div><!-- /wrapper -->
</div><!-- /layout-wrapper -->

<!-- Hover popup -->
<div class="day-popup" id="dayPopup"></div>


<script>
// ── ALL TASK DATA FROM PHP ──
const TASKS     = <?= json_encode($tasksJson ?? [], JSON_HEX_TAG) ?>;
const TODAY_STR = '<?= date('Y-m-d') ?>';
</script>

<script>
/* ══════════════════════════════════════════════════════════
   VIEW SWITCHER
══════════════════════════════════════════════════════════ */
const VIEW_ICONS  = { list:'fa-th-list', calendar:'fa-calendar-alt' };
const VIEW_LABELS = { list:'List View', calendar:'Calendar View' };
let activeView = 'list';

function toggleVD(){
  document.getElementById('vsBtn').classList.toggle('open');
  document.getElementById('viewDropdown').classList.toggle('open');
}

function switchView(v){
  activeView = v;
  document.getElementById('vsBtn').classList.remove('open');
  document.getElementById('viewDropdown').classList.remove('open');
  const vsIcon = document.getElementById('vsIcon');
  vsIcon.className = 'fas ' + VIEW_ICONS[v];
  document.getElementById('vsLabel').textContent = VIEW_LABELS[v];
  ['vdList','vdCalendar'].forEach(id => document.getElementById(id).classList.remove('active'));
  const vdMap = { list:'vdList', calendar:'vdCalendar' };
  document.getElementById(vdMap[v]).classList.add('active');
  document.getElementById('listView').style.display     = (v === 'list')     ? 'block' : 'none';
  document.getElementById('calendarView').style.display = (v === 'calendar') ? 'block' : 'none';
  if (v === 'calendar') setTimeout(renderCal, 20);
}

document.addEventListener('click', e => {
  if (!document.getElementById('viewSwitcher').contains(e.target)){
    document.getElementById('vsBtn').classList.remove('open');
    document.getElementById('viewDropdown').classList.remove('open');
  }
});

/* ══════════════════════════════════════════════════════════
   DATE RANGE FILTER
══════════════════════════════════════════════════════════ */
const elDateFrom = document.getElementById('dateFrom');
const elDateTo   = document.getElementById('dateTo');
const elDateErr  = document.getElementById('dateError');

function onDateChange(){
  const from = elDateFrom.value;
  const to   = elDateTo.value;

  // Visual indicator when a value is set
  elDateFrom.classList.toggle('has-value', !!from);
  elDateTo.classList.toggle('has-value', !!to);

  // Validate: both set but end < start
  if (from && to && to < from){
    elDateErr.style.display = 'flex';
    elDateTo.style.borderColor = 'var(--danger)';
    elDateTo.style.boxShadow   = '0 0 0 3px rgba(239,68,68,.12)';
    return; // don't filter with invalid range
  }

  // Clear error
  elDateErr.style.display = 'none';
  elDateTo.style.borderColor = '';
  elDateTo.style.boxShadow   = '';

  // Auto-apply immediately
  applyFilters();
}

/* ══════════════════════════════════════════════════════════
   FILTER & PILL LOGIC
══════════════════════════════════════════════════════════ */
let activePill = 'all';

function setPill(s, el){
  activePill = s;
  document.querySelectorAll('.stat-pill').forEach(p => p.classList.remove('active'));
  el.classList.add('active');
  applyFilters();
}

function applyFilters(){
  const search   = document.getElementById('searchTask').value.toLowerCase().trim();
  const project  = document.getElementById('filterProject').value;
  const group    = document.getElementById('filterGroup').value;
  const assignee = document.getElementById('filterAssignee').value;
  const from     = elDateFrom.value;   // '' or 'YYYY-MM-DD'
  const to       = elDateTo.value;     // '' or 'YYYY-MM-DD'

  // Only apply date range if valid (not invalid state)
  const dateValid = !(from && to && to < from);

  document.querySelectorAll('.task-row').forEach(row => {
    const due = row.dataset.due || '';

    // Date range check
    let dateOk = true;
    if (dateValid){
      if (from && due < from) dateOk = false;
      if (to   && due > to)   dateOk = false;
    }

    const ok = (activePill === 'all' || row.dataset.status === activePill)
            && (project    === 'all' || row.dataset.project === project)
            && (group      === 'all' || row.dataset.group   === group)
            && (assignee   === 'all' || row.dataset.user    === assignee)
            && (!search || row.dataset.title.includes(search))
            && dateOk;

    row.classList.toggle('hidden', !ok);
  });

  // Sync calendar if active
  if (activeView === 'calendar') renderCal();
}

function resetFilters(){
  document.getElementById('searchTask').value     = '';
  document.getElementById('filterProject').value  = 'all';
  document.getElementById('filterGroup').value    = 'all';
  document.getElementById('filterAssignee').value = 'all';
  elDateFrom.value = '';
  elDateTo.value   = '';
  elDateFrom.classList.remove('has-value');
  elDateTo.classList.remove('has-value');
  elDateErr.style.display    = 'none';
  elDateTo.style.borderColor = '';
  elDateTo.style.boxShadow   = '';
  activePill = 'all';
  document.querySelectorAll('.stat-pill').forEach(p => p.classList.remove('active'));
  document.querySelector('.stat-pill[data-s="all"]').classList.add('active');
  applyFilters();
}

/* ══════════════════════════════════════════════════════════
   BULK ACTIONS
══════════════════════════════════════════════════════════ */
function toggleAll(){
  const checked = document.getElementById('selAll').checked;
  document.querySelectorAll('.task-cb').forEach(cb => {
    if (!cb.closest('.task-row').classList.contains('hidden')) cb.checked = checked;
  });
  updateBulk();
}

function updateBulk(){
  const n = document.querySelectorAll('.task-cb:checked').length;
  document.getElementById('selCount').textContent = n;
  document.getElementById('bulkBar').classList.toggle('active', n > 0);
  if (!n) document.getElementById('selAll').checked = false;
}

function doBulk(action){
  const boxes = document.querySelectorAll('.task-cb:checked');
  if (!boxes.length){ alert('Select at least one task.'); return; }
  const msgs = {
    delete:   `Delete ${boxes.length} task(s)? This cannot be undone.`,
    complete: `Mark ${boxes.length} task(s) as complete?`,
    progress: `Set ${boxes.length} task(s) to in progress?`
  };
  if (confirm(msgs[action])){
    document.getElementById('bulkActionVal').value = action;
    document.getElementById('bulkForm').submit();
  }
}

/* ══════════════════════════════════════════════════════════
   CALENDAR ENGINE
══════════════════════════════════════════════════════════ */
const CAL_MONTHS = ['January','February','March','April','May','June',
                    'July','August','September','October','November','December'];
const CAL_DAYS   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

let calSub  = 'month';
let calDate = new Date(TODAY_STR + 'T12:00:00');

/* ── Build date-key → [task] map, honouring ALL active filters including date range ── */
function buildCalMap(){
  const search   = document.getElementById('searchTask').value.toLowerCase().trim();
  const project  = document.getElementById('filterProject').value;
  const group    = document.getElementById('filterGroup').value;
  const assignee = document.getElementById('filterAssignee').value;
  const from     = elDateFrom.value;
  const to       = elDateTo.value;
  const dateValid = !(from && to && to < from);

  const calMap = {};
  TASKS.forEach(task => {
    const dateKey = (task.due_date || '').substring(0, 10);
    if (!dateKey || dateKey.length !== 10) return;

    if (activePill !== 'all' && task.status !== activePill) return;
    if (project    !== 'all' && task.project  !== project)  return;
    if (group      !== 'all' && task.group    !== group)    return;
    if (assignee   !== 'all' && task.assignee !== assignee) return;
    if (search && !task.title.toLowerCase().includes(search)) return;

    // Date range filter
    if (dateValid){
      if (from && dateKey < from) return;
      if (to   && dateKey > to)   return;
    }

    if (!calMap[dateKey]) calMap[dateKey] = [];
    calMap[dateKey].push(task);
  });
  return calMap;
}

/* Return CSS class for a task chip */
function getChipClass(task){
  const due = (task.due_date || '').substring(0, 10);
  if (due && due < TODAY_STR && task.status !== 'complete') return 'overdue';
  return task.status || 'pending';
}

/* Format Date → YYYY-MM-DD */
function toDateKey(d){
  return d.getFullYear() + '-'
       + String(d.getMonth() + 1).padStart(2, '0') + '-'
       + String(d.getDate()).padStart(2, '0');
}

function escH(s){
  return String(s || '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* Is a date key within the selected range? (for visual highlighting) */
function isInRange(dateKey){
  const from = elDateFrom.value;
  const to   = elDateTo.value;
  if (!from && !to) return false;
  if (from && to && to < from) return false;
  if (from && dateKey === from) return 'start';
  if (to   && dateKey === to)   return 'end';
  if (from && to && dateKey > from && dateKey < to) return 'in';
  return false;
}

/* ── Navigation ── */
function calNav(dir){
  if      (calSub === 'month') calDate.setMonth(calDate.getMonth() + dir);
  else if (calSub === 'week')  calDate.setDate(calDate.getDate() + dir * 7);
  else                         calDate.setDate(calDate.getDate() + dir);
  renderCal();
}
function calToday(){ calDate = new Date(TODAY_STR + 'T12:00:00'); renderCal(); }
function setSub(v, el){
  calSub = v;
  document.querySelectorAll('.cal-sub-btn').forEach(b => b.classList.remove('active'));
  el.classList.add('active');
  renderCal();
}
function renderCal(){
  if      (calSub === 'month') renderMonth();
  else if (calSub === 'week')  renderWeek();
  else                         renderDay();
}

/* ══════════════════════════════
   MONTH VIEW
══════════════════════════════ */
function renderMonth(){
  const calMap = buildCalMap();
  const yr = calDate.getFullYear();
  const mo = calDate.getMonth();
  document.getElementById('calTitle').textContent = CAL_MONTHS[mo] + ' ' + yr;

  const firstDow      = new Date(yr, mo, 1).getDay();
  const daysInMonth   = new Date(yr, mo + 1, 0).getDate();
  const prevMonthDays = new Date(yr, mo, 0).getDate();
  const totalCells    = Math.ceil((firstDow + daysInMonth) / 7) * 7;

  const dowHeaders = CAL_DAYS.map(d =>
    `<div class="cal-month-dow">${d}</div>`
  ).join('');

  let dayCells = '';
  for (let i = 0; i < totalCells; i++){
    let dayNum, dateKey, isOtherMonth = false;

    if (i < firstDow){
      dayNum = prevMonthDays - firstDow + 1 + i;
      dateKey = toDateKey(new Date(yr, mo - 1, dayNum));
      isOtherMonth = true;
    } else if (i >= firstDow + daysInMonth){
      dayNum = i - firstDow - daysInMonth + 1;
      dateKey = toDateKey(new Date(yr, mo + 1, dayNum));
      isOtherMonth = true;
    } else {
      dayNum  = i - firstDow + 1;
      dateKey = toDateKey(new Date(yr, mo, dayNum));
    }

    const dayTasks = calMap[dateKey] || [];
    const total    = dayTasks.length;
    const isToday  = (dateKey === TODAY_STR);
    const rangePos = isInRange(dateKey);

    const cmp  = dayTasks.filter(t => t.status === 'complete').length;
    const prog = dayTasks.filter(t => t.status === 'in-progress').length;
    const pend = dayTasks.filter(t => t.status === 'pending').length;
    const ovrd = dayTasks.filter(t => getChipClass(t) === 'overdue').length;

    const numHtml = isToday
      ? `<div class="cal-dn"><div class="cal-dn-inner">${dayNum}</div></div>`
      : `<div class="cal-dn">${dayNum}</div>`;

    const chips = dayTasks.slice(0, 2).map(t =>
      `<a href="view_task.php?id=${t.id}" class="cal-chip ${getChipClass(t)}" title="${escH(t.title)}">${escH(t.title)}</a>`
    ).join('');

    const moreLink = total > 2
      ? `<span class="cal-more" onclick="event.stopPropagation();drillDay('${dateKey}')">+${total - 2} more</span>`
      : '';

    // Build class list — include range highlight classes
    const cls = ['cal-day'];
    if (isOtherMonth) cls.push('other-month');
    if (isToday)      cls.push('today');
    if (rangePos === 'start') cls.push('range-start');
    else if (rangePos === 'end') cls.push('range-end');
    else if (rangePos === 'in')  cls.push('in-range');

    dayCells += `
      <div class="${cls.join(' ')}"
           data-date="${dateKey}" data-total="${total}"
           data-cmp="${cmp}" data-prog="${prog}" data-pend="${pend}" data-ovrd="${ovrd}"
           onmouseenter="showPopup(event,this)" onmouseleave="hidePopup()"
           onclick="handleDayClick(event,'${dateKey}')">
        <div class="cal-day-header">
          ${numHtml}
          <span class="cal-cnt${total ? ' on' : ''}">${total}</span>
        </div>
        ${chips}${moreLink}
      </div>`;
  }

  document.getElementById('calBody').innerHTML = `
    <div class="cal-month">
      <div class="cal-month-header">${dowHeaders}</div>
      <div class="cal-month-body">${dayCells}</div>
    </div>`;
}

function handleDayClick(e, dateKey){
  if (e.target.closest('.cal-chip') || e.target.closest('.cal-more')) return;
  drillDay(dateKey);
}
function drillDay(dateKey){
  calDate = new Date(dateKey + 'T12:00:00');
  calSub  = 'day';
  document.querySelectorAll('.cal-sub-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('sbDay').classList.add('active');
  renderCal();
}

/* ══════════════════════════════
   WEEK VIEW
══════════════════════════════ */
function renderWeek(){
  const calMap = buildCalMap();
  const weekStart = new Date(calDate);
  weekStart.setDate(calDate.getDate() - calDate.getDay());
  weekStart.setHours(12, 0, 0, 0);

  const weekDays = [];
  for (let i = 0; i < 7; i++){
    const d = new Date(weekStart);
    d.setDate(weekStart.getDate() + i);
    weekDays.push(d);
  }

  const s = weekDays[0], e = weekDays[6];
  document.getElementById('calTitle').textContent =
    CAL_MONTHS[s.getMonth()].slice(0,3) + ' ' + s.getDate() +
    ' – ' + CAL_MONTHS[e.getMonth()].slice(0,3) + ' ' + e.getDate() + ', ' + e.getFullYear();

  let headHtml = `<div class="cal-wk-gutter-h"></div>`;
  weekDays.forEach(d => {
    const dk = toDateKey(d);
    const dt = calMap[dk] || [];
    const isToday = dk === TODAY_STR;
    headHtml += `
      <div class="cal-wk-day-h${isToday ? ' today' : ''}${dt.length ? ' busy' : ''}">
        <div class="wdow">${CAL_DAYS[d.getDay()]}</div>
        <div class="wnum">${d.getDate()}</div>
        <div class="wcnt">${dt.length ? dt.length + ' task' + (dt.length > 1 ? 's' : '') : '—'}</div>
      </div>`;
  });

  let bodyHtml = `<div class="cal-wk-gutter-b"></div>`;
  weekDays.forEach(d => {
    const dk = toDateKey(d);
    const dt = calMap[dk] || [];
    const isToday   = dk === TODAY_STR;
    const rangePos  = isInRange(dk);
    const rangeCls  = rangePos ? ' in-range-col' : '';
    const chips = dt.map(t =>
      `<a href="view_task.php?id=${t.id}" class="cal-chip ${getChipClass(t)}" title="${escH(t.title)}">${escH(t.title)}</a>`
    ).join('');
    bodyHtml += `<div class="cal-wk-cell${isToday ? ' today-col' : ''}${rangeCls}">${chips}</div>`;
  });

  document.getElementById('calBody').innerHTML = `
    <div class="cal-week">
      <div class="cal-wk-grid cal-wk-head">${headHtml}</div>
      <div class="cal-wk-grid">${bodyHtml}</div>
    </div>`;
}

/* ══════════════════════════════
   DAY VIEW
══════════════════════════════ */
function renderDay(){
  const calMap   = buildCalMap();
  const dateKey  = toDateKey(calDate);
  const dayTasks = calMap[dateKey] || [];
  const isToday  = dateKey === TODAY_STR;
  const dowName  = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][calDate.getDay()];

  document.getElementById('calTitle').textContent =
    dowName + ', ' + CAL_MONTHS[calDate.getMonth()] + ' ' + calDate.getDate() + ', ' + calDate.getFullYear();

  const badge = dayTasks.length
    ? `<span class="dv-badge">${dayTasks.length} Task${dayTasks.length > 1 ? 's' : ''}</span>`
    : `<span class="dv-badge empty">No tasks</span>`;

  let bodyHtml;
  if (!dayTasks.length){
    bodyHtml = `<div class="cal-empty">
      <i class="fas fa-calendar-check"></i>
      No tasks due on this day
    </div>`;
  } else {
    const items = dayTasks.map(t => {
      const cls = getChipClass(t);
      return `
        <a href="view_task.php?id=${t.id}" class="dv-item">
          <div class="dv-dot ${cls}"></div>
          <div class="dv-info">
            <div class="dv-title">${escH(t.title)}</div>
            <div class="dv-meta">${escH(t.project)} · ${escH(t.assignee)}</div>
          </div>
          <span class="status-badge ${cls}">${cls.replace('-', ' ')}</span>
        </a>`;
    }).join('');
    bodyHtml = `<div class="dv-list">${items}</div>`;
  }

  document.getElementById('calBody').innerHTML = `
    <div class="cal-day-view">
      <div class="cal-dv-header">
        <div class="dv-date-block">
          <div class="dv-big">${calDate.getDate()}</div>
          <div class="dv-label">${dowName}${isToday ? ' · Today' : ''}</div>
        </div>
        ${badge}
      </div>
      ${bodyHtml}
    </div>`;
}

/* ══════════════════════════════
   HOVER POPUP
══════════════════════════════ */
function showPopup(e, el){
  const total = parseInt(el.dataset.total) || 0;
  if (!total) return;
  const popup = document.getElementById('dayPopup');
  const d = new Date(el.dataset.date + 'T12:00:00');
  const lbl = CAL_MONTHS[d.getMonth()].slice(0,3) + ' ' + d.getDate() + ', ' + d.getFullYear();

  const rows = [
    [parseInt(el.dataset.prog), 'In Progress', '#a5b4fc'],
    [parseInt(el.dataset.pend), 'Pending',     '#94a3b8'],
    [parseInt(el.dataset.cmp),  'Complete',    '#6ee7b7'],
    [parseInt(el.dataset.ovrd), 'Overdue',     '#fca5a5'],
  ].filter(r => r[0] > 0).map(r =>
    `<div class="dp-row">
       <span class="dp-lbl" style="color:${r[2]}">${r[1]}</span>
       <span class="dp-val">${r[0]}</span>
     </div>`
  ).join('');

  popup.innerHTML = `
    <div class="dp-date">${lbl}</div>
    ${rows}
    <div class="dp-row dp-total">
      <span class="dp-lbl">Total</span>
      <span class="dp-val">${total}</span>
    </div>`;
  popup.classList.add('on');
  movePopup(e);
}

function hidePopup(){ document.getElementById('dayPopup').classList.remove('on'); }
function movePopup(e){
  const p = document.getElementById('dayPopup');
  p.style.left = Math.min(e.clientX + 14, window.innerWidth  - 185) + 'px';
  p.style.top  = Math.min(e.clientY + 14, window.innerHeight - 165) + 'px';
}
document.addEventListener('mousemove', e => {
  if (document.getElementById('dayPopup').classList.contains('on')) movePopup(e);
});
</script>
</body>
</html>