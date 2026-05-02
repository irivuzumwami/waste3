<?php
// customer/dashboard.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header('Location: ../index.php');
    exit;
}
require_once '../config/database.php';

$customer_id = $_SESSION['user_id'];

// Get customer details
$stmt = $pdo->prepare("SELECT * FROM customer WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

// Get customer orders
$stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC");
$stmt->execute([$customer_id]);
$orders = $stmt->fetchAll();

// Get customer payments
$stmt = $pdo->prepare("SELECT * FROM payment WHERE customer_id = ? ORDER BY id DESC");
$stmt->execute([$customer_id]);
$payments = $stmt->fetchAll();

// Get upcoming pickups (next 7 days)
$stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? AND pickup_date >= CURDATE() ORDER BY pickup_date ASC LIMIT 5");
$stmt->execute([$customer_id]);
$upcoming_pickups = $stmt->fetchAll();

// Get order statistics
$total_orders = count($orders);
$total_paid = 0;
foreach ($payments as $payment) {
    if ($payment['payment_date'] && $payment['payment_date'] != '0000-00-00') {
        $total_paid += $payment['amount'];
    }
}
$pending_payments = 0;
foreach ($payments as $payment) {
    if (!$payment['payment_date'] || $payment['payment_date'] == '0000-00-00') {
        $pending_payments += $payment['amount'];
    }
}

// Handle schedule pickup with waste type
if (isset($_POST['schedule_pickup'])) {
    $pickup_date = $_POST['pickup_date'];
    $waste_type = $_POST['waste_type'];
    
    $amount = 5000;
    switch($waste_type) {
        case 'General Waste': $amount = 5000; break;
        case 'Recyclable Waste': $amount = 4000; break;
        case 'Organic Waste': $amount = 3000; break;
        case 'Electronic Waste': $amount = 10000; break;
        case 'Hazardous Waste': $amount = 15000; break;
        default: $amount = 5000;
    }
    
    if ($pickup_date >= date('Y-m-d')) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO orders (customer_id, pickup_date, waste_type) VALUES (?, ?, ?)");
            $stmt->execute([$customer_id, $pickup_date, $waste_type]);
            $order_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO payment (order_id, customer_id, amount, payment_date) VALUES (?, ?, ?, '0000-00-00')");
            $stmt->execute([$order_id, $customer_id, $amount]);
            $pdo->commit();
            $success = "Pickup scheduled successfully for " . date('F j, Y', strtotime($pickup_date));
            header("Location: dashboard.php?success=scheduled");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Failed to schedule pickup. Please try again.";
        }
    } else {
        $error = "Pickup date must be today or in the future.";
    }
}

// Handle support ticket
if (isset($_POST['submit_support'])) {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $inquiry_type = $_POST['inquiry_type'];
    $message = $_POST['message'];
    
    $stmt = $pdo->prepare("INSERT INTO support (fullname, email, inqirytype, message) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$fullname, $email, $inquiry_type, $message])) {
        $support_success = "Support ticket submitted successfully! We'll respond within 24 hours.";
    } else {
        $support_error = "Failed to submit support ticket. Please try again.";
    }
}

// Handle profile update
if (isset($_POST['update_profile'])) {
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $phone = $_POST['phone'];
    $housenumber = $_POST['housenumber'];
    $province = $_POST['province'];
    $district = $_POST['district'];
    $sector = $_POST['sector'];
    $cell = $_POST['cell'];
    $village = $_POST['village'];
    
    $stmt = $pdo->prepare("UPDATE customer SET firstname=?, lastname=?, phone=?, housenumber=?, province=?, district=?, sector=?, cell=?, village=? WHERE id=?");
    if ($stmt->execute([$firstname, $lastname, $phone, $housenumber, $province, $district, $sector, $cell, $village, $customer_id])) {
        $profile_success = "Profile updated successfully!";
        $stmt = $pdo->prepare("SELECT * FROM customer WHERE id = ?");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();
    } else {
        $profile_error = "Failed to update profile. Please try again.";
    }
}

