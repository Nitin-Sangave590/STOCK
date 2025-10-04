<?php
// purchase_entry.php - Purchase Entry
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// ✅ Fetch suppliers safely
$stmt = $conn->prepare("SELECT id, name FROM accounts WHERE type = ? ORDER BY name ASC");
$type = 'supplier';
$stmt->bind_param("s", $type);
$stmt->execute();
$suppliers = $stmt->get_result();
$stmt->close();

// ✅ Fetch items safely
$stmt = $conn->prepare("SELECT id, name, purchase_rate FROM items ORDER BY name ASC");
$stmt->execute();
$items = $stmt->get_result();
$stmt->close();

// ✅ Get next invoice number (auto-increment manually)
$result = $conn->query("SELECT MAX(invoice_number) AS last_invoice FROM purchases");
$last_invoice = $result->fetch_assoc()['last_invoice'] ?? 0;
$next_invoice = $last_invoice + 1;

// ✅ Handle form submission
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = intval($_POST['supplier_id']);
    $invoice_number = intval($_POST['invoice_number']); // auto-filled, still accept manually
    $purchase_date = $_POST['purchase_date'];
    $hamali = floatval($_POST['hamali'] ?? 0);
    $freight = floatval($_POST['freight'] ?? 0);
    $total_amount = 0;

    if (!$supplier_id || !$invoice_number || !$purchase_date) {
        die("Error: Supplier, Invoice Number, and Purchase Date are required.");
    }

    $stmt = $conn->prepare("INSERT INTO purchases (supplier_id, invoice_number, purchase_date, hamali, freight, total_amount) 
                            VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issddd", $supplier_id, $invoice_number, $purchase_date, $hamali, $freight, $total_amount);
    if (!$stmt->execute()) {
        error_log("Purchase Insert Failed: " . $conn->error);
        die("Failed to insert purchase. Check logs.");
    }
    $purchase_id = $conn->insert_id;
    $stmt->close();

    // Insert purchase details
    if (!empty($_POST['item_id'])) {
        foreach ($_POST['item_id'] as $index => $item_id) {
            $item_id = intval($item_id);
            $quantity = floatval($_POST['quantity'][$index] ?? 0);
            $weight = floatval($_POST['weight'][$index] ?? 0);
            $rate = floatval($_POST['rate'][$index] ?? 0);
            $total = $weight * $rate;
            $total_amount += $total;

            $stmt = $conn->prepare("INSERT INTO purchase_details (purchase_id, item_id, quantity, weight, rate, total) 
                                    VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iidddd", $purchase_id, $item_id, $quantity, $weight, $rate, $total);
            $stmt->execute();
            $stmt->close();

            // Update stock
            $stmt = $conn->prepare("UPDATE items SET stock = stock + ? WHERE id = ?");
            $stmt->bind_param("di", $quantity, $item_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    $net_total = $total_amount + $hamali + $freight;
    $stmt = $conn->prepare("UPDATE purchases SET total_amount = ? WHERE id = ?");
    $stmt->bind_param("di", $net_total, $purchase_id);
    $stmt->execute();
    $stmt->close();

    header("Location: purchase_entry.php?success=1&page=$page");
    exit;
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold">Purchase Management</h2>
        <button class="btn btn-primary" id="toggleForm">➕ New Purchase Entry</button>
    </div>

    <!-- ✅ Purchase Entry Form -->
    <div id="purchaseFormContainer" class="card p-4 shadow mb-4" style="display:none;">
        <form method="POST" id="purchaseForm" action="purchase_entry.php?action=add">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Supplier</label>
                    <div class="input-group">
                        <select name="supplier_id" id="supplierSelect" class="form-select" required>
                            <option value="">Select Supplier</option>
                            <?php while ($row = $suppliers->fetch_assoc()) { ?>
                                <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                            <?php } ?>
                        </select>
                        <button type="button" class="btn btn-outline-success" id="" onclick="window.location.href='accounts.php?action=add';">
                            <i class="bi bi-plus"></i>+
                        </button>

                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Invoice Number</label>
                    <input type="number" name="invoice_number" class="form-control" value="<?= $next_invoice ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Purchase Date</label>
                    <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
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

            <!-- Expenses -->
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

            <button type="submit" class="btn btn-success">💾 Save Purchase</button>
            <button type="button" class="btn btn-secondary" id="closeForm">Cancel</button>
            <div class="form-check mt-3">
                <input type="checkbox" class="form-check-input" id="printCheckbox">
                <label class="form-check-label" for="printCheckbox">Print Bill</label>
            </div>
        </form>
    </div>

    <!-- ✅ Purchases List -->
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
                $stmt = $conn->prepare("SELECT p.*, a.name AS supplier 
                                        FROM purchases p 
                                        JOIN accounts a ON p.supplier_id = a.id 
                                        ORDER BY p.id ASD 
                                        LIMIT ? OFFSET ?");
                $stmt->bind_param("ii", $limit, $offset);
                $stmt->execute();
                $result = $stmt->get_result();
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
                $stmt->close();

                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM purchases");
                $stmt->execute();
                $total_rows = $stmt->get_result()->fetch_assoc()['total'];
                $stmt->close();
                $total_pages = ceil($total_rows / $limit);
                ?>
            </tbody>
        </table>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php } ?>
            </ul>
        </nav>
    </div>
</div>

<!-- ✅ Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="addSupplierForm">
      <div class="modal-header">
        <h5 class="modal-title">Add Supplier</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Supplier Name</label>
        <input type="text" name="name" class="form-control" required>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Add</button>
      </div>
    </form>
  </div>
</div>

<script>
$(document).ready(function() {
    $("#toggleForm").click(() => $("#purchaseFormContainer").slideDown());
    $("#closeForm").click(() => $("#purchaseFormContainer").slideUp());

    $(document).on('change', '.item-select', function() {
        let rate = $(this).find('option:selected').data('rate');
        $(this).closest('tr').find('.rate').val(rate);
        calculateTotals();
    });

    $(document).on('input', '.weight, .rate, #hamali, #freight', calculateTotals);

    $('#addRow').click(function() {
        let row = $('#productTable tbody tr:first').clone();
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
        $('#grandTotal').val((grandTotal + hamali + freight).toFixed(2));
    }

    // Supplier Add Modal
    $('#addSupplierBtn').click(() => $('#addSupplierModal').modal('show'));
    $('#addSupplierForm').submit(function(e){
        e.preventDefault();
        $.post('save_supplier.php', $(this).serialize(), function(newOption){
            $('#supplierSelect').append(newOption);
            $('#addSupplierModal').modal('hide');
            $('#addSupplierForm')[0].reset();
        });
    });

    $('#purchaseForm').submit(function(e) {
        if ($('#printCheckbox').is(':checked')) {
            e.preventDefault();
            // Fill Print Area
            window.print();
            $(this).unbind('submit').submit();
        }
    });
});
</script>

<?php include 'footer.php'; ?>
