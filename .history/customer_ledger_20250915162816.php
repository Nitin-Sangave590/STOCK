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

    echo "<h3>Ledger for {$customer_name} ({$from} to {$to})</h3>";

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

    echo "<table class='table table-striped'>
    <thead><tr>
        <th>Credit (Receipts)</th><th>Date</th>
        <th>Debit (Invoices)</th><th>Date</th>
    </tr></thead><tbody>";

    // Build a combined array to sort by date (so credit/debit show in order)
    $entries = [];
    while ($r = $receipts->fetch_assoc()) {
        $entries[] = ['type' => 'credit', 'amount' => $r['amount'], 'date' => $r['receipt_date'], 'desc' => $r['description']];
        $total_credit += $r['amount'];
    }
    while ($s = $sales->fetch_assoc()) {
        $entries[] = ['type' => 'debit', 'amount' => $s['total_amount'], 'date' => $s['sale_date'], 'desc' => "Invoice #{$s['invoice_number']}"];
        $total_debit += $s['total_amount'];
    }

    // Sort by date
    usort($entries, function($a, $b) {
        return strtotime($a['date']) <=> strtotime($b['date']);
    });

    foreach ($entries as $e) {
        if ($e['type'] === 'credit') {
            echo "<tr><td>{$e['amount']} ({$e['desc']})</td><td>{$e['date']}</td><td></td><td></td></tr>";
        } else {
            echo "<tr><td></td><td></td><td>{$e['amount']} ({$e['desc']})</td><td>{$e['date']}</td></tr>";
        }
    }

    echo "</tbody></table>";

    $balance = $total_debit - $total_credit;
    echo "<div class='alert alert-info'>
    <strong>Total Credit:</strong> $total_credit<br>
    <strong>Total Debit:</strong> $total_debit<br>
    <strong>Balance:</strong> $balance
    </div>";
    exit; // stop further HTML rendering for AJAX request
}

// --- Default page load (Customer List) ---
$customers = $conn->query("
    SELECT a.id, a.name,
        IFNULL(SUM(s.total_amount),0) - IFNULL(SUM(r.amount),0) AS balance
    FROM accounts a
    LEFT JOIN sales s ON s.customer_id = a.id
    LEFT JOIN customer_receipts r ON r.customer_id = a.id
    WHERE a.type='customer'
    GROUP BY a.id, a.name
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
            <tr data-id="<?php echo $row['id']; ?>">
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo number_format($row['balance'], 2); ?></td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<!-- Modal for Date Range -->
<div class="modal" id="dateModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Select Date Range</h5>
      </div>
      <div class="modal-body">
        <label>From Date</label>
        <input type="date" id="fromDate" class="form-control">
        <label>To Date</label>
        <input type="date" id="toDate" class="form-control">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="viewLedgerBtn">OK</button>
      </div>
    </div>
  </div>
</div>

<div id="ledgerResult"></div>

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
            bootstrap.Modal.getInstance(document.getElementById('dateModal')).hide();
        });
});
</script>

<?php include 'footer.php'; ?>
