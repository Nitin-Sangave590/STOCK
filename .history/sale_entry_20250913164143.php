<?php
// sale_entry.php - Sale Bill Entry
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';

$customers = $conn->query("SELECT * FROM accounts WHERE type='customer'");
$items = $conn->query("SELECT * FROM items");

if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_id = $_POST['customer_id'];
    $invoice_number = $_POST['invoice_number'];
    $sale_date = $_POST['sale_date'];
    $total_amount = 0;

    $sql = "INSERT INTO sales (customer_id, invoice_number, sale_date, total_amount) VALUES ($customer_id, '$invoice_number', '$sale_date', 0)";
    $conn->query($sql);
    $sale_id = $conn->insert_id;

    for ($i = 0; $i < count($_POST['item_id']); $i++) {
        $item_id = $_POST['item_id'][$i];
        $quantity = $_POST['quantity'][$i];
        $weight = $_POST['weight'][$i];
        $rate = $_POST['rate'][$i];
        $total = $quantity * $rate;

        $total_amount += $total;

        $sql = "INSERT INTO sale_details (sale_id, item_id, quantity, weight, rate, total) VALUES ($sale_id, $item_id, $quantity, $weight, $rate, $total)";
        $conn->query($sql);
    }

    $sql = "UPDATE sales SET total_amount = $total_amount WHERE id = $sale_id";
    $conn->query($sql);

    header("Location: sale_entry.php");
    exit;
}
?>
<h2>Sale Bill Entry</h2>
<a href="sale_entry.php?action=add" class="btn btn-primary mb-3">New Sale Entry</a>

<?php if ($action == 'add'): ?>
<form method="POST" id="saleForm">
    <div class="mb-3">
        <label>Customer</label>
        <select name="customer_id" class="form-select" required>
            <?php while ($row = $customers->fetch_assoc()) { ?>
                <option value="<?php echo $row['id']; ?>"><?php echo $row['name']; ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="mb-3">
        <label>Invoice Number</label>
        <input type="text" name="invoice_number" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Sale Date</label>
        <input type="date" name="sale_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
    </div>
    <h4>Products</h4>
    <table class="table" id="productTable">
        <thead>
            <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Weight</th>
                <th>Rate</th>
                <th>Total</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <select name="item_id[]" class="form-select item-select" required>
                        <?php $items->data_seek(0); while ($row = $items->fetch_assoc()) { ?>
                            <option value="<?php echo $row['id']; ?>" data-rate="<?php echo $row['sale_rate']; ?>"><?php echo $row['name']; ?></option>
                        <?php } ?>
                    </select>
                </td>
                <td><input type="number" name="quantity[]" class="form-control qty" required></td>
                <td><input type="number" step="0.01" name="weight[]" class="form-control"></td>
                <td><input type="number" step="0.01" name="rate[]" class="form-control rate" required></td>
                <td><input type="number" step="0.01" class="form-control total" readonly></td>
                <td><button type="button" class="btn btn-danger remove-row">Remove</button></td>
            </tr>
        </tbody>
    </table>
    <button type="button" id="addRow" class="btn btn-secondary">Add Product</button>
    <div class="mb-3 mt-3">
        <label>Grand Total</label>
        <input type="number" step="0.01" id="grandTotal" class="form-control" readonly>
    </div>
    <button type="submit" class="btn btn-primary">Save Sale</button>
    <a href="sale_entry.php" class="btn btn-secondary">Cancel</a>
</form>

<script>
$(document).ready(function() {
    $(document).on('change', '.item-select', function() {
        var rate = $(this).find('option:selected').data('rate');
        $(this).closest('tr').find('.rate').val(rate);
    });

    $(document).on('input', '.qty, .rate', function() {
        var row = $(this).closest('tr');
        var qty = row.find('.qty').val();
        var rate = row.find('.rate').val();
        var total = qty * rate;
        row.find('.total').val(total.toFixed(2));
        calculateGrandTotal();
    });

    $('#addRow').click(function() {
        var row = $('#productTable tbody tr:first').clone();
        row.find('input').val('');
        $('#productTable tbody').append(row);
    });

    $(document).on('click', '.remove-row', function() {
        if ($('#productTable tbody tr').length > 1) {
            $(this).closest('tr').remove();
            calculateGrandTotal();
        }
    });

    function calculateGrandTotal() {
        var grandTotal = 0;
        $('.total').each(function() {
            grandTotal += parseFloat($(this).val()) || 0;
        });
        $('#grandTotal').val(grandTotal.toFixed(2));
    }
});
</script>
<?php else: ?>
<h3>Existing Sales</h3>
<table class="table table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Customer</th>
            <th>Invoice</th>
            <th>Date</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $result = $conn->query("SELECT s.*, a.name as customer FROM sales s JOIN accounts a ON s.customer_id = a.id");
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['customer']}</td>
                <td>{$row['invoice_number']}</td>
                <td>{$row['sale_date']}</td>
                <td>{$row['total_amount']}</td>
            </tr>";
        }
        ?>
    </tbody>
</table>
<?php endif; ?>
<?php include 'footer.php'; ?>