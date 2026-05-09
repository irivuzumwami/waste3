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

// 3. Image Handling - Background images for rotation
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

// Favicon path
$faviconPath = 'logo.jpeg';
if (!file_exists(__DIR__ . '/' . $faviconPath)) {
    if (file_exists(__DIR__ . '/favicon.ico')) {
        $faviconPath = 'favicon.ico';
    } elseif (file_exists(__DIR__ . '/mylogo.jpeg')) {
        $faviconPath = 'mylogo.jpeg';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMS | Smart ste Management System</title>
    <link rel="icon" type="image/jpeg" href="<?php echo $faviconPath; ?>">
    <link rel="shortcut icon" type="image/jpeg" href="<?php echo $faviconPath; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        /* ========================================
           PROFESSIONAL DARK MODE (Default)
        ======================================== */
        :root {
            --primary: #0f3b5e;
            --primary-dark: #0a2a44;
            --secondary: #e67e22;
            --secondary-dark: #d35400;
            --accent: #2ecc71;
            --bg-dark: #0a0e17;
            --bg-card: #1a1f2e;
            --text-primary: #ffffff;
            --text-secondary: #a0a8c0;
            --text-muted: #6c7293;
            --border-color: rgba(255,255,255,0.08);
            --glass-bg: rgba(255,255,255,0.03);
            --success: #2ecc71;
            --teal-accent: #00c49a;
            --shadow-sm: 0 4px 6px rgba(0,0,0,0.3);
            --shadow-md: 0 10px 20px rgba(0,0,0,0.2);
            --shadow-lg: 0 20px 30px rgba(0,0,0,0.2);
        }
        
        /* ========================================
           PROFESSIONAL LIGHT MODE
        ======================================== */
        body.light-mode {
            --primary: #1a4d7a;
            --primary-dark: #0f3b5e;
            --secondary: #e67e22;
            --secondary-dark: #d35400;
            --accent: #27ae60;
            --bg-dark: #f0f4f8;
            --bg-card: #ffffff;
            --text-primary: #1a2a3a;
            --text-secondary: #5a6e8a;
            --text-muted: #8a9bb0;
            --border-color: #e2edf2;
            --glass-bg: #ffffff;
            --success: #27ae60;
            --teal-accent: #00875a;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.04);
            --shadow-md: 0 8px 20px rgba(0,0,0,0.06);
            --shadow-lg: 0 15px 30px rgba(0,0,0,0.08);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            overflow-x: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            line-height: 1.5;
        }

        /* --- Premium Navbar --- */
        .navbar {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            padding: 0.8rem 6%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .logo { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            text-decoration: none; 
            color: inherit;
            transition: all 0.3s ease;
        }
        
        .logo:hover { transform: translateY(-2px); }
        
        .logo-img {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid var(--secondary);
            transition: all 0.3s ease;
        }
        
        .logo:hover .logo-img { transform: scale(1.05); }

        .logo-text { display: flex; flex-direction: column; line-height: 1.2; }
        .logo-main { font-size: 26px; font-weight: 800; letter-spacing: -0.5px; }
        
        .wms { 
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .logo-tagline { 
            font-size: 9px; 
            color: var(--text-secondary); 
            letter-spacing: 1.5px; 
            text-transform: uppercase; 
            margin-top: 2px;
            font-weight: 500;
        }

        .nav-links { display: flex; gap: 2rem; align-items: center; flex-wrap: wrap; }
        .nav-links a { 
            color: var(--text-primary); 
            text-decoration: none; 
            font-weight: 500; 
            transition: 0.3s;
            position: relative;
            font-size: 0.95rem;
        }
        
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--secondary);
            transition: width 0.3s ease;
            border-radius: 2px;
        }
        
        .nav-links a:hover::after { width: 100%; }
        .nav-links a:hover { color: var(--secondary); }

        .theme-toggle {
            background: var(--glass-bg);
            border: 1px solid var(--border-color);
            border-radius: 40px;
            padding: 0.45rem 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .theme-toggle:hover {
            background: var(--secondary);
            color: white;
            transform: translateY(-2px);
            border-color: var(--secondary);
        }

        .btn-login {
            background: var(--primary);
            padding: 0.5rem 1.5rem;
            border-radius: 40px;
            border: 1px solid var(--secondary);
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(230,126,34,0.3);
        }

        /* --- Hero Section with Animated Background --- */
        .hero {
            min-height: 100vh;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
            padding: 6rem 1rem;
        }

        /* Background image container */
        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .bg-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0;
            transition: opacity 1.5s ease-in-out;
        }

        .bg-image.active {
            opacity: 1;
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(10,14,23,0.85), rgba(10,14,23,0.7));
            z-index: 1;
        }
        
        body.light-mode .hero-overlay {
            background: linear-gradient(135deg, rgba(240,244,248,0.9), rgba(240,244,248,0.85));
        }
        
        .hero-content { 
            position: relative; 
            z-index: 10; 
            padding: 0 20px;
            max-width: 850px;
            margin: 0 auto;
            animation: fadeInUp 0.8s ease;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .hero-content h1 { 
            font-size: clamp(2.8rem, 6vw, 4.5rem); 
            line-height: 1.2; 
            margin-bottom: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        
        .hero-content p {
            font-size: 1.15rem;
            margin-bottom: 2rem;
            color: var(--text-secondary);
            max-width: 650px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }
        
        .highlight { 
            color: var(--secondary);
            position: relative;
            display: inline-block;
        }

        /* Buttons */
        .btn-group { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-top: 0.5rem; }
        
        .btn { 
            padding: 0.9rem 2.2rem; 
            border-radius: 50px; 
            text-decoration: none; 
            font-weight: 600; 
            transition: all 0.3s ease; 
            display: inline-block; 
            border: none; 
            cursor: pointer; 
            font-size: 0.95rem;
        }
        
        .btn-primary { 
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white; 
            box-shadow: var(--shadow-md);
        }
        
        .btn-primary:hover { 
            transform: translateY(-3px); 
            background: linear-gradient(135deg, var(--secondary), var(--secondary-dark));
            color: white;
            box-shadow: 0 10px 25px rgba(230,126,34,0.3);
        }
        
        .btn-outline {
            border: 2px solid var(--secondary);
            color: var(--secondary);
            background: transparent;
        }
        
        .btn-outline:hover {
            background: var(--secondary);
            color: white;
            transform: translateY(-3px);
        }

        /* Features Section */
        .features-section { padding: 5rem 6%; background: var(--glass-bg); }
        
        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .section-badge {
            display: inline-block;
            background: var(--secondary);
            color: white;
            padding: 0.3rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1rem;
        }
        
        .section-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--text-primary);
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        
        .section-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .feature-card { 
            background: var(--bg-card); 
            padding: 2rem; 
            border-radius: 24px; 
            border: 1px solid var(--border-color); 
            transition: all 0.3s ease;
            text-align: center;
            box-shadow: var(--shadow-sm);
        }
        
        .feature-card:hover { 
            transform: translateY(-8px);
            border-color: var(--secondary);
            box-shadow: var(--shadow-lg);
        }
        
        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.2rem;
            font-size: 2rem;
            color: white;
        }
        
        .feature-card h3 {
            font-size: 1.35rem;
            margin-bottom: 0.8rem;
            color: var(--secondary);
            font-weight: 600;
        }
        
        .feature-card p { color: var(--text-secondary); line-height: 1.6; font-size: 0.9rem; }

        /* Footer */
        .footer {
            background: var(--bg-card);
            padding: 3rem 6% 2rem;
            border-top: 1px solid var(--border-color);
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .footer-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1rem;
        }
        
        .footer-logo-img {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }
        
        .footer-logo-text {
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .footer-section h4 { color: var(--text-primary); margin-bottom: 1rem; font-size: 1rem; font-weight: 600; }
        .footer-section p, .footer-section a { color: var(--text-secondary); text-decoration: none; display: block; margin-bottom: 0.6rem; transition: 0.3s; font-size: 0.9rem; }
        .footer-section a:hover { color: var(--secondary); transform: translateX(5px); }
        
        .social-links { display: flex; gap: 1rem; margin-top: 1rem; }
        .social-links a {
            width: 36px;
            height: 36px;
            background: var(--glass-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.3s;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }
        .social-links a:hover { background: var(--secondary); transform: translateY(-3px); color: white; border-color: var(--secondary); }
        
        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            margin-top: 2rem;
            border-top: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        /* Modal */
        .modal {
            display: none; 
            position: fixed; 
            inset: 0; 
            background: rgba(0,0,0,0.5); 
            backdrop-filter: blur(8px);
            z-index: 2000;
            align-items: center; 
            justify-content: center;
        }
        .modal.show { display: flex; }
        
        .modal-content {
            background: var(--bg-card); 
            padding: 2rem; 
            border-radius: 28px;
            width: 100%; 
            max-width: 420px; 
            border: 1px solid var(--border-color);
            position: relative;
            animation: modalSlideIn 0.3s ease;
            box-shadow: var(--shadow-lg);
        }
        
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .close-modal { 
            position: absolute; 
            top: 1.2rem; 
            right: 1.2rem; 
            font-size: 1.3rem; 
            cursor: pointer;
            color: var(--text-secondary);
            transition: 0.3s;
        }
        .close-modal:hover { color: var(--secondary); }
        
        .modal-content h2 { margin-bottom: 1.8rem; text-align: center; color: var(--secondary); font-size: 1.8rem; font-weight: 700; }
        
        .modal-input {
            width: 100%;
            padding: 0.9rem;
            border-radius: 14px;
            border: 1px solid var(--border-color);
            background: var(--glass-bg);
            color: var(--text-primary);
            font-size: 0.95rem;
            margin-bottom: 1rem;
        }
        
        .modal-input:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        
        .password-container { position: relative; width: 100%; margin-bottom: 1rem; }
        .password-container input { width: 100%; padding: 0.9rem; padding-right: 45px; border-radius: 14px; border: 1px solid var(--border-color); background: var(--glass-bg); color: var(--text-primary); }
        .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-secondary); cursor: pointer; }
        
        .alert { padding: 0.8rem 1rem; border-radius: 12px; margin-bottom: 1rem; font-size: 0.85rem; }
        .alert-error { background: rgba(231,76,60,0.1); color: #e74c3c; border-left: 3px solid #e74c3c; }
        .alert-success { background: rgba(46,204,113,0.1); color: var(--success); border-left: 3px solid var(--success); }

        /* Background Indicators */
        .bg-indicators {
            position: absolute; 
            bottom: 30px; 
            left: 50%; 
            transform: translateX(-50%);
            display: flex; 
            gap: 10px; 
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
        
        body.light-mode .indicator { background: rgba(0,0,0,0.25); }
        .indicator.active { background: var(--secondary); width: 28px; border-radius: 5px; }
        .indicator:hover { background: var(--secondary); transform: scale(1.2); }

        /* Scroll to top */
        .scroll-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 45px;
            height: 45px;
            background: var(--secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 999;
            box-shadow: var(--shadow-md);
        }
        .scroll-top.show { opacity: 1; visibility: visible; }
        .scroll-top:hover { transform: translateY(-3px); background: var(--secondary-dark); }
        .scroll-top i { color: white; font-size: 1.2rem; }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar { flex-direction: column; gap: 12px; padding: 1rem 5%; }
            .nav-links { justify-content: center; gap: 15px; }
            .hero-content h1 { font-size: 2rem; }
            .hero-content p { font-size: 0.95rem; }
            .btn-group { flex-direction: column; align-items: center; gap: 1rem; }
            .features-grid { grid-template-columns: 1fr; padding: 0 1rem; }
            .section-title { font-size: 1.8rem; }
            .logo-img { width: 40px; height: 40px; }
            .logo-main { font-size: 20px; }
            .theme-toggle span { display: none; }
            .footer-content { grid-template-columns: 1fr; text-align: center; }
            .footer-logo { justify-content: center; }
            .social-links { justify-content: center; }
            .bg-indicators { bottom: 15px; }
        }
    </style>
</head>
<body class="<?php echo $theme == 'light' ? 'light-mode' : ''; ?>">

    <nav class="navbar">
        <a href="#home" class="logo">
            <?php if($logoExists): ?>
                <img src="logo.jpeg" alt="WMS Logo" class="logo-img">
            <?php else: ?>
                <div class="logo-img" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-recycle" style="font-size: 22px; color: #fff;"></i>
                </div>
            <?php endif; ?>
            <div class="logo-text">
                <div class="logo-main"><span class="wms">WMS</span></div>
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
            <a href="customer/register.php" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); padding: 0.5rem 1.5rem; border-radius: 40px; color: white; font-weight: 600;">Register</a>
        </div>
    </nav>

    <section class="hero" id="home">
        <div class="hero-bg">
            <?php if($hasImages): ?>
                <?php foreach($validImages as $index => $img): ?>
                    <div class="bg-image <?php echo $index === 0 ? 'active' : ''; ?>" style="background-image: url('<?php echo $img; ?>');"></div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-image active" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);"></div>
            <?php endif; ?>
        </div>
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h1>Smart Waste Management<br><span class="highlight">For Cleaner Communities</span></h1>
            <p>Join us in making our environment cleaner and greener. Schedule pickups, track waste, and contribute to a sustainable future.</p>
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
    
    <section class="features-section" id="features">
        <div class="section-header">
            <div class="section-badge">Why Choose Us</div>
            <h2 class="section-title">Exceptional Waste Management<br>Solutions</h2>
            <p class="section-subtitle">Experience the future of waste management with our innovative and eco-friendly solutions</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-calendar-check"></i></div>
                <h3>Scheduled Pickups</h3>
                <p>Schedule waste pickups at your convenience with our easy-to-use system. Never miss a collection again.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <h3>Real-time Tracking</h3>
                <p>Track your waste collection status and get instant notifications about your scheduled pickups.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-leaf"></i></div>
                <h3>Eco-Friendly</h3>
                <p>We prioritize recycling and proper waste disposal methods to protect our environment.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-credit-card"></i></div>
                <h3>Easy Payments</h3>
                <p>Secure online payments with digital receipts and comprehensive payment history.</p>
            </div>
        </div>
    </section>

    <section id="about" style="padding: 5rem 6%; background: var(--glass-bg);">
        <div class="section-header">
            <div class="section-badge">About Us</div>
            <h2 class="section-title">Committed to a Cleaner Tomorrow</h2>
            <p class="section-subtitle">Learn more about our mission, vision, and the team behind WMS</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-bullseye"></i></div>
                <h3>Our Mission</h3>
                <p>To provide efficient waste management solutions that promote environmental sustainability and community health.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-eye"></i></div>
                <h3>Our Vision</h3>
                <p>A clean, waste-free Rwanda where every community takes pride in their environment.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-users"></i></div>
                <h3>Our Team</h3>
                <p>Dedicated professionals working tirelessly to ensure your waste is managed responsibly and efficiently.</p>
            </div>
        </div>
    </section>

    <footer class="footer" id="contact">
        <div class="footer-content">
            <div class="footer-section">
                <div class="footer-logo">
                    <?php if($logoExists): ?>
                        <img src="logo.jpeg" alt="WMS Logo" class="footer-logo-img">
                    <?php else: ?>
                        <div class="footer-logo-img" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-recycle" style="font-size: 18px; color: #fff;"></i>
                        </div>
                    <?php endif; ?>
                    <span class="footer-logo-text">WMS</span>
                </div>
                <p style="color: var(--text-secondary); font-size: 0.85rem;">Leading the way in sustainable waste management solutions across Rwanda.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <a href="#home">Home</a>
                <a href="#features">Features</a>
                <a href="#about">About</a>
                <a href="customer/register.php">Register</a>
            </div>
            <div class="footer-section">
                <h4>Contact Info</h4>
                <p><i class="fas fa-phone"></i> +250 788 123 456</p>
                <p><i class="fas fa-envelope"></i> info@wms.rw</p>
                <p><i class="fas fa-map-marker-alt"></i> Kigali, Rwanda</p>
            </div>
            <div class="footer-section">
                <h4>Working Hours</h4>
                <p>Monday - Friday: 8AM - 5PM</p>
                <p>Saturday: 9AM - 2PM</p>
                <p>Sunday: Closed</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 Waste Management System. All rights reserved. | Designed for Cleaner Communities</p>
        </div>
    </footer>

    <!-- Login Modal -->
    <div id="loginModal" class="modal <?php echo $showLoginModal ? 'show' : ''; ?>">
        <div class="modal-content">
            <span class="close-modal" onclick="closeLoginModal()">&times;</span>
            <h2>Welcome Back</h2>
            <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-error">❌ Invalid email or password. Please try again.</div>
            <?php endif; ?>
            <?php if(isset($_GET['success']) && $_GET['success'] == 'registered'): ?>
                <div class="alert alert-success">✅ Registration successful! Please login to continue.</div>
            <?php endif; ?>
            <?php if(isset($_GET['logout']) && $_GET['logout'] == 'success'): ?>
                <div class="alert alert-success">✅ You have been successfully logged out.</div>
            <?php endif; ?>
            <form action="authenticate.php" method="POST">
                <input type="email" name="email" placeholder="Email Address" class="modal-input" required>
                <div class="password-container">
                    <input type="password" name="password" id="loginPass" placeholder="Password" required>
                    <button type="button" class="toggle-password" onclick="togglePass()"><i class="fas fa-eye" id="eyeIcon"></i></button>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">Sign In</button>
            </form>
            <p style="text-align: center; margin-top: 1.5rem; color: var(--text-secondary); font-size: 0.85rem;">Don't have an account? <a href="customer/register.php" style="color: var(--secondary); font-weight: 600;">Register now</a></p>
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
        
        // Background Animation Logic - CORRECTED
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

        // Start rotation only if there are images
        let rotationInterval = null;
        if (images.length > 0) {
            rotationInterval = setInterval(rotateBackground, 20000);
        }

        // Modal Controls
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

        // Password Toggle
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

        // Close modal on outside click
        window.onclick = function(e) { 
            const modal = document.getElementById('loginModal'); 
            if (e.target === modal) closeLoginModal(); 
        }
        
        // Close modal with ESC key
        document.addEventListener('keydown', function(e) { 
            if (e.key === 'Escape') { 
                const modal = document.getElementById('loginModal'); 
                if (modal.classList.contains('show')) closeLoginModal(); 
            } 
        });
        
        // Auto-show modal if there's an error
        <?php if($showLoginModal): ?>
        document.addEventListener('DOMContentLoaded', function() { 
            openLoginModal(); 
        });
        <?php endif; ?>
        
        // Scroll to top button
        window.addEventListener('scroll', function() {
            const scrollTop = document.querySelector('.scroll-top');
            if (window.pageYOffset > 300) scrollTop.classList.add('show');
            else scrollTop.classList.remove('show');
        });
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
        
        // Debug: Log images found
        console.log('Background images found:', images.length);
    </script>
</body>
</html>