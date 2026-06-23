<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'pro';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to check if user is logged in and redirect if not
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../auth/login.php'); 
        exit();
    }
}

// Function to check role and redirect if not matching
function requireRole($role) {
    if (!isset($_SESSION['role'])) {
        header('Location: ../auth/login.php');
        exit();
    }
    if ($_SESSION['role'] !== $role) {
        if ($_SESSION['role'] === 'admin') {
            header('Location: ../admin/dashboard.php');
        } else {
            header('Location: ../user/dashboard.php');
        }
        exit();
    }
}

// Helper function for redirects with message
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['message'] = ['text' => $message, 'type' => $type];
    header("Location: $url");
    exit();
}
?>