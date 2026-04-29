<?php
// orders.php - Standalone Orders Management Page (No role restrictions)
session_start();
require_once 'config/database.php';

$success = '';
$error = '';

// ============================================
// CREATE ORDER FORM HANDLING
// ============================================
if (isset($_POST['create_order'])) {
    $customer_id = $_POST['customer_id'];
    $pickup_date = $_POST['pickup_date'];
    $waste_type = $_POST['waste_type'];
    
    // Calculate amount based on waste type
    $amount = 5000;
    switch($waste_type) {
        case 'General Waste': $amount = 5000; break;
        case 'Recyclable Waste': $amount = 4000; break;
        case 'Organic Waste': $amount = 3000; break;
        case 'Electronic Waste': $amount = 10000; break;
        case 'Hazardous Waste': $amount = 15000; break;
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO orders (customer_id, pickup_date, waste_type) VALUES (?, ?, ?)");
        $stmt->execute([$customer_id, $pickup_date, $waste_type]);
        $order_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO payment (order_id, customer_id, amount, payment_date) VALUES (?, ?, ?, '0000-00-00')");
        $stmt->execute([$order_id, $customer_id, $amount]);
        
        $pdo->commit();
        $success = "Order created successfully!";
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error = "Failed to create order: " . $e->getMessage();
    }
}

// ============================================
// DELETE ORDER HANDLING
// ============================================
if (isset($_GET['delete_order'])) {
    $order_id = $_GET['delete_order'];
    $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
    if ($stmt->execute([$order_id])) {
        $success = "Order deleted successfully!";
        header("Location: orders.php?success=deleted");
        exit;
    }
}

// ============================================
// UPDATE ORDER HANDLING
// ============================================
if (isset($_POST['update_order'])) {
    $order_id = $_POST['order_id'];
    $pickup_date = $_POST['pickup_date'];
    $waste_type = $_POST['waste_type'];
    
    $amount = 5000;
    switch($waste_type) {
        case 'General Waste': $amount = 5000; break;
        case 'Recyclable Waste': $amount = 4000; break;
        case 'Organic Waste': $amount = 3000; break;
        case 'Electronic Waste': $amount = 10000; break;
        case 'Hazardous Waste': $amount = 15000; break;
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE orders SET pickup_date = ?, waste_type = ? WHERE id = ?");
        $stmt->execute([$pickup_date, $waste_type, $order_id]);
        
        $stmt = $pdo->prepare("UPDATE payment SET amount = ? WHERE order_id = ?");
        $stmt->execute([$amount, $order_id]);
        
        $pdo->commit();
        $success = "Order updated successfully!";
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error = "Failed to update order: " . $e->getMessage();
    }
}

