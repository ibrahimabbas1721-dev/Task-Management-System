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

// 1. DATABASE AUTO-FIX
try {
    $pdo->query("SELECT profile_pic FROM users LIMIT 1");
} catch (PDOException $e) {
    $pdo->execute("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL");
}

// 2. SESSION SECURITY
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$msg = '';
$msg_type = '';

// 3. POST LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cropped_image']) && !empty($_POST['cropped_image'])) {
        $data = $_POST['cropped_image'];
        list($type, $data) = explode(';', $data);
        list(, $data)      = explode(',', $data);
        $decoded_image = base64_decode($data);
        $filename = "admin_" . $user_id . "_" . time() . ".png";
        $upload_path = "../uploads/profiles/" . $filename;

        if (!is_dir('../uploads/profiles/')) mkdir('../uploads/profiles/', 0777, true);

        if (file_put_contents($upload_path, $decoded_image)) {
            if (!empty($user['profile_pic']) && file_exists("../uploads/profiles/" . $user['profile_pic'])) {
                unlink("../uploads/profiles/" . $user['profile_pic']);
            }
            $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->execute([$filename, $user_id]);
            $user['profile_pic'] = $filename;
            $msg = "Profile visual synchronized.";
            $msg_type = "success";
        }
    }

    if (isset($_POST['update_details'])) {
        $email = $_POST['email'];
        $current_pw = $_POST['current_password'];
        $new_pw = $_POST['new_password'];
        $conf_pw = $_POST['confirm_password'];

        if (password_verify($current_pw, $user['password'])) {
            if (!empty($new_pw)) {
                if ($new_pw === $conf_pw) {
                    $hashed_pw = password_hash($new_pw, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET email = ?, password = ? WHERE id = ?");
                    $stmt->execute([$email, $hashed_pw, $user_id]);
                    $msg = "Credentials updated successfully.";
                } else {
                    $msg = "Password confirmation mismatch.";
                    $msg_type = "error";
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->execute([$email, $user_id]);
                $msg = "Email address updated.";
            }
            $msg_type = $msg_type ?: "success";
        } else {
            $msg = "Verification failed: Invalid current password.";
            $msg_type = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Registry | TMS Pro</title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link rel="stylesheet" href="../fontawesome/css/all.min.css" />

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --primary: #4f46e5;
            --primary-glow: rgba(79, 70, 229, 0.15);
            --dark: #0f172a;
            --deep-dark: #020617;
            --surface: #ffffff;
            --bg: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --danger: #ef4444;
            --success: #10b981;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --radius-lg: 24px;
            --radius-md: 16px;
            --radius-sm: 12px;
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-2xl: 2.5rem;
            --spacing-3xl: 3.2rem;
            --transition: 0.2s;
            --transition-lg: 0.3s;
            --transition-cubic: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -2px rgba(0, 0, 0, 0.02);
            --box-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --box-shadow-primary: 0 10px 20px -10px var(--primary);
        }

        html,
        body {
            height: 100%;
            width: 100%;
            overflow-x: hidden;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg);
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            color: var(--text-main);
        }

        .layout-wrapper {
            display: flex;
            height: 100vh;
            width: 100%;
            overflow-x: hidden;
        }

        .sidebar-spacer {
            width: 260px;
            flex-shrink: 0;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            width: 100%;
        }

        .main-content {
            flex: 1;
            padding: var(--spacing-xl);
            overflow-y: auto;
            overflow-x: hidden;
            width: 100%;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            width: 100%;
        }

        /* Unified Dark Banner */
        .page-banner {
            background: var(--deep-dark);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl) var(--spacing-2xl);
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--spacing-xl);
            position: relative;
            overflow: hidden;
        }

        .banner-content {
            display: flex;
            align-items: center;
            gap: var(--spacing-lg);
            position: relative;
            z-index: 2;
        }

        .banner-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            border: 1px solid rgba(255, 255, 255, 0.08);
            flex-shrink: 0;
        }

        .banner-text h1 {
            color: white;
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.3;
        }

        .banner-text p {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-top: var(--spacing-xs);
            line-height: 1.4;
        }

        /* Grid Layout */
        .profile-layout {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: var(--spacing-xl);
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: var(--spacing-2xl);
            box-shadow: var(--box-shadow);
        }

        /* Identity Side */
        .avatar-section {
            text-align: center;
        }

        .avatar-container {
            position: relative;
            width: 160px;
            height: 160px;
            margin: 0 auto var(--spacing-xl);
        }

        .img-profile {
            width: 100%;
            height: 100%;
            border-radius: 40px;
            object-fit: cover;
            border: 6px solid var(--bg);
            box-shadow: var(--box-shadow-lg);
            display: block;
        }

        .avatar-placeholder {
            width: 100%;
            height: 100%;
            border-radius: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            background: var(--primary);
            border: 6px solid var(--bg);
            box-shadow: var(--box-shadow-lg);
        }

        .avatar-icon {
            font-size: 64px;
        }

        .edit-trigger {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--primary);
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: var(--transition-cubic);
            border: 4px solid white;
            box-shadow: var(--box-shadow-lg);
        }

        .edit-trigger:hover {
            transform: scale(1.1) rotate(5deg);
            background: var(--dark);
        }

        .edit-trigger:active {
            transform: scale(0.95);
        }

        .edit-icon {
            font-size: 24px;
        }

        .info-list {
            margin-top: var(--spacing-xl);
            list-style: none;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid var(--border-light);
            line-height: 1.5;
        }

        .info-item:last-child {
            border: none;
        }

        .info-label {
            font-size: 10px;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .info-value {
            font-size: 13px;
            font-weight: 700;
            color: var(--dark);
            line-height: 1.4;
        }

        .badge {
            background: var(--border-light);
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 800;
            color: var(--primary);
            line-height: 1.4;
        }

        .info-value-success {
            color: var(--success);
        }

        /* Form Side */
        .section-header {
            margin-bottom: var(--spacing-xl);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header span {
            color: var(--primary);
            font-size: 24px;
        }

        .section-header h2 {
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            line-height: 1.4;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }

        .input-box {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .full {
            grid-column: span 2;
        }

        label {
            font-size: 10px;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            padding-left: 4px;
            line-height: 1.4;
        }

        label.danger-label {
            color: var(--danger);
        }

        .tms-field {
            width: 100%;
            padding: 14px 18px;
            border-radius: var(--radius-md);
            border: 1.5px solid var(--border);
            background: var(--bg);
            font-size: 14px;
            font-weight: 600;
            transition: var(--transition);
            color: var(--dark);
            font-family: inherit;
            line-height: 1.5;
        }

        .tms-field:focus {
            border-color: var(--primary);
            background: white;
            outline: none;
            box-shadow: 0 0 0 4px var(--primary-glow);
        }

        .tms-field.danger-field {
            border-color: var(--danger);
            border-color: #fee2e2;
        }

        .divider {
            margin: var(--spacing-2xl) 0;
            height: 1px;
            background: var(--border-light);
        }

        .btn-save {
            background: var(--dark);
            color: white;
            border: none;
            padding: 18px;
            border-radius: var(--radius-md);
            font-weight: 800;
            text-transform: uppercase;
            font-size: 12px;
            cursor: pointer;
            width: 100%;
            transition: var(--transition-lg);
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 44px;
            line-height: 1.5;
        }

        .btn-save:hover {
            background: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--box-shadow-primary);
        }

        .btn-save:active {
            transform: translateY(0);
        }

        .save-icon {
            font-size: 18px;
        }

        .btn-alt {
            background: var(--border-light);
            color: var(--text-muted);
        }

        .btn-alt:hover {
            background: #e2e8f0;
            color: var(--text-main);
        }

        /* Modal & Alerts */
        #cropModal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(2, 6, 23, 0.85);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(8px);
        }

        .modal-body {
            background: white;
            padding: var(--spacing-2xl);
            border-radius: 32px;
            max-width: 480px;
            width: 100%;
        }

        .img-wrap {
            width: 100%;
            aspect-ratio: 1;
            margin-bottom: var(--spacing-xl);
            border-radius: 20px;
            overflow: hidden;
            background: var(--border-light);
        }

        .img-wrap img {
            width: 100%;
            height: 100%;
            display: block;
        }

        .modal-footer {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .alert {
            padding: 1.25rem 2rem;
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-xl);
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            line-height: 1.5;
            border: 1px solid transparent;
        }

        .alert-icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        .alert.success {
            background: #f0fdf4;
            color: #166534;
            border-color: #dcfce7;
        }

        .alert.error {
            background: #fef2f2;
            color: #991b1b;
            border-color: #fee2e2;
        }

        #hidden-pic-form {
            display: none;
        }

        #upload-input {
            display: none;
        }

        /* Prevent horizontal scrolling */
        body,
        html,
        .layout-wrapper,
        .sidebar-spacer,
        .wrapper,
        .main-content {
            max-width: 100vw;
            overflow-x: hidden;
        }

        /* Accessibility */
        .btn-save:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        .tms-field:focus-visible {
            outline: none;
        }

        /* Tablet Styles */
        @media (max-width: 992px) {
            .sidebar-spacer {
                display: none;
            }

            .layout-wrapper {
                flex-direction: column;
            }

            .profile-layout {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .full {
                grid-column: span 1;
            }

            .main-content {
                padding: var(--spacing-lg);
            }

            .page-banner {
                padding: var(--spacing-lg);
            }

            .card {
                padding: var(--spacing-xl);
            }
        }

        /* Large Mobile */
        @media (max-width: 768px) {
            .layout-wrapper {
                flex-direction: column;
                height: auto;
                min-height: 100vh;
            }

            .wrapper {
                width: 100%;
            }

            .main-content {
                padding: var(--spacing-lg) var(--spacing-md);
                min-height: calc(100vh - 60px);
            }

            .container {
                width: 100%;
            }

            .page-banner {
                padding: var(--spacing-lg);
                margin-bottom: var(--spacing-lg);
            }

            .banner-text h1 {
                font-size: 1.25rem;
            }

            .profile-layout {
                gap: var(--spacing-lg);
            }

            .card {
                padding: var(--spacing-lg);
            }

            .avatar-container {
                width: 140px;
                height: 140px;
                margin: 0 auto var(--spacing-lg);
            }

            .img-profile,
            .avatar-placeholder {
                border-width: 5px;
                border-radius: 36px;
            }

            .edit-trigger {
                width: 44px;
                height: 44px;
            }

            .form-row {
                gap: var(--spacing-md);
                margin-bottom: var(--spacing-md);
            }

            .modal-body {
                padding: var(--spacing-xl);
            }

            .btn-save {
                padding: 14px;
                font-size: 11px;
                min-height: 40px;
            }

            .alert {
                padding: 1rem 1.5rem;
                font-size: 13px;
            }
        }

        /* Mobile Styles */
        @media (max-width: 480px) {
            .main-content {
                padding: var(--spacing-md);
                min-height: 100vh;
            }

            .page-banner {
                padding: var(--spacing-lg) var(--spacing-md);
                margin-bottom: var(--spacing-lg);
            }

            .banner-text h1 {
                font-size: 1.1rem;
            }

            .banner-text p {
                font-size: 10px;
            }

            .card {
                padding: var(--spacing-lg);
            }

            .avatar-container {
                width: 120px;
                height: 120px;
                margin: 0 auto var(--spacing-lg);
            }

            .avatar-icon {
                font-size: 52px;
            }

            .img-profile,
            .avatar-placeholder {
                border-width: 4px;
                border-radius: 28px;
            }

            .edit-trigger {
                width: 40px;
                height: 40px;
                border-width: 3px;
            }

            .edit-icon {
                font-size: 20px;
            }

            .info-item {
                padding: 12px 0;
                font-size: 12px;
            }

            .info-label {
                font-size: 9px;
            }

            .info-value {
                font-size: 12px;
            }

            .badge {
                font-size: 9px;
                padding: 3px 8px;
            }

            .section-header {
                margin-bottom: var(--spacing-lg);
                gap: 8px;
            }

            .section-header h2 {
                font-size: 13px;
            }

            .form-row {
                gap: var(--spacing-sm);
                margin-bottom: var(--spacing-sm);
            }

            .input-box {
                gap: 6px;
            }

            label {
                font-size: 9px;
            }

            .tms-field {
                padding: 12px 14px;
                font-size: 13px;
            }

            .divider {
                margin: var(--spacing-xl) 0;
            }

            .btn-save {
                padding: 12px;
                font-size: 10px;
                min-height: 36px;
                gap: 6px;
            }

            .save-icon {
                font-size: 16px;
            }

            .alert {
                padding: 0.9rem 1.2rem;
                font-size: 12px;
                margin-bottom: var(--spacing-lg);
            }

            .alert-icon {
                font-size: 18px;
            }

            .modal-body {
                padding: var(--spacing-lg);
                border-radius: 24px;
            }

            .img-wrap {
                margin-bottom: var(--spacing-lg);
                border-radius: 16px;
            }

            .modal-footer {
                gap: 0.75rem;
            }
        }

        /* Extra Small Mobile */
        @media (max-width: 360px) {
            .main-content {
                padding: var(--spacing-sm);
            }

            .page-banner {
                padding: var(--spacing-lg) var(--spacing-sm);
            }

            .banner-text h1 {
                font-size: 1rem;
            }

            .card {
                padding: var(--spacing-lg) var(--spacing-md);
            }

            .avatar-container {
                width: 100px;
                height: 100px;
            }

            .avatar-icon {
                font-size: 44px;
            }

            .img-profile,
            .avatar-placeholder {
                border-radius: 24px;
            }

            .tms-field {
                font-size: 12px;
                padding: 11px 12px;
            }

            .section-header h2 {
                font-size: 12px;
            }
        }
    </style>
</head>

<body>
    <div class="layout-wrapper">
        <div class="sidebar-spacer"><?php include '../includes/admin_sidebar.php'; ?></div>

        <div class="wrapper">
            <?php include '../includes/admin_header.php'; ?>

            <main class="main-content">
                <div class="container">

                    <header class="page-banner">
                        <div class="banner-content">
                            <div class="banner-icon">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="banner-text">
                                <h1>Registry Identity</h1>
                                <p>Administrator Security & Core Profile</p>
                            </div>
                        </div>
                    </header>

                    <?php if ($msg): ?>
                        <div class="alert <?= htmlspecialchars($msg_type) ?>">
                            <span class="material-symbols-outlined alert-icon"><?= $msg_type == 'success' ? 'check_circle' : 'error' ?></span>
                            <?= htmlspecialchars($msg) ?>
                        </div>
                    <?php endif; ?>

                    <div class="profile-layout">
                        <aside class="card avatar-section">
                            <div class="avatar-container">
                                <?php if (!empty($user['profile_pic'])): ?>
                                    <img src="../uploads/profiles/<?= htmlspecialchars($user['profile_pic']) ?>" id="current-avatar" class="img-profile" alt="Profile Picture">
                                <?php else: ?>
                                    <div class="avatar-placeholder">
                                        <span class="material-symbols-outlined avatar-icon">account_circle</span>
                                    </div>
                                <?php endif; ?>

                                <label for="upload-input" class="edit-trigger" title="Change Profile Picture">
                                    <i class="fas fa-camera"></i>
                                </label>
                                <input type="file" id="upload-input" accept="image/*">
                            </div>

                            <ul class="info-list">
                                <li class="info-item">
                                    <span class="info-label">Identity</span>
                                    <span class="info-value"><?= htmlspecialchars($user['username']) ?></span>
                                </li>
                                <li class="info-item">
                                    <span class="info-label">Privilege</span>
                                    <span class="badge">SYSTEM ADMIN</span>
                                </li>
                                <li class="info-item">
                                    <span class="info-label">Status</span>
                                    <span class="info-value info-value-success">Active</span>
                                </li>
                            </ul>
                        </aside>

                        <section class="card">
                            <div class="section-header">
                                <i class="fas fa-lock-open"></i>
                                <h2>Security Credentials</h2>
                            </div>

                            <form method="POST">
                                <div class="form-row">
                                    <div class="input-box full">
                                        <label>Primary Work Email</label>
                                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="tms-field" required>
                                    </div>
                                    <div class="input-box">
                                        <label>New Password</label>
                                        <input type="password" name="new_password" class="tms-field" placeholder="••••••••">
                                    </div>
                                    <div class="input-box">
                                        <label>Confirm Password</label>
                                        <input type="password" name="confirm_password" class="tms-field" placeholder="••••••••">
                                    </div>
                                </div>

                                <div class="divider"></div>

                                <div class="input-box full" style="margin-bottom: var(--spacing-xl);">
                                    <label class="danger-label">Verification Required</label>
                                    <input type="password" name="current_password" class="tms-field danger-field" placeholder="Confirm current password to save changes" required>
                                </div>

                                <button type="submit" name="update_details" class="btn-save">
                                    <i class="fas fa-sync-alt save-icon"></i>
                                    Synchronize Identity
                                </button>
                            </form>
                        </section>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div id="cropModal">
        <div class="modal-body">
            <div class="section-header">
                <i class="fas fa-crop"></i>
                <h2>Adjust Identity Portrait</h2>
            </div>
            <div class="img-wrap">
                <img id="image-to-crop" alt="Image to crop">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-save btn-alt" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn-save" id="confirm-crop">Apply Crop</button>
            </div>
        </div>
    </div>

    <form id="hidden-pic-form" method="POST">
        <input type="hidden" name="cropped_image" id="cropped_image_input">
    </form>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script>
        let cropper;
        const uploadInput = document.getElementById('upload-input');
        const cropImg = document.getElementById('image-to-crop');
        const cropModal = document.getElementById('cropModal');

        uploadInput.onchange = function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    cropImg.src = event.target.result;
                    cropModal.style.display = 'flex';
                    if (cropper) cropper.destroy();
                    cropper = new Cropper(cropImg, {
                        aspectRatio: 1,
                        viewMode: 1,
                        autoCropArea: 1,
                        background: false
                    });
                };
                reader.readAsDataURL(file);
            }
        };

        function closeModal() {
            cropModal.style.display = 'none';
            uploadInput.value = '';
        }

        document.getElementById('confirm-crop').onclick = function() {
            const canvas = cropper.getCroppedCanvas({
                width: 500,
                height: 500
            });
            document.getElementById('cropped_image_input').value = canvas.toDataURL('image/png');
            document.getElementById('hidden-pic-form').submit();
        };
    </script>
</body>

</html>