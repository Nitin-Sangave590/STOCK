<?php
// patti_printing.php - Patti Printing with A5 Page Size Support
ob_start();
include 'db.php';
include 'header.php';

// Initialize message variables
$modal_message = '';
$modal_type = ''; // 'success' or 'error'

// Helper function to check if a column exists
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// Build safe SELECT query with date filter
$columns = ["p.id", "p.invoice_number", "p.date", "a.name AS customer"];
if (columnExists($conn, "sales", "weight")) $columns[] = "p.weight";
if (columnExists($conn, "sales", "rate")) $columns[] = "p.rate";
if (columnExists($conn, "sales", "total_amount")) $columns[] = "p.total_amount";
elseif (columnExists($conn, "sales", "net_amount")) $columns[] = "p.net_amount AS total_amount";
if (columnExists($conn, "sales", "hamali")) $columns[] = "p.hamali";
if (columnExists($conn, "sales", "freight")) $columns[] = "p.freight";
if (columnExists($conn, "sales", "uchal")) $columns[] = "p.uchal";

$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$sql = "SELECT " . implode(", ", $columns) . " 
        FROM sales p 
        JOIN accounts a ON p.customer_id = a.id 
        WHERE 1=1";
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
if (!empty($start_date)) {
    $sql .= " AND p.date >= ?";
}
if (!empty($end_date)) {
    $sql .= " AND p.date <= ?";
}
$sql .= " ORDER BY p.id DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL Error: " . $conn->error);
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
$pattis = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle preview and print
$preview_content = '';
if (isset($_POST['preview']) || isset($_POST['print']) || isset($_POST['single_preview'])) {
    $selected_ids = isset($_POST['selected_ids']) ? array_map('intval', $_POST['selected_ids']) : [];
    if (isset($_POST['single_preview'])) {
        $selected_ids = [intval($_POST['single_preview'])];
    } elseif (empty($selected_ids) && isset($_POST['select_all'])) {
        $selected_ids = array_column($pattis, 'id');
    }

    if (!empty($selected_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $sql_details = "SELECT p.*, a.name AS customer, pd.quantity, pd.weight, pd.rate, pd.total, i.name AS item_name 
                        FROM sales p 
                        JOIN accounts a ON p.customer_id = a.id 
                        LEFT JOIN purchase_details pd ON p.id = pd.purchase_id 
                        LEFT JOIN items i ON pd.item_id = i.id 
                        WHERE p.id IN ($placeholders)";
        $stmt_details = $conn->prepare($sql_details);
        if ($stmt_details) {
            $stmt_details->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
            $stmt_details->execute();
            $details = $stmt_details->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_details->close();

            foreach ($selected_ids as $id) {
                $purchase = array_filter($details, function($d) use ($id) { return $id == $d['id']; });
                $purchase = array_values($purchase)[0];
                $purchase_details = array_filter($details, function($d) use ($id) { return $id == $d['id']; });

                $product_total = array_sum(array_column(array_values($purchase_details), 'total'));
                $total_expenses = ($purchase['hamali'] ?? 0) + ($purchase['freight'] ?? 0) + ($purchase['uchal'] ?? 0);
                $net_amount = $product_total - $total_expenses;

                $preview_content .= '<div class="print-container">';
                $preview_content .= '<div class="invoice-header">';
                $preview_content .= '<h2>वैभव ट्रेडिंग कंपनी</h2>';
                $preview_content .= '<p>Shop No 2 Karibasweshwar complex main road kasar shirshi Mo.No 8208893491</p>';
                $preview_content .= '<p>Farmer Patti</p>';
                $preview_content .= '</div>';

                $preview_content .= '<div class="invoice-details">';
                $preview_content .= '<p><strong>Farmer:</strong> ' . htmlspecialchars($purchase['customer']) . '</p>';
                $preview_content .= '<p><strong>Bill No:</strong> ' . htmlspecialchars($purchase['invoice_number']) . '</p>';
                $preview_content .= '<p><strong>Date:</strong> ' . date("d-m-Y", strtotime($purchase['date'])) . '</p>';
                $preview_content .= '</div>';

                $preview_content .= '<div class="table-container">';
                $preview_content .= '<table>';
                $preview_content .= '<thead><tr>';
                $preview_content .= '<th>Product</th><th>Qty</th><th>Weight</th><th>Rate</th><th>Total</th>';
                $preview_content .= '</tr></thead><tbody>';
                foreach ($purchase_details as $detail) {
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
                $preview_content .= '<p><strong>Grand Total:</strong> ₹' . number_format($product_total, 2) . '</p>';
                $preview_content .= '<p><strong>Hamali:</strong> ₹' . number_format($purchase['hamali'] ?? 0, 2) . '</p>';
                $preview_content .= '<p><strong>Motar Bhade:</strong> ₹' . number_format($purchase['freight'] ?? 0, 2) . '</p>';
                $preview_content .= '<p><strong>Uchal:</strong> ₹' . number_format($purchase['uchal'] ?? 0, 2) . '</p>';
                $preview_content .= '<p class="total-amount"><strong>Net Total:</strong> ₹' . number_format($net_amount, 2) . '</p>';
                $preview_content .= '</div>';
                $preview_content .= '</div>';
            }
        } else {
            die("SQL Error: " . $conn->error);
        }
    }
}

// Handle WhatsApp
if (isset($_POST['whatsapp'])) {
    $selected_ids = isset($_POST['selected_ids']) ? array_map('intval', $_POST['selected_ids']) : [];
    if (empty($selected_ids) && isset($_POST['select_all'])) {
        $selected_ids = array_column($pattis, 'id');
    }

    if (!empty($selected_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $sql_details = "SELECT p.*, a.name AS customer, pd.quantity, pd.weight, pd.rate, pd.total, i.name AS item_name 
                        FROM sales p 
                        JOIN accounts a ON p.customer_id = a.id 
                        LEFT JOIN purchase_details pd ON p.id = pd.purchase_id 
                        LEFT JOIN items i ON pd.item_id = i.id 
                        WHERE p.id IN ($placeholders)";
        $stmt_details = $conn->prepare($sql_details);
        if ($stmt_details) {
            $stmt_details->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
            $stmt_details->execute();
            $details = $stmt_details->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_details->close();

            $whatsapp_msg = "Selected Bill Details:\n";
            foreach ($selected_ids as $id) {
                $purchase = array_filter($details, function($d) use ($id) { return $id == $d['id']; });
                $purchase = array_values($purchase)[0];
                $purchase_details = array_filter($details, function($d) use ($id) { return $id == $d['id']; });

                $whatsapp_msg .= "Bill No: " . htmlspecialchars($purchase['invoice_number']) . "\n";
                $whatsapp_msg .= "Date: " . date("d-m-Y", strtotime($purchase['date'])) . "\n";
                $whatsapp_msg .= "Farmer: " . htmlspecialchars($purchase['customer']) . "\n";
                foreach ($purchase_details as $detail) {
                    $whatsapp_msg .= "Product: " . htmlspecialchars($detail['item_name']) . ", Qty: " . $detail['quantity'] . ", Weight: " . $detail['weight'] . ", Rate: ₹" . number_format($detail['rate'], 2) . ", Total: ₹" . number_format($detail['total'], 2) . "\n";
                }
                $product_total = array_sum(array_column(array_values($purchase_details), 'total'));
                $total_expenses = ($purchase['hamali'] ?? 0) + ($purchase['freight'] ?? 0) + ($purchase['uchal'] ?? 0);
                $net_amount = $product_total - $total_expenses;
                $whatsapp_msg .= "Grand Total: ₹" . number_format($product_total, 2) . "\n";
                $whatsapp_msg .= "Total Expenses: ₹" . number_format($total_expenses, 2) . "\n";
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
    <title>Bill </title>
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
            border-radius: 12px;
            box-shadow: 0px 4px 12px rgba(0,0,0,0.1);
            page-break-after: always;
        }
        .invoice-header {
            text-align: center;
            border-bottom: 2px solid #ddd;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        .invoice-header h2 {
            margin: 0;
            font-size: 28px;
            letter-spacing: 1px;
        }
        .invoice-header p {
            margin: 4px 0;
            font-size: 16px;
        }
        .invoice-details p {
            margin: 4px 0;
            font-size: 16px;
        }
        .table-container {
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        table th {
            background-color: #f1f1f1;
            font-weight: bold;
        }
        .total-section {
            margin-top: 20px;
            text-align: right;
        }
        .total-section p {
            font-size: 18px;
            margin: 4px 0;
        }
        .total-amount {
            font-weight: bold;
            font-size: 22px;
            color: #2c3e50;
        }
        @media print {
            body {
                background: none;
            }
            .print-container {
                box-shadow: none;
                border: none;
                margin: 0;
                padding: 10mm;
            }
            .no-print {
                display: none;
            }
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<div class="container mt-4">
    <h2 class="fw-bold mb-3">Patti Register</h2>

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

    <div class="mb-3 no-print">
        <button class="btn btn-primary" id="previewBtn">Preview Selected</button>
        <button class="btn btn-success" id="printBtn" disabled>Print Selected</button>
        <button class="btn btn-secondary" id="selectAllBtn">Select All</button>
        <button class="btn btn-warning" id="deselectAllBtn">Deselect All</button>
        <button class="btn btn-success" id="whatsappBtn"><i class="fab fa-whatsapp"></i> Send on WhatsApp</button>
    </div>

    <div class="card shadow p-3">
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th><input type="checkbox" id="selectAllCheckbox"></th>
                    <th>#</th>
                    <th>Patti No</th>
                    <th>Farmer</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Preview/Print</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pattis as $row): ?>
                    <tr>
                        <td><input type="checkbox" class="bill-checkbox" name="selected_ids[]" value="<?= $row['id'] ?>"></td>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['invoice_number'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['customer'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['date'] ?? '-') ?></td>
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

    <!-- Preview Content -->
    <?php if ($preview_content) { ?>
        <div id="previewContent"><?= $preview_content ?></div>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                <?php if (isset($_POST['print'])) { ?>
                    window.print();
                    window.onafterprint = function() {
                        window.location.href = 'patti_printing.php?page=<?= $page ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>';
                    };
                <?php } ?>
            });
        </script>
    <?php } ?>
</div>

<script>
$(document).ready(function() {
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

    $('#previewBtn').click(function() {
        let selectedIds = $('.bill-checkbox:checked').map(function() {
            return this.value;
        }).get();
        if (selectedIds.length === 0) {
            alert('Please select at least one patti.');
            return;
        }
        submitForm(selectedIds, 'preview');
    });

    $('#printBtn').click(function() {
        let selectedIds = $('.bill-checkbox:checked').map(function() {
            return this.value;
        }).get();
        if (selectedIds.length === 0) {
            alert('Please select at least one patti.');
            return;
        }
        submitForm(selectedIds, 'print');
    });

    $('.single-preview-btn').click(function() {
        let purchaseId = $(this).data('id');
        submitForm([purchaseId], 'single_preview');
    });

    $('#whatsappBtn').click(function() {
        let selectedIds = $('.bill-checkbox:checked').map(function() {
            return this.value;
        }).get();
        if (selectedIds.length === 0) {
            alert('Please select at least one patti.');
            return;
        }
        submitForm(selectedIds, 'whatsapp');
    });

    function submitForm(ids, action) {
        let form = $('<form>').attr({ method: 'POST', action: 'patti_printing.php' });
        if (action === 'single_preview') {
            form.append($('<input>').attr({ type: 'hidden', name: 'single_preview', value: ids[0] }));
        } else {
            ids.forEach(id => {
                form.append($('<input>').attr({ type: 'hidden', name: 'selected_ids[]', value: id }));
            });
            form.append($('<input>').attr({ type: 'hidden', name: action, value: true }));
        }
        form.append($('<input>').attr({ type: 'hidden', name: 'start_date', value: $('[name="start_date"]').val() }));
        form.append($('<input>').attr({ type: 'hidden', name: 'end_date', value: $('[name="end_date"]').val() }));
        form.append($('<input>').attr({ type: 'hidden', name: 'page', value: <?= $page ?> }));
        $('body').append(form);
        form.submit();
    }

    function updatePrintButton() {
        let hasSelected = $('.bill-checkbox:checked').length > 0;
        $('#printBtn').prop('disabled', !hasSelected);
    }

    $('[name="start_date"], [name="end_date"]').change(function() {
        $('#filterForm').submit();
    });
});
</script>

<?php include 'footer.php'; ?>
<?php ob_end_flush(); ?>
</body>
</html>