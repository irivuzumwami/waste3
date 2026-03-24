<?php
// admin/payments.php - Payment Management for EcoWaste Admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit;
}
require_once '../config/database.php';

// Handle Payment Actions
$message = '';
$error = '';

// Add Payment (Record new payment for an existing order)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $order_id = $_POST['order_id'];
    $amount = floatval($_POST['amount']);
    $payment_date = $_POST['payment_date'];
    
    // Check if payment already exists for this order
    $stmt = $pdo->prepare("SELECT id FROM payment WHERE order_id = ?");
    $stmt->execute([$order_id]);
    if ($stmt->fetch()) {
        $error = "Payment already exists for this order. Please edit the existing payment instead.";
    } else {
        // Get customer_id from order
        $stmt = $pdo->prepare("SELECT customer_id FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        
        if ($order) {
            try {
                $stmt = $pdo->prepare("INSERT INTO payment (order_id, customer_id, amount, payment_date) VALUES (?, ?, ?, ?)");
                $stmt->execute([$order_id, $order['customer_id'], $amount, $payment_date]);
                $message = "Payment recorded successfully!";
            } catch (PDOException $e) {
                $error = "Error recording payment: " . $e->getMessage();
            }
        } else {
            $error = "Order not found!";
        }
    }
}

// Update Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $payment_id = $_POST['payment_id'];
    $amount = floatval($_POST['amount']);
    $payment_date = $_POST['payment_date'];
    
    try {
        $stmt = $pdo->prepare("UPDATE payment SET amount = ?, payment_date = ? WHERE id = ?");
        $stmt->execute([$amount, $payment_date, $payment_id]);
        $message = "Payment updated successfully!";
    } catch (PDOException $e) {
        $error = "Error updating payment: " . $e->getMessage();
    }
}

// Delete Payment
if (isset($_GET['delete'])) {
    $payment_id = $_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM payment WHERE id = ?");
        $stmt->execute([$payment_id]);
        $message = "Payment deleted successfully!";
    } catch (PDOException $e) {
        $error = "Error deleting payment: " . $e->getMessage();
    }
}

// Get all payments with filters and pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$amount_min = isset($_GET['amount_min']) ? floatval($_GET['amount_min']) : '';
$amount_max = isset($_GET['amount_max']) ? floatval($_GET['amount_max']) : '';

// Build query with filters
$whereConditions = [];
$params = [];

