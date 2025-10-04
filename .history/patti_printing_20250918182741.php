<?php
// patti_register.php - Complete Patti Printing + Preview + Bulk Print

include 'db.php'; // ✅ Your database connection

// ---------- HELPER FUNCTION TO CHECK COLUMNS ----------
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// ---------- BUILD SAFE SELECT QUERY ----------
$columns = ["p.id", "p.invoice_number", "p.purchase_date", "a.name AS supplier"];

// check if columns exist before adding
if (columnExists($conn, "purchases", "weight")) $columns[] = "p.weight";
if (columnExists($conn, "purchases", "rate")) $columns[] = "p.rate";
if (columnExists($conn, "purchases", "total_amount")) $columns[] = "p.total_amount";
elseif (columnExists($conn, "purchases", "net_amount")) $columns[] = "p.net_amount AS total_amount";

$sql = "SELECT " . implode(", ", $columns) . " 
        FROM purchases p 
        JOIN accounts a ON p.supplier_id = a.id 
        ORDER BY p.id DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("<h3 style='color:red;text-align:center;'>SQL Error: " . $conn->error . "</h3>");
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

?>
<!DOCTYPE html>
<html>
<head>
    <title>Patti Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-light">

<div class="container mt-4">
    <h2 class="mb-3 text-center">Patti Register</h2>

    <button class="btn btn-success mb-3" id="printAllBtn">🖨 Print All Patti</button>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Invoice</th>
                <th>Date</th>
                <th>Supplier</th>
                <?php if (isset($pattis[0]['weight'])): ?><th>Weight</th><?php endif; ?>
                <?php if (isset($pattis[0]['rate'])): ?><th>Rate</th><?php endif; ?>
                <?php if (isset($pattis[0]['total_amount'])): ?><th>Total</th><?php endif; ?>
                <th>Print</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pattis as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['invoice_number'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['purchase_date'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['supplier'] ?? '-') ?></td>
                    <?php if (isset($row['weight'])): ?><td><?= htmlspecialchars($row['weight']) ?></td><?php endif; ?>
                    <?php if (isset($row['rate'])): ?><td><?= htmlspecialchars($row['rate']) ?></td><?php endif; ?>
                    <?php if (isset($row['total_amount'])): ?><td><?= htmlspecialchars($row['total_amount']) ?></td><?php endif; ?>
                    <td>
                        <button class="btn btn-sm btn-outline-primary print-preview-btn"
                                data-id="<?= $row['id'] ?>">🖨 Print</button>
                    </td>
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
        <iframe id="printPreviewFrame" src="" style="width:100%; height:600px;" frameborder="0"></iframe>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" id="printNowBtn">Print Now</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).on('click', '.print-preview-btn', function () {
    let billId = $(this).data('id');
    let previewUrl = 'patti_register.php?print_id=' + billId;
    $('#printPreviewFrame').attr('src', previewUrl);
    $('#printPreviewModal').modal('show');
});

$('#printNowBtn').click(function () {
    document.getElementById('printPreviewFrame').contentWindow.print();
});

$('#printAllBtn').click(function () {
    let printUrls = [];
    $('table tbody tr').each(function () {
        let billId = $(this).find('.print-preview-btn').data('id');
        if (billId) printUrls.push('patti_register.php?print_id=' + billId);
    });

    if (printUrls.length === 0) {
        alert('No bills found to print.');
        return;
    }

    printUrls.forEach(url => {
        let w = window.open(url, '_blank');
        w.onload = () => w.print();
    });
});
</script>

</body>
</html>