// ============================================
// GET ALL ORDERS
// ============================================
$stmt = $pdo->query("
    SELECT 
        o.id AS order_id,
        o.customer_id,
        o.pickup_date,
        o.created_at,
        o.waste_type,
        c.firstname,
        c.lastname,
        c.phone,
        c.housenumber,
        c.village,
        p.id AS payment_id,
        p.amount,
        p.payment_date,
        CASE 
            WHEN p.payment_date IS NOT NULL AND p.payment_date != '0000-00-00' THEN 'Paid'
            ELSE 'Pending'
        END AS payment_status
    FROM orders o
    JOIN customer c ON o.customer_id = c.id
    LEFT JOIN payment p ON o.id = p.order_id
    ORDER BY o.id DESC
");
$orders = $stmt->fetchAll();

// Get all customers for dropdown
$stmt = $pdo->query("SELECT id, firstname, lastname, phone FROM customer WHERE status = 'active' ORDER BY firstname");
$customers = $stmt->fetchAll();

// Get single order for editing
$edit_order = null;
if (isset($_GET['edit_order'])) {
    $stmt = $pdo->prepare("
        SELECT o.*, p.amount 
        FROM orders o 
        LEFT JOIN payment p ON o.id = p.order_id 
        WHERE o.id = ?
    ");
    $stmt->execute([$_GET['edit_order']]);
    $edit_order = $stmt->fetch();
}

// Check if logo exists
$logoExists = file_exists(__DIR__ . '/logo.jpeg');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - WMS</title>
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
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-dark);
            color: #fff;
        }
        
        /* Navigation Bar */
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
        
        /* Logo */
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        
        .logo-img {
            width: 45px;
            height: 45px;
            object-fit: cover;
            border-radius: 10px;
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
            font-size: 22px;
            font-weight: 800;
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
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: #fff;
            text-decoration: none;
            transition: 0.3s;
        }
        
        .nav-links a:hover {
            color: var(--secondary);
        }
        
        .btn-home {
            background: var(--primary);
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            border: 1px solid var(--secondary);
        }
        
        /* Container */
        .container {
            max-width: 1400px;
            margin: 80px auto 2rem;
            padding: 2rem;
        }
        
        h1 {
            color: var(--secondary);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        /* Forms */
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.8rem;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.2);
        }
        
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        /* Buttons */
        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
        
        .btn-warning {
            background: #f39c12;
            color: #fff;
        }
        
        .btn-warning:hover {
            background: #e67e22;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: rgba(255,68,68,0.8);
            color: #fff;
        }
        
        .btn-danger:hover {
            background: #ff4444;
        }
        
        .btn-sm {
            padding: 0.3rem 0.8rem;
            font-size: 0.8rem;
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
        
        .status-paid {
            background: rgba(0,196,154,0.2);
            color: var(--teal-accent);
        }
        
        .status-pending {
            background: rgba(255,193,7,0.2);
            color: #ffc107;
        }
        
        .waste-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .waste-general { background: rgba(30,58,138,0.3); color: var(--secondary); }
        .waste-recyclable { background: rgba(0,196,154,0.3); color: var(--teal-accent); }
        .waste-organic { background: rgba(46,204,113,0.3); color: #2ecc71; }
        .waste-electronic { background: rgba(231,76,60,0.3); color: #e74c3c; }
        .waste-hazardous { background: rgba(231,76,60,0.3); color: #e74c3c; }
        
        /* Modal */
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
            max-width: 500px;
            border: 1px solid var(--secondary);
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }
        
        .close-modal:hover {
            color: var(--secondary);
        }
        
        .modal-content h3 {
            margin-bottom: 1.5rem;
            color: var(--secondary);
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
        
        .alert-error {
            background: rgba(255,68,68,0.2);
            border: 1px solid #ff4444;
            color: #ff8888;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .amount-display {
            background: rgba(0,196,154,0.15);
            padding: 0.8rem;
            border-radius: 10px;
            text-align: center;
            font-weight: bold;
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
                gap: 0.8rem;
            }
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            .container {
                padding: 1rem;
                margin-top: 140px;
            }
            .row {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="logo">
            <?php if($logoExists): ?>
                <img src="logo.jpeg" alt="WMS Logo" class="logo-img">
            <?php endif; ?>
            <div class="logo-text">
                <div class="logo-main"><span class="wms">WMS</span></div>
                <div class="logo-tagline">For Cleaner Communities</div>
            </div>
        </a>
        <div class="nav-links">
            <a href="index.php" class="btn-home"><i class="fas fa-home"></i> Home</a>
            <a href="orders.php" class="btn-home" style="background: var(--teal-accent);"><i class="fas fa-clipboard-list"></i> Orders</a>
        </div>
    </nav>
    
    <div class="container">
        <h1><i class="fas fa-clipboard-list"></i> Orders Management</h1>
        
        <?php if(isset($success) && $success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
            <script>setTimeout(() => document.querySelector('.alert')?.remove(), 3000);</script>
        <?php endif; ?>
        
        <?php if(isset($error) && $error): ?>
            <div class="alert alert-error">❌ <?php echo $error; ?></div>
            <script>setTimeout(() => document.querySelector('.alert')?.remove(), 3000);</script>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                <h3>Total Orders</h3>
                <div class="stat-number"><?php echo count($orders); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <h3>Paid Orders</h3>
                <div class="stat-number">
                    <?php 
                    $paid = 0;
                    foreach($orders as $o) {
                        if($o['payment_status'] == 'Paid') $paid++;
                    }
                    echo $paid;
                    ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <h3>Pending Orders</h3>
                <div class="stat-number">
                    <?php 
                    $pending = 0;
                    foreach($orders as $o) {
                        if($o['payment_status'] == 'Pending') $pending++;
                    }
                    echo $pending;
                    ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
                <h3>This Week's Orders</h3>
                <div class="stat-number">
                    <?php 
                    $week = 0;
                    foreach($orders as $o) {
                        if(strtotime($o['pickup_date']) >= strtotime('monday this week')) $week++;
                    }
                    echo $week;
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Create Order Section -->
        <div class="section-card">
            <h3 class="section-title"><i class="fas fa-plus-circle"></i> Create New Order</h3>
            
            <form method="POST">
                <div class="row">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Select Customer *</label>
                        <select name="customer_id" required>
                            <option value="">-- Select Customer --</option>
                            <?php foreach($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>">
                                    <?php echo htmlspecialchars($customer['firstname'] . ' ' . $customer['lastname'] . ' - ' . $customer['phone']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Pickup Date *</label>
                        <input type="date" name="pickup_date" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-trash-alt"></i> Waste Type *</label>
                    <select name="waste_type" id="waste_type" required onchange="updateAmount()">
                        <option value="General Waste">🏠 General Waste - RWF 5,000</option>
                        <option value="Recyclable Waste">♻️ Recyclable Waste - RWF 4,000</option>
                        <option value="Organic Waste">🌿 Organic Waste - RWF 3,000</option>
                        <option value="Electronic Waste">💻 Electronic Waste - RWF 10,000</option>
                        <option value="Hazardous Waste">⚠️ Hazardous Waste - RWF 15,000</option>
                    </select>
                </div>
                
                <div class="amount-display" id="amount_display">
                    <i class="fas fa-money-bill-wave"></i> Amount: RWF 5,000
                </div>
                
                <button type="submit" name="create_order" class="btn btn-primary" style="margin-top: 1rem; width: 100%;">
                    <i class="fas fa-check"></i> Create Order
                </button>
            </form>
        </div>
        
        <!-- Orders List Section -->
        <div class="section-card">
            <h3 class="section-title"><i class="fas fa-list"></i> All Orders</h3>
            
            <?php if(count($orders) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Pickup Date</th>
                                <th>Waste Type</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($order['firstname'] . ' ' . $order['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($order['phone']); ?></td>
                                <td><?php echo htmlspecialchars($order['housenumber'] . ', ' . $order['village']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($order['pickup_date'])); ?></td>
                                <td>
                                    <?php
                                    $type_class = '';
                                    switch($order['waste_type']) {
                                        case 'General Waste': $type_class = 'waste-general'; break;
                                        case 'Recyclable Waste': $type_class = 'waste-recyclable'; break;
                                        case 'Organic Waste': $type_class = 'waste-organic'; break;
                                        case 'Electronic Waste': $type_class = 'waste-electronic'; break;
                                        case 'Hazardous Waste': $type_class = 'waste-hazardous'; break;
                                    }
                                    ?>
                                    <span class="waste-badge <?php echo $type_class; ?>"><?php echo $order['waste_type']; ?></span>
                                </td>
                                <td>RWF <?php echo number_format($order['amount']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $order['payment_status'] == 'Paid' ? 'status-paid' : 'status-pending'; ?>">
                                        <?php echo $order['payment_status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                <td class="action-buttons">
                                    <a href="?edit_order=<?php echo $order['order_id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="?delete_order=<?php echo $order['order_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this order?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; padding: 2rem;">No orders found</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Edit Order Modal -->
    <?php if($edit_order): ?>
    <div id="editModal" class="modal show">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
            <h3><i class="fas fa-edit"></i> Edit Order #<?php echo $edit_order['id']; ?></h3>
            <form method="POST">
                <input type="hidden" name="order_id" value="<?php echo $edit_order['id']; ?>">
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Pickup Date</label>
                    <input type="date" name="pickup_date" value="<?php echo $edit_order['pickup_date']; ?>" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-trash-alt"></i> Waste Type</label>
                    <select name="waste_type" id="edit_waste_type" required onchange="updateEditAmount()">
                        <option value="General Waste" <?php echo $edit_order['waste_type'] == 'General Waste' ? 'selected' : ''; ?>>🏠 General Waste - RWF 5,000</option>
                        <option value="Recyclable Waste" <?php echo $edit_order['waste_type'] == 'Recyclable Waste' ? 'selected' : ''; ?>>♻️ Recyclable Waste - RWF 4,000</option>
                        <option value="Organic Waste" <?php echo $edit_order['waste_type'] == 'Organic Waste' ? 'selected' : ''; ?>>🌿 Organic Waste - RWF 3,000</option>
                        <option value="Electronic Waste" <?php echo $edit_order['waste_type'] == 'Electronic Waste' ? 'selected' : ''; ?>>💻 Electronic Waste - RWF 10,000</option>
                        <option value="Hazardous Waste" <?php echo $edit_order['waste_type'] == 'Hazardous Waste' ? 'selected' : ''; ?>>⚠️ Hazardous Waste - RWF 15,000</option>
                    </select>
                </div>
                <div class="amount-display" id="edit_amount_display">
                    Amount: RWF <?php echo number_format($edit_order['amount']); ?>
                </div>
                <button type="submit" name="update_order" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                    <i class="fas fa-save"></i> Update Order
                </button>
            </form>
        </div>
    </div>
    
    <script>
        function closeEditModal() {
            window.location.href = 'orders.php';
        }
        
        function updateEditAmount() {
            const wasteType = document.getElementById('edit_waste_type').value;
            const amountDisplay = document.getElementById('edit_amount_display');
            let amount = 5000;
            
            switch(wasteType) {
                case 'General Waste': amount = 5000; break;
                case 'Recyclable Waste': amount = 4000; break;
                case 'Organic Waste': amount = 3000; break;
                case 'Electronic Waste': amount = 10000; break;
                case 'Hazardous Waste': amount = 15000; break;
            }
            
            amountDisplay.innerHTML = `<i class="fas fa-money-bill-wave"></i> Amount: RWF ${amount.toLocaleString()}`;
        }
    </script>
    <?php endif; ?>
    
    <script>
        function updateAmount() {
            const wasteType = document.getElementById('waste_type').value;
            const amountDisplay = document.getElementById('amount_display');
            let amount = 5000;
            
            switch(wasteType) {
                case 'General Waste': amount = 5000; break;
                case 'Recyclable Waste': amount = 4000; break;
                case 'Organic Waste': amount = 3000; break;
                case 'Electronic Waste': amount = 10000; break;
                case 'Hazardous Waste': amount = 15000; break;
            }
            
            amountDisplay.innerHTML = `<i class="fas fa-money-bill-wave"></i> Amount: RWF ${amount.toLocaleString()}`;
        }
    </script>
</body>
</html>