<?php
// bill_printing.php - Bill Printing with A5 Page Size Support
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
$columns = ["s.id", "s.invoice_number", "s.date", "a.name AS customer"];
if (columnExists($conn, "sales", "hamali")) $columns[] = "s.hamali";
if (columnExists($conn, "sales", "freight")) $columns[] = "s.freight";
if (columnExists($conn, "sales", "total_amount")) $columns[] = "s.total_amount";
elseif (columnExists($conn, "sales", "net_amount")) $columns[] = "s.net_amount AS total_amount";

$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$sql = "SELECT " . implode(", ", $columns) . " 
        FROM sales s 
        JOIN accounts a ON s.customer_id = a.id 
        WHERE 1=1";
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
if (!empty($start_date)) {
    $sql .= " AND s.date >= ?";
}
if (!empty($end_date)) {
    $sql .= " AND s.date <= ?";
}
$sql .= " ORDER BY s.id DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $modal_message = "SQL Error: " . $conn->error;
    $modal_type = 'error';
} else {
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
}

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
        $sql_details = "SELECT s.*, a.name AS customer, sd.quantity, sd.weight, sd.rate, sd.total, i.name AS item_name 
                        FROM sales s 
                        JOIN accounts a ON s.customer_id = a.id 
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
                if (empty($sale)) continue;
                $sale = array_values($sale)[0];
                $sale_details = array_filter($details, function($d) use ($id) { return $id == $d['id']; });

                $product_total = array_sum(array_column(array_values($sale_details), 'total'));
                $total_expenses = ($sale['hamali'] ?? 0) + ($sale['freight'] ?? 0);
                $net_amount = $product_total + $total_expenses;

                $preview_content .= '<div class="print-container">';
                $preview_content .= '<div class="invoice-header">';
                $preview_content .= '<h5>!! श्री शिवाय नमस्तुभ्यम् !!</h5>';
                $preview_content .= '<h2><strong>वैभव ट्रेडिंग कंपनी</strong></h2>';
                $preview_content .= '<h6>दुकान क्र. २, करिबेश्वर कॉम्प्लेक्स, मेन रोड, कासार शिरसी Mo.No 8208893491</h6>';
                $preview_content .= '<h5>Sale Bill</h5>';
                $preview_content .= '</div>';

                $preview_content .= '<div class="invoice-details">';
                $preview_content .= '<p><strong>Customer:</strong> ' . htmlspecialchars($sale['customer']) . '</p>';
                $preview_content .= '<p><strong>Bill No:</strong> ' . htmlspecialchars($sale['invoice_number']) . '</p>';
                $preview_content .= '<p><strong>Date:</strong> ' . date("d-m-Y", strtotime($sale['date'])) . '</p>';
                $preview_content .= '</div>';

                $preview_content .= '<div class="table-container">';
                $preview_content .= '<table class="table table-bordered">';
                $preview_content .= '<thead><tr>';
                $preview_content .= '<th>Product</th><th>Qty</th><th>Weight</th><th>Rate</th><th>Total</th>';
                $preview_content .= '</tr></thead><tbody>';
                foreach ($sale_details as $detail) {
                    if (!isset($detail['item_name'])) continue;
                    $preview_content .= '<tr>';
                    $preview_content .= '<td>' . htmlspecialchars($detail['item_name'] ?? 'N/A') . '</td>';
                    $preview_content .= '<td>' . number_format($detail['quantity'] ?? 0, 2) . '</td>';
                    $preview_content .= '<td>' . number_format($detail['weight'] ?? 0, 2) . '</td>';
                    $preview_content .= '<td>' . number_format($detail['rate'] ?? 0, 2) . '</td>';
                    $preview_content .= '<td>' . number_format($detail['total'] ?? 0, 2) . '</td>';
                    $preview_content .= '</tr>';
                }
                $preview_content .= '</tbody></table>';
                $preview_content .= '</div>';

                $preview_content .= '<div class="total-section">';
                $preview_content .= '<p><strong>Grand Total:</strong> ₹' . number_format($product_total, 2) . '</p>';
                $preview_content .= '<p>&nbsp;&nbsp;Hamali: ₹' . number_format($sale['hamali'] ?? 0, 2) . '</p>';
                $preview_content .= '<p>&nbsp;&nbsp;Freight: ₹' . number_format($sale['freight'] ?? 0, 2) . '</p>';
                $preview_content .= '<p><strong>Total Expenses:</strong> ₹' . number_format($total_expenses, 2) . '</p>';
                $preview_content .= '<p class="total-amount"><strong>Net Total:</strong> ₹' . number_format($net_amount, 2) . '</p>';
                $preview_content .= '</div>';

                $preview_content .= '<div class="invoice-footer">';
                $preview_content .= '<h5>!!---Thank You Visit Again---!!</h5>';
                $preview_content .= '</div>';
                $preview_content .= '</div>';
            }
        } else {
            $modal_message = "SQL Error: " . $conn->error;
            $modal_type = 'error';
        }
    } else {
        $modal_message = "Please select at least one bill.";
        $modal_type = 'error';
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
        $sql_details = "SELECT s.*, a.name AS customer, sd.quantity, sd.weight, sd.rate, sd.total, i.name AS item_name 
                        FROM sales s 
                        JOIN accounts a ON s.customer_id = a.id 
                        LEFT JOIN sale_details sd ON s.id = sd.sale_id 
                        LEFT JOIN items i ON sd.item_id = i.id 
                        WHERE s.id IN ($placeholders)";
        $stmt_details = $conn->prepare($sql_details);
        if ($stmt_details) {
            $stmt_details->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
            $stmt_details->execute();
            $details = $stmt_details->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_details->close();

            $whatsapp_msg = "Selected Bill Details:\n";
            foreach ($selected_ids as $id) {
                $sale = array_filter($details, function($d) use ($id) { return $id == $d['id']; });
                if (empty($sale)) continue;
                $sale = array_values($sale)[0];
                $sale_details = array_filter($details, function($d) use ($id) { return $id == $d['id']; });

                $whatsapp_msg .= "Bill No: " . htmlspecialchars($sale['invoice_number']) . "\n";
                $whatsapp_msg .= "Date: " . date("d-m-Y", strtotime($sale['date'])) . "\n";
                $whatsapp_msg .= "Customer: " . htmlspecialchars($sale['customer']) . "\n";
                foreach ($sale_details as $detail) {
                    if (!isset($detail['item_name'])) continue;
                    $whatsapp_msg .= "Product: " . htmlspecialchars($detail['item_name'] ?? 'N/A') . ", Qty: " . number_format($detail['quantity'] ?? 0, 2) . ", Weight: " . number_format($detail['weight'] ?? 0, 2) . ", Rate: ₹" . number_format($detail['rate'] ?? 0, 2) . ", Total: ₹" . number_format($detail['total'] ?? 0, 2) . "\n";
                }
                $product_total = array_sum(array_column(array_values($sale_details), 'total'));
                $total_expenses = ($sale['hamali'] ?? 0) + ($sale['freight'] ?? 0);
                $net_amount = $product_total + $total_expenses;
                $whatsapp_msg .= "Grand Total: ₹" . number_format($product_total, 2) . "\n";
                $whatsapp_msg .= "Total Expenses: ₹" . number_format($total_expenses, 2) . "\n";
                $whatsapp_msg .= "Net Total: ₹" . number_format($net_amount, 2) . "\n";
                $whatsapp_msg .= "----------------\n";
            }
            $encoded_msg = urlencode($whatsapp_msg);
            header("Location: https://wa.me/?text=" . $encoded_msg);
            exit;
        } else {
            $modal_message = "SQL Error: " . $conn->error;
            $modal_type = 'error';
        }
    } else {
        $modal_message = "Please select at least one bill.";
        $modal_type = 'error';
    }
}

