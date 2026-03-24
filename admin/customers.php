<?php
// admin/customers.php - Customer Management for EcoWaste Admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit;
}
require_once '../config/database.php';

// Handle Customer Actions
$action = $_GET['action'] ?? 'list';
$message = '';
$error = '';

// Add Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $status = $_POST['status'];
    $housenumber = trim($_POST['housenumber']);
    $province = trim($_POST['province']);
    $district = trim($_POST['district']);
    $sector = trim($_POST['sector']);
    $cell = trim($_POST['cell']);
    $village = trim($_POST['village']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO customer (firstname, lastname, email, phone, status, housenumber, province, district, sector, cell, village, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$firstname, $lastname, $email, $phone, $status, $housenumber, $province, $district, $sector, $cell, $village, $password]);
        $message = "Customer added successfully!";
    } catch (PDOException $e) {
        $error = "Error: " . ($e->errorInfo[1] == 1062 ? "Email already exists!" : "Database error");
    }
}

// Update Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
    $id = $_POST['customer_id'];
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $status = $_POST['status'];
    $housenumber = trim($_POST['housenumber']);
    $province = trim($_POST['province']);
    $district = trim($_POST['district']);
    $sector = trim($_POST['sector']);
    $cell = trim($_POST['cell']);
    $village = trim($_POST['village']);
    
    try {
        $stmt = $pdo->prepare("UPDATE customer SET firstname=?, lastname=?, email=?, phone=?, status=?, housenumber=?, province=?, district=?, sector=?, cell=?, village=? WHERE id=?");
        $stmt->execute([$firstname, $lastname, $email, $phone, $status, $housenumber, $province, $district, $sector, $cell, $village, $id]);
        $message = "Customer updated successfully!";
    } catch (PDOException $e) {
        $error = "Error updating customer: " . $e->getMessage();
    }
}

// Delete Customer
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        // Check if customer has orders
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
        $stmt->execute([$id]);
        $orderCount = $stmt->fetchColumn();
        
        if ($orderCount > 0) {
            $error = "Cannot delete customer with existing orders. Archive instead?";
        } else {
            $stmt = $pdo->prepare("DELETE FROM customer WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Customer deleted successfully!";
        }
    } catch (PDOException $e) {
        $error = "Error deleting customer: " . $e->getMessage();
    }
}

