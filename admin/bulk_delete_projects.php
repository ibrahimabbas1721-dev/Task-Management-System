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

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_ids'])) {
    $project_ids = explode(',', $_POST['project_ids']);
    $project_ids = array_map('intval', $project_ids);
    $project_ids = array_filter($project_ids, fn($id) => $id > 0);
    
    if (!empty($project_ids)) {
        try {
            // Smart column detection
            $colName = 'created_by_admin';
            $check = $pdo->query("SHOW COLUMNS FROM projects LIKE 'created_by_admin'");
            if ($check->fetch()) {
                $colName = 'created_by_admin';
            }
            
            // Create placeholders for IN clause
            $placeholders = implode(',', array_fill(0, count($project_ids), '?'));
            
            // Delete projects (this will cascade delete tasks if FK is set, otherwise delete tasks first)
            $sql = "DELETE FROM projects WHERE id IN ($placeholders) AND $colName = ?";
            $params = array_merge($project_ids, [$admin_id]);
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $deletedCount = $stmt->rowCount();
            header("Location: projects.php?msg=bulk_deleted&count=$deletedCount");
            exit;
        } catch (PDOException $e) {
            header("Location: projects.php?error=" . urlencode("Bulk delete failed"));
            exit;
        }
    }
}

header("Location: projects.php");
exit;
?>