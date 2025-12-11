<?php
include 'db.php'; // Include database connection

// ---------- Helper: Find which column exists ----------
function findColumn($conn, $table, $candidates) {
    foreach ($candidates as $c) {
        $col = $conn->real_escape_string($c);
        $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
        if ($res && $res->num_rows > 0) return $c;
    }
    return null;
}

// ---------- AJAX: Return ledger table only ----------
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $customer_id = intval($_GET['id'] ?? 0);
    $from = $_GET['from'] ?? '2000-01-01';
    $to = $_GET['to'] ?? date('Y-m-d');

    // Detect sales date column
    $sales_date_col = findColumn($conn, 'sales', ['date', 'sale_date', 'created_at', 'invoice_date']);
    if (!$sales_date_col) {
        die("No date-like column found in sales table. Run: DESCRIBE sales;");
    }

    // Detect receipts table date & description column names
    $receipts_table = 'customer_receipts';
    $tblExists = $conn->query("SHOW TABLES LIKE '{$receipts_table}'");
    if (!$tblExists || $tblExists->num_rows == 0) {
        die("Receipts table '{$receipts_table}' not found in database.");
    }

    $receipts_date_col = findColumn($conn, $receipts_table, ['date', 'receipt_date', 'created_at', 'payment_date']);
    if (!$receipts_date_col) {
        die("No date-like column found in {$receipts_table}.");
    }

    $receipts_desc_col = findColumn($conn, $receipts_table, ['description', 'desc', 'narration', 'remarks', 'note']);
    if (!$receipts_desc_col) {
        $receipts_desc_col = null;
    }

    // Prepare sales query
    $sales_sql = "SELECT invoice_number, bill_no, total_amount AS amount, `{$sales_date_col}` AS date, hamali, freight
                  FROM sales
                  WHERE customer_id = ? AND `{$sales_date_col}` BETWEEN ? AND ?
                  ORDER BY `{$sales_date_col}` ASC";
    $stmt1 = $conn->prepare($sales_sql);
    if (!$stmt1) {
        die("SQL Prepare Error (Sales): " . $conn->error);
    }
    $stmt1->bind_param("iss", $customer_id, $from, $to);
    $stmt1->execute();
    $sales = $stmt1->get_result();

    // Prepare receipts query
    $desc_sel = $receipts_desc_col ? "`{$receipts_desc_col}` AS description" : "'' AS description";
    $receipts_sql = "SELECT amount, `{$receipts_date_col}` AS date, {$desc_sel}
                     FROM `{$receipts_table}`
                     WHERE customer_id = ? AND `{$receipts_date_col}` BETWEEN ? AND ?
                     ORDER BY `{$receipts_date_col}` ASC";
    $stmt2 = $conn->prepare($receipts_sql);
    if (!$stmt2) {
        die("SQL Prepare Error (Receipts): " . $conn->error);
    }
    $stmt2->bind_param("iss", $customer_id, $from, $to);
    $stmt2->execute();
    $receipts = $stmt2->get_result();

    // Merge entries
    $entries = [];
    while ($r = $sales->fetch_assoc()) {
        $entries[] = [
            'type' => 'debit',
            'date' => $r['date'],
            'bill_no' => $r['bill_no'] ?? '',
            'invoice' => $r['invoice_number'] ?? '',
            'amount' => floatval($r['amount']),
            'desc' => '',
            'extra' => ['hamali' => $r['hamali'] ?? 0, 'freight' => $r['freight'] ?? 0]
        ];
    }
    while ($r = $receipts->fetch_assoc()) {
        $entries[] = [
            'type' => 'credit',
            'date' => $r['date'],
            'bill_no' => '',
            'invoice' => '',
            'amount' => floatval($r['amount']),
            'desc' => $r['description'] ?? ''
        ];
    }

    // Sort by date ascending
    usort($entries, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));

    // Build response HTML
    $total_debit = 0.0;
    $total_credit = 0.0;

    ob_start();
    ?>
    <div style="padding:8px;">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div><strong>Period:</strong> <?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?></div>
        <div>
          <button class="btn btn-sm btn-secondary" onclick="document.getElementById('ledgerModal').querySelector('.btn-close').click();">Close</button>
        </div>
      </div>

      <table class="table table-bordered" style="background:white;">
        <thead class="table-light text-center">
          <tr>
            <th colspan="3">Debit (Bills)</th>
            <th colspan="3">Credit (Receipts)</th>
          </tr>
          <tr class="text-center">
            <th style="width:14%">Date</th>
            <th style="width:22%">Invoice</th>
            <th style="width:12%">Amount</th>
            <th style="width:14%">Date</th>
            <th style="width:12%">Description</th>
            <th style="width:8%">Amount</th>
          </tr>
        </thead>
        <tbody>
        <?php if (count($entries) === 0): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No transactions in this period.</td></tr>
        <?php else: ?>
            <?php foreach ($entries as $e):
                if ($e['type'] === 'debit') :
                    $total_debit += $e['amount']; ?>
                    <tr>
                        <td class="text-center"><?= htmlspecialchars($e['date']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($e['invoice']) ?></td>
                        <td class="text-end"><?= number_format($e['amount'], 2) ?></td>
                        <td></td><td></td><td></td>
                    </tr>
                <?php else:
                    $total_credit += $e['amount']; ?>
                    <tr>
                        <td></td><td></td><td></td>
                        <td class="text-center"><?= htmlspecialchars($e['date']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($e['desc']) ?></td>
                        <td class="text-end"><?= number_format($e['amount'], 2) ?></td>
                    </tr>
                <?php endif;
            endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
          <tr style="background:#f7f7f9;">
            <td colspan="3" class="text-start"><strong>Total Debit:</strong> <?= number_format($total_debit, 2) ?></td>
            <td colspan="2" class="text-end"><strong>Total Credit:</strong></td>
            <td class="text-end"><strong><?= number_format($total_credit, 2) ?></strong></td>
          </tr>
          <tr>
            <td colspan="6" class="text-end"><strong>Closing Balance: <?= number_format($total_debit - $total_credit, 2) ?></strong></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php
    echo ob_get_clean();
    exit;
}

// ---------- NORMAL PAGE (with header.php) ----------
include 'header.php'; // Only included when NOT AJAX

// Fetch customers
$customers = $conn->query("SELECT id, name FROM accounts WHERE type='customer' ORDER BY name ASC");
if (!$customers) {
    die("SQL Error fetching customers: " . $conn->error);
}

// Fetch total sales and receipts for all customers
$sales_sums = [];
$receipt_sums = [];
$customer_ids = [];
while ($c = $customers->fetch_assoc()) {
    $customer_ids[] = $c['id'];
}
$customers->data_seek(0); // Reset cursor for later use

if (!empty($customer_ids)) {
    $ids_str = implode(',', array_map('intval', $customer_ids));

    // Get total sales per customer
    $sql = "SELECT customer_id, COALESCE(SUM(total_amount), 0) AS total_sales 
            FROM sales 
            WHERE customer_id IN ({$ids_str}) 
            GROUP BY customer_id";
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $sales_sums[$r['customer_id']] = floatval($r['total_sales']);
        }
    }

    // Get total receipts per customer
    $sql = "SELECT customer_id, COALESCE(SUM(amount), 0) AS total_receipts 
            FROM customer_receipts 
            WHERE customer_id IN ({$ids_str}) 
            GROUP BY customer_id";
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $receipt_sums[$r['customer_id']] = floatval($r['total_receipts']);
        }
    }
}

