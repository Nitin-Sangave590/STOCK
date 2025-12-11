<?php
// purchase_entry.php - Purchase Entry
ob_start(); // Start output buffering
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Initialize message variables for modals
$modal_message = '';
$modal_type = ''; // 'success' or 'error'

// Fetch suppliers safely with balance
$stmt = $conn->prepare("SELECT a.id, a.name, 
  COALESCE( (SELECT SUM(p.total_amount) FROM purchases p WHERE p.supplier_id = a.id), 0) - 
  COALESCE( (SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.supplier_id = a.id), 0) AS balance 
FROM accounts a WHERE a.type = ? ORDER BY a.name DESC");
$type = 'supplier';
$stmt->bind_param("s", $type);
$stmt->execute();
$suppliers = $stmt->get_result();
$stmt->close();

// Fetch items safely
$stmt = $conn->prepare("SELECT id, name, purchase_rate FROM items ORDER BY name DESC");
$stmt->execute();
$items = $stmt->get_result();
$stmt->close();

// Get next invoice number (auto-increment manually)
$result = $conn->query("SELECT MAX(invoice_number) AS last_invoice FROM purchases");
$last_invoice = $result->fetch_assoc()['last_invoice'] ?? 0;
$next_invoice = $last_invoice + 1;

$purchase = [];
$details = [];
$paid = false;
if ($action === 'edit') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        $modal_message = "Invalid ID";
        $modal_type = 'error';
    } else {
        $stmt = $conn->prepare("SELECT * FROM purchases WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $purchase = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$purchase) {
            $modal_message = "Purchase not found";
            $modal_type = 'error';
        } else {
            $stmt = $conn->prepare("SELECT * FROM purchase_details WHERE purchase_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $details_res = $stmt->get_result();
            while ($row = $details_res->fetch_assoc()) {
                $details[] = $row;
            }
            $stmt->close();

            // Check if paid (has associated payment)
            $stmt_check = $conn->prepare("SELECT id FROM supplier_payments WHERE purchase_id = ?");
            $stmt_check->bind_param("i", $id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $paid = true;
            }
            $stmt_check->close();
        }
    }
}

