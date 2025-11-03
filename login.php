<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();

if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'admin': redirect('admin/dashboard.php'); break;
        case 'teacher': redirect('teachers/dashboard.php'); break;
        case 'student': redirect('students/dashboard.php'); break;
        default: redirect('index.php');
    }
}

$error = '';
$prefilled_username = $_COOKIE['remember_user'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_or_email = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $remember = isset($_POST['remember']);

    if (!$username_or_email || !$password || !$role) {
        $error = 'Please fill in all fields.';
    } else {
        $db = getDB();

        $sql = "SELECT * FROM users WHERE (username = :username OR email = :email) AND role = :role AND is_active = 1 LIMIT 1";
        $user = $db->query($sql)
                   ->bind(':username', $username_or_email)
                   ->bind(':email', $username_or_email)
                   ->bind(':role', $role)
                   ->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid credentials for selected role.';
        } else {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;

            if ($remember) {
                setcookie('remember_user', $user['username'], time() + 86400*30, "/");
            } else {
                setcookie('remember_user', '', time() - 3600, "/");
            }

            switch ($user['role']) {
                case 'admin': header('Location: admin/dashboard.php'); exit; break;
                case 'teacher': header('Location: teachers/dashboard.php'); exit; break;
                case 'student': header('Location: students/dashboard.php'); exit; break;
                default: header('Location: index.php'); exit; break;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - School Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

:root {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --secondary: #8b5cf6;
    --accent: #ec4899;
    --success: #10b981;
    --danger: #ef4444;
    --dark: #0f172a;
    --light: #f8fafc;
    --gray: #64748b;
    --border: #e2e8f0;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    position: relative;
    overflow: hidden;
}

/* Animated background elements */
body::before,
body::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
    animation: float 8s ease-in-out infinite;
}

body::before {
    width: 600px;
    height: 600px;
    top: -300px;
    right: -200px;
    animation-delay: 0s;
}

body::after {
    width: 400px;
    height: 400px;
    bottom: -200px;
    left: -100px;
    animation-delay: 2s;
}

@keyframes float {
    0%, 100% { transform: translate(0, 0) rotate(0deg); }
    33% { transform: translate(30px, -30px) rotate(120deg); }
    66% { transform: translate(-20px, 20px) rotate(240deg); }
}

/* Mesh gradient overlay */
.mesh-overlay {
    position: absolute;
    inset: 0;
    background: 
        radial-gradient(circle at 20% 50%, rgba(120, 119, 198, 0.3), transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(255, 119, 198, 0.3), transparent 50%),
        radial-gradient(circle at 40% 20%, rgba(138, 92, 246, 0.3), transparent 50%);
    pointer-events: none;
}

.login-container {
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 480px;
}

.login-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px) saturate(180%);
    border-radius: 32px;
    padding: 3rem;
    box-shadow: 
        0 0 0 1px rgba(255, 255, 255, 0.3),
        0 20px 25px -5px rgba(0, 0, 0, 0.1),
        0 10px 10px -5px rgba(0, 0, 0, 0.04),
        0 0 80px rgba(99, 102, 241, 0.15);
    position: relative;
    animation: slideIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(40px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Glassmorphism shine effect */
.login-card::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 32px;
    padding: 2px;
    background: linear-gradient(135deg, 
        rgba(255, 255, 255, 0.4) 0%, 
        rgba(255, 255, 255, 0) 50%, 
        rgba(255, 255, 255, 0.2) 100%);
    -webkit-mask: 
        linear-gradient(#fff 0 0) content-box, 
        linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
}

.logo-container {
    text-align: center;
    margin-bottom: 2.5rem;
}

.logo-icon {
    width: 72px;
    height: 72px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.25rem;
    box-shadow: 
        0 10px 30px -5px rgba(99, 102, 241, 0.4),
        0 0 0 1px rgba(255, 255, 255, 0.1);
    position: relative;
    animation: logoFloat 3s ease-in-out infinite;
}

@keyframes logoFloat {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-8px); }
}

