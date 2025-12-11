<?php
// print_sale.php - Print Sale Bill
include 'db.php';
include 'header.php';

$sale_id = intval($_GET['id'] ?? 0);

if (!$sale_id) {
    die("Invalid Sale ID.");
}

$stmt = $conn->prepare("SELECT s.*, c.name as customer FROM sales s JOIN accounts c ON s.customer_id = c.id WHERE s.id = ?");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sale) {
    die("Sale not found.");
}

$stmt = $conn->prepare("SELECT sd.*, i.name FROM sale_details sd JOIN items i ON sd.item_id = i.id WHERE sd.sale_id = ?");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$details = $stmt->get_result();
$stmt->close();
?>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f9f9f9;
    margin: 0;
    padding: 20px;
}
.print-container {
    max-width: 800px;
    margin: auto;
    background: #fff;
    padding: 25px 40px;
    border-radius: 12px;
    box-shadow: 0px 4px 12px rgba(0,0,0,0.1);
}
.invoice-header {
    text-align: center;
    border-bottom: 2px solid #ddd;
    margin-bottom: 20px;
    padding-bottom: 10px;
}
.invoice-header h2 {
    margin: 0;
    font-size: 28px;
    letter-spacing: 1px;
}
.invoice-details p {
    margin: 4px 0;
    font-size: 16px;
}
.table-container {
    margin-top: 20px;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}
table th, table td {
    border: 1px solid #ddd;
    padding: 10px;
    text-align: center;
}
table th {
    background-color: #f1f1f1;
    font-weight: bold;
}
.total-section {
    margin-top: 20px;
    text-align: right;
}
.total-section p {
    font-size: 18px;
    margin: 4px 0;
}
.total-amount {
    font-weight: bold;
    font-size: 22px;
    color: #2c3e50;
}
@media print {
    body {
        background: none;
    }
    .print-container {
        box-shadow: none;
        border: none;
    }
}
</style>

<div class="print-container">
    <div class="invoice-header">
        <h2>Sale Invoice</h2>
    </div>

    <div class="invoice-details">
        <p><strong>Customer:</strong> <?= htmlspecialchars($sale['customer']) ?></p>
        <p><strong>Invoice Number:</strong> <?= htmlspecialchars($sale['invoice_number']) ?></p>
        <p><strong>Date:</strong> <?= date("d-m-Y", strtotime($sale['date'])) ?></p>
         </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Weight</th>
                    <th>Rate</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $details->fetch_assoc()) { ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= $row['quantity'] ?></td>
                        <td><?= $row['weight'] ?></td>
                        <td><?= number_format($row['rate'], 2) ?></td>
                        <td><?= number_format($row['total'], 2) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <div class="total-section">
        <p><strong>Hamali:</strong> <?= number_format($sale['hamali'], 2) ?></p>
        <p><strong>Freight:</strong> <?= number_format($sale['freight'], 2) ?></p>
        <p class="total-amount">Net Total: <?= number_format($sale['total_amount'], 2) ?></p>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    window.print();
    window.onafterprint = function() {
        window.close();
    };
});
</script>

<?php include 'footer.php'; ?>
