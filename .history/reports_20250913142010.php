<?php
// reports.php - Reports
include 'db.php';
include 'header.php';

// Fetch all purchases and sales, calculate stock per item
$items = $conn->query("SELECT * FROM items");

echo "<h2>Stock Report</h2>";
echo "<table class='table table-striped'>
    <thead>
        <tr>
            <th>Item</th>
            <th>Total Purchased Qty</th>
            <th>Total Sold Qty</th>
            <th>Remaining Stock Qty</th>
        </tr>
    </thead>
    <tbody>";

while ($item = $items->fetch_assoc()) {
    $item_id = $item['id'];
    $purchased = $conn->query("SELECT SUM(quantity) as total FROM purchase_details WHERE item_id = $item_id")->fetch_assoc()['total'] ?? 0;
    $sold = $conn->query("SELECT SUM(quantity) as total FROM sale_details WHERE item_id = $item_id")->fetch_assoc()['total'] ?? 0;
    $remaining = $purchased - $sold;

    echo "<tr>
        <td>{$item['name']}</td>
        <td>$purchased</td>
        <td>$sold</td>
        <td>$remaining</td>
    </tr>";
}

echo "</tbody></table>";

echo "<h3>All Purchase Records</h3>";
$purchases = $conn->query("SELECT p.*, a.name as supplier FROM purchases p JOIN accounts a ON p.supplier_id = a.id");
echo "<table class='table table-striped'>
    <thead><tr><th>ID</th><th>Supplier</th><th>Invoice</th><th>Date</th><th>Total</th></tr></thead>
    <tbody>";
while ($row = $purchases->fetch_assoc()) {
    echo "<tr><td>{$row['id']}</td><td>{$row['supplier']}</td><td>{$row['invoice_number']}</td><td>{$row['purchase_date']}</td><td>{$row['total_amount']}</td></tr>";
}
echo "</tbody></table>";

echo "<h3>All Sale Records</h3>";
$sales = $conn->query("SELECT s.*, a.name as customer FROM sales s JOIN accounts a ON s.customer_id = a.id");
echo "<table class='table table-striped'>
    <thead><tr><th>ID</th><th>Customer</th><th>Invoice</th><th>Date</th><th>Total</th></tr></thead>
    <tbody>";
while ($row = $sales->fetch_assoc()) {
    echo "<tr><td>{$row['id']}</td><td>{$row['customer']}</td><td>{$row['invoice_number']}</td><td>{$row['sale_date']}</td><td>{$row['total_amount']}</td></tr>";
}
echo "</tbody></table>";
?>
<?php include 'footer.php'; ?>