<?php
// patti_register.php - FULL Patti Register + Preview + Print (Single File)
// ✅ Uses A5 print layout, no blank page, new JS logic but keeps old DB code style

ob_start();
include 'db.php';
include 'header.php';

// ---------- HELPER FUNCTION ----------
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// ---------- BUILD QUERY ----------
$columns = ["p.id", "p.invoice_number", "p.purchase_date", "a.name AS supplier"];
if (columnExists($conn, "purchases", "weight")) $columns[] = "p.weight";
if (columnExists($conn, "purchases", "rate")) $columns[] = "p.rate";
if (columnExists($conn, "purchases", "total_amount")) $columns[] = "p.total_amount";
elseif (columnExists($conn, "purchases", "net_amount")) $columns[] = "p.net_amount AS total_amount";

$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$sql = "SELECT " . implode(", ", $columns) . "
        FROM purchases p 
        JOIN accounts a ON p.supplier_id = a.id 
        ORDER BY p.id DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$pattis = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- HANDLE PREVIEW AJAX ----------
if (isset($_POST['ajax']) && $_POST['ajax'] === 'preview' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $stmt = $conn->prepare("SELECT p.*, a.name AS supplier FROM purchases p JOIN accounts a ON p.supplier_id = a.id WHERE p.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $patti = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$patti) {
        echo "<div class='alert alert-danger'>Patti not found!</div>";
        exit;
    }

    // ✅ Your old preview layout but cleaned & A5 ready
    ?>
    <div class="print-container">
        <h3 class="text-center fw-bold">Patti / Purchase Invoice</h3>
        <hr>
        <p><strong>Patti No:</strong> <?= htmlspecialchars($patti['invoice_number']) ?></p>
        <p><strong>Supplier:</strong> <?= htmlspecialchars($patti['supplier']) ?></p>
        <p><strong>Date:</strong> <?= htmlspecialchars($patti['purchase_date']) ?></p>

        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Weight</th>
                    <th>Rate</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= htmlspecialchars($patti['weight'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($patti['rate'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($patti['total_amount'] ?? '-') ?></td>
                </tr>
            </tbody>
        </table>

        <div class="total-section">
            <strong>Grand Total: <?= htmlspecialchars($patti['total_amount'] ?? '0') ?></strong>
        </div>
    </div>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Patti Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f9f9f9; padding: 20px; }
        .print-container {
            max-width: 700px;
            margin: auto;
            background: white;
            padding: 20px 30px;
            border: 1px solid #ddd;
            border-radius: 10px;
        }
        table { width: 100%; border-collapse: collapse; }
        table th, table td { border: 1px solid #ddd; padding: 6px; text-align: center; }
        table th { background: #f5f5f5; }
        .total-section { text-align: right; font-size: 18px; margin-top: 10px; }

        /* ✅ Print Styles */
        @media print {
            @page { size: A5; margin: 10mm; }
            body * { visibility: hidden; }
            #printArea, #printArea * { visibility: visible; }
            #printArea { position: absolute; left: 0; top: 0; width: 100%; }
            .modal, .modal-backdrop { display: none !important; }
        }
    </style>
</head>
<body>

<div class="container">
    <h2 class="fw-bold">Patti Register</h2>
    <table class="table table-hover">
        <thead class="table-dark">
            <tr>
                <th>#</th><th>Patti No</th><th>Supplier</th><th>Date</th><th>Total</th><th>Preview</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pattis as $row): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['invoice_number']) ?></td>
                <td><?= htmlspecialchars($row['supplier']) ?></td>
                <td><?= htmlspecialchars($row['purchase_date']) ?></td>
                <td><?= htmlspecialchars($row['total_amount'] ?? '-') ?></td>
                <td><button class="btn btn-sm btn-primary preview-btn" data-id="<?= $row['id'] ?>">Preview</button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ✅ Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">🖨 Patti Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="printArea"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success" id="printNowBtn">Print</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(function(){
    $('.preview-btn').click(function(){
        let id = $(this).data('id');
        $.post(window.location.href, {ajax:'preview', id:id}, function(data){
            $('#printArea').html(data);
            $('#previewModal').modal('show');
        });
    });

    $('#printNowBtn').click(function(){
        window.print();
    });
});
</script>
</body>
</html>
