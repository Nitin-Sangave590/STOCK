<?php
// customer_ledger.php - Customer Ledger
include 'db.php';
include 'header.php';

$customer_id = $_GET['customer_id'] ?? '';

$customers = $conn->query("SELECT * FROM accounts WHERE type='customer'");

?>
<h2>Customer Ledger</h2>
<form method="GET">
    <div class="mb-3">
        <label>Select Customer</label>
        <select name="customer_id" class="form-select" required>
            <option value="">-- Select --</option>
            <?php while ($row = $customers->fetch_assoc()) { ?>
                <option value="<?php echo $row['id']; ?>" <?php if ($customer_id == $row['id']) echo 'selected'; ?>><?php echo $row['name']; ?></option>
            <?php } ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">View Ledger</button>
</form>

<?php if ($customer_id): 
    $customer_name = $conn->query("SELECT name FROM accounts WHERE id = $customer_id")->fetch_assoc()['name'];
?>
<h3>Ledger for <?php echo $customer_name; ?></h3>

<h4>Sales Invoices</h4>
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
        $sales = $conn->query("SELECT * FROM sales WHERE customer_id = $customer_id");
        $total_sales = 0;
        $total_paid = 0;
        while ($sale = $sales->fetch_assoc()) {
            $sale_id = $sale['id'];
            $paid = $conn->query("SELECT SUM(amount) as total FROM customer_receipts WHERE sale_id = $sale_id")->fetch_assoc()['total'] ?? 0;
            $outstanding = $sale['total_amount'] - $paid;
            $total_sales += $sale['total_amount'];
            $total_paid += $paid;
            echo "<tr>
                <td>{$sale['id']}</td>
                <td>{$sale['invoice_number']}</td>
                <td>{$sale['sale_date']}</td>
                <td>{$sale['total_amount']}</td>
                <td>$paid</td>
                <td>$outstanding</td>
            </tr>";
        }
        $total_outstanding = $total_sales - $total_paid;
        ?>
    </tbody>
</table>

<h4>Receipts</h4>
<table class="table table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Sale ID</th>
            <th>Date</th>
            <th>Amount</th>
            <th>Description</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $receipts = $conn->query("SELECT * FROM customer_receipts WHERE customer_id = $customer_id");
        while ($receipt = $receipts->fetch_assoc()) {
            echo "<tr>
                <td>{$receipt['id']}</td>
                <td>{$receipt['sale_id']}</td>
                <td>{$receipt['receipt_date']}</td>
                <td>{$receipt['amount']}</td>
                <td>{$receipt['description']}</td>
            </tr>";
        }
        ?>
    </tbody>
</table>

<div class="alert alert-info">
    Total Sales: <?php echo $total_sales; ?><br>
    Total Received: <?php echo $total_paid; ?><br>
    Outstanding Balance: <?php echo $total_outstanding; ?>
</div>
<?php endif; ?>
<?php include 'footer.php'; ?>