<?php
// customer_ledger.php
include 'db.php';
include 'header.php';

$customers = $conn->query("SELECT id, name FROM accounts WHERE type='customer'");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customer Ledger</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .ledger-modal .modal-dialog {
            max-width: 90%;
        }
        .ledger-modal .modal-content {
            border-radius: 15px;
            border: 3px solid #6f42c1;
            box-shadow: 0px 0px 20px rgba(0,0,0,0.3);
        }
        .ledger-modal .modal-header {
            background: #6f42c1;
            color: white;
            font-weight: bold;
        }
        .ledger-modal-table th, .ledger-modal-table td {
            text-align: center;
            vertical-align: middle;
        }
        .ledger-modal-table thead {
            background: #f8f9fa;
        }
        .ledger-modal-table tfoot {
            background: #f3e8ff;
            font-weight: bold;
        }
    </style>
</head>
<body class="p-4">
    <h2 class="mb-4">Customer Ledger</h2>

    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Customer Name</th>
                <th class="text-end">Balance</th>
                <th class="text-center">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($cust = $customers->fetch_assoc()): ?>
                <?php
                // Calculate balance from sales - receipts
                $stmt1 = $conn->prepare("SELECT COALESCE(SUM(total_amount),0) as total_sales FROM sales WHERE customer_id=?");
                $stmt1->bind_param("i", $cust['id']);
                $stmt1->execute();
                $sales_total = $stmt1->get_result()->fetch_assoc()['total_sales'];

                $stmt2 = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total_receipts FROM customer_receipts WHERE customer_id=?");
                $stmt2->bind_param("i", $cust['id']);
                $stmt2->execute();
                $receipts_total = $stmt2->get_result()->fetch_assoc()['total_receipts'];

                $balance = $sales_total - $receipts_total;
                ?>
                <tr>
                    <td><?= htmlspecialchars($cust['name']) ?></td>
                    <td class="text-end"><?= number_format($balance, 2) ?></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-primary view-ledger"
                                data-id="<?= $cust['id'] ?>"
                                data-name="<?= htmlspecialchars($cust['name']) ?>">
                            View Ledger
                        </button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Ledger Modal -->
    <div class="modal fade ledger-modal" id="ledgerModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ledger Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="ledgerContent">
                    <div class="text-center text-muted">Loading...</div>
                </div>
            </div>
        </div>
    </div>

<script>
$(document).ready(function(){
    $(".view-ledger").click(function(){
        let id = $(this).data("id");
        let name = $(this).data("name");
        $("#ledgerModal .modal-title").text("Ledger: " + name);
        $("#ledgerContent").html("<div class='text-center text-muted'>Loading...</div>");
        $("#ledgerModal").modal("show");

        $.get("customer_ledger.php", { ajax: 1, id: id }, function(data){
            $("#ledgerContent").html(data);
        });
    });
});
</script>

<?php
// ---------------------- AJAX HANDLER ----------------------
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $customer_id = intval($_GET['id']);
    $from = $_GET['from'] ?? '2000-01-01';
    $to   = $_GET['to'] ?? date('Y-m-d');

    // Fetch debits (sales)
    $stmt1 = $conn->prepare("SELECT total_amount AS amount, `date` AS date, CONCAT('Invoice #', invoice_number) AS description
                             FROM sales WHERE customer_id=? AND `date` BETWEEN ? AND ? ORDER BY `date` ASC");
    if (!$stmt1) { die("SQL Prepare Error (Sales): " . $conn->error); }
    $stmt1->bind_param("iss", $customer_id, $from, $to);
    $stmt1->execute();
    $sales = $stmt1->get_result();

    // Fetch credits (receipts)
    $stmt2 = $conn->prepare("SELECT amount, `date` AS date, description
                             FROM customer_receipts WHERE customer_id=? AND `date` BETWEEN ? AND ? ORDER BY `date` ASC");
    if (!$stmt2) { die("SQL Prepare Error (Receipts): " . $conn->error); }
    $stmt2->bind_param("iss", $customer_id, $from, $to);
    $stmt2->execute();
    $receipts = $stmt2->get_result();

    $total_debit = $total_credit = 0;
    ob_start();
    ?>
    <table class="table table-bordered ledger-modal-table">
        <thead>
            <tr class="table-secondary">
                <th colspan="3">Debit (Sales)</th>
                <th colspan="3">Credit (Receipts)</th>
            </tr>
            <tr>
                <th>Amount</th><th>Date</th><th>Description</th>
                <th>Amount</th><th>Date</th><th>Description</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $sales->fetch_assoc()): ?>
                <tr>
                    <td><?= number_format($row['amount'],2) ?></td>
                    <td><?= $row['date'] ?></td>
                    <td><?= htmlspecialchars($row['description']) ?></td>
                    <td></td><td></td><td></td>
                </tr>
                <?php $total_debit += $row['amount']; ?>
            <?php endwhile; ?>

            <?php while ($row = $receipts->fetch_assoc()): ?>
                <tr>
                    <td></td><td></td><td></td>
                    <td><?= number_format($row['amount'],2) ?></td>
                    <td><?= $row['date'] ?></td>
                    <td><?= htmlspecialchars($row['description']) ?></td>
                </tr>
                <?php $total_credit += $row['amount']; ?>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Total Debit: <?= number_format($total_debit,2) ?></td>
                <td colspan="3">Total Credit: <?= number_format($total_credit,2) ?></td>
            </tr>
            <tr>
                <td colspan="6" class="text-end">
                    Balance: <?= number_format($total_debit - $total_credit,2) ?>
                </td>
            </tr>
        </tfoot>
    </table>
    <?php
    echo ob_get_clean();
    exit;
}
?>
</body>
</html>
