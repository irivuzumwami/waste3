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
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address!";
    }
    
    // Validate phone (Rwandan format)
    if (!preg_match('/^07[0-9]{8}$/', $phone)) {
        $errors[] = "Invalid phone number! Must be 10 digits starting with 07 (e.g., 0781234567)";
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
            // Insert into customer table
            $stmt = $pdo->prepare("INSERT INTO customer (firstname, lastname, email, phone, status, housenumber, province, district, sector, cell, village, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$firstname, $lastname, $email, $phone, $status, $housenumber, $province, $district, $sector, $cell, $village, $password])) {
                $success = "Registration successful! Redirecting to login...";
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

// Check if background image exists
$bgImage = 'ede.jpg';
$bgImagePath = __DIR__ . '/../' . $bgImage;
if (!file_exists($bgImagePath)) {
    $bgImage = '';
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
            color: #fff;
            min-height: 100vh;
            position: relative;
        }
        
        /* Background Image with Overlay */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: <?php echo $bgImage ? "url('../" . $bgImage . "')" : "linear-gradient(135deg, #1e3a8a, #0f172a)"; ?>;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            filter: brightness(0.6);
            z-index: -2;
        }
        
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0,0,0,0.7), rgba(0,0,0,0.5));
            z-index: -1;
        }
        
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        
        h1 {
            text-align: center;
            color: var(--secondary);
            margin-bottom: 2rem;
            font-size: 2rem;
        }
        
        h1 i {
            margin-right: 10px;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
            font-weight: 500;
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
        
        /* Enhanced select dropdown styling with better visibility */
        select {
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
           
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1rem;
        }
        
        select option {
            background: var(--bg-dark);
            color: #fff;
            padding: 12px;
            font-size: 1rem;
        }
        
        select option:hover {
            background: var(--primary);
            color: var(--secondary);
        }
        
        select:focus option:checked {
            background: var(--primary) linear-gradient(0deg, var(--primary) 0%, var(--primary) 100%);
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.2);
            background: rgba(255,255,255,0.15);
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
        
        /* Button Group - Both Buttons Styled Consistently */
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .btn {
            padding: 1rem;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: none;
        }
        
        .btn-register {
            flex: 2;
            background: linear-gradient(135deg, var(--primary), #2e4a8a);
            color: #fff;
            border: 1px solid var(--secondary);
        }
        
        .btn-register:hover {
            background: linear-gradient(135deg, var(--secondary), #fcd34d);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(251, 191, 36, 0.4);
        }
        
        .btn-back {
            flex: 1;
            background: linear-gradient(135deg, var(--primary), #2e4a8a);
            color: #fff;
            border: 1px solid var(--secondary);
        }
        
        .btn-back:hover {
            background: linear-gradient(135deg, var(--secondary), #fcd34d);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(251, 191, 36, 0.4);
        }
        
        .btn i {
            font-size: 1rem;
            transition: transform 0.3s ease;
        }
        
        .btn-register:hover i {
            transform: translateX(3px);
        }
        
        .btn-back:hover i {
            transform: translateX(-3px);
        }
        
        /* Alert Styles */
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
            background: rgba(255,68,68,0.2);
            border: 1px solid #ff4444;
            color: #ff8888;
        }
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
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
        }
        
        .strength-weak { color: #ff4444; }
        .strength-medium { color: #ffbb44; }
        .strength-strong { color: var(--teal-accent); }
        
        .match-success { color: var(--teal-accent); }
        .match-error { color: #ff4444; }
        
        small {
            display: block;
            margin-top: 0.3rem;
            color: var(--text-muted);
            font-size: 0.7rem;
        }
        
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
        
        @media (max-width: 600px) {
            .container {
                margin: 1rem;
                padding: 1.5rem;
            }
            .row {
                grid-template-columns: 1fr;
            }
            .button-group {
                flex-direction: column;
            }
            .btn-register, .btn-back {
                flex: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <i class="fas fa-user-plus"></i> 
            Customer Registration
        </h1>
        
        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
            <script>
                setTimeout(function() {
                    window.location.href = '../index.php?success=registered&show_login=1';
                }, 2000);
            </script>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="registerForm" onsubmit="return validateForm()">
            <div class="row">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> First Name *</label>
                    <input type="text" name="firstname" value="<?php echo isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : ''; ?>" placeholder="Enter first name" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Last Name *</label>
                    <input type="text" name="lastname" value="<?php echo isset($_POST['lastname']) ? htmlspecialchars($_POST['lastname']) : ''; ?>" placeholder="Enter last name" required>
                </div>
            </div>
            
            <div class="row">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email *</label>
                    <input type="email" name="email" id="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" placeholder="your@email.com" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Phone *</label>
                    <input type="tel" name="phone" id="phone" placeholder="0781234567" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                    <small>Format: 0781234567</small>
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-home"></i> House Number *</label>
                <input type="text" name="housenumber" placeholder="e.g., KN 14 Ave, KG 123 St" value="<?php echo isset($_POST['housenumber']) ? htmlspecialchars($_POST['housenumber']) : ''; ?>" required>
            </div>
            
            <div class="row">
                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Province *</label>
                    <select name="province" id="province" required style="color: #fff;">
                        <option value="" disabled <?php echo !isset($_POST['province']) ? 'selected' : ''; ?>>-- Select Province --</option>
                        <option value="Kigali" <?php echo (isset($_POST['province']) && $_POST['province'] == 'Kigali') ? 'selected' : ''; ?> style="background: #1e3a8a;">Kigali City</option>
                        <option value="Northern" <?php echo (isset($_POST['province']) && $_POST['province'] == 'Northern') ? 'selected' : ''; ?> style="background: #1e3a8a;">Northern Province</option>
                        <option value="Southern" <?php echo (isset($_POST['province']) && $_POST['province'] == 'Southern') ? 'selected' : ''; ?> style="background: #1e3a8a;">Southern Province</option>
                        <option value="Eastern" <?php echo (isset($_POST['province']) && $_POST['province'] == 'Eastern') ? 'selected' : ''; ?> style="background: #1e3a8a;">Eastern Province</option>
                        <option value="Western" <?php echo (isset($_POST['province']) && $_POST['province'] == 'Western') ? 'selected' : ''; ?> style="background: #1e3a8a;">Western Province</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-city"></i> District *</label>
                    <input type="text" name="district" placeholder="Enter district" value="<?php echo isset($_POST['district']) ? htmlspecialchars($_POST['district']) : ''; ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="form-group">
                    <label><i class="fas fa-building"></i> Sector *</label>
                    <input type="text" name="sector" placeholder="Enter sector" value="<?php echo isset($_POST['sector']) ? htmlspecialchars($_POST['sector']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-location-dot"></i> Cell *</label>
                    <input type="text" name="cell" placeholder="Enter cell" value="<?php echo isset($_POST['cell']) ? htmlspecialchars($_POST['cell']) : ''; ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-tree"></i> Village *</label>
                <input type="text" name="village" placeholder="Enter village" value="<?php echo isset($_POST['village']) ? htmlspecialchars($_POST['village']) : ''; ?>" required>
            </div>
            
            <div class="row">
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password *</label>
                    <div class="password-container">
                        <input type="password" name="password" id="password" placeholder="Minimum 6 characters" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength" id="passwordStrength"></div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirm Password *</label>
                    <div class="password-container">
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm your password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength" id="confirmMatch"></div>
                </div>
            </div>
            
            <div class="button-group">
                <button type="submit" class="btn btn-register">
                    <i class="fas fa-user-plus"></i> Register Now
                </button>
                <button type="button" class="btn btn-back" onclick="window.location.href='../index.php'">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </button>
            </div>
        </form>
        
        <div class="login-link">
            <i class="fas fa-sign-in-alt"></i> Already have an account? 
            <a href="../index.php?show_login=1">Login here</a>
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
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            
            // Contains numbers
            if (/\d/.test(password)) strength++;
            
            // Contains lowercase and uppercase
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            
            // Contains special characters
            if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
            
            if (strength <= 2) {
                message = '⚠️ Weak password';
                className = 'strength-weak';
            } else if (strength <= 4) {
                message = '⚡ Medium password';
                className = 'strength-medium';
            } else {
                message = '✓ Strong password';
                className = 'strength-strong';
            }
            
            strengthDiv.innerHTML = '<i class="fas fa-info-circle"></i> ' + message;
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
                matchDiv.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
                matchDiv.className = 'password-strength match-success';
            } else {
                matchDiv.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match';
                matchDiv.className = 'password-strength match-error';
            }
        }
        
        // Validate form before submission
        function validateForm() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const email = document.getElementById('email').value;
            const phone = document.getElementById('phone').value;
            const province = document.getElementById('province').value;
            
            if (password !== confirm) {
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            if (!province) {
                alert('Please select a province!');
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
                alert('Please enter a valid phone number (format: 0781234567)');
                return false;
            }
            
            return true;
        }
        
        // Add event listeners
        const passwordField = document.getElementById('password');
        const confirmField = document.getElementById('confirm_password');
        
        if (passwordField) {
            passwordField.addEventListener('input', function() {
                checkPasswordStrength();
                checkPasswordMatch();
            });
        }
        
        if (confirmField) {
            confirmField.addEventListener('input', checkPasswordMatch);
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
        
        // Open login modal function (for the login link)
        function openLoginModal() {
            window.location.href = '../index.php?show_login=1';
        }
        
        // Add province select styling enhancement
        const provinceSelect = document.getElementById('province');
        if (provinceSelect) {
            provinceSelect.addEventListener('change', function() {
                if (this.value) {
                    this.style.color = '#fff';
                    this.style.fontWeight = '500';
                }
            });
        }
    </script>
</body>
</html>