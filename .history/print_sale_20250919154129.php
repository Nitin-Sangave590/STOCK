<?php
// sale_bill_management.php - Sale Entry and Bill Printing with A5 Support
ob_start();
include 'db.php';
include 'header.php';

// Initialize message variables for modals
$modal_message = '';
$modal_type = ''; // 'success' or 'error'
$action = $_GET['action'] ?? '';

// ✅ Verify database connection
if (!$conn) {
    die("Database connection failed.");
}

// ✅ Fetch customers safely
$stmt = $conn->prepare("SELECT id, name FROM accounts WHERE type = ? ORDER BY name ASC");
if ($stmt === false) {
    die("Failed to prepare statement for customers: " . $conn->error);
}
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

// ✅ Handle form submission for sale entry
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = intval($_POST['customer_id']);
    $invoice_number = trim($_POST['invoice_number']);
    $date = $_POST['date'];
    $hamali = floatval($_POST['hamali'] ?? 0);
    $freight = floatval($_POST['freight'] ?? 0);
    $total_amount = 0;

    // Basic validation
    if (!$customer_id || !$invoice_number || !$date) {
        $modal_message = "Customer, Invoice Number, and Sale Date are required.";
        $modal_type = 'error';
    } else {
        // Insert sale entry
        $stmt = $conn->prepare("INSERT INTO sales (customer_id, invoice_number, sale_date, hamali, freight, total_amount) VALUES (?, ?, ?, ?, ?, ?)");
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

        // Insert sale details
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
                $stock = $stock_result->fetch_assoc()['stock'];
                $stmt->close();

                if ($quantity > $stock) {
                    $modal_message = "Insufficient stock for item ID $item_id. Available: $stock, Requested: $quantity.";
                    $modal_type = 'error';
                    break;
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

        // Update net total
        $net_total = $total_amount + $hamali + $freight;
        $stmt = $conn->prepare("UPDATE sales SET total_amount = ? WHERE id = ?");
        if ($stmt === false) {
            die("Failed to prepare statement for total update: " . $conn->error);
        }
        $stmt->bind_param("di", $net_total, $sale_id);
        $stmt->execute();
        $stmt->close();

        if ($modal_type !== 'error') {
            $modal_message = "Sale added successfully!";
            $modal_type = 'success';
        }
    }
}

// ✅ Build sales list query with date filter
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$columns = ["s.id", "s.invoice_number", "s.sale_date", "c.name AS customer", "s.total_amount"];
$sql = "SELECT " . implode(", ", $columns) . " 
        FROM sales s 
        JOIN customers c ON s.customer_id = c.id 
        WHERE 1=1";
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
if (!empty($start_date)) {
    $sql .= " AND s.sale_date >= ?";
}
if (!empty($end_date)) {
    $sql .= " AND s.sale_date <= ?";
}
$sql .= " ORDER BY s.id DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("<h3 style='color:red;text-align:center;'>SQL Error: " . $conn->error . "</h3>");
}

