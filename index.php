<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            exit;
        case 'manager':
            header('Location: manager/dashboard.php');
            exit;
        case 'driver':
            header('Location: driver/dashboard.php');
            exit;
        case 'collector':
            header('Location: collector/dashboard.php');
            exit;
        case 'customer':
            header('Location: customer/dashboard.php');
            exit;
    }
}

// Check if we need to show login modal
$showLoginModal = isset($_GET['error']) || isset($_GET['show_login']) || isset($_GET['logout']);

// Check if a.jpg exists, if not use a default gradient
$bgImage = 'a.jpg';
$bgImagePath = __DIR__ . '/' . $bgImage;
if (!file_exists($bgImagePath)) {
    $bgImage = ''; // Will use gradient fallback
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoWaste | Smart Waste Management System</title>
    <!-- Font Awesome CDN for eye icons -->
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
            line-height: 1.6;
            color: #fff;
            background: var(--bg-dark);
        }
        
        /* Navigation */
        .navbar {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(10px);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            padding: 1rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        /* Enhanced Logo with Icon */
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary), var(--teal-accent));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: gentlePulse 2s infinite;
        }
        
        .logo-icon i {
            font-size: 24px;
            color: var(--secondary);
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        
        .logo-main {
            font-size: 24px;
            font-weight: 800;
        }
        
        .logo-main .eco {
            background: linear-gradient(135deg, var(--primary), var(--teal-accent));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .logo-main .waste {
            color: var(--secondary);
        }
        
        .logo-tagline {
            font-size: 8px;
            color: var(--text-muted);
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        @keyframes gentlePulse {
            0%, 100% {
                box-shadow: 0 2px 8px rgba(0,196,154,0.2);
            }
            50% {
                box-shadow: 0 2px 12px rgba(0,196,154,0.4);
            }
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: #fff;
            text-decoration: none;
            transition: 0.3s;
            font-weight: 500;
        }
        
        .nav-links a:hover {
            color: var(--secondary);
        }
        
        .btn-login {
            background: var(--primary);
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            border: 1px solid var(--secondary);
        }
        
        .btn-login:hover {
            background: var(--secondary);
            color: var(--primary);
        }
        
        /* Hero Section with a.jpg background */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 0 1rem;
            position: relative;
            background-color: var(--bg-dark);
        }
        
        /* Background with image */
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: <?php echo $bgImage ? "url('" . $bgImage . "')" : "linear-gradient(135deg, #1e3a8a, #0f172a)"; ?>;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            filter: brightness(0.5);
            z-index: 0;
        }
        
        /* Gradient overlay for better text readability */
        .hero::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0,0,0,0.7), rgba(0,0,0,0.5));
            z-index: 1;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
        }
        
        .hero-content h1 {
            font-size: 3.8rem;
            margin-bottom: 1rem;
            animation: fadeInUp 1s ease;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .hero-content h1 .highlight {
            color: var(--secondary);
        }
        
        .hero-content p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            color: rgba(255,255,255,0.9);
            animation: fadeInUp 1s ease 0.2s forwards;
            opacity: 0;
            animation-fill-mode: forwards;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 1s ease 0.4s forwards;
            opacity: 0;
            animation-fill-mode: forwards;
        }
        
        .btn {
            padding: 1rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            transition: 0.3s;
            display: inline-block;
        }
        
        .btn-primary {
            background: var(--primary);
            color: #fff;
            border: 1px solid var(--secondary);
        }
        
        .btn-primary:hover {
            background: var(--secondary);
            color: var(--primary);
            transform: translateY(-3px);
        }
        
        .btn-outline {
            border: 2px solid var(--secondary);
            color: var(--secondary);
        }
        
        .btn-outline:hover {
            background: var(--secondary);
            color: var(--primary);
            transform: translateY(-3px);
        }
        
        /* Features Section */
        .features {
            padding: 5rem 5%;
            background: var(--bg-dark);
            position: relative;
            z-index: 1;
        }
        
        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: var(--secondary);
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }
        
        .feature-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 20px;
            text-align: center;
            transition: 0.3s;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            border-color: var(--secondary);
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .feature-card h3 {
            margin-bottom: 1rem;
            color: var(--secondary);
        }
        
        /* Footer */
        .footer {
            background: var(--footer-bg);
            padding: 3rem 5% 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            position: relative;
            z-index: 1;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .footer-section h3 {
            color: var(--secondary);
            margin-bottom: 1rem;
        }
        
        .footer-section p, .footer-section a {
            color: var(--text-muted);
            text-decoration: none;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .footer-section a:hover {
            color: var(--secondary);
        }
        
        .copyright {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: var(--text-muted);
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.show {
            display: flex !important;
        }
        
        .modal-content {
            background: var(--bg-dark);
            padding: 2rem;
            border-radius: 20px;
            width: 90%;
            max-width: 400px;
            border: 1px solid var(--secondary);
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 1.8rem;
            cursor: pointer;
            color: var(--text-muted);
            transition: 0.3s;
        }
        
        .close-modal:hover {
            color: var(--secondary);
        }
        
        .modal-content h2 {
            margin-bottom: 1.5rem;
            text-align: center;
            color: var(--secondary);
        }
        
        .modal-content input {
            width: 100%;
            padding: 0.8rem;
            margin-bottom: 1rem;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .modal-content input:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.2);
        }
        
        /* Password Container with Font Awesome Toggle Button */
        .password-container {
            position: relative;
            width: 100%;
            margin-bottom: 1rem;
        }
        
        .password-container input {
            width: 100%;
            padding: 0.8rem;
            padding-right: 50px;
            margin-bottom: 0;
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
            font-size: 1.2rem;
            z-index: 10;
        }
        
        .toggle-password:hover {
            color: var(--secondary);
            background: rgba(255,255,255,0.1);
            transform: translateY(-50%) scale(1.05);
        }
        
        .toggle-password:active {
            transform: translateY(-50%) scale(0.95);
        }
        
        .toggle-password i {
            pointer-events: none;
            transition: all 0.2s ease;
        }
        
        .modal-content button[type="submit"] {
            width: 100%;
            padding: 0.8rem;
            background: var(--primary);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            font-size: 1rem;
            margin-top: 0.5rem;
        }
        
        .modal-content button[type="submit"]:hover {
            background: var(--secondary);
            color: var(--primary);
        }
        
        /* Alert Styles */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            animation: slideIn 0.3s ease;
        }
        
        .alert-error {
            background: rgba(255, 68, 68, 0.15);
            border-left: 4px solid #ff4444;
            color: #ff8888;
        }
        
        .alert-success {
            background: rgba(0, 196, 154, 0.15);
            border-left: 4px solid var(--teal-accent);
            color: var(--teal-accent);
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
        
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 1rem;
            }
            .hero::before {
                background-attachment: scroll;
            }
            .hero-content h1 {
                font-size: 2rem;
            }
            .btn-group {
                flex-direction: column;
                align-items: center;
            }
            .modal-content {
                width: 95%;
                margin: 1rem;
            }
            .toggle-password {
                right: 10px;
                padding: 6px;
                font-size: 1rem;
            }
            .logo-icon {
                width: 35px;
                height: 35px;
            }
            .logo-icon i {
                font-size: 18px;
            }
            .logo-main {
                font-size: 20px;
            }
            .logo-tagline {
                font-size: 7px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-recycle"></i>
            </div>
            <div class="logo-text">
                <div class="logo-main">
                    <span class="eco">Eco</span><span class="waste">Waste</span>
                </div>
                <div class="logo-tagline">Clean City Initiative</div>
            </div>
        </div>
        <div class="nav-links">
            <a href="#home">Home</a>
            <a href="#features">Features</a>
            <a href="#about">About</a>
            <a href="#contact">Contact</a>
            <a href="javascript:void(0)" onclick="openLoginModal()" class="btn-login">Login</a>
            <a href="customer/register.php" style="background: var(--teal-accent); padding: 0.5rem 1.5rem; border-radius: 50px;">Register</a>
        </div>
    </nav>

    <section class="hero" id="home">
        <div class="hero-content">
            <h1>Smart Waste Management <br><span class="highlight">For Cleaner Communities</span></h1>
            <p>Join us in making our environment cleaner and greener. Schedule pickups, track waste, and contribute to sustainability.</p>
            <div class="btn-group">
                <a href="customer/register.php" class="btn btn-primary">Register as Customer</a>
                <a href="javascript:void(0)" onclick="openLoginModal()" class="btn btn-outline">Login</a>
            </div>
        </div>
    </section>

    <section class="features" id="features">
        <h2 class="section-title">Why Choose Us?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🗑️</div>
                <h3>Scheduled Pickups</h3>
                <p>Schedule waste pickups at your convenience with our easy-to-use system.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Real-time Tracking</h3>
                <p>Track your waste collection status and get instant notifications.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">♻️</div>
                <h3>Eco-Friendly</h3>
                <p>We prioritize recycling and proper waste disposal methods.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💰</div>
                <h3>Easy Payments</h3>
                <p>Secure online payments with digital receipts and history.</p>
            </div>
        </div>
    </section>

    <section class="features" id="about" style="background: var(--footer-bg);">
        <h2 class="section-title">About Us</h2>
        <div class="features-grid">
            <div class="feature-card">
                <h3>Our Mission</h3>
                <p>To provide efficient waste management solutions that promote environmental sustainability and community health.</p>
            </div>
            <div class="feature-card">
                <h3>Our Vision</h3>
                <p>A clean, waste-free Rwanda where every community takes pride in their environment.</p>
            </div>
            <div class="feature-card">
                <h3>Our Team</h3>
                <p>Dedicated professionals working tirelessly to ensure your waste is managed responsibly.</p>
            </div>
        </div>
    </section>

    <footer class="footer" id="contact">
        <div class="footer-content">
            <div class="footer-section">
                <h3>EcoWaste Management</h3>
                <p>Leading the way in sustainable waste management solutions across Rwanda.</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <a href="#home">Home</a>
                <a href="#features">Features</a>
                <a href="#about">About</a>
                <a href="customer/register.php">Register</a>
            </div>
            <div class="footer-section">
                <h3>Contact Info</h3>
                <p>📞 +250 788 123 456</p>
                <p>✉️ info@ecowaste.rw</p>
                <p>📍 Kigali, Rwanda</p>
            </div>
            <div class="footer-section">
                <h3>Working Hours</h3>
                <p>Monday - Friday: 8AM - 5PM</p>
                <p>Saturday: 9AM - 2PM</p>
                <p>Sunday: Closed</p>
            </div>
        </div>
        <div class="copyright">
            <p>&copy; 2026 EcoWaste Management System. All rights reserved.</p>
        </div>
    </footer>

    <!-- Login Modal with Font Awesome Password Toggle -->
    <div id="loginModal" class="modal <?php echo $showLoginModal ? 'show' : ''; ?>">
        <div class="modal-content">
            <span class="close-modal" onclick="closeLoginModal()">&times;</span>
            <h2>Login to Account</h2>
            
            <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-error">
                    <?php 
                    $error = $_GET['error'];
                    switch($error) {
                        case 'empty_fields':
                            echo "❌ Please enter both email and password.";
                            break;
                        case 'invalid_credentials':
                            echo "❌ Invalid email or password. Please try again.";
                            break;
                        case 'account_inactive':
                            echo "⚠️ Your account is inactive. Please contact administrator.";
                            break;
                        case 'database_error':
                            echo "❌ Database error. Please try again later.";
                            break;
                        default:
                            echo "❌ Login failed. Please check your credentials.";
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['success']) && $_GET['success'] == 'registered'): ?>
                <div class="alert alert-success">
                    ✅ Registration successful! Please login to continue.
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['logout']) && $_GET['logout'] == 'success'): ?>
                <div class="alert alert-success">
                    ✅ You have been successfully logged out.
                </div>
            <?php endif; ?>
            
            <form action="authenticate.php" method="POST" id="loginForm">
                <input type="email" name="email" id="email" placeholder="Email Address" 
                       value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>" required>
                
                <!-- Password field with Font Awesome eye icons -->
                <div class="password-container">
                    <input type="password" name="password" id="password" placeholder="Password" required autocomplete="current-password">
                    <button type="button" class="toggle-password" id="togglePasswordBtn" onclick="togglePassword()">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                
                <button type="submit" id="loginBtn">Login</button>
            </form>
            
            <p style="text-align: center; margin-top: 1rem;">
                Don't have an account? <a href="customer/register.php" style="color: var(--secondary);">Register as Customer</a>
            </p>
        </div>
    </div>

    <script>
        // Password visibility toggle function with Font Awesome icons
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.getElementById('togglePasswordBtn');
            const icon = toggleBtn.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                toggleBtn.classList.add('visible');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                toggleBtn.classList.remove('visible');
            }
            
            passwordInput.focus();
        }
        
        function openLoginModal() {
            const modal = document.getElementById('loginModal');
            modal.classList.add('show');
            setTimeout(() => {
                document.getElementById('email').focus();
            }, 100);
        }
        
        function closeLoginModal() {
            const modal = document.getElementById('loginModal');
            modal.classList.remove('show');
            
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.getElementById('togglePasswordBtn');
            if (passwordInput && passwordInput.type === 'text') {
                passwordInput.type = 'password';
                const icon = toggleBtn.querySelector('i');
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                toggleBtn.classList.remove('visible');
            }
            
            if (window.history.pushState) {
                const url = new URL(window.location.href);
                url.searchParams.delete('error');
                url.searchParams.delete('email');
                url.searchParams.delete('show_login');
                url.searchParams.delete('logout');
                window.history.pushState({}, '', url);
            }
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('loginModal');
            if (event.target === modal) {
                closeLoginModal();
            }
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('loginModal');
                if (modal.classList.contains('show')) {
                    closeLoginModal();
                }
            }
        });
        
        <?php if($showLoginModal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            openLoginModal();
        });
        <?php endif; ?>
        
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                const email = document.getElementById('email').value;
                const password = document.getElementById('password').value;
                
                if (!email || !password) {
                    e.preventDefault();
                    alert('Please enter both email and password');
                    return false;
                }
                
                const submitBtn = document.getElementById('loginBtn');
                submitBtn.innerHTML = '⏳ Logging in...';
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.7';
            });
        }
        
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        
        function clearError() {
            const error = document.querySelector('.alert-error');
            if (error) {
                error.style.opacity = '0';
                setTimeout(() => error.remove(), 300);
            }
        }
        
        if (emailInput) emailInput.addEventListener('input', clearError);
        if (passwordInput) passwordInput.addEventListener('input', clearError);
        
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey && e.shiftKey && e.key === 'P') || 
                (e.ctrlKey && e.key === 'p' && !e.shiftKey)) {
                e.preventDefault();
                const passwordField = document.getElementById('password');
                if (passwordField && document.activeElement === passwordField) {
                    togglePassword();
                }
            }
        });
        
        let passwordTimer;
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                if (passwordInput.type === 'text') {
                    clearTimeout(passwordTimer);
                    passwordTimer = setTimeout(() => {
                        if (passwordInput.type === 'text') {
                            const toggleBtn = document.getElementById('togglePasswordBtn');
                            const icon = toggleBtn.querySelector('i');
                            passwordInput.type = 'password';
                            icon.classList.remove('fa-eye-slash');
                            icon.classList.add('fa-eye');
                            toggleBtn.classList.remove('visible');
                        }
                    }, 30000);
                }
            });
        }
    </script>
</body>
</html>