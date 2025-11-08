<?php
require_once __DIR__ . '/inc/functions.php';

// If already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    redirect('dashboard.php');
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Invalid request');
        redirect('index.php');
    }

    $result = $auth->login(
        $_POST['username'] ?? '',
        $_POST['password'] ?? ''
    );

    if ($result['success']) {
        redirect('dashboard.php');
    }
    $error = $result['message'];
}

// Get any flash messages
$flash = getFlash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Saving Ant — Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root{
            --primary: #0B5FFF;
            --dark: #06326B;
            --bg: #EAF3FF;
            --card-radius: 12px;
            --card-max-w: 400px;
            --error: #b00020;
            --success: #0d8050;
        }
        html,body{
            height:100%;
            margin:0;
            font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial;
            background: var(--bg);
            color: #0b2240;
        }
        .wrap{
            min-height:100%;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            padding:32px 16px;
            box-sizing:border-box;
        }
        .brand{
            text-align:center;
            margin-bottom:18px;
        }
        .brand .logo{
            font-weight:600;
            color:var(--dark);
            font-size:28px;
            letter-spacing:0.2px;
        }
        .brand .tag{
            margin-top:6px;
            color:rgba(6,50,107,0.85);
            font-size:14px;
        }
        .card{
            width:100%;
            max-width:var(--card-max-w);
            background:#fff;
            border-radius:var(--card-radius);
            box-shadow: 0 8px 20px rgba(11,95,255,0.08), 0 2px 6px rgba(6,50,107,0.05);
            padding:28px;
            box-sizing:border-box;
        }
        .form-row{margin-bottom:14px}
        label{display:block;font-size:13px;color:#18314d;margin-bottom:6px}
        input[type="text"], input[type="password"]{
            width:100%;
            padding:10px 12px;
            border-radius:8px;
            border:1px solid #e6eefb;
            background:#fbfdff;
            font-size:14px;
            box-sizing:border-box;
            outline:none;
            transition:box-shadow .12s, border-color .12s;
        }
        input[type="text"]:focus, input[type="password"]:focus{
            border-color: var(--primary);
            box-shadow: 0 6px 18px rgba(11,95,255,0.07);
        }
        .btn{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            width:100%;
            padding:10px 14px;
            background:var(--primary);
            color:#fff;
            border:none;
            border-radius:10px;
            font-weight:600;
            cursor:pointer;
            transition:background .12s, transform .06s;
            font-size:15px;
        }
        .btn:active{transform:translateY(1px)}
        .btn:hover{background:#084fe6}
        .muted{font-size:13px;color:#6b7a93;text-align:center;margin-top:12px}
        .small-link{color:var(--dark);text-decoration:none;font-weight:600}
        footer{margin-top:18px;font-size:12px;color:#6b7a93}
        .spinner{
            width:16px;height:16px;
            border:2px solid rgba(255,255,255,0.35);
            border-top-color:rgba(255,255,255,0.95);
            border-radius:50%;
            animation:spin .8s linear infinite;
            display:inline-block;
        }
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .alert-error {
            background: rgba(176,0,32,0.1);
            color: var(--error);
        }
        .alert-success {
            background: rgba(13,128,80,0.1);
            color: var(--success);
        }
        @keyframes spin{to{transform:rotate(360deg)}}
        @media (max-width:420px){
            .card{padding:20px}
            .brand .logo{font-size:22px}
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand">
            <div class="logo">Saving Ant</div>
            <div class="tag">Save Smart. Track Easily.</div>
        </div>

        <div class="card" role="main" aria-labelledby="login-heading">
            <h2 id="login-heading" style="margin:0 0 12px 0;color:var(--dark);font-size:18px">Welcome back</h2>

            <?php if (isset($flash)): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form id="loginForm" action="index.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                
                <div class="form-row">
                    <label for="username">Username or Email</label>
                    <input id="username" name="username" type="text" 
                           placeholder="your@email.com or username" required 
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" 
                           placeholder="••••••••" required>
                </div>
                <div class="form-row">
                    <button type="submit" class="btn" id="loginBtn">
                        <span id="btnText">Login</span>
                        <span id="btnSpinner" style="display:none;margin-left:4px">
                            <span class="spinner" aria-hidden="true"></span>
                        </span>
                    </button>
                </div>
            </form>

            <div class="muted">Don't have an account? <a class="small-link" href="register.php">Register here.</a></div>
        </div>

        <footer>© 2025 Saving Ant — All rights reserved.</footer>
    </div>

    <script>
        // Simple UI behavior: show spinner when submitting
        (function(){
            const form = document.getElementById('loginForm');
            const btn = document.getElementById('loginBtn');
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');

            form.addEventListener('submit', function(){
                btn.disabled = true;
                btn.style.opacity = '0.95';
                btnText.textContent = 'Logging in...';
                btnSpinner.style.display = 'inline-block';
            });
        })();
    </script>
</body>
</html>