// Pagination
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM sales");
if ($stmt) {
    $stmt->execute();
    $total_rows = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    $total_pages = ceil($total_rows / $limit);
} else {
    $modal_message = "SQL Error: " . $conn->error;
    $modal_type = 'error';
    $total_pages = 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill Printing</title>
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
        <h2 class="fw-bold">Bill Printing</h2>
    </div>

    <!-- Date Filter Form -->
    <div class="mb-3">
        <form method="post" id="filterForm" class="no-print">
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
        <a href="index.php" id="cancel" class="btn btn-danger no-print">❌ Close</a>
        <h4 class="fw-bold mb-3">📋 Bill List</h4>
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
                <?php foreach ($pattis as $row): ?>
                    <tr>
                        <td><input type="checkbox" class="bill-checkbox" name="selected_ids[]" value="<?= $row['id'] ?>"></td>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['invoice_number'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['customer'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['date'] ?? '-') ?></td>
                        <td><strong><?= isset($row['total_amount']) ? number_format($row['total_amount'], 2) : '-' ?></strong></td>
                        <td><button class="btn btn-sm btn-outline-primary single-preview-btn no-print" data-id="<?= $row['id'] ?>">🖨 Preview & Print</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
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
                <div id="previewContent"><?= $preview_content ?></div>
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
                <p id="successMessage"><?= htmlspecialchars($modal_message) ?></p>
            </div>
            <div class="modal-footer">
                <a href="bill_printing.php?page=<?= $page ?>" class="btn btn-primary">Back to Bill List</a>
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
                <p id="errorMessage"><?= htmlspecialchars($modal_message) ?></p>
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
    <?php if ($modal_message && $modal_type === 'success'): ?>
        $('#successModal').modal('show');
    <?php elseif ($modal_message && $modal_type === 'error'): ?>
        $('#errorModal').modal('show');
    <?php endif; ?>

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
            alert("!! Please Select At Least One Bill !!")
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
           alert("!! Please Select At Least One Bill !!")
            return;
        }
        loadPreview(selectedIds, 'print');
    });

    // Single Bill Preview/Print
    $('.single-preview-btn').click(function() {
        let saleId = $(this).data('id');
        loadPreview([saleId], 'single_preview');
    });

    // WhatsApp
    $('#whatsappBtn').click(function() {
        let selectedIds = $('.bill-checkbox:checked').map(function() {
            return this.value;
        }).get();
        if (selectedIds.length === 0) {
            $('#errorMessage').text('Please select at least one bill.');
            $('#errorModal').modal('show');
            return;
        }
        submitForm(selectedIds, 'whatsapp');
    });

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
        $.post('bill_printing.php', postData, function(response) {
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
        $('#printPreviewModal').modal('hide');
    });

    function updatePrintButton() {
        let hasSelected = $('.bill-checkbox:checked').length > 0;
        $('#printBtn').prop('disabled', !hasSelected);
    }

    // Submit filter form on date change
    $('[name="start_date"], [name="end_date"]').change(function() {
        $('#filterForm').submit();
    });

    // Handle print completion
    window.onafterprint = function() {
        $('#printableContent').empty();
        window.location.href = 'bill_printing.php?page=<?= $page ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>';
    };
});
</script>

<?php include 'footer.php'; ?>
<?php ob_end_flush(); ?>
</body>
</html>