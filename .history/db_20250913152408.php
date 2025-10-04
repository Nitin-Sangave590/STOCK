<?php
// db.php - Database Connection
$servername = "localhost";
$username = "root"; 
$password = " ";
$dbname = "STOCK";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database and tables if not exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
$conn->query($sql);

$conn->select_db($dbname);

// Accounts Table
$sql = "CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('customer', 'supplier') NOT NULL,
    address TEXT,
    phone VARCHAR(50),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Items Table
$sql = "CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    unit VARCHAR(50),
    purchase_rate DECIMAL(10,2),
    sale_rate DECIMAL(10,2),
    expenses DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Purchases Table
$sql = "CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    invoice_number VARCHAR(100),
    purchase_date DATE,
    total_amount DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Purchase Details Table
$sql = "CREATE TABLE IF NOT EXISTS purchase_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT,
    weight DECIMAL(10,2),
    rate DECIMAL(10,2),
    total DECIMAL(10,2)
)";
$conn->query($sql);

// Sales Table
$sql = "CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    invoice_number VARCHAR(100),
    sale_date DATE,
    total_amount DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Sale Details Table
$sql = "CREATE TABLE IF NOT EXISTS sale_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT,
    weight DECIMAL(10,2),
    rate DECIMAL(10,2),
    total DECIMAL(10,2)
)";
$conn->query($sql);
?><?php
// db.php - Database Connection
$servername = "localhost";
$username = "root"; // Change to your DB username
$password = ""; // Change to your DB password
$dbname = "stock_management";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database and tables if not exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
$conn->query($sql);

$conn->select_db($dbname);

// Accounts Table
$sql = "CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('customer', 'supplier') NOT NULL,
    address TEXT,
    phone VARCHAR(50),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Items Table
$sql = "CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    unit VARCHAR(50),
    purchase_rate DECIMAL(10,2),
    sale_rate DECIMAL(10,2),
    expenses DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Purchases Table
$sql = "CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    invoice_number VARCHAR(100),
    purchase_date DATE,
    total_amount DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Purchase Details Table
$sql = "CREATE TABLE IF NOT EXISTS purchase_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT,
    weight DECIMAL(10,2),
    rate DECIMAL(10,2),
    total DECIMAL(10,2)
)";
$conn->query($sql);

// Sales Table
$sql = "CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    invoice_number VARCHAR(100),
    sale_date DATE,
    total_amount DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Sale Details Table
$sql = "CREATE TABLE IF NOT EXISTS sale_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT,
    weight DECIMAL(10,2),
    rate DECIMAL(10,2),
    total DECIMAL(10,2)
)";
$conn->query($sql);
?>