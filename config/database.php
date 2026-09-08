<?php
// config/database.php - PostgreSQL Configuration
$host = 'localhost';
$port = '5432';
$dbname = 'waste';
$user = 'postgres';
$password = 'Barcelona';
$port='5432';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES 'UTF8'");
} catch(PDOException $e) {
    die("PostgreSQL Connection failed: " . $e->getMessage());
}
?>