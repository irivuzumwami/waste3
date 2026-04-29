<?php
// payments.php - Standalone Payments Management Page (No role restrictions)
session_start();
require_once 'config/database.php';

$success = '';
$error = '';

// ============================================
// UPDATE PAYMENT HANDLING
// ============================================
if (isset($_POST['update_payment'])) {
    $payment_id = $_POST['payment_id'];
    $payment_date = $_POST['payment_date'];
    
    $stmt = $pdo->prepare("UPDATE payment SET payment_date = ? WHERE id = ?");
    if ($stmt->execute([$payment_date, $payment_id])) {
        $success = "Payment updated successfully!";
        header("Location: payments.php?success=updated");
        exit;
    } else {
        $error = "Failed to update payment!";
    }
}

// ============================================
// MARK PAYMENT AS PAID
// ============================================
if (isset($_GET['mark_paid'])) {
    $payment_id = $_GET['mark_paid'];
    $stmt = $pdo->prepare("UPDATE payment SET payment_date = CURDATE() WHERE id = ?");
    if ($stmt->execute([$payment_id])) {
        $success = "Payment marked as paid!";
        header("Location: payments.php?success=marked");
        exit;
    }
}

// ============================================
// DELETE PAYMENT HANDLING
// ============================================
if (isset($_GET['delete_payment'])) {
    $payment_id = $_GET['delete_payment'];
    $stmt = $pdo->prepare("DELETE FROM payment WHERE id = ?");
    if ($stmt->execute([$payment_id])) {
        $success = "Payment deleted successfully!";
        header("Location: payments.php?success=deleted");
        exit;
    }
}

