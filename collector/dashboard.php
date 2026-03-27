<?php
// collector/dashboard.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'collector') {
    header('Location: ../index.php');
    exit;
}
require_once '../config/database.php';

$collector_id = $_SESSION['user_id'];

// Get collector details
$stmt = $pdo->prepare("SELECT * FROM workers WHERE id = ? AND role = 'collector'");
$stmt->execute([$collector_id]);
$collector = $stmt->fetch();

// Get pending payments
$stmt = $pdo->prepare("
    SELECT p.*, c.firstname, c.lastname, c.phone, c.email, c.housenumber, c.village,
           o.pickup_date
    FROM payment p 
    JOIN customer c ON p.customer_id = c.id 
    LEFT JOIN orders o ON p.order_id = o.id
    WHERE (p.payment_date IS NULL OR p.payment_date = '0000-00-00')
    ORDER BY p.id ASC
");
$stmt->execute();
$pending_payments = $stmt->fetchAll();

// Get recent collections
$stmt = $pdo->prepare("
    SELECT p.*, c.firstname, c.lastname, c.phone, c.village
    FROM payment p 
    JOIN customer c ON p.customer_id = c.id 
    WHERE (p.payment_date IS NOT NULL AND p.payment_date != '0000-00-00')
    ORDER BY p.payment_date DESC LIMIT 10
");
$stmt->execute();
$recent_collections = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Collector Dashboard - WMS</title>
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
        
        .status-pending {
            background: rgba(255,193,7,0.2);
            color: #ffc107;
        }
        
        .status-paid {
            background: rgba(0,196,154,0.2);
            color: var(--teal-accent);
        }
        
        .btn-collect {
            background: var(--teal-accent);
            color: #fff;
            border: none;
            padding: 0.4rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-collect:hover {
            background: #00a87e;
            transform: scale(1.05);
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
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($collector['firstname'] . ' ' . $collector['lastname']); ?></div>
            <div class="user-email"><?php echo htmlspecialchars($collector['email']); ?></div>
            <div class="user-role"><i class="fas fa-id-card"></i> Fee Collector</div>
        </div>
        <ul class="nav-menu">
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="collections.php"><i class="fas fa-history"></i> Collections</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-money-bill-wave"></i> Fee Collector Dashboard</h1>
            <a href="../logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <h3>Pending Payments</h3>
                <div class="stat-number"><?php echo count($pending_payments); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-history"></i></div>
                <h3>Recent Collections</h3>
                <div class="stat-number"><?php echo count($recent_collections); ?></div>
            </div>
        </div>
        
        <div class="section-card">
            <h3 class="section-title"><i class="fas fa-clock"></i> Pending Payments</h3>
            <?php if(count($pending_payments) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Amount</th>
                                <th>Pickup Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($pending_payments as $payment): ?>
                            <tr>
                                <td>#<?php echo $payment['id']; ?></td>
                                <td><?php echo htmlspecialchars($payment['firstname'] . ' ' . $payment['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($payment['phone']); ?></td>
                                <td><?php echo htmlspecialchars($payment['housenumber'] . ', ' . $payment['village']); ?></td>
                                <td><strong>RWF <?php echo number_format($payment['amount']); ?></strong></td>
                                <td><?php echo date('M j, Y', strtotime($payment['pickup_date'])); ?></td>
                                <td>
                                    <button type="button" class="btn-collect" onclick="collectPayment(<?php echo $payment['id']; ?>, <?php echo $payment['amount']; ?>)">
                                        <i class="fas fa-hand-holding-usd"></i> Collect
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; padding: 2rem; color: var(--text-muted);">
                    <i class="fas fa-check-circle"></i> No pending payments
                </p>
            <?php endif; ?>
        </div>
        
        <div class="section-card">
            <h3 class="section-title"><i class="fas fa-history"></i> Recent Collections</h3>
            <?php if(count($recent_collections) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Amount</th>
                                <th>Collection Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_collections as $collection): ?>
                            <tr>
                                <td>#<?php echo $collection['id']; ?></td>
                                <td><?php echo htmlspecialchars($collection['firstname'] . ' ' . $collection['lastname']); ?>
                                                                 Wasm
                                 <td class="stat-number"><?php echo number_format($collection['amount']); ?></td>
                                 <td><?php echo date('M j, Y', strtotime($collection['payment_date'])); ?></td>
                                 <td><span class="status-badge status-paid">Paid</span></td>
                               </tr>
                            <?php endforeach; ?>
                        </tbody>
                     </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; padding: 2rem; color: var(--text-muted);">
                    <i class="fas fa-receipt"></i> No collections yet
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }
        
        function collectPayment(paymentId, amount) {
            if(confirm(`Collect payment of RWF ${amount.toLocaleString()}?`)) {
                // Here you would submit a form or AJAX request
                window.location.href = `collect_payment.php?id=${paymentId}`;
            }
        }
        
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            if (window.innerWidth <= 768 && sidebar.classList.contains('show')) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
    </script>
</body>
</html>