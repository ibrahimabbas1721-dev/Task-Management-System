<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>TaskFlow | Enterprise Project Intelligence</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />

    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-glow: rgba(99, 102, 241, 0.2);
            --accent: #f59e0b;
            --success: #10b981;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --glass-bg: rgba(255, 255, 255, 0.8);
            --glass-border: rgba(226, 232, 240, 0.8);
            --footer-bg: #0f172a;
            --footer-input: #1e293b;
        }

        /* --- Global Reset & Prevent X-Axis Scroll --- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            overflow-x: hidden;
            position: relative;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-main);
            background-color: #fbfcfd;
            background-image:
                radial-gradient(circle at 15% 15%, rgba(99, 102, 241, 0.05) 0%, transparent 25%),
                radial-gradient(circle at 85% 85%, rgba(168, 85, 247, 0.05) 0%, transparent 25%);
            background-attachment: fixed;
            line-height: 1.5;
        }

        .container {
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 24px;
            width: 100%;
        }

        /* --- Navigation --- */
        nav {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            padding: 24px 0;
        }

        .nav-inner {
            padding: 12px 24px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            font-size: 22px;
            letter-spacing: -0.5px;
        }

        .logo-box {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary), #818cf8);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .logo-box .material-symbols-outlined {
            font-size: 20px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 32px;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-main);
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .btn-login {
            color: var(--text-muted) !important;
        }

        .btn-signup-nav {
            background: var(--text-main);
            color: white;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-signup-nav:hover {
            transform: translateY(-1px);
            background: #000;
        }

        /* --- Hamburger Menu Logic --- */
        #menu-toggle {
            display: none;
        }

        .hamburger {
            display: none;
            cursor: pointer;
            color: var(--text-main);
            padding: 4px;
        }

        /* --- Hero --- */
        .hero {
            padding: 180px 0 100px;
            text-align: center;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 100px;
            background: #fff;
            border: 1px solid #eef2f6;
            font-size: 12px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 32px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        .hero-badge .material-symbols-outlined {
            font-size: 16px;
        }

        .hero h1 {
            font-size: clamp(2.5rem, 8vw, 4.5rem);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.05em;
            margin-bottom: 24px;
        }

        .hero h1 span {
            color: #6366f1;
            background-image: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }

        .hero p {
            font-size: 20px;
            color: var(--text-muted);
            max-width: 700px;
            margin: 0 auto 48px;
            font-weight: 400;
        }

        /* --- Features --- */
        .section-tag {
            text-align: center;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: 12px;
            margin-bottom: 12px;
            display: block;
        }

        .section-title {
            text-align: center;
            font-size: clamp(28px, 5vw, 36px);
            font-weight: 800;
            margin-bottom: 60px;
            letter-spacing: -1px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 32px;
            margin-bottom: 120px;
        }

        .feature-card {
            background: white;
            border: 1px solid #f1f5f9;
            border-radius: 28px;
            padding: 40px;
            position: relative;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            border-color: var(--primary);
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.04);
        }

        .feature-card h3 {
            font-size: 20px;
            margin-bottom: 16px;
            font-weight: 700;
        }

        .feature-card p {
            color: var(--text-muted);
            font-size: 15px;
            line-height: 1.6;
        }

        .icon-wrapper {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
        }

        /* Feature Icon Specific Colors */
        .icon-admin-crud { background: #e0e7ff; color: #4338ca; }
        .icon-tracking { background: #ecfdf5; color: #059669; }
        .icon-ranking { background: #fff7ed; color: #d97706; }
        .icon-submissions { background: #fdf2f8; color: #db2777; }
        .icon-vault { background: #f1f5f9; color: #475569; }
        .icon-profiles { background: #eff6ff; color: #2563eb; }

        /* --- Footer --- */
        footer {
            background: var(--footer-bg);
            color: white;
            padding: 100px 0 50px;
            margin-top: 120px;
            border-radius: 40px 40px 0 0;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1.5fr;
            gap: 60px;
        }

        .footer-logo {
            color: white;
            margin-bottom: 24px;
        }

        .footer-desc {
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .footer-socials {
            display: flex;
            gap: 15px;
        }

        .footer-socials .material-symbols-outlined {
            color: #94a3b8;
        }

        .footer-col h4 {
            color: white;
            margin-bottom: 24px;
            font-size: 15px;
        }

        .footer-col a {
            color: #94a3b8;
            text-decoration: none;
            font-size: 14px;
            display: block;
            margin-bottom: 12px;
        }

        .footer-col a:hover {
            color: white;
        }

        .newsletter-input {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: none;
            background: var(--footer-input);
            color: white;
            margin-bottom: 12px;
        }

        .btn-subscribe {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: none;
            background: var(--primary);
            color: white;
            font-weight: 700;
            cursor: pointer;
        }

        .footer-bottom {
            border-top: 1px solid #1e293b;
            margin-top: 60px;
            padding-top: 30px;
            text-align: center;
            color: #64748b;
            font-size: 12px;
        }

        /* --- Utility Buttons --- */
        .btn-hero-primary {
            background: var(--primary);
            color: white;
            padding: 18px 36px;
            border-radius: 16px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            font-size: 16px;
            box-shadow: 0 10px 20px var(--primary-glow);
            transition: 0.3s;
        }

        .btn-hero-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* --- Responsive Queries --- */
        @media (max-width: 1024px) {
            .footer-grid {
                grid-template-columns: 1fr 1fr;
                gap: 40px;
            }
        }

        @media (max-width: 768px) {
            .hamburger {
                display: block;
            }

            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                flex-direction: column;
                padding: 24px;
                gap: 16px;
                border-radius: 0 0 20px 20px;
                border: 1px solid var(--glass-border);
                box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            }

            #menu-toggle:checked ~ .nav-links {
                display: flex;
            }

            .hero {
                padding: 140px 0 60px;
            }
            .features-grid {
                grid-template-columns: 1fr;
            }
            .footer-grid {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .footer-socials {
                justify-content: center;
            }
            .logo {
                justify-content: flex-start;
            }
        }
    </style>
</head>

<body>
    <nav>
        <div class="container">
            <div class="nav-inner">
                <div class="logo">
                    <div class="logo-box">
                        <span class="material-symbols-outlined">grid_view</span>
                    </div>
                    TaskFlow
                </div>

                <input type="checkbox" id="menu-toggle">
                <label for="menu-toggle" class="hamburger">
                    <span class="material-symbols-outlined">menu</span>
                </label>

                <div class="nav-links">
                    <a href="#admin-features">Admin Hub</a>
                    <a href="#user-experience">Team View</a>
                    <a href="./auth/login.php" class="btn-login">Log in</a>
                    <a href="./auth/signup.php"><button class="btn-signup-nav">Get Started</button></a>
                </div>
            </div>
        </div>
    </nav>

    <header class="hero container">
        <div class="hero-badge">
            <span class="material-symbols-outlined">verified</span>
            New: Project Ranking & Team Analytics
        </div>
        <h1>Command your projects with <span>absolute precision.</span></h1>
        <p>From high-level project CRUD to granular team member rankings. TaskFlow bridges the gap between management oversight and execution.</p>
        <div class="hero-actions">
            <a href="./auth/signup.php"><button class="btn-hero-primary">Launch Your Dashboard</button></a>
        </div>
    </header>

    <main class="container">
        <span class="section-tag" id="admin-features">Control Center</span>
        <h2 class="section-title">Powerful Admin Oversight</h2>

        <div class="features-grid">
            <div class="feature-card">
                <div class="icon-wrapper icon-admin-crud">
                    <span class="material-symbols-outlined">account_tree</span>
                </div>
                <h3>Project & Task CRUD</h3>
                <p>Full lifecycle management. Create complex projects, define tasks, and map them to the right team members instantly.</p>
            </div>

            <div class="feature-card">
                <div class="icon-wrapper icon-tracking">
                    <span class="material-symbols-outlined">insights</span>
                </div>
                <h3>Visual Progress Tracking</h3>
                <p>Monitor project health through dynamic graphical representations. Track completion rates and identify bottlenecks at a glance.</p>
            </div>

            <div class="feature-card">
                <div class="icon-wrapper icon-ranking">
                    <span class="material-symbols-outlined">stars</span>
                </div>
                <h3>Team Ranking System</h3>
                <p>Evaluate performance and <strong>rank team members</strong> based on efficiency, report quality, and deadline adherence.</p>
            </div>
        </div>

        <span class="section-tag" id="user-experience">Execution Hub</span>
        <h2 class="section-title">Designed for Team Focus</h2>

        <div class="features-grid">
            <div class="feature-card">
                <div class="icon-wrapper icon-submissions">
                    <span class="material-symbols-outlined">assignment_turned_in</span>
                </div>
                <h3>Streamlined Submissions</h3>
                <p>Users mark tasks as complete and provide detailed reports via integrated forms. No more messy email threads.</p>
            </div>

            <div class="feature-card">
                <div class="icon-wrapper icon-vault">
                    <span class="material-symbols-outlined">upload_file</span>
                </div>
                <h3>Media & PDF Vault</h3>
                <p>Full support for documentation. Upload project images, research PDFs, and relevant assets directly to the task.</p>
            </div>

            <div class="feature-card">
                <div class="icon-wrapper icon-profiles">
                    <span class="material-symbols-outlined">person_pin</span>
                </div>
                <h3>Performance Profiles</h3>
                <p>Every team member gets a dedicated profile page showcasing their progress, active tasks, and historical contributions.</p>
            </div>
        </div>
    </main>

    <footer>
        <div class="container footer-grid">
            <div class="footer-col">
                <div class="logo footer-logo">
                    <div class="logo-box"><span class="material-symbols-outlined">grid_view</span></div>
                    TaskFlow
                </div>
                <p class="footer-desc">
                    The next-gen project management tool for teams that value data-driven results and ranked performance.
                </p>
                <div class="footer-socials">
                    <span class="material-symbols-outlined">lan</span>
                    <span class="material-symbols-outlined">hub</span>
                </div>
            </div>

            <div class="footer-col">
                <h4>Admin Tools</h4>
                <a href="#">Project CRUD</a>
                <a href="#">User Management</a>
                <a href="#">Analytics Engine</a>
                <a href="#">Password Security</a>
            </div>

            <div class="footer-col">
                <h4>Team Access</h4>
                <a href="#">My Tasks</a>
                <a href="#">Submit Reports</a>
                <a href="#">File Manager</a>
                <a href="#">My Progress</a>
            </div>

            <div class="footer-col">
                <h4>Join the Newsletter</h4>
                <input type="text" placeholder="Work email..." class="newsletter-input">
                <button class="btn-subscribe">Subscribe</button>
            </div>
        </div>
        <div class="container footer-bottom">
            &copy; 2026 TaskFlow Inc. Admin Dashboard v4.0.1
        </div>
    </footer>
</body>

</html>