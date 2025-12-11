<?php
// patti_printing.php - Patti Printing with A5 Page Size Support
ob_start();
include 'db.php';
include 'header.php';

// Initialize message variables for modals
$modal_message = '';
$modal_type = ''; // 'success' or 'error'

// Helper function to check if a column exists
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// Build safe SELECT query with date filter
$columns = ["p.id", "p.invoice_number", "p.purchase_date", "a.name AS supplier"];
if (columnExists($conn, "purchases", "weight")) $columns[] = "p.weight";
if (columnExists($conn, "purchases", "rate")) $columns[] = "p.rate";
if (columnExists($conn, "purchases", "total_amount")) $columns[] = "p.total_amount";
elseif (columnExists($conn, "purchases", "net_amount")) $columns[] = "p.net_amount AS total_amount";
if (columnExists($conn, "purchases", "hamali")) $columns[] = "p.hamali";
if (columnExists($conn, "purchases", "freight")) $columns[] = "p.freight";
if (columnExists($conn, "purchases", "uchal")) $columns[] = "p.uchal";

$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$sql = "SELECT " . implode(", ", $columns) . " 
        FROM purchases p 
        JOIN accounts a ON p.supplier_id = a.id 
        WHERE 1=1";
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
if (!empty($start_date)) {
    $sql .= " AND p.purchase_date >= ?";
}
if (!empty($end_date)) {
    $sql .= " AND p.purchase_date <= ?";
}
$sql .= " ORDER BY p.id DESC LIMIT ? OFFSET ?";

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
$pattis = $result->fetch_all(MYSQLI_ASSOC);

// Handle preview and print for multiple or single bill
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
        $sql_details = "SELECT p.*, a.name AS supplier, pd.quantity, pd.weight, pd.rate, pd.total, i.name AS item_name 
                        FROM purchases p 
                        JOIN accounts a ON p.supplier_id = a.id 
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
                $preview_content .= '<h5>!! श्री शिवाय नमस्तुभ्यम् !!</h5>';
                $preview_content .= '<h2><strong>वैभव ट्रेडिंग कंपनी</strong></h2>';
                $preview_content .= '<h6>दुकान क्र. 2 करीबसवेश्वर कॉम्प्लेक्स मेन रोड कासार शिरशी मो.नं:8208893491</h6>';
                $preview_content .= '<h5>शेतकरी पट्टी</h5>';
                $preview_content .= '</div>';

                $preview_content .= '<div class="invoice-details">';
                $preview_content .= '<p><strong>Farmer:</strong> ' . htmlspecialchars($purchase['supplier']) . '</p>';
                $preview_content .= '<p><strong>Patti No:</strong> ' . htmlspecialchars($purchase['invoice_number']) . '</p>';
                $preview_content .= '<p><strong>Date:</strong> ' . date("d-m-Y", strtotime($purchase['purchase_date'])) . '</p>';
                $preview_content .= '</div>';

                $preview_content .= '<div class="table-container">';
                $preview_content .= '<table class="table table-bordered">';
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
                $preview_content .= '<p><strong>एकुन:</strong> ₹' . number_format($product_total, 2) . '</p>';
                $preview_content .= '<p>&nbsp;&nbsp;हमाली: ₹' . number_format($purchase['hamali'] ?? 0, 2) . '</p>';
                $preview_content .= '<p>&nbsp;&nbsp;मो.भाडे: ₹' . number_format($purchase['freight'] ?? 0, 2) . '</p>';
                $preview_content .= '<p>&nbsp;&nbsp;उचल: ₹' . number_format($purchase['uchal'] ?? 0, 2) . '</p>';
                $preview_content .= '<p><strong>एकुन.खर्च:</strong> ₹' . number_format($total_expenses, 2) . '</p>';
                $preview_content .= '<p class="total-amount"><strong>नक्की शिल्लक:</strong> ₹' . number_format($net_amount, 2) . '</p>';
                $preview_content .= '</div>';

                $preview_content .= '<div class="invoice-footer">';
                $preview_content .= '<p>!!---Thank You Visit Again---!!</p>';
                $preview_content .= '</div>';
                $preview_content .= '</div>'; // Close print-container
            }
        } else {
            die("<h3 style='color:red;text-align:center;'>SQL Error: " . $conn->error . "</h3>");
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
        $sql_details = "SELECT p.*, a.name AS supplier, pd.quantity, pd.weight, pd.rate, pd.total, i.name AS item_name 
                        FROM purchases p 
                        JOIN accounts a ON p.supplier_id = a.id 
                        LEFT JOIN purchase_details pd ON p.id = pd.purchase_id 
                        LEFT JOIN items i ON pd.item_id = i.id 
                        WHERE p.id IN ($placeholders)";
        $stmt_details = $conn->prepare($sql_details);
        if ($stmt_details) {
            $stmt_details->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
            $stmt_details->execute();
            $details = $stmt_details->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_details->close();

            $whatsapp_msg = "Selected Patti Details:\n";
            foreach ($selected_ids as $id) {
                $purchase = array_filter($details, function($d) use ($id) { return $id == $d['id']; });
                $purchase = array_values($purchase)[0];
                $purchase_details = array_filter($details, function($d) use ($id) { return $id == $d['id']; });

                $whatsapp_msg .= "Patti No: " . htmlspecialchars($purchase['invoice_number']) . "\n";
                $whatsapp_msg .= "Date: " . date("d-m-Y", strtotime($purchase['purchase_date'])) . "\n";
                $whatsapp_msg .= "Supplier: " . htmlspecialchars($purchase['supplier']) . "\n";
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
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM purchases");
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
    <title>Patti Register</title>
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
        <h2 class="fw-bold">Patti Register</h2>
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

    <div class="mb-3">
        <button class="btn btn-primary no-print" id="previewBtn">Preview Selected</button>
        <button class="btn btn-success no-print" id="printBtn" disabled>Print Selected</button>
        <button class="btn btn-secondary no-print" id="selectAllBtn">Select All</button>
        <button class="btn btn-warning no-print" id="deselectAllBtn">Deselect All</button>
        <button class="btn btn-success no-print" id="whatsappBtn"><i class="fab fa-whatsapp"></i> Send on WhatsApp</button>
    </div>

    <div class="card shadow p-3">
        <a href="index.php" id="cancel" class="btn btn-danger no-print">❌ Close</a>
        <h4 class="fw-bold mb-3">📋 Patti List</h4>
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th><input type="checkbox" id="selectAllCheckbox"></th>
                    <th>#</th>
                    <th>Patti No</th>
                    <th>Supplier</th>
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
                        <td><?= htmlspecialchars($row['supplier'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['purchase_date'] ?? '-') ?></td>
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
                <a href="patti_printing.php?page=<?= $page ?>" class="btn btn-primary">Back to Patti List</a>
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
            alert('!! Please select at least one bill.!!');
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
        let purchaseId = $(this).data('id');
        loadPreview([purchaseId], 'single_preview');
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
        $.post('patti_printing.php', postData, function(response) {
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
        $.post('patti_printing.php', { selected_ids: selectedIds, whatsapp: true, start_date: $('[name="start_date"]').val(), end_date: $('[name="end_date"]').val(), page: <?= $page ?> }, function(response) {
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