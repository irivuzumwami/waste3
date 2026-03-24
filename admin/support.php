<?php
// admin/support.php - Support Ticket Management for EcoWaste Admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit;
}
require_once '../config/database.php';

// First, let's check if the support table needs additional columns
// You may need to run these ALTER statements once:
/*
ALTER TABLE `support` ADD COLUMN IF NOT EXISTS `status` VARCHAR(30) DEFAULT 'open' AFTER `message`;
ALTER TABLE `support` ADD COLUMN IF NOT EXISTS `admin_notes` TEXT NULL AFTER `message`;
ALTER TABLE `support` ADD COLUMN IF NOT EXISTS `resolved_at` DATETIME NULL;
ALTER TABLE `support` ADD COLUMN IF NOT EXISTS `assigned_at` DATETIME NULL;
ALTER TABLE `support` ADD COLUMN IF NOT EXISTS `last_reply_at` DATETIME NULL;
*/

// Handle Support Actions
$message = '';
$error = '';

// Mark ticket as resolved
if (isset($_GET['resolve'])) {
    $ticket_id = $_GET['resolve'];
    try {
        // Check if status column exists, if not, we'll just delete or handle differently
        $stmt = $pdo->prepare("UPDATE support SET status = 'resolved', resolved_at = NOW() WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $message = "Ticket marked as resolved!";
    } catch (PDOException $e) {
        // If status column doesn't exist, just show a message
        $error = "Note: Status column may not exist. Please run the ALTER statements to add required columns.";
    }
}

// Mark ticket as in progress
if (isset($_GET['in_progress'])) {
    $ticket_id = $_GET['in_progress'];
    try {
        $stmt = $pdo->prepare("UPDATE support SET status = 'in_progress', assigned_at = NOW() WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $message = "Ticket marked as in progress!";
    } catch (PDOException $e) {
        $error = "Note: Status column may not exist. Please run the ALTER statements to add required columns.";
    }
}

// Reopen ticket
if (isset($_GET['reopen'])) {
    $ticket_id = $_GET['reopen'];
    try {
        $stmt = $pdo->prepare("UPDATE support SET status = 'open', resolved_at = NULL, assigned_at = NULL WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $message = "Ticket reopened!";
    } catch (PDOException $e) {
        $error = "Note: Status column may not exist. Please run the ALTER statements to add required columns.";
    }
}

// Delete ticket
if (isset($_GET['delete'])) {
    $ticket_id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM support WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $message = "Ticket deleted successfully!";
    } catch (PDOException $e) {
        $error = "Error deleting ticket: " . $e->getMessage();
    }
}

// Reply to ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_ticket'])) {
    $ticket_id = $_POST['ticket_id'];
    $reply_message = trim($_POST['reply_message']);
    $admin_name = $_SESSION['name'] ?? 'Admin';
    
    if ($reply_message) {
        try {
            // Check if admin_notes column exists
            $stmt = $pdo->prepare("SELECT admin_notes FROM support WHERE id = ?");
            $stmt->execute([$ticket_id]);
            $current = $stmt->fetch();
            
            if ($current) {
                $timestamp = date('Y-m-d H:i:s');
                $new_note = "[$timestamp] Admin ($admin_name): " . $reply_message . "\n";
                $updated_notes = ($current['admin_notes'] ? $current['admin_notes'] . $new_note : $new_note);
                
                $stmt = $pdo->prepare("UPDATE support SET admin_notes = ?, last_reply_at = NOW() WHERE id = ?");
                $stmt->execute([$updated_notes, $ticket_id]);
                $message = "Reply sent successfully!";
            } else {
                $error = "Ticket not found.";
            }
        } catch (PDOException $e) {
            // If column doesn't exist, just show a note
            $error = "Note: admin_notes column may not exist. Reply not saved. Please run the ALTER statements to add required columns.";
        }
    } else {
        $error = "Please enter a reply message.";
    }
}

// Get all support tickets with filters and pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query with filters - using id as fallback for date sorting since created_at doesn't exist
$whereConditions = [];
$params = [];

// Search by name, email, or message
if ($search) {
    $whereConditions[] = "(fullname LIKE ? OR email LIKE ? OR message LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Status filter - only if status column exists (we'll try to use it, if not, ignore)
if ($status_filter != 'all') {
    // We'll add this but it may fail if column doesn't exist
    $whereConditions[] = "status = ?";
    $params[] = $status_filter;
}

// Inquiry type filter
if ($type_filter != 'all') {
    $whereConditions[] = "inqirytype = ?";
    $params[] = $type_filter;
}

// Date range filter - using id as fallback since no date column
// Since there's no created_at, we'll skip date filtering or use id as rough proxy
// But we'll keep it for when the column is added
if ($date_from || $date_to) {
    // Skip date filtering if column doesn't exist - just show a note
    // For now, we'll comment this out to avoid errors
    // $whereConditions[] = "DATE(created_at) >= ?";
    // $params[] = $date_from;
}

$whereClause = count($whereConditions) > 0 ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get total count
$countSql = "SELECT COUNT(*) FROM support $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalTickets = $countStmt->fetchColumn();

// Get tickets with pagination - using id for sorting since no created_at
$sql = "SELECT * FROM support $whereClause ORDER BY id DESC LIMIT " . (int)$offset . ", " . (int)$limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$totalPages = ceil($totalTickets / $limit);

// Get ticket for viewing details via AJAX
if (isset($_GET['ajax_get_ticket'])) {
    $ticket_id = $_GET['ajax_get_ticket'];
    $stmt = $pdo->prepare("SELECT * FROM support WHERE id = ?");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    if ($ticket) {
        echo json_encode(['success' => true, 'ticket' => $ticket]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM support");
$totalAll = $stmt->fetch()['total'];

// Try to get status counts if status column exists
$totalOpen = 0;
$totalInProgress = 0;
$totalResolved = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM support WHERE status = 'open'");
    $totalOpen = $stmt->fetch()['total'];
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM support WHERE status = 'in_progress'");
    $totalInProgress = $stmt->fetch()['total'];
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM support WHERE status = 'resolved'");
    $totalResolved = $stmt->fetch()['total'];
} catch (PDOException $e) {
    // Status column doesn't exist, use defaults
    $totalOpen = $totalAll;
    $totalInProgress = 0;
    $totalResolved = 0;
}

// Get statistics by inquiry type
$stmt = $pdo->query("SELECT inqirytype, COUNT(*) as count FROM support GROUP BY inqirytype");
$typeCounts = [];
while ($row = $stmt->fetch()) {
    $typeCounts[$row['inqirytype']] = $row['count'];
}

// Get today's tickets (using id as proxy since no date column)
$todayTickets = 0;
// We can't reliably get today's tickets without a date column

// Get this week's tickets
$weekTickets = 0;

$inquiryTypes = [
    'General Inquiry' => 'fa-question-circle',
    'Billing Issue' => 'fa-dollar-sign',
    'Technical Support' => 'fa-laptop-code',
    'Waste Collection' => 'fa-truck',
    'Account Issue' => 'fa-user-cog',
    'Feedback' => 'fa-comment',
    'Other' => 'fa-ellipsis-h'
];

$statusColors = [
    'open' => '#f59e0b',
    'in_progress' => '#3b82f6',
    'resolved' => '#10b981'
];

$statusIcons = [
    'open' => 'fa-clock',
    'in_progress' => 'fa-spinner',
    'resolved' => 'fa-check-circle'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Tickets - EcoWaste Admin</title>
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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

        /* Inquiry Type Cards */
        .type-stats {
            display: flex;
            gap: 0.8rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .type-card {
            background: white;
            border-radius: 12px;
            padding: 0.6rem 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #e2e8f0;
        }

        .type-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            background: #eef2ff;
            color: #1e3a8a;
        }

        .type-info h4 {
            font-size: 0.7rem;
            color: #64748b;
        }

        .type-info .count {
            font-size: 1.1rem;
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

        .badge {
            padding: 0.2rem 0.7rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-open {
            background: #fef3c7;
            color: #d97706;
        }

        .badge-in_progress {
            background: #dbeafe;
            color: #2563eb;
        }

        .badge-resolved {
            background: #dcfce7;
            color: #15803d;
        }

        .message-preview {
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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

        .btn-reply {
            background: #dbeafe;
            color: #2563eb;
        }

        .btn-resolve {
            background: #dcfce7;
            color: #15803d;
        }

        .btn-progress {
            background: #fef3c7;
            color: #d97706;
        }

        .btn-reopen {
            background: #fee2e2;
            color: #b91c1c;
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

        .form-group textarea, .form-group input {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-family: inherit;
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

        .ticket-details {
            background: #f8fafc;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .ticket-message {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin: 1rem 0;
            border-left: 4px solid #1e3a8a;
        }

        .reply-history {
            margin-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
            padding-top: 1rem;
        }

        .reply-item {
            background: #f1f5f9;
            border-radius: 12px;
            padding: 0.8rem;
            margin-bottom: 0.8rem;
        }

        .reply-meta {
            font-size: 0.7rem;
            color: #64748b;
            margin-bottom: 0.3rem;
        }

        .info-note {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.5rem;
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
            <li class="nav-item"><a href="payments.php" class="nav-link"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li class="nav-item"><a href="support.php" class="nav-link active"><i class="fas fa-headset"></i> Support</a></li>
            <li class="nav-item logout-link"><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Support Ticket Management</h1>
                <p>Manage customer inquiries and track resolution status</p>
            </div>
            <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
        </div>

        <!-- Main Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span>Total Tickets</span>
                    <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
                </div>
                <div class="stat-number"><?php echo $totalAll; ?></div>
                <div class="stat-sub">All time inquiries</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span>Open</span>
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                </div>
                <div class="stat-number"><?php echo $totalOpen; ?></div>
                <div class="stat-sub">Need attention</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span>In Progress</span>
                    <div class="stat-icon"><i class="fas fa-spinner"></i></div>
                </div>
                <div class="stat-number"><?php echo $totalInProgress; ?></div>
                <div class="stat-sub">Being handled</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span>Resolved</span>
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="stat-number"><?php echo $totalResolved; ?></div>
                <div class="stat-sub">Completed</div>
            </div>
        </div>

        <!-- Inquiry Type Stats -->
        <div class="type-stats">
            <?php foreach ($inquiryTypes as $type => $icon): ?>
                <?php if (isset($typeCounts[$type]) && $typeCounts[$type] > 0): ?>
                    <div class="type-card">
                        <div class="type-icon"><i class="fas <?php echo $icon; ?>"></i></div>
                        <div class="type-info">
                            <h4><?php echo $type; ?></h4>
                            <div class="count"><?php echo $typeCounts[$type]; ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Name, Email, Message..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status" onchange="this.form.submit()">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="open" <?php echo $status_filter == 'open' ? 'selected' : ''; ?>>Open</option>
                            <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Inquiry Type</label>
                        <select name="type" onchange="this.form.submit()">
                            <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>All Types</option>
                            <?php foreach ($inquiryTypes as $type => $icon): ?>
                                <option value="<?php echo $type; ?>" <?php echo $type_filter == $type ? 'selected' : ''; ?>><?php echo $type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <?php if ($search || $status_filter != 'all' || $type_filter != 'all'): ?>
                            <a href="support.php" class="btn-outline"><i class="fas fa-times"></i> Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tickets Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Inquiry Type</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($tickets) > 0): ?>
                        <?php foreach ($tickets as $ticket): ?>
                            <?php 
                            // Determine status - if no status column, default to 'open'
                            $status = isset($ticket['status']) ? $ticket['status'] : 'open';
                            ?>
                            <tr>
                                <td><strong>#<?php echo $ticket['id']; ?></strong></td>
                                <td>
                                    <div><strong><?php echo htmlspecialchars($ticket['fullname']); ?></strong></div>
                                    <div style="font-size: 0.75rem; color: #64748b;"><?php echo htmlspecialchars($ticket['email']); ?></div>
                                </td>
                                <td>
                                    <span style="display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fas <?php echo $inquiryTypes[$ticket['inqirytype']] ?? 'fa-question-circle'; ?>"></i>
                                        <?php echo htmlspecialchars($ticket['inqirytype']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="message-preview" title="<?php echo htmlspecialchars($ticket['message']); ?>">
                                        <?php echo htmlspecialchars(substr($ticket['message'], 0, 60)) . (strlen($ticket['message']) > 60 ? '...' : ''); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $status; ?>">
                                        <i class="fas <?php echo $statusIcons[$status] ?? 'fa-clock'; ?>"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                    </span>
                                 </td>
                                <td class="action-buttons">
                                    <a href="javascript:void(0)" onclick="viewTicket(<?php echo $ticket['id']; ?>)" class="btn-icon btn-view"><i class="fas fa-eye"></i> View</a>
                                    <a href="javascript:void(0)" onclick="replyTicket(<?php echo $ticket['id']; ?>, '<?php echo addslashes($ticket['fullname']); ?>')" class="btn-icon btn-reply"><i class="fas fa-reply"></i> Reply</a>
                                    <?php if ($status == 'open'): ?>
                                        <a href="?in_progress=<?php echo $ticket['id']; ?>" class="btn-icon btn-progress" onclick="return confirm('Mark this ticket as in progress?')"><i class="fas fa-spinner"></i> Start</a>
                                    <?php endif; ?>
                                    <?php if ($status != 'resolved'): ?>
                                        <a href="?resolve=<?php echo $ticket['id']; ?>" class="btn-icon btn-resolve" onclick="return confirm('Mark this ticket as resolved?')"><i class="fas fa-check"></i> Resolve</a>
                                    <?php else: ?>
                                        <a href="?reopen=<?php echo $ticket['id']; ?>" class="btn-icon btn-reopen" onclick="return confirm('Reopen this ticket?')"><i class="fas fa-undo"></i> Reopen</a>
                                    <?php endif; ?>
                                    <a href="?delete=<?php echo $ticket['id']; ?>" class="btn-icon btn-delete" onclick="return confirm('Delete this ticket? This action cannot be undone.')"><i class="fas fa-trash"></i> Del</a>
                                 </td>
                             </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding: 3rem;">
                                <i class="fas fa-ticket-alt" style="font-size: 2rem; color: #cbd5e1;"></i>
                                <p style="margin-top: 0.5rem;">No support tickets found</p>
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
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- View Ticket Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2><i class="fas fa-ticket-alt"></i> Ticket Details</h2>
            <span class="close-modal" onclick="closeViewModal()">&times;</span>
        </div>
        <div id="ticketDetails"></div>
    </div>
</div>

<!-- Reply Ticket Modal -->
<div id="replyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-reply"></i> Reply to Ticket</h2>
            <span class="close-modal" onclick="closeReplyModal()">&times;</span>
        </div>
        <form method="POST" id="replyForm">
            <input type="hidden" name="ticket_id" id="reply_ticket_id">
            <div class="form-group">
                <label>Reply Message *</label>
                <textarea name="reply_message" rows="6" required placeholder="Type your response here..."></textarea>
            </div>
            <div class="info-note">
                <i class="fas fa-info-circle"></i> Your reply will be logged in the ticket history.
            </div>
            <button type="submit" name="reply_ticket" class="btn-primary" style="width:100%; margin-top:1rem;">Send Reply</button>
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

    function viewTicket(id) {
        fetch(`support.php?ajax_get_ticket=${id}`)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    const ticket = data.ticket;
                    const status = ticket.status || 'open';
                    const statusIcon = {
                        'open': 'fa-clock',
                        'in_progress': 'fa-spinner',
                        'resolved': 'fa-check-circle'
                    }[status] || 'fa-clock';
                    
                    let repliesHtml = '';
                    if (ticket.admin_notes) {
                        const replies = ticket.admin_notes.split('\n').filter(r => r.trim());
                        repliesHtml = '<div class="reply-history"><h4>Reply History</h4>';
                        replies.forEach(reply => {
                            if (reply.trim()) {
                                repliesHtml += `<div class="reply-item"><div class="reply-meta">${escapeHtml(reply.substring(0, 100))}</div></div>`;
                            }
                        });
                        repliesHtml += '</div>';
                    }
                    
                    document.getElementById('ticketDetails').innerHTML = `
                        <div class="ticket-details">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                                <div>
                                    <strong>Ticket #${ticket.id}</strong>
                                    <span class="badge badge-${status}" style="margin-left: 0.5rem;">
                                        <i class="fas ${statusIcon}"></i> ${status.replace('_', ' ').toUpperCase()}
                                    </span>
                                </div>
                            </div>
                            <div><strong>From:</strong> ${escapeHtml(ticket.fullname)} (${escapeHtml(ticket.email)})</div>
                            <div><strong>Inquiry Type:</strong> ${escapeHtml(ticket.inqirytype)}</div>
                            <div class="ticket-message">
                                <strong>Message:</strong><br>
                                ${escapeHtml(ticket.message).replace(/\n/g, '<br>')}
                            </div>
                            ${repliesHtml}
                        </div>
                    `;
                    document.getElementById('viewModal').classList.add('show');
                } else {
                    alert('Error loading ticket details');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading ticket details');
            });
    }
    
    function closeViewModal() { 
        document.getElementById('viewModal').classList.remove('show'); 
    }

    function replyTicket(id, customerName) {
        document.getElementById('reply_ticket_id').value = id;
        document.getElementById('replyModal').classList.add('show');
    }
    
    function closeReplyModal() { 
        document.getElementById('replyModal').classList.remove('show'); 
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
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