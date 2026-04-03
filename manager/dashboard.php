<?php
// manager/dashboard.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager') {
    header('Location: ../index.php');
    exit;
}
require_once '../config/database.php';

$manager_id = $_SESSION['user_id'];

// Get manager details
$stmt = $pdo->prepare("SELECT * FROM workers WHERE id = ? AND role = 'manager'");
$stmt->execute([$manager_id]);
$manager = $stmt->fetch();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE DATE(pickup_date) = CURDATE()");
$todayOrders = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE DATE(pickup_date) > CURDATE()");
$upcomingOrders = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM workers WHERE role = 'driver' AND status = 'active'");
$activeDrivers = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM workers WHERE role = 'collector' AND status = 'active'");
$activeCollectors = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT SUM(amount) as total FROM payment WHERE payment_date IS NOT NULL AND payment_date != '0000-00-00'");
$totalRevenue = $stmt->fetch()['total'] ?? 0;

// Get all drivers
$stmt = $pdo->query("SELECT * FROM workers WHERE role = 'driver' ORDER BY id DESC");
$drivers = $stmt->fetchAll();

// Get all fee collectors
$stmt = $pdo->query("SELECT * FROM workers WHERE role = 'collector' ORDER BY id DESC");
$collectors = $stmt->fetchAll();

