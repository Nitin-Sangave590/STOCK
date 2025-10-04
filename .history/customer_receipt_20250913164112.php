<?php
// customer_receipt.php - Customer Receipt Entry
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';

$customers = $conn->query("SELECT * FROM accounts WHERE type='customer'");

if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_id = $_POST['customer_id'];
    $sale_id = $_POST['sale_id'] ?? null;
    $receipt_date = $_POST['receipt_date'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];

    $sale_id_sql = $sale_id ? $sale_id : 'NULL';
    $sql = "INSERT INTO customer_receipts (customer_id, sale_id, receipt_date, amount, description) VALUES ($customer_id, $sale_id_sql, '$receipt_date', $amount, '$description')";
    $conn->query($sql);

    header("Location: customer_receipt.php");
    exit;
}
?>
<h2>Customer Receipt Entry</h2>
<a href="customer_receipt.php?action=add" class="btn btn-primary mb-3">Add New Receipt</a>

<?php if ($action == 'add'): ?>
<form method="POST">
    <div class="mb-3">
        <label>Customer</label>
        <select name="customer_id" id="customer_id" class="form-select" required>
            <?php $customers->data_seek(0); while ($row = $customers->fetch_assoc()) { ?>
                <option value="<?php echo $row['id']; ?>"><?php echo $row['name']; ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="mb-3">
        <label>Linked Sale (Optional)</label>
        <select name="sale_id" id="sale_id" class="form-select">
            <option value="">None</option>
            <!-- Populated by JS -->
        </select>
    </div>
    <div class="mb-3">
        <label>Receipt Date</label>
        <input type="date" name="receipt_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
    </div>
    <div class="mb-3">
        <label>Amount</label>
        <input type="number" step="0.01" name="amount" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Description</label>
        <textarea name="description" class="form-control"></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Save Receipt</button>
    <a href="customer_receipt.php" class="btn btn-secondary">Cancel</a>
</form>

<script>
$(document).ready(function() {
    $('#customer_id').change(function() {
        var customer_id = $(this).val();
        $.ajax({
            url: 'get_sales.php',
            method: 'GET',
            data: {customer_id: customer_id},
            success: function(data) {
                $('#sale_id').html(data);
            }
        });
    });
});
</script>
<?php else: ?>
<h3>Existing Receipts</h3>
<table class="table table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Customer</th>
            <th>Sale ID</th>
            <th>Date</th>
            <th>Amount</th>
            <th>Description</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $result = $conn->query("SELECT cr.*, a.name as customer FROM customer_receipts cr JOIN accounts a ON cr.customer_id = a.id");
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['customer']}</td>
                <td>{$row['sale_id']}</td>
                <td>{$row['receipt_date']}</td>
                <td>{$row['amount']}</td>
                <td>{$row['description']}</td>
            </tr>";
        }
        ?>
    </tbody>
</table>
<?php endif; ?>
<?php include 'footer.php'; ?>