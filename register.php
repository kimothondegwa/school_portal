<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $date_of_birth = trim($_POST['date_of_birth']);
    $gender = trim($_POST['gender']);
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);
    $class_level = trim($_POST['class_level']);
    $guardian_name = trim($_POST['guardian_name']);
    $guardian_phone = trim($_POST['guardian_phone']);
    $guardian_email = trim($_POST['guardian_email']);

    if (empty($first_name) || empty($last_name) || empty($email) || empty($username) || empty($password)) {
        $error = "All required fields must be filled.";
    } else {
        $db = getDB();

        // Check if email or username already exists
        $check = $db->query("SELECT user_id FROM users WHERE email = :email OR username = :username")
                    ->bind(':email', $email)
                    ->bind(':username', $username)
                    ->fetch();

        if ($check) {
            $error = "Email or username already exists!";
        } else {
            // Hash password
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            // Insert into users table
            $db->query("INSERT INTO users (username, email, password_hash, role) 
                        VALUES (:username, :email, :password_hash, 'student')")
               ->bind(':username', $username)
               ->bind(':email', $email)
               ->bind(':password_hash', $password_hash)
               ->execute();

            $user_id = $db->lastInsertId();

            // Generate admission number
            $admission_number = 'ADM' . date('Y') . str_pad($user_id, 4, '0', STR_PAD_LEFT);

            // Insert into students table
            $db->query("INSERT INTO students 
                        (user_id, admission_number, first_name, last_name, date_of_birth, gender, phone_number, address, class_level, guardian_name, guardian_phone, guardian_email)
                        VALUES
                        (:user_id, :admission_number, :first_name, :last_name, :date_of_birth, :gender, :phone_number, :address, :class_level, :guardian_name, :guardian_phone, :guardian_email)")
               ->bind(':user_id', $user_id)
               ->bind(':admission_number', $admission_number)
               ->bind(':first_name', $first_name)
               ->bind(':last_name', $last_name)
               ->bind(':date_of_birth', $date_of_birth)
               ->bind(':gender', $gender)
               ->bind(':phone_number', $phone_number)
               ->bind(':address', $address)
               ->bind(':class_level', $class_level)
               ->bind(':guardian_name', $guardian_name)
               ->bind(':guardian_phone', $guardian_phone)
               ->bind(':guardian_email', $guardian_email)
               ->execute();

            // Prefill login
            $_SESSION['registered_email'] = $email;
            $_SESSION['registered_username'] = $username;

            // Redirect to login
            header('Location: login.php');
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - School Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--primary-gradient);
            min-height: 100vh;
            padding: 2rem 0;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255,255,255,0.1) 0%, transparent 50%);
            animation: pulse 15s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .container {
            position: relative;
            z-index: 1;
        }

        /* Registration Card */
        .registration-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideUp 0.6s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Header Section */
        .registration-header {
            background: var(--primary-gradient);
            padding: 2.5rem 2rem;
            text-align: center;
            color: white;
        }

        .registration-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .registration-header h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .registration-header p {
            opacity: 0.9;
            font-size: 1rem;
        }

        /* Form Section */
        .registration-body {
            padding: 2.5rem;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #f8f9fc;
        }

        .section-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-gradient);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .section-header h4 {
            margin: 0;
            color: #5a5c69;
            font-weight: 700;
            font-size: 1.3rem;
        }

        /* Alert Styles */
        .alert {
            border: none;
            border-radius: 10px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(250, 112, 154, 0.15) 0%, rgba(254, 225, 64, 0.15) 100%);
            color: #dc3545;
            border-left: 4px solid #dc3545;
        }

        .alert i {
            font-size: 1.5rem;
        }

        /* Form Labels */
        .form-label {
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }

        .form-label i {
            color: #667eea;
            font-size: 0.9rem;
        }

        .required {
            color: #e74a3b;
            margin-left: 0.2rem;
        }

        /* Form Controls */
        .form-control, .form-select {
            border: 2px solid #e3e6f0;
            border-radius: 10px;
            padding: 0.8rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #764ba2;
            box-shadow: 0 0 0 0.25rem rgba(118, 75, 162, 0.15);
            outline: 0;
        }

        .form-control::placeholder {
            color: #b8b9bd;
        }

        /* Password Toggle */
        .password-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #858796;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #764ba2;
        }

        /* Submit Button */
        .btn-register {
            width: 100%;
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(118, 75, 162, 0.4);
        }

        /* Back to Login */
        .back-to-login {
            text-align: center;
            padding-top: 1.5rem;
            margin-top: 1.5rem;
            border-top: 2px solid #f8f9fc;
        }

        .back-to-login a {
            color: #764ba2;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-to-login a:hover {
            color: #667eea;
        }

        /* Progress Steps */
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e3e6f0;
            z-index: 0;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            background: white;
            border: 2px solid #e3e6f0;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #858796;
            margin-bottom: 0.5rem;
        }

        .step.active .step-circle {
            background: var(--primary-gradient);
            border-color: transparent;
            color: white;
        }

        .step-label {
            font-size: 0.85rem;
            color: #858796;
            font-weight: 500;
        }

        .step.active .step-label {
            color: #5a5c69;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 1rem 0;
            }

            .registration-body {
                padding: 1.5rem;
            }

            .registration-header {
                padding: 2rem 1.5rem;
            }

            .registration-header h2 {
                font-size: 1.5rem;
            }

            .section-header h4 {
                font-size: 1.1rem;
            }

            .progress-steps {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9">
                <div class="registration-card">
                    <!-- Header -->
                    <div class="registration-header">
                        <div class="registration-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h2>Student Registration</h2>
                        <p>Join our learning community and start your academic journey</p>
                    </div>

                    <!-- Form Body -->
                    <div class="registration-body">
                        <!-- Progress Steps -->
                        <div class="progress-steps">
                            <div class="step active">
                                <div class="step-circle">1</div>
                                <div class="step-label">Personal Info</div>
                            </div>
                            <div class="step active">
                                <div class="step-circle">2</div>
                                <div class="step-label">Account Details</div>
                            </div>
                            <div class="step active">
                                <div class="step-circle">3</div>
                                <div class="step-label">Guardian Info</div>
                            </div>
                        </div>

                        <!-- Error Alert -->
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i>
                                <span><?= htmlspecialchars($error) ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="registrationForm">
                            <!-- Personal Information -->
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <h4>Personal Information</h4>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-id-badge"></i>
                                        First Name <span class="required">*</span>
                                    </label>
                                    <input type="text" name="first_name" class="form-control" placeholder="Enter first name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-id-badge"></i>
                                        Last Name <span class="required">*</span>
                                    </label>
                                    <input type="text" name="last_name" class="form-control" placeholder="Enter last name" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-calendar"></i>
                                        Date of Birth
                                    </label>
                                    <input type="date" name="date_of_birth" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-venus-mars"></i>
                                        Gender
                                    </label>
                                    <select name="gender" class="form-select">
                                        <option value="">Select gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-phone"></i>
                                        Phone Number
                                    </label>
                                    <input type="tel" name="phone_number" class="form-control" placeholder="Enter phone number">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-graduation-cap"></i>
                                        Class Level
                                    </label>
                                    <input type="text" name="class_level" class="form-control" placeholder="e.g., Grade 10, Form 4">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-map-marker-alt"></i>
                                    Address
                                </label>
                                <input type="text" name="address" class="form-control" placeholder="Enter full address">
                            </div>

                            <!-- Account Details -->
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-user-lock"></i>
                                </div>
                                <h4>Account Details</h4>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-envelope"></i>
                                        Email Address <span class="required">*</span>
                                    </label>
                                    <input type="email" name="email" class="form-control" placeholder="your.email@example.com" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-user-circle"></i>
                                        Username <span class="required">*</span>
                                    </label>
                                    <input type="text" name="username" class="form-control" placeholder="Choose a username" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-lock"></i>
                                    Password <span class="required">*</span>
                                </label>
                                <div class="password-wrapper">
                                    <input type="password" name="password" id="password" class="form-control" placeholder="Create a strong password" required>
                                    <i class="fas fa-eye password-toggle" id="togglePassword" onclick="togglePassword()"></i>
                                </div>
                            </div>

                            <!-- Guardian Information -->
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h4>Guardian Information</h4>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-user-tie"></i>
                                    Guardian Name
                                </label>
                                <input type="text" name="guardian_name" class="form-control" placeholder="Enter guardian's full name">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-phone"></i>
                                        Guardian Phone
                                    </label>
                                    <input type="tel" name="guardian_phone" class="form-control" placeholder="Guardian's phone number">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-envelope"></i>
                                        Guardian Email
                                    </label>
                                    <input type="email" name="guardian_email" class="form-control" placeholder="Guardian's email address">
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" class="btn-register">
                                <i class="fas fa-user-plus"></i>
                                Create Account
                            </button>

                            <!-- Back to Login -->
                            <div class="back-to-login">
                                <a href="login.php">
                                    <i class="fas fa-arrow-left"></i>
                                    Already have an account? Login here
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle Password Visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePassword');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Form submission animation
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const btn = document.querySelector('.btn-register');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
            btn.disabled = true;
        });

        // Add entrance animation
        window.addEventListener('load', function() {
            const card = document.querySelector('.registration-card');
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>