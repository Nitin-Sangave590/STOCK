<?php
// supplier_ledger.php - Supplier Ledger
include 'db.php';
include 'header.php';

$supplier_id = $_GET['supplier_id'] ?? '';

$suppliers = $conn->query("SELECT * FROM accounts WHERE type='supplier'");

?>
<h2>Supplier Ledger</h2>
<form method="GET">
    <div class="mb-3">
        <label>Select Supplier</label>
        <select name="supplier_id" class="form-select" required>
            <option value="">-- Select --</option>
            <?php while ($row = $suppliers->fetch_assoc()) { ?>
                <option value="<?php echo $row['id']; ?>" <?php if ($supplier_id == $row['id']) echo 'selected'; ?>><?php echo $row['name']; ?></option>
            <?php } ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">View Ledger</button>
</form>

<?php if ($supplier_id): 
    $supplier_name = $conn->query("SELECT name FROM accounts WHERE id = $supplier_id")->fetch_assoc()['name'];
?>
<h3>Ledger for <?php echo $supplier_name; ?></h3>

<h4>Purchase Invoices</h4>
<table class="table table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Invoice</th>
            <th>Date</th>
            <th>Amount</th>
            <th>Paid</th>
            <th>Outstanding</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $purchases = $conn->query("SELECT * FROM purchases WHERE supplier_id = $supplier_id");
        $total_purchases = 0;
        $total_paid = 0;
        while ($purchase = $purchases->fetch_assoc()) {
            $purchase_id = $purchase['id'];
            $paid = $conn->query("SELECT SUM(amount) as total FROM supplier_payments WHERE purchase_id = $purchase_id")->fetch_assoc()['total'] ?? 0;
            $outstanding = $purchase['total_amount'] - $paid;
            $total_purchases += $purchase['total_amount'];
            $total_paid += $paid;
            echo "<tr>
                <td>{$purchase['id']}</td>
                <td>{$purchase['invoice_number']}</td>
                <td>{$purchase['purchase_date']}</td>
                <td>{$purchase['total_amount']}</td>
                <td>$paid</td>
                <td>$outstanding</td>
            </tr>";
        }
        $total_outstanding = $total_purchases - $total_paid;
        ?>
    </tbody>
</table>

<h4>Payments</h4>
<table class="table table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Purchase ID</th>
            <th>Date</th>
            <th>Amount</th>
            <th>Description</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $payments = $conn->query("SELECT * FROM supplier_payments WHERE supplier_id = $supplier_id");
        while ($payment = $payments->fetch_assoc()) {
            echo "<tr>
                <td>{$payment['id']}</td>
                <td>{$payment['purchase_id']}</td>
                <td>{$payment['payment_date']}</td>
                <td>{$payment['amount']}</td>
                <td>{$payment['description']}</td>
            </tr>";
        }
        ?>
    </tbody>
</table>

<div class="alert alert-info">
    Total Purchases: <?php echo $total_purchases; ?><br>
    Total Paid: <?php echo $total_paid; ?><br>
    Outstanding Balance: <?php echo $total_outstanding; ?>
</div>
<?php endif; ?>
<?php include 'footer.php'; ?>