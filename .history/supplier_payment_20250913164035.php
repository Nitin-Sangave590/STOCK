<?php
// supplier_payment.php - Supplier Payment Entry
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';

$suppliers = $conn->query("SELECT * FROM accounts WHERE type='supplier'");

if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $supplier_id = $_POST['supplier_id'];
    $purchase_id = $_POST['purchase_id'] ?? null;
    $payment_date = $_POST['payment_date'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];

    $purchase_id_sql = $purchase_id ? $purchase_id : 'NULL';
    $sql = "INSERT INTO supplier_payments (supplier_id, purchase_id, payment_date, amount, description) VALUES ($supplier_id, $purchase_id_sql, '$payment_date', $amount, '$description')";
    $conn->query($sql);

    header("Location: supplier_payment.php");
    exit;
}
?>
<h2>Supplier Payment Entry</h2>
<a href="supplier_payment.php?action=add" class="btn btn-primary mb-3">Add New Payment</a>

<?php if ($action == 'add'): ?>
<form method="POST">
    <div class="mb-3">
        <label>Supplier</label>
        <select name="supplier_id" id="supplier_id" class="form-select" required>
            <?php $suppliers->data_seek(0); while ($row = $suppliers->fetch_assoc()) { ?>
                <option value="<?php echo $row['id']; ?>"><?php echo $row['name']; ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="mb-3">
        <label>Linked Purchase (Optional)</label>
        <select name="purchase_id" id="purchase_id" class="form-select">
            <option value="">None</option>
            <!-- Populated by JS -->
        </select>
    </div>
    <div class="mb-3">
        <label>Payment Date</label>
        <input type="date" name="payment_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
    </div>
    <div class="mb-3">
        <label>Amount</label>
        <input type="number" step="0.01" name="amount" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Description</label>
        <textarea name="description" class="form-control"></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Save Payment</button>
    <a href="supplier_payment.php" class="btn btn-secondary">Cancel</a>
</form>

<script>
$(document).ready(function() {
    $('#supplier_id').change(function() {
        var supplier_id = $(this).val();
        $.ajax({
            url: 'get_purchases.php',
            method: 'GET',
            data: {supplier_id: supplier_id},
            success: function(data) {
                $('#purchase_id').html(data);
            }
        });
    });
});
</script>
<?php else: ?>
<h3>Existing Payments</h3>
<table class="table table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Supplier</th>
            <th>Purchase ID</th>
            <th>Date</th>
            <th>Amount</th>
            <th>Description</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $result = $conn->query("SELECT sp.*, a.name as supplier FROM supplier_payments sp JOIN accounts a ON sp.supplier_id = a.id");
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['supplier']}</td>
                <td>{$row['purchase_id']}</td>
                <td>{$row['payment_date']}</td>
                <td>{$row['amount']}</td>
                <td>{$row['description']}</td>
            </tr>";
        }
        ?>
    </tbody>
</table>
<?php endif; ?>
<?php include 'footer.php'; ?>