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
$stmt = $pdo->query("SELECT COUNT(*) as total FROM customer");
$totalCustomers = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE DATE(pickup_date) >= CURDATE()");
$upcomingOrders = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE DATE(pickup_date) = CURDATE()");
$todayOrders = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM workers WHERE role = 'driver' AND status = 'active'");
$activeDrivers = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM workers WHERE role = 'collector' AND status = 'active'");
$activeCollectors = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT SUM(amount) as total FROM payment WHERE payment_date IS NOT NULL AND payment_date != '0000-00-00'");
$totalRevenue = $stmt->fetch()['total'] ?? 0;

// Get recent orders
$stmt = $pdo->query("
    SELECT o.*, c.firstname, c.lastname, c.phone, c.village 
    FROM orders o 
    JOIN customer c ON o.customer_id = c.id 
    ORDER BY o.id DESC LIMIT 10
");
$recentOrders = $stmt->fetchAll();

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
    ORDER BY p.id DESC LIMIT 5
");
$pendingPayments = $stmt->fetchAll();

// Get active drivers
$stmt = $pdo->query("SELECT * FROM workers WHERE role = 'driver' AND status = 'active' LIMIT 5");
$drivers = $stmt->fetchAll();

// Get active collectors
$stmt = $pdo->query("SELECT * FROM workers WHERE role = 'collector' AND status = 'active' LIMIT 5");
$collectors = $stmt->fetchAll();

// Handle order assignment to driver
if (isset($_POST['assign_driver'])) {
    $order_id = $_POST['order_id'];
    $driver_id = $_POST['driver_id'];
    
    // Here you would update the order with assigned driver
    // For now, we'll just show success message
    $success = "Order #$order_id assigned to driver ID: $driver_id";
}

// Handle payment collection
if (isset($_POST['collect_payment'])) {
    $payment_id = $_POST['payment_id'];
    $stmt = $pdo->prepare("UPDATE payment SET payment_date = CURDATE() WHERE id = ?");
    if ($stmt->execute([$payment_id])) {
        $success = "Payment collected successfully!";
        header("Location: dashboard.php?success=payment_collected");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - EcoWaste</title>
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
            overflow-y: auto;
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
        
        .status-active {
            background: rgba(0,196,154,0.2);
            color: var(--teal-accent);
        }
        
        .status-pending {
            background: rgba(255,193,7,0.2);
            color: #ffc107;
        }
        
        .btn {
            padding: 0.4rem 0.8rem;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            border: none;
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
        
        select {
            padding: 0.3rem;
            border-radius: 5px;
            background: rgba(255,255,255,0.1);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.2);
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
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($manager['firstname'] . ' ' . $manager['lastname']); ?></div>
            <div class="user-email"><?php echo htmlspecialchars($manager['email']); ?></div>
            <div class="user-role"><i class="fas fa-tasks"></i> Operations Manager</div>
        </div>
        <ul class="nav-menu">
            <li><a href="#" class="active" onclick="showTab('dashboard'); return false;"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="#" onclick="showTab('orders'); return false;"><i class="fas fa-clipboard-list"></i> Manage Orders</a></li>
            <li><a href="#" onclick="showTab('drivers'); return false;"><i class="fas fa-truck"></i> Drivers</a></li>
            <li><a href="#" onclick="showTab('collectors'); return false;"><i class="fas fa-money-bill-wave"></i> Fee Collectors</a></li>
            <li><a href="#" onclick="showTab('payments'); return false;"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li><a href="#" onclick="showTab('reports'); return false;"><i class="fas fa-chart-bar"></i> Reports</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> Manager Dashboard</h1>
            <a href="../logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
        
        <?php if(isset($_GET['success']) && $_GET['success'] == 'payment_collected'): ?>
            <div class="alert alert-success">✅ Payment collected successfully!</div>
        <?php endif; ?>
        
        <!-- Dashboard Tab -->
        <div id="dashboard-tab" class="tab-content active">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <h3>Total Customers</h3>
                    <div class="stat-number"><?php echo $totalCustomers; ?></div>
                </div>
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
                            <tr><th>Order ID</th><th>Customer</th><th>Phone</th><th>Address</th><th>Action</th> </tr>
                        </thead>
                        <tbody>
                            <?php foreach($todayPickups as $pickup): ?>
                            <tr>
                                <td>#<?php echo $pickup['id']; ?></td>
                                <td><?php echo htmlspecialchars($pickup['firstname'] . ' ' . $pickup['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['phone']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['housenumber'] . ', ' . $pickup['village']); ?></td>
                                <td>
                                    <select onchange="assignDriver(<?php echo $pickup['id']; ?>, this.value)">
                                        <option value="">Assign Driver</option>
                                        <?php foreach($drivers as $driver): ?>
                                            <option value="<?php echo $driver['id']; ?>"><?php echo $driver['firstname'] . ' ' . $driver['lastname']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(count($todayPickups) == 0): ?>
                            <tr><td colspan="5" style="text-align: center;">No pickups scheduled for today</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="section-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3 class="section-title"><i class="fas fa-clock"></i> Pending Payments</h3>
                    <a href="#" onclick="showTab('payments'); return false;" class="btn btn-primary">View All</a>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr><th>Payment ID</th><th>Customer</th><th>Phone</th><th>Amount</th><th>Action</th> </tr>
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
                            <tr><th>Order ID</th><th>Customer</th><th>Pickup Date</th><th>Status</th><th>Payment</th><th>Action</th> </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recentOrders as $order): 
                                $order_date = strtotime($order['pickup_date']);
                                $today = strtotime(date('Y-m-d'));
                                if ($order_date == $today) {
                                    $status = 'Today';
                                    $status_class = 'status-pending';
                                } elseif ($order_date > $today) {
                                    $status = 'Upcoming';
                                    $status_class = 'status-active';
                                } else {
                                    $status = 'Completed';
                                    $status_class = 'status-pending';
                                }
                            ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo htmlspecialchars($order['firstname'] . ' ' . $order['lastname']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($order['pickup_date'])); ?></td>
                                <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status; ?></span></td>
                                <td><span class="status-badge status-pending">Pending</span></td>
                                <td>
                                    <button class="btn btn-primary" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Drivers Tab -->
        <div id="drivers-tab" class="tab-content">
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-truck"></i> Active Drivers</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Phone</th><th>Email</th><th>Status</th> </tr>
                        </thead>
                        <tbody>
                            <?php foreach($drivers as $driver): ?>
                            <tr>
                                <td>#<?php echo $driver['id']; ?></td>
                                <td><?php echo htmlspecialchars($driver['firstname'] . ' ' . $driver['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($driver['phone']); ?></td>
                                <td><?php echo htmlspecialchars($driver['email']); ?></td>
                                <td><span class="status-badge status-active">Active</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Collectors Tab -->
        <div id="collectors-tab" class="tab-content">
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-money-bill-wave"></i> Fee Collectors</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Phone</th><th>Email</th><th>Status</th> </tr>
                        </thead>
                        <tbody>
                            <?php foreach($collectors as $collector): ?>
                            <tr>
                                <td>#<?php echo $collector['id']; ?></td>
                                <td><?php echo htmlspecialchars($collector['firstname'] . ' ' . $collector['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($collector['phone']); ?></td>
                                <td><?php echo htmlspecialchars($collector['email']); ?></td>
                                <td><span class="status-badge status-active">Active</span></td>
                            </tr>
                            <?php endforeach; ?>
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
                            <tr><th>Payment ID</th><th>Customer</th><th>Order ID</th><th>Amount</th><th>Date</th><th>Status</th> </tr>
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
                                <td><span class="status-badge <?php echo ($payment['payment_date'] && $payment['payment_date'] != '0000-00-00') ? 'status-active' : 'status-pending'; ?>">
                                    <?php echo ($payment['payment_date'] && $payment['payment_date'] != '0000-00-00') ? 'Paid' : 'Pending'; ?>
                                </span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Reports Tab -->
        <div id="reports-tab" class="tab-content">
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-chart-bar"></i> Daily Reports</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr><th>Date</th><th>Orders</th><th>Revenue</th><th>Pending Payments</th> </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("
                                SELECT 
                                    DATE(o.pickup_date) as date,
                                    COUNT(o.id) as total_orders,
                                    SUM(CASE WHEN p.payment_date IS NOT NULL THEN p.amount ELSE 0 END) as collected,
                                    SUM(CASE WHEN p.payment_date IS NULL THEN p.amount ELSE 0 END) as pending
                                FROM orders o
                                LEFT JOIN payment p ON o.id = p.order_id
                                WHERE o.pickup_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                                GROUP BY DATE(o.pickup_date)
                                ORDER BY date DESC
                            ");
                            while($report = $stmt->fetch()):
                            ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($report['date'])); ?></td>
                                <td><?php echo $report['total_orders']; ?></td>
                                <td>RWF <?php echo number_format($report['collected']); ?></td>
                                <td>RWF <?php echo number_format($report['pending']); ?></td>
                            </tr>
                            <?php endwhile; ?>
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
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Update active menu item
            document.querySelectorAll('.nav-menu a').forEach(link => {
                link.classList.remove('active');
            });
            
            // Set active class on clicked link
            event.currentTarget.classList.add('active');
        }
        
        function assignDriver(orderId, driverId) {
            if (driverId) {
                if (confirm(`Assign order #${orderId} to this driver?`)) {
                    // Here you would submit an AJAX request or form
                    alert(`Order #${orderId} assigned to driver ID: ${driverId}`);
                }
            }
        }
        
        function viewOrder(orderId) {
            alert(`Viewing order #${orderId} details`);
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