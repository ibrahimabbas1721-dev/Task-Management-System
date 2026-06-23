<?php
include '../config/db.php';
requireLogin();

$id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// 1. Fetch user info
$userQuery = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$userQuery->execute([$user_id]);
$user = $userQuery->fetch(PDO::FETCH_ASSOC);

// 2. Pending Count for Header
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status != 'complete'");
$countStmt->execute([$user_id]);
$userPendingCount = $countStmt->fetchColumn();

// 3. Fetch task details
$stmt = $pdo->prepare("
    SELECT t.*, p.project_name 
    FROM tasks t 
    LEFT JOIN projects p ON t.project_id = p.id 
    WHERE t.id = ? AND t.assigned_to = ?
");
$stmt->execute([$id, $user_id]);
$task = $stmt->fetch();

if (!$task) {
    echo "<div style='text-align:center; padding:60px; font-family: sans-serif;'>
            <h1 style='color:#ef4444;'>ACCESS_DENIED</h1>
            <p>Task not found or unauthorized access.</p>
            <br>
            <a href='my_tasks.php' style='color:#6366f1; font-weight:700;'>Return to Queue</a>
          </div>";
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status         = $_POST['status'];
    $note           = trim($_POST['note']);
    $attachmentPath = $task['attachment'];

    // Handle File Upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['attachment']['tmp_name'];
        $origName    = basename($_FILES['attachment']['name']);
        $fileSize    = $_FILES['attachment']['size'];
        $fileType    = mime_content_type($fileTmpPath);

        $allowedTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];

        if ($fileSize > 10 * 1024 * 1024) {
            $error = "File size exceeds 10MB limit.";
        } elseif (!in_array($fileType, $allowedTypes)) {
            $error = "Invalid file type. Allowed: PDF, DOCX, JPG, PNG, GIF, WEBP.";
        } else {
            $fileName  = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
            $uploadDir = '../uploads/attachments/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            if (move_uploaded_file($fileTmpPath, $uploadDir . $fileName)) {
                // Delete old attachment if exists
                if (!empty($task['attachment']) && file_exists($uploadDir . $task['attachment'])) {
                    unlink($uploadDir . $task['attachment']);
                }
                $attachmentPath = $fileName;
            } else {
                $error = "File transmission failed. Check server permissions.";
            }
        }
    }

    if (!$error) {
        $validStatuses = ['pending', 'in_progress', 'complete'];
        if (!in_array($status, $validStatuses)) {
            $error = "Invalid status protocol.";
        } elseif (!empty($note) && strlen($note) < 5) {
            $error = "Intelligence report must be at least 5 characters.";
        } else {
            try {
                $timestamp          = date('Y-m-d H:i');
                $updatedDescription = $task['description'];
                if (!empty($note)) {
                    $updatedDescription .= "\n\n-- Update [$timestamp] by {$user['username']} --\n" . $note;
                }

                $updateStmt = $pdo->prepare(
                    "UPDATE tasks SET status = ?, description = ?, attachment = ? WHERE id = ? AND assigned_to = ?"
                );
                $updateStmt->execute([$status, $updatedDescription, $attachmentPath, $id, $user_id]);

                redirectWithMessage('my_tasks.php', 'Protocol Updated Successfully');
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// Helper: file icon based on extension
function getFileIcon(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return match($ext) {
        'pdf'            => 'fa-file-pdf',
        'doc', 'docx'    => 'fa-file-word',
        'jpg', 'jpeg',
        'png', 'gif',
        'webp'           => 'fa-file-image',
        default          => 'fa-file-alt',
    };
}

$isImage = function(string $filename): bool {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Update Protocol #<?= $task['id']; ?> | TMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="../fontawesome/css/all.min.css">

    <style>
        :root {
            --bg-body:        #f8fafc;
            --sidebar-width:  260px;
            --primary:        #6366f1;
            --primary-light:  #eef2ff;
            --dark-panel:     #020617;
            --glass-white:    #ffffff;
            --border-color:   #f1f5f9;
            --text-main:      #1e293b;
            --text-muted:     #64748b;
            --error:          #ef4444;
            --success:        #22c55e;
            --warning:        #f59e0b;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { overflow-x: hidden; width: 100%; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
            width: 100%;
        }

        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-left: var(--sidebar-width);
            min-width: 0;
            transition: margin-left 0.3s ease;
            overflow-x: hidden;
            max-width: 100vw;
        }

        .container {
            padding: 20px;
            max-width: 1100px;
            width: 100%;
            margin: 0 auto;
            overflow-x: hidden;
        }

        /* ── Banner ── */
        .dark-banner {
            background: var(--dark-panel);
            border-radius: 24px;
            padding: 30px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            border-left: 6px solid var(--primary);
            gap: 15px;
        }
        .banner-content { flex: 1; min-width: 0; }
        .banner-content h1 { font-size: clamp(20px,4vw,28px); font-weight: 800; font-style: italic; }
        .banner-content p  { font-size: 11px; opacity: 0.7; margin-bottom: 8px; }

        .btn-return {
            background: rgba(255,255,255,0.1);
            padding: 10px 15px;
            border-radius: 12px;
            color: white;
            text-decoration: none;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: .2s;
            white-space: nowrap;
        }
        .btn-return:hover { background: rgba(255,255,255,0.2); }

        /* ── Alert messages ── */
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error   { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
        .alert-success { background:#dcfce7; color:#166534; border:1px solid #86efac; }

        /* ── Cards ── */
        .card {
            background: var(--glass-white);
            border-radius: 24px;
            border: 1px solid var(--border-color);
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,.02);
            margin-bottom: 20px;
        }

        .section-label {
            font-size: 10px;
            font-weight: 900;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            display: block;
        }

        /* ── Info boxes ── */
        .info-box {
            background: #fafafa;
            border: 1px solid #f1f5f9;
            padding: 15px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 12px;
            word-break: break-word;
        }
        .info-box-label {
            font-size: 10px;
            font-weight: 700;
            display: block;
            margin-bottom: 8px;
        }
        .info-box-label-primary { color: var(--primary); }
        .info-box-label-muted   { color: var(--text-muted); }

        /* ── Current Attachment Box ── */
        .attachment-current {
            background: var(--primary-light);
            border: 1.5px solid #c7d2fe;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
        }
        .attachment-current-label {
            font-size: 10px;
            font-weight: 900;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 1px;
            display: block;
            margin-bottom: 10px;
        }
        .attachment-preview-img {
            width: 100%;
            max-height: 180px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 10px;
            border: 1px solid #c7d2fe;
        }
        .attachment-file-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .attachment-file-icon {
            width: 40px;
            height: 40px;
            background: var(--primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            flex-shrink: 0;
        }
        .attachment-file-info { flex: 1; min-width: 0; }
        .attachment-file-name {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-main);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .attachment-file-size {
            font-size: 10px;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .attachment-open-btn {
            background: var(--primary);
            color: white;
            padding: 7px 12px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 800;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: .2s;
            flex-shrink: 0;
        }
        .attachment-open-btn:hover { background: #4f46e5; }

        /* ── Status Grid ── */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px,1fr));
            gap: 10px;
            margin-bottom: 25px;
        }
        .status-option input { display: none; }
        .status-ui {
            background: #fff;
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 15px 5px;
            text-align: center;
            cursor: pointer;
            transition: .2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        .status-ui:hover { border-color: var(--primary); }
        .status-option input:checked + .status-ui {
            background: var(--dark-panel);
            border-color: var(--primary);
            color: white;
        }
        .status-icon { color: var(--text-muted); font-size: 18px; transition: color .2s; }
        .status-option input:checked + .status-ui .status-icon { color: white; }
        .status-label { font-size: 9px; font-weight: 800; }

        /* ── Textarea ── */
        .report-area {
            width: 100%;
            background: #fafafa;
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 15px;
            min-height: 120px;
            outline: none;
            margin-bottom: 20px;
            font-family: inherit;
            resize: vertical;
            font-size: 14px;
        }
        .report-area:focus { border-color: var(--primary); }

        /* ── File Upload Zone ── */
        .file-upload-wrapper {
            position: relative;
            border: 2px dashed var(--border-color);
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            background: #fafafa;
            transition: .2s;
            cursor: pointer;
        }
        .file-upload-wrapper:hover,
        .file-upload-wrapper.dragover { border-color: var(--primary); background: var(--primary-light); }
        .upload-icon  { color: var(--primary); font-size: 24px; display: block; margin-bottom: 10px; }
        .upload-text  { font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 4px; }
        .upload-hint  { font-size: 10px; color: var(--text-muted); }
        .file-upload-wrapper input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        /* selected file preview inside upload zone */
        .selected-preview {
            display: none;
            margin-top: 12px;
            background: white;
            border: 1px solid #c7d2fe;
            border-radius: 10px;
            padding: 10px 12px;
            align-items: center;
            gap: 10px;
            text-align: left;
        }
        .selected-preview.show { display: flex; }
        .selected-preview-icon {
            width: 34px;
            height: 34px;
            background: var(--primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            flex-shrink: 0;
        }
        .selected-preview-name {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-main);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
            min-width: 0;
        }
        .selected-preview-size { font-size: 10px; color: var(--text-muted); }

        /* ── Submit Button ── */
        .btn-submit {
            background: var(--dark-panel);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 14px;
            font-weight: 900;
            text-transform: uppercase;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 13px;
            transition: .2s;
            font-family: inherit;
            letter-spacing: .5px;
        }
        .btn-submit:hover { background: var(--primary); }

        /* ── Scrollable description ── */
        .description-box {
            font-size: 11px;
            max-height: 250px;
            overflow-y: auto;
            line-height: 1.7;
        }
        .description-box::-webkit-scrollbar { width: 4px; }
        .description-box::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .description-box::-webkit-scrollbar-thumb { background: #c7d2fe; border-radius: 10px; }

        /* ── Status Badge ── */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .badge-pending     { background: #fef9c3; color: #854d0e; }
        .badge-in_progress { background: #dbeafe; color: #1e40af; }
        .badge-complete    { background: #dcfce7; color: #166534; }

        /* ── Responsive ── */
        @media (min-width: 992px) {
            .content-grid { display: grid; grid-template-columns: 320px 1fr; gap: 20px; }
        }
        @media (max-width: 1024px) { .main-wrapper { margin-left: 0; } }
        @media (min-width: 769px)  { .container { padding: 40px; } }
    </style>
</head>
<body>

<?php include '../includes/user_sidebar.php'; ?>

<div class="main-wrapper">
    <?php include '../includes/user_header.php'; ?>

    <main class="container">

        <!-- Banner -->
        <header class="dark-banner">
            <div class="banner-content">
                <p>>_ Status_Update_Required · Task #<?= $task['id']; ?></p>
                <h1>Commit Progress</h1>
            </div>
            <a href="my_tasks.php" class="btn-return">
                <i class="fas fa-arrow-left"></i> Return
            </a>
        </header>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <div class="content-grid">

            <!-- ─── Left: Task Intelligence Panel ─── -->
            <div class="card">
                <span class="section-label">Task Intelligence</span>

                <div class="info-box">
                    <strong class="info-box-label info-box-label-primary">PROJECT</strong>
                    <?= htmlspecialchars($task['project_name'] ?? 'N/A'); ?>
                </div>

                <div class="info-box">
                    <strong class="info-box-label info-box-label-muted">TITLE</strong>
                    <?= htmlspecialchars($task['title']); ?>
                </div>

                <div class="info-box">
                    <strong class="info-box-label info-box-label-muted">CURRENT STATUS</strong>
                    <?php
                    $badgeClass = 'badge-' . $task['status'];
                    $icons = ['pending'=>'fa-clock','in_progress'=>'fa-bolt','complete'=>'fa-check-circle'];
                    $labels = ['pending'=>'Pending','in_progress'=>'In Progress','complete'=>'Complete'];
                    $icon  = $icons[$task['status']]  ?? 'fa-circle';
                    $label = $labels[$task['status']] ?? $task['status'];
                    ?>
                    <span class="status-badge <?= $badgeClass ?>">
                        <i class="fas <?= $icon ?>"></i> <?= $label ?>
                    </span>
                </div>

                <!-- ── Current Attachment ── -->
                <?php if (!empty($task['attachment'])): ?>
                    <div class="attachment-current">
                        <strong class="attachment-current-label">
                            <i class="fas fa-paperclip"></i> &nbsp;Stored Attachment
                        </strong>

                        <?php if ($isImage($task['attachment'])): ?>
                            <img
                                src="../uploads/attachments/<?= htmlspecialchars($task['attachment']); ?>"
                                alt="Attachment Preview"
                                class="attachment-preview-img"
                            >
                        <?php endif; ?>

                        <div class="attachment-file-row">
                            <div class="attachment-file-icon">
                                <i class="fas <?= getFileIcon($task['attachment']); ?>"></i>
                            </div>
                            <div class="attachment-file-info">
                                <div class="attachment-file-name">
                                    <?= htmlspecialchars($task['attachment']); ?>
                                </div>
                                <?php
                                $filePath = '../uploads/attachments/' . $task['attachment'];
                                if (file_exists($filePath)) {
                                    $bytes = filesize($filePath);
                                    $size  = $bytes < 1024*1024
                                        ? round($bytes/1024, 1) . ' KB'
                                        : round($bytes/(1024*1024), 1) . ' MB';
                                    echo "<div class='attachment-file-size'>{$size}</div>";
                                }
                                ?>
                            </div>
                                <a href="uploads/<?= htmlspecialchars($attachment) ?>" target="_blank">                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="info-box" style="text-align:center; color:var(--text-muted); font-size:12px;">
                        <i class="fas fa-paperclip" style="opacity:.4; font-size:20px; display:block; margin-bottom:6px;"></i>
                        No attachment uploaded yet
                    </div>
                <?php endif; ?>

                <!-- History -->
                <div class="info-box">
                    <strong class="info-box-label info-box-label-muted">HISTORY LOG</strong>
                    <div class="description-box">
                        <?= nl2br(htmlspecialchars($task['description'])); ?>
                    </div>
                </div>
            </div>

            <!-- ─── Right: Update Form Panel ─── -->
            <div class="card">
                <form method="POST" enctype="multipart/form-data">

                    <!-- 1. Status -->
                    <span class="section-label">1. Protocol Status</span>
                    <div class="status-grid">
                        <label class="status-option">
                            <input type="radio" name="status" value="pending"
                                <?= $task['status'] == 'pending' ? 'checked' : ''; ?>>
                            <div class="status-ui">
                                <i class="fas fa-clock status-icon"></i>
                                <div class="status-label">PENDING</div>
                            </div>
                        </label>
                        <label class="status-option">
                            <input type="radio" name="status" value="in_progress"
                                <?= $task['status'] == 'in_progress' ? 'checked' : ''; ?>>
                            <div class="status-ui">
                                <i class="fas fa-bolt status-icon"></i>
                                <div class="status-label">ACTIVE</div>
                            </div>
                        </label>
                        <label class="status-option">
                            <input type="radio" name="status" value="complete"
                                <?= $task['status'] == 'complete' ? 'checked' : ''; ?>>
                            <div class="status-ui">
                                <i class="fas fa-check-circle status-icon"></i>
                                <div class="status-label">FINALIZED</div>
                            </div>
                        </label>
                    </div>

                    <!-- 2. Note -->
                    <span class="section-label">2. Intelligence Report</span>
                    <textarea
                        name="note"
                        class="report-area"
                        placeholder="Write operational update here... (optional, min 5 chars if provided)"
                    ></textarea>

                    <!-- 3. Attachment -->
                    <span class="section-label">
                        3. Deliverable Attachment
                        <?php if (!empty($task['attachment'])): ?>
                            <span style="color:var(--warning); font-size:9px; font-weight:700; margin-left:6px;">
                                (upload new to replace existing)
                            </span>
                        <?php endif; ?>
                    </span>

                    <div class="file-upload-wrapper" id="dropZone">
                        <i class="fas fa-cloud-upload-alt upload-icon"></i>
                        <div class="upload-text" id="upload-text">Drop deliverable here or click to browse</div>
                        <div class="upload-hint">PDF, DOCX, JPG, PNG, GIF, WEBP &mdash; Max 10MB</div>
                        <input
                            type="file"
                            name="attachment"
                            id="file-input"
                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp"
                        >
                        <!-- Selected file mini-preview -->
                        <div class="selected-preview" id="selectedPreview">
                            <div class="selected-preview-icon" id="selectedIcon">
                                <i class="fas fa-file"></i>
                            </div>
                            <div style="flex:1; min-width:0;">
                                <div class="selected-preview-name" id="selectedName"></div>
                                <div class="selected-preview-size" id="selectedSize"></div>
                            </div>
                            <i class="fas fa-check-circle" style="color:var(--success); font-size:16px;"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i>
                        Commit Changes
                    </button>
                </form>
            </div>

        </div><!-- /.content-grid -->
    </main>
</div>

<script>
    const fileInput  = document.getElementById('file-input');
    const dropZone   = document.getElementById('dropZone');
    const uploadText = document.getElementById('upload-text');
    const preview    = document.getElementById('selectedPreview');
    const selName    = document.getElementById('selectedName');
    const selSize    = document.getElementById('selectedSize');
    const selIcon    = document.getElementById('selectedIcon');

    const extIconMap = {
        pdf:  'fa-file-pdf',
        doc:  'fa-file-word', docx: 'fa-file-word',
        jpg:  'fa-file-image', jpeg:'fa-file-image',
        png:  'fa-file-image', gif: 'fa-file-image', webp:'fa-file-image',
    };

    function formatBytes(bytes) {
        if (bytes < 1024)       return bytes + ' B';
        if (bytes < 1024*1024)  return (bytes/1024).toFixed(1) + ' KB';
        return (bytes/(1024*1024)).toFixed(1) + ' MB';
    }

    function getExtIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        return extIconMap[ext] || 'fa-file-alt';
    }

    function showFilePreview(file) {
        selName.textContent   = file.name;
        selSize.textContent   = formatBytes(file.size);
        selIcon.innerHTML     = `<i class="fas ${getExtIcon(file.name)}"></i>`;
        preview.classList.add('show');
        uploadText.textContent = 'File selected — ready to upload';
        uploadText.style.color = '#6366f1';
    }

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) showFilePreview(fileInput.files[0]);
    });

    // Drag-and-drop
    dropZone.addEventListener('dragover', e => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length) {
            fileInput.files = files;          // assign dropped files
            showFilePreview(files[0]);
        }
    });
</script>
</body>
</html>