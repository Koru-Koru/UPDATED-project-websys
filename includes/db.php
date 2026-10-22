<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = 'localhost';
$dbname = 'phbestcoffee_db';
$username = 'root';
$password = ''; // Default XAMPP MySQL password is usually blank.

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    exit('Database connection failed. Check XAMPP MySQL and the settings in includes/db.php.');
}
