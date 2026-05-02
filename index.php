<?php
session_start();
// Database configuration file
if (file_exists('config/database.php')) {
    require_once 'config/database.php';
}

// 1. Session & Role Redirect Logic
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    $redirects = [
        'admin'     => 'admin/dashboard.php',
        'manager'   => 'manager/dashboard.php',
        'driver'    => 'driver/dashboard.php',
        'collector' => 'collector/dashboard.php',
        'customer'  => 'customer/dashboard.php'
    ];
    if (array_key_exists($role, $redirects)) {
        header('Location: ' . $redirects[$role]);
        exit;
    }
}

// 2. Logic for UI states
$showLoginModal = isset($_GET['error']) || isset($_GET['show_login']) || isset($_GET['logout']);

// 3. Image Handling
$backgroundImages = ['pe.jpg', 'wa.jpg', 'z.jpg', 'ede.jpg'];
$validImages = [];

foreach ($backgroundImages as $image) {
    if (file_exists(__DIR__ . '/' . $image)) {
        $validImages[] = $image;
    }
}

// Logo Check
$logoPath = 'logo.jpeg';
$logoExists = file_exists(__DIR__ . '/' . $logoPath);
$hasImages = count($validImages) > 0;

// Get theme preference from cookie or default to dark
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'dark';

