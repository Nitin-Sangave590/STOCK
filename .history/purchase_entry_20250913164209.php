<?php
// purchase_entry.php - Purchase Entry
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';

$suppliers = $conn->query("SELECT * FROM accounts WHERE type='supplier'");
$items = $conn->query("SELECT * FROM items");

if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $supplier_id = $_POST['supplier_id'];
    $invoice_number = $_POST['invoice_number'];
    $purchase_date = $_POST['purchase_date'];
    $total_amount = 0;

    $sql = "INSERT INTO purchases (supplier_id, invoice_number, purchase_date, total_amount) VALUES ($supplier_id, '$invoice_number', '$purchase_date', 0)";
    $conn->query($sql);
    $purchase_id = $conn->insert_id;

    for ($i = 0; $i < count($_POST['item_id']); $i++) {
        $item_id = $_POST['item_id'][$i];
        $quantity = $_POST['quantity'][$i];
        $weight = $_POST['weight'][$i];
        $rate = $_POST['rate'][$i];
        $total = $quantity * $rate;

        $total_amount += $total;

        $sql = "INSERT INTO purchase_details (purchase_id, item_id, quantity, weight, rate, total) VALUES ($purchase_id, $item_id, $quantity, $weight, $rate, $total)";
        $conn->query($sql);
    }

    $sql = "UPDATE purchases SET total_amount = $total_amount WHERE id = $purchase_id";
    $conn->query($sql);

    header("Location: purchase_entry.php");
    exit;
}
?>
<h2>Purchase Entry</h2>
<a href="purchase_entry.php?action=add" class="btn btn-primary mb-3">New Purchase Entry</a>

<?php if ($action == 'add'): ?>
<form method="POST" id="purchaseForm">
    <div class="mb-3">
        <label>Supplier</label>
        <select name="supplier_id" class="form-select" required>
            <?php while ($row = $suppliers->fetch_assoc()) { ?>
                <option value="<?php echo $row['id']; ?>"><?php echo $row['name']; ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="mb-3">
        <label>Invoice Number</label>
        <input type="text" name="invoice_number" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Purchase Date</label>
        <input type="date" name="purchase_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
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
                            <option value="<?php echo $row['id']; ?>" data-rate="<?php echo $row['purchase_rate']; ?>"><?php echo $row['name']; ?></option>
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
    <button type="submit" class="btn btn-primary">Save Purchase</button>
    <a href="purchase_entry.php" class="btn btn-secondary">Cancel</a>
    <div class="form-check mt-3">
        <input type="checkbox" class="form-check-input" id="printCheckbox">
        <label class="form-check-label" for="printCheckbox">Print Bill</label>
    </div>
</form>

<div class="print-area" id="printArea">
    <h2>Purchase Bill</h2>
</div>

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

    $('#purchaseForm').submit(function(e) {
        if ($('#printCheckbox').is(':checked')) {
            var printContent = '<p>Supplier: ' + $('select[name="supplier_id"] option:selected').text() + '</p>';
            printContent += '<p>Invoice: ' + $('input[name="invoice_number"]').val() + '</p>';
            printContent += '<p>Date: ' + $('input[name="purchase_date"]').val() + '</p>';
            printContent += '<table border="1"><tr><th>Product</th><th>Qty</th><th>Weight</th><th>Rate</th><th>Total</th></tr>';
            $('#productTable tbody tr').each(function() {
                printContent += '<tr>';
                printContent += '<td>' + $(this).find('.item-select option:selected').text() + '</td>';
                printContent += '<td>' + $(this).find('.qty').val() + '</td>';
                printContent += '<td>' + $(this).find('input[name="weight[]"]').val() + '</td>';
                printContent += '<td>' + $(this).find('.rate').val() + '</td>';
                printContent += '<td>' + $(this).find('.total').val() + '</td>';
                printContent += '</tr>';
            });
            printContent += '</table>';
            printContent += '<p>Grand Total: ' + $('#grandTotal').val() + '</p>';
            $('#printArea').html(printContent);
            window.print();
            e.preventDefault(); // Prevent form submission if printing, but since header redirect after post, adjust if needed
        }
    });
});
</script>
<?php else: ?>
<h3>Existing Purchases</h3>
<table class="table table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Supplier</th>
            <th>Invoice</th>
            <th>Date</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $result = $conn->query("SELECT p.*, a.name as supplier FROM purchases p JOIN accounts a ON p.supplier_id = a.id");
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['supplier']}</td>
                <td>{$row['invoice_number']}</td>
                <td>{$row['purchase_date']}</td>
                <td>{$row['total_amount']}</td>
            </tr>";
        }
        ?>
    </tbody>
</table>
<?php endif; ?>
<?php include 'footer.php'; ?>