// Search by customer name or order ID
if ($search) {
    $whereConditions[] = "(p.id LIKE ? OR CONCAT(c.firstname, ' ', c.lastname) LIKE ? OR c.phone LIKE ? OR o.id LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Date range filter
if ($date_from) {
    $whereConditions[] = "p.payment_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $whereConditions[] = "p.payment_date <= ?";
    $params[] = $date_to;
}

// Amount range filter
if ($amount_min) {
    $whereConditions[] = "p.amount >= ?";
    $params[] = $amount_min;
}
if ($amount_max) {
    $whereConditions[] = "p.amount <= ?";
    $params[] = $amount_max;
}

$whereClause = count($whereConditions) > 0 ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get total count
$countSql = "SELECT COUNT(*) FROM payment p 
             JOIN orders o ON p.order_id = o.id 
             JOIN customer c ON o.customer_id = c.id 
             $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalPayments = $countStmt->fetchColumn();

// Get payments with pagination
$sql = "SELECT p.*, o.id as order_id, o.pickup_date,
               c.firstname, c.lastname, c.email, c.phone, c.village
        FROM payment p 
        JOIN orders o ON p.order_id = o.id 
        JOIN customer c ON o.customer_id = c.id 
        $whereClause 
        ORDER BY p.payment_date DESC, p.id DESC 
        LIMIT " . (int)$offset . ", " . (int)$limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

$totalPages = ceil($totalPayments / $limit);

// Get payment for editing via AJAX
if (isset($_GET['ajax_get_payment'])) {
    $payment_id = $_GET['ajax_get_payment'];
    $stmt = $pdo->prepare("
        SELECT p.*, o.id as order_id, o.pickup_date,
               c.firstname, c.lastname, c.email, c.phone
        FROM payment p 
        JOIN orders o ON p.order_id = o.id 
        JOIN customer c ON o.customer_id = c.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    if ($payment) {
        echo json_encode(['success' => true, 'payment' => $payment]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Get orders without payment for dropdown (to add new payments)
$stmt = $pdo->query("
    SELECT o.id, c.firstname, c.lastname, c.phone 
    FROM orders o 
    JOIN customer c ON o.customer_id = c.id 
    LEFT JOIN payment p ON o.id = p.order_id 
    WHERE p.id IS NULL 
    ORDER BY o.id DESC
");
$unpaidOrders = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total, SUM(amount) as total_amount FROM payment");
$stats = $stmt->fetch();
$totalPaymentsAll = $stats['total'];
$totalRevenue = $stats['total_amount'] ?? 0;

// Get monthly statistics for the current year
$currentYear = date('Y');
$monthlyStats = [];
for ($i = 1; $i <= 12; $i++) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM payment WHERE YEAR(payment_date) = ? AND MONTH(payment_date) = ?");
    $stmt->execute([$currentYear, $i]);
    $monthlyStats[$i] = $stmt->fetch();
}

// Get today's payments
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM payment WHERE payment_date = ?");
$stmt->execute([$today]);
$todayStats = $stmt->fetch();

// Get this week's payments
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));
$stmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM payment WHERE payment_date BETWEEN ? AND ?");
$stmt->execute([$weekStart, $weekEnd]);
$weekStats = $stmt->fetch();

// Get this month's payments
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$stmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM payment WHERE payment_date BETWEEN ? AND ?");
$stmt->execute([$monthStart, $monthEnd]);
$monthStats = $stmt->fetch();

// Get average payment amount
$stmt = $pdo->query("SELECT AVG(amount) as avg FROM payment");
$avgAmount = $stmt->fetch()['avg'] ?? 0;

// Get highest payment
$stmt = $pdo->query("SELECT MAX(amount) as max FROM payment");
$maxAmount = $stmt->fetch()['max'] ?? 0;

// Get lowest payment
$stmt = $pdo->query("SELECT MIN(amount) as min FROM payment");
$minAmount = $stmt->fetch()['min'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - EcoWaste Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            color: #0f172a;
        }

        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #0f172a 0%, #0a0f1c 100%);
            color: #e2e8f0;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 100;
        }

        .sidebar-header {
            padding: 1.8rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            margin-bottom: 1.5rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #1e3a8a, #00c49a);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-icon i {
            font-size: 22px;
            color: #fbbf24;
        }

        .logo-text h2 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .logo-text p {
            font-size: 0.7rem;
            color: #94a3b8;
        }

        .user-badge {
            margin-top: 1.5rem;
            padding: 0.8rem;
            background: rgba(255,255,255,0.05);
            border-radius: 16px;
            text-align: center;
        }

        .user-badge .name {
            font-weight: 600;
        }

        .user-badge .role {
            font-size: 0.75rem;
            color: #fbbf24;
            background: rgba(251,191,36,0.15);
            display: inline-block;
            padding: 0.2rem 0.8rem;
            border-radius: 30px;
            margin-top: 6px;
        }

        .nav-menu {
            list-style: none;
            padding: 0 1rem;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            color: #cbd5e1;
            text-decoration: none;
            font-weight: 500;
            transition: 0.2s;
        }

        .nav-link i {
            width: 24px;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.08);
            color: white;
        }

        .nav-link.active {
            background: #1e3a8a;
        }

        .logout-link {
            margin-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.08);
            padding-top: 1rem;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 1.8rem 2rem;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title h1 {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .page-title p {
            color: #475569;
            font-size: 0.9rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .stat-header span {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            background: #eef2ff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1e3a8a;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            color: #0f172a;
        }

        .stat-sub {
            font-size: 0.7rem;
            color: #64748b;
        }

        /* Mini Stats Row */
        .stats-mini-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-mini-card {
            background: white;
            border-radius: 16px;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px solid #e2e8f0;
        }

        .stat-mini-icon {
            width: 48px;
            height: 48px;
            background: #eef2ff;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #1e3a8a;
        }

        .stat-mini-info h4 {
            font-size: 0.7rem;
            color: #64748b;
            text-transform: uppercase;
        }

        .stat-mini-info .value {
            font-size: 1.3rem;
            font-weight: 700;
        }

        .stat-mini-info .small {
            font-size: 0.7rem;
            color: #64748b;
        }

        /* Chart Row */
        .chart-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 1.2rem;
            border: 1px solid #e2e8f0;
        }

        .chart-card h3 {
            font-size: 1rem;
            margin-bottom: 1rem;
            color: #1e293b;
        }

        canvas {
            max-height: 250px;
            width: 100%;
        }

        /* Action Bar */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .btn-primary {
            background: #1e3a8a;
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: 0.2s;
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #cbd5e1;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #1e293b;
        }

        .btn-outline:hover {
            background: #f1f5f9;
        }

        .filter-section {
            background: white;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
        }

        .filter-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 140px;
        }

        .filter-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
            color: #64748b;
            text-transform: uppercase;
        }

        .filter-group select, .filter-group input {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.9rem;
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 24px;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem 1rem;
            text-align: left;
            border-bottom: 1px solid #f0f2f5;
        }

        th {
            background: #f8fafc;
            font-weight: 600;
            color: #1e293b;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        td {
            font-size: 0.9rem;
        }

        .amount {
            font-weight: 700;
            color: #15803d;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .btn-icon {
            padding: 5px 10px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.7rem;
            transition: 0.2s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-view {
            background: #eef2ff;
            color: #1e3a8a;
        }

        .btn-edit {
            background: #fef9c3;
            color: #854d0e;
        }

        .btn-delete {
            background: #fee2e2;
            color: #b91c1c;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 2rem;
        }

        .pagination a, .pagination span {
            padding: 8px 14px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            text-decoration: none;
            color: #1e3a8a;
        }

        .pagination .active {
            background: #1e3a8a;
            color: white;
            border-color: #1e3a8a;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 28px;
            width: 90%;
            max-width: 550px;
            max-height: 85vh;
            overflow-y: auto;
            padding: 2rem;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .close-modal {
            font-size: 1.8rem;
            cursor: pointer;
            color: #94a3b8;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 500;
            font-size: 0.85rem;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-family: inherit;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #dcfce7;
            color: #15803d;
            border-left: 4px solid #15803d;
        }

        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
            border-left: 4px solid #b91c1c;
        }

        .info-note {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.5rem;
        }

        .payment-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .detail-item {
            padding: 0.8rem;
            background: #f8fafc;
            border-radius: 12px;
        }

        .detail-label {
            font-size: 0.7rem;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 0.3rem;
        }

        .detail-value {
            font-weight: 600;
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            .menu-toggle {
                display: block;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .chart-row {
                grid-template-columns: 1fr;
            }
            .payment-details-grid {
                grid-template-columns: 1fr;
            }
        }

        .menu-toggle {
            display: none;
            background: white;
            border: none;
            font-size: 1.4rem;
            padding: 0.5rem;
            border-radius: 10px;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon"><i class="fas fa-recycle"></i></div>
                <div class="logo-text">
                    <h2>EcoWaste</h2>
                    <p>Smart Waste Management</p>
                </div>
            </div>
            <div class="user-badge">
                <div class="name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></div>
                <div class="role">Administrator</div>
            </div>
        </div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="customers.php" class="nav-link"><i class="fas fa-users"></i> Customers</a></li>
            <li class="nav-item"><a href="workers.php" class="nav-link"><i class="fas fa-hard-hat"></i> Workers</a></li>
            <li class="nav-item"><a href="orders.php" class="nav-link"><i class="fas fa-truck"></i> Orders</a></li>
            <li class="nav-item"><a href="payments.php" class="nav-link active"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li class="nav-item"><a href="support.php" class="nav-link"><i class="fas fa-headset"></i> Support</a></li>
            <li class="nav-item logout-link"><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Payment Management</h1>
                <p>Track all payments, view revenue statistics, and manage transactions</p>
            </div>
            <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
        </div>

        <!-- Main Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span>Total Revenue</span>
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                </div>
                <div class="stat-number">RWF <?php echo number_format($totalRevenue); ?></div>
                <div class="stat-sub">All time earnings</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span>Total Transactions</span>
                    <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($totalPaymentsAll); ?></div>
                <div class="stat-sub">Completed payments</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span>Average Payment</span>
                    <div class="stat-icon"><i class="fas fa-chart-simple"></i></div>
                </div>
                <div class="stat-number">RWF <?php echo number_format($avgAmount); ?></div>
                <div class="stat-sub">Per transaction</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span>Range</span>
                    <div class="stat-icon"><i class="fas fa-chart-bar"></i></div>
                </div>
                <div class="stat-number">RWF <?php echo number_format($minAmount); ?> - <?php echo number_format($maxAmount); ?></div>
                <div class="stat-sub">Min - Max payment</div>
            </div>
        </div>

        <!-- Mini Stats Row -->
        <div class="stats-mini-row">
            <div class="stat-mini-card">
                <div class="stat-mini-icon"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-mini-info">
                    <h4>Today's Payments</h4>
                    <div class="value">RWF <?php echo number_format($todayStats['total']); ?></div>
                    <div class="small"><?php echo $todayStats['count']; ?> transactions</div>
                </div>
            </div>
            <div class="stat-mini-card">
                <div class="stat-mini-icon"><i class="fas fa-calendar-week"></i></div>
                <div class="stat-mini-info">
                    <h4>This Week</h4>
                    <div class="value">RWF <?php echo number_format($weekStats['total']); ?></div>
                    <div class="small"><?php echo $weekStats['count']; ?> transactions</div>
                </div>
            </div>
            <div class="stat-mini-card">
                <div class="stat-mini-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-mini-info">
                    <h4>This Month</h4>
                    <div class="value">RWF <?php echo number_format($monthStats['total']); ?></div>
                    <div class="small"><?php echo $monthStats['count']; ?> transactions</div>
                </div>
            </div>
        </div>

        <!-- Chart Row -->
        <div class="chart-row">
            <div class="chart-card">
                <h3><i class="fas fa-chart-bar"></i> Monthly Revenue (<?php echo $currentYear; ?>)</h3>
                <canvas id="revenueChart"></canvas>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> Monthly Transaction Count</h3>
                <canvas id="countChart"></canvas>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Action Bar -->
        <div class="action-bar">
            <button class="btn-primary" onclick="openAddModal()"><i class="fas fa-plus-circle"></i> Record Payment</button>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Payment ID, Customer, Order..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="filter-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="filter-group">
                        <label>Min Amount (RWF)</label>
                        <input type="number" name="amount_min" placeholder="Min" value="<?php echo $amount_min; ?>">
                    </div>
                    <div class="filter-group">
                        <label>Max Amount (RWF)</label>
                        <input type="number" name="amount_max" placeholder="Max" value="<?php echo $amount_max; ?>">
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn-outline"><i class="fas fa-search"></i> Apply</button>
                        <?php if ($search || $date_from || $date_to || $amount_min || $amount_max): ?>
                            <a href="payments.php" class="btn-outline"><i class="fas fa-times"></i> Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Payments Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Payment ID</th>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Amount</th>
                        <th>Payment Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($payments) > 0): ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><strong>#<?php echo $payment['id']; ?></strong></td>
                                <td>ORD-<?php echo $payment['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($payment['firstname'] . ' ' . $payment['lastname']); ?></td>
                                <td>
                                    <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($payment['phone']); ?></div>
                                    <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($payment['email']); ?></div>
                                </td>
                                <td class="amount">RWF <?php echo number_format($payment['amount']); ?></td>
                                <td>
                                    <strong><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></strong>
                                    <div class="small"><?php echo date('h:i A', strtotime($payment['payment_date'])); ?></div>
                                </td>
                                <td class="action-buttons">
                                    <a href="javascript:void(0)" onclick="viewPayment(<?php echo $payment['id']; ?>)" class="btn-icon btn-view"><i class="fas fa-eye"></i> View</a>
                                    <a href="javascript:void(0)" onclick="openEditModal(<?php echo $payment['id']; ?>)" class="btn-icon btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="?delete=<?php echo $payment['id']; ?>" class="btn-icon btn-delete" onclick="return confirm('Delete this payment? This action cannot be undone.')"><i class="fas fa-trash"></i> Del</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding: 3rem;">
                                <i class="fas fa-credit-card" style="font-size: 2rem; color: #cbd5e1;"></i>
                                <p style="margin-top: 0.5rem;">No payments found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&amount_min=<?php echo $amount_min; ?>&amount_max=<?php echo $amount_max; ?>"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&amount_min=<?php echo $amount_min; ?>&amount_max=<?php echo $amount_max; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&amount_min=<?php echo $amount_min; ?>&amount_max=<?php echo $amount_max; ?>"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Add Payment Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-plus-circle"></i> Record New Payment</h2>
            <span class="close-modal" onclick="closeAddModal()">&times;</span>
        </div>
        <form method="POST">
            <div class="form-group">
                <label>Select Order (Unpaid Orders) *</label>
                <select name="order_id" required>
                    <option value="">-- Select Order --</option>
                    <?php foreach ($unpaidOrders as $order): ?>
                        <option value="<?php echo $order['id']; ?>">
                            Order #<?php echo $order['id']; ?> - <?php echo htmlspecialchars($order['firstname'] . ' ' . $order['lastname'] . ' (' . $order['phone'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (count($unpaidOrders) == 0): ?>
                    <div class="info-note" style="color: #d97706;">All orders have been paid. No unpaid orders found.</div>
                <?php endif; ?>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Amount (RWF) *</label>
                    <input type="number" name="amount" value="5000" min="100" step="500" required>
                </div>
                <div class="form-group">
                    <label>Payment Date *</label>
                    <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <button type="submit" name="add_payment" class="btn-primary" style="width:100%; margin-top:1rem;">Record Payment</button>
        </form>
    </div>
</div>

<!-- Edit Payment Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Edit Payment</h2>
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="payment_id" id="edit_payment_id">
            <div class="form-row">
                <div class="form-group">
                    <label>Amount (RWF)</label>
                    <input type="number" name="amount" id="edit_amount" required>
                </div>
                <div class="form-group">
                    <label>Payment Date</label>
                    <input type="date" name="payment_date" id="edit_payment_date" required>
                </div>
            </div>
            <div class="info-note" id="order_info"></div>
            <button type="submit" name="update_payment" class="btn-primary" style="width:100%; margin-top:1rem;">Update Payment</button>
        </form>
    </div>
</div>

<!-- View Payment Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2><i class="fas fa-file-invoice-dollar"></i> Payment Details</h2>
            <span class="close-modal" onclick="closeViewModal()">&times;</span>
        </div>
        <div id="paymentDetails"></div>
    </div>
</div>

<script>
    // Sidebar toggle for mobile
    const toggleBtn = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    if(toggleBtn) {
        toggleBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
        document.addEventListener('click', (e) => {
            if(window.innerWidth <= 768 && sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== toggleBtn) {
                sidebar.classList.remove('open');
            }
        });
    }

    function openAddModal() { 
        document.getElementById('addModal').classList.add('show'); 
    }
    
    function closeAddModal() { 
        document.getElementById('addModal').classList.remove('show'); 
    }

    function openEditModal(id) {
        fetch(`payments.php?ajax_get_payment=${id}`)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    document.getElementById('edit_payment_id').value = data.payment.id;
                    document.getElementById('edit_amount').value = data.payment.amount;
                    document.getElementById('edit_payment_date').value = data.payment.payment_date;
                    document.getElementById('order_info').innerHTML = `
                        <i class="fas fa-info-circle"></i> Order #${data.payment.order_id} - 
                        ${data.payment.firstname} ${data.payment.lastname} (${data.payment.phone})
                    `;
                    document.getElementById('editModal').classList.add('show');
                } else {
                    alert('Error loading payment data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading payment data');
            });
    }
    
    function closeEditModal() { 
        document.getElementById('editModal').classList.remove('show'); 
    }

    function viewPayment(id) {
        fetch(`payments.php?ajax_get_payment=${id}`)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    const payment = data.payment;
                    document.getElementById('paymentDetails').innerHTML = `
                        <div class="payment-details-grid">
                            <div class="detail-item">
                                <div class="detail-label">Payment ID</div>
                                <div class="detail-value">#${payment.id}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Order ID</div>
                                <div class="detail-value">#${payment.order_id}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Customer Name</div>
                                <div class="detail-value">${payment.firstname} ${payment.lastname}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Contact</div>
                                <div class="detail-value">${payment.phone}<br>${payment.email}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Amount Paid</div>
                                <div class="detail-value" style="color: #15803d; font-size: 1.3rem;">RWF ${Number(payment.amount).toLocaleString()}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Payment Date</div>
                                <div class="detail-value">${new Date(payment.payment_date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Order Pickup Date</div>
                                <div class="detail-value">${new Date(payment.pickup_date).toLocaleDateString()}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Transaction ID</div>
                                <div class="detail-value">ECO-${payment.id}-${payment.order_id}</div>
                            </div>
                        </div>
                    `;
                    document.getElementById('viewModal').classList.add('show');
                } else {
                    alert('Error loading payment details');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading payment details');
            });
    }
    
    function closeViewModal() { 
        document.getElementById('viewModal').classList.remove('show'); 
    }

    // Close modals when clicking outside
    window.onclick = function(e) {
        if(e.target.classList.contains('modal')) {
            e.target.classList.remove('show');
        }
    }

    // Charts
    <?php
    $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $monthlyTotals = [];
    $monthlyCounts = [];
    for ($i = 1; $i <= 12; $i++) {
        $monthlyTotals[] = $monthlyStats[$i]['total'] ?? 0;
        $monthlyCounts[] = $monthlyStats[$i]['count'] ?? 0;
    }
    ?>
    
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($monthNames); ?>,
            datasets: [{
                label: 'Revenue (RWF)',
                data: <?php echo json_encode($monthlyTotals); ?>,
                backgroundColor: '#1e3a8a',
                borderRadius: 8,
                borderColor: '#1e3a8a'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'top' },
                tooltip: { callbacks: { label: (ctx) => `RWF ${ctx.raw.toLocaleString()}` } }
            },
            scales: { y: { beginAtZero: true, ticks: { callback: (val) => 'RWF ' + val.toLocaleString() } } }
        }
    });
    
    const countCtx = document.getElementById('countChart').getContext('2d');
    new Chart(countCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($monthNames); ?>,
            datasets: [{
                label: 'Number of Transactions',
                data: <?php echo json_encode($monthlyCounts); ?>,
                borderColor: '#00c49a',
                backgroundColor: 'rgba(0,196,154,0.1)',
                borderWidth: 3,
                pointBackgroundColor: '#fbbf24',
                pointBorderColor: '#fff',
                pointRadius: 5,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'top' }
            },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
</script>
</body>
</html>