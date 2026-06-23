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
$error = '';
$admin_id = $_SESSION['user_id'] ?? 0; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password  = $_POST['password'];
    $role      = $_POST['role'];

    if (empty($username) || empty($email) || empty($password) || empty($role) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "All fields are required and email must be valid.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        try {
            $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $checkEmail->execute([$email]);
            
            if ($checkEmail->rowCount() > 0) {
                $error = "Email already in use.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_by_admin, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$username, $email, $hashed_password, $role, $admin_id]);
                
                header("Location: manage_users.php?msg=created");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Add New User | TMS Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    
    <link rel="stylesheet" href="../fontawesome/css/all.min.css" />
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-light: #eff6ff;
            --deep-dark: #161e2d;
            --bg: #f8fafc;
            --white: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --danger: #ef4444;
            --danger-bg: #fef2f2;
            --danger-border: #fee2e2;
            --radius: 24px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--bg); 
            color: var(--text-main);
            display: flex; 
            height: 100vh; 
            overflow: hidden; 
        }

        .sidebar-spacer {
            width: 260px;
            flex-shrink: 0;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .scroll-area {
            flex: 1;
            overflow-y: auto;
            padding: 2.5rem;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
        }

        .banner { 
            background: var(--deep-dark); 
            border-radius: var(--radius); 
            padding: 1.5rem 2rem; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            margin-bottom: 2.5rem;
            color: white;
        }

        .banner-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .banner-icon { 
            width: 56px;
            height: 56px; 
            background: rgba(255, 255, 255, 0.05); 
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px; 
            display: flex;
            align-items: center;
            justify-content: center; 
            color: var(--primary);
        }

        /* Updated Icon Size for Font Awesome */
        .banner-icon i {
            font-size: 24px;
        }

        .banner-text h1 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 2px;
            color: #fff;
        }

        .banner-text p {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 10px 18px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .btn-back i {
            font-size: 14px;
        }

        .error-box { 
            background: var(--danger-bg); 
            border: 1px solid var(--danger-border); 
            color: var(--danger); 
            padding: 1rem; 
            border-radius: 16px; 
            font-size: 13px; 
            font-weight: 600; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            margin-bottom: 1.5rem; 
        }

        .error-box i {
            font-size: 18px;
        }

        .form-card { 
            background: var(--white); 
            border: 1px solid var(--border); 
            border-radius: var(--radius); 
            padding: 2.5rem; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
        }

        .form-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 1.5rem; 
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 1.5rem;
        }
        
        .label-style { 
            font-size: 11px; 
            font-weight: 800; 
            color: var(--text-muted); 
            text-transform: uppercase; 
            letter-spacing: 0.05em; 
            padding-left: 4px;
        }

        .input-wrapper {
            position: relative;
            width: 100%;
        }

        .tms-input { 
            width: 100%; 
            background: #fcfdfe; 
            border: 1px solid var(--border); 
            border-radius: 12px; 
            padding: 14px 16px; 
            font-weight: 600; 
            color: var(--text-main); 
            font-size: 14px; 
            outline: none; 
            transition: all 0.2s;
            font-family: inherit;
        }

        .tms-input:focus { 
            border-color: var(--primary); 
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.05); 
            background: var(--white);
        }

        select.tms-input {
            appearance: none;
            cursor: pointer;
        }

        /* Updated Select Icon for Font Awesome */
        .select-icon { 
            position: absolute;
            right: 16px;
            top: 50%; 
            transform: translateY(-50%); 
            pointer-events: none;
            color: var(--text-muted); 
            font-size: 14px;
        }

        .btn-container { 
            margin-top: 1rem; 
            padding-top: 2rem; 
            border-top: 1px solid var(--border); 
            display: flex;
            justify-content: flex-end;
        }

        .btn-submit { 
            padding: 0.85rem 2rem; 
            background: var(--primary); 
            color: white; 
            border: none; 
            border-radius: 14px; 
            font-weight: 700; 
            font-size: 14px; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            transition: all 0.3s;
            font-family: inherit;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            background: #1e40af;
        }

        .btn-submit i {
            font-size: 16px;
        }

        /* Mobile Adjustments */
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar-spacer { width: 100%; border-bottom: 1px solid var(--border); }
            .scroll-area { padding: 1.5rem; }
            .banner { flex-direction: column; gap: 1rem; padding: 1.5rem; }
            .form-grid { grid-template-columns: 1fr; gap: 0; }
            .btn-submit { width: 100%; justify-content: center; }
        }
    </style>
</head>

<body>
    <div class="sidebar-spacer">
        <?php $activePage = 'manage_users'; include '../includes/admin_sidebar.php'; ?>
    </div>

    <div class="main-content">
        <?php include '../includes/admin_header.php'; ?>

        <main class="scroll-area">
            <div class="container">
                
                <div class="banner">
                    <div class="banner-info">
                        <div class="banner-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="banner-text">
                            <h1>New Personnel</h1>
                            <p>System User Onboarding</p>
                        </div>
                    </div>
                    <a href="manage_users.php" class="btn-back">
                        <i class="fas fa-chevron-left"></i> Back
                    </a>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="error-box">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="form-card">
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="label-style">Identity / Username</label>
                                <input type="text" name="username" required class="tms-input" placeholder="e.g. j.smith" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label class="label-style">Clearance / Access Role</label>
                                <div class="input-wrapper">
                                    <select name="role" required class="tms-input">
                                        <option value="user">Standard User</option>
                                        <option value="admin">System Administrator</option>
                                    </select>
                                    <i class="fas fa-chevron-down select-icon"></i>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="label-style">Communication / Email Address</label>
                            <input type="email" name="email" required class="tms-input" placeholder="personnel@tms.pro" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="label-style">Secure Key / Password</label>
                            <input type="password" name="password" required class="tms-input" placeholder="Min. 6 characters required">
                        </div>

                        <div class="btn-container">
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-user-shield"></i>
                                Authorize and Create
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>