// Check if logo exists
$logoExists = file_exists(__DIR__ . '/../logo.jpeg');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - WMS</title>
    <!-- Favicon - WMS Logo instead of XAMPP -->
    <link rel="icon" type="image/jpeg" href="../logo.jpeg">
    <link rel="shortcut icon" type="image/jpeg" href="../logo.jpeg">
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
            --sidebar-width: 280px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-dark);
            color: #fff;
            overflow-x: hidden;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100%;
            background: rgba(11, 17, 32, 0.98);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
            z-index: 100;
            overflow-y: auto;
        }
        
        /* Logo Styles */
        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .logo-container:hover {
            background: rgba(255,255,255,0.05);
        }
        
        .logo-img {
            width: 45px;
            height: 45px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid var(--teal-accent);
            transition: all 0.3s ease;
        }
        
        .logo-container:hover .logo-img {
            transform: scale(1.05);
            border-color: var(--secondary);
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        
        .logo-main {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 1px;
        }
        
        .logo-main .wms {
            background: linear-gradient(135deg, var(--teal-accent), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .logo-tagline {
            font-size: 8px;
            color: var(--text-muted);
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 2px;
        }
        
        .user-info {
            text-align: center;
            padding: 2rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--teal-accent));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            font-weight: bold;
            border: 3px solid var(--secondary);
        }
        
        .user-name {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.3rem;
        }
        
        .user-email {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .user-role {
            display: inline-block;
            background: var(--primary);
            padding: 0.2rem 0.8rem;
            border-radius: 20px;
            font-size: 0.7rem;
            margin-top: 0.5rem;
        }
        
        .nav-menu {
            list-style: none;
            padding: 1rem 0;
        }
        
        .nav-menu li {
            margin-bottom: 0.5rem;
        }
        
        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.8rem 1.5rem;
            color: #fff;
            text-decoration: none;
            transition: 0.3s;
            border-left: 3px solid transparent;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-weight: 500;
        }
        
        .nav-menu a:hover {
            background: rgba(30, 58, 138, 0.4);
            border-left-color: var(--secondary);
            color: var(--secondary);
            transform: translateX(5px);
        }
        
        .nav-menu a.active {
            background: rgba(30, 58, 138, 0.5);
            border-left-color: var(--secondary);
            color: var(--secondary);
        }
        
        .nav-menu i {
            width: 22px;
            font-size: 1.1rem;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .header h1 {
            font-size: 1.8rem;
            color: var(--secondary);
        }
        
        .logout-btn {
            background: rgba(255,68,68,0.2);
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            color: #ff8888;
            text-decoration: none;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .logout-btn:hover {
            background: rgba(255,68,68,0.3);
            color: #ff4444;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.05);
            padding: 1.5rem;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--secondary);
        }
        
        .stat-card h3 {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--secondary);
        }
        
        .stat-icon {
            float: right;
            font-size: 2rem;
            color: var(--text-muted);
        }
        
        /* Section Cards */
        .section-card {
            background: rgba(255,255,255,0.05);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .section-title {
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            color: var(--secondary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            background: rgba(255,255,255,0.1);
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        
        .form-group input:hover, 
        .form-group select:hover, 
        .form-group textarea:hover {
            border-color: var(--secondary);
            background: rgba(255,255,255,0.15);
        }
        
        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.2);
            background: rgba(255,255,255,0.2);
        }
        
        /* Enhanced Select Dropdown */
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
            background: #1a1a2e;
            color: #fff;
            padding: 12px;
            font-size: 1rem;
        }
        
        /* Waste Type Select Colors */
        #waste_type option[value="General Waste"] { color: #fbbf24; background: #1a1a2e; font-weight: bold; }
        #waste_type option[value="Recyclable Waste"] { color: #00c49a; background: #1a1a2e; font-weight: bold; }
        #waste_type option[value="Organic Waste"] { color: #2ecc71; background: #1a1a2e; font-weight: bold; }
        #waste_type option[value="Electronic Waste"] { color: #e74c3c; background: #1a1a2e; font-weight: bold; }
        #waste_type option[value="Hazardous Waste"] { color: #e74c3c; background: #1a1a2e; font-weight: bold; }
        
        /* Province Select Colors */
        select[name="province"] option[value="Kigali"] { color: #fbbf24; background: #1a1a2e; }
        select[name="province"] option[value="Northern"] { color: #00c49a; background: #1a1a2e; }
        select[name="province"] option[value="Southern"] { color: #2ecc71; background: #1a1a2e; }
        select[name="province"] option[value="Eastern"] { color: #3498db; background: #1a1a2e; }
        select[name="province"] option[value="Western"] { color: #e74c3c; background: #1a1a2e; }
        
        /* Inquiry Type Select Colors */
        select[name="inquiry_type"] option[value="inquiry"] { color: #fbbf24; background: #1a1a2e; }
        select[name="inquiry_type"] option[value="complaint"] { color: #e74c3c; background: #1a1a2e; }
        select[name="inquiry_type"] option[value="feedback"] { color: #2ecc71; background: #1a1a2e; }
        select[name="inquiry_type"] option[value="billing"] { color: #00c49a; background: #1a1a2e; }
        select[name="inquiry_type"] option[value="pickup"] { color: #3498db; background: #1a1a2e; }
        
        /* Waste Type Badge Styles */
        .waste-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .waste-general { background: rgba(30, 58, 138, 0.3); color: var(--secondary); border-left: 3px solid var(--secondary); }
        .waste-recyclable { background: rgba(0, 196, 154, 0.2); color: var(--teal-accent); border-left: 3px solid var(--teal-accent); }
        .waste-organic { background: rgba(46, 204, 113, 0.2); color: #2ecc71; border-left: 3px solid #2ecc71; }
        .waste-electronic { background: rgba(231, 76, 60, 0.2); color: #e74c3c; border-left: 3px solid #e74c3c; }
        .waste-hazardous { background: rgba(231, 76, 60, 0.3); color: #e74c3c; border-left: 3px solid #e74c3c; }
        
        /* Tables */
        .table-responsive {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        th {
            color: var(--secondary);
            font-weight: 600;
        }
        
        .status-badge {
            padding: 0.2rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-block;
        }
        .status-paid { background: rgba(0,196,154,0.2); color: var(--teal-accent); }
        .status-pending { background: rgba(255,193,7,0.2); color: #ffc107; }
        .status-scheduled { background: rgba(30,58,138,0.2); color: var(--secondary); }
        
        /* Buttons */
        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
            transition: 0.3s;
        }
        .btn-primary {
            background: var(--primary);
            color: #fff;
            border: 1px solid var(--secondary);
        }
        .btn-primary:hover {
            background: var(--secondary);
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            animation: slideIn 0.3s ease;
        }
        .alert-success { background: rgba(0,196,154,0.2); border: 1px solid var(--teal-accent); color: var(--teal-accent); }
        .alert-error { background: rgba(255,68,68,0.2); border: 1px solid #ff4444; color: #ff8888; }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Amount Display */
        .amount-display {
            background: rgba(0,196,154,0.15);
            padding: 0.5rem;
            border-radius: 8px;
            margin-top: 0.5rem;
            text-align: center;
            font-weight: bold;
            color: var(--teal-accent);
        }
        
        small {
            color: var(--text-muted);
            display: block;
            margin-top: 0.3rem;
            font-size: 0.7rem;
        }
        
        /* Footer with Logo */
        .footer {
            margin-top: 3rem;
            padding: 2rem;
            text-align: center;
            border-top: 1px solid rgba(255,255,255,0.1);
            background: rgba(11, 17, 32, 0.5);
        }
        
        .footer-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 1rem;
        }
        
        .footer-logo-img {
            width: 35px;
            height: 35px;
            border-radius: 8px;
        }
        
        .footer-logo-text {
            font-size: 18px;
            font-weight: bold;
            background: linear-gradient(135deg, var(--teal-accent), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .footer p {
            color: var(--text-muted);
            font-size: 0.8rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .row {
                grid-template-columns: 1fr;
            }
            .menu-toggle {
                display: block;
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 1000;
                background: var(--primary);
                padding: 0.5rem;
                border-radius: 10px;
                cursor: pointer;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        .menu-toggle { display: none; }
    </style>
</head>
<body>
    <div class="menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </div>
    
    <div class="sidebar" id="sidebar">
        <a href="../index.php" class="logo-container">
            <?php if($logoExists): ?>
                <img src="../logo.jpeg" alt="WMS Logo" class="logo-img" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'45\' height=\'45\' viewBox=\'0 0 100 100\'%3E%3Crect width=\'100\' height=\'100\' fill=\'%231e3a8a\' rx=\'12\'/%3E%3Ctext x=\'50\' y=\'70\' font-size=\'48\' text-anchor=\'middle\' fill=\'%23fbbf24\'%3EW%3C/text%3E%3C/svg%3E'">
            <?php else: ?>
                <div class="logo-img" style="background: linear-gradient(135deg, var(--teal-accent), var(--secondary)); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-recycle" style="font-size: 24px; color: #fff;"></i>
                </div>
            <?php endif; ?>
            <div class="logo-text">
                <div class="logo-main"><span class="wms">WMS</span></div>
                <div class="logo-tagline">For Cleaner Communities</div>
            </div>
        </a>
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($customer['firstname'], 0, 1) . substr($customer['lastname'], 0, 1)); ?>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($customer['firstname'] . ' ' . $customer['lastname']); ?></div>
            <div class="user-email"><?php echo htmlspecialchars($customer['email']); ?></div>
            <div class="user-role"><i class="fas fa-user"></i> Customer</div>
        </div>
        <ul class="nav-menu">
            <li><a onclick="showTab('dashboard')" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a onclick="showTab('schedule')"><i class="fas fa-calendar-plus"></i> Schedule Pickup</a></li>
            <li><a onclick="showTab('orders')"><i class="fas fa-truck"></i> My Orders</a></li>
            <li><a onclick="showTab('payments')"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
            <li><a onclick="showTab('profile')"><i class="fas fa-user"></i> Profile</a></li>
            <li><a onclick="showTab('support')"><i class="fas fa-headset"></i> Support</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-recycle"></i> Welcome, <?php echo htmlspecialchars($customer['firstname']); ?>!</h1>
            <a href="../logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
        
        <?php if(isset($_GET['success']) && $_GET['success'] == 'scheduled'): ?>
            <div class="alert alert-success">✅ Pickup scheduled successfully!</div>
            <script>setTimeout(() => document.querySelector('.alert')?.remove(), 3000);</script>
        <?php endif; ?>
        
        <!-- Dashboard Tab -->
        <div id="dashboard-tab" class="tab-content active">
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-truck"></i></div><h3>Total Orders</h3><div class="stat-number"><?php echo $total_orders; ?></div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><h3>Total Paid</h3><div class="stat-number">RWF <?php echo number_format($total_paid); ?></div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-clock"></i></div><h3>Pending Payments</h3><div class="stat-number">RWF <?php echo number_format($pending_payments); ?></div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-calendar"></i></div><h3>Upcoming Pickups</h3><div class="stat-number"><?php echo count($upcoming_pickups); ?></div></div>
            </div>
            
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-calendar-week"></i> Upcoming Pickups</h3>
                <?php if(count($upcoming_pickups) > 0): ?>
                    <div class="table-responsive"><tr><thead></tr><th>Order ID</th><th>Pickup Date</th><th>Waste Type</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody><?php foreach($upcoming_pickups as $order): ?><tr>
                        <td>#<?php echo $order['id']; ?></td>
                        <td><?php echo date('F j, Y', strtotime($order['pickup_date'])); ?></td>
                        <td><?php $waste_type = isset($order['waste_type']) ? $order['waste_type'] : 'General Waste'; $type_class = strpos($waste_type, 'General') !== false ? 'waste-general' : (strpos($waste_type, 'Recyclable') !== false ? 'waste-recyclable' : (strpos($waste_type, 'Organic') !== false ? 'waste-organic' : 'waste-general')); ?><span class="waste-badge <?php echo $type_class; ?>"><?php echo $waste_type; ?></span></td>
                        <td><span class="status-badge status-scheduled">Scheduled</span></td>
                        <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                    </tr><?php endforeach; ?></tbody></table></div>
                <?php else: ?>
                    <p style="text-align: center; padding: 2rem;"><i class="fas fa-calendar-day"></i> No upcoming pickups. <a href="#" onclick="showTab('schedule'); return false;" style="color: var(--secondary);">Schedule one now!</a></p>
                <?php endif; ?>
            </div>
            
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-history"></i> Recent Orders</h3>
                <?php if(count($orders) > 0): ?>
                    <div class="table-responsive"><table><thead></td><th>Order ID</th><th>Pickup Date</th><th>Waste Type</th><th>Created</th></tr></thead>
                    <tbody><?php foreach(array_slice($orders, 0, 5) as $order): ?><tr>
                        <td>#<?php echo $order['id']; ?></td>
                        <td><?php echo date('F j, Y', strtotime($order['pickup_date'])); ?></td>
                        <td><?php $waste_type = isset($order['waste_type']) ? $order['waste_type'] : 'General Waste'; $type_class = strpos($waste_type, 'General') !== false ? 'waste-general' : (strpos($waste_type, 'Recyclable') !== false ? 'waste-recyclable' : 'waste-general'); ?><span class="waste-badge <?php echo $type_class; ?>"><?php echo $waste_type; ?></span></td>
                        <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                    </tr><?php endforeach; ?></tbody></table></div>
                <?php else: ?>
                    <p style="text-align: center; padding: 2rem;"><i class="fas fa-box-open"></i> No orders yet. <a href="#" onclick="showTab('schedule'); return false;" style="color: var(--secondary);">Schedule your first pickup!</a></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Schedule Pickup Tab -->
        <div id="schedule-tab" class="tab-content">
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-calendar-plus"></i> Schedule New Pickup</h3>
                <?php if(isset($error)): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
                <form method="POST" id="scheduleForm">
                    <div class="form-group"><label><i class="fas fa-calendar"></i> Select Pickup Date *</label>
                        <input type="date" name="pickup_date" min="<?php echo date('Y-m-d'); ?>" required>
                        <small>Pickup is available Monday - Saturday</small>
                    </div>
                    <div class="form-group"><label><i class="fas fa-trash-alt"></i> Waste Type *</label>
                        <select name="waste_type" id="waste_type" required onchange="updateAmount()">
                            <option value="General Waste" style="color: #fbbf24;">🏠 General Waste - RWF 5,000</option>
                            <option value="Recyclable Waste" style="color: #00c49a;">♻️ Recyclable Waste - RWF 4,000</option>
                            <option value="Organic Waste" style="color: #2ecc71;">🌿 Organic Waste - RWF 3,000</option>
                            <option value="Electronic Waste" style="color: #e74c3c;">💻 Electronic Waste - RWF 10,000</option>
                            <option value="Hazardous Waste" style="color: #e74c3c;">⚠️ Hazardous Waste - RWF 15,000</option>
                        </select>
                    </div>
                    <div class="amount-display" id="amountDisplay"><i class="fas fa-money-bill-wave"></i> Amount to pay: RWF 5,000</div>
                    <div style="background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 10px; margin-top: 1rem;">
                        <div id="wasteInfo"><i class="fas fa-info-circle"></i> <strong>General Waste:</strong> Household trash, non-recyclable items.</div>
                    </div>
                    <button type="submit" name="schedule_pickup" class="btn btn-primary" style="width: 100%; margin-top: 1rem;"><i class="fas fa-calendar-check"></i> Schedule Pickup</button>
                </form>
            </div>
            <div class="section-card"><h3 class="section-title"><i class="fas fa-info-circle"></i> Pickup Information</h3>
                <ul><li><i class="fas fa-check-circle" style="color: var(--teal-accent);"></i> Pickups are scheduled between 8:00 AM - 5:00 PM</li>
                <li><i class="fas fa-check-circle" style="color: var(--teal-accent);"></i> Please place bins outside by 7:00 AM on pickup day</li>
                <li><i class="fas fa-check-circle" style="color: var(--teal-accent);"></i> Payment is collected at time of pickup</li>
                <li><i class="fas fa-check-circle" style="color: var(--teal-accent);"></i> Different waste types have different rates</li></ul>
            </div>
        </div>
        
        <!-- Orders Tab -->
        <div id="orders-tab" class="tab-content">
            <div class="section-card"><h3 class="section-title"><i class="fas fa-truck"></i> All Orders</h3>
                <?php if(count($orders) > 0): ?>
                    <div class="table-responsive"><table><thead><tr><th>Order ID</th><th>Pickup Date</th><th>Waste Type</th><th>Status</th><th>Created At</th></tr></thead>
                    <tbody><?php foreach($orders as $order): ?><tr>
                        <td>#<?php echo $order['id']; ?></td>
                        <td><?php echo date('F j, Y', strtotime($order['pickup_date'])); ?></td>
                        <td><?php $waste_type = isset($order['waste_type']) ? $order['waste_type'] : 'General Waste'; $type_class = strpos($waste_type, 'General') !== false ? 'waste-general' : (strpos($waste_type, 'Recyclable') !== false ? 'waste-recyclable' : (strpos($waste_type, 'Organic') !== false ? 'waste-organic' : 'waste-general')); ?><span class="waste-badge <?php echo $type_class; ?>"><?php echo $waste_type; ?></span></td>
                        <td><span class="status-badge status-scheduled">Scheduled</span></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></td>
                    </tr><?php endforeach; ?></tbody></table></div>
                <?php else: ?><p style="text-align: center; padding: 2rem;"><i class="fas fa-box-open"></i> No orders found. <a href="#" onclick="showTab('schedule'); return false;" style="color: var(--secondary);">Schedule your first pickup!</a></p><?php endif; ?>
            </div>
        </div>
        
        <!-- Payments Tab -->
        <div id="payments-tab" class="tab-content">
            <div class="section-card"><h3 class="section-title"><i class="fas fa-money-bill-wave"></i> Payment History</h3>
                <?php if(count($payments) > 0): ?>
                    <div class="table-responsive"><table><thead><tr><th>Payment ID</th><th>Order ID</th><th>Amount</th><th>Payment Date</th><th>Status</th></tr></thead>
                    <tbody><?php foreach($payments as $payment): ?><tr>
                        <td>#<?php echo $payment['id']; ?></td>
                        <td>#<?php echo $payment['order_id']; ?></td>
                        <td>RWF <?php echo number_format($payment['amount']); ?></td>
                        <td><?php echo ($payment['payment_date'] && $payment['payment_date'] != '0000-00-00') ? date('F j, Y', strtotime($payment['payment_date'])) : '<span style="color: #ffc107;">Pending</span>'; ?></td>
                        <td><?php if($payment['payment_date'] && $payment['payment_date'] != '0000-00-00'): ?><span class="status-badge status-paid">Paid</span><?php else: ?><span class="status-badge status-pending">Pending</span><?php endif; ?></td>
                    </tr><?php endforeach; ?></tbody></table></div>
                <?php else: ?><p style="text-align: center; padding: 2rem;"><i class="fas fa-receipt"></i> No payment records found</p><?php endif; ?>
            </div>
        </div>
        
        <!-- Profile Tab -->
        <div id="profile-tab" class="tab-content">
            <div class="section-card"><h3 class="section-title"><i class="fas fa-user-edit"></i> Edit Profile</h3>
                <?php if(isset($profile_success)): ?><div class="alert alert-success"><?php echo $profile_success; ?></div><?php endif; ?>
                <?php if(isset($profile_error)): ?><div class="alert alert-error"><?php echo $profile_error; ?></div><?php endif; ?>
                <form method="POST">
                    <div class="row">
                        <div class="form-group"><label><i class="fas fa-user"></i> First Name</label>
                            <input type="text" name="firstname" value="<?php echo htmlspecialchars($customer['firstname']); ?>" placeholder="Enter first name" required></div>
                        <div class="form-group"><label><i class="fas fa-user"></i> Last Name</label>
                            <input type="text" name="lastname" value="<?php echo htmlspecialchars($customer['lastname']); ?>" placeholder="Enter last name" required></div>
                    </div>
                    <div class="form-group"><label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" value="<?php echo htmlspecialchars($customer['email']); ?>" disabled>
                        <small>Email cannot be changed</small></div>
                    <div class="form-group"><label><i class="fas fa-phone"></i> Phone</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($customer['phone']); ?>" placeholder="Enter phone number" required></div>
                    <div class="form-group"><label><i class="fas fa-home"></i> House Number</label>
                        <input type="text" name="housenumber" value="<?php echo htmlspecialchars($customer['housenumber']); ?>" placeholder="Enter house number" required></div>
                    <div class="row">
                        <div class="form-group"><label><i class="fas fa-map-marker-alt"></i> Province</label>
                            <select name="province" required>
                                <option value="Kigali" <?php echo $customer['province'] == 'Kigali' ? 'selected' : ''; ?> style="color: #fbbf24;">🏙️ Kigali</option>
                                <option value="Northern" <?php echo $customer['province'] == 'Northern' ? 'selected' : ''; ?> style="color: #00c49a;">⛰️ Northern</option>
                                <option value="Southern" <?php echo $customer['province'] == 'Southern' ? 'selected' : ''; ?> style="color: #2ecc71;">🌄 Southern</option>
                                <option value="Eastern" <?php echo $customer['province'] == 'Eastern' ? 'selected' : ''; ?> style="color: #3498db;">🌅 Eastern</option>
                                <option value="Western" <?php echo $customer['province'] == 'Western' ? 'selected' : ''; ?> style="color: #e74c3c;">🏞️ Western</option>
                            </select>
                        </div>
                        <div class="form-group"><label><i class="fas fa-city"></i> District</label>
                            <input type="text" name="district" value="<?php echo htmlspecialchars($customer['district']); ?>" placeholder="Enter district" required></div>
                    </div>
                    <div class="row">
                        <div class="form-group"><label><i class="fas fa-building"></i> Sector</label>
                            <input type="text" name="sector" value="<?php echo htmlspecialchars($customer['sector']); ?>" placeholder="Enter sector" required></div>
                        <div class="form-group"><label><i class="fas fa-location-dot"></i> Cell</label>
                            <input type="text" name="cell" value="<?php echo htmlspecialchars($customer['cell']); ?>" placeholder="Enter cell" required></div>
                    </div>
                    <div class="form-group"><label><i class="fas fa-tree"></i> Village</label>
                        <input type="text" name="village" value="<?php echo htmlspecialchars($customer['village']); ?>" placeholder="Enter village" required></div>
                    <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save"></i> Update Profile</button>
                </form>
            </div>
        </div>
        
        <!-- Support Tab -->
        <div id="support-tab" class="tab-content">
            <div class="section-card"><h3 class="section-title"><i class="fas fa-headset"></i> Contact Support</h3>
                <?php if(isset($support_success)): ?><div class="alert alert-success"><?php echo $support_success; ?></div><?php endif; ?>
                <?php if(isset($support_error)): ?><div class="alert alert-error"><?php echo $support_error; ?></div><?php endif; ?>
                <form method="POST">
                    <div class="form-group"><label><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" name="fullname" value="<?php echo htmlspecialchars($customer['firstname'] . ' ' . $customer['lastname']); ?>" placeholder="Enter full name" required></div>
                    <div class="form-group"><label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>" placeholder="Enter email" required></div>
                    <div class="form-group"><label><i class="fas fa-question-circle"></i> Inquiry Type</label>
                        <select name="inquiry_type" required>
                            <option value="inquiry" style="color: #fbbf24;">📝 General Inquiry</option>
                            <option value="complaint" style="color: #e74c3c;">⚠️ Complaint</option>
                            <option value="feedback" style="color: #2ecc71;">💡 Feedback</option>
                            <option value="billing" style="color: #00c49a;">💰 Billing Question</option>
                            <option value="pickup" style="color: #3498db;">🚛 Pickup Issue</option>
                        </select>
                    </div>
                    <div class="form-group"><label><i class="fas fa-comment"></i> Message</label>
                        <textarea name="message" rows="5" required placeholder="Describe your issue in detail..."></textarea></div>
                    <button type="submit" name="submit_support" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Ticket</button>
                </form>
            </div>
            <div class="section-card"><h3 class="section-title"><i class="fas fa-phone-alt"></i> Contact Information</h3>
                <ul><li><i class="fas fa-phone"></i> <strong>Phone:</strong> +250 788 123 456</li>
                <li><i class="fas fa-envelope"></i> <strong>Email:</strong> support@wms.rw</li>
                <li><i class="fas fa-clock"></i> <strong>Hours:</strong> Mon-Fri 8:00 AM - 5:00 PM</li>
                <li><i class="fas fa-map-marker-alt"></i> <strong>Address:</strong> Kigali, Rwanda</li></ul>
            </div>
        </div>
    </div>
    
    <!-- Footer with WMS Logo -->
    <div class="footer">
        <div class="footer-logo">
            <?php if($logoExists): ?>
                <img src="../logo.jpeg" alt="WMS Logo" class="footer-logo-img">
            <?php else: ?>
                <div style="width: 35px; height: 35px; background: linear-gradient(135deg, var(--teal-accent), var(--secondary)); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-recycle" style="color: #fff; font-size: 18px;"></i>
                </div>
            <?php endif; ?>
            <span class="footer-logo-text">WMS</span>
        </div>
        <p>&copy; <?php echo date('Y'); ?> Waste Management System. All rights reserved.</p>
    </div>
    
    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            const selectedTab = document.getElementById(tabName + '-tab');
            if (selectedTab) selectedTab.classList.add('active');
            document.querySelectorAll('.nav-menu a').forEach(link => link.classList.remove('active'));
            if (event && event.currentTarget) event.currentTarget.classList.add('active');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('show'); }
        
        function updateAmount() {
            const wasteType = document.getElementById('waste_type').value;
            const amountDisplay = document.getElementById('amountDisplay');
            const wasteInfo = document.getElementById('wasteInfo');
            let amount = 0, infoHtml = '';
            switch(wasteType) {
                case 'General Waste': amount = 5000; infoHtml = '<i class="fas fa-home"></i> <strong>General Waste:</strong> Household trash, non-recyclable items.'; break;
                case 'Recyclable Waste': amount = 4000; infoHtml = '<i class="fas fa-recycle"></i> <strong>Recyclable Waste:</strong> Paper, plastic bottles, glass containers.'; break;
                case 'Organic Waste': amount = 3000; infoHtml = '<i class="fas fa-leaf"></i> <strong>Organic Waste:</strong> Food scraps, yard waste.'; break;
                case 'Electronic Waste': amount = 10000; infoHtml = '<i class="fas fa-laptop"></i> <strong>Electronic Waste:</strong> Computers, phones, batteries.'; break;
                case 'Hazardous Waste': amount = 15000; infoHtml = '<i class="fas fa-exclamation-triangle"></i> <strong>Hazardous Waste:</strong> Chemicals, paints, medical waste.'; break;
                default: amount = 5000; infoHtml = '<i class="fas fa-home"></i> <strong>General Waste:</strong> Household trash.';
            }
            amountDisplay.innerHTML = `<i class="fas fa-money-bill-wave"></i> Amount to pay: RWF ${amount.toLocaleString()}`;
            wasteInfo.innerHTML = infoHtml;
        }
        
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar'), menuToggle = document.querySelector('.menu-toggle');
            if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('show') && !sidebar.contains(event.target) && !menuToggle.contains(event.target)) sidebar.classList.remove('show');
        });
        
        setTimeout(() => { document.querySelectorAll('.alert').forEach(alert => { alert.style.opacity = '0'; setTimeout(() => alert.remove(), 300); }); }, 5000);
        document.addEventListener('DOMContentLoaded', function() { if (!document.querySelector('.tab-content.active')) showTab('dashboard'); updateAmount(); });
    </script>
</body>
</html>