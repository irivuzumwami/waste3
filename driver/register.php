<?php
// admin/add_driver.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit;
}
require_once '../config/database.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $category = $_POST['category'];
    $status = $_POST['status'];
    $gender = $_POST['gender'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
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
    
    // Validate phone
    if (!preg_match('/^07[0-9]{8}$/', $phone)) {
        $errors[] = "Invalid phone number! Must be 10 digits starting with 07";
    }
    
    // Check if email already exists
    try {
        $check_email = $pdo->prepare("SELECT id FROM drivers WHERE email = ?");
        $check_email->execute([$email]);
        if ($check_email->rowCount() > 0) {
            $errors[] = "Email address already registered!";
        }
        
        // Check if phone already exists
        $check_phone = $pdo->prepare("SELECT id FROM drivers WHERE phone = ?");
        $check_phone->execute([$phone]);
        if ($check_phone->rowCount() > 0) {
            $errors[] = "Phone number already registered!";
        }
    } catch (PDOException $e) {
        error_log("Check error: " . $e->getMessage());
        $errors[] = "Database error. Please try again.";
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO drivers (firstname, lastname, email, phone, category, status, gender, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $result = $stmt->execute([$firstname, $lastname, $email, $phone, $category, $status, $gender, $password]);
            
            if ($result) {
                $success = "Driver registered successfully!";
                $_POST = array();
            } else {
                $error = "Registration failed. Please try again.";
            }
        } catch(PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = "Registration failed. Please try again.";
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

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
    <title>Add Driver - WMS Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1e3a8a;
            --secondary: #fbbf24;
            --glass-bg: rgba(255, 255, 255, 0.12);
            --bg-dark: #0f172a;
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
            margin-bottom: 1.2rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--secondary);
            font-weight: 600;
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
            background: rgba(255,255,255,0.15);
        }
        
        input::placeholder, select::placeholder {
            color: var(--text-muted);
        }
        
        /* Select Styling */
        select {
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%23fbbf24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>');
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
        
        /* Category Options Colors */
        select[name="category"] option[value="B"] { color: #3498db; background: #0f172a; font-weight: bold; }
        select[name="category"] option[value="C"] { color: #e74c3c; background: #0f172a; font-weight: bold; }
        select[name="category"] option[value="D"] { color: #f39c12; background: #0f172a; font-weight: bold; }
        select[name="category"] option[value="F"] { color: #2ecc71; background: #0f172a; font-weight: bold; }
        
        /* Gender Options Colors */
        select[name="gender"] option[value="Male"] { color: #3498db; background: #0f172a; font-weight: bold; }
        select[name="gender"] option[value="Female"] { color: #e74c3c; background: #0f172a; font-weight: bold; }
        
        /* Status Options Colors */
        select[name="status"] option[value="active"] { color: #00c49a; background: #0f172a; font-weight: bold; }
        select[name="status"] option[value="inactive"] { color: #e74c3c; background: #0f172a; font-weight: bold; }
        
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.2rem;
        }
        
        /* Password Container */
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
        
        /* Button Group */
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
        
        .btn-submit {
            flex: 2;
            background: var(--primary);
            color: #fff;
            border: 1px solid var(--secondary);
        }
        
        .btn-submit:hover {
            background: var(--secondary);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(251, 191, 36, 0.3);
        }
        
        .btn-back {
            flex: 1;
            background: transparent;
            color: var(--secondary);
            border: 1px solid var(--secondary);
        }
        
        .btn-back:hover {
            background: var(--secondary);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(251, 191, 36, 0.3);
        }
        
        .btn i {
            font-size: 1rem;
            transition: transform 0.3s ease;
        }
        
        .btn-submit:hover i {
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
        
        .password-strength {
            font-size: 0.8rem;
            margin-top: 0.3rem;
        }
        
        .strength-weak { color: #ff4444; }
        .strength-medium { color: #ffbb44; }
        .strength-strong { color: var(--teal-accent); }
        
        .match-success { color: var(--teal-accent); }
        .match-error { color: #ff4444; }
        
        .category-info {
            background: rgba(0,196,154,0.1);
            padding: 0.5rem;
            border-radius: 8px;
            margin-top: 0.5rem;
            font-size: 0.8rem;
            text-align: center;
        }
        
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
                gap: 1rem;
            }
            .button-group {
                flex-direction: column;
            }
            .btn-submit, .btn-back {
                flex: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <i class="fas fa-truck"></i> 
            Register New Driver
        </h1>
        
        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
            <script>
                setTimeout(function() {
                    window.location.href = 'dashboard.php?success=driver_added';
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
                    <input type="email" name="email" id="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" placeholder="driver@wms.rw" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Phone *</label>
                    <input type="tel" name="phone" id="phone" placeholder="0781234567" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                    <small>Format: 0781234567</small>
                </div>
            </div>
            
            <div class="row">
                <div class="form-group">
                    <label><i class="fas fa-id-card"></i> Driver Category *</label>
                    <select name="category" id="category" required onchange="updateCategoryInfo()">
                        <option value="" disabled selected>-- Select Driver Category --</option>
                        <option value="B" <?php echo (isset($_POST['category']) && $_POST['category'] == 'B') ? 'selected' : ''; ?>>🚗 Category B - Light Vehicles</option>
                        <option value="C" <?php echo (isset($_POST['category']) && $_POST['category'] == 'C') ? 'selected' : ''; ?>>🚚 Category C - Heavy Trucks</option>
                        <option value="D" <?php echo (isset($_POST['category']) && $_POST['category'] == 'D') ? 'selected' : ''; ?>>🚌 Category D - Buses</option>
                        <option value="F" <?php echo (isset($_POST['category']) && $_POST['category'] == 'F') ? 'selected' : ''; ?>>🏗️ Category F - Heavy Machinery</option>
                    </select>
                    <div class="category-info" id="categoryInfo">
                        <i class="fas fa-info-circle"></i> Select a driver category to see details
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-venus-mars"></i> Gender *</label>
                    <select name="gender" required>
                        <option value="" disabled selected>-- Select Gender --</option>
                        <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>👨 Male</option>
                        <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>👩 Female</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="form-group">
                    <label><i class="fas fa-toggle-on"></i> Status *</label>
                    <select name="status" required>
                        <option value="" disabled selected>-- Select Status --</option>
                        <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : ''; ?>>✅ Active</option>
                        <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : ''; ?>>❌ Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password *</label>
                    <div class="password-container">
                        <input type="password" name="password" id="password" placeholder="Minimum 6 characters" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
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
            
            <div class="button-group">
                <button type="submit" class="btn btn-submit">
                    <i class="fas fa-user-plus"></i> Register Driver
                </button>
                <button type="button" class="btn btn-back" onclick="window.location.href='dashboard.php'">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </button>
            </div>
        </form>
    </div>
    
    <script>
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
        
        function updateCategoryInfo() {
            const category = document.getElementById('category').value;
            const infoDiv = document.getElementById('categoryInfo');
            
            let infoText = '';
            let borderColor = '';
            
            switch(category) {
                case 'B':
                    infoText = '✓ Category B: Light vehicles, cars, vans up to 3.5 tons. Can transport light waste materials.';
                    borderColor = '#3498db';
                    break;
                case 'C':
                    infoText = '✓ Category C: Heavy trucks, vehicles over 3.5 tons. Suitable for large waste collection trucks.';
                    borderColor = '#e74c3c';
                    break;
                case 'D':
                    infoText = '✓ Category D: Buses, passenger vehicles (8+ seats). Can transport waste collection crews.';
                    borderColor = '#f39c12';
                    break;
                case 'F':
                    infoText = '✓ Category F: Heavy machinery, specialized vehicles. For operating compactors and heavy equipment.';
                    borderColor = '#2ecc71';
                    break;
                default:
                    infoText = 'Select a driver category to see vehicle type and responsibilities';
                    borderColor = 'var(--secondary)';
            }
            
            if (category) {
                infoDiv.innerHTML = `<i class="fas fa-info-circle"></i> ${infoText}`;
                infoDiv.style.borderLeft = `3px solid ${borderColor}`;
            } else {
                infoDiv.innerHTML = `<i class="fas fa-info-circle"></i> ${infoText}`;
                infoDiv.style.borderLeft = `3px solid var(--secondary)`;
            }
        }
        
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
        
        function validateForm() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const email = document.getElementById('email').value;
            const phone = document.getElementById('phone').value;
            const category = document.getElementById('category').value;
            const gender = document.querySelector('select[name="gender"]').value;
            const status = document.querySelector('select[name="status"]').value;
            
            if (password !== confirm) {
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            if (!category) {
                alert('Please select a driver category!');
                return false;
            }
            
            if (!gender) {
                alert('Please select gender!');
                return false;
            }
            
            if (!status) {
                alert('Please select status!');
                return false;
            }
            
            const emailRegex = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address!');
                return false;
            }
            
            const phoneRegex = /^07[0-9]{8}$/;
            if (!phoneRegex.test(phone)) {
                alert('Please enter a valid phone number (format: 0781234567)');
                return false;
            }
            
            return true;
        }
        
        const passwordField = document.getElementById('password');
        const confirmField = document.getElementById('confirm_password');
        
        if (passwordField) {
            passwordField.addEventListener('input', checkPasswordMatch);
        }
        
        if (confirmField) {
            confirmField.addEventListener('input', checkPasswordMatch);
        }
        
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
        
        if (document.getElementById('category').value) {
            updateCategoryInfo();
        }
    </script>
</body>
</html>