// Get all orders with customer details
$stmt = $pdo->query("
    SELECT o.*, c.firstname, c.lastname, c.phone, c.housenumber, c.village, c.sector,
           p.amount, p.payment_date
    FROM orders o 
    JOIN customer c ON o.customer_id = c.id 
    LEFT JOIN payment p ON o.id = p.order_id 
    ORDER BY o.id DESC
");
$allOrders = $stmt->fetchAll();

// Get today's pickups
$stmt = $pdo->query("
    SELECT o.*, c.firstname, c.lastname, c.phone, c.housenumber, c.village 
    FROM orders o 
    JOIN customer c ON o.customer_id = c.id 
    WHERE DATE(o.pickup_date) = CURDATE()
    ORDER BY o.pickup_date ASC
");
$todayPickups = $stmt->fetchAll();

// Get pending payments
$stmt = $pdo->query("
    SELECT p.*, c.firstname, c.lastname, c.phone 
    FROM payment p 
    JOIN customer c ON p.customer_id = c.id 
    WHERE p.payment_date IS NULL OR p.payment_date = '0000-00-00'
    ORDER BY p.id DESC
");
$pendingPayments = $stmt->fetchAll();

// Get daily report data
$stmt = $pdo->query("
    SELECT 
        DATE(o.pickup_date) as date,
        COUNT(o.id) as total_orders,
        SUM(CASE WHEN p.payment_date IS NOT NULL THEN p.amount ELSE 0 END) as collected_amount,
        SUM(CASE WHEN p.payment_date IS NULL THEN p.amount ELSE 0 END) as pending_amount,
        COUNT(CASE WHEN p.payment_date IS NOT NULL THEN 1 END) as paid_orders,
        COUNT(CASE WHEN p.payment_date IS NULL THEN 1 END) as pending_orders
    FROM orders o
    LEFT JOIN payment p ON o.id = p.order_id
    WHERE o.pickup_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(o.pickup_date)
    ORDER BY date DESC
");
$dailyReports = $stmt->fetchAll();

// Get monthly report data
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(o.pickup_date, '%Y-%m') as month,
        COUNT(o.id) as total_orders,
        SUM(CASE WHEN p.payment_date IS NOT NULL THEN p.amount ELSE 0 END) as collected_amount,
        SUM(CASE WHEN p.payment_date IS NULL THEN p.amount ELSE 0 END) as pending_amount
    FROM orders o
    LEFT JOIN payment p ON o.id = p.order_id
    WHERE o.pickup_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(o.pickup_date, '%Y-%m')
    ORDER BY month DESC
");
$monthlyReports = $stmt->fetchAll();

// Get top customers
$stmt = $pdo->query("
    SELECT 
        c.id, c.firstname, c.lastname, c.phone,
        COUNT(o.id) as total_orders,
        SUM(p.amount) as total_paid
    FROM customer c
    LEFT JOIN orders o ON c.id = o.customer_id
    LEFT JOIN payment p ON o.id = p.order_id AND p.payment_date IS NOT NULL
    GROUP BY c.id
    ORDER BY total_paid DESC
    LIMIT 10
");
$topCustomers = $stmt->fetchAll();

// Handle payment collection
if (isset($_POST['collect_payment'])) {
    $payment_id = $_POST['payment_id'];
    $stmt = $pdo->prepare("UPDATE payment SET payment_date = CURDATE(), collected_by = ? WHERE id = ?");
    if ($stmt->execute([$manager_id, $payment_id])) {
        header("Location: dashboard.php?success=payment_collected");
        exit;
    }
}

// Handle driver status update
if (isset($_POST['update_driver_status'])) {
    $driver_id = $_POST['driver_id'];
    $status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE workers SET status = ? WHERE id = ? AND role = 'driver'");
    if ($stmt->execute([$status, $driver_id])) {
        header("Location: dashboard.php?success=status_updated");
        exit;
    }
}

// Handle collector status update
if (isset($_POST['update_collector_status'])) {
    $collector_id = $_POST['collector_id'];
    $status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE workers SET status = ? WHERE id = ? AND role = 'collector'");
    if ($stmt->execute([$status, $collector_id])) {
        header("Location: dashboard.php?success=status_updated");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - WMS</title>
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
            width: 80px;
            height: 60px;
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
            cursor: pointer;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        .status-active {
            background: rgba(0,196,154,0.2);
            color: var(--teal-accent);
        }
        
        .status-inactive {
            background: rgba(255,68,68,0.2);
            color: #ff8888;
        }
        
        .status-pending {
            background: rgba(255,193,7,0.2);
            color: #ffc107;
        }
        
        .status-paid {
            background: rgba(0,196,154,0.2);
            color: var(--teal-accent);
        }
        
        .status-upcoming {
            background: rgba(30,58,138,0.2);
            color: var(--secondary);
        }
        
        .btn {
            padding: 0.4rem 0.8rem;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: 0.3s;
            cursor: pointer;
            border: none;
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
        
        .btn-sm {
            padding: 0.2rem 0.5rem;
            font-size: 0.7rem;
        }
        
        /* Enhanced Status Select Styles */
        select.status-select {
            padding: 0.4rem 0.8rem;
            border-radius: 5px;
            background: rgba(30, 58, 138, 0.3);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.3);
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        select.status-select option {
            background: var(--bg-dark);
            color: #fff;
            padding: 8px;
        }
        
        select.status-select option[value="active"] {
            background: rgba(0,196,154,0.3);
            color: var(--teal-accent);
        }
        
        select.status-select option[value="inactive"] {
            background: rgba(255,68,68,0.3);
            color: #ff8888;
        }
        
        select.status-select:hover {
            border-color: var(--secondary);
            background: rgba(30, 58, 138, 0.5);
        }
        
        select.status-select:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.2);
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
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
        
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
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
        
        /* Report Cards */
        .report-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .report-card {
            background: rgba(255,255,255,0.05);
            padding: 1rem;
            border-radius: 15px;
            text-align: center;
        }
        
        .report-card h4 {
            color: var(--text-muted);
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
        }
        
        .report-card .number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--secondary);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
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
            .tabs {
                justify-content: center;
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
        <a href="../index.php" class="logo-container">
            <img src="../logo.jpeg" alt="WMS Logo" class="logo-img" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'45\' height=\'45\' viewBox=\'0 0 100 100\'%3E%3Crect width=\'100\' height=\'100\' fill=\'%231e3a8a\' rx=\'12\'/%3E%3Ctext x=\'50\' y=\'70\' font-size=\'48\' text-anchor=\'middle\' fill=\'%23fbbf24\'%3EW%3C/text%3E%3C/svg%3E'">
            <div class="logo-text">
                <div class="logo-main">
                    <span class="wms">WMS</span>
                </div>
                <div class="logo-tagline">For Cleaner Communities</div>
            </div>
        </a>
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($manager['firstname'] . ' ' . $manager['lastname']); ?></div>
            <div class="user-email"><?php echo htmlspecialchars($manager['email']); ?></div>
            <div class="user-role"><i class="fas fa-tasks"></i> Operations Manager</div>
        </div>
        <ul class="nav-menu">
            <li><a onclick="showTab('dashboard')" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a onclick="showTab('orders')"><i class="fas fa-clipboard-list"></i> Orders</a></li>
            <li><a onclick="showTab('drivers')"><i class="fas fa-truck"></i> Drivers</a></li>
            <li><a onclick="showTab('collectors')"><i class="fas fa-money-bill-wave"></i> Fee Collectors</a></li>
            <li><a onclick="showTab('payments')"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li><a onclick="showTab('reports')"><i class="fas fa-file-alt"></i> Reports</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> Manager Dashboard</h1>
            <a href="../logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
        
        <?php if(isset($_GET['success']) && $_GET['success'] == 'payment_collected'): ?>
            <div class="alert alert-success">✅ Payment collected successfully!</div>
            <script>setTimeout(() => document.querySelector('.alert')?.remove(), 3000);</script>
        <?php endif; ?>
        
        <?php if(isset($_GET['success']) && $_GET['success'] == 'status_updated'): ?>
            <div class="alert alert-success">✅ Status updated successfully!</div>
            <script>setTimeout(() => document.querySelector('.alert')?.remove(), 3000);</script>
        <?php endif; ?>
        
        <!-- Dashboard Tab -->
        <div id="dashboard-tab" class="tab-content active">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                    <h3>Today's Pickups</h3>
                    <div class="stat-number"><?php echo $todayOrders; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
                    <h3>Upcoming Pickups</h3>
                    <div class="stat-number"><?php echo $upcomingOrders; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-truck"></i></div>
                    <h3>Active Drivers</h3>
                    <div class="stat-number"><?php echo $activeDrivers; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <h3>Fee Collectors</h3>
                    <div class="stat-number"><?php echo $activeCollectors; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                    <h3>Total Revenue</h3>
                    <div class="stat-number">RWF <?php echo number_format($totalRevenue); ?></div>
                </div>
            </div>
            
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-calendar-day"></i> Today's Pickups</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr><th>Order ID</th><th>Customer</th><th>Phone</th><th>Address</th> </tr>
                        </thead>
                        <tbody>
                            <?php foreach($todayPickups as $pickup): ?>
                            <tr>
                                <td>#<?php echo $pickup['id']; ?></td>
                                <td><?php echo htmlspecialchars($pickup['firstname'] . ' ' . $pickup['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['phone']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['housenumber'] . ', ' . $pickup['village']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(count($todayPickups) == 0): ?>
                            <tr><td colspan="4" style="text-align: center;">No pickups scheduled for today</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-clock"></i> Pending Payments</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr><th>Payment ID</th><th>Customer</th><th>Phone</th><th>Amount</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($pendingPayments as $payment): ?>
                            <tr>
                                <td>#<?php echo $payment['id']; ?></td>
                                <td><?php echo htmlspecialchars($payment['firstname'] . ' ' . $payment['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($payment['phone']); ?></td>
                                <td>RWF <?php echo number_format($payment['amount']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                        <button type="submit" name="collect_payment" class="btn btn-success" onclick="return confirm('Mark this payment as collected?')">
                                            <i class="fas fa-check"></i> Collect
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(count($pendingPayments) == 0): ?>
                            <tr><td colspan="5" style="text-align: center;">No pending payments</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Orders Tab -->
        <div id="orders-tab" class="tab-content">
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-clipboard-list"></i> All Orders</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th><th>Customer</th><th>Phone</th><th>Pickup Date</th>
                                <th>Address</th><th>Amount</th><th>Payment Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($allOrders as $order): 
                                $payment_status = ($order['payment_date'] && $order['payment_date'] != '0000-00-00') ? 'Paid' : 'Pending';
                                $payment_class = ($payment_status == 'Paid') ? 'status-paid' : 'status-pending';
                            ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo htmlspecialchars($order['firstname'] . ' ' . $order['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($order['phone']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($order['pickup_date'])); ?></td>
                                <td><?php echo htmlspecialchars($order['village'] . ', ' . $order['sector']); ?></td>
                                <td>RWF <?php echo number_format($order['amount'] ?? 5000); ?></td>
                                <td><span class="status-badge <?php echo $payment_class; ?>"><?php echo $payment_status; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(count($allOrders) == 0): ?>
                            <tr><td colspan="7" style="text-align: center;">No orders found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Drivers Tab -->
        <div id="drivers-tab" class="tab-content">
            <div class="section-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3 class="section-title"><i class="fas fa-truck"></i> Registered Drivers</h3>
                    <a href="../admin/add_driver.php" class="btn btn-primary">Add New Driver</a>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Gender</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($drivers as $driver): ?>
                            <tr>
                                <td>#<?php echo $driver['id']; ?></td>
                                <td><?php echo htmlspecialchars($driver['firstname'] . ' ' . $driver['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($driver['email']); ?></td>
                                <td><?php echo htmlspecialchars($driver['phone']); ?></td>
                                <td><?php echo $driver['gender']; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $driver['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo ucfirst($driver['status']); ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="driver_id" value="<?php echo $driver['id']; ?>">
                                        <select name="status" class="status-select" onchange="this.form.submit()">
                                            <option value="active" <?php echo $driver['status'] == 'active' ? 'selected' : ''; ?> style="color: #00c49a;">Active</option>
                                            <option value="inactive" <?php echo $driver['status'] == 'inactive' ? 'selected' : ''; ?> style="color: #ff8888;">Inactive</option>
                                        </select>
                                        <input type="hidden" name="update_driver_status" value="1">
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(count($drivers) == 0): ?>
                            <tr><td colspan="7" style="text-align: center;">No drivers registered</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Fee Collectors Tab -->
        <div id="collectors-tab" class="tab-content">
            <div class="section-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3 class="section-title"><i class="fas fa-money-bill-wave"></i> Registered Fee Collectors</h3>
                    <a href="../admin/add_collector.php" class="btn btn-primary">Add New Collector</a>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Gender</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($collectors as $collector): ?>
                            <tr>
                                <td>#<?php echo $collector['id']; ?></td>
                                <td><?php echo htmlspecialchars($collector['firstname'] . ' ' . $collector['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($collector['email']); ?></td>
                                <td><?php echo htmlspecialchars($collector['phone']); ?></td>
                                <td><?php echo $collector['gender']; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $collector['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo ucfirst($collector['status']); ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="collector_id" value="<?php echo $collector['id']; ?>">
                                        <select name="status" class="status-select" onchange="this.form.submit()">
                                            <option value="active" <?php echo $collector['status'] == 'active' ? 'selected' : ''; ?> style="color: #00c49a;">Active</option>
                                            <option value="inactive" <?php echo $collector['status'] == 'inactive' ? 'selected' : ''; ?> style="color: #ff8888;">Inactive</option>
                                        </select>
                                        <input type="hidden" name="update_collector_status" value="1">
                                    </form>
                                 </tr>
                            <?php endforeach; ?>
                            <?php if(count($collectors) == 0): ?>
                            <tr><td colspan="7" style="text-align: center;">No fee collectors registered</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Payments Tab -->
        <div id="payments-tab" class="tab-content">
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-credit-card"></i> Payment History</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr><th>Payment ID</th><th>Customer</th><th>Order ID</th><th>Amount</th><th>Payment Date</th><th>Status</th> </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("
                                SELECT p.*, c.firstname, c.lastname 
                                FROM payment p 
                                JOIN customer c ON p.customer_id = c.id 
                                ORDER BY p.id DESC LIMIT 20
                            ");
                            while($payment = $stmt->fetch()):
                            ?>
                             <tr>
                                <td>#<?php echo $payment['id']; ?></td>
                                <td><?php echo htmlspecialchars($payment['firstname'] . ' ' . $payment['lastname']); ?></td>
                                <td>#<?php echo $payment['order_id']; ?></td>
                                <td>RWF <?php echo number_format($payment['amount']); ?></td>
                                <td><?php echo $payment['payment_date'] && $payment['payment_date'] != '0000-00-00' ? date('M j, Y', strtotime($payment['payment_date'])) : 'Pending'; ?></td>
                                <td>
                                    <span class="status-badge <?php echo ($payment['payment_date'] && $payment['payment_date'] != '0000-00-00') ? 'status-paid' : 'status-pending'; ?>">
                                        <?php echo ($payment['payment_date'] && $payment['payment_date'] != '0000-00-00') ? 'Paid' : 'Pending'; ?>
                                    </span>
                                </td>
                             </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Reports Tab -->
        <div id="reports-tab" class="tab-content">
            <!-- Summary Cards -->
            <div class="report-summary">
                <div class="report-card">
                    <h4>Total Orders (30 Days)</h4>
                    <div class="number"><?php echo array_sum(array_column($dailyReports, 'total_orders')); ?></div>
                </div>
                <div class="report-card">
                    <h4>Total Collected</h4>
                    <div class="number">RWF <?php echo number_format(array_sum(array_column($dailyReports, 'collected_amount'))); ?></div>
                </div>
                <div class="report-card">
                    <h4>Total Pending</h4>
                    <div class="number">RWF <?php echo number_format(array_sum(array_column($dailyReports, 'pending_amount'))); ?></div>
                </div>
                <div class="report-card">
                    <h4>Collection Rate</h4>
                    <div class="number">
                        <?php 
                        $totalCollected = array_sum(array_column($dailyReports, 'collected_amount'));
                        $totalPending = array_sum(array_column($dailyReports, 'pending_amount'));
                        $total = $totalCollected + $totalPending;
                        $rate = $total > 0 ? round(($totalCollected / $total) * 100) : 0;
                        echo $rate . '%';
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Daily Report -->
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-calendar-day"></i> Daily Report (Last 30 Days)</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            32<th>Date</th><th>Orders</th><th>Collected</th><th>Pending</th><th>Paid Orders</th><th>Pending Orders</th> </tr>
                        </thead>
                        <tbody>
                            <?php foreach($dailyReports as $report): ?>
                             <tr>
                                <td><?php echo date('M j, Y', strtotime($report['date'])); ?></td>
                                <td><?php echo $report['total_orders']; ?></td>
                                <td>RWF <?php echo number_format($report['collected_amount']); ?></td>
                                <td>RWF <?php echo number_format($report['pending_amount']); ?></td>
                                <td><?php echo $report['paid_orders']; ?></td>
                                <td><?php echo $report['pending_orders']; ?></td>
                             </tr>
                            <?php endforeach; ?>
                            <?php if(count($dailyReports) == 0): ?>
                             <tr><td colspan="6" style="text-align: center;">No data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Monthly Report -->
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-calendar-alt"></i> Monthly Report</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            32<th>Month</th><th>Total Orders</th><th>Collected Amount</th><th>Pending Amount</th> </tr>
                        </thead>
                        <tbody>
                            <?php foreach($monthlyReports as $report): ?>
                             <tr>
                                <td><?php echo date('F Y', strtotime($report['month'] . '-01')); ?></td>
                                <td><?php echo $report['total_orders']; ?></td>
                                <td>RWF <?php echo number_format($report['collected_amount']); ?></td>
                                <td>RWF <?php echo number_format($report['pending_amount']); ?></td>
                             </tr>
                            <?php endforeach; ?>
                            <?php if(count($monthlyReports) == 0): ?>
                             <tr><td colspan="4" style="text-align: center;">No data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Top Customers -->
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-trophy"></i> Top Customers</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            32<th>Rank</th><th>Customer</th><th>Phone</th><th>Total Orders</th><th>Total Paid</th> </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach($topCustomers as $customer): ?>
                             <tr>
                                <td>
                                    <?php if($rank == 1): ?>🥇
                                    <?php elseif($rank == 2): ?>🥈
                                    <?php elseif($rank == 3): ?>🥉
                                    <?php else: echo $rank; endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($customer['firstname'] . ' ' . $customer['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                                <td><?php echo $customer['total_orders']; ?></td>
                                <td>RWF <?php echo number_format($customer['total_paid']); ?></td>
                             </tr>
                            <?php $rank++; endforeach; ?>
                            <?php if(count($topCustomers) == 0): ?>
                             <tr><td colspan="5" style="text-align: center;">No data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }
        
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            const selectedTab = document.getElementById(tabName + '-tab');
            if (selectedTab) {
                selectedTab.classList.add('active');
            }
            
            // Update active menu item
            document.querySelectorAll('.nav-menu a').forEach(link => {
                link.classList.remove('active');
            });
            
            // Set active class on clicked link
            if (event && event.currentTarget) {
                event.currentTarget.classList.add('active');
            }
        }
        
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('show')) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
        
        // Ensure active tab is highlighted on page load
        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = document.querySelector('.tab-content.active');
            if (!activeTab) {
                showTab('dashboard');
            }
        });
    </script>
</body>
</html>