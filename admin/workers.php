<?php
// admin/workers.php - Worker Management for EcoWaste Admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit;
}
require_once '../config/database.php';

// Handle Worker Actions
$message = '';
$error = '';

// Add Worker
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_worker'])) {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $gender = $_POST['gender'];
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $status = $_POST['status'];
    $role = $_POST['role'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO workers (firstname, lastname, gender, phone, email, status, role, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$firstname, $lastname, $gender, $phone, $email, $status, $role, $password]);
        $message = "Worker added successfully!";
    } catch (PDOException $e) {
        $error = "Error: " . ($e->errorInfo[1] == 1062 ? "Email already exists!" : "Database error: " . $e->getMessage());
    }
}

// Update Worker
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_worker'])) {
    $id = $_POST['worker_id'];
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $gender = $_POST['gender'];
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $status = $_POST['status'];
    $role = $_POST['role'];
    
    // Check if password is being updated
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("UPDATE workers SET firstname=?, lastname=?, gender=?, phone=?, email=?, status=?, role=?, password=? WHERE id=?");
            $stmt->execute([$firstname, $lastname, $gender, $phone, $email, $status, $role, $password, $id]);
            $message = "Worker updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating worker: " . $e->getMessage();
        }
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE workers SET firstname=?, lastname=?, gender=?, phone=?, email=?, status=?, role=? WHERE id=?");
            $stmt->execute([$firstname, $lastname, $gender, $phone, $email, $status, $role, $id]);
            $message = "Worker updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating worker: " . $e->getMessage();
        }
    }
}

// Delete Worker
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Prevent deleting the last admin
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM workers WHERE role = 'admin' AND id != ?");
    $stmt->execute([$id]);
    $adminCount = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT role FROM workers WHERE id = ?");
    $stmt->execute([$id]);
    $worker = $stmt->fetch();
    
    if ($worker && $worker['role'] == 'admin' && $adminCount == 0) {
        $error = "Cannot delete the last admin account!";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM workers WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Worker deleted successfully!";
        } catch (PDOException $e) {
            $error = "Error deleting worker: " . $e->getMessage();
        }
    }
}

// Toggle Status (Activate/Deactivate)
if (isset($_GET['toggle_status'])) {
    $id = $_GET['toggle_status'];
    
    // Prevent deactivating the last admin
    $stmt = $pdo->prepare("SELECT role FROM workers WHERE id = ?");
    $stmt->execute([$id]);
    $worker = $stmt->fetch();
    
    if ($worker && $worker['role'] == 'admin') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM workers WHERE role = 'admin' AND status = 'active' AND id != ?");
        $stmt->execute([$id]);
        $activeAdmins = $stmt->fetchColumn();
        
        if ($activeAdmins == 0) {
            $error = "Cannot deactivate the last active admin account!";
            header("Location: workers.php?error=" . urlencode($error));
            exit;
        }
    }
    
    $stmt = $pdo->prepare("SELECT status FROM workers WHERE id = ?");
    $stmt->execute([$id]);
    $current = $stmt->fetch();
    
    if ($current) {
        $newStatus = ($current['status'] == 'active') ? 'inactive' : 'active';
        $stmt = $pdo->prepare("UPDATE workers SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);
        $message = "Worker status updated to " . ucfirst($newStatus);
    }
}

// Reset Password
if (isset($_GET['reset_password'])) {
    $id = $_GET['reset_password'];
    $defaultPassword = password_hash('password123', PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("UPDATE workers SET password = ? WHERE id = ?");
        $stmt->execute([$defaultPassword, $id]);
        $message = "Password reset to 'password123'. Please remind the worker to change it.";
    } catch (PDOException $e) {
        $error = "Error resetting password: " . $e->getMessage();
    }
}

// Get all workers with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

// Build query with filters
$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($role_filter && $role_filter != 'all') {
    $whereConditions[] = "role = ?";
    $params[] = $role_filter;
}

$whereClause = count($whereConditions) > 0 ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get total count
$countSql = "SELECT COUNT(*) FROM workers $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalWorkers = $countStmt->fetchColumn();

// Get workers with pagination - FIXED: Use separate query building for LIMIT
$sql = "SELECT * FROM workers $whereClause ORDER BY 
        CASE role 
            WHEN 'admin' THEN 1 
            WHEN 'manager' THEN 2 
            WHEN 'driver' THEN 3 
            WHEN 'collector' THEN 4 
            ELSE 5 
        END, id DESC";
$sql .= " LIMIT " . (int)$offset . ", " . (int)$limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$workers = $stmt->fetchAll();

$totalPages = ceil($totalWorkers / $limit);

