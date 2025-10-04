<?php
// purchase_entry.php - Purchase Entry
ob_start();
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$modal_message = '';
$modal_type = '';

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

$purchase = [];
$details = [];
if ($action === 'edit') {
    $id = intval($_GET['id'] ?? 0);
    if ($id) {
        $stmt = $conn->prepare("SELECT * FROM purchases WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $purchase = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("SELECT * FROM purchase_details WHERE purchase_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $details[] = $row;
        }
        $stmt->close();
    }
}

// ✅ Handle Add
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = intval($_POST['supplier_id']);
    $invoice_number = intval($_POST['invoice_number']);
    $purchase_date = $_POST['purchase_date'];
    $hamali = floatval($_POST['hamali'] ?? 0);
    $freight = floatval($_POST['freight'] ?? 0);
    $uchal = floatval($_POST['uchal'] ?? 0);
    $product_total = 0;

    if ($supplier_id && $invoice_number && $purchase_date) {
        $stmt = $conn->prepare("INSERT INTO purchases (supplier_id, invoice_number, purchase_date, hamali, freight, uchal, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $tmp_total = 0;
        $stmt->bind_param("issdddd", $supplier_id, $invoice_number, $purchase_date, $hamali, $freight, $uchal, $tmp_total);
        if ($stmt->execute()) {
            $purchase_id = $conn->insert_id;
            $stmt->close();

            foreach ($_POST['item_id'] as $i => $item_id) {
                $item_id = intval($item_id);
                $qty = floatval($_POST['quantity'][$i] ?? 0);
                $weight = floatval($_POST['weight'][$i] ?? 0);
                $rate = floatval($_POST['rate'][$i] ?? 0);
                $total = $weight * $rate;
                $product_total += $total;

                $stmt = $conn->prepare("INSERT INTO purchase_details (purchase_id, item_id, quantity, weight, rate, total) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iidddd", $purchase_id, $item_id, $qty, $weight, $rate, $total);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE items SET stock = stock + ? WHERE id = ?");
                $stmt->bind_param("di", $qty, $item_id);
                $stmt->execute();
                $stmt->close();
            }

            $net_total = $product_total - $hamali - $freight - $uchal;
            $stmt = $conn->prepare("UPDATE purchases SET total_amount = ? WHERE id = ?");
            $stmt->bind_param("di", $net_total, $purchase_id);
            $stmt->execute();
            $stmt->close();

            $modal_message = "Purchase added successfully!";
            $modal_type = 'success';
        } else {
            $modal_message = "Failed to insert purchase.";
            $modal_type = 'error';
        }
    } else {
        $modal_message = "Supplier, Invoice Number, Date required.";
        $modal_type = 'error';
    }
}

