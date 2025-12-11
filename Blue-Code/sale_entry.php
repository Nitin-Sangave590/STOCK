<?php
ob_start();
// sale_entry.php - Sale Entry
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// ✅ Verify database connection
if (!$conn) {
    die("Database connection failed.");
}

// ✅ Fetch customers safely
// $stmt = $conn->prepare("SELECT id, name FROM accounts WHERE type = ? ORDER BY name ASC");
// if ($stmt === false) {
//     die("Failed to prepare statement for customers: " . $conn->error);
// }
// $type = 'customer';
// $stmt->bind_param("s", $type);
// $stmt->execute();
// $customers = $stmt->get_result();
// $stmt->close();

// Fetch suppliers safely with balance
$stmt = $conn->prepare("SELECT a.id, a.name, 
  COALESCE( (SELECT SUM(p.total_amount) FROM sales p WHERE p.customer_id = a.id), 0) - 
  COALESCE( (SELECT SUM(c.amount) FROM customer_receipts c WHERE c.customer_id = a.id), 0) AS balance 
FROM accounts a WHERE a.type = ? ORDER BY a.name DESC");
$type = 'customer';
$stmt->bind_param("s", $type);
$stmt->execute();
$customers = $stmt->get_result();
$stmt->close();











// ✅ Fetch items safely with sale_rate and stock
$stmt = $conn->prepare("SELECT id, name, sale_rate, stock FROM items ORDER BY name ASC");
if ($stmt === false) {
    die("Failed to prepare statement for items: " . $conn->error);
}
$stmt->execute();
$items = $stmt->get_result();
$stmt->close();


// Get next invoice number (auto-increment manually)
$result = $conn->query("SELECT MAX(invoice_number) AS last_invoice FROM sales");
$last_invoice = $result->fetch_assoc()['last_invoice'] ?? 0;
$next_invoice = $last_invoice + 1;


// Handle delete action
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Fetch and restore stock from details
    $stmt = $conn->prepare("SELECT * FROM sale_details WHERE sale_id = ?");
    if ($stmt === false) {
        die("Failed to prepare statement for sale details: " . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($detail = $result->fetch_assoc()) {
        $item_id = $detail['item_id'];
        $qty = $detail['quantity'];
        $ustmt = $conn->prepare("UPDATE items SET stock = stock + ? WHERE id = ?");
        if ($ustmt === false) {
            die("Failed to prepare statement for stock update: " . $conn->error);
        }
        $ustmt->bind_param("di", $qty, $item_id);
        $ustmt->execute();
        $ustmt->close();
    }
    $stmt->close();

    // Delete details
    $dstmt = $conn->prepare("DELETE FROM sale_details WHERE sale_id = ?");
    if ($dstmt === false) {
        die("Failed to prepare statement for delete details: " . $conn->error);
    }
    $dstmt->bind_param("i", $id);
    $dstmt->execute();
    $dstmt->close();

    // Delete sale
    $dstmt = $conn->prepare("DELETE FROM sales WHERE id = ?");
    if ($dstmt === false) {
        die("Failed to prepare statement for delete sale: " . $conn->error);
    }
    $dstmt->bind_param("i", $id);
    $dstmt->execute();
    $dstmt->close();

    header("Location: sale_entry.php?success=delete&page=$page");
    exit;
}

// Fetch sale data for edit
$sale = null;
$sale_details = [];
if ($action === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM sales WHERE id = ?");
    if ($stmt === false) {
        die("Failed to prepare statement for sale fetch: " . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $sale = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($sale) {
        $stmt = $conn->prepare("SELECT sd.*, i.name, i.sale_rate, i.stock FROM sale_details sd JOIN items i ON sd.item_id = i.id WHERE sd.sale_id = ?");
        if ($stmt === false) {
            die("Failed to prepare statement for sale details fetch: " . $conn->error);
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $sale_details[] = $row;
        }
        $stmt->close();
    }
}

// ✅ Handle form submission for add
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = intval($_POST['customer_id']);
    $invoice_number = trim($_POST['invoice_number']);
    $date = $_POST['date'];
    $hamali = floatval($_POST['hamali'] ?? 0);
    $freight = floatval($_POST['freight'] ?? 0);
    $total_amount = 0;

    // ✅ Basic validation
    if (!$customer_id || !$invoice_number || !$date) {
        die("Error: Customer, Invoice Number, and Sale Date are required.");
    }

    // ✅ Insert sale entry
    $stmt = $conn->prepare("INSERT INTO sales (customer_id, invoice_number, `date`, hamali, freight, total_amount) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        error_log("Prepare failed for sale insert: " . $conn->error);
        die("Failed to prepare statement for sale insertion. Error: " . $conn->error);
    }
    $stmt->bind_param("issddd", $customer_id, $invoice_number, $date, $hamali, $freight, $total_amount);
    if (!$stmt->execute()) {
        error_log("Sale Insert Failed: " . $conn->error);
        die("Failed to insert sale. Check logs.");
    }
    $sale_id = $conn->insert_id;
    $stmt->close();

    // ✅ Insert sale details
    if (!empty($_POST['item_id'])) {
        foreach ($_POST['item_id'] as $index => $item_id) {
            $item_id = intval($item_id);
            $quantity = floatval($_POST['quantity'][$index] ?? 0);
            $weight = floatval($_POST['weight'][$index] ?? 0);
            $rate = floatval($_POST['rate'][$index] ?? 0);
            $total = $weight * $rate;
            $total_amount += $total;

            // Validate stock
            $stmt = $conn->prepare("SELECT stock FROM items WHERE id = ?");
            if ($stmt === false) {
                die("Failed to prepare statement for stock check: " . $conn->error);
            }
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $stock_result = $stmt->get_result();
            $stock = $stock_result->fetch_assoc()['stock'] ?? 0;
            $stmt->close();

            if ($quantity > $stock) {
                die("Error: Insufficient stock for item ID $item_id. Available: $stock, Requested: $quantity.");
            }

            // Insert details
            $stmt = $conn->prepare("INSERT INTO sale_details (sale_id, item_id, quantity, weight, rate, total) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt === false) {
                die("Failed to prepare statement for sale details: " . $conn->error);
            }
            $stmt->bind_param("iidddd", $sale_id, $item_id, $quantity, $weight, $rate, $total);
            if (!$stmt->execute()) {
                error_log("Sale Detail Insert Failed: " . $conn->error);
                die("Failed to insert sale details.");
            }
            $stmt->close();

            // Update stock
            $stmt = $conn->prepare("UPDATE items SET stock = stock - ? WHERE id = ?");
            if ($stmt === false) {
                die("Failed to prepare statement for stock update: " . $conn->error);
            }
            $stmt->bind_param("di", $quantity, $item_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // ✅ Update net total
    $net_total = $total_amount + $hamali + $freight;
    $stmt = $conn->prepare("UPDATE sales SET total_amount = ? WHERE id = ?");
    if ($stmt === false) {
        die("Failed to prepare statement for total update: " . $conn->error);
    }
    $stmt->bind_param("di", $net_total, $sale_id);
    $stmt->execute();
    $stmt->close();

    header("Location: sale_entry.php?success=add&page=$page");
    exit;
}

// ✅ Handle form submission for update
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sale_id'])) {
    $sale_id = intval($_POST['sale_id']);
    $customer_id = intval($_POST['customer_id']);
    $invoice_number = trim($_POST['invoice_number']);
    $date = $_POST['date'];
    $hamali = floatval($_POST['hamali'] ?? 0);
    $freight = floatval($_POST['freight'] ?? 0);
    $total_amount = 0;

    // ✅ Basic validation
    if (!$customer_id || !$invoice_number || !$date) {
        die("Error: Customer, Invoice Number, and Sale Date are required.");
    }

    // Restore old stock
    $stmt = $conn->prepare("SELECT * FROM sale_details WHERE sale_id = ?");
    if ($stmt === false) {
        die("Failed to prepare statement for old sale details: " . $conn->error);
    }
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($detail = $result->fetch_assoc()) {
        $item_id = $detail['item_id'];
        $qty = $detail['quantity'];
        $ustmt = $conn->prepare("UPDATE items SET stock = stock + ? WHERE id = ?");
        if ($ustmt === false) {
            die("Failed to prepare statement for stock restore: " . $conn->error);
        }
        $ustmt->bind_param("di", $qty, $item_id);
        $ustmt->execute();
        $ustmt->close();
    }
    $stmt->close();

    // Delete old details
    $dstmt = $conn->prepare("DELETE FROM sale_details WHERE sale_id = ?");
    if ($dstmt === false) {
        die("Failed to prepare statement for delete old details: " . $conn->error);
    }
    $dstmt->bind_param("i", $sale_id);
    $dstmt->execute();
    $dstmt->close();

    // Update sale entry
    $stmt = $conn->prepare("UPDATE sales SET customer_id = ?, invoice_number = ?, `date` = ?, hamali = ?, freight = ?, total_amount = ? WHERE id = ?");
    if ($stmt === false) {
        error_log("Prepare failed for sale update: " . $conn->error);
        die("Failed to prepare statement for sale update. Error: " . $conn->error);
    }
    $stmt->bind_param("issdddi", $customer_id, $invoice_number, $date, $hamali, $freight, $total_amount, $sale_id);
    if (!$stmt->execute()) {
        error_log("Sale Update Failed: " . $conn->error);
        die("Failed to update sale. Check logs.");
    }
    $stmt->close();

    // ✅ Insert new sale details (same as add)
    if (!empty($_POST['item_id'])) {
        foreach ($_POST['item_id'] as $index => $item_id) {
            $item_id = intval($item_id);
            $quantity = floatval($_POST['quantity'][$index] ?? 0);
            $weight = floatval($_POST['weight'][$index] ?? 0);
            $rate = floatval($_POST['rate'][$index] ?? 0);
            $total = $weight * $rate;
            $total_amount += $total;

            // Validate stock
            $stmt = $conn->prepare("SELECT stock FROM items WHERE id = ?");
            if ($stmt === false) {
                die("Failed to prepare statement for stock check: " . $conn->error);
            }
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $stock_result = $stmt->get_result();
            $stock = $stock_result->fetch_assoc()['stock'] ?? 0;
            $stmt->close();

            if ($quantity > $stock) {
                die("Error: Insufficient stock for item ID $item_id. Available: $stock, Requested: $quantity.");
            }

            // Insert details
            $stmt = $conn->prepare("INSERT INTO sale_details (sale_id, item_id, quantity, weight, rate, total) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt === false) {
                die("Failed to prepare statement for sale details: " . $conn->error);
            }
            $stmt->bind_param("iidddd", $sale_id, $item_id, $quantity, $weight, $rate, $total);
            if (!$stmt->execute()) {
                error_log("Sale Detail Insert Failed: " . $conn->error);
                die("Failed to insert sale details.");
            }
            $stmt->close();

            // Update stock
            $stmt = $conn->prepare("UPDATE items SET stock = stock - ? WHERE id = ?");
            if ($stmt === false) {
                die("Failed to prepare statement for stock update: " . $conn->error);
            }
            $stmt->bind_param("di", $quantity, $item_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // ✅ Update net total
    $net_total = $total_amount + $hamali + $freight;
    $stmt = $conn->prepare("UPDATE sales SET total_amount = ? WHERE id = ?");
    if ($stmt === false) {
        die("Failed to prepare statement for total update: " . $conn->error);
    }
    $stmt->bind_param("di", $net_total, $sale_id);
    $stmt->execute();
    $stmt->close();

    header("Location: sale_entry.php?success=update&page=$page");
    exit;
}
?>
<style>
    #cancel {
        margin-left: 90%;
        margin-bottom: -2%
    }

    #toggleForm {
        margin-left: 70% !important;
        margin-bottom: -4%;
        width: 18%
    }

    .pagination .page-item.active .page-link {
        background-color: #007bff;
        border-color: #007bff;
        color: white;
    }

    .pagination .page-link {
        color: #007bff;
    }
</style>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold">Sale Management</h2>

    </div>

    <!-- ✅ Sale Entry Form -->
    <div id="saleFormContainer" class="card p-4 shadow mb-4" style="display: <?= ($action === 'edit') ? 'block' : 'none'; ?>;">
        <form method="POST" id="saleForm" action="sale_entry.php?action=<?= ($action === 'edit' && $sale) ? 'update' : 'add' ?>">
            <?php if ($sale) { ?>
                <input type="hidden" name="sale_id" value="<?= $sale['id'] ?>">
            <?php } ?>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">Select Customer</option>
                        <?php $customers->data_seek(0); while ($row = $customers->fetch_assoc()) { ?>
                            <option value="<?= $row['id'] ?>" <?= ($sale && $sale['customer_id'] == $row['id']) ? 'selected' : '' ?>><?= htmlspecialchars($row['name']) ?> | <?= $row['balance'] ?? 0 ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Invoice Number</label>
                    <input type="text" name="invoice_number" class="form-control" required value="<?= $sale ? htmlspecialchars($sale['invoice_number']) : '' ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sale Date</label>
                    <input type="date" name="date" class="form-control" value="<?= $sale ? $sale['date'] : date('Y-m-d') ?>" required>
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
                    if ($action === 'edit' && !empty($sale_details)) {
                        foreach ($sale_details as $detail) {
                    ?>
                            <tr>
                                <td>
                                    <select name="item_id[]" class="form-select item-select" required>
                                        <option value="">Select Item</option>
                                        <?php $items->data_seek(0); while ($row = $items->fetch_assoc()) { ?>
                                            <option value="<?= $row['id'] ?>" data-rate="<?= $row['sale_rate'] ?>" data-stock="<?= $row['stock'] ?>" <?= ($detail['item_id'] == $row['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($row['name']) ?> (Stock: <?= $row['stock'] ?>)
                                            </option>
                                        <?php } ?>
                                    </select>
                                </td>
                                <td><input type="number" name="quantity[]" class="form-control qty" min="0" step="0.01" required value="<?= $detail['quantity'] ?>"></td>
                                <td><input type="number" step="0.01" name="weight[]" class="form-control weight" required value="<?= $detail['weight'] ?>"></td>
                                <td><input type="number" step="0.01" name="rate[]" class="form-control rate" required value="<?= $detail['rate'] ?>"></td>
                                <td><input type="number" step="0.01" class="form-control total" readonly value="<?= $detail['total'] ?>"></td>
                                <td><button type="button" class="btn btn-danger btn-sm remove-row">❌</button></td>
                            </tr>
                    <?php
                        }
                    } else {
                    ?>
                        <tr>
                            <td>
                                <select name="item_id[]" class="form-select item-select" required>
                                    <option value="">Select Item</option>
                                    <?php $items->data_seek(0); while ($row = $items->fetch_assoc()) { ?>
                                        <option value="<?= $row['id'] ?>" data-rate="<?= $row['sale_rate'] ?>" data-stock="<?= $row['stock'] ?>">
                                            <?= htmlspecialchars($row['name']) ?> (Stock: <?= $row['stock'] ?>)
                                        </option>
                                    <?php } ?>
                                </select>
                            </td>
                            <td><input type="number" name="quantity[]" class="form-control qty" min="0" step="0.01" required></td>
                            <td><input type="number" step="0.01" name="weight[]" class="form-control weight" required></td>
                            <td><input type="number" step="0.01" name="rate[]" class="form-control rate" required></td>
                            <td><input type="number" step="0.01" class="form-control total" readonly></td>
                            <td><button type="button" class="btn btn-danger btn-sm remove-row">❌</button></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <button type="button" id="addRow" class="btn btn-outline-secondary mb-3">➕ Add Product</button>

            <!-- ✅ Expenses -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Hamali</label>
                    <input type="number" step="0.01" name="hamali" id="hamali" class="form-control" value="<?= $sale ? $sale['hamali'] : '0' ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Freight</label>
                    <input type="number" step="0.01" name="freight" id="freight" class="form-control" value="<?= $sale ? $sale['freight'] : '0' ?>">
                </div>
            </div>

            <!-- ✅ Grand Total -->
            <div class="mb-3">
                <label class="form-label">Net Total</label>
                <input type="number" step="0.01" id="grandTotal" class="form-control fw-bold" readonly value="<?= $sale ? $sale['total_amount'] : '' ?>">
            </div>

            <button type="submit" class="btn btn-success">💾 Save Sale</button>
            <button type="button" class="btn btn-secondary" id="closeForm">Cancel</button>
            <div class="form-check mt-3">
                <input type="checkbox" class="form-check-input" id="printCheckbox">
                <label class="form-check-label" for="printCheckbox">Print Bill</label>
            </div>
        </form>
    </div>

    <!-- ✅ Sales List -->
    <div class="card shadow p-3">
        <button class="btn btn-primary" id="toggleForm">➕ New Sale Entry</button>
        <a href="index.php" id="cancel" class="btn btn-danger"> ❌close </a>
        <h4 class="fw-bold mb-3">📋 Sale List</h4>
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Invoice</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Print</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $conn->prepare("SELECT s.*, a.name AS customer FROM sales s JOIN accounts a ON s.customer_id = a.id ORDER BY s.id DESC LIMIT ? OFFSET ?");
                if ($stmt === false) {
                    die("Failed to prepare statement for sales list: " . $conn->error);
                }
                $stmt->bind_param("ii", $limit, $offset);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>
                        <td>{$row['id']}</td>
                        <td>" . htmlspecialchars($row['customer']) . "</td>
                        <td>" . htmlspecialchars($row['invoice_number']) . "</td>
                        <td>" . htmlspecialchars($row['date']) . "</td>
                        <td><strong>{$row['total_amount']}</strong></td>
                        <td><a href='print_sale.php?id={$row['id']}' target='_blank' class='btn btn-sm btn-outline-primary'>🖨 Print</a></td>
                        <td>
                            <a href='sale_entry.php?action=edit&id={$row['id']}&page=$page' class='btn btn-sm btn-outline-secondary'>✏️ Edit</a>
                            <a href='sale_entry.php?action=delete&id={$row['id']}&page=$page' onclick='return confirm(\"Are you sure you want to delete this sale?\")' class='btn btn-sm btn-outline-danger'>🗑️ Delete</a>
                        </td>
                    </tr>";
                }
                $stmt->close();

                // Pagination
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM sales");
                if ($stmt === false) {
                    die("Failed to prepare statement for pagination: " . $conn->error);
                }
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

    <!-- ✅ Item Stock Summary -->
    <div class="card shadow p-3 mt-4">
        <h4 class="fw-bold mb-3">📊 Item Stock Summary</h4>
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Item</th>
                    <th>Total Purchased Qty</th>
                    <th>Total Purchased Weight</th>
                    <th>Total Sold Qty</th>
                    <th>Total Sold Weight</th>
                    <th>Available Qty</th>
                    <th>Available Weight</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $conn->prepare("
                    SELECT 
                        i.id, 
                        i.name, 
                        COALESCE((SELECT SUM(quantity) FROM purchase_details WHERE item_id = i.id), 0) AS total_purch_qty,
                        COALESCE((SELECT SUM(weight) FROM purchase_details WHERE item_id = i.id), 0) AS total_purch_weight,
                        COALESCE((SELECT SUM(quantity) FROM sale_details WHERE item_id = i.id), 0) AS total_sold_qty,
                        COALESCE((SELECT SUM(weight) FROM sale_details WHERE item_id = i.id), 0) AS total_sold_weight,
                        i.stock AS available_qty
                    FROM items i
                    ORDER BY i.name ASC
                ");
                if ($stmt === false) {
                    die("Failed to prepare statement for stock summary: " . $conn->error);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $available_weight = $row['total_purch_weight'] - $row['total_sold_weight'];
                    echo "<tr>
                        <td>" . htmlspecialchars($row['name']) . "</td>
                        <td>" . number_format($row['total_purch_qty'], 2) . "</td>
                        <td>" . number_format($row['total_purch_weight'], 2) . "</td>
                        <td>" . number_format($row['total_sold_qty'], 2) . "</td>
                        <td>" . number_format($row['total_sold_weight'], 2) . "</td>
                        <td><strong>" . number_format($row['available_qty'], 2) . "</strong></td>
                        <td><strong>" . number_format($available_weight, 2) . "</strong></td>
                    </tr>";
                }
                $stmt->close();
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ✅ Print Preview -->
<div class="print-area" id="printArea" style="display:none;">
    <h2>Sale Bill</h2>
    <p><strong>Customer:</strong> <span id="printCustomer"></span></p>
    <p><strong>Invoice Number:</strong> <span id="printInvoice"></span></p>
    <p><strong>Date:</strong> <span id="printDate"></span></p>
    <p><strong>Hamali:</strong> <span id="printHamali"></span></p>
    <p><strong>Freight:</strong> <span id="printFreight"></span></p>
    <table border="1" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Weight</th>
                <th>Rate</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody id="printTableBody"></tbody>
    </table>
    <p><strong>Net Total:</strong> <span id="printGrandTotal"></span></p>
</div>

<script>
    $(document).ready(function() {
        $("#toggleForm").click(() => $("#saleFormContainer").slideDown());
        $("#closeForm").click(() => $("#saleFormContainer").slideUp());

        $(document).on('change', '.item-select', function() {
            let rate = $(this).find('option:selected').data('rate') || 0;
            let tr = $(this).closest('tr');
            tr.find('.rate').val(rate);
            if (!tr.find('.qty').val()) tr.find('.qty').val(1); // Auto-fetch default qty=1
            if (!tr.find('.weight').val()) tr.find('.weight').val(1); // Auto-fetch default weight=1 (adjust as needed)
            calculateTotals();
        });

        $(document).on('input', '.qty', function() {
            let qty = parseFloat($(this).val()) || 0;
            let stock = parseFloat($(this).closest('tr').find('.item-select option:selected').data('stock')) || 0;
            if (qty > stock) {
                alert("Insufficient stock! Available: " + stock);
                $(this).val(stock);
            }
            calculateTotals();
        });

        $(document).on('input', '.weight, .rate, #hamali, #freight', calculateTotals);

        $('#addRow').click(function() {
            let row = $('#productTable tbody tr:first').clone();
            row.find('input').val('');
            row.find('.total').val('');
            row.find('.item-select').val('');
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

        // Initial calculation for edit mode
        calculateTotals();

        $('#saleForm').submit(function(e) {
            if ($('#printCheckbox').is(':checked')) {
                e.preventDefault();
                // Fill Print Area
                $('#printCustomer').text($('select[name="customer_id"] option:selected').text());
                $('#printInvoice').text($('input[name="invoice_number"]').val());
                $('#printDate').text($('input[name="date"]').val());
                $('#printHamali').text($('#hamali').val() || '0.00');
                $('#printFreight').text($('#freight').val() || '0.00');
                $('#printGrandTotal').text($('#grandTotal').val());

                let tableBody = '';
                $('#productTable tbody tr').each(function() {
                    tableBody += '<tr>' +
                        '<td>' + $(this).find('.item-select option:selected').text() + '</td>' +
                        '<td>' + ($(this).find('.qty').val() || '0.00') + '</td>' +
                        '<td>' + ($(this).find('.weight').val() || '0.00') + '</td>' +
                        '<td>' + ($(this).find('.rate').val() || '0.00') + '</td>' +
                        '<td>' + ($(this).find('.total').val() || '0.00') + '</td>' +
                    '</tr>';
                });
                $('#printTableBody').html(tableBody);

                $('#printArea').show();
                window.print();
                $('#printArea').hide();
                $(this).unbind('submit').submit();
            }
        });
    });
</script>

<?php include 'footer.php'; ?>