// Favicon path - auto-detect correct path
$faviconPath = 'logo.jpeg';
if (!file_exists(__DIR__ . '/' . $faviconPath)) {
    // Try alternative favicon names
    if (file_exists(__DIR__ . '/favicon.ico')) {
        $faviconPath = 'favicon.ico';
    } elseif (file_exists(__DIR__ . '/mylogo.jpeg')) {
        $faviconPath = 'mylogo.jpeg';
    } elseif (file_exists(__DIR__ . '/assets/mylogo.jpeg')) {
        $faviconPath = 'assets/mylogo.jpeg';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMS | Smart Waste Management System</title>
    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="<?php echo $faviconPath; ?>">
    <link rel="shortcut icon" type="image/jpeg" href="<?php echo $faviconPath; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Light Mode Variables - Reduced Opacity for Less Shining */
        :root {
            --primary: #1e3a8a;
            --secondary: #fbbf24;
            --glass-bg: rgba(255, 255, 255, 0.08);
            --bg-dark: #0f172a;
            --footer-bg: #0b1120;
            --text-muted: #94a3b8;
            --teal-accent: #00c49a;
            --card-bg: rgba(255, 255, 255, 0.05);
            --border-color: rgba(255, 255, 255, 0.1);
            --input-bg: rgba(255, 255, 255, 0.1);
            --input-border: rgba(255, 255, 255, 0.2);
            --text-color: #fff;
            --navbar-bg: rgba(15, 23, 42, 0.9);
            --hero-overlay-dark: linear-gradient(135deg, rgba(15,23,42,0.7), rgba(15,23,42,0.85));
        }
        
        /* Light Mode Styles - Reduced Opacity (Less Shining) */
        body.light-mode {
            --primary: #1e3a8a;
            --secondary: #d4a017;
            --glass-bg: rgba(255, 255, 255, 0.02);
            --bg-dark: #e8eef3;
            --footer-bg: #dce3ec;
            --text-muted: #5a6e8a;
            --teal-accent: #00875a;
            --card-bg: rgba(255, 255, 255, 0.7);
            --border-color: rgba(0, 0, 0, 0.08);
            --input-bg: rgba(255, 255, 255, 0.9);
            --input-border: rgba(0, 0, 0, 0.15);
            --text-color: #2c3e50;
            --navbar-bg: rgba(255, 255, 255, 0.92);
            --hero-overlay-dark: linear-gradient(135deg, rgba(200, 210, 220, 0.5), rgba(230, 240, 250, 0.6));
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg-dark);
            color: var(--text-color);
            overflow-x: hidden;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* --- Navbar & Logo --- */
        .navbar {
            background: var(--navbar-bg);
            backdrop-filter: blur(12px);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            padding: 0.8rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.3s ease;
        }

        .logo { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            text-decoration: none; 
            color: inherit;
            transition: transform 0.3s ease;
        }
        
        .logo:hover {
            transform: translateY(-2px);
        }
        
        .logo-img {
            width: 55px;
            height: 55px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid var(--teal-accent);
            transition: all 0.3s ease;
        }
        
        .logo:hover .logo-img {
            transform: scale(1.05);
            border-color: var(--secondary);
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .logo-main { 
            font-size: 28px; 
            font-weight: 800; 
            letter-spacing: 1px;
        }
        
        .wms { 
            background: linear-gradient(135deg, var(--teal-accent), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .logo-tagline {
            font-size: 10px;
            color: var(--text-muted);
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        /* Theme Toggle Button */
        .theme-toggle {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            padding: 0.5rem 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            color: var(--text-color);
        }
        
        .theme-toggle:hover {
            background: var(--secondary);
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        .theme-toggle i {
            font-size: 1rem;
        }

        .nav-links { display: flex; gap: 2rem; align-items: center; }
        .nav-links a { 
            color: var(--text-color); 
            text-decoration: none; 
            font-weight: 500; 
            transition: 0.3s;
            position: relative;
        }
        
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--secondary);
            transition: width 0.3s ease;
        }
        
        .nav-links a:hover::after {
            width: 100%;
        }
        
        .nav-links a:hover { color: var(--secondary); }
        
        .btn-login {
            background: var(--primary);
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            border: 1px solid var(--secondary);
            color: #fff;
        }
        
        .btn-login:hover {
            background: var(--secondary);
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        .btn-login::after {
            display: none;
        }

        /* --- Hero & Background Animation --- */
        .hero {
            height: 100vh;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
        }

        .hero-bg {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: 0;
        }

        .bg-image {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 1.5s ease-in-out;
        }

        .bg-image.active { opacity: 1; }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background: var(--hero-overlay-dark);
            z-index: 1;
        }
        
        .hero-content { 
            position: relative; 
            z-index: 10; 
            padding: 0 20px;
            max-width: 800px;
            animation: fadeInUp 0.8s ease;
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
        
        .hero-content h1 { 
            font-size: clamp(2.5rem, 5vw, 4rem); 
            line-height: 1.2; 
            margin-bottom: 1.5rem;
            font-weight: 800;
        }
        
        .hero-content h4 {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            color: var(--text-muted);
            font-weight: normal;
        }
        
        .highlight { color: var(--secondary); }

        /* --- UI Components --- */
        .btn { 
            padding: 0.8rem 2rem; 
            border-radius: 50px; 
            text-decoration: none; 
            font-weight: 600; 
            transition: 0.3s; 
            display: inline-block; 
            border: none; 
            cursor: pointer; 
            font-size: 1rem;
        }
        
        .btn-primary { 
            background: var(--primary); 
            color: white; 
            border: 1px solid var(--secondary);
            box-shadow: 0 4px 15px rgba(30, 58, 138, 0.3);
        }
        
        .btn-primary:hover { 
            transform: translateY(-3px); 
            background: var(--secondary); 
            color: var(--primary);
            box-shadow: 0 8px 25px rgba(251, 191, 36, 0.4);
        }
        
        .btn-outline {
            border: 2px solid var(--secondary);
            color: var(--secondary);
            background: transparent;
        }
        
        .btn-outline:hover {
            background: var(--secondary);
            color: var(--primary);
            transform: translateY(-3px);
        }
        
        /* Features Section */
        .features { 
            padding: 5rem 5%; 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--secondary);
        }
        
        .section-title::after {
            content: '';
            display: block;
            width: 80px;
            height: 3px;
            background: var(--secondary);
            margin: 1rem auto 0;
            border-radius: 2px;
        }
        
        .feature-card { 
            background: var(--card-bg); 
            backdrop-filter: blur(8px);
            padding: 2rem; 
            border-radius: 20px; 
            border: 1px solid var(--border-color); 
            transition: 0.3s;
            text-align: center;
        }
        
        .feature-card:hover { 
            border-color: var(--secondary); 
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--secondary);
        }
        
        .feature-card p {
            color: var(--text-muted);
            line-height: 1.6;
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: inline-block;
        }
        
        /* Footer */
        .footer {
            background: var(--footer-bg);
            padding: 3rem 5% 1.5rem;
            border-top: 1px solid var(--border-color);
            margin-top: 2rem;
            transition: background 0.3s ease;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .footer-section h3 {
            color: var(--secondary);
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        
        .footer-section p, .footer-section a {
            color: var(--text-muted);
            text-decoration: none;
            display: block;
            margin-bottom: 0.5rem;
            transition: 0.3s;
        }
        
        .footer-section a:hover {
            color: var(--secondary);
            transform: translateX(5px);
        }
        
        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .social-links a {
            width: 35px;
            height: 35px;
            background: rgba(0,0,0,0.08);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.3s;
        }
        
        body.light-mode .social-links a {
            background: rgba(0,0,0,0.05);
        }
        
        .social-links a:hover {
            background: var(--secondary);
            transform: translateY(-3px);
        }
        
        .social-links a:hover i {
            color: var(--primary);
        }
        
        .copyright {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 2rem;
        }

        /* --- Login Modal --- */
        .modal {
            display: none; 
            position: fixed; 
            inset: 0; 
            background: rgba(0,0,0,0.85); 
            backdrop-filter: blur(8px);
            z-index: 2000;
            align-items: center; 
            justify-content: center;
        }
        
        body.light-mode .modal {
            background: rgba(0,0,0,0.4);
        }
        
        .modal.show { display: flex; }
        
        .modal-content {
            background: var(--card-bg); 
            backdrop-filter: blur(10px);
            padding: 2.5rem; 
            border-radius: 20px;
            width: 100%; 
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
            top: 1rem; 
            right: 1rem; 
            font-size: 1.5rem; 
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
        
        .password-container { 
            position: relative; 
            width: 100%; 
            margin-bottom: 1rem;
        }
        
        .password-container input { 
            width: 100%; 
            padding: 0.8rem; 
            border-radius: 10px; 
            border: 1px solid var(--input-border); 
            background: var(--input-bg); 
            color: var(--text-color);
            font-size: 1rem;
        }
        
        .password-container input:focus {
            outline: none;
            border-color: var(--secondary);
        }
        
        .toggle-password { 
            position: absolute; 
            right: 12px; 
            top: 50%;
            transform: translateY(-50%);
            background: none; 
            border: none; 
            color: var(--text-muted); 
            cursor: pointer;
            font-size: 1.1rem;
        }
        
        .toggle-password:hover {
            color: var(--secondary);
        }

        .alert { 
            padding: 10px; 
            border-radius: 10px; 
            margin-bottom: 15px; 
            font-size: 0.9rem; 
        }
        
        .alert-error { 
            background: rgba(239, 68, 68, 0.2); 
            color: #fca5a5; 
            border-left: 4px solid #ef4444; 
        }
        
        .alert-success {
            background: rgba(0,196,154,0.2);
            border-left: 4px solid var(--teal-accent);
            color: var(--teal-accent);
        }

        .bg-indicators {
            position: absolute; 
            bottom: 30px; 
            left: 50%; 
            transform: translateX(-50%);
            display: flex; 
            gap: 12px; 
            z-index: 20;
        }
        
        .indicator { 
            width: 10px; 
            height: 10px; 
            border-radius: 50%; 
            background: rgba(255,255,255,0.5); 
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        body.light-mode .indicator {
            background: rgba(0,0,0,0.2);
        }
        
        .indicator.active { 
            background: var(--secondary); 
            width: 25px; 
            border-radius: 5px;
        }
        
        .indicator:hover {
            background: var(--secondary);
            transform: scale(1.2);
        }
        
        /* Scroll to top button */
        .scroll-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 45px;
            height: 45px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 999;
            border: 1px solid var(--secondary);
        }
        
        .scroll-top.show {
            opacity: 1;
            visibility: visible;
        }
        
        .scroll-top:hover {
            background: var(--secondary);
            transform: translateY(-3px);
        }
        
        .scroll-top:hover i {
            color: var(--primary);
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 10px;
                padding: 0.8rem 5%;
            }
            .nav-links {
                gap: 10px;
                flex-wrap: wrap;
                justify-content: center;
            }
            .hero-content h1 { 
                font-size: 2rem;
            }
            .hero-content h4 {
                font-size: 0.9rem;
            }
            .btn-group {
                flex-direction: column;
                align-items: center;
                gap: 1rem;
            }
            .features {
                grid-template-columns: 1fr;
                padding: 3rem 1.5rem;
            }
            .logo-img {
                width: 45px;
                height: 45px;
            }
            .logo-main {
                font-size: 20px;
            }
            .logo-tagline {
                font-size: 7px;
            }
            .bg-indicators {
                bottom: 15px;
            }
            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .social-links {
                justify-content: center;
            }
            .theme-toggle span {
                display: none;
            }
        }
    </style>
</head>
<body class="<?php echo $theme == 'light' ? 'light-mode' : ''; ?>">

    <nav class="navbar">
        <a href="#home" class="logo">
            <?php if($logoExists): ?>
                <img src="logo.jpeg" alt="WMS Logo" class="logo-img">
            <?php else: ?>
                <div class="logo-img" style="background: linear-gradient(135deg, var(--teal-accent), var(--secondary)); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-recycle" style="font-size: 28px; color: #fff;"></i>
                </div>
            <?php endif; ?>
            <div class="logo-text">
                <div class="logo-main">
                    <span class="wms">WMS</span>
                </div>
                <div class="logo-tagline">For Cleaner Communities</div>
            </div>
        </a>
        <div class="nav-links">
            <a href="#home">Home</a>
            <a href="#features">Features</a>
            <a href="#about">About</a>
            <a href="#contact">Contact</a>
            <button class="theme-toggle" onclick="toggleTheme()">
                <i class="fas <?php echo $theme == 'light' ? 'fa-moon' : 'fa-sun'; ?>"></i>
                <span><?php echo $theme == 'light' ? 'Dark Mode' : 'Light Mode'; ?></span>
            </button>
            <a href="javascript:void(0)" onclick="openLoginModal()" class="btn-login">Login</a>
            <a href="customer/register.php" style="background: var(--teal-accent); padding: 0.5rem 1.5rem; border-radius: 50px;">Register</a>
        </div>
    </nav>

    <section class="hero" id="home">
        <div class="hero-bg">
            <?php if($hasImages): ?>
                <?php foreach($validImages as $index => $img): ?>
                    <div class="bg-image <?php echo $index === 0 ? 'active' : ''; ?>" 
                         style="background-image: url('<?php echo $img; ?>');"></div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-image active" style="background: linear-gradient(135deg, #1e3a8a, #00c49a);"></div>
            <?php endif; ?>
        </div>
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h1>Smart Waste Management<br><span class="highlight">For Cleaner Communities</span></h1>
            <h4>Join us in making our environment cleaner and greener. Schedule pickups, track waste, and contribute to a sustainable future.</h4>
            <div class="btn-group">
                <a href="customer/register.php" class="btn btn-primary">Register as Customer</a>
                <a href="javascript:void(0)" onclick="openLoginModal()" class="btn btn-outline">Login</a>
            </div>
        </div>

        <?php if($hasImages && count($validImages) > 1): ?>
        <div class="bg-indicators">
            <?php foreach($validImages as $index => $img): ?>
                <div class="indicator <?php echo $index === 0 ? 'active' : ''; ?>" onclick="jumpToImage(<?php echo $index; ?>)"></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    
    <h2 class="section-title">Why Choose Us?</h2>
    <section class="features" id="features">
        <div class="feature-card">
            <div class="feature-icon">🗑️</div>
            <h3>Scheduled Pickups</h3>
            <p>Schedule waste pickups at your convenience with our easy-to-use system. Never miss a collection again.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">📊</div>
            <h3>Real-time Tracking</h3>
            <p>Track your waste collection status and get instant notifications about your scheduled pickups.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">♻️</div>
            <h3>Eco-Friendly</h3>
            <p>We prioritize recycling and proper waste disposal methods to protect our environment.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">💰</div>
            <h3>Easy Payments</h3>
            <p>Secure online payments with digital receipts and comprehensive payment history.</p>
        </div>
    </section>

    <section id="about" style="background: linear-gradient(135deg, var(--footer-bg), var(--bg-dark)); padding: 4rem 5%;">
        <h2 class="section-title">About Us</h2>
        <div class="features" style="padding: 0; margin-top: 2rem;">
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
                <p>Dedicated professionals working tirelessly to ensure your waste is managed responsibly and efficiently.</p>
            </div>
        </div>
    </section>

    <footer class="footer" id="contact">
        <div class="footer-content">
            <div class="footer-section">
                <h3>WMS Management</h3>
                <p>Leading the way in sustainable waste management solutions across Rwanda.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
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
                <p><i class="fas fa-phone"></i> +250 788 123 456</p>
                <p><i class="fas fa-envelope"></i> info@wms.rw</p>
                <p><i class="fas fa-map-marker-alt"></i> Kigali, Rwanda</p>
            </div>
            <div class="footer-section">
                <h3>Working Hours</h3>
                <p>Monday - Friday: 8AM - 5PM</p>
                <p>Saturday: 9AM - 2PM</p>
                <p>Sunday: Closed</p>
            </div>
        </div>
        <div class="copyright">
            <p>&copy; 2026 Waste Management System. All rights reserved. | Designed for Cleaner Communities</p>
        </div>
    </footer>

    <div id="loginModal" class="modal <?php echo $showLoginModal ? 'show' : ''; ?>">
        <div class="modal-content">
            <span class="close-modal" onclick="closeLoginModal()">&times;</span>
            <h2>Welcome Back!</h2>
            
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

            <form action="authenticate.php" method="POST">
                <input type="email" name="email" placeholder="Email Address" required 
                       style="width: 100%; padding: 0.8rem; border-radius: 10px; border: 1px solid var(--input-border); background: var(--input-bg); color: var(--text-color); margin-bottom: 1rem;">
                
                <div class="password-container">
                    <input type="password" name="password" id="loginPass" placeholder="Password" required>
                    <button type="button" class="toggle-password" onclick="togglePass()">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Sign In</button>
            </form>
            
            <p style="text-align: center; margin-top: 1rem;">
                Don't have an account? <a href="customer/register.php" style="color: var(--secondary);">Register now</a>
            </p>
        </div>
    </div>

    <!-- Scroll to Top Button -->
    <div class="scroll-top" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
        <i class="fas fa-arrow-up"></i>
    </div>

    <script>
        // Theme Toggle Function
        function toggleTheme() {
            const body = document.body;
            const themeToggle = document.querySelector('.theme-toggle');
            const icon = themeToggle.querySelector('i');
            const span = themeToggle.querySelector('span');
            
            if (body.classList.contains('light-mode')) {
                body.classList.remove('light-mode');
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
                span.textContent = 'Light Mode';
                document.cookie = "theme=dark; path=/; max-age=" + (60*60*24*365);
            } else {
                body.classList.add('light-mode');
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
                span.textContent = 'Dark Mode';
                document.cookie = "theme=light; path=/; max-age=" + (60*60*24*365);
            }
        }
        
        // 1. Background Animation Logic
        let currentIdx = 0;
        const images = document.querySelectorAll('.bg-image');
        const indicators = document.querySelectorAll('.indicator');

        function rotateBackground() {
            if (images.length <= 1) return;
            
            images[currentIdx].classList.remove('active');
            if(indicators.length) indicators[currentIdx].classList.remove('active');
            
            currentIdx = (currentIdx + 1) % images.length;
            
            images[currentIdx].classList.add('active');
            if(indicators.length) indicators[currentIdx].classList.add('active');
        }

        function jumpToImage(idx) {
            images[currentIdx].classList.remove('active');
            if(indicators.length) indicators[currentIdx].classList.remove('active');
            currentIdx = idx;
            images[currentIdx].classList.add('active');
            if(indicators.length) indicators[currentIdx].classList.add('active');
            
            if(window.rotationInterval) {
                clearInterval(window.rotationInterval);
                window.rotationInterval = setInterval(rotateBackground, 20000);
            }
        }

        let rotationInterval = setInterval(rotateBackground, 20000);

        // 2. Modal Controls
        function openLoginModal() { 
            document.getElementById('loginModal').classList.add('show'); 
        }
        function closeLoginModal() { 
            document.getElementById('loginModal').classList.remove('show'); 
            
            if (window.history.pushState) {
                const url = new URL(window.location.href);
                url.searchParams.delete('error');
                url.searchParams.delete('email');
                url.searchParams.delete('show_login');
                url.searchParams.delete('logout');
                window.history.pushState({}, '', url);
            }
        }

        // 3. Password Toggle
        function togglePass() {
            const passInput = document.getElementById('loginPass');
            const icon = document.getElementById('eyeIcon');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        window.onclick = function(e) {
            const modal = document.getElementById('loginModal');
            if (e.target === modal) {
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
        
        window.addEventListener('scroll', function() {
            const scrollTop = document.querySelector('.scroll-top');
            if (window.pageYOffset > 300) {
                scrollTop.classList.add('show');
            } else {
                scrollTop.classList.remove('show');
            }
        });
        
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>