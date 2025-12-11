<?php
// get_sales.php - AJAX for sales
include 'db.php';

$customer_id = $_GET['customer_id'] ?? 0;

$result = $conn->query("SELECT id, invoice_number FROM sales WHERE customer_id = $customer_id");

$output = '<option value="">None</option>';
while ($row = $result->fetch_assoc()) {
    $output .= "<option value=\"{$row['id']}\">{$row['invoice_number']}</option>";
}

echo $output;
?>