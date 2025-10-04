<?php
// purchase_entry.php - Purchase Entry (Add + Edit + Expense Deduction)
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';
$edit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// ✅ Fetch suppliers
$stmt = $conn->prepare("SELECT id, name FROM accounts WHERE type = ? ORDER BY name ASC");
$type = 'supplier';
$stmt->bind_param("s", $type);
$stmt->execute();
$suppliers = $stmt->get_result();
$stmt->close();

// ✅ Fetch items
$stmt = $conn->prepare("SELECT id, name, purchase_rate FROM items ORDER BY name ASC");
$stmt->execute();
$items = $stmt->get_result();
$stmt->close();

// ✅ Get next invoice number
$result = $conn->query("SELECT MAX(invoice_number) AS last_invoice FROM purchases");
$last_invoice = $result->fetch_assoc()['last_invoice'] ?? 0;
$next_invoice = $last_invoice + 1;

// ✅ Load data if editing
$edit_purchase = null;
$edit_details = [];
if ($edit_id) {
    $stmt = $conn->prepare("SELECT * FROM purchases WHERE id=?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_purchase = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM purchase_details WHERE purchase_id=?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_details = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ✅ Handle Add/Edit Submission
if (($action === 'add' || $action === 'edit') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = intval($_POST['supplier_id']);
    $invoice_number = intval($_POST['invoice_number']);
    $purchase_date = $_POST['purchase_date'];
    $hamali = floatval($_POST['hamali'] ?? 0);
    $freight = floatval($_POST['freight'] ?? 0);
    $uchal = floatval($_POST['uchal'] ?? 0);
    $total_amount = 0;

    if (!$supplier_id || !$invoice_number || !$purchase_date) {
        die("Error: Supplier, Invoice Number, and Purchase Date are required.");
    }

    if ($action === 'add') {
        $stmt = $conn->prepare("INSERT INTO purchases (supplier_id, invoice_number, purchase_date, hamali, freight, uchal, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdddd", $supplier_id, $invoice_number, $purchase_date, $hamali, $freight, $uchal, $total_amount);
        $stmt->execute();
        $purchase_id = $conn->insert_id;
        $stmt->close();
    } else {
        $purchase_id = intval($_POST['purchase_id']);
        $stmt = $conn->prepare("UPDATE purchases SET supplier_id=?, invoice_number=?, purchase_date=?, hamali=?, freight=?, uchal=? WHERE id=?");
        $stmt->bind_param("issdddi", $supplier_id, $invoice_number, $purchase_date, $hamali, $freight, $uchal, $purchase_id);
        $stmt->execute();
        $stmt->close();
        $conn->query("DELETE FROM purchase_details WHERE purchase_id={$purchase_id}");
    }

    foreach ($_POST['item_id'] as $i => $item_id) {
        $item_id = intval($item_id);
        $quantity = floatval($_POST['quantity'][$i] ?? 0);
        $weight = floatval($_POST['weight'][$i] ?? 0);
        $rate = floatval($_POST['rate'][$i] ?? 0);
        $total = $weight * $rate;
        $total_amount += $total;

        $stmt = $conn->prepare("INSERT INTO purchase_details (purchase_id, item_id, quantity, weight, rate, total) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iidddd", $purchase_id, $item_id, $quantity, $weight, $rate, $total);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE items SET stock = stock + ? WHERE id = ?");
        $stmt->bind_param("di", $quantity, $item_id);
        $stmt->execute();
        $stmt->close();
    }

    $net_total = $total_amount - ($hamali + $freight + $uchal);
    $stmt = $conn->prepare("UPDATE purchases SET total_amount=? WHERE id=?");
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
        <button class="btn btn-primary" id="toggleForm">➕ <?= $edit_id ? 'Edit Purchase' : 'New Purchase Entry' ?></button>
    </div>

    <div id="purchaseFormContainer" class="card p-4 shadow mb-4" style="<?= $edit_id ? '' : 'display:none;' ?>">
        <form method="POST" id="purchaseForm" action="purchase_entry.php?action=<?= $edit_id ? 'edit' : 'add' ?>">
            <input type="hidden" name="purchase_id" value="<?= $edit_purchase['id'] ?? '' ?>">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Supplier</label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">Select Supplier</option>
                        <?php $suppliers->data_seek(0); while ($row = $suppliers->fetch_assoc()) { ?>
                            <option value="<?= $row['id'] ?>" <?= ($edit_purchase && $edit_purchase['supplier_id'] == $row['id']) ? 'selected' : '' ?>><?= htmlspecialchars($row['name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Invoice Number</label>
                    <input type="number" name="invoice_number" class="form-control" value="<?= $edit_purchase['invoice_number'] ?? $next_invoice ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Purchase Date</label>
                    <input type="date" name="purchase_date" class="form-control" value="<?= $edit_purchase['purchase_date'] ?? date('Y-m-d') ?>" required>
                </div>
            </div>

            <h5 class="fw-bold">Products</h5>
            <table class="table table-bordered" id="productTable">
                <thead><tr><th>Product</th><th>Qty</th><th>Weight</th><th>Rate</th><th>Total</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if (!empty($edit_details)) { foreach ($edit_details as $d) { ?>
                        <tr>
                            <td><select name="item_id[]" class="form-select item-select">
                                <?php $items->data_seek(0); while ($r = $items->fetch_assoc()) { ?>
                                    <option value="<?= $r['id'] ?>" data-rate="<?= $r['purchase_rate'] ?>" <?= $d['item_id']==$r['id']?'selected':'' ?>><?= htmlspecialchars($r['name']) ?></option>
                                <?php } ?>
                            </select></td>
                            <td><input type="number" name="quantity[]" class="form-control qty" value="<?= $d['quantity'] ?>"></td>
                            <td><input type="number" step="0.01" name="weight[]" class="form-control weight" value="<?= $d['weight'] ?>"></td>
                            <td><input type="number" step="0.01" name="rate[]" class="form-control rate" value="<?= $d['rate'] ?>"></td>
                            <td><input type="number" step="0.01" class="form-control total" value="<?= $d['total'] ?>" readonly></td>
                            <td><button type="button" class="btn btn-danger btn-sm remove-row">❌</button></td>
                        </tr>
                    <?php } } else { ?>
                        <tr>
                            <td><select name="item_id[]" class="form-select item-select" required>
                                <?php $items->data_seek(0); while ($r = $items->fetch_assoc()) { ?>
                                    <option value="<?= $r['id'] ?>" data-rate="<?= $r['purchase_rate'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                                <?php } ?>
                            </select></td>
                            <td><input type="number" name="quantity[]" class="form-control qty"></td>
                            <td><input type="number" step="0.01" name="weight[]" class="form-control weight"></td>
                            <td><input type="number" step="0.01" name="rate[]" class="form-control rate"></td>
                            <td><input type="number" step="0.01" class="form-control total" readonly></td>
                            <td><button type="button" class="btn btn-danger btn-sm remove-row">❌</button></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <button type="button" id="addRow" class="btn btn-outline-secondary mb-3">➕ Add Product</button>

            <div class="row mb-3">
                <div class="col-md-4"><label>Hamali</label><input type="number" name="hamali" id="hamali" class="form-control" value="<?= $edit_purchase['hamali'] ?? 0 ?>"></div>
                <div class="col-md-4"><label>Freight</label><input type="number" name="freight" id="freight" class="form-control" value="<?= $edit_purchase['freight'] ?? 0 ?>"></div>
                <div class="col-md-4"><label>Uchal</label><input type="number" name="uchal" id="uchal" class="form-control" value="<?= $edit_purchase['uchal'] ?? 0 ?>"></div>
            </div>

            <div class="mb-3">
                <label>Net Total (After Deductions)</label>
                <input type="number" step="0.01" id="grandTotal" class="form-control fw-bold" readonly>
            </div>

            <button type="submit" class="btn btn-success">💾 Save</button>
            <button type="button" class="btn btn-secondary" id="closeForm">Cancel</button>
        </form>
    </div>

    <div class="card shadow p-3">
        <h4 class="fw-bold mb-3">📋 Purchase List</h4>
        <table class="table table-hover">
            <thead><tr><th>#</th><th>Supplier</th><th>Invoice</th><th>Date</th><th>Total</th><th>Edit</th></tr></thead>
            <tbody>
                <?php
                $stmt = $conn->prepare("SELECT p.*, a.name AS supplier FROM purchases p JOIN accounts a ON p.supplier_id=a.id ORDER BY p.id ASC LIMIT ? OFFSET ?");
                $stmt->bind_param("ii", $limit, $offset);
                $stmt->execute();
                $result = $stmt->get_result();
                while($row=$result->fetch_assoc()){
                    echo "<tr>
                        <td>{$row['id']}</td>
                        <td>".htmlspecialchars($row['supplier'])."</td>
                        <td>{$row['invoice_number']}</td>
                        <td>{$row['purchase_date']}</td>
                        <td><strong>{$row['total_amount']}</strong></td>
                        <td><a href='purchase_entry.php?id={$row['id']}' class='btn btn-warning btn-sm'>✏ Edit</a></td>
                    </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function(){
    function calculateTotals(){
        let grandTotal=0;
        $('#productTable tbody tr').each(function(){
            let weight=parseFloat($(this).find('.weight').val())||0;
            let rate=parseFloat($(this).find('.rate').val())||0;
            let rowTotal=weight*rate;
            $(this).find('.total').val(rowTotal.toFixed(2));
            grandTotal+=rowTotal;
        });
        let hamali=parseFloat($('#hamali').val())||0;
        let freight=parseFloat($('#freight').val())||0;
        let uchal=parseFloat($('#uchal').val())||0;
        $('#grandTotal').val((grandTotal-hamali-freight-uchal).toFixed(2));
    }
    $(document).on('input','.weight,.rate,#hamali,#freight,#uchal',calculateTotals);
    $('#addRow').click(function(){
        let row=$('#productTable tbody tr:first').clone();
        row.find('input').val('');
        $('#productTable tbody').append(row);
    });
    $(document).on('click','.remove-row',function(){if($('#productTable tbody tr').length>1){$(this).closest('tr').remove();calculateTotals();}});
    calculateTotals();
});
</script>

<?php include 'footer.php'; ?>
