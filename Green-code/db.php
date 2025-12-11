<?php
// db.php - Database Connection (Corrected)

// Database Configuration
$servername = "localhost";
$username   = "root";       // Change if needed
$password   = "";           // Change if needed
$dbname     = "stock";
$port       = 3307;         // ✅ Your MySQL running port (important)

// Create Connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check Connection
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}
// echo "✅ Connected successfully!<br>";

// Create Database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS `$dbname`";
if ($conn->query($sql) === TRUE) {
    // echo "✅ Database check OK.<br>";
} else {
    echo "❌ Error creating database: " . $conn->error . "<br>";
}

// Select Database
$conn->select_db($dbname);

// Function to run table creation queries with error display
function createTable($conn, $sql, $tableName) {
    if ($conn->query($sql) === TRUE) {
        // echo "✅ Table '$tableName' ready.<br>";
    } else {
        echo "❌ Error creating '$tableName': " . $conn->error . "<br>";
    }
}

// Accounts Table
createTable($conn, "
CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('customer','supplier') NOT NULL,
    address TEXT,
    phone VARCHAR(50),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "accounts");

// Items Table
createTable($conn, "
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    unit VARCHAR(50),
    purchase_rate DECIMAL(10,2),
    sale_rate DECIMAL(10,2),
    expenses DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "items");

// Purchases Table
createTable($conn, "
CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    invoice_number VARCHAR(100),
    purchase_date DATE,
    total_amount DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "purchases");

// Purchase Details Table
createTable($conn, "
CREATE TABLE IF NOT EXISTS purchase_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT,
    weight DECIMAL(10,2),
    rate DECIMAL(10,2),
    total DECIMAL(10,2)
)", "purchase_details");

// Sales Table
createTable($conn, "
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    invoice_number VARCHAR(100),
    sale_date DATE,
    total_amount DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "sales");

// Sale Details Table
createTable($conn, "
CREATE TABLE IF NOT EXISTS sale_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT,
    weight DECIMAL(10,2),
    rate DECIMAL(10,2),
    total DECIMAL(10,2)
)", "sale_details");

// Customer Receipts Table
createTable($conn, "
CREATE TABLE IF NOT EXISTS customer_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    sale_id INT,
    receipt_date DATE,
    amount DECIMAL(10,2),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "customer_receipts");

// Supplier Payments Table
createTable($conn, "
CREATE TABLE IF NOT EXISTS supplier_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    purchase_id INT,
    payment_date DATE,
    amount DECIMAL(10,2),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "supplier_payments");

// echo "<br>✅ All tables checked/created successfully.";
?>
