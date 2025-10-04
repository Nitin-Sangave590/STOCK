<?php
// db.php - Database Connection & Schema Setup

$servername = "localhost";
$username   = "root";         // Change if needed
$password   = "";             // Keep empty if no password
$dbname     = "STOCK";
$port       = 3307;           // Change to 3306 if using default MySQL port

// Create connection
$conn = new mysqli($servername, $username, $password, "", $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS `$dbname`";
if (!$conn->query($sql)) {
    die("Error creating database: " . $conn->error);
}

// Select database
$conn->select_db($dbname);

// --- Create Tables ---

// Accounts Table
$conn->query("CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('customer', 'supplier') NOT NULL,
    address TEXT,
    phone VARCHAR(50),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Items Table
$conn->query("CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    unit VARCHAR(50),
    purchase_rate DECIMAL(10,2),
    sale_rate DECIMAL(10,2),
    expenses DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Purchases Table
$conn->query("CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    invoice_number VARCHAR(100),
    purchase_date DATE,
    total_amount DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Purchase Details Table
$conn->query("CREATE TABLE IF NOT EXISTS purchase_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT,
    weight DECIMAL(10,2),
    rate DECIMAL(10,2),
    total DECIMAL(10,2)
)");

// Sales Table
$conn->query("CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    invoice_number VARCHAR(100),
    sale_date DATE,
    total_amount DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Sale Details Table
$conn->query("CREATE TABLE IF NOT EXISTS sale_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT,
    weight DECIMAL(10,2),
    rate DECIMAL(10,2),
    total DECIMAL(10,2)
)");

echo "Database and tables are ready.";
?>