// Get worker for editing via AJAX
if (isset($_GET['ajax_get_worker'])) {
    $id = $_GET['ajax_get_worker'];
    $stmt = $pdo->prepare("SELECT * FROM workers WHERE id = ?");
    $stmt->execute([$id]);
    $worker = $stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    if ($worker) {
        // Remove password from response
        unset($worker['password']);
        echo json_encode(['success' => true, 'worker' => $worker]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM workers");
$totalAll = $stmt->fetch()['total'];
$stmt = $pdo->query("SELECT COUNT(*) as total FROM workers WHERE status = 'active'");
$totalActive = $stmt->fetch()['total'];
$stmt = $pdo->query("SELECT COUNT(*) as total FROM workers WHERE status = 'inactive'");
$totalInactive = $stmt->fetch()['total'];

// Get counts by role
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM workers GROUP BY role");
$roleCounts = [];
while ($row = $stmt->fetch()) {
    $roleCounts[$row['role']] = $row['count'];
}

$roleIcons = [
    'admin' => 'fa-crown',
    'manager' => 'fa-chart-line',
    'driver' => 'fa-truck',
    'collector' => 'fa-recycle'
];

$roleColors = [
    'admin' => '#8b5cf6',
    'manager' => '#3b82f6',
    'driver' => '#10b981',
    'collector' => '#f59e0b'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workers - EcoWaste Admin</title>
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

        /* Role Cards */
        .role-stats {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .role-card {
            background: white;
            border-radius: 16px;
            padding: 0.8rem 1.2rem;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid;
            flex: 1;
            min-width: 120px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .role-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .role-info h4 {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
        }

        .role-info .count {
            font-size: 1.5rem;
            font-weight: 700;
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

        .filter-group {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group select {
            padding: 0.6rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-family: inherit;
            background: white;
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

        .role-badge {
            padding: 0.2rem 0.7rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }

        .role-admin { background: #ede9fe; color: #7c3aed; }
        .role-manager { background: #dbeafe; color: #2563eb; }
        .role-driver { background: #d1fae5; color: #059669; }
        .role-collector { background: #fed7aa; color: #d97706; }

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

        .btn-edit {
            background: #eef2ff;
            color: #1e3a8a;
        }

        .btn-toggle {
            background: #fef9c3;
            color: #854d0e;
        }

        .btn-reset {
            background: #e0e7ff;
            color: #4338ca;
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
            margin-top: 0.25rem;
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
            .filter-group {
                width: 100%;
            }
            .search-box input {
                width: 100%;
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
            <li class="nav-item"><a href="workers.php" class="nav-link active"><i class="fas fa-hard-hat"></i> Workers</a></li>
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
                <h1>Worker Management</h1>
                <p>Manage staff, drivers, collectors, and administrators</p>
            </div>
            <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
        </div>

        <!-- Stats -->
        <div class="stats-mini">
            <div class="stat-mini-card">
                <div class="stat-mini-icon"><i class="fas fa-users"></i></div>
                <div class="stat-mini-info">
                    <h3><?php echo $totalAll; ?></h3>
                    <p>Total Workers</p>
                </div>
            </div>
            <div class="stat-mini-card">
                <div class="stat-mini-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-mini-info">
                    <h3><?php echo $totalActive; ?></h3>
                    <p>Active Staff</p>
                </div>
            </div>
            <div class="stat-mini-card">
                <div class="stat-mini-icon"><i class="fas fa-user-slash"></i></div>
                <div class="stat-mini-info">
                    <h3><?php echo $totalInactive; ?></h3>
                    <p>Inactive Staff</p>
                </div>
            </div>
        </div>

        <!-- Role Distribution -->
        <div class="role-stats">
            <?php foreach (['admin', 'manager', 'driver', 'collector'] as $role): ?>
            <div class="role-card" style="border-left-color: <?php echo $roleColors[$role]; ?>">
                <div class="role-icon" style="background: <?php echo $roleColors[$role]; ?>20; color: <?php echo $roleColors[$role]; ?>">
                    <i class="fas <?php echo $roleIcons[$role]; ?>"></i>
                </div>
                <div class="role-info">
                    <h4><?php echo ucfirst($role); ?>s</h4>
                    <div class="count"><?php echo $roleCounts[$role] ?? 0; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
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
            <button class="btn-primary" onclick="openAddModal()"><i class="fas fa-plus-circle"></i> Add New Worker</button>
            <div class="filter-group">
                <form method="GET" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <select name="role" onchange="this.form.submit()">
                        <option value="all">All Roles</option>
                        <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="manager" <?php echo $role_filter == 'manager' ? 'selected' : ''; ?>>Manager</option>
                        <option value="driver" <?php echo $role_filter == 'driver' ? 'selected' : ''; ?>>Driver</option>
                        <option value="collector" <?php echo $role_filter == 'collector' ? 'selected' : ''; ?>>Collector</option>
                    </select>
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Search by name, email..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-outline"><i class="fas fa-search"></i></button>
                        <?php if ($search || $role_filter != 'all'): ?>
                            <a href="workers.php" class="btn-outline"><i class="fas fa-times"></i> Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Workers Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Gender</th>
                        <th>Contact</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($workers) > 0): ?>
                        <?php foreach ($workers as $worker): ?>
                            <tr>
                                <td>#<?php echo $worker['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($worker['firstname'] . ' ' . $worker['lastname']); ?></strong>
                                </td>
                                <td>
                                    <i class="fas fa-<?php echo $worker['gender'] == 'Male' ? 'mars' : 'venus'; ?>"></i>
                                    <?php echo $worker['gender']; ?>
                                </td>
                                <td>
                                    <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($worker['email']); ?></div>
                                    <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($worker['phone']); ?></div>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo $worker['role']; ?>">
                                        <i class="fas <?php echo $roleIcons[$worker['role']]; ?>"></i>
                                        <?php echo ucfirst($worker['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $worker['status'] == 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo ucfirst($worker['status']); ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <a href="javascript:void(0)" onclick="openEditModal(<?php echo $worker['id']; ?>)" class="btn-icon btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="?reset_password=<?php echo $worker['id']; ?>" class="btn-icon btn-reset" onclick="return confirm('Reset password to default (password123)?')"><i class="fas fa-key"></i> Reset</a>
                                    <a href="?toggle_status=<?php echo $worker['id']; ?>" class="btn-icon btn-toggle" onclick="return confirm('Toggle worker status?')"><i class="fas fa-exchange-alt"></i> Toggle</a>
                                    <a href="?delete=<?php echo $worker['id']; ?>" class="btn-icon btn-delete" onclick="return confirm('Delete this worker? This action cannot be undone.')"><i class="fas fa-trash"></i> Del</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding: 3rem;">
                                <i class="fas fa-users-slash" style="font-size: 2rem; color: #cbd5e1;"></i>
                                <p style="margin-top: 0.5rem;">No workers found</p>
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
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Add Worker Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-plus"></i> Add New Worker</h2>
            <span class="close-modal" onclick="closeAddModal()">&times;</span>
        </div>
        <form method="POST">
            <div class="form-row">
                <div class="form-group"><label>First Name *</label><input type="text" name="firstname" required></div>
                <div class="form-group"><label>Last Name *</label><input type="text" name="lastname" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Gender *</label>
                    <select name="gender" required>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="form-group"><label>Phone *</label><input type="text" name="phone" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Email *</label><input type="email" name="email" required></div>
                <div class="form-group"><label>Role *</label>
                    <select name="role" required>
                        <option value="admin">Administrator</option>
                        <option value="manager">Manager</option>
                        <option value="driver">Driver</option>
                        <option value="collector">Collector</option>
                    </select>
                </div>
            </div>
            <div class="form-group"><label>Password *</label><input type="password" name="password" required></div>
            <div class="form-group"><label>Status</label>
                <select name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="info-note"><i class="fas fa-info-circle"></i> Default password is encrypted. Users can change after login.</div>
            <button type="submit" name="add_worker" class="btn-primary" style="width:100%; margin-top:1rem;">Create Worker</button>
        </form>
    </div>
</div>

<!-- Edit Worker Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-edit"></i> Edit Worker</h2>
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="worker_id" id="edit_id">
            <div class="form-row">
                <div class="form-group"><label>First Name</label><input type="text" name="firstname" id="edit_firstname" required></div>
                <div class="form-group"><label>Last Name</label><input type="text" name="lastname" id="edit_lastname" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Gender</label>
                    <select name="gender" id="edit_gender">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="form-group"><label>Phone</label><input type="text" name="phone" id="edit_phone" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email" required></div>
                <div class="form-group"><label>Role</label>
                    <select name="role" id="edit_role">
                        <option value="admin">Administrator</option>
                        <option value="manager">Manager</option>
                        <option value="driver">Driver</option>
                        <option value="collector">Collector</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>New Password (leave empty to keep current)</label>
                <input type="password" name="password" placeholder="Enter new password only if changing">
            </div>
            <div class="form-group"><label>Status</label>
                <select name="status" id="edit_status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <button type="submit" name="update_worker" class="btn-primary" style="width:100%; margin-top:1rem;">Update Worker</button>
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
        fetch(`workers.php?ajax_get_worker=${id}`)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    document.getElementById('edit_id').value = data.worker.id;
                    document.getElementById('edit_firstname').value = data.worker.firstname;
                    document.getElementById('edit_lastname').value = data.worker.lastname;
                    document.getElementById('edit_gender').value = data.worker.gender;
                    document.getElementById('edit_phone').value = data.worker.phone;
                    document.getElementById('edit_email').value = data.worker.email;
                    document.getElementById('edit_role').value = data.worker.role;
                    document.getElementById('edit_status').value = data.worker.status;
                    document.getElementById('editModal').classList.add('show');
                } else {
                    alert('Error loading worker data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading worker data');
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