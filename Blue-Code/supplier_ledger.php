<?php
// ledger.php - Supplier Ledger
include 'db.php'; // Database connection

// Helper: Find which column exists in a table
function findColumn($conn, $table, $candidates) {
    foreach ($candidates as $c) {
        $col = $conn->real_escape_string($c);
        $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
        if ($res && $res->num_rows > 0) return $c;
    }
    return null;
}

// AJAX: Return supplier ledger table only
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $supplier_id = intval($_GET['id'] ?? 0);
    $from = $_GET['from'] ?? '2000-01-01';
    $to = $_GET['to'] ?? date('Y-m-d');

    // Detect purchase date column
    $purchase_date_col = findColumn($conn, 'purchases', ['date', 'purchase_date', 'created_at', 'invoice_date']);
    if (!$purchase_date_col) {
        die("No date-like column found in purchases table. Run: DESCRIBE purchases;");
    }

    // Detect supplier payments table date & description column names
    $payments_table = 'supplier_payments';
    $tblExists = $conn->query("SHOW TABLES LIKE '{$payments_table}'");
    if (!$tblExists || $tblExists->num_rows == 0) {
        die("Payments table '{$payments_table}' not found in database.");
    }

    $payments_date_col = findColumn($conn, $payments_table, ['date', 'payment_date', 'created_at']);
    if (!$payments_date_col) {
        die("No date-like column found in {$payments_table}.");
    }

    $payments_desc_col = findColumn($conn, $payments_table, ['description', 'desc', 'narration', 'remarks', 'note']);
    if (!$payments_desc_col) {
        $payments_desc_col = null;
    }

    // Prepare purchases query (Debit)
    $purchases_sql = "SELECT id, invoice_number, total_amount AS amount, `{$purchase_date_col}` AS date, hamali, freight, uchal
                      FROM purchases
                      WHERE supplier_id = ? AND `{$purchase_date_col}` BETWEEN ? AND ?
                      ORDER BY `{$purchase_date_col}` ASC";
    $stmt1 = $conn->prepare($purchases_sql);
    if (!$stmt1) {
        die("SQL Prepare Error (Purchases): " . $conn->error);
    }
    $stmt1->bind_param("iss", $supplier_id, $from, $to);
    $stmt1->execute();
    $purchases = $stmt1->get_result();
    $stmt1->close();

    // Prepare payments query (Credit)
    $desc_sel = $payments_desc_col ? "`{$payments_desc_col}` AS description" : "'' AS description";
    $payments_sql = "SELECT sp.amount, sp.`{$payments_date_col}` AS date, {$desc_sel}, sp.purchase_id, p.invoice_number
                     FROM `{$payments_table}` sp
                     LEFT JOIN purchases p ON sp.purchase_id = p.id
                     WHERE sp.supplier_id = ? AND sp.`{$payments_date_col}` BETWEEN ? AND ?
                     ORDER BY sp.`{$payments_date_col}` ASC";
    $stmt2 = $conn->prepare($payments_sql);
    if (!$stmt2) {
        die("SQL Prepare Error (Payments): " . $conn->error);
    }
    $stmt2->bind_param("iss", $supplier_id, $from, $to);
    $stmt2->execute();
    $payments = $stmt2->get_result();
    $stmt2->close();

    // Merge entries
    $entries = [];
    while ($r = $purchases->fetch_assoc()) {
        $entries[] = [
            'type' => 'debit',
            'date' => $r['date'],
            'invoice' => $r['invoice_number'] ?? '',
            'amount' => floatval($r['amount']),
            'desc' => '',
            'purchase_id' => $r['id'],
            'extra' => ['hamali' => $r['hamali'] ?? 0, 'freight' => $r['freight'] ?? 0, 'uchal' => $r['uchal'] ?? 0]
        ];
    }
    while ($r = $payments->fetch_assoc()) {
        $entries[] = [
            'type' => 'credit',
            'date' => $r['date'],
            'invoice' => $r['purchase_id'] ? ($r['invoice_number'] ? "#{$r['invoice_number']}" : "Purchase #{$r['purchase_id']}") : '',
            'amount' => floatval($r['amount']),
            'desc' => $r['description'] ?? '',
            'purchase_id' => $r['purchase_id']
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
            <th colspan="3">Debit (Purchases)</th>
            <th colspan="3">Credit (Payments)</th>
          </tr>
          <tr class="text-center">
            <th style="width:14%">Date</th>
            <th style="width:22%">Invoice</th>
            <th style="width:12%">Amount</th>
            <th style="width:14%">Date</th>
            <th style="width:22%">Description</th>
            <th style="width:12%">Amount</th>
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
                        <td class="text-center"><?= htmlspecialchars($e['desc']) . ($e['invoice'] ? ' (Linked to ' . htmlspecialchars($e['invoice']) . ')' : '') ?></td>
                        <td class="text-end"><?= number_format($e['amount'], 2) ?></td>
                    </tr>
                <?php endif;
            endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
          <tr style="background:#f7f7f9;">
            <td colspan="3" class="text-start"><strong>Total Debit:</strong> ₹<?= number_format($total_debit, 2) ?></td>
            <td colspan="2" class="text-end"><strong>Total Credit:</strong></td>
            <td class="text-end"><strong>₹<?= number_format($total_credit, 2) ?></strong></td>
          </tr>
          <tr>
            <td colspan="6" class="text-end"><strong>Closing Balance: ₹<?= number_format($total_debit - $total_credit, 2) ?></strong></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php
    echo ob_get_clean();
    exit;
}

// NORMAL PAGE (with header.php)
include 'header.php';

// Fetch all suppliers
$suppliers = $conn->query("SELECT id, name FROM accounts WHERE type='supplier' ORDER BY name ASC");
if (!$suppliers) {
    die("SQL Error fetching suppliers: " . $conn->error);
}

// Instead of querying sums for each supplier inside loop (which is N+1 query problem),
// let's prefetch sums for all suppliers at once using group by queries.

$supplier_ids = [];
while ($row = $suppliers->fetch_assoc()) {
    $supplier_ids[] = (int)$row['id'];
    $suppliers_list[] = $row;
}

$ids_str = implode(',', $supplier_ids);

// Get total purchases per supplier
$purchase_sums = [];
if (!empty($ids_str)) {
    $sql = "SELECT supplier_id, COALESCE(SUM(total_amount), 0) AS total_purchase FROM purchases WHERE supplier_id IN ({$ids_str}) GROUP BY supplier_id";
    $res = $conn->query($sql);
    while ($r = $res->fetch_assoc()) {
        $purchase_sums[$r['supplier_id']] = floatval($r['total_purchase']);
    }

    // Get total payments per supplier
    $sql2 = "SELECT supplier_id, COALESCE(SUM(amount), 0) AS total_payment FROM supplier_payments WHERE supplier_id IN ({$ids_str}) GROUP BY supplier_id";
    $res2 = $conn->query($sql2);
    while ($r = $res2->fetch_assoc()) {
        $payment_sums[$r['supplier_id']] = floatval($r['total_payment']);
    }
} else {
    $suppliers_list = [];
    $purchase_sums = [];
    $payment_sums = [];
}

// Calculate total balance of all suppliers combined
$total_all_balance = 0.0;
foreach ($suppliers_list as $supplier) {
    $sid = (int)$supplier['id'];
    $total_purchases = $purchase_sums[$sid] ?? 0.0;
    $total_payments = $payment_sums[$sid] ?? 0.0;
    $balance = $total_purchases - $total_payments;
    $total_all_balance += $balance;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Supplier Ledger</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        body {
            background: #f2f4f7;
            padding: 20px;
        }

        .ledger-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
        }

        .modal-ledger .modal-content {
            border-radius: 14px;
            border: 3px solid #198754;
        }

        .modal-ledger .modal-header {
            background: #198754;
            color: white;
            border-top-left-radius: 11px;
            border-top-right-radius: 11px;
        }

        .modal-legend {
            font-weight: 600;
            color: #198754;
        }
        #supp_total{

               margin-right: 32%;
               margin-top:2%

        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="container">
        <h2 class="mb-4 fw-bold">Supplier Ledger</h2>

        <div class="table-responsive ledger-table p-3 mb-4">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Supplier Name</th>
                        <th class="text-end">Balance</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers_list)): ?>
                        <tr><td colspan="3" class="text-center text-muted">No suppliers found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($suppliers_list as $c):
                            $sid = (int)$c['id'];
                            $total_purchases = $purchase_sums[$sid] ?? 0.0;
                            $total_payments = $payment_sums[$sid] ?? 0.0;
                            $balance = $total_purchases - $total_payments;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($c['name']) ?></td>
                            <td class="text-end <?= $balance >= 0 ? 'text-danger' : 'text-success' ?>">
                                <?= number_format($balance, 2) ?>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-success open-ledger" data-id="<?= $sid ?>" data-name="<?= htmlspecialchars($c['name']) ?>">
                                    View Ledger
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
                        <div class="text-end fs-6 fw-bold" id="supp_total">                            
                            Total Balance : 
                            <span class="<?= $total_all_balance >= 0 ? 'text-danger' : 'text-success' ?>">
                                ₹<?= number_format($total_all_balance, 2) ?>
                            </span>
                        </div>
        </div>

        
    </div>

    <!-- Modal -->
    <div class="modal fade modal-ledger" id="ledgerModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Supplier Ledger</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex gap-2 align-items-center mb-3">
                        <div class="modal-legend">Supplier:</div>
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
                        <div class="text-center text-muted">Select a supplier and click "Load Ledger".</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        const today = new Date().toISOString().slice(0, 10);
        const yearStart = new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10);
        $('#fromDate').val(yearStart);
        $('#toDate').val(today);

        let currentSupplierId = null;

        $('.open-ledger').on('click', function() {
            currentSupplierId = $(this).data('id');
            const name = $(this).data('name');
            $('#ledgerCustomerName').text(name);
            $('#ledgerContent').html('<div class="text-center text-muted">Select dates and click Load Ledger.</div>');
            $('#ledgerModal').modal('show');
        });

        $('#loadLedger').on('click', function() {
            if (!currentSupplierId) return;
            const from = $('#fromDate').val();
            const to = $('#toDate').val();
            if (!from || !to) {
                alert('Please select From and To dates');
                return;
            }
            $('#ledgerContent').html('<div class="text-center text-muted">Loading...</div>');
            $.get(window.location.pathname, { ajax: 1, id: currentSupplierId, from: from, to: to }, function(html) {
                $('#ledgerContent').html(html);
            }).fail(function(xhr) {
                $('#ledgerContent').html('<div class="text-danger">Error loading ledger: ' + xhr.responseText + '</div>');
            });
        });
    });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>