// Toggle Status (Activate/Deactivate)
if (isset($_GET['toggle_status'])) {
    $id = $_GET['toggle_status'];
    $stmt = $pdo->prepare("SELECT status FROM customer WHERE id = ?");
    $stmt->execute([$id]);
    $current = $stmt->fetch()['status'];
    $newStatus = ($current == 'active') ? 'inactive' : 'active';
    
    $stmt = $pdo->prepare("UPDATE customer SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);
    $message = "Customer status updated to " . ucfirst($newStatus);
}

// Get all customers with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM customer WHERE firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR phone LIKE ? ORDER BY id DESC LIMIT ?, ?");
    $searchTerm = "%$search%";
    $stmt->bindValue(1, $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(2, $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(3, $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(4, $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(5, $offset, PDO::PARAM_INT);
    $stmt->bindValue(6, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $customers = $stmt->fetchAll();
    
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM customer WHERE firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR phone LIKE ?");
    $countStmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $totalCustomers = $countStmt->fetchColumn();
} else {
    $stmt = $pdo->prepare("SELECT * FROM customer ORDER BY id DESC LIMIT ?, ?");
    $stmt->bindValue(1, $offset, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $customers = $stmt->fetchAll();
    $totalCustomers = $pdo->query("SELECT COUNT(*) FROM customer")->fetchColumn();
}

$totalPages = ceil($totalCustomers / $limit);

// Get customer for editing via AJAX
if (isset($_GET['ajax_get_customer'])) {
    $id = $_GET['ajax_get_customer'];
    $stmt = $pdo->prepare("SELECT * FROM customer WHERE id = ?");
    $stmt->execute([$id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    if ($customer) {
        echo json_encode(['success' => true, 'customer' => $customer]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM customer");
$totalAll = $stmt->fetch()['total'];
$stmt = $pdo->query("SELECT COUNT(*) as total FROM customer WHERE status = 'active'");
$totalActive = $stmt->fetch()['total'];
$stmt = $pdo->query("SELECT COUNT(*) as total FROM customer WHERE status = 'inactive'");
$totalInactive = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - EcoWaste Admin</title>
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
        .stats-mini {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .stat-mini-card {
            background: white;
            border-radius: 20px;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            flex: 1;
            min-width: 150px;
        }

        .stat-mini-icon {
            width: 48px;
            height: 48px;
            background: #eef2ff;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #1e3a8a;
        }

        .stat-mini-info h3 {
            font-size: 1.6rem;
            font-weight: 700;
        }

        .stat-mini-info p {
            font-size: 0.8rem;
            color: #64748b;
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

        .search-box {
            display: flex;
            gap: 0.5rem;
        }

        .search-box input {
            padding: 0.7rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            width: 260px;
            font-family: inherit;
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
            font-size: 0.85rem;
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

        .badge-active {
            background: #dcfce7;
            color: #15803d;
        }

        .badge-inactive {
            background: #fee2e2;
            color: #b91c1c;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-icon {
            padding: 6px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.75rem;
            transition: 0.2s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-edit {
            background: #eef2ff;
            color: #1e3a8a;
        }

        .btn-toggle {
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
            max-width: 600px;
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
            <li class="nav-item"><a href="customers.php" class="nav-link active"><i class="fas fa-users"></i> Customers</a></li>
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
                <h1>Customer Management</h1>
                <p>Manage all registered customers, view details, and control access</p>
            </div>
            <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
        </div>

        <!-- Stats -->
        <div class="stats-mini">
            <div class="stat-mini-card">
                <div class="stat-mini-icon"><i class="fas fa-users"></i></div>
                <div class="stat-mini-info">
                    <h3><?php echo $totalAll; ?></h3>
                    <p>Total Customers</p>
                </div>
            </div>
            <div class="stat-mini-card">
                <div class="stat-mini-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-mini-info">
                    <h3><?php echo $totalActive; ?></h3>
                    <p>Active Accounts</p>
                </div>
            </div>
            <div class="stat-mini-card">
                <div class="stat-mini-icon"><i class="fas fa-user-slash"></i></div>
                <div class="stat-mini-info">
                    <h3><?php echo $totalInactive; ?></h3>
                    <p>Inactive Accounts</p>
                </div>
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
            <button class="btn-primary" onclick="openAddModal()"><i class="fas fa-plus-circle"></i> Add New Customer</button>
            <form method="GET" class="search-box">
                <input type="text" name="search" placeholder="Search by name, email, phone..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-outline"><i class="fas fa-search"></i> Search</button>
                <?php if ($search): ?>
                    <a href="customers.php" class="btn-outline"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Customers Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                    
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($customers) > 0): ?>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                           
                                <td><strong><?php echo htmlspecialchars($customer['firstname'] . ' ' . $customer['lastname']); ?></strong></td>
                                <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                                <td><?php echo htmlspecialchars($customer['village'] . ', ' . $customer['cell']); ?></td>
                                <td>
                                    <span class="badge <?php echo $customer['status'] == 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo ucfirst($customer['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                <td class="action-buttons">
                                    <a href="javascript:void(0)" onclick="openEditModal(<?php echo $customer['id']; ?>)" class="btn-icon btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="?toggle_status=<?php echo $customer['id']; ?>" class="btn-icon btn-toggle" onclick="return confirm('Toggle account status?')"><i class="fas fa-exchange-alt"></i> Toggle</a>
                                    <a href="?delete=<?php echo $customer['id']; ?>" class="btn-icon btn-delete" onclick="return confirm('Delete this customer? This action cannot be undone if no orders exist.')"><i class="fas fa-trash"></i> Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center; padding: 3rem;">No customers found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Add Customer Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-plus"></i> Add New Customer</h2>
            <span class="close-modal" onclick="closeAddModal()">&times;</span>
        </div>
        <form method="POST">
            <div class="form-row">
                <div class="form-group"><label>First Name *</label><input type="text" name="firstname" required></div>
                <div class="form-group"><label>Last Name *</label><input type="text" name="lastname" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Email *</label><input type="email" name="email" required></div>
                <div class="form-group"><label>Phone *</label><input type="text" name="phone" required></div>
            </div>
            <div class="form-group"><label>Password *</label><input type="password" name="password" required></div>
            <div class="form-row">
                <div class="form-group"><label>Province</label><input type="text" name="province"></div>
                <div class="form-group"><label>District</label><input type="text" name="district"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Sector</label><input type="text" name="sector"></div>
                <div class="form-group"><label>Cell</label><input type="text" name="cell"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Village</label><input type="text" name="village"></div>
                <div class="form-group"><label>House Number</label><input type="text" name="housenumber"></div>
            </div>
            <div class="form-group"><label>Status</label>
                <select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select>
            </div>
            <button type="submit" name="add_customer" class="btn-primary" style="width:100%; margin-top:1rem;">Create Customer</button>
        </form>
    </div>
</div>

<!-- Edit Customer Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-edit"></i> Edit Customer</h2>
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="customer_id" id="edit_id">
            <div class="form-row">
                <div class="form-group"><label>First Name</label><input type="text" name="firstname" id="edit_firstname" required></div>
                <div class="form-group"><label>Last Name</label><input type="text" name="lastname" id="edit_lastname" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email" required></div>
                <div class="form-group"><label>Phone</label><input type="text" name="phone" id="edit_phone" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Province</label><input type="text" name="province" id="edit_province"></div>
                <div class="form-group"><label>District</label><input type="text" name="district" id="edit_district"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Sector</label><input type="text" name="sector" id="edit_sector"></div>
                <div class="form-group"><label>Cell</label><input type="text" name="cell" id="edit_cell"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Village</label><input type="text" name="village" id="edit_village"></div>
                <div class="form-group"><label>House Number</label><input type="text" name="housenumber" id="edit_housenumber"></div>
            </div>
            <div class="form-group"><label>Status</label>
                <select name="status" id="edit_status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <button type="submit" name="update_customer" class="btn-primary" style="width:100%; margin-top:1rem;">Update Customer</button>
        </form>
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
        // Fetch customer data via AJAX
        fetch(`customers.php?ajax_get_customer=${id}`)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    document.getElementById('edit_id').value = data.customer.id;
                    document.getElementById('edit_firstname').value = data.customer.firstname;
                    document.getElementById('edit_lastname').value = data.customer.lastname;
                    document.getElementById('edit_email').value = data.customer.email;
                    document.getElementById('edit_phone').value = data.customer.phone;
                    document.getElementById('edit_province').value = data.customer.province || '';
                    document.getElementById('edit_district').value = data.customer.district || '';
                    document.getElementById('edit_sector').value = data.customer.sector || '';
                    document.getElementById('edit_cell').value = data.customer.cell || '';
                    document.getElementById('edit_village').value = data.customer.village || '';
                    document.getElementById('edit_housenumber').value = data.customer.housenumber || '';
                    document.getElementById('edit_status').value = data.customer.status;
                    document.getElementById('editModal').classList.add('show');
                } else {
                    alert('Error loading customer data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading customer data');
            });
    }
    
    function closeEditModal() { 
        document.getElementById('editModal').classList.remove('show'); 
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