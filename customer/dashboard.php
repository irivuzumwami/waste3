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

// Handle schedule pickup
if (isset($_POST['schedule_pickup'])) {
    $pickup_date = $_POST['pickup_date'];
    
    // Validate date
    if ($pickup_date >= date('Y-m-d')) {
        $stmt = $pdo->prepare("INSERT INTO orders (customer_id, pickup_date) VALUES (?, ?)");
        if ($stmt->execute([$customer_id, $pickup_date])) {
            $order_id = $pdo->lastInsertId();
            // Create payment record
            $amount = 5000; // Default waste fee amount
            $stmt = $pdo->prepare("INSERT INTO payment (order_id, customer_id, amount, payment_date) VALUES (?, ?, ?, NULL)");
            $stmt->execute([$order_id, $customer_id, $amount]);
            $success = "Pickup scheduled successfully for " . date('F j, Y', strtotime($pickup_date));
            // Refresh page to show new order
            header("Location: dashboard.php?success=scheduled");
            exit;
        } else {
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
        // Refresh customer data
        $stmt = $pdo->prepare("SELECT * FROM customer WHERE id = ?");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();
    } else {
        $profile_error = "Failed to update profile. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - EcoWaste</title>
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
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
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
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--secondary);
            text-align: center;
            padding: 2rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .logo span {
            color: var(--teal-accent);
        }
        
        .user-info {
            text-align: center;
            padding: 2rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            background: var(--primary);
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
        
        .nav-menu {
            list-style: none;
            padding: 1rem 0;
        }
        
        .nav-menu li {
            margin-bottom: 0.3rem;
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
        }
        
        .nav-menu a:hover, .nav-menu a.active {
            background: rgba(30, 58, 138, 0.3);
            border-left-color: var(--secondary);
            color: var(--secondary);
        }
        
        .nav-menu i {
            width: 20px;
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
        
        .section-title i {
            font-size: 1.3rem;
        }
        
        /* Tables */
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
        
        .status-paid {
            background: rgba(0,196,154,0.2);
            color: var(--teal-accent);
        }
        
        .status-pending {
            background: rgba(255,193,7,0.2);
            color: #ffc107;
        }
        
        .status-scheduled {
            background: rgba(30,58,138,0.2);
            color: var(--secondary);
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--primary: #ffffff;);
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            background: rgba(26, 2, 133, 0.1);
            border: 1px solid rgba(240, 227, 227, 0.7);
            border-radius: 10px;
            color: #4030ed;
            font-size: 1rem;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        
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
        }
        
        .btn-primary:hover {
            background: var(--secondary);
            color: var(--primary);
        }
        
        .btn-success {
            background: var(--teal-accent);
            color: #fff;
        }
        
        .btn-success:hover {
            background: #00a87e;
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
        
        /* Tabs */
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .tab-btn {
            background: none;
            border: none;
            padding: 0.8rem 1.5rem;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1rem;
            transition: 0.3s;
            border-bottom: 2px solid transparent;
        }
        
        .tab-btn:hover {
            color: var(--secondary);
        }
        
        .tab-btn.active {
            color: var(--secondary);
            border-bottom-color: var(--secondary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
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
        }
        
        .menu-toggle {
            display: none;
        }
    </style>
</head>
<body>
    <div class="menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </div>
    
    <div class="sidebar" id="sidebar">
        <div class="logo">Eco<span>Waste</span></div>
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($customer['firstname'], 0, 1) . substr($customer['lastname'], 0, 1)); ?>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($customer['firstname'] . ' ' . $customer['lastname']); ?></div>
            <div class="user-email"><?php echo htmlspecialchars($customer['email']); ?></div>
        </div>
        <ul class="nav-menu">
            <li><a href="#" class="active" onclick="showTab('dashboard'); return false;"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="#" onclick="showTab('schedule'); return false;"><i class="fas fa-calendar-plus"></i> Schedule Pickup</a></li>
            <li><a href="#" onclick="showTab('orders'); return false;"><i class="fas fa-truck"></i> My Orders</a></li>
            <li><a href="#" onclick="showTab('payments'); return false;"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
            <li><a href="#" onclick="showTab('profile'); return false;"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="#" onclick="showTab('support'); return false;"><i class="fas fa-headset"></i> Support</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-recycle"></i> Welcome, <?php echo htmlspecialchars($customer['firstname']); ?>!</h1>
            <a href="../logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
        
        <?php if(isset($_GET['success']) && $_GET['success'] == 'scheduled'): ?>
            <div class="alert alert-success">✅ Pickup scheduled successfully!</div>
        <?php endif; ?>
        
        <!-- Dashboard Tab -->
        <div id="dashboard-tab" class="tab-content active">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-truck"></i></div>
                    <h3>Total Orders</h3>
                    <div class="stat-number"><?php echo $total_orders; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <h3>Total Paid</h3>
                    <div class="stat-number">RWF <?php echo number_format($total_paid); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <h3>Pending Payments</h3>
                    <div class="stat-number">RWF <?php echo number_format($pending_payments); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar"></i></div>
                    <h3>Upcoming Pickups</h3>
                    <div class="stat-number"><?php echo count($upcoming_pickups); ?></div>
                </div>
            </div>
            
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-calendar-week"></i> Upcoming Pickups</h3>
                <?php if(count($upcoming_pickups) > 0): ?>
                    <table>
                        <thead>
                            <tr><th>Order ID</th><th>Pickup Date</th><th>Status</th><th>Created</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($upcoming_pickups as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo date('F j, Y', strtotime($order['pickup_date'])); ?></td>
                                <td><span class="status-badge status-scheduled">Scheduled</span></td>
                                <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; padding: 2rem; color: var(--text-muted);">
                        <i class="fas fa-calendar-day"></i> No upcoming pickups. Schedule one now!
                    </p>
                <?php endif; ?>
            </div>
            
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-history"></i> Recent Orders</h3>
                <?php if(count($orders) > 0): ?>
                    <table>
                        <thead>
                            <tr><th>Order ID</th><th>Pickup Date</th><th>Created</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach(array_slice($orders, 0, 5) as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo date('F j, Y', strtotime($order['pickup_date'])); ?></td>
                                <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; padding: 2rem; color: var(--text-muted);">
                        <i class="fas fa-box-open"></i> No orders yet
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Schedule Pickup Tab -->
        <div id="schedule-tab" class="tab-content">
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-calendar-plus"></i> Schedule New Pickup</h3>
                
                <?php if(isset($error)): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Select Pickup Date</label>
                        <input type="date" name="pickup_date" min="<?php echo date('Y-m-d'); ?>" required>
                        <small style="color: var(--text-muted);">Pickup is available Monday - Saturday</small>
                    </div>
                    <div class="form-group">
                        <label>Waste Type</label>
                        <select name="waste_type">
                            <option>General Waste (Default)</option>
                            <option>Recyclable Waste</option>
                            <option>Organic Waste</option>
                            <option>Electronic Waste</option>
                            <option>Hazardous Waste</option>
                            
                        </select>
                        <small style="color: var(--text-muted);">Default fee: RWF 5,000 per pickup</small>
                    </div>
                    <button type="submit" name="schedule_pickup" class="btn btn-primary">Schedule Pickup</button>
                </form>
            </div>
            
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-info-circle"></i> Pickup Information</h3>
                <ul style="list-style: none; padding-left: 0;">
                    <li><i class="fas fa-check-circle" style="color: var(--teal-accent);"></i> Pickups are scheduled between 8:00 AM - 5:00 PM</li>
                    <li><i class="fas fa-check-circle" style="color: var(--teal-accent);"></i> Please place bins outside by 7:00 AM on pickup day</li>
                    <li><i class="fas fa-check-circle" style="color: var(--teal-accent);"></i> Payment of RWF 5,000 is required per pickup</li>
                    <li><i class="fas fa-check-circle" style="color: var(--teal-accent);"></i> You'll receive confirmation via email</li>
                </ul>
            </div>
        </div>
        
        <!-- Orders Tab -->
        <div id="orders-tab" class="tab-content">
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-truck"></i> All Orders</h3>
                <?php if(count($orders) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr><th>Order ID</th><th>Pickup Date</th><th>Status</th><th>Created At</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo date('F j, Y', strtotime($order['pickup_date'])); ?></td>
                                    <td><span class="status-badge status-scheduled">Scheduled</span></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; padding: 2rem; color: var(--text-muted);">
                        <i class="fas fa-box-open"></i> No orders found. Schedule your first pickup!
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Payments Tab -->
        <div id="payments-tab" class="tab-content">
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-money-bill-wave"></i> Payment History</h3>
                <?php if(count($payments) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr><th>Payment ID</th><th>Order ID</th><th>Amount</th><th>Payment Date</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($payments as $payment): ?>
                                <tr>
                                    <td>#<?php echo $payment['id']; ?></td>
                                    <td>#<?php echo $payment['order_id']; ?></td>
                                    <td>RWF <?php echo number_format($payment['amount']); ?></td>
                                    <td><?php echo ($payment['payment_date'] && $payment['payment_date'] != '0000-00-00') ? date('F j, Y', strtotime($payment['payment_date'])) : '<span style="color: #ffc107;">Pending</span>'; ?></td>
                                    <td>
                                        <?php if($payment['payment_date'] && $payment['payment_date'] != '0000-00-00'): ?>
                                            <span class="status-badge status-paid">Paid</span>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; padding: 2rem; color: var(--text-muted);">
                        <i class="fas fa-receipt"></i> No payment records found
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Profile Tab -->
        <div id="profile-tab" class="tab-content">
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-user-edit"></i> Edit Profile</h3>
                
                <?php if(isset($profile_success)): ?>
                    <div class="alert alert-success"><?php echo $profile_success; ?></div>
                <?php endif; ?>
                <?php if(isset($profile_error)): ?>
                    <div class="alert alert-error"><?php echo $profile_error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="row">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="firstname" value="<?php echo htmlspecialchars($customer['firstname']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="lastname" value="<?php echo htmlspecialchars($customer['lastname']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?php echo htmlspecialchars($customer['email']); ?>" disabled>
                        <small style="color: var(--text-muted);">Email cannot be changed</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($customer['phone']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>House Number</label>
                        <input type="text" name="housenumber" value="<?php echo htmlspecialchars($customer['housenumber']); ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="form-group">
                            <label>Province</label>
                            <select name="province" required>
                                <option value="Kigali" <?php echo $customer['province'] == 'Kigali' ? 'selected' : ''; ?>>Kigali</option>
                                <option value="Northern" <?php echo $customer['province'] == 'Northern' ? 'selected' : ''; ?>>Northern</option>
                                <option value="Southern" <?php echo $customer['province'] == 'Southern' ? 'selected' : ''; ?>>Southern</option>
                                <option value="Eastern" <?php echo $customer['province'] == 'Eastern' ? 'selected' : ''; ?>>Eastern</option>
                                <option value="Western" <?php echo $customer['province'] == 'Western' ? 'selected' : ''; ?>>Western</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>District</label>
                            <input type="text" name="district" value="<?php echo htmlspecialchars($customer['district']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="form-group">
                            <label>Sector</label>
                            <input type="text" name="sector" value="<?php echo htmlspecialchars($customer['sector']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Cell</label>
                            <input type="text" name="cell" value="<?php echo htmlspecialchars($customer['cell']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Village</label>
                        <input type="text" name="village" value="<?php echo htmlspecialchars($customer['village']); ?>" required>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                </form>
            </div>
        </div>
        
        <!-- Support Tab -->
        <div id="support-tab" class="tab-content">
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-headset"></i> Contact Support</h3>
                
                <?php if(isset($support_success)): ?>
                    <div class="alert alert-success"><?php echo $support_success; ?></div>
                <?php endif; ?>
                <?php if(isset($support_error)): ?>
                    <div class="alert alert-error"><?php echo $support_error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="fullname" value="<?php echo htmlspecialchars($customer['firstname'] . ' ' . $customer['lastname']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Inquiry Type</label>
                        <select name="inquiry_type" required>
                            <option value="inquiry">General Inquiry</option>
                            <option value="complaint">Complaint</option>
                            <option value="feedback">Feedback</option>
                            <option value="billing">Billing Question</option>
                            <option value="pickup">Pickup Issue</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" rows="5" required placeholder="Describe your issue or question..."></textarea>
                    </div>
                    <button type="submit" name="submit_support" class="btn btn-primary">Submit Ticket</button>
                </form>
            </div>
            
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-phone-alt"></i> Contact Information</h3>
                <ul style="list-style: none; padding-left: 0;">
                    <li><i class="fas fa-phone"></i> <strong>Phone:</strong> +250 788 123 456</li>
                    <li><i class="fas fa-envelope"></i> <strong>Email:</strong> support@ecowaste.rw</li>
                    <li><i class="fas fa-clock"></i> <strong>Hours:</strong> Mon-Fri 8:00 AM - 5:00 PM</li>
                    <li><i class="fas fa-map-marker-alt"></i> <strong>Address:</strong> Kigali, Rwanda</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Update active menu item
            document.querySelectorAll('.nav-menu a').forEach(link => {
                link.classList.remove('active');
            });
            
            // Set active class on clicked link
            event.currentTarget.classList.add('active');
        }
        
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            if (window.innerWidth <= 768 && sidebar.classList.contains('show')) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>