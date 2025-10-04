<?php
// patti_register.php - Patti Register with Multi-Select + Print Preview + Print Selected

include 'db.php';

// ---------- HELPER FUNCTION ----------
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// ---------- BUILD SAFE QUERY ----------
$columns = ["p.id", "p.invoice_number", "p.purchase_date", "a.name AS supplier"];
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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Patti Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        /* ✅ Image-style checkbox */
        .select-checkbox {
            width: 22px;
            height: 22px;
            cursor: pointer;
            accent-color: #198754; /* Bootstrap green */
            transform: scale(1.3);
        }
    </style>
</head>
<body class="bg-light">

<div class="container mt-4">
    <h2 class="mb-3 text-center">Patti Register</h2>

    <table class="table table-bordered table-striped align-middle">
        <thead class="table-dark">
            <tr>
                <th><input type="checkbox" id="selectAll" class="select-checkbox"></th>
                <th>#</th>
                <th>Invoice</th>
                <th>Date</th>
                <th>Supplier</th>
                <?php if (isset($pattis[0]['weight'])): ?><th>Weight</th><?php endif; ?>
                <?php if (isset($pattis[0]['rate'])): ?><th>Rate</th><?php endif; ?>
                <?php if (isset($pattis[0]['total_amount'])): ?><th>Total</th><?php endif; ?>
                <th>Preview</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pattis as $row): ?>
                <tr>
                    <td class="text-center">
                        <input type="checkbox" class="select-checkbox patti-checkbox" data-id="<?= $row['id'] ?>">
                    </td>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['invoice_number'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['purchase_date'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['supplier'] ?? '-') ?></td>
                    <?php if (isset($row['weight'])): ?><td><?= htmlspecialchars($row['weight']) ?></td><?php endif; ?>
                    <?php if (isset($row['rate'])): ?><td><?= htmlspecialchars($row['rate']) ?></td><?php endif; ?>
                    <?php if (isset($row['total_amount'])): ?><td><?= htmlspecialchars($row['total_amount']) ?></td><?php endif; ?>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary print-preview-btn"
                                data-id="<?= $row['id'] ?>">🖨 Preview</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- ✅ Print Selected Button -->
    <div class="text-center mt-3">
        <button class="btn btn-success" id="printSelectedBtn">🖨 Print Selected</button>
    </div>
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

// ✅ Select All Checkbox
$('#selectAll').on('change', function () {
    $('.patti-checkbox').prop('checked', $(this).prop('checked'));
});

// ✅ Print Selected
$('#printSelectedBtn').click(function () {
    let selectedIds = [];
    $('.patti-checkbox:checked').each(function () {
        selectedIds.push($(this).data('id'));
    });

    if (selectedIds.length === 0) {
        alert('Please select at least one Patti to print.');
        return;
    }

    selectedIds.forEach(id => {
        let url = 'patti_register.php?print_id=' + id;
        let w = window.open(url, '_blank');
        w.onload = () => w.print();
    });
});
</script>

</body>
</html>
