<?php
// authenticate.php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        header("Location: index.php?error=empty_fields&show_login=1&email=" . urlencode($email));
        exit;
    }
    
    try {
        // Check in customer table
        $stmt = $pdo->prepare("SELECT *, 'customer' as user_role FROM customer WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $role = null;
        $redirect = null;
        $user_data = null;
        
        if ($user && $user['password'] == $password) {
            $role = 'customer';
            $user_data = $user;
        } else {
            // Check in workers table
            $stmt = $pdo->prepare("SELECT *, 'worker' as user_type FROM workers WHERE email = ?");
            $stmt->execute([$email]);
            $worker = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($worker && $worker['password'] == $password) {
                $role = $worker['role'];
                $user_data = $worker;
            }
        }
        
        if (!$user_data) {
            header("Location: index.php?error=invalid_credentials&show_login=1&email=" . urlencode($email));
            exit;
        }
        
        if (isset($user_data['status']) && $user_data['status'] != 'active') {
            header("Location: index.php?error=account_inactive&show_login=1&email=" . urlencode($email));
            exit;
        }
        
        $_SESSION['user_id'] = $user_data['id'];
        $_SESSION['email'] = $user_data['email'];
        $_SESSION['role'] = $role;
        $_SESSION['name'] = $user_data['firstname'] . ' ' . $user_data['lastname'];
        
        if ($role == 'customer') {
            $redirect = 'customer/dashboard.php';
        } else {
            switch($role) {
                case 'admin': $redirect = 'admin/dashboard.php'; break;
                case 'manager': $redirect = 'manager/dashboard.php'; break;
                case 'driver': $redirect = 'driver/dashboard.php'; break;
                case 'collector': $redirect = 'collector/dashboard.php'; break;
                default: $redirect = 'index.php?error=invalid_role';
            }
        }
        
        session_regenerate_id(true);
        header("Location: $redirect");
        exit;
        
    } catch(PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        header("Location: index.php?error=database_error&show_login=1");
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}
?>