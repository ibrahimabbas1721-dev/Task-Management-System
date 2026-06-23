<?php
include '../config/db.php'; 

$signup_message = '';
$message_type = ''; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'admin';

    if (empty($user) || empty($email) || empty($password)) {
        $signup_message = 'All fields are required.';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $signup_message = 'Please enter a valid email address.';
        $message_type = 'error';
    } elseif (strlen($password) < 6) {
        $signup_message = 'Password must be at least 6 characters.';
        $message_type = 'error';
    } else {
        try {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            
            if ($check->fetch()) {
                $signup_message = 'This email is already registered.';
                $message_type = 'error';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                
                if ($stmt->execute([$user, $email, $hashed_password, $role])) {
                    $signup_message = 'Account created! You are registered as ' . ucfirst($role) . '.';
                    $message_type = 'success';
                }
            }
        } catch (PDOException $e) {
            $signup_message = 'Database Error. Please check your connection.';
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Sign Up | TaskFlow</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    
    <style>
        :root {
            --primary: #6366f1;
            --accent: #a855f7;
            --slate-900: #0f172a;
            --slate-700: #334155;
            --slate-500: #64748b;
            --slate-200: #e2e8f0;
            --error-bg: #fef2f2;
            --error-text: #ef4444;
            --success-bg: #ecfdf5;
            --success-text: #059669;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--slate-900);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background-color: #ffffff;
            background-image: 
                radial-gradient(at 0% 0%, hsla(253,16%,95%,1) 0, transparent 50%), 
                radial-gradient(at 50% 0%, hsla(225,39%,90%,1) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(339,49%,90%,1) 0, transparent 50%);
        }

        /* Container */
        .main-container { width: 100%; max-width: 440px; }

        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 32px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        h1 { font-size: 26px; font-weight: 800; letter-spacing: -1px; margin-bottom: 8px; text-align: center; }
        .subtitle { color: var(--slate-500); font-weight: 500; margin-bottom: 32px; text-align: center; font-size: 15px; }

        /* Form Layout - Set to Full Width */
        .form-group { width: 100%; margin-bottom: 20px; }

        label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: var(--slate-700);
            margin-bottom: 8px;
            margin-left: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input, select { 
            width: 100%; /* Force full width */
            height: 52px; 
            border-radius: 14px; 
            border: 1px solid var(--slate-200); 
            background: rgba(255, 255, 255, 0.8); 
            padding: 0 18px; 
            font-family: inherit; 
            font-size: 15px; 
            transition: all 0.2s ease;
        }

        select { appearance: none; cursor: pointer; }

        .select-wrapper { position: relative; width: 100%; }
        .select-wrapper::after {
            content: '\e5cf'; 
            font-family: 'Material Symbols Outlined';
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--slate-500);
            pointer-events: none;
            font-size: 20px;
        }

        input:focus, select:focus {
            outline: none;
            background: white;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        /* Alerts */
        .alert { padding: 14px; border-radius: 14px; text-align: center; font-size: 14px; font-weight: 600; margin-bottom: 24px; border: 1px solid transparent; }
        .alert-error { background: var(--error-bg); color: var(--error-text); border-color: #fee2e2; }
        .alert-success { background: var(--success-bg); color: var(--success-text); border-color: #d1fae5; }

        /* Button */
        .btn-submit {
            width: 100%;
            height: 54px;
            background: var(--slate-900);
            color: white;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.2s ease;
        }

        .btn-submit:hover { transform: translateY(-1px); background: #000; box-shadow: 0 10px 20px -5px rgba(0,0,0,0.15); }

        .footer-link { margin-top: 24px; text-align: center; font-size: 14px; color: var(--slate-500); }
        .footer-link a { color: var(--primary); font-weight: 700; text-decoration: none; }
        
        @media (max-width: 400px) { .glass-card { padding: 30px 20px; } }
    </style>
</head>
<body>

    <div class="main-container">
        <div class="glass-card">
            <h1>Create Account</h1>
            <p class="subtitle">Enter your details to join TaskFlow</p>

            <form method="POST" action="signup.php">
                <?php if ($signup_message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $signup_message; ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Username</label>
                    <input name="username" type="text" required placeholder="e.g. alexjohnson" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" />
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input name="email" type="email" required placeholder="alex@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" />
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input name="password" type="password" required placeholder="At least 6 characters" />
                </div>

                <button type="submit" class="btn-submit">Sign Up</button>
            </form>

            <div class="footer-link">
                Already have an account? <a href="login.php">Log In</a>
            </div>
        </div>
    </div>

</body>
</html>