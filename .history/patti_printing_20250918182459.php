<?php
// patti_register.php - Complete Patti Printing + Preview + Bulk Print

include 'db.php'; // ✅ Your database connection

// ---------- FETCH PATTI RECORDS ----------
// $stmt = $conn->prepare("SELECT p.id, p.invoice_number, p.purchase_date, a.name AS supplier, 
//                         p.weight, p.rate, p.total_amount 
//                         FROM purchases p 
//                         JOIN accounts a ON p.supplier_id = a.id 
//                         ORDER BY p.id DESC");
// $stmt->execute();
// $result = $stmt->get_result();
// $pattis = $result->fetch_all(MYSQLI_ASSOC);

// ---------- HANDLE SINGLE PRINT PREVIEW ----------
if (isset($_GET['print_id'])) {
    $id = intval($_GET['print_id']);
    $stmt = $conn->prepare("SELECT p.*, a.name AS supplier, i.name AS item
                            FROM purchases p
                            JOIN accounts a ON p.supplier_id = a.id
                            JOIN items i ON p.item_id = i.sku
                            WHERE p.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (!$data) {
        die("<h3 style='color:red;text-align:center;'>Invalid Patti ID</h3>");
    }

    // ---------- PRINT-FRIENDLY OUTPUT ----------
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Patti #<?= htmlspecialchars($data['invoice_number']) ?></title>
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
            <p><strong>Invoice No:</strong> <?= htmlspecialchars($data['invoice_number']) ?></p>
            <p><strong>Date:</strong> <?= htmlspecialchars($data['purchase_date']) ?></p>
            <p><strong>Supplier:</strong> <?= htmlspecialchars($data['supplier']) ?></p>

            <table>
                <tr><th>Item</th><th>Weight</th><th>Rate</th><th>Total</th></tr>
                <tr>
                    <td><?= htmlspecialchars($data['item']) ?></td>
                    <td><?= htmlspecialchars($data['weight']) ?></td>
                    <td><?= htmlspecialchars($data['rate']) ?></td>
                    <td><?= htmlspecialchars($data['total_amount']) ?></td>
                </tr>
            </table>
            <p class="total">Grand Total: ₹<?= number_format($data['total_amount'], 2) ?></p>

            <div style="text-align:center; margin-top:15px;">
                <button onclick="window.print()">🖨 Print</button>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ---------- MAIN LIST + PRINT PREVIEW ----------
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
                <th>Weight</th>
                <th>Rate</th>
                <th>Total</th>
                <th>Print</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pattis as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['invoice_number']) ?></td>
                    <td><?= htmlspecialchars($row['purchase_date']) ?></td>
                    <td><?= htmlspecialchars($row['supplier']) ?></td>
                    <td><?= htmlspecialchars($row['weight']) ?></td>
                    <td><?= htmlspecialchars($row['rate']) ?></td>
                    <td><?= htmlspecialchars($row['total_amount']) ?></td>
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