// ============================================
// GET ALL PAYMENTS WITH ORDER DETAILS
// ============================================
$stmt = $pdo->query("
    SELECT 
        p.id AS payment_id,
        p.order_id,
        p.customer_id,
        p.amount,
        p.payment_date,
        c.firstname,
        c.lastname,
        c.phone,
        c.housenumber,
        c.village,
        o.pickup_date,
        o.waste_type,
        CASE 
            WHEN p.payment_date IS NOT NULL AND p.payment_date != '0000-00-00' THEN 'Paid'
            ELSE 'Pending'
        END AS payment_status
    FROM payment p
    JOIN customer c ON p.customer_id = c.id
    LEFT JOIN orders o ON p.order_id = o.id
    ORDER BY p.id DESC
");
$payments = $stmt->fetchAll();

// Get pending payments count
$pending_count = 0;
$paid_count = 0;
$total_amount = 0;
foreach ($payments as $payment) {
    if ($payment['payment_status'] == 'Pending') {
        $pending_count++;
    } else {
        $paid_count++;
    }
    $total_amount += $payment['amount'];
}

// Get single payment for editing
$edit_payment = null;
if (isset($_GET['edit_payment'])) {
    $stmt = $pdo->prepare("SELECT * FROM payment WHERE id = ?");
    $stmt->execute([$_GET['edit_payment']]);
    $edit_payment = $stmt->fetch();
}

// Get monthly summary
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(payment_date, '%Y-%m') as month,
        COUNT(*) as count,
        SUM(amount) as total
    FROM payment
    WHERE payment_date IS NOT NULL AND payment_date != '0000-00-00'
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
$monthly_summary = $stmt->fetchAll();

// Check if logo exists
$logoExists = file_exists(__DIR__ . '/logo.jpeg');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments Management - WMS</title>
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
        
        .btn-success {
            background: var(--teal-accent);
            color: #fff;
        }
        
        .btn-success:hover {
            background: #00a87e;
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
        
        .amount-highlight {
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
            <a href="orders.php" class="btn-home"><i class="fas fa-clipboard-list"></i> Orders</a>
            <a href="payments.php" class="btn-home" style="background: var(--teal-accent);"><i class="fas fa-credit-card"></i> Payments</a>
        </div>
    </nav>
    
    <div class="container">
        <h1><i class="fas fa-credit-card"></i> Payments Management</h1>
        
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
                <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
                <h3>Total Payments</h3>
                <div class="stat-number"><?php echo count($payments); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <h3>Paid Payments</h3>
                <div class="stat-number"><?php echo $paid_count; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <h3>Pending Payments</h3>
                <div class="stat-number"><?php echo $pending_count; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                <h3>Total Amount</h3>
                <div class="stat-number">RWF <?php echo number_format($total_amount); ?></div>
            </div>
        </div>
        
        <!-- Monthly Summary -->
        <?php if(count($monthly_summary) > 0): ?>
        <div class="section-card">
            <h3 class="section-title"><i class="fas fa-chart-line"></i> Monthly Summary</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Payments Count</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($monthly_summary as $summary): ?>
                        <tr>
                            <td><?php echo date('F Y', strtotime($summary['month'] . '-01')); ?></td>
                            <td><?php echo $summary['count']; ?></td>
                            <td>RWF <?php echo number_format($summary['total']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Payments List Section -->
        <div class="section-card">
            <h3 class="section-title"><i class="fas fa-list"></i> All Payments</h3>
            
            <?php if(count($payments) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Waste Type</th>
                                <th>Amount</th>
                                <th>Pickup Date</th>
                                <th>Payment Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($payments as $payment): ?>
                            <tr>
                                <td>#<?php echo $payment['payment_id']; ?></td>
                                <td>#<?php echo $payment['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($payment['firstname'] . ' ' . $payment['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($payment['phone']); ?></td>
                                <td><?php echo htmlspecialchars($payment['housenumber'] . ', ' . $payment['village']); ?></td>
                                <td>
                                    <?php
                                    $type_class = '';
                                    switch($payment['waste_type']) {
                                        case 'General Waste': $type_class = 'waste-general'; break;
                                        case 'Recyclable Waste': $type_class = 'waste-recyclable'; break;
                                        case 'Organic Waste': $type_class = 'waste-organic'; break;
                                        case 'Electronic Waste': $type_class = 'waste-electronic'; break;
                                        case 'Hazardous Waste': $type_class = 'waste-hazardous'; break;
                                        default: $type_class = 'waste-general';
                                    }
                                    ?>
                                    <span class="waste-badge <?php echo $type_class; ?>"><?php echo $payment['waste_type'] ?? 'General Waste'; ?></span>
                                </td>
                                <td class="amount-highlight">RWF <?php echo number_format($payment['amount']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($payment['pickup_date'])); ?></td>
                                <td><?php echo ($payment['payment_date'] && $payment['payment_date'] != '0000-00-00') ? date('M j, Y', strtotime($payment['payment_date'])) : '<span style="color: #ffc107;">Not Paid</span>'; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $payment['payment_status'] == 'Paid' ? 'status-paid' : 'status-pending'; ?>">
                                        <?php echo $payment['payment_status']; ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <?php if($payment['payment_status'] == 'Pending'): ?>
                                        <a href="?mark_paid=<?php echo $payment['payment_id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Mark this payment as paid?')">
                                            <i class="fas fa-check"></i> Mark Paid
                                        </a>
                                    <?php endif; ?>
                                    <a href="?edit_payment=<?php echo $payment['payment_id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="?delete_payment=<?php echo $payment['payment_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this payment?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; padding: 2rem;">No payments found</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Edit Payment Modal -->
    <?php if($edit_payment): ?>
    <div id="editModal" class="modal show">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
            <h3><i class="fas fa-edit"></i> Edit Payment #<?php echo $edit_payment['id']; ?></h3>
            <form method="POST">
                <input type="hidden" name="payment_id" value="<?php echo $edit_payment['id']; ?>">
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Payment Date</label>
                    <input type="date" name="payment_date" value="<?php echo ($edit_payment['payment_date'] && $edit_payment['payment_date'] != '0000-00-00') ? $edit_payment['payment_date'] : ''; ?>">
                    <small>Leave empty for pending payment</small>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> Amount</label>
                    <input type="text" value="RWF <?php echo number_format($edit_payment['amount']); ?>" disabled>
                </div>
                <button type="submit" name="update_payment" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                    <i class="fas fa-save"></i> Update Payment
                </button>
            </form>
        </div>
    </div>
    
    <script>
        function closeEditModal() {
            window.location.href = 'payments.php';
        }
    </script>
    <?php endif; ?>
    
    <script>
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>