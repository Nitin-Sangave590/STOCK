<?php
// get_purchases.php - AJAX for purchases
include 'db.php';

$supplier_id = $_GET['supplier_id'] ?? 0;

$result = $conn->query("SELECT id, invoice_number FROM purchases WHERE supplier_id = $supplier_id");

$output = '<option value="">None</option>';
while ($row = $result->fetch_assoc()) {
    $output .= "<option value=\"{$row['id']}\">{$row['invoice_number']}</option>";
}

echo $output;
?>