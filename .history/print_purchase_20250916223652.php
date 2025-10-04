<?php
// print_purchase.php - Print Purchase Bill
include 'db.php';
include 'header.php';

$purchase_id = intval($_GET['id'] ?? 0);

if (!$purchase_id) {
    die("Invalid Purchase ID.");
}

$stmt = $conn->prepare("SELECT p.*, a.name as supplier FROM purchases p JOIN accounts a ON p.supplier_id = a.id WHERE p.id = ?");
$stmt->bind_param("i", $purchase_id);
$stmt->execute();
$purchase = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$purchase) {
    die("Purchase not found.");
}

$stmt = $conn->prepare("SELECT pd.*, i.name FROM purchase_details pd JOIN items i ON pd.item_id = i.id WHERE pd.purchase_id = ?");
$stmt->bind_param("i", $purchase_id);
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
        <h5>!! श्री शिवाय नमस्तुभ्यम् !!</h5>
        <h2>वैभव ट्रेडिंग कंपनी</h2>
        <h4>Farmer Patti </h4>
    </div>

    <div class="invoice-details">
        <p><strong>Farmer:</strong> <?= htmlspecialchars($purchase['supplier']) ?></p>
        <p><strong>Patti  Number:</strong> <?= htmlspecialchars($purchase['invoice_number']) ?></p>
        <p><strong>Date:</strong> <?= date("d-m-Y", strtotime($purchase['purchase_date'])) ?></p>
        
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
        <p><strong>Hamali:</strong> <?= number_format($purchase['hamali'], 2) ?></p>
        <p><strong>Freight:</strong> <?= number_format($purchase['freight'], 2) ?></p>
        <p class="total-amount">Net Total: <?= number_format($purchase['total_amount'], 2) ?></p>
    </div>
    <div class="invoice-header">
        <h5>!!---Thank You Visit Again---!!</h5>
        
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
