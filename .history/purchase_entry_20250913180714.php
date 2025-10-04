<?php
// purchase_entry.php - Purchase Entry
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$suppliers = $conn->query("SELECT * FROM accounts WHERE type='supplier' ORDER BY name ASC");
$items = $conn->query("SELECT * FROM items ORDER BY name ASC");

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = intval($_POST['supplier_id']);
    $invoice_number = $conn->real_escape_string($_POST['invoice_number']);
    $purchase_date = $conn->real_escape_string($_POST['purchase_date']);
    $hamali = floatval($_POST['hamali']);
    $freight = floatval($_POST['freight']);
    $total_amount = 0;

    // Insert main purchase
    $insertPurchase = $conn->query("INSERT INTO purchases (supplier_id, invoice_number, purchase_date, hamali, freight, total_amount) 
        VALUES ($supplier_id, '$invoice_number', '$purchase_date', $hamali, $freight, 0)");
    if (!$insertPurchase) {
        die("Insert Purchase Error: " . $conn->error);
    }
    $purchase_id = $conn->insert_id;

    // Insert purchase details
    for ($i = 0; $i < count($_POST['item_id']); $i++) {
        $item_id = intval($_POST['item_id'][$i]);
        $quantity = floatval($_POST['quantity'][$i]);
        $weight = floatval($_POST['weight'][$i]);
        $rate = floatval($_POST['rate'][$i]);
        $total = $weight * $rate;
        $total_amount += $total;

        $insertDetail = $conn->query("INSERT INTO purchase_details (purchase_id, item_id, quantity, weight, rate, total) 
            VALUES ($purchase_id, $item_id, $quantity, $weight, $rate, $total)");
        if (!$insertDetail) {
            die("Insert Purchase Detail Error: " . $conn->error);
        }

        $updateStock = $conn->query("UPDATE items SET stock = stock + $quantity WHERE id = $item_id");
        if (!$updateStock) {
            die("Stock Update Error: " . $conn->error);
        }
    }

    // Final total = product total + expenses
    $net_total = $total_amount + $hamali + $freight;
    $conn->query("UPDATE purchases SET total_amount = $net_total WHERE id = $purchase_id");

    header("Location: purchase_entry.php?success=1");
    exit;
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold">Purchase Management</h2>
        <button class="btn btn-primary" id="toggleForm">➕ New Purchase Entry</button>
    </div>

    <!-- Purchase Entry Form -->
    <div id="purchaseFormContainer" class="card p-4 shadow mb-4" style="display:none;">
        <form method="POST" id="purchaseForm" action="purchase_entry.php?action=add">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Supplier</label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">Select Supplier</option>
                        <?php while ($row = $suppliers->fetch_assoc()) { ?>
                            <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Invoice Number</label>
                    <input type="text" name="invoice_number" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Purchase Date</label>
                    <input type="date" name="purchase_date" class="form-control" required value="<?= date('Y-m-d'); ?>">
                </div>
            </div>

            <h5 class="fw-bold">Products</h5>
            <table class="table table-bordered table-sm" id="productTable">
                <thead class="table-light">
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
                                    <option value="<?= $row['id'] ?>" data-rate="<?= $row['purchase_rate'] ?>">
                                        <?= htmlspecialchars($row['name']) ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </td>
                        <td><input type="number" name="quantity[]" class="form-control qty" min="0" required></td>
                        <td><input type="number" step="0.01" name="weight[]" class="form-control weight" required></td>
                        <td><input type="number" step="0.01" name="rate[]" class="form-control rate" required></td>
                        <td><input type="number" step="0.01" class="form-control total" readonly></td>
                        <td><button type="button" class="btn btn-danger btn-sm remove-row">❌</button></td>
                    </tr>
                </tbody>
            </table>
            <button type="button" id="addRow" class="btn btn-outline-secondary mb-3">➕ Add Product</button>

            <!-- Expenses Section -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Hamali</label>
                    <input type="number" step="0.01" name="hamali" id="hamali" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Freight</label>
                    <input type="number" step="0.01" name="freight" id="freight" class="form-control" value="0">
                </div>
            </div>

            <!-- Grand Total -->
            <div class="mb-3">
                <label class="form-label">Net Total</label>
                <input type="number" step="0.01" id="grandTotal" class="form-control fw-bold" readonly>
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="printCheckbox">
                <label class="form-check-label" for="printCheckbox">Print Bill</label>
            </div>

            <button type="submit" class="btn btn-success">💾 Save Purchase</button>
            <button type="button" class="btn btn-secondary" id="closeForm">Cancel</button>
        </form>
    </div>

    <!-- Purchases List -->
    <div class="card shadow p-3">
        <h4 class="fw-bold mb-3">📋 Purchase List</h4>
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Supplier</th>
                    <th>Invoice</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Print</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $result = $conn->query("SELECT p.*, a.name as supplier 
                                        FROM purchases p 
                                        JOIN accounts a ON p.supplier_id = a.id
                                        ORDER BY p.id DESC
                                        LIMIT $limit OFFSET $offset");

                while ($row = $result->fetch_assoc()) {
                    echo "<tr>
                        <td>{$row['id']}</td>
                        <td>" . htmlspecialchars($row['supplier']) . "</td>
                        <td>" . htmlspecialchars($row['invoice_number']) . "</td>
                        <td>{$row['purchase_date']}</td>
                        <td><strong>{$row['total_amount']}</strong></td>
                        <td><a href='print_purchase.php?id={$row['id']}' target='_blank' class='btn btn-sm btn-outline-primary'>🖨 Print</a></td>
                    </tr>";
                }

                $countResult = $conn->query("SELECT COUNT(*) as total FROM purchases");
                $totalRows = $countResult->fetch_assoc()['total'];
                $totalPages = ceil($totalRows / $limit);
                ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <nav>
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++) { ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php } ?>
            </ul>
        </nav>
    </div>
</div>

<script>
$(document).ready(function() {
    $("#toggleForm").click(() => $("#purchaseFormContainer").slideDown());
    $("#closeForm").click(() => $("#purchaseFormContainer").slideUp());

    $(document).on('change', '.item-select', function() {
        var rate = $(this).find('option:selected').data('rate');
        $(this).closest('tr').find('.rate').val(rate);
        calculateTotals();
    });

    $(document).on('input', '.weight, .rate, #hamali, #freight', calculateTotals);

    $('#addRow').click(function() {
        var row = $('#productTable tbody tr:first').clone();
        row.find('input').val('');
        $('#productTable tbody').append(row);
    });

    $(document).on('click', '.remove-row', function() {
        if ($('#productTable tbody tr').length > 1) {
            $(this).closest('tr').remove();
            calculateTotals();
        }
    });

    function calculateTotals() {
        let grandTotal = 0;
        $('#productTable tbody tr').each(function() {
            let weight = parseFloat($(this).find('.weight').val()) || 0;
            let rate = parseFloat($(this).find('.rate').val()) || 0;
            let rowTotal = weight * rate;
            $(this).find('.total').val(rowTotal.toFixed(2));
            grandTotal += rowTotal;
        });

        let hamali = parseFloat($('#hamali').val()) || 0;
        let freight = parseFloat($('#freight').val()) || 0;
        let netTotal = grandTotal + hamali + freight; // ✅ Corrected calculation
        $('#grandTotal').val(netTotal.toFixed(2));
    }
});
</script>

<?php include 'footer.php'; ?>
