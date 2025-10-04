<?php
// patti_register.php - Complete Patti Printing + Preview + Bulk Print with Checkbox Selection, WhatsApp, and Date Filter

include 'db.php'; // ✅ Your database connection

// ---------- HELPER FUNCTION TO CHECK COLUMNS ----------
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// ---------- BUILD SAFE SELECT QUERY WITH DATE FILTER ----------
$columns = ["p.id", "p.invoice_number", "p.purchase_date", "a.name AS supplier"];
if (columnExists($conn, "purchases", "weight")) $columns[] = "p.weight";
if (columnExists($conn, "purchases", "rate")) $columns[] = "p.rate";
if (columnExists($conn, "purchases", "total_amount")) $columns[] = "p.total_amount";
elseif (columnExists($conn, "purchases", "net_amount")) $columns[] = "p.net_amount AS total_amount";

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
$sql .= " ORDER BY p.id DESC";

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
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$pattis = $result->fetch_all(MYSQLI_ASSOC);

// ---------- HANDLE SINGLE PRINT PREVIEW ----------
if (isset($_GET['print_id'])) {
    $id = intval($_GET['print_id']);
    $sql2 = "SELECT p.*, a.name AS supplier 
             FROM purchases p
             JOIN accounts a ON p.supplier_id = a.id
             WHERE p.id = ?";
    $stmt2 = $conn->prepare($sql2);
    if (!$stmt2) {
        die("<h3 style='color:red;text-align:center;'>SQL Error: " . $conn->error . "</h3>");
    }
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    $data = $stmt2->get_result()->fetch_assoc();

    if (!$data) {
        die("<h3 style='color:red;text-align:center;'>Invalid Patti ID</h3>");
    }

    // ---------- PRINT-FRIENDLY OUTPUT ----------
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Patti #<?= htmlspecialchars($data['invoice_number'] ?? $data['id']) ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .bill-box { border: 2px solid #333; padding: 20px; max-width: 700px; margin: auto; }
            h2 { text-align: center; margin-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
            .total { font-weight: bold; text-align: right; }
            @media print {
                body { margin: 0; }
                button { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="bill-box">
            <h2>Purchase Patti</h2>
            <p><strong>Invoice No:</strong> <?= htmlspecialchars($data['invoice_number'] ?? $data['id']) ?></p>
            <p><strong>Date:</strong> <?= htmlspecialchars($data['purchase_date'] ?? '-') ?></p>
            <p><strong>Supplier:</strong> <?= htmlspecialchars($data['supplier'] ?? '-') ?></p>

            <table>
                <tr>
                    <?php if (isset($data['weight'])): ?><th>Weight</th><?php endif; ?>
                    <?php if (isset($data['rate'])): ?><th>Rate</th><?php endif; ?>
                    <?php if (isset($data['total_amount']) || isset($data['net_amount'])): ?><th>Total</th><?php endif; ?>
                </tr>
                <tr>
                    <?php if (isset($data['weight'])): ?><td><?= htmlspecialchars($data['weight']) ?></td><?php endif; ?>
                    <?php if (isset($data['rate'])): ?><td><?= htmlspecialchars($data['rate']) ?></td><?php endif; ?>
                    <?php 
                        $total = $data['total_amount'] ?? $data['net_amount'] ?? 0;
                        if ($total): ?>
                        <td><?= htmlspecialchars($total) ?></td>
                    <?php endif; ?>
                </tr>
            </table>

            <?php if ($total): ?>
                <p class="total">Grand Total: ₹<?= number_format($total, 2) ?></p>
            <?php endif; ?>

            <div style="text-align:center; margin-top:15px;">
                <button onclick="window.print()">🖨 Print</button>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle bulk print preview
if (isset($_POST['preview']) || isset($_POST['print']) || isset($_POST['whatsapp'])) {
    $selected_ids = isset($_POST['selected_ids']) ? array_map('intval', $_POST['selected_ids']) : [];
    if (empty($selected_ids) && isset($_POST['select_all'])) {
        $selected_ids = array_column($pattis, 'id');
    }

    if (!empty($selected_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $sql3 = "SELECT p.*, a.name AS supplier 
                 FROM purchases p
                 JOIN accounts a ON p.supplier_id = a.id
                 WHERE p.id IN ($placeholders)";
        $stmt3 = $conn->prepare($sql3);
        if ($stmt3) {
            $stmt3->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
            $stmt3->execute();
            $selected_pattis = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            die("<h3 style='color:red;text-align:center;'>SQL Error: " . $conn->error . "</h3>");
        }

        if (isset($_POST['print'])) {
            foreach ($selected_pattis as $data) {
                ?>
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Patti #<?= htmlspecialchars($data['invoice_number'] ?? $data['id']) ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .bill-box { border: 2px solid #333; padding: 20px; max-width: 700px; margin: auto; }
                        h2 { text-align: center; margin-bottom: 10px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
                        .total { font-weight: bold; text-align: right; }
                        @media print {
                            body { margin: 0; }
                            button { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="bill-box">
                        <h2>Purchase Patti</h2>
                        <p><strong>Invoice No:</strong> <?= htmlspecialchars($data['invoice_number'] ?? $data['id']) ?></p>
                        <p><strong>Date:</strong> <?= htmlspecialchars($data['purchase_date'] ?? '-') ?></p>
                        <p><strong>Supplier:</strong> <?= htmlspecialchars($data['supplier'] ?? '-') ?></p>

                        <table>
                            <tr>
                                <?php if (isset($data['weight'])): ?><th>Weight</th><?php endif; ?>
                                <?php if (isset($data['rate'])): ?><th>Rate</th><?php endif; ?>
                                <?php if (isset($data['total_amount']) || isset($data['net_amount'])): ?><th>Total</th><?php endif; ?>
                            </tr>
                            <tr>
                                <?php if (isset($data['weight'])): ?><td><?= htmlspecialchars($data['weight']) ?></td><?php endif; ?>
                                <?php if (isset($data['rate'])): ?><td><?= htmlspecialchars($data['rate']) ?></td><?php endif; ?>
                                <?php 
                                    $total = $data['total_amount'] ?? $data['net_amount'] ?? 0;
                                    if ($total): ?>
                                    <td><?= htmlspecialchars($total) ?></td>
                                <?php endif; ?>
                            </tr>
                        </table>

                        <?php if ($total): ?>
                            <p class="total">Grand Total: ₹<?= number_format($total, 2) ?></p>
                        <?php endif; ?>
                    </div>
                </body>
                </html>
                <?php
            }
            exit;
        } elseif (isset($_POST['whatsapp'])) {
            $whatsapp_msg = "Selected Patti Details:\n";
            foreach ($selected_pattis as $data) {
                $whatsapp_msg .= "Invoice No: " . htmlspecialchars($data['invoice_number'] ?? $data['id']) . "\n";
                $whatsapp_msg .= "Date: " . htmlspecialchars($data['purchase_date'] ?? '-') . "\n";
                $whatsapp_msg .= "Supplier: " . htmlspecialchars($data['supplier'] ?? '-') . "\n";
                if (isset($data['weight'])) $whatsapp_msg .= "Weight: " . htmlspecialchars($data['weight']) . "\n";
                if (isset($data['rate'])) $whatsapp_msg .= "Rate: " . htmlspecialchars($data['rate']) . "\n";
                $total = $data['total_amount'] ?? $data['net_amount'] ?? 0;
                if ($total) $whatsapp_msg .= "Total: " . htmlspecialchars($total) . "\n";
                $whatsapp_msg .= "----------------\n";
            }
            $encoded_msg = urlencode($whatsapp_msg);
            header("Location: https://wa.me/?text=" . $encoded_msg);
            exit;
        } else {
            // Preview in modal
            $preview_content = '';
            foreach ($selected_pattis as $data) {
                $preview_content .= "<div class='bill-box'>";
                $preview_content .= "<h2>Purchase Patti</h2>";
                $preview_content .= "<p><strong>Invoice No:</strong> " . htmlspecialchars($data['invoice_number'] ?? $data['id']) . "</p>";
                $preview_content .= "<p><strong>Date:</strong> " . htmlspecialchars($data['purchase_date'] ?? '-') . "</p>";
                $preview_content .= "<p><strong>Supplier:</strong> " . htmlspecialchars($data['supplier'] ?? '-') . "</p>";
                $preview_content .= "<table><tr>";
                if (isset($data['weight'])) $preview_content .= "<th>Weight</th>";
                if (isset($data['rate'])) $preview_content .= "<th>Rate</th>";
                if (isset($data['total_amount']) || isset($data['net_amount'])) $preview_content .= "<th>Total</th>";
                $preview_content .= "</tr><tr>";
                if (isset($data['weight'])) $preview_content .= "<td>" . htmlspecialchars($data['weight']) . "</td>";
                if (isset($data['rate'])) $preview_content .= "<td>" . htmlspecialchars($data['rate']) . "</td>";
                $total = $data['total_amount'] ?? $data['net_amount'] ?? 0;
                if ($total) $preview_content .= "<td>" . htmlspecialchars($total) . "</td>";
                $preview_content .= "</tr></table>";
                if ($total) $preview_content .= "<p class='total'>Grand Total: ₹" . number_format($total, 2) . "</p>";
                $preview_content .= "</div>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Patti Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .bill-box { border: 2px solid #333; padding: 20px; max-width: 700px; margin: auto; }
        h2 { text-align: center; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .total { font-weight: bold; text-align: right; }
        @media print {
            body { margin: 0; }
            button { display: none; }
        }
    </style>
</head>
<body class="bg-light">
<div class="container mt-4">
    <h2 class="mb-3 text-center">Patti Register</h2>

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
        <button class="btn btn-primary" id="previewBtn">Preview Selected</button>
        <button class="btn btn-success" id="printBtn">Print Selected</button>
        <button class="btn btn-secondary" id="selectAllBtn">Select All</button>
        <button class="btn btn-warning" id="deselectAllBtn">Deselect All</button>
        <button class="btn btn-success" id="whatsappBtn"><i class="www/whatsapp.com"></i> Send on WhatsApp</button>
    </div>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th><input type="checkbox" id="selectAllCheckbox"></th>
                <th>#</th>
                <th>Invoice</th>
                <th>Date</th>
                <th>Supplier</th>
                <?php if (isset($pattis[0]['weight'])): ?><th>Weight</th><?php endif; ?>
                <?php if (isset($pattis[0]['rate'])): ?><th>Rate</th><?php endif; ?>
                <?php if (isset($pattis[0]['total_amount'])): ?><th>Total</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pattis as $row): ?>
                <tr>
                    <td><input type="checkbox" class="bill-checkbox" name="selected_ids[]" value="<?= $row['id'] ?>"></td>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['invoice_number'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['purchase_date'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['supplier'] ?? '-') ?></td>
                    <?php if (isset($row['weight'])): ?><td><?= htmlspecialchars($row['weight']) ?></td><?php endif; ?>
                    <?php if (isset($row['rate'])): ?><td><?= htmlspecialchars($row['rate']) ?></td><?php endif; ?>
                    <?php if (isset($row['total_amount'])): ?><td><?= htmlspecialchars($row['total_amount']) ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Print Preview Modal -->
<div class="modal fade" id="printPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🖨 Print Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="previewContent"><?= $preview_content ?? '' ?></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="printNowBtn">Print Now</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#selectAllCheckbox').change(function() {
        $('.bill-checkbox').prop('checked', this.checked);
    });

    $('#selectAllBtn').click(function() {
        $('#selectAllCheckbox').prop('checked', true).change();
    });

    $('#deselectAllBtn').click(function() {
        $('#selectAllCheckbox').prop('checked', false).change();
    });

    $('#previewBtn').click(function() {
        let selectedIds = $('.bill-checkbox:checked').map(function() {
            return this.value;
        }).get();
        if (selectedIds.length === 0) {
            alert('Please select at least one bill.');
            return;
        }
        $.post('patti_register.php', { selected_ids: selectedIds, preview: true, start_date: $('[name="start_date"]').val(), end_date: $('[name="end_date"]').val() }, function(response) {
            $('#previewContent').html(response);
            $('#printPreviewModal').modal('show');
        });
    });

    $('#printBtn').click(function() {
        let selectedIds = $('.bill-checkbox:checked').map(function() {
            return this.value;
        }).get();
        if (selectedIds.length === 0) {
            alert('Please select at least one bill.');
            return;
        }
        $.post('patti_register.php', { selected_ids: selectedIds, print: true, start_date: $('[name="start_date"]').val(), end_date: $('[name="end_date"]').val() }, function(response) {
            window.print();
        });
    });

    $('#whatsappBtn').click(function() {
        let selectedIds = $('.bill-checkbox:checked').map(function() {
            return this.value;
        }).get();
        if (selectedIds.length === 0) {
            alert('Please select at least one bill.');
            return;
        }
        $.post('patti_register.php', { selected_ids: selectedIds, whatsapp: true, start_date: $('[name="start_date"]').val(), end_date: $('[name="end_date"]').val() }, function(response) {
            // Redirect handled by PHP
        });
    });

    $('#printNowBtn').click(function() {
        window.print();
    });

    // Submit filter form on date change
    $('[name="start_date"], [name="end_date"]').change(function() {
        $('#filterForm').submit();
    });
});
</script>
</body>
</html>