.logo-icon::after {
    content: '';
    position: absolute;
    inset: -2px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    border-radius: 22px;
    z-index: -1;
    opacity: 0;
    transition: opacity 0.3s;
}

.logo-icon:hover::after {
    opacity: 0.3;
}

.logo-icon i {
    font-size: 2rem;
    color: white;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
}

.login-title {
    font-size: 2rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--dark) 0%, var(--primary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
    letter-spacing: -0.02em;
}

.login-subtitle {
    color: var(--gray);
    font-size: 0.95rem;
    font-weight: 500;
}

.form-floating {
    position: relative;
    margin-bottom: 1.25rem;
}

.form-floating > .form-control,
.form-floating > .form-select {
    height: 58px;
    border: 2px solid var(--border);
    border-radius: 16px;
    padding: 1rem 1rem 1rem 3.25rem;
    font-size: 0.95rem;
    font-weight: 500;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: #ffffff;
    color: var(--dark);
}

.form-floating > .form-control:focus,
.form-floating > .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.08);
    outline: none;
    transform: translateY(-1px);
}

.form-floating > label {
    padding: 1rem 1rem 1rem 3.25rem;
    color: var(--gray);
    font-weight: 500;
    font-size: 0.9rem;
}

.input-icon {
    position: absolute;
    left: 1.1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray);
    font-size: 1.15rem;
    z-index: 4;
    transition: all 0.3s;
}

.form-floating > .form-control:focus ~ .input-icon,
.form-floating > .form-select:focus ~ .input-icon {
    color: var(--primary);
    transform: translateY(-50%) scale(1.1);
}

.form-select {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 1.1rem center;
    background-size: 16px 12px;
}

.password-toggle {
    position: absolute;
    right: 1.1rem;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: var(--gray);
    font-size: 1.15rem;
    z-index: 5;
    transition: all 0.3s;
    padding: 0.5rem;
    margin: -0.5rem;
    border-radius: 8px;
}

.password-toggle:hover {
    color: var(--primary);
    background: rgba(99, 102, 241, 0.08);
}

.form-check {
    padding-left: 2rem;
    margin-bottom: 1.5rem;
}

.form-check-input {
    width: 1.35rem;
    height: 1.35rem;
    border: 2.5px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    margin-top: 0.15rem;
}

.form-check-input:checked {
    background-color: var(--primary);
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}

.form-check-input:focus {
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}

.form-check-label {
    color: var(--dark);
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    user-select: none;
}

.btn-login {
    width: 100%;
    height: 58px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    font-weight: 700;
    font-size: 1rem;
    border: none;
    border-radius: 16px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 
        0 10px 25px -5px rgba(99, 102, 241, 0.4),
        0 0 0 1px rgba(255, 255, 255, 0.1) inset;
    position: relative;
    overflow: hidden;
    letter-spacing: 0.02em;
}

.btn-login::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, 
        rgba(255, 255, 255, 0) 0%, 
        rgba(255, 255, 255, 0.2) 50%, 
        rgba(255, 255, 255, 0) 100%);
    transform: translateX(-100%);
    transition: transform 0.6s;
}

.btn-login:hover::before {
    transform: translateX(100%);
}

.btn-login:hover {
    transform: translateY(-3px);
    box-shadow: 
        0 20px 35px -5px rgba(99, 102, 241, 0.5),
        0 0 0 1px rgba(255, 255, 255, 0.1) inset;
}

.btn-login:active {
    transform: translateY(-1px);
}

.btn-back {
    width: 100%;
    height: 58px;
    background: white;
    color: var(--primary);
    font-weight: 700;
    border: 2px solid var(--border);
    border-radius: 16px;
    margin-top: 0.75rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    letter-spacing: 0.02em;
}

.btn-back:hover {
    background: var(--light);
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.15);
}

.divider {
    display: flex;
    align-items: center;
    text-align: center;
    margin: 2rem 0 1.75rem;
}

.divider::before,
.divider::after {
    content: '';
    flex: 1;
    border-bottom: 2px solid var(--border);
}