$types = '';
$params = [];
if (!empty($start_date)) {
    $types .= 's';
    $params[] = $start_date;
}
if (!empty($end_date)) {
    $types .= 's';
    $params[] = $end_date;
}
$types .= 'ii';
$params[] = $limit;
$params[] = $offset;
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$bills = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ✅ Handle preview and print for multiple or single bill
$preview_content = '';
if (isset($_POST['preview']) || isset($_POST['print']) || isset($_POST['single_preview'])) {
    $selected_ids = isset($_POST['selected_ids']) ? array_map('intval', $_POST['selected_ids']) : [];
    if (isset($_POST['single_preview'])) {
        $selected_ids = [intval($_POST['single_preview'])];
    } elseif (empty($selected_ids) && isset($_POST['select_all'])) {
        $selected_ids = array_column($bills, 'id');
    }

    if (!empty($selected_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $sql_details = "SELECT s.*, c.name AS customer, sd.quantity, sd.weight, sd.rate, sd.total, i.name AS item_name 
                        FROM sales s 
                        JOIN customers c ON s.customer_id = c.id 
                        LEFT JOIN sale_details sd ON s.id = sd.sale_id 
                        LEFT JOIN items i ON sd.item_id = i.id 
                        WHERE s.id IN ($placeholders)";
        $stmt_details = $conn->prepare($sql_details);
        if ($stmt_details) {
            $stmt_details->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
            $stmt_details->execute();
            $details = $stmt_details->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_details->close();

            foreach ($selected_ids as $id) {
                $sale = array_filter($details, function($d) use ($id) { return $id == $d['id']; });
                $sale = array_values($sale)[0];
                $sale_details = array_filter($details, function($d) use ($id) { return $id == $d['id']; });

                $product_total = array_sum(array_column(array_values($sale_details), 'total'));
                $hamali = $sale['hamali'] ?? 0;
                $freight = $sale['freight'] ?? 0;
                $net_amount = $product_total + $hamali + $freight;

                $preview_content .= '<div class="print-container">';
                $preview_content .= '<div class="invoice-header">';
                $preview_content .= '<h5>!! श्री शिवाय नमस्तुभ्यम् !!</h5>';
                $preview_content .= '<h2><strong>वैभव ट्रेडिंग कंपनी</strong></h2>';
                $preview_content .= '<h6>Shop No 2 Karibasweshwar complex main road kasar shirshi Mo.No 8208893491</h6>';
                $preview_content .= '<h5>Sale Bill</h5>';
                $preview_content .= '</div>';

                $preview_content .= '<div class="invoice-details">';
                $preview_content .= '<p><strong>Customer:</strong> ' . htmlspecialchars($sale['customer']) . '</p>';
                $preview_content .= '<p><strong>Bill No:</strong> ' . htmlspecialchars($sale['invoice_number']) . '</p>';
                $preview_content .= '<p><strong>Date:</strong> ' . date("d-m-Y", strtotime($sale['sale_date'])) . '</p>';
                $preview_content .= '</div>';

                $preview_content .= '<div class="table-container">';
                $preview_content .= '<table class="table table-bordered">';
                $preview_content .= '<thead><tr>';
                $preview_content .= '<th>Product</th><th>Qty</th><th>Weight</th><th>Rate</th><th>Total</th>';
                $preview_content .= '</tr></thead><tbody>';
                foreach ($sale_details as $detail) {
                    $preview_content .= '<tr>';
                    $preview_content .= '<td>' . htmlspecialchars($detail['item_name'] ?? 'N/A') . '</td>';
                    $preview_content .= '<td>' . ($detail['quantity'] ?? 0) . '</td>';
                    $preview_content .= '<td>' . ($detail['weight'] ?? 0) . '</td>';
                    $preview_content .= '<td>' . number_format($detail['rate'] ?? 0, 2) . '</td>';
                    $preview_content .= '<td>' . number_format($detail['total'] ?? 0, 2) . '</td>';
                    $preview_content .= '</tr>';
                }
                $preview_content .= '</tbody></table>';
                $preview_content .= '</div>';

                $preview_content .= '<div class="total-section">';
                $preview_content .= '<p><strong>Sub Total:</strong> ₹' . number_format($product_total, 2) . '</p>';
                $preview_content .= '<p>&nbsp;&nbsp;Hamali: ₹' . number_format($hamali, 2) . '</p>';
                $preview_content .= '<p>&nbsp;&nbsp;Freight: ₹' . number_format($freight, 2) . '</p>';
                $preview_content .= '<p class="total-amount"><strong>Net Total:</strong> ₹' . number_format($net_amount, 2) . '</p>';
                $preview_content .= '</div>';

                $preview_content .= '<div class="invoice-footer">';
                $preview_content .= '<h5>!!---Thank You Visit Again---!!</h5>';
                $preview_content .= '</div>';
                $preview_content .= '</div>'; // Close print-container
            }
        } else {
            die("<h3 style='color:red;text-align:center;'>SQL Error: " . $conn->error . "</h3>");
        }
    }
}

// ✅ Handle WhatsApp
if (isset($_POST['whatsapp'])) {
    $selected_ids = isset($_POST['selected_ids']) ? array_map('intval', $_POST['selected_ids']) : [];
    if (empty($selected_ids) && isset($_POST['select_all'])) {
        $selected_ids = array_column($bills, 'id');
    }

    if (!empty($selected_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $sql_details = "SELECT s.*, c.name AS customer, sd.quantity, sd.weight, sd.rate, sd.total, i.name AS item_name 
                        FROM sales s 
                        JOIN customers c ON s.customer_id = c.id 
                        LEFT JOIN sale_details sd ON s.id = sd.sale_id 
                        LEFT JOIN items i ON sd.item_id = i.id 
                        WHERE s.id IN ($placeholders)";
        $stmt_details = $conn->prepare($sql_details);
        if ($stmt_details) {
            $stmt_details->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
            $stmt_details->execute();
            $details = $stmt_details->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_details->close();

            $whatsapp_msg = "Selected Sale Bill Details:\n";
            foreach ($selected_ids as $id) {
                $sale = array_filter($details, function($d) use ($id) { return $id == $d['id']; });
                $sale = array_values($sale)[0];
                $sale_details = array_filter($details, function($d) use ($id) { return $id == $d['id']; });

                $whatsapp_msg .= "Bill No: " . htmlspecialchars($sale['invoice_number']) . "\n";
                $whatsapp_msg .= "Date: " . date("d-m-Y", strtotime($sale['sale_date'])) . "\n";
                $whatsapp_msg .= "Customer: " . htmlspecialchars($sale['customer']) . "\n";
                foreach ($sale_details as $detail) {
                    $whatsapp_msg .= "Product: " . htmlspecialchars($detail['item_name']) . ", Qty: " . $detail['quantity'] . ", Weight: " . $detail['weight'] . ", Rate: ₹" . number_format($detail['rate'], 2) . ", Total: ₹" . number_format($detail['total'], 2) . "\n";
                }
                $product_total = array_sum(array_column(array_values($sale_details), 'total'));
                $hamali = $sale['hamali'] ?? 0;
                $freight = $sale['freight'] ?? 0;
                $net_amount = $product_total + $hamali + $freight;
                $whatsapp_msg .= "Sub Total: ₹" . number_format($product_total, 2) . "\n";
                $whatsapp_msg .= "Hamali: ₹" . number_format($hamali, 2) . "\n";
                $whatsapp_msg .= "Freight: ₹" . number_format($freight, 2) . "\n";
                $whatsapp_msg .= "Net Total: ₹" . number_format($net_amount, 2) . "\n";
                $whatsapp_msg .= "----------------\n";
            }
            $encoded_msg = urlencode($whatsapp_msg);
            header("Location: https://wa.me/?text=" . $encoded_msg);
            exit;
        }
    }
}

// Pagination
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM sales");
$stmt->execute();
$total_rows = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$total_pages = ceil($total_rows / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Bill Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f9f9f9;
            margin: 0;
            padding: 20px;
        }
        .print-container {
            width: 148mm; /* A5 width */
            min-height: 210mm; /* A5 height */
            margin: 0 auto;
            background: #fff;
            padding: 10mm;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            page-break-after: always;
        }
        .invoice-header {
            text-align: center;
            border-bottom: 2px solid #ddd;
            margin-bottom: 5mm;
            padding-bottom: 3mm;
        }
        .invoice-header h2 {
            margin: 0;
            font-size: 20pt;
            letter-spacing: 1px;
        }
        .invoice-header h5 {
            margin: 2mm 0;
            font-size: 12pt;
        }
        .invoice-header h6 {
            margin: 2mm 0;
            font-size: 10pt;
        }
        .invoice-details p {
            margin: 2mm 0;
            font-size: 10pt;
        }
        .table-container {
            margin-top: 5mm;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 3mm;
            text-align: center;
        }
        table th {
            background-color: #f1f1f1;
            font-weight: bold;
        }
        .total-section {
            margin-top: 5mm;
            text-align: right;
            font-size: 10pt;
        }
        .total-section p {
            margin: 2mm 0;
        }
        .total-amount {
            font-weight: bold;
            font-size: 12pt;
            color: #2c3e50;
        }
        .invoice-footer {
            text-align: center;
            margin-top: 5mm;
        }
        #cancel {
            margin-bottom: -2%;
            width: 10%;
            margin-left: 88%;
        }
        #toggleForm {
            margin-left: 70%;
            margin-bottom: -4%;
            width: 18%;
        }

        /* Print Styles for A5 */
        @media print {
            body * {
                visibility: hidden;
            }
            #printableContent, #printableContent * {
                visibility: visible;
            }
            #printableContent {
                position: absolute;
                top: 0;
                left: 0;
                width: 148mm;
                height: auto;
                margin: 0;
                padding: 0;
            }
            .print-container {
                box-shadow: none;
                border: none;
                margin: 0;
                padding: 10mm;
                page-break-after: always;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold">Sale Bill Management</h2>
    </div>

    <!-- Sale Entry Form -->
    <div id="saleFormContainer" class="card p-4 shadow mb-4" style="display:none;">
        <form method="POST" id="saleForm" action="sale_bill_management.php?action=add">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">Select Customer</option>
                        <?php while ($row = $customers->fetch_assoc()) { ?>
                            <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Invoice Number</label>
                    <input type="text" name="invoice_number" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sale Date</label>
                    <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
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
                </tbody>
            </table>
            <button type="button" id="addRow" class="btn btn-outline-secondary mb-3">➕ Add Product</button>

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

            <div class="mb-3">
                <label class="form-label">Net Total</label>
                <input type="number" step="0.01" id="grandTotal" class="form-control fw-bold" readonly>
            </div>

            <button type="submit" class="btn btn-success">💾 Save Sale</button>
            <button type="button" class="btn btn-secondary" id="closeForm">Cancel</button>
            <div class="form-check mt-3">
                <input type="checkbox" class="form-check-input" id="printCheckbox">
                <label class="form-check-label" for="printCheckbox">Print Bill</label>
            </div>
        </form>
    </div>

    <!-- Date Filter Form -->
    <div class="mb-3">
        <form method="post" id="filterForm">
            <div class="row">
                <div class="col-md-4">
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-4">
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-info">Filter</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Sale Bill List -->
    <div class="card shadow p-3">
        <button class="btn btn-primary no-print" id="toggleForm">➕ New Sale Entry</button>
        <a href="index.php" id="cancel" class="btn btn-danger no-print">❌ Close</a>
        <h4 class="fw-bold mb-3">📋 Sale Bill List</h4>
        <div class="mb-3">
            <button class="btn btn-primary no-print" id="previewBtn">Preview Selected</button>
            <button class="btn btn-success no-print" id="printBtn" disabled>Print Selected</button>
            <button class="btn btn-secondary no-print" id="selectAllBtn">Select All</button>
            <button class="btn btn-warning no-print" id="deselectAllBtn">Deselect All</button>
            <button class="btn btn-success no-print" id="whatsappBtn"><i class="fab fa-whatsapp"></i> Send on WhatsApp</button>
        </div>
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th><input type="checkbox" id="selectAllCheckbox"></th>
                    <th>#</th>
                    <th>Bill No</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Preview/Print</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bills as $row): ?>
                    <tr>
                        <td><input type="checkbox" class="bill-checkbox" name="selected_ids[]" value="<?= $row['id'] ?>"></td>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['invoice_number'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['customer'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['sale_date'] ?? '-') ?></td>
                        <td><strong><?= isset($row['total_amount']) ? htmlspecialchars($row['total_amount']) : '-' ?></strong></td>
                        <td><button class="btn btn-sm btn-outline-primary single-preview-btn no-print" data-id="<?= $row['id'] ?>">🖨 Preview & Print</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"><?= $i ?></a>
                    </li>
                <?php } ?>
            </ul>
        </nav>
    </div>

    <!-- Item Stock Summary -->
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

<!-- Print Preview Modal -->
<div class="modal fade no-print" id="printPreviewModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🖨 Print Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="previewContent"><?= $preview_content ?? '' ?></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="printNowBtn">Print Now</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade no-print" id="successModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Success</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="successMessage"></p>
            </div>
            <div class="modal-footer">
                <a href="sale_bill_management.php?page=<?= $page ?>" class="btn btn-primary">Back to Sale Bill List</a>
            </div>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div class="modal fade no-print" id="errorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Error</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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

<!-- Hidden Printable Content -->
<div id="printableContent" style="display: none;"></div>

<script>
$(document).ready(function() {
    // Show modals if message is set
    <?php if ($modal_message && $modal_type === 'success') { ?>
        $('#successMessage').text('<?= addslashes($modal_message) ?>');
        $('#successModal').modal('show');
    <?php } elseif ($modal_message && $modal_type === 'error') { ?>
        $('#errorMessage').text('<?= addslashes($modal_message) ?>');
        $('#errorModal').modal('show');
    <?php } ?>

    // Toggle sale form
    $("#toggleForm").click(() => $("#saleFormContainer").slideDown());
    $("#closeForm").click(() => $("#saleFormContainer").slideUp());

    // Item selection and rate update
    $(document).on('change', '.item-select', function() {
        let rate = $(this).find('option:selected').data('rate');
        $(this).closest('tr').find('.rate').val(rate);
        calculateTotals();
    });

    // Stock validation
    $(document).on('input', '.qty', function() {
        let qty = parseFloat($(this).val()) || 0;
        let stock = parseFloat($(this).closest('tr').find('.item-select option:selected').data('stock')) || 0;
        if (qty > stock) {
            alert("Insufficient stock! Available: " + stock);
            $(this).val(stock);
        }
        calculateTotals();
    });

    // Calculate totals on input change
    $(document).on('input', '.weight, .rate, #hamali, #freight', calculateTotals);

    // Add product row
    $('#addRow').click(function() {
        let row = $('#productTable tbody tr:first').clone();
        row.find('input').val('');
        row.find('.total').val('');
        $('#productTable tbody').append(row);
    });

    // Remove product row
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

    // Print on form submission
    $('#saleForm').submit(function(e) {
        if ($('#printCheckbox').is(':checked')) {
            e.preventDefault();
            let customer = $('select[name="customer_id"] option:selected').text();
            let invoice = $('input[name="invoice_number"]').val();
            let date = $('input[name="date"]').val();
            let hamali = $('#hamali').val() || '0.00';
            let freight = $('#freight').val() || '0.00';
            let grandTotal = $('#grandTotal').val();

            let tableBody = '';
            $('#productTable tbody tr').each(function() {
                let item = $(this).find('.item-select option:selected').text();
                let qty = $(this).find('.qty').val() || '0.00';
                let weight = $(this).find('.weight').val() || '0.00';
                let rate = $(this).find('.rate').val() || '0.00';
                let total = $(this).find('.total').val() || '0.00';
                tableBody += `
                    <tr>
                        <td>${item}</td>
                        <td>${qty}</td>
                        <td>${weight}</td>
                        <td>${rate}</td>
                        <td>${total}</td>
                    </tr>`;
            });

            let printContent = `
                <div class="print-container">
                    <div class="invoice-header">
                        <h5>!! श्री शिवाय नमस्तुभ्यम् !!</h5>
                        <h2><strong>वैभव ट्रेडिंग कंपनी</strong></h2>
                        <h6>Shop No 2 Karibasweshwar complex main road kasar shirshi Mo.No 8208893491</h6>
                        <h5>Sale Bill</h5>
                    </div>
                    <div class="invoice-details">
                        <p><strong>Customer:</strong> ${customer}</p>
                        <p><strong>Bill No:</strong> ${invoice}</p>
                        <p><strong>Date:</strong> ${date}</p>
                    </div>
                    <div class="table-container">
                        <table class="table table-bordered">
                            <thead><tr><th>Product</th><th>Qty</th><th>Weight</th><th>Rate</th><th>Total</th></tr></thead>
                            <tbody>${tableBody}</tbody>
                        </table>
                    </div>
                    <div class="total-section">
                        <p><strong>Sub Total:</strong> ₹${grandTotal}</p>
                        <p>&nbsp;&nbsp;Hamali: ₹${hamali}</p>
                        <p>&nbsp;&nbsp;Freight: ₹${freight}</p>
                        <p class="total-amount"><strong>Net Total:</strong> ₹${grandTotal}</p>
                    </div>
                    <div class="invoice-footer">
                        <h5>!!---Thank You Visit Again---!!</h5>
                    </div>
                </div>`;
            $('#printableContent').html(printContent);
            window.print();
            $(this).unbind('submit').submit();
        }
    });

    // Select all checkboxes
    $('#selectAllCheckbox').change(function() {
        $('.bill-checkbox').prop('checked', this.checked);
        updatePrintButton();
    });

    $('#selectAllBtn').click(function() {
        $('#selectAllCheckbox').prop('checked', true).change();
    });

    $('#deselectAllBtn').click(function() {
        $('#selectAllCheckbox').prop('checked', false).change();
    });

    $('.bill-checkbox').change(function() {
        updatePrintButton();
    });

    // Bulk Preview
    $('#previewBtn').click(function() {
        let selectedIds = $('.bill-checkbox:checked').map(function() {
            return this.value;
        }).get();
        if (selectedIds.length === 0) {
            alert('Please select at least one bill.');
            return;
        }
        loadPreview(selectedIds, 'preview');
    });

    // Bulk Print
    $('#printBtn').click(function() {
        let selectedIds = $('.bill-checkbox:checked').map(function() {
            return this.value;
        }).get();
        if (selectedIds.length === 0) {
            alert('Please select at least one bill.');
            return;
        }
        loadPreview(selectedIds, 'print');
    });

    // Single Bill Preview/Print
    $('.single-preview-btn').click(function() {
        let saleId = $(this).data('id');
        loadPreview([saleId], 'single_preview');
    });

    // Load preview content via AJAX
    function loadPreview(ids, action) {
        let postData = {
            selected_ids: ids,
            [action]: true,
            start_date: $('[name="start_date"]').val(),
            end_date: $('[name="end_date"]').val(),
            page: <?= $page ?>
        };
        if (action === 'single_preview') {
            postData.single_preview = ids[0];
            delete postData.selected_ids;
        }
        $.post('sale_bill_management.php', postData, function(response) {
            let $tempDiv = $('<div>').html(response);
            $('#previewContent').html($tempDiv.find('#previewContent').html() || response);
            $('#printPreviewModal').modal('show');
            $('#printBtn').prop('disabled', false);
        });
    }

    // Print Now
    $('#printNowBtn').click(function() {
        let $printContent = $('#previewContent').clone();
        $('#printableContent').html($printContent.html());
        window.print();
    });

    // WhatsApp
    $('#whatsappBtn').click(function() {
        let selectedIds = $('.bill-checkbox:checked').map(function() {
            return this.value;
        }).get();
        if (selectedIds.length === 0) {
            alert('Please select at least one bill.');
            return;
        }
        $.post('sale_bill_management.php', { selected_ids: selectedIds, whatsapp: true, start_date: $('[name="start_date"]').val(), end_date: $('[name="end_date"]').val(), page: <?= $page ?> }, function(response) {
            // Redirect handled by PHP
        });
    });

    function updatePrintButton() {
        let hasSelected = $('.bill-checkbox:checked').length > 0;
        $('#printBtn').prop('disabled', !hasSelected);
    }

    // Submit filter form on date change
    $('[name="start_date"], [name="end_date"]').change(function() {
        $('#filterForm').submit();
    });
});
</script>

<?php include 'footer.php'; ?>
<?php ob_end_flush(); ?>
</body>
</html>