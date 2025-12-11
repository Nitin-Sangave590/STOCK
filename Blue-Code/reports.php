<?php
// reports.php - Reports
include 'db.php';
include 'header.php';
?>
<h2>Invoices List (Purchases and Sales)</h2>
<table class="table table-striped">
    <thead>
        <tr>
            <th>Type</th>
            <th>ID</th>
            <th>Party</th>
            <th>Invoice Number</th>
            <th>Date</th>
            <th>Total Amount</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $sql = "(SELECT 'Purchase' as type, p.id, a.name as party, p.invoice_number, p.purchase_date as date, p.total_amount 
                 FROM purchases p JOIN accounts a ON p.supplier_id = a.id)
                UNION
                (SELECT 'Sale' as type, s.id, a.name as party, s.invoice_number, s.sale_date as date, s.total_amount 
                 FROM sales s JOIN accounts a ON s.customer_id = a.id)
                ORDER BY date DESC";
        $result = $conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                <td>{$row['type']}</td>
                <td>{$row['id']}</td>
                <td>{$row['party']}</td>
                <td>{$row['invoice_number']}</td>
                <td>{$row['date']}</td>
                <td>{$row['total_amount']}</td>
            </tr>";
        }
        ?>
    </tbody>
</table>

<h2>Stock Report</h2>
<table class="table table-striped">
    <thead>
        <tr>
            <th>Item</th>
            <th>Total Purchased Qty</th>
            <th>Total Sold Qty</th>
            <th>Remaining Stock Qty</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $items = $conn->query("SELECT * FROM items");
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
        ?>
    </tbody>
</table>
<?php include 'footer.php'; ?>