.divider span {
    padding: 0 1.25rem;
    color: var(--gray);
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.links-container {
    text-align: center;
}

.links-container a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s;
    position: relative;
    padding: 0.25rem 0;
}

.links-container a::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--primary);
    transition: width 0.3s;
}

.links-container a:hover::after {
    width: 100%;
}

.links-container a:hover {
    color: var(--primary-dark);
}

.links-container .mx-2 {
    color: var(--border);
    font-weight: 700;
}

.alert {
    border-radius: 16px;
    border: none;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    animation: shake 0.5s cubic-bezier(0.36, 0.07, 0.19, 0.97);
    font-weight: 500;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-8px); }
    20%, 40%, 60%, 80% { transform: translateX(8px); }
}

.alert-danger {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    color: var(--danger);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.alert i {
    font-size: 1.1rem;
}

@media (max-width: 576px) {
    .login-card {
        padding: 2rem 1.5rem;
        border-radius: 28px;
    }
    
    .login-title {
        font-size: 1.75rem;
    }
    
    .logo-icon {
        width: 64px;
        height: 64px;
    }
    
    .logo-icon i {
        font-size: 1.75rem;
    }
}

/* Loading state animation */
@keyframes spin {
    to { transform: rotate(360deg); }
}

.btn-login.loading::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    top: 50%;
    left: 50%;
    margin-left: -10px;
    margin-top: -10px;
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 0.6s linear infinite;
}

/* Role Selector Styles */
.role-selector-wrapper {
    margin-bottom: 1.5rem;
}

.role-label {
    display: block;
    color: var(--dark);
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 1rem;
    text-align: center;
}

.role-buttons {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.role-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.25rem 0.75rem;
    background: white;
    border: 2px solid var(--border);
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.role-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    opacity: 0;
    transition: opacity 0.3s;
}

.role-btn i {
    font-size: 1.75rem;
    color: var(--gray);
    transition: all 0.3s;
    position: relative;
    z-index: 1;
    margin-bottom: 0.5rem;
}

.role-btn span {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--dark);
    transition: all 0.3s;
    position: relative;
    z-index: 1;
}

.role-btn:hover {
    border-color: var(--primary);
    transform: translateY(-3px);
    box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.25);
}

.role-btn:hover i,
.role-btn:hover span {
    color: var(--primary);
}

.role-btn.active {
    border-color: var(--primary);
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    box-shadow: 
        0 10px 25px -5px rgba(99, 102, 241, 0.4),
        0 0 0 1px rgba(255, 255, 255, 0.1) inset;
    transform: translateY(-2px);
}

.role-btn.active::before {
    opacity: 1;
}

.role-btn.active i,
.role-btn.active span {
    color: white;
}

.role-error {
    color: var(--danger);
    font-size: 0.85rem;
    font-weight: 500;
    text-align: center;
    margin-top: 0.5rem;
    animation: shake 0.5s;
}

