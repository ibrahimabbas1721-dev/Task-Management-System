<?php
include '../config/db.php';

$login_message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = isset($_POST['identifier']) ? trim($_POST['identifier']) : '';
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $login_message = 'Please enter your details.';
        $message_type = 'error';
    } else {
        try {
            // Attempt to find user by email or username
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? OR username = ?');
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] === 'admin') {
                    header('Location: ../admin/dashboard.php');
                } else {
                    header('Location: ../user/dashboard.php');
                }
                exit;
            } else {
                $login_message = 'Invalid credentials or password.';
                $message_type = 'error';
            }
        } catch (PDOException $e) {
            $login_message = 'Database error. Please try again.';
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
    <title>Log In | TaskFlow</title>
    
    <link rel="stylesheet" href="../fontawesome/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    
    <style>
        /* Variables */
        :root {
            --primary: #6366f1;
            --accent: #a855f7;
            --slate-900: #0f172a;
            --slate-700: #334155;
            --slate-500: #64748b;
            --slate-200: #e2e8f0;
            --error-bg: #fef2f2;
            --error-text: #ef4444;
        }

        /* Reset & Base */
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

        .main-container { width: 100%; max-width: 440px; }

        .glass-card {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 32px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
        }

        header { text-align: center; margin-bottom: 32px; }
        
        .logo-box { 
            width: 48px; 
            height: 48px; 
            background: linear-gradient(135deg, var(--primary), var(--accent)); 
            border-radius: 12px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: white; 
            margin: 0 auto 16px;
        }

        /* Font Awesome icon size adjustment */
        .logo-icon { font-size: 22px; }

        h1 { font-size: 26px; font-weight: 800; letter-spacing: -1px; margin-bottom: 8px; }
        .subtitle { color: var(--slate-500); font-weight: 500; font-size: 15px; }

        .form-group { width: 100%; margin-bottom: 20px; }
        .label-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; padding: 0 4px; }
        
        label { 
            font-size: 13px; 
            font-weight: 700; 
            color: var(--slate-700); 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
        }

        .forgot-link {
            font-size: 11px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
        }

        input { 
            width: 100%; 
            height: 54px; 
            border-radius: 16px; 
            border: 1px solid var(--slate-200); 
            background: rgba(255, 255, 255, 0.8); 
            padding: 0 20px; 
            font-family: inherit; 
            font-size: 15px; 
            transition: all 0.2s ease;
        }

        input:focus {
            outline: none;
            background: white;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .alert { 
            padding: 14px; 
            border-radius: 14px; 
            text-align: center; 
            font-size: 14px; 
            font-weight: 700; 
            margin-bottom: 24px; 
            background: var(--error-bg); 
            color: var(--error-text);
            border: 1px solid #fee2e2;
        }

        .btn-login {
            width: 100%; 
            height: 56px; 
            background: var(--slate-900); 
            color: white; 
            border: none; 
            border-radius: 16px; 
            font-weight: 700; 
            font-size: 16px; 
            cursor: pointer; 
            transition: all 0.2s ease;
            margin-top: 10px;
        }

        .btn-login:hover { 
            transform: translateY(-1px); 
            background: #000; 
            box-shadow: 0 10px 20px rgba(0,0,0,0.1); 
        }

        .footer-link { margin-top: 24px; text-align: center; font-size: 14px; color: var(--slate-500); }
        .footer-link a { color: var(--primary); font-weight: 700; text-decoration: none; }

        @media (max-width: 480px) {
            .glass-card { padding: 30px 20px; }
            h1 { font-size: 22px; }
        }
    </style>
</head>
<body>

    <div class="main-container">
        <div class="glass-card">
            <header>
                <div class="logo-box">
                    <i class="fas fa-user-plus logo-icon"></i>
                </div>
                <h1>Welcome Back</h1>
                <p class="subtitle">Log in using your account details</p>
            </header>

            <?php if (isset($login_message) && $login_message): ?>
                <div class="alert"><?php echo $login_message; ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <div class="label-row">
                        <label>Full Name or Email</label>
                    </div>
                    <input type="text" name="identifier" required placeholder="John Doe or john@example.com" value="<?php echo htmlspecialchars($_POST['identifier'] ?? ''); ?>" />
                </div>

                <div class="form-group">
                    <div class="label-row">
                        <label>Password</label>
                        <a href="forgot_password.php" class="forgot-link">FORGOT?</a>
                    </div>
                    <input type="password" name="password" required placeholder="••••••••" />
                </div>

                <button type="submit" class="btn-login">Sign In</button>
            </form>

            <div class="footer-link">
                Don't have an account? <a href="signup.php">Join now</a>
            </div>
        </div>
    </div>

</body>
</html>