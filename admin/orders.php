<?php
// admin/orders.php - Order Management for EcoWaste Admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit;
}
require_once '../config/database.php';

// Handle Order Actions
$message = '';
$error = '';

// Create Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $customer_id = $_POST['customer_id'];
    $pickup_date = $_POST['pickup_date'];
    $amount = floatval($_POST['amount']);
    $payment_status = $_POST['payment_status'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert order
        $stmt = $pdo->prepare("INSERT INTO orders (customer_id, pickup_date) VALUES (?, ?)");
        $stmt->execute([$customer_id, $pickup_date]);
        $order_id = $pdo->lastInsertId();
        
        // Insert payment if paid
        if ($payment_status == 'paid') {
            $stmt = $pdo->prepare("INSERT INTO payment (order_id, customer_id, amount, payment_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$order_id, $customer_id, $amount, date('Y-m-d')]);
        }
        
        $pdo->commit();
        $message = "Order created successfully!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error creating order: " . $e->getMessage();
    }
}

// Update Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $order_id = $_POST['order_id'];
    $pickup_date = $_POST['pickup_date'];
    $amount = floatval($_POST['amount']);
    $payment_status = $_POST['payment_status'];
    
    try {
        $pdo->beginTransaction();
        
        // Update order
        $stmt = $pdo->prepare("UPDATE orders SET pickup_date = ? WHERE id = ?");
        $stmt->execute([$pickup_date, $order_id]);
        
        // Check if payment exists
        $stmt = $pdo->prepare("SELECT id FROM payment WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $payment_exists = $stmt->fetch();
        
        // Get customer_id for payment
        $stmt = $pdo->prepare("SELECT customer_id FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        
        if ($payment_status == 'paid') {
            if ($payment_exists) {
                // Update existing payment
                $stmt = $pdo->prepare("UPDATE payment SET amount = ?, payment_date = ? WHERE order_id = ?");
                $stmt->execute([$amount, date('Y-m-d'), $order_id]);
            } else {
                // Create new payment
                $stmt = $pdo->prepare("INSERT INTO payment (order_id, customer_id, amount, payment_date) VALUES (?, ?, ?, ?)");
                $stmt->execute([$order_id, $order['customer_id'], $amount, date('Y-m-d')]);
            }
        } else {
            // Delete payment if exists
            if ($payment_exists) {
                $stmt = $pdo->prepare("DELETE FROM payment WHERE order_id = ?");
                $stmt->execute([$order_id]);
            }
        }
        
        $pdo->commit();
        $message = "Order updated successfully!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error updating order: " . $e->getMessage();
    }
}

// Delete Order
if (isset($_GET['delete'])) {
    $order_id = $_GET['delete'];
    
    try {
        $pdo->beginTransaction();
        
        // Delete payment first (if exists)
        $stmt = $pdo->prepare("DELETE FROM payment WHERE order_id = ?");
        $stmt->execute([$order_id]);
        
        // Delete order
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        
        $pdo->commit();
        $message = "Order deleted successfully!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error deleting order: " . $e->getMessage();
    }
}

// Mark as Completed (for past dates)
if (isset($_GET['mark_completed'])) {
    $order_id = $_GET['mark_completed'];
    $message = "Order marked as completed (no database changes needed)";
}

// Get all orders with filters and pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$payment_filter = isset($_GET['payment']) ? $_GET['payment'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query with filters
$whereConditions = [];
$params = [];

// Search by customer name or order ID
if ($search) {
    $whereConditions[] = "(o.id LIKE ? OR CONCAT(c.firstname, ' ', c.lastname) LIKE ? OR c.phone LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Status filter (based on pickup date)
if ($status_filter != 'all') {
    $today = date('Y-m-d');
    if ($status_filter == 'today') {
        $whereConditions[] = "o.pickup_date = ?";
        $params[] = $today;
    } elseif ($status_filter == 'upcoming') {
        $whereConditions[] = "o.pickup_date > ?";
        $params[] = $today;
    } elseif ($status_filter == 'past') {
        $whereConditions[] = "o.pickup_date < ?";
        $params[] = $today;
    }
}

// Payment filter
if ($payment_filter != 'all') {
    if ($payment_filter == 'paid') {
        $whereConditions[] = "p.id IS NOT NULL";
    } elseif ($payment_filter == 'pending') {
        $whereConditions[] = "p.id IS NULL";
    }
}

// Date range filter
if ($date_from) {
    $whereConditions[] = "o.pickup_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $whereConditions[] = "o.pickup_date <= ?";
    $params[] = $date_to;
}

$whereClause = count($whereConditions) > 0 ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get total count
$countSql = "SELECT COUNT(DISTINCT o.id) FROM orders o 
             JOIN customer c ON o.customer_id = c.id 
             LEFT JOIN payment p ON o.id = p.order_id 
             $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalOrders = $countStmt->fetchColumn();

// Get orders with pagination
$sql = "SELECT o.*, c.firstname, c.lastname, c.phone, c.email, c.village, c.housenumber,
               p.amount, p.payment_date 
        FROM orders o 
        JOIN customer c ON o.customer_id = c.id 
        LEFT JOIN payment p ON o.id = p.order_id 
        $whereClause 
        ORDER BY o.pickup_date ASC, o.id DESC 
        LIMIT " . (int)$offset . ", " . (int)$limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$totalPages = ceil($totalOrders / $limit);

// Get order for viewing details via AJAX
if (isset($_GET['ajax_get_order'])) {
    $order_id = $_GET['ajax_get_order'];
    $stmt = $pdo->prepare("
        SELECT o.*, c.firstname, c.lastname, c.email, c.phone, c.housenumber, 
               c.province, c.district, c.sector, c.cell, c.village,
               p.amount, p.payment_date
        FROM orders o 
        JOIN customer c ON o.customer_id = c.id 
        LEFT JOIN payment p ON o.id = p.order_id 
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    if ($order) {
        echo json_encode(['success' => true, 'order' => $order]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Get statistics
$today = date('Y-m-d');
$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
$totalAll = $stmt->fetch()['total'];
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE pickup_date = ?");
$stmt->execute([$today]);
$totalToday = $stmt->fetch()['total'];
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE pickup_date > ?");
$stmt->execute([$today]);
$totalUpcoming = $stmt->fetch()['total'];
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE pickup_date < ?");
$stmt->execute([$today]);
$totalPast = $stmt->fetch()['total'];

// Payment statistics
$stmt = $pdo->query("SELECT COUNT(DISTINCT o.id) as total FROM orders o LEFT JOIN payment p ON o.id = p.order_id WHERE p.id IS NOT NULL");
$totalPaid = $stmt->fetch()['total'];
$stmt = $pdo->query("SELECT COUNT(DISTINCT o.id) as total FROM orders o LEFT JOIN payment p ON o.id = p.order_id WHERE p.id IS NULL");
$totalPending = $stmt->fetch()['total'];

// Get all customers for dropdown
$stmt = $pdo->query("SELECT id, firstname, lastname, phone FROM customer WHERE status = 'active' ORDER BY firstname");
$customers = $stmt->fetchAll();

// Get order for editing (for modal)
$editOrder = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("
        SELECT o.*, p.amount, p.payment_date,
               CASE WHEN p.id IS NOT NULL THEN 'paid' ELSE 'pending' END as payment_status
        FROM orders o 
        LEFT JOIN payment p ON o.id = p.order_id 
        WHERE o.id = ?
    ");
    $stmt->execute([$_GET['edit']]);
    $editOrder = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - EcoWaste Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
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

        /* Action Bar */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
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
            min-width: 150px;
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

        .badge {
            padding: 0.2rem 0.7rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-today {
            background: #fef3c7;
            color: #d97706;
        }

        .badge-upcoming {
            background: #dbeafe;
            color: #2563eb;
        }

        .badge-past {
            background: #e2e3e5;
            color: #4b5563;
        }

        .badge-paid {
            background: #dcfce7;
            color: #15803d;
        }

        .badge-pending {
            background: #fee2e2;
            color: #b91c1c;
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
            max-width: 650px;
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

        .form-group input, .form-group select, .form-group textarea {
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

        .order-details-grid {
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
            .order-details-grid {
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
            <li class="nav-item"><a href="orders.php" class="nav-link active"><i class="fas fa-truck"></i> Orders</a></li>
            <li class="nav-item"><a href="payments.php" class="nav-link"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li class="nav-item"><a href="support.php" class="nav-link"><i class="fas fa-headset"></i> Support</a></li>
            <li class="nav-item logout-link"><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Order Management</h1>
                <p>Manage waste collection orders, track status, and process payments</p>
            </div>
            <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span>Total Orders</span>
                    <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                </div>
                <div class="stat-number"><?php echo $totalAll; ?></div>
                <div class="stat-sub">All time orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span>Today's Pickups</span>
                    <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                </div>
                <div class="stat-number"><?php echo $totalToday; ?></div>
                <div class="stat-sub">Scheduled for today</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span>Upcoming</span>
                    <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
                </div>
                <div class="stat-number"><?php echo $totalUpcoming; ?></div>
                <div class="stat-sub">Future pickups</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span>Completed</span>
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="stat-number"><?php echo $totalPast; ?></div>
                <div class="stat-sub">Past pickups</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span>Paid Orders</span>
                    <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
                </div>
                <div class="stat-number"><?php echo $totalPaid; ?></div>
                <div class="stat-sub">Payment completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span>Pending Payment</span>
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                </div>
                <div class="stat-number"><?php echo $totalPending; ?></div>
                <div class="stat-sub">Awaiting payment</div>
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
            <button class="btn-primary" onclick="openCreateModal()"><i class="fas fa-plus-circle"></i> Create New Order</button>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Order ID, Customer, Phone..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status" onchange="this.form.submit()">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Orders</option>
                            <option value="today" <?php echo $status_filter == 'today' ? 'selected' : ''; ?>>Today's Orders</option>
                            <option value="upcoming" <?php echo $status_filter == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                            <option value="past" <?php echo $status_filter == 'past' ? 'selected' : ''; ?>>Past/Completed</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Payment</label>
                        <select name="payment" onchange="this.form.submit()">
                            <option value="all" <?php echo $payment_filter == 'all' ? 'selected' : ''; ?>>All Payments</option>
                            <option value="paid" <?php echo $payment_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="pending" <?php echo $payment_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>" onchange="this.form.submit()">
                    </div>
                    <div class="filter-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>" onchange="this.form.submit()">
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <?php if ($search || $status_filter != 'all' || $payment_filter != 'all' || $date_from || $date_to): ?>
                            <a href="orders.php" class="btn-outline"><i class="fas fa-times"></i> Clear Filters</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Pickup Date</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order):
                            $today = date('Y-m-d');
                            if ($order['pickup_date'] == $today) {
                                $status_class = 'badge-today';
                                $status_text = 'Today';
                            } elseif ($order['pickup_date'] > $today) {
                                $status_class = 'badge-upcoming';
                                $status_text = 'Upcoming';
                            } else {
                                $status_class = 'badge-past';
                                $status_text = 'Completed';
                            }
                            
                            $payment_class = $order['payment_date'] ? 'badge-paid' : 'badge-pending';
                            $payment_text = $order['payment_date'] ? 'Paid' : 'Pending';
                        ?>
                            <tr>
                                <td><strong>#<?php echo $order['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($order['firstname'] . ' ' . $order['lastname']); ?></td>
                                <td>
                                    <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['phone']); ?></div>
                                    <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($order['email']); ?></div>
                                </td>
                                <td>
                                    <strong><?php echo date('M d, Y', strtotime($order['pickup_date'])); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($order['village'] . ', ' . $order['housenumber']); ?></td>
                                <td><span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                <td><span class="badge <?php echo $payment_class; ?>"><?php echo $payment_text; ?></span>
                                    <?php if ($order['amount']): ?><br><small>RWF <?php echo number_format($order['amount']); ?></small><?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <a href="javascript:void(0)" onclick="viewOrder(<?php echo $order['id']; ?>)" class="btn-icon btn-view"><i class="fas fa-eye"></i> View</a>
                                    <a href="?edit=<?php echo $order['id']; ?>" class="btn-icon btn-edit" onclick="openEditModal(event, <?php echo $order['id']; ?>)"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="?delete=<?php echo $order['id']; ?>" class="btn-icon btn-delete" onclick="return confirm('Delete this order? This will also delete associated payment records.')"><i class="fas fa-trash"></i> Del</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding: 3rem;">
                                <i class="fas fa-box-open" style="font-size: 2rem; color: #cbd5e1;"></i>
                                <p style="margin-top: 0.5rem;">No orders found</p>
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
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&payment=<?php echo $payment_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&payment=<?php echo $payment_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&payment=<?php echo $payment_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Create Order Modal -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-plus-circle"></i> Create New Order</h2>
            <span class="close-modal" onclick="closeCreateModal()">&times;</span>
        </div>
        <form method="POST">
            <div class="form-group">
                <label>Select Customer *</label>
                <select name="customer_id" required>
                    <option value="">-- Select Customer --</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo $customer['id']; ?>">
                            <?php echo htmlspecialchars($customer['firstname'] . ' ' . $customer['lastname'] . ' - ' . $customer['phone']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Pickup Date *</label>
                    <input type="date" name="pickup_date" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Amount (RWF) *</label>
                    <input type="number" name="amount" value="5000" min="1000" step="500" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Payment Status</label>
                <select name="payment_status">
                    <option value="pending">Pending</option>
                    <option value="paid">Paid (Record payment now)</option>
                </select>
            </div>
            
            <div class="info-note" style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">
                <i class="fas fa-info-circle"></i> Default pickup fee: 5,000 RWF
            </div>
            
            <button type="submit" name="create_order" class="btn-primary" style="width:100%; margin-top:1rem;">Create Order</button>
        </form>
    </div>
</div>

<!-- Edit Order Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Edit Order</h2>
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="order_id" id="edit_order_id">
            <div class="form-group">
                <label>Pickup Date</label>
                <input type="date" name="pickup_date" id="edit_pickup_date" required>
            </div>
            <div class="form-group">
                <label>Amount (RWF)</label>
                <input type="number" name="amount" id="edit_amount" required>
            </div>
            <div class="form-group">
                <label>Payment Status</label>
                <select name="payment_status" id="edit_payment_status">
                    <option value="pending">Pending</option>
                    <option value="paid">Paid</option>
                </select>
            </div>
            <button type="submit" name="update_order" class="btn-primary" style="width:100%; margin-top:1rem;">Update Order</button>
        </form>
    </div>
</div>

<!-- View Order Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2><i class="fas fa-file-invoice"></i> Order Details</h2>
            <span class="close-modal" onclick="closeViewModal()">&times;</span>
        </div>
        <div id="orderDetails"></div>
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

    function openCreateModal() { 
        document.getElementById('createModal').classList.add('show'); 
    }
    
    function closeCreateModal() { 
        document.getElementById('createModal').classList.remove('show'); 
    }

    function openEditModal(e, id) {
        e.preventDefault();
        fetch(`orders.php?ajax_get_order=${id}`)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    document.getElementById('edit_order_id').value = data.order.id;
                    document.getElementById('edit_pickup_date').value = data.order.pickup_date;
                    document.getElementById('edit_amount').value = data.order.amount || 5000;
                    document.getElementById('edit_payment_status').value = data.order.payment_date ? 'paid' : 'pending';
                    document.getElementById('editModal').classList.add('show');
                } else {
                    alert('Error loading order data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading order data');
            });
    }
    
    function closeEditModal() { 
        document.getElementById('editModal').classList.remove('show'); 
    }

    function viewOrder(id) {
        fetch(`orders.php?ajax_get_order=${id}`)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    const order = data.order;
                    const today = new Date().toISOString().split('T')[0];
                    const pickupDate = order.pickup_date;
                    let statusBadge = '';
                    if (pickupDate === today) {
                        statusBadge = '<span class="badge badge-today">Today</span>';
                    } else if (pickupDate > today) {
                        statusBadge = '<span class="badge badge-upcoming">Upcoming</span>';
                    } else {
                        statusBadge = '<span class="badge badge-past">Completed</span>';
                    }
                    
                    const paymentBadge = order.payment_date ? 
                        '<span class="badge badge-paid">Paid</span>' : 
                        '<span class="badge badge-pending">Pending</span>';
                    
                    document.getElementById('orderDetails').innerHTML = `
                        <div class="order-details-grid">
                            <div class="detail-item">
                                <div class="detail-label">Order ID</div>
                                <div class="detail-value">#${order.id}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Status</div>
                                <div class="detail-value">${statusBadge}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Customer Name</div>
                                <div class="detail-value">${order.firstname} ${order.lastname}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Contact</div>
                                <div class="detail-value">${order.phone}<br>${order.email}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Pickup Date</div>
                                <div class="detail-value">${new Date(order.pickup_date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Amount</div>
                                <div class="detail-value">RWF ${order.amount ? Number(order.amount).toLocaleString() : '0'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Payment Status</div>
                                <div class="detail-value">${paymentBadge}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Payment Date</div>
                                <div class="detail-value">${order.payment_date ? new Date(order.payment_date).toLocaleDateString() : 'Not paid yet'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Address</div>
                                <div class="detail-value">${order.village}, ${order.cell}, ${order.sector}<br>${order.district}, ${order.province}<br>House: ${order.housenumber}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Created At</div>
                                <div class="detail-value">${new Date(order.created_at).toLocaleString()}</div>
                            </div>
                        </div>
                    `;
                    document.getElementById('viewModal').classList.add('show');
                } else {
                    alert('Error loading order details');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading order details');
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
</script>
</body>
</html>