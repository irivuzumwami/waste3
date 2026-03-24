<?php
// admin/dashboard.php - Professional Admin Dashboard for EcoWaste
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit;
}
require_once '../config/database.php';

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM customer");
$totalCustomers = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
$totalOrders = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM workers");
$totalWorkers = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT SUM(amount) as total FROM payment");
$totalRevenue = $stmt->fetch()['total'] ?? 0;

// Recent orders with customer details
$stmt = $pdo->query("
    SELECT o.*, c.firstname, c.lastname, c.phone, c.village, c.housenumber
    FROM orders o 
    JOIN customer c ON o.customer_id = c.id 
    ORDER BY o.id DESC LIMIT 5
");
$recentOrders = $stmt->fetchAll();

// Recent payments
$stmt = $pdo->query("
    SELECT p.*, o.id as order_id, c.firstname, c.lastname 
    FROM payment p 
    JOIN orders o ON p.order_id = o.id 
    JOIN customer c ON o.customer_id = c.id 
    ORDER BY p.id DESC LIMIT 5
");
$recentPayments = $stmt->fetchAll();

// Support tickets count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM support");
$supportTickets = $stmt->fetch()['total'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>EcoWaste Admin | Dashboard</title>
    <!-- Font Awesome 6 (Free) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <!-- Chart.js CDN -->
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
            line-height: 1.5;
        }

        /* Dashboard Layout */
        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* ========= SIDEBAR (Dark Modern) ========= */
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
            box-shadow: 2px 0 12px rgba(0,0,0,0.08);
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
            letter-spacing: -0.3px;
        }

        .logo-text p {
            font-size: 0.7rem;
            color: #94a3b8;
            margin-top: 2px;
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
            font-size: 1rem;
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
            font-size: 1.2rem;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.08);
            color: white;
        }

        .nav-link.active {
            background: #1e3a8a;
            color: white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .logout-link {
            margin-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.08);
            padding-top: 1rem;
        }

        /* ========= MAIN CONTENT ========= */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 1.8rem 2rem;
        }

        /* Top header */
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
            color: #0f172a;
        }

        .page-title p {
            color: #475569;
            font-size: 0.9rem;
        }

        .date-time {
            background: white;
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-weight: 500;
            font-size: 0.9rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #e2e8f0;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 20px -12px rgba(0,0,0,0.1);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.8rem;
        }

        .stat-header span {
            font-size: 0.85rem;
            font-weight: 500;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            background: #eef2ff;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1e3a8a;
            font-size: 1.4rem;
        }

        .stat-number {
            font-size: 2.3rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.3rem;
        }

        .stat-sub {
            font-size: 0.75rem;
            color: #5b6e8c;
        }

        /* Charts row */
        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: white;
            border-radius: 24px;
            padding: 1.2rem 1rem 1rem 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }

        .chart-card h3 {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            padding-left: 0.5rem;
            font-weight: 600;
        }

        canvas {
            max-height: 260px;
            width: 100%;
        }

        /* Section cards */
        .section-card {
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
            flex-wrap: wrap;
        }

        .section-header h2 {
            font-size: 1.3rem;
            font-weight: 700;
        }

        .btn-link {
            color: #1e3a8a;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .btn-link i {
            margin-left: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 0.9rem 0.5rem;
            text-align: left;
            border-bottom: 1px solid #eef2ff;
        }

        th {
            font-weight: 600;
            color: #334155;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        td {
            font-size: 0.9rem;
            color: #1e293b;
        }

        .badge {
            padding: 0.2rem 0.7rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success {
            background: #e0f2fe;
            color: #0369a1;
        }

        .badge-warning {
            background: #fef9c3;
            color: #854d0e;
        }

        .badge-info {
            background: #e0e7ff;
            color: #1e3a8a;
        }

        .amount {
            font-weight: 700;
            color: #0f172a;
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 260px;
            }
            .main-content {
                margin-left: 260px;
            }
            .charts-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1000;
                width: 260px;
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
                background: white;
                border: none;
                font-size: 1.4rem;
                padding: 0.5rem;
                border-radius: 10px;
                cursor: pointer;
            }
        }

        .menu-toggle {
            display: none;
        }

        .footer-note {
            text-align: center;
            padding: 1rem 0;
            font-size: 0.75rem;
            color: #62748c;
            border-top: 1px solid #e2e8f0;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-recycle"></i>
                </div>
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
            <li class="nav-item"><a href="dashboard.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="customers.php" class="nav-link"><i class="fas fa-users"></i> Customers</a></li>
            <li class="nav-item"><a href="workers.php" class="nav-link"><i class="fas fa-hard-hat"></i> Workers</a></li>
            <li class="nav-item"><a href="orders.php" class="nav-link"><i class="fas fa-truck"></i> Orders</a></li>
            <li class="nav-item"><a href="payments.php" class="nav-link"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li class="nav-item"><a href="support.php" class="nav-link"><i class="fas fa-headset"></i> Support</a></li>
            <li class="nav-item logout-link"><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?> · Monitor your operations</p>
            </div>
            <div class="date-time">
                <i class="far fa-calendar-alt"></i> 
                <?php echo date('l, F j, Y'); ?> &nbsp;|&nbsp;
                <i class="far fa-clock"></i> <?php echo date('h:i A'); ?>
            </div>
            <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span>Total Customers</span>
                    <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($totalCustomers); ?></div>
                <div class="stat-sub">Active accounts</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span>Total Orders</span>
                    <div class="stat-icon"><i class="fas fa-boxes"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($totalOrders); ?></div>
                <div class="stat-sub">Pickup requests</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span>Workers</span>
                    <div class="stat-icon"><i class="fas fa-people-arrows"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($totalWorkers); ?></div>
                <div class="stat-sub">Drivers & collectors</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span>Revenue</span>
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                </div>
                <div class="stat-number">RWF <?php echo number_format($totalRevenue); ?></div>
                <div class="stat-sub">Total collected</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span>Support Tickets</span>
                    <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($supportTickets); ?></div>
                <div class="stat-sub">Pending / Resolved</div>
            </div>
        </div>

        <!-- Charts Row: Revenue trend & Order status mock -->
        <div class="charts-row">
            <div class="chart-card">
                <h3><i class="fas fa-chart-bar" style="margin-right: 8px;"></i> Weekly Revenue (RWF)</h3>
                <canvas id="revenueChart" width="400" height="200"></canvas>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> Order Status (Last 30 days)</h3>
                <canvas id="orderStatusChart" width="400" height="200"></canvas>
            </div>
        </div>

        <!-- Recent Orders Table -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-truck-fast"></i> Recent Orders</h2>
                <a href="orders.php" class="btn-link">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr><th>Order ID</th><th>Customer</th><th>Phone</th><th>Pickup Date</th><th>Location</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if(count($recentOrders) > 0): foreach($recentOrders as $order): 
                            $statusClass = 'badge-info';
                            $statusText = 'Scheduled';
                            if(strtotime($order['pickup_date']) < time()) { $statusText = 'Completed'; $statusClass = 'badge-success'; }
                            elseif(strtotime($order['pickup_date']) == strtotime(date('Y-m-d'))) { $statusText = 'Today'; $statusClass = 'badge-warning'; }
                        ?>
                        <tr>
                            <td>#<?php echo $order['id']; ?></td>
                            <td><?php echo htmlspecialchars($order['firstname'].' '.$order['lastname']); ?></td>
                            <td><?php echo htmlspecialchars($order['phone']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($order['pickup_date'])); ?></td>
                            <td><?php echo htmlspecialchars($order['village']); ?></td>
                            <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="6" style="text-align:center;">No orders found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-credit-card"></i> Recent Payments</h2>
                <a href="payments.php" class="btn-link">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead><tr><th>Payment ID</th><th>Order #</th><th>Customer</th><th>Amount (RWF)</th><th>Payment Date</th></tr></thead>
                    <tbody>
                        <?php if(count($recentPayments) > 0): foreach($recentPayments as $pay): ?>
                        <tr>
                            <td>#<?php echo $pay['id']; ?></td>
                            <td>ORD-<?php echo $pay['order_id']; ?></td>
                            <td><?php echo htmlspecialchars($pay['firstname'].' '.$pay['lastname']); ?></td>
                            <td class="amount"><?php echo number_format($pay['amount']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($pay['payment_date'])); ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5">No payments recorded</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="footer-note">
            <i class="fas fa-charging-station"></i> EcoWaste Management System · Cleaner communities, smarter future
        </div>
    </main>
</div>

<script>
    // Mobile sidebar toggle
    const toggleBtn = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    if(toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
        document.addEventListener('click', (e) => {
            if(window.innerWidth <= 768 && sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== toggleBtn) {
                sidebar.classList.remove('open');
            }
        });
    }

    // Revenue Chart (weekly simulation using real DB data if available, else demo)
    const ctxRev = document.getElementById('revenueChart').getContext('2d');
    // We'll fetch real weekly totals if possible - but use safe fallback from PHP
    // For real data: we can call an API or embed via PHP: fetch last 7 days revenue from payment table.
    // Let's embed a dynamic dataset from payments (last 7 days)
    <?php
    $weekLabels = [];
    $weekAmounts = [];
    for($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $weekLabels[] = date('D', strtotime($date));
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as daily_total FROM payment WHERE payment_date = ?");
        $stmt->execute([$date]);
        $dayTotal = $stmt->fetch()['daily_total'];
        $weekAmounts[] = $dayTotal;
    }
    ?>
    new Chart(ctxRev, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($weekLabels); ?>,
            datasets: [{
                label: 'Revenue (RWF)',
                data: <?php echo json_encode($weekAmounts); ?>,
                borderColor: '#1e3a8a',
                backgroundColor: 'rgba(30,58,138,0.05)',
                borderWidth: 3,
                pointBackgroundColor: '#fbbf24',
                pointBorderColor: '#fff',
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: true, position: 'top' },
                tooltip: { callbacks: { label: (ctx) => `RWF ${ctx.raw.toLocaleString()}` } }
            },
            scales: { y: { beginAtZero: true, ticks: { callback: (val) => 'RWF ' + val.toLocaleString() } } }
        }
    });

    // Order Status Chart (dummy distribution, but could also be real)
    <?php
    $totalOrdersCount = $totalOrders;
    $completedOrders = 0;
    $upcomingOrders = 0;
    $todayOrders = 0;
    $stmt = $pdo->query("SELECT pickup_date FROM orders");
    $allDates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $today = date('Y-m-d');
    foreach($allDates as $ord) {
        if(strtotime($ord['pickup_date']) < strtotime($today)) $completedOrders++;
        elseif($ord['pickup_date'] == $today) $todayOrders++;
        else $upcomingOrders++;
    }
    ?>
    const ctxPie = document.getElementById('orderStatusChart').getContext('2d');
    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'Today\'s Pickups', 'Upcoming'],
            datasets: [{
                data: [<?php echo $completedOrders; ?>, <?php echo $todayOrders; ?>, <?php echo $upcomingOrders; ?>],
                backgroundColor: ['#00c49a', '#fbbf24', '#1e3a8a'],
                borderWidth: 0,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${ctx.raw} orders` } }
            },
            cutout: '65%'
        }
    });
</script>
</body>
</html>