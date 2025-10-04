<?php
// customer_ledger.php - Customer Ledger (Single File Version)
include 'db.php';
include 'header.php';

// --- Handle AJAX request for ledger details ---
if (isset($_GET['action']) && $_GET['action'] === 'fetch_ledger') {
    $customer_id = intval($_GET['customer_id']);
    $from = $_GET['from'];
    $to = $_GET['to'];

    // Get Customer Name
    $cust = $conn->query("SELECT name FROM accounts WHERE id=$customer_id")->fetch_assoc();
    $customer_name = $cust['name'] ?? 'Unknown';

    $sales = $conn->query("
        SELECT id, invoice_number, sale_date, total_amount
        FROM sales
        WHERE customer_id=$customer_id AND sale_date BETWEEN '$from' AND '$to'
        ORDER BY sale_date
    ");

    $receipts = $conn->query("
        SELECT id, receipt_date, amount, description
        FROM customer_receipts
        WHERE customer_id=$customer_id AND receipt_date BETWEEN '$from' AND '$to'
        ORDER BY receipt_date
    ");

    $total_debit = 0;
    $total_credit = 0;
    $entries = [];

    while ($r = $receipts->fetch_assoc()) {
        $entries[] = ['type' => 'credit', 'amount' => $r['amount'], 'date' => $r['receipt_date'], 'desc' => $r['description']];
        $total_credit += $r['amount'];
    }
    while ($s = $sales->fetch_assoc()) {
        $entries[] = ['type' => 'debit', 'amount' => $s['total_amount'], 'date' => $s['sale_date'], 'desc' => "Invoice #{$s['invoice_number']}"];
        $total_debit += $s['total_amount'];
    }

    usort($entries, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));

    ?>
    <div class="modal-content p-3" style="background:#f7f7f7; border-radius:12px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 style="color:#0d6efd;">📒 Ledger for <?= htmlspecialchars($customer_name) ?></h4>
            <button class="btn btn-danger btn-sm" onclick="document.getElementById('ledgerModal').style.display='none'">Close ✖</button>
        </div>

        <table class="table table-bordered table-hover">
            <thead style="background:#e9ecef;">
                <tr>
                    <th style="width:40%; color:green;">Credit (Receipts)</th>
                    <th style="width:15%;">Date</th>
                    <th style="width:40%; color:red;">Debit (Invoices)</th>
                    <th style="width:15%;">Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($entries as $e): ?>
                <tr>
                    <?php if ($e['type'] === 'credit'): ?>
                        <td style="color:green; font-weight:bold;"><?= number_format($e['amount'], 2) ?> (<?= htmlspecialchars($e['desc']) ?>)</td>
                        <td><?= $e['date'] ?></td>
                        <td></td><td></td>
                    <?php else: ?>
                        <td></td><td></td>
                        <td style="color:red; font-weight:bold;"><?= number_format($e['amount'], 2) ?> (<?= htmlspecialchars($e['desc']) ?>)</td>
                        <td><?= $e['date'] ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="alert alert-info">
            <strong>Total Credit:</strong> <?= number_format($total_credit, 2) ?><br>
            <strong>Total Debit:</strong> <?= number_format($total_debit, 2) ?><br>
            <strong>Balance:</strong> <?= number_format($total_debit - $total_credit, 2) ?>
        </div>
    </div>
    <?php
    exit;
}

// --- Default page load ---
$customers = $conn->query("
    SELECT a.id, a.name,
    (
      COALESCE((SELECT SUM(total_amount) FROM sales WHERE customer_id=a.id), 0) -
      COALESCE((SELECT SUM(amount) FROM customer_receipts WHERE customer_id=a.id), 0)
    ) AS balance
    FROM accounts a
    WHERE a.type='customer'
    ORDER BY a.name ASC
");
?>

<h2>Customer Ledger</h2>

<table class="table table-bordered table-hover" id="customerTable">
    <thead>
        <tr>
            <th>Customer Name</th>
            <th>Balance</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $customers->fetch_assoc()) { ?>
            <tr data-id="<?= $row['id'] ?>" style="cursor:pointer;">
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= number_format($row['balance'], 2) ?></td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<!-- Date Picker Modal -->
<div class="modal" id="dateModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content p-3">
      <h5>Select Date Range</h5>
      <label>From Date</label>
      <input type="date" id="fromDate" class="form-control mb-2">
      <label>To Date</label>
      <input type="date" id="toDate" class="form-control mb-3">
      <button class="btn btn-primary w-100" id="viewLedgerBtn">OK</button>
    </div>
  </div>
</div>

<!-- Ledger Modal -->
<div id="ledgerModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; padding:20px; overflow:auto;">
    <div id="ledgerResult" style="max-width:900px; margin:auto;"></div>
</div>

<script>
let selectedCustomer = null;

document.querySelectorAll("#customerTable tbody tr").forEach(row => {
    row.addEventListener("dblclick", () => {
        selectedCustomer = row.dataset.id;
        new bootstrap.Modal(document.getElementById('dateModal')).show();
    });
});

document.getElementById("viewLedgerBtn").addEventListener("click", () => {
    let fromDate = document.getElementById("fromDate").value;
    let toDate = document.getElementById("toDate").value;
    if (!fromDate || !toDate) {
        alert("Please select both dates.");
        return;
    }

    fetch(`customer_ledger.php?action=fetch_ledger&customer_id=${selectedCustomer}&from=${fromDate}&to=${toDate}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById("ledgerResult").innerHTML = html;
            document.getElementById("ledgerModal").style.display = 'block';
            bootstrap.Modal.getInstance(document.getElementById('dateModal')).hide();
        });
});
</script>

<?php include 'footer.php'; ?>