// Handle form submission
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = intval($_POST['supplier_id']);
    $invoice_number = intval($_POST['invoice_number']);
    $purchase_date = $_POST['purchase_date'];
    $hamali = floatval($_POST['hamali'] ?? 0);
    $freight = floatval($_POST['freight'] ?? 0);
    $uchal = floatval($_POST['uchal'] ?? 0);
    $gross_total = 0; // Sum of product totals

    if (!$supplier_id || !$invoice_number || !$purchase_date) {
        $modal_message = "Error: Supplier, Invoice Number, and Purchase Date are required.";
        $modal_type = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO purchases (supplier_id, invoice_number, purchase_date, hamali, freight, uchal, total_amount) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
        $total_amount = 0; // Temporary, will update later
        $stmt->bind_param("issdddd", $supplier_id, $invoice_number, $purchase_date, $hamali, $freight, $uchal, $total_amount);
        if (!$stmt->execute()) {
            error_log("Purchase Insert Failed: " . $conn->error);
            $modal_message = "Failed to insert purchase. Check logs.";
            $modal_type = 'error';
        } else {
            $purchase_id = $conn->insert_id;
            $stmt->close();

            // Insert purchase details and calculate gross total
            if (!empty($_POST['item_id'])) {
                foreach ($_POST['item_id'] as $index => $item_id) {
                    $item_id = intval($item_id);
                    $quantity = floatval($_POST['quantity'][$index] ?? 0);
                    $weight = floatval($_POST['weight'][$index] ?? 0);
                    $rate = floatval($_POST['rate'][$index] ?? 0);
                    $total = $weight * $rate;
                    $gross_total += $total;

                    $stmt = $conn->prepare("INSERT INTO purchase_details (purchase_id, item_id, quantity, weight, rate, total) 
                                            VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iidddd", $purchase_id, $item_id, $quantity, $weight, $rate, $total);
                    if (!$stmt->execute()) {
                        error_log("Purchase Details Insert Failed: " . $conn->error);
                        $modal_message = "Failed to insert purchase details.";
                        $modal_type = 'error';
                        break;
                    }
                    $stmt->close();

                    // Update stock
                    $stmt = $conn->prepare("UPDATE items SET stock = stock + ? WHERE id = ?");
                    $stmt->bind_param("di", $quantity, $item_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            if ($modal_type !== 'error') {
                $net_total = $gross_total - $hamali - $freight - $uchal; // Net amount after deductions
                $stmt = $conn->prepare("UPDATE purchases SET total_amount = ? WHERE id = ?");
                $stmt->bind_param("di", $net_total, $purchase_id);
                if ($stmt->execute()) {
                    $modal_message = "Purchase added successfully!";
                    $modal_type = 'success';
                } else {
                    error_log("Purchase Total Update Failed: " . $conn->error);
                    $modal_message = "Failed to update purchase total.";
                    $modal_type = 'error';
                }
                $stmt->close();

                // Handle paid toggle for add
                if (isset($_POST['paid'])) {
                    $payment_date = $purchase_date;
                    $amount = $net_total;
                    $description = "Automatic payment for purchase invoice #$invoice_number";
                    $stmt_payment = $conn->prepare("INSERT INTO supplier_payments (supplier_id, purchase_id, payment_date, amount, description) 
                                                    VALUES (?, ?, ?, ?, ?)");
                    $stmt_payment->bind_param("iisds", $supplier_id, $purchase_id, $payment_date, $amount, $description);
                    if (!$stmt_payment->execute()) {
                        error_log("Payment Insert Failed: " . $conn->error);
                        $modal_message .= " Failed to add automatic payment.";
                        $modal_type = 'error';
                    }
                    $stmt_payment->close();
                }
            }
        }
    }
} elseif ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $purchase_id = intval($_GET['id']);
    if (!$purchase_id) {
        $modal_message = "Invalid ID";
        $modal_type = 'error';
    } else {
        $supplier_id = intval($_POST['supplier_id']);
        $invoice_number = intval($_POST['invoice_number']);
        $purchase_date = $_POST['purchase_date'];
        $hamali = floatval($_POST['hamali'] ?? 0);
        $freight = floatval($_POST['freight'] ?? 0);
        $uchal = floatval($_POST['uchal'] ?? 0);
        $gross_total = 0; // Sum of product totals

        if (!$supplier_id || !$invoice_number || !$purchase_date) {
            $modal_message = "Error: Supplier, Invoice Number, and Purchase Date are required.";
            $modal_type = 'error';
        } else {
            // Subtract old stocks
            $stmt = $conn->prepare("SELECT item_id, quantity FROM purchase_details WHERE purchase_id = ?");
            $stmt->bind_param("i", $purchase_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($old = $result->fetch_assoc()) {
                $stmt_stock = $conn->prepare("UPDATE items SET stock = stock - ? WHERE id = ?");
                $stmt_stock->bind_param("di", $old['quantity'], $old['item_id']);
                $stmt_stock->execute();
                $stmt_stock->close();
            }
            $stmt->close();

            // Delete old details
            $stmt = $conn->prepare("DELETE FROM purchase_details WHERE purchase_id = ?");
            $stmt->bind_param("i", $purchase_id);
            $stmt->execute();
            $stmt->close();

            // Update purchase
            $stmt = $conn->prepare("UPDATE purchases SET supplier_id = ?, invoice_number = ?, purchase_date = ?, hamali = ?, freight = ?, uchal = ?, total_amount = ? WHERE id = ?");
            $tmp_total = 0; // Temporary, will update later
            $stmt->bind_param("issddddi", $supplier_id, $invoice_number, $purchase_date, $hamali, $freight, $uchal, $tmp_total, $purchase_id);
            if (!$stmt->execute()) {
                error_log("Purchase Update Failed: " . $conn->error);
                $modal_message = "Failed to update purchase. Check logs.";
                $modal_type = 'error';
            } else {
                $stmt->close();

                // Insert new purchase details and calculate gross total
                if (!empty($_POST['item_id'])) {
                    foreach ($_POST['item_id'] as $index => $item_id) {
                        $item_id = intval($item_id);
                        $quantity = floatval($_POST['quantity'][$index] ?? 0);
                        $weight = floatval($_POST['weight'][$index] ?? 0);
                        $rate = floatval($_POST['rate'][$index] ?? 0);
                        $total = $weight * $rate;
                        $gross_total += $total;

                        $stmt = $conn->prepare("INSERT INTO purchase_details (purchase_id, item_id, quantity, weight, rate, total) 
                                                VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("iidddd", $purchase_id, $item_id, $quantity, $weight, $rate, $total);
                        if (!$stmt->execute()) {
                            error_log("Purchase Details Insert Failed: " . $conn->error);
                            $modal_message = "Failed to insert purchase details.";
                            $modal_type = 'error';
                            break;
                        }
                        $stmt->close();

                        // Update stock
                        $stmt = $conn->prepare("UPDATE items SET stock = stock + ? WHERE id = ?");
                        $stmt->bind_param("di", $quantity, $item_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                if ($modal_type !== 'error') {
                    $net_total = $gross_total - $hamali - $freight - $uchal; // Net amount after deductions
                    $stmt = $conn->prepare("UPDATE purchases SET total_amount = ? WHERE id = ?");
                    $stmt->bind_param("di", $net_total, $purchase_id);
                    if ($stmt->execute()) {
                        $modal_message = "Purchase updated successfully!";
                        $modal_type = 'success';
                    } else {
                        error_log("Purchase Total Update Failed: " . $conn->error);
                        $modal_message = "Failed to update purchase total.";
                        $modal_type = 'error';
                    }
                    $stmt->close();

                    // Handle paid toggle for update
                    $stmt_check = $conn->prepare("SELECT id FROM supplier_payments WHERE purchase_id = ?");
                    $stmt_check->bind_param("i", $purchase_id);
                    $stmt_check->execute();
                    $has_payment = $stmt_check->get_result()->num_rows > 0;
                    $stmt_check->close();

                    $payment_date = $purchase_date;
                    $amount = $net_total;
                    $description = "Automatic payment for purchase invoice #$invoice_number";

                    if (isset($_POST['paid'])) {
                        if ($has_payment) {
                            // Update existing payment
                            $stmt_payment = $conn->prepare("UPDATE supplier_payments SET supplier_id = ?, payment_date = ?, amount = ?, description = ? WHERE purchase_id = ?");
                            $stmt_payment->bind_param("isdsi", $supplier_id, $payment_date, $amount, $description, $purchase_id);
                            if (!$stmt_payment->execute()) {
                                error_log("Payment Update Failed: " . $conn->error);
                                $modal_message .= " Failed to update automatic payment.";
                                $modal_type = 'error';
                            }
                            $stmt_payment->close();
                        } else {
                            // Insert new payment
                            $stmt_payment = $conn->prepare("INSERT INTO supplier_payments (supplier_id, purchase_id, payment_date, amount, description) 
                                                            VALUES (?, ?, ?, ?, ?)");
                            $stmt_payment->bind_param("iisds", $supplier_id, $purchase_id, $payment_date, $amount, $description);
                            if (!$stmt_payment->execute()) {
                                error_log("Payment Insert Failed: " . $conn->error);
                                $modal_message .= " Failed to add automatic payment.";
                                $modal_type = 'error';
                            }
                            $stmt_payment->close();
                        }
                    } else {
                        if ($has_payment) {
                            // Delete existing payment if toggle is off
                            $stmt_payment = $conn->prepare("DELETE FROM supplier_payments WHERE purchase_id = ?");
                            $stmt_payment->bind_param("i", $purchase_id);
                            if (!$stmt_payment->execute()) {
                                error_log("Payment Delete Failed: " . $conn->error);
                                $modal_message .= " Failed to remove automatic payment.";
                                $modal_type = 'error';
                            }
                            $stmt_payment->close();
                        }
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        #cancel{
            margin-bottom: -2%;
            width: 10%;
            margin-left: 88%;}

        #toggleForm{
            margin-left: 65%;
            margin-bottom: -6%;
        }    

        /* Enhanced Print Styles - Isolates form content for printing */
        @media print {
            * { visibility: hidden; }
            #purchaseFormContainer, #purchaseFormContainer * { visibility: visible; }
            #purchaseFormContainer { position: absolute; left: 0; top: 0; width: 100%; height: 100%; margin: 0; padding: 0; background: white; }
            .print-container { box-shadow: none !important; border: none !important; margin: 0 !important; padding: 25px 40px !important; border-radius: 0 !important; max-width: none !important; page-break-inside: avoid; }
            body { background: white !important; padding: 0 !important; margin: 0 !important; }
            .container, .card, .pagination, button, .btn, nav, h2, h4, #toggleForm, #cancel { display: none !important; visibility: hidden !important; }
        }

        /* Custom Toggle Switch Styles */
        .custom-toggle {
            position: relative;
            display: inline-block;
            width: 80px;
            height: 34px;
        }

        .custom-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 40px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 20px;
        }

        input:checked + .slider {
            background-color: #4CAF50; /* Green */
        }

        input:not(:checked) + .slider {
            background-color: #f44336; /* Red */
        }

        input:checked + .slider:before {
            transform: translateX(36px);
        }

        /* Icons */
        .slider:after {
            content: '\2716'; /* X */
            color: white;
            position: absolute;
            right: 12px;
            top: 5px;
            font-size: 20px;
            transition: .4s;
        }

        input:checked + .slider:after {
            content: '\2714'; /* Check */
            left: 12px;
            right: auto;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <!-- <h2 class="fw-bold">Purchase Management</h2> -->
        <!-- <button class="btn btn-primary" id="toggleForm">➕ New Purchase Entry</button> -->
        
    </div>
    
    <!-- Purchase Entry Form -->
    <div id="purchaseFormContainer" class="card p-4 shadow mb-4" style="display:<?= ($action === 'edit') ? 'block' : 'none' ?>;">
        
        <form method="POST" id="purchaseForm" action="purchase_entry.php?action=<?= ($action === 'edit') ? 'update&id=' . intval($_GET['id']) : 'add' ?>">
            <div class="row mb-4 align-items-end">
    <div class="col-md-3">
        <label class="form-label">Supplier</label>
        <div class="input-group">
           
            <select name="supplier_id" id="supplierSelect" class="form-select" required>
                <option value="">Select Supplier</option>
                <?php $suppliers->data_seek(0); while ($row = $suppliers->fetch_assoc()) { ?>
                    <option value="<?= $row['id'] ?>" <?= ($action === 'edit' && $row['id'] == $purchase['supplier_id']) ? 'selected' : '' ?>><?= htmlspecialchars($row['name']) ?> | <?= $row['balance'] ?? 0 ?></option>
                <?php } ?>
            </select>
            <button type="button" class="btn btn-outline-success" onclick="window.location.href='accounts.php?action=add';">
                <i class="bi bi-plus"></i>+
            </button>
        </div>
    </div>
    <div class="col-md-3">
        <label class="form-label">Invoice Number</label>
        <input type="number" name="invoice_number" class="form-control" value="<?= ($action === 'edit') ? $purchase['invoice_number'] : $next_invoice ?>" <?= ($action === 'edit') ? '' : 'readonly' ?>>
    </div>
    <div class="col-md-3">
        <label class="form-label">Purchase Date</label>
        <input type="date" name="purchase_date" class="form-control" value="<?= ($action === 'edit') ? $purchase['purchase_date'] : date('Y-m-d') ?>" required>
    </div>
    <div class="col-md-3 d-flex align-items-center">
        <div class="form-check form-switch">
            <label class="custom-toggle">
                <input type="checkbox" name="paid" id="paidToggle" <?= $paid ? 'checked' : '' ?>>
                <span class="slider"></span>
            </label>
            <label class="form-check-label ms-2" for="paidToggle">Paid||Unpaid</label>
        </div>
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
                    <?php
                    $has_rows = false;
                    if ($action === 'edit' && !empty($details)) {
                        $has_rows = true;
                        foreach ($details as $detail) {
                    ?>
                    <tr>
                        <td>
                            <select name="item_id[]" class="form-select item-select" required>
                                <?php $items->data_seek(0); while ($row = $items->fetch_assoc()) { ?>
                                    <option value="<?= $row['id'] ?>" data-rate="<?= $row['purchase_rate'] ?>" <?= ($row['id'] == $detail['item_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($row['name']) ?>
                                    </option>
                                    <?php } ?>
                            </select>
                        </td>
                        <td><input type="number" name="quantity[]" class="form-control qty" min="0" required value="<?= $detail['quantity'] ?>"></td>
                        <td><input type="number" step="0.01" name="weight[]" class="form-control weight" required value="<?= $detail['weight'] ?>"></td>
                        <td><input type="number" step="0.01" name="rate[]" class="form-control rate" required value="<?= $detail['rate'] ?>"></td>
                        <td><input type="number" step="0.01" class="form-control total" readonly value="<?= $detail['total'] ?>"></td>
                        <td><button type="button" class="btn btn-danger btn-sm remove-row">❌</button></td>
                    </tr>
                    <?php
                        }
                    }
                    if (!$has_rows) {
                        ?>
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
                    <?php } ?>
                </tbody>
            </table>
            <button type="button" id="addRow" class="btn btn-outline-secondary mb-3">➕ Add Product</button>

            <!-- Expenses -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Hamali</label>
                    <input type="number" step="0.01" name="hamali" id="hamali" class="form-control" value="<?= ($action === 'edit') ? $purchase['hamali'] : '0' ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Freight</label>
                    <input type="number" step="0.01" name="freight" id="freight" class="form-control" value="<?= ($action === 'edit') ? $purchase['freight'] : '0' ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Uchal</label>
                    <input type="number" step="0.01" name="uchal" id="uchal" class="form-control" value="<?= ($action === 'edit') ? ($purchase['uchal'] ?? '0') : '0' ?>">
                </div>
            </div>

            <!-- Grand Total and Net Total -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Grand Total</label>
                    <input type="number" step="0.01" id="grandTotal" class="form-control fw-bold" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Net Total</label>
                    <input type="number" step="0.01" id="netTotal" class="form-control fw-bold" readonly>
                </div>
            </div>


            <button type="submit" class="btn btn-success"><?= ($action === 'edit') ? 'Update' : '💾 Save' ?> Purchase</button>
            <button type="button" class="btn btn-secondary" id="closeForm">Cancel</button>
            <div class="form-check mt-3">
                <input type="checkbox" class="form-check-input" id="printCheckbox">
                <label class="form-check-label" for="printCheckbox">Print Bill</label>
            </div>
        </form>
    </div>

    <!-- Purchases List -->
    <div class="card shadow p-3">
        <!-- <a href="index.php" id="cancel" class="btn btn-danger">❌</a> -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <!-- <h2 class="fw-bold">Purchase Management</h2> -->
        <button class="btn btn-primary" id="toggleForm">➕ New Purchase Entry</button>
    </div>
        <a href="index.php" id="cancel" class="btn btn-danger"> ❌close </a>
        <h4 class="fw-bold mb-3">📋 Purchase List</h4>
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Supplier</th>
                    <th>Invoice</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Edit</th>
                    <th>Print</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $conn->prepare("SELECT p.*, a.name AS supplier 
                                        FROM purchases p 
                                        JOIN accounts a ON p.supplier_id = a.id 
                                        ORDER BY p.id DESC 
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
                        <td><a href='purchase_entry.php?action=edit&id={$row['id']}&page={$page}' class='btn btn-sm btn-outline-secondary'>✏ Edit</a></td>
                        <td><a href='Print_purchase.php?id={$row['id']}' target='_blank' class='btn btn-sm btn-outline-primary'>🖨 Print</a></td>
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

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Success</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="successMessage"></p>
            </div>
            <div class="modal-footer">
                <a href="purchase_entry.php?page=<?= $page ?>" class="btn btn-primary">Back to Purchase List</a>
            </div>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Error</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="errorMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Supplier Modal -->
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
function filterSuppliers() {
    var input = document.getElementById("supplierSearch");
    var filter = input.value.toLowerCase();
    var select = document.getElementById("supplierSelect");
    var options = select.getElementsByTagName("option");
    for (var i = 0; i < options.length; i++) {
        var txtValue = options[i].textContent || options[i].innerText;
        if (txtValue.toLowerCase().indexOf(filter) > -1) {
            options[i].style.display = "";
        } else {
            options[i].style.display = "none";
        }
    }
}

$(document).ready(function() {
    // Show modals if message is set
    <?php if ($modal_message && $modal_type === 'success') { ?>
        $('#successMessage').text('<?= addslashes($modal_message) ?>');
        $('#successModal').modal('show');
    <?php } elseif ($modal_message && $modal_type === 'error') { ?>
        $('#errorMessage').text('<?= addslashes($modal_message) ?>');
        $('#errorModal').modal('show');
    <?php } ?>

    $("#toggleForm").click(() => $("#purchaseFormContainer").slideDown());
    $("#closeForm").click(() => $("#purchaseFormContainer").slideUp());

    $(document).on('change', '.item-select', function() {
        let rate = $(this).find('option:selected').data('rate');
        $(this).closest('tr').find('.rate').val(rate);
        calculateTotals();
    });

    $(document).on('input', '.weight, .rate, #hamali, #freight, #uchal', calculateTotals);

    $('#addRow').click(function() {
        let row = $('#productTable tbody tr:first').clone();
        row.find('input').val('');
        $('#productTable tbody').append(row);
        calculateTotals();
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
        let uchal = parseFloat($('#uchal').val()) || 0;
        let netTotal = grandTotal - hamali - freight - uchal;
        $('#grandTotal').val(grandTotal.toFixed(2));
        $('#netTotal').val(netTotal.toFixed(2));
    }

    $('#addSupplierBtn').click(() => $('#addSupplierModal').modal('show'));
    $('#addSupplierForm').submit(function(e) {
        e.preventDefault();
        $.post('save_supplier.php', $(this).serialize(), function(newOption) {
            $('#supplierSelect').append(newOption);
            $('#addSupplierModal').modal('hide');
            $('#addSupplierForm')[0].reset();
        });
    });

    $('#purchaseForm').submit(function(e) {
        if ($('#printCheckbox').is(':checked')) {
            e.preventDefault();
            // Temporarily adjust layout for print
            $('#purchaseFormContainer').css({ 'position': 'absolute', 'left': 0, 'top': 0, 'width': '100%', 'height': '100%', 'margin': 0, 'padding': 0 });
            $('.container, .card, .pagination, button, .btn, nav, h2, h4, #toggleForm, #cancel').css('display', 'none');
            
            // Trigger print
            setTimeout(function() {
                window.print();
                // Reset layout after print
                $('#purchaseFormContainer').css({ 'position': '', 'left': '', 'top': '', 'width': '', 'height': '', 'margin': '', 'padding': '' });
                $('.container, .card, .pagination, button, .btn, nav, h2, h4, #toggleForm, #cancel').css('display', '');
                $(this).unbind('submit').submit(); // Proceed with form submission
            }, 100);
        }
    });

    calculateTotals();
});

</script>

<?php include 'footer.php'; ?>
<?php ob_end_flush(); // Flush output buffer ?>
</body>
</html>