// ✅ Handle Update
elseif ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $purchase_id = intval($_GET['id']);
    if ($purchase_id) {
        $supplier_id = intval($_POST['supplier_id']);
        $invoice_number = intval($_POST['invoice_number']);
        $purchase_date = $_POST['purchase_date'];
        $hamali = floatval($_POST['hamali'] ?? 0);
        $freight = floatval($_POST['freight'] ?? 0);
        $uchal = floatval($_POST['uchal'] ?? 0);
        $product_total = 0;

        $stmt = $conn->prepare("SELECT item_id, quantity FROM purchase_details WHERE purchase_id = ?");
        $stmt->bind_param("i", $purchase_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($old = $res->fetch_assoc()) {
            $st = $conn->prepare("UPDATE items SET stock = stock - ? WHERE id = ?");
            $st->bind_param("di", $old['quantity'], $old['item_id']);
            $st->execute();
            $st->close();
        }
        $stmt->close();

        $conn->query("DELETE FROM purchase_details WHERE purchase_id = $purchase_id");

        foreach ($_POST['item_id'] as $i => $item_id) {
            $item_id = intval($item_id);
            $qty = floatval($_POST['quantity'][$i] ?? 0);
            $weight = floatval($_POST['weight'][$i] ?? 0);
            $rate = floatval($_POST['rate'][$i] ?? 0);
            $total = $weight * $rate;
            $product_total += $total;

            $stmt = $conn->prepare("INSERT INTO purchase_details (purchase_id, item_id, quantity, weight, rate, total) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iidddd", $purchase_id, $item_id, $qty, $weight, $rate, $total);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE items SET stock = stock + ? WHERE id = ?");
            $stmt->bind_param("di", $qty, $item_id);
            $stmt->execute();
            $stmt->close();
        }

        $net_total = $product_total - $hamali - $freight - $uchal;
        $stmt = $conn->prepare("UPDATE purchases SET supplier_id=?, invoice_number=?, purchase_date=?, hamali=?, freight=?, uchal=?, total_amount=? WHERE id=?");
        $stmt->bind_param("issddddi", $supplier_id, $invoice_number, $purchase_date, $hamali, $freight, $uchal, $net_total, $purchase_id);
        $stmt->execute();
        $stmt->close();

        $modal_message = "Purchase updated successfully!";
        $modal_type = 'success';
    } else {
        $modal_message = "Invalid ID";
        $modal_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Purchase Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<div class="container mt-4">
<div class="d-flex justify-content-between mb-3">
<h2>Purchase Management</h2>
<button class="btn btn-primary" id="toggleForm">➕ New Purchase</button>
</div>

<div id="purchaseFormContainer" class="card p-4 shadow mb-4" style="display:<?= ($action === 'edit') ? 'block' : 'none' ?>;">
<form method="POST" id="purchaseForm" action="purchase_entry.php?action=<?= ($action === 'edit') ? 'update&id=' . intval($_GET['id']) : 'add' ?>">
<div class="row mb-3">
<div class="col-md-4">
<label>Supplier</label>
<select name="supplier_id" class="form-select" required>
<option value="">Select Supplier</option>
<?php $suppliers->data_seek(0); while($row=$suppliers->fetch_assoc()){ ?>
<option value="<?= $row['id'] ?>" <?= ($action==='edit' && $row['id']==$purchase['supplier_id'])?'selected':'' ?>><?= htmlspecialchars($row['name']) ?></option>
<?php } ?>
</select>
</div>
<div class="col-md-4">
<label>Invoice</label>
<input type="number" name="invoice_number" class="form-control" value="<?= ($action==='edit')?$purchase['invoice_number']:$next_invoice ?>" <?= ($action==='edit')?'':'readonly' ?>>
</div>
<div class="col-md-4">
<label>Date</label>
<input type="date" name="purchase_date" class="form-control" value="<?= ($action==='edit')?$purchase['purchase_date']:date('Y-m-d') ?>">
</div>
</div>

<h5>Products</h5>
<table class="table table-bordered" id="productTable">
<thead><tr><th>Product</th><th>Qty</th><th>Weight</th><th>Rate</th><th>Total</th><th>Action</th></tr></thead>
<tbody>
<?php $has_rows=false; if($action==='edit' && !empty($details)){ $has_rows=true; foreach($details as $d){ ?>
<tr>
<td><select name="item_id[]" class="form-select item-select"><?php $items->data_seek(0); while($r=$items->fetch_assoc()){ ?><option value="<?= $r['id'] ?>" data-rate="<?= $r['purchase_rate'] ?>" <?= ($r['id']==$d['item_id'])?'selected':'' ?>><?= htmlspecialchars($r['name']) ?></option><?php } ?></select></td>
<td><input type="number" name="quantity[]" class="form-control qty" value="<?= $d['quantity'] ?>"></td>
<td><input type="number" step="0.01" name="weight[]" class="form-control weight" value="<?= $d['weight'] ?>"></td>
<td><input type="number" step="0.01" name="rate[]" class="form-control rate" value="<?= $d['rate'] ?>"></td>
<td><input type="number" step="0.01" class="form-control total" value="<?= $d['total'] ?>" readonly></td>
<td><button type="button" class="btn btn-danger btn-sm remove-row">❌</button></td>
</tr>
<?php }} if(!$has_rows){ ?>
<tr>
<td><select name="item_id[]" class="form-select item-select"><?php $items->data_seek(0); while($r=$items->fetch_assoc()){ ?><option value="<?= $r['id'] ?>" data-rate="<?= $r['purchase_rate'] ?>"><?= htmlspecialchars($r['name']) ?></option><?php } ?></select></td>
<td><input type="number" name="quantity[]" class="form-control qty"></td>
<td><input type="number" step="0.01" name="weight[]" class="form-control weight"></td>
<td><input type="number" step="0.01" name="rate[]" class="form-control rate"></td>
<td><input type="number" step="0.01" class="form-control total" readonly></td>
<td><button type="button" class="btn btn-danger btn-sm remove-row">❌</button></td>
</tr>
<?php } ?>
</tbody>
</table>
<button type="button" id="addRow" class="btn btn-outline-secondary">➕ Add Product</button>

<div class="row mt-3">
<div class="col-md-4"><label>Hamali</label><input type="number" step="0.01" name="hamali" id="hamali" class="form-control" value="<?= ($action==='edit')?$purchase['hamali']:'0' ?>"></div>
<div class="col-md-4"><label>Freight</label><input type="number" step="0.01" name="freight" id="freight" class="form-control" value="<?= ($action==='edit')?$purchase['freight']:'0' ?>"></div>
<div class="col-md-4"><label>Uchal</label><input type="number" step="0.01" name="uchal" id="uchal" class="form-control" value="<?= ($action==='edit')?$purchase['uchal']:'0' ?>"></div>
</div>
<div class="mt-3"><label>Product Total</label><input type="number" step="0.01" id="productTotal" class="form-control" readonly></div>
<div class="mt-3"><label>Net Total</label><input type="number" step="0.01" id="grandTotal" class="form-control fw-bold" readonly></div>
<button type="submit" class="btn btn-success mt-3"><?= ($action==='edit')?'Update':'💾 Save' ?></button>
<button type="button" class="btn btn-secondary mt-3" id="closeForm">Cancel</button>
</form>
</div>

<div class="card shadow p-3">
<h4>📋 Purchase List</h4>
<table class="table table-hover"><thead class="table-dark"><tr><th>#</th><th>Supplier</th><th>Invoice</th><th>Date</th><th>Total</th><th>Edit</th><th>Print</th></tr></thead><tbody>
<?php $stmt=$conn->prepare("SELECT p.*,a.name as supplier FROM purchases p JOIN accounts a ON p.supplier_id=a.id ORDER BY p.id ASC LIMIT ? OFFSET ?"); $stmt->bind_param("ii",$limit,$offset); $stmt->execute(); $res=$stmt->get_result(); while($row=$res->fetch_assoc()){ echo "<tr><td>{$row['id']}</td><td>".htmlspecialchars($row['supplier'])."</td><td>{$row['invoice_number']}</td><td>{$row['purchase_date']}</td><td><strong>{$row['total_amount']}</strong></td><td><a href='purchase_entry.php?action=edit&id={$row['id']}&page={$page}' class='btn btn-sm btn-outline-secondary'>✏</a></td><td><a href='print_purchase.php?id={$row['id']}' target='_blank' class='btn btn-sm btn-outline-primary'>🖨</a></td></tr>"; } $stmt->close(); ?>
</tbody></table>
</div>
</div>

<script>
$(document).ready(function(){
$("#toggleForm").click(()=>$("#purchaseFormContainer").slideDown());
$("#closeForm").click(()=>$("#purchaseFormContainer").slideUp());
$(document).on('change','.item-select',function(){let rate=$(this).find('option:selected').data('rate');$(this).closest('tr').find('.rate').val(rate);calculateTotals();});
$(document).on('input','.weight,.rate,#hamali,#freight,#uchal',calculateTotals);
$('#addRow').click(function(){let r=$('#productTable tbody tr:first').clone();r.find('input').val('');$('#productTable tbody').append(r);});
$(document).on('click','.remove-row',function(){if($('#productTable tbody tr').length>1){$(this).closest('tr').remove();calculateTotals();}});

function calculateTotals(){let productTotal=0;$('#productTable tbody tr').each(function(){let w=parseFloat($(this).find('.weight').val())||0;let rate=parseFloat($(this).find('.rate').val())||0;let t=w*rate;$(this).find('.total').val(t.toFixed(2));productTotal+=t;});$('#productTotal').val(productTotal.toFixed(2));let h=parseFloat($('#hamali').val())||0;let f=parseFloat($('#freight').val())||0;let u=parseFloat($('#uchal').val())||
