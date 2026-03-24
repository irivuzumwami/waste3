<?php
// customer/register.php
session_start();
require_once '../config/database.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $housenumber = trim($_POST['housenumber']);
    $province = $_POST['province'];
    $district = trim($_POST['district']);
    $sector = trim($_POST['sector']);
    $cell = trim($_POST['cell']);
    $village = trim($_POST['village']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $status = 'active';
    
    // Validation
    $errors = [];
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match!";
    }
    
    // Check password length
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long!";
    }
    
    // Check if email already exists
    $check_email = $pdo->prepare("SELECT id FROM customer WHERE email = ?");
    $check_email->execute([$email]);
    if ($check_email->rowCount() > 0) {
        $errors[] = "Email address already registered!";
    }
    
    // Check if phone already exists
    $check_phone = $pdo->prepare("SELECT id FROM customer WHERE phone = ?");
    $check_phone->execute([$phone]);
    if ($check_phone->rowCount() > 0) {
        $errors[] = "Phone number already registered!";
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        try {
            // Insert into customer table (remove the foreign key constraint issue)
            $stmt = $pdo->prepare("INSERT INTO customer (firstname, lastname, email, phone, status, housenumber, province, district, sector, cell, village, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$firstname, $lastname, $email, $phone, $status, $housenumber, $province, $district, $sector, $cell, $village, $password])) {
                $success = "Registration successful! You can now login.";
                // Clear form data
                $_POST = array();
            } else {
                $error = "Registration failed. Please try again.";
            }
        } catch(PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = "Database error. Please try again later.";
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Registration - EcoWaste</title>
    <!-- Font Awesome for password toggle -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1e3a8a;
            --secondary: #fbbf24;
            --glass-bg: rgba(255, 255, 255, 0.12);
            --bg-dark: #0f172a;
            --footer-bg: #0b1120;
            --text-muted: #94a3b8;
            --teal-accent: #00c49a;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-dark);
            color: #fff;
            min-height: 100vh;
        }
        
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: rgba(255,255,255,0.05);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        h1 {
            text-align: center;
            color: var(--secondary);
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
        }
        
        input, select {
            width: 100%;
            padding: 0.8rem;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.2);
        }
        
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        /* Password Container with Toggle */
        .password-container {
            position: relative;
            width: 100%;
        }
        
        .password-container input {
            width: 100%;
            padding: 0.8rem;
            padding-right: 45px;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 8px;
            color: var(--text-muted);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1.1rem;
            z-index: 10;
        }
        
        .toggle-password:hover {
            color: var(--secondary);
            background: rgba(255,255,255,0.1);
        }
        
        button[type="submit"] {
            width: 100%;
            padding: 1rem;
            background: var(--primary);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            margin-top: 1rem;
            transition: 0.3s;
        }
        
        button[type="submit"]:hover {
            background: var(--secondary);
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            text-align: center;
            animation: slideIn 0.3s ease;
        }
        
        .alert-success {
            background: rgba(0,196,154,0.2);
            border: 1px solid var(--teal-accent);
            color: var(--teal-accent);
        }
        
        .alert-error {
            background: rgba(255,0,0,0.2);
            border: 1px solid #ff4444;
            color: #ff4444;
        }
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .login-link a {
            color: var(--secondary);
            text-decoration: none;
            transition: 0.3s;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .password-strength {
            font-size: 0.8rem;
            margin-top: 0.3rem;
            color: var(--text-muted);
        }
        
        .strength-weak { color: #ff4444; }
        .strength-medium { color: #ffbb44; }
        .strength-strong { color: var(--teal-accent); }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 300px) {
            .container {
                margin: 1rem;
                padding: 1rem;
            }
            .row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Customer Registration</h1>
        
        <?php if($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <script>
                setTimeout(function() {
                    window.location.href = '../index.php?success=registered';
                }, 2000);
            </script>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" id="registerForm" onsubmit="return validateForm()">
            <div class="row">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="firstname" value="<?php echo isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="lastname" value="<?php echo isset($_POST['lastname']) ? htmlspecialchars($_POST['lastname']) : ''; ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" id="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>House Number</label>
                <input type="text" name="housenumber" value="<?php echo isset($_POST['housenumber']) ? htmlspecialchars($_POST['housenumber']) : ''; ?>" required>
            </div>
            
            <div class="row">
                <div class="form-group">
                    <label>Province</label>
                    <select name="province" required>
                        <option value="">Select Province</option>
                        <option value="Kigali" <?php echo (isset($_POST['province']) && $_POST['province'] == 'Kigali') ? 'selected' : ''; ?>>Kigali</option>
                        <option value="Northern" <?php echo (isset($_POST['province']) && $_POST['province'] == 'Northern') ? 'selected' : ''; ?>>Northern</option>
                        <option value="Southern" <?php echo (isset($_POST['province']) && $_POST['province'] == 'Southern') ? 'selected' : ''; ?>>Southern</option>
                        <option value="Eastern" <?php echo (isset($_POST['province']) && $_POST['province'] == 'Eastern') ? 'selected' : ''; ?>>Eastern</option>
                        <option value="Western" <?php echo (isset($_POST['province']) && $_POST['province'] == 'Western') ? 'selected' : ''; ?>>Western</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>District</label>
                    <input type="text" name="district" value="<?php echo isset($_POST['district']) ? htmlspecialchars($_POST['district']) : ''; ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="form-group">
                    <label>Sector</label>
                    <input type="text" name="sector" value="<?php echo isset($_POST['sector']) ? htmlspecialchars($_POST['sector']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Cell</label>
                    <input type="text" name="cell" value="<?php echo isset($_POST['cell']) ? htmlspecialchars($_POST['cell']) : ''; ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Village</label>
                <input type="text" name="village" value="<?php echo isset($_POST['village']) ? htmlspecialchars($_POST['village']) : ''; ?>" required>
            </div>
            
            <div class="row">
                <div class="form-group">
                    <label>Password</label>
                    <div class="password-container">
                        <input type="password" name="password" id="password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength" id="passwordStrength"></div>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <div class="password-container">
                        <input type="password" name="confirm_password" id="confirm_password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength" id="confirmMatch"></div>
                </div>
            </div>
            
            <button type="submit">Register Now</button>
            <button type="button" style="margin-top: 0." onclick="window.location.href='../index.php'">Back</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="../index.php" onclick="openLoginModal()">Login here</a>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const input = document.getElementById(fieldId);
            const button = input.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Password strength checker
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.innerHTML = '';
                return;
            }
            
            let strength = 0;
            let message = '';
            let className = '';
            
            // Length check
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            
            // Contains numbers
            if (/\d/.test(password)) strength++;
            
            // Contains lowercase and uppercase
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            
            // Contains special characters
            if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
            
            if (strength <= 2) {
                message = 'Weak password';
                className = 'strength-weak';
            } else if (strength <= 4) {
                message = 'Medium password';
                className = 'strength-medium';
            } else {
                message = 'Strong password';
                className = 'strength-strong';
            }
            
            strengthDiv.innerHTML = message;
            strengthDiv.className = 'password-strength ' + className;
        }
        
        // Check if passwords match
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('confirmMatch');
            
            if (confirm.length === 0) {
                matchDiv.innerHTML = '';
                return;
            }
            
            if (password === confirm) {
                matchDiv.innerHTML = '✓ Passwords match';
                matchDiv.style.color = '#00c49a';
            } else {
                matchDiv.innerHTML = '✗ Passwords do not match';
                matchDiv.style.color = '#ff4444';
            }
        }
        
        // Validate form before submission
        function validateForm() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const email = document.getElementById('email').value;
            const phone = document.getElementById('phone').value;
            
            if (password !== confirm) {
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address!');
                return false;
            }
            
            // Phone validation (Rwandan format)
            const phoneRegex = /^07[0-9]{8}$/;
            if (!phoneRegex.test(phone)) {
                alert('Please enter a valid phone number (format: 078XXXXXXX)');
                return false;
            }
            
            return true;
        }
        
        // Add event listeners
        document.getElementById('password').addEventListener('input', function() {
            checkPasswordStrength();
            checkPasswordMatch();
        });
        
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
        
        // Open login modal function (for the login link)
        function openLoginModal() {
            window.location.href = '../index.php?show_login=1';
        }
    </script>
</body>
</html>