// Calculate total combined balance of all customers
$total_all_balance = 0.0;
$customer_balances = [];
while ($c = $customers->fetch_assoc()) {
    $cid = (int)$c['id'];
    $total_sales = $sales_sums[$cid] ?? 0.0;
    $total_receipts = $receipt_sums[$cid] ?? 0.0;
    $balance = $total_sales - $total_receipts;
    $customer_balances[$cid] = $balance;
    $total_all_balance += $balance;
}
$customers->data_seek(0); // Reset cursor for HTML rendering
?>

<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Customer Ledger</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f2f4f7; padding:20px; }
    .ledger-table { background:white; border-radius:8px; box-shadow:0 6px 18px rgba(0,0,0,0.06); }
    .modal-ledger .modal-content { border-radius:14px; border:3px solid #6f42c1; }
    .modal-ledger .modal-header { background:#6f42c1; color:white; border-top-left-radius:11px; border-top-right-radius:11px; }
    .modal-legend { font-weight:600; color:#6f42c1; }

    #supp_total{
      margin-right: 27%;
      margin-top:2%
    }



  </style>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
  <h2 class="mb-4">Customer Ledger</h2>

  <div class="table-responsive ledger-table p-3">
    <table class="table table-hover mb-0">
      <thead class="table-dark">
        <tr>
          <th>Customer Name</th>
          <th class="text-end">Balance</th>
          <th class="text-center">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($c = $customers->fetch_assoc()):
            $cid = (int)$c['id'];
            $balance = $customer_balances[$cid] ?? 0.0;
        ?>
          <tr>
            <td><?= htmlspecialchars($c['name']) ?></td>
            <td class="text-end <?= $balance >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($balance, 2) ?></td>
            <td class="text-center">
              <button class="btn btn-sm btn-primary open-ledger" data-id="<?= $cid ?>" data-name="<?= htmlspecialchars($c['name']) ?>">
                View Ledger
              </button>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
    <div class="text-end fs- fw-bold" id="supp_total">                            
      Total Balance :   
      <span class="<?= $total_all_balance >= 0 ? 'text-success' : 'text-danger' ?>">
        ₹<?= number_format($total_all_balance, 2) ?>
      </span>
    </div>
  </div>

  <!-- Modal -->
  <div class="modal fade modal-ledger" id="ledgerModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Ledger</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="d-flex gap-2 align-items-center mb-3">
            <div class="modal-legend">Customer:</div>
            <div id="ledgerCustomerName" style="font-weight:700"></div>
            <div class="ms-auto d-flex gap-2 align-items-center">
              <label class="mb-0">From</label>
              <input id="fromDate" type="date" class="form-control form-control-sm" />
              <label class="mb-0">To</label>
              <input id="toDate" type="date" class="form-control form-control-sm" />
              <button id="loadLedger" class="btn btn-sm btn-success">Load Ledger</button>
            </div>
          </div>
          <div id="ledgerContent" style="min-height:200px">
            <div class="text-center text-muted">Select a customer and click "Load Ledger".</div>
          </div>
        </div>
      </div>
    </div>
  </div>

<script>
(function(){
  const today = new Date().toISOString().slice(0,10);
  const yearStart = new Date(new Date().getFullYear(),0,1).toISOString().slice(0,10);
  $('#fromDate').val(yearStart);
  $('#toDate').val(today);

  let currentCustomerId = null;

  $('.open-ledger').on('click', function(){
    currentCustomerId = $(this).data('id');
    const name = $(this).data('name');
    $('#ledgerCustomerName').text(name);
    $('#ledgerContent').html('<div class="text-center text-muted">Select dates and click Load Ledger.</div>');
    $('#ledgerModal').modal('show');
  });

  $('#loadLedger').on('click', function(){
    if (!currentCustomerId) return;
    const from = $('#fromDate').val();
    const to = $('#toDate').val();
    if (!from || !to) { alert('Please select From and To dates'); return; }
    $('#ledgerContent').html('<div class="text-center text-muted">Loading...</div>');
    $.get(window.location.pathname, { ajax: 1, id: currentCustomerId, from: from, to: to }, function(html){
      $('#ledgerContent').html(html);
    }).fail(function(xhr){
      $('#ledgerContent').html('<div class="text-danger">Error loading ledger: ' + xhr.responseText + '</div>');
    });
  });
})();
</script>
</body>
</html>