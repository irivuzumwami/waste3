<?php
// config/database.php
$host = 'ep-fancy-haze-aqkr2h03-pooler.c-8.us-east-1.aws.neon.tech';
$dbname = 'neondb';
$username = 'neondb_owner';
$password = 'npg_t1oWv2cmpDFN';
$port = '5432';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>