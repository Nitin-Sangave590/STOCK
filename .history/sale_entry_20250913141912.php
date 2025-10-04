<?php
// sale_entry.php - Sale Bill Entry
include 'db.php';
include 'header.php';

$customers = $conn->query("SELECT * FROM accounts WHERE type='customer'");

$items = $conn->query("SELECT * FROM items");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
        $total = $quantity * $rate; // Adjust if needed

        $total_amount += $total;

        $sql = "INSERT INTO sale_details (sale_id, item_id, quantity, weight, rate, total) VALUES ($sale_id, $item_id, $quantity, $weight, $rate, $total)";
        $conn->query($sql);
    }

    $sql = "UPDATE sales SET total_amount = $total_amount WHERE id = $sale_id";
    $conn->query($sql);

    echo "<div class='alert alert-success'>Sale added successfully! Sale ID: $sale_id</div>";
}
?>
<h2>Sale Bill Entry</h2>
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
</form>

<script>
$(document).ready(function() {
    // Similar JS as purchase, auto fill rate, calculate totals, add/remove rows
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
<?php include 'footer.php'; ?>