@media (max-width: 576px) {
    .role-buttons {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    
    .role-btn {
        flex-direction: row;
        justify-content: flex-start;
        padding: 1rem 1.25rem;
        gap: 1rem;
    }
    
    .role-btn i {
        margin-bottom: 0;
        font-size: 1.5rem;
    }
    
    .role-btn span {
        font-size: 0.95rem;
    }
}
</style>
</head>
<body>
<div class="mesh-overlay"></div>
<div class="login-container">
    <div class="login-card">
        <div class="logo-container">
            <div class="logo-icon">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <h3 class="login-title">Welcome Back</h3>
            <p class="login-subtitle">Sign in to continue to your account</p>
        </div>

        <?php if(!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="form-floating">
                <input type="text" name="username" class="form-control" id="username" 
                       placeholder="Username or Email" value="<?= htmlspecialchars($prefilled_username) ?>" required>
                <label for="username">Username or Email</label>
                <i class="bi bi-person-fill input-icon"></i>
            </div>

            <div class="form-floating">
                <input type="password" name="password" class="form-control" id="password" 
                       placeholder="Password" required>
                <label for="password">Password</label>
                <i class="bi bi-lock-fill input-icon"></i>
                <i class="bi bi-eye-fill password-toggle" id="togglePassword"></i>
            </div>

            <div class="role-selector-wrapper">
                <label class="role-label">Select Your Role</label>
                <div class="role-buttons">
                    <div class="role-btn" data-role="admin">
                        <i class="bi bi-shield-fill-check"></i>
                        <span>Admin</span>
                    </div>
                    <div class="role-btn" data-role="teacher">
                        <i class="bi bi-person-video3"></i>
                        <span>Teacher</span>
                    </div>
                    <div class="role-btn" data-role="student">
                        <i class="bi bi-backpack-fill"></i>
                        <span>Student</span>
                    </div>
                </div>
                <input type="hidden" name="role" id="role" required>
                <div class="role-error" style="display: none;">Please select a role</div>
            </div>

            <div class="form-check">
                <input type="checkbox" name="remember" class="form-check-input" id="remember" 
                       <?= isset($_COOKIE['remember_user']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="remember">
                    Keep me signed in for 30 days
                </label>
            </div>

            <button type="submit" class="btn btn-login">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
            
            <button type="button" class="btn btn-back" onclick="window.location.href='index.php'">
                <i class="bi bi-arrow-left me-2"></i>Back to Home
            </button>
        </form>

        <div class="divider">
            <span>Or</span>
        </div>

        <div class="links-container">
            <a href="register.php">
                <i class="bi bi-person-plus-fill me-1"></i>Create Account
            </a>
            <span class="mx-2">•</span>
            <a href="forgot_password.php">
                <i class="bi bi-key-fill me-1"></i>Reset Password
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Password toggle functionality
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');

togglePassword.addEventListener('click', function() {
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    
    this.classList.toggle('bi-eye-fill');
    this.classList.toggle('bi-eye-slash-fill');
});

// Role selection functionality
const roleButtons = document.querySelectorAll('.role-btn');
const roleInput = document.getElementById('role');
const roleError = document.querySelector('.role-error');

roleButtons.forEach(btn => {
    btn.addEventListener('click', function() {
        // Remove active class from all buttons
        roleButtons.forEach(b => b.classList.remove('active'));
        
        // Add active class to clicked button
        this.classList.add('active');
        
        // Set the hidden input value
        roleInput.value = this.getAttribute('data-role');
        
        // Hide error if visible
        roleError.style.display = 'none';
    });
});

// Form validation with animation
const form = document.getElementById('loginForm');
const loginBtn = form.querySelector('.btn-login');

form.addEventListener('submit', function(e) {
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const role = document.getElementById('role').value;
    
    if (!username || !password || !role) {
        e.preventDefault();
        
        // Add shake animation to empty fields
        if (!username) {
            document.getElementById('username').parentElement.style.animation = 'shake 0.5s';
            document.getElementById('username').focus();
        }
        if (!password) document.getElementById('password').parentElement.style.animation = 'shake 0.5s';
        if (!role) {
            roleError.style.display = 'block';
            document.querySelector('.role-selector-wrapper').style.animation = 'shake 0.5s';
        }
        
        setTimeout(() => {
            document.querySelectorAll('.form-floating, .role-selector-wrapper').forEach(el => el.style.animation = '');
        }, 500);
    } else {
        // Add loading state
        loginBtn.classList.add('loading');
        loginBtn.innerHTML = '<span style="opacity: 0">Signing in...</span>';
    }
});

// Clear animations on input
document.querySelectorAll('.form-control, .form-select').forEach(input => {
    input.addEventListener('input', function() {
        this.parentElement.style.animation = '';
    });
});

// Add smooth focus transitions
document.querySelectorAll('.form-control, .form-select').forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.style.transform = 'translateY(-2px)';
    });
    
    input.addEventListener('blur', function() {
        this.parentElement.style.transform = 'translateY(0)';
    });
});
</script>
</body>
</html>ml>