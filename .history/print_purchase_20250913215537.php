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

<div class="container mt-4 print-area" style="display: block;">
    <h2>Purchase Bill</h2>
    <p><strong>Supplier:</strong> <?= htmlspecialchars($purchase['supplier']) ?></p>
    <p><strong>Invoice Number:</strong> <?= htmlspecialchars($purchase['invoice_number']) ?></p>
    <p><strong>Date:</strong> <?= $purchase['purchase_date'] ?></p>
    <p><strong>Hamali:</strong> <?= $purchase['hamali'] ?></p>
    <p><strong>Freight:</strong> <?= $purchase['freight'] ?></p>
    <table border="1" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th>Product</th>
                <th>Quantity</th>
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
                    <td><?= $row['rate'] ?></td>
                    <td><?= $row['total'] ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    <p><strong>Net Total:</strong> <?= $purchase['total_amount'] ?></p>
</div>

<script>
$(document).ready(function() {
    window.print();
    window.onafterprint = function() {
        window.close();
    };
});
</script>

<?php include 'footer.php'; ?>