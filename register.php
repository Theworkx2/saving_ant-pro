<?php
require_once __DIR__ . '/inc/functions.php';

// If already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    redirect('dashboard.php');
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Invalid request');
        redirect('register.php');
    }

    $result = $auth->register([
        'username' => $_POST['username'] ?? '',
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? '',
        'full_name' => $_POST['fullname'] ?? '',
        'role' => $_POST['role'] ?? 'user'
    ]);

    if ($result['success']) {
        setFlash('success', 'Registration successful! Please login.');
        redirect('index.php');
    }
    $errors = $result['errors'];
}

// Get any flash messages
$flash = getFlash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Saving Ant — Register</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root{
            --primary:#0B5FFF;
            --dark:#06326B;
            --bg:#EAF3FF;
            --card-radius:12px;
            --card-max-w:420px;
            --error:#b00020;
            --success:#0d8050;
        }
        html,body{height:100%;margin:0;font-family:'Poppins',system-ui,-apple-system,'Segoe UI',Roboto,Arial;background:var(--bg);color:#0b2240}
        .wrap{min-height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:28px}
        .brand{text-align:center;margin-bottom:16px}
        .brand .logo{font-weight:600;color:var(--dark);font-size:26px}
        .brand .tag{margin-top:6px;color:rgba(6,50,107,0.85);font-size:14px}
        .card{width:100%;max-width:var(--card-max-w);background:#fff;border-radius:var(--card-radius);box-shadow:0 8px 20px rgba(11,95,255,0.07),0 2px 6px rgba(6,50,107,0.04);padding:26px;box-sizing:border-box}
        .form-row{margin-bottom:12px}
        label{display:block;font-size:13px;color:#18314d;margin-bottom:6px}
        input{width:100%;padding:10px 12px;border-radius:8px;border:1px solid #e6eefb;background:#fbfdff;font-size:14px;outline:none}
        input:focus{border-color:var(--primary);box-shadow:0 6px 18px rgba(11,95,255,0.06)}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:10px 14px;background:var(--primary);color:#fff;border:none;border-radius:10px;font-weight:600;cursor:pointer;transition:background .12s}
        .btn:hover{background:#084fe6}
        .muted{font-size:13px;color:#6b7a93;text-align:center;margin-top:12px}
        .small-link{color:var(--dark);text-decoration:none;font-weight:600}
        footer{margin-top:18px;font-size:12px;color:#6b7a93;text-align:center}
        .spinner{width:16px;height:16px;border:2px solid rgba(255,255,255,0.35);border-top-color:rgba(255,255,255,0.95);border-radius:50%;animation:spin .8s linear infinite;display:inline-block}
        @keyframes spin{to{transform:rotate(360deg)}}
        @media(max-width:440px){.card{padding:20px}.brand .logo{font-size:22px}}
        .error{color:var(--error);font-size:13px;margin-top:4px}
        .alert{padding:12px;border-radius:8px;margin-bottom:16px;font-size:14px}
        .alert-error{background:rgba(176,0,32,0.1);color:var(--error)}
        .alert-success{background:rgba(13,128,80,0.1);color:var(--success)}
        .field-error{border-color:var(--error)}
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand">
            <div class="logo">Saving Ant</div>
            <div class="tag">Save Smart. Track Easily.</div>
        </div>

        <div class="card" role="main" aria-labelledby="register-heading">
            <h2 id="register-heading" style="margin:0 0 12px 0;color:var(--dark);font-size:18px">Create an account</h2>

            <?php if (isset($flash)): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <form id="registerForm" action="register.php" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                <div class="form-row">
                    <label for="fullname">Full name</label>
                    <input id="fullname" name="fullname" type="text" 
                           placeholder="Your full name" required
                           value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>"
                           class="<?= isset($errors['full_name']) ? 'field-error' : '' ?>">
                    <?php if (isset($errors['full_name'])): ?>
                        <div class="error"><?= htmlspecialchars($errors['full_name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" 
                           placeholder="you@example.com" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           class="<?= isset($errors['email']) ? 'field-error' : '' ?>">
                    <?php if (isset($errors['email'])): ?>
                        <div class="error"><?= htmlspecialchars($errors['email']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" 
                           placeholder="choose a username" required
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           class="<?= isset($errors['username']) ? 'field-error' : '' ?>">
                    <?php if (isset($errors['username'])): ?>
                        <div class="error"><?= htmlspecialchars($errors['username']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" 
                           placeholder="At least 8 characters" required
                           class="<?= isset($errors['password']) ? 'field-error' : '' ?>">
                    <?php if (isset($errors['password'])): ?>
                        <div class="error"><?= htmlspecialchars($errors['password']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <label for="confirm">Confirm password</label>
                    <input id="confirm" name="confirm" type="password" 
                           placeholder="Repeat password" required>
                    <div id="password-error" class="error" style="display:none"></div>
                </div>

                <div class="form-row">
                    <label for="role">Account Type</label>
                    <select id="role" name="role" style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid #e6eefb;background:#fbfdff;font-size:14px;outline:none">
                        <option value="user">Regular User</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>

                <div class="form-row">
                    <button type="submit" class="btn" id="registerBtn">
                        <span id="btnText">Register</span>
                        <span id="btnSpinner" style="display:none;margin-left:6px">
                            <span class="spinner" aria-hidden="true"></span>
                        </span>
                    </button>
                </div>
            </form>

            <div class="muted">Already have an account? <a class="small-link" href="index.php">Login here.</a></div>
        </div>

        <footer>© 2025 Saving Ant — All rights reserved.</footer>
    </div>

    <script>
        (function(){
            const form = document.getElementById('registerForm');
            const btn = document.getElementById('registerBtn');
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');
            const passwordError = document.getElementById('password-error');

            form.addEventListener('submit', function(e){
                const password = document.getElementById('password').value;
                const confirm = document.getElementById('confirm').value;

                // Clear previous error
                passwordError.style.display = 'none';
                passwordError.textContent = '';

                // Client-side password match validation
                if (password !== confirm) {
                    e.preventDefault();
                    passwordError.textContent = 'Passwords do not match';
                    passwordError.style.display = 'block';
                    return;
                }

                // Show loading state
                btn.disabled = true;
                btnText.textContent = 'Registering...';
                btnSpinner.style.display = 'inline-block';
            });
        })();
    </script>
</body>
</html>
